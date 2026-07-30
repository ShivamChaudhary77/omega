/**
 * Renders the per-project structured fact block on project.html from
 * data/projects.json, and emits the matching Project/CreativeWork JSON-LD —
 * one data object powering both, per the SEO plan's Section 4 requirement.
 * Only fields that are actually present in projects.json are shown/emitted;
 * nothing here fabricates a size, budget, timeline, or material.
 */
(function () {
    "use strict";

    function esc(str) {
        const div = document.createElement("div");
        div.textContent = str == null ? "" : String(str);
        return div.innerHTML;
    }

    function factPills(project) {
        const facts = [];
        if (project.sizeSqFt) facts.push(`<span class="fact-pill"><strong>${esc(project.sizeSqFt)} sq. ft.</strong></span>`);
        if (project.budgetBand) facts.push(`<span class="fact-pill"><strong>${esc(project.budgetBand)}</strong> Budget</span>`);
        if (project.timeline) facts.push(`<span class="fact-pill"><strong>${esc(project.timeline)}</strong></span>`);
        if (project.location) facts.push(`<span class="fact-pill">${esc(project.location)}</span>`);
        if (project.materials && project.materials.length) facts.push(`<span class="fact-pill">${esc(project.materials.join(", "))}</span>`);
        return facts;
    }

    function buildCreativeWork(project) {
        const cw = {
            "@context": "https://schema.org",
            "@type": "CreativeWork",
            "name": project.name,
            "about": project.type,
            "url": "https://theomegagroup.in/project.html",
            "creator": { "@type": "LocalBusiness", "@id": "https://theomegagroup.in/#organization" },
            "image": project.image || undefined,
            "locationCreated": project.location || undefined,
        };
        if (project.sizeSqFt) cw.size = `${project.sizeSqFt} sq. ft.`;
        Object.keys(cw).forEach(function (k) { if (cw[k] === undefined) delete cw[k]; });
        return cw;
    }

    document.addEventListener("DOMContentLoaded", function () {
        const cards = document.querySelectorAll("[data-project-slug]");
        if (!cards.length) return;

        fetch("/data/projects.json")
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const projects = data.projects || [];
                const schemaGraph = [];
                cards.forEach(function (card) {
                    const slug = card.getAttribute("data-project-slug");
                    const project = projects.find(function (p) { return p.slug === slug; });
                    if (!project) return;

                    const pills = factPills(project);
                    if (pills.length) {
                        const wrap = document.createElement("div");
                        wrap.className = "fact-strip mt-2 mb-0";
                        wrap.innerHTML = pills.join("");
                        const body = card.querySelector(".project-card-body");
                        if (body) body.appendChild(wrap);
                    }
                    schemaGraph.push(buildCreativeWork(project));
                });

                if (schemaGraph.length) {
                    const el = document.createElement("script");
                    el.type = "application/ld+json";
                    el.id = "ld-projects";
                    el.textContent = JSON.stringify(schemaGraph);
                    document.head.appendChild(el);
                }
            })
            .catch(function (err) { console.error("Failed to load projects.json:", err); });
    });
})();
