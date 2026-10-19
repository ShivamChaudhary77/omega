#!/usr/bin/env python3
"""Regenerate sitemap-blog.xml from data/blog-data.json — the single source
of truth for every static blog post.

This file was previously hand-maintained and fell out of sync: by October
2026 it was missing every post published after the 6th, so the sitemap
channel search engines are told to use (robots.txt lists it under Sitemap:)
carried no signal at all for those pages. Re-run this after publishing any
post so new URLs are never missing from the sitemap again.

Does not touch sitemap.xml (static top-level/location/project pages) or
blog-cms/public/sitemap.php (the CMS's own dynamic sitemap for CMS-managed
posts) — both are separate, already-automated channels.

Usage: python3 scripts/build-sitemap.py
"""
import json
from pathlib import Path

SITE_ROOT = Path(__file__).resolve().parent.parent
DATA = SITE_ROOT / "data" / "blog-data.json"
OUT = SITE_ROOT / "sitemap-blog.xml"
SITE_BASE = "https://theomegagroup.in"


def main():
    posts = json.loads(DATA.read_text(encoding="utf-8"))
    posts = [p for p in posts if p.get("slug") and p.get("urlPath")]

    lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ]
    for p in posts:
        loc = SITE_BASE + p["urlPath"]
        lastmod = (p.get("modifiedDate") or p["publishDate"])[:10]
        lines.append(
            f"  <url><loc>{loc}</loc><lastmod>{lastmod}</lastmod>"
            f"<changefreq>monthly</changefreq><priority>0.6</priority></url>"
        )
    lines.append("</urlset>")

    OUT.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"sitemap-blog.xml regenerated with {len(posts)} post(s)")


if __name__ == "__main__":
    main()
