<?php
/**
 * Loyalty Service
 *
 * Handles loyalty points management.
 *
 * @package BookingX\UserProfilesAdvanced\Services
 * @since   1.0.0
 */

namespace BookingX\UserProfilesAdvanced\Services;

use BookingX\UserProfilesAdvanced\UserProfilesAdvancedAddon;
use WP_Error;

/**
 * Loyalty service class.
 *
 * @since 1.0.0
 */
class LoyaltyService {

	/**
	 * Addon instance.
	 *
	 * @var UserProfilesAdvancedAddon
	 */
	protected UserProfilesAdvancedAddon $addon;

	/**
	 * Points table.
	 *
	 * @var string
	 */
	protected string $points_table;

	/**
	 * Balance table.
	 *
	 * @var string
	 */
	protected string $balance_table;

	/**
	 * Constructor.
	 *
	 * @param UserProfilesAdvancedAddon $addon Addon instance.
	 */
	public function __construct( UserProfilesAdvancedAddon $addon ) {
		global $wpdb;

		$this->addon         = $addon;
		$this->points_table  = $wpdb->prefix . 'bkx_loyalty_points';
		$this->balance_table = $wpdb->prefix . 'bkx_loyalty_balance';
	}

	/**
	 * Get user balance.
	 *
	 * @param int $user_id User ID.
	 * @return array Balance info.
	 */
	public function get_balance( int $user_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$balance = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d',
				$this->balance_table,
				$user_id
			)
		);

		if ( ! $balance ) {
			return array(
				'available_points'   => 0,
				'lifetime_earned'    => 0,
				'lifetime_redeemed'  => 0,
				'pending_discount'   => 0,
			);
		}

		return array(
			'available_points'   => (int) $balance->available_points,
			'lifetime_earned'    => (int) $balance->lifetime_earned,
			'lifetime_redeemed'  => (int) $balance->lifetime_redeemed,
			'pending_discount'   => (float) $balance->pending_discount,
		);
	}

	/**
	 * Award points to user.
	 *
	 * @param int         $user_id User ID.
	 * @param int         $points Points to award.
	 * @param string      $type Transaction type.
	 * @param int|null    $reference_id Reference ID (booking, referral, etc.).
	 * @param string|null $description Description.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function award_points( int $user_id, int $points, string $type, ?int $reference_id = null, ?string $description = null ) {
		global $wpdb;

		if ( $points <= 0 ) {
			return new WP_Error( 'invalid_points', __( 'Points must be positive.', 'bkx-user-profiles-advanced' ) );
		}

		// Create transaction.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->points_table,
			array(
				'user_id'      => $user_id,
				'points'       => $points,
				'type'         => $type,
				'reference_id' => $reference_id,
				'description'  => $description ?? $this->get_transaction_description( $type, $points ),
			)
		);

		// Update balance.
		$this->update_balance( $user_id, $points, 0 );

		/**
		 * Fires when points are awarded.
		 *
		 * @param int    $user_id User ID.
		 * @param int    $points Points awarded.
		 * @param string $type Transaction type.
		 */
		do_action( 'bkx_loyalty_points_awarded', $user_id, $points, $type );

		return true;
	}

	/**
	 * Redeem points for discount.
	 *
	 * @param int $user_id User ID.
	 * @param int $points Points to redeem.
	 * @return array|WP_Error Result or error.
	 */
	public function redeem_points( int $user_id, int $points ) {
		global $wpdb;

		$balance    = $this->get_balance( $user_id );
		$min_redeem = $this->addon->get_setting( 'min_points_redeem', 100 );

		if ( $points < $min_redeem ) {
			return new WP_Error(
				'min_points',
				sprintf(
					/* translators: %d: minimum points */
					__( 'Minimum %d points required to redeem.', 'bkx-user-profiles-advanced' ),
					$min_redeem
				)
			);
		}

		if ( $points > $balance['available_points'] ) {
			return new WP_Error( 'insufficient_points', __( 'Insufficient points.', 'bkx-user-profiles-advanced' ) );
		}

		// Calculate discount.
		$rate     = $this->addon->get_setting( 'points_redemption_rate', 100 );
		$value    = $this->addon->get_setting( 'points_redemption_value', 1 );
		$discount = ( $points / $rate ) * $value;

		// Create redemption transaction.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->points_table,
			array(
				'user_id'     => $user_id,
				'points'      => -$points,
				'type'        => 'redemption',
				'description' => sprintf(
					/* translators: 1: points, 2: discount amount */
					__( 'Redeemed %1$d points for $%2$s discount', 'bkx-user-profiles-advanced' ),
					$points,
					number_format( $discount, 2 )
				),
			)
		);

		// Update balance.
		$this->update_balance( $user_id, -$points, $points );

		// Store pending discount.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->balance_table,
			array( 'pending_discount' => $discount ),
			array( 'user_id' => $user_id )
		);

		$new_balance = $this->get_balance( $user_id );

		/**
		 * Fires when points are redeemed.
		 *
		 * @param int   $user_id User ID.
		 * @param int   $points Points redeemed.
		 * @param float $discount Discount amount.
		 */
		do_action( 'bkx_loyalty_points_redeemed', $user_id, $points, $discount );

		return array(
			'discount'    => $discount,
			'new_balance' => $new_balance['available_points'],
		);
	}

	/**
	 * Get pending discount for user.
	 *
	 * @param int $user_id User ID.
	 * @return float Discount amount.
	 */
	public function get_pending_discount( int $user_id ): float {
		$balance = $this->get_balance( $user_id );
		return $balance['pending_discount'];
	}

	/**
	 * Apply discount to booking.
	 *
	 * @param int   $user_id User ID.
	 * @param int   $booking_id Booking ID.
	 * @param float $discount Discount amount.
	 * @return void
	 */
	public function apply_discount( int $user_id, int $booking_id, float $discount ): void {
		global $wpdb;

		// Clear pending discount.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->balance_table,
			array( 'pending_discount' => 0 ),
			array( 'user_id' => $user_id )
		);

		// Store discount on booking.
		update_post_meta( $booking_id, 'loyalty_discount', $discount );
	}

	/**
	 * Update balance.
	 *
	 * @param int $user_id User ID.
	 * @param int $points_change Points change (positive or negative).
	 * @param int $redeemed Points redeemed (for lifetime tracking).
	 * @return void
	 */
	protected function update_balance( int $user_id, int $points_change, int $redeemed = 0 ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT user_id FROM %i WHERE user_id = %d',
				$this->balance_table,
				$user_id
			)
		);

		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET
						available_points = available_points + %d,
						lifetime_earned = lifetime_earned + %d,
						lifetime_redeemed = lifetime_redeemed + %d
					WHERE user_id = %d',
					$this->balance_table,
					$points_change,
					$points_change > 0 ? $points_change : 0,
					$redeemed,
					$user_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$this->balance_table,
				array(
					'user_id'           => $user_id,
					'available_points'  => max( 0, $points_change ),
					'lifetime_earned'   => $points_change > 0 ? $points_change : 0,
					'lifetime_redeemed' => $redeemed,
				)
			);
		}
	}

	/**
	 * Get transaction history.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args Query arguments.
	 * @return array Transactions.
	 */
	public function get_history( int $user_id, array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
			'type'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'user_id = %d' );
		$params = array( $this->points_table, $user_id );

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$params[] = $args['type'];
		}

		// Add limit and offset to params.
		$params[] = $args['limit'];
		$params[] = $args['offset'];

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				...$params
			)
		);
	}

	/**
	 * Delete user points data.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function delete_user_points( int $user_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->points_table, array( 'user_id' => $user_id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->balance_table, array( 'user_id' => $user_id ) );
	}

	/**
	 * Get transaction description.
	 *
	 * @param string $type Transaction type.
	 * @param int    $points Points.
	 * @return string Description.
	 */
	protected function get_transaction_description( string $type, int $points ): string {
		$descriptions = array(
			'booking_completed' => sprintf(
				/* translators: %d: points */
				__( 'Earned %d points for completed booking', 'bkx-user-profiles-advanced' ),
				$points
			),
			'referral'          => sprintf(
				/* translators: %d: points */
				__( 'Earned %d points for referral', 'bkx-user-profiles-advanced' ),
				$points
			),
			'signup_bonus'      => sprintf(
				/* translators: %d: points */
				__( 'Welcome bonus: %d points', 'bkx-user-profiles-advanced' ),
				$points
			),
			'admin_adjustment'  => sprintf(
				/* translators: %d: points */
				__( 'Admin adjustment: %d points', 'bkx-user-profiles-advanced' ),
				$points
			),
		);

		return $descriptions[ $type ] ?? __( 'Points transaction', 'bkx-user-profiles-advanced' );
	}

	/**
	 * Calculate points value in currency.
	 *
	 * @param int $points Points.
	 * @return float Currency value.
	 */
	public function calculate_value( int $points ): float {
		$rate  = $this->addon->get_setting( 'points_redemption_rate', 100 );
		$value = $this->addon->get_setting( 'points_redemption_value', 1 );

		return ( $points / $rate ) * $value;
	}
}
