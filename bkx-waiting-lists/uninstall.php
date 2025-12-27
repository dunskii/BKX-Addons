<?php
/**
 * Uninstall script for Waiting Lists
 *
 * @package BookingX\WaitingLists
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_waiting_lists_settings' );
delete_option( 'bkx_waiting_lists_db_version' );
delete_option( 'bkx_waiting_lists_license_key' );
delete_option( 'bkx_waiting_lists_license_status' );

// Drop table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_waiting_list" );

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_waiting_list_cleanup' );
wp_clear_scheduled_hook( 'bkx_waiting_list_check_expired' );
