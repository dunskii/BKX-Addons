<?php
/**
 * Main Multiple Services Addon Class
 *
 * @package BookingX\MultipleServices
 * @since   1.0.0
 */

namespace BookingX\MultipleServices;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\MultipleServices\Services\BundleService;
use BookingX\MultipleServices\Services\DurationCalculator;
use BookingX\MultipleServices\Admin\SettingsPage;

/**
 * Main addon class for Multiple Services.
 *
 * @since 1.0.0
 */
class MultipleServicesAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasAjax;

	/**
	 * Bundle service instance.
	 *
	 * @var BundleService
	 */
	protected ?BundleService $bundle_service = null;

	/**
	 * Duration calculator instance.
	 *
	 * @var DurationCalculator
	 */
	protected ?DurationCalculator $duration_calculator = null;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Path to the main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		// Set addon properties
		$this->addon_id        = 'bkx_multiple_services';
		$this->addon_name      = __( 'BookingX - Multiple Services', 'bkx-multiple-services' );
		$this->version         = BKX_MULTIPLE_SERVICES_VERSION;
		$this->text_domain     = 'bkx-multiple-services';
		$this->min_bkx_version = '2.0.0';
		$this->min_php_version = '7.4';
		$this->min_wp_version  = '5.8';

		parent::__construct( $plugin_file );
	}

	/**
	 * Register with BookingX Framework.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function register_with_framework(): void {
		// Register settings tab
		add_filter( 'bkx_settings_tabs', array( $this, 'register_settings_tab' ) );

		// Register this addon as active
		add_filter( "bookingx_addon_{$this->addon_id}_active", '__return_true' );
	}

	/**
	 * Register settings tab.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function register_settings_tab( array $tabs ): array {
		$tabs['multiple_services'] = __( 'Multiple Services', 'bkx-multiple-services' );
		return $tabs;
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_hooks(): void {
		// Modify booking form to include service selector
		add_action( 'bkx_before_booking_form', array( $this, 'render_service_selector' ), 10, 1 );

		// Calculate total duration and price
		add_filter( 'bkx_booking_total_duration', array( $this, 'calculate_total_duration' ), 10, 2 );
		add_filter( 'bkx_booking_total_price', array( $this, 'calculate_total_price' ), 10, 2 );

		// Validate service combinations
		add_filter( 'bkx_validate_booking_data', array( $this, 'validate_service_combination' ), 10, 2 );

		// Save selected services
		add_action( 'bkx_booking_created', array( $this, 'save_selected_services' ), 10, 2 );

		// Check resource availability
		add_filter( 'bkx_check_availability', array( $this, 'check_multi_service_availability' ), 10, 3 );

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_admin(): void {
		// Initialize settings page
		$settings_page = new SettingsPage( $this );

		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add meta box to booking edit screen
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );

		// Add action links to plugin list
		add_filter( 'plugin_action_links_' . BKX_MULTIPLE_SERVICES_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Initialize frontend functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_frontend(): void {
		// Register AJAX actions
		$this->register_ajax_action( 'get_service_combinations', array( $this, 'ajax_get_service_combinations' ), true );
		$this->register_ajax_action( 'calculate_bundle_price', array( $this, 'ajax_calculate_bundle_price' ), true );
		$this->register_ajax_action( 'check_availability', array( $this, 'ajax_check_availability' ), true );
	}

	/**
	 * Get database migrations.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_migrations(): array {
		return array();
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enable_bundles'            => true,
			'bundle_discount_type'      => 'percentage',
			'bundle_discount_value'     => 10,
			'max_services_per_booking'  => 5,
			'duration_calculation_mode' => 'sequential',
			'allow_service_combinations' => 'all',
			'restricted_combinations'   => array(),
			'display_mode'              => 'checkbox',
			'show_bundle_pricing'       => true,
			'require_same_resource'     => false,
		);
	}

	/**
	 * Get settings fields.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_settings_fields(): array {
		return array(
			'enable_bundles'            => array( 'type' => 'checkbox' ),
			'bundle_discount_type'      => array( 'type' => 'select', 'options' => array( 'percentage' => 'Percentage', 'fixed' => 'Fixed Amount' ) ),
			'bundle_discount_value'     => array( 'type' => 'number' ),
			'max_services_per_booking'  => array( 'type' => 'integer' ),
			'duration_calculation_mode' => array( 'type' => 'select', 'options' => array( 'sequential' => 'Sequential', 'parallel' => 'Parallel', 'longest' => 'Longest Service' ) ),
			'allow_service_combinations' => array( 'type' => 'select', 'options' => array( 'all' => 'All Services', 'restricted' => 'Restricted List' ) ),
			'restricted_combinations'   => array( 'type' => 'json' ),
			'display_mode'              => array( 'type' => 'select', 'options' => array( 'checkbox' => 'Checkboxes', 'dropdown' => 'Dropdown' ) ),
			'show_bundle_pricing'       => array( 'type' => 'checkbox' ),
			'require_same_resource'     => array( 'type' => 'checkbox' ),
		);
	}

	/**
	 * Render service selector on booking form.
	 *
	 * @since 1.0.0
	 * @param int $base_id Service ID.
	 * @return void
	 */
	public function render_service_selector( $base_id ): void {
		$template_path = BKX_MULTIPLE_SERVICES_PATH . 'templates/frontend/service-selector.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Calculate total duration for multiple services.
	 *
	 * @since 1.0.0
	 * @param int   $duration      Current duration.
	 * @param array $booking_data  Booking data.
	 * @return int
	 */
	public function calculate_total_duration( $duration, $booking_data ): int {
		if ( empty( $booking_data['selected_services'] ) ) {
			return $duration;
		}

		return $this->get_duration_calculator()->calculate_total_duration(
			$booking_data['selected_services'],
			$this->get_setting( 'duration_calculation_mode', 'sequential' )
		);
	}

	/**
	 * Calculate total price for multiple services.
	 *
	 * @since 1.0.0
	 * @param float $price         Current price.
	 * @param array $booking_data  Booking data.
	 * @return float
	 */
	public function calculate_total_price( $price, $booking_data ): float {
		if ( empty( $booking_data['selected_services'] ) ) {
			return $price;
		}

		return $this->get_bundle_service()->calculate_bundle_price(
			$booking_data['selected_services'],
			$this->get_setting( 'enable_bundles', true ),
			$this->get_setting( 'bundle_discount_type', 'percentage' ),
			$this->get_setting( 'bundle_discount_value', 10 )
		);
	}

	/**
	 * Validate service combination.
	 *
	 * @since 1.0.0
	 * @param bool  $valid        Current validation status.
	 * @param array $booking_data Booking data.
	 * @return bool
	 */
	public function validate_service_combination( $valid, $booking_data ): bool {
		if ( ! $valid || empty( $booking_data['selected_services'] ) ) {
			return $valid;
		}

		$max_services = $this->get_setting( 'max_services_per_booking', 5 );
		if ( count( $booking_data['selected_services'] ) > $max_services ) {
			return false;
		}

		// Check restricted combinations
		if ( 'restricted' === $this->get_setting( 'allow_service_combinations', 'all' ) ) {
			$restricted = $this->get_setting( 'restricted_combinations', array() );
			if ( ! $this->get_bundle_service()->is_combination_allowed( $booking_data['selected_services'], $restricted ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Save selected services to booking meta.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function save_selected_services( $booking_id, $booking_data ): void {
		if ( ! empty( $booking_data['selected_services'] ) ) {
			update_post_meta( $booking_id, '_bkx_selected_services', $booking_data['selected_services'] );
			update_post_meta( $booking_id, '_bkx_is_multi_service_booking', true );
		}
	}

	/**
	 * Check availability for multi-service bookings.
	 *
	 * @since 1.0.0
	 * @param bool   $available    Current availability status.
	 * @param string $date         Booking date.
	 * @param array  $booking_data Booking data.
	 * @return bool
	 */
	public function check_multi_service_availability( $available, $date, $booking_data ): bool {
		if ( ! $available || empty( $booking_data['selected_services'] ) ) {
			return $available;
		}

		$require_same_resource = $this->get_setting( 'require_same_resource', false );

		return $this->get_bundle_service()->check_services_availability(
			$booking_data['selected_services'],
			$date,
			$booking_data['seat_id'] ?? 0,
			$require_same_resource
		);
	}

	/**
	 * AJAX: Get available service combinations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_service_combinations(): void {
		$base_id = $this->get_post_param( 'base_id', 0, 'int' );

		$combinations = $this->get_bundle_service()->get_available_combinations( $base_id );

		$this->ajax_success( array( 'combinations' => $combinations ) );
	}

	/**
	 * AJAX: Calculate bundle price.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_calculate_bundle_price(): void {
		$service_ids = $this->get_post_param( 'service_ids', array(), 'array' );

		if ( empty( $service_ids ) ) {
			$this->ajax_error( 'no_services', __( 'No services selected.', 'bkx-multiple-services' ) );
		}

		$price = $this->get_bundle_service()->calculate_bundle_price(
			$service_ids,
			$this->get_setting( 'enable_bundles', true ),
			$this->get_setting( 'bundle_discount_type', 'percentage' ),
			$this->get_setting( 'bundle_discount_value', 10 )
		);

		$this->ajax_success( array( 'price' => $price ) );
	}

	/**
	 * AJAX: Check availability for selected services.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_check_availability(): void {
		$service_ids = $this->get_post_param( 'service_ids', array(), 'array' );
		$date        = $this->get_post_param( 'date', '', 'string' );
		$seat_id     = $this->get_post_param( 'seat_id', 0, 'int' );

		if ( empty( $service_ids ) || empty( $date ) ) {
			$this->ajax_error( 'missing_data', __( 'Missing required data.', 'bkx-multiple-services' ) );
		}

		$available = $this->get_bundle_service()->check_services_availability(
			$service_ids,
			$date,
			$seat_id,
			$this->get_setting( 'require_same_resource', false )
		);

		$this->ajax_success( array( 'available' => $available ) );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ): void {
		// Only on settings page
		if ( 'bkx_booking_page_bkx_settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-multiple-services-admin',
			BKX_MULTIPLE_SERVICES_URL . 'assets/css/admin.css',
			array(),
			BKX_MULTIPLE_SERVICES_VERSION
		);

		wp_enqueue_script(
			'bkx-multiple-services-admin',
			BKX_MULTIPLE_SERVICES_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_MULTIPLE_SERVICES_VERSION,
			true
		);
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_scripts(): void {
		// Only enqueue on booking pages
		if ( ! is_page() && ! is_singular( 'bkx_base' ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-multiple-services-frontend',
			BKX_MULTIPLE_SERVICES_URL . 'assets/css/frontend.css',
			array(),
			BKX_MULTIPLE_SERVICES_VERSION
		);

		wp_enqueue_script(
			'bkx-multiple-services-frontend',
			BKX_MULTIPLE_SERVICES_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_MULTIPLE_SERVICES_VERSION,
			true
		);

		// Localize script with AJAX data
		$this->localize_ajax_data(
			'bkx-multiple-services-frontend',
			'bkxMultipleServices',
			array( 'get_service_combinations', 'calculate_bundle_price', 'check_availability' ),
			array(
				'settings' => array(
					'maxServices'   => $this->get_setting( 'max_services_per_booking', 5 ),
					'displayMode'   => $this->get_setting( 'display_mode', 'checkbox' ),
					'showPricing'   => $this->get_setting( 'show_bundle_pricing', true ),
				),
				'i18n' => array(
					'selectServices' => __( 'Select additional services', 'bkx-multiple-services' ),
					'maxReached'     => __( 'Maximum number of services reached', 'bkx-multiple-services' ),
					'calculating'    => __( 'Calculating price...', 'bkx-multiple-services' ),
				),
			)
		);
	}

	/**
	 * Register meta boxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'bkx_selected_services',
			__( 'Selected Services', 'bkx-multiple-services' ),
			array( $this, 'render_selected_services_meta_box' ),
			'bkx_booking',
			'side',
			'high'
		);
	}

	/**
	 * Render selected services meta box.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_selected_services_meta_box( \WP_Post $post ): void {
		$selected_services = get_post_meta( $post->ID, '_bkx_selected_services', true );

		if ( empty( $selected_services ) ) {
			echo '<p>' . esc_html__( 'No additional services selected.', 'bkx-multiple-services' ) . '</p>';
			return;
		}

		echo '<ul>';
		foreach ( $selected_services as $service_id ) {
			$service = get_post( $service_id );
			if ( $service ) {
				echo '<li>' . esc_html( $service->post_title ) . '</li>';
			}
		}
		echo '</ul>';
	}

	/**
	 * Add action links to plugin list.
	 *
	 * @since 1.0.0
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx_settings&tab=multiple_services' ) ),
			esc_html__( 'Settings', 'bkx-multiple-services' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Get bundle service instance.
	 *
	 * @since 1.0.0
	 * @return BundleService
	 */
	public function get_bundle_service(): BundleService {
		if ( ! $this->bundle_service ) {
			$this->bundle_service = new BundleService( $this );
		}

		return $this->bundle_service;
	}

	/**
	 * Get duration calculator instance.
	 *
	 * @since 1.0.0
	 * @return DurationCalculator
	 */
	public function get_duration_calculator(): DurationCalculator {
		if ( ! $this->duration_calculator ) {
			$this->duration_calculator = new DurationCalculator( $this );
		}

		return $this->duration_calculator;
	}
}
