<?php
/**
 * Plugin Name: BookingX - WooCommerce Pro Integration
 * Plugin URI: https://developer.jeremybush.com/add-ons/woocommerce-pro
 * Description: Seamlessly integrate BookingX bookings with WooCommerce for cart checkout, product bundling, and unified order management.
 * Version: 1.0.0
 * Author: Jeremy Bush
 * Author URI: https://developer.jeremybush.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-woocommerce-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package BookingX\WooCommercePro
 */

namespace BookingX\WooCommercePro;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_WOOCOMMERCE_VERSION', '1.0.0' );
define( 'BKX_WOOCOMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_WOOCOMMERCE_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_WOOCOMMERCE_BASENAME', plugin_basename( __FILE__ ) );
define( 'BKX_WOOCOMMERCE_SLUG', 'bkx-woocommerce-pro' );

// Autoloader.
spl_autoload_register( function ( $class ) {
	$prefix = 'BookingX\\WooCommercePro\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = BKX_WOOCOMMERCE_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Check plugin dependencies.
 *
 * @return bool
 */
function bkx_woocommerce_check_dependencies() {
	$missing = array();

	// Check BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		$missing[] = 'BookingX';
	}

	// Check WooCommerce.
	if ( ! class_exists( 'WooCommerce' ) ) {
		$missing[] = 'WooCommerce';
	}

	if ( ! empty( $missing ) ) {
		add_action( 'admin_notices', function () use ( $missing ) {
			$message = sprintf(
				/* translators: %s: plugin names */
				__( 'BookingX WooCommerce Pro requires the following plugins to be active: %s', 'bkx-woocommerce-pro' ),
				implode( ', ', $missing )
			);
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		} );
		return false;
	}

	return true;
}

/**
 * Initialize the plugin.
 */
function bkx_woocommerce_init() {
	if ( ! bkx_woocommerce_check_dependencies() ) {
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'bkx-woocommerce-pro', false, dirname( BKX_WOOCOMMERCE_BASENAME ) . '/languages' );

	// Initialize main addon class.
	WooCommerceAddon::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bkx_woocommerce_init', 20 );

/**
 * Activation hook.
 */
function bkx_woocommerce_activate() {
	// Set default settings.
	$defaults = array(
		'enabled'               => true,
		'add_to_cart'           => true,
		'cart_behavior'         => 'redirect', // redirect, stay, mini-cart
		'checkout_flow'         => 'woocommerce', // woocommerce, bookingx, hybrid
		'product_integration'   => 'virtual', // virtual, simple, variable
		'sync_inventory'        => true,
		'create_orders'         => true,
		'order_status_mapping'  => array(
			'pending'    => 'bkx-pending',
			'processing' => 'bkx-ack',
			'completed'  => 'bkx-completed',
			'cancelled'  => 'bkx-cancelled',
			'refunded'   => 'bkx-cancelled',
		),
		'display_in_account'    => true,
		'enable_coupons'        => true,
		'enable_subscriptions'  => false,
		'bundle_services'       => true,
		'gift_cards'            => false,
		'booking_product_type'  => 'bkx_booking',
		'tax_handling'          => 'inherit', // inherit, manual, none
		'email_integration'     => true,
	);

	if ( ! get_option( 'bkx_woocommerce_settings' ) ) {
		update_option( 'bkx_woocommerce_settings', $defaults );
	}

	// Set DB version.
	update_option( 'bkx_woocommerce_db_version', BKX_WOOCOMMERCE_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\bkx_woocommerce_activate' );

/**
 * Deactivation hook.
 */
function bkx_woocommerce_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\bkx_woocommerce_deactivate' );

/**
 * Declare HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
