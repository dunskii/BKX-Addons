<?php
/**
 * Uninstall script for Multi-Location Management.
 *
 * @package BookingX\MultiLocation
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 */
function bkx_ml_uninstall() {
	global $wpdb;

	// Delete options.
	delete_option( 'bkx_ml_db_version' );
	delete_option( 'bkx_ml_google_maps_key' );
	delete_option( 'bkx_ml_default_location' );
	delete_option( 'bkx_ml_show_map' );
	delete_option( 'bkx_ml_enable_geolocation' );
	delete_option( 'bkx_ml_distance_unit' );
	delete_option( 'bkx_ml_search_radius' );

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_locations',
		$wpdb->prefix . 'bkx_location_hours',
		$wpdb->prefix . 'bkx_location_holidays',
		$wpdb->prefix . 'bkx_location_staff',
		$wpdb->prefix . 'bkx_location_services',
		$wpdb->prefix . 'bkx_location_resources',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Delete post meta.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => '_bkx_location_id' ),
		array( '%s' )
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => '_bkx_resource_id' ),
		array( '%s' )
	);

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_ml_%' OR option_name LIKE '_transient_timeout_bkx_ml_%'"
	);

	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_ml_daily_cleanup' );
	wp_clear_scheduled_hook( 'bkx_ml_sync_google_places' );
}

// Handle multisite.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		bkx_ml_uninstall();
		restore_current_blog();
	}
} else {
	bkx_ml_uninstall();
}
