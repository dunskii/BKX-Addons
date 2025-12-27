<?php
/**
 * ActiveCampaign Addon Main Class
 *
 * @package BookingX\ActiveCampaign
 * @since   1.0.0
 */

namespace BookingX\ActiveCampaign;

use BookingX\AddonSDK\Abstracts\AbstractIntegration;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasCron;
use BookingX\AddonSDK\Services\EncryptionService;
use BookingX\ActiveCampaign\Services\ActiveCampaignAPI;
use BookingX\ActiveCampaign\Services\ContactService;
use BookingX\ActiveCampaign\Services\DealService;
use BookingX\ActiveCampaign\Admin\SettingsPage;

/**
 * Class ActiveCampaignAddon
 *
 * @since 1.0.0
 */
class ActiveCampaignAddon extends AbstractIntegration {

	use HasSettings;
	use HasLicense;
	use HasCron;

	/**
	 * Addon name.
	 *
	 * @var string
	 */
	protected string $addon_name = 'ActiveCampaign';

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $addon_slug = 'bkx-activecampaign';

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected string $version = BKX_ACTIVECAMPAIGN_VERSION;

	/**
	 * API instance.
	 *
	 * @var ActiveCampaignAPI
	 */
	private ActiveCampaignAPI $api;

	/**
	 * Contact service instance.
	 *
	 * @var ContactService
	 */
	private ContactService $contact_service;

	/**
	 * Deal service instance.
	 *
	 * @var DealService
	 */
	private DealService $deal_service;

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
	 * Get integration type.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_integration_type(): string {
		return 'crm';
	}

	/**
	 * Get integration name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_integration_name(): string {
		return 'ActiveCampaign';
	}

	/**
	 * Check if integration is connected.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_connected(): bool {
		$api_url = $this->get_setting( 'api_url', '' );
		$api_key = $this->get_setting( 'api_key', '' );

		return ! empty( $api_url ) && ! empty( $api_key );
	}

	/**
	 * Connect to integration.
	 *
	 * @since 1.0.0
	 * @param array $credentials Credentials array.
	 * @return bool|\WP_Error
	 */
	public function connect( array $credentials ) {
		if ( empty( $credentials['api_url'] ) || empty( $credentials['api_key'] ) ) {
			return new \WP_Error( 'missing_credentials', __( 'API URL and API Key are required.', 'bkx-activecampaign' ) );
		}

		// Store credentials.
		$this->update_setting( 'api_url', sanitize_url( $credentials['api_url'] ) );
		$this->update_setting( 'api_key', $this->encryption->encrypt( $credentials['api_key'] ) );

		// Test connection.
		$this->api = new ActiveCampaignAPI( $this );
		$result    = $this->api->test_connection();

		if ( is_wp_error( $result ) ) {
			$this->update_setting( 'api_key', '' );
			return $result;
		}

		$this->update_setting( 'connected', true );
		$this->update_setting( 'account_info', $result );

		return true;
	}

	/**
	 * Disconnect from integration.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function disconnect(): bool {
		$this->update_setting( 'api_url', '' );
		$this->update_setting( 'api_key', '' );
		$this->update_setting( 'connected', false );
		$this->update_setting( 'account_info', array() );

		return true;
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
			$this->api             = new ActiveCampaignAPI( $this );
			$this->contact_service = new ContactService( $this->api, $this );
			$this->deal_service    = new DealService( $this->api, $this );
		}

		// Initialize admin.
		if ( is_admin() ) {
			$this->settings_page = new SettingsPage( $this );
		}

		// Register hooks.
		$this->register_hooks();

		// Schedule cron jobs.
		$this->schedule_sync();
	}

	/**
	 * Register hooks for BookingX events.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks(): void {
		if ( ! $this->is_connected() ) {
			return;
		}

		// Booking created - sync contact and optionally create deal.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );

		// Booking completed.
		add_action( 'bkx_booking_completed', array( $this, 'on_booking_completed' ), 10, 2 );

		// Booking cancelled.
		add_action( 'bkx_booking_cancelled', array( $this, 'on_booking_cancelled' ), 10, 2 );

		// User registered.
		add_action( 'user_register', array( $this, 'on_user_register' ), 10, 1 );
	}

	/**
	 * Schedule sync cron job.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function schedule_sync(): void {
		if ( ! $this->is_connected() || ! $this->get_setting( 'enable_sync', false ) ) {
			return;
		}

		$this->schedule_recurring_task(
			'bkx_activecampaign_sync',
			'daily',
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

		$this->contact_service->sync_all_contacts();
		$this->log( 'ActiveCampaign sync completed' );
	}

	/**
	 * Handle booking created event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_created( int $booking_id, array $booking_data ): void {
		if ( ! $this->is_connected() ) {
			return;
		}

		$email = get_post_meta( $booking_id, 'customer_email', true );
		$name  = get_post_meta( $booking_id, 'customer_name', true );
		$phone = get_post_meta( $booking_id, 'customer_phone', true );

		if ( empty( $email ) ) {
			return;
		}

		// Create or update contact.
		$contact_id = $this->contact_service->sync_contact(
			$email,
			array(
				'firstName' => $this->get_first_name( $name ),
				'lastName'  => $this->get_last_name( $name ),
				'phone'     => $phone,
			)
		);

		if ( is_wp_error( $contact_id ) ) {
			$this->log( sprintf( 'Failed to sync contact: %s', $contact_id->get_error_message() ), 'error' );
			return;
		}

		// Add tags for booking.
		if ( $this->get_setting( 'tag_on_booking', true ) ) {
			$tag = $this->get_setting( 'booking_tag', 'BookingX Customer' );
			$this->contact_service->add_tag( $contact_id, $tag );
		}

		// Add to list.
		$list_id = $this->get_setting( 'default_list_id', '' );
		if ( ! empty( $list_id ) ) {
			$this->contact_service->add_to_list( $contact_id, $list_id );
		}

		// Create deal if enabled.
		if ( $this->get_setting( 'create_deals', false ) ) {
			$this->deal_service->create_deal_from_booking( $booking_id, $contact_id );
		}

		// Trigger automation.
		$automation_id = $this->get_setting( 'booking_automation_id', '' );
		if ( ! empty( $automation_id ) ) {
			$this->contact_service->add_to_automation( $contact_id, $automation_id );
		}

		// Store ActiveCampaign contact ID.
		update_post_meta( $booking_id, '_activecampaign_contact_id', $contact_id );

		$this->log( sprintf( 'Synced booking %d to ActiveCampaign contact %d', $booking_id, $contact_id ) );
	}

	/**
	 * Handle booking completed event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_completed( int $booking_id, array $booking_data ): void {
		if ( ! $this->is_connected() ) {
			return;
		}

		$contact_id = get_post_meta( $booking_id, '_activecampaign_contact_id', true );

		if ( empty( $contact_id ) ) {
			return;
		}

		// Update deal status.
		if ( $this->get_setting( 'create_deals', false ) ) {
			$this->deal_service->update_deal_stage( $booking_id, 'won' );
		}

		// Add completed tag.
		$completed_tag = $this->get_setting( 'completed_tag', '' );
		if ( ! empty( $completed_tag ) ) {
			$this->contact_service->add_tag( $contact_id, $completed_tag );
		}

		// Trigger completed automation.
		$automation_id = $this->get_setting( 'completed_automation_id', '' );
		if ( ! empty( $automation_id ) ) {
			$this->contact_service->add_to_automation( $contact_id, $automation_id );
		}
	}

	/**
	 * Handle booking cancelled event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_cancelled( int $booking_id, array $booking_data ): void {
		if ( ! $this->is_connected() ) {
			return;
		}

		$contact_id = get_post_meta( $booking_id, '_activecampaign_contact_id', true );

		if ( empty( $contact_id ) ) {
			return;
		}

		// Update deal status.
		if ( $this->get_setting( 'create_deals', false ) ) {
			$this->deal_service->update_deal_stage( $booking_id, 'lost' );
		}

		// Add cancelled tag.
		$cancelled_tag = $this->get_setting( 'cancelled_tag', '' );
		if ( ! empty( $cancelled_tag ) ) {
			$this->contact_service->add_tag( $contact_id, $cancelled_tag );
		}
	}

	/**
	 * Handle user registration.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_user_register( int $user_id ): void {
		if ( ! $this->is_connected() || ! $this->get_setting( 'sync_users', false ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$contact_id = $this->contact_service->sync_contact(
			$user->user_email,
			array(
				'firstName' => $user->first_name,
				'lastName'  => $user->last_name,
			)
		);

		if ( ! is_wp_error( $contact_id ) ) {
			update_user_meta( $user_id, '_activecampaign_contact_id', $contact_id );
		}
	}

	/**
	 * Get first name from full name.
	 *
	 * @since 1.0.0
	 * @param string $full_name Full name.
	 * @return string
	 */
	private function get_first_name( string $full_name ): string {
		$parts = explode( ' ', trim( $full_name ), 2 );
		return $parts[0] ?? '';
	}

	/**
	 * Get last name from full name.
	 *
	 * @since 1.0.0
	 * @param string $full_name Full name.
	 * @return string
	 */
	private function get_last_name( string $full_name ): string {
		$parts = explode( ' ', trim( $full_name ), 2 );
		return $parts[1] ?? '';
	}

	/**
	 * Get the API instance.
	 *
	 * @since 1.0.0
	 * @return ActiveCampaignAPI|null
	 */
	public function get_api(): ?ActiveCampaignAPI {
		return $this->api ?? null;
	}

	/**
	 * Get the contact service.
	 *
	 * @since 1.0.0
	 * @return ContactService|null
	 */
	public function get_contact_service(): ?ContactService {
		return $this->contact_service ?? null;
	}

	/**
	 * Get the deal service.
	 *
	 * @since 1.0.0
	 * @return DealService|null
	 */
	public function get_deal_service(): ?DealService {
		return $this->deal_service ?? null;
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
			'api_url'                 => '',
			'api_key'                 => '',
			'connected'               => false,
			'account_info'            => array(),
			'default_list_id'         => '',
			'tag_on_booking'          => true,
			'booking_tag'             => 'BookingX Customer',
			'completed_tag'           => 'Booking Completed',
			'cancelled_tag'           => 'Booking Cancelled',
			'create_deals'            => false,
			'deal_pipeline_id'        => '',
			'deal_stage_id'           => '',
			'booking_automation_id'   => '',
			'completed_automation_id' => '',
			'enable_sync'             => false,
			'sync_users'              => false,
		);
	}
}
