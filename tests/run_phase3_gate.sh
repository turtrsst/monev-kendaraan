#!/usr/bin/env bash
# Phase 3 verification. Runtime tests are never reported as PASS unless executed.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PASS=0
FAIL=0
STATIC_OUT="tests/RESULTS-phase3-gate.txt"
: > "$STATIC_OUT"
note(){ printf '%s\n' "$1" | tee -a "$STATIC_OUT"; }
ok(){ PASS=$((PASS+1)); note "[PASS] $1"; }
bad(){ FAIL=$((FAIL+1)); note "[FAIL] $1"; }

note "=== PHASE 3 TRIP EXECUTION GATE — $(date -u +%FT%TZ) ==="
note "--- STATIC CHECKS ---"
for f in database/migrations/007_phase3_trips.sql app/models/Trip.php app/services/TripService.php \
         app/services/TripStateMachine.php app/validators/TripGpsValidator.php \
         api/trips/TripApiController.php modules/trips/controllers/TripController.php \
         modules/trips/views/index.php modules/trips/views/show.php tests/unit_phase3.php tests/integration_phase3.php tests/http_phase3.sh; do
  if [ -f "$f" ]; then ok "required file exists: $f"; else bad "missing required file: $f"; fi
done
if bash -n tests/http_phase3.sh && bash -n tests/run_phase3_gate.sh; then ok "bash syntax for Phase 3 HTTP and gate scripts"; else bad "bash syntax check for test scripts"; fi
if bash -n tests/http_phase2.sh; then ok "Phase 2 regression HTTP script syntax"; else bad "Phase 2 regression HTTP script syntax"; fi

for route in "/api/trips/{id}/start" "/api/trips/{id}/arrival" "/api/trips/{id}/returning" "/api/trips/{id}/complete" "/api/trips/{id}/submit"; do
  if grep -F "$route" app/config/routes.php | grep -q "'csrf'"; then ok "state-changing API has CSRF middleware: $route"; else bad "missing CSRF route protection: $route"; fi
done
if grep -q "SELECT \* FROM trip WHERE id = ? FOR UPDATE" app/services/TripService.php; then ok "trip transitions lock the trip row"; else bad "trip transition row lock missing"; fi
if grep -q "TripStateMachine::allows" app/services/TripService.php; then ok "transition validation is centralized in TripService"; else bad "TripService state machine call missing"; fi
if grep -q "UNIQUE KEY uq_trip_assignment" database/migrations/007_phase3_trips.sql && grep -q "UNIQUE KEY uq_trip_event_uuid" database/migrations/007_phase3_trips.sql; then ok "database enforces one trip per assignment and unique event UUID"; else bad "trip/event uniqueness constraints missing"; fi
if grep -q "navigator.geolocation.getCurrentPosition" modules/trips/views/show.php && ! grep -q "watchPosition" modules/trips/views/show.php; then ok "GPS capture is one-shot event-based; no continuous tracking"; else bad "GPS capture scope check failed"; fi
if [ ! -f app/services/OcrService.php ] && [ ! -f app/services/ExpenseService.php ] && [ ! -f app/services/EquipmentChecklistService.php ]; then ok "no Phase 4 OCR/expense/equipment service was added"; else bad "Phase 4 service leakage detected"; fi
if git diff --check; then ok "git diff whitespace/conflict check"; else bad "git diff check failed"; fi

note "STATIC: PASS=$PASS FAIL=$FAIL"
if [ "$FAIL" -ne 0 ]; then note "PHASE 3 GATE: FAIL (static checks)"; exit 1; fi

if command -v php >/dev/null 2>&1; then
  note "--- PHP STATIC & UNIT ---"
  PHP_FAIL=0
  while IFS= read -r file; do
    if ! php -l "$file" >/dev/null; then bad "PHP syntax: $file"; PHP_FAIL=1; else ok "PHP syntax: $file"; fi
  done < <(find app api modules tests -type f -name '*.php' -print | sort)
  if [ "$PHP_FAIL" -ne 0 ]; then note "STATIC: FAIL (PHP syntax)"; exit 1; fi
  if php tests/unit_phase2.php; then note "PHASE 2 UNIT REGRESSION: PASS"; else note "PHASE 2 UNIT REGRESSION: FAIL"; exit 1; fi
  if php tests/unit_phase3.php; then note "UNIT: PASS (23 assertions)"; else note "UNIT: FAIL"; exit 1; fi
  if [ "${PHASE2_RUN_HTTP:-0}" = "1" ]; then
    if tests/http_phase2.sh; then note "PHASE 2 HTTP REGRESSION: PASS"; else note "PHASE 2 HTTP REGRESSION: FAIL"; exit 1; fi
  else
    note "PHASE 2 HTTP REGRESSION: NOT RUN (set PHASE2_RUN_HTTP=1 with BASE_URL to a migrated test app)"
  fi
  if [ "${PHASE3_RUN_HTTP:-0}" = "1" ]; then
    if tests/http_phase3.sh; then note "PHASE 3 HTTP/CSRF: PASS"; else note "PHASE 3 HTTP/CSRF: FAIL"; exit 1; fi
  else
    note "PHASE 3 HTTP/CSRF: NOT RUN (set PHASE3_RUN_HTTP=1 with a test account and BASE_URL)"
  fi
  if [ "${PHASE3_RUN_INTEGRATION:-0}" = "1" ]; then
    if php tests/integration_phase3.php; then note "INTEGRATION: PASS (integration assertion count shown above)"; else note "INTEGRATION: FAIL"; exit 1; fi
  else
    note "INTEGRATION: NOT RUN (set PHASE3_RUN_INTEGRATION=1 PHASE3_TEST_DB_OK=1 against an isolated migrated database)"
  fi
else
  note "STATIC PHP LINT: NOT RUNNABLE — PHP CLI unavailable"
  note "PHASE 2 REGRESSION: NOT RUNNABLE — PHP CLI unavailable"
  note "UNIT: NOT RUNNABLE — PHP CLI unavailable"
  note "INTEGRATION: NOT RUNNABLE — PHP CLI and MySQL/MariaDB runtime unavailable"
  note "PHASE 3 GATE: NOT RUNNABLE"
  exit 2
fi
