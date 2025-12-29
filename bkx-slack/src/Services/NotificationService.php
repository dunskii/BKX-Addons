<?php
/**
 * Notification Service.
 *
 * @package BookingX\Slack\Services
 */

namespace BookingX\Slack\Services;

defined( 'ABSPATH' ) || exit;

/**
 * NotificationService class.
 *
 * Handles sending notifications to Slack.
 */
class NotificationService {

	/**
	 * Slack API instance.
	 *
	 * @var SlackApi
	 */
	private $api;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SlackApi $api Slack API instance.
	 */
	public function __construct( SlackApi $api ) {
		$this->api      = $api;
		$this->settings = get_option( 'bkx_slack_settings', array() );
	}

	/**
	 * Handle booking created event.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data = array() ) {
		if ( empty( $this->settings['notify_new_booking'] ) ) {
			return;
		}

		$this->send_booking_notification( $booking_id, 'new_booking' );
	}

	/**
	 * Handle booking status changed event.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function on_booking_status_changed( $booking_id, $old_status, $new_status ) {
		$event_type = null;

		switch ( $new_status ) {
			case 'bkx-cancelled':
				if ( ! empty( $this->settings['notify_cancelled'] ) ) {
					$event_type = 'cancelled';
				}
				break;

			case 'bkx-completed':
				if ( ! empty( $this->settings['notify_completed'] ) ) {
					$event_type = 'completed';
				}
				break;

			case 'bkx-ack':
				if ( ! empty( $this->settings['notify_rescheduled'] ) && 'bkx-pending' !== $old_status ) {
					$event_type = 'rescheduled';
				}
				break;
		}

		if ( $event_type ) {
			$this->send_booking_notification( $booking_id, $event_type );
		}
	}

	/**
	 * Handle post status transition.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 */
	public function on_post_status_transition( $new_status, $old_status, $post ) {
		if ( 'bkx_booking' !== $post->post_type ) {
			return;
		}

		if ( $new_status === $old_status ) {
			return;
		}

		$this->on_booking_status_changed( $post->ID, $old_status, $new_status );
	}

	/**
	 * Send booking notification.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $event_type Event type.
	 */
	public function send_booking_notification( $booking_id, $event_type ) {
		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return;
		}

		$message = $this->build_booking_message( $booking_id, $event_type );
		$workspaces = $this->get_active_workspaces();

		foreach ( $workspaces as $workspace ) {
			$channels = $this->get_workspace_channels( $workspace->id, $event_type );

			foreach ( $channels as $channel ) {
				$this->send_to_channel( $workspace, $channel, $message, $event_type, $booking_id );
			}

			// Also send via incoming webhook if configured.
			if ( $workspace->incoming_webhook_url ) {
				$this->api->post_webhook( $workspace->incoming_webhook_url, $message );
			}
		}
	}

	/**
	 * Build booking message.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $event_type Event type.
	 * @return array
	 */
	private function build_booking_message( $booking_id, $event_type ) {
		$booking_date  = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time  = get_post_meta( $booking_id, 'booking_time', true );
		$customer_name = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$service_id    = get_post_meta( $booking_id, 'base_id', true );
		$seat_id       = get_post_meta( $booking_id, 'seat_id', true );
		$total         = get_post_meta( $booking_id, 'booking_total', true );

		$service = get_post( $service_id );
		$seat    = get_post( $seat_id );

		$service_name = $service ? $service->post_title : __( 'Unknown Service', 'bkx-slack' );
		$staff_name   = $seat ? $seat->post_title : __( 'Not assigned', 'bkx-slack' );

		$emoji = $this->get_event_emoji( $event_type );
		$title = $this->get_event_title( $event_type );
		$color = $this->get_event_color( $event_type );

		$formatted_date = $booking_date ? wp_date( 'l, F j, Y', strtotime( $booking_date ) ) : '';
		$formatted_time = $booking_time ? wp_date( 'g:i A', strtotime( $booking_time ) ) : '';

		$message = array(
			'text'        => sprintf( '%s %s', $emoji, $title ),
			'attachments' => array(
				array(
					'color'  => $color,
					'blocks' => array(
						array(
							'type' => 'section',
							'text' => array(
								'type' => 'mrkdwn',
								'text' => sprintf(
									"*%s #%d*\n%s %s",
									$title,
									$booking_id,
									$emoji,
									$service_name
								),
							),
						),
						array(
							'type'   => 'section',
							'fields' => array(
								array(
									'type' => 'mrkdwn',
									'text' => sprintf( "*Customer:*\n%s", $customer_name ?: __( 'Guest', 'bkx-slack' ) ),
								),
								array(
									'type' => 'mrkdwn',
									'text' => sprintf( "*Staff:*\n%s", $staff_name ),
								),
								array(
									'type' => 'mrkdwn',
									'text' => sprintf( "*Date:*\n%s", $formatted_date ),
								),
								array(
									'type' => 'mrkdwn',
									'text' => sprintf( "*Time:*\n%s", $formatted_time ),
								),
							),
						),
					),
				),
			),
		);

		// Add action buttons for new bookings.
		if ( 'new_booking' === $event_type ) {
			$message['attachments'][0]['blocks'][] = array(
				'type'     => 'actions',
				'block_id' => 'booking_actions_' . $booking_id,
				'elements' => array(
					array(
						'type'      => 'button',
						'text'      => array(
							'type'  => 'plain_text',
							'text'  => __( 'Confirm', 'bkx-slack' ),
							'emoji' => true,
						),
						'style'     => 'primary',
						'action_id' => 'confirm_booking',
						'value'     => (string) $booking_id,
					),
					array(
						'type'      => 'button',
						'text'      => array(
							'type'  => 'plain_text',
							'text'  => __( 'View Details', 'bkx-slack' ),
							'emoji' => true,
						),
						'action_id' => 'view_booking',
						'url'       => admin_url( 'post.php?post=' . $booking_id . '&action=edit' ),
					),
				),
			);
		}

		// Add context with total if available.
		if ( $total ) {
			$message['attachments'][0]['blocks'][] = array(
				'type'     => 'context',
				'elements' => array(
					array(
						'type' => 'mrkdwn',
						'text' => sprintf( '*Total:* %s', wc_price( $total ) ),
					),
				),
			);
		}

		return $message;
	}

	/**
	 * Get event emoji.
	 *
	 * @param string $event_type Event type.
	 * @return string
	 */
	private function get_event_emoji( $event_type ) {
		$emojis = array(
			'new_booking' => ':calendar:',
			'cancelled'   => ':x:',
			'completed'   => ':white_check_mark:',
			'rescheduled' => ':arrows_counterclockwise:',
		);

		return $emojis[ $event_type ] ?? ':bell:';
	}

	/**
	 * Get event title.
	 *
	 * @param string $event_type Event type.
	 * @return string
	 */
	private function get_event_title( $event_type ) {
		$titles = array(
			'new_booking' => __( 'New Booking', 'bkx-slack' ),
			'cancelled'   => __( 'Booking Cancelled', 'bkx-slack' ),
			'completed'   => __( 'Booking Completed', 'bkx-slack' ),
			'rescheduled' => __( 'Booking Rescheduled', 'bkx-slack' ),
		);

		return $titles[ $event_type ] ?? __( 'Booking Update', 'bkx-slack' );
	}

	/**
	 * Get event color.
	 *
	 * @param string $event_type Event type.
	 * @return string
	 */
	private function get_event_color( $event_type ) {
		$colors = array(
			'new_booking' => '#36a64f', // Green.
			'cancelled'   => '#dc3545', // Red.
			'completed'   => '#007bff', // Blue.
			'rescheduled' => '#ffc107', // Yellow.
		);

		return $colors[ $event_type ] ?? '#6c757d';
	}

	/**
	 * Get active workspaces.
	 *
	 * @return array
	 */
	private function get_active_workspaces() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}bkx_slack_workspaces WHERE status = 'active'"
		);
	}

	/**
	 * Get workspace channels for an event type.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $event_type   Event type.
	 * @return array
	 */
	private function get_workspace_channels( $workspace_id, $event_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$channels = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}bkx_slack_channels WHERE workspace_id = %d AND enabled = 1",
			$workspace_id
		) );

		$filtered = array();

		foreach ( $channels as $channel ) {
			$types = json_decode( $channel->notification_types, true ) ?: array();

			// If no types specified, send all notifications.
			if ( empty( $types ) || in_array( $event_type, $types, true ) ) {
				$filtered[] = $channel;
			}
		}

		return $filtered;
	}

	/**
	 * Send message to a channel.
	 *
	 * @param object $workspace  Workspace object.
	 * @param object $channel    Channel object.
	 * @param array  $message    Message data.
	 * @param string $event_type Event type.
	 * @param int    $booking_id Booking ID.
	 */
	private function send_to_channel( $workspace, $channel, $message, $event_type, $booking_id ) {
		$token = $this->decrypt_token( $workspace->access_token );

		$result = $this->api->post_message( $channel->channel_id, $message, $token );

		// Log the notification.
		$this->log_notification(
			$workspace->id,
			$channel->channel_id,
			$event_type,
			$booking_id,
			$message['text'],
			is_wp_error( $result ) ? 'failed' : 'sent',
			is_wp_error( $result ) ? $result->get_error_message() : null
		);
	}

	/**
	 * Send test notification.
	 *
	 * @param int    $workspace_id Workspace ID.
	 * @param string $channel_id   Channel ID.
	 * @return bool|WP_Error
	 */
	public function send_test_notification( $workspace_id, $channel_id ) {
		$token = $this->api->get_workspace_token( $workspace_id );

		if ( ! $token ) {
			return new \WP_Error( 'no_token', __( 'Workspace not found or disconnected.', 'bkx-slack' ) );
		}

		$message = array(
			'text'   => ':white_check_mark: BookingX Slack integration is working!',
			'blocks' => array(
				array(
					'type' => 'section',
					'text' => array(
						'type' => 'mrkdwn',
						'text' => '*BookingX Test Notification*',
					),
				),
				array(
					'type' => 'section',
					'text' => array(
						'type' => 'mrkdwn',
						'text' => ':white_check_mark: Your Slack integration is configured correctly! You will receive booking notifications in this channel.',
					),
				),
				array(
					'type'     => 'context',
					'elements' => array(
						array(
							'type' => 'mrkdwn',
							'text' => sprintf( 'Sent at %s', wp_date( 'F j, Y g:i A' ) ),
						),
					),
				),
			),
		);

		return $this->api->post_message( $channel_id, $message, $token );
	}

	/**
	 * Log notification.
	 *
	 * @param int    $workspace_id  Workspace ID.
	 * @param string $channel_id    Channel ID.
	 * @param string $event_type    Event type.
	 * @param int    $booking_id    Booking ID.
	 * @param string $message       Message text.
	 * @param string $status        Status.
	 * @param string $error_message Error message.
	 */
	private function log_notification( $workspace_id, $channel_id, $event_type, $booking_id, $message, $status, $error_message = null ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_slack_logs',
			array(
				'workspace_id'  => $workspace_id,
				'channel_id'    => $channel_id,
				'event_type'    => $event_type,
				'booking_id'    => $booking_id,
				'message'       => $message,
				'status'        => $status,
				'error_message' => $error_message,
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Decrypt stored token.
	 *
	 * @param string $encrypted Encrypted token.
	 * @return string
	 */
	private function decrypt_token( $encrypted ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $encrypted );
		}

		$key  = $this->get_encryption_key();
		$data = base64_decode( $encrypted );
		$iv   = substr( $data, 0, 16 );
		$cipher = substr( $data, 16 );

		return openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );
	}

	/**
	 * Get encryption key.
	 *
	 * @return string
	 */
	private function get_encryption_key() {
		$key = defined( 'BKX_SLACK_ENCRYPTION_KEY' ) ? BKX_SLACK_ENCRYPTION_KEY : AUTH_KEY;
		return hash( 'sha256', $key, true );
	}
}
