<?php
/**
 * Intent Handler for Google Assistant.
 *
 * Processes booking intents from voice commands.
 *
 * @package BookingX\GoogleAssistant
 */

namespace BookingX\GoogleAssistant\Services;

defined( 'ABSPATH' ) || exit;

/**
 * IntentHandler class.
 */
class IntentHandler {

	/**
	 * Session manager.
	 *
	 * @var SessionManager
	 */
	private $session_manager;

	/**
	 * Account linker.
	 *
	 * @var AccountLinker
	 */
	private $account_linker;

	/**
	 * Constructor.
	 *
	 * @param SessionManager $session_manager Session manager.
	 * @param AccountLinker  $account_linker  Account linker.
	 */
	public function __construct( SessionManager $session_manager, AccountLinker $account_linker ) {
		$this->session_manager = $session_manager;
		$this->account_linker  = $account_linker;
	}

	/**
	 * Handle intent.
	 *
	 * @param string $intent       Intent name.
	 * @param array  $parameters   Intent parameters.
	 * @param array  $session_data Session data.
	 * @param array  $user_data    User data from account linking.
	 * @return array Response data.
	 */
	public function handle( $intent, $parameters, $session_data, $user_data = null ) {
		switch ( $intent ) {
			case 'actions.intent.MAIN':
			case 'welcome':
				return $this->handle_welcome( $session_data, $user_data );

			case 'list_services':
				return $this->handle_list_services( $session_data );

			case 'check_availability':
				return $this->handle_check_availability( $parameters, $session_data );

			case 'book_appointment':
			case 'start_booking':
				return $this->handle_start_booking( $parameters, $session_data, $user_data );

			case 'select_service':
				return $this->handle_select_service( $parameters, $session_data );

			case 'select_staff':
				return $this->handle_select_staff( $parameters, $session_data );

			case 'select_date':
				return $this->handle_select_date( $parameters, $session_data );

			case 'select_time':
				return $this->handle_select_time( $parameters, $session_data );

			case 'confirm_booking':
				return $this->handle_confirm_booking( $session_data, $user_data );

			case 'cancel_booking':
				return $this->handle_cancel_booking( $parameters, $session_data, $user_data );

			case 'my_bookings':
				return $this->handle_my_bookings( $user_data );

			case 'help':
				return $this->handle_help();

			case 'actions.intent.CANCEL':
			case 'goodbye':
				return $this->handle_goodbye( $session_data );

			default:
				return $this->handle_fallback( $session_data );
		}
	}

	/**
	 * Handle welcome intent.
	 *
	 * @param array $session_data Session data.
	 * @param array $user_data    User data.
	 * @return array
	 */
	private function handle_welcome( $session_data, $user_data ) {
		$settings        = get_option( 'bkx_google_assistant_settings', array() );
		$welcome_message = $settings['welcome_message'] ?? '';

		if ( empty( $welcome_message ) ) {
			$site_name       = get_bloginfo( 'name' );
			$welcome_message = sprintf(
				/* translators: %s: Site name */
				__( 'Welcome to %s! I can help you book an appointment, check availability, or list our services. What would you like to do?', 'bkx-google-assistant' ),
				$site_name
			);
		}

		// Personalize if user is linked.
		if ( $user_data && ! empty( $user_data->customer_name ) ) {
			$name            = explode( ' ', $user_data->customer_name )[0];
			$welcome_message = sprintf(
				/* translators: %1$s: Customer name, %2$s: Original message */
				__( 'Hi %1$s! %2$s', 'bkx-google-assistant' ),
				$name,
				$welcome_message
			);
		}

		return array(
			'text'        => $welcome_message,
			'suggestions' => array(
				__( 'Book appointment', 'bkx-google-assistant' ),
				__( 'List services', 'bkx-google-assistant' ),
				__( 'Check availability', 'bkx-google-assistant' ),
			),
			'end_conversation' => false,
		);
	}

	/**
	 * Handle list services intent.
	 *
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_list_services( $session_data ) {
		$services = $this->get_available_services();

		if ( empty( $services ) ) {
			return array(
				'text'             => __( 'Sorry, there are no services available at the moment. Please try again later.', 'bkx-google-assistant' ),
				'end_conversation' => false,
			);
		}

		$service_list = array();
		foreach ( $services as $service ) {
			$price          = get_post_meta( $service->ID, 'base_price', true );
			$duration       = get_post_meta( $service->ID, 'base_time', true );
			$service_list[] = sprintf(
				'%s - %s (%s)',
				$service->post_title,
				$this->format_price( $price ),
				$this->format_duration( $duration )
			);
		}

		$text = __( 'Here are our available services:', 'bkx-google-assistant' ) . "\n\n";
		$text .= implode( "\n", $service_list );
		$text .= "\n\n" . __( 'Which service would you like to book?', 'bkx-google-assistant' );

		// Update session intent.
		$this->session_manager->update_intent( $session_data['session_id'], 'select_service' );

		return array(
			'text'        => $text,
			'suggestions' => array_slice( array_column( $services, 'post_title' ), 0, 8 ),
			'end_conversation' => false,
		);
	}

	/**
	 * Handle check availability intent.
	 *
	 * @param array $parameters   Intent parameters.
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_check_availability( $parameters, $session_data ) {
		$date       = $parameters['date'] ?? null;
		$service_id = $parameters['service'] ?? $session_data['booking_data']['service_id'] ?? null;

		if ( ! $date ) {
			$date = gmdate( 'Y-m-d', strtotime( 'tomorrow' ) );
		} else {
			$date = gmdate( 'Y-m-d', strtotime( $date ) );
		}

		$slots = $this->get_available_slots( $date, $service_id );

		if ( empty( $slots ) ) {
			return array(
				'text'        => sprintf(
					/* translators: %s: Date */
					__( 'Sorry, there are no available slots on %s. Would you like to check another date?', 'bkx-google-assistant' ),
					date_i18n( get_option( 'date_format' ), strtotime( $date ) )
				),
				'suggestions' => array(
					__( 'Tomorrow', 'bkx-google-assistant' ),
					__( 'Next week', 'bkx-google-assistant' ),
					__( 'Different service', 'bkx-google-assistant' ),
				),
				'end_conversation' => false,
			);
		}

		$slot_list = array_map( function( $slot ) {
			return date_i18n( get_option( 'time_format' ), strtotime( $slot ) );
		}, array_slice( $slots, 0, 5 ) );

		$text = sprintf(
			/* translators: %s: Date */
			__( 'Available times on %s:', 'bkx-google-assistant' ),
			date_i18n( get_option( 'date_format' ), strtotime( $date ) )
		) . "\n\n";
		$text .= implode( ', ', $slot_list );

		if ( count( $slots ) > 5 ) {
			$text .= sprintf(
				/* translators: %d: Number of additional slots */
				__( ' and %d more slots.', 'bkx-google-assistant' ),
				count( $slots ) - 5
			);
		}

		$text .= "\n\n" . __( 'Would you like to book one of these times?', 'bkx-google-assistant' );

		return array(
			'text'        => $text,
			'suggestions' => array_merge( array_slice( $slot_list, 0, 4 ), array( __( 'Different date', 'bkx-google-assistant' ) ) ),
			'end_conversation' => false,
		);
	}

	/**
	 * Handle start booking intent.
	 *
	 * @param array $parameters   Intent parameters.
	 * @param array $session_data Session data.
	 * @param array $user_data    User data.
	 * @return array
	 */
	private function handle_start_booking( $parameters, $session_data, $user_data ) {
		$settings = get_option( 'bkx_google_assistant_settings', array() );

		// Check if account linking is required.
		if ( ! empty( $settings['require_linking'] ) && ! $user_data ) {
			return array(
				'text'               => __( 'To book an appointment, I\'ll need to link your account. This helps us keep track of your bookings.', 'bkx-google-assistant' ),
				'request_account_linking' => true,
				'end_conversation'   => false,
			);
		}

		// Initialize booking data.
		$booking_data = array(
			'started_at' => current_time( 'mysql' ),
		);

		// Check if service was provided.
		if ( ! empty( $parameters['service'] ) ) {
			$service = $this->find_service_by_name( $parameters['service'] );
			if ( $service ) {
				$booking_data['service_id']   = $service->ID;
				$booking_data['service_name'] = $service->post_title;
			}
		}

		// Check if date was provided.
		if ( ! empty( $parameters['date'] ) ) {
			$booking_data['date'] = gmdate( 'Y-m-d', strtotime( $parameters['date'] ) );
		}

		// Check if time was provided.
		if ( ! empty( $parameters['time'] ) ) {
			$booking_data['time'] = gmdate( 'H:i:s', strtotime( $parameters['time'] ) );
		}

		$this->session_manager->update_booking_data( $session_data['session_id'], $booking_data );

		// Determine next step.
		if ( empty( $booking_data['service_id'] ) ) {
			$this->session_manager->update_intent( $session_data['session_id'], 'select_service' );
			return $this->handle_list_services( $session_data );
		}

		if ( empty( $booking_data['date'] ) ) {
			$this->session_manager->update_intent( $session_data['session_id'], 'select_date' );
			return array(
				'text'        => sprintf(
					/* translators: %s: Service name */
					__( 'Great, you\'d like to book %s. What date would you like?', 'bkx-google-assistant' ),
					$booking_data['service_name']
				),
				'suggestions' => array(
					__( 'Today', 'bkx-google-assistant' ),
					__( 'Tomorrow', 'bkx-google-assistant' ),
					__( 'This weekend', 'bkx-google-assistant' ),
					__( 'Next week', 'bkx-google-assistant' ),
				),
				'end_conversation' => false,
			);
		}

		if ( empty( $booking_data['time'] ) ) {
			$this->session_manager->update_intent( $session_data['session_id'], 'select_time' );
			$slots = $this->get_available_slots( $booking_data['date'], $booking_data['service_id'] );

			$slot_suggestions = array_map( function( $slot ) {
				return date_i18n( get_option( 'time_format' ), strtotime( $slot ) );
			}, array_slice( $slots, 0, 4 ) );

			return array(
				'text'        => sprintf(
					/* translators: %s: Date */
					__( 'What time on %s would you like?', 'bkx-google-assistant' ),
					date_i18n( get_option( 'date_format' ), strtotime( $booking_data['date'] ) )
				),
				'suggestions' => $slot_suggestions,
				'end_conversation' => false,
			);
		}

		// All info collected, confirm.
		return $this->handle_confirm_booking( array_merge( $session_data, array( 'booking_data' => $booking_data ) ), $user_data );
	}

	/**
	 * Handle select service intent.
	 *
	 * @param array $parameters   Intent parameters.
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_select_service( $parameters, $session_data ) {
		$service_name = $parameters['service'] ?? '';
		$service      = $this->find_service_by_name( $service_name );

		if ( ! $service ) {
			return array(
				'text'        => __( 'I couldn\'t find that service. Could you please choose from the list?', 'bkx-google-assistant' ),
				'suggestions' => array_slice(
					array_column( $this->get_available_services(), 'post_title' ),
					0,
					8
				),
				'end_conversation' => false,
			);
		}

		$booking_data                 = $session_data['booking_data'] ?? array();
		$booking_data['service_id']   = $service->ID;
		$booking_data['service_name'] = $service->post_title;

		$this->session_manager->update_booking_data( $session_data['session_id'], $booking_data );
		$this->session_manager->update_intent( $session_data['session_id'], 'select_date' );

		return array(
			'text'        => sprintf(
				/* translators: %s: Service name */
				__( 'Perfect, %s. What date would you like to book?', 'bkx-google-assistant' ),
				$service->post_title
			),
			'suggestions' => array(
				__( 'Today', 'bkx-google-assistant' ),
				__( 'Tomorrow', 'bkx-google-assistant' ),
				__( 'This weekend', 'bkx-google-assistant' ),
				__( 'Next week', 'bkx-google-assistant' ),
			),
			'end_conversation' => false,
		);
	}

	/**
	 * Handle select staff intent.
	 *
	 * @param array $parameters   Intent parameters.
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_select_staff( $parameters, $session_data ) {
		$staff_name = $parameters['staff'] ?? '';
		$staff      = $this->find_staff_by_name( $staff_name );

		if ( ! $staff ) {
			return array(
				'text'        => __( 'I couldn\'t find that staff member. Would you like me to assign anyone available?', 'bkx-google-assistant' ),
				'suggestions' => array(
					__( 'Anyone available', 'bkx-google-assistant' ),
					__( 'List staff', 'bkx-google-assistant' ),
				),
				'end_conversation' => false,
			);
		}

		$booking_data               = $session_data['booking_data'] ?? array();
		$booking_data['seat_id']    = $staff->ID;
		$booking_data['staff_name'] = $staff->post_title;

		$this->session_manager->update_booking_data( $session_data['session_id'], $booking_data );

		// Continue with date selection.
		return $this->handle_select_service( array( 'service' => $booking_data['service_name'] ?? '' ), $session_data );
	}

	/**
	 * Handle select date intent.
	 *
	 * @param array $parameters   Intent parameters.
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_select_date( $parameters, $session_data ) {
		$date_input = $parameters['date'] ?? '';

		// Parse relative dates.
		$date = $this->parse_date( $date_input );

		if ( ! $date || strtotime( $date ) < strtotime( 'today' ) ) {
			return array(
				'text'        => __( 'I couldn\'t understand that date. Could you please say a date like "tomorrow" or "next Monday"?', 'bkx-google-assistant' ),
				'end_conversation' => false,
			);
		}

		$booking_data         = $session_data['booking_data'] ?? array();
		$booking_data['date'] = $date;

		$this->session_manager->update_booking_data( $session_data['session_id'], $booking_data );
		$this->session_manager->update_intent( $session_data['session_id'], 'select_time' );

		// Get available slots.
		$slots = $this->get_available_slots( $date, $booking_data['service_id'] ?? null );

		if ( empty( $slots ) ) {
			return array(
				'text'        => sprintf(
					/* translators: %s: Date */
					__( 'Sorry, there are no available times on %s. Would you like to try a different date?', 'bkx-google-assistant' ),
					date_i18n( get_option( 'date_format' ), strtotime( $date ) )
				),
				'suggestions' => array(
					__( 'Tomorrow', 'bkx-google-assistant' ),
					__( 'Next week', 'bkx-google-assistant' ),
				),
				'end_conversation' => false,
			);
		}

		$slot_suggestions = array_map( function( $slot ) {
			return date_i18n( get_option( 'time_format' ), strtotime( $slot ) );
		}, array_slice( $slots, 0, 4 ) );

		return array(
			'text'        => sprintf(
				/* translators: %s: Date */
				__( 'Great! Here are the available times on %s. What time works for you?', 'bkx-google-assistant' ),
				date_i18n( get_option( 'date_format' ), strtotime( $date ) )
			),
			'suggestions' => $slot_suggestions,
			'end_conversation' => false,
		);
	}

	/**
	 * Handle select time intent.
	 *
	 * @param array $parameters   Intent parameters.
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_select_time( $parameters, $session_data ) {
		$time_input = $parameters['time'] ?? '';
		$time       = $this->parse_time( $time_input );

		if ( ! $time ) {
			return array(
				'text'        => __( 'I couldn\'t understand that time. Could you please say a time like "10 AM" or "2:30 PM"?', 'bkx-google-assistant' ),
				'end_conversation' => false,
			);
		}

		$booking_data         = $session_data['booking_data'] ?? array();
		$booking_data['time'] = $time;

		$this->session_manager->update_booking_data( $session_data['session_id'], $booking_data );

		// All required info collected, show confirmation.
		$session_data['booking_data'] = $booking_data;
		return $this->show_booking_summary( $session_data );
	}

	/**
	 * Show booking summary for confirmation.
	 *
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function show_booking_summary( $session_data ) {
		$booking_data = $session_data['booking_data'];
		$service      = get_post( $booking_data['service_id'] );
		$price        = get_post_meta( $booking_data['service_id'], 'base_price', true );

		$summary = __( 'Here\'s your booking summary:', 'bkx-google-assistant' ) . "\n\n";
		$summary .= sprintf( __( 'Service: %s', 'bkx-google-assistant' ), $service->post_title ) . "\n";
		$summary .= sprintf(
			__( 'Date: %s', 'bkx-google-assistant' ),
			date_i18n( get_option( 'date_format' ), strtotime( $booking_data['date'] ) )
		) . "\n";
		$summary .= sprintf(
			__( 'Time: %s', 'bkx-google-assistant' ),
			date_i18n( get_option( 'time_format' ), strtotime( $booking_data['time'] ) )
		) . "\n";
		$summary .= sprintf( __( 'Price: %s', 'bkx-google-assistant' ), $this->format_price( $price ) ) . "\n\n";
		$summary .= __( 'Would you like to confirm this booking?', 'bkx-google-assistant' );

		$this->session_manager->update_intent( $session_data['session_id'], 'confirm_booking' );

		return array(
			'text'        => $summary,
			'suggestions' => array(
				__( 'Yes, confirm', 'bkx-google-assistant' ),
				__( 'Change time', 'bkx-google-assistant' ),
				__( 'Change date', 'bkx-google-assistant' ),
				__( 'Cancel', 'bkx-google-assistant' ),
			),
			'end_conversation' => false,
		);
	}

	/**
	 * Handle confirm booking intent.
	 *
	 * @param array $session_data Session data.
	 * @param array $user_data    User data.
	 * @return array
	 */
	private function handle_confirm_booking( $session_data, $user_data ) {
		$booking_data = $session_data['booking_data'];

		// Create the booking.
		$booking_id = $this->create_booking( $booking_data, $user_data );

		if ( is_wp_error( $booking_id ) ) {
			return array(
				'text'        => sprintf(
					/* translators: %s: Error message */
					__( 'Sorry, I couldn\'t complete your booking: %s. Would you like to try again?', 'bkx-google-assistant' ),
					$booking_id->get_error_message()
				),
				'suggestions' => array(
					__( 'Try again', 'bkx-google-assistant' ),
					__( 'Cancel', 'bkx-google-assistant' ),
				),
				'end_conversation' => false,
			);
		}

		$this->session_manager->end_session( $session_data['session_id'] );

		$service = get_post( $booking_data['service_id'] );

		return array(
			'text' => sprintf(
				/* translators: %1$s: Service name, %2$s: Date, %3$s: Time, %4$s: Confirmation number */
				__( 'Your booking is confirmed! %1$s on %2$s at %3$s. Your confirmation number is %4$s. You\'ll receive a confirmation email shortly. Is there anything else I can help you with?', 'bkx-google-assistant' ),
				$service->post_title,
				date_i18n( get_option( 'date_format' ), strtotime( $booking_data['date'] ) ),
				date_i18n( get_option( 'time_format' ), strtotime( $booking_data['time'] ) ),
				$booking_id
			),
			'booking_id'   => $booking_id,
			'suggestions'  => array(
				__( 'Book another', 'bkx-google-assistant' ),
				__( 'My bookings', 'bkx-google-assistant' ),
				__( 'Goodbye', 'bkx-google-assistant' ),
			),
			'end_conversation' => false,
		);
	}

	/**
	 * Handle cancel booking intent.
	 *
	 * @param array $parameters   Intent parameters.
	 * @param array $session_data Session data.
	 * @param array $user_data    User data.
	 * @return array
	 */
	private function handle_cancel_booking( $parameters, $session_data, $user_data ) {
		if ( ! $user_data ) {
			return array(
				'text'               => __( 'To cancel a booking, I\'ll need to verify your identity. Please link your account first.', 'bkx-google-assistant' ),
				'request_account_linking' => true,
				'end_conversation'   => false,
			);
		}

		$booking_id = $parameters['booking_id'] ?? null;

		if ( ! $booking_id ) {
			// List user's upcoming bookings.
			$bookings = $this->get_user_bookings( $user_data->customer_email );

			if ( empty( $bookings ) ) {
				return array(
					'text'             => __( 'You don\'t have any upcoming bookings to cancel.', 'bkx-google-assistant' ),
					'end_conversation' => false,
				);
			}

			$booking_list = array();
			foreach ( $bookings as $booking ) {
				$booking_list[] = sprintf(
					'#%d - %s on %s',
					$booking->ID,
					get_post_meta( $booking->ID, 'service_name', true ),
					date_i18n( get_option( 'date_format' ), strtotime( get_post_meta( $booking->ID, 'booking_date', true ) ) )
				);
			}

			return array(
				'text'        => __( 'Which booking would you like to cancel?', 'bkx-google-assistant' ) . "\n\n" . implode( "\n", $booking_list ),
				'suggestions' => array_map( function( $b ) { return '#' . $b->ID; }, array_slice( $bookings, 0, 4 ) ),
				'end_conversation' => false,
			);
		}

		// Cancel the booking.
		$result = $this->cancel_booking( $booking_id, $user_data->customer_email );

		if ( is_wp_error( $result ) ) {
			return array(
				'text'             => $result->get_error_message(),
				'end_conversation' => false,
			);
		}

		return array(
			'text'        => sprintf(
				/* translators: %s: Booking ID */
				__( 'Booking #%s has been cancelled. Is there anything else I can help you with?', 'bkx-google-assistant' ),
				$booking_id
			),
			'suggestions' => array(
				__( 'Book new appointment', 'bkx-google-assistant' ),
				__( 'My bookings', 'bkx-google-assistant' ),
				__( 'Goodbye', 'bkx-google-assistant' ),
			),
			'end_conversation' => false,
		);
	}

	/**
	 * Handle my bookings intent.
	 *
	 * @param array $user_data User data.
	 * @return array
	 */
	private function handle_my_bookings( $user_data ) {
		if ( ! $user_data ) {
			return array(
				'text'               => __( 'To view your bookings, please link your account first.', 'bkx-google-assistant' ),
				'request_account_linking' => true,
				'end_conversation'   => false,
			);
		}

		$bookings = $this->get_user_bookings( $user_data->customer_email );

		if ( empty( $bookings ) ) {
			return array(
				'text'        => __( 'You don\'t have any upcoming bookings. Would you like to make one?', 'bkx-google-assistant' ),
				'suggestions' => array(
					__( 'Book appointment', 'bkx-google-assistant' ),
					__( 'List services', 'bkx-google-assistant' ),
				),
				'end_conversation' => false,
			);
		}

		$text = __( 'Here are your upcoming bookings:', 'bkx-google-assistant' ) . "\n\n";

		foreach ( $bookings as $booking ) {
			$text .= sprintf(
				"#%d - %s\n%s at %s\n\n",
				$booking->ID,
				get_post_meta( $booking->ID, 'service_name', true ),
				date_i18n( get_option( 'date_format' ), strtotime( get_post_meta( $booking->ID, 'booking_date', true ) ) ),
				date_i18n( get_option( 'time_format' ), strtotime( get_post_meta( $booking->ID, 'booking_time', true ) ) )
			);
		}

		return array(
			'text'        => $text,
			'suggestions' => array(
				__( 'Book another', 'bkx-google-assistant' ),
				__( 'Cancel booking', 'bkx-google-assistant' ),
				__( 'Goodbye', 'bkx-google-assistant' ),
			),
			'end_conversation' => false,
		);
	}

	/**
	 * Handle help intent.
	 *
	 * @return array
	 */
	private function handle_help() {
		return array(
			'text' => __( 'I can help you with the following:', 'bkx-google-assistant' ) . "\n\n" .
				__( '• Book an appointment - Just say "book an appointment"', 'bkx-google-assistant' ) . "\n" .
				__( '• Check availability - "What times are available tomorrow?"', 'bkx-google-assistant' ) . "\n" .
				__( '• List services - "What services do you offer?"', 'bkx-google-assistant' ) . "\n" .
				__( '• View your bookings - "Show my bookings"', 'bkx-google-assistant' ) . "\n" .
				__( '• Cancel a booking - "Cancel my booking"', 'bkx-google-assistant' ) . "\n\n" .
				__( 'What would you like to do?', 'bkx-google-assistant' ),
			'suggestions' => array(
				__( 'Book appointment', 'bkx-google-assistant' ),
				__( 'List services', 'bkx-google-assistant' ),
				__( 'My bookings', 'bkx-google-assistant' ),
			),
			'end_conversation' => false,
		);
	}

	/**
	 * Handle goodbye intent.
	 *
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_goodbye( $session_data ) {
		$this->session_manager->end_session( $session_data['session_id'] );

		return array(
			'text'             => __( 'Thank you for using our booking service. Goodbye!', 'bkx-google-assistant' ),
			'end_conversation' => true,
		);
	}

	/**
	 * Handle fallback/unknown intent.
	 *
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_fallback( $session_data ) {
		return array(
			'text'        => __( 'I\'m sorry, I didn\'t understand that. You can ask me to book an appointment, check availability, or list services. What would you like to do?', 'bkx-google-assistant' ),
			'suggestions' => array(
				__( 'Book appointment', 'bkx-google-assistant' ),
				__( 'List services', 'bkx-google-assistant' ),
				__( 'Help', 'bkx-google-assistant' ),
			),
			'end_conversation' => false,
		);
	}

	/**
	 * Get available services.
	 *
	 * @return array
	 */
	private function get_available_services() {
		$args = array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		return get_posts( $args );
	}

	/**
	 * Find service by name.
	 *
	 * @param string $name Service name.
	 * @return \WP_Post|null
	 */
	private function find_service_by_name( $name ) {
		$services = $this->get_available_services();

		foreach ( $services as $service ) {
			if ( stripos( $service->post_title, $name ) !== false ) {
				return $service;
			}
		}

		return null;
	}

	/**
	 * Find staff by name.
	 *
	 * @param string $name Staff name.
	 * @return \WP_Post|null
	 */
	private function find_staff_by_name( $name ) {
		$args = array(
			'post_type'      => 'bkx_seat',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			's'              => $name,
		);

		$posts = get_posts( $args );

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Get available slots for a date.
	 *
	 * @param string $date       Date (Y-m-d).
	 * @param int    $service_id Service ID.
	 * @return array Available time slots.
	 */
	private function get_available_slots( $date, $service_id = null ) {
		// Use BookingX availability API if available.
		if ( function_exists( 'bkx_get_available_slots' ) ) {
			return bkx_get_available_slots( $date, $service_id );
		}

		// Fallback: Generate basic slots.
		$slots      = array();
		$start_hour = 9;
		$end_hour   = 17;

		for ( $hour = $start_hour; $hour < $end_hour; $hour++ ) {
			$slots[] = sprintf( '%02d:00:00', $hour );
			$slots[] = sprintf( '%02d:30:00', $hour );
		}

		return $slots;
	}

	/**
	 * Parse date input.
	 *
	 * @param string $input Date input.
	 * @return string|null Y-m-d format or null.
	 */
	private function parse_date( $input ) {
		$input = strtolower( trim( $input ) );

		$mappings = array(
			'today'        => 'today',
			'tomorrow'     => 'tomorrow',
			'this weekend' => 'next saturday',
			'next week'    => 'next monday',
		);

		if ( isset( $mappings[ $input ] ) ) {
			return gmdate( 'Y-m-d', strtotime( $mappings[ $input ] ) );
		}

		$timestamp = strtotime( $input );
		if ( $timestamp ) {
			return gmdate( 'Y-m-d', $timestamp );
		}

		return null;
	}

	/**
	 * Parse time input.
	 *
	 * @param string $input Time input.
	 * @return string|null H:i:s format or null.
	 */
	private function parse_time( $input ) {
		$timestamp = strtotime( $input );
		if ( $timestamp ) {
			return gmdate( 'H:i:s', $timestamp );
		}

		return null;
	}

	/**
	 * Format price.
	 *
	 * @param float $price Price.
	 * @return string
	 */
	private function format_price( $price ) {
		if ( function_exists( 'bkx_format_price' ) ) {
			return bkx_format_price( $price );
		}

		return '$' . number_format( (float) $price, 2 );
	}

	/**
	 * Format duration.
	 *
	 * @param int $minutes Duration in minutes.
	 * @return string
	 */
	private function format_duration( $minutes ) {
		$minutes = (int) $minutes;

		if ( $minutes < 60 ) {
			/* translators: %d: Number of minutes */
			return sprintf( _n( '%d min', '%d mins', $minutes, 'bkx-google-assistant' ), $minutes );
		}

		$hours = floor( $minutes / 60 );
		$mins  = $minutes % 60;

		if ( $mins === 0 ) {
			/* translators: %d: Number of hours */
			return sprintf( _n( '%d hour', '%d hours', $hours, 'bkx-google-assistant' ), $hours );
		}

		/* translators: %1$d: Hours, %2$d: Minutes */
		return sprintf( __( '%1$d hr %2$d min', 'bkx-google-assistant' ), $hours, $mins );
	}

	/**
	 * Create booking.
	 *
	 * @param array $booking_data Booking data.
	 * @param array $user_data    User data.
	 * @return int|\WP_Error Booking ID or error.
	 */
	private function create_booking( $booking_data, $user_data ) {
		$service = get_post( $booking_data['service_id'] );

		if ( ! $service ) {
			return new \WP_Error( 'invalid_service', __( 'Invalid service selected.', 'bkx-google-assistant' ) );
		}

		$booking_args = array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-pending',
			'post_title'  => sprintf(
				'Voice Booking - %s - %s',
				$service->post_title,
				$booking_data['date']
			),
		);

		$booking_id = wp_insert_post( $booking_args );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		// Add booking meta.
		update_post_meta( $booking_id, 'booking_date', $booking_data['date'] );
		update_post_meta( $booking_id, 'booking_time', $booking_data['time'] );
		update_post_meta( $booking_id, 'service_id', $booking_data['service_id'] );
		update_post_meta( $booking_id, 'service_name', $service->post_title );
		update_post_meta( $booking_id, 'booking_source', 'google_assistant' );

		if ( ! empty( $booking_data['seat_id'] ) ) {
			update_post_meta( $booking_id, 'seat_id', $booking_data['seat_id'] );
		}

		// Add customer info if available.
		if ( $user_data ) {
			update_post_meta( $booking_id, 'customer_email', $user_data->customer_email );
			update_post_meta( $booking_id, 'customer_name', $user_data->customer_name );
			if ( $user_data->wp_user_id ) {
				update_post_meta( $booking_id, 'customer_id', $user_data->wp_user_id );
			}
		}

		// Trigger booking created action.
		do_action( 'bkx_booking_created', $booking_id, $booking_data );
		do_action( 'bkx_voice_booking_created', $booking_id, 'google_assistant' );

		return $booking_id;
	}

	/**
	 * Get user bookings.
	 *
	 * @param string $email Customer email.
	 * @return array
	 */
	private function get_user_bookings( $email ) {
		$args = array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
			'posts_per_page' => 10,
			'meta_query'     => array(
				array(
					'key'     => 'customer_email',
					'value'   => $email,
					'compare' => '=',
				),
				array(
					'key'     => 'booking_date',
					'value'   => gmdate( 'Y-m-d' ),
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => 'booking_date',
			'order'          => 'ASC',
		);

		return get_posts( $args );
	}

	/**
	 * Cancel booking.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $email      Customer email.
	 * @return bool|\WP_Error
	 */
	private function cancel_booking( $booking_id, $email ) {
		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error( 'not_found', __( 'Booking not found.', 'bkx-google-assistant' ) );
		}

		$booking_email = get_post_meta( $booking_id, 'customer_email', true );

		if ( $booking_email !== $email ) {
			return new \WP_Error( 'unauthorized', __( 'You can only cancel your own bookings.', 'bkx-google-assistant' ) );
		}

		wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-cancelled',
		) );

		do_action( 'bkx_booking_cancelled', $booking_id );

		return true;
	}
}
