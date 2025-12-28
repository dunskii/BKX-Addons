<?php
/**
 * Main Beauty & Wellness Addon Class
 *
 * @package BookingX\BeautyWellness
 * @since   1.0.0
 */

namespace BookingX\BeautyWellness;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\BeautyWellness\Admin\SettingsPage;
use BookingX\BeautyWellness\Admin\ClientProfileMetabox;
use BookingX\BeautyWellness\Admin\TreatmentMetabox;
use BookingX\BeautyWellness\Services\TreatmentMenuService;
use BookingX\BeautyWellness\Services\ClientPreferencesService;
use BookingX\BeautyWellness\Services\ServiceAddonsService;
use BookingX\BeautyWellness\Services\PortfolioService;

/**
 * Beauty & Wellness Addon main class.
 *
 * @since 1.0.0
 */
class BeautyWellnessAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Singleton instance.
	 *
	 * @var BeautyWellnessAddon|null
	 */
	private static ?BeautyWellnessAddon $instance = null;

	/**
	 * Treatment menu service.
	 *
	 * @var TreatmentMenuService
	 */
	private TreatmentMenuService $treatment_service;

	/**
	 * Client preferences service.
	 *
	 * @var ClientPreferencesService
	 */
	private ClientPreferencesService $preferences_service;

	/**
	 * Service add-ons service.
	 *
	 * @var ServiceAddonsService
	 */
	private ServiceAddonsService $addons_service;

	/**
	 * Portfolio service.
	 *
	 * @var PortfolioService
	 */
	private PortfolioService $portfolio_service;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return BeautyWellnessAddon
	 */
	public static function get_instance(): BeautyWellnessAddon {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->id             = 'beauty_wellness';
		$this->name           = __( 'Beauty & Wellness Suite', 'bkx-beauty-wellness' );
		$this->version        = BKX_BEAUTY_WELLNESS_VERSION;
		$this->file           = BKX_BEAUTY_WELLNESS_FILE;
		$this->settings_key   = 'bkx_beauty_wellness_settings';
		$this->license_key    = 'bkx_beauty_wellness_license_key';
		$this->license_status = 'bkx_beauty_wellness_license_status';
		$this->store_url      = 'https://developer.com';
		$this->item_id        = 0;

		$this->init();
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		$this->load_settings();
		$this->init_services();

		// Register custom taxonomies.
		add_action( 'init', array( $this, 'register_taxonomies' ) );

		// Admin.
		if ( is_admin() ) {
			new SettingsPage( $this );
			new ClientProfileMetabox( $this );
			new TreatmentMetabox( $this );

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Booking form modifications.
		add_filter( 'bkx_booking_form_fields', array( $this, 'add_beauty_fields' ) );
		add_action( 'bkx_after_booking_created', array( $this, 'save_client_preferences' ), 10, 2 );

		// Service add-ons.
		add_filter( 'bkx_service_extras', array( $this, 'add_service_addons' ), 10, 2 );
		add_filter( 'bkx_booking_total', array( $this, 'calculate_addons_total' ), 10, 2 );

		// Staff portfolio.
		add_filter( 'bkx_seat_display_data', array( $this, 'add_portfolio_data' ), 10, 2 );

		// AJAX handlers.
		$this->register_ajax_handlers();

		// Shortcodes.
		$this->register_shortcodes();
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_services(): void {
		$this->treatment_service   = new TreatmentMenuService();
		$this->preferences_service = new ClientPreferencesService();
		$this->addons_service      = new ServiceAddonsService();
		$this->portfolio_service   = new PortfolioService();
	}

	/**
	 * Register custom taxonomies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_taxonomies(): void {
		// Treatment Categories.
		register_taxonomy(
			'bkx_treatment_category',
			'bkx_base',
			array(
				'labels'            => array(
					'name'          => __( 'Treatment Categories', 'bkx-beauty-wellness' ),
					'singular_name' => __( 'Treatment Category', 'bkx-beauty-wellness' ),
					'add_new_item'  => __( 'Add New Category', 'bkx-beauty-wellness' ),
					'edit_item'     => __( 'Edit Category', 'bkx-beauty-wellness' ),
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'treatment-category' ),
			)
		);

		// Skin Types.
		register_taxonomy(
			'bkx_skin_type',
			'bkx_base',
			array(
				'labels'       => array(
					'name'          => __( 'Skin Types', 'bkx-beauty-wellness' ),
					'singular_name' => __( 'Skin Type', 'bkx-beauty-wellness' ),
				),
				'hierarchical' => false,
				'show_ui'      => true,
				'rewrite'      => array( 'slug' => 'skin-type' ),
			)
		);

		// Specializations (for stylists).
		register_taxonomy(
			'bkx_specialization',
			'bkx_seat',
			array(
				'labels'            => array(
					'name'          => __( 'Specializations', 'bkx-beauty-wellness' ),
					'singular_name' => __( 'Specialization', 'bkx-beauty-wellness' ),
				),
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'specialization' ),
			)
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_admin_scripts(): void {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Admin pages.
		if ( strpos( $screen->id, 'bkx-beauty-wellness' ) !== false ||
			 in_array( $screen->post_type, array( 'bkx_base', 'bkx_seat', 'bkx_booking' ), true ) ) {

			wp_enqueue_style(
				'bkx-beauty-wellness-admin',
				BKX_BEAUTY_WELLNESS_URL . 'assets/css/admin.css',
				array(),
				BKX_BEAUTY_WELLNESS_VERSION
			);

			wp_enqueue_script(
				'bkx-beauty-wellness-admin',
				BKX_BEAUTY_WELLNESS_URL . 'assets/js/admin.js',
				array( 'jquery', 'wp-media-uploader' ),
				BKX_BEAUTY_WELLNESS_VERSION,
				true
			);

			wp_localize_script( 'bkx-beauty-wellness-admin', 'bkxBeautyAdmin', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_beauty_wellness_admin' ),
				'i18n'    => array(
					'selectImage'      => __( 'Select Image', 'bkx-beauty-wellness' ),
					'useThisImage'     => __( 'Use This Image', 'bkx-beauty-wellness' ),
					'confirmDelete'    => __( 'Are you sure you want to delete this?', 'bkx-beauty-wellness' ),
					'uploadBefore'     => __( 'Upload Before Photo', 'bkx-beauty-wellness' ),
					'uploadAfter'      => __( 'Upload After Photo', 'bkx-beauty-wellness' ),
				),
			) );
		}
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$settings = $this->get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-beauty-wellness',
			BKX_BEAUTY_WELLNESS_URL . 'assets/css/frontend.css',
			array(),
			BKX_BEAUTY_WELLNESS_VERSION
		);

		wp_enqueue_script(
			'bkx-beauty-wellness',
			BKX_BEAUTY_WELLNESS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_BEAUTY_WELLNESS_VERSION,
			true
		);

		wp_localize_script( 'bkx-beauty-wellness', 'bkxBeauty', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bkx_beauty_wellness_nonce' ),
			'i18n'    => array(
				'selectAddons' => __( 'Select add-ons', 'bkx-beauty-wellness' ),
				'addonsTotal'  => __( 'Add-ons total:', 'bkx-beauty-wellness' ),
			),
		) );
	}

	/**
	 * Add beauty-specific fields to booking form.
	 *
	 * @since 1.0.0
	 * @param array $fields Existing form fields.
	 * @return array
	 */
	public function add_beauty_fields( array $fields ): array {
		$settings = $this->get_settings();

		// Skin type selection.
		if ( ! empty( $settings['skin_type_tracking'] ) ) {
			$fields['skin_type'] = array(
				'type'     => 'select',
				'label'    => __( 'Skin Type', 'bkx-beauty-wellness' ),
				'options'  => array(
					''        => __( 'Select skin type', 'bkx-beauty-wellness' ),
					'normal'  => __( 'Normal', 'bkx-beauty-wellness' ),
					'dry'     => __( 'Dry', 'bkx-beauty-wellness' ),
					'oily'    => __( 'Oily', 'bkx-beauty-wellness' ),
					'combo'   => __( 'Combination', 'bkx-beauty-wellness' ),
					'sensitive' => __( 'Sensitive', 'bkx-beauty-wellness' ),
				),
				'priority' => 50,
			);
		}

		// Allergy information.
		if ( ! empty( $settings['allergy_alerts'] ) ) {
			$fields['allergies'] = array(
				'type'        => 'textarea',
				'label'       => __( 'Allergies & Sensitivities', 'bkx-beauty-wellness' ),
				'placeholder' => __( 'Please list any allergies or product sensitivities...', 'bkx-beauty-wellness' ),
				'priority'    => 51,
			);
		}

		// Special requests.
		$fields['special_requests'] = array(
			'type'        => 'textarea',
			'label'       => __( 'Special Requests', 'bkx-beauty-wellness' ),
			'placeholder' => __( 'Any specific preferences or requests...', 'bkx-beauty-wellness' ),
			'priority'    => 52,
		);

		return $fields;
	}

	/**
	 * Save client preferences after booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function save_client_preferences( int $booking_id, array $booking_data ): void {
		$customer_id = get_post_meta( $booking_id, 'customer_id', true );

		if ( ! $customer_id ) {
			return;
		}

		$preferences = array();

		if ( ! empty( $booking_data['skin_type'] ) ) {
			$preferences['skin_type'] = sanitize_text_field( $booking_data['skin_type'] );
		}

		if ( ! empty( $booking_data['allergies'] ) ) {
			$preferences['allergies'] = sanitize_textarea_field( $booking_data['allergies'] );
		}

		if ( ! empty( $booking_data['special_requests'] ) ) {
			$preferences['special_requests'] = sanitize_textarea_field( $booking_data['special_requests'] );
		}

		if ( ! empty( $preferences ) ) {
			$this->preferences_service->save_preferences( $customer_id, $preferences );
		}
	}

	/**
	 * Add service add-ons to service.
	 *
	 * @since 1.0.0
	 * @param array $extras     Existing extras.
	 * @param int   $service_id Service ID.
	 * @return array
	 */
	public function add_service_addons( array $extras, int $service_id ): array {
		$service_addons = $this->addons_service->get_service_addons( $service_id );

		foreach ( $service_addons as $addon ) {
			$extras[] = array(
				'id'          => 'beauty_addon_' . $addon['id'],
				'name'        => $addon['name'],
				'description' => $addon['description'],
				'price'       => $addon['price'],
				'duration'    => $addon['duration'],
				'type'        => 'beauty_addon',
			);
		}

		return $extras;
	}

	/**
	 * Calculate add-ons total.
	 *
	 * @since 1.0.0
	 * @param float $total        Current total.
	 * @param array $booking_data Booking data.
	 * @return float
	 */
	public function calculate_addons_total( float $total, array $booking_data ): float {
		if ( empty( $booking_data['beauty_addons'] ) ) {
			return $total;
		}

		foreach ( $booking_data['beauty_addons'] as $addon_id ) {
			$addon = $this->addons_service->get_addon( $addon_id );
			if ( $addon ) {
				$total += floatval( $addon['price'] );
			}
		}

		return $total;
	}

	/**
	 * Add portfolio data to staff display.
	 *
	 * @since 1.0.0
	 * @param array $data    Staff data.
	 * @param int   $seat_id Staff/seat ID.
	 * @return array
	 */
	public function add_portfolio_data( array $data, int $seat_id ): array {
		$settings = $this->get_settings();

		if ( empty( $settings['enable_stylist_portfolio'] ) ) {
			return $data;
		}

		$portfolio = $this->portfolio_service->get_portfolio( $seat_id );

		if ( ! empty( $portfolio ) ) {
			$data['portfolio'] = $portfolio;
		}

		$specializations = wp_get_post_terms( $seat_id, 'bkx_specialization', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $specializations ) && ! empty( $specializations ) ) {
			$data['specializations'] = $specializations;
		}

		return $data;
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		// Frontend.
		add_action( 'wp_ajax_bkx_beauty_get_addons', array( $this, 'ajax_get_addons' ) );
		add_action( 'wp_ajax_nopriv_bkx_beauty_get_addons', array( $this, 'ajax_get_addons' ) );

		add_action( 'wp_ajax_bkx_beauty_get_client_prefs', array( $this, 'ajax_get_client_preferences' ) );

		// Admin.
		add_action( 'wp_ajax_bkx_beauty_save_addon', array( $this, 'ajax_save_addon' ) );
		add_action( 'wp_ajax_bkx_beauty_delete_addon', array( $this, 'ajax_delete_addon' ) );
		add_action( 'wp_ajax_bkx_beauty_save_portfolio', array( $this, 'ajax_save_portfolio' ) );
	}

	/**
	 * AJAX: Get service add-ons.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_addons(): void {
		check_ajax_referer( 'bkx_beauty_wellness_nonce', 'nonce' );

		$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'bkx-beauty-wellness' ) ) );
		}

		$addons = $this->addons_service->get_service_addons( $service_id );

		wp_send_json_success( array( 'addons' => $addons ) );
	}

	/**
	 * AJAX: Get client preferences.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_client_preferences(): void {
		check_ajax_referer( 'bkx_beauty_wellness_nonce', 'nonce' );

		$customer_id = get_current_user_id();

		if ( ! $customer_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', 'bkx-beauty-wellness' ) ) );
		}

		$preferences = $this->preferences_service->get_preferences( $customer_id );

		wp_send_json_success( array( 'preferences' => $preferences ) );
	}

	/**
	 * AJAX: Save service add-on (admin).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_save_addon(): void {
		check_ajax_referer( 'bkx_beauty_wellness_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-beauty-wellness' ) ) );
		}

		$data = array(
			'service_id'  => isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0,
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'price'       => isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0,
			'duration'    => isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : 0,
		);

		$addon_id = isset( $_POST['addon_id'] ) ? absint( $_POST['addon_id'] ) : 0;

		$result = $this->addons_service->save_addon( $data, $addon_id );

		if ( $result ) {
			wp_send_json_success( array(
				'addon_id' => $result,
				'message'  => __( 'Add-on saved.', 'bkx-beauty-wellness' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save add-on.', 'bkx-beauty-wellness' ) ) );
		}
	}

	/**
	 * AJAX: Delete service add-on (admin).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_delete_addon(): void {
		check_ajax_referer( 'bkx_beauty_wellness_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-beauty-wellness' ) ) );
		}

		$addon_id = isset( $_POST['addon_id'] ) ? absint( $_POST['addon_id'] ) : 0;

		if ( ! $addon_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid add-on.', 'bkx-beauty-wellness' ) ) );
		}

		$result = $this->addons_service->delete_addon( $addon_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Add-on deleted.', 'bkx-beauty-wellness' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to delete add-on.', 'bkx-beauty-wellness' ) ) );
		}
	}

	/**
	 * AJAX: Save portfolio item (admin).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_save_portfolio(): void {
		check_ajax_referer( 'bkx_beauty_wellness_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-beauty-wellness' ) ) );
		}

		$data = array(
			'seat_id'      => isset( $_POST['seat_id'] ) ? absint( $_POST['seat_id'] ) : 0,
			'title'        => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'  => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'before_image' => isset( $_POST['before_image'] ) ? absint( $_POST['before_image'] ) : 0,
			'after_image'  => isset( $_POST['after_image'] ) ? absint( $_POST['after_image'] ) : 0,
			'service_id'   => isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0,
		);

		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		$result = $this->portfolio_service->save_item( $data, $item_id );

		if ( $result ) {
			wp_send_json_success( array(
				'item_id' => $result,
				'message' => __( 'Portfolio item saved.', 'bkx-beauty-wellness' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save portfolio item.', 'bkx-beauty-wellness' ) ) );
		}
	}

	/**
	 * Register shortcodes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_shortcodes(): void {
		add_shortcode( 'bkx_treatment_menu', array( $this, 'shortcode_treatment_menu' ) );
		add_shortcode( 'bkx_stylist_portfolio', array( $this, 'shortcode_portfolio' ) );
	}

	/**
	 * Shortcode: Treatment menu.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_treatment_menu( $atts ): string {
		$atts = shortcode_atts( array(
			'category' => '',
			'columns'  => 3,
		), $atts );

		ob_start();
		include BKX_BEAUTY_WELLNESS_PATH . 'templates/treatment-menu.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: Stylist portfolio.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_portfolio( $atts ): string {
		$atts = shortcode_atts( array(
			'stylist_id' => 0,
			'limit'      => 6,
		), $atts );

		ob_start();
		include BKX_BEAUTY_WELLNESS_PATH . 'templates/portfolio.php';
		return ob_get_clean();
	}

	/**
	 * Get treatment service.
	 *
	 * @since 1.0.0
	 * @return TreatmentMenuService
	 */
	public function get_treatment_service(): TreatmentMenuService {
		return $this->treatment_service;
	}

	/**
	 * Get preferences service.
	 *
	 * @since 1.0.0
	 * @return ClientPreferencesService
	 */
	public function get_preferences_service(): ClientPreferencesService {
		return $this->preferences_service;
	}

	/**
	 * Get add-ons service.
	 *
	 * @since 1.0.0
	 * @return ServiceAddonsService
	 */
	public function get_addons_service(): ServiceAddonsService {
		return $this->addons_service;
	}

	/**
	 * Get portfolio service.
	 *
	 * @since 1.0.0
	 * @return PortfolioService
	 */
	public function get_portfolio_service(): PortfolioService {
		return $this->portfolio_service;
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enabled'                   => 1,
			'enable_treatment_menu'     => 1,
			'enable_client_preferences' => 1,
			'enable_service_addons'     => 1,
			'enable_stylist_portfolio'  => 1,
			'enable_consultation_form'  => 1,
			'skin_type_tracking'        => 1,
			'allergy_alerts'            => 1,
			'product_recommendations'   => 1,
			'before_after_photos'       => 1,
		);
	}
}
