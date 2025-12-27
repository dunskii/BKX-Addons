<?php
/**
 * Reminder Scheduler
 *
 * Handles scheduling and processing of reminders.
 *
 * @package BookingX\BookingReminders\Schedulers
 * @since   1.0.0
 */

namespace BookingX\BookingReminders\Schedulers;

use BookingX\BookingReminders\BookingRemindersAddon;
use BookingX\BookingReminders\Services\ReminderService;

/**
 * Reminder scheduler class.
 *
 * @since 1.0.0
 */
class ReminderScheduler {

	/**
	 * Addon instance.
	 *
	 * @var BookingRemindersAddon
	 */
	protected BookingRemindersAddon $addon;

	/**
	 * Reminder service.
	 *
	 * @var ReminderService
	 */
	protected ReminderService $reminder_service;

	/**
	 * Constructor.
	 *
	 * @param BookingRemindersAddon $addon Addon instance.
	 * @param ReminderService       $reminder_service Reminder service.
	 */
	public function __construct( BookingRemindersAddon $addon, ReminderService $reminder_service ) {
		$this->addon            = $addon;
		$this->reminder_service = $reminder_service;
	}

	/**
	 * Schedule reminders for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function schedule_reminders( int $booking_id ): void {
		$booking_data = $this->reminder_service->get_booking_data( $booking_id );

		if ( ! $booking_data ) {
			return;
		}

		// Get booking datetime.
		$booking_datetime = $booking_data['booking_datetime'];
		if ( empty( $booking_datetime ) ) {
			return;
		}

		$booking_timestamp = strtotime( $booking_datetime );

		// Don't schedule reminders for past bookings.
		if ( $booking_timestamp < time() ) {
			return;
		}

		// Schedule email reminders.
		if ( $this->addon->get_setting( 'email_enabled', true ) ) {
			$this->schedule_channel_reminders( $booking_id, $booking_data, $booking_timestamp, 'email' );
		}

		// Schedule SMS reminders.
		if ( $this->addon->get_setting( 'sms_enabled', false ) ) {
			$this->schedule_channel_reminders( $booking_id, $booking_data, $booking_timestamp, 'sms' );
		}
	}

	/**
	 * Schedule reminders for a specific channel.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param array  $booking_data Booking data.
	 * @param int    $booking_timestamp Booking timestamp.
	 * @param string $channel Channel (email/sms).
	 * @return void
	 */
	protected function schedule_channel_reminders( int $booking_id, array $booking_data, int $booking_timestamp, string $channel ): void {
		$prefix = 'email' === $channel ? 'email' : 'sms';

		// Get reminder times configuration.
		$reminders = array(
			1 => array(
				'enabled' => $this->addon->get_setting( "{$prefix}_reminder_1_enabled", true ),
				'time'    => $this->addon->get_setting( "{$prefix}_reminder_1_time", 24 ),
			),
			2 => array(
				'enabled' => $this->addon->get_setting( "{$prefix}_reminder_2_enabled", false ),
				'time'    => $this->addon->get_setting( "{$prefix}_reminder_2_time", 2 ),
			),
			3 => array(
				'enabled' => $this->addon->get_setting( "{$prefix}_reminder_3_enabled", false ),
				'time'    => $this->addon->get_setting( "{$prefix}_reminder_3_time", 48 ),
			),
		);

		$recipient = 'email' === $channel
			? $booking_data['customer_email']
			: $booking_data['customer_phone'];

		if ( empty( $recipient ) ) {
			return;
		}

		foreach ( $reminders as $number => $config ) {
			if ( ! $config['enabled'] ) {
				continue;
			}

			// Calculate scheduled time (hours before booking).
			$scheduled_timestamp = $booking_timestamp - ( $config['time'] * HOUR_IN_SECONDS );

			// Don't schedule if already in the past.
			if ( $scheduled_timestamp < time() ) {
				continue;
			}

			$this->insert_reminder(
				$booking_id,
				'reminder',
				$number,
				$channel,
				$recipient,
				$scheduled_timestamp
			);
		}
	}

	/**
	 * Schedule follow-up for a completed booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function schedule_followup( int $booking_id ): void {
		$booking_data = $this->reminder_service->get_booking_data( $booking_id );

		if ( ! $booking_data ) {
			return;
		}

		$followup_time = $this->addon->get_setting( 'followup_time', 24 );
		$scheduled_at  = time() + ( $followup_time * HOUR_IN_SECONDS );

		// Only email follow-ups for now.
		if ( ! empty( $booking_data['customer_email'] ) ) {
			$this->insert_reminder(
				$booking_id,
				'followup',
				1,
				'email',
				$booking_data['customer_email'],
				$scheduled_at
			);
		}
	}

	/**
	 * Cancel all reminders for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function cancel_reminders( int $booking_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_scheduled_reminders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'status' => 'cancelled' ),
			array(
				'booking_id' => $booking_id,
				'status'     => 'pending',
			),
			array( '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Process pending reminders.
	 *
	 * Called by cron every 15 minutes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_pending_reminders(): void {
		$reminders = $this->reminder_service->get_due_reminders( 50 );

		if ( empty( $reminders ) ) {
			return;
		}

		foreach ( $reminders as $reminder ) {
			$this->reminder_service->send_reminder( $reminder );
		}

		/**
		 * Fires after reminders have been processed.
		 *
		 * @since 1.0.0
		 * @param int $count Number of reminders processed.
		 */
		do_action( 'bkx_booking_reminders_processed', count( $reminders ) );
	}

	/**
	 * Insert a reminder into the database.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $type Reminder type.
	 * @param int    $number Reminder number.
	 * @param string $channel Channel (email/sms).
	 * @param string $recipient Recipient email/phone.
	 * @param int    $scheduled_timestamp Scheduled timestamp.
	 * @return int|false Insert ID or false.
	 */
	protected function insert_reminder(
		int $booking_id,
		string $type,
		int $number,
		string $channel,
		string $recipient,
		int $scheduled_timestamp
	) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_scheduled_reminders';

		// Check if reminder already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE booking_id = %d AND reminder_type = %s AND reminder_number = %d AND channel = %s AND status = %s',
				$table,
				$booking_id,
				$type,
				$number,
				$channel,
				'pending'
			)
		);

		if ( $exists ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'booking_id'      => $booking_id,
				'reminder_type'   => $type,
				'reminder_number' => $number,
				'channel'         => $channel,
				'recipient'       => $recipient,
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', $scheduled_timestamp ),
				'status'          => 'pending',
				'attempts'        => 0,
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d' )
		);

		return $wpdb->insert_id;
	}
}
