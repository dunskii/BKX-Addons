<?php
/**
 * IFTTT Webhooks Tab.
 *
 * @package BookingX\IFTTT
 */

defined( 'ABSPATH' ) || exit;

$addon           = \BookingX\IFTTT\IFTTTAddon::get_instance();
$webhook_manager = $addon->get_service( 'webhook_manager' );
$trigger_handler = $addon->get_service( 'trigger_handler' );
$webhooks        = $webhook_manager ? $webhook_manager->get_webhooks() : array();
$triggers        = $trigger_handler ? $trigger_handler->get_triggers() : array();
$stats           = $webhook_manager ? $webhook_manager->get_stats() : array();
?>

<div class="bkx-ifttt-webhooks">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Webhook Management', 'bkx-ifttt' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Webhooks allow you to send booking events to external services in real-time.', 'bkx-ifttt' ); ?>
		</p>
	</div>

	<!-- Stats Cards -->
	<div class="bkx-webhook-stats">
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( $stats['total_webhooks'] ?? 0 ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Total Webhooks', 'bkx-ifttt' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( $stats['active_webhooks'] ?? 0 ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Active', 'bkx-ifttt' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( $stats['total_sent'] ?? 0 ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Delivered', 'bkx-ifttt' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( ( $stats['success_rate'] ?? 100 ) . '%' ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Success Rate', 'bkx-ifttt' ); ?></span>
		</div>
	</div>

	<!-- Add Webhook Form -->
	<div class="bkx-add-webhook">
		<h3><?php esc_html_e( 'Add New Webhook', 'bkx-ifttt' ); ?></h3>
		<form id="bkx-add-webhook-form" method="post">
			<?php wp_nonce_field( 'bkx_ifttt_webhook', 'bkx_ifttt_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="webhook-url"><?php esc_html_e( 'Webhook URL', 'bkx-ifttt' ); ?></label>
					</th>
					<td>
						<input type="url" id="webhook-url" name="url" class="regular-text"
							   placeholder="https://example.com/webhook" required>
						<p class="description">
							<?php esc_html_e( 'The URL to receive webhook notifications.', 'bkx-ifttt' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="webhook-trigger"><?php esc_html_e( 'Trigger', 'bkx-ifttt' ); ?></label>
					</th>
					<td>
						<select id="webhook-trigger" name="trigger" required>
							<option value=""><?php esc_html_e( '-- Select Trigger --', 'bkx-ifttt' ); ?></option>
							<?php foreach ( $triggers as $slug => $trigger ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>">
									<?php echo esc_html( $trigger['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select which event should trigger this webhook.', 'bkx-ifttt' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Add Webhook', 'bkx-ifttt' ); ?>
				</button>
				<span class="spinner"></span>
			</p>
		</form>
	</div>

	<!-- Webhooks List -->
	<div class="bkx-webhooks-list">
		<h3><?php esc_html_e( 'Registered Webhooks', 'bkx-ifttt' ); ?></h3>
		<?php if ( empty( $webhooks ) ) : ?>
			<p class="description"><?php esc_html_e( 'No webhooks registered yet.', 'bkx-ifttt' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" id="bkx-webhooks-table">
				<thead>
					<tr>
						<th class="column-status"><?php esc_html_e( 'Status', 'bkx-ifttt' ); ?></th>
						<th class="column-url"><?php esc_html_e( 'URL', 'bkx-ifttt' ); ?></th>
						<th class="column-trigger"><?php esc_html_e( 'Trigger', 'bkx-ifttt' ); ?></th>
						<th class="column-stats"><?php esc_html_e( 'Stats', 'bkx-ifttt' ); ?></th>
						<th class="column-last-sent"><?php esc_html_e( 'Last Sent', 'bkx-ifttt' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'bkx-ifttt' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $webhooks as $id => $webhook ) : ?>
						<tr data-webhook-id="<?php echo esc_attr( $id ); ?>">
							<td class="column-status">
								<label class="bkx-toggle bkx-toggle-small">
									<input type="checkbox" class="bkx-webhook-toggle"
										   data-webhook-id="<?php echo esc_attr( $id ); ?>"
										   <?php checked( $webhook['active'] ); ?>>
									<span class="bkx-toggle-slider"></span>
								</label>
							</td>
							<td class="column-url">
								<code class="bkx-webhook-url"><?php echo esc_url( $webhook['url'] ); ?></code>
							</td>
							<td class="column-trigger">
								<span class="bkx-trigger-badge">
									<?php
									$trigger_name = isset( $triggers[ $webhook['trigger'] ] )
										? $triggers[ $webhook['trigger'] ]['name']
										: $webhook['trigger'];
									echo esc_html( $trigger_name );
									?>
								</span>
							</td>
							<td class="column-stats">
								<span class="bkx-stat-sent" title="<?php esc_attr_e( 'Sent', 'bkx-ifttt' ); ?>">
									<?php echo esc_html( $webhook['send_count'] ?? 0 ); ?>
								</span>
								/
								<span class="bkx-stat-failed" title="<?php esc_attr_e( 'Failed', 'bkx-ifttt' ); ?>">
									<?php echo esc_html( $webhook['fail_count'] ?? 0 ); ?>
								</span>
							</td>
							<td class="column-last-sent">
								<?php
								echo $webhook['last_sent']
									? esc_html( human_time_diff( strtotime( $webhook['last_sent'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'bkx-ifttt' ) )
									: '<em>' . esc_html__( 'Never', 'bkx-ifttt' ) . '</em>';
								?>
							</td>
							<td class="column-actions">
								<button type="button" class="button button-small bkx-test-webhook"
										data-webhook-id="<?php echo esc_attr( $id ); ?>"
										title="<?php esc_attr_e( 'Test Webhook', 'bkx-ifttt' ); ?>">
									<span class="dashicons dashicons-controls-play"></span>
								</button>
								<button type="button" class="button button-small bkx-view-secret"
										data-secret="<?php echo esc_attr( $webhook['secret'] ?? '' ); ?>"
										title="<?php esc_attr_e( 'View Secret', 'bkx-ifttt' ); ?>">
									<span class="dashicons dashicons-lock"></span>
								</button>
								<button type="button" class="button button-small bkx-delete-webhook"
										data-webhook-id="<?php echo esc_attr( $id ); ?>"
										title="<?php esc_attr_e( 'Delete Webhook', 'bkx-ifttt' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- Documentation -->
	<div class="bkx-webhook-docs">
		<h3><?php esc_html_e( 'Webhook Payload Format', 'bkx-ifttt' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Webhooks are sent as POST requests with JSON payload and the following headers:', 'bkx-ifttt' ); ?>
		</p>
		<pre class="bkx-code-block">
Content-Type: application/json
X-BKX-IFTTT-Signature: {hmac-sha256-signature}
X-BKX-IFTTT-Timestamp: {unix-timestamp}
X-BKX-IFTTT-Event: {trigger-slug}</pre>

		<p class="description">
			<?php esc_html_e( 'Verify the signature using HMAC-SHA256 with your webhook secret:', 'bkx-ifttt' ); ?>
		</p>
		<pre class="bkx-code-block">
expected = hmac_sha256(request_body, webhook_secret)
if (expected == X-BKX-IFTTT-Signature) {
    // Valid request
}</pre>
	</div>
</div>

<!-- Secret Modal -->
<div id="bkx-secret-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-small">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Webhook Secret', 'bkx-ifttt' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<p class="description">
				<?php esc_html_e( 'Use this secret to verify webhook signatures:', 'bkx-ifttt' ); ?>
			</p>
			<div class="bkx-secret-display">
				<code id="bkx-webhook-secret"></code>
				<button type="button" class="button" id="bkx-copy-secret">
					<span class="dashicons dashicons-clipboard"></span>
				</button>
			</div>
		</div>
	</div>
</div>
