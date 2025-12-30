<?php
/**
 * Webhooks Management Template.
 *
 * @package BookingX\EnterpriseAPI
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$webhooks = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}bkx_webhooks ORDER BY created_at DESC"
);

$webhook_service = \BookingX\EnterpriseAPI\EnterpriseAPIAddon::get_instance()->get_service( 'webhooks' );
$available_events = $webhook_service->get_available_events();
?>
<div class="bkx-webhooks">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Webhooks', 'bkx-enterprise-api' ); ?></h2>
		<button type="button" class="button button-primary" id="bkx-create-webhook-btn">
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'Create Webhook', 'bkx-enterprise-api' ); ?>
		</button>
	</div>

	<p class="description">
		<?php esc_html_e( 'Webhooks allow you to receive real-time HTTP notifications when events occur in BookingX.', 'bkx-enterprise-api' ); ?>
	</p>

	<!-- Create Webhook Modal -->
	<div id="bkx-create-webhook-modal" class="bkx-modal" style="display: none;">
		<div class="bkx-modal-content bkx-modal-large">
			<span class="bkx-modal-close">&times;</span>
			<h3><?php esc_html_e( 'Create Webhook', 'bkx-enterprise-api' ); ?></h3>
			<form id="bkx-create-webhook-form">
				<table class="form-table">
					<tr>
						<th><label for="webhook_name"><?php esc_html_e( 'Name', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<input type="text" name="name" id="webhook_name" class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th><label for="webhook_url"><?php esc_html_e( 'Endpoint URL', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<input type="url" name="url" id="webhook_url" class="large-text" required placeholder="https://your-app.com/webhooks/bookingx">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Events', 'bkx-enterprise-api' ); ?></th>
						<td>
							<label style="margin-bottom: 10px; display: block;">
								<input type="checkbox" name="events[]" value="*" id="all_events">
								<strong><?php esc_html_e( 'All Events', 'bkx-enterprise-api' ); ?></strong>
							</label>
							<div class="bkx-events-grid">
								<?php foreach ( $available_events as $event => $label ) : ?>
									<label>
										<input type="checkbox" name="events[]" value="<?php echo esc_attr( $event ); ?>" class="bkx-event-checkbox">
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</td>
					</tr>
					<tr>
						<th><label for="retry_count"><?php esc_html_e( 'Retry Count', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<input type="number" name="retry_count" id="retry_count" value="3" min="0" max="10" class="small-text">
							<p class="description"><?php esc_html_e( 'Number of retry attempts for failed deliveries.', 'bkx-enterprise-api' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="timeout_seconds"><?php esc_html_e( 'Timeout', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<input type="number" name="timeout_seconds" id="timeout_seconds" value="30" min="5" max="60" class="small-text">
							<span><?php esc_html_e( 'seconds', 'bkx-enterprise-api' ); ?></span>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Webhook', 'bkx-enterprise-api' ); ?></button>
					<button type="button" class="button bkx-modal-close"><?php esc_html_e( 'Cancel', 'bkx-enterprise-api' ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<!-- Webhooks Table -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 200px;"><?php esc_html_e( 'Name', 'bkx-enterprise-api' ); ?></th>
				<th><?php esc_html_e( 'URL', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 150px;"><?php esc_html_e( 'Events', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 120px;"><?php esc_html_e( 'Deliveries (24h)', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Status', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 150px;"><?php esc_html_e( 'Actions', 'bkx-enterprise-api' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $webhooks ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No webhooks configured.', 'bkx-enterprise-api' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $webhooks as $webhook ) : ?>
					<?php
					$events = json_decode( $webhook->events, true ) ?: array();
					$recent_deliveries = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_webhook_deliveries WHERE webhook_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
							$webhook->id
						)
					);
					$recent_failures = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_webhook_deliveries WHERE webhook_id = %d AND status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
							$webhook->id
						)
					);
					?>
					<tr data-webhook-id="<?php echo esc_attr( $webhook->id ); ?>">
						<td><strong><?php echo esc_html( $webhook->name ); ?></strong></td>
						<td>
							<code class="bkx-url-truncate"><?php echo esc_html( $webhook->url ); ?></code>
						</td>
						<td>
							<?php if ( in_array( '*', $events, true ) ) : ?>
								<span class="bkx-permission-badge">All Events</span>
							<?php else : ?>
								<span class="bkx-permission-badge"><?php echo esc_html( count( $events ) ); ?> events</span>
							<?php endif; ?>
						</td>
						<td>
							<span class="bkx-delivery-count"><?php echo esc_html( $recent_deliveries ); ?></span>
							<?php if ( $recent_failures > 0 ) : ?>
								<span class="bkx-failure-count">(<?php echo esc_html( $recent_failures ); ?> failed)</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $webhook->is_active ) : ?>
								<span class="bkx-status-badge bkx-status-active"><?php esc_html_e( 'Active', 'bkx-enterprise-api' ); ?></span>
							<?php else : ?>
								<span class="bkx-status-badge bkx-status-inactive"><?php esc_html_e( 'Inactive', 'bkx-enterprise-api' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button button-small bkx-test-webhook" data-id="<?php echo esc_attr( $webhook->id ); ?>">
								<?php esc_html_e( 'Test', 'bkx-enterprise-api' ); ?>
							</button>
							<button type="button" class="button button-small bkx-delete-webhook" data-id="<?php echo esc_attr( $webhook->id ); ?>">
								<?php esc_html_e( 'Delete', 'bkx-enterprise-api' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Webhook Payload Example -->
	<div class="bkx-webhook-example">
		<h3><?php esc_html_e( 'Example Payload', 'bkx-enterprise-api' ); ?></h3>
		<pre><code>{
  "event": "booking.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "id": 123,
    "status": "pending",
    "customer_name": "John Doe",
    "customer_email": "john@example.com",
    "service_id": 45,
    "staff_id": 12,
    "booking_date": "2024-01-20",
    "booking_time": "14:00",
    "total": 75.00
  }
}</code></pre>
		<p class="description">
			<?php esc_html_e( 'Webhooks include an X-BookingX-Signature header for verification using HMAC SHA256.', 'bkx-enterprise-api' ); ?>
		</p>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var modal = document.getElementById('bkx-create-webhook-modal');

	document.getElementById('bkx-create-webhook-btn').addEventListener('click', function() {
		modal.style.display = 'flex';
	});

	document.querySelectorAll('.bkx-modal-close').forEach(function(btn) {
		btn.addEventListener('click', function() {
			modal.style.display = 'none';
		});
	});

	// All events checkbox.
	document.getElementById('all_events').addEventListener('change', function() {
		document.querySelectorAll('.bkx-event-checkbox').forEach(function(cb) {
			cb.checked = false;
			cb.disabled = document.getElementById('all_events').checked;
		});
	});

	// Create webhook.
	document.getElementById('bkx-create-webhook-form').addEventListener('submit', function(e) {
		e.preventDefault();
		var formData = new FormData(this);

		wp.apiFetch({
			path: '/bookingx/v1/webhooks',
			method: 'POST',
			data: {
				name: formData.get('name'),
				url: formData.get('url'),
				events: formData.getAll('events[]'),
				retry_count: formData.get('retry_count'),
				timeout_seconds: formData.get('timeout_seconds')
			}
		}).then(function(response) {
			alert('<?php esc_html_e( 'Webhook created! Secret: ', 'bkx-enterprise-api' ); ?>' + response.secret);
			location.reload();
		}).catch(function(error) {
			alert(error.message || 'Error creating webhook');
		});
	});

	// Test webhook.
	document.querySelectorAll('.bkx-test-webhook').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = this.dataset.id;
			var that = this;
			that.disabled = true;
			that.textContent = '<?php esc_html_e( 'Testing...', 'bkx-enterprise-api' ); ?>';

			wp.apiFetch({
				path: '/bookingx/v1/webhooks/' + id + '/test',
				method: 'POST'
			}).then(function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Test successful! Response: ', 'bkx-enterprise-api' ); ?>' + response.response_code);
				} else {
					alert('<?php esc_html_e( 'Test failed: ', 'bkx-enterprise-api' ); ?>' + response.response_code);
				}
			}).catch(function(error) {
				alert(error.message || 'Error testing webhook');
			}).finally(function() {
				that.disabled = false;
				that.textContent = '<?php esc_html_e( 'Test', 'bkx-enterprise-api' ); ?>';
			});
		});
	});

	// Delete webhook.
	document.querySelectorAll('.bkx-delete-webhook').forEach(function(btn) {
		btn.addEventListener('click', function() {
			if (!confirm('<?php esc_html_e( 'Delete this webhook?', 'bkx-enterprise-api' ); ?>')) {
				return;
			}

			var id = this.dataset.id;

			wp.apiFetch({
				path: '/bookingx/v1/webhooks/' + id,
				method: 'DELETE'
			}).then(function() {
				document.querySelector('tr[data-webhook-id="' + id + '"]').remove();
			}).catch(function(error) {
				alert(error.message || 'Error deleting webhook');
			});
		});
	});
});
</script>
