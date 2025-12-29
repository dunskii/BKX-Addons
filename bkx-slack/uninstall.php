<?php
/**
 * Uninstall script for Slack Integration.
 *
 * @package BookingX\Slack
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 */
function bkx_slack_uninstall() {
	global $wpdb;

	// Delete options.
	delete_option( 'bkx_slack_settings' );
	delete_option( 'bkx_slack_db_version' );

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_slack_workspaces',
		$wpdb->prefix . 'bkx_slack_channels',
		$wpdb->prefix . 'bkx_slack_logs',
		$wpdb->prefix . 'bkx_slack_users',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_slack_%' OR option_name LIKE '_transient_timeout_bkx_slack_%'"
	);

	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_slack_cleanup_logs' );
	wp_clear_scheduled_hook( 'bkx_slack_refresh_tokens' );
}

// Handle multisite.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		bkx_slack_uninstall();
		restore_current_blog();
	}
} else {
	bkx_slack_uninstall();
}
