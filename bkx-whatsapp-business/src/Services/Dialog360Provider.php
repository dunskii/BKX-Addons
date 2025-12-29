<?php
/**
 * 360dialog WhatsApp Provider.
 *
 * @package BookingX\WhatsAppBusiness\Services
 * @since   1.0.0
 */

namespace BookingX\WhatsAppBusiness\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Dialog360Provider class.
 *
 * Handles communication with 360dialog's WhatsApp API.
 *
 * @since 1.0.0
 */
class Dialog360Provider {

	/**
	 * API Base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://waba.360dialog.io/v1';

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Send a text message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to      Recipient phone number.
	 * @param string $message Message text.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_text_message( $to, $message ) {
		$data = array(
			'to'   => $this->format_phone_number( $to ),
			'type' => 'text',
			'text' => array(
				'body' => $message,
			),
		);

		return $this->make_request( '/messages', $data );
	}

	/**
	 * Send a template message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to         Recipient phone number.
	 * @param string $template   Template name.
	 * @param string $language   Language code.
	 * @param array  $components Template components.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_template_message( $to, $template, $language = 'en', $components = array() ) {
		$template_data = array(
			'name'     => $template,
			'language' => array(
				'policy' => 'deterministic',
				'code'   => $language,
			),
		);

		if ( ! empty( $components ) ) {
			$template_data['components'] = $components;
		}

		$data = array(
			'to'       => $this->format_phone_number( $to ),
			'type'     => 'template',
			'template' => $template_data,
		);

		return $this->make_request( '/messages', $data );
	}

	/**
	 * Send a media message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to       Recipient phone number.
	 * @param string $type     Media type (image, video, document, audio).
	 * @param string $media_id Media URL.
	 * @param string $caption  Optional caption.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_media_message( $to, $type, $media_id, $caption = '' ) {
		$media_data = array(
			'link' => $media_id,
		);

		if ( ! empty( $caption ) && in_array( $type, array( 'image', 'video', 'document' ), true ) ) {
			$media_data['caption'] = $caption;
		}

		$data = array(
			'to'   => $this->format_phone_number( $to ),
			'type' => $type,
			$type  => $media_data,
		);

		return $this->make_request( '/messages', $data );
	}

	/**
	 * Send an interactive message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to          Recipient phone number.
	 * @param array  $interactive Interactive message data.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_interactive_message( $to, $interactive ) {
		$data = array(
			'to'          => $this->format_phone_number( $to ),
			'type'        => 'interactive',
			'interactive' => $interactive,
		);

		return $this->make_request( '/messages', $data );
	}

	/**
	 * Mark message as read.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message_id Message ID.
	 * @return array|\WP_Error Response or error.
	 */
	public function mark_as_read( $message_id ) {
		$data = array(
			'status'     => 'read',
			'message_id' => $message_id,
		);

		return $this->make_request( '/messages', $data );
	}

	/**
	 * Get templates.
	 *
	 * @since 1.0.0
	 *
	 * @return array|\WP_Error Templates or error.
	 */
	public function get_templates() {
		return $this->make_request( '/configs/templates', array(), 'GET' );
	}

	/**
	 * Create a template.
	 *
	 * @since 1.0.0
	 *
	 * @param array $template_data Template data.
	 * @return array|\WP_Error Response or error.
	 */
	public function create_template( $template_data ) {
		return $this->make_request( '/configs/templates', $template_data );
	}

	/**
	 * Delete a template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template_name Template name.
	 * @return array|\WP_Error Response or error.
	 */
	public function delete_template( $template_name ) {
		return $this->make_request( "/configs/templates/{$template_name}", array(), 'DELETE' );
	}

	/**
	 * Test connection.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error True on success or error.
	 */
	public function test_connection() {
		$result = $this->make_request( '/configs/webhook', array(), 'GET' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Set webhook URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Webhook URL.
	 * @return array|\WP_Error Response or error.
	 */
	public function set_webhook( $url ) {
		$data = array(
			'url' => $url,
		);

		return $this->make_request( '/configs/webhook', $data );
	}

	/**
	 * Make API request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @param string $method   HTTP method.
	 * @return array|\WP_Error Response or error.
	 */
	private function make_request( $endpoint, $data = array(), $method = 'POST' ) {
		$api_key = $this->settings['dialog360_api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'missing_config', __( '360dialog API key is not configured.', 'bkx-whatsapp-business' ) );
		}

		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'D360-API-KEY' => $api_key,
				'Content-Type' => 'application/json',
			),
			'timeout' => 30,
		);

		if ( 'GET' === $method && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		} elseif ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		return $this->handle_response( $response );
	}

	/**
	 * Handle API response.
	 *
	 * @since 1.0.0
	 *
	 * @param array|\WP_Error $response API response.
	 * @return array|\WP_Error Parsed response or error.
	 */
	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$error_message = $data['error']['message'] ?? __( 'Unknown API error.', 'bkx-whatsapp-business' );
			$error_code    = $data['error']['code'] ?? $code;

			return new \WP_Error( "dialog360_error_{$error_code}", $error_message );
		}

		return $data;
	}

	/**
	 * Format phone number.
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone Phone number.
	 * @return string Formatted phone number.
	 */
	private function format_phone_number( $phone ) {
		// Remove all non-numeric characters.
		$phone = preg_replace( '/[^\d]/', '', $phone );

		return $phone;
	}
}
