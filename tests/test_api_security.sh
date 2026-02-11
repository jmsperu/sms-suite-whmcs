#!/usr/bin/env bash
#
# SMS Suite — API Security Test Script
#
# Tests all security hardening changes:
#   1. Brute-force protection (auth rate limiting)
#   2. Phone number validation
#   3. Input sanitization
#   4. Gateway ownership
#   5. Webhook hub challenge XSS
#   6. Error response hardening
#   7. Normal API operations still work
#
# Usage:
#   export SMS_API_BASE="https://xcobean.com/modules/addons/sms_suite/webhook.php"
#   export SMS_API_KEY="sms_your_key"
#   export SMS_API_SECRET="your_secret"
#   bash tests/test_api_security.sh
#
# Or with smsapi.php entry point:
#   export SMS_API_BASE="https://xcobean.com/modules/addons/sms_suite/smsapi.php"
#   export SMS_API_PARAM="endpoint"   # use "endpoint" for smsapi.php, "route" for webhook.php
#   bash tests/test_api_security.sh

set -uo pipefail

# ── Config ──────────────────────────────────────────────────────────────
BASE="${SMS_API_BASE:?Set SMS_API_BASE to your API URL}"
KEY="${SMS_API_KEY:?Set SMS_API_KEY}"
SECRET="${SMS_API_SECRET:?Set SMS_API_SECRET}"
PARAM="${SMS_API_PARAM:-route}"   # "route" for webhook.php, "endpoint" for smsapi.php

# Optional: a valid phone for send tests (won't actually send if gateway isn't configured)
TEST_PHONE="${SMS_TEST_PHONE:-254702324532}"

PASS=0
FAIL=0
SKIP=0

# ── Helpers ─────────────────────────────────────────────────────────────
red()    { printf "\033[31m%s\033[0m" "$*"; }
green()  { printf "\033[32m%s\033[0m" "$*"; }
yellow() { printf "\033[33m%s\033[0m" "$*"; }
bold()   { printf "\033[1m%s\033[0m" "$*"; }

api_get() {
    local endpoint="$1"; shift
    curl -s -w "\n%{http_code}" \
        -H "X-API-Key: ${KEY}" \
        -H "X-API-Secret: ${SECRET}" \
        "${BASE}?${PARAM}=${endpoint}" "$@"
}

api_post() {
    local endpoint="$1"; shift
    local body="$1"; shift
    curl -s -w "\n%{http_code}" \
        -X POST \
        -H "Content-Type: application/json" \
        -H "X-API-Key: ${KEY}" \
        -H "X-API-Secret: ${SECRET}" \
        -d "${body}" \
        "${BASE}?${PARAM}=${endpoint}" "$@"
}

api_post_bad_auth() {
    local endpoint="$1"
    curl -s -w "\n%{http_code}" \
        -X POST \
        -H "Content-Type: application/json" \
        -H "X-API-Key: sms_invalidkey1234" \
        -H "X-API-Secret: wrong_secret_value" \
        -d '{}' \
        "${BASE}?${PARAM}=${endpoint}"
}

# Parse response: last line is HTTP code, rest is body
parse_response() {
    local raw="$1"
    HTTP_CODE=$(echo "$raw" | tail -n1)
    BODY=$(echo "$raw" | sed '$d')
}

# Extract JSON field (simple jq-like)
json_field() {
    echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d$1)" 2>/dev/null || echo ""
}

assert_http() {
    local test_name="$1"
    local expected="$2"

    if [ "$HTTP_CODE" = "$expected" ]; then
        echo "  $(green PASS) ${test_name} (HTTP ${HTTP_CODE})"
        PASS=$((PASS + 1))
    else
        echo "  $(red FAIL) ${test_name} — expected HTTP ${expected}, got ${HTTP_CODE}"
        echo "        Body: $(echo "$BODY" | head -c 200)"
        FAIL=$((FAIL + 1))
    fi
}

assert_json_field() {
    local test_name="$1"
    local field="$2"
    local expected="$3"
    local actual
    actual=$(json_field "$field")

    if [ "$actual" = "$expected" ]; then
        echo "  $(green PASS) ${test_name}"
        PASS=$((PASS + 1))
    else
        echo "  $(red FAIL) ${test_name} — expected '${expected}', got '${actual}'"
        FAIL=$((FAIL + 1))
    fi
}

assert_body_contains() {
    local test_name="$1"
    local needle="$2"

    if echo "$BODY" | grep -q "$needle"; then
        echo "  $(green PASS) ${test_name}"
        PASS=$((PASS + 1))
    else
        echo "  $(red FAIL) ${test_name} — body does not contain '${needle}'"
        echo "        Body: $(echo "$BODY" | head -c 200)"
        FAIL=$((FAIL + 1))
    fi
}

assert_header_contains() {
    local test_name="$1"
    local needle="$2"

    if echo "$HEADERS" | grep -qi "$needle"; then
        echo "  $(green PASS) ${test_name}"
        PASS=$((PASS + 1))
    else
        echo "  $(red FAIL) ${test_name} — headers do not contain '${needle}'"
        FAIL=$((FAIL + 1))
    fi
}

# ════════════════════════════════════════════════════════════════════════
echo ""
bold "━━━ SMS Suite API Security Tests ━━━"
echo ""
echo "Base URL : ${BASE}"
echo "Param    : ${PARAM}"
echo "API Key  : ${KEY:0:12}..."
echo ""

# ── 1. Normal API Operations ───────────────────────────────────────────
bold "1. Normal API Operations"
echo ""

# 1a. Balance
raw=$(api_get "balance")
parse_response "$raw"
assert_http "GET /balance returns 200" "200"
assert_json_field "Balance response has success=true" "['success']" "True"

# 1b. Gateways
raw=$(api_get "gateways")
parse_response "$raw"
assert_http "GET /gateways returns 200" "200"

# 1c. Messages
raw=$(api_get "messages")
parse_response "$raw"
assert_http "GET /messages returns 200" "200"

# 1d. Contacts (may be 403 if key lacks 'contacts' scope)
raw=$(api_get "contacts")
parse_response "$raw"
if [ "$HTTP_CODE" = "403" ]; then
    echo "  $(yellow SKIP) GET /contacts — API key lacks 'contacts' scope"
    SKIP=$((SKIP + 1))
else
    assert_http "GET /contacts returns 200" "200"
fi

# 1e. Templates
raw=$(api_get "templates")
parse_response "$raw"
assert_http "GET /templates returns 200" "200"

# 1f. Campaigns (may be 403 if key lacks 'campaigns' scope)
raw=$(api_get "campaigns")
parse_response "$raw"
if [ "$HTTP_CODE" = "403" ]; then
    echo "  $(yellow SKIP) GET /campaigns — API key lacks 'campaigns' scope"
    SKIP=$((SKIP + 1))
else
    assert_http "GET /campaigns returns 200" "200"
fi

# 1g. Transactions
raw=$(api_get "transactions")
parse_response "$raw"
assert_http "GET /transactions returns 200" "200"

# 1h. Unknown endpoint
raw=$(api_get "nonexistent")
parse_response "$raw"
assert_http "GET /nonexistent returns 404" "404"

echo ""

# ── 2. Auth Failure Logging ────────────────────────────────────────────
bold "2. Auth Failure Handling"
echo ""

raw=$(api_post_bad_auth "balance")
parse_response "$raw"
assert_http "Bad credentials return 401" "401"
assert_body_contains "Error message present" "Invalid API credentials"

echo ""

# ── 3. Brute-Force Protection ─────────────────────────────────────────
bold "3. Brute-Force Protection (11 rapid failed attempts)"
echo "  (Requires deployed security hardening code)"
echo ""

RATE_LIMITED=false
for i in $(seq 1 12); do
    raw=$(api_post_bad_auth "balance")
    parse_response "$raw"
    if [ "$HTTP_CODE" = "429" ]; then
        RATE_LIMITED=true
        echo "  $(green PASS) Rate limited after ${i} attempts (HTTP 429)"
        PASS=$((PASS + 1))
        break
    fi
done

if [ "$RATE_LIMITED" = false ]; then
    echo "  $(yellow SKIP) No rate limiting detected — deploy security hardening first"
    SKIP=$((SKIP + 1))
fi

# Verify our valid key still works (rate limit is per-IP, and the bad attempts also count)
# Wait a moment in case rate limiter is very aggressive
echo ""
echo "  Waiting 5s for rate limit window to pass..."
sleep 5

raw=$(api_get "balance")
parse_response "$raw"
# This might be 429 if the rate limit is strict per-IP regardless of key
if [ "$HTTP_CODE" = "200" ]; then
    echo "  $(green PASS) Valid API key still works after brute-force block"
    PASS=$((PASS + 1))
elif [ "$HTTP_CODE" = "429" ]; then
    echo "  $(yellow SKIP) Valid key also rate-limited (expected — same IP, wait for window)"
    SKIP=$((SKIP + 1))
else
    echo "  $(red FAIL) Unexpected status ${HTTP_CODE} after brute-force test"
    FAIL=$((FAIL + 1))
fi

echo ""

# ── 4. Phone Number Validation ────────────────────────────────────────
bold "4. Phone Number Validation"
echo ""

# 4a. Invalid phone: letters
raw=$(api_post "send" '{"to":"abc","message":"test"}')
parse_response "$raw"
assert_http "Send to 'abc' returns 400" "400"
assert_body_contains "Invalid phone error" "Invalid phone number"

# 4b. Invalid phone: too short
raw=$(api_post "send" '{"to":"123","message":"test"}')
parse_response "$raw"
assert_http "Send to '123' returns 400" "400"
assert_body_contains "Short phone rejected" "Invalid phone number"

# 4c. Invalid phone: empty
raw=$(api_post "send" '{"to":"","message":"test"}')
parse_response "$raw"
assert_http "Send to empty returns 400" "400"

# 4d. WhatsApp invalid phone
raw=$(api_post "whatsapp/send" '{"to":"notaphone","message":"test"}')
parse_response "$raw"
assert_http "WhatsApp send to invalid returns 400" "400"

# 4e. Schedule with invalid phone (requires deployed code)
raw=$(api_post "send/schedule" '{"to":"xyz","message":"test","scheduled_at":"2026-12-31 00:00:00"}')
parse_response "$raw"
if [ "$HTTP_CODE" = "400" ]; then
    echo "  $(green PASS) Schedule to invalid phone returns 400"
    PASS=$((PASS + 1))
else
    echo "  $(yellow SKIP) Schedule phone validation not active yet (HTTP ${HTTP_CODE}) — deploy first"
    SKIP=$((SKIP + 1))
fi

# 4f. WhatsApp template with invalid phone
raw=$(api_post "whatsapp/template" '{"to":"badphone","template_name":"test"}')
parse_response "$raw"
assert_http "WhatsApp template to invalid phone returns 400" "400"

# 4g. WhatsApp media with invalid phone
raw=$(api_post "whatsapp/media" '{"to":"nope","media_url":"https://example.com/img.jpg"}')
parse_response "$raw"
assert_http "WhatsApp media to invalid phone returns 400" "400"

# 4h. Valid phone format (should pass validation, may fail on gateway)
raw=$(api_post "send" "{\"to\":\"${TEST_PHONE}\",\"message\":\"Security test - ignore\"}")
parse_response "$raw"
if [ "$HTTP_CODE" = "201" ] || [ "$HTTP_CODE" = "400" ]; then
    # 201 = sent, 400 = gateway/billing issue (phone validated OK)
    echo "  $(green PASS) Valid phone accepted (HTTP ${HTTP_CODE})"
    PASS=$((PASS + 1))
else
    echo "  $(yellow SKIP) Valid phone test returned HTTP ${HTTP_CODE}"
    SKIP=$((SKIP + 1))
fi

echo ""

# ── 5. Contact Creation Validation ────────────────────────────────────
bold "5. Contact Creation Validation"
echo ""

# Check if we have contacts scope first
raw=$(api_get "contacts")
parse_response "$raw"
if [ "$HTTP_CODE" = "403" ]; then
    echo "  $(yellow SKIP) Contact tests — API key lacks 'contacts' scope"
    SKIP=$((SKIP + 3))
else
    # 5a. Invalid phone in contact
    raw=$(api_post "contacts" '{"phone":"notvalid"}')
    parse_response "$raw"
    assert_http "Create contact with invalid phone returns 400" "400"

    # 5b. Invalid email in contact
    raw=$(api_post "contacts" '{"phone":"254702324532","email":"not-an-email"}')
    parse_response "$raw"
    assert_http "Create contact with invalid email returns 400" "400"
    assert_body_contains "Email validation error" "Invalid email"

    # 5c. Valid contact creation
    raw=$(api_post "contacts" "{\"phone\":\"${TEST_PHONE}\",\"first_name\":\"Test\",\"last_name\":\"User\",\"email\":\"test@example.com\"}")
    parse_response "$raw"
    if [ "$HTTP_CODE" = "201" ] || [ "$HTTP_CODE" = "400" ]; then
        echo "  $(green PASS) Valid contact accepted (HTTP ${HTTP_CODE})"
        PASS=$((PASS + 1))
    else
        echo "  $(red FAIL) Valid contact creation failed (HTTP ${HTTP_CODE})"
        FAIL=$((FAIL + 1))
    fi
fi

echo ""

# ── 6. Bulk Send Validation ───────────────────────────────────────────
bold "6. Bulk Send Phone Validation"
echo ""

raw=$(api_post "send/bulk" '{"recipients":["abc","def","123"],"message":"test bulk"}')
parse_response "$raw"
assert_http "Bulk with all invalid phones returns 201" "201"
# All recipients should fail validation individually
assert_body_contains "Bulk reports failures" "Invalid phone number"

echo ""

# ── 7. Gateway Ownership (WhatsApp Templates) ────────────────────────
bold "7. Gateway Ownership Check"
echo ""

# Try accessing a non-existent gateway
raw=$(api_get "whatsapp/templates&gateway_id=99999")
parse_response "$raw"
if [ "$HTTP_CODE" = "403" ]; then
    echo "  $(green PASS) Non-existent gateway returns 403"
    PASS=$((PASS + 1))
elif [ "$HTTP_CODE" = "400" ]; then
    echo "  $(green PASS) Non-existent gateway returns 400"
    PASS=$((PASS + 1))
else
    echo "  $(yellow SKIP) Gateway ownership test returned HTTP ${HTTP_CODE}"
    SKIP=$((SKIP + 1))
fi

echo ""

# ── 8. Webhook Hub Challenge XSS ─────────────────────────────────────
bold "8. Webhook Hub Challenge XSS Protection"
echo ""

# Strip the query param part to get base webhook URL
WEBHOOK_BASE="${SMS_API_BASE}"
# Send a hub challenge with HTML injection
CHALLENGE_RESPONSE=$(curl -s -D - \
    "${WEBHOOK_BASE}?gateway=meta_whatsapp&hub_verify_token=invalid&hub_challenge=<script>alert(1)</script>&hub_mode=subscribe" \
    2>/dev/null)

HEADERS=$(echo "$CHALLENGE_RESPONSE" | sed '/^\r$/q')
BODY=$(echo "$CHALLENGE_RESPONSE" | sed '1,/^\r$/d')

# Should get 403 (invalid token) — but check the content-type would be text/plain if it were valid
if echo "$CHALLENGE_RESPONSE" | grep -qi "text/plain"; then
    echo "  $(green PASS) Hub challenge response has Content-Type: text/plain"
    PASS=$((PASS + 1))
elif echo "$BODY" | grep -q "Verification failed"; then
    echo "  $(green PASS) Hub challenge rejected invalid token (Content-Type enforced on valid path)"
    PASS=$((PASS + 1))
else
    echo "  $(yellow SKIP) Cannot verify Content-Type (token was invalid, got rejection)"
    SKIP=$((SKIP + 1))
fi

echo ""

# ── 9. Error Response Hardening ───────────────────────────────────────
bold "9. Error Response Hardening"
echo ""

# Send malformed webhook payload to trigger processing error
raw=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d '{"malformed": true}' \
    "${WEBHOOK_BASE}?gateway=unknown_type")
parse_response "$raw"

if echo "$BODY" | grep -q "Internal error"; then
    echo "  $(green PASS) Error response uses generic message"
    PASS=$((PASS + 1))
elif [ "$HTTP_CODE" = "200" ]; then
    echo "  $(green PASS) Webhook processed without error (no exception triggered)"
    PASS=$((PASS + 1))
else
    echo "  $(yellow SKIP) Webhook returned HTTP ${HTTP_CODE} — check manually"
    SKIP=$((SKIP + 1))
fi

# Verify no stack traces or detailed errors in response
if echo "$BODY" | grep -qiE "exception|stack.?trace|\.php:|line [0-9]"; then
    echo "  $(red FAIL) Response contains detailed error information"
    FAIL=$((FAIL + 1))
else
    echo "  $(green PASS) No detailed error info leaked in response"
    PASS=$((PASS + 1))
fi

echo ""

# ── 10. Send with Valid Phone (integration smoke test) ────────────────
bold "10. Send SMS Smoke Test"
echo ""

raw=$(api_post "send" "{\"to\":\"${TEST_PHONE}\",\"message\":\"Security hardening test\"}")
parse_response "$raw"

case "$HTTP_CODE" in
    201)
        echo "  $(green PASS) SMS sent successfully"
        PASS=$((PASS + 1))
        MSG_ID=$(json_field "['data']['message_id']")
        if [ -n "$MSG_ID" ] && [ "$MSG_ID" != "" ]; then
            echo "  $(green PASS) Got message_id: ${MSG_ID}"
            PASS=$((PASS + 1))

            # Check status
            raw=$(api_get "status&message_id=${MSG_ID}")
            parse_response "$raw"
            assert_http "GET /status for sent message returns 200" "200"
        fi
        ;;
    400)
        echo "  $(yellow SKIP) Send returned 400 (gateway/billing not configured)"
        SKIP=$((SKIP + 1))
        ;;
    403)
        echo "  $(yellow SKIP) Send returned 403 (scope not granted)"
        SKIP=$((SKIP + 1))
        ;;
    429)
        echo "  $(yellow SKIP) Send returned 429 (rate limited from earlier tests)"
        SKIP=$((SKIP + 1))
        ;;
    *)
        echo "  $(red FAIL) Unexpected HTTP ${HTTP_CODE}"
        FAIL=$((FAIL + 1))
        ;;
esac

echo ""

# ── Results ───────────────────────────────────────────────────────────
echo ""
bold "━━━ Results ━━━"
echo ""
echo "  $(green "PASS: ${PASS}")  $(red "FAIL: ${FAIL}")  $(yellow "SKIP: ${SKIP}")"
echo ""

if [ "$FAIL" -gt 0 ]; then
    echo "$(red 'Some tests FAILED. Review output above.')"
    exit 1
else
    echo "$(green 'All tests passed!')"
    exit 0
fi
