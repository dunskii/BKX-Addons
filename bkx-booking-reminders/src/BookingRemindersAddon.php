<?php
/**
 * Booking Reminders Addon Main Class
 *
 * Main entry point for the Booking Reminders addon.
 *
 * @package BookingX\BookingReminders
 * @since   1.0.0
 */

namespace BookingX\BookingReminders;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasCron;
use BookingX\BookingReminders\Admin\SettingsPage;
use BookingX\BookingReminders\Migrations\CreateRemindersTables;
use BookingX\BookingReminders\Services\ReminderService;
use BookingX\BookingReminders\Services\EmailService;
use BookingX\BookingReminders\Services\SmsService;
use BookingX\BookingReminders\Schedulers\ReminderScheduler;

/**
 * Main Booking Reminders addon class.
 *
 * @since 1.0.0
 */
class BookingRemindersAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasCron;

	/**
	 * Singleton instance.
	 *
	 * @var BookingRemindersAddon|null
	 */
	private static ?BookingRemindersAddon $instance = null;

	/**
	 * Reminder service.
	 *
	 * @var ReminderService|null
	 */
	protected ?ReminderService $reminder_service = null;

	/**
	 * Email service.
	 *
	 * @var EmailService|null
	 */
	protected ?EmailService $email_service = null;

	/**
	 * SMS service.
	 *
	 * @var SmsService|null
	 */
	protected ?SmsService $sms_service = null;

	/**
	 * Reminder scheduler.
	 *
	 * @var ReminderScheduler|null
	 */
	protected ?ReminderScheduler $scheduler = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 * @return BookingRemindersAddon
	 */
	public static function get_instance(): BookingRemindersAddon {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get addon ID.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_id(): string {
		return 'booking-reminders';
	}

	/**
	 * Get addon name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Booking Reminders', 'bkx-booking-reminders' );
	}

	/**
	 * Get addon version.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_version(): string {
		return BKX_BOOKING_REMINDERS_VERSION;
	}

	/**
	 * Get addon file path.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_file(): string {
		return BKX_BOOKING_REMINDERS_FILE;
	}

	/**
	 * Get addon path.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_path(): string {
		return BKX_BOOKING_REMINDERS_PATH;
	}

	/**
	 * Get addon URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_url(): string {
		return BKX_BOOKING_REMINDERS_URL;
	}

	/**
	 * Get minimum BookingX version required.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_min_core_version(): string {
		return '2.0.0';
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->load_textdomain();
		$this->init_settings();
		$this->init_database();
		$this->init_services();
		$this->init_admin();
		$this->init_hooks();
		$this->init_cron();
	}

	/**
	 * Load text domain for translations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function load_textdomain(): void {
		load_plugin_textdomain(
			'bkx-booking-reminders',
			false,
			dirname( BKX_BOOKING_REMINDERS_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_settings(): void {
		$this->settings_key = 'bkx_booking_reminders_settings';

		$this->default_settings = array(
			'enabled'                    => true,
			// Email settings.
			'email_enabled'              => true,
			'email_reminder_1_enabled'   => true,
			'email_reminder_1_time'      => 24, // hours before.
			'email_reminder_2_enabled'   => true,
			'email_reminder_2_time'      => 2, // hours before.
			'email_reminder_3_enabled'   => false,
			'email_reminder_3_time'      => 48, // hours before.
			'email_subject'              => __( 'Reminder: Your upcoming appointment', 'bkx-booking-reminders' ),
			'email_template'             => 'default',
			// SMS settings.
			'sms_enabled'                => false,
			'sms_provider'               => 'twilio',
			'sms_reminder_1_enabled'     => true,
			'sms_reminder_1_time'        => 24, // hours before.
			'sms_reminder_2_enabled'     => false,
			'sms_reminder_2_time'        => 2, // hours before.
			// Twilio settings.
			'twilio_account_sid'         => '',
			'twilio_auth_token'          => '',
			'twilio_phone_number'        => '',
			// Follow-up settings.
			'followup_enabled'           => true,
			'followup_time'              => 24, // hours after.
			'followup_request_review'    => true,
			// General settings.
			'exclude_statuses'           => array( 'bkx-cancelled', 'bkx-missed' ),
			'include_ical'               => true,
			'debug_log'                  => false,
		);

		$this->load_settings();
	}

	/**
	 * Initialize database tables.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_database(): void {
		$this->migrations = array(
			CreateRemindersTables::class,
		);

		// Run migrations on activation.
		if ( get_option( 'bkx_booking_reminders_activated' ) ) {
			$this->run_migrations();
			delete_option( 'bkx_booking_reminders_activated' );
		}
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_services(): void {
		$this->email_service    = new EmailService( $this );
		$this->sms_service      = new SmsService( $this );
		$this->reminder_service = new ReminderService( $this, $this->email_service, $this->sms_service );
		$this->scheduler        = new ReminderScheduler( $this, $this->reminder_service );
	}

	/**
	 * Initialize admin features.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_admin(): void {
		if ( is_admin() ) {
			new SettingsPage( $this );
		}
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_hooks(): void {
		// When a booking is created, schedule reminders.
		add_action( 'bkx_booking_created', array( $this, 'schedule_reminders_for_booking' ), 10, 2 );

		// When a booking is updated, reschedule reminders.
		add_action( 'bkx_booking_updated', array( $this, 'reschedule_reminders_for_booking' ), 10, 2 );

		// When a booking is cancelled, cancel reminders.
		add_action( 'bkx_booking_cancelled', array( $this, 'cancel_reminders_for_booking' ), 10, 1 );

		// When a booking is completed, schedule follow-up.
		add_action( 'bkx_booking_completed', array( $this, 'schedule_followup_for_booking' ), 10, 1 );

		// Admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add meta box to booking edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_reminder_meta_box' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_send_test_reminder', array( $this, 'ajax_send_test_reminder' ) );
		add_action( 'wp_ajax_bkx_resend_reminder', array( $this, 'ajax_resend_reminder' ) );
	}

	/**
	 * Initialize cron jobs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_cron(): void {
		// Process scheduled reminders.
		add_action( 'bkx_booking_reminders_process', array( $this->scheduler, 'process_pending_reminders' ) );
	}

	/**
	 * Schedule reminders for a new booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function schedule_reminders_for_booking( int $booking_id, array $booking_data ): void {
		if ( ! $this->get_setting( 'enabled', true ) ) {
			return;
		}

		$this->scheduler->schedule_reminders( $booking_id );
	}

	/**
	 * Reschedule reminders when booking is updated.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function reschedule_reminders_for_booking( int $booking_id, array $booking_data ): void {
		if ( ! $this->get_setting( 'enabled', true ) ) {
			return;
		}

		// Cancel existing and reschedule.
		$this->scheduler->cancel_reminders( $booking_id );
		$this->scheduler->schedule_reminders( $booking_id );
	}

	/**
	 * Cancel reminders when booking is cancelled.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function cancel_reminders_for_booking( int $booking_id ): void {
		$this->scheduler->cancel_reminders( $booking_id );
	}

	/**
	 * Schedule follow-up when booking is completed.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function schedule_followup_for_booking( int $booking_id ): void {
		if ( ! $this->get_setting( 'followup_enabled', true ) ) {
			return;
		}

		$this->scheduler->schedule_followup( $booking_id );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		// Only load on relevant pages.
		$allowed_hooks = array(
			'toplevel_page_bookingx-settings',
			'post.php',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_script(
			'bkx-booking-reminders-admin',
			BKX_BOOKING_REMINDERS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_BOOKING_REMINDERS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-booking-reminders-admin',
			'bkxRemindersAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_reminders_admin' ),
				'i18n'    => array(
					'sending'    => __( 'Sending...', 'bkx-booking-reminders' ),
					'sent'       => __( 'Sent!', 'bkx-booking-reminders' ),
					'failed'     => __( 'Failed to send', 'bkx-booking-reminders' ),
					'confirmResend' => __( 'Are you sure you want to resend this reminder?', 'bkx-booking-reminders' ),
				),
			)
		);

		wp_enqueue_style(
			'bkx-booking-reminders-admin',
			BKX_BOOKING_REMINDERS_URL . 'assets/css/admin.css',
			array(),
			BKX_BOOKING_REMINDERS_VERSION
		);
	}

	/**
	 * Add reminder meta box to booking edit screen.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_reminder_meta_box(): void {
		add_meta_box(
			'bkx_booking_reminders',
			__( 'Booking Reminders', 'bkx-booking-reminders' ),
			array( $this, 'render_reminder_meta_box' ),
			'bkx_booking',
			'side',
			'default'
		);
	}

	/**
	 * Render reminder meta box.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_reminder_meta_box( \WP_Post $post ): void {
		$reminders = $this->reminder_service->get_reminders_for_booking( $post->ID );

		if ( empty( $reminders ) ) {
			echo '<p>' . esc_html__( 'No reminders scheduled for this booking.', 'bkx-booking-reminders' ) . '</p>';
			return;
		}

		echo '<ul class="bkx-reminder-list">';
		foreach ( $reminders as $reminder ) {
			$status_class = 'bkx-reminder-' . esc_attr( $reminder->status );
			$status_label = $this->get_status_label( $reminder->status );
			$scheduled    = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $reminder->scheduled_at ) );

			printf(
				'<li class="%s"><strong>%s</strong> - %s<br><small>%s: %s</small>%s</li>',
				esc_attr( $status_class ),
				esc_html( ucfirst( $reminder->reminder_type ) ),
				esc_html( $status_label ),
				esc_html__( 'Scheduled', 'bkx-booking-reminders' ),
				esc_html( $scheduled ),
				'sent' === $reminder->status ? '' : sprintf(
					' <button type="button" class="button button-small bkx-resend-reminder" data-id="%d">%s</button>',
					absint( $reminder->id ),
					esc_html__( 'Send Now', 'bkx-booking-reminders' )
				)
			);
		}
		echo '</ul>';

		wp_nonce_field( 'bkx_reminders_meta_box', 'bkx_reminders_nonce' );
	}

	/**
	 * Get status label.
	 *
	 * @since 1.0.0
	 * @param string $status Status key.
	 * @return string
	 */
	protected function get_status_label( string $status ): string {
		$labels = array(
			'pending'   => __( 'Pending', 'bkx-booking-reminders' ),
			'sent'      => __( 'Sent', 'bkx-booking-reminders' ),
			'failed'    => __( 'Failed', 'bkx-booking-reminders' ),
			'cancelled' => __( 'Cancelled', 'bkx-booking-reminders' ),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * AJAX handler: Send test reminder.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_send_test_reminder(): void {
		check_ajax_referer( 'bkx_reminders_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-booking-reminders' ) ) );
		}

		$type  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'email';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		if ( 'email' === $type && empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email address is required.', 'bkx-booking-reminders' ) ) );
		}

		if ( 'sms' === $type && empty( $phone ) ) {
			wp_send_json_error( array( 'message' => __( 'Phone number is required.', 'bkx-booking-reminders' ) ) );
		}

		// Send test reminder.
		$result = $this->reminder_service->send_test_reminder( $type, $email, $phone );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}
	}

	/**
	 * AJAX handler: Resend reminder.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_resend_reminder(): void {
		check_ajax_referer( 'bkx_reminders_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-booking-reminders' ) ) );
		}

		$reminder_id = isset( $_POST['reminder_id'] ) ? absint( $_POST['reminder_id'] ) : 0;

		if ( ! $reminder_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid reminder ID.', 'bkx-booking-reminders' ) ) );
		}

		$result = $this->reminder_service->resend_reminder( $reminder_id );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}
	}

	/**
	 * Get the reminder service.
	 *
	 * @since 1.0.0
	 * @return ReminderService|null
	 */
	public function get_reminder_service(): ?ReminderService {
		return $this->reminder_service;
	}

	/**
	 * Get the email service.
	 *
	 * @since 1.0.0
	 * @return EmailService|null
	 */
	public function get_email_service(): ?EmailService {
		return $this->email_service;
	}

	/**
	 * Get the SMS service.
	 *
	 * @since 1.0.0
	 * @return SmsService|null
	 */
	public function get_sms_service(): ?SmsService {
		return $this->sms_service;
	}

	/**
	 * Get settings fields for the settings page.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings_fields(): array {
		return array(
			array(
				'id'      => 'enabled',
				'title'   => __( 'Enable Reminders', 'bkx-booking-reminders' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable booking reminders', 'bkx-booking-reminders' ),
				'default' => true,
			),
			array(
				'id'      => 'email_enabled',
				'title'   => __( 'Email Reminders', 'bkx-booking-reminders' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send email reminders', 'bkx-booking-reminders' ),
				'default' => true,
			),
			array(
				'id'      => 'sms_enabled',
				'title'   => __( 'SMS Reminders', 'bkx-booking-reminders' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send SMS reminders (requires Twilio)', 'bkx-booking-reminders' ),
				'default' => false,
			),
		);
	}
}
