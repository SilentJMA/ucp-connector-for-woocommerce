#!/usr/bin/env bash
set -euo pipefail

# Minimal ACP/UCP smoke test runner for a live WordPress site.
# Usage:
#   BASE_URL="https://example.com" API_KEY="..." ./scripts/smoke-test.sh
# Optional:
#   PRODUCT_ID=123 ACP_HEADER="X-ACP-API-Key" UCP_HEADER="X-UCP-API-Key"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FIXTURE_DIR="${ROOT_DIR}/fixtures"

BASE_URL="${BASE_URL:-}"
API_KEY="${API_KEY:-}"
PRODUCT_ID="${PRODUCT_ID:-1}"
ACP_HEADER="${ACP_HEADER:-X-ACP-API-Key}"
UCP_HEADER="${UCP_HEADER:-X-UCP-API-Key}"
CURL_CONNECT_TIMEOUT="${CURL_CONNECT_TIMEOUT:-10}"
CURL_MAX_TIME="${CURL_MAX_TIME:-180}"
CURL_RETRIES="${CURL_RETRIES:-1}"

if [[ -z "${BASE_URL}" || -z "${API_KEY}" ]]; then
  echo "Missing BASE_URL or API_KEY."
  echo "Example:"
  echo "  BASE_URL='https://example.com' API_KEY='xxxx' PRODUCT_ID=123 ./scripts/smoke-test.sh"
  exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
  echo "python3 is required for JSON assertions."
  exit 1
fi

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "${WORK_DIR}"' EXIT

stamp() { date '+%H:%M:%S'; }
info() { echo "[$(stamp)] $*"; }
fail() { echo "[$(stamp)] ERROR: $*" >&2; exit 1; }

json_assert_field() {
  local json_file="$1"
  local expr="$2"
  python3 - "$json_file" "$expr" <<'PY'
import json, sys
path, expr = sys.argv[1], sys.argv[2]
with open(path, 'r', encoding='utf-8') as f:
    obj = json.load(f)
value = obj
for part in expr.split('.'):
    if part == '':
        continue
    if isinstance(value, dict) and part in value:
        value = value[part]
    else:
        print("missing")
        sys.exit(2)
if value in ("", None, [], {}):
    print("empty")
    sys.exit(3)
print(value)
PY
}

prepare_fixture() {
  local source="$1"
  local target="$2"
  sed "s/\"product_id\": 1/\"product_id\": ${PRODUCT_ID}/g" "${source}" > "${target}"
}

request_json() {
  local method="$1"
  local url="$2"
  local header_name="$3"
  local body_file="${4:-}"
  local out_file="$5"

  local http_code
  local attempt=1
  while [[ "${attempt}" -le "${CURL_RETRIES}" ]]; do
    if [[ -n "${body_file}" ]]; then
      http_code="$(curl -sS -o "${out_file}" -w "%{http_code}" \
        --connect-timeout "${CURL_CONNECT_TIMEOUT}" \
        --max-time "${CURL_MAX_TIME}" \
        -X "${method}" "${url}" \
        -H "Content-Type: application/json" \
        -H "${header_name}: ${API_KEY}" \
        --data @"${body_file}")" && { echo "${http_code}"; return 0; }
    else
      http_code="$(curl -sS -o "${out_file}" -w "%{http_code}" \
        --connect-timeout "${CURL_CONNECT_TIMEOUT}" \
        --max-time "${CURL_MAX_TIME}" \
        -X "${method}" "${url}" \
        -H "${header_name}: ${API_KEY}")" && { echo "${http_code}"; return 0; }
    fi

    if [[ "${attempt}" -lt "${CURL_RETRIES}" ]]; then
      info "Request failed (${method} ${url}), retry ${attempt}/${CURL_RETRIES}"
      sleep 2
    fi
    attempt=$((attempt + 1))
  done

  fail "Request failed after ${CURL_RETRIES} attempts (${method} ${url})"
}

assert_status() {
  local expected="$1"
  local actual="$2"
  local context="$3"
  [[ "${actual}" == "${expected}" ]] || fail "${context}: expected HTTP ${expected}, got ${actual}"
}

run_acp_flow() {
  info "Running ACP flow"

  local acp_create="${WORK_DIR}/acp_create.json"
  prepare_fixture "${FIXTURE_DIR}/acp/create_checkout_session.json" "${acp_create}"

  local acp_create_out="${WORK_DIR}/acp_create_out.json"
  local acp_code
  acp_code="$(request_json "POST" "${BASE_URL}/wp-json/acp/v1/checkout_sessions" "${ACP_HEADER}" "${acp_create}" "${acp_create_out}")"
  assert_status "201" "${acp_code}" "ACP create"

  local acp_session_id
  acp_session_id="$(json_assert_field "${acp_create_out}" "id")" || fail "ACP create response missing id"
  info "ACP session created: ${acp_session_id}"

  local acp_update_out="${WORK_DIR}/acp_update_out.json"
  local acp_update_code
  acp_update_code="$(request_json "POST" "${BASE_URL}/wp-json/acp/v1/checkout_sessions/${acp_session_id}" "${ACP_HEADER}" "${FIXTURE_DIR}/acp/update_checkout_session.json" "${acp_update_out}")"
  assert_status "200" "${acp_update_code}" "ACP update"
  json_assert_field "${acp_update_out}" "status" >/dev/null || fail "ACP update response missing status"

  local acp_get_out="${WORK_DIR}/acp_get_out.json"
  local acp_get_code
  acp_get_code="$(request_json "GET" "${BASE_URL}/wp-json/acp/v1/checkout_sessions/${acp_session_id}" "${ACP_HEADER}" "" "${acp_get_out}")"
  assert_status "200" "${acp_get_code}" "ACP get session"
  json_assert_field "${acp_get_out}" "totals.total" >/dev/null || fail "ACP get response missing totals.total"

  local acp_complete_out="${WORK_DIR}/acp_complete_out.json"
  local acp_complete_code
  acp_complete_code="$(request_json "POST" "${BASE_URL}/wp-json/acp/v1/checkout_sessions/${acp_session_id}/complete" "${ACP_HEADER}" "${FIXTURE_DIR}/acp/complete_checkout_session.json" "${acp_complete_out}")"
  assert_status "200" "${acp_complete_code}" "ACP complete"
  local acp_order_id
  acp_order_id="$(json_assert_field "${acp_complete_out}" "order.order_id")" || fail "ACP complete response missing order.order_id"

  local acp_order_out="${WORK_DIR}/acp_order_out.json"
  local acp_order_code
  acp_order_code="$(request_json "GET" "${BASE_URL}/wp-json/acp/v1/orders/${acp_order_id}" "${ACP_HEADER}" "" "${acp_order_out}")"
  assert_status "200" "${acp_order_code}" "ACP order lookup"
  json_assert_field "${acp_order_out}" "billing_address.address_1" >/dev/null || fail "ACP order missing billing_address.address_1"
  json_assert_field "${acp_order_out}" "shipping_address.address_1" >/dev/null || fail "ACP order missing shipping_address.address_1"

  info "ACP flow passed"
}

run_ucp_flow() {
  info "Running UCP compatibility flow"

  local ucp_create="${WORK_DIR}/ucp_create.json"
  prepare_fixture "${FIXTURE_DIR}/ucp/legacy_create_session.json" "${ucp_create}"

  local ucp_create_out="${WORK_DIR}/ucp_create_out.json"
  local ucp_create_code
  ucp_create_code="$(request_json "POST" "${BASE_URL}/wp-json/ucp/v1/session" "${UCP_HEADER}" "${ucp_create}" "${ucp_create_out}")"
  assert_status "201" "${ucp_create_code}" "UCP create"

  local ucp_session_id
  ucp_session_id="$(json_assert_field "${ucp_create_out}" "session_id")" || fail "UCP create response missing session_id"
  info "UCP session created: ${ucp_session_id}"

  local ucp_update_out="${WORK_DIR}/ucp_update_out.json"
  local ucp_update_code
  ucp_update_code="$(request_json "PUT" "${BASE_URL}/wp-json/ucp/v1/update/${ucp_session_id}" "${UCP_HEADER}" "${FIXTURE_DIR}/ucp/legacy_update_session.json" "${ucp_update_out}")"
  assert_status "200" "${ucp_update_code}" "UCP legacy update"
  json_assert_field "${ucp_update_out}" "success" >/dev/null || fail "UCP update response missing success"

  local ucp_status_out="${WORK_DIR}/ucp_status_out.json"
  local ucp_status_code
  ucp_status_code="$(request_json "GET" "${BASE_URL}/wp-json/ucp/v1/status/${ucp_session_id}" "${UCP_HEADER}" "" "${ucp_status_out}")"
  assert_status "200" "${ucp_status_code}" "UCP status"
  json_assert_field "${ucp_status_out}" "status" >/dev/null || fail "UCP status response missing status"

  local ucp_complete_out="${WORK_DIR}/ucp_complete_out.json"
  local ucp_complete_code
  ucp_complete_code="$(request_json "POST" "${BASE_URL}/wp-json/ucp/v1/complete/${ucp_session_id}" "${UCP_HEADER}" "${FIXTURE_DIR}/ucp/legacy_complete_session.json" "${ucp_complete_out}")"
  assert_status "200" "${ucp_complete_code}" "UCP complete"

  local ucp_order_id
  ucp_order_id="$(json_assert_field "${ucp_complete_out}" "order.order_id")" || fail "UCP complete response missing order.order_id"

  local ucp_order_out="${WORK_DIR}/ucp_order_out.json"
  local ucp_order_code
  ucp_order_code="$(request_json "GET" "${BASE_URL}/wp-json/ucp/v1/orders/${ucp_order_id}" "${UCP_HEADER}" "" "${ucp_order_out}")"
  assert_status "200" "${ucp_order_code}" "UCP order lookup"
  json_assert_field "${ucp_order_out}" "billing_address.address_1" >/dev/null || fail "UCP order missing billing_address.address_1"
  json_assert_field "${ucp_order_out}" "shipping_address.address_1" >/dev/null || fail "UCP order missing shipping_address.address_1"

  info "UCP flow passed"
}

info "Starting smoke tests against ${BASE_URL}"
run_acp_flow
run_ucp_flow
info "All smoke tests passed"
