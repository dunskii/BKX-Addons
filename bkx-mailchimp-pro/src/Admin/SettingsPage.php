<?php
/**
 * Mailchimp Pro Settings Page
 *
 * @package BookingX\MailchimpPro\Admin
 * @since   1.0.0
 */

namespace BookingX\MailchimpPro\Admin;

/**
 * Settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\MailchimpPro\MailchimpProAddon
	 */
	protected $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\MailchimpPro\MailchimpProAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;

		add_action( 'bkx_settings_mailchimp_pro_tab', [ $this, 'render_settings_tab' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_mailchimp_pro_settings',
			'bkx_mailchimp_pro_settings',
			[ $this, 'sanitize_settings' ]
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 1.0.0
	 * @param array $input Settings input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ): array {
		$sanitized = [];

		// API Key (encrypt)
		if ( ! empty( $input['api_key'] ) ) {
			$encryption = new \BookingX\AddonSDK\Services\EncryptionService();
			$sanitized['api_key'] = $encryption->encrypt( $input['api_key'] );
		}

		// Boolean fields
		$sanitized['sync_enabled'] = ! empty( $input['sync_enabled'] );
		$sanitized['double_optin'] = ! empty( $input['double_optin'] );

		// Text fields
		$sanitized['default_list_id']  = sanitize_text_field( $input['default_list_id'] ?? '' );
		$sanitized['tag_on_booking']   = sanitize_text_field( $input['tag_on_booking'] ?? '' );
		$sanitized['tag_on_completed'] = sanitize_text_field( $input['tag_on_completed'] ?? '' );
		$sanitized['tag_on_cancelled'] = sanitize_text_field( $input['tag_on_cancelled'] ?? '' );
		$sanitized['sync_frequency']   = sanitize_text_field( $input['sync_frequency'] ?? 'realtime' );

		// Merge fields
		$sanitized['merge_fields'] = [
			'BOOKINGS' => ! empty( $input['merge_fields']['BOOKINGS'] ),
			'LASTBOOK' => ! empty( $input['merge_fields']['LASTBOOK'] ),
			'TOTSPENT' => ! empty( $input['merge_fields']['TOTSPENT'] ),
		];

		return $sanitized;
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_tab(): void {
		$settings = $this->addon->get_all_settings();
		?>
		<div class="bkx-mailchimp-pro-settings">
			<h2><?php esc_html_e( 'Mailchimp Pro Settings', 'bkx-mailchimp-pro' ); ?></h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'bkx_mailchimp_pro_settings' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="api_key"><?php esc_html_e( 'API Key', 'bkx-mailchimp-pro' ); ?></label>
						</th>
						<td>
							<input type="password" id="api_key" name="bkx_mailchimp_pro_settings[api_key]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Enter your Mailchimp API key', 'bkx-mailchimp-pro' ); ?>">
							<p class="description">
								<?php
								printf(
									/* translators: %s: Mailchimp API keys URL */
									esc_html__( 'Get your API key from %s', 'bkx-mailchimp-pro' ),
									'<a href="https://admin.mailchimp.com/account/api/" target="_blank">Mailchimp</a>'
								);
								?>
							</p>
							<button type="button" id="test-connection" class="button"><?php esc_html_e( 'Test Connection', 'bkx-mailchimp-pro' ); ?></button>
							<span id="connection-status"></span>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="sync_enabled"><?php esc_html_e( 'Enable Sync', 'bkx-mailchimp-pro' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="sync_enabled" name="bkx_mailchimp_pro_settings[sync_enabled]" value="1" <?php checked( $settings['sync_enabled'] ?? false ); ?>>
								<?php esc_html_e( 'Automatically sync bookings to Mailchimp', 'bkx-mailchimp-pro' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="default_list_id"><?php esc_html_e( 'Default List', 'bkx-mailchimp-pro' ); ?></label>
						</th>
						<td>
							<select id="default_list_id" name="bkx_mailchimp_pro_settings[default_list_id]" class="regular-text">
								<option value=""><?php esc_html_e( 'Select a list...', 'bkx-mailchimp-pro' ); ?></option>
							</select>
							<button type="button" id="refresh-lists" class="button"><?php esc_html_e( 'Refresh Lists', 'bkx-mailchimp-pro' ); ?></button>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="double_optin"><?php esc_html_e( 'Double Opt-in', 'bkx-mailchimp-pro' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="double_optin" name="bkx_mailchimp_pro_settings[double_optin]" value="1" <?php checked( $settings['double_optin'] ?? true ); ?>>
								<?php esc_html_e( 'Require subscribers to confirm their email address', 'bkx-mailchimp-pro' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Tags', 'bkx-mailchimp-pro' ); ?>
						</th>
						<td>
							<p>
								<label for="tag_on_booking"><?php esc_html_e( 'On Booking Created:', 'bkx-mailchimp-pro' ); ?></label><br>
								<input type="text" id="tag_on_booking" name="bkx_mailchimp_pro_settings[tag_on_booking]" value="<?php echo esc_attr( $settings['tag_on_booking'] ?? 'booking-created' ); ?>" class="regular-text">
							</p>
							<p>
								<label for="tag_on_completed"><?php esc_html_e( 'On Booking Completed:', 'bkx-mailchimp-pro' ); ?></label><br>
								<input type="text" id="tag_on_completed" name="bkx_mailchimp_pro_settings[tag_on_completed]" value="<?php echo esc_attr( $settings['tag_on_completed'] ?? 'booking-completed' ); ?>" class="regular-text">
							</p>
							<p>
								<label for="tag_on_cancelled"><?php esc_html_e( 'On Booking Cancelled:', 'bkx-mailchimp-pro' ); ?></label><br>
								<input type="text" id="tag_on_cancelled" name="bkx_mailchimp_pro_settings[tag_on_cancelled]" value="<?php echo esc_attr( $settings['tag_on_cancelled'] ?? 'booking-cancelled' ); ?>" class="regular-text">
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Merge Fields', 'bkx-mailchimp-pro' ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" name="bkx_mailchimp_pro_settings[merge_fields][BOOKINGS]" value="1" <?php checked( $settings['merge_fields']['BOOKINGS'] ?? true ); ?>>
								<?php esc_html_e( 'Total Bookings Count', 'bkx-mailchimp-pro' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="bkx_mailchimp_pro_settings[merge_fields][LASTBOOK]" value="1" <?php checked( $settings['merge_fields']['LASTBOOK'] ?? true ); ?>>
								<?php esc_html_e( 'Last Booking Date', 'bkx-mailchimp-pro' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="bkx_mailchimp_pro_settings[merge_fields][TOTSPENT]" value="1" <?php checked( $settings['merge_fields']['TOTSPENT'] ?? true ); ?>>
								<?php esc_html_e( 'Total Amount Spent', 'bkx-mailchimp-pro' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="sync_frequency"><?php esc_html_e( 'Sync Frequency', 'bkx-mailchimp-pro' ); ?></label>
						</th>
						<td>
							<select id="sync_frequency" name="bkx_mailchimp_pro_settings[sync_frequency]">
								<option value="realtime" <?php selected( $settings['sync_frequency'] ?? 'realtime', 'realtime' ); ?>><?php esc_html_e( 'Real-time (immediate)', 'bkx-mailchimp-pro' ); ?></option>
								<option value="hourly" <?php selected( $settings['sync_frequency'] ?? 'realtime', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'bkx-mailchimp-pro' ); ?></option>
								<option value="daily" <?php selected( $settings['sync_frequency'] ?? 'realtime', 'daily' ); ?>><?php esc_html_e( 'Daily', 'bkx-mailchimp-pro' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>

			<h3><?php esc_html_e( 'Manual Sync', 'bkx-mailchimp-pro' ); ?></h3>
			<p>
				<button type="button" id="manual-sync-all" class="button button-secondary"><?php esc_html_e( 'Sync All Bookings', 'bkx-mailchimp-pro' ); ?></button>
				<button type="button" id="manual-sync-recent" class="button button-secondary"><?php esc_html_e( 'Sync Recent Bookings (Last 100)', 'bkx-mailchimp-pro' ); ?></button>
			</p>
			<div id="sync-status"></div>
		</div>
		<?php
	}
}
