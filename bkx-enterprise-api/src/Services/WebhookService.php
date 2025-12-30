<?php
/**
 * Webhook Service.
 *
 * @package BookingX\EnterpriseAPI\Services
 */

namespace BookingX\EnterpriseAPI\Services;

defined( 'ABSPATH' ) || exit;

/**
 * WebhookService class.
 */
class WebhookService {

	/**
	 * Trigger webhook for event.
	 *
	 * @param string $event Event name.
	 * @param mixed  $data  Event data.
	 */
	public function trigger( $event, $data = null ) {
		$webhooks = $this->get_webhooks_for_event( $event );

		foreach ( $webhooks as $webhook ) {
			$this->queue_delivery( $webhook, $event, $data );
		}
	}

	/**
	 * Get webhooks for event.
	 *
	 * @param string $event Event name.
	 * @return array
	 */
	private function get_webhooks_for_event( $event ) {
		global $wpdb;

		$webhooks = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}bkx_webhooks WHERE is_active = 1"
		);

		return array_filter( $webhooks, function( $webhook ) use ( $event ) {
			$events = json_decode( $webhook->events, true ) ?: array();
			return in_array( '*', $events, true ) || in_array( $event, $events, true );
		} );
	}

	/**
	 * Queue webhook delivery.
	 *
	 * @param object $webhook Webhook object.
	 * @param string $event   Event name.
	 * @param mixed  $data    Event data.
	 */
	private function queue_delivery( $webhook, $event, $data ) {
		global $wpdb;

		$payload = wp_json_encode( array(
			'event'     => $event,
			'timestamp' => gmdate( 'c' ),
			'data'      => $data,
		) );

		$wpdb->insert(
			$wpdb->prefix . 'bkx_webhook_deliveries',
			array(
				'webhook_id' => $webhook->id,
				'event'      => $event,
				'payload'    => $payload,
				'status'     => 'pending',
			)
		);

		$delivery_id = $wpdb->insert_id;

		// Schedule immediate delivery.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'bkx_deliver_webhook', array( $delivery_id ) );
		} else {
			wp_schedule_single_event( time(), 'bkx_deliver_webhook', array( $delivery_id ) );
		}
	}

	/**
	 * Deliver webhook.
	 *
	 * @param int $delivery_id Delivery ID.
	 */
	public function deliver( $delivery_id ) {
		global $wpdb;

		$delivery = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT d.*, w.url, w.secret, w.headers, w.timeout_seconds, w.retry_count
				FROM {$wpdb->prefix}bkx_webhook_deliveries d
				JOIN {$wpdb->prefix}bkx_webhooks w ON d.webhook_id = w.id
				WHERE d.id = %d",
				$delivery_id
			)
		);

		if ( ! $delivery ) {
			return;
		}

		// Build headers.
		$headers = array(
			'Content-Type'           => 'application/json',
			'X-BookingX-Event'       => $delivery->event,
			'X-BookingX-Delivery-ID' => $delivery_id,
			'X-BookingX-Signature'   => $this->generate_signature( $delivery->payload, $delivery->secret ),
		);

		// Add custom headers.
		$custom_headers = json_decode( $delivery->headers, true ) ?: array();
		$headers = array_merge( $headers, $custom_headers );

		// Send request.
		$start_time = microtime( true );

		$response = wp_remote_post( $delivery->url, array(
			'headers' => $headers,
			'body'    => $delivery->payload,
			'timeout' => $delivery->timeout_seconds ?: 30,
		) );

		$duration = round( ( microtime( true ) - $start_time ) * 1000 );

		// Handle response.
		if ( is_wp_error( $response ) ) {
			$this->handle_failure( $delivery, $response->get_error_message(), $duration );
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( $code >= 200 && $code < 300 ) {
				$this->handle_success( $delivery, $code, $body, $duration );
			} else {
				$this->handle_failure( $delivery, "HTTP {$code}: {$body}", $duration );
			}
		}
	}

	/**
	 * Handle successful delivery.
	 *
	 * @param object $delivery Delivery object.
	 * @param int    $code     Response code.
	 * @param string $body     Response body.
	 * @param int    $duration Duration in ms.
	 */
	private function handle_success( $delivery, $code, $body, $duration ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'bkx_webhook_deliveries',
			array(
				'status'        => 'success',
				'response_code' => $code,
				'response_body' => $body,
				'duration_ms'   => $duration,
				'delivered_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $delivery->id )
		);
	}

	/**
	 * Handle failed delivery.
	 *
	 * @param object $delivery Delivery object.
	 * @param string $error    Error message.
	 * @param int    $duration Duration in ms.
	 */
	private function handle_failure( $delivery, $error, $duration ) {
		global $wpdb;

		$attempt = $delivery->attempt + 1;

		$wpdb->update(
			$wpdb->prefix . 'bkx_webhook_deliveries',
			array(
				'status'        => 'failed',
				'error_message' => $error,
				'duration_ms'   => $duration,
				'attempt'       => $attempt,
			),
			array( 'id' => $delivery->id )
		);

		// Retry if under retry limit.
		if ( $attempt < $delivery->retry_count ) {
			$delay = pow( 2, $attempt ) * 60; // Exponential backoff.

			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + $delay, 'bkx_deliver_webhook', array( $delivery->id ) );
			} else {
				wp_schedule_single_event( time() + $delay, 'bkx_deliver_webhook', array( $delivery->id ) );
			}
		}
	}

	/**
	 * Generate webhook signature.
	 *
	 * @param string $payload Payload.
	 * @param string $secret  Secret.
	 * @return string
	 */
	private function generate_signature( $payload, $secret ) {
		return 'sha256=' . hash_hmac( 'sha256', $payload, $secret );
	}

	/**
	 * List webhooks.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_webhooks( $request ) {
		global $wpdb;

		$webhooks = $wpdb->get_results(
			"SELECT id, name, url, events, is_active, retry_count, timeout_seconds, created_at, updated_at
			FROM {$wpdb->prefix}bkx_webhooks
			ORDER BY created_at DESC"
		);

		foreach ( $webhooks as $webhook ) {
			$webhook->events = json_decode( $webhook->events, true );

			// Get recent deliveries count.
			$webhook->recent_deliveries = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_webhook_deliveries
					WHERE webhook_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
					$webhook->id
				)
			);

			$webhook->recent_failures = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_webhook_deliveries
					WHERE webhook_id = %d AND status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
					$webhook->id
				)
			);
		}

		return rest_ensure_response( $webhooks );
	}

	/**
	 * Get single webhook.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_webhook( $request ) {
		global $wpdb;

		$id = $request->get_param( 'id' );

		$webhook = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_webhooks WHERE id = %d",
				$id
			)
		);

		if ( ! $webhook ) {
			return new \WP_Error( 'not_found', __( 'Webhook not found.', 'bkx-enterprise-api' ), array( 'status' => 404 ) );
		}

		$webhook->events  = json_decode( $webhook->events, true );
		$webhook->headers = json_decode( $webhook->headers, true );

		// Get recent deliveries.
		$webhook->deliveries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, event, status, response_code, duration_ms, error_message, created_at, delivered_at
				FROM {$wpdb->prefix}bkx_webhook_deliveries
				WHERE webhook_id = %d
				ORDER BY created_at DESC
				LIMIT 20",
				$id
			)
		);

		return rest_ensure_response( $webhook );
	}

	/**
	 * Create webhook.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_webhook( $request ) {
		global $wpdb;

		$name    = sanitize_text_field( $request->get_param( 'name' ) );
		$url     = esc_url_raw( $request->get_param( 'url' ) );
		$events  = $request->get_param( 'events' ) ?: array( '*' );
		$headers = $request->get_param( 'headers' ) ?: array();

		if ( empty( $name ) || empty( $url ) ) {
			return new \WP_Error( 'missing_fields', __( 'Name and URL are required.', 'bkx-enterprise-api' ), array( 'status' => 400 ) );
		}

		$secret = bin2hex( random_bytes( 32 ) );

		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_webhooks',
			array(
				'name'            => $name,
				'url'             => $url,
				'secret'          => $secret,
				'events'          => wp_json_encode( array_map( 'sanitize_text_field', $events ) ),
				'headers'         => wp_json_encode( $headers ),
				'is_active'       => 1,
				'retry_count'     => absint( $request->get_param( 'retry_count' ) ) ?: 3,
				'timeout_seconds' => absint( $request->get_param( 'timeout_seconds' ) ) ?: 30,
				'created_by'      => get_current_user_id(),
			)
		);

		if ( ! $result ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create webhook.', 'bkx-enterprise-api' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'id'      => $wpdb->insert_id,
			'secret'  => $secret,
			'message' => __( 'Webhook created. Save the secret securely - it will only be shown once.', 'bkx-enterprise-api' ),
		) );
	}

	/**
	 * Update webhook.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_webhook( $request ) {
		global $wpdb;

		$id = $request->get_param( 'id' );

		$update_data = array();

		if ( $request->has_param( 'name' ) ) {
			$update_data['name'] = sanitize_text_field( $request->get_param( 'name' ) );
		}

		if ( $request->has_param( 'url' ) ) {
			$update_data['url'] = esc_url_raw( $request->get_param( 'url' ) );
		}

		if ( $request->has_param( 'events' ) ) {
			$update_data['events'] = wp_json_encode( array_map( 'sanitize_text_field', $request->get_param( 'events' ) ) );
		}

		if ( $request->has_param( 'is_active' ) ) {
			$update_data['is_active'] = (bool) $request->get_param( 'is_active' ) ? 1 : 0;
		}

		if ( $request->has_param( 'headers' ) ) {
			$update_data['headers'] = wp_json_encode( $request->get_param( 'headers' ) );
		}

		if ( empty( $update_data ) ) {
			return new \WP_Error( 'no_changes', __( 'No changes provided.', 'bkx-enterprise-api' ), array( 'status' => 400 ) );
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'bkx_webhooks',
			$update_data,
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update webhook.', 'bkx-enterprise-api' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'updated' => true ) );
	}

	/**
	 * Delete webhook.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_webhook( $request ) {
		global $wpdb;

		$id = $request->get_param( 'id' );

		// Delete deliveries first.
		$wpdb->delete(
			$wpdb->prefix . 'bkx_webhook_deliveries',
			array( 'webhook_id' => $id )
		);

		$result = $wpdb->delete(
			$wpdb->prefix . 'bkx_webhooks',
			array( 'id' => $id )
		);

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete webhook.', 'bkx-enterprise-api' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Test webhook.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_webhook( $request ) {
		global $wpdb;

		$id = $request->get_param( 'id' );

		$webhook = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_webhooks WHERE id = %d",
				$id
			)
		);

		if ( ! $webhook ) {
			return new \WP_Error( 'not_found', __( 'Webhook not found.', 'bkx-enterprise-api' ), array( 'status' => 404 ) );
		}

		// Send test payload.
		$payload = wp_json_encode( array(
			'event'     => 'test',
			'timestamp' => gmdate( 'c' ),
			'data'      => array(
				'message' => 'This is a test webhook delivery from BookingX.',
			),
		) );

		$headers = array(
			'Content-Type'           => 'application/json',
			'X-BookingX-Event'       => 'test',
			'X-BookingX-Delivery-ID' => 'test-' . time(),
			'X-BookingX-Signature'   => $this->generate_signature( $payload, $webhook->secret ),
		);

		$custom_headers = json_decode( $webhook->headers, true ) ?: array();
		$headers = array_merge( $headers, $custom_headers );

		$start_time = microtime( true );

		$response = wp_remote_post( $webhook->url, array(
			'headers' => $headers,
			'body'    => $payload,
			'timeout' => $webhook->timeout_seconds ?: 30,
		) );

		$duration = round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'test_failed', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		return rest_ensure_response( array(
			'success'       => $code >= 200 && $code < 300,
			'response_code' => $code,
			'response_body' => $body,
			'duration_ms'   => $duration,
		) );
	}

	/**
	 * Get available webhook events.
	 *
	 * @return array
	 */
	public function get_available_events() {
		return array(
			'booking.created'   => __( 'Booking Created', 'bkx-enterprise-api' ),
			'booking.updated'   => __( 'Booking Updated', 'bkx-enterprise-api' ),
			'booking.confirmed' => __( 'Booking Confirmed', 'bkx-enterprise-api' ),
			'booking.cancelled' => __( 'Booking Cancelled', 'bkx-enterprise-api' ),
			'booking.completed' => __( 'Booking Completed', 'bkx-enterprise-api' ),
			'payment.received'  => __( 'Payment Received', 'bkx-enterprise-api' ),
			'payment.refunded'  => __( 'Payment Refunded', 'bkx-enterprise-api' ),
			'service.created'   => __( 'Service Created', 'bkx-enterprise-api' ),
			'service.updated'   => __( 'Service Updated', 'bkx-enterprise-api' ),
			'staff.created'     => __( 'Staff Created', 'bkx-enterprise-api' ),
			'staff.updated'     => __( 'Staff Updated', 'bkx-enterprise-api' ),
		);
	}
}
