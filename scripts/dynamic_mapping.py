#!/usr/bin/env python3
"""Convert the static site's data sources into dynamic-site page JSON.

One function per source:
  post_page(post)                      data/blog-data.json item  -> "blog-post" page
  location_page(slug, data)            location_pages_content.CONTENT item -> "location" page
  project_page(project, sort_order)    data/projects.json item   -> "project" page

Used by tests/make-fixtures.py (Phase 2) and by the full migration (Phase 3),
so both always share the same mapping. Plain-text fields have HTML entities
decoded (templates escape them again); fields named "html"/"...Html" keep
their HTML.
"""
import html

TYPE_COPY = {
    "Apartment Interior": (
        "This apartment interior was designed and delivered end-to-end by The Omega Group — layout, "
        "lighting, furniture, and finishes planned as one cohesive whole rather than room-by-room "
        "afterthoughts. As with every Omega Group apartment project, the brief balanced everyday livability "
        "with a considered, premium finish."
    ),
    "Villa Interior": (
        "This villa interior was designed and delivered end-to-end by The Omega Group, balancing the "
        "expansive scale of independent-villa living with a considered, liveable luxury — indoor-outdoor "
        "flow, layered lighting, and finishes chosen to suit the home's proportions rather than a generic "
        "template."
    ),
    "Modular Kitchen": (
        "This modular kitchen was designed and installed by The Omega Group, built around real cooking "
        "habits and storage needs rather than a stock layout — the same zoning and material-selection "
        "process we walk every renovation client through."
    ),
}

APPROACH_HTML = (
    "<p>Every project — including this one — follows the same process: a detailed "
    "consultation about the space and how it's actually used, design and material sign-off before any "
    "work begins, execution by our own site team with quality checks at every milestone, and a final "
    "walkthrough before handover. It's the same process we walk through in detail on our "
    '<a href="/interior-designers-gurgaon/">Gurgaon interior design page</a>.</p>'
)


def text(value):
    """Plain text: decode HTML entities (&amp; -> &)."""
    return html.unescape(value) if isinstance(value, str) else value


def root_path(path):
    """'img/x.webp' -> '/img/x.webp' (pages are served from nested URLs)."""
    if not path or path.startswith(("/", "http://", "https://")):
        return path
    return "/" + path


def post_page(post, seq=None):
    """seq: the post's position in blog-data.json; breaks ties between posts
    published at the same moment so listings keep their current order."""
    page = {
        "id": post["slug"],
        "type": "blog-post",
        "status": "published",
        "urlPath": post["urlPath"],
        "seo": {
            "title": text(post["metaTitle"]),
            "description": text(post["metaDescription"]),
            "focusKeyword": text(post.get("focusKeyword", "")),
        },
        "title": text(post["title"]),
        "category": post["category"],
        "tags": [text(t) for t in post.get("tags", [])],
        "publishDate": post["publishDate"],
        "modifiedDate": post.get("modifiedDate") or post["publishDate"],
        "author": post.get("author") or "Shivam Kumar",
        "reviewedBy": post.get("reviewedBy"),
        "readingTimeMinutes": post.get("readingTimeMinutes", 0),
        "featuredImage": post["featuredImage"],
        "excerpt": text(post.get("excerpt", "")),
        "keyTakeaways": [text(t) for t in post.get("keyTakeaways", [])],
        "contentHtml": post.get("contentHtml", ""),
        "faqs": [{"question": text(f["question"]), "answer": text(f["answer"])} for f in post.get("faqs", [])],
        "internalLinks": [{"text": text(l.get("text", "")), "href": l["href"]} for l in post.get("internalLinks", [])],
    }
    if seq is not None:
        page["seq"] = seq
    return page


def location_page(slug, data):
    return {
        "id": slug,
        "type": "location",
        "status": "published",
        "urlPath": f"/{slug}/",
        "seo": {
            "title": text(data["meta_title"]),
            "description": text(data["meta_description"]),
            "keywords": text(data.get("keywords", "")),
        },
        "h1": text(data["h1"]),
        "heroImage": root_path(data["hero_image"]),
        "breadcrumb": [{"name": text(name), "path": path} for name, path in data["breadcrumb"]],
        "directAnswer": text(data["direct_answer"]),
        "facts": [{"label": text(label), "value": text(value)} for label, value in (data.get("facts") or [])],
        "keyTakeaways": [text(t) for t in data["takeaways"]],
        "sections": [{"heading": text(h), "html": body.strip()} for h, body in data["sections"]],
        "faqs": [{"question": text(q), "answer": text(a)} for q, a in data["faqs"]],
        "internalLinks": [{"text": text(t), "href": h} for t, h in data["internal_links"]],
        "ctaContext": text(data["cta_context"]),
    }


def project_page(p, sort_order, card=None):
    """card: optional {"title", "alt"} as shown on the homepage/projects grid."""
    slug = p["detailPageSlug"]
    location_bit = f", {p['location']}" if p.get("location") else ""
    minimal = bool(p.get("minimal"))
    page = {
        "id": slug,
        "type": "project",
        "status": "published",
        "urlPath": f"/project/{slug}/",
        "seo": {
            "title": text(p.get("metaTitle") or f"{p['name']} | {p['type']} by The Omega Group"),
            "description": text(p.get("metaDescription") or (
                f"{p['name']} — a {p['type'].lower()} designed and delivered end-to-end by The Omega Group"
                f"{location_bit}. See the finished space and how we approach every project.")),
        },
        "name": p["name"],
        "projectType": p["type"],
        "configuration": p.get("configuration"),
        "location": p.get("location"),
        "sizeSqFt": p.get("sizeSqFt"),
        "budgetBand": p.get("budgetBand"),
        "timeline": p.get("timeline"),
        "image": p["image"],
        "imageAlt": f"{p['name']} — {p['type']} designed by The Omega Group",
        "gallery": p.get("gallery") or [],
        "h1": text(p.get("h1") or f"{p['name']} — {p['type']}{location_bit}"),
        "leadAnswer": None if minimal else (
            f"{p['name']} is a {p['type'].lower()} designed and delivered end-to-end by The Omega Group"
            f"{location_bit} — design, material sourcing, and on-site execution managed by one team from "
            f"first consultation to final handover."),
        "sections": [] if minimal else [
            {"heading": "About This Project", "html": f"<p>{TYPE_COPY.get(p['type'], TYPE_COPY['Apartment Interior'])}</p>"},
            {"heading": "The Omega Group Approach", "html": APPROACH_HTML},
        ],
        "relatedBlogPosts": p.get("relatedBlogPosts") or ([p["relatedBlogPost"]] if p.get("relatedBlogPost") else []),
        "sortOrder": sort_order,
    }
    if card:
        page["card"] = card
    return page
