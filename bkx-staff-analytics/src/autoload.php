<?php
/**
 * Autoloader for Staff Performance Analytics.
 *
 * @package BookingX\StaffAnalytics
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		// Namespace prefix.
		$prefix = 'BookingX\\StaffAnalytics\\';

		// Check if class uses our namespace.
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Get relative class name.
		$relative_class = substr( $class, $len );

		// Convert namespace to file path.
		$file = BKX_STAFF_ANALYTICS_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		// Load file if it exists.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
