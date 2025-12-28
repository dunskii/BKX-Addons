<?php
/**
 * Business Intelligence Dashboard Template.
 *
 * @package BookingX\BusinessIntelligence
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap bkx-bi-dashboard">
	<h1><?php esc_html_e( 'Business Intelligence Dashboard', 'bkx-business-intelligence' ); ?></h1>

	<!-- Date Range Selector -->
	<div class="bkx-bi-controls">
		<div class="bkx-bi-date-range">
			<label for="bkx-bi-period"><?php esc_html_e( 'Period:', 'bkx-business-intelligence' ); ?></label>
			<select id="bkx-bi-period" class="bkx-bi-select">
				<option value="today"><?php esc_html_e( 'Today', 'bkx-business-intelligence' ); ?></option>
				<option value="week" selected><?php esc_html_e( 'Last 7 Days', 'bkx-business-intelligence' ); ?></option>
				<option value="month"><?php esc_html_e( 'Last 30 Days', 'bkx-business-intelligence' ); ?></option>
				<option value="quarter"><?php esc_html_e( 'Last 90 Days', 'bkx-business-intelligence' ); ?></option>
				<option value="year"><?php esc_html_e( 'Last Year', 'bkx-business-intelligence' ); ?></option>
				<option value="custom"><?php esc_html_e( 'Custom Range', 'bkx-business-intelligence' ); ?></option>
			</select>

			<div id="bkx-bi-custom-range" class="bkx-bi-custom-range" style="display: none;">
				<input type="date" id="bkx-bi-start-date" class="bkx-bi-date-input" />
				<span><?php esc_html_e( 'to', 'bkx-business-intelligence' ); ?></span>
				<input type="date" id="bkx-bi-end-date" class="bkx-bi-date-input" />
			</div>

			<button type="button" id="bkx-bi-refresh" class="button button-primary">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh', 'bkx-business-intelligence' ); ?>
			</button>
		</div>

		<div class="bkx-bi-actions">
			<button type="button" id="bkx-bi-export-csv" class="button">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'bkx-business-intelligence' ); ?>
			</button>
			<button type="button" id="bkx-bi-export-pdf" class="button">
				<span class="dashicons dashicons-pdf"></span>
				<?php esc_html_e( 'Export PDF', 'bkx-business-intelligence' ); ?>
			</button>
		</div>
	</div>

	<!-- KPI Cards -->
	<div class="bkx-bi-kpi-grid">
		<div class="bkx-bi-kpi-card" data-kpi="revenue">
			<div class="bkx-bi-kpi-icon">
				<span class="dashicons dashicons-chart-line"></span>
			</div>
			<div class="bkx-bi-kpi-content">
				<div class="bkx-bi-kpi-value" id="kpi-revenue">
					<span class="bkx-bi-loading"></span>
				</div>
				<div class="bkx-bi-kpi-label"><?php esc_html_e( 'Total Revenue', 'bkx-business-intelligence' ); ?></div>
				<div class="bkx-bi-kpi-change" id="kpi-revenue-change"></div>
			</div>
		</div>

		<div class="bkx-bi-kpi-card" data-kpi="bookings">
			<div class="bkx-bi-kpi-icon">
				<span class="dashicons dashicons-calendar-alt"></span>
			</div>
			<div class="bkx-bi-kpi-content">
				<div class="bkx-bi-kpi-value" id="kpi-bookings">
					<span class="bkx-bi-loading"></span>
				</div>
				<div class="bkx-bi-kpi-label"><?php esc_html_e( 'Total Bookings', 'bkx-business-intelligence' ); ?></div>
				<div class="bkx-bi-kpi-change" id="kpi-bookings-change"></div>
			</div>
		</div>

		<div class="bkx-bi-kpi-card" data-kpi="customers">
			<div class="bkx-bi-kpi-icon">
				<span class="dashicons dashicons-groups"></span>
			</div>
			<div class="bkx-bi-kpi-content">
				<div class="bkx-bi-kpi-value" id="kpi-customers">
					<span class="bkx-bi-loading"></span>
				</div>
				<div class="bkx-bi-kpi-label"><?php esc_html_e( 'Unique Customers', 'bkx-business-intelligence' ); ?></div>
			</div>
		</div>

		<div class="bkx-bi-kpi-card" data-kpi="avg-value">
			<div class="bkx-bi-kpi-icon">
				<span class="dashicons dashicons-money-alt"></span>
			</div>
			<div class="bkx-bi-kpi-content">
				<div class="bkx-bi-kpi-value" id="kpi-avg-value">
					<span class="bkx-bi-loading"></span>
				</div>
				<div class="bkx-bi-kpi-label"><?php esc_html_e( 'Avg. Booking Value', 'bkx-business-intelligence' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Performance Rates -->
	<div class="bkx-bi-rates-grid">
		<div class="bkx-bi-rate-card">
			<div class="bkx-bi-rate-circle" id="rate-completion">
				<svg viewBox="0 0 36 36" class="bkx-bi-circular-chart green">
					<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
					<path class="circle" stroke-dasharray="0, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
					<text x="18" y="20.35" class="percentage">0%</text>
				</svg>
			</div>
			<div class="bkx-bi-rate-label"><?php esc_html_e( 'Completion Rate', 'bkx-business-intelligence' ); ?></div>
		</div>

		<div class="bkx-bi-rate-card">
			<div class="bkx-bi-rate-circle" id="rate-cancellation">
				<svg viewBox="0 0 36 36" class="bkx-bi-circular-chart red">
					<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
					<path class="circle" stroke-dasharray="0, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
					<text x="18" y="20.35" class="percentage">0%</text>
				</svg>
			</div>
			<div class="bkx-bi-rate-label"><?php esc_html_e( 'Cancellation Rate', 'bkx-business-intelligence' ); ?></div>
		</div>

		<div class="bkx-bi-rate-card">
			<div class="bkx-bi-rate-circle" id="rate-noshow">
				<svg viewBox="0 0 36 36" class="bkx-bi-circular-chart orange">
					<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
					<path class="circle" stroke-dasharray="0, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
					<text x="18" y="20.35" class="percentage">0%</text>
				</svg>
			</div>
			<div class="bkx-bi-rate-label"><?php esc_html_e( 'No-Show Rate', 'bkx-business-intelligence' ); ?></div>
		</div>
	</div>

	<!-- Charts Row 1 -->
	<div class="bkx-bi-charts-row">
		<div class="bkx-bi-chart-card bkx-bi-chart-large">
			<div class="bkx-bi-chart-header">
				<h3><?php esc_html_e( 'Revenue Trend', 'bkx-business-intelligence' ); ?></h3>
				<div class="bkx-bi-chart-actions">
					<button type="button" class="bkx-bi-chart-toggle active" data-chart="revenue" data-type="revenue">
						<?php esc_html_e( 'Revenue', 'bkx-business-intelligence' ); ?>
					</button>
					<button type="button" class="bkx-bi-chart-toggle" data-chart="revenue" data-type="bookings">
						<?php esc_html_e( 'Bookings', 'bkx-business-intelligence' ); ?>
					</button>
				</div>
			</div>
			<div class="bkx-bi-chart-body">
				<canvas id="chart-revenue-trend"></canvas>
			</div>
		</div>

		<div class="bkx-bi-chart-card bkx-bi-chart-small">
			<div class="bkx-bi-chart-header">
				<h3><?php esc_html_e( 'Service Breakdown', 'bkx-business-intelligence' ); ?></h3>
			</div>
			<div class="bkx-bi-chart-body">
				<canvas id="chart-service-breakdown"></canvas>
			</div>
		</div>
	</div>

	<!-- Charts Row 2 -->
	<div class="bkx-bi-charts-row">
		<div class="bkx-bi-chart-card bkx-bi-chart-medium">
			<div class="bkx-bi-chart-header">
				<h3><?php esc_html_e( 'Staff Performance', 'bkx-business-intelligence' ); ?></h3>
			</div>
			<div class="bkx-bi-chart-body">
				<canvas id="chart-staff-performance"></canvas>
			</div>
		</div>

		<div class="bkx-bi-chart-card bkx-bi-chart-medium">
			<div class="bkx-bi-chart-header">
				<h3><?php esc_html_e( 'Hourly Distribution', 'bkx-business-intelligence' ); ?></h3>
			</div>
			<div class="bkx-bi-chart-body">
				<canvas id="chart-hourly-distribution"></canvas>
			</div>
		</div>
	</div>

	<!-- Charts Row 3 -->
	<div class="bkx-bi-charts-row">
		<div class="bkx-bi-chart-card bkx-bi-chart-medium">
			<div class="bkx-bi-chart-header">
				<h3><?php esc_html_e( 'Day of Week Distribution', 'bkx-business-intelligence' ); ?></h3>
			</div>
			<div class="bkx-bi-chart-body">
				<canvas id="chart-day-distribution"></canvas>
			</div>
		</div>

		<div class="bkx-bi-chart-card bkx-bi-chart-medium">
			<div class="bkx-bi-chart-header">
				<h3><?php esc_html_e( 'Month Over Month', 'bkx-business-intelligence' ); ?></h3>
			</div>
			<div class="bkx-bi-chart-body">
				<canvas id="chart-month-comparison"></canvas>
			</div>
		</div>
	</div>

	<!-- Forecast Section -->
	<div class="bkx-bi-section">
		<h2><?php esc_html_e( 'Forecast', 'bkx-business-intelligence' ); ?></h2>

		<div class="bkx-bi-forecast-controls">
			<label for="bkx-bi-forecast-metric"><?php esc_html_e( 'Metric:', 'bkx-business-intelligence' ); ?></label>
			<select id="bkx-bi-forecast-metric" class="bkx-bi-select">
				<option value="revenue"><?php esc_html_e( 'Revenue', 'bkx-business-intelligence' ); ?></option>
				<option value="bookings"><?php esc_html_e( 'Bookings', 'bkx-business-intelligence' ); ?></option>
			</select>

			<label for="bkx-bi-forecast-days"><?php esc_html_e( 'Days:', 'bkx-business-intelligence' ); ?></label>
			<select id="bkx-bi-forecast-days" class="bkx-bi-select">
				<option value="7"><?php esc_html_e( '7 days', 'bkx-business-intelligence' ); ?></option>
				<option value="14"><?php esc_html_e( '14 days', 'bkx-business-intelligence' ); ?></option>
				<option value="30" selected><?php esc_html_e( '30 days', 'bkx-business-intelligence' ); ?></option>
				<option value="60"><?php esc_html_e( '60 days', 'bkx-business-intelligence' ); ?></option>
				<option value="90"><?php esc_html_e( '90 days', 'bkx-business-intelligence' ); ?></option>
			</select>

			<button type="button" id="bkx-bi-generate-forecast" class="button button-primary">
				<?php esc_html_e( 'Generate Forecast', 'bkx-business-intelligence' ); ?>
			</button>
		</div>

		<div class="bkx-bi-charts-row">
			<div class="bkx-bi-chart-card bkx-bi-chart-large">
				<div class="bkx-bi-chart-header">
					<h3 id="forecast-title"><?php esc_html_e( 'Revenue Forecast', 'bkx-business-intelligence' ); ?></h3>
					<div class="bkx-bi-forecast-summary" id="forecast-summary"></div>
				</div>
				<div class="bkx-bi-chart-body">
					<canvas id="chart-forecast"></canvas>
				</div>
			</div>

			<div class="bkx-bi-forecast-details">
				<div class="bkx-bi-detail-card">
					<div class="bkx-bi-detail-label"><?php esc_html_e( 'Total Forecast', 'bkx-business-intelligence' ); ?></div>
					<div class="bkx-bi-detail-value" id="forecast-total">-</div>
				</div>
				<div class="bkx-bi-detail-card">
					<div class="bkx-bi-detail-label"><?php esc_html_e( 'Daily Average', 'bkx-business-intelligence' ); ?></div>
					<div class="bkx-bi-detail-value" id="forecast-avg">-</div>
				</div>
				<div class="bkx-bi-detail-card">
					<div class="bkx-bi-detail-label"><?php esc_html_e( 'Confidence', 'bkx-business-intelligence' ); ?></div>
					<div class="bkx-bi-detail-value" id="forecast-confidence">-</div>
				</div>
				<div class="bkx-bi-detail-card">
					<div class="bkx-bi-detail-label"><?php esc_html_e( 'Model', 'bkx-business-intelligence' ); ?></div>
					<div class="bkx-bi-detail-value" id="forecast-model">-</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Insights Section -->
	<div class="bkx-bi-section">
		<h2><?php esc_html_e( 'Insights', 'bkx-business-intelligence' ); ?></h2>
		<div class="bkx-bi-insights" id="bkx-bi-insights">
			<div class="bkx-bi-loading-message">
				<span class="bkx-bi-loading"></span>
				<?php esc_html_e( 'Analyzing data...', 'bkx-business-intelligence' ); ?>
			</div>
		</div>
	</div>
</div>

<script type="text/template" id="tmpl-bkx-bi-insight">
	<div class="bkx-bi-insight bkx-bi-insight-{{ data.type }}">
		<span class="dashicons dashicons-{{ data.icon }}"></span>
		<p>{{ data.message }}</p>
	</div>
</script>
