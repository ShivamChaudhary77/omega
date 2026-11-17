# CLAUDE.md

Static HTML site for The Omega Group (theomegagroup.in), hosted on cPanel shared hosting (LiteSpeed web server, Apache-compatible `.htaccess`; PHP/MySQL for forms). **Not WordPress.**

**Read `KNOWLEDGE-BASE.md` first.** It has the architecture, sources of truth, build scripts, update recipes and known issues.

Key rules:
- Edit the source (`data/*.json`, `scripts/location_pages_content.py`, `single-post.html`, `redirects.json`), then run the matching `scripts/build-*.py`. Never hand-edit generated pages or content between `<!-- …:START/END -->` markers.
- New blog post: add it to `data/blog-data.json`, then run `python3 scripts/build-post-pages.py && python3 scripts/build-blog-index.py && python3 scripts/build-sitemap.py`.
- Check `keyword-map.md` before targeting a keyword (one keyword maps to one URL).
- Never fabricate stats, reviews, testimonials or project facts.
- Never commit `php/config.php`.
- Preview with `python3 -m http.server 8000` (`file://` breaks the JSON fetches).
- A conversion to a dynamic PHP site with a dashboard is in progress (`DYNAMIC-CMS-PLAN.md`, `KNOWLEDGE-BASE.md` §12). Run `php tests/run.php` (plus `php tests/dashboard-http.php` for `admin/` changes, and `php tests/compare-site.php` for template/migration changes) after touching `app/`, `templates/` or `admin/`. Never add a root `index.php` before Phase 6 go-live: it would replace the live homepage.
