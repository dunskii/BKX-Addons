/**
 * Staff Analytics Admin Scripts.
 *
 * @package BookingX\StaffAnalytics
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const StaffAnalytics = {
		chart: null,

		init: function() {
			this.bindEvents();
			this.initChart();
			this.initLeaderboard();
		},

		bindEvents: function() {
			// Chart filters
			$('#bkx-chart-staff, #bkx-chart-period').on('change', this.updateChart.bind(this));

			// Leaderboard filters
			$('#bkx-lb-metric, #bkx-lb-period').on('change', this.updateLeaderboard.bind(this));

			// Goals
			$('#bkx-add-goal').on('click', this.showGoalModal.bind(this));
			$(document).on('click', '.bkx-edit-goal', this.editGoal.bind(this));
			$(document).on('click', '.bkx-delete-goal', this.deleteGoal.bind(this));
			$('#bkx-goal-form').on('submit', this.saveGoal.bind(this));

			// Reviews
			$(document).on('click', '.bkx-approve-review', this.approveReview.bind(this));
			$(document).on('click', '.bkx-reject-review', this.rejectReview.bind(this));

			// Time tracking
			$('#bkx-filter-time').on('click', this.filterTimeLogs.bind(this));

			// Export
			$('#bkx-export-form').on('submit', this.exportReport.bind(this));

			// Modal
			$('.bkx-modal-close, .bkx-modal-cancel').on('click', this.hideModal.bind(this));
			$(document).on('click', '.bkx-modal', function(e) {
				if ($(e.target).hasClass('bkx-modal')) {
					StaffAnalytics.hideModal();
				}
			});
		},

		initChart: function() {
			const canvas = document.getElementById('bkx-performance-chart');
			if (!canvas) return;

			this.chart = new Chart(canvas, {
				type: 'line',
				data: {
					labels: [],
					datasets: [
						{
							label: 'Revenue',
							data: [],
							borderColor: '#667eea',
							backgroundColor: 'rgba(102, 126, 234, 0.1)',
							tension: 0.4,
							fill: true,
							yAxisID: 'y'
						},
						{
							label: 'Bookings',
							data: [],
							borderColor: '#46b450',
							backgroundColor: 'rgba(70, 180, 80, 0.1)',
							tension: 0.4,
							fill: true,
							yAxisID: 'y1'
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						mode: 'index',
						intersect: false
					},
					scales: {
						y: {
							type: 'linear',
							display: true,
							position: 'left',
							title: {
								display: true,
								text: 'Revenue ($)'
							}
						},
						y1: {
							type: 'linear',
							display: true,
							position: 'right',
							grid: {
								drawOnChartArea: false
							},
							title: {
								display: true,
								text: 'Bookings'
							}
						}
					}
				}
			});

			this.updateChart();
		},

		updateChart: function() {
			const staffId = $('#bkx-chart-staff').val() || 0;
			const period = $('#bkx-chart-period').val() || 'month';

			$.ajax({
				url: bkxStaffAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_get_staff_metrics',
					nonce: bkxStaffAnalytics.nonce,
					staff_id: staffId,
					period: period
				},
				success: (response) => {
					if (response.success && this.chart) {
						const daily = response.data.daily || [];
						this.chart.data.labels = daily.map(d => d.metric_date);
						this.chart.data.datasets[0].data = daily.map(d => parseFloat(d.revenue) || 0);
						this.chart.data.datasets[1].data = daily.map(d => parseInt(d.bookings) || 0);
						this.chart.update();
					}
				}
			});
		},

		initLeaderboard: function() {
			this.updateLeaderboard();
		},

		updateLeaderboard: function() {
			const container = $('#bkx-leaderboard-container');
			if (!container.length) return;

			const metric = $('#bkx-lb-metric').val() || 'revenue';
			const period = $('#bkx-lb-period').val() || 'month';

			container.html('<div class="bkx-loading">' + bkxStaffAnalytics.i18n.loading + '</div>');

			$.ajax({
				url: bkxStaffAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_get_leaderboard',
					nonce: bkxStaffAnalytics.nonce,
					metric: metric,
					period: period,
					limit: 10
				},
				success: (response) => {
					if (response.success) {
						this.renderLeaderboard(container, response.data.rankings, metric);
					}
				}
			});
		},

		renderLeaderboard: function(container, rankings, metric) {
			if (!rankings.length) {
				container.html('<div class="bkx-empty">No data available</div>');
				return;
			}

			let html = '';
			rankings.forEach(staff => {
				let value = '';
				switch (metric) {
					case 'revenue':
						value = '$' + parseFloat(staff.total_revenue).toLocaleString(undefined, {minimumFractionDigits: 2});
						break;
					case 'bookings':
						value = staff.completed_bookings + ' bookings';
						break;
					case 'rating':
						value = staff.avg_rating + '/5 ★';
						break;
					case 'hours':
						value = staff.total_hours + ' hours';
						break;
					case 'new_customers':
						value = staff.new_customers + ' new';
						break;
					default:
						value = staff.total_revenue;
				}

				const avatar = staff.thumbnail || 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"%3E%3Ccircle cx="12" cy="12" r="12" fill="%23ddd"/%3E%3Ccircle cx="12" cy="8" r="4" fill="%23999"/%3E%3Cpath d="M4 20c0-4.4 3.6-8 8-8s8 3.6 8 8" fill="%23999"/%3E%3C/svg%3E';

				html += `
					<div class="bkx-leaderboard-item">
						<span class="bkx-leaderboard-rank">${staff.rank}</span>
						<img src="${avatar}" alt="" class="bkx-leaderboard-avatar">
						<div class="bkx-leaderboard-info">
							<div class="bkx-leaderboard-name">${staff.staff_name}</div>
							<div class="bkx-leaderboard-stats">
								${staff.total_bookings} bookings | ${staff.completion_rate}% completion
							</div>
						</div>
						<div class="bkx-leaderboard-value">${value}</div>
					</div>
				`;
			});

			container.html(html);
		},

		showGoalModal: function(e) {
			e.preventDefault();
			$('#bkx-goal-modal-title').text('Add Goal');
			$('#bkx-goal-form')[0].reset();
			$('#goal-id').val('');
			$('#goal-start').val(this.getToday());
			$('#goal-end').val(this.getMonthEnd());
			$('#bkx-goal-modal').show();
		},

		editGoal: function(e) {
			const id = $(e.currentTarget).data('id');
			const row = $(`tr[data-goal-id="${id}"]`);

			// Populate form with existing data
			$('#bkx-goal-modal-title').text('Edit Goal');
			$('#goal-id').val(id);
			// Would need to fetch goal data via AJAX for full edit functionality

			$('#bkx-goal-modal').show();
		},

		deleteGoal: function(e) {
			const id = $(e.currentTarget).data('id');

			if (!confirm(bkxStaffAnalytics.i18n.confirmDelete)) {
				return;
			}

			$.ajax({
				url: bkxStaffAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_delete_staff_goal',
					nonce: bkxStaffAnalytics.nonce,
					goal_id: id
				},
				success: (response) => {
					if (response.success) {
						$(`tr[data-goal-id="${id}"]`).fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || bkxStaffAnalytics.i18n.error);
					}
				}
			});
		},

		saveGoal: function(e) {
			e.preventDefault();

			const form = $(e.currentTarget);
			const data = {
				action: 'bkx_save_staff_goal',
				nonce: bkxStaffAnalytics.nonce,
				goal_id: $('#goal-id').val(),
				staff_id: $('#goal-staff').val(),
				goal_type: $('#goal-type').val(),
				target_value: $('#goal-target').val(),
				start_date: $('#goal-start').val(),
				end_date: $('#goal-end').val()
			};

			$.ajax({
				url: bkxStaffAnalytics.ajaxUrl,
				type: 'POST',
				data: data,
				success: (response) => {
					if (response.success) {
						this.hideModal();
						location.reload(); // Refresh to show updated goals
					} else {
						alert(response.data.message || bkxStaffAnalytics.i18n.error);
					}
				}
			});
		},

		approveReview: function(e) {
			const id = $(e.currentTarget).data('id');
			const row = $(e.currentTarget).closest('tr');

			$.ajax({
				url: bkxStaffAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_approve_review',
					nonce: bkxStaffAnalytics.nonce,
					review_id: id,
					approved: true
				},
				success: (response) => {
					if (response.success) {
						row.fadeOut(300, function() {
							$(this).remove();
						});
					}
				}
			});
		},

		rejectReview: function(e) {
			const id = $(e.currentTarget).data('id');
			const row = $(e.currentTarget).closest('tr');

			$.ajax({
				url: bkxStaffAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_approve_review',
					nonce: bkxStaffAnalytics.nonce,
					review_id: id,
					approved: false
				},
				success: (response) => {
					if (response.success) {
						row.fadeOut(300, function() {
							$(this).remove();
						});
					}
				}
			});
		},

		filterTimeLogs: function() {
			const staffId = $('#bkx-time-staff').val();
			const startDate = $('#bkx-time-start').val();
			const endDate = $('#bkx-time-end').val();

			// Reload page with filters
			const url = new URL(window.location.href);
			url.searchParams.set('time_staff', staffId);
			url.searchParams.set('time_start', startDate);
			url.searchParams.set('time_end', endDate);
			window.location.href = url.toString();
		},

		exportReport: function(e) {
			e.preventDefault();

			const form = $(e.currentTarget);
			const btn = form.find('button[type="submit"]');
			const originalText = btn.text();

			btn.prop('disabled', true).text('Generating...');

			$.ajax({
				url: bkxStaffAnalytics.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_export_staff_report',
					nonce: bkxStaffAnalytics.nonce,
					staff_id: $('#export-staff').val(),
					report_type: $('#export-type').val(),
					format: $('#export-format').val(),
					start_date: $('#export-start').val(),
					end_date: $('#export-end').val()
				},
				success: (response) => {
					if (response.success && response.data.download_url) {
						window.location.href = response.data.download_url;
					} else {
						alert(response.data.message || bkxStaffAnalytics.i18n.error);
					}
				},
				complete: () => {
					btn.prop('disabled', false).text(originalText);
				}
			});
		},

		hideModal: function() {
			$('.bkx-modal').hide();
		},

		getToday: function() {
			return new Date().toISOString().split('T')[0];
		},

		getMonthEnd: function() {
			const date = new Date();
			date.setMonth(date.getMonth() + 1);
			date.setDate(0);
			return date.toISOString().split('T')[0];
		}
	};

	$(document).ready(function() {
		StaffAnalytics.init();
	});

})(jQuery);
