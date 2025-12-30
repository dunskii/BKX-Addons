<?php
/**
 * Webhook Manager Service.
 *
 * @package BookingX\WebhooksManager
 */

namespace BookingX\WebhooksManager\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookManager
 *
 * Handles CRUD operations for webhooks.
 */
class WebhookManager {

	/**
	 * Webhooks table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Subscriptions table name.
	 *
	 * @var string
	 */
	private $subscriptions_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table               = $wpdb->prefix . 'bkx_webhooks';
		$this->subscriptions_table = $wpdb->prefix . 'bkx_webhook_subscriptions';
	}

	/**
	 * Create a new webhook.
	 *
	 * @param array $data Webhook data.
	 * @return int|false Webhook ID or false on failure.
	 */
	public function create( array $data ) {
		global $wpdb;

		$defaults = array(
			'name'              => '',
			'url'               => '',
			'events'            => array(),
			'secret'            => $this->generate_secret(),
			'headers'           => array(),
			'payload_format'    => 'json',
			'http_method'       => 'POST',
			'content_type'      => 'application/json',
			'timeout'           => 30,
			'retry_count'       => 3,
			'retry_delay'       => 60,
			'retry_multiplier'  => 2,
			'verify_ssl'        => true,
			'active_start_time' => null,
			'active_end_time'   => null,
			'active_days'       => null,
			'conditions'        => array(),
			'status'            => 'active',
			'created_by'        => get_current_user_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate required fields.
		if ( empty( $data['name'] ) || empty( $data['url'] ) || empty( $data['events'] ) ) {
			return false;
		}

		// Validate URL.
		if ( ! filter_var( $data['url'], FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Prepare data for insertion.
		$insert_data = array(
			'name'              => sanitize_text_field( $data['name'] ),
			'url'               => esc_url_raw( $data['url'] ),
			'events'            => wp_json_encode( $data['events'] ),
			'secret'            => $data['secret'],
			'headers'           => wp_json_encode( $data['headers'] ),
			'payload_format'    => sanitize_text_field( $data['payload_format'] ),
			'http_method'       => strtoupper( sanitize_text_field( $data['http_method'] ) ),
			'content_type'      => sanitize_text_field( $data['content_type'] ),
			'timeout'           => absint( $data['timeout'] ),
			'retry_count'       => absint( $data['retry_count'] ),
			'retry_delay'       => absint( $data['retry_delay'] ),
			'retry_multiplier'  => floatval( $data['retry_multiplier'] ),
			'verify_ssl'        => $data['verify_ssl'] ? 1 : 0,
			'active_start_time' => $data['active_start_time'],
			'active_end_time'   => $data['active_end_time'],
			'active_days'       => $data['active_days'] ? wp_json_encode( $data['active_days'] ) : null,
			'conditions'        => wp_json_encode( $data['conditions'] ),
			'status'            => sanitize_text_field( $data['status'] ),
			'created_by'        => absint( $data['created_by'] ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			$insert_data,
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%d', '%d', '%d', '%f', '%d', '%s', '%s', '%s',
				'%s', '%s', '%d',
			)
		);

		if ( ! $result ) {
			return false;
		}

		$webhook_id = $wpdb->insert_id;

		// Create event subscriptions.
		foreach ( $data['events'] as $event ) {
			$this->add_subscription( $webhook_id, $event );
		}

		/**
		 * Fires after a webhook is created.
		 *
		 * @param int   $webhook_id The webhook ID.
		 * @param array $data       The webhook data.
		 */
		do_action( 'bkx_webhook_created', $webhook_id, $data );

		return $webhook_id;
	}

	/**
	 * Update a webhook.
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param array $data       Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $webhook_id, array $data ): bool {
		global $wpdb;

		$webhook = $this->get( $webhook_id );
		if ( ! $webhook ) {
			return false;
		}

		$update_data   = array();
		$update_format = array();

		// Map of field => format.
		$field_formats = array(
			'name'              => '%s',
			'url'               => '%s',
			'events'            => '%s',
			'headers'           => '%s',
			'payload_format'    => '%s',
			'http_method'       => '%s',
			'content_type'      => '%s',
			'timeout'           => '%d',
			'retry_count'       => '%d',
			'retry_delay'       => '%d',
			'retry_multiplier'  => '%f',
			'verify_ssl'        => '%d',
			'active_start_time' => '%s',
			'active_end_time'   => '%s',
			'active_days'       => '%s',
			'conditions'        => '%s',
			'status'            => '%s',
		);

		foreach ( $field_formats as $field => $format ) {
			if ( ! isset( $data[ $field ] ) ) {
				continue;
			}

			$value = $data[ $field ];

			// Handle special fields.
			switch ( $field ) {
				case 'name':
					$value = sanitize_text_field( $value );
					break;
				case 'url':
					$value = esc_url_raw( $value );
					break;
				case 'events':
				case 'headers':
				case 'conditions':
					$value = wp_json_encode( $value );
					break;
				case 'active_days':
					$value = $value ? wp_json_encode( $value ) : null;
					break;
				case 'http_method':
					$value = strtoupper( sanitize_text_field( $value ) );
					break;
				case 'verify_ssl':
					$value = $value ? 1 : 0;
					break;
				case 'timeout':
				case 'retry_count':
				case 'retry_delay':
					$value = absint( $value );
					break;
				case 'retry_multiplier':
					$value = floatval( $value );
					break;
				default:
					$value = sanitize_text_field( $value );
			}

			$update_data[ $field ] = $value;
			$update_format[]       = $format;
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $webhook_id ),
			$update_format,
			array( '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		// Update event subscriptions if events changed.
		if ( isset( $data['events'] ) ) {
			$this->sync_subscriptions( $webhook_id, $data['events'] );
		}

		/**
		 * Fires after a webhook is updated.
		 *
		 * @param int   $webhook_id The webhook ID.
		 * @param array $data       The updated data.
		 */
		do_action( 'bkx_webhook_updated', $webhook_id, $data );

		return true;
	}

	/**
	 * Delete a webhook.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $webhook_id ): bool {
		global $wpdb;

		// Delete subscriptions first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$this->subscriptions_table,
			array( 'webhook_id' => $webhook_id ),
			array( '%d' )
		);

		// Delete webhook.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->table,
			array( 'id' => $webhook_id ),
			array( '%d' )
		);

		if ( $result ) {
			/**
			 * Fires after a webhook is deleted.
			 *
			 * @param int $webhook_id The webhook ID.
			 */
			do_action( 'bkx_webhook_deleted', $webhook_id );
		}

		return (bool) $result;
	}

	/**
	 * Get a webhook by ID.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return object|null Webhook object or null.
	 */
	public function get( int $webhook_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$webhook = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$this->table,
				$webhook_id
			)
		);

		if ( $webhook ) {
			$webhook = $this->format_webhook( $webhook );
		}

		return $webhook;
	}

	/**
	 * Get webhooks with optional filtering.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of webhooks.
	 */
	public function get_all( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'     => '',
			'event'      => '',
			'search'     => '',
			'created_by' => 0,
			'orderby'    => 'created_at',
			'order'      => 'DESC',
			'limit'      => 20,
			'offset'     => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'w.status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['event'] ) ) {
			$where[]  = 'w.events LIKE %s';
			$values[] = '%"' . $wpdb->esc_like( $args['event'] ) . '"%';
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(w.name LIKE %s OR w.url LIKE %s)';
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
		}

		if ( ! empty( $args['created_by'] ) ) {
			$where[]  = 'w.created_by = %d';
			$values[] = $args['created_by'];
		}

		$allowed_orderby = array( 'id', 'name', 'status', 'created_at', 'last_triggered_at', 'success_count', 'failure_count' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$where_clause = implode( ' AND ', $where );

		// Build query - use literal for table name since prepare doesn't handle it well in complex queries.
		$query = "SELECT w.* FROM {$this->table} w WHERE {$where_clause} ORDER BY w.{$orderby} {$order}";

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
		$webhooks = $wpdb->get_results( $query );

		return array_map( array( $this, 'format_webhook' ), $webhooks );
	}

	/**
	 * Get webhooks count.
	 *
	 * @param string $status Optional status filter.
	 * @return int Count.
	 */
	public function get_count( string $status = '' ): int {
		global $wpdb;

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE status = %s",
					$this->table,
					$status
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i",
				$this->table
			)
		);
	}

	/**
	 * Get webhooks subscribed to a specific event.
	 *
	 * @param string $event Event type.
	 * @return array Array of webhooks.
	 */
	public function get_by_event( string $event ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$webhooks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT w.* FROM %i w
				INNER JOIN %i s ON w.id = s.webhook_id
				WHERE s.event_type = %s
				AND w.status = 'active'
				AND s.status = 'active'
				ORDER BY s.priority ASC",
				$this->table,
				$this->subscriptions_table,
				$event
			)
		);

		return array_map( array( $this, 'format_webhook' ), $webhooks );
	}

	/**
	 * Toggle webhook status.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return bool True on success, false on failure.
	 */
	public function toggle( int $webhook_id ): bool {
		$webhook = $this->get( $webhook_id );
		if ( ! $webhook ) {
			return false;
		}

		$new_status = 'active' === $webhook->status ? 'paused' : 'active';

		return $this->update( $webhook_id, array( 'status' => $new_status ) );
	}

	/**
	 * Update webhook statistics.
	 *
	 * @param int  $webhook_id    Webhook ID.
	 * @param bool $success       Whether delivery was successful.
	 * @param int  $response_code HTTP response code.
	 * @return bool True on success, false on failure.
	 */
	public function update_stats( int $webhook_id, bool $success, int $response_code = 0 ): bool {
		global $wpdb;

		$update_data = array(
			'last_triggered_at'  => current_time( 'mysql' ),
			'last_response_code' => $response_code,
		);

		if ( $success ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (bool) $wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET
						last_triggered_at = %s,
						last_response_code = %d,
						success_count = success_count + 1
					WHERE id = %d",
					$this->table,
					$update_data['last_triggered_at'],
					$response_code,
					$webhook_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (bool) $wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET
						last_triggered_at = %s,
						last_response_code = %d,
						failure_count = failure_count + 1
					WHERE id = %d",
					$this->table,
					$update_data['last_triggered_at'],
					$response_code,
					$webhook_id
				)
			);
		}
	}

	/**
	 * Add an event subscription.
	 *
	 * @param int    $webhook_id Webhook ID.
	 * @param string $event_type Event type.
	 * @param array  $options    Additional options.
	 * @return int|false Subscription ID or false.
	 */
	public function add_subscription( int $webhook_id, string $event_type, array $options = array() ) {
		global $wpdb;

		$data = array(
			'webhook_id'         => $webhook_id,
			'event_type'         => $event_type,
			'filter_conditions'  => wp_json_encode( $options['filter_conditions'] ?? array() ),
			'transform_template' => $options['transform_template'] ?? null,
			'priority'           => $options['priority'] ?? 10,
			'status'             => $options['status'] ?? 'active',
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->subscriptions_table,
			$data,
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Sync event subscriptions with events list.
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param array $events     List of events.
	 */
	private function sync_subscriptions( int $webhook_id, array $events ): void {
		global $wpdb;

		// Get current subscriptions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT event_type FROM %i WHERE webhook_id = %d",
				$this->subscriptions_table,
				$webhook_id
			)
		);

		// Add new subscriptions.
		$to_add = array_diff( $events, $current );
		foreach ( $to_add as $event ) {
			$this->add_subscription( $webhook_id, $event );
		}

		// Remove old subscriptions.
		$to_remove = array_diff( $current, $events );
		foreach ( $to_remove as $event ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$this->subscriptions_table,
				array(
					'webhook_id'  => $webhook_id,
					'event_type'  => $event,
				),
				array( '%d', '%s' )
			);
		}
	}

	/**
	 * Generate a webhook secret.
	 *
	 * @return string Generated secret.
	 */
	private function generate_secret(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Regenerate webhook secret.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return string|false New secret or false on failure.
	 */
	public function regenerate_secret( int $webhook_id ) {
		global $wpdb;

		$new_secret = $this->generate_secret();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array( 'secret' => $new_secret ),
			array( 'id' => $webhook_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $result ? $new_secret : false;
	}

	/**
	 * Format webhook object.
	 *
	 * @param object $webhook Raw webhook object.
	 * @return object Formatted webhook.
	 */
	private function format_webhook( $webhook ) {
		$webhook->events      = json_decode( $webhook->events, true ) ?: array();
		$webhook->headers     = json_decode( $webhook->headers, true ) ?: array();
		$webhook->conditions  = json_decode( $webhook->conditions, true ) ?: array();
		$webhook->active_days = $webhook->active_days ? json_decode( $webhook->active_days, true ) : null;
		$webhook->verify_ssl  = (bool) $webhook->verify_ssl;

		return $webhook;
	}

	/**
	 * Check if webhook is within active time window.
	 *
	 * @param object $webhook Webhook object.
	 * @return bool True if within active window.
	 */
	public function is_within_active_window( $webhook ): bool {
		// Check status.
		if ( 'active' !== $webhook->status ) {
			return false;
		}

		$current_time = current_time( 'H:i:s' );
		$current_day  = strtolower( current_time( 'l' ) );

		// Check active days.
		if ( ! empty( $webhook->active_days ) && ! in_array( $current_day, $webhook->active_days, true ) ) {
			return false;
		}

		// Check time window.
		if ( $webhook->active_start_time && $webhook->active_end_time ) {
			if ( $current_time < $webhook->active_start_time || $current_time > $webhook->active_end_time ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Evaluate webhook conditions.
	 *
	 * @param object $webhook Webhook object.
	 * @param array  $payload Event payload.
	 * @return bool True if conditions are met.
	 */
	public function evaluate_conditions( $webhook, array $payload ): bool {
		if ( empty( $webhook->conditions ) ) {
			return true;
		}

		foreach ( $webhook->conditions as $condition ) {
			$field    = $condition['field'] ?? '';
			$operator = $condition['operator'] ?? 'equals';
			$value    = $condition['value'] ?? '';

			// Get the field value from payload using dot notation.
			$field_value = $this->get_nested_value( $payload, $field );

			if ( ! $this->compare_values( $field_value, $operator, $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get nested value from array using dot notation.
	 *
	 * @param array  $array Array to search.
	 * @param string $key   Dot-notation key.
	 * @return mixed Value or null.
	 */
	private function get_nested_value( array $array, string $key ) {
		$keys = explode( '.', $key );

		foreach ( $keys as $key_part ) {
			if ( ! is_array( $array ) || ! isset( $array[ $key_part ] ) ) {
				return null;
			}
			$array = $array[ $key_part ];
		}

		return $array;
	}

	/**
	 * Compare values based on operator.
	 *
	 * @param mixed  $field_value Field value.
	 * @param string $operator    Comparison operator.
	 * @param mixed  $value       Value to compare.
	 * @return bool Comparison result.
	 */
	private function compare_values( $field_value, string $operator, $value ): bool {
		switch ( $operator ) {
			case 'equals':
				return $field_value == $value; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			case 'not_equals':
				return $field_value != $value; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			case 'contains':
				return is_string( $field_value ) && strpos( $field_value, $value ) !== false;
			case 'not_contains':
				return is_string( $field_value ) && strpos( $field_value, $value ) === false;
			case 'greater_than':
				return $field_value > $value;
			case 'less_than':
				return $field_value < $value;
			case 'in':
				$values = is_array( $value ) ? $value : explode( ',', $value );
				return in_array( $field_value, $values, true );
			case 'not_in':
				$values = is_array( $value ) ? $value : explode( ',', $value );
				return ! in_array( $field_value, $values, true );
			case 'is_empty':
				return empty( $field_value );
			case 'is_not_empty':
				return ! empty( $field_value );
			default:
				return true;
		}
	}
}
