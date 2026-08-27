#!/usr/bin/env bash
# Security scan: blocks the deployment if known leaked credentials or obvious
# secret patterns appear in TRACKED files (git grep ignores untracked/ignored
# working files such as production data/*.json, which legitimately hold
# server-side values outside the repository).
set -euo pipefail

fail=0

# 1. The compromised CallMeBot WhatsApp key (MUST never return to the repo).
#    Written as a character-class regex so this script never matches itself.
if git grep -InE '(^|[^0-9])[3][2][1][9][5][1][4]([^0-9]|$)' -- . ; then
  echo "::error::The compromised WhatsApp API key was found in tracked files. Rotate it and remove it."
  fail=1
fi

# 2. Obvious secret-bearing files that must never be committed.
for f in .env .env.local .env.production includes/.env.php config.local.php; do
  if [ -f "$f" ]; then
    echo "::error::Secret file '$f' is present in the working tree and must be gitignored."
    fail=1
  fi
done

# 3. Generic credential-looking assignments in tracked PHP (heuristic - review
#    matches manually; variable names alone are not flagged).
if git grep -InE -- "(api_key|apikey|password|passwd|secret|token)['\"]?\s*(=>|=)\s*['\"][A-Za-z0-9+/_-]{12,}['\"]" -- '*.php' ; then
  echo "::error::Possible hardcoded credential(s) found. Review the matches above."
  fail=1
fi

# 4. Private keys anywhere in tracked files.
if git grep -InE 'BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY' -- . ; then
  echo "::error::A private key was committed."
  fail=1
fi

if [ "$fail" -ne 0 ]; then
  echo "::error::Security scan failed - deployment aborted."
  exit 1
fi
echo "Security scan passed: no leaked WhatsApp key, secret files, hardcoded credentials or private keys found."
