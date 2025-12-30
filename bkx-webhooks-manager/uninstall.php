<?php
/**
 * Uninstall script for BKX Webhooks Manager.
 *
 * @package BookingX\WebhooksManager
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Clean up all plugin data.
 */
function bkx_webhooks_manager_uninstall() {
	global $wpdb;

	// Only delete data if option is set.
	$settings = get_option( 'bkx_webhooks_manager_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Delete database tables.
	$tables = array(
		$wpdb->prefix . 'bkx_webhooks',
		$wpdb->prefix . 'bkx_webhook_deliveries',
		$wpdb->prefix . 'bkx_webhook_subscriptions',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	// Delete options.
	$options = array(
		'bkx_webhooks_manager_settings',
		'bkx_webhooks_manager_version',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_webhooks_cleanup' );
	wp_clear_scheduled_hook( 'bkx_webhooks_process_retries' );

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_bkx_webhook_%'
		OR option_name LIKE '_transient_timeout_bkx_webhook_%'"
	);

	// Clear any cached data.
	wp_cache_flush();
}

// Run uninstall.
bkx_webhooks_manager_uninstall();
