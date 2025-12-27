<?php
/**
 * Uninstall script for Outlook Calendar
 *
 * @package BookingX\OutlookCalendar
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'bkx_outlook_settings' );
delete_option( 'bkx_outlook_license_key' );
delete_option( 'bkx_outlook_license_status' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_outlook_sync' );
