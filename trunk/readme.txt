=== UCP Connector for Woocommerce ===
Contributors: mohamedayoubjabane
Tags: api, rest, commerce, ucp, acp, woocommerce
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.3.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce adapter for UCP and OpenAI Agentic Commerce Protocol (ACP) checkout sessions.

== Description ==

This plugin exposes a protocol-aware commerce API on top of WooCommerce.

= What it supports =

* ACP checkout session flow
* UCP legacy compatibility routes
* Product search and order lookup
* Shared session model with merchant-authoritative recalculation
* Capability negotiation with platform profile capabilities
* API key auth via Bearer, `X-UCP-API-Key`, or `X-ACP-API-Key`
* Rate limiting and IP allowlist

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

Shared
* `GET /product/search`
* `GET /orders/{order_id}`
* `GET /sessions`

== Installation ==

1. Upload to `/wp-content/plugins/ucp-adapter-for-woocommerce/`
2. Activate plugin
3. Open **UCP Connector for Woocommerce** in wp-admin
4. Copy API key and configure store metadata/policy links
5. Call endpoints with the API key

== Changelog ==

= 1.3.0 =
* Added agent security model controls.
* Added `UCP-Agent` domain allowlist support with wildcard and known-platform fallback.
* Added optional `Request-Signature` detached JWS verification against agent profile signing keys.
* Improved admin settings UI with clearer security-focused sections.

= 1.2.0 =
* Restructured plugin files into admin/api/core include groups.
* Rebranded plugin as UCP Connector for Woocommerce.
* Added billing/shipping address coverage in order responses.
* Updated smoke fixtures and script to assert order addresses.

= 1.1.0 =
* Added ACP checkout session endpoints.
* Added capabilities endpoint for UCP and ACP namespaces.
* Added capability negotiation and structured payload validation.
* Reworked storage around normalized checkout-session model.
* Added order lookup endpoint and improved product search.
* Added admin settings for protocol toggles and merchant links.
* Added working API key regeneration AJAX handler.
* Improved authentication and request hardening.

= 1.0.3 =
* Previous UCP-only release.
