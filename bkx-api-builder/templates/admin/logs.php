<?php
/**
 * Request Logs admin template.
 *
 * @package BookingX\APIBuilder
 */

defined( 'ABSPATH' ) || exit;

$addon  = \BookingX\APIBuilder\APIBuilderAddon::get_instance();
$logger = $addon->get_service( 'request_logger' );

// Get filter parameters.
$method    = isset( $_GET['method'] ) ? sanitize_text_field( wp_unslash( $_GET['method'] ) ) : '';
$code      = isset( $_GET['code'] ) ? absint( $_GET['code'] ) : 0;
$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
$search    = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
$paged     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

$logs  = $logger->get_logs( array(
	'method'    => $method,
	'response_code' => $code,
	'date_from' => $date_from,
	'date_to'   => $date_to,
	'search'    => $search,
	'page'      => $paged,
	'per_page'  => 50,
) );

$stats = $logger->get_stats( 24 );
?>
<div class="bkx-logs-page">
	<div class="bkx-page-header">
		<h2><?php esc_html_e( 'Request Logs', 'bkx-api-builder' ); ?></h2>
		<div class="bkx-header-actions">
			<button type="button" class="button" id="bkx-export-logs">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'bkx-api-builder' ); ?>
			</button>
			<button type="button" class="button" id="bkx-clear-logs">
				<?php esc_html_e( 'Clear Logs', 'bkx-api-builder' ); ?>
			</button>
		</div>
	</div>

	<!-- Stats Summary -->
	<div class="bkx-stats-grid">
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $stats['total_requests'] ?? 0 ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Total Requests (24h)', 'bkx-api-builder' ); ?></span>
		</div>
		<div class="bkx-stat-card bkx-success">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $stats['successful_requests'] ?? 0 ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Successful', 'bkx-api-builder' ); ?></span>
		</div>
		<div class="bkx-stat-card bkx-error">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( ( $stats['client_errors'] ?? 0 ) + ( $stats['server_errors'] ?? 0 ) ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Errors', 'bkx-api-builder' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( round( $stats['avg_response_time'] ?? 0 ) ); ?> ms</span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Avg Response Time', 'bkx-api-builder' ); ?></span>
		</div>
	</div>

	<!-- Filters -->
	<div class="bkx-logs-filters">
		<form method="get">
			<input type="hidden" name="post_type" value="bkx_booking">
			<input type="hidden" name="page" value="bkx-api-builder">
			<input type="hidden" name="tab" value="logs">

			<select name="method">
				<option value=""><?php esc_html_e( 'All Methods', 'bkx-api-builder' ); ?></option>
				<option value="GET" <?php selected( $method, 'GET' ); ?>>GET</option>
				<option value="POST" <?php selected( $method, 'POST' ); ?>>POST</option>
				<option value="PUT" <?php selected( $method, 'PUT' ); ?>>PUT</option>
				<option value="DELETE" <?php selected( $method, 'DELETE' ); ?>>DELETE</option>
			</select>

			<select name="code">
				<option value=""><?php esc_html_e( 'All Status Codes', 'bkx-api-builder' ); ?></option>
				<option value="200" <?php selected( $code, 200 ); ?>>200 OK</option>
				<option value="400" <?php selected( $code, 400 ); ?>>400 Bad Request</option>
				<option value="401" <?php selected( $code, 401 ); ?>>401 Unauthorized</option>
				<option value="403" <?php selected( $code, 403 ); ?>>403 Forbidden</option>
				<option value="404" <?php selected( $code, 404 ); ?>>404 Not Found</option>
				<option value="429" <?php selected( $code, 429 ); ?>>429 Rate Limited</option>
				<option value="500" <?php selected( $code, 500 ); ?>>500 Server Error</option>
			</select>

			<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From', 'bkx-api-builder' ); ?>">
			<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To', 'bkx-api-builder' ); ?>">
			<input type="text" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search route or IP...', 'bkx-api-builder' ); ?>">

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-api-builder' ); ?></button>
		</form>
	</div>

	<?php if ( empty( $logs ) ) : ?>
		<div class="bkx-empty-state">
			<span class="dashicons dashicons-list-view"></span>
			<h3><?php esc_html_e( 'No logs found', 'bkx-api-builder' ); ?></h3>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 80px;"><?php esc_html_e( 'Method', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Route', 'bkx-api-builder' ); ?></th>
					<th style="width: 80px;"><?php esc_html_e( 'Status', 'bkx-api-builder' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Response Time', 'bkx-api-builder' ); ?></th>
					<th style="width: 120px;"><?php esc_html_e( 'IP Address', 'bkx-api-builder' ); ?></th>
					<th style="width: 150px;"><?php esc_html_e( 'Timestamp', 'bkx-api-builder' ); ?></th>
					<th style="width: 80px;"><?php esc_html_e( 'Details', 'bkx-api-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td>
							<span class="bkx-method bkx-method-<?php echo esc_attr( strtolower( $log['method'] ) ); ?>">
								<?php echo esc_html( $log['method'] ); ?>
							</span>
						</td>
						<td><code><?php echo esc_html( $log['route'] ); ?></code></td>
						<td>
							<?php
							$code_class = 'info';
							if ( $log['response_code'] >= 200 && $log['response_code'] < 300 ) {
								$code_class = 'success';
							} elseif ( $log['response_code'] >= 400 ) {
								$code_class = 'error';
							}
							?>
							<span class="bkx-status-code bkx-<?php echo esc_attr( $code_class ); ?>">
								<?php echo esc_html( $log['response_code'] ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $log['response_time'] ); ?> ms</td>
						<td><?php echo esc_html( $log['ip_address'] ); ?></td>
						<td><?php echo esc_html( wp_date( 'M j, H:i:s', strtotime( $log['created_at'] ) ) ); ?></td>
						<td>
							<button type="button" class="button-link bkx-view-log" data-id="<?php echo esc_attr( $log['id'] ); ?>"
									data-details="<?php echo esc_attr( wp_json_encode( $log ) ); ?>">
								<?php esc_html_e( 'View', 'bkx-api-builder' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<!-- Log Details Modal -->
<div id="bkx-log-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-large">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Request Details', 'bkx-api-builder' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<div id="bkx-log-details"></div>
		</div>
	</div>
</div>
