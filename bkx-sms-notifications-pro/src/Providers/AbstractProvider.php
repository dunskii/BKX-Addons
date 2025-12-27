<?php
/**
 * Abstract SMS Provider
 *
 * @package BookingX\SmsNotificationsPro\Providers
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro\Providers;

use BookingX\SmsNotificationsPro\SmsNotificationsProAddon;
use WP_Error;

/**
 * Abstract base class for SMS providers.
 *
 * @since 1.0.0
 */
abstract class AbstractProvider implements ProviderInterface {

	/**
	 * Addon instance.
	 *
	 * @var SmsNotificationsProAddon
	 */
	protected SmsNotificationsProAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param SmsNotificationsProAddon $addon Addon instance.
	 */
	public function __construct( SmsNotificationsProAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Get decrypted setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Decrypted value.
	 */
	protected function get_credential( string $key, $default = '' ) {
		$value = $this->addon->get_setting( $key, $default );

		if ( empty( $value ) ) {
			return $default;
		}

		// Try to decrypt if it looks encrypted.
		if ( class_exists( 'BookingX\\AddonSDK\\Services\\EncryptionService' ) ) {
			$encryption = new \BookingX\AddonSDK\Services\EncryptionService();
			$decrypted  = $encryption->decrypt( $value );
			if ( false !== $decrypted ) {
				return $decrypted;
			}
		}

		return $value;
	}

	/**
	 * Make HTTP request with error handling.
	 *
	 * @param string $url Request URL.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error Response or error.
	 */
	protected function request( string $url, array $args = array() ) {
		$defaults = array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		);

		$args     = wp_parse_args( $args, $defaults );
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = $data['message'] ?? $data['error'] ?? __( 'Request failed.', 'bkx-sms-notifications-pro' );
			return new WP_Error( 'api_error', $message, array( 'status' => $code ) );
		}

		return $data;
	}

	/**
	 * Get the sender ID/number.
	 *
	 * @return string Sender ID.
	 */
	protected function get_sender(): string {
		return $this->addon->get_setting( 'sender_id', '' );
	}
}
