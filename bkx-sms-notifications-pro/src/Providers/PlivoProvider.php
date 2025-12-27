<?php
/**
 * Plivo SMS Provider
 *
 * @package BookingX\SmsNotificationsPro\Providers
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro\Providers;

use WP_Error;

/**
 * Plivo SMS provider class.
 *
 * @since 1.0.0
 */
class PlivoProvider extends AbstractProvider {

	/**
	 * Plivo API base URL.
	 *
	 * @var string
	 */
	private const API_URL = 'https://api.plivo.com/v1';

	/**
	 * Get provider name.
	 *
	 * @return string Provider name.
	 */
	public function get_name(): string {
		return 'Plivo';
	}

	/**
	 * Send an SMS message.
	 *
	 * @param string $to Phone number in E.164 format.
	 * @param string $message Message content.
	 * @return array|WP_Error Result with message_id on success, WP_Error on failure.
	 */
	public function send( string $to, string $message ) {
		$auth_id    = $this->get_credential( 'plivo_auth_id' );
		$auth_token = $this->get_credential( 'plivo_auth_token' );
		$from       = $this->get_credential( 'plivo_phone_number' );

		if ( empty( $auth_id ) || empty( $auth_token ) ) {
			return new WP_Error( 'missing_credentials', __( 'Plivo credentials not configured.', 'bkx-sms-notifications-pro' ) );
		}

		// Remove + from phone numbers.
		$to   = ltrim( $to, '+' );
		$from = ltrim( $from, '+' );

		$response = wp_remote_post(
			self::API_URL . '/Account/' . $auth_id . '/Message/',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $auth_id . ':' . $auth_token ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'src'  => $from,
						'dst'  => $to,
						'text' => $message,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$error_msg = $data['error'] ?? __( 'Failed to send SMS.', 'bkx-sms-notifications-pro' );
			return new WP_Error( 'plivo_error', $error_msg );
		}

		return array(
			'message_id' => $data['message_uuid'][0] ?? null,
			'status'     => 'sent',
			'cost'       => null,
		);
	}

	/**
	 * Get account balance.
	 *
	 * @return array|WP_Error Balance info or error.
	 */
	public function get_balance() {
		$auth_id    = $this->get_credential( 'plivo_auth_id' );
		$auth_token = $this->get_credential( 'plivo_auth_token' );

		if ( empty( $auth_id ) || empty( $auth_token ) ) {
			return new WP_Error( 'missing_credentials', __( 'Plivo credentials not configured.', 'bkx-sms-notifications-pro' ) );
		}

		$response = wp_remote_get(
			self::API_URL . '/Account/' . $auth_id . '/',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $auth_id . ':' . $auth_token ),
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'plivo_error', __( 'Failed to retrieve balance.', 'bkx-sms-notifications-pro' ) );
		}

		return array(
			'balance'  => $data['cash_credits'],
			'currency' => 'USD',
		);
	}

	/**
	 * Validate provider credentials.
	 *
	 * @return bool|WP_Error True if valid, WP_Error on failure.
	 */
	public function validate_credentials() {
		$result = $this->get_balance();

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'invalid_credentials', __( 'Invalid Plivo credentials.', 'bkx-sms-notifications-pro' ) );
		}

		return true;
	}
}
