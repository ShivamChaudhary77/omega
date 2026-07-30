# Analytics & Tracking Reference

## GA4 setup

`js/analytics.js` loads `gtag.js` using the Measurement ID stored in `data/organization.json` → `ga4MeasurementId`. It currently holds a placeholder (`G-XXXXXXXXXX`) — **gtag.js will not load, and no events will fire, until a real GA4 Measurement ID is put there.** This was a deliberate choice: better to visibly no-op than to silently wire tracking to a fake ID.

To go live: create/confirm the GA4 property for theomegagroup.in, copy its Measurement ID (`G-XXXXXXXXXX` format) from GA4 Admin → Data Streams, and paste it into `data/organization.json`. No other file changes needed — every page picks it up automatically since `js/analytics.js` is already included everywhere.

In GA4 Admin → Events, mark these four as **Key Events** (GA4's current name for what used to be "Conversions") so they show up in the reporting used throughout the SEO plan:

## Events

| Event name | Fires when | Params sent |
|---|---|---|
| `whatsapp_click` | Any click on a link whose `href` contains `wa.me` or `api.whatsapp.com` | `link_url`, `page_path` |
| `call_click` | Any click on a `tel:` link | `link_url`, `page_path` |
| `book_appointment` | Any click on an element with `data-cta="book-appointment"` | `link_url`, `page_path` |
| `form_submit` | Native `submit` event on any `<form data-track-submit>` | `form_id`, `page_path` |

All four are delegated listeners bound once in `js/analytics.js` (`bindKeyEvents`) — they work on elements added to the page later (e.g. dynamically rendered blog cards), no per-element wiring needed. To make a new WhatsApp/call/booking link trackable, just use a real `wa.me`/`tel:`/`data-cta="book-appointment"` link — nothing else to configure.

**Note on `form_submit`:** the contact form (`contact.html`) has no backend endpoint wired up yet — it's a static site with no server. `data-track-submit` is already on the form, so the event fires the moment a real form handler (Formspree, a serverless function, mailto fallback, etc.) is added — no code changes needed then either.

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
