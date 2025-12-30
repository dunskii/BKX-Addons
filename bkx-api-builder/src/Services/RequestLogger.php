<?php
/**
 * Request Logger service.
 *
 * @package BookingX\APIBuilder\Services
 */

namespace BookingX\APIBuilder\Services;

defined( 'ABSPATH' ) || exit;

/**
 * RequestLogger class.
 */
class RequestLogger {

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
		$this->table = $wpdb->prefix . 'bkx_api_logs';
	}

	/**
	 * Log API request.
	 *
	 * @param int              $endpoint_id  Endpoint ID.
	 * @param \WP_REST_Request $request      Request object.
	 * @param mixed            $response     Response data.
	 * @param float            $response_time Response time in seconds.
	 * @param int              $api_key_id   Optional API key ID.
	 */
	public function log( $endpoint_id, $request, $response, $response_time, $api_key_id = 0 ) {
		global $wpdb;

		$response_code = 200;
		$response_body = '';
		$error_message = '';

		if ( is_wp_error( $response ) ) {
			$response_code = $response->get_error_data( 'status' ) ?: 500;
			$response_body = wp_json_encode( array(
				'code'    => $response->get_error_code(),
				'message' => $response->get_error_message(),
			) );
			$error_message = $response->get_error_message();
		} elseif ( $response instanceof \WP_REST_Response ) {
			$response_code = $response->get_status();
			$response_body = wp_json_encode( $response->get_data() );
		} else {
			$response_body = wp_json_encode( $response );
		}

		// Truncate large bodies.
		$max_body_size  = 65535; // TEXT column limit.
		$request_body   = $request->get_body();
		$request_headers = wp_json_encode( $request->get_headers() );

		if ( strlen( $request_body ) > $max_body_size ) {
			$request_body = substr( $request_body, 0, $max_body_size - 20 ) . '... [truncated]';
		}

		if ( strlen( $response_body ) > $max_body_size ) {
			$response_body = substr( $response_body, 0, $max_body_size - 20 ) . '... [truncated]';
		}

		// Mask sensitive data.
		$request_body    = $this->mask_sensitive_data( $request_body );
		$request_headers = $this->mask_sensitive_headers( $request_headers );

		$data = array(
			'endpoint_id'     => $endpoint_id,
			'api_key_id'      => $api_key_id ?: null,
			'method'          => $request->get_method(),
			'route'           => $request->get_route(),
			'request_headers' => $request_headers,
			'request_body'    => $request_body,
			'response_code'   => $response_code,
			'response_body'   => $response_body,
			'response_time'   => round( $response_time * 1000, 2 ), // Convert to ms.
			'ip_address'      => $this->get_client_ip(),
			'user_agent'      => $this->get_user_agent(),
			'error_message'   => $error_message,
			'created_at'      => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->table,
			$data,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Mask sensitive data in request body.
	 *
	 * @param string $body Request body.
	 * @return string
	 */
	private function mask_sensitive_data( $body ) {
		$patterns = array(
			'/"password"\s*:\s*"[^"]*"/'     => '"password": "[REDACTED]"',
			'/"api_key"\s*:\s*"[^"]*"/'      => '"api_key": "[REDACTED]"',
			'/"api_secret"\s*:\s*"[^"]*"/'   => '"api_secret": "[REDACTED]"',
			'/"credit_card"\s*:\s*"[^"]*"/'  => '"credit_card": "[REDACTED]"',
			'/"card_number"\s*:\s*"[^"]*"/'  => '"card_number": "[REDACTED]"',
			'/"cvv"\s*:\s*"[^"]*"/'          => '"cvv": "[REDACTED]"',
			'/"ssn"\s*:\s*"[^"]*"/'          => '"ssn": "[REDACTED]"',
		);

		return preg_replace( array_keys( $patterns ), array_values( $patterns ), $body );
	}

	/**
	 * Mask sensitive headers.
	 *
	 * @param string $headers Headers JSON.
	 * @return string
	 */
	private function mask_sensitive_headers( $headers ) {
		$decoded = json_decode( $headers, true );

		if ( ! is_array( $decoded ) ) {
			return $headers;
		}

		$sensitive = array( 'authorization', 'x-api-key', 'x-api-secret', 'cookie' );

		foreach ( $sensitive as $header ) {
			if ( isset( $decoded[ $header ] ) ) {
				$decoded[ $header ] = '[REDACTED]';
			}
		}

		return wp_json_encode( $decoded );
	}

	/**
	 * Get logs.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'endpoint_id'   => 0,
			'api_key_id'    => 0,
			'response_code' => 0,
			'method'        => '',
			'date_from'     => '',
			'date_to'       => '',
			'search'        => '',
			'per_page'      => 50,
			'page'          => 1,
			'orderby'       => 'created_at',
			'order'         => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['endpoint_id'] ) ) {
			$where[]  = 'endpoint_id = %d';
			$values[] = $args['endpoint_id'];
		}

		if ( ! empty( $args['api_key_id'] ) ) {
			$where[]  = 'api_key_id = %d';
			$values[] = $args['api_key_id'];
		}

		if ( ! empty( $args['response_code'] ) ) {
			$where[]  = 'response_code = %d';
			$values[] = $args['response_code'];
		}

		if ( ! empty( $args['method'] ) ) {
			$where[]  = 'method = %s';
			$values[] = $args['method'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(route LIKE %s OR ip_address LIKE %s)';
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
	 * Get log by ID.
	 *
	 * @param int $id Log ID.
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
	 * Get statistics.
	 *
	 * @param int $hours Number of hours to analyze.
	 * @return array
	 */
	public function get_stats( $hours = 24 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - ( $hours * 3600 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_requests,
					COUNT(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 END) as successful_requests,
					COUNT(CASE WHEN response_code >= 400 AND response_code < 500 THEN 1 END) as client_errors,
					COUNT(CASE WHEN response_code >= 500 THEN 1 END) as server_errors,
					AVG(response_time) as avg_response_time,
					MAX(response_time) as max_response_time,
					MIN(response_time) as min_response_time,
					COUNT(DISTINCT ip_address) as unique_ips
				 FROM {$this->table}
				 WHERE created_at > %s",
				$since
			),
			ARRAY_A
		);

		return $stats ?: array();
	}

	/**
	 * Get requests by hour.
	 *
	 * @param int $hours Number of hours.
	 * @return array
	 */
	public function get_requests_by_hour( $hours = 24 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - ( $hours * 3600 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(created_at, '%%Y-%%m-%%d %%H:00:00') as hour,
					COUNT(*) as requests,
					COUNT(CASE WHEN response_code >= 400 THEN 1 END) as errors
				 FROM {$this->table}
				 WHERE created_at > %s
				 GROUP BY hour
				 ORDER BY hour",
				$since
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Get top endpoints.
	 *
	 * @param int $limit Number of endpoints.
	 * @param int $hours Number of hours.
	 * @return array
	 */
	public function get_top_endpoints( $limit = 10, $hours = 24 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - ( $hours * 3600 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					route,
					method,
					COUNT(*) as requests,
					AVG(response_time) as avg_response_time,
					COUNT(CASE WHEN response_code >= 400 THEN 1 END) as errors
				 FROM {$this->table}
				 WHERE created_at > %s
				 GROUP BY route, method
				 ORDER BY requests DESC
				 LIMIT %d",
				$since,
				$limit
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Get error summary.
	 *
	 * @param int $hours Number of hours.
	 * @return array
	 */
	public function get_error_summary( $hours = 24 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - ( $hours * 3600 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					response_code,
					COUNT(*) as count,
					MAX(error_message) as sample_message
				 FROM {$this->table}
				 WHERE created_at > %s AND response_code >= 400
				 GROUP BY response_code
				 ORDER BY count DESC",
				$since
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Cleanup old logs.
	 *
	 * @param int $days Number of days to keep.
	 * @return int Number of deleted records.
	 */
	public function cleanup( $days = 30 ) {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * 86400 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE created_at < %s",
				$cutoff
			)
		);

		return (int) $deleted;
	}

	/**
	 * Clear all logs.
	 *
	 * @return bool
	 */
	public function clear_all() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( "TRUNCATE TABLE {$this->table}" );

		return false !== $result;
	}

	/**
	 * Get total count.
	 *
	 * @return int
	 */
	public function get_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Export logs.
	 *
	 * @param array $args Query arguments.
	 * @return string CSV content.
	 */
	public function export( $args = array() ) {
		$args['per_page'] = 10000; // Max export.
		$logs = $this->get_logs( $args );

		$csv = "ID,Endpoint ID,Method,Route,Response Code,Response Time (ms),IP Address,User Agent,Error,Created At\n";

		foreach ( $logs as $log ) {
			$csv .= sprintf(
				"%d,%d,%s,\"%s\",%d,%.2f,%s,\"%s\",\"%s\",%s\n",
				$log['id'],
				$log['endpoint_id'],
				$log['method'],
				str_replace( '"', '""', $log['route'] ),
				$log['response_code'],
				$log['response_time'],
				$log['ip_address'],
				str_replace( '"', '""', $log['user_agent'] ?? '' ),
				str_replace( '"', '""', $log['error_message'] ?? '' ),
				$log['created_at']
			);
		}

		return $csv;
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

	/**
	 * Get user agent.
	 *
	 * @return string
	 */
	private function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
			: '';
	}
}
