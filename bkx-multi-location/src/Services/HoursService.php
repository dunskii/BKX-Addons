<?php
/**
 * Hours service for managing location hours and holidays.
 *
 * @package BookingX\MultiLocation\Services
 */

namespace BookingX\MultiLocation\Services;

defined( 'ABSPATH' ) || exit;

/**
 * HoursService class.
 */
class HoursService {

	/**
	 * Hours table name.
	 *
	 * @var string
	 */
	private $hours_table;

	/**
	 * Holidays table name.
	 *
	 * @var string
	 */
	private $holidays_table;

	/**
	 * Day names.
	 *
	 * @var array
	 */
	private $day_names = array(
		0 => 'Sunday',
		1 => 'Monday',
		2 => 'Tuesday',
		3 => 'Wednesday',
		4 => 'Thursday',
		5 => 'Friday',
		6 => 'Saturday',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->hours_table    = $wpdb->prefix . 'bkx_location_hours';
		$this->holidays_table = $wpdb->prefix . 'bkx_location_holidays';
	}

	/**
	 * Get hours for a location.
	 *
	 * @param int $location_id Location ID.
	 * @return array
	 */
	public function get_for_location( $location_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->hours_table} WHERE location_id = %d ORDER BY day_of_week",
				$location_id
			)
		);
	}

	/**
	 * Get hours for a specific day.
	 *
	 * @param int $location_id Location ID.
	 * @param int $day_of_week Day of week (0 = Sunday).
	 * @return object|null
	 */
	public function get_day( $location_id, $day_of_week ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->hours_table} WHERE location_id = %d AND day_of_week = %d",
				$location_id,
				$day_of_week
			)
		);
	}

	/**
	 * Save hours for a location.
	 *
	 * @param int   $location_id Location ID.
	 * @param array $hours       Hours data indexed by day.
	 * @return bool|\WP_Error
	 */
	public function save( $location_id, $hours ) {
		global $wpdb;

		foreach ( $hours as $day => $data ) {
			$day_of_week = absint( $day );
			if ( $day_of_week > 6 ) {
				continue;
			}

			$is_open     = ! empty( $data['is_open'] ) ? 1 : 0;
			$open_time   = $is_open && ! empty( $data['open_time'] ) ? sanitize_text_field( $data['open_time'] ) : null;
			$close_time  = $is_open && ! empty( $data['close_time'] ) ? sanitize_text_field( $data['close_time'] ) : null;
			$break_start = $is_open && ! empty( $data['break_start'] ) ? sanitize_text_field( $data['break_start'] ) : null;
			$break_end   = $is_open && ! empty( $data['break_end'] ) ? sanitize_text_field( $data['break_end'] ) : null;

			// Check if row exists.
			$existing = $this->get_day( $location_id, $day_of_week );

			if ( $existing ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update(
					$this->hours_table,
					array(
						'is_open'     => $is_open,
						'open_time'   => $open_time,
						'close_time'  => $close_time,
						'break_start' => $break_start,
						'break_end'   => $break_end,
					),
					array( 'id' => $existing->id ),
					array( '%d', '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$this->hours_table,
					array(
						'location_id' => $location_id,
						'day_of_week' => $day_of_week,
						'is_open'     => $is_open,
						'open_time'   => $open_time,
						'close_time'  => $close_time,
						'break_start' => $break_start,
						'break_end'   => $break_end,
					),
					array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
				);
			}
		}

		return true;
	}

	/**
	 * Check if location is open on a given date.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $date        Date in Y-m-d format.
	 * @return bool
	 */
	public function is_open( $location_id, $date ) {
		// Check holidays first.
		if ( $this->is_holiday( $location_id, $date ) ) {
			return false;
		}

		// Check day of week.
		$day_of_week = gmdate( 'w', strtotime( $date ) );
		$hours       = $this->get_day( $location_id, $day_of_week );

		return $hours && $hours->is_open;
	}

	/**
	 * Check if date is a holiday.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $date        Date in Y-m-d format.
	 * @return bool
	 */
	public function is_holiday( $location_id, $date ) {
		global $wpdb;

		$date_obj = new \DateTime( $date );

		// Check exact date match.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$exact = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->holidays_table} WHERE location_id = %d AND date = %s AND is_recurring = 0",
				$location_id,
				$date
			)
		);

		if ( $exact > 0 ) {
			return true;
		}

		// Check recurring holidays (same month and day).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$recurring = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->holidays_table}
				WHERE location_id = %d
				AND is_recurring = 1
				AND MONTH(date) = %d
				AND DAY(date) = %d",
				$location_id,
				$date_obj->format( 'n' ),
				$date_obj->format( 'j' )
			)
		);

		return $recurring > 0;
	}

	/**
	 * Get holidays for a location.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $year        Optional year filter.
	 * @return array
	 */
	public function get_holidays( $location_id, $year = '' ) {
		global $wpdb;

		if ( $year ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->holidays_table}
					WHERE location_id = %d
					AND (YEAR(date) = %d OR is_recurring = 1)
					ORDER BY MONTH(date), DAY(date)",
					$location_id,
					$year
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->holidays_table} WHERE location_id = %d ORDER BY date",
				$location_id
			)
		);
	}

	/**
	 * Add a holiday.
	 *
	 * @param int    $location_id  Location ID.
	 * @param string $name         Holiday name.
	 * @param string $date         Date in Y-m-d format.
	 * @param bool   $is_recurring Whether it recurs annually.
	 * @return int|\WP_Error Holiday ID or error.
	 */
	public function add_holiday( $location_id, $name, $date, $is_recurring = false ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$this->holidays_table,
			array(
				'location_id'  => $location_id,
				'name'         => $name,
				'date'         => $date,
				'is_recurring' => $is_recurring ? 1 : 0,
			),
			array( '%d', '%s', '%s', '%d' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to add holiday.', 'bkx-multi-location' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Delete a holiday.
	 *
	 * @param int $holiday_id Holiday ID.
	 * @return bool
	 */
	public function delete_holiday( $holiday_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete(
			$this->holidays_table,
			array( 'id' => $holiday_id ),
			array( '%d' )
		);
	}

	/**
	 * Get available time slots for a date.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $date        Date in Y-m-d format.
	 * @param int    $service_id  Optional service ID for duration.
	 * @return array
	 */
	public function get_available_slots( $location_id, $date, $service_id = 0 ) {
		if ( ! $this->is_open( $location_id, $date ) ) {
			return array();
		}

		$day_of_week = gmdate( 'w', strtotime( $date ) );
		$hours       = $this->get_day( $location_id, $day_of_week );

		if ( ! $hours || ! $hours->is_open ) {
			return array();
		}

		// Get slot duration (default 30 minutes).
		$slot_duration = 30;
		if ( $service_id ) {
			$service_duration = get_post_meta( $service_id, 'base_time', true );
			if ( $service_duration ) {
				$slot_duration = absint( $service_duration );
			}
		}

		$slots = array();

		// Convert times to minutes for easier calculation.
		$open_minutes  = $this->time_to_minutes( $hours->open_time );
		$close_minutes = $this->time_to_minutes( $hours->close_time );

		$break_start_minutes = $hours->break_start ? $this->time_to_minutes( $hours->break_start ) : null;
		$break_end_minutes   = $hours->break_end ? $this->time_to_minutes( $hours->break_end ) : null;

		$current = $open_minutes;

		while ( $current + $slot_duration <= $close_minutes ) {
			// Skip break time.
			if ( $break_start_minutes && $break_end_minutes ) {
				if ( $current >= $break_start_minutes && $current < $break_end_minutes ) {
					$current = $break_end_minutes;
					continue;
				}
				// Skip if slot would overlap with break.
				if ( $current < $break_start_minutes && $current + $slot_duration > $break_start_minutes ) {
					$current = $break_end_minutes;
					continue;
				}
			}

			$slots[] = array(
				'time'     => $this->minutes_to_time( $current ),
				'end_time' => $this->minutes_to_time( $current + $slot_duration ),
				'minutes'  => $current,
			);

			$current += $slot_duration;
		}

		return $slots;
	}

	/**
	 * Convert time string to minutes.
	 *
	 * @param string $time Time string (H:i:s or H:i).
	 * @return int
	 */
	private function time_to_minutes( $time ) {
		$parts = explode( ':', $time );
		return ( absint( $parts[0] ) * 60 ) + absint( $parts[1] ?? 0 );
	}

	/**
	 * Convert minutes to time string.
	 *
	 * @param int $minutes Minutes since midnight.
	 * @return string
	 */
	private function minutes_to_time( $minutes ) {
		$hours = floor( $minutes / 60 );
		$mins  = $minutes % 60;
		return sprintf( '%02d:%02d', $hours, $mins );
	}

	/**
	 * Get formatted hours for display.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $format      Time format.
	 * @return array
	 */
	public function get_formatted_hours( $location_id, $format = '' ) {
		if ( ! $format ) {
			$format = get_option( 'time_format', 'g:i a' );
		}

		$hours     = $this->get_for_location( $location_id );
		$formatted = array();

		foreach ( $hours as $hour ) {
			$day_name = $this->day_names[ $hour->day_of_week ] ?? '';

			if ( $hour->is_open ) {
				$open  = gmdate( $format, strtotime( $hour->open_time ) );
				$close = gmdate( $format, strtotime( $hour->close_time ) );

				$formatted[ $hour->day_of_week ] = array(
					'day'   => $day_name,
					'hours' => sprintf( '%s - %s', $open, $close ),
					'open'  => true,
				);

				if ( $hour->break_start && $hour->break_end ) {
					$formatted[ $hour->day_of_week ]['break'] = sprintf(
						'%s - %s',
						gmdate( $format, strtotime( $hour->break_start ) ),
						gmdate( $format, strtotime( $hour->break_end ) )
					);
				}
			} else {
				$formatted[ $hour->day_of_week ] = array(
					'day'   => $day_name,
					'hours' => __( 'Closed', 'bkx-multi-location' ),
					'open'  => false,
				);
			}
		}

		return $formatted;
	}

	/**
	 * Copy hours from one location to another.
	 *
	 * @param int $source_id      Source location ID.
	 * @param int $destination_id Destination location ID.
	 * @return bool
	 */
	public function copy_hours( $source_id, $destination_id ) {
		$source_hours = $this->get_for_location( $source_id );

		if ( empty( $source_hours ) ) {
			return false;
		}

		$hours_data = array();
		foreach ( $source_hours as $hour ) {
			$hours_data[ $hour->day_of_week ] = array(
				'is_open'     => $hour->is_open,
				'open_time'   => $hour->open_time,
				'close_time'  => $hour->close_time,
				'break_start' => $hour->break_start,
				'break_end'   => $hour->break_end,
			);
		}

		return $this->save( $destination_id, $hours_data );
	}
}
