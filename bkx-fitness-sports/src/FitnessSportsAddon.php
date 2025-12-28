<?php
/**
 * Fitness & Sports Addon Main Class
 *
 * @package BookingX\FitnessSports
 * @since   1.0.0
 */

namespace BookingX\FitnessSports;

use BookingX\FitnessSports\Services\ClassScheduleService;
use BookingX\FitnessSports\Services\MembershipService;
use BookingX\FitnessSports\Services\EquipmentService;
use BookingX\FitnessSports\Services\PerformanceService;
use BookingX\FitnessSports\Admin\SettingsPage;
use BookingX\FitnessSports\Admin\ClassMetabox;
use BookingX\FitnessSports\Admin\TrainerMetabox;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FitnessSportsAddon
 *
 * @since 1.0.0
 */
class FitnessSportsAddon {

	/**
	 * Single instance of the class.
	 *
	 * @var FitnessSportsAddon|null
	 */
	private static ?FitnessSportsAddon $instance = null;

	/**
	 * Settings array.
	 *
	 * @var array
	 */
	private array $settings;

	/**
	 * Class schedule service.
	 *
	 * @var ClassScheduleService
	 */
	private ClassScheduleService $class_service;

	/**
	 * Membership service.
	 *
	 * @var MembershipService
	 */
	private MembershipService $membership_service;

	/**
	 * Equipment service.
	 *
	 * @var EquipmentService
	 */
	private EquipmentService $equipment_service;

	/**
	 * Performance service.
	 *
	 * @var PerformanceService
	 */
	private PerformanceService $performance_service;

	/**
	 * Get single instance.
	 *
	 * @since 1.0.0
	 * @return FitnessSportsAddon
	 */
	public static function get_instance(): FitnessSportsAddon {
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
		$this->settings = get_option( 'bkx_fitness_sports_settings', array() );

		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_services(): void {
		$this->class_service       = new ClassScheduleService();
		$this->membership_service  = new MembershipService();
		$this->equipment_service   = new EquipmentService();
		$this->performance_service = new PerformanceService();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks(): void {
		// Load textdomain.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Register custom post types.
		add_action( 'init', array( $this, 'register_post_types' ) );

		// Register taxonomies.
		add_action( 'init', array( $this, 'register_taxonomies' ) );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Initialize admin.
		if ( is_admin() ) {
			$this->init_admin();
		}

		// Register shortcodes.
		add_shortcode( 'bkx_class_schedule', array( $this->class_service, 'render_schedule' ) );
		add_shortcode( 'bkx_trainer_list', array( $this, 'render_trainer_list' ) );
		add_shortcode( 'bkx_equipment_booking', array( $this->equipment_service, 'render_booking_form' ) );
		add_shortcode( 'bkx_membership_plans', array( $this->membership_service, 'render_plans' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_book_class', array( $this, 'ajax_book_class' ) );
		add_action( 'wp_ajax_nopriv_bkx_book_class', array( $this, 'ajax_book_class' ) );
		add_action( 'wp_ajax_bkx_cancel_class_booking', array( $this, 'ajax_cancel_class_booking' ) );
		add_action( 'wp_ajax_bkx_join_waitlist', array( $this, 'ajax_join_waitlist' ) );
		add_action( 'wp_ajax_nopriv_bkx_join_waitlist', array( $this, 'ajax_join_waitlist' ) );
		add_action( 'wp_ajax_bkx_book_equipment', array( $this, 'ajax_book_equipment' ) );
		add_action( 'wp_ajax_bkx_log_workout', array( $this, 'ajax_log_workout' ) );

		// Integration hooks.
		add_filter( 'bkx_booking_types', array( $this, 'add_booking_types' ) );
		add_filter( 'bkx_seat_capabilities', array( $this, 'add_trainer_capabilities' ) );
		add_action( 'bkx_booking_completed', array( $this, 'handle_booking_completed' ), 10, 2 );

		// Cron for class reminders.
		add_action( 'bkx_fitness_class_reminders', array( $this, 'send_class_reminders' ) );

		// Membership hooks.
		add_action( 'bkx_fitness_check_memberships', array( $this->membership_service, 'check_expiring_memberships' ) );
	}

	/**
	 * Load textdomain.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'bkx-fitness-sports',
			false,
			dirname( BKX_FITNESS_SPORTS_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register custom post types.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_types(): void {
		// Fitness Class post type.
		register_post_type( 'bkx_fitness_class', array(
			'labels'             => array(
				'name'               => __( 'Fitness Classes', 'bkx-fitness-sports' ),
				'singular_name'      => __( 'Fitness Class', 'bkx-fitness-sports' ),
				'add_new'            => __( 'Add New Class', 'bkx-fitness-sports' ),
				'add_new_item'       => __( 'Add New Fitness Class', 'bkx-fitness-sports' ),
				'edit_item'          => __( 'Edit Fitness Class', 'bkx-fitness-sports' ),
				'new_item'           => __( 'New Fitness Class', 'bkx-fitness-sports' ),
				'view_item'          => __( 'View Fitness Class', 'bkx-fitness-sports' ),
				'search_items'       => __( 'Search Classes', 'bkx-fitness-sports' ),
				'not_found'          => __( 'No classes found', 'bkx-fitness-sports' ),
				'not_found_in_trash' => __( 'No classes found in trash', 'bkx-fitness-sports' ),
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'bookingx-options',
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'fitness-class' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'       => true,
		) );

		// Equipment post type.
		register_post_type( 'bkx_equipment', array(
			'labels'             => array(
				'name'               => __( 'Equipment', 'bkx-fitness-sports' ),
				'singular_name'      => __( 'Equipment', 'bkx-fitness-sports' ),
				'add_new'            => __( 'Add New Equipment', 'bkx-fitness-sports' ),
				'add_new_item'       => __( 'Add New Equipment', 'bkx-fitness-sports' ),
				'edit_item'          => __( 'Edit Equipment', 'bkx-fitness-sports' ),
				'new_item'           => __( 'New Equipment', 'bkx-fitness-sports' ),
				'view_item'          => __( 'View Equipment', 'bkx-fitness-sports' ),
				'search_items'       => __( 'Search Equipment', 'bkx-fitness-sports' ),
				'not_found'          => __( 'No equipment found', 'bkx-fitness-sports' ),
				'not_found_in_trash' => __( 'No equipment found in trash', 'bkx-fitness-sports' ),
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'bookingx-options',
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'equipment' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail' ),
			'show_in_rest'       => true,
		) );

		// Membership Plan post type.
		register_post_type( 'bkx_membership', array(
			'labels'             => array(
				'name'               => __( 'Membership Plans', 'bkx-fitness-sports' ),
				'singular_name'      => __( 'Membership Plan', 'bkx-fitness-sports' ),
				'add_new'            => __( 'Add New Plan', 'bkx-fitness-sports' ),
				'add_new_item'       => __( 'Add New Membership Plan', 'bkx-fitness-sports' ),
				'edit_item'          => __( 'Edit Membership Plan', 'bkx-fitness-sports' ),
				'new_item'           => __( 'New Membership Plan', 'bkx-fitness-sports' ),
				'view_item'          => __( 'View Membership Plan', 'bkx-fitness-sports' ),
				'search_items'       => __( 'Search Plans', 'bkx-fitness-sports' ),
				'not_found'          => __( 'No plans found', 'bkx-fitness-sports' ),
				'not_found_in_trash' => __( 'No plans found in trash', 'bkx-fitness-sports' ),
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'bookingx-options',
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'membership' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail' ),
			'show_in_rest'       => true,
		) );
	}

	/**
	 * Register taxonomies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_taxonomies(): void {
		// Class Category.
		register_taxonomy( 'bkx_class_category', array( 'bkx_fitness_class' ), array(
			'labels'            => array(
				'name'          => __( 'Class Categories', 'bkx-fitness-sports' ),
				'singular_name' => __( 'Class Category', 'bkx-fitness-sports' ),
				'search_items'  => __( 'Search Categories', 'bkx-fitness-sports' ),
				'all_items'     => __( 'All Categories', 'bkx-fitness-sports' ),
				'edit_item'     => __( 'Edit Category', 'bkx-fitness-sports' ),
				'update_item'   => __( 'Update Category', 'bkx-fitness-sports' ),
				'add_new_item'  => __( 'Add New Category', 'bkx-fitness-sports' ),
				'new_item_name' => __( 'New Category Name', 'bkx-fitness-sports' ),
				'menu_name'     => __( 'Categories', 'bkx-fitness-sports' ),
			),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'class-category' ),
			'show_in_rest'      => true,
		) );

		// Difficulty Level.
		register_taxonomy( 'bkx_difficulty_level', array( 'bkx_fitness_class' ), array(
			'labels'            => array(
				'name'          => __( 'Difficulty Levels', 'bkx-fitness-sports' ),
				'singular_name' => __( 'Difficulty Level', 'bkx-fitness-sports' ),
			),
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'difficulty' ),
			'show_in_rest'      => true,
		) );

		// Equipment Category.
		register_taxonomy( 'bkx_equipment_category', array( 'bkx_equipment' ), array(
			'labels'            => array(
				'name'          => __( 'Equipment Categories', 'bkx-fitness-sports' ),
				'singular_name' => __( 'Equipment Category', 'bkx-fitness-sports' ),
			),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'equipment-category' ),
			'show_in_rest'      => true,
		) );

		// Trainer Specialty.
		register_taxonomy( 'bkx_trainer_specialty', array( 'bkx_seat' ), array(
			'labels'            => array(
				'name'          => __( 'Trainer Specialties', 'bkx-fitness-sports' ),
				'singular_name' => __( 'Trainer Specialty', 'bkx-fitness-sports' ),
			),
			'hierarchical'      => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'specialty' ),
			'show_in_rest'      => true,
		) );
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {
		wp_enqueue_style(
			'bkx-fitness-sports',
			BKX_FITNESS_SPORTS_URL . 'assets/css/frontend.css',
			array(),
			BKX_FITNESS_SPORTS_VERSION
		);

		wp_enqueue_script(
			'bkx-fitness-sports',
			BKX_FITNESS_SPORTS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_FITNESS_SPORTS_VERSION,
			true
		);

		wp_localize_script( 'bkx-fitness-sports', 'bkxFitnessSports', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bkx_fitness_sports_nonce' ),
			'i18n'    => array(
				'confirmCancel'  => __( 'Are you sure you want to cancel this booking?', 'bkx-fitness-sports' ),
				'bookingSuccess' => __( 'Successfully booked!', 'bkx-fitness-sports' ),
				'waitlistAdded'  => __( 'Added to waitlist.', 'bkx-fitness-sports' ),
				'error'          => __( 'An error occurred. Please try again.', 'bkx-fitness-sports' ),
			),
		) );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function admin_enqueue_scripts( string $hook ): void {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, array( 'bkx_fitness_class', 'bkx_equipment', 'bkx_membership' ), true ) ) {
			if ( 'bookingx_page_bkx-fitness-sports' !== $hook ) {
				return;
			}
		}

		wp_enqueue_style(
			'bkx-fitness-sports-admin',
			BKX_FITNESS_SPORTS_URL . 'assets/css/admin.css',
			array(),
			BKX_FITNESS_SPORTS_VERSION
		);

		wp_enqueue_script(
			'bkx-fitness-sports-admin',
			BKX_FITNESS_SPORTS_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-datepicker', 'jquery-ui-sortable' ),
			BKX_FITNESS_SPORTS_VERSION,
			true
		);

		wp_localize_script( 'bkx-fitness-sports-admin', 'bkxFitnessSportsAdmin', array(
			'nonce' => wp_create_nonce( 'bkx_fitness_sports_admin_nonce' ),
			'i18n'  => array(
				'confirmDelete' => __( 'Are you sure you want to delete this?', 'bkx-fitness-sports' ),
			),
		) );
	}

	/**
	 * Initialize admin components.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_admin(): void {
		$settings_page = new SettingsPage();
		$settings_page->init();

		$class_metabox = new ClassMetabox();
		$class_metabox->init();

		$trainer_metabox = new TrainerMetabox();
		$trainer_metabox->init();
	}

	/**
	 * Render trainer list shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_trainer_list( array $atts = array() ): string {
		$atts = shortcode_atts( array(
			'specialty' => '',
			'columns'   => 3,
			'limit'     => -1,
		), $atts );

		$args = array(
			'post_type'      => 'bkx_seat',
			'post_status'    => 'publish',
			'posts_per_page' => $atts['limit'],
			'meta_query'     => array(
				array(
					'key'   => '_bkx_is_trainer',
					'value' => '1',
				),
			),
		);

		if ( ! empty( $atts['specialty'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'bkx_trainer_specialty',
					'field'    => 'slug',
					'terms'    => $atts['specialty'],
				),
			);
		}

		$trainers = new \WP_Query( $args );

		ob_start();
		?>
		<div class="bkx-trainer-list bkx-trainer-columns-<?php echo esc_attr( $atts['columns'] ); ?>">
			<?php if ( $trainers->have_posts() ) : ?>
				<?php while ( $trainers->have_posts() ) : $trainers->the_post(); ?>
					<div class="bkx-trainer-card">
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="bkx-trainer-image">
								<?php the_post_thumbnail( 'medium' ); ?>
							</div>
						<?php endif; ?>

						<div class="bkx-trainer-content">
							<h3 class="bkx-trainer-name"><?php the_title(); ?></h3>

							<?php
							$specialties = wp_get_post_terms( get_the_ID(), 'bkx_trainer_specialty', array( 'fields' => 'names' ) );
							if ( ! empty( $specialties ) ) :
								?>
								<div class="bkx-trainer-specialties">
									<?php echo esc_html( implode( ', ', $specialties ) ); ?>
								</div>
							<?php endif; ?>

							<?php if ( has_excerpt() ) : ?>
								<div class="bkx-trainer-bio">
									<?php the_excerpt(); ?>
								</div>
							<?php endif; ?>

							<?php
							$certifications = get_post_meta( get_the_ID(), '_bkx_trainer_certifications', true );
							if ( ! empty( $certifications ) ) :
								?>
								<div class="bkx-trainer-certifications">
									<strong><?php esc_html_e( 'Certifications:', 'bkx-fitness-sports' ); ?></strong>
									<?php echo esc_html( implode( ', ', $certifications ) ); ?>
								</div>
							<?php endif; ?>

							<a href="<?php the_permalink(); ?>" class="bkx-book-trainer-btn">
								<?php esc_html_e( 'Book Session', 'bkx-fitness-sports' ); ?>
							</a>
						</div>
					</div>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<p><?php esc_html_e( 'No trainers found.', 'bkx-fitness-sports' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX handler for booking a class.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_book_class(): void {
		check_ajax_referer( 'bkx_fitness_sports_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to book a class.', 'bkx-fitness-sports' ) ) );
		}

		$class_id    = absint( $_POST['class_id'] ?? 0 );
		$schedule_id = absint( $_POST['schedule_id'] ?? 0 );
		$user_id     = get_current_user_id();

		if ( ! $class_id || ! $schedule_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid class or schedule.', 'bkx-fitness-sports' ) ) );
		}

		$result = $this->class_service->book_class( $user_id, $class_id, $schedule_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'    => __( 'Successfully booked!', 'bkx-fitness-sports' ),
			'booking_id' => $result,
		) );
	}

	/**
	 * AJAX handler for canceling a class booking.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_cancel_class_booking(): void {
		check_ajax_referer( 'bkx_fitness_sports_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-fitness-sports' ) ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		$user_id    = get_current_user_id();

		$result = $this->class_service->cancel_booking( $user_id, $booking_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Booking cancelled.', 'bkx-fitness-sports' ) ) );
	}

	/**
	 * AJAX handler for joining waitlist.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_join_waitlist(): void {
		check_ajax_referer( 'bkx_fitness_sports_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to join the waitlist.', 'bkx-fitness-sports' ) ) );
		}

		$class_id    = absint( $_POST['class_id'] ?? 0 );
		$schedule_id = absint( $_POST['schedule_id'] ?? 0 );
		$user_id     = get_current_user_id();

		$result = $this->class_service->add_to_waitlist( $user_id, $class_id, $schedule_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'Added to waitlist.', 'bkx-fitness-sports' ),
			'position' => $result,
		) );
	}

	/**
	 * AJAX handler for booking equipment.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_book_equipment(): void {
		check_ajax_referer( 'bkx_fitness_sports_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to book equipment.', 'bkx-fitness-sports' ) ) );
		}

		$equipment_id = absint( $_POST['equipment_id'] ?? 0 );
		$start_time   = sanitize_text_field( $_POST['start_time'] ?? '' );
		$end_time     = sanitize_text_field( $_POST['end_time'] ?? '' );
		$user_id      = get_current_user_id();

		$result = $this->equipment_service->book_equipment( $user_id, $equipment_id, $start_time, $end_time );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'    => __( 'Equipment booked successfully!', 'bkx-fitness-sports' ),
			'booking_id' => $result,
		) );
	}

	/**
	 * AJAX handler for logging workout.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_log_workout(): void {
		check_ajax_referer( 'bkx_fitness_sports_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-fitness-sports' ) ) );
		}

		$workout_data = array(
			'type'       => sanitize_text_field( $_POST['workout_type'] ?? '' ),
			'duration'   => absint( $_POST['duration'] ?? 0 ),
			'calories'   => absint( $_POST['calories'] ?? 0 ),
			'notes'      => sanitize_textarea_field( $_POST['notes'] ?? '' ),
			'exercises'  => isset( $_POST['exercises'] ) ? array_map( 'sanitize_text_field', (array) $_POST['exercises'] ) : array(),
		);

		$result = $this->performance_service->log_workout( get_current_user_id(), $workout_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Workout logged!', 'bkx-fitness-sports' ) ) );
	}

	/**
	 * Add fitness booking types.
	 *
	 * @since 1.0.0
	 * @param array $types Existing booking types.
	 * @return array
	 */
	public function add_booking_types( array $types ): array {
		$types['fitness_class']     = __( 'Fitness Class', 'bkx-fitness-sports' );
		$types['personal_training'] = __( 'Personal Training', 'bkx-fitness-sports' );
		$types['equipment_rental']  = __( 'Equipment Rental', 'bkx-fitness-sports' );

		return $types;
	}

	/**
	 * Add trainer capabilities.
	 *
	 * @since 1.0.0
	 * @param array $capabilities Existing capabilities.
	 * @return array
	 */
	public function add_trainer_capabilities( array $capabilities ): array {
		$capabilities['personal_training'] = __( 'Personal Training', 'bkx-fitness-sports' );
		$capabilities['group_classes']     = __( 'Group Classes', 'bkx-fitness-sports' );
		$capabilities['online_coaching']   = __( 'Online Coaching', 'bkx-fitness-sports' );

		return $capabilities;
	}

	/**
	 * Handle booking completed.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function handle_booking_completed( int $booking_id, array $booking_data ): void {
		$booking_type = get_post_meta( $booking_id, '_bkx_booking_type', true );

		if ( 'fitness_class' === $booking_type ) {
			$this->class_service->confirm_attendance( $booking_id );
		}
	}

	/**
	 * Send class reminders.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function send_class_reminders(): void {
		$this->class_service->send_upcoming_reminders();
	}

	/**
	 * Get settings.
	 *
	 * @since 1.0.0
	 * @param string|null $key Optional setting key.
	 * @return mixed
	 */
	public function get_settings( ?string $key = null ) {
		if ( null === $key ) {
			return $this->settings;
		}

		return $this->settings[ $key ] ?? null;
	}
}
