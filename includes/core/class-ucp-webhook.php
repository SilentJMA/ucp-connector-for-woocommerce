<?php

/**
 * UCP Webhook Dispatcher
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Sends HMAC-SHA256 signed webhook notifications on session lifecycle events.
 */
class UCP_Adapter_Webhook
{
	/**
	 * Bootstrap webhook listeners.
	 *
	 * @return void
	 */
	public static function init()
	{
		add_action('ucp_adapter_session_created', array(__CLASS__, 'on_session_created'));
		add_action('ucp_adapter_session_completed', array(__CLASS__, 'on_session_completed'), 10, 2);
		add_action('ucp_adapter_session_canceled', array(__CLASS__, 'on_session_canceled'));
	}

	/**
	 * Fired when a checkout session is created.
	 *
	 * @param array $session Session data.
	 * @return void
	 */
	public static function on_session_created($session)
	{
		self::dispatch('session.created', self::session_payload($session));
	}

	/**
	 * Fired when a checkout session is completed.
	 *
	 * @param array    $session Session data.
	 * @param WC_Order $order   WooCommerce order.
	 * @return void
	 */
	public static function on_session_completed($session, $order)
	{
		$payload = self::session_payload($session);
		$payload['order_id'] = $order->get_id();
		$payload['order_total'] = (string) $order->get_total();
		self::dispatch('session.completed', $payload);
	}

	/**
	 * Fired when a checkout session is canceled.
	 *
	 * @param array $session Session data.
	 * @return void
	 */
	public static function on_session_canceled($session)
	{
		self::dispatch('session.canceled', self::session_payload($session));
	}

	/**
	 * Build a minimal session payload for the webhook body.
	 *
	 * @param array $session Session row.
	 * @return array
	 */
	private static function session_payload($session)
	{
		$data = isset($session['data']) && is_array($session['data']) ? $session['data'] : array();
		return array(
			'session_id' => isset($session['session_id']) ? $session['session_id'] : '',
			'protocol'   => isset($data['protocol']) ? $data['protocol'] : 'ucp',
			'status'     => isset($data['status']) ? $data['status'] : '',
			'total'      => isset($data['totals']['total']) ? $data['totals']['total'] : '0.00',
			'currency'   => isset($data['currency']) ? $data['currency'] : 'USD',
		);
	}

	/**
	 * Send a signed webhook POST to the configured URL.
	 *
	 * @param string $event   Event name.
	 * @param array  $payload Event data.
	 * @return void
	 */
	private static function dispatch($event, $payload)
	{
		$url = trim((string) get_option('ucp_adapter_webhook_url', ''));
		if ('' === $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
			return;
		}

		$secret = (string) get_option('ucp_adapter_webhook_secret', '');
		$timestamp = time();

		$body = wp_json_encode(
			array(
				'event'     => $event,
				'timestamp' => $timestamp,
				'data'      => $payload,
			)
		);

		$headers = array(
			'Content-Type'    => 'application/json',
			'X-UCP-Event'     => $event,
			'X-UCP-Timestamp' => (string) $timestamp,
		);

		if ('' !== $secret) {
			$signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
			$headers['X-UCP-Signature'] = 'sha256=' . $signature;
		}

		wp_remote_post(
			$url,
			array(
				'timeout'  => 10,
				'blocking' => false,
				'headers'  => $headers,
				'body'     => $body,
			)
		);
	}
}
