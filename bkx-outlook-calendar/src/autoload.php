<?php
/**
 * Autoloader for Outlook Calendar Add-on
 *
 * @package BookingX\OutlookCalendar
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load SDK autoloader.
$sdk_autoload = dirname( __DIR__, 2 ) . '/_shared/bkx-addon-sdk/src/autoload.php';
if ( file_exists( $sdk_autoload ) ) {
	require_once $sdk_autoload;
}

/**
 * PSR-4 Autoloader for BookingX\OutlookCalendar namespace.
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'BookingX\\OutlookCalendar\\';
		$base_dir = __DIR__ . '/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);
