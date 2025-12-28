/**
 * Advanced Analytics Admin JavaScript.
 *
 * @package BookingX\AdvancedAnalytics
 * @since   1.0.0
 */

(function($) {
	'use strict';

	var charts = {};
	var state = {
		startDate: null,
		endDate: null,
		currentTab: 'overview'
	};

	/**
	 * Initialize.
	 */
	function init() {
		setDefaultDates();
		bindEvents();
		loadOverview();
	}

	/**
	 * Set default dates (last 30 days).
	 */
	function setDefaultDates() {
		var today = new Date();
		var thirtyDaysAgo = new Date(today);
		thirtyDaysAgo.setDate(today.getDate() - 30);

		state.endDate = formatDate(today);
		state.startDate = formatDate(thirtyDaysAgo);

		$('#bkx-aa-start').val(state.startDate);
		$('#bkx-aa-end').val(state.endDate);
	}

	/**
	 * Bind events.
	 */
	function bindEvents() {
		// Tab navigation.
		$('.bkx-aa-tabs .nav-tab').on('click', function(e) {
			e.preventDefault();
			var tab = $(this).data('tab');
			switchTab(tab);
		});

		// Analyze button.
		$('#bkx-aa-analyze').on('click', function() {
			state.startDate = $('#bkx-aa-start').val();
			state.endDate = $('#bkx-aa-end').val();
			loadCurrentTab();
		});

		// Quick date buttons.
		$('.bkx-aa-quick').on('click', function() {
			var days = $(this).data('days');
			setDateRange(days);
		});

		// Analysis buttons.
		$('.bkx-aa-analysis-btn').on('click', function() {
			var type = $(this).data('type');
			loadAnalysis(type);
		});

		// Cohort analysis.
		$('#run-cohort').on('click', loadCohortAnalysis);

		// Comparison.
		$('#run-comparison').on('click', loadComparison);

		// Segments.
		$('#run-segments').on('click', loadSegments);

		// Patterns.
		$('#run-patterns').on('click', loadPatterns);
	}

	/**
	 * Switch tab.
	 */
	function switchTab(tab) {
		state.currentTab = tab;

		$('.bkx-aa-tabs .nav-tab').removeClass('nav-tab-active');
		$('.bkx-aa-tabs .nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active');

		$('.bkx-aa-tab-pane').removeClass('active');
		$('#tab-' + tab).addClass('active');

		loadCurrentTab();
	}

	/**
	 * Load current tab data.
	 */
	function loadCurrentTab() {
		switch (state.currentTab) {
			case 'overview':
				loadOverview();
				break;
			case 'cohorts':
				// Loaded on button click.
				break;
			case 'comparison':
				// Loaded on button click.
				break;
			case 'segments':
				// Loaded on button click.
				break;
			case 'patterns':
				// Loaded on button click.
				break;
		}
	}

	/**
	 * Set date range from quick button.
	 */
	function setDateRange(days) {
		var today = new Date();
		var start = new Date(today);
		start.setDate(today.getDate() - days);

		state.endDate = formatDate(today);
		state.startDate = formatDate(start);

		$('#bkx-aa-start').val(state.startDate);
		$('#bkx-aa-end').val(state.endDate);

		loadCurrentTab();
	}

	/**
	 * Load overview.
	 */
	function loadOverview() {
		$('#overview-summary').html('<div class="bkx-aa-loading"></div>');

		$.ajax({
			url: bkxAA.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_aa_get_booking_analysis',
				nonce: bkxAA.nonce,
				start_date: state.startDate,
				end_date: state.endDate,
				analysis_type: 'overview'
			},
			success: function(response) {
				if (response.success) {
					renderOverview(response.data);
				}
			}
		});
	}

	/**
	 * Render overview.
	 */
	function renderOverview(data) {
		var summary = data.summary;
		var template = wp.template('bkx-aa-summary-item');
		var $container = $('#overview-summary');
		$container.empty();

		var items = [
			{ value: formatNumber(summary.total_bookings), label: bkxAA.i18n.bookings },
			{ value: bkxAA.currencySymbol + formatNumber(summary.total_revenue), label: bkxAA.i18n.revenue },
			{ value: formatNumber(summary.unique_customers), label: bkxAA.i18n.customers },
			{ value: bkxAA.currencySymbol + formatNumber(summary.avg_booking_value), label: 'Avg Value' },
			{ value: data.rates.completion_rate + '%', label: 'Completion' },
			{ value: data.rates.cancellation_rate + '%', label: 'Cancellation' }
		];

		items.forEach(function(item) {
			$container.append(template(item));
		});

		// Render trend chart.
		renderTrendChart(data.daily_trend);

		// Render top lists.
		renderTopList('#top-services', data.top_services, 'revenue');
		renderTopList('#top-staff', data.top_staff, 'revenue');
	}

	/**
	 * Render trend chart.
	 */
	function renderTrendChart(data) {
		var ctx = document.getElementById('chart-daily-trend');
		if (!ctx) return;

		if (charts.dailyTrend) {
			charts.dailyTrend.destroy();
		}

		charts.dailyTrend = new Chart(ctx, {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: [
					{
						label: bkxAA.i18n.bookings,
						data: data.bookings,
						borderColor: '#0073aa',
						backgroundColor: 'rgba(0, 115, 170, 0.1)',
						fill: true,
						yAxisID: 'y'
					},
					{
						label: bkxAA.i18n.revenue,
						data: data.revenue,
						borderColor: '#46b450',
						backgroundColor: 'transparent',
						yAxisID: 'y1'
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					y: {
						type: 'linear',
						position: 'left',
						beginAtZero: true
					},
					y1: {
						type: 'linear',
						position: 'right',
						beginAtZero: true,
						grid: { drawOnChartArea: false }
					}
				}
			}
		});
	}

	/**
	 * Render top list.
	 */
	function renderTopList(selector, data, valueField) {
		var $container = $(selector);
		var template = wp.template('bkx-aa-list-item');
		$container.empty();

		if (!data || !data.length) {
			$container.html('<p class="bkx-aa-placeholder">' + bkxAA.i18n.noData + '</p>');
			return;
		}

		data.forEach(function(item, index) {
			$container.append(template({
				rank: index + 1,
				name: item.name,
				value: valueField === 'revenue' ? bkxAA.currencySymbol + formatNumber(item[valueField]) : formatNumber(item[valueField])
			}));
		});
	}

	/**
	 * Load specific analysis.
	 */
	function loadAnalysis(type) {
		var $results = $('#analysis-results');
		$results.html('<div class="bkx-aa-loading"></div>');

		$.ajax({
			url: bkxAA.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_aa_get_booking_analysis',
				nonce: bkxAA.nonce,
				start_date: state.startDate,
				end_date: state.endDate,
				analysis_type: type
			},
			success: function(response) {
				if (response.success) {
					renderAnalysis(type, response.data);
				}
			}
		});
	}

	/**
	 * Render analysis results.
	 */
	function renderAnalysis(type, data) {
		var $results = $('#analysis-results');
		$results.empty();

		if (type === 'conversion') {
			renderConversionFunnel(data, $results);
		} else if (type === 'timing') {
			renderTimingAnalysis(data, $results);
		} else if (type === 'cancellation') {
			renderCancellationAnalysis(data, $results);
		}
	}

	/**
	 * Render conversion funnel.
	 */
	function renderConversionFunnel(data, $container) {
		var html = '<div class="bkx-aa-funnel">';
		html += '<h4>Conversion Funnel</h4>';

		data.funnel.forEach(function(stage) {
			var width = stage.percentage;
			html += '<div class="bkx-aa-funnel-stage" style="width: ' + width + '%">';
			html += '<span class="stage-name">' + stage.stage + '</span>';
			html += '<span class="stage-count">' + stage.count + ' (' + stage.percentage + '%)</span>';
			html += '</div>';
		});

		html += '</div>';
		html += '<div class="bkx-aa-summary-grid">';
		html += '<div class="bkx-aa-summary-item"><div class="bkx-aa-summary-value">' + data.conversion_rates.overall_conversion + '%</div><div class="bkx-aa-summary-label">Overall Conversion</div></div>';
		html += '<div class="bkx-aa-summary-item"><div class="bkx-aa-summary-value">' + data.drop_off.pre_confirmation + '</div><div class="bkx-aa-summary-label">Pre-Confirmation Drop</div></div>';
		html += '<div class="bkx-aa-summary-item"><div class="bkx-aa-summary-value">' + data.drop_off.post_confirmation + '</div><div class="bkx-aa-summary-label">Post-Confirmation Drop</div></div>';
		html += '</div>';

		$container.html(html);
	}

	/**
	 * Render timing analysis.
	 */
	function renderTimingAnalysis(data, $container) {
		$container.html('<canvas id="chart-timing" style="max-height: 300px;"></canvas><div id="timing-insights"></div>');

		var ctx = document.getElementById('chart-timing');
		new Chart(ctx, {
			type: 'bar',
			data: {
				labels: data.lead_time.chart_data.labels,
				datasets: [{
					label: 'Bookings',
					data: data.lead_time.chart_data.data,
					backgroundColor: '#0073aa'
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false
			}
		});

		renderInsights(data.insights, '#timing-insights');
	}

	/**
	 * Render cancellation analysis.
	 */
	function renderCancellationAnalysis(data, $container) {
		var html = '<div class="bkx-aa-summary-grid">';
		html += '<div class="bkx-aa-summary-item"><div class="bkx-aa-summary-value">' + data.summary.cancelled + '</div><div class="bkx-aa-summary-label">Cancelled</div></div>';
		html += '<div class="bkx-aa-summary-item"><div class="bkx-aa-summary-value">' + data.summary.cancellation_rate + '%</div><div class="bkx-aa-summary-label">Rate</div></div>';
		html += '<div class="bkx-aa-summary-item"><div class="bkx-aa-summary-value">' + bkxAA.currencySymbol + formatNumber(data.summary.lost_revenue) + '</div><div class="bkx-aa-summary-label">Lost Revenue</div></div>';
		html += '</div>';
		html += '<canvas id="chart-cancellation" style="max-height: 300px;"></canvas>';

		$container.html(html);

		var ctx = document.getElementById('chart-cancellation');
		new Chart(ctx, {
			type: 'bar',
			data: {
				labels: data.chart_data.daily.labels,
				datasets: [{
					label: 'Cancellation Rate %',
					data: data.chart_data.daily.data,
					backgroundColor: '#dc3232'
				}]
			}
		});
	}

	/**
	 * Load cohort analysis.
	 */
	function loadCohortAnalysis() {
		var cohortType = $('#cohort-type').val();
		var metric = $('#cohort-metric').val();

		$('#cohort-matrix').html('<div class="bkx-aa-loading"></div>');

		$.ajax({
			url: bkxAA.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_aa_get_cohort_analysis',
				nonce: bkxAA.nonce,
				cohort_type: cohortType,
				metric: metric
			},
			success: function(response) {
				if (response.success) {
					renderCohortMatrix(response.data);
				}
			}
		});
	}

	/**
	 * Render cohort matrix.
	 */
	function renderCohortMatrix(data) {
		var $container = $('#cohort-matrix');

		if (!data.matrix || !data.matrix.length) {
			$container.html('<p class="bkx-aa-placeholder">' + bkxAA.i18n.noData + '</p>');
			return;
		}

		var html = '<table>';
		html += '<thead><tr><th>Cohort</th><th>Size</th>';

		if (data.header_labels) {
			data.header_labels.forEach(function(label) {
				html += '<th>' + label + '</th>';
			});
		}
		html += '</tr></thead><tbody>';

		data.matrix.forEach(function(row) {
			html += '<tr>';
			html += '<td class="cohort-label">' + row.cohort + '</td>';
			html += '<td>' + row.size + '</td>';

			row.periods.forEach(function(value, idx) {
				var color = getCohortCellColor(value, data.type);
				html += '<td style="--cell-color: ' + color + '">' + value + (data.type === 'retention' ? '%' : '') + '</td>';
			});

			html += '</tr>';
		});

		html += '</tbody></table>';
		$container.html(html);

		if (data.insights) {
			renderInsights(data.insights, '#cohort-insights');
		}
	}

	/**
	 * Get cohort cell color.
	 */
	function getCohortCellColor(value, type) {
		if (type === 'retention') {
			if (value >= 50) return 'rgba(0, 163, 42, 0.3)';
			if (value >= 30) return 'rgba(0, 163, 42, 0.2)';
			if (value >= 15) return 'rgba(255, 185, 0, 0.2)';
			if (value > 0) return 'rgba(214, 54, 56, 0.1)';
			return '#fff';
		}
		return '#fff';
	}

	/**
	 * Load comparison.
	 */
	function loadComparison() {
		var data = {
			action: 'bkx_aa_get_comparison',
			nonce: bkxAA.nonce,
			period_a_start: $('#period-a-start').val(),
			period_a_end: $('#period-a-end').val(),
			period_b_start: $('#period-b-start').val(),
			period_b_end: $('#period-b-end').val(),
			dimensions: ['revenue', 'bookings', 'customers', 'avg_value']
		};

		$('#comparison-results').html('<div class="bkx-aa-loading"></div>');

		$.ajax({
			url: bkxAA.ajaxUrl,
			type: 'POST',
			data: data,
			success: function(response) {
				if (response.success) {
					renderComparison(response.data);
				}
			}
		});
	}

	/**
	 * Render comparison.
	 */
	function renderComparison(data) {
		var $container = $('#comparison-results');
		var template = wp.template('bkx-aa-comparison-change');
		$container.empty();

		for (var key in data.changes) {
			var change = data.changes[key];
			$container.append(template({
				label: key.replace(/_/g, ' ').toUpperCase(),
				period_a: formatValue(change.period_a, key),
				period_b: formatValue(change.period_b, key),
				percent_change: change.percent_change,
				trend: change.trend
			}));
		}

		if (data.insights) {
			var $insights = $('<div class="bkx-aa-insights" style="grid-column: 1/-1;"></div>');
			$container.append($insights);
			renderInsights(data.insights, $insights);
		}
	}

	/**
	 * Load segments.
	 */
	function loadSegments() {
		var segmentType = $('#segment-type').val();

		$('#segment-details').html('<div class="bkx-aa-loading"></div>');

		$.ajax({
			url: bkxAA.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_aa_get_segments',
				nonce: bkxAA.nonce,
				segment_type: segmentType,
				start_date: state.startDate,
				end_date: state.endDate
			},
			success: function(response) {
				if (response.success) {
					renderSegments(response.data);
				}
			}
		});
	}

	/**
	 * Render segments.
	 */
	function renderSegments(data) {
		// Chart.
		var ctx = document.getElementById('chart-segments');
		if (charts.segments) charts.segments.destroy();

		charts.segments = new Chart(ctx, {
			type: 'doughnut',
			data: data.chart_data,
			options: {
				responsive: true,
				maintainAspectRatio: false
			}
		});

		// Details.
		var $details = $('#segment-details');
		$details.empty();

		for (var key in data.segments) {
			var seg = data.segments[key];
			var html = '<div class="bkx-aa-list-item">';
			html += '<span class="bkx-aa-list-name">' + (seg.label || key) + '</span>';
			html += '<span class="bkx-aa-list-value">' + seg.count + ' customers</span>';
			html += '</div>';
			$details.append(html);
		}

		if (data.insights) {
			renderInsights(data.insights, '#segment-insights');
		}
	}

	/**
	 * Load patterns.
	 */
	function loadPatterns() {
		var patternType = $('#pattern-type').val();

		$('#pattern-details').html('<div class="bkx-aa-loading"></div>');

		$.ajax({
			url: bkxAA.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_aa_get_patterns',
				nonce: bkxAA.nonce,
				pattern_type: patternType,
				start_date: state.startDate,
				end_date: state.endDate
			},
			success: function(response) {
				if (response.success) {
					renderPatterns(patternType, response.data);
				}
			}
		});
	}

	/**
	 * Render patterns.
	 */
	function renderPatterns(type, data) {
		var ctx = document.getElementById('chart-patterns');
		if (charts.patterns) charts.patterns.destroy();

		var chartData = type === 'seasonal' ? data.monthly : data.chart_data;

		charts.patterns = new Chart(ctx, {
			type: type === 'anomaly' ? 'line' : 'bar',
			data: {
				labels: chartData.labels,
				datasets: chartData.datasets || [{
					label: 'Bookings',
					data: chartData.data,
					backgroundColor: '#0073aa'
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false
			}
		});

		// Details.
		var $details = $('#pattern-details');
		$details.empty();

		if (type === 'seasonal') {
			$details.html('<p><strong>Peak:</strong> ' + data.peak_month + '</p><p><strong>Low:</strong> ' + data.low_month + '</p>');
		} else if (type === 'trend' && data.trends) {
			for (var key in data.trends) {
				var trend = data.trends[key];
				$details.append('<p><strong>' + key + ':</strong> ' + trend.direction + ' (' + trend.strength + ')</p>');
			}
		} else if (type === 'anomaly' && data.anomalies) {
			data.anomalies.forEach(function(a) {
				$details.append('<p><strong>' + a.date + ':</strong> ' + a.description + '</p>');
			});
		}

		if (data.insights) {
			renderInsights(data.insights, '#pattern-insights');
		}
	}

	/**
	 * Render insights.
	 */
	function renderInsights(insights, selector) {
		var $container = $(selector);
		if (typeof $container === 'string') {
			$container = $(selector);
		}
		$container.empty();

		if (!insights || !insights.length) return;

		var template = wp.template('bkx-aa-insight');
		insights.forEach(function(insight) {
			$container.append(template(insight));
		});
	}

	/**
	 * Format date.
	 */
	function formatDate(date) {
		return date.toISOString().split('T')[0];
	}

	/**
	 * Format number.
	 */
	function formatNumber(num) {
		return parseFloat(num || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
	}

	/**
	 * Format value based on key.
	 */
	function formatValue(value, key) {
		if (key === 'revenue' || key === 'avg_value') {
			return bkxAA.currencySymbol + formatNumber(value);
		}
		return formatNumber(value);
	}

	// Initialize on ready.
	$(document).ready(init);

})(jQuery);
