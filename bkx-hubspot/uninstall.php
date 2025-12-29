<?php
/**
 * Uninstall script for HubSpot Integration.
 *
 * @package BookingX\HubSpot
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 */
function bkx_hubspot_uninstall() {
	global $wpdb;

	// Delete options.
	delete_option( 'bkx_hubspot_settings' );
	delete_option( 'bkx_hubspot_credentials' );
	delete_option( 'bkx_hubspot_db_version' );

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_hs_mappings',
		$wpdb->prefix . 'bkx_hs_logs',
		$wpdb->prefix . 'bkx_hs_property_mappings',
		$wpdb->prefix . 'bkx_hs_queue',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_hs_%' OR option_name LIKE '_transient_timeout_bkx_hs_%'"
	);

	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_hubspot_sync_cron' );
	wp_clear_scheduled_hook( 'bkx_hubspot_token_refresh' );
}

// Handle multisite.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		bkx_hubspot_uninstall();
		restore_current_blog();
	}
} else {
	bkx_hubspot_uninstall();
}
