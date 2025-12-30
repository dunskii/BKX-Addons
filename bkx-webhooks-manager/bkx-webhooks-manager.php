<?php
/**
 * Plugin Name: BookingX Webhooks Manager
 * Plugin URI: https://developer.developer/add-ons/webhooks-manager
 * Description: Comprehensive webhook management for BookingX with event triggers, delivery monitoring, retry logic, and signature verification.
 * Version: 1.0.0
 * Author: Developer Starter
 * Author URI: https://developer.developer
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-webhooks-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\WebhooksManager
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_WEBHOOKS_VERSION', '1.0.0' );
define( 'BKX_WEBHOOKS_FILE', __FILE__ );
define( 'BKX_WEBHOOKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_WEBHOOKS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_WEBHOOKS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize the plugin.
 */
function bkx_webhooks_manager_init() {
	// Check for BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_webhooks_manager_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_WEBHOOKS_PATH . 'src/autoload.php';

	// Initialize addon.
	\BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_webhooks_manager_init', 20 );

/**
 * Missing BookingX notice.
 */
function bkx_webhooks_manager_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX Webhooks Manager requires BookingX plugin to be installed and activated.', 'bkx-webhooks-manager' ); ?></p>
	</div>
	<?php
}

/**
 * Activation hook.
 */
function bkx_webhooks_manager_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Webhooks table.
	$webhooks_table = $wpdb->prefix . 'bkx_webhooks';
	$sql_webhooks   = "CREATE TABLE $webhooks_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		url varchar(500) NOT NULL,
		events text NOT NULL,
		secret varchar(64) NOT NULL,
		headers text,
		payload_format varchar(20) NOT NULL DEFAULT 'json',
		http_method varchar(10) NOT NULL DEFAULT 'POST',
		content_type varchar(100) NOT NULL DEFAULT 'application/json',
		timeout int(11) NOT NULL DEFAULT 30,
		retry_count int(11) NOT NULL DEFAULT 3,
		retry_delay int(11) NOT NULL DEFAULT 60,
		retry_multiplier float NOT NULL DEFAULT 2,
		verify_ssl tinyint(1) NOT NULL DEFAULT 1,
		active_start_time time DEFAULT NULL,
		active_end_time time DEFAULT NULL,
		active_days varchar(50) DEFAULT NULL,
		conditions text,
		status varchar(20) NOT NULL DEFAULT 'active',
		last_triggered_at datetime DEFAULT NULL,
		last_response_code int(11) DEFAULT NULL,
		success_count bigint(20) unsigned NOT NULL DEFAULT 0,
		failure_count bigint(20) unsigned NOT NULL DEFAULT 0,
		created_by bigint(20) unsigned NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY status (status),
		KEY created_by (created_by)
	) $charset_collate;";

	// Delivery log table.
	$deliveries_table = $wpdb->prefix . 'bkx_webhook_deliveries';
	$sql_deliveries   = "CREATE TABLE $deliveries_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		webhook_id bigint(20) unsigned NOT NULL,
		event_type varchar(100) NOT NULL,
		event_id varchar(100) DEFAULT NULL,
		payload longtext NOT NULL,
		request_headers text,
		response_code int(11) DEFAULT NULL,
		response_body longtext,
		response_headers text,
		response_time float DEFAULT NULL,
		attempt int(11) NOT NULL DEFAULT 1,
		status varchar(20) NOT NULL DEFAULT 'pending',
		error_message text,
		scheduled_at datetime DEFAULT NULL,
		delivered_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY webhook_id (webhook_id),
		KEY event_type (event_type),
		KEY status (status),
		KEY created_at (created_at)
	) $charset_collate;";

	// Event subscriptions table.
	$subscriptions_table = $wpdb->prefix . 'bkx_webhook_subscriptions';
	$sql_subscriptions   = "CREATE TABLE $subscriptions_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		webhook_id bigint(20) unsigned NOT NULL,
		event_type varchar(100) NOT NULL,
		filter_conditions text,
		transform_template text,
		priority int(11) NOT NULL DEFAULT 10,
		status varchar(20) NOT NULL DEFAULT 'active',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY webhook_event (webhook_id, event_type),
		KEY event_type (event_type)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_webhooks );
	dbDelta( $sql_deliveries );
	dbDelta( $sql_subscriptions );

	// Set default options.
	$default_settings = array(
		'enabled'                => true,
		'async_delivery'         => true,
		'max_retries'            => 3,
		'retry_delay'            => 60,
		'default_timeout'        => 30,
		'log_retention_days'     => 30,
		'max_payload_size'       => 1048576, // 1MB.
		'signature_algorithm'    => 'sha256',
		'include_timestamp'      => true,
		'batch_delivery'         => false,
		'batch_size'             => 10,
		'batch_interval'         => 60,
		'notify_on_failure'      => true,
		'failure_threshold'      => 5,
		'failure_notification_email' => get_option( 'admin_email' ),
	);
	add_option( 'bkx_webhooks_manager_settings', $default_settings );
	add_option( 'bkx_webhooks_manager_version', BKX_WEBHOOKS_VERSION );

	// Schedule cleanup cron.
	if ( ! wp_next_scheduled( 'bkx_webhooks_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_webhooks_cleanup' );
	}

	// Schedule retry processor.
	if ( ! wp_next_scheduled( 'bkx_webhooks_process_retries' ) ) {
		wp_schedule_event( time(), 'every_minute', 'bkx_webhooks_process_retries' );
	}
}
register_activation_hook( __FILE__, 'bkx_webhooks_manager_activate' );

/**
 * Deactivation hook.
 */
function bkx_webhooks_manager_deactivate() {
	wp_clear_scheduled_hook( 'bkx_webhooks_cleanup' );
	wp_clear_scheduled_hook( 'bkx_webhooks_process_retries' );
}
register_deactivation_hook( __FILE__, 'bkx_webhooks_manager_deactivate' );

/**
 * Add custom cron schedule.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function bkx_webhooks_cron_schedules( $schedules ) {
	$schedules['every_minute'] = array(
		'interval' => 60,
		'display'  => __( 'Every Minute', 'bkx-webhooks-manager' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'bkx_webhooks_cron_schedules' );
