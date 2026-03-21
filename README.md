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

## Security Model

## How to Restrict Access to Authorized AI Agents

The plugin provides multiple layers of security.

### 1. Domain Whitelist (Recommended)

Enable **Agent Whitelist** and configure allowed domains:

```text
api.openai.com
generativelanguage.googleapis.com
api.anthropic.com
api.cohere.ai
```

Agents must send a `UCP-Agent` header with their profile URL:

```text
UCP-Agent: UCP/2026-01-11 profile="https://api.openai.com/.well-known/ucp"
```

The plugin extracts the domain and checks it against the whitelist.

### 2. Request Signature Verification (High Security)

Enable **Require Agent Signature** for cryptographic verification:

1. Agent signs the request body with its private key.
2. Agent sends signature in `Request-Signature` header (detached JWS).
3. Plugin fetches agent public keys from `/.well-known/ucp`.
4. Plugin verifies the signature matches the request body.

This provides:
- Only authorized agents can call endpoints.
- Requests cannot be tampered with in transit.
- Non-repudiation for signed requests.

### 3. Known AI Platforms (Default Whitelist)

If whitelist is enabled but no domains are configured, these are allowed by default:

| Platform | Domain |
| --- | --- |
| OpenAI | `api.openai.com` |
| Google Gemini | `generativelanguage.googleapis.com` |
| Anthropic | `api.anthropic.com` |
| Cohere | `api.cohere.ai` |
| Mistral | `api.mistral.ai` |
| Amazon Bedrock | `inference.aws.amazon.com` |
| Together AI | `api.together.xyz` |
| Perplexity | `api.perplexity.ai` |

### Security Configuration Examples

Development (Open Access):
- Agent Whitelist: Disabled
- Require Signature: Disabled

Production (Whitelist Only):
- Agent Whitelist: Enabled
- Whitelisted Domains: `api.openai.com`, `api.anthropic.com`
- Require Signature: Disabled

High Security (Whitelist + Signatures):
- Agent Whitelist: Enabled
- Whitelisted Domains: `api.openai.com`
- Require Signature: Enabled

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

- Current plugin version: `1.0.0`
