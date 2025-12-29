<?php
/**
 * BKX to BKX Integration Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\BkxIntegration
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if data should be removed on uninstall.
$settings = get_option( 'bkx_bkx_integration_settings', array() );
$remove_data = isset( $settings['remove_data_on_uninstall'] ) && $settings['remove_data_on_uninstall'];

if ( ! $remove_data ) {
	// Don't remove data unless explicitly configured.
	return;
}

global $wpdb;

// Delete options.
$options = array(
	'bkx_bkx_integration_settings',
	'bkx_bkx_api_key',
	'bkx_bkx_api_secret',
	'bkx_bkx_integration_version',
	'bkx_bkx_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete transients.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	WHERE option_name LIKE '%_transient_bkx_bkx_%'
	OR option_name LIKE '%_transient_timeout_bkx_bkx_%'"
);

// Drop custom tables.
$tables = array(
	$wpdb->prefix . 'bkx_remote_sites',
	$wpdb->prefix . 'bkx_remote_mappings',
	$wpdb->prefix . 'bkx_remote_logs',
	$wpdb->prefix . 'bkx_remote_queue',
	$wpdb->prefix . 'bkx_remote_conflicts',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Remove scheduled cron events.
$cron_hooks = array(
	'bkx_bkx_process_queue',
	'bkx_bkx_cleanup_logs',
	'bkx_bkx_sync_availability',
);

foreach ( $cron_hooks as $hook ) {
	$timestamp = wp_next_scheduled( $hook );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
	}
}

// Clear any remaining cron jobs.
wp_clear_scheduled_hook( 'bkx_bkx_process_queue' );
wp_clear_scheduled_hook( 'bkx_bkx_cleanup_logs' );
wp_clear_scheduled_hook( 'bkx_bkx_sync_availability' );

// Delete user meta.
$wpdb->query(
	"DELETE FROM {$wpdb->usermeta}
	WHERE meta_key LIKE 'bkx_bkx_%'"
);

// Delete post meta related to remote sync.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	WHERE meta_key LIKE '_bkx_remote_%'"
);

// Remove capabilities if added.
$role = get_role( 'administrator' );
if ( $role ) {
	$role->remove_cap( 'manage_bkx_integration' );
}

// Clear object cache.
wp_cache_flush();
