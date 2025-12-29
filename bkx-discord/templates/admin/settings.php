<?php
/**
 * Settings tab template.
 *
 * @package BookingX\Discord
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_discord_settings', array() );
?>

<div class="bkx-discord-settings">
	<form id="bkx-discord-settings-form" method="post">
		<?php wp_nonce_field( 'bkx_discord_settings', 'bkx_discord_nonce' ); ?>

		<!-- Appearance Settings -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Appearance', 'bkx-discord' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="embed-color"><?php esc_html_e( 'Embed Color', 'bkx-discord' ); ?></label>
					</th>
					<td>
						<input type="color" id="embed-color" name="embed_color"
							   value="<?php echo esc_attr( $settings['embed_color'] ?? '#5865F2' ); ?>">
						<p class="description">
							<?php esc_html_e( 'The accent color for notification embeds.', 'bkx-discord' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bot-username"><?php esc_html_e( 'Bot Username', 'bkx-discord' ); ?></label>
					</th>
					<td>
						<input type="text" id="bot-username" name="bot_username" class="regular-text"
							   value="<?php echo esc_attr( $settings['bot_username'] ?? 'BookingX' ); ?>">
						<p class="description">
							<?php esc_html_e( 'The display name for webhook messages.', 'bkx-discord' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mention-role"><?php esc_html_e( 'Mention Role ID', 'bkx-discord' ); ?></label>
					</th>
					<td>
						<input type="text" id="mention-role" name="mention_role" class="regular-text"
							   value="<?php echo esc_attr( $settings['mention_role'] ?? '' ); ?>"
							   placeholder="e.g., 123456789012345678">
						<p class="description">
							<?php esc_html_e( 'Optional: Role ID to @mention in notifications. Leave empty to disable.', 'bkx-discord' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Content Settings -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Notification Content', 'bkx-discord' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Include Fields', 'bkx-discord' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="include_customer" value="1"
									   <?php checked( ! empty( $settings['include_customer'] ) ); ?>>
								<?php esc_html_e( 'Customer Information', 'bkx-discord' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="include_staff" value="1"
									   <?php checked( ! empty( $settings['include_staff'] ) ); ?>>
								<?php esc_html_e( 'Staff/Resource', 'bkx-discord' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="include_price" value="1"
									   <?php checked( ! empty( $settings['include_price'] ) ); ?>>
								<?php esc_html_e( 'Booking Total', 'bkx-discord' ); ?>
							</label>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Select which fields to include in notification embeds.', 'bkx-discord' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Default Events -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Default Notification Events', 'bkx-discord' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'These settings apply when a webhook has no specific events configured.', 'bkx-discord' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Events', 'bkx-discord' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="notify_new" value="1"
									   <?php checked( ! empty( $settings['notify_new'] ) ); ?>>
								<?php esc_html_e( 'New Booking', 'bkx-discord' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="notify_cancelled" value="1"
									   <?php checked( ! empty( $settings['notify_cancelled'] ) ); ?>>
								<?php esc_html_e( 'Booking Cancelled', 'bkx-discord' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="notify_completed" value="1"
									   <?php checked( ! empty( $settings['notify_completed'] ) ); ?>>
								<?php esc_html_e( 'Booking Completed', 'bkx-discord' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="notify_rescheduled" value="1"
									   <?php checked( ! empty( $settings['notify_rescheduled'] ) ); ?>>
								<?php esc_html_e( 'Booking Rescheduled', 'bkx-discord' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>

		<!-- Notification Preview -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Notification Preview', 'bkx-discord' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'This is how your booking notifications will appear in Discord.', 'bkx-discord' ); ?>
			</p>

			<div class="bkx-discord-preview">
				<div class="bkx-discord-message">
					<div class="bkx-discord-avatar">BX</div>
					<div class="bkx-discord-content">
						<div class="bkx-discord-header">
							<span class="bkx-discord-username">BookingX</span>
							<span class="bkx-discord-timestamp">Today at 11:30 AM</span>
						</div>
						<div class="bkx-discord-embed" style="border-left-color: <?php echo esc_attr( $settings['embed_color'] ?? '#5865F2' ); ?>;">
							<div class="bkx-discord-embed-title">New Booking #123</div>
							<div class="bkx-discord-embed-description">A new booking has been created.</div>
							<div class="bkx-discord-embed-fields">
								<div class="bkx-discord-embed-field">
									<span class="bkx-discord-field-name">Service</span>
									<span class="bkx-discord-field-value">Haircut</span>
								</div>
								<div class="bkx-discord-embed-field">
									<span class="bkx-discord-field-name">Date & Time</span>
									<span class="bkx-discord-field-value">Monday, Jan 15 at 2:00 PM</span>
								</div>
								<div class="bkx-discord-embed-field">
									<span class="bkx-discord-field-name">Customer</span>
									<span class="bkx-discord-field-value">John Doe</span>
								</div>
								<div class="bkx-discord-embed-field">
									<span class="bkx-discord-field-name">Staff</span>
									<span class="bkx-discord-field-value">Jane Smith</span>
								</div>
								<div class="bkx-discord-embed-field">
									<span class="bkx-discord-field-name">Total</span>
									<span class="bkx-discord-field-value">$50.00</span>
								</div>
								<div class="bkx-discord-embed-field">
									<span class="bkx-discord-field-name">Status</span>
									<span class="bkx-discord-field-value">Pending</span>
								</div>
							</div>
							<div class="bkx-discord-embed-footer">
								<?php echo esc_html( get_bloginfo( 'name' ) ); ?> &bull; Today at 11:30 AM
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-discord' ); ?>
			</button>
		</p>
	</form>
</div>
