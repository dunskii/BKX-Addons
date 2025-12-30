<?php
/**
 * Webhooks admin template.
 *
 * @package BookingX\APIBuilder
 */

defined( 'ABSPATH' ) || exit;

$addon      = \BookingX\APIBuilder\APIBuilderAddon::get_instance();
$dispatcher = $addon->get_service( 'webhook_dispatcher' );
$webhooks   = $dispatcher->get_all();
$events     = $dispatcher->get_available_events();
?>
<div class="bkx-webhooks-page">
	<div class="bkx-page-header">
		<h2><?php esc_html_e( 'Webhooks', 'bkx-api-builder' ); ?></h2>
		<button type="button" class="button button-primary" id="bkx-add-webhook">
			<span class="dashicons dashicons-admin-links"></span>
			<?php esc_html_e( 'Add Webhook', 'bkx-api-builder' ); ?>
		</button>
	</div>

	<?php if ( empty( $webhooks ) ) : ?>
		<div class="bkx-empty-state">
			<span class="dashicons dashicons-admin-links"></span>
			<h3><?php esc_html_e( 'No webhooks configured', 'bkx-api-builder' ); ?></h3>
			<p><?php esc_html_e( 'Add a webhook to send real-time notifications to external services.', 'bkx-api-builder' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'URL', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Events', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Last Triggered', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'bkx-api-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $webhooks as $webhook ) : ?>
					<?php $webhook_events = json_decode( $webhook['events'], true ) ?: array(); ?>
					<tr data-id="<?php echo esc_attr( $webhook['id'] ); ?>">
						<td><strong><?php echo esc_html( $webhook['name'] ); ?></strong></td>
						<td><code><?php echo esc_html( substr( $webhook['url'], 0, 50 ) . ( strlen( $webhook['url'] ) > 50 ? '...' : '' ) ); ?></code></td>
						<td>
							<?php
							$event_count = count( $webhook_events );
							if ( in_array( '*', $webhook_events, true ) ) {
								esc_html_e( 'All events', 'bkx-api-builder' );
							} else {
								echo esc_html( $event_count . ' ' . _n( 'event', 'events', $event_count, 'bkx-api-builder' ) );
							}
							?>
						</td>
						<td>
							<?php
							if ( $webhook['last_triggered_at'] ) {
								echo esc_html( human_time_diff( strtotime( $webhook['last_triggered_at'] ), current_time( 'timestamp' ) ) . ' ago' );
								$code_class = $webhook['last_response_code'] >= 200 && $webhook['last_response_code'] < 300 ? 'success' : 'error';
								echo '<br><span class="bkx-response-code bkx-' . esc_attr( $code_class ) . '">' . esc_html( $webhook['last_response_code'] ) . '</span>';
							} else {
								esc_html_e( 'Never', 'bkx-api-builder' );
							}
							?>
						</td>
						<td>
							<span class="bkx-status bkx-status-<?php echo esc_attr( $webhook['status'] ); ?>">
								<?php echo esc_html( ucfirst( $webhook['status'] ) ); ?>
							</span>
							<?php if ( $webhook['failure_count'] > 0 ) : ?>
								<span class="bkx-failure-badge"><?php echo esc_html( $webhook['failure_count'] ); ?> <?php esc_html_e( 'failures', 'bkx-api-builder' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button bkx-test-webhook" data-id="<?php echo esc_attr( $webhook['id'] ); ?>">
								<?php esc_html_e( 'Test', 'bkx-api-builder' ); ?>
							</button>
							<button type="button" class="button bkx-edit-webhook" data-id="<?php echo esc_attr( $webhook['id'] ); ?>">
								<?php esc_html_e( 'Edit', 'bkx-api-builder' ); ?>
							</button>
							<button type="button" class="button bkx-delete-webhook" data-id="<?php echo esc_attr( $webhook['id'] ); ?>">
								<?php esc_html_e( 'Delete', 'bkx-api-builder' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<!-- Webhook Modal -->
<div id="bkx-webhook-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Webhook Configuration', 'bkx-api-builder' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<form id="bkx-webhook-form">
			<div class="bkx-modal-body">
				<input type="hidden" name="webhook_id" id="webhook_id" value="0">

				<div class="bkx-form-row">
					<label for="webhook_name"><?php esc_html_e( 'Name', 'bkx-api-builder' ); ?></label>
					<input type="text" name="name" id="webhook_name" class="regular-text" required>
				</div>

				<div class="bkx-form-row">
					<label for="webhook_url"><?php esc_html_e( 'Payload URL', 'bkx-api-builder' ); ?></label>
					<input type="url" name="url" id="webhook_url" class="regular-text" required placeholder="https://example.com/webhook">
				</div>

				<div class="bkx-form-row">
					<label><?php esc_html_e( 'Events', 'bkx-api-builder' ); ?></label>
					<div class="bkx-checkbox-group">
						<label>
							<input type="checkbox" name="events[]" value="*" class="bkx-all-events">
							<strong><?php esc_html_e( 'All events', 'bkx-api-builder' ); ?></strong>
						</label>
						<?php foreach ( $events as $event_key => $event_label ) : ?>
							<label>
								<input type="checkbox" name="events[]" value="<?php echo esc_attr( $event_key ); ?>">
								<?php echo esc_html( $event_label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="bkx-form-row bkx-form-row-inline">
					<div>
						<label for="webhook_retry"><?php esc_html_e( 'Retry Count', 'bkx-api-builder' ); ?></label>
						<input type="number" name="retry_count" id="webhook_retry" value="3" min="0" max="10">
					</div>
					<div>
						<label for="webhook_timeout"><?php esc_html_e( 'Timeout (seconds)', 'bkx-api-builder' ); ?></label>
						<input type="number" name="timeout" id="webhook_timeout" value="30" min="5" max="60">
					</div>
				</div>
			</div>
			<div class="bkx-modal-footer">
				<button type="button" class="button bkx-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-api-builder' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Webhook', 'bkx-api-builder' ); ?></button>
			</div>
		</form>
	</div>
</div>
