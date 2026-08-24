# Contributing to UCP Connector for WooCommerce

Thank you for your interest in contributing.

## Getting Started

### Prerequisites

- WordPress 6.9+
- WooCommerce (latest stable)
- PHP 8.0+
- A local WordPress development environment (LocalWP, wp-env, or similar)

### Setup

1. Clone the repository into your WordPress plugins directory:
   ```bash
   git clone https://github.com/SilentJMA/ucp-connector-for-woocommerce.git \
     /path/to/wordpress/wp-content/plugins/ucp-adapter-for-woocommerce
   ```
2. Activate the plugin in WordPress admin.
3. Generate an API key from **UCP Connector > Security**.

### Running Tests

Run the smoke test suite against a live WordPress instance:

```bash
BASE_URL="https://your-site.test" \
API_KEY="your_api_key" \
PRODUCT_ID=123 \
bash scripts/smoke-test.sh
```

## How to Contribute

### Reporting Bugs

Use the [bug report template](.github/ISSUE_TEMPLATE/bug_report.md) to file an issue. Include:

- WordPress and WooCommerce versions
- PHP version
- Steps to reproduce
- Expected vs actual behavior

### Suggesting Features

Use the [feature request template](.github/ISSUE_TEMPLATE/feature_request.md).

### Pull Requests

1. Fork the repository and create a feature branch from `main`.
2. Keep changes focused — one concern per PR.
3. Ensure `php -l` passes on all PHP files.
4. Update `readme.txt` changelog if your change is user-facing.
5. Fill out the PR template checklist.

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) for PHP.
- Use WordPress sanitization and escaping functions for all user input and output.
- Prefix all functions, classes, and options with `ucp_adapter_` or `UCP_Adapter_`.

## License

By contributing, you agree that your contributions will be licensed under the [UCP Connector Non-Commercial License v1.0](LICENSE).
