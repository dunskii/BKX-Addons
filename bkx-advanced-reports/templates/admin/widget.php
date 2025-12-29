<?php
/**
 * Dashboard Widget Template.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="bkx-dashboard-widget">
	<!-- Quick Stats -->
	<div class="bkx-widget-stats">
		<div class="bkx-widget-stat">
			<span class="bkx-stat-value" id="widget-revenue">--</span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Revenue (30 days)', 'bkx-advanced-reports' ); ?></span>
			<span class="bkx-stat-change" id="widget-revenue-change"></span>
		</div>
		<div class="bkx-widget-stat">
			<span class="bkx-stat-value" id="widget-bookings">--</span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Bookings (30 days)', 'bkx-advanced-reports' ); ?></span>
			<span class="bkx-stat-change" id="widget-bookings-change"></span>
		</div>
		<div class="bkx-widget-stat">
			<span class="bkx-stat-value" id="widget-customers">--</span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Customers', 'bkx-advanced-reports' ); ?></span>
		</div>
	</div>

	<!-- Mini Chart -->
	<div class="bkx-widget-chart">
		<canvas id="bkx-widget-chart" height="100"></canvas>
	</div>

	<!-- Quick Actions -->
	<div class="bkx-widget-actions">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-advanced-reports' ) ); ?>" class="button">
			<?php esc_html_e( 'View Full Reports', 'bkx-advanced-reports' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-advanced-reports&tab=exports' ) ); ?>" class="button">
			<?php esc_html_e( 'Export', 'bkx-advanced-reports' ); ?>
		</a>
	</div>
</div>

<style>
	.bkx-dashboard-widget {
		padding: 0;
	}
	.bkx-widget-stats {
		display: flex;
		justify-content: space-between;
		padding: 10px 0;
		border-bottom: 1px solid #e0e0e0;
		margin-bottom: 10px;
	}
	.bkx-widget-stat {
		text-align: center;
		flex: 1;
	}
	.bkx-stat-value {
		display: block;
		font-size: 1.5em;
		font-weight: 600;
		color: #1e1e1e;
	}
	.bkx-stat-label {
		display: block;
		font-size: 11px;
		color: #666;
		margin-top: 2px;
	}
	.bkx-stat-change {
		display: block;
		font-size: 11px;
		margin-top: 2px;
	}
	.bkx-stat-change.positive {
		color: #46b450;
	}
	.bkx-stat-change.negative {
		color: #dc3232;
	}
	.bkx-widget-chart {
		padding: 10px 0;
	}
	.bkx-widget-actions {
		display: flex;
		gap: 10px;
		padding-top: 10px;
		border-top: 1px solid #e0e0e0;
	}
	.bkx-widget-actions .button {
		flex: 1;
		text-align: center;
	}
</style>

<script>
jQuery(document).ready(function($) {
	// Load widget data.
	$.ajax({
		url: ajaxurl,
		type: 'POST',
		data: {
			action: 'bkx_get_widget_data',
			nonce: '<?php echo esc_js( wp_create_nonce( 'bkx_widget_nonce' ) ); ?>'
		},
		success: function(response) {
			if (response.success && response.data) {
				var data = response.data;

				// Update stats.
				$('#widget-revenue').text(data.revenue_formatted);
				$('#widget-bookings').text(data.bookings);
				$('#widget-customers').text(data.customers);

				// Update changes.
				if (data.revenue_change !== undefined) {
					var revenueChange = $('#widget-revenue-change');
					var sign = data.revenue_change >= 0 ? '+' : '';
					revenueChange.text(sign + data.revenue_change + '%');
					revenueChange.addClass(data.revenue_change >= 0 ? 'positive' : 'negative');
				}

				if (data.bookings_change !== undefined) {
					var bookingsChange = $('#widget-bookings-change');
					var sign = data.bookings_change >= 0 ? '+' : '';
					bookingsChange.text(sign + data.bookings_change + '%');
					bookingsChange.addClass(data.bookings_change >= 0 ? 'positive' : 'negative');
				}

				// Render mini chart.
				if (data.chart_data && typeof Chart !== 'undefined') {
					var ctx = document.getElementById('bkx-widget-chart').getContext('2d');
					new Chart(ctx, {
						type: 'line',
						data: {
							labels: data.chart_data.labels,
							datasets: [{
								data: data.chart_data.values,
								borderColor: '#2271b1',
								backgroundColor: 'rgba(34, 113, 177, 0.1)',
								fill: true,
								tension: 0.4,
								pointRadius: 0
							}]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: {
								legend: { display: false }
							},
							scales: {
								x: { display: false },
								y: { display: false }
							}
						}
					});
				}
			}
		}
	});
});
</script>
