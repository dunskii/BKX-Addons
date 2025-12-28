<?php
/**
 * Autoloader for Regional Payments addon.
 *
 * @package BookingX\RegionalPayments
 * @since   1.0.0
 */

spl_autoload_register( function ( $class ) {
	$prefix = 'BookingX\\RegionalPayments\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = BKX_REGIONAL_PAYMENTS_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );
