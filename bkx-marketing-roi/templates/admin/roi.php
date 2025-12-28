<?php
/**
 * Marketing ROI Admin Page Template.
 *
 * @package BookingX\MarketingROI
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap bkx-roi-wrap">
	<h1><?php esc_html_e( 'Marketing ROI Tracker', 'bkx-marketing-roi' ); ?></h1>

	<!-- Date Filter -->
	<div class="bkx-roi-filters">
		<div class="bkx-roi-date-range">
			<label for="bkx-roi-start-date"><?php esc_html_e( 'From:', 'bkx-marketing-roi' ); ?></label>
			<input type="date" id="bkx-roi-start-date" class="bkx-roi-date-input" />

			<label for="bkx-roi-end-date"><?php esc_html_e( 'To:', 'bkx-marketing-roi' ); ?></label>
			<input type="date" id="bkx-roi-end-date" class="bkx-roi-date-input" />

			<button type="button" id="bkx-roi-apply-filter" class="button button-primary">
				<?php esc_html_e( 'Apply', 'bkx-marketing-roi' ); ?>
			</button>
		</div>

		<div class="bkx-roi-quick-ranges">
			<button type="button" class="button bkx-roi-range-btn" data-range="7"><?php esc_html_e( '7 Days', 'bkx-marketing-roi' ); ?></button>
			<button type="button" class="button bkx-roi-range-btn active" data-range="30"><?php esc_html_e( '30 Days', 'bkx-marketing-roi' ); ?></button>
			<button type="button" class="button bkx-roi-range-btn" data-range="90"><?php esc_html_e( '90 Days', 'bkx-marketing-roi' ); ?></button>
		</div>
	</div>

	<!-- Tabs -->
	<nav class="nav-tab-wrapper bkx-roi-tabs">
		<a href="#dashboard" class="nav-tab nav-tab-active" data-tab="dashboard">
			<?php esc_html_e( 'Dashboard', 'bkx-marketing-roi' ); ?>
		</a>
		<a href="#campaigns" class="nav-tab" data-tab="campaigns">
			<?php esc_html_e( 'Campaigns', 'bkx-marketing-roi' ); ?>
		</a>
		<a href="#utm-reports" class="nav-tab" data-tab="utm-reports">
			<?php esc_html_e( 'UTM Reports', 'bkx-marketing-roi' ); ?>
		</a>
	</nav>

	<!-- Dashboard Tab -->
	<div id="dashboard" class="bkx-roi-tab-content active">
		<!-- Summary Cards -->
		<div class="bkx-roi-summary-cards">
			<div class="bkx-roi-card">
				<div class="bkx-roi-card-icon">
					<span class="dashicons dashicons-visibility"></span>
				</div>
				<div class="bkx-roi-card-content">
					<span class="bkx-roi-card-value" id="total-visits">-</span>
					<span class="bkx-roi-card-label"><?php esc_html_e( 'Total Visits', 'bkx-marketing-roi' ); ?></span>
				</div>
			</div>

			<div class="bkx-roi-card">
				<div class="bkx-roi-card-icon success">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="bkx-roi-card-content">
					<span class="bkx-roi-card-value" id="conversions">-</span>
					<span class="bkx-roi-card-label"><?php esc_html_e( 'Conversions', 'bkx-marketing-roi' ); ?></span>
				</div>
			</div>

			<div class="bkx-roi-card">
				<div class="bkx-roi-card-icon info">
					<span class="dashicons dashicons-chart-line"></span>
				</div>
				<div class="bkx-roi-card-content">
					<span class="bkx-roi-card-value" id="conversion-rate">-</span>
					<span class="bkx-roi-card-label"><?php esc_html_e( 'Conv. Rate', 'bkx-marketing-roi' ); ?></span>
				</div>
			</div>

			<div class="bkx-roi-card">
				<div class="bkx-roi-card-icon">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
				<div class="bkx-roi-card-content">
					<span class="bkx-roi-card-value" id="total-revenue">-</span>
					<span class="bkx-roi-card-label"><?php esc_html_e( 'Revenue', 'bkx-marketing-roi' ); ?></span>
				</div>
			</div>
		</div>

		<!-- ROI Cards -->
		<div class="bkx-roi-summary-cards">
			<div class="bkx-roi-card highlight">
				<div class="bkx-roi-card-icon success">
					<span class="dashicons dashicons-chart-bar"></span>
				</div>
				<div class="bkx-roi-card-content">
					<span class="bkx-roi-card-value" id="roi-percent">-</span>
					<span class="bkx-roi-card-label"><?php esc_html_e( 'ROI', 'bkx-marketing-roi' ); ?></span>
				</div>
			</div>

			<div class="bkx-roi-card">
				<div class="bkx-roi-card-icon warning">
					<span class="dashicons dashicons-money"></span>
				</div>
				<div class="bkx-roi-card-content">
					<span class="bkx-roi-card-value" id="total-cost">-</span>
					<span class="bkx-roi-card-label"><?php esc_html_e( 'Total Cost', 'bkx-marketing-roi' ); ?></span>
				</div>
			</div>

			<div class="bkx-roi-card">
				<div class="bkx-roi-card-icon info">
					<span class="dashicons dashicons-performance"></span>
				</div>
				<div class="bkx-roi-card-content">
					<span class="bkx-roi-card-value" id="roas">-</span>
					<span class="bkx-roi-card-label"><?php esc_html_e( 'ROAS', 'bkx-marketing-roi' ); ?></span>
				</div>
			</div>

			<div class="bkx-roi-card">
				<div class="bkx-roi-card-icon">
					<span class="dashicons dashicons-admin-users"></span>
				</div>
				<div class="bkx-roi-card-content">
					<span class="bkx-roi-card-value" id="cpa">-</span>
					<span class="bkx-roi-card-label"><?php esc_html_e( 'CPA', 'bkx-marketing-roi' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Charts Row -->
		<div class="bkx-roi-charts-row">
			<div class="bkx-roi-chart-container half">
				<h3><?php esc_html_e( 'Performance by Source', 'bkx-marketing-roi' ); ?></h3>
				<canvas id="source-chart"></canvas>
			</div>

			<div class="bkx-roi-chart-container half">
				<h3><?php esc_html_e( 'Performance by Medium', 'bkx-marketing-roi' ); ?></h3>
				<canvas id="medium-chart"></canvas>
			</div>
		</div>

		<!-- Daily Trends -->
		<div class="bkx-roi-chart-container full">
			<h3><?php esc_html_e( 'Daily Performance', 'bkx-marketing-roi' ); ?></h3>
			<canvas id="daily-trends-chart"></canvas>
		</div>

		<!-- Campaign Performance Table -->
		<div class="bkx-roi-table-container">
			<h3><?php esc_html_e( 'Campaign Performance', 'bkx-marketing-roi' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Campaign', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'Visits', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'Conv.', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'Rate', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'Revenue', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'Cost', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'ROI', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'ROAS', 'bkx-marketing-roi' ); ?></th>
					</tr>
				</thead>
				<tbody id="campaigns-table">
					<tr><td colspan="8" class="bkx-roi-loading"><?php esc_html_e( 'Loading...', 'bkx-marketing-roi' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Campaigns Tab -->
	<div id="campaigns" class="bkx-roi-tab-content">
		<div class="bkx-roi-campaigns-header">
			<h2><?php esc_html_e( 'Manage Campaigns', 'bkx-marketing-roi' ); ?></h2>
			<button type="button" id="add-campaign-btn" class="button button-primary">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add Campaign', 'bkx-marketing-roi' ); ?>
			</button>
		</div>

		<!-- Campaigns List -->
		<div class="bkx-roi-campaigns-list" id="campaigns-list">
			<p class="bkx-roi-loading"><?php esc_html_e( 'Loading campaigns...', 'bkx-marketing-roi' ); ?></p>
		</div>
	</div>

	<!-- UTM Reports Tab -->
	<div id="utm-reports" class="bkx-roi-tab-content">
		<!-- Group By Selector -->
		<div class="bkx-roi-report-options">
			<label for="utm-group-by"><?php esc_html_e( 'Group By:', 'bkx-marketing-roi' ); ?></label>
			<select id="utm-group-by" class="bkx-roi-select">
				<option value="source"><?php esc_html_e( 'Source', 'bkx-marketing-roi' ); ?></option>
				<option value="medium"><?php esc_html_e( 'Medium', 'bkx-marketing-roi' ); ?></option>
				<option value="campaign"><?php esc_html_e( 'Campaign', 'bkx-marketing-roi' ); ?></option>
				<option value="content"><?php esc_html_e( 'Content', 'bkx-marketing-roi' ); ?></option>
				<option value="term"><?php esc_html_e( 'Term', 'bkx-marketing-roi' ); ?></option>
			</select>

			<button type="button" id="export-utm-report" class="button">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'bkx-marketing-roi' ); ?>
			</button>
		</div>

		<!-- UTM Chart -->
		<div class="bkx-roi-chart-container full">
			<h3><?php esc_html_e( 'UTM Performance', 'bkx-marketing-roi' ); ?></h3>
			<canvas id="utm-chart"></canvas>
		</div>

		<!-- UTM Table -->
		<div class="bkx-roi-table-container">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th id="utm-header-value"><?php esc_html_e( 'Source', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'Visits', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'Conversions', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'Conv. Rate', 'bkx-marketing-roi' ); ?></th>
						<th><?php esc_html_e( 'Revenue', 'bkx-marketing-roi' ); ?></th>
					</tr>
				</thead>
				<tbody id="utm-table">
					<tr><td colspan="5" class="bkx-roi-loading"><?php esc_html_e( 'Loading...', 'bkx-marketing-roi' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Campaign Modal -->
<div id="campaign-modal" class="bkx-roi-modal" style="display:none;">
	<div class="bkx-roi-modal-content">
		<div class="bkx-roi-modal-header">
			<h2 id="campaign-modal-title"><?php esc_html_e( 'Add Campaign', 'bkx-marketing-roi' ); ?></h2>
			<button type="button" class="bkx-roi-modal-close">&times;</button>
		</div>
		<div class="bkx-roi-modal-body">
			<form id="campaign-form">
				<input type="hidden" name="id" id="campaign-id" />

				<div class="bkx-roi-form-row">
					<label for="campaign-name"><?php esc_html_e( 'Campaign Name', 'bkx-marketing-roi' ); ?> *</label>
					<input type="text" id="campaign-name" name="campaign_name" required />
				</div>

				<div class="bkx-roi-form-grid">
					<div class="bkx-roi-form-row">
						<label for="campaign-source"><?php esc_html_e( 'UTM Source', 'bkx-marketing-roi' ); ?></label>
						<input type="text" id="campaign-source" name="utm_source" placeholder="google, facebook, newsletter" />
					</div>

					<div class="bkx-roi-form-row">
						<label for="campaign-medium"><?php esc_html_e( 'UTM Medium', 'bkx-marketing-roi' ); ?></label>
						<input type="text" id="campaign-medium" name="utm_medium" placeholder="cpc, email, social" />
					</div>
				</div>

				<div class="bkx-roi-form-row">
					<label for="campaign-utm-campaign"><?php esc_html_e( 'UTM Campaign', 'bkx-marketing-roi' ); ?></label>
					<input type="text" id="campaign-utm-campaign" name="utm_campaign" placeholder="spring_sale, new_launch" />
				</div>

				<div class="bkx-roi-form-grid">
					<div class="bkx-roi-form-row">
						<label for="campaign-content"><?php esc_html_e( 'UTM Content', 'bkx-marketing-roi' ); ?></label>
						<input type="text" id="campaign-content" name="utm_content" placeholder="banner_v1, text_link" />
					</div>

					<div class="bkx-roi-form-row">
						<label for="campaign-term"><?php esc_html_e( 'UTM Term', 'bkx-marketing-roi' ); ?></label>
						<input type="text" id="campaign-term" name="utm_term" placeholder="booking+software" />
					</div>
				</div>

				<div class="bkx-roi-form-grid">
					<div class="bkx-roi-form-row">
						<label for="campaign-budget"><?php esc_html_e( 'Budget', 'bkx-marketing-roi' ); ?></label>
						<input type="number" id="campaign-budget" name="budget" step="0.01" min="0" />
					</div>

					<div class="bkx-roi-form-row">
						<label for="campaign-status"><?php esc_html_e( 'Status', 'bkx-marketing-roi' ); ?></label>
						<select id="campaign-status" name="status">
							<option value="active"><?php esc_html_e( 'Active', 'bkx-marketing-roi' ); ?></option>
							<option value="paused"><?php esc_html_e( 'Paused', 'bkx-marketing-roi' ); ?></option>
							<option value="ended"><?php esc_html_e( 'Ended', 'bkx-marketing-roi' ); ?></option>
						</select>
					</div>
				</div>

				<div class="bkx-roi-form-grid">
					<div class="bkx-roi-form-row">
						<label for="campaign-start"><?php esc_html_e( 'Start Date', 'bkx-marketing-roi' ); ?></label>
						<input type="date" id="campaign-start" name="start_date" />
					</div>

					<div class="bkx-roi-form-row">
						<label for="campaign-end"><?php esc_html_e( 'End Date', 'bkx-marketing-roi' ); ?></label>
						<input type="date" id="campaign-end" name="end_date" />
					</div>
				</div>

				<div class="bkx-roi-form-row">
					<label for="campaign-notes"><?php esc_html_e( 'Notes', 'bkx-marketing-roi' ); ?></label>
					<textarea id="campaign-notes" name="notes" rows="3"></textarea>
				</div>
			</form>
		</div>
		<div class="bkx-roi-modal-footer">
			<button type="button" class="button bkx-roi-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-marketing-roi' ); ?></button>
			<button type="button" id="save-campaign-btn" class="button button-primary"><?php esc_html_e( 'Save Campaign', 'bkx-marketing-roi' ); ?></button>
		</div>
	</div>
</div>

<!-- Add Cost Modal -->
<div id="cost-modal" class="bkx-roi-modal" style="display:none;">
	<div class="bkx-roi-modal-content bkx-roi-modal-small">
		<div class="bkx-roi-modal-header">
			<h2><?php esc_html_e( 'Add Cost', 'bkx-marketing-roi' ); ?></h2>
			<button type="button" class="bkx-roi-modal-close">&times;</button>
		</div>
		<div class="bkx-roi-modal-body">
			<form id="cost-form">
				<input type="hidden" name="campaign_id" id="cost-campaign-id" />

				<div class="bkx-roi-form-row">
					<label for="cost-date"><?php esc_html_e( 'Date', 'bkx-marketing-roi' ); ?> *</label>
					<input type="date" id="cost-date" name="cost_date" required />
				</div>

				<div class="bkx-roi-form-row">
					<label for="cost-amount"><?php esc_html_e( 'Amount', 'bkx-marketing-roi' ); ?> *</label>
					<input type="number" id="cost-amount" name="amount" step="0.01" min="0" required />
				</div>

				<div class="bkx-roi-form-row">
					<label for="cost-type"><?php esc_html_e( 'Type', 'bkx-marketing-roi' ); ?></label>
					<select id="cost-type" name="cost_type">
						<option value="ad_spend"><?php esc_html_e( 'Ad Spend', 'bkx-marketing-roi' ); ?></option>
						<option value="creative"><?php esc_html_e( 'Creative', 'bkx-marketing-roi' ); ?></option>
						<option value="agency"><?php esc_html_e( 'Agency Fee', 'bkx-marketing-roi' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'bkx-marketing-roi' ); ?></option>
					</select>
				</div>

				<div class="bkx-roi-form-row">
					<label for="cost-notes"><?php esc_html_e( 'Notes', 'bkx-marketing-roi' ); ?></label>
					<input type="text" id="cost-notes" name="notes" />
				</div>
			</form>
		</div>
		<div class="bkx-roi-modal-footer">
			<button type="button" class="button bkx-roi-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-marketing-roi' ); ?></button>
			<button type="button" id="save-cost-btn" class="button button-primary"><?php esc_html_e( 'Add Cost', 'bkx-marketing-roi' ); ?></button>
		</div>
	</div>
</div>
