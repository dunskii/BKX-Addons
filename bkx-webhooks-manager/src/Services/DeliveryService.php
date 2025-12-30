<?php
/**
 * Delivery Service.
 *
 * @package BookingX\WebhooksManager
 */

namespace BookingX\WebhooksManager\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class DeliveryService
 *
 * Handles webhook delivery and retry logic.
 */
class DeliveryService {

	/**
	 * Deliveries table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table    = $wpdb->prefix . 'bkx_webhook_deliveries';
		$this->settings = get_option( 'bkx_webhooks_manager_settings', array() );
	}

	/**
	 * Create a delivery record.
	 *
	 * @param int    $webhook_id Webhook ID.
	 * @param string $event_type Event type.
	 * @param array  $payload    Event payload.
	 * @param string $event_id   Optional event ID.
	 * @return int|false Delivery ID or false.
	 */
	public function create( int $webhook_id, string $event_type, array $payload, string $event_id = '' ) {
		global $wpdb;

		$data = array(
			'webhook_id'   => $webhook_id,
			'event_type'   => $event_type,
			'event_id'     => $event_id ?: $this->generate_event_id(),
			'payload'      => wp_json_encode( $payload ),
			'attempt'      => 0,
			'status'       => 'pending',
			'scheduled_at' => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			$data,
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Deliver a webhook.
	 *
	 * @param int    $delivery_id Delivery ID.
	 * @param object $webhook     Webhook object.
	 * @return bool True on success, false on failure.
	 */
	public function deliver( int $delivery_id, $webhook ): bool {
		global $wpdb;

		$delivery = $this->get( $delivery_id );
		if ( ! $delivery ) {
			return false;
		}

		// Update attempt count.
		$attempt = $delivery->attempt + 1;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array(
				'attempt' => $attempt,
				'status'  => 'delivering',
			),
			array( 'id' => $delivery_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		// Build request.
		$payload = json_decode( $delivery->payload, true );

		// Get signature service.
		$addon            = \BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
		$signature_service = $addon->get_service( 'signature_service' );

		// Sign the payload.
		$signature = $signature_service->sign( $delivery->payload, $webhook->secret );

		// Build headers.
		$headers = array(
			'Content-Type'        => $webhook->content_type,
			'User-Agent'          => 'BookingX-Webhooks/1.0',
			'X-BKX-Event'         => $delivery->event_type,
			'X-BKX-Event-ID'      => $delivery->event_id,
			'X-BKX-Delivery-ID'   => $delivery_id,
			'X-BKX-Signature'     => $signature,
			'X-BKX-Timestamp'     => time(),
		);

		// Add custom headers.
		if ( ! empty( $webhook->headers ) ) {
			$headers = array_merge( $headers, $webhook->headers );
		}

		// Prepare body based on format.
		$body = $this->format_body( $payload, $webhook->payload_format );

		$start_time = microtime( true );

		// Make the request.
		$response = wp_remote_request(
			$webhook->url,
			array(
				'method'      => $webhook->http_method,
				'timeout'     => $webhook->timeout,
				'headers'     => $headers,
				'body'        => $body,
				'sslverify'   => $webhook->verify_ssl,
				'redirection' => 5,
			)
		);

		$end_time      = microtime( true );
		$response_time = round( ( $end_time - $start_time ) * 1000, 2 );

		// Process response.
		if ( is_wp_error( $response ) ) {
			return $this->handle_failure(
				$delivery_id,
				$webhook,
				$attempt,
				0,
				'',
				array(),
				$response->get_error_message(),
				$response_time,
				$headers
			);
		}

		$response_code    = wp_remote_retrieve_response_code( $response );
		$response_body    = wp_remote_retrieve_body( $response );
		$response_headers = wp_remote_retrieve_headers( $response )->getAll();

		// Check for success (2xx status codes).
		if ( $response_code >= 200 && $response_code < 300 ) {
			return $this->handle_success(
				$delivery_id,
				$webhook,
				$response_code,
				$response_body,
				$response_headers,
				$response_time,
				$headers
			);
		}

		// Handle failure.
		return $this->handle_failure(
			$delivery_id,
			$webhook,
			$attempt,
			$response_code,
			$response_body,
			$response_headers,
			'HTTP ' . $response_code,
			$response_time,
			$headers
		);
	}

	/**
	 * Handle successful delivery.
	 *
	 * @param int    $delivery_id      Delivery ID.
	 * @param object $webhook          Webhook object.
	 * @param int    $response_code    HTTP response code.
	 * @param string $response_body    Response body.
	 * @param array  $response_headers Response headers.
	 * @param float  $response_time    Response time in ms.
	 * @param array  $request_headers  Request headers.
	 * @return bool True.
	 */
	private function handle_success(
		int $delivery_id,
		$webhook,
		int $response_code,
		string $response_body,
		array $response_headers,
		float $response_time,
		array $request_headers
	): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array(
				'status'           => 'delivered',
				'response_code'    => $response_code,
				'response_body'    => $this->truncate_response( $response_body ),
				'response_headers' => wp_json_encode( $response_headers ),
				'response_time'    => $response_time,
				'request_headers'  => wp_json_encode( $request_headers ),
				'delivered_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $delivery_id ),
			array( '%s', '%d', '%s', '%s', '%f', '%s', '%s' ),
			array( '%d' )
		);

		// Update webhook stats.
		$addon          = \BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
		$webhook_manager = $addon->get_service( 'webhook_manager' );
		$webhook_manager->update_stats( $webhook->id, true, $response_code );

		/**
		 * Fires after successful webhook delivery.
		 *
		 * @param int    $delivery_id Delivery ID.
		 * @param object $webhook     Webhook object.
		 * @param int    $response_code Response code.
		 */
		do_action( 'bkx_webhook_delivered', $delivery_id, $webhook, $response_code );

		return true;
	}

	/**
	 * Handle failed delivery.
	 *
	 * @param int    $delivery_id      Delivery ID.
	 * @param object $webhook          Webhook object.
	 * @param int    $attempt          Attempt number.
	 * @param int    $response_code    HTTP response code.
	 * @param string $response_body    Response body.
	 * @param array  $response_headers Response headers.
	 * @param string $error_message    Error message.
	 * @param float  $response_time    Response time in ms.
	 * @param array  $request_headers  Request headers.
	 * @return bool False.
	 */
	private function handle_failure(
		int $delivery_id,
		$webhook,
		int $attempt,
		int $response_code,
		string $response_body,
		array $response_headers,
		string $error_message,
		float $response_time,
		array $request_headers
	): bool {
		global $wpdb;

		$max_retries = $webhook->retry_count;
		$should_retry = $attempt < $max_retries;

		$update_data = array(
			'response_code'    => $response_code,
			'response_body'    => $this->truncate_response( $response_body ),
			'response_headers' => wp_json_encode( $response_headers ),
			'response_time'    => $response_time,
			'request_headers'  => wp_json_encode( $request_headers ),
			'error_message'    => $error_message,
		);

		if ( $should_retry ) {
			// Calculate next retry time with exponential backoff.
			$delay                       = $webhook->retry_delay * pow( $webhook->retry_multiplier, $attempt - 1 );
			$update_data['status']       = 'pending';
			$update_data['scheduled_at'] = gmdate( 'Y-m-d H:i:s', time() + $delay );
		} else {
			$update_data['status'] = 'failed';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $delivery_id ),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Update webhook stats.
		$addon          = \BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
		$webhook_manager = $addon->get_service( 'webhook_manager' );
		$webhook_manager->update_stats( $webhook->id, false, $response_code );

		// Check for failure notification.
		if ( ! $should_retry ) {
			$this->maybe_send_failure_notification( $webhook, $delivery_id, $error_message );

			/**
			 * Fires when webhook delivery fails permanently.
			 *
			 * @param int    $delivery_id   Delivery ID.
			 * @param object $webhook       Webhook object.
			 * @param string $error_message Error message.
			 */
			do_action( 'bkx_webhook_failed', $delivery_id, $webhook, $error_message );
		}

		return false;
	}

	/**
	 * Get a delivery by ID.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @return object|null Delivery object or null.
	 */
	public function get( int $delivery_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$this->table,
				$delivery_id
			)
		);
	}

	/**
	 * Get deliveries with optional filtering.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of deliveries.
	 */
	public function get_all( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'webhook_id' => 0,
			'event_type' => '',
			'status'     => '',
			'date_from'  => '',
			'date_to'    => '',
			'orderby'    => 'created_at',
			'order'      => 'DESC',
			'limit'      => 50,
			'offset'     => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['webhook_id'] ) ) {
			$where[]  = 'webhook_id = %d';
			$values[] = $args['webhook_id'];
		}

		if ( ! empty( $args['event_type'] ) ) {
			$where[]  = 'event_type = %s';
			$values[] = $args['event_type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $args['date_to'];
		}

		$allowed_orderby = array( 'id', 'event_type', 'status', 'attempt', 'response_time', 'created_at', 'delivered_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM {$this->table} WHERE {$where_clause} ORDER BY {$orderby} {$order}";

		if ( $args['limit'] > 0 ) {
			$query   .= ' LIMIT %d OFFSET %d';
			$values[] = $args['limit'];
			$values[] = $args['offset'];
		}

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $query, $values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $query );
	}

	/**
	 * Get pending deliveries for retry.
	 *
	 * @param int $limit Maximum deliveries to retrieve.
	 * @return array Array of pending deliveries.
	 */
	public function get_pending_retries( int $limit = 100 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.*, w.url, w.secret, w.http_method, w.content_type, w.timeout,
					w.retry_count, w.retry_delay, w.retry_multiplier, w.verify_ssl,
					w.headers as webhook_headers, w.payload_format
				FROM %i d
				INNER JOIN {$wpdb->prefix}bkx_webhooks w ON d.webhook_id = w.id
				WHERE d.status = 'pending'
				AND d.scheduled_at <= %s
				AND d.attempt > 0
				AND w.status = 'active'
				ORDER BY d.scheduled_at ASC
				LIMIT %d",
				$this->table,
				current_time( 'mysql' ),
				$limit
			)
		);
	}

	/**
	 * Retry a specific delivery.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @return bool True on success, false on failure.
	 */
	public function retry( int $delivery_id ): bool {
		global $wpdb;

		$delivery = $this->get( $delivery_id );
		if ( ! $delivery ) {
			return false;
		}

		// Reset delivery for retry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array(
				'status'       => 'pending',
				'scheduled_at' => current_time( 'mysql' ),
				'attempt'      => 0,
			),
			array( 'id' => $delivery_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Delete old delivery logs.
	 *
	 * @param int $days Days to keep logs.
	 * @return int Number of deleted records.
	 */
	public function cleanup( int $days = 30 ): int {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE created_at < %s",
				$this->table,
				$cutoff_date
			)
		);

		return (int) $deleted;
	}

	/**
	 * Get delivery statistics.
	 *
	 * @param array $args Filter arguments.
	 * @return array Statistics.
	 */
	public function get_stats( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'webhook_id' => 0,
			'date_from'  => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
			'date_to'    => gmdate( 'Y-m-d' ),
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['webhook_id'] ) ) {
			$where[]  = 'webhook_id = %d';
			$values[] = $args['webhook_id'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		// Get counts by status.
		$query = "SELECT status, COUNT(*) as count FROM {$this->table} WHERE {$where_clause} GROUP BY status";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $query, $values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$status_counts = $wpdb->get_results( $query, OBJECT_K );

		// Get average response time.
		$query = "SELECT AVG(response_time) as avg_time FROM {$this->table} WHERE {$where_clause} AND response_time > 0";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $query, $values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$avg_response = $wpdb->get_var( $query );

		// Get deliveries by day.
		$query = "SELECT DATE(created_at) as date, status, COUNT(*) as count
				  FROM {$this->table}
				  WHERE {$where_clause}
				  GROUP BY DATE(created_at), status
				  ORDER BY date ASC";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $query, $values );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$daily_data = $wpdb->get_results( $query );

		// Format daily data.
		$daily = array();
		foreach ( $daily_data as $row ) {
			if ( ! isset( $daily[ $row->date ] ) ) {
				$daily[ $row->date ] = array(
					'delivered' => 0,
					'failed'    => 0,
					'pending'   => 0,
				);
			}
			$daily[ $row->date ][ $row->status ] = (int) $row->count;
		}

		return array(
			'total'             => array_sum( array_column( (array) $status_counts, 'count' ) ),
			'delivered'         => isset( $status_counts['delivered'] ) ? (int) $status_counts['delivered']->count : 0,
			'failed'            => isset( $status_counts['failed'] ) ? (int) $status_counts['failed']->count : 0,
			'pending'           => isset( $status_counts['pending'] ) ? (int) $status_counts['pending']->count : 0,
			'avg_response_time' => round( (float) $avg_response, 2 ),
			'daily'             => $daily,
		);
	}

	/**
	 * Format body based on payload format.
	 *
	 * @param array  $payload Payload data.
	 * @param string $format  Format type.
	 * @return string Formatted body.
	 */
	private function format_body( array $payload, string $format ): string {
		switch ( $format ) {
			case 'form':
				return http_build_query( $payload );
			case 'xml':
				return $this->array_to_xml( $payload );
			case 'json':
			default:
				return wp_json_encode( $payload );
		}
	}

	/**
	 * Convert array to XML.
	 *
	 * @param array  $data    Data to convert.
	 * @param string $root    Root element name.
	 * @param object $xml     SimpleXMLElement object.
	 * @return string XML string.
	 */
	private function array_to_xml( array $data, string $root = 'webhook', $xml = null ): string {
		if ( null === $xml ) {
			$xml = new \SimpleXMLElement( "<?xml version=\"1.0\"?><{$root}></{$root}>" );
		}

		foreach ( $data as $key => $value ) {
			// Handle numeric keys.
			if ( is_numeric( $key ) ) {
				$key = 'item';
			}

			if ( is_array( $value ) ) {
				$child = $xml->addChild( $key );
				$this->array_to_xml( $value, $root, $child );
			} else {
				$xml->addChild( $key, htmlspecialchars( (string) $value ) );
			}
		}

		return $xml->asXML();
	}

	/**
	 * Truncate response body if too large.
	 *
	 * @param string $response Response body.
	 * @param int    $max_size Maximum size in bytes.
	 * @return string Truncated response.
	 */
	private function truncate_response( string $response, int $max_size = 65535 ): string {
		if ( strlen( $response ) > $max_size ) {
			return substr( $response, 0, $max_size - 50 ) . '... [truncated]';
		}
		return $response;
	}

	/**
	 * Generate unique event ID.
	 *
	 * @return string Event ID.
	 */
	private function generate_event_id(): string {
		return 'evt_' . bin2hex( random_bytes( 12 ) );
	}

	/**
	 * Maybe send failure notification.
	 *
	 * @param object $webhook      Webhook object.
	 * @param int    $delivery_id  Delivery ID.
	 * @param string $error        Error message.
	 */
	private function maybe_send_failure_notification( $webhook, int $delivery_id, string $error ): void {
		$settings = $this->settings;

		if ( empty( $settings['notify_on_failure'] ) ) {
			return;
		}

		// Check if failure threshold reached.
		if ( $webhook->failure_count < ( $settings['failure_threshold'] ?? 5 ) ) {
			return;
		}

		$email = $settings['failure_notification_email'] ?? get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: %s: webhook name */
			__( '[BookingX] Webhook "%s" is failing', 'bkx-webhooks-manager' ),
			$webhook->name
		);

		$message = sprintf(
			/* translators: 1: webhook name, 2: failure count, 3: error message, 4: admin URL */
			__(
				"The webhook \"%1\$s\" has failed %2\$d times.\n\nLast error: %3\$s\n\nView deliveries: %4\$s",
				'bkx-webhooks-manager'
			),
			$webhook->name,
			$webhook->failure_count,
			$error,
			admin_url( 'edit.php?post_type=bkx_booking&page=bkx-webhooks-manager&tab=deliveries&webhook=' . $webhook->id )
		);

		wp_mail( $email, $subject, $message );
	}
}
