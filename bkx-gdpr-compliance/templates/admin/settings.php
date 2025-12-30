<?php
/**
 * Settings template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_gdpr_settings', array() );

// Handle form submission.
if ( isset( $_POST['bkx_gdpr_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_gdpr_settings_nonce'] ) ), 'bkx_gdpr_save_settings' ) ) {
	$new_settings = array(
		'enabled'                    => ! empty( $_POST['enabled'] ),
		'privacy_policy_page'        => absint( $_POST['privacy_policy_page'] ?? 0 ),
		'cookie_policy_page'         => absint( $_POST['cookie_policy_page'] ?? 0 ),
		'data_retention_days'        => absint( $_POST['data_retention_days'] ?? 365 ),
		'booking_retention_days'     => absint( $_POST['booking_retention_days'] ?? 730 ),
		'consent_required'           => ! empty( $_POST['consent_required'] ),
		'cookie_banner_enabled'      => ! empty( $_POST['cookie_banner_enabled'] ),
		'cookie_banner_position'     => sanitize_text_field( $_POST['cookie_banner_position'] ?? 'bottom' ),
		'dpo_email'                  => sanitize_email( $_POST['dpo_email'] ?? '' ),
		'dpo_name'                   => sanitize_text_field( $_POST['dpo_name'] ?? '' ),
		'company_name'               => sanitize_text_field( $_POST['company_name'] ?? '' ),
		'company_address'            => sanitize_textarea_field( $_POST['company_address'] ?? '' ),
		'auto_delete_expired'        => ! empty( $_POST['auto_delete_expired'] ),
		'anonymize_instead_delete'   => ! empty( $_POST['anonymize_instead_delete'] ),
		'request_verification'       => ! empty( $_POST['request_verification'] ),
		'request_expiry_hours'       => absint( $_POST['request_expiry_hours'] ?? 48 ),
		'breach_notification_emails' => sanitize_text_field( $_POST['breach_notification_emails'] ?? '' ),
		'ccpa_enabled'               => ! empty( $_POST['ccpa_enabled'] ),
		'ccpa_do_not_sell'           => ! empty( $_POST['ccpa_do_not_sell'] ),
	);

	update_option( 'bkx_gdpr_settings', $new_settings );
	$settings = $new_settings;

	echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully.', 'bkx-gdpr-compliance' ) . '</p></div>';
}

$pages = get_pages();
?>

<div class="bkx-gdpr-settings">
	<form method="post">
		<?php wp_nonce_field( 'bkx_gdpr_save_settings', 'bkx_gdpr_settings_nonce' ); ?>

		<!-- General Settings -->
		<div class="bkx-gdpr-card">
			<h2><?php esc_html_e( 'General Settings', 'bkx-gdpr-compliance' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable GDPR Features', 'bkx-gdpr-compliance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ?? true ); ?>>
							<?php esc_html_e( 'Enable GDPR/CCPA compliance features', 'bkx-gdpr-compliance' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="company_name"><?php esc_html_e( 'Company Name', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<input type="text" name="company_name" id="company_name" class="regular-text"
							   value="<?php echo esc_attr( $settings['company_name'] ?? '' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="company_address"><?php esc_html_e( 'Company Address', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<textarea name="company_address" id="company_address" rows="3" class="large-text"><?php echo esc_textarea( $settings['company_address'] ?? '' ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dpo_name"><?php esc_html_e( 'Data Protection Officer Name', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<input type="text" name="dpo_name" id="dpo_name" class="regular-text"
							   value="<?php echo esc_attr( $settings['dpo_name'] ?? '' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dpo_email"><?php esc_html_e( 'DPO Contact Email', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<input type="email" name="dpo_email" id="dpo_email" class="regular-text"
							   value="<?php echo esc_attr( $settings['dpo_email'] ?? '' ); ?>">
					</td>
				</tr>
			</table>
		</div>

		<!-- Policy Pages -->
		<div class="bkx-gdpr-card">
			<h2><?php esc_html_e( 'Policy Pages', 'bkx-gdpr-compliance' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="privacy_policy_page"><?php esc_html_e( 'Privacy Policy Page', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<select name="privacy_policy_page" id="privacy_policy_page">
							<option value=""><?php esc_html_e( 'Select a page...', 'bkx-gdpr-compliance' ); ?></option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $settings['privacy_policy_page'] ?? 0, $page->ID ); ?>>
									<?php echo esc_html( $page->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cookie_policy_page"><?php esc_html_e( 'Cookie Policy Page', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<select name="cookie_policy_page" id="cookie_policy_page">
							<option value=""><?php esc_html_e( 'Select a page...', 'bkx-gdpr-compliance' ); ?></option>
							<?php foreach ( $pages as $page ) : ?>
								<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $settings['cookie_policy_page'] ?? 0, $page->ID ); ?>>
									<?php echo esc_html( $page->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- Consent Settings -->
		<div class="bkx-gdpr-card">
			<h2><?php esc_html_e( 'Consent Settings', 'bkx-gdpr-compliance' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Booking Form Consent', 'bkx-gdpr-compliance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="consent_required" value="1" <?php checked( $settings['consent_required'] ?? true ); ?>>
							<?php esc_html_e( 'Require privacy consent on booking form', 'bkx-gdpr-compliance' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cookie Banner', 'bkx-gdpr-compliance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cookie_banner_enabled" value="1" <?php checked( $settings['cookie_banner_enabled'] ?? true ); ?>>
							<?php esc_html_e( 'Show cookie consent banner', 'bkx-gdpr-compliance' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cookie_banner_position"><?php esc_html_e( 'Banner Position', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<select name="cookie_banner_position" id="cookie_banner_position">
							<option value="bottom" <?php selected( $settings['cookie_banner_position'] ?? 'bottom', 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'bkx-gdpr-compliance' ); ?></option>
							<option value="top" <?php selected( $settings['cookie_banner_position'] ?? 'bottom', 'top' ); ?>><?php esc_html_e( 'Top', 'bkx-gdpr-compliance' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- Data Retention -->
		<div class="bkx-gdpr-card">
			<h2><?php esc_html_e( 'Data Retention', 'bkx-gdpr-compliance' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="data_retention_days"><?php esc_html_e( 'General Data Retention', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<input type="number" name="data_retention_days" id="data_retention_days" class="small-text"
							   value="<?php echo esc_attr( $settings['data_retention_days'] ?? 365 ); ?>" min="30" max="3650">
						<?php esc_html_e( 'days', 'bkx-gdpr-compliance' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="booking_retention_days"><?php esc_html_e( 'Booking Data Retention', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<input type="number" name="booking_retention_days" id="booking_retention_days" class="small-text"
							   value="<?php echo esc_attr( $settings['booking_retention_days'] ?? 730 ); ?>" min="30" max="3650">
						<?php esc_html_e( 'days after completion', 'bkx-gdpr-compliance' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic Cleanup', 'bkx-gdpr-compliance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_delete_expired" value="1" <?php checked( $settings['auto_delete_expired'] ?? false ); ?>>
							<?php esc_html_e( 'Automatically delete/anonymize expired data', 'bkx-gdpr-compliance' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Anonymization', 'bkx-gdpr-compliance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="anonymize_instead_delete" value="1" <?php checked( $settings['anonymize_instead_delete'] ?? true ); ?>>
							<?php esc_html_e( 'Anonymize bookings instead of deleting (recommended)', 'bkx-gdpr-compliance' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Keeps booking statistics while removing personal data.', 'bkx-gdpr-compliance' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Data Request Settings -->
		<div class="bkx-gdpr-card">
			<h2><?php esc_html_e( 'Data Request Settings', 'bkx-gdpr-compliance' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Email Verification', 'bkx-gdpr-compliance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="request_verification" value="1" <?php checked( $settings['request_verification'] ?? true ); ?>>
							<?php esc_html_e( 'Require email verification for data requests', 'bkx-gdpr-compliance' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="request_expiry_hours"><?php esc_html_e( 'Request Expiry', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<input type="number" name="request_expiry_hours" id="request_expiry_hours" class="small-text"
							   value="<?php echo esc_attr( $settings['request_expiry_hours'] ?? 48 ); ?>" min="1" max="168">
						<?php esc_html_e( 'hours', 'bkx-gdpr-compliance' ); ?>
						<p class="description"><?php esc_html_e( 'Unverified requests will expire after this time.', 'bkx-gdpr-compliance' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Breach Notifications -->
		<div class="bkx-gdpr-card">
			<h2><?php esc_html_e( 'Breach Notifications', 'bkx-gdpr-compliance' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="breach_notification_emails"><?php esc_html_e( 'Notification Emails', 'bkx-gdpr-compliance' ); ?></label>
					</th>
					<td>
						<input type="text" name="breach_notification_emails" id="breach_notification_emails" class="large-text"
							   value="<?php echo esc_attr( $settings['breach_notification_emails'] ?? '' ); ?>">
						<p class="description"><?php esc_html_e( 'Comma-separated list of emails to notify in case of a data breach.', 'bkx-gdpr-compliance' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- CCPA Settings -->
		<div class="bkx-gdpr-card">
			<h2><?php esc_html_e( 'CCPA Settings (California)', 'bkx-gdpr-compliance' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable CCPA', 'bkx-gdpr-compliance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ccpa_enabled" value="1" <?php checked( $settings['ccpa_enabled'] ?? true ); ?>>
							<?php esc_html_e( 'Enable CCPA compliance features', 'bkx-gdpr-compliance' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Do Not Sell Link', 'bkx-gdpr-compliance' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ccpa_do_not_sell" value="1" <?php checked( $settings['ccpa_do_not_sell'] ?? true ); ?>>
							<?php esc_html_e( 'Show "Do Not Sell My Personal Information" option', 'bkx-gdpr-compliance' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'bkx-gdpr-compliance' ); ?></button>
		</p>
	</form>
</div>
