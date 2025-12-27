<?php
/**
 * Plugin Name: BookingX - PayPal Pro
 * Plugin URI: https://bookingx.com/addons/paypal-pro
 * Description: Accept payments via PayPal Commerce Platform with advanced card processing, Pay Later, and comprehensive webhook support.
 * Version: 1.0.0
 * Author: Booking X
 * Author URI: https://bookingx.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-paypal-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\PayPalPro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'BKX_PAYPAL_PRO_VERSION', '1.0.0' );
define( 'BKX_PAYPAL_PRO_FILE', __FILE__ );
define( 'BKX_PAYPAL_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_PAYPAL_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_PAYPAL_PRO_BASENAME', plugin_basename( __FILE__ ) );

// Require the autoloader.
require_once BKX_PAYPAL_PRO_PATH . 'autoload.php';

// Initialize the addon.
add_action( 'plugins_loaded', 'bkx_paypal_pro_init', 20 );

/**
 * Initialize the PayPal Pro addon.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_paypal_pro_init() {
	// Check if the SDK autoloader exists.
	$sdk_path = dirname( BKX_PAYPAL_PRO_PATH ) . '/_shared/bkx-addon-sdk/autoload.php';

	if ( ! file_exists( $sdk_path ) ) {
		add_action( 'admin_notices', 'bkx_paypal_pro_sdk_missing_notice' );
		return;
	}

	// Load the SDK.
	require_once $sdk_path;

	// Instantiate and initialize the addon.
	$addon = new \BookingX\PayPalPro\PayPalPro( BKX_PAYPAL_PRO_FILE );
	$addon->init();
}

/**
 * Show SDK missing notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_paypal_pro_sdk_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'BookingX - PayPal Pro:', 'bkx-paypal-pro' ); ?></strong>
			<?php esc_html_e( 'The BookingX Add-on SDK is missing. Please ensure the SDK is installed in the _shared directory.', 'bkx-paypal-pro' ); ?>
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
function bkx_paypal_pro_activate() {
	// Check if BookingX is active.
	if ( ! function_exists( 'BKX' ) ) {
		deactivate_plugins( BKX_PAYPAL_PRO_BASENAME );
		wp_die(
			esc_html__( 'This plugin requires BookingX to be installed and activated.', 'bkx-paypal-pro' ),
			esc_html__( 'Plugin Activation Error', 'bkx-paypal-pro' ),
			array( 'back_link' => true )
		);
	}

	// Set default settings.
	$defaults = array(
		'enabled'                   => false,
		'paypal_mode'               => 'sandbox',
		'paypal_sandbox_client_id'  => '',
		'paypal_sandbox_client_secret' => '',
		'paypal_live_client_id'     => '',
		'paypal_live_client_secret' => '',
		'paypal_webhook_id'         => '',
		'enable_card_fields'        => false,
		'enable_pay_later'          => true,
		'button_color'              => 'gold',
		'button_shape'              => 'rect',
		'intent'                    => 'capture',
		'currency'                  => 'USD',
		'debug_log'                 => false,
	);

	add_option( 'bkx_paypal_pro_settings', $defaults );

	// Set activation flag for running migrations.
	set_transient( 'bkx_paypal_pro_activated', true, 30 );
}
register_activation_hook( __FILE__, 'bkx_paypal_pro_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_paypal_pro_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_paypal_pro_license_check' );
	wp_clear_scheduled_hook( 'bkx_paypal_pro_token_refresh' );
}
register_deactivation_hook( __FILE__, 'bkx_paypal_pro_deactivate' );
