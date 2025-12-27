<?php
/**
 * Uninstall Script
 *
 * Runs when the plugin is uninstalled to clean up data.
 *
 * @package BookingX\PayPalPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if we should delete data on uninstall.
$delete_data = get_option( 'bkx_paypal_pro_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_paypal_pro_settings' );
delete_option( 'bkx_paypal_pro_license_key' );
delete_option( 'bkx_paypal_pro_license_status' );
delete_option( 'bkx_paypal_pro_trial_start' );
delete_option( 'bkx_paypal_pro_db_version' );
delete_option( 'bkx_paypal_pro_delete_data_on_uninstall' );

// Delete transients.
delete_transient( 'bkx_paypal_pro_access_token' );

// Drop database tables.
$tables = array(
	$wpdb->prefix . 'bkx_paypal_transactions',
	$wpdb->prefix . 'bkx_paypal_webhook_events',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete post meta.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_paypal_%'" );

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_paypal_pro_license_check' );
wp_clear_scheduled_hook( 'bkx_paypal_pro_token_refresh' );

// Clear any cached data.
wp_cache_flush();
