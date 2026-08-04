/**
 * Minimal, dependency-free lightbox. Wrap any set of images in a container
 * marked data-lightbox-gallery, with each item as:
 *   <a href="{full-image}" class="project-gallery-item" data-lightbox-src="{full-image}">
 *       <img src="{same-or-smaller-image}" loading="lazy" alt="...">
 *   </a>
 * Clicking opens a fullscreen overlay with next/prev + keyboard navigation.
 * The <a href> is a real link to the image as a no-JS fallback, so galleries
 * stay functional (and crawlable) even without this script.
 */
(function () {
    "use strict";

    let overlay, imgEl, captionEl, counterEl, prevBtn, nextBtn, closeBtn;
    let currentItems = [];
    let currentIndex = 0;
    let lastFocusedEl = null;

    function buildOverlay() {
        if (overlay) return;
        overlay = document.createElement("div");
        overlay.className = "lightbox-overlay";
        overlay.setAttribute("role", "dialog");
        overlay.setAttribute("aria-modal", "true");
        overlay.setAttribute("aria-label", "Image viewer");
        overlay.innerHTML = `
            <button type="button" class="lightbox-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>
            <button type="button" class="lightbox-prev" aria-label="Previous image"><i class="bi bi-chevron-left"></i></button>
            <figure class="lightbox-figure">
                <img alt="">
                <figcaption class="lightbox-caption"></figcaption>
            </figure>
            <button type="button" class="lightbox-next" aria-label="Next image"><i class="bi bi-chevron-right"></i></button>
            <div class="lightbox-counter"></div>
        `;
        document.body.appendChild(overlay);

        imgEl = overlay.querySelector("img");
        captionEl = overlay.querySelector(".lightbox-caption");
        counterEl = overlay.querySelector(".lightbox-counter");
        prevBtn = overlay.querySelector(".lightbox-prev");
        nextBtn = overlay.querySelector(".lightbox-next");
        closeBtn = overlay.querySelector(".lightbox-close");

        closeBtn.addEventListener("click", close);
        prevBtn.addEventListener("click", function () { show(currentIndex - 1); });
        nextBtn.addEventListener("click", function () { show(currentIndex + 1); });
        overlay.addEventListener("click", function (e) {
            if (e.target === overlay) close();
        });
        document.addEventListener("keydown", function (e) {
            if (!overlay.classList.contains("is-open")) return;
            if (e.key === "Escape") close();
            if (e.key === "ArrowLeft") show(currentIndex - 1);
            if (e.key === "ArrowRight") show(currentIndex + 1);
        });
    }

    function show(index) {
        if (!currentItems.length) return;
        currentIndex = (index + currentItems.length) % currentItems.length;
        const item = currentItems[currentIndex];
        imgEl.src = item.src;
        imgEl.alt = item.alt || "";
        captionEl.textContent = item.alt || "";
        counterEl.textContent = `${currentIndex + 1} / ${currentItems.length}`;
        const multi = currentItems.length > 1;
        prevBtn.style.display = multi ? "" : "none";
        nextBtn.style.display = multi ? "" : "none";
    }

    function open(items, index, triggerEl) {
        buildOverlay();
        currentItems = items;
        lastFocusedEl = triggerEl || document.activeElement;
        show(index);
        overlay.classList.add("is-open");
        document.body.style.overflow = "hidden";
        closeBtn.focus();
    }

    function close() {
        if (!overlay) return;
        overlay.classList.remove("is-open");
        document.body.style.overflow = "";
        if (lastFocusedEl && typeof lastFocusedEl.focus === "function") lastFocusedEl.focus();
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("[data-lightbox-gallery]").forEach(function (gallery) {
            const links = Array.from(gallery.querySelectorAll("[data-lightbox-src]"));
            const items = links.map(function (a) {
                const img = a.querySelector("img");
                return { src: a.getAttribute("data-lightbox-src"), alt: img ? img.alt : "" };
            });
            links.forEach(function (a, i) {
                a.addEventListener("click", function (e) {
                    e.preventDefault();
                    open(items, i, a);
                });
            });
        });
    });
})();
