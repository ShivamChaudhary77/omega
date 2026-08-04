#!/usr/bin/env python3
"""Generate the 2 pillar + 5 satellite location pages from the CONTENT data
below. Each page is a real static file at <slug>/index.html (same
directory+index.html pattern as the blog, for clean crawlable URLs with zero
server config). Re-run after editing CONTENT to regenerate.

Usage: python3 scripts/build-location-pages.py
"""
import json
import re
from pathlib import Path

SITE_ROOT = Path(__file__).resolve().parent.parent
SITE_BASE = "https://theomegagroup.in"

NAV_LINKS = [
    ("index.html", "Home"),
    ("about.html", "About"),
    ("service.html", "Services"),
    ("project.html", "Projects"),
    ("blog.html", "Blog"),
    ("contact.html", "Contact"),
]

FOOTER_SERVICE_AREAS = [
    ("/interior-designers-gurgaon/", "Gurgaon"),
    ("/interior-designers-dwarka-expressway/", "Dwarka Expressway"),
    ("/interior-designers-sohna-road/", "Sohna Road"),
    ("/interior-designers-sushant-lok/", "Sushant Lok"),
    ("/interior-designers-new-gurgaon/", "New Gurgaon"),
    ("/interior-designers-dlf-gurgaon/", "DLF Gurgaon"),
    ("/interior-designers-manesar/", "Manesar"),
]


def nav_html(active_slug):
    items = []
    for href, label in NAV_LINKS:
        items.append(f'<a href="/{href}" class="nav-item nav-link">{label}</a>')
    return "\n                        ".join(items)


def breadcrumb_html(crumbs):
    # crumbs: list of (label, href_or_None) — last one has href None (active)
    parts = []
    for i, (label, href) in enumerate(crumbs):
        if href:
            parts.append(f'<li class="breadcrumb-item"><a class="text-primary" href="{href}">{label}</a></li>')
        else:
            parts.append(f'<li class="breadcrumb-item text-secondary active" aria-current="page">{label}</li>')
    return "\n                            ".join(parts)


def takeaways_html(items):
    return "\n                    ".join(f"<li>{i}</li>" for i in items)


def sections_html(sections):
    out = []
    for h2, body_html in sections:
        out.append(f"<h2>{h2}</h2>\n{body_html}")
    return "\n\n                ".join(out)


def faq_accordion_html(faqs, accordion_id):
    items = []
    for i, (q, a) in enumerate(faqs):
        cid = f"{accordion_id}-{i}"
        items.append(f"""
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button {'' if i == 0 else 'collapsed'}" type="button"
                                data-bs-toggle="collapse" data-bs-target="#{cid}"
                                aria-expanded="{'true' if i == 0 else 'false'}" aria-controls="{cid}">
                                {q}
                            </button>
                        </h3>
                        <div id="{cid}" class="accordion-collapse collapse {'show' if i == 0 else ''}" data-bs-parent="#{accordion_id}">
                            <div class="accordion-body">{a}</div>
                        </div>
                    </div>""")
    return "".join(items)


def internal_links_html(links):
    return "\n                    ".join(f'<li><a href="{href}">{text}</a></li>' for text, href in links)


def fact_strip_html(facts):
    if not facts:
        return ""
    pills = "\n                ".join(f'<span class="fact-pill"><strong>{v}</strong> {l}</span>' for l, v in facts)
    return f'<div class="fact-strip">\n                {pills}\n            </div>\n'


PAGE_TEMPLATE = """<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">

    <!-- Google Search Console verification -->
    <meta name="google-site-verification" content="WfK6bn2Y0riMl83guJlVGhq8GrnN6rbKjyn5Nrk43Ls" />

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-FSNHC0N7BD"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){{dataLayer.push(arguments);}}
        gtag('js', new Date());
        gtag('config', 'G-FSNHC0N7BD');
    </script>
    <title>{meta_title}</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="keywords" content="{keywords}">
    <meta name="description" content="{meta_description}">
    <link rel="canonical" href="{canonical}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="The Omega Group">
    <meta property="og:title" content="{meta_title}">
    <meta property="og:description" content="{meta_description}">
    <meta property="og:url" content="{canonical}">
    <meta property="og:image" content="{SITE_BASE}/{hero_image}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{meta_title}">
    <meta name="twitter:description" content="{meta_description}">

    <!-- Favicon -->
    <link href="/img/favicon.svg" rel="icon" type="image/svg+xml">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Space+Grotesk&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="/lib/animate/animate.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="/css/style.css" rel="stylesheet">
    <link href="/css/blog.css" rel="stylesheet">

    <!-- Preload LCP image -->
    <link rel="preload" as="image" href="/{hero_image}">
</head>

<body>
    <!-- Spinner Start -->
    <div id="spinner"
        class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->


    <!-- Navbar Start -->
    <div class="container-fluid sticky-top">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light border-bottom border-2 border-white">
                <a href="/index.html" class="navbar-brand">
                    <img class="brand-logo" src="/img/logo.png" alt="The Omega Group logo" width="300" height="42">
                </a>
                <button type="button" class="navbar-toggler ms-auto me-0" data-bs-toggle="collapse"
                    data-bs-target="#navbarCollapse" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ms-auto">
                        {nav}
                    </div>
                </div>
            </nav>
        </div>
    </div>
    <!-- Navbar End -->


    <!-- Hero Start -->
    <div class="container-fluid pb-5 hero-header hero-home">
        <div class="container py-5">
            <div class="hero-glass row g-3 align-items-center p-4 p-lg-5 mx-0">
                <div class="col-lg-7 text-center text-lg-start">
                    <h1 class="display-5 mb-0 animated slideInLeft">{h1}</h1>
                </div>
                <div class="col-lg-5 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            {breadcrumb}
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    <!-- Hero End -->


    <div class="container py-5">
        <div class="post-layout">

            <p class="lead-answer">{direct_answer}</p>

            {fact_strip}
            <div class="key-takeaways-box">
                <h2><i class="bi bi-lightbulb"></i> Key Takeaways</h2>
                <ul>
                    {takeaways}
                </ul>
            </div>

            <details class="post-toc" data-toc-wrap style="display:none;">
                <summary>What's in this page</summary>
                <ul class="post-toc-list" data-toc-target></ul>
            </details>

            <div class="post-body" data-toc-source>
                {sections}
            </div>

            <div class="post-faq" data-faq-schema>
                <h2 class="h3 mb-4">Frequently Asked Questions</h2>
                <div class="accordion" id="{accordion_id}">{faq_accordion}
                </div>
            </div>

            <div class="post-links-block">
                <h2>Explore More</h2>
                <ul>
                    {internal_links}
                </ul>
            </div>

        </div>
    </div>


    <!-- Final CTA Start -->
    <div class="container-fluid bg-primary newsletter p-0">
        <div class="container p-0">
            <div class="row g-0 align-items-center">
                <div class="col-md-5 ps-lg-0 text-start wow fadeIn" data-wow-delay="0.2s">
                    <img class="img-fluid w-100 h-100" style="object-fit: cover;" src="/img/cta-consultation.jpg"
                        alt="Luxury bedroom suite with marble accent wall and custom dressing area designed by The Omega Group"
                        loading="lazy" width="720" height="480">
                </div>
                <div class="col-md-7 py-5 newsletter-text wow fadeIn" data-wow-delay="0.5s">
                    <div class="p-5">
                        <span class="text-uppercase text-primary fw-bold d-block mb-2" style="letter-spacing: 2px;">Book Your Appointment</span>
                        <h2 class="h1 mb-4">Free Luxury Design Consultation</h2>
                        <p class="text-white mb-4">Talk to our design team about your {cta_context} — no obligation, just clarity on scope, timeline, and budget.</p>
                        <a href="/contact.html" class="btn btn-primary py-3 px-5" data-cta="book-appointment">Book Consultation</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Final CTA End -->


    {footer}


    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top" aria-label="Back to top"><i
            class="bi bi-arrow-up"></i></a>


    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/lib/wow/wow.min.js"></script>
    <script src="/lib/easing/easing.min.js"></script>
    <script src="/lib/waypoints/waypoints.min.js"></script>

    <!-- Template Javascript -->
    <script src="/js/main.js"></script>
    <script src="/js/toc.js"></script>
    <script src="/js/schema.js"></script>
    <script src="/js/analytics.js"></script>
</body>

</html>
"""

FOOTER_TEMPLATE = """<!-- Footer Start -->
    <div class="container-fluid bg-dark text-white-50 footer pt-5">
        <div class="container py-5">
            <div class="row g-5">
                <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="0.1s">
                    <a href="/index.html" class="d-inline-block mb-3 brand-mark">
                        <img class="brand-logo" src="/img/logo.png" alt="The Omega Group logo" width="300" height="42">
                    </a>
                    <p class="mb-0">The Omega Group is a premium interior design and turnkey execution studio,
                        crafting bespoke homes and commercial spaces for clients who expect nothing less than
                        excellence.</p>
                </div>
                <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="0.3s">
                    <h5 class="text-white mb-4">Quick Links</h5>
                    <a class="btn btn-link" href="/about.html">About Us</a>
                    <a class="btn btn-link" href="/project.html">Our Projects</a>
                    <a class="btn btn-link" href="/service.html">Our Services</a>
                    <a class="btn btn-link" href="/blog.html">Blog</a>
                    <a class="btn btn-link" href="/faqs.html">FAQs</a>
                    <a class="btn btn-link" href="/contact.html">Book a Consultation</a>
                </div>
                <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="0.5s">
                    <h5 class="text-white mb-4">Service Areas</h5>
                    __SERVICE_AREAS__
                </div>
                <div class="col-md-6 col-lg-3 wow fadeIn" data-wow-delay="0.7s">
                    <h5 class="text-white mb-4">Get In Touch</h5>
                    <p><i class="fa fa-map-marker-alt me-3"></i>Plot S4 &amp; S5, Main Dwarka Expressway, Near, Daulatabad Chowk, opp. Pillar 109, Gurugram, Haryana 122006</p>
                    <p><i class="fa fa-phone-alt me-3"></i>+91 981 1001 900</p>
                    <p><i class="fa fa-envelope me-3"></i>thegroupomega@gmail.com</p>
                    <div class="d-flex pt-2">
                        <a class="btn btn-outline-primary btn-square border-2 me-2"
                            href="https://www.facebook.com/theomegainteriors" target="_blank" rel="noopener"
                            aria-label="The Omega Group on Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a class="btn btn-outline-primary btn-square border-2 me-2"
                            href="https://www.instagram.com/theomegainteriors/" target="_blank" rel="noopener"
                            aria-label="The Omega Group on Instagram"><i class="fab fa-instagram"></i></a>
                        <a class="btn btn-outline-primary btn-square border-2"
                            href="https://wa.me/919811001900" target="_blank" rel="noopener"
                            aria-label="Chat with The Omega Group on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>
        </div>
        <div class="container wow fadeIn" data-wow-delay="0.1s">
            <div class="copyright">
                <div class="row">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        &copy; <a class="border-bottom" href="/index.html">The Omega Group</a>, All Rights Reserved.
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <div class="footer-menu">
                            <a href="/index.html">Home</a>
                            <a href="/about.html">About</a>
                            <a href="/service.html">Services</a>
                            <a href="/blog.html">Blog</a>
                            <a href="/contact.html">Contact</a>
                            <a href="/privacy-policy.html">Privacy Policy</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End -->"""


def build_footer():
    links = "\n                    ".join(f'<a class="btn btn-link" href="{href}">{label}</a>' for href, label in FOOTER_SERVICE_AREAS)
    return FOOTER_TEMPLATE.replace("__SERVICE_AREAS__", links)


def render_page(slug, data):
    canonical = f"{SITE_BASE}/{slug}/"
    html = PAGE_TEMPLATE.format(
        meta_title=data["meta_title"],
        keywords=data["keywords"],
        meta_description=data["meta_description"],
        canonical=canonical,
        SITE_BASE=SITE_BASE,
        hero_image=data["hero_image"],
        nav=nav_html(slug),
        h1=data["h1"],
        breadcrumb=breadcrumb_html(data["breadcrumb"]),
        direct_answer=data["direct_answer"],
        fact_strip=fact_strip_html(data.get("facts")),
        takeaways=takeaways_html(data["takeaways"]),
        sections=sections_html(data["sections"]),
        accordion_id=f"faq-{slug}",
        faq_accordion=faq_accordion_html(data["faqs"], f"faq-{slug}"),
        internal_links=internal_links_html(data["internal_links"]),
        cta_context=data["cta_context"],
        footer=build_footer(),
    )
    target_dir = SITE_ROOT / slug
    target_dir.mkdir(parents=True, exist_ok=True)
    (target_dir / "index.html").write_text(html, encoding="utf-8")
    print(f"wrote {slug}/index.html ({len(html)} bytes)")


if __name__ == "__main__":
    from location_pages_content import CONTENT
    for slug, data in CONTENT.items():
        render_page(slug, data)
