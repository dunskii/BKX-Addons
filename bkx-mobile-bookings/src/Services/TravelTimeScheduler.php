<?php
/**
 * Travel Time Scheduler Service.
 *
 * @package BookingX\MobileBookings\Services
 * @since   1.0.0
 */

namespace BookingX\MobileBookings\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TravelTimeScheduler Class.
 */
class TravelTimeScheduler {

	/**
	 * Distance calculator.
	 *
	 * @var DistanceCalculator
	 */
	private $distance_calculator;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param DistanceCalculator $distance_calculator Distance calculator.
	 * @param array              $settings            Plugin settings.
	 */
	public function __construct( DistanceCalculator $distance_calculator, $settings ) {
		$this->distance_calculator = $distance_calculator;
		$this->settings            = $settings;
	}

	/**
	 * Filter available slots based on travel time from previous booking.
	 *
	 * @param array  $slots       Available slots.
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date (Y-m-d).
	 * @return array
	 */
	public function filter_slots_by_travel( $slots, $provider_id, $date ) {
		if ( empty( $this->settings['add_travel_buffer'] ) ) {
			return $slots;
		}

		// Get provider's bookings for the date.
		$bookings = $this->get_provider_bookings( $provider_id, $date );

		if ( empty( $bookings ) ) {
			return $slots;
		}

		$filtered_slots = array();

		foreach ( $slots as $slot ) {
			if ( $this->is_slot_feasible( $slot, $bookings, $provider_id ) ) {
				$filtered_slots[] = $slot;
			}
		}

		return $filtered_slots;
	}

	/**
	 * Check if a slot is feasible considering travel time.
	 *
	 * @param array $slot       Slot data.
	 * @param array $bookings   Existing bookings.
	 * @param int   $provider_id Provider ID.
	 * @return bool
	 */
	private function is_slot_feasible( $slot, $bookings, $provider_id ) {
		$slot_start = strtotime( $slot['start_time'] );
		$slot_end   = strtotime( $slot['end_time'] );

		foreach ( $bookings as $booking ) {
			$booking_start = strtotime( $booking['start_time'] );
			$booking_end   = strtotime( $booking['end_time'] );

			// Check if there's a booking right before this slot.
			if ( $booking_end <= $slot_start ) {
				$gap_minutes   = ( $slot_start - $booking_end ) / 60;
				$travel_needed = $this->estimate_travel_time_between( $booking, $slot );

				if ( $travel_needed > $gap_minutes ) {
					return false;
				}
			}

			// Check if there's a booking right after this slot.
			if ( $slot_end <= $booking_start ) {
				$gap_minutes   = ( $booking_start - $slot_end ) / 60;
				$travel_needed = $this->estimate_travel_time_between( $slot, $booking );

				if ( $travel_needed > $gap_minutes ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Estimate travel time between two locations.
	 *
	 * @param array $from From location data.
	 * @param array $to   To location data.
	 * @return int Travel time in minutes.
	 */
	private function estimate_travel_time_between( $from, $to ) {
		// If we have coordinates, calculate actual travel time.
		if ( ! empty( $from['lat'] ) && ! empty( $from['lng'] ) &&
			! empty( $to['lat'] ) && ! empty( $to['lng'] ) ) {

			$result = $this->distance_calculator->calculate_by_coordinates(
				$from['lat'],
				$from['lng'],
				$to['lat'],
				$to['lng']
			);

			if ( ! is_wp_error( $result ) ) {
				$base_duration = $result['duration_in_traffic_minutes'] ?? $result['duration_minutes'];
				return $this->distance_calculator->get_buffered_travel_time( $base_duration );
			}
		}

		// Default buffer if we can't calculate.
		return absint( $this->settings['min_buffer_minutes'] ?? 15 );
	}

	/**
	 * Calculate schedule with travel time between appointments.
	 *
	 * @param array  $bookings    Array of bookings with locations.
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date.
	 * @return array
	 */
	public function calculate_schedule_with_travel( $bookings, $provider_id, $date ) {
		if ( empty( $bookings ) ) {
			return array();
		}

		// Sort bookings by start time.
		usort(
			$bookings,
			function ( $a, $b ) {
				return strtotime( $a['start_time'] ) - strtotime( $b['start_time'] );
			}
		);

		$schedule        = array();
		$provider_home   = $this->get_provider_home_location( $provider_id );
		$previous_end    = null;
		$previous_location = $provider_home;

		foreach ( $bookings as $index => $booking ) {
			$booking_start = strtotime( $booking['start_time'] );

			// Calculate travel time from previous location.
			$travel_time = 0;
			$travel_info = null;

			if ( $previous_location && ! empty( $booking['lat'] ) && ! empty( $booking['lng'] ) ) {
				$result = $this->distance_calculator->calculate_by_coordinates(
					$previous_location['lat'],
					$previous_location['lng'],
					$booking['lat'],
					$booking['lng']
				);

				if ( ! is_wp_error( $result ) ) {
					$travel_time = $this->distance_calculator->get_buffered_travel_time(
						$result['duration_in_traffic_minutes'] ?? $result['duration_minutes']
					);

					$travel_info = array(
						'duration_minutes' => $travel_time,
						'duration_text'    => $this->distance_calculator->format_duration( $travel_time ),
						'distance_miles'   => $result['distance_miles'],
						'distance_text'    => $result['distance_text'],
					);
				}
			}

			// Calculate required departure time.
			$depart_time = $booking_start - ( $travel_time * 60 );

			// Check if there's a conflict.
			$has_conflict = false;
			if ( $previous_end && $depart_time < $previous_end ) {
				$has_conflict = true;
			}

			$schedule[] = array(
				'booking_id'     => $booking['booking_id'] ?? $booking['id'],
				'start_time'     => $booking['start_time'],
				'end_time'       => $booking['end_time'],
				'location'       => array(
					'address' => $booking['address'] ?? '',
					'lat'     => $booking['lat'] ?? null,
					'lng'     => $booking['lng'] ?? null,
				),
				'travel_from'    => $travel_info,
				'depart_time'    => gmdate( 'H:i', $depart_time ),
				'has_conflict'   => $has_conflict,
				'order'          => $index + 1,
			);

			$previous_end      = strtotime( $booking['end_time'] );
			$previous_location = array(
				'lat' => $booking['lat'] ?? null,
				'lng' => $booking['lng'] ?? null,
			);
		}

		// Add return trip to home if configured.
		if ( ! empty( $this->settings['return_to_provider_home'] ) && $provider_home ) {
			$last_booking = end( $bookings );

			if ( ! empty( $last_booking['lat'] ) && ! empty( $last_booking['lng'] ) ) {
				$result = $this->distance_calculator->calculate_by_coordinates(
					$last_booking['lat'],
					$last_booking['lng'],
					$provider_home['lat'],
					$provider_home['lng']
				);

				if ( ! is_wp_error( $result ) ) {
					$schedule['return_trip'] = array(
						'from'             => $last_booking['address'] ?? '',
						'to'               => $provider_home['address'] ?? 'Home',
						'duration_minutes' => $result['duration_minutes'],
						'duration_text'    => $result['duration_text'],
						'distance_miles'   => $result['distance_miles'],
						'distance_text'    => $result['distance_text'],
					);
				}
			}
		}

		return $schedule;
	}

	/**
	 * Get next available slot considering travel time.
	 *
	 * @param int    $service_id  Service ID.
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date.
	 * @param array  $location    Customer location (lat, lng).
	 * @return array|null
	 */
	public function get_next_available_slot( $service_id, $provider_id, $date, $location ) {
		// Get all available slots for the provider.
		$slots = apply_filters( 'bkx_get_available_slots', array(), $service_id, $provider_id, $date );

		if ( empty( $slots ) ) {
			return null;
		}

		// Filter by travel time.
		$filtered = $this->filter_slots_by_travel( $slots, $provider_id, $date );

		if ( empty( $filtered ) ) {
			return null;
		}

		// Return the first available slot.
		return $filtered[0];
	}

	/**
	 * Validate schedule feasibility.
	 *
	 * @param array  $bookings    Bookings to validate.
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date.
	 * @return array Validation result.
	 */
	public function validate_schedule_feasibility( $bookings, $provider_id, $date ) {
		$schedule = $this->calculate_schedule_with_travel( $bookings, $provider_id, $date );
		$issues   = array();

		foreach ( $schedule as $item ) {
			if ( is_array( $item ) && ! empty( $item['has_conflict'] ) ) {
				$issues[] = array(
					'booking_id' => $item['booking_id'],
					'message'    => sprintf(
						/* translators: %s: time */
						__( 'Insufficient travel time before appointment at %s', 'bkx-mobile-bookings' ),
						$item['start_time']
					),
				);
			}
		}

		return array(
			'is_feasible' => empty( $issues ),
			'issues'      => $issues,
			'schedule'    => $schedule,
		);
	}

	/**
	 * Get provider's bookings for a date.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date (Y-m-d).
	 * @return array
	 */
	private function get_provider_bookings( $provider_id, $date ) {
		global $wpdb;

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as booking_id, pm1.meta_value as start_time, pm2.meta_value as end_time,
				        l.latitude as lat, l.longitude as lng, l.address
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'booking_start_time'
				LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'booking_end_time'
				LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->prefix}bkx_locations l ON p.ID = l.booking_id
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-pending', 'bkx-ack')
				AND pm3.meta_value = %d
				AND DATE(pm1.meta_value) = %s
				ORDER BY pm1.meta_value ASC",
				$provider_id,
				$date
			),
			ARRAY_A
		);

		return $bookings ?: array();
	}

	/**
	 * Get provider's home location.
	 *
	 * @param int $provider_id Provider ID.
	 * @return array|null
	 */
	private function get_provider_home_location( $provider_id ) {
		global $wpdb;

		$location = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT latitude as lat, longitude as lng, address
				FROM {$wpdb->prefix}bkx_locations
				WHERE provider_id = %d AND location_type = 'provider'
				ORDER BY created_at DESC
				LIMIT 1",
				$provider_id
			),
			ARRAY_A
		);

		return $location;
	}
}
