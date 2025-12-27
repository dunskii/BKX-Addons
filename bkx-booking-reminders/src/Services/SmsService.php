<?php
/**
 * SMS Service
 *
 * Handles sending SMS reminders via Twilio.
 *
 * @package BookingX\BookingReminders\Services
 * @since   1.0.0
 */

namespace BookingX\BookingReminders\Services;

use BookingX\BookingReminders\BookingRemindersAddon;

/**
 * SMS service class.
 *
 * @since 1.0.0
 */
class SmsService {

	/**
	 * Addon instance.
	 *
	 * @var BookingRemindersAddon
	 */
	protected BookingRemindersAddon $addon;

	/**
	 * Twilio API URL.
	 *
	 * @var string
	 */
	protected string $twilio_api_url = 'https://api.twilio.com/2010-04-01/Accounts';

	/**
	 * Constructor.
	 *
	 * @param BookingRemindersAddon $addon Addon instance.
	 */
	public function __construct( BookingRemindersAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Check if SMS is enabled and configured.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( ! $this->addon->get_setting( 'sms_enabled', false ) ) {
			return false;
		}

		$account_sid = $this->addon->get_setting( 'twilio_account_sid', '' );
		$auth_token  = $this->addon->get_setting( 'twilio_auth_token', '' );
		$phone       = $this->addon->get_setting( 'twilio_phone_number', '' );

		return ! empty( $account_sid ) && ! empty( $auth_token ) && ! empty( $phone );
	}

	/**
	 * Send an SMS reminder.
	 *
	 * @since 1.0.0
	 * @param array  $booking_data Booking data.
	 * @param object $reminder Reminder object.
	 * @return array Result with success status.
	 */
	public function send_reminder( array $booking_data, object $reminder ): array {
		if ( ! $this->is_enabled() ) {
			return array(
				'success' => false,
				'error'   => __( 'SMS is not enabled or not configured.', 'bkx-booking-reminders' ),
			);
		}

		$to = $booking_data['customer_phone'] ?? $reminder->recipient;

		if ( empty( $to ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No phone number provided.', 'bkx-booking-reminders' ),
			);
		}

		// Format phone number.
		$to = $this->format_phone_number( $to );

		if ( ! $to ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid phone number format.', 'bkx-booking-reminders' ),
			);
		}

		// Get template.
		$template = $this->get_template( $reminder->reminder_type );

		if ( ! $template ) {
			return array(
				'success' => false,
				'error'   => __( 'SMS template not found.', 'bkx-booking-reminders' ),
			);
		}

		// Parse template.
		$message = $this->parse_template( $template->content, $booking_data );

		// Ensure message is under 160 characters for single SMS.
		if ( strlen( $message ) > 160 ) {
			$message = substr( $message, 0, 157 ) . '...';
		}

		/**
		 * Filter the SMS message before sending.
		 *
		 * @since 1.0.0
		 * @param string $message      SMS message.
		 * @param array  $booking_data Booking data.
		 * @param object $reminder     Reminder object.
		 */
		$message = apply_filters( 'bkx_reminder_sms_message', $message, $booking_data, $reminder );

		// Send via Twilio.
		$result = $this->send_via_twilio( $to, $message );

		if ( $result['success'] ) {
			$this->log( sprintf( 'SMS reminder sent to %s for booking #%d', $to, $booking_data['id'] ) );
		} else {
			$this->log( sprintf( 'Failed to send SMS to %s: %s', $to, $result['error'] ), 'error' );
		}

		return $result;
	}

	/**
	 * Send SMS via Twilio.
	 *
	 * @since 1.0.0
	 * @param string $to Phone number.
	 * @param string $message Message content.
	 * @return array Result.
	 */
	protected function send_via_twilio( string $to, string $message ): array {
		$account_sid = $this->addon->get_setting( 'twilio_account_sid', '' );
		$auth_token  = $this->addon->get_setting( 'twilio_auth_token', '' );
		$from        = $this->addon->get_setting( 'twilio_phone_number', '' );

		$url = sprintf(
			'%s/%s/Messages.json',
			$this->twilio_api_url,
			$account_sid
		);

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
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 201 === $code || 200 === $code ) {
			return array(
				'success'           => true,
				'message'           => $message,
				'provider_response' => array(
					'sid'    => $body['sid'] ?? '',
					'status' => $body['status'] ?? '',
				),
			);
		}

		$error = $body['message'] ?? __( 'Unknown Twilio error.', 'bkx-booking-reminders' );

		return array(
			'success'           => false,
			'error'             => $error,
			'provider_response' => $body,
		);
	}

	/**
	 * Get SMS template.
	 *
	 * @since 1.0.0
	 * @param string $type Template type.
	 * @return object|null Template object or null.
	 */
	protected function get_template( string $type ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_reminder_templates';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$template = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE template_type = %s AND channel = %s AND is_active = 1 ORDER BY is_default DESC LIMIT 1',
				$table,
				$type,
				'sms'
			)
		);

		return $template;
	}

	/**
	 * Parse template with booking data.
	 *
	 * @since 1.0.0
	 * @param string $template Template content.
	 * @param array  $data Booking data.
	 * @return string Parsed content.
	 */
	protected function parse_template( string $template, array $data ): string {
		$replacements = array(
			'{customer_name}'       => $data['customer_name'] ?? '',
			'{customer_first_name}' => $data['customer_first_name'] ?? '',
			'{service_name}'        => $data['service_name'] ?? '',
			'{booking_date}'        => $data['booking_date'] ?? '',
			'{booking_time}'        => $data['booking_time'] ?? '',
			'{staff_name}'          => $data['staff_name'] ?? '',
			'{business_name}'       => $data['business_name'] ?? '',
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Format phone number to E.164 format.
	 *
	 * @since 1.0.0
	 * @param string $phone Phone number.
	 * @return string|false Formatted number or false.
	 */
	protected function format_phone_number( string $phone ) {
		// Remove all non-numeric characters except +.
		$phone = preg_replace( '/[^0-9+]/', '', $phone );

		// If already in E.164 format.
		if ( preg_match( '/^\+[1-9]\d{6,14}$/', $phone ) ) {
			return $phone;
		}

		// If starts with 0, assume local number - add country code.
		if ( 0 === strpos( $phone, '0' ) ) {
			$default_country = apply_filters( 'bkx_reminder_default_country_code', '+1' );
			$phone           = $default_country . ltrim( $phone, '0' );
		}

		// If no + prefix, add it.
		if ( 0 !== strpos( $phone, '+' ) ) {
			$phone = '+' . $phone;
		}

		// Validate E.164 format.
		if ( preg_match( '/^\+[1-9]\d{6,14}$/', $phone ) ) {
			return $phone;
		}

		return false;
	}

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 * @param string $message Message to log.
	 * @param string $level Log level.
	 * @return void
	 */
	protected function log( string $message, string $level = 'info' ): void {
		if ( ! $this->addon->get_setting( 'debug_log', false ) && 'error' !== $level ) {
			return;
		}

		$log_file  = WP_CONTENT_DIR . '/bkx-reminders-debug.log';
		$timestamp = current_time( 'c' );
		$formatted = sprintf( "[%s] [%s] [SMS] %s\n", $timestamp, strtoupper( $level ), $message );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_file, $formatted, FILE_APPEND | LOCK_EX );
	}
}
