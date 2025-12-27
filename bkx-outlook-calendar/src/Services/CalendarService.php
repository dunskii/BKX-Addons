<?php
/**
 * Calendar Service
 *
 * @package BookingX\OutlookCalendar
 * @since   1.0.0
 */

namespace BookingX\OutlookCalendar\Services;

use BookingX\OutlookCalendar\OutlookCalendarAddon;

/**
 * Class CalendarService
 *
 * Handles calendar event operations.
 *
 * @since 1.0.0
 */
class CalendarService {

	/**
	 * API instance.
	 *
	 * @var MicrosoftGraphAPI
	 */
	private MicrosoftGraphAPI $api;

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
	 * @param MicrosoftGraphAPI    $api   API instance.
	 * @param OutlookCalendarAddon $addon Addon instance.
	 */
	public function __construct( MicrosoftGraphAPI $api, OutlookCalendarAddon $addon ) {
		$this->api   = $api;
		$this->addon = $addon;
	}

	/**
	 * Create calendar event from booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return string|\WP_Error Event ID or error.
	 */
	public function create_event( int $booking_id, array $booking_data ) {
		$calendar_id = $this->addon->get_setting( 'selected_calendar', '' );

		if ( empty( $calendar_id ) ) {
			return new \WP_Error( 'no_calendar', __( 'No calendar selected', 'bkx-outlook-calendar' ) );
		}

		$event_data = $this->build_event_data( $booking_id, $booking_data );

		$response = $this->api->create_event( $calendar_id, $event_data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['id'] ?? '';
	}

	/**
	 * Update calendar event.
	 *
	 * @since 1.0.0
	 * @param string $event_id     Event ID.
	 * @param int    $booking_id   Booking ID.
	 * @param array  $booking_data Booking data.
	 * @return bool|\WP_Error
	 */
	public function update_event( string $event_id, int $booking_id, array $booking_data ) {
		$event_data = $this->build_event_data( $booking_id, $booking_data );

		$response = $this->api->update_event( $event_id, $event_data );

		return ! is_wp_error( $response );
	}

	/**
	 * Delete calendar event.
	 *
	 * @since 1.0.0
	 * @param string $event_id Event ID.
	 * @return bool|\WP_Error
	 */
	public function delete_event( string $event_id ) {
		$response = $this->api->delete_event( $event_id );

		return ! is_wp_error( $response );
	}

	/**
	 * Build event data from booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return array
	 */
	private function build_event_data( int $booking_id, array $booking_data ): array {
		$booking       = get_post( $booking_id );
		$service_id    = get_post_meta( $booking_id, 'base_id', true );
		$service       = get_post( $service_id );
		$customer_name = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );
		$booking_date  = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time  = get_post_meta( $booking_id, 'booking_time', true );
		$duration      = get_post_meta( $service_id, 'base_time', true ) ?: 60;

		// Parse start time.
		$start_datetime = new \DateTime( $booking_date . ' ' . $booking_time, wp_timezone() );
		$end_datetime   = clone $start_datetime;
		$end_datetime->modify( '+' . $duration . ' minutes' );

		// Build title.
		$title_template = $this->addon->get_setting( 'event_title', '{service} - {customer}' );
		$title          = $this->replace_placeholders( $title_template, $booking_id );

		// Build description.
		$desc_template = $this->addon->get_setting( 'event_description', '' );
		$description   = $this->replace_placeholders( $desc_template, $booking_id );

		$event_data = array(
			'subject'  => $title,
			'body'     => array(
				'contentType' => 'text',
				'content'     => $description,
			),
			'start'    => array(
				'dateTime' => $start_datetime->format( 'Y-m-d\TH:i:s' ),
				'timeZone' => wp_timezone_string(),
			),
			'end'      => array(
				'dateTime' => $end_datetime->format( 'Y-m-d\TH:i:s' ),
				'timeZone' => wp_timezone_string(),
			),
			'location' => array(
				'displayName' => get_bloginfo( 'name' ),
			),
		);

		// Add attendee if customer has email.
		if ( ! empty( $customer_email ) ) {
			$event_data['attendees'] = array(
				array(
					'emailAddress' => array(
						'address' => $customer_email,
						'name'    => $customer_name,
					),
					'type' => 'required',
				),
			);
		}

		// Add reminder.
		$event_data['reminderMinutesBeforeStart'] = 30;

		return $event_data;
	}

	/**
	 * Replace placeholders in template.
	 *
	 * @since 1.0.0
	 * @param string $template   Template string.
	 * @param int    $booking_id Booking ID.
	 * @return string
	 */
	private function replace_placeholders( string $template, int $booking_id ): string {
		$service_id    = get_post_meta( $booking_id, 'base_id', true );
		$seat_id       = get_post_meta( $booking_id, 'seat_id', true );
		$service       = get_post( $service_id );
		$seat          = get_post( $seat_id );
		$customer_name = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );
		$booking_date  = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time  = get_post_meta( $booking_id, 'booking_time', true );

		$replacements = array(
			'{booking_id}' => $booking_id,
			'{service}'    => $service ? $service->post_title : '',
			'{resource}'   => $seat ? $seat->post_title : '',
			'{customer}'   => $customer_name,
			'{email}'      => $customer_email,
			'{phone}'      => $customer_phone,
			'{date}'       => $booking_date,
			'{time}'       => $booking_time,
			'{site_name}'  => get_bloginfo( 'name' ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Get busy times from Outlook calendar.
	 *
	 * @since 1.0.0
	 * @param string $start_date  Start date (Y-m-d).
	 * @param string $end_date    End date (Y-m-d).
	 * @param int    $resource_id Resource ID (unused for now).
	 * @return array Array of busy times.
	 */
	public function get_busy_times( string $start_date, string $end_date, int $resource_id = 0 ): array {
		$calendar_id = $this->addon->get_setting( 'selected_calendar', '' );

		if ( empty( $calendar_id ) ) {
			return array();
		}

		$start_iso = $start_date . 'T00:00:00';
		$end_iso   = $end_date . 'T23:59:59';

		$events = $this->api->get_events( $calendar_id, $start_iso, $end_iso );

		if ( is_wp_error( $events ) ) {
			return array();
		}

		$busy_times = array();

		foreach ( $events as $event ) {
			// Skip all-day events or free events.
			if ( $event['isAllDay'] ?? false ) {
				continue;
			}

			if ( 'free' === ( $event['showAs'] ?? '' ) ) {
				continue;
			}

			$start = new \DateTime( $event['start']['dateTime'], new \DateTimeZone( $event['start']['timeZone'] ?? 'UTC' ) );
			$end   = new \DateTime( $event['end']['dateTime'], new \DateTimeZone( $event['end']['timeZone'] ?? 'UTC' ) );

			// Convert to site timezone.
			$start->setTimezone( wp_timezone() );
			$end->setTimezone( wp_timezone() );

			$busy_times[] = array(
				'start' => $start->format( 'Y-m-d H:i:s' ),
				'end'   => $end->format( 'Y-m-d H:i:s' ),
			);
		}

		return $busy_times;
	}

	/**
	 * Get list of calendars.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_calendars(): array {
		$calendars = $this->api->get_calendars();

		if ( is_wp_error( $calendars ) ) {
			return array();
		}

		return $calendars;
	}
}
