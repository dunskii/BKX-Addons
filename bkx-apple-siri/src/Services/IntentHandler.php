<?php
/**
 * Intent Handler Service.
 *
 * Handles SiriKit Intents for booking operations.
 *
 * @package BookingX\AppleSiri
 */

namespace BookingX\AppleSiri\Services;

defined( 'ABSPATH' ) || exit;

/**
 * IntentHandler class.
 */
class IntentHandler {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\AppleSiri\AppleSiriAddon
	 */
	private $addon;

	/**
	 * Supported intent types.
	 *
	 * @var array
	 */
	private $supported_intents = array(
		'INBookRestaurantReservationIntent',
		'INGetAvailableRestaurantReservationBookingsIntent',
		'INCreateTaskListIntent',
		'INAddTasksIntent',
		'INSearchForMessagesIntent',
		// Custom intents.
		'BookAppointmentIntent',
		'RescheduleAppointmentIntent',
		'CancelAppointmentIntent',
		'CheckAvailabilityIntent',
		'GetUpcomingAppointmentsIntent',
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
	 * Handle incoming intent from Siri.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_intent( $request ) {
		$body = $request->get_json_params();

		if ( empty( $body['intent'] ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'Missing intent data',
				),
				400
			);
		}

		$intent_type = $body['intent']['type'] ?? '';
		$intent_data = $body['intent']['data'] ?? array();
		$user_id     = $body['user_id'] ?? null;

		// Log request if enabled.
		$this->log_intent( $intent_type, $intent_data );

		// Route to appropriate handler.
		switch ( $intent_type ) {
			case 'BookAppointmentIntent':
			case 'INBookRestaurantReservationIntent':
				return $this->handle_book_intent( $intent_data, $user_id );

			case 'RescheduleAppointmentIntent':
				return $this->handle_reschedule_intent( $intent_data, $user_id );

			case 'CancelAppointmentIntent':
				return $this->handle_cancel_intent( $intent_data, $user_id );

			case 'CheckAvailabilityIntent':
			case 'INGetAvailableRestaurantReservationBookingsIntent':
				return $this->handle_availability_intent( $intent_data );

			case 'GetUpcomingAppointmentsIntent':
				return $this->handle_upcoming_intent( $user_id );

			default:
				return new \WP_REST_Response(
					array(
						'success' => false,
						'error'   => 'Unsupported intent type: ' . $intent_type,
					),
					400
				);
		}
	}

	/**
	 * Handle booking intent.
	 *
	 * @param array  $data    Intent data.
	 * @param string $user_id User ID.
	 * @return \WP_REST_Response
	 */
	private function handle_book_intent( array $data, $user_id ) {
		$service_name = $data['service'] ?? '';
		$date         = $data['date'] ?? '';
		$time         = $data['time'] ?? '';
		$staff_name   = $data['staff'] ?? '';

		// Resolve service.
		$service_id = $this->resolve_service( $service_name );
		if ( ! $service_id ) {
			return $this->create_dialog_response(
				'service_disambiguation',
				__( 'Which service would you like to book?', 'bkx-apple-siri' ),
				$this->get_available_services()
			);
		}

		// Resolve date.
		$booking_date = $this->parse_date( $date );
		if ( ! $booking_date ) {
			return $this->create_dialog_response(
				'date_selection',
				__( 'When would you like to book?', 'bkx-apple-siri' ),
				$this->get_available_dates( $service_id )
			);
		}

		// Resolve time.
		$booking_time = $this->parse_time( $time );
		if ( ! $booking_time ) {
			$available_times = $this->get_available_times( $service_id, $booking_date );
			return $this->create_dialog_response(
				'time_selection',
				__( 'What time works for you?', 'bkx-apple-siri' ),
				$available_times
			);
		}

		// Resolve staff.
		$staff_id = $this->resolve_staff( $staff_name, $service_id );

		// Check if confirmation is required.
		if ( $this->addon->get_setting( 'require_confirmation', true ) ) {
			return $this->create_confirmation_response( $service_id, $booking_date, $booking_time, $staff_id );
		}

		// Create booking.
		$booking = $this->create_booking( $service_id, $booking_date, $booking_time, $staff_id, $user_id );

		if ( is_wp_error( $booking ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => $booking->get_error_message(),
					'code'    => 'INIntentHandlingStatusFailure',
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'booking_id' => $booking['id'],
				'message'    => sprintf(
					/* translators: 1: Service name, 2: Date, 3: Time */
					__( 'Your %1$s appointment is confirmed for %2$s at %3$s.', 'bkx-apple-siri' ),
					$booking['service_name'],
					$booking['date'],
					$booking['time']
				),
				'code'       => 'INIntentHandlingStatusSuccess',
				'data'       => $booking,
			),
			200
		);
	}

	/**
	 * Handle reschedule intent.
	 *
	 * @param array  $data    Intent data.
	 * @param string $user_id User ID.
	 * @return \WP_REST_Response
	 */
	private function handle_reschedule_intent( array $data, $user_id ) {
		$booking_id  = $data['booking_id'] ?? 0;
		$new_date    = $data['new_date'] ?? '';
		$new_time    = $data['new_time'] ?? '';

		if ( ! $booking_id ) {
			// Find user's upcoming booking.
			$bookings = $this->get_user_bookings( $user_id, 'upcoming' );
			if ( empty( $bookings ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'You don\'t have any upcoming appointments to reschedule.', 'bkx-apple-siri' ),
						'code'    => 'INIntentHandlingStatusFailure',
					),
					200
				);
			}

			if ( count( $bookings ) === 1 ) {
				$booking_id = $bookings[0]['id'];
			} else {
				return $this->create_dialog_response(
					'booking_selection',
					__( 'Which appointment would you like to reschedule?', 'bkx-apple-siri' ),
					$bookings
				);
			}
		}

		// Get new date/time.
		$booking_date = $this->parse_date( $new_date );
		$booking_time = $this->parse_time( $new_time );

		if ( ! $booking_date || ! $booking_time ) {
			return $this->create_dialog_response(
				'datetime_selection',
				__( 'When would you like to reschedule to?', 'bkx-apple-siri' ),
				array()
			);
		}

		// Reschedule booking.
		$result = $this->reschedule_booking( $booking_id, $booking_date, $booking_time );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
					'code'    => 'INIntentHandlingStatusFailure',
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf(
					/* translators: 1: Date, 2: Time */
					__( 'Your appointment has been rescheduled to %1$s at %2$s.', 'bkx-apple-siri' ),
					$booking_date,
					$booking_time
				),
				'code'    => 'INIntentHandlingStatusSuccess',
			),
			200
		);
	}

	/**
	 * Handle cancel intent.
	 *
	 * @param array  $data    Intent data.
	 * @param string $user_id User ID.
	 * @return \WP_REST_Response
	 */
	private function handle_cancel_intent( array $data, $user_id ) {
		$booking_id = $data['booking_id'] ?? 0;

		if ( ! $booking_id ) {
			$bookings = $this->get_user_bookings( $user_id, 'upcoming' );
			if ( empty( $bookings ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'You don\'t have any upcoming appointments to cancel.', 'bkx-apple-siri' ),
						'code'    => 'INIntentHandlingStatusFailure',
					),
					200
				);
			}

			if ( count( $bookings ) === 1 ) {
				$booking_id = $bookings[0]['id'];
			} else {
				return $this->create_dialog_response(
					'booking_selection',
					__( 'Which appointment would you like to cancel?', 'bkx-apple-siri' ),
					$bookings
				);
			}
		}

		// Cancel booking.
		$result = $this->cancel_booking( $booking_id );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
					'code'    => 'INIntentHandlingStatusFailure',
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Your appointment has been cancelled.', 'bkx-apple-siri' ),
				'code'    => 'INIntentHandlingStatusSuccess',
			),
			200
		);
	}

	/**
	 * Handle availability intent.
	 *
	 * @param array $data Intent data.
	 * @return \WP_REST_Response
	 */
	private function handle_availability_intent( array $data ) {
		$service_name = $data['service'] ?? '';
		$date         = $data['date'] ?? '';

		$service_id = $this->resolve_service( $service_name );
		if ( ! $service_id ) {
			$service_id = $this->addon->get_setting( 'default_service_id', 0 );
		}

		$booking_date = $this->parse_date( $date );
		if ( ! $booking_date ) {
			$booking_date = gmdate( 'Y-m-d' ); // Today.
		}

		$available_times = $this->get_available_times( $service_id, $booking_date );

		if ( empty( $available_times ) ) {
			return new \WP_REST_Response(
				array(
					'success'   => true,
					'available' => false,
					'message'   => sprintf(
						/* translators: %s: Date */
						__( 'Sorry, there are no available times on %s.', 'bkx-apple-siri' ),
						$booking_date
					),
					'code'      => 'INIntentHandlingStatusSuccess',
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'success'   => true,
				'available' => true,
				'slots'     => $available_times,
				'message'   => sprintf(
					/* translators: 1: Count, 2: Date */
					__( 'We have %1$d available times on %2$s.', 'bkx-apple-siri' ),
					count( $available_times ),
					$booking_date
				),
				'code'      => 'INIntentHandlingStatusSuccess',
			),
			200
		);
	}

	/**
	 * Handle upcoming appointments intent.
	 *
	 * @param string $user_id User ID.
	 * @return \WP_REST_Response
	 */
	private function handle_upcoming_intent( $user_id ) {
		$bookings = $this->get_user_bookings( $user_id, 'upcoming' );

		if ( empty( $bookings ) ) {
			return new \WP_REST_Response(
				array(
					'success'  => true,
					'bookings' => array(),
					'message'  => __( 'You don\'t have any upcoming appointments.', 'bkx-apple-siri' ),
					'code'     => 'INIntentHandlingStatusSuccess',
				),
				200
			);
		}

		$message = sprintf(
			/* translators: %d: Number of appointments */
			_n(
				'You have %d upcoming appointment.',
				'You have %d upcoming appointments.',
				count( $bookings ),
				'bkx-apple-siri'
			),
			count( $bookings )
		);

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'bookings' => $bookings,
				'message'  => $message,
				'code'     => 'INIntentHandlingStatusSuccess',
			),
			200
		);
	}

	/**
	 * Create dialog response for disambiguation.
	 *
	 * @param string $parameter Parameter needing clarification.
	 * @param string $prompt    Prompt to show user.
	 * @param array  $options   Available options.
	 * @return \WP_REST_Response
	 */
	private function create_dialog_response( $parameter, $prompt, array $options ) {
		return new \WP_REST_Response(
			array(
				'success'             => true,
				'code'                => 'INIntentHandlingStatusDeferredToApplication',
				'needsDisambiguation' => true,
				'parameter'           => $parameter,
				'prompt'              => $prompt,
				'options'             => $options,
			),
			200
		);
	}

	/**
	 * Create confirmation response.
	 *
	 * @param int    $service_id   Service ID.
	 * @param string $date         Booking date.
	 * @param string $time         Booking time.
	 * @param int    $staff_id     Staff ID.
	 * @return \WP_REST_Response
	 */
	private function create_confirmation_response( $service_id, $date, $time, $staff_id ) {
		$service = get_post( $service_id );
		$staff   = $staff_id ? get_post( $staff_id ) : null;

		$message = sprintf(
			/* translators: 1: Service name, 2: Date, 3: Time */
			__( 'I\'ll book %1$s for %2$s at %3$s.', 'bkx-apple-siri' ),
			$service ? $service->post_title : __( 'the service', 'bkx-apple-siri' ),
			$date,
			$time
		);

		if ( $staff ) {
			/* translators: 1: Service, 2: Staff, 3: Date, 4: Time */
			$message = sprintf(
				__( 'I\'ll book %1$s with %2$s for %3$s at %4$s.', 'bkx-apple-siri' ),
				$service->post_title,
				$staff->post_title,
				$date,
				$time
			);
		}

		return new \WP_REST_Response(
			array(
				'success'              => true,
				'code'                 => 'INIntentHandlingStatusReady',
				'needsConfirmation'    => true,
				'confirmationPrompt'   => $message . ' ' . __( 'Should I go ahead and book it?', 'bkx-apple-siri' ),
				'pendingBooking'       => array(
					'service_id' => $service_id,
					'date'       => $date,
					'time'       => $time,
					'staff_id'   => $staff_id,
				),
			),
			200
		);
	}

	/**
	 * Resolve service from name.
	 *
	 * @param string $name Service name.
	 * @return int|null Service ID or null.
	 */
	private function resolve_service( $name ) {
		if ( empty( $name ) ) {
			return null;
		}

		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => 1,
				's'              => $name,
				'post_status'    => 'publish',
			)
		);

		return ! empty( $services ) ? $services[0]->ID : null;
	}

	/**
	 * Resolve staff from name.
	 *
	 * @param string $name       Staff name.
	 * @param int    $service_id Service ID.
	 * @return int|null Staff ID or null.
	 */
	private function resolve_staff( $name, $service_id ) {
		if ( empty( $name ) ) {
			return null;
		}

		$staff = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'posts_per_page' => 1,
				's'              => $name,
				'post_status'    => 'publish',
			)
		);

		return ! empty( $staff ) ? $staff[0]->ID : null;
	}

	/**
	 * Parse date from natural language.
	 *
	 * @param string $date Date string.
	 * @return string|null Formatted date or null.
	 */
	private function parse_date( $date ) {
		if ( empty( $date ) ) {
			return null;
		}

		$timestamp = strtotime( $date );
		if ( $timestamp === false ) {
			return null;
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Parse time from natural language.
	 *
	 * @param string $time Time string.
	 * @return string|null Formatted time or null.
	 */
	private function parse_time( $time ) {
		if ( empty( $time ) ) {
			return null;
		}

		$timestamp = strtotime( $time );
		if ( $timestamp === false ) {
			return null;
		}

		return gmdate( 'H:i', $timestamp );
	}

	/**
	 * Get available services.
	 *
	 * @return array
	 */
	private function get_available_services() {
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => 10,
				'post_status'    => 'publish',
			)
		);

		return array_map(
			function ( $service ) {
				return array(
					'id'    => $service->ID,
					'title' => $service->post_title,
				);
			},
			$services
		);
	}

	/**
	 * Get available dates for service.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	private function get_available_dates( $service_id ) {
		// Return next 7 available days.
		$dates = array();
		$date  = new \DateTime();

		for ( $i = 0; $i < 14 && count( $dates ) < 7; $i++ ) {
			$dates[] = array(
				'date'  => $date->format( 'Y-m-d' ),
				'label' => $date->format( 'l, F j' ),
			);
			$date->modify( '+1 day' );
		}

		return $dates;
	}

	/**
	 * Get available times for service and date.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @return array
	 */
	private function get_available_times( $service_id, $date ) {
		// This would integrate with BookingX availability system.
		// Placeholder implementation.
		$times = array();
		for ( $hour = 9; $hour <= 17; $hour++ ) {
			$times[] = array(
				'time'  => sprintf( '%02d:00', $hour ),
				'label' => gmdate( 'g:i A', strtotime( sprintf( '%02d:00', $hour ) ) ),
			);
		}

		return $times;
	}

	/**
	 * Get user bookings.
	 *
	 * @param string $user_id User ID.
	 * @param string $type    Type: 'upcoming', 'past', 'all'.
	 * @return array
	 */
	private function get_user_bookings( $user_id, $type = 'upcoming' ) {
		// This would integrate with BookingX booking system.
		return array();
	}

	/**
	 * Create booking.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @param string $time       Time.
	 * @param int    $staff_id   Staff ID.
	 * @param string $user_id    User ID.
	 * @return array|\WP_Error
	 */
	private function create_booking( $service_id, $date, $time, $staff_id, $user_id ) {
		// This would integrate with BookingX booking creation.
		return array(
			'id'           => wp_rand( 1000, 9999 ),
			'service_name' => get_the_title( $service_id ),
			'date'         => $date,
			'time'         => $time,
		);
	}

	/**
	 * Reschedule booking.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_date   New date.
	 * @param string $new_time   New time.
	 * @return bool|\WP_Error
	 */
	private function reschedule_booking( $booking_id, $new_date, $new_time ) {
		// This would integrate with BookingX booking system.
		return true;
	}

	/**
	 * Cancel booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool|\WP_Error
	 */
	private function cancel_booking( $booking_id ) {
		// This would integrate with BookingX booking system.
		return true;
	}

	/**
	 * Log intent for debugging.
	 *
	 * @param string $intent_type Intent type.
	 * @param array  $intent_data Intent data.
	 */
	private function log_intent( $intent_type, array $intent_data ) {
		if ( ! $this->addon->get_setting( 'log_requests', false ) ) {
			return;
		}

		$log = array(
			'timestamp'   => current_time( 'mysql' ),
			'intent_type' => $intent_type,
			'data'        => $intent_data,
		);

		$logs   = get_option( 'bkx_apple_siri_logs', array() );
		$logs[] = $log;

		// Keep last 100 logs.
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		update_option( 'bkx_apple_siri_logs', $logs );
	}
}
