#!/usr/bin/env python3
"""Generate a static /<category>/<slug>/index.html for every post in
data/blog-data.json, so each article has a real, crawlable SEO-friendly URL
(no query strings, no server rewrite rules needed) on any static host.

Every generated file is byte-identical to single-post.html: the template is
fully dynamic (js/single-post.js reads the slug from the URL path and fetches
data/blog-data.json at runtime), so "generating a page" is just copying the
same shell to its target path. Re-run this after adding/removing posts or
after changing a post's category (which changes its urlPath).

Usage: python3 scripts/build-post-pages.py
"""
import json
import shutil
from pathlib import Path

SITE_ROOT = Path(__file__).resolve().parent.parent
TEMPLATE = SITE_ROOT / "single-post.html"
DATA = SITE_ROOT / "data" / "blog-data.json"

# Category directories that must never collide with existing top-level files.
RESERVED = {"css", "js", "img", "lib", "video", "data", "scripts",
            "index.html", "about.html", "service.html", "project.html",
            "contact.html", "blog.html", "single-post.html"}


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
        (target_dir / "index.html").write_text(template_html, encoding="utf-8")
        written.append(str(target_dir.relative_to(SITE_ROOT)))
        skipped_dirs.add(category_slug)

    print(f"Wrote {len(written)} post pages across {len(skipped_dirs)} category directories.")


if __name__ == "__main__":
    main()
