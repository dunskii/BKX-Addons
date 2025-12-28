<?php
/**
 * Uninstall script for Hold Blocks
 *
 * @package BookingX\HoldBlocks
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_hold_blocks_settings' );
delete_option( 'bkx_hold_blocks_db_version' );
delete_option( 'bkx_hold_blocks_license_key' );
delete_option( 'bkx_hold_blocks_license_status' );

// Drop custom table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_hold_blocks" );

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_hold_blocks_cleanup' );
