#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

echo "== Pactopia360 · predeploy check =="

# 1) Marcadores de conflicto REALES (anclados)
echo "- Checking merge markers..."
if git grep -n -E '^(<<<<<<< |=======\s*0>>>>>>> )' -- app resources routes database > /tmp/p360_merge_markers.txt 2>/dev/null; then
  echo "ERROR: Found merge-conflict markers:"
  cat /tmp/p360_merge_markers.txt
  exit 1
fi
echo "OK: no merge markers"

# 2) Sintaxis PHP (controllers críticos)
echo "- Checking PHP syntax..."
php -l app/Http/Controllers/Admin/Billing/BillingStatementsController.php >/dev/null
php -l app/Http/Controllers/Admin/Billing/BillingStatementsHubController.php >/dev/null
echo "OK: PHP syntax"

# 3) Mojibake típico (opcional)
echo "- Checking mojibake..."
if git grep -n -E 'Â|Ã|â€”|â€“|â€œ|â€' -- resources/views > /tmp/p360_mojibake.txt 2>/dev/null; then
  echo "ERROR: Found mojibake sequences:"
  cat /tmp/p360_mojibake.txt
  exit 1
fi
echo "OK: no mojibake"

echo "== All good =="
