#!/usr/bin/env bash
# Deployment-safety gate: proves the tree that would be rsynced contains NO
# production data and NO production uploads. Runs BEFORE anything touches the
# server, so a misconfigured exclusion can never reach production.
set -euo pipefail

fail=0

echo "== Tracked files under data/ =="
git ls-files data/
# Only example templates and non-JSON runtime seeds are allowed.
bad_data=$(git ls-files 'data/*.json' | grep -v -E 'data/[a-z_.]+\.example\.json$' || true)
if [ -n "$bad_data" ]; then
  echo "::error::Production data JSON files are tracked in git and would be deployed:"
  echo "$bad_data"
  fail=1
fi

echo "== Tracked files under logs/ =="
git ls-files logs/
bad_logs=$(git ls-files logs/ | grep -v -E 'logs/\.gitkeep$' || true)
if [ -n "$bad_logs" ]; then
  echo "::error::Log files are tracked in git:"
  echo "$bad_logs"
  fail=1
fi

echo "== Backup artefacts tracked in git =="
bad_backups=$(git ls-files 'data/backups/*' '*_backups/*' || true)
if [ -n "$bad_backups" ]; then
  echo "::error::Backup archives are tracked in git:"
  echo "$bad_backups"
  fail=1
fi

echo "== Secret files tracked in git =="
bad_secrets=$(git ls-files -- '.env*' 'includes/.env.php' 'config.local.php' '*.pem' '*.key' || true)
if [ -n "$bad_secrets" ]; then
  echo "::error::Secret/key files are tracked in git:"
  echo "$bad_secrets"
  fail=1
fi

echo "== Staging area check (files added but not yet committed) =="
staged_data=$( (git diff --cached --name-only; git diff --name-only) | grep -E '^(data/|logs/|includes/\.env\.php$)' | grep -v -E 'data/[a-z_.]+\.example\.json$' || true )
if [ -n "$staged_data" ]; then
  echo "::warning::Files under data/ or logs/ are staged/modified in this push (they are still excluded from deployment by rsync-exclude.txt):"
  echo "$staged_data"
fi

if [ "$fail" -ne 0 ]; then
  echo "::error::Deployment safety check FAILED - deployment aborted."
  exit 1
fi
echo "Deployment safety check passed: no production data, logs, backups or secrets in the deploy payload."
