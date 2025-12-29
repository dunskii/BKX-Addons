<?php
/**
 * Uninstall script for BookingX Facebook Booking.
 *
 * Removes all plugin data when uninstalled.
 *
 * @package BookingX\FacebookBooking
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check user permissions.
if ( ! current_user_can( 'activate_plugins' ) ) {
	exit;
}

global $wpdb;

/**
 * Delete plugin options.
 */
$options = array(
	'bkx_fb_booking_settings',
	'bkx_fb_booking_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/**
 * Delete custom database tables.
 */
$tables = array(
	$wpdb->prefix . 'bkx_fb_pages',
	$wpdb->prefix . 'bkx_fb_bookings',
	$wpdb->prefix . 'bkx_fb_webhooks',
	$wpdb->prefix . 'bkx_fb_services',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Delete transients.
 */
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_fb_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_fb_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

/**
 * Delete session transients.
 */
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_fb_session_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_fb_session_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

/**
 * Clear scheduled events.
 */
wp_clear_scheduled_hook( 'bkx_fb_sync_bookings' );
wp_clear_scheduled_hook( 'bkx_fb_refresh_tokens' );

/**
 * Delete booking meta related to Facebook Booking.
 */
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'booking_source' AND meta_value = 'facebook'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'fb_booking_id'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

/**
 * Flush rewrite rules.
 */
flush_rewrite_rules();

/**
 * Multisite support.
 */
if ( is_multisite() ) {
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );

		// Delete options.
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Delete tables.
		$site_tables = array(
			$wpdb->prefix . 'bkx_fb_pages',
			$wpdb->prefix . 'bkx_fb_bookings',
			$wpdb->prefix . 'bkx_fb_webhooks',
			$wpdb->prefix . 'bkx_fb_services',
		);

		foreach ( $site_tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Delete transients.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_fb_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_fb_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Clear scheduled events.
		wp_clear_scheduled_hook( 'bkx_fb_sync_bookings' );
		wp_clear_scheduled_hook( 'bkx_fb_refresh_tokens' );

		// Flush rewrite rules.
		flush_rewrite_rules();

		restore_current_blog();
	}
}
