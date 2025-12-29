<?php
/**
 * Settings tab template.
 *
 * @package BookingX\CRM
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_crm_settings', array() );
?>

<div class="bkx-crm-settings-page">
	<form id="bkx-crm-settings-form" method="post">
		<?php wp_nonce_field( 'bkx_crm_settings', 'bkx_crm_nonce' ); ?>

		<!-- General Settings -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'General Settings', 'bkx-crm' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'User Sync', 'bkx-crm' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_sync_users" value="1"
								   <?php checked( ! empty( $settings['auto_sync_users'] ) ); ?>>
							<?php esc_html_e( 'Automatically sync WordPress users to CRM customers', 'bkx-crm' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, user profile updates will sync to CRM customer records.', 'bkx-crm' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Automated Follow-ups -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Automated Follow-ups', 'bkx-crm' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Automation', 'bkx-crm' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_followup_enabled" value="1"
								   <?php checked( ! empty( $settings['auto_followup_enabled'] ) ); ?>>
							<?php esc_html_e( 'Automatically schedule follow-up emails for bookings', 'bkx-crm' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="thankyou-delay"><?php esc_html_e( 'Thank You Email', 'bkx-crm' ); ?></label>
					</th>
					<td>
						<input type="number" id="thankyou-delay" name="thankyou_delay" class="small-text"
							   value="<?php echo esc_attr( $settings['thankyou_delay'] ?? 24 ); ?>" min="0">
						<?php esc_html_e( 'hours after booking', 'bkx-crm' ); ?>
						<p class="description">
							<?php esc_html_e( 'Set to 0 to disable.', 'bkx-crm' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="feedback-delay"><?php esc_html_e( 'Feedback Request', 'bkx-crm' ); ?></label>
					</th>
					<td>
						<input type="number" id="feedback-delay" name="feedback_delay" class="small-text"
							   value="<?php echo esc_attr( $settings['feedback_delay'] ?? 72 ); ?>" min="0">
						<?php esc_html_e( 'hours after booking', 'bkx-crm' ); ?>
						<p class="description">
							<?php esc_html_e( 'Set to 0 to disable.', 'bkx-crm' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="reengagement-days"><?php esc_html_e( 'Re-engagement', 'bkx-crm' ); ?></label>
					</th>
					<td>
						<input type="number" id="reengagement-days" name="reengagement_days" class="small-text"
							   value="<?php echo esc_attr( $settings['reengagement_days'] ?? 30 ); ?>" min="0">
						<?php esc_html_e( 'days since last booking', 'bkx-crm' ); ?>
						<p class="description">
							<?php esc_html_e( 'Send reminder to customers who haven\'t booked recently. Set to 0 to disable.', 'bkx-crm' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Birthday Emails -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Birthday Emails', 'bkx-crm' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable', 'bkx-crm' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="birthday_enabled" value="1"
								   <?php checked( ! empty( $settings['birthday_enabled'] ) ); ?>>
							<?php esc_html_e( 'Send birthday greetings to customers', 'bkx-crm' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Requires date of birth in customer profile.', 'bkx-crm' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="birthday-days"><?php esc_html_e( 'Send Before', 'bkx-crm' ); ?></label>
					</th>
					<td>
						<input type="number" id="birthday-days" name="birthday_days_before" class="small-text"
							   value="<?php echo esc_attr( $settings['birthday_days_before'] ?? 7 ); ?>" min="0" max="30">
						<?php esc_html_e( 'days before birthday', 'bkx-crm' ); ?>
						<p class="description">
							<?php esc_html_e( 'Set to 0 to send on the actual birthday.', 'bkx-crm' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-crm' ); ?>
			</button>
		</p>
	</form>
</div>
