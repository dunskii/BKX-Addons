<?php
/**
 * Sync Service
 *
 * @package BookingX\OutlookCalendar
 * @since   1.0.0
 */

namespace BookingX\OutlookCalendar\Services;

use BookingX\OutlookCalendar\OutlookCalendarAddon;

/**
 * Class SyncService
 *
 * Handles two-way sync between BookingX and Outlook.
 *
 * @since 1.0.0
 */
class SyncService {

	/**
	 * Calendar service instance.
	 *
	 * @var CalendarService
	 */
	private CalendarService $calendar_service;

	/**
	 * Addon instance.
	 *
	 * @var OutlookCalendarAddon
	 */
	private OutlookCalendarAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param CalendarService      $calendar_service Calendar service.
	 * @param OutlookCalendarAddon $addon            Addon instance.
	 */
	public function __construct( CalendarService $calendar_service, OutlookCalendarAddon $addon ) {
		$this->calendar_service = $calendar_service;
		$this->addon            = $addon;
	}

	/**
	 * Sync events from Outlook to BookingX.
	 *
	 * This checks for cancelled/modified events in Outlook
	 * and updates the corresponding bookings.
	 *
	 * @since 1.0.0
	 * @return array Sync results.
	 */
	public function sync_from_outlook(): array {
		$results = array(
			'checked'  => 0,
			'updated'  => 0,
			'cancelled' => 0,
			'errors'   => 0,
		);

		// Get bookings with Outlook event IDs from the last 30 days.
		$bookings = $this->get_synced_bookings();

		foreach ( $bookings as $booking ) {
			++$results['checked'];

			$event_id = get_post_meta( $booking->ID, '_outlook_event_id', true );

			if ( empty( $event_id ) ) {
				continue;
			}

			// Check if event still exists.
			$event = $this->addon->get_api()->request( 'GET', "me/events/{$event_id}" );

			if ( is_wp_error( $event ) ) {
				// Event was deleted in Outlook.
				if ( $this->addon->get_setting( 'sync_cancellations', false ) ) {
					$this->cancel_booking( $booking->ID );
					++$results['cancelled'];
				}
				continue;
			}

			// Check if event was cancelled.
			if ( isset( $event['isCancelled'] ) && $event['isCancelled'] ) {
				if ( $this->addon->get_setting( 'sync_cancellations', false ) ) {
					$this->cancel_booking( $booking->ID );
					++$results['cancelled'];
				}
				continue;
			}

			// Check if event time changed.
			if ( $this->addon->get_setting( 'sync_changes', false ) ) {
				$updated = $this->check_event_changes( $booking->ID, $event );
				if ( $updated ) {
					++$results['updated'];
				}
			}
		}

		$this->addon->update_setting( 'last_sync', current_time( 'mysql' ) );

		return $results;
	}

	/**
	 * Get bookings that have been synced to Outlook.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_synced_bookings(): array {
		$args = array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
			'posts_per_page' => 100,
			'meta_query'     => array(
				array(
					'key'     => '_outlook_event_id',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'booking_date',
					'value'   => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		);

		return get_posts( $args );
	}

	/**
	 * Cancel a booking due to Outlook event deletion.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private function cancel_booking( int $booking_id ): void {
		wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => 'bkx-cancelled',
			)
		);

		update_post_meta( $booking_id, '_cancelled_via', 'outlook_sync' );
		delete_post_meta( $booking_id, '_outlook_event_id' );

		$this->addon->log( sprintf( 'Cancelled booking %d due to Outlook event deletion', $booking_id ) );

		/**
		 * Fires when a booking is cancelled via Outlook sync.
		 *
		 * @since 1.0.0
		 * @param int $booking_id Booking ID.
		 */
		do_action( 'bkx_outlook_booking_cancelled', $booking_id );
	}

	/**
	 * Check if Outlook event has changed and update booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $event      Outlook event data.
	 * @return bool Whether booking was updated.
	 */
	private function check_event_changes( int $booking_id, array $event ): bool {
		$current_date = get_post_meta( $booking_id, 'booking_date', true );
		$current_time = get_post_meta( $booking_id, 'booking_time', true );

		// Parse event start time.
		$event_start = new \DateTime(
			$event['start']['dateTime'],
			new \DateTimeZone( $event['start']['timeZone'] ?? 'UTC' )
		);
		$event_start->setTimezone( wp_timezone() );

		$event_date = $event_start->format( 'Y-m-d' );
		$event_time = $event_start->format( 'H:i' );

		// Check if changed.
		if ( $current_date === $event_date && $current_time === $event_time ) {
			return false;
		}

		// Update booking.
		update_post_meta( $booking_id, 'booking_date', $event_date );
		update_post_meta( $booking_id, 'booking_time', $event_time );
		update_post_meta( $booking_id, '_last_outlook_sync', current_time( 'mysql' ) );

		$this->addon->log(
			sprintf(
				'Updated booking %d: %s %s -> %s %s',
				$booking_id,
				$current_date,
				$current_time,
				$event_date,
				$event_time
			)
		);

		/**
		 * Fires when a booking is updated via Outlook sync.
		 *
		 * @since 1.0.0
		 * @param int   $booking_id Booking ID.
		 * @param array $event      Outlook event data.
		 */
		do_action( 'bkx_outlook_booking_updated', $booking_id, $event );

		return true;
	}

	/**
	 * Sync all pending bookings to Outlook.
	 *
	 * @since 1.0.0
	 * @return array Results.
	 */
	public function sync_to_outlook(): array {
		$results = array(
			'synced' => 0,
			'errors' => 0,
		);

		$args = array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
			'posts_per_page' => 50,
			'meta_query'     => array(
				array(
					'key'     => '_outlook_event_id',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'booking_date',
					'value'   => gmdate( 'Y-m-d' ),
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		);

		$bookings = get_posts( $args );

		foreach ( $bookings as $booking ) {
			$event_id = $this->calendar_service->create_event( $booking->ID, array() );

			if ( ! is_wp_error( $event_id ) ) {
				update_post_meta( $booking->ID, '_outlook_event_id', $event_id );
				++$results['synced'];
			} else {
				++$results['errors'];
			}
		}

		return $results;
	}
}
