<?php
/**
 * Apple Siri Integration Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\AppleSiri
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'bkx_apple_siri_settings' );
delete_option( 'bkx_apple_siri_donations' );
delete_option( 'bkx_apple_siri_pending_donations' );
delete_option( 'bkx_apple_siri_request_log' );

// Delete transients.
delete_transient( 'bkx_apple_siri_keys' );

// Delete user meta.
$users = get_users(
	array(
		'meta_key' => 'apple_user_id',
		'fields'   => 'ID',
	)
);

foreach ( $users as $user_id ) {
	delete_user_meta( $user_id, 'apple_user_id' );
}

// Clear any scheduled events.
wp_clear_scheduled_hook( 'bkx_apple_siri_cleanup_donations' );
