/**
 * Business Intelligence Admin JavaScript.
 *
 * @package BookingX\BusinessIntelligence
 * @since   1.0.0
 */

(function($) {
	'use strict';

	// Chart instances.
	var charts = {
		revenueTrend: null,
		serviceBreakdown: null,
		staffPerformance: null,
		hourlyDistribution: null,
		dayDistribution: null,
		monthComparison: null,
		forecast: null
	};

	// Current state.
	var state = {
		period: 'week',
		startDate: null,
		endDate: null,
		chartType: 'revenue',
		currentReport: null
	};

	/**
	 * Initialize dashboard.
	 */
	function initDashboard() {
		if (!$('.bkx-bi-dashboard').length) {
			return;
		}

		// Set default dates.
		var today = new Date();
		state.endDate = formatDate(today);
		state.startDate = formatDate(new Date(today.setDate(today.getDate() - 7)));

		// Bind events.
		bindDashboardEvents();

		// Load initial data.
		loadKPIs();
		loadCharts();
	}

	/**
	 * Bind dashboard events.
	 */
	function bindDashboardEvents() {
		// Period selector.
		$('#bkx-bi-period').on('change', function() {
			state.period = $(this).val();

			if (state.period === 'custom') {
				$('#bkx-bi-custom-range').show();
			} else {
				$('#bkx-bi-custom-range').hide();
				updateDateRange();
				loadKPIs();
				loadCharts();
			}
		});

		// Custom date range.
		$('#bkx-bi-start-date, #bkx-bi-end-date').on('change', function() {
			state.startDate = $('#bkx-bi-start-date').val();
			state.endDate = $('#bkx-bi-end-date').val();

			if (state.startDate && state.endDate) {
				loadKPIs();
				loadCharts();
			}
		});

		// Refresh button.
		$('#bkx-bi-refresh').on('click', function() {
			loadKPIs();
			loadCharts();
		});

		// Chart type toggle.
		$('.bkx-bi-chart-toggle').on('click', function() {
			var $btn = $(this);
			var chartId = $btn.data('chart');
			var type = $btn.data('type');

			$btn.siblings().removeClass('active');
			$btn.addClass('active');

			if (chartId === 'revenue') {
				state.chartType = type;
				loadTrendChart();
			}
		});

		// Export buttons.
		$('#bkx-bi-export-csv').on('click', function() {
			exportDashboard('csv');
		});

		$('#bkx-bi-export-pdf').on('click', function() {
			exportDashboard('pdf');
		});

		// Forecast.
		$('#bkx-bi-generate-forecast').on('click', function() {
			loadForecast();
		});
	}

	/**
	 * Update date range based on period.
	 */
	function updateDateRange() {
		var today = new Date();
		var start = new Date();

		switch (state.period) {
			case 'today':
				start = today;
				break;
			case 'week':
				start.setDate(today.getDate() - 7);
				break;
			case 'month':
				start.setDate(today.getDate() - 30);
				break;
			case 'quarter':
				start.setDate(today.getDate() - 90);
				break;
			case 'year':
				start.setDate(today.getDate() - 365);
				break;
		}

		state.startDate = formatDate(start);
		state.endDate = formatDate(today);
	}

	/**
	 * Load KPIs.
	 */
	function loadKPIs() {
		// Show loading.
		$('.bkx-bi-kpi-value').html('<span class="bkx-bi-loading"></span>');
		$('.bkx-bi-kpi-change').html('');

		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_get_metrics',
				nonce: bkxBI.nonce,
				start_date: state.startDate,
				end_date: state.endDate
			},
			success: function(response) {
				if (response.success) {
					updateKPIs(response.data);
				}
			}
		});
	}

	/**
	 * Update KPI display.
	 */
	function updateKPIs(data) {
		var kpis = data.kpis;

		// Revenue.
		$('#kpi-revenue').text(bkxBI.currencySymbol + formatNumber(kpis.revenue));
		updateChange('#kpi-revenue-change', kpis.revenue_change);

		// Bookings.
		$('#kpi-bookings').text(formatNumber(kpis.bookings));
		updateChange('#kpi-bookings-change', kpis.bookings_change);

		// Customers.
		$('#kpi-customers').text(formatNumber(kpis.customers));

		// Average value.
		$('#kpi-avg-value').text(bkxBI.currencySymbol + formatNumber(kpis.avg_booking_value));

		// Rates.
		updateRate('#rate-completion', kpis.completion_rate);
		updateRate('#rate-cancellation', kpis.cancellation_rate);
		updateRate('#rate-noshow', kpis.noshow_rate);

		// Insights.
		loadInsights(kpis);
	}

	/**
	 * Update change indicator.
	 */
	function updateChange(selector, value) {
		var $el = $(selector);
		var className = value >= 0 ? 'positive' : 'negative';
		var sign = value >= 0 ? '+' : '';

		$el.removeClass('positive negative')
			.addClass(className)
			.text(sign + value.toFixed(1) + '% vs previous period');
	}

	/**
	 * Update rate circle.
	 */
	function updateRate(selector, value) {
		var $container = $(selector);
		var $circle = $container.find('.circle');
		var $text = $container.find('.percentage');

		$circle.attr('stroke-dasharray', value + ', 100');
		$text.text(value.toFixed(0) + '%');
	}

	/**
	 * Load all charts.
	 */
	function loadCharts() {
		loadTrendChart();
		loadServiceBreakdown();
		loadStaffPerformance();
		loadHourlyDistribution();
		loadDayDistribution();
		loadMonthComparison();
	}

	/**
	 * Load revenue/booking trend chart.
	 */
	function loadTrendChart() {
		var chartType = state.chartType === 'revenue' ? 'revenue_trend' : 'booking_trend';

		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_get_chart_data',
				nonce: bkxBI.nonce,
				chart: chartType,
				start_date: state.startDate,
				end_date: state.endDate
			},
			success: function(response) {
				if (response.success) {
					renderLineChart('chart-revenue-trend', response.data, 'revenueTrend');
				}
			}
		});
	}

	/**
	 * Load service breakdown chart.
	 */
	function loadServiceBreakdown() {
		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_get_chart_data',
				nonce: bkxBI.nonce,
				chart: 'service_breakdown',
				start_date: state.startDate,
				end_date: state.endDate
			},
			success: function(response) {
				if (response.success) {
					renderDoughnutChart('chart-service-breakdown', response.data, 'serviceBreakdown');
				}
			}
		});
	}

	/**
	 * Load staff performance chart.
	 */
	function loadStaffPerformance() {
		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_get_chart_data',
				nonce: bkxBI.nonce,
				chart: 'staff_performance',
				start_date: state.startDate,
				end_date: state.endDate
			},
			success: function(response) {
				if (response.success) {
					renderBarChart('chart-staff-performance', response.data, 'staffPerformance', true);
				}
			}
		});
	}

	/**
	 * Load hourly distribution chart.
	 */
	function loadHourlyDistribution() {
		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_get_chart_data',
				nonce: bkxBI.nonce,
				chart: 'hourly_distribution',
				start_date: state.startDate,
				end_date: state.endDate
			},
			success: function(response) {
				if (response.success) {
					renderBarChart('chart-hourly-distribution', response.data, 'hourlyDistribution');
				}
			}
		});
	}

	/**
	 * Load day of week distribution chart.
	 */
	function loadDayDistribution() {
		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_get_chart_data',
				nonce: bkxBI.nonce,
				chart: 'day_of_week_distribution',
				start_date: state.startDate,
				end_date: state.endDate
			},
			success: function(response) {
				if (response.success) {
					renderBarChart('chart-day-distribution', response.data, 'dayDistribution');
				}
			}
		});
	}

	/**
	 * Load month over month comparison chart.
	 */
	function loadMonthComparison() {
		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_get_chart_data',
				nonce: bkxBI.nonce,
				chart: 'month_over_month'
			},
			success: function(response) {
				if (response.success) {
					renderLineChart('chart-month-comparison', response.data, 'monthComparison', true);
				}
			}
		});
	}

	/**
	 * Load forecast.
	 */
	function loadForecast() {
		var metric = $('#bkx-bi-forecast-metric').val();
		var days = $('#bkx-bi-forecast-days').val();

		$('#bkx-bi-generate-forecast').prop('disabled', true).text(bkxBI.i18n.generating);

		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_get_forecast',
				nonce: bkxBI.nonce,
				metric: metric,
				days: days
			},
			success: function(response) {
				$('#bkx-bi-generate-forecast').prop('disabled', false).text(bkxBI.i18n.generate);

				if (response.success) {
					renderForecastChart(response.data);
					updateForecastSummary(response.data.summary);
				}
			},
			error: function() {
				$('#bkx-bi-generate-forecast').prop('disabled', false).text(bkxBI.i18n.generate);
			}
		});
	}

	/**
	 * Render line chart.
	 */
	function renderLineChart(canvasId, data, chartKey, dualAxis) {
		var ctx = document.getElementById(canvasId);
		if (!ctx) return;

		// Destroy existing chart.
		if (charts[chartKey]) {
			charts[chartKey].destroy();
		}

		var options = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: {
					display: data.datasets.length > 1,
					position: 'top'
				}
			},
			scales: {
				x: {
					grid: {
						display: false
					}
				},
				y: {
					beginAtZero: true,
					grid: {
						color: '#f0f0f1'
					}
				}
			}
		};

		if (dualAxis) {
			options.scales.y1 = {
				type: 'linear',
				display: true,
				position: 'right',
				beginAtZero: true,
				grid: {
					drawOnChartArea: false
				}
			};
		}

		charts[chartKey] = new Chart(ctx, {
			type: 'line',
			data: data,
			options: options
		});
	}

	/**
	 * Render bar chart.
	 */
	function renderBarChart(canvasId, data, chartKey, horizontal) {
		var ctx = document.getElementById(canvasId);
		if (!ctx) return;

		// Destroy existing chart.
		if (charts[chartKey]) {
			charts[chartKey].destroy();
		}

		var options = {
			responsive: true,
			maintainAspectRatio: false,
			indexAxis: horizontal ? 'y' : 'x',
			plugins: {
				legend: {
					display: data.datasets.length > 1
				}
			},
			scales: {
				x: {
					grid: {
						display: false
					}
				},
				y: {
					beginAtZero: true,
					grid: {
						color: '#f0f0f1'
					}
				}
			}
		};

		charts[chartKey] = new Chart(ctx, {
			type: 'bar',
			data: data,
			options: options
		});
	}

	/**
	 * Render doughnut chart.
	 */
	function renderDoughnutChart(canvasId, data, chartKey) {
		var ctx = document.getElementById(canvasId);
		if (!ctx) return;

		// Destroy existing chart.
		if (charts[chartKey]) {
			charts[chartKey].destroy();
		}

		charts[chartKey] = new Chart(ctx, {
			type: 'doughnut',
			data: data,
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: 'right'
					}
				}
			}
		});
	}

	/**
	 * Render forecast chart.
	 */
	function renderForecastChart(data) {
		var ctx = document.getElementById('chart-forecast');
		if (!ctx) return;

		if (charts.forecast) {
			charts.forecast.destroy();
		}

		// Update title.
		var metric = $('#bkx-bi-forecast-metric').val();
		var title = metric === 'revenue' ? bkxBI.i18n.revenueForecast : bkxBI.i18n.bookingsForecast;
		$('#forecast-title').text(title);

		charts.forecast = new Chart(ctx, {
			type: 'line',
			data: data,
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						position: 'top'
					}
				},
				scales: {
					y: {
						beginAtZero: true
					}
				}
			}
		});
	}

	/**
	 * Update forecast summary.
	 */
	function updateForecastSummary(summary) {
		var metric = $('#bkx-bi-forecast-metric').val();
		var prefix = metric === 'revenue' ? bkxBI.currencySymbol : '';

		$('#forecast-total').text(prefix + formatNumber(summary.total_forecast));
		$('#forecast-avg').text(prefix + formatNumber(summary.avg_daily));
		$('#forecast-confidence').text(summary.confidence_level + '%');
		$('#forecast-model').text(summary.model);
	}

	/**
	 * Load insights.
	 */
	function loadInsights(kpis) {
		var $container = $('#bkx-bi-insights');
		var insights = [];

		// Revenue insight.
		if (kpis.revenue_change > 10) {
			insights.push({
				type: 'positive',
				icon: 'yes-alt',
				message: bkxBI.i18n.revenueUp.replace('%s', kpis.revenue_change.toFixed(1))
			});
		} else if (kpis.revenue_change < -10) {
			insights.push({
				type: 'warning',
				icon: 'warning',
				message: bkxBI.i18n.revenueDown.replace('%s', Math.abs(kpis.revenue_change).toFixed(1))
			});
		}

		// Cancellation insight.
		if (kpis.cancellation_rate > 20) {
			insights.push({
				type: 'warning',
				icon: 'warning',
				message: bkxBI.i18n.highCancellation.replace('%s', kpis.cancellation_rate.toFixed(1))
			});
		}

		// No-show insight.
		if (kpis.noshow_rate > 10) {
			insights.push({
				type: 'warning',
				icon: 'clock',
				message: bkxBI.i18n.highNoShow.replace('%s', kpis.noshow_rate.toFixed(1))
			});
		}

		// Render insights.
		$container.empty();

		if (insights.length === 0) {
			$container.html('<div class="bkx-bi-insight bkx-bi-insight-info"><span class="dashicons dashicons-info"></span><p>' + bkxBI.i18n.noInsights + '</p></div>');
		} else {
			var template = wp.template('bkx-bi-insight');
			insights.forEach(function(insight) {
				$container.append(template(insight));
			});
		}
	}

	/**
	 * Export dashboard.
	 */
	function exportDashboard(format) {
		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_export_report',
				nonce: bkxBI.nonce,
				format: format,
				type: 'executive_summary',
				start_date: state.startDate,
				end_date: state.endDate
			},
			success: function(response) {
				if (response.success && response.data.url) {
					window.location.href = response.data.url;
				}
			}
		});
	}

	/**
	 * Initialize reports page.
	 */
	function initReports() {
		if (!$('.bkx-bi-reports').length) {
			return;
		}

		// Set default dates.
		var today = new Date();
		var monthAgo = new Date(today);
		monthAgo.setDate(today.getDate() - 30);

		$('#report-start-date').val(formatDate(monthAgo));
		$('#report-end-date').val(formatDate(today));

		// Bind events.
		bindReportEvents();

		// Load saved reports.
		loadSavedReports();
	}

	/**
	 * Bind report events.
	 */
	function bindReportEvents() {
		// Save checkbox toggle.
		$('#report-save').on('change', function() {
			if ($(this).is(':checked')) {
				$('#report-schedule-options').slideDown();
			} else {
				$('#report-schedule-options').slideUp();
			}
		});

		// Generate report form.
		$('#bkx-bi-report-form').on('submit', function(e) {
			e.preventDefault();
			generateReport();
		});

		// Export buttons.
		$('#export-csv').on('click', function() { exportCurrentReport('csv'); });
		$('#export-pdf').on('click', function() { exportCurrentReport('pdf'); });
		$('#export-excel').on('click', function() { exportCurrentReport('excel'); });

		// Email button.
		$('#email-report').on('click', function() {
			$('#email-report-modal').show();
		});

		// Modal close.
		$('.bkx-bi-modal-close, .bkx-bi-modal-cancel').on('click', function() {
			$(this).closest('.bkx-bi-modal').hide();
		});

		// Send email.
		$('#send-email-report').on('click', function() {
			emailReport();
		});

		// Print.
		$('#print-report').on('click', function() {
			window.print();
		});

		// Filter reports.
		$('#filter-type').on('change', function() {
			loadSavedReports({ type: $(this).val() });
		});

		// Report actions (delegated).
		$(document).on('click', '.bkx-bi-view-report', function() {
			viewSavedReport($(this).data('id'));
		});

		$(document).on('click', '.bkx-bi-run-report', function() {
			runSavedReport($(this).data('id'));
		});

		$(document).on('click', '.bkx-bi-delete-report', function() {
			if (confirm(bkxBI.i18n.confirmDelete)) {
				deleteSavedReport($(this).data('id'));
			}
		});
	}

	/**
	 * Generate report.
	 */
	function generateReport() {
		var $form = $('#bkx-bi-report-form');
		var $btn = $form.find('button[type="submit"]');

		$btn.prop('disabled', true).text(bkxBI.i18n.generating);

		var data = {
			action: 'bkx_bi_generate_report',
			nonce: bkxBI.nonce,
			type: $('#report-type').val(),
			start_date: $('#report-start-date').val(),
			end_date: $('#report-end-date').val(),
			name: $('#report-name').val(),
			save: $('#report-save').is(':checked') ? 1 : 0,
			schedule: $('#report-schedule').val(),
			recipients: $('#report-recipients').val()
		};

		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: data,
			success: function(response) {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-area"></span> ' + bkxBI.i18n.generateReport);

				if (response.success) {
					state.currentReport = response.data;
					renderReportPreview(response.data);

					if (data.save) {
						loadSavedReports();
					}
				}
			},
			error: function() {
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-chart-area"></span> ' + bkxBI.i18n.generateReport);
			}
		});
	}

	/**
	 * Render report preview.
	 */
	function renderReportPreview(report) {
		var $preview = $('#report-preview');
		var $content = $('#preview-content');

		$preview.show();
		$('#preview-title').text(report.title);

		// Meta.
		var meta = '';
		if (report.period) {
			meta += '<strong>' + bkxBI.i18n.period + ':</strong> ' + report.period.start + ' to ' + report.period.end + ' | ';
		}
		meta += '<strong>' + bkxBI.i18n.generated + ':</strong> ' + report.generated;
		$('#preview-meta').html(meta);

		// Content.
		$content.empty();

		// Summary.
		if (report.summary) {
			var summaryTemplate = wp.template('bkx-bi-report-summary');
			$content.append(summaryTemplate(report));
		}

		// Services table.
		if (report.services && report.services.length) {
			$content.append('<h3>' + bkxBI.i18n.servicePerformance + '</h3>');
			var headers = [bkxBI.i18n.service, bkxBI.i18n.bookings, bkxBI.i18n.completed, bkxBI.i18n.completionRate, bkxBI.i18n.revenue];
			var rows = report.services.map(function(s) {
				return [s.name, s.total_bookings, s.completed, s.completion_rate + '%', bkxBI.currencySymbol + formatNumber(s.revenue)];
			});
			var tableTemplate = wp.template('bkx-bi-report-table');
			$content.append(tableTemplate({ headers: headers, rows: rows }));
		}

		// Staff table.
		if (report.staff && report.staff.length) {
			$content.append('<h3>' + bkxBI.i18n.staffPerformance + '</h3>');
			var headers = [bkxBI.i18n.staff, bkxBI.i18n.bookings, bkxBI.i18n.completed, bkxBI.i18n.completionRate, bkxBI.i18n.revenue];
			var rows = report.staff.map(function(s) {
				return [s.name, s.total_bookings, s.completed, s.completion_rate + '%', bkxBI.currencySymbol + formatNumber(s.revenue)];
			});
			var tableTemplate = wp.template('bkx-bi-report-table');
			$content.append(tableTemplate({ headers: headers, rows: rows }));
		}

		// Insights.
		if (report.insights && report.insights.length) {
			$content.append('<h3>' + bkxBI.i18n.insights + '</h3>');
			var template = wp.template('bkx-bi-insight');
			report.insights.forEach(function(insight) {
				$content.append(template({
					type: insight.type,
					icon: insight.type === 'positive' ? 'yes-alt' : 'warning',
					message: insight.message
				}));
			});
		}

		// Scroll to preview.
		$('html, body').animate({
			scrollTop: $preview.offset().top - 50
		}, 500);
	}

	/**
	 * Export current report.
	 */
	function exportCurrentReport(format) {
		if (!state.currentReport) return;

		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_export_report',
				nonce: bkxBI.nonce,
				format: format,
				report: JSON.stringify(state.currentReport)
			},
			success: function(response) {
				if (response.success && response.data.url) {
					window.location.href = response.data.url;
				}
			}
		});
	}

	/**
	 * Email report.
	 */
	function emailReport() {
		if (!state.currentReport) return;

		var recipients = $('#email-recipients').val();
		var format = $('#email-format').val();

		if (!recipients) {
			alert(bkxBI.i18n.enterRecipients);
			return;
		}

		$('#send-email-report').prop('disabled', true).text(bkxBI.i18n.sending);

		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_email_report',
				nonce: bkxBI.nonce,
				report: JSON.stringify(state.currentReport),
				recipients: recipients,
				format: format
			},
			success: function(response) {
				$('#send-email-report').prop('disabled', false).text(bkxBI.i18n.send);
				$('#email-report-modal').hide();

				if (response.success) {
					alert(bkxBI.i18n.emailSent);
				} else {
					alert(bkxBI.i18n.emailFailed);
				}
			},
			error: function() {
				$('#send-email-report').prop('disabled', false).text(bkxBI.i18n.send);
				alert(bkxBI.i18n.emailFailed);
			}
		});
	}

	/**
	 * Load saved reports.
	 */
	function loadSavedReports(args) {
		args = args || {};

		var $tbody = $('#saved-reports-body');
		$tbody.html('<tr class="bkx-bi-loading-row"><td colspan="5"><span class="bkx-bi-loading"></span> ' + bkxBI.i18n.loading + '</td></tr>');

		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_get_saved_reports',
				nonce: bkxBI.nonce,
				type: args.type || ''
			},
			success: function(response) {
				$tbody.empty();

				if (response.success && response.data.length) {
					var template = wp.template('bkx-bi-saved-report-row');

					response.data.forEach(function(report) {
						report.report_type_label = getReportTypeLabel(report.report_type);
						report.schedule_label = getScheduleLabel(report.schedule);
						$tbody.append(template(report));
					});
				} else {
					$tbody.html('<tr><td colspan="5" style="text-align:center;padding:20px;">' + bkxBI.i18n.noReports + '</td></tr>');
				}
			}
		});
	}

	/**
	 * View saved report.
	 */
	function viewSavedReport(id) {
		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_get_report',
				nonce: bkxBI.nonce,
				id: id
			},
			success: function(response) {
				if (response.success) {
					state.currentReport = response.data;
					renderReportPreview(response.data);
				}
			}
		});
	}

	/**
	 * Run saved report (regenerate with current data).
	 */
	function runSavedReport(id) {
		// TODO: Implement regeneration.
		alert('Regenerating report...');
	}

	/**
	 * Delete saved report.
	 */
	function deleteSavedReport(id) {
		$.ajax({
			url: bkxBI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_bi_delete_report',
				nonce: bkxBI.nonce,
				id: id
			},
			success: function(response) {
				if (response.success) {
					$('tr[data-report-id="' + id + '"]').fadeOut(function() {
						$(this).remove();
					});
				}
			}
		});
	}

	/**
	 * Get report type label.
	 */
	function getReportTypeLabel(type) {
		var labels = {
			'revenue_summary': bkxBI.i18n.revenueSummary,
			'booking_analysis': bkxBI.i18n.bookingAnalysis,
			'service_performance': bkxBI.i18n.servicePerformance,
			'staff_performance': bkxBI.i18n.staffPerformance,
			'customer_insights': bkxBI.i18n.customerInsights,
			'trend_analysis': bkxBI.i18n.trendAnalysis,
			'forecast_report': bkxBI.i18n.forecastReport,
			'executive_summary': bkxBI.i18n.executiveSummary
		};
		return labels[type] || type;
	}

	/**
	 * Get schedule label.
	 */
	function getScheduleLabel(schedule) {
		var labels = {
			'daily': bkxBI.i18n.daily,
			'weekly': bkxBI.i18n.weekly,
			'monthly': bkxBI.i18n.monthly
		};
		return labels[schedule] || schedule;
	}

	/**
	 * Format date for input.
	 */
	function formatDate(date) {
		var year = date.getFullYear();
		var month = String(date.getMonth() + 1).padStart(2, '0');
		var day = String(date.getDate()).padStart(2, '0');
		return year + '-' + month + '-' + day;
	}

	/**
	 * Format number with commas.
	 */
	function formatNumber(num) {
		if (typeof num !== 'number') {
			num = parseFloat(num) || 0;
		}
		return num.toLocaleString('en-US', { maximumFractionDigits: 2 });
	}

	// Initialize on document ready.
	$(document).ready(function() {
		initDashboard();
		initReports();
	});

})(jQuery);
