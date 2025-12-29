<?php
/**
 * Twilio WhatsApp Provider.
 *
 * @package BookingX\WhatsAppBusiness\Services
 * @since   1.0.0
 */

namespace BookingX\WhatsAppBusiness\Services;

defined( 'ABSPATH' ) || exit;

/**
 * TwilioProvider class.
 *
 * Handles communication with Twilio's WhatsApp API.
 *
 * @since 1.0.0
 */
class TwilioProvider {

	/**
	 * API Base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.twilio.com/2010-04-01';

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
		$account_sid  = $this->settings['twilio_account_sid'] ?? '';
		$phone_number = $this->settings['twilio_phone_number'] ?? '';

		if ( empty( $account_sid ) || empty( $phone_number ) ) {
			return new \WP_Error( 'missing_config', __( 'Twilio credentials are not configured.', 'bkx-whatsapp-business' ) );
		}

		$data = array(
			'From' => 'whatsapp:' . $this->format_phone_number( $phone_number ),
			'To'   => 'whatsapp:' . $this->format_phone_number( $to ),
			'Body' => $message,
		);

		return $this->make_request( "/Accounts/{$account_sid}/Messages.json", $data );
	}

	/**
	 * Send a template message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to         Recipient phone number.
	 * @param string $template   Template SID.
	 * @param string $language   Language code (unused for Twilio).
	 * @param array  $variables  Template variables.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_template_message( $to, $template, $language = 'en', $variables = array() ) {
		$account_sid  = $this->settings['twilio_account_sid'] ?? '';
		$phone_number = $this->settings['twilio_phone_number'] ?? '';

		if ( empty( $account_sid ) || empty( $phone_number ) ) {
			return new \WP_Error( 'missing_config', __( 'Twilio credentials are not configured.', 'bkx-whatsapp-business' ) );
		}

		$data = array(
			'From'               => 'whatsapp:' . $this->format_phone_number( $phone_number ),
			'To'                 => 'whatsapp:' . $this->format_phone_number( $to ),
			'ContentSid'         => $template,
			'ContentVariables'   => wp_json_encode( $variables ),
		);

		return $this->make_request( "/Accounts/{$account_sid}/Messages.json", $data );
	}

	/**
	 * Send a media message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to       Recipient phone number.
	 * @param string $type     Media type (unused for Twilio).
	 * @param string $media_url Media URL.
	 * @param string $caption  Optional caption.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_media_message( $to, $type, $media_url, $caption = '' ) {
		$account_sid  = $this->settings['twilio_account_sid'] ?? '';
		$phone_number = $this->settings['twilio_phone_number'] ?? '';

		if ( empty( $account_sid ) || empty( $phone_number ) ) {
			return new \WP_Error( 'missing_config', __( 'Twilio credentials are not configured.', 'bkx-whatsapp-business' ) );
		}

		$data = array(
			'From'     => 'whatsapp:' . $this->format_phone_number( $phone_number ),
			'To'       => 'whatsapp:' . $this->format_phone_number( $to ),
			'MediaUrl' => $media_url,
		);

		if ( ! empty( $caption ) ) {
			$data['Body'] = $caption;
		}

		return $this->make_request( "/Accounts/{$account_sid}/Messages.json", $data );
	}

	/**
	 * Send an interactive message.
	 *
	 * Twilio uses Content Templates for interactive messages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to          Recipient phone number.
	 * @param array  $interactive Interactive message data.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_interactive_message( $to, $interactive ) {
		// Twilio doesn't support raw interactive messages.
		// They require Content Templates.
		return new \WP_Error(
			'not_supported',
			__( 'Interactive messages require Twilio Content Templates.', 'bkx-whatsapp-business' )
		);
	}

	/**
	 * Mark message as read.
	 *
	 * Twilio doesn't support marking messages as read.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message_id Message ID.
	 * @return true Always returns true.
	 */
	public function mark_as_read( $message_id ) {
		// Twilio handles read receipts automatically.
		return true;
	}

	/**
	 * Get templates.
	 *
	 * @since 1.0.0
	 *
	 * @return array|\WP_Error Templates or error.
	 */
	public function get_templates() {
		$account_sid = $this->settings['twilio_account_sid'] ?? '';

		if ( empty( $account_sid ) ) {
			return new \WP_Error( 'missing_config', __( 'Twilio Account SID is not configured.', 'bkx-whatsapp-business' ) );
		}

		// Twilio Content Templates API.
		return $this->make_request( "/Accounts/{$account_sid}/Content.json", array(), 'GET' );
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
		$account_sid = $this->settings['twilio_account_sid'] ?? '';

		if ( empty( $account_sid ) ) {
			return new \WP_Error( 'missing_config', __( 'Twilio Account SID is not configured.', 'bkx-whatsapp-business' ) );
		}

		return $this->make_request( "/Accounts/{$account_sid}/Content.json", $template_data );
	}

	/**
	 * Delete a template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template_sid Template SID.
	 * @return array|\WP_Error Response or error.
	 */
	public function delete_template( $template_sid ) {
		$account_sid = $this->settings['twilio_account_sid'] ?? '';

		if ( empty( $account_sid ) ) {
			return new \WP_Error( 'missing_config', __( 'Twilio Account SID is not configured.', 'bkx-whatsapp-business' ) );
		}

		return $this->make_request( "/Accounts/{$account_sid}/Content/{$template_sid}.json", array(), 'DELETE' );
	}

	/**
	 * Test connection.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error True on success or error.
	 */
	public function test_connection() {
		$account_sid = $this->settings['twilio_account_sid'] ?? '';

		if ( empty( $account_sid ) ) {
			return new \WP_Error( 'missing_config', __( 'Twilio Account SID is not configured.', 'bkx-whatsapp-business' ) );
		}

		$result = $this->make_request( "/Accounts/{$account_sid}.json", array(), 'GET' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get message status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message_sid Message SID.
	 * @return array|\WP_Error Message status or error.
	 */
	public function get_message_status( $message_sid ) {
		$account_sid = $this->settings['twilio_account_sid'] ?? '';

		if ( empty( $account_sid ) ) {
			return new \WP_Error( 'missing_config', __( 'Twilio Account SID is not configured.', 'bkx-whatsapp-business' ) );
		}

		return $this->make_request( "/Accounts/{$account_sid}/Messages/{$message_sid}.json", array(), 'GET' );
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
		$account_sid = $this->settings['twilio_account_sid'] ?? '';
		$auth_token  = $this->settings['twilio_auth_token'] ?? '';

		if ( empty( $account_sid ) || empty( $auth_token ) ) {
			return new \WP_Error( 'missing_config', __( 'Twilio credentials are not configured.', 'bkx-whatsapp-business' ) );
		}

		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( "{$account_sid}:{$auth_token}" ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'timeout' => 30,
		);

		if ( 'GET' === $method && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		} elseif ( ! empty( $data ) ) {
			$args['body'] = $data;
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
			$error_message = $data['message'] ?? __( 'Unknown API error.', 'bkx-whatsapp-business' );
			$error_code    = $data['code'] ?? $code;

			return new \WP_Error( "twilio_error_{$error_code}", $error_message );
		}

		return $data;
	}

	/**
	 * Format phone number.
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone Phone number.
	 * @return string Formatted phone number with +.
	 */
	private function format_phone_number( $phone ) {
		// Remove all non-numeric characters except +.
		$phone = preg_replace( '/[^\d+]/', '', $phone );

		// Add + if not present.
		if ( strpos( $phone, '+' ) !== 0 ) {
			$phone = '+' . $phone;
		}

		return $phone;
	}
}
