<?php
/**
 * User Profiles Advanced Addon
 *
 * @package BookingX\UserProfilesAdvanced
 * @since   1.0.0
 */

namespace BookingX\UserProfilesAdvanced;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\UserProfilesAdvanced\Services\ProfileService;
use BookingX\UserProfilesAdvanced\Services\LoyaltyService;
use BookingX\UserProfilesAdvanced\Services\FavoritesService;
use BookingX\UserProfilesAdvanced\Admin\SettingsPage;

/**
 * Main addon class.
 *
 * @since 1.0.0
 */
class UserProfilesAdvancedAddon extends AbstractAddon {

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $slug = 'bkx-user-profiles-advanced';

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected string $version = BKX_USER_PROFILES_VERSION;

	/**
	 * Profile service.
	 *
	 * @var ProfileService
	 */
	protected ProfileService $profile_service;

	/**
	 * Loyalty service.
	 *
	 * @var LoyaltyService
	 */
	protected LoyaltyService $loyalty_service;

	/**
	 * Favorites service.
	 *
	 * @var FavoritesService
	 */
	protected FavoritesService $favorites_service;

	/**
	 * Initialize the addon.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_services();
		$this->register_hooks();
		$this->register_admin();
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings.
	 */
	public function get_default_settings(): array {
		return array(
			// Profile settings.
			'enable_profiles'           => true,
			'allow_profile_edit'        => true,
			'require_login_for_booking' => false,
			'auto_create_account'       => true,
			'profile_page_id'           => 0,

			// Booking history.
			'show_booking_history'      => true,
			'bookings_per_page'         => 10,
			'allow_rebooking'           => true,
			'allow_cancellation'        => true,
			'cancellation_period'       => 24,

			// Favorites.
			'enable_favorites'          => true,
			'max_favorites'             => 50,

			// Loyalty points.
			'enable_loyalty'            => true,
			'points_per_booking'        => 10,
			'points_per_currency'       => 1,
			'points_for_referral'       => 50,
			'points_redemption_rate'    => 100,
			'points_redemption_value'   => 1,
			'min_points_redeem'         => 100,

			// Notifications.
			'email_booking_reminder'    => true,
			'email_points_earned'       => true,
			'email_points_redeemed'     => true,

			// Preferences.
			'enable_preferences'        => true,
			'preference_fields'         => array( 'preferred_time', 'communication_preference' ),
		);
	}

	/**
	 * Load services.
	 *
	 * @return void
	 */
	protected function load_services(): void {
		$this->profile_service   = new ProfileService( $this );
		$this->loyalty_service   = new LoyaltyService( $this );
		$this->favorites_service = new FavoritesService( $this );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		// Profile hooks.
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Booking hooks.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_completed', array( $this, 'on_booking_completed' ), 10, 1 );
		add_action( 'bkx_booking_cancelled', array( $this, 'on_booking_cancelled' ), 10, 1 );

		// User hooks.
		add_action( 'user_register', array( $this, 'on_user_register' ), 10, 1 );
		add_action( 'delete_user', array( $this, 'on_user_delete' ), 10, 1 );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_profile_update', array( $this, 'ajax_update_profile' ) );
		add_action( 'wp_ajax_bkx_toggle_favorite', array( $this, 'ajax_toggle_favorite' ) );
		add_action( 'wp_ajax_bkx_redeem_points', array( $this, 'ajax_redeem_points' ) );
		add_action( 'wp_ajax_bkx_cancel_booking', array( $this, 'ajax_cancel_booking' ) );
		add_action( 'wp_ajax_bkx_rebook', array( $this, 'ajax_rebook' ) );

		// Filters.
		add_filter( 'bkx_booking_form_fields', array( $this, 'add_profile_fields' ), 10, 1 );
		add_filter( 'bkx_booking_total', array( $this, 'apply_loyalty_discount' ), 10, 2 );
	}

	/**
	 * Register admin page.
	 *
	 * @return void
	 */
	protected function register_admin(): void {
		if ( is_admin() ) {
			$settings_page = new SettingsPage( $this );
			$settings_page->register();

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'bkx_customer_profile', array( $this, 'render_profile_shortcode' ) );
		add_shortcode( 'bkx_booking_history', array( $this, 'render_history_shortcode' ) );
		add_shortcode( 'bkx_favorites', array( $this, 'render_favorites_shortcode' ) );
		add_shortcode( 'bkx_loyalty_points', array( $this, 'render_loyalty_shortcode' ) );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style(
			'bkx-user-profiles',
			BKX_USER_PROFILES_URL . 'assets/css/frontend.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'bkx-user-profiles',
			BKX_USER_PROFILES_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'bkx-user-profiles',
			'bkxProfiles',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_profiles_nonce' ),
				'i18n'    => array(
					'confirm_cancel'  => __( 'Are you sure you want to cancel this booking?', 'bkx-user-profiles-advanced' ),
					'confirm_rebook'  => __( 'Rebook this service?', 'bkx-user-profiles-advanced' ),
					'points_redeemed' => __( 'Points redeemed successfully!', 'bkx-user-profiles-advanced' ),
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
		if ( 'bookingx_page_bkx-user-profiles' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-user-profiles-admin',
			BKX_USER_PROFILES_URL . 'assets/css/admin.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'bkx-user-profiles-admin',
			BKX_USER_PROFILES_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'bkx-user-profiles-admin',
			'bkxProfilesAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_profiles_admin_nonce' ),
			)
		);
	}

	/**
	 * Handle booking created.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $data Booking data.
	 * @return void
	 */
	public function on_booking_created( int $booking_id, array $data ): void {
		$user_id = get_current_user_id();

		if ( ! $user_id && $this->get_setting( 'auto_create_account', true ) ) {
			$user_id = $this->profile_service->maybe_create_user( $data );
		}

		if ( $user_id ) {
			update_post_meta( $booking_id, 'user_id', $user_id );
			$this->profile_service->update_last_booking( $user_id, $booking_id );
		}
	}

	/**
	 * Handle booking completed.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function on_booking_completed( int $booking_id ): void {
		if ( ! $this->get_setting( 'enable_loyalty', true ) ) {
			return;
		}

		$user_id = get_post_meta( $booking_id, 'user_id', true );

		if ( ! $user_id ) {
			return;
		}

		// Award points.
		$total            = get_post_meta( $booking_id, 'total_price', true );
		$points_per_booking = $this->get_setting( 'points_per_booking', 10 );
		$points_per_currency = $this->get_setting( 'points_per_currency', 1 );

		$points = $points_per_booking + ( floatval( $total ) * $points_per_currency );

		$this->loyalty_service->award_points(
			(int) $user_id,
			(int) $points,
			'booking_completed',
			$booking_id
		);
	}

	/**
	 * Handle booking cancelled.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function on_booking_cancelled( int $booking_id ): void {
		// Optionally deduct points for cancelled bookings.
		$user_id = get_post_meta( $booking_id, 'user_id', true );

		if ( $user_id ) {
			$this->profile_service->increment_cancellation_count( (int) $user_id );
		}
	}

	/**
	 * Handle user registration.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_user_register( int $user_id ): void {
		$this->profile_service->create_profile( $user_id );
	}

	/**
	 * Handle user deletion.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_user_delete( int $user_id ): void {
		$this->profile_service->delete_profile( $user_id );
		$this->loyalty_service->delete_user_points( $user_id );
		$this->favorites_service->delete_user_favorites( $user_id );
	}

	/**
	 * AJAX: Update profile.
	 *
	 * @return void
	 */
	public function ajax_update_profile(): void {
		check_ajax_referer( 'bkx_profiles_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-user-profiles-advanced' ) ) );
		}

		$user_id = get_current_user_id();
		$data    = array(
			'phone'                    => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'preferred_time'           => sanitize_text_field( wp_unslash( $_POST['preferred_time'] ?? '' ) ),
			'communication_preference' => sanitize_text_field( wp_unslash( $_POST['communication_preference'] ?? 'email' ) ),
			'notes'                    => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
		);

		$result = $this->profile_service->update_profile( $user_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Profile updated successfully.', 'bkx-user-profiles-advanced' ) ) );
	}

	/**
	 * AJAX: Toggle favorite.
	 *
	 * @return void
	 */
	public function ajax_toggle_favorite(): void {
		check_ajax_referer( 'bkx_profiles_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-user-profiles-advanced' ) ) );
		}

		$user_id = get_current_user_id();
		$item_id = absint( $_POST['item_id'] ?? 0 );
		$type    = sanitize_text_field( $_POST['type'] ?? 'service' );

		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid item.', 'bkx-user-profiles-advanced' ) ) );
		}

		$result = $this->favorites_service->toggle_favorite( $user_id, $item_id, $type );

		wp_send_json_success(
			array(
				'is_favorite' => $result,
				'message'     => $result
					? __( 'Added to favorites.', 'bkx-user-profiles-advanced' )
					: __( 'Removed from favorites.', 'bkx-user-profiles-advanced' ),
			)
		);
	}

	/**
	 * AJAX: Redeem points.
	 *
	 * @return void
	 */
	public function ajax_redeem_points(): void {
		check_ajax_referer( 'bkx_profiles_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-user-profiles-advanced' ) ) );
		}

		$user_id = get_current_user_id();
		$points  = absint( $_POST['points'] ?? 0 );

		if ( ! $points ) {
			wp_send_json_error( array( 'message' => __( 'Invalid points amount.', 'bkx-user-profiles-advanced' ) ) );
		}

		$result = $this->loyalty_service->redeem_points( $user_id, $points );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'discount'   => $result['discount'],
				'new_balance' => $result['new_balance'],
				'message'    => sprintf(
					/* translators: %s: discount amount */
					__( 'Redeemed! You have a $%s discount on your next booking.', 'bkx-user-profiles-advanced' ),
					$result['discount']
				),
			)
		);
	}

	/**
	 * AJAX: Cancel booking.
	 *
	 * @return void
	 */
	public function ajax_cancel_booking(): void {
		check_ajax_referer( 'bkx_profiles_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-user-profiles-advanced' ) ) );
		}

		$user_id    = get_current_user_id();
		$booking_id = absint( $_POST['booking_id'] ?? 0 );

		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking.', 'bkx-user-profiles-advanced' ) ) );
		}

		// Verify ownership.
		$booking_user = get_post_meta( $booking_id, 'user_id', true );
		if ( (int) $booking_user !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'You cannot cancel this booking.', 'bkx-user-profiles-advanced' ) ) );
		}

		// Check cancellation period.
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $booking_id, 'booking_time', true );
		$cancel_hours = $this->get_setting( 'cancellation_period', 24 );

		$booking_datetime = strtotime( $booking_date . ' ' . $booking_time );
		$min_cancel_time  = $booking_datetime - ( $cancel_hours * 3600 );

		if ( time() > $min_cancel_time ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: hours */
						__( 'Bookings cannot be cancelled less than %d hours before the appointment.', 'bkx-user-profiles-advanced' ),
						$cancel_hours
					),
				)
			);
		}

		// Update booking status.
		wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => 'bkx-cancelled',
			)
		);

		/**
		 * Fires when a booking is cancelled by the customer.
		 *
		 * @param int $booking_id Booking ID.
		 * @param int $user_id User ID.
		 */
		do_action( 'bkx_booking_cancelled', $booking_id );

		wp_send_json_success( array( 'message' => __( 'Booking cancelled successfully.', 'bkx-user-profiles-advanced' ) ) );
	}

	/**
	 * AJAX: Rebook.
	 *
	 * @return void
	 */
	public function ajax_rebook(): void {
		check_ajax_referer( 'bkx_profiles_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-user-profiles-advanced' ) ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );

		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking.', 'bkx-user-profiles-advanced' ) ) );
		}

		// Get booking details for rebook URL.
		$seat_id = get_post_meta( $booking_id, 'seat_id', true );
		$base_id = get_post_meta( $booking_id, 'base_id', true );

		$booking_url = add_query_arg(
			array(
				'seat' => $seat_id,
				'base' => $base_id,
			),
			get_permalink( $this->get_setting( 'profile_page_id', 0 ) ) ?: home_url( '/booking/' )
		);

		wp_send_json_success( array( 'redirect_url' => $booking_url ) );
	}

	/**
	 * Add profile fields to booking form.
	 *
	 * @param array $fields Form fields.
	 * @return array Modified fields.
	 */
	public function add_profile_fields( array $fields ): array {
		if ( ! is_user_logged_in() ) {
			return $fields;
		}

		$user_id = get_current_user_id();
		$profile = $this->profile_service->get_profile( $user_id );

		if ( $profile ) {
			// Pre-fill fields from profile.
			if ( isset( $fields['phone'] ) && ! empty( $profile->phone ) ) {
				$fields['phone']['default'] = $profile->phone;
			}
		}

		return $fields;
	}

	/**
	 * Apply loyalty discount to booking total.
	 *
	 * @param float $total Booking total.
	 * @param int   $booking_id Booking ID.
	 * @return float Modified total.
	 */
	public function apply_loyalty_discount( float $total, int $booking_id ): float {
		if ( ! is_user_logged_in() || ! $this->get_setting( 'enable_loyalty', true ) ) {
			return $total;
		}

		$user_id  = get_current_user_id();
		$discount = $this->loyalty_service->get_pending_discount( $user_id );

		if ( $discount > 0 ) {
			$total = max( 0, $total - $discount );
			$this->loyalty_service->apply_discount( $user_id, $booking_id, $discount );
		}

		return $total;
	}

	/**
	 * Render profile shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_profile_shortcode( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your profile.', 'bkx-user-profiles-advanced' ) . '</p>';
		}

		ob_start();
		include BKX_USER_PROFILES_PATH . 'templates/frontend/profile.php';
		return ob_get_clean();
	}

	/**
	 * Render booking history shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_history_shortcode( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your booking history.', 'bkx-user-profiles-advanced' ) . '</p>';
		}

		ob_start();
		include BKX_USER_PROFILES_PATH . 'templates/frontend/booking-history.php';
		return ob_get_clean();
	}

	/**
	 * Render favorites shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_favorites_shortcode( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your favorites.', 'bkx-user-profiles-advanced' ) . '</p>';
		}

		ob_start();
		include BKX_USER_PROFILES_PATH . 'templates/frontend/favorites.php';
		return ob_get_clean();
	}

	/**
	 * Render loyalty points shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_loyalty_shortcode( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your loyalty points.', 'bkx-user-profiles-advanced' ) . '</p>';
		}

		ob_start();
		include BKX_USER_PROFILES_PATH . 'templates/frontend/loyalty-points.php';
		return ob_get_clean();
	}

	/**
	 * Get profile service.
	 *
	 * @return ProfileService Profile service instance.
	 */
	public function get_profile_service(): ProfileService {
		return $this->profile_service;
	}

	/**
	 * Get loyalty service.
	 *
	 * @return LoyaltyService Loyalty service instance.
	 */
	public function get_loyalty_service(): LoyaltyService {
		return $this->loyalty_service;
	}

	/**
	 * Get favorites service.
	 *
	 * @return FavoritesService Favorites service instance.
	 */
	public function get_favorites_service(): FavoritesService {
		return $this->favorites_service;
	}
}
