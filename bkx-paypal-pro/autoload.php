<?php
/**
 * PSR-4 Autoloader for PayPal Pro Add-on
 *
 * @package BookingX\PayPalPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		$prefix   = 'BookingX\\PayPalPro\\';
		$base_dir = __DIR__ . '/src/';

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
