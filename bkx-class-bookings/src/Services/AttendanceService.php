<?php
/**
 * Attendance Service
 *
 * @package BookingX\ClassBookings\Services
 * @since   1.0.0
 */

namespace BookingX\ClassBookings\Services;

/**
 * Service for managing class attendance.
 *
 * @since 1.0.0
 */
class AttendanceService {

	/**
	 * Book a class session.
	 *
	 * @since 1.0.0
	 * @param array $data Booking data.
	 * @return int|false Booking ID or false on failure.
	 */
	public function book_class( array $data ) {
		global $wpdb;

		$required = array( 'session_id', 'customer_name', 'customer_email' );
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return false;
			}
		}

		$session = ( new ScheduleService() )->get_session( $data['session_id'] );
		if ( ! $session ) {
			return false;
		}

		$quantity  = isset( $data['quantity'] ) ? absint( $data['quantity'] ) : 1;
		$available = $session['capacity'] - $session['booked_count'];

		if ( $available < $quantity ) {
			return false;
		}

		$table = $wpdb->prefix . 'bkx_class_bookings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			array(
				'session_id'     => absint( $data['session_id'] ),
				'class_id'       => absint( $session['class_id'] ),
				'booking_id'     => isset( $data['booking_id'] ) ? absint( $data['booking_id'] ) : null,
				'customer_id'    => isset( $data['customer_id'] ) ? absint( $data['customer_id'] ) : null,
				'customer_name'  => sanitize_text_field( $data['customer_name'] ),
				'customer_email' => sanitize_email( $data['customer_email'] ),
				'customer_phone' => isset( $data['customer_phone'] ) ? sanitize_text_field( $data['customer_phone'] ) : null,
				'quantity'       => $quantity,
				'status'         => 'registered',
				'notes'          => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$booking_id = $wpdb->insert_id;

		// Update session booked count.
		( new ScheduleService() )->update_booked_count( $data['session_id'], $quantity );

		// Trigger action.
		do_action( 'bkx_class_booked', $booking_id, $data );

		return $booking_id;
	}

	/**
	 * Cancel a class booking.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Class booking ID.
	 * @param string $reason     Cancellation reason.
	 * @return bool
	 */
	public function cancel_booking( int $booking_id, string $reason = '' ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_bookings';

		// Get the booking.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking || 'cancelled' === $booking['status'] ) {
			return false;
		}

		// Update status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table,
			array(
				'status' => 'cancelled',
				'notes'  => $reason ? $booking['notes'] . "\nCancellation: " . $reason : $booking['notes'],
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		// Update session booked count.
		( new ScheduleService() )->update_booked_count( $booking['session_id'], -$booking['quantity'] );

		// Trigger action.
		do_action( 'bkx_class_booking_cancelled', $booking_id, $booking, $reason );

		return true;
	}

	/**
	 * Get bookings for a session.
	 *
	 * @since 1.0.0
	 * @param int $session_id Session ID.
	 * @return array
	 */
	public function get_session_bookings( int $session_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_bookings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_id = %d ORDER BY created_at",
				$session_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Get bookings for a customer.
	 *
	 * @since 1.0.0
	 * @param string $email      Customer email.
	 * @param string $status     Optional status filter.
	 * @param bool   $future_only Only return future sessions.
	 * @return array
	 */
	public function get_customer_bookings( string $email, string $status = '', bool $future_only = false ): array {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bkx_class_bookings';
		$sessions_table = $wpdb->prefix . 'bkx_class_sessions';

		$where = 'b.customer_email = %s';
		$args  = array( $email );

		if ( $status ) {
			$where .= ' AND b.status = %s';
			$args[] = $status;
		}

		if ( $future_only ) {
			$where .= ' AND s.session_date >= %s';
			$args[] = current_time( 'Y-m-d' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, s.session_date, s.start_time, s.end_time, p.post_title as class_name
				FROM {$bookings_table} b
				INNER JOIN {$sessions_table} s ON b.session_id = s.id
				INNER JOIN {$wpdb->posts} p ON b.class_id = p.ID
				WHERE {$where}
				ORDER BY s.session_date DESC, s.start_time DESC",
				...$args
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Mark attendance.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Class booking ID.
	 * @param string $status     Attendance status (present, absent, late).
	 * @param int    $marked_by  User ID who marked attendance.
	 * @return bool
	 */
	public function mark_attendance( int $booking_id, string $status, int $marked_by = 0 ): bool {
		global $wpdb;

		$allowed_statuses = array( 'present', 'absent', 'late', 'excused' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return false;
		}

		$bookings_table   = $wpdb->prefix . 'bkx_class_bookings';
		$attendance_table = $wpdb->prefix . 'bkx_class_attendance';

		// Get the booking.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$bookings_table} WHERE id = %d",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return false;
		}

		$now = current_time( 'mysql' );

		// Check if attendance record exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$attendance_table} WHERE class_booking_id = %d AND session_id = %d",
				$booking_id,
				$booking['session_id']
			)
		);

		if ( $existing ) {
			// Update existing record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->update(
				$attendance_table,
				array(
					'status'    => $status,
					'marked_by' => $marked_by ?: null,
				),
				array( 'id' => $existing ),
				array( '%s', '%d' ),
				array( '%d' )
			);
		} else {
			// Insert new record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->insert(
				$attendance_table,
				array(
					'class_booking_id' => $booking_id,
					'session_id'       => $booking['session_id'],
					'status'           => $status,
					'check_in_time'    => 'present' === $status || 'late' === $status ? $now : null,
					'marked_by'        => $marked_by ?: null,
				),
				array( '%d', '%d', '%s', '%s', '%d' )
			);
		}

		if ( false === $result ) {
			return false;
		}

		// Update booking checked_in_at if present.
		if ( 'present' === $status || 'late' === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$bookings_table,
				array( 'checked_in_at' => $now ),
				array( 'id' => $booking_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		// Trigger action.
		do_action( 'bkx_class_attendance_marked', $booking_id, $status, $booking );

		return true;
	}

	/**
	 * Get attendance for a session.
	 *
	 * @since 1.0.0
	 * @param int $session_id Session ID.
	 * @return array
	 */
	public function get_session_attendance( int $session_id ): array {
		global $wpdb;

		$attendance_table = $wpdb->prefix . 'bkx_class_attendance';
		$bookings_table   = $wpdb->prefix . 'bkx_class_bookings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, b.customer_name, b.customer_email, b.quantity
				FROM {$attendance_table} a
				INNER JOIN {$bookings_table} b ON a.class_booking_id = b.id
				WHERE a.session_id = %d
				ORDER BY b.customer_name",
				$session_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Get attendance statistics for a class.
	 *
	 * @since 1.0.0
	 * @param int    $class_id   Class post ID.
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array
	 */
	public function get_attendance_stats( int $class_id, string $start_date, string $end_date ): array {
		global $wpdb;

		$attendance_table = $wpdb->prefix . 'bkx_class_attendance';
		$sessions_table   = $wpdb->prefix . 'bkx_class_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_records,
					SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
					SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
					SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
					SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count
				FROM {$attendance_table} a
				INNER JOIN {$sessions_table} s ON a.session_id = s.id
				WHERE s.class_id = %d
				AND s.session_date BETWEEN %s AND %s",
				$class_id,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		if ( ! $stats ) {
			return array(
				'total_records'    => 0,
				'present_count'    => 0,
				'absent_count'     => 0,
				'late_count'       => 0,
				'excused_count'    => 0,
				'attendance_rate'  => 0,
			);
		}

		$total = (int) $stats['total_records'];
		$stats['attendance_rate'] = $total > 0
			? round( ( ( (int) $stats['present_count'] + (int) $stats['late_count'] ) / $total ) * 100, 2 )
			: 0;

		return $stats;
	}

	/**
	 * Check in a participant.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Class booking ID.
	 * @return bool
	 */
	public function check_in( int $booking_id ): bool {
		return $this->mark_attendance( $booking_id, 'present', get_current_user_id() );
	}

	/**
	 * Check out a participant.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Class booking ID.
	 * @return bool
	 */
	public function check_out( int $booking_id ): bool {
		global $wpdb;

		$attendance_table = $wpdb->prefix . 'bkx_class_attendance';
		$bookings_table   = $wpdb->prefix . 'bkx_class_bookings';

		// Get the booking.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$bookings_table} WHERE id = %d",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$attendance_table,
			array( 'check_out_time' => current_time( 'mysql' ) ),
			array(
				'class_booking_id' => $booking_id,
				'session_id'       => $booking['session_id'],
			),
			array( '%s' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}
}
