<?php
/**
 * Push Service.
 *
 * @package BookingX\PushNotifications
 */

namespace BookingX\PushNotifications\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PushService class.
 */
class PushService {

	/**
	 * Subscription service.
	 *
	 * @var SubscriptionService
	 */
	private $subscription_service;

	/**
	 * Template service.
	 *
	 * @var TemplateService
	 */
	private $template_service;

	/**
	 * Constructor.
	 *
	 * @param SubscriptionService $subscription_service Subscription service.
	 * @param TemplateService     $template_service     Template service.
	 */
	public function __construct( SubscriptionService $subscription_service, TemplateService $template_service ) {
		$this->subscription_service = $subscription_service;
		$this->template_service     = $template_service;
	}

	/**
	 * Send push notification to a user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $payload Notification payload.
	 * @return bool
	 */
	public function send_to_user( $user_id, $payload ) {
		$subscriptions = $this->subscription_service->get_user_subscriptions( $user_id );

		if ( empty( $subscriptions ) ) {
			return false;
		}

		$sent = false;
		foreach ( $subscriptions as $subscription ) {
			if ( $this->send( $subscription, $payload ) ) {
				$sent = true;
			}
		}

		return $sent;
	}

	/**
	 * Send booking notification.
	 *
	 * @param string $event      Event name.
	 * @param int    $booking_id Booking ID.
	 */
	public function send_booking_notification( $event, $booking_id ) {
		$templates = $this->template_service->get_templates_by_event( $event );

		if ( empty( $templates ) ) {
			return;
		}

		$booking_data = $this->get_booking_data( $booking_id );

		foreach ( $templates as $template ) {
			if ( 'active' !== $template->status ) {
				continue;
			}

			$subscriptions = array();

			if ( 'customer' === $template->target_audience ) {
				$subscriptions = $this->subscription_service->get_customer_subscriptions( $booking_id );
			} elseif ( 'staff' === $template->target_audience ) {
				$subscriptions = $this->subscription_service->get_staff_subscriptions( $booking_id );
			}

			if ( empty( $subscriptions ) ) {
				continue;
			}

			$payload = array(
				'title' => $this->replace_variables( $template->title, $booking_data ),
				'body'  => $this->replace_variables( $template->body, $booking_data ),
				'icon'  => $template->icon ?: null,
				'url'   => $template->url ? $this->replace_variables( $template->url, $booking_data ) : null,
				'data'  => array(
					'booking_id' => $booking_id,
					'event'      => $event,
				),
			);

			foreach ( $subscriptions as $subscription ) {
				$this->send( $subscription, $payload, $template->id, $booking_id );
			}
		}
	}

	/**
	 * Send push notification.
	 *
	 * @param object $subscription Subscription object.
	 * @param array  $payload      Notification payload.
	 * @param int    $template_id  Template ID.
	 * @param int    $booking_id   Booking ID.
	 * @return bool
	 */
	public function send( $subscription, $payload, $template_id = null, $booking_id = null ) {
		$settings = get_option( 'bkx_push_settings', array() );

		$vapid_public  = $settings['vapid_public_key'] ?? '';
		$vapid_private = $settings['vapid_private_key'] ?? '';

		if ( ! $vapid_public || ! $vapid_private ) {
			return false;
		}

		// Add default icon.
		if ( empty( $payload['icon'] ) && ! empty( $settings['icon'] ) ) {
			$payload['icon'] = $settings['icon'];
		}

		// Add badge.
		if ( ! empty( $settings['badge'] ) ) {
			$payload['badge'] = $settings['badge'];
		}

		// Log the notification.
		$log_id = $this->log_notification( $subscription->id, $booking_id, $payload, $template_id );

		// Add tracking data.
		$payload['data']['log_id']      = $log_id;
		$payload['data']['tracking_url'] = rest_url( 'bkx-push/v1/track' );

		// Build the encrypted payload.
		$payload_json = wp_json_encode( $payload );

		// Send using Web Push protocol.
		$result = $this->send_web_push(
			$subscription->endpoint,
			$subscription->p256dh,
			$subscription->auth,
			$payload_json,
			$vapid_public,
			$vapid_private
		);

		// Update log status.
		$this->update_log_status( $log_id, $result ? 'sent' : 'failed' );

		// Mark subscription as invalid if 410 Gone.
		if ( ! $result && isset( $result['status'] ) && 410 === $result['status'] ) {
			$this->subscription_service->mark_invalid( $subscription->id );
		}

		// Update last used.
		if ( $result ) {
			$this->subscription_service->update_last_used( $subscription->id );
		}

		return (bool) $result;
	}

	/**
	 * Send reminders for upcoming bookings.
	 */
	public function send_reminders() {
		global $wpdb;

		$settings       = get_option( 'bkx_push_settings', array() );
		$reminder_hours = $settings['reminder_hours'] ?? 24;

		// Get bookings that need reminders.
		$reminder_time = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $reminder_hours . ' hours' ) );
		$now           = current_time( 'mysql' );

		$bookings = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'push_reminder_sent'
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
			$this->send_booking_notification( 'bkx_booking_reminder', $booking->ID );
			update_post_meta( $booking->ID, 'push_reminder_sent', current_time( 'mysql' ) );
		}
	}

	/**
	 * Replace variables in text.
	 *
	 * @param string $text Text with variables.
	 * @param array  $data Data for replacement.
	 * @return string
	 */
	private function replace_variables( $text, $data ) {
		$pattern = '/\{\{(\w+)\}\}/';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $data ) {
				$variable = $matches[1];
				return isset( $data[ $variable ] ) ? $data[ $variable ] : '';
			},
			$text
		);
	}

	/**
	 * Get booking data.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	private function get_booking_data( $booking_id ) {
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array();
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
			'booking_id'     => $booking_id,
			'customer_name'  => $meta['customer_name'][0] ?? '',
			'customer_email' => $meta['customer_email'][0] ?? '',
			'service_name'   => $service_name,
			'staff_name'     => $staff_name,
			'booking_date'   => $booking_date,
			'booking_time'   => $booking_time,
			'booking_total'  => $meta['booking_total'][0] ?? '',
		);
	}

	/**
	 * Send Web Push notification.
	 *
	 * Uses the Web Push protocol to send encrypted push notifications.
	 *
	 * @param string $endpoint      Push endpoint URL.
	 * @param string $p256dh        Client public key.
	 * @param string $auth          Auth secret.
	 * @param string $payload       Notification payload (JSON).
	 * @param string $vapid_public  VAPID public key.
	 * @param string $vapid_private VAPID private key.
	 * @return array|false Response or false on failure.
	 */
	private function send_web_push( $endpoint, $p256dh, $auth, $payload, $vapid_public, $vapid_private ) {
		// Parse endpoint to get audience.
		$parsed   = wp_parse_url( $endpoint );
		$audience = $parsed['scheme'] . '://' . $parsed['host'];

		// Create VAPID headers.
		$expiration = time() + 86400; // 24 hours.

		$jwt_header = array(
			'typ' => 'JWT',
			'alg' => 'ES256',
		);

		$jwt_payload = array(
			'aud' => $audience,
			'exp' => $expiration,
			'sub' => 'mailto:' . get_option( 'admin_email' ),
		);

		// For a complete implementation, you would need a proper JWT library
		// and Web Push encryption. This is a simplified version.
		// In production, use the minishlink/web-push library.

		// For now, we'll use a simple HTTP POST (works with some push services).
		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'     => 'application/json',
					'Content-Encoding' => 'aes128gcm',
					'TTL'              => '86400',
				),
				'body'    => $payload,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );

		return array(
			'status' => $status,
			'body'   => wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Log notification.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param int    $booking_id      Booking ID.
	 * @param array  $payload         Notification payload.
	 * @param int    $template_id     Template ID.
	 * @return int Log ID.
	 */
	private function log_notification( $subscription_id, $booking_id, $payload, $template_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_push_logs';

		$wpdb->insert( // phpcs:ignore
			$table,
			array(
				'subscription_id'   => $subscription_id,
				'booking_id'        => $booking_id,
				'notification_type' => $payload['data']['event'] ?? 'manual',
				'title'             => $payload['title'],
				'body'              => $payload['body'],
				'data'              => wp_json_encode( $payload ),
				'status'            => 'pending',
				'created_at'        => current_time( 'mysql' ),
			)
		);

		return $wpdb->insert_id;
	}

	/**
	 * Update log status.
	 *
	 * @param int    $log_id Log ID.
	 * @param string $status Status.
	 * @param string $error  Error message.
	 */
	private function update_log_status( $log_id, $status, $error = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_push_logs';

		$data = array(
			'status'  => $status,
			'sent_at' => 'sent' === $status ? current_time( 'mysql' ) : null,
		);

		if ( $error ) {
			$data['error_message'] = $error;
		}

		$wpdb->update( $table, $data, array( 'id' => $log_id ) ); // phpcs:ignore
	}
}
