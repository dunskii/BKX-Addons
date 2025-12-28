/**
 * BookingX Marketing ROI - Admin JavaScript
 *
 * @package BookingX\MarketingROI
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKX_ROI = {
		charts: {},
		currentTab: 'dashboard',
		dateRange: {
			start: '',
			end: ''
		},

		init: function() {
			this.initDateRange();
			this.bindEvents();
			this.loadTab('dashboard');
		},

		initDateRange: function() {
			const end = new Date();
			const start = new Date();
			start.setDate(start.getDate() - 30);

			$('#bkx-roi-start-date').val(this.formatDate(start));
			$('#bkx-roi-end-date').val(this.formatDate(end));

			this.dateRange.start = this.formatDate(start);
			this.dateRange.end = this.formatDate(end);
		},

		formatDate: function(date) {
			return date.toISOString().split('T')[0];
		},

		formatCurrency: function(amount) {
			return bkxROI.currencySymbol + parseFloat(amount).toFixed(2);
		},

		bindEvents: function() {
			const self = this;

			// Tab switching
			$('.bkx-roi-tabs .nav-tab').on('click', function(e) {
				e.preventDefault();
				const tab = $(this).data('tab');
				self.switchTab(tab);
			});

			// Date filter
			$('#bkx-roi-apply-filter').on('click', function() {
				self.dateRange.start = $('#bkx-roi-start-date').val();
				self.dateRange.end = $('#bkx-roi-end-date').val();
				self.loadTab(self.currentTab);
			});

			// Quick date ranges
			$('.bkx-roi-range-btn').on('click', function() {
				const days = parseInt($(this).data('range'));
				const end = new Date();
				const start = new Date();
				start.setDate(start.getDate() - days);

				$('#bkx-roi-start-date').val(self.formatDate(start));
				$('#bkx-roi-end-date').val(self.formatDate(end));

				$('.bkx-roi-range-btn').removeClass('active');
				$(this).addClass('active');

				self.dateRange.start = self.formatDate(start);
				self.dateRange.end = self.formatDate(end);
				self.loadTab(self.currentTab);
			});

			// Campaign modal
			$('#add-campaign-btn').on('click', function() {
				self.openCampaignModal();
			});

			$('.bkx-roi-modal-close, .bkx-roi-modal-cancel').on('click', function() {
				$(this).closest('.bkx-roi-modal').hide();
			});

			$('#save-campaign-btn').on('click', function() {
				self.saveCampaign();
			});

			$('#save-cost-btn').on('click', function() {
				self.saveCost();
			});

			// UTM group by change
			$('#utm-group-by').on('change', function() {
				self.loadUTMReport();
			});

			// Export
			$('#export-utm-report').on('click', function() {
				self.exportReport();
			});
		},

		switchTab: function(tab) {
			$('.bkx-roi-tabs .nav-tab').removeClass('nav-tab-active');
			$('.bkx-roi-tabs .nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active');

			$('.bkx-roi-tab-content').removeClass('active');
			$('#' + tab).addClass('active');

			this.currentTab = tab;
			this.loadTab(tab);
		},

		loadTab: function(tab) {
			switch (tab) {
				case 'dashboard':
					this.loadDashboard();
					break;
				case 'campaigns':
					this.loadCampaigns();
					break;
				case 'utm-reports':
					this.loadUTMReport();
					break;
			}
		},

		loadDashboard: function() {
			const self = this;

			this.ajax('bkx_roi_get_dashboard', {}, function(data) {
				// Update summary cards
				$('#total-visits').text(data.summary.total_visits);
				$('#conversions').text(data.summary.conversions);
				$('#conversion-rate').text(data.summary.conversion_rate + '%');
				$('#total-revenue').text(self.formatCurrency(data.summary.total_revenue));

				// ROI cards
				const roiClass = data.summary.roi >= 0 ? 'roi-positive' : 'roi-negative';
				$('#roi-percent').html('<span class="' + roiClass + '">' + data.summary.roi + '%</span>');
				$('#total-cost').text(self.formatCurrency(data.summary.total_cost));
				$('#roas').text(data.summary.roas.toFixed(2) + 'x');
				$('#cpa').text(self.formatCurrency(data.summary.cpa));

				// Render charts
				self.renderSourceChart(data.by_source);
				self.renderMediumChart(data.by_medium);
				self.renderDailyTrends(data.daily_trends);

				// Render campaigns table
				self.renderCampaignsTable(data.campaigns);
			});
		},

		renderSourceChart: function(data) {
			const ctx = document.getElementById('source-chart');
			if (!ctx) return;

			if (this.charts.source) {
				this.charts.source.destroy();
			}

			const colors = ['#2271b1', '#00a32a', '#dba617', '#d63638', '#72aee6', '#8B5CF6'];

			this.charts.source = new Chart(ctx, {
				type: 'doughnut',
				data: {
					labels: data.map(d => d.utm_value || 'Unknown'),
					datasets: [{
						data: data.map(d => d.visits),
						backgroundColor: colors.slice(0, data.length)
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

		renderMediumChart: function(data) {
			const ctx = document.getElementById('medium-chart');
			if (!ctx) return;

			if (this.charts.medium) {
				this.charts.medium.destroy();
			}

			this.charts.medium = new Chart(ctx, {
				type: 'bar',
				data: {
					labels: data.map(d => d.utm_value || 'Unknown'),
					datasets: [
						{
							label: 'Visits',
							data: data.map(d => d.visits),
							backgroundColor: '#2271b1'
						},
						{
							label: 'Conversions',
							data: data.map(d => d.conversions),
							backgroundColor: '#00a32a'
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { position: 'top' }
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
							label: 'Visits',
							data: trends.map(t => t.visits),
							borderColor: '#2271b1',
							backgroundColor: 'rgba(34, 113, 177, 0.1)',
							fill: true,
							yAxisID: 'y'
						},
						{
							label: 'Revenue',
							data: trends.map(t => t.revenue),
							borderColor: '#00a32a',
							backgroundColor: 'transparent',
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
							grid: { drawOnChartArea: false },
							ticks: {
								callback: v => bkxROI.currencySymbol + v
							}
						}
					}
				}
			});
		},

		renderCampaignsTable: function(campaigns) {
			const self = this;
			const tbody = $('#campaigns-table');
			tbody.empty();

			if (!campaigns || campaigns.length === 0) {
				tbody.html('<tr><td colspan="8">' + bkxROI.i18n.noData + '</td></tr>');
				return;
			}

			campaigns.forEach(function(campaign) {
				const roiClass = campaign.roi >= 0 ? 'roi-positive' : 'roi-negative';
				const row = `
					<tr>
						<td><strong>${campaign.campaign_name}</strong></td>
						<td>${campaign.visits}</td>
						<td>${campaign.conversions}</td>
						<td>${campaign.conversion_rate}%</td>
						<td>${self.formatCurrency(campaign.revenue)}</td>
						<td>${self.formatCurrency(campaign.cost)}</td>
						<td><span class="${roiClass}">${campaign.roi}%</span></td>
						<td>${campaign.roas.toFixed(2)}x</td>
					</tr>
				`;
				tbody.append(row);
			});
		},

		loadCampaigns: function() {
			const self = this;

			this.ajax('bkx_roi_get_campaigns', {}, function(campaigns) {
				self.renderCampaignsList(campaigns);
			});
		},

		renderCampaignsList: function(campaigns) {
			const self = this;
			const container = $('#campaigns-list');
			container.empty();

			if (!campaigns || campaigns.length === 0) {
				container.html('<p>' + bkxROI.i18n.noData + '</p>');
				return;
			}

			campaigns.forEach(function(campaign) {
				const utmTags = [];
				if (campaign.utm_source) utmTags.push('source: ' + campaign.utm_source);
				if (campaign.utm_medium) utmTags.push('medium: ' + campaign.utm_medium);
				if (campaign.utm_campaign) utmTags.push('campaign: ' + campaign.utm_campaign);

				const html = `
					<div class="bkx-roi-campaign-card" data-id="${campaign.id}">
						<div class="bkx-roi-campaign-card-header">
							<h3 class="bkx-roi-campaign-title">${campaign.campaign_name}</h3>
							<span class="bkx-roi-campaign-status ${campaign.status}">${campaign.status}</span>
						</div>
						<div class="bkx-roi-campaign-utm">
							${utmTags.map(t => '<span class="bkx-roi-utm-tag">' + t + '</span>').join('')}
						</div>
						<div class="bkx-roi-campaign-metrics">
							<div class="bkx-roi-metric">
								<span class="bkx-roi-metric-value">${self.formatCurrency(campaign.budget)}</span>
								<span class="bkx-roi-metric-label">Budget</span>
							</div>
							<div class="bkx-roi-metric">
								<span class="bkx-roi-metric-value">${campaign.start_date || '-'}</span>
								<span class="bkx-roi-metric-label">Start</span>
							</div>
							<div class="bkx-roi-metric">
								<span class="bkx-roi-metric-value">${campaign.end_date || '-'}</span>
								<span class="bkx-roi-metric-label">End</span>
							</div>
						</div>
						<div class="bkx-roi-campaign-actions">
							<button type="button" class="button edit-campaign-btn" data-id="${campaign.id}">Edit</button>
							<button type="button" class="button add-cost-btn" data-id="${campaign.id}">Add Cost</button>
							<button type="button" class="button delete-campaign-btn" data-id="${campaign.id}">Delete</button>
						</div>
					</div>
				`;
				container.append(html);
			});

			// Bind action buttons
			$('.edit-campaign-btn').on('click', function() {
				const id = $(this).data('id');
				const campaign = campaigns.find(c => c.id === id);
				self.openCampaignModal(campaign);
			});

			$('.add-cost-btn').on('click', function() {
				const id = $(this).data('id');
				self.openCostModal(id);
			});

			$('.delete-campaign-btn').on('click', function() {
				const id = $(this).data('id');
				if (confirm(bkxROI.i18n.confirmDelete)) {
					self.deleteCampaign(id);
				}
			});
		},

		loadUTMReport: function() {
			const self = this;
			const groupBy = $('#utm-group-by').val();

			// Update header
			$('#utm-header-value').text(groupBy.charAt(0).toUpperCase() + groupBy.slice(1));

			this.ajax('bkx_roi_get_utm_report', { group_by: groupBy }, function(data) {
				self.renderUTMChart(data, groupBy);
				self.renderUTMTable(data);
			});
		},

		renderUTMChart: function(data, groupBy) {
			const ctx = document.getElementById('utm-chart');
			if (!ctx) return;

			if (this.charts.utm) {
				this.charts.utm.destroy();
			}

			this.charts.utm = new Chart(ctx, {
				type: 'bar',
				data: {
					labels: data.map(d => d.value || 'Unknown'),
					datasets: [
						{
							label: 'Visits',
							data: data.map(d => d.visits),
							backgroundColor: '#2271b1'
						},
						{
							label: 'Conversions',
							data: data.map(d => d.conversions),
							backgroundColor: '#00a32a'
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: { position: 'top' }
					}
				}
			});
		},

		renderUTMTable: function(data) {
			const self = this;
			const tbody = $('#utm-table');
			tbody.empty();

			if (!data || data.length === 0) {
				tbody.html('<tr><td colspan="5">' + bkxROI.i18n.noData + '</td></tr>');
				return;
			}

			data.forEach(function(row) {
				const html = `
					<tr>
						<td>${row.value || 'Unknown'}</td>
						<td>${row.visits}</td>
						<td>${row.conversions}</td>
						<td>${row.conversion_rate}%</td>
						<td>${self.formatCurrency(row.revenue)}</td>
					</tr>
				`;
				tbody.append(html);
			});
		},

		openCampaignModal: function(campaign) {
			const form = $('#campaign-form')[0];
			form.reset();

			if (campaign) {
				$('#campaign-modal-title').text('Edit Campaign');
				$('#campaign-id').val(campaign.id);
				$('#campaign-name').val(campaign.campaign_name);
				$('#campaign-source').val(campaign.utm_source);
				$('#campaign-medium').val(campaign.utm_medium);
				$('#campaign-utm-campaign').val(campaign.utm_campaign);
				$('#campaign-content').val(campaign.utm_content);
				$('#campaign-term').val(campaign.utm_term);
				$('#campaign-budget').val(campaign.budget);
				$('#campaign-status').val(campaign.status);
				$('#campaign-start').val(campaign.start_date);
				$('#campaign-end').val(campaign.end_date);
				$('#campaign-notes').val(campaign.notes);
			} else {
				$('#campaign-modal-title').text('Add Campaign');
			}

			$('#campaign-modal').show();
		},

		openCostModal: function(campaignId) {
			$('#cost-form')[0].reset();
			$('#cost-campaign-id').val(campaignId);
			$('#cost-date').val(this.formatDate(new Date()));
			$('#cost-modal').show();
		},

		saveCampaign: function() {
			const self = this;
			const form = $('#campaign-form');
			const data = {};

			form.serializeArray().forEach(function(item) {
				data[item.name] = item.value;
			});

			this.ajax('bkx_roi_save_campaign', data, function(response) {
				$('#campaign-modal').hide();
				self.loadCampaigns();
				alert(bkxROI.i18n.saved);
			});
		},

		saveCost: function() {
			const self = this;
			const form = $('#cost-form');
			const data = {};

			form.serializeArray().forEach(function(item) {
				data[item.name] = item.value;
			});

			this.ajax('bkx_roi_add_cost', data, function(response) {
				$('#cost-modal').hide();
				self.loadDashboard();
			});
		},

		deleteCampaign: function(id) {
			const self = this;

			this.ajax('bkx_roi_delete_campaign', { id: id }, function(response) {
				self.loadCampaigns();
				alert(bkxROI.i18n.deleted);
			});
		},

		exportReport: function() {
			const groupBy = $('#utm-group-by').val();
			const reportType = groupBy === 'source' ? 'sources' : groupBy + 's';

			this.ajax('bkx_roi_export_report', { report_type: reportType }, function(response) {
				if (response.url) {
					window.open(response.url, '_blank');
				}
			});
		},

		ajax: function(action, data, callback) {
			data = data || {};
			data.action = action;
			data.nonce = bkxROI.nonce;
			data.start_date = this.dateRange.start;
			data.end_date = this.dateRange.end;

			$.post(bkxROI.ajaxUrl, data, function(response) {
				if (response.success && callback) {
					callback(response.data);
				} else if (!response.success) {
					alert(bkxROI.i18n.error);
				}
			});
		}
	};

	$(document).ready(function() {
		BKX_ROI.init();
	});

})(jQuery);
