<?php
/**
 * Uninstall script for CRM Enhanced.
 *
 * @package BookingX\CRM
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 */
function bkx_crm_uninstall() {
	global $wpdb;

	// Delete options.
	delete_option( 'bkx_crm_settings' );
	delete_option( 'bkx_crm_db_version' );

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_crm_customers',
		$wpdb->prefix . 'bkx_crm_tags',
		$wpdb->prefix . 'bkx_crm_customer_tags',
		$wpdb->prefix . 'bkx_crm_segments',
		$wpdb->prefix . 'bkx_crm_notes',
		$wpdb->prefix . 'bkx_crm_communications',
		$wpdb->prefix . 'bkx_crm_followups',
		$wpdb->prefix . 'bkx_crm_activities',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_crm_%' OR option_name LIKE '_transient_timeout_bkx_crm_%'"
	);

	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_crm_process_followups' );
	wp_clear_scheduled_hook( 'bkx_crm_check_birthdays' );
	wp_clear_scheduled_hook( 'bkx_crm_sync_customers' );
}

// Handle multisite.
if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		bkx_crm_uninstall();
		restore_current_blog();
	}
} else {
	bkx_crm_uninstall();
}
