<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\StripePayments
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin data on uninstall.
 *
 * This function is called when the plugin is deleted via the WordPress admin.
 * It removes all plugin data including database tables and options.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_stripe_payments_uninstall() {
	global $wpdb;

	// Check if we should delete data
	// Only delete if explicitly configured to do so
	$delete_data = get_option( 'bkx_stripe_payments_delete_data_on_uninstall', false );

	if ( ! $delete_data ) {
		return; // Preserve data by default
	}

	// Delete database tables
	$tables = array(
		$wpdb->prefix . 'bkx_stripe_transactions',
		$wpdb->prefix . 'bkx_stripe_subscriptions',
		$wpdb->prefix . 'bkx_stripe_refunds',
		$wpdb->prefix . 'bkx_stripe_webhook_events',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Delete options
	delete_option( 'bkx_stripe_payments_settings' );
	delete_option( 'bkx_stripe_payments_db_version' );
	delete_option( 'bkx_stripe_payments_license_key' );
	delete_option( 'bkx_stripe_payments_license_status' );
	delete_option( 'bkx_stripe_payments_delete_data_on_uninstall' );

	// Delete transients
	delete_transient( 'bkx_stripe_activated' );

	// Delete post meta (payment gateway references)
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			'_bkx_stripe_%'
		)
	);

	// Delete user meta (saved customer IDs)
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
			'_bkx_stripe_customer_id'
		)
	);

	// Clear any scheduled cron events
	wp_clear_scheduled_hook( 'bkx_stripe_sync_webhooks' );
	wp_clear_scheduled_hook( 'bkx_stripe_cleanup_logs' );

	// Flush rewrite rules
	flush_rewrite_rules();
}

// Run uninstall
bkx_stripe_payments_uninstall();
