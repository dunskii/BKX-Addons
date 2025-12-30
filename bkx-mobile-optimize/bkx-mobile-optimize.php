<?php
/**
 * Plugin Name: BookingX - Mobile Booking Optimization
 * Plugin URI: https://flavor-flavor-flavor.dev/bookingx/addons/mobile-optimize
 * Description: Optimize the booking experience for mobile users with responsive forms, touch-friendly interfaces, and mobile-specific features.
 * Version: 1.0.0
 * Author: flavor-flavor-flavor.dev
 * Author URI: https://flavor-flavor-flavor.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-mobile-optimize
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\MobileOptimize
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_MOBILE_OPTIMIZE_VERSION', '1.0.0' );
define( 'BKX_MOBILE_OPTIMIZE_PLUGIN_FILE', __FILE__ );
define( 'BKX_MOBILE_OPTIMIZE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_MOBILE_OPTIMIZE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader.
require_once BKX_MOBILE_OPTIMIZE_PLUGIN_DIR . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function bkx_mobile_optimize_init() {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_mobile_optimize_missing_bookingx_notice' );
		return;
	}

	// Initialize the addon.
	\BookingX\MobileOptimize\MobileOptimizeAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_mobile_optimize_init', 20 );

/**
 * Missing BookingX notice.
 *
 * @return void
 */
function bkx_mobile_optimize_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'BookingX - Mobile Booking Optimization', 'bkx-mobile-optimize' ); ?></strong>
			<?php esc_html_e( 'requires the BookingX plugin to be installed and activated.', 'bkx-mobile-optimize' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation.
 *
 * @return void
 */
function bkx_mobile_optimize_activate() {
	$default_settings = array(
		'enabled'                  => true,
		'responsive_form'          => true,
		'touch_friendly'           => true,
		'swipe_calendar'           => true,
		'floating_cta'             => true,
		'bottom_sheet_picker'      => true,
		'haptic_feedback'          => true,
		'one_tap_booking'          => false,
		'express_checkout'         => true,
		'smart_autofill'           => true,
		'location_detection'       => false,
		'mobile_payments'          => true,
		'lazy_load_images'         => true,
		'skeleton_loading'         => true,
		'reduced_motion'           => true,
		'mobile_breakpoint'        => 768,
		'tablet_breakpoint'        => 1024,
		'form_step_indicator'      => true,
		'keyboard_optimization'    => true,
		'click_to_call'            => true,
		'sms_confirmation'         => false,
	);

	if ( ! get_option( 'bkx_mobile_optimize_settings' ) ) {
		update_option( 'bkx_mobile_optimize_settings', $default_settings );
	}

	update_option( 'bkx_mobile_optimize_version', BKX_MOBILE_OPTIMIZE_VERSION );
}
register_activation_hook( __FILE__, 'bkx_mobile_optimize_activate' );
