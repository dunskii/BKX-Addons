<?php
/**
 * Uninstall BookingX Customer Journey Analytics.
 *
 * @package BookingX\CustomerJourney
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete database tables.
$tables = array(
	$wpdb->prefix . 'bkx_cj_touchpoints',
	$wpdb->prefix . 'bkx_cj_lifecycle',
	$wpdb->prefix . 'bkx_cj_journeys',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete options.
delete_option( 'bkx_customer_journey_version' );

// Clear scheduled hooks.
wp_clear_scheduled_hook( 'bkx_cj_update_lifecycle' );
