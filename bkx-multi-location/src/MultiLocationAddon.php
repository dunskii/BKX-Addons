<?php
/**
 * Main Multi-Location Management addon class.
 *
 * @package BookingX\MultiLocation
 */

namespace BookingX\MultiLocation;

defined( 'ABSPATH' ) || exit;

/**
 * MultiLocationAddon class.
 */
class MultiLocationAddon {

	/**
	 * Single instance.
	 *
	 * @var MultiLocationAddon
	 */
	private static $instance = null;

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get singleton instance.
	 *
	 * @return MultiLocationAddon
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
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['locations'] = new Services\LocationService();
		$this->services['hours']     = new Services\HoursService();
		$this->services['staff']     = new Services\StaffService();
		$this->services['services']  = new Services\ServiceAvailability();
		$this->services['resources'] = new Services\ResourceService();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Settings integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_locations', array( $this, 'render_settings_tab' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_ml_save_location', array( $this, 'ajax_save_location' ) );
		add_action( 'wp_ajax_bkx_ml_delete_location', array( $this, 'ajax_delete_location' ) );
		add_action( 'wp_ajax_bkx_ml_get_location', array( $this, 'ajax_get_location' ) );
		add_action( 'wp_ajax_bkx_ml_save_hours', array( $this, 'ajax_save_hours' ) );
		add_action( 'wp_ajax_bkx_ml_save_holiday', array( $this, 'ajax_save_holiday' ) );
		add_action( 'wp_ajax_bkx_ml_delete_holiday', array( $this, 'ajax_delete_holiday' ) );
		add_action( 'wp_ajax_bkx_ml_assign_staff', array( $this, 'ajax_assign_staff' ) );
		add_action( 'wp_ajax_bkx_ml_remove_staff', array( $this, 'ajax_remove_staff' ) );
		add_action( 'wp_ajax_bkx_ml_save_service', array( $this, 'ajax_save_service' ) );
		add_action( 'wp_ajax_bkx_ml_save_resource', array( $this, 'ajax_save_resource' ) );
		add_action( 'wp_ajax_bkx_ml_delete_resource', array( $this, 'ajax_delete_resource' ) );
		add_action( 'wp_ajax_bkx_ml_reorder_locations', array( $this, 'ajax_reorder_locations' ) );
		add_action( 'wp_ajax_bkx_ml_geocode_address', array( $this, 'ajax_geocode_address' ) );

		// Frontend AJAX (for location selector).
		add_action( 'wp_ajax_bkx_ml_get_locations', array( $this, 'ajax_get_locations' ) );
		add_action( 'wp_ajax_nopriv_bkx_ml_get_locations', array( $this, 'ajax_get_locations' ) );
		add_action( 'wp_ajax_bkx_ml_get_location_availability', array( $this, 'ajax_get_availability' ) );
		add_action( 'wp_ajax_nopriv_bkx_ml_get_location_availability', array( $this, 'ajax_get_availability' ) );

		// BookingX integration hooks.
		add_filter( 'bkx_booking_form_fields', array( $this, 'add_location_field' ), 5 );
		add_filter( 'bkx_available_seats', array( $this, 'filter_seats_by_location' ), 10, 2 );
		add_filter( 'bkx_available_services', array( $this, 'filter_services_by_location' ), 10, 2 );
		add_filter( 'bkx_service_price', array( $this, 'apply_location_price' ), 10, 3 );
		add_filter( 'bkx_service_duration', array( $this, 'apply_location_duration' ), 10, 3 );
		add_action( 'bkx_booking_created', array( $this, 'save_booking_location' ), 10, 2 );

		// Availability hooks.
		add_filter( 'bkx_seat_availability', array( $this, 'filter_availability_by_location' ), 10, 3 );
		add_filter( 'bkx_business_hours', array( $this, 'get_location_hours' ), 10, 2 );
		add_filter( 'bkx_holidays', array( $this, 'get_location_holidays' ), 10, 2 );

		// Admin columns.
		add_filter( 'manage_bkx_booking_posts_columns', array( $this, 'add_location_column' ) );
		add_action( 'manage_bkx_booking_posts_custom_column', array( $this, 'render_location_column' ), 10, 2 );
		add_filter( 'manage_edit-bkx_booking_sortable_columns', array( $this, 'make_location_sortable' ) );

		// Meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_booking_meta_box' ) );
		add_action( 'save_post_bkx_booking', array( $this, 'save_booking_meta_box' ) );

		// Shortcodes.
		add_shortcode( 'bkx_location_selector', array( $this, 'render_location_selector' ) );
		add_shortcode( 'bkx_location_map', array( $this, 'render_location_map' ) );
		add_shortcode( 'bkx_location_list', array( $this, 'render_location_list' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Cron.
		add_action( 'bkx_ml_daily_cleanup', array( $this, 'daily_cleanup' ) );

		if ( ! wp_next_scheduled( 'bkx_ml_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_ml_daily_cleanup' );
		}
	}

	/**
	 * Get a service.
	 *
	 * @param string $name Service name.
	 * @return mixed
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Locations', 'bkx-multi-location' ),
			__( 'Locations', 'bkx-multi-location' ),
			'manage_options',
			'bkx-locations',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bkx_booking_page_bkx-locations' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-ml-admin',
			BKX_ML_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_ML_VERSION
		);

		wp_enqueue_script(
			'bkx-ml-admin',
			BKX_ML_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			BKX_ML_VERSION,
			true
		);

		wp_localize_script(
			'bkx-ml-admin',
			'bkxML',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'bkx_ml_admin' ),
				'strings'         => array(
					'confirm_delete'       => __( 'Are you sure you want to delete this location? This cannot be undone.', 'bkx-multi-location' ),
					'confirm_delete_holiday' => __( 'Delete this holiday?', 'bkx-multi-location' ),
					'saving'               => __( 'Saving...', 'bkx-multi-location' ),
					'saved'                => __( 'Saved!', 'bkx-multi-location' ),
					'error'                => __( 'An error occurred. Please try again.', 'bkx-multi-location' ),
					'geocoding'            => __( 'Looking up address...', 'bkx-multi-location' ),
					'geocode_error'        => __( 'Could not find coordinates for this address.', 'bkx-multi-location' ),
				),
				'google_maps_key' => get_option( 'bkx_ml_google_maps_key', '' ),
			)
		);

		// Google Maps for geocoding.
		$maps_key = get_option( 'bkx_ml_google_maps_key', '' );
		if ( $maps_key ) {
			wp_enqueue_script(
				'google-maps',
				'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $maps_key ) . '&libraries=places',
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				true
			);
		}
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'bkx-ml-frontend',
			BKX_ML_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			BKX_ML_VERSION
		);

		wp_enqueue_script(
			'bkx-ml-frontend',
			BKX_ML_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_ML_VERSION,
			true
		);

		wp_localize_script(
			'bkx-ml-frontend',
			'bkxMLFront',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_ml_frontend' ),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = sanitize_text_field( $_GET['tab'] ?? 'locations' );
		include BKX_ML_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['locations'] = __( 'Locations', 'bkx-multi-location' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		include BKX_ML_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * AJAX: Save location.
	 */
	public function ajax_save_location() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$location_id = absint( $_POST['location_id'] ?? 0 );
		$data        = array(
			'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
			'slug'           => sanitize_title( $_POST['slug'] ?? $_POST['name'] ?? '' ),
			'description'    => sanitize_textarea_field( $_POST['description'] ?? '' ),
			'address_line_1' => sanitize_text_field( $_POST['address_line_1'] ?? '' ),
			'address_line_2' => sanitize_text_field( $_POST['address_line_2'] ?? '' ),
			'city'           => sanitize_text_field( $_POST['city'] ?? '' ),
			'state'          => sanitize_text_field( $_POST['state'] ?? '' ),
			'postal_code'    => sanitize_text_field( $_POST['postal_code'] ?? '' ),
			'country'        => sanitize_text_field( $_POST['country'] ?? '' ),
			'latitude'       => floatval( $_POST['latitude'] ?? 0 ),
			'longitude'      => floatval( $_POST['longitude'] ?? 0 ),
			'phone'          => sanitize_text_field( $_POST['phone'] ?? '' ),
			'email'          => sanitize_email( $_POST['email'] ?? '' ),
			'timezone'       => sanitize_text_field( $_POST['timezone'] ?? '' ),
			'status'         => sanitize_text_field( $_POST['status'] ?? 'active' ),
		);

		if ( empty( $data['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Location name is required.', 'bkx-multi-location' ) ) );
		}

		$result = $this->services['locations']->save( $location_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Location saved successfully.', 'bkx-multi-location' ),
				'location_id' => $result,
			)
		);
	}

	/**
	 * AJAX: Delete location.
	 */
	public function ajax_delete_location() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$location_id = absint( $_POST['location_id'] ?? 0 );

		if ( ! $location_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid location ID.', 'bkx-multi-location' ) ) );
		}

		// Check if this is the only location.
		$count = $this->services['locations']->count();
		if ( $count <= 1 ) {
			wp_send_json_error( array( 'message' => __( 'Cannot delete the last location. At least one location is required.', 'bkx-multi-location' ) ) );
		}

		$result = $this->services['locations']->delete( $location_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Location deleted.', 'bkx-multi-location' ) ) );
	}

	/**
	 * AJAX: Get location data.
	 */
	public function ajax_get_location() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$location_id = absint( $_POST['location_id'] ?? 0 );
		$location    = $this->services['locations']->get( $location_id );

		if ( ! $location ) {
			wp_send_json_error( array( 'message' => __( 'Location not found.', 'bkx-multi-location' ) ) );
		}

		// Get related data.
		$location->hours     = $this->services['hours']->get_for_location( $location_id );
		$location->holidays  = $this->services['hours']->get_holidays( $location_id );
		$location->staff     = $this->services['staff']->get_for_location( $location_id );
		$location->services  = $this->services['services']->get_for_location( $location_id );
		$location->resources = $this->services['resources']->get_for_location( $location_id );

		wp_send_json_success( array( 'location' => $location ) );
	}

	/**
	 * AJAX: Save hours.
	 */
	public function ajax_save_hours() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$location_id = absint( $_POST['location_id'] ?? 0 );
		$hours       = isset( $_POST['hours'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['hours'] ) ) : array();

		if ( ! $location_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid location ID.', 'bkx-multi-location' ) ) );
		}

		$result = $this->services['hours']->save( $location_id, $hours );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Hours saved.', 'bkx-multi-location' ) ) );
	}

	/**
	 * AJAX: Save holiday.
	 */
	public function ajax_save_holiday() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$location_id  = absint( $_POST['location_id'] ?? 0 );
		$name         = sanitize_text_field( $_POST['name'] ?? '' );
		$date         = sanitize_text_field( $_POST['date'] ?? '' );
		$is_recurring = absint( $_POST['is_recurring'] ?? 0 );

		if ( ! $location_id || ! $name || ! $date ) {
			wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'bkx-multi-location' ) ) );
		}

		$result = $this->services['hours']->add_holiday( $location_id, $name, $date, $is_recurring );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Holiday added.', 'bkx-multi-location' ),
				'holiday_id' => $result,
			)
		);
	}

	/**
	 * AJAX: Delete holiday.
	 */
	public function ajax_delete_holiday() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$holiday_id = absint( $_POST['holiday_id'] ?? 0 );

		if ( ! $holiday_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid holiday ID.', 'bkx-multi-location' ) ) );
		}

		$this->services['hours']->delete_holiday( $holiday_id );

		wp_send_json_success( array( 'message' => __( 'Holiday deleted.', 'bkx-multi-location' ) ) );
	}

	/**
	 * AJAX: Assign staff to location.
	 */
	public function ajax_assign_staff() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$location_id = absint( $_POST['location_id'] ?? 0 );
		$seat_id     = absint( $_POST['seat_id'] ?? 0 );
		$is_primary  = absint( $_POST['is_primary'] ?? 0 );

		if ( ! $location_id || ! $seat_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'bkx-multi-location' ) ) );
		}

		$result = $this->services['staff']->assign( $location_id, $seat_id, $is_primary );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Staff assigned.', 'bkx-multi-location' ) ) );
	}

	/**
	 * AJAX: Remove staff from location.
	 */
	public function ajax_remove_staff() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$location_id = absint( $_POST['location_id'] ?? 0 );
		$seat_id     = absint( $_POST['seat_id'] ?? 0 );

		if ( ! $location_id || ! $seat_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'bkx-multi-location' ) ) );
		}

		$this->services['staff']->remove( $location_id, $seat_id );

		wp_send_json_success( array( 'message' => __( 'Staff removed.', 'bkx-multi-location' ) ) );
	}

	/**
	 * AJAX: Save service availability.
	 */
	public function ajax_save_service() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$location_id       = absint( $_POST['location_id'] ?? 0 );
		$base_id           = absint( $_POST['base_id'] ?? 0 );
		$is_available      = absint( $_POST['is_available'] ?? 1 );
		$price_override    = isset( $_POST['price_override'] ) && '' !== $_POST['price_override'] ? floatval( $_POST['price_override'] ) : null;
		$duration_override = isset( $_POST['duration_override'] ) && '' !== $_POST['duration_override'] ? absint( $_POST['duration_override'] ) : null;

		if ( ! $location_id || ! $base_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'bkx-multi-location' ) ) );
		}

		$result = $this->services['services']->save(
			$location_id,
			$base_id,
			array(
				'is_available'      => $is_available,
				'price_override'    => $price_override,
				'duration_override' => $duration_override,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Service settings saved.', 'bkx-multi-location' ) ) );
	}

	/**
	 * AJAX: Save resource.
	 */
	public function ajax_save_resource() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$resource_id = absint( $_POST['resource_id'] ?? 0 );
		$data        = array(
			'location_id' => absint( $_POST['location_id'] ?? 0 ),
			'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
			'type'        => sanitize_text_field( $_POST['type'] ?? 'room' ),
			'capacity'    => absint( $_POST['capacity'] ?? 1 ),
			'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
			'status'      => sanitize_text_field( $_POST['status'] ?? 'active' ),
		);

		if ( ! $data['location_id'] || ! $data['name'] ) {
			wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'bkx-multi-location' ) ) );
		}

		$result = $this->services['resources']->save( $resource_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Resource saved.', 'bkx-multi-location' ),
				'resource_id' => $result,
			)
		);
	}

	/**
	 * AJAX: Delete resource.
	 */
	public function ajax_delete_resource() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$resource_id = absint( $_POST['resource_id'] ?? 0 );

		if ( ! $resource_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid resource ID.', 'bkx-multi-location' ) ) );
		}

		$this->services['resources']->delete( $resource_id );

		wp_send_json_success( array( 'message' => __( 'Resource deleted.', 'bkx-multi-location' ) ) );
	}

	/**
	 * AJAX: Reorder locations.
	 */
	public function ajax_reorder_locations() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$order = isset( $_POST['order'] ) ? array_map( 'absint', $_POST['order'] ) : array();

		if ( empty( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order data.', 'bkx-multi-location' ) ) );
		}

		$this->services['locations']->reorder( $order );

		wp_send_json_success( array( 'message' => __( 'Order saved.', 'bkx-multi-location' ) ) );
	}

	/**
	 * AJAX: Geocode address.
	 */
	public function ajax_geocode_address() {
		check_ajax_referer( 'bkx_ml_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-multi-location' ) ) );
		}

		$address  = sanitize_text_field( $_POST['address'] ?? '' );
		$maps_key = get_option( 'bkx_ml_google_maps_key', '' );

		if ( ! $address ) {
			wp_send_json_error( array( 'message' => __( 'Address is required.', 'bkx-multi-location' ) ) );
		}

		if ( ! $maps_key ) {
			wp_send_json_error( array( 'message' => __( 'Google Maps API key not configured.', 'bkx-multi-location' ) ) );
		}

		$url      = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query(
			array(
				'address' => $address,
				'key'     => $maps_key,
			)
		);
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 'OK' !== $body['status'] || empty( $body['results'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Address not found.', 'bkx-multi-location' ) ) );
		}

		$result = $body['results'][0];

		wp_send_json_success(
			array(
				'latitude'          => $result['geometry']['location']['lat'],
				'longitude'         => $result['geometry']['location']['lng'],
				'formatted_address' => $result['formatted_address'],
			)
		);
	}

	/**
	 * AJAX: Get locations (frontend).
	 */
	public function ajax_get_locations() {
		$locations = $this->services['locations']->get_all( array( 'status' => 'active' ) );

		wp_send_json_success( array( 'locations' => $locations ) );
	}

	/**
	 * AJAX: Get location availability.
	 */
	public function ajax_get_availability() {
		$location_id = absint( $_POST['location_id'] ?? 0 );
		$date        = sanitize_text_field( $_POST['date'] ?? '' );
		$service_id  = absint( $_POST['service_id'] ?? 0 );

		if ( ! $location_id || ! $date ) {
			wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'bkx-multi-location' ) ) );
		}

		$location = $this->services['locations']->get( $location_id );
		if ( ! $location ) {
			wp_send_json_error( array( 'message' => __( 'Location not found.', 'bkx-multi-location' ) ) );
		}

		// Get available staff for this location.
		$staff = $this->services['staff']->get_available_for_location( $location_id, $date, $service_id );

		// Get available time slots.
		$slots = $this->services['hours']->get_available_slots( $location_id, $date, $service_id );

		wp_send_json_success(
			array(
				'location' => $location,
				'staff'    => $staff,
				'slots'    => $slots,
			)
		);
	}

	/**
	 * Add location field to booking form.
	 *
	 * @param array $fields Form fields.
	 * @return array
	 */
	public function add_location_field( $fields ) {
		$locations = $this->services['locations']->get_all( array( 'status' => 'active' ) );

		if ( count( $locations ) > 1 ) {
			$options = array();
			foreach ( $locations as $location ) {
				$options[ $location->id ] = $location->name;
			}

			$location_field = array(
				'id'       => 'bkx_location',
				'type'     => 'select',
				'label'    => __( 'Location', 'bkx-multi-location' ),
				'options'  => $options,
				'required' => true,
				'priority' => 5,
			);

			array_unshift( $fields, $location_field );
		}

		return $fields;
	}

	/**
	 * Filter seats by location.
	 *
	 * @param array $seats     Available seats.
	 * @param array $args      Query arguments.
	 * @return array
	 */
	public function filter_seats_by_location( $seats, $args ) {
		$location_id = $args['location_id'] ?? ( $_POST['bkx_location'] ?? 0 );
		$location_id = absint( $location_id );

		if ( ! $location_id ) {
			return $seats;
		}

		$location_seats = $this->services['staff']->get_seat_ids_for_location( $location_id );

		if ( empty( $location_seats ) ) {
			return $seats;
		}

		return array_filter(
			$seats,
			function ( $seat ) use ( $location_seats ) {
				$seat_id = is_object( $seat ) ? $seat->ID : $seat;
				return in_array( $seat_id, $location_seats, true );
			}
		);
	}

	/**
	 * Filter services by location.
	 *
	 * @param array $services  Available services.
	 * @param array $args      Query arguments.
	 * @return array
	 */
	public function filter_services_by_location( $services, $args ) {
		$location_id = $args['location_id'] ?? ( $_POST['bkx_location'] ?? 0 );
		$location_id = absint( $location_id );

		if ( ! $location_id ) {
			return $services;
		}

		$available = $this->services['services']->get_available_base_ids( $location_id );

		return array_filter(
			$services,
			function ( $service ) use ( $available ) {
				$service_id = is_object( $service ) ? $service->ID : $service;
				return in_array( $service_id, $available, true );
			}
		);
	}

	/**
	 * Apply location-specific price.
	 *
	 * @param float $price      Original price.
	 * @param int   $service_id Service ID.
	 * @param array $args       Additional arguments.
	 * @return float
	 */
	public function apply_location_price( $price, $service_id, $args ) {
		$location_id = $args['location_id'] ?? 0;

		if ( ! $location_id ) {
			return $price;
		}

		$override = $this->services['services']->get_price_override( $location_id, $service_id );

		return null !== $override ? $override : $price;
	}

	/**
	 * Apply location-specific duration.
	 *
	 * @param int   $duration   Original duration.
	 * @param int   $service_id Service ID.
	 * @param array $args       Additional arguments.
	 * @return int
	 */
	public function apply_location_duration( $duration, $service_id, $args ) {
		$location_id = $args['location_id'] ?? 0;

		if ( ! $location_id ) {
			return $duration;
		}

		$override = $this->services['services']->get_duration_override( $location_id, $service_id );

		return null !== $override ? $override : $duration;
	}

	/**
	 * Save location to booking meta.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function save_booking_location( $booking_id, $booking_data ) {
		$location_id = absint( $booking_data['bkx_location'] ?? 0 );

		if ( $location_id ) {
			update_post_meta( $booking_id, '_bkx_location_id', $location_id );
		}
	}

	/**
	 * Filter availability by location hours.
	 *
	 * @param array  $availability Current availability.
	 * @param int    $seat_id      Seat ID.
	 * @param string $date         Date.
	 * @return array
	 */
	public function filter_availability_by_location( $availability, $seat_id, $date ) {
		// Get primary location for this seat.
		$location_id = $this->services['staff']->get_primary_location( $seat_id );

		if ( ! $location_id ) {
			return $availability;
		}

		// Check if location is open on this date.
		if ( ! $this->services['hours']->is_open( $location_id, $date ) ) {
			return array();
		}

		return $availability;
	}

	/**
	 * Get location-specific business hours.
	 *
	 * @param array $hours       Default hours.
	 * @param int   $location_id Location ID.
	 * @return array
	 */
	public function get_location_hours( $hours, $location_id ) {
		if ( ! $location_id ) {
			return $hours;
		}

		$location_hours = $this->services['hours']->get_for_location( $location_id );

		if ( empty( $location_hours ) ) {
			return $hours;
		}

		$formatted = array();
		foreach ( $location_hours as $hour ) {
			$formatted[ $hour->day_of_week ] = array(
				'open'        => $hour->is_open,
				'open_time'   => $hour->open_time,
				'close_time'  => $hour->close_time,
				'break_start' => $hour->break_start,
				'break_end'   => $hour->break_end,
			);
		}

		return $formatted;
	}

	/**
	 * Get location-specific holidays.
	 *
	 * @param array $holidays    Default holidays.
	 * @param int   $location_id Location ID.
	 * @return array
	 */
	public function get_location_holidays( $holidays, $location_id ) {
		if ( ! $location_id ) {
			return $holidays;
		}

		$location_holidays = $this->services['hours']->get_holidays( $location_id );

		$dates = array();
		foreach ( $location_holidays as $holiday ) {
			$dates[] = $holiday->date;
		}

		return array_merge( $holidays, $dates );
	}

	/**
	 * Add location column to bookings list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_location_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['bkx_location'] = __( 'Location', 'bkx-multi-location' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render location column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_location_column( $column, $post_id ) {
		if ( 'bkx_location' !== $column ) {
			return;
		}

		$location_id = get_post_meta( $post_id, '_bkx_location_id', true );

		if ( $location_id ) {
			$location = $this->services['locations']->get( $location_id );
			if ( $location ) {
				echo esc_html( $location->name );
				return;
			}
		}

		echo '<span class="bkx-muted">—</span>';
	}

	/**
	 * Make location column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function make_location_sortable( $columns ) {
		$columns['bkx_location'] = 'bkx_location';
		return $columns;
	}

	/**
	 * Add booking meta box.
	 */
	public function add_booking_meta_box() {
		add_meta_box(
			'bkx_booking_location',
			__( 'Location', 'bkx-multi-location' ),
			array( $this, 'render_booking_meta_box' ),
			'bkx_booking',
			'side',
			'high'
		);
	}

	/**
	 * Render booking meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_booking_meta_box( $post ) {
		$location_id = get_post_meta( $post->ID, '_bkx_location_id', true );
		$locations   = $this->services['locations']->get_all();

		wp_nonce_field( 'bkx_ml_booking_location', 'bkx_ml_booking_nonce' );
		?>
		<select name="bkx_location_id" style="width: 100%;">
			<option value=""><?php esc_html_e( '— Select Location —', 'bkx-multi-location' ); ?></option>
			<?php foreach ( $locations as $location ) : ?>
				<option value="<?php echo esc_attr( $location->id ); ?>" <?php selected( $location_id, $location->id ); ?>>
					<?php echo esc_html( $location->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Save booking meta box.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_booking_meta_box( $post_id ) {
		if ( ! isset( $_POST['bkx_ml_booking_nonce'] ) || ! wp_verify_nonce( $_POST['bkx_ml_booking_nonce'], 'bkx_ml_booking_location' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$location_id = absint( $_POST['bkx_location_id'] ?? 0 );

		if ( $location_id ) {
			update_post_meta( $post_id, '_bkx_location_id', $location_id );
		} else {
			delete_post_meta( $post_id, '_bkx_location_id' );
		}
	}

	/**
	 * Render location selector shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_location_selector( $atts ) {
		$atts = shortcode_atts(
			array(
				'style'       => 'dropdown',
				'show_map'    => 'no',
				'redirect_to' => '',
			),
			$atts
		);

		$locations = $this->services['locations']->get_all( array( 'status' => 'active' ) );

		ob_start();
		include BKX_ML_PLUGIN_DIR . 'templates/frontend/location-selector.php';
		return ob_get_clean();
	}

	/**
	 * Render location map shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_location_map( $atts ) {
		$atts = shortcode_atts(
			array(
				'height'      => '400px',
				'zoom'        => '10',
				'location_id' => '',
			),
			$atts
		);

		$maps_key = get_option( 'bkx_ml_google_maps_key', '' );
		if ( ! $maps_key ) {
			return '<p>' . esc_html__( 'Google Maps API key not configured.', 'bkx-multi-location' ) . '</p>';
		}

		if ( $atts['location_id'] ) {
			$locations = array( $this->services['locations']->get( absint( $atts['location_id'] ) ) );
		} else {
			$locations = $this->services['locations']->get_all( array( 'status' => 'active' ) );
		}

		$locations = array_filter(
			$locations,
			function ( $l ) {
				return $l && $l->latitude && $l->longitude;
			}
		);

		if ( empty( $locations ) ) {
			return '<p>' . esc_html__( 'No locations with coordinates found.', 'bkx-multi-location' ) . '</p>';
		}

		ob_start();
		include BKX_ML_PLUGIN_DIR . 'templates/frontend/location-map.php';
		return ob_get_clean();
	}

	/**
	 * Render location list shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_location_list( $atts ) {
		$atts = shortcode_atts(
			array(
				'columns'      => '3',
				'show_address' => 'yes',
				'show_phone'   => 'yes',
				'show_hours'   => 'yes',
			),
			$atts
		);

		$locations = $this->services['locations']->get_all( array( 'status' => 'active' ) );

		ob_start();
		include BKX_ML_PLUGIN_DIR . 'templates/frontend/location-list.php';
		return ob_get_clean();
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-multi-location/v1',
			'/locations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_locations' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bkx-multi-location/v1',
			'/locations/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_location' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			'bkx-multi-location/v1',
			'/locations/(?P<id>\d+)/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_availability' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'   => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'date' => array(
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * REST: Get all locations.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_locations( $request ) {
		$locations = $this->services['locations']->get_all( array( 'status' => 'active' ) );
		return rest_ensure_response( $locations );
	}

	/**
	 * REST: Get single location.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_get_location( $request ) {
		$location = $this->services['locations']->get( $request->get_param( 'id' ) );

		if ( ! $location ) {
			return new \WP_Error( 'not_found', __( 'Location not found.', 'bkx-multi-location' ), array( 'status' => 404 ) );
		}

		$location->hours = $this->services['hours']->get_for_location( $location->id );

		return rest_ensure_response( $location );
	}

	/**
	 * REST: Get location availability.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_get_availability( $request ) {
		$location_id = $request->get_param( 'id' );
		$date        = $request->get_param( 'date' );
		$service_id  = $request->get_param( 'service_id' );

		$location = $this->services['locations']->get( $location_id );

		if ( ! $location ) {
			return new \WP_Error( 'not_found', __( 'Location not found.', 'bkx-multi-location' ), array( 'status' => 404 ) );
		}

		$staff = $this->services['staff']->get_available_for_location( $location_id, $date, $service_id );
		$slots = $this->services['hours']->get_available_slots( $location_id, $date, $service_id );

		return rest_ensure_response(
			array(
				'location' => $location,
				'staff'    => $staff,
				'slots'    => $slots,
			)
		);
	}

	/**
	 * Daily cleanup task.
	 */
	public function daily_cleanup() {
		// Clean up old transients.
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_ml_%' AND option_name LIKE '%_timeout%' AND option_value < UNIX_TIMESTAMP()"
		);
	}
}
