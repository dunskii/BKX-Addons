<?php
/**
 * PSR-4 Autoloader
 *
 * @package BookingX\MultipleServices
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Autoloader for BookingX Multiple Services.
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register( function ( $class ) {
	// Project namespace prefix
	$prefix = 'BookingX\\MultipleServices\\';

	// Base directory for the namespace prefix
	$base_dir = __DIR__ . '/';

	// Does the class use the namespace prefix?
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		// No, move to the next registered autoloader
		return;
	}

	// Get the relative class name
	$relative_class = substr( $class, $len );

	// Replace the namespace prefix with the base directory, replace namespace
	// separators with directory separators in the relative class name, append
	// with .php
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	// If the file exists, require it
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Load SDK autoloader.
 */
$sdk_autoloader = dirname( dirname( __DIR__ ) ) . '/_shared/bkx-addon-sdk/autoload.php';
if ( file_exists( $sdk_autoloader ) ) {
	require_once $sdk_autoloader;
}

/**
 * Load Composer autoloader if available.
 */
$composer_autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $composer_autoloader ) ) {
	require_once $composer_autoloader;
}
