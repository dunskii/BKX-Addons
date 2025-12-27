<?php
/**
 * Outlook Calendar Addon Main Class
 *
 * @package BookingX\OutlookCalendar
 * @since   1.0.0
 */

namespace BookingX\OutlookCalendar;

use BookingX\AddonSDK\Abstracts\AbstractCalendarProvider;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasCron;
use BookingX\AddonSDK\Services\EncryptionService;
use BookingX\OutlookCalendar\Services\MicrosoftGraphAPI;
use BookingX\OutlookCalendar\Services\CalendarService;
use BookingX\OutlookCalendar\Services\SyncService;
use BookingX\OutlookCalendar\Admin\SettingsPage;

/**
 * Class OutlookCalendarAddon
 *
 * @since 1.0.0
 */
class OutlookCalendarAddon extends AbstractCalendarProvider {

	use HasSettings;
	use HasLicense;
	use HasCron;

	/**
	 * Addon name.
	 *
	 * @var string
	 */
	protected string $addon_name = 'Outlook 365 Calendar';

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $addon_slug = 'bkx-outlook-calendar';

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected string $version = BKX_OUTLOOK_VERSION;

	/**
	 * Microsoft Graph API instance.
	 *
	 * @var MicrosoftGraphAPI
	 */
	private MicrosoftGraphAPI $api;

	/**
	 * Calendar service instance.
	 *
	 * @var CalendarService
	 */
	private CalendarService $calendar_service;

	/**
	 * Sync service instance.
	 *
	 * @var SyncService
	 */
	private SyncService $sync_service;

	/**
	 * Encryption service.
	 *
	 * @var EncryptionService
	 */
	private EncryptionService $encryption;

	/**
	 * Settings page instance.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Get calendar provider name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_provider_name(): string {
		return 'Microsoft Outlook 365';
	}

	/**
	 * Check if calendar is connected.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_connected(): bool {
		$access_token = $this->get_setting( 'access_token', '' );
		return ! empty( $access_token );
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_auth_url(): string {
		$client_id    = $this->get_setting( 'client_id', '' );
		$redirect_uri = admin_url( 'admin.php?page=bkx-outlook&action=oauth_callback' );
		$scope        = 'offline_access Calendars.ReadWrite User.Read';
		$state        = wp_create_nonce( 'bkx_outlook_oauth' );

		return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query(
			array(
				'client_id'     => $client_id,
				'response_type' => 'code',
				'redirect_uri'  => $redirect_uri,
				'scope'         => $scope,
				'state'         => $state,
				'response_mode' => 'query',
			)
		);
	}

	/**
	 * Handle OAuth callback.
	 *
	 * @since 1.0.0
	 * @param string $code Authorization code.
	 * @return bool|\WP_Error
	 */
	public function handle_oauth_callback( string $code ) {
		$client_id     = $this->get_setting( 'client_id', '' );
		$client_secret = $this->encryption->decrypt( $this->get_setting( 'client_secret', '' ) );
		$redirect_uri  = admin_url( 'admin.php?page=bkx-outlook&action=oauth_callback' );

		$response = wp_remote_post(
			'https://login.microsoftonline.com/common/oauth2/v2.0/token',
			array(
				'body' => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
					'scope'         => 'offline_access Calendars.ReadWrite User.Read',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error( 'oauth_error', $body['error_description'] ?? $body['error'] );
		}

		// Store tokens.
		$this->update_setting( 'access_token', $this->encryption->encrypt( $body['access_token'] ) );
		$this->update_setting( 'refresh_token', $this->encryption->encrypt( $body['refresh_token'] ) );
		$this->update_setting( 'token_expires', time() + $body['expires_in'] );

		// Get user info.
		$this->api = new MicrosoftGraphAPI( $this );
		$user_info = $this->api->get_user_info();

		if ( ! is_wp_error( $user_info ) ) {
			$this->update_setting( 'user_email', $user_info['mail'] ?? $user_info['userPrincipalName'] ?? '' );
			$this->update_setting( 'user_name', $user_info['displayName'] ?? '' );
		}

		$this->log( 'Connected to Microsoft Outlook 365' );

		return true;
	}

	/**
	 * Disconnect from calendar.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function disconnect(): bool {
		$this->update_setting( 'access_token', '' );
		$this->update_setting( 'refresh_token', '' );
		$this->update_setting( 'token_expires', 0 );
		$this->update_setting( 'user_email', '' );
		$this->update_setting( 'user_name', '' );
		$this->update_setting( 'selected_calendar', '' );

		$this->log( 'Disconnected from Microsoft Outlook 365' );

		return true;
	}

	/**
	 * Create calendar event from booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return string|\WP_Error Event ID or error.
	 */
	public function create_event( int $booking_id, array $booking_data ) {
		if ( ! $this->is_connected() ) {
			return new \WP_Error( 'not_connected', __( 'Not connected to Outlook', 'bkx-outlook-calendar' ) );
		}

		return $this->calendar_service->create_event( $booking_id, $booking_data );
	}

	/**
	 * Update calendar event.
	 *
	 * @since 1.0.0
	 * @param string $event_id     Event ID.
	 * @param int    $booking_id   Booking ID.
	 * @param array  $booking_data Booking data.
	 * @return bool|\WP_Error
	 */
	public function update_event( string $event_id, int $booking_id, array $booking_data ) {
		if ( ! $this->is_connected() ) {
			return new \WP_Error( 'not_connected', __( 'Not connected to Outlook', 'bkx-outlook-calendar' ) );
		}

		return $this->calendar_service->update_event( $event_id, $booking_id, $booking_data );
	}

	/**
	 * Delete calendar event.
	 *
	 * @since 1.0.0
	 * @param string $event_id Event ID.
	 * @return bool|\WP_Error
	 */
	public function delete_event( string $event_id ) {
		if ( ! $this->is_connected() ) {
			return new \WP_Error( 'not_connected', __( 'Not connected to Outlook', 'bkx-outlook-calendar' ) );
		}

		return $this->calendar_service->delete_event( $event_id );
	}

	/**
	 * Get busy times for availability checking.
	 *
	 * @since 1.0.0
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @param int    $resource_id Resource/seat ID.
	 * @return array Array of busy time slots.
	 */
	public function get_busy_times( string $start_date, string $end_date, int $resource_id = 0 ): array {
		if ( ! $this->is_connected() ) {
			return array();
		}

		return $this->calendar_service->get_busy_times( $start_date, $end_date, $resource_id );
	}

	/**
	 * Boot the addon after initialization.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function boot(): void {
		// Initialize encryption.
		$this->encryption = new EncryptionService();

		// Initialize API if connected.
		if ( $this->is_connected() ) {
			$this->api              = new MicrosoftGraphAPI( $this );
			$this->calendar_service = new CalendarService( $this->api, $this );
			$this->sync_service     = new SyncService( $this->calendar_service, $this );
		}

		// Initialize admin.
		if ( is_admin() ) {
			$this->settings_page = new SettingsPage( $this );
		}

		// Register hooks.
		$this->register_hooks();

		// Schedule sync.
		$this->schedule_sync();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks(): void {
		if ( ! $this->is_connected() ) {
			return;
		}

		// Sync booking to calendar.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_updated', array( $this, 'on_booking_updated' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'on_booking_cancelled' ), 10, 2 );

		// Check availability.
		add_filter( 'bkx_availability_busy_times', array( $this, 'add_outlook_busy_times' ), 10, 3 );
	}

	/**
	 * Schedule sync cron.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function schedule_sync(): void {
		if ( ! $this->is_connected() || ! $this->get_setting( 'enable_sync', true ) ) {
			return;
		}

		$this->schedule_recurring_task(
			'bkx_outlook_sync',
			'hourly',
			array( $this, 'run_sync' )
		);
	}

	/**
	 * Run sync task.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run_sync(): void {
		if ( ! $this->is_connected() ) {
			return;
		}

		$this->sync_service->sync_from_outlook();
		$this->log( 'Outlook sync completed' );
	}

	/**
	 * Handle booking created.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_created( int $booking_id, array $booking_data ): void {
		if ( ! $this->get_setting( 'sync_bookings', true ) ) {
			return;
		}

		$event_id = $this->create_event( $booking_id, $booking_data );

		if ( ! is_wp_error( $event_id ) ) {
			update_post_meta( $booking_id, '_outlook_event_id', $event_id );
			$this->log( sprintf( 'Created Outlook event for booking %d', $booking_id ) );
		}
	}

	/**
	 * Handle booking updated.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_updated( int $booking_id, array $booking_data ): void {
		$event_id = get_post_meta( $booking_id, '_outlook_event_id', true );

		if ( empty( $event_id ) ) {
			$this->on_booking_created( $booking_id, $booking_data );
			return;
		}

		$result = $this->update_event( $event_id, $booking_id, $booking_data );

		if ( ! is_wp_error( $result ) ) {
			$this->log( sprintf( 'Updated Outlook event for booking %d', $booking_id ) );
		}
	}

	/**
	 * Handle booking cancelled.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_cancelled( int $booking_id, array $booking_data ): void {
		$event_id = get_post_meta( $booking_id, '_outlook_event_id', true );

		if ( empty( $event_id ) ) {
			return;
		}

		$delete_on_cancel = $this->get_setting( 'delete_on_cancel', true );

		if ( $delete_on_cancel ) {
			$result = $this->delete_event( $event_id );

			if ( ! is_wp_error( $result ) ) {
				delete_post_meta( $booking_id, '_outlook_event_id' );
				$this->log( sprintf( 'Deleted Outlook event for cancelled booking %d', $booking_id ) );
			}
		}
	}

	/**
	 * Add Outlook busy times to availability check.
	 *
	 * @since 1.0.0
	 * @param array  $busy_times  Existing busy times.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return array
	 */
	public function add_outlook_busy_times( array $busy_times, string $start_date, string $end_date ): array {
		if ( ! $this->get_setting( 'check_availability', true ) ) {
			return $busy_times;
		}

		$outlook_busy = $this->get_busy_times( $start_date, $end_date );

		return array_merge( $busy_times, $outlook_busy );
	}

	/**
	 * Get the API instance.
	 *
	 * @since 1.0.0
	 * @return MicrosoftGraphAPI|null
	 */
	public function get_api(): ?MicrosoftGraphAPI {
		return $this->api ?? null;
	}

	/**
	 * Get the encryption service.
	 *
	 * @since 1.0.0
	 * @return EncryptionService
	 */
	public function get_encryption(): EncryptionService {
		return $this->encryption;
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'client_id'          => '',
			'client_secret'      => '',
			'access_token'       => '',
			'refresh_token'      => '',
			'token_expires'      => 0,
			'user_email'         => '',
			'user_name'          => '',
			'selected_calendar'  => '',
			'sync_bookings'      => true,
			'check_availability' => true,
			'delete_on_cancel'   => true,
			'enable_sync'        => true,
			'event_title'        => '{service} - {customer}',
			'event_description'  => "Booking #{booking_id}\nService: {service}\nCustomer: {customer}\nPhone: {phone}",
		);
	}
}
