/**
 * Site-wide JSON-LD injector. Include on every page (after the DOM, before
 * </body> is fine — it only writes <script type="application/ld+json"> tags,
 * nothing render-blocking).
 *
 * - Organization/LocalBusiness: built from data/organization.json — the
 *   single NAP/brand source of truth, so every page emits byte-identical
 *   name/address/phone.
 * - BreadcrumbList: read directly from the page's own visible
 *   <nav aria-label="breadcrumb"> markup, so schema can never drift from
 *   what a visitor actually sees.
 * - FAQPage: read directly from any [data-faq-schema] accordion's visible
 *   .accordion-button / .accordion-body question/answer pairs, same
 *   guarantee as breadcrumbs.
 * - AggregateRating: only emitted if data/reviews.json actually contains
 *   reviews — never populated with placeholder ratings.
 */
(function () {
    "use strict";

    function injectLd(id, data) {
        if (!data) return;
        const el = document.createElement("script");
        el.type = "application/ld+json";
        el.id = id;
        el.textContent = JSON.stringify(data);
        document.head.appendChild(el);
    }

    function absoluteUrl(href) {
        try {
            return new URL(href, window.location.href).href;
        } catch (e) {
            return href;
        }
    }

    function buildOrganization(org) {
        const ld = {
            "@context": "https://schema.org",
            "@type": "LocalBusiness",
            "@id": org.url + "#organization",
            "name": org.name,
            "alternateName": org.alternateName || undefined,
            "url": org.url,
            "logo": org.logo,
            "image": org.image,
            "telephone": org.telephone,
            "email": org.email || undefined,
            "priceRange": org.priceRange,
            "foundingDate": org.foundingDate,
            "sameAs": org.sameAs && org.sameAs.length ? org.sameAs : undefined,
            "address": org.address ? {
                "@type": "PostalAddress",
                "streetAddress": org.address.streetAddress,
                "addressLocality": org.address.addressLocality,
                "addressRegion": org.address.addressRegion,
                "postalCode": org.address.postalCode,
                "addressCountry": org.address.addressCountry
            } : undefined,
            "geo": org.geo ? {
                "@type": "GeoCoordinates",
                "latitude": org.geo.latitude,
                "longitude": org.geo.longitude
            } : undefined,
            "areaServed": org.areaServed && org.areaServed.length
                ? org.areaServed.map(function (a) { return { "@type": "AdministrativeArea", "name": a }; })
                : undefined
        };
        return stripEmpty(ld);
    }

    function stripEmpty(obj) {
        Object.keys(obj).forEach(function (k) {
            if (obj[k] === undefined || obj[k] === null || obj[k] === "") delete obj[k];
        });
        return obj;
    }

    function buildBreadcrumb() {
        const nav = document.querySelector('nav[aria-label="breadcrumb"] ol.breadcrumb');
        if (!nav) return null;
        const items = Array.from(nav.querySelectorAll(".breadcrumb-item")).map(function (li, i) {
            const a = li.querySelector("a");
            const name = (a || li).textContent.trim();
            const url = a ? absoluteUrl(a.getAttribute("href")) : window.location.href;
            return { "@type": "ListItem", "position": i + 1, "name": name, "item": url };
        });
        if (items.length < 2) return null;
        return {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "itemListElement": items
        };
    }

    function buildFaqSchema() {
        const containers = document.querySelectorAll("[data-faq-schema]");
        if (!containers.length) return null;
        const mainEntity = [];
        containers.forEach(function (container) {
            container.querySelectorAll(".accordion-item").forEach(function (item) {
                const q = item.querySelector(".accordion-button");
                const a = item.querySelector(".accordion-body");
                if (!q || !a) return;
                const question = q.textContent.trim();
                const answer = a.textContent.trim();
                if (!question || !answer) return;
                mainEntity.push({
                    "@type": "Question",
                    "name": question,
                    "acceptedAnswer": { "@type": "Answer", "text": answer }
                });
            });
        });
        if (!mainEntity.length) return null;
        return { "@context": "https://schema.org", "@type": "FAQPage", "mainEntity": mainEntity };
    }

    function buildAggregateRating(org, reviews) {
        const marker = document.querySelector("[data-review-schema]");
        if (!marker || !reviews || !reviews.length) return null;
        const sum = reviews.reduce(function (s, r) { return s + Number(r.rating || 0); }, 0);
        const avg = sum / reviews.length;
        return {
            "@context": "https://schema.org",
            "@type": "LocalBusiness",
            "@id": org.url + "#organization",
            "name": org.name,
            "aggregateRating": {
                "@type": "AggregateRating",
                "ratingValue": Math.round(avg * 10) / 10,
                "reviewCount": reviews.length
            }
        };
    }

    Promise.all([
        fetch("/data/organization.json").then(function (r) { return r.json(); }).catch(function () { return null; }),
        fetch("/data/reviews.json").then(function (r) { return r.json(); }).catch(function () { return []; })
    ]).then(function (results) {
        const org = results[0];
        const reviews = results[1] || [];
        if (org) {
            injectLd("ld-organization", buildOrganization(org));
            window.__omegaOrg = org; // exposed for other schema builders (e.g. project fact-block) on the page
        }
        injectLd("ld-breadcrumb-auto", buildBreadcrumb());
        injectLd("ld-faq-auto", buildFaqSchema());
        if (org) injectLd("ld-rating-auto", buildAggregateRating(org, reviews));
        document.dispatchEvent(new CustomEvent("omega:schema-ready", { detail: { org: org, reviews: reviews } }));
    });
})();
