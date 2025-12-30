<?php
/**
 * Plugin Name: BookingX Enterprise API Suite
 * Plugin URI: https://flavor-flavor.dev/bookingx/addons/enterprise-api
 * Description: Comprehensive REST API suite with GraphQL support, OAuth2 authentication, API versioning, and developer portal for enterprise integrations.
 * Version: 1.0.0
 * Author: flavor-flavor.dev
 * Author URI: https://flavor-flavor.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-enterprise-api
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\EnterpriseAPI
 */

namespace BookingX\EnterpriseAPI;

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_ENTERPRISE_API_VERSION', '1.0.0' );
define( 'BKX_ENTERPRISE_API_FILE', __FILE__ );
define( 'BKX_ENTERPRISE_API_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_ENTERPRISE_API_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Enterprise API addon class.
 */
final class EnterpriseAPIAddon {

	/**
	 * Single instance.
	 *
	 * @var EnterpriseAPIAddon|null
	 */
	private static $instance = null;

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get singleton instance.
	 *
	 * @return EnterpriseAPIAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Load dependencies.
	 */
	private function load_dependencies() {
		// Autoloader.
		spl_autoload_register( function( $class ) {
			$prefix = 'BookingX\\EnterpriseAPI\\';
			$base_dir = BKX_ENTERPRISE_API_PATH . 'src/';

			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				return;
			}

			$relative_class = substr( $class, $len );
			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require $file;
			}
		} );
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services = array(
			'oauth'        => new Services\OAuthService(),
			'api_keys'     => new Services\APIKeyService(),
			'rate_limiter' => new Services\RateLimiter(),
			'versioning'   => new Services\VersioningService(),
			'graphql'      => new Services\GraphQLService(),
			'webhooks'     => new Services\WebhookService(),
		);
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'check_dependencies' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this, 'register_api_routes' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Rate limiting.
		add_filter( 'rest_pre_dispatch', array( $this->services['rate_limiter'], 'check_rate_limit' ), 10, 3 );

		// API versioning.
		add_filter( 'rest_request_before_callbacks', array( $this->services['versioning'], 'handle_version' ), 10, 3 );

		// OAuth authentication.
		add_filter( 'determine_current_user', array( $this->services['oauth'], 'authenticate' ), 20 );
		add_filter( 'rest_authentication_errors', array( $this->services['oauth'], 'check_errors' ) );

		// GraphQL endpoint.
		add_action( 'init', array( $this->services['graphql'], 'register_endpoint' ) );

		// Webhook events.
		add_action( 'bkx_booking_created', array( $this->services['webhooks'], 'trigger' ), 10, 2 );
		add_action( 'bkx_booking_updated', array( $this->services['webhooks'], 'trigger' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this->services['webhooks'], 'trigger' ), 10, 2 );
	}

	/**
	 * Check dependencies.
	 */
	public function check_dependencies() {
		if ( ! class_exists( 'Bookingx' ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'BookingX Enterprise API Suite requires BookingX plugin to be active.', 'bkx-enterprise-api' );
				echo '</p></div>';
			} );
			return;
		}
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		$this->create_tables();
		$this->create_default_options();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Create database tables.
	 */
	private function create_tables() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$tables = array();

		// OAuth clients table.
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_oauth_clients (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id varchar(80) NOT NULL,
			client_secret varchar(255) NOT NULL,
			name varchar(255) NOT NULL,
			description text,
			redirect_uri text,
			grant_types varchar(100) DEFAULT 'authorization_code,refresh_token',
			scope text,
			user_id bigint(20) unsigned DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY client_id (client_id),
			KEY user_id (user_id)
		) {$charset};";

		// OAuth access tokens.
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_oauth_tokens (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			access_token varchar(255) NOT NULL,
			client_id varchar(80) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			expires datetime NOT NULL,
			scope text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY access_token (access_token),
			KEY client_id (client_id),
			KEY user_id (user_id),
			KEY expires (expires)
		) {$charset};";

		// OAuth refresh tokens.
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_oauth_refresh_tokens (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			refresh_token varchar(255) NOT NULL,
			client_id varchar(80) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			expires datetime NOT NULL,
			scope text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY refresh_token (refresh_token),
			KEY client_id (client_id),
			KEY expires (expires)
		) {$charset};";

		// OAuth authorization codes.
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_oauth_codes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			authorization_code varchar(255) NOT NULL,
			client_id varchar(80) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			redirect_uri text,
			expires datetime NOT NULL,
			scope text,
			code_challenge varchar(128) DEFAULT NULL,
			code_challenge_method varchar(10) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY authorization_code (authorization_code),
			KEY client_id (client_id),
			KEY expires (expires)
		) {$charset};";

		// API keys table.
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_api_keys (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			key_id varchar(32) NOT NULL,
			key_hash varchar(255) NOT NULL,
			name varchar(255) NOT NULL,
			description text,
			user_id bigint(20) unsigned NOT NULL,
			permissions text,
			rate_limit int(11) DEFAULT 1000,
			last_used datetime DEFAULT NULL,
			last_ip varchar(45) DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			expires_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY key_id (key_id),
			KEY user_id (user_id),
			KEY is_active (is_active)
		) {$charset};";

		// API request logs.
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_api_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id varchar(80) DEFAULT NULL,
			api_key_id varchar(32) DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			endpoint varchar(255) NOT NULL,
			method varchar(10) NOT NULL,
			request_headers text,
			request_body longtext,
			response_code int(11) NOT NULL,
			response_body longtext,
			duration_ms int(11) DEFAULT NULL,
			ip_address varchar(45) NOT NULL,
			user_agent text,
			api_version varchar(10) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY client_id (client_id),
			KEY api_key_id (api_key_id),
			KEY user_id (user_id),
			KEY endpoint (endpoint),
			KEY created_at (created_at)
		) {$charset};";

		// Webhooks table.
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_webhooks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			url varchar(2048) NOT NULL,
			secret varchar(255) NOT NULL,
			events text NOT NULL,
			headers text,
			is_active tinyint(1) DEFAULT 1,
			retry_count int(11) DEFAULT 3,
			timeout_seconds int(11) DEFAULT 30,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY is_active (is_active),
			KEY created_by (created_by)
		) {$charset};";

		// Webhook deliveries.
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_webhook_deliveries (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			webhook_id bigint(20) unsigned NOT NULL,
			event varchar(100) NOT NULL,
			payload longtext NOT NULL,
			response_code int(11) DEFAULT NULL,
			response_body longtext,
			duration_ms int(11) DEFAULT NULL,
			attempt int(11) DEFAULT 1,
			status enum('pending','success','failed') DEFAULT 'pending',
			error_message text,
			delivered_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY webhook_id (webhook_id),
			KEY event (event),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";

		// Rate limit tracking.
		$tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_rate_limits (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			identifier varchar(255) NOT NULL,
			endpoint varchar(255) NOT NULL,
			requests int(11) DEFAULT 0,
			window_start datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY identifier_endpoint_window (identifier, endpoint, window_start),
			KEY window_start (window_start)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'bkx_enterprise_api_db_version', '1.0.0' );
	}

	/**
	 * Create default options.
	 */
	private function create_default_options() {
		$defaults = array(
			'enabled'                   => true,
			'api_version'               => 'v1',
			'supported_versions'        => array( 'v1' ),
			'default_rate_limit'        => 1000,
			'rate_limit_window'         => 3600,
			'enable_oauth'              => true,
			'enable_api_keys'           => true,
			'enable_graphql'            => true,
			'enable_webhooks'           => true,
			'enable_logging'            => true,
			'log_request_body'          => false,
			'log_response_body'         => false,
			'log_retention_days'        => 30,
			'oauth_token_lifetime'      => 3600,
			'oauth_refresh_lifetime'    => 86400 * 30,
			'cors_allowed_origins'      => array( '*' ),
			'cors_allowed_methods'      => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS' ),
			'cors_allowed_headers'      => array( 'Authorization', 'Content-Type', 'X-API-Key', 'X-API-Version' ),
			'require_https'             => true,
			'sandbox_mode'              => false,
			'developer_portal_enabled'  => true,
		);

		if ( ! get_option( 'bkx_enterprise_api_settings' ) ) {
			update_option( 'bkx_enterprise_api_settings', $defaults );
		}
	}

	/**
	 * Initialize.
	 */
	public function init() {
		load_plugin_textdomain( 'bkx-enterprise-api', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Register rewrite rules for GraphQL.
		add_rewrite_rule( '^graphql/?$', 'index.php?bkx_graphql=1', 'top' );
		add_rewrite_tag( '%bkx_graphql%', '1' );

		// Developer portal.
		add_rewrite_rule( '^api-docs/?$', 'index.php?bkx_api_docs=1', 'top' );
		add_rewrite_tag( '%bkx_api_docs%', '1' );
	}

	/**
	 * Register API routes.
	 */
	public function register_api_routes() {
		$namespace = 'bookingx/v1';

		// OAuth endpoints.
		register_rest_route( $namespace, '/oauth/authorize', array(
			'methods'             => 'GET',
			'callback'            => array( $this->services['oauth'], 'authorize' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $namespace, '/oauth/token', array(
			'methods'             => 'POST',
			'callback'            => array( $this->services['oauth'], 'token' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $namespace, '/oauth/revoke', array(
			'methods'             => 'POST',
			'callback'            => array( $this->services['oauth'], 'revoke' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( $namespace, '/oauth/introspect', array(
			'methods'             => 'POST',
			'callback'            => array( $this->services['oauth'], 'introspect' ),
			'permission_callback' => '__return_true',
		) );

		// API key management.
		register_rest_route( $namespace, '/api-keys', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->services['api_keys'], 'list_keys' ),
				'permission_callback' => array( $this, 'can_manage_api_keys' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->services['api_keys'], 'create_key' ),
				'permission_callback' => array( $this, 'can_manage_api_keys' ),
			),
		) );

		register_rest_route( $namespace, '/api-keys/(?P<id>[a-zA-Z0-9]+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->services['api_keys'], 'get_key' ),
				'permission_callback' => array( $this, 'can_manage_api_keys' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this->services['api_keys'], 'delete_key' ),
				'permission_callback' => array( $this, 'can_manage_api_keys' ),
			),
		) );

		// Webhooks.
		register_rest_route( $namespace, '/webhooks', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->services['webhooks'], 'list_webhooks' ),
				'permission_callback' => array( $this, 'can_manage_webhooks' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->services['webhooks'], 'create_webhook' ),
				'permission_callback' => array( $this, 'can_manage_webhooks' ),
			),
		) );

		register_rest_route( $namespace, '/webhooks/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->services['webhooks'], 'get_webhook' ),
				'permission_callback' => array( $this, 'can_manage_webhooks' ),
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this->services['webhooks'], 'update_webhook' ),
				'permission_callback' => array( $this, 'can_manage_webhooks' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this->services['webhooks'], 'delete_webhook' ),
				'permission_callback' => array( $this, 'can_manage_webhooks' ),
			),
		) );

		register_rest_route( $namespace, '/webhooks/(?P<id>\d+)/test', array(
			'methods'             => 'POST',
			'callback'            => array( $this->services['webhooks'], 'test_webhook' ),
			'permission_callback' => array( $this, 'can_manage_webhooks' ),
		) );

		// API stats.
		register_rest_route( $namespace, '/stats', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_api_stats' ),
			'permission_callback' => array( $this, 'can_view_api_stats' ),
		) );
	}

	/**
	 * Permission callback for API key management.
	 *
	 * @return bool
	 */
	public function can_manage_api_keys() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission callback for webhook management.
	 *
	 * @return bool
	 */
	public function can_manage_webhooks() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission callback for viewing API stats.
	 *
	 * @return bool
	 */
	public function can_view_api_stats() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get API statistics.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_api_stats( $request ) {
		global $wpdb;

		$period = $request->get_param( 'period' ) ?: '7d';

		switch ( $period ) {
			case '24h':
				$since = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
				break;
			case '7d':
				$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
				break;
			case '30d':
				$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
				break;
			default:
				$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		}

		$stats = array(
			'total_requests' => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s",
					$since
				)
			),
			'successful_requests' => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s AND response_code >= 200 AND response_code < 300",
					$since
				)
			),
			'failed_requests' => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s AND response_code >= 400",
					$since
				)
			),
			'avg_response_time' => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT AVG(duration_ms) FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s",
					$since
				)
			),
			'unique_clients' => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT COALESCE(client_id, api_key_id, ip_address)) FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s",
					$since
				)
			),
			'top_endpoints' => $wpdb->get_results(
				$wpdb->prepare(
					"SELECT endpoint, COUNT(*) as count FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s GROUP BY endpoint ORDER BY count DESC LIMIT 10",
					$since
				)
			),
		);

		return rest_ensure_response( $stats );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Enterprise API', 'bkx-enterprise-api' ),
			__( 'Enterprise API', 'bkx-enterprise-api' ),
			'manage_options',
			'bkx-enterprise-api',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bkx_booking_page_bkx-enterprise-api' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-enterprise-api-admin',
			BKX_ENTERPRISE_API_URL . 'assets/css/admin.css',
			array(),
			BKX_ENTERPRISE_API_VERSION
		);

		wp_enqueue_script(
			'bkx-enterprise-api-admin',
			BKX_ENTERPRISE_API_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-api-fetch' ),
			BKX_ENTERPRISE_API_VERSION,
			true
		);

		wp_localize_script( 'bkx-enterprise-api-admin', 'bkxEnterpriseAPI', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'restUrl' => rest_url( 'bookingx/v1/' ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'confirm'      => __( 'Are you sure?', 'bkx-enterprise-api' ),
				'copied'       => __( 'Copied!', 'bkx-enterprise-api' ),
				'keyWarning'   => __( 'Make sure to copy your API key now. You won\'t be able to see it again!', 'bkx-enterprise-api' ),
				'testSuccess'  => __( 'Webhook test successful!', 'bkx-enterprise-api' ),
				'testFailed'   => __( 'Webhook test failed.', 'bkx-enterprise-api' ),
			),
		) );

		// Chart.js for stats.
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
		include BKX_ENTERPRISE_API_PATH . 'templates/admin/page.php';
	}

	/**
	 * Get a service instance.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		return get_option( 'bkx_enterprise_api_settings', array() );
	}
}

// Initialize.
function bkx_enterprise_api() {
	return EnterpriseAPIAddon::get_instance();
}

add_action( 'plugins_loaded', 'BookingX\\EnterpriseAPI\\bkx_enterprise_api' );
