---
name: blog-seo-optimizer
description: Optimizes Omega Project blog post content for SEO (search engine optimization), AEO (answer engine optimization), and GEO (generative engine optimization). Use when the user shares a blog post draft, URL, or .docx and asks to optimize, rank, rewrite, improve for search, or make it answer-engine/AI-engine friendly.
---

# Omega Project — Blog SEO/AEO/GEO Optimizer

## When to use
Trigger when the user:
- Shares blog post content (pasted text, .docx, .md, or URL) and asks to "optimize," "improve SEO," "make it rank," "clean up," or "make it AI/answer-engine friendly."
- Asks to fix keyword usage, meta tags, headings, or FAQ sections on an Omega Project blog post.
- Asks to generate FAQ schema or structured data for a post.

## Inputs to gather first
Before optimizing, check what's available; ask only if genuinely missing:
1. The blog post content itself (paste, file, or URL).
2. Target primary keyword / topic (infer from content if not given).
3. Any secondary/related keywords the user wants covered.
4. Target audience or search intent (informational, commercial, navigational) — infer from content tone if not stated.

## Optimization checklist

### 1. SEO (traditional search)
- **Title tag**: ≤60 characters, primary keyword near the front, compelling (not clickbait).
- **Meta description**: ≤155 characters, includes primary keyword, has a clear value proposition or CTA.
- **URL slug**: short, lowercase, hyphenated, keyword-relevant.
- **Heading hierarchy**: single H1 matching search intent, logical H2/H3 nesting, keywords in headings naturally (no stuffing).
- **Keyword placement**: primary keyword in first 100 words, in at least one H2, and naturally throughout (avoid density above ~1-2%).
- **Internal links**: suggest 2-4 relevant internal links to other Omega Project content (ask the user for URLs if not supplied, or note as a placeholder).
- **External links**: 1-2 authoritative outbound links where relevant (helps trust signals).
- **Image alt text**: descriptive, includes keyword where natural, no stuffing.
- **Readability**: short paragraphs (2-4 sentences), scannable formatting, bullet/numbered lists where useful.

### 2. AEO (answer engine optimization — Google featured snippets, voice search, "People Also Ask")
- **Direct-answer opening**: for question-style queries, include a concise 1-3 sentence answer within the first paragraph or right after the relevant heading, before elaborating.
- **FAQ section**: add or improve an FAQ block with 3-6 real questions users would ask, each answered in 1-3 sentences (snippet-length).
- **Structured formatting**: use ordered/unordered lists and tables for step-by-step or comparison content — these get pulled into snippets more often.
- **Question-based subheadings**: phrase some H2/H3s as questions matching real search queries.

### 3. GEO (generative engine optimization — being cited/summarized by AI systems like ChatGPT, Perplexity, Google AI Overviews)
- **Self-contained claims**: write statements that are factually complete and quotable in isolation (avoid "as mentioned above" or pronoun-dependent sentences).
- **Clear attribution-friendly structure**: state facts, stats, and definitions plainly and early in a paragraph or section, not buried in narrative.
- **Named entities**: use specific, consistent names (brand, product, place) rather than vague references — helps AI systems attribute and cite correctly.
- **Data and specifics**: prefer concrete numbers, dates, and named sources over generalizations — AI summarizers favor citable specifics.
- **Freshness signals**: include a visible last-updated date or note if the content is time-sensitive.

## Output format
When optimizing a post, deliver:
1. **Summary of changes** (short bullet list — what was fixed and why).
2. **Suggested meta title + meta description.**
3. **Revised content** with the above fixes applied (in the same format the user provided — .docx stays .docx, markdown stays markdown, etc. — see the docx skill if the source is a Word file).
4. **FAQ schema (JSON-LD)** if an FAQ section was added or exists, ready to paste into the CMS.
5. **Open questions** — anything you need from the user (missing internal link targets, unclear target keyword, etc.).

## Notes
- Never fabricate statistics, sources, or internal link URLs — flag them as placeholders instead.
- Keep Omega Project's existing tone and factual claims intact; don't rewrite voice unless asked.
- If the source is a .docx or .pptx file, use the docx/pptx skill for reading/writing — don't hand-parse binary files.