<?php
/**
 * Schedule Service
 *
 * @package BookingX\ClassBookings\Services
 * @since   1.0.0
 */

namespace BookingX\ClassBookings\Services;

/**
 * Service for managing class schedules and sessions.
 *
 * @since 1.0.0
 */
class ScheduleService {

	/**
	 * Add a recurring schedule.
	 *
	 * @since 1.0.0
	 * @param array $data Schedule data.
	 * @return int|false Schedule ID or false on failure.
	 */
	public function add_schedule( array $data ) {
		global $wpdb;

		$defaults = array(
			'class_id'    => 0,
			'seat_id'     => null,
			'day_of_week' => 0,
			'start_time'  => '09:00:00',
			'end_time'    => '10:00:00',
			'capacity'    => 10,
			'is_active'   => 1,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields.
		if ( empty( $data['class_id'] ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'bkx_class_schedules';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			array(
				'class_id'    => absint( $data['class_id'] ),
				'seat_id'     => $data['seat_id'] ? absint( $data['seat_id'] ) : null,
				'day_of_week' => absint( $data['day_of_week'] ),
				'start_time'  => sanitize_text_field( $data['start_time'] ),
				'end_time'    => sanitize_text_field( $data['end_time'] ),
				'capacity'    => absint( $data['capacity'] ),
				'is_active'   => absint( $data['is_active'] ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%d' )
		);

		if ( ! $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update a schedule.
	 *
	 * @since 1.0.0
	 * @param int   $schedule_id Schedule ID.
	 * @param array $data        Schedule data.
	 * @return bool
	 */
	public function update_schedule( int $schedule_id, array $data ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_schedules';

		$update_data = array();
		$format      = array();

		if ( isset( $data['seat_id'] ) ) {
			$update_data['seat_id'] = $data['seat_id'] ? absint( $data['seat_id'] ) : null;
			$format[]               = '%d';
		}

		if ( isset( $data['day_of_week'] ) ) {
			$update_data['day_of_week'] = absint( $data['day_of_week'] );
			$format[]                   = '%d';
		}

		if ( isset( $data['start_time'] ) ) {
			$update_data['start_time'] = sanitize_text_field( $data['start_time'] );
			$format[]                  = '%s';
		}

		if ( isset( $data['end_time'] ) ) {
			$update_data['end_time'] = sanitize_text_field( $data['end_time'] );
			$format[]                = '%s';
		}

		if ( isset( $data['capacity'] ) ) {
			$update_data['capacity'] = absint( $data['capacity'] );
			$format[]                = '%d';
		}

		if ( isset( $data['is_active'] ) ) {
			$update_data['is_active'] = absint( $data['is_active'] );
			$format[]                 = '%d';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $schedule_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a schedule.
	 *
	 * @since 1.0.0
	 * @param int $schedule_id Schedule ID.
	 * @return bool
	 */
	public function delete_schedule( int $schedule_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_schedules';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$table,
			array( 'id' => $schedule_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get schedules for a class.
	 *
	 * @since 1.0.0
	 * @param int $class_id Class post ID.
	 * @return array
	 */
	public function get_schedules( int $class_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_schedules';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE class_id = %d ORDER BY day_of_week, start_time",
				$class_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Generate sessions from schedules.
	 *
	 * @since 1.0.0
	 * @param int    $class_id   Class post ID.
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return int Number of sessions created.
	 */
	public function generate_sessions( int $class_id, string $start_date, string $end_date ): int {
		$schedules = $this->get_schedules( $class_id );

		if ( empty( $schedules ) ) {
			return 0;
		}

		$created = 0;
		$current = strtotime( $start_date );
		$end     = strtotime( $end_date );

		while ( $current <= $end ) {
			$day_of_week = (int) gmdate( 'w', $current );
			$date        = gmdate( 'Y-m-d', $current );

			foreach ( $schedules as $schedule ) {
				if ( (int) $schedule['day_of_week'] === $day_of_week && $schedule['is_active'] ) {
					$session_id = $this->create_session(
						array(
							'class_id'    => $class_id,
							'schedule_id' => $schedule['id'],
							'seat_id'     => $schedule['seat_id'],
							'date'        => $date,
							'start_time'  => $schedule['start_time'],
							'end_time'    => $schedule['end_time'],
							'capacity'    => $schedule['capacity'],
						)
					);

					if ( $session_id ) {
						++$created;
					}
				}
			}

			$current = strtotime( '+1 day', $current );
		}

		return $created;
	}

	/**
	 * Create a session.
	 *
	 * @since 1.0.0
	 * @param array $data Session data.
	 * @return int|false Session ID or false on failure.
	 */
	public function create_session( array $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_sessions';

		// Check if session already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE class_id = %d AND session_date = %s AND start_time = %s",
				$data['class_id'],
				$data['date'],
				$data['start_time']
			)
		);

		if ( $existing ) {
			return (int) $existing;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			array(
				'class_id'     => absint( $data['class_id'] ),
				'schedule_id'  => isset( $data['schedule_id'] ) ? absint( $data['schedule_id'] ) : null,
				'seat_id'      => isset( $data['seat_id'] ) ? absint( $data['seat_id'] ) : null,
				'session_date' => sanitize_text_field( $data['date'] ),
				'start_time'   => sanitize_text_field( $data['start_time'] ),
				'end_time'     => sanitize_text_field( $data['end_time'] ),
				'capacity'     => absint( $data['capacity'] ),
				'booked_count' => 0,
				'status'       => 'scheduled',
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get a session.
	 *
	 * @since 1.0.0
	 * @param int $session_id Session ID.
	 * @return array|null
	 */
	public function get_session( int $session_id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$session_id
			),
			ARRAY_A
		);

		return $session ?: null;
	}

	/**
	 * Get sessions for a date range.
	 *
	 * @since 1.0.0
	 * @param string   $start_date Start date (Y-m-d).
	 * @param string   $end_date   End date (Y-m-d).
	 * @param int|null $class_id   Optional class ID filter.
	 * @return array
	 */
	public function get_sessions( string $start_date, string $end_date, ?int $class_id = null ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_sessions';

		$where = 'session_date BETWEEN %s AND %s';
		$args  = array( $start_date, $end_date );

		if ( $class_id ) {
			$where .= ' AND class_id = %d';
			$args[] = $class_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, p.post_title as class_name
				FROM {$table} s
				INNER JOIN {$wpdb->posts} p ON s.class_id = p.ID
				WHERE {$where}
				ORDER BY session_date, start_time",
				...$args
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Update session status.
	 *
	 * @since 1.0.0
	 * @param int    $session_id Session ID.
	 * @param string $status     New status.
	 * @return bool
	 */
	public function update_session_status( int $session_id, string $status ): bool {
		global $wpdb;

		$allowed_statuses = array( 'scheduled', 'cancelled', 'completed' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'bkx_class_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'id' => $session_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update booked count for a session.
	 *
	 * @since 1.0.0
	 * @param int $session_id Session ID.
	 * @param int $change     Change in count (positive or negative).
	 * @return bool
	 */
	public function update_booked_count( int $session_id, int $change ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET booked_count = GREATEST(0, booked_count + %d) WHERE id = %d",
				$change,
				$session_id
			)
		);

		return false !== $result;
	}

	/**
	 * Cancel a session.
	 *
	 * @since 1.0.0
	 * @param int    $session_id Session ID.
	 * @param string $reason     Cancellation reason.
	 * @return bool
	 */
	public function cancel_session( int $session_id, string $reason = '' ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table,
			array(
				'status' => 'cancelled',
				'notes'  => $reason,
			),
			array( 'id' => $session_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		// Trigger action for notifications.
		do_action( 'bkx_class_session_cancelled', $session_id, $reason );

		return true;
	}
}
