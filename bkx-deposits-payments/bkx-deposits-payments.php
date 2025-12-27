<?php
/**
 * Plugin Name: BookingX - Deposits & Payments
 * Plugin URI: https://bookingx.com/addons/deposits-payments
 * Description: Enable deposits and partial payments for bookings with configurable deposit amounts, balance due dates, and automated reminders.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-deposits-payments
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\DepositsPayments
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin constants.
 */
define( 'BKX_DEPOSITS_VERSION', '1.0.0' );
define( 'BKX_DEPOSITS_FILE', __FILE__ );
define( 'BKX_DEPOSITS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_DEPOSITS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_DEPOSITS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the autoloader.
 */
require_once BKX_DEPOSITS_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_deposits_payments_init() {
	// Load text domain for translations
	load_plugin_textdomain( 'bkx-deposits-payments', false, dirname( BKX_DEPOSITS_BASENAME ) . '/languages' );

	// Initialize the main addon class
	$addon = new \BookingX\DepositsPayments\DepositsPaymentsAddon( BKX_DEPOSITS_FILE );

	// Initialize the addon
	if ( $addon->init() ) {
		// Store in global for access
		$GLOBALS['bkx_deposits_payments'] = $addon;
	}
}
add_action( 'plugins_loaded', 'bkx_deposits_payments_init', 10 );

/**
 * Get the main addon instance.
 *
 * @since 1.0.0
 * @return \BookingX\DepositsPayments\DepositsPaymentsAddon|null
 */
function bkx_deposits() {
	return $GLOBALS['bkx_deposits_payments'] ?? null;
}

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_deposits_payments_activate() {
	// Check requirements before activation
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( BKX_DEPOSITS_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Deposits & Payments requires PHP 7.4 or higher.', 'bkx-deposits-payments' ),
			esc_html__( 'Plugin Activation Error', 'bkx-deposits-payments' ),
			array( 'back_link' => true )
		);
	}

	if ( ! function_exists( 'BKX' ) ) {
		deactivate_plugins( BKX_DEPOSITS_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Deposits & Payments requires the BookingX plugin to be installed and activated.', 'bkx-deposits-payments' ),
			esc_html__( 'Plugin Activation Error', 'bkx-deposits-payments' ),
			array( 'back_link' => true )
		);
	}

	// Set activation flag for migrations
	set_transient( 'bkx_deposits_activated', true, 30 );
}
register_activation_hook( BKX_DEPOSITS_FILE, 'bkx_deposits_payments_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_deposits_payments_deactivate() {
	// Clear scheduled tasks
	wp_clear_scheduled_hook( 'bkx_deposits_balance_reminders' );
	wp_clear_scheduled_hook( 'bkx_deposits_cleanup' );

	// Clear caches
	delete_transient( 'bkx_deposits_activated' );
}
register_deactivation_hook( BKX_DEPOSITS_FILE, 'bkx_deposits_payments_deactivate' );
