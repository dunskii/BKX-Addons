<?php
/**
 * Uninstall script for BookingX Reserve with Google.
 *
 * Removes all plugin data when uninstalled.
 *
 * @package BookingX\ReserveGoogle
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
	'bkx_reserve_google_settings',
	'bkx_reserve_google_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/**
 * Delete custom database tables.
 */
$tables = array(
	$wpdb->prefix . 'bkx_rwg_merchants',
	$wpdb->prefix . 'bkx_rwg_services',
	$wpdb->prefix . 'bkx_rwg_slots',
	$wpdb->prefix . 'bkx_rwg_bookings',
	$wpdb->prefix . 'bkx_rwg_logs',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Delete transients.
 */
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_rwg_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_rwg_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

/**
 * Clear scheduled events.
 */
wp_clear_scheduled_hook( 'bkx_rwg_sync_availability' );
wp_clear_scheduled_hook( 'bkx_rwg_cleanup_slots' );

/**
 * Delete booking meta related to Reserve with Google.
 */
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'booking_source' AND meta_value = 'reserve_with_google'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

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
			$wpdb->prefix . 'bkx_rwg_merchants',
			$wpdb->prefix . 'bkx_rwg_services',
			$wpdb->prefix . 'bkx_rwg_slots',
			$wpdb->prefix . 'bkx_rwg_bookings',
			$wpdb->prefix . 'bkx_rwg_logs',
		);

		foreach ( $site_tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Delete transients.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_rwg_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_rwg_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Clear scheduled events.
		wp_clear_scheduled_hook( 'bkx_rwg_sync_availability' );
		wp_clear_scheduled_hook( 'bkx_rwg_cleanup_slots' );

		// Flush rewrite rules.
		flush_rewrite_rules();

		restore_current_blog();
	}
}
