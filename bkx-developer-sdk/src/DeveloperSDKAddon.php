<?php
/**
 * Developer SDK Addon Main Class.
 *
 * @package BookingX\DeveloperSDK
 */

namespace BookingX\DeveloperSDK;

defined( 'ABSPATH' ) || exit;

/**
 * Class DeveloperSDKAddon
 */
class DeveloperSDKAddon {

	/**
	 * Singleton instance.
	 *
	 * @var DeveloperSDKAddon
	 */
	private static $instance = null;

	/**
	 * Services container.
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
	 * Get singleton instance.
	 *
	 * @return DeveloperSDKAddon
	 */
	public static function get_instance(): DeveloperSDKAddon {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings = get_option( 'bkx_developer_sdk_settings', array() );

		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services(): void {
		$this->services['code_generator']      = new Services\CodeGenerator();
		$this->services['api_explorer']        = new Services\APIExplorer();
		$this->services['sandbox_manager']     = new Services\SandboxManager();
		$this->services['test_data_generator'] = new Services\TestDataGenerator();
		$this->services['documentation']       = new Services\Documentation();
		$this->services['hook_inspector']      = new Services\HookInspector();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );

		// Assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_generate_code', array( $this, 'ajax_generate_code' ) );
		add_action( 'wp_ajax_bkx_explore_api', array( $this, 'ajax_explore_api' ) );
		add_action( 'wp_ajax_bkx_create_sandbox', array( $this, 'ajax_create_sandbox' ) );
		add_action( 'wp_ajax_bkx_generate_test_data', array( $this, 'ajax_generate_test_data' ) );
		add_action( 'wp_ajax_bkx_save_sdk_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_get_hooks', array( $this, 'ajax_get_hooks' ) );
		add_action( 'wp_ajax_bkx_test_code', array( $this, 'ajax_test_code' ) );
		add_action( 'wp_ajax_bkx_download_code', array( $this, 'ajax_download_code' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// BookingX integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_developer_sdk', array( $this, 'render_settings_tab' ) );

		// Debug bar integration.
		if ( ! empty( $this->settings['debug_mode'] ) ) {
			add_action( 'admin_bar_menu', array( $this, 'add_debug_menu' ), 999 );
		}
	}

	/**
	 * Get service.
	 *
	 * @param string $name Service name.
	 * @return object|null Service instance.
	 */
	public function get_service( string $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Developer SDK', 'bkx-developer-sdk' ),
			__( 'Developer SDK', 'bkx-developer-sdk' ),
			'manage_options',
			'bkx-developer-sdk',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( strpos( $hook, 'bkx-developer-sdk' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-developer-sdk-admin',
			BKX_DEV_SDK_URL . 'assets/css/admin.css',
			array(),
			BKX_DEV_SDK_VERSION
		);

		// Code editor.
		wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );

		// Highlight.js for syntax highlighting.
		wp_enqueue_style(
			'highlight-js',
			'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github-dark.min.css',
			array(),
			'11.8.0'
		);
		wp_enqueue_script(
			'highlight-js',
			'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js',
			array(),
			'11.8.0',
			true
		);

		wp_enqueue_script(
			'bkx-developer-sdk-admin',
			BKX_DEV_SDK_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util', 'highlight-js' ),
			BKX_DEV_SDK_VERSION,
			true
		);

		wp_localize_script(
			'bkx-developer-sdk-admin',
			'bkxDevSDK',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_developer_sdk_nonce' ),
				'strings' => array(
					'generating'       => __( 'Generating...', 'bkx-developer-sdk' ),
					'generate'         => __( 'Generate', 'bkx-developer-sdk' ),
					'error'            => __( 'An error occurred.', 'bkx-developer-sdk' ),
					'copied'           => __( 'Copied to clipboard!', 'bkx-developer-sdk' ),
					'download'         => __( 'Download', 'bkx-developer-sdk' ),
					'test_running'     => __( 'Running test...', 'bkx-developer-sdk' ),
					'test_passed'      => __( 'Test passed!', 'bkx-developer-sdk' ),
					'test_failed'      => __( 'Test failed.', 'bkx-developer-sdk' ),
					'confirm_sandbox'  => __( 'Create sandbox environment? This will create isolated test data.', 'bkx-developer-sdk' ),
					'sandbox_created'  => __( 'Sandbox created successfully!', 'bkx-developer-sdk' ),
					'saving'           => __( 'Saving...', 'bkx-developer-sdk' ),
					'saved'            => __( 'Settings saved!', 'bkx-developer-sdk' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page(): void {
		include BKX_DEV_SDK_PATH . 'templates/admin/page.php';
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs['developer_sdk'] = __( 'Developer SDK', 'bkx-developer-sdk' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab(): void {
		include BKX_DEV_SDK_PATH . 'templates/admin/settings-tab.php';
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'bkx-sdk/v1',
			'/documentation',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_documentation' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);

		register_rest_route(
			'bkx-sdk/v1',
			'/hooks',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_hooks' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);

		register_rest_route(
			'bkx-sdk/v1',
			'/endpoints',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_endpoints' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);
	}

	/**
	 * REST permission check.
	 *
	 * @return bool Whether user has permission.
	 */
	public function rest_permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST get documentation.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_documentation( \WP_REST_Request $request ): \WP_REST_Response {
		$docs = $this->services['documentation']->get_all();
		return new \WP_REST_Response( $docs );
	}

	/**
	 * REST get hooks.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_hooks( \WP_REST_Request $request ): \WP_REST_Response {
		$hooks = $this->services['hook_inspector']->get_all_hooks();
		return new \WP_REST_Response( $hooks );
	}

	/**
	 * REST get endpoints.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_endpoints( \WP_REST_Request $request ): \WP_REST_Response {
		$endpoints = $this->services['api_explorer']->get_bookingx_endpoints();
		return new \WP_REST_Response( $endpoints );
	}

	/**
	 * AJAX generate code.
	 */
	public function ajax_generate_code(): void {
		check_ajax_referer( 'bkx_developer_sdk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-developer-sdk' ) ) );
		}

		$type   = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$params = isset( $_POST['params'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['params'] ) ) : array();

		$generator = $this->services['code_generator'];
		$code      = $generator->generate( $type, $params );

		if ( is_wp_error( $code ) ) {
			wp_send_json_error( array( 'message' => $code->get_error_message() ) );
		}

		wp_send_json_success( array( 'code' => $code ) );
	}

	/**
	 * AJAX explore API.
	 */
	public function ajax_explore_api(): void {
		check_ajax_referer( 'bkx_developer_sdk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-developer-sdk' ) ) );
		}

		$method   = sanitize_text_field( wp_unslash( $_POST['method'] ?? 'GET' ) );
		$endpoint = sanitize_text_field( wp_unslash( $_POST['endpoint'] ?? '' ) );
		$body     = isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '';

		$explorer = $this->services['api_explorer'];
		$result   = $explorer->make_request( $method, $endpoint, $body );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX create sandbox.
	 */
	public function ajax_create_sandbox(): void {
		check_ajax_referer( 'bkx_developer_sdk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-developer-sdk' ) ) );
		}

		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );

		$sandbox = $this->services['sandbox_manager'];
		$result  = $sandbox->create( $name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX generate test data.
	 */
	public function ajax_generate_test_data(): void {
		check_ajax_referer( 'bkx_developer_sdk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-developer-sdk' ) ) );
		}

		$type  = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$count = absint( $_POST['count'] ?? 1 );

		$generator = $this->services['test_data_generator'];
		$result    = $generator->generate( $type, $count );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX save settings.
	 */
	public function ajax_save_settings(): void {
		check_ajax_referer( 'bkx_developer_sdk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-developer-sdk' ) ) );
		}

		$settings = array(
			'debug_mode'              => ! empty( $_POST['debug_mode'] ),
			'enable_sandbox'          => ! empty( $_POST['enable_sandbox'] ),
			'sandbox_prefix'          => sanitize_text_field( wp_unslash( $_POST['sandbox_prefix'] ?? 'bkx_sandbox_' ) ),
			'enable_code_generator'   => ! empty( $_POST['enable_code_generator'] ),
			'enable_api_explorer'     => ! empty( $_POST['enable_api_explorer'] ),
			'enable_testing_tools'    => ! empty( $_POST['enable_testing_tools'] ),
			'enable_documentation'    => ! empty( $_POST['enable_documentation'] ),
			'api_explorer_cache_ttl'  => absint( $_POST['api_explorer_cache_ttl'] ?? 3600 ),
			'test_data_retention'     => absint( $_POST['test_data_retention'] ?? 7 ),
			'log_api_requests'        => ! empty( $_POST['log_api_requests'] ),
			'enable_cli'              => ! empty( $_POST['enable_cli'] ),
		);

		update_option( 'bkx_developer_sdk_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'bkx-developer-sdk' ) ) );
	}

	/**
	 * AJAX get hooks.
	 */
	public function ajax_get_hooks(): void {
		check_ajax_referer( 'bkx_developer_sdk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-developer-sdk' ) ) );
		}

		$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$search   = sanitize_text_field( wp_unslash( $_POST['search'] ?? '' ) );

		$inspector = $this->services['hook_inspector'];
		$hooks     = $inspector->get_hooks( $category, $search );

		wp_send_json_success( array( 'hooks' => $hooks ) );
	}

	/**
	 * AJAX test code.
	 */
	public function ajax_test_code(): void {
		check_ajax_referer( 'bkx_developer_sdk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-developer-sdk' ) ) );
		}

		$code = wp_unslash( $_POST['code'] ?? '' );

		// Security check - only allow certain operations in test mode.
		if ( strpos( $code, 'eval' ) !== false || strpos( $code, 'exec' ) !== false ) {
			wp_send_json_error( array( 'message' => __( 'Code contains disallowed functions.', 'bkx-developer-sdk' ) ) );
		}

		$sandbox = $this->services['sandbox_manager'];
		$result  = $sandbox->execute_code( $code );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX download code.
	 */
	public function ajax_download_code(): void {
		check_ajax_referer( 'bkx_developer_sdk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-developer-sdk' ) ) );
		}

		$code     = wp_unslash( $_POST['code'] ?? '' );
		$filename = sanitize_file_name( wp_unslash( $_POST['filename'] ?? 'generated-code.php' ) );

		$generator = $this->services['code_generator'];
		$file_url  = $generator->save_code( $code, $filename );

		if ( is_wp_error( $file_url ) ) {
			wp_send_json_error( array( 'message' => $file_url->get_error_message() ) );
		}

		wp_send_json_success( array( 'url' => $file_url ) );
	}

	/**
	 * Add debug menu to admin bar.
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar instance.
	 */
	public function add_debug_menu( \WP_Admin_Bar $admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'bkx-developer-debug',
				'title' => '<span class="ab-icon dashicons dashicons-code-standards"></span> BKX Debug',
				'href'  => admin_url( 'edit.php?post_type=bkx_booking&page=bkx-developer-sdk' ),
			)
		);

		$admin_bar->add_node(
			array(
				'id'     => 'bkx-debug-api-explorer',
				'parent' => 'bkx-developer-debug',
				'title'  => __( 'API Explorer', 'bkx-developer-sdk' ),
				'href'   => admin_url( 'edit.php?post_type=bkx_booking&page=bkx-developer-sdk&tab=api-explorer' ),
			)
		);

		$admin_bar->add_node(
			array(
				'id'     => 'bkx-debug-hooks',
				'parent' => 'bkx-developer-debug',
				'title'  => __( 'Hook Inspector', 'bkx-developer-sdk' ),
				'href'   => admin_url( 'edit.php?post_type=bkx_booking&page=bkx-developer-sdk&tab=hooks' ),
			)
		);
	}

	/**
	 * Get available code templates.
	 *
	 * @return array Templates.
	 */
	public function get_code_templates(): array {
		return array(
			'addon'           => array(
				'label'       => __( 'Add-on Boilerplate', 'bkx-developer-sdk' ),
				'description' => __( 'Generate a complete add-on plugin structure.', 'bkx-developer-sdk' ),
				'params'      => array( 'name', 'slug', 'namespace', 'description' ),
			),
			'payment_gateway' => array(
				'label'       => __( 'Payment Gateway', 'bkx-developer-sdk' ),
				'description' => __( 'Generate a payment gateway integration.', 'bkx-developer-sdk' ),
				'params'      => array( 'name', 'slug', 'api_url' ),
			),
			'rest_endpoint'   => array(
				'label'       => __( 'REST Endpoint', 'bkx-developer-sdk' ),
				'description' => __( 'Generate a custom REST API endpoint.', 'bkx-developer-sdk' ),
				'params'      => array( 'route', 'methods', 'callback' ),
			),
			'shortcode'       => array(
				'label'       => __( 'Shortcode', 'bkx-developer-sdk' ),
				'description' => __( 'Generate a shortcode with attributes.', 'bkx-developer-sdk' ),
				'params'      => array( 'tag', 'attributes' ),
			),
			'widget'          => array(
				'label'       => __( 'Widget', 'bkx-developer-sdk' ),
				'description' => __( 'Generate a WordPress widget.', 'bkx-developer-sdk' ),
				'params'      => array( 'name', 'description', 'fields' ),
			),
			'metabox'         => array(
				'label'       => __( 'Meta Box', 'bkx-developer-sdk' ),
				'description' => __( 'Generate a meta box for bookings.', 'bkx-developer-sdk' ),
				'params'      => array( 'id', 'title', 'fields' ),
			),
			'email_template'  => array(
				'label'       => __( 'Email Template', 'bkx-developer-sdk' ),
				'description' => __( 'Generate a custom email template.', 'bkx-developer-sdk' ),
				'params'      => array( 'name', 'subject', 'trigger' ),
			),
			'cron_job'        => array(
				'label'       => __( 'Cron Job', 'bkx-developer-sdk' ),
				'description' => __( 'Generate a scheduled task.', 'bkx-developer-sdk' ),
				'params'      => array( 'name', 'schedule', 'callback' ),
			),
			'hook_listener'   => array(
				'label'       => __( 'Hook Listener', 'bkx-developer-sdk' ),
				'description' => __( 'Generate hook integration code.', 'bkx-developer-sdk' ),
				'params'      => array( 'hook', 'priority', 'callback' ),
			),
		);
	}
}
