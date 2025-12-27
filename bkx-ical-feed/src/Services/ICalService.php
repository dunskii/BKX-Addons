<?php
/**
 * iCal Service
 *
 * @package BookingX\ICalFeed
 * @since   1.0.0
 */

namespace BookingX\ICalFeed\Services;

use BookingX\ICalFeed\ICalFeedAddon;

/**
 * Class ICalService
 *
 * Generates iCal (ICS) formatted calendar feeds.
 *
 * @since 1.0.0
 */
class ICalService {

	/**
	 * Addon instance.
	 *
	 * @var ICalFeedAddon
	 */
	private ICalFeedAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ICalFeedAddon $addon Addon instance.
	 */
	public function __construct( ICalFeedAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Generate iCal string from bookings.
	 *
	 * @since 1.0.0
	 * @param array  $bookings Booking posts.
	 * @param string $cal_name Calendar name.
	 * @return string iCal formatted string.
	 */
	public function generate_ical( array $bookings, string $cal_name = 'BookingX' ): string {
		$ical  = "BEGIN:VCALENDAR\r\n";
		$ical .= "VERSION:2.0\r\n";
		$ical .= 'PRODID:-//' . get_bloginfo( 'name' ) . "//BookingX//EN\r\n";
		$ical .= "CALSCALE:GREGORIAN\r\n";
		$ical .= "METHOD:PUBLISH\r\n";
		$ical .= 'X-WR-CALNAME:' . $this->escape_ical( $cal_name ) . "\r\n";
		$ical .= 'X-WR-TIMEZONE:' . wp_timezone_string() . "\r\n";

		// Add timezone component.
		$ical .= $this->generate_vtimezone();

		foreach ( $bookings as $booking ) {
			$ical .= $this->booking_to_vevent( $booking );
		}

		$ical .= "END:VCALENDAR\r\n";

		return $ical;
	}

	/**
	 * Generate single booking file.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return string iCal formatted string.
	 */
	public function generate_single( int $booking_id ): string {
		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return '';
		}

		return $this->generate_ical( array( $booking ), __( 'Booking', 'bkx-ical-feed' ) );
	}

	/**
	 * Convert booking to VEVENT.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $booking Booking post.
	 * @return string VEVENT formatted string.
	 */
	private function booking_to_vevent( \WP_Post $booking ): string {
		$booking_id     = $booking->ID;
		$booking_date   = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time   = get_post_meta( $booking_id, 'booking_time', true );
		$service_id     = get_post_meta( $booking_id, 'base_id', true );
		$seat_id        = get_post_meta( $booking_id, 'seat_id', true );
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );

		$service  = get_post( $service_id );
		$seat     = get_post( $seat_id );
		$duration = get_post_meta( $service_id, 'base_time', true ) ?: 60;

		// Parse dates.
		$start = new \DateTime( $booking_date . ' ' . $booking_time, wp_timezone() );
		$end   = clone $start;
		$end->modify( '+' . intval( $duration ) . ' minutes' );

		// Build summary.
		$summary_template = $this->addon->get_setting( 'event_title', '{service} - {customer}' );
		$summary          = $this->replace_placeholders( $summary_template, $booking_id );

		// Build description.
		$desc_parts = array();
		if ( ! empty( $customer_name ) ) {
			/* translators: %s: customer name */
			$desc_parts[] = sprintf( __( 'Customer: %s', 'bkx-ical-feed' ), $customer_name );
		}
		if ( ! empty( $customer_email ) ) {
			/* translators: %s: customer email */
			$desc_parts[] = sprintf( __( 'Email: %s', 'bkx-ical-feed' ), $customer_email );
		}
		if ( ! empty( $customer_phone ) ) {
			/* translators: %s: customer phone */
			$desc_parts[] = sprintf( __( 'Phone: %s', 'bkx-ical-feed' ), $customer_phone );
		}
		if ( $service ) {
			/* translators: %s: service name */
			$desc_parts[] = sprintf( __( 'Service: %s', 'bkx-ical-feed' ), $service->post_title );
		}
		if ( $seat ) {
			$seat_alias = bkx_crud_option_multisite( 'bkx_alias_seat' ) ?: 'Resource';
			/* translators: 1: resource alias, 2: resource name */
			$desc_parts[] = sprintf( __( '%1$s: %2$s', 'bkx-ical-feed' ), $seat_alias, $seat->post_title );
		}

		// Add custom description if set.
		$custom_desc = $this->addon->get_setting( 'event_description', '' );
		if ( ! empty( $custom_desc ) ) {
			$desc_parts[] = '';
			$desc_parts[] = $this->replace_placeholders( $custom_desc, $booking_id );
		}

		$description = implode( '\n', $desc_parts );

		// Build location.
		$location = $this->addon->get_setting( 'event_location', get_bloginfo( 'name' ) );
		$location = $this->replace_placeholders( $location, $booking_id );

		// Generate UID.
		$uid = 'booking-' . $booking_id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );

		// Build VEVENT.
		$vevent  = "BEGIN:VEVENT\r\n";
		$vevent .= 'UID:' . $uid . "\r\n";
		$vevent .= 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . "\r\n";
		$vevent .= 'DTSTART;TZID=' . wp_timezone_string() . ':' . $start->format( 'Ymd\THis' ) . "\r\n";
		$vevent .= 'DTEND;TZID=' . wp_timezone_string() . ':' . $end->format( 'Ymd\THis' ) . "\r\n";
		$vevent .= 'SUMMARY:' . $this->escape_ical( $summary ) . "\r\n";
		$vevent .= 'DESCRIPTION:' . $this->escape_ical( $description ) . "\r\n";
		$vevent .= 'LOCATION:' . $this->escape_ical( $location ) . "\r\n";
		$vevent .= 'STATUS:' . $this->get_event_status( $booking->post_status ) . "\r\n";
		$vevent .= 'SEQUENCE:' . $this->get_sequence( $booking_id ) . "\r\n";

		// Add booking URL.
		$booking_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
		$vevent     .= 'URL:' . $booking_url . "\r\n";

		// Add organizer.
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );
		$vevent     .= 'ORGANIZER;CN=' . $this->escape_ical( $site_name ) . ':mailto:' . $admin_email . "\r\n";

		// Add attendee if customer has email.
		if ( ! empty( $customer_email ) ) {
			$vevent .= 'ATTENDEE;CN=' . $this->escape_ical( $customer_name ) . ';PARTSTAT=ACCEPTED:mailto:' . $customer_email . "\r\n";
		}

		// Add alarm/reminder.
		$reminder = $this->addon->get_setting( 'reminder_minutes', 30 );
		if ( $reminder > 0 ) {
			$vevent .= "BEGIN:VALARM\r\n";
			$vevent .= "ACTION:DISPLAY\r\n";
			$vevent .= 'DESCRIPTION:' . $this->escape_ical( $summary ) . "\r\n";
			$vevent .= 'TRIGGER:-PT' . $reminder . "M\r\n";
			$vevent .= "END:VALARM\r\n";
		}

		$vevent .= "END:VEVENT\r\n";

		return $vevent;
	}

	/**
	 * Generate VTIMEZONE component.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function generate_vtimezone(): string {
		$timezone_string = wp_timezone_string();

		// Skip if using UTC offset.
		if ( preg_match( '/^[+-]/', $timezone_string ) ) {
			return '';
		}

		try {
			$tz = new \DateTimeZone( $timezone_string );
			$transitions = $tz->getTransitions( time() - 31536000, time() + 31536000 );

			if ( false === $transitions || count( $transitions ) < 2 ) {
				return '';
			}

			$vtimezone  = "BEGIN:VTIMEZONE\r\n";
			$vtimezone .= 'TZID:' . $timezone_string . "\r\n";

			foreach ( array_slice( $transitions, 1, 2 ) as $trans ) {
				$type = $trans['isdst'] ? 'DAYLIGHT' : 'STANDARD';
				$offset_from = $this->format_offset( $transitions[0]['offset'] );
				$offset_to   = $this->format_offset( $trans['offset'] );

				$vtimezone .= 'BEGIN:' . $type . "\r\n";
				$vtimezone .= 'DTSTART:' . gmdate( 'Ymd\THis', $trans['ts'] ) . "\r\n";
				$vtimezone .= 'TZOFFSETFROM:' . $offset_from . "\r\n";
				$vtimezone .= 'TZOFFSETTO:' . $offset_to . "\r\n";
				$vtimezone .= 'TZNAME:' . $trans['abbr'] . "\r\n";
				$vtimezone .= 'END:' . $type . "\r\n";
			}

			$vtimezone .= "END:VTIMEZONE\r\n";

			return $vtimezone;
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Format timezone offset.
	 *
	 * @since 1.0.0
	 * @param int $offset Offset in seconds.
	 * @return string
	 */
	private function format_offset( int $offset ): string {
		$sign    = $offset >= 0 ? '+' : '-';
		$offset  = abs( $offset );
		$hours   = floor( $offset / 3600 );
		$minutes = floor( ( $offset % 3600 ) / 60 );

		return sprintf( '%s%02d%02d', $sign, $hours, $minutes );
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
		$service_id     = get_post_meta( $booking_id, 'base_id', true );
		$seat_id        = get_post_meta( $booking_id, 'seat_id', true );
		$service        = get_post( $service_id );
		$seat           = get_post( $seat_id );
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );
		$booking_date   = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time   = get_post_meta( $booking_id, 'booking_time', true );

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
	 * Escape string for iCal format.
	 *
	 * @since 1.0.0
	 * @param string $string String to escape.
	 * @return string
	 */
	private function escape_ical( string $string ): string {
		$string = str_replace( '\\', '\\\\', $string );
		$string = str_replace( ',', '\,', $string );
		$string = str_replace( ';', '\;', $string );
		$string = str_replace( "\n", '\n', $string );
		$string = str_replace( "\r", '', $string );

		// Fold long lines (max 75 chars).
		return $this->fold_line( $string );
	}

	/**
	 * Fold long lines per RFC 5545.
	 *
	 * @since 1.0.0
	 * @param string $line Line to fold.
	 * @return string
	 */
	private function fold_line( string $line ): string {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}

		$folded = '';
		while ( strlen( $line ) > 75 ) {
			$folded .= substr( $line, 0, 75 ) . "\r\n ";
			$line    = substr( $line, 75 );
		}
		$folded .= $line;

		return $folded;
	}

	/**
	 * Get iCal status from booking status.
	 *
	 * @since 1.0.0
	 * @param string $status Booking status.
	 * @return string
	 */
	private function get_event_status( string $status ): string {
		switch ( $status ) {
			case 'bkx-pending':
				return 'TENTATIVE';
			case 'bkx-ack':
			case 'bkx-completed':
				return 'CONFIRMED';
			case 'bkx-cancelled':
				return 'CANCELLED';
			default:
				return 'CONFIRMED';
		}
	}

	/**
	 * Get sequence number for event updates.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return int
	 */
	private function get_sequence( int $booking_id ): int {
		$sequence = get_post_meta( $booking_id, '_ical_sequence', true );
		return intval( $sequence );
	}

	/**
	 * Increment sequence for booking update.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function increment_sequence( int $booking_id ): void {
		$sequence = $this->get_sequence( $booking_id );
		update_post_meta( $booking_id, '_ical_sequence', $sequence + 1 );
	}
}
