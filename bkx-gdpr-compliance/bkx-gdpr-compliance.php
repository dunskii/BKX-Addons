<?php
/**
 * Plugin Name: BookingX - GDPR/CCPA Compliance Suite
 * Plugin URI: https://flavflavor.dev/bookingx/addons/gdpr-compliance
 * Description: Comprehensive GDPR and CCPA compliance tools for BookingX including consent management, data export, right to erasure, and privacy policy generator.
 * Version: 1.0.0
 * Author: flavflavor.dev
 * Author URI: https://flavflavor.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-gdpr-compliance
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_GDPR_VERSION', '1.0.0' );
define( 'BKX_GDPR_PLUGIN_FILE', __FILE__ );
define( 'BKX_GDPR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_GDPR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_GDPR_MIN_BKX_VERSION', '2.0.0' );

/**
 * Activation hook.
 */
function bkx_gdpr_activate() {
	if ( ! class_exists( 'Bookingx' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'BookingX GDPR/CCPA Compliance Suite requires BookingX plugin to be installed and activated.', 'bkx-gdpr-compliance' ),
			'Plugin Activation Error',
			array( 'back_link' => true )
		);
	}

	// Create database tables.
	bkx_gdpr_create_tables();

	// Set default options.
	bkx_gdpr_set_defaults();

	// Schedule cleanup cron.
	if ( ! wp_next_scheduled( 'bkx_gdpr_data_retention_check' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_gdpr_data_retention_check' );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_gdpr_activate' );

/**
 * Create database tables.
 */
function bkx_gdpr_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$sql = array();

	// Consent records table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_consent_records (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned DEFAULT NULL,
		email varchar(255) NOT NULL,
		consent_type varchar(50) NOT NULL,
		consent_given tinyint(1) NOT NULL DEFAULT 0,
		consent_text text NOT NULL,
		ip_address varchar(45) DEFAULT NULL,
		user_agent text DEFAULT NULL,
		source varchar(100) DEFAULT NULL,
		version varchar(20) DEFAULT NULL,
		given_at datetime DEFAULT NULL,
		withdrawn_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY email (email),
		KEY consent_type (consent_type),
		KEY consent_given (consent_given)
	) $charset_collate;";

	// Data subject requests table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_data_requests (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		request_type varchar(50) NOT NULL,
		email varchar(255) NOT NULL,
		user_id bigint(20) unsigned DEFAULT NULL,
		status varchar(50) NOT NULL DEFAULT 'pending',
		verification_token varchar(255) DEFAULT NULL,
		verified_at datetime DEFAULT NULL,
		processed_at datetime DEFAULT NULL,
		processed_by bigint(20) unsigned DEFAULT NULL,
		notes text DEFAULT NULL,
		export_file varchar(255) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		expires_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY request_type (request_type),
		KEY email (email),
		KEY status (status),
		KEY verification_token (verification_token)
	) $charset_collate;";

	// Processing activities log (Article 30).
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_processing_activities (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		activity_name varchar(255) NOT NULL,
		purpose text NOT NULL,
		data_categories text NOT NULL,
		data_subjects text NOT NULL,
		recipients text DEFAULT NULL,
		transfers text DEFAULT NULL,
		retention_period varchar(100) DEFAULT NULL,
		security_measures text DEFAULT NULL,
		legal_basis varchar(100) NOT NULL,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY is_active (is_active)
	) $charset_collate;";

	// Data breach log.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_data_breaches (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		breach_date datetime NOT NULL,
		discovered_date datetime NOT NULL,
		nature text NOT NULL,
		data_affected text NOT NULL,
		subjects_affected int unsigned DEFAULT 0,
		consequences text DEFAULT NULL,
		measures_taken text DEFAULT NULL,
		dpa_notified tinyint(1) NOT NULL DEFAULT 0,
		dpa_notified_at datetime DEFAULT NULL,
		subjects_notified tinyint(1) NOT NULL DEFAULT 0,
		subjects_notified_at datetime DEFAULT NULL,
		reported_by bigint(20) unsigned DEFAULT NULL,
		status varchar(50) NOT NULL DEFAULT 'open',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY status (status),
		KEY breach_date (breach_date)
	) $charset_collate;";

	// Cookie consent log.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_cookie_consents (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		visitor_id varchar(255) NOT NULL,
		user_id bigint(20) unsigned DEFAULT NULL,
		necessary tinyint(1) NOT NULL DEFAULT 1,
		functional tinyint(1) NOT NULL DEFAULT 0,
		analytics tinyint(1) NOT NULL DEFAULT 0,
		marketing tinyint(1) NOT NULL DEFAULT 0,
		ip_address varchar(45) DEFAULT NULL,
		user_agent text DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY visitor_id (visitor_id),
		KEY user_id (user_id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	foreach ( $sql as $query ) {
		dbDelta( $query );
	}

	update_option( 'bkx_gdpr_db_version', BKX_GDPR_VERSION );
}

/**
 * Set default options.
 */
function bkx_gdpr_set_defaults() {
	$defaults = array(
		'enabled'                    => true,
		'privacy_policy_page'        => 0,
		'cookie_policy_page'         => 0,
		'data_retention_days'        => 365,
		'booking_retention_days'     => 730,
		'consent_required'           => true,
		'consent_types'              => array( 'marketing', 'analytics', 'third_party' ),
		'cookie_banner_enabled'      => true,
		'cookie_banner_position'     => 'bottom',
		'cookie_banner_style'        => 'bar',
		'dpo_email'                  => get_option( 'admin_email' ),
		'dpo_name'                   => '',
		'company_name'               => get_bloginfo( 'name' ),
		'company_address'            => '',
		'auto_delete_expired'        => false,
		'anonymize_instead_delete'   => true,
		'export_format'              => 'json',
		'request_verification'       => true,
		'request_expiry_hours'       => 48,
		'breach_notification_emails' => get_option( 'admin_email' ),
		'ccpa_enabled'               => true,
		'ccpa_do_not_sell'           => true,
	);

	if ( ! get_option( 'bkx_gdpr_settings' ) ) {
		update_option( 'bkx_gdpr_settings', $defaults );
	}
}

/**
 * Deactivation hook.
 */
function bkx_gdpr_deactivate() {
	wp_clear_scheduled_hook( 'bkx_gdpr_data_retention_check' );
	wp_clear_scheduled_hook( 'bkx_gdpr_request_expiry_check' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_gdpr_deactivate' );

/**
 * Initialize the plugin.
 */
function bkx_gdpr_init() {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_gdpr_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_GDPR_PLUGIN_DIR . 'src/autoload.php';

	// Initialize the addon.
	\BookingX\GdprCompliance\GdprComplianceAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_gdpr_init', 20 );

/**
 * Missing BookingX notice.
 */
function bkx_gdpr_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-gdpr-compliance' ),
				'<strong>BookingX GDPR/CCPA Compliance Suite</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Load text domain.
 */
function bkx_gdpr_load_textdomain() {
	load_plugin_textdomain(
		'bkx-gdpr-compliance',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', 'bkx_gdpr_load_textdomain' );
