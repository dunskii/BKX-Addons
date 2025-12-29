<?php
/**
 * Plugin Name: BookingX - Salesforce Connector
 * Plugin URI: https://developer.jetonjets.com/bookingx/addons/salesforce
 * Description: Enterprise Salesforce CRM integration for BookingX with contact, lead, and opportunity sync.
 * Version: 1.0.0
 * Author: Developer Jeton
 * Author URI: https://developer.jetonjets.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-salesforce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 *
 * @package BookingX\Salesforce
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_SALESFORCE_VERSION', '1.0.0' );
define( 'BKX_SALESFORCE_FILE', __FILE__ );
define( 'BKX_SALESFORCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_SALESFORCE_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_SALESFORCE_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements.
define( 'BKX_SALESFORCE_MIN_PHP', '7.4' );
define( 'BKX_SALESFORCE_MIN_WP', '5.8' );
define( 'BKX_SALESFORCE_MIN_BKX', '2.0.0' );

/**
 * Check plugin requirements.
 *
 * @return bool
 */
function bkx_salesforce_check_requirements() {
	$errors = array();

	// Check PHP version.
	if ( version_compare( PHP_VERSION, BKX_SALESFORCE_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required PHP version 2: Current PHP version */
			__( 'BookingX Salesforce Connector requires PHP %1$s or higher. You are running PHP %2$s.', 'bkx-salesforce' ),
			BKX_SALESFORCE_MIN_PHP,
			PHP_VERSION
		);
	}

	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), BKX_SALESFORCE_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WP version 2: Current WP version */
			__( 'BookingX Salesforce Connector requires WordPress %1$s or higher. You are running WordPress %2$s.', 'bkx-salesforce' ),
			BKX_SALESFORCE_MIN_WP,
			get_bloginfo( 'version' )
		);
	}

	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		$errors[] = __( 'BookingX Salesforce Connector requires the BookingX plugin to be installed and activated.', 'bkx-salesforce' );
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
function bkx_salesforce_init() {
	if ( ! bkx_salesforce_check_requirements() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_SALESFORCE_PATH . 'src/autoload.php';

	// Initialize the addon.
	\BookingX\Salesforce\SalesforceAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_salesforce_init', 20 );

/**
 * Activation hook.
 */
function bkx_salesforce_activate() {
	if ( ! bkx_salesforce_check_requirements() ) {
		return;
	}

	require_once BKX_SALESFORCE_PATH . 'src/autoload.php';

	// Create database tables.
	bkx_salesforce_create_tables();

	// Set default options.
	if ( ! get_option( 'bkx_salesforce_settings' ) ) {
		update_option(
			'bkx_salesforce_settings',
			array(
				'sync_contacts'        => true,
				'sync_leads'           => false,
				'create_opportunities' => true,
				'sync_on_booking'      => true,
				'sync_on_status'       => true,
			)
		);
	}

	// Schedule sync cron.
	if ( ! wp_next_scheduled( 'bkx_salesforce_sync_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'bkx_salesforce_sync_cron' );
	}

	// Set DB version.
	update_option( 'bkx_salesforce_db_version', BKX_SALESFORCE_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_salesforce_activate' );

/**
 * Deactivation hook.
 */
function bkx_salesforce_deactivate() {
	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_salesforce_sync_cron' );
	wp_clear_scheduled_hook( 'bkx_salesforce_token_refresh' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_salesforce_deactivate' );

/**
 * Create database tables.
 */
function bkx_salesforce_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Sync mappings table - links WP objects to Salesforce records.
	$table_mappings = $wpdb->prefix . 'bkx_sf_mappings';
	$sql_mappings   = "CREATE TABLE {$table_mappings} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		wp_object_type varchar(50) NOT NULL,
		wp_object_id bigint(20) unsigned NOT NULL,
		sf_object_type varchar(50) NOT NULL,
		sf_object_id varchar(18) NOT NULL,
		sync_status varchar(20) DEFAULT 'synced',
		last_sync datetime DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY wp_sf_mapping (wp_object_type, wp_object_id, sf_object_type),
		KEY sf_object_id (sf_object_id),
		KEY sync_status (sync_status)
	) {$charset_collate};";

	// Sync logs table.
	$table_logs = $wpdb->prefix . 'bkx_sf_logs';
	$sql_logs   = "CREATE TABLE {$table_logs} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		direction varchar(10) NOT NULL,
		action varchar(50) NOT NULL,
		wp_object_type varchar(50) DEFAULT NULL,
		wp_object_id bigint(20) unsigned DEFAULT NULL,
		sf_object_type varchar(50) DEFAULT NULL,
		sf_object_id varchar(18) DEFAULT NULL,
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

	// Field mappings table - custom field configurations.
	$table_fields = $wpdb->prefix . 'bkx_sf_field_mappings';
	$sql_fields   = "CREATE TABLE {$table_fields} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		object_type varchar(50) NOT NULL,
		wp_field varchar(100) NOT NULL,
		sf_field varchar(100) NOT NULL,
		sync_direction varchar(20) DEFAULT 'both',
		transform varchar(50) DEFAULT NULL,
		is_active tinyint(1) DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY object_field (object_type, wp_field, sf_field),
		KEY is_active (is_active)
	) {$charset_collate};";

	// Queue table for batch operations.
	$table_queue = $wpdb->prefix . 'bkx_sf_queue';
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
	dbDelta( $sql_fields );
	dbDelta( $sql_queue );

	// Insert default field mappings.
	bkx_salesforce_insert_default_mappings( $table_fields );
}

/**
 * Insert default field mappings.
 *
 * @param string $table_name The field mappings table name.
 */
function bkx_salesforce_insert_default_mappings( $table_name ) {
	global $wpdb;

	// Check if mappings exist.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
	if ( $count > 0 ) {
		return;
	}

	// Default Contact mappings.
	$contact_mappings = array(
		array( 'Contact', 'customer_first_name', 'FirstName', 'both' ),
		array( 'Contact', 'customer_last_name', 'LastName', 'both' ),
		array( 'Contact', 'customer_email', 'Email', 'both' ),
		array( 'Contact', 'customer_phone', 'Phone', 'both' ),
		array( 'Contact', 'billing_address_1', 'MailingStreet', 'wp_to_sf' ),
		array( 'Contact', 'billing_city', 'MailingCity', 'wp_to_sf' ),
		array( 'Contact', 'billing_state', 'MailingState', 'wp_to_sf' ),
		array( 'Contact', 'billing_postcode', 'MailingPostalCode', 'wp_to_sf' ),
		array( 'Contact', 'billing_country', 'MailingCountry', 'wp_to_sf' ),
	);

	// Default Lead mappings.
	$lead_mappings = array(
		array( 'Lead', 'customer_first_name', 'FirstName', 'wp_to_sf' ),
		array( 'Lead', 'customer_last_name', 'LastName', 'wp_to_sf' ),
		array( 'Lead', 'customer_email', 'Email', 'wp_to_sf' ),
		array( 'Lead', 'customer_phone', 'Phone', 'wp_to_sf' ),
		array( 'Lead', 'booking_service', 'Company', 'wp_to_sf' ),
	);

	// Default Opportunity mappings.
	$opportunity_mappings = array(
		array( 'Opportunity', 'booking_total', 'Amount', 'wp_to_sf' ),
		array( 'Opportunity', 'booking_date', 'CloseDate', 'wp_to_sf' ),
		array( 'Opportunity', 'booking_service', 'Name', 'wp_to_sf' ),
	);

	$all_mappings = array_merge( $contact_mappings, $lead_mappings, $opportunity_mappings );

	foreach ( $all_mappings as $mapping ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table_name,
			array(
				'object_type'    => $mapping[0],
				'wp_field'       => $mapping[1],
				'sf_field'       => $mapping[2],
				'sync_direction' => $mapping[3],
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}
}
