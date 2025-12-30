<?php
/**
 * Uninstall script for BKX API Builder.
 *
 * @package BookingX\APIBuilder
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Clean up all plugin data.
 */
function bkx_api_builder_uninstall() {
	global $wpdb;

	// Only delete data if option is set.
	$settings = get_option( 'bkx_api_builder_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Delete database tables.
	$tables = array(
		$wpdb->prefix . 'bkx_api_endpoints',
		$wpdb->prefix . 'bkx_api_keys',
		$wpdb->prefix . 'bkx_api_logs',
		$wpdb->prefix . 'bkx_api_rate_limits',
		$wpdb->prefix . 'bkx_api_webhooks',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	// Delete options.
	$options = array(
		'bkx_api_builder_settings',
		'bkx_api_builder_version',
		'bkx_api_builder_db_version',
		'bkx_api_blocked_identifiers',
		'bkx_api_webhook_logs',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_api_builder_cleanup' );

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_bkx_api_%'
		OR option_name LIKE '_transient_timeout_bkx_api_%'"
	);

	// Clear any cached data.
	wp_cache_flush();
}

// Run uninstall.
bkx_api_builder_uninstall();
