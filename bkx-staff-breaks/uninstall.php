<?php
/**
 * Uninstall script for Staff Breaks & Time Off
 *
 * @package BookingX\StaffBreaks
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_staff_breaks_settings' );
delete_option( 'bkx_staff_breaks_db_version' );
delete_option( 'bkx_staff_breaks_license_key' );
delete_option( 'bkx_staff_breaks_license_status' );

// Drop tables.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_staff_breaks" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_staff_timeoff" );

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_staff_breaks_cleanup' );
