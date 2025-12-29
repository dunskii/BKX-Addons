<?php
/**
 * Subscription Service.
 *
 * @package BookingX\PushNotifications
 */

namespace BookingX\PushNotifications\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SubscriptionService class.
 */
class SubscriptionService {

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'bkx_push_subscriptions';
	}

	/**
	 * Subscribe a user.
	 *
	 * @param array $data Subscription data.
	 * @return int|false Subscription ID or false.
	 */
	public function subscribe( $data ) {
		global $wpdb;

		// Check if already exists.
		$existing = $wpdb->get_var( // phpcs:ignore
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE endpoint = %s",
				$data['endpoint']
			)
		);

		$device_type = $this->detect_device_type( $data['user_agent'] ?? '' );

		if ( $existing ) {
			// Update existing subscription.
			$wpdb->update( // phpcs:ignore
				$this->table,
				array(
					'p256dh'      => $data['p256dh'],
					'auth'        => $data['auth'],
					'user_id'     => $data['user_id'] ?: null,
					'user_agent'  => $data['user_agent'] ?? null,
					'device_type' => $device_type,
					'is_active'   => 1,
					'last_used'   => current_time( 'mysql' ),
				),
				array( 'id' => $existing )
			);

			return $existing;
		}

		// Insert new subscription.
		$result = $wpdb->insert( // phpcs:ignore
			$this->table,
			array(
				'endpoint'    => $data['endpoint'],
				'p256dh'      => $data['p256dh'],
				'auth'        => $data['auth'],
				'user_id'     => $data['user_id'] ?: null,
				'user_agent'  => $data['user_agent'] ?? null,
				'device_type' => $device_type,
				'is_active'   => 1,
				'created_at'  => current_time( 'mysql' ),
			)
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Unsubscribe by endpoint.
	 *
	 * @param string $endpoint Endpoint URL.
	 * @return bool
	 */
	public function unsubscribe( $endpoint ) {
		global $wpdb;

		return (bool) $wpdb->update( // phpcs:ignore
			$this->table,
			array( 'is_active' => 0 ),
			array( 'endpoint' => $endpoint )
		);
	}

	/**
	 * Get subscription by ID.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return object|null
	 */
	public function get_subscription( $subscription_id ) {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$subscription_id
			)
		);
	}

	/**
	 * Get subscriptions by user ID.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_subscriptions( $user_id ) {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE user_id = %d AND is_active = 1",
				$user_id
			)
		);
	}

	/**
	 * Get all active subscriptions.
	 *
	 * @return array
	 */
	public function get_all_active() {
		global $wpdb;

		return $wpdb->get_results( "SELECT * FROM {$this->table} WHERE is_active = 1" ); // phpcs:ignore
	}

	/**
	 * Get subscriptions for staff.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	public function get_staff_subscriptions( $booking_id ) {
		global $wpdb;

		// Get staff ID from booking.
		$staff_id = get_post_meta( $booking_id, 'seat_id', true );

		if ( ! $staff_id ) {
			// If no specific staff, get all admin subscriptions.
			return $wpdb->get_results( // phpcs:ignore
				"SELECT s.* FROM {$this->table} s
				INNER JOIN {$wpdb->users} u ON s.user_id = u.ID
				INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
				WHERE s.is_active = 1
				AND um.meta_key = '{$wpdb->prefix}capabilities'
				AND um.meta_value LIKE '%administrator%'"
			);
		}

		// Get the user linked to this staff member.
		$staff_user_id = get_post_meta( $staff_id, 'linked_user_id', true );

		if ( ! $staff_user_id ) {
			return array();
		}

		return $this->get_user_subscriptions( $staff_user_id );
	}

	/**
	 * Get customer subscription for booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	public function get_customer_subscriptions( $booking_id ) {
		// Get customer email from booking.
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );

		if ( ! $customer_email ) {
			return array();
		}

		// Find user by email.
		$user = get_user_by( 'email', $customer_email );

		if ( ! $user ) {
			return array();
		}

		return $this->get_user_subscriptions( $user->ID );
	}

	/**
	 * Mark subscription as invalid.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function mark_invalid( $subscription_id ) {
		global $wpdb;

		$wpdb->update( // phpcs:ignore
			$this->table,
			array( 'is_active' => 0 ),
			array( 'id' => $subscription_id )
		);
	}

	/**
	 * Update last used timestamp.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function update_last_used( $subscription_id ) {
		global $wpdb;

		$wpdb->update( // phpcs:ignore
			$this->table,
			array( 'last_used' => current_time( 'mysql' ) ),
			array( 'id' => $subscription_id )
		);
	}

	/**
	 * Detect device type from user agent.
	 *
	 * @param string $user_agent User agent string.
	 * @return string
	 */
	private function detect_device_type( $user_agent ) {
		$user_agent = strtolower( $user_agent );

		if ( preg_match( '/mobile|android|iphone|ipod|blackberry|opera mini|opera mobi/i', $user_agent ) ) {
			return 'mobile';
		}

		if ( preg_match( '/tablet|ipad|kindle|playbook/i', $user_agent ) ) {
			return 'tablet';
		}

		return 'desktop';
	}

	/**
	 * Cleanup old inactive subscriptions.
	 */
	public function cleanup_old_subscriptions() {
		global $wpdb;

		// Remove subscriptions not used in 90 days.
		$wpdb->query( // phpcs:ignore
			"DELETE FROM {$this->table}
			WHERE is_active = 0
			OR (last_used IS NOT NULL AND last_used < DATE_SUB(NOW(), INTERVAL 90 DAY))"
		);
	}

	/**
	 * Get subscription stats.
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;

		return array(
			'total'   => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ), // phpcs:ignore
			'active'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE is_active = 1" ), // phpcs:ignore
			'desktop' => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE is_active = 1 AND device_type = 'desktop'" ), // phpcs:ignore
			'mobile'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE is_active = 1 AND device_type = 'mobile'" ), // phpcs:ignore
			'tablet'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE is_active = 1 AND device_type = 'tablet'" ), // phpcs:ignore
		);
	}
}
