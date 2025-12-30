<?php
/**
 * Main API Builder Addon class.
 *
 * @package BookingX\APIBuilder
 */

namespace BookingX\APIBuilder;

use BookingX\APIBuilder\Services\EndpointManager;
use BookingX\APIBuilder\Services\APIKeyManager;
use BookingX\APIBuilder\Services\RateLimiter;
use BookingX\APIBuilder\Services\RequestLogger;
use BookingX\APIBuilder\Services\DocumentationGenerator;
use BookingX\APIBuilder\Services\WebhookDispatcher;

defined( 'ABSPATH' ) || exit;

/**
 * APIBuilderAddon class.
 */
class APIBuilderAddon {

	/**
	 * Single instance.
	 *
	 * @var APIBuilderAddon
	 */
	private static $instance = null;

	/**
	 * Services.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Get instance.
	 *
	 * @return APIBuilderAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_settings();
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Load settings.
	 */
	private function load_settings() {
		$this->settings = get_option( 'bkx_api_builder_settings', array() );
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['endpoint_manager']  = new EndpointManager();
		$this->services['api_key_manager']   = new APIKeyManager();
		$this->services['rate_limiter']      = new RateLimiter();
		$this->services['request_logger']    = new RequestLogger();
		$this->services['doc_generator']     = new DocumentationGenerator();
		$this->services['webhook_dispatcher'] = new WebhookDispatcher();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// REST API hooks.
		add_action( 'rest_api_init', array( $this, 'register_custom_endpoints' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'pre_dispatch_handler' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'post_dispatch_handler' ), 10, 3 );

		// CORS headers.
		if ( ! empty( $this->settings['enable_cors'] ) ) {
			add_action( 'rest_api_init', array( $this, 'add_cors_headers' ), 15 );
		}

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_api_save_endpoint', array( $this, 'ajax_save_endpoint' ) );
		add_action( 'wp_ajax_bkx_api_delete_endpoint', array( $this, 'ajax_delete_endpoint' ) );
		add_action( 'wp_ajax_bkx_api_test_endpoint', array( $this, 'ajax_test_endpoint' ) );
		add_action( 'wp_ajax_bkx_api_generate_key', array( $this, 'ajax_generate_key' ) );
		add_action( 'wp_ajax_bkx_api_revoke_key', array( $this, 'ajax_revoke_key' ) );
		add_action( 'wp_ajax_bkx_api_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_api_export_docs', array( $this, 'ajax_export_docs' ) );
		add_action( 'wp_ajax_bkx_api_clear_logs', array( $this, 'ajax_clear_logs' ) );

		// Cron handlers.
		add_action( 'bkx_api_builder_cleanup', array( $this, 'cleanup_old_data' ) );

		// BookingX integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_api_builder', array( $this, 'render_settings_tab' ) );

		// BookingX events for webhooks.
		add_action( 'bkx_booking_created', array( $this, 'trigger_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_updated', array( $this, 'trigger_booking_updated' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'trigger_booking_cancelled' ), 10, 2 );
		add_action( 'bkx_payment_completed', array( $this, 'trigger_payment_completed' ), 10, 2 );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'API Builder', 'bkx-api-builder' ),
			__( 'API Builder', 'bkx-api-builder' ),
			'manage_options',
			'bkx-api-builder',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bkx_booking_page_bkx-api-builder' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-api-builder-admin',
			BKX_API_BUILDER_URL . 'assets/css/admin.css',
			array(),
			BKX_API_BUILDER_VERSION
		);

		wp_enqueue_script(
			'bkx-api-builder-admin',
			BKX_API_BUILDER_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			BKX_API_BUILDER_VERSION,
			true
		);

		// Code editor for JSON schemas.
		wp_enqueue_code_editor( array( 'type' => 'application/json' ) );

		wp_localize_script(
			'bkx-api-builder-admin',
			'bkxAPIBuilder',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'bkx_api_builder_nonce' ),
				'restUrl'   => rest_url(),
				'namespace' => $this->settings['api_namespace'] ?? 'bkx-custom/v1',
				'i18n'      => array(
					'saving'           => __( 'Saving...', 'bkx-api-builder' ),
					'saved'            => __( 'Saved successfully', 'bkx-api-builder' ),
					'error'            => __( 'An error occurred', 'bkx-api-builder' ),
					'confirmDelete'    => __( 'Are you sure you want to delete this endpoint?', 'bkx-api-builder' ),
					'confirmRevoke'    => __( 'Are you sure you want to revoke this API key?', 'bkx-api-builder' ),
					'testing'          => __( 'Testing endpoint...', 'bkx-api-builder' ),
					'generating'       => __( 'Generating API key...', 'bkx-api-builder' ),
					'copySuccess'      => __( 'Copied to clipboard', 'bkx-api-builder' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'endpoints';

		include BKX_API_BUILDER_PATH . 'templates/admin/page.php';
	}

	/**
	 * Register custom endpoints.
	 */
	public function register_custom_endpoints() {
		$endpoints = $this->services['endpoint_manager']->get_active_endpoints();

		foreach ( $endpoints as $endpoint ) {
			register_rest_route(
				$endpoint['namespace'],
				$endpoint['route'],
				array(
					'methods'             => $endpoint['method'],
					'callback'            => function ( $request ) use ( $endpoint ) {
						return $this->handle_custom_endpoint( $endpoint, $request );
					},
					'permission_callback' => function ( $request ) use ( $endpoint ) {
						return $this->check_endpoint_permission( $endpoint, $request );
					},
					'args'                => $this->get_endpoint_args( $endpoint ),
				)
			);
		}

		// Register documentation endpoint.
		if ( ! empty( $this->settings['enable_documentation'] ) ) {
			register_rest_route(
				$this->settings['api_namespace'] ?? 'bkx-custom/v1',
				'/docs',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_api_documentation' ),
					'permission_callback' => function () {
						if ( ! empty( $this->settings['documentation_public'] ) ) {
							return true;
						}
						return current_user_can( 'manage_options' );
					},
				)
			);
		}
	}

	/**
	 * Handle custom endpoint request.
	 *
	 * @param array            $endpoint Endpoint config.
	 * @param \WP_REST_Request $request  Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function handle_custom_endpoint( $endpoint, $request ) {
		$start_time = microtime( true );

		// Check cache first.
		if ( ! empty( $endpoint['cache_enabled'] ) && 'GET' === $endpoint['method'] ) {
			$cache_key = 'bkx_api_' . md5( wp_json_encode( array(
				'endpoint' => $endpoint['id'],
				'params'   => $request->get_params(),
			) ) );

			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return new \WP_REST_Response( $cached, 200 );
			}
		}

		$handler_type   = $endpoint['handler_type'];
		$handler_config = json_decode( $endpoint['handler_config'], true ) ?: array();

		$response = null;

		switch ( $handler_type ) {
			case 'query':
				$response = $this->handle_query_endpoint( $handler_config, $request );
				break;

			case 'action':
				$response = $this->handle_action_endpoint( $handler_config, $request );
				break;

			case 'callback':
				$response = $this->handle_callback_endpoint( $handler_config, $request );
				break;

			case 'proxy':
				$response = $this->handle_proxy_endpoint( $handler_config, $request );
				break;

			default:
				$response = new \WP_Error(
					'invalid_handler',
					__( 'Invalid endpoint handler type', 'bkx-api-builder' ),
					array( 'status' => 500 )
				);
		}

		// Cache successful GET responses.
		if ( ! empty( $endpoint['cache_enabled'] ) && 'GET' === $endpoint['method'] && ! is_wp_error( $response ) ) {
			set_transient( $cache_key, $response, $endpoint['cache_ttl'] );
		}

		// Log request.
		if ( ! empty( $this->settings['enable_logging'] ) ) {
			$this->services['request_logger']->log(
				$endpoint['id'],
				$request,
				$response,
				microtime( true ) - $start_time
			);
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle query-based endpoint.
	 *
	 * @param array            $config  Handler config.
	 * @param \WP_REST_Request $request Request object.
	 * @return array|\WP_Error
	 */
	private function handle_query_endpoint( $config, $request ) {
		global $wpdb;

		$post_type = $config['post_type'] ?? 'bkx_booking';
		$params    = $request->get_params();

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => min( absint( $params['per_page'] ?? 10 ), 100 ),
			'paged'          => absint( $params['page'] ?? 1 ),
			'post_status'    => $config['post_status'] ?? 'any',
		);

		// Apply filters from config.
		if ( ! empty( $config['meta_query'] ) ) {
			$args['meta_query'] = $this->build_meta_query( $config['meta_query'], $params );
		}

		if ( ! empty( $config['date_query'] ) && ! empty( $params['date_from'] ) ) {
			$args['date_query'] = array(
				array(
					'after'     => sanitize_text_field( $params['date_from'] ),
					'before'    => sanitize_text_field( $params['date_to'] ?? '' ) ?: null,
					'inclusive' => true,
				),
			);
		}

		if ( ! empty( $params['orderby'] ) ) {
			$args['orderby'] = sanitize_text_field( $params['orderby'] );
			$args['order']   = sanitize_text_field( $params['order'] ?? 'DESC' );
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$item = array(
				'id'           => $post->ID,
				'title'        => $post->post_title,
				'status'       => $post->post_status,
				'date_created' => $post->post_date,
				'date_modified' => $post->post_modified,
			);

			// Add selected meta fields.
			if ( ! empty( $config['fields'] ) ) {
				foreach ( $config['fields'] as $field ) {
					$item[ $field ] = get_post_meta( $post->ID, $field, true );
				}
			}

			$items[] = $item;
		}

		return array(
			'items'       => $items,
			'total'       => $query->found_posts,
			'pages'       => $query->max_num_pages,
			'current_page' => $args['paged'],
		);
	}

	/**
	 * Handle action-based endpoint.
	 *
	 * @param array            $config  Handler config.
	 * @param \WP_REST_Request $request Request object.
	 * @return array|\WP_Error
	 */
	private function handle_action_endpoint( $config, $request ) {
		$action = $config['action'] ?? '';
		$params = $request->get_params();

		switch ( $action ) {
			case 'create_booking':
				return $this->action_create_booking( $params );

			case 'update_booking':
				return $this->action_update_booking( $params );

			case 'cancel_booking':
				return $this->action_cancel_booking( $params );

			case 'get_availability':
				return $this->action_get_availability( $params );

			case 'get_services':
				return $this->action_get_services( $params );

			case 'get_staff':
				return $this->action_get_staff( $params );

			default:
				// Allow custom actions via filter.
				$result = apply_filters( 'bkx_api_custom_action_' . $action, null, $params, $request );
				if ( null !== $result ) {
					return $result;
				}

				return new \WP_Error(
					'unknown_action',
					__( 'Unknown action type', 'bkx-api-builder' ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * Handle callback-based endpoint.
	 *
	 * @param array            $config  Handler config.
	 * @param \WP_REST_Request $request Request object.
	 * @return mixed|\WP_Error
	 */
	private function handle_callback_endpoint( $config, $request ) {
		$callback = $config['callback'] ?? '';

		if ( empty( $callback ) || ! is_callable( $callback ) ) {
			return new \WP_Error(
				'invalid_callback',
				__( 'Invalid callback function', 'bkx-api-builder' ),
				array( 'status' => 500 )
			);
		}

		return call_user_func( $callback, $request );
	}

	/**
	 * Handle proxy endpoint.
	 *
	 * @param array            $config  Handler config.
	 * @param \WP_REST_Request $request Request object.
	 * @return array|\WP_Error
	 */
	private function handle_proxy_endpoint( $config, $request ) {
		$target_url = $config['target_url'] ?? '';

		if ( empty( $target_url ) ) {
			return new \WP_Error(
				'missing_target',
				__( 'Proxy target URL not configured', 'bkx-api-builder' ),
				array( 'status' => 500 )
			);
		}

		$args = array(
			'method'  => $request->get_method(),
			'headers' => $config['headers'] ?? array(),
			'timeout' => $config['timeout'] ?? 30,
		);

		if ( in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = $request->get_body();
		}

		$response = wp_remote_request( $target_url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return $data ?: $body;
	}

	/**
	 * Check endpoint permission.
	 *
	 * @param array            $endpoint Endpoint config.
	 * @param \WP_REST_Request $request  Request object.
	 * @return bool|\WP_Error
	 */
	private function check_endpoint_permission( $endpoint, $request ) {
		$auth_type = $endpoint['authentication'];

		switch ( $auth_type ) {
			case 'none':
				return true;

			case 'api_key':
				return $this->authenticate_api_key( $request );

			case 'jwt':
				return $this->authenticate_jwt( $request );

			case 'oauth':
				return $this->authenticate_oauth( $request );

			case 'wordpress':
				return is_user_logged_in();

			case 'capability':
				$permissions = json_decode( $endpoint['permissions'], true ) ?: array();
				$capability  = $permissions['capability'] ?? 'manage_options';
				return current_user_can( $capability );

			default:
				return false;
		}
	}

	/**
	 * Authenticate via API key.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	private function authenticate_api_key( $request ) {
		$api_key = $request->get_header( 'X-API-Key' );

		if ( empty( $api_key ) ) {
			$api_key = $request->get_param( 'api_key' );
		}

		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'missing_api_key',
				__( 'API key is required', 'bkx-api-builder' ),
				array( 'status' => 401 )
			);
		}

		$key_data = $this->services['api_key_manager']->validate_key( $api_key );

		if ( ! $key_data ) {
			return new \WP_Error(
				'invalid_api_key',
				__( 'Invalid or expired API key', 'bkx-api-builder' ),
				array( 'status' => 401 )
			);
		}

		// Check IP restrictions.
		if ( ! empty( $key_data['allowed_ips'] ) ) {
			$allowed_ips = json_decode( $key_data['allowed_ips'], true ) ?: array();
			$client_ip   = $this->get_client_ip();

			if ( ! empty( $allowed_ips ) && ! in_array( $client_ip, $allowed_ips, true ) ) {
				return new \WP_Error(
					'ip_not_allowed',
					__( 'IP address not allowed', 'bkx-api-builder' ),
					array( 'status' => 403 )
				);
			}
		}

		// Check rate limit.
		if ( ! $this->services['rate_limiter']->check( 'key_' . $key_data['id'], $key_data['rate_limit'], $key_data['rate_limit_window'] ) ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded', 'bkx-api-builder' ),
				array( 'status' => 429 )
			);
		}

		// Update last used.
		$this->services['api_key_manager']->update_last_used( $key_data['id'] );

		return true;
	}

	/**
	 * Authenticate via JWT.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	private function authenticate_jwt( $request ) {
		$auth_header = $request->get_header( 'Authorization' );

		if ( empty( $auth_header ) || strpos( $auth_header, 'Bearer ' ) !== 0 ) {
			return new \WP_Error(
				'missing_token',
				__( 'JWT token is required', 'bkx-api-builder' ),
				array( 'status' => 401 )
			);
		}

		$token = substr( $auth_header, 7 );

		// Verify JWT token (requires JWT library).
		$result = apply_filters( 'bkx_api_verify_jwt', null, $token );

		if ( null === $result ) {
			return new \WP_Error(
				'jwt_not_configured',
				__( 'JWT authentication not configured', 'bkx-api-builder' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	/**
	 * Authenticate via OAuth.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	private function authenticate_oauth( $request ) {
		$result = apply_filters( 'bkx_api_verify_oauth', null, $request );

		if ( null === $result ) {
			return new \WP_Error(
				'oauth_not_configured',
				__( 'OAuth authentication not configured', 'bkx-api-builder' ),
				array( 'status' => 500 )
			);
		}

		return $result;
	}

	/**
	 * Pre-dispatch handler.
	 *
	 * @param mixed            $result  Pre-dispatch result.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request Request object.
	 * @return mixed
	 */
	public function pre_dispatch_handler( $result, $server, $request ) {
		$route = $request->get_route();

		// Check if this is a custom endpoint.
		$namespace = $this->settings['api_namespace'] ?? 'bkx-custom/v1';
		if ( strpos( $route, '/' . $namespace ) !== 0 ) {
			return $result;
		}

		// Apply global rate limiting.
		$identifier = $this->get_client_ip();
		$limit      = $this->settings['default_rate_limit'] ?? 1000;
		$window     = $this->settings['rate_limit_window'] ?? 3600;

		if ( ! $this->services['rate_limiter']->check( $identifier, $limit, $window ) ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded', 'bkx-api-builder' ),
				array( 'status' => 429 )
			);
		}

		// Require HTTPS.
		if ( ! empty( $this->settings['require_https'] ) && ! is_ssl() ) {
			return new \WP_Error(
				'https_required',
				__( 'HTTPS is required for API requests', 'bkx-api-builder' ),
				array( 'status' => 403 )
			);
		}

		return $result;
	}

	/**
	 * Post-dispatch handler.
	 *
	 * @param \WP_REST_Response $result  Response object.
	 * @param \WP_REST_Server   $server  Server instance.
	 * @param \WP_REST_Request  $request Request object.
	 * @return \WP_REST_Response
	 */
	public function post_dispatch_handler( $result, $server, $request ) {
		// Add rate limit headers.
		$identifier = $this->get_client_ip();
		$remaining  = $this->services['rate_limiter']->get_remaining( $identifier );
		$limit      = $this->settings['default_rate_limit'] ?? 1000;

		$result->header( 'X-RateLimit-Limit', $limit );
		$result->header( 'X-RateLimit-Remaining', max( 0, $remaining ) );

		return $result;
	}

	/**
	 * Add CORS headers.
	 */
	public function add_cors_headers() {
		$origins = $this->settings['allowed_origins'] ?? '*';

		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		add_filter( 'rest_pre_serve_request', function ( $value ) use ( $origins ) {
			header( 'Access-Control-Allow-Origin: ' . $origins );
			header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, X-API-Key, Content-Type' );
			header( 'Access-Control-Allow-Credentials: true' );
			header( 'Access-Control-Max-Age: 600' );

			return $value;
		} );
	}

	/**
	 * Get API documentation.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return array
	 */
	public function get_api_documentation( $request ) {
		return $this->services['doc_generator']->generate();
	}

	/**
	 * Get client IP address.
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
	 * Get endpoint arguments from schema.
	 *
	 * @param array $endpoint Endpoint config.
	 * @return array
	 */
	private function get_endpoint_args( $endpoint ) {
		$schema = json_decode( $endpoint['request_schema'], true );
		if ( empty( $schema ) ) {
			return array();
		}

		$args = array();
		foreach ( $schema as $param => $config ) {
			$args[ $param ] = array(
				'required'          => $config['required'] ?? false,
				'type'              => $config['type'] ?? 'string',
				'sanitize_callback' => $this->get_sanitize_callback( $config['type'] ?? 'string' ),
				'validate_callback' => function ( $value, $request, $param ) use ( $config ) {
					return $this->validate_param( $value, $config );
				},
			);

			if ( isset( $config['default'] ) ) {
				$args[ $param ]['default'] = $config['default'];
			}

			if ( isset( $config['description'] ) ) {
				$args[ $param ]['description'] = $config['description'];
			}
		}

		return $args;
	}

	/**
	 * Get sanitize callback for type.
	 *
	 * @param string $type Parameter type.
	 * @return callable
	 */
	private function get_sanitize_callback( $type ) {
		switch ( $type ) {
			case 'integer':
				return 'absint';
			case 'boolean':
				return 'rest_sanitize_boolean';
			case 'email':
				return 'sanitize_email';
			case 'url':
				return 'esc_url_raw';
			default:
				return 'sanitize_text_field';
		}
	}

	/**
	 * Validate parameter.
	 *
	 * @param mixed $value  Parameter value.
	 * @param array $config Parameter config.
	 * @return bool|\WP_Error
	 */
	private function validate_param( $value, $config ) {
		$type = $config['type'] ?? 'string';

		switch ( $type ) {
			case 'integer':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error( 'invalid_param', __( 'Parameter must be an integer', 'bkx-api-builder' ) );
				}
				if ( isset( $config['min'] ) && $value < $config['min'] ) {
					return new \WP_Error( 'invalid_param', sprintf( __( 'Parameter must be at least %d', 'bkx-api-builder' ), $config['min'] ) );
				}
				if ( isset( $config['max'] ) && $value > $config['max'] ) {
					return new \WP_Error( 'invalid_param', sprintf( __( 'Parameter must be at most %d', 'bkx-api-builder' ), $config['max'] ) );
				}
				break;

			case 'email':
				if ( ! is_email( $value ) ) {
					return new \WP_Error( 'invalid_param', __( 'Invalid email address', 'bkx-api-builder' ) );
				}
				break;

			case 'enum':
				if ( ! empty( $config['values'] ) && ! in_array( $value, $config['values'], true ) ) {
					return new \WP_Error( 'invalid_param', __( 'Invalid value', 'bkx-api-builder' ) );
				}
				break;
		}

		return true;
	}

	/**
	 * Build meta query from config.
	 *
	 * @param array $config Meta query config.
	 * @param array $params Request parameters.
	 * @return array
	 */
	private function build_meta_query( $config, $params ) {
		$meta_query = array();

		foreach ( $config as $query ) {
			$key     = $query['key'];
			$param   = $query['param'] ?? $key;
			$compare = $query['compare'] ?? '=';

			if ( isset( $params[ $param ] ) ) {
				$meta_query[] = array(
					'key'     => $key,
					'value'   => sanitize_text_field( $params[ $param ] ),
					'compare' => $compare,
				);
			}
		}

		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}

		return $meta_query;
	}

	/**
	 * Action: Create booking.
	 *
	 * @param array $params Request parameters.
	 * @return array|\WP_Error
	 */
	private function action_create_booking( $params ) {
		$required = array( 'service_id', 'date', 'time' );
		foreach ( $required as $field ) {
			if ( empty( $params[ $field ] ) ) {
				return new \WP_Error(
					'missing_field',
					sprintf( __( 'Missing required field: %s', 'bkx-api-builder' ), $field ),
					array( 'status' => 400 )
				);
			}
		}

		// Use BookingX API to create booking.
		$booking_data = apply_filters( 'bkx_api_create_booking_data', array(
			'service_id'   => absint( $params['service_id'] ),
			'seat_id'      => absint( $params['staff_id'] ?? 0 ),
			'booking_date' => sanitize_text_field( $params['date'] ),
			'booking_time' => sanitize_text_field( $params['time'] ),
			'customer'     => array(
				'name'  => sanitize_text_field( $params['customer_name'] ?? '' ),
				'email' => sanitize_email( $params['customer_email'] ?? '' ),
				'phone' => sanitize_text_field( $params['customer_phone'] ?? '' ),
			),
			'notes'        => sanitize_textarea_field( $params['notes'] ?? '' ),
		), $params );

		$booking_id = apply_filters( 'bkx_api_create_booking', 0, $booking_data );

		if ( ! $booking_id ) {
			return new \WP_Error(
				'booking_failed',
				__( 'Failed to create booking', 'bkx-api-builder' ),
				array( 'status' => 500 )
			);
		}

		// Trigger webhook.
		$this->services['webhook_dispatcher']->dispatch( 'booking.created', array(
			'booking_id' => $booking_id,
			'data'       => $booking_data,
		) );

		return array(
			'success'    => true,
			'booking_id' => $booking_id,
			'message'    => __( 'Booking created successfully', 'bkx-api-builder' ),
		);
	}

	/**
	 * Action: Update booking.
	 *
	 * @param array $params Request parameters.
	 * @return array|\WP_Error
	 */
	private function action_update_booking( $params ) {
		if ( empty( $params['booking_id'] ) ) {
			return new \WP_Error(
				'missing_booking_id',
				__( 'Booking ID is required', 'bkx-api-builder' ),
				array( 'status' => 400 )
			);
		}

		$booking_id = absint( $params['booking_id'] );

		// Verify booking exists.
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error(
				'booking_not_found',
				__( 'Booking not found', 'bkx-api-builder' ),
				array( 'status' => 404 )
			);
		}

		// Update booking.
		$updated = apply_filters( 'bkx_api_update_booking', false, $booking_id, $params );

		if ( ! $updated ) {
			return new \WP_Error(
				'update_failed',
				__( 'Failed to update booking', 'bkx-api-builder' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Booking updated successfully', 'bkx-api-builder' ),
		);
	}

	/**
	 * Action: Cancel booking.
	 *
	 * @param array $params Request parameters.
	 * @return array|\WP_Error
	 */
	private function action_cancel_booking( $params ) {
		if ( empty( $params['booking_id'] ) ) {
			return new \WP_Error(
				'missing_booking_id',
				__( 'Booking ID is required', 'bkx-api-builder' ),
				array( 'status' => 400 )
			);
		}

		$booking_id = absint( $params['booking_id'] );

		// Verify booking exists.
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error(
				'booking_not_found',
				__( 'Booking not found', 'bkx-api-builder' ),
				array( 'status' => 404 )
			);
		}

		// Cancel booking.
		$result = wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-cancelled',
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Trigger webhook.
		$this->services['webhook_dispatcher']->dispatch( 'booking.cancelled', array(
			'booking_id' => $booking_id,
			'reason'     => sanitize_text_field( $params['reason'] ?? '' ),
		) );

		return array(
			'success' => true,
			'message' => __( 'Booking cancelled successfully', 'bkx-api-builder' ),
		);
	}

	/**
	 * Action: Get availability.
	 *
	 * @param array $params Request parameters.
	 * @return array|\WP_Error
	 */
	private function action_get_availability( $params ) {
		$service_id = absint( $params['service_id'] ?? 0 );
		$staff_id   = absint( $params['staff_id'] ?? 0 );
		$date       = sanitize_text_field( $params['date'] ?? '' );

		if ( empty( $date ) ) {
			$date = wp_date( 'Y-m-d' );
		}

		$slots = apply_filters( 'bkx_api_get_availability', array(), $service_id, $staff_id, $date );

		return array(
			'date'  => $date,
			'slots' => $slots,
		);
	}

	/**
	 * Action: Get services.
	 *
	 * @param array $params Request parameters.
	 * @return array
	 */
	private function action_get_services( $params ) {
		$args = array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => min( absint( $params['per_page'] ?? 50 ), 100 ),
			'post_status'    => 'publish',
		);

		if ( ! empty( $params['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'bkx_base_category',
					'field'    => 'slug',
					'terms'    => sanitize_text_field( $params['category'] ),
				),
			);
		}

		$query    = new \WP_Query( $args );
		$services = array();

		foreach ( $query->posts as $post ) {
			$services[] = array(
				'id'          => $post->ID,
				'name'        => $post->post_title,
				'description' => $post->post_excerpt,
				'duration'    => get_post_meta( $post->ID, 'base_time', true ),
				'price'       => get_post_meta( $post->ID, 'base_price', true ),
			);
		}

		return array(
			'services' => $services,
			'total'    => $query->found_posts,
		);
	}

	/**
	 * Action: Get staff.
	 *
	 * @param array $params Request parameters.
	 * @return array
	 */
	private function action_get_staff( $params ) {
		$args = array(
			'post_type'      => 'bkx_seat',
			'posts_per_page' => min( absint( $params['per_page'] ?? 50 ), 100 ),
			'post_status'    => 'publish',
		);

		if ( ! empty( $params['service_id'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'seat_base',
					'value'   => absint( $params['service_id'] ),
					'compare' => 'LIKE',
				),
			);
		}

		$query = new \WP_Query( $args );
		$staff = array();

		foreach ( $query->posts as $post ) {
			$staff[] = array(
				'id'          => $post->ID,
				'name'        => $post->post_title,
				'description' => $post->post_excerpt,
				'image'       => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ),
			);
		}

		return array(
			'staff' => $staff,
			'total' => $query->found_posts,
		);
	}

	/**
	 * AJAX: Save endpoint.
	 */
	public function ajax_save_endpoint() {
		check_ajax_referer( 'bkx_api_builder_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-api-builder' ) ) );
		}

		$endpoint_id = absint( $_POST['endpoint_id'] ?? 0 );
		$data        = array(
			'name'           => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'slug'           => sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) ),
			'method'         => sanitize_text_field( wp_unslash( $_POST['method'] ?? 'GET' ) ),
			'namespace'      => sanitize_text_field( wp_unslash( $_POST['namespace'] ?? 'bkx-custom/v1' ) ),
			'route'          => sanitize_text_field( wp_unslash( $_POST['route'] ?? '' ) ),
			'description'    => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'handler_type'   => sanitize_text_field( wp_unslash( $_POST['handler_type'] ?? 'query' ) ),
			'handler_config' => wp_unslash( $_POST['handler_config'] ?? '{}' ),
			'request_schema' => wp_unslash( $_POST['request_schema'] ?? '{}' ),
			'response_schema' => wp_unslash( $_POST['response_schema'] ?? '{}' ),
			'authentication' => sanitize_text_field( wp_unslash( $_POST['authentication'] ?? 'none' ) ),
			'rate_limit'     => absint( $_POST['rate_limit'] ?? 0 ),
			'rate_limit_window' => absint( $_POST['rate_limit_window'] ?? 3600 ),
			'cache_enabled'  => ! empty( $_POST['cache_enabled'] ),
			'cache_ttl'      => absint( $_POST['cache_ttl'] ?? 300 ),
			'permissions'    => wp_unslash( $_POST['permissions'] ?? '{}' ),
			'status'         => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
			'version'        => sanitize_text_field( wp_unslash( $_POST['version'] ?? '1.0' ) ),
		);

		$result = $this->services['endpoint_manager']->save( $endpoint_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Flush rewrite rules to register new endpoint.
		flush_rewrite_rules();

		wp_send_json_success( array(
			'message'     => __( 'Endpoint saved successfully', 'bkx-api-builder' ),
			'endpoint_id' => $result,
		) );
	}

	/**
	 * AJAX: Delete endpoint.
	 */
	public function ajax_delete_endpoint() {
		check_ajax_referer( 'bkx_api_builder_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-api-builder' ) ) );
		}

		$endpoint_id = absint( $_POST['endpoint_id'] ?? 0 );

		if ( ! $endpoint_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid endpoint ID', 'bkx-api-builder' ) ) );
		}

		$result = $this->services['endpoint_manager']->delete( $endpoint_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete endpoint', 'bkx-api-builder' ) ) );
		}

		flush_rewrite_rules();

		wp_send_json_success( array( 'message' => __( 'Endpoint deleted successfully', 'bkx-api-builder' ) ) );
	}

	/**
	 * AJAX: Test endpoint.
	 */
	public function ajax_test_endpoint() {
		check_ajax_referer( 'bkx_api_builder_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-api-builder' ) ) );
		}

		$endpoint_id = absint( $_POST['endpoint_id'] ?? 0 );
		$endpoint    = $this->services['endpoint_manager']->get( $endpoint_id );

		if ( ! $endpoint ) {
			wp_send_json_error( array( 'message' => __( 'Endpoint not found', 'bkx-api-builder' ) ) );
		}

		$url = rest_url( $endpoint['namespace'] . $endpoint['route'] );

		$args = array(
			'method'  => $endpoint['method'],
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		);

		// Add test parameters.
		$params = json_decode( wp_unslash( $_POST['test_params'] ?? '{}' ), true );
		if ( ! empty( $params ) ) {
			if ( 'GET' === $endpoint['method'] ) {
				$url = add_query_arg( $params, $url );
			} else {
				$args['body'] = wp_json_encode( $params );
			}
		}

		// Add authentication if needed.
		if ( 'api_key' === $endpoint['authentication'] && ! empty( $_POST['test_api_key'] ) ) {
			$args['headers']['X-API-Key'] = sanitize_text_field( wp_unslash( $_POST['test_api_key'] ) );
		}

		$start    = microtime( true );
		$response = wp_remote_request( $url, $args );
		$duration = round( ( microtime( true ) - $start ) * 1000, 2 );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array(
				'message'  => $response->get_error_message(),
				'duration' => $duration,
			) );
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		wp_send_json_success( array(
			'status_code' => $code,
			'body'        => json_decode( $body, true ) ?: $body,
			'duration'    => $duration,
			'headers'     => wp_remote_retrieve_headers( $response )->getAll(),
		) );
	}

	/**
	 * AJAX: Generate API key.
	 */
	public function ajax_generate_key() {
		check_ajax_referer( 'bkx_api_builder_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-api-builder' ) ) );
		}

		$data = array(
			'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'user_id'          => get_current_user_id(),
			'permissions'      => wp_unslash( $_POST['permissions'] ?? '{}' ),
			'rate_limit'       => absint( $_POST['rate_limit'] ?? 1000 ),
			'rate_limit_window' => absint( $_POST['rate_limit_window'] ?? 3600 ),
			'allowed_ips'      => wp_unslash( $_POST['allowed_ips'] ?? '[]' ),
			'allowed_origins'  => wp_unslash( $_POST['allowed_origins'] ?? '[]' ),
			'expires_at'       => sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) ) ?: null,
		);

		$result = $this->services['api_key_manager']->generate( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'    => __( 'API key generated successfully', 'bkx-api-builder' ),
			'key_id'     => $result['id'],
			'api_key'    => $result['api_key'],
			'api_secret' => $result['api_secret'],
			'note'       => __( 'Save these credentials now. The secret will not be shown again.', 'bkx-api-builder' ),
		) );
	}

	/**
	 * AJAX: Revoke API key.
	 */
	public function ajax_revoke_key() {
		check_ajax_referer( 'bkx_api_builder_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-api-builder' ) ) );
		}

		$key_id = absint( $_POST['key_id'] ?? 0 );

		if ( ! $key_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid key ID', 'bkx-api-builder' ) ) );
		}

		$result = $this->services['api_key_manager']->revoke( $key_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to revoke API key', 'bkx-api-builder' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'API key revoked successfully', 'bkx-api-builder' ) ) );
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_api_builder_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-api-builder' ) ) );
		}

		$settings = array(
			'enable_logging'       => ! empty( $_POST['enable_logging'] ),
			'log_retention_days'   => absint( $_POST['log_retention_days'] ?? 30 ),
			'default_rate_limit'   => absint( $_POST['default_rate_limit'] ?? 1000 ),
			'rate_limit_window'    => absint( $_POST['rate_limit_window'] ?? 3600 ),
			'enable_cors'          => ! empty( $_POST['enable_cors'] ),
			'allowed_origins'      => sanitize_text_field( wp_unslash( $_POST['allowed_origins'] ?? '*' ) ),
			'enable_documentation' => ! empty( $_POST['enable_documentation'] ),
			'documentation_public' => ! empty( $_POST['documentation_public'] ),
			'require_https'        => ! empty( $_POST['require_https'] ),
			'enable_webhooks'      => ! empty( $_POST['enable_webhooks'] ),
			'webhook_retry_count'  => absint( $_POST['webhook_retry_count'] ?? 3 ),
			'webhook_retry_delay'  => absint( $_POST['webhook_retry_delay'] ?? 60 ),
			'api_namespace'        => sanitize_text_field( wp_unslash( $_POST['api_namespace'] ?? 'bkx-custom/v1' ) ),
			'enable_versioning'    => ! empty( $_POST['enable_versioning'] ),
		);

		update_option( 'bkx_api_builder_settings', $settings );
		$this->settings = $settings;

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully', 'bkx-api-builder' ) ) );
	}

	/**
	 * AJAX: Export documentation.
	 */
	public function ajax_export_docs() {
		check_ajax_referer( 'bkx_api_builder_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-api-builder' ) ) );
		}

		$format = sanitize_text_field( wp_unslash( $_POST['format'] ?? 'openapi' ) );
		$docs   = $this->services['doc_generator']->export( $format );

		if ( is_wp_error( $docs ) ) {
			wp_send_json_error( array( 'message' => $docs->get_error_message() ) );
		}

		wp_send_json_success( array(
			'content'  => $docs,
			'filename' => 'bookingx-api.' . ( 'openapi' === $format ? 'json' : $format ),
		) );
	}

	/**
	 * AJAX: Clear logs.
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'bkx_api_builder_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-api-builder' ) ) );
		}

		$this->services['request_logger']->clear_all();

		wp_send_json_success( array( 'message' => __( 'Logs cleared successfully', 'bkx-api-builder' ) ) );
	}

	/**
	 * Cleanup old data.
	 */
	public function cleanup_old_data() {
		$days = $this->settings['log_retention_days'] ?? 30;
		$this->services['request_logger']->cleanup( $days );
		$this->services['rate_limiter']->cleanup();
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['api_builder'] = __( 'API Builder', 'bkx-api-builder' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_API_BUILDER_PATH . 'templates/admin/settings-tab.php';
	}

	/**
	 * Trigger booking created webhook.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function trigger_booking_created( $booking_id, $booking_data ) {
		$this->services['webhook_dispatcher']->dispatch( 'booking.created', array(
			'booking_id' => $booking_id,
			'data'       => $booking_data,
		) );
	}

	/**
	 * Trigger booking updated webhook.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function trigger_booking_updated( $booking_id, $booking_data ) {
		$this->services['webhook_dispatcher']->dispatch( 'booking.updated', array(
			'booking_id' => $booking_id,
			'data'       => $booking_data,
		) );
	}

	/**
	 * Trigger booking cancelled webhook.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $data       Cancellation data.
	 */
	public function trigger_booking_cancelled( $booking_id, $data ) {
		$this->services['webhook_dispatcher']->dispatch( 'booking.cancelled', array(
			'booking_id' => $booking_id,
			'data'       => $data,
		) );
	}

	/**
	 * Trigger payment completed webhook.
	 *
	 * @param int   $payment_id   Payment ID.
	 * @param array $payment_data Payment data.
	 */
	public function trigger_payment_completed( $payment_id, $payment_data ) {
		$this->services['webhook_dispatcher']->dispatch( 'payment.completed', array(
			'payment_id' => $payment_id,
			'data'       => $payment_data,
		) );
	}

	/**
	 * Get service.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Get settings.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return $this->settings[ $key ] ?? $default;
	}
}
