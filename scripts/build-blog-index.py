#!/usr/bin/env python3
"""Bake the pinned post + first page of the blog grid into blog.html's raw
HTML, between the BLOG-PINNED:START/END and BLOG-GRID:START/END markers.

js/blog.js only renders these client-side after fetching
data/blog-data.json, so a crawler (or any visitor) that doesn't execute
JavaScript sees an empty grid. This script mirrors js/blog.js's own sort
(newest publishDate first), pinned-post rule (newest post) and page size (9)
so the baked-in markup matches exactly what js/blog.js re-renders on load —
no visible flash, no mismatch for crawlers.

Re-run after editing data/blog-data.json; do not hand-edit the markers.

Usage: python3 scripts/build-blog-index.py
"""
import html
import json
import re
from datetime import datetime
from pathlib import Path

SITE_ROOT = Path(__file__).resolve().parent.parent
BLOG_HTML = SITE_ROOT / "blog.html"
BLOG_DATA = SITE_ROOT / "data" / "blog-data.json"

PAGE_SIZE = 9

PINNED_START = "<!-- BLOG-PINNED:START -->"
PINNED_END = "<!-- BLOG-PINNED:END -->"
GRID_START = "<!-- BLOG-GRID:START -->"
GRID_END = "<!-- BLOG-GRID:END -->"


def esc(s):
    return html.escape(s or "", quote=True)


def format_date(iso):
    try:
        d = datetime.fromisoformat(iso)
    except (TypeError, ValueError):
        return ""
    return f"{d.day} {d.strftime('%b %Y')}"


def card_html(post):
    img = post["featuredImage"]
    return f"""
        <div class="col-md-6 col-lg-4">
            <a href="{esc(post['urlPath'])}" class="blog-card">
                <div class="blog-card-img">
                    <img src="{esc(img['src'])}" alt="{esc(img['alt'])}"
                        width="{img['width']}" height="{img['height']}" loading="lazy">
                    <span class="blog-badge">{esc(post['category'])}</span>
                </div>
                <div class="blog-card-body">
                    <div class="blog-card-meta">
                        <span><i class="bi bi-calendar3"></i>{esc(format_date(post['publishDate']))}</span>
                        <span><i class="bi bi-clock"></i>{post['readingTimeMinutes']} min read</span>
                    </div>
                    <h3>{esc(post['title'])}</h3>
                    <p>{esc(post['excerpt'])}</p>
                    <span class="blog-card-link">Read Article <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        </div>"""


def pinned_html(post):
    img = post["featuredImage"]
    return f"""
        <a href="{esc(post['urlPath'])}" class="blog-pinned-card">
            <div class="blog-pinned-card-img">
                <img src="{esc(img['src'])}" alt="{esc(img['alt'])}"
                    width="{img['width']}" height="{img['height']}">
            </div>
            <div class="blog-pinned-card-body">
                <span class="blog-badge">Latest Article</span>
                <h2 class="h3">{esc(post['title'])}</h2>
                <p>{esc(post['excerpt'])}</p>
            </div>
        </a>"""


def replace_between(html_text, start_marker, end_marker, inner):
    pattern = re.compile(re.escape(start_marker) + r".*?" + re.escape(end_marker), re.S)
    if not pattern.search(html_text):
        raise SystemExit(f"{start_marker} / {end_marker} markers not found in blog.html")
    return pattern.sub(start_marker + inner + end_marker, html_text, count=1)


def main():
    posts = json.loads(BLOG_DATA.read_text(encoding="utf-8"))
    posts = [p for p in posts if p.get("slug") and p.get("title")]
    posts.sort(key=lambda p: p["publishDate"], reverse=True)

    pinned = posts[0]
    page_posts = posts[:PAGE_SIZE]

    html_text = BLOG_HTML.read_text(encoding="utf-8")
    html_text = replace_between(html_text, PINNED_START, PINNED_END, pinned_html(pinned))
    html_text = replace_between(html_text, GRID_START, GRID_END, "".join(card_html(p) for p in page_posts))
    BLOG_HTML.write_text(html_text, encoding="utf-8")

    print(f"blog.html updated: pinned post + {len(page_posts)} grid card(s) baked in from {len(posts)} total post(s)")


if __name__ == "__main__":
    main()
