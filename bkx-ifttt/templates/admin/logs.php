<?php
/**
 * IFTTT Logs Tab.
 *
 * @package BookingX\IFTTT
 */

defined( 'ABSPATH' ) || exit;

$addon           = \BookingX\IFTTT\IFTTTAddon::get_instance();
$webhook_manager = $addon->get_service( 'webhook_manager' );

// Get log type filter.
$log_type = isset( $_GET['log_type'] ) ? sanitize_text_field( wp_unslash( $_GET['log_type'] ) ) : 'all';

// Get logs based on type.
$trigger_logs = get_option( 'bkx_ifttt_trigger_logs', array() );
$action_logs  = get_option( 'bkx_ifttt_action_logs', array() );
$webhook_logs = get_option( 'bkx_ifttt_webhook_logs', array() );
$error_logs   = get_option( 'bkx_ifttt_error_logs', array() );

// Merge and sort logs if showing all.
$all_logs = array();
if ( 'all' === $log_type || 'triggers' === $log_type ) {
	foreach ( $trigger_logs as $log ) {
		$log['type'] = 'trigger';
		$all_logs[]  = $log;
	}
}
if ( 'all' === $log_type || 'actions' === $log_type ) {
	foreach ( $action_logs as $log ) {
		$log['type'] = 'action';
		$all_logs[]  = $log;
	}
}
if ( 'all' === $log_type || 'webhooks' === $log_type ) {
	foreach ( $webhook_logs as $log ) {
		$log['type'] = 'webhook';
		$all_logs[]  = $log;
	}
}
if ( 'all' === $log_type || 'errors' === $log_type ) {
	foreach ( $error_logs as $log ) {
		$log['type'] = 'error';
		$all_logs[]  = $log;
	}
}

// Sort by timestamp descending.
usort(
	$all_logs,
	function ( $a, $b ) {
		return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
	}
);

// Limit to 100 entries.
$all_logs = array_slice( $all_logs, 0, 100 );
?>

<div class="bkx-ifttt-logs">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Request Logs', 'bkx-ifttt' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'View recent IFTTT API requests, triggers, actions, and webhook deliveries.', 'bkx-ifttt' ); ?>
		</p>
	</div>

	<!-- Log Type Filter -->
	<div class="bkx-log-filters">
		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'log_type', 'all' ) ); ?>"
				   class="<?php echo 'all' === $log_type ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'bkx-ifttt' ); ?>
					<span class="count">(<?php echo esc_html( count( $trigger_logs ) + count( $action_logs ) + count( $webhook_logs ) + count( $error_logs ) ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'log_type', 'triggers' ) ); ?>"
				   class="<?php echo 'triggers' === $log_type ? 'current' : ''; ?>">
					<?php esc_html_e( 'Triggers', 'bkx-ifttt' ); ?>
					<span class="count">(<?php echo esc_html( count( $trigger_logs ) ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'log_type', 'actions' ) ); ?>"
				   class="<?php echo 'actions' === $log_type ? 'current' : ''; ?>">
					<?php esc_html_e( 'Actions', 'bkx-ifttt' ); ?>
					<span class="count">(<?php echo esc_html( count( $action_logs ) ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'log_type', 'webhooks' ) ); ?>"
				   class="<?php echo 'webhooks' === $log_type ? 'current' : ''; ?>">
					<?php esc_html_e( 'Webhooks', 'bkx-ifttt' ); ?>
					<span class="count">(<?php echo esc_html( count( $webhook_logs ) ); ?>)</span>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( add_query_arg( 'log_type', 'errors' ) ); ?>"
				   class="<?php echo 'errors' === $log_type ? 'current' : ''; ?>">
					<?php esc_html_e( 'Errors', 'bkx-ifttt' ); ?>
					<span class="count">(<?php echo esc_html( count( $error_logs ) ); ?>)</span>
				</a>
			</li>
		</ul>

		<div class="bkx-log-actions">
			<button type="button" class="button" id="bkx-refresh-logs">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh', 'bkx-ifttt' ); ?>
			</button>
			<button type="button" class="button" id="bkx-clear-logs">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Clear Logs', 'bkx-ifttt' ); ?>
			</button>
		</div>
	</div>

	<!-- Logs Table -->
	<?php if ( empty( $all_logs ) ) : ?>
		<div class="bkx-no-logs">
			<span class="dashicons dashicons-info-outline"></span>
			<p>
				<?php esc_html_e( 'No logs recorded yet.', 'bkx-ifttt' ); ?>
				<?php if ( ! $addon->get_setting( 'log_requests', false ) ) : ?>
					<br>
					<em><?php esc_html_e( 'Request logging is disabled. Enable it in Settings to record activity.', 'bkx-ifttt' ); ?></em>
				<?php endif; ?>
			</p>
		</div>
	<?php else : ?>
		<table class="widefat striped bkx-logs-table">
			<thead>
				<tr>
					<th class="column-type"><?php esc_html_e( 'Type', 'bkx-ifttt' ); ?></th>
					<th class="column-timestamp"><?php esc_html_e( 'Time', 'bkx-ifttt' ); ?></th>
					<th class="column-event"><?php esc_html_e( 'Event', 'bkx-ifttt' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'bkx-ifttt' ); ?></th>
					<th class="column-details"><?php esc_html_e( 'Details', 'bkx-ifttt' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $all_logs as $log ) : ?>
					<tr class="bkx-log-row bkx-log-<?php echo esc_attr( $log['type'] ); ?>">
						<td class="column-type">
							<?php
							$type_labels = array(
								'trigger' => __( 'Trigger', 'bkx-ifttt' ),
								'action'  => __( 'Action', 'bkx-ifttt' ),
								'webhook' => __( 'Webhook', 'bkx-ifttt' ),
								'error'   => __( 'Error', 'bkx-ifttt' ),
							);
							$type_icons  = array(
								'trigger' => 'controls-play',
								'action'  => 'admin-generic',
								'webhook' => 'share',
								'error'   => 'warning',
							);
							?>
							<span class="bkx-log-type-badge bkx-type-<?php echo esc_attr( $log['type'] ); ?>">
								<span class="dashicons dashicons-<?php echo esc_attr( $type_icons[ $log['type'] ] ?? 'marker' ); ?>"></span>
								<?php echo esc_html( $type_labels[ $log['type'] ] ?? $log['type'] ); ?>
							</span>
						</td>
						<td class="column-timestamp">
							<span title="<?php echo esc_attr( $log['timestamp'] ); ?>">
								<?php echo esc_html( human_time_diff( strtotime( $log['timestamp'] ), current_time( 'timestamp' ) ) ); ?>
								<?php esc_html_e( 'ago', 'bkx-ifttt' ); ?>
							</span>
						</td>
						<td class="column-event">
							<?php
							$event = $log['trigger'] ?? $log['action'] ?? $log['message'] ?? '-';
							echo '<code>' . esc_html( $event ) . '</code>';
							?>
						</td>
						<td class="column-status">
							<?php if ( 'error' === $log['type'] ) : ?>
								<span class="bkx-status-error">
									<span class="dashicons dashicons-warning"></span>
								</span>
							<?php elseif ( isset( $log['success'] ) ) : ?>
								<?php if ( $log['success'] ) : ?>
									<span class="bkx-status-success">
										<span class="dashicons dashicons-yes-alt"></span>
									</span>
								<?php else : ?>
									<span class="bkx-status-error">
										<span class="dashicons dashicons-dismiss"></span>
									</span>
								<?php endif; ?>
							<?php else : ?>
								<span class="bkx-status-info">
									<span class="dashicons dashicons-info-outline"></span>
								</span>
							<?php endif; ?>
						</td>
						<td class="column-details">
							<?php
							if ( ! empty( $log['booking_id'] ) ) {
								printf(
									'<a href="%s">Booking #%d</a>',
									esc_url( admin_url( 'post.php?post=' . $log['booking_id'] . '&action=edit' ) ),
									esc_html( $log['booking_id'] )
								);
							} elseif ( ! empty( $log['webhook_count'] ) ) {
								printf(
									/* translators: %d: number of webhooks */
									esc_html__( '%d webhooks notified', 'bkx-ifttt' ),
									esc_html( $log['webhook_count'] )
								);
							} elseif ( ! empty( $log['url'] ) ) {
								echo '<code class="bkx-truncate">' . esc_url( $log['url'] ) . '</code>';
							} elseif ( ! empty( $log['error'] ) ) {
								echo '<span class="bkx-error-text">' . esc_html( $log['error'] ) . '</span>';
							} elseif ( ! empty( $log['context'] ) ) {
								echo '<span class="bkx-context">' . esc_html( is_array( $log['context'] ) ? wp_json_encode( $log['context'] ) : $log['context'] ) . '</span>';
							} elseif ( ! empty( $log['response_code'] ) ) {
								echo 'HTTP ' . esc_html( $log['response_code'] );
							} else {
								echo '-';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description" style="margin-top: 15px;">
			<?php esc_html_e( 'Showing the most recent 100 log entries. Logs older than 30 days are automatically deleted.', 'bkx-ifttt' ); ?>
		</p>
	<?php endif; ?>
</div>
