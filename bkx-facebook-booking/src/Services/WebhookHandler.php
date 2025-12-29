<?php
/**
 * Facebook Webhook Handler.
 *
 * @package BookingX\FacebookBooking\Services
 */

namespace BookingX\FacebookBooking\Services;

defined( 'ABSPATH' ) || exit;

/**
 * WebhookHandler class.
 *
 * Processes incoming Facebook webhooks.
 */
class WebhookHandler {

	/**
	 * Facebook API instance.
	 *
	 * @var FacebookApi
	 */
	private $api;

	/**
	 * Page Manager instance.
	 *
	 * @var PageManager
	 */
	private $page_manager;

	/**
	 * Booking Sync instance.
	 *
	 * @var BookingSync
	 */
	private $booking_sync;

	/**
	 * Constructor.
	 *
	 * @param FacebookApi $api          Facebook API instance.
	 * @param PageManager $page_manager Page Manager instance.
	 * @param BookingSync $booking_sync Booking Sync instance.
	 */
	public function __construct( FacebookApi $api, PageManager $page_manager, BookingSync $booking_sync ) {
		$this->api          = $api;
		$this->page_manager = $page_manager;
		$this->booking_sync = $booking_sync;
	}

	/**
	 * Handle webhook verification (GET request).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_verification( $request ) {
		$mode      = $request->get_param( 'hub_mode' );
		$token     = $request->get_param( 'hub_verify_token' );
		$challenge = $request->get_param( 'hub_challenge' );

		$settings     = get_option( 'bkx_fb_booking_settings', array() );
		$verify_token = $settings['verify_token'] ?? '';

		if ( 'subscribe' === $mode && $token === $verify_token ) {
			$this->log_webhook( 'verification_success', null, array( 'challenge' => $challenge ) );
			return new \WP_REST_Response( intval( $challenge ), 200 );
		}

		$this->log_webhook( 'verification_failed', null, array(
			'mode'  => $mode,
			'token' => $token,
		) );

		return new \WP_Error( 'verification_failed', 'Verification failed', array( 'status' => 403 ) );
	}

	/**
	 * Handle webhook event (POST request).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_event( $request ) {
		$payload = $request->get_body();
		$signature = $request->get_header( 'X-Hub-Signature-256' );

		// Verify signature.
		if ( ! $this->api->verify_webhook_signature( $payload, $signature ) ) {
			$this->log_webhook( 'signature_invalid', null, array( 'signature' => $signature ) );
			return new \WP_REST_Response( 'Invalid signature', 403 );
		}

		$data = json_decode( $payload, true );

		if ( 'page' !== ( $data['object'] ?? '' ) ) {
			return new \WP_REST_Response( 'OK', 200 );
		}

		// Process each entry.
		foreach ( $data['entry'] ?? array() as $entry ) {
			$page_id = $entry['id'] ?? null;

			// Process messaging events.
			if ( isset( $entry['messaging'] ) ) {
				foreach ( $entry['messaging'] as $event ) {
					$this->process_messaging_event( $page_id, $event );
				}
			}

			// Process changes (feed events).
			if ( isset( $entry['changes'] ) ) {
				foreach ( $entry['changes'] as $change ) {
					$this->process_change_event( $page_id, $change );
				}
			}
		}

		return new \WP_REST_Response( 'OK', 200 );
	}

	/**
	 * Process a messaging event.
	 *
	 * @param string $page_id Page ID.
	 * @param array  $event   Event data.
	 */
	private function process_messaging_event( $page_id, $event ) {
		$sender_id = $event['sender']['id'] ?? null;
		$timestamp = $event['timestamp'] ?? time() * 1000;

		// Log the event.
		$this->log_webhook( 'messaging', $page_id, $event );

		if ( isset( $event['message'] ) ) {
			$this->handle_message( $page_id, $sender_id, $event['message'] );
		} elseif ( isset( $event['postback'] ) ) {
			$this->handle_postback( $page_id, $sender_id, $event['postback'] );
		} elseif ( isset( $event['optin'] ) ) {
			$this->handle_optin( $page_id, $sender_id, $event['optin'] );
		}
	}

	/**
	 * Handle incoming message.
	 *
	 * @param string $page_id   Page ID.
	 * @param string $sender_id Sender PSID.
	 * @param array  $message   Message data.
	 */
	private function handle_message( $page_id, $sender_id, $message ) {
		$text = $message['text'] ?? '';
		$quick_reply = $message['quick_reply']['payload'] ?? null;

		// Get page access token.
		$access_token = $this->page_manager->get_access_token( $page_id );

		if ( ! $access_token ) {
			return;
		}

		// Handle quick reply payload.
		if ( $quick_reply ) {
			$this->handle_quick_reply( $page_id, $sender_id, $quick_reply, $access_token );
			return;
		}

		// Parse message for booking intent.
		$intent = $this->parse_message_intent( $text );

		switch ( $intent ) {
			case 'book':
			case 'appointment':
			case 'schedule':
				$this->send_booking_prompt( $page_id, $sender_id, $access_token );
				break;

			case 'services':
			case 'menu':
			case 'options':
				$this->send_services_list( $page_id, $sender_id, $access_token );
				break;

			case 'help':
				$this->send_help_message( $page_id, $sender_id, $access_token );
				break;

			case 'cancel':
				$this->send_cancel_prompt( $page_id, $sender_id, $access_token );
				break;

			case 'status':
			case 'mybookings':
				$this->send_booking_status( $page_id, $sender_id, $access_token );
				break;

			default:
				$this->send_default_response( $page_id, $sender_id, $access_token );
				break;
		}
	}

	/**
	 * Parse message to determine intent.
	 *
	 * @param string $text Message text.
	 * @return string
	 */
	private function parse_message_intent( $text ) {
		$text = strtolower( trim( $text ) );

		$booking_keywords = array( 'book', 'appointment', 'schedule', 'reserve', 'make a booking' );
		$services_keywords = array( 'services', 'menu', 'options', 'what do you offer', 'list' );
		$help_keywords = array( 'help', 'support', 'how', 'info', 'information' );
		$cancel_keywords = array( 'cancel', 'remove', 'delete' );
		$status_keywords = array( 'status', 'my booking', 'my appointments', 'upcoming' );

		foreach ( $booking_keywords as $keyword ) {
			if ( strpos( $text, $keyword ) !== false ) {
				return 'book';
			}
		}

		foreach ( $services_keywords as $keyword ) {
			if ( strpos( $text, $keyword ) !== false ) {
				return 'services';
			}
		}

		foreach ( $help_keywords as $keyword ) {
			if ( strpos( $text, $keyword ) !== false ) {
				return 'help';
			}
		}

		foreach ( $cancel_keywords as $keyword ) {
			if ( strpos( $text, $keyword ) !== false ) {
				return 'cancel';
			}
		}

		foreach ( $status_keywords as $keyword ) {
			if ( strpos( $text, $keyword ) !== false ) {
				return 'status';
			}
		}

		return 'unknown';
	}

	/**
	 * Handle quick reply payload.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $payload      Payload string.
	 * @param string $access_token Page access token.
	 */
	private function handle_quick_reply( $page_id, $sender_id, $payload, $access_token ) {
		$parts = explode( ':', $payload );
		$action = $parts[0] ?? '';
		$data = $parts[1] ?? '';

		switch ( $action ) {
			case 'SELECT_SERVICE':
				$this->handle_service_selection( $page_id, $sender_id, $data, $access_token );
				break;

			case 'SELECT_DATE':
				$this->handle_date_selection( $page_id, $sender_id, $data, $access_token );
				break;

			case 'SELECT_TIME':
				$this->handle_time_selection( $page_id, $sender_id, $data, $access_token );
				break;

			case 'CONFIRM_BOOKING':
				$this->handle_booking_confirmation( $page_id, $sender_id, $data, $access_token );
				break;

			case 'CANCEL_BOOKING':
				$this->handle_booking_cancellation( $page_id, $sender_id, $data, $access_token );
				break;

			case 'BOOK_NOW':
				$this->send_booking_prompt( $page_id, $sender_id, $access_token );
				break;

			case 'VIEW_SERVICES':
				$this->send_services_list( $page_id, $sender_id, $access_token );
				break;
		}
	}

	/**
	 * Handle postback event.
	 *
	 * @param string $page_id   Page ID.
	 * @param string $sender_id Sender PSID.
	 * @param array  $postback  Postback data.
	 */
	private function handle_postback( $page_id, $sender_id, $postback ) {
		$payload = $postback['payload'] ?? '';
		$access_token = $this->page_manager->get_access_token( $page_id );

		if ( ! $access_token ) {
			return;
		}

		switch ( $payload ) {
			case 'GET_STARTED':
				$this->send_welcome_message( $page_id, $sender_id, $access_token );
				break;

			case 'BOOK_NOW':
				$this->send_booking_prompt( $page_id, $sender_id, $access_token );
				break;

			case 'VIEW_SERVICES':
				$this->send_services_list( $page_id, $sender_id, $access_token );
				break;

			case 'MY_BOOKINGS':
				$this->send_booking_status( $page_id, $sender_id, $access_token );
				break;

			case 'CONTACT_US':
				$this->send_contact_info( $page_id, $sender_id, $access_token );
				break;
		}
	}

	/**
	 * Handle opt-in event.
	 *
	 * @param string $page_id   Page ID.
	 * @param string $sender_id Sender PSID.
	 * @param array  $optin     Opt-in data.
	 */
	private function handle_optin( $page_id, $sender_id, $optin ) {
		$ref = $optin['ref'] ?? '';

		$this->log_webhook( 'optin', $page_id, array(
			'sender_id' => $sender_id,
			'ref'       => $ref,
		) );

		// User opted in via Send to Messenger plugin.
		$access_token = $this->page_manager->get_access_token( $page_id );

		if ( $access_token ) {
			$this->send_welcome_message( $page_id, $sender_id, $access_token );
		}
	}

	/**
	 * Process a feed change event.
	 *
	 * @param string $page_id Page ID.
	 * @param array  $change  Change data.
	 */
	private function process_change_event( $page_id, $change ) {
		$field = $change['field'] ?? '';
		$value = $change['value'] ?? array();

		$this->log_webhook( 'feed_' . $field, $page_id, $value );

		// Handle specific feed events if needed.
		// For now, just log them.
	}

	/**
	 * Send welcome message.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $access_token Page access token.
	 */
	private function send_welcome_message( $page_id, $sender_id, $access_token ) {
		// Get user profile.
		$profile = $this->api->get_user_profile( $sender_id, $access_token );
		$name = ! is_wp_error( $profile ) ? ( $profile['first_name'] ?? 'there' ) : 'there';

		$settings = get_option( 'bkx_fb_booking_settings', array() );
		$business_name = $settings['business_name'] ?? get_bloginfo( 'name' );

		$text = sprintf(
			/* translators: 1: User name, 2: Business name */
			__( 'Hi %1$s! Welcome to %2$s. How can I help you today?', 'bkx-facebook-booking' ),
			$name,
			$business_name
		);

		$quick_replies = array(
			array(
				'title'   => __( 'Book Now', 'bkx-facebook-booking' ),
				'payload' => 'BOOK_NOW',
			),
			array(
				'title'   => __( 'View Services', 'bkx-facebook-booking' ),
				'payload' => 'VIEW_SERVICES',
			),
			array(
				'title'   => __( 'My Bookings', 'bkx-facebook-booking' ),
				'payload' => 'MY_BOOKINGS:' . $sender_id,
			),
		);

		$this->api->send_quick_replies( $sender_id, $text, $quick_replies, $access_token );
	}

	/**
	 * Send booking prompt.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $access_token Page access token.
	 */
	private function send_booking_prompt( $page_id, $sender_id, $access_token ) {
		$services = $this->page_manager->get_page_services( $page_id );

		if ( empty( $services ) ) {
			$this->api->send_message(
				$sender_id,
				array( 'text' => __( 'Sorry, no services are currently available for booking. Please contact us directly.', 'bkx-facebook-booking' ) ),
				$access_token
			);
			return;
		}

		// Create service selection carousel.
		$elements = array();

		foreach ( array_slice( $services, 0, 10 ) as $service ) {
			$subtitle = '';
			if ( $service->duration_minutes ) {
				$subtitle .= sprintf( __( '%d min', 'bkx-facebook-booking' ), $service->duration_minutes );
			}
			if ( $service->price ) {
				$subtitle .= ( $subtitle ? ' - ' : '' ) . wc_price( $service->price );
			}

			$elements[] = array(
				'title'    => $service->name,
				'subtitle' => $subtitle ?: $service->description,
				'buttons'  => array(
					array(
						'type'    => 'postback',
						'title'   => __( 'Select', 'bkx-facebook-booking' ),
						'payload' => 'SELECT_SERVICE:' . $service->bkx_service_id,
					),
				),
			);
		}

		if ( ! empty( $elements ) ) {
			$this->api->send_carousel( $sender_id, $elements, $access_token );
		}
	}

	/**
	 * Send services list.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $access_token Page access token.
	 */
	private function send_services_list( $page_id, $sender_id, $access_token ) {
		$services = $this->page_manager->get_page_services( $page_id );

		if ( empty( $services ) ) {
			$this->api->send_message(
				$sender_id,
				array( 'text' => __( 'No services are currently available.', 'bkx-facebook-booking' ) ),
				$access_token
			);
			return;
		}

		$text = __( "Here are our available services:\n\n", 'bkx-facebook-booking' );

		foreach ( $services as $service ) {
			$text .= sprintf(
				"%s - %s (%d min)\n",
				$service->name,
				$service->price ? wc_price( $service->price ) : __( 'Free', 'bkx-facebook-booking' ),
				$service->duration_minutes
			);
		}

		$text .= "\n" . __( 'Would you like to book an appointment?', 'bkx-facebook-booking' );

		$quick_replies = array(
			array(
				'title'   => __( 'Book Now', 'bkx-facebook-booking' ),
				'payload' => 'BOOK_NOW',
			),
		);

		$this->api->send_quick_replies( $sender_id, $text, $quick_replies, $access_token );
	}

	/**
	 * Send help message.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $access_token Page access token.
	 */
	private function send_help_message( $page_id, $sender_id, $access_token ) {
		$text = __( "I can help you with:\n\n", 'bkx-facebook-booking' );
		$text .= __( "- Book an appointment\n", 'bkx-facebook-booking' );
		$text .= __( "- View our services\n", 'bkx-facebook-booking' );
		$text .= __( "- Check your bookings\n", 'bkx-facebook-booking' );
		$text .= __( "- Cancel a booking\n\n", 'bkx-facebook-booking' );
		$text .= __( 'Just type what you need or tap one of the options below!', 'bkx-facebook-booking' );

		$quick_replies = array(
			array(
				'title'   => __( 'Book Now', 'bkx-facebook-booking' ),
				'payload' => 'BOOK_NOW',
			),
			array(
				'title'   => __( 'My Bookings', 'bkx-facebook-booking' ),
				'payload' => 'MY_BOOKINGS:' . $sender_id,
			),
		);

		$this->api->send_quick_replies( $sender_id, $text, $quick_replies, $access_token );
	}

	/**
	 * Send default response.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $access_token Page access token.
	 */
	private function send_default_response( $page_id, $sender_id, $access_token ) {
		$text = __( "I'm sorry, I didn't quite understand that. Here's what I can help you with:", 'bkx-facebook-booking' );

		$quick_replies = array(
			array(
				'title'   => __( 'Book Now', 'bkx-facebook-booking' ),
				'payload' => 'BOOK_NOW',
			),
			array(
				'title'   => __( 'View Services', 'bkx-facebook-booking' ),
				'payload' => 'VIEW_SERVICES',
			),
			array(
				'title'   => __( 'Help', 'bkx-facebook-booking' ),
				'payload' => 'HELP',
			),
		);

		$this->api->send_quick_replies( $sender_id, $text, $quick_replies, $access_token );
	}

	/**
	 * Handle service selection.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $service_id   Service ID.
	 * @param string $access_token Page access token.
	 */
	private function handle_service_selection( $page_id, $sender_id, $service_id, $access_token ) {
		// Store selection in session.
		$this->store_session_data( $sender_id, 'selected_service', $service_id );

		// Get available dates.
		$dates = $this->get_available_dates( $service_id );

		if ( empty( $dates ) ) {
			$this->api->send_message(
				$sender_id,
				array( 'text' => __( 'Sorry, no available dates for this service. Please try another service or contact us directly.', 'bkx-facebook-booking' ) ),
				$access_token
			);
			return;
		}

		$text = __( 'Great choice! Please select a date:', 'bkx-facebook-booking' );

		$quick_replies = array();
		foreach ( array_slice( $dates, 0, 10 ) as $date ) {
			$quick_replies[] = array(
				'title'   => wp_date( 'D, M j', strtotime( $date ) ),
				'payload' => 'SELECT_DATE:' . $date,
			);
		}

		$this->api->send_quick_replies( $sender_id, $text, $quick_replies, $access_token );
	}

	/**
	 * Handle date selection.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $date         Selected date.
	 * @param string $access_token Page access token.
	 */
	private function handle_date_selection( $page_id, $sender_id, $date, $access_token ) {
		$this->store_session_data( $sender_id, 'selected_date', $date );

		$service_id = $this->get_session_data( $sender_id, 'selected_service' );

		// Get available times.
		$times = $this->get_available_times( $service_id, $date );

		if ( empty( $times ) ) {
			$this->api->send_message(
				$sender_id,
				array( 'text' => __( 'Sorry, no available times on this date. Please select another date.', 'bkx-facebook-booking' ) ),
				$access_token
			);
			return;
		}

		$text = sprintf(
			/* translators: %s: Selected date */
			__( 'Available times for %s:', 'bkx-facebook-booking' ),
			wp_date( 'l, F j', strtotime( $date ) )
		);

		$quick_replies = array();
		foreach ( array_slice( $times, 0, 10 ) as $time ) {
			$quick_replies[] = array(
				'title'   => wp_date( 'g:i A', strtotime( $time ) ),
				'payload' => 'SELECT_TIME:' . $time,
			);
		}

		$this->api->send_quick_replies( $sender_id, $text, $quick_replies, $access_token );
	}

	/**
	 * Handle time selection.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $time         Selected time.
	 * @param string $access_token Page access token.
	 */
	private function handle_time_selection( $page_id, $sender_id, $time, $access_token ) {
		$this->store_session_data( $sender_id, 'selected_time', $time );

		$service_id = $this->get_session_data( $sender_id, 'selected_service' );
		$date = $this->get_session_data( $sender_id, 'selected_date' );

		// Get service details.
		$service = get_post( $service_id );
		$service_name = $service ? $service->post_title : __( 'Service', 'bkx-facebook-booking' );
		$price = get_post_meta( $service_id, 'base_price', true );

		$text = __( "Please confirm your booking:\n\n", 'bkx-facebook-booking' );
		$text .= sprintf( __( "Service: %s\n", 'bkx-facebook-booking' ), $service_name );
		$text .= sprintf( __( "Date: %s\n", 'bkx-facebook-booking' ), wp_date( 'l, F j, Y', strtotime( $date ) ) );
		$text .= sprintf( __( "Time: %s\n", 'bkx-facebook-booking' ), wp_date( 'g:i A', strtotime( $time ) ) );

		if ( $price ) {
			$text .= sprintf( __( "Price: %s\n", 'bkx-facebook-booking' ), wc_price( $price ) );
		}

		$quick_replies = array(
			array(
				'title'   => __( 'Confirm', 'bkx-facebook-booking' ),
				'payload' => 'CONFIRM_BOOKING:' . $sender_id,
			),
			array(
				'title'   => __( 'Cancel', 'bkx-facebook-booking' ),
				'payload' => 'CANCEL_FLOW',
			),
		);

		$this->api->send_quick_replies( $sender_id, $text, $quick_replies, $access_token );
	}

	/**
	 * Handle booking confirmation.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $data         Additional data.
	 * @param string $access_token Page access token.
	 */
	private function handle_booking_confirmation( $page_id, $sender_id, $data, $access_token ) {
		$service_id = $this->get_session_data( $sender_id, 'selected_service' );
		$date = $this->get_session_data( $sender_id, 'selected_date' );
		$time = $this->get_session_data( $sender_id, 'selected_time' );

		if ( ! $service_id || ! $date || ! $time ) {
			$this->api->send_message(
				$sender_id,
				array( 'text' => __( 'Sorry, something went wrong. Please start a new booking.', 'bkx-facebook-booking' ) ),
				$access_token
			);
			return;
		}

		// Get user profile for booking.
		$profile = $this->api->get_user_profile( $sender_id, $access_token );
		$customer_name = ! is_wp_error( $profile )
			? trim( ( $profile['first_name'] ?? '' ) . ' ' . ( $profile['last_name'] ?? '' ) )
			: '';

		// Create the booking.
		$booking_data = array(
			'page_id'       => $page_id,
			'service_id'    => $service_id,
			'fb_user_id'    => $sender_id,
			'customer_name' => $customer_name,
			'booking_date'  => $date,
			'start_time'    => $time,
			'source'        => 'messenger',
		);

		$result = $this->booking_sync->create_facebook_booking( $booking_data );

		if ( is_wp_error( $result ) ) {
			$this->api->send_message(
				$sender_id,
				array( 'text' => __( 'Sorry, we could not complete your booking. Please try again or contact us directly.', 'bkx-facebook-booking' ) ),
				$access_token
			);
			return;
		}

		// Clear session.
		$this->clear_session( $sender_id );

		// Send confirmation.
		$service = get_post( $service_id );

		$text = __( "Your booking has been confirmed!\n\n", 'bkx-facebook-booking' );
		$text .= sprintf( __( "Booking #: %s\n", 'bkx-facebook-booking' ), $result['fb_booking_id'] );
		$text .= sprintf( __( "Service: %s\n", 'bkx-facebook-booking' ), $service ? $service->post_title : '' );
		$text .= sprintf( __( "Date: %s\n", 'bkx-facebook-booking' ), wp_date( 'l, F j, Y', strtotime( $date ) ) );
		$text .= sprintf( __( "Time: %s\n\n", 'bkx-facebook-booking' ), wp_date( 'g:i A', strtotime( $time ) ) );
		$text .= __( "We'll send you a reminder before your appointment. See you soon!", 'bkx-facebook-booking' );

		$this->api->send_message( $sender_id, array( 'text' => $text ), $access_token );
	}

	/**
	 * Send cancel prompt.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $access_token Page access token.
	 */
	private function send_cancel_prompt( $page_id, $sender_id, $access_token ) {
		$bookings = $this->booking_sync->get_user_bookings( $sender_id, 'upcoming' );

		if ( empty( $bookings ) ) {
			$this->api->send_message(
				$sender_id,
				array( 'text' => __( "You don't have any upcoming bookings to cancel.", 'bkx-facebook-booking' ) ),
				$access_token
			);
			return;
		}

		$elements = array();

		foreach ( array_slice( $bookings, 0, 10 ) as $booking ) {
			$elements[] = array(
				'title'    => sprintf(
					'#%s - %s',
					$booking->fb_booking_id,
					wp_date( 'M j, g:i A', strtotime( $booking->booking_date . ' ' . $booking->start_time ) )
				),
				'subtitle' => $booking->customer_name,
				'buttons'  => array(
					array(
						'type'    => 'postback',
						'title'   => __( 'Cancel', 'bkx-facebook-booking' ),
						'payload' => 'CANCEL_BOOKING:' . $booking->fb_booking_id,
					),
				),
			);
		}

		$this->api->send_carousel( $sender_id, $elements, $access_token );
	}

	/**
	 * Handle booking cancellation.
	 *
	 * @param string $page_id       Page ID.
	 * @param string $sender_id     Sender PSID.
	 * @param string $fb_booking_id Facebook booking ID.
	 * @param string $access_token  Page access token.
	 */
	private function handle_booking_cancellation( $page_id, $sender_id, $fb_booking_id, $access_token ) {
		$result = $this->booking_sync->cancel_booking( $fb_booking_id );

		if ( $result ) {
			$this->api->send_message(
				$sender_id,
				array( 'text' => sprintf(
					/* translators: %s: Booking ID */
					__( 'Booking #%s has been cancelled. We hope to see you again soon!', 'bkx-facebook-booking' ),
					$fb_booking_id
				) ),
				$access_token
			);
		} else {
			$this->api->send_message(
				$sender_id,
				array( 'text' => __( 'Sorry, we could not cancel this booking. Please contact us directly.', 'bkx-facebook-booking' ) ),
				$access_token
			);
		}
	}

	/**
	 * Send booking status.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $access_token Page access token.
	 */
	private function send_booking_status( $page_id, $sender_id, $access_token ) {
		$bookings = $this->booking_sync->get_user_bookings( $sender_id, 'upcoming' );

		if ( empty( $bookings ) ) {
			$text = __( "You don't have any upcoming bookings.\n\nWould you like to book an appointment?", 'bkx-facebook-booking' );

			$quick_replies = array(
				array(
					'title'   => __( 'Book Now', 'bkx-facebook-booking' ),
					'payload' => 'BOOK_NOW',
				),
			);

			$this->api->send_quick_replies( $sender_id, $text, $quick_replies, $access_token );
			return;
		}

		$text = __( "Your upcoming bookings:\n\n", 'bkx-facebook-booking' );

		foreach ( $bookings as $booking ) {
			$text .= sprintf(
				"#%s - %s at %s (%s)\n",
				$booking->fb_booking_id,
				wp_date( 'M j', strtotime( $booking->booking_date ) ),
				wp_date( 'g:i A', strtotime( $booking->start_time ) ),
				ucfirst( $booking->status )
			);
		}

		$quick_replies = array(
			array(
				'title'   => __( 'Book Another', 'bkx-facebook-booking' ),
				'payload' => 'BOOK_NOW',
			),
			array(
				'title'   => __( 'Cancel Booking', 'bkx-facebook-booking' ),
				'payload' => 'CANCEL_PROMPT',
			),
		);

		$this->api->send_quick_replies( $sender_id, $text, $quick_replies, $access_token );
	}

	/**
	 * Send contact info.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $sender_id    Sender PSID.
	 * @param string $access_token Page access token.
	 */
	private function send_contact_info( $page_id, $sender_id, $access_token ) {
		$settings = get_option( 'bkx_fb_booking_settings', array() );

		$text = __( "Contact us:\n\n", 'bkx-facebook-booking' );

		if ( ! empty( $settings['contact_phone'] ) ) {
			$text .= sprintf( __( "Phone: %s\n", 'bkx-facebook-booking' ), $settings['contact_phone'] );
		}

		if ( ! empty( $settings['contact_email'] ) ) {
			$text .= sprintf( __( "Email: %s\n", 'bkx-facebook-booking' ), $settings['contact_email'] );
		}

		$text .= sprintf( __( "\nWebsite: %s", 'bkx-facebook-booking' ), home_url() );

		$this->api->send_message( $sender_id, array( 'text' => $text ), $access_token );
	}

	/**
	 * Get available dates for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	private function get_available_dates( $service_id ) {
		$dates = array();
		$start = new \DateTime();
		$end = new \DateTime( '+30 days' );

		// Get availability from BookingX.
		if ( function_exists( 'bkx_get_available_dates' ) ) {
			return bkx_get_available_dates( $service_id, $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) );
		}

		// Fallback: return next 14 days.
		for ( $i = 0; $i < 14; $i++ ) {
			$date = new \DateTime( "+{$i} days" );
			// Skip Sundays.
			if ( 0 !== (int) $date->format( 'w' ) ) {
				$dates[] = $date->format( 'Y-m-d' );
			}
		}

		return $dates;
	}

	/**
	 * Get available times for a service on a date.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date (Y-m-d).
	 * @return array
	 */
	private function get_available_times( $service_id, $date ) {
		// Get availability from BookingX.
		if ( function_exists( 'bkx_get_available_times' ) ) {
			return bkx_get_available_times( $service_id, $date );
		}

		// Fallback: return standard business hours.
		$times = array();
		$start = 9; // 9 AM
		$end = 17;  // 5 PM

		for ( $hour = $start; $hour < $end; $hour++ ) {
			$times[] = sprintf( '%02d:00:00', $hour );
			$times[] = sprintf( '%02d:30:00', $hour );
		}

		return $times;
	}

	/**
	 * Store session data.
	 *
	 * @param string $sender_id Sender PSID.
	 * @param string $key       Data key.
	 * @param mixed  $value     Data value.
	 */
	private function store_session_data( $sender_id, $key, $value ) {
		$session = get_transient( 'bkx_fb_session_' . $sender_id );

		if ( ! is_array( $session ) ) {
			$session = array();
		}

		$session[ $key ] = $value;

		set_transient( 'bkx_fb_session_' . $sender_id, $session, HOUR_IN_SECONDS );
	}

	/**
	 * Get session data.
	 *
	 * @param string $sender_id Sender PSID.
	 * @param string $key       Data key.
	 * @return mixed
	 */
	private function get_session_data( $sender_id, $key ) {
		$session = get_transient( 'bkx_fb_session_' . $sender_id );

		if ( ! is_array( $session ) ) {
			return null;
		}

		return $session[ $key ] ?? null;
	}

	/**
	 * Clear session.
	 *
	 * @param string $sender_id Sender PSID.
	 */
	private function clear_session( $sender_id ) {
		delete_transient( 'bkx_fb_session_' . $sender_id );
	}

	/**
	 * Log webhook event.
	 *
	 * @param string $event_type Event type.
	 * @param string $page_id    Page ID.
	 * @param array  $payload    Event payload.
	 */
	private function log_webhook( $event_type, $page_id, $payload ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_webhooks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $table, array(
			'event_type' => $event_type,
			'page_id'    => $page_id,
			'payload'    => wp_json_encode( $payload ),
			'created_at' => current_time( 'mysql' ),
		) );
	}
}
