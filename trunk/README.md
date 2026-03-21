# UCP Connector for WooCommerce

WooCommerce plugin for **UCP** and **OpenAI Agentic Commerce Protocol (ACP)** checkout flows.

This plugin exposes merchant-owned checkout sessions and order creation APIs under:
- `wp-json/acp/v1` (ACP)
- `wp-json/ucp/v1` (UCP compatibility)

## Why This Plugin

UCP Connector for WooCommerce gives you one normalized commerce adapter for:
- ACP-style checkout sessions (`create`, `update`, `get`, `complete`, `cancel`)
- Legacy UCP session compatibility routes
- Merchant-authoritative pricing/tax/stock recalculation on each update
- WooCommerce-native order creation and lookup

## Features

### Protocol and API
- ACP checkout session routes:
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
- Shared routes:
  - `GET /capabilities`
  - `GET /product/search`
  - `GET /orders/{order_id}`
  - `GET /sessions`

### Security Model (Shopware-inspired, adapted for WooCommerce)
- API key authentication:
  - `Authorization: Bearer <key>`
  - `X-UCP-API-Key: <key>`
  - `X-ACP-API-Key: <key>`
- Optional IP allowlist
- Optional rate limiting (IP + auth identity)
- Optional `UCP-Agent` profile domain allowlist with wildcard support (`*.openai.com`)
- Optional detached JWS request signature verification via `Request-Signature` and agent `signing_keys`

### Admin Experience
- Clear settings sections for:
  - Core plugin settings
  - Agent security model
  - Network guards
- API key regeneration
- Protocol toggles
- Store metadata + policy link controls

## Quick Start

1. Activate plugin in WordPress.
2. Open **UCP Connector for WooCommerce** in wp-admin.
3. Copy API key.
4. Enable ACP/UCP protocols as needed.
5. Call API endpoints with your API key.

## Request Examples

Use local fixtures as examples:
- ACP fixtures: [`fixtures/acp/`](/Users/home/Local Sites/stagingaa/app/public/wp-content/plugins/ucp-adapter-for-woocommerce/fixtures/acp)
- UCP fixtures: [`fixtures/ucp/`](/Users/home/Local Sites/stagingaa/app/public/wp-content/plugins/ucp-adapter-for-woocommerce/fixtures/ucp)

Smoke test runner:
- [`scripts/smoke-test.sh`](/Users/home/Local Sites/stagingaa/app/public/wp-content/plugins/ucp-adapter-for-woocommerce/scripts/smoke-test.sh)

Run:

```bash
BASE_URL="http://127.0.0.1:10003" \
API_KEY="your_api_key" \
PRODUCT_ID=123 \
bash scripts/smoke-test.sh
```

## Test Results

Current local validation summary is tracked here:
- [`TEST_RESULTS.md`](/Users/home/Local Sites/stagingaa/app/public/wp-content/plugins/ucp-adapter-for-woocommerce/TEST_RESULTS.md)

## Plain-Language and UX Standard

This README and admin UX are intentionally written using plain-language principles inspired by:
- [PlainLanguage.gov](https://www.plainlanguage.gov/)
- [Digital.gov](https://digital.gov/)

Goals:
- short, task-first instructions
- predictable sectioning
- minimal jargon
- quick scan for setup and troubleshooting

## SEO Keywords

UCP WooCommerce plugin, ACP WooCommerce adapter, OpenAI Agentic Commerce Protocol WooCommerce, WooCommerce checkout session API, WooCommerce agentic commerce integration, UCP connector.
