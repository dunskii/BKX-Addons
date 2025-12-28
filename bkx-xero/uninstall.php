<?php
/**
 * Uninstall BookingX Xero Integration.
 *
 * @package BookingX\Xero
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete database tables.
$tables = array(
	$wpdb->prefix . 'bkx_xero_sync_log',
	$wpdb->prefix . 'bkx_xero_mapping',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete options.
$options = array(
	'bkx_xero_client_id',
	'bkx_xero_client_secret',
	'bkx_xero_access_token',
	'bkx_xero_refresh_token',
	'bkx_xero_token_expires',
	'bkx_xero_tenant_id',
	'bkx_xero_tenant_name',
	'bkx_xero_auto_sync_contacts',
	'bkx_xero_auto_sync_invoices',
	'bkx_xero_auto_sync_payments',
	'bkx_xero_revenue_account',
	'bkx_xero_tax_type',
	'bkx_xero_branding_theme',
	'bkx_xero_bank_account',
	'bkx_xero_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete transients.
delete_transient( 'bkx_xero_oauth_state' );

// Clean up post meta.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_bkx_xero_invoice_id', '_bkx_xero_payment_id', '_bkx_xero_contact_id')"
);

// Remove scheduled cron.
$timestamp = wp_next_scheduled( 'bkx_xero_batch_sync' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'bkx_xero_batch_sync' );
}
