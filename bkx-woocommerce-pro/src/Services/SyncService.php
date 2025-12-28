<?php
/**
 * Sync Service.
 *
 * Handles bidirectional synchronization between BookingX and WooCommerce.
 *
 * @package BookingX\WooCommercePro\Services
 * @since   1.0.0
 */

namespace BookingX\WooCommercePro\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SyncService Class.
 */
class SyncService {

	/**
	 * Instance.
	 *
	 * @var SyncService
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return SyncService
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Price sync.
		add_action( 'save_post_bkx_base', array( $this, 'sync_service_price' ), 20, 2 );
		add_action( 'woocommerce_product_set_price', array( $this, 'sync_product_price' ), 10, 2 );

		// Availability sync.
		add_action( 'bkx_booking_status_changed', array( $this, 'update_product_stock' ), 10, 3 );

		// Coupon sync.
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon_for_booking' ), 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'validate_coupon_for_booking_product' ), 10, 4 );

		// Scheduled sync.
		add_action( 'bkx_woo_daily_sync', array( $this, 'run_daily_sync' ) );

		if ( ! wp_next_scheduled( 'bkx_woo_daily_sync' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_woo_daily_sync' );
		}
	}

	/**
	 * Sync all products.
	 *
	 * @return array Results.
	 */
	public function sync_all_products() {
		$product_integration = ProductIntegration::get_instance();
		$results             = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		// Get all services.
		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		foreach ( $services as $service ) {
			$existing = $product_integration->get_product_for_service( $service->ID );

			if ( $existing ) {
				$updated = $product_integration->update_product_from_service( $service->ID );
				if ( $updated ) {
					$results['updated']++;
				} else {
					$results['skipped']++;
				}
			} else {
				$product_id = $product_integration->create_product_from_service( $service->ID );
				if ( $product_id ) {
					$results['created']++;
				} else {
					$results['errors'][] = sprintf(
						/* translators: %s: service title */
						__( 'Failed to create product for service: %s', 'bkx-woocommerce-pro' ),
						$service->post_title
					);
				}
			}
		}

		$results['total'] = $results['created'] + $results['updated'] + $results['skipped'];

		do_action( 'bkx_woo_sync_completed', $results );

		return $results;
	}

	/**
	 * Sync service price to product.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function sync_service_price( $post_id, $post ) {
		if ( 'bkx_base' !== $post->post_type ) {
			return;
		}

		$settings = get_option( 'bkx_woocommerce_settings', array() );

		if ( empty( $settings['sync_inventory'] ) ) {
			return;
		}

		$product_integration = ProductIntegration::get_instance();
		$product_id          = $product_integration->get_product_for_service( $post_id );

		if ( ! $product_id ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$price = get_post_meta( $post_id, 'base_price', true );

		// Only update if different.
		if ( $product->get_regular_price() !== $price ) {
			$product->set_regular_price( $price );
			$product->set_price( $price );
			$product->save();
		}
	}

	/**
	 * Sync product price to service.
	 *
	 * @param string      $price   New price.
	 * @param \WC_Product $product Product object.
	 */
	public function sync_product_price( $price, $product ) {
		if ( 'bkx_booking' !== $product->get_type() ) {
			return;
		}

		$settings = get_option( 'bkx_woocommerce_settings', array() );

		if ( empty( $settings['sync_inventory'] ) ) {
			return;
		}

		$service_id = $product->get_meta( '_bkx_service_id' );

		if ( ! $service_id ) {
			return;
		}

		$current_price = get_post_meta( $service_id, 'base_price', true );

		// Only update if different.
		if ( $current_price !== $price ) {
			update_post_meta( $service_id, 'base_price', $price );
		}
	}

	/**
	 * Sync booking status to order.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status.
	 */
	public function sync_booking_status_to_order( $booking_id, $new_status ) {
		$order_id = get_post_meta( $booking_id, 'from_woo_order', true );

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$settings = get_option( 'bkx_woocommerce_settings', array() );
		$mapping  = isset( $settings['order_status_mapping'] ) ? $settings['order_status_mapping'] : array();

		// Reverse mapping.
		$reverse_mapping = array_flip( $mapping );

		if ( isset( $reverse_mapping[ $new_status ] ) ) {
			$new_order_status = $reverse_mapping[ $new_status ];

			// Only update if different.
			if ( $order->get_status() !== $new_order_status ) {
				$order->update_status(
					$new_order_status,
					/* translators: %s: new status */
					sprintf( __( 'Status updated from BookingX (%s)', 'bkx-woocommerce-pro' ), $new_status )
				);
			}
		}
	}

	/**
	 * Update product stock status based on availability.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function update_product_stock( $booking_id, $old_status, $new_status ) {
		// This is mainly for display purposes.
		// Actual availability is checked in real-time.
	}

	/**
	 * Validate coupon for booking products.
	 *
	 * @param bool       $valid  Is valid.
	 * @param \WC_Coupon $coupon Coupon object.
	 * @return bool
	 */
	public function validate_coupon_for_booking( $valid, $coupon ) {
		if ( ! $valid ) {
			return $valid;
		}

		$settings = get_option( 'bkx_woocommerce_settings', array() );

		if ( empty( $settings['enable_coupons'] ) ) {
			// Check if cart has booking products.
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product = $cart_item['data'];
				if ( $product && 'bkx_booking' === $product->get_type() ) {
					throw new \Exception( __( 'Coupons cannot be applied to booking products.', 'bkx-woocommerce-pro' ) );
				}
			}
		}

		return $valid;
	}

	/**
	 * Validate coupon for specific booking product.
	 *
	 * @param bool       $valid   Is valid.
	 * @param \WC_Product $product Product object.
	 * @param \WC_Coupon $coupon  Coupon object.
	 * @param array      $values  Cart item values.
	 * @return bool
	 */
	public function validate_coupon_for_booking_product( $valid, $product, $coupon, $values ) {
		if ( ! $valid ) {
			return $valid;
		}

		if ( 'bkx_booking' !== $product->get_type() ) {
			return $valid;
		}

		$settings = get_option( 'bkx_woocommerce_settings', array() );

		if ( empty( $settings['enable_coupons'] ) ) {
			return false;
		}

		// Check if coupon has excluded product types.
		$excluded_types = $coupon->get_meta( 'exclude_product_types' );
		if ( is_array( $excluded_types ) && in_array( 'bkx_booking', $excluded_types, true ) ) {
			return false;
		}

		return $valid;
	}

	/**
	 * Run daily sync.
	 */
	public function run_daily_sync() {
		$settings = get_option( 'bkx_woocommerce_settings', array() );

		if ( empty( $settings['sync_inventory'] ) ) {
			return;
		}

		$this->sync_all_products();
		$this->cleanup_expired_bookings();
	}

	/**
	 * Cleanup expired pending bookings.
	 */
	private function cleanup_expired_bookings() {
		$settings    = get_option( 'bkx_woocommerce_settings', array() );
		$hold_time   = isset( $settings['booking_hold_time'] ) ? absint( $settings['booking_hold_time'] ) : 60; // minutes
		$cutoff_time = gmdate( 'Y-m-d H:i:s', time() - ( $hold_time * 60 ) );

		// Find expired pending bookings from WooCommerce.
		global $wpdb;

		$expired_bookings = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'bkx_booking'
			AND p.post_status = 'bkx-pending'
			AND pm.meta_key = 'from_woo_order'
			AND p.post_date < %s",
			$cutoff_time
		) );

		foreach ( $expired_bookings as $booking_id ) {
			$order_id = get_post_meta( $booking_id, 'from_woo_order', true );
			$order    = wc_get_order( $order_id );

			// Only cleanup if order is also pending/failed.
			if ( $order && in_array( $order->get_status(), array( 'pending', 'failed' ), true ) ) {
				wp_update_post( array(
					'ID'          => $booking_id,
					'post_status' => 'bkx-cancelled',
				) );

				do_action( 'bkx_woo_booking_expired', $booking_id, $order_id );
			}
		}
	}

	/**
	 * Get sync statistics.
	 *
	 * @return array
	 */
	public function get_sync_stats() {
		$product_integration = ProductIntegration::get_instance();

		// Count services.
		$total_services = wp_count_posts( 'bkx_base' );
		$published_services = isset( $total_services->publish ) ? $total_services->publish : 0;

		// Count products.
		$booking_products = wc_get_products( array(
			'type'   => 'bkx_booking',
			'status' => 'publish',
			'limit'  => -1,
			'return' => 'ids',
		) );

		// Find unsynced services.
		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		) );

		$synced   = 0;
		$unsynced = 0;

		foreach ( $services as $service_id ) {
			$product_id = $product_integration->get_product_for_service( $service_id );
			if ( $product_id ) {
				$synced++;
			} else {
				$unsynced++;
			}
		}

		return array(
			'total_services'    => $published_services,
			'total_products'    => count( $booking_products ),
			'synced_services'   => $synced,
			'unsynced_services' => $unsynced,
			'sync_percentage'   => $published_services > 0 ? round( ( $synced / $published_services ) * 100 ) : 0,
		);
	}

	/**
	 * Export sync report.
	 *
	 * @return array
	 */
	public function export_sync_report() {
		$product_integration = ProductIntegration::get_instance();
		$report              = array();

		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		foreach ( $services as $service ) {
			$product_id    = $product_integration->get_product_for_service( $service->ID );
			$service_price = get_post_meta( $service->ID, 'base_price', true );

			$row = array(
				'service_id'    => $service->ID,
				'service_title' => $service->post_title,
				'service_price' => $service_price,
				'product_id'    => $product_id,
				'product_price' => '',
				'synced'        => ! empty( $product_id ),
				'price_match'   => false,
			);

			if ( $product_id ) {
				$product              = wc_get_product( $product_id );
				$row['product_price'] = $product ? $product->get_price() : '';
				$row['price_match']   = $row['service_price'] === $row['product_price'];
			}

			$report[] = $row;
		}

		return $report;
	}
}
