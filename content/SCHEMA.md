# Content format (dynamic site)

Every page is one JSON file: `content/pages/<id>.json` locally, and the same file inside the content directory outside `public_html` in production. Site-wide settings live in `site.json`. This file documents both, for the Phase 3 migration and the Phase 4 dashboard.

**Escaping rule:** a field named `html` or ending in `Html` (`contentHtml`, `headingHtml`) holds HTML and is printed as-is, so the dashboard must sanitise it on save. Every other field is plain text and is escaped when printed. FAQ answers are plain text.

## Fields every page has

| Field | Meaning |
|---|---|
| `id` | lowercase-hyphen id; also the file name |
| `type` | `home`, `page`, `about`, `service`, `contact`, `faqs`, `blog-index`, `project-index`, `author`, `landing`, `not-found`, `blog-post`, `location` or `project` |
| `status` | `published` or `draft` (drafts never show publicly) |
| `urlPath` | the page's URL, e.g. `/kitchen-design/x/` or `/about.html` |
| `aliases` | optional extra URLs that show the same page (home: `["/"]`) |
| `seo` | `title` (required), `description`, `keywords`, `focusKeyword`, `ogImage`, `noindex`, `canonical`, `preloadImage` |
| `navActive` | optional nav item key to highlight (defaults by type) |
| `breadcrumb` | optional `[{name, path}]`; the last item has `path: null` |
| `cta` | `false` hides the consultation box; or an object overriding its `eyebrow`, `heading`, `text`, `buttonLabel`, `buttonHref`, `image`, `imageAlt` |
| `layout` | `default` or `bare` (no site nav, footer or CTA; for the ads landing pages) |
| `assets` | `css[]`, `js[]` (added to the defaults), `scripts[]` (replaces all scripts), `owlCarousel` (bool), `animateCss` (bool, default true) |
| `headHtml` | advanced: extra HTML for `<head>` |

## Blog post (`blog-post`)
`title, category, tags[], publishDate, modifiedDate, author, reviewedBy, readingTimeMinutes, featuredImage{src,alt,width,height}, excerpt, keyTakeaways[], contentHtml, faqs[{question,answer}], internalLinks[{text,href}]`

The table of contents, heading anchors, related posts (same category first), author box (from `site.json` `authors`) and share links are generated automatically.

## Location page (`location`)
`h1, heroImage, directAnswer, facts[{label,value}], keyTakeaways[], sections[{heading, html}], faqs[], internalLinks[], ctaContext` (fills `{ctaContext}` in the location CTA text).

## Project (`project`)
`name, projectType, configuration, location, sizeSqFt, budgetBand, timeline, image, imageAlt, gallery[], h1, leadAnswer, sections[{heading, html}], relatedBlogPosts[{text,href}], sortOrder, card{title, alt, image}`

- **Gallery-only project:** leave `leadAnswer` and `sections` empty.
- **Never invent facts:** leave size, budget and timeline empty unless the client supplied real figures.
- **Automatic parts:** "Explore More" links to 3 other projects, the related posts, "See all our projects" and "Start a project like this one".

## Block pages (every other type)
A page with `blocks: [...]` is rendered block by block, in order:

| Block `type` | Fields | Notes |
|---|---|---|
| `html` | `html` | exact HTML; keeps hand-designed sections pixel-identical |
| `hero` | `h1`, `variant` (`wide`) | title band with breadcrumb |
| `rich-text` | `heading`, `html` | formatted text section |
| `faq` | `heading` or `headingHtml`, `items[{question,answer}]`, `id` | added to FAQPage schema automatically |
| `latest-posts` | `headingHtml`, `intro`, `count`, `buttonLabel`, `buttonHref` | newest published posts |
| `project-cards` | `style` (`carousel` or `grid`), `headingHtml`, `intro`, `buttonLabel`, `buttonHref` | every published project, by `sortOrder` |
| `blog-listing` | none | pinned post, filter, search and the first 9 cards; `js/blog.js` reads `/api/posts.json` |

A page **without** blocks is a simple page: `h1`, `intro` (HTML), `sections[{heading, html}]`, `faqs[]`.

## Special URLs
- **`/api/posts.json`:** live feed of published posts for `js/blog.js`.
- **404 page:** the published page with id `not-found` is used for every 404. Without it, the static `404.html` is used.

## site.json
`business{name, alternateName, telephone, email, priceRange, foundingDate, address, geo, areaServed[], sameAs[], logo, image}`, `phoneDisplay`, `whatsappNumber`, `startingPrice`, `stats`, `mapEmbedUrl`, `defaultOgImage`, `tracking{ga4MeasurementId, metaPixelId, googleSiteVerification}`, `nav[{key,label,href}]`, `footer{about, quickLinks[], serviceAreas[], services[], addressDisplay, bottomMenu[]}`, `cta{...}`, `ctaTextByType{type: text}`, `authors{name: {url, sameAs[], bio, reviewedNote}}`.
