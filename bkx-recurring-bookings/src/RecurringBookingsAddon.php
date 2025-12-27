<?php
/**
 * Recurring Bookings Addon
 *
 * @package BookingX\RecurringBookings
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasCron;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\RecurringBookings\Services\RecurrenceService;
use BookingX\RecurringBookings\Services\InstanceGenerator;
use BookingX\RecurringBookings\Admin\SettingsPage;

/**
 * Main addon class.
 *
 * @since 1.0.0
 */
class RecurringBookingsAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasCron;
	use HasAjax;

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $slug = 'bkx-recurring-bookings';

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
	protected string $name = 'Recurring Bookings';

	/**
	 * Recurrence service.
	 *
	 * @var RecurrenceService
	 */
	protected RecurrenceService $recurrence_service;

	/**
	 * Instance generator.
	 *
	 * @var InstanceGenerator
	 */
	protected InstanceGenerator $instance_generator;

	/**
	 * Initialize the addon.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_settings( 'bkx_recurring_settings' );
		$this->init_license( 'bkx_recurring_license', 'https://bookingx.com', 89 );
		$this->init_database( BKX_RECURRING_PATH . 'src/Migrations/' );

		// Initialize services.
		$this->recurrence_service = new RecurrenceService( $this );
		$this->instance_generator = new InstanceGenerator( $this, $this->recurrence_service );

		// Register hooks.
		$this->register_hooks();

		// Initialize admin.
		if ( is_admin() ) {
			new SettingsPage( $this );
		}

		// Register AJAX handlers.
		$this->register_ajax_handlers();

		// Register cron handlers.
		$this->register_cron_handlers();
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

		// Booking form integration.
		add_filter( 'bkx_booking_form_fields', array( $this, 'add_recurring_fields' ) );
		add_action( 'bkx_booking_created', array( $this, 'handle_recurring_booking' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'handle_booking_cancellation' ), 10, 2 );

		// Admin columns.
		add_filter( 'manage_bkx_booking_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_bkx_booking_posts_custom_column', array( $this, 'render_admin_columns' ), 10, 2 );

		// Booking meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	protected function register_ajax_handlers(): void {
		$this->register_ajax( 'bkx_get_recurrence_preview', array( $this, 'ajax_get_recurrence_preview' ) );
		$this->register_ajax( 'bkx_cancel_recurring_series', array( $this, 'ajax_cancel_recurring_series' ), true );
		$this->register_ajax( 'bkx_skip_instance', array( $this, 'ajax_skip_instance' ), true );
		$this->register_ajax( 'bkx_reschedule_instance', array( $this, 'ajax_reschedule_instance' ), true );
		$this->register_ajax( 'bkx_get_series_instances', array( $this, 'ajax_get_series_instances' ), true );
	}

	/**
	 * Register cron handlers.
	 *
	 * @return void
	 */
	protected function register_cron_handlers(): void {
		add_action( 'bkx_recurring_generate_instances', array( $this->instance_generator, 'generate_upcoming_instances' ) );
		add_action( 'bkx_recurring_cleanup_expired', array( $this, 'cleanup_expired_instances' ) );

		// Schedule cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'bkx_recurring_cleanup_expired' ) ) {
			wp_schedule_event( time(), 'weekly', 'bkx_recurring_cleanup_expired' );
		}
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
			'bkx-recurring-frontend',
			BKX_RECURRING_URL . 'assets/css/frontend.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'bkx-recurring-frontend',
			BKX_RECURRING_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'bkx-recurring-frontend',
			'bkxRecurring',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_recurring_nonce' ),
				'i18n'     => array(
					'daily'        => __( 'Daily', 'bkx-recurring-bookings' ),
					'weekly'       => __( 'Weekly', 'bkx-recurring-bookings' ),
					'biweekly'     => __( 'Every 2 weeks', 'bkx-recurring-bookings' ),
					'monthly'      => __( 'Monthly', 'bkx-recurring-bookings' ),
					'custom'       => __( 'Custom', 'bkx-recurring-bookings' ),
					'occurrences'  => __( 'occurrences', 'bkx-recurring-bookings' ),
					'loading'      => __( 'Loading...', 'bkx-recurring-bookings' ),
					'error'        => __( 'Error loading preview', 'bkx-recurring-bookings' ),
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

		if ( ! $screen || ( 'bkx_booking' !== $screen->post_type && 'bookingx_page_bkx-recurring-settings' !== $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-recurring-admin',
			BKX_RECURRING_URL . 'assets/css/admin.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'bkx-recurring-admin',
			BKX_RECURRING_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			$this->version,
			true
		);

		wp_localize_script(
			'bkx-recurring-admin',
			'bkxRecurringAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_recurring_admin_nonce' ),
				'i18n'     => array(
					'confirm_cancel'    => __( 'Are you sure you want to cancel all future instances in this series?', 'bkx-recurring-bookings' ),
					'confirm_skip'      => __( 'Are you sure you want to skip this instance?', 'bkx-recurring-bookings' ),
					'cancel_success'    => __( 'Series cancelled successfully.', 'bkx-recurring-bookings' ),
					'skip_success'      => __( 'Instance skipped successfully.', 'bkx-recurring-bookings' ),
					'error'             => __( 'An error occurred. Please try again.', 'bkx-recurring-bookings' ),
				),
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

		// Load if booking shortcode present.
		global $post;
		if ( $post && has_shortcode( $post->post_content, 'bookingx' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add recurring fields to booking form.
	 *
	 * @param array $fields Form fields.
	 * @return array Modified fields.
	 */
	public function add_recurring_fields( array $fields ): array {
		if ( ! $this->get_setting( 'enable_recurring', true ) ) {
			return $fields;
		}

		$fields['recurring'] = array(
			'type'     => 'recurring',
			'label'    => __( 'Repeat Booking', 'bkx-recurring-bookings' ),
			'priority' => 50,
			'options'  => $this->get_enabled_patterns(),
		);

		return $fields;
	}

	/**
	 * Get enabled recurrence patterns.
	 *
	 * @return array Patterns.
	 */
	public function get_enabled_patterns(): array {
		$patterns = array(
			'none'     => __( 'One-time booking', 'bkx-recurring-bookings' ),
			'daily'    => __( 'Daily', 'bkx-recurring-bookings' ),
			'weekly'   => __( 'Weekly', 'bkx-recurring-bookings' ),
			'biweekly' => __( 'Every 2 weeks', 'bkx-recurring-bookings' ),
			'monthly'  => __( 'Monthly', 'bkx-recurring-bookings' ),
		);

		if ( $this->get_setting( 'enable_custom_patterns', false ) ) {
			$patterns['custom'] = __( 'Custom...', 'bkx-recurring-bookings' );
		}

		$disabled = $this->get_setting( 'disabled_patterns', array() );
		foreach ( $disabled as $pattern ) {
			unset( $patterns[ $pattern ] );
		}

		return $patterns;
	}

	/**
	 * Handle recurring booking creation.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function handle_recurring_booking( int $booking_id, array $booking_data ): void {
		if ( empty( $booking_data['recurring_pattern'] ) || 'none' === $booking_data['recurring_pattern'] ) {
			return;
		}

		$series_id = $this->recurrence_service->create_series(
			$booking_id,
			$booking_data['recurring_pattern'],
			$booking_data['recurring_options'] ?? array()
		);

		if ( $series_id ) {
			// Generate initial instances.
			$this->instance_generator->generate_instances_for_series( $series_id );

			// Mark original booking as master.
			update_post_meta( $booking_id, '_bkx_recurring_series_id', $series_id );
			update_post_meta( $booking_id, '_bkx_is_recurring_master', true );
		}
	}

	/**
	 * Handle booking cancellation.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $reason Cancellation reason.
	 * @return void
	 */
	public function handle_booking_cancellation( int $booking_id, string $reason ): void {
		$series_id = get_post_meta( $booking_id, '_bkx_recurring_series_id', true );

		if ( ! $series_id ) {
			return;
		}

		$cancel_future = apply_filters( 'bkx_recurring_cancel_future_on_master_cancel', false, $booking_id, $series_id );

		if ( $cancel_future && get_post_meta( $booking_id, '_bkx_is_recurring_master', true ) ) {
			$this->recurrence_service->cancel_series( $series_id, $reason );
		}
	}

	/**
	 * Add admin columns.
	 *
	 * @param array $columns Columns.
	 * @return array Modified columns.
	 */
	public function add_admin_columns( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'title' === $key ) {
				$new_columns['recurring'] = __( 'Recurring', 'bkx-recurring-bookings' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render admin columns.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_columns( string $column, int $post_id ): void {
		if ( 'recurring' !== $column ) {
			return;
		}

		$series_id = get_post_meta( $post_id, '_bkx_recurring_series_id', true );

		if ( $series_id ) {
			$is_master = get_post_meta( $post_id, '_bkx_is_recurring_master', true );
			$series    = $this->recurrence_service->get_series( $series_id );

			if ( $series ) {
				$pattern_label = $this->get_pattern_label( $series['pattern'] );
				echo '<span class="bkx-recurring-badge">';
				echo esc_html( $pattern_label );
				if ( $is_master ) {
					echo ' <small>(' . esc_html__( 'Master', 'bkx-recurring-bookings' ) . ')</small>';
				}
				echo '</span>';
			}
		} else {
			echo '<span class="bkx-single-badge">' . esc_html__( 'Single', 'bkx-recurring-bookings' ) . '</span>';
		}
	}

	/**
	 * Get pattern label.
	 *
	 * @param string $pattern Pattern key.
	 * @return string Label.
	 */
	public function get_pattern_label( string $pattern ): string {
		$labels = array(
			'daily'    => __( 'Daily', 'bkx-recurring-bookings' ),
			'weekly'   => __( 'Weekly', 'bkx-recurring-bookings' ),
			'biweekly' => __( 'Biweekly', 'bkx-recurring-bookings' ),
			'monthly'  => __( 'Monthly', 'bkx-recurring-bookings' ),
			'custom'   => __( 'Custom', 'bkx-recurring-bookings' ),
		);

		return $labels[ $pattern ] ?? $pattern;
	}

	/**
	 * Add meta boxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'bkx-recurring-info',
			__( 'Recurring Booking', 'bkx-recurring-bookings' ),
			array( $this, 'render_meta_box' ),
			'bkx_booking',
			'side',
			'default'
		);
	}

	/**
	 * Render meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$series_id = get_post_meta( $post->ID, '_bkx_recurring_series_id', true );

		if ( ! $series_id ) {
			echo '<p>' . esc_html__( 'This is a one-time booking.', 'bkx-recurring-bookings' ) . '</p>';
			return;
		}

		$series    = $this->recurrence_service->get_series( $series_id );
		$is_master = get_post_meta( $post->ID, '_bkx_is_recurring_master', true );

		if ( ! $series ) {
			echo '<p>' . esc_html__( 'Series not found.', 'bkx-recurring-bookings' ) . '</p>';
			return;
		}

		include BKX_RECURRING_PATH . 'templates/admin/meta-box.php';
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'bookingx/v1',
			'/recurring/series',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_series' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);

		register_rest_route(
			'bookingx/v1',
			'/recurring/series/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_single_series' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);

		register_rest_route(
			'bookingx/v1',
			'/recurring/series/(?P<id>\d+)/instances',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_series_instances' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);
	}

	/**
	 * REST permission check.
	 *
	 * @return bool
	 */
	public function rest_permission_check(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * REST get series.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_series( \WP_REST_Request $request ): \WP_REST_Response {
		$series = $this->recurrence_service->get_all_series( $request->get_params() );
		return new \WP_REST_Response( $series, 200 );
	}

	/**
	 * REST get single series.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_single_series( \WP_REST_Request $request ): \WP_REST_Response {
		$series = $this->recurrence_service->get_series( $request->get_param( 'id' ) );

		if ( ! $series ) {
			return new \WP_REST_Response( array( 'error' => 'Series not found' ), 404 );
		}

		return new \WP_REST_Response( $series, 200 );
	}

	/**
	 * REST get series instances.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_series_instances( \WP_REST_Request $request ): \WP_REST_Response {
		$instances = $this->instance_generator->get_instances(
			$request->get_param( 'id' ),
			$request->get_params()
		);

		return new \WP_REST_Response( $instances, 200 );
	}

	/**
	 * AJAX get recurrence preview.
	 *
	 * @return void
	 */
	public function ajax_get_recurrence_preview(): void {
		check_ajax_referer( 'bkx_recurring_nonce', 'nonce' );

		$pattern    = sanitize_text_field( $_POST['pattern'] ?? '' );
		$start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
		$options    = isset( $_POST['options'] ) ? array_map( 'sanitize_text_field', (array) $_POST['options'] ) : array();

		$preview = $this->recurrence_service->get_preview( $pattern, $start_date, $options );

		wp_send_json_success( $preview );
	}

	/**
	 * AJAX cancel recurring series.
	 *
	 * @return void
	 */
	public function ajax_cancel_recurring_series(): void {
		check_ajax_referer( 'bkx_recurring_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-recurring-bookings' ) ) );
		}

		$series_id = absint( $_POST['series_id'] ?? 0 );
		$reason    = sanitize_text_field( $_POST['reason'] ?? '' );

		if ( ! $series_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid series ID.', 'bkx-recurring-bookings' ) ) );
		}

		$result = $this->recurrence_service->cancel_series( $series_id, $reason );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Series cancelled.', 'bkx-recurring-bookings' ) ) );
	}

	/**
	 * AJAX skip instance.
	 *
	 * @return void
	 */
	public function ajax_skip_instance(): void {
		check_ajax_referer( 'bkx_recurring_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-recurring-bookings' ) ) );
		}

		$instance_id = absint( $_POST['instance_id'] ?? 0 );
		$reason      = sanitize_text_field( $_POST['reason'] ?? '' );

		if ( ! $instance_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid instance ID.', 'bkx-recurring-bookings' ) ) );
		}

		$result = $this->instance_generator->skip_instance( $instance_id, $reason );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Instance skipped.', 'bkx-recurring-bookings' ) ) );
	}

	/**
	 * AJAX reschedule instance.
	 *
	 * @return void
	 */
	public function ajax_reschedule_instance(): void {
		check_ajax_referer( 'bkx_recurring_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-recurring-bookings' ) ) );
		}

		$instance_id = absint( $_POST['instance_id'] ?? 0 );
		$new_date    = sanitize_text_field( $_POST['new_date'] ?? '' );
		$new_time    = sanitize_text_field( $_POST['new_time'] ?? '' );

		if ( ! $instance_id || ! $new_date ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bkx-recurring-bookings' ) ) );
		}

		$result = $this->instance_generator->reschedule_instance( $instance_id, $new_date, $new_time );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Instance rescheduled.', 'bkx-recurring-bookings' ) ) );
	}

	/**
	 * AJAX get series instances.
	 *
	 * @return void
	 */
	public function ajax_get_series_instances(): void {
		check_ajax_referer( 'bkx_recurring_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-recurring-bookings' ) ) );
		}

		$series_id = absint( $_POST['series_id'] ?? 0 );

		if ( ! $series_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid series ID.', 'bkx-recurring-bookings' ) ) );
		}

		$instances = $this->instance_generator->get_instances( $series_id );

		wp_send_json_success( $instances );
	}

	/**
	 * Cleanup expired instances.
	 *
	 * @return void
	 */
	public function cleanup_expired_instances(): void {
		$retention_days = $this->get_setting( 'data_retention_days', 365 );
		$this->instance_generator->cleanup_expired( $retention_days );
	}

	/**
	 * Get recurrence service.
	 *
	 * @return RecurrenceService
	 */
	public function get_recurrence_service(): RecurrenceService {
		return $this->recurrence_service;
	}

	/**
	 * Get instance generator.
	 *
	 * @return InstanceGenerator
	 */
	public function get_instance_generator(): InstanceGenerator {
		return $this->instance_generator;
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	protected function get_default_settings(): array {
		return array(
			'enable_recurring'         => true,
			'enable_custom_patterns'   => false,
			'disabled_patterns'        => array(),
			'max_occurrences'          => 52,
			'max_advance_days'         => 365,
			'allow_series_edit'        => true,
			'allow_instance_skip'      => true,
			'allow_instance_reschedule' => true,
			'generate_ahead_days'      => 30,
			'data_retention_days'      => 365,
			'recurring_discount'       => 0,
			'require_full_payment'     => false,
		);
	}
}
