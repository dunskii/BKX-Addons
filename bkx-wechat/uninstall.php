<?php
/**
 * WeChat Integration Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\WeChat
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'bkx_wechat_settings' );
delete_option( 'bkx_wechat_qr_codes' );
delete_option( 'bkx_wechat_message_log' );

// Delete transients.
delete_transient( 'bkx_wechat_access_token_official_account' );
delete_transient( 'bkx_wechat_access_token_mini_program' );

// Delete user meta.
$meta_keys = array(
	'wechat_openid',
	'wechat_mini_openid',
	'wechat_unionid',
	'wechat_nickname',
	'wechat_headimgurl',
	'wechat_sex',
	'wechat_city',
	'wechat_province',
	'wechat_country',
	'wechat_unsubscribed',
);

global $wpdb;

foreach ( $meta_keys as $key ) {
	$wpdb->delete(
		$wpdb->usermeta,
		array( 'meta_key' => $key )
	);

	// Also delete preference keys.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			'wechat_pref_%'
		)
	);
}

// Delete booking meta.
$booking_meta_keys = array(
	'_wechat_openid',
	'_wechat_source',
	'_wechat_order_no',
	'_wechat_transaction_id',
	'_wechat_paid_amount',
	'_wechat_paid_at',
);

foreach ( $booking_meta_keys as $key ) {
	$wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => $key )
	);
}

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_wechat_send_reminders' );
