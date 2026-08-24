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
	 * Main menu slug.
	 *
	 * @var string
	 */
	private const MENU_SLUG = 'ucp-adapter-for-woocommerce';

	/**
	 * Settings slug.
	 *
	 * @var string
	 */
	private const SETTINGS_SLUG = 'ucp-adapter-settings';

	/**
	 * Security slug.
	 *
	 * @var string
	 */
	private const SECURITY_SLUG = 'ucp-adapter-security';

	/**
	 * Sessions slug.
	 *
	 * @var string
	 */
	private const SESSIONS_SLUG = 'ucp-adapter-sessions';

	/**
	 * Docs slug.
	 *
	 * @var string
	 */
	private const DOCS_SLUG = 'ucp-adapter-docs';

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
			__('UCP Connector', 'ucp-adapter-for-woocommerce'),
			'manage_options',
			self::MENU_SLUG,
			array($this, 'render_overview_page'),
			'dashicons-store',
			80
		);

		add_submenu_page(
			self::MENU_SLUG,
			__('Overview', 'ucp-adapter-for-woocommerce'),
			__('Overview', 'ucp-adapter-for-woocommerce'),
			'manage_options',
			self::MENU_SLUG,
			array($this, 'render_overview_page')
		);

		add_submenu_page(
			self::MENU_SLUG,
			__('Checkout Sessions', 'ucp-adapter-for-woocommerce'),
			__('Checkout Sessions', 'ucp-adapter-for-woocommerce'),
			'manage_options',
			self::SESSIONS_SLUG,
			array($this, 'render_sessions_page')
		);

		add_submenu_page(
			self::MENU_SLUG,
			__('Configuration', 'ucp-adapter-for-woocommerce'),
			__('Configuration', 'ucp-adapter-for-woocommerce'),
			'manage_options',
			self::SETTINGS_SLUG,
			array($this, 'render_settings_page')
		);

		add_submenu_page(
			self::MENU_SLUG,
			__('Security', 'ucp-adapter-for-woocommerce'),
			__('Security', 'ucp-adapter-for-woocommerce'),
			'manage_options',
			self::SECURITY_SLUG,
			array($this, 'render_security_page')
		);

		add_submenu_page(
			self::MENU_SLUG,
			__('API Docs', 'ucp-adapter-for-woocommerce'),
			__('API Docs', 'ucp-adapter-for-woocommerce'),
			'manage_options',
			self::DOCS_SLUG,
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
		register_setting('ucp_adapter_settings', 'ucp_adapter_cors_origins', array('sanitize_callback' => 'sanitize_textarea_field'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_webhook_url', array('sanitize_callback' => 'esc_url_raw'));
		register_setting('ucp_adapter_settings', 'ucp_adapter_webhook_secret', array('sanitize_callback' => 'sanitize_text_field'));
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
	 * Render page title + nav shell open.
	 *
	 * @param string $title Page title.
	 * @param string $description Page subtitle.
	 * @param array  $badges Hero badges.
	 * @return void
	 */
	private function render_shell_start($title, $description, $badges = array())
	{
		?>
		<div class="wrap ucp-adapter-admin">
			<h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
			<hr class="wp-header-end" />
		</div>
		<div class="wrap ucp-adapter-admin ucp-admin-shell">
			<nav class="ucp-admin-nav" aria-label="<?php esc_attr_e('UCP Admin Navigation', 'ucp-adapter-for-woocommerce'); ?>">
				<?php foreach ($this->get_admin_nav_items() as $item) : ?>
					<a href="<?php echo esc_url($item['url']); ?>" class="ucp-admin-nav-link <?php echo $item['active'] ? 'is-active' : ''; ?>"><?php echo esc_html($item['label']); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="ucp-admin-hero">
				<div class="ucp-admin-hero-copy">
					<h2><?php echo esc_html($title); ?></h2>
					<p><?php echo esc_html($description); ?></p>
				</div>
				<?php if (! empty($badges)) : ?>
					<div class="ucp-admin-hero-badges">
						<?php foreach ($badges as $badge) : ?>
							<span class="ucp-pill"><?php echo esc_html($badge); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php
	}

	/**
	 * Close page shell.
	 *
	 * @return void
	 */
	private function render_shell_end()
	{
		echo '</div>';
	}

	/**
	 * Build top navigation model.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_admin_nav_items()
	{
		$current = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : self::MENU_SLUG;
		$items   = array(
			self::MENU_SLUG     => __('Overview', 'ucp-adapter-for-woocommerce'),
			self::SESSIONS_SLUG => __('Checkout Sessions', 'ucp-adapter-for-woocommerce'),
			self::SETTINGS_SLUG => __('Configuration', 'ucp-adapter-for-woocommerce'),
			self::SECURITY_SLUG => __('Security', 'ucp-adapter-for-woocommerce'),
			self::DOCS_SLUG     => __('API Docs', 'ucp-adapter-for-woocommerce'),
		);
		$nav     = array();

		foreach ($items as $slug => $label) {
			$nav[] = array(
				'label'  => $label,
				'url'    => admin_url('admin.php?page=' . $slug),
				'active' => $slug === $current,
			);
		}

		return $nav;
	}

	/**
	 * Render overview page.
	 *
	 * @return void
	 */
	public function render_overview_page()
	{
		$sessions = UCP_Adapter_Session_Handler::get_instance()->get_recent_sessions(40);
		$stats    = array(
			'total'              => 0,
			'acp'                => 0,
			'ucp'                => 0,
			'ready_for_payment'  => 0,
			'completed'          => 0,
			'not_ready_for_payment' => 0,
		);

		foreach ($sessions as $session) {
			$data = isset($session['data']) && is_array($session['data']) ? $session['data'] : array();
			$stats['total']++;

			$protocol = isset($data['protocol']) ? strtolower((string) $data['protocol']) : 'ucp';
			$status   = isset($data['status']) ? (string) $data['status'] : 'not_ready_for_payment';

			if ('acp' === $protocol) {
				$stats['acp']++;
			} else {
				$stats['ucp']++;
			}

			if (isset($stats[ $status ])) {
				$stats[ $status ]++;
			}
		}

		$this->render_shell_start(
			__('Overview', 'ucp-adapter-for-woocommerce'),
			__('Use this dashboard to monitor ACP/UCP activity and jump to configuration and security tasks.', 'ucp-adapter-for-woocommerce'),
			array('WooCommerce', 'ACP', 'UCP')
		);
		?>
			<div class="ucp-overview-grid">
				<div class="ucp-admin-card">
					<h3><?php esc_html_e('Recent Sessions', 'ucp-adapter-for-woocommerce'); ?></h3>
					<p class="ucp-metric-value"><?php echo esc_html((string) $stats['total']); ?></p>
				</div>
				<div class="ucp-admin-card">
					<h3><?php esc_html_e('Completed', 'ucp-adapter-for-woocommerce'); ?></h3>
					<p class="ucp-metric-value"><?php echo esc_html((string) $stats['completed']); ?></p>
				</div>
				<div class="ucp-admin-card">
					<h3><?php esc_html_e('Ready For Payment', 'ucp-adapter-for-woocommerce'); ?></h3>
					<p class="ucp-metric-value"><?php echo esc_html((string) $stats['ready_for_payment']); ?></p>
				</div>
				<div class="ucp-admin-card">
					<h3><?php esc_html_e('Not Ready For Payment', 'ucp-adapter-for-woocommerce'); ?></h3>
					<p class="ucp-metric-value"><?php echo esc_html((string) $stats['not_ready_for_payment']); ?></p>
				</div>
			</div>
			<div class="ucp-admin-cards">
				<div class="ucp-admin-card">
					<h3><?php esc_html_e('Protocol Mix', 'ucp-adapter-for-woocommerce'); ?></h3>
					<p><?php printf(esc_html__('ACP: %1$s · UCP: %2$s', 'ucp-adapter-for-woocommerce'), esc_html((string) $stats['acp']), esc_html((string) $stats['ucp'])); ?></p>
				</div>
				<div class="ucp-admin-card">
					<h3><?php esc_html_e('Discovery Endpoint', 'ucp-adapter-for-woocommerce'); ?></h3>
					<p><code><?php echo esc_html(home_url('/.well-known/ucp')); ?></code></p>
					<p class="description"><?php esc_html_e('AI agents use this URL to auto-discover your store\'s UCP capabilities.', 'ucp-adapter-for-woocommerce'); ?></p>
				</div>
				<div class="ucp-admin-card">
					<h3><?php esc_html_e('API Endpoints', 'ucp-adapter-for-woocommerce'); ?></h3>
					<p>
						<code><?php echo esc_html(rest_url('acp/v1/health')); ?></code><br />
						<code><?php echo esc_html(rest_url('ucp/v1/health')); ?></code>
					</p>
				</div>
				<div class="ucp-admin-card">
					<h3><?php esc_html_e('Quick Actions', 'ucp-adapter-for-woocommerce'); ?></h3>
					<p>
						<a href="<?php echo esc_url(admin_url('admin.php?page=' . self::SESSIONS_SLUG)); ?>"><?php esc_html_e('View Sessions', 'ucp-adapter-for-woocommerce'); ?></a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url(admin_url('admin.php?page=' . self::SETTINGS_SLUG)); ?>"><?php esc_html_e('Edit Configuration', 'ucp-adapter-for-woocommerce'); ?></a>
						&nbsp;|&nbsp;
						<a href="<?php echo esc_url(admin_url('admin.php?page=' . self::SECURITY_SLUG)); ?>"><?php esc_html_e('Review Security', 'ucp-adapter-for-woocommerce'); ?></a>
					</p>
				</div>
			</div>
		<?php
		$this->render_shell_end();
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page()
	{
		$this->render_shell_start(
			__('Configuration', 'ucp-adapter-for-woocommerce'),
			__('Manage protocol behavior, merchant metadata, and checkout links shown to agent clients.', 'ucp-adapter-for-woocommerce')
		);
		?>
			<form method="post" action="options.php" class="ucp-settings-form">
				<?php
				settings_fields('ucp_adapter_settings');
				do_settings_sections('ucp_adapter_settings');
				?>
				<table class="form-table ucp-form-table">
					<tr><th colspan="2"><h2><?php esc_html_e('Protocol Configuration', 'ucp-adapter-for-woocommerce'); ?></h2></th></tr>
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
					<tr><th colspan="2"><h2><?php esc_html_e('Merchant Checkout', 'ucp-adapter-for-woocommerce'); ?></h2></th></tr>
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
				</table>

				<div class="ucp-form-actions">
					<?php submit_button(__('Save Configuration', 'ucp-adapter-for-woocommerce')); ?>
				</div>
			</form>
		<?php
		$this->render_shell_end();
	}

	/**
	 * Render security page.
	 *
	 * @return void
	 */
	public function render_security_page()
	{
		$api_key = (string) get_option('ucp_adapter_api_key', '');

		$this->render_shell_start(
			__('Security', 'ucp-adapter-for-woocommerce'),
			__('Control authentication, rate limiting, agent authorization, signatures, and network access.', 'ucp-adapter-for-woocommerce')
		);
		?>
			<form method="post" action="options.php" class="ucp-settings-form">
				<?php
				settings_fields('ucp_adapter_settings');
				do_settings_sections('ucp_adapter_settings');
				?>
				<table class="form-table ucp-form-table">
					<tr><th colspan="2"><h2><?php esc_html_e('API Authentication', 'ucp-adapter-for-woocommerce'); ?></h2></th></tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_api_key"><?php esc_html_e('API Key', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td>
							<input type="text" id="ucp_adapter_api_key" name="ucp_adapter_api_key" class="regular-text" value="<?php echo esc_attr($api_key); ?>" readonly />
							<button type="button" class="button button-secondary" id="regenerate-api-key"><?php esc_html_e('Regenerate', 'ucp-adapter-for-woocommerce'); ?></button>
						</td>
					</tr>
					<tr><th colspan="2"><h2><?php esc_html_e('Rate Limiting', 'ucp-adapter-for-woocommerce'); ?></h2></th></tr>
					<tr class="ucp-rate-limit-row">
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
					<tr><th colspan="2"><h2><?php esc_html_e('Network Guard', 'ucp-adapter-for-woocommerce'); ?></h2></th></tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_ip_whitelist"><?php esc_html_e('IP Allowlist', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td>
							<textarea id="ucp_adapter_ip_whitelist" name="ucp_adapter_ip_whitelist" rows="4" cols="50"><?php echo esc_textarea(get_option('ucp_adapter_ip_whitelist', '')); ?></textarea>
							<p class="description"><?php esc_html_e('One IP address per line. Leave empty to allow all IPs.', 'ucp-adapter-for-woocommerce'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_cors_origins"><?php esc_html_e('CORS Allowed Origins', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td>
							<textarea id="ucp_adapter_cors_origins" name="ucp_adapter_cors_origins" rows="3" cols="50"><?php echo esc_textarea(get_option('ucp_adapter_cors_origins', '*')); ?></textarea>
							<p class="description"><?php esc_html_e('One origin per line, or * to allow all. Controls which domains can call the API from browsers.', 'ucp-adapter-for-woocommerce'); ?></p>
						</td>
					</tr>
					<tr><th colspan="2"><h2><?php esc_html_e('Webhooks', 'ucp-adapter-for-woocommerce'); ?></h2></th></tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_webhook_url"><?php esc_html_e('Webhook URL', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td>
							<input type="url" id="ucp_adapter_webhook_url" name="ucp_adapter_webhook_url" class="regular-text" value="<?php echo esc_attr(get_option('ucp_adapter_webhook_url', '')); ?>" />
							<p class="description"><?php esc_html_e('Receives HMAC-signed POST notifications on session create, complete, and cancel events.', 'ucp-adapter-for-woocommerce'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ucp_adapter_webhook_secret"><?php esc_html_e('Webhook Secret', 'ucp-adapter-for-woocommerce'); ?></label></th>
						<td>
							<input type="text" id="ucp_adapter_webhook_secret" name="ucp_adapter_webhook_secret" class="regular-text" value="<?php echo esc_attr(get_option('ucp_adapter_webhook_secret', '')); ?>" />
							<p class="description"><?php esc_html_e('Used to sign webhook payloads with HMAC-SHA256. Verify via the X-UCP-Signature header.', 'ucp-adapter-for-woocommerce'); ?></p>
						</td>
					</tr>
				</table>
				<div class="ucp-form-actions">
					<?php submit_button(__('Save Security Settings', 'ucp-adapter-for-woocommerce')); ?>
				</div>
			</form>
		<?php
		$this->render_shell_end();
	}

	/**
	 * Render sessions page.
	 *
	 * @return void
	 */
	public function render_sessions_page()
	{
		$sessions = UCP_Adapter_Session_Handler::get_instance()->get_recent_sessions(100);

		$this->render_shell_start(
			__('Checkout Sessions', 'ucp-adapter-for-woocommerce'),
			__('Live view of normalized checkout sessions across ACP and UCP routes.', 'ucp-adapter-for-woocommerce')
		);
		?>
			<div class="ucp-table-card ucp-table-wrap">
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
							<td data-label="<?php esc_attr_e('Session ID', 'ucp-adapter-for-woocommerce'); ?>">
								<?php
								$session_id = (string) $session['session_id'];
								$short_id   = strlen($session_id) > 40 ? substr($session_id, 0, 24) . '…' . substr($session_id, -10) : $session_id;
								?>
								<code class="ucp-session-id" title="<?php echo esc_attr($session_id); ?>"><?php echo esc_html($short_id); ?></code>
							</td>
							<td data-label="<?php esc_attr_e('Protocol', 'ucp-adapter-for-woocommerce'); ?>"><?php echo esc_html(isset($data['protocol']) ? strtoupper($data['protocol']) : 'UCP'); ?></td>
							<td data-label="<?php esc_attr_e('Status', 'ucp-adapter-for-woocommerce'); ?>"><span class="ucp-admin-status <?php echo esc_attr(isset($data['status']) ? $data['status'] : 'not_ready_for_payment'); ?>"><?php echo esc_html(isset($data['status']) ? $data['status'] : 'unknown'); ?></span></td>
							<td data-label="<?php esc_attr_e('Total', 'ucp-adapter-for-woocommerce'); ?>"><?php echo esc_html(isset($data['totals']['total']) ? $data['totals']['total'] : '0.00'); ?></td>
							<td data-label="<?php esc_attr_e('Updated', 'ucp-adapter-for-woocommerce'); ?>"><?php echo esc_html(isset($data['updated_at']) ? $data['updated_at'] : $session['updated_at']); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5"><?php esc_html_e('No sessions found.', 'ucp-adapter-for-woocommerce'); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			</div>
		<?php
		$this->render_shell_end();
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

		$this->render_shell_start(
			__('API Docs', 'ucp-adapter-for-woocommerce'),
			__('ACP-native and UCP compatibility endpoints are exposed over one WooCommerce-backed checkout model.', 'ucp-adapter-for-woocommerce')
		);
		?>
			<div class="ucp-doc-card">
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

				<h2><?php esc_html_e('Catalog & Common Routes', 'ucp-adapter-for-woocommerce'); ?></h2>
				<pre><code><?php echo esc_html("GET {$acp_base}/health\nGET {$acp_base}/products/{id}\nGET {$acp_base}/products?search=shirt&category=clothing&min_price=10&max_price=50&in_stock=1\nGET {$acp_base}/product/search?search=shirt\nGET {$acp_base}/categories\nGET {$acp_base}/orders/{order_id}\nGET {$acp_base}/sessions"); ?></code></pre>

				<h2><?php esc_html_e('Discovery', 'ucp-adapter-for-woocommerce'); ?></h2>
				<pre><code><?php echo esc_html(home_url('/.well-known/ucp')); ?></code></pre>
				<p><?php esc_html_e('Public JSON manifest for AI agent auto-discovery (no authentication required).', 'ucp-adapter-for-woocommerce'); ?></p>

				<h2><?php esc_html_e('Additional Headers', 'ucp-adapter-for-woocommerce'); ?></h2>
				<p><code>Idempotency-Key: &lt;unique-key&gt;</code> — <?php esc_html_e('Prevents duplicate session creation. Same key returns the existing session.', 'ucp-adapter-for-woocommerce'); ?></p>

				<h2><?php esc_html_e('Webhooks', 'ucp-adapter-for-woocommerce'); ?></h2>
				<p><?php esc_html_e('When configured, the plugin sends HMAC-SHA256 signed POST requests on these events:', 'ucp-adapter-for-woocommerce'); ?></p>
				<pre><code><?php echo esc_html("session.created\nsession.completed\nsession.canceled"); ?></code></pre>
				<p><?php esc_html_e('Verify signatures using the X-UCP-Signature header: sha256=HMAC(timestamp.body, secret)', 'ucp-adapter-for-woocommerce'); ?></p>
			</div>
		<?php
		$this->render_shell_end();
	}
}
