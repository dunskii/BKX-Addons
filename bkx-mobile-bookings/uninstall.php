<?php
/**
 * Mobile Bookings Advanced Uninstall.
 *
 * Removes all plugin data when uninstalled.
 *
 * @package BookingX\MobileBookings
 * @since   1.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Uninstall Mobile Bookings Advanced.
 *
 * @since 1.0.0
 */
function bkx_mobile_bookings_uninstall() {
	global $wpdb;

	// Check if we should delete data.
	$settings = get_option( 'bkx_mobile_bookings_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Delete custom tables.
	$tables = array(
		'bkx_locations',
		'bkx_travel_times',
		'bkx_service_areas',
		'bkx_provider_routes',
		'bkx_gps_checkins',
		'bkx_provider_locations',
	);

	foreach ( $tables as $table ) {
		$table_name = $wpdb->prefix . $table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
	}

	// Delete options.
	$options = array(
		'bkx_mobile_bookings_settings',
		'bkx_mobile_bookings_version',
		'bkx_mobile_bookings_license_key',
		'bkx_mobile_bookings_license_status',
		'bkx_mobile_bookings_license_expires',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete post meta.
	$meta_keys = array(
		'_bkx_booking_location',
		'_bkx_booking_coordinates',
		'_bkx_booking_distance',
		'_bkx_booking_travel_time',
		'_bkx_booking_travel_fee',
		'_bkx_booking_checkin_time',
		'_bkx_booking_checkin_location',
		'_bkx_booking_checkin_verified',
		'_bkx_provider_service_areas',
		'_bkx_provider_mobile_enabled',
		'_bkx_provider_base_location',
		'_bkx_provider_travel_radius',
	);

	foreach ( $meta_keys as $meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ), array( '%s' ) );
	}

	// Delete user meta.
	$user_meta_keys = array(
		'bkx_provider_location',
		'bkx_provider_last_update',
		'bkx_provider_tracking_enabled',
	);

	foreach ( $user_meta_keys as $meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta_key ), array( '%s' ) );
	}

	// Delete transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_bkx_distance_%'
		OR option_name LIKE '_transient_timeout_bkx_distance_%'
		OR option_name LIKE '_transient_bkx_geocode_%'
		OR option_name LIKE '_transient_timeout_bkx_geocode_%'
		OR option_name LIKE '_transient_bkx_route_%'
		OR option_name LIKE '_transient_timeout_bkx_route_%'"
	);

	// Clear scheduled events.
	$cron_hooks = array(
		'bkx_mobile_bookings_cleanup',
		'bkx_mobile_bookings_cache_refresh',
		'bkx_mobile_bookings_location_cleanup',
	);

	foreach ( $cron_hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}

	// Delete log files.
	$upload_dir = wp_upload_dir();
	$log_dir    = $upload_dir['basedir'] . '/bkx-mobile-bookings-logs';
	if ( is_dir( $log_dir ) ) {
		bkx_mobile_bookings_delete_directory( $log_dir );
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
function bkx_mobile_bookings_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;
		if ( is_dir( $path ) ) {
			bkx_mobile_bookings_delete_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	return rmdir( $dir );
}

// Run uninstall.
bkx_mobile_bookings_uninstall();
