<?php
/**
 * Autoloader for BookingX Mailchimp Pro addon.
 *
 * @package BookingX\MailchimpPro
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load Composer autoloader if available
if ( file_exists( BKX_MAILCHIMP_PRO_PATH . 'vendor/autoload.php' ) ) {
	require_once BKX_MAILCHIMP_PRO_PATH . 'vendor/autoload.php';
}

// Load SDK autoloader
$sdk_autoload = BKX_MAILCHIMP_PRO_PATH . '../_shared/bkx-addon-sdk/src/autoload.php';
if ( file_exists( $sdk_autoload ) ) {
	require_once $sdk_autoload;
}

/**
 * PSR-4 autoloader for this addon.
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register( function ( $class ) {
	$prefix   = 'BookingX\\MailchimpPro\\';
	$base_dir = BKX_MAILCHIMP_PRO_PATH . 'src/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );
