<?php
/**
 * Plugin Name: BookingX - HubSpot Integration
 * Plugin URI: https://developer.jetonjets.com/bookingx/addons/hubspot
 * Description: HubSpot CRM and marketing automation integration for BookingX with contact sync and deal creation.
 * Version: 1.0.0
 * Author: Developer Jeton
 * Author URI: https://developer.jetonjets.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-hubspot
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\HubSpot
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_HUBSPOT_VERSION', '1.0.0' );
define( 'BKX_HUBSPOT_FILE', __FILE__ );
define( 'BKX_HUBSPOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_HUBSPOT_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_HUBSPOT_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements.
define( 'BKX_HUBSPOT_MIN_PHP', '7.4' );
define( 'BKX_HUBSPOT_MIN_WP', '5.8' );
define( 'BKX_HUBSPOT_MIN_BKX', '2.0.0' );

/**
 * Check plugin requirements.
 *
 * @return bool
 */
function bkx_hubspot_check_requirements() {
	$errors = array();

	// Check PHP version.
	if ( version_compare( PHP_VERSION, BKX_HUBSPOT_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required PHP version 2: Current PHP version */
			__( 'BookingX HubSpot Integration requires PHP %1$s or higher. You are running PHP %2$s.', 'bkx-hubspot' ),
			BKX_HUBSPOT_MIN_PHP,
			PHP_VERSION
		);
	}

	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), BKX_HUBSPOT_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WP version 2: Current WP version */
			__( 'BookingX HubSpot Integration requires WordPress %1$s or higher. You are running WordPress %2$s.', 'bkx-hubspot' ),
			BKX_HUBSPOT_MIN_WP,
			get_bloginfo( 'version' )
		);
	}

	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		$errors[] = __( 'BookingX HubSpot Integration requires the BookingX plugin to be installed and activated.', 'bkx-hubspot' );
	}

	if ( ! empty( $errors ) ) {
		add_action(
			'admin_notices',
			function () use ( $errors ) {
				foreach ( $errors as $error ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
				}
			}
		);
		return false;
	}

	return true;
}

/**
 * Initialize the plugin.
 */
function bkx_hubspot_init() {
	if ( ! bkx_hubspot_check_requirements() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_HUBSPOT_PATH . 'src/autoload.php';

	// Initialize the addon.
	\BookingX\HubSpot\HubSpotAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_hubspot_init', 20 );

/**
 * Activation hook.
 */
function bkx_hubspot_activate() {
	if ( ! bkx_hubspot_check_requirements() ) {
		return;
	}

	require_once BKX_HUBSPOT_PATH . 'src/autoload.php';

	// Create database tables.
	bkx_hubspot_create_tables();

	// Set default options.
	if ( ! get_option( 'bkx_hubspot_settings' ) ) {
		update_option(
			'bkx_hubspot_settings',
			array(
				'sync_contacts'    => true,
				'create_deals'     => true,
				'sync_on_booking'  => true,
				'sync_on_status'   => true,
				'add_to_list'      => false,
				'track_activities' => true,
			)
		);
	}

	// Schedule sync cron.
	if ( ! wp_next_scheduled( 'bkx_hubspot_sync_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'bkx_hubspot_sync_cron' );
	}

	// Set DB version.
	update_option( 'bkx_hubspot_db_version', BKX_HUBSPOT_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_hubspot_activate' );

/**
 * Deactivation hook.
 */
function bkx_hubspot_deactivate() {
	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_hubspot_sync_cron' );
	wp_clear_scheduled_hook( 'bkx_hubspot_token_refresh' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_hubspot_deactivate' );

/**
 * Create database tables.
 */
function bkx_hubspot_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Sync mappings table - links WP objects to HubSpot records.
	$table_mappings = $wpdb->prefix . 'bkx_hs_mappings';
	$sql_mappings   = "CREATE TABLE {$table_mappings} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		wp_object_type varchar(50) NOT NULL,
		wp_object_id bigint(20) unsigned NOT NULL,
		hs_object_type varchar(50) NOT NULL,
		hs_object_id varchar(50) NOT NULL,
		sync_status varchar(20) DEFAULT 'synced',
		last_sync datetime DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY wp_hs_mapping (wp_object_type, wp_object_id, hs_object_type),
		KEY hs_object_id (hs_object_id),
		KEY sync_status (sync_status)
	) {$charset_collate};";

	// Sync logs table.
	$table_logs = $wpdb->prefix . 'bkx_hs_logs';
	$sql_logs   = "CREATE TABLE {$table_logs} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		direction varchar(10) NOT NULL,
		action varchar(50) NOT NULL,
		wp_object_type varchar(50) DEFAULT NULL,
		wp_object_id bigint(20) unsigned DEFAULT NULL,
		hs_object_type varchar(50) DEFAULT NULL,
		hs_object_id varchar(50) DEFAULT NULL,
		status varchar(20) NOT NULL,
		message text DEFAULT NULL,
		request_data longtext DEFAULT NULL,
		response_data longtext DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY direction (direction),
		KEY status (status),
		KEY created_at (created_at)
	) {$charset_collate};";

	// Property mappings table.
	$table_props = $wpdb->prefix . 'bkx_hs_property_mappings';
	$sql_props   = "CREATE TABLE {$table_props} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		object_type varchar(50) NOT NULL,
		wp_field varchar(100) NOT NULL,
		hs_property varchar(100) NOT NULL,
		sync_direction varchar(20) DEFAULT 'both',
		transform varchar(50) DEFAULT NULL,
		is_active tinyint(1) DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY object_prop (object_type, wp_field, hs_property),
		KEY is_active (is_active)
	) {$charset_collate};";

	// Queue table for batch operations.
	$table_queue = $wpdb->prefix . 'bkx_hs_queue';
	$sql_queue   = "CREATE TABLE {$table_queue} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		operation varchar(50) NOT NULL,
		wp_object_type varchar(50) NOT NULL,
		wp_object_id bigint(20) unsigned NOT NULL,
		priority int(11) DEFAULT 10,
		attempts int(11) DEFAULT 0,
		max_attempts int(11) DEFAULT 3,
		status varchar(20) DEFAULT 'pending',
		scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
		processed_at datetime DEFAULT NULL,
		error_message text DEFAULT NULL,
		PRIMARY KEY (id),
		KEY status_priority (status, priority, scheduled_at),
		KEY wp_object (wp_object_type, wp_object_id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_mappings );
	dbDelta( $sql_logs );
	dbDelta( $sql_props );
	dbDelta( $sql_queue );

	// Insert default property mappings.
	bkx_hubspot_insert_default_mappings( $table_props );
}

/**
 * Insert default property mappings.
 *
 * @param string $table_name The property mappings table name.
 */
function bkx_hubspot_insert_default_mappings( $table_name ) {
	global $wpdb;

	// Check if mappings exist.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
	if ( $count > 0 ) {
		return;
	}

	// Default Contact mappings.
	$contact_mappings = array(
		array( 'contact', 'customer_first_name', 'firstname', 'both' ),
		array( 'contact', 'customer_last_name', 'lastname', 'both' ),
		array( 'contact', 'customer_email', 'email', 'both' ),
		array( 'contact', 'customer_phone', 'phone', 'both' ),
		array( 'contact', 'billing_address_1', 'address', 'wp_to_hs' ),
		array( 'contact', 'billing_city', 'city', 'wp_to_hs' ),
		array( 'contact', 'billing_state', 'state', 'wp_to_hs' ),
		array( 'contact', 'billing_postcode', 'zip', 'wp_to_hs' ),
		array( 'contact', 'billing_country', 'country', 'wp_to_hs' ),
	);

	// Default Deal mappings.
	$deal_mappings = array(
		array( 'deal', 'booking_total', 'amount', 'wp_to_hs' ),
		array( 'deal', 'booking_date', 'closedate', 'wp_to_hs' ),
		array( 'deal', 'booking_service', 'dealname', 'wp_to_hs' ),
	);

	$all_mappings = array_merge( $contact_mappings, $deal_mappings );

	foreach ( $all_mappings as $mapping ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table_name,
			array(
				'object_type'    => $mapping[0],
				'wp_field'       => $mapping[1],
				'hs_property'    => $mapping[2],
				'sync_direction' => $mapping[3],
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}
}
