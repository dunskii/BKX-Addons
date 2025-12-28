<?php
/**
 * Uninstall script for Regional Payments
 *
 * @package BookingX\RegionalPayments
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'bkx_regional_payments_settings' );
delete_option( 'bkx_regional_payments_license_key' );
delete_option( 'bkx_regional_payments_license_status' );

// Delete individual gateway settings.
$gateways = array( 'pix', 'upi', 'sepa', 'ideal', 'bancontact', 'giropay', 'przelewy24', 'boleto' );
foreach ( $gateways as $gateway ) {
	delete_option( 'bkx_regional_' . $gateway . '_settings' );
}
