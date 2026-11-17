#!/usr/bin/env python3
"""Build tests/fixtures/pages/*.json from REAL site data, so the PHP tests
render genuine content and can be compared with the current static pages.

Run from the repo root (re-run if the mapping or source data changes):
    python3 tests/make-fixtures.py
"""
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "scripts"))
from dynamic_mapping import post_page, location_page, project_page  # noqa: E402
from location_pages_content import CONTENT  # noqa: E402

OUT = ROOT / "tests" / "fixtures" / "pages"


def project_cards_from_index_html():
    """{detail slug: {"title", "alt"}} as shown in index.html's carousel."""
    html = (ROOT / "index.html").read_text(encoding="utf-8")
    cards = {}
    for m in re.finditer(r'<a href="/project/([^/]+)/" class="project-card[^>]*>.*?alt="([^"]*)".*?<h4 class="mb-1">([^<]*)</h4>', html, re.S):
        cards[m.group(1)] = {"title": m.group(3), "alt": m.group(2).replace("&amp;", "&")}
    return cards


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    for old in OUT.glob("*.json"):
        old.unlink()

    def write(page):
        (OUT / f"{page['id']}.json").write_text(json.dumps(page, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    posts = json.loads((ROOT / "data" / "blog-data.json").read_text(encoding="utf-8"))
    posts.sort(key=lambda p: p["publishDate"], reverse=True)
    for post in posts[:5]:  # newest five: enough for related posts, home and blog listing
        write(post_page(post))

    for slug in ("interior-designers-manesar", "interior-designers-gurgaon"):
        write(location_page(slug, CONTENT[slug]))

    cards = project_cards_from_index_html()
    projects = [p for p in json.loads((ROOT / "data" / "projects.json").read_text(encoding="utf-8"))["projects"] if p.get("detailPageSlug")]
    for i, p in enumerate(projects):
        write(project_page(p, i + 1, cards.get(p["detailPageSlug"])))

    print(f"Wrote {len(list(OUT.glob('*.json')))} fixture page(s) to {OUT.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
