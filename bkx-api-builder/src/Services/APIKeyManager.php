<?php
/**
 * API Key Manager service.
 *
 * @package BookingX\APIBuilder\Services
 */

namespace BookingX\APIBuilder\Services;

defined( 'ABSPATH' ) || exit;

/**
 * APIKeyManager class.
 */
class APIKeyManager {

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
		$this->table = $wpdb->prefix . 'bkx_api_keys';
	}

	/**
	 * Generate new API key.
	 *
	 * @param array $data Key data.
	 * @return array|\WP_Error Key credentials or error.
	 */
	public function generate( $data ) {
		global $wpdb;

		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'API key name is required', 'bkx-api-builder' ) );
		}

		// Generate key and secret.
		$api_key    = $this->generate_key();
		$api_secret = $this->generate_secret();

		// Hash the secret for storage.
		$hashed_secret = wp_hash_password( $api_secret );

		$insert_data = array(
			'name'              => sanitize_text_field( $data['name'] ),
			'api_key'           => $api_key,
			'api_secret'        => $hashed_secret,
			'user_id'           => absint( $data['user_id'] ?? get_current_user_id() ),
			'permissions'       => $data['permissions'] ?? '{}',
			'rate_limit'        => absint( $data['rate_limit'] ?? 1000 ),
			'rate_limit_window' => absint( $data['rate_limit_window'] ?? 3600 ),
			'allowed_ips'       => $data['allowed_ips'] ?? '[]',
			'allowed_origins'   => $data['allowed_origins'] ?? '[]',
			'expires_at'        => $data['expires_at'] ?: null,
			'status'            => 'active',
			'created_at'        => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			$insert_data,
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create API key', 'bkx-api-builder' ) );
		}

		return array(
			'id'         => $wpdb->insert_id,
			'api_key'    => $api_key,
			'api_secret' => $api_secret, // Only returned once.
		);
	}

	/**
	 * Generate API key.
	 *
	 * @return string
	 */
	private function generate_key() {
		return 'bkx_' . bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Generate API secret.
	 *
	 * @return string
	 */
	private function generate_secret() {
		return 'bkxs_' . bin2hex( random_bytes( 24 ) );
	}

	/**
	 * Validate API key.
	 *
	 * @param string $api_key API key to validate.
	 * @return array|false Key data or false.
	 */
	public function validate_key( $api_key ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$key_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE api_key = %s AND status = 'active'",
				$api_key
			),
			ARRAY_A
		);

		if ( ! $key_data ) {
			return false;
		}

		// Check expiration.
		if ( $key_data['expires_at'] && strtotime( $key_data['expires_at'] ) < time() ) {
			$this->update_status( $key_data['id'], 'expired' );
			return false;
		}

		return $key_data;
	}

	/**
	 * Validate API key with secret.
	 *
	 * @param string $api_key    API key.
	 * @param string $api_secret API secret.
	 * @return array|false Key data or false.
	 */
	public function validate_credentials( $api_key, $api_secret ) {
		$key_data = $this->validate_key( $api_key );

		if ( ! $key_data ) {
			return false;
		}

		// Verify secret.
		if ( ! wp_check_password( $api_secret, $key_data['api_secret'] ) ) {
			return false;
		}

		return $key_data;
	}

	/**
	 * Get all API keys.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'user_id'  => 0,
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

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$values[] = $args['user_id'];
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];

		$query = "SELECT id, name, api_key, user_id, permissions, rate_limit, rate_limit_window,
				         allowed_ips, allowed_origins, last_used_at, last_ip, request_count,
				         expires_at, status, created_at
				  FROM {$this->table}
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
	 * Get API key by ID.
	 *
	 * @param int $id Key ID.
	 * @return array|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, api_key, user_id, permissions, rate_limit, rate_limit_window,
				        allowed_ips, allowed_origins, last_used_at, last_ip, request_count,
				        expires_at, status, created_at
				 FROM {$this->table}
				 WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $result ?: null;
	}

	/**
	 * Update API key.
	 *
	 * @param int   $id   Key ID.
	 * @param array $data Update data.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$allowed_fields = array(
			'name', 'permissions', 'rate_limit', 'rate_limit_window',
			'allowed_ips', 'allowed_origins', 'expires_at', 'status',
		);

		$update_data = array();
		$formats     = array();

		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
				$formats[]             = in_array( $field, array( 'rate_limit', 'rate_limit_window' ), true ) ? '%d' : '%s';
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
	 * Revoke API key.
	 *
	 * @param int $id Key ID.
	 * @return bool
	 */
	public function revoke( $id ) {
		return $this->update_status( $id, 'revoked' );
	}

	/**
	 * Update key status.
	 *
	 * @param int    $id     Key ID.
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
	 * Update last used timestamp.
	 *
	 * @param int $id Key ID.
	 * @return bool
	 */
	public function update_last_used( $id ) {
		global $wpdb;

		$ip = $this->get_client_ip();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table}
				 SET last_used_at = %s, last_ip = %s, request_count = request_count + 1
				 WHERE id = %d",
				current_time( 'mysql' ),
				$ip,
				$id
			)
		);

		return false !== $result;
	}

	/**
	 * Delete API key.
	 *
	 * @param int $id Key ID.
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
	 * Regenerate API secret.
	 *
	 * @param int $id Key ID.
	 * @return string|\WP_Error New secret or error.
	 */
	public function regenerate_secret( $id ) {
		global $wpdb;

		$key = $this->get( $id );
		if ( ! $key ) {
			return new \WP_Error( 'not_found', __( 'API key not found', 'bkx-api-builder' ) );
		}

		$new_secret    = $this->generate_secret();
		$hashed_secret = wp_hash_password( $new_secret );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array(
				'api_secret' => $hashed_secret,
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
	 * Get usage statistics.
	 *
	 * @param int $id Key ID.
	 * @return array
	 */
	public function get_usage_stats( $id ) {
		global $wpdb;

		$logs_table = $wpdb->prefix . 'bkx_api_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_requests,
					COUNT(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 END) as successful_requests,
					COUNT(CASE WHEN response_code >= 400 THEN 1 END) as failed_requests,
					AVG(response_time) as avg_response_time,
					MAX(created_at) as last_request
				 FROM {$logs_table}
				 WHERE api_key_id = %d",
				$id
			),
			ARRAY_A
		);

		return $stats ?: array();
	}

	/**
	 * Get client IP.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}

		return '127.0.0.1';
	}
}
