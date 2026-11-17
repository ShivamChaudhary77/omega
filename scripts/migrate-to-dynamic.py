#!/usr/bin/env python3
"""Convert the whole static site into dynamic-site content (Phase 3).

Writes one JSON file per page into content/pages/ (or --out DIR):
  * every blog post        from data/blog-data.json
  * every location page    from scripts/location_pages_content.py
  * every project page     from data/projects.json (+ card text from index.html)
  * the hand-written pages (home, about, services, contact, FAQs, privacy,
    blog, projects, author, 404, the two ads landing pages), split into
    blocks. Sections that list posts/projects or hold a plain FAQ become
    structured blocks; every other section is kept as an exact "html" block.

Safe to re-run at any time. Until go-live the static site stays the source
of truth (new posts are still added to data/blog-data.json), so re-run this
right before the Phase 6 switch. Then verify with:
    php tests/compare-site.php

Usage: python3 scripts/migrate-to-dynamic.py [--out DIR]
"""
import html
import json
import re
import sys
from pathlib import Path
from urllib.parse import urljoin

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "scripts"))
from dynamic_mapping import post_page, location_page, project_page, text  # noqa: E402
from html_sections import body_inner, top_level_elements  # noqa: E402
from location_pages_content import CONTENT  # noqa: E402

SITE = "https://theomegagroup.in"

# Hand-written pages: file -> (id, type, layout)
SINGLE_PAGES = {
    "index.html": ("home", "home", "default"),
    "about.html": ("about", "about", "default"),
    "service.html": ("service", "service", "default"),
    "contact.html": ("contact", "contact", "default"),
    "faqs.html": ("faqs", "faqs", "default"),
    "privacy-policy.html": ("privacy-policy", "page", "default"),
    "blog.html": ("blog", "blog-index", "default"),
    "project.html": ("projects", "project-index", "default"),
    "author/shivam-kumar/index.html": ("author-shivam-kumar", "author", "default"),
    "404.html": ("not-found", "not-found", "default"),
    "free-design-consultation/index.html": ("free-design-consultation", "landing", "bare"),
    "gurgaon-home-interiors/index.html": ("gurgaon-home-interiors", "landing", "bare"),
}

NAV_KEYS = {"/index.html": "home", "/about.html": "about", "/service.html": "services",
            "/project.html": "projects", "/blog.html": "blog", "/contact.html": "contact"}

# Assets the templates already load on every default-layout page.
DEFAULT_CSS = {"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css",
               "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css",
               "/lib/animate/animate.min.css", "/css/bootstrap.min.css", "/css/style.css",
               "/lib/owlcarousel/assets/owl.carousel.min.css"}
DEFAULT_JS = {"/lib/wow/wow.min.js", "/lib/easing/easing.min.js", "/lib/waypoints/waypoints.min.js",
              "/lib/owlcarousel/owl.carousel.min.js", "/js/main.js", "/js/analytics.js"}
# Scripts the dynamic site replaces with server-side rendering.
OBSOLETE_JS = {"/js/schema.js", "/js/projects.js", "/js/single-post.js", "/js/blog-cms-grid.js", "/js/toc.js"}
# JSON-LD the dynamic site generates itself (anything else is kept as schemaExtra).
HOME_CAROUSEL_EXCLUDE = []  # projects the homepage carousel leaves out (set in main)
AUTO_SCHEMA = {"LocalBusiness", "Organization", "BreadcrumbList", "CollectionPage", "BlogPosting"}


def page_url(rel_file):
    if rel_file.endswith("/index.html"):
        return "/" + rel_file[: -len("index.html")]
    return "/" + rel_file


def resolve(url, base):
    """Make a relative URL root-absolute ('img/x' on /about.html -> '/img/x')."""
    if not url or re.match(r"^(?:[a-z][a-z0-9+.-]*:|/|#|\{)", url, re.I):
        return url
    resolved = urljoin(SITE + base, url)
    return resolved[len(SITE):] if resolved.startswith(SITE) else resolved


def absolutize_html(fragment, base):
    def repl(m):
        return f'{m.group(1)}="{resolve(m.group(2), base)}"'
    return re.sub(r'\b(href|src|poster|action|data-lightbox-src)="([^"]*)"', repl, fragment)


def strip_comments(fragment):
    return re.sub(r"<!--.*?-->", "", fragment, flags=re.S)


def plain(fragment):
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", "", fragment))).strip()


def head_of(page_html):
    return re.search(r"<head>(.*?)</head>", page_html, re.S).group(1)


def meta(head, name, attr="name"):
    m = re.search(rf'<meta {attr}="{re.escape(name)}"[^>]*content="([^"]*)"', head) or \
        re.search(rf'<meta content="([^"]*)"[^>]*{attr}="{re.escape(name)}"', head)
    return html.unescape(m.group(1)) if m else ""


def extract_seo(head, url):
    seo = {"title": plain(re.search(r"<title[^>]*>(.*?)</title>", head, re.S).group(1))}
    for key, name, attr in (("description", "description", "name"), ("keywords", "keywords", "name")):
        value = meta(head, name, attr)
        if value:
            seo[key] = value
    if "noindex" in meta(head, "robots"):
        seo["noindex"] = True
    canonical = re.search(r'<link rel="canonical"[^>]*href="([^"]*)"', head)
    if canonical:
        path = canonical.group(1).replace(SITE, "") or "/"
        if path not in (url, "/") or (path == "/" and url != "/index.html"):
            seo["canonical"] = path
    og_image = meta(head, "og:image", "property")
    if og_image:
        seo["ogImage"] = og_image.replace(SITE, "")
    preload = re.search(r'<link rel="preload" as="image" href="([^"]*)"', head)
    if preload:
        seo["preloadImage"] = resolve(preload.group(1), url)
    return seo


def extract_assets(head, body, url, bare):
    # Optional "?v=N" cache-busting query is allowed (e.g. gurgaon-landing.css?v=2).
    css = [resolve(h, url) for h in re.findall(r'<link href="([^"]+\.css(?:\?[^"]*)?)" rel="stylesheet"', head)]
    js = [resolve(s, url) for s in re.findall(r'<script src="([^"]+)"></script>', body)]
    assets = {}
    extra_css = [c for c in css if c not in DEFAULT_CSS]
    if extra_css:
        assets["css"] = extra_css
    if "/lib/owlcarousel/assets/owl.carousel.min.css" in css:
        assets["owlCarousel"] = True
    if "/lib/animate/animate.min.css" not in css:
        assets["animateCss"] = False
    if bare:
        assets["scripts"] = js
    else:
        extra_js = [j for j in js if j not in DEFAULT_JS and j not in OBSOLETE_JS and not j.startswith("http")]
        if extra_js:
            assets["js"] = extra_js
    return assets


def extract_schema(head):
    """JSON-LD blocks the dynamic site does not generate itself, plus the
    breadcrumb (from schema) and whether a FAQPage was present."""
    extra, crumbs, has_faq = [], None, False
    for raw in re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', head, re.S):
        raw = raw.strip()
        if not raw:
            continue
        data = json.loads(raw)
        for item in data if isinstance(data, list) else [data]:
            kind = item.get("@type")
            if kind == "BreadcrumbList":
                crumbs = [{"name": text(i["name"]), "path": i["item"].replace(SITE, "") or "/"} for i in item["itemListElement"]]
            elif kind == "FAQPage":
                has_faq = True
                extra.append(item)
            elif kind not in AUTO_SCHEMA:
                extra.append(item)
    return extra, crumbs, has_faq


def breadcrumb_from_hero(el):
    ol = re.search(r'<ol class="breadcrumb[^"]*">(.*?)</ol>', el, re.S)
    if not ol:
        return None
    crumbs = []
    for li in re.findall(r"<li[^>]*>(.*?)</li>", ol.group(1), re.S):
        link = re.search(r'href="([^"]*)"', li)
        crumbs.append({"name": plain(li), "path": link.group(1) if link else None})
    return crumbs


def cta_from(el, site_cta, url):
    found = {
        "eyebrow": plain((re.search(r'<span class="text-uppercase[^"]*"[^>]*>(.*?)</span>', el, re.S) or [None, ""])[1]),
        "heading": plain((re.search(r'<h2[^>]*>(.*?)</h2>', el, re.S) or [None, ""])[1]),
        "text": plain((re.search(r'<p class="text-white[^"]*">(.*?)</p>', el, re.S) or [None, ""])[1]),
        "buttonLabel": plain((re.search(r'<a [^>]*class="btn[^"]*"[^>]*>(.*?)</a>', el, re.S) or [None, ""])[1]),
        "buttonHref": resolve((re.search(r'<a href="([^"]*)" class="btn', el) or [None, ""])[1], url),
        "image": resolve((re.search(r'<img [^>]*src="([^"]*)"', el) or [None, ""])[1], url),
        "imageAlt": html.unescape((re.search(r'<img [^>]*alt="([^"]*)"', el, re.S) or [None, ""])[1]),
    }
    return {k: v for k, v in found.items() if v and v != site_cta.get(k)}


def faq_block(el, url):
    """A simple FAQ section (one heading + one accordion) -> faq block, else None.
    Answers with links keep them as answerHtml."""
    if el.count('class="accordion"') != 1 or 'lead-answer' in el:
        return None
    heading = re.search(r'<h2[^>]*>(.*?)</h2>', el, re.S)
    acc_id = re.search(r'<div class="accordion" id="([^"]+)"', el).group(1)
    items = []
    for q, a in re.findall(r'<button class="accordion-button[^"]*"[^>]*>(.*?)</button>.*?<div class="accordion-body">(.*?)</div>', el, re.S):
        if "<" in a:
            items.append({"question": plain(q), "answerHtml": absolutize_html(a.strip(), url)})
        else:
            items.append({"question": plain(q), "answer": plain(a)})
    if not items:
        return None
    block = {"type": "faq", "id": acc_id, "items": items}
    if heading:
        block["headingHtml"] = heading.group(1).strip()
    return block


def section_heading(el):
    m = re.search(r'<h2 class="h1 mb-3">(.*?)</h2>', el, re.S)
    return re.sub(r"\s+", " ", m.group(1)).strip() if m else None


def section_intro(el):
    m = re.search(r'<p class="mx-auto[^"]*"[^>]*>(.*?)</p>', el, re.S)
    return plain(m.group(1)) if m else None


def block_for(el, url):
    """Turn one top-level element into a block (or None to drop it)."""
    if 'project-carousel' in el:
        button = re.search(r'<a href="([^"]*)" class="btn[^"]*">(.*?)</a>', el, re.S)
        return {"type": "project-cards", "style": "carousel", "exclude": HOME_CAROUSEL_EXCLUDE, "headingHtml": section_heading(el),
                "intro": section_intro(el), "buttonLabel": plain(button.group(2)) if button else None,
                "buttonHref": resolve(button.group(1), url) if button else None}
    if "HOME-BLOG:START" in el:
        button = re.search(r'<a href="([^"]*)" class="btn[^"]*">(.*?)</a>', el, re.S)
        return {"type": "latest-posts", "headingHtml": section_heading(el), "intro": section_intro(el),
                "count": el.count('class="blog-card"'),
                "buttonLabel": plain(button.group(2)) if button else None,
                "buttonHref": resolve(button.group(1), url) if button else None}
    if 'id="blog-cms-section"' in el:
        return None  # the old MySQL blog CMS teaser; retired
    if 'id="blog-grid"' in el:
        return {"type": "blog-listing"}
    if 'data-project-slug=' in el and 'class="row g-4"' in el:
        button = re.search(r'<a href="([^"]*)" class="btn[^"]*">(.*?)</a>', el, re.S)
        return {"type": "project-cards", "style": "grid", "headingHtml": section_heading(el),
                "intro": section_intro(el), "buttonLabel": plain(button.group(2)) if button else None,
                "buttonHref": resolve(button.group(1), url) if button else None}
    if 'class="accordion"' in el:
        block = faq_block(el, url)
        if block:
            return block
    return {"type": "html", "html": absolutize_html(strip_comments(el), url).strip()}


def clean(d):
    return {k: v for k, v in d.items() if v not in (None, "", [], {})}


def single_page(rel_file, page_id, page_type, layout, site_cta):
    url = page_url(rel_file)
    source = (ROOT / rel_file).read_text(encoding="utf-8")
    head = head_of(source)
    body = body_inner(source)
    bare = layout == "bare"
    extra_schema, schema_crumbs, has_faq_schema = extract_schema(head)

    page = {
        "id": page_id, "type": page_type, "status": "published", "urlPath": url,
        "seo": extract_seo(head, url),
    }
    if url == "/index.html":
        page["aliases"] = ["/"]
    if bare:
        page["layout"] = "bare"

    blocks, crumbs, cta = [], schema_crumbs, None
    h1 = re.search(r"<h1[^>]*>(.*?)</h1>", body, re.S)
    for el in top_level_elements(body):
        if el.startswith("<script"):
            continue
        if not bare:
            if el.startswith('<div id="spinner"') or 'class="container-fluid sticky-top"' in el.split(">", 1)[0] \
                    or "footer pt-5" in el.split(">", 1)[0] or "back-to-top" in el.split(">", 1)[0]:
                continue
            if "newsletter" in el.split(">", 1)[0]:
                cta = cta_from(el, site_cta, url)
                continue
            if "hero-header" in el.split(">", 1)[0] and crumbs is None:
                crumbs = breadcrumb_from_hero(el)
        block = block_for(el, url) if not bare else {"type": "html", "html": absolutize_html(strip_comments(el), url).strip()}
        if block:
            blocks.append(clean(block))

    if h1:
        page["h1"] = plain(h1.group(1))
    if crumbs:
        page["breadcrumb"] = [{"name": c["name"], "path": None if c["path"] is None or i == len(crumbs) - 1 else resolve(c["path"], url)}
                              for i, c in enumerate(crumbs)]
    active = re.search(r'<a href="([^"]*)" class="nav-item nav-link active"', body)
    if active and not bare:
        key = NAV_KEYS.get(resolve(active.group(1), url))
        if key:
            page["navActive"] = key
    if cta:
        page["cta"] = cta
    elif not bare and "newsletter" not in body:
        page["cta"] = False
    page["blocks"] = blocks
    if extra_schema:
        page["schemaExtra"] = extra_schema
    assets = extract_assets(head, body, url, bare)
    if assets:
        page["assets"] = assets
    return page


def cards_in(rel_file):
    """[(detail slug, {"title", "alt", "image"})] for every project card on a page, in order."""
    src = (ROOT / rel_file).read_text(encoding="utf-8")
    out = []
    for m in re.finditer(r'<a href="/project/([^/]+)/" class="project-card[^>]*>(.*?)</a>', src, re.S):
        inner = m.group(2)
        img = re.search(r"<img[^>]*>", inner, re.S).group(0)
        out.append((m.group(1), {
            "title": html.unescape(re.search(r'<h4 class="mb-1">([^<]*)</h4>', inner).group(1)),
            "alt": html.unescape(re.search(r'alt="([^"]*)"', img).group(1)),
            "image": re.search(r'src="([^"]*)"', img).group(1),
        }))
    return out


def main():
    out = Path(sys.argv[sys.argv.index("--out") + 1]) if "--out" in sys.argv else ROOT / "content" / "pages"
    out.mkdir(parents=True, exist_ok=True)
    site = json.loads((ROOT / "content" / "site.json").read_text(encoding="utf-8"))
    written = []

    sitemap_meta = {}
    sm = (ROOT / "sitemap.xml").read_text(encoding="utf-8")
    for order, m in enumerate(re.finditer(r"<loc>([^<]*)</loc>\s*<lastmod>([^<]*)</lastmod>\s*<changefreq>([^<]*)</changefreq>\s*<priority>([^<]*)</priority>", sm), 1):
        sitemap_meta[m.group(1).replace(SITE, "") or "/"] = {"lastmod": m.group(2), "changefreq": m.group(3), "priority": m.group(4), "order": order}

    def write(page):
        page = {k: v for k, v in page.items() if v is not None}
        if page["type"] != "blog-post" and page["urlPath"] in sitemap_meta:
            page["sitemap"] = sitemap_meta[page["urlPath"]]
        (out / f"{page['id']}.json").write_text(json.dumps(page, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        written.append(page["id"])

    posts = json.loads((ROOT / "data" / "blog-data.json").read_text(encoding="utf-8"))
    for i, post in enumerate(posts):
        write(post_page(post, i + 1))
    for slug, data in CONTENT.items():
        write(location_page(slug, data))
    grid_cards = dict(cards_in("project.html"))      # the projects page grid
    home_cards = dict(cards_in("index.html"))        # the homepage carousel
    projects = [p for p in json.loads((ROOT / "data" / "projects.json").read_text(encoding="utf-8"))["projects"] if p.get("detailPageSlug")]
    for i, project in enumerate(projects):
        slug = project["detailPageSlug"]
        card = dict(grid_cards.get(slug) or home_cards.get(slug) or {})
        if card.get("image") == project["image"]:
            card.pop("image")
        page = project_page(project, i + 1, card or None)
        home = home_cards.get(slug)
        if home:
            carousel = {k: v for k, v in home.items() if v != (grid_cards.get(slug) or {}).get(k)}
            if carousel.get("image") == project["image"]:
                carousel.pop("image")
            if carousel:
                page["carouselCard"] = carousel
        write(page)
    global HOME_CAROUSEL_EXCLUDE
    HOME_CAROUSEL_EXCLUDE = [p["detailPageSlug"] for p in projects if p["detailPageSlug"] not in home_cards]
    for rel_file, (page_id, page_type, layout) in SINGLE_PAGES.items():
        write(single_page(rel_file, page_id, page_type, layout, site.get("cta", {})))

    # Files were rewritten in place, which does not change the folder's
    # timestamp; drop the URL index so the site rebuilds it on the next request.
    index_file = out.parent / "_cache" / "index.json"
    if index_file.exists():
        index_file.unlink()
    pages_cache = out.parent / "_cache" / "pages"
    for cached in pages_cache.glob("*.json") if pages_cache.exists() else []:
        cached.unlink()

    if len(written) != len(set(written)):
        raise SystemExit("Duplicate page ids: " + ", ".join(sorted({i for i in written if written.count(i) > 1})))
    stale = sorted(p.stem for p in out.glob("*.json") if p.stem not in set(written))
    print(f"Wrote {len(written)} page(s) to {out.relative_to(ROOT) if out.is_relative_to(ROOT) else out}: "
          f"{len(posts)} posts, {len(CONTENT)} locations, {len(projects)} projects, {len(SINGLE_PAGES)} hand-written pages")
    if stale:
        print("Pages in the folder that the static site no longer has (left untouched): " + ", ".join(stale))


if __name__ == "__main__":
    main()
