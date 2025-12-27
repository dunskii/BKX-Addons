<?php
/**
 * Autoloader for BookingX Booking Reminders.
 *
 * @package BookingX\BookingReminders
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	function ( $class ) {
		$prefix = 'BookingX\\BookingReminders\\';
		$len    = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = BKX_BOOKING_REMINDERS_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);
