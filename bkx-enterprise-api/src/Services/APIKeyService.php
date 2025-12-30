<?php
/**
 * API Key Service.
 *
 * @package BookingX\EnterpriseAPI\Services
 */

namespace BookingX\EnterpriseAPI\Services;

defined( 'ABSPATH' ) || exit;

/**
 * APIKeyService class.
 */
class APIKeyService {

	/**
	 * Authenticate request via API key.
	 *
	 * @param int|false $user_id Current user ID.
	 * @return int|false
	 */
	public function authenticate( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}

		$api_key = $this->get_api_key_from_request();
		if ( ! $api_key ) {
			return $user_id;
		}

		$key_data = $this->validate_api_key( $api_key );
		if ( $key_data && $key_data->user_id ) {
			// Update last used.
			$this->update_last_used( $key_data->id );
			return (int) $key_data->user_id;
		}

		return $user_id;
	}

	/**
	 * Get API key from request.
	 *
	 * @return string|null
	 */
	private function get_api_key_from_request() {
		// Check header.
		if ( isset( $_SERVER['HTTP_X_API_KEY'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_API_KEY'] ) );
		}

		// Check query parameter.
		if ( isset( $_GET['api_key'] ) ) {
			return sanitize_text_field( $_GET['api_key'] );
		}

		// Check Authorization header with ApiKey scheme.
		$auth_header = $this->get_authorization_header();
		if ( $auth_header && strpos( $auth_header, 'ApiKey ' ) === 0 ) {
			return substr( $auth_header, 7 );
		}

		return null;
	}

	/**
	 * Validate API key.
	 *
	 * @param string $api_key API key.
	 * @return object|null
	 */
	private function validate_api_key( $api_key ) {
		global $wpdb;

		// Extract key ID (first 32 chars).
		$key_id = substr( $api_key, 0, 32 );

		$key_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_api_keys WHERE key_id = %s AND is_active = 1",
				$key_id
			)
		);

		if ( ! $key_data ) {
			return null;
		}

		// Check expiration.
		if ( $key_data->expires_at && strtotime( $key_data->expires_at ) < time() ) {
			return null;
		}

		// Verify key hash.
		if ( ! password_verify( $api_key, $key_data->key_hash ) ) {
			return null;
		}

		return $key_data;
	}

	/**
	 * Update last used timestamp.
	 *
	 * @param int $id Key ID.
	 */
	private function update_last_used( $id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'bkx_api_keys',
			array(
				'last_used' => current_time( 'mysql' ),
				'last_ip'   => $this->get_client_ip(),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * List API keys.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_keys( $request ) {
		global $wpdb;

		$user_id = $request->get_param( 'user_id' );
		$page    = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$limit   = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$offset  = ( $page - 1 ) * $limit;

		$where = '1=1';
		$values = array();

		if ( $user_id ) {
			$where .= ' AND user_id = %d';
			$values[] = $user_id;
		}

		$values[] = $limit;
		$values[] = $offset;

		$keys = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, key_id, name, description, user_id, permissions, rate_limit, last_used, last_ip, is_active, expires_at, created_at
				FROM {$wpdb->prefix}bkx_api_keys
				WHERE {$where}
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$values
			)
		);

		// Get total count.
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_api_keys WHERE {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_slice( $values, 0, -2 )
			)
		);

		// Format keys.
		foreach ( $keys as $key ) {
			$key->permissions = json_decode( $key->permissions, true ) ?: array();
			$key->user = get_userdata( $key->user_id );
			unset( $key->user->user_pass );
		}

		return rest_ensure_response( array(
			'keys'  => $keys,
			'total' => (int) $total,
			'page'  => $page,
			'pages' => ceil( $total / $limit ),
		) );
	}

	/**
	 * Get single API key.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_key( $request ) {
		global $wpdb;

		$key_id = $request->get_param( 'id' );

		$key = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, key_id, name, description, user_id, permissions, rate_limit, last_used, last_ip, is_active, expires_at, created_at
				FROM {$wpdb->prefix}bkx_api_keys
				WHERE key_id = %s",
				$key_id
			)
		);

		if ( ! $key ) {
			return new \WP_Error( 'not_found', __( 'API key not found.', 'bkx-enterprise-api' ), array( 'status' => 404 ) );
		}

		$key->permissions = json_decode( $key->permissions, true ) ?: array();
		$key->user = get_userdata( $key->user_id );
		unset( $key->user->user_pass );

		return rest_ensure_response( $key );
	}

	/**
	 * Create API key.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_key( $request ) {
		global $wpdb;

		$name        = sanitize_text_field( $request->get_param( 'name' ) );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) );
		$permissions = $request->get_param( 'permissions' ) ?: array( 'read' );
		$rate_limit  = absint( $request->get_param( 'rate_limit' ) ) ?: 1000;
		$expires_at  = $request->get_param( 'expires_at' );
		$user_id     = $request->get_param( 'user_id' ) ?: get_current_user_id();

		if ( empty( $name ) ) {
			return new \WP_Error( 'missing_name', __( 'API key name is required.', 'bkx-enterprise-api' ), array( 'status' => 400 ) );
		}

		// Generate key.
		$key_id  = bin2hex( random_bytes( 16 ) );
		$secret  = bin2hex( random_bytes( 32 ) );
		$api_key = $key_id . $secret;

		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_api_keys',
			array(
				'key_id'      => $key_id,
				'key_hash'    => password_hash( $api_key, PASSWORD_DEFAULT ),
				'name'        => $name,
				'description' => $description,
				'user_id'     => $user_id,
				'permissions' => wp_json_encode( array_map( 'sanitize_text_field', $permissions ) ),
				'rate_limit'  => $rate_limit,
				'expires_at'  => $expires_at ? sanitize_text_field( $expires_at ) : null,
			)
		);

		if ( ! $result ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create API key.', 'bkx-enterprise-api' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'key_id'  => $key_id,
			'api_key' => $api_key,
			'message' => __( 'API key created. Save this key securely - it will only be shown once.', 'bkx-enterprise-api' ),
		) );
	}

	/**
	 * Delete API key.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_key( $request ) {
		global $wpdb;

		$key_id = $request->get_param( 'id' );

		$result = $wpdb->delete(
			$wpdb->prefix . 'bkx_api_keys',
			array( 'key_id' => $key_id )
		);

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete API key.', 'bkx-enterprise-api' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * Check if API key has permission.
	 *
	 * @param string $api_key    API key.
	 * @param string $permission Permission to check.
	 * @return bool
	 */
	public function has_permission( $api_key, $permission ) {
		$key_data = $this->validate_api_key( $api_key );
		if ( ! $key_data ) {
			return false;
		}

		$permissions = json_decode( $key_data->permissions, true ) ?: array();

		// Check for wildcard.
		if ( in_array( '*', $permissions, true ) ) {
			return true;
		}

		return in_array( $permission, $permissions, true );
	}

	/**
	 * Get authorization header.
	 *
	 * @return string|null
	 */
	private function get_authorization_header() {
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		return null;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated list.
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
