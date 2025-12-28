<?php
/**
 * Autoloader for Beauty & Wellness addon.
 *
 * @package BookingX\BeautyWellness
 * @since   1.0.0
 */

spl_autoload_register( function ( $class ) {
	$prefix = 'BookingX\\BeautyWellness\\';
	$len    = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = BKX_BEAUTY_WELLNESS_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );
