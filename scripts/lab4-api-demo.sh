#!/bin/sh
# Lab 4 helper (host): logs in over HTTP with a cookie jar, issues a Sanctum token and calls the API.
# Works against the hardened solution (needs routes from solutions/lab4). Uses the seeded test account only.
# Usage: sh scripts/lab4-api-demo.sh [read|manage]
set -e
BASE=${BASE:-http://localhost:8000}
LEVEL=${1:-read}
EMAIL=${LAB_EMAIL:-admin.alpha@example.test}
PASSWORD=${LAB_PASSWORD:-Lab-Passw0rd!}
JAR=$(mktemp)
trap 'rm -f "$JAR"' EXIT

csrf() { grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"$//'; }

echo "1) GET /login  (session cookie + CSRF token)"
TOKEN=$(curl -s -c "$JAR" -b "$JAR" "$BASE/login" | csrf)

echo "2) POST /login"
curl -s -o /dev/null -w "   status %{http_code}\n" -c "$JAR" -b "$JAR" \
  --data-urlencode "_token=$TOKEN" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASSWORD" "$BASE/login"

TOKEN=$(curl -s -c "$JAR" -b "$JAR" "$BASE/projects" | csrf)

echo "3) POST /settings/api-tokens  (access_level=$LEVEL)"
RESP=$(curl -s -c "$JAR" -b "$JAR" -H "Accept: application/json" -H "X-CSRF-TOKEN: $TOKEN" \
  --data-urlencode "name=demo-$LEVEL" --data-urlencode "access_level=$LEVEL" "$BASE/settings/api-tokens")
echo "   $RESP" | sed 's/"token":"[^"]*"/"token":"<hidden>"/'
API_TOKEN=$(echo "$RESP" | grep -o '"token":"[^"]*"' | sed 's/"token":"//; s/"$//')
[ -n "$API_TOKEN" ] || { echo "   no token returned"; exit 1; }

echo "4) GET /api/secure-products (Bearer)"
curl -s -o /dev/null -w "   status %{http_code}\n" -H "Accept: application/json" -H "Authorization: Bearer $API_TOKEN" "$BASE/api/secure-products"

echo "5) POST /api/secure-products (Bearer)  -> read: 403, manage: 201"
curl -s -o /dev/null -w "   status %{http_code}\n" -X POST -H "Accept: application/json" -H "Authorization: Bearer $API_TOKEN" "$BASE/api/secure-products"

echo "6) No token -> 401"
curl -s -o /dev/null -w "   status %{http_code}\n" -H "Accept: application/json" "$BASE/api/secure-products"
