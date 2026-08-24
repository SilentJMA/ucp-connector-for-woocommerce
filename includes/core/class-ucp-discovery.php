<?php

/**
 * UCP Discovery Endpoint
 *
 * Serves the /.well-known/ucp manifest for agent auto-discovery.
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Registers and serves the /.well-known/ucp discovery manifest.
 */
class UCP_Adapter_Discovery
{
	/**
	 * Query variable name.
	 *
	 * @var string
	 */
	private const QUERY_VAR = 'ucp_wellknown';

	/**
	 * Bootstrap rewrite rules and request handler.
	 *
	 * @return void
	 */
	public static function init()
	{
		add_action('init', array(__CLASS__, 'register_rewrite_rule'));
		add_filter('query_vars', array(__CLASS__, 'register_query_var'));
		add_action('template_redirect', array(__CLASS__, 'serve_manifest'));
	}

	/**
	 * Register the rewrite rule.
	 *
	 * @return void
	 */
	public static function register_rewrite_rule()
	{
		add_rewrite_rule(
			'^\.well-known/ucp/?$',
			'index.php?' . self::QUERY_VAR . '=1',
			'top'
		);
	}

	/**
	 * Allow the query variable through WordPress.
	 *
	 * @param array $vars Allowed query vars.
	 * @return array
	 */
	public static function register_query_var($vars)
	{
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Intercept the request and serve the UCP manifest JSON.
	 *
	 * @return void
	 */
	public static function serve_manifest()
	{
		if (! get_query_var(self::QUERY_VAR)) {
			return;
		}

		$ucp_enabled = (bool) get_option('ucp_adapter_protocol_ucp_enabled', 1);
		$acp_enabled = (bool) get_option('ucp_adapter_protocol_acp_enabled', 1);

		$protocols = array();
		if ($ucp_enabled) {
			$protocols[] = 'ucp';
		}
		if ($acp_enabled) {
			$protocols[] = 'acp';
		}

		$namespace = $ucp_enabled ? 'ucp/v1' : 'acp/v1';

		$currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
		$store_name = get_option('ucp_adapter_store_name', get_bloginfo('name'));
		$site_icon_id = (int) get_option('site_icon');
		$logo_url = $site_icon_id ? wp_get_attachment_url($site_icon_id) : '';

		$auth_methods = array('bearer', 'x-ucp-api-key', 'x-acp-api-key');
		$agent_whitelist = (bool) get_option('ucp_adapter_agent_whitelist_enabled', 0);
		$signature_required = (bool) get_option('ucp_adapter_require_agent_signature', 0);

		$delegated = (bool) get_option('ucp_adapter_enable_delegated_payment', 0);

		$manifest = array(
			'ucp_version'    => '2026-01-11',
			'merchant'       => array(
				'name'     => $store_name,
				'url'      => home_url('/'),
				'currency' => $currency,
				'locale'   => get_locale(),
			),
			'protocols'      => $protocols,
			'capabilities'   => array(
				array(
					'name'    => 'dev.ucp.shopping.checkout',
					'version' => '2026-01-11',
					'spec'    => 'https://ucp.dev/specification/checkout',
					'schema'  => 'https://ucp.dev/schemas/shopping/checkout.json',
				),
				array(
					'name'    => 'dev.ucp.shopping.fulfillment',
					'version' => '2026-01-11',
					'spec'    => 'https://ucp.dev/specification/fulfillment',
					'schema'  => 'https://ucp.dev/schemas/shopping/fulfillment.json',
					'extends' => 'dev.ucp.shopping.checkout',
				),
			),
			'endpoints'      => array(
				'checkout_sessions' => rest_url($namespace . '/checkout_sessions'),
				'products'          => rest_url($namespace . '/products'),
				'product_search'    => rest_url($namespace . '/product/search'),
				'categories'        => rest_url($namespace . '/categories'),
				'capabilities'      => rest_url($namespace . '/capabilities'),
				'health'            => rest_url($namespace . '/health'),
			),
			'authentication' => array(
				'type'               => 'api_key',
				'methods'            => $auth_methods,
				'agent_verification' => $agent_whitelist,
				'signature_required' => $signature_required,
			),
			'payment'        => array(
				'mode'    => $delegated ? 'delegated_optional' : 'merchant',
			),
			'links'          => array(
				'terms'         => esc_url_raw(get_option('ucp_adapter_terms_url', '')),
				'privacy'       => esc_url_raw(get_option('ucp_adapter_privacy_url', '')),
				'refund_policy' => esc_url_raw(get_option('ucp_adapter_refund_url', '')),
				'checkout'      => esc_url_raw(get_option('ucp_adapter_checkout_return_url', '')),
			),
		);

		if ('' !== $logo_url) {
			$manifest['merchant']['logo'] = esc_url_raw($logo_url);
		}

		$manifest = apply_filters('ucp_adapter_discovery_manifest', $manifest);

		nocache_headers();
		header('Content-Type: application/json; charset=utf-8');
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: GET, OPTIONS');
		echo wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		exit;
	}

	/**
	 * Flush rewrite rules on activation.
	 *
	 * @return void
	 */
	public static function flush_rules()
	{
		self::register_rewrite_rule();
		flush_rewrite_rules();
	}
}
