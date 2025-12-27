<?php
/**
 * Uninstall script for Rewards Points
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_rewards_settings' );
delete_option( 'bkx_rewards_license_key' );
delete_option( 'bkx_rewards_license_status' );
delete_option( 'bkx_rewards_db_version' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_rewards_expire_points' );

// Optionally drop tables (uncomment if you want to remove all data).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_points_redemptions" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_points_transactions" );
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_points_balance" );
