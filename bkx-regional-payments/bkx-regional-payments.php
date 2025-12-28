<?php
/**
 * Plugin Name: BookingX - Regional Payment Hub
 * Plugin URI: https://bookingx.developer.com/addons/regional-payments
 * Description: Regional payment gateways for emerging markets including PIX (Brazil), UPI (India), SEPA (Europe), and more.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://developer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-regional-payments
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\RegionalPayments
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_REGIONAL_PAYMENTS_VERSION', '1.0.0' );
define( 'BKX_REGIONAL_PAYMENTS_FILE', __FILE__ );
define( 'BKX_REGIONAL_PAYMENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_REGIONAL_PAYMENTS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_REGIONAL_PAYMENTS_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_REGIONAL_PAYMENTS_PATH . 'src/autoload.php';

/**
 * Initialize the addon.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_regional_payments_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_regional_payments_missing_bookingx_notice' );
		return;
	}

	// Check if Addon SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		add_action( 'admin_notices', 'bkx_regional_payments_missing_sdk_notice' );
		return;
	}

	// Initialize the addon.
	\BookingX\RegionalPayments\RegionalPaymentsAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_regional_payments_init', 20 );

/**
 * Display missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_regional_payments_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-regional-payments' ),
				'<strong>BookingX - Regional Payment Hub</strong>'
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
function bkx_regional_payments_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires the BookingX Addon SDK to be installed.', 'bkx-regional-payments' ),
				'<strong>BookingX - Regional Payment Hub</strong>'
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
function bkx_regional_payments_activate(): void {
	// Set default options.
	if ( false === get_option( 'bkx_regional_payments_settings' ) ) {
		update_option( 'bkx_regional_payments_settings', array(
			'enabled_gateways' => array(),
			'auto_detect_country' => 1,
			'fallback_gateway' => '',
		) );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_regional_payments_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_regional_payments_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_regional_payments_deactivate' );
