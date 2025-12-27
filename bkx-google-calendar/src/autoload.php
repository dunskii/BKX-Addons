<?php
/**
 * Autoloader for BookingX Google Calendar.
 *
 * @package BookingX\GoogleCalendar
 * @since   1.0.0
 */

namespace BookingX\GoogleCalendar;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		// Base namespace.
		$namespace = 'BookingX\\GoogleCalendar\\';

		// Check if class belongs to our namespace.
		if ( 0 !== strpos( $class, $namespace ) ) {
			return;
		}

		// Remove namespace prefix.
		$relative_class = substr( $class, strlen( $namespace ) );

		// Convert to file path.
		$file = BKX_GOOGLE_CALENDAR_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		// Load file if it exists.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

// Load SDK autoloader if available.
$sdk_autoload = dirname( BKX_GOOGLE_CALENDAR_PATH ) . '/_shared/bkx-addon-sdk/src/autoload.php';
if ( file_exists( $sdk_autoload ) ) {
	require_once $sdk_autoload;
}

// Load Google API client autoloader if available.
$google_autoload = BKX_GOOGLE_CALENDAR_PATH . 'vendor/autoload.php';
if ( file_exists( $google_autoload ) ) {
	require_once $google_autoload;
}
