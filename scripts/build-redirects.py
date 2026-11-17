#!/usr/bin/env python3
"""Generate host-specific redirect config from redirects.json (single source
of truth). Re-run after adding entries to redirects.json.

Usage:
  python3 scripts/build-redirects.py            current static site (default)
  python3 scripts/build-redirects.py --dynamic  dynamic PHP site (Phase 6 go-live):
      replaces the Blog CMS routing block with the front-controller rules that
      send every URL without a real file to index.php, and blocks the internal
      app/, templates/, content/ and tests/ folders.
"""
import json
import sys
from pathlib import Path

DYNAMIC = "--dynamic" in sys.argv[1:]

SITE_ROOT = Path(__file__).resolve().parent.parent
DOMAIN = "https://theomegagroup.in"

data = json.loads((SITE_ROOT / "redirects.json").read_text(encoding="utf-8"))
redirects = data["redirects"]

# ---------- Netlify _redirects ----------
lines = [f"{r['from']}  {r['to']}  301" for r in redirects]
(SITE_ROOT / "_redirects").write_text("\n".join(lines) + "\n", encoding="utf-8")

# ---------- Apache .htaccess ----------
htaccess_lines = [
    "# Generated from redirects.json by scripts/build-redirects.py — do not hand-edit the",
    "# redirect block below; edit redirects.json and re-run the script instead.",
    "RewriteEngine On",
    "",
] + ([
    "# Unknown addresses are answered by index.php with the site's branded 404",
    "# page (content-managed, noindex).",
] if DYNAMIC else []) + [
    "# Serve the site's own branded 404 page for any unmatched URL, instead of",
    "# the host's generic default error page. 404.html already carries its own",
    "# noindex meta tag, so this does not affect indexability — only what a",
    "# visitor (or a crawler checking the page itself) actually sees. The",
    "# blog-cms router has its own equivalent fallback for its specific",
    "# category/slug routes (see blog-cms/public/router.php) and is unaffected.",
    "ErrorDocument 404 /404.html" if not DYNAMIC else "ErrorDocument 404 /index.php",
    "",
]
if DYNAMIC:
    htaccess_lines += [
        "# The dynamic site's front door comes first; index.html is only a fallback.",
        "DirectoryIndex index.php index.html",
        "",
    ]
if DYNAMIC:
    # Exact-match rewrite rules instead of mod_alias "Redirect": mod_alias
    # matches a path PREFIX (Redirect /project/ would also catch every
    # /project/<slug>/ page once the static folders are gone), and Apache and
    # LiteSpeed treat it differently. These behave the same everywhere.
    import re as _re
    htaccess_lines.append("# Historic redirects (exact address, with or without the final slash).")
    for r in redirects:
        pattern = _re.escape(r["from"].strip("/"))
        htaccess_lines.append(f"RewriteRule ^{pattern}/?$ {DOMAIN}{r['to']} [R=301,L]")
else:
    for r in redirects:
        htaccess_lines.append(f"Redirect 301 {r['from']} {DOMAIN}{r['to']}")
if DYNAMIC:
    htaccess_lines += [
        "",
        "# ---- Dynamic site front controller (index.php -> app/front.php) ----",
        "# Internal folders are never served (each also has its own deny .htaccess).",
        "RewriteRule ^(app|templates|content|tests)(/|$) - [F,L]",
        "",
        "# Real files (CSS, JS, images, PHP form endpoints, uploads) are served as-is.",
        "# Every other URL is a content page rendered by index.php, which also",
        "# handles dashboard redirects and the branded 404.",
        "RewriteCond %{REQUEST_FILENAME} !-f",
        "RewriteCond %{REQUEST_FILENAME} !-d",
        "RewriteRule ^ index.php [L,QSA]",
    ]
else:
    htaccess_lines += [
    "",
    "# Clean directory-style URLs (blog posts + location pages) are real",
    "# directories with their own index.html, so no rewrite rules are needed",
    "# for them — Apache serves <dir>/index.html for <dir>/ automatically.",
    "",
    "# ---- Blog CMS dynamic routing (blog-cms/public/router.php) ----",
    "# Added for the Blog CMS module (see BLOG-README.md / blog-cms/DEPLOYMENT.md).",
    "# This block ONLY ever fires when Apache found NO existing static file or",
    "# directory for the request — every one of the existing static blog posts,",
    "# and every other existing page, resolves exactly as before and never",
    "# reaches this rule at all. Only a URL with no matching static content",
    "# falls through to the CMS's MySQL-backed router, which serves it if a",
    "# published CMS post exists at that category/slug or a real 404 if not.",
    "RewriteCond %{REQUEST_FILENAME} !-f",
    "RewriteCond %{REQUEST_FILENAME} !-d",
    "RewriteRule ^([a-z0-9-]+)/([a-z0-9-]+)/?$ /blog-cms/public/router.php?category_slug=$1&post_slug=$2 [L,QSA]",
]
if DYNAMIC:
    static_comment = [
        "# Serve the site's own branded 404 page for any unmatched URL, instead of",
        "# the host's generic default error page. 404.html already carries its own",
        "# noindex meta tag, so this does not affect indexability — only what a",
        "# visitor (or a crawler checking the page itself) actually sees. The",
        "# blog-cms router has its own equivalent fallback for its specific",
        "# category/slug routes (see blog-cms/public/router.php) and is unaffected.",
    ]
    htaccess_lines = [line for line in htaccess_lines if line not in static_comment]
(SITE_ROOT / ".htaccess").write_text("\n".join(htaccess_lines) + "\n", encoding="utf-8")

# ---------- Vercel vercel.json ----------
vercel_config = {
    "redirects": [
        {"source": r["from"].rstrip("/"), "destination": r["to"], "permanent": True}
        for r in redirects
    ]
}
(SITE_ROOT / "vercel.json").write_text(json.dumps(vercel_config, indent=2) + "\n", encoding="utf-8")

print(f"Generated _redirects, .htaccess, vercel.json from {len(redirects)} redirect(s) in redirects.json")
