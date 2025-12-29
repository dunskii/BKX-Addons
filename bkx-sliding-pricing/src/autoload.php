<?php
/**
 * Autoloader for Sliding Pricing.
 *
 * @package BookingX\SlidingPricing
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		// Namespace prefix.
		$prefix = 'BookingX\\SlidingPricing\\';

		// Check if class uses our namespace.
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Get relative class name.
		$relative_class = substr( $class, $len );

		// Convert namespace to file path.
		$file = BKX_SLIDING_PRICING_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		// Load file if it exists.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
