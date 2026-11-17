# Plan: Static Site → Dynamic Site with a Simple Dashboard

**Status:** Phases 1–5 of 6 done (17 Nov 2026) · **Written:** 17 Nov 2026

## Progress

| Phase | Status | Notes |
|---|---|---|
| 1. Foundations | ✅ Done | See "Phase 1 delivered" below |
| 2. Templates | ✅ Done | See "Phase 2 delivered" below |
| 3. Migration + comparison | ✅ Done | See "Phase 3 delivered" below |
| 4. Dashboard | ✅ Done | See "Phase 4 delivered" below |
| 5. SEO automation | ✅ Done | See "Phase 5 delivered" below |
| 6. Staging, go-live, monitoring | Not started | |

### Phase 1 delivered
- `app/`: config (`config.php`, optional gitignored `config.local.php`), `bootstrap.php`, `helpers.php` (escaping, atomic JSON writes), `content.php` (pages, URL index, validation, history, trash, dashboard redirects), `cache.php` (rendered-page cache, cleared on every change), `render.php` (templates, `<head>` and schema builders), `front.php` (front controller), and `cli.php` (`rebuild`, `clear-cache`, `check`, `render`).
- `templates/`: `layout.php` plus partials `head`, `nav`, `footer`, `cta`, `hero`, `faq`, `scripts`. The nav, footer and CTA now exist **once**, driven by `content/site.json`. There's also a generic `page.php` body template.
- `content/site.json`: business details, tracking IDs, nav, footer and CTA text. `content/pages/` is empty until Phase 3.
- `router.php`: local development server (`php -S localhost:8000 router.php`; add `OMEGA_MODE=dynamic` to prefer dynamic pages over the static files). It does nothing on the live server.
- `scripts/build-redirects.py --dynamic`: generates the go-live `.htaccess` (front-controller rules). The default output is unchanged.
- `.cpanel.yml` no longer deploys `tests/`, `content/`, `uploads/` or `app/config.local.php`. Internal folders each carry a deny-all `.htaccess`.
- `tests/run.php`: 59 automated checks (`php tests/run.php`).

**The live site is unaffected:** there is no root `index.php` and the `.htaccess` hasn't changed, so the static pages are still what visitors get. Phase 6 adds `index.php` (a single `require app/bootstrap.php; front_handle_request();`) and switches `.htaccess` to `--dynamic`.

**Deviation from §3:** the front-controller logic lives in `app/front.php`, not a root `index.php`, so it could be deployed safely before go-live.

### Phase 2 delivered
- **Templates:**
  - `templates/blog-post.php`: the full article is now rendered on the server, including the table of contents, takeaways, FAQs, author box, related posts and share links. `single-post.js` is no longer needed.
  - `templates/location.php` and `templates/project.php`.
  - `templates/page.php`: simple pages and the block renderer.
- **Blocks** (`templates/blocks/`): `html`, `hero`, `rich-text`, `faq`, `latest-posts` (homepage "From The Blog"), `project-cards` (homepage carousel and projects grid) and `blog-listing` (blog page).
  - **Lists update themselves:** the latest-posts, project-cards and blog-listing blocks always show the current posts and projects.
  - **Hand-designed sections stay exact:** the unique sections of the home, about, service, contact and landing pages become `html` blocks.
- **Extra partials:** `faq-accordion`, `post-card` and `project-card`. The CTA text now varies by page type (`site.json` → `ctaTextByType`).
- **Layouts:** a `bare` layout for the ads landing pages (no site nav, footer or CTA; own script list). The 404 page is content-managed (page id `not-found`).
- **`/api/posts.json`:** a live feed for `js/blog.js`, whose data URL is now configurable (the static site still uses `data/blog-data.json`).
- **Server-rendered schema per type:** BlogPosting, CreativeWork, CollectionPage/ItemList, plus Breadcrumb and FAQ from page data and FAQ blocks.
- **`content/SCHEMA.md`:** documents the content format of every page type and block.
- **`scripts/dynamic_mapping.py`:** converts blog-data.json, location content and projects.json into page JSON. Phase 3 reuses it.
- **Tests: 141 checks.** `tests/make-fixtures.py` builds fixtures from **real** site data. The tests confirm that the location and project pages show **identical visible text, links, titles, descriptions and canonicals** to the current static pages. Blog posts are checked against their data and their schema against the static pages.
- **Intended differences from the static site:**
  - Every page has the fuller LocalBusiness schema (static posts had a basic Organization).
  - Article text is in the HTML instead of being loaded by JavaScript.
  - Related posts are ordered newest-first.
  - Every page gets OG/Twitter tags.
**Goal:** let a non-technical person create, edit and delete website content from a password-protected dashboard, without losing any SEO the site has today.

**Constraints agreed:**
- No framework (no Laravel, React, etc.) and no WordPress.
- One shared dashboard password. No user accounts or roles.
- The dashboard must keep every page SEO-friendly: editable title, meta description, URL, FAQs and images, with schema and the sitemap handled automatically.
- Visitors notice no change. Every existing URL keeps working, with the same design.

---

### Phase 3 delivered
- **`scripts/migrate-to-dynamic.py`:** converts the whole site into `content/pages/`: **148 pages** (114 posts, 8 locations, 14 projects, 12 hand-written pages).
  - **Hand-written pages are split into blocks** (`scripts/html_sections.py`). The projects carousel and grid, "From The Blog", the blog listing and simple FAQ sections become structured blocks. Every other section stays exact HTML.
  - **Page-level details are captured:** SEO tags, breadcrumbs, nav highlight, consultation-box text, page-specific CSS/JS and page-specific structured data (`schemaExtra`).
  - **Landing pages** keep every element, using the bare layout.
- **`tests/compare-site.php`:** compares **every static page with its dynamic version**. It checks status, title, description, canonical, robots, h1 and structured data (nothing the static page had may be missing). For the page body above the footer, it checks visible text, links, images and structure (every element's tag and classes, in order). Blog posts are checked for their full article text.
  - **Result: 148 of 148 pages match.**
- **Additions needed for exactness:**
  - FAQ answers with links (`answerHtml`).
  - FAQ schema read from exact-HTML accordions.
  - Separate homepage-carousel card text (`carouselCard`) and an `exclude` list for the carousel.
  - A hidden `seq` tie-breaker so posts with the same publish date keep their order.
- **Intended differences:**
  - The hidden, empty "Latest From The Team" blog-CMS teaser on `blog.html` is gone.
  - The footer is now the same everywhere: root-absolute links and the map on every page.
- **⚠️ Re-run before go-live.** The static site is still where new posts are published until Phase 6. Run `python3 scripts/migrate-to-dynamic.py && php tests/compare-site.php` again right before the switch, so content added in the meantime is carried over. Anyone who hand-edits a generated page must also update its source (see the Phase 2 note on `location_pages_content.py`), or the migration won't see the edit.

### Phase 4 delivered
- **`/admin/` dashboard** in plain PHP, using the site's own Bootstrap CSS and one JS file:
  - **Login:** one shared password.
  - **Page list:** search and filters by type and status.
  - **Edit forms per page type**, driven by `app/admin_fields.php`: rich text (TinyMCE 6 from cdnjs), repeating rows (FAQs, links, sections, facts), an image picker with upload, and a page-sections editor (add, move, remove).
  - **Drafts, preview, trash and history:** drafts and preview of the saved version; trash with restore and "delete for good"; version history with restore.
  - **Images page and site settings:** a page for uploaded images; site settings (business details, figures, menu, footer, CTA wording, tracking IDs); password change.
- **"Edit text" for designed sections:** the hand-designed HTML sections can be reworded field by field (texts and image descriptions) without touching their markup (`app/sanitize.php` → `html_text_parts()` / `html_apply_text_edits()`). "Edit HTML (advanced)" is still available.
- **Security** (`app/security.php`):
  - the password is stored only as a hash in `<content>/_security/admin.json` (`php app/cli.php set-password`, or the one-time `/admin/setup.php` with a setup token)
  - session cookie limited to `/admin`, HttpOnly, SameSite=Strict, Secure on HTTPS; 2-hour idle logout
  - CSRF tokens on every form; 5 failed logins per IP lock that IP for 15 minutes
  - HTTPS forced; `noindex` and anti-framing headers; `/admin/` disallowed in `robots.txt`
  - helper files denied by `.htaccess`
- **Saved content is cleaned:**
  - Rich text goes through an allow-list. Styling classes are kept, pasted junk attributes and scripts are removed.
  - Designed sections only lose scripts, event handlers and `javascript:` links.
  - Both leave all 58 migrated designed sections byte-identical.
- **Uploads** (`app/media.php`): checked by real file type, max 5 MB, resized to 1600 px and saved as WebP. They go to `/uploads/`, which is excluded from deploys and protected by an auto-created `.htaccess` that blocks scripts.
- **Automatic values:** reading time, featured-image size, `modifiedDate` when the article changes, `seq` for new posts, and the URL from category + title.
- **The dashboard stays off until set up:** it shows "not set up" until the content folder exists, so deploying it before Phase 6 is safe.
- **`DASHBOARD-GUIDE.md`:** a plain-language guide for the person who will manage the site.
- **Tests:**
  - `tests/suite-dashboard.php`: open-and-save of **all 148 real pages renders identically**, plus editing, text-only edits, reordering, cleaning, password and lockout. 172 unit checks in total.
  - `tests/dashboard-http.php`: 38 end-to-end checks over real HTTP (login, CSRF, edit, text edit on the home page, upload, create/publish, duplicate URL, preview, delete/restore, settings, logout, lockout).
  - Browser walkthrough of the list, editor, image picker and section editor, including phone width.
- **Left for Phase 5, as planned:** automatic redirects when a URL changes or a page is deleted, and the SEO panel (Google preview, duplicate-title and keyword warnings).

### Phase 5 delivered
- **Live sitemaps and `llms.txt`** (`app/seo.php`), generated from the content on every change:
  - **`/sitemap.xml`:** identical to the static file (same URLs, order, lastmod, changefreq and priority), plus the author page that was missing.
  - **`/sitemap-blog.xml`:** byte-identical to the static file.
  - **`/llms.txt`:** byte-identical to the static file, now built from `site.json`.
  - **Sitemap settings:** the migration carries each page's sitemap settings over (`sitemap` field).
- **Automatic redirects:**
  - **URL change:** changing a published page's URL creates a 301 from the old address.
  - **Deleting:** you choose where visitors go (a suggested page, the home page, a custom address, or none).
  - **Saving or restoring:** a page saved or restored at an address removes any redirect from it.
  - **Redirects screen** in the dashboard: list, add (301 or 410) and remove. A redirect can never hide an existing page.
- **SEO panel in the editor:**
  - a live Google-style preview (title, URL, description)
  - title and description length, and duplicate titles or descriptions across the site
  - a competing focus keyword between posts, and the keyword missing from the title or first paragraph
  - missing image alt text, missing H1, and missing internal links or FAQs on posts
  - **broken internal links and images:** checked against pages, dashboard and historic redirects, and real files
- **`router.php` "live" mode** (`OMEGA_MODE=live`) behaves like the server after go-live (static HTML ignored). The end-to-end test now runs in it, which shows the site works with the static pages removed.
- **Tests:**
  - **`tests/suite-seo.php`:** live files identical to the static ones, sitemap updates on publish/unpublish, `llms.txt` follows settings, redirect/page consistency, link checker, SEO warnings.
  - **Totals:** 199 unit checks and 50 end-to-end checks; 148 of 148 pages still match.
- **Whole-site SEO audit** using the new checks. These are existing content issues on today's site, not fixed here:
  - 42 SEO titles over 60 characters and 40 meta descriptions over 160.
  - 3 posts share one SEO title, and the two Pareena Heights projects share "Pareena Heights".
  - One broken link: the DLF Phase 1–5 post links to `/home-residential-interiors/golf-course-road-interior-design-ultra-luxury-guide/`, which doesn't exist.

## 1. The approach in one paragraph

Replace the "edit JSON, run Python scripts, upload static HTML" workflow with **plain PHP that renders each page on the server when it's requested**, reading content from **JSON files stored on the server**. A small **`/admin` dashboard**, also plain PHP plus one JavaScript text editor, edits those JSON files. There is no database (the existing MySQL stays for the contact and consultation forms only). The cPanel host already runs PHP, so no new hosting is needed.

```
Visitor ──► /kitchen-design/modular-kitchen-cost-gurgaon-2026/
              │  .htaccess: not a real file → index.php
              ▼
           index.php (router) ──► content/pages/<id>.json ──► templates/blog-post.php ──► full HTML page
                                                                      ▲
Editor ──► /admin (password) ──► forms ──► save JSON (+ backup copy) ──┘   (live immediately)
```

## 2. Why this design

| Choice | Reason |
|---|---|
| **PHP, server-rendered** | Google gets complete HTML, including article text. That's better than today: post bodies are currently filled in by JavaScript, which some AI crawlers can't see. |
| **JSON files instead of MySQL** | The content is already JSON. About 150 pages is small. Files are easy to back up, copy and inspect, and avoid schema migrations. One editor means no write conflicts. |
| **One JSON file per page** | Today's `blog-data.json` is 1.5 MB. Rewriting it on every save risks corrupting all posts at once. Per-page files limit damage to one page and make backups simple. |
| **No framework** | It's a small amount of code (an estimated ~2,500 lines of PHP). Any PHP developer can maintain it, and there are no framework upgrades to keep up with. |
| **Reuse pieces of `blog-cms/`** | Its CSRF protection, session handling, HTML sanitiser, slug helper and safe image upload (`includes/csrf.php`, `auth.php`, `validation.php`, `media.php`) are already written and reviewed. Copy the functions; drop its users, roles and MySQL. |

## 3. Folder layout (target)

```
public_html/                    ← deployed from git (cPanel rsync)
  index.php                     router: every page request comes here
  .htaccess                     existing 301s + "if no real file → index.php"
  app/                          (blocked from web access)
    bootstrap.php               paths, config loading, helpers
    content.php                 load / save / list / delete pages (atomic writes + backups)
    render.php                  picks the template, builds <head>, schema, breadcrumbs
    seo.php                     JSON-LD builders, sitemap, canonical
    security.php                session, password check, CSRF, login rate limit
  templates/
    partials/head.php  nav.php  footer.php  cta.php  faq.php   ← ONE copy of each
    home.php  page.php  blog-post.php  location.php  project.php
    blog-index.php  project-index.php  author.php  landing.php
  admin/
    index.php                   dashboard list (search, filter by type)
    edit.php                    create / edit form (fields change per page type)
    delete.php  media.php  settings.php  login.php  logout.php  history.php
    assets/admin.css  admin.js
  css/ js/ img/ lib/ php/       unchanged (site assets + form endpoints)

/home/theomega/omega-content/   ← OUTSIDE public_html, never touched by deploys
  pages/<id>.json               one file per page
  site.json                     business info, nav menu, footer, social links
  redirects.json                old URL → new URL (auto-added when a URL changes)
  _history/<id>/<timestamp>.json   automatic backup of every previous version
  _trash/<id>.json              deleted pages (restorable)
  config.php                    password hash, settings

public_html/uploads/            ← images uploaded from the dashboard (excluded from deploy rsync)
```

**Important:** `.cpanel.yml` must exclude `uploads/`. Content lives outside `public_html`, so a git deploy can never overwrite edits made in the dashboard. This is the most important rule in the whole plan.

## 4. Content model (page types)

Every page file shares the same core fields:

```json
{
  "id": "modular-kitchen-cost-gurgaon-2026",
  "type": "blog-post",
  "status": "published",
  "urlPath": "/kitchen-design/modular-kitchen-cost-gurgaon-2026/",
  "seo": { "title": "...", "description": "...", "focusKeyword": "...", "ogImage": "...", "noindex": false },
  "createdAt": "...", "updatedAt": "..."
}
```

`status` is `published` or `draft`. A page type then adds its own fields:

| Type | Extra fields (what the editor sees as a form) | Used for |
|---|---|---|
| **blog-post** | title, category, tags, featured image + alt, excerpt, key takeaways, body (rich text), FAQs, internal links, author, publish date | the 114 posts |
| **location** | H1, hero image, direct-answer intro, sections (heading + rich text, repeatable), facts, FAQs, related links | the 8 `interior-designers-*` pages |
| **project** | name, type, location, banner, gallery (multi-upload, drag to reorder), optional size/budget/timeline, related posts | the 14 project pages |
| **page** | H1, hero image, sections (heading + rich text, repeatable), optional FAQs, optional CTA | about, faqs, privacy, and **any new page** |
| **home / service / contact / landing** | fixed layout; only the text, images, stats and FAQs inside each section are editable fields | pages with special designs |

"Create new page" offers **Blog post, Location page, Project, Simple page**. Special-layout pages (home, service, contact, the two ads landing pages) can be edited but not created from scratch, because that would require design work.

## 5. The dashboard (what the editor can do)

1. **Log in:** one password field. After 5 wrong tries in 15 minutes, that IP is locked out for 15 minutes.
2. **Content list:** all pages in a table (title, type, URL, status, last updated), with a search box, a type filter, and **Edit / View / Delete** on each row.
3. **Create / edit form:**
   - fields for the chosen type; a rich-text editor for body sections (headings, bold, lists, links, tables, images);
   - the URL fills in automatically from the title and can be edited;
   - an **SEO panel** showing title and description length counters, a Google-style preview snippet, and warnings (missing alt text, duplicate title, focus keyword already used by another page);
   - **Save draft**, **Publish** and **Preview** buttons.
4. **Delete:** the page moves to Trash (restorable). The editor chooses where the old URL should redirect (suggested default: its category or the blog page), so Google never sees a broken link.
5. **Change a URL:** a 301 redirect from the old URL is added automatically.
6. **History:** every save keeps the previous version, with one-click restore.
7. **Media:** upload JPG/PNG/WebP up to 5 MB. Images are resized to a maximum of 1600 px and converted to WebP where the server supports it. Alt text is required.
8. **Settings:** business details (phone, address, stats, starting price) edited in one place and used everywhere (schema, footer, `llms.txt`). Nav menu links. Change password (requires the current password).

The rich-text editor is **TinyMCE** (self-hosted copy, free edition) or **Quill**. That's a single JS file, not a framework. On save, the server cleans the HTML so only safe tags are allowed.

## 6. SEO handled automatically (no editor action needed)

| Item | How |
|---|---|
| `<title>`, meta description, canonical, OG/Twitter tags | from each page's `seo` fields, with sensible fallbacks |
| JSON-LD | Article + Breadcrumb + FAQPage (posts), LocalBusiness (all pages, from `site.json`), CreativeWork (projects), CollectionPage (listings): the same schema the Python scripts produce today |
| `/sitemap.xml` and `/sitemap-blog.xml` | generated on request from published pages, keeping both URLs already submitted to Google Search Console; drafts and noindex pages are left out |
| Redirects | existing ~90 rules stay in `.htaccess`; new ones from the dashboard are handled by the router |
| 404 | a real 404 status code with the branded page |
| Homepage "From the Blog" and blog listing | always current, newest posts first, rendered server-side (no more `build-blog-index.py`) |
| `llms.txt` | generated from `site.json` |
| Tracking | GA4, Meta Pixel and GSC tags live in one `head.php` partial, on every page |
| Speed | rendered pages cached as files, cache cleared on every save; images lazy-loaded with width/height set |

## 7. Migration (one-time, done by the developer)

A one-time script converts what exists today into the new content files:

1. `data/blog-data.json` → 114 `blog-post` files (straight field mapping).
2. `scripts/location_pages_content.py` → 8 `location` files.
3. `data/projects.json` + `project-galleries.json` → 14 `project` files.
4. `data/organization.json` → `site.json`.
5. Core HTML pages (index, about, service, contact, faqs, privacy, author, 404, two landing pages) → their templates, with the editable text pulled out into fields. This is the only manual part.
6. Ignore `blog-cms/` and its MySQL tables. If the live CMS has any published posts, import them too (check the live `/blog-cms/admin` first).

**Check before going live:** an automated comparison script requests every URL from the old static site and the new PHP site, and compares status code, `<title>`, meta description, canonical, H1, JSON-LD and internal links. The switch happens only when everything matches, apart from deliberate improvements such as the article body now being in the HTML.

## 8. Go-live (cutover)

1. Build and test locally with `php -S localhost:8000`.
2. Deploy to a **staging subdomain** (e.g. `staging.theomegagroup.in`, password-protected and noindex). The owner tries the dashboard there.
3. Back up `public_html` and the database in cPanel.
4. Deploy the PHP site. **Delete the old static post, location and project folders on the server.** Apache serves a real folder before the router, so leftover folders would hide the dynamic pages, and the cPanel rsync doesn't delete them by itself.
5. Run the comparison script against the live site. Submit both sitemaps in Search Console.
6. Watch GSC Coverage and GA4 for 2 weeks.

**Rollback:** restore the step-3 backup (the static site is unchanged in git history).

## 9. Security checklist

- Password stored only as a `password_hash()` value, in a config file outside `public_html`.
- Session cookie flags HttpOnly, Secure and SameSite=Strict. Session ID regenerated at login. Auto-logout after 2 hours idle.
- CSRF token on every dashboard form and delete action.
- Login rate limiting (section 5). HTTPS forced for `/admin`.
- `admin/` pages sent with `noindex` and disallowed in `robots.txt`.
- `app/` and `templates/` blocked from direct web access via `.htaccess`.
- Uploads checked by real file type (not extension). PHP execution disabled in `uploads/`.
- HTML from the editor sanitised to an allow-list. All other output escaped.
- Content files written atomically (write a temp file, then rename) so a crash can't leave half a file.

## 10. Backups

- Every save keeps the previous version in `_history/` (keep the last 30 per page).
- A daily cron job zips `omega-content/` and `uploads/`, keeps 14 days, and is downloadable from the dashboard.
- Optionally, a weekly copy to Google Drive or email.

## 11. Phases and effort (one developer)

| Phase | Work | Estimate |
|---|---|---|
| 1. Foundations | router, `.htaccess`, partials, content loader, `site.json`, caching | 2 days |
| 2. Templates | blog post, location, project, simple page, listings, home/service/contact/landing, 404 | 3 days |
| 3. Migration + comparison | conversion script, URL-by-URL comparison until identical | 2 days |
| 4. Dashboard | login, list, create/edit forms per type, editor, media, delete/trash, history, settings | 4 days |
| 5. SEO automation | schema, sitemaps, redirects on URL change, SEO panel warnings | 1 day |
| 6. Staging, owner walkthrough, go-live, monitoring | | 2 days |
| **Total** | | **≈ 2–2.5 weeks** |

## 12. What stays the same / what goes away

**Stays:**
- design, CSS and images
- every URL and redirect
- GA4 and Meta Pixel events
- the contact and consultation forms and their MySQL tables
- hosting

**Goes away after go-live:**
- the Python build scripts (`scripts/`) and the hand-editing of JSON
- the static post, location and project folders
- `vercel.json` and `_redirects`
- `blog-cms/` (after any live CMS posts are imported)
- `KNOWLEDGE-BASE.md` sections 3–5, which will be rewritten for the dashboard workflow

## 13. Open questions for the owner

1. Is any content currently live in `/blog-cms/`? (Check `https://theomegagroup.in/blog-cms/admin/login.php`.)
2. Which PHP version does the cPanel hosting account run? (Need 8.0+. Check cPanel → Software → Select PHP Version / MultiPHP Manager.)
3. Are staging subdomains allowed on the plan?
4. Should the dashboard send an email notification when content is published or deleted? (Optional.)
5. Who will keep the dashboard password, and where (a password manager is recommended)?
