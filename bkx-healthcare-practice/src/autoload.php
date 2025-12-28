<?php
/**
 * Autoloader for Healthcare Practice addon.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

spl_autoload_register( function ( $class ) {
	$prefix = 'BookingX\\HealthcarePractice\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = BKX_HEALTHCARE_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );
