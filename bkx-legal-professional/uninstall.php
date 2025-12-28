<?php
/**
 * Uninstall script for Legal & Professional Services.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check user has permission.
if ( ! current_user_can( 'delete_plugins' ) ) {
	exit;
}

/**
 * Delete plugin data on uninstall.
 */
function bkx_legal_uninstall() {
	global $wpdb;

	// Delete options.
	$options = array(
		'bkx_legal_settings',
		'bkx_legal_db_version',
		'bkx_legal_license_key',
		'bkx_legal_license_status',
	);

	// Delete sequence options (multiple years).
	$years = range( 2020, (int) gmdate( 'Y' ) + 1 );
	foreach ( $years as $year ) {
		$options[] = 'bkx_legal_matter_sequence_' . $year;
		for ( $month = 1; $month <= 12; $month++ ) {
			$options[] = 'bkx_legal_invoice_sequence_' . $year . str_pad( $month, 2, '0', STR_PAD_LEFT );
		}
	}

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete custom post types and their meta.
	$post_types = array( 'bkx_matter', 'bkx_intake_template', 'bkx_retainer', 'bkx_attorney' );

	foreach ( $post_types as $post_type ) {
		$posts = get_posts( array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	// Delete taxonomies.
	$taxonomies = array( 'bkx_practice_area', 'bkx_matter_type', 'bkx_matter_status' );

	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		) );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				wp_delete_term( $term_id, $taxonomy );
			}
		}
	}

	// Drop custom tables.
	$tables = array(
		'bkx_legal_intakes',
		'bkx_legal_parties',
		'bkx_legal_conflict_checks',
		'bkx_legal_documents',
		'bkx_legal_document_access_log',
		'bkx_legal_retainers',
		'bkx_legal_time_entries',
		'bkx_legal_expenses',
		'bkx_legal_invoices',
		'bkx_legal_invoice_items',
		'bkx_legal_payments',
		'bkx_legal_deadlines',
		'bkx_legal_notes',
		'bkx_legal_activity_log',
		'bkx_legal_trust_transactions',
		'bkx_legal_messages',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// Delete post meta related to legal matters.
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_matter_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_attorney_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_bkx_matter_id'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_bkx_time_entry_id'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Delete uploaded documents directory.
	$upload_dir = wp_upload_dir();
	$legal_dir  = $upload_dir['basedir'] . '/bkx-legal-documents';

	if ( is_dir( $legal_dir ) ) {
		bkx_legal_delete_directory( $legal_dir );
	}

	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_legal_daily_deadline_check' );

	// Clear transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_legal_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_legal_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Flush rewrite rules.
	flush_rewrite_rules();
}

/**
 * Recursively delete a directory.
 *
 * @param string $dir Directory path.
 * @return bool
 */
function bkx_legal_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;
		if ( is_dir( $path ) ) {
			bkx_legal_delete_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	return rmdir( $dir );
}

// Run uninstall.
bkx_legal_uninstall();
