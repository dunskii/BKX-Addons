<?php
/**
 * Rate Limiter Service.
 *
 * @package BookingX\EnterpriseAPI\Services
 */

namespace BookingX\EnterpriseAPI\Services;

defined( 'ABSPATH' ) || exit;

/**
 * RateLimiter class.
 */
class RateLimiter {

	/**
	 * Check rate limit.
	 *
	 * @param mixed            $result  Response to replace with.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request object.
	 * @return mixed|\WP_Error
	 */
	public function check_rate_limit( $result, $server, $request ) {
		// Skip for non-BookingX endpoints.
		$route = $request->get_route();
		if ( strpos( $route, '/bookingx/' ) === false ) {
			return $result;
		}

		$settings = get_option( 'bkx_enterprise_api_settings', array() );
		$limit    = $settings['default_rate_limit'] ?? 1000;
		$window   = $settings['rate_limit_window'] ?? 3600;

		// Get identifier.
		$identifier = $this->get_identifier( $request );

		// Check for custom rate limit (from API key).
		$custom_limit = $this->get_custom_limit( $request );
		if ( $custom_limit ) {
			$limit = $custom_limit;
		}

		// Get current usage.
		$usage = $this->get_usage( $identifier, $route, $window );

		// Check limit.
		if ( $usage >= $limit ) {
			$retry_after = $this->get_retry_after( $identifier, $route, $window );

			$response = new \WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded. Please try again later.', 'bkx-enterprise-api' ),
				array(
					'status' => 429,
					'headers' => array(
						'X-RateLimit-Limit'     => $limit,
						'X-RateLimit-Remaining' => 0,
						'X-RateLimit-Reset'     => $retry_after,
						'Retry-After'           => max( 1, $retry_after - time() ),
					),
				)
			);

			return $response;
		}

		// Increment usage.
		$this->increment_usage( $identifier, $route, $window );

		// Add rate limit headers.
		add_filter( 'rest_post_dispatch', function( $response ) use ( $limit, $usage ) {
			if ( $response instanceof \WP_REST_Response ) {
				$response->header( 'X-RateLimit-Limit', $limit );
				$response->header( 'X-RateLimit-Remaining', max( 0, $limit - $usage - 1 ) );
			}
			return $response;
		} );

		return $result;
	}

	/**
	 * Get request identifier.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return string
	 */
	private function get_identifier( $request ) {
		// Check for API key.
		if ( isset( $_SERVER['HTTP_X_API_KEY'] ) ) {
			return 'apikey:' . substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_API_KEY'] ) ), 0, 32 );
		}

		// Check for OAuth token.
		$auth_header = $this->get_authorization_header();
		if ( $auth_header && strpos( $auth_header, 'Bearer ' ) === 0 ) {
			$token = substr( $auth_header, 7 );
			return 'oauth:' . substr( md5( $token ), 0, 16 );
		}

		// Fall back to user ID.
		if ( is_user_logged_in() ) {
			return 'user:' . get_current_user_id();
		}

		// Fall back to IP.
		return 'ip:' . $this->get_client_ip();
	}

	/**
	 * Get custom rate limit.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return int|null
	 */
	private function get_custom_limit( $request ) {
		if ( ! isset( $_SERVER['HTTP_X_API_KEY'] ) ) {
			return null;
		}

		global $wpdb;

		$key_id = substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_API_KEY'] ) ), 0, 32 );

		$limit = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT rate_limit FROM {$wpdb->prefix}bkx_api_keys WHERE key_id = %s AND is_active = 1",
				$key_id
			)
		);

		return $limit ? (int) $limit : null;
	}

	/**
	 * Get current usage.
	 *
	 * @param string $identifier Identifier.
	 * @param string $endpoint   Endpoint.
	 * @param int    $window     Window in seconds.
	 * @return int
	 */
	private function get_usage( $identifier, $endpoint, $window ) {
		global $wpdb;

		$window_start = gmdate( 'Y-m-d H:i:s', floor( time() / $window ) * $window );

		$usage = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT requests FROM {$wpdb->prefix}bkx_rate_limits
				WHERE identifier = %s AND endpoint = %s AND window_start = %s",
				$identifier,
				$endpoint,
				$window_start
			)
		);

		return (int) $usage;
	}

	/**
	 * Increment usage.
	 *
	 * @param string $identifier Identifier.
	 * @param string $endpoint   Endpoint.
	 * @param int    $window     Window in seconds.
	 */
	private function increment_usage( $identifier, $endpoint, $window ) {
		global $wpdb;

		$window_start = gmdate( 'Y-m-d H:i:s', floor( time() / $window ) * $window );

		// Try to update.
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}bkx_rate_limits
				SET requests = requests + 1
				WHERE identifier = %s AND endpoint = %s AND window_start = %s",
				$identifier,
				$endpoint,
				$window_start
			)
		);

		// If no row updated, insert.
		if ( 0 === $result ) {
			$wpdb->insert(
				$wpdb->prefix . 'bkx_rate_limits',
				array(
					'identifier'   => $identifier,
					'endpoint'     => $endpoint,
					'requests'     => 1,
					'window_start' => $window_start,
				)
			);
		}
	}

	/**
	 * Get retry after timestamp.
	 *
	 * @param string $identifier Identifier.
	 * @param string $endpoint   Endpoint.
	 * @param int    $window     Window in seconds.
	 * @return int
	 */
	private function get_retry_after( $identifier, $endpoint, $window ) {
		$window_start = floor( time() / $window ) * $window;
		return $window_start + $window;
	}

	/**
	 * Clean up old rate limit records.
	 */
	public function cleanup() {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_rate_limits WHERE window_start < %s",
				$cutoff
			)
		);
	}

	/**
	 * Get rate limit stats.
	 *
	 * @param string $identifier Identifier.
	 * @return array
	 */
	public function get_stats( $identifier ) {
		global $wpdb;

		$stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT endpoint, SUM(requests) as total_requests
				FROM {$wpdb->prefix}bkx_rate_limits
				WHERE identifier = %s
				GROUP BY endpoint
				ORDER BY total_requests DESC
				LIMIT 10",
				$identifier
			)
		);

		return $stats ?: array();
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
	 * Get client IP.
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
