/**
 * Financial Reports Admin JavaScript.
 *
 * @package BookingX\FinancialReports
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKX_Financial = {
		charts: {},
		currency: bkxFinancial.currency || '$',

		init: function() {
			this.bindEvents();
			this.initPage();
		},

		bindEvents: function() {
			// Period selectors.
			$('#revenue-period').on('change', this.toggleDateRange);
			$('#load-revenue').on('click', () => this.loadRevenueData());
			$('#export-revenue').on('click', () => this.exportReport('revenue'));

			$('#load-pnl').on('click', () => this.loadPnLData());
			$('#export-pnl').on('click', () => this.exportReport('pnl'));

			$('#load-tax').on('click', () => this.loadTaxData());
			$('#export-tax').on('click', () => this.exportReport('tax'));

			$('#load-cashflow').on('click', () => this.loadCashflowData());

			$('#load-expenses').on('click', () => this.loadExpenses());
			$('#add-expense').on('click', () => this.openExpenseModal());
			$('#expense-form').on('submit', (e) => this.saveExpense(e));
			$('.bkx-fin-modal-close, .bkx-fin-modal-cancel').on('click', () => this.closeModal());

			$(document).on('click', '.edit-expense', (e) => this.editExpense(e));
			$(document).on('click', '.delete-expense', (e) => this.deleteExpense(e));
		},

		initPage: function() {
			const tab = new URLSearchParams(window.location.search).get('tab') || 'dashboard';

			switch (tab) {
				case 'dashboard':
					this.loadDashboard();
					break;
				case 'revenue':
					this.loadRevenueData();
					break;
				case 'pnl':
					this.loadPnLData();
					break;
				case 'expenses':
					this.loadExpenses();
					this.loadExpenseCategories();
					break;
				case 'tax':
					this.loadTaxData();
					break;
				case 'cashflow':
					this.loadCashflowData();
					break;
			}
		},

		toggleDateRange: function() {
			const val = $(this).val();
			if (val === 'custom') {
				$(this).closest('.bkx-fin-filters').find('.bkx-fin-date-range').show();
			} else {
				$(this).closest('.bkx-fin-filters').find('.bkx-fin-date-range').hide();
			}
		},

		loadDashboard: function() {
			$.ajax({
				url: bkxFinancial.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fin_get_dashboard',
					nonce: bkxFinancial.nonce
				},
				success: (response) => {
					if (response.success) {
						this.renderDashboard(response.data);
					}
				}
			});
		},

		renderDashboard: function(data) {
			// Quick stats.
			$('#stat-today').text(this.formatCurrency(data.summary.today.revenue));
			$('#stat-week').text(this.formatCurrency(data.summary.this_week.revenue));
			$('#stat-month').text(this.formatCurrency(data.summary.this_month.revenue));

			const growth = data.quick_stats.growth_rate;
			const $growth = $('#stat-growth');
			$growth.text((growth >= 0 ? '+' : '') + growth + '%');
			$growth.addClass(growth >= 0 ? 'positive' : 'negative');

			// Revenue chart.
			this.renderRevenueChart('revenue-chart', data.revenue_chart);

			// Top services.
			let servicesHtml = '';
			data.top_services.forEach(service => {
				servicesHtml += `<li>
					<span class="bkx-fin-top-name">${this.escapeHtml(service.name)}</span>
					<span class="bkx-fin-top-value">${this.formatCurrency(service.revenue)}</span>
				</li>`;
			});
			$('#top-services').html(servicesHtml || '<li>No data</li>');

			// Recent bookings.
			let bookingsHtml = '';
			data.recent_bookings.forEach(booking => {
				const status = booking.status.replace('bkx-', '');
				bookingsHtml += `<tr>
					<td>#${booking.booking_id}</td>
					<td>${this.escapeHtml(booking.customer_name || '-')}</td>
					<td>${this.escapeHtml(booking.service || '-')}</td>
					<td>${booking.booking_date || '-'}</td>
					<td>${this.formatCurrency(booking.amount)}</td>
					<td><span class="bkx-status-${status}">${status}</span></td>
				</tr>`;
			});
			$('#recent-bookings tbody').html(bookingsHtml || '<tr><td colspan="6">No bookings</td></tr>');
		},

		loadRevenueData: function() {
			const period = $('#revenue-period').val();
			const startDate = $('#revenue-start-date').val();
			const endDate = $('#revenue-end-date').val();

			$.ajax({
				url: bkxFinancial.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fin_get_revenue_data',
					nonce: bkxFinancial.nonce,
					period: period,
					start_date: startDate,
					end_date: endDate
				},
				success: (response) => {
					if (response.success) {
						this.renderRevenueReport(response.data);
					}
				}
			});
		},

		renderRevenueReport: function(data) {
			// Totals.
			$('#revenue-total').text(this.formatCurrency(data.totals.total_revenue));
			$('#revenue-bookings').text(data.totals.total_bookings);
			$('#revenue-avg').text(this.formatCurrency(data.totals.average_booking));

			const change = data.totals.growth_percent;
			const $change = $('#revenue-change');
			$change.text((change >= 0 ? '+' : '') + change + '% vs previous period');
			$change.removeClass('positive negative').addClass(change >= 0 ? 'positive' : 'negative');

			// Chart.
			this.renderRevenueChart('daily-revenue-chart', {
				labels: data.daily.map(d => d.label),
				values: data.daily.map(d => d.revenue)
			});

			// By service.
			let serviceHtml = '';
			(data.breakdown.by_service || []).forEach(item => {
				serviceHtml += `<tr>
					<td>${this.escapeHtml(item.service_name || 'Unknown')}</td>
					<td>${item.bookings}</td>
					<td>${this.formatCurrency(item.revenue)}</td>
				</tr>`;
			});
			$('#revenue-by-service tbody').html(serviceHtml || '<tr><td colspan="3">No data</td></tr>');

			// By payment.
			let paymentHtml = '';
			(data.breakdown.by_payment || []).forEach(item => {
				paymentHtml += `<tr>
					<td>${this.escapeHtml(item.payment_method)}</td>
					<td>${item.bookings}</td>
					<td>${this.formatCurrency(item.revenue)}</td>
				</tr>`;
			});
			$('#revenue-by-payment tbody').html(paymentHtml || '<tr><td colspan="3">No data</td></tr>');
		},

		loadPnLData: function() {
			const period = $('#pnl-period').val();

			$.ajax({
				url: bkxFinancial.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fin_get_pnl_report',
					nonce: bkxFinancial.nonce,
					period: period
				},
				success: (response) => {
					if (response.success) {
						this.renderPnLReport(response.data);
					}
				}
			});
		},

		renderPnLReport: function(data) {
			// Revenue items.
			let revenueHtml = '';
			data.revenue.items.forEach(item => {
				revenueHtml += `<tr>
					<td>${this.escapeHtml(item.name)}</td>
					<td>${this.formatCurrency(item.amount)}</td>
				</tr>`;
			});
			$('#pnl-revenue tbody').html(revenueHtml || '<tr><td colspan="2">No revenue</td></tr>');
			$('#pnl-revenue-total').text(this.formatCurrency(data.revenue.total));

			// Expense items.
			let expenseHtml = '';
			data.expenses.items.forEach(item => {
				expenseHtml += `<tr>
					<td>${this.escapeHtml(item.category || item.name)}</td>
					<td>${this.formatCurrency(item.amount)}</td>
				</tr>`;
			});
			$('#pnl-expenses tbody').html(expenseHtml || '<tr><td colspan="2">No expenses</td></tr>');
			$('#pnl-expenses-total').text(this.formatCurrency(data.expenses.total));

			// Profit.
			$('#pnl-profit').text(this.formatCurrency(data.gross_profit.amount));
			$('#pnl-margin').text(data.gross_profit.margin + '%');

			// Chart.
			if (data.monthly_breakdown && data.monthly_breakdown.length > 0) {
				this.renderPnLChart(data.monthly_breakdown);
			}
		},

		loadTaxData: function() {
			const period = $('#tax-period').val();

			$.ajax({
				url: bkxFinancial.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fin_get_tax_report',
					nonce: bkxFinancial.nonce,
					period: period
				},
				success: (response) => {
					if (response.success) {
						this.renderTaxReport(response.data);
					}
				}
			});
		},

		renderTaxReport: function(data) {
			$('#tax-sales').text(this.formatCurrency(data.taxable_sales));
			$('#tax-collected').text(this.formatCurrency(data.taxes_collected));
			$('#tax-rate').text(data.effective_rate + '%');

			let summaryHtml = '';
			(data.monthly_summary || []).forEach(month => {
				summaryHtml += `<tr>
					<td>${month.label}</td>
					<td>${this.formatCurrency(month.taxable_sales)}</td>
					<td>${this.formatCurrency(month.tax_collected)}</td>
				</tr>`;
			});
			$('#tax-summary tbody').html(summaryHtml || '<tr><td colspan="3">No data</td></tr>');
		},

		loadCashflowData: function() {
			const period = $('#cashflow-period').val();

			$.ajax({
				url: bkxFinancial.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fin_get_cashflow',
					nonce: bkxFinancial.nonce,
					period: period
				},
				success: (response) => {
					if (response.success) {
						this.renderCashflowReport(response.data);
					}
				}
			});
		},

		renderCashflowReport: function(data) {
			$('#cf-inflow').text(this.formatCurrency(data.inflows.total));
			$('#cf-outflow').text(this.formatCurrency(data.outflows.total));
			$('#cf-net').text(this.formatCurrency(data.net_flow));

			// Inflows table.
			let inflowHtml = '';
			data.inflows.items.forEach(item => {
				inflowHtml += `<tr>
					<td>${this.escapeHtml(item.category)}</td>
					<td>${this.formatCurrency(item.amount)}</td>
				</tr>`;
			});
			$('#cf-inflows tbody').html(inflowHtml || '<tr><td colspan="2">No inflows</td></tr>');

			// Outflows table.
			let outflowHtml = '';
			data.outflows.items.forEach(item => {
				outflowHtml += `<tr>
					<td>${this.escapeHtml(item.category)}</td>
					<td>${this.formatCurrency(item.amount)}</td>
				</tr>`;
			});
			$('#cf-outflows tbody').html(outflowHtml || '<tr><td colspan="2">No outflows</td></tr>');

			// Chart.
			this.renderCashflowChart(data.daily_flow);

			// Forecast.
			if (data.forecaste && data.forecaste.available) {
				let forecastHtml = `
					<div class="bkx-fin-forecast-item">
						<span class="bkx-fin-forecast-label">Projected Inflow</span>
						<span class="bkx-fin-forecast-value">${this.formatCurrency(data.forecaste.projected_inflow)}</span>
					</div>
					<div class="bkx-fin-forecast-item">
						<span class="bkx-fin-forecast-label">Projected Outflow</span>
						<span class="bkx-fin-forecast-value">${this.formatCurrency(data.forecaste.projected_outflow)}</span>
					</div>
					<div class="bkx-fin-forecast-item">
						<span class="bkx-fin-forecast-label">Projected Net</span>
						<span class="bkx-fin-forecast-value">${this.formatCurrency(data.forecaste.projected_net)}</span>
					</div>
					<div class="bkx-fin-forecast-item">
						<span class="bkx-fin-forecast-label">Projected Balance</span>
						<span class="bkx-fin-forecast-value">${this.formatCurrency(data.forecaste.projected_balance)}</span>
					</div>
				`;
				$('#cf-forecast').html(forecastHtml);
			} else {
				$('#cf-forecast-card').hide();
			}
		},

		loadExpenses: function() {
			const startDate = $('#expense-start-date').val();
			const endDate = $('#expense-end-date').val();
			const category = $('#expense-category').val();

			$.ajax({
				url: bkxFinancial.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fin_get_expenses',
					nonce: bkxFinancial.nonce,
					start_date: startDate,
					end_date: endDate,
					category: category
				},
				success: (response) => {
					if (response.success) {
						this.renderExpenses(response.data);
					}
				}
			});
		},

		renderExpenses: function(data) {
			let html = '';
			data.expenses.forEach(expense => {
				html += `<tr>
					<td>${expense.expense_date}</td>
					<td>${this.escapeHtml(expense.category)}</td>
					<td>${this.escapeHtml(expense.description)}</td>
					<td>${this.formatCurrency(expense.amount)}</td>
					<td>
						<button class="button button-small edit-expense" data-id="${expense.id}">Edit</button>
						<button class="button button-small delete-expense" data-id="${expense.id}">Delete</button>
					</td>
				</tr>`;
			});
			$('#expenses-table tbody').html(html || '<tr><td colspan="5">No expenses</td></tr>');
			$('#expenses-total').text(this.formatCurrency(data.grand_total));

			// Categories chart.
			if (data.category_totals && data.category_totals.length > 0) {
				this.renderExpensesChart(data.category_totals);

				let catHtml = '';
				const colors = this.getChartColors();
				data.category_totals.forEach((cat, i) => {
					catHtml += `<li>
						<span><span class="bkx-fin-cat-color" style="background:${colors[i % colors.length]}"></span>${this.escapeHtml(cat.category)}</span>
						<span>${this.formatCurrency(cat.total)}</span>
					</li>`;
				});
				$('#expense-categories').html(catHtml);
			}
		},

		loadExpenseCategories: function() {
			// Load categories into filter and form selects.
			const categories = [
				'Staff', 'Rent', 'Utilities', 'Supplies', 'Marketing',
				'Equipment', 'Insurance', 'Software', 'Professional Services',
				'Travel', 'Miscellaneous'
			];

			let filterHtml = '<option value="">All Categories</option>';
			let formHtml = '';
			categories.forEach(cat => {
				filterHtml += `<option value="${cat}">${cat}</option>`;
				formHtml += `<option value="${cat}">${cat}</option>`;
			});

			$('#expense-category').html(filterHtml);
			$('#expense-cat').html(formHtml);
		},

		openExpenseModal: function(expenseData = null) {
			$('#expense-id').val(expenseData ? expenseData.id : 0);
			$('#expense-date').val(expenseData ? expenseData.expense_date : new Date().toISOString().split('T')[0]);
			$('#expense-cat').val(expenseData ? expenseData.category : '');
			$('#expense-desc').val(expenseData ? expenseData.description : '');
			$('#expense-amount').val(expenseData ? expenseData.amount : '');
			$('#expense-vendor').val(expenseData ? expenseData.vendor : '');
			$('#expense-notes').val(expenseData ? expenseData.notes : '');

			$('#expense-modal-title').text(expenseData ? 'Edit Expense' : 'Add Expense');
			$('#expense-modal').show();
		},

		closeModal: function() {
			$('#expense-modal').hide();
		},

		saveExpense: function(e) {
			e.preventDefault();

			$.ajax({
				url: bkxFinancial.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fin_save_expense',
					nonce: bkxFinancial.nonce,
					expense_id: $('#expense-id').val(),
					expense_date: $('#expense-date').val(),
					category: $('#expense-cat').val(),
					description: $('#expense-desc').val(),
					amount: $('#expense-amount').val(),
					vendor: $('#expense-vendor').val(),
					notes: $('#expense-notes').val()
				},
				success: (response) => {
					if (response.success) {
						this.closeModal();
						this.loadExpenses();
						alert(bkxFinancial.i18n.saved);
					} else {
						alert(response.data.message || bkxFinancial.i18n.error);
					}
				}
			});
		},

		editExpense: function(e) {
			const id = $(e.target).data('id');
			// For simplicity, reload from table data.
			// In production, you'd fetch the expense by ID.
			const $row = $(e.target).closest('tr');
			const expenseData = {
				id: id,
				expense_date: $row.find('td:eq(0)').text(),
				category: $row.find('td:eq(1)').text(),
				description: $row.find('td:eq(2)').text(),
				amount: $row.find('td:eq(3)').text().replace(/[^0-9.]/g, ''),
				vendor: '',
				notes: ''
			};
			this.openExpenseModal(expenseData);
		},

		deleteExpense: function(e) {
			if (!confirm(bkxFinancial.i18n.confirm)) return;

			const id = $(e.target).data('id');

			$.ajax({
				url: bkxFinancial.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fin_delete_expense',
					nonce: bkxFinancial.nonce,
					expense_id: id
				},
				success: (response) => {
					if (response.success) {
						this.loadExpenses();
					} else {
						alert(response.data.message || bkxFinancial.i18n.error);
					}
				}
			});
		},

		exportReport: function(type) {
			const startDate = $(`#${type}-start-date`).val() || '';
			const endDate = $(`#${type}-end-date`).val() || '';

			$.ajax({
				url: bkxFinancial.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fin_export_report',
					nonce: bkxFinancial.nonce,
					report_type: type,
					format: 'csv',
					start_date: startDate,
					end_date: endDate
				},
				success: (response) => {
					if (response.success && response.data.download_url) {
						window.location.href = response.data.download_url;
					} else {
						alert(response.data.message || bkxFinancial.i18n.error);
					}
				}
			});
		},

		// Chart rendering.
		renderRevenueChart: function(canvasId, data) {
			const ctx = document.getElementById(canvasId);
			if (!ctx) return;

			if (this.charts[canvasId]) {
				this.charts[canvasId].destroy();
			}

			this.charts[canvasId] = new Chart(ctx, {
				type: 'line',
				data: {
					labels: data.labels,
					datasets: [{
						label: bkxFinancial.i18n.revenue,
						data: data.values,
						borderColor: '#667eea',
						backgroundColor: 'rgba(102, 126, 234, 0.1)',
						fill: true,
						tension: 0.3
					}]
				},
				options: {
					responsive: true,
					plugins: {
						legend: { display: false }
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: (value) => this.currency + value
							}
						}
					}
				}
			});
		},

		renderPnLChart: function(data) {
			const ctx = document.getElementById('pnl-chart');
			if (!ctx) return;

			if (this.charts['pnl-chart']) {
				this.charts['pnl-chart'].destroy();
			}

			this.charts['pnl-chart'] = new Chart(ctx, {
				type: 'bar',
				data: {
					labels: data.map(d => d.label),
					datasets: [
						{
							label: bkxFinancial.i18n.revenue,
							data: data.map(d => d.revenue),
							backgroundColor: '#667eea'
						},
						{
							label: bkxFinancial.i18n.expenses,
							data: data.map(d => d.expenses),
							backgroundColor: '#dc3545'
						},
						{
							label: bkxFinancial.i18n.profit,
							data: data.map(d => d.profit),
							backgroundColor: '#46b450'
						}
					]
				},
				options: {
					responsive: true,
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: (value) => this.currency + value
							}
						}
					}
				}
			});
		},

		renderCashflowChart: function(data) {
			const ctx = document.getElementById('cashflow-chart');
			if (!ctx) return;

			if (this.charts['cashflow-chart']) {
				this.charts['cashflow-chart'].destroy();
			}

			this.charts['cashflow-chart'] = new Chart(ctx, {
				type: 'line',
				data: {
					labels: data.map(d => d.label),
					datasets: [
						{
							label: 'Balance',
							data: data.map(d => d.balance),
							borderColor: '#667eea',
							fill: false,
							tension: 0.3
						}
					]
				},
				options: {
					responsive: true,
					scales: {
						y: {
							ticks: {
								callback: (value) => this.currency + value
							}
						}
					}
				}
			});
		},

		renderExpensesChart: function(data) {
			const ctx = document.getElementById('expenses-chart');
			if (!ctx) return;

			if (this.charts['expenses-chart']) {
				this.charts['expenses-chart'].destroy();
			}

			this.charts['expenses-chart'] = new Chart(ctx, {
				type: 'doughnut',
				data: {
					labels: data.map(d => d.category),
					datasets: [{
						data: data.map(d => d.total),
						backgroundColor: this.getChartColors()
					}]
				},
				options: {
					responsive: true,
					plugins: {
						legend: { display: false }
					}
				}
			});
		},

		getChartColors: function() {
			return [
				'#667eea', '#764ba2', '#f093fb', '#f5576c',
				'#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
				'#fa709a', '#fee140', '#a8edea', '#fed6e3'
			];
		},

		formatCurrency: function(amount) {
			const num = parseFloat(amount) || 0;
			return this.currency + num.toLocaleString(undefined, {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			});
		},

		escapeHtml: function(str) {
			if (!str) return '';
			const div = document.createElement('div');
			div.appendChild(document.createTextNode(str));
			return div.innerHTML;
		}
	};

	$(document).ready(function() {
		BKX_Financial.init();
	});

})(jQuery);
