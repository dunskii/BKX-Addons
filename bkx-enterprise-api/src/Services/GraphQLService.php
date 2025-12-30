<?php
/**
 * GraphQL Service.
 *
 * @package BookingX\EnterpriseAPI\Services
 */

namespace BookingX\EnterpriseAPI\Services;

defined( 'ABSPATH' ) || exit;

/**
 * GraphQLService class.
 */
class GraphQLService {

	/**
	 * Schema types.
	 *
	 * @var array
	 */
	private $types = array();

	/**
	 * Query resolvers.
	 *
	 * @var array
	 */
	private $resolvers = array();

	/**
	 * Mutation resolvers.
	 *
	 * @var array
	 */
	private $mutations = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_types();
		$this->register_resolvers();
		$this->register_mutations();
	}

	/**
	 * Register GraphQL endpoint.
	 */
	public function register_endpoint() {
		add_action( 'template_redirect', array( $this, 'handle_request' ) );
	}

	/**
	 * Handle GraphQL request.
	 */
	public function handle_request() {
		if ( ! get_query_var( 'bkx_graphql' ) ) {
			return;
		}

		$settings = get_option( 'bkx_enterprise_api_settings', array() );
		if ( empty( $settings['enable_graphql'] ) ) {
			wp_send_json_error( array( 'message' => __( 'GraphQL is disabled.', 'bkx-enterprise-api' ) ), 403 );
		}

		// Handle CORS.
		$this->handle_cors();

		// OPTIONS request.
		if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			status_header( 200 );
			exit;
		}

		// Get request body.
		$raw_body = file_get_contents( 'php://input' );
		$body     = json_decode( $raw_body, true );

		if ( ! $body || empty( $body['query'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid GraphQL request.', 'bkx-enterprise-api' ) ), 400 );
		}

		$query     = $body['query'];
		$variables = $body['variables'] ?? array();

		try {
			$result = $this->execute( $query, $variables );
			wp_send_json( $result );
		} catch ( \Exception $e ) {
			wp_send_json( array(
				'errors' => array(
					array( 'message' => $e->getMessage() ),
				),
			) );
		}
	}

	/**
	 * Handle CORS headers.
	 */
	private function handle_cors() {
		$settings = get_option( 'bkx_enterprise_api_settings', array() );
		$origins  = $settings['cors_allowed_origins'] ?? array( '*' );

		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '*';

		if ( in_array( '*', $origins, true ) || in_array( $origin, $origins, true ) ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
		}

		header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key' );
		header( 'Content-Type: application/json' );
	}

	/**
	 * Register GraphQL types.
	 */
	private function register_types() {
		$this->types = array(
			'Booking' => array(
				'id'             => 'ID!',
				'status'         => 'String!',
				'customerName'   => 'String',
				'customerEmail'  => 'String',
				'customerPhone'  => 'String',
				'service'        => 'Service',
				'staff'          => 'Staff',
				'bookingDate'    => 'String!',
				'bookingTime'    => 'String!',
				'duration'       => 'Int!',
				'total'          => 'Float!',
				'notes'          => 'String',
				'createdAt'      => 'String!',
				'updatedAt'      => 'String',
			),
			'Service' => array(
				'id'          => 'ID!',
				'name'        => 'String!',
				'description' => 'String',
				'duration'    => 'Int!',
				'price'       => 'Float!',
				'image'       => 'String',
			),
			'Staff' => array(
				'id'          => 'ID!',
				'name'        => 'String!',
				'email'       => 'String',
				'phone'       => 'String',
				'image'       => 'String',
				'services'    => '[Service]',
			),
			'AvailabilitySlot' => array(
				'date'      => 'String!',
				'time'      => 'String!',
				'available' => 'Boolean!',
			),
			'BookingConnection' => array(
				'edges' => '[BookingEdge]',
				'pageInfo' => 'PageInfo',
				'totalCount' => 'Int',
			),
			'BookingEdge' => array(
				'node' => 'Booking',
				'cursor' => 'String',
			),
			'PageInfo' => array(
				'hasNextPage' => 'Boolean!',
				'hasPreviousPage' => 'Boolean!',
				'startCursor' => 'String',
				'endCursor' => 'String',
			),
		);
	}

	/**
	 * Register query resolvers.
	 */
	private function register_resolvers() {
		$this->resolvers = array(
			'booking'   => array( $this, 'resolve_booking' ),
			'bookings'  => array( $this, 'resolve_bookings' ),
			'service'   => array( $this, 'resolve_service' ),
			'services'  => array( $this, 'resolve_services' ),
			'staff'     => array( $this, 'resolve_staff' ),
			'staffList' => array( $this, 'resolve_staff_list' ),
			'availability' => array( $this, 'resolve_availability' ),
		);
	}

	/**
	 * Register mutation resolvers.
	 */
	private function register_mutations() {
		$this->mutations = array(
			'createBooking' => array( $this, 'mutate_create_booking' ),
			'updateBooking' => array( $this, 'mutate_update_booking' ),
			'cancelBooking' => array( $this, 'mutate_cancel_booking' ),
		);
	}

	/**
	 * Execute GraphQL query.
	 *
	 * @param string $query     Query string.
	 * @param array  $variables Variables.
	 * @return array
	 */
	public function execute( $query, $variables = array() ) {
		// Parse query.
		$parsed = $this->parse_query( $query );

		if ( isset( $parsed['errors'] ) ) {
			return $parsed;
		}

		$data = array();

		// Execute queries.
		foreach ( $parsed['queries'] as $name => $field ) {
			$resolver = $this->resolvers[ $field['name'] ] ?? null;
			if ( $resolver ) {
				$args = $this->resolve_variables( $field['args'], $variables );
				$data[ $name ] = call_user_func( $resolver, $args, $field['fields'] );
			}
		}

		// Execute mutations.
		foreach ( $parsed['mutations'] as $name => $field ) {
			$mutator = $this->mutations[ $field['name'] ] ?? null;
			if ( $mutator ) {
				$args = $this->resolve_variables( $field['args'], $variables );
				$data[ $name ] = call_user_func( $mutator, $args, $field['fields'] );
			}
		}

		return array( 'data' => $data );
	}

	/**
	 * Parse GraphQL query (simplified parser).
	 *
	 * @param string $query Query string.
	 * @return array
	 */
	private function parse_query( $query ) {
		$result = array(
			'queries'   => array(),
			'mutations' => array(),
		);

		// Determine operation type.
		$is_mutation = strpos( $query, 'mutation' ) !== false;

		// Extract fields (simplified).
		preg_match_all( '/(\w+)\s*(?:\(([^)]*)\))?\s*\{([^}]*)\}/', $query, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$field_name = $match[1];
			$args_str   = $match[2] ?? '';
			$fields_str = $match[3] ?? '';

			// Skip operation keywords.
			if ( in_array( $field_name, array( 'query', 'mutation' ), true ) ) {
				continue;
			}

			$field = array(
				'name'   => $field_name,
				'args'   => $this->parse_args( $args_str ),
				'fields' => $this->parse_fields( $fields_str ),
			);

			if ( $is_mutation ) {
				$result['mutations'][ $field_name ] = $field;
			} else {
				$result['queries'][ $field_name ] = $field;
			}
		}

		return $result;
	}

	/**
	 * Parse arguments string.
	 *
	 * @param string $str Arguments string.
	 * @return array
	 */
	private function parse_args( $str ) {
		$args = array();
		if ( empty( $str ) ) {
			return $args;
		}

		preg_match_all( '/(\w+)\s*:\s*(\$?\w+|"[^"]*"|\d+)/', $str, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$key   = $match[1];
			$value = trim( $match[2], '"' );

			// Check for variable reference.
			if ( strpos( $value, '$' ) === 0 ) {
				$args[ $key ] = array( 'var' => substr( $value, 1 ) );
			} else {
				$args[ $key ] = is_numeric( $value ) ? (int) $value : $value;
			}
		}

		return $args;
	}

	/**
	 * Parse fields string.
	 *
	 * @param string $str Fields string.
	 * @return array
	 */
	private function parse_fields( $str ) {
		return array_filter( array_map( 'trim', preg_split( '/[\s,]+/', $str ) ) );
	}

	/**
	 * Resolve variables in arguments.
	 *
	 * @param array $args      Arguments.
	 * @param array $variables Variables.
	 * @return array
	 */
	private function resolve_variables( $args, $variables ) {
		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) && isset( $value['var'] ) ) {
				$args[ $key ] = $variables[ $value['var'] ] ?? null;
			}
		}
		return $args;
	}

	/**
	 * Resolve booking query.
	 *
	 * @param array $args   Arguments.
	 * @param array $fields Fields.
	 * @return array|null
	 */
	public function resolve_booking( $args, $fields ) {
		$booking_id = $args['id'] ?? 0;

		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return null;
		}

		return $this->format_booking( $booking, $fields );
	}

	/**
	 * Resolve bookings query.
	 *
	 * @param array $args   Arguments.
	 * @param array $fields Fields.
	 * @return array
	 */
	public function resolve_bookings( $args, $fields ) {
		$query_args = array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => $args['first'] ?? 20,
			'post_status'    => 'any',
		);

		if ( ! empty( $args['status'] ) ) {
			$query_args['post_status'] = $args['status'];
		}

		if ( ! empty( $args['after'] ) ) {
			$query_args['offset'] = (int) base64_decode( $args['after'] );
		}

		$query = new \WP_Query( $query_args );

		$edges = array();
		foreach ( $query->posts as $booking ) {
			$edges[] = array(
				'node'   => $this->format_booking( $booking, $fields ),
				'cursor' => base64_encode( (string) $booking->ID ),
			);
		}

		return array(
			'edges'      => $edges,
			'totalCount' => $query->found_posts,
			'pageInfo'   => array(
				'hasNextPage'     => $query->max_num_pages > 1,
				'hasPreviousPage' => ! empty( $args['after'] ),
				'startCursor'     => ! empty( $edges ) ? $edges[0]['cursor'] : null,
				'endCursor'       => ! empty( $edges ) ? end( $edges )['cursor'] : null,
			),
		);
	}

	/**
	 * Resolve service query.
	 *
	 * @param array $args   Arguments.
	 * @param array $fields Fields.
	 * @return array|null
	 */
	public function resolve_service( $args, $fields ) {
		$service_id = $args['id'] ?? 0;

		$service = get_post( $service_id );
		if ( ! $service || 'bkx_base' !== $service->post_type ) {
			return null;
		}

		return $this->format_service( $service, $fields );
	}

	/**
	 * Resolve services query.
	 *
	 * @param array $args   Arguments.
	 * @param array $fields Fields.
	 * @return array
	 */
	public function resolve_services( $args, $fields ) {
		$query = new \WP_Query( array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => $args['first'] ?? 50,
			'post_status'    => 'publish',
		) );

		$services = array();
		foreach ( $query->posts as $service ) {
			$services[] = $this->format_service( $service, $fields );
		}

		return $services;
	}

	/**
	 * Resolve staff query.
	 *
	 * @param array $args   Arguments.
	 * @param array $fields Fields.
	 * @return array|null
	 */
	public function resolve_staff( $args, $fields ) {
		$staff_id = $args['id'] ?? 0;

		$staff = get_post( $staff_id );
		if ( ! $staff || 'bkx_seat' !== $staff->post_type ) {
			return null;
		}

		return $this->format_staff( $staff, $fields );
	}

	/**
	 * Resolve staff list query.
	 *
	 * @param array $args   Arguments.
	 * @param array $fields Fields.
	 * @return array
	 */
	public function resolve_staff_list( $args, $fields ) {
		$query = new \WP_Query( array(
			'post_type'      => 'bkx_seat',
			'posts_per_page' => $args['first'] ?? 50,
			'post_status'    => 'publish',
		) );

		$staff_list = array();
		foreach ( $query->posts as $staff ) {
			$staff_list[] = $this->format_staff( $staff, $fields );
		}

		return $staff_list;
	}

	/**
	 * Resolve availability query.
	 *
	 * @param array $args   Arguments.
	 * @param array $fields Fields.
	 * @return array
	 */
	public function resolve_availability( $args, $fields ) {
		$service_id = $args['serviceId'] ?? 0;
		$staff_id   = $args['staffId'] ?? 0;
		$date       = $args['date'] ?? gmdate( 'Y-m-d' );

		// Get availability slots (simplified).
		$slots = array();

		// Generate time slots.
		$start = strtotime( $date . ' 09:00:00' );
		$end   = strtotime( $date . ' 17:00:00' );

		for ( $time = $start; $time < $end; $time += 1800 ) { // 30-minute intervals.
			$slots[] = array(
				'date'      => $date,
				'time'      => gmdate( 'H:i', $time ),
				'available' => true, // Simplified; actual implementation would check bookings.
			);
		}

		return $slots;
	}

	/**
	 * Create booking mutation.
	 *
	 * @param array $args   Arguments.
	 * @param array $fields Fields.
	 * @return array
	 */
	public function mutate_create_booking( $args, $fields ) {
		$input = $args['input'] ?? array();

		$booking_id = wp_insert_post( array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-pending',
			'post_title'  => sprintf(
				'Booking - %s',
				sanitize_text_field( $input['customerName'] ?? '' )
			),
		) );

		if ( is_wp_error( $booking_id ) ) {
			return array( 'error' => $booking_id->get_error_message() );
		}

		// Save meta.
		if ( ! empty( $input['customerName'] ) ) {
			update_post_meta( $booking_id, 'customer_name', sanitize_text_field( $input['customerName'] ) );
		}
		if ( ! empty( $input['customerEmail'] ) ) {
			update_post_meta( $booking_id, 'customer_email', sanitize_email( $input['customerEmail'] ) );
		}
		if ( ! empty( $input['serviceId'] ) ) {
			update_post_meta( $booking_id, 'base_id', absint( $input['serviceId'] ) );
		}
		if ( ! empty( $input['staffId'] ) ) {
			update_post_meta( $booking_id, 'seat_id', absint( $input['staffId'] ) );
		}
		if ( ! empty( $input['date'] ) ) {
			update_post_meta( $booking_id, 'booking_date', sanitize_text_field( $input['date'] ) );
		}
		if ( ! empty( $input['time'] ) ) {
			update_post_meta( $booking_id, 'booking_time', sanitize_text_field( $input['time'] ) );
		}

		return array(
			'booking' => $this->format_booking( get_post( $booking_id ), $fields ),
		);
	}

	/**
	 * Update booking mutation.
	 *
	 * @param array $args   Arguments.
	 * @param array $fields Fields.
	 * @return array
	 */
	public function mutate_update_booking( $args, $fields ) {
		$booking_id = $args['id'] ?? 0;
		$input      = $args['input'] ?? array();

		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array( 'error' => __( 'Booking not found.', 'bkx-enterprise-api' ) );
		}

		// Update status.
		if ( ! empty( $input['status'] ) ) {
			wp_update_post( array(
				'ID'          => $booking_id,
				'post_status' => 'bkx-' . sanitize_text_field( $input['status'] ),
			) );
		}

		// Update meta.
		if ( ! empty( $input['notes'] ) ) {
			update_post_meta( $booking_id, 'booking_notes', sanitize_textarea_field( $input['notes'] ) );
		}

		return array(
			'booking' => $this->format_booking( get_post( $booking_id ), $fields ),
		);
	}

	/**
	 * Cancel booking mutation.
	 *
	 * @param array $args   Arguments.
	 * @param array $fields Fields.
	 * @return array
	 */
	public function mutate_cancel_booking( $args, $fields ) {
		$booking_id = $args['id'] ?? 0;

		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array( 'error' => __( 'Booking not found.', 'bkx-enterprise-api' ) );
		}

		wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-cancelled',
		) );

		return array(
			'success' => true,
			'booking' => $this->format_booking( get_post( $booking_id ), $fields ),
		);
	}

	/**
	 * Format booking for response.
	 *
	 * @param \WP_Post $booking Booking post.
	 * @param array    $fields  Requested fields.
	 * @return array
	 */
	private function format_booking( $booking, $fields ) {
		return array(
			'id'            => $booking->ID,
			'status'        => str_replace( 'bkx-', '', $booking->post_status ),
			'customerName'  => get_post_meta( $booking->ID, 'customer_name', true ),
			'customerEmail' => get_post_meta( $booking->ID, 'customer_email', true ),
			'customerPhone' => get_post_meta( $booking->ID, 'customer_phone', true ),
			'bookingDate'   => get_post_meta( $booking->ID, 'booking_date', true ),
			'bookingTime'   => get_post_meta( $booking->ID, 'booking_time', true ),
			'duration'      => (int) get_post_meta( $booking->ID, 'duration', true ),
			'total'         => (float) get_post_meta( $booking->ID, 'total_price', true ),
			'notes'         => get_post_meta( $booking->ID, 'booking_notes', true ),
			'createdAt'     => $booking->post_date_gmt,
			'updatedAt'     => $booking->post_modified_gmt,
		);
	}

	/**
	 * Format service for response.
	 *
	 * @param \WP_Post $service Service post.
	 * @param array    $fields  Requested fields.
	 * @return array
	 */
	private function format_service( $service, $fields ) {
		return array(
			'id'          => $service->ID,
			'name'        => $service->post_title,
			'description' => $service->post_content,
			'duration'    => (int) get_post_meta( $service->ID, 'base_time', true ),
			'price'       => (float) get_post_meta( $service->ID, 'base_price', true ),
			'image'       => get_the_post_thumbnail_url( $service->ID, 'medium' ),
		);
	}

	/**
	 * Format staff for response.
	 *
	 * @param \WP_Post $staff Staff post.
	 * @param array    $fields Requested fields.
	 * @return array
	 */
	private function format_staff( $staff, $fields ) {
		return array(
			'id'    => $staff->ID,
			'name'  => $staff->post_title,
			'email' => get_post_meta( $staff->ID, 'seat_email', true ),
			'phone' => get_post_meta( $staff->ID, 'seat_phone', true ),
			'image' => get_the_post_thumbnail_url( $staff->ID, 'medium' ),
		);
	}
}
