<?php
/**
 * Sandbox Manager Service.
 *
 * @package BookingX\DeveloperSDK
 */

namespace BookingX\DeveloperSDK\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class SandboxManager
 *
 * Manages isolated sandbox environments for testing.
 */
class SandboxManager {

	/**
	 * Create a new sandbox.
	 *
	 * @param string $name Sandbox name.
	 * @return array|WP_Error Sandbox details or error.
	 */
	public function create( string $name ) {
		$settings = get_option( 'bkx_developer_sdk_settings', array() );

		if ( empty( $settings['enable_sandbox'] ) ) {
			return new \WP_Error( 'disabled', __( 'Sandbox feature is disabled.', 'bkx-developer-sdk' ) );
		}

		$prefix    = $settings['sandbox_prefix'] ?? 'bkx_sandbox_';
		$sandbox_id = $prefix . sanitize_title( $name ) . '_' . time();

		// Create test data.
		$test_data = $this->create_test_data( $sandbox_id );

		if ( is_wp_error( $test_data ) ) {
			return $test_data;
		}

		// Store sandbox info.
		$sandboxes               = get_option( 'bkx_sandboxes', array() );
		$sandboxes[ $sandbox_id ] = array(
			'name'       => $name,
			'created_at' => current_time( 'mysql' ),
			'created_by' => get_current_user_id(),
			'data'       => $test_data,
		);
		update_option( 'bkx_sandboxes', $sandboxes );

		return array(
			'sandbox_id' => $sandbox_id,
			'name'       => $name,
			'data'       => $test_data,
		);
	}

	/**
	 * Create test data for sandbox.
	 *
	 * @param string $sandbox_id Sandbox ID.
	 * @return array|WP_Error Created data or error.
	 */
	private function create_test_data( string $sandbox_id ): array {
		$data = array(
			'services' => array(),
			'staff'    => array(),
			'bookings' => array(),
			'extras'   => array(),
		);

		// Create test services.
		for ( $i = 1; $i <= 3; $i++ ) {
			$service_id = wp_insert_post(
				array(
					'post_type'   => 'bkx_base',
					'post_title'  => sprintf( 'Test Service %d [%s]', $i, $sandbox_id ),
					'post_status' => 'publish',
					'meta_input'  => array(
						'_sandbox_id'  => $sandbox_id,
						'base_price'   => rand( 50, 200 ),
						'base_time'    => array( 30, 60, 90 )[ $i - 1 ],
						'base_desc'    => 'This is a test service for the sandbox environment.',
					),
				)
			);

			if ( ! is_wp_error( $service_id ) ) {
				$data['services'][] = $service_id;
			}
		}

		// Create test staff.
		for ( $i = 1; $i <= 2; $i++ ) {
			$staff_id = wp_insert_post(
				array(
					'post_type'   => 'bkx_seat',
					'post_title'  => sprintf( 'Test Staff %d [%s]', $i, $sandbox_id ),
					'post_status' => 'publish',
					'meta_input'  => array(
						'_sandbox_id' => $sandbox_id,
						'seat_email'  => sprintf( 'staff%d@example.com', $i ),
						'seat_phone'  => '+1234567890',
					),
				)
			);

			if ( ! is_wp_error( $staff_id ) ) {
				$data['staff'][] = $staff_id;
			}
		}

		// Create test extras.
		$extra_id = wp_insert_post(
			array(
				'post_type'   => 'bkx_addition',
				'post_title'  => sprintf( 'Test Extra [%s]', $sandbox_id ),
				'post_status' => 'publish',
				'meta_input'  => array(
					'_sandbox_id'   => $sandbox_id,
					'addition_price' => 25,
				),
			)
		);

		if ( ! is_wp_error( $extra_id ) ) {
			$data['extras'][] = $extra_id;
		}

		// Create test bookings.
		$statuses = array( 'bkx-pending', 'bkx-ack', 'bkx-completed' );

		for ( $i = 0; $i < 5; $i++ ) {
			$booking_date = gmdate( 'Y-m-d', strtotime( '+' . ( $i + 1 ) . ' days' ) );
			$booking_id   = wp_insert_post(
				array(
					'post_type'   => 'bkx_booking',
					'post_title'  => sprintf( 'Test Booking %d [%s]', $i + 1, $sandbox_id ),
					'post_status' => $statuses[ $i % 3 ],
					'meta_input'  => array(
						'_sandbox_id'    => $sandbox_id,
						'booking_date'   => $booking_date,
						'booking_time'   => sprintf( '%02d:00:00', 9 + $i ),
						'base_id'        => $data['services'][ $i % count( $data['services'] ) ] ?? 0,
						'seat_id'        => $data['staff'][ $i % count( $data['staff'] ) ] ?? 0,
						'customer_name'  => sprintf( 'Test Customer %d', $i + 1 ),
						'customer_email' => sprintf( 'customer%d@example.com', $i + 1 ),
						'customer_phone' => '+1234567890',
						'booking_total'  => rand( 50, 300 ),
					),
				)
			);

			if ( ! is_wp_error( $booking_id ) ) {
				$data['bookings'][] = $booking_id;
			}
		}

		return $data;
	}

	/**
	 * Delete a sandbox and its data.
	 *
	 * @param string $sandbox_id Sandbox ID.
	 * @return bool True on success.
	 */
	public function delete( string $sandbox_id ): bool {
		$sandboxes = get_option( 'bkx_sandboxes', array() );

		if ( ! isset( $sandboxes[ $sandbox_id ] ) ) {
			return false;
		}

		$sandbox = $sandboxes[ $sandbox_id ];

		// Delete all sandbox posts.
		$post_types = array( 'bkx_booking', 'bkx_base', 'bkx_seat', 'bkx_addition' );

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'meta_key'       => '_sandbox_id',
					'meta_value'     => $sandbox_id,
				)
			);

			foreach ( $posts as $post ) {
				wp_delete_post( $post->ID, true );
			}
		}

		// Remove sandbox record.
		unset( $sandboxes[ $sandbox_id ] );
		update_option( 'bkx_sandboxes', $sandboxes );

		return true;
	}

	/**
	 * Get all sandboxes.
	 *
	 * @return array Sandboxes.
	 */
	public function get_all(): array {
		return get_option( 'bkx_sandboxes', array() );
	}

	/**
	 * Execute code in sandbox context.
	 *
	 * @param string $code Code to execute.
	 * @return array Execution result.
	 */
	public function execute_code( string $code ): array {
		// Security: Only allow in development/debug mode.
		$settings = get_option( 'bkx_developer_sdk_settings', array() );

		if ( empty( $settings['debug_mode'] ) ) {
			return array(
				'success' => false,
				'output'  => '',
				'error'   => __( 'Debug mode must be enabled to execute code.', 'bkx-developer-sdk' ),
			);
		}

		// Disallowed functions.
		$disallowed = array(
			'eval',
			'exec',
			'shell_exec',
			'system',
			'passthru',
			'popen',
			'proc_open',
			'pcntl_exec',
			'file_put_contents',
			'file_get_contents',
			'fwrite',
			'fopen',
			'unlink',
			'rmdir',
			'mkdir',
			'chmod',
			'chown',
			'curl_exec',
			'curl_multi_exec',
		);

		foreach ( $disallowed as $func ) {
			if ( stripos( $code, $func ) !== false ) {
				return array(
					'success' => false,
					'output'  => '',
					'error'   => sprintf(
						/* translators: %s: function name */
						__( 'Function "%s" is not allowed.', 'bkx-developer-sdk' ),
						$func
					),
				);
			}
		}

		// Capture output.
		ob_start();
		$error = null;

		try {
			// Set error handler.
			set_error_handler(
				function ( $errno, $errstr, $errfile, $errline ) use ( &$error ) {
					$error = array(
						'message' => $errstr,
						'file'    => $errfile,
						'line'    => $errline,
					);
					return true;
				}
			);

			// Execute in isolated scope.
			$result = $this->execute_isolated( $code );

			restore_error_handler();

		} catch ( \Throwable $e ) {
			restore_error_handler();
			$error = array(
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			);
		}

		$output = ob_get_clean();

		if ( $error ) {
			return array(
				'success' => false,
				'output'  => $output,
				'error'   => $error['message'],
				'details' => $error,
			);
		}

		return array(
			'success' => true,
			'output'  => $output,
			'result'  => isset( $result ) ? $this->format_result( $result ) : null,
		);
	}

	/**
	 * Execute code in isolated scope.
	 *
	 * @param string $code Code to execute.
	 * @return mixed Result.
	 */
	private function execute_isolated( string $code ) {
		// Remove opening PHP tag if present.
		$code = preg_replace( '/^<\?php\s*/', '', $code );

		// Wrap in function to isolate scope.
		$wrapper = function () use ( $code ) {
			return eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		};

		return $wrapper();
	}

	/**
	 * Format result for display.
	 *
	 * @param mixed $result Result value.
	 * @return string Formatted result.
	 */
	private function format_result( $result ): string {
		if ( is_null( $result ) ) {
			return 'null';
		}

		if ( is_bool( $result ) ) {
			return $result ? 'true' : 'false';
		}

		if ( is_string( $result ) ) {
			return '"' . $result . '"';
		}

		if ( is_array( $result ) || is_object( $result ) ) {
			return wp_json_encode( $result, JSON_PRETTY_PRINT );
		}

		return (string) $result;
	}

	/**
	 * Cleanup expired sandboxes.
	 */
	public function cleanup_expired(): void {
		$settings  = get_option( 'bkx_developer_sdk_settings', array() );
		$retention = $settings['test_data_retention'] ?? 7;

		$sandboxes = $this->get_all();
		$cutoff    = strtotime( "-{$retention} days" );

		foreach ( $sandboxes as $sandbox_id => $sandbox ) {
			$created = strtotime( $sandbox['created_at'] );

			if ( $created < $cutoff ) {
				$this->delete( $sandbox_id );
			}
		}
	}
}
