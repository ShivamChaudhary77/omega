/**
 * Generic auto-TOC for static long-form pages (pillar/satellite/service
 * pages). Finds every <h2> inside [data-toc-source], assigns it an id if it
 * doesn't have one, and lists them inside [data-toc-target]. Mirrors the
 * same auto-TOC logic js/single-post.js uses for blog posts, generalized
 * for hand-authored static content instead of JSON-rendered content.
 */
(function () {
    "use strict";

    function slugify(text) {
        return String(text).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
    }

    document.addEventListener("DOMContentLoaded", function () {
        const source = document.querySelector("[data-toc-source]");
        const target = document.querySelector("[data-toc-target]");
        const wrap = document.querySelector("[data-toc-wrap]");
        if (!source || !target) return;

        const headings = source.querySelectorAll("h2");
        if (headings.length < 2) return;

        const used = new Set();
        const items = [];
        headings.forEach(function (h) {
            if (!h.id) {
                let id = slugify(h.textContent), uniq = id, n = 1;
                while (used.has(uniq) || !uniq) { uniq = id + "-" + (n++); }
                h.id = uniq;
            }
            used.add(h.id);
            items.push({ id: h.id, text: h.textContent });
        });

        target.innerHTML = items.map(function (item) {
            const div = document.createElement("div");
            div.textContent = item.text;
            return `<li><a href="#${item.id}">${div.innerHTML}</a></li>`;
        }).join("");

        if (wrap) wrap.style.display = "";
    });
})();
