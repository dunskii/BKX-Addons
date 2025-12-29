<?php
/**
 * Notification Service.
 *
 * @package BookingX\Discord
 */

namespace BookingX\Discord\Services;

defined( 'ABSPATH' ) || exit;

/**
 * NotificationService class.
 */
class NotificationService {

	/**
	 * Discord API.
	 *
	 * @var DiscordApi
	 */
	private $api;

	/**
	 * Webhook manager.
	 *
	 * @var WebhookManager
	 */
	private $webhooks;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param DiscordApi     $api      Discord API.
	 * @param WebhookManager $webhooks Webhook manager.
	 */
	public function __construct( DiscordApi $api, WebhookManager $webhooks ) {
		$this->api      = $api;
		$this->webhooks = $webhooks;
		$this->settings = get_option( 'bkx_discord_settings', array() );
	}

	/**
	 * Handle new booking.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		if ( empty( $this->settings['notify_new'] ) ) {
			return;
		}

		$this->send_notification( $booking_id, 'new_booking' );
	}

	/**
	 * Handle status change.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function on_status_changed( $booking_id, $old_status, $new_status ) {
		$event = null;

		if ( 'bkx-cancelled' === $new_status && ! empty( $this->settings['notify_cancelled'] ) ) {
			$event = 'cancelled';
		} elseif ( 'bkx-completed' === $new_status && ! empty( $this->settings['notify_completed'] ) ) {
			$event = 'completed';
		}

		if ( $event ) {
			$this->send_notification( $booking_id, $event );
		}
	}

	/**
	 * Handle rescheduled booking.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $new_data   New booking data.
	 */
	public function on_booking_rescheduled( $booking_id, $new_data ) {
		if ( empty( $this->settings['notify_rescheduled'] ) ) {
			return;
		}

		$this->send_notification( $booking_id, 'rescheduled' );
	}

	/**
	 * Send notification to all applicable webhooks.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $event      Event type.
	 */
	public function send_notification( $booking_id, $event ) {
		$webhooks = $this->webhooks->get_webhooks_for_event( $event );

		if ( empty( $webhooks ) ) {
			return;
		}

		$payload = $this->build_notification_payload( $booking_id, $event );

		foreach ( $webhooks as $webhook ) {
			$result = $this->api->send_webhook( $webhook->webhook_url, $payload );

			$this->log_notification(
				$webhook->id,
				$booking_id,
				$event,
				$result
			);
		}
	}

	/**
	 * Send test notification.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return true|WP_Error
	 */
	public function send_test( $webhook_id ) {
		$webhook = $this->webhooks->get_webhook( $webhook_id );

		if ( ! $webhook ) {
			return new \WP_Error( 'not_found', __( 'Webhook not found.', 'bkx-discord' ) );
		}

		$payload = $this->build_test_payload();
		$result  = $this->api->send_webhook( $webhook->webhook_url, $payload );

		$this->log_notification( $webhook_id, 0, 'test', $result );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Build notification payload.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $event      Event type.
	 * @return array
	 */
	private function build_notification_payload( $booking_id, $event ) {
		$booking = $this->get_booking_data( $booking_id );
		$config  = $this->get_event_config( $event );

		$fields = array();

		// Service field.
		if ( ! empty( $booking['service'] ) ) {
			$fields[] = array(
				'name'   => 'Service',
				'value'  => $booking['service'],
				'inline' => true,
			);
		}

		// Date/Time field.
		if ( ! empty( $booking['date'] ) ) {
			$fields[] = array(
				'name'   => 'Date & Time',
				'value'  => $booking['date'] . ( $booking['time'] ? ' at ' . $booking['time'] : '' ),
				'inline' => true,
			);
		}

		// Customer field.
		if ( ! empty( $this->settings['include_customer'] ) && ! empty( $booking['customer'] ) ) {
			$fields[] = array(
				'name'   => 'Customer',
				'value'  => $booking['customer'],
				'inline' => true,
			);
		}

		// Staff field.
		if ( ! empty( $this->settings['include_staff'] ) && ! empty( $booking['staff'] ) ) {
			$fields[] = array(
				'name'   => 'Staff',
				'value'  => $booking['staff'],
				'inline' => true,
			);
		}

		// Price field.
		if ( ! empty( $this->settings['include_price'] ) && ! empty( $booking['total'] ) ) {
			$fields[] = array(
				'name'   => 'Total',
				'value'  => $booking['total'],
				'inline' => true,
			);
		}

		// Status field.
		$fields[] = array(
			'name'   => 'Status',
			'value'  => $booking['status'] ?? ucfirst( $event ),
			'inline' => true,
		);

		$embed = $this->api->build_embed( array(
			'title'       => $config['title'] . ' #' . $booking_id,
			'description' => $config['description'],
			'color'       => $config['color'],
			'fields'      => $fields,
			'footer'      => get_bloginfo( 'name' ),
			'url'         => admin_url( 'post.php?post=' . $booking_id . '&action=edit' ),
		) );

		$payload = array(
			'username' => $this->settings['bot_username'] ?? 'BookingX',
			'embeds'   => array( $embed ),
		);

		// Add role mention if configured.
		if ( ! empty( $this->settings['mention_role'] ) ) {
			$payload['content'] = '<@&' . $this->settings['mention_role'] . '>';
		}

		return $payload;
	}

	/**
	 * Build test notification payload.
	 *
	 * @return array
	 */
	private function build_test_payload() {
		$embed = $this->api->build_embed( array(
			'title'       => 'Test Notification',
			'description' => 'This is a test notification from BookingX. Your Discord webhook is working correctly!',
			'color'       => $this->settings['embed_color'] ?? '#5865F2',
			'fields'      => array(
				array(
					'name'   => 'Service',
					'value'  => 'Example Service',
					'inline' => true,
				),
				array(
					'name'   => 'Date & Time',
					'value'  => wp_date( 'l, F j, Y' ) . ' at ' . wp_date( 'g:i A' ),
					'inline' => true,
				),
				array(
					'name'   => 'Customer',
					'value'  => 'John Doe',
					'inline' => true,
				),
				array(
					'name'   => 'Staff',
					'value'  => 'Jane Smith',
					'inline' => true,
				),
				array(
					'name'   => 'Total',
					'value'  => '$50.00',
					'inline' => true,
				),
				array(
					'name'   => 'Status',
					'value'  => 'Confirmed',
					'inline' => true,
				),
			),
			'footer'      => get_bloginfo( 'name' ),
		) );

		return array(
			'username' => $this->settings['bot_username'] ?? 'BookingX',
			'embeds'   => array( $embed ),
		);
	}

	/**
	 * Get event configuration.
	 *
	 * @param string $event Event type.
	 * @return array
	 */
	private function get_event_config( $event ) {
		$configs = array(
			'new_booking' => array(
				'title'       => 'New Booking',
				'description' => 'A new booking has been created.',
				'color'       => '#57F287', // Green.
			),
			'cancelled'   => array(
				'title'       => 'Booking Cancelled',
				'description' => 'A booking has been cancelled.',
				'color'       => '#ED4245', // Red.
			),
			'completed'   => array(
				'title'       => 'Booking Completed',
				'description' => 'A booking has been marked as complete.',
				'color'       => '#5865F2', // Blurple.
			),
			'rescheduled' => array(
				'title'       => 'Booking Rescheduled',
				'description' => 'A booking has been rescheduled.',
				'color'       => '#FEE75C', // Yellow.
			),
		);

		return $configs[ $event ] ?? array(
			'title'       => 'Booking Update',
			'description' => 'A booking has been updated.',
			'color'       => $this->settings['embed_color'] ?? '#5865F2',
		);
	}

	/**
	 * Get booking data.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	private function get_booking_data( $booking_id ) {
		$data = array(
			'service'  => '',
			'date'     => '',
			'time'     => '',
			'customer' => '',
			'staff'    => '',
			'total'    => '',
			'status'   => '',
		);

		// Get booking meta.
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $booking_id, 'booking_time', true );
		$seat_id      = get_post_meta( $booking_id, 'seat_id', true );
		$base_id      = get_post_meta( $booking_id, 'base_id', true );

		// Format date/time.
		if ( $booking_date ) {
			$data['date'] = wp_date( 'l, F j, Y', strtotime( $booking_date ) );
		}

		if ( $booking_time ) {
			$data['time'] = wp_date( 'g:i A', strtotime( $booking_time ) );
		}

		// Get service name.
		if ( $base_id ) {
			$data['service'] = get_the_title( $base_id );
		}

		// Get staff name.
		if ( $seat_id ) {
			$data['staff'] = get_the_title( $seat_id );
		}

		// Get customer info.
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );

		if ( $customer_name ) {
			$data['customer'] = $customer_name;
			if ( $customer_email ) {
				$data['customer'] .= ' (' . $customer_email . ')';
			}
		}

		// Get total.
		$total = get_post_meta( $booking_id, 'booking_total', true );
		if ( $total ) {
			$data['total'] = function_exists( 'wc_price' )
				? strip_tags( wc_price( $total ) )
				: '$' . number_format( (float) $total, 2 );
		}

		// Get status.
		$status = get_post_status( $booking_id );
		$data['status'] = ucfirst( str_replace( 'bkx-', '', $status ) );

		return $data;
	}

	/**
	 * Log notification.
	 *
	 * @param int           $webhook_id Webhook ID.
	 * @param int           $booking_id Booking ID.
	 * @param string        $event      Event type.
	 * @param array|WP_Error $result    API result.
	 */
	private function log_notification( $webhook_id, $booking_id, $event, $result ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_discord_logs';

		$log_data = array(
			'webhook_id'  => $webhook_id,
			'booking_id'  => $booking_id ?: null,
			'event_type'  => $event,
			'status'      => is_wp_error( $result ) ? 'failed' : 'sent',
		);

		if ( is_wp_error( $result ) ) {
			$log_data['error_message'] = $result->get_error_message();
		} elseif ( isset( $result['id'] ) ) {
			$log_data['message_id'] = $result['id'];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $table, $log_data );
	}
}
