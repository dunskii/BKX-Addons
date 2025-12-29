<?php
/**
 * Notification Service.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

namespace BookingX\VideoConsultation\Services;

/**
 * NotificationService class.
 *
 * Handles video consultation notifications.
 *
 * @since 1.0.0
 */
class NotificationService {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Send video consultation created notification.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $room_data Room data.
	 */
	public function send_created_notification( $booking_id, $room_data ) {
		$booking_data = $this->get_booking_data( $booking_id );

		// Send to customer.
		$this->send_customer_email( $booking_data, $room_data, 'created' );

		// Send to staff.
		$this->send_staff_email( $booking_data, $room_data, 'created' );
	}

	/**
	 * Send reminder notification.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 */
	public function send_reminder( $booking_id ) {
		global $wpdb;

		// Get room data.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$room = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE booking_id = %d",
				$wpdb->prefix . 'bkx_video_rooms',
				$booking_id
			)
		);

		if ( ! $room || 'scheduled' !== $room->status ) {
			return;
		}

		$booking_data = $this->get_booking_data( $booking_id );
		$room_data    = array(
			'room_id'         => $room->room_id,
			'participant_url' => $room->participant_url,
			'host_url'        => $room->host_url,
			'password'        => $room->password,
		);

		// Send to customer.
		$this->send_customer_email( $booking_data, $room_data, 'reminder' );

		// Send to staff.
		$this->send_staff_email( $booking_data, $room_data, 'reminder' );
	}

	/**
	 * Send session ended notification.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @param int $duration Duration in minutes.
	 */
	public function send_ended_notification( $booking_id, $duration ) {
		$booking_data = $this->get_booking_data( $booking_id );

		$subject = sprintf(
			/* translators: %s: Business name */
			__( 'Video Consultation Completed - %s', 'bkx-video-consultation' ),
			get_bloginfo( 'name' )
		);

		$message = $this->get_ended_template( $booking_data, $duration );

		$this->send_email( $booking_data['customer_email'], $subject, $message );
	}

	/**
	 * Send email to customer.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $booking_data Booking data.
	 * @param array  $room_data Room data.
	 * @param string $type Notification type.
	 */
	private function send_customer_email( $booking_data, $room_data, $type ) {
		$email = $booking_data['customer_email'] ?? '';
		if ( empty( $email ) ) {
			return;
		}

		switch ( $type ) {
			case 'created':
				$subject = sprintf(
					/* translators: %s: Business name */
					__( 'Your Video Consultation Details - %s', 'bkx-video-consultation' ),
					get_bloginfo( 'name' )
				);
				$message = $this->get_customer_created_template( $booking_data, $room_data );
				break;

			case 'reminder':
				$minutes = $this->settings['reminder_before_minutes'] ?? 15;
				$subject = sprintf(
					/* translators: %d: Minutes until consultation */
					__( 'Your Video Consultation Starts in %d Minutes', 'bkx-video-consultation' ),
					$minutes
				);
				$message = $this->get_customer_reminder_template( $booking_data, $room_data );
				break;

			default:
				return;
		}

		$this->send_email( $email, $subject, $message );
	}

	/**
	 * Send email to staff.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $booking_data Booking data.
	 * @param array  $room_data Room data.
	 * @param string $type Notification type.
	 */
	private function send_staff_email( $booking_data, $room_data, $type ) {
		$staff_id    = $booking_data['staff_id'] ?? 0;
		$staff_email = '';

		if ( $staff_id ) {
			$staff_email = get_post_meta( $staff_id, 'seat_email', true );
		}

		if ( empty( $staff_email ) ) {
			$staff_email = get_option( 'admin_email' );
		}

		switch ( $type ) {
			case 'created':
				$subject = sprintf(
					/* translators: %s: Customer name */
					__( 'New Video Consultation Scheduled - %s', 'bkx-video-consultation' ),
					$booking_data['customer_name'] ?? __( 'Customer', 'bkx-video-consultation' )
				);
				$message = $this->get_staff_created_template( $booking_data, $room_data );
				break;

			case 'reminder':
				$minutes = $this->settings['reminder_before_minutes'] ?? 15;
				$subject = sprintf(
					/* translators: %d: Minutes until consultation */
					__( 'Video Consultation in %d Minutes', 'bkx-video-consultation' ),
					$minutes
				);
				$message = $this->get_staff_reminder_template( $booking_data, $room_data );
				break;

			default:
				return;
		}

		$this->send_email( $staff_email, $subject, $message );
	}

	/**
	 * Send email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to Recipient email.
	 * @param string $subject Email subject.
	 * @param string $message Email message.
	 * @return bool
	 */
	private function send_email( $to, $subject, $message ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		/**
		 * Filter email parameters.
		 *
		 * @param array $params Email parameters.
		 */
		$params = apply_filters(
			'bkx_video_email_params',
			array(
				'to'      => $to,
				'subject' => $subject,
				'message' => $message,
				'headers' => $headers,
			)
		);

		return wp_mail( $params['to'], $params['subject'], $params['message'], $params['headers'] );
	}

	/**
	 * Get booking data.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	private function get_booking_data( $booking_id ) {
		return array(
			'booking_id'     => $booking_id,
			'customer_name'  => get_post_meta( $booking_id, 'customer_name', true ),
			'customer_email' => get_post_meta( $booking_id, 'customer_email', true ),
			'booking_date'   => get_post_meta( $booking_id, 'booking_date', true ),
			'booking_time'   => get_post_meta( $booking_id, 'booking_time', true ),
			'service_name'   => get_the_title( get_post_meta( $booking_id, 'base_id', true ) ),
			'staff_id'       => get_post_meta( $booking_id, 'seat_id', true ),
			'staff_name'     => get_the_title( get_post_meta( $booking_id, 'seat_id', true ) ),
		);
	}

	/**
	 * Get customer created email template.
	 *
	 * @since 1.0.0
	 *
	 * @param array $booking_data Booking data.
	 * @param array $room_data Room data.
	 * @return string
	 */
	private function get_customer_created_template( $booking_data, $room_data ) {
		$template = '
		<html>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #2271b1;">Your Video Consultation is Confirmed</h2>

				<p>Hello ' . esc_html( $booking_data['customer_name'] ) . ',</p>

				<p>Your video consultation has been scheduled. Here are the details:</p>

				<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
					<p><strong>Service:</strong> ' . esc_html( $booking_data['service_name'] ) . '</p>
					<p><strong>With:</strong> ' . esc_html( $booking_data['staff_name'] ) . '</p>
					<p><strong>Date:</strong> ' . esc_html( $booking_data['booking_date'] ) . '</p>
					<p><strong>Time:</strong> ' . esc_html( $booking_data['booking_time'] ) . '</p>
				</div>

				<h3>How to Join</h3>
				<p>When it\'s time for your consultation, click the button below:</p>

				<p style="text-align: center; margin: 30px 0;">
					<a href="' . esc_url( $room_data['participant_url'] ) . '"
					   style="background: #2271b1; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
						Join Video Consultation
					</a>
				</p>

				' . ( ! empty( $room_data['password'] ) ? '<p><strong>Room Password:</strong> ' . esc_html( $room_data['password'] ) . '</p>' : '' ) . '

				<h3>Before Your Consultation</h3>
				<ul>
					<li>Ensure you have a stable internet connection</li>
					<li>Test your camera and microphone</li>
					<li>Find a quiet, well-lit space</li>
					<li>Join a few minutes early</li>
				</ul>

				<p>If you need to reschedule or have questions, please contact us.</p>

				<p>Best regards,<br>' . esc_html( get_bloginfo( 'name' ) ) . '</p>
			</div>
		</body>
		</html>';

		return $template;
	}

	/**
	 * Get customer reminder email template.
	 *
	 * @since 1.0.0
	 *
	 * @param array $booking_data Booking data.
	 * @param array $room_data Room data.
	 * @return string
	 */
	private function get_customer_reminder_template( $booking_data, $room_data ) {
		$minutes = $this->settings['reminder_before_minutes'] ?? 15;

		$template = '
		<html>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #2271b1;">Your Consultation Starts Soon!</h2>

				<p>Hello ' . esc_html( $booking_data['customer_name'] ) . ',</p>

				<p>This is a reminder that your video consultation begins in <strong>' . esc_html( $minutes ) . ' minutes</strong>.</p>

				<p style="text-align: center; margin: 30px 0;">
					<a href="' . esc_url( $room_data['participant_url'] ) . '"
					   style="background: #46b450; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-size: 18px;">
						Join Now
					</a>
				</p>

				<p>See you soon!</p>

				<p>Best regards,<br>' . esc_html( get_bloginfo( 'name' ) ) . '</p>
			</div>
		</body>
		</html>';

		return $template;
	}

	/**
	 * Get staff created email template.
	 *
	 * @since 1.0.0
	 *
	 * @param array $booking_data Booking data.
	 * @param array $room_data Room data.
	 * @return string
	 */
	private function get_staff_created_template( $booking_data, $room_data ) {
		$template = '
		<html>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #2271b1;">New Video Consultation Scheduled</h2>

				<p>A new video consultation has been booked:</p>

				<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
					<p><strong>Customer:</strong> ' . esc_html( $booking_data['customer_name'] ) . '</p>
					<p><strong>Email:</strong> ' . esc_html( $booking_data['customer_email'] ) . '</p>
					<p><strong>Service:</strong> ' . esc_html( $booking_data['service_name'] ) . '</p>
					<p><strong>Date:</strong> ' . esc_html( $booking_data['booking_date'] ) . '</p>
					<p><strong>Time:</strong> ' . esc_html( $booking_data['booking_time'] ) . '</p>
				</div>

				<p style="text-align: center; margin: 30px 0;">
					<a href="' . esc_url( $room_data['host_url'] ) . '"
					   style="background: #2271b1; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
						Start Consultation (Host)
					</a>
				</p>

				<p><strong>Room ID:</strong> ' . esc_html( $room_data['room_id'] ) . '</p>
				' . ( ! empty( $room_data['password'] ) ? '<p><strong>Password:</strong> ' . esc_html( $room_data['password'] ) . '</p>' : '' ) . '
			</div>
		</body>
		</html>';

		return $template;
	}

	/**
	 * Get staff reminder email template.
	 *
	 * @since 1.0.0
	 *
	 * @param array $booking_data Booking data.
	 * @param array $room_data Room data.
	 * @return string
	 */
	private function get_staff_reminder_template( $booking_data, $room_data ) {
		$minutes = $this->settings['reminder_before_minutes'] ?? 15;

		$template = '
		<html>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #2271b1;">Video Consultation in ' . esc_html( $minutes ) . ' Minutes</h2>

				<p>Your video consultation with <strong>' . esc_html( $booking_data['customer_name'] ) . '</strong> starts soon.</p>

				<p style="text-align: center; margin: 30px 0;">
					<a href="' . esc_url( $room_data['host_url'] ) . '"
					   style="background: #46b450; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-size: 18px;">
						Start Consultation
					</a>
				</p>
			</div>
		</body>
		</html>';

		return $template;
	}

	/**
	 * Get session ended email template.
	 *
	 * @since 1.0.0
	 *
	 * @param array $booking_data Booking data.
	 * @param int   $duration Duration in minutes.
	 * @return string
	 */
	private function get_ended_template( $booking_data, $duration ) {
		$template = '
		<html>
		<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
			<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
				<h2 style="color: #2271b1;">Thank You for Your Consultation</h2>

				<p>Hello ' . esc_html( $booking_data['customer_name'] ) . ',</p>

				<p>Your video consultation has been completed.</p>

				<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
					<p><strong>Service:</strong> ' . esc_html( $booking_data['service_name'] ) . '</p>
					<p><strong>Duration:</strong> ' . esc_html( $duration ) . ' minutes</p>
				</div>

				<p>If you have any follow-up questions or would like to book another consultation, please don\'t hesitate to reach out.</p>

				<p>Thank you for choosing ' . esc_html( get_bloginfo( 'name' ) ) . '!</p>
			</div>
		</body>
		</html>';

		return $template;
	}
}
