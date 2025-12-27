<?php
/**
 * SMS Service
 *
 * Handles sending SMS messages via multiple providers.
 *
 * @package BookingX\SmsNotificationsPro\Services
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro\Services;

use BookingX\SmsNotificationsPro\SmsNotificationsProAddon;
use BookingX\SmsNotificationsPro\Providers\TwilioProvider;
use BookingX\SmsNotificationsPro\Providers\VonageProvider;
use BookingX\SmsNotificationsPro\Providers\MessageBirdProvider;
use BookingX\SmsNotificationsPro\Providers\PlivoProvider;
use WP_Error;

/**
 * SMS service class.
 *
 * @since 1.0.0
 */
class SmsService {

	/**
	 * Addon instance.
	 *
	 * @var SmsNotificationsProAddon
	 */
	protected SmsNotificationsProAddon $addon;

	/**
	 * Template service.
	 *
	 * @var TemplateService
	 */
	protected TemplateService $template_service;

	/**
	 * Log table.
	 *
	 * @var string
	 */
	protected string $log_table;

	/**
	 * Constructor.
	 *
	 * @param SmsNotificationsProAddon $addon Addon instance.
	 * @param TemplateService          $template_service Template service.
	 */
	public function __construct( SmsNotificationsProAddon $addon, TemplateService $template_service ) {
		global $wpdb;

		$this->addon            = $addon;
		$this->template_service = $template_service;
		$this->log_table        = $wpdb->prefix . 'bkx_sms_log';
	}

	/**
	 * Send notification for a booking.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $type Notification type.
	 * @param string $recipient_type Recipient type (customer, staff, admin).
	 * @return array|WP_Error Result or error.
	 */
	public function send_notification( int $booking_id, string $type, string $recipient_type ) {
		// Get recipient phone.
		$phone = $this->get_recipient_phone( $booking_id, $recipient_type );

		if ( empty( $phone ) ) {
			return new WP_Error( 'no_phone', __( 'No phone number available.', 'bkx-sms-notifications-pro' ) );
		}

		// Format phone number.
		$phone = $this->format_phone_number( $phone );

		if ( ! $phone ) {
			return new WP_Error( 'invalid_phone', __( 'Invalid phone number format.', 'bkx-sms-notifications-pro' ) );
		}

		// Check rate limit.
		if ( ! $this->check_rate_limit( $phone ) ) {
			return new WP_Error( 'rate_limited', __( 'Rate limit exceeded.', 'bkx-sms-notifications-pro' ) );
		}

		// Get message template.
		$message = $this->template_service->get_parsed_template( $type, $recipient_type, $booking_id );

		if ( is_wp_error( $message ) ) {
			return $message;
		}

		// Send message.
		return $this->send( $phone, $message, $booking_id, $type, $recipient_type );
	}

	/**
	 * Send SMS message.
	 *
	 * @param string      $to Phone number.
	 * @param string      $message Message content.
	 * @param int|null    $booking_id Optional booking ID.
	 * @param string|null $type Optional message type.
	 * @param string|null $recipient_type Optional recipient type.
	 * @return array|WP_Error Result or error.
	 */
	public function send( string $to, string $message, ?int $booking_id = null, ?string $type = null, ?string $recipient_type = null ) {
		$provider = $this->get_provider();

		if ( is_wp_error( $provider ) ) {
			$this->log_message( $booking_id, $type, $recipient_type, $to, $message, 'failed', $provider->get_error_message() );
			return $provider;
		}

		// Truncate message if needed.
		$max_length = $this->addon->get_setting( 'max_message_length', 160 );
		if ( strlen( $message ) > $max_length ) {
			$message = substr( $message, 0, $max_length - 3 ) . '...';
		}

		// Send via provider.
		$result = $provider->send( $to, $message );

		// Log result.
		$status        = is_wp_error( $result ) ? 'failed' : 'sent';
		$error_message = is_wp_error( $result ) ? $result->get_error_message() : null;
		$message_id    = is_wp_error( $result ) ? null : ( $result['message_id'] ?? null );

		$this->log_message(
			$booking_id,
			$type ?? 'manual',
			$recipient_type ?? 'unknown',
			$to,
			$message,
			$status,
			$error_message,
			$message_id
		);

		return $result;
	}

	/**
	 * Send test message.
	 *
	 * @param string $phone Phone number.
	 * @return array|WP_Error Result or error.
	 */
	public function send_test_message( string $phone ) {
		$phone = $this->format_phone_number( $phone );

		if ( ! $phone ) {
			return new WP_Error( 'invalid_phone', __( 'Invalid phone number format.', 'bkx-sms-notifications-pro' ) );
		}

		$message = sprintf(
			/* translators: %s: site name */
			__( 'Test message from %s - SMS notifications are working!', 'bkx-sms-notifications-pro' ),
			get_bloginfo( 'name' )
		);

		return $this->send( $phone, $message, null, 'test', 'admin' );
	}

	/**
	 * Resend a failed message.
	 *
	 * @param int $message_id Log entry ID.
	 * @return array|WP_Error Result or error.
	 */
	public function resend_message( int $message_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$this->log_table,
				$message_id
			)
		);

		if ( ! $entry ) {
			return new WP_Error( 'not_found', __( 'Message not found.', 'bkx-sms-notifications-pro' ) );
		}

		return $this->send(
			$entry->recipient,
			$entry->message,
			$entry->booking_id,
			$entry->message_type,
			$entry->recipient_type
		);
	}

	/**
	 * Get account balance from provider.
	 *
	 * @return array|WP_Error Balance info or error.
	 */
	public function get_account_balance() {
		$provider = $this->get_provider();

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		return $provider->get_balance();
	}

	/**
	 * Get SMS history for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array History entries.
	 */
	public function get_booking_history( int $booking_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE booking_id = %d ORDER BY sent_at DESC',
				$this->log_table,
				$booking_id
			)
		);
	}

	/**
	 * Get SMS statistics.
	 *
	 * @return array Stats.
	 */
	public function get_stats(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->log_table )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sent = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $this->log_table, 'sent' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$failed = $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $this->log_table, 'failed' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$today = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE DATE(sent_at) = CURDATE() AND status = %s',
				$this->log_table,
				'sent'
			)
		);

		return array(
			'total'       => (int) $total,
			'sent'        => (int) $sent,
			'failed'      => (int) $failed,
			'today'       => (int) $today,
			'success_rate' => $total > 0 ? round( ( $sent / $total ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Get the configured SMS provider.
	 *
	 * @return object|WP_Error Provider instance or error.
	 */
	protected function get_provider() {
		$provider_name = $this->addon->get_setting( 'provider', 'twilio' );

		switch ( $provider_name ) {
			case 'twilio':
				return new TwilioProvider( $this->addon );

			case 'vonage':
				return new VonageProvider( $this->addon );

			case 'messagebird':
				return new MessageBirdProvider( $this->addon );

			case 'plivo':
				return new PlivoProvider( $this->addon );

			default:
				return new WP_Error( 'invalid_provider', __( 'Invalid SMS provider.', 'bkx-sms-notifications-pro' ) );
		}
	}

	/**
	 * Get recipient phone number.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $recipient_type Recipient type.
	 * @return string Phone number.
	 */
	protected function get_recipient_phone( int $booking_id, string $recipient_type ): string {
		switch ( $recipient_type ) {
			case 'customer':
				return get_post_meta( $booking_id, 'customer_phone', true );

			case 'staff':
				$seat_id = get_post_meta( $booking_id, 'seat_id', true );
				return get_post_meta( $seat_id, 'seat_phone', true );

			case 'admin':
				return $this->addon->get_setting( 'admin_phone', '' );

			default:
				return '';
		}
	}

	/**
	 * Format phone number to E.164.
	 *
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

		// If starts with 0, add country code.
		if ( 0 === strpos( $phone, '0' ) ) {
			$default_country = $this->addon->get_setting( 'default_country_code', '+1' );
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
	 * Check rate limit.
	 *
	 * @param string $phone Phone number.
	 * @return bool True if within limit.
	 */
	protected function check_rate_limit( string $phone ): bool {
		if ( ! $this->addon->get_setting( 'rate_limit_enabled', true ) ) {
			return true;
		}

		global $wpdb;

		$limit = $this->addon->get_setting( 'rate_limit_per_hour', 100 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE recipient = %s AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
				$this->log_table,
				$phone
			)
		);

		return (int) $count < $limit;
	}

	/**
	 * Log a message.
	 *
	 * @param int|null    $booking_id Booking ID.
	 * @param string|null $type Message type.
	 * @param string|null $recipient_type Recipient type.
	 * @param string      $recipient Phone number.
	 * @param string      $message Message content.
	 * @param string      $status Status.
	 * @param string|null $error_message Error message.
	 * @param string|null $message_id Provider message ID.
	 * @return void
	 */
	protected function log_message(
		?int $booking_id,
		?string $type,
		?string $recipient_type,
		string $recipient,
		string $message,
		string $status,
		?string $error_message = null,
		?string $message_id = null
	): void {
		if ( ! $this->addon->get_setting( 'log_messages', true ) ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->log_table,
			array(
				'booking_id'          => $booking_id,
				'message_type'        => $type ?? 'unknown',
				'recipient_type'      => $recipient_type ?? 'unknown',
				'recipient'           => $recipient,
				'message'             => $message,
				'provider'            => $this->addon->get_setting( 'provider', 'twilio' ),
				'provider_message_id' => $message_id,
				'status'              => $status,
				'error_message'       => $error_message,
			)
		);
	}
}
