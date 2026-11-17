# The Omega Group — Website Development

**Domain:** theomegagroup.in
**Also operates as:** Omega Interiors & Furniture (Google Business Profile listing name)
**Founded:** June 2022 (on record)

---

## 1. About The Omega Group

The Omega Group is a premium, end-to-end interior design and turnkey execution firm based in **Gurgaon (Gurugram), Haryana**, serving clients across Gurgaon and the broader **Delhi NCR** region.

### What we do
- **Residential** — apartments, villas, modular kitchens, custom furniture
- **Commercial** — offices, retail, showrooms
- **Hospitality** — hotels, restaurants
- **Turnkey execution model:** Concept → 3D Visualization → Material Selection → Site Supervision → Delivery

### Key differentiators
- 10+ years of experience
- 90+ completed projects
- 90% on-time delivery
- 70+ quality checks per project
- 290+ custom furniture designs
- Starting price for full home turnkey: **₹15 Lakhs+** (premium/luxury positioning, `priceRange: ₹₹₹₹`)

### Target clients
Luxury homeowners, property investors, corporate clients, and hospitality businesses across Gurgaon/NCR.

### Tracked competitors
MADS Creations, Essentia Environments, Morph Design Co.

---

## 2. Target Locations (Ranking Priority)

The website's SEO, GEO (Generative Engine Optimization), and AEO (Answer Engine Optimization) strategy is built around ranking for the following geographic areas, in priority order:

| Priority | Location | Notes |
|---|---|---|
| 1 | **Gurgaon / Gurugram** (core market) | Primary flagship pillar page |
| 1 | **Dwarka Expressway** | Primary flagship pillar page (high-growth residential corridor) |
| 2 | **DLF (DLF Phase 1–5 / Cyber City area)** | Established commercial + luxury residential zone |
| 2 | **Sushant Lok** | High-value residential zone |
| 2 | **Sohna Road** | Location landing page |
| 2 | **New Gurgaon** | Location landing page |
| 2 | **Manesar** | Location landing page |
| 3 | **Delhi NCR (broader region)** | Umbrella/regional targeting |

**Strategic note:** The two flagship pillar pages (Gurgaon + Dwarka Expressway) consolidate previously cannibalizing URLs (e.g., separate "interior designer in dwarka," "best interior designer in dwarka," "interior designers in dwarka" pages) into single authoritative pages, with the remaining location areas (DLF, Sushant Lok, Sohna Road, New Gurgaon, Manesar) built out as supporting local landing pages that link back into the pillars.

---

## 3. Digital Goals

- **SEO / GEO / AEO growth** for theomegagroup.in
- **Google Business Profile (GBP/GMB)** optimization — posts, service descriptions, Q&A, topic calendars
- **Content marketing** via blog and structured web assets, targeting local + commercial-intent keywords (e.g., "interior designer Dwarka Expressway," "modular kitchen cost Gurgaon")
- **Competitive positioning** against established Gurgaon design firms
- **AI-search visibility** — tracked via periodic checks in ChatGPT, Gemini, and Perplexity for core queries like "interior designer gurgaon" and "interior designer dwarka expressway"

---

## 4. Tech Stack

> The site was originally on WordPress. Its content was imported in July 2026 and **the live site is now static HTML**. WordPress, ACF and CF7 are no longer used. See `KNOWLEDGE-BASE.md` for the full technical reference.

| Layer | Details |
|---|---|
| Pages | Static HTML (Bootstrap 5 + jQuery). Clean URLs are directories with an `index.html`. |
| Content data | JSON in `data/`: `blog-data.json` (all blog posts), `projects.json`, `organization.json` (business name, address, phone, stats) |
| Page generation | Python 3 scripts in `scripts/` build the blog post, location and project pages, the schema blocks, sitemaps and redirects from that data |
| Forms | Plain PHP + PDO + MySQL (`php/contact-submit.php`, `php/consultation-leads-submit.php`) |
| Optional CMS | PHP/MySQL blog admin in `blog-cms/` (tables in `database/`) |
| SEO / Meta | Meta, OG and canonical tags plus JSON-LD baked into each page by the generators. `js/schema.js` adds site-wide LocalBusiness/Breadcrumb/FAQ schema. |
| Hosting / deploy | cPanel shared hosting (LiteSpeed, Apache-compatible `.htaccess`). cPanel Git deployment rsyncs the repo to `public_html` (`.cpanel.yml`). |
| Typography | Space Grotesk (headings) + Helvetica Neue stack (body) |
| Brand palette | Ivory, black/ink, antique gold (`css/style.css` `:root` tokens); navy + gold (internal documents) |

### Engineering standards
- **Accessibility:** `prefers-reduced-motion` support
- **Performance:** LCP optimization, lazy loading, CLS prevention
- **Security:** output escaping throughout

---

## 5. Content & SEO Workflow

1. **Audit before writing.** Check `keyword-map.md` and the `focusKeyword` / `slug` values in `data/blog-data.json`, which lists every published static post. Also search `site:theomegagroup.in [keyword]`. The blog has no `/blog/page/N/` archive URLs; pagination is client-side.
2. **Identify gaps** — avoid duplicating existing topics/keywords.
3. **Generate full post** — meta title, meta description, URL slug, AEO-style FAQs, internal linking recommendations, image alt text.
4. **Deliver as formatted `.docx`** — branded navy/gold formatting, cover page, colored tables. Filenames use underscores (e.g., `Omega_Blog_-_Topic_Name.docx`).

### Content principles
- Human-written tone, no keyword stuffing
- Structured for featured snippets and AI search engines (AEO/GEO), alongside traditional SEO
- GMB posts: 700–800 characters
- Service descriptions: ~30 words
- Default to concise; confirm length before producing longer pieces

---

## 6. Current State & Roadmap

- **SEO baseline (mid-2026):** early organic growth stage; 90-day GSC snapshot showed real commercial demand (impressions) but shallow rankings (avg. position ~28) and near-zero clicks — a consolidation/authority problem, not a content-volume problem.
- **Active 3-month plan (Aug–Oct 2026):**
  - **Month 1:** Fix GA4 conversion tracking, consolidate cannibalizing pages into flagship pillars (Gurgaon + Dwarka Expressway), ship schema, technical crawl audit, GBP audit kickoff.
  - **Month 2:** Publish real location landing pages (Sohna Road, Sushant Lok, New Gurgaon, DLF, Manesar), continue blog cadence, full GBP optimization, local citations, first backlink outreach.
  - **Month 3:** Scale content/link-building, re-optimize pages using live ranking data, run AI-visibility checks, expand reviews, scope Month 4–6 priorities.

---

## 7. On the Horizon

- Continued blog content production across the keyword/content funnel
- Expansion of GMB content (posts, Q&A, service descriptions)
- Further WordPress template/theme development
- Ongoing competitive monitoring and strategy refinement

---

*Internal project documentation — The Omega Group website development.*