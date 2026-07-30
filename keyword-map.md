# Keyword → URL Map

One governing rule: **every keyword below is assigned to exactly one URL.** If a keyword could plausibly fit two pages, it's listed once, under the page that should legitimately rank for it — the other page must not target it as a primary phrase (incidental mentions in body copy are fine; H1s, title tags, and meta descriptions must not compete).

**Intent boundary that keeps this from recreating the old cannibalization:** the two pillar + five satellite pages own **commercial "hire a designer in [place]" intent**. The blog owns **informational "how do I / how much does / what is" intent**. Same core topic (e.g. modular kitchens) can legitimately have a page on both sides — a satellite page ranks for "interior designer near me in Sohna Road," a blog post ranks for "modular kitchen cost in Gurgaon" — because the search intent, and therefore the ideal result, is different. Where a query is ambiguous, it's assigned to the commercial page, since that's this site's business goal.

## Pillar pages

| URL | Primary keywords (from GSC + plan) | Supporting keyword variants |
|---|---|---|
| `/interior-designers-gurgaon/` | interior designer gurgaon · interior designers in gurgaon · best interior designer gurgaon · top 10 interior designers in gurgaon · luxury interior designers in gurgaon · turnkey interior contractors in gurgaon | interior design company gurgaon · interior designer near me (Gurgaon-intent) · top interior design firms gurgaon · gurgaon interior design studio |
| `/interior-designers-dwarka-expressway/` | interior designer dwarka expressway · interior designer in dwarka · best interior designer in dwarka · best home interior designer in dwarka | interior designers in dwarka · dwarka expressway interior design · home interior designer dwarka expressway |

## Satellite pages (all cross-link primarily into `/interior-designers-gurgaon/`)

| URL | Primary keywords | Supporting keyword variants |
|---|---|---|
| `/interior-designers-sohna-road/` | interior designer sohna road | interior design sohna road gurgaon · home interior designer sohna road |
| `/interior-designers-sushant-lok/` | interior designer sushant lok | interior design sushant lok gurgaon · sushant lok apartment interior designer |
| `/interior-designers-new-gurgaon/` | interior designer new gurgaon | interior design new gurgaon sector · new gurgaon home interior designer |
| `/interior-designers-dlf-gurgaon/` | interior designer dlf gurgaon | interior design dlf phase 1–5 · dlf gurgaon apartment interior designer |
| `/interior-designers-manesar/` | interior designer manesar | interior design manesar gurgaon · manesar home interior designer |

## Existing core pages

| URL | Primary keywords | Notes |
|---|---|---|
| `/index.html` | the omega group · omega interiors and furniture · luxury interior design gurgaon (brand-adjacent, not the bare commercial term) | Brand + category entry point. Must not target the bare "interior designer gurgaon" head term — that's the Gurgaon pillar's job — or the two pages compete. |
| `/about.html` | about the omega group · interior design company gurgaon experience · interior design studio gurgaon | E-E-A-T / trust page, not a ranking target for commercial head terms. |
| `/service.html` | luxury home interior design services · modular kitchen design gurgaon · bespoke furniture design gurgaon · office interior design services gurgaon · turnkey interior design services | Service-category intent (what we offer), not location intent (where/who) — that split is what keeps this from re-overlapping the pillar pages. |
| `/project.html` | interior design portfolio gurgaon · interior design projects gurgaon | |
| `/contact.html` | book interior design consultation · contact interior designer gurgaon | |
| `/blog.html` + posts | Informational long-tail: modular kitchen cost gurgaon · interior design cost gurgaon 2bhk/3bhk/4bhk · turnkey interior design guide · pop ceiling vs false ceiling · etc. | Already assigned per-post in `data/blog-data.json` (`focusKeyword` field) — see that file for the full 62-post breakdown. Governing rule: informational intent, not commercial "hire someone" intent. |

## Known cannibalization this map resolves

From the GSC review, these near-identical queries were previously split across separate URLs. All now resolve to a single page each:

- "interior designer in dwarka" / "best interior designer in dwarka" / "interior designers in dwarka" → **`/interior-designers-dwarka-expressway/`** only.
- "interior designer gurgaon" / "interior designers in gurgaon" / "best interior designer gurgaon" / "top 10 interior designers in gurgaon" → **`/interior-designers-gurgaon/`** only.

## Maintenance rule

Before adding any new page (service, location, or blog post), check this file first. If the target keyword is already assigned, either fold the new content into the existing page or pick a genuinely distinct angle/keyword — don't ship a second page chasing the same query.
