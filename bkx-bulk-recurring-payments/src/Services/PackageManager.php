<?php
/**
 * Package Manager Service.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

namespace BookingX\BulkRecurringPayments\Services;

/**
 * PackageManager class.
 *
 * Handles CRUD operations for payment packages.
 *
 * @since 1.0.0
 */
class PackageManager {

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		global $wpdb;
		$this->settings = $settings;
		$this->table    = $wpdb->prefix . 'bkx_payment_packages';
	}

	/**
	 * Get a package by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Package ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$package = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$this->table,
				$id
			)
		);

		if ( $package ) {
			$package = $this->hydrate( $package );
		}

		return $package;
	}

	/**
	 * Get all packages.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'type'    => '',
			'status'  => '',
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 100,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );

		if ( ! empty( $args['type'] ) ) {
			$where[] = $wpdb->prepare( 'package_type = %s', $args['type'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$packages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY %i %s LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->table,
				$args['orderby'],
				$args['order'],
				$args['limit'],
				$args['offset']
			)
		);

		return array_map( array( $this, 'hydrate' ), $packages );
	}

	/**
	 * Get packages for a specific service.
	 *
	 * @since 1.0.0
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	public function get_for_service( $service_id ) {
		$packages = $this->get_all( array( 'status' => 'active' ) );

		return array_filter(
			$packages,
			function ( $package ) use ( $service_id ) {
				if ( empty( $package->service_ids ) ) {
					return true; // Package applies to all services.
				}
				return in_array( $service_id, $package->service_ids, true );
			}
		);
	}

	/**
	 * Save a package.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Package data.
	 * @return object|\WP_Error
	 */
	public function save( $data ) {
		global $wpdb;

		// Validate required fields.
		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'Package name is required.', 'bkx-bulk-recurring-payments' ) );
		}

		if ( empty( $data['price'] ) || $data['price'] <= 0 ) {
			return new \WP_Error( 'invalid_price', __( 'Package price must be greater than 0.', 'bkx-bulk-recurring-payments' ) );
		}

		// Prepare data for database.
		$db_data = array(
			'name'            => sanitize_text_field( $data['name'] ),
			'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
			'package_type'    => in_array( $data['package_type'], array( 'bulk', 'recurring' ), true ) ? $data['package_type'] : 'bulk',
			'service_ids'     => wp_json_encode( $data['service_ids'] ?? array() ),
			'quantity'        => 'bulk' === $data['package_type'] ? absint( $data['quantity'] ?? 1 ) : null,
			'interval_type'   => $data['interval_type'] ?? null,
			'interval_count'  => absint( $data['interval_count'] ?? 1 ),
			'billing_cycles'  => absint( $data['billing_cycles'] ?? 0 ),
			'price'           => floatval( $data['price'] ),
			'discount_type'   => in_array( $data['discount_type'], array( 'percentage', 'fixed' ), true ) ? $data['discount_type'] : 'percentage',
			'discount_amount' => floatval( $data['discount_amount'] ?? 0 ),
			'trial_days'      => absint( $data['trial_days'] ?? 0 ),
			'setup_fee'       => floatval( $data['setup_fee'] ?? 0 ),
			'status'          => in_array( $data['status'], array( 'active', 'inactive' ), true ) ? $data['status'] : 'active',
			'valid_from'      => ! empty( $data['valid_from'] ) ? sanitize_text_field( $data['valid_from'] ) : null,
			'valid_until'     => ! empty( $data['valid_until'] ) ? sanitize_text_field( $data['valid_until'] ) : null,
			'max_purchases'   => absint( $data['max_purchases'] ?? 0 ),
			'meta_data'       => isset( $data['meta_data'] ) ? wp_json_encode( $data['meta_data'] ) : null,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%f', '%s', '%f', '%d', '%f', '%s', '%s', '%s', '%d', '%s' );

		if ( ! empty( $data['id'] ) ) {
			// Update existing package.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$this->table,
				$db_data,
				array( 'id' => absint( $data['id'] ) ),
				$formats,
				array( '%d' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Failed to update package.', 'bkx-bulk-recurring-payments' ) );
			}

			$package_id = absint( $data['id'] );

			/**
			 * Fires after a package is updated.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $package_id Package ID.
			 * @param array $data       Package data.
			 */
			do_action( 'bkx_package_updated', $package_id, $data );
		} else {
			// Insert new package.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert( $this->table, $db_data, $formats );

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Failed to create package.', 'bkx-bulk-recurring-payments' ) );
			}

			$package_id = $wpdb->insert_id;

			/**
			 * Fires after a package is created.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $package_id Package ID.
			 * @param array $data       Package data.
			 */
			do_action( 'bkx_package_created', $package_id, $data );
		}

		return $this->get( $package_id );
	}

	/**
	 * Delete a package.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Package ID.
	 * @return bool|\WP_Error
	 */
	public function delete( $id ) {
		global $wpdb;

		// Check if package has active subscriptions or purchases.
		$has_active = $this->has_active_purchases( $id );

		if ( $has_active ) {
			return new \WP_Error(
				'has_active',
				__( 'Cannot delete package with active subscriptions or purchases. Deactivate it instead.', 'bkx-bulk-recurring-payments' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to delete package.', 'bkx-bulk-recurring-payments' ) );
		}

		/**
		 * Fires after a package is deleted.
		 *
		 * @since 1.0.0
		 *
		 * @param int $id Package ID.
		 */
		do_action( 'bkx_package_deleted', $id );

		return true;
	}

	/**
	 * Check if package has active purchases.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Package ID.
	 * @return bool
	 */
	public function has_active_purchases( $id ) {
		global $wpdb;

		// Check subscriptions.
		$sub_table = $wpdb->prefix . 'bkx_subscriptions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$subs = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE package_id = %d AND status IN ('active', 'paused')",
				$sub_table,
				$id
			)
		);

		if ( $subs > 0 ) {
			return true;
		}

		// Check bulk purchases.
		$bulk_table = $wpdb->prefix . 'bkx_bulk_purchases';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$bulk = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE package_id = %d AND status = 'active'",
				$bulk_table,
				$id
			)
		);

		return $bulk > 0;
	}

	/**
	 * Increment purchase count.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Package ID.
	 * @return bool
	 */
	public function increment_purchase_count( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET purchase_count = purchase_count + 1 WHERE id = %d",
				$this->table,
				$id
			)
		);
	}

	/**
	 * Check if package is available for purchase.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Package ID.
	 * @return bool|\WP_Error
	 */
	public function is_available( $id ) {
		$package = $this->get( $id );

		if ( ! $package ) {
			return new \WP_Error( 'not_found', __( 'Package not found.', 'bkx-bulk-recurring-payments' ) );
		}

		if ( 'active' !== $package->status ) {
			return new \WP_Error( 'inactive', __( 'This package is not currently available.', 'bkx-bulk-recurring-payments' ) );
		}

		$now = current_time( 'Y-m-d' );

		if ( ! empty( $package->valid_from ) && $now < $package->valid_from ) {
			return new \WP_Error( 'not_started', __( 'This package is not yet available.', 'bkx-bulk-recurring-payments' ) );
		}

		if ( ! empty( $package->valid_until ) && $now > $package->valid_until ) {
			return new \WP_Error( 'expired', __( 'This package has expired.', 'bkx-bulk-recurring-payments' ) );
		}

		if ( $package->max_purchases > 0 && $package->purchase_count >= $package->max_purchases ) {
			return new \WP_Error( 'sold_out', __( 'This package is sold out.', 'bkx-bulk-recurring-payments' ) );
		}

		return true;
	}

	/**
	 * Calculate package price with discount.
	 *
	 * @since 1.0.0
	 *
	 * @param object $package Package object.
	 * @param int    $quantity Quantity (for bulk packages).
	 * @return array
	 */
	public function calculate_price( $package, $quantity = 1 ) {
		$base_price = $package->price;

		if ( 'bulk' === $package->package_type && $quantity > 1 ) {
			$base_price *= $quantity;
		}

		$discount = 0;

		if ( $package->discount_amount > 0 ) {
			if ( 'percentage' === $package->discount_type ) {
				$discount = $base_price * ( $package->discount_amount / 100 );
			} else {
				$discount = $package->discount_amount;
			}
		}

		$total = $base_price - $discount;

		if ( 'recurring' === $package->package_type && $package->setup_fee > 0 ) {
			$total += $package->setup_fee;
		}

		return array(
			'base_price' => $base_price,
			'discount'   => $discount,
			'setup_fee'  => $package->setup_fee ?? 0,
			'total'      => max( 0, $total ),
		);
	}

	/**
	 * Hydrate package object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $package Raw package data.
	 * @return object
	 */
	private function hydrate( $package ) {
		$package->service_ids = json_decode( $package->service_ids, true ) ?: array();
		$package->meta_data   = json_decode( $package->meta_data, true ) ?: array();

		// Add computed properties.
		$package->is_available = $this->is_available( $package->id );
		$package->price_info   = $this->calculate_price( $package );

		// Add interval label.
		if ( 'recurring' === $package->package_type ) {
			$package->interval_label = $this->get_interval_label( $package );
		}

		return $package;
	}

	/**
	 * Get interval label.
	 *
	 * @since 1.0.0
	 *
	 * @param object $package Package object.
	 * @return string
	 */
	private function get_interval_label( $package ) {
		$count = $package->interval_count;
		$type  = $package->interval_type;

		$labels = array(
			'day'   => _n( 'day', 'days', $count, 'bkx-bulk-recurring-payments' ),
			'week'  => _n( 'week', 'weeks', $count, 'bkx-bulk-recurring-payments' ),
			'month' => _n( 'month', 'months', $count, 'bkx-bulk-recurring-payments' ),
			'year'  => _n( 'year', 'years', $count, 'bkx-bulk-recurring-payments' ),
		);

		if ( 1 === $count ) {
			return ucfirst( $labels[ $type ] ?? $type );
		}

		return sprintf( '%d %s', $count, $labels[ $type ] ?? $type );
	}
}
