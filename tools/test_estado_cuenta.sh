#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${P360_BASE_URL:-https://pactopia360.com}"
LOGIN_GET="${BASE_URL}/cliente/login"
LOGIN_POST="${BASE_URL}/cliente/login"
TARGET_URL="${BASE_URL}/cliente/estado-de-cuenta"

EMAIL="${P360_EMAIL:-}"
PASS="${P360_PASS:-}"

# =========================================================
# Helpers
# =========================================================
say(){ echo "== $* =="; }
die(){ echo "ERROR: $*" >&2; exit 1; }

urldecode() {
  python3 - <<'PY' "$1"
import sys, urllib.parse
print(urllib.parse.unquote(sys.argv[1]))
PY
}

# =========================================================
# Validaciones duras (evita pruebas falsas)
# =========================================================
if [[ -z "${EMAIL}" || -z "${PASS}" ]]; then
  die "Debes setear P360_EMAIL y P360_PASS con credenciales reales. Ej: P360_EMAIL='a@b.com' P360_PASS='xxx' bash tools/test_estado_cuenta.sh"
fi

if [[ "${EMAIL}" == "TU_EMAIL_REAL" || "${PASS}" == "TU_PASS_REAL" ]]; then
  die "Sigues usando placeholders (TU_EMAIL_REAL / TU_PASS_REAL). Reemplázalos por credenciales reales."
fi

TMP_DIR="$(mktemp -d)"
COOKIEJAR="${TMP_DIR}/cookies.txt"
HDR_GET_LOGIN="${TMP_DIR}/hdr_get_login.txt"
HDR_POST_LOGIN="${TMP_DIR}/hdr_post_login.txt"
HDR_GET_TARGET="${TMP_DIR}/hdr_get_target.txt"
BODY_GET_TARGET="${TMP_DIR}/body_get_target.html"
BODY_POST_LOGIN="${TMP_DIR}/body_post_login.html"

cleanup() { rm -rf "${TMP_DIR}" >/dev/null 2>&1 || true; }
trap cleanup EXIT

say "Base URL: ${BASE_URL}"
say "Limpia log (si lo ejecutas en server manualmente): : > storage/logs/laravel.log"
echo

# =========================================================
# 1) GET login para obtener cookies iniciales
# =========================================================
say "1) GET /cliente/login (cookies iniciales)"
curl -sS -D "${HDR_GET_LOGIN}" -c "${COOKIEJAR}" -b "${COOKIEJAR}" \
  -A "P360-test-script/1.0" \
  "${LOGIN_GET}" >/dev/null

echo "GET login status:"
head -n 1 "${HDR_GET_LOGIN}" || true
echo

# =========================================================
# 2) Extrae XSRF-TOKEN y arma header X-XSRF-TOKEN
# =========================================================
say "2) Extrae XSRF-TOKEN"
RAW_XSRF="$(awk '$6=="XSRF-TOKEN"{print $7}' "${COOKIEJAR}" | tail -n 1 || true)"
if [[ -z "${RAW_XSRF}" ]]; then
  echo "Cookiejar:"
  cat "${COOKIEJAR}" || true
  die "No se encontró XSRF-TOKEN en cookiejar."
fi

XSRF_DECODED="$(urldecode "${RAW_XSRF}")"
echo "RAW_XSRF (cookie)    = ${RAW_XSRF:0:40}..."
echo "XSRF_DECODED (header)= ${XSRF_DECODED:0:40}..."
echo

# =========================================================
# 3) POST login
# =========================================================
say "3) POST /cliente/login"
echo "Antes del POST, pactopia360_session:"
awk '$6=="pactopia360_session"{print $7}' "${COOKIEJAR}" | tail -n 1 || true
echo

# Intentamos login, capturando headers y body
curl -sS -D "${HDR_POST_LOGIN}" -c "${COOKIEJAR}" -b "${COOKIEJAR}" \
  -A "P360-test-script/1.0" \
  -H "X-Requested-With: XMLHttpRequest" \
  -H "X-XSRF-TOKEN: ${XSRF_DECODED}" \
  -H "Referer: ${LOGIN_GET}" \
  -X POST \
  --data-urlencode "email=${EMAIL}" \
  --data-urlencode "password=${PASS}" \
  --data-urlencode "_token=${XSRF_DECODED}" \
  "${LOGIN_POST}" > "${BODY_POST_LOGIN}" || true

echo "POST login status:"
head -n 1 "${HDR_POST_LOGIN}" || true
echo

echo "POST login Location (si redirige):"
grep -i '^location:' "${HDR_POST_LOGIN}" || true
echo

echo "Después del POST, pactopia360_session:"
awk '$6=="pactopia360_session"{print $7}' "${COOKIEJAR}" | tail -n 1 || true
echo

# Señal rápida si login devolvió HTML de login otra vez
if grep -qi "cliente/login" "${BODY_POST_LOGIN}"; then
  echo "AVISO: El body del POST contiene 'cliente/login' (posible fallo de login o redirect a login)."
fi
echo

# =========================================================
# 4) GET estado de cuenta con sesión ya iniciada (NO seguir redirects)
# =========================================================
say "4) GET /cliente/estado-de-cuenta (sin -L, para ver si redirige)"
HTTP_CODE="$(curl -sS -o "${BODY_GET_TARGET}" -D "${HDR_GET_TARGET}" \
  -b "${COOKIEJAR}" -c "${COOKIEJAR}" \
  -A "P360-test-script/1.0" \
  -w "%{http_code}" \
  "${TARGET_URL}" || true)"

echo "HTTP_CODE=${HTTP_CODE}"
echo

echo "GET estado Location (si redirige):"
grep -i '^location:' "${HDR_GET_TARGET}" || true
echo

say "Primeras 15 líneas de respuesta"
sed -n '1,15p' "${BODY_GET_TARGET}" || true
echo

say "Headers GET estado (primeras 25 líneas)"
sed -n '1,25p' "${HDR_GET_TARGET}" || true
echo

say "Cookies usadas (final)"
cat "${COOKIEJAR}" || true
echo

# Resultado final
if [[ "${HTTP_CODE}" == "200" ]]; then
  say "OK: estado-de-cuenta devolvió 200 (autenticado)"
else
  say "FALLO: estado-de-cuenta NO devolvió 200 (revisar Location/POST login)"
fi
