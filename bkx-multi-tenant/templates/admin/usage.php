<?php
/**
 * Usage Statistics Template.
 *
 * @package BookingX\MultiTenant
 */

defined( 'ABSPATH' ) || exit;

$usage_tracker  = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'usage_tracker' );
$tenant_manager = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'tenant_manager' );
$plan_manager   = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'plan_manager' );

$tenant_id = isset( $_GET['tenant'] ) ? absint( $_GET['tenant'] ) : 0;
$tenant    = $tenant_id ? $tenant_manager->get_tenant( $tenant_id ) : null;
$period    = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'monthly';

if ( $tenant ) {
	$usage = $usage_tracker->get_tenant_usage_summary( $tenant_id );
	$history = $usage_tracker->get_usage_history( $tenant_id, 'bookings', 'monthly', 12 );
	$plan = $plan_manager->get_tenant_plan( $tenant_id );
} else {
	// Global stats.
	$booking_stats = $usage_tracker->get_global_usage_stats( 'bookings', $period );
	$api_stats     = $usage_tracker->get_global_usage_stats( 'api_calls', $period );
}
?>
<div class="wrap bkx-usage-page">
	<h1>
		<?php
		if ( $tenant ) {
			printf( esc_html__( 'Usage: %s', 'bkx-multi-tenant' ), esc_html( $tenant->name ) );
		} else {
			esc_html_e( 'Usage Statistics', 'bkx-multi-tenant' );
		}
		?>
	</h1>

	<?php if ( $tenant ) : ?>
		<!-- Single Tenant Usage -->
		<div class="bkx-tenant-usage">
			<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-tenant-usage' ) ); ?>" class="button" style="margin-bottom: 20px;">
				&larr; <?php esc_html_e( 'All Tenants', 'bkx-multi-tenant' ); ?>
			</a>

			<div class="bkx-usage-header">
				<div class="bkx-tenant-info">
					<h2><?php echo esc_html( $tenant->name ); ?></h2>
					<?php if ( $plan ) : ?>
						<span class="bkx-plan-badge"><?php echo esc_html( $plan->name ); ?></span>
					<?php endif; ?>
					<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $tenant->status ); ?>">
						<?php echo esc_html( ucfirst( $tenant->status ) ); ?>
					</span>
				</div>
			</div>

			<div class="bkx-usage-grid">
				<?php foreach ( $usage as $metric => $data ) : ?>
					<?php
					$is_array = is_array( $data );
					$current = $is_array ? $data['current'] : $data;
					$limit = $is_array ? $data['limit'] : -1;
					$percent = $is_array ? $data['percent'] : 0;
					$is_warning = $percent > 80;
					$is_danger = $percent > 95;
					?>
					<div class="bkx-usage-card <?php echo $is_danger ? 'bkx-danger' : ( $is_warning ? 'bkx-warning' : '' ); ?>">
						<h3><?php echo esc_html( ucwords( str_replace( '_', ' ', $metric ) ) ); ?></h3>
						<div class="bkx-usage-value">
							<span class="bkx-current"><?php echo esc_html( number_format( $current ) ); ?></span>
							<?php if ( $limit > 0 ) : ?>
								<span class="bkx-limit">/ <?php echo esc_html( number_format( $limit ) ); ?></span>
							<?php elseif ( $limit < 0 ) : ?>
								<span class="bkx-unlimited"><?php esc_html_e( 'Unlimited', 'bkx-multi-tenant' ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( $limit > 0 ) : ?>
							<div class="bkx-usage-bar">
								<div class="bkx-usage-fill" style="width: <?php echo esc_attr( min( $percent, 100 ) ); ?>%"></div>
							</div>
							<div class="bkx-usage-percent"><?php echo esc_html( $percent ); ?>% <?php esc_html_e( 'used', 'bkx-multi-tenant' ); ?></div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Usage History Chart -->
			<div class="bkx-usage-history">
				<h3><?php esc_html_e( 'Booking History (Last 12 Months)', 'bkx-multi-tenant' ); ?></h3>
				<div class="bkx-chart-container">
					<canvas id="bkx-usage-chart"></canvas>
				</div>
				<script>
				document.addEventListener('DOMContentLoaded', function() {
					var ctx = document.getElementById('bkx-usage-chart');
					if (ctx && typeof Chart !== 'undefined') {
						new Chart(ctx, {
							type: 'line',
							data: {
								labels: <?php echo wp_json_encode( array_reverse( array_map( function( $h ) { return date_i18n( 'M Y', strtotime( $h->period_start ) ); }, $history ) ) ); ?>,
								datasets: [{
									label: '<?php esc_html_e( 'Bookings', 'bkx-multi-tenant' ); ?>',
									data: <?php echo wp_json_encode( array_reverse( array_map( function( $h ) { return (int) $h->value; }, $history ) ) ); ?>,
									borderColor: '#2563eb',
									backgroundColor: 'rgba(37, 99, 235, 0.1)',
									fill: true,
									tension: 0.3
								}]
							},
							options: {
								responsive: true,
								maintainAspectRatio: false,
								plugins: {
									legend: { display: false }
								},
								scales: {
									y: { beginAtZero: true }
								}
							}
						});
					}
				});
				</script>
			</div>
		</div>

	<?php else : ?>
		<!-- Global Usage Stats -->
		<div class="bkx-global-usage">
			<div class="bkx-period-filter">
				<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-tenant-usage&period=daily' ) ); ?>" class="button <?php echo 'daily' === $period ? 'button-primary' : ''; ?>">
					<?php esc_html_e( 'Daily', 'bkx-multi-tenant' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-tenant-usage&period=monthly' ) ); ?>" class="button <?php echo 'monthly' === $period ? 'button-primary' : ''; ?>">
					<?php esc_html_e( 'Monthly', 'bkx-multi-tenant' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-tenant-usage&period=yearly' ) ); ?>" class="button <?php echo 'yearly' === $period ? 'button-primary' : ''; ?>">
					<?php esc_html_e( 'Yearly', 'bkx-multi-tenant' ); ?>
				</a>
			</div>

			<div class="bkx-usage-tables">
				<!-- Bookings by Tenant -->
				<div class="bkx-usage-table-wrap">
					<h2><?php esc_html_e( 'Bookings by Tenant', 'bkx-multi-tenant' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Tenant', 'bkx-multi-tenant' ); ?></th>
								<th><?php esc_html_e( 'Bookings', 'bkx-multi-tenant' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bkx-multi-tenant' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $booking_stats ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No data available.', 'bkx-multi-tenant' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $booking_stats as $stat ) : ?>
									<tr>
										<td><?php echo esc_html( $stat->tenant_name ?: __( 'Unknown', 'bkx-multi-tenant' ) ); ?></td>
										<td><strong><?php echo esc_html( number_format( $stat->value ) ); ?></strong></td>
										<td>
											<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-tenant-usage&tenant=' . $stat->tenant_id ) ); ?>" class="button button-small">
												<?php esc_html_e( 'Details', 'bkx-multi-tenant' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<!-- API Calls by Tenant -->
				<div class="bkx-usage-table-wrap">
					<h2><?php esc_html_e( 'API Calls by Tenant', 'bkx-multi-tenant' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Tenant', 'bkx-multi-tenant' ); ?></th>
								<th><?php esc_html_e( 'API Calls', 'bkx-multi-tenant' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bkx-multi-tenant' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $api_stats ) ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No data available.', 'bkx-multi-tenant' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $api_stats as $stat ) : ?>
									<tr>
										<td><?php echo esc_html( $stat->tenant_name ?: __( 'Unknown', 'bkx-multi-tenant' ) ); ?></td>
										<td><strong><?php echo esc_html( number_format( $stat->value ) ); ?></strong></td>
										<td>
											<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-tenant-usage&tenant=' . $stat->tenant_id ) ); ?>" class="button button-small">
												<?php esc_html_e( 'Details', 'bkx-multi-tenant' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
