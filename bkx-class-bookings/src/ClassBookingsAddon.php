<?php
/**
 * Class Bookings Addon
 *
 * @package BookingX\ClassBookings
 * @since   1.0.0
 */

namespace BookingX\ClassBookings;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\ClassBookings\Services\ClassService;
use BookingX\ClassBookings\Services\ScheduleService;
use BookingX\ClassBookings\Services\AttendanceService;
use BookingX\ClassBookings\Admin\SettingsPage;
use BookingX\ClassBookings\Admin\ClassMetabox;

/**
 * Class ClassBookingsAddon
 *
 * Main addon class for Class Bookings.
 *
 * @since 1.0.0
 */
class ClassBookingsAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $slug = 'bkx-class-bookings';

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
	protected string $name = 'Class Bookings';

	/**
	 * License product ID.
	 *
	 * @var int
	 */
	protected int $product_id = 113;

	/**
	 * Class service instance.
	 *
	 * @var ClassService
	 */
	private ClassService $class_service;

	/**
	 * Schedule service instance.
	 *
	 * @var ScheduleService
	 */
	private ScheduleService $schedule_service;

	/**
	 * Attendance service instance.
	 *
	 * @var AttendanceService
	 */
	private AttendanceService $attendance_service;

	/**
	 * Boot the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function boot(): void {
		$this->class_service      = new ClassService( $this );
		$this->schedule_service   = new ScheduleService( $this );
		$this->attendance_service = new AttendanceService( $this );

		// Register custom post type.
		add_action( 'init', array( $this, 'register_post_types' ) );

		// Admin.
		if ( is_admin() ) {
			$settings_page = new SettingsPage( $this );
			$class_metabox = new ClassMetabox( $this );

			add_action( 'admin_menu', array( $settings_page, 'add_menu' ) );
			add_action( 'add_meta_boxes', array( $class_metabox, 'add_metaboxes' ) );
			add_action( 'save_post_bkx_class', array( $class_metabox, 'save_metabox' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Shortcodes.
		add_shortcode( 'bkx_class_schedule', array( $this, 'render_schedule_shortcode' ) );
		add_shortcode( 'bkx_class_calendar', array( $this, 'render_calendar_shortcode' ) );
		add_shortcode( 'bkx_class_list', array( $this, 'render_class_list_shortcode' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_book_class', array( $this, 'ajax_book_class' ) );
		add_action( 'wp_ajax_nopriv_bkx_book_class', array( $this, 'ajax_book_class' ) );
		add_action( 'wp_ajax_bkx_cancel_class_booking', array( $this, 'ajax_cancel_booking' ) );
		add_action( 'wp_ajax_bkx_get_class_schedule', array( $this, 'ajax_get_schedule' ) );
		add_action( 'wp_ajax_nopriv_bkx_get_class_schedule', array( $this, 'ajax_get_schedule' ) );
		add_action( 'wp_ajax_bkx_mark_attendance', array( $this, 'ajax_mark_attendance' ) );

		// Integrate with BookingX.
		add_filter( 'bkx_booking_types', array( $this, 'add_class_booking_type' ) );
	}

	/**
	 * Register custom post types.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_types(): void {
		$labels = array(
			'name'               => _x( 'Classes', 'post type general name', 'bkx-class-bookings' ),
			'singular_name'      => _x( 'Class', 'post type singular name', 'bkx-class-bookings' ),
			'menu_name'          => _x( 'Classes', 'admin menu', 'bkx-class-bookings' ),
			'add_new'            => _x( 'Add New', 'class', 'bkx-class-bookings' ),
			'add_new_item'       => __( 'Add New Class', 'bkx-class-bookings' ),
			'edit_item'          => __( 'Edit Class', 'bkx-class-bookings' ),
			'new_item'           => __( 'New Class', 'bkx-class-bookings' ),
			'view_item'          => __( 'View Class', 'bkx-class-bookings' ),
			'search_items'       => __( 'Search Classes', 'bkx-class-bookings' ),
			'not_found'          => __( 'No classes found', 'bkx-class-bookings' ),
			'not_found_in_trash' => __( 'No classes found in Trash', 'bkx-class-bookings' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'class' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 56,
			'menu_icon'          => 'dashicons-groups',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'bkx_class', $args );

		// Register class category taxonomy.
		$cat_labels = array(
			'name'          => _x( 'Class Categories', 'taxonomy general name', 'bkx-class-bookings' ),
			'singular_name' => _x( 'Category', 'taxonomy singular name', 'bkx-class-bookings' ),
		);

		register_taxonomy(
			'bkx_class_category',
			'bkx_class',
			array(
				'labels'            => $cat_labels,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'class-category' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * AJAX: Book a class.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_book_class(): void {
		check_ajax_referer( 'bkx_class_bookings', 'nonce' );

		$schedule_id = absint( $_POST['schedule_id'] ?? 0 );
		$class_id    = absint( $_POST['class_id'] ?? 0 );
		$date        = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		$spots       = absint( $_POST['spots'] ?? 1 );
		$email       = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$phone       = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );

		if ( empty( $date ) || empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'bkx-class-bookings' ) ) );
		}

		$result = $this->class_service->book_class(
			array(
				'schedule_id' => $schedule_id,
				'class_id'    => $class_id,
				'date'        => $date,
				'spots'       => $spots,
				'email'       => $email,
				'name'        => $name,
				'phone'       => $phone,
				'user_id'     => get_current_user_id(),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Class booked successfully!', 'bkx-class-bookings' ),
				'booking_id' => $result,
			)
		);
	}

	/**
	 * AJAX: Cancel class booking.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_cancel_booking(): void {
		check_ajax_referer( 'bkx_class_bookings', 'nonce' );

		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		$token      = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

		$result = $this->class_service->cancel_booking( $booking_id, $token );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Booking cancelled.', 'bkx-class-bookings' ) ) );
	}

	/**
	 * AJAX: Get class schedule.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_schedule(): void {
		$class_id   = absint( $_GET['class_id'] ?? 0 );
		$start_date = sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) );
		$end_date   = sanitize_text_field( wp_unslash( $_GET['end'] ?? '' ) );

		if ( empty( $start_date ) ) {
			$start_date = gmdate( 'Y-m-d' );
		}
		if ( empty( $end_date ) ) {
			$end_date = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
		}

		$schedule = $this->schedule_service->get_schedule_for_range( $class_id, $start_date, $end_date );

		wp_send_json_success( array( 'schedule' => $schedule ) );
	}

	/**
	 * AJAX: Mark attendance.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_mark_attendance(): void {
		check_ajax_referer( 'bkx_class_bookings', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-class-bookings' ) ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		$attended   = ! empty( $_POST['attended'] );

		$result = $this->attendance_service->mark_attendance( $booking_id, $attended );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update attendance.', 'bkx-class-bookings' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * Render schedule shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_schedule_shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'class_id' => 0,
				'days'     => 7,
				'category' => '',
			),
			$atts
		);

		$schedule = $this->schedule_service->get_upcoming_schedule(
			absint( $atts['class_id'] ),
			absint( $atts['days'] ),
			sanitize_text_field( $atts['category'] )
		);

		ob_start();
		include BKX_CLASS_BOOKINGS_PATH . 'templates/schedule.php';
		return ob_get_clean();
	}

	/**
	 * Render calendar shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_calendar_shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'class_id' => 0,
				'category' => '',
			),
			$atts
		);

		ob_start();
		include BKX_CLASS_BOOKINGS_PATH . 'templates/calendar.php';
		return ob_get_clean();
	}

	/**
	 * Render class list shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_class_list_shortcode( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'category'   => '',
				'limit'      => 10,
				'instructor' => 0,
			),
			$atts
		);

		$args = array(
			'post_type'      => 'bkx_class',
			'post_status'    => 'publish',
			'posts_per_page' => absint( $atts['limit'] ),
		);

		if ( ! empty( $atts['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'bkx_class_category',
					'field'    => 'slug',
					'terms'    => sanitize_text_field( $atts['category'] ),
				),
			);
		}

		if ( ! empty( $atts['instructor'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_class_instructor',
					'value' => absint( $atts['instructor'] ),
				),
			);
		}

		$classes = get_posts( $args );

		ob_start();
		include BKX_CLASS_BOOKINGS_PATH . 'templates/class-list.php';
		return ob_get_clean();
	}

	/**
	 * Add class booking type to BookingX.
	 *
	 * @since 1.0.0
	 * @param array $types Booking types.
	 * @return array
	 */
	public function add_class_booking_type( array $types ): array {
		$types['class'] = __( 'Class Booking', 'bkx-class-bookings' );
		return $types;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		global $post_type;

		$allowed = array( 'toplevel_page_bkx-class-bookings', 'post.php', 'post-new.php' );

		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'bkx_class' !== $post_type ) {
			return;
		}

		wp_enqueue_style(
			'bkx-class-bookings-admin',
			BKX_CLASS_BOOKINGS_URL . 'assets/css/admin.css',
			array(),
			BKX_CLASS_BOOKINGS_VERSION
		);

		wp_enqueue_script(
			'bkx-class-bookings-admin',
			BKX_CLASS_BOOKINGS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_CLASS_BOOKINGS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-class-bookings-admin',
			'bkxClassBookings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_class_bookings' ),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		wp_enqueue_style(
			'bkx-class-bookings-frontend',
			BKX_CLASS_BOOKINGS_URL . 'assets/css/frontend.css',
			array(),
			BKX_CLASS_BOOKINGS_VERSION
		);

		wp_enqueue_script(
			'bkx-class-bookings-frontend',
			BKX_CLASS_BOOKINGS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_CLASS_BOOKINGS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-class-bookings-frontend',
			'bkxClassBookings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_class_bookings' ),
				'i18n'    => array(
					'booking'   => __( 'Booking...', 'bkx-class-bookings' ),
					'booked'    => __( 'Booked!', 'bkx-class-bookings' ),
					'error'     => __( 'An error occurred.', 'bkx-class-bookings' ),
					'full'      => __( 'Class is full', 'bkx-class-bookings' ),
					'spotsLeft' => __( 'spots left', 'bkx-class-bookings' ),
				),
			)
		);
	}

	/**
	 * Get class service.
	 *
	 * @since 1.0.0
	 * @return ClassService
	 */
	public function get_class_service(): ClassService {
		return $this->class_service;
	}

	/**
	 * Get schedule service.
	 *
	 * @since 1.0.0
	 * @return ScheduleService
	 */
	public function get_schedule_service(): ScheduleService {
		return $this->schedule_service;
	}

	/**
	 * Get attendance service.
	 *
	 * @since 1.0.0
	 * @return AttendanceService
	 */
	public function get_attendance_service(): AttendanceService {
		return $this->attendance_service;
	}
}
