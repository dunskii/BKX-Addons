<?php
/**
 * Plugin Name: BookingX - Legal & Professional Services
 * Plugin URI: https://bookingx.developer.com/addons/legal-professional
 * Description: Professional scheduling solution for law firms, consulting agencies, and professional service providers with client intake, case management, document handling, and billing integration.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://developer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-legal-professional
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\LegalProfessional
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_LEGAL_VERSION', '1.0.0' );
define( 'BKX_LEGAL_FILE', __FILE__ );
define( 'BKX_LEGAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_LEGAL_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_LEGAL_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_LEGAL_PATH . 'src/autoload.php';

/**
 * Initialize the addon.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_legal_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_legal_missing_bookingx_notice' );
		return;
	}

	// Check if Addon SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		add_action( 'admin_notices', 'bkx_legal_missing_sdk_notice' );
		return;
	}

	// Initialize the addon.
	\BookingX\LegalProfessional\LegalAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_legal_init', 20 );

/**
 * Display missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_legal_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-legal-professional' ),
				'<strong>BookingX - Legal & Professional Services</strong>'
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
function bkx_legal_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires the BookingX Addon SDK to be installed.', 'bkx-legal-professional' ),
				'<strong>BookingX - Legal & Professional Services</strong>'
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
function bkx_legal_activate(): void {
	// Run migrations.
	if ( class_exists( 'BookingX\\LegalProfessional\\Migrations\\CreateLegalTables' ) ) {
		$migration = new \BookingX\LegalProfessional\Migrations\CreateLegalTables();
		$migration->up();
	}

	// Set default options.
	if ( false === get_option( 'bkx_legal_settings' ) ) {
		update_option( 'bkx_legal_settings', array(
			'enabled'                     => 1,
			'practice_type'               => 'law_firm',
			'enable_client_intake'        => 1,
			'enable_case_management'      => 1,
			'enable_document_management'  => 1,
			'enable_conflict_check'       => 1,
			'enable_retainer_agreements'  => 1,
			'enable_billing_tracking'     => 1,
			'enable_client_portal'        => 1,
			'consultation_fee'            => 0,
			'default_consultation_duration' => 60,
			'require_intake_before_booking' => 1,
			'enable_confidentiality_notice' => 1,
			'time_tracking_increment'     => 6, // 6-minute billing.
			'enable_matter_types'         => 1,
		) );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_legal_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_legal_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_legal_deactivate' );
