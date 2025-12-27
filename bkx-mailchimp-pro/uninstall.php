<?php
/**
 * Uninstall script for BookingX Mailchimp Pro.
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\MailchimpPro
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_mailchimp_pro_uninstall_options() {
	delete_option( 'bkx_mailchimp_pro_settings' );
	delete_option( 'bkx_mailchimp_pro_license_key' );
	delete_option( 'bkx_mailchimp_pro_license_status' );
	delete_option( 'bkx_mailchimp_pro_db_version' );

	// Delete transients
	delete_transient( 'bkx_mailchimp_pro_activated' );
	delete_transient( 'bkx_mailchimp_pro_lists' );
	delete_transient( 'bkx_mailchimp_pro_tags' );
}

/**
 * Clear scheduled events.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_mailchimp_pro_uninstall_events() {
	wp_clear_scheduled_hook( 'bkx_mailchimp_pro_sync' );
	wp_clear_scheduled_hook( 'bkx_mailchimp_pro_cleanup' );
}

/**
 * Drop custom tables.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_mailchimp_pro_uninstall_tables() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'bkx_mailchimp_sync_log';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
}

// Only remove data if the constant is defined
if ( defined( 'BKX_MAILCHIMP_PRO_REMOVE_ALL_DATA' ) && BKX_MAILCHIMP_PRO_REMOVE_ALL_DATA ) {
	bkx_mailchimp_pro_uninstall_options();
	bkx_mailchimp_pro_uninstall_events();
	bkx_mailchimp_pro_uninstall_tables();
}
