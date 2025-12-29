<?php
/**
 * Booking sync service.
 *
 * @package BookingX\BkxIntegration\Services
 */

namespace BookingX\BkxIntegration\Services;

defined( 'ABSPATH' ) || exit;

/**
 * BookingSync class.
 */
class BookingSync {

	/**
	 * API client.
	 *
	 * @var ApiClient
	 */
	private $api;

	/**
	 * Site service.
	 *
	 * @var SiteService
	 */
	private $sites;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api   = new ApiClient();
		$this->sites = new SiteService();
	}

	/**
	 * Queue outgoing booking sync.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $action     Action (create, update, delete, status_change).
	 */
	public function queue_outgoing( $booking_id, $action ) {
		global $wpdb;

		// Get active sites that sync bookings.
		$sites = $this->sites->get_all( array( 'status' => 'active' ) );

		foreach ( $sites as $site ) {
			if ( ! $site->sync_bookings ) {
				continue;
			}

			// Check direction.
			if ( 'pull' === $site->direction ) {
				continue;
			}

			// Check if already queued.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}bkx_remote_queue
					WHERE site_id = %d AND object_type = 'booking' AND object_id = %d AND status = 'pending'",
					$site->id,
					$booking_id
				)
			);

			if ( $existing ) {
				// Update existing queue item.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update(
					$wpdb->prefix . 'bkx_remote_queue',
					array(
						'action'  => $action,
						'payload' => wp_json_encode( $this->get_booking_data( $booking_id ) ),
					),
					array( 'id' => $existing ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			} else {
				// Insert new queue item.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->insert(
					$wpdb->prefix . 'bkx_remote_queue',
					array(
						'site_id'     => $site->id,
						'action'      => $action,
						'object_type' => 'booking',
						'object_id'   => $booking_id,
						'payload'     => wp_json_encode( $this->get_booking_data( $booking_id ) ),
						'priority'    => 'delete' === $action ? 5 : 10,
					),
					array( '%d', '%s', '%s', '%d', '%s', '%d' )
				);
			}
		}
	}

	/**
	 * Get booking data for sync.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	public function get_booking_data( $booking_id ) {
		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array();
		}

		$meta = get_post_meta( $booking_id );

		return array(
			'local_id'        => $booking_id,
			'status'          => $booking->post_status,
			'date'            => $meta['booking_date'][0] ?? '',
			'time'            => $meta['booking_time'][0] ?? '',
			'service_id'      => $meta['booking_multi_base'][0] ?? '',
			'staff_id'        => $meta['booking_multi_seat'][0] ?? '',
			'customer_email'  => $meta['customer_email'][0] ?? '',
			'customer_name'   => $meta['customer_primary_name'][0] ?? '',
			'customer_phone'  => $meta['customer_phone'][0] ?? '',
			'total_price'     => $meta['booking_total_with_currency'][0] ?? '',
			'notes'           => $meta['booking_notes'][0] ?? '',
			'source_site'     => home_url(),
			'created_at'      => $booking->post_date,
			'updated_at'      => $booking->post_modified,
			'hash'            => $this->generate_hash( $booking_id ),
		);
	}

	/**
	 * Generate hash for booking data.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	private function generate_hash( $booking_id ) {
		$data = get_post_meta( $booking_id );
		unset( $data['_edit_lock'], $data['_edit_last'] );
		return md5( wp_json_encode( $data ) );
	}

	/**
	 * Handle incoming booking sync.
	 *
	 * @param array  $data   Booking data.
	 * @param string $method HTTP method.
	 * @return int|\WP_Error Booking ID or error.
	 */
	public function handle_incoming( $data, $method ) {
		$remote_id   = $data['local_id'] ?? 0;
		$source_site = $data['source_site'] ?? '';

		if ( ! $remote_id || ! $source_site ) {
			return new \WP_Error( 'missing_data', __( 'Missing required data.', 'bkx-bkx-integration' ) );
		}

		// Get site by URL.
		$site = $this->sites->get_by_url( $source_site );

		if ( ! $site ) {
			return new \WP_Error( 'unknown_site', __( 'Unknown source site.', 'bkx-bkx-integration' ) );
		}

		// Check if we should process incoming bookings.
		if ( 'push' === $site->direction ) {
			return new \WP_Error( 'direction_mismatch', __( 'Site not configured for incoming sync.', 'bkx-bkx-integration' ) );
		}

		// Find existing local booking.
		$local_id = $this->get_local_id( $site->id, $remote_id );

		switch ( $method ) {
			case 'DELETE':
				return $this->delete_booking( $local_id );

			case 'PUT':
				return $this->update_booking( $local_id, $data, $site->id, $remote_id );

			default:
				return $this->create_booking( $data, $site->id, $remote_id );
		}
	}

	/**
	 * Create booking from remote data.
	 *
	 * @param array $data      Booking data.
	 * @param int   $site_id   Site ID.
	 * @param int   $remote_id Remote booking ID.
	 * @return int|\WP_Error
	 */
	private function create_booking( $data, $site_id, $remote_id ) {
		// Check for conflicts.
		$conflict = $this->check_conflict( $data );
		if ( $conflict ) {
			return $this->handle_conflict( $site_id, 'booking', 0, $remote_id, null, $data, 'double_booking' );
		}

		$booking_data = array(
			'post_type'   => 'bkx_booking',
			'post_status' => $data['status'] ?? 'bkx-pending',
			'post_title'  => sprintf(
				/* translators: %s: customer name */
				__( 'Booking - %s', 'bkx-bkx-integration' ),
				$data['customer_name'] ?? ''
			),
		);

		$booking_id = wp_insert_post( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		// Save meta.
		$this->save_booking_meta( $booking_id, $data );

		// Save mapping.
		$this->save_mapping( $site_id, 'booking', $booking_id, $remote_id, $data['hash'] ?? '' );

		return $booking_id;
	}

	/**
	 * Update booking from remote data.
	 *
	 * @param int   $local_id  Local booking ID.
	 * @param array $data      Booking data.
	 * @param int   $site_id   Site ID.
	 * @param int   $remote_id Remote booking ID.
	 * @return int|\WP_Error
	 */
	private function update_booking( $local_id, $data, $site_id, $remote_id ) {
		if ( ! $local_id ) {
			// Create if doesn't exist.
			return $this->create_booking( $data, $site_id, $remote_id );
		}

		// Check for local changes (conflict detection).
		$local_hash  = $this->get_mapping_hash( $site_id, 'booking', $local_id );
		$current_hash = $this->generate_hash( $local_id );

		if ( $local_hash && $local_hash !== $current_hash ) {
			// Local has changed since last sync - potential conflict.
			$local_data = $this->get_booking_data( $local_id );
			return $this->handle_conflict( $site_id, 'booking', $local_id, $remote_id, $local_data, $data, 'concurrent_update' );
		}

		// Update post.
		wp_update_post(
			array(
				'ID'          => $local_id,
				'post_status' => $data['status'] ?? 'bkx-pending',
			)
		);

		// Update meta.
		$this->save_booking_meta( $local_id, $data );

		// Update mapping hash.
		$this->update_mapping_hash( $site_id, 'booking', $local_id, $data['hash'] ?? '' );

		return $local_id;
	}

	/**
	 * Delete booking.
	 *
	 * @param int $local_id Local booking ID.
	 * @return bool|\WP_Error
	 */
	private function delete_booking( $local_id ) {
		if ( ! $local_id ) {
			return true; // Already doesn't exist.
		}

		wp_delete_post( $local_id, true );

		// Delete mapping.
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'bkx_remote_mappings',
			array(
				'object_type' => 'booking',
				'local_id'    => $local_id,
			),
			array( '%s', '%d' )
		);

		return true;
	}

	/**
	 * Save booking meta.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $data       Booking data.
	 */
	private function save_booking_meta( $booking_id, $data ) {
		$meta_mapping = array(
			'date'           => 'booking_date',
			'time'           => 'booking_time',
			'service_id'     => 'booking_multi_base',
			'staff_id'       => 'booking_multi_seat',
			'customer_email' => 'customer_email',
			'customer_name'  => 'customer_primary_name',
			'customer_phone' => 'customer_phone',
			'total_price'    => 'booking_total_with_currency',
			'notes'          => 'booking_notes',
		);

		foreach ( $meta_mapping as $data_key => $meta_key ) {
			if ( isset( $data[ $data_key ] ) ) {
				update_post_meta( $booking_id, $meta_key, $data[ $data_key ] );
			}
		}

		// Mark as synced from remote.
		update_post_meta( $booking_id, '_bkx_synced_from_remote', true );
		update_post_meta( $booking_id, '_bkx_source_site', $data['source_site'] ?? '' );
	}

	/**
	 * Check for booking conflicts.
	 *
	 * @param array $data Booking data.
	 * @return bool
	 */
	private function check_conflict( $data ) {
		global $wpdb;

		// Check for double booking (same date/time/staff).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'booking_time'
				INNER JOIN {$wpdb->postmeta} pm_staff ON p.ID = pm_staff.post_id AND pm_staff.meta_key = 'booking_multi_seat'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status NOT IN ('trash', 'bkx-cancelled')
				AND pm_date.meta_value = %s
				AND pm_time.meta_value = %s
				AND pm_staff.meta_value = %s
				LIMIT 1",
				$data['date'] ?? '',
				$data['time'] ?? '',
				$data['staff_id'] ?? ''
			)
		);

		return (bool) $existing;
	}

	/**
	 * Handle sync conflict.
	 *
	 * @param int    $site_id       Site ID.
	 * @param string $object_type   Object type.
	 * @param int    $local_id      Local ID.
	 * @param string $remote_id     Remote ID.
	 * @param array  $local_data    Local data.
	 * @param array  $remote_data   Remote data.
	 * @param string $conflict_type Conflict type.
	 * @return \WP_Error
	 */
	private function handle_conflict( $site_id, $object_type, $local_id, $remote_id, $local_data, $remote_data, $conflict_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_remote_conflicts',
			array(
				'site_id'       => $site_id,
				'object_type'   => $object_type,
				'local_id'      => $local_id,
				'remote_id'     => $remote_id,
				'local_data'    => wp_json_encode( $local_data ),
				'remote_data'   => wp_json_encode( $remote_data ),
				'conflict_type' => $conflict_type,
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return new \WP_Error(
			'conflict',
			sprintf(
				/* translators: %s: conflict type */
				__( 'Sync conflict detected: %s', 'bkx-bkx-integration' ),
				$conflict_type
			)
		);
	}

	/**
	 * Get local ID from mapping.
	 *
	 * @param int    $site_id     Site ID.
	 * @param string $remote_id   Remote ID.
	 * @return int
	 */
	private function get_local_id( $site_id, $remote_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$local_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT local_id FROM {$wpdb->prefix}bkx_remote_mappings
				WHERE site_id = %d AND object_type = 'booking' AND remote_id = %s",
				$site_id,
				$remote_id
			)
		);

		return absint( $local_id );
	}

	/**
	 * Save mapping.
	 *
	 * @param int    $site_id     Site ID.
	 * @param string $object_type Object type.
	 * @param int    $local_id    Local ID.
	 * @param string $remote_id   Remote ID.
	 * @param string $hash        Data hash.
	 */
	private function save_mapping( $site_id, $object_type, $local_id, $remote_id, $hash = '' ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$wpdb->prefix . 'bkx_remote_mappings',
			array(
				'site_id'     => $site_id,
				'object_type' => $object_type,
				'local_id'    => $local_id,
				'remote_id'   => $remote_id,
				'sync_hash'   => $hash,
				'last_synced' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get mapping hash.
	 *
	 * @param int    $site_id     Site ID.
	 * @param string $object_type Object type.
	 * @param int    $local_id    Local ID.
	 * @return string
	 */
	private function get_mapping_hash( $site_id, $object_type, $local_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sync_hash FROM {$wpdb->prefix}bkx_remote_mappings
				WHERE site_id = %d AND object_type = %s AND local_id = %d",
				$site_id,
				$object_type,
				$local_id
			)
		);
	}

	/**
	 * Update mapping hash.
	 *
	 * @param int    $site_id     Site ID.
	 * @param string $object_type Object type.
	 * @param int    $local_id    Local ID.
	 * @param string $hash        New hash.
	 */
	private function update_mapping_hash( $site_id, $object_type, $local_id, $hash ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->prefix . 'bkx_remote_mappings',
			array(
				'sync_hash'   => $hash,
				'last_synced' => current_time( 'mysql' ),
			),
			array(
				'site_id'     => $site_id,
				'object_type' => $object_type,
				'local_id'    => $local_id,
			),
			array( '%s', '%s' ),
			array( '%d', '%s', '%d' )
		);
	}

	/**
	 * Sync all bookings for a site.
	 *
	 * @param int $site_id Site ID.
	 * @return array Results.
	 */
	public function sync_site( $site_id ) {
		$site = $this->sites->get( $site_id );

		if ( ! $site || ! $site->sync_bookings ) {
			return array( 'synced' => 0, 'errors' => 0 );
		}

		$synced = 0;
		$errors = 0;

		// Get recent bookings (last 7 days).
		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => 100,
				'post_status'    => array( 'bkx-pending', 'bkx-ack', 'bkx-completed' ),
				'date_query'     => array(
					array(
						'after' => '7 days ago',
					),
				),
			)
		);

		foreach ( $bookings as $booking ) {
			$data   = $this->get_booking_data( $booking->ID );
			$result = $this->api->send_booking( $site_id, $data, 'create' );

			if ( is_wp_error( $result ) ) {
				++$errors;
			} else {
				++$synced;
			}
		}

		$this->sites->update_last_sync( $site_id );

		return array(
			'synced' => $synced,
			'errors' => $errors,
		);
	}
}
