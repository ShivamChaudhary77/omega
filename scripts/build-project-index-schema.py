#!/usr/bin/env python3
"""Bake Organization/LocalBusiness + BreadcrumbList + CollectionPage(ItemList)
JSON-LD into project.html between the PROJECT-SCHEMA:START/END markers.

project.html previously shipped with zero structured data in its raw HTML —
js/schema.js only injects Organization/BreadcrumbList client-side, invisible
to any crawler that doesn't execute JavaScript (same issue ORG-SCHEMA on
index.html already solves; see scripts/build-homepage-schema.py). The
ItemList mirrors the project.html grid exactly — same 14 projects, same
order, same URLs — straight from data/projects.json, so it can never drift
from what a visitor actually sees.

Re-run after editing data/organization.json or data/projects.json; do not
hand-edit the block between the markers.

Usage: python3 scripts/build-project-index-schema.py
"""
import json
import re
from pathlib import Path

SITE_ROOT = Path(__file__).resolve().parent.parent
PROJECT_HTML = SITE_ROOT / "project.html"
ORG_JSON = SITE_ROOT / "data" / "organization.json"
PROJECTS_JSON = SITE_ROOT / "data" / "projects.json"
SITE_BASE = "https://theomegagroup.in"

START_MARKER = "<!-- PROJECT-SCHEMA:START -->"
END_MARKER = "<!-- PROJECT-SCHEMA:END -->"


def strip_empty(d):
    return {k: v for k, v in d.items() if v not in (None, "", [], {})}


def build_local_business_ld(org):
    ld = {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "@id": org["url"] + "#organization",
        "name": org["name"],
        "alternateName": org.get("alternateName"),
        "url": org["url"],
        "logo": org["logo"],
        "image": org.get("image"),
        "telephone": org["telephone"],
        "email": org.get("email"),
        "priceRange": org.get("priceRange"),
        "foundingDate": org.get("foundingDate"),
        "sameAs": org.get("sameAs") or None,
        "address": strip_empty({
            "@type": "PostalAddress",
            "streetAddress": org["address"]["streetAddress"],
            "addressLocality": org["address"]["addressLocality"],
            "addressRegion": org["address"]["addressRegion"],
            "postalCode": org["address"].get("postalCode"),
            "addressCountry": org["address"]["addressCountry"],
        }) if org.get("address") else None,
    }
    return strip_empty(ld)


def build_breadcrumb_ld():
    return {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {"@type": "ListItem", "position": 1, "name": "Home", "item": f"{SITE_BASE}/index.html"},
            {"@type": "ListItem", "position": 2, "name": "Projects", "item": f"{SITE_BASE}/project.html"},
        ],
    }


def build_collection_ld(projects):
    items = [p for p in projects if p.get("detailPageSlug")]
    return {
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        "name": "Our Projects | The Omega Group",
        "url": f"{SITE_BASE}/project.html",
        "mainEntity": {
            "@type": "ItemList",
            "itemListElement": [
                {
                    "@type": "ListItem",
                    "position": i + 1,
                    "name": p["name"],
                    "url": f"{SITE_BASE}/project/{p['detailPageSlug']}/",
                }
                for i, p in enumerate(items)
            ],
        },
    }


def ld_script(data):
    payload = json.dumps(data, ensure_ascii=False).replace("</", "<\\/")
    return f'<script type="application/ld+json">{payload}</script>'


def main():
    org = json.loads(ORG_JSON.read_text(encoding="utf-8"))
    projects = json.loads(PROJECTS_JSON.read_text(encoding="utf-8"))["projects"]

    block = "\n".join([
        START_MARKER,
        ld_script(build_local_business_ld(org)),
        ld_script(build_breadcrumb_ld()),
        ld_script(build_collection_ld(projects)),
        f"    {END_MARKER}",
    ])

    html = PROJECT_HTML.read_text(encoding="utf-8")
    pattern = re.compile(re.escape(START_MARKER) + r".*?" + re.escape(END_MARKER), re.S)
    if pattern.search(html):
        html = pattern.sub(block, html, count=1)
    else:
        html = html.replace("</head>", f"    {block}\n</head>", 1)
    PROJECT_HTML.write_text(html, encoding="utf-8")
    print("project.html LocalBusiness/BreadcrumbList/CollectionPage JSON-LD refreshed")


if __name__ == "__main__":
    main()
