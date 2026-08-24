=== UCP Connector for Woocommerce ===
Contributors: mohamedayoubjabane
Tags: api, rest, commerce, ucp, acp, woocommerce
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.0
Requires Plugins: woocommerce
License: UCP Connector Non-Commercial License v1.0
License URI: https://github.com/SilentJMA/ucp-connector-for-woocommerce/blob/main/LICENSE

WooCommerce adapter for UCP and OpenAI Agentic Commerce Protocol (ACP) checkout sessions.

== Description ==

This plugin exposes a protocol-aware commerce API on top of WooCommerce.

= What it supports =

* `/.well-known/ucp` discovery endpoint for AI agent auto-discovery
* ACP checkout session flow (OpenAI Agentic Commerce Protocol)
* UCP legacy compatibility routes (Universal Commerce Protocol)
* Product catalog: search with filters, single product detail, categories
* Product search filtering by category, price range, stock status with pagination
* Stock quantity validation during checkout recalculation
* WooCommerce coupon/discount application on checkout sessions
* Shared session model with merchant-authoritative recalculation
* Capability negotiation with platform profile capabilities
* Idempotency key support for preventing duplicate session creation
* HMAC-SHA256 signed webhook notifications on session lifecycle events
* Agent identity tracking on checkout sessions
* CORS headers for cross-origin agent requests
* Health check endpoint
* API key auth via Bearer, `X-UCP-API-Key`, or `X-ACP-API-Key`
* Rate limiting and IP allowlist

= Discovery =

AI agents discover your store by fetching `https://your-site.com/.well-known/ucp` (no authentication required).

= Core Endpoints =

ACP (`/wp-json/acp/v1`)
* `POST /checkout_sessions`
* `POST /checkout_sessions/{id}`
* `GET /checkout_sessions/{id}`
* `POST /checkout_sessions/{id}/complete`
* `POST /checkout_sessions/{id}/cancel`
* `GET /capabilities`

UCP (`/wp-json/ucp/v1`)
* `POST /session`
* `PUT /update/{id}`
* `GET /status/{id}`
* `POST /complete/{id}`
* `GET /capabilities`

Catalog & Common (available on both namespaces)
* `GET /health`
* `GET /products/{id}`
* `GET /products?search=&category=&min_price=&max_price=&in_stock=1`
* `GET /product/search`
* `GET /categories`
* `GET /orders/{order_id}`
* `GET /sessions`

== Installation ==

1. Upload to `/wp-content/plugins/ucp-adapter-for-woocommerce/`
2. Activate plugin
3. Open **UCP Connector for Woocommerce** in wp-admin
4. Copy API key and configure store metadata/policy links
5. Call endpoints with the API key

== Changelog ==

= 1.1.0 =
* Added `/.well-known/ucp` discovery endpoint for AI agent auto-discovery.
* Added product catalog API: single product detail with variations, product categories, and enhanced search filters (category, price range, stock status, sorting, pagination with total count).
* Added stock quantity validation during checkout recalculation — prevents over-selling.
* Added WooCommerce coupon/discount support: validates and applies percent, fixed_cart, and fixed_product coupons.
* Added `Idempotency-Key` header support to prevent duplicate session creation.
* Added HMAC-SHA256 signed webhook notifications for session.created, session.completed, and session.canceled events.
* Added agent identity tracking from UCP-Agent header stored in session metadata.
* Added CORS headers with configurable allowed origins for cross-origin agent requests.
* Added health check endpoint (`GET /health`) — no authentication required.
* Added webhook configuration to admin Security page (URL + signing secret).
* Added CORS allowed origins setting to admin Security page.
* Added discovery endpoint and health endpoint URLs to admin Overview page.
* Updated API Docs page with new catalog, discovery, idempotency, and webhook documentation.

= 1.0.4 =
* Reworked admin information architecture with dedicated pages: Overview, Checkout Sessions, Configuration, Security, and API Docs.
* Added unified in-page navigation across all plugin screens.
* Separated configuration controls from security controls for clearer operations.

= 1.0.3 =
* Fixed rate-limit double count bug.
* Improved request hardening.

= 1.0.2 =
* Adopted UCP Connector Non-Commercial License v1.0.
* Restructured plugin files into admin/api/core include groups.
* Added billing/shipping address coverage in order responses.
* Updated smoke fixtures and script to assert order addresses.

= 1.0.1 =
* Improved admin UI with modern layout, hero section, and cleaner settings presentation.
* Fixed admin page notice/layout conflicts by isolating WordPress heading and notice area.
* Made checkout sessions table responsive and added safe truncation for long session IDs.

= 1.0.0 =
* Official initial release.
* Unified ACP and UCP checkout session APIs on a normalized WooCommerce model.
* Added ACP checkout session endpoints and capabilities endpoint.
* Added capability negotiation with platform profile capabilities.
* Added agent authorization controls with `UCP-Agent` domain allowlist and wildcard matching.
* Added optional `Request-Signature` detached JWS verification against agent profile signing keys.
* Added product search, order lookup, and session listing endpoints.
* Added API key auth via Bearer, `X-UCP-API-Key`, or `X-ACP-API-Key`.
* Added rate limiting and IP allowlist.
* Added admin settings for protocol toggles and merchant links.
* Added local fixtures and smoke test runner for ACP/UCP flow validation.
