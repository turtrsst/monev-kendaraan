#!/usr/bin/env bash
# Authenticated HTTP CSRF regression for trip state routes.
# Requires a running migrated app and a test account without forced password change.
set -euo pipefail
BASE="${BASE_URL:-http://127.0.0.1:8085}"
: "${TEST_AUTH_USERNAME:?Set TEST_AUTH_USERNAME to a test account}"
: "${TEST_AUTH_PASSWORD:?Set TEST_AUTH_PASSWORD to a test account password}"
JAR="$(mktemp)"
LOGIN_HTML="$(mktemp)"
PAGE_HTML="$(mktemp)"
RESPONSE="$(mktemp)"
trap 'rm -f "$JAR" "$LOGIN_HTML" "$PAGE_HTML" "$RESPONSE"' EXIT
PASS=0
FAIL=0
ok(){ PASS=$((PASS+1)); echo "[PASS] $1"; }
bad(){ FAIL=$((FAIL+1)); echo "[FAIL] $1"; }

code=$(curl -sS -b "$JAR" -c "$JAR" -o "$LOGIN_HTML" -w '%{http_code}' "$BASE/login")
[ "$code" = "200" ] || { bad 'GET login form'; exit 1; }
token=$(grep -o 'name="_csrf" value="[a-f0-9]\{64\}"' "$LOGIN_HTML" | head -1 | sed 's/.*value="//;s/"//')
[ -n "$token" ] || { bad 'login CSRF token not found'; exit 1; }
code=$(curl -sS -b "$JAR" -c "$JAR" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_csrf=$token" --data-urlencode "username=$TEST_AUTH_USERNAME" \
  --data-urlencode "password=$TEST_AUTH_PASSWORD" "$BASE/login")
[ "$code" = "302" ] || { bad "test account login (HTTP $code)"; exit 1; }
ok 'test account login'
code=$(curl -sS -b "$JAR" -c "$JAR" -o "$RESPONSE" -w '%{http_code}' "$BASE/api/trips")
[ "$code" = "200" ] || { bad "authenticated trip API access / force-password gate (HTTP $code)"; exit 1; }
ok 'authenticated trip list API'
code=$(curl -sS -b "$JAR" -c "$JAR" -o "$RESPONSE" -w '%{http_code}' \
  -X POST -H 'Content-Type: application/json' -d '{}' "$BASE/api/trips/1/start")
if [ "$code" = "403" ] && grep -q 'csrf_invalid' "$RESPONSE"; then
  ok 'authenticated state-changing trip request without CSRF is rejected'
else
  bad "trip POST without CSRF should return 403 csrf_invalid (got HTTP $code)"
fi
printf '\nHTTP CSRF: PASS=%s FAIL=%s\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
