<?php

/**
 * Plugin Name: UCP Connector for Woocommerce
 * Plugin URI: https://wordpress.org/plugins/ucp-adapter-for-woocommerce
 * Description: WooCommerce adapter for UCP and OpenAI ACP checkout sessions.
 * Version: 1.0.1
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Author: Mohamed Ayoub Jabane
 * Author URI: https://github.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ucp-adapter-for-woocommerce
 * Domain Path: /languages
 *
 * @package UCP_Adapter
 */

if (! defined('ABSPATH')) {
	exit;
}

define('UCP_ADAPTER_VERSION', '1.0.1');
define('UCP_ADAPTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UCP_ADAPTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UCP_ADAPTER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class.
 */
class UCP_Adapter_Core
{
	/**
	 * Instance.
	 *
	 * @var UCP_Adapter_Core|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return UCP_Adapter_Core
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
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include dependencies.
	 *
	 * @return void
	 */
	private function includes()
	{
		require_once UCP_ADAPTER_PLUGIN_DIR . 'includes/api/class-ucp-rest-api.php';
		require_once UCP_ADAPTER_PLUGIN_DIR . 'includes/core/class-ucp-session-handler.php';
		require_once UCP_ADAPTER_PLUGIN_DIR . 'includes/core/class-ucp-security.php';
		require_once UCP_ADAPTER_PLUGIN_DIR . 'includes/admin/class-ucp-admin.php';
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function init_hooks()
	{
		add_action('init', array($this, 'init'));
		add_action('rest_api_init', array('UCP_Adapter_REST_API', 'register_routes'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_action('wp_ajax_ucp_adapter_regenerate_api_key', array($this, 'ajax_regenerate_api_key'));

		if (is_admin()) {
			UCP_Adapter_Admin::get_instance();
		}

		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
	}

	/**
	 * Init plugin services.
	 *
	 * @return void
	 */
	public function init()
	{
		UCP_Adapter_Session_Handler::get_instance();
		do_action('ucp_adapter_init');
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts()
	{
		if (is_admin()) {
			return;
		}

		wp_enqueue_style(
			'ucp-adapter-frontend',
			UCP_ADAPTER_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			UCP_ADAPTER_VERSION
		);

		wp_enqueue_script(
			'ucp-adapter-frontend',
			UCP_ADAPTER_PLUGIN_URL . 'assets/js/frontend.js',
			array('jquery'),
			UCP_ADAPTER_VERSION,
			true
		);

		wp_localize_script(
			'ucp-adapter-frontend',
			'ucpAdapterData',
			array(
				'restUrl'          => rest_url('ucp/v1'),
				'acpRestUrl'       => rest_url('acp/v1'),
				'version'          => UCP_ADAPTER_VERSION,
			)
		);
	}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public function activate()
	{
		global $wpdb;

		$table_name      = $wpdb->prefix . 'ucp_adapter_sessions';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			session_id varchar(255) NOT NULL,
			session_data longtext NOT NULL,
			expires bigint(20) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY session_id (session_id),
			KEY expires (expires)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		add_option('ucp_adapter_version', UCP_ADAPTER_VERSION);
		add_option('ucp_adapter_api_key', UCP_Adapter_Security::generate_api_key());
		add_option('ucp_adapter_session_timeout', 3600);
		add_option('ucp_adapter_protocol_ucp_enabled', 1);
		add_option('ucp_adapter_protocol_acp_enabled', 1);
		add_option('ucp_adapter_store_name', get_bloginfo('name'));
		add_option('ucp_adapter_checkout_return_url', function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/'));
		add_option('ucp_adapter_terms_url', '');
		add_option('ucp_adapter_privacy_url', '');
		add_option('ucp_adapter_refund_url', '');
		add_option('ucp_adapter_enable_delegated_payment', 0);
		add_option('ucp_adapter_rate_limit_enabled', 0);
		add_option('ucp_adapter_rate_limit', 100);
		add_option('ucp_adapter_rate_window', 60);
		add_option('ucp_adapter_ip_whitelist', '');
		add_option('ucp_adapter_agent_whitelist_enabled', 0);
		add_option('ucp_adapter_agent_whitelist_domains', '');
		add_option('ucp_adapter_require_agent_signature', 0);

		flush_rewrite_rules();
	}

	/**
	 * Deactivation.
	 *
	 * @return void
	 */
	public function deactivate()
	{
		UCP_Adapter_Session_Handler::cleanup_expired_sessions();
		flush_rewrite_rules();
	}

	/**
	 * Regenerate API key from admin.
	 *
	 * @return void
	 */
	public function ajax_regenerate_api_key()
	{
		check_ajax_referer('ucp_adapter_admin', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Unauthorized.', 'ucp-adapter-for-woocommerce')), 403);
		}

		$new_key = UCP_Adapter_Security::generate_api_key();
		update_option('ucp_adapter_api_key', $new_key);

		wp_send_json_success(array('api_key' => $new_key));
	}
}

/**
 * Bootstrap plugin.
 *
 * @return UCP_Adapter_Core
 */
function ucp_adapter_plugin()
{
	return UCP_Adapter_Core::get_instance();
}

ucp_adapter_plugin();
