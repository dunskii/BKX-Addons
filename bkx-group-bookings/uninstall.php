<?php
/**
 * Uninstall script for Group Bookings
 *
 * @package BookingX\GroupBookings
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_group_bookings_settings' );
delete_option( 'bkx_group_bookings_db_version' );
delete_option( 'bkx_group_bookings_license_key' );
delete_option( 'bkx_group_bookings_license_status' );

// Delete post meta.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	WHERE meta_key LIKE '_bkx_group_%'"
);

// Drop custom tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_group_pricing_tiers" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_group_participants" );
// phpcs:enable
