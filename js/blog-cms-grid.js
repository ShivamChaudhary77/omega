/**
 * Populates the "Latest From The Team" grid on blog.html from the Blog
 * CMS's published posts (blog-cms/public/posts-feed.php). Completely
 * independent of js/blog.js — different data source, different DOM nodes,
 * no shared state — so a failure here (e.g. the CMS isn't deployed yet, or
 * has zero posts) can never break the existing static-post grid below it.
 */
(function () {
    "use strict";

    const FEED_URL = "/blog-cms/public/posts-feed.php";

    function esc(str) {
        const div = document.createElement("div");
        div.textContent = str == null ? "" : String(str);
        return div.innerHTML;
    }

    function formatDate(iso) {
        if (!iso) return "";
        const d = new Date(iso.replace(" ", "T"));
        if (isNaN(d)) return "";
        return d.toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });
    }

    function cardHtml(post) {
        const img = post.featuredImage
            ? `<img src="${esc(post.featuredImage)}" alt="${esc(post.title)}" loading="lazy">`
            : "";
        return `
        <div class="col-md-6 col-lg-4">
            <a href="${esc(post.url)}" class="blog-card">
                <div class="blog-card-img">
                    ${img}
                    <span class="blog-badge">${esc(post.category)}</span>
                </div>
                <div class="blog-card-body">
                    <div class="blog-card-meta">
                        <span><i class="bi bi-calendar3"></i>${esc(formatDate(post.publishDate))}</span>
                        ${post.readingTimeMinutes ? `<span><i class="bi bi-clock"></i>${post.readingTimeMinutes} min read</span>` : ""}
                    </div>
                    <h3>${esc(post.title)}</h3>
                    <p>${esc(post.excerpt)}</p>
                    <span class="blog-card-link">Read Article <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        </div>`;
    }

    document.addEventListener("DOMContentLoaded", function () {
        const section = document.getElementById("blog-cms-section");
        const grid = document.getElementById("blog-cms-grid");
        if (!section || !grid) return;

        fetch(FEED_URL)
            .then(function (r) {
                if (!r.ok) throw new Error("feed unavailable");
                return r.json();
            })
            .then(function (data) {
                const posts = Array.isArray(data.posts) ? data.posts : [];
                if (posts.length === 0) return; // keep section hidden
                grid.innerHTML = posts.map(cardHtml).join("");
                section.style.display = "";
            })
            .catch(function () {
                // CMS not deployed yet, or genuinely no posts — section
                // simply stays hidden, exactly as if it were never added.
            });
    });
})();
