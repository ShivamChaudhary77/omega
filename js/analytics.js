/**
 * GA4 key-event tracking. See TRACKING.md for the full event/UTM reference.
 *
 * GA4 itself (gtag.js + dataLayer + `gtag('config', ...)`) is loaded via the
 * standard Google snippet inline in every page's <head> — this file only
 * binds the four custom key events on top of that already-initialized
 * `window.gtag`. Include near the end of <body>, after the head snippet.
 */
(function () {
    "use strict";

    function gtag() {
        (window.gtag || function () { (window.dataLayer = window.dataLayer || []).push(arguments); }).apply(null, arguments);
    }

    // whatsapp_click — any link to wa.me / api.whatsapp.com
    document.addEventListener("click", function (e) {
        const a = e.target.closest('a[href*="wa.me"], a[href*="api.whatsapp.com"]');
        if (a) gtag("event", "whatsapp_click", { link_url: a.href, page_path: location.pathname });
    });

    // call_click — any tel: link
    document.addEventListener("click", function (e) {
        const a = e.target.closest('a[href^="tel:"]');
        if (a) gtag("event", "call_click", { link_url: a.href, page_path: location.pathname });
    });

    // book_appointment — any CTA explicitly marked data-cta="book-appointment"
    // (distinct from generic form_submit per Section 3 of the SEO plan)
    document.addEventListener("click", function (e) {
        const a = e.target.closest('[data-cta="book-appointment"]');
        if (a) gtag("event", "book_appointment", { link_url: a.href || "", page_path: location.pathname });
    });

    // form_submit is NOT bound here — it needs to fire only on a *confirmed*
    // successful save, not on every submit click (a failed/validation-error
    // submit shouldn't count as a key event). js/contact-form.js owns that:
    // it calls gtag('event', 'form_submit', ...) itself after the backend
    // returns { success: true }.
})();
