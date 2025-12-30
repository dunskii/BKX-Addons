<?php
/**
 * Main Divi Addon Class.
 *
 * @package BookingX\Divi
 */

namespace BookingX\Divi;

defined( 'ABSPATH' ) || exit;

/**
 * DiviAddon class.
 */
class DiviAddon {

	/**
	 * Instance.
	 *
	 * @var DiviAddon
	 */
	private static $instance = null;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Get instance.
	 *
	 * @return DiviAddon
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
		$this->settings = get_option( 'bkx_divi_settings', array() );

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Register Divi modules.
		add_action( 'et_builder_ready', array( $this, 'register_modules' ) );

		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_divi_save_settings', array( $this, 'ajax_save_settings' ) );

		// Add settings tab to BookingX.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_divi', array( $this, 'render_settings_tab' ) );

		// Extend Divi Builder with custom categories.
		add_filter( 'et_builder_module_general_tab_output', array( $this, 'add_module_category' ), 10, 3 );

		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'bkx-divi',
			false,
			dirname( BKX_DIVI_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register Divi modules.
	 */
	public function register_modules() {
		if ( ! $this->get_setting( 'enable_modules', true ) ) {
			return;
		}

		// Load module files.
		$modules_dir = BKX_DIVI_PLUGIN_DIR . 'src/Modules/';

		if ( file_exists( $modules_dir . 'BookingForm.php' ) ) {
			require_once $modules_dir . 'BookingForm.php';
		}

		if ( file_exists( $modules_dir . 'ServiceList.php' ) ) {
			require_once $modules_dir . 'ServiceList.php';
		}

		if ( file_exists( $modules_dir . 'ResourceList.php' ) ) {
			require_once $modules_dir . 'ResourceList.php';
		}

		if ( file_exists( $modules_dir . 'AvailabilityCalendar.php' ) ) {
			require_once $modules_dir . 'AvailabilityCalendar.php';
		}

		if ( file_exists( $modules_dir . 'BookingButton.php' ) ) {
			require_once $modules_dir . 'BookingButton.php';
		}
	}

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Divi Integration', 'bkx-divi' ),
			__( 'Divi Integration', 'bkx-divi' ),
			'manage_options',
			'bkx-divi',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'bkx_booking_page_bkx-divi' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-divi-admin',
			BKX_DIVI_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_DIVI_VERSION
		);

		wp_enqueue_script(
			'bkx-divi-admin',
			BKX_DIVI_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_DIVI_VERSION,
			true
		);

		wp_localize_script(
			'bkx-divi-admin',
			'bkxDiviAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_divi_admin' ),
				'strings' => array(
					'settingsSaved' => __( 'Settings saved successfully.', 'bkx-divi' ),
					'error'         => __( 'An error occurred. Please try again.', 'bkx-divi' ),
					'cacheCeared'   => __( 'Divi cache cleared successfully.', 'bkx-divi' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend scripts.
	 */
	public function enqueue_frontend_scripts() {
		if ( ! $this->is_divi_builder_active() ) {
			return;
		}

		wp_enqueue_style(
			'bkx-divi-frontend',
			BKX_DIVI_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			BKX_DIVI_VERSION
		);

		// Add custom CSS if set.
		$custom_css = $this->get_setting( 'custom_css', '' );
		if ( ! empty( $custom_css ) ) {
			wp_add_inline_style( 'bkx-divi-frontend', $custom_css );
		}

		wp_enqueue_script(
			'bkx-divi-frontend',
			BKX_DIVI_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_DIVI_VERSION,
			true
		);

		wp_localize_script(
			'bkx-divi-frontend',
			'bkxDiviFrontend',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'animationEnabled' => $this->get_setting( 'animation_enabled', true ),
			)
		);
	}

	/**
	 * Check if Divi Builder is active on current page.
	 *
	 * @return bool
	 */
	private function is_divi_builder_active() {
		if ( function_exists( 'et_pb_is_pagebuilder_used' ) ) {
			return et_pb_is_pagebuilder_used( get_the_ID() );
		}
		return false;
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_DIVI_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Add settings tab to BookingX.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['divi'] = __( 'Divi', 'bkx-divi' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_DIVI_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * Add module category.
	 *
	 * @param string $output     Output.
	 * @param string $function   Function name.
	 * @param array  $attributes Attributes.
	 * @return string
	 */
	public function add_module_category( $output, $function, $attributes ) {
		return $output;
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_divi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-divi' ) );
		}

		$settings = array(
			'enable_modules'     => ! empty( $_POST['enable_modules'] ),
			'custom_css'         => isset( $_POST['custom_css'] ) ? wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ) ) : '',
			'booking_form_style' => isset( $_POST['booking_form_style'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_form_style'] ) ) : 'default',
			'calendar_style'     => isset( $_POST['calendar_style'] ) ? sanitize_text_field( wp_unslash( $_POST['calendar_style'] ) ) : 'default',
			'animation_enabled'  => ! empty( $_POST['animation_enabled'] ),
		);

		update_option( 'bkx_divi_settings', $settings );
		$this->settings = $settings;

		// Clear Divi cache.
		if ( function_exists( 'et_core_clear_transients' ) ) {
			et_core_clear_transients();
		}

		wp_send_json_success( __( 'Settings saved.', 'bkx-divi' ) );
	}

	/**
	 * Get setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Get all available services.
	 *
	 * @return array
	 */
	public function get_services() {
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array();
		foreach ( $services as $service ) {
			$options[ $service->ID ] = $service->post_title;
		}

		return $options;
	}

	/**
	 * Get all available resources.
	 *
	 * @return array
	 */
	public function get_resources() {
		$resources = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array();
		foreach ( $resources as $resource ) {
			$options[ $resource->ID ] = $resource->post_title;
		}

		return $options;
	}
}
