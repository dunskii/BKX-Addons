<?php
/**
 * Rewards Points Addon Main Class
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 */

namespace BookingX\RewardsPoints;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasCron;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\RewardsPoints\Services\PointsService;
use BookingX\RewardsPoints\Services\RedemptionService;
use BookingX\RewardsPoints\Admin\SettingsPage;
use BookingX\RewardsPoints\Migrations\CreatePointsTables;

/**
 * Class RewardsPointsAddon
 *
 * @since 1.0.0
 */
class RewardsPointsAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasCron;
	use HasAjax;

	/**
	 * Addon name.
	 *
	 * @var string
	 */
	protected string $addon_name = 'Rewards Points';

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $addon_slug = 'bkx-rewards-points';

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected string $version = BKX_REWARDS_VERSION;

	/**
	 * Points service instance.
	 *
	 * @var PointsService
	 */
	private PointsService $points_service;

	/**
	 * Redemption service instance.
	 *
	 * @var RedemptionService
	 */
	private RedemptionService $redemption_service;

	/**
	 * Settings page instance.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Boot the addon after initialization.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function boot(): void {
		// Initialize services.
		$this->points_service     = new PointsService( $this );
		$this->redemption_service = new RedemptionService( $this );

		// Initialize admin.
		if ( is_admin() ) {
			$this->settings_page = new SettingsPage( $this );
		}

		// Register hooks.
		$this->register_hooks();

		// Register AJAX handlers.
		$this->register_ajax_handlers();

		// Register shortcodes.
		$this->register_shortcodes();

		// Schedule expiration cron.
		$this->schedule_expiration();
	}

	/**
	 * Run database migrations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run_migrations(): void {
		$migration = new CreatePointsTables();
		$migration->up();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks(): void {
		// Award points on booking completion.
		add_action( 'bkx_booking_completed', array( $this, 'award_booking_points' ), 10, 2 );

		// Award points on user registration.
		if ( $this->get_setting( 'points_on_signup', 0 ) > 0 ) {
			add_action( 'user_register', array( $this, 'award_signup_points' ), 10, 1 );
		}

		// Apply redemption to booking total.
		add_filter( 'bkx_booking_total', array( $this, 'apply_redemption_discount' ), 20, 2 );

		// Add points selector to booking form.
		add_action( 'bkx_booking_form_after_services', array( $this, 'render_points_selector' ) );

		// Enqueue frontend scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		$this->add_ajax_handler( 'bkx_get_user_points', array( $this, 'ajax_get_user_points' ) );
		$this->add_ajax_handler( 'bkx_calculate_redemption', array( $this, 'ajax_calculate_redemption' ) );
		$this->add_ajax_handler( 'bkx_apply_points', array( $this, 'ajax_apply_points' ) );
	}

	/**
	 * Register shortcodes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_shortcodes(): void {
		add_shortcode( 'bkx_my_points', array( $this, 'shortcode_my_points' ) );
		add_shortcode( 'bkx_points_history', array( $this, 'shortcode_points_history' ) );
		add_shortcode( 'bkx_points_balance', array( $this, 'shortcode_points_balance' ) );
	}

	/**
	 * Schedule point expiration cron.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function schedule_expiration(): void {
		if ( ! $this->get_setting( 'enable_expiration', false ) ) {
			return;
		}

		$this->schedule_recurring_task(
			'bkx_rewards_expire_points',
			'daily',
			array( $this->points_service, 'expire_points' )
		);
	}

	/**
	 * Award points for completed booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function award_booking_points( int $booking_id, array $booking_data ): void {
		// Check if already awarded.
		if ( get_post_meta( $booking_id, '_bkx_points_awarded', true ) ) {
			return;
		}

		$user_id = $this->get_booking_user_id( $booking_id );

		if ( ! $user_id ) {
			return;
		}

		$total  = floatval( get_post_meta( $booking_id, 'booking_total', true ) );
		$points = $this->calculate_earning_points( $total );

		if ( $points <= 0 ) {
			return;
		}

		$result = $this->points_service->add_points(
			$user_id,
			$points,
			'booking',
			sprintf(
				/* translators: Booking ID */
				__( 'Points earned from booking #%d', 'bkx-rewards-points' ),
				$booking_id
			),
			$booking_id
		);

		if ( $result ) {
			update_post_meta( $booking_id, '_bkx_points_awarded', $points );
			$this->log( sprintf( 'Awarded %d points to user %d for booking %d', $points, $user_id, $booking_id ) );
		}
	}

	/**
	 * Award points for user registration.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function award_signup_points( int $user_id ): void {
		$points = absint( $this->get_setting( 'points_on_signup', 0 ) );

		if ( $points <= 0 ) {
			return;
		}

		$this->points_service->add_points(
			$user_id,
			$points,
			'signup',
			__( 'Welcome bonus points', 'bkx-rewards-points' )
		);

		$this->log( sprintf( 'Awarded %d signup points to user %d', $points, $user_id ) );
	}

	/**
	 * Calculate earning points from booking total.
	 *
	 * @since 1.0.0
	 * @param float $total Booking total.
	 * @return int
	 */
	private function calculate_earning_points( float $total ): int {
		$rate = floatval( $this->get_setting( 'points_per_dollar', 1 ) );
		return (int) floor( $total * $rate );
	}

	/**
	 * Get user ID from booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return int|null
	 */
	private function get_booking_user_id( int $booking_id ): ?int {
		// Check if booking has user ID.
		$user_id = get_post_meta( $booking_id, 'customer_user_id', true );

		if ( $user_id ) {
			return absint( $user_id );
		}

		// Try to find user by email.
		$email = get_post_meta( $booking_id, 'customer_email', true );

		if ( $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				return $user->ID;
			}
		}

		return null;
	}

	/**
	 * Apply redemption discount to booking total.
	 *
	 * @since 1.0.0
	 * @param float $total      Booking total.
	 * @param int   $booking_id Booking ID.
	 * @return float
	 */
	public function apply_redemption_discount( float $total, int $booking_id ): float {
		$redemption_id = get_post_meta( $booking_id, '_bkx_points_redemption_id', true );

		if ( ! $redemption_id ) {
			return $total;
		}

		$discount = $this->redemption_service->get_redemption_discount( $redemption_id );

		return max( 0, $total - $discount );
	}

	/**
	 * Render points selector on booking form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_points_selector(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$balance = $this->points_service->get_balance( $user_id );

		if ( $balance <= 0 ) {
			return;
		}

		$min_redemption = absint( $this->get_setting( 'min_redemption', 100 ) );

		if ( $balance < $min_redemption ) {
			return;
		}

		include BKX_REWARDS_PATH . 'templates/points-selector.php';
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		wp_enqueue_style(
			'bkx-rewards-frontend',
			BKX_REWARDS_URL . 'assets/css/frontend.css',
			array(),
			BKX_REWARDS_VERSION
		);

		wp_enqueue_script(
			'bkx-rewards-frontend',
			BKX_REWARDS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_REWARDS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-rewards-frontend',
			'bkxRewards',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_rewards_nonce' ),
				'i18n'     => array(
					'loading'    => __( 'Loading...', 'bkx-rewards-points' ),
					'error'      => __( 'An error occurred', 'bkx-rewards-points' ),
					'points'     => __( 'points', 'bkx-rewards-points' ),
					'discount'   => __( 'discount', 'bkx-rewards-points' ),
				),
			)
		);
	}

	/**
	 * AJAX: Get user points balance.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_user_points(): void {
		check_ajax_referer( 'bkx_rewards_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-rewards-points' ) ) );
		}

		$user_id = get_current_user_id();
		$balance = $this->points_service->get_balance( $user_id );

		wp_send_json_success(
			array(
				'balance'          => $balance,
				'formatted'        => number_format( $balance ),
				'min_redemption'   => absint( $this->get_setting( 'min_redemption', 100 ) ),
				'redemption_value' => floatval( $this->get_setting( 'redemption_value', 0.01 ) ),
			)
		);
	}

	/**
	 * AJAX: Calculate redemption value.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_calculate_redemption(): void {
		check_ajax_referer( 'bkx_rewards_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-rewards-points' ) ) );
		}

		$points = absint( $_POST['points'] ?? 0 );

		if ( $points <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid points amount.', 'bkx-rewards-points' ) ) );
		}

		$user_id = get_current_user_id();
		$balance = $this->points_service->get_balance( $user_id );

		if ( $points > $balance ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient points.', 'bkx-rewards-points' ) ) );
		}

		$min_redemption = absint( $this->get_setting( 'min_redemption', 100 ) );

		if ( $points < $min_redemption ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: Minimum points */
						__( 'Minimum redemption is %d points.', 'bkx-rewards-points' ),
						$min_redemption
					),
				)
			);
		}

		$value = $this->redemption_service->calculate_value( $points );

		wp_send_json_success(
			array(
				'points'   => $points,
				'value'    => $value,
				'currency' => get_option( 'bkx_currency', 'USD' ),
			)
		);
	}

	/**
	 * AJAX: Apply points to booking.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_apply_points(): void {
		check_ajax_referer( 'bkx_rewards_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-rewards-points' ) ) );
		}

		$points     = absint( $_POST['points'] ?? 0 );
		$booking_id = absint( $_POST['booking_id'] ?? 0 );

		if ( $points <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid points amount.', 'bkx-rewards-points' ) ) );
		}

		$user_id = get_current_user_id();
		$result  = $this->redemption_service->redeem(
			$user_id,
			$points,
			$booking_id
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'redemption_id' => $result,
				'discount'      => $this->redemption_service->get_redemption_discount( $result ),
				'message'       => __( 'Points applied successfully!', 'bkx-rewards-points' ),
			)
		);
	}

	/**
	 * Shortcode: Display user's points dashboard.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_my_points( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your points.', 'bkx-rewards-points' ) . '</p>';
		}

		$user_id = get_current_user_id();
		$balance = $this->points_service->get_balance( $user_id );
		$history = $this->points_service->get_history( $user_id, 10 );

		ob_start();
		include BKX_REWARDS_PATH . 'templates/my-points.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: Display points history.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_points_history( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'limit' => 20,
			),
			$atts
		);

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your points history.', 'bkx-rewards-points' ) . '</p>';
		}

		$user_id = get_current_user_id();
		$history = $this->points_service->get_history( $user_id, absint( $atts['limit'] ) );

		ob_start();
		include BKX_REWARDS_PATH . 'templates/points-history.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: Display points balance.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_points_balance( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id = get_current_user_id();
		$balance = $this->points_service->get_balance( $user_id );

		return '<span class="bkx-points-balance">' . number_format( $balance ) . '</span>';
	}

	/**
	 * Get the points service.
	 *
	 * @since 1.0.0
	 * @return PointsService
	 */
	public function get_points_service(): PointsService {
		return $this->points_service;
	}

	/**
	 * Get the redemption service.
	 *
	 * @since 1.0.0
	 * @return RedemptionService
	 */
	public function get_redemption_service(): RedemptionService {
		return $this->redemption_service;
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enabled'           => true,
			'points_per_dollar' => 1,
			'redemption_value'  => 0.01, // 1 point = $0.01.
			'min_redemption'    => 100,
			'max_redemption'    => 0, // 0 = unlimited.
			'points_on_signup'  => 0,
			'enable_expiration' => false,
			'expiration_days'   => 365,
		);
	}
}
