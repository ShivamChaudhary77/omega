(function () {
    "use strict";

    const DATA_URL = "/data/blog-data.json";
    const SITE_BASE = "https://theomegagroup.in";

    function esc(str) {
        const div = document.createElement("div");
        div.textContent = str == null ? "" : String(str);
        return div.innerHTML;
    }

    function formatDate(iso) {
        if (!iso) return "";
        const d = new Date(iso);
        if (isNaN(d)) return "";
        return d.toLocaleDateString("en-IN", { day: "numeric", month: "long", year: "numeric" });
    }

    function slugify(text) {
        return String(text).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
    }

    function getSlugFromUrl() {
        // Query param wins (used by the root single-post.html template when
        // opened directly / for local testing). Otherwise this file is being
        // served from its generated /<category>/<slug>/index.html location,
        // so the slug is just the last real path segment.
        const params = new URLSearchParams(window.location.search);
        const qSlug = params.get("slug");
        if (qSlug) return qSlug;

        const segments = window.location.pathname.split("/").filter(Boolean);
        if (!segments.length) return "";
        const last = segments[segments.length - 1];
        return last.endsWith(".html") ? "" : decodeURIComponent(last);
    }

    function setMeta(id, attr, value) {
        const el = document.getElementById(id);
        if (!el) return;
        if (attr === "text") el.textContent = value;
        else el.setAttribute(attr, value);
    }

    function renderMetaRow(post) {
        const wrap = document.getElementById("post-meta-row");
        wrap.innerHTML = `
            <span><i class="bi bi-calendar3"></i>${esc(formatDate(post.publishDate))}</span>
            <span><i class="bi bi-clock"></i>${post.readingTimeMinutes} min read</span>
            <span><i class="bi bi-folder2"></i>${esc(post.category)}</span>
            <span><i class="bi bi-person"></i>${esc(post.author)}</span>
        `;
    }

    function renderTakeaways(post) {
        if (!post.keyTakeaways || !post.keyTakeaways.length) return;
        const box = document.getElementById("post-takeaways");
        const list = document.getElementById("post-takeaways-list");
        list.innerHTML = post.keyTakeaways.map(function (t) { return `<li>${esc(t)}</li>`; }).join("");
        box.style.display = "";
    }

    function renderBodyAndToc(post) {
        const bodyEl = document.getElementById("post-body");
        // contentHtml is generated server-side (by our own import script) from
        // our own WordPress export, not raw third-party user input.
        bodyEl.innerHTML = post.contentHtml;

        const headings = bodyEl.querySelectorAll("h2, h3");
        const tocItems = [];
        const usedIds = new Set();
        headings.forEach(function (h) {
            let id = slugify(h.textContent);
            let uniq = id, n = 1;
            while (usedIds.has(uniq) || !uniq) { uniq = id + "-" + (n++); }
            usedIds.add(uniq);
            h.id = uniq;
            if (h.tagName === "H2") {
                tocItems.push({ id: uniq, text: h.textContent });
            }
        });

        if (tocItems.length >= 2) {
            const toc = document.getElementById("post-toc");
            const list = document.getElementById("post-toc-list");
            list.innerHTML = tocItems.map(function (item) {
                return `<li><a href="#${item.id}">${esc(item.text)}</a></li>`;
            }).join("");
            toc.style.display = "";
        }
    }

    function renderFaqs(post) {
        if (!post.faqs || !post.faqs.length) return;
        const wrap = document.getElementById("post-faq-wrap");
        const accordion = document.getElementById("post-faq-accordion");
        accordion.innerHTML = post.faqs.map(function (faq, i) {
            const qId = "faq-" + i;
            return `
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button ${i === 0 ? "" : "collapsed"}" type="button"
                        data-bs-toggle="collapse" data-bs-target="#${qId}"
                        aria-expanded="${i === 0 ? "true" : "false"}" aria-controls="${qId}">
                        ${esc(faq.question)}
                    </button>
                </h3>
                <div id="${qId}" class="accordion-collapse collapse ${i === 0 ? "show" : ""}" data-bs-parent="#post-faq-accordion">
                    <div class="accordion-body">${esc(faq.answer)}</div>
                </div>
            </div>`;
        }).join("");
        wrap.style.display = "";
    }

    function renderInternalLinks(post) {
        if (!post.internalLinks || !post.internalLinks.length) return;
        const seen = new Set();
        const unique = post.internalLinks.filter(function (l) {
            if (seen.has(l.href)) return false;
            seen.add(l.href);
            return true;
        });
        if (!unique.length) return;
        const wrap = document.getElementById("post-links-block");
        const list = document.getElementById("post-links-list");
        list.innerHTML = unique.map(function (l) {
            return `<li><a href="${esc(l.href)}">${esc(l.text || l.href)}</a></li>`;
        }).join("");
        wrap.style.display = "";
    }

    function cardHtml(post) {
        return `
        <div class="col-md-6 col-lg-4">
            <a href="${esc(post.urlPath)}" class="blog-card">
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

    function renderRelated(post, allPosts) {
        let related = allPosts.filter(function (p) {
            return p.slug !== post.slug && p.category === post.category;
        });
        if (related.length < 3) {
            const others = allPosts.filter(function (p) {
                return p.slug !== post.slug && related.indexOf(p) === -1;
            });
            related = related.concat(others).slice(0, 3);
        } else {
            related = related.slice(0, 3);
        }
        if (!related.length) return;
        document.getElementById("related-posts-grid").innerHTML = related.map(cardHtml).join("");
        document.getElementById("related-posts-section").style.display = "";
    }

    function renderShareLinks(post) {
        const url = encodeURIComponent(SITE_BASE + post.urlPath);
        const title = encodeURIComponent(post.title);
        document.getElementById("share-facebook").href = "https://www.facebook.com/sharer/sharer.php?u=" + url;
        document.getElementById("share-twitter").href = "https://twitter.com/intent/tweet?url=" + url + "&text=" + title;
        document.getElementById("share-whatsapp").href = "https://wa.me/?text=" + title + "%20" + url;
        document.getElementById("share-linkedin").href = "https://www.linkedin.com/sharing/share-offsite/?url=" + url;
    }

    function injectSeo(post) {
        const url = post.canonicalUrl || (SITE_BASE + post.urlPath);
        document.title = post.metaTitle;
        setMeta("meta-description", "content", post.metaDescription);
        setMeta("meta-canonical", "href", post.canonicalUrl || url);
        setMeta("meta-hreflang", "href", post.canonicalUrl || url);
        setMeta("og-title", "content", post.metaTitle);
        setMeta("og-description", "content", post.metaDescription);
        setMeta("og-url", "content", url);
        setMeta("og-image", "content", SITE_BASE + post.featuredImage.src);
        setMeta("twitter-title", "content", post.metaTitle);
        setMeta("twitter-description", "content", post.metaDescription);

        const article = {
            "@context": "https://schema.org",
            "@type": "BlogPosting",
            "headline": post.title,
            "description": post.metaDescription,
            "image": SITE_BASE + post.featuredImage.src,
            "datePublished": post.publishDate,
            "dateModified": post.modifiedDate || post.publishDate,
            "author": { "@type": "Organization", "name": post.author || "The Omega Group" },
            "publisher": {
                "@type": "Organization",
                "name": "The Omega Group",
                "logo": { "@type": "ImageObject", "url": SITE_BASE + "/img/logo.png" }
            },
            "mainEntityOfPage": { "@type": "WebPage", "@id": url },
            "keywords": [post.focusKeyword].concat(post.tags || []).filter(Boolean).join(", ")
        };
        document.getElementById("ld-article").textContent = JSON.stringify(article);

        const breadcrumb = {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "itemListElement": [
                { "@type": "ListItem", "position": 1, "name": "Home", "item": SITE_BASE + "/index.html" },
                { "@type": "ListItem", "position": 2, "name": "Blog", "item": SITE_BASE + "/blog.html" },
                { "@type": "ListItem", "position": 3, "name": post.title, "item": url }
            ]
        };
        document.getElementById("ld-breadcrumb").textContent = JSON.stringify(breadcrumb);

        if (post.faqs && post.faqs.length) {
            const faqSchema = {
                "@context": "https://schema.org",
                "@type": "FAQPage",
                "mainEntity": post.faqs.map(function (f) {
                    return {
                        "@type": "Question",
                        "name": f.question,
                        "acceptedAnswer": { "@type": "Answer", "text": f.answer }
                    };
                })
            };
            document.getElementById("ld-faq").textContent = JSON.stringify(faqSchema);
        }
    }

    function render(post, allPosts) {
        injectSeo(post);
        setMeta("crumb-category", "text", post.category);
        setMeta("post-title", "text", post.title);
        renderMetaRow(post);

        const img = document.getElementById("post-featured-image");
        img.src = post.featuredImage.src;
        img.alt = post.featuredImage.alt;
        img.width = post.featuredImage.width;
        img.height = post.featuredImage.height;

        renderTakeaways(post);
        renderBodyAndToc(post);
        renderFaqs(post);
        renderInternalLinks(post);
        renderShareLinks(post);
        renderRelated(post, allPosts);

        document.getElementById("post-root").classList.remove("d-none");
    }

    function showNotFound() {
        document.getElementById("post-not-found").classList.remove("d-none");
        document.title = "Article Not Found | The Omega Group";
    }

    document.addEventListener("DOMContentLoaded", function () {
        const slug = getSlugFromUrl();
        if (!slug) {
            showNotFound();
            return;
        }
        fetch(DATA_URL)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const post = data.find(function (p) { return p.slug === slug; });
                if (!post) {
                    showNotFound();
                    return;
                }
                render(post, data);
            })
            .catch(function (err) {
                console.error("Failed to load blog data:", err);
                showNotFound();
            });
    });
})();
