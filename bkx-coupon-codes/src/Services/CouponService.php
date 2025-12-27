<?php
/**
 * Coupon Service
 *
 * Handles coupon CRUD operations and validation.
 *
 * @package BookingX\CouponCodes\Services
 * @since   1.0.0
 */

namespace BookingX\CouponCodes\Services;

use BookingX\CouponCodes\CouponCodesAddon;
use WP_Error;

/**
 * Coupon service class.
 *
 * @since 1.0.0
 */
class CouponService {

	/**
	 * Addon instance.
	 *
	 * @var CouponCodesAddon
	 */
	protected CouponCodesAddon $addon;

	/**
	 * Coupons table name.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Usage table name.
	 *
	 * @var string
	 */
	protected string $usage_table;

	/**
	 * Constructor.
	 *
	 * @param CouponCodesAddon $addon Addon instance.
	 */
	public function __construct( CouponCodesAddon $addon ) {
		global $wpdb;

		$this->addon       = $addon;
		$this->table       = $wpdb->prefix . 'bkx_coupons';
		$this->usage_table = $wpdb->prefix . 'bkx_coupon_usage';
	}

	/**
	 * Get all coupons.
	 *
	 * @param array $args Query arguments.
	 * @return array Coupons.
	 */
	public function get_coupons( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'page'     => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( 'active' === $args['status'] ) {
			$where[] = 'is_active = 1';
		} elseif ( 'inactive' === $args['status'] ) {
			$where[] = 'is_active = 0';
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(code LIKE %s OR description LIKE %s)';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_clause = implode( ' AND ', $where );
		$orderby      = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] ) ?: 'created_at DESC';
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];

		$query = "SELECT * FROM %i WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";

		$query_values = array_merge( array( $this->table ), $values, array( $args['per_page'], $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( $wpdb->prepare( $query, $query_values ) );
	}

	/**
	 * Get total coupon count.
	 *
	 * @param array $args Query arguments.
	 * @return int Count.
	 */
	public function get_count( array $args = array() ): int {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();

		if ( isset( $args['status'] ) && 'active' === $args['status'] ) {
			$where[] = 'is_active = 1';
		} elseif ( isset( $args['status'] ) && 'inactive' === $args['status'] ) {
			$where[] = 'is_active = 0';
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(code LIKE %s OR description LIKE %s)';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_clause = implode( ' AND ', $where );

		$query_values = array_merge( array( $this->table ), $values );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where_clause}", $query_values ) );
	}

	/**
	 * Get a single coupon by ID.
	 *
	 * @param int $coupon_id Coupon ID.
	 * @return object|null Coupon object or null.
	 */
	public function get_coupon( int $coupon_id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$this->table,
				$coupon_id
			)
		);
	}

	/**
	 * Get coupon by code.
	 *
	 * @param string $code Coupon code.
	 * @return object|null Coupon object or null.
	 */
	public function get_coupon_by_code( string $code ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE code = %s',
				$this->table,
				strtoupper( $code )
			)
		);
	}

	/**
	 * Create a new coupon.
	 *
	 * @param array $data Coupon data.
	 * @return int|WP_Error Coupon ID or error.
	 */
	public function create_coupon( array $data ) {
		global $wpdb;

		// Validate required fields.
		if ( empty( $data['code'] ) ) {
			return new WP_Error( 'missing_code', __( 'Coupon code is required.', 'bkx-coupon-codes' ) );
		}

		// Check for duplicate code.
		$existing = $this->get_coupon_by_code( $data['code'] );
		if ( $existing ) {
			return new WP_Error( 'duplicate_code', __( 'A coupon with this code already exists.', 'bkx-coupon-codes' ) );
		}

		// Prepare data.
		$insert_data = array(
			'code'               => strtoupper( $data['code'] ),
			'description'        => $data['description'] ?? '',
			'discount_type'      => $data['discount_type'] ?? 'percentage',
			'discount_value'     => floatval( $data['discount_value'] ?? 0 ),
			'min_booking_amount' => floatval( $data['min_booking_amount'] ?? 0 ),
			'max_discount'       => floatval( $data['max_discount'] ?? 0 ),
			'usage_limit'        => absint( $data['usage_limit'] ?? 0 ),
			'per_user_limit'     => absint( $data['per_user_limit'] ?? 0 ),
			'start_date'         => ! empty( $data['start_date'] ) ? $data['start_date'] : null,
			'end_date'           => ! empty( $data['end_date'] ) ? $data['end_date'] : null,
			'is_active'          => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
			'services'           => ! empty( $data['services'] ) ? wp_json_encode( $data['services'] ) : null,
			'seats'              => ! empty( $data['seats'] ) ? wp_json_encode( $data['seats'] ) : null,
			'excluded_services'  => ! empty( $data['excluded_services'] ) ? wp_json_encode( $data['excluded_services'] ) : null,
			'user_roles'         => ! empty( $data['user_roles'] ) ? wp_json_encode( $data['user_roles'] ) : null,
			'first_booking_only' => isset( $data['first_booking_only'] ) ? (int) $data['first_booking_only'] : 0,
			'created_by'         => get_current_user_id(),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $this->table, $insert_data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create coupon.', 'bkx-coupon-codes' ) );
		}

		$coupon_id = $wpdb->insert_id;

		/**
		 * Fires after a coupon is created.
		 *
		 * @since 1.0.0
		 * @param int   $coupon_id Coupon ID.
		 * @param array $data      Coupon data.
		 */
		do_action( 'bkx_coupon_created', $coupon_id, $data );

		return $coupon_id;
	}

	/**
	 * Update a coupon.
	 *
	 * @param int   $coupon_id Coupon ID.
	 * @param array $data Coupon data.
	 * @return bool|WP_Error True on success or error.
	 */
	public function update_coupon( int $coupon_id, array $data ) {
		global $wpdb;

		$coupon = $this->get_coupon( $coupon_id );

		if ( ! $coupon ) {
			return new WP_Error( 'not_found', __( 'Coupon not found.', 'bkx-coupon-codes' ) );
		}

		// Check for duplicate code (if code changed).
		if ( ! empty( $data['code'] ) && strtoupper( $data['code'] ) !== $coupon->code ) {
			$existing = $this->get_coupon_by_code( $data['code'] );
			if ( $existing ) {
				return new WP_Error( 'duplicate_code', __( 'A coupon with this code already exists.', 'bkx-coupon-codes' ) );
			}
		}

		// Prepare update data.
		$update_data = array();

		if ( isset( $data['code'] ) ) {
			$update_data['code'] = strtoupper( $data['code'] );
		}
		if ( isset( $data['description'] ) ) {
			$update_data['description'] = $data['description'];
		}
		if ( isset( $data['discount_type'] ) ) {
			$update_data['discount_type'] = $data['discount_type'];
		}
		if ( isset( $data['discount_value'] ) ) {
			$update_data['discount_value'] = floatval( $data['discount_value'] );
		}
		if ( isset( $data['min_booking_amount'] ) ) {
			$update_data['min_booking_amount'] = floatval( $data['min_booking_amount'] );
		}
		if ( isset( $data['max_discount'] ) ) {
			$update_data['max_discount'] = floatval( $data['max_discount'] );
		}
		if ( isset( $data['usage_limit'] ) ) {
			$update_data['usage_limit'] = absint( $data['usage_limit'] );
		}
		if ( isset( $data['per_user_limit'] ) ) {
			$update_data['per_user_limit'] = absint( $data['per_user_limit'] );
		}
		if ( array_key_exists( 'start_date', $data ) ) {
			$update_data['start_date'] = ! empty( $data['start_date'] ) ? $data['start_date'] : null;
		}
		if ( array_key_exists( 'end_date', $data ) ) {
			$update_data['end_date'] = ! empty( $data['end_date'] ) ? $data['end_date'] : null;
		}
		if ( isset( $data['is_active'] ) ) {
			$update_data['is_active'] = (int) $data['is_active'];
		}
		if ( array_key_exists( 'services', $data ) ) {
			$update_data['services'] = ! empty( $data['services'] ) ? wp_json_encode( $data['services'] ) : null;
		}
		if ( array_key_exists( 'seats', $data ) ) {
			$update_data['seats'] = ! empty( $data['seats'] ) ? wp_json_encode( $data['seats'] ) : null;
		}
		if ( array_key_exists( 'excluded_services', $data ) ) {
			$update_data['excluded_services'] = ! empty( $data['excluded_services'] ) ? wp_json_encode( $data['excluded_services'] ) : null;
		}
		if ( array_key_exists( 'user_roles', $data ) ) {
			$update_data['user_roles'] = ! empty( $data['user_roles'] ) ? wp_json_encode( $data['user_roles'] ) : null;
		}
		if ( isset( $data['first_booking_only'] ) ) {
			$update_data['first_booking_only'] = (int) $data['first_booking_only'];
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $coupon_id )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to update coupon.', 'bkx-coupon-codes' ) );
		}

		/**
		 * Fires after a coupon is updated.
		 *
		 * @since 1.0.0
		 * @param int   $coupon_id Coupon ID.
		 * @param array $data      Updated data.
		 */
		do_action( 'bkx_coupon_updated', $coupon_id, $data );

		return true;
	}

	/**
	 * Delete a coupon.
	 *
	 * @param int $coupon_id Coupon ID.
	 * @return bool|WP_Error True on success or error.
	 */
	public function delete_coupon( int $coupon_id ) {
		global $wpdb;

		$coupon = $this->get_coupon( $coupon_id );

		if ( ! $coupon ) {
			return new WP_Error( 'not_found', __( 'Coupon not found.', 'bkx-coupon-codes' ) );
		}

		// Delete usage records first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->usage_table, array( 'coupon_id' => $coupon_id ) );

		// Delete coupon.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $this->table, array( 'id' => $coupon_id ) );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to delete coupon.', 'bkx-coupon-codes' ) );
		}

		/**
		 * Fires after a coupon is deleted.
		 *
		 * @since 1.0.0
		 * @param int    $coupon_id Coupon ID.
		 * @param object $coupon    Coupon object.
		 */
		do_action( 'bkx_coupon_deleted', $coupon_id, $coupon );

		return true;
	}

	/**
	 * Toggle coupon active status.
	 *
	 * @param int $coupon_id Coupon ID.
	 * @return bool|WP_Error New status or error.
	 */
	public function toggle_active( int $coupon_id ) {
		global $wpdb;

		$coupon = $this->get_coupon( $coupon_id );

		if ( ! $coupon ) {
			return new WP_Error( 'not_found', __( 'Coupon not found.', 'bkx-coupon-codes' ) );
		}

		$new_status = $coupon->is_active ? 0 : 1;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array( 'is_active' => $new_status ),
			array( 'id' => $coupon_id )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to update coupon status.', 'bkx-coupon-codes' ) );
		}

		return (bool) $new_status;
	}

	/**
	 * Validate a coupon code.
	 *
	 * @param string $code Coupon code.
	 * @param array  $booking_data Optional booking data for context.
	 * @return array|WP_Error Coupon data or error.
	 */
	public function validate_coupon( string $code, array $booking_data = array() ) {
		$coupon = $this->get_coupon_by_code( $code );

		if ( ! $coupon ) {
			return new WP_Error( 'invalid_code', __( 'Invalid coupon code.', 'bkx-coupon-codes' ) );
		}

		// Check if active.
		if ( ! $coupon->is_active ) {
			return new WP_Error( 'inactive', __( 'This coupon is not active.', 'bkx-coupon-codes' ) );
		}

		// Check date range.
		$now = current_time( 'mysql' );

		if ( ! empty( $coupon->start_date ) && $now < $coupon->start_date ) {
			return new WP_Error( 'not_started', __( 'This coupon is not yet valid.', 'bkx-coupon-codes' ) );
		}

		if ( ! empty( $coupon->end_date ) && $now > $coupon->end_date ) {
			return new WP_Error( 'expired', __( 'This coupon has expired.', 'bkx-coupon-codes' ) );
		}

		// Check usage limit.
		if ( $coupon->usage_limit > 0 && $coupon->usage_count >= $coupon->usage_limit ) {
			return new WP_Error( 'usage_limit', __( 'This coupon has reached its usage limit.', 'bkx-coupon-codes' ) );
		}

		// Check per-user limit.
		if ( $coupon->per_user_limit > 0 && is_user_logged_in() ) {
			$user_usage = $this->get_user_usage_count( $coupon->id, get_current_user_id() );
			if ( $user_usage >= $coupon->per_user_limit ) {
				return new WP_Error( 'per_user_limit', __( 'You have already used this coupon the maximum number of times.', 'bkx-coupon-codes' ) );
			}
		}

		// Check login requirement.
		if ( $this->addon->get_setting( 'require_login', false ) && ! is_user_logged_in() ) {
			return new WP_Error( 'login_required', __( 'You must be logged in to use coupons.', 'bkx-coupon-codes' ) );
		}

		// Check minimum booking amount.
		if ( ! empty( $booking_data['total'] ) && $coupon->min_booking_amount > 0 ) {
			if ( $booking_data['total'] < $coupon->min_booking_amount ) {
				return new WP_Error(
					'min_amount',
					sprintf(
						/* translators: %s: minimum amount */
						__( 'Minimum booking amount of %s required.', 'bkx-coupon-codes' ),
						wc_price( $coupon->min_booking_amount )
					)
				);
			}
		}

		// Check service restrictions.
		if ( ! empty( $coupon->services ) && ! empty( $booking_data['service_id'] ) ) {
			$allowed_services = json_decode( $coupon->services, true );
			if ( ! in_array( $booking_data['service_id'], $allowed_services, true ) ) {
				return new WP_Error( 'service_not_allowed', __( 'This coupon is not valid for the selected service.', 'bkx-coupon-codes' ) );
			}
		}

		// Check excluded services.
		if ( ! empty( $coupon->excluded_services ) && ! empty( $booking_data['service_id'] ) ) {
			$excluded = json_decode( $coupon->excluded_services, true );
			if ( in_array( $booking_data['service_id'], $excluded, true ) ) {
				return new WP_Error( 'service_excluded', __( 'This coupon is not valid for the selected service.', 'bkx-coupon-codes' ) );
			}
		}

		// Check seat restrictions.
		if ( ! empty( $coupon->seats ) && ! empty( $booking_data['seat_id'] ) ) {
			$allowed_seats = json_decode( $coupon->seats, true );
			if ( ! in_array( $booking_data['seat_id'], $allowed_seats, true ) ) {
				return new WP_Error( 'seat_not_allowed', __( 'This coupon is not valid for the selected resource.', 'bkx-coupon-codes' ) );
			}
		}

		// Check user role restrictions.
		if ( ! empty( $coupon->user_roles ) && is_user_logged_in() ) {
			$allowed_roles = json_decode( $coupon->user_roles, true );
			$user          = wp_get_current_user();
			$has_role      = false;

			foreach ( $allowed_roles as $role ) {
				if ( in_array( $role, $user->roles, true ) ) {
					$has_role = true;
					break;
				}
			}

			if ( ! $has_role ) {
				return new WP_Error( 'role_not_allowed', __( 'This coupon is not available for your account type.', 'bkx-coupon-codes' ) );
			}
		}

		// Check first booking only.
		if ( $coupon->first_booking_only && is_user_logged_in() ) {
			$has_bookings = $this->user_has_bookings( get_current_user_id() );
			if ( $has_bookings ) {
				return new WP_Error( 'first_booking_only', __( 'This coupon is only valid for your first booking.', 'bkx-coupon-codes' ) );
			}
		}

		/**
		 * Filter coupon validation result.
		 *
		 * @since 1.0.0
		 * @param bool|WP_Error $valid        True if valid, WP_Error if not.
		 * @param object        $coupon       Coupon object.
		 * @param array         $booking_data Booking data.
		 */
		$validation = apply_filters( 'bkx_validate_coupon', true, $coupon, $booking_data );

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Return coupon data as array.
		return array(
			'id'             => $coupon->id,
			'code'           => $coupon->code,
			'discount_type'  => $coupon->discount_type,
			'discount_value' => floatval( $coupon->discount_value ),
			'max_discount'   => floatval( $coupon->max_discount ),
		);
	}

	/**
	 * Record coupon usage.
	 *
	 * @param int   $coupon_id Coupon ID.
	 * @param int   $booking_id Booking ID.
	 * @param int   $user_id User ID.
	 * @param float $discount_amount Discount amount.
	 * @return bool True on success.
	 */
	public function record_usage( int $coupon_id, int $booking_id, int $user_id, float $discount_amount ): bool {
		global $wpdb;

		// Insert usage record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->usage_table,
			array(
				'coupon_id'       => $coupon_id,
				'booking_id'      => $booking_id,
				'user_id'         => $user_id ?: null,
				'discount_amount' => $discount_amount,
			)
		);

		if ( false === $result ) {
			return false;
		}

		// Increment usage count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET usage_count = usage_count + 1 WHERE id = %d',
				$this->table,
				$coupon_id
			)
		);

		/**
		 * Fires after coupon usage is recorded.
		 *
		 * @since 1.0.0
		 * @param int   $coupon_id       Coupon ID.
		 * @param int   $booking_id      Booking ID.
		 * @param float $discount_amount Discount amount.
		 */
		do_action( 'bkx_coupon_usage_recorded', $coupon_id, $booking_id, $discount_amount );

		return true;
	}

	/**
	 * Release coupon usage (for cancelled bookings).
	 *
	 * @param int $coupon_id Coupon ID.
	 * @param int $booking_id Booking ID.
	 * @return bool True on success.
	 */
	public function release_usage( int $coupon_id, int $booking_id ): bool {
		global $wpdb;

		// Mark usage as released.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->usage_table,
			array( 'status' => 'released' ),
			array(
				'coupon_id'  => $coupon_id,
				'booking_id' => $booking_id,
				'status'     => 'active',
			)
		);

		if ( false === $result ) {
			return false;
		}

		// Decrement usage count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET usage_count = GREATEST(0, usage_count - 1) WHERE id = %d',
				$this->table,
				$coupon_id
			)
		);

		return true;
	}

	/**
	 * Get user usage count for a coupon.
	 *
	 * @param int $coupon_id Coupon ID.
	 * @param int $user_id User ID.
	 * @return int Usage count.
	 */
	public function get_user_usage_count( int $coupon_id, int $user_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE coupon_id = %d AND user_id = %d AND status = %s',
				$this->usage_table,
				$coupon_id,
				$user_id,
				'active'
			)
		);
	}

	/**
	 * Check if user has any bookings.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if user has bookings.
	 */
	protected function user_has_bookings( int $user_id ): bool {
		$bookings = get_posts(
			array(
				'post_type'   => 'bkx_booking',
				'author'      => $user_id,
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);

		return ! empty( $bookings );
	}

	/**
	 * Generate a unique coupon code.
	 *
	 * @param int $length Code length.
	 * @return string Unique code.
	 */
	public function generate_unique_code( int $length = 8 ): string {
		$characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$code       = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$code .= $characters[ wp_rand( 0, strlen( $characters ) - 1 ) ];
		}

		// Check if code exists.
		$existing = $this->get_coupon_by_code( $code );

		if ( $existing ) {
			return $this->generate_unique_code( $length );
		}

		return $code;
	}

	/**
	 * Get coupon usage statistics.
	 *
	 * @param int $coupon_id Coupon ID.
	 * @return array Statistics.
	 */
	public function get_usage_stats( int $coupon_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COUNT(*) as total_uses, SUM(discount_amount) as total_discount, COUNT(DISTINCT user_id) as unique_users FROM %i WHERE coupon_id = %d AND status = %s',
				$this->usage_table,
				$coupon_id,
				'active'
			),
			ARRAY_A
		);

		return array(
			'total_uses'     => (int) ( $stats['total_uses'] ?? 0 ),
			'total_discount' => floatval( $stats['total_discount'] ?? 0 ),
			'unique_users'   => (int) ( $stats['unique_users'] ?? 0 ),
		);
	}
}
