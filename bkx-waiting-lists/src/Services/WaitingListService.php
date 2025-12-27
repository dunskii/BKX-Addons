<?php
/**
 * Waiting List Service
 *
 * @package BookingX\WaitingLists
 * @since   1.0.0
 */

namespace BookingX\WaitingLists\Services;

use BookingX\WaitingLists\WaitingListsAddon;

/**
 * Class WaitingListService
 *
 * Handles waiting list operations.
 *
 * @since 1.0.0
 */
class WaitingListService {

	/**
	 * Addon instance.
	 *
	 * @var WaitingListsAddon
	 */
	private WaitingListsAddon $addon;

	/**
	 * Notification service.
	 *
	 * @var NotificationService
	 */
	private NotificationService $notifications;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param WaitingListsAddon   $addon         Addon instance.
	 * @param NotificationService $notifications Notification service.
	 */
	public function __construct( WaitingListsAddon $addon, NotificationService $notifications ) {
		global $wpdb;
		$this->addon         = $addon;
		$this->notifications = $notifications;
		$this->table         = $wpdb->prefix . 'bkx_waiting_list';
	}

	/**
	 * Add someone to the waiting list.
	 *
	 * @since 1.0.0
	 * @param array $data Entry data.
	 * @return int|\WP_Error Entry ID or error.
	 */
	public function add_to_waitlist( array $data ) {
		global $wpdb;

		// Validate.
		if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
			return new \WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'bkx-waiting-lists' ) );
		}

		if ( empty( $data['date'] ) || empty( $data['time'] ) ) {
			return new \WP_Error( 'missing_datetime', __( 'Date and time are required.', 'bkx-waiting-lists' ) );
		}

		// Check if already on waitlist.
		$existing = $this->find_entry(
			$data['seat_id'] ?? 0,
			$data['service_id'] ?? 0,
			$data['date'],
			$data['time'],
			$data['email']
		);

		if ( $existing ) {
			return new \WP_Error( 'already_on_waitlist', __( 'You are already on the waiting list for this slot.', 'bkx-waiting-lists' ) );
		}

		// Check max waitlist size.
		$max_size     = $this->addon->get_setting( 'max_waitlist_size', 10 );
		$current_size = $this->get_waitlist_count( $data['seat_id'] ?? 0, $data['service_id'] ?? 0, $data['date'], $data['time'] );

		if ( $current_size >= $max_size ) {
			return new \WP_Error( 'waitlist_full', __( 'The waiting list for this slot is full.', 'bkx-waiting-lists' ) );
		}

		// Generate token.
		$token = wp_generate_password( 32, false );

		// Get next position.
		$position = $current_size + 1;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'seat_id'        => absint( $data['seat_id'] ?? 0 ),
				'service_id'     => absint( $data['service_id'] ?? 0 ),
				'booking_date'   => sanitize_text_field( $data['date'] ),
				'booking_time'   => sanitize_text_field( $data['time'] ),
				'user_id'        => absint( $data['user_id'] ?? 0 ),
				'customer_name'  => sanitize_text_field( $data['name'] ?? '' ),
				'customer_email' => sanitize_email( $data['email'] ),
				'customer_phone' => sanitize_text_field( $data['phone'] ?? '' ),
				'position'       => $position,
				'status'         => 'waiting',
				'token'          => $token,
				'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to join waiting list.', 'bkx-waiting-lists' ) );
		}

		$entry_id = $wpdb->insert_id;

		// Send confirmation email.
		$this->notifications->send_joined_notification( $entry_id );

		/**
		 * Fires when someone joins the waiting list.
		 *
		 * @since 1.0.0
		 * @param int   $entry_id Entry ID.
		 * @param array $data     Entry data.
		 */
		do_action( 'bkx_waitlist_joined', $entry_id, $data );

		return $entry_id;
	}

	/**
	 * Remove from waiting list.
	 *
	 * @since 1.0.0
	 * @param int    $entry_id Entry ID.
	 * @param string $token    Security token.
	 * @return true|\WP_Error
	 */
	public function remove_from_waitlist( int $entry_id, string $token ) {
		$entry = $this->get_entry( $entry_id );

		if ( ! $entry ) {
			return new \WP_Error( 'not_found', __( 'Entry not found.', 'bkx-waiting-lists' ) );
		}

		// Verify token or user ownership.
		if ( ! $this->verify_access( $entry, $token ) ) {
			return new \WP_Error( 'unauthorized', __( 'You are not authorized to remove this entry.', 'bkx-waiting-lists' ) );
		}

		$this->update_status( $entry_id, 'cancelled' );

		// Reorder positions.
		$this->reorder_positions( $entry['seat_id'], $entry['service_id'], $entry['booking_date'], $entry['booking_time'] );

		/**
		 * Fires when someone leaves the waiting list.
		 *
		 * @since 1.0.0
		 * @param int   $entry_id Entry ID.
		 * @param array $entry    Entry data.
		 */
		do_action( 'bkx_waitlist_left', $entry_id, $entry );

		return true;
	}

	/**
	 * Process a booking cancellation.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id    Seat ID.
	 * @param int    $service_id Service ID.
	 * @param string $date       Booking date.
	 * @param string $time       Booking time.
	 * @return void
	 */
	public function process_cancellation( int $seat_id, int $service_id, string $date, string $time ): void {
		// Get next person on waitlist.
		$next_entry = $this->get_next_waiting( $seat_id, $service_id, $date, $time );

		if ( ! $next_entry ) {
			return;
		}

		// Offer the slot.
		$this->offer_slot( $next_entry['id'] );
	}

	/**
	 * Offer a slot to someone on the waitlist.
	 *
	 * @since 1.0.0
	 * @param int $entry_id Entry ID.
	 * @return void
	 */
	public function offer_slot( int $entry_id ): void {
		global $wpdb;

		$expiry_hours = $this->addon->get_setting( 'offer_expiry_hours', 24 );
		$expires_at   = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_hours} hours" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array(
				'status'           => 'offered',
				'notified_at'      => current_time( 'mysql' ),
				'offer_expires_at' => $expires_at,
			),
			array( 'id' => $entry_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Send notification.
		$this->notifications->send_offer_notification( $entry_id );

		/**
		 * Fires when a slot is offered to someone on the waitlist.
		 *
		 * @since 1.0.0
		 * @param int $entry_id Entry ID.
		 */
		do_action( 'bkx_waitlist_slot_offered', $entry_id );
	}

	/**
	 * Accept an offer.
	 *
	 * @since 1.0.0
	 * @param int    $entry_id Entry ID.
	 * @param string $token    Security token.
	 * @return int|\WP_Error Booking ID or error.
	 */
	public function accept_offer( int $entry_id, string $token ) {
		$entry = $this->get_entry( $entry_id );

		if ( ! $entry ) {
			return new \WP_Error( 'not_found', __( 'Entry not found.', 'bkx-waiting-lists' ) );
		}

		if ( ! $this->verify_access( $entry, $token ) ) {
			return new \WP_Error( 'unauthorized', __( 'Invalid token.', 'bkx-waiting-lists' ) );
		}

		if ( 'offered' !== $entry['status'] ) {
			return new \WP_Error( 'invalid_status', __( 'This offer is no longer available.', 'bkx-waiting-lists' ) );
		}

		// Check if offer expired.
		if ( ! empty( $entry['offer_expires_at'] ) && strtotime( $entry['offer_expires_at'] ) < time() ) {
			$this->update_status( $entry_id, 'expired' );
			return new \WP_Error( 'offer_expired', __( 'This offer has expired.', 'bkx-waiting-lists' ) );
		}

		// Create the booking.
		$booking_id = $this->create_booking( $entry );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		// Update entry.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array(
				'status'      => 'accepted',
				'accepted_at' => current_time( 'mysql' ),
				'booking_id'  => $booking_id,
			),
			array( 'id' => $entry_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		// Send confirmation.
		$this->notifications->send_accepted_notification( $entry_id, $booking_id );

		/**
		 * Fires when a waitlist offer is accepted.
		 *
		 * @since 1.0.0
		 * @param int $entry_id   Entry ID.
		 * @param int $booking_id Booking ID.
		 */
		do_action( 'bkx_waitlist_offer_accepted', $entry_id, $booking_id );

		return $booking_id;
	}

	/**
	 * Decline an offer.
	 *
	 * @since 1.0.0
	 * @param int    $entry_id Entry ID.
	 * @param string $token    Security token.
	 * @return true|\WP_Error
	 */
	public function decline_offer( int $entry_id, string $token ) {
		$entry = $this->get_entry( $entry_id );

		if ( ! $entry ) {
			return new \WP_Error( 'not_found', __( 'Entry not found.', 'bkx-waiting-lists' ) );
		}

		if ( ! $this->verify_access( $entry, $token ) ) {
			return new \WP_Error( 'unauthorized', __( 'Invalid token.', 'bkx-waiting-lists' ) );
		}

		$this->update_status( $entry_id, 'declined' );

		// Offer to next person.
		$next_entry = $this->get_next_waiting(
			$entry['seat_id'],
			$entry['service_id'],
			$entry['booking_date'],
			$entry['booking_time']
		);

		if ( $next_entry ) {
			$this->offer_slot( $next_entry['id'] );
		}

		/**
		 * Fires when a waitlist offer is declined.
		 *
		 * @since 1.0.0
		 * @param int $entry_id Entry ID.
		 */
		do_action( 'bkx_waitlist_offer_declined', $entry_id );

		return true;
	}

	/**
	 * Check for expired offers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function check_expired_offers(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}
				WHERE status = 'offered'
				AND offer_expires_at < %s",
				current_time( 'mysql' )
			),
			ARRAY_A
		);

		foreach ( $expired as $entry ) {
			$this->update_status( $entry['id'], 'expired' );

			// Offer to next person.
			$next_entry = $this->get_next_waiting(
				$entry['seat_id'],
				$entry['service_id'],
				$entry['booking_date'],
				$entry['booking_time']
			);

			if ( $next_entry ) {
				$this->offer_slot( $next_entry['id'] );
			}
		}
	}

	/**
	 * Clean up old entries.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cleanup_old_entries(): void {
		global $wpdb;

		$retention_days = $this->addon->get_setting( 'retention_days', 30 );
		$cutoff_date    = gmdate( 'Y-m-d', strtotime( "-{$retention_days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$this->table}
				WHERE booking_date < %s
				OR (status IN ('cancelled', 'declined', 'expired') AND created_at < %s)",
				$cutoff_date,
				$cutoff_date . ' 00:00:00'
			)
		);
	}

	/**
	 * Get entry by ID.
	 *
	 * @since 1.0.0
	 * @param int $entry_id Entry ID.
	 * @return array|null
	 */
	public function get_entry( int $entry_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table} WHERE id = %d",
				$entry_id
			),
			ARRAY_A
		);

		return $entry ?: null;
	}

	/**
	 * Get user's waitlist entries.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_entries( int $user_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}
				WHERE user_id = %d
				AND status IN ('waiting', 'offered')
				AND booking_date >= %s
				ORDER BY booking_date, booking_time",
				$user_id,
				gmdate( 'Y-m-d' )
			),
			ARRAY_A
		);

		return $entries ?: array();
	}

	/**
	 * Get waitlist for a slot.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id    Seat ID.
	 * @param int    $service_id Service ID.
	 * @param string $date       Booking date.
	 * @param string $time       Booking time.
	 * @return array
	 */
	public function get_waitlist( int $seat_id, int $service_id, string $date, string $time ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}
				WHERE seat_id = %d
				AND service_id = %d
				AND booking_date = %s
				AND booking_time = %s
				AND status IN ('waiting', 'offered')
				ORDER BY position",
				$seat_id,
				$service_id,
				$date,
				$time
			),
			ARRAY_A
		);

		return $entries ?: array();
	}

	/**
	 * Get next waiting entry.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id    Seat ID.
	 * @param int    $service_id Service ID.
	 * @param string $date       Booking date.
	 * @param string $time       Booking time.
	 * @return array|null
	 */
	private function get_next_waiting( int $seat_id, int $service_id, string $date, string $time ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}
				WHERE seat_id = %d
				AND service_id = %d
				AND booking_date = %s
				AND booking_time = %s
				AND status = 'waiting'
				ORDER BY position
				LIMIT 1",
				$seat_id,
				$service_id,
				$date,
				$time
			),
			ARRAY_A
		);

		return $entry ?: null;
	}

	/**
	 * Find existing entry.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id    Seat ID.
	 * @param int    $service_id Service ID.
	 * @param string $date       Booking date.
	 * @param string $time       Booking time.
	 * @param string $email      Customer email.
	 * @return array|null
	 */
	private function find_entry( int $seat_id, int $service_id, string $date, string $time, string $email ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}
				WHERE seat_id = %d
				AND service_id = %d
				AND booking_date = %s
				AND booking_time = %s
				AND customer_email = %s
				AND status IN ('waiting', 'offered')",
				$seat_id,
				$service_id,
				$date,
				$time,
				$email
			),
			ARRAY_A
		);

		return $entry ?: null;
	}

	/**
	 * Get waitlist count.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id    Seat ID.
	 * @param int    $service_id Service ID.
	 * @param string $date       Booking date.
	 * @param string $time       Booking time.
	 * @return int
	 */
	private function get_waitlist_count( int $seat_id, int $service_id, string $date, string $time ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->table}
				WHERE seat_id = %d
				AND service_id = %d
				AND booking_date = %s
				AND booking_time = %s
				AND status IN ('waiting', 'offered')",
				$seat_id,
				$service_id,
				$date,
				$time
			)
		);

		return (int) $count;
	}

	/**
	 * Update entry status.
	 *
	 * @since 1.0.0
	 * @param int    $entry_id Entry ID.
	 * @param string $status   New status.
	 * @return bool
	 */
	private function update_status( int $entry_id, string $status ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array( 'status' => $status ),
			array( 'id' => $entry_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Reorder positions after removal.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id    Seat ID.
	 * @param int    $service_id Service ID.
	 * @param string $date       Booking date.
	 * @param string $time       Booking time.
	 * @return void
	 */
	private function reorder_positions( int $seat_id, int $service_id, string $date, string $time ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$this->table}
				WHERE seat_id = %d
				AND service_id = %d
				AND booking_date = %s
				AND booking_time = %s
				AND status IN ('waiting', 'offered')
				ORDER BY position",
				$seat_id,
				$service_id,
				$date,
				$time
			),
			ARRAY_A
		);

		$position = 1;
		foreach ( $entries as $entry ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array( 'position' => $position ),
				array( 'id' => $entry['id'] ),
				array( '%d' ),
				array( '%d' )
			);
			++$position;
		}
	}

	/**
	 * Verify access to entry.
	 *
	 * @since 1.0.0
	 * @param array  $entry Entry data.
	 * @param string $token Security token.
	 * @return bool
	 */
	private function verify_access( array $entry, string $token ): bool {
		// Admin can access any entry.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Token verification.
		if ( ! empty( $token ) && hash_equals( $entry['token'], $token ) ) {
			return true;
		}

		// User ownership.
		if ( is_user_logged_in() && (int) $entry['user_id'] === get_current_user_id() ) {
			return true;
		}

		return false;
	}

	/**
	 * Create booking from waitlist entry.
	 *
	 * @since 1.0.0
	 * @param array $entry Waitlist entry.
	 * @return int|\WP_Error Booking ID or error.
	 */
	private function create_booking( array $entry ) {
		$booking_data = array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-pending',
			'post_title'  => sprintf(
				/* translators: 1: date, 2: time */
				__( 'Booking %1$s %2$s', 'bkx-waiting-lists' ),
				$entry['booking_date'],
				$entry['booking_time']
			),
		);

		$booking_id = wp_insert_post( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		// Add meta.
		update_post_meta( $booking_id, 'seat_id', $entry['seat_id'] );
		update_post_meta( $booking_id, 'base_id', $entry['service_id'] );
		update_post_meta( $booking_id, 'booking_date', $entry['booking_date'] );
		update_post_meta( $booking_id, 'booking_time', substr( $entry['booking_time'], 0, 5 ) );
		update_post_meta( $booking_id, 'customer_name', $entry['customer_name'] );
		update_post_meta( $booking_id, 'customer_email', $entry['customer_email'] );
		update_post_meta( $booking_id, 'customer_phone', $entry['customer_phone'] );
		update_post_meta( $booking_id, '_created_from_waitlist', $entry['id'] );

		if ( $entry['user_id'] ) {
			update_post_meta( $booking_id, '_customer_user', $entry['user_id'] );
		}

		/**
		 * Fires after a booking is created from the waitlist.
		 *
		 * @since 1.0.0
		 * @param int   $booking_id Booking ID.
		 * @param array $entry      Waitlist entry.
		 */
		do_action( 'bkx_waitlist_booking_created', $booking_id, $entry );

		return $booking_id;
	}
}
