<?php
/**
 * Uninstall script for BookingX Live Chat.
 *
 * Removes all plugin data when uninstalled.
 *
 * @package BookingX\LiveChat
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check user permissions.
if ( ! current_user_can( 'activate_plugins' ) ) {
	exit;
}

global $wpdb;

/**
 * Delete plugin options.
 */
$options = array(
	'bkx_livechat_settings',
	'bkx_livechat_db_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/**
 * Delete custom database tables.
 */
$tables = array(
	$wpdb->prefix . 'bkx_livechat_chats',
	$wpdb->prefix . 'bkx_livechat_messages',
	$wpdb->prefix . 'bkx_livechat_responses',
	$wpdb->prefix . 'bkx_livechat_operators',
	$wpdb->prefix . 'bkx_livechat_visitors',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Delete user meta.
 */
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'bkx_livechat_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

/**
 * Delete transients.
 */
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_livechat_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_livechat_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

/**
 * Clear scheduled events.
 */
wp_clear_scheduled_hook( 'bkx_livechat_cleanup_visitors' );
wp_clear_scheduled_hook( 'bkx_livechat_offline_check' );

/**
 * Remove capabilities.
 */
$roles = array( 'administrator', 'editor' );
$caps  = array(
	'bkx_livechat_respond',
	'bkx_livechat_manage_operators',
	'bkx_livechat_view_history',
	'bkx_livechat_manage_responses',
);

foreach ( $roles as $role_name ) {
	$role = get_role( $role_name );
	if ( $role ) {
		foreach ( $caps as $cap ) {
			$role->remove_cap( $cap );
		}
	}
}

/**
 * Multisite support.
 */
if ( is_multisite() ) {
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );

		// Delete options.
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Delete tables.
		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Delete user meta.
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'bkx_livechat_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Delete transients.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_livechat_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_livechat_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Clear scheduled events.
		wp_clear_scheduled_hook( 'bkx_livechat_cleanup_visitors' );
		wp_clear_scheduled_hook( 'bkx_livechat_offline_check' );

		restore_current_blog();
	}
}
