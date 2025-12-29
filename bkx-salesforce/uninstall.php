<?php
/**
 * Uninstall script for Salesforce Connector.
 *
 * @package BookingX\Salesforce
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 */
function bkx_salesforce_uninstall() {
	global $wpdb;

	// Delete options.
	delete_option( 'bkx_salesforce_settings' );
	delete_option( 'bkx_salesforce_credentials' );
	delete_option( 'bkx_salesforce_db_version' );

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_sf_mappings',
		$wpdb->prefix . 'bkx_sf_logs',
		$wpdb->prefix . 'bkx_sf_field_mappings',
		$wpdb->prefix . 'bkx_sf_queue',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_sf_%' OR option_name LIKE '_transient_timeout_bkx_sf_%'"
	);

	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_salesforce_sync_cron' );
	wp_clear_scheduled_hook( 'bkx_salesforce_token_refresh' );
}

// Handle multisite.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		bkx_salesforce_uninstall();
		restore_current_blog();
	}
} else {
	bkx_salesforce_uninstall();
}
