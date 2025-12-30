<?php
/**
 * FreshBooks Integration Uninstall
 *
 * @package BookingX\FreshBooks
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_freshbooks_settings' );

// Delete booking meta.
$meta_keys = array(
	'_freshbooks_client_id',
	'_freshbooks_invoice_id',
	'_freshbooks_invoice_number',
	'_freshbooks_payment_id',
	'_freshbooks_synced',
	'_freshbooks_payment_synced',
);

foreach ( $meta_keys as $key ) {
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $key ) );
}

// Drop sync log table.
$table_name = $wpdb->prefix . 'bkx_freshbooks_sync_log';
$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table_name ) );

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_freshbooks_sync_cron' );
wp_clear_scheduled_hook( 'bkx_freshbooks_refresh_token' );
