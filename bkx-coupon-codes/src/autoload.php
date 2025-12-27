<?php
/**
 * Autoloader for BookingX Coupon Codes.
 *
 * @package BookingX\CouponCodes
 * @since   1.0.0
 */

namespace BookingX\CouponCodes;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		// Base namespace.
		$namespace = 'BookingX\\CouponCodes\\';

		// Check if class belongs to our namespace.
		if ( 0 !== strpos( $class, $namespace ) ) {
			return;
		}

		// Remove namespace prefix.
		$relative_class = substr( $class, strlen( $namespace ) );

		// Convert to file path.
		$file = BKX_COUPON_CODES_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		// Load file if it exists.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

// Load SDK autoloader if available.
$sdk_autoload = dirname( BKX_COUPON_CODES_PATH ) . '/_shared/bkx-addon-sdk/src/autoload.php';
if ( file_exists( $sdk_autoload ) ) {
	require_once $sdk_autoload;
}
