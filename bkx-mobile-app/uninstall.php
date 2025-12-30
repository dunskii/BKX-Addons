<?php
/**
 * Mobile App Framework Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\MobileApp
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if we should remove all data.
$settings = get_option( 'bkx_mobile_app_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Remove options.
$options_to_delete = array(
	'bkx_mobile_app_settings',
	'bkx_mobile_app_version',
	'bkx_mobile_app_db_version',
	'bkx_mobile_app_license_key',
	'bkx_mobile_app_license_status',
	'bkx_mobile_app_jwt_secret',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Remove database tables.
$tables_to_drop = array(
	$wpdb->prefix . 'bkx_mobile_devices',
	$wpdb->prefix . 'bkx_mobile_api_keys',
	$wpdb->prefix . 'bkx_mobile_push_log',
);

foreach ( $tables_to_drop as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove scheduled events.
$crons_to_remove = array(
	'bkx_mobile_app_cleanup_devices',
	'bkx_mobile_app_send_reminders',
	'bkx_mobile_app_cleanup_push_log',
);

foreach ( $crons_to_remove as $cron ) {
	$timestamp = wp_next_scheduled( $cron );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $cron );
	}
}

// Remove user meta.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'bkx_mobile_app_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_mobile_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_mobile_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove log files.
$upload_dir = wp_upload_dir();
$log_dir    = $upload_dir['basedir'] . '/bkx-logs/mobile-app/';

if ( is_dir( $log_dir ) ) {
	$files = glob( $log_dir . '*' );
	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}
	rmdir( $log_dir );
}

// Remove APNS private key if stored.
if ( ! empty( $settings['apns_private_key_path'] ) && file_exists( $settings['apns_private_key_path'] ) ) {
	wp_delete_file( $settings['apns_private_key_path'] );
}

// Clear any cached data.
wp_cache_flush();
