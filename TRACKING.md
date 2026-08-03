# Analytics & Tracking Reference

## GA4 + Search Console setup

**Live**, using property `G-FSNHC0N7BD`. Every page's `<head>` carries the standard Google gtag.js snippet directly (inline, synchronous — the way Google's own setup instructions specify), immediately after `<meta charset>`:

```html
<meta name="google-site-verification" content="WfK6bn2Y0riMl83guJlVGhq8GrnN6rbKjyn5Nrk43Ls" />
<script async src="https://www.googletagmanager.com/gtag/js?id=G-FSNHC0N7BD"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-FSNHC0N7BD');
</script>
```

`js/analytics.js` no longer loads gtag.js itself (that would double-initialize it) — it just binds the four key events below on top of the `gtag` the head snippet already defines. It's included near the end of `<body>` on every page.

Both the GSC verification meta tag and the GA4 ID are also recorded in `data/organization.json` (`googleSiteVerification`, `ga4MeasurementId`) for reference/documentation, but the live tag lives in each page's `<head>` — that's what Google actually reads.

**To change the GA4 property or add a new page:** edit the snippet in `scripts/build-location-pages.py` (for pillar/satellite pages) and `single-post.html` (for the blog template), then re-run `scripts/build-post-pages.py` / `scripts/build-location-pages.py` to propagate; hand-edit the 7 static core pages (index/about/service/project/contact/blog/404) directly.

In GA4 Admin → Events, mark these four as **Key Events** (GA4's current name for what used to be "Conversions") so they show up in the reporting used throughout the SEO plan:

## Events

| Event name | Fires when | Params sent |
|---|---|---|
| `whatsapp_click` | Any click on a link whose `href` contains `wa.me` or `api.whatsapp.com` | `link_url`, `page_path` |
| `call_click` | Any click on a `tel:` link | `link_url`, `page_path` |
| `book_appointment` | Any click on an element with `data-cta="book-appointment"` | `link_url`, `page_path` |
| `form_submit` | Contact form save confirmed successful by the backend (not the click, not a validation failure) | `form_id`, `page_path` |

The first three are delegated listeners bound once in `js/analytics.js` — they work on elements added to the page later (e.g. dynamically rendered blog cards), no per-element wiring needed. To make a new WhatsApp/call/booking link trackable, just use a real `wa.me`/`tel:`/`data-cta="book-appointment"` link — nothing else to configure.

**`form_submit` is different on purpose:** it's fired directly by `js/contact-form.js`, only after `php/contact-submit.php` responds `{ success: true }`. A blocked-by-validation or failed-network submit does not fire it — see `README-CONTACT-FORM.md` for how the form/backend pair works.

## UTM convention

Use this pattern for every outbound link that isn't a normal internal nav link, so GA4 stops folding that traffic into the (currently oversized, 75% of sessions) Direct channel:

```
?utm_source=<channel>&utm_medium=<type>&utm_campaign=site_cta
```

| Channel | `utm_source` | `utm_medium` |
|---|---|---|
| WhatsApp business link | `whatsapp` | `social` |
| Instagram bio link | `instagram` | `social` |
| Facebook bio link | `facebook` | `social` |
| Offline QR code (signage, business cards, print) | `qr` | `offline` — add a specific `utm_content` per QR placement, e.g. `utm_content=business_card` |

Already applied in this pass: the footer/CTA WhatsApp and social links across the site (see `js/analytics.js`'s click delegation — it works regardless of query string, so UTM params don't need to match any code, just stay consistent for reporting). When Instagram/Facebook bio links or QR codes are set up outside this codebase, tag them the same way so they show up correctly segmented in GA4 Acquisition reports.

## Why this matters (from the SEO review)

GA4 currently shows a 0% key-event rate — no enquiry action is measured at all, so there has never been a real conversion rate to plan against. Every traffic/lead projection in the SEO plan depends on this being live first.
