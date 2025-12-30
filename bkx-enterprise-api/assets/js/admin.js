/**
 * Enterprise API Admin JavaScript.
 *
 * @package BookingX\EnterpriseAPI
 */

(function($) {
	'use strict';

	var BkxEnterpriseAPI = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Copy buttons.
			$(document).on('click', '.bkx-copy-btn', this.handleCopy);

			// Modal close on outside click.
			$(document).on('click', '.bkx-modal', function(e) {
				if ($(e.target).hasClass('bkx-modal')) {
					$(this).hide();
				}
			});

			// Escape key closes modals.
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					$('.bkx-modal').hide();
				}
			});
		},

		/**
		 * Handle copy to clipboard.
		 *
		 * @param {Event} e Click event.
		 */
		handleCopy: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var text = $btn.data('copy') || $('#' + $btn.data('target')).text();

			navigator.clipboard.writeText(text).then(function() {
				var originalHtml = $btn.html();
				$btn.html('<span class="dashicons dashicons-yes"></span>');
				setTimeout(function() {
					$btn.html(originalHtml);
				}, 2000);
			}).catch(function(err) {
				console.error('Failed to copy:', err);
			});
		},

		/**
		 * Show notification.
		 *
		 * @param {string} message Message to show.
		 * @param {string} type    Type: success, error, warning.
		 */
		showNotice: function(message, type) {
			var $notice = $('<div class="notice notice-' + (type || 'success') + ' is-dismissible"><p>' + message + '</p></div>');
			$('.bkx-enterprise-api h1').after($notice);

			// Auto dismiss.
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Format number with commas.
		 *
		 * @param {number} num Number to format.
		 * @return {string} Formatted number.
		 */
		formatNumber: function(num) {
			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		},

		/**
		 * Format bytes to human readable.
		 *
		 * @param {number} bytes Bytes.
		 * @return {string} Formatted size.
		 */
		formatBytes: function(bytes) {
			if (bytes === 0) return '0 Bytes';
			var k = 1024;
			var sizes = ['Bytes', 'KB', 'MB', 'GB'];
			var i = Math.floor(Math.log(bytes) / Math.log(k));
			return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
		},

		/**
		 * Format duration.
		 *
		 * @param {number} ms Milliseconds.
		 * @return {string} Formatted duration.
		 */
		formatDuration: function(ms) {
			if (ms < 1000) {
				return ms + 'ms';
			}
			return (ms / 1000).toFixed(2) + 's';
		},

		/**
		 * Refresh stats.
		 *
		 * @param {string} period Period (24h, 7d, 30d).
		 */
		refreshStats: function(period) {
			wp.apiFetch({
				path: '/bookingx/v1/stats?period=' + (period || '7d')
			}).then(function(response) {
				// Update stats UI.
				console.log('Stats:', response);
			}).catch(function(error) {
				console.error('Error fetching stats:', error);
			});
		},

		/**
		 * Test API endpoint.
		 *
		 * @param {string} endpoint Endpoint to test.
		 * @param {string} method   HTTP method.
		 * @param {Object} data     Request data.
		 * @return {Promise} API response.
		 */
		testEndpoint: function(endpoint, method, data) {
			return wp.apiFetch({
				path: '/bookingx/v1/' + endpoint,
				method: method || 'GET',
				data: data || {}
			});
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		BkxEnterpriseAPI.init();
	});

	// Expose to global scope.
	window.BkxEnterpriseAPI = BkxEnterpriseAPI;

})(jQuery);
