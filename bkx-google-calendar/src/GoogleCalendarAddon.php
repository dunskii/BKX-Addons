<?php
/**
 * Main Google Calendar Addon Class
 *
 * @package BookingX\GoogleCalendar
 * @since   1.0.0
 */

namespace BookingX\GoogleCalendar;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasCron;
use BookingX\GoogleCalendar\Admin\SettingsPage;
use BookingX\GoogleCalendar\Services\GoogleApiService;
use BookingX\GoogleCalendar\Services\CalendarSyncService;
use BookingX\GoogleCalendar\Controllers\OAuthController;
use BookingX\GoogleCalendar\Migrations\CreateSyncTables;

/**
 * Google Calendar Addon class.
 *
 * @since 1.0.0
 */
class GoogleCalendarAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasCron;

	/**
	 * Singleton instance.
	 *
	 * @var GoogleCalendarAddon|null
	 */
	protected static ?GoogleCalendarAddon $instance = null;

	/**
	 * Google API service.
	 *
	 * @var GoogleApiService|null
	 */
	protected ?GoogleApiService $google_api = null;

	/**
	 * Calendar sync service.
	 *
	 * @var CalendarSyncService|null
	 */
	protected ?CalendarSyncService $sync_service = null;

	/**
	 * OAuth controller.
	 *
	 * @var OAuthController|null
	 */
	protected ?OAuthController $oauth_controller = null;

	/**
	 * Settings page.
	 *
	 * @var SettingsPage|null
	 */
	protected ?SettingsPage $settings_page = null;

	/**
	 * Get singleton instance.
	 *
	 * @return GoogleCalendarAddon
	 */
	public static function get_instance(): GoogleCalendarAddon {
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
		return 'bkx-google-calendar';
	}

	/**
	 * Get addon name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Google Calendar Integration', 'bkx-google-calendar' );
	}

	/**
	 * Get addon version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return BKX_GOOGLE_CALENDAR_VERSION;
	}

	/**
	 * Get addon file.
	 *
	 * @return string
	 */
	public function get_file(): string {
		return BKX_GOOGLE_CALENDAR_FILE;
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public function get_default_settings(): array {
		return array(
			// API Credentials (encrypted).
			'client_id'            => '',
			'client_secret'        => '',

			// Sync Settings.
			'sync_enabled'         => true,
			'sync_direction'       => 'two_way', // one_way_to_google, one_way_from_google, two_way.
			'sync_interval'        => 15, // minutes.
			'sync_staff_calendars' => true,
			'sync_customer_events' => true,

			// Event Settings.
			'event_title_format'   => '{service_name} - {customer_name}',
			'event_description'    => "Booking Details:\n\nService: {service_name}\nCustomer: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}\n\nNotes: {booking_notes}",
			'event_color'          => '1', // Google Calendar color ID.
			'include_location'     => true,
			'add_reminders'        => true,
			'reminder_minutes'     => 30,

			// Availability Settings.
			'block_busy_times'     => true,
			'check_conflicts'      => true,
			'buffer_minutes'       => 0,

			// Customer Calendar.
			'customer_ical_export' => true,
			'customer_add_to_gcal' => true,

			// Notifications.
			'notify_sync_errors'   => true,
			'error_email'          => get_option( 'admin_email' ),

			// Debug.
			'debug_log'            => false,
		);
	}

	/**
	 * Get migrations.
	 *
	 * @return array
	 */
	public function get_migrations(): array {
		return array(
			'1.0.0' => CreateSyncTables::class,
		);
	}

	/**
	 * Get cron schedules.
	 *
	 * @return array
	 */
	public function get_cron_schedules(): array {
		$interval = $this->get_setting( 'sync_interval', 15 );

		return array(
			'bkx_google_sync' => array(
				'interval' => $interval * MINUTE_IN_SECONDS,
				'display'  => sprintf(
					/* translators: %d: interval in minutes */
					__( 'Every %d minutes', 'bkx-google-calendar' ),
					$interval
				),
			),
		);
	}

	/**
	 * Initialize the addon.
	 *
	 * @return void
	 */
	public function init(): void {
		// Initialize services.
		$this->google_api       = new GoogleApiService( $this );
		$this->sync_service     = new CalendarSyncService( $this, $this->google_api );
		$this->oauth_controller = new OAuthController( $this, $this->google_api );

		// Initialize admin.
		if ( is_admin() ) {
			$this->settings_page = new SettingsPage( $this );
			$this->settings_page->init();
		}

		// Register hooks.
		$this->register_hooks();

		// Schedule cron.
		$this->schedule_cron();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function register_hooks(): void {
		// OAuth callback handling.
		add_action( 'init', array( $this->oauth_controller, 'handle_oauth_callback' ) );

		// Booking events - sync to Google Calendar.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_updated', array( $this, 'on_booking_updated' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'on_booking_cancelled' ) );
		add_action( 'bkx_booking_completed', array( $this, 'on_booking_completed' ) );

		// Cron sync.
		add_action( 'bkx_google_calendar_sync', array( $this, 'run_sync' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_google_connect', array( $this, 'ajax_connect' ) );
		add_action( 'wp_ajax_bkx_google_disconnect', array( $this, 'ajax_disconnect' ) );
		add_action( 'wp_ajax_bkx_google_sync_now', array( $this, 'ajax_sync_now' ) );
		add_action( 'wp_ajax_bkx_google_test_connection', array( $this, 'ajax_test_connection' ) );

		// Staff calendar connection.
		add_action( 'wp_ajax_bkx_staff_connect_calendar', array( $this, 'ajax_staff_connect' ) );
		add_action( 'wp_ajax_bkx_staff_disconnect_calendar', array( $this, 'ajax_staff_disconnect' ) );

		// Admin hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Staff profile meta box.
		add_action( 'add_meta_boxes_bkx_seat', array( $this, 'add_staff_meta_box' ) );
		add_action( 'save_post_bkx_seat', array( $this, 'save_staff_meta' ) );

		// Customer "Add to Calendar" button.
		add_action( 'bkx_booking_confirmation', array( $this, 'render_add_to_calendar' ) );
		add_filter( 'bkx_booking_email_content', array( $this, 'add_calendar_links_to_email' ), 10, 2 );

		// REST API for calendar data.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Availability check filter.
		add_filter( 'bkx_availability_slots', array( $this, 'filter_availability' ), 10, 3 );
	}

	/**
	 * Schedule sync cron.
	 *
	 * @return void
	 */
	protected function schedule_cron(): void {
		if ( ! $this->get_setting( 'sync_enabled', true ) ) {
			return;
		}

		if ( ! wp_next_scheduled( 'bkx_google_calendar_sync' ) ) {
			wp_schedule_event( time(), 'bkx_google_sync', 'bkx_google_calendar_sync' );
		}
	}

	/**
	 * Handle booking created.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_created( int $booking_id, array $booking_data ): void {
		if ( ! $this->get_setting( 'sync_enabled', true ) ) {
			return;
		}

		$this->sync_service->create_event( $booking_id, $booking_data );
	}

	/**
	 * Handle booking updated.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_updated( int $booking_id, array $booking_data ): void {
		if ( ! $this->get_setting( 'sync_enabled', true ) ) {
			return;
		}

		$this->sync_service->update_event( $booking_id, $booking_data );
	}

	/**
	 * Handle booking cancelled.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function on_booking_cancelled( int $booking_id ): void {
		if ( ! $this->get_setting( 'sync_enabled', true ) ) {
			return;
		}

		$this->sync_service->delete_event( $booking_id );
	}

	/**
	 * Handle booking completed.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function on_booking_completed( int $booking_id ): void {
		// Update event status if needed.
		$this->sync_service->mark_event_completed( $booking_id );
	}

	/**
	 * Run sync process.
	 *
	 * @return void
	 */
	public function run_sync(): void {
		$this->sync_service->run_full_sync();
	}

	/**
	 * AJAX: Initiate Google connection.
	 *
	 * @return void
	 */
	public function ajax_connect(): void {
		check_ajax_referer( 'bkx_google_calendar', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-google-calendar' ) ) );
		}

		$auth_url = $this->oauth_controller->get_auth_url();

		if ( is_wp_error( $auth_url ) ) {
			wp_send_json_error( array( 'message' => $auth_url->get_error_message() ) );
		}

		wp_send_json_success( array( 'auth_url' => $auth_url ) );
	}

	/**
	 * AJAX: Disconnect Google account.
	 *
	 * @return void
	 */
	public function ajax_disconnect(): void {
		check_ajax_referer( 'bkx_google_calendar', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-google-calendar' ) ) );
		}

		$result = $this->oauth_controller->disconnect();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Disconnected successfully.', 'bkx-google-calendar' ) ) );
	}

	/**
	 * AJAX: Run sync now.
	 *
	 * @return void
	 */
	public function ajax_sync_now(): void {
		check_ajax_referer( 'bkx_google_calendar', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-google-calendar' ) ) );
		}

		$result = $this->sync_service->run_full_sync();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Sync completed successfully.', 'bkx-google-calendar' ),
				'stats'   => $result,
			)
		);
	}

	/**
	 * AJAX: Test connection.
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'bkx_google_calendar', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-google-calendar' ) ) );
		}

		$result = $this->google_api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Connection successful!', 'bkx-google-calendar' ),
				'email'      => $result['email'] ?? '',
				'calendars'  => $result['calendars'] ?? array(),
			)
		);
	}

	/**
	 * AJAX: Staff connect calendar.
	 *
	 * @return void
	 */
	public function ajax_staff_connect(): void {
		check_ajax_referer( 'bkx_google_calendar', 'nonce' );

		$staff_id = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0;

		if ( ! $staff_id || ! current_user_can( 'edit_post', $staff_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-google-calendar' ) ) );
		}

		$auth_url = $this->oauth_controller->get_staff_auth_url( $staff_id );

		if ( is_wp_error( $auth_url ) ) {
			wp_send_json_error( array( 'message' => $auth_url->get_error_message() ) );
		}

		wp_send_json_success( array( 'auth_url' => $auth_url ) );
	}

	/**
	 * AJAX: Staff disconnect calendar.
	 *
	 * @return void
	 */
	public function ajax_staff_disconnect(): void {
		check_ajax_referer( 'bkx_google_calendar', 'nonce' );

		$staff_id = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0;

		if ( ! $staff_id || ! current_user_can( 'edit_post', $staff_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-google-calendar' ) ) );
		}

		$result = $this->oauth_controller->disconnect_staff( $staff_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Calendar disconnected.', 'bkx-google-calendar' ) ) );
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

		// Load on settings page or staff edit page.
		if ( 'bkx_booking_page_bkx-google-calendar' !== $screen->id && 'bkx_seat' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'bkx-google-calendar-admin',
			BKX_GOOGLE_CALENDAR_URL . 'assets/css/admin.css',
			array(),
			BKX_GOOGLE_CALENDAR_VERSION
		);

		wp_enqueue_script(
			'bkx-google-calendar-admin',
			BKX_GOOGLE_CALENDAR_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_GOOGLE_CALENDAR_VERSION,
			true
		);

		wp_localize_script(
			'bkx-google-calendar-admin',
			'bkxGoogleCalendar',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bkx_google_calendar' ),
				'isConnected' => $this->google_api->is_connected(),
				'i18n'        => array(
					'connecting'    => __( 'Connecting...', 'bkx-google-calendar' ),
					'disconnecting' => __( 'Disconnecting...', 'bkx-google-calendar' ),
					'syncing'       => __( 'Syncing...', 'bkx-google-calendar' ),
					'testing'       => __( 'Testing...', 'bkx-google-calendar' ),
					'confirmDisconnect' => __( 'Are you sure you want to disconnect?', 'bkx-google-calendar' ),
				),
			)
		);
	}

	/**
	 * Add staff calendar meta box.
	 *
	 * @return void
	 */
	public function add_staff_meta_box(): void {
		if ( ! $this->get_setting( 'sync_staff_calendars', true ) ) {
			return;
		}

		add_meta_box(
			'bkx-google-calendar',
			__( 'Google Calendar', 'bkx-google-calendar' ),
			array( $this, 'render_staff_meta_box' ),
			'bkx_seat',
			'side'
		);
	}

	/**
	 * Render staff calendar meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_staff_meta_box( \WP_Post $post ): void {
		$is_connected = $this->google_api->is_staff_connected( $post->ID );
		$calendar_id  = get_post_meta( $post->ID, '_bkx_google_calendar_id', true );

		wp_nonce_field( 'bkx_staff_calendar', 'bkx_staff_calendar_nonce' );

		?>
		<div class="bkx-staff-calendar-meta">
			<?php if ( $is_connected ) : ?>
				<p class="bkx-connected">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Connected', 'bkx-google-calendar' ); ?>
				</p>
				<?php if ( $calendar_id ) : ?>
					<p class="description"><?php echo esc_html( $calendar_id ); ?></p>
				<?php endif; ?>
				<button type="button" class="button bkx-staff-disconnect-calendar" data-staff-id="<?php echo esc_attr( $post->ID ); ?>">
					<?php esc_html_e( 'Disconnect', 'bkx-google-calendar' ); ?>
				</button>
			<?php else : ?>
				<p class="bkx-not-connected">
					<?php esc_html_e( 'Not connected', 'bkx-google-calendar' ); ?>
				</p>
				<button type="button" class="button button-primary bkx-staff-connect-calendar" data-staff-id="<?php echo esc_attr( $post->ID ); ?>">
					<?php esc_html_e( 'Connect Google Calendar', 'bkx-google-calendar' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save staff calendar meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_staff_meta( int $post_id ): void {
		if ( ! isset( $_POST['bkx_staff_calendar_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_staff_calendar_nonce'] ) ), 'bkx_staff_calendar' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save any calendar-related settings if needed.
	}

	/**
	 * Render "Add to Calendar" buttons on booking confirmation.
	 *
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function render_add_to_calendar( int $booking_id ): void {
		if ( ! $this->get_setting( 'customer_add_to_gcal', true ) ) {
			return;
		}

		$gcal_url = $this->sync_service->get_google_calendar_add_url( $booking_id );
		$ical_url = $this->sync_service->get_ical_download_url( $booking_id );

		?>
		<div class="bkx-add-to-calendar">
			<h4><?php esc_html_e( 'Add to Calendar', 'bkx-google-calendar' ); ?></h4>
			<div class="bkx-calendar-buttons">
				<a href="<?php echo esc_url( $gcal_url ); ?>" target="_blank" class="bkx-gcal-button">
					<img src="<?php echo esc_url( BKX_GOOGLE_CALENDAR_URL . 'assets/images/google-calendar-icon.svg' ); ?>" alt="">
					<?php esc_html_e( 'Google Calendar', 'bkx-google-calendar' ); ?>
				</a>
				<?php if ( $this->get_setting( 'customer_ical_export', true ) ) : ?>
					<a href="<?php echo esc_url( $ical_url ); ?>" class="bkx-ical-button" download>
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php esc_html_e( 'Download .ics', 'bkx-google-calendar' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Add calendar links to email.
	 *
	 * @param string $content Email content.
	 * @param int    $booking_id Booking ID.
	 * @return string Modified content.
	 */
	public function add_calendar_links_to_email( string $content, int $booking_id ): string {
		if ( ! $this->get_setting( 'customer_add_to_gcal', true ) ) {
			return $content;
		}

		$gcal_url = $this->sync_service->get_google_calendar_add_url( $booking_id );
		$ical_url = $this->sync_service->get_ical_download_url( $booking_id );

		$calendar_section = sprintf(
			"\n\n%s\n%s: %s\n%s: %s",
			__( '--- Add to Calendar ---', 'bkx-google-calendar' ),
			__( 'Google Calendar', 'bkx-google-calendar' ),
			$gcal_url,
			__( 'Download iCal', 'bkx-google-calendar' ),
			$ical_url
		);

		return $content . $calendar_section;
	}

	/**
	 * Filter availability slots based on Google Calendar busy times.
	 *
	 * @param array $slots Available slots.
	 * @param int   $staff_id Staff ID.
	 * @param array $args Query arguments.
	 * @return array Filtered slots.
	 */
	public function filter_availability( array $slots, int $staff_id, array $args ): array {
		if ( ! $this->get_setting( 'block_busy_times', true ) ) {
			return $slots;
		}

		if ( ! $this->google_api->is_staff_connected( $staff_id ) ) {
			return $slots;
		}

		return $this->sync_service->filter_busy_slots( $slots, $staff_id, $args );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'bkx-calendar/v1',
			'/ical/(?P<booking_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->sync_service, 'serve_ical' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'booking_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			'bkx-calendar/v1',
			'/busy-times',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->sync_service, 'get_busy_times_api' ),
				'permission_callback' => array( $this, 'check_api_permissions' ),
				'args'                => array(
					'staff_id'   => array(
						'required' => true,
						'type'     => 'integer',
					),
					'start_date' => array(
						'required' => true,
						'type'     => 'string',
					),
					'end_date'   => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * Check API permissions.
	 *
	 * @return bool
	 */
	public function check_api_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get Google API service.
	 *
	 * @return GoogleApiService
	 */
	public function get_google_api(): GoogleApiService {
		return $this->google_api;
	}

	/**
	 * Get sync service.
	 *
	 * @return CalendarSyncService
	 */
	public function get_sync_service(): CalendarSyncService {
		return $this->sync_service;
	}

	/**
	 * Get OAuth controller.
	 *
	 * @return OAuthController
	 */
	public function get_oauth_controller(): OAuthController {
		return $this->oauth_controller;
	}
}
