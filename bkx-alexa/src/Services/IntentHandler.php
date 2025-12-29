<?php
/**
 * Intent Handler for Amazon Alexa.
 *
 * Processes booking intents from voice commands.
 *
 * @package BookingX\Alexa
 */

namespace BookingX\Alexa\Services;

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
	 * @param array  $slots        Intent slots.
	 * @param array  $session_data Session data.
	 * @param array  $user_data    User data from account linking.
	 * @return array Response data.
	 */
	public function handle( $intent, $slots, $session_data, $user_data = null ) {
		switch ( $intent ) {
			case 'LaunchRequest':
				return $this->handle_launch( $session_data, $user_data );

			case 'ListServicesIntent':
				return $this->handle_list_services( $session_data );

			case 'CheckAvailabilityIntent':
				return $this->handle_check_availability( $slots, $session_data );

			case 'BookAppointmentIntent':
				return $this->handle_book_appointment( $slots, $session_data, $user_data );

			case 'SelectServiceIntent':
				return $this->handle_select_service( $slots, $session_data );

			case 'SelectDateIntent':
				return $this->handle_select_date( $slots, $session_data );

			case 'SelectTimeIntent':
				return $this->handle_select_time( $slots, $session_data );

			case 'ConfirmBookingIntent':
				return $this->handle_confirm_booking( $session_data, $user_data );

			case 'CancelBookingIntent':
				return $this->handle_cancel_booking( $slots, $session_data, $user_data );

			case 'MyBookingsIntent':
				return $this->handle_my_bookings( $user_data );

			case 'AMAZON.YesIntent':
				return $this->handle_yes( $session_data, $user_data );

			case 'AMAZON.NoIntent':
				return $this->handle_no( $session_data );

			case 'AMAZON.HelpIntent':
				return $this->handle_help();

			case 'AMAZON.StopIntent':
			case 'AMAZON.CancelIntent':
				return $this->handle_stop( $session_data );

			case 'AMAZON.FallbackIntent':
			default:
				return $this->handle_fallback( $session_data );
		}
	}

	/**
	 * Handle launch request.
	 *
	 * @param array $session_data Session data.
	 * @param array $user_data    User data.
	 * @return array
	 */
	private function handle_launch( $session_data, $user_data ) {
		$settings        = get_option( 'bkx_alexa_settings', array() );
		$welcome_message = $settings['welcome_message'] ?? '';

		if ( empty( $welcome_message ) ) {
			$site_name       = get_bloginfo( 'name' );
			$welcome_message = sprintf(
				/* translators: %s: Site name */
				__( 'Welcome to %s! I can help you book an appointment, check availability, or list our services. What would you like to do?', 'bkx-alexa' ),
				$site_name
			);
		}

		// Personalize if user is linked.
		if ( $user_data && ! empty( $user_data->customer_name ) ) {
			$name            = explode( ' ', $user_data->customer_name )[0];
			$welcome_message = sprintf(
				/* translators: %1$s: Customer name, %2$s: Original message */
				__( 'Hi %1$s! %2$s', 'bkx-alexa' ),
				$name,
				$welcome_message
			);
		}

		return array(
			'speech'           => $welcome_message,
			'reprompt'         => __( 'You can say "book an appointment", "list services", or "check availability". What would you like to do?', 'bkx-alexa' ),
			'end_session'      => false,
			'card'             => array(
				'type'  => 'Simple',
				'title' => get_bloginfo( 'name' ),
				'content' => $welcome_message,
			),
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
				'speech'      => __( 'Sorry, there are no services available at the moment. Please try again later.', 'bkx-alexa' ),
				'end_session' => false,
			);
		}

		$service_names = array_column( $services, 'post_title' );

		// Format for speech.
		if ( count( $service_names ) === 1 ) {
			$speech_list = $service_names[0];
		} elseif ( count( $service_names ) === 2 ) {
			$speech_list = $service_names[0] . ' and ' . $service_names[1];
		} else {
			$last        = array_pop( $service_names );
			$speech_list = implode( ', ', $service_names ) . ', and ' . $last;
		}

		$text = sprintf(
			/* translators: %s: Service list */
			__( 'We offer the following services: %s. Which service would you like to book?', 'bkx-alexa' ),
			$speech_list
		);

		$this->session_manager->update_intent( $session_data['session_id'], 'SelectServiceIntent' );

		return array(
			'speech'      => $text,
			'reprompt'    => __( 'Which service would you like? Just say the name of the service.', 'bkx-alexa' ),
			'end_session' => false,
		);
	}

	/**
	 * Handle check availability intent.
	 *
	 * @param array $slots        Intent slots.
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_check_availability( $slots, $session_data ) {
		$date       = $slots['date'] ?? null;
		$service_id = $session_data['booking_data']['service_id'] ?? null;

		if ( ! $date ) {
			$date = gmdate( 'Y-m-d', strtotime( 'tomorrow' ) );
		} else {
			$date = $this->parse_alexa_date( $date );
		}

		$slots_available = $this->get_available_slots( $date, $service_id );

		if ( empty( $slots_available ) ) {
			return array(
				'speech'      => sprintf(
					/* translators: %s: Date */
					__( 'Sorry, there are no available times on %s. Would you like to check another date?', 'bkx-alexa' ),
					$this->format_date_for_speech( $date )
				),
				'reprompt'    => __( 'Would you like to check a different date?', 'bkx-alexa' ),
				'end_session' => false,
			);
		}

		$slot_list = array_map( function( $slot ) {
			return date_i18n( 'g:i A', strtotime( $slot ) );
		}, array_slice( $slots_available, 0, 5 ) );

		// Format for speech.
		if ( count( $slot_list ) === 1 ) {
			$speech_slots = $slot_list[0];
		} elseif ( count( $slot_list ) === 2 ) {
			$speech_slots = $slot_list[0] . ' and ' . $slot_list[1];
		} else {
			$last         = array_pop( $slot_list );
			$speech_slots = implode( ', ', $slot_list ) . ', and ' . $last;
		}

		$text = sprintf(
			/* translators: %1$s: Date, %2$s: Available times */
			__( 'Available times on %1$s include: %2$s. Would you like to book one of these times?', 'bkx-alexa' ),
			$this->format_date_for_speech( $date ),
			$speech_slots
		);

		return array(
			'speech'      => $text,
			'reprompt'    => __( 'Would you like to book an appointment? Just tell me the time.', 'bkx-alexa' ),
			'end_session' => false,
		);
	}

	/**
	 * Handle book appointment intent.
	 *
	 * @param array $slots        Intent slots.
	 * @param array $session_data Session data.
	 * @param array $user_data    User data.
	 * @return array
	 */
	private function handle_book_appointment( $slots, $session_data, $user_data ) {
		$settings = get_option( 'bkx_alexa_settings', array() );

		// Check if account linking is required.
		if ( ! empty( $settings['require_linking'] ) && ! $user_data ) {
			return array(
				'speech'               => __( 'To book an appointment, I\'ll need to link your account. Please check the Alexa app to link your account and try again.', 'bkx-alexa' ),
				'card'                 => array(
					'type' => 'LinkAccount',
				),
				'end_session'          => true,
			);
		}

		// Initialize booking data.
		$booking_data = $session_data['booking_data'] ?? array();
		$booking_data['started_at'] = current_time( 'mysql' );

		// Check if service was provided.
		if ( ! empty( $slots['service'] ) ) {
			$service = $this->find_service_by_name( $slots['service'] );
			if ( $service ) {
				$booking_data['service_id']   = $service->ID;
				$booking_data['service_name'] = $service->post_title;
			}
		}

		// Check if date was provided.
		if ( ! empty( $slots['date'] ) ) {
			$booking_data['date'] = $this->parse_alexa_date( $slots['date'] );
		}

		// Check if time was provided.
		if ( ! empty( $slots['time'] ) ) {
			$booking_data['time'] = $this->parse_alexa_time( $slots['time'] );
		}

		$this->session_manager->update_booking_data( $session_data['session_id'], $booking_data );

		// Determine next step.
		if ( empty( $booking_data['service_id'] ) ) {
			$this->session_manager->update_intent( $session_data['session_id'], 'SelectServiceIntent' );
			return $this->handle_list_services( $session_data );
		}

		if ( empty( $booking_data['date'] ) ) {
			$this->session_manager->update_intent( $session_data['session_id'], 'SelectDateIntent' );
			return array(
				'speech'      => sprintf(
					/* translators: %s: Service name */
					__( 'Great, you\'d like to book %s. What date would you prefer?', 'bkx-alexa' ),
					$booking_data['service_name']
				),
				'reprompt'    => __( 'What date would you like? You can say today, tomorrow, or a specific date.', 'bkx-alexa' ),
				'end_session' => false,
			);
		}

		if ( empty( $booking_data['time'] ) ) {
			$this->session_manager->update_intent( $session_data['session_id'], 'SelectTimeIntent' );
			$available_slots = $this->get_available_slots( $booking_data['date'], $booking_data['service_id'] );

			if ( empty( $available_slots ) ) {
				return array(
					'speech'      => sprintf(
						/* translators: %s: Date */
						__( 'Sorry, there are no available times on %s. Would you like to try a different date?', 'bkx-alexa' ),
						$this->format_date_for_speech( $booking_data['date'] )
					),
					'end_session' => false,
				);
			}

			$slot_list = array_map( function( $slot ) {
				return date_i18n( 'g:i A', strtotime( $slot ) );
			}, array_slice( $available_slots, 0, 3 ) );

			return array(
				'speech'      => sprintf(
					/* translators: %1$s: Date, %2$s: Available times */
					__( 'On %1$s, we have availability at %2$s. What time works for you?', 'bkx-alexa' ),
					$this->format_date_for_speech( $booking_data['date'] ),
					implode( ', ', $slot_list )
				),
				'reprompt'    => __( 'What time would you like? Just tell me the time.', 'bkx-alexa' ),
				'end_session' => false,
			);
		}

		// All info collected, show confirmation.
		$session_data['booking_data'] = $booking_data;
		return $this->show_booking_summary( $session_data );
	}

	/**
	 * Handle select service intent.
	 *
	 * @param array $slots        Intent slots.
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_select_service( $slots, $session_data ) {
		$service_name = $slots['service'] ?? '';
		$service      = $this->find_service_by_name( $service_name );

		if ( ! $service ) {
			$services     = $this->get_available_services();
			$service_list = implode( ', ', array_column( $services, 'post_title' ) );

			return array(
				'speech'      => sprintf(
					/* translators: %s: Service list */
					__( 'I couldn\'t find that service. We offer: %s. Which would you like?', 'bkx-alexa' ),
					$service_list
				),
				'reprompt'    => __( 'Which service would you like to book?', 'bkx-alexa' ),
				'end_session' => false,
			);
		}

		$booking_data                 = $session_data['booking_data'] ?? array();
		$booking_data['service_id']   = $service->ID;
		$booking_data['service_name'] = $service->post_title;

		$this->session_manager->update_booking_data( $session_data['session_id'], $booking_data );
		$this->session_manager->update_intent( $session_data['session_id'], 'SelectDateIntent' );

		return array(
			'speech'      => sprintf(
				/* translators: %s: Service name */
				__( 'Perfect, %s. What date would you like to book?', 'bkx-alexa' ),
				$service->post_title
			),
			'reprompt'    => __( 'What date would you like? You can say today, tomorrow, or a specific date.', 'bkx-alexa' ),
			'end_session' => false,
		);
	}

	/**
	 * Handle select date intent.
	 *
	 * @param array $slots        Intent slots.
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_select_date( $slots, $session_data ) {
		$date_input = $slots['date'] ?? '';
		$date       = $this->parse_alexa_date( $date_input );

		if ( ! $date || strtotime( $date ) < strtotime( 'today' ) ) {
			return array(
				'speech'      => __( 'I couldn\'t understand that date. Could you please say a date like "tomorrow" or "next Monday"?', 'bkx-alexa' ),
				'reprompt'    => __( 'What date would you like?', 'bkx-alexa' ),
				'end_session' => false,
			);
		}

		$booking_data         = $session_data['booking_data'] ?? array();
		$booking_data['date'] = $date;

		$this->session_manager->update_booking_data( $session_data['session_id'], $booking_data );
		$this->session_manager->update_intent( $session_data['session_id'], 'SelectTimeIntent' );

		// Get available slots.
		$available_slots = $this->get_available_slots( $date, $booking_data['service_id'] ?? null );

		if ( empty( $available_slots ) ) {
			return array(
				'speech'      => sprintf(
					/* translators: %s: Date */
					__( 'Sorry, there are no available times on %s. Would you like to try a different date?', 'bkx-alexa' ),
					$this->format_date_for_speech( $date )
				),
				'end_session' => false,
			);
		}

		$slot_list = array_map( function( $slot ) {
			return date_i18n( 'g:i A', strtotime( $slot ) );
		}, array_slice( $available_slots, 0, 4 ) );

		return array(
			'speech'      => sprintf(
				/* translators: %1$s: Date, %2$s: Available times */
				__( 'Great! On %1$s, we have times available at %2$s. What time works for you?', 'bkx-alexa' ),
				$this->format_date_for_speech( $date ),
				implode( ', ', $slot_list )
			),
			'reprompt'    => __( 'What time would you prefer?', 'bkx-alexa' ),
			'end_session' => false,
		);
	}

	/**
	 * Handle select time intent.
	 *
	 * @param array $slots        Intent slots.
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_select_time( $slots, $session_data ) {
		$time_input = $slots['time'] ?? '';
		$time       = $this->parse_alexa_time( $time_input );

		if ( ! $time ) {
			return array(
				'speech'      => __( 'I couldn\'t understand that time. Could you please say a time like "10 AM" or "2:30 PM"?', 'bkx-alexa' ),
				'reprompt'    => __( 'What time would you like?', 'bkx-alexa' ),
				'end_session' => false,
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

		$summary = sprintf(
			/* translators: %1$s: Service name, %2$s: Date, %3$s: Time, %4$s: Price */
			__( 'Here\'s your booking: %1$s on %2$s at %3$s for %4$s. Should I confirm this booking?', 'bkx-alexa' ),
			$service->post_title,
			$this->format_date_for_speech( $booking_data['date'] ),
			date_i18n( 'g:i A', strtotime( $booking_data['time'] ) ),
			$this->format_price( $price )
		);

		$this->session_manager->update_intent( $session_data['session_id'], 'ConfirmBookingIntent' );

		return array(
			'speech'      => $summary,
			'reprompt'    => __( 'Should I confirm this booking? Say yes to confirm or no to cancel.', 'bkx-alexa' ),
			'end_session' => false,
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
				'speech'      => sprintf(
					/* translators: %s: Error message */
					__( 'Sorry, I couldn\'t complete your booking: %s. Would you like to try again?', 'bkx-alexa' ),
					$booking_id->get_error_message()
				),
				'end_session' => false,
			);
		}

		$this->session_manager->end_session( $session_data['session_id'] );

		$service = get_post( $booking_data['service_id'] );

		return array(
			'speech' => sprintf(
				/* translators: %1$s: Service name, %2$s: Date, %3$s: Time, %4$s: Confirmation number */
				__( 'Your booking is confirmed! %1$s on %2$s at %3$s. Your confirmation number is %4$s. You\'ll receive a confirmation email shortly. Thank you for booking with us!', 'bkx-alexa' ),
				$service->post_title,
				$this->format_date_for_speech( $booking_data['date'] ),
				date_i18n( 'g:i A', strtotime( $booking_data['time'] ) ),
				$booking_id
			),
			'booking_id' => $booking_id,
			'card'       => array(
				'type'    => 'Simple',
				'title'   => __( 'Booking Confirmed', 'bkx-alexa' ),
				'content' => sprintf(
					"Service: %s\nDate: %s\nTime: %s\nConfirmation #: %s",
					$service->post_title,
					date_i18n( get_option( 'date_format' ), strtotime( $booking_data['date'] ) ),
					date_i18n( get_option( 'time_format' ), strtotime( $booking_data['time'] ) ),
					$booking_id
				),
			),
			'end_session' => true,
		);
	}

	/**
	 * Handle yes intent (contextual).
	 *
	 * @param array $session_data Session data.
	 * @param array $user_data    User data.
	 * @return array
	 */
	private function handle_yes( $session_data, $user_data ) {
		$current_intent = $session_data['intent'] ?? '';

		if ( $current_intent === 'ConfirmBookingIntent' ) {
			return $this->handle_confirm_booking( $session_data, $user_data );
		}

		return array(
			'speech'      => __( 'Okay! What would you like to do?', 'bkx-alexa' ),
			'end_session' => false,
		);
	}

	/**
	 * Handle no intent (contextual).
	 *
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_no( $session_data ) {
		$current_intent = $session_data['intent'] ?? '';

		if ( $current_intent === 'ConfirmBookingIntent' ) {
			// Clear booking data.
			$this->session_manager->update_booking_data( $session_data['session_id'], array() );

			return array(
				'speech'      => __( 'No problem, I\'ve cancelled that booking. Would you like to start over or try a different service?', 'bkx-alexa' ),
				'end_session' => false,
			);
		}

		return array(
			'speech'      => __( 'Okay! Is there anything else I can help you with?', 'bkx-alexa' ),
			'end_session' => false,
		);
	}

	/**
	 * Handle cancel booking intent.
	 *
	 * @param array $slots        Intent slots.
	 * @param array $session_data Session data.
	 * @param array $user_data    User data.
	 * @return array
	 */
	private function handle_cancel_booking( $slots, $session_data, $user_data ) {
		if ( ! $user_data ) {
			return array(
				'speech'      => __( 'To cancel a booking, please link your account in the Alexa app first.', 'bkx-alexa' ),
				'card'        => array( 'type' => 'LinkAccount' ),
				'end_session' => true,
			);
		}

		$booking_id = $slots['booking_id'] ?? null;

		if ( ! $booking_id ) {
			$bookings = $this->get_user_bookings( $user_data->customer_email );

			if ( empty( $bookings ) ) {
				return array(
					'speech'      => __( 'You don\'t have any upcoming bookings to cancel.', 'bkx-alexa' ),
					'end_session' => false,
				);
			}

			$booking_list = array();
			foreach ( array_slice( $bookings, 0, 3 ) as $booking ) {
				$booking_list[] = sprintf(
					'number %d for %s',
					$booking->ID,
					get_post_meta( $booking->ID, 'service_name', true )
				);
			}

			return array(
				'speech'      => __( 'Which booking would you like to cancel? Your upcoming bookings are: ', 'bkx-alexa' ) . implode( ', ', $booking_list ),
				'reprompt'    => __( 'Please tell me the booking number you\'d like to cancel.', 'bkx-alexa' ),
				'end_session' => false,
			);
		}

		$result = $this->cancel_booking( $booking_id, $user_data->customer_email );

		if ( is_wp_error( $result ) ) {
			return array(
				'speech'      => $result->get_error_message(),
				'end_session' => false,
			);
		}

		return array(
			'speech'      => sprintf(
				/* translators: %s: Booking ID */
				__( 'Booking number %s has been cancelled. Is there anything else I can help you with?', 'bkx-alexa' ),
				$booking_id
			),
			'end_session' => false,
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
				'speech'      => __( 'To view your bookings, please link your account in the Alexa app.', 'bkx-alexa' ),
				'card'        => array( 'type' => 'LinkAccount' ),
				'end_session' => true,
			);
		}

		$bookings = $this->get_user_bookings( $user_data->customer_email );

		if ( empty( $bookings ) ) {
			return array(
				'speech'      => __( 'You don\'t have any upcoming bookings. Would you like to make one?', 'bkx-alexa' ),
				'end_session' => false,
			);
		}

		$booking_list = array();
		$card_content = "Your upcoming bookings:\n\n";

		foreach ( array_slice( $bookings, 0, 5 ) as $booking ) {
			$service_name = get_post_meta( $booking->ID, 'service_name', true );
			$date         = get_post_meta( $booking->ID, 'booking_date', true );
			$time         = get_post_meta( $booking->ID, 'booking_time', true );

			$booking_list[] = sprintf(
				'%s on %s at %s',
				$service_name,
				$this->format_date_for_speech( $date ),
				date_i18n( 'g:i A', strtotime( $time ) )
			);

			$card_content .= sprintf(
				"#%d - %s\n%s at %s\n\n",
				$booking->ID,
				$service_name,
				date_i18n( get_option( 'date_format' ), strtotime( $date ) ),
				date_i18n( get_option( 'time_format' ), strtotime( $time ) )
			);
		}

		return array(
			'speech'      => __( 'Here are your upcoming bookings: ', 'bkx-alexa' ) . implode( '. ', $booking_list ),
			'card'        => array(
				'type'    => 'Simple',
				'title'   => __( 'Your Bookings', 'bkx-alexa' ),
				'content' => $card_content,
			),
			'end_session' => false,
		);
	}

	/**
	 * Handle help intent.
	 *
	 * @return array
	 */
	private function handle_help() {
		return array(
			'speech' => __( 'I can help you book appointments. You can say "book an appointment" to start a new booking, "list services" to see what\'s available, "check availability" to see open times, or "my bookings" to view your appointments. What would you like to do?', 'bkx-alexa' ),
			'reprompt' => __( 'What would you like to do? Say "book an appointment" to get started.', 'bkx-alexa' ),
			'end_session' => false,
		);
	}

	/**
	 * Handle stop intent.
	 *
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_stop( $session_data ) {
		$this->session_manager->end_session( $session_data['session_id'] );

		return array(
			'speech'      => __( 'Thank you for using our booking service. Goodbye!', 'bkx-alexa' ),
			'end_session' => true,
		);
	}

	/**
	 * Handle fallback intent.
	 *
	 * @param array $session_data Session data.
	 * @return array
	 */
	private function handle_fallback( $session_data ) {
		return array(
			'speech'      => __( 'I\'m sorry, I didn\'t catch that. You can say "book an appointment", "list services", or "help" for more options.', 'bkx-alexa' ),
			'reprompt'    => __( 'What would you like to do?', 'bkx-alexa' ),
			'end_session' => false,
		);
	}

	/**
	 * Get available services.
	 *
	 * @return array
	 */
	private function get_available_services() {
		return get_posts( array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
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
	 * Get available slots for a date.
	 *
	 * @param string $date       Date (Y-m-d).
	 * @param int    $service_id Service ID.
	 * @return array
	 */
	private function get_available_slots( $date, $service_id = null ) {
		if ( function_exists( 'bkx_get_available_slots' ) ) {
			return bkx_get_available_slots( $date, $service_id );
		}

		// Fallback: Generate basic slots.
		$slots = array();
		for ( $hour = 9; $hour < 17; $hour++ ) {
			$slots[] = sprintf( '%02d:00:00', $hour );
			$slots[] = sprintf( '%02d:30:00', $hour );
		}

		return $slots;
	}

	/**
	 * Parse Alexa date format.
	 *
	 * @param string $date Alexa date value.
	 * @return string Y-m-d format.
	 */
	private function parse_alexa_date( $date ) {
		// Alexa sends dates in various formats.
		// ISO date: 2024-01-15
		// Week: 2024-W03
		// Weekend: 2024-W03-WE
		// Month: 2024-01.

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $date;
		}

		// Handle week format.
		if ( preg_match( '/^(\d{4})-W(\d{2})/', $date, $matches ) ) {
			$dto = new \DateTime();
			$dto->setISODate( (int) $matches[1], (int) $matches[2] );
			return $dto->format( 'Y-m-d' );
		}

		// Try strtotime for relative dates.
		$timestamp = strtotime( $date );
		if ( $timestamp ) {
			return gmdate( 'Y-m-d', $timestamp );
		}

		return gmdate( 'Y-m-d', strtotime( 'tomorrow' ) );
	}

	/**
	 * Parse Alexa time format.
	 *
	 * @param string $time Alexa time value.
	 * @return string H:i:s format.
	 */
	private function parse_alexa_time( $time ) {
		// Alexa sends times in HH:MM format or special values like "MO" (morning).
		$special_times = array(
			'MO' => '09:00:00',
			'AF' => '14:00:00',
			'EV' => '18:00:00',
			'NI' => '20:00:00',
		);

		if ( isset( $special_times[ $time ] ) ) {
			return $special_times[ $time ];
		}

		// Parse HH:MM format.
		if ( preg_match( '/^(\d{2}):(\d{2})$/', $time, $matches ) ) {
			return $time . ':00';
		}

		$timestamp = strtotime( $time );
		if ( $timestamp ) {
			return gmdate( 'H:i:s', $timestamp );
		}

		return null;
	}

	/**
	 * Format date for speech.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return string
	 */
	private function format_date_for_speech( $date ) {
		$timestamp = strtotime( $date );
		$today     = strtotime( 'today' );
		$tomorrow  = strtotime( 'tomorrow' );

		if ( $timestamp === $today ) {
			return __( 'today', 'bkx-alexa' );
		}

		if ( $timestamp === $tomorrow ) {
			return __( 'tomorrow', 'bkx-alexa' );
		}

		return date_i18n( 'l, F jS', $timestamp );
	}

	/**
	 * Format price for speech.
	 *
	 * @param float $price Price.
	 * @return string
	 */
	private function format_price( $price ) {
		$price = (float) $price;
		if ( $price === floor( $price ) ) {
			return '$' . number_format( $price, 0 );
		}
		return '$' . number_format( $price, 2 );
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
			return new \WP_Error( 'invalid_service', __( 'Invalid service selected.', 'bkx-alexa' ) );
		}

		$booking_args = array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-pending',
			'post_title'  => sprintf( 'Alexa Booking - %s - %s', $service->post_title, $booking_data['date'] ),
		);

		$booking_id = wp_insert_post( $booking_args );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		update_post_meta( $booking_id, 'booking_date', $booking_data['date'] );
		update_post_meta( $booking_id, 'booking_time', $booking_data['time'] );
		update_post_meta( $booking_id, 'service_id', $booking_data['service_id'] );
		update_post_meta( $booking_id, 'service_name', $service->post_title );
		update_post_meta( $booking_id, 'booking_source', 'alexa' );

		if ( $user_data ) {
			update_post_meta( $booking_id, 'customer_email', $user_data->customer_email );
			update_post_meta( $booking_id, 'customer_name', $user_data->customer_name );
			if ( $user_data->wp_user_id ) {
				update_post_meta( $booking_id, 'customer_id', $user_data->wp_user_id );
			}
		}

		do_action( 'bkx_booking_created', $booking_id, $booking_data );
		do_action( 'bkx_voice_booking_created', $booking_id, 'alexa' );

		return $booking_id;
	}

	/**
	 * Get user bookings.
	 *
	 * @param string $email Customer email.
	 * @return array
	 */
	private function get_user_bookings( $email ) {
		return get_posts( array(
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
			'orderby'  => 'meta_value',
			'meta_key' => 'booking_date',
			'order'    => 'ASC',
		) );
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
			return new \WP_Error( 'not_found', __( 'Booking not found.', 'bkx-alexa' ) );
		}

		$booking_email = get_post_meta( $booking_id, 'customer_email', true );

		if ( $booking_email !== $email ) {
			return new \WP_Error( 'unauthorized', __( 'You can only cancel your own bookings.', 'bkx-alexa' ) );
		}

		wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-cancelled',
		) );

		do_action( 'bkx_booking_cancelled', $booking_id );

		return true;
	}
}
