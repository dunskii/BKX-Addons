<?php
/**
 * Plugin Name: BookingX - Healthcare Practice Management
 * Plugin URI: https://bookingx.developer.com/addons/healthcare-practice
 * Description: HIPAA-ready solution for medical practices, clinics, and healthcare providers with patient intake forms, consent management, EHR integration, and appointment reminders.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://developer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-healthcare-practice
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\HealthcarePractice
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_HEALTHCARE_VERSION', '1.0.0' );
define( 'BKX_HEALTHCARE_FILE', __FILE__ );
define( 'BKX_HEALTHCARE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_HEALTHCARE_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_HEALTHCARE_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_HEALTHCARE_PATH . 'src/autoload.php';

/**
 * Initialize the addon.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_healthcare_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_healthcare_missing_bookingx_notice' );
		return;
	}

	// Check if Addon SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		add_action( 'admin_notices', 'bkx_healthcare_missing_sdk_notice' );
		return;
	}

	// Initialize the addon.
	\BookingX\HealthcarePractice\HealthcareAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_healthcare_init', 20 );

/**
 * Display missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_healthcare_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-healthcare-practice' ),
				'<strong>BookingX - Healthcare Practice Management</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Display missing SDK notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_healthcare_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires the BookingX Addon SDK to be installed.', 'bkx-healthcare-practice' ),
				'<strong>BookingX - Healthcare Practice Management</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_healthcare_activate(): void {
	// Run migrations.
	if ( class_exists( 'BookingX\\HealthcarePractice\\Migrations\\CreateHealthcareTables' ) ) {
		$migration = new \BookingX\HealthcarePractice\Migrations\CreateHealthcareTables();
		$migration->up();
	}

	// Set default options.
	if ( false === get_option( 'bkx_healthcare_settings' ) ) {
		update_option( 'bkx_healthcare_settings', array(
			'enabled'                    => 1,
			'practice_type'              => 'general',
			'enable_patient_intake'      => 1,
			'enable_consent_forms'       => 1,
			'enable_hipaa_compliance'    => 1,
			'enable_patient_portal'      => 1,
			'enable_appointment_reminders' => 1,
			'reminder_hours'             => 24,
			'enable_insurance_verification' => 0,
			'require_consent_before_booking' => 1,
			'patient_data_retention_days' => 2555, // ~7 years
			'enable_telemedicine'        => 1,
		) );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_healthcare_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_healthcare_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_healthcare_deactivate' );
