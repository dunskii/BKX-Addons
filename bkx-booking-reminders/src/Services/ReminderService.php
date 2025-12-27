<?php
/**
 * Reminder Service
 *
 * Core service for managing booking reminders.
 *
 * @package BookingX\BookingReminders\Services
 * @since   1.0.0
 */

namespace BookingX\BookingReminders\Services;

use BookingX\BookingReminders\BookingRemindersAddon;

/**
 * Reminder service class.
 *
 * @since 1.0.0
 */
class ReminderService {

	/**
	 * Addon instance.
	 *
	 * @var BookingRemindersAddon
	 */
	protected BookingRemindersAddon $addon;

	/**
	 * Email service.
	 *
	 * @var EmailService
	 */
	protected EmailService $email_service;

	/**
	 * SMS service.
	 *
	 * @var SmsService
	 */
	protected SmsService $sms_service;

	/**
	 * Constructor.
	 *
	 * @param BookingRemindersAddon $addon Addon instance.
	 * @param EmailService          $email_service Email service.
	 * @param SmsService            $sms_service SMS service.
	 */
	public function __construct(
		BookingRemindersAddon $addon,
		EmailService $email_service,
		SmsService $sms_service
	) {
		$this->addon         = $addon;
		$this->email_service = $email_service;
		$this->sms_service   = $sms_service;
	}

	/**
	 * Get reminders for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Array of reminder objects.
	 */
	public function get_reminders_for_booking( int $booking_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_scheduled_reminders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE booking_id = %d ORDER BY scheduled_at ASC',
				$table,
				$booking_id
			)
		);
	}

	/**
	 * Get pending reminders that are due.
	 *
	 * @since 1.0.0
	 * @param int $limit Maximum number of reminders to fetch.
	 * @return array Array of reminder objects.
	 */
	public function get_due_reminders( int $limit = 50 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_scheduled_reminders';
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d',
				$table,
				'pending',
				$now,
				$limit
			)
		);
	}

	/**
	 * Send a reminder.
	 *
	 * @since 1.0.0
	 * @param object $reminder Reminder object.
	 * @return array Result with success status.
	 */
	public function send_reminder( object $reminder ): array {
		$booking_id = $reminder->booking_id;
		$channel    = $reminder->channel;

		// Get booking data.
		$booking_data = $this->get_booking_data( $booking_id );

		if ( ! $booking_data ) {
			return $this->mark_reminder_failed( $reminder->id, __( 'Booking not found.', 'bkx-booking-reminders' ) );
		}

		// Check if booking status should receive reminders.
		$exclude_statuses = $this->addon->get_setting( 'exclude_statuses', array( 'bkx-cancelled', 'bkx-missed' ) );
		if ( in_array( $booking_data['status'], $exclude_statuses, true ) ) {
			return $this->mark_reminder_cancelled( $reminder->id, __( 'Booking status excluded.', 'bkx-booking-reminders' ) );
		}

		// Send based on channel.
		$result = match ( $channel ) {
			'email' => $this->email_service->send_reminder( $booking_data, $reminder ),
			'sms'   => $this->sms_service->send_reminder( $booking_data, $reminder ),
			default => array(
				'success' => false,
				'error'   => __( 'Unknown channel.', 'bkx-booking-reminders' ),
			),
		};

		if ( $result['success'] ) {
			$this->mark_reminder_sent( $reminder->id );
			$this->log_reminder( $reminder, $booking_data, 'sent', $result );
		} else {
			$this->increment_attempt( $reminder->id, $result['error'] ?? '' );

			// Mark as failed after 3 attempts.
			if ( $reminder->attempts >= 2 ) {
				$this->mark_reminder_failed( $reminder->id, $result['error'] ?? '' );
			}
		}

		return $result;
	}

	/**
	 * Send a test reminder.
	 *
	 * @since 1.0.0
	 * @param string $type  Reminder type (email/sms).
	 * @param string $email Test email address.
	 * @param string $phone Test phone number.
	 * @return array Result.
	 */
	public function send_test_reminder( string $type, string $email, string $phone ): array {
		// Create mock booking data.
		$booking_data = array(
			'id'                 => 0,
			'status'             => 'bkx-pending',
			'customer_name'      => __( 'John Doe', 'bkx-booking-reminders' ),
			'customer_first_name' => __( 'John', 'bkx-booking-reminders' ),
			'customer_email'     => $email,
			'customer_phone'     => $phone,
			'service_name'       => __( 'Sample Service', 'bkx-booking-reminders' ),
			'booking_date'       => wp_date( get_option( 'date_format' ), strtotime( '+1 day' ) ),
			'booking_time'       => '10:00 AM',
			'duration'           => __( '1 hour', 'bkx-booking-reminders' ),
			'staff_name'         => __( 'Jane Smith', 'bkx-booking-reminders' ),
			'location'           => __( '123 Main Street', 'bkx-booking-reminders' ),
		);

		// Create mock reminder object.
		$reminder = (object) array(
			'id'              => 0,
			'booking_id'      => 0,
			'reminder_type'   => 'reminder',
			'reminder_number' => 1,
			'channel'         => $type,
			'recipient'       => 'email' === $type ? $email : $phone,
		);

		if ( 'email' === $type ) {
			return $this->email_service->send_reminder( $booking_data, $reminder );
		} elseif ( 'sms' === $type ) {
			return $this->sms_service->send_reminder( $booking_data, $reminder );
		}

		return array(
			'success' => false,
			'error'   => __( 'Invalid reminder type.', 'bkx-booking-reminders' ),
		);
	}

	/**
	 * Resend a specific reminder.
	 *
	 * @since 1.0.0
	 * @param int $reminder_id Reminder ID.
	 * @return array Result.
	 */
	public function resend_reminder( int $reminder_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_scheduled_reminders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reminder = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table,
				$reminder_id
			)
		);

		if ( ! $reminder ) {
			return array(
				'success' => false,
				'error'   => __( 'Reminder not found.', 'bkx-booking-reminders' ),
			);
		}

		// Reset status for resend.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'   => 'pending',
				'attempts' => 0,
			),
			array( 'id' => $reminder_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		$reminder->status   = 'pending';
		$reminder->attempts = 0;

		return $this->send_reminder( $reminder );
	}

	/**
	 * Get booking data for template.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array|null Booking data or null.
	 */
	public function get_booking_data( int $booking_id ): ?array {
		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return null;
		}

		// Get booking meta.
		$booking_date  = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time  = get_post_meta( $booking_id, 'booking_time', true );
		$seat_id       = get_post_meta( $booking_id, 'seat_id', true );
		$base_id       = get_post_meta( $booking_id, 'base_id', true );

		// Get customer info.
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );

		// Get service name.
		$service_name = '';
		if ( $base_id ) {
			$base = get_post( $base_id );
			if ( $base ) {
				$service_name = $base->post_title;
			}
		}

		// Get staff name.
		$staff_name = '';
		if ( $seat_id ) {
			$seat = get_post( $seat_id );
			if ( $seat ) {
				$staff_name = $seat->post_title;
			}
		}

		// Get duration.
		$duration = get_post_meta( $booking_id, 'total_time', true );
		if ( $duration ) {
			$duration = $this->format_duration( $duration );
		}

		// Get business info.
		$business_name    = get_bloginfo( 'name' );
		$business_address = get_option( 'bkx_business_address', '' );
		$business_phone   = get_option( 'bkx_business_phone', '' );

		// Format date and time.
		$formatted_date = '';
		$formatted_time = '';
		if ( $booking_date ) {
			$formatted_date = wp_date( get_option( 'date_format' ), strtotime( $booking_date ) );
		}
		if ( $booking_time ) {
			$formatted_time = wp_date( get_option( 'time_format' ), strtotime( $booking_time ) );
		}

		// Extract first name.
		$name_parts          = explode( ' ', $customer_name );
		$customer_first_name = $name_parts[0] ?? $customer_name;

		return array(
			'id'                  => $booking_id,
			'status'              => $booking->post_status,
			'customer_name'       => $customer_name,
			'customer_first_name' => $customer_first_name,
			'customer_email'      => $customer_email,
			'customer_phone'      => $customer_phone,
			'service_name'        => $service_name,
			'staff_name'          => $staff_name,
			'booking_date'        => $formatted_date,
			'booking_time'        => $formatted_time,
			'booking_datetime'    => $booking_date . ' ' . $booking_time,
			'duration'            => $duration,
			'location'            => $business_address,
			'business_name'       => $business_name,
			'business_address'    => $business_address,
			'business_phone'      => $business_phone,
		);
	}

	/**
	 * Format duration in minutes to human-readable.
	 *
	 * @since 1.0.0
	 * @param int $minutes Duration in minutes.
	 * @return string Formatted duration.
	 */
	protected function format_duration( int $minutes ): string {
		$hours = floor( $minutes / 60 );
		$mins  = $minutes % 60;

		if ( $hours > 0 && $mins > 0 ) {
			return sprintf(
				/* translators: 1: hours, 2: minutes */
				__( '%1$d hour %2$d min', 'bkx-booking-reminders' ),
				$hours,
				$mins
			);
		} elseif ( $hours > 0 ) {
			return sprintf(
				/* translators: %d: number of hours */
				_n( '%d hour', '%d hours', $hours, 'bkx-booking-reminders' ),
				$hours
			);
		} else {
			return sprintf(
				/* translators: %d: number of minutes */
				__( '%d min', 'bkx-booking-reminders' ),
				$mins
			);
		}
	}

	/**
	 * Mark reminder as sent.
	 *
	 * @since 1.0.0
	 * @param int $reminder_id Reminder ID.
	 * @return void
	 */
	protected function mark_reminder_sent( int $reminder_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_scheduled_reminders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'  => 'sent',
				'sent_at' => current_time( 'mysql' ),
			),
			array( 'id' => $reminder_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark reminder as failed.
	 *
	 * @since 1.0.0
	 * @param int    $reminder_id Reminder ID.
	 * @param string $error Error message.
	 * @return array Result.
	 */
	protected function mark_reminder_failed( int $reminder_id, string $error ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_scheduled_reminders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'     => 'failed',
				'last_error' => $error,
			),
			array( 'id' => $reminder_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'success' => false,
			'error'   => $error,
		);
	}

	/**
	 * Mark reminder as cancelled.
	 *
	 * @since 1.0.0
	 * @param int    $reminder_id Reminder ID.
	 * @param string $reason Cancellation reason.
	 * @return array Result.
	 */
	protected function mark_reminder_cancelled( int $reminder_id, string $reason ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_scheduled_reminders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'     => 'cancelled',
				'last_error' => $reason,
			),
			array( 'id' => $reminder_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'success' => true,
			'message' => $reason,
		);
	}

	/**
	 * Increment attempt counter.
	 *
	 * @since 1.0.0
	 * @param int    $reminder_id Reminder ID.
	 * @param string $error Last error message.
	 * @return void
	 */
	protected function increment_attempt( int $reminder_id, string $error ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_scheduled_reminders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET attempts = attempts + 1, last_error = %s WHERE id = %d',
				$table,
				$error,
				$reminder_id
			)
		);
	}

	/**
	 * Log a sent reminder.
	 *
	 * @since 1.0.0
	 * @param object $reminder Reminder object.
	 * @param array  $booking_data Booking data.
	 * @param string $status Log status.
	 * @param array  $result Send result.
	 * @return void
	 */
	protected function log_reminder( object $reminder, array $booking_data, string $status, array $result ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_reminder_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'reminder_id'       => $reminder->id,
				'booking_id'        => $reminder->booking_id,
				'channel'           => $reminder->channel,
				'recipient'         => $reminder->recipient,
				'subject'           => $result['subject'] ?? null,
				'message_preview'   => isset( $result['message'] ) ? substr( $result['message'], 0, 500 ) : null,
				'status'            => $status,
				'provider_response' => isset( $result['provider_response'] ) ? wp_json_encode( $result['provider_response'] ) : null,
				'sent_at'           => current_time( 'mysql' ),
			)
		);
	}
}
