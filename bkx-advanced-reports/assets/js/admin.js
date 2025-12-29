/**
 * Advanced Reports Admin JavaScript.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

(function($) {
	'use strict';

	// Chart instances storage.
	var charts = {};

	// Current date range.
	var dateFrom = $('#bkx-date-from').val();
	var dateTo = $('#bkx-date-to').val();

	// Chart.js default options.
	var chartDefaults = {
		responsive: true,
		maintainAspectRatio: true,
		plugins: {
			legend: {
				position: 'bottom',
				labels: {
					boxWidth: 12,
					padding: 15
				}
			},
			tooltip: {
				backgroundColor: 'rgba(0, 0, 0, 0.8)',
				padding: 12,
				titleFont: {
					size: 13
				},
				bodyFont: {
					size: 12
				}
			}
		}
	};

	// Color palette.
	var colors = {
		primary: '#2271b1',
		success: '#1e8e3e',
		warning: '#f9a825',
		danger: '#d93025',
		info: '#1a73e8',
		purple: '#7b1fa2',
		teal: '#00897b',
		orange: '#f57c00',
		chartColors: [
			'#2271b1',
			'#1e8e3e',
			'#f9a825',
			'#d93025',
			'#7b1fa2',
			'#00897b',
			'#f57c00',
			'#5c6bc0',
			'#8d6e63',
			'#78909c'
		]
	};

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		initDatePickers();
		initQuickRanges();
		loadReportData();
		initExportForm();
		initScheduleForm();
	});

	/**
	 * Initialize date pickers.
	 */
	function initDatePickers() {
		if ($.fn.datepicker) {
			$('#bkx-date-from, #bkx-date-to, #export-date-from, #export-date-to').datepicker({
				dateFormat: 'yy-mm-dd',
				maxDate: 0,
				changeMonth: true,
				changeYear: true
			});
		}

		$('#bkx-apply-dates').on('click', function() {
			dateFrom = $('#bkx-date-from').val();
			dateTo = $('#bkx-date-to').val();
			loadReportData();
		});
	}

	/**
	 * Initialize quick date range buttons.
	 */
	function initQuickRanges() {
		$('.bkx-quick-ranges .button').on('click', function() {
			var range = $(this).data('range');
			var today = new Date();
			var from, to;

			to = formatDate(today);

			switch (range) {
				case 'today':
					from = to;
					break;
				case 'week':
					var weekStart = new Date(today);
					weekStart.setDate(today.getDate() - today.getDay());
					from = formatDate(weekStart);
					break;
				case 'month':
					from = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
					break;
				case 'quarter':
					var quarter = Math.floor(today.getMonth() / 3);
					from = formatDate(new Date(today.getFullYear(), quarter * 3, 1));
					break;
				case 'year':
					from = formatDate(new Date(today.getFullYear(), 0, 1));
					break;
				default:
					from = formatDate(new Date(today.setDate(today.getDate() - 30)));
			}

			$('#bkx-date-from').val(from);
			$('#bkx-date-to').val(to);
			dateFrom = from;
			dateTo = to;

			$('.bkx-quick-ranges .button').removeClass('active');
			$(this).addClass('active');

			loadReportData();
		});
	}

	/**
	 * Format date as YYYY-MM-DD.
	 */
	function formatDate(date) {
		var year = date.getFullYear();
		var month = String(date.getMonth() + 1).padStart(2, '0');
		var day = String(date.getDate()).padStart(2, '0');
		return year + '-' + month + '-' + day;
	}

	/**
	 * Format currency.
	 */
	function formatCurrency(amount) {
		return bkxReportsData.currency_symbol + parseFloat(amount).toFixed(2);
	}

	/**
	 * Load report data based on active tab.
	 */
	function loadReportData() {
		var activeTab = getActiveTab();

		showLoading();

		switch (activeTab) {
			case 'revenue':
				loadRevenueReport();
				break;
			case 'bookings':
				loadBookingsReport();
				break;
			case 'staff':
				loadStaffReport();
				break;
			case 'customers':
				loadCustomerReport();
				break;
			case 'exports':
				loadRecentExports();
				break;
		}
	}

	/**
	 * Get active tab.
	 */
	function getActiveTab() {
		var urlParams = new URLSearchParams(window.location.search);
		return urlParams.get('tab') || 'revenue';
	}

	/**
	 * Show loading overlay.
	 */
	function showLoading() {
		$('.bkx-loading-overlay').show();
	}

	/**
	 * Hide loading overlay.
	 */
	function hideLoading() {
		$('.bkx-loading-overlay').hide();
	}

	/**
	 * Load revenue report.
	 */
	function loadRevenueReport() {
		$.ajax({
			url: bkxReportsData.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_get_revenue_report',
				nonce: bkxReportsData.nonce,
				date_from: dateFrom,
				date_to: dateTo
			},
			success: function(response) {
				hideLoading();
				if (response.success && response.data) {
					renderRevenueSummary(response.data.summary);
					renderRevenueTrend(response.data.trend);
					renderRevenueByService(response.data.by_service);
					renderRevenueByStaff(response.data.by_staff);
				}
			},
			error: function() {
				hideLoading();
				console.error('Failed to load revenue report');
			}
		});
	}

	/**
	 * Render revenue summary cards.
	 */
	function renderRevenueSummary(summary) {
		$('#revenue-summary [data-field="total_revenue"]').text(formatCurrency(summary.total_revenue || 0));
		$('#revenue-summary [data-field="total_bookings"]').text(summary.total_bookings || 0);
		$('#revenue-summary [data-field="avg_booking_value"]').text(formatCurrency(summary.avg_booking_value || 0));
		$('#revenue-summary [data-field="projected_month"]').text(formatCurrency(summary.projected_month || 0));
	}

	/**
	 * Render revenue trend chart.
	 */
	function renderRevenueTrend(data) {
		if (!data || !data.labels) return;

		destroyChart('revenue-trend-chart');

		var ctx = document.getElementById('revenue-trend-chart');
		if (!ctx) return;

		charts['revenue-trend-chart'] = new Chart(ctx, {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: [{
					label: bkxReportsData.i18n.revenue,
					data: data.data,
					borderColor: colors.primary,
					backgroundColor: 'rgba(34, 113, 177, 0.1)',
					fill: true,
					tension: 0.4
				}]
			},
			options: $.extend(true, {}, chartDefaults, {
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							callback: function(value) {
								return formatCurrency(value);
							}
						}
					}
				}
			})
		});
	}

	/**
	 * Render revenue by service chart.
	 */
	function renderRevenueByService(data) {
		if (!data || !data.length) return;

		destroyChart('revenue-service-chart');

		var ctx = document.getElementById('revenue-service-chart');
		if (!ctx) return;

		var labels = data.map(function(item) { return item.service_name; });
		var values = data.map(function(item) { return item.revenue; });

		charts['revenue-service-chart'] = new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{
					data: values,
					backgroundColor: colors.chartColors
				}]
			},
			options: chartDefaults
		});
	}

	/**
	 * Render revenue by staff chart.
	 */
	function renderRevenueByStaff(data) {
		if (!data || !data.length) return;

		destroyChart('revenue-staff-chart');

		var ctx = document.getElementById('revenue-staff-chart');
		if (!ctx) return;

		var labels = data.map(function(item) { return item.staff_name; });
		var values = data.map(function(item) { return item.revenue; });

		charts['revenue-staff-chart'] = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [{
					label: bkxReportsData.i18n.revenue,
					data: values,
					backgroundColor: colors.chartColors
				}]
			},
			options: $.extend(true, {}, chartDefaults, {
				indexAxis: 'y',
				scales: {
					x: {
						beginAtZero: true,
						ticks: {
							callback: function(value) {
								return formatCurrency(value);
							}
						}
					}
				}
			})
		});
	}

	/**
	 * Load bookings report.
	 */
	function loadBookingsReport() {
		$.ajax({
			url: bkxReportsData.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_get_bookings_report',
				nonce: bkxReportsData.nonce,
				date_from: dateFrom,
				date_to: dateTo
			},
			success: function(response) {
				hideLoading();
				if (response.success && response.data) {
					renderBookingsSummary(response.data.summary);
					renderBookingsTrend(response.data.trend);
					renderBookingsByDay(response.data.by_day);
					renderBookingsByTime(response.data.by_time);
					renderPeakTimesHeatmap(response.data.peak_times);
				}
			},
			error: function() {
				hideLoading();
				console.error('Failed to load bookings report');
			}
		});
	}

	/**
	 * Render bookings summary cards.
	 */
	function renderBookingsSummary(summary) {
		$('#bookings-summary [data-field="total"]').text(summary.total || 0);
		$('#bookings-summary [data-field="completed"]').text(summary.completed || 0);
		$('#bookings-summary [data-field="cancelled"]').text(summary.cancelled || 0);
		$('#bookings-summary [data-field="completion_rate"]').text((summary.completion_rate || 0) + '%');
	}

	/**
	 * Render bookings trend chart.
	 */
	function renderBookingsTrend(data) {
		if (!data || !data.labels) return;

		destroyChart('bookings-trend-chart');

		var ctx = document.getElementById('bookings-trend-chart');
		if (!ctx) return;

		charts['bookings-trend-chart'] = new Chart(ctx, {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: [{
					label: bkxReportsData.i18n.bookings,
					data: data.data,
					borderColor: colors.info,
					backgroundColor: 'rgba(26, 115, 232, 0.1)',
					fill: true,
					tension: 0.4
				}]
			},
			options: $.extend(true, {}, chartDefaults, {
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							stepSize: 1
						}
					}
				}
			})
		});
	}

	/**
	 * Render bookings by day of week chart.
	 */
	function renderBookingsByDay(data) {
		if (!data || !data.labels) return;

		destroyChart('bookings-day-chart');

		var ctx = document.getElementById('bookings-day-chart');
		if (!ctx) return;

		charts['bookings-day-chart'] = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: data.labels,
				datasets: [{
					label: bkxReportsData.i18n.bookings,
					data: data.data,
					backgroundColor: colors.chartColors
				}]
			},
			options: $.extend(true, {}, chartDefaults, {
				scales: {
					y: {
						beginAtZero: true
					}
				}
			})
		});
	}

	/**
	 * Render bookings by time of day chart.
	 */
	function renderBookingsByTime(data) {
		if (!data || !data.labels) return;

		destroyChart('bookings-time-chart');

		var ctx = document.getElementById('bookings-time-chart');
		if (!ctx) return;

		charts['bookings-time-chart'] = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: data.labels,
				datasets: [{
					label: bkxReportsData.i18n.bookings,
					data: data.data,
					backgroundColor: colors.teal
				}]
			},
			options: $.extend(true, {}, chartDefaults, {
				scales: {
					y: {
						beginAtZero: true
					}
				}
			})
		});
	}

	/**
	 * Render peak times heatmap.
	 */
	function renderPeakTimesHeatmap(data) {
		if (!data || !data.heatmap) return;

		var $container = $('#peak-times-heatmap');
		var html = '<div class="bkx-heatmap-grid">';

		// Header row.
		html += '<div class="bkx-heatmap-cell bkx-heatmap-header"></div>';
		var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
		days.forEach(function(day) {
			html += '<div class="bkx-heatmap-cell bkx-heatmap-header">' + day + '</div>';
		});

		// Data rows.
		for (var hour = 0; hour < 24; hour++) {
			var hourLabel = (hour < 10 ? '0' : '') + hour + ':00';
			html += '<div class="bkx-heatmap-cell bkx-heatmap-time">' + hourLabel + '</div>';

			for (var day = 0; day < 7; day++) {
				var value = data.heatmap[hour] ? (data.heatmap[hour][day] || 0) : 0;
				var level = getHeatmapLevel(value, data.max_value);
				html += '<div class="bkx-heatmap-cell bkx-heatmap-value level-' + level + '">' + value + '</div>';
			}
		}

		html += '</div>';
		$container.html(html);
	}

	/**
	 * Get heatmap color level.
	 */
	function getHeatmapLevel(value, maxValue) {
		if (value === 0) return 0;
		if (maxValue === 0) return 0;

		var ratio = value / maxValue;
		if (ratio <= 0.25) return 1;
		if (ratio <= 0.5) return 2;
		if (ratio <= 0.75) return 3;
		return 4;
	}

	/**
	 * Load staff report.
	 */
	function loadStaffReport() {
		$.ajax({
			url: bkxReportsData.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_get_staff_report',
				nonce: bkxReportsData.nonce,
				date_from: dateFrom,
				date_to: dateTo
			},
			success: function(response) {
				hideLoading();
				if (response.success && response.data) {
					renderStaffSummary(response.data.summary);
					renderTopPerformers(response.data.top_performers);
					renderStaffUtilization(response.data.utilization);
					renderStaffComparison(response.data.comparison);
				}
			},
			error: function() {
				hideLoading();
				console.error('Failed to load staff report');
			}
		});
	}

	/**
	 * Render staff summary.
	 */
	function renderStaffSummary(summary) {
		$('#staff-summary [data-field="total_staff"]').text(summary.total_staff || 0);
		$('#staff-summary [data-field="active_staff"]').text(summary.active_staff || 0);
		$('#staff-summary [data-field="average_bookings"]').text(summary.average_bookings || 0);
		$('#staff-summary [data-field="staff_utilization_rate"]').text((summary.staff_utilization_rate || 0) + '%');
	}

	/**
	 * Render top performers table.
	 */
	function renderTopPerformers(data) {
		if (!data || !data.length) return;

		var $tbody = $('#top-performers-table tbody');
		$tbody.empty();

		data.forEach(function(staff, index) {
			var row = '<tr>';
			row += '<td>' + (index + 1) + '</td>';
			row += '<td>' + escapeHtml(staff.staff_name) + '</td>';
			row += '<td>' + staff.bookings + '</td>';
			row += '<td>' + staff.completed + '</td>';
			row += '<td>' + formatCurrency(staff.revenue) + '</td>';
			row += '<td>' + staff.completion_rate + '%</td>';
			row += '</tr>';
			$tbody.append(row);
		});
	}

	/**
	 * Render staff utilization chart.
	 */
	function renderStaffUtilization(data) {
		if (!data || !data.length) return;

		destroyChart('staff-utilization-chart');

		var ctx = document.getElementById('staff-utilization-chart');
		if (!ctx) return;

		var labels = data.map(function(item) { return item.staff_name; });
		var values = data.map(function(item) { return item.utilization_rate; });

		charts['staff-utilization-chart'] = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [{
					label: bkxReportsData.i18n.utilization,
					data: values,
					backgroundColor: colors.chartColors
				}]
			},
			options: $.extend(true, {}, chartDefaults, {
				indexAxis: 'y',
				scales: {
					x: {
						beginAtZero: true,
						max: 100,
						ticks: {
							callback: function(value) {
								return value + '%';
							}
						}
					}
				}
			})
		});
	}

	/**
	 * Render staff comparison chart.
	 */
	function renderStaffComparison(data) {
		if (!data || !data.staff || !data.staff.length) return;

		destroyChart('staff-comparison-chart');

		var ctx = document.getElementById('staff-comparison-chart');
		if (!ctx) return;

		var labels = data.staff.map(function(item) { return item.staff_name; });
		var scores = data.staff.map(function(item) { return item.performance_score; });

		charts['staff-comparison-chart'] = new Chart(ctx, {
			type: 'radar',
			data: {
				labels: labels,
				datasets: [{
					label: bkxReportsData.i18n.performance_score,
					data: scores,
					borderColor: colors.primary,
					backgroundColor: 'rgba(34, 113, 177, 0.2)',
					pointBackgroundColor: colors.primary
				}]
			},
			options: $.extend(true, {}, chartDefaults, {
				scales: {
					r: {
						beginAtZero: true
					}
				}
			})
		});
	}

	/**
	 * Load customer report.
	 */
	function loadCustomerReport() {
		$.ajax({
			url: bkxReportsData.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_get_customer_report',
				nonce: bkxReportsData.nonce,
				date_from: dateFrom,
				date_to: dateTo
			},
			success: function(response) {
				hideLoading();
				if (response.success && response.data) {
					renderCustomerSummary(response.data.summary, response.data.retention);
					renderNewVsReturning(response.data.new_vs_returning);
					renderLTVDistribution(response.data.lifetime_value);
					renderTopCustomers(response.data.top_customers);
					renderAcquisitionTrend(response.data.acquisition);
				}
			},
			error: function() {
				hideLoading();
				console.error('Failed to load customer report');
			}
		});
	}

	/**
	 * Render customer summary.
	 */
	function renderCustomerSummary(summary, retention) {
		$('#customer-summary [data-field="unique_customers"]').text(summary.unique_customers || 0);
		$('#customer-summary [data-field="avg_bookings_per_customer"]').text(summary.avg_bookings_per_customer || 0);
		$('#customer-summary [data-field="avg_revenue_per_customer"]').text(formatCurrency(summary.avg_revenue_per_customer || 0));
		$('#customer-summary [data-field="retention_rate"]').text((retention ? retention.retention_rate : 0) + '%');
	}

	/**
	 * Render new vs returning chart.
	 */
	function renderNewVsReturning(data) {
		if (!data) return;

		destroyChart('new-returning-chart');

		var ctx = document.getElementById('new-returning-chart');
		if (!ctx) return;

		charts['new-returning-chart'] = new Chart(ctx, {
			type: 'pie',
			data: {
				labels: [bkxReportsData.i18n.new_customers, bkxReportsData.i18n.returning_customers],
				datasets: [{
					data: [data.new, data.returning],
					backgroundColor: [colors.success, colors.primary]
				}]
			},
			options: chartDefaults
		});
	}

	/**
	 * Render LTV distribution chart.
	 */
	function renderLTVDistribution(data) {
		if (!data || !data.distribution) return;

		destroyChart('ltv-distribution-chart');

		var ctx = document.getElementById('ltv-distribution-chart');
		if (!ctx) return;

		charts['ltv-distribution-chart'] = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: data.distribution.labels,
				datasets: [{
					label: bkxReportsData.i18n.customers,
					data: data.distribution.data,
					backgroundColor: colors.chartColors
				}]
			},
			options: $.extend(true, {}, chartDefaults, {
				scales: {
					y: {
						beginAtZero: true
					}
				}
			})
		});
	}

	/**
	 * Render top customers table.
	 */
	function renderTopCustomers(data) {
		if (!data || !data.length) return;

		var $tbody = $('#top-customers-table tbody');
		$tbody.empty();

		data.forEach(function(customer) {
			var row = '<tr>';
			row += '<td>' + escapeHtml(customer.name) + '</td>';
			row += '<td>' + escapeHtml(customer.email) + '</td>';
			row += '<td>' + customer.bookings + '</td>';
			row += '<td>' + formatCurrency(customer.total_spent) + '</td>';
			row += '</tr>';
			$tbody.append(row);
		});
	}

	/**
	 * Render acquisition trend chart.
	 */
	function renderAcquisitionTrend(data) {
		if (!data || !data.labels) return;

		destroyChart('acquisition-trend-chart');

		var ctx = document.getElementById('acquisition-trend-chart');
		if (!ctx) return;

		charts['acquisition-trend-chart'] = new Chart(ctx, {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: [{
					label: bkxReportsData.i18n.new_customers,
					data: data.data,
					borderColor: colors.success,
					backgroundColor: 'rgba(30, 142, 62, 0.1)',
					fill: true,
					tension: 0.4
				}]
			},
			options: $.extend(true, {}, chartDefaults, {
				scales: {
					y: {
						beginAtZero: true,
						ticks: {
							stepSize: 1
						}
					}
				}
			})
		});
	}

	/**
	 * Load recent exports.
	 */
	function loadRecentExports() {
		$.ajax({
			url: bkxReportsData.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_get_recent_exports',
				nonce: bkxReportsData.nonce
			},
			success: function(response) {
				hideLoading();
				if (response.success && response.data) {
					renderRecentExports(response.data);
				}
			},
			error: function() {
				hideLoading();
			}
		});
	}

	/**
	 * Render recent exports table.
	 */
	function renderRecentExports(data) {
		var $tbody = $('#recent-exports-table tbody');
		$tbody.empty();

		if (!data || !data.length) {
			$tbody.append('<tr><td colspan="6">' + bkxReportsData.i18n.no_exports + '</td></tr>');
			return;
		}

		data.forEach(function(exportItem) {
			var statusClass = 'bkx-status-badge ' + exportItem.status;
			var row = '<tr>';
			row += '<td>' + escapeHtml(exportItem.report_type) + '</td>';
			row += '<td>' + exportItem.format.toUpperCase() + '</td>';
			row += '<td>' + exportItem.date_from + ' - ' + exportItem.date_to + '</td>';
			row += '<td>' + exportItem.created_at + '</td>';
			row += '<td><span class="' + statusClass + '">' + exportItem.status + '</span></td>';
			row += '<td>';
			if (exportItem.status === 'completed' && exportItem.download_url) {
				row += '<a href="' + exportItem.download_url + '" class="button button-small">' + bkxReportsData.i18n.download + '</a>';
			}
			row += '</td>';
			row += '</tr>';
			$tbody.append(row);
		});
	}

	/**
	 * Initialize export form.
	 */
	function initExportForm() {
		$('#bkx-export-form').on('submit', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $button = $form.find('#bkx-generate-export');

			$button.prop('disabled', true).text(bkxReportsData.i18n.generating);

			$.ajax({
				url: bkxReportsData.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_generate_export',
					nonce: bkxReportsData.nonce,
					report_type: $form.find('#export-type').val(),
					format: $form.find('#export-format').val(),
					date_from: $form.find('#export-date-from').val(),
					date_to: $form.find('#export-date-to').val()
				},
				success: function(response) {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> ' + bkxReportsData.i18n.generate_export);

					if (response.success) {
						loadRecentExports();
						alert(bkxReportsData.i18n.export_started);
					} else {
						alert(response.data || bkxReportsData.i18n.export_failed);
					}
				},
				error: function() {
					$button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> ' + bkxReportsData.i18n.generate_export);
					alert(bkxReportsData.i18n.export_failed);
				}
			});
		});
	}

	/**
	 * Initialize schedule form.
	 */
	function initScheduleForm() {
		$('#bkx-schedule-form').on('submit', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $button = $form.find('#bkx-create-schedule');

			$button.prop('disabled', true);

			$.ajax({
				url: bkxReportsData.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_create_report_schedule',
					nonce: bkxReportsData.nonce,
					report_type: $form.find('#schedule-type').val(),
					frequency: $form.find('#schedule-frequency').val(),
					email: $form.find('#schedule-email').val()
				},
				success: function(response) {
					$button.prop('disabled', false);

					if (response.success) {
						alert(bkxReportsData.i18n.schedule_created);
					} else {
						alert(response.data || bkxReportsData.i18n.schedule_failed);
					}
				},
				error: function() {
					$button.prop('disabled', false);
					alert(bkxReportsData.i18n.schedule_failed);
				}
			});
		});
	}

	/**
	 * Destroy a chart instance.
	 */
	function destroyChart(id) {
		if (charts[id]) {
			charts[id].destroy();
			delete charts[id];
		}
	}

	/**
	 * Escape HTML.
	 */
	function escapeHtml(text) {
		if (!text) return '';
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

})(jQuery);
