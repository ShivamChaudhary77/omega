#!/usr/bin/env python3
"""Generate a static /<category>/<slug>/index.html for every post in
data/blog-data.json, so each article has a real, crawlable SEO-friendly URL
(no query strings, no server rewrite rules needed) on any static host.

Each generated file starts from the single-post.html shell, but is NOT
byte-identical to it: the <head> (title, meta description, canonical,
hreflang, OG/Twitter tags) and the Article/BreadcrumbList/FAQPage JSON-LD are
baked in per-post at build time, from the same data js/single-post.js uses to
render them client-side. This matters because a non-JS-executing consumer
(several AI/LLM crawlers, "view source", basic scrapers) previously saw an
empty <title>, a generic fallback description, and canonical="/blog.html" on
every single post — js/single-post.js remains in place and overwrites these
with the identical values at runtime, so it's now a redundant confirmation
pass rather than the only source of truth.

The visible post body (post-body, FAQ accordion, related posts, etc.) is
still rendered client-side by js/single-post.js from data/blog-data.json —
that part of the architecture is unchanged; only the SEO-critical <head> tags
and structured data are now baked statically.

Usage: python3 scripts/build-post-pages.py
"""
import json
import re
from pathlib import Path

SITE_ROOT = Path(__file__).resolve().parent.parent
TEMPLATE = SITE_ROOT / "single-post.html"
DATA = SITE_ROOT / "data" / "blog-data.json"
SITE_BASE = "https://theomegagroup.in"

# Category directories that must never collide with existing top-level files.
RESERVED = {"css", "js", "img", "lib", "video", "data", "scripts",
            "index.html", "about.html", "service.html", "project.html",
            "contact.html", "blog.html", "single-post.html"}


def esc_attr(value):
    """Escape a string for safe use inside an HTML attribute value."""
    return (
        str(value)
        .replace("&", "&amp;")
        .replace('"', "&quot;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
    )


def json_ld_script(ld_id, data):
    """Render a populated <script type="application/ld+json" id="..."> tag.
    Escapes "</" so a literal "</script" inside JSON content (e.g. in an FAQ
    answer) can never prematurely close the tag."""
    if not data:
        return f'<script type="application/ld+json" id="{ld_id}"></script>'
    payload = json.dumps(data, ensure_ascii=False).replace("</", "<\\/")
    return f'<script type="application/ld+json" id="{ld_id}">{payload}</script>'


def build_article_ld(post, url):
    return {
        "@context": "https://schema.org",
        "@type": "BlogPosting",
        "headline": post["title"],
        "description": post["metaDescription"],
        "image": SITE_BASE + post["featuredImage"]["src"],
        "datePublished": post["publishDate"],
        "dateModified": post.get("modifiedDate") or post["publishDate"],
        "author": {"@type": "Organization", "name": post.get("author") or "The Omega Group"},
        "publisher": {
            "@type": "Organization",
            "name": "The Omega Group",
            "logo": {"@type": "ImageObject", "url": SITE_BASE + "/img/logo.png"},
        },
        "mainEntityOfPage": {"@type": "WebPage", "@id": url},
        "keywords": ", ".join(filter(None, [post.get("focusKeyword")] + (post.get("tags") or []))),
    }


def build_breadcrumb_ld(post, url):
    return {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "Home", "item": f"{SITE_BASE}/index.html"},
            {"@type": "ListItem", "position": 2, "name": "Blog", "item": f"{SITE_BASE}/blog.html"},
            {"@type": "ListItem", "position": 3, "name": post["title"], "item": url},
        ],
    }


def build_faq_ld(post):
    faqs = post.get("faqs") or []
    if not faqs:
        return None
    return {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": f["question"],
                "acceptedAnswer": {"@type": "Answer", "text": f["answer"]},
            }
            for f in faqs
        ],
    }


def render_head(template_html, post):
    """Return template_html with the <head> SEO tags and JSON-LD placeholders
    replaced by this post's real, baked-in values."""
    url = post.get("canonicalUrl") or (SITE_BASE + post["urlPath"])
    title = esc_attr(post["metaTitle"])
    description = esc_attr(post["metaDescription"])
    canonical = esc_attr(url)
    og_image = esc_attr(SITE_BASE + post["featuredImage"]["src"])

    html = template_html

    html = html.replace(
        '<title id="doc-title">Article | The Omega Group Blog</title>',
        f'<title id="doc-title">{title}</title>',
    )
    html = html.replace(
        '<meta name="description" id="meta-description" content="Interior design insights from The Omega Group, Gurgaon\'s luxury interior design and turnkey execution studio.">',
        f'<meta name="description" id="meta-description" content="{description}">',
    )
    html = html.replace(
        '<link rel="canonical" id="meta-canonical" href="https://theomegagroup.in/blog.html">',
        f'<link rel="canonical" id="meta-canonical" href="{canonical}">',
    )
    html = html.replace(
        '<link rel="alternate" hreflang="en" id="meta-hreflang" href="https://theomegagroup.in/blog.html">',
        f'<link rel="alternate" hreflang="en" id="meta-hreflang" href="{canonical}">',
    )
    html = html.replace(
        '<meta property="og:title" id="og-title" content="The Omega Group Blog">',
        f'<meta property="og:title" id="og-title" content="{title}">',
    )
    html = html.replace(
        '<meta property="og:description" id="og-description" content="Interior design insights from The Omega Group.">',
        f'<meta property="og:description" id="og-description" content="{description}">',
    )
    html = html.replace(
        '<meta property="og:url" id="og-url" content="https://theomegagroup.in/blog.html">',
        f'<meta property="og:url" id="og-url" content="{canonical}">',
    )
    html = html.replace(
        '<meta property="og:image" id="og-image" content="https://theomegagroup.in/img/about-1.jpg">',
        f'<meta property="og:image" id="og-image" content="{og_image}">',
    )
    html = html.replace(
        '<meta name="twitter:title" id="twitter-title" content="The Omega Group Blog">',
        f'<meta name="twitter:title" id="twitter-title" content="{title}">',
    )
    html = html.replace(
        '<meta name="twitter:description" id="twitter-description" content="Interior design insights from The Omega Group.">',
        f'<meta name="twitter:description" id="twitter-description" content="{description}">',
    )

    html = html.replace(
        '<script type="application/ld+json" id="ld-article"></script>',
        json_ld_script("ld-article", build_article_ld(post, url)),
    )
    html = html.replace(
        '<script type="application/ld+json" id="ld-breadcrumb"></script>',
        json_ld_script("ld-breadcrumb", build_breadcrumb_ld(post, url)),
    )
    html = html.replace(
        '<script type="application/ld+json" id="ld-faq"></script>',
        json_ld_script("ld-faq", build_faq_ld(post)),
    )

    return html


def main():
    posts = json.loads(DATA.read_text(encoding="utf-8"))
    template_html = TEMPLATE.read_text(encoding="utf-8")

    written, skipped_dirs = [], set()
    for post in posts:
        url_path = post["urlPath"].strip("/")  # "<category>/<slug>"
        category_slug = url_path.split("/")[0]
        if category_slug in RESERVED:
            print(f"SKIP {post['slug']}: category slug '{category_slug}' collides with an existing top-level path")
            continue
        target_dir = SITE_ROOT / url_path
        target_dir.mkdir(parents=True, exist_ok=True)
        page_html = render_head(template_html, post)
        (target_dir / "index.html").write_text(page_html, encoding="utf-8")
        written.append(str(target_dir.relative_to(SITE_ROOT)))
        skipped_dirs.add(category_slug)

    print(f"Wrote {len(written)} post pages across {len(skipped_dirs)} category directories.")


if __name__ == "__main__":
    main()
