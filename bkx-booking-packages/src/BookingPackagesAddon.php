<?php
/**
 * Booking Packages Addon
 *
 * @package BookingX\BookingPackages
 * @since   1.0.0
 */

namespace BookingX\BookingPackages;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\BookingPackages\Services\PackageService;
use BookingX\BookingPackages\Services\RedemptionService;
use BookingX\BookingPackages\Admin\SettingsPage;
use BookingX\BookingPackages\Admin\PackagesListTable;

/**
 * Main addon class.
 *
 * @since 1.0.0
 */
class BookingPackagesAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $slug = 'bkx-booking-packages';

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected string $version = '1.0.0';

	/**
	 * Addon name.
	 *
	 * @var string
	 */
	protected string $name = 'Booking Packages';

	/**
	 * Package service.
	 *
	 * @var PackageService
	 */
	protected PackageService $package_service;

	/**
	 * Redemption service.
	 *
	 * @var RedemptionService
	 */
	protected RedemptionService $redemption_service;

	/**
	 * Initialize the addon.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_settings( 'bkx_packages_settings' );
		$this->init_license( 'bkx_packages_license', 'https://bookingx.com', 91 );
		$this->init_database( BKX_PACKAGES_PATH . 'src/Migrations/' );

		// Initialize services.
		$this->package_service    = new PackageService( $this );
		$this->redemption_service = new RedemptionService( $this, $this->package_service );

		// Register hooks.
		$this->register_hooks();

		// Initialize admin.
		if ( is_admin() ) {
			new SettingsPage( $this );
		}

		// Register AJAX handlers.
		$this->register_ajax_handlers();

		// Register shortcodes.
		$this->register_shortcodes();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Register custom post type for packages.
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Booking form integration.
		add_filter( 'bkx_booking_form_fields', array( $this, 'add_package_field' ) );
		add_filter( 'bkx_booking_calculate_price', array( $this, 'apply_package_discount' ), 10, 3 );
		add_action( 'bkx_booking_created', array( $this, 'handle_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_completed', array( $this, 'handle_booking_completed' ), 10, 1 );

		// Customer account.
		add_filter( 'bkx_customer_account_tabs', array( $this, 'add_account_tab' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Package expiration check.
		add_action( 'bkx_daily_cron', array( $this, 'check_expired_packages' ) );
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	protected function register_ajax_handlers(): void {
		$this->register_ajax( 'bkx_get_customer_packages', array( $this, 'ajax_get_customer_packages' ) );
		$this->register_ajax( 'bkx_apply_package', array( $this, 'ajax_apply_package' ) );
		$this->register_ajax( 'bkx_purchase_package', array( $this, 'ajax_purchase_package' ) );
		$this->register_ajax( 'bkx_get_package_details', array( $this, 'ajax_get_package_details' ) );
		$this->register_ajax( 'bkx_admin_assign_package', array( $this, 'ajax_admin_assign_package' ), true );
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	protected function register_shortcodes(): void {
		add_shortcode( 'bkx_packages', array( $this, 'shortcode_packages' ) );
		add_shortcode( 'bkx_my_packages', array( $this, 'shortcode_my_packages' ) );
		add_shortcode( 'bkx_package_purchase', array( $this, 'shortcode_purchase' ) );
	}

	/**
	 * Register custom post type for packages.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => __( 'Packages', 'bkx-booking-packages' ),
			'singular_name'      => __( 'Package', 'bkx-booking-packages' ),
			'add_new'            => __( 'Add New', 'bkx-booking-packages' ),
			'add_new_item'       => __( 'Add New Package', 'bkx-booking-packages' ),
			'edit_item'          => __( 'Edit Package', 'bkx-booking-packages' ),
			'new_item'           => __( 'New Package', 'bkx-booking-packages' ),
			'view_item'          => __( 'View Package', 'bkx-booking-packages' ),
			'search_items'       => __( 'Search Packages', 'bkx-booking-packages' ),
			'not_found'          => __( 'No packages found', 'bkx-booking-packages' ),
			'not_found_in_trash' => __( 'No packages found in trash', 'bkx-booking-packages' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => 'bookingx',
			'query_var'           => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => null,
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
			'show_in_rest'        => true,
		);

		register_post_type( 'bkx_package', $args );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		wp_enqueue_style(
			'bkx-packages-frontend',
			BKX_PACKAGES_URL . 'assets/css/frontend.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'bkx-packages-frontend',
			BKX_PACKAGES_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'bkx-packages-frontend',
			'bkxPackages',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_packages_nonce' ),
				'i18n'     => array(
					'select_package' => __( 'Select a Package', 'bkx-booking-packages' ),
					'uses_remaining' => __( 'uses remaining', 'bkx-booking-packages' ),
					'expires'        => __( 'Expires', 'bkx-booking-packages' ),
					'unlimited'      => __( 'Unlimited', 'bkx-booking-packages' ),
					'loading'        => __( 'Loading...', 'bkx-booking-packages' ),
					'error'          => __( 'An error occurred', 'bkx-booking-packages' ),
				),
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();

		if ( ! $screen || ( 'bkx_package' !== $screen->post_type && 'bookingx_page_bkx-packages-settings' !== $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-packages-admin',
			BKX_PACKAGES_URL . 'assets/css/admin.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'bkx-packages-admin',
			BKX_PACKAGES_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'bkx-packages-admin',
			'bkxPackagesAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_packages_admin_nonce' ),
			)
		);
	}

	/**
	 * Check if assets should load.
	 *
	 * @return bool
	 */
	protected function should_load_assets(): bool {
		// Load on booking pages.
		if ( is_singular( 'bkx_seat' ) || is_singular( 'bkx_base' ) ) {
			return true;
		}

		// Load if shortcode present.
		global $post;
		if ( $post ) {
			if ( has_shortcode( $post->post_content, 'bookingx' ) ||
				 has_shortcode( $post->post_content, 'bkx_packages' ) ||
				 has_shortcode( $post->post_content, 'bkx_my_packages' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add package field to booking form.
	 *
	 * @param array $fields Form fields.
	 * @return array Modified fields.
	 */
	public function add_package_field( array $fields ): array {
		if ( ! is_user_logged_in() ) {
			return $fields;
		}

		$customer_id = get_current_user_id();
		$packages    = $this->package_service->get_customer_packages( $customer_id );

		if ( empty( $packages ) ) {
			return $fields;
		}

		$fields['package'] = array(
			'type'     => 'package_select',
			'label'    => __( 'Use Package', 'bkx-booking-packages' ),
			'priority' => 25,
			'packages' => $packages,
		);

		return $fields;
	}

	/**
	 * Apply package discount to booking price.
	 *
	 * @param float $price Original price.
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return float Modified price.
	 */
	public function apply_package_discount( float $price, int $booking_id, array $booking_data ): float {
		if ( empty( $booking_data['package_id'] ) ) {
			return $price;
		}

		$customer_package = $this->package_service->get_customer_package( $booking_data['package_id'] );

		if ( ! $customer_package || ! $this->package_service->is_package_valid( $customer_package ) ) {
			return $price;
		}

		// Package covers the full service.
		return 0;
	}

	/**
	 * Handle booking created.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function handle_booking_created( int $booking_id, array $booking_data ): void {
		if ( empty( $booking_data['package_id'] ) ) {
			return;
		}

		// Reserve the package usage.
		$this->redemption_service->reserve_usage( $booking_data['package_id'], $booking_id );

		// Store package reference on booking.
		update_post_meta( $booking_id, '_bkx_package_id', $booking_data['package_id'] );
	}

	/**
	 * Handle booking completed.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function handle_booking_completed( int $booking_id ): void {
		$package_id = get_post_meta( $booking_id, '_bkx_package_id', true );

		if ( ! $package_id ) {
			return;
		}

		// Confirm the redemption.
		$this->redemption_service->confirm_redemption( $package_id, $booking_id );
	}

	/**
	 * Add account tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array Modified tabs.
	 */
	public function add_account_tab( array $tabs ): array {
		$tabs['packages'] = array(
			'title'    => __( 'My Packages', 'bkx-booking-packages' ),
			'callback' => array( $this, 'render_account_tab' ),
			'priority' => 30,
		);

		return $tabs;
	}

	/**
	 * Render account tab.
	 *
	 * @return void
	 */
	public function render_account_tab(): void {
		$customer_id = get_current_user_id();
		$packages    = $this->package_service->get_customer_packages( $customer_id );

		include BKX_PACKAGES_PATH . 'templates/frontend/my-packages.php';
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'bookingx/v1',
			'/packages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_packages' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bookingx/v1',
			'/packages/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_package' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bookingx/v1',
			'/customer/packages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_customer_packages' ),
				'permission_callback' => array( $this, 'rest_customer_permission' ),
			)
		);
	}

	/**
	 * REST customer permission check.
	 *
	 * @return bool
	 */
	public function rest_customer_permission(): bool {
		return is_user_logged_in();
	}

	/**
	 * REST get packages.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_packages( \WP_REST_Request $request ): \WP_REST_Response {
		$packages = $this->package_service->get_available_packages( $request->get_params() );
		return new \WP_REST_Response( $packages, 200 );
	}

	/**
	 * REST get single package.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_package( \WP_REST_Request $request ): \WP_REST_Response {
		$package = $this->package_service->get_package( $request->get_param( 'id' ) );

		if ( ! $package ) {
			return new \WP_REST_Response( array( 'error' => 'Package not found' ), 404 );
		}

		return new \WP_REST_Response( $package, 200 );
	}

	/**
	 * REST get customer packages.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_customer_packages( \WP_REST_Request $request ): \WP_REST_Response {
		$packages = $this->package_service->get_customer_packages( get_current_user_id() );
		return new \WP_REST_Response( $packages, 200 );
	}

	/**
	 * AJAX get customer packages.
	 *
	 * @return void
	 */
	public function ajax_get_customer_packages(): void {
		check_ajax_referer( 'bkx_packages_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to view packages.', 'bkx-booking-packages' ) ) );
		}

		$packages = $this->package_service->get_customer_packages( get_current_user_id() );

		wp_send_json_success( $packages );
	}

	/**
	 * AJAX apply package to booking.
	 *
	 * @return void
	 */
	public function ajax_apply_package(): void {
		check_ajax_referer( 'bkx_packages_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-booking-packages' ) ) );
		}

		$package_id = absint( $_POST['package_id'] ?? 0 );
		$service_id = absint( $_POST['service_id'] ?? 0 );

		if ( ! $package_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package.', 'bkx-booking-packages' ) ) );
		}

		$customer_package = $this->package_service->get_customer_package( $package_id );

		if ( ! $customer_package ) {
			wp_send_json_error( array( 'message' => __( 'Package not found.', 'bkx-booking-packages' ) ) );
		}

		if ( ! $this->package_service->is_package_valid( $customer_package ) ) {
			wp_send_json_error( array( 'message' => __( 'Package is expired or has no remaining uses.', 'bkx-booking-packages' ) ) );
		}

		// Check if package applies to this service.
		if ( $service_id && ! $this->package_service->package_applies_to_service( $customer_package, $service_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Package does not apply to this service.', 'bkx-booking-packages' ) ) );
		}

		wp_send_json_success(
			array(
				'package_id'      => $package_id,
				'uses_remaining'  => $customer_package['uses_remaining'],
				'discount_type'   => 'full',
				'message'         => __( 'Package applied successfully.', 'bkx-booking-packages' ),
			)
		);
	}

	/**
	 * AJAX purchase package.
	 *
	 * @return void
	 */
	public function ajax_purchase_package(): void {
		check_ajax_referer( 'bkx_packages_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to purchase.', 'bkx-booking-packages' ) ) );
		}

		$package_id = absint( $_POST['package_id'] ?? 0 );

		if ( ! $package_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package.', 'bkx-booking-packages' ) ) );
		}

		$package = $this->package_service->get_package( $package_id );

		if ( ! $package ) {
			wp_send_json_error( array( 'message' => __( 'Package not found.', 'bkx-booking-packages' ) ) );
		}

		// Create checkout session or redirect to payment.
		$checkout_url = $this->package_service->create_purchase_session( $package_id, get_current_user_id() );

		if ( is_wp_error( $checkout_url ) ) {
			wp_send_json_error( array( 'message' => $checkout_url->get_error_message() ) );
		}

		wp_send_json_success( array( 'checkout_url' => $checkout_url ) );
	}

	/**
	 * AJAX get package details.
	 *
	 * @return void
	 */
	public function ajax_get_package_details(): void {
		check_ajax_referer( 'bkx_packages_nonce', 'nonce' );

		$package_id = absint( $_POST['package_id'] ?? 0 );

		if ( ! $package_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package.', 'bkx-booking-packages' ) ) );
		}

		$package = $this->package_service->get_package( $package_id );

		if ( ! $package ) {
			wp_send_json_error( array( 'message' => __( 'Package not found.', 'bkx-booking-packages' ) ) );
		}

		wp_send_json_success( $package );
	}

	/**
	 * AJAX admin assign package.
	 *
	 * @return void
	 */
	public function ajax_admin_assign_package(): void {
		check_ajax_referer( 'bkx_packages_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-booking-packages' ) ) );
		}

		$package_id  = absint( $_POST['package_id'] ?? 0 );
		$customer_id = absint( $_POST['customer_id'] ?? 0 );

		if ( ! $package_id || ! $customer_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bkx-booking-packages' ) ) );
		}

		$result = $this->package_service->assign_package_to_customer( $package_id, $customer_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Package assigned successfully.', 'bkx-booking-packages' ) ) );
	}

	/**
	 * Check expired packages.
	 *
	 * @return void
	 */
	public function check_expired_packages(): void {
		$this->package_service->expire_packages();
	}

	/**
	 * Shortcode: Display available packages.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function shortcode_packages( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'service_id' => 0,
				'columns'    => 3,
			),
			$atts,
			'bkx_packages'
		);

		$packages = $this->package_service->get_available_packages(
			array( 'service_id' => $atts['service_id'] )
		);

		ob_start();
		include BKX_PACKAGES_PATH . 'templates/frontend/packages-grid.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: Display customer's packages.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function shortcode_my_packages( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your packages.', 'bkx-booking-packages' ) . '</p>';
		}

		$packages = $this->package_service->get_customer_packages( get_current_user_id() );

		ob_start();
		include BKX_PACKAGES_PATH . 'templates/frontend/my-packages.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: Package purchase form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function shortcode_purchase( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'package_id' => 0,
			),
			$atts,
			'bkx_package_purchase'
		);

		if ( ! $atts['package_id'] ) {
			return '';
		}

		$package = $this->package_service->get_package( $atts['package_id'] );

		if ( ! $package ) {
			return '<p>' . esc_html__( 'Package not found.', 'bkx-booking-packages' ) . '</p>';
		}

		ob_start();
		include BKX_PACKAGES_PATH . 'templates/frontend/purchase-form.php';
		return ob_get_clean();
	}

	/**
	 * Get package service.
	 *
	 * @return PackageService
	 */
	public function get_package_service(): PackageService {
		return $this->package_service;
	}

	/**
	 * Get redemption service.
	 *
	 * @return RedemptionService
	 */
	public function get_redemption_service(): RedemptionService {
		return $this->redemption_service;
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	protected function get_default_settings(): array {
		return array(
			'enable_packages'     => true,
			'allow_partial_use'   => false,
			'show_on_booking'     => true,
			'default_validity'    => 365,
			'notify_expiring'     => true,
			'expiry_notice_days'  => 7,
			'allow_gifting'       => false,
			'allow_transfer'      => false,
		);
	}
}
