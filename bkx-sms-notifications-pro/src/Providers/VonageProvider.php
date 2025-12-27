<?php
/**
 * Vonage (Nexmo) SMS Provider
 *
 * @package BookingX\SmsNotificationsPro\Providers
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro\Providers;

use WP_Error;

/**
 * Vonage SMS provider class.
 *
 * @since 1.0.0
 */
class VonageProvider extends AbstractProvider {

	/**
	 * Vonage API base URL.
	 *
	 * @var string
	 */
	private const API_URL = 'https://rest.nexmo.com';

	/**
	 * Get provider name.
	 *
	 * @return string Provider name.
	 */
	public function get_name(): string {
		return 'Vonage';
	}

	/**
	 * Send an SMS message.
	 *
	 * @param string $to Phone number in E.164 format.
	 * @param string $message Message content.
	 * @return array|WP_Error Result with message_id on success, WP_Error on failure.
	 */
	public function send( string $to, string $message ) {
		$api_key    = $this->get_credential( 'vonage_api_key' );
		$api_secret = $this->get_credential( 'vonage_api_secret' );
		$from       = $this->get_credential( 'vonage_sender_id' );

		if ( empty( $api_key ) || empty( $api_secret ) ) {
			return new WP_Error( 'missing_credentials', __( 'Vonage credentials not configured.', 'bkx-sms-notifications-pro' ) );
		}

		// Remove + from phone number for Vonage.
		$to = ltrim( $to, '+' );

		$response = wp_remote_post(
			self::API_URL . '/sms/json',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'api_key'    => $api_key,
						'api_secret' => $api_secret,
						'to'         => $to,
						'from'       => $from,
						'text'       => $message,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['messages'] ) ) {
			return new WP_Error( 'vonage_error', __( 'Invalid response from Vonage.', 'bkx-sms-notifications-pro' ) );
		}

		$msg = $data['messages'][0];

		if ( '0' !== $msg['status'] ) {
			return new WP_Error(
				'vonage_error',
				$msg['error-text'] ?? __( 'Failed to send SMS.', 'bkx-sms-notifications-pro' ),
				array( 'status' => $msg['status'] )
			);
		}

		return array(
			'message_id' => $msg['message-id'],
			'status'     => 'sent',
			'cost'       => $msg['message-price'] ?? null,
		);
	}

	/**
	 * Get account balance.
	 *
	 * @return array|WP_Error Balance info or error.
	 */
	public function get_balance() {
		$api_key    = $this->get_credential( 'vonage_api_key' );
		$api_secret = $this->get_credential( 'vonage_api_secret' );

		if ( empty( $api_key ) || empty( $api_secret ) ) {
			return new WP_Error( 'missing_credentials', __( 'Vonage credentials not configured.', 'bkx-sms-notifications-pro' ) );
		}

		$response = wp_remote_get(
			self::API_URL . '/account/get-balance?api_key=' . $api_key . '&api_secret=' . $api_secret,
			array( 'timeout' => 30 )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['value'] ) ) {
			return new WP_Error( 'vonage_error', __( 'Failed to retrieve balance.', 'bkx-sms-notifications-pro' ) );
		}

		return array(
			'balance'  => $data['value'],
			'currency' => 'EUR',
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
			return new WP_Error( 'invalid_credentials', __( 'Invalid Vonage credentials.', 'bkx-sms-notifications-pro' ) );
		}

		return true;
	}
}
