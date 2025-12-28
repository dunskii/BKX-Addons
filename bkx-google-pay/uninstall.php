<?php
/**
 * Uninstall script for Google Pay
 *
 * @package BookingX\GooglePay
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'bkx_google_pay_settings' );
delete_option( 'bkx_google_pay_license_key' );
delete_option( 'bkx_google_pay_license_status' );
