<?php
/**
 * Uninstall script for Sliding Pricing Add-on.
 *
 * @package BookingX\SlidingPricing
 * @since   1.0.0
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data on uninstall.
 */
function bkx_sliding_pricing_uninstall() {
	global $wpdb;

	// Check if we should delete data.
	$settings = get_option( 'bkx_sliding_pricing_settings', array() );

	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_pricing_rules',
		$wpdb->prefix . 'bkx_pricing_seasons',
		$wpdb->prefix . 'bkx_pricing_timeslots',
		$wpdb->prefix . 'bkx_pricing_history',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Delete options.
	delete_option( 'bkx_sliding_pricing_settings' );
	delete_option( 'bkx_sliding_pricing_version' );
	delete_option( 'bkx_sliding_pricing_license_key' );
	delete_option( 'bkx_sliding_pricing_license_status' );

	// Clear any transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_pricing_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_pricing_%'" );

	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_sliding_pricing_daily_cleanup' );

	// For multisite, run on each site.
	if ( is_multisite() ) {
		$sites = get_sites( array( 'fields' => 'ids' ) );

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );
			bkx_sliding_pricing_uninstall_site();
			restore_current_blog();
		}
	}
}

/**
 * Clean up site-specific data.
 */
function bkx_sliding_pricing_uninstall_site() {
	global $wpdb;

	// Drop site-specific tables.
	$tables = array(
		$wpdb->prefix . 'bkx_pricing_rules',
		$wpdb->prefix . 'bkx_pricing_seasons',
		$wpdb->prefix . 'bkx_pricing_timeslots',
		$wpdb->prefix . 'bkx_pricing_history',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Delete site options.
	delete_option( 'bkx_sliding_pricing_settings' );
	delete_option( 'bkx_sliding_pricing_version' );
	delete_option( 'bkx_sliding_pricing_license_key' );
	delete_option( 'bkx_sliding_pricing_license_status' );

	// Clear transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_pricing_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_pricing_%'" );
}

// Run uninstall.
bkx_sliding_pricing_uninstall();
