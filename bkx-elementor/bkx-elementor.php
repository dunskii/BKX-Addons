<?php
/**
 * Plugin Name: BookingX - Elementor Page Builder
 * Plugin URI: https://developer.jeremybush.com/add-ons/elementor
 * Description: Design beautiful booking forms and service displays with Elementor widgets. Includes booking form widget, service grid, staff carousel, and more.
 * Version: 1.0.0
 * Author: Jeremy Bush
 * Author URI: https://developer.jeremybush.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-elementor
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Elementor tested up to: 3.18
 *
 * @package BookingX\Elementor
 */

namespace BookingX\Elementor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_ELEMENTOR_VERSION', '1.0.0' );
define( 'BKX_ELEMENTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_ELEMENTOR_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_ELEMENTOR_BASENAME', plugin_basename( __FILE__ ) );
define( 'BKX_ELEMENTOR_SLUG', 'bkx-elementor' );
define( 'BKX_ELEMENTOR_MINIMUM_VERSION', '3.5.0' );

// Autoloader.
spl_autoload_register( function ( $class ) {
	$prefix = 'BookingX\\Elementor\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = BKX_ELEMENTOR_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Check plugin dependencies.
 *
 * @return bool
 */
function bkx_elementor_check_dependencies() {
	$missing = array();

	// Check BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		$missing[] = 'BookingX';
	}

	// Check Elementor.
	if ( ! did_action( 'elementor/loaded' ) ) {
		$missing[] = 'Elementor';
	}

	if ( ! empty( $missing ) ) {
		add_action( 'admin_notices', function () use ( $missing ) {
			$message = sprintf(
				/* translators: %s: plugin names */
				__( 'BookingX Elementor Page Builder requires the following plugins to be active: %s', 'bkx-elementor' ),
				implode( ', ', $missing )
			);
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		} );
		return false;
	}

	// Check Elementor version.
	if ( ! version_compare( ELEMENTOR_VERSION, BKX_ELEMENTOR_MINIMUM_VERSION, '>=' ) ) {
		add_action( 'admin_notices', function () {
			$message = sprintf(
				/* translators: %s: required version */
				__( 'BookingX Elementor Page Builder requires Elementor version %s or higher.', 'bkx-elementor' ),
				BKX_ELEMENTOR_MINIMUM_VERSION
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
function bkx_elementor_init() {
	if ( ! bkx_elementor_check_dependencies() ) {
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'bkx-elementor', false, dirname( BKX_ELEMENTOR_BASENAME ) . '/languages' );

	// Initialize main addon class.
	ElementorAddon::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bkx_elementor_init', 20 );

/**
 * Activation hook.
 */
function bkx_elementor_activate() {
	// Set default settings.
	$defaults = array(
		'enabled'            => true,
		'custom_styles'      => true,
		'ajax_booking'       => true,
		'widget_booking_form' => true,
		'widget_services'    => true,
		'widget_staff'       => true,
		'widget_availability' => true,
	);

	if ( ! get_option( 'bkx_elementor_settings' ) ) {
		update_option( 'bkx_elementor_settings', $defaults );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\bkx_elementor_activate' );

/**
 * Deactivation hook.
 */
function bkx_elementor_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\bkx_elementor_deactivate' );
