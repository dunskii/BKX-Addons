<?php
/**
 * IFTTT Integration Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\IFTTT
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'bkx_ifttt_settings' );
delete_option( 'bkx_ifttt_trigger_logs' );
delete_option( 'bkx_ifttt_action_logs' );
delete_option( 'bkx_ifttt_webhook_logs' );
delete_option( 'bkx_ifttt_error_logs' );

// Delete transients.
global $wpdb;

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'_transient_bkx_ifttt_%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'_transient_timeout_bkx_ifttt_%'
	)
);

// Delete booking meta.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		'_ifttt_%'
	)
);

// Delete meta for bookings created via IFTTT.
$wpdb->delete(
	$wpdb->postmeta,
	array( 'meta_key' => '_created_via', 'meta_value' => 'ifttt' )
);

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_ifttt_cleanup_logs' );
