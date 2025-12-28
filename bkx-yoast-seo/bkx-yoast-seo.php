<?php
/**
 * Plugin Name: BookingX - Yoast SEO Integration
 * Plugin URI: https://developer.jeremybush.com/add-ons/yoast-seo
 * Description: Optimize your booking pages for search engines with Yoast SEO integration. Adds structured data, meta optimization, and sitemap support.
 * Version: 1.0.0
 * Author: Jeremy Bush
 * Author URI: https://developer.jeremybush.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-yoast-seo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\YoastSeo
 */

namespace BookingX\YoastSeo;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_YOAST_VERSION', '1.0.0' );
define( 'BKX_YOAST_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_YOAST_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_YOAST_BASENAME', plugin_basename( __FILE__ ) );
define( 'BKX_YOAST_SLUG', 'bkx-yoast-seo' );

// Autoloader.
spl_autoload_register( function ( $class ) {
	$prefix = 'BookingX\\YoastSeo\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = BKX_YOAST_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Check plugin dependencies.
 *
 * @return bool
 */
function bkx_yoast_check_dependencies() {
	$missing = array();

	// Check BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		$missing[] = 'BookingX';
	}

	// Check Yoast SEO.
	if ( ! defined( 'WPSEO_VERSION' ) ) {
		$missing[] = 'Yoast SEO';
	}

	if ( ! empty( $missing ) ) {
		add_action( 'admin_notices', function () use ( $missing ) {
			$message = sprintf(
				/* translators: %s: plugin names */
				__( 'BookingX Yoast SEO Integration requires the following plugins to be active: %s', 'bkx-yoast-seo' ),
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
function bkx_yoast_init() {
	if ( ! bkx_yoast_check_dependencies() ) {
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'bkx-yoast-seo', false, dirname( BKX_YOAST_BASENAME ) . '/languages' );

	// Initialize main addon class.
	YoastSeoAddon::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bkx_yoast_init', 20 );

/**
 * Activation hook.
 */
function bkx_yoast_activate() {
	// Set default settings.
	$defaults = array(
		'enabled'                => true,
		'schema_service'         => true,
		'schema_local_business'  => true,
		'schema_booking_event'   => true,
		'meta_services'          => true,
		'meta_seats'             => true,
		'sitemap_services'       => true,
		'sitemap_seats'          => true,
		'og_services'            => true,
		'twitter_cards'          => true,
		'breadcrumbs'            => true,
		'canonical_urls'         => true,
		'noindex_past_bookings'  => true,
		'auto_meta_description'  => true,
		'default_service_title'  => '%service_name% - Book Online | %sitename%',
		'default_seat_title'     => '%seat_name% - %seat_alias% | %sitename%',
	);

	if ( ! get_option( 'bkx_yoast_settings' ) ) {
		update_option( 'bkx_yoast_settings', $defaults );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\bkx_yoast_activate' );

/**
 * Deactivation hook.
 */
function bkx_yoast_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\bkx_yoast_deactivate' );
