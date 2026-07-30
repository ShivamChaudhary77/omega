(function () {
    "use strict";

    const DATA_URL = "/data/blog-data.json";
    const PAGE_SIZE = 9;

    let allPosts = [];
    let state = { category: "all", query: "", page: 1 };

    function esc(str) {
        const div = document.createElement("div");
        div.textContent = str == null ? "" : String(str);
        return div.innerHTML;
    }

    function formatDate(iso) {
        if (!iso) return "";
        const d = new Date(iso);
        if (isNaN(d)) return "";
        return d.toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" });
    }

    function postUrl(post) {
        return post.urlPath;
    }

    function cardHtml(post) {
        return `
        <div class="col-md-6 col-lg-4">
            <a href="${postUrl(post)}" class="blog-card">
                <div class="blog-card-img">
                    <img src="${esc(post.featuredImage.src)}" alt="${esc(post.featuredImage.alt)}"
                        width="${post.featuredImage.width}" height="${post.featuredImage.height}" loading="lazy">
                    <span class="blog-badge">${esc(post.category)}</span>
                </div>
                <div class="blog-card-body">
                    <div class="blog-card-meta">
                        <span><i class="bi bi-calendar3"></i>${esc(formatDate(post.publishDate))}</span>
                        <span><i class="bi bi-clock"></i>${post.readingTimeMinutes} min read</span>
                    </div>
                    <h3>${esc(post.title)}</h3>
                    <p>${esc(post.excerpt)}</p>
                    <span class="blog-card-link">Read Article <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        </div>`;
    }

    function pinnedHtml(post) {
        return `
        <a href="${postUrl(post)}" class="blog-pinned-card">
            <div class="blog-pinned-card-img">
                <img src="${esc(post.featuredImage.src)}" alt="${esc(post.featuredImage.alt)}"
                    width="${post.featuredImage.width}" height="${post.featuredImage.height}">
            </div>
            <div class="blog-pinned-card-body">
                <span class="blog-badge">Latest Article</span>
                <h2 class="h3">${esc(post.title)}</h2>
                <p>${esc(post.excerpt)}</p>
            </div>
        </a>`;
    }

    function getFiltered() {
        const q = state.query.trim().toLowerCase();
        return allPosts.filter(function (p) {
            const catOk = state.category === "all" || p.category === state.category;
            if (!catOk) return false;
            if (!q) return true;
            return (p.title + " " + p.excerpt + " " + p.tags.join(" ")).toLowerCase().indexOf(q) !== -1;
        });
    }

    function renderGrid() {
        const grid = document.getElementById("blog-grid");
        const emptyState = document.getElementById("blog-empty-state");
        const filtered = getFiltered();
        const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        state.page = Math.min(state.page, totalPages);
        const start = (state.page - 1) * PAGE_SIZE;
        const pagePosts = filtered.slice(start, start + PAGE_SIZE);

        if (!pagePosts.length) {
            grid.innerHTML = "";
            emptyState.classList.remove("d-none");
        } else {
            emptyState.classList.add("d-none");
            grid.innerHTML = pagePosts.map(cardHtml).join("");
        }
        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        const nav = document.getElementById("blog-pagination");
        if (totalPages <= 1) {
            nav.innerHTML = "";
            return;
        }
        let html = `<button class="blog-page-btn" data-page="${state.page - 1}" ${state.page === 1 ? "disabled" : ""} aria-label="Previous page"><i class="bi bi-chevron-left"></i></button>`;
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="blog-page-btn ${i === state.page ? "active" : ""}" data-page="${i}" aria-current="${i === state.page ? "page" : "false"}">${i}</button>`;
        }
        html += `<button class="blog-page-btn" data-page="${state.page + 1}" ${state.page === totalPages ? "disabled" : ""} aria-label="Next page"><i class="bi bi-chevron-right"></i></button>`;
        nav.innerHTML = html;
        nav.querySelectorAll("[data-page]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                const p = parseInt(btn.getAttribute("data-page"), 10);
                if (p >= 1 && p <= totalPages) {
                    state.page = p;
                    renderGrid();
                    document.getElementById("blog-grid").scrollIntoView({ behavior: "smooth", block: "start" });
                }
            });
        });
    }

    function renderFilterPills() {
        const categories = Array.from(new Set(allPosts.map(function (p) { return p.category; }))).sort();
        const wrap = document.getElementById("blog-filter-pills");
        categories.forEach(function (cat) {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "blog-filter-pill";
            btn.dataset.category = cat;
            btn.textContent = cat;
            wrap.appendChild(btn);
        });
        wrap.addEventListener("click", function (e) {
            const btn = e.target.closest(".blog-filter-pill");
            if (!btn) return;
            wrap.querySelectorAll(".blog-filter-pill").forEach(function (b) { b.classList.remove("active"); });
            btn.classList.add("active");
            state.category = btn.dataset.category;
            state.page = 1;
            renderGrid();
        });
    }

    function renderPinned() {
        if (!allPosts.length) return;
        const sorted = allPosts.slice().sort(function (a, b) { return new Date(b.publishDate) - new Date(a.publishDate); });
        document.getElementById("blog-pinned").innerHTML = pinnedHtml(sorted[0]);
    }

    function injectCollectionSchema() {
        const el = document.getElementById("ld-collection");
        if (!el) return;
        const schema = {
            "@context": "https://schema.org",
            "@type": "CollectionPage",
            "name": "Interior Design Blog | The Omega Group",
            "url": "https://theomegagroup.in/blog.html",
            "mainEntity": {
                "@type": "ItemList",
                "itemListElement": allPosts.slice(0, 30).map(function (p, i) {
                    return {
                        "@type": "ListItem",
                        "position": i + 1,
                        "url": "https://theomegagroup.in" + p.urlPath,
                        "name": p.title
                    };
                })
            }
        };
        el.textContent = JSON.stringify(schema);
    }

    function init() {
        fetch(DATA_URL)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                allPosts = data.filter(function (p) { return p.slug && p.title; });
                renderPinned();
                renderFilterPills();
                renderGrid();
                injectCollectionSchema();
            })
            .catch(function (err) {
                console.error("Failed to load blog data:", err);
                document.getElementById("blog-grid").innerHTML =
                    '<div class="col-12 text-center py-5"><p>Unable to load articles right now. Please try again shortly.</p></div>';
            });

        let searchTimeout;
        document.getElementById("blog-search-input").addEventListener("input", function (e) {
            clearTimeout(searchTimeout);
            const val = e.target.value;
            searchTimeout = setTimeout(function () {
                state.query = val;
                state.page = 1;
                renderGrid();
            }, 200);
        });
    }

    document.addEventListener("DOMContentLoaded", init);
})();
