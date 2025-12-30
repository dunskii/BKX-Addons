<?php
/**
 * Delivery log template.
 *
 * @package BookingX\WebhooksManager
 */

defined( 'ABSPATH' ) || exit;

$addon            = \BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
$webhook_manager  = $addon->get_service( 'webhook_manager' );
$delivery_service = $addon->get_service( 'delivery_service' );

// Get filters.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$webhook_id = isset( $_GET['webhook'] ) ? absint( $_GET['webhook'] ) : 0;
$status     = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$event_type = isset( $_GET['event'] ) ? sanitize_text_field( wp_unslash( $_GET['event'] ) ) : '';
$date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
$paged      = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$per_page = 50;
$offset   = ( $paged - 1 ) * $per_page;

// Get deliveries.
$deliveries = $delivery_service->get_all(
	array(
		'webhook_id' => $webhook_id,
		'status'     => $status,
		'event_type' => $event_type,
		'date_from'  => $date_from,
		'date_to'    => $date_to,
		'limit'      => $per_page,
		'offset'     => $offset,
	)
);

// Get all webhooks for filter.
$webhooks = $webhook_manager->get_all( array( 'limit' => 100 ) );

// Get stats.
$stats = $delivery_service->get_stats(
	array(
		'webhook_id' => $webhook_id,
		'date_from'  => $date_from ?: gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
		'date_to'    => $date_to ?: gmdate( 'Y-m-d' ),
	)
);
?>

<div class="bkx-deliveries-header">
	<!-- Stats Cards -->
	<div class="bkx-stats-cards">
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $stats['total'] ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Total', 'bkx-webhooks-manager' ); ?></span>
		</div>
		<div class="bkx-stat-card bkx-stat-success">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $stats['delivered'] ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Delivered', 'bkx-webhooks-manager' ); ?></span>
		</div>
		<div class="bkx-stat-card bkx-stat-error">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $stats['failed'] ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Failed', 'bkx-webhooks-manager' ); ?></span>
		</div>
		<div class="bkx-stat-card bkx-stat-pending">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $stats['pending'] ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Pending', 'bkx-webhooks-manager' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( $stats['avg_response_time'] ); ?> ms</span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Avg Response', 'bkx-webhooks-manager' ); ?></span>
		</div>
	</div>

	<!-- Filters -->
	<form method="get" class="bkx-deliveries-filters">
		<input type="hidden" name="post_type" value="bkx_booking">
		<input type="hidden" name="page" value="bkx-webhooks-manager">
		<input type="hidden" name="tab" value="deliveries">

		<select name="webhook">
			<option value=""><?php esc_html_e( 'All Webhooks', 'bkx-webhooks-manager' ); ?></option>
			<?php foreach ( $webhooks as $webhook ) : ?>
				<option value="<?php echo esc_attr( $webhook->id ); ?>" <?php selected( $webhook_id, $webhook->id ); ?>>
					<?php echo esc_html( $webhook->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select name="status">
			<option value=""><?php esc_html_e( 'All Statuses', 'bkx-webhooks-manager' ); ?></option>
			<option value="delivered" <?php selected( $status, 'delivered' ); ?>><?php esc_html_e( 'Delivered', 'bkx-webhooks-manager' ); ?></option>
			<option value="failed" <?php selected( $status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'bkx-webhooks-manager' ); ?></option>
			<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'bkx-webhooks-manager' ); ?></option>
		</select>

		<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From', 'bkx-webhooks-manager' ); ?>">
		<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To', 'bkx-webhooks-manager' ); ?>">

		<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'bkx-webhooks-manager' ); ?>">
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-webhooks-manager&tab=deliveries' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'bkx-webhooks-manager' ); ?></a>
	</form>
</div>

<?php if ( empty( $deliveries ) ) : ?>
	<div class="bkx-empty-state">
		<span class="dashicons dashicons-migrate"></span>
		<h2><?php esc_html_e( 'No deliveries found', 'bkx-webhooks-manager' ); ?></h2>
		<p><?php esc_html_e( 'Webhook deliveries will appear here once events are triggered.', 'bkx-webhooks-manager' ); ?></p>
	</div>
<?php else : ?>
	<table class="wp-list-table widefat fixed striped bkx-deliveries-table">
		<thead>
			<tr>
				<th class="column-id"><?php esc_html_e( 'ID', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-webhook"><?php esc_html_e( 'Webhook', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-event"><?php esc_html_e( 'Event', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-status"><?php esc_html_e( 'Status', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-response"><?php esc_html_e( 'Response', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-attempts"><?php esc_html_e( 'Attempts', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-time"><?php esc_html_e( 'Response Time', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-date"><?php esc_html_e( 'Date', 'bkx-webhooks-manager' ); ?></th>
				<th class="column-actions"><?php esc_html_e( 'Actions', 'bkx-webhooks-manager' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $deliveries as $delivery ) : ?>
				<?php
				$webhook = $webhook_manager->get( $delivery->webhook_id );
				$webhook_name = $webhook ? $webhook->name : __( 'Deleted', 'bkx-webhooks-manager' );
				?>
				<tr data-delivery-id="<?php echo esc_attr( $delivery->id ); ?>">
					<td class="column-id">
						<a href="#" class="bkx-view-delivery" data-id="<?php echo esc_attr( $delivery->id ); ?>">
							#<?php echo esc_html( $delivery->id ); ?>
						</a>
					</td>
					<td class="column-webhook">
						<?php echo esc_html( $webhook_name ); ?>
					</td>
					<td class="column-event">
						<code><?php echo esc_html( $delivery->event_type ); ?></code>
					</td>
					<td class="column-status">
						<span class="bkx-status bkx-status-<?php echo esc_attr( $delivery->status ); ?>">
							<?php echo esc_html( ucfirst( $delivery->status ) ); ?>
						</span>
					</td>
					<td class="column-response">
						<?php if ( $delivery->response_code ) : ?>
							<span class="bkx-response-code bkx-response-<?php echo $delivery->response_code >= 200 && $delivery->response_code < 300 ? 'success' : 'error'; ?>">
								<?php echo esc_html( $delivery->response_code ); ?>
							</span>
						<?php elseif ( $delivery->error_message ) : ?>
							<span class="bkx-error-message" title="<?php echo esc_attr( $delivery->error_message ); ?>">
								<?php echo esc_html( substr( $delivery->error_message, 0, 30 ) ); ?>...
							</span>
						<?php else : ?>
							-
						<?php endif; ?>
					</td>
					<td class="column-attempts">
						<?php echo esc_html( $delivery->attempt ); ?>
					</td>
					<td class="column-time">
						<?php if ( $delivery->response_time ) : ?>
							<?php echo esc_html( number_format( $delivery->response_time, 2 ) ); ?> ms
						<?php else : ?>
							-
						<?php endif; ?>
					</td>
					<td class="column-date">
						<?php echo esc_html( wp_date( 'M j, Y H:i', strtotime( $delivery->created_at ) ) ); ?>
					</td>
					<td class="column-actions">
						<button type="button" class="button button-small bkx-view-delivery" data-id="<?php echo esc_attr( $delivery->id ); ?>" title="<?php esc_attr_e( 'View Details', 'bkx-webhooks-manager' ); ?>">
							<span class="dashicons dashicons-visibility"></span>
						</button>
						<?php if ( 'failed' === $delivery->status || 'pending' === $delivery->status ) : ?>
							<button type="button" class="button button-small bkx-retry-delivery" data-id="<?php echo esc_attr( $delivery->id ); ?>" title="<?php esc_attr_e( 'Retry', 'bkx-webhooks-manager' ); ?>">
								<span class="dashicons dashicons-update"></span>
							</button>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<!-- Delivery Detail Modal -->
<div id="bkx-delivery-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-large">
		<div class="bkx-modal-header">
			<h2><?php esc_html_e( 'Delivery Details', 'bkx-webhooks-manager' ); ?></h2>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<div id="bkx-delivery-loading" class="bkx-loading">
				<span class="spinner is-active"></span>
			</div>
			<div id="bkx-delivery-content" style="display: none;">
				<div class="bkx-delivery-tabs">
					<button type="button" class="bkx-delivery-tab active" data-tab="request"><?php esc_html_e( 'Request', 'bkx-webhooks-manager' ); ?></button>
					<button type="button" class="bkx-delivery-tab" data-tab="response"><?php esc_html_e( 'Response', 'bkx-webhooks-manager' ); ?></button>
				</div>

				<div class="bkx-delivery-tab-content active" data-tab="request">
					<h4><?php esc_html_e( 'Request Headers', 'bkx-webhooks-manager' ); ?></h4>
					<pre id="bkx-request-headers"></pre>

					<h4><?php esc_html_e( 'Payload', 'bkx-webhooks-manager' ); ?></h4>
					<pre id="bkx-request-payload"></pre>
				</div>

				<div class="bkx-delivery-tab-content" data-tab="response">
					<h4><?php esc_html_e( 'Response Headers', 'bkx-webhooks-manager' ); ?></h4>
					<pre id="bkx-response-headers"></pre>

					<h4><?php esc_html_e( 'Response Body', 'bkx-webhooks-manager' ); ?></h4>
					<pre id="bkx-response-body"></pre>
				</div>
			</div>
		</div>
		<div class="bkx-modal-footer">
			<button type="button" class="button bkx-modal-close"><?php esc_html_e( 'Close', 'bkx-webhooks-manager' ); ?></button>
		</div>
	</div>
</div>
