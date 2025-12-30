<?php
/**
 * HIPAA Compliance Settings.
 *
 * @package BookingX\HIPAA
 */

defined( 'ABSPATH' ) || exit;

$settings   = get_option( 'bkx_hipaa_settings', array() );
$phi_fields = \BookingX\HIPAA\Services\PHIHandler::get_default_phi_fields();
?>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'HIPAA Compliance Settings', 'bkx-hipaa' ); ?></h2>

	<form id="bkx-hipaa-settings-form" method="post">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable HIPAA Mode', 'bkx-hipaa' ); ?></th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="enabled" value="1"
							   <?php checked( isset( $settings['enabled'] ) ? $settings['enabled'] : true, true ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable HIPAA compliance features including PHI encryption and audit logging.', 'bkx-hipaa' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Encryption Method', 'bkx-hipaa' ); ?></th>
				<td>
					<select name="encryption_method">
						<option value="aes-256-gcm" <?php selected( isset( $settings['encryption_method'] ) ? $settings['encryption_method'] : 'aes-256-gcm', 'aes-256-gcm' ); ?>>
							AES-256-GCM (Recommended)
						</option>
						<option value="aes-256-cbc" <?php selected( isset( $settings['encryption_method'] ) ? $settings['encryption_method'] : '', 'aes-256-cbc' ); ?>>
							AES-256-CBC
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Encryption algorithm for PHI data.', 'bkx-hipaa' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'PHI Fields', 'bkx-hipaa' ); ?></th>
				<td>
					<?php
					$selected_fields = isset( $settings['phi_fields'] ) ? $settings['phi_fields'] : array( 'customer_email', 'customer_phone', 'customer_name', 'booking_notes' );
					foreach ( $phi_fields as $field => $label ) :
						?>
						<label style="display: block; margin-bottom: 5px;">
							<input type="checkbox" name="phi_fields[]" value="<?php echo esc_attr( $field ); ?>"
								   <?php checked( in_array( $field, $selected_fields, true ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description">
						<?php esc_html_e( 'Select fields to encrypt and protect as PHI.', 'bkx-hipaa' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Audit Log Retention', 'bkx-hipaa' ); ?></th>
				<td>
					<select name="audit_retention_days">
						<option value="365" <?php selected( isset( $settings['audit_retention_days'] ) ? $settings['audit_retention_days'] : 0, 365 ); ?>>
							1 <?php esc_html_e( 'year', 'bkx-hipaa' ); ?>
						</option>
						<option value="730" <?php selected( isset( $settings['audit_retention_days'] ) ? $settings['audit_retention_days'] : 0, 730 ); ?>>
							2 <?php esc_html_e( 'years', 'bkx-hipaa' ); ?>
						</option>
						<option value="1095" <?php selected( isset( $settings['audit_retention_days'] ) ? $settings['audit_retention_days'] : 0, 1095 ); ?>>
							3 <?php esc_html_e( 'years', 'bkx-hipaa' ); ?>
						</option>
						<option value="2190" <?php selected( isset( $settings['audit_retention_days'] ) ? $settings['audit_retention_days'] : 2190, 2190 ); ?>>
							6 <?php esc_html_e( 'years (HIPAA Requirement)', 'bkx-hipaa' ); ?>
						</option>
						<option value="3650" <?php selected( isset( $settings['audit_retention_days'] ) ? $settings['audit_retention_days'] : 0, 3650 ); ?>>
							10 <?php esc_html_e( 'years', 'bkx-hipaa' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'HIPAA requires audit logs to be retained for at least 6 years.', 'bkx-hipaa' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-Logout Timeout', 'bkx-hipaa' ); ?></th>
				<td>
					<select name="auto_logout_minutes">
						<option value="5" <?php selected( isset( $settings['auto_logout_minutes'] ) ? $settings['auto_logout_minutes'] : 0, 5 ); ?>>
							5 <?php esc_html_e( 'minutes', 'bkx-hipaa' ); ?>
						</option>
						<option value="10" <?php selected( isset( $settings['auto_logout_minutes'] ) ? $settings['auto_logout_minutes'] : 0, 10 ); ?>>
							10 <?php esc_html_e( 'minutes', 'bkx-hipaa' ); ?>
						</option>
						<option value="15" <?php selected( isset( $settings['auto_logout_minutes'] ) ? $settings['auto_logout_minutes'] : 15, 15 ); ?>>
							15 <?php esc_html_e( 'minutes (Recommended)', 'bkx-hipaa' ); ?>
						</option>
						<option value="30" <?php selected( isset( $settings['auto_logout_minutes'] ) ? $settings['auto_logout_minutes'] : 0, 30 ); ?>>
							30 <?php esc_html_e( 'minutes', 'bkx-hipaa' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Automatically log out inactive users after this period.', 'bkx-hipaa' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Password Security', 'bkx-hipaa' ); ?></th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="require_strong_password" value="1"
							   <?php checked( isset( $settings['require_strong_password'] ) ? $settings['require_strong_password'] : true, true ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<?php esc_html_e( 'Require strong passwords', 'bkx-hipaa' ); ?>
					<p class="description">
						<?php esc_html_e( 'Enforce minimum 8 characters with uppercase, lowercase, number, and special character.', 'bkx-hipaa' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Access Review Period', 'bkx-hipaa' ); ?></th>
				<td>
					<select name="access_review_days">
						<option value="30" <?php selected( isset( $settings['access_review_days'] ) ? $settings['access_review_days'] : 0, 30 ); ?>>
							30 <?php esc_html_e( 'days', 'bkx-hipaa' ); ?>
						</option>
						<option value="60" <?php selected( isset( $settings['access_review_days'] ) ? $settings['access_review_days'] : 0, 60 ); ?>>
							60 <?php esc_html_e( 'days', 'bkx-hipaa' ); ?>
						</option>
						<option value="90" <?php selected( isset( $settings['access_review_days'] ) ? $settings['access_review_days'] : 90, 90 ); ?>>
							90 <?php esc_html_e( 'days (Recommended)', 'bkx-hipaa' ); ?>
						</option>
						<option value="180" <?php selected( isset( $settings['access_review_days'] ) ? $settings['access_review_days'] : 0, 180 ); ?>>
							180 <?php esc_html_e( 'days', 'bkx-hipaa' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Periodic review of user access levels.', 'bkx-hipaa' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Breach Notification', 'bkx-hipaa' ); ?></th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="breach_notification" value="1"
							   <?php checked( isset( $settings['breach_notification'] ) ? $settings['breach_notification'] : true, true ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<?php esc_html_e( 'Enable breach notification alerts', 'bkx-hipaa' ); ?>
					<p class="description">
						<?php esc_html_e( 'Send alerts when suspicious activity is detected.', 'bkx-hipaa' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" id="bkx-save-settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-hipaa' ); ?>
			</button>
			<span class="spinner"></span>
		</p>
	</form>
</div>
