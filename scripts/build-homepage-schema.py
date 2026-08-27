#!/usr/bin/env python3
"""Regenerate the static Organization/LocalBusiness JSON-LD block in
index.html between the ORG-SCHEMA:START/END markers, from
data/organization.json.

This exists because js/schema.js only injects this schema client-side —
invisible to any crawler that doesn't execute JavaScript. Re-run this after
editing data/organization.json so the baked-in copy in index.html stays in
sync; do not hand-edit the block between the markers.

Usage: python3 scripts/build-homepage-schema.py
"""
import json
import re
from pathlib import Path

SITE_ROOT = Path(__file__).resolve().parent.parent
INDEX_HTML = SITE_ROOT / "index.html"
ORG_JSON = SITE_ROOT / "data" / "organization.json"

START_MARKER = "<!-- ORG-SCHEMA:START -->"
END_MARKER = "<!-- ORG-SCHEMA:END -->"


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
        "geo": strip_empty({
            "@type": "GeoCoordinates",
            "latitude": org["geo"]["latitude"],
            "longitude": org["geo"]["longitude"],
        }) if org.get("geo") else None,
        "areaServed": [
            {"@type": "AdministrativeArea", "name": a} for a in org.get("areaServed", [])
        ] or None,
    }
    return strip_empty(ld)


def main():
    org = json.loads(ORG_JSON.read_text(encoding="utf-8"))
    ld = build_local_business_ld(org)
    payload = json.dumps(ld, ensure_ascii=False).replace("</", "<\\/")
    block = f'{START_MARKER}\n<script type="application/ld+json">{payload}</script>\n    {END_MARKER}'

    html = INDEX_HTML.read_text(encoding="utf-8")
    pattern = re.compile(re.escape(START_MARKER) + r".*?" + re.escape(END_MARKER), re.S)
    if not pattern.search(html):
        raise SystemExit("ORG-SCHEMA markers not found in index.html — add them once, then re-run.")
    html = pattern.sub(block, html, count=1)
    INDEX_HTML.write_text(html, encoding="utf-8")
    print("index.html Organization/LocalBusiness JSON-LD refreshed from data/organization.json")


if __name__ == "__main__":
    main()
