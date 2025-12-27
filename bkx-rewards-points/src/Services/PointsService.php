<?php
/**
 * Points Service
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 */

namespace BookingX\RewardsPoints\Services;

use BookingX\RewardsPoints\RewardsPointsAddon;

/**
 * Class PointsService
 *
 * Handles points balance and transactions.
 *
 * @since 1.0.0
 */
class PointsService {

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
	 * Get user's current points balance.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_balance( int $user_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_points_balance';

		$balance = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT balance FROM %i WHERE user_id = %d",
				$table,
				$user_id
			)
		);

		return absint( $balance ?? 0 );
	}

	/**
	 * Add points to user's balance.
	 *
	 * @since 1.0.0
	 * @param int         $user_id       User ID.
	 * @param int         $points        Points to add.
	 * @param string      $type          Transaction type.
	 * @param string      $description   Description.
	 * @param int|null    $reference_id  Reference ID (e.g., booking ID).
	 * @param string|null $reference_type Reference type.
	 * @return bool
	 */
	public function add_points(
		int $user_id,
		int $points,
		string $type = 'manual',
		string $description = '',
		?int $reference_id = null,
		?string $reference_type = null
	): bool {
		global $wpdb;

		if ( $points <= 0 ) {
			return false;
		}

		$table_balance      = $wpdb->prefix . 'bkx_points_balance';
		$table_transactions = $wpdb->prefix . 'bkx_points_transactions';

		// Calculate expiration if enabled.
		$expires_at = null;
		if ( $this->addon->get_setting( 'enable_expiration', false ) ) {
			$days       = absint( $this->addon->get_setting( 'expiration_days', 365 ) );
			$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );
		}

		// Start transaction.
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Insert transaction.
			$inserted = $wpdb->insert(
				$table_transactions,
				array(
					'user_id'        => $user_id,
					'points'         => $points,
					'type'           => $type,
					'description'    => $description,
					'reference_type' => $reference_type ?? $type,
					'reference_id'   => $reference_id,
					'expires_at'     => $expires_at,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
			);

			if ( ! $inserted ) {
				throw new \Exception( 'Failed to insert transaction' );
			}

			// Update balance.
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO %i (user_id, balance, lifetime_earned, last_activity)
					VALUES (%d, %d, %d, NOW())
					ON DUPLICATE KEY UPDATE
						balance = balance + %d,
						lifetime_earned = lifetime_earned + %d,
						last_activity = NOW()",
					$table_balance,
					$user_id,
					$points,
					$points,
					$points,
					$points
				)
			);

			$wpdb->query( 'COMMIT' );

			/**
			 * Fires after points are added.
			 *
			 * @since 1.0.0
			 * @param int    $user_id User ID.
			 * @param int    $points  Points added.
			 * @param string $type    Transaction type.
			 */
			do_action( 'bkx_rewards_points_added', $user_id, $points, $type );

			return true;
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			$this->addon->log( sprintf( 'Failed to add points: %s', $e->getMessage() ), 'error' );
			return false;
		}
	}

	/**
	 * Deduct points from user's balance.
	 *
	 * @since 1.0.0
	 * @param int    $user_id     User ID.
	 * @param int    $points      Points to deduct.
	 * @param string $type        Transaction type.
	 * @param string $description Description.
	 * @param int    $reference_id Reference ID.
	 * @return int|false Transaction ID or false on failure.
	 */
	public function deduct_points(
		int $user_id,
		int $points,
		string $type = 'redemption',
		string $description = '',
		int $reference_id = 0
	) {
		global $wpdb;

		if ( $points <= 0 ) {
			return false;
		}

		$balance = $this->get_balance( $user_id );

		if ( $balance < $points ) {
			return false;
		}

		$table_balance      = $wpdb->prefix . 'bkx_points_balance';
		$table_transactions = $wpdb->prefix . 'bkx_points_transactions';

		// Start transaction.
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Insert transaction (negative points).
			$inserted = $wpdb->insert(
				$table_transactions,
				array(
					'user_id'        => $user_id,
					'points'         => -$points, // Negative for deduction.
					'type'           => $type,
					'description'    => $description,
					'reference_type' => $type,
					'reference_id'   => $reference_id ?: null,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d' )
			);

			if ( ! $inserted ) {
				throw new \Exception( 'Failed to insert transaction' );
			}

			$transaction_id = $wpdb->insert_id;

			// Update balance.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i
					SET balance = balance - %d,
						lifetime_redeemed = lifetime_redeemed + %d,
						last_activity = NOW()
					WHERE user_id = %d",
					$table_balance,
					$points,
					$points,
					$user_id
				)
			);

			$wpdb->query( 'COMMIT' );

			/**
			 * Fires after points are deducted.
			 *
			 * @since 1.0.0
			 * @param int    $user_id User ID.
			 * @param int    $points  Points deducted.
			 * @param string $type    Transaction type.
			 */
			do_action( 'bkx_rewards_points_deducted', $user_id, $points, $type );

			return $transaction_id;
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			$this->addon->log( sprintf( 'Failed to deduct points: %s', $e->getMessage() ), 'error' );
			return false;
		}
	}

	/**
	 * Get user's points transaction history.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $limit   Number of records to return.
	 * @param int $offset  Offset for pagination.
	 * @return array
	 */
	public function get_history( int $user_id, int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_points_transactions';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				WHERE user_id = %d
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$table,
				$user_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Expire old points.
	 *
	 * @since 1.0.0
	 * @return int Number of expired transactions.
	 */
	public function expire_points(): int {
		global $wpdb;

		$table_transactions = $wpdb->prefix . 'bkx_points_transactions';
		$table_balance      = $wpdb->prefix . 'bkx_points_balance';

		// Find expiring transactions.
		$expiring = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, SUM(points) as total_points
				FROM %i
				WHERE expires_at IS NOT NULL
				AND expires_at < NOW()
				AND points > 0
				AND type != 'expired'
				GROUP BY user_id",
				$table_transactions
			),
			ARRAY_A
		);

		if ( empty( $expiring ) ) {
			return 0;
		}

		$count = 0;

		foreach ( $expiring as $row ) {
			$user_id = absint( $row['user_id'] );
			$points  = absint( $row['total_points'] );

			// Only expire if user still has enough balance.
			$balance = $this->get_balance( $user_id );
			$to_expire = min( $points, $balance );

			if ( $to_expire <= 0 ) {
				continue;
			}

			// Deduct expired points.
			$this->deduct_points(
				$user_id,
				$to_expire,
				'expired',
				__( 'Points expired', 'bkx-rewards-points' )
			);

			++$count;
		}

		// Mark transactions as processed.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET type = CONCAT(type, '_expired')
				WHERE expires_at IS NOT NULL
				AND expires_at < NOW()
				AND points > 0
				AND type NOT LIKE '%%expired%%'",
				$table_transactions
			)
		);

		$this->addon->log( sprintf( 'Expired points for %d users', $count ) );

		return $count;
	}

	/**
	 * Get user statistics.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_stats( int $user_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_points_balance';

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT balance, lifetime_earned, lifetime_redeemed, last_activity
				FROM %i
				WHERE user_id = %d",
				$table,
				$user_id
			),
			ARRAY_A
		);

		return $stats ?: array(
			'balance'          => 0,
			'lifetime_earned'  => 0,
			'lifetime_redeemed' => 0,
			'last_activity'    => null,
		);
	}
}
