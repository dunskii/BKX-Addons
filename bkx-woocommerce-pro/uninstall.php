<?php
/**
 * Uninstall script for WooCommerce Pro Integration.
 *
 * @package BookingX\WooCommercePro
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
function bkx_woocommerce_uninstall() {
	global $wpdb;

	// Delete options.
	$options = array(
		'bkx_woocommerce_settings',
		'bkx_woocommerce_db_version',
		'bkx_woocommerce_license_key',
		'bkx_woocommerce_license_status',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Get all booking products.
	$booking_products = $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
		INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
		INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
		WHERE p.post_type = 'product'
		AND tt.taxonomy = 'product_type'
		AND t.slug = 'bkx_booking'"
	);

	// Delete booking product meta.
	foreach ( $booking_products as $product_id ) {
		delete_post_meta( $product_id, '_bkx_service_id' );
		delete_post_meta( $product_id, '_bkx_seat_id' );
		delete_post_meta( $product_id, '_bkx_duration' );
		delete_post_meta( $product_id, '_bkx_requires_date' );
		delete_post_meta( $product_id, '_bkx_requires_seat' );
		delete_post_meta( $product_id, '_bkx_sold_individually' );
		delete_post_meta( $product_id, '_bkx_allowed_extras' );
	}

	// Delete service to product links.
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_bkx_woo_product_id'" );

	// Delete order booking meta.
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_bkx_booking_ids'" );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_bkx_booking_id'" );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_bkx_booking_data'" );

	// Delete booking order links.
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'from_woo_order'" );

	// Delete product type term if no products use it.
	$term = get_term_by( 'slug', 'bkx_booking', 'product_type' );
	if ( $term && empty( $booking_products ) ) {
		wp_delete_term( $term->term_id, 'product_type' );
	}

	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_woo_daily_sync' );

	// Clear transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_woo_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_woo_%'" );

	// Flush rewrite rules.
	flush_rewrite_rules();
}

// Run uninstall.
bkx_woocommerce_uninstall();
