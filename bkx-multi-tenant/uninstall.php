<?php
/**
 * Multi-Tenant Management Uninstall.
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\MultiTenant
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check user permissions.
if ( ! current_user_can( 'activate_plugins' ) ) {
	exit;
}

global $wpdb;

/**
 * Clean up plugin data.
 */
function bkx_multi_tenant_uninstall() {
	global $wpdb;

	// Only clean up if the option is set.
	$settings = get_site_option( 'bkx_multi_tenant_settings', array() );
	$delete_data = isset( $settings['delete_data_on_uninstall'] ) && $settings['delete_data_on_uninstall'];

	if ( ! $delete_data ) {
		// Just remove options, keep data.
		delete_site_option( 'bkx_multi_tenant_settings' );
		delete_site_option( 'bkx_multi_tenant_version' );
		delete_site_option( 'bkx_multi_tenant_license_key' );
		delete_site_option( 'bkx_multi_tenant_license_status' );
		return;
	}

	// Drop custom tables.
	$tables = array(
		$wpdb->base_prefix . 'bkx_tenants',
		$wpdb->base_prefix . 'bkx_tenant_sites',
		$wpdb->base_prefix . 'bkx_tenant_users',
		$wpdb->base_prefix . 'bkx_tenant_plans',
		$wpdb->base_prefix . 'bkx_tenant_usage',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Delete site options.
	delete_site_option( 'bkx_multi_tenant_settings' );
	delete_site_option( 'bkx_multi_tenant_version' );
	delete_site_option( 'bkx_multi_tenant_license_key' );
	delete_site_option( 'bkx_multi_tenant_license_status' );
	delete_site_option( 'bkx_multi_tenant_db_version' );

	// Clean up tenant meta from posts.
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_tenant%'" );

	// Clean up transients.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			'%_transient_bkx_tenant%',
			'%_transient_timeout_bkx_tenant%'
		)
	);

	// For each site in multisite.
	if ( is_multisite() ) {
		$sites = get_sites( array( 'fields' => 'ids' ) );

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );

			// Delete site-specific options.
			delete_option( 'bkx_current_tenant_id' );
			delete_option( 'bkx_tenant_branding_cache' );

			// Clean transients.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					'%_transient_bkx_tenant%',
					'%_transient_timeout_bkx_tenant%'
				)
			);

			restore_current_blog();
		}
	}

	// Clear any scheduled crons.
	$crons = array(
		'bkx_tenant_usage_cleanup',
		'bkx_tenant_check_limits',
		'bkx_tenant_check_trials',
		'bkx_tenant_billing_check',
	);

	foreach ( $crons as $cron ) {
		$timestamp = wp_next_scheduled( $cron );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $cron );
		}
	}

	// Delete log files.
	$upload_dir = wp_upload_dir();
	$log_dir = $upload_dir['basedir'] . '/bkx-multi-tenant-logs/';

	if ( is_dir( $log_dir ) ) {
		$files = glob( $log_dir . '*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		rmdir( $log_dir );
	}
}

bkx_multi_tenant_uninstall();
