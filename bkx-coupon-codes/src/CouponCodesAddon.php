<?php
/**
 * Main Coupon Codes Addon Class
 *
 * @package BookingX\CouponCodes
 * @since   1.0.0
 */

namespace BookingX\CouponCodes;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\CouponCodes\Admin\SettingsPage;
use BookingX\CouponCodes\Admin\CouponListTable;
use BookingX\CouponCodes\Services\CouponService;
use BookingX\CouponCodes\Services\DiscountCalculator;
use BookingX\CouponCodes\Migrations\CreateCouponTables;

/**
 * Coupon Codes Addon class.
 *
 * @since 1.0.0
 */
class CouponCodesAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Singleton instance.
	 *
	 * @var CouponCodesAddon|null
	 */
	protected static ?CouponCodesAddon $instance = null;

	/**
	 * Coupon service.
	 *
	 * @var CouponService|null
	 */
	protected ?CouponService $coupon_service = null;

	/**
	 * Discount calculator.
	 *
	 * @var DiscountCalculator|null
	 */
	protected ?DiscountCalculator $discount_calculator = null;

	/**
	 * Settings page.
	 *
	 * @var SettingsPage|null
	 */
	protected ?SettingsPage $settings_page = null;

	/**
	 * Get singleton instance.
	 *
	 * @return CouponCodesAddon
	 */
	public static function get_instance(): CouponCodesAddon {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get addon slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'bkx-coupon-codes';
	}

	/**
	 * Get addon name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Coupon Codes & Discounts', 'bkx-coupon-codes' );
	}

	/**
	 * Get addon version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return BKX_COUPON_CODES_VERSION;
	}

	/**
	 * Get addon file.
	 *
	 * @return string
	 */
	public function get_file(): string {
		return BKX_COUPON_CODES_FILE;
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public function get_default_settings(): array {
		return array(
			// General Settings.
			'enable_coupons'            => true,
			'coupon_field_label'        => __( 'Have a coupon code?', 'bkx-coupon-codes' ),
			'coupon_placeholder'        => __( 'Enter coupon code', 'bkx-coupon-codes' ),
			'apply_button_text'         => __( 'Apply', 'bkx-coupon-codes' ),
			'show_discount_breakdown'   => true,

			// Discount Types.
			'allow_percentage'          => true,
			'allow_fixed_amount'        => true,
			'allow_free_service'        => true,
			'allow_free_extra'          => true,

			// Restrictions.
			'allow_stacking'            => false,
			'max_coupons_per_booking'   => 1,
			'require_login'             => false,
			'min_booking_amount'        => 0,

			// Usage Limits.
			'default_usage_limit'       => 0,
			'default_per_user_limit'    => 0,

			// Display Settings.
			'show_savings'              => true,
			'savings_format'            => 'You save {amount}!',

			// Email Notifications.
			'notify_admin_on_use'       => false,
			'admin_notification_email'  => get_option( 'admin_email' ),

			// Expiration.
			'show_expiry_warning'       => true,
			'expiry_warning_days'       => 3,
		);
	}

	/**
	 * Get migrations.
	 *
	 * @return array
	 */
	public function get_migrations(): array {
		return array(
			'1.0.0' => CreateCouponTables::class,
		);
	}

	/**
	 * Initialize the addon.
	 *
	 * @return void
	 */
	public function init(): void {
		// Initialize services.
		$this->coupon_service      = new CouponService( $this );
		$this->discount_calculator = new DiscountCalculator( $this );

		// Initialize admin.
		if ( is_admin() ) {
			$this->settings_page = new SettingsPage( $this );
			$this->settings_page->init();
		}

		// Register hooks.
		$this->register_hooks();

		// Register AJAX handlers.
		$this->register_ajax_handlers();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		// Frontend coupon field.
		add_action( 'bkx_booking_form_after_extras', array( $this, 'render_coupon_field' ) );
		add_action( 'bkx_booking_form_before_payment', array( $this, 'render_coupon_field' ) );

		// Booking price calculation.
		add_filter( 'bkx_booking_total_price', array( $this, 'apply_coupon_discount' ), 10, 2 );
		add_filter( 'bkx_booking_price_breakdown', array( $this, 'add_discount_to_breakdown' ), 10, 2 );

		// Booking save/update.
		add_action( 'bkx_booking_created', array( $this, 'record_coupon_usage' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'release_coupon_usage' ) );

		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Frontend scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Booking confirmation email.
		add_filter( 'bkx_booking_email_placeholders', array( $this, 'add_email_placeholders' ), 10, 2 );

		// Admin booking details.
		add_action( 'bkx_booking_meta_box', array( $this, 'render_booking_coupon_info' ) );
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	protected function register_ajax_handlers(): void {
		add_action( 'wp_ajax_bkx_validate_coupon', array( $this, 'ajax_validate_coupon' ) );
		add_action( 'wp_ajax_nopriv_bkx_validate_coupon', array( $this, 'ajax_validate_coupon' ) );
		add_action( 'wp_ajax_bkx_apply_coupon', array( $this, 'ajax_apply_coupon' ) );
		add_action( 'wp_ajax_nopriv_bkx_apply_coupon', array( $this, 'ajax_apply_coupon' ) );
		add_action( 'wp_ajax_bkx_remove_coupon', array( $this, 'ajax_remove_coupon' ) );
		add_action( 'wp_ajax_nopriv_bkx_remove_coupon', array( $this, 'ajax_remove_coupon' ) );

		// Admin AJAX.
		add_action( 'wp_ajax_bkx_admin_create_coupon', array( $this, 'ajax_create_coupon' ) );
		add_action( 'wp_ajax_bkx_admin_delete_coupon', array( $this, 'ajax_delete_coupon' ) );
		add_action( 'wp_ajax_bkx_admin_toggle_coupon', array( $this, 'ajax_toggle_coupon' ) );
		add_action( 'wp_ajax_bkx_generate_coupon_code', array( $this, 'ajax_generate_code' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Coupon Codes', 'bkx-coupon-codes' ),
			__( 'Coupons', 'bkx-coupon-codes' ),
			'manage_options',
			'bkx-coupons',
			array( $this, 'render_coupons_page' )
		);
	}

	/**
	 * Render coupons admin page.
	 *
	 * @return void
	 */
	public function render_coupons_page(): void {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		switch ( $action ) {
			case 'add':
			case 'edit':
				$this->render_coupon_form();
				break;
			default:
				$this->render_coupon_list();
				break;
		}
	}

	/**
	 * Render coupon list.
	 *
	 * @return void
	 */
	protected function render_coupon_list(): void {
		$list_table = new CouponListTable( $this );
		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Coupon Codes', 'bkx-coupon-codes' ); ?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-coupons&action=add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New Coupon', 'bkx-coupon-codes' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="post_type" value="bkx_booking">
				<input type="hidden" name="page" value="bkx-coupons">
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render coupon add/edit form.
	 *
	 * @return void
	 */
	protected function render_coupon_form(): void {
		$coupon_id = isset( $_GET['coupon_id'] ) ? absint( $_GET['coupon_id'] ) : 0;
		$coupon    = $coupon_id ? $this->coupon_service->get_coupon( $coupon_id ) : null;
		$is_edit   = ! empty( $coupon );

		// Handle form submission.
		if ( isset( $_POST['bkx_save_coupon'] ) ) {
			check_admin_referer( 'bkx_save_coupon', 'bkx_coupon_nonce' );

			$data = $this->sanitize_coupon_form_data( $_POST );

			if ( $is_edit ) {
				$result = $this->coupon_service->update_coupon( $coupon_id, $data );
			} else {
				$result = $this->coupon_service->create_coupon( $data );
			}

			if ( is_wp_error( $result ) ) {
				$error_message = $result->get_error_message();
			} else {
				wp_safe_redirect( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-coupons&message=saved' ) );
				exit;
			}
		}

		include BKX_COUPON_CODES_PATH . 'templates/admin/coupon-form.php';
	}

	/**
	 * Sanitize coupon form data.
	 *
	 * @param array $data Form data.
	 * @return array Sanitized data.
	 */
	protected function sanitize_coupon_form_data( array $data ): array {
		return array(
			'code'               => sanitize_text_field( strtoupper( $data['code'] ?? '' ) ),
			'description'        => sanitize_textarea_field( $data['description'] ?? '' ),
			'discount_type'      => sanitize_text_field( $data['discount_type'] ?? 'percentage' ),
			'discount_value'     => floatval( $data['discount_value'] ?? 0 ),
			'min_booking_amount' => floatval( $data['min_booking_amount'] ?? 0 ),
			'max_discount'       => floatval( $data['max_discount'] ?? 0 ),
			'usage_limit'        => absint( $data['usage_limit'] ?? 0 ),
			'per_user_limit'     => absint( $data['per_user_limit'] ?? 0 ),
			'start_date'         => sanitize_text_field( $data['start_date'] ?? '' ),
			'end_date'           => sanitize_text_field( $data['end_date'] ?? '' ),
			'is_active'          => isset( $data['is_active'] ) ? 1 : 0,
			'services'           => array_map( 'absint', $data['services'] ?? array() ),
			'seats'              => array_map( 'absint', $data['seats'] ?? array() ),
			'excluded_services'  => array_map( 'absint', $data['excluded_services'] ?? array() ),
			'user_roles'         => array_map( 'sanitize_text_field', $data['user_roles'] ?? array() ),
			'first_booking_only' => isset( $data['first_booking_only'] ) ? 1 : 0,
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		$screen = get_current_screen();

		if ( ! $screen || ( 'bkx_booking_page_bkx-coupons' !== $screen->id && 'bkx_booking_page_bkx-coupon-settings' !== $screen->id ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-coupon-codes-admin',
			BKX_COUPON_CODES_URL . 'assets/css/admin.css',
			array(),
			BKX_COUPON_CODES_VERSION
		);

		wp_enqueue_script(
			'bkx-coupon-codes-admin',
			BKX_COUPON_CODES_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			BKX_COUPON_CODES_VERSION,
			true
		);

		wp_localize_script(
			'bkx-coupon-codes-admin',
			'bkxCouponAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_coupon_admin' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Are you sure you want to delete this coupon?', 'bkx-coupon-codes' ),
					'generating'    => __( 'Generating...', 'bkx-coupon-codes' ),
					'saving'        => __( 'Saving...', 'bkx-coupon-codes' ),
					'deleting'      => __( 'Deleting...', 'bkx-coupon-codes' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @return void
	 */
	public function enqueue_frontend_scripts(): void {
		if ( ! $this->get_setting( 'enable_coupons', true ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-coupon-codes',
			BKX_COUPON_CODES_URL . 'assets/css/frontend.css',
			array(),
			BKX_COUPON_CODES_VERSION
		);

		wp_enqueue_script(
			'bkx-coupon-codes',
			BKX_COUPON_CODES_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_COUPON_CODES_VERSION,
			true
		);

		wp_localize_script(
			'bkx-coupon-codes',
			'bkxCoupon',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bkx_coupon_frontend' ),
				'fieldLabel'  => $this->get_setting( 'coupon_field_label' ),
				'placeholder' => $this->get_setting( 'coupon_placeholder' ),
				'applyText'   => $this->get_setting( 'apply_button_text' ),
				'i18n'        => array(
					'applying' => __( 'Applying...', 'bkx-coupon-codes' ),
					'applied'  => __( 'Coupon applied!', 'bkx-coupon-codes' ),
					'removed'  => __( 'Coupon removed', 'bkx-coupon-codes' ),
					'invalid'  => __( 'Invalid coupon code', 'bkx-coupon-codes' ),
					'expired'  => __( 'This coupon has expired', 'bkx-coupon-codes' ),
					'minOrder' => __( 'Minimum order amount not met', 'bkx-coupon-codes' ),
				),
			)
		);
	}

	/**
	 * Render coupon field on booking form.
	 *
	 * @return void
	 */
	public function render_coupon_field(): void {
		if ( ! $this->get_setting( 'enable_coupons', true ) ) {
			return;
		}

		include BKX_COUPON_CODES_PATH . 'templates/frontend/coupon-field.php';
	}

	/**
	 * Apply coupon discount to booking total.
	 *
	 * @param float $total Booking total.
	 * @param array $booking_data Booking data.
	 * @return float Modified total.
	 */
	public function apply_coupon_discount( float $total, array $booking_data ): float {
		$session_coupon = $this->get_session_coupon();

		if ( ! $session_coupon ) {
			return $total;
		}

		$discount = $this->discount_calculator->calculate(
			$session_coupon,
			$total,
			$booking_data
		);

		return max( 0, $total - $discount );
	}

	/**
	 * Add discount to price breakdown.
	 *
	 * @param array $breakdown Price breakdown.
	 * @param array $booking_data Booking data.
	 * @return array Modified breakdown.
	 */
	public function add_discount_to_breakdown( array $breakdown, array $booking_data ): array {
		$session_coupon = $this->get_session_coupon();

		if ( ! $session_coupon ) {
			return $breakdown;
		}

		$subtotal = array_sum( array_column( $breakdown, 'amount' ) );
		$discount = $this->discount_calculator->calculate(
			$session_coupon,
			$subtotal,
			$booking_data
		);

		if ( $discount > 0 ) {
			$breakdown[] = array(
				'label'  => sprintf(
					/* translators: %s: coupon code */
					__( 'Discount (%s)', 'bkx-coupon-codes' ),
					$session_coupon['code']
				),
				'amount' => -$discount,
				'type'   => 'discount',
			);
		}

		return $breakdown;
	}

	/**
	 * Record coupon usage when booking is created.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function record_coupon_usage( int $booking_id, array $booking_data ): void {
		$session_coupon = $this->get_session_coupon();

		if ( ! $session_coupon ) {
			return;
		}

		$subtotal = $booking_data['original_total'] ?? $booking_data['total'] ?? 0;
		$discount = $this->discount_calculator->calculate(
			$session_coupon,
			$subtotal,
			$booking_data
		);

		$this->coupon_service->record_usage(
			$session_coupon['id'],
			$booking_id,
			get_current_user_id(),
			$discount
		);

		// Store coupon info in booking meta.
		update_post_meta( $booking_id, '_bkx_coupon_code', $session_coupon['code'] );
		update_post_meta( $booking_id, '_bkx_coupon_discount', $discount );

		// Clear session.
		$this->clear_session_coupon();

		// Notify admin if enabled.
		if ( $this->get_setting( 'notify_admin_on_use', false ) ) {
			$this->notify_admin_coupon_used( $session_coupon, $booking_id, $discount );
		}
	}

	/**
	 * Release coupon usage when booking is cancelled.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function release_coupon_usage( int $booking_id ): void {
		$coupon_code = get_post_meta( $booking_id, '_bkx_coupon_code', true );

		if ( ! $coupon_code ) {
			return;
		}

		$coupon = $this->coupon_service->get_coupon_by_code( $coupon_code );

		if ( $coupon ) {
			$this->coupon_service->release_usage( $coupon->id, $booking_id );
		}
	}

	/**
	 * Validate coupon via AJAX.
	 *
	 * @return void
	 */
	public function ajax_validate_coupon(): void {
		check_ajax_referer( 'bkx_coupon_frontend', 'nonce' );

		$code = isset( $_POST['code'] ) ? sanitize_text_field( strtoupper( wp_unslash( $_POST['code'] ) ) ) : '';

		if ( empty( $code ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a coupon code.', 'bkx-coupon-codes' ) ) );
		}

		$result = $this->coupon_service->validate_coupon( $code );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'valid'         => true,
				'code'          => $result['code'],
				'discount_type' => $result['discount_type'],
				'discount_text' => $this->format_discount_text( $result ),
			)
		);
	}

	/**
	 * Apply coupon via AJAX.
	 *
	 * @return void
	 */
	public function ajax_apply_coupon(): void {
		check_ajax_referer( 'bkx_coupon_frontend', 'nonce' );

		$code         = isset( $_POST['code'] ) ? sanitize_text_field( strtoupper( wp_unslash( $_POST['code'] ) ) ) : '';
		$booking_data = isset( $_POST['booking_data'] ) ? $this->sanitize_booking_data( wp_unslash( $_POST['booking_data'] ) ) : array();

		if ( empty( $code ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a coupon code.', 'bkx-coupon-codes' ) ) );
		}

		$result = $this->coupon_service->validate_coupon( $code, $booking_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Calculate discount.
		$subtotal = floatval( $booking_data['total'] ?? 0 );
		$discount = $this->discount_calculator->calculate( $result, $subtotal, $booking_data );

		// Store in session.
		$this->set_session_coupon( $result );

		wp_send_json_success(
			array(
				'code'          => $result['code'],
				'discount'      => $discount,
				'new_total'     => max( 0, $subtotal - $discount ),
				'discount_text' => $this->format_discount_text( $result ),
				'savings_text'  => $this->format_savings_text( $discount ),
			)
		);
	}

	/**
	 * Remove coupon via AJAX.
	 *
	 * @return void
	 */
	public function ajax_remove_coupon(): void {
		check_ajax_referer( 'bkx_coupon_frontend', 'nonce' );

		$this->clear_session_coupon();

		wp_send_json_success( array( 'message' => __( 'Coupon removed.', 'bkx-coupon-codes' ) ) );
	}

	/**
	 * Generate coupon code via AJAX.
	 *
	 * @return void
	 */
	public function ajax_generate_code(): void {
		check_ajax_referer( 'bkx_coupon_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-coupon-codes' ) ) );
		}

		$code = $this->coupon_service->generate_unique_code();

		wp_send_json_success( array( 'code' => $code ) );
	}

	/**
	 * Create coupon via AJAX.
	 *
	 * @return void
	 */
	public function ajax_create_coupon(): void {
		check_ajax_referer( 'bkx_coupon_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-coupon-codes' ) ) );
		}

		$data   = $this->sanitize_coupon_form_data( $_POST );
		$result = $this->coupon_service->create_coupon( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'id' => $result ) );
	}

	/**
	 * Delete coupon via AJAX.
	 *
	 * @return void
	 */
	public function ajax_delete_coupon(): void {
		check_ajax_referer( 'bkx_coupon_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-coupon-codes' ) ) );
		}

		$coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;

		if ( ! $coupon_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid coupon ID.', 'bkx-coupon-codes' ) ) );
		}

		$result = $this->coupon_service->delete_coupon( $coupon_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	/**
	 * Toggle coupon status via AJAX.
	 *
	 * @return void
	 */
	public function ajax_toggle_coupon(): void {
		check_ajax_referer( 'bkx_coupon_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-coupon-codes' ) ) );
		}

		$coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;

		if ( ! $coupon_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid coupon ID.', 'bkx-coupon-codes' ) ) );
		}

		$result = $this->coupon_service->toggle_active( $coupon_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'is_active' => $result ) );
	}

	/**
	 * Get coupon from session.
	 *
	 * @return array|null
	 */
	protected function get_session_coupon(): ?array {
		if ( ! session_id() ) {
			return null;
		}

		return $_SESSION['bkx_coupon'] ?? null;
	}

	/**
	 * Set coupon in session.
	 *
	 * @param array $coupon Coupon data.
	 * @return void
	 */
	protected function set_session_coupon( array $coupon ): void {
		if ( ! session_id() ) {
			session_start();
		}

		$_SESSION['bkx_coupon'] = $coupon;
	}

	/**
	 * Clear coupon from session.
	 *
	 * @return void
	 */
	protected function clear_session_coupon(): void {
		if ( ! session_id() ) {
			return;
		}

		unset( $_SESSION['bkx_coupon'] );
	}

	/**
	 * Format discount text for display.
	 *
	 * @param array $coupon Coupon data.
	 * @return string
	 */
	protected function format_discount_text( array $coupon ): string {
		switch ( $coupon['discount_type'] ) {
			case 'percentage':
				return sprintf( '%d%% off', $coupon['discount_value'] );
			case 'fixed':
				return sprintf( '%s off', wc_price( $coupon['discount_value'] ) );
			case 'free_service':
				return __( 'Free service', 'bkx-coupon-codes' );
			case 'free_extra':
				return __( 'Free add-on', 'bkx-coupon-codes' );
			default:
				return '';
		}
	}

	/**
	 * Format savings text for display.
	 *
	 * @param float $discount Discount amount.
	 * @return string
	 */
	protected function format_savings_text( float $discount ): string {
		if ( ! $this->get_setting( 'show_savings', true ) ) {
			return '';
		}

		$format = $this->get_setting( 'savings_format', 'You save {amount}!' );
		return str_replace( '{amount}', wc_price( $discount ), $format );
	}

	/**
	 * Sanitize booking data from AJAX.
	 *
	 * @param array $data Raw booking data.
	 * @return array Sanitized data.
	 */
	protected function sanitize_booking_data( array $data ): array {
		return array(
			'service_id' => absint( $data['service_id'] ?? 0 ),
			'seat_id'    => absint( $data['seat_id'] ?? 0 ),
			'extras'     => array_map( 'absint', $data['extras'] ?? array() ),
			'total'      => floatval( $data['total'] ?? 0 ),
			'user_id'    => get_current_user_id(),
		);
	}

	/**
	 * Notify admin when coupon is used.
	 *
	 * @param array $coupon Coupon data.
	 * @param int   $booking_id Booking ID.
	 * @param float $discount Discount amount.
	 * @return void
	 */
	protected function notify_admin_coupon_used( array $coupon, int $booking_id, float $discount ): void {
		$admin_email = $this->get_setting( 'admin_notification_email', get_option( 'admin_email' ) );

		$subject = sprintf(
			/* translators: %s: coupon code */
			__( 'Coupon "%s" was used', 'bkx-coupon-codes' ),
			$coupon['code']
		);

		$message = sprintf(
			/* translators: 1: coupon code, 2: booking ID, 3: discount amount */
			__( 'Coupon "%1$s" was used on booking #%2$d for a discount of %3$s.', 'bkx-coupon-codes' ),
			$coupon['code'],
			$booking_id,
			wc_price( $discount )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Add email placeholders for coupon info.
	 *
	 * @param array $placeholders Existing placeholders.
	 * @param int   $booking_id Booking ID.
	 * @return array Modified placeholders.
	 */
	public function add_email_placeholders( array $placeholders, int $booking_id ): array {
		$coupon_code = get_post_meta( $booking_id, '_bkx_coupon_code', true );
		$discount    = get_post_meta( $booking_id, '_bkx_coupon_discount', true );

		$placeholders['{coupon_code}']     = $coupon_code ?: '';
		$placeholders['{coupon_discount}'] = $discount ? wc_price( $discount ) : '';

		return $placeholders;
	}

	/**
	 * Render coupon info in booking meta box.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function render_booking_coupon_info( int $booking_id ): void {
		$coupon_code = get_post_meta( $booking_id, '_bkx_coupon_code', true );
		$discount    = get_post_meta( $booking_id, '_bkx_coupon_discount', true );

		if ( ! $coupon_code ) {
			return;
		}

		?>
		<div class="bkx-booking-coupon-info">
			<h4><?php esc_html_e( 'Coupon Applied', 'bkx-coupon-codes' ); ?></h4>
			<p>
				<strong><?php esc_html_e( 'Code:', 'bkx-coupon-codes' ); ?></strong>
				<?php echo esc_html( $coupon_code ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Discount:', 'bkx-coupon-codes' ); ?></strong>
				<?php echo esc_html( wc_price( $discount ) ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'bkx-coupons/v1',
			'/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_validate_coupon' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'code' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST API validate coupon.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_validate_coupon( \WP_REST_Request $request ): \WP_REST_Response {
		$code   = strtoupper( $request->get_param( 'code' ) );
		$result = $this->coupon_service->validate_coupon( $code );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'valid'   => false,
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'valid'         => true,
				'code'          => $result['code'],
				'discount_type' => $result['discount_type'],
				'discount_text' => $this->format_discount_text( $result ),
			),
			200
		);
	}

	/**
	 * Get coupon service.
	 *
	 * @return CouponService
	 */
	public function get_coupon_service(): CouponService {
		return $this->coupon_service;
	}

	/**
	 * Get discount calculator.
	 *
	 * @return DiscountCalculator
	 */
	public function get_discount_calculator(): DiscountCalculator {
		return $this->discount_calculator;
	}
}
