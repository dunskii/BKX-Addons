<?php
/**
 * Security & Audit uninstall script.
 *
 * @package BookingX\SecurityAudit
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data on uninstall.
 */
function bkx_security_audit_uninstall() {
	global $wpdb;

	// Check if we should delete data on uninstall.
	$settings = get_option( 'bkx_security_audit_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Delete custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_audit_log',
		$wpdb->prefix . 'bkx_login_attempts',
		$wpdb->prefix . 'bkx_ip_lockouts',
		$wpdb->prefix . 'bkx_security_events',
		$wpdb->prefix . 'bkx_file_hashes',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	// Delete options.
	$options = array(
		'bkx_security_audit_settings',
		'bkx_security_audit_version',
		'bkx_security_audit_db_version',
		'bkx_security_audit_license_key',
		'bkx_security_audit_license_status',
		'bkx_security_last_scan',
		'bkx_security_baseline_date',
		'bkx_security_integrity_check',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete user meta.
	$user_meta_keys = array(
		'bkx_2fa_enabled',
		'bkx_2fa_secret',
		'bkx_2fa_temp_secret',
		'bkx_2fa_backup_codes',
	);

	foreach ( $user_meta_keys as $meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta_key ) );
	}

	// Delete transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_security_%' OR option_name LIKE '_transient_timeout_bkx_security_%' OR option_name LIKE '_transient_bkx_ip_info_%' OR option_name LIKE '_transient_timeout_bkx_ip_info_%'"
	);

	// Remove scheduled cron events.
	$cron_hooks = array(
		'bkx_security_daily_scan',
		'bkx_security_audit_cleanup',
	);

	foreach ( $cron_hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
		wp_unschedule_hook( $hook );
	}

	// Remove log files.
	$upload_dir = wp_upload_dir();
	$log_dir    = $upload_dir['basedir'] . '/bkx-security-logs/';

	if ( is_dir( $log_dir ) ) {
		bkx_security_delete_directory( $log_dir );
	}

	// Clear any caches.
	wp_cache_flush();
}

/**
 * Recursively delete a directory.
 *
 * @param string $dir Directory path.
 * @return bool
 */
function bkx_security_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . DIRECTORY_SEPARATOR . $file;
		if ( is_dir( $path ) ) {
			bkx_security_delete_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	return rmdir( $dir );
}

// Run uninstall.
bkx_security_audit_uninstall();
