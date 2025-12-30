<?php
/**
 * API Dashboard Template.
 *
 * @package BookingX\EnterpriseAPI
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '7d';

switch ( $period ) {
	case '24h':
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
		break;
	case '30d':
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
		break;
	default:
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
}

// Get stats.
$total_requests = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s",
		$since
	)
);

$successful_requests = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s AND response_code >= 200 AND response_code < 300",
		$since
	)
);

$failed_requests = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s AND response_code >= 400",
		$since
	)
);

$avg_response_time = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT AVG(duration_ms) FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s",
		$since
	)
);

$unique_clients = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(DISTINCT COALESCE(client_id, api_key_id, ip_address)) FROM {$wpdb->prefix}bkx_api_logs WHERE created_at >= %s",
		$since
	)
);

// Get top endpoints.
$top_endpoints = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT endpoint, COUNT(*) as count
		FROM {$wpdb->prefix}bkx_api_logs
		WHERE created_at >= %s
		GROUP BY endpoint
		ORDER BY count DESC
		LIMIT 10",
		$since
	)
);

// Get requests by day.
$requests_by_day = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT DATE(created_at) as date, COUNT(*) as count
		FROM {$wpdb->prefix}bkx_api_logs
		WHERE created_at >= %s
		GROUP BY DATE(created_at)
		ORDER BY date ASC",
		$since
	)
);

// Get error breakdown.
$error_breakdown = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT response_code, COUNT(*) as count
		FROM {$wpdb->prefix}bkx_api_logs
		WHERE created_at >= %s AND response_code >= 400
		GROUP BY response_code
		ORDER BY count DESC",
		$since
	)
);

// Get active API keys count.
$active_keys = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_api_keys WHERE is_active = 1"
);

// Get active OAuth clients.
$active_clients = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_oauth_clients WHERE is_active = 1"
);

// Get active webhooks.
$active_webhooks = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_webhooks WHERE is_active = 1"
);
?>
<div class="bkx-api-dashboard">
	<!-- Period Filter -->
	<div class="bkx-period-filter">
		<span><?php esc_html_e( 'Period:', 'bkx-enterprise-api' ); ?></span>
		<a href="<?php echo esc_url( add_query_arg( 'period', '24h' ) ); ?>" class="button <?php echo '24h' === $period ? 'button-primary' : ''; ?>">
			<?php esc_html_e( '24 Hours', 'bkx-enterprise-api' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'period', '7d' ) ); ?>" class="button <?php echo '7d' === $period ? 'button-primary' : ''; ?>">
			<?php esc_html_e( '7 Days', 'bkx-enterprise-api' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'period', '30d' ) ); ?>" class="button <?php echo '30d' === $period ? 'button-primary' : ''; ?>">
			<?php esc_html_e( '30 Days', 'bkx-enterprise-api' ); ?>
		</a>
	</div>

	<!-- Stats Cards -->
	<div class="bkx-stats-grid">
		<div class="bkx-stat-card">
			<div class="bkx-stat-icon">
				<span class="dashicons dashicons-rest-api"></span>
			</div>
			<div class="bkx-stat-content">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $total_requests ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Total Requests', 'bkx-enterprise-api' ); ?></span>
			</div>
		</div>

		<div class="bkx-stat-card bkx-stat-success">
			<div class="bkx-stat-icon">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="bkx-stat-content">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $successful_requests ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Successful', 'bkx-enterprise-api' ); ?></span>
			</div>
		</div>

		<div class="bkx-stat-card bkx-stat-danger">
			<div class="bkx-stat-icon">
				<span class="dashicons dashicons-dismiss"></span>
			</div>
			<div class="bkx-stat-content">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $failed_requests ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Failed', 'bkx-enterprise-api' ); ?></span>
			</div>
		</div>

		<div class="bkx-stat-card">
			<div class="bkx-stat-icon">
				<span class="dashicons dashicons-performance"></span>
			</div>
			<div class="bkx-stat-content">
				<span class="bkx-stat-value"><?php echo esc_html( round( $avg_response_time ?: 0 ) ); ?>ms</span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Avg Response Time', 'bkx-enterprise-api' ); ?></span>
			</div>
		</div>

		<div class="bkx-stat-card">
			<div class="bkx-stat-icon">
				<span class="dashicons dashicons-groups"></span>
			</div>
			<div class="bkx-stat-content">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $unique_clients ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Unique Clients', 'bkx-enterprise-api' ); ?></span>
			</div>
		</div>

		<div class="bkx-stat-card">
			<div class="bkx-stat-icon">
				<span class="dashicons dashicons-admin-network"></span>
			</div>
			<div class="bkx-stat-content">
				<span class="bkx-stat-value"><?php echo esc_html( $active_keys ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Active API Keys', 'bkx-enterprise-api' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Charts Row -->
	<div class="bkx-charts-row">
		<div class="bkx-chart-card">
			<h3><?php esc_html_e( 'Requests Over Time', 'bkx-enterprise-api' ); ?></h3>
			<canvas id="bkx-requests-chart" height="200"></canvas>
		</div>

		<div class="bkx-chart-card">
			<h3><?php esc_html_e( 'Error Distribution', 'bkx-enterprise-api' ); ?></h3>
			<canvas id="bkx-errors-chart" height="200"></canvas>
		</div>
	</div>

	<!-- Top Endpoints -->
	<div class="bkx-endpoints-table">
		<h3><?php esc_html_e( 'Top Endpoints', 'bkx-enterprise-api' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Endpoint', 'bkx-enterprise-api' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Requests', 'bkx-enterprise-api' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Share', 'bkx-enterprise-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $top_endpoints ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No data available.', 'bkx-enterprise-api' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $top_endpoints as $endpoint ) : ?>
						<tr>
							<td><code><?php echo esc_html( $endpoint->endpoint ); ?></code></td>
							<td><?php echo esc_html( number_format( $endpoint->count ) ); ?></td>
							<td>
								<?php
								$share = $total_requests > 0 ? round( ( $endpoint->count / $total_requests ) * 100, 1 ) : 0;
								echo esc_html( $share . '%' );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Quick Actions -->
	<div class="bkx-quick-actions">
		<h3><?php esc_html_e( 'Quick Actions', 'bkx-enterprise-api' ); ?></h3>
		<div class="bkx-action-cards">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-enterprise-api&tab=api-keys' ) ); ?>" class="bkx-action-card">
				<span class="dashicons dashicons-admin-network"></span>
				<span><?php esc_html_e( 'Create API Key', 'bkx-enterprise-api' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-enterprise-api&tab=oauth' ) ); ?>" class="bkx-action-card">
				<span class="dashicons dashicons-lock"></span>
				<span><?php esc_html_e( 'Add OAuth Client', 'bkx-enterprise-api' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-enterprise-api&tab=webhooks' ) ); ?>" class="bkx-action-card">
				<span class="dashicons dashicons-share"></span>
				<span><?php esc_html_e( 'Create Webhook', 'bkx-enterprise-api' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-enterprise-api&tab=docs' ) ); ?>" class="bkx-action-card">
				<span class="dashicons dashicons-book"></span>
				<span><?php esc_html_e( 'View Docs', 'bkx-enterprise-api' ); ?></span>
			</a>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Requests chart.
	var requestsCtx = document.getElementById('bkx-requests-chart');
	if (requestsCtx && typeof Chart !== 'undefined') {
		new Chart(requestsCtx, {
			type: 'line',
			data: {
				labels: <?php echo wp_json_encode( array_map( function( $r ) { return date_i18n( 'M j', strtotime( $r->date ) ); }, $requests_by_day ) ); ?>,
				datasets: [{
					label: '<?php esc_html_e( 'Requests', 'bkx-enterprise-api' ); ?>',
					data: <?php echo wp_json_encode( array_map( function( $r ) { return (int) $r->count; }, $requests_by_day ) ); ?>,
					borderColor: '#2563eb',
					backgroundColor: 'rgba(37, 99, 235, 0.1)',
					fill: true,
					tension: 0.3
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { y: { beginAtZero: true } }
			}
		});
	}

	// Errors chart.
	var errorsCtx = document.getElementById('bkx-errors-chart');
	if (errorsCtx && typeof Chart !== 'undefined' && <?php echo wp_json_encode( count( $error_breakdown ) ); ?> > 0) {
		new Chart(errorsCtx, {
			type: 'doughnut',
			data: {
				labels: <?php echo wp_json_encode( array_map( function( $e ) { return 'HTTP ' . $e->response_code; }, $error_breakdown ) ); ?>,
				datasets: [{
					data: <?php echo wp_json_encode( array_map( function( $e ) { return (int) $e->count; }, $error_breakdown ) ); ?>,
					backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16']
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'right' } }
			}
		});
	}
});
</script>
