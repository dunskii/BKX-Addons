<?php
/**
 * Main Ratings & Reviews Addon Class
 *
 * @package BookingX\RatingsReviews
 * @since   1.0.0
 */

namespace BookingX\RatingsReviews;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\RatingsReviews\Services\ReviewService;
use BookingX\RatingsReviews\Admin\SettingsPage;
use BookingX\RatingsReviews\Migrations\CreateReviewTables;

/**
 * Main addon class for Ratings & Reviews.
 *
 * @since 1.0.0
 */
class RatingsReviewsAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Review service instance.
	 *
	 * @var ReviewService
	 */
	protected ?ReviewService $review_service = null;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Path to the main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		// Set addon properties
		$this->addon_id        = 'bkx_ratings_reviews';
		$this->addon_name      = __( 'BookingX - Ratings & Reviews', 'bkx-ratings-reviews' );
		$this->version         = BKX_RATINGS_VERSION;
		$this->text_domain     = 'bkx-ratings-reviews';
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
		$tabs['ratings_reviews'] = __( 'Ratings & Reviews', 'bkx-ratings-reviews' );
		return $tabs;
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_hooks(): void {
		// Send review requests after completed bookings
		add_action( 'bkx_booking_status_changed', array( $this, 'maybe_send_review_request' ), 10, 3 );

		// Display reviews on service pages
		add_action( 'bkx_after_service_content', array( $this, 'display_service_reviews' ), 10, 1 );

		// Display average rating
		add_filter( 'bkx_service_meta', array( $this, 'add_rating_to_service_meta' ), 10, 2 );

		// Handle review submission
		add_action( 'bkx_submit_review', array( $this, 'handle_review_submission' ), 10, 1 );

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

		// Add reviews management page
		add_action( 'admin_menu', array( $this, 'register_reviews_menu' ) );

		// Add action links
		add_filter( 'plugin_action_links_' . BKX_RATINGS_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Initialize frontend functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_frontend(): void {
		// Register AJAX actions
		$this->register_ajax_action( 'submit_review', array( $this, 'ajax_submit_review' ), true );
		$this->register_ajax_action( 'load_reviews', array( $this, 'ajax_load_reviews' ), true );
		$this->register_ajax_action( 'vote_review', array( $this, 'ajax_vote_review' ), true );
	}

	/**
	 * Get database migrations.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_migrations(): array {
		return array(
			'1.0.0' => array(
				CreateReviewTables::class,
			),
		);
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enable_reviews'              => true,
			'require_verified_booking'    => true,
			'enable_review_moderation'    => true,
			'auto_approve_reviews'        => false,
			'min_rating'                  => 1,
			'max_rating'                  => 5,
			'enable_review_requests'      => true,
			'review_request_delay'        => 24,
			'allow_review_editing'        => true,
			'review_edit_window'          => 48,
			'display_reviewer_name'       => true,
			'display_review_date'         => true,
			'enable_helpful_voting'       => true,
			'reviews_per_page'            => 10,
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
			'enable_reviews'              => array( 'type' => 'checkbox' ),
			'require_verified_booking'    => array( 'type' => 'checkbox' ),
			'enable_review_moderation'    => array( 'type' => 'checkbox' ),
			'auto_approve_reviews'        => array( 'type' => 'checkbox' ),
			'min_rating'                  => array( 'type' => 'integer' ),
			'max_rating'                  => array( 'type' => 'integer' ),
			'enable_review_requests'      => array( 'type' => 'checkbox' ),
			'review_request_delay'        => array( 'type' => 'integer' ),
			'allow_review_editing'        => array( 'type' => 'checkbox' ),
			'review_edit_window'          => array( 'type' => 'integer' ),
			'display_reviewer_name'       => array( 'type' => 'checkbox' ),
			'display_review_date'         => array( 'type' => 'checkbox' ),
			'enable_helpful_voting'       => array( 'type' => 'checkbox' ),
			'reviews_per_page'            => array( 'type' => 'integer' ),
		);
	}

	/**
	 * Maybe send review request after booking completion.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @return void
	 */
	public function maybe_send_review_request( $booking_id, $old_status, $new_status ): void {
		if ( 'bkx-completed' !== $new_status ) {
			return;
		}

		if ( ! $this->get_setting( 'enable_review_requests', true ) ) {
			return;
		}

		$delay_hours = $this->get_setting( 'review_request_delay', 24 );

		// Schedule review request email
		wp_schedule_single_event(
			time() + ( $delay_hours * HOUR_IN_SECONDS ),
			'bkx_send_review_request',
			array( $booking_id )
		);
	}

	/**
	 * Display reviews on service pages.
	 *
	 * @since 1.0.0
	 * @param int $service_id Service ID.
	 * @return void
	 */
	public function display_service_reviews( $service_id ): void {
		$template_path = BKX_RATINGS_PATH . 'templates/frontend/reviews-list.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Add rating to service meta.
	 *
	 * @since 1.0.0
	 * @param array $meta       Service meta.
	 * @param int   $service_id Service ID.
	 * @return array
	 */
	public function add_rating_to_service_meta( $meta, $service_id ): array {
		$rating = $this->get_review_service()->get_average_rating( $service_id );

		if ( $rating > 0 ) {
			$meta['rating'] = $rating;
			$meta['review_count'] = $this->get_review_service()->get_review_count( $service_id );
		}

		return $meta;
	}

	/**
	 * Handle review submission.
	 *
	 * @since 1.0.0
	 * @param array $review_data Review data.
	 * @return void
	 */
	public function handle_review_submission( $review_data ): void {
		$this->get_review_service()->submit_review( $review_data );
	}

	/**
	 * AJAX: Submit review.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_submit_review(): void {
		$booking_id = $this->get_post_param( 'booking_id', 0, 'int' );
		$rating = $this->get_post_param( 'rating', 0, 'int' );
		$review_text = $this->get_post_param( 'review_text', '', 'html' );

		if ( ! $booking_id || ! $rating ) {
			$this->ajax_error( 'missing_data', __( 'Missing required data.', 'bkx-ratings-reviews' ) );
		}

		$result = $this->get_review_service()->submit_review( array(
			'booking_id'  => $booking_id,
			'rating'      => $rating,
			'review_text' => $review_text,
		) );

		if ( $result['success'] ) {
			$this->ajax_success( $result );
		} else {
			$this->ajax_error( 'submission_failed', $result['message'] );
		}
	}

	/**
	 * AJAX: Load reviews.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_load_reviews(): void {
		$service_id = $this->get_post_param( 'service_id', 0, 'int' );
		$page = $this->get_post_param( 'page', 1, 'int' );
		$per_page = $this->get_setting( 'reviews_per_page', 10 );

		$reviews = $this->get_review_service()->get_service_reviews( $service_id, $page, $per_page );

		$this->ajax_success( array( 'reviews' => $reviews ) );
	}

	/**
	 * AJAX: Vote on review helpfulness.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_vote_review(): void {
		if ( ! $this->get_setting( 'enable_helpful_voting', true ) ) {
			$this->ajax_error( 'voting_disabled', __( 'Voting is disabled.', 'bkx-ratings-reviews' ) );
		}

		$review_id = $this->get_post_param( 'review_id', 0, 'int' );
		$vote_type = $this->get_post_param( 'vote_type', '', 'string' );

		if ( ! in_array( $vote_type, array( 'helpful', 'not_helpful' ), true ) ) {
			$this->ajax_error( 'invalid_vote', __( 'Invalid vote type.', 'bkx-ratings-reviews' ) );
		}

		$result = $this->get_review_service()->record_vote( $review_id, $vote_type );

		$this->ajax_success( $result );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ): void {
		if ( 'bkx_booking_page_bkx_settings' !== $hook && 'toplevel_page_bkx_reviews' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-ratings-admin',
			BKX_RATINGS_URL . 'assets/css/admin.css',
			array(),
			BKX_RATINGS_VERSION
		);

		wp_enqueue_script(
			'bkx-ratings-admin',
			BKX_RATINGS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_RATINGS_VERSION,
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
		if ( ! is_page() && ! is_singular( array( 'bkx_base', 'bkx_seat' ) ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-ratings-frontend',
			BKX_RATINGS_URL . 'assets/css/frontend.css',
			array(),
			BKX_RATINGS_VERSION
		);

		wp_enqueue_script(
			'bkx-ratings-frontend',
			BKX_RATINGS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_RATINGS_VERSION,
			true
		);

		$this->localize_ajax_data(
			'bkx-ratings-frontend',
			'bkxRatings',
			array( 'submit_review', 'load_reviews', 'vote_review' ),
			array(
				'settings' => array(
					'maxRating'     => $this->get_setting( 'max_rating', 5 ),
					'allowEditing'  => $this->get_setting( 'allow_review_editing', true ),
					'enableVoting'  => $this->get_setting( 'enable_helpful_voting', true ),
				),
				'i18n' => array(
					'submitting'     => __( 'Submitting review...', 'bkx-ratings-reviews' ),
					'submitSuccess'  => __( 'Review submitted successfully!', 'bkx-ratings-reviews' ),
					'selectRating'   => __( 'Please select a rating', 'bkx-ratings-reviews' ),
				),
			)
		);
	}

	/**
	 * Register reviews management menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_reviews_menu(): void {
		add_menu_page(
			__( 'Reviews', 'bkx-ratings-reviews' ),
			__( 'Reviews', 'bkx-ratings-reviews' ),
			'manage_options',
			'bkx_reviews',
			array( $this, 'render_reviews_page' ),
			'dashicons-star-filled',
			30
		);
	}

	/**
	 * Render reviews management page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_reviews_page(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Manage Reviews', 'bkx-ratings-reviews' ) . '</h1>';
		echo '<p>' . esc_html__( 'Review management interface coming soon.', 'bkx-ratings-reviews' ) . '</p>';
		echo '</div>';
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
			esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx_settings&tab=ratings_reviews' ) ),
			esc_html__( 'Settings', 'bkx-ratings-reviews' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Get review service instance.
	 *
	 * @since 1.0.0
	 * @return ReviewService
	 */
	public function get_review_service(): ReviewService {
		if ( ! $this->review_service ) {
			$this->review_service = new ReviewService( $this );
		}

		return $this->review_service;
	}
}
