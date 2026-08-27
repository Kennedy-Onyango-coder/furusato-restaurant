#!/usr/bin/env bash
# Pre-deployment backup of production data, uploads and logs.
# Runs ON the Hostinger server (piped over SSH) BEFORE any file is changed.
# Backups live OUTSIDE public_html (~/backups/furusato/) so a code deployment
# can never delete them, and are never synced anywhere.
#
# Usage: bash -s < deploy-path  (deploy path passed as $1)
set -euo pipefail

DEPLOY_PATH="${1:?Usage: backup-production.sh <deploy-path>}"
STAMP="$(date +%Y-%m-%d-%H%M%S)"
BACKUP_ROOT="$HOME/backups/furusato"
TARGET="$BACKUP_ROOT/furusato-production-$STAMP.tar.gz"

mkdir -p "$BACKUP_ROOT"
cd "$DEPLOY_PATH"

# Fail before touching anything if critical production paths are missing —
# that would mean we are pointed at the wrong directory.
for required in data .htaccess; do
  if [ ! -e "$required" ]; then
    echo "ABORT: '$required' not found in $DEPLOY_PATH - refusing to run." >&2
    exit 1
  fi
done

tar -czf "$TARGET" \
  data logs \
  assets/images/menu assets/images/hero assets/images/gallery \
  2>/dev/null || tar -czf "$TARGET" data logs 2>/dev/null

echo "Backup created: $TARGET ($(du -h "$TARGET" | cut -f1))"

# Keep the 14 most recent backups, prune older ones.
ls -1t "$BACKUP_ROOT"/furusato-production-*.tar.gz 2>/dev/null | tail -n +15 | xargs -r rm -f
echo "Backups retained in $BACKUP_ROOT:"
ls -1t "$BACKUP_ROOT"/furusato-production-*.tar.gz | head -5

chmod 600 "$TARGET"
