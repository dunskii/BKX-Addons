<?php
/**
 * Plugin Name: BookingX - IFTTT Integration
 * Plugin URI: https://flavor-flavor-flavor.local/plugins/bkx-ifttt
 * Description: Connect BookingX with IFTTT for powerful automation with 700+ apps and services.
 * Version: 1.0.0
 * Author: flavor-flavor-flavor.local
 * Author URI: https://flavor-flavor-flavor.local
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-ifttt
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\IFTTT
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_IFTTT_VERSION', '1.0.0' );
define( 'BKX_IFTTT_PLUGIN_FILE', __FILE__ );
define( 'BKX_IFTTT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_IFTTT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_IFTTT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_ifttt_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_ifttt_missing_bookingx_notice' );
		return false;
	}
	return true;
}

/**
 * Show notice if BookingX is not active.
 */
function bkx_ifttt_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-ifttt' ),
				'<strong>BookingX IFTTT Integration</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_ifttt_init() {
	if ( ! bkx_ifttt_check_dependencies() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_IFTTT_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	\BookingX\IFTTT\IFTTTAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_ifttt_init', 20 );

/**
 * Plugin activation.
 */
function bkx_ifttt_activate() {
	// Set default options.
	$defaults = array(
		'enabled'          => false,
		'service_key'      => wp_generate_password( 32, false ),
		'triggers'         => array(
			'booking_created'   => true,
			'booking_confirmed' => true,
			'booking_cancelled' => true,
			'booking_completed' => true,
			'booking_reminder'  => true,
		),
		'actions'          => array(
			'create_booking' => true,
			'cancel_booking' => true,
			'update_booking' => true,
		),
		'webhooks'         => array(),
		'rate_limit'       => 100,
		'log_requests'     => false,
	);

	if ( ! get_option( 'bkx_ifttt_settings' ) ) {
		add_option( 'bkx_ifttt_settings', $defaults );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_ifttt_activate' );

/**
 * Plugin deactivation.
 */
function bkx_ifttt_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_ifttt_deactivate' );
