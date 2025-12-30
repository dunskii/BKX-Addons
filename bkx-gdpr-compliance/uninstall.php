<?php
/**
 * GDPR Compliance uninstall script.
 *
 * @package BookingX\GdprCompliance
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data on uninstall.
 */
function bkx_gdpr_compliance_uninstall() {
	global $wpdb;

	// Check if we should delete data on uninstall.
	$settings = get_option( 'bkx_gdpr_compliance_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Delete custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_consent_records',
		$wpdb->prefix . 'bkx_data_requests',
		$wpdb->prefix . 'bkx_processing_activities',
		$wpdb->prefix . 'bkx_data_breaches',
		$wpdb->prefix . 'bkx_cookie_consents',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	// Delete options.
	$options = array(
		'bkx_gdpr_compliance_settings',
		'bkx_gdpr_compliance_version',
		'bkx_gdpr_compliance_db_version',
		'bkx_gdpr_compliance_license_key',
		'bkx_gdpr_compliance_license_status',
		'bkx_gdpr_cookie_banner_dismissed',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete user meta.
	$user_meta_keys = array(
		'bkx_ccpa_do_not_sell',
		'bkx_ccpa_opt_out_date',
		'bkx_gdpr_data_exported',
		'bkx_gdpr_consent_version',
	);

	foreach ( $user_meta_keys as $meta_key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta_key ) );
	}

	// Delete transients.
	$transient_patterns = array(
		'bkx_gdpr_%',
		'bkx_ccpa_%',
		'bkx_consent_%',
	);

	foreach ( $transient_patterns as $pattern ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . $pattern,
				'_transient_timeout_' . $pattern
			)
		);
	}

	// Remove scheduled cron events.
	$cron_hooks = array(
		'bkx_gdpr_process_pending_requests',
		'bkx_gdpr_cleanup_expired_requests',
		'bkx_gdpr_data_retention_cleanup',
		'bkx_gdpr_consent_expiry_check',
		'bkx_gdpr_breach_reminder',
	);

	foreach ( $cron_hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
		wp_unschedule_hook( $hook );
	}

	// Remove any export files.
	$upload_dir = wp_upload_dir();
	$export_dir = $upload_dir['basedir'] . '/bkx-gdpr-exports/';

	if ( is_dir( $export_dir ) ) {
		bkx_gdpr_delete_directory( $export_dir );
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
function bkx_gdpr_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . DIRECTORY_SEPARATOR . $file;
		if ( is_dir( $path ) ) {
			bkx_gdpr_delete_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	return rmdir( $dir );
}

// Run uninstall.
bkx_gdpr_compliance_uninstall();
