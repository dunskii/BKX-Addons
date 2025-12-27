<?php
/**
 * Settings Page
 *
 * Admin settings page for Booking Reminders.
 *
 * @package BookingX\BookingReminders\Admin
 * @since   1.0.0
 */

namespace BookingX\BookingReminders\Admin;

use BookingX\BookingReminders\BookingRemindersAddon;

/**
 * Settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var BookingRemindersAddon
	 */
	protected BookingRemindersAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param BookingRemindersAddon $addon Addon instance.
	 */
	public function __construct( BookingRemindersAddon $addon ) {
		$this->addon = $addon;

		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_reminders', array( $this, 'render_settings' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings tab.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs['reminders'] = __( 'Reminders', 'bkx-booking-reminders' );
		return $tabs;
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_booking_reminders_settings',
			'bkx_booking_reminders_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 1.0.0
	 * @param array $input Input values.
	 * @return array Sanitized values.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Boolean fields.
		$checkboxes = array(
			'enabled',
			'email_enabled',
			'email_reminder_1_enabled',
			'email_reminder_2_enabled',
			'email_reminder_3_enabled',
			'sms_enabled',
			'sms_reminder_1_enabled',
			'sms_reminder_2_enabled',
			'followup_enabled',
			'followup_request_review',
			'include_ical',
			'debug_log',
		);

		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		// Integer fields (hours).
		$integers = array(
			'email_reminder_1_time',
			'email_reminder_2_time',
			'email_reminder_3_time',
			'sms_reminder_1_time',
			'sms_reminder_2_time',
			'followup_time',
		);

		foreach ( $integers as $key ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 24;
		}

		// Text fields.
		$sanitized['email_subject']  = isset( $input['email_subject'] )
			? sanitize_text_field( $input['email_subject'] )
			: '';
		$sanitized['email_template'] = isset( $input['email_template'] )
			? sanitize_text_field( $input['email_template'] )
			: 'default';
		$sanitized['sms_provider']   = isset( $input['sms_provider'] )
			? sanitize_text_field( $input['sms_provider'] )
			: 'twilio';

		// Twilio credentials (encrypted).
		$sanitized['twilio_account_sid']  = isset( $input['twilio_account_sid'] )
			? sanitize_text_field( $input['twilio_account_sid'] )
			: '';
		$sanitized['twilio_auth_token']   = isset( $input['twilio_auth_token'] )
			? sanitize_text_field( $input['twilio_auth_token'] )
			: '';
		$sanitized['twilio_phone_number'] = isset( $input['twilio_phone_number'] )
			? sanitize_text_field( $input['twilio_phone_number'] )
			: '';

		// Excluded statuses.
		$sanitized['exclude_statuses'] = isset( $input['exclude_statuses'] ) && is_array( $input['exclude_statuses'] )
			? array_map( 'sanitize_text_field', $input['exclude_statuses'] )
			: array( 'bkx-cancelled', 'bkx-missed' );

		return $sanitized;
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings(): void {
		$settings = $this->addon->get_all_settings();

		// Handle save.
		if ( isset( $_POST['bkx_reminders_nonce'] ) &&
			wp_verify_nonce( sanitize_key( $_POST['bkx_reminders_nonce'] ), 'bkx_save_reminders_settings' ) ) {

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'bkx-booking-reminders' ) );
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in sanitize_settings
			$input    = isset( $_POST['bkx_booking_reminders_settings'] ) ? wp_unslash( $_POST['bkx_booking_reminders_settings'] ) : array();
			$settings = $this->sanitize_settings( $input );
			update_option( 'bkx_booking_reminders_settings', $settings );

			echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__( 'Settings saved successfully.', 'bkx-booking-reminders' ) .
				'</p></div>';
		}
		?>
		<div class="bkx-reminders-settings">
			<form method="post" action="">
				<?php wp_nonce_field( 'bkx_save_reminders_settings', 'bkx_reminders_nonce' ); ?>

				<!-- General Settings -->
				<h2><?php esc_html_e( 'General Settings', 'bkx-booking-reminders' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Reminders', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[enabled]"
									   value="1"
									   <?php checked( $settings['enabled'] ?? true ); ?>>
								<?php esc_html_e( 'Enable booking reminders', 'bkx-booking-reminders' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Exclude Booking Statuses', 'bkx-booking-reminders' ); ?></th>
						<td>
							<?php
							$statuses         = array(
								'bkx-pending'   => __( 'Pending', 'bkx-booking-reminders' ),
								'bkx-ack'       => __( 'Acknowledged', 'bkx-booking-reminders' ),
								'bkx-completed' => __( 'Completed', 'bkx-booking-reminders' ),
								'bkx-cancelled' => __( 'Cancelled', 'bkx-booking-reminders' ),
								'bkx-missed'    => __( 'Missed', 'bkx-booking-reminders' ),
							);
							$excluded         = $settings['exclude_statuses'] ?? array( 'bkx-cancelled', 'bkx-missed' );
							foreach ( $statuses as $status => $label ) :
								?>
								<label style="margin-right: 15px;">
									<input type="checkbox"
										   name="bkx_booking_reminders_settings[exclude_statuses][]"
										   value="<?php echo esc_attr( $status ); ?>"
										   <?php checked( in_array( $status, $excluded, true ) ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description">
								<?php esc_html_e( 'Reminders will not be sent for bookings with these statuses.', 'bkx-booking-reminders' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<!-- Email Settings -->
				<h2><?php esc_html_e( 'Email Reminders', 'bkx-booking-reminders' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Email', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[email_enabled]"
									   value="1"
									   <?php checked( $settings['email_enabled'] ?? true ); ?>>
								<?php esc_html_e( 'Send email reminders', 'bkx-booking-reminders' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reminder 1', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[email_reminder_1_enabled]"
									   value="1"
									   <?php checked( $settings['email_reminder_1_enabled'] ?? true ); ?>>
								<?php esc_html_e( 'Enabled', 'bkx-booking-reminders' ); ?>
							</label>
							<input type="number"
								   name="bkx_booking_reminders_settings[email_reminder_1_time]"
								   value="<?php echo esc_attr( $settings['email_reminder_1_time'] ?? 24 ); ?>"
								   min="1"
								   max="168"
								   style="width: 60px;">
							<?php esc_html_e( 'hours before appointment', 'bkx-booking-reminders' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reminder 2', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[email_reminder_2_enabled]"
									   value="1"
									   <?php checked( $settings['email_reminder_2_enabled'] ?? true ); ?>>
								<?php esc_html_e( 'Enabled', 'bkx-booking-reminders' ); ?>
							</label>
							<input type="number"
								   name="bkx_booking_reminders_settings[email_reminder_2_time]"
								   value="<?php echo esc_attr( $settings['email_reminder_2_time'] ?? 2 ); ?>"
								   min="1"
								   max="168"
								   style="width: 60px;">
							<?php esc_html_e( 'hours before appointment', 'bkx-booking-reminders' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reminder 3', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[email_reminder_3_enabled]"
									   value="1"
									   <?php checked( $settings['email_reminder_3_enabled'] ?? false ); ?>>
								<?php esc_html_e( 'Enabled', 'bkx-booking-reminders' ); ?>
							</label>
							<input type="number"
								   name="bkx_booking_reminders_settings[email_reminder_3_time]"
								   value="<?php echo esc_attr( $settings['email_reminder_3_time'] ?? 48 ); ?>"
								   min="1"
								   max="168"
								   style="width: 60px;">
							<?php esc_html_e( 'hours before appointment', 'bkx-booking-reminders' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include iCal', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[include_ical]"
									   value="1"
									   <?php checked( $settings['include_ical'] ?? true ); ?>>
								<?php esc_html_e( 'Attach calendar invitation (.ics file)', 'bkx-booking-reminders' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<!-- SMS Settings -->
				<h2><?php esc_html_e( 'SMS Reminders (Twilio)', 'bkx-booking-reminders' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable SMS', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[sms_enabled]"
									   value="1"
									   <?php checked( $settings['sms_enabled'] ?? false ); ?>>
								<?php esc_html_e( 'Send SMS reminders', 'bkx-booking-reminders' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Twilio Account SID', 'bkx-booking-reminders' ); ?></th>
						<td>
							<input type="text"
								   name="bkx_booking_reminders_settings[twilio_account_sid]"
								   value="<?php echo esc_attr( $settings['twilio_account_sid'] ?? '' ); ?>"
								   class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Twilio Auth Token', 'bkx-booking-reminders' ); ?></th>
						<td>
							<input type="password"
								   name="bkx_booking_reminders_settings[twilio_auth_token]"
								   value="<?php echo esc_attr( $settings['twilio_auth_token'] ?? '' ); ?>"
								   class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Twilio Phone Number', 'bkx-booking-reminders' ); ?></th>
						<td>
							<input type="text"
								   name="bkx_booking_reminders_settings[twilio_phone_number]"
								   value="<?php echo esc_attr( $settings['twilio_phone_number'] ?? '' ); ?>"
								   class="regular-text"
								   placeholder="+15551234567">
							<p class="description">
								<?php esc_html_e( 'Your Twilio phone number in E.164 format.', 'bkx-booking-reminders' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'SMS Reminder 1', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[sms_reminder_1_enabled]"
									   value="1"
									   <?php checked( $settings['sms_reminder_1_enabled'] ?? true ); ?>>
								<?php esc_html_e( 'Enabled', 'bkx-booking-reminders' ); ?>
							</label>
							<input type="number"
								   name="bkx_booking_reminders_settings[sms_reminder_1_time]"
								   value="<?php echo esc_attr( $settings['sms_reminder_1_time'] ?? 24 ); ?>"
								   min="1"
								   max="168"
								   style="width: 60px;">
							<?php esc_html_e( 'hours before appointment', 'bkx-booking-reminders' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'SMS Reminder 2', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[sms_reminder_2_enabled]"
									   value="1"
									   <?php checked( $settings['sms_reminder_2_enabled'] ?? false ); ?>>
								<?php esc_html_e( 'Enabled', 'bkx-booking-reminders' ); ?>
							</label>
							<input type="number"
								   name="bkx_booking_reminders_settings[sms_reminder_2_time]"
								   value="<?php echo esc_attr( $settings['sms_reminder_2_time'] ?? 2 ); ?>"
								   min="1"
								   max="168"
								   style="width: 60px;">
							<?php esc_html_e( 'hours before appointment', 'bkx-booking-reminders' ); ?>
						</td>
					</tr>
				</table>

				<!-- Follow-up Settings -->
				<h2><?php esc_html_e( 'Follow-up Email', 'bkx-booking-reminders' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Follow-up', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[followup_enabled]"
									   value="1"
									   <?php checked( $settings['followup_enabled'] ?? true ); ?>>
								<?php esc_html_e( 'Send follow-up email after appointment', 'bkx-booking-reminders' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Send After', 'bkx-booking-reminders' ); ?></th>
						<td>
							<input type="number"
								   name="bkx_booking_reminders_settings[followup_time]"
								   value="<?php echo esc_attr( $settings['followup_time'] ?? 24 ); ?>"
								   min="1"
								   max="168"
								   style="width: 60px;">
							<?php esc_html_e( 'hours after appointment', 'bkx-booking-reminders' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Request Review', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[followup_request_review]"
									   value="1"
									   <?php checked( $settings['followup_request_review'] ?? true ); ?>>
								<?php esc_html_e( 'Include review request in follow-up email', 'bkx-booking-reminders' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<!-- Test & Debug -->
				<h2><?php esc_html_e( 'Testing & Debug', 'bkx-booking-reminders' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Test Email', 'bkx-booking-reminders' ); ?></th>
						<td>
							<input type="email"
								   id="bkx-test-email"
								   class="regular-text"
								   placeholder="<?php esc_attr_e( 'Enter email address', 'bkx-booking-reminders' ); ?>">
							<button type="button" class="button" id="bkx-send-test-email">
								<?php esc_html_e( 'Send Test Email', 'bkx-booking-reminders' ); ?>
							</button>
							<span id="bkx-test-email-result"></span>
						</td>
					</tr>
					<?php if ( ! empty( $settings['sms_enabled'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test SMS', 'bkx-booking-reminders' ); ?></th>
						<td>
							<input type="tel"
								   id="bkx-test-phone"
								   class="regular-text"
								   placeholder="<?php esc_attr_e( '+15551234567', 'bkx-booking-reminders' ); ?>">
							<button type="button" class="button" id="bkx-send-test-sms">
								<?php esc_html_e( 'Send Test SMS', 'bkx-booking-reminders' ); ?>
							</button>
							<span id="bkx-test-sms-result"></span>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Debug Log', 'bkx-booking-reminders' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="bkx_booking_reminders_settings[debug_log]"
									   value="1"
									   <?php checked( $settings['debug_log'] ?? false ); ?>>
								<?php esc_html_e( 'Enable debug logging', 'bkx-booking-reminders' ); ?>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: %s: log file path */
									esc_html__( 'Log file: %s', 'bkx-booking-reminders' ),
									'<code>' . esc_html( WP_CONTENT_DIR . '/bkx-reminders-debug.log' ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
