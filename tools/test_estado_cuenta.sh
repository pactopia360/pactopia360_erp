#!/usr/bin/env bash
set -euo pipefail

BASE="https://pactopia360.com"
JAR="/tmp/p360_cookiejar.txt"

# ====== AJUSTA ESTO ======
EMAIL="${P360_EMAIL:-}"
PASS="${P360_PASS:-}"
# =========================

if [[ -z "$EMAIL" || -z "$PASS" ]]; then
  echo "ERROR: Define P360_EMAIL y P360_PASS antes de correr."
  echo "Ejemplo:"
  echo "  P360_EMAIL='cliente@dominio.com' P360_PASS='*****' bash tools/test_estado_cuenta.sh"
  exit 1
fi

cd "$(dirname "$0")/.."  # root del proyecto

echo "== Limpia log =="
: > storage/logs/laravel.log

rm -f "$JAR"

echo "== 1) GET login para obtener cookies iniciales (XSRF/session) =="
curl -s -k -c "$JAR" -b "$JAR" \
  "${BASE}/cliente/login" >/dev/null

echo "== 2) Extrae XSRF-TOKEN de cookiejar y conviértelo para header X-XSRF-TOKEN =="
XSRF_RAW="$(awk '$6=="XSRF-TOKEN"{print $7}' "$JAR" | tail -n 1)"
if [[ -z "$XSRF_RAW" ]]; then
  echo "ERROR: No pude obtener XSRF-TOKEN del cookiejar."
  echo "Cookiejar:"
  cat "$JAR"
  exit 1
fi

# URL-decode básico con php (en Git Bash / WSL normalmente existe php)
XSRF="$(php -r 'echo urldecode($argv[1]);' "$XSRF_RAW")"

echo "== 3) POST login =="
curl -s -k -L -D /tmp/p360_login_headers.txt \
  -c "$JAR" -b "$JAR" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "X-XSRF-TOKEN: ${XSRF}" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data "_token=${XSRF_RAW}&email=$(php -r 'echo urlencode($argv[1]);' "$EMAIL")&password=$(php -r 'echo urlencode($argv[1]);' "$PASS")" \
  "${BASE}/cliente/login" >/dev/null

echo "== 4) GET estado de cuenta con sesión ya iniciada =="
HTTP_CODE="$(curl -s -k -o /tmp/p360_estado.html -w "%{http_code}" \
  -c "$JAR" -b "$JAR" \
  "${BASE}/cliente/estado-de-cuenta")"

echo "HTTP_CODE=${HTTP_CODE}"
echo "== Primeras 15 líneas de respuesta =="
sed -n '1,15p' /tmp/p360_estado.html || true

echo "== Último log (si hubo ejecución real) =="
tail -n 250 storage/logs/laravel.log || true

echo "== Cookies usadas =="
tail -n 20 "$JAR" || true
