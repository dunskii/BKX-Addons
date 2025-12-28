<?php
/**
 * Uninstall BookingX Business Intelligence.
 *
 * @package BookingX\BusinessIntelligence
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if user wants to delete data.
$settings = get_option( 'bkx_bi_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Delete database tables.
$tables = array(
	$wpdb->prefix . 'bkx_bi_metrics',
	$wpdb->prefix . 'bkx_bi_reports',
	$wpdb->prefix . 'bkx_bi_forecasts',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete options.
delete_option( 'bkx_bi_settings' );
delete_option( 'bkx_bi_version' );
delete_option( 'bkx_bi_db_version' );

// Delete transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_bi_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_bi_%'" );

// Clear object cache.
wp_cache_flush();

// Delete export files.
$upload_dir = wp_upload_dir();
$export_dir = $upload_dir['basedir'] . '/bkx-bi-exports/';

if ( is_dir( $export_dir ) ) {
	$files = glob( $export_dir . '*' );
	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	rmdir( $export_dir );
}

// Clear scheduled hooks.
wp_clear_scheduled_hook( 'bkx_bi_aggregate_data' );
wp_clear_scheduled_hook( 'bkx_bi_send_reports' );
wp_clear_scheduled_hook( 'bkx_bi_cleanup_exports' );
