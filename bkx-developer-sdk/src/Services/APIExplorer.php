<?php
/**
 * API Explorer Service.
 *
 * @package BookingX\DeveloperSDK
 */

namespace BookingX\DeveloperSDK\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class APIExplorer
 *
 * Explore and test BookingX REST API endpoints.
 */
class APIExplorer {

	/**
	 * Make an API request.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint Endpoint path.
	 * @param string $body     Request body (JSON).
	 * @return array Response data.
	 */
	public function make_request( string $method, string $endpoint, string $body = '' ): array {
		$url = rest_url( $endpoint );

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-WP-Nonce'   => wp_create_nonce( 'wp_rest' ),
			),
			'timeout' => 30,
			'cookies' => $_COOKIE,
		);

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && $body ) {
			$args['body'] = $body;
		}

		$start_time = microtime( true );
		$response   = wp_remote_request( $url, $args );
		$end_time   = microtime( true );

		$response_time = round( ( $end_time - $start_time ) * 1000, 2 );

		if ( is_wp_error( $response ) ) {
			return array(
				'success'       => false,
				'status'        => 0,
				'response_time' => $response_time,
				'error'         => $response->get_error_message(),
				'headers'       => array(),
				'body'          => '',
			);
		}

		$status  = wp_remote_retrieve_response_code( $response );
		$headers = wp_remote_retrieve_headers( $response )->getAll();
		$body    = wp_remote_retrieve_body( $response );

		// Try to parse JSON.
		$parsed_body = json_decode( $body, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$body = $parsed_body;
		}

		// Log request if enabled.
		$this->log_request( $method, $endpoint, $status, $response_time );

		return array(
			'success'       => $status >= 200 && $status < 300,
			'status'        => $status,
			'response_time' => $response_time,
			'headers'       => $headers,
			'body'          => $body,
		);
	}

	/**
	 * Get all BookingX REST endpoints.
	 *
	 * @return array Endpoints.
	 */
	public function get_bookingx_endpoints(): array {
		$endpoints = array();

		// Get all registered routes.
		$routes = rest_get_server()->get_routes();

		foreach ( $routes as $route => $handlers ) {
			// Filter for BookingX routes.
			if ( strpos( $route, '/bkx/' ) === false && strpos( $route, '/bookingx/' ) === false ) {
				continue;
			}

			$methods = array();
			$args    = array();

			foreach ( $handlers as $handler ) {
				if ( isset( $handler['methods'] ) ) {
					$methods = array_merge( $methods, array_keys( $handler['methods'] ) );
				}
				if ( isset( $handler['args'] ) ) {
					$args = array_merge( $args, $handler['args'] );
				}
			}

			$endpoints[] = array(
				'route'   => $route,
				'methods' => array_unique( $methods ),
				'args'    => $args,
			);
		}

		return $endpoints;
	}

	/**
	 * Get common endpoints for quick access.
	 *
	 * @return array Common endpoints.
	 */
	public function get_common_endpoints(): array {
		return array(
			array(
				'label'       => __( 'Get Bookings', 'bkx-developer-sdk' ),
				'method'      => 'GET',
				'endpoint'    => '/wp/v2/bkx_booking',
				'description' => __( 'Retrieve a list of bookings.', 'bkx-developer-sdk' ),
			),
			array(
				'label'       => __( 'Get Single Booking', 'bkx-developer-sdk' ),
				'method'      => 'GET',
				'endpoint'    => '/wp/v2/bkx_booking/{id}',
				'description' => __( 'Retrieve a specific booking.', 'bkx-developer-sdk' ),
			),
			array(
				'label'       => __( 'Create Booking', 'bkx-developer-sdk' ),
				'method'      => 'POST',
				'endpoint'    => '/wp/v2/bkx_booking',
				'description' => __( 'Create a new booking.', 'bkx-developer-sdk' ),
				'body'        => json_encode(
					array(
						'status'    => 'bkx-pending',
						'meta'      => array(
							'booking_date'   => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
							'booking_time'   => '10:00:00',
							'customer_email' => 'customer@example.com',
						),
					),
					JSON_PRETTY_PRINT
				),
			),
			array(
				'label'       => __( 'Update Booking', 'bkx-developer-sdk' ),
				'method'      => 'PUT',
				'endpoint'    => '/wp/v2/bkx_booking/{id}',
				'description' => __( 'Update an existing booking.', 'bkx-developer-sdk' ),
			),
			array(
				'label'       => __( 'Delete Booking', 'bkx-developer-sdk' ),
				'method'      => 'DELETE',
				'endpoint'    => '/wp/v2/bkx_booking/{id}',
				'description' => __( 'Delete a booking.', 'bkx-developer-sdk' ),
			),
			array(
				'label'       => __( 'Get Services', 'bkx-developer-sdk' ),
				'method'      => 'GET',
				'endpoint'    => '/wp/v2/bkx_base',
				'description' => __( 'Retrieve a list of services.', 'bkx-developer-sdk' ),
			),
			array(
				'label'       => __( 'Get Staff', 'bkx-developer-sdk' ),
				'method'      => 'GET',
				'endpoint'    => '/wp/v2/bkx_seat',
				'description' => __( 'Retrieve a list of staff/resources.', 'bkx-developer-sdk' ),
			),
			array(
				'label'       => __( 'Get Extras', 'bkx-developer-sdk' ),
				'method'      => 'GET',
				'endpoint'    => '/wp/v2/bkx_addition',
				'description' => __( 'Retrieve a list of extras/additions.', 'bkx-developer-sdk' ),
			),
			array(
				'label'       => __( 'Check Availability', 'bkx-developer-sdk' ),
				'method'      => 'GET',
				'endpoint'    => '/bkx/v1/availability',
				'description' => __( 'Check availability for a date range.', 'bkx-developer-sdk' ),
			),
		);
	}

	/**
	 * Generate cURL command for an endpoint.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint Endpoint.
	 * @param string $body     Request body.
	 * @return string cURL command.
	 */
	public function generate_curl_command( string $method, string $endpoint, string $body = '' ): string {
		$url = rest_url( $endpoint );

		$curl = "curl -X {$method} \\\n";
		$curl .= "  '{$url}' \\\n";
		$curl .= "  -H 'Content-Type: application/json' \\\n";
		$curl .= "  -H 'X-WP-Nonce: YOUR_NONCE'";

		if ( $body && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$curl .= " \\\n  -d '{$body}'";
		}

		return $curl;
	}

	/**
	 * Generate JavaScript fetch code for an endpoint.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint Endpoint.
	 * @param string $body     Request body.
	 * @return string JavaScript code.
	 */
	public function generate_js_code( string $method, string $endpoint, string $body = '' ): string {
		$url = rest_url( $endpoint );

		$code = "fetch('{$url}', {\n";
		$code .= "    method: '{$method}',\n";
		$code .= "    headers: {\n";
		$code .= "        'Content-Type': 'application/json',\n";
		$code .= "        'X-WP-Nonce': wpApiSettings.nonce\n";
		$code .= "    }";

		if ( $body && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$code .= ",\n    body: JSON.stringify({$body})";
		}

		$code .= "\n})\n";
		$code .= ".then(response => response.json())\n";
		$code .= ".then(data => console.log(data))\n";
		$code .= ".catch(error => console.error('Error:', error));";

		return $code;
	}

	/**
	 * Generate PHP code for an endpoint.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint Endpoint.
	 * @param string $body     Request body.
	 * @return string PHP code.
	 */
	public function generate_php_code( string $method, string $endpoint, string $body = '' ): string {
		$code = "<?php\n";
		$code .= "\$response = wp_remote_request(\n";
		$code .= "    rest_url( '{$endpoint}' ),\n";
		$code .= "    array(\n";
		$code .= "        'method'  => '{$method}',\n";
		$code .= "        'headers' => array(\n";
		$code .= "            'Content-Type' => 'application/json',\n";
		$code .= "            'X-WP-Nonce'   => wp_create_nonce( 'wp_rest' ),\n";
		$code .= "        ),\n";

		if ( $body && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$code .= "        'body' => '{$body}',\n";
		}

		$code .= "    )\n";
		$code .= ");\n\n";
		$code .= "if ( is_wp_error( \$response ) ) {\n";
		$code .= "    echo \$response->get_error_message();\n";
		$code .= "} else {\n";
		$code .= "    \$body = json_decode( wp_remote_retrieve_body( \$response ), true );\n";
		$code .= "    print_r( \$body );\n";
		$code .= "}\n";

		return $code;
	}

	/**
	 * Log API request.
	 *
	 * @param string $method        HTTP method.
	 * @param string $endpoint      Endpoint.
	 * @param int    $status        Response status.
	 * @param float  $response_time Response time.
	 */
	private function log_request( string $method, string $endpoint, int $status, float $response_time ): void {
		$settings = get_option( 'bkx_developer_sdk_settings', array() );

		if ( empty( $settings['log_api_requests'] ) ) {
			return;
		}

		$log = get_option( 'bkx_api_explorer_log', array() );

		$log[] = array(
			'method'        => $method,
			'endpoint'      => $endpoint,
			'status'        => $status,
			'response_time' => $response_time,
			'timestamp'     => current_time( 'mysql' ),
			'user_id'       => get_current_user_id(),
		);

		// Keep only last 100 entries.
		$log = array_slice( $log, -100 );

		update_option( 'bkx_api_explorer_log', $log );
	}

	/**
	 * Get API request log.
	 *
	 * @param int $limit Number of entries to return.
	 * @return array Log entries.
	 */
	public function get_request_log( int $limit = 50 ): array {
		$log = get_option( 'bkx_api_explorer_log', array() );
		return array_slice( array_reverse( $log ), 0, $limit );
	}

	/**
	 * Clear API request log.
	 */
	public function clear_request_log(): void {
		delete_option( 'bkx_api_explorer_log' );
	}
}
