<?php
/**
 * Customer Journey Admin Page Template.
 *
 * @package BookingX\CustomerJourney
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap bkx-cj-wrap">
	<h1><?php esc_html_e( 'Customer Journey Analytics', 'bkx-customer-journey' ); ?></h1>

	<!-- Date Filter -->
	<div class="bkx-cj-filters">
		<div class="bkx-cj-date-range">
			<label for="bkx-cj-start-date"><?php esc_html_e( 'From:', 'bkx-customer-journey' ); ?></label>
			<input type="date" id="bkx-cj-start-date" class="bkx-cj-date-input" />

			<label for="bkx-cj-end-date"><?php esc_html_e( 'To:', 'bkx-customer-journey' ); ?></label>
			<input type="date" id="bkx-cj-end-date" class="bkx-cj-date-input" />

			<button type="button" id="bkx-cj-apply-filter" class="button button-primary">
				<?php esc_html_e( 'Apply', 'bkx-customer-journey' ); ?>
			</button>
		</div>

		<div class="bkx-cj-quick-ranges">
			<button type="button" class="button bkx-cj-range-btn" data-range="7"><?php esc_html_e( 'Last 7 Days', 'bkx-customer-journey' ); ?></button>
			<button type="button" class="button bkx-cj-range-btn active" data-range="30"><?php esc_html_e( 'Last 30 Days', 'bkx-customer-journey' ); ?></button>
			<button type="button" class="button bkx-cj-range-btn" data-range="90"><?php esc_html_e( 'Last 90 Days', 'bkx-customer-journey' ); ?></button>
		</div>
	</div>

	<!-- Tabs -->
	<nav class="nav-tab-wrapper bkx-cj-tabs">
		<a href="#overview" class="nav-tab nav-tab-active" data-tab="overview">
			<?php esc_html_e( 'Overview', 'bkx-customer-journey' ); ?>
		</a>
		<a href="#touchpoints" class="nav-tab" data-tab="touchpoints">
			<?php esc_html_e( 'Touchpoints', 'bkx-customer-journey' ); ?>
		</a>
		<a href="#lifecycle" class="nav-tab" data-tab="lifecycle">
			<?php esc_html_e( 'Lifecycle', 'bkx-customer-journey' ); ?>
		</a>
		<a href="#attribution" class="nav-tab" data-tab="attribution">
			<?php esc_html_e( 'Attribution', 'bkx-customer-journey' ); ?>
		</a>
		<a href="#customer" class="nav-tab" data-tab="customer">
			<?php esc_html_e( 'Customer Lookup', 'bkx-customer-journey' ); ?>
		</a>
	</nav>

	<!-- Overview Tab -->
	<div id="overview" class="bkx-cj-tab-content active">
		<!-- Summary Cards -->
		<div class="bkx-cj-summary-cards">
			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon">
					<span class="dashicons dashicons-visibility"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="total-journeys">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Total Journeys', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>

			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon success">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="conversion-rate">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Conversion Rate', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>

			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon info">
					<span class="dashicons dashicons-marker"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="avg-touchpoints">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Avg Touchpoints', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>

			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon warning">
					<span class="dashicons dashicons-clock"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="avg-duration">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Avg Duration', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Charts Row -->
		<div class="bkx-cj-charts-row">
			<div class="bkx-cj-chart-container half">
				<h3><?php esc_html_e( 'Conversion Funnel', 'bkx-customer-journey' ); ?></h3>
				<div class="bkx-cj-funnel" id="journey-funnel"></div>
			</div>

			<div class="bkx-cj-chart-container half">
				<h3><?php esc_html_e( 'Journey Outcomes', 'bkx-customer-journey' ); ?></h3>
				<canvas id="outcomes-chart"></canvas>
			</div>
		</div>

		<!-- Daily Trends -->
		<div class="bkx-cj-chart-container full">
			<h3><?php esc_html_e( 'Daily Trends', 'bkx-customer-journey' ); ?></h3>
			<canvas id="daily-trends-chart"></canvas>
		</div>

		<!-- Drop-off Points -->
		<div class="bkx-cj-table-container">
			<h3><?php esc_html_e( 'Top Drop-off Points', 'bkx-customer-journey' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Last Touchpoint', 'bkx-customer-journey' ); ?></th>
						<th><?php esc_html_e( 'Page', 'bkx-customer-journey' ); ?></th>
						<th><?php esc_html_e( 'Abandonments', 'bkx-customer-journey' ); ?></th>
					</tr>
				</thead>
				<tbody id="dropoff-table">
					<tr><td colspan="3" class="bkx-cj-loading"><?php esc_html_e( 'Loading...', 'bkx-customer-journey' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Touchpoints Tab -->
	<div id="touchpoints" class="bkx-cj-tab-content">
		<!-- Touchpoint Summary -->
		<div class="bkx-cj-summary-cards">
			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon">
					<span class="dashicons dashicons-chart-bar"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="total-touchpoints">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Total Touchpoints', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>

			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon info">
					<span class="dashicons dashicons-groups"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="unique-sessions">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Unique Sessions', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>

			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon success">
					<span class="dashicons dashicons-chart-line"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="avg-per-session">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Avg per Session', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>

			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon warning">
					<span class="dashicons dashicons-admin-page"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="avg-to-convert">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Avg to Convert', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Charts Row -->
		<div class="bkx-cj-charts-row">
			<div class="bkx-cj-chart-container half">
				<h3><?php esc_html_e( 'Touchpoints by Type', 'bkx-customer-journey' ); ?></h3>
				<canvas id="touchpoints-by-type-chart"></canvas>
			</div>

			<div class="bkx-cj-chart-container half">
				<h3><?php esc_html_e( 'Touchpoints by Device', 'bkx-customer-journey' ); ?></h3>
				<canvas id="touchpoints-by-device-chart"></canvas>
			</div>
		</div>

		<!-- Hourly Patterns -->
		<div class="bkx-cj-chart-container full">
			<h3><?php esc_html_e( 'Hourly Activity Patterns', 'bkx-customer-journey' ); ?></h3>
			<canvas id="hourly-patterns-chart"></canvas>
		</div>

		<!-- Popular Pages & Referrers -->
		<div class="bkx-cj-tables-row">
			<div class="bkx-cj-table-container half">
				<h3><?php esc_html_e( 'Popular Pages', 'bkx-customer-journey' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Page', 'bkx-customer-journey' ); ?></th>
							<th><?php esc_html_e( 'Views', 'bkx-customer-journey' ); ?></th>
							<th><?php esc_html_e( 'Unique', 'bkx-customer-journey' ); ?></th>
						</tr>
					</thead>
					<tbody id="popular-pages-table">
						<tr><td colspan="3" class="bkx-cj-loading"><?php esc_html_e( 'Loading...', 'bkx-customer-journey' ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<div class="bkx-cj-table-container half">
				<h3><?php esc_html_e( 'Traffic Sources', 'bkx-customer-journey' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source', 'bkx-customer-journey' ); ?></th>
							<th><?php esc_html_e( 'Visits', 'bkx-customer-journey' ); ?></th>
							<th><?php esc_html_e( 'Sessions', 'bkx-customer-journey' ); ?></th>
						</tr>
					</thead>
					<tbody id="referrer-sources-table">
						<tr><td colspan="3" class="bkx-cj-loading"><?php esc_html_e( 'Loading...', 'bkx-customer-journey' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Lifecycle Tab -->
	<div id="lifecycle" class="bkx-cj-tab-content">
		<!-- Lifecycle Stages -->
		<div class="bkx-cj-lifecycle-stages">
			<h3><?php esc_html_e( 'Customer Lifecycle Stages', 'bkx-customer-journey' ); ?></h3>
			<div id="lifecycle-stages" class="bkx-cj-stages-grid"></div>
		</div>

		<!-- Lifecycle Chart -->
		<div class="bkx-cj-chart-container full">
			<h3><?php esc_html_e( 'Lifecycle Distribution', 'bkx-customer-journey' ); ?></h3>
			<canvas id="lifecycle-chart"></canvas>
		</div>

		<!-- Stage Transitions -->
		<div class="bkx-cj-transitions">
			<h3><?php esc_html_e( 'Stage Transitions', 'bkx-customer-journey' ); ?></h3>
			<div id="lifecycle-transitions" class="bkx-cj-transitions-diagram"></div>
		</div>

		<!-- At Risk Customers -->
		<div class="bkx-cj-table-container">
			<h3><?php esc_html_e( 'Customers at Risk', 'bkx-customer-journey' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Customer', 'bkx-customer-journey' ); ?></th>
						<th><?php esc_html_e( 'Stage', 'bkx-customer-journey' ); ?></th>
						<th><?php esc_html_e( 'Churn Risk', 'bkx-customer-journey' ); ?></th>
						<th><?php esc_html_e( 'Days Inactive', 'bkx-customer-journey' ); ?></th>
						<th><?php esc_html_e( 'Total Revenue', 'bkx-customer-journey' ); ?></th>
					</tr>
				</thead>
				<tbody id="at-risk-table">
					<tr><td colspan="5" class="bkx-cj-loading"><?php esc_html_e( 'Loading...', 'bkx-customer-journey' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Attribution Tab -->
	<div id="attribution" class="bkx-cj-tab-content">
		<!-- Model Selector -->
		<div class="bkx-cj-attribution-models">
			<label for="attribution-model"><?php esc_html_e( 'Attribution Model:', 'bkx-customer-journey' ); ?></label>
			<select id="attribution-model" class="bkx-cj-model-select">
				<option value="first_touch"><?php esc_html_e( 'First Touch', 'bkx-customer-journey' ); ?></option>
				<option value="last_touch"><?php esc_html_e( 'Last Touch', 'bkx-customer-journey' ); ?></option>
				<option value="linear"><?php esc_html_e( 'Linear', 'bkx-customer-journey' ); ?></option>
				<option value="time_decay"><?php esc_html_e( 'Time Decay', 'bkx-customer-journey' ); ?></option>
				<option value="position"><?php esc_html_e( 'Position-Based', 'bkx-customer-journey' ); ?></option>
			</select>
		</div>

		<!-- Attribution Summary -->
		<div class="bkx-cj-summary-cards">
			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon success">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="attr-conversions">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Conversions', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>

			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="attr-revenue">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Total Revenue', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>

			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon info">
					<span class="dashicons dashicons-networking"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="attr-path-length">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Avg Path Length', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>

			<div class="bkx-cj-card">
				<div class="bkx-cj-card-icon warning">
					<span class="dashicons dashicons-randomize"></span>
				</div>
				<div class="bkx-cj-card-content">
					<span class="bkx-cj-card-value" id="attr-multi-touch">-</span>
					<span class="bkx-cj-card-label"><?php esc_html_e( 'Multi-Touch Rate', 'bkx-customer-journey' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Attribution Chart -->
		<div class="bkx-cj-charts-row">
			<div class="bkx-cj-chart-container half">
				<h3><?php esc_html_e( 'Channel Attribution', 'bkx-customer-journey' ); ?></h3>
				<canvas id="attribution-chart"></canvas>
			</div>

			<div class="bkx-cj-chart-container half">
				<h3><?php esc_html_e( 'Revenue by Channel', 'bkx-customer-journey' ); ?></h3>
				<canvas id="revenue-attribution-chart"></canvas>
			</div>
		</div>

		<!-- Top Conversion Paths -->
		<div class="bkx-cj-table-container">
			<h3><?php esc_html_e( 'Top Conversion Paths', 'bkx-customer-journey' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Path', 'bkx-customer-journey' ); ?></th>
						<th><?php esc_html_e( 'Conversions', 'bkx-customer-journey' ); ?></th>
						<th><?php esc_html_e( 'Revenue', 'bkx-customer-journey' ); ?></th>
					</tr>
				</thead>
				<tbody id="conversion-paths-table">
					<tr><td colspan="3" class="bkx-cj-loading"><?php esc_html_e( 'Loading...', 'bkx-customer-journey' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Customer Lookup Tab -->
	<div id="customer" class="bkx-cj-tab-content">
		<!-- Search -->
		<div class="bkx-cj-customer-search">
			<label for="customer-email"><?php esc_html_e( 'Customer Email:', 'bkx-customer-journey' ); ?></label>
			<input type="email" id="customer-email" class="regular-text" placeholder="<?php esc_attr_e( 'Enter customer email...', 'bkx-customer-journey' ); ?>" />
			<button type="button" id="lookup-customer" class="button button-primary">
				<?php esc_html_e( 'Lookup', 'bkx-customer-journey' ); ?>
			</button>
		</div>

		<!-- Customer Profile -->
		<div id="customer-profile" class="bkx-cj-customer-profile" style="display: none;">
			<div class="bkx-cj-profile-header">
				<div class="bkx-cj-profile-info">
					<h2 id="customer-email-display"></h2>
					<span id="customer-stage-badge" class="bkx-cj-stage-badge"></span>
				</div>
				<div class="bkx-cj-profile-metrics">
					<div class="bkx-cj-metric">
						<span class="bkx-cj-metric-value" id="customer-bookings">-</span>
						<span class="bkx-cj-metric-label"><?php esc_html_e( 'Bookings', 'bkx-customer-journey' ); ?></span>
					</div>
					<div class="bkx-cj-metric">
						<span class="bkx-cj-metric-value" id="customer-revenue">-</span>
						<span class="bkx-cj-metric-label"><?php esc_html_e( 'Revenue', 'bkx-customer-journey' ); ?></span>
					</div>
					<div class="bkx-cj-metric">
						<span class="bkx-cj-metric-value" id="customer-ltv">-</span>
						<span class="bkx-cj-metric-label"><?php esc_html_e( 'LTV Score', 'bkx-customer-journey' ); ?></span>
					</div>
					<div class="bkx-cj-metric">
						<span class="bkx-cj-metric-value" id="customer-churn-risk">-</span>
						<span class="bkx-cj-metric-label"><?php esc_html_e( 'Churn Risk', 'bkx-customer-journey' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Customer Timeline -->
			<div class="bkx-cj-customer-timeline">
				<h3><?php esc_html_e( 'Customer Journey Timeline', 'bkx-customer-journey' ); ?></h3>
				<div id="customer-timeline" class="bkx-cj-timeline"></div>
			</div>

			<!-- Customer Bookings -->
			<div class="bkx-cj-table-container">
				<h3><?php esc_html_e( 'Recent Bookings', 'bkx-customer-journey' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'bkx-customer-journey' ); ?></th>
							<th><?php esc_html_e( 'Date', 'bkx-customer-journey' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-customer-journey' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'bkx-customer-journey' ); ?></th>
						</tr>
					</thead>
					<tbody id="customer-bookings-table">
						<tr><td colspan="4"><?php esc_html_e( 'No bookings found', 'bkx-customer-journey' ); ?></td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- No Customer Selected -->
		<div id="no-customer" class="bkx-cj-no-customer">
			<span class="dashicons dashicons-search"></span>
			<p><?php esc_html_e( 'Enter a customer email to view their journey', 'bkx-customer-journey' ); ?></p>
		</div>
	</div>
</div>
