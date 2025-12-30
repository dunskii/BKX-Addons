<?php
/**
 * Main Multi-Tenant Addon Class.
 *
 * @package BookingX\MultiTenant
 */

namespace BookingX\MultiTenant;

defined( 'ABSPATH' ) || exit;

/**
 * MultiTenantAddon class.
 */
class MultiTenantAddon {

	/**
	 * Singleton instance.
	 *
	 * @var MultiTenantAddon
	 */
	private static $instance = null;

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Current tenant.
	 *
	 * @var object|null
	 */
	private $current_tenant = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MultiTenantAddon
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
		$this->init_services();
		$this->init_hooks();
		$this->detect_tenant();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['tenant_manager']  = new Services\TenantManager();
		$this->services['plan_manager']    = new Services\PlanManager();
		$this->services['user_manager']    = new Services\TenantUserManager();
		$this->services['usage_tracker']   = new Services\UsageTracker();
		$this->services['branding']        = new Services\BrandingService();
		$this->services['data_isolation']  = new Services\DataIsolationService();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Network admin hooks.
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'add_network_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		// Single site admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Tenant context hooks.
		add_action( 'init', array( $this, 'setup_tenant_context' ), 5 );

		// Data isolation.
		add_filter( 'bkx_booking_query_args', array( $this->services['data_isolation'], 'filter_booking_query' ) );
		add_filter( 'bkx_service_query_args', array( $this->services['data_isolation'], 'filter_service_query' ) );

		// Usage tracking.
		add_action( 'bkx_booking_created', array( $this->services['usage_tracker'], 'track_booking_created' ) );

		// Branding.
		if ( ! is_admin() ) {
			add_action( 'wp_head', array( $this->services['branding'], 'apply_custom_css' ) );
		}

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Limits enforcement.
		add_filter( 'bkx_can_create_booking', array( $this, 'check_booking_limit' ), 10, 2 );
	}

	/**
	 * Detect current tenant.
	 */
	private function detect_tenant() {
		if ( is_multisite() ) {
			$site_id = get_current_blog_id();
			$this->current_tenant = $this->services['tenant_manager']->get_tenant_by_site( $site_id );
		} else {
			// Single site mode - check domain or setting.
			$this->current_tenant = $this->services['tenant_manager']->get_tenant_by_domain( home_url() );
		}
	}

	/**
	 * Setup tenant context.
	 */
	public function setup_tenant_context() {
		if ( ! $this->current_tenant ) {
			return;
		}

		// Set tenant context for data isolation.
		$this->services['data_isolation']->set_tenant( $this->current_tenant );

		// Apply branding.
		$this->services['branding']->set_tenant( $this->current_tenant );

		// Fire action for extensions.
		do_action( 'bkx_tenant_context_set', $this->current_tenant );
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
	 * Get current tenant.
	 *
	 * @return object|null
	 */
	public function get_current_tenant() {
		return $this->current_tenant;
	}

	/**
	 * Add network admin menu.
	 */
	public function add_network_admin_menu() {
		add_menu_page(
			__( 'Multi-Tenant', 'bkx-multi-tenant' ),
			__( 'Multi-Tenant', 'bkx-multi-tenant' ),
			'manage_network_options',
			'bkx-multi-tenant',
			array( $this, 'render_network_admin_page' ),
			'dashicons-networking',
			30
		);

		add_submenu_page(
			'bkx-multi-tenant',
			__( 'Tenants', 'bkx-multi-tenant' ),
			__( 'Tenants', 'bkx-multi-tenant' ),
			'manage_network_options',
			'bkx-multi-tenant',
			array( $this, 'render_network_admin_page' )
		);

		add_submenu_page(
			'bkx-multi-tenant',
			__( 'Plans', 'bkx-multi-tenant' ),
			__( 'Plans', 'bkx-multi-tenant' ),
			'manage_network_options',
			'bkx-multi-tenant-plans',
			array( $this, 'render_plans_page' )
		);

		add_submenu_page(
			'bkx-multi-tenant',
			__( 'Usage', 'bkx-multi-tenant' ),
			__( 'Usage', 'bkx-multi-tenant' ),
			'manage_network_options',
			'bkx-multi-tenant-usage',
			array( $this, 'render_usage_page' )
		);

		add_submenu_page(
			'bkx-multi-tenant',
			__( 'Settings', 'bkx-multi-tenant' ),
			__( 'Settings', 'bkx-multi-tenant' ),
			'manage_network_options',
			'bkx-multi-tenant-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Add admin menu for single site.
	 */
	public function add_admin_menu() {
		if ( is_multisite() && ! is_main_site() ) {
			// Tenant-specific menu.
			add_submenu_page(
				'bookingx',
				__( 'Tenant Settings', 'bkx-multi-tenant' ),
				__( 'Tenant', 'bkx-multi-tenant' ),
				'manage_options',
				'bkx-tenant',
				array( $this, 'render_tenant_settings_page' )
			);
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-multi-tenant' ) === false && strpos( $hook, 'bkx-tenant' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-multi-tenant-admin',
			BKX_MULTI_TENANT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_MULTI_TENANT_VERSION
		);

		wp_enqueue_script(
			'bkx-multi-tenant-admin',
			BKX_MULTI_TENANT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			BKX_MULTI_TENANT_VERSION,
			true
		);

		wp_enqueue_style( 'wp-color-picker' );

		wp_localize_script(
			'bkx-multi-tenant-admin',
			'bkxMultiTenant',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_multi_tenant_admin' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Are you sure you want to delete this tenant? This action cannot be undone.', 'bkx-multi-tenant' ),
				),
			)
		);
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-multi-tenant/v1',
			'/tenants',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->services['tenant_manager'], 'rest_get_tenants' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this->services['tenant_manager'], 'rest_create_tenant' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			'bkx-multi-tenant/v1',
			'/tenants/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->services['tenant_manager'], 'rest_get_tenant' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this->services['tenant_manager'], 'rest_update_tenant' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this->services['tenant_manager'], 'rest_delete_tenant' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		register_rest_route(
			'bkx-multi-tenant/v1',
			'/tenants/(?P<id>\d+)/usage',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->services['usage_tracker'], 'rest_get_tenant_usage' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Check admin permission.
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_network_options' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Check booking limit.
	 *
	 * @param bool  $can_create Whether booking can be created.
	 * @param array $booking_data Booking data.
	 * @return bool
	 */
	public function check_booking_limit( $can_create, $booking_data ) {
		if ( ! $this->current_tenant ) {
			return $can_create;
		}

		$plan = $this->services['plan_manager']->get_tenant_plan( $this->current_tenant->id );
		if ( ! $plan ) {
			return $can_create;
		}

		$limits = json_decode( $plan->limits, true );
		if ( empty( $limits['bookings'] ) || $limits['bookings'] === -1 ) {
			return $can_create;
		}

		$current_usage = $this->services['usage_tracker']->get_current_usage(
			$this->current_tenant->id,
			'bookings',
			'monthly'
		);

		if ( $current_usage >= $limits['bookings'] ) {
			return new \WP_Error(
				'booking_limit_reached',
				__( 'Monthly booking limit reached. Please upgrade your plan.', 'bkx-multi-tenant' )
			);
		}

		return $can_create;
	}

	/**
	 * Render network admin page.
	 */
	public function render_network_admin_page() {
		include BKX_MULTI_TENANT_PLUGIN_DIR . 'templates/admin/network-dashboard.php';
	}

	/**
	 * Render plans page.
	 */
	public function render_plans_page() {
		include BKX_MULTI_TENANT_PLUGIN_DIR . 'templates/admin/plans.php';
	}

	/**
	 * Render usage page.
	 */
	public function render_usage_page() {
		include BKX_MULTI_TENANT_PLUGIN_DIR . 'templates/admin/usage.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		include BKX_MULTI_TENANT_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	/**
	 * Render tenant settings page.
	 */
	public function render_tenant_settings_page() {
		include BKX_MULTI_TENANT_PLUGIN_DIR . 'templates/admin/tenant-settings.php';
	}
}
