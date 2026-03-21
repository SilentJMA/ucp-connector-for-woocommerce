# Test Results

## Environment

- Runtime: Local WordPress + WooCommerce
- Date: March 21, 2026
- Plugin version under test: `1.3.0`

## Smoke Test Coverage

Script: [`scripts/smoke-test.sh`](./scripts/smoke-test.sh)

Validated flows:
- ACP: create -> update -> get -> complete -> order lookup
- UCP: create -> update -> status -> complete -> order lookup
- Address assertions:
  - `billing_address.address_1`
  - `shipping_address.address_1`

## Latest Observed Results

- ACP flow: **PASS**
  - Session creation and completion succeeded.
  - Order lookup returned billing and shipping address fields.
- UCP flow: **PARTIAL / ENVIRONMENT-LIMITED**
  - Requests succeed, but response latency is highly variable on this LocalWP stack.
  - Some calls complete after long delays (observed >100 seconds), causing timeout-prone runs.

## Security and Syntax Validation

- PHP syntax checks: **PASS**
  - `includes/core/class-ucp-security.php`
  - `includes/admin/class-ucp-admin.php`
  - `includes/api/class-ucp-rest-api.php`
  - `ucp_adapter_main.php`
  - Same checks passed in `trunk/` copies.

## Notes

- Failures seen during test runs are currently dominated by local environment request stalls (php-fpm workers blocked by broader stack/plugin load), not deterministic adapter syntax/runtime fatals in this plugin.
