/**
 * GA4 base install + key-event tracking. See TRACKING.md for the full
 * event/UTM reference. Include on every page, near the end of <body>.
 *
 * Swap data/organization.json's "ga4MeasurementId" (currently a placeholder
 * "G-XXXXXXXXXX") for the real GA4 Measurement ID before this fires any
 * real events — until then gtag() calls are queued harmlessly (no network
 * calls go out under the placeholder ID).
 */
(function () {
    "use strict";

    fetch("/data/organization.json")
        .then(function (r) { return r.json(); })
        .then(function (org) {
            const id = org && org.ga4MeasurementId;
            if (!id || id.indexOf("XXXX") !== -1) {
                console.info("[analytics] GA4 Measurement ID not configured yet — skipping gtag.js load. Set data/organization.json → ga4MeasurementId.");
                return;
            }
            loadGtag(id);
        })
        .catch(function () { /* org fetch failed — analytics simply won't load */ });

    function loadGtag(measurementId) {
        window.dataLayer = window.dataLayer || [];
        function gtag() { window.dataLayer.push(arguments); }
        window.gtag = gtag;
        gtag("js", new Date());
        gtag("config", measurementId);

        const script = document.createElement("script");
        script.async = true;
        script.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(measurementId);
        document.head.appendChild(script);

        bindKeyEvents(gtag);
    }

    function bindKeyEvents(gtag) {
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

        // form_submit — fires on successful submission, not just click.
        // contact.html's form has no backend wired up yet (see contact.html
        // comment); this listens on submit so it starts firing the moment a
        // real form handler / action endpoint is added, with no code changes.
        document.addEventListener("submit", function (e) {
            const form = e.target.closest("form[data-track-submit]");
            if (form) gtag("event", "form_submit", { form_id: form.id || "", page_path: location.pathname });
        });
    }
})();
