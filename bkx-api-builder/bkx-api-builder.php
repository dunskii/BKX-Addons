<?php
/**
 * Plugin Name: BookingX Custom API Builder
 * Plugin URI: https://flavflavor.developer/add-ons/api-builder
 * Description: Build custom REST API endpoints for BookingX with visual endpoint designer, authentication, rate limiting, and API documentation.
 * Version: 1.0.0
 * Author: Developer Starter
 * Author URI: https://developer.developer
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-api-builder
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\APIBuilder
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_API_BUILDER_VERSION', '1.0.0' );
define( 'BKX_API_BUILDER_FILE', __FILE__ );
define( 'BKX_API_BUILDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_API_BUILDER_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_API_BUILDER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize the plugin.
 */
function bkx_api_builder_init() {
	// Check for BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_api_builder_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_API_BUILDER_PATH . 'src/autoload.php';

	// Initialize addon.
	\BookingX\APIBuilder\APIBuilderAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_api_builder_init', 20 );

/**
 * Missing BookingX notice.
 */
function bkx_api_builder_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX Custom API Builder requires BookingX plugin to be installed and activated.', 'bkx-api-builder' ); ?></p>
	</div>
	<?php
}

/**
 * Activation hook.
 */
function bkx_api_builder_activate() {
	// Create database tables.
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Custom endpoints table.
	$endpoints_table = $wpdb->prefix . 'bkx_api_endpoints';
	$sql_endpoints   = "CREATE TABLE $endpoints_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		slug varchar(255) NOT NULL,
		method varchar(10) NOT NULL DEFAULT 'GET',
		namespace varchar(100) NOT NULL DEFAULT 'bkx/v1',
		route varchar(255) NOT NULL,
		description text,
		handler_type varchar(50) NOT NULL DEFAULT 'query',
		handler_config longtext,
		request_schema longtext,
		response_schema longtext,
		authentication varchar(50) NOT NULL DEFAULT 'none',
		rate_limit int(11) NOT NULL DEFAULT 0,
		rate_limit_window int(11) NOT NULL DEFAULT 3600,
		cache_enabled tinyint(1) NOT NULL DEFAULT 0,
		cache_ttl int(11) NOT NULL DEFAULT 300,
		permissions text,
		status varchar(20) NOT NULL DEFAULT 'active',
		version varchar(10) NOT NULL DEFAULT '1.0',
		created_by bigint(20) unsigned NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug),
		KEY namespace_route (namespace, route),
		KEY status (status)
	) $charset_collate;";

	// API keys table.
	$keys_table = $wpdb->prefix . 'bkx_api_keys';
	$sql_keys   = "CREATE TABLE $keys_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		api_key varchar(64) NOT NULL,
		api_secret varchar(64) NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		permissions text,
		rate_limit int(11) NOT NULL DEFAULT 1000,
		rate_limit_window int(11) NOT NULL DEFAULT 3600,
		allowed_ips text,
		allowed_origins text,
		last_used_at datetime DEFAULT NULL,
		last_ip varchar(45) DEFAULT NULL,
		request_count bigint(20) unsigned NOT NULL DEFAULT 0,
		expires_at datetime DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY api_key (api_key),
		KEY user_id (user_id),
		KEY status (status)
	) $charset_collate;";

	// API logs table.
	$logs_table = $wpdb->prefix . 'bkx_api_logs';
	$sql_logs   = "CREATE TABLE $logs_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		endpoint_id bigint(20) unsigned DEFAULT NULL,
		api_key_id bigint(20) unsigned DEFAULT NULL,
		method varchar(10) NOT NULL,
		route varchar(255) NOT NULL,
		request_headers longtext,
		request_body longtext,
		response_code int(11) NOT NULL,
		response_body longtext,
		response_time float NOT NULL DEFAULT 0,
		ip_address varchar(45) NOT NULL,
		user_agent varchar(255) DEFAULT NULL,
		error_message text,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY endpoint_id (endpoint_id),
		KEY api_key_id (api_key_id),
		KEY response_code (response_code),
		KEY created_at (created_at)
	) $charset_collate;";

	// Rate limiting table.
	$rate_table = $wpdb->prefix . 'bkx_api_rate_limits';
	$sql_rate   = "CREATE TABLE $rate_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		identifier varchar(255) NOT NULL,
		endpoint_id bigint(20) unsigned DEFAULT NULL,
		request_count int(11) NOT NULL DEFAULT 1,
		window_start datetime NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY identifier_endpoint (identifier, endpoint_id),
		KEY window_start (window_start)
	) $charset_collate;";

	// Webhooks table.
	$webhooks_table = $wpdb->prefix . 'bkx_api_webhooks';
	$sql_webhooks   = "CREATE TABLE $webhooks_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		url varchar(500) NOT NULL,
		events text NOT NULL,
		secret varchar(64) NOT NULL,
		headers text,
		retry_count int(11) NOT NULL DEFAULT 3,
		retry_delay int(11) NOT NULL DEFAULT 60,
		timeout int(11) NOT NULL DEFAULT 30,
		status varchar(20) NOT NULL DEFAULT 'active',
		last_triggered_at datetime DEFAULT NULL,
		last_response_code int(11) DEFAULT NULL,
		failure_count int(11) NOT NULL DEFAULT 0,
		created_by bigint(20) unsigned NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY status (status),
		KEY events ((CAST(events AS CHAR(255))))
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_endpoints );
	dbDelta( $sql_keys );
	dbDelta( $sql_logs );
	dbDelta( $sql_rate );
	dbDelta( $sql_webhooks );

	// Set default options.
	$default_settings = array(
		'enable_logging'        => true,
		'log_retention_days'    => 30,
		'default_rate_limit'    => 1000,
		'rate_limit_window'     => 3600,
		'enable_cors'           => true,
		'allowed_origins'       => '*',
		'enable_documentation'  => true,
		'documentation_public'  => false,
		'require_https'         => true,
		'enable_webhooks'       => true,
		'webhook_retry_count'   => 3,
		'webhook_retry_delay'   => 60,
		'api_namespace'         => 'bkx-custom/v1',
		'enable_versioning'     => true,
	);
	add_option( 'bkx_api_builder_settings', $default_settings );
	add_option( 'bkx_api_builder_version', BKX_API_BUILDER_VERSION );
	add_option( 'bkx_api_builder_db_version', '1.0.0' );

	// Schedule cleanup cron.
	if ( ! wp_next_scheduled( 'bkx_api_builder_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_api_builder_cleanup' );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_api_builder_activate' );

/**
 * Deactivation hook.
 */
function bkx_api_builder_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_api_builder_cleanup' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_api_builder_deactivate' );
