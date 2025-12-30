<?php
/**
 * IFTTT Settings Tab.
 *
 * @package BookingX\IFTTT
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\IFTTT\IFTTTAddon::get_instance();
$settings = get_option( 'bkx_ifttt_settings', array() );
$enabled  = ! empty( $settings['enabled'] );

// Generate endpoint URLs.
$rest_url    = rest_url( 'bookingx-ifttt/v1/' );
$service_key = $settings['service_key'] ?? '';
?>

<div class="bkx-ifttt-settings">
	<form id="bkx-ifttt-settings-form" method="post">
		<?php wp_nonce_field( 'bkx_ifttt_settings', 'bkx_ifttt_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="bkx-ifttt-enabled"><?php esc_html_e( 'Enable IFTTT Integration', 'bkx-ifttt' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" id="bkx-ifttt-enabled" name="enabled" value="1" <?php checked( $enabled ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable or disable the IFTTT integration.', 'bkx-ifttt' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx-ifttt-service-key"><?php esc_html_e( 'Service Key', 'bkx-ifttt' ); ?></label>
				</th>
				<td>
					<div class="bkx-key-field">
						<input type="text" id="bkx-ifttt-service-key" name="service_key"
							   value="<?php echo esc_attr( $service_key ); ?>"
							   class="regular-text" readonly>
						<button type="button" class="button" id="bkx-regenerate-key">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Regenerate', 'bkx-ifttt' ); ?>
						</button>
						<button type="button" class="button" id="bkx-copy-key">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copy', 'bkx-ifttt' ); ?>
						</button>
					</div>
					<p class="description">
						<?php esc_html_e( 'This key is used to authenticate requests from IFTTT. Keep it secret!', 'bkx-ifttt' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'API Endpoints', 'bkx-ifttt' ); ?>
				</th>
				<td>
					<div class="bkx-endpoint-info">
						<p><strong><?php esc_html_e( 'Base URL:', 'bkx-ifttt' ); ?></strong></p>
						<code id="bkx-base-url"><?php echo esc_url( $rest_url ); ?></code>
						<button type="button" class="button button-small bkx-copy-endpoint" data-target="bkx-base-url">
							<span class="dashicons dashicons-clipboard"></span>
						</button>
					</div>
					<div class="bkx-endpoint-list">
						<p><strong><?php esc_html_e( 'Available Endpoints:', 'bkx-ifttt' ); ?></strong></p>
						<ul>
							<li><code>GET /status</code> - <?php esc_html_e( 'Service status', 'bkx-ifttt' ); ?></li>
							<li><code>POST /triggers/{trigger_slug}</code> - <?php esc_html_e( 'Trigger data', 'bkx-ifttt' ); ?></li>
							<li><code>POST /actions/{action_slug}</code> - <?php esc_html_e( 'Execute action', 'bkx-ifttt' ); ?></li>
							<li><code>POST /webhooks</code> - <?php esc_html_e( 'Register webhook', 'bkx-ifttt' ); ?></li>
						</ul>
					</div>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx-ifttt-rate-limit"><?php esc_html_e( 'Rate Limit', 'bkx-ifttt' ); ?></label>
				</th>
				<td>
					<input type="number" id="bkx-ifttt-rate-limit" name="rate_limit"
						   value="<?php echo esc_attr( $settings['rate_limit'] ?? 100 ); ?>"
						   min="10" max="1000" class="small-text">
					<span><?php esc_html_e( 'requests per hour', 'bkx-ifttt' ); ?></span>
					<p class="description">
						<?php esc_html_e( 'Maximum number of API requests allowed per hour from a single source.', 'bkx-ifttt' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx-ifttt-log-requests"><?php esc_html_e( 'Log Requests', 'bkx-ifttt' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" id="bkx-ifttt-log-requests" name="log_requests" value="1"
							   <?php checked( ! empty( $settings['log_requests'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable logging of API requests and webhook deliveries for debugging.', 'bkx-ifttt' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div class="bkx-ifttt-connection-test">
			<h3><?php esc_html_e( 'Connection Test', 'bkx-ifttt' ); ?></h3>
			<button type="button" class="button" id="bkx-test-connection">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Test Connection', 'bkx-ifttt' ); ?>
			</button>
			<span id="bkx-connection-status"></span>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary" id="bkx-save-settings">
				<?php esc_html_e( 'Save Settings', 'bkx-ifttt' ); ?>
			</button>
			<span class="spinner"></span>
		</p>
	</form>

	<div class="bkx-ifttt-documentation">
		<h3><?php esc_html_e( 'Getting Started with IFTTT', 'bkx-ifttt' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Create an IFTTT account or sign in at ifttt.com', 'bkx-ifttt' ); ?></li>
			<li><?php esc_html_e( 'Search for "BookingX" in the services directory', 'bkx-ifttt' ); ?></li>
			<li><?php esc_html_e( 'Connect your BookingX account using the service key above', 'bkx-ifttt' ); ?></li>
			<li><?php esc_html_e( 'Create applets using BookingX triggers and actions', 'bkx-ifttt' ); ?></li>
		</ol>

		<h4><?php esc_html_e( 'Example Applets', 'bkx-ifttt' ); ?></h4>
		<ul>
			<li><?php esc_html_e( 'New booking → Add event to Google Calendar', 'bkx-ifttt' ); ?></li>
			<li><?php esc_html_e( 'Booking cancelled → Send Slack notification', 'bkx-ifttt' ); ?></li>
			<li><?php esc_html_e( 'Payment received → Log to Google Sheets', 'bkx-ifttt' ); ?></li>
			<li><?php esc_html_e( 'Email received → Create booking in BookingX', 'bkx-ifttt' ); ?></li>
		</ul>
	</div>
</div>
