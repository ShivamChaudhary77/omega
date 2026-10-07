#!/usr/bin/env python3
"""Generate host-specific redirect config from redirects.json (single source
of truth). Re-run after adding entries to redirects.json.

Usage: python3 scripts/build-redirects.py
"""
import json
from pathlib import Path

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
    "# Serve the site's own branded 404 page for any unmatched URL, instead of",
    "# the host's generic default error page. 404.html already carries its own",
    "# noindex meta tag, so this does not affect indexability — only what a",
    "# visitor (or a crawler checking the page itself) actually sees. The",
    "# blog-cms router has its own equivalent fallback for its specific",
    "# category/slug routes (see blog-cms/public/router.php) and is unaffected.",
    "ErrorDocument 404 /404.html",
    "",
]
for r in redirects:
    htaccess_lines.append(f"Redirect 301 {r['from']} {DOMAIN}{r['to']}")
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
