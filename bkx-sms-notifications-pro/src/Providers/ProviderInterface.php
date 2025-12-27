<?php
/**
 * SMS Provider Interface
 *
 * @package BookingX\SmsNotificationsPro\Providers
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro\Providers;

use WP_Error;

/**
 * Interface for SMS providers.
 *
 * @since 1.0.0
 */
interface ProviderInterface {

	/**
	 * Send an SMS message.
	 *
	 * @param string $to Phone number in E.164 format.
	 * @param string $message Message content.
	 * @return array|WP_Error Result with message_id on success, WP_Error on failure.
	 */
	public function send( string $to, string $message );

	/**
	 * Get account balance.
	 *
	 * @return array|WP_Error Balance info or error.
	 */
	public function get_balance();

	/**
	 * Validate provider credentials.
	 *
	 * @return bool|WP_Error True if valid, WP_Error on failure.
	 */
	public function validate_credentials();

	/**
	 * Get provider name.
	 *
	 * @return string Provider name.
	 */
	public function get_name(): string;
}
