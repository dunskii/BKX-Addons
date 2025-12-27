<?php
/**
 * SMS Notifications Settings Page
 *
 * @package BookingX\SmsNotificationsPro\Admin
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro\Admin;

use BookingX\SmsNotificationsPro\SmsNotificationsProAddon;
use BookingX\SmsNotificationsPro\Services\TemplateService;
use BookingX\SmsNotificationsPro\Services\SmsService;

/**
 * Settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var SmsNotificationsProAddon
	 */
	protected SmsNotificationsProAddon $addon;

	/**
	 * Template service.
	 *
	 * @var TemplateService
	 */
	protected TemplateService $template_service;

	/**
	 * SMS service.
	 *
	 * @var SmsService
	 */
	protected SmsService $sms_service;

	/**
	 * Constructor.
	 *
	 * @param SmsNotificationsProAddon $addon Addon instance.
	 */
	public function __construct( SmsNotificationsProAddon $addon ) {
		$this->addon            = $addon;
		$this->template_service = new TemplateService( $addon );
		$this->sms_service      = new SmsService( $addon, $this->template_service );
	}

	/**
	 * Register the settings page.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add menu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'bkx-settings',
			__( 'SMS Notifications', 'bkx-sms-notifications-pro' ),
			__( 'SMS Notifications', 'bkx-sms-notifications-pro' ),
			'manage_options',
			'bkx-sms-notifications',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_sms_settings',
			'bkx_sms_notifications_pro_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Provider.
		$sanitized['provider'] = sanitize_text_field( $input['provider'] ?? 'twilio' );

		// Twilio settings.
		$sanitized['twilio_account_sid']  = sanitize_text_field( $input['twilio_account_sid'] ?? '' );
		$sanitized['twilio_auth_token']   = $this->maybe_encrypt( $input['twilio_auth_token'] ?? '' );
		$sanitized['twilio_phone_number'] = sanitize_text_field( $input['twilio_phone_number'] ?? '' );

		// Vonage settings.
		$sanitized['vonage_api_key']    = sanitize_text_field( $input['vonage_api_key'] ?? '' );
		$sanitized['vonage_api_secret'] = $this->maybe_encrypt( $input['vonage_api_secret'] ?? '' );
		$sanitized['vonage_sender_id']  = sanitize_text_field( $input['vonage_sender_id'] ?? '' );

		// MessageBird settings.
		$sanitized['messagebird_api_key']    = $this->maybe_encrypt( $input['messagebird_api_key'] ?? '' );
		$sanitized['messagebird_originator'] = sanitize_text_field( $input['messagebird_originator'] ?? '' );

		// Plivo settings.
		$sanitized['plivo_auth_id']      = sanitize_text_field( $input['plivo_auth_id'] ?? '' );
		$sanitized['plivo_auth_token']   = $this->maybe_encrypt( $input['plivo_auth_token'] ?? '' );
		$sanitized['plivo_phone_number'] = sanitize_text_field( $input['plivo_phone_number'] ?? '' );

		// General settings.
		$sanitized['admin_phone']           = sanitize_text_field( $input['admin_phone'] ?? '' );
		$sanitized['default_country_code']  = sanitize_text_field( $input['default_country_code'] ?? '+1' );
		$sanitized['max_message_length']    = absint( $input['max_message_length'] ?? 160 );
		$sanitized['log_messages']          = ! empty( $input['log_messages'] );
		$sanitized['rate_limit_enabled']    = ! empty( $input['rate_limit_enabled'] );
		$sanitized['rate_limit_per_hour']   = absint( $input['rate_limit_per_hour'] ?? 100 );

		// Notification toggles.
		$notification_types = array(
			'notify_customer_created',
			'notify_customer_confirmed',
			'notify_customer_cancelled',
			'notify_customer_reminder',
			'notify_customer_completed',
			'notify_customer_updated',
			'notify_staff_created',
			'notify_staff_cancelled',
			'notify_admin_created',
		);

		foreach ( $notification_types as $type ) {
			$sanitized[ $type ] = ! empty( $input[ $type ] );
		}

		// Opt-in settings.
		$sanitized['require_customer_optin'] = ! empty( $input['require_customer_optin'] );
		$sanitized['optin_field_label']      = sanitize_text_field( $input['optin_field_label'] ?? '' );

		return $sanitized;
	}

	/**
	 * Encrypt sensitive value.
	 *
	 * @param string $value Value to encrypt.
	 * @return string Encrypted value.
	 */
	protected function maybe_encrypt( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Skip if already appears encrypted.
		if ( 0 === strpos( $value, 'enc:' ) ) {
			return $value;
		}

		if ( class_exists( 'BookingX\\AddonSDK\\Services\\EncryptionService' ) ) {
			$encryption = new \BookingX\AddonSDK\Services\EncryptionService();
			$encrypted  = $encryption->encrypt( $value );
			if ( false !== $encrypted ) {
				return $encrypted;
			}
		}

		return $value;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'provider';
		$settings   = get_option( 'bkx_sms_notifications_pro_settings', array() );
		?>
		<div class="wrap bkx-sms-settings">
			<h1><?php esc_html_e( 'SMS Notifications Pro', 'bkx-sms-notifications-pro' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=bkx-sms-notifications&tab=provider" class="nav-tab <?php echo 'provider' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Provider', 'bkx-sms-notifications-pro' ); ?>
				</a>
				<a href="?page=bkx-sms-notifications&tab=notifications" class="nav-tab <?php echo 'notifications' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Notifications', 'bkx-sms-notifications-pro' ); ?>
				</a>
				<a href="?page=bkx-sms-notifications&tab=templates" class="nav-tab <?php echo 'templates' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Templates', 'bkx-sms-notifications-pro' ); ?>
				</a>
				<a href="?page=bkx-sms-notifications&tab=logs" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Logs', 'bkx-sms-notifications-pro' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'notifications':
						$this->render_notifications_tab( $settings );
						break;
					case 'templates':
						$this->render_templates_tab();
						break;
					case 'logs':
						$this->render_logs_tab();
						break;
					default:
						$this->render_provider_tab( $settings );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render provider tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	protected function render_provider_tab( array $settings ): void {
		$provider = $settings['provider'] ?? 'twilio';
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bkx_sms_settings' ); ?>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'SMS Provider', 'bkx-sms-notifications-pro' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Provider', 'bkx-sms-notifications-pro' ); ?></th>
						<td>
							<select name="bkx_sms_notifications_pro_settings[provider]" id="bkx-sms-provider">
								<option value="twilio" <?php selected( $provider, 'twilio' ); ?>><?php esc_html_e( 'Twilio', 'bkx-sms-notifications-pro' ); ?></option>
								<option value="vonage" <?php selected( $provider, 'vonage' ); ?>><?php esc_html_e( 'Vonage (Nexmo)', 'bkx-sms-notifications-pro' ); ?></option>
								<option value="messagebird" <?php selected( $provider, 'messagebird' ); ?>><?php esc_html_e( 'MessageBird', 'bkx-sms-notifications-pro' ); ?></option>
								<option value="plivo" <?php selected( $provider, 'plivo' ); ?>><?php esc_html_e( 'Plivo', 'bkx-sms-notifications-pro' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<!-- Twilio Settings -->
			<div class="bkx-card provider-settings" id="twilio-settings" style="<?php echo 'twilio' !== $provider ? 'display:none;' : ''; ?>">
				<h2><?php esc_html_e( 'Twilio Settings', 'bkx-sms-notifications-pro' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Account SID', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="text" name="bkx_sms_notifications_pro_settings[twilio_account_sid]" value="<?php echo esc_attr( $settings['twilio_account_sid'] ?? '' ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auth Token', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="password" name="bkx_sms_notifications_pro_settings[twilio_auth_token]" value="<?php echo esc_attr( $settings['twilio_auth_token'] ?? '' ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Phone Number', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="text" name="bkx_sms_notifications_pro_settings[twilio_phone_number]" value="<?php echo esc_attr( $settings['twilio_phone_number'] ?? '' ); ?>" class="regular-text" placeholder="+1234567890" /></td>
					</tr>
				</table>
			</div>

			<!-- Vonage Settings -->
			<div class="bkx-card provider-settings" id="vonage-settings" style="<?php echo 'vonage' !== $provider ? 'display:none;' : ''; ?>">
				<h2><?php esc_html_e( 'Vonage Settings', 'bkx-sms-notifications-pro' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'API Key', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="text" name="bkx_sms_notifications_pro_settings[vonage_api_key]" value="<?php echo esc_attr( $settings['vonage_api_key'] ?? '' ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API Secret', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="password" name="bkx_sms_notifications_pro_settings[vonage_api_secret]" value="<?php echo esc_attr( $settings['vonage_api_secret'] ?? '' ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sender ID', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="text" name="bkx_sms_notifications_pro_settings[vonage_sender_id]" value="<?php echo esc_attr( $settings['vonage_sender_id'] ?? '' ); ?>" class="regular-text" /></td>
					</tr>
				</table>
			</div>

			<!-- MessageBird Settings -->
			<div class="bkx-card provider-settings" id="messagebird-settings" style="<?php echo 'messagebird' !== $provider ? 'display:none;' : ''; ?>">
				<h2><?php esc_html_e( 'MessageBird Settings', 'bkx-sms-notifications-pro' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'API Key', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="password" name="bkx_sms_notifications_pro_settings[messagebird_api_key]" value="<?php echo esc_attr( $settings['messagebird_api_key'] ?? '' ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Originator', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="text" name="bkx_sms_notifications_pro_settings[messagebird_originator]" value="<?php echo esc_attr( $settings['messagebird_originator'] ?? '' ); ?>" class="regular-text" /></td>
					</tr>
				</table>
			</div>

			<!-- Plivo Settings -->
			<div class="bkx-card provider-settings" id="plivo-settings" style="<?php echo 'plivo' !== $provider ? 'display:none;' : ''; ?>">
				<h2><?php esc_html_e( 'Plivo Settings', 'bkx-sms-notifications-pro' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Auth ID', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="text" name="bkx_sms_notifications_pro_settings[plivo_auth_id]" value="<?php echo esc_attr( $settings['plivo_auth_id'] ?? '' ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auth Token', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="password" name="bkx_sms_notifications_pro_settings[plivo_auth_token]" value="<?php echo esc_attr( $settings['plivo_auth_token'] ?? '' ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Phone Number', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="text" name="bkx_sms_notifications_pro_settings[plivo_phone_number]" value="<?php echo esc_attr( $settings['plivo_phone_number'] ?? '' ); ?>" class="regular-text" placeholder="+1234567890" /></td>
					</tr>
				</table>
			</div>

			<!-- General Settings -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'General Settings', 'bkx-sms-notifications-pro' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin Phone', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="text" name="bkx_sms_notifications_pro_settings[admin_phone]" value="<?php echo esc_attr( $settings['admin_phone'] ?? '' ); ?>" class="regular-text" placeholder="+1234567890" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default Country Code', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="text" name="bkx_sms_notifications_pro_settings[default_country_code]" value="<?php echo esc_attr( $settings['default_country_code'] ?? '+1' ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Max Message Length', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="number" name="bkx_sms_notifications_pro_settings[max_message_length]" value="<?php echo esc_attr( $settings['max_message_length'] ?? 160 ); ?>" class="small-text" min="50" max="1600" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Log Messages', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[log_messages]" value="1" <?php checked( ! empty( $settings['log_messages'] ) ); ?> /> <?php esc_html_e( 'Keep a log of sent messages', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rate Limiting', 'bkx-sms-notifications-pro' ); ?></th>
						<td>
							<label><input type="checkbox" name="bkx_sms_notifications_pro_settings[rate_limit_enabled]" value="1" <?php checked( ! empty( $settings['rate_limit_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable rate limiting', 'bkx-sms-notifications-pro' ); ?></label>
							<br /><br />
							<input type="number" name="bkx_sms_notifications_pro_settings[rate_limit_per_hour]" value="<?php echo esc_attr( $settings['rate_limit_per_hour'] ?? 100 ); ?>" class="small-text" /> <?php esc_html_e( 'messages per phone number per hour', 'bkx-sms-notifications-pro' ); ?>
						</td>
					</tr>
				</table>
			</div>

			<!-- Test SMS -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Test SMS', 'bkx-sms-notifications-pro' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Phone Number', 'bkx-sms-notifications-pro' ); ?></th>
						<td>
							<input type="text" id="bkx-test-phone" class="regular-text" placeholder="+1234567890" />
							<button type="button" class="button" id="bkx-send-test"><?php esc_html_e( 'Send Test', 'bkx-sms-notifications-pro' ); ?></button>
							<span id="bkx-test-result"></span>
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render notifications tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	protected function render_notifications_tab( array $settings ): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bkx_sms_settings' ); ?>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Customer Notifications', 'bkx-sms-notifications-pro' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Booking Created', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[notify_customer_created]" value="1" <?php checked( ! empty( $settings['notify_customer_created'] ) ); ?> /> <?php esc_html_e( 'Send confirmation when booking is created', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Booking Confirmed', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[notify_customer_confirmed]" value="1" <?php checked( ! empty( $settings['notify_customer_confirmed'] ) ); ?> /> <?php esc_html_e( 'Send notification when booking is confirmed', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Booking Cancelled', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[notify_customer_cancelled]" value="1" <?php checked( ! empty( $settings['notify_customer_cancelled'] ) ); ?> /> <?php esc_html_e( 'Send notification when booking is cancelled', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Booking Reminder', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[notify_customer_reminder]" value="1" <?php checked( ! empty( $settings['notify_customer_reminder'] ) ); ?> /> <?php esc_html_e( 'Send reminder before appointment', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Booking Completed', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[notify_customer_completed]" value="1" <?php checked( ! empty( $settings['notify_customer_completed'] ) ); ?> /> <?php esc_html_e( 'Send thank you after completion', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Booking Updated', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[notify_customer_updated]" value="1" <?php checked( ! empty( $settings['notify_customer_updated'] ) ); ?> /> <?php esc_html_e( 'Send notification when booking is updated', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Staff Notifications', 'bkx-sms-notifications-pro' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'New Booking', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[notify_staff_created]" value="1" <?php checked( ! empty( $settings['notify_staff_created'] ) ); ?> /> <?php esc_html_e( 'Notify staff of new bookings', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Booking Cancelled', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[notify_staff_cancelled]" value="1" <?php checked( ! empty( $settings['notify_staff_cancelled'] ) ); ?> /> <?php esc_html_e( 'Notify staff of cancellations', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Admin Notifications', 'bkx-sms-notifications-pro' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'New Booking', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[notify_admin_created]" value="1" <?php checked( ! empty( $settings['notify_admin_created'] ) ); ?> /> <?php esc_html_e( 'Notify admin of new bookings', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Customer Opt-in', 'bkx-sms-notifications-pro' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Require Opt-in', 'bkx-sms-notifications-pro' ); ?></th>
						<td><label><input type="checkbox" name="bkx_sms_notifications_pro_settings[require_customer_optin]" value="1" <?php checked( ! empty( $settings['require_customer_optin'] ) ); ?> /> <?php esc_html_e( 'Customers must opt-in to receive SMS', 'bkx-sms-notifications-pro' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Opt-in Label', 'bkx-sms-notifications-pro' ); ?></th>
						<td><input type="text" name="bkx_sms_notifications_pro_settings[optin_field_label]" value="<?php echo esc_attr( $settings['optin_field_label'] ?? __( 'I agree to receive SMS notifications', 'bkx-sms-notifications-pro' ) ); ?>" class="regular-text" /></td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render templates tab.
	 *
	 * @return void
	 */
	protected function render_templates_tab(): void {
		$templates    = $this->template_service->get_templates();
		$placeholders = $this->template_service->get_available_placeholders();
		?>
		<div class="bkx-card">
			<h2><?php esc_html_e( 'SMS Templates', 'bkx-sms-notifications-pro' ); ?></h2>

			<div class="bkx-placeholders-reference">
				<h4><?php esc_html_e( 'Available Placeholders', 'bkx-sms-notifications-pro' ); ?></h4>
				<p>
					<?php
					foreach ( $placeholders as $key => $label ) {
						echo '<code>{' . esc_html( $key ) . '}</code> ';
					}
					?>
				</p>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'bkx-sms-notifications-pro' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-sms-notifications-pro' ); ?></th>
						<th><?php esc_html_e( 'Recipient', 'bkx-sms-notifications-pro' ); ?></th>
						<th><?php esc_html_e( 'Content', 'bkx-sms-notifications-pro' ); ?></th>
						<th><?php esc_html_e( 'Active', 'bkx-sms-notifications-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-sms-notifications-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $templates as $template ) : ?>
						<tr data-id="<?php echo esc_attr( $template->id ); ?>">
							<td><?php echo esc_html( $template->name ); ?></td>
							<td><code><?php echo esc_html( $template->template_key ); ?></code></td>
							<td><?php echo esc_html( ucfirst( $template->recipient_type ) ); ?></td>
							<td><code><?php echo esc_html( substr( $template->content, 0, 50 ) . '...' ); ?></code></td>
							<td><?php echo $template->is_active ? '✓' : '✗'; ?></td>
							<td>
								<button type="button" class="button button-small bkx-edit-template" data-id="<?php echo esc_attr( $template->id ); ?>"><?php esc_html_e( 'Edit', 'bkx-sms-notifications-pro' ); ?></button>
								<button type="button" class="button button-small bkx-preview-template" data-content="<?php echo esc_attr( $template->content ); ?>"><?php esc_html_e( 'Preview', 'bkx-sms-notifications-pro' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Template Edit Modal -->
		<div id="bkx-template-modal" class="bkx-modal" style="display:none;">
			<div class="bkx-modal-content">
				<span class="bkx-modal-close">&times;</span>
				<h2><?php esc_html_e( 'Edit Template', 'bkx-sms-notifications-pro' ); ?></h2>
				<form id="bkx-template-form">
					<input type="hidden" id="template-id" name="id" />
					<p>
						<label for="template-name"><?php esc_html_e( 'Name', 'bkx-sms-notifications-pro' ); ?></label>
						<input type="text" id="template-name" name="name" class="widefat" />
					</p>
					<p>
						<label for="template-content"><?php esc_html_e( 'Content', 'bkx-sms-notifications-pro' ); ?></label>
						<textarea id="template-content" name="content" class="widefat" rows="4"></textarea>
						<span class="description" id="char-count"></span>
					</p>
					<p>
						<label>
							<input type="checkbox" id="template-active" name="is_active" value="1" />
							<?php esc_html_e( 'Active', 'bkx-sms-notifications-pro' ); ?>
						</label>
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Template', 'bkx-sms-notifications-pro' ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render logs tab.
	 *
	 * @return void
	 */
	protected function render_logs_tab(): void {
		global $wpdb;

		$page     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;

		$log_table = $wpdb->prefix . 'bkx_sms_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $log_table ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY sent_at DESC LIMIT %d OFFSET %d',
				$log_table,
				$per_page,
				$offset
			)
		);

		$total_pages = ceil( $total / $per_page );
		$stats       = $this->sms_service->get_stats();
		?>
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Statistics', 'bkx-sms-notifications-pro' ); ?></h2>
			<div class="bkx-stats-grid">
				<div class="bkx-stat">
					<span class="bkx-stat-value"><?php echo esc_html( $stats['total'] ); ?></span>
					<span class="bkx-stat-label"><?php esc_html_e( 'Total Messages', 'bkx-sms-notifications-pro' ); ?></span>
				</div>
				<div class="bkx-stat">
					<span class="bkx-stat-value"><?php echo esc_html( $stats['sent'] ); ?></span>
					<span class="bkx-stat-label"><?php esc_html_e( 'Sent', 'bkx-sms-notifications-pro' ); ?></span>
				</div>
				<div class="bkx-stat">
					<span class="bkx-stat-value"><?php echo esc_html( $stats['failed'] ); ?></span>
					<span class="bkx-stat-label"><?php esc_html_e( 'Failed', 'bkx-sms-notifications-pro' ); ?></span>
				</div>
				<div class="bkx-stat">
					<span class="bkx-stat-value"><?php echo esc_html( $stats['success_rate'] ); ?>%</span>
					<span class="bkx-stat-label"><?php esc_html_e( 'Success Rate', 'bkx-sms-notifications-pro' ); ?></span>
				</div>
				<div class="bkx-stat">
					<span class="bkx-stat-value"><?php echo esc_html( $stats['today'] ); ?></span>
					<span class="bkx-stat-label"><?php esc_html_e( 'Today', 'bkx-sms-notifications-pro' ); ?></span>
				</div>
			</div>
		</div>

		<div class="bkx-card">
			<h2><?php esc_html_e( 'Message Log', 'bkx-sms-notifications-pro' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'bkx-sms-notifications-pro' ); ?></th>
						<th><?php esc_html_e( 'Recipient', 'bkx-sms-notifications-pro' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-sms-notifications-pro' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'bkx-sms-notifications-pro' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-sms-notifications-pro' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-sms-notifications-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No messages logged yet.', 'bkx-sms-notifications-pro' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->sent_at ) ) ); ?></td>
								<td><?php echo esc_html( $log->recipient ); ?></td>
								<td><?php echo esc_html( $log->message_type ); ?></td>
								<td><?php echo esc_html( ucfirst( $log->provider ) ); ?></td>
								<td>
									<span class="bkx-status bkx-status-<?php echo esc_attr( $log->status ); ?>">
										<?php echo esc_html( ucfirst( $log->status ) ); ?>
									</span>
									<?php if ( ! empty( $log->error_message ) ) : ?>
										<span class="bkx-error-tooltip" title="<?php echo esc_attr( $log->error_message ); ?>">ℹ</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'failed' === $log->status ) : ?>
										<button type="button" class="button button-small bkx-resend" data-id="<?php echo esc_attr( $log->id ); ?>"><?php esc_html_e( 'Resend', 'bkx-sms-notifications-pro' ); ?></button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $page,
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
