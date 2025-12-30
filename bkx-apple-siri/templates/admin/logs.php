<?php
/**
 * Apple Siri logs template.
 *
 * @package BookingX\AppleSiri
 */

defined( 'ABSPATH' ) || exit;

$addon = \BookingX\AppleSiri\AppleSiriAddon::get_instance();

// Get recent logs.
$logs = get_option( 'bkx_apple_siri_request_log', array() );
$logs = array_reverse( $logs ); // Most recent first.
?>
<div class="bkx-logs-section">
	<div class="bkx-logs-header">
		<h2><?php esc_html_e( 'Request Logs', 'bkx-apple-siri' ); ?></h2>
		<div class="bkx-logs-actions">
			<button type="button" class="button" id="bkx-refresh-logs">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh', 'bkx-apple-siri' ); ?>
			</button>
			<button type="button" class="button" id="bkx-clear-logs">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Clear Logs', 'bkx-apple-siri' ); ?>
			</button>
		</div>
	</div>

	<?php if ( ! $addon->get_setting( 'log_requests', false ) ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'Debug logging is currently disabled. Enable it in Settings to capture request logs.', 'bkx-apple-siri' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $logs ) ) : ?>
		<div class="bkx-no-logs">
			<span class="dashicons dashicons-format-status"></span>
			<p><?php esc_html_e( 'No logs to display.', 'bkx-apple-siri' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped" id="bkx-logs-table">
			<thead>
				<tr>
					<th class="column-time" style="width: 150px;"><?php esc_html_e( 'Time', 'bkx-apple-siri' ); ?></th>
					<th class="column-intent" style="width: 200px;"><?php esc_html_e( 'Intent', 'bkx-apple-siri' ); ?></th>
					<th class="column-status" style="width: 100px;"><?php esc_html_e( 'Status', 'bkx-apple-siri' ); ?></th>
					<th class="column-user" style="width: 150px;"><?php esc_html_e( 'User', 'bkx-apple-siri' ); ?></th>
					<th class="column-details"><?php esc_html_e( 'Details', 'bkx-apple-siri' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr class="bkx-log-row <?php echo esc_attr( 'status-' . ( $log['status'] ?? 'unknown' ) ); ?>">
						<td class="column-time">
							<?php
							$timestamp = $log['timestamp'] ?? 0;
							if ( $timestamp ) {
								echo esc_html( wp_date( 'M j, Y g:i:s A', $timestamp ) );
							}
							?>
						</td>
						<td class="column-intent">
							<span class="bkx-intent-badge">
								<?php echo esc_html( $log['intent'] ?? __( 'Unknown', 'bkx-apple-siri' ) ); ?>
							</span>
						</td>
						<td class="column-status">
							<?php
							$status       = $log['status'] ?? 'unknown';
							$status_label = array(
								'success' => __( 'Success', 'bkx-apple-siri' ),
								'error'   => __( 'Error', 'bkx-apple-siri' ),
								'pending' => __( 'Pending', 'bkx-apple-siri' ),
							);
							?>
							<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( $status_label[ $status ] ?? $status ); ?>
							</span>
						</td>
						<td class="column-user">
							<?php
							$user_id = $log['user_id'] ?? 0;
							if ( $user_id ) {
								$user = get_userdata( $user_id );
								echo $user ? esc_html( $user->display_name ) : sprintf( '#%d', $user_id );
							} else {
								echo '<em>' . esc_html__( 'Guest', 'bkx-apple-siri' ) . '</em>';
							}
							?>
						</td>
						<td class="column-details">
							<?php if ( ! empty( $log['message'] ) ) : ?>
								<span class="bkx-log-message"><?php echo esc_html( $log['message'] ); ?></span>
							<?php endif; ?>
							<button type="button" class="button button-small bkx-view-log-details"
									data-log='<?php echo esc_attr( wp_json_encode( $log ) ); ?>'>
								<?php esc_html_e( 'View', 'bkx-apple-siri' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div class="bkx-logs-section">
	<h2><?php esc_html_e( 'Statistics', 'bkx-apple-siri' ); ?></h2>

	<?php
	// Calculate statistics.
	$total_requests  = count( $logs );
	$success_count   = 0;
	$error_count     = 0;
	$intent_counts   = array();

	foreach ( $logs as $log ) {
		if ( ( $log['status'] ?? '' ) === 'success' ) {
			++$success_count;
		} elseif ( ( $log['status'] ?? '' ) === 'error' ) {
			++$error_count;
		}

		$intent = $log['intent'] ?? 'Unknown';
		if ( ! isset( $intent_counts[ $intent ] ) ) {
			$intent_counts[ $intent ] = 0;
		}
		++$intent_counts[ $intent ];
	}

	$success_rate = $total_requests > 0 ? round( ( $success_count / $total_requests ) * 100, 1 ) : 0;
	?>

	<div class="bkx-stats-grid">
		<div class="bkx-stat-card">
			<div class="bkx-stat-value"><?php echo esc_html( $total_requests ); ?></div>
			<div class="bkx-stat-label"><?php esc_html_e( 'Total Requests', 'bkx-apple-siri' ); ?></div>
		</div>

		<div class="bkx-stat-card bkx-stat-success">
			<div class="bkx-stat-value"><?php echo esc_html( $success_count ); ?></div>
			<div class="bkx-stat-label"><?php esc_html_e( 'Successful', 'bkx-apple-siri' ); ?></div>
		</div>

		<div class="bkx-stat-card bkx-stat-error">
			<div class="bkx-stat-value"><?php echo esc_html( $error_count ); ?></div>
			<div class="bkx-stat-label"><?php esc_html_e( 'Errors', 'bkx-apple-siri' ); ?></div>
		</div>

		<div class="bkx-stat-card">
			<div class="bkx-stat-value"><?php echo esc_html( $success_rate ); ?>%</div>
			<div class="bkx-stat-label"><?php esc_html_e( 'Success Rate', 'bkx-apple-siri' ); ?></div>
		</div>
	</div>

	<?php if ( ! empty( $intent_counts ) ) : ?>
		<h3><?php esc_html_e( 'Requests by Intent', 'bkx-apple-siri' ); ?></h3>
		<table class="widefat" style="max-width: 500px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Intent', 'bkx-apple-siri' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Count', 'bkx-apple-siri' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				arsort( $intent_counts );
				foreach ( $intent_counts as $intent => $count ) :
					?>
					<tr>
						<td><?php echo esc_html( $intent ); ?></td>
						<td><?php echo esc_html( $count ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<!-- Log Details Modal -->
<div id="bkx-log-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Request Details', 'bkx-apple-siri' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<pre id="bkx-log-details-content"></pre>
		</div>
	</div>
</div>
