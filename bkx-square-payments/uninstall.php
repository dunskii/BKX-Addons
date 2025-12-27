<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\SquarePayments
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Security: Check if this is a valid uninstall request.
if ( ! current_user_can( 'activate_plugins' ) ) {
	exit;
}

/**
 * Delete plugin options.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_square_delete_options() {
	// Delete add-on settings.
	delete_option( 'bkx_square_payments_settings' );
	delete_option( 'bkx_square_payments_version' );
	delete_option( 'bkx_square_payments_db_version' );

	// Delete gateway settings.
	delete_option( 'bkx_gateway_square_settings' );

	// Delete license-related options.
	delete_option( 'bkx_square_payments_license_key' );
	delete_option( 'bkx_square_payments_license_status' );

	// Delete transients.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_square_%' OR option_name LIKE '_transient_timeout_bkx_square_%'"
	);
}

/**
 * Delete plugin database tables.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_square_delete_tables() {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'bkx_square_transactions',
		$wpdb->prefix . 'bkx_square_refunds',
		$wpdb->prefix . 'bkx_square_webhook_events',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}

/**
 * Delete plugin post meta.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_square_delete_post_meta() {
	global $wpdb;

	$meta_keys = array(
		'_square_payment_id',
		'_square_transaction_id',
		'_square_refund_id',
		'_square_payment_status',
		'_square_refund_status',
	);

	foreach ( $meta_keys as $meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->postmeta,
			array( 'meta_key' => $meta_key ),
			array( '%s' )
		);
	}
}

/**
 * Clear scheduled hooks.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_square_clear_scheduled_hooks() {
	wp_clear_scheduled_hook( 'bkx_square_payments_cleanup' );
	wp_clear_scheduled_hook( 'bkx_square_sync_customers' );
}

// Ask for confirmation before deleting data (WordPress will handle this via UI).
// Only delete if the user specifically chose to delete all data.
$delete_data = get_option( 'bkx_square_delete_data_on_uninstall', false );

if ( $delete_data ) {
	// Delete all plugin data.
	bkx_square_delete_options();
	bkx_square_delete_tables();
	bkx_square_delete_post_meta();
	bkx_square_clear_scheduled_hooks();
} else {
	// Only delete plugin options and clear hooks, keep database tables and transaction data.
	bkx_square_delete_options();
	bkx_square_clear_scheduled_hooks();
}
