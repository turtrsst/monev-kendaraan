#!/usr/bin/env bash
# ==============================================================
# Gate Phase 2 — uji HTTP end-to-end (server PHP bawaan).
# Prasyarat: MariaDB aktif + migrate + seed (dijalankan runner).
# Usage: tests/http_phase2.sh (base URL dari env BASE_URL)
# ==============================================================
set -uo pipefail

BASE="${BASE_URL:-http://127.0.0.1:8085}"
ADMIN_JAR="$(mktemp)"
OPERATOR_JAR="$(mktemp)"
DRIVER_JAR="$(mktemp)"
GUEST_JAR="$(mktemp)"

PASS=0
FAIL=0
RESULTS="tests/RESULTS-http-phase2.txt"
: > "$RESULTS"

say() { echo "$1"; }
ok()  { PASS=$((PASS+1)); echo "[PASS] $1" | tee -a "$RESULTS"; }
bad() { FAIL=$((FAIL+1)); echo "[FAIL] $1" | tee -a "$RESULTS"; }
chk() {
  if [ "$2" = "1" ]; then ok "$1"; else bad "$1"; fi
}

extract_csrf() {
  grep -o 'name="_csrf" value="[a-f0-9]\{64\}"' | head -1 | sed 's/.*value="//;s/"//'
}

echo "=== PHASE 2 HTTP & SECURITY TESTS ($BASE) ==="

# 1. Unauthenticated checks (guest access to protected routes)
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/kendaraan")
chk "Guest GET /kendaraan returns 302/redirect" "$([ "$code" = "302" ] && echo 1 || echo 0)"

code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/driver")
chk "Guest GET /driver returns 302/redirect" "$([ "$code" = "302" ] && echo 1 || echo 0)"

code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/ambulans")
chk "Guest GET /ambulans returns 302/redirect" "$([ "$code" = "302" ] && echo 1 || echo 0)"

code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/penugasan")
chk "Guest GET /penugasan returns 302/redirect" "$([ "$code" = "302" ] && echo 1 || echo 0)"

code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/vehicles")
chk "Guest GET /api/vehicles returns 401 unauthenticated" "$([ "$code" = "401" ] && echo 1 || echo 0)"

code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/drivers")
chk "Guest GET /api/drivers returns 401 unauthenticated" "$([ "$code" = "401" ] && echo 1 || echo 0)"

code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/ambulances")
chk "Guest GET /api/ambulances returns 401 unauthenticated" "$([ "$code" = "401" ] && echo 1 || echo 0)"

code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/assignments")
chk "Guest GET /api/assignments returns 401 unauthenticated" "$([ "$code" = "401" ] && echo 1 || echo 0)"

# 2. CSRF enforcement check on state-changing API
code=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H "Content-Type: application/json" -d '{"vehicle_code":"TEST"}' "$BASE/api/vehicles")
chk "POST /api/vehicles without CSRF/auth rejected" "$([ "$code" = "401" ] || [ "$code" = "419" ] && echo 1 || echo 0)"

# 3. No Phase 3 routes leakage check (404 expected)
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/trips")
chk "Phase 3 route /api/trips does NOT exist (404 expected)" "$([ "$code" = "404" ] && echo 1 || echo 0)"

code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/trips")
chk "Phase 3 route /trips does NOT exist (404 expected)" "$([ "$code" = "404" ] && echo 1 || echo 0)"

rm -f "$ADMIN_JAR" "$OPERATOR_JAR" "$DRIVER_JAR" "$GUEST_JAR"

echo ""
echo "----------------------------------------"
echo "RINGKASAN HTTP TEST PHASE 2:"
echo "PASS: $PASS"
echo "FAIL: $FAIL"
echo "----------------------------------------"

[ "$FAIL" -eq 0 ]
