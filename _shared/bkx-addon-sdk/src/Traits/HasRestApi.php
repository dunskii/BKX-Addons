<?php
/**
 * REST API Trait
 *
 * Provides REST API registration and handling for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Traits
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Traits;

/**
 * Trait for REST API functionality.
 *
 * @since 1.0.0
 */
trait HasRestApi {

    /**
     * REST API namespace.
     *
     * @var string
     */
    protected string $rest_namespace = 'bookingx/v1';

    /**
     * Registered routes.
     *
     * @var array
     */
    protected array $registered_routes = [];

    /**
     * Register REST API routes.
     *
     * Call this method from init_rest_api().
     *
     * @since 1.0.0
     * @return void
     */
    protected function register_rest_routes(): void {
        $routes = $this->get_rest_routes();

        foreach ( $routes as $route => $config ) {
            $this->register_route( $route, $config );
        }
    }

    /**
     * Register a single REST route.
     *
     * @since 1.0.0
     * @param string $route  Route pattern.
     * @param array  $config Route configuration.
     * @return void
     */
    protected function register_route( string $route, array $config ): void {
        $args = [];

        // Support multiple methods per route
        if ( isset( $config['methods'] ) ) {
            $args[] = $this->build_route_args( $config );
        } else {
            // Multiple endpoints for same route
            foreach ( $config as $method_config ) {
                $args[] = $this->build_route_args( $method_config );
            }
        }

        register_rest_route( $this->rest_namespace, $route, $args );

        $this->registered_routes[] = $route;
    }

    /**
     * Build route arguments.
     *
     * @since 1.0.0
     * @param array $config Route configuration.
     * @return array
     */
    protected function build_route_args( array $config ): array {
        $args = [
            'methods'             => $config['methods'] ?? \WP_REST_Server::READABLE,
            'callback'            => $config['callback'],
            'permission_callback' => $config['permission_callback'] ?? [ $this, 'check_permission' ],
        ];

        if ( isset( $config['args'] ) ) {
            $args['args'] = $this->build_route_schema( $config['args'] );
        }

        return $args;
    }

    /**
     * Build route argument schema.
     *
     * @since 1.0.0
     * @param array $args Argument definitions.
     * @return array
     */
    protected function build_route_schema( array $args ): array {
        $schema = [];

        foreach ( $args as $name => $config ) {
            $schema[ $name ] = wp_parse_args( $config, [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => 'rest_validate_request_arg',
            ] );
        }

        return $schema;
    }

    /**
     * Default permission callback.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Request object.
     * @return bool|\WP_Error
     */
    public function check_permission( \WP_REST_Request $request ) {
        // Allow read operations for anyone
        if ( $request->get_method() === 'GET' ) {
            return true;
        }

        // Require authentication for write operations
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_not_logged_in',
                __( 'You must be logged in to perform this action.', 'bkx-addon-sdk' ),
                [ 'status' => 401 ]
            );
        }

        // Check for admin capability by default
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to perform this action.', 'bkx-addon-sdk' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Permission callback for public endpoints.
     *
     * @since 1.0.0
     * @return bool
     */
    public function public_permission(): bool {
        return true;
    }

    /**
     * Permission callback for logged-in users.
     *
     * @since 1.0.0
     * @return bool|\WP_Error
     */
    public function logged_in_permission() {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_not_logged_in',
                __( 'You must be logged in.', 'bkx-addon-sdk' ),
                [ 'status' => 401 ]
            );
        }

        return true;
    }

    /**
     * Permission callback for admins only.
     *
     * @since 1.0.0
     * @return bool|\WP_Error
     */
    public function admin_permission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have permission.', 'bkx-addon-sdk' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Create a success response.
     *
     * @since 1.0.0
     * @param mixed $data    Response data.
     * @param int   $status  HTTP status code.
     * @param array $headers Additional headers.
     * @return \WP_REST_Response
     */
    protected function success_response( $data = null, int $status = 200, array $headers = [] ): \WP_REST_Response {
        $response = new \WP_REST_Response( [
            'success' => true,
            'data'    => $data,
        ], $status );

        foreach ( $headers as $key => $value ) {
            $response->header( $key, $value );
        }

        return $response;
    }

    /**
     * Create an error response.
     *
     * @since 1.0.0
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     * @param array  $data    Additional error data.
     * @return \WP_Error
     */
    protected function error_response( string $code, string $message, int $status = 400, array $data = [] ): \WP_Error {
        return new \WP_Error( $code, $message, array_merge( [ 'status' => $status ], $data ) );
    }

    /**
     * Create a paginated response.
     *
     * @since 1.0.0
     * @param array $items       Items to return.
     * @param int   $total       Total items.
     * @param int   $page        Current page.
     * @param int   $per_page    Items per page.
     * @param array $extra_data  Additional data to include.
     * @return \WP_REST_Response
     */
    protected function paginated_response( array $items, int $total, int $page, int $per_page, array $extra_data = [] ): \WP_REST_Response {
        $max_pages = ceil( $total / $per_page );

        $data = array_merge( [
            'items'      => $items,
            'pagination' => [
                'total'       => $total,
                'total_pages' => $max_pages,
                'current'     => $page,
                'per_page'    => $per_page,
            ],
        ], $extra_data );

        $response = $this->success_response( $data );

        // Add pagination headers
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', $max_pages );

        return $response;
    }

    /**
     * Get pagination parameters from request.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Request object.
     * @return array
     */
    protected function get_pagination_params( \WP_REST_Request $request ): array {
        return [
            'page'     => max( 1, (int) $request->get_param( 'page' ) ?: 1 ),
            'per_page' => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 10 ) ),
            'offset'   => 0,
        ];
    }

    /**
     * Get sorting parameters from request.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request        Request object.
     * @param array            $allowed_fields Allowed sort fields.
     * @param string           $default_field  Default sort field.
     * @param string           $default_order  Default sort order.
     * @return array
     */
    protected function get_sort_params( \WP_REST_Request $request, array $allowed_fields, string $default_field = 'id', string $default_order = 'desc' ): array {
        $orderby = $request->get_param( 'orderby' ) ?: $default_field;
        $order   = strtolower( $request->get_param( 'order' ) ?: $default_order );

        if ( ! in_array( $orderby, $allowed_fields, true ) ) {
            $orderby = $default_field;
        }

        if ( ! in_array( $order, [ 'asc', 'desc' ], true ) ) {
            $order = $default_order;
        }

        return [
            'orderby' => $orderby,
            'order'   => $order,
        ];
    }

    /**
     * Validate and sanitize date parameter.
     *
     * @since 1.0.0
     * @param string $date    Date string.
     * @param string $default Default date.
     * @return string
     */
    protected function validate_date_param( string $date, string $default = '' ): string {
        if ( empty( $date ) ) {
            return $default;
        }

        $timestamp = strtotime( $date );

        if ( false === $timestamp ) {
            return $default;
        }

        return gmdate( 'Y-m-d', $timestamp );
    }

    /**
     * Get REST routes to register.
     *
     * Override in class to define routes.
     *
     * Example:
     * return [
     *     '/items' => [
     *         [
     *             'methods'  => 'GET',
     *             'callback' => [ $this, 'get_items' ],
     *             'args'     => [
     *                 'page' => [ 'type' => 'integer', 'default' => 1 ],
     *             ],
     *         ],
     *         [
     *             'methods'             => 'POST',
     *             'callback'            => [ $this, 'create_item' ],
     *             'permission_callback' => [ $this, 'admin_permission' ],
     *         ],
     *     ],
     *     '/items/(?P<id>\d+)' => [
     *         'methods'  => 'GET',
     *         'callback' => [ $this, 'get_item' ],
     *         'args'     => [
     *             'id' => [ 'type' => 'integer', 'required' => true ],
     *         ],
     *     ],
     * ];
     *
     * @since 1.0.0
     * @return array
     */
    protected function get_rest_routes(): array {
        return [];
    }
}
