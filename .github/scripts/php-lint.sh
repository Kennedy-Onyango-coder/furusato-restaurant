#!/usr/bin/env bash
# Lint every PHP file in the repository. Any syntax error fails the build.
set -euo pipefail

fail=0
count=0
while IFS= read -r -d '' f; do
  count=$((count + 1))
  if ! php -l "$f" >/dev/null 2>&1; then
    echo "PHP SYNTAX ERROR in: $f"
    php -l "$f" || true
    fail=1
  fi
done < <(find . -type f -name '*.php' -not -path './.git/*' -print0)

echo "PHP files checked: $count"
if [ "$fail" -ne 0 ]; then
  echo "::error::PHP syntax validation failed - deployment aborted."
  exit 1
fi
echo "All PHP files passed syntax validation."
