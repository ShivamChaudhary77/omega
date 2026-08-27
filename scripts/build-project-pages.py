#!/usr/bin/env python3
"""Generate individual /project/<slug>/index.html detail pages from
data/projects.json, for projects that have a real indexed 'detailPageSlug'
(confirmed via the GSC Coverage export, Aug 2026). Real facts only — no
sq. ft./budget/timeline are shown unless data/projects.json actually has
them (see that file's _note).

Usage: python3 scripts/build-project-pages.py
"""
import json
from pathlib import Path

SITE_ROOT = Path(__file__).resolve().parent.parent
SITE_BASE = "https://theomegagroup.in"

NAV = """<a href="/index.html" class="nav-item nav-link">Home</a>
                        <a href="/about.html" class="nav-item nav-link">About</a>
                        <a href="/service.html" class="nav-item nav-link">Services</a>
                        <a href="/project.html" class="nav-item nav-link active">Projects</a>
                        <a href="/blog.html" class="nav-item nav-link">Blog</a>
                        <a href="/contact.html" class="nav-item nav-link">Contact</a>"""

FOOTER = """<!-- Footer Start -->
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
                <div class="col-md-6 col-lg-2 wow fadeIn" data-wow-delay="0.3s">
                    <h5 class="text-white mb-4">Quick Links</h5>
                    <a class="btn btn-link" href="/about.html">About Us</a>
                    <a class="btn btn-link" href="/project.html">Our Projects</a>
                    <a class="btn btn-link" href="/service.html">Our Services</a>
                    <a class="btn btn-link" href="/blog.html">Blog</a>
                    <a class="btn btn-link" href="/faqs.html">FAQs</a>
                    <a class="btn btn-link" href="/contact.html">Book a Consultation</a>
                </div>
                <div class="col-md-6 col-lg-2 wow fadeIn" data-wow-delay="0.4s">
                    <h5 class="text-white mb-4">Service Areas</h5>
                    <a class="btn btn-link" href="/interior-designers-gurgaon/">Gurgaon</a>
                    <a class="btn btn-link" href="/interior-designers-dwarka-expressway/">Dwarka Expressway</a>
                    <a class="btn btn-link" href="/interior-designers-sohna-road/">Sohna Road</a>
                    <a class="btn btn-link" href="/interior-designers-sushant-lok/">Sushant Lok</a>
                    <a class="btn btn-link" href="/interior-designers-new-gurgaon/">New Gurgaon</a>
                    <a class="btn btn-link" href="/interior-designers-dlf-gurgaon/">DLF Gurgaon</a>
                    <a class="btn btn-link" href="/interior-designers-golf-course-road/">Golf Course Road</a>
                    <a class="btn btn-link" href="/interior-designers-manesar/">Manesar</a>
                </div>
                <div class="col-md-6 col-lg-2 wow fadeIn" data-wow-delay="0.5s">
                    <h5 class="text-white mb-4">Our Services</h5>
                    <a class="btn btn-link" href="/service.html">Luxury Home Interiors</a>
                    <a class="btn btn-link" href="/service.html">Villa &amp; Apartment Interiors</a>
                    <a class="btn btn-link" href="/service.html">Office &amp; Commercial Interiors</a>
                    <a class="btn btn-link" href="/service.html">Bespoke Furniture</a>
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
                    <div class="footer-map">
                        <iframe
                            src="https://www.google.com/maps?q=Plot%20S4%20%26%20S5%2C%20Main%20Dwarka%20Expressway%2C%20Near%2C%20Daulatabad%20Chowk%2C%20opp.%20Pillar%20109%2C%20Gurugram%2C%20Haryana%20122006&amp;output=embed"
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            title="The Omega Group location map"></iframe>
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

TYPE_COPY = {
    "Apartment Interior": (
        "This apartment interior was designed and delivered end-to-end by The Omega Group — layout, "
        "lighting, furniture, and finishes planned as one cohesive whole rather than room-by-room "
        "afterthoughts. As with every Omega Group apartment project, the brief balanced everyday livability "
        "with a considered, premium finish."
    ),
    "Villa Interior": (
        "This villa interior was designed and delivered end-to-end by The Omega Group, balancing the "
        "expansive scale of independent-villa living with a considered, liveable luxury — indoor-outdoor "
        "flow, layered lighting, and finishes chosen to suit the home's proportions rather than a generic "
        "template."
    ),
    "Modular Kitchen": (
        "This modular kitchen was designed and installed by The Omega Group, built around real cooking "
        "habits and storage needs rather than a stock layout — the same zoning and material-selection "
        "process we walk every renovation client through."
    ),
}

FACT_LABELS = [
    ("configuration", "Configuration"),
    ("location", None),  # location shown as its own pill without a prefix label
    ("sizeSqFt", "Size"),
    ("budgetBand", "Budget"),
    ("timeline", "Timeline"),
]

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
    <meta name="description" content="{meta_description}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{canonical}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="The Omega Group">
    <meta property="og:title" content="{meta_title}">
    <meta property="og:description" content="{meta_description}">
    <meta property="og:url" content="{canonical}">
    <meta property="og:image" content="{image_abs}">
    <meta name="twitter:card" content="summary_large_image">

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
    <link href="/css/lightbox.css" rel="stylesheet">

    <link rel="preload" as="image" href="{image}">

    <script type="application/ld+json">{creative_work_json}</script>
</head>

<body>
    <div id="spinner"
        class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

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

    <div class="container-fluid pb-5 hero-header hero-home">
        <div class="container py-5">
            <div class="hero-glass row g-3 align-items-center p-4 p-lg-5 mx-0">
                <div class="col-lg-7 text-center text-lg-start">
                    <h1 class="display-5 mb-0 animated slideInLeft">{h1}</h1>
                </div>
                <div class="col-lg-5 animated slideInRight">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-end mb-0">
                            <li class="breadcrumb-item"><a class="text-primary" href="/index.html">Home</a></li>
                            <li class="breadcrumb-item"><a class="text-primary" href="/project.html">Projects</a></li>
                            <li class="breadcrumb-item text-secondary active" aria-current="page">{name}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="post-featured-img">
            <img src="{image}" alt="{alt}" width="1200" height="675" fetchpriority="high">
        </div>
    </div>

    <div class="container py-5">
        <div class="post-layout">
            <p class="lead-answer">{direct_answer}</p>

            {fact_strip}

            <div class="post-body">
                <h2>About This Project</h2>
                <p>{type_copy}</p>
                <h2>The Omega Group Approach</h2>
                <p>Every project — including this one — follows the same process: a detailed consultation about
                    the space and how it's actually used, design and material sign-off before any work begins,
                    execution by our own site team with quality checks at every milestone, and a final walkthrough
                    before handover. It's the same process we walk through in detail on our
                    <a href="/interior-designers-gurgaon/">Gurgaon interior design page</a>.</p>
            </div>

            {gallery_section}

            <div class="post-links-block">
                <h2>Explore More</h2>
                <ul>
                    {related_links}
                    <li><a href="/project.html">See all our projects</a></li>
                    <li><a href="/contact.html">Start a project like this one</a></li>
                </ul>
            </div>
        </div>
    </div>

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
                        <p class="text-white mb-4">Like what you see? Talk to our design team about a project for your own home — no obligation, just clarity on scope, timeline, and budget.</p>
                        <a href="/contact.html" class="btn btn-primary py-3 px-5" data-cta="book-appointment">Book Consultation</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {footer}

    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top" aria-label="Back to top"><i
            class="bi bi-arrow-up"></i></a>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/lib/wow/wow.min.js"></script>
    <script src="/lib/easing/easing.min.js"></script>
    <script src="/lib/waypoints/waypoints.min.js"></script>

    <script src="/js/main.js"></script>
    <script src="/js/schema.js"></script>
    <script src="/js/analytics.js"></script>
    <script src="/js/lightbox.js"></script>
</body>

</html>
"""


def fact_pills(p):
    pills = []
    if p.get("configuration"):
        pills.append(f'<span class="fact-pill"><strong>{p["configuration"]}</strong></span>')
    if p.get("location"):
        pills.append(f'<span class="fact-pill">{p["location"]}</span>')
    if p.get("sizeSqFt"):
        pills.append(f'<span class="fact-pill"><strong>{p["sizeSqFt"]} sq. ft.</strong></span>')
    if p.get("budgetBand"):
        pills.append(f'<span class="fact-pill"><strong>{p["budgetBand"]}</strong> Budget</span>')
    if p.get("timeline"):
        pills.append(f'<span class="fact-pill"><strong>{p["timeline"]}</strong></span>')
    pills.append(f'<span class="fact-pill">{p["type"]}</span>')
    return pills


def abs_url(path):
    return path if path.startswith("http") else f"{SITE_BASE}{path}"


def build_creative_work(p, canonical):
    cw = {
        "@context": "https://schema.org",
        "@type": "CreativeWork",
        "name": p["name"],
        "about": p["type"],
        "url": canonical,
        "image": abs_url(p["image"]),
        "creator": {"@type": "LocalBusiness", "@id": "https://theomegagroup.in/#organization"},
    }
    if p.get("location"):
        cw["locationCreated"] = p["location"]
    if p.get("sizeSqFt"):
        cw["size"] = f'{p["sizeSqFt"]} sq. ft.'
    return cw


def build_gallery_section(p):
    gallery = p.get("gallery") or []
    if not gallery:
        return ""
    items = []
    for i, img in enumerate(gallery):
        alt = f"{p['name']} — {p['type']}, photo {i + 1} of {len(gallery)}"
        loading = "eager" if i < 4 else "lazy"
        items.append(
            f'<a href="{img}" class="project-gallery-item" data-lightbox-src="{img}">'
            f'<img src="{img}" alt="{alt}" loading="{loading}"></a>'
        )
    return (
        '<div class="post-body">\n'
        f'                <h2>Project Gallery ({len(gallery)} photos)</h2>\n'
        '                <div class="project-gallery" data-lightbox-gallery>\n'
        '                    ' + "\n                    ".join(items) + '\n'
        '                </div>\n'
        '            </div>'
    )


def main():
    data = json.load(open(SITE_ROOT / "data" / "projects.json", encoding="utf-8"))
    projects = [p for p in data["projects"] if p.get("detailPageSlug")]

    for p in projects:
        slug = p["detailPageSlug"]
        canonical = f"{SITE_BASE}/project/{slug}/"
        location_bit = f", {p['location']}" if p.get("location") else ""
        h1 = f"{p['name']} — {p['type']}{location_bit}"
        meta_title = f"{p['name']} | {p['type']} by The Omega Group"
        meta_description = f"{p['name']} — a {p['type'].lower()} designed and delivered end-to-end by The Omega Group{location_bit}. See the finished space and how we approach every project."
        direct_answer = (
            f"{p['name']} is a {p['type'].lower()} designed and delivered end-to-end by The Omega Group"
            f"{location_bit} — design, material sourcing, and on-site execution managed by one team from "
            f"first consultation to final handover."
        )
        alt = f"{p['name']} — {p['type']} designed by The Omega Group"
        fact_strip = '<div class="fact-strip">\n                ' + "\n                ".join(fact_pills(p)) + '\n            </div>'
        related = [q for q in projects if q["slug"] != p["slug"]][:3]
        related_links = "\n                    ".join(
            f'<li><a href="/project/{q["detailPageSlug"]}/">{q["name"]} — {q["type"]}</a></li>' for q in related
        )
        creative_work_json = json.dumps(build_creative_work(p, canonical), ensure_ascii=False)

        html = PAGE_TEMPLATE.format(
            meta_title=meta_title,
            meta_description=meta_description,
            canonical=canonical,
            image=p["image"],
            image_abs=abs_url(p["image"]),
            creative_work_json=creative_work_json,
            nav=NAV,
            h1=h1,
            name=p["name"],
            alt=alt,
            direct_answer=direct_answer,
            fact_strip=fact_strip,
            type_copy=TYPE_COPY.get(p["type"], TYPE_COPY["Apartment Interior"]),
            gallery_section=build_gallery_section(p),
            related_links=related_links,
            footer=FOOTER,
        )
        target_dir = SITE_ROOT / "project" / slug
        target_dir.mkdir(parents=True, exist_ok=True)
        (target_dir / "index.html").write_text(html, encoding="utf-8")
        print(f"wrote project/{slug}/index.html")


if __name__ == "__main__":
    main()
