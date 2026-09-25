#!/usr/bin/env bash
# ==============================================================
# Gate Phase 1 — uji HTTP end-to-end (server PHP bawaan).
# Prasyarat: MariaDB aktif + migrate + seed (dijalankan runner).
# Usage: tests/http_phase1.sh  (base URL dari env BASE_URL)
# ==============================================================
set -uo pipefail

BASE="${BASE_URL:-http://127.0.0.1:8085}"
JAR="$(mktemp)"
PASS=0; FAIL=0; RESULTS=tests/RESULTS-http-phase1.txt
: > "$RESULTS"

say() { echo "$1"; }
ok()  { PASS=$((PASS+1)); echo "[PASS] $1" | tee -a "$RESULTS"; }
bad() { FAIL=$((FAIL+1)); echo "[FAIL] $1" | tee -a "$RESULTS"; }
chk() { # chk <nama> <kondisi-penuh-apakah-benar>
  if [ "$2" = "1" ]; then ok "$1"; else bad "$1"; fi
}

# --- CSRF + cookie dari halaman login ---------------------------------
login_page() { curl -s -c "$JAR" "$BASE/login"; }

extract_csrf() { # stdin = html
  grep -o 'name="_csrf" value="[a-f0-9]\{64\}"' | head -1 | sed 's/.*value="//;s/"//'
}

echo "=== PHASE 1 HTTP TESTS ($BASE) ==="

# 1. GET / → redirect ke /login (guest)
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/")
chk "GET / redirect ke /login" "$([ "$code" = "302" ] && echo 1 || echo 0)"

# 2. GET /login 200 + ada form CSRF
html=$(login_page)
csrf=$(printf '%s' "$html" | extract_csrf)
chk "GET /login 200 + form login" "$([ -n "$csrf" ] && printf '%s' "$html" | grep -q 'name="username"' && echo 1 || echo 0)"

# 3. GET /login tandai current (guest middleware: logged-in → redirect)
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE/login")
chk "guest: /login tetap 200 utk guest" "$([ "$code" = "200" ] && echo 1 || echo 0)"

# 4. POST /login TANPA CSRF → 403 csrf_invalid
code=$(curl -s -o /tmp/body403.json -w '%{http_code}' -b "$JAR" -c "$JAR" \
  -H 'X-Requested-With: XMLHttpRequest' \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"x"}' "$BASE/login")
body=$(cat /tmp/body403.json)
chk "POST /login tanpa CSRF → 403 code=csrf_invalid" \
  "$([ "$code" = "403" ] && printf '%s' "$body" | grep -q 'csrf_invalid' && echo 1 || echo 0)"

# 5. Login SQLi payload → 422 validation (bukan 500, tidak ada detail DB)
code=$(curl -s -o /tmp/b.sql -w '%{http_code}' -b "$JAR" -c "$JAR" \
  -H 'X-Requested-With: XMLHttpRequest' -H 'X-CSRF-Token: '"$csrf" \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin'\'' OR 1=1--","password":"whatever"}' "$BASE/login")
body=$(cat /tmp/b.sql)
chk "SQLi username → 422 validasi (tanpa error DB)" \
  "$([ "$code" = "422" ] && ! printf '%s' "$body" | grep -qiE 'SQLSTATE|syntax|PDOException' && echo 1 || echo 0)"

# 6. XSS ter-escape di halaman login (refleksi pesan error)
html=$(curl -s -b "$JAR" -c "$JAR" "$BASE/login?<script>=1")
chk "URL/script tidak dipantulkan mentah" \
  "$(printf '%s' "$html" | grep -q '<script>=1</script>' && echo 0 || echo 1)"

# 7. Password salah → error generik (bukan "user tidak ditemukan")
html=$(login_page); csrf=$(printf '%s' "$html" | extract_csrf)
code=$(curl -s -o /tmp/b.login -w '%{http_code}' -b "$JAR" -c "$JAR" \
  -H 'X-Requested-With: XMLHttpRequest' -H 'X-CSRF-Token: '"$csrf" \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"salah-banget"}' "$BASE/login")
body=$(cat /tmp/b.login)
chk "login gagal → pesan generik (username/password salah)" \
  "$([ "$code" = "401" ] && printf '%s' "$body" | grep -qi 'salah' && echo 1 || echo 0)"

# 8. Throttle: 5x gagal berturut → 429
got429=0
for i in 1 2 3 4 5; do
  html=$(login_page); csrf=$(printf '%s' "$html" | extract_csrf)
  code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" \
    -H 'X-Requested-With: XMLHttpRequest' -H 'X-CSRF-Token: '"$csrf" \
    -H 'Content-Type: application/json' \
    -d '{"username":"admin","password":"salah'$i'"}' "$BASE/login")
  [ "$code" = "429" ] && got429=1
done
chk "5x gagal → 429 rate-limited" "$got429"

# reset throttle biar langkah berikut bersih
"$PHP_BIN" -r 'define("BASE_PATH", getcwd()); require "app/core/Autoloader.php"; require "app/helpers/general.php"; require "app/helpers/log.php"; App\Core\Autoloader::register(); App\Core\Env::load(BASE_PATH."/.env"); App\Core\DB::run("DELETE FROM login_attempts"); App\Core\DB::run("UPDATE users SET failed_login_count=0, locked_until=NULL");' 2>>storage/logs/gate-inline.log

# 9. Login sukses (pakai password bootstrap dari env)
: "${TEST_ADMIN_PASSWORD:?butuh TEST_ADMIN_PASSWORD}"
html=$(login_page); csrf=$(printf '%s' "$html" | extract_csrf)
hdrs=$(curl -s -D /tmp/h.ok -o /dev/null -b "$JAR" -c "$JAR" \
  -H 'X-Requested-With: XMLHttpRequest' -H 'X-CSRF-Token: '"$csrf" \
  -H 'Content-Type: application/json' \
  -d "{\"username\":\"admin\",\"password\":\"$TEST_ADMIN_PASSWORD\"}" "$BASE/login")
code=$(head -1 /tmp/h.ok | awk '{print $2}')
loc=$(grep -i '^Location:' /tmp/h.ok | tr -d '\r' | awk '{print $2}')
chk "login sukses → 302 ke /ganti-password (force pw)" \
  "$([ "$code" = "302" ] && [ "$loc" = "/ganti-password" ] && echo 1 || echo 0)"

# 10. /api/auth/me → 200 + user admin + force_password_change=true
code=$(curl -s -o /tmp/b.me -w '%{http_code}' -b "$JAR" -c "$JAR" \
  -H 'X-Requested-With: XMLHttpRequest' -H "X-CSRF-Token: $csrf" "$BASE/api/auth/me")
body=$(cat /tmp/b.me)
chk "GET /api/auth/me → 200 user admin" \
  "$([ "$code" = "200" ] && printf '%s' "$body" | grep -q '"username":"admin"' && echo 1 || echo 0)"
chk "force_password_change=true" \
  "$(printf '%s' "$body" | grep -q '"force_password_change":true\|"force_password_change":1' && echo 1 || echo 0)"

# 11. Force-password-change gate: halaman selain whitelist → 403
code=$(curl -s -o /tmp/b.gate -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE/beranda")
body=$(cat /tmp/b.gate)
chk "gate: /beranda → 403 force_password_change" \
  "$([ "$code" = "403" ] && printf '%s' "$body" | grep -qi 'password' && echo 1 || echo 0)"

# 12. Gate: /api/auth/heartbeat TETAP diizinkan (keep-alive utk peringatan)
#     Ambil CSRF segar — login merotasi token
html=$(curl -s -b "$JAR" -c "$JAR" "$BASE/ganti-password")
csrf=$(printf '%s' "$html" | extract_csrf)
code=$(curl -s -o /tmp/b.hb -w '%{http_code}' -b "$JAR" -c "$JAR" \
  -H 'X-Requested-With: XMLHttpRequest' -H "X-CSRF-Token: $csrf" \
  -X POST "$BASE/api/auth/heartbeat")
body=$(cat /tmp/b.hb)
chk "gate: POST /api/auth/heartbeat → 200 (whitelisted)" \
  "$([ "$code" = "200" ] && printf '%s' "$body" | grep -q '"success":true' && echo 1 || echo 0)"

# 13. Gate: /pengaturan → 403 role (admin role lolos gate krn force pw dulu)
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE/pengaturan")
chk "role gate: /pengaturan → 403 (belum ganti password)" \
  "$([ "$code" = "403" ] && echo 1 || echo 0)"

# 14. Ganti password → sukses
html=$(curl -s -b "$JAR" -c "$JAR" "$BASE/ganti-password")
csrf=$(printf '%s' "$html" | extract_csrf)
NEW_PASS="${TEST_ADMIN_PASSWORD}x1"
curl -s -D /tmp/h.cp -o /dev/null -b "$JAR" -c "$JAR" \
  -H 'X-Requested-With: XMLHttpRequest' -H 'X-CSRF-Token: '"$csrf" \
  -H 'Content-Type: application/json' \
  -d "{\"current_password\":\"$TEST_ADMIN_PASSWORD\",\"new_password\":\"$NEW_PASS\",\"confirm_password\":\"$NEW_PASS\"}" \
  "$BASE/ganti-password"
code=$(head -1 /tmp/h.cp | awk '{print $2}')
loc=$(grep -i '^Location:' /tmp/h.cp | tr -d '\r' | awk '{print $2}')
chk "POST /ganti-password → 302 ke /beranda" \
  "$([ "$code" = "302" ] && [ "$loc" = "/beranda" ] && echo 1 || echo 0)"

# 15. Setelah ganti → gate lolos: /beranda 200
code=$(curl -s -o /tmp/b.dash -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE/beranda")
chk "force_password_change hilang → /beranda 200" \
  "$([ "$code" = "200" ] && echo 1 || echo 0)"

# 16. /pengaturan (admin) → 200
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" "$BASE/pengaturan")
chk "admin: /pengaturan → 200" "$([ "$code" = "200" ] && echo 1 || echo 0)"

# 17. POST /pengaturan tanpa CSRF → 403
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -c "$JAR" \
  -H 'X-Requested-With: XMLHttpRequest' -H 'Content-Type: application/json' \
  -d '{"hospital_name":"X"}' -X POST "$BASE/pengaturan")
chk "POST /pengaturan tanpa CSRF → 403" "$([ "$code" = "403" ] && echo 1 || echo 0)"

# 18. /api/auth/me TANPA cookie → 401
code=$(curl -s -o /dev/null -w '%{http_code}' -H 'X-Requested-With: XMLHttpRequest' "$BASE/api/auth/me")
chk "tanpa sesi: /api/auth/me → 401" "$([ "$code" = "401" ] && echo 1 || echo 0)"

# 19. POST /logout (route = POST) → destroy; setelahnya /api/auth/me 401
html=$(curl -s -b "$JAR" -c "$JAR" "$BASE/pengaturan")
csrf=$(printf '%s' "$html" | extract_csrf)
curl -s -o /dev/null -b "$JAR" -c "$JAR" -X POST \
  -H 'X-Requested-With: XMLHttpRequest' -H "X-CSRF-Token: $csrf" "$BASE/logout"
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" -H 'X-Requested-With: XMLHttpRequest' "$BASE/api/auth/me")
chk "logout menghancurkan sesi → 401" "$([ "$code" = "401" ] && echo 1 || echo 0)"

# 20. /.env TIDAK tersedia via web
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/.env")
chk "/.env tidak tersedia (404/403)" \
  "$([ "$code" = "404" ] || [ "$code" = "403" ] || [ "$code" = "301" ] && echo 1 || echo 0)"

# 21. /storage/.htaccess memblokir akses langsung
code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/../storage/logs/app-$(date +%F).log")
chk "storage/logs tidak terjangkau publik" \
  "$([ "$code" = "404" ] || [ "$code" = "403" ] && echo 1 || echo 0)"

# 22. Security headers minimal
curl -s -D /tmp/h.dash -o /dev/null -b "$JAR" "$BASE/beranda"
hdrs=$(cat /tmp/h.dash)
chk "X-Content-Type-Options: nosniff" "$(printf '%s' "$hdrs" | grep -qi 'x-content-type-options' && echo 1 || echo 0)"
chk "X-Frame-Options ada" "$(printf '%s' "$hdrs" | grep -qi 'x-frame-options' && echo 1 || echo 0)"

# 23. Error page 404 custom (bukan debug trace)
body=$(curl -s "$BASE/halaman-tidak-ada-xyz")
chk "404 page: tanpa stack trace" \
  "$(printf '%s' "$body" | grep -qi 'stack trace\|PDOException' && echo 0 || echo 1)"
chk "404 page: ada nomor 404" "$(printf '%s' "$body" | grep -q '404' && echo 1 || echo 0)"

# 24. Audit: login sukses tercatat
n=$("$PHP_BIN" -r 'define("BASE_PATH", getcwd()); require "app/core/Autoloader.php"; require "app/helpers/general.php"; require "app/helpers/log.php"; App\Core\Autoloader::register(); App\Core\Env::load(BASE_PATH."/.env"); echo (int)App\Core\DB::scalar("SELECT COUNT(*) FROM audit_logs WHERE action=\"LOGIN_SUCCESS\"");' 2>>storage/logs/gate-inline.log)
chk "audit: LOGIN_SUCCESS tercatat ($n)" "$([ "${n:-0}" -ge 1 ] && echo 1 || echo 0)"

rm -f "$JAR"
echo
echo "=== RINGKASAN HTTP: $PASS PASS, $FAIL FAIL ==="
[ "$FAIL" -eq 0 ]
