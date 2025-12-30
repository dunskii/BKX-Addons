<?php
/**
 * Rate Limiter service.
 *
 * @package BookingX\APIBuilder\Services
 */

namespace BookingX\APIBuilder\Services;

defined( 'ABSPATH' ) || exit;

/**
 * RateLimiter class.
 */
class RateLimiter {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * In-memory cache.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'bkx_api_rate_limits';
	}

	/**
	 * Check rate limit.
	 *
	 * @param string $identifier Unique identifier (IP, API key ID, etc.).
	 * @param int    $limit      Maximum requests allowed.
	 * @param int    $window     Time window in seconds.
	 * @param int    $endpoint_id Optional endpoint ID for per-endpoint limits.
	 * @return bool True if within limit, false if exceeded.
	 */
	public function check( $identifier, $limit, $window, $endpoint_id = 0 ) {
		if ( $limit <= 0 ) {
			return true; // No limit.
		}

		$cache_key = $identifier . '_' . $endpoint_id;

		// Check in-memory cache first.
		if ( isset( $this->cache[ $cache_key ] ) ) {
			$cached = $this->cache[ $cache_key ];
			if ( $cached['count'] >= $limit && ( time() - $cached['start'] ) < $window ) {
				return false;
			}
		}

		global $wpdb;

		$window_start = gmdate( 'Y-m-d H:i:s', time() - $window );

		// Get current count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				 WHERE identifier = %s AND (endpoint_id = %d OR endpoint_id IS NULL)
				 AND window_start > %s",
				$identifier,
				$endpoint_id,
				$window_start
			),
			ARRAY_A
		);

		if ( $record ) {
			if ( $record['request_count'] >= $limit ) {
				return false;
			}

			// Increment count.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array( 'request_count' => $record['request_count'] + 1 ),
				array( 'id' => $record['id'] ),
				array( '%d' ),
				array( '%d' )
			);

			$this->cache[ $cache_key ] = array(
				'count' => $record['request_count'] + 1,
				'start' => strtotime( $record['window_start'] ),
			);
		} else {
			// Create new window.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$this->table,
				array(
					'identifier'    => $identifier,
					'endpoint_id'   => $endpoint_id ?: null,
					'request_count' => 1,
					'window_start'  => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%d', '%s' )
			);

			$this->cache[ $cache_key ] = array(
				'count' => 1,
				'start' => time(),
			);
		}

		return true;
	}

	/**
	 * Get remaining requests.
	 *
	 * @param string $identifier  Unique identifier.
	 * @param int    $limit       Maximum requests allowed.
	 * @param int    $window      Time window in seconds.
	 * @param int    $endpoint_id Optional endpoint ID.
	 * @return int Remaining requests.
	 */
	public function get_remaining( $identifier, $limit = 1000, $window = 3600, $endpoint_id = 0 ) {
		global $wpdb;

		$window_start = gmdate( 'Y-m-d H:i:s', time() - $window );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT request_count FROM {$this->table}
				 WHERE identifier = %s AND (endpoint_id = %d OR endpoint_id IS NULL)
				 AND window_start > %s",
				$identifier,
				$endpoint_id,
				$window_start
			)
		);

		return max( 0, $limit - (int) $count );
	}

	/**
	 * Get reset time.
	 *
	 * @param string $identifier  Unique identifier.
	 * @param int    $window      Time window in seconds.
	 * @param int    $endpoint_id Optional endpoint ID.
	 * @return int Unix timestamp when limit resets.
	 */
	public function get_reset_time( $identifier, $window = 3600, $endpoint_id = 0 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$window_start = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT window_start FROM {$this->table}
				 WHERE identifier = %s AND (endpoint_id = %d OR endpoint_id IS NULL)
				 ORDER BY window_start DESC
				 LIMIT 1",
				$identifier,
				$endpoint_id
			)
		);

		if ( ! $window_start ) {
			return time() + $window;
		}

		return strtotime( $window_start ) + $window;
	}

	/**
	 * Reset rate limit for identifier.
	 *
	 * @param string $identifier  Unique identifier.
	 * @param int    $endpoint_id Optional endpoint ID.
	 * @return bool
	 */
	public function reset( $identifier, $endpoint_id = 0 ) {
		global $wpdb;

		$where = array( 'identifier' => $identifier );
		if ( $endpoint_id ) {
			$where['endpoint_id'] = $endpoint_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $this->table, $where );

		$cache_key = $identifier . '_' . $endpoint_id;
		unset( $this->cache[ $cache_key ] );

		return false !== $result;
	}

	/**
	 * Cleanup expired records.
	 *
	 * @return int Number of deleted records.
	 */
	public function cleanup() {
		global $wpdb;

		// Delete records older than 24 hours.
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 86400 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE window_start < %s",
				$cutoff
			)
		);

		return (int) $deleted;
	}

	/**
	 * Get usage statistics.
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
					COUNT(DISTINCT identifier) as unique_clients,
					SUM(request_count) as total_requests,
					MAX(request_count) as max_requests,
					AVG(request_count) as avg_requests
				 FROM {$this->table}
				 WHERE window_start > %s",
				$since
			),
			ARRAY_A
		);

		return $stats ?: array();
	}

	/**
	 * Get top consumers.
	 *
	 * @param int $limit Number of top consumers.
	 * @param int $hours Number of hours to analyze.
	 * @return array
	 */
	public function get_top_consumers( $limit = 10, $hours = 24 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', time() - ( $hours * 3600 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT identifier, SUM(request_count) as total_requests
				 FROM {$this->table}
				 WHERE window_start > %s
				 GROUP BY identifier
				 ORDER BY total_requests DESC
				 LIMIT %d",
				$since,
				$limit
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Check if identifier is blocked.
	 *
	 * @param string $identifier Unique identifier.
	 * @return bool
	 */
	public function is_blocked( $identifier ) {
		$blocked = get_option( 'bkx_api_blocked_identifiers', array() );
		return in_array( $identifier, $blocked, true );
	}

	/**
	 * Block identifier.
	 *
	 * @param string $identifier Unique identifier.
	 * @param int    $duration   Block duration in seconds (0 for permanent).
	 * @return bool
	 */
	public function block( $identifier, $duration = 0 ) {
		$blocked = get_option( 'bkx_api_blocked_identifiers', array() );

		if ( $duration > 0 ) {
			$blocked[ $identifier ] = time() + $duration;
		} else {
			$blocked[ $identifier ] = 0; // Permanent.
		}

		return update_option( 'bkx_api_blocked_identifiers', $blocked );
	}

	/**
	 * Unblock identifier.
	 *
	 * @param string $identifier Unique identifier.
	 * @return bool
	 */
	public function unblock( $identifier ) {
		$blocked = get_option( 'bkx_api_blocked_identifiers', array() );
		unset( $blocked[ $identifier ] );
		return update_option( 'bkx_api_blocked_identifiers', $blocked );
	}
}
