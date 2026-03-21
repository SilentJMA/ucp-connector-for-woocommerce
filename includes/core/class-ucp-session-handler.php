<?php

/**
 * UCP Session Handler
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Stores normalized commerce sessions for both UCP and ACP clients.
 */
class UCP_Adapter_Session_Handler
{

	/**
	 * Single instance.
	 *
	 * @var UCP_Adapter_Session_Handler|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return UCP_Adapter_Session_Handler
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct()
	{
		if (! wp_next_scheduled('ucp_adapter_cleanup_sessions')) {
			wp_schedule_event(time(), 'hourly', 'ucp_adapter_cleanup_sessions');
		}

		add_action('ucp_adapter_cleanup_sessions', array(__CLASS__, 'cleanup_expired_sessions'));
	}

	/**
	 * Create a checkout session.
	 *
	 * @param array $args Session bootstrap data.
	 * @return string|WP_Error
	 */
	public function create_session($args = array())
	{
		global $wpdb;

		$protocol   = isset($args['protocol']) ? sanitize_key($args['protocol']) : 'ucp';
		$protocol   = in_array($protocol, array('ucp', 'acp'), true) ? $protocol : 'ucp';
		$session_id = $this->generate_session_id($protocol);
		$timeout    = max(60, (int) get_option('ucp_adapter_session_timeout', 3600));
		$expires    = time() + $timeout;
		$payload    = $this->normalize_session_payload($args, $session_id, $expires);
		$payload    = $this->recalculate_checkout($payload);

		$table_name = $wpdb->prefix . 'ucp_adapter_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'session_id'   => $session_id,
				'session_data' => wp_json_encode($payload),
				'expires'      => $expires,
			),
			array('%s', '%s', '%d')
		);

		if (false === $inserted) {
			return new WP_Error(
				'ucp_session_create_failed',
				__('Failed to create checkout session.', 'ucp-adapter-for-woocommerce'),
				array('status' => 500)
			);
		}

		$this->flush_cache($session_id);

		return $session_id;
	}

	/**
	 * Fetch a session.
	 *
	 * @param string $session_id Session id.
	 * @return array|WP_Error
	 */
	public function get_session($session_id)
	{
		$cache_key = 'ucp_adapter_session_' . $session_id;
		$cached    = wp_cache_get($cache_key, 'ucp_adapter');

		if (false !== $cached) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ucp_adapter_sessions WHERE session_id = %s AND expires > %d",
				$session_id,
				time()
			),
			ARRAY_A
		);

		if (! $row) {
			return new WP_Error(
				'ucp_session_not_found',
				__('Checkout session not found or expired.', 'ucp-adapter-for-woocommerce'),
				array('status' => 404)
			);
		}

		$payload = json_decode($row['session_data'], true);
		$payload = is_array($payload) ? $payload : array();
		$payload = $this->normalize_session_payload($payload, $row['session_id'], (int) $row['expires']);

		$session = array(
			'session_id' => $row['session_id'],
			'expires'    => (int) $row['expires'],
			'created_at' => $row['created_at'],
			'updated_at' => $row['updated_at'],
			'data'       => $payload,
		);

		wp_cache_set($cache_key, $session, 'ucp_adapter', 300);

		return $session;
	}

	/**
	 * Apply partial updates and recalculate session payload.
	 *
	 * @param string $session_id Session id.
	 * @param array  $changes Payload changes.
	 * @return array|WP_Error
	 */
	public function update_session($session_id, $changes)
	{
		global $wpdb;

		$session = $this->get_session($session_id);

		if (is_wp_error($session)) {
			return $session;
		}

		$payload = $this->merge_assoc($session['data'], $changes);
		$payload = $this->recalculate_checkout($payload);

		$table_name = $wpdb->prefix . 'ucp_adapter_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table_name,
			array(
				'session_data' => wp_json_encode($payload),
				'expires'      => (int) $payload['expires_at'],
			),
			array('session_id' => $session_id),
			array('%s', '%d'),
			array('%s')
		);

		if (false === $result) {
			return new WP_Error(
				'ucp_session_update_failed',
				__('Failed to update checkout session.', 'ucp-adapter-for-woocommerce'),
				array('status' => 500)
			);
		}

		$this->flush_cache($session_id);

		return $this->get_session($session_id);
	}

	/**
	 * Complete a session.
	 *
	 * @param string $session_id Session id.
	 * @param array  $completion_data Completion payload.
	 * @return array|WP_Error
	 */
	public function complete_session($session_id, $completion_data)
	{
		$completion = array(
			'status'       => 'completed',
			'completed_at' => current_time('mysql'),
			'expires_at'   => time() + WEEK_IN_SECONDS,
		);

		$completion = $this->merge_assoc($completion, $completion_data);

		return $this->update_session($session_id, $completion);
	}

	/**
	 * Cancel a session.
	 *
	 * @param string $session_id Session id.
	 * @param array  $cancel_data Cancel payload.
	 * @return array|WP_Error
	 */
	public function cancel_session($session_id, $cancel_data = array())
	{
		$changes = array(
			'status'       => 'canceled',
			'completed_at' => current_time('mysql'),
			'metadata'     => array(
				'cancellation' => $cancel_data,
			),
		);

		return $this->update_session($session_id, $changes);
	}

	/**
	 * Delete a session.
	 *
	 * @param string $session_id Session id.
	 * @return bool
	 */
	public function delete_session($session_id)
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'ucp_adapter_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$table_name,
			array('session_id' => $session_id),
			array('%s')
		);

		if (false !== $result) {
			$this->flush_cache($session_id);
		}

		return false !== $result;
	}

	/**
	 * Get recent sessions.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_recent_sessions($limit = 50)
	{
		$cache_key = 'ucp_adapter_recent_sessions';
		$cached    = wp_cache_get($cache_key, 'ucp_adapter');

		if (false !== $cached) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ucp_adapter_sessions ORDER BY created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$sessions = array();
		foreach ($rows as $row) {
			$payload    = json_decode($row['session_data'], true);
			$sessions[] = array(
				'session_id' => $row['session_id'],
				'expires'    => (int) $row['expires'],
				'created_at' => $row['created_at'],
				'updated_at' => $row['updated_at'],
				'data'       => is_array($payload) ? $payload : array(),
			);
		}

		wp_cache_set($cache_key, $sessions, 'ucp_adapter', 300);

		return $sessions;
	}

	/**
	 * Paginated sessions.
	 *
	 * @param int $page Page number.
	 * @param int $limit Per page.
	 * @return array
	 */
	public function get_sessions($page = 1, $limit = 10)
	{
		global $wpdb;

		$page   = max(1, (int) $page);
		$limit  = max(1, min(100, (int) $limit));
		$offset = ($page - 1) * $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ucp_adapter_sessions ORDER BY created_at DESC LIMIT %d, %d",
				$offset,
				$limit
			),
			ARRAY_A
		);

		$sessions = array();
		foreach ($rows as $row) {
			$payload    = json_decode($row['session_data'], true);
			$sessions[] = array(
				'session_id' => $row['session_id'],
				'expires'    => (int) $row['expires'],
				'created_at' => $row['created_at'],
				'updated_at' => $row['updated_at'],
				'data'       => is_array($payload) ? $payload : array(),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ucp_adapter_sessions");

		return array(
			'sessions' => $sessions,
			'total'    => $total,
		);
	}

	/**
	 * Cleanup expired rows.
	 *
	 * @return void
	 */
	public static function cleanup_expired_sessions()
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}ucp_adapter_sessions WHERE expires < %d",
				time()
			)
		);

		wp_cache_delete('ucp_adapter_recent_sessions', 'ucp_adapter');
	}

	/**
	 * Normalize session payload.
	 *
	 * @param array  $payload Input payload.
	 * @param string $session_id Session id.
	 * @param int    $expires Expiration timestamp.
	 * @return array
	 */
	private function normalize_session_payload($payload, $session_id, $expires)
	{
		$defaults = array(
			'id'                    => $session_id,
			'protocol'              => 'ucp',
			'status'                => 'not_ready_for_payment',
			'currency'              => $this->get_store_currency(),
			'buyer'                 => array(),
			'line_items'            => array(),
			'fulfillment_address'   => array(),
			'fulfillment_options'   => array(),
			'fulfillment_option_id' => '',
			'totals'                => array(
				'item_subtotal' => '0.00',
				'shipping'      => '0.00',
				'tax'           => '0.00',
				'discounts'     => '0.00',
				'total'         => '0.00',
			),
			'messages'              => array(),
			'links'                 => $this->get_policy_links(),
			'payment_provider'      => array(
				'mode' => (bool) get_option('ucp_adapter_enable_delegated_payment', 0) ? 'delegated_optional' : 'merchant',
			),
			'order'                 => array(),
			'metadata'              => array(),
			'capabilities'          => $this->default_capabilities(),
			'created_at'            => current_time('mysql'),
			'updated_at'            => current_time('mysql'),
			'completed_at'          => null,
			'expires_at'            => $expires,
		);

		$merged                     = $this->merge_assoc($defaults, is_array($payload) ? $payload : array());
		$merged['id']               = $session_id;
		$merged['protocol']         = in_array($merged['protocol'], array('ucp', 'acp'), true) ? $merged['protocol'] : 'ucp';
		$merged['currency']         = ! empty($merged['currency']) ? $merged['currency'] : $this->get_store_currency();
		$merged['line_items']       = is_array($merged['line_items']) ? array_values($merged['line_items']) : array();
		$merged['buyer']            = is_array($merged['buyer']) ? $merged['buyer'] : array();
		$merged['fulfillment_address'] = is_array($merged['fulfillment_address']) ? $merged['fulfillment_address'] : array();
		$merged['metadata']         = is_array($merged['metadata']) ? $merged['metadata'] : array();
		$merged['capabilities']     = is_array($merged['capabilities']) ? array_values($merged['capabilities']) : $this->default_capabilities();
		$merged['messages']         = is_array($merged['messages']) ? array_values($merged['messages']) : array();
		$merged['links']            = is_array($merged['links']) ? $merged['links'] : $this->get_policy_links();
		$merged['fulfillment_options'] = is_array($merged['fulfillment_options']) ? array_values($merged['fulfillment_options']) : array();
		$merged['totals']           = is_array($merged['totals']) ? $merged['totals'] : $defaults['totals'];
		$merged['expires_at']       = (int) $merged['expires_at'];

		return $merged;
	}

	/**
	 * Recalculate pricing, fulfillment and status.
	 *
	 * @param array $payload Session payload.
	 * @return array
	 */
	private function recalculate_checkout($payload)
	{
		$payload              = $this->normalize_session_payload($payload, $payload['id'], (int) $payload['expires_at']);
		$payload['updated_at'] = current_time('mysql');
		$messages             = array();
		$line_items           = array();

		$item_subtotal = 0.0;
		$tax_total     = 0.0;
		$discounts     = 0.0;

		foreach ($payload['line_items'] as $item) {
			$product_id   = isset($item['product_id']) ? absint($item['product_id']) : 0;
			$variation_id = isset($item['variation_id']) ? absint($item['variation_id']) : 0;
			$quantity     = isset($item['quantity']) ? max(1, (int) $item['quantity']) : 1;
			$product      = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);

			if (! $product || ! $product->exists()) {
				$messages[] = array(
					'type'     => 'error',
					'code'     => 'product_not_found',
					'path'     => '$.line_items',
					'content'  => sprintf(__('Product %d is not available.', 'ucp-adapter-for-woocommerce'), $product_id),
					'severity' => 'recoverable',
				);
				continue;
			}

			if (! $product->is_purchasable() || ! $product->is_in_stock()) {
				$messages[] = array(
					'type'     => 'error',
					'code'     => 'out_of_stock',
					'path'     => '$.line_items',
					'content'  => sprintf(__('Product %s is not purchasable.', 'ucp-adapter-for-woocommerce'), $product->get_name()),
					'severity' => 'recoverable',
				);
				continue;
			}

			$base_unit  = (float) wc_get_price_excluding_tax($product, array('qty' => 1));
			$total_unit = (float) wc_get_price_including_tax($product, array('qty' => 1));
			$line_base  = $base_unit * $quantity;
			$line_total = $total_unit * $quantity;
			$line_tax   = max(0, $line_total - $line_base);

			$item_subtotal += $line_base;
			$tax_total     += $line_tax;

			$line_items[] = array(
				'item_id'        => (string) $product->get_id(),
				'product_id'     => $product_id,
				'variation_id'   => $variation_id,
				'sku'            => $product->get_sku(),
				'title'          => $product->get_name(),
				'quantity'       => $quantity,
				'base_amount'    => wc_format_decimal($base_unit, 2),
				'discount_amount'=> wc_format_decimal(0, 2),
				'subtotal_amount'=> wc_format_decimal($line_base, 2),
				'tax_amount'     => wc_format_decimal($line_tax, 2),
				'total_amount'   => wc_format_decimal($line_total, 2),
			);
		}

		$payload['line_items']          = $line_items;
		$buyer_email                    = isset($payload['buyer']['email']) ? sanitize_email($payload['buyer']['email']) : '';
		if (empty($line_items)) {
			$messages[] = array(
				'type'     => 'error',
				'code'     => 'missing',
				'path'     => '$.line_items',
				'content'  => __('At least one line item is required.', 'ucp-adapter-for-woocommerce'),
				'severity' => 'recoverable',
			);
		}
		if (empty($buyer_email)) {
			$messages[] = array(
				'type'     => 'error',
				'code'     => 'missing',
				'path'     => '$.buyer.email',
				'content'  => __('Buyer email is required.', 'ucp-adapter-for-woocommerce'),
				'severity' => 'recoverable',
			);
		}

		$payload['messages']            = $messages;
		$payload['fulfillment_options'] = $this->build_fulfillment_options($payload['currency']);
		$payload                        = $this->ensure_fulfillment_option($payload);
		if (empty($payload['fulfillment_option_id'])) {
			$payload['messages'][] = array(
				'type'     => 'error',
				'code'     => 'missing',
				'path'     => '$.fulfillment_option_id',
				'content'  => __('Fulfillment option is required.', 'ucp-adapter-for-woocommerce'),
				'severity' => 'recoverable',
			);
		}
		$shipping_cost                  = $this->selected_shipping_cost($payload);
		$total                          = $item_subtotal + $tax_total + $shipping_cost - $discounts;

		$payload['totals'] = array(
			'item_subtotal' => wc_format_decimal($item_subtotal, 2),
			'shipping'      => wc_format_decimal($shipping_cost, 2),
			'tax'           => wc_format_decimal($tax_total, 2),
			'discounts'     => wc_format_decimal($discounts, 2),
			'total'         => wc_format_decimal(max(0, $total), 2),
		);

		if (! in_array($payload['status'], array('completed', 'canceled'), true)) {
			$payload['status'] = $this->resolve_checkout_status($payload);
		}

		return $payload;
	}

	/**
	 * Resolve checkout readiness status.
	 *
	 * @param array $payload Checkout session payload.
	 * @return string
	 */
	private function resolve_checkout_status($payload)
	{
		if (empty($payload['line_items'])) {
			return 'not_ready_for_payment';
		}

		$buyer_email = isset($payload['buyer']['email']) ? sanitize_email($payload['buyer']['email']) : '';
		if (empty($buyer_email)) {
			return 'not_ready_for_payment';
		}

		if (empty($payload['fulfillment_option_id'])) {
			return 'not_ready_for_payment';
		}

		return empty($payload['messages']) ? 'ready_for_payment' : 'not_ready_for_payment';
	}

	/**
	 * Merge associative arrays recursively.
	 *
	 * @param array $base Base payload.
	 * @param array $changes Changes payload.
	 * @return array
	 */
	private function merge_assoc($base, $changes)
	{
		if (! is_array($changes)) {
			return $base;
		}

		foreach ($changes as $key => $value) {
			if (is_array($value) && isset($base[ $key ]) && is_array($base[ $key ]) && $this->is_assoc($value)) {
				$base[ $key ] = $this->merge_assoc($base[ $key ], $value);
				continue;
			}

			$base[ $key ] = $value;
		}

		return $base;
	}

	/**
	 * Check whether an array is associative.
	 *
	 * @param array $array Array.
	 * @return bool
	 */
	private function is_assoc($array)
	{
		if (empty($array)) {
			return false;
		}

		return array_keys($array) !== range(0, count($array) - 1);
	}

	/**
	 * Build fulfillment options from enabled shipping methods.
	 *
	 * @param string $currency Currency code.
	 * @return array
	 */
	private function build_fulfillment_options($currency)
	{
		$options = array(
			array(
				'id'          => 'standard',
				'label'       => __('Standard shipping', 'ucp-adapter-for-woocommerce'),
				'amount'      => wc_format_decimal(0, 2),
				'currency'    => $currency,
				'description' => __('Default shipping option.', 'ucp-adapter-for-woocommerce'),
			),
		);

		if (class_exists('WC_Shipping') && function_exists('WC')) {
			$methods = WC()->shipping() ? WC()->shipping()->get_shipping_methods() : array();
			foreach ($methods as $method_id => $method) {
				if ('yes' !== $method->enabled) {
					continue;
				}

				$options[] = array(
					'id'          => sanitize_key($method_id),
					'label'       => $method->get_method_title(),
					'amount'      => wc_format_decimal(0, 2),
					'currency'    => $currency,
					'description' => $method->get_method_description(),
				);
			}
		}

		return array_values(array_unique($options, SORT_REGULAR));
	}

	/**
	 * Ensure selected fulfillment option is valid.
	 *
	 * @param array $payload Payload.
	 * @return array
	 */
	private function ensure_fulfillment_option($payload)
	{
		$selected = isset($payload['fulfillment_option_id']) ? sanitize_key($payload['fulfillment_option_id']) : '';
		$valid    = false;

		foreach ($payload['fulfillment_options'] as $option) {
			if (! empty($option['id']) && $selected === sanitize_key($option['id'])) {
				$valid = true;
				break;
			}
		}

		if (! $valid) {
			$payload['fulfillment_option_id'] = isset($payload['fulfillment_options'][0]['id']) ? sanitize_key($payload['fulfillment_options'][0]['id']) : '';
		}

		return $payload;
	}

	/**
	 * Resolve selected shipping cost.
	 *
	 * @param array $payload Payload.
	 * @return float
	 */
	private function selected_shipping_cost($payload)
	{
		$selected = sanitize_key($payload['fulfillment_option_id']);
		foreach ($payload['fulfillment_options'] as $option) {
			if (sanitize_key($option['id']) !== $selected) {
				continue;
			}

			return isset($option['amount']) ? (float) $option['amount'] : 0.0;
		}

		return 0.0;
	}

	/**
	 * Generate unique session id.
	 *
	 * @param string $protocol Protocol.
	 * @return string
	 */
	private function generate_session_id($protocol)
	{
		return sanitize_key($protocol) . '_' . bin2hex(random_bytes(16)) . '_' . time();
	}

	/**
	 * Flush cache keys.
	 *
	 * @param string $session_id Session id.
	 * @return void
	 */
	private function flush_cache($session_id = '')
	{
		wp_cache_delete('ucp_adapter_recent_sessions', 'ucp_adapter');

		if ('' !== $session_id) {
			wp_cache_delete('ucp_adapter_session_' . $session_id, 'ucp_adapter');
		}
	}

	/**
	 * Resolve store currency.
	 *
	 * @return string
	 */
	private function get_store_currency()
	{
		if (function_exists('get_woocommerce_currency')) {
			return get_woocommerce_currency();
		}

		return 'USD';
	}

	/**
	 * Merchant links shown in ACP payloads.
	 *
	 * @return array
	 */
	private function get_policy_links()
	{
		return array(
			'terms'         => esc_url_raw(get_option('ucp_adapter_terms_url', '')),
			'privacy'       => esc_url_raw(get_option('ucp_adapter_privacy_url', '')),
			'refund_policy' => esc_url_raw(get_option('ucp_adapter_refund_url', '')),
			'checkout'      => esc_url_raw(get_option('ucp_adapter_checkout_return_url', '')),
		);
	}

	/**
	 * Default advertised capabilities.
	 *
	 * @return array
	 */
	private function default_capabilities()
	{
		return array(
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
		);
	}
}
