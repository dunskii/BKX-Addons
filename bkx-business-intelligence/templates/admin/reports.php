<?php
/**
 * Reports Management Template.
 *
 * @package BookingX\BusinessIntelligence
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap bkx-bi-reports">
	<h1><?php esc_html_e( 'Reports', 'bkx-business-intelligence' ); ?></h1>

	<div class="bkx-bi-reports-container">
		<!-- Report Generator -->
		<div class="bkx-bi-card bkx-bi-report-generator">
			<h2><?php esc_html_e( 'Generate Report', 'bkx-business-intelligence' ); ?></h2>

			<form id="bkx-bi-report-form" class="bkx-bi-form">
				<div class="bkx-bi-form-row">
					<label for="report-type"><?php esc_html_e( 'Report Type', 'bkx-business-intelligence' ); ?></label>
					<select id="report-type" name="report_type" class="bkx-bi-select" required>
						<option value=""><?php esc_html_e( 'Select a report type...', 'bkx-business-intelligence' ); ?></option>
						<option value="revenue_summary"><?php esc_html_e( 'Revenue Summary', 'bkx-business-intelligence' ); ?></option>
						<option value="booking_analysis"><?php esc_html_e( 'Booking Analysis', 'bkx-business-intelligence' ); ?></option>
						<option value="service_performance"><?php esc_html_e( 'Service Performance', 'bkx-business-intelligence' ); ?></option>
						<option value="staff_performance"><?php esc_html_e( 'Staff Performance', 'bkx-business-intelligence' ); ?></option>
						<option value="customer_insights"><?php esc_html_e( 'Customer Insights', 'bkx-business-intelligence' ); ?></option>
						<option value="trend_analysis"><?php esc_html_e( 'Trend Analysis', 'bkx-business-intelligence' ); ?></option>
						<option value="forecast_report"><?php esc_html_e( 'Forecast Report', 'bkx-business-intelligence' ); ?></option>
						<option value="executive_summary"><?php esc_html_e( 'Executive Summary', 'bkx-business-intelligence' ); ?></option>
					</select>
				</div>

				<div class="bkx-bi-form-row bkx-bi-form-row-inline">
					<div class="bkx-bi-form-field">
						<label for="report-start-date"><?php esc_html_e( 'Start Date', 'bkx-business-intelligence' ); ?></label>
						<input type="date" id="report-start-date" name="start_date" class="bkx-bi-date-input" required />
					</div>
					<div class="bkx-bi-form-field">
						<label for="report-end-date"><?php esc_html_e( 'End Date', 'bkx-business-intelligence' ); ?></label>
						<input type="date" id="report-end-date" name="end_date" class="bkx-bi-date-input" required />
					</div>
				</div>

				<div class="bkx-bi-form-row">
					<label for="report-name"><?php esc_html_e( 'Report Name (optional)', 'bkx-business-intelligence' ); ?></label>
					<input type="text" id="report-name" name="report_name" class="regular-text" placeholder="<?php esc_attr_e( 'Custom report name...', 'bkx-business-intelligence' ); ?>" />
				</div>

				<div class="bkx-bi-form-row">
					<label class="bkx-bi-checkbox-label">
						<input type="checkbox" id="report-save" name="save_report" value="1" />
						<?php esc_html_e( 'Save this report for later', 'bkx-business-intelligence' ); ?>
					</label>
				</div>

				<div id="report-schedule-options" class="bkx-bi-schedule-options" style="display: none;">
					<div class="bkx-bi-form-row">
						<label for="report-schedule"><?php esc_html_e( 'Schedule', 'bkx-business-intelligence' ); ?></label>
						<select id="report-schedule" name="schedule" class="bkx-bi-select">
							<option value=""><?php esc_html_e( 'No schedule (manual only)', 'bkx-business-intelligence' ); ?></option>
							<option value="daily"><?php esc_html_e( 'Daily', 'bkx-business-intelligence' ); ?></option>
							<option value="weekly"><?php esc_html_e( 'Weekly (Monday)', 'bkx-business-intelligence' ); ?></option>
							<option value="monthly"><?php esc_html_e( 'Monthly (1st)', 'bkx-business-intelligence' ); ?></option>
						</select>
					</div>

					<div class="bkx-bi-form-row">
						<label for="report-recipients"><?php esc_html_e( 'Email Recipients', 'bkx-business-intelligence' ); ?></label>
						<input type="text" id="report-recipients" name="recipients" class="regular-text" placeholder="<?php esc_attr_e( 'email@example.com, another@example.com', 'bkx-business-intelligence' ); ?>" />
						<p class="description"><?php esc_html_e( 'Comma-separated list of email addresses', 'bkx-business-intelligence' ); ?></p>
					</div>
				</div>

				<div class="bkx-bi-form-actions">
					<button type="submit" class="button button-primary button-large">
						<span class="dashicons dashicons-chart-area"></span>
						<?php esc_html_e( 'Generate Report', 'bkx-business-intelligence' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Report Preview -->
		<div class="bkx-bi-card bkx-bi-report-preview" id="report-preview" style="display: none;">
			<div class="bkx-bi-report-header">
				<h2 id="preview-title"><?php esc_html_e( 'Report Preview', 'bkx-business-intelligence' ); ?></h2>
				<div class="bkx-bi-report-actions">
					<button type="button" id="export-csv" class="button">
						<span class="dashicons dashicons-media-spreadsheet"></span>
						<?php esc_html_e( 'CSV', 'bkx-business-intelligence' ); ?>
					</button>
					<button type="button" id="export-pdf" class="button">
						<span class="dashicons dashicons-pdf"></span>
						<?php esc_html_e( 'PDF', 'bkx-business-intelligence' ); ?>
					</button>
					<button type="button" id="export-excel" class="button">
						<span class="dashicons dashicons-media-spreadsheet"></span>
						<?php esc_html_e( 'Excel', 'bkx-business-intelligence' ); ?>
					</button>
					<button type="button" id="email-report" class="button">
						<span class="dashicons dashicons-email"></span>
						<?php esc_html_e( 'Email', 'bkx-business-intelligence' ); ?>
					</button>
					<button type="button" id="print-report" class="button">
						<span class="dashicons dashicons-printer"></span>
						<?php esc_html_e( 'Print', 'bkx-business-intelligence' ); ?>
					</button>
				</div>
			</div>

			<div class="bkx-bi-report-meta" id="preview-meta"></div>

			<div class="bkx-bi-report-content" id="preview-content">
				<!-- Dynamic content will be inserted here -->
			</div>
		</div>
	</div>

	<!-- Saved Reports -->
	<div class="bkx-bi-card bkx-bi-saved-reports">
		<h2><?php esc_html_e( 'Saved Reports', 'bkx-business-intelligence' ); ?></h2>

		<div class="bkx-bi-table-controls">
			<div class="bkx-bi-table-filter">
				<label for="filter-type"><?php esc_html_e( 'Filter by type:', 'bkx-business-intelligence' ); ?></label>
				<select id="filter-type" class="bkx-bi-select">
					<option value=""><?php esc_html_e( 'All Types', 'bkx-business-intelligence' ); ?></option>
					<option value="revenue_summary"><?php esc_html_e( 'Revenue Summary', 'bkx-business-intelligence' ); ?></option>
					<option value="booking_analysis"><?php esc_html_e( 'Booking Analysis', 'bkx-business-intelligence' ); ?></option>
					<option value="service_performance"><?php esc_html_e( 'Service Performance', 'bkx-business-intelligence' ); ?></option>
					<option value="staff_performance"><?php esc_html_e( 'Staff Performance', 'bkx-business-intelligence' ); ?></option>
					<option value="customer_insights"><?php esc_html_e( 'Customer Insights', 'bkx-business-intelligence' ); ?></option>
					<option value="executive_summary"><?php esc_html_e( 'Executive Summary', 'bkx-business-intelligence' ); ?></option>
				</select>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped" id="saved-reports-table">
			<thead>
				<tr>
					<th class="column-name"><?php esc_html_e( 'Report Name', 'bkx-business-intelligence' ); ?></th>
					<th class="column-type"><?php esc_html_e( 'Type', 'bkx-business-intelligence' ); ?></th>
					<th class="column-schedule"><?php esc_html_e( 'Schedule', 'bkx-business-intelligence' ); ?></th>
					<th class="column-created"><?php esc_html_e( 'Created', 'bkx-business-intelligence' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'bkx-business-intelligence' ); ?></th>
				</tr>
			</thead>
			<tbody id="saved-reports-body">
				<tr class="bkx-bi-loading-row">
					<td colspan="5">
						<span class="bkx-bi-loading"></span>
						<?php esc_html_e( 'Loading saved reports...', 'bkx-business-intelligence' ); ?>
					</td>
				</tr>
			</tbody>
		</table>

		<div class="bkx-bi-pagination" id="saved-reports-pagination"></div>
	</div>
</div>

<!-- Email Report Modal -->
<div id="email-report-modal" class="bkx-bi-modal" style="display: none;">
	<div class="bkx-bi-modal-content">
		<div class="bkx-bi-modal-header">
			<h3><?php esc_html_e( 'Email Report', 'bkx-business-intelligence' ); ?></h3>
			<button type="button" class="bkx-bi-modal-close">&times;</button>
		</div>
		<div class="bkx-bi-modal-body">
			<form id="email-report-form">
				<div class="bkx-bi-form-row">
					<label for="email-recipients"><?php esc_html_e( 'Recipients', 'bkx-business-intelligence' ); ?></label>
					<input type="text" id="email-recipients" name="recipients" class="regular-text" required placeholder="<?php esc_attr_e( 'email@example.com', 'bkx-business-intelligence' ); ?>" />
				</div>
				<div class="bkx-bi-form-row">
					<label for="email-format"><?php esc_html_e( 'Format', 'bkx-business-intelligence' ); ?></label>
					<select id="email-format" name="format" class="bkx-bi-select">
						<option value="pdf"><?php esc_html_e( 'PDF', 'bkx-business-intelligence' ); ?></option>
						<option value="csv"><?php esc_html_e( 'CSV', 'bkx-business-intelligence' ); ?></option>
					</select>
				</div>
			</form>
		</div>
		<div class="bkx-bi-modal-footer">
			<button type="button" class="button bkx-bi-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-business-intelligence' ); ?></button>
			<button type="button" class="button button-primary" id="send-email-report"><?php esc_html_e( 'Send', 'bkx-business-intelligence' ); ?></button>
		</div>
	</div>
</div>

<script type="text/template" id="tmpl-bkx-bi-saved-report-row">
	<tr data-report-id="{{ data.id }}">
		<td class="column-name">
			<strong>{{ data.report_name }}</strong>
		</td>
		<td class="column-type">{{ data.report_type_label }}</td>
		<td class="column-schedule">
			<# if ( data.schedule ) { #>
				{{ data.schedule_label }}
				<br><small><?php esc_html_e( 'Next:', 'bkx-business-intelligence' ); ?> {{ data.next_run }}</small>
			<# } else { #>
				<em><?php esc_html_e( 'Manual', 'bkx-business-intelligence' ); ?></em>
			<# } #>
		</td>
		<td class="column-created">{{ data.created_at }}</td>
		<td class="column-actions">
			<button type="button" class="button button-small bkx-bi-view-report" data-id="{{ data.id }}">
				<span class="dashicons dashicons-visibility"></span>
			</button>
			<button type="button" class="button button-small bkx-bi-run-report" data-id="{{ data.id }}">
				<span class="dashicons dashicons-update"></span>
			</button>
			<button type="button" class="button button-small bkx-bi-delete-report" data-id="{{ data.id }}">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</td>
	</tr>
</script>

<script type="text/template" id="tmpl-bkx-bi-report-summary">
	<div class="bkx-bi-report-summary">
		<# if ( data.summary ) { #>
			<div class="bkx-bi-summary-grid">
				<# for ( var key in data.summary ) { #>
					<div class="bkx-bi-summary-item">
						<div class="bkx-bi-summary-value">{{ data.summary[key] }}</div>
						<div class="bkx-bi-summary-label">{{ key.replace(/_/g, ' ') }}</div>
					</div>
				<# } #>
			</div>
		<# } #>
	</div>
</script>

<script type="text/template" id="tmpl-bkx-bi-report-table">
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<# for ( var i = 0; i < data.headers.length; i++ ) { #>
					<th>{{ data.headers[i] }}</th>
				<# } #>
			</tr>
		</thead>
		<tbody>
			<# for ( var i = 0; i < data.rows.length; i++ ) { #>
				<tr>
					<# for ( var j = 0; j < data.rows[i].length; j++ ) { #>
						<td>{{ data.rows[i][j] }}</td>
					<# } #>
				</tr>
			<# } #>
		</tbody>
	</table>
</script>
