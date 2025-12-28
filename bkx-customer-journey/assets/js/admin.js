/**
 * BookingX Customer Journey - Admin JavaScript
 *
 * @package BookingX\CustomerJourney
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKX_CJ = {
		charts: {},
		currentTab: 'overview',
		dateRange: {
			start: '',
			end: ''
		},

		init: function() {
			this.initDateRange();
			this.bindEvents();
			this.loadTab('overview');
		},

		initDateRange: function() {
			// Default to last 30 days
			const end = new Date();
			const start = new Date();
			start.setDate(start.getDate() - 30);

			$('#bkx-cj-start-date').val(this.formatDate(start));
			$('#bkx-cj-end-date').val(this.formatDate(end));

			this.dateRange.start = this.formatDate(start);
			this.dateRange.end = this.formatDate(end);
		},

		formatDate: function(date) {
			return date.toISOString().split('T')[0];
		},

		bindEvents: function() {
			const self = this;

			// Tab switching
			$('.bkx-cj-tabs .nav-tab').on('click', function(e) {
				e.preventDefault();
				const tab = $(this).data('tab');
				self.switchTab(tab);
			});

			// Date filter
			$('#bkx-cj-apply-filter').on('click', function() {
				self.dateRange.start = $('#bkx-cj-start-date').val();
				self.dateRange.end = $('#bkx-cj-end-date').val();
				self.loadTab(self.currentTab);
			});

			// Quick date ranges
			$('.bkx-cj-range-btn').on('click', function() {
				const days = parseInt($(this).data('range'));
				const end = new Date();
				const start = new Date();
				start.setDate(start.getDate() - days);

				$('#bkx-cj-start-date').val(self.formatDate(start));
				$('#bkx-cj-end-date').val(self.formatDate(end));

				$('.bkx-cj-range-btn').removeClass('active');
				$(this).addClass('active');

				self.dateRange.start = self.formatDate(start);
				self.dateRange.end = self.formatDate(end);
				self.loadTab(self.currentTab);
			});

			// Attribution model change
			$('#attribution-model').on('change', function() {
				self.loadAttribution();
			});

			// Customer lookup
			$('#lookup-customer').on('click', function() {
				self.lookupCustomer();
			});

			$('#customer-email').on('keypress', function(e) {
				if (e.which === 13) {
					self.lookupCustomer();
				}
			});
		},

		switchTab: function(tab) {
			$('.bkx-cj-tabs .nav-tab').removeClass('nav-tab-active');
			$('.bkx-cj-tabs .nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active');

			$('.bkx-cj-tab-content').removeClass('active');
			$('#' + tab).addClass('active');

			this.currentTab = tab;
			this.loadTab(tab);
		},

		loadTab: function(tab) {
			switch (tab) {
				case 'overview':
					this.loadOverview();
					break;
				case 'touchpoints':
					this.loadTouchpoints();
					break;
				case 'lifecycle':
					this.loadLifecycle();
					break;
				case 'attribution':
					this.loadAttribution();
					break;
			}
		},

		loadOverview: function() {
			const self = this;

			this.ajax('bkx_cj_get_journey_overview', {}, function(data) {
				// Update summary cards
				$('#total-journeys').text(data.summary.total_journeys);
				$('#conversion-rate').text(data.summary.conversion_rate + '%');
				$('#avg-touchpoints').text(data.summary.avg_touchpoints);
				$('#avg-duration').text(data.summary.avg_duration_mins + ' min');

				// Render funnel
				self.renderFunnel(data.funnel);

				// Render outcomes chart
				self.renderOutcomesChart(data.by_outcome);

				// Render daily trends
				self.renderDailyTrends(data.daily_trends);

				// Render drop-off table
				self.renderDropOffTable(data.drop_off);
			});
		},

		renderFunnel: function(funnel) {
			const container = $('#journey-funnel');
			container.empty();

			if (!funnel || funnel.length === 0) {
				container.html('<p class="bkx-cj-loading">' + bkxCJ.i18n.noData + '</p>');
				return;
			}

			const maxCount = Math.max(...funnel.map(s => s.count));

			funnel.forEach(function(stage) {
				const width = maxCount > 0 ? (stage.count / maxCount * 100) : 0;
				const html = `
					<div class="bkx-cj-funnel-stage">
						<div class="bkx-cj-funnel-bar-container">
							<div class="bkx-cj-funnel-bar" style="width: ${width}%">
								<span class="bkx-cj-funnel-bar-label">${stage.label}</span>
							</div>
						</div>
						<span class="bkx-cj-funnel-count">${stage.count}</span>
						<span class="bkx-cj-funnel-rate">${stage.conversion_rate}%</span>
					</div>
				`;
				container.append(html);
			});
		},

		renderOutcomesChart: function(outcomes) {
			const ctx = document.getElementById('outcomes-chart');
			if (!ctx) return;

			if (this.charts.outcomes) {
				this.charts.outcomes.destroy();
			}

			const colors = {
				converted: '#00a32a',
				abandoned: '#dba617',
				bounced: '#d63638',
				in_progress: '#72aee6'
			};

			this.charts.outcomes = new Chart(ctx, {
				type: 'doughnut',
				data: {
					labels: outcomes.map(o => o.label),
					datasets: [{
						data: outcomes.map(o => o.count),
						backgroundColor: outcomes.map(o => colors[o.outcome] || '#9CA3AF')
					}]
				},
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
		},

		renderDailyTrends: function(trends) {
			const ctx = document.getElementById('daily-trends-chart');
			if (!ctx) return;

			if (this.charts.dailyTrends) {
				this.charts.dailyTrends.destroy();
			}

			this.charts.dailyTrends = new Chart(ctx, {
				type: 'line',
				data: {
					labels: trends.map(t => t.date),
					datasets: [
						{
							label: 'Total Journeys',
							data: trends.map(t => t.total),
							borderColor: '#2271b1',
							backgroundColor: 'rgba(34, 113, 177, 0.1)',
							fill: true,
							yAxisID: 'y'
						},
						{
							label: 'Conversions',
							data: trends.map(t => t.converted),
							borderColor: '#00a32a',
							backgroundColor: 'transparent',
							yAxisID: 'y'
						},
						{
							label: 'Conversion Rate',
							data: trends.map(t => t.conversion_rate),
							borderColor: '#dba617',
							backgroundColor: 'transparent',
							borderDash: [5, 5],
							yAxisID: 'y1'
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						intersect: false,
						mode: 'index'
					},
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
							max: 100,
							grid: { drawOnChartArea: false },
							ticks: { callback: v => v + '%' }
						}
					}
				}
			});
		},

		renderDropOffTable: function(dropoffs) {
			const tbody = $('#dropoff-table');
			tbody.empty();

			if (!dropoffs || dropoffs.length === 0) {
				tbody.html('<tr><td colspan="3">' + bkxCJ.i18n.noData + '</td></tr>');
				return;
			}

			dropoffs.forEach(function(item) {
				const row = `
					<tr>
						<td>${item.last_touchpoint}</td>
						<td>${item.page || '-'}</td>
						<td>${item.count}</td>
					</tr>
				`;
				tbody.append(row);
			});
		},

		loadTouchpoints: function() {
			const self = this;

			this.ajax('bkx_cj_get_touchpoint_analysis', {}, function(data) {
				// Update summary
				$('#total-touchpoints').text(data.summary.total_touchpoints);
				$('#unique-sessions').text(data.summary.unique_sessions);
				$('#avg-per-session').text(data.summary.avg_per_session);
				$('#avg-to-convert').text(data.summary.avg_touchpoints_to_conv);

				// Render charts
				self.renderTouchpointsByType(data.by_type);
				self.renderTouchpointsByDevice(data.by_device);
				self.renderHourlyPatterns(data.hourly_patterns);

				// Render tables
				self.renderPopularPages(data.popular_pages);
				self.renderReferrerSources(data.referrer_sources);
			});
		},

		renderTouchpointsByType: function(data) {
			const ctx = document.getElementById('touchpoints-by-type-chart');
			if (!ctx) return;

			if (this.charts.byType) {
				this.charts.byType.destroy();
			}

			this.charts.byType = new Chart(ctx, {
				type: 'bar',
				data: {
					labels: data.map(d => d.label),
					datasets: [{
						label: 'Count',
						data: data.map(d => d.count),
						backgroundColor: '#2271b1'
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					indexAxis: 'y',
					plugins: {
						legend: { display: false }
					}
				}
			});
		},

		renderTouchpointsByDevice: function(data) {
			const ctx = document.getElementById('touchpoints-by-device-chart');
			if (!ctx) return;

			if (this.charts.byDevice) {
				this.charts.byDevice.destroy();
			}

			const colors = {
				desktop: '#2271b1',
				mobile: '#00a32a',
				tablet: '#dba617'
			};

			this.charts.byDevice = new Chart(ctx, {
				type: 'pie',
				data: {
					labels: data.map(d => d.device),
					datasets: [{
						data: data.map(d => d.count),
						backgroundColor: data.map(d => colors[d.device] || '#9CA3AF')
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { position: 'right' }
					}
				}
			});
		},

		renderHourlyPatterns: function(data) {
			const ctx = document.getElementById('hourly-patterns-chart');
			if (!ctx) return;

			if (this.charts.hourly) {
				this.charts.hourly.destroy();
			}

			this.charts.hourly = new Chart(ctx, {
				type: 'bar',
				data: {
					labels: data.map(d => d.label),
					datasets: [{
						label: 'Activity',
						data: data.map(d => d.count),
						backgroundColor: '#72aee6'
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { display: false }
					},
					scales: {
						y: { beginAtZero: true }
					}
				}
			});
		},

		renderPopularPages: function(pages) {
			const tbody = $('#popular-pages-table');
			tbody.empty();

			if (!pages || pages.length === 0) {
				tbody.html('<tr><td colspan="3">' + bkxCJ.i18n.noData + '</td></tr>');
				return;
			}

			pages.forEach(function(page) {
				const row = `
					<tr>
						<td title="${page.url}">${page.path}</td>
						<td>${page.views}</td>
						<td>${page.unique_views}</td>
					</tr>
				`;
				tbody.append(row);
			});
		},

		renderReferrerSources: function(sources) {
			const tbody = $('#referrer-sources-table');
			tbody.empty();

			if (!sources || sources.length === 0) {
				tbody.html('<tr><td colspan="3">' + bkxCJ.i18n.noData + '</td></tr>');
				return;
			}

			sources.forEach(function(source) {
				const row = `
					<tr>
						<td>${source.source}</td>
						<td>${source.count}</td>
						<td>${source.sessions}</td>
					</tr>
				`;
				tbody.append(row);
			});
		},

		loadLifecycle: function() {
			const self = this;

			this.ajax('bkx_cj_get_lifecycle_data', {}, function(data) {
				self.renderLifecycleStages(data.stages);
				self.renderLifecycleChart(data.stages);
				self.renderTransitions(data.transitions);
			});
		},

		renderLifecycleStages: function(stages) {
			const container = $('#lifecycle-stages');
			container.empty();

			stages.forEach(function(stage) {
				const html = `
					<div class="bkx-cj-stage-card stage-${stage.stage}">
						<span class="stage-count">${stage.count}</span>
						<span class="stage-label">${stage.label}</span>
						<span class="stage-revenue">${bkxCJ.currencySymbol}${stage.revenue.toFixed(2)}</span>
					</div>
				`;
				container.append(html);
			});
		},

		renderLifecycleChart: function(stages) {
			const ctx = document.getElementById('lifecycle-chart');
			if (!ctx) return;

			if (this.charts.lifecycle) {
				this.charts.lifecycle.destroy();
			}

			this.charts.lifecycle = new Chart(ctx, {
				type: 'bar',
				data: {
					labels: stages.map(s => s.label),
					datasets: [{
						label: 'Customers',
						data: stages.map(s => s.count),
						backgroundColor: stages.map(s => s.color)
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { display: false }
					},
					scales: {
						y: { beginAtZero: true }
					}
				}
			});
		},

		renderTransitions: function(transitions) {
			const container = $('#lifecycle-transitions');
			container.empty();

			const stageColors = {
				lead: '#9CA3AF',
				prospect: '#60A5FA',
				customer: '#34D399',
				loyal: '#A78BFA',
				champion: '#F59E0B',
				at_risk: '#F97316',
				churned: '#EF4444'
			};

			transitions.forEach(function(t) {
				const html = `
					<div class="bkx-cj-transition">
						<span class="from" style="background: ${stageColors[t.from]}">${t.from}</span>
						<span class="arrow">→</span>
						<span class="to" style="background: ${stageColors[t.to]}">${t.to}</span>
					</div>
				`;
				container.append(html);
			});
		},

		loadAttribution: function() {
			const self = this;
			const model = $('#attribution-model').val();

			this.ajax('bkx_cj_get_attribution', { model: model }, function(data) {
				// Update summary
				$('#attr-conversions').text(data.summary.total_conversions);
				$('#attr-revenue').text(bkxCJ.currencySymbol + data.summary.total_revenue.toFixed(2));
				$('#attr-path-length').text(data.summary.avg_path_length);
				$('#attr-multi-touch').text(data.summary.multi_touch_rate + '%');

				// Render charts
				self.renderAttributionChart(data.channels);
				self.renderRevenueAttributionChart(data.channels);

				// Render paths table
				self.renderConversionPaths(data.paths);
			});
		},

		renderAttributionChart: function(channels) {
			const ctx = document.getElementById('attribution-chart');
			if (!ctx) return;

			if (this.charts.attribution) {
				this.charts.attribution.destroy();
			}

			this.charts.attribution = new Chart(ctx, {
				type: 'doughnut',
				data: {
					labels: channels.map(c => c.label),
					datasets: [{
						data: channels.map(c => c.conversions),
						backgroundColor: channels.map(c => c.color)
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { position: 'right' }
					}
				}
			});
		},

		renderRevenueAttributionChart: function(channels) {
			const ctx = document.getElementById('revenue-attribution-chart');
			if (!ctx) return;

			if (this.charts.revenueAttr) {
				this.charts.revenueAttr.destroy();
			}

			this.charts.revenueAttr = new Chart(ctx, {
				type: 'bar',
				data: {
					labels: channels.map(c => c.label),
					datasets: [{
						label: 'Revenue',
						data: channels.map(c => c.revenue),
						backgroundColor: channels.map(c => c.color)
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { display: false }
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: function(v) {
									return bkxCJ.currencySymbol + v;
								}
							}
						}
					}
				}
			});
		},

		renderConversionPaths: function(paths) {
			const tbody = $('#conversion-paths-table');
			tbody.empty();

			if (!paths || paths.length === 0) {
				tbody.html('<tr><td colspan="3">' + bkxCJ.i18n.noData + '</td></tr>');
				return;
			}

			paths.forEach(function(path) {
				const pathHtml = path.path.map(step =>
					`<span class="bkx-cj-path-step">${step}</span>`
				).join('<span class="bkx-cj-path-arrow">→</span>');

				const row = `
					<tr>
						<td><div class="bkx-cj-path">${pathHtml}</div></td>
						<td>${path.count}</td>
						<td>${bkxCJ.currencySymbol}${path.revenue.toFixed(2)}</td>
					</tr>
				`;
				tbody.append(row);
			});
		},

		lookupCustomer: function() {
			const self = this;
			const email = $('#customer-email').val().trim();

			if (!email) {
				alert('Please enter a customer email');
				return;
			}

			this.ajax('bkx_cj_get_customer_profile', { email: email }, function(data) {
				if (!data.profile) {
					alert('Customer not found');
					return;
				}

				$('#no-customer').hide();
				$('#customer-profile').show();

				// Update profile info
				$('#customer-email-display').text(data.profile.email);
				$('#customer-stage-badge')
					.text(data.profile.stage_label)
					.css('background-color', data.profile.stage_color);

				// Update metrics
				$('#customer-bookings').text(data.profile.total_bookings);
				$('#customer-revenue').text(bkxCJ.currencySymbol + data.profile.total_revenue.toFixed(2));
				$('#customer-ltv').text(bkxCJ.currencySymbol + data.profile.ltv_score.toFixed(2));
				$('#customer-churn-risk').text((data.profile.churn_risk * 100).toFixed(0) + '%');

				// Render timeline
				self.renderCustomerTimeline(data.journey);

				// Render bookings
				self.renderCustomerBookings(data.profile.bookings);
			});
		},

		renderCustomerTimeline: function(journeys) {
			const container = $('#customer-timeline');
			container.empty();

			if (!journeys || journeys.length === 0) {
				container.html('<p>' + bkxCJ.i18n.noData + '</p>');
				return;
			}

			journeys.forEach(function(journey) {
				const isConversion = journey.outcome === 'converted';

				journey.touchpoints.forEach(function(tp, index) {
					const html = `
						<div class="bkx-cj-timeline-item ${isConversion && index === journey.touchpoints.length - 1 ? 'conversion' : ''}">
							<div class="bkx-cj-timeline-date">${tp.timestamp}</div>
							<div class="bkx-cj-timeline-title">${tp.type}</div>
							<div class="bkx-cj-timeline-details">${tp.page || ''}</div>
						</div>
					`;
					container.append(html);
				});
			});
		},

		renderCustomerBookings: function(bookings) {
			const tbody = $('#customer-bookings-table');
			tbody.empty();

			if (!bookings || bookings.length === 0) {
				tbody.html('<tr><td colspan="4">' + bkxCJ.i18n.noData + '</td></tr>');
				return;
			}

			bookings.forEach(function(booking) {
				const row = `
					<tr>
						<td>#${booking.id}</td>
						<td>${booking.date || '-'}</td>
						<td>${booking.status}</td>
						<td>${bkxCJ.currencySymbol}${booking.amount.toFixed(2)}</td>
					</tr>
				`;
				tbody.append(row);
			});
		},

		ajax: function(action, data, callback) {
			data = data || {};
			data.action = action;
			data.nonce = bkxCJ.nonce;
			data.start_date = this.dateRange.start;
			data.end_date = this.dateRange.end;

			$.post(bkxCJ.ajaxUrl, data, function(response) {
				if (response.success && callback) {
					callback(response.data);
				}
			});
		}
	};

	$(document).ready(function() {
		BKX_CJ.init();
	});

})(jQuery);
