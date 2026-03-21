<?php

/**
 * UCP Admin Interface
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Admin configuration UI.
 */
class UCP_Adapter_Admin
{

	/**
	 * Singleton.
	 *
	 * @var UCP_Adapter_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return UCP_Adapter_Admin
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
		add_action('admin_menu', array($this, 'add_admin_menu'));
		add_action('admin_init', array($this, 'register_settings'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
	}

	/**
	 * Register menu pages.
	 *
	 * @return void
	 */
	public function add_admin_menu()
	{
		add_menu_page(
			__('UCP Connector for Woocommerce', 'ucp-adapter-for-woocommerce'),
			__('UCP Connector for Woocommerce', 'ucp-adapter-for-woocommerce'),
			'manage_options',
			'ucp-adapter-for-woocommerce',
			array($this, 'render_settings_page'),
			'dashicons-store',
			80
		);

		add_submenu_page(
			'ucp-adapter-for-woocommerce',
			__('Settings', 'ucp-adapter-for-woocommerce'),
			__('Settings', 'ucp-adapter-for-woocommerce'),
			'manage_options',
			'ucp-adapter-for-woocommerce',
			array($this, 'render_settings_page')
		);

		add_submenu_page(
			'ucp-adapter-for-woocommerce',
			__('Checkout Sessions', 'ucp-adapter-for-woocommerce'),
			__('Checkout Sessions', 'ucp-adapter-for-woocommerce'),
			'manage_options',
			'ucp-adapter-sessions',
			array($this, 'render_sessions_page')
		);

		add_submenu_page(
			'ucp-adapter-for-woocommerce',
			__('API Docs', 'ucp-adapter-for-woocommerce'),
			__('API Docs', 'ucp-adapter-for-woocommerce'),
			'manage_options',
			'ucp-adapter-docs',
			array($this, 'render_docs_page')
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings()
	{
		register_setting('ucp_adapter_settings', 'ucp_adapter_api_key', array('sanitize_callback' => 'sanitize_text_field'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_session_timeout', array('sanitize_callback' => 'absint'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_protocol_ucp_enabled', array('sanitize_callback' => 'absint'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_protocol_acp_enabled', array('sanitize_callback' => 'absint'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_store_name', array('sanitize_callback' => 'sanitize_text_field'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_checkout_return_url', array('sanitize_callback' => 'esc_url_raw'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_terms_url', array('sanitize_callback' => 'esc_url_raw'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_privacy_url', array('sanitize_callback' => 'esc_url_raw'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_refund_url', array('sanitize_callback' => 'esc_url_raw'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_enable_delegated_payment', array('sanitize_callback' => 'absint'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_rate_limit_enabled', array('sanitize_callback' => 'absint'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_rate_limit', array('sanitize_callback' => 'absint'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_rate_window', array('sanitize_callback' => 'absint'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_ip_whitelist', array('sanitize_callback' => 'sanitize_textarea_field'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_agent_whitelist_enabled', array('sanitize_callback' => 'absint'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_agent_whitelist_domains', array('sanitize_callback' => 'sanitize_textarea_field'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_require_agent_signature', array('sanitize_callback' => 'absint'));
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current hook.
	 * @return void
	 */
	public function enqueue_admin_scripts($hook)
	{
		if (false === strpos($hook, 'ucp-adapter')) {
			return;
		}

		wp_enqueue_style(
			'ucp-adapter-admin',
			UCP_ADAPTER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			UCP_ADAPTER_VERSION
		);

		wp_enqueue_script(
			'ucp-adapter-admin',
			UCP_ADAPTER_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery'),
			UCP_ADAPTER_VERSION,
			true
		);

		wp_localize_script(
			'ucp-adapter-admin',
			'ucpAdapterAdminData',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('ucp_adapter_admin'),
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page()
	{
		$api_key = (string) get_option('ucp_adapter_api_key', '');
		?>
		<div class="wrap ucp-adapter-admin">
			<h1><?php esc_html_e('UCP Connector for Woocommerce', 'ucp-adapter-for-woocommerce'); ?></h1>
			<p><?php esc_html_e('Configure protocol support, security controls, and checkout metadata for commerce agents.', 'ucp-adapter-for-woocommerce'); ?></p>
			<div class="ucp-admin-cards">
				<div class="ucp-admin-card">
					<h3><?php esc_html_e('Security Model', 'ucp-adapter-for-woocommerce'); ?></h3>
					<p><?php esc_html_e('Supports API keys, IP allowlists, agent domain allowlists, and optional detached JWS request signatures.', 'ucp-adapter-for-woocommerce'); ?></p>
				</div>
				<div class="ucp-admin-card">
					<h3><?php esc_html_e('Protocol Surface', 'ucp-adapter-for-woocommerce'); ?></h3>
					<p><?php esc_html_e('ACP and UCP run on one normalized checkout session model with merchant-authoritative recalculation.', 'ucp-adapter-for-woocommerce'); ?></p>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields('ucp_adapter_settings');
				do_settings_sections('ucp_adapter_settings');
				?>
				<table class="form-table">
					<tr><th colspan="2"><h2><?php esc_html_e('Core Settings', 'ucp-adapter-for-woocommerce'); ?></h2></th></tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_api_key"><?php esc_html_e('API Key', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td>
							<input type="text" id="ucp_adapter_api_key" name="ucp_adapter_api_key" class="regular-text" value="<?php echo esc_attr($api_key); ?>" readonly />
							<button type="button" class="button button-secondary" id="regenerate-api-key"><?php esc_html_e('Regenerate', 'ucp-adapter-for-woocommerce'); ?></button>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_session_timeout"><?php esc_html_e('Session Timeout (seconds)', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td><input type="number" id="ucp_adapter_session_timeout" name="ucp_adapter_session_timeout" class="small-text" value="<?php echo esc_attr(get_option('ucp_adapter_session_timeout', 3600)); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Protocols', 'ucp-adapter-for-woocommerce'); ?></th>
						<td>
							<label><input type="checkbox" name="ucp_adapter_protocol_ucp_enabled" value="1" <?php checked((int) get_option('ucp_adapter_protocol_ucp_enabled', 1), 1); ?> /> <?php esc_html_e('Enable UCP', 'ucp-adapter-for-woocommerce'); ?></label><br />
							<label><input type="checkbox" name="ucp_adapter_protocol_acp_enabled" value="1" <?php checked((int) get_option('ucp_adapter_protocol_acp_enabled', 1), 1); ?> /> <?php esc_html_e('Enable ACP', 'ucp-adapter-for-woocommerce'); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_store_name"><?php esc_html_e('Store Display Name', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td><input type="text" id="ucp_adapter_store_name" name="ucp_adapter_store_name" class="regular-text" value="<?php echo esc_attr(get_option('ucp_adapter_store_name', get_bloginfo('name'))); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_checkout_return_url"><?php esc_html_e('Checkout Return URL', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td><input type="url" id="ucp_adapter_checkout_return_url" name="ucp_adapter_checkout_return_url" class="regular-text" value="<?php echo esc_attr(get_option('ucp_adapter_checkout_return_url', '')); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_terms_url"><?php esc_html_e('Terms URL', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td><input type="url" id="ucp_adapter_terms_url" name="ucp_adapter_terms_url" class="regular-text" value="<?php echo esc_attr(get_option('ucp_adapter_terms_url', '')); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_privacy_url"><?php esc_html_e('Privacy URL', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td><input type="url" id="ucp_adapter_privacy_url" name="ucp_adapter_privacy_url" class="regular-text" value="<?php echo esc_attr(get_option('ucp_adapter_privacy_url', '')); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_refund_url"><?php esc_html_e('Refund Policy URL', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td><input type="url" id="ucp_adapter_refund_url" name="ucp_adapter_refund_url" class="regular-text" value="<?php echo esc_attr(get_option('ucp_adapter_refund_url', '')); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Delegated Payment', 'ucp-adapter-for-woocommerce'); ?></th>
						<td><label><input type="checkbox" name="ucp_adapter_enable_delegated_payment" value="1" <?php checked((int) get_option('ucp_adapter_enable_delegated_payment', 0), 1); ?> /> <?php esc_html_e('Enable delegated payment compatibility mode', 'ucp-adapter-for-woocommerce'); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Rate Limiting', 'ucp-adapter-for-woocommerce'); ?></th>
						<td>
							<label><input type="checkbox" name="ucp_adapter_rate_limit_enabled" value="1" <?php checked((int) get_option('ucp_adapter_rate_limit_enabled', 0), 1); ?> /> <?php esc_html_e('Enable request rate limiting', 'ucp-adapter-for-woocommerce'); ?></label><br />
							<label><?php esc_html_e('Requests', 'ucp-adapter-for-woocommerce'); ?> <input type="number" class="small-text" name="ucp_adapter_rate_limit" value="<?php echo esc_attr(get_option('ucp_adapter_rate_limit', 100)); ?>" /></label>
							<label><?php esc_html_e('Window (seconds)', 'ucp-adapter-for-woocommerce'); ?> <input type="number" class="small-text" name="ucp_adapter_rate_window" value="<?php echo esc_attr(get_option('ucp_adapter_rate_window', 60)); ?>" /></label>
						</td>
					</tr>
					<tr><th colspan="2"><h2><?php esc_html_e('Agent Security Model', 'ucp-adapter-for-woocommerce'); ?></h2></th></tr>
					<tr>
						<th scope="row"><?php esc_html_e('Agent Domain Whitelist', 'ucp-adapter-for-woocommerce'); ?></th>
						<td>
							<label><input type="checkbox" name="ucp_adapter_agent_whitelist_enabled" value="1" <?php checked((int) get_option('ucp_adapter_agent_whitelist_enabled', 0), 1); ?> /> <?php esc_html_e('Require `UCP-Agent` header and allow only whitelisted agent domains', 'ucp-adapter-for-woocommerce'); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_agent_whitelist_domains"><?php esc_html_e('Allowed Agent Domains', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td>
							<textarea id="ucp_adapter_agent_whitelist_domains" name="ucp_adapter_agent_whitelist_domains" rows="5" cols="50"><?php echo esc_textarea(get_option('ucp_adapter_agent_whitelist_domains', '')); ?></textarea>
							<p class="description"><?php esc_html_e('One domain per line. Wildcards supported, e.g. *.openai.com. Leave empty to allow known AI platform defaults.', 'ucp-adapter-for-woocommerce'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Request Signature', 'ucp-adapter-for-woocommerce'); ?></th>
						<td>
							<label><input type="checkbox" name="ucp_adapter_require_agent_signature" value="1" <?php checked((int) get_option('ucp_adapter_require_agent_signature', 0), 1); ?> /> <?php esc_html_e('Require `Request-Signature` detached JWS verification using agent profile signing keys', 'ucp-adapter-for-woocommerce'); ?></label>
						</td>
					</tr>
					<tr><th colspan="2"><h2><?php esc_html_e('Network Guards', 'ucp-adapter-for-woocommerce'); ?></h2></th></tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_ip_whitelist"><?php esc_html_e('IP Allowlist', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td>
							<textarea id="ucp_adapter_ip_whitelist" name="ucp_adapter_ip_whitelist" rows="4" cols="50"><?php echo esc_textarea(get_option('ucp_adapter_ip_whitelist', '')); ?></textarea>
							<p class="description"><?php esc_html_e('One IP address per line. Leave empty to allow all IPs.', 'ucp-adapter-for-woocommerce'); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render sessions page.
	 *
	 * @return void
	 */
	public function render_sessions_page()
	{
		$sessions = UCP_Adapter_Session_Handler::get_instance()->get_recent_sessions(100);
		?>
		<div class="wrap ucp-adapter-admin">
			<h1><?php esc_html_e('Recent Checkout Sessions', 'ucp-adapter-for-woocommerce'); ?></h1>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e('Session ID', 'ucp-adapter-for-woocommerce'); ?></th>
						<th><?php esc_html_e('Protocol', 'ucp-adapter-for-woocommerce'); ?></th>
						<th><?php esc_html_e('Status', 'ucp-adapter-for-woocommerce'); ?></th>
						<th><?php esc_html_e('Total', 'ucp-adapter-for-woocommerce'); ?></th>
						<th><?php esc_html_e('Updated', 'ucp-adapter-for-woocommerce'); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if (! empty($sessions)) : ?>
					<?php foreach ($sessions as $session) : ?>
						<?php $data = isset($session['data']) && is_array($session['data']) ? $session['data'] : array(); ?>
						<tr>
							<td><code><?php echo esc_html($session['session_id']); ?></code></td>
							<td><?php echo esc_html(isset($data['protocol']) ? strtoupper($data['protocol']) : 'UCP'); ?></td>
							<td><span class="ucp-admin-status <?php echo esc_attr(isset($data['status']) ? $data['status'] : 'not_ready_for_payment'); ?>"><?php echo esc_html(isset($data['status']) ? $data['status'] : 'unknown'); ?></span></td>
							<td><?php echo esc_html(isset($data['totals']['total']) ? $data['totals']['total'] : '0.00'); ?></td>
							<td><?php echo esc_html(isset($data['updated_at']) ? $data['updated_at'] : $session['updated_at']); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5"><?php esc_html_e('No sessions found.', 'ucp-adapter-for-woocommerce'); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render docs page.
	 *
	 * @return void
	 */
	public function render_docs_page()
	{
		$acp_base = rest_url('acp/v1');
		$ucp_base = rest_url('ucp/v1');
		?>
		<div class="wrap ucp-adapter-admin ucp-docs-section">
			<h1><?php esc_html_e('API Documentation', 'ucp-adapter-for-woocommerce'); ?></h1>
			<p><?php esc_html_e('This adapter exposes ACP-native checkout routes and UCP legacy compatibility routes over the same WooCommerce session model.', 'ucp-adapter-for-woocommerce'); ?></p>

			<h2><?php esc_html_e('Authentication', 'ucp-adapter-for-woocommerce'); ?></h2>
			<p><code>Authorization: Bearer &lt;api_key&gt;</code></p>
			<p><code>X-UCP-API-Key: &lt;api_key&gt;</code> <?php esc_html_e('or', 'ucp-adapter-for-woocommerce'); ?> <code>X-ACP-API-Key: &lt;api_key&gt;</code></p>
			<p><code>UCP-Agent: UCP/2026-01-11 profile="https://api.openai.com/.well-known/ucp"</code></p>
			<p><code>Request-Signature: &lt;detached-jws&gt;</code></p>

			<h2><?php esc_html_e('ACP Routes', 'ucp-adapter-for-woocommerce'); ?></h2>
			<pre><code><?php echo esc_html("POST {$acp_base}/checkout_sessions\nPOST {$acp_base}/checkout_sessions/{id}\nGET {$acp_base}/checkout_sessions/{id}\nPOST {$acp_base}/checkout_sessions/{id}/complete\nPOST {$acp_base}/checkout_sessions/{id}/cancel\nGET {$acp_base}/capabilities"); ?></code></pre>
			<p><?php esc_html_e('For capability negotiation, include an optional `platform_profile.ucp.capabilities` array in create-session requests.', 'ucp-adapter-for-woocommerce'); ?></p>

			<h2><?php esc_html_e('UCP Compatibility Routes', 'ucp-adapter-for-woocommerce'); ?></h2>
			<pre><code><?php echo esc_html("POST {$ucp_base}/session\nPUT {$ucp_base}/update/{id}\nGET {$ucp_base}/status/{id}\nPOST {$ucp_base}/complete/{id}\nGET {$ucp_base}/capabilities"); ?></code></pre>

			<h2><?php esc_html_e('Common Routes', 'ucp-adapter-for-woocommerce'); ?></h2>
			<pre><code><?php echo esc_html("GET {$acp_base}/product/search?search=shirt\nGET {$acp_base}/orders/{order_id}\nGET {$acp_base}/sessions"); ?></code></pre>
		</div>
		<?php
	}
}
