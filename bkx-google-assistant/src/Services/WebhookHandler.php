<?php
/**
 * Webhook Handler for Google Assistant requests.
 *
 * @package BookingX\GoogleAssistant
 */

namespace BookingX\GoogleAssistant\Services;

defined( 'ABSPATH' ) || exit;

/**
 * WebhookHandler class.
 */
class WebhookHandler {

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
	 * Handle incoming webhook request.
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

		// Get or create session.
		$session_manager = new SessionManager();
		$session_data    = $session_manager->get_or_create(
			$parsed['session_id'],
			$parsed['user_id']
		);

		// Get user data if account is linked.
		$user_data = null;
		if ( ! empty( $parsed['access_token'] ) ) {
			$account_linker = new AccountLinker();
			$user_data      = $account_linker->get_user_by_token( $parsed['access_token'] );

			// Link Google user ID if not already linked.
			if ( $user_data && ! empty( $parsed['user_id'] ) ) {
				$account_linker->link_google_user( $parsed['user_id'], $user_data->wp_user_id );
			}
		}

		// Handle the intent.
		$response = $this->intent_handler->handle(
			$parsed['intent'],
			$parsed['parameters'],
			$session_data,
			$user_data
		);

		// Log the response.
		$processing_time = (int) ( ( microtime( true ) - $start_time ) * 1000 );
		$this->log_response( $parsed, $response, $processing_time );

		// Format response for Google Assistant.
		return $this->format_response( $response, $parsed );
	}

	/**
	 * Parse Google Assistant request.
	 *
	 * @param array $body Request body.
	 * @return array|\WP_Error Parsed data or error.
	 */
	private function parse_request( $body ) {
		// Handle different Google Assistant request formats.

		// Actions SDK / Dialogflow format.
		$session_id   = '';
		$user_id      = '';
		$access_token = '';
		$intent       = '';
		$parameters   = array();
		$user_query   = '';

		// Extract session ID.
		if ( isset( $body['session'] ) ) {
			// Dialogflow format.
			$session_id = $body['session'];
		} elseif ( isset( $body['conversation']['conversationId'] ) ) {
			// Actions SDK format.
			$session_id = $body['conversation']['conversationId'];
		}

		// Extract user ID.
		if ( isset( $body['user']['userId'] ) ) {
			$user_id = $body['user']['userId'];
		} elseif ( isset( $body['originalDetectIntentRequest']['payload']['user']['userId'] ) ) {
			$user_id = $body['originalDetectIntentRequest']['payload']['user']['userId'];
		}

		// Extract access token for account linking.
		if ( isset( $body['user']['accessToken'] ) ) {
			$access_token = $body['user']['accessToken'];
		} elseif ( isset( $body['originalDetectIntentRequest']['payload']['user']['accessToken'] ) ) {
			$access_token = $body['originalDetectIntentRequest']['payload']['user']['accessToken'];
		}

		// Extract intent.
		if ( isset( $body['queryResult']['intent']['displayName'] ) ) {
			// Dialogflow format.
			$intent     = $body['queryResult']['intent']['displayName'];
			$parameters = $body['queryResult']['parameters'] ?? array();
			$user_query = $body['queryResult']['queryText'] ?? '';
		} elseif ( isset( $body['inputs'][0]['intent'] ) ) {
			// Actions SDK format.
			$intent = $body['inputs'][0]['intent'];

			// Extract parameters from arguments.
			if ( isset( $body['inputs'][0]['arguments'] ) ) {
				foreach ( $body['inputs'][0]['arguments'] as $arg ) {
					if ( isset( $arg['name'] ) && isset( $arg['textValue'] ) ) {
						$parameters[ $arg['name'] ] = $arg['textValue'];
					}
				}
			}

			// Get user query.
			if ( isset( $body['inputs'][0]['rawInputs'][0]['query'] ) ) {
				$user_query = $body['inputs'][0]['rawInputs'][0]['query'];
			}
		} elseif ( isset( $body['handler']['name'] ) ) {
			// Actions Builder format.
			$intent = $body['handler']['name'];

			if ( isset( $body['intent']['params'] ) ) {
				foreach ( $body['intent']['params'] as $name => $param ) {
					$parameters[ $name ] = $param['resolved'] ?? $param['original'] ?? '';
				}
			}

			if ( isset( $body['intent']['query'] ) ) {
				$user_query = $body['intent']['query'];
			}
		}

		if ( empty( $session_id ) ) {
			return new \WP_Error( 'invalid_request', 'Missing session ID' );
		}

		return array(
			'session_id'   => sanitize_text_field( $session_id ),
			'user_id'      => sanitize_text_field( $user_id ),
			'access_token' => sanitize_text_field( $access_token ),
			'intent'       => sanitize_text_field( $intent ),
			'parameters'   => array_map( 'sanitize_text_field', $parameters ),
			'user_query'   => sanitize_text_field( $user_query ),
		);
	}

	/**
	 * Format response for Google Assistant.
	 *
	 * @param array $response Internal response.
	 * @param array $parsed   Parsed request.
	 * @return \WP_REST_Response
	 */
	private function format_response( $response, $parsed ) {
		$text             = $response['text'] ?? '';
		$end_conversation = $response['end_conversation'] ?? false;
		$suggestions      = $response['suggestions'] ?? array();

		// Build response in Actions SDK / Dialogflow format.
		$output = array(
			'fulfillmentText' => $text,
			'payload'         => array(
				'google' => array(
					'expectUserResponse' => ! $end_conversation,
					'richResponse'       => array(
						'items' => array(
							array(
								'simpleResponse' => array(
									'textToSpeech' => $text,
									'displayText'  => $text,
								),
							),
						),
					),
				),
			),
		);

		// Add suggestions.
		if ( ! empty( $suggestions ) && ! $end_conversation ) {
			$suggestion_chips = array();
			foreach ( array_slice( $suggestions, 0, 8 ) as $suggestion ) {
				$suggestion_chips[] = array( 'title' => $suggestion );
			}
			$output['payload']['google']['richResponse']['suggestions'] = $suggestion_chips;
		}

		// Handle account linking request.
		if ( ! empty( $response['request_account_linking'] ) ) {
			$output['payload']['google']['systemIntent'] = array(
				'intent' => 'actions.intent.SIGN_IN',
				'data'   => array(
					'@type' => 'type.googleapis.com/google.actions.v2.SignInValueSpec',
				),
			);
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
			'fulfillmentText' => $message,
			'payload'         => array(
				'google' => array(
					'expectUserResponse' => true,
					'richResponse'       => array(
						'items' => array(
							array(
								'simpleResponse' => array(
									'textToSpeech' => $message,
								),
							),
						),
					),
				),
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
			error_log( 'Google Assistant Request: ' . wp_json_encode( $body ) );
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
			$wpdb->prefix . 'bkx_assistant_logs',
			array(
				'session_id'       => $parsed['session_id'],
				'booking_id'       => $response['booking_id'] ?? null,
				'intent'           => $parsed['intent'],
				'request_payload'  => wp_json_encode( $parsed ),
				'response_payload' => wp_json_encode( $response ),
				'user_query'       => $parsed['user_query'],
				'response_text'    => $response['text'] ?? '',
				'status'           => isset( $response['booking_id'] ) ? 'success' : 'handled',
				'processing_time'  => $processing_time,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}
}
