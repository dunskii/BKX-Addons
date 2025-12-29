<?php
/**
 * Bulk & Recurring Payments Uninstall.
 *
 * Removes all plugin data when uninstalled.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Uninstall Bulk & Recurring Payments.
 *
 * @since 1.0.0
 */
function bkx_bulk_recurring_payments_uninstall() {
	global $wpdb;

	// Check if we should delete data.
	$settings = get_option( 'bkx_bulk_recurring_payments_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Delete custom tables.
	$tables = array(
		'bkx_payment_packages',
		'bkx_subscriptions',
		'bkx_subscription_payments',
		'bkx_bulk_purchases',
		'bkx_bulk_usage',
		'bkx_invoice_templates',
	);

	foreach ( $tables as $table ) {
		$table_name = $wpdb->prefix . $table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
	}

	// Delete options.
	$options = array(
		'bkx_bulk_recurring_payments_settings',
		'bkx_bulk_recurring_payments_version',
		'bkx_bulk_recurring_payments_license_key',
		'bkx_bulk_recurring_payments_license_status',
		'bkx_bulk_recurring_payments_license_expires',
		'bkx_next_invoice_number',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete post meta.
	$meta_keys = array(
		'_bkx_bulk_purchase_id',
		'_bkx_subscription_id',
		'_bkx_payment_method',
		'_bkx_package_credits',
	);

	foreach ( $meta_keys as $meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ), array( '%s' ) );
	}

	// Delete user meta.
	$user_meta_keys = array(
		'bkx_stripe_customer_id',
		'bkx_paypal_customer_id',
	);

	foreach ( $user_meta_keys as $meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta_key ), array( '%s' ) );
	}

	// Delete transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_bkx_package_%'
		OR option_name LIKE '_transient_timeout_bkx_package_%'
		OR option_name LIKE '_transient_bkx_subscription_%'
		OR option_name LIKE '_transient_timeout_bkx_subscription_%'"
	);

	// Clear scheduled events.
	$cron_hooks = array(
		'bkx_process_recurring_payments',
		'bkx_check_subscription_renewals',
		'bkx_send_renewal_reminders',
		'bkx_check_bulk_expiry',
		'bkx_retry_failed_payments',
	);

	foreach ( $cron_hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}

	// Delete invoice files.
	$upload_dir = wp_upload_dir();
	$invoice_dir = $upload_dir['basedir'] . '/bkx-invoices';
	if ( is_dir( $invoice_dir ) ) {
		bkx_bulk_recurring_delete_directory( $invoice_dir );
	}

	// Clear any cached data.
	wp_cache_flush();
}

/**
 * Recursively delete a directory.
 *
 * @since 1.0.0
 *
 * @param string $dir Directory path.
 * @return bool True on success.
 */
function bkx_bulk_recurring_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;
		if ( is_dir( $path ) ) {
			bkx_bulk_recurring_delete_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	return rmdir( $dir );
}

// Run uninstall.
bkx_bulk_recurring_payments_uninstall();
