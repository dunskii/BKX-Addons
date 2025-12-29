<?php
/**
 * Queue processor service for batch sync operations.
 *
 * @package BookingX\HubSpot
 */

namespace BookingX\HubSpot\Services;

defined( 'ABSPATH' ) || exit;

/**
 * QueueProcessor class.
 */
class QueueProcessor {

	/**
	 * HubSpot API instance.
	 *
	 * @var HubSpotApi
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
	 * @param HubSpotApi $api      HubSpot API instance.
	 * @param array      $settings Plugin settings.
	 */
	public function __construct( HubSpotApi $api, array $settings ) {
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
		$table = $wpdb->prefix . 'bkx_hs_queue';

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
				return new \WP_Error( 'unknown_operation', __( 'Unknown queue operation', 'bkx-hubspot' ) );
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
			return new \WP_Error( 'invalid_type', __( 'Unsupported object type', 'bkx-hubspot' ) );
		}

		$booking_id = $item->wp_object_id;

		// Sync contact if enabled.
		if ( ! empty( $this->settings['sync_contacts'] ) ) {
			$contact_sync = new ContactSync( $this->api, $this->settings );
			$contact_sync->sync_from_booking( $booking_id );
		}

		// Create deal if enabled.
		if ( ! empty( $this->settings['create_deals'] ) ) {
			$deal_sync = new DealSync( $this->api, $this->settings );
			$result    = $deal_sync->create_from_booking( $booking_id );

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
			return new \WP_Error( 'invalid_type', __( 'Unsupported object type', 'bkx-hubspot' ) );
		}

		$booking_id = $item->wp_object_id;

		// Update contact if enabled.
		if ( ! empty( $this->settings['sync_contacts'] ) ) {
			$contact_sync = new ContactSync( $this->api, $this->settings );
			$contact_sync->sync_from_booking( $booking_id );
		}

		// Update deal if enabled.
		if ( ! empty( $this->settings['create_deals'] ) ) {
			$deal_sync = new DealSync( $this->api, $this->settings );
			$result    = $deal_sync->update_from_booking( $booking_id );

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
			return new \WP_Error( 'invalid_type', __( 'Unsupported object type', 'bkx-hubspot' ) );
		}

		$booking_id = $item->wp_object_id;
		$new_status = get_post_status( $booking_id );

		// Update deal stage if enabled.
		if ( ! empty( $this->settings['create_deals'] ) ) {
			$deal_sync = new DealSync( $this->api, $this->settings );
			$result    = $deal_sync->update_stage_from_status( $booking_id, $new_status );

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
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_mappings';

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
		$table = $wpdb->prefix . 'bkx_hs_queue';

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
		$table = $wpdb->prefix . 'bkx_hs_queue';

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
		$table = $wpdb->prefix . 'bkx_hs_queue';

		$status = $item->attempts >= $item->max_attempts ? 'failed' : 'pending';

		$scheduled_at = current_time( 'mysql' );
		if ( 'pending' === $status ) {
			$delay        = pow( 2, $item->attempts ) * 60;
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
		$table = $wpdb->prefix . 'bkx_hs_queue';

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
}
