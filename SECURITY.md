# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 1.0.x   | Yes       |
| < 1.0   | No        |

## Reporting a Vulnerability

If you discover a security vulnerability in UCP Connector for WooCommerce, please report it responsibly.

**Do not open a public GitHub issue for security vulnerabilities.**

Instead, please email **kilwanitro@gmail.com** with:

1. A description of the vulnerability
2. Steps to reproduce the issue
3. The potential impact
4. Any suggested fix (optional)

You will receive an acknowledgment within 48 hours. We aim to provide a fix or mitigation within 7 days for critical issues.

## Security Features

This plugin includes several security controls:

- **API key authentication** via `Authorization: Bearer`, `X-UCP-API-Key`, or `X-ACP-API-Key` headers
- **IP allowlist** to restrict access by client IP address
- **Rate limiting** by IP and identity
- **UCP-Agent domain allowlist** with wildcard matching
- **Request signature verification** via detached JWS (ES256) against agent profile signing keys

## Best Practices

When deploying this plugin:

1. Use HTTPS exclusively
2. Enable the agent domain allowlist in production
3. Consider enabling request signature verification for high-security deployments
4. Rotate your API key periodically
5. Configure rate limiting appropriate to your traffic
6. Review the IP allowlist if your deployment is behind a reverse proxy
