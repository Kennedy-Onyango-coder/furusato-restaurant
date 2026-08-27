#!/usr/bin/env bash
# Post-deployment verification, run ON the server via SSH.
# Confirms the code landed AND that production data/uploads survived untouched.
# Never prints secret values.
set -euo pipefail

DEPLOY_PATH="${1:?Usage: verify-production.sh <deploy-path>}"
BASE_URL="https://furusatorestaurant.com"

cd "$DEPLOY_PATH"
fail=0

echo "== Deployed code present =="
for f in index.php menu.php our-story.php contact.php sitemap.php sw.js includes/config.php includes/mailer.php api/menu.php api/settings.php api/auth.php api/reservations.php admin/login.php admin/dashboard.php .htaccess; do
  if [ -e "$f" ]; then echo "  ok  $f"; else echo "  MISSING $f"; fail=1; fi
done

echo "== Production data preserved =="
for f in data/settings.json data/menu.json data/hero.json data/specials.json data/admin.json; do
  if [ -e "$f" ]; then echo "  ok  $f"; else echo "  MISSING $f (production data must never be deleted by a deploy)"; fail=1; fi
done

echo "== Production media preserved =="
for d in assets/images/menu assets/images/hero assets/images/gallery; do
  if [ -d "$d" ]; then
    echo "  ok  $d ($(find "$d" -type f | wc -l) files)"
  else
    echo "  MISSING directory $d"; fail=1
  fi
done

echo "== Server-only secret file untouched =="
if [ -e includes/.env.php ]; then echo "  ok  includes/.env.php present on server (not deployed, not deleted)"; else echo "  note: includes/.env.php not created yet - see DEPLOYMENT.md"; fi

echo "== Writable runtime directories (never 777) =="
for d in data data/backups logs assets/images/menu assets/images/hero assets/images/gallery; do
  if [ -d "$d" ]; then
    perms=$(stat -c '%a' "$d")
    echo "  $d -> $perms"
    if [ "$perms" = "777" ]; then echo "  ERROR: $d is world-writable"; fail=1; fi
  fi
done

echo "== Live site checks =="
check() {
  code=$(curl -s -o /dev/null -w '%{http_code}' "$1")
  echo "  $code  $1"
  case "$code" in 2*|3*) ;; *) fail=1 ;; esac
}
check "$BASE_URL/"
check "$BASE_URL/menu.php"
check "$BASE_URL/our-story.php"
check "$BASE_URL/contact.php"
check "$BASE_URL/sitemap.php"

echo "== Admin login page reachable (no credential check performed) =="
check "$BASE_URL/admin/login.php"

echo "== CSS/JS assets served (styling intact) =="
check "$BASE_URL/assets/css/style.css"

if [ "$fail" -ne 0 ]; then
  echo "::error::Post-deployment verification FAILED. Backup was taken before deploy; see rollback section in DEPLOYMENT.md."
  exit 1
fi
echo "Post-deployment verification PASSED."
