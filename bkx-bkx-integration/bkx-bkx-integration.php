<?php
/**
 * Plugin Name: BookingX - BKX to BKX Integration
 * Plugin URI: https://developer.jetonx.com/addons/bkx-integration
 * Description: Synchronize bookings, availability, and customer data between multiple BookingX installations for franchises and distributed businesses.
 * Version: 1.0.0
 * Author: JetonX
 * Author URI: https://developer.jetonx.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-bkx-integration
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\BkxIntegration
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_BKX_VERSION', '1.0.0' );
define( 'BKX_BKX_PLUGIN_FILE', __FILE__ );
define( 'BKX_BKX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_BKX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_BKX_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_bkx_is_bookingx_active() {
	return class_exists( 'Bookingx' ) || in_array( 'bookingx/bookingx.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
}

/**
 * Admin notice for missing BookingX.
 */
function bkx_bkx_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-bkx-integration' ),
				'<strong>BookingX BKX to BKX Integration</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_bkx_init() {
	if ( ! bkx_bkx_is_bookingx_active() ) {
		add_action( 'admin_notices', 'bkx_bkx_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_BKX_PLUGIN_DIR . 'src/autoload.php';

	// Initialize main addon class.
	\BookingX\BkxIntegration\BkxIntegrationAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_bkx_init', 20 );

/**
 * Activation hook.
 */
function bkx_bkx_activate() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	// Remote sites table.
	$table_sites = $wpdb->prefix . 'bkx_remote_sites';
	$sql_sites   = "CREATE TABLE {$table_sites} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		url varchar(255) NOT NULL,
		api_key varchar(255) NOT NULL,
		api_secret varchar(255) NOT NULL,
		direction varchar(20) DEFAULT 'both',
		status varchar(20) DEFAULT 'active',
		sync_bookings tinyint(1) DEFAULT 1,
		sync_availability tinyint(1) DEFAULT 1,
		sync_customers tinyint(1) DEFAULT 0,
		sync_services tinyint(1) DEFAULT 0,
		sync_staff tinyint(1) DEFAULT 0,
		last_sync datetime DEFAULT NULL,
		last_error text,
		settings longtext,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY url (url),
		KEY status (status)
	) {$charset_collate};";

	dbDelta( $sql_sites );

	// Sync mappings table (maps local IDs to remote IDs).
	$table_mappings = $wpdb->prefix . 'bkx_remote_mappings';
	$sql_mappings   = "CREATE TABLE {$table_mappings} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		site_id bigint(20) unsigned NOT NULL,
		object_type varchar(50) NOT NULL,
		local_id bigint(20) unsigned NOT NULL,
		remote_id varchar(255) NOT NULL,
		sync_hash varchar(64) DEFAULT '',
		last_synced datetime DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY site_object (site_id, object_type, local_id),
		KEY site_id (site_id),
		KEY object_type (object_type),
		KEY remote_id (remote_id)
	) {$charset_collate};";

	dbDelta( $sql_mappings );

	// Sync logs table.
	$table_logs = $wpdb->prefix . 'bkx_remote_logs';
	$sql_logs   = "CREATE TABLE {$table_logs} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		site_id bigint(20) unsigned NOT NULL,
		direction varchar(20) NOT NULL,
		action varchar(50) NOT NULL,
		object_type varchar(50) NOT NULL,
		local_id bigint(20) unsigned DEFAULT NULL,
		remote_id varchar(255) DEFAULT '',
		status varchar(20) NOT NULL,
		message text,
		request_data longtext,
		response_data longtext,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY site_id (site_id),
		KEY direction (direction),
		KEY status (status),
		KEY created_at (created_at)
	) {$charset_collate};";

	dbDelta( $sql_logs );

	// Sync queue table.
	$table_queue = $wpdb->prefix . 'bkx_remote_queue';
	$sql_queue   = "CREATE TABLE {$table_queue} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		site_id bigint(20) unsigned NOT NULL,
		action varchar(50) NOT NULL,
		object_type varchar(50) NOT NULL,
		object_id bigint(20) unsigned NOT NULL,
		payload longtext,
		priority int(11) DEFAULT 10,
		attempts int(11) DEFAULT 0,
		max_attempts int(11) DEFAULT 3,
		scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
		processed_at datetime DEFAULT NULL,
		status varchar(20) DEFAULT 'pending',
		error_message text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY site_id (site_id),
		KEY status (status),
		KEY scheduled_at (scheduled_at),
		KEY priority (priority)
	) {$charset_collate};";

	dbDelta( $sql_queue );

	// Conflict resolution table.
	$table_conflicts = $wpdb->prefix . 'bkx_remote_conflicts';
	$sql_conflicts   = "CREATE TABLE {$table_conflicts} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		site_id bigint(20) unsigned NOT NULL,
		object_type varchar(50) NOT NULL,
		local_id bigint(20) unsigned NOT NULL,
		remote_id varchar(255) NOT NULL,
		local_data longtext,
		remote_data longtext,
		conflict_type varchar(50) NOT NULL,
		resolution varchar(50) DEFAULT NULL,
		resolved_at datetime DEFAULT NULL,
		resolved_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY site_id (site_id),
		KEY resolution (resolution)
	) {$charset_collate};";

	dbDelta( $sql_conflicts );

	// Generate local API credentials if not exists.
	if ( ! get_option( 'bkx_bkx_api_key' ) ) {
		update_option( 'bkx_bkx_api_key', wp_generate_password( 32, false ) );
		update_option( 'bkx_bkx_api_secret', wp_generate_password( 64, false ) );
	}

	// Store database version.
	update_option( 'bkx_bkx_db_version', BKX_BKX_VERSION );

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_bkx_activate' );

/**
 * Deactivation hook.
 */
function bkx_bkx_deactivate() {
	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_bkx_process_queue' );
	wp_clear_scheduled_hook( 'bkx_bkx_sync_availability' );
	wp_clear_scheduled_hook( 'bkx_bkx_health_check' );
	wp_clear_scheduled_hook( 'bkx_bkx_cleanup_logs' );

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_bkx_deactivate' );
