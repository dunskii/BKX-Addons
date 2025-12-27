<?php
/**
 * Redemption Service
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 */

namespace BookingX\RewardsPoints\Services;

use BookingX\RewardsPoints\RewardsPointsAddon;

/**
 * Class RedemptionService
 *
 * Handles points redemption for discounts.
 *
 * @since 1.0.0
 */
class RedemptionService {

	/**
	 * Addon instance.
	 *
	 * @var RewardsPointsAddon
	 */
	private RewardsPointsAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param RewardsPointsAddon $addon Addon instance.
	 */
	public function __construct( RewardsPointsAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Calculate monetary value of points.
	 *
	 * @since 1.0.0
	 * @param int $points Number of points.
	 * @return float
	 */
	public function calculate_value( int $points ): float {
		$value_per_point = floatval( $this->addon->get_setting( 'redemption_value', 0.01 ) );
		return round( $points * $value_per_point, 2 );
	}

	/**
	 * Redeem points for a booking.
	 *
	 * @since 1.0.0
	 * @param int $user_id    User ID.
	 * @param int $points     Points to redeem.
	 * @param int $booking_id Booking ID.
	 * @return int|\WP_Error Redemption ID or error.
	 */
	public function redeem( int $user_id, int $points, int $booking_id = 0 ) {
		global $wpdb;

		// Validate points.
		$min_redemption = absint( $this->addon->get_setting( 'min_redemption', 100 ) );
		$max_redemption = absint( $this->addon->get_setting( 'max_redemption', 0 ) );

		if ( $points < $min_redemption ) {
			return new \WP_Error(
				'min_redemption',
				sprintf(
					/* translators: Minimum points */
					__( 'Minimum redemption is %d points.', 'bkx-rewards-points' ),
					$min_redemption
				)
			);
		}

		if ( $max_redemption > 0 && $points > $max_redemption ) {
			return new \WP_Error(
				'max_redemption',
				sprintf(
					/* translators: Maximum points */
					__( 'Maximum redemption is %d points.', 'bkx-rewards-points' ),
					$max_redemption
				)
			);
		}

		// Check balance.
		$points_service = $this->addon->get_points_service();
		$balance        = $points_service->get_balance( $user_id );

		if ( $balance < $points ) {
			return new \WP_Error(
				'insufficient_points',
				__( 'Insufficient points balance.', 'bkx-rewards-points' )
			);
		}

		// Calculate discount.
		$discount = $this->calculate_value( $points );

		// Deduct points.
		$transaction_id = $points_service->deduct_points(
			$user_id,
			$points,
			'redemption',
			sprintf(
				/* translators: 1: Discount amount, 2: Booking ID */
				__( 'Redeemed for $%1$.2f discount on booking #%2$d', 'bkx-rewards-points' ),
				$discount,
				$booking_id
			),
			$booking_id
		);

		if ( ! $transaction_id ) {
			return new \WP_Error(
				'deduction_failed',
				__( 'Failed to deduct points.', 'bkx-rewards-points' )
			);
		}

		// Create redemption record.
		$table = $wpdb->prefix . 'bkx_points_redemptions';

		$wpdb->insert(
			$table,
			array(
				'user_id'        => $user_id,
				'transaction_id' => $transaction_id,
				'points_used'    => $points,
				'discount_value' => $discount,
				'booking_id'     => $booking_id ?: null,
				'status'         => 'pending',
			),
			array( '%d', '%d', '%d', '%f', '%d', '%s' )
		);

		$redemption_id = $wpdb->insert_id;

		// Store redemption on booking.
		if ( $booking_id ) {
			update_post_meta( $booking_id, '_bkx_points_redemption_id', $redemption_id );
			update_post_meta( $booking_id, '_bkx_points_discount', $discount );
		}

		/**
		 * Fires after points are redeemed.
		 *
		 * @since 1.0.0
		 * @param int   $redemption_id Redemption ID.
		 * @param int   $user_id       User ID.
		 * @param int   $points        Points redeemed.
		 * @param float $discount      Discount value.
		 */
		do_action( 'bkx_rewards_points_redeemed', $redemption_id, $user_id, $points, $discount );

		$this->addon->log( sprintf( 'User %d redeemed %d points for $%.2f discount', $user_id, $points, $discount ) );

		return $redemption_id;
	}

	/**
	 * Get redemption discount amount.
	 *
	 * @since 1.0.0
	 * @param int $redemption_id Redemption ID.
	 * @return float
	 */
	public function get_redemption_discount( int $redemption_id ): float {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_points_redemptions';

		$discount = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT discount_value FROM %i WHERE id = %d",
				$table,
				$redemption_id
			)
		);

		return floatval( $discount ?? 0 );
	}

	/**
	 * Confirm redemption after booking completion.
	 *
	 * @since 1.0.0
	 * @param int $redemption_id Redemption ID.
	 * @return bool
	 */
	public function confirm_redemption( int $redemption_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_points_redemptions';

		$updated = $wpdb->update(
			$table,
			array( 'status' => 'confirmed' ),
			array( 'id' => $redemption_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $updated !== false;
	}

	/**
	 * Cancel redemption and refund points.
	 *
	 * @since 1.0.0
	 * @param int $redemption_id Redemption ID.
	 * @return bool
	 */
	public function cancel_redemption( int $redemption_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_points_redemptions';

		$redemption = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$table,
				$redemption_id
			),
			ARRAY_A
		);

		if ( ! $redemption || 'cancelled' === $redemption['status'] ) {
			return false;
		}

		// Refund points.
		$points_service = $this->addon->get_points_service();
		$refunded       = $points_service->add_points(
			$redemption['user_id'],
			$redemption['points_used'],
			'refund',
			sprintf(
				/* translators: Redemption ID */
				__( 'Points refunded from cancelled redemption #%d', 'bkx-rewards-points' ),
				$redemption_id
			),
			$redemption_id,
			'redemption'
		);

		if ( ! $refunded ) {
			return false;
		}

		// Update redemption status.
		$wpdb->update(
			$table,
			array( 'status' => 'cancelled' ),
			array( 'id' => $redemption_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Remove from booking if applicable.
		if ( $redemption['booking_id'] ) {
			delete_post_meta( $redemption['booking_id'], '_bkx_points_redemption_id' );
			delete_post_meta( $redemption['booking_id'], '_bkx_points_discount' );
		}

		$this->addon->log( sprintf( 'Cancelled redemption %d, refunded %d points', $redemption_id, $redemption['points_used'] ) );

		return true;
	}

	/**
	 * Get user's redemption history.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $limit   Number of records.
	 * @return array
	 */
	public function get_user_redemptions( int $user_id, int $limit = 20 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_points_redemptions';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				WHERE user_id = %d
				ORDER BY created_at DESC
				LIMIT %d",
				$table,
				$user_id,
				$limit
			),
			ARRAY_A
		);

		return $results ?: array();
	}
}
