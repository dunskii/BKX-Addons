<?php
/**
 * Plugin Name: BookingX - Stripe Payments
 * Plugin URI: https://bookingx.com/addons/stripe-payments
 * Description: Accept credit card payments via Stripe for BookingX bookings. Supports PaymentIntents, 3D Secure, Apple Pay, Google Pay, and recurring subscriptions.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-stripe-payments
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\StripePayments
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin constants.
 */
define( 'BKX_STRIPE_VERSION', '1.0.0' );
define( 'BKX_STRIPE_FILE', __FILE__ );
define( 'BKX_STRIPE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_STRIPE_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_STRIPE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the autoloader.
 */
require_once BKX_STRIPE_PATH . 'autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_stripe_payments_init() {
	// Load text domain for translations
	load_plugin_textdomain( 'bkx-stripe-payments', false, dirname( BKX_STRIPE_BASENAME ) . '/languages' );

	// Initialize the main addon class
	$stripe_addon = new \BookingX\StripePayments\StripePayments( BKX_STRIPE_FILE );

	// Initialize the addon
	if ( $stripe_addon->init() ) {
		// Store in global for access
		$GLOBALS['bkx_stripe_payments'] = $stripe_addon;
	}
}
add_action( 'plugins_loaded', 'bkx_stripe_payments_init', 10 );

/**
 * Get the main addon instance.
 *
 * @since 1.0.0
 * @return \BookingX\StripePayments\StripePayments|null
 */
function bkx_stripe() {
	return $GLOBALS['bkx_stripe_payments'] ?? null;
}

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_stripe_payments_activate() {
	// Check requirements before activation
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( BKX_STRIPE_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Stripe Payments requires PHP 7.4 or higher.', 'bkx-stripe-payments' ),
			esc_html__( 'Plugin Activation Error', 'bkx-stripe-payments' ),
			array( 'back_link' => true )
		);
	}

	if ( ! function_exists( 'BKX' ) ) {
		deactivate_plugins( BKX_STRIPE_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Stripe Payments requires the BookingX plugin to be installed and activated.', 'bkx-stripe-payments' ),
			esc_html__( 'Plugin Activation Error', 'bkx-stripe-payments' ),
			array( 'back_link' => true )
		);
	}

	// Set activation flag for migrations
	set_transient( 'bkx_stripe_activated', true, 30 );
}
register_activation_hook( BKX_STRIPE_FILE, 'bkx_stripe_payments_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_stripe_payments_deactivate() {
	// Clear any scheduled tasks
	wp_clear_scheduled_hook( 'bkx_stripe_sync_webhooks' );
	wp_clear_scheduled_hook( 'bkx_stripe_cleanup_logs' );

	// Clear caches
	delete_transient( 'bkx_stripe_activated' );
}
register_deactivation_hook( BKX_STRIPE_FILE, 'bkx_stripe_payments_deactivate' );
