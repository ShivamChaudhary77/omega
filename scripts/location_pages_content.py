# Content data for the 2 pillar + 5 satellite location pages.
# Edit this file, then re-run scripts/build-location-pages.py to regenerate.

PROCESS_HTML = """
<p>Every project — whether it's a single room or a full turnkey build — follows the same five-stage process, run by one design team from first call to final walkthrough:</p>
<ul>
    <li><strong>Consultation.</strong> We start with a detailed conversation about your space, lifestyle, and budget — in person at your site wherever possible.</li>
    <li><strong>Design planning.</strong> Our designers translate that brief into layouts, mood boards, and material concepts for your sign-off.</li>
    <li><strong>Material selection.</strong> We curate finishes, fittings, and furnishings from trusted premium suppliers to match your design and budget.</li>
    <li><strong>Execution.</strong> Our site teams carry out the build, with quality checks at every milestone — 70+ checks across a typical project.</li>
    <li><strong>Final handover.</strong> We complete a walkthrough with you before handing over a space that's ready to live or work in.</li>
</ul>
"""

RECENT_PROJECTS_HTML = """
<p>A few of the homes we've recently designed and delivered end-to-end:</p>
<ul>
    <li><strong>SOBHA</strong> — apartment interior, open-plan living and dining.</li>
    <li><strong>Shree Balaji CGHS</strong> — modular kitchen, white cabinetry with dark countertops.</li>
    <li><strong>Pareena Heights</strong> — apartment interior, compact modern living space.</li>
    <li><strong>M3M Woodshire</strong> — villa interior, moody dark-toned living room and lounge.</li>
    <li><strong>Hpuri Emerald Bay</strong> — modular kitchen, traditional wood-finished with center island.</li>
    <li><strong>Urban Loft</strong> — apartment interior, contemporary open-plan kitchen and dining.</li>
</ul>
<p>See the full portfolio, with photos, on our <a href="/project.html">Projects page</a>.</p>
"""

COST_HTML = """
<p>Full-home turnkey interior design with The Omega Group starts at <strong>₹15 Lakhs+</strong>, scaling with home size, material grade, and level of customization. Every quote is itemised — you see exactly what you're paying for before work begins, no hidden line items added mid-project.</p>
<p>For a detailed, size-by-size cost breakdown, see our <a href="/home-residential-interiors/interior-design-cost-in-gurgaon-2bhk-3bhk-4bhk-2026/">2BHK/3BHK/4BHK interior design cost guide</a> and <a href="/kitchen-design/modular-kitchen-cost-gurgaon-2026/">modular kitchen cost guide</a> for Gurgaon.</p>
"""

SERVICE_AREAS_HTML = """
<p>The Omega Group is headquartered on Dwarka Expressway and serves clients across every major Gurgaon micro-market:</p>
<ul>
    <li><a href="/interior-designers-dwarka-expressway/">Dwarka Expressway</a></li>
    <li><a href="/interior-designers-sohna-road/">Sohna Road</a></li>
    <li><a href="/interior-designers-sushant-lok/">Sushant Lok</a></li>
    <li><a href="/interior-designers-new-gurgaon/">New Gurgaon</a></li>
    <li><a href="/interior-designers-dlf-gurgaon/">DLF Gurgaon</a></li>
    <li><a href="/interior-designers-manesar/">Manesar</a></li>
</ul>
"""

FACTS = [
    ("Years Experience", "10+"),
    ("Projects Delivered", "90+"),
    ("On-Time Delivery", "90%"),
    ("QC Checks / Project", "70+"),
]


def satellite_content(location, slug, hero_image, nearby_note):
    return {
        "meta_title": f"Interior Designer in {location} | The Omega Group",
        "keywords": f"interior designer {location.lower()}, interior design {location.lower()} gurgaon, home interior designer {location.lower()}",
        "meta_description": f"Looking for an interior designer in {location}, Gurgaon? The Omega Group designs and delivers luxury home and commercial interiors with full turnkey execution — 10+ years, 90+ projects, 90% on-time delivery.",
        "hero_image": hero_image,
        "h1": f"Interior Designer in {location}",
        "breadcrumb": [("Home", "/index.html"), (f"Interior Designer in {location}", None)],
        "direct_answer": (
            f"The Omega Group is a Gurgaon-based luxury interior design and turnkey execution studio serving "
            f"{location} and the surrounding area. {nearby_note} We handle design, material sourcing, and on-site "
            f"execution as one accountable team — from first consultation to final handover."
        ),
        "facts": FACTS,
        "takeaways": [
            f"The Omega Group designs and delivers residential and commercial interiors for clients in {location} and across Gurgaon.",
            "Full turnkey execution — one team for design, procurement, and site work, not separate vendors to coordinate.",
            "10+ years of experience, 90+ projects delivered, with 90% completed on schedule.",
        ],
        "sections": [
            (f"Interior Design Services in {location}",
             f"<p>Whether you're furnishing a new apartment, renovating a villa, or fitting out an office in {location}, "
             f"our team covers the full range of interior design work: full-home and apartment interiors, modular "
             f"kitchens, bespoke furniture, and office &amp; commercial interiors. See the complete list on our "
             f"<a href=\"/service.html\">Services page</a>.</p>"),
            (f"Why {location} Homeowners Choose The Omega Group",
             f"<p>Homeowners in {location} work with us because every project is led by one design team from concept "
             f"to handover — not a design firm on one side and a separate execution contractor on the other. That "
             f"single point of accountability is what keeps timelines and budgets predictable, backed by 70+ quality "
             f"checks across a typical project.</p>"),
            ("Our Interior Design Process", PROCESS_HTML),
            ("Interior Design Costs", COST_HTML),
            ("Areas We Serve Near " + location, SERVICE_AREAS_HTML),
        ],
        "faqs": [
            (f"Does The Omega Group work in {location}?",
             f"Yes — The Omega Group designs and executes residential and commercial interior projects for clients in {location} and across Gurgaon, including Dwarka Expressway, Sohna Road, Sushant Lok, New Gurgaon, DLF, and Manesar."),
            ("What interior design services do you offer?",
             "Full-home and apartment interiors, villa interiors, modular kitchens, bespoke furniture, and office &amp; commercial interiors, all delivered on a turnkey basis — design, procurement, and execution under one team."),
            ("How much does interior design cost?",
             "Full-home turnkey interior design starts at ₹15 Lakhs+, scaling with home size, material grade, and customization. See our detailed cost guides on the blog for a size-by-size breakdown."),
            ("How do I get started?",
             "Book a complimentary design consultation through our Contact page or call us directly — we'll discuss your space, budget, and timeline before proposing a design direction."),
        ],
        "internal_links": [
            ("Interior Designers in Gurgaon (full service area)", "/interior-designers-gurgaon/"),
            ("Our Services", "/service.html"),
            ("Our Projects", "/project.html"),
            ("Book a Consultation", "/contact.html"),
        ],
        "cta_context": f"{location.lower()} interior design project",
    }


CONTENT = {
    "interior-designers-gurgaon": {
        "meta_title": "Interior Designers in Gurgaon | The Omega Group",
        "keywords": "interior designer gurgaon, interior designers in gurgaon, best interior designer gurgaon, top 10 interior designers in gurgaon, luxury interior designers in gurgaon, turnkey interior contractors in gurgaon",
        "meta_description": "The Omega Group is a Gurgaon-based luxury interior design and turnkey execution studio — 10+ years, 90+ projects, 90% on-time delivery. Residential and commercial interiors across every Gurgaon micro-market.",
        "hero_image": "img/hero-slider-2.jpg",
        "h1": "Interior Designers in Gurgaon",
        "breadcrumb": [("Home", "/index.html"), ("Interior Designers in Gurgaon", None)],
        "direct_answer": (
            "The Omega Group is a Gurgaon-based luxury interior design and turnkey execution studio, headquartered on "
            "Dwarka Expressway, serving homeowners and businesses across every major Gurgaon micro-market — DLF, Sushant "
            "Lok, Sohna Road, New Gurgaon, Dwarka Expressway, and Manesar. We've delivered 90+ residential and commercial "
            "projects over 10+ years, with 90% completed on schedule, managing design, procurement, and site execution "
            "as one accountable team."
        ),
        "facts": FACTS,
        "takeaways": [
            "10+ years designing and executing homes and commercial spaces across Gurgaon.",
            "90+ projects delivered, 90% completed on schedule — one team from design to handover, not separate vendors.",
            "Full turnkey execution: design, procurement, and site work under a single point of contact.",
            "Serving all major Gurgaon micro-markets — DLF, Sushant Lok, Sohna Road, New Gurgaon, Dwarka Expressway, Manesar.",
        ],
        "sections": [
            ("Why Homeowners in Gurgaon Choose The Omega Group",
             "<p>Gurgaon has no shortage of interior designers — the challenge is finding one who stays accountable "
             "from the first sketch through to the final coat of paint. The Omega Group runs design and execution as "
             "one team, so there's a single point of contact throughout your project, premium materials sourced "
             "directly through us, and a quality-check process — 70+ checks on a typical project — built into every "
             "milestone rather than left to a final inspection.</p>"),
            ("Interior Design Services We Offer in Gurgaon",
             "<p>We design and execute the full range of residential and commercial interiors: full-home and "
             "apartment interiors, villa interiors, modular kitchens, bespoke furniture, and office &amp; commercial "
             "fit-outs. Every engagement is scoped around your space, budget, and timeline — see the complete "
             "breakdown on our <a href=\"/service.html\">Services page</a>.</p>"),
            ("Our Interior Design Process", PROCESS_HTML),
            ("Recent Projects in Gurgaon", RECENT_PROJECTS_HTML),
            ("Areas We Serve Across Gurgaon", SERVICE_AREAS_HTML),
            ("Interior Design Costs in Gurgaon", COST_HTML),
        ],
        "faqs": [
            ("Who is the best interior designer in Gurgaon?",
             "There's no single objective answer, but The Omega Group is a strong fit if you want one accountable team handling design and execution together: 10+ years' experience, 90+ projects delivered, and a 90% on-time delivery rate, backed by 70+ quality checks per project."),
            ("How much does interior design cost in Gurgaon?",
             "Full-home turnkey interior design with The Omega Group starts at ₹15 Lakhs+, scaling with home size, material grade, and customization. See our 2BHK/3BHK/4BHK cost guide on the blog for a detailed, size-by-size breakdown."),
            ("Does The Omega Group provide turnkey interior design in Gurgaon?",
             "Yes — design, material procurement, and on-site execution are all managed by our own team, so you're not coordinating separate designers and contractors."),
            ("Which areas of Gurgaon do you serve?",
             "We serve clients across Gurgaon, including DLF, Sushant Lok, Sohna Road, New Gurgaon, Dwarka Expressway (where we're headquartered), and Manesar."),
            ("How long does a full home interior project take in Gurgaon?",
             "Most full-home turnkey projects run 8–16 weeks from design sign-off to handover, depending on home size and the level of customization."),
            ("Do you design both residential and commercial spaces in Gurgaon?",
             "Yes — homes, villas, and apartments as well as office and commercial interiors, all under the same turnkey process."),
        ],
        "internal_links": [
            ("Interior Designer in Dwarka Expressway", "/interior-designers-dwarka-expressway/"),
            ("Interior Designer in Sohna Road", "/interior-designers-sohna-road/"),
            ("Interior Designer in Sushant Lok", "/interior-designers-sushant-lok/"),
            ("Interior Designer in New Gurgaon", "/interior-designers-new-gurgaon/"),
            ("Interior Designer in DLF Gurgaon", "/interior-designers-dlf-gurgaon/"),
            ("Interior Designer in Manesar", "/interior-designers-manesar/"),
            ("Our Services", "/service.html"),
            ("Our Projects", "/project.html"),
            ("Book a Consultation", "/contact.html"),
        ],
        "cta_context": "Gurgaon interior design project",
    },

    "interior-designers-dwarka-expressway": {
        "meta_title": "Interior Designer in Dwarka Expressway | The Omega Group",
        "keywords": "interior designer dwarka expressway, interior designer in dwarka, best interior designer in dwarka, best home interior designer in dwarka, interior designers in dwarka",
        "meta_description": "The Omega Group is headquartered on Dwarka Expressway and designs luxury home and commercial interiors for the corridor — 10+ years, 90+ projects, full turnkey execution from design to handover.",
        "hero_image": "img/about-1.jpg",
        "h1": "Interior Designer in Dwarka Expressway",
        "breadcrumb": [("Home", "/index.html"), ("Interior Designer in Dwarka Expressway", None)],
        "direct_answer": (
            "The Omega Group is a luxury interior design and turnkey execution studio headquartered directly on Main "
            "Dwarka Expressway (Plot S4 &amp; S5, near Daulatabad Chowk, opposite Pillar 109) — not a Gurgaon-wide firm "
            "with a satellite office here, but a Dwarka Expressway-based team that knows the corridor's new-possession "
            "towers and villa developments firsthand. We've delivered 90+ projects over 10+ years, managing design, "
            "material sourcing, and execution as one accountable team."
        ),
        "facts": FACTS,
        "takeaways": [
            "Headquartered on Main Dwarka Expressway itself — not a branch office covering the area remotely.",
            "10+ years' experience, 90+ projects delivered, 90% completed on schedule.",
            "Full turnkey execution: one team for design, procurement, and site work.",
            "Specialist experience with new-possession apartment interiors and villa projects along the corridor.",
        ],
        "sections": [
            ("Why a Dwarka Expressway-Based Interior Designer Matters",
             "<p>Dwarka Expressway has grown fast — new-possession towers, half-finished amenities, and villa plots "
             "all along the same corridor, each with its own handover quirks, society approval processes, and site "
             "access rules. Being headquartered on the expressway itself (Plot S4 &amp; S5, near Daulatabad Chowk, "
             "opposite Pillar 109) means our team can walk a site, meet a facility manager, or check on material "
             "delivery without treating it as an out-of-territory visit.</p>"),
            ("Interior Design Services on Dwarka Expressway",
             "<p>Full-home and apartment interiors, villa interiors, modular kitchens, bespoke furniture, and office "
             "&amp; commercial fit-outs — the same full-service range we offer across Gurgaon, delivered turnkey. See "
             "the complete breakdown on our <a href=\"/service.html\">Services page</a>.</p>"),
            ("Our Interior Design Process", PROCESS_HTML),
            ("Recent Projects", RECENT_PROJECTS_HTML),
            ("Interior Design Costs on Dwarka Expressway", COST_HTML),
            ("Also Serving Nearby Gurgaon Areas",
             "<p>Beyond Dwarka Expressway, we serve clients across Gurgaon — see our "
             "<a href=\"/interior-designers-gurgaon/\">Gurgaon interior design page</a> for the full list of areas, "
             "including Sohna Road, Sushant Lok, New Gurgaon, DLF, and Manesar.</p>"),
        ],
        "faqs": [
            ("Who is the best interior designer in Dwarka Expressway?",
             "The Omega Group is headquartered directly on Main Dwarka Expressway, with 10+ years' experience and 90+ projects delivered — a genuinely local team rather than a Gurgaon-wide firm covering the area remotely."),
            ("Where exactly is The Omega Group located?",
             "Plot S4 &amp; S5, Main Dwarka Expressway, near Daulatabad Chowk, opposite Pillar 109, Gurugram, Haryana 122006."),
            ("Do you handle new-possession apartment interiors on Dwarka Expressway?",
             "Yes — new-possession towers are a large part of our work on the corridor, including coordinating with society management on material delivery and site access."),
            ("How much does interior design cost on Dwarka Expressway?",
             "Full-home turnkey interior design starts at ₹15 Lakhs+, the same pricing structure as our other Gurgaon projects — see our blog cost guides for a size-by-size breakdown."),
            ("Do you also serve areas beyond Dwarka Expressway?",
             "Yes — Sohna Road, Sushant Lok, New Gurgaon, DLF, and Manesar. See our Gurgaon interior design page for the full service area."),
        ],
        "internal_links": [
            ("Interior Designers in Gurgaon (full service area)", "/interior-designers-gurgaon/"),
            ("Interior Designer in New Gurgaon", "/interior-designers-new-gurgaon/"),
            ("Interior Designer in Sohna Road", "/interior-designers-sohna-road/"),
            ("Our Services", "/service.html"),
            ("Our Projects", "/project.html"),
            ("Book a Consultation", "/contact.html"),
        ],
        "cta_context": "Dwarka Expressway interior design project",
    },

    "interior-designers-sohna-road": satellite_content(
        "Sohna Road", "interior-designers-sohna-road", "img/project-2.jpg",
        "Sohna Road's apartment and villa developments are a short drive from our Dwarka Expressway studio."
    ),
    "interior-designers-sushant-lok": satellite_content(
        "Sushant Lok", "interior-designers-sushant-lok", "img/project-3.jpg",
        "Sushant Lok's mix of independent floors and apartment complexes is well within our regular Gurgaon service area."
    ),
    "interior-designers-new-gurgaon": satellite_content(
        "New Gurgaon", "interior-designers-new-gurgaon", "img/project-5.jpg",
        "New Gurgaon's newer sectors sit close to our Dwarka Expressway studio, along the same growth corridor."
    ),
    "interior-designers-dlf-gurgaon": satellite_content(
        "DLF Gurgaon", "interior-designers-dlf-gurgaon", "img/project-6.jpg",
        "From DLF Phase 1 through Phase 5, we regularly work across DLF's established residential phases."
    ),
    "interior-designers-manesar": satellite_content(
        "Manesar", "interior-designers-manesar", "img/about-2.jpg",
        "Manesar's residential and industrial-adjacent developments fall within our standard Gurgaon service radius."
    ),
}
