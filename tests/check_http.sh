#!/usr/bin/env bash
set -euo pipefail

# Simple HTTP checks for backup_app .htaccess behavior
# Usage: ./tests/check_http.sh [BASE_URL]
# Example: ./tests/check_http.sh http://localhost

BASE_URL=${1:-http://localhost}

fail=0
echo "Running checks against: $BASE_URL"

check_root() {
  echo "- Checking root page..."
  code=$(curl -s -o /tmp/check_root_body -w "%{http_code}" "$BASE_URL/") || true
  body=$(cat /tmp/check_root_body 2>/dev/null || true)
  if [[ "$code" =~ ^2 ]]; then
    echo "  OK: HTTP $code"
    if echo "$body" | grep -q "Backup — DB dump"; then
      echo "  OK: Found backup page text"
    else
      echo "  WARN: backup page text not found"
      fail=1
    fi
  else
    echo "  FAIL: root returned HTTP $code"
    fail=1
  fi
}

check_dotfile() {
  echo "- Checking dotfile protection (.env)..."
  code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/.env" || true)
  if [[ "$code" =~ ^4|5 ]]; then
    echo "  OK: access denied (HTTP $code)"
  else
    echo "  FAIL: .env accessible (HTTP $code)"
    fail=1
  fi
}

check_static_file() {
  echo "- Checking static file serving from public/ (creating test file)..."
  TMPFILE="public/check_http_test_$$.txt"
  echo "check-ok" > "$TMPFILE"
  sleep 0.2
  body=$(curl -s "$BASE_URL/$(basename $TMPFILE)" || true)
  if [[ "$body" == "check-ok" ]]; then
    echo "  OK: static file served"
  else
    echo "  FAIL: static file not served or not reachable"
    echo "  (got: ${body:0:80})"
    fail=1
  fi
  rm -f "$TMPFILE"
}

check_headers() {
  echo "- Checking security headers..."
  headers=$(curl -sI "$BASE_URL/" || true)
  ok=1
  for h in "X-Frame-Options" "X-Content-Type-Options" "Referrer-Policy"; do
    echo "$headers" | grep -i "$h" >/dev/null || { echo "  WARN: $h missing"; ok=0; }
  done
  if [[ $ok -eq 1 ]]; then
    echo "  OK: basic security headers present"
  else
    echo "  WARN: some headers missing"
  fi
}

echo "Starting checks..."
check_root
check_dotfile
check_static_file
check_headers

if [[ $fail -ne 0 ]]; then
  echo "One or more checks failed. See output above."
  exit 2
fi

echo "All checks passed (or only non-fatal warnings)."
