<?php
/**
 * Site service for managing remote BKX sites.
 *
 * @package BookingX\BkxIntegration\Services
 */

namespace BookingX\BkxIntegration\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SiteService class.
 */
class SiteService {

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
		$this->table = $wpdb->prefix . 'bkx_remote_sites';
	}

	/**
	 * Get all sites.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = '1=1';
		$params = array();

		if ( $args['status'] ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY name";

		if ( $params ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	/**
	 * Get single site.
	 *
	 * @param int $id Site ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$site = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		if ( $site && $site->settings ) {
			$site->settings = json_decode( $site->settings, true );
		}

		return $site;
	}

	/**
	 * Get site by URL.
	 *
	 * @param string $url Site URL.
	 * @return object|null
	 */
	public function get_by_url( $url ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE url = %s",
				$url
			)
		);
	}

	/**
	 * Save site.
	 *
	 * @param int   $id   Site ID (0 for new).
	 * @param array $data Site data.
	 * @return int|\WP_Error Site ID on success.
	 */
	public function save( $id, $data ) {
		global $wpdb;

		// Normalize URL.
		$data['url'] = untrailingslashit( $data['url'] );

		// Check for duplicate URL.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE url = %s AND id != %d",
				$data['url'],
				$id
			)
		);

		if ( $existing ) {
			return new \WP_Error( 'duplicate_url', __( 'A site with this URL already exists.', 'bkx-bkx-integration' ) );
		}

		$insert_data = array(
			'name'              => $data['name'],
			'url'               => $data['url'],
			'api_key'           => $data['api_key'],
			'api_secret'        => $data['api_secret'],
			'direction'         => $data['direction'] ?? 'both',
			'status'            => $data['status'] ?? 'active',
			'sync_bookings'     => $data['sync_bookings'] ?? 1,
			'sync_availability' => $data['sync_availability'] ?? 1,
			'sync_customers'    => $data['sync_customers'] ?? 0,
			'sync_services'     => $data['sync_services'] ?? 0,
			'sync_staff'        => $data['sync_staff'] ?? 0,
		);

		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$insert_data['settings'] = wp_json_encode( $data['settings'] );
		}

		if ( $id ) {
			// Update.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->update(
				$this->table,
				$insert_data,
				array( 'id' => $id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' ),
				array( '%d' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Failed to update site.', 'bkx-bkx-integration' ) );
			}

			return $id;
		}

		// Insert.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$this->table,
			$insert_data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create site.', 'bkx-bkx-integration' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Delete site.
	 *
	 * @param int $id Site ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		// Delete related data.
		$wpdb->delete( $wpdb->prefix . 'bkx_remote_mappings', array( 'site_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_remote_logs', array( 'site_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_remote_queue', array( 'site_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_remote_conflicts', array( 'site_id' => $id ), array( '%d' ) );

		// Delete site.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Update site status.
	 *
	 * @param int    $id      Site ID.
	 * @param string $status  New status.
	 * @param string $error   Error message (optional).
	 */
	public function update_status( $id, $status, $error = '' ) {
		global $wpdb;

		$data = array(
			'status'     => $status,
			'last_error' => $error,
		);

		if ( 'active' === $status ) {
			$data['last_error'] = '';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->table,
			$data,
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update last sync time.
	 *
	 * @param int $id Site ID.
	 */
	public function update_last_sync( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->table,
			array( 'last_sync' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Count sites.
	 *
	 * @param string $status Optional status filter.
	 * @return int
	 */
	public function count( $status = '' ) {
		global $wpdb;

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
					$status
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Get sites for sync direction.
	 *
	 * @param string $direction Direction ('push', 'pull', 'both').
	 * @return array
	 */
	public function get_for_direction( $direction ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = 'active' AND (direction = %s OR direction = 'both')",
				$direction
			)
		);
	}
}
