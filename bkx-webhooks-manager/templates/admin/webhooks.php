<?php
/**
 * Webhooks list/management template.
 *
 * @package BookingX\WebhooksManager
 */

defined( 'ABSPATH' ) || exit;

$addon           = \BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
$webhook_manager = $addon->get_service( 'webhook_manager' );
$event_dispatcher = $addon->get_service( 'event_dispatcher' );

// Get filters.
$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$paged  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$per_page = 20;
$offset   = ( $paged - 1 ) * $per_page;

// Get webhooks.
$webhooks = $webhook_manager->get_all(
	array(
		'status'  => $status,
		'search'  => $search,
		'limit'   => $per_page,
		'offset'  => $offset,
		'orderby' => 'created_at',
		'order'   => 'DESC',
	)
);

$total_webhooks = $webhook_manager->get_count( $status );
$total_pages    = ceil( $total_webhooks / $per_page );

// Get available events.
$available_events = $event_dispatcher->get_available_events();

// Status counts.
$counts = array(
	'all'    => $webhook_manager->get_count(),
	'active' => $webhook_manager->get_count( 'active' ),
	'paused' => $webhook_manager->get_count( 'paused' ),
);
?>

<div class="bkx-webhooks-header">
	<div class="bkx-webhooks-actions">
		<button type="button" class="button button-primary" id="bkx-add-webhook">
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'Add Webhook', 'bkx-webhooks-manager' ); ?>
		</button>
	</div>

	<div class="bkx-webhooks-filters">
		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( remove_query_arg( 'status' ) ); ?>" class="<?php echo empty( $status ) ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'bkx-webhooks-manager' ); ?>
					<span class="count">(<?php echo esc_html( $counts['all'] ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'status', 'active' ) ); ?>" class="<?php echo 'active' === $status ? 'current' : ''; ?>">
					<?php esc_html_e( 'Active', 'bkx-webhooks-manager' ); ?>
					<span class="count">(<?php echo esc_html( $counts['active'] ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'status', 'paused' ) ); ?>" class="<?php echo 'paused' === $status ? 'current' : ''; ?>">
					<?php esc_html_e( 'Paused', 'bkx-webhooks-manager' ); ?>
					<span class="count">(<?php echo esc_html( $counts['paused'] ); ?>)</span>
				</a>
			</li>
		</ul>

		<form method="get" class="search-form">
			<input type="hidden" name="post_type" value="bkx_booking">
			<input type="hidden" name="page" value="bkx-webhooks-manager">
			<?php if ( $status ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
			<?php endif; ?>
			<p class="search-box">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search webhooks...', 'bkx-webhooks-manager' ); ?>">
				<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'bkx-webhooks-manager' ); ?>">
			</p>
		</form>
	</div>
</div>

<?php if ( empty( $webhooks ) ) : ?>
	<div class="bkx-empty-state">
		<span class="dashicons dashicons-rest-api"></span>
		<h2><?php esc_html_e( 'No webhooks found', 'bkx-webhooks-manager' ); ?></h2>
		<p><?php esc_html_e( 'Create your first webhook to start sending event notifications to external services.', 'bkx-webhooks-manager' ); ?></p>
		<button type="button" class="button button-primary" id="bkx-add-webhook-empty">
			<?php esc_html_e( 'Create Webhook', 'bkx-webhooks-manager' ); ?>
		</button>
	</div>
<?php else : ?>
	<table class="wp-list-table widefat fixed striped bkx-webhooks-table">
		<thead>
			<tr>
				<th class="column-name"><?php esc_html_e( 'Name', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-url"><?php esc_html_e( 'URL', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-events"><?php esc_html_e( 'Events', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-status"><?php esc_html_e( 'Status', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-stats"><?php esc_html_e( 'Success/Fail', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-last-triggered"><?php esc_html_e( 'Last Triggered', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-actions"><?php esc_html_e( 'Actions', 'bkx-webhooks-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $webhooks as $webhook ) : ?>
				<tr data-webhook-id="<?php echo esc_attr( $webhook->id ); ?>">
					<td class="column-name">
						<strong>
							<a href="#" class="bkx-edit-webhook" data-id="<?php echo esc_attr( $webhook->id ); ?>">
								<?php echo esc_html( $webhook->name ); ?>
							</a>
						</strong>
					</td>
					<td class="column-url">
						<code title="<?php echo esc_attr( $webhook->url ); ?>">
							<?php echo esc_html( strlen( $webhook->url ) > 50 ? substr( $webhook->url, 0, 50 ) . '...' : $webhook->url ); ?>
						</code>
					</td>
					<td class="column-events">
						<?php
						$event_count = count( $webhook->events );
						if ( $event_count <= 2 ) {
							echo esc_html( implode( ', ', $webhook->events ) );
						} else {
							echo esc_html( $webhook->events[0] ) . ', ';
							printf(
								/* translators: %d: number of additional events */
								esc_html__( '+%d more', 'bkx-webhooks-manager' ),
								$event_count - 1
							);
						}
						?>
					</td>
					<td class="column-status">
						<span class="bkx-status bkx-status-<?php echo esc_attr( $webhook->status ); ?>">
							<?php echo esc_html( ucfirst( $webhook->status ) ); ?>
						</span>
					</td>
					<td class="column-stats">
						<span class="bkx-stat-success" title="<?php esc_attr_e( 'Successful deliveries', 'bkx-webhooks-manager' ); ?>">
							<?php echo esc_html( number_format( $webhook->success_count ) ); ?>
						</span>
						/
						<span class="bkx-stat-fail" title="<?php esc_attr_e( 'Failed deliveries', 'bkx-webhooks-manager' ); ?>">
							<?php echo esc_html( number_format( $webhook->failure_count ) ); ?>
						</span>
					</td>
					<td class="column-last-triggered">
						<?php
						if ( $webhook->last_triggered_at ) {
							$time_diff = human_time_diff( strtotime( $webhook->last_triggered_at ), current_time( 'timestamp' ) );
							printf(
								/* translators: %s: human readable time difference */
								esc_html__( '%s ago', 'bkx-webhooks-manager' ),
								esc_html( $time_diff )
							);
							if ( $webhook->last_response_code ) {
								$code_class = $webhook->last_response_code >= 200 && $webhook->last_response_code < 300 ? 'success' : 'error';
								echo ' <span class="bkx-response-code bkx-response-' . esc_attr( $code_class ) . '">' . esc_html( $webhook->last_response_code ) . '</span>';
							}
						} else {
							echo '<span class="bkx-never">' . esc_html__( 'Never', 'bkx-webhooks-manager' ) . '</span>';
						}
						?>
					</td>
					<td class="column-actions">
						<button type="button" class="button button-small bkx-test-webhook" data-id="<?php echo esc_attr( $webhook->id ); ?>" title="<?php esc_attr_e( 'Send test', 'bkx-webhooks-manager' ); ?>">
							<span class="dashicons dashicons-controls-play"></span>
						</button>
						<button type="button" class="button button-small bkx-toggle-webhook" data-id="<?php echo esc_attr( $webhook->id ); ?>" title="<?php echo 'active' === $webhook->status ? esc_attr__( 'Pause', 'bkx-webhooks-manager' ) : esc_attr__( 'Activate', 'bkx-webhooks-manager' ); ?>">
							<span class="dashicons dashicons-<?php echo 'active' === $webhook->status ? 'controls-pause' : 'controls-play'; ?>"></span>
						</button>
						<button type="button" class="button button-small bkx-edit-webhook" data-id="<?php echo esc_attr( $webhook->id ); ?>" title="<?php esc_attr_e( 'Edit', 'bkx-webhooks-manager' ); ?>">
							<span class="dashicons dashicons-edit"></span>
						</button>
						<button type="button" class="button button-small bkx-delete-webhook" data-id="<?php echo esc_attr( $webhook->id ); ?>" title="<?php esc_attr_e( 'Delete', 'bkx-webhooks-manager' ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				$pagination_args = array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'total'     => $total_pages,
					'current'   => $paged,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				);
				echo wp_kses_post( paginate_links( $pagination_args ) );
				?>
			</div>
		</div>
	<?php endif; ?>
<?php endif; ?>

<!-- Webhook Modal -->
<div id="bkx-webhook-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h2 id="bkx-modal-title"><?php esc_html_e( 'Add Webhook', 'bkx-webhooks-manager' ); ?></h2>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<form id="bkx-webhook-form">
			<input type="hidden" name="webhook_id" id="webhook_id" value="">
			<?php wp_nonce_field( 'bkx_webhooks_nonce', 'bkx_nonce' ); ?>

			<div class="bkx-modal-body">
				<div class="bkx-form-tabs">
					<button type="button" class="bkx-form-tab active" data-tab="general"><?php esc_html_e( 'General', 'bkx-webhooks-manager' ); ?></button>
					<button type="button" class="bkx-form-tab" data-tab="events"><?php esc_html_e( 'Events', 'bkx-webhooks-manager' ); ?></button>
					<button type="button" class="bkx-form-tab" data-tab="delivery"><?php esc_html_e( 'Delivery', 'bkx-webhooks-manager' ); ?></button>
					<button type="button" class="bkx-form-tab" data-tab="advanced"><?php esc_html_e( 'Advanced', 'bkx-webhooks-manager' ); ?></button>
				</div>

				<!-- General Tab -->
				<div class="bkx-form-tab-content active" data-tab="general">
					<table class="form-table">
						<tr>
							<th><label for="webhook_name"><?php esc_html_e( 'Name', 'bkx-webhooks-manager' ); ?> <span class="required">*</span></label></th>
							<td>
								<input type="text" id="webhook_name" name="name" class="regular-text" required>
								<p class="description"><?php esc_html_e( 'A friendly name to identify this webhook.', 'bkx-webhooks-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="webhook_url"><?php esc_html_e( 'Endpoint URL', 'bkx-webhooks-manager' ); ?> <span class="required">*</span></label></th>
							<td>
								<input type="url" id="webhook_url" name="url" class="large-text" required placeholder="https://example.com/webhook">
								<p class="description"><?php esc_html_e( 'The URL where webhook payloads will be sent.', 'bkx-webhooks-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="webhook_status"><?php esc_html_e( 'Status', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<select id="webhook_status" name="status">
									<option value="active"><?php esc_html_e( 'Active', 'bkx-webhooks-manager' ); ?></option>
									<option value="paused"><?php esc_html_e( 'Paused', 'bkx-webhooks-manager' ); ?></option>
								</select>
							</td>
						</tr>
						<tr class="bkx-secret-row">
							<th><label><?php esc_html_e( 'Secret', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<code id="webhook_secret_display">-</code>
								<button type="button" class="button button-small" id="bkx-regenerate-secret"><?php esc_html_e( 'Regenerate', 'bkx-webhooks-manager' ); ?></button>
								<p class="description"><?php esc_html_e( 'Used to sign payloads for verification.', 'bkx-webhooks-manager' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Events Tab -->
				<div class="bkx-form-tab-content" data-tab="events">
					<p class="description"><?php esc_html_e( 'Select which events should trigger this webhook:', 'bkx-webhooks-manager' ); ?></p>

					<?php foreach ( $available_events as $category => $events ) : ?>
						<div class="bkx-event-category">
							<h4>
								<label>
									<input type="checkbox" class="bkx-category-toggle" data-category="<?php echo esc_attr( $category ); ?>">
									<?php echo esc_html( ucfirst( $category ) ); ?>
								</label>
							</h4>
							<div class="bkx-event-list">
								<?php foreach ( $events as $event_key => $event ) : ?>
									<label class="bkx-event-item">
										<input type="checkbox" name="events[]" value="<?php echo esc_attr( $event_key ); ?>" data-category="<?php echo esc_attr( $category ); ?>">
										<span class="bkx-event-label"><?php echo esc_html( $event['label'] ); ?></span>
										<span class="bkx-event-desc"><?php echo esc_html( $event['description'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<!-- Delivery Tab -->
				<div class="bkx-form-tab-content" data-tab="delivery">
					<table class="form-table">
						<tr>
							<th><label for="webhook_method"><?php esc_html_e( 'HTTP Method', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<select id="webhook_method" name="http_method">
									<option value="POST">POST</option>
									<option value="PUT">PUT</option>
									<option value="PATCH">PATCH</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="webhook_format"><?php esc_html_e( 'Payload Format', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<select id="webhook_format" name="payload_format">
									<option value="json">JSON</option>
									<option value="form">Form Data</option>
									<option value="xml">XML</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="webhook_timeout"><?php esc_html_e( 'Timeout (seconds)', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<input type="number" id="webhook_timeout" name="timeout" value="30" min="5" max="120" class="small-text">
							</td>
						</tr>
						<tr>
							<th><label for="webhook_retry_count"><?php esc_html_e( 'Retry Count', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<input type="number" id="webhook_retry_count" name="retry_count" value="3" min="0" max="10" class="small-text">
								<p class="description"><?php esc_html_e( 'Number of retry attempts for failed deliveries.', 'bkx-webhooks-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="webhook_retry_delay"><?php esc_html_e( 'Retry Delay (seconds)', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<input type="number" id="webhook_retry_delay" name="retry_delay" value="60" min="30" max="3600" class="small-text">
							</td>
						</tr>
						<tr>
							<th><label for="webhook_verify_ssl"><?php esc_html_e( 'Verify SSL', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="webhook_verify_ssl" name="verify_ssl" value="1" checked>
									<?php esc_html_e( 'Verify SSL certificate (recommended)', 'bkx-webhooks-manager' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<!-- Advanced Tab -->
				<div class="bkx-form-tab-content" data-tab="advanced">
					<table class="form-table">
						<tr>
							<th><label for="webhook_headers"><?php esc_html_e( 'Custom Headers', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<div id="webhook_headers_container">
									<div class="bkx-header-row">
										<input type="text" name="header_key[]" placeholder="<?php esc_attr_e( 'Header Name', 'bkx-webhooks-manager' ); ?>" class="regular-text">
										<input type="text" name="header_value[]" placeholder="<?php esc_attr_e( 'Header Value', 'bkx-webhooks-manager' ); ?>" class="regular-text">
										<button type="button" class="button bkx-remove-header">&times;</button>
									</div>
								</div>
								<button type="button" class="button" id="bkx-add-header"><?php esc_html_e( 'Add Header', 'bkx-webhooks-manager' ); ?></button>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Active Time Window', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<input type="time" id="webhook_start_time" name="active_start_time" class="small-text">
								<?php esc_html_e( 'to', 'bkx-webhooks-manager' ); ?>
								<input type="time" id="webhook_end_time" name="active_end_time" class="small-text">
								<p class="description"><?php esc_html_e( 'Leave empty to send at any time.', 'bkx-webhooks-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Active Days', 'bkx-webhooks-manager' ); ?></label></th>
							<td>
								<?php
								$days = array(
									'monday'    => __( 'Mon', 'bkx-webhooks-manager' ),
									'tuesday'   => __( 'Tue', 'bkx-webhooks-manager' ),
									'wednesday' => __( 'Wed', 'bkx-webhooks-manager' ),
									'thursday'  => __( 'Thu', 'bkx-webhooks-manager' ),
									'friday'    => __( 'Fri', 'bkx-webhooks-manager' ),
									'saturday'  => __( 'Sat', 'bkx-webhooks-manager' ),
									'sunday'    => __( 'Sun', 'bkx-webhooks-manager' ),
								);
								foreach ( $days as $value => $label ) :
									?>
									<label class="bkx-day-checkbox">
										<input type="checkbox" name="active_days[]" value="<?php echo esc_attr( $value ); ?>">
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Leave empty to send any day.', 'bkx-webhooks-manager' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="bkx-modal-footer">
				<button type="button" class="button bkx-modal-close"><?php esc_html_e( 'Cancel', 'bkx-webhooks-manager' ); ?></button>
				<button type="submit" class="button button-primary" id="bkx-save-webhook"><?php esc_html_e( 'Save Webhook', 'bkx-webhooks-manager' ); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- Test Result Modal -->
<div id="bkx-test-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-small">
		<div class="bkx-modal-header">
			<h2><?php esc_html_e( 'Test Webhook', 'bkx-webhooks-manager' ); ?></h2>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<div id="bkx-test-loading" class="bkx-test-loading">
				<span class="spinner is-active"></span>
				<p><?php esc_html_e( 'Sending test webhook...', 'bkx-webhooks-manager' ); ?></p>
			</div>
			<div id="bkx-test-result" style="display: none;">
				<div class="bkx-test-status"></div>
				<table class="bkx-test-details">
					<tr>
						<th><?php esc_html_e( 'Response Code:', 'bkx-webhooks-manager' ); ?></th>
						<td id="bkx-test-code"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Response Time:', 'bkx-webhooks-manager' ); ?></th>
						<td id="bkx-test-time"></td>
					</tr>
					<tr id="bkx-test-error-row" style="display: none;">
						<th><?php esc_html_e( 'Error:', 'bkx-webhooks-manager' ); ?></th>
						<td id="bkx-test-error"></td>
					</tr>
				</table>
			</div>
		</div>
		<div class="bkx-modal-footer">
			<button type="button" class="button bkx-modal-close"><?php esc_html_e( 'Close', 'bkx-webhooks-manager' ); ?></button>
		</div>
	</div>
</div>
