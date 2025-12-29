<?php
/**
 * Webhook Handler.
 *
 * @package BookingX\WhatsAppBusiness\Services
 * @since   1.0.0
 */

namespace BookingX\WhatsAppBusiness\Services;

defined( 'ABSPATH' ) || exit;

/**
 * WebhookHandler class.
 *
 * Handles incoming webhook events from WhatsApp.
 *
 * @since 1.0.0
 */
class WebhookHandler {

	/**
	 * Message service.
	 *
	 * @var MessageService
	 */
	private $message_service;

	/**
	 * Conversation service.
	 *
	 * @var ConversationService
	 */
	private $conversation_service;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param MessageService      $message_service      Message service.
	 * @param ConversationService $conversation_service Conversation service.
	 * @param array               $settings             Plugin settings.
	 */
	public function __construct( MessageService $message_service, ConversationService $conversation_service, array $settings ) {
		$this->message_service      = $message_service;
		$this->conversation_service = $conversation_service;
		$this->settings             = $settings;
	}

	/**
	 * Verify webhook (GET request).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function verify_webhook( $request ) {
		$mode      = $request->get_param( 'hub_mode' );
		$token     = $request->get_param( 'hub_verify_token' );
		$challenge = $request->get_param( 'hub_challenge' );

		$verify_token = $this->settings['webhook_verify_token'] ?? '';

		if ( 'subscribe' === $mode && $token === $verify_token ) {
			return new \WP_REST_Response( $challenge, 200 );
		}

		return new \WP_REST_Response( 'Forbidden', 403 );
	}

	/**
	 * Handle webhook (POST request).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function handle_webhook( $request ) {
		$body = $request->get_json_params();

		// Log incoming webhook for debugging.
		$this->log_webhook( $body );

		// Handle Meta Cloud API format.
		if ( isset( $body['entry'] ) ) {
			return $this->handle_cloud_api_webhook( $body );
		}

		// Handle Twilio format.
		if ( isset( $body['MessageSid'] ) ) {
			return $this->handle_twilio_webhook( $body );
		}

		// Handle 360dialog format.
		if ( isset( $body['messages'] ) || isset( $body['statuses'] ) ) {
			return $this->handle_360dialog_webhook( $body );
		}

		return new \WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/**
	 * Handle Meta Cloud API webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param array $body Webhook body.
	 * @return \WP_REST_Response Response.
	 */
	private function handle_cloud_api_webhook( $body ) {
		foreach ( $body['entry'] as $entry ) {
			$changes = $entry['changes'] ?? array();

			foreach ( $changes as $change ) {
				$value = $change['value'] ?? array();

				// Handle messages.
				if ( isset( $value['messages'] ) ) {
					foreach ( $value['messages'] as $message ) {
						$this->process_incoming_message( $message );
					}
				}

				// Handle status updates.
				if ( isset( $value['statuses'] ) ) {
					foreach ( $value['statuses'] as $status ) {
						$this->process_status_update( $status );
					}
				}
			}
		}

		return new \WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/**
	 * Handle Twilio webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param array $body Webhook body.
	 * @return \WP_REST_Response Response.
	 */
	private function handle_twilio_webhook( $body ) {
		// Twilio sends form data, convert to our format.
		$message = array(
			'id'        => $body['MessageSid'] ?? '',
			'from'      => str_replace( 'whatsapp:', '', $body['From'] ?? '' ),
			'to'        => str_replace( 'whatsapp:', '', $body['To'] ?? '' ),
			'type'      => ! empty( $body['MediaUrl0'] ) ? 'media' : 'text',
			'timestamp' => time(),
		);

		if ( 'text' === $message['type'] ) {
			$message['text'] = array( 'body' => $body['Body'] ?? '' );
		} else {
			$message['media_url'] = $body['MediaUrl0'] ?? '';
			$message['caption']   = $body['Body'] ?? '';
		}

		// Check if it's a status callback.
		if ( isset( $body['MessageStatus'] ) ) {
			$this->process_status_update(
				array(
					'id'        => $body['MessageSid'] ?? '',
					'status'    => $body['MessageStatus'] ?? '',
					'timestamp' => time(),
				)
			);
		} else {
			$this->process_incoming_message( $message );
		}

		return new \WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/**
	 * Handle 360dialog webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param array $body Webhook body.
	 * @return \WP_REST_Response Response.
	 */
	private function handle_360dialog_webhook( $body ) {
		// Handle messages.
		if ( isset( $body['messages'] ) ) {
			foreach ( $body['messages'] as $message ) {
				$this->process_incoming_message( $message );
			}
		}

		// Handle statuses.
		if ( isset( $body['statuses'] ) ) {
			foreach ( $body['statuses'] as $status ) {
				$this->process_status_update( $status );
			}
		}

		return new \WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/**
	 * Process incoming message.
	 *
	 * @since 1.0.0
	 *
	 * @param array $message Message data.
	 */
	private function process_incoming_message( $message ) {
		$message_id = $message['id'] ?? '';
		$from       = $message['from'] ?? '';
		$type       = $message['type'] ?? 'text';
		$timestamp  = $message['timestamp'] ?? time();

		// Get message content based on type.
		$content = '';
		switch ( $type ) {
			case 'text':
				$content = $message['text']['body'] ?? '';
				break;

			case 'image':
			case 'video':
			case 'audio':
			case 'document':
				$content = $message[ $type ]['caption'] ?? '[Media: ' . $type . ']';
				break;

			case 'location':
				$lat  = $message['location']['latitude'] ?? '';
				$long = $message['location']['longitude'] ?? '';
				$content = "[Location: {$lat}, {$long}]";
				break;

			case 'button':
				$content = $message['button']['text'] ?? '';
				break;

			case 'interactive':
				$interactive = $message['interactive'] ?? array();
				$content = $interactive['button_reply']['title'] ?? ( $interactive['list_reply']['title'] ?? '' );
				break;

			default:
				$content = '[Unsupported message type: ' . $type . ']';
		}

		// Log the message.
		$this->message_service->log_incoming_message( $from, $content, $type, $message_id );

		// Update or create conversation.
		$contact_name = $message['contacts'][0]['profile']['name'] ?? '';
		$conversation = $this->conversation_service->get_or_create( $from, $contact_name );
		$this->conversation_service->on_new_message( $from, $message_id, true );

		// Send auto-reply if enabled.
		if ( ! empty( $this->settings['auto_reply_enabled'] ) && ! empty( $this->settings['enable_two_way_chat'] ) ) {
			$this->send_auto_reply( $from );
		}

		/**
		 * Fires when a WhatsApp message is received.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $message      Message data.
		 * @param object $conversation Conversation object.
		 */
		do_action( 'bkx_whatsapp_message_received', $message, $conversation );
	}

	/**
	 * Process status update.
	 *
	 * @since 1.0.0
	 *
	 * @param array $status Status data.
	 */
	private function process_status_update( $status ) {
		$message_id  = $status['id'] ?? '';
		$status_name = $status['status'] ?? '';
		$timestamp   = $status['timestamp'] ?? time();

		// Map status names.
		$status_map = array(
			'sent'      => 'sent',
			'delivered' => 'delivered',
			'read'      => 'read',
			'failed'    => 'failed',
		);

		$mapped_status = $status_map[ $status_name ] ?? $status_name;

		// Update timestamp field based on status.
		$timestamp_field = '';
		switch ( $mapped_status ) {
			case 'sent':
				$timestamp_field = 'sent_at';
				break;
			case 'delivered':
				$timestamp_field = 'delivered_at';
				break;
			case 'read':
				$timestamp_field = 'read_at';
				break;
		}

		$this->message_service->update_message_status( $message_id, $mapped_status, $timestamp_field );

		// Handle failed status.
		if ( 'failed' === $mapped_status ) {
			$error_code    = $status['errors'][0]['code'] ?? '';
			$error_message = $status['errors'][0]['title'] ?? 'Message delivery failed';

			/**
			 * Fires when a WhatsApp message fails.
			 *
			 * @since 1.0.0
			 *
			 * @param string $message_id    Message ID.
			 * @param string $error_code    Error code.
			 * @param string $error_message Error message.
			 */
			do_action( 'bkx_whatsapp_message_failed', $message_id, $error_code, $error_message );
		}
	}

	/**
	 * Send auto-reply.
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone Phone number.
	 */
	private function send_auto_reply( $phone ) {
		// Check if within business hours.
		$current_time = current_time( 'H:i' );
		$start_time   = $this->settings['business_hours_start'] ?? '09:00';
		$end_time     = $this->settings['business_hours_end'] ?? '18:00';

		if ( $current_time < $start_time || $current_time > $end_time ) {
			// Outside business hours.
			$message = $this->settings['outside_hours_message'] ?? '';
		} else {
			$message = $this->settings['auto_reply_message'] ?? '';
		}

		if ( empty( $message ) ) {
			return;
		}

		// Check if we've already sent an auto-reply recently (within 5 minutes).
		$cache_key = 'bkx_whatsapp_autoreply_' . md5( $phone );
		if ( get_transient( $cache_key ) ) {
			return;
		}

		$this->message_service->send_text_message( $phone, $message );

		// Set transient to prevent spam.
		set_transient( $cache_key, true, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Log webhook for debugging.
	 *
	 * @since 1.0.0
	 *
	 * @param array $body Webhook body.
	 */
	private function log_webhook( $body ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$upload_dir = wp_upload_dir();
			$log_dir    = $upload_dir['basedir'] . '/bkx-whatsapp-logs';

			if ( ! file_exists( $log_dir ) ) {
				wp_mkdir_p( $log_dir );

				// Add .htaccess to protect logs.
				file_put_contents( $log_dir . '/.htaccess', 'deny from all' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}

			$log_file = $log_dir . '/webhook-' . gmdate( 'Y-m-d' ) . '.log';
			$log_data = gmdate( 'Y-m-d H:i:s' ) . ' - ' . wp_json_encode( $body ) . "\n";

			file_put_contents( $log_file, $log_data, FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
}
