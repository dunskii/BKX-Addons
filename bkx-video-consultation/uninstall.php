<?php
/**
 * Uninstall Video Consultation Add-on.
 *
 * Handles clean removal of plugin data when uninstalled via WordPress admin.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 *
 * @since 1.0.0
 */
function bkx_video_consultation_uninstall() {
	global $wpdb;

	// Check if we should preserve data.
	$settings = get_option( 'bkx_video_consultation_settings', array() );
	if ( ! empty( $settings['preserve_data_on_uninstall'] ) ) {
		return;
	}

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_video_rooms',
		$wpdb->prefix . 'bkx_video_recordings',
		$wpdb->prefix . 'bkx_video_session_logs',
		$wpdb->prefix . 'bkx_video_waiting_room',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// Delete options.
	$options = array(
		'bkx_video_consultation_settings',
		'bkx_video_consultation_version',
		'bkx_video_consultation_db_version',
		'bkx_video_consultation_license_key',
		'bkx_video_consultation_license_status',
		'bkx_video_consultation_license_expires',
		'bkx_video_consultation_zoom_access_token',
		'bkx_video_consultation_zoom_token_expires',
		'bkx_video_consultation_google_tokens',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete post meta.
	$wpdb->query(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_video_%'"
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Delete recording files.
	$upload_dir = wp_upload_dir();
	$recordings_dir = $upload_dir['basedir'] . '/bkx-video-recordings';

	if ( is_dir( $recordings_dir ) ) {
		bkx_video_consultation_delete_directory( $recordings_dir );
	}

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_video_%' OR option_name LIKE '_transient_timeout_bkx_video_%'"
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Clear any scheduled cron events.
	wp_clear_scheduled_hook( 'bkx_video_cleanup_expired_recordings' );
	wp_clear_scheduled_hook( 'bkx_video_cleanup_old_sessions' );
	wp_clear_scheduled_hook( 'bkx_video_refresh_zoom_token' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}

/**
 * Recursively delete a directory.
 *
 * @since 1.0.0
 *
 * @param string $dir Directory path.
 * @return bool True on success.
 */
function bkx_video_consultation_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;

		if ( is_dir( $path ) ) {
			bkx_video_consultation_delete_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	return rmdir( $dir );
}

// Run uninstall.
bkx_video_consultation_uninstall();
