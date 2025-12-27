<?php
/**
 * Email Service
 *
 * Handles sending email reminders.
 *
 * @package BookingX\BookingReminders\Services
 * @since   1.0.0
 */

namespace BookingX\BookingReminders\Services;

use BookingX\BookingReminders\BookingRemindersAddon;

/**
 * Email service class.
 *
 * @since 1.0.0
 */
class EmailService {

	/**
	 * Addon instance.
	 *
	 * @var BookingRemindersAddon
	 */
	protected BookingRemindersAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param BookingRemindersAddon $addon Addon instance.
	 */
	public function __construct( BookingRemindersAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Send a reminder email.
	 *
	 * @since 1.0.0
	 * @param array  $booking_data Booking data.
	 * @param object $reminder Reminder object.
	 * @return array Result with success status.
	 */
	public function send_reminder( array $booking_data, object $reminder ): array {
		$to = $booking_data['customer_email'] ?? $reminder->recipient;

		if ( empty( $to ) || ! is_email( $to ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid email address.', 'bkx-booking-reminders' ),
			);
		}

		// Get template.
		$template = $this->get_template( $reminder->reminder_type );

		if ( ! $template ) {
			return array(
				'success' => false,
				'error'   => __( 'Email template not found.', 'bkx-booking-reminders' ),
			);
		}

		// Parse template.
		$subject = $this->parse_template( $template->subject, $booking_data );
		$content = $this->parse_template( $template->content, $booking_data );

		// Set headers.
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		// Add reply-to if configured.
		$reply_to = get_option( 'admin_email' );
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		// Optionally attach iCal file.
		$attachments = array();
		if ( $this->addon->get_setting( 'include_ical', true ) && $booking_data['id'] > 0 ) {
			$ical_file = $this->generate_ical( $booking_data );
			if ( $ical_file ) {
				$attachments[] = $ical_file;
			}
		}

		/**
		 * Filter the email arguments before sending.
		 *
		 * @since 1.0.0
		 * @param array  $args         Email arguments.
		 * @param array  $booking_data Booking data.
		 * @param object $reminder     Reminder object.
		 */
		$args = apply_filters(
			'bkx_reminder_email_args',
			array(
				'to'          => $to,
				'subject'     => $subject,
				'message'     => $content,
				'headers'     => $headers,
				'attachments' => $attachments,
			),
			$booking_data,
			$reminder
		);

		// Send email.
		$sent = wp_mail(
			$args['to'],
			$args['subject'],
			$args['message'],
			$args['headers'],
			$args['attachments']
		);

		// Clean up temp files.
		foreach ( $attachments as $attachment ) {
			if ( file_exists( $attachment ) ) {
				wp_delete_file( $attachment );
			}
		}

		if ( $sent ) {
			$this->log( sprintf( 'Email reminder sent to %s for booking #%d', $to, $booking_data['id'] ) );

			return array(
				'success' => true,
				'message' => __( 'Email sent successfully.', 'bkx-booking-reminders' ),
				'subject' => $subject,
			);
		}

		$this->log( sprintf( 'Failed to send email reminder to %s for booking #%d', $to, $booking_data['id'] ), 'error' );

		return array(
			'success' => false,
			'error'   => __( 'Failed to send email.', 'bkx-booking-reminders' ),
		);
	}

	/**
	 * Get email template.
	 *
	 * @since 1.0.0
	 * @param string $type Template type.
	 * @return object|null Template object or null.
	 */
	protected function get_template( string $type ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_reminder_templates';

		// Get active template for type and channel.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$template = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE template_type = %s AND channel = %s AND is_active = 1 ORDER BY is_default DESC LIMIT 1',
				$table,
				$type,
				'email'
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
		// Simple placeholder replacements.
		$replacements = array(
			'{customer_name}'       => $data['customer_name'] ?? '',
			'{customer_first_name}' => $data['customer_first_name'] ?? '',
			'{customer_email}'      => $data['customer_email'] ?? '',
			'{customer_phone}'      => $data['customer_phone'] ?? '',
			'{service_name}'        => $data['service_name'] ?? '',
			'{staff_name}'          => $data['staff_name'] ?? '',
			'{booking_date}'        => $data['booking_date'] ?? '',
			'{booking_time}'        => $data['booking_time'] ?? '',
			'{duration}'            => $data['duration'] ?? '',
			'{location}'            => $data['location'] ?? '',
			'{business_name}'       => $data['business_name'] ?? '',
			'{business_address}'    => $data['business_address'] ?? '',
			'{business_phone}'      => $data['business_phone'] ?? '',
			'{booking_id}'          => $data['id'] ?? '',
		);

		$content = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		// Handle conditional blocks.
		$content = $this->parse_conditionals( $content, $data );

		return $content;
	}

	/**
	 * Parse conditional blocks in template.
	 *
	 * @since 1.0.0
	 * @param string $content Template content.
	 * @param array  $data Booking data.
	 * @return string Parsed content.
	 */
	protected function parse_conditionals( string $content, array $data ): string {
		// {if_staff}...{/if_staff}
		if ( ! empty( $data['staff_name'] ) ) {
			$content = preg_replace( '/\{if_staff\}(.*?)\{\/if_staff\}/s', '$1', $content );
		} else {
			$content = preg_replace( '/\{if_staff\}.*?\{\/if_staff\}/s', '', $content );
		}

		// {if_location}...{/if_location}
		if ( ! empty( $data['location'] ) ) {
			$content = preg_replace( '/\{if_location\}(.*?)\{\/if_location\}/s', '$1', $content );
		} else {
			$content = preg_replace( '/\{if_location\}.*?\{\/if_location\}/s', '', $content );
		}

		// {if_review}...{/if_review}
		if ( $this->addon->get_setting( 'followup_request_review', true ) ) {
			$review_link = apply_filters( 'bkx_reminder_review_link', home_url( '/reviews/' ), $data );
			$content     = str_replace( '{review_link}', $review_link, $content );
			$content     = preg_replace( '/\{if_review\}(.*?)\{\/if_review\}/s', '$1', $content );
		} else {
			$content = preg_replace( '/\{if_review\}.*?\{\/if_review\}/s', '', $content );
		}

		return $content;
	}

	/**
	 * Generate iCal file for booking.
	 *
	 * @since 1.0.0
	 * @param array $data Booking data.
	 * @return string|null File path or null on failure.
	 */
	protected function generate_ical( array $data ): ?string {
		if ( empty( $data['booking_datetime'] ) ) {
			return null;
		}

		$start = strtotime( $data['booking_datetime'] );
		$end   = $start + ( intval( $data['duration'] ?? 60 ) * 60 );

		$uid         = 'booking-' . $data['id'] . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
		$summary     = sprintf( '%s - %s', $data['service_name'], $data['business_name'] );
		$description = sprintf(
			"%s\n%s: %s\n%s: %s",
			$data['service_name'],
			__( 'With', 'bkx-booking-reminders' ),
			$data['staff_name'] ?? __( 'Staff', 'bkx-booking-reminders' ),
			__( 'Duration', 'bkx-booking-reminders' ),
			$data['duration'] ?? ''
		);

		$ical = "BEGIN:VCALENDAR\r\n";
		$ical .= "VERSION:2.0\r\n";
		$ical .= "PRODID:-//BookingX//Booking Reminders//EN\r\n";
		$ical .= "CALSCALE:GREGORIAN\r\n";
		$ical .= "METHOD:REQUEST\r\n";
		$ical .= "BEGIN:VEVENT\r\n";
		$ical .= 'UID:' . $uid . "\r\n";
		$ical .= 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . "\r\n";
		$ical .= 'DTSTART:' . gmdate( 'Ymd\THis\Z', $start ) . "\r\n";
		$ical .= 'DTEND:' . gmdate( 'Ymd\THis\Z', $end ) . "\r\n";
		$ical .= 'SUMMARY:' . $this->escape_ical( $summary ) . "\r\n";
		$ical .= 'DESCRIPTION:' . $this->escape_ical( $description ) . "\r\n";

		if ( ! empty( $data['location'] ) ) {
			$ical .= 'LOCATION:' . $this->escape_ical( $data['location'] ) . "\r\n";
		}

		$ical .= "STATUS:CONFIRMED\r\n";
		$ical .= "SEQUENCE:0\r\n";
		$ical .= "BEGIN:VALARM\r\n";
		$ical .= "ACTION:DISPLAY\r\n";
		$ical .= "DESCRIPTION:Reminder\r\n";
		$ical .= "TRIGGER:-PT1H\r\n";
		$ical .= "END:VALARM\r\n";
		$ical .= "END:VEVENT\r\n";
		$ical .= "END:VCALENDAR\r\n";

		// Save to temp file.
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/bkx-reminders/booking-' . $data['id'] . '.ics';

		// Create directory if needed.
		wp_mkdir_p( dirname( $file_path ) );

		// Write file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $file_path, $ical ) ) {
			return $file_path;
		}

		return null;
	}

	/**
	 * Escape text for iCal format.
	 *
	 * @since 1.0.0
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	protected function escape_ical( string $text ): string {
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( ',', '\,', $text );
		$text = str_replace( ';', '\;', $text );
		$text = str_replace( "\n", '\n', $text );
		return $text;
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
		$formatted = sprintf( "[%s] [%s] [EMAIL] %s\n", $timestamp, strtoupper( $level ), $message );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_file, $formatted, FILE_APPEND | LOCK_EX );
	}
}
