#!/usr/bin/env bash
# ==============================================================
# PHASE 2 GATE RUNNER
# Mengevaluasi kelayakan Phase 2 gate:
# 1. Static validation (Syntax check, Scope check, Security check)
# 2. Runtime checks (PHP & MariaDB availability detection)
# Output: tests/RESULTS-phase2-gate.txt & stdout
# ==============================================================
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

OUT="tests/RESULTS-phase2-gate.txt"
: > "$OUT"

PASS=0
FAIL=0

note() { echo "$1" | tee -a "$OUT"; }
ok()   { PASS=$((PASS+1)); note "[PASS] $1"; }
bad()  { FAIL=$((FAIL+1)); note "[FAIL] $1"; }
chk()  { if [ "$2" = "1" ]; then ok "$1"; else bad "$1"; fi; }

note "=== PHASE 2 GATE RUNNER — $(date -u +%FT%TZ) ==="
note ""

# -------------------------------------------------------------
# 1. STATIC CODE & SCOPE CHECKS
# -------------------------------------------------------------
note "--- [1/4] Static Code & Scope Checks ---"

# Check bash syntax of test scripts
if bash -n tests/http_phase2.sh; then
  ok "bash syntax: tests/http_phase2.sh"
else
  bad "bash syntax: tests/http_phase2.sh"
fi

if bash -n tests/run_phase2_gate.sh; then
  ok "bash syntax: tests/run_phase2_gate.sh"
else
  bad "bash syntax: tests/run_phase2_gate.sh"
fi

# Check Phase 2 migration files exist
for m in 003_phase2_vehicles.sql 004_phase2_drivers.sql 005_phase2_ambulances.sql 006_phase2_assignments.sql; do
  if [ -f "database/migrations/$m" ]; then
    ok "migration file exists: $m"
  else
    bad "missing migration file: $m"
  fi
done

# Check Phase 2 Models exist
for mdl in Vehicle.php Driver.php Ambulance.php Assignment.php; do
  if [ -f "app/models/$mdl" ]; then
    ok "model exists: $mdl"
  else
    bad "missing model: $mdl"
  fi
done

# Check Phase 2 Services exist
for srv in VehicleService.php DriverService.php AmbulanceService.php AssignmentService.php; do
  if [ -f "app/services/$srv" ]; then
    ok "service exists: $srv"
  else
    bad "missing service: $srv"
  fi
done

# Check Phase 2 Validators exist
for val in VehicleValidator.php DriverValidator.php AmbulanceValidator.php AssignmentValidator.php; do
  if [ -f "app/validators/$val" ]; then
    ok "validator exists: $val"
  else
    bad "missing validator: $val"
  fi
done

# Check Phase 2 Controllers exist
for ctrl in modules/vehicles/controllers/VehicleController.php \
            modules/drivers/controllers/DriverController.php \
            modules/ambulances/controllers/AmbulanceController.php \
            modules/assignments/controllers/AssignmentController.php \
            api/vehicles/VehicleApiController.php \
            api/drivers/DriverApiController.php \
            api/ambulances/AmbulanceApiController.php \
            api/assignments/AssignmentApiController.php; do
  if [ -f "$ctrl" ]; then
    ok "controller exists: $ctrl"
  else
    bad "missing controller: $ctrl"
  fi
done

# -------------------------------------------------------------
# 2. HARD SCOPE LOCK & LEAKAGE CHECK (NO PHASE 3 IMPLEMENTATION)
# -------------------------------------------------------------
note ""
note "--- [2/4] Hard Scope Lock (Verification No Phase 3) ---"

# TripService should not exist
if [ -f "app/services/TripService.php" ]; then
  bad "Phase 3 leakage detected: app/services/TripService.php exists!"
else
  ok "no TripService.php in app/services/"
fi

# Trip model should not exist
if [ -f "app/models/Trip.php" ]; then
  bad "Phase 3 leakage detected: app/models/Trip.php exists!"
else
  ok "no Trip.php in app/models/"
fi

# TripController should not exist
if [ -f "modules/trips/controllers/TripController.php" ] || [ -f "api/trips/TripApiController.php" ]; then
  bad "Phase 3 leakage detected: TripController or TripApiController exists!"
else
  ok "no Trip controllers in modules/ or api/"
fi

# No GPS tracking, OCR service, or expense ledger implementation
if [ -f "app/services/GpsService.php" ] || [ -f "app/services/OcrService.php" ] || [ -f "app/services/ExpenseService.php" ]; then
  bad "Phase 3+ services detected!"
else
  ok "no Phase 3+ services (GPS, OCR, Expense)"
fi

# Check no git conflict markers (search without matching this runner file itself)
CONFLICT_PATTERN="<""<""<""<""<""<""< HEAD"
CONFLICTS=$(grep -rn --exclude="run_phase2_gate.sh" "$CONFLICT_PATTERN" app modules api database tests 2>/dev/null || true)
if [ -n "$CONFLICTS" ]; then
  bad "Git conflict markers found in codebase!"
else
  ok "no git conflict markers found"
fi

# -------------------------------------------------------------
# 3. RUNTIME AVAILABILITY CHECKS
# -------------------------------------------------------------
note ""
note "--- [3/4] Runtime Environment Availability ---"

PHP_AVAILABLE=0
if command -v php >/dev/null 2>&1; then
  PHP_AVAILABLE=1
  ok "PHP CLI detected: $(php -v | head -1)"
else
  note "[INFO] PHP CLI unavailable in this environment"
fi

DB_AVAILABLE=0
if command -v mysql >/dev/null 2>&1 || command -v mariadb >/dev/null 2>&1; then
  DB_AVAILABLE=1
  ok "MySQL/MariaDB client detected"
else
  note "[INFO] MySQL/MariaDB unavailable in this environment"
fi

# -------------------------------------------------------------
# 4. GATE VERDICT
# -------------------------------------------------------------
note ""
note "--- [4/4] Phase 2 Verdict ---"
note "STATIC CHECKS PASS: $PASS"
note "STATIC CHECKS FAIL: $FAIL"

if [ "$PHP_AVAILABLE" -eq 1 ] && [ "$DB_AVAILABLE" -eq 1 ]; then
  note "PHP RUNTIME: RUNNABLE"
  note "DATABASE RUNTIME: RUNNABLE"
else
  note "PHP RUNTIME: NOT RUNNABLE — PHP CLI unavailable"
  note "DATABASE RUNTIME: NOT RUNNABLE — Database daemon unavailable"
  note "PHASE 2 GATE: NOT APPROVED (Awaiting PHP 8.2+ & MySQL isolated test run)"
fi

echo ""
echo "Gate evaluation finished."
