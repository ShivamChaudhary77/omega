# Blog System — The Omega Group

A static, JSON-driven blog built on top of the existing site template. No build step, no server-side code — one dynamic template renders every post, but each post also gets a real, crawlable, query-string-free URL.

## URL structure

Every post lives at **`https://theomegagroup.in/<category-slug>/<post-slug>/`** — e.g.
`/office-commercial-interiors/how-to-choose-the-best-office-interior-designer-in-gurgaon/`.

This is a real file on disk, not a client-side route: `scripts/build-post-pages.py` copies `single-post.html` (the template) to `<category-slug>/<post-slug>/index.html` for every post. The copy is byte-identical to the template — `js/single-post.js` reads the slug from `location.pathname` (the last path segment) at runtime and fetches `/data/blog-data.json` to find that post, so "generating a page" is really just placing the same shell at its URL. This works on **any** static host (Hostinger, Netlify, GitHub Pages, etc.) with zero server configuration — no `.htaccess` rewrite rules needed, because `/category/slug/` naturally resolves to `/category/slug/index.html` on every standard web server.

The root `single-post.html` still works directly with `?slug=your-slug` (query param takes priority over the path) — handy for local testing before a post's category/directory exists.

## How it works

- **`data/blog-data.json`** — one object per post (schema below). This is the single source of truth. Each post's `urlPath` (e.g. `/office-commercial-interiors/how-to-choose-.../`) and `canonicalUrl` (the same path, absolute) are computed from its `category` + `slug` at import time.
- **`blog.html`** + **`js/blog.js`** — listing page. Renders a pinned "latest article" card, a category filter bar, a client-side search box, and a paginated grid (9 posts/page). Card links point straight at each post's `urlPath`.
- **`single-post.html`** + **`js/single-post.js`** — the single-post template described above. Renders SEO tags, JSON-LD, breadcrumb, key takeaways, table of contents, body, FAQ accordion, internal links, author box, related posts — all keyed off whatever slug it resolves from the URL.
- **`scripts/build-post-pages.py`** — generates the 62 `<category>/<slug>/index.html` files from `single-post.html` + `blog-data.json`. **Re-run this any time you add/remove a post or change a post's `category`** (changing category changes its URL).
- **`css/blog.css`** — all blog-specific styling, layered on top of `css/style.css` and reusing the site's existing `--clr-*` variables and card/shadow language, so it looks native to the rest of the site rather than bolted on.
- **`sitemap-blog.xml`** — every published post's real URL + `blog.html`, for search engines (see "Pagination & crawlability" below).

## Local testing

Opening `blog.html` directly via `file://` will **not** work — `fetch()` of a local JSON file is blocked by the browser under the `file://` protocol. Serve the folder over HTTP, e.g.:

```
cd /Users/shivamkumar/Desktop/Omega
python3 -m http.server 8000
```

then visit `http://localhost:8000/blog.html`.

## Adding a new post

Append a new object to the array in `data/blog-data.json` with this shape:

```json
{
  "slug": "your-post-slug",
  "title": "Post Title",
  "metaTitle": "SEO title (~60 chars) | The Omega Group",
  "metaDescription": "SEO description, ~150-160 chars.",
  "focusKeyword": "primary keyword phrase",
  "category": "Home & Residential Interiors",
  "tags": ["Gurgaon", "Luxury Interiors"],
  "publishDate": "2026-07-30T12:00:00",
  "modifiedDate": "2026-07-30T12:00:00",
  "author": "The Omega Group Design Team",
  "readingTimeMinutes": 6,
  "featuredImage": { "src": "/img/blog/featured/your-image.jpg", "alt": "Descriptive alt text", "width": 1200, "height": 800 },
  "excerpt": "One or two sentences shown on cards and used as the meta description fallback.",
  "keyTakeaways": ["First key point", "Second key point", "Third key point"],
  "contentHtml": "<p>Full article body as clean HTML — use <h2>/<h3> for section headings, <p> for paragraphs.</p>",
  "faqs": [{ "question": "A question?", "answer": "Its answer." }],
  "internalLinks": [{ "text": "Explore Our Projects", "href": "/project.html" }],
  "urlPath": "/home-residential-interiors/your-post-slug/",
  "canonicalUrl": "https://theomegagroup.in/home-residential-interiors/your-post-slug/"
}
```

`urlPath` must be `/<slugified-category>/<slug>/` (lowercase, spaces/`&` → `-`) and `canonicalUrl` is just `https://theomegagroup.in` + `urlPath` — keep them in sync with `category`.

Then:
1. Drop the featured image into `img/blog/featured/` (and any inline content images into `img/blog/content/`) — reference them with a leading `/` (e.g. `/img/blog/featured/your-image.jpg`).
2. Run `python3 scripts/build-post-pages.py` to generate the new post's `<category>/<slug>/index.html`.
3. Regenerate `sitemap-blog.xml` (see below), or add a `<url>` block for the new post by hand following the existing pattern.
4. No other file needs to change. `blog.html` picks up new categories automatically for the filter bar.

### Regenerating the sitemap

```python
python3 - <<'EOF'
import json
from datetime import datetime, timezone
posts = json.load(open("data/blog-data.json"))
today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
lines = ['<?xml version="1.0" encoding="UTF-8"?>',
         '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
         '  <url><loc>https://theomegagroup.in/blog.html</loc>'
         f'<lastmod>{today}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>']
for p in sorted(posts, key=lambda x: x["publishDate"]):
    mod = (p.get("modifiedDate") or p["publishDate"])[:10]
    lines.append(f'  <url><loc>{p["canonicalUrl"]}</loc><lastmod>{mod}</lastmod>'
                 '<changefreq>monthly</changefreq><priority>0.6</priority></url>')
lines.append('</urlset>')
open("sitemap-blog.xml", "w").write("\n".join(lines) + "\n")
EOF
```

## Where the initial 62 posts came from

They were imported from a WordPress export (`theomega.WordPress.2026-07-29.xml`, 66 posts total). 4 were excluded automatically: 1 scheduled ("future") post and 3 drafts (2 of which had no body content). The import:

- Stripped WordPress's inline `<span style="font-weight:400">` styling artifacts.
- Re-wrapped blank-line-separated plain text into `<p>` tags (WordPress's classic editor stores raw text and lets `wpautop()` add `<p>` tags at render time — this import replicates that step).
- Promoted stand-alone bold lines (e.g. `**A Section Title**` used as a pseudo-heading) to real `<h2>` tags.
- Detected and extracted `<h2>FAQ</h2>` + numbered `<h3>` question blocks into the `faqs` array (30 of 62 posts had this pattern; the rest have `faqs: []` and simply don't render an FAQ section).
- Downloaded every referenced image from the WordPress staging host and re-hosted it locally under `img/blog/` — nothing in the live site depends on the old WordPress domain.
- Derived `category` and `tags` with a keyword heuristic (no real category/tag taxonomy existed in the source — everything was tagged generically "Blog"). Recategorize any post manually in `blog-data.json` if the heuristic missed the mark.
- Derived `keyTakeaways` from each post's first few `<h2>` sections where the source didn't already have a clear summary. **These are a reasonable first pass, not hand-edited editorial copy** — worth a human pass before or shortly after launch, especially on higher-traffic posts.

**`data/blog-import-report.json`** lists every post that was auto-flagged during import (no FAQ detected, meta description auto-generated instead of pulled from Yoast, thin content, etc.) — 34 of 62 posts have at least one flag, almost all just "no FAQs detected," which is expected and not an error.

## Pagination & crawlability

Pagination in `blog.html` is client-side (no `/blog/page/2/`-style URLs). To keep every article crawlable regardless, **every post URL is listed individually in `sitemap-blog.xml`**, not just the ones visible on page 1. Submit `sitemap-blog.xml` to Google Search Console alongside the main sitemap.

## Design decisions worth knowing about

- **Typography/color**: kept the site's existing Space Grotesk / Open Sans / gold-black-ivory system instead of introducing new fonts, to stay visually consistent with the rest of the site (home, about, service, project, contact) rather than making the blog look like a different product.
- **Nav/footer**: added a "Blog" link to the nav, footer Quick Links, and footer bottom menu on all five existing pages (including the home page) — without it the blog would be unreachable from the rest of the site.
- **Author field**: the WordPress export's author was the literal admin username `Omega@Admin`; every post now credits **"The Omega Group Design Team"** instead.
- **Root-absolute asset paths**: `single-post.html` references its CSS/JS/images as `/css/style.css`, `/js/main.js`, etc. (leading slash), not `css/style.css`. This is deliberate — the same file gets copied to `<category>/<slug>/index.html`, two directories deep, so a relative path would resolve incorrectly there. `blog.html` and the other top-level pages don't need this since they only ever live at the site root.
