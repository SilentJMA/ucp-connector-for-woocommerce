<?php

/**
 * UCP REST API Handler
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * REST endpoints for ACP and UCP.
 */
class UCP_Adapter_REST_API
{

	/**
	 * UCP namespace.
	 *
	 * @var string
	 */
	const UCP_NAMESPACE = 'ucp/v1';

	/**
	 * ACP namespace.
	 *
	 * @var string
	 */
	const ACP_NAMESPACE = 'acp/v1';

	/**
	 * Capability version date.
	 *
	 * @var string
	 */
	const CAPABILITY_VERSION = '2026-01-11';

	/**
	 * Supported business capabilities.
	 *
	 * @var array
	 */
	const BUSINESS_CAPABILITIES = array(
		array(
			'name'    => 'dev.ucp.shopping.checkout',
			'version' => self::CAPABILITY_VERSION,
			'spec'    => 'https://ucp.dev/specification/checkout',
			'schema'  => 'https://ucp.dev/schemas/shopping/checkout.json',
		),
		array(
			'name'    => 'dev.ucp.shopping.fulfillment',
			'version' => self::CAPABILITY_VERSION,
			'spec'    => 'https://ucp.dev/specification/fulfillment',
			'schema'  => 'https://ucp.dev/schemas/shopping/fulfillment.json',
			'extends' => 'dev.ucp.shopping.checkout',
		),
	);

	/**
	 * Register all routes.
	 *
	 * @return void
	 */
	public static function register_routes()
	{
		self::register_capability_routes();
		self::register_checkout_routes(self::ACP_NAMESPACE);
		self::register_checkout_routes(self::UCP_NAMESPACE);
		self::register_legacy_ucp_routes();
		self::register_common_routes();
	}

	/**
	 * Shared capabilities routes.
	 *
	 * @return void
	 */
	private static function register_capability_routes()
	{
		foreach (array(self::UCP_NAMESPACE, self::ACP_NAMESPACE) as $namespace) {
			register_rest_route(
				$namespace,
				'/capabilities',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(__CLASS__, 'handle_capabilities'),
					'permission_callback' => array(__CLASS__, 'check_api_permission'),
				)
			);
		}
	}

	/**
	 * Register checkout routes for a namespace.
	 *
	 * @param string $namespace Namespace.
	 * @return void
	 */
	private static function register_checkout_routes($namespace)
	{
		register_rest_route(
			$namespace,
			'/checkout_sessions',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'handle_create_checkout_session'),
				'permission_callback' => array(__CLASS__, 'check_api_permission'),
			)
		);

		register_rest_route(
			$namespace,
			'/checkout_sessions/(?P<session_id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(__CLASS__, 'handle_get_checkout_session'),
					'permission_callback' => array(__CLASS__, 'check_api_permission'),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array(__CLASS__, 'handle_update_checkout_session'),
					'permission_callback' => array(__CLASS__, 'check_api_permission'),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array(__CLASS__, 'handle_patch_checkout_session'),
					'permission_callback' => array(__CLASS__, 'check_api_permission'),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/checkout_sessions/(?P<session_id>[a-zA-Z0-9_-]+)/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'handle_complete_checkout_session'),
				'permission_callback' => array(__CLASS__, 'check_api_permission'),
			)
		);

		register_rest_route(
			$namespace,
			'/checkout_sessions/(?P<session_id>[a-zA-Z0-9_-]+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'handle_cancel_checkout_session'),
				'permission_callback' => array(__CLASS__, 'check_api_permission'),
			)
		);
	}

	/**
	 * Register legacy UCP endpoints.
	 *
	 * @return void
	 */
	private static function register_legacy_ucp_routes()
	{
		register_rest_route(
			self::UCP_NAMESPACE,
			'/session',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'handle_legacy_session_create'),
				'permission_callback' => array(__CLASS__, 'check_api_permission'),
			)
		);

		register_rest_route(
			self::UCP_NAMESPACE,
			'/update/(?P<session_id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array(__CLASS__, 'handle_legacy_update'),
				'permission_callback' => array(__CLASS__, 'check_api_permission'),
			)
		);

		register_rest_route(
			self::UCP_NAMESPACE,
			'/status/(?P<session_id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array(__CLASS__, 'handle_legacy_status'),
				'permission_callback' => array(__CLASS__, 'check_api_permission'),
			)
		);

		register_rest_route(
			self::UCP_NAMESPACE,
			'/complete/(?P<session_id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array(__CLASS__, 'handle_legacy_complete'),
				'permission_callback' => array(__CLASS__, 'check_api_permission'),
			)
		);
	}

	/**
	 * Routes shared by both protocols.
	 *
	 * @return void
	 */
	private static function register_common_routes()
	{
		foreach (array(self::UCP_NAMESPACE, self::ACP_NAMESPACE) as $namespace) {
			register_rest_route(
				$namespace,
				'/product/search',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(__CLASS__, 'handle_product_search'),
					'permission_callback' => array(__CLASS__, 'check_api_permission'),
				)
			);

			register_rest_route(
				$namespace,
				'/orders/(?P<order_id>[0-9]+)',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(__CLASS__, 'handle_order_lookup'),
					'permission_callback' => array(__CLASS__, 'check_api_permission'),
				)
			);

			register_rest_route(
				$namespace,
				'/sessions',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array(__CLASS__, 'handle_list_sessions'),
					'permission_callback' => array(__CLASS__, 'check_api_permission'),
				)
			);
		}
	}

	/**
	 * Permission callback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function check_api_permission($request)
	{
		static $permission_cache = array();
		$cache_key = md5(
			(string) $request->get_method() . '|' .
			(string) $request->get_route() . '|' .
			(string) $request->get_header('Authorization') . '|' .
			(string) $request->get_header('X-UCP-API-Key') . '|' .
			(string) $request->get_header('X-ACP-API-Key') . '|' .
			(string) $request->get_header('UCP-Agent') . '|' .
			(string) $request->get_param('api_key')
		);
		if (array_key_exists($cache_key, $permission_cache)) {
			return $permission_cache[ $cache_key ];
		}

		$route = (string) $request->get_route();
		if (false !== strpos($route, '/acp/') && ! (bool) get_option('ucp_adapter_protocol_acp_enabled', 1)) {
			$permission_cache[ $cache_key ] = new WP_Error(
				'ucp_adapter_acp_disabled',
				__('ACP protocol is disabled.', 'ucp-adapter-for-woocommerce'),
				array('status' => 403)
			);
			return $permission_cache[ $cache_key ];
		}
		if (false !== strpos($route, '/ucp/') && ! (bool) get_option('ucp_adapter_protocol_ucp_enabled', 1)) {
			$permission_cache[ $cache_key ] = new WP_Error(
				'ucp_adapter_ucp_disabled',
				__('UCP protocol is disabled.', 'ucp-adapter-for-woocommerce'),
				array('status' => 403)
			);
			return $permission_cache[ $cache_key ];
		}

		$auth = UCP_Adapter_Security::authenticate_request($request);
		if (is_wp_error($auth)) {
			$permission_cache[ $cache_key ] = $auth;
			return $auth;
		}

		$verified = UCP_Adapter_Security::verify_request($request, $auth['identity']);
		if (is_wp_error($verified)) {
			$permission_cache[ $cache_key ] = $verified;
			return $verified;
		}

		$permission_cache[ $cache_key ] = true;
		return true;
	}

	/**
	 * Capabilities endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_capabilities($request)
	{
		$namespace = $request->get_route();
		$is_acp    = false !== strpos($namespace, '/acp/');
		$active    = self::negotiate_capabilities(array());

		$data = array(
			'supported_protocols' => array('ucp', 'acp'),
			'default_protocol'    => $is_acp ? 'acp' : 'ucp',
			'capabilities'        => $active,
			'auth'                => array(
				'bearer'          => true,
				'x_ucp_api_key'   => true,
				'x_acp_api_key'   => true,
				'query_api_key'   => true,
				'ucp_agent'       => (bool) get_option('ucp_adapter_agent_whitelist_enabled', 0),
				'request_signature' => (bool) get_option('ucp_adapter_require_agent_signature', 0),
			),
			'payment_mode'        => (bool) get_option('ucp_adapter_enable_delegated_payment', 0) ? 'delegated_optional' : 'merchant',
			'store'               => array(
				'name'          => get_option('ucp_adapter_store_name', get_bloginfo('name')),
				'currency'      => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
				'locale'        => get_locale(),
				'checkout_url'  => esc_url_raw(get_option('ucp_adapter_checkout_return_url', '')),
				'terms_url'     => esc_url_raw(get_option('ucp_adapter_terms_url', '')),
				'privacy_url'   => esc_url_raw(get_option('ucp_adapter_privacy_url', '')),
				'refund_url'    => esc_url_raw(get_option('ucp_adapter_refund_url', '')),
			),
			'endpoints'           => array(
				'create_checkout_session' => rest_url(($is_acp ? self::ACP_NAMESPACE : self::UCP_NAMESPACE) . '/checkout_sessions'),
				'get_checkout_session'    => rest_url(($is_acp ? self::ACP_NAMESPACE : self::UCP_NAMESPACE) . '/checkout_sessions/{id}'),
				'complete_checkout'       => rest_url(($is_acp ? self::ACP_NAMESPACE : self::UCP_NAMESPACE) . '/checkout_sessions/{id}/complete'),
				'cancel_checkout'         => rest_url(($is_acp ? self::ACP_NAMESPACE : self::UCP_NAMESPACE) . '/checkout_sessions/{id}/cancel'),
				'orders'                  => rest_url(($is_acp ? self::ACP_NAMESPACE : self::UCP_NAMESPACE) . '/orders/{order_id}'),
			),
		);

		return new WP_REST_Response($data, 200);
	}

	/**
	 * ACP/UCP create checkout session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_create_checkout_session($request)
	{
		$body = $request->get_json_params();
		$body = is_array($body) ? $body : array();
		$validation = self::validate_checkout_payload('create', $body);
		if (is_wp_error($validation)) {
			return $validation;
		}

		$protocol = false !== strpos($request->get_route(), '/acp/') ? 'acp' : 'ucp';
		$active_capabilities = self::negotiate_capabilities(
			isset($body['platform_profile']) && is_array($body['platform_profile']) ? $body['platform_profile'] : array()
		);
		$args     = array(
			'protocol'              => $protocol,
			'buyer'                 => isset($body['buyer']) && is_array($body['buyer']) ? $body['buyer'] : array(),
			'line_items'            => isset($body['line_items']) && is_array($body['line_items']) ? $body['line_items'] : array(),
			'fulfillment_address'   => isset($body['fulfillment_address']) && is_array($body['fulfillment_address']) ? $body['fulfillment_address'] : array(),
			'fulfillment_option_id' => isset($body['fulfillment_option_id']) ? sanitize_key($body['fulfillment_option_id']) : '',
			'metadata'              => isset($body['metadata']) && is_array($body['metadata']) ? $body['metadata'] : array(),
			'capabilities'          => $active_capabilities,
		);

		$session_handler = UCP_Adapter_Session_Handler::get_instance();
		$session_id      = $session_handler->create_session($args);

		if (is_wp_error($session_id)) {
			return $session_id;
		}

		$session = $session_handler->get_session($session_id);
		if (is_wp_error($session)) {
			return $session;
		}

		return new WP_REST_Response(self::format_checkout_session_response($session), 201);
	}

	/**
	 * Fetch checkout session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_get_checkout_session($request)
	{
		$session = UCP_Adapter_Session_Handler::get_instance()->get_session($request->get_param('session_id'));
		if (is_wp_error($session)) {
			return $session;
		}

		return new WP_REST_Response(self::format_checkout_session_response($session), 200);
	}

	/**
	 * Update checkout session via POST.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_update_checkout_session($request)
	{
		return self::apply_checkout_patch($request);
	}

	/**
	 * Update checkout session via PATCH/PUT.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_patch_checkout_session($request)
	{
		return self::apply_checkout_patch($request);
	}

	/**
	 * Complete checkout.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_complete_checkout_session($request)
	{
		$session_handler = UCP_Adapter_Session_Handler::get_instance();
		$session         = $session_handler->get_session($request->get_param('session_id'));
		if (is_wp_error($session)) {
			return $session;
		}

		$current = $session['data'];
		if ('completed' === $current['status']) {
			return new WP_Error(
				'ucp_adapter_already_completed',
				__('Checkout session is already completed.', 'ucp-adapter-for-woocommerce'),
				array('status' => 409)
			);
		}
		if ('canceled' === $current['status']) {
			return new WP_Error(
				'ucp_adapter_canceled_session',
				__('Canceled sessions cannot be completed.', 'ucp-adapter-for-woocommerce'),
				array('status' => 405)
			);
		}
		if ('ready_for_payment' !== $current['status']) {
			return new WP_Error(
				'ucp_adapter_not_ready',
				__('Checkout session is not ready for payment.', 'ucp-adapter-for-woocommerce'),
				array('status' => 422)
			);
		}

		$body      = $request->get_json_params();
		$body      = is_array($body) ? $body : array();
		$order     = self::create_wc_order_from_session($current, $body);
		if (is_wp_error($order)) {
			return $order;
		}

		$completion_data = array(
			'status'   => 'completed',
			'order'    => array(
				'order_id'      => $order->get_id(),
				'number'        => $order->get_order_number(),
				'status'        => $order->get_status(),
				'total'         => (string) $order->get_total(),
				'currency'      => $order->get_currency(),
				'payment_method'=> $order->get_payment_method(),
				'billing_address' => $order->get_address('billing'),
				'shipping_address' => $order->get_address('shipping'),
			),
			'metadata' => array(
				'completion' => array(
					'delegated_payment_supported' => (bool) get_option('ucp_adapter_enable_delegated_payment', 0),
					'payment_payload_received'    => ! empty($body['payment']),
				),
			),
		);

		$updated = $session_handler->complete_session($request->get_param('session_id'), $completion_data);
		if (is_wp_error($updated)) {
			return $updated;
		}

		return new WP_REST_Response(self::format_checkout_session_response($updated), 200);
	}

	/**
	 * Cancel checkout session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_cancel_checkout_session($request)
	{
		$session_handler = UCP_Adapter_Session_Handler::get_instance();
		$session         = $session_handler->get_session($request->get_param('session_id'));
		if (is_wp_error($session)) {
			return $session;
		}

		if ('completed' === $session['data']['status']) {
			return new WP_Error(
				'ucp_adapter_completed_session',
				__('Completed sessions cannot be canceled.', 'ucp-adapter-for-woocommerce'),
				array('status' => 405)
			);
		}

		$body    = $request->get_json_params();
		$body    = is_array($body) ? $body : array();
		$updated = $session_handler->cancel_session($request->get_param('session_id'), $body);
		if (is_wp_error($updated)) {
			return $updated;
		}

		return new WP_REST_Response(self::format_checkout_session_response($updated), 200);
	}

	/**
	 * Legacy UCP create session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_legacy_session_create($request)
	{
		$body  = $request->get_json_params();
		$body  = is_array($body) ? $body : array();
		$buyer = isset($body['user_data']) && is_array($body['user_data']) ? $body['user_data'] : array();
		$args  = array(
			'protocol'    => 'ucp',
			'buyer'       => $buyer,
			'line_items'  => isset($body['line_items']) && is_array($body['line_items']) ? $body['line_items'] : array(),
			'metadata'    => isset($body['metadata']) && is_array($body['metadata']) ? $body['metadata'] : array(),
		);

		$session_handler = UCP_Adapter_Session_Handler::get_instance();
		$session_id      = $session_handler->create_session($args);
		if (is_wp_error($session_id)) {
			return $session_id;
		}

		$session = $session_handler->get_session($session_id);
		if (is_wp_error($session)) {
			return $session;
		}

		$data = self::format_checkout_session_response($session);

		return new WP_REST_Response(
			array(
				'success'    => true,
				'session_id' => $data['id'],
				'expires_at' => $data['expires_at'],
				'status'     => $data['status'],
				'message'    => __('Session created successfully.', 'ucp-adapter-for-woocommerce'),
			),
			201
		);
	}

	/**
	 * Legacy UCP update adapter.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_legacy_update($request)
	{
		$session_id = $request->get_param('session_id');
		$action     = sanitize_key((string) $request->get_param('action'));
		$data       = $request->get_param('data');
		$data       = is_array($data) ? $data : array();

		$session = UCP_Adapter_Session_Handler::get_instance()->get_session($session_id);
		if (is_wp_error($session)) {
			return $session;
		}

		$changes = self::map_legacy_action($action, $data, $session['data']);
		if (is_wp_error($changes)) {
			return $changes;
		}

		$updated = UCP_Adapter_Session_Handler::get_instance()->update_session($session_id, $changes);
		if (is_wp_error($updated)) {
			return $updated;
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'session_id' => $session_id,
				'action'     => $action,
				'data'       => $updated['data'],
				'message'    => __('Session updated successfully.', 'ucp-adapter-for-woocommerce'),
			),
			200
		);
	}

	/**
	 * Legacy status adapter.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_legacy_status($request)
	{
		$session = UCP_Adapter_Session_Handler::get_instance()->get_session($request->get_param('session_id'));
		if (is_wp_error($session)) {
			return $session;
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'session_id' => $session['session_id'],
				'status'     => $session['data']['status'],
				'created_at' => $session['data']['created_at'],
				'expires_at' => $session['data']['expires_at'],
				'data'       => $session['data'],
			),
			200
		);
	}

	/**
	 * Legacy complete adapter.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_legacy_complete($request)
	{
		return self::handle_complete_checkout_session($request);
	}

	/**
	 * Product search endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_product_search($request)
	{
		$search = sanitize_text_field((string) $request->get_param('search'));
		$page   = max(1, absint($request->get_param('page')));
		$limit  = max(1, min(100, absint($request->get_param('limit'))));

		$products = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => $limit,
				'page'    => $page,
				's'       => $search,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		$results = array();
		foreach ($products as $product) {
			$image_id = $product->get_image_id();
			$image    = $image_id ? wp_get_attachment_url($image_id) : wc_placeholder_img_src();

			$results[] = array(
				'id'                => $product->get_id(),
				'name'              => $product->get_name(),
				'sku'               => $product->get_sku(),
				'type'              => $product->get_type(),
				'price'             => $product->get_price(),
				'regular_price'     => $product->get_regular_price(),
				'sale_price'        => $product->get_sale_price(),
				'currency'          => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
				'permalink'         => $product->get_permalink(),
				'image'             => $image,
				'short_description' => wp_strip_all_tags($product->get_short_description()),
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $results,
				'meta'    => array(
					'page'   => $page,
					'limit'  => $limit,
					'search' => $search,
					'count'  => count($results),
				),
			),
			200
		);
	}

	/**
	 * Order lookup endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_order_lookup($request)
	{
		$order = wc_get_order(absint($request->get_param('order_id')));
		if (! $order) {
			return new WP_Error(
				'ucp_adapter_order_not_found',
				__('Order not found.', 'ucp-adapter-for-woocommerce'),
				array('status' => 404)
			);
		}

		$response = array(
			'order_id'          => $order->get_id(),
			'order_number'      => $order->get_order_number(),
			'status'            => $order->get_status(),
			'currency'          => $order->get_currency(),
			'total'             => (string) $order->get_total(),
			'subtotal'          => (string) $order->get_subtotal(),
			'total_tax'         => (string) $order->get_total_tax(),
			'total_shipping'    => (string) $order->get_shipping_total(),
			'payment_method'    => $order->get_payment_method(),
			'payment_method_title' => $order->get_payment_method_title(),
			'date_created'      => $order->get_date_created() ? $order->get_date_created()->date(DATE_ATOM) : null,
			'date_paid'         => $order->get_date_paid() ? $order->get_date_paid()->date(DATE_ATOM) : null,
			'date_completed'    => $order->get_date_completed() ? $order->get_date_completed()->date(DATE_ATOM) : null,
			'billing_address'   => $order->get_address('billing'),
			'shipping_address'  => $order->get_address('shipping'),
			'line_items'        => array(),
		);

		foreach ($order->get_items() as $item) {
			$response['line_items'][] = array(
				'product_id' => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'subtotal'   => (string) $item->get_subtotal(),
				'total'      => (string) $item->get_total(),
			);
		}

		return new WP_REST_Response($response, 200);
	}

	/**
	 * List sessions endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_list_sessions($request)
	{
		$page   = max(1, absint($request->get_param('page')));
		$limit  = max(1, min(100, absint($request->get_param('limit'))));
		$result = UCP_Adapter_Session_Handler::get_instance()->get_sessions($page, $limit);

		$rows = array();
		foreach ($result['sessions'] as $session) {
			$rows[] = array(
				'id'         => $session['session_id'],
				'status'     => isset($session['data']['status']) ? $session['data']['status'] : 'not_ready_for_payment',
				'protocol'   => isset($session['data']['protocol']) ? $session['data']['protocol'] : 'ucp',
				'created_at' => isset($session['data']['created_at']) ? $session['data']['created_at'] : $session['created_at'],
				'expires_at' => isset($session['data']['expires_at']) ? $session['data']['expires_at'] : $session['expires'],
				'total'      => isset($session['data']['totals']['total']) ? $session['data']['totals']['total'] : '0.00',
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $rows,
				'meta'    => array(
					'page'  => $page,
					'limit' => $limit,
					'total' => $result['total'],
				),
			),
			200
		);
	}

	/**
	 * Apply patch updates to checkout session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function apply_checkout_patch($request)
	{
		$session_id = $request->get_param('session_id');
		$body       = $request->get_json_params();
		$body       = is_array($body) ? $body : array();
		$validation = self::validate_checkout_payload('update', $body);
		if (is_wp_error($validation)) {
			return $validation;
		}

		$allowed_keys = array(
			'buyer',
			'line_items',
			'fulfillment_address',
			'fulfillment_option_id',
			'metadata',
		);

		$changes = array();
		foreach ($allowed_keys as $key) {
			if (array_key_exists($key, $body)) {
				$changes[ $key ] = $body[ $key ];
			}
		}

		if (isset($body['apply_coupon']) && function_exists('wc_format_coupon_code')) {
			$changes['metadata']['coupon_code'] = wc_format_coupon_code($body['apply_coupon']);
		}

		$updated = UCP_Adapter_Session_Handler::get_instance()->update_session($session_id, $changes);
		if (is_wp_error($updated)) {
			return $updated;
		}

		return new WP_REST_Response(self::format_checkout_session_response($updated), 200);
	}

	/**
	 * Map old update actions.
	 *
	 * @param string $action Action.
	 * @param array  $data Payload.
	 * @return array|WP_Error
	 */
	private static function map_legacy_action($action, $data, $current)
	{
		$current_items = isset($current['line_items']) && is_array($current['line_items']) ? $current['line_items'] : array();

		switch ($action) {
			case 'add_item':
				$item = array(
					'product_id'   => isset($data['product_id']) ? absint($data['product_id']) : 0,
					'variation_id' => isset($data['variation_id']) ? absint($data['variation_id']) : 0,
					'quantity'     => isset($data['quantity']) ? max(1, (int) $data['quantity']) : 1,
				);
				$current_items[] = $item;
				return array('line_items' => $current_items);

			case 'update_item':
				$index = isset($data['index']) ? absint($data['index']) : -1;
				if (! isset($current_items[ $index ])) {
					return new WP_Error(
						'ucp_adapter_item_not_found',
						__('Legacy item index not found.', 'ucp-adapter-for-woocommerce'),
						array('status' => 404)
					);
				}
				$current_items[ $index ] = array_merge($current_items[ $index ], $data);
				return array('line_items' => array_values($current_items));

			case 'remove_item':
				$index = isset($data['index']) ? absint($data['index']) : -1;
				if (isset($current_items[ $index ])) {
					unset($current_items[ $index ]);
				}
				return array('line_items' => array_values($current_items));

			case 'set_data':
				return $data;

			case 'set_buyer':
				return array('buyer' => $data);

			case 'set_shipping_address':
				return array('fulfillment_address' => $data);

			case 'set_metadata':
				return array('metadata' => $data);

			default:
				return new WP_Error(
					'ucp_adapter_invalid_action',
					__('Unsupported legacy action.', 'ucp-adapter-for-woocommerce'),
					array('status' => 400)
				);
		}
	}

	/**
	 * Build response object.
	 *
	 * @param array $session Session row.
	 * @return array
	 */
	private static function format_checkout_session_response($session)
	{
		$data = $session['data'];
		return array(
			'id'                    => $session['session_id'],
			'protocol'              => $data['protocol'],
			'status'                => $data['status'],
			'currency'              => $data['currency'],
			'buyer'                 => $data['buyer'],
			'line_items'            => $data['line_items'],
			'fulfillment_address'   => $data['fulfillment_address'],
			'fulfillment_options'   => $data['fulfillment_options'],
			'fulfillment_option_id' => $data['fulfillment_option_id'],
			'totals'                => $data['totals'],
			'messages'              => $data['messages'],
			'links'                 => $data['links'],
			'payment_provider'      => $data['payment_provider'],
			'order'                 => $data['order'],
			'metadata'              => $data['metadata'],
			'created_at'            => $data['created_at'],
			'updated_at'            => $data['updated_at'],
			'completed_at'          => $data['completed_at'],
			'expires_at'            => $data['expires_at'],
			'continue_url'          => self::build_continue_url($session['session_id']),
			'ucp'                   => array(
				'version'      => self::CAPABILITY_VERSION,
				'capabilities' => isset($data['capabilities']) && is_array($data['capabilities']) ? $data['capabilities'] : self::BUSINESS_CAPABILITIES,
			),
		);
	}

	/**
	 * Negotiate active capabilities with a platform profile.
	 *
	 * @param array $platform_profile Platform profile payload.
	 * @return array
	 */
	private static function negotiate_capabilities($platform_profile)
	{
		$platform_caps = array();
		if (isset($platform_profile['ucp']['capabilities']) && is_array($platform_profile['ucp']['capabilities'])) {
			$platform_caps = $platform_profile['ucp']['capabilities'];
		}

		if (empty($platform_caps)) {
			return self::BUSINESS_CAPABILITIES;
		}

		$intersection = array();
		foreach (self::BUSINESS_CAPABILITIES as $business_cap) {
			foreach ($platform_caps as $platform_cap) {
				$platform_name = isset($platform_cap['name']) ? (string) $platform_cap['name'] : '';
				if ($platform_name !== $business_cap['name']) {
					continue;
				}

				$platform_version = isset($platform_cap['version']) ? (string) $platform_cap['version'] : self::CAPABILITY_VERSION;
				$business_version = isset($business_cap['version']) ? (string) $business_cap['version'] : self::CAPABILITY_VERSION;
				if (! self::is_capability_version_compatible($platform_version, $business_version)) {
					continue;
				}

				$intersection[] = $business_cap;
				break;
			}
		}

		if (empty($intersection)) {
			return array(self::BUSINESS_CAPABILITIES[0]);
		}

		$filtered = $intersection;
		$changed  = true;
		while ($changed) {
			$changed = false;
			$names   = array_map(
				static function ($capability) {
					return isset($capability['name']) ? $capability['name'] : '';
				},
				$filtered
			);
			$next = array();
			foreach ($filtered as $capability) {
				if (empty($capability['extends']) || in_array($capability['extends'], $names, true)) {
					$next[] = $capability;
				} else {
					$changed = true;
				}
			}
			$filtered = $next;
		}

		return array_values($filtered);
	}

	/**
	 * Version compatibility rule: platform <= business.
	 *
	 * @param string $platform_version Platform version.
	 * @param string $business_version Business version.
	 * @return bool
	 */
	private static function is_capability_version_compatible($platform_version, $business_version)
	{
		$platform_ts = strtotime($platform_version);
		$business_ts = strtotime($business_version);

		return false !== $platform_ts && false !== $business_ts && $platform_ts <= $business_ts;
	}

	/**
	 * Validate request payload by operation.
	 *
	 * @param string $operation Operation.
	 * @param array  $payload Payload.
	 * @return true|WP_Error
	 */
	private static function validate_checkout_payload($operation, $payload)
	{
		$errors = array();
		$line_items_present = array_key_exists('line_items', $payload);

		if ('create' === $operation && (! $line_items_present || ! is_array($payload['line_items']))) {
			$errors[] = array(
				'property' => 'line_items',
				'message'  => 'line_items is required and must be an array',
			);
		}

		if ($line_items_present && ! is_array($payload['line_items'])) {
			$errors[] = array(
				'property' => 'line_items',
				'message'  => 'line_items must be an array',
			);
		}

		if ($line_items_present && is_array($payload['line_items'])) {
			foreach ($payload['line_items'] as $index => $line_item) {
				$product_id = isset($line_item['product_id']) ? absint($line_item['product_id']) : 0;
				$quantity   = isset($line_item['quantity']) ? (int) $line_item['quantity'] : 0;
				if ($product_id <= 0) {
					$errors[] = array(
						'property' => "line_items[{$index}].product_id",
						'message'  => 'product_id must be a positive integer',
					);
				}
				if ($quantity < 1) {
					$errors[] = array(
						'property' => "line_items[{$index}].quantity",
						'message'  => 'quantity must be a positive integer',
					);
				}
			}
		}

		if (isset($payload['buyer']) && ! is_array($payload['buyer'])) {
			$errors[] = array(
				'property' => 'buyer',
				'message'  => 'buyer must be an object',
			);
		}

		if (isset($payload['buyer']['email']) && ! is_email($payload['buyer']['email'])) {
			$errors[] = array(
				'property' => 'buyer.email',
				'message'  => 'buyer.email must be a valid email address',
			);
		}

		if (empty($errors)) {
			return true;
		}

		return new WP_Error(
			'ucp_adapter_validation_failed',
			__('Validation failed.', 'ucp-adapter-for-woocommerce'),
			array(
				'status'  => 400,
				'errors'  => $errors,
			)
		);
	}

	/**
	 * Build continue URL for hosted checkout handoff.
	 *
	 * @param string $session_id Session id.
	 * @return string
	 */
	private static function build_continue_url($session_id)
	{
		$base = (string) get_option('ucp_adapter_checkout_return_url', '');
		$base = '' !== $base ? $base : home_url('/checkout/');

		return add_query_arg(
			array(
				'ucp_session' => $session_id,
			),
			$base
		);
	}

	/**
	 * Convert checkout session into WooCommerce order.
	 *
	 * @param array $session_data Session payload.
	 * @param array $body Request body.
	 * @return WC_Order|WP_Error
	 */
	private static function create_wc_order_from_session($session_data, $body)
	{
		try {
			$order = wc_create_order();
		} catch (Exception $e) {
			return new WP_Error(
				'ucp_adapter_order_create_failed',
				$e->getMessage(),
				array('status' => 500)
			);
		}

		foreach ($session_data['line_items'] as $line) {
			$product_id   = isset($line['product_id']) ? absint($line['product_id']) : 0;
			$variation_id = isset($line['variation_id']) ? absint($line['variation_id']) : 0;
			$quantity     = isset($line['quantity']) ? max(1, (int) $line['quantity']) : 1;

			$product = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);
			if (! $product) {
				continue;
			}

			$order->add_product($product, $quantity);
		}

		$buyer = isset($body['buyer']) && is_array($body['buyer']) ? $body['buyer'] : $session_data['buyer'];
		$fulfillment_addr = isset($body['fulfillment_address']) && is_array($body['fulfillment_address']) ? $body['fulfillment_address'] : $session_data['fulfillment_address'];
		$billing_source   = isset($body['billing_address']) && is_array($body['billing_address']) ? $body['billing_address'] : $fulfillment_addr;
		$shipping_source  = isset($body['shipping_address']) && is_array($body['shipping_address']) ? $body['shipping_address'] : $fulfillment_addr;

		$billing = array(
			'first_name' => isset($buyer['first_name']) ? sanitize_text_field($buyer['first_name']) : '',
			'last_name'  => isset($buyer['last_name']) ? sanitize_text_field($buyer['last_name']) : '',
			'email'      => isset($buyer['email']) ? sanitize_email($buyer['email']) : '',
			'phone'      => isset($buyer['phone']) ? sanitize_text_field($buyer['phone']) : '',
			'address_1'  => isset($billing_source['address_1']) ? sanitize_text_field($billing_source['address_1']) : '',
			'address_2'  => isset($billing_source['address_2']) ? sanitize_text_field($billing_source['address_2']) : '',
			'city'       => isset($billing_source['city']) ? sanitize_text_field($billing_source['city']) : '',
			'state'      => isset($billing_source['state']) ? sanitize_text_field($billing_source['state']) : '',
			'postcode'   => isset($billing_source['postcode']) ? sanitize_text_field($billing_source['postcode']) : '',
			'country'    => isset($billing_source['country']) ? sanitize_text_field($billing_source['country']) : '',
		);

		$shipping = array(
			'first_name' => isset($buyer['first_name']) ? sanitize_text_field($buyer['first_name']) : '',
			'last_name'  => isset($buyer['last_name']) ? sanitize_text_field($buyer['last_name']) : '',
			'address_1'  => isset($shipping_source['address_1']) ? sanitize_text_field($shipping_source['address_1']) : '',
			'address_2'  => isset($shipping_source['address_2']) ? sanitize_text_field($shipping_source['address_2']) : '',
			'city'       => isset($shipping_source['city']) ? sanitize_text_field($shipping_source['city']) : '',
			'state'      => isset($shipping_source['state']) ? sanitize_text_field($shipping_source['state']) : '',
			'postcode'   => isset($shipping_source['postcode']) ? sanitize_text_field($shipping_source['postcode']) : '',
			'country'    => isset($shipping_source['country']) ? sanitize_text_field($shipping_source['country']) : '',
		);

		$order->set_address($billing, 'billing');
		$order->set_address($shipping, 'shipping');
		$order->calculate_totals(true);

		if (! empty($body['payment_method'])) {
			$order->set_payment_method(sanitize_text_field($body['payment_method']));
		}

		$order->update_status('processing');
		$order->save();

		return $order;
	}
}
