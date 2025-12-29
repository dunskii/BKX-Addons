<?php
/**
 * Message Service.
 *
 * @package BookingX\WhatsAppBusiness\Services
 * @since   1.0.0
 */

namespace BookingX\WhatsAppBusiness\Services;

defined( 'ABSPATH' ) || exit;

/**
 * MessageService class.
 *
 * Handles message sending and tracking.
 *
 * @since 1.0.0
 */
class MessageService {

	/**
	 * API Provider.
	 *
	 * @var object
	 */
	private $provider;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param object $provider API provider instance.
	 * @param array  $settings Plugin settings.
	 */
	public function __construct( $provider, array $settings ) {
		$this->provider = $provider;
		$this->settings = $settings;
	}

	/**
	 * Send a text message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to         Recipient phone number.
	 * @param string $message    Message text.
	 * @param int    $booking_id Optional booking ID.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_text_message( $to, $message, $booking_id = 0 ) {
		$result = $this->provider->send_text_message( $to, $message );

		if ( is_wp_error( $result ) ) {
			$this->log_message( $to, 'outbound', 'text', $message, 'failed', $booking_id, $result->get_error_message() );
			return $result;
		}

		$message_id = $result['messages'][0]['id'] ?? ( $result['sid'] ?? wp_generate_uuid4() );

		$this->log_message( $to, 'outbound', 'text', $message, 'sent', $booking_id, '', $message_id );

		return array(
			'success'    => true,
			'message_id' => $message_id,
		);
	}

	/**
	 * Send a template message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to         Recipient phone number.
	 * @param string $template   Template name.
	 * @param array  $variables  Template variables.
	 * @param int    $booking_id Optional booking ID.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_template_message( $to, $template, $variables = array(), $booking_id = 0 ) {
		$components = array();

		if ( ! empty( $variables ) ) {
			$parameters = array();
			foreach ( $variables as $value ) {
				$parameters[] = array(
					'type' => 'text',
					'text' => $value,
				);
			}
			$components[] = array(
				'type'       => 'body',
				'parameters' => $parameters,
			);
		}

		$result = $this->provider->send_template_message( $to, $template, 'en', $components );

		if ( is_wp_error( $result ) ) {
			$this->log_message( $to, 'outbound', 'template', $template, 'failed', $booking_id, $result->get_error_message() );
			return $result;
		}

		$message_id = $result['messages'][0]['id'] ?? ( $result['sid'] ?? wp_generate_uuid4() );

		$this->log_message( $to, 'outbound', 'template', $template, 'sent', $booking_id, '', $message_id );

		return array(
			'success'    => true,
			'message_id' => $message_id,
		);
	}

	/**
	 * Send booking confirmation.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $phone      Phone number.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_booking_confirmation( $booking_id, $phone ) {
		$booking = get_post( $booking_id );

		if ( ! $booking ) {
			return new \WP_Error( 'invalid_booking', __( 'Booking not found.', 'bkx-whatsapp-business' ) );
		}

		$template = $this->settings['confirmation_template'] ?? '';

		if ( ! empty( $template ) ) {
			$variables = $this->get_booking_variables( $booking_id );
			return $this->send_template_message( $phone, $template, $variables, $booking_id );
		}

		// Fallback to text message.
		$message = $this->get_confirmation_message( $booking_id );
		return $this->send_text_message( $phone, $message, $booking_id );
	}

	/**
	 * Send booking reminder.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $phone      Phone number.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_booking_reminder( $booking_id, $phone ) {
		$template = $this->settings['reminder_template'] ?? '';

		if ( ! empty( $template ) ) {
			$variables = $this->get_booking_variables( $booking_id );
			return $this->send_template_message( $phone, $template, $variables, $booking_id );
		}

		// Fallback to text message.
		$message = $this->get_reminder_message( $booking_id );
		return $this->send_text_message( $phone, $message, $booking_id );
	}

	/**
	 * Send booking cancelled notification.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $phone      Phone number.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_booking_cancelled( $booking_id, $phone ) {
		$template = $this->settings['cancelled_template'] ?? '';

		if ( ! empty( $template ) ) {
			$variables = $this->get_booking_variables( $booking_id );
			return $this->send_template_message( $phone, $template, $variables, $booking_id );
		}

		// Fallback to text message.
		$message = $this->get_cancelled_message( $booking_id );
		return $this->send_text_message( $phone, $message, $booking_id );
	}

	/**
	 * Send booking rescheduled notification.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $phone      Phone number.
	 * @param string $new_date   New date.
	 * @param string $old_date   Old date.
	 * @return array|\WP_Error Response or error.
	 */
	public function send_booking_rescheduled( $booking_id, $phone, $new_date, $old_date ) {
		$template = $this->settings['rescheduled_template'] ?? '';

		if ( ! empty( $template ) ) {
			$variables = $this->get_booking_variables( $booking_id );
			$variables['new_date'] = $new_date;
			$variables['old_date'] = $old_date;
			return $this->send_template_message( $phone, $template, array_values( $variables ), $booking_id );
		}

		// Fallback to text message.
		$message = $this->get_rescheduled_message( $booking_id, $new_date, $old_date );
		return $this->send_text_message( $phone, $message, $booking_id );
	}

	/**
	 * Send pending reminders.
	 *
	 * @since 1.0.0
	 */
	public function send_pending_reminders() {
		global $wpdb;

		$reminder_hours = absint( $this->settings['reminder_hours'] ?? 24 );
		$reminder_time  = gmdate( 'Y-m-d H:i:s', strtotime( "+{$reminder_hours} hours" ) );
		$now            = current_time( 'mysql' );

		// Get bookings that need reminders.
		$bookings = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT p.ID, pm.meta_value as booking_date
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_bkx_whatsapp_reminder_sent'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-pending', 'bkx-ack')
				AND pm.meta_value BETWEEN %s AND %s
				AND pm2.meta_id IS NULL
				LIMIT 50",
				$now,
				$reminder_time
			)
		);

		foreach ( $bookings as $booking ) {
			$phone = get_post_meta( $booking->ID, 'customer_phone', true );

			if ( empty( $phone ) ) {
				continue;
			}

			$result = $this->send_booking_reminder( $booking->ID, $phone );

			if ( ! is_wp_error( $result ) ) {
				update_post_meta( $booking->ID, '_bkx_whatsapp_reminder_sent', current_time( 'mysql' ) );
			}
		}
	}

	/**
	 * Get messages for a phone number.
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone Phone number.
	 * @param int    $page  Page number.
	 * @return array Messages.
	 */
	public function get_messages_for_phone( $phone, $page = 1 ) {
		global $wpdb;

		$per_page = 50;
		$offset   = ( $page - 1 ) * $per_page;
		$table    = $wpdb->prefix . 'bkx_whatsapp_messages';

		$messages = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE phone_number = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$table,
				$phone,
				$per_page,
				$offset
			)
		);

		return array_reverse( $messages );
	}

	/**
	 * Get messages for a booking.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @param int $limit      Limit.
	 * @return array Messages.
	 */
	public function get_messages_for_booking( $booking_id, $limit = 20 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_messages';

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE booking_id = %d ORDER BY created_at DESC LIMIT %d",
				$table,
				$booking_id,
				$limit
			)
		);
	}

	/**
	 * Log an incoming message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone      Phone number.
	 * @param string $content    Message content.
	 * @param string $type       Message type.
	 * @param string $message_id Message ID.
	 * @return int Inserted message ID.
	 */
	public function log_incoming_message( $phone, $content, $type, $message_id ) {
		return $this->log_message( $phone, 'inbound', $type, $content, 'received', 0, '', $message_id );
	}

	/**
	 * Update message status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message_id Message ID.
	 * @param string $status     New status.
	 * @param string $timestamp  Timestamp field to update.
	 */
	public function update_message_status( $message_id, $status, $timestamp = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_messages';
		$data  = array( 'status' => $status );

		if ( ! empty( $timestamp ) ) {
			$data[ $timestamp ] = current_time( 'mysql' );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			$data,
			array( 'message_id' => $message_id )
		);
	}

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone         Phone number.
	 * @param string $direction     Direction (inbound/outbound).
	 * @param string $type          Message type.
	 * @param string $content       Message content.
	 * @param string $status        Message status.
	 * @param int    $booking_id    Optional booking ID.
	 * @param string $error_message Optional error message.
	 * @param string $message_id    Optional message ID.
	 * @return int Inserted ID.
	 */
	private function log_message( $phone, $direction, $type, $content, $status, $booking_id = 0, $error_message = '', $message_id = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_messages';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'message_id'    => $message_id ?: wp_generate_uuid4(),
				'booking_id'    => $booking_id ?: null,
				'phone_number'  => $phone,
				'direction'     => $direction,
				'message_type'  => $type,
				'content'       => $content,
				'status'        => $status,
				'error_message' => $error_message ?: null,
				'sent_at'       => 'outbound' === $direction ? current_time( 'mysql' ) : null,
			)
		);

		return $wpdb->insert_id;
	}

	/**
	 * Get booking variables.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @return array Variables.
	 */
	private function get_booking_variables( $booking_id ) {
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$booking_date   = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time   = get_post_meta( $booking_id, 'booking_time', true );
		$service_id     = get_post_meta( $booking_id, 'base_id', true );
		$service_name   = $service_id ? get_the_title( $service_id ) : '';
		$business_name  = get_bloginfo( 'name' );

		return array(
			'customer_name' => $customer_name,
			'service_name'  => $service_name,
			'booking_date'  => $booking_date ? wp_date( get_option( 'date_format' ), strtotime( $booking_date ) ) : '',
			'booking_time'  => $booking_time ? wp_date( get_option( 'time_format' ), strtotime( $booking_time ) ) : '',
			'business_name' => $business_name,
		);
	}

	/**
	 * Get confirmation message.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @return string Message.
	 */
	private function get_confirmation_message( $booking_id ) {
		$vars = $this->get_booking_variables( $booking_id );

		return sprintf(
			/* translators: %1$s: customer name, %2$s: service name, %3$s: date, %4$s: time, %5$s: business name */
			__( "Hi %1\$s,\n\nYour booking for %2\$s has been confirmed.\n\nDate: %3\$s\nTime: %4\$s\n\nThank you for choosing %5\$s!", 'bkx-whatsapp-business' ),
			$vars['customer_name'],
			$vars['service_name'],
			$vars['booking_date'],
			$vars['booking_time'],
			$vars['business_name']
		);
	}

	/**
	 * Get reminder message.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @return string Message.
	 */
	private function get_reminder_message( $booking_id ) {
		$vars = $this->get_booking_variables( $booking_id );

		return sprintf(
			/* translators: %1$s: customer name, %2$s: service name, %3$s: date, %4$s: time */
			__( "Hi %1\$s,\n\nThis is a reminder about your upcoming appointment for %2\$s.\n\nDate: %3\$s\nTime: %4\$s\n\nWe look forward to seeing you!", 'bkx-whatsapp-business' ),
			$vars['customer_name'],
			$vars['service_name'],
			$vars['booking_date'],
			$vars['booking_time']
		);
	}

	/**
	 * Get cancelled message.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @return string Message.
	 */
	private function get_cancelled_message( $booking_id ) {
		$vars = $this->get_booking_variables( $booking_id );

		return sprintf(
			/* translators: %1$s: customer name, %2$s: service name, %3$s: date */
			__( "Hi %1\$s,\n\nYour booking for %2\$s on %3\$s has been cancelled.\n\nIf you have any questions, please contact us.", 'bkx-whatsapp-business' ),
			$vars['customer_name'],
			$vars['service_name'],
			$vars['booking_date']
		);
	}

	/**
	 * Get rescheduled message.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_date   New date.
	 * @param string $old_date   Old date.
	 * @return string Message.
	 */
	private function get_rescheduled_message( $booking_id, $new_date, $old_date ) {
		$vars = $this->get_booking_variables( $booking_id );

		return sprintf(
			/* translators: %1$s: customer name, %2$s: service name, %3$s: old date, %4$s: new date, %5$s: time */
			__( "Hi %1\$s,\n\nYour booking for %2\$s has been rescheduled.\n\nPrevious: %3\$s\nNew: %4\$s at %5\$s\n\nPlease contact us if this doesn't work for you.", 'bkx-whatsapp-business' ),
			$vars['customer_name'],
			$vars['service_name'],
			$old_date,
			$new_date,
			$vars['booking_time']
		);
	}
}
