<?php
/**
 * MessageBird SMS Provider
 *
 * @package BookingX\SmsNotificationsPro\Providers
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro\Providers;

use WP_Error;

/**
 * MessageBird SMS provider class.
 *
 * @since 1.0.0
 */
class MessageBirdProvider extends AbstractProvider {

	/**
	 * MessageBird API base URL.
	 *
	 * @var string
	 */
	private const API_URL = 'https://rest.messagebird.com';

	/**
	 * Get provider name.
	 *
	 * @return string Provider name.
	 */
	public function get_name(): string {
		return 'MessageBird';
	}

	/**
	 * Send an SMS message.
	 *
	 * @param string $to Phone number in E.164 format.
	 * @param string $message Message content.
	 * @return array|WP_Error Result with message_id on success, WP_Error on failure.
	 */
	public function send( string $to, string $message ) {
		$api_key = $this->get_credential( 'messagebird_api_key' );
		$from    = $this->get_credential( 'messagebird_originator' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_credentials', __( 'MessageBird API key not configured.', 'bkx-sms-notifications-pro' ) );
		}

		// Remove + from phone number.
		$to = ltrim( $to, '+' );

		$response = wp_remote_post(
			self::API_URL . '/messages',
			array(
				'headers' => array(
					'Authorization' => 'AccessKey ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'originator' => $from,
						'recipients' => array( $to ),
						'body'       => $message,
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
			$error_msg = __( 'Failed to send SMS.', 'bkx-sms-notifications-pro' );
			if ( isset( $data['errors'][0]['description'] ) ) {
				$error_msg = $data['errors'][0]['description'];
			}
			return new WP_Error( 'messagebird_error', $error_msg );
		}

		return array(
			'message_id' => $data['id'],
			'status'     => $data['recipients']['items'][0]['status'] ?? 'sent',
			'cost'       => null,
		);
	}

	/**
	 * Get account balance.
	 *
	 * @return array|WP_Error Balance info or error.
	 */
	public function get_balance() {
		$api_key = $this->get_credential( 'messagebird_api_key' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_credentials', __( 'MessageBird API key not configured.', 'bkx-sms-notifications-pro' ) );
		}

		$response = wp_remote_get(
			self::API_URL . '/balance',
			array(
				'headers' => array(
					'Authorization' => 'AccessKey ' . $api_key,
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
			return new WP_Error( 'messagebird_error', __( 'Failed to retrieve balance.', 'bkx-sms-notifications-pro' ) );
		}

		return array(
			'balance'  => $data['amount'],
			'currency' => $data['type'],
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
			return new WP_Error( 'invalid_credentials', __( 'Invalid MessageBird credentials.', 'bkx-sms-notifications-pro' ) );
		}

		return true;
	}
}
