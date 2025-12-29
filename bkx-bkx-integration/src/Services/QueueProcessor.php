<?php
/**
 * Queue processor service.
 *
 * @package BookingX\BkxIntegration\Services
 */

namespace BookingX\BkxIntegration\Services;

defined( 'ABSPATH' ) || exit;

/**
 * QueueProcessor class.
 */
class QueueProcessor {

	/**
	 * API client.
	 *
	 * @var ApiClient
	 */
	private $api;

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
		$this->api   = new ApiClient();
		$this->table = $wpdb->prefix . 'bkx_remote_queue';
	}

	/**
	 * Process pending queue items.
	 *
	 * @param int $limit Max items to process.
	 * @return int Number of items processed.
	 */
	public function process( $limit = 50 ) {
		global $wpdb;

		// Get pending items ordered by priority and scheduled time.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE status = 'pending'
				AND scheduled_at <= NOW()
				AND attempts < max_attempts
				ORDER BY priority ASC, scheduled_at ASC
				LIMIT %d",
				$limit
			)
		);

		$processed = 0;

		foreach ( $items as $item ) {
			$this->process_item( $item );
			++$processed;
		}

		// Cleanup completed items older than 24 hours.
		$this->cleanup();

		return $processed;
	}

	/**
	 * Process single queue item.
	 *
	 * @param object $item Queue item.
	 */
	private function process_item( $item ) {
		global $wpdb;

		// Mark as processing.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->table,
			array(
				'status'   => 'processing',
				'attempts' => $item->attempts + 1,
			),
			array( 'id' => $item->id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		$payload = json_decode( $item->payload, true );
		$result  = null;

		switch ( $item->object_type ) {
			case 'booking':
				$result = $this->process_booking( $item->site_id, $item->action, $payload );
				break;

			case 'availability':
				$result = $this->process_availability( $item->site_id, $payload );
				break;

			case 'customer':
				$result = $this->process_customer( $item->site_id, $item->action, $payload );
				break;
		}

		if ( is_wp_error( $result ) ) {
			$this->mark_failed( $item, $result->get_error_message() );
		} else {
			$this->mark_completed( $item );
		}
	}

	/**
	 * Process booking queue item.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $action  Action.
	 * @param array  $data    Booking data.
	 * @return array|\WP_Error
	 */
	private function process_booking( $site_id, $action, $data ) {
		return $this->api->send_booking( $site_id, $data, $action );
	}

	/**
	 * Process availability queue item.
	 *
	 * @param int   $site_id Site ID.
	 * @param array $data    Availability data.
	 * @return array|\WP_Error
	 */
	private function process_availability( $site_id, $data ) {
		return $this->api->send_availability( $site_id, $data );
	}

	/**
	 * Process customer queue item.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $action  Action.
	 * @param array  $data    Customer data.
	 * @return array|\WP_Error
	 */
	private function process_customer( $site_id, $action, $data ) {
		return $this->api->send_customer( $site_id, $data, $action );
	}

	/**
	 * Mark item as completed.
	 *
	 * @param object $item Queue item.
	 */
	private function mark_completed( $item ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->table,
			array(
				'status'       => 'completed',
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $item->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark item as failed.
	 *
	 * @param object $item    Queue item.
	 * @param string $message Error message.
	 */
	private function mark_failed( $item, $message ) {
		global $wpdb;

		$new_status = ( $item->attempts + 1 >= $item->max_attempts ) ? 'failed' : 'pending';

		// Calculate next retry time with exponential backoff.
		$delay = pow( 2, $item->attempts ) * 60; // 2, 4, 8 minutes, etc.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->table,
			array(
				'status'        => $new_status,
				'error_message' => $message,
				'scheduled_at'  => gmdate( 'Y-m-d H:i:s', time() + $delay ),
			),
			array( 'id' => $item->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Cleanup old completed/failed items.
	 */
	private function cleanup() {
		global $wpdb;

		// Delete completed items older than 24 hours.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"DELETE FROM {$this->table}
			WHERE status = 'completed'
			AND processed_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
		);

		// Delete failed items older than 7 days.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"DELETE FROM {$this->table}
			WHERE status = 'failed'
			AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
		);
	}

	/**
	 * Get queue statistics.
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$stats = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status",
			OBJECT_K
		);

		return array(
			'pending'    => $stats['pending']->count ?? 0,
			'processing' => $stats['processing']->count ?? 0,
			'completed'  => $stats['completed']->count ?? 0,
			'failed'     => $stats['failed']->count ?? 0,
		);
	}

	/**
	 * Retry failed items.
	 *
	 * @param int $site_id Optional site ID filter.
	 * @return int Number of items reset.
	 */
	public function retry_failed( $site_id = 0 ) {
		global $wpdb;

		$where = 'status = %s';
		$params = array( 'failed' );

		if ( $site_id ) {
			$where   .= ' AND site_id = %d';
			$params[] = $site_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table}
				SET status = 'pending', attempts = 0, error_message = NULL, scheduled_at = NOW()
				WHERE {$where}",
				$params
			)
		);
	}

	/**
	 * Get failed items.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_failed( $limit = 50 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = 'failed' ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}
}
