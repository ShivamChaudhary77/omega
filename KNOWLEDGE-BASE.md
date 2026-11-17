# The Omega Group Website: Knowledge Base

**Site:** https://theomegagroup.in · **Repo:** `/Users/shivamkumar/Desktop/Omega` (branch `main`)
**Last reviewed:** 17 Nov 2026 (state as of commit `6049cfd`: 114 blog posts, 149 HTML files)

Read this before changing the site. It covers how the site is built, which file is the source of truth for each thing, the exact steps for common updates, and the known gaps. Topic docs that go deeper are listed in [§12](#12-other-docs-in-the-repo).

---

## 1. What the site is

- **Business:** The Omega Group (GBP name: *Omega Interiors & Furniture*). A premium interior design and turnkey execution studio in Gurugram, founded June 2022. HQ: Plot S4 & S5, Main Dwarka Expressway, near Daulatabad Chowk, opp. Pillar 109, Gurugram 122006. Phone +91 981 1001 900, WhatsApp `919811001900`.
- **Positioning facts used across the site** (source: `data/organization.json`): 10+ yrs experience, 90+ projects, 90% on-time delivery, 70+ QC checks per project, 290+ custom furniture designs, full-home turnkey from **₹15 Lakhs+**, price range ₹₹₹₹.
- **Goal of the site:** local SEO, GEO and AEO for Gurgaon and Dwarka Expressway interior-design searches. Leads come in through the contact form, the paid-ads landing page, WhatsApp and phone.

## 2. Architecture at a glance

> ⚠️ `Readme.MD` says the site runs on WordPress with ACF and CF7. **That is out of date.** The live site is **static HTML** with a little PHP and MySQL. The WordPress content was imported once (July 2026) and WordPress is no longer used.

| Layer | What it is |
|---|---|
| Pages | Hand-written or script-generated static `.html` files. Clean URLs are directories that each hold an `index.html`. |
| Styling | Bootstrap 5 (`css/bootstrap.min.css`) plus `css/style.css` (brand tokens). Page-specific CSS: `css/blog.css`, `css/consultation-landing.css`, `css/lightbox.css`. SCSS sources in `scss/` are the template's originals and are not part of any build. |
| JS | jQuery with template libs in `lib/` (owl carousel, wow, waypoints, counterup, easing, animate). Custom code is in `js/` ([§6](#6-front-end-javascript)). |
| Data | JSON in `data/`. This is the source of truth for blog posts, projects and the business's name/address/phone details (NAP). |
| Generators | Python 3 scripts in `scripts/`. There is no npm build step. `package.json` only lists Playwright, a dev tool. |
| Backend | Plain PHP 8 with PDO and MySQL on the cPanel host: two lead-form endpoints in `php/`, plus an optional MySQL-backed **Blog CMS** in `blog-cms/`. |
| Hosting | **cPanel shared hosting** (LiteSpeed web server, which honours Apache `.htaccess` rules) is the live host. **It is not on Hostinger.** `.htaccess` handles redirects, the 404 page and CMS routing, and the PHP code needs it. `vercel.json` and `_redirects` (Netlify) are generated alternatives and are not in use. |
| Deploy | cPanel Git Version Control, triggered by hand (Update from Remote, then Deploy HEAD Commit; a GitHub push alone does not deploy). `.cpanel.yml` rsyncs the repo to `/home/theomega/public_html/`, excluding `.git`, `.claude`, `.DS_Store`, `php/config.php`, `blog-cms/uploads`, `node_modules`, `/tests`, `/content`, `/uploads` and `app/config.local.php`. |
| Tracking | GA4 `G-FSNHC0N7BD`, Meta Pixel `857790543995038`, GSC verification meta tag. Every page has all of them (checked across all HTML files). |

### Directory map

```
/                         core pages: index, about, service, project, contact, blog, faqs,
                          privacy-policy, 404, single-post (blog template)
/<category>/<slug>/       114 static blog posts (generated)
/interior-designers-*/    8 location pages: 2 pillars + 6 satellites (generated)
/project/<slug>/          14 project detail pages (generated)
/author/shivam-kumar/     author page (E-E-A-T)
/free-design-consultation/ paid-ads landing page (noindex)
/gurgaon-home-interiors/  Gurgaon home interiors page + ads landing page (indexed, in sitemap; same form/endpoint
                          as above; extra styles in css/gurgaon-landing.css)
/blog/<slug>/             1 legacy post URL kept to preserve rankings
/builder-floor-…-2026/, /dlf-the-arbour-…-2026/   2 one-off legacy post dirs (category = the dir itself)
data/        blog-data.json, projects.json, organization.json, project-galleries.json,
             reviews.json (empty on purpose), blog-import-report.json
scripts/     Python generators (see §4)
php/         contact + consultation form endpoints, config.php (gitignored)
blog-cms/    optional PHP admin CMS (admin/, includes/, public/, uploads/)
database/    SQL migrations for the CMS (001–006)
img/         site images; img/blog/{featured,content}/, img/projects/<slug>/
```

## 3. Sources of truth: edit these, never the generated output

| You want to change… | Edit this | Then run |
|---|---|---|
| A blog post (any field) | `data/blog-data.json` | the full blog pipeline ([§5.1](#51-publish-a-new-blog-post)) |
| Business NAP, stats, social links, starting price | `data/organization.json` | `build-homepage-schema.py`, `build-project-index-schema.py`, `build-location-pages.py`. Also update hard-coded copies (see [§10](#10-known-issues--tech-debt)). |
| Location page content | `scripts/location_pages_content.py` (`CONTENT` dict) | `build-location-pages.py` |
| Location page layout, nav, footer | `scripts/build-location-pages.py` (`PAGE_TEMPLATE`, `FOOTER_TEMPLATE`, `NAV_LINKS`, `FOOTER_SERVICE_AREAS`) | `build-location-pages.py` |
| Project detail pages | `data/projects.json` | `build-project-pages.py`, then `build-project-index-schema.py` |
| Project page layout | `scripts/build-project-pages.py` (`PAGE_TEMPLATE`, `NAV`, `FOOTER`) | `build-project-pages.py` |
| Blog post page shell (head, layout, scripts) | `single-post.html` | `build-post-pages.py` (rewrites all 106 posts) |
| Redirects | `redirects.json` | `build-redirects.py` (rewrites `.htaccess`, `_redirects`, `vercel.json`) |
| Core pages (index, about, service, project grid, contact, faqs, privacy, 404) | the HTML file directly | none. Do **not** edit between the `<!-- …:START/END -->` markers. |

**Generated regions inside hand-written files.** Do not hand-edit these; scripts overwrite them:
- `index.html`: `ORG-SCHEMA` (LocalBusiness JSON-LD) and `HOME-BLOG` (4 newest posts)
- `blog.html`: `BLOG-PINNED` and `BLOG-GRID` (first 9 posts, pre-rendered for crawlers)
- `project.html`: `PROJECT-SCHEMA`

## 4. Build scripts (`scripts/`)

All scripts run from the repo root with `python3 scripts/<name>.py`. They are idempotent: re-running on unchanged data produces identical files.

| Script | Reads | Writes |
|---|---|---|
| `build-post-pages.py` | `single-post.html`, `blog-data.json` | `/<category>/<slug>/index.html` for every post (114 as of this review), with title, meta, canonical, OG/Twitter tags and Article + Breadcrumb + FAQPage JSON-LD baked into the head. **It never deletes** old directories. `AUTHORS` dict = named-author Person schema. `RESERVED` = category slugs it refuses to use. |
| `build-blog-index.py` | `blog-data.json` | `blog.html` pinned post + page-1 grid, `index.html` "From The Blog" (newest 4). Mirrors `js/blog.js` sort order and page size (9). |
| `build-sitemap.py` | `blog-data.json` | `sitemap-blog.xml` (all posts). Does **not** modify `sitemap.xml`, but warns if `sitemap.xml` lists any blog post. |
| `build-location-pages.py` | `location_pages_content.py`, `organization.json` | 8 `interior-designers-*/index.html` |
| `build-project-pages.py` | `projects.json` | `/project/<detailPageSlug>/index.html` |
| `build-project-index-schema.py` | `organization.json`, `projects.json` | `project.html` schema block |
| `build-homepage-schema.py` | `organization.json` | `index.html` ORG-SCHEMA block |
| `build-redirects.py` | `redirects.json` | `.htaccess` (whole file, including the 404 and CMS rewrite blocks), `_redirects`, `vercel.json` |
| `reorganize-project-images.py` | raw photo folders | one-time WebP conversion (needs Pillow). Historical; don't re-run casually. |

## 5. Common update recipes

### 5.1 Publish a new blog post

1. Check `keyword-map.md` and the existing `focusKeyword` values in `blog-data.json` so you don't cannibalise a keyword another page already owns. Blog = **informational** intent. Pillar and location pages = **commercial "hire a designer"** intent.
2. Add the featured image to `img/blog/featured/` (≈1200×800 JPG/WebP, descriptive kebab-case filename). Put inline images in `img/blog/content/`. Reference both with a leading `/`.
3. Append a post object to `data/blog-data.json`. Fields: `slug, title, metaTitle (≤60), metaDescription (≈150–160), focusKeyword, category, tags[], publishDate, modifiedDate (ISO, e.g. 2026-10-22T12:00:00), author ("Shivam Kumar"), reviewedBy (optional, "The Omega Group design team"), readingTimeMinutes, featuredImage{src,alt,width,height}, excerpt, keyTakeaways[], contentHtml (h2/h3/p/ul/table, no h1), faqs[{question,answer}], internalLinks[{text,href}], urlPath, canonicalUrl`.
   - `urlPath` = `/<category-slug>/<slug>/`, with the category lower-cased and spaces/`&` turned into `-`. Existing category slugs: `home-residential-interiors`, `cost-planning-guides`, `office-commercial-interiors`, `kitchen-design`, `hospitality-retail-interiors`, `design-trends-inspiration`, `interior-design-insights`.
   - `canonicalUrl` = `https://theomegagroup.in` + `urlPath`.
   - Add 3–5 `internalLinks` to related posts and the relevant pillar or location page. Add a link back from 1–2 older related posts too, to build the cluster.
4. Run:
   ```bash
   python3 scripts/build-post-pages.py && python3 scripts/build-blog-index.py && python3 scripts/build-sitemap.py
   ```
5. Preview with `python3 -m http.server 8000` and open `http://localhost:8000/<urlPath>`. `file://` won't work because the pages fetch JSON.
6. Commit. Recent commits use the form `Add <topic> blog post`. A post commit normally touches `data/blog-data.json`, the image, the new `index.html`, `blog.html`, `index.html` and `sitemap-blog.xml`.
7. After deploying, request indexing in GSC.

Content rules (from the project's skill and docs): human tone, no keyword stuffing, a direct answer in the first paragraph, question-style H2s where natural, an FAQ block, and real figures only. **Never invent stats, reviews, project facts or URLs.** The repo-level skill `.claude/skills/blog-seo-optimizer` has the full SEO/AEO/GEO checklist.

### 5.2 Edit or move an existing post
Edit the post in `blog-data.json`, bump `modifiedDate`, then rerun the three scripts above. **If `urlPath` changes** (for example a category change): `rm -rf` the old directory yourself, add an old→new entry to `redirects.json`, run `build-redirects.py`, and update any `internalLinks` that point at the old URL.

### 5.3 Add or change a location page
Add an entry to `CONTENT` in `scripts/location_pages_content.py` (use `satellite_content(...)` for satellites). Add the page to `FOOTER_SERVICE_AREAS` and `SERVICE_AREAS_HTML` if it should be linked site-wide. Run `build-location-pages.py`, then add the URL to `sitemap.xml` and `keyword-map.md` by hand.

### 5.4 Add a project
1. Put WebP photos in `img/projects/<slug>/` (`<slug>-banner.webp`, `<slug>-01.webp` …, max 1200 px wide).
2. Add an entry to `data/projects.json`. Keep `sizeSqFt`, `budgetBand`, `timeline` and `materials` as null or empty unless the client gave real figures. Use `minimal: true` for a gallery-only page, and `relatedBlogPosts` for cross-links.
3. Run `build-project-pages.py` and `build-project-index-schema.py`.
4. Add a card to the grid in `project.html` by hand (copy an existing `data-project-slug` card), and add the URL to `sitemap.xml`.

### 5.5 Change business info (phone, address, stats, price)
Update `data/organization.json` and run the 3 schema/location scripts. Then grep for the old value, because NAP, stats and "₹15 Lakhs" also appear hard-coded in core pages, `scripts/location_pages_content.py`, footers in the generator templates, `llms.txt`, `js/main.js` (WhatsApp number) and `js/consultation-form.js` plus `php/consultation-leads-submit.php` (the qualification threshold):
```bash
grep -rn "1001 900\|1001900\|15 Lakh" --include=*.html --include=*.py --include=*.js --include=*.php --include=*.txt . | grep -v node_modules
```

### 5.6 Change nav or footer
There is no shared include. The nav and footer exist in: the 9 core HTML pages (by hand), `single-post.html` (then rebuild posts), `build-location-pages.py`, `build-project-pages.py`, `author/shivam-kumar/index.html` and `free-design-consultation/index.html` (which has its own minimal header). Edit every source, then rebuild.

### 5.7 Add a redirect
Add `{source, destination, permanent:true}` to `redirects.json`, then run `build-redirects.py`. That run rewrites `.htaccess` in full, so put any custom Apache rules in the script, not in `.htaccess`.

## 6. Front-end JavaScript

| File | Purpose |
|---|---|
| `main.js` | Spinner, WOW animations, sticky nav, back-to-top, Owl carousels (skipped if the library isn't loaded), and the **floating WhatsApp button**, injected on every page. |
| `analytics.js` | Delegated GA4 events: `whatsapp_click` (wa.me links), `call_click` (`tel:`), `book_appointment` (`[data-cta="book-appointment"]`). |
| `schema.js` | Client-side JSON-LD: LocalBusiness from `organization.json`, BreadcrumbList from the visible breadcrumb, FAQPage from `[data-faq-schema]` accordions, AggregateRating only if `reviews.json` has entries. |
| `blog.js` | Blog listing: category pills, search, 9-per-page client pagination. |
| `single-post.js` | Renders a post body, TOC, FAQs, related posts and share links from `blog-data.json`, using the slug from the URL path (or `?slug=`). |
| `blog-cms-grid.js` | Fills the "Latest From The Team" grid on `blog.html` from the CMS feed. Fails silently if the CMS isn't deployed. |
| `contact-form.js` | Validates the contact form and POSTs it to `php/contact-submit.php`. Fires `form_submit` only on a confirmed save. |
| `consultation-form.js` | 3-step landing-page form. Events: `cta_click`, `form_start`, `form_step_complete`, `form_submit`, `lead_qualified`, `scroll_depth`. |
| `projects.js` | Project fact pills and CreativeWork schema on `project.html`. |
| `toc.js` | Auto table of contents for `[data-toc-source]` pages. |
| `lightbox.js` | Accessible gallery lightbox for `[data-lightbox-gallery]`. |

Blog post **bodies are rendered client-side.** Only the `<head>` and JSON-LD are baked into the HTML, so crawlers that don't run JS see the metadata but not the article text. See [§10](#10-known-issues--tech-debt).

## 7. Backend (PHP and MySQL on the cPanel host)

- **DB config:** `php/config.php` (gitignored and not tracked; it exists only locally and on the server). `php/config.example.php` is the template. `php/.htaccess` blocks web access to both files.
- **Contact form:** `contact.html` → `php/contact-submit.php` → table `contact_form_submissions`. Fields: name, phone, city, project details, plus page URL, referrer, IP and user agent. Spam defence: a honeypot field named `website`.
- **Consultation landing page:** `free-design-consultation/` → `php/consultation-leads-submit.php` → table `consultation_leads` (schema in `php/consultation_leads_table.sql`). The server sets `is_qualified` when budget ≥ ₹15 L and the timeline isn't "Just Exploring".
- **Blog CMS** (`blog-cms/`): an optional admin for writing posts in a browser instead of editing JSON. Roles: author, editor, super_admin. It includes revisions, a media library, scheduled posts and an activity log. Its tables are `blog_*` (`database/001–006`). A published CMS post is served by `blog-cms/public/router.php` **only when no static file exists** at that URL (the `.htaccess` rewrite). CMS posts appear in `blog-cms/public/sitemap.php` and the `blog.html` "Latest From The Team" grid, **not** in `blog-data.json` or `sitemap-blog.xml`. Setup: `blog-cms/DEPLOYMENT.md`. The repo has no record of whether it was actually deployed; check `/blog-cms/admin/login.php` on the live site.
- **Never commit credentials.** If `config.php` is ever committed, rotate the DB password in cPanel.

## 8. Design system

- **Colours** (`css/style.css` `:root`): gold `--clr-gold #C9A84C` (light `#E2C97E`, dark `#9E7E30`), black `#0A0A0A`, ink `#1A1208` (footer), ivory background `--clr-dark #FAF7F2`, `--clr-dark-2 #F0EAE0`, `--clr-dark-3 #E8DFD0`, white surface. Gold is for accents only (text, borders, buttons, icons), never for large panels. Bootstrap `.text-primary`, `.bg-primary` and similar classes are remapped to these colours.
- **Type:** headings use *Space Grotesk* (Google Fonts). Body text uses the *Helvetica Neue* stack. Open Sans is also loaded but barely used. The Marcellus/Jost fonts named in `Readme.MD` belong to the old WordPress theme.
- **Components:** Bootstrap grid, cards and accordion (FAQ accordions carry `data-faq-schema`), `.fact-pill` / `.fact-strip`, `.blog-card`, `.whatsapp-float`, and the `brand-logo` image (`img/logo.png`, 300×42).
- **Images:** prefer WebP with explicit `width`/`height` (to avoid layout shift) and `loading="lazy"` below the fold. Paths that may be served from nested directories must start with `/`.

## 9. SEO system

- **Keyword ownership:** `keyword-map.md`. One keyword maps to one URL. Pillars: `/interior-designers-gurgaon/` and `/interior-designers-dwarka-expressway/`. Satellites: Sohna Road, Sushant Lok, New Gurgaon, DLF, Manesar and Golf Course Road. The homepage targets brand terms, not the bare "interior designer gurgaon" term.
- **Sitemaps** (all listed in `robots.txt`): `sitemap.xml` (core, location and project pages only, hand-maintained; **no blog posts**), `sitemap-blog.xml` (all static posts, generated) and `blog-cms/public/sitemap.php` (CMS posts).
- **Structured data:** LocalBusiness (all pages), Article + Breadcrumb + FAQPage (posts), FAQPage (location pages and faqs), CreativeWork (projects), CollectionPage/ItemList (`project.html`, `blog.html`). No AggregateRating until real reviews exist in `data/reviews.json`.
- **AI crawlers:** `llms.txt` holds a brand fact sheet and key URLs. Keep it in sync with `organization.json`.
- **Noindex:** only `404.html` and the ads landing page `free-design-consultation/` (deliberately not blocked in `robots.txt`, and kept out of the sitemaps). `gurgaon-home-interiors/` is indexed and targets "home interiors gurgaon" (see `keyword-map.md`). `robots.txt` disallows `/blog-cms/admin/`.
- **E-E-A-T:** every post's author is **Shivam Kumar**, linked to `/author/shivam-kumar/` and a LinkedIn `sameAs`. To add an author, extend `AUTHORS` in `build-post-pages.py`, the matching logic in `js/single-post.js`, and create an `/author/<slug>/` page.
- **Hard rule across all past work:** no fabricated reviews, stats, project sizes, budgets or testimonials.

## 10. Known issues / tech debt

Fixed on 17 Nov 2026: the 90 duplicate blog URLs removed from `sitemap.xml` (plus a warning in `build-sitemap.py`); the outdated WordPress description in `Readme.MD`; stale counts and steps in `BLOG-README.md` and the CMS docs; Golf Course Road added to `keyword-map.md` and `llms.txt`; the stale `_note` in `projects.json`.

| # | Issue | Suggested fix |
|---|---|---|
| 1 | `/author/shivam-kumar/` is in no sitemap. | Add it to `sitemap.xml`. |
| 2 | `organization.json` `areaServed` (and so the LocalBusiness schema) doesn't include Golf Course Road, though it has a location page. | Add it if the business confirms. |
| 3 | `.cpanel.yml` rsync has no `--delete`, so files removed or moved in git (e.g. an old post directory) **stay live on the server**. | Remove them in cPanel File Manager, or add `--delete` with care (it would also remove server-only files not in the exclude list). |
| 4 | Blog post body text is rendered only by JS. Non-JS crawlers (some AI/LLM bots) get the head metadata but no article. | Extend `build-post-pages.py` to bake `contentHtml`, FAQs and takeaways into the HTML, as `build-blog-index.py` already does for the listing. |
| 5 | Nav, footer and NAP are duplicated across about 6 sources (see [§5.6](#56-change-nav-or-footer)), so a change in one place doesn't reach the others. | Consider a shared partial step in the generators, or a single "rebuild all" script. |
| 6 | No single command rebuilds everything. | Add `scripts/build-all.sh` that runs every generator in order. |
| 7 | `RESERVED` in `build-post-pages.py` omits `project`, `php`, `blog-cms`, `database`, `free-design-consultation`, `interior-designers-*` and others. A badly chosen category slug could overwrite those directories. | Expand the set. |
| 8 | `data/reviews.json` is empty and `googleBusinessProfileUrl` is blank, so there is no review schema or GBP link. | Fill both once the GBP is verified, using real reviews only. |
| 9 | Unused files: `interior-design-website-template-free.jpg`, `LICENSE.txt` (template licence), `scss/`, the original `.jpg` files that sit alongside `.webp` versions, and committed `.DS_Store` files. | Clean up. `.DS_Store` is gitignored but still tracked: run `git rm --cached`. |
| 10 | Old legacy-import posts have a thin `faqs`/`keyTakeaways` from the auto-import (see `data/blog-import-report.json`). | Do editorial passes on high-impression posts. |

## 11. Deploy checklist

1. Run the relevant generators and preview locally (`python3 -m http.server 8000`; PHP endpoints only work on the host or with `php -S`).
2. Spot-check that `git diff` contains only the expected files. Generators are idempotent, so unexpected diffs mean something changed upstream.
3. Commit and push to GitHub `main`. **Pushing does not deploy by itself.** In cPanel open **Git Version Control → Manage → Pull or Deploy**, click **Update from Remote**, then **Deploy HEAD Commit** (`.cpanel.yml` then rsyncs the repo into `public_html`). The rsync doesn't delete anything, so if you removed or renamed a page, also delete the old copy on the server.
4. On the live site, check the new URL, view the source for title, canonical and JSON-LD, test any redirect, and submit the URL or sitemap in GSC.
5. For form or PHP changes, submit a test lead and confirm the row in phpMyAdmin.

## 12. Dynamic site (in progress)

The site is being converted to server-rendered PHP with a dashboard: see `DYNAMIC-CMS-PLAN.md` for the 6 phases and their status. **Until Phase 6 (go-live), everything above still describes the live site.** The new code is deployed but inactive.

- **Code:** `app/` (logic), `templates/` (layout, page types, `blocks/`, `partials/`), `content/site.json` (site-wide settings). Pages will live as `content/pages/<id>.json`. The content format is documented in `content/SCHEMA.md`.
- **Test data:** `python3 tests/make-fixtures.py` rebuilds the test fixtures from real site data. `scripts/dynamic_mapping.py` holds the conversion rules.
- **Local preview:**
  ```bash
  php -S localhost:8000 router.php
  ```
  Add `OMEGA_MODE=dynamic` to render dynamic pages even where a static file exists.
- **Tests:**
  ```bash
  php tests/run.php
  ```
- **Maintenance:** `php app/cli.php rebuild | clear-cache | check | render /path/`
- **Keep in sync until go-live:** business details are in both `data/organization.json` (static site) and `content/site.json` (dynamic site).
- **Production content directory:** this must sit outside `public_html` and is set in `app/config.local.php` (gitignored, not deployed). It gets created in Phase 6.

## 13. Other docs in the repo

| File | Covers |
|---|---|
| `BLOG-README.md` | Blog architecture and the history of the WordPress import (partly stale, see §10) |
| `TRACKING.md` | GA4 setup, event definitions, UTM conventions |
| `README-CONTACT-FORM.md` | Contact form, its table and security notes |
| `README-CONSULTATION-LANDING.md` | Paid-ads landing page, lead qualification, events |
| `blog-cms/DEPLOYMENT.md`, `database/README.md` | Blog CMS install and migrations |
| `keyword-map.md` | Keyword → URL ownership |
| `llms.txt` | AI-crawler brand summary |
| `.claude/skills/blog-seo-optimizer/SKILL.md` | Blog SEO/AEO/GEO checklist |
| `redirects.json` `_note` | Why each redirect exists |
| `data/projects.json` `_note` | Rules for project data (no invented facts, slug typos kept on purpose) |
