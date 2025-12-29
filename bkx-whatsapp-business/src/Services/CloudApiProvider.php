<?php
/**
 * WhatsApp Cloud API Provider.
 *
 * @package BookingX\WhatsAppBusiness\Services
 * @since   1.0.0
 */

namespace BookingX\WhatsAppBusiness\Services;

defined( 'ABSPATH' ) || exit;

/**
 * CloudApiProvider class.
 *
 * Handles communication with Meta's WhatsApp Business Cloud API.
 *
 * @since 1.0.0
 */
class CloudApiProvider {

	/**
	 * API Base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://graph.facebook.com/v18.0';

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
		$phone_number_id = $this->settings['phone_number_id'] ?? '';

		if ( empty( $phone_number_id ) ) {
			return new \WP_Error( 'missing_config', __( 'Phone Number ID is not configured.', 'bkx-whatsapp-business' ) );
		}

		$data = array(
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $this->format_phone_number( $to ),
			'type'              => 'text',
			'text'              => array(
				'preview_url' => false,
				'body'        => $message,
			),
		);

		return $this->make_request( "/{$phone_number_id}/messages", $data );
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
		$phone_number_id = $this->settings['phone_number_id'] ?? '';

		if ( empty( $phone_number_id ) ) {
			return new \WP_Error( 'missing_config', __( 'Phone Number ID is not configured.', 'bkx-whatsapp-business' ) );
		}

		$template_data = array(
			'name'     => $template,
			'language' => array(
				'code' => $language,
			),
		);

		if ( ! empty( $components ) ) {
			$template_data['components'] = $components;
		}

		$data = array(
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $this->format_phone_number( $to ),
			'type'              => 'template',
			'template'          => $template_data,
		);

		return $this->make_request( "/{$phone_number_id}/messages", $data );
	}

	/**
	 * Send a media message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to       Recipient phone number.
	 * @param string $type     Media type (image, video, document, audio).
	 * @param string $media_id Media ID or URL.
	 * @param string $caption  Optional caption.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_media_message( $to, $type, $media_id, $caption = '' ) {
		$phone_number_id = $this->settings['phone_number_id'] ?? '';

		if ( empty( $phone_number_id ) ) {
			return new \WP_Error( 'missing_config', __( 'Phone Number ID is not configured.', 'bkx-whatsapp-business' ) );
		}

		$media_data = array();

		if ( filter_var( $media_id, FILTER_VALIDATE_URL ) ) {
			$media_data['link'] = $media_id;
		} else {
			$media_data['id'] = $media_id;
		}

		if ( ! empty( $caption ) && in_array( $type, array( 'image', 'video', 'document' ), true ) ) {
			$media_data['caption'] = $caption;
		}

		$data = array(
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $this->format_phone_number( $to ),
			'type'              => $type,
			$type               => $media_data,
		);

		return $this->make_request( "/{$phone_number_id}/messages", $data );
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
		$phone_number_id = $this->settings['phone_number_id'] ?? '';

		if ( empty( $phone_number_id ) ) {
			return new \WP_Error( 'missing_config', __( 'Phone Number ID is not configured.', 'bkx-whatsapp-business' ) );
		}

		$data = array(
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $this->format_phone_number( $to ),
			'type'              => 'interactive',
			'interactive'       => $interactive,
		);

		return $this->make_request( "/{$phone_number_id}/messages", $data );
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
		$phone_number_id = $this->settings['phone_number_id'] ?? '';

		if ( empty( $phone_number_id ) ) {
			return new \WP_Error( 'missing_config', __( 'Phone Number ID is not configured.', 'bkx-whatsapp-business' ) );
		}

		$data = array(
			'messaging_product' => 'whatsapp',
			'status'            => 'read',
			'message_id'        => $message_id,
		);

		return $this->make_request( "/{$phone_number_id}/messages", $data );
	}

	/**
	 * Get templates.
	 *
	 * @since 1.0.0
	 *
	 * @return array|\WP_Error Templates or error.
	 */
	public function get_templates() {
		$business_account_id = $this->settings['business_account_id'] ?? '';

		if ( empty( $business_account_id ) ) {
			return new \WP_Error( 'missing_config', __( 'Business Account ID is not configured.', 'bkx-whatsapp-business' ) );
		}

		return $this->make_request( "/{$business_account_id}/message_templates", array(), 'GET' );
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
		$business_account_id = $this->settings['business_account_id'] ?? '';

		if ( empty( $business_account_id ) ) {
			return new \WP_Error( 'missing_config', __( 'Business Account ID is not configured.', 'bkx-whatsapp-business' ) );
		}

		return $this->make_request( "/{$business_account_id}/message_templates", $template_data );
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
		$business_account_id = $this->settings['business_account_id'] ?? '';

		if ( empty( $business_account_id ) ) {
			return new \WP_Error( 'missing_config', __( 'Business Account ID is not configured.', 'bkx-whatsapp-business' ) );
		}

		return $this->make_request(
			"/{$business_account_id}/message_templates",
			array( 'name' => $template_name ),
			'DELETE'
		);
	}

	/**
	 * Upload media.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path File path.
	 * @param string $mime_type MIME type.
	 * @return array|\WP_Error Media ID or error.
	 */
	public function upload_media( $file_path, $mime_type ) {
		$phone_number_id = $this->settings['phone_number_id'] ?? '';

		if ( empty( $phone_number_id ) ) {
			return new \WP_Error( 'missing_config', __( 'Phone Number ID is not configured.', 'bkx-whatsapp-business' ) );
		}

		$access_token = $this->settings['access_token'] ?? '';
		$url = self::API_BASE_URL . "/{$phone_number_id}/media";

		$boundary = wp_generate_uuid4();
		$body     = '';

		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"messaging_product\"\r\n\r\n";
		$body .= "whatsapp\r\n";

		$body .= "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . basename( $file_path ) . "\"\r\n";
		$body .= "Content-Type: {$mime_type}\r\n\r\n";
		$body .= file_get_contents( $file_path ) . "\r\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$body .= "--{$boundary}--";

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => "Bearer {$access_token}",
					'Content-Type'  => "multipart/form-data; boundary={$boundary}",
				),
				'body'    => $body,
				'timeout' => 60,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Get media URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $media_id Media ID.
	 * @return array|\WP_Error Media URL or error.
	 */
	public function get_media_url( $media_id ) {
		return $this->make_request( "/{$media_id}", array(), 'GET' );
	}

	/**
	 * Test connection.
	 *
	 * @since 1.0.0
	 *
	 * @return true|\WP_Error True on success or error.
	 */
	public function test_connection() {
		$phone_number_id = $this->settings['phone_number_id'] ?? '';

		if ( empty( $phone_number_id ) ) {
			return new \WP_Error( 'missing_config', __( 'Phone Number ID is not configured.', 'bkx-whatsapp-business' ) );
		}

		$result = $this->make_request( "/{$phone_number_id}", array(), 'GET' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
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
		$access_token = $this->settings['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return new \WP_Error( 'missing_token', __( 'Access token is not configured.', 'bkx-whatsapp-business' ) );
		}

		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => "Bearer {$access_token}",
				'Content-Type'  => 'application/json',
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

			return new \WP_Error( "api_error_{$error_code}", $error_message );
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
		// Remove all non-numeric characters except +.
		$phone = preg_replace( '/[^\d+]/', '', $phone );

		// Remove leading + if present.
		$phone = ltrim( $phone, '+' );

		return $phone;
	}
}
