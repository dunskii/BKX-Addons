<?php
/**
 * Bulk Purchase Manager Service.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

namespace BookingX\BulkRecurringPayments\Services;

/**
 * BulkPurchaseManager class.
 *
 * Handles bulk package purchases and credit usage.
 *
 * @since 1.0.0
 */
class BulkPurchaseManager {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Usage table name.
	 *
	 * @var string
	 */
	private $usage_table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		global $wpdb;
		$this->settings    = $settings;
		$this->table       = $wpdb->prefix . 'bkx_bulk_purchases';
		$this->usage_table = $wpdb->prefix . 'bkx_bulk_usage';
	}

	/**
	 * Get a bulk purchase by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Bulk purchase ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$purchase = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$this->table,
				$id
			)
		);

		if ( $purchase ) {
			$purchase = $this->hydrate( $purchase );
		}

		return $purchase;
	}

	/**
	 * Get bulk purchases by customer.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_by_customer( $customer_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( $wpdb->prepare( 'customer_id = %d', $customer_id ) );

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$purchases = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY %i %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->table,
				$args['orderby'],
				$args['order']
			)
		);

		return array_map( array( $this, 'hydrate' ), $purchases );
	}

	/**
	 * Get available credits for a customer.
	 *
	 * @since 1.0.0
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public function get_available( $customer_id ) {
		global $wpdb;

		$today = current_time( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$purchases = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				WHERE customer_id = %d
				AND status = 'active'
				AND (quantity_purchased - quantity_used) > 0
				AND (expires_at IS NULL OR expires_at > %s)
				ORDER BY expires_at ASC, created_at ASC",
				$this->table,
				$customer_id,
				$today
			)
		);

		return array_map( array( $this, 'hydrate' ), $purchases );
	}

	/**
	 * Get all bulk purchases.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'package' => 0,
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 100,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		if ( ! empty( $args['package'] ) ) {
			$where[] = $wpdb->prepare( 'package_id = %d', $args['package'] );
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$purchases = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY %i %s LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->table,
				$args['orderby'],
				$args['order'],
				$args['limit'],
				$args['offset']
			)
		);

		return array_map( array( $this, 'hydrate' ), $purchases );
	}

	/**
	 * Create a bulk purchase.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $customer_id Customer ID.
	 * @param object $package Package object.
	 * @param string $gateway Payment gateway.
	 * @param string $payment_method Payment method ID.
	 * @return array|\WP_Error
	 */
	public function create( $customer_id, $package, $gateway, $payment_method ) {
		global $wpdb;

		// Calculate pricing.
		$quantity    = $package->quantity ?? 1;
		$price_info  = ( new PackageManager( $this->settings ) )->calculate_price( $package, $quantity );
		$unit_price  = $package->price / $quantity;

		// Calculate expiry.
		$expiry_days = $this->settings['bulk_expiry_days'] ?? 365;
		$expires_at  = gmdate( 'Y-m-d', strtotime( "+{$expiry_days} days" ) );

		// Insert purchase.
		$data = array(
			'customer_id'        => $customer_id,
			'package_id'         => $package->id,
			'quantity_purchased' => $quantity,
			'quantity_used'      => 0,
			'unit_price'         => $unit_price,
			'total_price'        => $price_info['total'],
			'discount_applied'   => $price_info['discount'],
			'gateway'            => $gateway,
			'status'             => 'pending',
			'expires_at'         => $expires_at,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			$data,
			array( '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create purchase.', 'bkx-bulk-recurring-payments' ) );
		}

		$purchase_id = $wpdb->insert_id;

		// Process payment.
		$payment_result = $this->process_payment( $purchase_id, $price_info['total'], $gateway, $payment_method );

		if ( is_wp_error( $payment_result ) ) {
			// Delete the purchase.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $this->table, array( 'id' => $purchase_id ), array( '%d' ) );
			return $payment_result;
		}

		// Update with payment info.
		$update_data = array(
			'gateway_payment_id' => $payment_result['payment_id'],
		);

		// Auto-activate if enabled.
		if ( ! empty( $this->settings['auto_activate_bulk'] ) ) {
			$update_data['status']       = 'active';
			$update_data['activated_at'] = current_time( 'mysql' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $purchase_id ),
			null,
			array( '%d' )
		);

		// Increment package purchase count.
		( new PackageManager( $this->settings ) )->increment_purchase_count( $package->id );

		/**
		 * Fires after a bulk purchase is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $purchase_id Purchase ID.
		 * @param int    $customer_id Customer ID.
		 * @param object $package     Package object.
		 */
		do_action( 'bkx_bulk_purchase_created', $purchase_id, $customer_id, $package );

		return array(
			'purchase_id'  => $purchase_id,
			'redirect_url' => $payment_result['redirect_url'] ?? null,
		);
	}

	/**
	 * Process payment for bulk purchase.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $purchase_id Purchase ID.
	 * @param float  $amount Amount to charge.
	 * @param string $gateway Gateway name.
	 * @param string $payment_method Payment method ID.
	 * @return array|\WP_Error
	 */
	private function process_payment( $purchase_id, $amount, $gateway, $payment_method ) {
		/**
		 * Filter to process bulk payment.
		 *
		 * @since 1.0.0
		 *
		 * @param array|\WP_Error $result         Payment result.
		 * @param int             $purchase_id    Purchase ID.
		 * @param float           $amount         Amount to charge.
		 * @param string          $payment_method Payment method ID.
		 */
		return apply_filters(
			"bkx_process_{$gateway}_bulk_payment",
			new \WP_Error( 'not_implemented', __( 'Gateway not configured.', 'bkx-bulk-recurring-payments' ) ),
			$purchase_id,
			$amount,
			$payment_method
		);
	}

	/**
	 * Use a credit from a bulk purchase.
	 *
	 * @since 1.0.0
	 *
	 * @param int $purchase_id Bulk purchase ID.
	 * @param int $booking_id Booking ID.
	 * @param int $quantity Quantity to use.
	 * @return bool|\WP_Error
	 */
	public function use_credit( $purchase_id, $booking_id, $quantity = 1 ) {
		global $wpdb;

		$purchase = $this->get( $purchase_id );

		if ( ! $purchase ) {
			return new \WP_Error( 'not_found', __( 'Purchase not found.', 'bkx-bulk-recurring-payments' ) );
		}

		if ( 'active' !== $purchase->status ) {
			return new \WP_Error( 'invalid_status', __( 'Purchase is not active.', 'bkx-bulk-recurring-payments' ) );
		}

		$remaining = $purchase->quantity_purchased - $purchase->quantity_used;

		if ( $quantity > $remaining ) {
			return new \WP_Error( 'insufficient_credits', __( 'Insufficient credits.', 'bkx-bulk-recurring-payments' ) );
		}

		// Check expiry.
		if ( $purchase->expires_at && $purchase->expires_at < current_time( 'Y-m-d' ) ) {
			return new \WP_Error( 'expired', __( 'Credits have expired.', 'bkx-bulk-recurring-payments' ) );
		}

		// Record usage.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$usage_result = $wpdb->insert(
			$this->usage_table,
			array(
				'bulk_purchase_id' => $purchase_id,
				'booking_id'       => $booking_id,
				'quantity_used'    => $quantity,
			),
			array( '%d', '%d', '%d' )
		);

		if ( false === $usage_result ) {
			return new \WP_Error( 'db_error', __( 'Failed to record usage.', 'bkx-bulk-recurring-payments' ) );
		}

		// Update purchase.
		$new_used = $purchase->quantity_used + $quantity;

		$update_data = array( 'quantity_used' => $new_used );

		// Check if depleted.
		if ( $new_used >= $purchase->quantity_purchased ) {
			$update_data['status'] = 'depleted';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $purchase_id ),
			null,
			array( '%d' )
		);

		// Update booking meta.
		update_post_meta( $booking_id, '_bkx_bulk_purchase_id', $purchase_id );
		update_post_meta( $booking_id, '_bkx_payment_method', 'bulk_credit' );

		/**
		 * Fires after a bulk credit is used.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $purchase_id Purchase ID.
		 * @param int    $booking_id  Booking ID.
		 * @param int    $quantity    Quantity used.
		 * @param object $purchase    Purchase object.
		 */
		do_action( 'bkx_bulk_credit_used', $purchase_id, $booking_id, $quantity, $purchase );

		return true;
	}

	/**
	 * Apply a credit to a booking.
	 *
	 * @since 1.0.0
	 *
	 * @param int $bulk_purchase_id Bulk purchase ID.
	 * @param int $booking_id Booking ID.
	 * @param int $customer_id Customer ID (for verification).
	 * @return array|\WP_Error
	 */
	public function apply_credit( $bulk_purchase_id, $booking_id, $customer_id ) {
		$purchase = $this->get( $bulk_purchase_id );

		if ( ! $purchase ) {
			return new \WP_Error( 'not_found', __( 'Purchase not found.', 'bkx-bulk-recurring-payments' ) );
		}

		// Verify ownership.
		if ( (int) $purchase->customer_id !== $customer_id ) {
			return new \WP_Error( 'not_authorized', __( 'You do not own this credit package.', 'bkx-bulk-recurring-payments' ) );
		}

		// Verify booking ownership.
		$booking_customer = get_post_meta( $booking_id, 'customer_id', true );
		if ( (int) $booking_customer !== $customer_id ) {
			return new \WP_Error( 'not_authorized', __( 'You do not own this booking.', 'bkx-bulk-recurring-payments' ) );
		}

		// Use the credit.
		$result = $this->use_credit( $bulk_purchase_id, $booking_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'          => true,
			'remaining_credits' => $purchase->quantity_purchased - $purchase->quantity_used - 1,
		);
	}

	/**
	 * Refund a credit from a cancelled booking.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool|\WP_Error
	 */
	public function refund_credit( $booking_id ) {
		global $wpdb;

		// Get usage record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$usage = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE booking_id = %d",
				$this->usage_table,
				$booking_id
			)
		);

		if ( ! $usage ) {
			return new \WP_Error( 'not_found', __( 'No credit usage found for this booking.', 'bkx-bulk-recurring-payments' ) );
		}

		// Delete usage record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$this->usage_table,
			array( 'id' => $usage->id ),
			array( '%d' )
		);

		// Update purchase.
		$purchase = $this->get( $usage->bulk_purchase_id );

		if ( $purchase ) {
			$new_used = max( 0, $purchase->quantity_used - $usage->quantity_used );

			$update_data = array( 'quantity_used' => $new_used );

			// Reactivate if was depleted.
			if ( 'depleted' === $purchase->status && $new_used < $purchase->quantity_purchased ) {
				$update_data['status'] = 'active';
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				$update_data,
				array( 'id' => $usage->bulk_purchase_id ),
				null,
				array( '%d' )
			);
		}

		// Remove booking meta.
		delete_post_meta( $booking_id, '_bkx_bulk_purchase_id' );

		/**
		 * Fires after a bulk credit is refunded.
		 *
		 * @since 1.0.0
		 *
		 * @param int $booking_id  Booking ID.
		 * @param int $purchase_id Purchase ID.
		 * @param int $quantity    Quantity refunded.
		 */
		do_action( 'bkx_bulk_credit_refunded', $booking_id, $usage->bulk_purchase_id, $usage->quantity_used );

		return true;
	}

	/**
	 * Check for expired bulk purchases.
	 *
	 * @since 1.0.0
	 */
	public function check_expiry() {
		global $wpdb;

		$today = current_time( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE status = 'active' AND expires_at <= %s",
				$this->table,
				$today
			)
		);

		foreach ( $expired as $purchase ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array( 'status' => 'expired' ),
				array( 'id' => $purchase->id ),
				array( '%s' ),
				array( '%d' )
			);

			/**
			 * Fires after a bulk purchase expires.
			 *
			 * @since 1.0.0
			 *
			 * @param int $purchase_id Purchase ID.
			 */
			do_action( 'bkx_bulk_purchase_expired', $purchase->id );
		}
	}

	/**
	 * Get usage history for a purchase.
	 *
	 * @since 1.0.0
	 *
	 * @param int $purchase_id Purchase ID.
	 * @return array
	 */
	public function get_usage_history( $purchase_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.*, p.post_title as booking_title
				FROM %i u
				LEFT JOIN {$wpdb->posts} p ON u.booking_id = p.ID
				WHERE u.bulk_purchase_id = %d
				ORDER BY u.used_at DESC",
				$this->usage_table,
				$purchase_id
			)
		);
	}

	/**
	 * Hydrate purchase object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $purchase Raw purchase data.
	 * @return object
	 */
	private function hydrate( $purchase ) {
		$purchase->meta_data = json_decode( $purchase->meta_data ?? '{}', true ) ?: array();

		// Calculate remaining.
		$purchase->quantity_remaining = $purchase->quantity_purchased - $purchase->quantity_used;

		// Load package.
		$purchase->package = ( new PackageManager( $this->settings ) )->get( $purchase->package_id );

		// Load customer.
		$purchase->customer = get_userdata( $purchase->customer_id );

		// Add status label.
		$purchase->status_label = $this->get_status_label( $purchase->status );

		// Check if expired.
		if ( 'active' === $purchase->status && $purchase->expires_at ) {
			$purchase->is_expired = $purchase->expires_at < current_time( 'Y-m-d' );
			$purchase->days_until_expiry = floor( ( strtotime( $purchase->expires_at ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS );
		}

		return $purchase;
	}

	/**
	 * Get status label.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'pending'  => __( 'Pending', 'bkx-bulk-recurring-payments' ),
			'active'   => __( 'Active', 'bkx-bulk-recurring-payments' ),
			'depleted' => __( 'Depleted', 'bkx-bulk-recurring-payments' ),
			'expired'  => __( 'Expired', 'bkx-bulk-recurring-payments' ),
			'refunded' => __( 'Refunded', 'bkx-bulk-recurring-payments' ),
		);

		return $labels[ $status ] ?? ucfirst( $status );
	}
}
