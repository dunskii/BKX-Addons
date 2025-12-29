<?php
/**
 * Queue processor service for batch sync operations.
 *
 * @package BookingX\Salesforce
 */

namespace BookingX\Salesforce\Services;

defined( 'ABSPATH' ) || exit;

/**
 * QueueProcessor class.
 */
class QueueProcessor {

	/**
	 * Salesforce API instance.
	 *
	 * @var SalesforceApi
	 */
	private $api;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Batch size.
	 *
	 * @var int
	 */
	private $batch_size = 50;

	/**
	 * Constructor.
	 *
	 * @param SalesforceApi $api      Salesforce API instance.
	 * @param array         $settings Plugin settings.
	 */
	public function __construct( SalesforceApi $api, array $settings ) {
		$this->api      = $api;
		$this->settings = $settings;
	}

	/**
	 * Process the sync queue.
	 *
	 * @param int $limit Maximum items to process.
	 * @return array Results.
	 */
	public function process( $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_queue';

		// Get pending items.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE status = 'pending'
				AND scheduled_at <= %s
				AND attempts < max_attempts
				ORDER BY priority ASC, scheduled_at ASC
				LIMIT %d",
				current_time( 'mysql' ),
				$limit
			)
		);

		$results = array(
			'processed' => 0,
			'success'   => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		foreach ( $items as $item ) {
			$result = $this->process_item( $item );

			$results['processed']++;

			if ( is_wp_error( $result ) ) {
				$results['failed']++;
				$results['errors'][] = array(
					'id'      => $item->id,
					'message' => $result->get_error_message(),
				);

				$this->mark_failed( $item, $result->get_error_message() );
			} else {
				$results['success']++;
				$this->mark_completed( $item );
			}
		}

		// Clean up old completed items.
		$this->cleanup_old_items();

		return $results;
	}

	/**
	 * Process a single queue item.
	 *
	 * @param object $item Queue item.
	 * @return bool|\WP_Error
	 */
	private function process_item( $item ) {
		// Mark as processing.
		$this->mark_processing( $item );

		switch ( $item->operation ) {
			case 'create':
				return $this->handle_create( $item );

			case 'update':
				return $this->handle_update( $item );

			case 'update_status':
				return $this->handle_status_update( $item );

			case 'delete':
				return $this->handle_delete( $item );

			default:
				return new \WP_Error( 'unknown_operation', __( 'Unknown queue operation', 'bkx-salesforce' ) );
		}
	}

	/**
	 * Handle create operation.
	 *
	 * @param object $item Queue item.
	 * @return bool|\WP_Error
	 */
	private function handle_create( $item ) {
		if ( 'booking' !== $item->wp_object_type ) {
			return new \WP_Error( 'invalid_type', __( 'Unsupported object type', 'bkx-salesforce' ) );
		}

		$booking_id = $item->wp_object_id;

		// Sync contact if enabled.
		if ( ! empty( $this->settings['sync_contacts'] ) ) {
			$contact_sync = new ContactSync( $this->api, $this->settings );
			$contact_sync->sync_from_booking( $booking_id );
		}

		// Sync lead if enabled.
		if ( ! empty( $this->settings['sync_leads'] ) ) {
			$lead_sync = new LeadSync( $this->api, $this->settings );
			$lead_sync->sync_from_booking( $booking_id );
		}

		// Create opportunity if enabled.
		if ( ! empty( $this->settings['create_opportunities'] ) ) {
			$opp_sync = new OpportunitySync( $this->api, $this->settings );
			$result   = $opp_sync->create_from_booking( $booking_id );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Handle update operation.
	 *
	 * @param object $item Queue item.
	 * @return bool|\WP_Error
	 */
	private function handle_update( $item ) {
		if ( 'booking' !== $item->wp_object_type ) {
			return new \WP_Error( 'invalid_type', __( 'Unsupported object type', 'bkx-salesforce' ) );
		}

		$booking_id = $item->wp_object_id;

		// Update contact if enabled.
		if ( ! empty( $this->settings['sync_contacts'] ) ) {
			$contact_sync = new ContactSync( $this->api, $this->settings );
			$contact_sync->sync_from_booking( $booking_id );
		}

		// Update opportunity if enabled.
		if ( ! empty( $this->settings['create_opportunities'] ) ) {
			$opp_sync = new OpportunitySync( $this->api, $this->settings );
			$result   = $opp_sync->update_from_booking( $booking_id );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Handle status update operation.
	 *
	 * @param object $item Queue item.
	 * @return bool|\WP_Error
	 */
	private function handle_status_update( $item ) {
		if ( 'booking' !== $item->wp_object_type ) {
			return new \WP_Error( 'invalid_type', __( 'Unsupported object type', 'bkx-salesforce' ) );
		}

		$booking_id = $item->wp_object_id;
		$new_status = get_post_status( $booking_id );

		// Update lead status if enabled.
		if ( ! empty( $this->settings['sync_leads'] ) ) {
			$lead_sync = new LeadSync( $this->api, $this->settings );
			$lead_sync->update_lead_status( $booking_id, $new_status );
		}

		// Update opportunity stage if enabled.
		if ( ! empty( $this->settings['create_opportunities'] ) ) {
			$opp_sync = new OpportunitySync( $this->api, $this->settings );
			$result   = $opp_sync->update_stage_from_status( $booking_id, $new_status );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Handle delete operation.
	 *
	 * @param object $item Queue item.
	 * @return bool|\WP_Error
	 */
	private function handle_delete( $item ) {
		// Note: We typically don't delete Salesforce records when
		// WordPress records are deleted. Just remove the mapping.
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$table,
			array(
				'wp_object_type' => $item->wp_object_type,
				'wp_object_id'   => $item->wp_object_id,
			),
			array( '%s', '%d' )
		);

		return true;
	}

	/**
	 * Mark item as processing.
	 *
	 * @param object $item Queue item.
	 */
	private function mark_processing( $item ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array(
				'status'   => 'processing',
				'attempts' => $item->attempts + 1,
			),
			array( 'id' => $item->id ),
			array( '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Mark item as completed.
	 *
	 * @param object $item Queue item.
	 */
	private function mark_completed( $item ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
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
		$table = $wpdb->prefix . 'bkx_sf_queue';

		$status = $item->attempts >= $item->max_attempts ? 'failed' : 'pending';

		// If pending, schedule retry with exponential backoff.
		$scheduled_at = current_time( 'mysql' );
		if ( 'pending' === $status ) {
			$delay        = pow( 2, $item->attempts ) * 60; // 2, 4, 8 minutes.
			$scheduled_at = gmdate( 'Y-m-d H:i:s', time() + $delay );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array(
				'status'        => $status,
				'error_message' => $message,
				'scheduled_at'  => $scheduled_at,
			),
			array( 'id' => $item->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Clean up old completed/failed items.
	 */
	private function cleanup_old_items() {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_queue';

		// Delete items older than 7 days that are completed or failed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				WHERE status IN ('completed', 'failed')
				AND processed_at < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			)
		);
	}

	/**
	 * Get queue statistics.
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$stats = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
			FROM {$table}
			GROUP BY status"
		);

		$result = array(
			'pending'    => 0,
			'processing' => 0,
			'completed'  => 0,
			'failed'     => 0,
		);

		foreach ( $stats as $stat ) {
			$result[ $stat->status ] = (int) $stat->count;
		}

		return $result;
	}

	/**
	 * Clear the queue.
	 *
	 * @param string|null $status Status to clear (null for all).
	 * @return int Number of items deleted.
	 */
	public function clear( $status = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_queue';

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $wpdb->delete(
				$table,
				array( 'status' => $status ),
				array( '%s' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Retry failed items.
	 *
	 * @return int Number of items reset.
	 */
	public function retry_failed() {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->update(
			$table,
			array(
				'status'        => 'pending',
				'attempts'      => 0,
				'error_message' => null,
				'scheduled_at'  => current_time( 'mysql' ),
			),
			array( 'status' => 'failed' ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%s' )
		);
	}
}
