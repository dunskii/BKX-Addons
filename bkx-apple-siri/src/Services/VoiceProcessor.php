<?php
/**
 * Voice Processor Service.
 *
 * Processes voice commands and natural language for bookings.
 *
 * @package BookingX\AppleSiri
 */

namespace BookingX\AppleSiri\Services;

defined( 'ABSPATH' ) || exit;

/**
 * VoiceProcessor class.
 */
class VoiceProcessor {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\AppleSiri\AppleSiriAddon
	 */
	private $addon;

	/**
	 * Date patterns for natural language parsing.
	 *
	 * @var array
	 */
	private $date_patterns = array(
		'today'            => '+0 days',
		'tomorrow'         => '+1 day',
		'day after tomorrow' => '+2 days',
		'next week'        => '+1 week',
		'next monday'      => 'next monday',
		'next tuesday'     => 'next tuesday',
		'next wednesday'   => 'next wednesday',
		'next thursday'    => 'next thursday',
		'next friday'      => 'next friday',
		'next saturday'    => 'next saturday',
		'next sunday'      => 'next sunday',
		'this monday'      => 'monday this week',
		'this friday'      => 'friday this week',
	);

	/**
	 * Time patterns for natural language parsing.
	 *
	 * @var array
	 */
	private $time_patterns = array(
		'morning'          => '09:00',
		'late morning'     => '11:00',
		'noon'             => '12:00',
		'afternoon'        => '14:00',
		'late afternoon'   => '16:00',
		'evening'          => '18:00',
	);

	/**
	 * Constructor.
	 *
	 * @param \BookingX\AppleSiri\AppleSiriAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Check availability via REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function check_availability( $request ) {
		$date       = $request->get_param( 'date' );
		$service_id = $request->get_param( 'service_id' );
		$staff_id   = $request->get_param( 'staff_id' );

		// Parse natural language date.
		$parsed_date = $this->parse_natural_date( $date );
		if ( ! $parsed_date ) {
			$parsed_date = gmdate( 'Y-m-d' );
		}

		// Get available slots.
		$slots = $this->get_available_slots( $service_id, $staff_id, $parsed_date );

		if ( empty( $slots ) ) {
			return new \WP_REST_Response(
				array(
					'available'  => false,
					'date'       => $parsed_date,
					'slots'      => array(),
					'message'    => $this->format_no_availability_message( $parsed_date ),
					'suggestion' => $this->get_next_available_date( $service_id, $staff_id, $parsed_date ),
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'available' => true,
				'date'      => $parsed_date,
				'slots'     => $slots,
				'message'   => $this->format_availability_message( $slots, $parsed_date ),
			),
			200
		);
	}

	/**
	 * Create booking via REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_booking( $request ) {
		$params = $request->get_json_params();

		$service_id = $params['service_id'] ?? $this->addon->get_setting( 'default_service_id', 0 );
		$staff_id   = $params['staff_id'] ?? 0;
		$date       = $params['date'] ?? '';
		$time       = $params['time'] ?? '';
		$customer   = $params['customer'] ?? array();

		// Parse natural language.
		$parsed_date = $this->parse_natural_date( $date );
		$parsed_time = $this->parse_natural_time( $time );

		if ( ! $parsed_date || ! $parsed_time ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'I couldn\'t understand the date or time. Please try again.', 'bkx-apple-siri' ),
				),
				400
			);
		}

		// Validate slot availability.
		if ( ! $this->is_slot_available( $service_id, $staff_id, $parsed_date, $parsed_time ) ) {
			$alternatives = $this->get_alternative_slots( $service_id, $staff_id, $parsed_date, $parsed_time );

			return new \WP_REST_Response(
				array(
					'success'      => false,
					'error'        => __( 'That time is not available.', 'bkx-apple-siri' ),
					'alternatives' => $alternatives,
					'message'      => $this->format_alternatives_message( $alternatives ),
				),
				200
			);
		}

		// Create the booking.
		$booking_data = array(
			'service_id'  => $service_id,
			'staff_id'    => $staff_id,
			'date'        => $parsed_date,
			'time'        => $parsed_time,
			'customer'    => $customer,
			'source'      => 'apple_siri',
			'status'      => 'bkx-pending',
		);

		$booking_id = $this->create_bkx_booking( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => $booking_id->get_error_message(),
				),
				500
			);
		}

		// Get booking details for response.
		$booking = $this->get_booking_details( $booking_id );

		// Add to calendar if enabled.
		if ( $this->addon->get_setting( 'send_booking_to_reminders', true ) ) {
			$calendar_event = $this->format_calendar_event( $booking );
		}

		return new \WP_REST_Response(
			array(
				'success'        => true,
				'booking_id'     => $booking_id,
				'booking'        => $booking,
				'message'        => $this->format_confirmation_message( $booking ),
				'calendar_event' => $calendar_event ?? null,
			),
			200
		);
	}

	/**
	 * Parse natural language date.
	 *
	 * @param string $input Date input.
	 * @return string|null Formatted date or null.
	 */
	public function parse_natural_date( $input ) {
		if ( empty( $input ) ) {
			return null;
		}

		$input = strtolower( trim( $input ) );

		// Check for known patterns.
		foreach ( $this->date_patterns as $pattern => $modifier ) {
			if ( strpos( $input, $pattern ) !== false ) {
				return gmdate( 'Y-m-d', strtotime( $modifier ) );
			}
		}

		// Try strtotime directly.
		$timestamp = strtotime( $input );
		if ( $timestamp !== false && $timestamp >= time() ) {
			return gmdate( 'Y-m-d', $timestamp );
		}

		// Try parsing common formats.
		$formats = array(
			'F j',       // January 15.
			'F jS',      // January 15th.
			'm/d',       // 1/15.
			'm-d',       // 1-15.
			'Y-m-d',     // 2025-01-15.
			'm/d/Y',     // 1/15/2025.
		);

		foreach ( $formats as $format ) {
			$date = \DateTime::createFromFormat( $format, $input );
			if ( $date ) {
				// Set year if not provided.
				if ( strpos( $format, 'Y' ) === false ) {
					$date->setDate( (int) gmdate( 'Y' ), (int) $date->format( 'm' ), (int) $date->format( 'd' ) );
					// If date is in past, assume next year.
					if ( $date->getTimestamp() < time() ) {
						$date->modify( '+1 year' );
					}
				}
				return $date->format( 'Y-m-d' );
			}
		}

		return null;
	}

	/**
	 * Parse natural language time.
	 *
	 * @param string $input Time input.
	 * @return string|null Formatted time (H:i) or null.
	 */
	public function parse_natural_time( $input ) {
		if ( empty( $input ) ) {
			return null;
		}

		$input = strtolower( trim( $input ) );

		// Check for known patterns.
		foreach ( $this->time_patterns as $pattern => $time ) {
			if ( strpos( $input, $pattern ) !== false ) {
				return $time;
			}
		}

		// Try strtotime directly.
		$timestamp = strtotime( $input );
		if ( $timestamp !== false ) {
			return gmdate( 'H:i', $timestamp );
		}

		// Try common time formats.
		$formats = array(
			'g:i a',     // 9:00 am.
			'g:ia',      // 9:00am.
			'ga',        // 9am.
			'g a',       // 9 am.
			'H:i',       // 09:00.
			'Hi',        // 0900.
		);

		foreach ( $formats as $format ) {
			$time = \DateTime::createFromFormat( $format, $input );
			if ( $time ) {
				return $time->format( 'H:i' );
			}
		}

		return null;
	}

	/**
	 * Get available slots for a date.
	 *
	 * @param int    $service_id Service ID.
	 * @param int    $staff_id   Staff ID.
	 * @param string $date       Date.
	 * @return array
	 */
	private function get_available_slots( $service_id, $staff_id, $date ) {
		// This would integrate with BookingX availability system.
		// Placeholder implementation.
		$slots = array();

		$start_hour = 9;
		$end_hour   = 17;
		$interval   = 60; // Minutes.

		for ( $hour = $start_hour; $hour < $end_hour; $hour++ ) {
			$time = sprintf( '%02d:00', $hour );
			$slots[] = array(
				'time'       => $time,
				'time_label' => gmdate( 'g:i A', strtotime( $time ) ),
				'available'  => true,
			);
		}

		return $slots;
	}

	/**
	 * Check if specific slot is available.
	 *
	 * @param int    $service_id Service ID.
	 * @param int    $staff_id   Staff ID.
	 * @param string $date       Date.
	 * @param string $time       Time.
	 * @return bool
	 */
	private function is_slot_available( $service_id, $staff_id, $date, $time ) {
		// This would integrate with BookingX availability system.
		return true;
	}

	/**
	 * Get alternative slots.
	 *
	 * @param int    $service_id Service ID.
	 * @param int    $staff_id   Staff ID.
	 * @param string $date       Date.
	 * @param string $time       Time.
	 * @return array
	 */
	private function get_alternative_slots( $service_id, $staff_id, $date, $time ) {
		$slots    = $this->get_available_slots( $service_id, $staff_id, $date );
		$time_int = (int) str_replace( ':', '', $time );

		// Sort by proximity to requested time.
		usort(
			$slots,
			function ( $a, $b ) use ( $time_int ) {
				$a_diff = abs( (int) str_replace( ':', '', $a['time'] ) - $time_int );
				$b_diff = abs( (int) str_replace( ':', '', $b['time'] ) - $time_int );
				return $a_diff - $b_diff;
			}
		);

		return array_slice( $slots, 0, 3 );
	}

	/**
	 * Get next available date.
	 *
	 * @param int    $service_id Service ID.
	 * @param int    $staff_id   Staff ID.
	 * @param string $date       Starting date.
	 * @return array|null
	 */
	private function get_next_available_date( $service_id, $staff_id, $date ) {
		$check_date = new \DateTime( $date );

		for ( $i = 1; $i <= 14; $i++ ) {
			$check_date->modify( '+1 day' );
			$slots = $this->get_available_slots( $service_id, $staff_id, $check_date->format( 'Y-m-d' ) );

			if ( ! empty( $slots ) ) {
				return array(
					'date'       => $check_date->format( 'Y-m-d' ),
					'date_label' => $check_date->format( 'l, F j' ),
					'slots'      => $slots,
				);
			}
		}

		return null;
	}

	/**
	 * Create BookingX booking.
	 *
	 * @param array $data Booking data.
	 * @return int|\WP_Error Booking ID or error.
	 */
	private function create_bkx_booking( $data ) {
		// This would integrate with BookingX booking creation.
		// Placeholder implementation.
		$booking_id = wp_insert_post(
			array(
				'post_type'   => 'bkx_booking',
				'post_status' => 'publish',
				'post_title'  => sprintf(
					'Siri Booking - %s %s',
					$data['date'],
					$data['time']
				),
				'meta_input'  => array(
					'booking_date'  => $data['date'],
					'booking_time'  => $data['time'],
					'service_id'    => $data['service_id'],
					'seat_id'       => $data['staff_id'],
					'booking_source' => 'apple_siri',
				),
			)
		);

		if ( ! is_wp_error( $booking_id ) ) {
			// Trigger booking created action.
			do_action( 'bkx_booking_created', $booking_id, $data );
		}

		return $booking_id;
	}

	/**
	 * Get booking details.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	private function get_booking_details( $booking_id ) {
		$post = get_post( $booking_id );
		if ( ! $post ) {
			return array();
		}

		$service_id = get_post_meta( $booking_id, 'service_id', true );
		$staff_id   = get_post_meta( $booking_id, 'seat_id', true );
		$date       = get_post_meta( $booking_id, 'booking_date', true );
		$time       = get_post_meta( $booking_id, 'booking_time', true );

		return array(
			'id'           => $booking_id,
			'service_name' => $service_id ? get_the_title( $service_id ) : '',
			'staff_name'   => $staff_id ? get_the_title( $staff_id ) : '',
			'date'         => $date,
			'date_label'   => gmdate( 'l, F j, Y', strtotime( $date ) ),
			'time'         => $time,
			'time_label'   => gmdate( 'g:i A', strtotime( $time ) ),
			'status'       => $post->post_status,
		);
	}

	/**
	 * Format calendar event for iOS.
	 *
	 * @param array $booking Booking details.
	 * @return array
	 */
	private function format_calendar_event( $booking ) {
		$start = new \DateTime( $booking['date'] . ' ' . $booking['time'] );
		$end   = clone $start;
		$end->modify( '+1 hour' ); // Default duration.

		return array(
			'title'    => sprintf(
				/* translators: %s: Service name */
				__( '%s Appointment', 'bkx-apple-siri' ),
				$booking['service_name']
			),
			'location' => get_bloginfo( 'name' ),
			'start'    => $start->format( 'Y-m-d\TH:i:s' ),
			'end'      => $end->format( 'Y-m-d\TH:i:s' ),
			'notes'    => sprintf(
				/* translators: 1: Booking ID */
				__( 'Booking ID: #%1$d', 'bkx-apple-siri' ),
				$booking['id']
			),
		);
	}

	/**
	 * Format no availability message.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	private function format_no_availability_message( $date ) {
		return sprintf(
			/* translators: %s: Date */
			__( 'I\'m sorry, there are no available times on %s.', 'bkx-apple-siri' ),
			gmdate( 'l, F j', strtotime( $date ) )
		);
	}

	/**
	 * Format availability message.
	 *
	 * @param array  $slots Available slots.
	 * @param string $date  Date.
	 * @return string
	 */
	private function format_availability_message( $slots, $date ) {
		$count = count( $slots );

		return sprintf(
			/* translators: 1: Count, 2: Date */
			_n(
				'There is %1$d available time on %2$s.',
				'There are %1$d available times on %2$s.',
				$count,
				'bkx-apple-siri'
			),
			$count,
			gmdate( 'l, F j', strtotime( $date ) )
		);
	}

	/**
	 * Format alternatives message.
	 *
	 * @param array $alternatives Alternative slots.
	 * @return string
	 */
	private function format_alternatives_message( $alternatives ) {
		if ( empty( $alternatives ) ) {
			return __( 'Unfortunately, there are no available times nearby.', 'bkx-apple-siri' );
		}

		$times = array_map(
			function ( $slot ) {
				return $slot['time_label'];
			},
			$alternatives
		);

		return sprintf(
			/* translators: %s: List of times */
			__( 'The closest available times are: %s', 'bkx-apple-siri' ),
			implode( ', ', $times )
		);
	}

	/**
	 * Format confirmation message.
	 *
	 * @param array $booking Booking details.
	 * @return string
	 */
	private function format_confirmation_message( $booking ) {
		if ( ! empty( $booking['staff_name'] ) ) {
			return sprintf(
				/* translators: 1: Service, 2: Staff, 3: Date, 4: Time */
				__( 'Your %1$s appointment with %2$s is confirmed for %3$s at %4$s.', 'bkx-apple-siri' ),
				$booking['service_name'],
				$booking['staff_name'],
				$booking['date_label'],
				$booking['time_label']
			);
		}

		return sprintf(
			/* translators: 1: Service, 2: Date, 3: Time */
			__( 'Your %1$s appointment is confirmed for %2$s at %3$s.', 'bkx-apple-siri' ),
			$booking['service_name'],
			$booking['date_label'],
			$booking['time_label']
		);
	}
}
