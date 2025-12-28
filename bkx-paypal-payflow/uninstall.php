<?php
/**
 * Uninstall script for PayPal Payflow
 *
 * @package BookingX\PayPalPayflow
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'bkx_paypal_payflow_settings' );
delete_option( 'bkx_paypal_payflow_license_key' );
delete_option( 'bkx_paypal_payflow_license_status' );
