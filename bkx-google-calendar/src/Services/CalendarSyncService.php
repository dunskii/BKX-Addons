<?php
/**
 * Calendar Sync Service
 *
 * Handles synchronization between BookingX and Google Calendar.
 *
 * @package BookingX\GoogleCalendar\Services
 * @since   1.0.0
 */

namespace BookingX\GoogleCalendar\Services;

use BookingX\GoogleCalendar\GoogleCalendarAddon;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Calendar sync service class.
 *
 * @since 1.0.0
 */
class CalendarSyncService {

	/**
	 * Addon instance.
	 *
	 * @var GoogleCalendarAddon
	 */
	protected GoogleCalendarAddon $addon;

	/**
	 * Google API service.
	 *
	 * @var GoogleApiService
	 */
	protected GoogleApiService $google_api;

	/**
	 * Events table.
	 *
	 * @var string
	 */
	protected string $events_table;

	/**
	 * Sync log table.
	 *
	 * @var string
	 */
	protected string $log_table;

	/**
	 * Constructor.
	 *
	 * @param GoogleCalendarAddon $addon Addon instance.
	 * @param GoogleApiService    $google_api Google API service.
	 */
	public function __construct( GoogleCalendarAddon $addon, GoogleApiService $google_api ) {
		global $wpdb;

		$this->addon       = $addon;
		$this->google_api  = $google_api;
		$this->events_table = $wpdb->prefix . 'bkx_google_events';
		$this->log_table    = $wpdb->prefix . 'bkx_google_sync_log';
	}

	/**
	 * Create event from booking.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return bool|WP_Error
	 */
	public function create_event( int $booking_id, array $booking_data ) {
		$staff_id = $booking_data['seat_id'] ?? 0;

		// Get the appropriate connection.
		$connection = $staff_id && $this->addon->get_setting( 'sync_staff_calendars', true )
			? $this->google_api->get_staff_connection( $staff_id )
			: $this->google_api->get_main_connection();

		if ( ! $connection ) {
			return new WP_Error( 'no_connection', __( 'No Google Calendar connection available.', 'bkx-google-calendar' ) );
		}

		$access_token = $this->google_api->get_valid_token( $connection );

		if ( is_wp_error( $access_token ) ) {
			$this->log_error( 'create_event', $access_token->get_error_message() );
			return $access_token;
		}

		// Build event data.
		$event_data = $this->build_event_data( $booking_id, $booking_data );

		// Determine calendar.
		$calendar_id = $connection->calendar_id ?: 'primary';

		// Create in Google Calendar.
		$result = $this->google_api->create_event( $access_token, $calendar_id, $event_data );

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'create_event', $result->get_error_message() );
			return $result;
		}

		// Store mapping.
		$this->store_event_mapping( $booking_id, $result['id'], $calendar_id, $staff_id );

		$this->log( sprintf( 'Created Google event %s for booking #%d', $result['id'], $booking_id ) );

		return true;
	}

	/**
	 * Update event from booking.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return bool|WP_Error
	 */
	public function update_event( int $booking_id, array $booking_data ) {
		$mapping = $this->get_event_mapping( $booking_id );

		if ( ! $mapping ) {
			// No existing event - create new.
			return $this->create_event( $booking_id, $booking_data );
		}

		$connection = $mapping->staff_id
			? $this->google_api->get_staff_connection( $mapping->staff_id )
			: $this->google_api->get_main_connection();

		if ( ! $connection ) {
			return new WP_Error( 'no_connection', __( 'No Google Calendar connection available.', 'bkx-google-calendar' ) );
		}

		$access_token = $this->google_api->get_valid_token( $connection );

		if ( is_wp_error( $access_token ) ) {
			$this->log_error( 'update_event', $access_token->get_error_message() );
			return $access_token;
		}

		// Build event data.
		$event_data = $this->build_event_data( $booking_id, $booking_data );

		// Update in Google Calendar.
		$result = $this->google_api->update_event(
			$access_token,
			$mapping->calendar_id,
			$mapping->google_event_id,
			$event_data
		);

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'update_event', $result->get_error_message() );
			return $result;
		}

		// Update mapping timestamp.
		$this->update_event_mapping_timestamp( $booking_id );

		$this->log( sprintf( 'Updated Google event %s for booking #%d', $mapping->google_event_id, $booking_id ) );

		return true;
	}

	/**
	 * Delete event for booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool|WP_Error
	 */
	public function delete_event( int $booking_id ) {
		$mapping = $this->get_event_mapping( $booking_id );

		if ( ! $mapping ) {
			return true; // No event to delete.
		}

		$connection = $mapping->staff_id
			? $this->google_api->get_staff_connection( $mapping->staff_id )
			: $this->google_api->get_main_connection();

		if ( ! $connection ) {
			return new WP_Error( 'no_connection', __( 'No Google Calendar connection available.', 'bkx-google-calendar' ) );
		}

		$access_token = $this->google_api->get_valid_token( $connection );

		if ( is_wp_error( $access_token ) ) {
			$this->log_error( 'delete_event', $access_token->get_error_message() );
			return $access_token;
		}

		// Delete from Google Calendar.
		$result = $this->google_api->delete_event(
			$access_token,
			$mapping->calendar_id,
			$mapping->google_event_id
		);

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'delete_event', $result->get_error_message() );
			return $result;
		}

		// Remove mapping.
		$this->delete_event_mapping( $booking_id );

		$this->log( sprintf( 'Deleted Google event for booking #%d', $booking_id ) );

		return true;
	}

	/**
	 * Mark event as completed.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public function mark_event_completed( int $booking_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$this->events_table,
			array( 'event_status' => 'completed' ),
			array( 'booking_id' => $booking_id )
		);
	}

	/**
	 * Run full sync.
	 *
	 * @return array|WP_Error Sync stats or error.
	 */
	public function run_full_sync() {
		$start_time = microtime( true );

		$stats = array(
			'synced_to_google'   => 0,
			'synced_from_google' => 0,
			'errors'             => 0,
		);

		$direction = $this->addon->get_setting( 'sync_direction', 'two_way' );

		// Sync to Google.
		if ( 'one_way_from_google' !== $direction ) {
			$result = $this->sync_bookings_to_google();
			if ( ! is_wp_error( $result ) ) {
				$stats['synced_to_google'] = $result;
			} else {
				$stats['errors']++;
			}
		}

		// Sync from Google.
		if ( 'one_way_to_google' !== $direction ) {
			$result = $this->sync_from_google();
			if ( ! is_wp_error( $result ) ) {
				$stats['synced_from_google'] = $result;
			} else {
				$stats['errors']++;
			}
		}

		$duration = microtime( true ) - $start_time;

		// Log sync.
		$this->log_sync( 'full_sync', $direction, $stats, $duration );

		return $stats;
	}

	/**
	 * Sync pending bookings to Google.
	 *
	 * @return int|WP_Error Number synced or error.
	 */
	protected function sync_bookings_to_google() {
		global $wpdb;

		// Get bookings without Google events.
		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
				'posts_per_page' => 50,
				'meta_query'     => array(
					array(
						'key'     => '_bkx_google_event_id',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$synced = 0;

		foreach ( $bookings as $booking ) {
			$booking_data = $this->get_booking_data( $booking->ID );
			$result       = $this->create_event( $booking->ID, $booking_data );

			if ( ! is_wp_error( $result ) ) {
				$synced++;
			}
		}

		return $synced;
	}

	/**
	 * Sync events from Google.
	 *
	 * @return int|WP_Error Number synced or error.
	 */
	protected function sync_from_google() {
		if ( ! $this->addon->get_setting( 'check_conflicts', true ) ) {
			return 0;
		}

		// Get connection.
		$connection = $this->google_api->get_main_connection();

		if ( ! $connection ) {
			return 0;
		}

		$access_token = $this->google_api->get_valid_token( $connection );

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$calendar_id = $connection->calendar_id ?: 'primary';

		// Get events.
		$result = $this->google_api->get_events(
			$access_token,
			$calendar_id,
			$connection->sync_token
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update sync token.
		if ( ! empty( $result['nextSyncToken'] ) ) {
			$this->update_sync_token( $connection->id, $result['nextSyncToken'] );
		}

		// Process events - mark conflicts.
		// For now, just count.
		return count( $result['items'] );
	}

	/**
	 * Build Google Calendar event data from booking.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return array Event data.
	 */
	protected function build_event_data( int $booking_id, array $booking_data ): array {
		$title_format = $this->addon->get_setting( 'event_title_format', '{service_name} - {customer_name}' );
		$description_format = $this->addon->get_setting(
			'event_description',
			"Booking Details:\n\nService: {service_name}\nCustomer: {customer_name}"
		);

		// Parse templates.
		$placeholders = $this->get_placeholders( $booking_id, $booking_data );
		$title        = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $title_format );
		$description  = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $description_format );

		// Build datetime.
		$start = $booking_data['booking_datetime'] ?? '';
		$duration = $booking_data['duration'] ?? 60;
		$end = gmdate( 'Y-m-d\TH:i:s', strtotime( $start ) + ( $duration * 60 ) );

		$event = array(
			'summary'     => $title,
			'description' => $description,
			'start'       => array(
				'dateTime' => gmdate( 'c', strtotime( $start ) ),
				'timeZone' => wp_timezone_string(),
			),
			'end'         => array(
				'dateTime' => gmdate( 'c', strtotime( $end ) ),
				'timeZone' => wp_timezone_string(),
			),
			'status'      => 'confirmed',
		);

		// Add color.
		$color_id = $this->addon->get_setting( 'event_color', '1' );
		if ( $color_id ) {
			$event['colorId'] = $color_id;
		}

		// Add location.
		if ( $this->addon->get_setting( 'include_location', true ) ) {
			$location = $booking_data['location'] ?? get_option( 'blogname' );
			if ( $location ) {
				$event['location'] = $location;
			}
		}

		// Add reminders.
		if ( $this->addon->get_setting( 'add_reminders', true ) ) {
			$reminder_minutes = $this->addon->get_setting( 'reminder_minutes', 30 );
			$event['reminders'] = array(
				'useDefault' => false,
				'overrides'  => array(
					array(
						'method'  => 'popup',
						'minutes' => $reminder_minutes,
					),
					array(
						'method'  => 'email',
						'minutes' => $reminder_minutes,
					),
				),
			);
		}

		// Add extended properties for reference.
		$event['extendedProperties'] = array(
			'private' => array(
				'bookingx_id'   => (string) $booking_id,
				'bookingx_site' => home_url(),
			),
		);

		return $event;
	}

	/**
	 * Get template placeholders.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return array Placeholders.
	 */
	protected function get_placeholders( int $booking_id, array $booking_data ): array {
		return array(
			'{booking_id}'      => $booking_id,
			'{service_name}'    => $booking_data['service_name'] ?? '',
			'{customer_name}'   => $booking_data['customer_name'] ?? '',
			'{customer_email}'  => $booking_data['customer_email'] ?? '',
			'{customer_phone}'  => $booking_data['customer_phone'] ?? '',
			'{staff_name}'      => $booking_data['staff_name'] ?? '',
			'{booking_date}'    => $booking_data['booking_date'] ?? '',
			'{booking_time}'    => $booking_data['booking_time'] ?? '',
			'{booking_notes}'   => $booking_data['notes'] ?? '',
			'{booking_total}'   => $booking_data['total'] ?? '',
		);
	}

	/**
	 * Get booking data.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array Booking data.
	 */
	protected function get_booking_data( int $booking_id ): array {
		$booking = get_post( $booking_id );

		if ( ! $booking ) {
			return array();
		}

		$meta = get_post_meta( $booking_id );

		$service_id = $meta['base_id'][0] ?? 0;
		$seat_id    = $meta['seat_id'][0] ?? 0;

		$service = get_post( $service_id );
		$seat    = get_post( $seat_id );

		return array(
			'booking_id'       => $booking_id,
			'service_id'       => $service_id,
			'seat_id'          => $seat_id,
			'service_name'     => $service ? $service->post_title : '',
			'staff_name'       => $seat ? $seat->post_title : '',
			'customer_name'    => $meta['customer_name'][0] ?? '',
			'customer_email'   => $meta['customer_email'][0] ?? '',
			'customer_phone'   => $meta['customer_phone'][0] ?? '',
			'booking_datetime' => $meta['booking_date'][0] ?? '',
			'booking_date'     => isset( $meta['booking_date'][0] ) ? wp_date( get_option( 'date_format' ), strtotime( $meta['booking_date'][0] ) ) : '',
			'booking_time'     => $meta['booking_time'][0] ?? '',
			'duration'         => $meta['duration'][0] ?? 60,
			'total'            => $meta['booking_total'][0] ?? '',
			'notes'            => $meta['booking_notes'][0] ?? '',
			'location'         => $meta['location'][0] ?? '',
		);
	}

	/**
	 * Store event mapping.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $google_event_id Google event ID.
	 * @param string $calendar_id Calendar ID.
	 * @param int    $staff_id Staff ID.
	 * @return void
	 */
	protected function store_event_mapping( int $booking_id, string $google_event_id, string $calendar_id, int $staff_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->events_table,
			array(
				'booking_id'      => $booking_id,
				'google_event_id' => $google_event_id,
				'calendar_id'     => $calendar_id,
				'staff_id'        => $staff_id ?: null,
				'sync_direction'  => 'to_google',
			)
		);

		// Also store in post meta for quick access.
		update_post_meta( $booking_id, '_bkx_google_event_id', $google_event_id );
		update_post_meta( $booking_id, '_bkx_google_calendar_id', $calendar_id );
	}

	/**
	 * Get event mapping.
	 *
	 * @param int $booking_id Booking ID.
	 * @return object|null
	 */
	protected function get_event_mapping( int $booking_id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE booking_id = %d LIMIT 1',
				$this->events_table,
				$booking_id
			)
		);
	}

	/**
	 * Update event mapping timestamp.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	protected function update_event_mapping_timestamp( int $booking_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->events_table,
			array( 'last_synced_at' => current_time( 'mysql' ) ),
			array( 'booking_id' => $booking_id )
		);
	}

	/**
	 * Delete event mapping.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	protected function delete_event_mapping( int $booking_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->events_table, array( 'booking_id' => $booking_id ) );

		delete_post_meta( $booking_id, '_bkx_google_event_id' );
		delete_post_meta( $booking_id, '_bkx_google_calendar_id' );
	}

	/**
	 * Update sync token.
	 *
	 * @param int    $connection_id Connection ID.
	 * @param string $sync_token Sync token.
	 * @return void
	 */
	protected function update_sync_token( int $connection_id, string $sync_token ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'bkx_google_connections',
			array(
				'sync_token'   => $sync_token,
				'last_sync_at' => current_time( 'mysql' ),
			),
			array( 'id' => $connection_id )
		);
	}

	/**
	 * Filter busy slots from availability.
	 *
	 * @param array $slots Available slots.
	 * @param int   $staff_id Staff ID.
	 * @param array $args Query arguments.
	 * @return array Filtered slots.
	 */
	public function filter_busy_slots( array $slots, int $staff_id, array $args ): array {
		$connection = $this->google_api->get_staff_connection( $staff_id );

		if ( ! $connection ) {
			return $slots;
		}

		$access_token = $this->google_api->get_valid_token( $connection );

		if ( is_wp_error( $access_token ) ) {
			return $slots;
		}

		$calendar_id = $connection->calendar_id ?: 'primary';

		// Get date range from args or slots.
		$start_date = $args['start_date'] ?? gmdate( 'c' );
		$end_date   = $args['end_date'] ?? gmdate( 'c', strtotime( '+7 days' ) );

		$busy_times = $this->google_api->get_freebusy(
			$access_token,
			$calendar_id,
			$start_date,
			$end_date
		);

		if ( is_wp_error( $busy_times ) || empty( $busy_times ) ) {
			return $slots;
		}

		$buffer = $this->addon->get_setting( 'buffer_minutes', 0 ) * 60;

		// Filter out slots that overlap with busy times.
		return array_filter(
			$slots,
			function ( $slot ) use ( $busy_times, $buffer ) {
				$slot_start = strtotime( $slot['start'] ) - $buffer;
				$slot_end   = strtotime( $slot['end'] ) + $buffer;

				foreach ( $busy_times as $busy ) {
					$busy_start = strtotime( $busy['start'] );
					$busy_end   = strtotime( $busy['end'] );

					// Check overlap.
					if ( $slot_start < $busy_end && $slot_end > $busy_start ) {
						return false;
					}
				}

				return true;
			}
		);
	}

	/**
	 * Get Google Calendar add URL.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string URL.
	 */
	public function get_google_calendar_add_url( int $booking_id ): string {
		$booking_data = $this->get_booking_data( $booking_id );

		if ( empty( $booking_data ) ) {
			return '';
		}

		$title = $booking_data['service_name'];
		$start = gmdate( 'Ymd\THis\Z', strtotime( $booking_data['booking_datetime'] ) );
		$end   = gmdate( 'Ymd\THis\Z', strtotime( $booking_data['booking_datetime'] ) + ( $booking_data['duration'] * 60 ) );

		$details = sprintf(
			"Booking with %s\nConfirmation #%d",
			$booking_data['staff_name'],
			$booking_id
		);

		$params = array(
			'action'   => 'TEMPLATE',
			'text'     => $title,
			'dates'    => $start . '/' . $end,
			'details'  => $details,
			'location' => $booking_data['location'] ?: '',
		);

		return 'https://calendar.google.com/calendar/render?' . http_build_query( $params );
	}

	/**
	 * Get iCal download URL.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string URL.
	 */
	public function get_ical_download_url( int $booking_id ): string {
		return rest_url( 'bkx-calendar/v1/ical/' . $booking_id );
	}

	/**
	 * Serve iCal file.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function serve_ical( WP_REST_Request $request ): WP_REST_Response {
		$booking_id   = $request->get_param( 'booking_id' );
		$booking_data = $this->get_booking_data( $booking_id );

		if ( empty( $booking_data ) ) {
			return new WP_REST_Response( 'Booking not found', 404 );
		}

		$ical = $this->generate_ical( $booking_id, $booking_data );

		$response = new WP_REST_Response( $ical );
		$response->header( 'Content-Type', 'text/calendar; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="booking-' . $booking_id . '.ics"' );

		return $response;
	}

	/**
	 * Generate iCal content.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $data Booking data.
	 * @return string iCal content.
	 */
	protected function generate_ical( int $booking_id, array $data ): string {
		$start     = gmdate( 'Ymd\THis\Z', strtotime( $data['booking_datetime'] ) );
		$end       = gmdate( 'Ymd\THis\Z', strtotime( $data['booking_datetime'] ) + ( $data['duration'] * 60 ) );
		$now       = gmdate( 'Ymd\THis\Z' );
		$uid       = 'bkx-' . $booking_id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );

		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//BookingX//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' . $uid,
			'DTSTAMP:' . $now,
			'DTSTART:' . $start,
			'DTEND:' . $end,
			'SUMMARY:' . $this->escape_ical( $data['service_name'] ),
			'DESCRIPTION:' . $this->escape_ical( sprintf( 'Booking with %s', $data['staff_name'] ) ),
		);

		if ( ! empty( $data['location'] ) ) {
			$lines[] = 'LOCATION:' . $this->escape_ical( $data['location'] );
		}

		$lines[] = 'STATUS:CONFIRMED';
		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines );
	}

	/**
	 * Escape string for iCal.
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	protected function escape_ical( string $text ): string {
		return preg_replace( '/([\,;])/', '\\\$1', $text );
	}

	/**
	 * Get busy times API.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_busy_times_api( WP_REST_Request $request ): WP_REST_Response {
		$staff_id   = $request->get_param( 'staff_id' );
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$connection = $this->google_api->get_staff_connection( $staff_id );

		if ( ! $connection ) {
			return new WP_REST_Response( array( 'busy' => array() ), 200 );
		}

		$access_token = $this->google_api->get_valid_token( $connection );

		if ( is_wp_error( $access_token ) ) {
			return new WP_REST_Response(
				array( 'error' => $access_token->get_error_message() ),
				500
			);
		}

		$calendar_id = $connection->calendar_id ?: 'primary';

		$busy_times = $this->google_api->get_freebusy(
			$access_token,
			$calendar_id,
			$start_date,
			$end_date
		);

		if ( is_wp_error( $busy_times ) ) {
			return new WP_REST_Response(
				array( 'error' => $busy_times->get_error_message() ),
				500
			);
		}

		return new WP_REST_Response( array( 'busy' => $busy_times ), 200 );
	}

	/**
	 * Log a message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	protected function log( string $message ): void {
		if ( ! $this->addon->get_setting( 'debug_log', false ) ) {
			return;
		}

		$log_file  = WP_CONTENT_DIR . '/bkx-google-calendar-debug.log';
		$timestamp = current_time( 'c' );
		$formatted = sprintf( "[%s] [INFO] %s\n", $timestamp, $message );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_file, $formatted, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Log an error.
	 *
	 * @param string $context Error context.
	 * @param string $message Error message.
	 * @return void
	 */
	protected function log_error( string $context, string $message ): void {
		$log_file  = WP_CONTENT_DIR . '/bkx-google-calendar-debug.log';
		$timestamp = current_time( 'c' );
		$formatted = sprintf( "[%s] [ERROR] [%s] %s\n", $timestamp, $context, $message );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_file, $formatted, FILE_APPEND | LOCK_EX );

		// Notify admin if enabled.
		if ( $this->addon->get_setting( 'notify_sync_errors', true ) ) {
			$this->notify_admin_error( $context, $message );
		}
	}

	/**
	 * Log sync operation.
	 *
	 * @param string $type Sync type.
	 * @param string $direction Sync direction.
	 * @param array  $stats Sync stats.
	 * @param float  $duration Duration in seconds.
	 * @return void
	 */
	protected function log_sync( string $type, string $direction, array $stats, float $duration ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->log_table,
			array(
				'sync_type'        => $type,
				'direction'        => $direction,
				'items_synced'     => ( $stats['synced_to_google'] ?? 0 ) + ( $stats['synced_from_google'] ?? 0 ),
				'items_failed'     => $stats['errors'] ?? 0,
				'started_at'       => gmdate( 'Y-m-d H:i:s', time() - $duration ),
				'completed_at'     => current_time( 'mysql' ),
				'duration_seconds' => (int) $duration,
			)
		);
	}

	/**
	 * Notify admin of sync error.
	 *
	 * @param string $context Error context.
	 * @param string $message Error message.
	 * @return void
	 */
	protected function notify_admin_error( string $context, string $message ): void {
		$admin_email = $this->addon->get_setting( 'error_email', get_option( 'admin_email' ) );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Google Calendar Sync Error', 'bkx-google-calendar' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: 1: context, 2: error message */
			__( "A Google Calendar sync error occurred:\n\nContext: %1\$s\nError: %2\$s\n\nTime: %3\$s", 'bkx-google-calendar' ),
			$context,
			$message,
			current_time( 'c' )
		);

		wp_mail( $admin_email, $subject, $body );
	}
}
