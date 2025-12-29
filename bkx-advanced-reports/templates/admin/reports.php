<?php
/**
 * Advanced Reports Admin Template.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'revenue';
$tabs       = array(
	'revenue'   => __( 'Revenue', 'bkx-advanced-reports' ),
	'bookings'  => __( 'Bookings', 'bkx-advanced-reports' ),
	'staff'     => __( 'Staff Performance', 'bkx-advanced-reports' ),
	'customers' => __( 'Customers', 'bkx-advanced-reports' ),
	'exports'   => __( 'Exports', 'bkx-advanced-reports' ),
);

// Date range defaults.
$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : gmdate( 'Y-m-d' );
?>

<div class="wrap bkx-advanced-reports">
	<h1><?php esc_html_e( 'Advanced Reports', 'bkx-advanced-reports' ); ?></h1>

	<!-- Tabs Navigation -->
	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<!-- Date Range Picker -->
	<div class="bkx-reports-toolbar">
		<div class="bkx-date-range">
			<label><?php esc_html_e( 'Date Range:', 'bkx-advanced-reports' ); ?></label>
			<input type="text" id="bkx-date-from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From', 'bkx-advanced-reports' ); ?>">
			<span class="bkx-date-separator"><?php esc_html_e( 'to', 'bkx-advanced-reports' ); ?></span>
			<input type="text" id="bkx-date-to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To', 'bkx-advanced-reports' ); ?>">
			<button type="button" class="button" id="bkx-apply-dates"><?php esc_html_e( 'Apply', 'bkx-advanced-reports' ); ?></button>
		</div>
		<div class="bkx-quick-ranges">
			<button type="button" class="button" data-range="today"><?php esc_html_e( 'Today', 'bkx-advanced-reports' ); ?></button>
			<button type="button" class="button" data-range="week"><?php esc_html_e( 'This Week', 'bkx-advanced-reports' ); ?></button>
			<button type="button" class="button" data-range="month"><?php esc_html_e( 'This Month', 'bkx-advanced-reports' ); ?></button>
			<button type="button" class="button" data-range="quarter"><?php esc_html_e( 'This Quarter', 'bkx-advanced-reports' ); ?></button>
			<button type="button" class="button" data-range="year"><?php esc_html_e( 'This Year', 'bkx-advanced-reports' ); ?></button>
		</div>
	</div>

	<!-- Tab Content -->
	<div class="bkx-reports-content">
		<?php if ( 'revenue' === $active_tab ) : ?>
			<!-- Revenue Reports -->
			<div class="bkx-report-section" id="revenue-reports">
				<!-- Summary Cards -->
				<div class="bkx-summary-cards" id="revenue-summary">
					<div class="bkx-card bkx-card-revenue">
						<div class="bkx-card-icon"><span class="dashicons dashicons-chart-area"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="total_revenue">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Total Revenue', 'bkx-advanced-reports' ); ?></span>
						</div>
						<div class="bkx-card-change" data-field="revenue_change"></div>
					</div>
					<div class="bkx-card bkx-card-bookings">
						<div class="bkx-card-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="total_bookings">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Total Bookings', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card bkx-card-average">
						<div class="bkx-card-icon"><span class="dashicons dashicons-performance"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="avg_booking_value">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Avg Booking Value', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card bkx-card-projected">
						<div class="bkx-card-icon"><span class="dashicons dashicons-trending-up"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="projected_month">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Projected This Month', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Revenue Trend Chart -->
				<div class="bkx-chart-container">
					<h3><?php esc_html_e( 'Revenue Trend', 'bkx-advanced-reports' ); ?></h3>
					<canvas id="revenue-trend-chart"></canvas>
				</div>

				<!-- Revenue by Service/Staff -->
				<div class="bkx-charts-row">
					<div class="bkx-chart-container bkx-chart-half">
						<h3><?php esc_html_e( 'Revenue by Service', 'bkx-advanced-reports' ); ?></h3>
						<canvas id="revenue-service-chart"></canvas>
					</div>
					<div class="bkx-chart-container bkx-chart-half">
						<h3><?php esc_html_e( 'Revenue by Staff', 'bkx-advanced-reports' ); ?></h3>
						<canvas id="revenue-staff-chart"></canvas>
					</div>
				</div>
			</div>

		<?php elseif ( 'bookings' === $active_tab ) : ?>
			<!-- Bookings Reports -->
			<div class="bkx-report-section" id="bookings-reports">
				<!-- Summary Cards -->
				<div class="bkx-summary-cards" id="bookings-summary">
					<div class="bkx-card bkx-card-total">
						<div class="bkx-card-icon"><span class="dashicons dashicons-calendar"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="total">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Total Bookings', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card bkx-card-completed">
						<div class="bkx-card-icon"><span class="dashicons dashicons-yes-alt"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="completed">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Completed', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card bkx-card-cancelled">
						<div class="bkx-card-icon"><span class="dashicons dashicons-dismiss"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="cancelled">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Cancelled', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card bkx-card-completion">
						<div class="bkx-card-icon"><span class="dashicons dashicons-chart-pie"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="completion_rate">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Completion Rate', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Bookings Trend Chart -->
				<div class="bkx-chart-container">
					<h3><?php esc_html_e( 'Booking Trend', 'bkx-advanced-reports' ); ?></h3>
					<canvas id="bookings-trend-chart"></canvas>
				</div>

				<!-- Day/Time Distribution -->
				<div class="bkx-charts-row">
					<div class="bkx-chart-container bkx-chart-half">
						<h3><?php esc_html_e( 'Bookings by Day of Week', 'bkx-advanced-reports' ); ?></h3>
						<canvas id="bookings-day-chart"></canvas>
					</div>
					<div class="bkx-chart-container bkx-chart-half">
						<h3><?php esc_html_e( 'Bookings by Time of Day', 'bkx-advanced-reports' ); ?></h3>
						<canvas id="bookings-time-chart"></canvas>
					</div>
				</div>

				<!-- Peak Times Heatmap -->
				<div class="bkx-chart-container">
					<h3><?php esc_html_e( 'Peak Times Heatmap', 'bkx-advanced-reports' ); ?></h3>
					<div id="peak-times-heatmap" class="bkx-heatmap"></div>
				</div>
			</div>

		<?php elseif ( 'staff' === $active_tab ) : ?>
			<!-- Staff Reports -->
			<div class="bkx-report-section" id="staff-reports">
				<!-- Summary Cards -->
				<div class="bkx-summary-cards" id="staff-summary">
					<div class="bkx-card">
						<div class="bkx-card-icon"><span class="dashicons dashicons-groups"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="total_staff">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Total Staff', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card">
						<div class="bkx-card-icon"><span class="dashicons dashicons-businessman"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="active_staff">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Active Staff', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card">
						<div class="bkx-card-icon"><span class="dashicons dashicons-tickets-alt"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="average_bookings">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Avg Bookings/Staff', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card">
						<div class="bkx-card-icon"><span class="dashicons dashicons-clock"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="staff_utilization_rate">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Utilization Rate', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
				</div>

				<!-- Top Performers Table -->
				<div class="bkx-table-container">
					<h3><?php esc_html_e( 'Top Performers', 'bkx-advanced-reports' ); ?></h3>
					<table class="wp-list-table widefat fixed striped" id="top-performers-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Rank', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Staff Member', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Bookings', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Completed', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Revenue', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Completion Rate', 'bkx-advanced-reports' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>

				<!-- Utilization Chart -->
				<div class="bkx-chart-container">
					<h3><?php esc_html_e( 'Staff Utilization', 'bkx-advanced-reports' ); ?></h3>
					<canvas id="staff-utilization-chart"></canvas>
				</div>

				<!-- Performance Comparison -->
				<div class="bkx-chart-container">
					<h3><?php esc_html_e( 'Performance Comparison', 'bkx-advanced-reports' ); ?></h3>
					<canvas id="staff-comparison-chart"></canvas>
				</div>
			</div>

		<?php elseif ( 'customers' === $active_tab ) : ?>
			<!-- Customer Reports -->
			<div class="bkx-report-section" id="customer-reports">
				<!-- Summary Cards -->
				<div class="bkx-summary-cards" id="customer-summary">
					<div class="bkx-card">
						<div class="bkx-card-icon"><span class="dashicons dashicons-admin-users"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="unique_customers">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Unique Customers', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card">
						<div class="bkx-card-icon"><span class="dashicons dashicons-star-filled"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="avg_bookings_per_customer">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Avg Bookings/Customer', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card">
						<div class="bkx-card-icon"><span class="dashicons dashicons-money-alt"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="avg_revenue_per_customer">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Avg Revenue/Customer', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
					<div class="bkx-card">
						<div class="bkx-card-icon"><span class="dashicons dashicons-update"></span></div>
						<div class="bkx-card-content">
							<span class="bkx-card-value" data-field="retention_rate">--</span>
							<span class="bkx-card-label"><?php esc_html_e( 'Retention Rate', 'bkx-advanced-reports' ); ?></span>
						</div>
					</div>
				</div>

				<!-- New vs Returning -->
				<div class="bkx-charts-row">
					<div class="bkx-chart-container bkx-chart-half">
						<h3><?php esc_html_e( 'New vs Returning Customers', 'bkx-advanced-reports' ); ?></h3>
						<canvas id="new-returning-chart"></canvas>
					</div>
					<div class="bkx-chart-container bkx-chart-half">
						<h3><?php esc_html_e( 'Customer Lifetime Value Distribution', 'bkx-advanced-reports' ); ?></h3>
						<canvas id="ltv-distribution-chart"></canvas>
					</div>
				</div>

				<!-- Top Customers Table -->
				<div class="bkx-table-container">
					<h3><?php esc_html_e( 'Top Customers', 'bkx-advanced-reports' ); ?></h3>
					<table class="wp-list-table widefat fixed striped" id="top-customers-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Customer', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Email', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Bookings', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Total Spent', 'bkx-advanced-reports' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>

				<!-- Customer Acquisition Trend -->
				<div class="bkx-chart-container">
					<h3><?php esc_html_e( 'Customer Acquisition Trend', 'bkx-advanced-reports' ); ?></h3>
					<canvas id="acquisition-trend-chart"></canvas>
				</div>
			</div>

		<?php elseif ( 'exports' === $active_tab ) : ?>
			<!-- Exports -->
			<div class="bkx-report-section" id="exports-section">
				<!-- Export Options -->
				<div class="bkx-export-options">
					<h3><?php esc_html_e( 'Export Reports', 'bkx-advanced-reports' ); ?></h3>
					<p><?php esc_html_e( 'Generate downloadable reports in various formats.', 'bkx-advanced-reports' ); ?></p>

					<form id="bkx-export-form" class="bkx-export-form">
						<?php wp_nonce_field( 'bkx_export_report', 'bkx_export_nonce' ); ?>

						<div class="bkx-form-row">
							<label for="export-type"><?php esc_html_e( 'Report Type', 'bkx-advanced-reports' ); ?></label>
							<select id="export-type" name="report_type">
								<option value="revenue"><?php esc_html_e( 'Revenue Report', 'bkx-advanced-reports' ); ?></option>
								<option value="bookings"><?php esc_html_e( 'Bookings Report', 'bkx-advanced-reports' ); ?></option>
								<option value="staff"><?php esc_html_e( 'Staff Performance Report', 'bkx-advanced-reports' ); ?></option>
								<option value="customers"><?php esc_html_e( 'Customer Report', 'bkx-advanced-reports' ); ?></option>
								<option value="comprehensive"><?php esc_html_e( 'Comprehensive Report (All)', 'bkx-advanced-reports' ); ?></option>
							</select>
						</div>

						<div class="bkx-form-row">
							<label for="export-format"><?php esc_html_e( 'Format', 'bkx-advanced-reports' ); ?></label>
							<select id="export-format" name="format">
								<option value="csv"><?php esc_html_e( 'CSV', 'bkx-advanced-reports' ); ?></option>
								<option value="xlsx"><?php esc_html_e( 'Excel (XLSX)', 'bkx-advanced-reports' ); ?></option>
								<option value="pdf"><?php esc_html_e( 'PDF', 'bkx-advanced-reports' ); ?></option>
							</select>
						</div>

						<div class="bkx-form-row">
							<label for="export-date-from"><?php esc_html_e( 'Date Range', 'bkx-advanced-reports' ); ?></label>
							<input type="text" id="export-date-from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
							<span class="bkx-date-separator"><?php esc_html_e( 'to', 'bkx-advanced-reports' ); ?></span>
							<input type="text" id="export-date-to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
						</div>

						<div class="bkx-form-row">
							<button type="submit" class="button button-primary" id="bkx-generate-export">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Generate Export', 'bkx-advanced-reports' ); ?>
							</button>
						</div>
					</form>
				</div>

				<!-- Recent Exports -->
				<div class="bkx-recent-exports">
					<h3><?php esc_html_e( 'Recent Exports', 'bkx-advanced-reports' ); ?></h3>
					<table class="wp-list-table widefat fixed striped" id="recent-exports-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Report', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Format', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Date Range', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Created', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-advanced-reports' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bkx-advanced-reports' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>

				<!-- Scheduled Reports -->
				<div class="bkx-scheduled-reports">
					<h3><?php esc_html_e( 'Scheduled Reports', 'bkx-advanced-reports' ); ?></h3>
					<p><?php esc_html_e( 'Set up automatic report generation and email delivery.', 'bkx-advanced-reports' ); ?></p>

					<form id="bkx-schedule-form" class="bkx-schedule-form">
						<?php wp_nonce_field( 'bkx_schedule_report', 'bkx_schedule_nonce' ); ?>

						<div class="bkx-form-row">
							<label for="schedule-type"><?php esc_html_e( 'Report Type', 'bkx-advanced-reports' ); ?></label>
							<select id="schedule-type" name="report_type">
								<option value="revenue"><?php esc_html_e( 'Revenue Report', 'bkx-advanced-reports' ); ?></option>
								<option value="bookings"><?php esc_html_e( 'Bookings Report', 'bkx-advanced-reports' ); ?></option>
								<option value="comprehensive"><?php esc_html_e( 'Comprehensive Report', 'bkx-advanced-reports' ); ?></option>
							</select>
						</div>

						<div class="bkx-form-row">
							<label for="schedule-frequency"><?php esc_html_e( 'Frequency', 'bkx-advanced-reports' ); ?></label>
							<select id="schedule-frequency" name="frequency">
								<option value="daily"><?php esc_html_e( 'Daily', 'bkx-advanced-reports' ); ?></option>
								<option value="weekly"><?php esc_html_e( 'Weekly', 'bkx-advanced-reports' ); ?></option>
								<option value="monthly"><?php esc_html_e( 'Monthly', 'bkx-advanced-reports' ); ?></option>
							</select>
						</div>

						<div class="bkx-form-row">
							<label for="schedule-email"><?php esc_html_e( 'Email To', 'bkx-advanced-reports' ); ?></label>
							<input type="email" id="schedule-email" name="email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						</div>

						<div class="bkx-form-row">
							<button type="submit" class="button button-secondary" id="bkx-create-schedule">
								<span class="dashicons dashicons-clock"></span>
								<?php esc_html_e( 'Create Schedule', 'bkx-advanced-reports' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Loading Overlay -->
	<div class="bkx-loading-overlay" style="display: none;">
		<div class="bkx-spinner"></div>
		<p><?php esc_html_e( 'Loading report data...', 'bkx-advanced-reports' ); ?></p>
	</div>
</div>
