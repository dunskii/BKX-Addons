<?php
/**
 * MYOB Integration Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\MYOB
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete plugin options.
delete_option( 'bkx_myob_settings' );

// Delete booking meta.
$meta_keys = array(
	'_myob_customer_id',
	'_myob_invoice_id',
	'_myob_invoice_number',
	'_myob_payment_id',
	'_myob_synced',
	'_myob_payment_synced',
);

foreach ( $meta_keys as $key ) {
	$wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => $key )
	);
}

// Drop sync log table.
$table_name = $wpdb->prefix . 'bkx_myob_sync_log';
$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table_name ) );

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_myob_sync_cron' );
wp_clear_scheduled_hook( 'bkx_myob_refresh_token' );
