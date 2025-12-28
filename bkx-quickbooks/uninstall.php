<?php
/**
 * Uninstall BookingX QuickBooks Integration.
 *
 * @package BookingX\QuickBooks
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete database tables.
$tables = array(
	$wpdb->prefix . 'bkx_qb_sync_log',
	$wpdb->prefix . 'bkx_qb_mapping',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete options.
$options = array(
	'bkx_qb_client_id',
	'bkx_qb_client_secret',
	'bkx_qb_environment',
	'bkx_qb_access_token',
	'bkx_qb_refresh_token',
	'bkx_qb_token_expires',
	'bkx_qb_realm_id',
	'bkx_qb_auto_sync_customers',
	'bkx_qb_auto_sync_invoices',
	'bkx_qb_auto_sync_payments',
	'bkx_qb_default_income_account',
	'bkx_qb_default_tax_code',
	'bkx_qb_default_item_id',
	'bkx_qb_payment_method_mappings',
	'bkx_quickbooks_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete transients.
delete_transient( 'bkx_qb_oauth_state' );

// Clean up post meta.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_bkx_qb_invoice_id', '_bkx_qb_payment_id', '_bkx_qb_customer_id')"
);

// Remove scheduled cron.
$timestamp = wp_next_scheduled( 'bkx_qb_batch_sync' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'bkx_qb_batch_sync' );
}
