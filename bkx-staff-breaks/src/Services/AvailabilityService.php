<?php
/**
 * Availability Service
 *
 * @package BookingX\StaffBreaks
 * @since   1.0.0
 */

namespace BookingX\StaffBreaks\Services;

use BookingX\StaffBreaks\StaffBreaksAddon;

/**
 * Class AvailabilityService
 *
 * Filters availability based on breaks and time off.
 *
 * @since 1.0.0
 */
class AvailabilityService {

	/**
	 * Addon instance.
	 *
	 * @var StaffBreaksAddon
	 */
	private StaffBreaksAddon $addon;

	/**
	 * Breaks service.
	 *
	 * @var BreaksService
	 */
	private BreaksService $breaks_service;

	/**
	 * Time off service.
	 *
	 * @var TimeOffService
	 */
	private TimeOffService $timeoff_service;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param StaffBreaksAddon $addon           Addon instance.
	 * @param BreaksService    $breaks_service  Breaks service.
	 * @param TimeOffService   $timeoff_service Time off service.
	 */
	public function __construct(
		StaffBreaksAddon $addon,
		BreaksService $breaks_service,
		TimeOffService $timeoff_service
	) {
		$this->addon           = $addon;
		$this->breaks_service  = $breaks_service;
		$this->timeoff_service = $timeoff_service;
	}

	/**
	 * Filter available slots.
	 *
	 * @since 1.0.0
	 * @param array  $slots   Available slots.
	 * @param int    $seat_id Seat ID.
	 * @param string $date    Date (Y-m-d).
	 * @return array Filtered slots.
	 */
	public function filter_available_slots( array $slots, int $seat_id, string $date ): array {
		if ( empty( $slots ) || empty( $seat_id ) ) {
			return $slots;
		}

		// Check if entire day is blocked.
		if ( $this->is_day_blocked( $seat_id, $date ) ) {
			return array();
		}

		$day_name = strtolower( gmdate( 'l', strtotime( $date ) ) );

		// Get breaks and blocked times for this day.
		$breaks       = $this->breaks_service->get_breaks_for_day( $seat_id, $day_name );
		$blocked_times = $this->timeoff_service->get_blocked_times( $seat_id, $date );

		// Filter out slots that conflict with breaks or time off.
		$filtered_slots = array();

		foreach ( $slots as $slot ) {
			$slot_start = $this->get_slot_time( $slot, 'start' );
			$slot_end   = $this->get_slot_time( $slot, 'end' );

			if ( empty( $slot_start ) || empty( $slot_end ) ) {
				$filtered_slots[] = $slot;
				continue;
			}

			// Check against breaks.
			$conflicts_break = false;
			foreach ( $breaks as $break ) {
				if ( $this->times_overlap( $slot_start, $slot_end, $break['start_time'], $break['end_time'] ) ) {
					$conflicts_break = true;
					break;
				}
			}

			if ( $conflicts_break ) {
				continue;
			}

			// Check against blocked times.
			$conflicts_blocked = false;
			foreach ( $blocked_times as $blocked ) {
				if ( $this->times_overlap( $slot_start, $slot_end, $blocked['start'], $blocked['end'] ) ) {
					$conflicts_blocked = true;
					break;
				}
			}

			if ( $conflicts_blocked ) {
				continue;
			}

			$filtered_slots[] = $slot;
		}

		return $filtered_slots;
	}

	/**
	 * Check if seat is available on a date/time.
	 *
	 * @since 1.0.0
	 * @param bool   $available  Current availability.
	 * @param int    $seat_id    Seat ID.
	 * @param string $date       Date (Y-m-d).
	 * @param string $start_time Start time (H:i).
	 * @return bool
	 */
	public function check_seat_available( bool $available, int $seat_id, string $date, string $start_time ): bool {
		if ( ! $available ) {
			return false;
		}

		// Check if day is blocked.
		if ( $this->is_day_blocked( $seat_id, $date ) ) {
			return false;
		}

		// Check if specific time is blocked.
		if ( $this->timeoff_service->is_date_blocked( $seat_id, $date, $start_time ) ) {
			return false;
		}

		// Check breaks.
		$day_name = strtolower( gmdate( 'l', strtotime( $date ) ) );
		$breaks   = $this->breaks_service->get_breaks_for_day( $seat_id, $day_name );

		foreach ( $breaks as $break ) {
			$break_start = substr( $break['start_time'], 0, 5 );
			$break_end   = substr( $break['end_time'], 0, 5 );

			if ( $start_time >= $break_start && $start_time < $break_end ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if an entire day is blocked.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat ID.
	 * @param string $date    Date (Y-m-d).
	 * @return bool
	 */
	public function is_day_blocked( int $seat_id, string $date ): bool {
		$blocked_times = $this->timeoff_service->get_blocked_times( $seat_id, $date );

		foreach ( $blocked_times as $blocked ) {
			if ( $blocked['all_day'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get slot time from slot array.
	 *
	 * @since 1.0.0
	 * @param mixed  $slot Slot data.
	 * @param string $type 'start' or 'end'.
	 * @return string
	 */
	private function get_slot_time( $slot, string $type ): string {
		if ( is_array( $slot ) ) {
			if ( 'start' === $type ) {
				return $slot['start_time'] ?? $slot['time'] ?? $slot[0] ?? '';
			}
			return $slot['end_time'] ?? $slot[1] ?? '';
		}

		// String format (e.g., "09:00" or "09:00-10:00").
		if ( is_string( $slot ) ) {
			if ( strpos( $slot, '-' ) !== false ) {
				$parts = explode( '-', $slot );
				return 'start' === $type ? trim( $parts[0] ) : trim( $parts[1] );
			}
			return $slot;
		}

		return '';
	}

	/**
	 * Check if two time ranges overlap.
	 *
	 * @since 1.0.0
	 * @param string $start1 First range start.
	 * @param string $end1   First range end.
	 * @param string $start2 Second range start.
	 * @param string $end2   Second range end.
	 * @return bool
	 */
	private function times_overlap( string $start1, string $end1, string $start2, string $end2 ): bool {
		// Normalize to H:i format.
		$start1 = substr( $start1, 0, 5 );
		$end1   = substr( $end1, 0, 5 );
		$start2 = substr( $start2, 0, 5 );
		$end2   = substr( $end2, 0, 5 );

		return $start1 < $end2 && $end1 > $start2;
	}

	/**
	 * Get availability summary for a date range.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id    Seat ID.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_availability_summary( int $seat_id, string $start_date, string $end_date ): array {
		$summary = array(
			'blocked_days'  => array(),
			'partial_days'  => array(),
			'available_days' => 0,
		);

		$current = new \DateTime( $start_date );
		$end     = new \DateTime( $end_date );

		while ( $current <= $end ) {
			$date = $current->format( 'Y-m-d' );

			if ( $this->is_day_blocked( $seat_id, $date ) ) {
				$summary['blocked_days'][] = $date;
			} else {
				$blocked_times = $this->timeoff_service->get_blocked_times( $seat_id, $date );
				$day_name      = strtolower( $current->format( 'l' ) );
				$breaks        = $this->breaks_service->get_breaks_for_day( $seat_id, $day_name );

				if ( ! empty( $blocked_times ) || ! empty( $breaks ) ) {
					$summary['partial_days'][] = array(
						'date'          => $date,
						'blocked_times' => $blocked_times,
						'breaks'        => $breaks,
					);
				} else {
					++$summary['available_days'];
				}
			}

			$current->modify( '+1 day' );
		}

		return $summary;
	}
}
