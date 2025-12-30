<?php
/**
 * API Request Logs Template.
 *
 * @package BookingX\EnterpriseAPI
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page = 50;
$offset = ( $page - 1 ) * $per_page;

$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$endpoint_filter = isset( $_GET['endpoint'] ) ? sanitize_text_field( $_GET['endpoint'] ) : '';

$where = '1=1';
$values = array();

if ( 'success' === $status_filter ) {
	$where .= ' AND response_code >= 200 AND response_code < 300';
} elseif ( 'error' === $status_filter ) {
	$where .= ' AND response_code >= 400';
}

if ( $endpoint_filter ) {
	$where .= ' AND endpoint LIKE %s';
	$values[] = '%' . $wpdb->esc_like( $endpoint_filter ) . '%';
}

$values[] = $per_page;
$values[] = $offset;

$logs = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}bkx_api_logs
		WHERE {$where}
		ORDER BY created_at DESC
		LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$values
	)
);

$total = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_api_logs WHERE {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		array_slice( $values, 0, -2 )
	)
);

$total_pages = ceil( $total / $per_page );
?>
<div class="bkx-api-logs">
	<h2><?php esc_html_e( 'Request Logs', 'bkx-enterprise-api' ); ?></h2>

	<!-- Filters -->
	<div class="bkx-log-filters">
		<form method="get">
			<input type="hidden" name="post_type" value="bkx_booking">
			<input type="hidden" name="page" value="bkx-enterprise-api">
			<input type="hidden" name="tab" value="logs">

			<select name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'bkx-enterprise-api' ); ?></option>
				<option value="success" <?php selected( $status_filter, 'success' ); ?>><?php esc_html_e( 'Success (2xx)', 'bkx-enterprise-api' ); ?></option>
				<option value="error" <?php selected( $status_filter, 'error' ); ?>><?php esc_html_e( 'Errors (4xx/5xx)', 'bkx-enterprise-api' ); ?></option>
			</select>

			<input type="text" name="endpoint" placeholder="<?php esc_attr_e( 'Filter by endpoint', 'bkx-enterprise-api' ); ?>" value="<?php echo esc_attr( $endpoint_filter ); ?>">

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-enterprise-api' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-enterprise-api&tab=logs' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'bkx-enterprise-api' ); ?></a>
		</form>
	</div>

	<!-- Logs Table -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 150px;"><?php esc_html_e( 'Time', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Method', 'bkx-enterprise-api' ); ?></th>
				<th><?php esc_html_e( 'Endpoint', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Status', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Duration', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 120px;"><?php esc_html_e( 'Client', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 120px;"><?php esc_html_e( 'IP Address', 'bkx-enterprise-api' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No logs found.', 'bkx-enterprise-api' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<tr class="bkx-log-row" data-log-id="<?php echo esc_attr( $log->id ); ?>">
						<td>
							<?php echo esc_html( date_i18n( 'M j, H:i:s', strtotime( $log->created_at ) ) ); ?>
						</td>
						<td>
							<span class="bkx-method-badge bkx-method-<?php echo esc_attr( strtolower( $log->method ) ); ?>">
								<?php echo esc_html( $log->method ); ?>
							</span>
						</td>
						<td>
							<code><?php echo esc_html( $log->endpoint ); ?></code>
						</td>
						<td>
							<?php
							$status_class = 'success';
							if ( $log->response_code >= 400 ) {
								$status_class = 'error';
							} elseif ( $log->response_code >= 300 ) {
								$status_class = 'redirect';
							}
							?>
							<span class="bkx-status-code bkx-status-<?php echo esc_attr( $status_class ); ?>">
								<?php echo esc_html( $log->response_code ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $log->duration_ms ); ?>ms</td>
						<td>
							<?php if ( $log->client_id ) : ?>
								<span title="OAuth Client"><?php echo esc_html( substr( $log->client_id, 0, 12 ) ); ?>...</span>
							<?php elseif ( $log->api_key_id ) : ?>
								<span title="API Key"><?php echo esc_html( substr( $log->api_key_id, 0, 8 ) ); ?>...</span>
							<?php else : ?>
								<em><?php esc_html_e( 'Anonymous', 'bkx-enterprise-api' ); ?></em>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log->ip_address ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s: number of items */
						esc_html__( '%s items', 'bkx-enterprise-api' ),
						number_format_i18n( $total )
					);
					?>
				</span>
				<span class="pagination-links">
					<?php if ( $page > 1 ) : ?>
						<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page - 1 ) ); ?>">
							<span>&lsaquo;</span>
						</a>
					<?php endif; ?>
					<span class="paging-input">
						<?php echo esc_html( $page ); ?> / <?php echo esc_html( $total_pages ); ?>
					</span>
					<?php if ( $page < $total_pages ) : ?>
						<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page + 1 ) ); ?>">
							<span>&rsaquo;</span>
						</a>
					<?php endif; ?>
				</span>
			</div>
		</div>
	<?php endif; ?>

	<!-- Log Retention Notice -->
	<?php
	$settings = get_option( 'bkx_enterprise_api_settings', array() );
	$retention = $settings['log_retention_days'] ?? 30;
	?>
	<p class="description">
		<?php
		printf(
			/* translators: %d: number of days */
			esc_html__( 'Logs are automatically deleted after %d days.', 'bkx-enterprise-api' ),
			$retention
		);
		?>
	</p>
</div>

<style>
.bkx-method-badge {
	display: inline-block;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
}
.bkx-method-get { background: #dbeafe; color: #1e40af; }
.bkx-method-post { background: #d1fae5; color: #065f46; }
.bkx-method-put { background: #fef3c7; color: #92400e; }
.bkx-method-patch { background: #e5e7eb; color: #374151; }
.bkx-method-delete { background: #fee2e2; color: #991b1b; }

.bkx-status-code {
	font-weight: 600;
}
.bkx-status-success { color: #065f46; }
.bkx-status-redirect { color: #92400e; }
.bkx-status-error { color: #991b1b; }
</style>
