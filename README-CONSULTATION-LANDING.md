# Free Design Consultation Landing Page — Setup

`free-design-consultation/index.html` is a paid-ads landing page: no main
site navigation, `noindex, follow`, and its own 3-step lead form. It follows
the same plain PHP + PDO + MySQL pattern as the existing contact form
(`php/contact-submit.php`) — **this site has no WordPress, ACF, or CF7**, so
there was no existing "custom DB-backed form" pattern to extend beyond that
one. See `php/contact-submit.php` / `README-CONTACT-FORM.md` for the sibling
implementation this one mirrors.

## Files

- **`free-design-consultation/index.html`** — the page itself. Static HTML like every other page on this site (no templating layer, no build step needed for this page).
- **`css/consultation-landing.css`** — page-specific styles only (sticky minimal header, stat band, process timeline connector, 3-step form UI, mobile sticky CTA bar). Everything else (colors, fonts, buttons, icon-cards, accordion, testimonials) reuses `css/style.css`'s real design tokens and components — nothing was redefined.
- **`js/consultation-form.js`** — 3-step form logic + this page's tracking events (`cta_click`, `form_start`, `form_step_complete`, `lead_qualified`, `scroll_depth`). Reuses the exact fetch/JSON/validation pattern from `js/contact-form.js`.
- **`php/consultation-leads-submit.php`** — the endpoint. Same structure as `php/contact-submit.php`: PDO prepared statement, server-side validation (never trusts client input, including the `is_qualified` flag — that's always recomputed server-side), honeypot, generic error message to the client, real error logged server-side only.
- **`php/consultation_leads_table.sql`** — run once against production. See below.

## Table

```sql
-- contents of php/consultation_leads_table.sql
```

Run it in hPanel → phpMyAdmin (or your MySQL client) against the same
database `php/config.php` already points at — no new DB or credentials
needed, this reuses the existing connection the contact form uses.

## Qualification logic

A lead is marked `is_qualified = 1` if their stated budget is at or above
the site's real published starting price (`data/organization.json`
`startingPrice`: ₹15 Lakhs+ for a full home turnkey) **and** their timeline
isn't "Just Exploring". This threshold is grounded in a fact already
published elsewhere on the site, not an arbitrary number invented for this
form. Change both places if that number ever changes: `js/consultation-form.js`'s
`computeIsQualified()` (client-side, only used to decide whether to fire the
`lead_qualified` GA4 event immediately) and `php/consultation-leads-submit.php`'s
same check (server-side, authoritative — what's actually stored).

## Tracking — what's real vs. new

GA4 (`G-FSNHC0N7BD`) is the same live property as the rest of the site — no
second analytics setup. Event names:

| Event | Source | Notes |
|---|---|---|
| `page_view` | Automatic, from the standard `gtag('config', ...)` snippet in `<head>` | Not a custom event — already fires on load, same as every page |
| `call_click` | `js/analytics.js` (existing, unmodified) | Delegated `tel:` listener already covers this page's header/hero/sticky-bar call links |
| `whatsapp_click` | `js/analytics.js` (existing, unmodified) | Delegated `wa.me` listener already covers the sticky-bar WhatsApp button |
| `cta_click` | `js/consultation-form.js` (new) | Fires on any `data-cta` element click; page-specific, since the site's existing `js/analytics.js` only tracks 3 specific CTA shapes, not a generic one |
| `form_start` | `js/consultation-form.js` (new) | First interaction with any form field |
| `form_step_complete` | `js/consultation-form.js` (new) | Fires on each "Next" click, with the step number completed |
| `form_submit` | `js/consultation-form.js` (new), same event *name* as `js/contact-form.js` uses | Fires only after the backend confirms `{ success: true }`, matching the rest of the site's convention for this name |
| `lead_qualified` | `js/consultation-form.js` (new) | Fires alongside `form_submit`, only when the recomputed qualification is true |
| `scroll_depth` | `js/consultation-form.js` (new) | 25/50/75/100%, fired once each per page view |

**Deviation from the original brief:** the brief described a `phone_click`
event and a bespoke dataLayer schema as "already wired and tested end-to-end."
No such schema exists anywhere in this repo — `TRACKING.md` documents the
real, live event set, which uses `call_click` for tel: clicks. This page
uses that real name instead of introducing a duplicate `phone_click` event
for the same click, which would double-count in GA4 reporting.

## Why no "Google Reviews" section

`data/organization.json`'s `googleBusinessProfileUrl` is empty — there is no
connected GBP to pull real reviews from, and this project has a standing
instruction against fabricating off-site SEO content (reviews included; see
prior SEO work on this repo). The page uses a "Client Love" section instead,
reusing the three real, already-published testimonials from `index.html`
verbatim. If a GBP gets connected later, swap this section for the Google
Reviews widget/API of your choice.

## Testing without a live PHP host

Same caveat as `README-CONTACT-FORM.md`: this environment has no PHP
runtime and no network path to the production MySQL instance, so
`php/consultation-leads-submit.php` was written and reviewed carefully but
**could not be executed** here. Before relying on it:

1. Deploy `free-design-consultation/`, `css/consultation-landing.css`,
   `js/consultation-form.js`, and `php/consultation-leads-submit.php` to the
   host (same deploy as everything else in this repo).
2. Run `php/consultation_leads_table.sql` against production if
   `consultation_leads` doesn't already exist.
3. Submit the form on the live URL and confirm a row lands in
   `consultation_leads` with `is_qualified` set correctly for the budget/
   timeline combination you tested.
4. Check `error_log` (hPanel → Advanced → PHP error logs) if it doesn't.

## Not in the sitemap, but not blocked either

`free-design-consultation/` is intentionally **not** added to `sitemap.xml`
or `sitemap-blog.xml`, and `<meta name="robots" content="noindex, follow">`
is set in the page's `<head>` (see the comment above that tag in the HTML
for why). `robots.txt` is left untouched — a `Disallow` there would stop
Google from ever crawling the page to see the noindex tag in the first
place, which would defeat the purpose and also risk blocking ad-platform
crawlers that respect robots.txt.
