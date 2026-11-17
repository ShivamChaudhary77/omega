#!/usr/bin/env bash
# Daily backup of everything the dashboard manages: the content folder and
# uploaded images. Keeps the 14 newest backups. Add as a daily cron job in
# cPanel → Cron Jobs (see GO-LIVE.md):
#
#   bash /home/theomega/<repository>/scripts/backup-content.sh
#
# Options: --content DIR (default ~/omega-content), --public DIR (default
# ~/public_html), --dest DIR (default ~/omega-content-backups), --keep N (default 14)

set -euo pipefail

CONTENT="$HOME/omega-content"
PUBLIC="$HOME/public_html"
DEST="$HOME/omega-content-backups"
KEEP=14
while [ $# -gt 0 ]; do
  case "$1" in
    --content) CONTENT="${2%/}"; shift ;;
    --public) PUBLIC="${2%/}"; shift ;;
    --dest) DEST="${2%/}"; shift ;;
    --keep) KEEP="$2"; shift ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
  esac
  shift
done

[ -d "$CONTENT" ] || { echo "$CONTENT not found" >&2; exit 1; }
mkdir -p "$DEST"
chmod 700 "$DEST"
FILE="$DEST/omega-content-$(date +%Y%m%d-%H%M%S).tar.gz"
ARGS=(-C "$(dirname "$CONTENT")" --exclude="$(basename "$CONTENT")/_cache" "$(basename "$CONTENT")")
[ -d "$PUBLIC/uploads" ] && ARGS+=(-C "$PUBLIC" uploads)
tar -czf "$FILE" "${ARGS[@]}"
chmod 600 "$FILE"
ls -1t "$DEST"/omega-content-*.tar.gz | tail -n +$((KEEP + 1)) | while IFS= read -r old; do rm -f "$old"; done
echo "Backup written: $FILE"
