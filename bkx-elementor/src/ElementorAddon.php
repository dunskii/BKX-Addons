<?php
/**
 * Main Elementor Addon Class.
 *
 * @package BookingX\Elementor
 * @since   1.0.0
 */

namespace BookingX\Elementor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ElementorAddon Class.
 */
class ElementorAddon {

	/**
	 * Instance.
	 *
	 * @var ElementorAddon
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
	 * @return ElementorAddon
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
		$this->settings = get_option( 'bkx_elementor_settings', array() );
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Register widget category.
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_widget_category' ) );

		// Register widgets.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		// Enqueue styles.
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'enqueue_frontend_styles' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'enqueue_editor_styles' ) );

		// Enqueue scripts.
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_frontend_scripts' ) );

		// Add custom controls.
		add_action( 'elementor/controls/register', array( $this, 'register_controls' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_elementor_get_services', array( $this, 'ajax_get_services' ) );
		add_action( 'wp_ajax_nopriv_bkx_elementor_get_services', array( $this, 'ajax_get_services' ) );
		add_action( 'wp_ajax_bkx_elementor_get_availability', array( $this, 'ajax_get_availability' ) );
		add_action( 'wp_ajax_nopriv_bkx_elementor_get_availability', array( $this, 'ajax_get_availability' ) );

		// Admin settings.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Register widget category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_widget_category( $elements_manager ) {
		$elements_manager->add_category(
			'bookingx',
			array(
				'title' => __( 'BookingX', 'bkx-elementor' ),
				'icon'  => 'fa fa-calendar',
			)
		);
	}

	/**
	 * Register widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets( $widgets_manager ) {
		// Booking Form Widget.
		if ( $this->get_setting( 'widget_booking_form', true ) ) {
			require_once BKX_ELEMENTOR_PATH . 'src/Widgets/BookingFormWidget.php';
			$widgets_manager->register( new Widgets\BookingFormWidget() );
		}

		// Services Grid Widget.
		if ( $this->get_setting( 'widget_services', true ) ) {
			require_once BKX_ELEMENTOR_PATH . 'src/Widgets/ServicesGridWidget.php';
			$widgets_manager->register( new Widgets\ServicesGridWidget() );
		}

		// Staff Carousel Widget.
		if ( $this->get_setting( 'widget_staff', true ) ) {
			require_once BKX_ELEMENTOR_PATH . 'src/Widgets/StaffCarouselWidget.php';
			$widgets_manager->register( new Widgets\StaffCarouselWidget() );
		}

		// Availability Calendar Widget.
		if ( $this->get_setting( 'widget_availability', true ) ) {
			require_once BKX_ELEMENTOR_PATH . 'src/Widgets/AvailabilityWidget.php';
			$widgets_manager->register( new Widgets\AvailabilityWidget() );
		}
	}

	/**
	 * Register controls.
	 *
	 * @param \Elementor\Controls_Manager $controls_manager Elementor controls manager.
	 */
	public function register_controls( $controls_manager ) {
		// Service selector control.
		require_once BKX_ELEMENTOR_PATH . 'src/Controls/ServiceSelectControl.php';
		$controls_manager->register( new Controls\ServiceSelectControl() );
	}

	/**
	 * Enqueue frontend styles.
	 */
	public function enqueue_frontend_styles() {
		wp_enqueue_style(
			'bkx-elementor-frontend',
			BKX_ELEMENTOR_URL . 'assets/css/frontend.css',
			array(),
			BKX_ELEMENTOR_VERSION
		);
	}

	/**
	 * Enqueue editor styles.
	 */
	public function enqueue_editor_styles() {
		wp_enqueue_style(
			'bkx-elementor-editor',
			BKX_ELEMENTOR_URL . 'assets/css/editor.css',
			array(),
			BKX_ELEMENTOR_VERSION
		);
	}

	/**
	 * Register frontend scripts.
	 */
	public function register_frontend_scripts() {
		wp_register_script(
			'bkx-elementor-frontend',
			BKX_ELEMENTOR_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_ELEMENTOR_VERSION,
			true
		);

		wp_localize_script( 'bkx-elementor-frontend', 'bkxElementor', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bkx_elementor_nonce' ),
			'i18n'    => array(
				'selectDate'    => __( 'Select a date', 'bkx-elementor' ),
				'selectTime'    => __( 'Select a time', 'bkx-elementor' ),
				'noSlots'       => __( 'No available slots', 'bkx-elementor' ),
				'loading'       => __( 'Loading...', 'bkx-elementor' ),
				'bookNow'       => __( 'Book Now', 'bkx-elementor' ),
				'bookingSuccess' => __( 'Booking confirmed!', 'bkx-elementor' ),
			),
		) );
	}

	/**
	 * AJAX: Get services.
	 */
	public function ajax_get_services() {
		check_ajax_referer( 'bkx_elementor_nonce', 'nonce' );

		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$result = array();

		foreach ( $services as $service ) {
			$result[] = array(
				'id'       => $service->ID,
				'title'    => $service->post_title,
				'price'    => get_post_meta( $service->ID, 'base_price', true ),
				'duration' => get_post_meta( $service->ID, 'base_time', true ),
				'image'    => get_the_post_thumbnail_url( $service->ID, 'medium' ),
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get availability.
	 */
	public function ajax_get_availability() {
		check_ajax_referer( 'bkx_elementor_nonce', 'nonce' );

		$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$seat_id    = isset( $_POST['seat_id'] ) ? absint( $_POST['seat_id'] ) : 0;
		$date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		if ( ! $service_id || ! $date ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bkx-elementor' ) ) );
		}

		// Get availability slots using BookingX function.
		$slots = array();

		if ( function_exists( 'bkx_get_available_slots' ) ) {
			$slots = bkx_get_available_slots( $seat_id, $service_id, $date );
		}

		wp_send_json_success( $slots );
	}

	/**
	 * Add settings page.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Elementor Integration', 'bkx-elementor' ),
			__( 'Elementor', 'bkx-elementor' ),
			'manage_options',
			'bkx-elementor',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'bkx_elementor_settings', 'bkx_elementor_settings', array( $this, 'sanitize_settings' ) );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input values.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$checkboxes = array(
			'enabled',
			'custom_styles',
			'ajax_booking',
			'widget_booking_form',
			'widget_services',
			'widget_staff',
			'widget_availability',
		);

		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		return $sanitized;
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Elementor Integration Settings', 'bkx-elementor' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'bkx_elementor_settings' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Available Widgets', 'bkx-elementor' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="bkx_elementor_settings[widget_booking_form]" value="1"
									<?php checked( $this->get_setting( 'widget_booking_form', true ) ); ?>>
								<?php esc_html_e( 'Booking Form Widget', 'bkx-elementor' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="bkx_elementor_settings[widget_services]" value="1"
									<?php checked( $this->get_setting( 'widget_services', true ) ); ?>>
								<?php esc_html_e( 'Services Grid Widget', 'bkx-elementor' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="bkx_elementor_settings[widget_staff]" value="1"
									<?php checked( $this->get_setting( 'widget_staff', true ) ); ?>>
								<?php esc_html_e( 'Staff Carousel Widget', 'bkx-elementor' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="bkx_elementor_settings[widget_availability]" value="1"
									<?php checked( $this->get_setting( 'widget_availability', true ) ); ?>>
								<?php esc_html_e( 'Availability Calendar Widget', 'bkx-elementor' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'AJAX Booking', 'bkx-elementor' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="bkx_elementor_settings[ajax_booking]" value="1"
									<?php checked( $this->get_setting( 'ajax_booking', true ) ); ?>>
								<?php esc_html_e( 'Enable AJAX booking submission', 'bkx-elementor' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
