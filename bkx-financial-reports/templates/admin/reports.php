<?php
/**
 * Financial Reports Admin Template.
 *
 * @package BookingX\FinancialReports
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
?>
<div class="wrap bkx-financial-wrap">
	<h1><?php esc_html_e( 'Financial Reports', 'bkx-financial-reports' ); ?></h1>

	<nav class="nav-tab-wrapper bkx-fin-nav">
		<a href="?page=bkx-financial-reports&tab=dashboard" class="nav-tab <?php echo 'dashboard' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Dashboard', 'bkx-financial-reports' ); ?>
		</a>
		<a href="?page=bkx-financial-reports&tab=revenue" class="nav-tab <?php echo 'revenue' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Revenue', 'bkx-financial-reports' ); ?>
		</a>
		<a href="?page=bkx-financial-reports&tab=pnl" class="nav-tab <?php echo 'pnl' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'P&L', 'bkx-financial-reports' ); ?>
		</a>
		<a href="?page=bkx-financial-reports&tab=expenses" class="nav-tab <?php echo 'expenses' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Expenses', 'bkx-financial-reports' ); ?>
		</a>
		<a href="?page=bkx-financial-reports&tab=tax" class="nav-tab <?php echo 'tax' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Tax', 'bkx-financial-reports' ); ?>
		</a>
		<a href="?page=bkx-financial-reports&tab=cashflow" class="nav-tab <?php echo 'cashflow' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Cash Flow', 'bkx-financial-reports' ); ?>
		</a>
	</nav>

	<div class="bkx-fin-content">
		<?php if ( 'dashboard' === $active_tab ) : ?>
			<!-- Dashboard Tab -->
			<div class="bkx-fin-dashboard">
				<div class="bkx-fin-quick-stats" id="bkx-fin-quick-stats">
					<div class="bkx-fin-stat-card">
						<span class="bkx-fin-stat-label"><?php esc_html_e( 'Today', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-stat-value" id="stat-today">-</span>
					</div>
					<div class="bkx-fin-stat-card">
						<span class="bkx-fin-stat-label"><?php esc_html_e( 'This Week', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-stat-value" id="stat-week">-</span>
					</div>
					<div class="bkx-fin-stat-card">
						<span class="bkx-fin-stat-label"><?php esc_html_e( 'This Month', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-stat-value" id="stat-month">-</span>
					</div>
					<div class="bkx-fin-stat-card">
						<span class="bkx-fin-stat-label"><?php esc_html_e( 'Growth', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-stat-value bkx-fin-growth" id="stat-growth">-</span>
					</div>
				</div>

				<div class="bkx-fin-row">
					<div class="bkx-fin-col-8">
						<div class="bkx-fin-card">
							<h2><?php esc_html_e( 'Revenue Trend', 'bkx-financial-reports' ); ?></h2>
							<canvas id="revenue-chart" height="300"></canvas>
						</div>
					</div>
					<div class="bkx-fin-col-4">
						<div class="bkx-fin-card">
							<h2><?php esc_html_e( 'Top Services', 'bkx-financial-reports' ); ?></h2>
							<ul class="bkx-fin-top-list" id="top-services">
								<li><?php esc_html_e( 'Loading...', 'bkx-financial-reports' ); ?></li>
							</ul>
						</div>
					</div>
				</div>

				<div class="bkx-fin-card">
					<h2><?php esc_html_e( 'Recent Bookings', 'bkx-financial-reports' ); ?></h2>
					<table class="wp-list-table widefat fixed striped" id="recent-bookings">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'bkx-financial-reports' ); ?></th>
								<th><?php esc_html_e( 'Customer', 'bkx-financial-reports' ); ?></th>
								<th><?php esc_html_e( 'Service', 'bkx-financial-reports' ); ?></th>
								<th><?php esc_html_e( 'Date', 'bkx-financial-reports' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'bkx-financial-reports' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-financial-reports' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>

		<?php elseif ( 'revenue' === $active_tab ) : ?>
			<!-- Revenue Tab -->
			<div class="bkx-fin-card">
				<div class="bkx-fin-filters">
					<select id="revenue-period">
						<option value="week"><?php esc_html_e( 'Last 7 Days', 'bkx-financial-reports' ); ?></option>
						<option value="month" selected><?php esc_html_e( 'This Month', 'bkx-financial-reports' ); ?></option>
						<option value="quarter"><?php esc_html_e( 'This Quarter', 'bkx-financial-reports' ); ?></option>
						<option value="year"><?php esc_html_e( 'This Year', 'bkx-financial-reports' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom Range', 'bkx-financial-reports' ); ?></option>
					</select>
					<div class="bkx-fin-date-range" style="display: none;">
						<input type="date" id="revenue-start-date">
						<span>to</span>
						<input type="date" id="revenue-end-date">
					</div>
					<button class="button" id="load-revenue"><?php esc_html_e( 'Load', 'bkx-financial-reports' ); ?></button>
					<button class="button" id="export-revenue"><?php esc_html_e( 'Export CSV', 'bkx-financial-reports' ); ?></button>
				</div>
			</div>

			<div class="bkx-fin-row">
				<div class="bkx-fin-col-4">
					<div class="bkx-fin-metric-card">
						<span class="bkx-fin-metric-label"><?php esc_html_e( 'Total Revenue', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-metric-value" id="revenue-total">-</span>
						<span class="bkx-fin-metric-change" id="revenue-change"></span>
					</div>
				</div>
				<div class="bkx-fin-col-4">
					<div class="bkx-fin-metric-card">
						<span class="bkx-fin-metric-label"><?php esc_html_e( 'Total Bookings', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-metric-value" id="revenue-bookings">-</span>
					</div>
				</div>
				<div class="bkx-fin-col-4">
					<div class="bkx-fin-metric-card">
						<span class="bkx-fin-metric-label"><?php esc_html_e( 'Average Booking', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-metric-value" id="revenue-avg">-</span>
					</div>
				</div>
			</div>

			<div class="bkx-fin-card">
				<h2><?php esc_html_e( 'Daily Revenue', 'bkx-financial-reports' ); ?></h2>
				<canvas id="daily-revenue-chart" height="300"></canvas>
			</div>

			<div class="bkx-fin-row">
				<div class="bkx-fin-col-6">
					<div class="bkx-fin-card">
						<h2><?php esc_html_e( 'By Service', 'bkx-financial-reports' ); ?></h2>
						<table class="wp-list-table widefat fixed striped" id="revenue-by-service">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Service', 'bkx-financial-reports' ); ?></th>
									<th><?php esc_html_e( 'Bookings', 'bkx-financial-reports' ); ?></th>
									<th><?php esc_html_e( 'Revenue', 'bkx-financial-reports' ); ?></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
				<div class="bkx-fin-col-6">
					<div class="bkx-fin-card">
						<h2><?php esc_html_e( 'By Payment Method', 'bkx-financial-reports' ); ?></h2>
						<table class="wp-list-table widefat fixed striped" id="revenue-by-payment">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Method', 'bkx-financial-reports' ); ?></th>
									<th><?php esc_html_e( 'Bookings', 'bkx-financial-reports' ); ?></th>
									<th><?php esc_html_e( 'Revenue', 'bkx-financial-reports' ); ?></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>

		<?php elseif ( 'pnl' === $active_tab ) : ?>
			<!-- P&L Tab -->
			<div class="bkx-fin-card">
				<div class="bkx-fin-filters">
					<select id="pnl-period">
						<option value="month" selected><?php esc_html_e( 'This Month', 'bkx-financial-reports' ); ?></option>
						<option value="quarter"><?php esc_html_e( 'This Quarter', 'bkx-financial-reports' ); ?></option>
						<option value="year"><?php esc_html_e( 'This Year', 'bkx-financial-reports' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom Range', 'bkx-financial-reports' ); ?></option>
					</select>
					<button class="button" id="load-pnl"><?php esc_html_e( 'Load', 'bkx-financial-reports' ); ?></button>
					<button class="button" id="export-pnl"><?php esc_html_e( 'Export', 'bkx-financial-reports' ); ?></button>
				</div>
			</div>

			<div class="bkx-fin-row">
				<div class="bkx-fin-col-6">
					<div class="bkx-fin-card bkx-fin-pnl-section">
						<h2><?php esc_html_e( 'Revenue', 'bkx-financial-reports' ); ?></h2>
						<table class="wp-list-table widefat" id="pnl-revenue">
							<tbody></tbody>
							<tfoot>
								<tr class="bkx-fin-total-row">
									<td><?php esc_html_e( 'Total Revenue', 'bkx-financial-reports' ); ?></td>
									<td id="pnl-revenue-total">-</td>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
				<div class="bkx-fin-col-6">
					<div class="bkx-fin-card bkx-fin-pnl-section">
						<h2><?php esc_html_e( 'Expenses', 'bkx-financial-reports' ); ?></h2>
						<table class="wp-list-table widefat" id="pnl-expenses">
							<tbody></tbody>
							<tfoot>
								<tr class="bkx-fin-total-row">
									<td><?php esc_html_e( 'Total Expenses', 'bkx-financial-reports' ); ?></td>
									<td id="pnl-expenses-total">-</td>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
			</div>

			<div class="bkx-fin-card bkx-fin-profit-summary">
				<div class="bkx-fin-profit-row">
					<span class="bkx-fin-profit-label"><?php esc_html_e( 'Gross Profit', 'bkx-financial-reports' ); ?></span>
					<span class="bkx-fin-profit-value" id="pnl-profit">-</span>
				</div>
				<div class="bkx-fin-profit-row">
					<span class="bkx-fin-profit-label"><?php esc_html_e( 'Profit Margin', 'bkx-financial-reports' ); ?></span>
					<span class="bkx-fin-profit-value" id="pnl-margin">-</span>
				</div>
			</div>

			<div class="bkx-fin-card">
				<h2><?php esc_html_e( 'Monthly Trend', 'bkx-financial-reports' ); ?></h2>
				<canvas id="pnl-chart" height="300"></canvas>
			</div>

		<?php elseif ( 'expenses' === $active_tab ) : ?>
			<!-- Expenses Tab -->
			<div class="bkx-fin-card">
				<div class="bkx-fin-filters">
					<input type="date" id="expense-start-date">
					<span>to</span>
					<input type="date" id="expense-end-date">
					<select id="expense-category">
						<option value=""><?php esc_html_e( 'All Categories', 'bkx-financial-reports' ); ?></option>
					</select>
					<button class="button" id="load-expenses"><?php esc_html_e( 'Filter', 'bkx-financial-reports' ); ?></button>
					<button class="button button-primary" id="add-expense"><?php esc_html_e( 'Add Expense', 'bkx-financial-reports' ); ?></button>
				</div>
			</div>

			<div class="bkx-fin-row">
				<div class="bkx-fin-col-8">
					<div class="bkx-fin-card">
						<h2><?php esc_html_e( 'Expenses', 'bkx-financial-reports' ); ?></h2>
						<table class="wp-list-table widefat fixed striped" id="expenses-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'bkx-financial-reports' ); ?></th>
									<th><?php esc_html_e( 'Category', 'bkx-financial-reports' ); ?></th>
									<th><?php esc_html_e( 'Description', 'bkx-financial-reports' ); ?></th>
									<th><?php esc_html_e( 'Amount', 'bkx-financial-reports' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'bkx-financial-reports' ); ?></th>
								</tr>
							</thead>
							<tbody></tbody>
							<tfoot>
								<tr class="bkx-fin-total-row">
									<td colspan="3"><?php esc_html_e( 'Total', 'bkx-financial-reports' ); ?></td>
									<td id="expenses-total">-</td>
									<td></td>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
				<div class="bkx-fin-col-4">
					<div class="bkx-fin-card">
						<h2><?php esc_html_e( 'By Category', 'bkx-financial-reports' ); ?></h2>
						<canvas id="expenses-chart" height="250"></canvas>
						<ul class="bkx-fin-category-list" id="expense-categories"></ul>
					</div>
				</div>
			</div>

			<!-- Add/Edit Expense Modal -->
			<div id="expense-modal" class="bkx-fin-modal" style="display: none;">
				<div class="bkx-fin-modal-content">
					<span class="bkx-fin-modal-close">&times;</span>
					<h2 id="expense-modal-title"><?php esc_html_e( 'Add Expense', 'bkx-financial-reports' ); ?></h2>
					<form id="expense-form">
						<input type="hidden" id="expense-id" value="0">
						<table class="form-table">
							<tr>
								<th><label for="expense-date"><?php esc_html_e( 'Date', 'bkx-financial-reports' ); ?></label></th>
								<td><input type="date" id="expense-date" required></td>
							</tr>
							<tr>
								<th><label for="expense-cat"><?php esc_html_e( 'Category', 'bkx-financial-reports' ); ?></label></th>
								<td><select id="expense-cat" required></select></td>
							</tr>
							<tr>
								<th><label for="expense-desc"><?php esc_html_e( 'Description', 'bkx-financial-reports' ); ?></label></th>
								<td><input type="text" id="expense-desc" class="regular-text" required></td>
							</tr>
							<tr>
								<th><label for="expense-amount"><?php esc_html_e( 'Amount', 'bkx-financial-reports' ); ?></label></th>
								<td><input type="number" id="expense-amount" step="0.01" min="0" required></td>
							</tr>
							<tr>
								<th><label for="expense-vendor"><?php esc_html_e( 'Vendor', 'bkx-financial-reports' ); ?></label></th>
								<td><input type="text" id="expense-vendor" class="regular-text"></td>
							</tr>
							<tr>
								<th><label for="expense-notes"><?php esc_html_e( 'Notes', 'bkx-financial-reports' ); ?></label></th>
								<td><textarea id="expense-notes" rows="3" class="large-text"></textarea></td>
							</tr>
						</table>
						<p class="submit">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'bkx-financial-reports' ); ?></button>
							<button type="button" class="button bkx-fin-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-financial-reports' ); ?></button>
						</p>
					</form>
				</div>
			</div>

		<?php elseif ( 'tax' === $active_tab ) : ?>
			<!-- Tax Tab -->
			<div class="bkx-fin-card">
				<div class="bkx-fin-filters">
					<select id="tax-period">
						<option value="quarter" selected><?php esc_html_e( 'This Quarter', 'bkx-financial-reports' ); ?></option>
						<option value="year"><?php esc_html_e( 'This Year', 'bkx-financial-reports' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom Range', 'bkx-financial-reports' ); ?></option>
					</select>
					<button class="button" id="load-tax"><?php esc_html_e( 'Load', 'bkx-financial-reports' ); ?></button>
					<button class="button" id="export-tax"><?php esc_html_e( 'Export', 'bkx-financial-reports' ); ?></button>
				</div>
			</div>

			<div class="bkx-fin-row">
				<div class="bkx-fin-col-4">
					<div class="bkx-fin-metric-card">
						<span class="bkx-fin-metric-label"><?php esc_html_e( 'Taxable Sales', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-metric-value" id="tax-sales">-</span>
					</div>
				</div>
				<div class="bkx-fin-col-4">
					<div class="bkx-fin-metric-card">
						<span class="bkx-fin-metric-label"><?php esc_html_e( 'Tax Collected', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-metric-value" id="tax-collected">-</span>
					</div>
				</div>
				<div class="bkx-fin-col-4">
					<div class="bkx-fin-metric-card">
						<span class="bkx-fin-metric-label"><?php esc_html_e( 'Effective Rate', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-metric-value" id="tax-rate">-</span>
					</div>
				</div>
			</div>

			<div class="bkx-fin-card">
				<h2><?php esc_html_e( 'Monthly Tax Summary', 'bkx-financial-reports' ); ?></h2>
				<table class="wp-list-table widefat fixed striped" id="tax-summary">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Month', 'bkx-financial-reports' ); ?></th>
							<th><?php esc_html_e( 'Taxable Sales', 'bkx-financial-reports' ); ?></th>
							<th><?php esc_html_e( 'Tax Collected', 'bkx-financial-reports' ); ?></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>

		<?php elseif ( 'cashflow' === $active_tab ) : ?>
			<!-- Cash Flow Tab -->
			<div class="bkx-fin-card">
				<div class="bkx-fin-filters">
					<select id="cashflow-period">
						<option value="week"><?php esc_html_e( 'Last 7 Days', 'bkx-financial-reports' ); ?></option>
						<option value="month" selected><?php esc_html_e( 'This Month', 'bkx-financial-reports' ); ?></option>
						<option value="quarter"><?php esc_html_e( 'This Quarter', 'bkx-financial-reports' ); ?></option>
					</select>
					<button class="button" id="load-cashflow"><?php esc_html_e( 'Load', 'bkx-financial-reports' ); ?></button>
				</div>
			</div>

			<div class="bkx-fin-row">
				<div class="bkx-fin-col-4">
					<div class="bkx-fin-metric-card bkx-fin-inflow">
						<span class="bkx-fin-metric-label"><?php esc_html_e( 'Total Inflow', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-metric-value" id="cf-inflow">-</span>
					</div>
				</div>
				<div class="bkx-fin-col-4">
					<div class="bkx-fin-metric-card bkx-fin-outflow">
						<span class="bkx-fin-metric-label"><?php esc_html_e( 'Total Outflow', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-metric-value" id="cf-outflow">-</span>
					</div>
				</div>
				<div class="bkx-fin-col-4">
					<div class="bkx-fin-metric-card">
						<span class="bkx-fin-metric-label"><?php esc_html_e( 'Net Cash Flow', 'bkx-financial-reports' ); ?></span>
						<span class="bkx-fin-metric-value" id="cf-net">-</span>
					</div>
				</div>
			</div>

			<div class="bkx-fin-card">
				<h2><?php esc_html_e( 'Cash Flow Trend', 'bkx-financial-reports' ); ?></h2>
				<canvas id="cashflow-chart" height="300"></canvas>
			</div>

			<div class="bkx-fin-row">
				<div class="bkx-fin-col-6">
					<div class="bkx-fin-card">
						<h2><?php esc_html_e( 'Inflows', 'bkx-financial-reports' ); ?></h2>
						<table class="wp-list-table widefat" id="cf-inflows">
							<tbody></tbody>
						</table>
					</div>
				</div>
				<div class="bkx-fin-col-6">
					<div class="bkx-fin-card">
						<h2><?php esc_html_e( 'Outflows', 'bkx-financial-reports' ); ?></h2>
						<table class="wp-list-table widefat" id="cf-outflows">
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>

			<div class="bkx-fin-card" id="cf-forecast-card">
				<h2><?php esc_html_e( '30-Day Forecast', 'bkx-financial-reports' ); ?></h2>
				<div class="bkx-fin-forecast" id="cf-forecast"></div>
			</div>
		<?php endif; ?>
	</div>
</div>
