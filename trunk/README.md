# UCP Connector for WooCommerce

UCP Connector for WooCommerce is a merchant-owned checkout adapter for:
- UCP APIs (`/wp-json/ucp/v1`)
- ACP APIs (`/wp-json/acp/v1`)

It keeps one normalized checkout-session model and maps it to WooCommerce carts and orders.

## Core Features

### Unified Checkout Session Model
- Single session structure used by both ACP and UCP endpoints
- Merchant-authoritative recalculation of price, tax, stock, and fulfillment state
- Consistent response objects for line items, totals, messages, links, and order data

### ACP and UCP Route Coverage
- ACP checkout sessions:
  - `POST /checkout_sessions`
  - `POST /checkout_sessions/{id}`
  - `GET /checkout_sessions/{id}`
  - `POST /checkout_sessions/{id}/complete`
  - `POST /checkout_sessions/{id}/cancel`
- UCP compatibility routes:
  - `POST /session`
  - `PUT /update/{id}`
  - `GET /status/{id}`
  - `POST /complete/{id}`
- Shared utility routes:
  - `GET /capabilities`
  - `GET /product/search`
  - `GET /orders/{order_id}`
  - `GET /sessions`

### Security Controls
- API key authentication with:
  - `Authorization: Bearer <key>`
  - `X-UCP-API-Key: <key>`
  - `X-ACP-API-Key: <key>`
- Optional IP allowlist
- Optional request rate limiting
- Optional `UCP-Agent` domain allowlist with wildcard matching
- Optional detached JWS request signature verification via `Request-Signature`

### WooCommerce Order Mapping
- Creates WooCommerce orders from checkout sessions
- Returns both `billing_address` and `shipping_address` in completion and order lookup responses
- Exposes order IDs and totals for follow-up workflows

### Admin Experience
- Settings for protocol toggles, API key, and timeouts
- Merchant metadata and policy link configuration
- Security section for rate limits, IP allowlists, agent controls, and signatures
- Sessions view for recent checkout lifecycle monitoring

## Installation

1. Install and activate the plugin.
2. Open **UCP Connector for WooCommerce** in WordPress admin.
3. Generate or copy the API key.
4. Configure protocol, security, and merchant settings.

## Usage Examples

- ACP payload fixtures: [`fixtures/acp`](./fixtures/acp)
- UCP payload fixtures: [`fixtures/ucp`](./fixtures/ucp)
- Smoke test script: [`scripts/smoke-test.sh`](./scripts/smoke-test.sh)

Example run:

```bash
BASE_URL="https://your-wordpress-site.example" \
API_KEY="your_api_key" \
PRODUCT_ID=123 \
bash scripts/smoke-test.sh
```

## Validation Results

- Current validation summary: [`TEST_RESULTS.md`](./TEST_RESULTS.md)

## Version

- Current plugin version: `1.3.0`
