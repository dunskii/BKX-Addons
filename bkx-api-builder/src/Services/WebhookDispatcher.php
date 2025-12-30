<?php
/**
 * Webhook Dispatcher service.
 *
 * @package BookingX\APIBuilder\Services
 */

namespace BookingX\APIBuilder\Services;

defined( 'ABSPATH' ) || exit;

/**
 * WebhookDispatcher class.
 */
class WebhookDispatcher {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'bkx_api_webhooks';
	}

	/**
	 * Dispatch webhook event.
	 *
	 * @param string $event Event name (e.g., 'booking.created').
	 * @param array  $data  Event data.
	 */
	public function dispatch( $event, $data ) {
		$settings = get_option( 'bkx_api_builder_settings', array() );

		if ( empty( $settings['enable_webhooks'] ) ) {
			return;
		}

		$webhooks = $this->get_webhooks_for_event( $event );

		foreach ( $webhooks as $webhook ) {
			$this->send_webhook( $webhook, $event, $data );
		}
	}

	/**
	 * Get webhooks subscribed to event.
	 *
	 * @param string $event Event name.
	 * @return array
	 */
	private function get_webhooks_for_event( $event ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$webhooks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				 WHERE status = 'active'
				 AND (events LIKE %s OR events LIKE %s)",
				'%"' . $event . '"%',
				'%"*"%' // Wildcard for all events.
			),
			ARRAY_A
		);

		return $webhooks ?: array();
	}

	/**
	 * Send webhook.
	 *
	 * @param array  $webhook Webhook config.
	 * @param string $event   Event name.
	 * @param array  $data    Event data.
	 */
	private function send_webhook( $webhook, $event, $data ) {
		$payload = array(
			'event'      => $event,
			'timestamp'  => current_time( 'c' ),
			'webhook_id' => $webhook['id'],
			'data'       => $data,
		);

		$body      = wp_json_encode( $payload );
		$signature = $this->generate_signature( $body, $webhook['secret'] );

		$headers = array(
			'Content-Type'      => 'application/json',
			'X-Webhook-Event'   => $event,
			'X-Webhook-Signature' => $signature,
			'X-Webhook-Timestamp' => (string) time(),
			'User-Agent'        => 'BookingX-Webhook/1.0',
		);

		// Add custom headers.
		$custom_headers = json_decode( $webhook['headers'], true ) ?: array();
		$headers        = array_merge( $headers, $custom_headers );

		$args = array(
			'method'    => 'POST',
			'headers'   => $headers,
			'body'      => $body,
			'timeout'   => $webhook['timeout'] ?? 30,
			'sslverify' => true,
		);

		// Use Action Scheduler for async delivery if available.
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time(),
				'bkx_api_deliver_webhook',
				array(
					'webhook_id' => $webhook['id'],
					'url'        => $webhook['url'],
					'args'       => $args,
					'retry'      => 0,
				)
			);
		} else {
			// Synchronous delivery.
			$this->deliver_webhook( $webhook['id'], $webhook['url'], $args, 0 );
		}
	}

	/**
	 * Deliver webhook (can be called async).
	 *
	 * @param int    $webhook_id Webhook ID.
	 * @param string $url        Webhook URL.
	 * @param array  $args       Request args.
	 * @param int    $retry      Current retry count.
	 */
	public function deliver_webhook( $webhook_id, $url, $args, $retry = 0 ) {
		$response = wp_remote_post( $url, $args );

		$response_code = is_wp_error( $response )
			? 0
			: wp_remote_retrieve_response_code( $response );

		$success = $response_code >= 200 && $response_code < 300;

		// Update webhook status.
		$this->update_webhook_status( $webhook_id, $response_code, ! $success );

		// Retry on failure.
		if ( ! $success ) {
			$webhook = $this->get( $webhook_id );

			if ( $webhook && $retry < $webhook['retry_count'] ) {
				$delay = $webhook['retry_delay'] * pow( 2, $retry ); // Exponential backoff.

				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action(
						time() + $delay,
						'bkx_api_deliver_webhook',
						array(
							'webhook_id' => $webhook_id,
							'url'        => $url,
							'args'       => $args,
							'retry'      => $retry + 1,
						)
					);
				}
			}
		}

		// Log delivery.
		$this->log_delivery( $webhook_id, $url, $args['body'] ?? '', $response_code, $response );
	}

	/**
	 * Generate HMAC signature.
	 *
	 * @param string $payload Payload to sign.
	 * @param string $secret  Secret key.
	 * @return string
	 */
	private function generate_signature( $payload, $secret ) {
		$timestamp = time();
		$data      = $timestamp . '.' . $payload;
		$signature = hash_hmac( 'sha256', $data, $secret );

		return 't=' . $timestamp . ',v1=' . $signature;
	}

	/**
	 * Verify webhook signature.
	 *
	 * @param string $payload   Received payload.
	 * @param string $signature Received signature header.
	 * @param string $secret    Expected secret.
	 * @param int    $tolerance Time tolerance in seconds.
	 * @return bool
	 */
	public function verify_signature( $payload, $signature, $secret, $tolerance = 300 ) {
		// Parse signature header.
		preg_match( '/t=(\d+),v1=([a-f0-9]+)/', $signature, $matches );

		if ( count( $matches ) !== 3 ) {
			return false;
		}

		$timestamp          = (int) $matches[1];
		$received_signature = $matches[2];

		// Check timestamp tolerance.
		if ( abs( time() - $timestamp ) > $tolerance ) {
			return false;
		}

		// Verify signature.
		$data              = $timestamp . '.' . $payload;
		$expected_signature = hash_hmac( 'sha256', $data, $secret );

		return hash_equals( $expected_signature, $received_signature );
	}

	/**
	 * Create webhook.
	 *
	 * @param array $data Webhook data.
	 * @return int|\WP_Error Webhook ID or error.
	 */
	public function create( $data ) {
		global $wpdb;

		if ( empty( $data['name'] ) || empty( $data['url'] ) || empty( $data['events'] ) ) {
			return new \WP_Error( 'missing_fields', __( 'Name, URL, and events are required', 'bkx-api-builder' ) );
		}

		// Validate URL.
		if ( ! filter_var( $data['url'], FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', __( 'Invalid webhook URL', 'bkx-api-builder' ) );
		}

		// Generate secret.
		$secret = 'whsec_' . bin2hex( random_bytes( 24 ) );

		$insert_data = array(
			'name'          => sanitize_text_field( $data['name'] ),
			'url'           => esc_url_raw( $data['url'] ),
			'events'        => is_array( $data['events'] ) ? wp_json_encode( $data['events'] ) : $data['events'],
			'secret'        => $secret,
			'headers'       => $data['headers'] ?? '{}',
			'retry_count'   => absint( $data['retry_count'] ?? 3 ),
			'retry_delay'   => absint( $data['retry_delay'] ?? 60 ),
			'timeout'       => absint( $data['timeout'] ?? 30 ),
			'status'        => 'active',
			'created_by'    => get_current_user_id(),
			'created_at'    => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			$insert_data,
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create webhook', 'bkx-api-builder' ) );
		}

		return array(
			'id'     => $wpdb->insert_id,
			'secret' => $secret, // Only returned once.
		);
	}

	/**
	 * Get webhook by ID.
	 *
	 * @param int $id Webhook ID.
	 * @return array|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Get all webhooks.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'per_page' => 50,
			'page'     => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];

		$query = "SELECT * FROM {$this->table}
				  WHERE {$where_clause}
				  ORDER BY created_at DESC
				  LIMIT %d OFFSET %d";

		$values[] = $args['per_page'];
		$values[] = $offset;

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $wpdb->prepare( $query, $values ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $query, ARRAY_A );
		}

		return $results ?: array();
	}

	/**
	 * Update webhook.
	 *
	 * @param int   $id   Webhook ID.
	 * @param array $data Update data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$allowed_fields = array(
			'name', 'url', 'events', 'headers', 'retry_count',
			'retry_delay', 'timeout', 'status',
		);

		$update_data = array();
		$formats     = array();

		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$value = $data[ $field ];

				if ( 'events' === $field && is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}

				$update_data[ $field ] = $value;
				$formats[]             = in_array( $field, array( 'retry_count', 'retry_delay', 'timeout' ), true ) ? '%d' : '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$update_data['updated_at'] = current_time( 'mysql' );
		$formats[]                 = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete webhook.
	 *
	 * @param int $id Webhook ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Regenerate webhook secret.
	 *
	 * @param int $id Webhook ID.
	 * @return string|\WP_Error New secret or error.
	 */
	public function regenerate_secret( $id ) {
		global $wpdb;

		$webhook = $this->get( $id );
		if ( ! $webhook ) {
			return new \WP_Error( 'not_found', __( 'Webhook not found', 'bkx-api-builder' ) );
		}

		$new_secret = 'whsec_' . bin2hex( random_bytes( 24 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array(
				'secret'     => $new_secret,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to regenerate secret', 'bkx-api-builder' ) );
		}

		return $new_secret;
	}

	/**
	 * Update webhook status after delivery.
	 *
	 * @param int  $id            Webhook ID.
	 * @param int  $response_code Response code.
	 * @param bool $failed        Whether delivery failed.
	 */
	private function update_webhook_status( $id, $response_code, $failed ) {
		global $wpdb;

		$update = array(
			'last_triggered_at'   => current_time( 'mysql' ),
			'last_response_code'  => $response_code,
		);

		if ( $failed ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->table}
					 SET last_triggered_at = %s, last_response_code = %d, failure_count = failure_count + 1
					 WHERE id = %d",
					current_time( 'mysql' ),
					$response_code,
					$id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array(
					'last_triggered_at'  => current_time( 'mysql' ),
					'last_response_code' => $response_code,
					'failure_count'      => 0,
				),
				array( 'id' => $id ),
				array( '%s', '%d', '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Log webhook delivery.
	 *
	 * @param int    $webhook_id Webhook ID.
	 * @param string $url        URL.
	 * @param string $body       Request body.
	 * @param int    $code       Response code.
	 * @param mixed  $response   Response object.
	 */
	private function log_delivery( $webhook_id, $url, $body, $code, $response ) {
		$logs = get_option( 'bkx_api_webhook_logs', array() );

		$logs[] = array(
			'webhook_id'    => $webhook_id,
			'url'           => $url,
			'response_code' => $code,
			'response_body' => is_wp_error( $response )
				? $response->get_error_message()
				: substr( wp_remote_retrieve_body( $response ), 0, 1000 ),
			'timestamp'     => current_time( 'c' ),
		);

		// Keep only last 100 logs.
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		update_option( 'bkx_api_webhook_logs', $logs, false );
	}

	/**
	 * Get delivery logs.
	 *
	 * @param int $webhook_id Optional webhook ID filter.
	 * @param int $limit      Number of logs to return.
	 * @return array
	 */
	public function get_delivery_logs( $webhook_id = 0, $limit = 50 ) {
		$logs = get_option( 'bkx_api_webhook_logs', array() );

		if ( $webhook_id ) {
			$logs = array_filter( $logs, function ( $log ) use ( $webhook_id ) {
				return $log['webhook_id'] == $webhook_id;
			} );
		}

		$logs = array_reverse( $logs );

		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Test webhook.
	 *
	 * @param int $id Webhook ID.
	 * @return array Result.
	 */
	public function test( $id ) {
		$webhook = $this->get( $id );

		if ( ! $webhook ) {
			return array(
				'success' => false,
				'message' => __( 'Webhook not found', 'bkx-api-builder' ),
			);
		}

		$payload = array(
			'event'      => 'test',
			'timestamp'  => current_time( 'c' ),
			'webhook_id' => $id,
			'data'       => array(
				'message' => __( 'This is a test webhook delivery', 'bkx-api-builder' ),
			),
		);

		$body      = wp_json_encode( $payload );
		$signature = $this->generate_signature( $body, $webhook['secret'] );

		$headers = array(
			'Content-Type'        => 'application/json',
			'X-Webhook-Event'     => 'test',
			'X-Webhook-Signature' => $signature,
			'X-Webhook-Timestamp' => (string) time(),
			'User-Agent'          => 'BookingX-Webhook/1.0',
		);

		$start    = microtime( true );
		$response = wp_remote_post( $webhook['url'], array(
			'headers' => $headers,
			'body'    => $body,
			'timeout' => $webhook['timeout'] ?? 30,
		) );
		$duration = round( ( microtime( true ) - $start ) * 1000, 2 );

		if ( is_wp_error( $response ) ) {
			return array(
				'success'  => false,
				'message'  => $response->get_error_message(),
				'duration' => $duration,
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		return array(
			'success'       => $code >= 200 && $code < 300,
			'response_code' => $code,
			'response_body' => wp_remote_retrieve_body( $response ),
			'duration'      => $duration,
		);
	}

	/**
	 * Get available events.
	 *
	 * @return array
	 */
	public function get_available_events() {
		return array(
			'booking.created'   => __( 'Booking Created', 'bkx-api-builder' ),
			'booking.updated'   => __( 'Booking Updated', 'bkx-api-builder' ),
			'booking.cancelled' => __( 'Booking Cancelled', 'bkx-api-builder' ),
			'booking.completed' => __( 'Booking Completed', 'bkx-api-builder' ),
			'payment.completed' => __( 'Payment Completed', 'bkx-api-builder' ),
			'payment.failed'    => __( 'Payment Failed', 'bkx-api-builder' ),
			'payment.refunded'  => __( 'Payment Refunded', 'bkx-api-builder' ),
			'customer.created'  => __( 'Customer Created', 'bkx-api-builder' ),
			'customer.updated'  => __( 'Customer Updated', 'bkx-api-builder' ),
			'service.created'   => __( 'Service Created', 'bkx-api-builder' ),
			'service.updated'   => __( 'Service Updated', 'bkx-api-builder' ),
			'staff.created'     => __( 'Staff Created', 'bkx-api-builder' ),
			'staff.updated'     => __( 'Staff Updated', 'bkx-api-builder' ),
		);
	}
}
