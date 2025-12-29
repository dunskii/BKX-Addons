<?php
/**
 * Settings tab template.
 *
 * @package BookingX\Slack
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_slack_settings', array() );
?>

<div class="bkx-slack-settings">
	<form id="bkx-slack-settings-form" method="post">
		<?php wp_nonce_field( 'bkx_slack_settings', 'bkx_slack_nonce' ); ?>

		<div class="bkx-card">
			<h2><?php esc_html_e( 'Slack App Configuration', 'bkx-slack' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: %s: Slack API link */
					esc_html__( 'Create a Slack App at %s and enter the credentials below.', 'bkx-slack' ),
					'<a href="https://api.slack.com/apps" target="_blank">api.slack.com/apps</a>'
				);
				?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="client_id"><?php esc_html_e( 'Client ID', 'bkx-slack' ); ?></label>
					</th>
					<td>
						<input type="text" id="client_id" name="client_id"
							   value="<?php echo esc_attr( $settings['client_id'] ?? '' ); ?>"
							   class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="client_secret"><?php esc_html_e( 'Client Secret', 'bkx-slack' ); ?></label>
					</th>
					<td>
						<input type="password" id="client_secret" name="client_secret"
							   value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>"
							   class="regular-text" required>
						<button type="button" class="button bkx-toggle-password">
							<?php esc_html_e( 'Show', 'bkx-slack' ); ?>
						</button>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="signing_secret"><?php esc_html_e( 'Signing Secret', 'bkx-slack' ); ?></label>
					</th>
					<td>
						<input type="password" id="signing_secret" name="signing_secret"
							   value="<?php echo esc_attr( $settings['signing_secret'] ?? '' ); ?>"
							   class="regular-text">
						<button type="button" class="button bkx-toggle-password">
							<?php esc_html_e( 'Show', 'bkx-slack' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Required for slash commands and interactive components.', 'bkx-slack' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-card">
			<h2><?php esc_html_e( 'Webhook URLs', 'bkx-slack' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Use these URLs when configuring your Slack App.', 'bkx-slack' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'OAuth Redirect URL', 'bkx-slack' ); ?></th>
					<td>
						<code id="oauth-url"><?php echo esc_url( admin_url( 'admin.php?page=bkx-slack' ) ); ?></code>
						<button type="button" class="button bkx-copy-btn" data-copy="oauth-url">
							<?php esc_html_e( 'Copy', 'bkx-slack' ); ?>
						</button>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Slash Commands URL', 'bkx-slack' ); ?></th>
					<td>
						<code id="slash-url"><?php echo esc_url( rest_url( 'bkx-slack/v1/slash' ) ); ?></code>
						<button type="button" class="button bkx-copy-btn" data-copy="slash-url">
							<?php esc_html_e( 'Copy', 'bkx-slack' ); ?>
						</button>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Interactive Components URL', 'bkx-slack' ); ?></th>
					<td>
						<code id="interactive-url"><?php echo esc_url( rest_url( 'bkx-slack/v1/interactive' ) ); ?></code>
						<button type="button" class="button bkx-copy-btn" data-copy="interactive-url">
							<?php esc_html_e( 'Copy', 'bkx-slack' ); ?>
						</button>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Event Subscriptions URL', 'bkx-slack' ); ?></th>
					<td>
						<code id="events-url"><?php echo esc_url( rest_url( 'bkx-slack/v1/events' ) ); ?></code>
						<button type="button" class="button bkx-copy-btn" data-copy="events-url">
							<?php esc_html_e( 'Copy', 'bkx-slack' ); ?>
						</button>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-card">
			<h2><?php esc_html_e( 'Features', 'bkx-slack' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Slash Commands', 'bkx-slack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_slash_commands" value="1"
								   <?php checked( ! empty( $settings['enable_slash_commands'] ) ); ?>>
							<?php esc_html_e( 'Enable /bookingx slash command', 'bkx-slack' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Allows team members to view and manage bookings from Slack.', 'bkx-slack' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Interactive Components', 'bkx-slack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_interactive" value="1"
								   <?php checked( ! empty( $settings['enable_interactive'] ) ); ?>>
							<?php esc_html_e( 'Enable buttons and modals in notifications', 'bkx-slack' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Allows confirming or cancelling bookings directly from Slack.', 'bkx-slack' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-slack' ); ?>
			</button>
		</p>
	</form>
</div>

<div class="bkx-card">
	<h2><?php esc_html_e( 'Setup Instructions', 'bkx-slack' ); ?></h2>

	<ol class="bkx-setup-steps">
		<li>
			<strong><?php esc_html_e( 'Create a Slack App', 'bkx-slack' ); ?></strong>
			<p><?php esc_html_e( 'Go to api.slack.com/apps and click "Create New App" > "From scratch".', 'bkx-slack' ); ?></p>
		</li>
		<li>
			<strong><?php esc_html_e( 'Configure OAuth & Permissions', 'bkx-slack' ); ?></strong>
			<p><?php esc_html_e( 'Add the OAuth Redirect URL above and add these scopes: chat:write, chat:write.public, channels:read, commands, incoming-webhook', 'bkx-slack' ); ?></p>
		</li>
		<li>
			<strong><?php esc_html_e( 'Enable Incoming Webhooks', 'bkx-slack' ); ?></strong>
			<p><?php esc_html_e( 'Turn on Incoming Webhooks in your app settings.', 'bkx-slack' ); ?></p>
		</li>
		<li>
			<strong><?php esc_html_e( 'Create Slash Command (Optional)', 'bkx-slack' ); ?></strong>
			<p><?php esc_html_e( 'Create a /bookingx command pointing to the Slash Commands URL above.', 'bkx-slack' ); ?></p>
		</li>
		<li>
			<strong><?php esc_html_e( 'Enable Interactivity (Optional)', 'bkx-slack' ); ?></strong>
			<p><?php esc_html_e( 'Enable Interactivity and add the Interactive Components URL above.', 'bkx-slack' ); ?></p>
		</li>
		<li>
			<strong><?php esc_html_e( 'Install to Workspace', 'bkx-slack' ); ?></strong>
			<p><?php esc_html_e( 'Go to the Workspaces tab and click "Add to Slack" to connect your workspace.', 'bkx-slack' ); ?></p>
		</li>
	</ol>
</div>
