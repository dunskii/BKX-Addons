<?php
/**
 * Webhooks tab template.
 *
 * @package BookingX\Discord
 */

defined( 'ABSPATH' ) || exit;

$webhook_manager = new \BookingX\Discord\Services\WebhookManager();
$webhooks = $webhook_manager->get_webhooks();
?>

<div class="bkx-discord-webhooks">
	<!-- Add New Webhook Card -->
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Add Discord Webhook', 'bkx-discord' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Add a Discord webhook to receive booking notifications in your server.', 'bkx-discord' ); ?>
		</p>

		<form id="bkx-discord-add-webhook-form" class="bkx-form">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="webhook-name"><?php esc_html_e( 'Name', 'bkx-discord' ); ?></label>
					</th>
					<td>
						<input type="text" id="webhook-name" name="name" class="regular-text"
							   placeholder="<?php esc_attr_e( 'e.g., Booking Alerts', 'bkx-discord' ); ?>" required>
						<p class="description">
							<?php esc_html_e( 'A friendly name to identify this webhook.', 'bkx-discord' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="webhook-url"><?php esc_html_e( 'Webhook URL', 'bkx-discord' ); ?></label>
					</th>
					<td>
						<input type="url" id="webhook-url" name="webhook_url" class="large-text"
							   placeholder="https://discord.com/api/webhooks/..." required>
						<p class="description">
							<?php
							printf(
								/* translators: %s: Link to Discord documentation */
								esc_html__( 'Create a webhook in your Discord server settings. %s', 'bkx-discord' ),
								'<a href="https://support.discord.com/hc/en-us/articles/228383668" target="_blank">' . esc_html__( 'Learn how', 'bkx-discord' ) . '</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Events', 'bkx-discord' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="events[]" value="new_booking" checked>
								<?php esc_html_e( 'New Booking', 'bkx-discord' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="events[]" value="cancelled" checked>
								<?php esc_html_e( 'Booking Cancelled', 'bkx-discord' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="events[]" value="completed" checked>
								<?php esc_html_e( 'Booking Completed', 'bkx-discord' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="events[]" value="rescheduled" checked>
								<?php esc_html_e( 'Booking Rescheduled', 'bkx-discord' ); ?>
							</label>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Select which events trigger notifications to this webhook.', 'bkx-discord' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Add Webhook', 'bkx-discord' ); ?>
				</button>
			</p>
		</form>
	</div>

	<!-- Configured Webhooks Card -->
	<?php if ( ! empty( $webhooks ) ) : ?>
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Configured Webhooks', 'bkx-discord' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 200px;"><?php esc_html_e( 'Name', 'bkx-discord' ); ?></th>
						<th><?php esc_html_e( 'Channel', 'bkx-discord' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Status', 'bkx-discord' ); ?></th>
						<th style="width: 180px;"><?php esc_html_e( 'Events', 'bkx-discord' ); ?></th>
						<th style="width: 200px;"><?php esc_html_e( 'Actions', 'bkx-discord' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $webhooks as $webhook ) : ?>
						<tr data-webhook-id="<?php echo esc_attr( $webhook->id ); ?>">
							<td>
								<strong><?php echo esc_html( $webhook->name ); ?></strong>
								<?php if ( $webhook->guild_id ) : ?>
									<br>
									<small class="bkx-muted"><?php echo esc_html( $webhook->guild_id ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $webhook->channel_name ) : ?>
									#<?php echo esc_html( $webhook->channel_name ); ?>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td>
								<label class="bkx-toggle">
									<input type="checkbox" class="bkx-toggle-webhook"
										   data-webhook-id="<?php echo esc_attr( $webhook->id ); ?>"
										   <?php checked( $webhook->is_active ); ?>>
									<span class="bkx-toggle-slider"></span>
								</label>
							</td>
							<td>
								<?php
								$events = is_array( $webhook->notify_events ) ? $webhook->notify_events : array();
								if ( empty( $events ) ) {
									echo '<span class="bkx-muted">' . esc_html__( 'All events', 'bkx-discord' ) . '</span>';
								} else {
									echo esc_html( implode( ', ', array_map( 'ucwords', str_replace( '_', ' ', $events ) ) ) );
								}
								?>
							</td>
							<td>
								<button type="button" class="button button-small bkx-test-webhook"
										data-webhook-id="<?php echo esc_attr( $webhook->id ); ?>">
									<?php esc_html_e( 'Test', 'bkx-discord' ); ?>
								</button>
								<button type="button" class="button button-small button-link-delete bkx-delete-webhook"
										data-webhook-id="<?php echo esc_attr( $webhook->id ); ?>">
									<?php esc_html_e( 'Delete', 'bkx-discord' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<!-- How to Create Webhook Card -->
	<div class="bkx-card">
		<h2><?php esc_html_e( 'How to Create a Discord Webhook', 'bkx-discord' ); ?></h2>

		<ol class="bkx-setup-steps">
			<li>
				<strong><?php esc_html_e( 'Open Server Settings', 'bkx-discord' ); ?></strong>
				<p><?php esc_html_e( 'Right-click on your Discord server and select "Server Settings".', 'bkx-discord' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Go to Integrations', 'bkx-discord' ); ?></strong>
				<p><?php esc_html_e( 'Navigate to "Integrations" in the left sidebar.', 'bkx-discord' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Create Webhook', 'bkx-discord' ); ?></strong>
				<p><?php esc_html_e( 'Click "Webhooks" then "New Webhook". Choose the channel for notifications.', 'bkx-discord' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Copy Webhook URL', 'bkx-discord' ); ?></strong>
				<p><?php esc_html_e( 'Click "Copy Webhook URL" and paste it in the form above.', 'bkx-discord' ); ?></p>
			</li>
		</ol>
	</div>
</div>
