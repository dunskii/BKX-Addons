<?php
/**
 * Email Service.
 *
 * @package BookingX\AdvancedEmailTemplates
 */

namespace BookingX\AdvancedEmailTemplates\Services;

defined( 'ABSPATH' ) || exit;

/**
 * EmailService class.
 */
class EmailService {

	/**
	 * Variable service.
	 *
	 * @var VariableService
	 */
	private $variable_service;

	/**
	 * Constructor.
	 *
	 * @param VariableService $variable_service Variable service.
	 */
	public function __construct( VariableService $variable_service ) {
		$this->variable_service = $variable_service;
	}

	/**
	 * Send booking email.
	 *
	 * @param string $event      Event name.
	 * @param int    $booking_id Booking ID.
	 * @return bool
	 */
	public function send_booking_email( $event, $booking_id ) {
		$template_service = new TemplateService();
		$template         = $template_service->get_template_by_event( $event );

		if ( ! $template || 'active' !== $template->status ) {
			return false;
		}

		$booking_data = $this->get_booking_data( $booking_id );
		if ( ! $booking_data ) {
			return false;
		}

		$recipient = $booking_data['customer_email'];
		if ( ! is_email( $recipient ) ) {
			return false;
		}

		$subject = $this->variable_service->replace_variables( $template->subject, $booking_data );
		$content = $this->render_template( $template, $booking_data );

		return $this->send_email( $recipient, $subject, $content, $template->id, $booking_id );
	}

	/**
	 * Send admin notification.
	 *
	 * @param string $event      Event name.
	 * @param int    $booking_id Booking ID.
	 * @return bool
	 */
	public function send_admin_notification( $event, $booking_id ) {
		$template_service = new TemplateService();
		$template         = $template_service->get_template_by_event( $event );

		if ( ! $template || 'active' !== $template->status ) {
			return false;
		}

		$booking_data = $this->get_booking_data( $booking_id );
		if ( ! $booking_data ) {
			return false;
		}

		$recipient = get_option( 'admin_email' );
		$subject   = $this->variable_service->replace_variables( $template->subject, $booking_data );
		$content   = $this->render_template( $template, $booking_data );

		return $this->send_email( $recipient, $subject, $content, $template->id, $booking_id );
	}

	/**
	 * Send test email.
	 *
	 * @param string $email   Recipient email.
	 * @param string $subject Subject.
	 * @param string $content Content.
	 * @return bool
	 */
	public function send_test_email( $email, $subject, $content ) {
		$sample_data = $this->variable_service->get_sample_data();

		$subject = $this->variable_service->replace_variables( $subject, $sample_data );
		$content = $this->variable_service->replace_variables( $content, $sample_data );
		$content = $this->wrap_in_layout( $content );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		return wp_mail( $email, $subject, $content, $headers );
	}

	/**
	 * Send email.
	 *
	 * @param string $recipient  Recipient email.
	 * @param string $subject    Subject.
	 * @param string $content    Content.
	 * @param int    $template_id Template ID.
	 * @param int    $booking_id Booking ID.
	 * @return bool
	 */
	public function send_email( $recipient, $subject, $content, $template_id = null, $booking_id = null ) {
		// Log the email.
		$log_id = $this->log_email( $template_id, $booking_id, $recipient, $subject, $content );

		// Add tracking pixel.
		if ( $log_id ) {
			$tracking_url = add_query_arg( 'bkx_email_track', $log_id, home_url() );
			$content     .= '<img src="' . esc_url( $tracking_url ) . '" width="1" height="1" alt="" style="display:none;">';
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		$result = wp_mail( $recipient, $subject, $content, $headers );

		// Update log status.
		if ( $log_id && ! $result ) {
			$this->update_log_status( $log_id, 'failed', 'Email sending failed' );
		}

		return $result;
	}

	/**
	 * Render template.
	 *
	 * @param object $template Template object.
	 * @param array  $data     Data for variables.
	 * @return string
	 */
	public function render_template( $template, $data ) {
		$content = $this->variable_service->replace_variables( $template->content, $data );

		// Process conditionals.
		$content = $this->process_conditionals( $content, $data );

		// Wrap in layout.
		$content = $this->wrap_in_layout( $content, $template->preheader ?? '' );

		return $content;
	}

	/**
	 * Process conditionals.
	 *
	 * @param string $content Content.
	 * @param array  $data    Data.
	 * @return string
	 */
	private function process_conditionals( $content, $data ) {
		// Simple {{#if variable}}content{{/if}} syntax.
		$pattern = '/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $data ) {
				$variable = $matches[1];
				$block    = $matches[2];

				if ( ! empty( $data[ $variable ] ) ) {
					return $block;
				}

				return '';
			},
			$content
		);
	}

	/**
	 * Wrap content in email layout.
	 *
	 * @param string $content   Content.
	 * @param string $preheader Preheader text.
	 * @return string
	 */
	private function wrap_in_layout( $content, $preheader = '' ) {
		$settings    = get_option( 'bkx_email_templates_settings', array() );
		$logo        = $settings['logo'] ?? '';
		$footer_text = $settings['footer_text'] ?? get_bloginfo( 'name' );
		$bg_color    = $settings['bg_color'] ?? '#f7f7f7';
		$text_color  = $settings['text_color'] ?? '#333333';
		$link_color  = $settings['link_color'] ?? '#2271b1';

		ob_start();
		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<meta http-equiv="X-UA-Compatible" content="IE=edge">
			<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
			<style>
				body {
					margin: 0;
					padding: 0;
					font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
					font-size: 16px;
					line-height: 1.6;
					color: <?php echo esc_attr( $text_color ); ?>;
					background-color: <?php echo esc_attr( $bg_color ); ?>;
				}
				a {
					color: <?php echo esc_attr( $link_color ); ?>;
				}
				.email-wrapper {
					max-width: 600px;
					margin: 0 auto;
					padding: 20px;
				}
				.email-header {
					text-align: center;
					padding: 20px 0;
				}
				.email-header img {
					max-width: 200px;
					height: auto;
				}
				.email-content {
					background: #ffffff;
					padding: 30px;
					border-radius: 8px;
					box-shadow: 0 2px 4px rgba(0,0,0,0.1);
				}
				.email-footer {
					text-align: center;
					padding: 20px;
					font-size: 12px;
					color: #999999;
				}
				.preheader {
					display: none !important;
					visibility: hidden;
					opacity: 0;
					color: transparent;
					height: 0;
					width: 0;
				}
				@media screen and (max-width: 600px) {
					.email-wrapper {
						padding: 10px;
					}
					.email-content {
						padding: 20px;
					}
				}
			</style>
		</head>
		<body>
			<?php if ( $preheader ) : ?>
				<div class="preheader"><?php echo esc_html( $preheader ); ?></div>
			<?php endif; ?>

			<div class="email-wrapper">
				<div class="email-header">
					<?php if ( $logo ) : ?>
						<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					<?php else : ?>
						<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
					<?php endif; ?>
				</div>

				<div class="email-content">
					<?php echo wp_kses_post( $content ); ?>
				</div>

				<div class="email-footer">
					<p><?php echo esc_html( $footer_text ); ?></p>
					<p>&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get booking data.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|false
	 */
	private function get_booking_data( $booking_id ) {
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return false;
		}

		$meta = get_post_meta( $booking_id );

		// Get service.
		$service_id   = $meta['base_id'][0] ?? 0;
		$service      = get_post( $service_id );
		$service_name = $service ? $service->post_title : '';

		// Get staff.
		$staff_id   = $meta['seat_id'][0] ?? 0;
		$staff      = get_post( $staff_id );
		$staff_name = $staff ? $staff->post_title : '';

		// Format date and time.
		$booking_date = $meta['booking_date'][0] ?? '';
		$booking_time = $meta['booking_time'][0] ?? '';

		if ( $booking_date ) {
			$booking_date = wp_date( get_option( 'date_format' ), strtotime( $booking_date ) );
		}

		return array(
			'booking_id'         => $booking_id,
			'customer_name'      => $meta['customer_name'][0] ?? '',
			'customer_email'     => $meta['customer_email'][0] ?? '',
			'customer_phone'     => $meta['customer_phone'][0] ?? '',
			'service_name'       => $service_name,
			'service_id'         => $service_id,
			'staff_name'         => $staff_name,
			'staff_id'           => $staff_id,
			'booking_date'       => $booking_date,
			'booking_time'       => $booking_time,
			'booking_total'      => $meta['booking_total'][0] ?? '',
			'booking_status'     => $booking->post_status,
			'location_address'   => $meta['location_address'][0] ?? '',
			'admin_booking_url'  => admin_url( 'post.php?post=' . $booking_id . '&action=edit' ),
			'cancellation_reason' => $meta['cancellation_reason'][0] ?? '',
		);
	}

	/**
	 * Log email.
	 *
	 * @param int    $template_id Template ID.
	 * @param int    $booking_id  Booking ID.
	 * @param string $recipient   Recipient.
	 * @param string $subject     Subject.
	 * @param string $body        Body.
	 * @return int|false Log ID or false.
	 */
	private function log_email( $template_id, $booking_id, $recipient, $subject, $body ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_email_logs';

		$result = $wpdb->insert( // phpcs:ignore
			$table,
			array(
				'template_id' => $template_id,
				'booking_id'  => $booking_id,
				'recipient'   => $recipient,
				'subject'     => $subject,
				'body'        => $body,
				'status'      => 'sent',
				'sent_at'     => current_time( 'mysql' ),
			)
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update log status.
	 *
	 * @param int    $log_id  Log ID.
	 * @param string $status  Status.
	 * @param string $message Error message.
	 */
	private function update_log_status( $log_id, $status, $message = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_email_logs';

		$wpdb->update( // phpcs:ignore
			$table,
			array(
				'status'        => $status,
				'error_message' => $message,
			),
			array( 'id' => $log_id )
		);
	}

	/**
	 * Send upcoming reminders.
	 */
	public function send_upcoming_reminders() {
		global $wpdb;

		$settings        = get_option( 'bkx_email_templates_settings', array() );
		$reminder_hours  = $settings['reminder_hours'] ?? 24;

		// Get bookings that need reminders.
		$reminder_time = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $reminder_hours . ' hours' ) );
		$now           = current_time( 'mysql' );

		$bookings = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'reminder_sent'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-pending')
				AND pm.meta_value BETWEEN %s AND %s
				AND pm2.meta_value IS NULL
				LIMIT 50",
				$now,
				$reminder_time
			)
		);

		foreach ( $bookings as $booking ) {
			$this->send_booking_email( 'bkx_booking_reminder', $booking->ID );
			update_post_meta( $booking->ID, 'reminder_sent', current_time( 'mysql' ) );
		}
	}
}
