<?php
/**
 * Skill Handler for Amazon Alexa requests.
 *
 * @package BookingX\Alexa
 */

namespace BookingX\Alexa\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SkillHandler class.
 */
class SkillHandler {

	/**
	 * Intent handler.
	 *
	 * @var IntentHandler
	 */
	private $intent_handler;

	/**
	 * Constructor.
	 *
	 * @param IntentHandler $intent_handler Intent handler.
	 */
	public function __construct( IntentHandler $intent_handler ) {
		$this->intent_handler = $intent_handler;
	}

	/**
	 * Handle incoming Alexa request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_request( $request ) {
		$start_time = microtime( true );

		$body = $request->get_json_params();

		// Log the request.
		$this->log_request( $body );

		// Parse the request.
		$parsed = $this->parse_request( $body );

		if ( is_wp_error( $parsed ) ) {
			return $this->error_response( $parsed->get_error_message() );
		}

		// Handle based on request type.
		$response = $this->route_request( $parsed );

		// Log the response.
		$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
		$this->log_response( $parsed, $response, $processing_time );

		// Format response for Alexa.
		return $this->format_response( $response );
	}

	/**
	 * Parse Alexa request.
	 *
	 * @param array $body Request body.
	 * @return array|\WP_Error Parsed data or error.
	 */
	private function parse_request( $body ) {
		$request_type = $body['request']['type'] ?? '';
		$session_id   = $body['session']['sessionId'] ?? '';
		$user_id      = $body['session']['user']['userId'] ?? $body['context']['System']['user']['userId'] ?? '';
		$device_id    = $body['context']['System']['device']['deviceId'] ?? '';
		$access_token = $body['session']['user']['accessToken'] ?? $body['context']['System']['user']['accessToken'] ?? '';

		// Get intent info.
		$intent = '';
		$slots  = array();

		if ( $request_type === 'LaunchRequest' ) {
			$intent = 'LaunchRequest';
		} elseif ( $request_type === 'IntentRequest' ) {
			$intent = $body['request']['intent']['name'] ?? '';

			// Parse slots.
			$raw_slots = $body['request']['intent']['slots'] ?? array();
			foreach ( $raw_slots as $slot_name => $slot_data ) {
				$slots[ $slot_name ] = $slot_data['value'] ?? '';

				// Handle slot resolution (matched values).
				if ( ! empty( $slot_data['resolutions']['resolutionsPerAuthority'][0]['values'][0]['value']['name'] ) ) {
					$slots[ $slot_name ] = $slot_data['resolutions']['resolutionsPerAuthority'][0]['values'][0]['value']['name'];
				}
			}
		} elseif ( $request_type === 'SessionEndedRequest' ) {
			$intent = 'SessionEndedRequest';
		}

		if ( empty( $session_id ) && $request_type !== 'SessionEndedRequest' ) {
			return new \WP_Error( 'invalid_request', 'Missing session ID' );
		}

		return array(
			'request_type' => sanitize_text_field( $request_type ),
			'session_id'   => sanitize_text_field( $session_id ),
			'user_id'      => sanitize_text_field( $user_id ),
			'device_id'    => sanitize_text_field( $device_id ),
			'access_token' => sanitize_text_field( $access_token ),
			'intent'       => sanitize_text_field( $intent ),
			'slots'        => array_map( 'sanitize_text_field', $slots ),
			'is_new'       => ! empty( $body['session']['new'] ),
			'locale'       => $body['request']['locale'] ?? 'en-US',
		);
	}

	/**
	 * Route request to appropriate handler.
	 *
	 * @param array $parsed Parsed request.
	 * @return array Response data.
	 */
	private function route_request( $parsed ) {
		// Handle session ended.
		if ( $parsed['request_type'] === 'SessionEndedRequest' ) {
			return array( 'end_session' => true );
		}

		// Get or create session.
		$session_manager = new SessionManager();
		$session_data    = $session_manager->get_or_create(
			$parsed['session_id'],
			$parsed['user_id'],
			$parsed['device_id']
		);

		// Get user data if account is linked.
		$user_data = null;
		if ( ! empty( $parsed['access_token'] ) ) {
			$account_linker = new AccountLinker();
			$user_data      = $account_linker->get_user_by_token( $parsed['access_token'] );

			// Link Amazon user ID if not already linked.
			if ( $user_data && ! empty( $parsed['user_id'] ) ) {
				$account_linker->link_amazon_user( $parsed['user_id'], $user_data->wp_user_id );
			}
		}

		// Use existing intent if this is a continuation.
		$intent = $parsed['intent'];
		if ( empty( $intent ) && ! empty( $session_data['intent'] ) ) {
			$intent = $session_data['intent'];
		}

		// Handle the intent.
		return $this->intent_handler->handle(
			$intent,
			$parsed['slots'],
			$session_data,
			$user_data
		);
	}

	/**
	 * Format response for Alexa.
	 *
	 * @param array $response Internal response.
	 * @return \WP_REST_Response
	 */
	private function format_response( $response ) {
		$output = array(
			'version'  => '1.0',
			'response' => array(
				'shouldEndSession' => $response['end_session'] ?? false,
			),
		);

		// Add speech output.
		if ( ! empty( $response['speech'] ) ) {
			$output['response']['outputSpeech'] = array(
				'type' => 'PlainText',
				'text' => $response['speech'],
			);
		}

		// Add reprompt.
		if ( ! empty( $response['reprompt'] ) ) {
			$output['response']['reprompt'] = array(
				'outputSpeech' => array(
					'type' => 'PlainText',
					'text' => $response['reprompt'],
				),
			);
		}

		// Add card.
		if ( ! empty( $response['card'] ) ) {
			$output['response']['card'] = $response['card'];
		}

		// Add directives.
		if ( ! empty( $response['directives'] ) ) {
			$output['response']['directives'] = $response['directives'];
		}

		return rest_ensure_response( $output );
	}

	/**
	 * Generate error response.
	 *
	 * @param string $message Error message.
	 * @return \WP_REST_Response
	 */
	private function error_response( $message ) {
		return rest_ensure_response( array(
			'version'  => '1.0',
			'response' => array(
				'outputSpeech' => array(
					'type' => 'PlainText',
					'text' => $message,
				),
				'shouldEndSession' => false,
			),
		) );
	}

	/**
	 * Log incoming request.
	 *
	 * @param array $body Request body.
	 */
	private function log_request( $body ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Alexa Request: ' . wp_json_encode( $body ) );
		}
	}

	/**
	 * Log response.
	 *
	 * @param array $parsed          Parsed request.
	 * @param array $response        Response data.
	 * @param int   $processing_time Processing time in ms.
	 */
	private function log_response( $parsed, $response, $processing_time ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bkx_alexa_logs',
			array(
				'session_id'       => $parsed['session_id'],
				'booking_id'       => $response['booking_id'] ?? null,
				'intent'           => $parsed['intent'],
				'request_type'     => $parsed['request_type'],
				'request_payload'  => wp_json_encode( $parsed ),
				'response_payload' => wp_json_encode( $response ),
				'user_query'       => implode( ', ', $parsed['slots'] ),
				'response_text'    => $response['speech'] ?? '',
				'status'           => isset( $response['booking_id'] ) ? 'success' : 'handled',
				'processing_time'  => $processing_time,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}
}
