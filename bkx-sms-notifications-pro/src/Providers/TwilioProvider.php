<?php
/**
 * Twilio SMS Provider
 *
 * @package BookingX\SmsNotificationsPro\Providers
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro\Providers;

use WP_Error;

/**
 * Twilio SMS provider class.
 *
 * @since 1.0.0
 */
class TwilioProvider extends AbstractProvider {

	/**
	 * Twilio API base URL.
	 *
	 * @var string
	 */
	private const API_URL = 'https://api.twilio.com/2010-04-01';

	/**
	 * Get provider name.
	 *
	 * @return string Provider name.
	 */
	public function get_name(): string {
		return 'Twilio';
	}

	/**
	 * Send an SMS message.
	 *
	 * @param string $to Phone number in E.164 format.
	 * @param string $message Message content.
	 * @return array|WP_Error Result with message_id on success, WP_Error on failure.
	 */
	public function send( string $to, string $message ) {
		$account_sid = $this->get_credential( 'twilio_account_sid' );
		$auth_token  = $this->get_credential( 'twilio_auth_token' );
		$from        = $this->get_credential( 'twilio_phone_number' );

		if ( empty( $account_sid ) || empty( $auth_token ) || empty( $from ) ) {
			return new WP_Error( 'missing_credentials', __( 'Twilio credentials not configured.', 'bkx-sms-notifications-pro' ) );
		}

		$url = self::API_URL . '/Accounts/' . $account_sid . '/Messages.json';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'To'   => $to,
					'From' => $from,
					'Body' => $message,
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
			$message = $data['message'] ?? __( 'Failed to send SMS.', 'bkx-sms-notifications-pro' );
			return new WP_Error( 'twilio_error', $message, array( 'code' => $data['code'] ?? null ) );
		}

		return array(
			'message_id' => $data['sid'],
			'status'     => $data['status'],
			'cost'       => $data['price'] ?? null,
		);
	}

	/**
	 * Get account balance.
	 *
	 * @return array|WP_Error Balance info or error.
	 */
	public function get_balance() {
		$account_sid = $this->get_credential( 'twilio_account_sid' );
		$auth_token  = $this->get_credential( 'twilio_auth_token' );

		if ( empty( $account_sid ) || empty( $auth_token ) ) {
			return new WP_Error( 'missing_credentials', __( 'Twilio credentials not configured.', 'bkx-sms-notifications-pro' ) );
		}

		$url = self::API_URL . '/Accounts/' . $account_sid . '/Balance.json';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
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
			return new WP_Error( 'twilio_error', __( 'Failed to retrieve balance.', 'bkx-sms-notifications-pro' ) );
		}

		return array(
			'balance'  => $data['balance'],
			'currency' => $data['currency'],
		);
	}

	/**
	 * Validate provider credentials.
	 *
	 * @return bool|WP_Error True if valid, WP_Error on failure.
	 */
	public function validate_credentials() {
		$account_sid = $this->get_credential( 'twilio_account_sid' );
		$auth_token  = $this->get_credential( 'twilio_auth_token' );

		if ( empty( $account_sid ) || empty( $auth_token ) ) {
			return new WP_Error( 'missing_credentials', __( 'Account SID and Auth Token are required.', 'bkx-sms-notifications-pro' ) );
		}

		$url = self::API_URL . '/Accounts/' . $account_sid . '.json';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error( 'invalid_credentials', __( 'Invalid Twilio credentials.', 'bkx-sms-notifications-pro' ) );
		}

		return true;
	}
}
