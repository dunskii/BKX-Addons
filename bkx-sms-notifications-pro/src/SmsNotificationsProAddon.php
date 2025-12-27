<?php
/**
 * Main SMS Notifications Pro Addon Class
 *
 * @package BookingX\SmsNotificationsPro
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\SmsNotificationsPro\Admin\SettingsPage;
use BookingX\SmsNotificationsPro\Services\SmsService;
use BookingX\SmsNotificationsPro\Services\TemplateService;
use BookingX\SmsNotificationsPro\Migrations\CreateSmsTables;

/**
 * SMS Notifications Pro Addon class.
 *
 * @since 1.0.0
 */
class SmsNotificationsProAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;

	/**
	 * Singleton instance.
	 *
	 * @var SmsNotificationsProAddon|null
	 */
	protected static ?SmsNotificationsProAddon $instance = null;

	/**
	 * SMS service.
	 *
	 * @var SmsService|null
	 */
	protected ?SmsService $sms_service = null;

	/**
	 * Template service.
	 *
	 * @var TemplateService|null
	 */
	protected ?TemplateService $template_service = null;

	/**
	 * Settings page.
	 *
	 * @var SettingsPage|null
	 */
	protected ?SettingsPage $settings_page = null;

	/**
	 * Get singleton instance.
	 *
	 * @return SmsNotificationsProAddon
	 */
	public static function get_instance(): SmsNotificationsProAddon {
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
		return 'bkx-sms-notifications-pro';
	}

	/**
	 * Get addon name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'SMS Notifications Pro', 'bkx-sms-notifications-pro' );
	}

	/**
	 * Get addon version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return BKX_SMS_PRO_VERSION;
	}

	/**
	 * Get addon file.
	 *
	 * @return string
	 */
	public function get_file(): string {
		return BKX_SMS_PRO_FILE;
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public function get_default_settings(): array {
		return array(
			// General.
			'enabled'              => true,
			'default_country_code' => '+1',

			// Provider Settings.
			'provider'             => 'twilio',

			// Twilio.
			'twilio_account_sid'   => '',
			'twilio_auth_token'    => '',
			'twilio_phone_number'  => '',

			// Vonage (Nexmo).
			'vonage_api_key'       => '',
			'vonage_api_secret'    => '',
			'vonage_from_number'   => '',

			// MessageBird.
			'messagebird_api_key'  => '',
			'messagebird_originator' => '',

			// Plivo.
			'plivo_auth_id'        => '',
			'plivo_auth_token'     => '',
			'plivo_phone_number'   => '',

			// Notification Types.
			'notify_booking_created'    => true,
			'notify_booking_confirmed'  => true,
			'notify_booking_cancelled'  => true,
			'notify_booking_reminder'   => true,
			'notify_booking_completed'  => true,
			'notify_staff'              => true,

			// Recipient Settings.
			'customer_opt_in_required'  => false,
			'customer_opt_in_text'      => __( 'I agree to receive SMS notifications', 'bkx-sms-notifications-pro' ),
			'admin_phone'               => '',

			// Message Settings.
			'sender_name'               => '',
			'max_message_length'        => 160,

			// Rate Limiting.
			'rate_limit_enabled'        => true,
			'rate_limit_per_hour'       => 100,

			// Logging.
			'log_messages'              => true,
			'debug_log'                 => false,
		);
	}

	/**
	 * Get migrations.
	 *
	 * @return array
	 */
	public function get_migrations(): array {
		return array(
			'1.0.0' => CreateSmsTables::class,
		);
	}

	/**
	 * Initialize the addon.
	 *
	 * @return void
	 */
	public function init(): void {
		// Initialize services.
		$this->template_service = new TemplateService( $this );
		$this->sms_service      = new SmsService( $this, $this->template_service );

		// Initialize admin.
		if ( is_admin() ) {
			$this->settings_page = new SettingsPage( $this );
			$this->settings_page->init();
		}

		// Register hooks.
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		// Booking events.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_updated', array( $this, 'on_booking_updated' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'on_booking_cancelled' ) );
		add_action( 'bkx_booking_completed', array( $this, 'on_booking_completed' ) );
		add_action( 'bkx_booking_acknowledged', array( $this, 'on_booking_confirmed' ) );

		// Opt-in checkbox on booking form.
		if ( $this->get_setting( 'customer_opt_in_required', false ) ) {
			add_action( 'bkx_booking_form_after_phone', array( $this, 'render_opt_in_checkbox' ) );
			add_filter( 'bkx_booking_validation', array( $this, 'validate_opt_in' ), 10, 2 );
		}

		// Admin hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_sms_send_test', array( $this, 'ajax_send_test' ) );
		add_action( 'wp_ajax_bkx_sms_get_balance', array( $this, 'ajax_get_balance' ) );
		add_action( 'wp_ajax_bkx_sms_resend', array( $this, 'ajax_resend' ) );

		// Meta box for booking SMS history.
		add_action( 'add_meta_boxes_bkx_booking', array( $this, 'add_booking_meta_box' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Handle booking created.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_created( int $booking_id, array $booking_data ): void {
		if ( ! $this->get_setting( 'enabled', true ) ) {
			return;
		}

		if ( ! $this->get_setting( 'notify_booking_created', true ) ) {
			return;
		}

		// Check opt-in if required.
		if ( $this->get_setting( 'customer_opt_in_required', false ) ) {
			$opted_in = get_post_meta( $booking_id, '_bkx_sms_opt_in', true );
			if ( ! $opted_in ) {
				return;
			}
		}

		// Send to customer.
		$this->sms_service->send_notification(
			$booking_id,
			'booking_created',
			'customer'
		);

		// Send to staff.
		if ( $this->get_setting( 'notify_staff', true ) ) {
			$this->sms_service->send_notification(
				$booking_id,
				'booking_created',
				'staff'
			);
		}

		// Send to admin.
		$admin_phone = $this->get_setting( 'admin_phone', '' );
		if ( $admin_phone ) {
			$this->sms_service->send_notification(
				$booking_id,
				'booking_created',
				'admin'
			);
		}
	}

	/**
	 * Handle booking updated.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_updated( int $booking_id, array $booking_data ): void {
		// Check if significant changes were made.
		$changes = $booking_data['changes'] ?? array();

		if ( empty( $changes ) ) {
			return;
		}

		$significant_changes = array( 'booking_date', 'booking_time', 'seat_id', 'base_id' );
		$has_significant     = array_intersect( array_keys( $changes ), $significant_changes );

		if ( ! $has_significant ) {
			return;
		}

		// Send update notification.
		$this->sms_service->send_notification(
			$booking_id,
			'booking_updated',
			'customer'
		);
	}

	/**
	 * Handle booking confirmed.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function on_booking_confirmed( int $booking_id ): void {
		if ( ! $this->get_setting( 'notify_booking_confirmed', true ) ) {
			return;
		}

		$this->sms_service->send_notification(
			$booking_id,
			'booking_confirmed',
			'customer'
		);
	}

	/**
	 * Handle booking cancelled.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function on_booking_cancelled( int $booking_id ): void {
		if ( ! $this->get_setting( 'notify_booking_cancelled', true ) ) {
			return;
		}

		$this->sms_service->send_notification(
			$booking_id,
			'booking_cancelled',
			'customer'
		);

		if ( $this->get_setting( 'notify_staff', true ) ) {
			$this->sms_service->send_notification(
				$booking_id,
				'booking_cancelled',
				'staff'
			);
		}
	}

	/**
	 * Handle booking completed.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function on_booking_completed( int $booking_id ): void {
		if ( ! $this->get_setting( 'notify_booking_completed', true ) ) {
			return;
		}

		$this->sms_service->send_notification(
			$booking_id,
			'booking_completed',
			'customer'
		);
	}

	/**
	 * Render opt-in checkbox.
	 *
	 * @return void
	 */
	public function render_opt_in_checkbox(): void {
		$text = $this->get_setting( 'customer_opt_in_text', __( 'I agree to receive SMS notifications', 'bkx-sms-notifications-pro' ) );

		?>
		<div class="bkx-sms-opt-in">
			<label>
				<input type="checkbox" name="bkx_sms_opt_in" value="1">
				<?php echo esc_html( $text ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Validate opt-in.
	 *
	 * @param array $errors Existing errors.
	 * @param array $data Form data.
	 * @return array Modified errors.
	 */
	public function validate_opt_in( array $errors, array $data ): array {
		// Store opt-in preference (don't require it).
		if ( isset( $data['bkx_sms_opt_in'] ) && $data['bkx_sms_opt_in'] ) {
			add_action(
				'bkx_booking_created',
				function ( $booking_id ) {
					update_post_meta( $booking_id, '_bkx_sms_opt_in', 1 );
				}
			);
		}

		return $errors;
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		if ( 'bkx_booking_page_bkx-sms-settings' !== $screen->id && 'bkx_booking' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'bkx-sms-pro-admin',
			BKX_SMS_PRO_URL . 'assets/css/admin.css',
			array(),
			BKX_SMS_PRO_VERSION
		);

		wp_enqueue_script(
			'bkx-sms-pro-admin',
			BKX_SMS_PRO_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_SMS_PRO_VERSION,
			true
		);

		wp_localize_script(
			'bkx-sms-pro-admin',
			'bkxSmsPro',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_sms_pro' ),
				'i18n'    => array(
					'sending'      => __( 'Sending...', 'bkx-sms-notifications-pro' ),
					'sent'         => __( 'Sent!', 'bkx-sms-notifications-pro' ),
					'failed'       => __( 'Failed', 'bkx-sms-notifications-pro' ),
					'checking'     => __( 'Checking...', 'bkx-sms-notifications-pro' ),
					'confirmResend' => __( 'Are you sure you want to resend this message?', 'bkx-sms-notifications-pro' ),
				),
			)
		);
	}

	/**
	 * Add booking meta box.
	 *
	 * @return void
	 */
	public function add_booking_meta_box(): void {
		add_meta_box(
			'bkx-sms-history',
			__( 'SMS History', 'bkx-sms-notifications-pro' ),
			array( $this, 'render_booking_meta_box' ),
			'bkx_booking',
			'side'
		);
	}

	/**
	 * Render booking SMS history meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_booking_meta_box( \WP_Post $post ): void {
		$history = $this->sms_service->get_booking_history( $post->ID );

		if ( empty( $history ) ) {
			echo '<p>' . esc_html__( 'No SMS messages sent for this booking.', 'bkx-sms-notifications-pro' ) . '</p>';
			return;
		}

		?>
		<ul class="bkx-sms-history-list">
			<?php foreach ( $history as $entry ) : ?>
				<li class="bkx-sms-entry bkx-sms-<?php echo esc_attr( $entry->status ); ?>">
					<div class="bkx-sms-info">
						<strong><?php echo esc_html( $entry->recipient ); ?></strong>
						<span class="bkx-sms-status"><?php echo esc_html( ucfirst( $entry->status ) ); ?></span>
					</div>
					<div class="bkx-sms-meta">
						<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->sent_at ) ) ); ?>
					</div>
					<?php if ( 'failed' === $entry->status ) : ?>
						<button type="button" class="button button-small bkx-sms-resend" data-id="<?php echo esc_attr( $entry->id ); ?>">
							<?php esc_html_e( 'Resend', 'bkx-sms-notifications-pro' ); ?>
						</button>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * AJAX: Send test SMS.
	 *
	 * @return void
	 */
	public function ajax_send_test(): void {
		check_ajax_referer( 'bkx_sms_pro', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-sms-notifications-pro' ) ) );
		}

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		if ( empty( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Phone number is required.', 'bkx-sms-notifications-pro' ) ) );
		}

		$result = $this->sms_service->send_test_message( $phone );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Test message sent!', 'bkx-sms-notifications-pro' ) ) );
	}

	/**
	 * AJAX: Get account balance.
	 *
	 * @return void
	 */
	public function ajax_get_balance(): void {
		check_ajax_referer( 'bkx_sms_pro', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-sms-notifications-pro' ) ) );
		}

		$result = $this->sms_service->get_account_balance();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Resend message.
	 *
	 * @return void
	 */
	public function ajax_resend(): void {
		check_ajax_referer( 'bkx_sms_pro', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-sms-notifications-pro' ) ) );
		}

		$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;

		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid message ID.', 'bkx-sms-notifications-pro' ) ) );
		}

		$result = $this->sms_service->resend_message( $message_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Message resent!', 'bkx-sms-notifications-pro' ) ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'bkx-sms/v1',
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats_api' ),
				'permission_callback' => array( $this, 'check_admin_permissions' ),
			)
		);
	}

	/**
	 * Check admin permissions for REST API.
	 *
	 * @return bool
	 */
	public function check_admin_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get SMS stats via REST API.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stats_api(): \WP_REST_Response {
		$stats = $this->sms_service->get_stats();
		return new \WP_REST_Response( $stats, 200 );
	}

	/**
	 * Get SMS service.
	 *
	 * @return SmsService
	 */
	public function get_sms_service(): SmsService {
		return $this->sms_service;
	}

	/**
	 * Get template service.
	 *
	 * @return TemplateService
	 */
	public function get_template_service(): TemplateService {
		return $this->template_service;
	}
}
