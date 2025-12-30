<?php
/**
 * Endpoint Manager service.
 *
 * @package BookingX\APIBuilder\Services
 */

namespace BookingX\APIBuilder\Services;

defined( 'ABSPATH' ) || exit;

/**
 * EndpointManager class.
 */
class EndpointManager {

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
		$this->table = $wpdb->prefix . 'bkx_api_endpoints';
	}

	/**
	 * Get all endpoints.
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
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'search'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(name LIKE %s OR route LIKE %s)';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];

		$query = "SELECT * FROM {$this->table}
				  WHERE {$where_clause}
				  ORDER BY {$args['orderby']} {$args['order']}
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
	 * Get active endpoints.
	 *
	 * @return array
	 */
	public function get_active_endpoints() {
		return $this->get_all( array( 'status' => 'active', 'per_page' => 100 ) );
	}

	/**
	 * Get endpoint by ID.
	 *
	 * @param int $id Endpoint ID.
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
	 * Get endpoint by slug.
	 *
	 * @param string $slug Endpoint slug.
	 * @return array|null
	 */
	public function get_by_slug( $slug ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE slug = %s", $slug ),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Save endpoint.
	 *
	 * @param int   $id   Endpoint ID (0 for new).
	 * @param array $data Endpoint data.
	 * @return int|\WP_Error Endpoint ID or error.
	 */
	public function save( $id, $data ) {
		global $wpdb;

		// Validate required fields.
		if ( empty( $data['name'] ) || empty( $data['route'] ) ) {
			return new \WP_Error( 'missing_fields', __( 'Name and route are required', 'bkx-api-builder' ) );
		}

		// Generate slug if not provided.
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		// Check for duplicate slug.
		$existing = $this->get_by_slug( $data['slug'] );
		if ( $existing && ( ! $id || $existing['id'] != $id ) ) {
			$data['slug'] = $data['slug'] . '-' . time();
		}

		// Validate route format.
		if ( ! preg_match( '/^\/[a-zA-Z0-9\-_\/\{\}]+$/', $data['route'] ) ) {
			return new \WP_Error( 'invalid_route', __( 'Invalid route format. Must start with / and contain only letters, numbers, hyphens, underscores, and path parameters.', 'bkx-api-builder' ) );
		}

		// Validate JSON fields.
		$json_fields = array( 'handler_config', 'request_schema', 'response_schema', 'permissions' );
		foreach ( $json_fields as $field ) {
			if ( ! empty( $data[ $field ] ) && null === json_decode( $data[ $field ] ) ) {
				return new \WP_Error( 'invalid_json', sprintf( __( 'Invalid JSON in %s', 'bkx-api-builder' ), $field ) );
			}
		}

		$insert_data = array(
			'name'             => $data['name'],
			'slug'             => $data['slug'],
			'method'           => strtoupper( $data['method'] ?? 'GET' ),
			'namespace'        => $data['namespace'] ?? 'bkx-custom/v1',
			'route'            => $data['route'],
			'description'      => $data['description'] ?? '',
			'handler_type'     => $data['handler_type'] ?? 'query',
			'handler_config'   => $data['handler_config'] ?? '{}',
			'request_schema'   => $data['request_schema'] ?? '{}',
			'response_schema'  => $data['response_schema'] ?? '{}',
			'authentication'   => $data['authentication'] ?? 'none',
			'rate_limit'       => absint( $data['rate_limit'] ?? 0 ),
			'rate_limit_window' => absint( $data['rate_limit_window'] ?? 3600 ),
			'cache_enabled'    => ! empty( $data['cache_enabled'] ) ? 1 : 0,
			'cache_ttl'        => absint( $data['cache_ttl'] ?? 300 ),
			'permissions'      => $data['permissions'] ?? '{}',
			'status'           => $data['status'] ?? 'active',
			'version'          => $data['version'] ?? '1.0',
		);

		if ( $id ) {
			$insert_data['updated_at'] = current_time( 'mysql' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$this->table,
				$insert_data,
				array( 'id' => $id ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Failed to update endpoint', 'bkx-api-builder' ) );
			}

			return $id;
		} else {
			$insert_data['created_by'] = get_current_user_id();
			$insert_data['created_at'] = current_time( 'mysql' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->insert(
				$this->table,
				$insert_data,
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Failed to create endpoint', 'bkx-api-builder' ) );
			}

			return $wpdb->insert_id;
		}
	}

	/**
	 * Delete endpoint.
	 *
	 * @param int $id Endpoint ID.
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
	 * Update endpoint status.
	 *
	 * @param int    $id     Endpoint ID.
	 * @param string $status New status.
	 * @return bool
	 */
	public function update_status( $id, $status ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get total count.
	 *
	 * @param string $status Filter by status.
	 * @return int
	 */
	public function get_count( $status = '' ) {
		global $wpdb;

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE status = %s", $status )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Duplicate endpoint.
	 *
	 * @param int $id Endpoint ID to duplicate.
	 * @return int|\WP_Error New endpoint ID or error.
	 */
	public function duplicate( $id ) {
		$endpoint = $this->get( $id );

		if ( ! $endpoint ) {
			return new \WP_Error( 'not_found', __( 'Endpoint not found', 'bkx-api-builder' ) );
		}

		unset( $endpoint['id'], $endpoint['created_at'], $endpoint['updated_at'] );

		$endpoint['name']   = $endpoint['name'] . ' (Copy)';
		$endpoint['slug']   = '';
		$endpoint['status'] = 'draft';

		return $this->save( 0, $endpoint );
	}

	/**
	 * Export endpoints.
	 *
	 * @param array $ids Endpoint IDs to export (empty for all).
	 * @return array
	 */
	public function export( $ids = array() ) {
		global $wpdb;

		if ( ! empty( $ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$endpoints = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id IN ($placeholders)", $ids ),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$endpoints = $wpdb->get_results( "SELECT * FROM {$this->table}", ARRAY_A );
		}

		// Remove IDs and timestamps for export.
		foreach ( $endpoints as &$endpoint ) {
			unset( $endpoint['id'], $endpoint['created_by'], $endpoint['created_at'], $endpoint['updated_at'] );
		}

		return $endpoints;
	}

	/**
	 * Import endpoints.
	 *
	 * @param array $endpoints Endpoints to import.
	 * @return array Results with success/error counts.
	 */
	public function import( $endpoints ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( $endpoints as $endpoint ) {
			$endpoint['status'] = 'draft'; // Import as draft.
			$result = $this->save( 0, $endpoint );

			if ( is_wp_error( $result ) ) {
				$results['failed']++;
				$results['errors'][] = $endpoint['name'] . ': ' . $result->get_error_message();
			} else {
				$results['success']++;
			}
		}

		return $results;
	}
}
