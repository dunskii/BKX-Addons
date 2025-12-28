<?php
/**
 * Advanced Analytics Admin Template.
 *
 * @package BookingX\AdvancedAnalytics
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap bkx-aa-analytics">
	<h1><?php esc_html_e( 'Advanced Booking Analytics', 'bkx-advanced-analytics' ); ?></h1>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper bkx-aa-tabs">
		<a href="#overview" class="nav-tab nav-tab-active" data-tab="overview">
			<span class="dashicons dashicons-dashboard"></span>
			<?php esc_html_e( 'Overview', 'bkx-advanced-analytics' ); ?>
		</a>
		<a href="#cohorts" class="nav-tab" data-tab="cohorts">
			<span class="dashicons dashicons-groups"></span>
			<?php esc_html_e( 'Cohort Analysis', 'bkx-advanced-analytics' ); ?>
		</a>
		<a href="#comparison" class="nav-tab" data-tab="comparison">
			<span class="dashicons dashicons-chart-bar"></span>
			<?php esc_html_e( 'Comparison', 'bkx-advanced-analytics' ); ?>
		</a>
		<a href="#segments" class="nav-tab" data-tab="segments">
			<span class="dashicons dashicons-networking"></span>
			<?php esc_html_e( 'Segmentation', 'bkx-advanced-analytics' ); ?>
		</a>
		<a href="#patterns" class="nav-tab" data-tab="patterns">
			<span class="dashicons dashicons-chart-area"></span>
			<?php esc_html_e( 'Patterns', 'bkx-advanced-analytics' ); ?>
		</a>
	</nav>

	<!-- Date Range Controls -->
	<div class="bkx-aa-controls">
		<div class="bkx-aa-date-range">
			<label for="bkx-aa-start"><?php esc_html_e( 'From:', 'bkx-advanced-analytics' ); ?></label>
			<input type="date" id="bkx-aa-start" class="bkx-aa-date-input" />
			<label for="bkx-aa-end"><?php esc_html_e( 'To:', 'bkx-advanced-analytics' ); ?></label>
			<input type="date" id="bkx-aa-end" class="bkx-aa-date-input" />
			<button type="button" id="bkx-aa-analyze" class="button button-primary">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Analyze', 'bkx-advanced-analytics' ); ?>
			</button>
		</div>
		<div class="bkx-aa-quick-dates">
			<button type="button" class="button bkx-aa-quick" data-days="7"><?php esc_html_e( '7 Days', 'bkx-advanced-analytics' ); ?></button>
			<button type="button" class="button bkx-aa-quick" data-days="30"><?php esc_html_e( '30 Days', 'bkx-advanced-analytics' ); ?></button>
			<button type="button" class="button bkx-aa-quick" data-days="90"><?php esc_html_e( '90 Days', 'bkx-advanced-analytics' ); ?></button>
			<button type="button" class="button bkx-aa-quick" data-days="365"><?php esc_html_e( '1 Year', 'bkx-advanced-analytics' ); ?></button>
		</div>
	</div>

	<!-- Tab Content -->
	<div class="bkx-aa-tab-content">

		<!-- Overview Tab -->
		<div id="tab-overview" class="bkx-aa-tab-pane active">
			<div class="bkx-aa-grid">
				<!-- Summary Cards -->
				<div class="bkx-aa-card bkx-aa-card-full">
					<h3><?php esc_html_e( 'Summary', 'bkx-advanced-analytics' ); ?></h3>
					<div class="bkx-aa-summary-grid" id="overview-summary">
						<div class="bkx-aa-loading"></div>
					</div>
				</div>

				<!-- Daily Trend Chart -->
				<div class="bkx-aa-card bkx-aa-card-large">
					<h3><?php esc_html_e( 'Daily Trend', 'bkx-advanced-analytics' ); ?></h3>
					<div class="bkx-aa-chart-container">
						<canvas id="chart-daily-trend"></canvas>
					</div>
				</div>

				<!-- Top Services -->
				<div class="bkx-aa-card">
					<h3><?php esc_html_e( 'Top Services', 'bkx-advanced-analytics' ); ?></h3>
					<div id="top-services"></div>
				</div>

				<!-- Top Staff -->
				<div class="bkx-aa-card">
					<h3><?php esc_html_e( 'Top Staff', 'bkx-advanced-analytics' ); ?></h3>
					<div id="top-staff"></div>
				</div>
			</div>

			<!-- Analysis Types -->
			<div class="bkx-aa-analysis-section">
				<h3><?php esc_html_e( 'Detailed Analysis', 'bkx-advanced-analytics' ); ?></h3>
				<div class="bkx-aa-analysis-buttons">
					<button type="button" class="button bkx-aa-analysis-btn" data-type="conversion">
						<span class="dashicons dashicons-filter"></span>
						<?php esc_html_e( 'Conversion Funnel', 'bkx-advanced-analytics' ); ?>
					</button>
					<button type="button" class="button bkx-aa-analysis-btn" data-type="timing">
						<span class="dashicons dashicons-clock"></span>
						<?php esc_html_e( 'Booking Timing', 'bkx-advanced-analytics' ); ?>
					</button>
					<button type="button" class="button bkx-aa-analysis-btn" data-type="cancellation">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e( 'Cancellation Analysis', 'bkx-advanced-analytics' ); ?>
					</button>
				</div>
				<div id="analysis-results" class="bkx-aa-analysis-results"></div>
			</div>
		</div>

		<!-- Cohort Analysis Tab -->
		<div id="tab-cohorts" class="bkx-aa-tab-pane">
			<div class="bkx-aa-cohort-controls">
				<label for="cohort-type"><?php esc_html_e( 'Cohort Type:', 'bkx-advanced-analytics' ); ?></label>
				<select id="cohort-type" class="bkx-aa-select">
					<option value="monthly"><?php esc_html_e( 'Monthly', 'bkx-advanced-analytics' ); ?></option>
					<option value="weekly"><?php esc_html_e( 'Weekly', 'bkx-advanced-analytics' ); ?></option>
				</select>
				<label for="cohort-metric"><?php esc_html_e( 'Metric:', 'bkx-advanced-analytics' ); ?></label>
				<select id="cohort-metric" class="bkx-aa-select">
					<option value="retention"><?php esc_html_e( 'Retention', 'bkx-advanced-analytics' ); ?></option>
					<option value="revenue"><?php esc_html_e( 'Revenue', 'bkx-advanced-analytics' ); ?></option>
					<option value="frequency"><?php esc_html_e( 'Frequency', 'bkx-advanced-analytics' ); ?></option>
				</select>
				<button type="button" id="run-cohort" class="button button-primary">
					<?php esc_html_e( 'Generate Cohort', 'bkx-advanced-analytics' ); ?>
				</button>
			</div>
			<div class="bkx-aa-card bkx-aa-card-full">
				<h3><?php esc_html_e( 'Cohort Matrix', 'bkx-advanced-analytics' ); ?></h3>
				<div id="cohort-matrix" class="bkx-aa-cohort-matrix">
					<p class="bkx-aa-placeholder"><?php esc_html_e( 'Click "Generate Cohort" to see the retention matrix.', 'bkx-advanced-analytics' ); ?></p>
				</div>
			</div>
			<div id="cohort-insights" class="bkx-aa-insights"></div>
		</div>

		<!-- Comparison Tab -->
		<div id="tab-comparison" class="bkx-aa-tab-pane">
			<div class="bkx-aa-comparison-controls">
				<div class="bkx-aa-period">
					<h4><?php esc_html_e( 'Period A', 'bkx-advanced-analytics' ); ?></h4>
					<input type="date" id="period-a-start" class="bkx-aa-date-input" />
					<span><?php esc_html_e( 'to', 'bkx-advanced-analytics' ); ?></span>
					<input type="date" id="period-a-end" class="bkx-aa-date-input" />
				</div>
				<div class="bkx-aa-period">
					<h4><?php esc_html_e( 'Period B', 'bkx-advanced-analytics' ); ?></h4>
					<input type="date" id="period-b-start" class="bkx-aa-date-input" />
					<span><?php esc_html_e( 'to', 'bkx-advanced-analytics' ); ?></span>
					<input type="date" id="period-b-end" class="bkx-aa-date-input" />
				</div>
				<button type="button" id="run-comparison" class="button button-primary">
					<?php esc_html_e( 'Compare', 'bkx-advanced-analytics' ); ?>
				</button>
			</div>
			<div id="comparison-results" class="bkx-aa-comparison-results"></div>
		</div>

		<!-- Segmentation Tab -->
		<div id="tab-segments" class="bkx-aa-tab-pane">
			<div class="bkx-aa-segment-controls">
				<label for="segment-type"><?php esc_html_e( 'Segment Type:', 'bkx-advanced-analytics' ); ?></label>
				<select id="segment-type" class="bkx-aa-select">
					<option value="customer"><?php esc_html_e( 'Customer Segments', 'bkx-advanced-analytics' ); ?></option>
					<option value="rfm"><?php esc_html_e( 'RFM Analysis', 'bkx-advanced-analytics' ); ?></option>
					<option value="value"><?php esc_html_e( 'Value Segments', 'bkx-advanced-analytics' ); ?></option>
					<option value="behavior"><?php esc_html_e( 'Behavioral Segments', 'bkx-advanced-analytics' ); ?></option>
				</select>
				<button type="button" id="run-segments" class="button button-primary">
					<?php esc_html_e( 'Analyze Segments', 'bkx-advanced-analytics' ); ?>
				</button>
			</div>
			<div class="bkx-aa-grid">
				<div class="bkx-aa-card bkx-aa-card-half">
					<h3><?php esc_html_e( 'Segment Distribution', 'bkx-advanced-analytics' ); ?></h3>
					<div class="bkx-aa-chart-container">
						<canvas id="chart-segments"></canvas>
					</div>
				</div>
				<div class="bkx-aa-card bkx-aa-card-half">
					<h3><?php esc_html_e( 'Segment Details', 'bkx-advanced-analytics' ); ?></h3>
					<div id="segment-details"></div>
				</div>
			</div>
			<div id="segment-insights" class="bkx-aa-insights"></div>
		</div>

		<!-- Patterns Tab -->
		<div id="tab-patterns" class="bkx-aa-tab-pane">
			<div class="bkx-aa-pattern-controls">
				<label for="pattern-type"><?php esc_html_e( 'Pattern Type:', 'bkx-advanced-analytics' ); ?></label>
				<select id="pattern-type" class="bkx-aa-select">
					<option value="seasonal"><?php esc_html_e( 'Seasonal Patterns', 'bkx-advanced-analytics' ); ?></option>
					<option value="trend"><?php esc_html_e( 'Trend Analysis', 'bkx-advanced-analytics' ); ?></option>
					<option value="anomaly"><?php esc_html_e( 'Anomaly Detection', 'bkx-advanced-analytics' ); ?></option>
				</select>
				<button type="button" id="run-patterns" class="button button-primary">
					<?php esc_html_e( 'Detect Patterns', 'bkx-advanced-analytics' ); ?>
				</button>
			</div>
			<div class="bkx-aa-grid">
				<div class="bkx-aa-card bkx-aa-card-large">
					<h3 id="pattern-chart-title"><?php esc_html_e( 'Pattern Visualization', 'bkx-advanced-analytics' ); ?></h3>
					<div class="bkx-aa-chart-container">
						<canvas id="chart-patterns"></canvas>
					</div>
				</div>
				<div class="bkx-aa-card">
					<h3><?php esc_html_e( 'Pattern Details', 'bkx-advanced-analytics' ); ?></h3>
					<div id="pattern-details"></div>
				</div>
			</div>
			<div id="pattern-insights" class="bkx-aa-insights"></div>
		</div>
	</div>
</div>

<!-- Templates -->
<script type="text/template" id="tmpl-bkx-aa-summary-item">
	<div class="bkx-aa-summary-item">
		<div class="bkx-aa-summary-value">{{ data.value }}</div>
		<div class="bkx-aa-summary-label">{{ data.label }}</div>
		<# if ( data.change ) { #>
			<div class="bkx-aa-summary-change {{ data.change > 0 ? 'positive' : 'negative' }}">
				{{ data.change > 0 ? '+' : '' }}{{ data.change }}%
			</div>
		<# } #>
	</div>
</script>

<script type="text/template" id="tmpl-bkx-aa-list-item">
	<div class="bkx-aa-list-item">
		<span class="bkx-aa-list-rank">{{ data.rank }}</span>
		<span class="bkx-aa-list-name">{{ data.name }}</span>
		<span class="bkx-aa-list-value">{{ data.value }}</span>
	</div>
</script>

<script type="text/template" id="tmpl-bkx-aa-insight">
	<div class="bkx-aa-insight bkx-aa-insight-{{ data.type }}">
		<span class="dashicons dashicons-{{ data.type === 'positive' ? 'yes-alt' : ( data.type === 'warning' ? 'warning' : 'info' ) }}"></span>
		<p>{{ data.message }}</p>
	</div>
</script>

<script type="text/template" id="tmpl-bkx-aa-comparison-change">
	<div class="bkx-aa-change-item">
		<div class="bkx-aa-change-label">{{ data.label }}</div>
		<div class="bkx-aa-change-values">
			<span class="period-a">{{ data.period_a }}</span>
			<span class="arrow {{ data.trend }}">→</span>
			<span class="period-b">{{ data.period_b }}</span>
		</div>
		<div class="bkx-aa-change-percent {{ data.percent_change >= 0 ? 'positive' : 'negative' }}">
			{{ data.percent_change >= 0 ? '+' : '' }}{{ data.percent_change }}%
		</div>
	</div>
</script>
