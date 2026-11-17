#!/usr/bin/env bash
# Undo scripts/go-live.sh: put the static pages back and switch the dynamic
# site off. The content folder (with any dashboard edits) is kept.
#
#   bash scripts/rollback-go-live.sh --backup ~/omega-golive-backup-XXXX.tar.gz           # dry run
#   bash scripts/rollback-go-live.sh --backup ~/omega-golive-backup-XXXX.tar.gz --apply
#
# Options: --public DIR (default ~/public_html)

set -euo pipefail

PUBLIC="$HOME/public_html"
BACKUP=""
APPLY=0
while [ $# -gt 0 ]; do
  case "$1" in
    --apply) APPLY=1 ;;
    --public) PUBLIC="${2%/}"; shift ;;
    --backup) BACKUP="$2"; shift ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
  esac
  shift
done

[ -n "$BACKUP" ] && [ -f "$BACKUP" ] || { echo "Give the backup file: --backup ~/omega-golive-backup-....tar.gz" >&2; exit 1; }
[ -d "$PUBLIC" ] || { echo "$PUBLIC not found" >&2; exit 1; }

FILES=$(tar -tzf "$BACKUP" | wc -l | tr -d ' ')
echo "Will restore $FILES files from $BACKUP into $PUBLIC and remove $PUBLIC/app/config.local.php."
if [ $APPLY = 0 ]; then
  echo "Dry run: nothing changed. Add --apply to roll back."
  exit 0
fi

rm -f "$PUBLIC/app/config.local.php"
tar -xzf "$BACKUP" -C "$PUBLIC"
echo "Rolled back: the static site is being served again."
echo "Note: the next cPanel deploy of the go-live commit brings back the go-live .htaccess; that's harmless"
echo "while config.local.php is absent (index.php then serves the static pages)."
