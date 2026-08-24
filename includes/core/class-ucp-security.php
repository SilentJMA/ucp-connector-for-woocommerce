<?php

/**
 * UCP Security Handler
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Auth and request hardening helpers.
 */
class UCP_Adapter_Security
{
	/**
	 * Known trusted AI domains used as default whitelist fallback.
	 *
	 * @var string[]
	 */
	private const KNOWN_AGENT_DOMAINS = array(
		'api.openai.com',
		'generativelanguage.googleapis.com',
		'api.anthropic.com',
		'api.cohere.ai',
		'api.mistral.ai',
		'inference.aws.amazon.com',
		'api.together.xyz',
		'api.perplexity.ai',
	);

	/**
	 * Authenticate request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function authenticate_request($request)
	{
		$stored_key = (string) get_option('ucp_adapter_api_key', '');
		if ('' === $stored_key) {
			return new WP_Error(
				'ucp_adapter_missing_api_key',
				__('API key is not configured.', 'ucp-adapter-for-woocommerce'),
				array('status' => 500)
			);
		}

		$candidates = array(
			$request->get_header('Authorization'),
			$request->get_header('X-UCP-API-Key'),
			$request->get_header('X-ACP-API-Key'),
			$request->get_param('api_key'),
		);

		$provided = '';
		$source   = '';

		foreach ($candidates as $candidate_index => $candidate) {
			if (empty($candidate)) {
				continue;
			}

			$value = (string) $candidate;
			if (0 === $candidate_index && 0 === stripos($value, 'Bearer ')) {
				$value  = trim(substr($value, 7));
				$source = 'bearer';
			} elseif (1 === $candidate_index) {
				$source = 'x-ucp-api-key';
			} elseif (2 === $candidate_index) {
				$source = 'x-acp-api-key';
			} else {
				$source = 'query';
			}

			if ('' !== trim($value)) {
				$provided = trim($value);
				if ('query' === $source) {
					_doing_it_wrong(
						__METHOD__,
						__('Passing the API key as a query parameter is deprecated and will be removed in a future version. Use the Authorization, X-UCP-API-Key, or X-ACP-API-Key header instead.', 'ucp-adapter-for-woocommerce'),
						'1.0.5'
					);
				}
				break;
			}
		}

		if ('' === $provided || ! hash_equals($stored_key, $provided)) {
			return new WP_Error(
				'ucp_adapter_unauthorized',
				__('Invalid or missing API key.', 'ucp-adapter-for-woocommerce'),
				array('status' => 401)
			);
		}

		return array(
			'source'   => $source,
			'identity' => md5($provided),
		);
	}

	/**
	 * Verify request authenticity and policy checks.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $identity Auth identity.
	 * @return true|WP_Error
	 */
	public static function verify_request($request, $identity = '')
	{
		if (! self::check_ip_whitelist()) {
			return new WP_Error(
				'ucp_adapter_ip_forbidden',
				__('Requester IP is not allowed.', 'ucp-adapter-for-woocommerce'),
				array('status' => 403)
			);
		}

		if (! self::check_rate_limit($identity)) {
			return new WP_Error(
				'ucp_adapter_rate_limited',
				__('Rate limit exceeded.', 'ucp-adapter-for-woocommerce'),
				array('status' => 429)
			);
		}

		$agent_authorization = self::verify_agent_authorization($request);
		if (is_wp_error($agent_authorization)) {
			return $agent_authorization;
		}

		$passed = apply_filters('ucp_adapter_verify_request', true, $request);
		if (! $passed) {
			return new WP_Error(
				'ucp_adapter_forbidden',
				__('Request verification failed.', 'ucp-adapter-for-woocommerce'),
				array('status' => 403)
			);
		}

		return true;
	}

	/**
	 * Verify UCP-Agent whitelist and optional request signature.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private static function verify_agent_authorization($request)
	{
		$whitelist_enabled = (bool) get_option('ucp_adapter_agent_whitelist_enabled', 0);
		$signature_enabled = (bool) get_option('ucp_adapter_require_agent_signature', 0);

		if (! $whitelist_enabled && ! $signature_enabled) {
			return true;
		}

		$agent_header = (string) $request->get_header('UCP-Agent');
		$profile_url  = self::extract_profile_url($agent_header);

		if ('' === $profile_url) {
			return new WP_Error(
				'ucp_adapter_missing_agent_header',
				__('Missing or invalid UCP-Agent header.', 'ucp-adapter-for-woocommerce'),
				array('status' => 403)
			);
		}

		if ($whitelist_enabled) {
			$domain = self::get_profile_domain($profile_url);
			if ('' === $domain || ! self::is_domain_allowed($domain)) {
				return new WP_Error(
					'ucp_adapter_agent_not_whitelisted',
					__('Agent domain is not in the whitelist.', 'ucp-adapter-for-woocommerce'),
					array('status' => 403)
				);
			}
		}

		if ($signature_enabled) {
			$signature = (string) $request->get_header('Request-Signature');
			if ('' === trim($signature)) {
				return new WP_Error(
					'ucp_adapter_missing_signature',
					__('Missing Request-Signature header.', 'ucp-adapter-for-woocommerce'),
					array('status' => 403)
				);
			}

			$profile = self::fetch_agent_profile($profile_url);
			if (is_wp_error($profile)) {
				return $profile;
			}

			$signing_keys = isset($profile['signing_keys']) && is_array($profile['signing_keys']) ? $profile['signing_keys'] : array();
			if (empty($signing_keys)) {
				return new WP_Error(
					'ucp_adapter_missing_signing_keys',
					__('Agent profile does not include signing keys.', 'ucp-adapter-for-woocommerce'),
					array('status' => 403)
				);
			}

			$body = (string) $request->get_body();
			if (! self::verify_detached_jws_signature($signature, $body, $signing_keys)) {
				return new WP_Error(
					'ucp_adapter_invalid_signature',
					__('Invalid request signature.', 'ucp-adapter-for-woocommerce'),
					array('status' => 403)
				);
			}
		}

		return true;
	}

	/**
	 * Parse and validate ip allowlist.
	 *
	 * @return bool
	 */
	private static function check_ip_whitelist()
	{
		$whitelist = self::get_ip_whitelist();
		if (empty($whitelist)) {
			return true;
		}

		$client_ip = self::get_client_ip();
		if ('' === $client_ip) {
			return false;
		}

		return in_array($client_ip, $whitelist, true);
	}

	/**
	 * Parse configured agent domains.
	 *
	 * @return string[]
	 */
	private static function get_agent_whitelist_domains()
	{
		$raw = get_option('ucp_adapter_agent_whitelist_domains', '');
		$rows = preg_split('/\r\n|\r|\n/', (string) $raw);
		$domains = array();

		foreach ((array) $rows as $row) {
			$row = strtolower(trim((string) $row));
			if ('' === $row) {
				continue;
			}
			$domains[] = $row;
		}

		if (empty($domains)) {
			return self::KNOWN_AGENT_DOMAINS;
		}

		return array_values(array_unique($domains));
	}

	/**
	 * Check if domain matches allowed list with wildcard support.
	 *
	 * @param string $domain Domain.
	 * @return bool
	 */
	private static function is_domain_allowed($domain)
	{
		$domain = strtolower(trim($domain));
		if ('' === $domain) {
			return false;
		}

		foreach (self::get_agent_whitelist_domains() as $pattern) {
			if ($domain === $pattern) {
				return true;
			}

			if (0 === strpos($pattern, '*.')) {
				$suffix = substr($pattern, 1);
				if ('' !== $suffix && str_ends_with($domain, $suffix)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Extract profile URL from UCP-Agent header.
	 *
	 * @param string $header UCP-Agent header.
	 * @return string
	 */
	private static function extract_profile_url($header)
	{
		$header = trim((string) $header);
		if ('' === $header) {
			return '';
		}

		if (preg_match('/profile="([^"]+)"/', $header, $matches)) {
			$url = esc_url_raw($matches[1]);
			return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
		}

		if (filter_var($header, FILTER_VALIDATE_URL)) {
			return esc_url_raw($header);
		}

		return '';
	}

	/**
	 * Parse hostname from profile URL.
	 *
	 * @param string $profile_url URL.
	 * @return string
	 */
	private static function get_profile_domain($profile_url)
	{
		$parts = wp_parse_url($profile_url);
		return isset($parts['host']) ? strtolower((string) $parts['host']) : '';
	}

	/**
	 * Load and cache platform profile.
	 *
	 * @param string $profile_url URL.
	 * @return array|WP_Error
	 */
	private static function fetch_agent_profile($profile_url)
	{
		$cache_key = 'ucp_adapter_profile_' . md5($profile_url);
		$cached    = get_transient($cache_key);
		if (is_array($cached)) {
			return $cached;
		}

		$response = wp_remote_get(
			$profile_url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if (is_wp_error($response)) {
			return new WP_Error(
				'ucp_adapter_profile_fetch_failed',
				__('Unable to fetch agent profile.', 'ucp-adapter-for-woocommerce'),
				array('status' => 403)
			);
		}

		$status = (int) wp_remote_retrieve_response_code($response);
		$body   = (string) wp_remote_retrieve_body($response);
		$data   = json_decode($body, true);

		if ($status < 200 || $status >= 300 || ! is_array($data)) {
			return new WP_Error(
				'ucp_adapter_profile_invalid',
				__('Agent profile is invalid or unavailable.', 'ucp-adapter-for-woocommerce'),
				array('status' => 403)
			);
		}

		set_transient($cache_key, $data, 5 * MINUTE_IN_SECONDS);

		return $data;
	}

	/**
	 * Verify detached JWS signature (header..signature) over raw body.
	 *
	 * @param string $detached_jws Signature.
	 * @param string $body Raw request body.
	 * @param array  $jwk_keys JWK keys.
	 * @return bool
	 */
	private static function verify_detached_jws_signature($detached_jws, $body, $jwk_keys)
	{
		$parts = explode('.', (string) $detached_jws);
		if (3 !== count($parts) || '' !== $parts[1]) {
			return false;
		}

		$header_json = self::base64url_decode($parts[0]);
		$signature   = self::base64url_decode($parts[2]);
		if (false === $header_json || false === $signature) {
			return false;
		}

		$header = json_decode($header_json, true);
		if (! is_array($header)) {
			return false;
		}

		$alg = isset($header['alg']) ? (string) $header['alg'] : '';
		if ('ES256' !== $alg) {
			return false;
		}

		$kid = isset($header['kid']) ? (string) $header['kid'] : '';
		$signing_input = $parts[0] . '.' . self::base64url_encode((string) $body);

		foreach ((array) $jwk_keys as $jwk) {
			if (! is_array($jwk)) {
				continue;
			}

			if ('' !== $kid && isset($jwk['kid']) && (string) $jwk['kid'] !== $kid) {
				continue;
			}

			$public_key_pem = self::jwk_to_public_pem($jwk);
			if ('' === $public_key_pem) {
				continue;
			}

			$der_signature = self::ecdsa_raw_to_der($signature);
			if (false === $der_signature) {
				continue;
			}

			$verify = openssl_verify($signing_input, $der_signature, $public_key_pem, OPENSSL_ALGO_SHA256);
			if (1 === $verify) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert EC JWK to PEM public key.
	 *
	 * @param array $jwk JWK key.
	 * @return string
	 */
	private static function jwk_to_public_pem($jwk)
	{
		if (
			! isset($jwk['kty'], $jwk['crv'], $jwk['x'], $jwk['y']) ||
			'EC' !== $jwk['kty'] ||
			'P-256' !== $jwk['crv']
		) {
			return '';
		}

		$x = self::base64url_decode((string) $jwk['x']);
		$y = self::base64url_decode((string) $jwk['y']);
		if (false === $x || false === $y || 32 !== strlen($x) || 32 !== strlen($y)) {
			return '';
		}

		$uncompressed = "\x04" . $x . $y;
		$ec_public_key = "\x30\x59\x30\x13\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07\x03\x42\x00" . $uncompressed;
		$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($ec_public_key), 64, "\n") . "-----END PUBLIC KEY-----\n";

		return $pem;
	}

	/**
	 * Convert 64-byte raw ECDSA signature (R||S) to DER.
	 *
	 * @param string $raw Raw signature.
	 * @return string|false
	 */
	private static function ecdsa_raw_to_der($raw)
	{
		if (64 !== strlen($raw)) {
			return false;
		}

		$r = substr($raw, 0, 32);
		$s = substr($raw, 32, 32);

		$r = ltrim($r, "\x00");
		$s = ltrim($s, "\x00");
		if ('' === $r) {
			$r = "\x00";
		}
		if ('' === $s) {
			$s = "\x00";
		}

		if (ord($r[0]) > 0x7f) {
			$r = "\x00" . $r;
		}
		if (ord($s[0]) > 0x7f) {
			$s = "\x00" . $s;
		}

		$der_r = "\x02" . chr(strlen($r)) . $r;
		$der_s = "\x02" . chr(strlen($s)) . $s;
		$seq   = $der_r . $der_s;

		return "\x30" . chr(strlen($seq)) . $seq;
	}

	/**
	 * Base64url decode helper.
	 *
	 * @param string $input Input.
	 * @return string|false
	 */
	private static function base64url_decode($input)
	{
		$remainder = strlen($input) % 4;
		if ($remainder > 0) {
			$input .= str_repeat('=', 4 - $remainder);
		}

		return base64_decode(strtr($input, '-_', '+/'), true);
	}

	/**
	 * Base64url encode helper.
	 *
	 * @param string $input Input.
	 * @return string
	 */
	private static function base64url_encode($input)
	{
		return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
	}

	/**
	 * Rate limiting by IP + identity.
	 *
	 * @param string $identity Identity hash.
	 * @return bool
	 */
	private static function check_rate_limit($identity = '')
	{
		if (! (bool) get_option('ucp_adapter_rate_limit_enabled', false)) {
			return true;
		}

		$client_ip = self::get_client_ip();
		$limit     = max(1, (int) get_option('ucp_adapter_rate_limit', 100));
		$window    = max(1, (int) get_option('ucp_adapter_rate_window', 60));
		$key_seed  = $client_ip . '|' . $identity;
		$cache_key = 'ucp_adapter_rate_' . md5($key_seed);
		$requests  = (int) get_transient($cache_key);

		$requests++;
		if ($requests > $limit) {
			return false;
		}

		set_transient($cache_key, $requests, $window);

		return true;
	}

	/**
	 * Parse textarea whitelist option.
	 *
	 * @return array
	 */
	public static function get_ip_whitelist()
	{
		$raw = get_option('ucp_adapter_ip_whitelist', '');
		if (is_array($raw)) {
			$lines = $raw;
		} else {
			$lines = preg_split('/\r\n|\r|\n/', (string) $raw);
		}

		$ips = array();
		foreach ($lines as $line) {
			$line = trim((string) $line);
			if ('' === $line) {
				continue;
			}

			if (filter_var($line, FILTER_VALIDATE_IP)) {
				$ips[] = $line;
			}
		}

		return array_values(array_unique($ips));
	}

	/**
	 * Get client ip.
	 *
	 * @return string
	 */
	private static function get_client_ip()
	{
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ($headers as $header) {
			if (empty($_SERVER[ $header ])) {
				continue;
			}

			$raw   = sanitize_text_field(wp_unslash($_SERVER[ $header ]));
			$parts = explode(',', $raw);
			$ip    = trim($parts[0]);

			if (filter_var($ip, FILTER_VALIDATE_IP)) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * Sanitize nested data.
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 */
	public static function sanitize_data($data)
	{
		if (is_array($data)) {
			$sanitized = array();
			foreach ($data as $key => $value) {
				$sanitized[ sanitize_key((string) $key) ] = self::sanitize_data($value);
			}
			return $sanitized;
		}

		if (is_string($data)) {
			return sanitize_text_field($data);
		}

		return $data;
	}

	/**
	 * Generate secure API key.
	 *
	 * @return string
	 */
	public static function generate_api_key()
	{
		return wp_generate_password(48, false, false);
	}
}
