<?php
/**
 * Enterprise API Suite Uninstall.
 *
 * @package BookingX\EnterpriseAPI
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
function bkx_enterprise_api_uninstall() {
	global $wpdb;

	// Only delete data if option is set.
	$settings = get_option( 'bkx_enterprise_api_settings', array() );
	$delete_data = isset( $settings['delete_data_on_uninstall'] ) && $settings['delete_data_on_uninstall'];

	if ( ! $delete_data ) {
		// Just remove options.
		delete_option( 'bkx_enterprise_api_settings' );
		delete_option( 'bkx_enterprise_api_version' );
		delete_option( 'bkx_enterprise_api_db_version' );
		return;
	}

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_oauth_clients',
		$wpdb->prefix . 'bkx_oauth_tokens',
		$wpdb->prefix . 'bkx_oauth_refresh_tokens',
		$wpdb->prefix . 'bkx_oauth_codes',
		$wpdb->prefix . 'bkx_api_keys',
		$wpdb->prefix . 'bkx_api_logs',
		$wpdb->prefix . 'bkx_webhooks',
		$wpdb->prefix . 'bkx_webhook_deliveries',
		$wpdb->prefix . 'bkx_rate_limits',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Delete options.
	delete_option( 'bkx_enterprise_api_settings' );
	delete_option( 'bkx_enterprise_api_version' );
	delete_option( 'bkx_enterprise_api_db_version' );

	// Clean up transients.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'%_transient_bkx_api%',
			'%_transient_timeout_bkx_api%'
		)
	);

	// Remove scheduled crons.
	$crons = array(
		'bkx_api_cleanup_logs',
		'bkx_api_cleanup_tokens',
		'bkx_api_cleanup_rate_limits',
		'bkx_webhook_retry',
	);

	foreach ( $crons as $cron ) {
		$timestamp = wp_next_scheduled( $cron );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $cron );
		}
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}

bkx_enterprise_api_uninstall();
