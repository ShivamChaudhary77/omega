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
]
for r in redirects:
    htaccess_lines.append(f"Redirect 301 {r['from']} {DOMAIN}{r['to']}")
htaccess_lines += [
    "",
    "# Clean directory-style URLs (blog posts + location pages) are real",
    "# directories with their own index.html, so no rewrite rules are needed",
    "# for them — Apache serves <dir>/index.html for <dir>/ automatically.",
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
