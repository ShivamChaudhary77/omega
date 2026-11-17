#!/usr/bin/env bash
# Switch theomegagroup.in from the static site to the dynamic site.
# Run on the cPanel server (cPanel → Terminal), from the Git repository
# folder that cPanel Git Version Control uses. See GO-LIVE.md first.
#
#   bash scripts/go-live.sh                 # dry run: checks only, changes nothing
#   bash scripts/go-live.sh --apply         # do it
#
# Options:
#   --public  DIR   website folder           (default: ~/public_html)
#   --content DIR   content folder to create (default: ~/omega-content), must be OUTSIDE --public
#   --url     URL   site address to verify   (default: https://theomegagroup.in)
#   --no-verify     skip the final web checks (e.g. when testing without the domain)
#   --setup-link    don't ask for the dashboard password; write a one-time
#                   private setup link into the output instead. Used
#                   automatically when there is no terminal, e.g. when run
#                   from cPanel → Cron Jobs (see GO-LIVE.md, "No Terminal?")
#   PHP=/path/to/php to force a PHP binary; otherwise the command-line PHP is
#   found automatically (cron's plain "php" is often the web-only php-cgi)
#
# What --apply does, in order (stops at the first problem):
#   1. checks PHP, extensions and that the new code is deployed
#   2. backs up every static page it will remove (+ .htaccess, robots.txt)
#   3. creates the content folder from the repository's content/
#   4. writes public/app/config.local.php pointing at it
#   5. asks for the dashboard password (the site switches on here)
#   6. removes the static pages the dynamic site now serves
#      (afterwards deploy the "remove static pages" commit; see GO-LIVE.md)
#   7. checks the live site answers dynamically
# Undo with scripts/rollback-go-live.sh (printed at the end).

set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PUBLIC="$HOME/public_html"
CONTENT="$HOME/omega-content"
URL="https://theomegagroup.in"
APPLY=0
VERIFY=1
SETUP_LINK=0
PHP_WANTED="${PHP:-}"

while [ $# -gt 0 ]; do
  case "$1" in
    --apply) APPLY=1 ;;
    --public) PUBLIC="${2%/}"; shift ;;
    --content) CONTENT="${2%/}"; shift ;;
    --url) URL="${2%/}"; shift ;;
    --no-verify) VERIFY=0 ;;
    --setup-link) SETUP_LINK=1 ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
  esac
  shift
done

if [ $SETUP_LINK = 0 ] && [ -z "${OMEGA_ADMIN_PASSWORD:-}" ] && ! [ -t 0 ]; then
  SETUP_LINK=1   # no terminal to type a password into (e.g. cPanel Cron Jobs)
fi

if [ -t 1 ]; then
  say() { printf '\n\033[1m%s\033[0m\n' "$*"; }
else
  say() { printf '\n== %s\n' "$*"; }   # plain text for log files
fi
ok()   { printf '  ✓ %s\n' "$*"; }
fail() { printf '  ✗ %s\n' "$*" >&2; exit 1; }

say "Omega go-live $( [ $APPLY = 1 ] && echo '(APPLY)' || echo '(dry run: nothing will change)')"
echo "  repository: $REPO"
echo "  website:    $PUBLIC"
echo "  content:    $CONTENT"

# ---------------------------------------------------------------- 1. checks
say "1. Checks"
# Find a command-line PHP 8.0+ with the needed extensions. Cron jobs often
# get the web-only php-cgi as plain "php", so try the usual cPanel and
# CloudLinux locations (newest first) and keep the first that qualifies.
php_ok() { # binary -> prints version if it is a usable CLI PHP 8.0+
  [ -x "$1" ] || command -v "$1" >/dev/null 2>&1 || return 1
  "$1" -r 'if (PHP_SAPI !== "cli" || version_compare(PHP_VERSION, "8.0.0", "<")) exit(1);
           foreach (["dom","gd","fileinfo","mbstring","json","session"] as $e) if (!extension_loaded($e)) exit(2);
           echo PHP_VERSION;' 2>/dev/null
}
CANDIDATES=()
[ -n "$PHP_WANTED" ] && CANDIDATES+=("$PHP_WANTED")
CANDIDATES+=(/usr/local/bin/php)
for c in $(ls -d /opt/cpanel/ea-php8*/root/usr/bin/php /opt/alt/php8*/usr/bin/php 2>/dev/null | sort -r); do CANDIDATES+=("$c"); done
CANDIDATES+=(/usr/bin/php php)
PHP=""
for c in "${CANDIDATES[@]}"; do
  if v="$(php_ok "$c")" && [[ "$v" =~ ^[0-9]+\.[0-9]+ ]]; then PHP="$c"; break; fi
done
if [ -z "$PHP" ]; then
  echo "  PHP binaries tried:" >&2
  for c in "${CANDIDATES[@]}"; do
    if [ -x "$c" ] || command -v "$c" >/dev/null 2>&1; then
      info="$("$c" -r 'echo PHP_SAPI, " ", PHP_VERSION; foreach (["dom","gd","fileinfo","mbstring"] as $e) if (!extension_loaded($e)) echo " missing:$e";' 2>/dev/null | head -1)"
      echo "    $c → ${info:-not a command-line PHP}" >&2
    fi
  done
  fail "no command-line PHP 8.0+ with the extensions dom, gd, fileinfo, mbstring found (cPanel → Select PHP Version → Extensions)"
fi
ok "using $PHP"
ok "PHP $("$PHP" -r 'echo PHP_VERSION;')"
for ext in dom gd fileinfo mbstring json session; do
  "$PHP" -r "exit(extension_loaded('$ext') ? 0 : 1);" || fail "PHP extension '$ext' is missing (cPanel → Select PHP Version → Extensions)"
done
ok "PHP extensions present"
[ -d "$PUBLIC" ] || fail "website folder $PUBLIC not found"
[ -f "$PUBLIC/app/front.php" ] && [ -f "$PUBLIC/index.php" ] && [ -d "$PUBLIC/admin" ] || fail "new code not deployed to $PUBLIC yet (deploy the go-live commit in cPanel first)"
grep -q "DirectoryIndex index.php" "$PUBLIC/.htaccess" || fail "$PUBLIC/.htaccess is not the go-live version (deploy the go-live commit first)"
ok "go-live code is deployed"
[ -f "$REPO/content/site.json" ] && [ -d "$REPO/content/pages" ] || fail "$REPO/content is missing: run this from the Git repository folder"
case "$CONTENT/" in "$PUBLIC"/*) fail "the content folder must be OUTSIDE $PUBLIC";; esac
[ ! -e "$CONTENT" ] || fail "$CONTENT already exists. Already switched on? (Use the dashboard, or move it aside to start again.)"
if [ -e "$PUBLIC/.git" ]; then
  ok "deploy style: repository checked out in $PUBLIC (git pull)"
else
  ok "deploy style: files copied into $PUBLIC"
fi
[ ! -e "$PUBLIC/app/config.local.php" ] || fail "$PUBLIC/app/config.local.php already exists"
PAGES=$(ls "$REPO/content/pages"/*.json | wc -l | tr -d ' ')
ok "$PAGES content pages ready in the repository"

LIST="$(mktemp)"
OMEGA_CONTENT_DIR="$REPO/content" "$PHP" "$REPO/app/cli.php" static-files "$PUBLIC" > "$LIST"
COUNT=$(wc -l < "$LIST" | tr -d ' ')
ok "$COUNT static files will be replaced by the dynamic site"

if [ $APPLY = 0 ]; then
  say "Dry run finished: everything is ready. Run again with --apply to switch over."
  rm -f "$LIST"
  exit 0
fi

# ---------------------------------------------------------------- 2. backup
say "2. Backup"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$HOME/omega-golive-backup-$STAMP.tar.gz"
EXTRA=()
for f in .htaccess robots.txt; do [ -f "$PUBLIC/$f" ] && EXTRA+=("$f"); done
tar -czf "$BACKUP" -C "$PUBLIC" -T "$LIST" ${EXTRA[@]+"${EXTRA[@]}"}
ok "static pages backed up to $BACKUP"

# ---------------------------------------------------------------- 3. content
say "3. Content folder"
mkdir -p "$CONTENT"
cp "$REPO/content/site.json" "$CONTENT/"
cp -R "$REPO/content/pages" "$CONTENT/pages"
chmod 700 "$CONTENT" "$CONTENT/pages"
chmod 600 "$CONTENT/site.json" "$CONTENT"/pages/*.json
ok "created $CONTENT with $PAGES pages"

# ---------------------------------------------------------------- 4. config
say "4. Configuration"
umask 027
TOKEN_LINE=""
if [ $SETUP_LINK = 1 ]; then
  TOKEN="$("$PHP" -r 'echo bin2hex(random_bytes(24));')"
  TOKEN_LINE="    'admin_setup_token' => '$TOKEN', // one-time password setup link; useless once a password is set"
fi
cat > "$PUBLIC/app/config.local.php" <<PHPCONF
<?php
// Server-only settings (not in git, not overwritten by deploys). Written by scripts/go-live.sh on $STAMP.
return [
    'enabled' => true,
    'content_dir' => '$CONTENT',
$TOKEN_LINE
];
PHPCONF
chmod 640 "$PUBLIC/app/config.local.php"
ok "wrote $PUBLIC/app/config.local.php"

# ---------------------------------------------------------------- 5. password
say "5. Dashboard password"
if [ $SETUP_LINK = 1 ]; then
  ok "no password asked (no terminal). Set it with this private one-time link:"
  echo
  echo "      $URL/admin/setup.php?token=$TOKEN"
  echo
  echo "    Open it in your browser, choose the password, and keep this file private."
  echo "    Until a password is set, nobody can log in; after that the link stops working."
elif [ -n "${OMEGA_ADMIN_PASSWORD:-}" ]; then
  printf '%s\n%s\n' "$OMEGA_ADMIN_PASSWORD" "$OMEGA_ADMIN_PASSWORD" | "$PHP" "$PUBLIC/app/cli.php" set-password >/dev/null
  ok "password set from OMEGA_ADMIN_PASSWORD"
else
  "$PHP" "$PUBLIC/app/cli.php" set-password
fi
"$PHP" "$PUBLIC/app/cli.php" rebuild
"$PHP" "$PUBLIC/app/cli.php" check || fail "content check failed (nothing removed yet; undo with the rollback script)"

# ---------------------------------------------------------------- 6. switch
say "6. Removing the static pages (the dynamic site serves them now)"
while IFS= read -r rel; do
  rm -f "$PUBLIC/$rel"
done < "$LIST"
# Remove folders left empty (deepest first), only where pages were removed.
sed -n 's#/[^/]*$##p' "$LIST" | sort -u | awk '{ print length, $0 }' | sort -rn | cut -d' ' -f2- | while IFS= read -r dir; do
  while [ -n "$dir" ] && [ "$dir" != "." ] && rmdir "$PUBLIC/$dir" 2>/dev/null; do dir="$(dirname "$dir")"; done
done
ok "removed $COUNT static files"
rm -f "$LIST"

# ---------------------------------------------------------------- 7. verify
say "7. Checking the website"
if [ $VERIFY = 1 ]; then
  check_url() { # path, expected status, text that must appear
    local body status
    body="$(curl -sS -o - -w '\n%{http_code}' "$URL$1" 2>/dev/null || true)"
    status="${body##*$'\n'}"
    if [ "$status" != "$2" ] || ! grep -q -- "$3" <<<"$body"; then
      echo "  ✗ $1 → HTTP $status (expected $2 containing '$3')" >&2
      return 1
    fi
    ok "$1 → $status"
  }
  FAILED=0
  check_url "/" 200 "From The" || FAILED=1
  check_url "/blog.html" 200 "blog-grid" || FAILED=1
  check_url "/kitchen-design/modular-kitchen-cost-gurgaon-2026/" 200 "post-body" || FAILED=1
  check_url "/sitemap.xml" 200 "<urlset" || FAILED=1
  check_url "/api/posts.json" 200 "urlPath" || FAILED=1
  check_url "/admin/login.php" 200 "Website dashboard" || FAILED=1
  check_url "/this-page-does-not-exist/" 404 "" || FAILED=1
  if [ $FAILED = 1 ]; then
    say "Some checks failed. If the site is broken, roll back now:"
  else
    say "Done: the dynamic site is live."
  fi
else
  say "Done (web checks skipped)."
fi
cat <<EOF

  Dashboard:  $URL/admin/
  Undo:       bash $REPO/scripts/rollback-go-live.sh --public $PUBLIC --backup $BACKUP --apply
  Backups:    add the daily cron job from GO-LIVE.md (scripts/backup-content.sh)
EOF
