#!/usr/bin/env bash
# ==============================================================
# PHASE 1 GATE — menjalankan SELURUH test gate + security checks.
# Output: tests/RESULTS-phase1-gate.txt  (dan stdout)
# Exit code: 0 = semua PASS, 1 = ada FAIL
# Usage: [PHP_BIN=...] [BASE_PORT=8085] [START_DB=1] tests/run_phase1_gate.sh
# ==============================================================
set -uo pipefail
cd "$(dirname "$0")/.."
ROOT="$PWD"
OUT="tests/RESULTS-phase1-gate.txt"
: > "$OUT"
PASS=0; FAIL=0

note() { echo "$1" | tee -a "$OUT"; }
ok()  { PASS=$((PASS+1)); note "[PASS] $1"; }
bad() { FAIL=$((FAIL+1)); note "[FAIL] $1"; }
chk() { if [ "$2" = "1" ]; then ok "$1"; else bad "$1"; fi; }

note "=== PHASE 1 GATE — $(date -u +%FT%TZ) ==="
note ""

# ---------------------------------------------------------------
note "--- [1/6] Runtime PHP ---"
PHP="${PHP_BIN:-/home/user/build/opt/php/bin/php}"
if [ ! -x "$PHP" ]; then
  PHP="$(command -v php || true)"
fi
if [ -z "$PHP" ] || [ ! -x "$PHP" ]; then
  bad "binary PHP tidak ditemukan (build PHP dulu)"
  note "RINGKASAN: $PASS PASS, $FAIL FAIL (gagal fatal)"
  exit 1
fi
VER="$("$PHP" -r 'echo PHP_VERSION;')"
ok "PHP: $VER ($PHP)"
MODS="$("$PHP" -m 2>/dev/null || true)"
for m in pdo pdo_mysql mbstring json session fileinfo filter tokenizer ctype posix; do
  # here-string (tanpa pipe) — hindari kode status aneh pipefail + grep -q
  if grep -qix "$m" <<<"$MODS"; then ok "ext: $m"; else bad "ext: $m HILANG"; fi
done
PHP_BIN="$PHP"; export PHP_BIN
if "$PHP" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
  ok "PHP >= 8.2"
else
  bad "PHP < 8.2"
fi

# ---------------------------------------------------------------
note ""
note "--- [2/6] Database (MariaDB) + migrasi ---"
SOCK="${MARIADB_SOCK:-/home/user/build/run/mysqld.sock}"
MYSQLD="${MARIADB_BIN:-/home/user/build/opt/mariadb/bin/mariadbd}"
DATADIR="${MARIADB_DATADIR:-/home/user/build/opt/mariadb/data}"

if [ ! -S "$SOCK" ]; then
  if [ -x "$MYSQLD" ] && [ -d "$DATADIR" ] && [ "${START_DB:-1}" = "1" ]; then
    note "start mariadbd (socket $SOCK)…"
    mkdir -p "$(dirname "$SOCK")"
    "$MYSQLD" --datadir="$DATADIR" --socket="$SOCK" --port="${DB_PORT:-3306}" \
      --bind-address=127.0.0.1 --user="$(id -un)" --skip-networking=0 \
      > storage/logs/mysqld-gate.log 2>&1 &
    for i in $(seq 1 30); do [ -S "$SOCK" ] && break; sleep 1; done
  fi
fi
if [ -S "$SOCK" ]; then
  ok "MariaDB running (socket $SOCK)"
else
  bad "MariaDB TIDAK jalan (socket $SOCK absen) — build server belum selesai / START_DB=0"
fi

if [ -S "$SOCK" ]; then
  # buat DB + user bila belum ada (root tanpa password via socket/127.0.0.1 utk bootstrap sandbox)
  SETUP=$("$PHP" -r '
    require "app/core/Autoloader.php";
    App\Core\Autoloader::register();
    $h = getenv("DB_HOST") ?: "127.0.0.1"; $p = getenv("DB_PORT") ?: "3306";
    $pdo = new PDO("mysql:host=$h;port=$p", "root", "", [PDO::ATTR_TIMEOUT => 5]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS kendaraan_logbook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE USER IF NOT EXISTS '\''fleet'\''@'\''%'\'' IDENTIFIED BY '\''fleet_secret_sandbox'\''");
    $pdo->exec("GRANT ALL PRIVILEGES ON kendaraan_logbook.* TO '\''fleet'\''@'\''%'\''");
    $pdo->exec("FLUSH PRIVILEGES");
    echo "DB_READY";
  ' 2>&1) || true
  chk "database + user fleet siap [$SETUP]" "$([ "$SETUP" = "DB_READY" ] && echo 1 || echo 0)"
fi

# migrasi
if [ -S "$SOCK" ]; then
  if "$PHP" database/migrate.php > storage/logs/migrate-gate.log 2>&1; then
    ok "migrate.php sukses (lihat storage/logs/migrate-gate.log)"
    tail -n +1 storage/logs/migrate-gate.log | sed 's/^/    | /' | tee -a "$OUT"
  else
    bad "migrate.php GAGAL"; tail -5 storage/logs/migrate-gate.log | tee -a "$OUT"
  fi
  # seed admin + password bootstrap (rotasi tiap gate — tidak pernah disimpan di UI/repo)
  SEED_OUT="$("$PHP" database/seeds/seed_admin.php --reset-password 2>&1)"
  TEST_ADMIN_PASSWORD="$(printf '%s' "$SEED_OUT" | sed -n 's/^Password *: *//p' | tail -1)"
  export TEST_ADMIN_PASSWORD
  if [ -n "$TEST_ADMIN_PASSWORD" ]; then
    ok "seed admin: password bootstrap dirotasi (hanya untuk gate ini)"
  else
    bad "seed admin: gagal mengekstrak password dari output"
  fi
fi

# ---------------------------------------------------------------
note ""
note "--- [3/6] Unit tests ---"
if [ -n "${TEST_ADMIN_PASSWORD:-}" ]; then
  if "$PHP" tests/unit_phase1.php --admin-password="$TEST_ADMIN_PASSWORD" 2>&1 | tee -a "$OUT"; then
    ok "unit suite exit=0"
  else
    bad "unit suite exit≠0"
  fi
else
  bad "unit suite dilewati (tanpa password)"
fi

# ---------------------------------------------------------------
note ""
note "--- [4/6] HTTP gate tests ---"
rm -rf storage/cache/ratelimit 2>/dev/null || true
PORT="${BASE_PORT:-8085}"
BASE_URL="http://127.0.0.1:$PORT"; export BASE_URL
# pastikan port bebas
if curl -s -o /dev/null --max-time 2 "http://127.0.0.1:$PORT/login"; then
  bad "port $PORT sudah terpakai server lain"
else
  "$PHP" -S 0.0.0.0:"$PORT" -t public public/index.php > storage/logs/php-server.log 2>&1 &
  SERVER_PID=$!
  for i in $(seq 1 20); do
    curl -s -o /dev/null --max-time 1 "$BASE_URL/login" && break
    sleep 0.5
  done
  if bash tests/http_phase1.sh 2>&1 | tee -a "$OUT"; then
    ok "HTTP suite exit=0"
  else
    bad "HTTP suite exit≠0"
  fi
  kill "$SERVER_PID" 2>/dev/null || true
  wait "$SERVER_PID" 2>/dev/null || true
fi

# ---------------------------------------------------------------
note ""
note "--- [5/6] Security checks ---"
# .env tidak di-commit
if git ls-files --error-unmatch .env >/dev/null 2>&1; then
  bad ".env TER-TRACK di git"
else
  ok ".env tidak di-commit"
fi
chk ".env ada di .gitignore" "$(grep -qE '^\.env\*?|^\.env$' .gitignore && echo 1 || echo 0)"
# rahasia tidak bocor ke file ter-track
LEAK=$(git grep -l "fleet_secret_sandbox" -- ':!.env.example' 2>/dev/null | grep -v '^tests/' | head -3)
chk "password DB sandbox tidak ada di file ter-track [${LEAK:-bersih}]" "$([ -z "$LEAK" ] && echo 1 || echo 0)"
# tanpa CDN eksternal
CDN=$(grep -rlE 'cdn\.jsdelivr|cdnjs\.cloud|unpkg\.com|fonts\.googleapis|fonts\.gstatic' public/assets/css app/ modules/ api/ 2>/dev/null | head -3)
chk "tanpa CDN eksternal (aset di-vendor) [${CDN:-bersih}]" "$([ -z "$CDN" ] && echo 1 || echo 0)"
# nama RS tidak hard-coded di kode aplikasi
HARD=$(grep -rl "Soeradji" app/ modules/ api/ public/ 2>/dev/null | head -3)
chk "nama RS tidak hard-coded di kode aplikasi [${HARD:-bersih}]" "$([ -z "$HARD" ] && echo 1 || echo 0)"
# storage privat dijaga
chk "storage/.htaccess (deny) ada" "$(grep -q 'Require all denied' storage/.htaccess && echo 1 || echo 0)"
chk "storage/logs/.htaccess ada" "$(test -f storage/logs/.htaccess && echo 1 || echo 0)"
chk "storage/private/.htaccess ada" "$(test -f storage/private/.htaccess && echo 1 || echo 0)"
# password_hash pada seeder
chk "seeder memakai password_hash()" "$(grep -q 'password_hash(' database/seeds/seed_admin.php && echo 1 || echo 0)"
# prepared statements: tidak ada query string konkatenasi user-input kasar (heuristik)
BADSQL=$(grep -rnE '(query|exec|prepare)\s*\(\s*["'\''].*\$_(GET|POST|REQUEST)' app/ modules/ api/ 2>/dev/null | grep -v '//' | head -3)
chk "tanpa query konkatenasi \$_GET/\$_POST [${BADSQL:-bersih}]" "$([ -z "$BADSQL" ] && echo 1 || echo 0)"
# escape output
chk "helper e() dipakai di view (count)" "$([ "$(grep -ro 'e(' app/views app/views 2>/dev/null | wc -l)" -gt 10 ] && echo 1 || echo 0)"
# CSRF: semua POST form punya _csrf
FORM_FILES=$(grep -rEl '<form' app/ modules/ --include='*.php' | wc -l)
CSRFD_FILES=$(grep -rEl 'name="_csrf"|csrf_field\(' app/ modules/ --include='*.php' | wc -l)
chk "setiap view form punya _csrf (file form=$FORM_FILES, file dgn csrf=$CSRFD_FILES)" \
  "$([ "$FORM_FILES" -le "$CSRFD_FILES" ] && echo 1 || echo 0)"
# vendor bootstrap ter-vendor lokal
chk "bootstrap.min.css lokal" "$(test -f public/assets/vendor/bootstrap/css/bootstrap.min.css && echo 1 || echo 0)"
chk "bootstrap.bundle.min.js lokal" "$(test -f public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js && echo 1 || echo 0)"
chk "bootstrap-icons lokal + font" "$(test -f public/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2 && echo 1 || echo 0)"
# tidak ada file besar aneh / file ter-commit mencurigakan
BIG=$(git ls-files -z | xargs -0 -I{} sh -c 'test -f "{}" && test $(stat -c%s "{}") -gt 2000000 && echo "{}"' 2>/dev/null | head -3)
chk "tanpa file ter-track >2MB [${BIG:-bersih}]" "$([ -z "$BIG" ] && echo 1 || echo 0)"

# ---------------------------------------------------------------
note ""
note "--- [6/6] Ringkasan akhir ---"
# gabung hasil unit
if [ -f tests/RESULTS-unit-phase1.txt ]; then
  U_PASS=$(grep -c '^PASS' tests/RESULTS-unit-phase1.txt 2>/dev/null || echo 0)
  U_FAIL=$(grep -c '^FAIL' tests/RESULTS-unit-phase1.txt 2>/dev/null || echo 0)
  note "unit: $U_PASS PASS, $U_FAIL FAIL"
fi
note "gate-level: $PASS PASS, $FAIL FAIL"
if [ "$FAIL" -eq 0 ]; then
  note "STATUS GATE PHASE 1: ✅ PASS"
else
  note "STATUS GATE PHASE 1: ❌ FAIL ($FAIL item)"
fi
note "Hasil lengkap: $OUT"
[ "$FAIL" -eq 0 ]
