<?php
/**
 * Plugin Name: BookingX - HIPAA Compliance
 * Plugin URI: https://bookingx.com/add-ons/hipaa
 * Description: HIPAA compliance features for healthcare providers - PHI encryption, audit logging, BAA management, and access controls.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-hipaa
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\HIPAA
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_HIPAA_VERSION', '1.0.0' );
define( 'BKX_HIPAA_PLUGIN_FILE', __FILE__ );
define( 'BKX_HIPAA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_HIPAA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_HIPAA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Admin notice for missing BookingX.
 */
function bkx_hipaa_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin link */
				esc_html__( 'BookingX HIPAA Compliance requires the %s plugin to be installed and activated.', 'bkx-hipaa' ),
				'<a href="https://bookingx.com" target="_blank">BookingX</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_hipaa_init() {
	// Check for BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_hipaa_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_HIPAA_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	\BookingX\HIPAA\HIPAAAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_hipaa_init', 20 );

/**
 * Activation hook.
 */
function bkx_hipaa_activate() {
	global $wpdb;

	// Create audit log table.
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_hipaa_audit_log (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		event_type varchar(50) NOT NULL,
		event_action varchar(100) NOT NULL,
		user_id bigint(20) UNSIGNED DEFAULT NULL,
		user_ip varchar(45) DEFAULT NULL,
		user_agent text DEFAULT NULL,
		resource_type varchar(50) DEFAULT NULL,
		resource_id bigint(20) UNSIGNED DEFAULT NULL,
		phi_accessed tinyint(1) DEFAULT 0,
		data_before longtext DEFAULT NULL,
		data_after longtext DEFAULT NULL,
		metadata longtext DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY event_type (event_type),
		KEY user_id (user_id),
		KEY resource_type_id (resource_type, resource_id),
		KEY phi_accessed (phi_accessed),
		KEY created_at (created_at)
	) $charset_collate;";

	// Create BAA table.
	$sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_hipaa_baa (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		vendor_name varchar(255) NOT NULL,
		vendor_email varchar(255) NOT NULL,
		vendor_contact varchar(255) DEFAULT NULL,
		baa_status varchar(20) NOT NULL DEFAULT 'pending',
		signed_date datetime DEFAULT NULL,
		expiry_date datetime DEFAULT NULL,
		document_path varchar(500) DEFAULT NULL,
		notes text DEFAULT NULL,
		created_by bigint(20) UNSIGNED NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY baa_status (baa_status),
		KEY expiry_date (expiry_date)
	) $charset_collate;";

	// Create access control table.
	$sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_hipaa_access_control (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id bigint(20) UNSIGNED NOT NULL,
		access_level varchar(50) NOT NULL,
		phi_fields text DEFAULT NULL,
		restrictions text DEFAULT NULL,
		granted_by bigint(20) UNSIGNED NOT NULL,
		granted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		expires_at datetime DEFAULT NULL,
		is_active tinyint(1) DEFAULT 1,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY access_level (access_level),
		KEY is_active (is_active)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Set default options.
	$defaults = array(
		'enabled'                => true,
		'encryption_method'      => 'aes-256-gcm',
		'audit_retention_days'   => 365 * 6, // 6 years per HIPAA.
		'phi_fields'             => array( 'customer_email', 'customer_phone', 'customer_name', 'booking_notes' ),
		'auto_logout_minutes'    => 15,
		'require_strong_password' => true,
		'two_factor_required'    => true,
		'access_review_days'     => 90,
		'breach_notification'    => true,
	);

	if ( ! get_option( 'bkx_hipaa_settings' ) ) {
		add_option( 'bkx_hipaa_settings', $defaults );
	}

	// Generate encryption key if not exists.
	if ( ! get_option( 'bkx_hipaa_encryption_key' ) ) {
		$key = sodium_crypto_secretbox_keygen();
		update_option( 'bkx_hipaa_encryption_key', base64_encode( $key ), false );
	}

	// Schedule cron jobs.
	if ( ! wp_next_scheduled( 'bkx_hipaa_audit_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_hipaa_audit_cleanup' );
	}

	if ( ! wp_next_scheduled( 'bkx_hipaa_access_review' ) ) {
		wp_schedule_event( time(), 'weekly', 'bkx_hipaa_access_review' );
	}

	if ( ! wp_next_scheduled( 'bkx_hipaa_baa_expiry_check' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_hipaa_baa_expiry_check' );
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_hipaa_activate' );

/**
 * Deactivation hook.
 */
function bkx_hipaa_deactivate() {
	wp_clear_scheduled_hook( 'bkx_hipaa_audit_cleanup' );
	wp_clear_scheduled_hook( 'bkx_hipaa_access_review' );
	wp_clear_scheduled_hook( 'bkx_hipaa_baa_expiry_check' );

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_hipaa_deactivate' );
