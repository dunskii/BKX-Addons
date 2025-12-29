<?php
/**
 * Settings Tab Template.
 *
 * @package BookingX\WhatsAppBusiness
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_whatsapp_business_settings', array() );
$webhook_url = rest_url( 'bkx-whatsapp/v1/webhook' );
?>

<div class="bkx-settings-container">
	<form id="bkx-whatsapp-settings-form">
		<!-- API Configuration -->
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'API Configuration', 'bkx-whatsapp-business' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable WhatsApp', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
							<?php esc_html_e( 'Enable WhatsApp notifications', 'bkx-whatsapp-business' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'API Provider', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<select name="api_provider" id="bkx-api-provider">
							<option value="cloud_api" <?php selected( ( $settings['api_provider'] ?? '' ), 'cloud_api' ); ?>>
								<?php esc_html_e( 'Meta Cloud API (Official)', 'bkx-whatsapp-business' ); ?>
							</option>
							<option value="twilio" <?php selected( ( $settings['api_provider'] ?? '' ), 'twilio' ); ?>>
								<?php esc_html_e( 'Twilio', 'bkx-whatsapp-business' ); ?>
							</option>
							<option value="360dialog" <?php selected( ( $settings['api_provider'] ?? '' ), '360dialog' ); ?>>
								<?php esc_html_e( '360dialog', 'bkx-whatsapp-business' ); ?>
							</option>
						</select>
					</td>
				</tr>
			</table>

			<!-- Cloud API Settings -->
			<div class="bkx-provider-settings" id="bkx-cloud-api-settings">
				<h3><?php esc_html_e( 'Meta Cloud API Settings', 'bkx-whatsapp-business' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Phone Number ID', 'bkx-whatsapp-business' ); ?></th>
						<td>
							<input type="text" name="phone_number_id" value="<?php echo esc_attr( $settings['phone_number_id'] ?? '' ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Found in Meta Business Suite > WhatsApp > API Setup', 'bkx-whatsapp-business' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Business Account ID', 'bkx-whatsapp-business' ); ?></th>
						<td>
							<input type="text" name="business_account_id" value="<?php echo esc_attr( $settings['business_account_id'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Access Token', 'bkx-whatsapp-business' ); ?></th>
						<td>
							<input type="password" name="access_token" value="<?php echo esc_attr( $settings['access_token'] ?? '' ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Permanent access token from Meta Developer Portal', 'bkx-whatsapp-business' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Twilio Settings -->
			<div class="bkx-provider-settings" id="bkx-twilio-settings" style="display: none;">
				<h3><?php esc_html_e( 'Twilio Settings', 'bkx-whatsapp-business' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Account SID', 'bkx-whatsapp-business' ); ?></th>
						<td>
							<input type="text" name="twilio_account_sid" value="<?php echo esc_attr( $settings['twilio_account_sid'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auth Token', 'bkx-whatsapp-business' ); ?></th>
						<td>
							<input type="password" name="twilio_auth_token" value="<?php echo esc_attr( $settings['twilio_auth_token'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WhatsApp Phone Number', 'bkx-whatsapp-business' ); ?></th>
						<td>
							<input type="text" name="twilio_phone_number" value="<?php echo esc_attr( $settings['twilio_phone_number'] ?? '' ); ?>" class="regular-text" placeholder="+14155238886">
						</td>
					</tr>
				</table>
			</div>

			<!-- 360dialog Settings -->
			<div class="bkx-provider-settings" id="bkx-360dialog-settings" style="display: none;">
				<h3><?php esc_html_e( '360dialog Settings', 'bkx-whatsapp-business' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'API Key', 'bkx-whatsapp-business' ); ?></th>
						<td>
							<input type="password" name="dialog360_api_key" value="<?php echo esc_attr( $settings['dialog360_api_key'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
				</table>
			</div>

			<!-- Webhook Configuration -->
			<h3><?php esc_html_e( 'Webhook Configuration', 'bkx-whatsapp-business' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Webhook URL', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<code id="bkx-webhook-url"><?php echo esc_url( $webhook_url ); ?></code>
						<button type="button" class="button button-small" id="bkx-copy-webhook">
							<?php esc_html_e( 'Copy', 'bkx-whatsapp-business' ); ?>
						</button>
						<p class="description"><?php esc_html_e( 'Add this URL to your WhatsApp webhook configuration', 'bkx-whatsapp-business' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Verify Token', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<input type="text" name="webhook_verify_token" value="<?php echo esc_attr( $settings['webhook_verify_token'] ?? '' ); ?>" class="regular-text" readonly>
						<p class="description"><?php esc_html_e( 'Use this token when setting up the webhook', 'bkx-whatsapp-business' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Test Connection', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<button type="button" class="button" id="bkx-test-connection">
							<?php esc_html_e( 'Test Connection', 'bkx-whatsapp-business' ); ?>
						</button>
						<span id="bkx-connection-status"></span>
					</td>
				</tr>
			</table>
		</div>

		<!-- Notification Settings -->
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'Notification Settings', 'bkx-whatsapp-business' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Booking Confirmation', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="send_booking_confirmation" value="1" <?php checked( ! empty( $settings['send_booking_confirmation'] ) ); ?>>
							<?php esc_html_e( 'Send confirmation when booking is created', 'bkx-whatsapp-business' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Booking Reminder', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="send_booking_reminder" value="1" <?php checked( ! empty( $settings['send_booking_reminder'] ) ); ?>>
							<?php esc_html_e( 'Send reminder before appointment', 'bkx-whatsapp-business' ); ?>
						</label>
						<br>
						<label>
							<?php esc_html_e( 'Hours before:', 'bkx-whatsapp-business' ); ?>
							<input type="number" name="reminder_hours" value="<?php echo esc_attr( $settings['reminder_hours'] ?? 24 ); ?>" min="1" max="168" style="width: 60px;">
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Booking Cancelled', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="send_booking_cancelled" value="1" <?php checked( ! empty( $settings['send_booking_cancelled'] ) ); ?>>
							<?php esc_html_e( 'Send notification when booking is cancelled', 'bkx-whatsapp-business' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Booking Rescheduled', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="send_booking_rescheduled" value="1" <?php checked( ! empty( $settings['send_booking_rescheduled'] ) ); ?>>
							<?php esc_html_e( 'Send notification when booking is rescheduled', 'bkx-whatsapp-business' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- Two-Way Chat Settings -->
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'Two-Way Chat', 'bkx-whatsapp-business' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Chat', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_two_way_chat" value="1" <?php checked( ! empty( $settings['enable_two_way_chat'] ) ); ?>>
							<?php esc_html_e( 'Allow customers to reply to messages', 'bkx-whatsapp-business' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-Reply', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_reply_enabled" value="1" <?php checked( ! empty( $settings['auto_reply_enabled'] ) ); ?>>
							<?php esc_html_e( 'Send automatic reply to incoming messages', 'bkx-whatsapp-business' ); ?>
						</label>
						<br><br>
						<textarea name="auto_reply_message" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'Thank you for your message. We will get back to you shortly.', 'bkx-whatsapp-business' ); ?>"><?php echo esc_textarea( $settings['auto_reply_message'] ?? '' ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Business Hours', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<label>
							<?php esc_html_e( 'From:', 'bkx-whatsapp-business' ); ?>
							<input type="time" name="business_hours_start" value="<?php echo esc_attr( $settings['business_hours_start'] ?? '09:00' ); ?>">
						</label>
						<label>
							<?php esc_html_e( 'To:', 'bkx-whatsapp-business' ); ?>
							<input type="time" name="business_hours_end" value="<?php echo esc_attr( $settings['business_hours_end'] ?? '18:00' ); ?>">
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Outside Hours Message', 'bkx-whatsapp-business' ); ?></th>
					<td>
						<textarea name="outside_hours_message" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'We are currently outside business hours. We will respond when we reopen.', 'bkx-whatsapp-business' ); ?>"><?php echo esc_textarea( $settings['outside_hours_message'] ?? '' ); ?></textarea>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'bkx-whatsapp-business' ); ?>">
		</p>
	</form>
</div>
