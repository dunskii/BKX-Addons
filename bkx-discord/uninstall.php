<?php
/**
 * Uninstall script for Discord Notifications.
 *
 * @package BookingX\Discord
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 */
function bkx_discord_uninstall() {
	global $wpdb;

	// Delete options.
	delete_option( 'bkx_discord_settings' );
	delete_option( 'bkx_discord_db_version' );

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_discord_webhooks',
		$wpdb->prefix . 'bkx_discord_logs',
		$wpdb->prefix . 'bkx_discord_bots',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_discord_%' OR option_name LIKE '_transient_timeout_bkx_discord_%'"
	);

	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_discord_cleanup_logs' );
}

// Handle multisite.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		bkx_discord_uninstall();
		restore_current_blog();
	}
} else {
	bkx_discord_uninstall();
}
