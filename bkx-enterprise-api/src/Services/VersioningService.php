<?php
/**
 * API Versioning Service.
 *
 * @package BookingX\EnterpriseAPI\Services
 */

namespace BookingX\EnterpriseAPI\Services;

defined( 'ABSPATH' ) || exit;

/**
 * VersioningService class.
 */
class VersioningService {

	/**
	 * Supported API versions.
	 *
	 * @var array
	 */
	private $versions = array( 'v1', 'v2' );

	/**
	 * Default version.
	 *
	 * @var string
	 */
	private $default_version = 'v1';

	/**
	 * Current version for request.
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * Handle API version.
	 *
	 * @param mixed            $response Response.
	 * @param \WP_REST_Server  $handler  Handler.
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public function handle_version( $response, $handler, $request ) {
		// Skip for non-BookingX endpoints.
		$route = $request->get_route();
		if ( strpos( $route, '/bookingx/' ) === false ) {
			return $response;
		}

		// Get requested version.
		$version = $this->get_requested_version( $request );

		// Validate version.
		if ( ! in_array( $version, $this->versions, true ) ) {
			return new \WP_Error(
				'invalid_api_version',
				sprintf(
					/* translators: %s: comma-separated list of versions */
					__( 'Invalid API version. Supported versions: %s', 'bkx-enterprise-api' ),
					implode( ', ', $this->versions )
				),
				array( 'status' => 400 )
			);
		}

		// Check deprecation.
		if ( $this->is_version_deprecated( $version ) ) {
			add_filter( 'rest_post_dispatch', function( $response ) use ( $version ) {
				if ( $response instanceof \WP_REST_Response ) {
					$response->header(
						'X-API-Deprecation-Warning',
						sprintf(
							/* translators: %s: deprecated version */
							__( 'API version %s is deprecated and will be removed soon.', 'bkx-enterprise-api' ),
							$version
						)
					);
				}
				return $response;
			} );
		}

		$this->current_version = $version;

		// Add version header to response.
		add_filter( 'rest_post_dispatch', function( $response ) use ( $version ) {
			if ( $response instanceof \WP_REST_Response ) {
				$response->header( 'X-API-Version', $version );
			}
			return $response;
		} );

		return $response;
	}

	/**
	 * Get requested version.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return string
	 */
	private function get_requested_version( $request ) {
		// Check header.
		if ( isset( $_SERVER['HTTP_X_API_VERSION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_API_VERSION'] ) );
		}

		// Check Accept header.
		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );
			if ( preg_match( '/application\/vnd\.bookingx\.(v\d+)\+json/', $accept, $matches ) ) {
				return $matches[1];
			}
		}

		// Check query parameter.
		if ( $request->get_param( 'api_version' ) ) {
			return sanitize_text_field( $request->get_param( 'api_version' ) );
		}

		// Extract from route.
		$route = $request->get_route();
		if ( preg_match( '/\/bookingx\/(v\d+)\//', $route, $matches ) ) {
			return $matches[1];
		}

		return $this->default_version;
	}

	/**
	 * Check if version is deprecated.
	 *
	 * @param string $version Version string.
	 * @return bool
	 */
	private function is_version_deprecated( $version ) {
		$deprecated = array(); // Add deprecated versions here.
		return in_array( $version, $deprecated, true );
	}

	/**
	 * Get current version.
	 *
	 * @return string
	 */
	public function get_current_version() {
		return $this->current_version ?: $this->default_version;
	}

	/**
	 * Get supported versions.
	 *
	 * @return array
	 */
	public function get_supported_versions() {
		return $this->versions;
	}

	/**
	 * Get default version.
	 *
	 * @return string
	 */
	public function get_default_version() {
		return $this->default_version;
	}

	/**
	 * Transform response for version.
	 *
	 * @param array  $data    Response data.
	 * @param string $version API version.
	 * @param string $type    Resource type.
	 * @return array
	 */
	public function transform_response( $data, $version, $type ) {
		$method = "transform_{$type}_{$version}";

		if ( method_exists( $this, $method ) ) {
			return $this->$method( $data );
		}

		return $data;
	}

	/**
	 * Transform booking for v1.
	 *
	 * @param array $data Booking data.
	 * @return array
	 */
	protected function transform_booking_v1( $data ) {
		// v1 format.
		return array(
			'id'           => $data['id'],
			'status'       => $data['status'],
			'customer'     => array(
				'name'  => $data['customer_name'],
				'email' => $data['customer_email'],
				'phone' => $data['customer_phone'],
			),
			'service'      => array(
				'id'   => $data['service_id'],
				'name' => $data['service_name'],
			),
			'staff'        => array(
				'id'   => $data['staff_id'],
				'name' => $data['staff_name'],
			),
			'datetime'     => $data['booking_datetime'],
			'duration'     => $data['duration'],
			'total'        => $data['total'],
			'notes'        => $data['notes'],
			'created_at'   => $data['created_at'],
			'updated_at'   => $data['updated_at'],
		);
	}

	/**
	 * Transform booking for v2.
	 *
	 * @param array $data Booking data.
	 * @return array
	 */
	protected function transform_booking_v2( $data ) {
		// v2 format with expanded structure.
		return array(
			'id'         => $data['id'],
			'type'       => 'booking',
			'attributes' => array(
				'status'     => $data['status'],
				'datetime'   => $data['booking_datetime'],
				'duration'   => $data['duration'],
				'total'      => array(
					'amount'   => $data['total'],
					'currency' => $data['currency'] ?? 'USD',
				),
				'notes'      => $data['notes'],
				'created_at' => $data['created_at'],
				'updated_at' => $data['updated_at'],
			),
			'relationships' => array(
				'customer' => array(
					'data' => array(
						'type' => 'customer',
						'id'   => $data['customer_id'],
					),
				),
				'service' => array(
					'data' => array(
						'type' => 'service',
						'id'   => $data['service_id'],
					),
				),
				'staff' => array(
					'data' => array(
						'type' => 'staff',
						'id'   => $data['staff_id'],
					),
				),
			),
			'included' => array(
				array(
					'type'       => 'customer',
					'id'         => $data['customer_id'],
					'attributes' => array(
						'name'  => $data['customer_name'],
						'email' => $data['customer_email'],
						'phone' => $data['customer_phone'],
					),
				),
			),
		);
	}

	/**
	 * Get version changelog.
	 *
	 * @return array
	 */
	public function get_changelog() {
		return array(
			'v2' => array(
				'released'    => '2024-01-01',
				'description' => __( 'JSON:API compliant responses, expanded relationships.', 'bkx-enterprise-api' ),
				'changes'     => array(
					__( 'Response format follows JSON:API specification', 'bkx-enterprise-api' ),
					__( 'Added relationships and included resources', 'bkx-enterprise-api' ),
					__( 'Improved error response format', 'bkx-enterprise-api' ),
				),
			),
			'v1' => array(
				'released'    => '2023-01-01',
				'description' => __( 'Initial API version with core booking functionality.', 'bkx-enterprise-api' ),
				'changes'     => array(
					__( 'Bookings CRUD operations', 'bkx-enterprise-api' ),
					__( 'Services and staff management', 'bkx-enterprise-api' ),
					__( 'Availability checking', 'bkx-enterprise-api' ),
				),
			),
		);
	}
}
