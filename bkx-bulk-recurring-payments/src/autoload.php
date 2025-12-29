<?php
/**
 * PSR-4 Autoloader for Bulk & Recurring Payments.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

namespace BookingX\BulkRecurringPayments;

spl_autoload_register(
	function ( $class ) {
		$prefix   = 'BookingX\\BulkRecurringPayments\\';
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
