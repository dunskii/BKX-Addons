/**
 * Facebook Booking Admin JavaScript
 *
 * @package BookingX\FacebookBooking
 */

(function($) {
	'use strict';

	var BkxFbAdmin = {
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
			// Settings form
			$('#bkx-fb-settings-form').on('submit', this.saveSettings.bind(this));

			// Toggle password visibility
			$('.bkx-toggle-password').on('click', this.togglePassword.bind(this));

			// Generate token
			$('#bkx-generate-token').on('click', this.generateToken.bind(this));

			// Copy to clipboard
			$('.bkx-copy-btn').on('click', this.copyToClipboard.bind(this));

			// Connect page
			$('#bkx-connect-page').on('click', this.connectPage.bind(this));

			// Disconnect page
			$(document).on('click', '.bkx-disconnect-page', this.disconnectPage.bind(this));

			// Sync page
			$(document).on('click', '.bkx-sync-page', this.syncPage.bind(this));

			// Manage services
			$(document).on('click', '.bkx-manage-services', this.openServicesModal.bind(this));

			// Close modal
			$(document).on('click', '.bkx-modal-close', this.closeModal.bind(this));
			$(document).on('click', '.bkx-modal', function(e) {
				if ($(e.target).hasClass('bkx-modal')) {
					BkxFbAdmin.closeModal();
				}
			});

			// Cancel booking
			$(document).on('click', '.bkx-cancel-booking', this.cancelBooking.bind(this));

			// Export CSV
			$('#bkx-export-csv').on('click', this.exportCsv.bind(this));

			// Clear logs
			$('#bkx-clear-logs').on('click', this.clearLogs.bind(this));
		},

		/**
		 * Save settings.
		 *
		 * @param {Event} e
		 */
		saveSettings: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var $button = $form.find('[type="submit"]');

			$button.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: bkxFb.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_fb_save_settings',
				success: function(response) {
					if (response.success) {
						BkxFbAdmin.showToast(bkxFb.i18n.saved, 'success');
					} else {
						BkxFbAdmin.showToast(response.data.message || bkxFb.i18n.error, 'error');
					}
				},
				error: function() {
					BkxFbAdmin.showToast(bkxFb.i18n.networkError, 'error');
				},
				complete: function() {
					$button.prop('disabled', false).removeClass('updating-message');
				}
			});
		},

		/**
		 * Toggle password visibility.
		 *
		 * @param {Event} e
		 */
		togglePassword: function(e) {
			var $button = $(e.target);
			var $input = $button.prev('input');
			var type = $input.attr('type');

			if (type === 'password') {
				$input.attr('type', 'text');
				$button.text(bkxFb.i18n.hide || 'Hide');
			} else {
				$input.attr('type', 'password');
				$button.text(bkxFb.i18n.show || 'Show');
			}
		},

		/**
		 * Generate verify token.
		 */
		generateToken: function() {
			var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
			var token = '';

			for (var i = 0; i < 32; i++) {
				token += chars.charAt(Math.floor(Math.random() * chars.length));
			}

			$('#verify_token').val(token);
		},

		/**
		 * Copy to clipboard.
		 *
		 * @param {Event} e
		 */
		copyToClipboard: function(e) {
			var $button = $(e.target);
			var targetId = $button.data('copy');
			var $target = $('#' + targetId);
			var text = $target.text();

			navigator.clipboard.writeText(text).then(function() {
				var originalText = $button.text();
				$button.text(bkxFb.i18n.copied || 'Copied!');

				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			}).catch(function() {
				// Fallback for older browsers
				var textarea = document.createElement('textarea');
				textarea.value = text;
				document.body.appendChild(textarea);
				textarea.select();
				document.execCommand('copy');
				document.body.removeChild(textarea);

				var originalText = $button.text();
				$button.text(bkxFb.i18n.copied || 'Copied!');

				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			});
		},

		/**
		 * Connect Facebook page.
		 */
		connectPage: function() {
			// Open Facebook login dialog
			var width = 600;
			var height = 600;
			var left = (screen.width / 2) - (width / 2);
			var top = (screen.height / 2) - (height / 2);

			window.open(
				bkxFb.loginUrl,
				'fbconnect',
				'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes'
			);
		},

		/**
		 * Disconnect page.
		 *
		 * @param {Event} e
		 */
		disconnectPage: function(e) {
			var $button = $(e.target);
			var pageId = $button.data('page-id');

			if (!confirm(bkxFb.i18n.confirmDisconnect || 'Are you sure you want to disconnect this page?')) {
				return;
			}

			$button.prop('disabled', true);

			$.ajax({
				url: bkxFb.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fb_disconnect_page',
					nonce: bkxFb.nonce,
					page_id: pageId
				},
				success: function(response) {
					if (response.success) {
						BkxFbAdmin.showToast(bkxFb.i18n.disconnected || 'Page disconnected', 'success');
						location.reload();
					} else {
						BkxFbAdmin.showToast(response.data.message || bkxFb.i18n.error, 'error');
					}
				},
				error: function() {
					BkxFbAdmin.showToast(bkxFb.i18n.networkError, 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Sync page.
		 *
		 * @param {Event} e
		 */
		syncPage: function(e) {
			var $button = $(e.target);
			var pageId = $button.data('page-id');

			$button.prop('disabled', true).text(bkxFb.i18n.syncing || 'Syncing...');

			$.ajax({
				url: bkxFb.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fb_sync_page',
					nonce: bkxFb.nonce,
					page_id: pageId
				},
				success: function(response) {
					if (response.success) {
						BkxFbAdmin.showToast(response.data.message || bkxFb.i18n.synced || 'Sync complete', 'success');
						location.reload();
					} else {
						BkxFbAdmin.showToast(response.data.message || bkxFb.i18n.error, 'error');
					}
				},
				error: function() {
					BkxFbAdmin.showToast(bkxFb.i18n.networkError, 'error');
				},
				complete: function() {
					$button.prop('disabled', false).text(bkxFb.i18n.sync || 'Sync');
				}
			});
		},

		/**
		 * Open services modal.
		 *
		 * @param {Event} e
		 */
		openServicesModal: function(e) {
			var $button = $(e.target);
			var pageId = $button.data('page-id');

			$('#bkx-services-modal').show();
			$('#bkx-services-list').html('<p>' + (bkxFb.i18n.loading || 'Loading...') + '</p>');

			// Load services
			$.ajax({
				url: bkxFb.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fb_get_services',
					nonce: bkxFb.nonce,
					page_id: pageId
				},
				success: function(response) {
					if (response.success) {
						BkxFbAdmin.renderServices(pageId, response.data);
					} else {
						$('#bkx-services-list').html('<p>' + (response.data.message || bkxFb.i18n.error) + '</p>');
					}
				},
				error: function() {
					$('#bkx-services-list').html('<p>' + bkxFb.i18n.networkError + '</p>');
				}
			});
		},

		/**
		 * Render services list.
		 *
		 * @param {string} pageId   Page ID.
		 * @param {object} data     Services data.
		 */
		renderServices: function(pageId, data) {
			var html = '';

			if (data.mapped && data.mapped.length > 0) {
				html += '<h4>' + (bkxFb.i18n.mappedServices || 'Mapped Services') + '</h4>';

				data.mapped.forEach(function(service) {
					html += '<div class="service-item">';
					html += '<div class="service-info">';
					html += '<div class="service-name">' + service.name + '</div>';
					html += '<div class="service-meta">' + service.duration + ' min' + (service.price ? ' - $' + service.price : '') + '</div>';
					html += '</div>';
					html += '<button type="button" class="button button-small bkx-unmap-service" ';
					html += 'data-page-id="' + pageId + '" data-service-id="' + service.bkx_service_id + '">';
					html += (bkxFb.i18n.remove || 'Remove');
					html += '</button>';
					html += '</div>';
				});
			}

			if (data.available && data.available.length > 0) {
				html += '<h4 style="margin-top: 20px;">' + (bkxFb.i18n.availableServices || 'Available Services') + '</h4>';

				data.available.forEach(function(service) {
					html += '<div class="service-item">';
					html += '<div class="service-info">';
					html += '<div class="service-name">' + service.name + '</div>';
					html += '<div class="service-meta">' + service.duration + ' min' + (service.price ? ' - $' + service.price : '') + '</div>';
					html += '</div>';
					html += '<button type="button" class="button button-small button-primary bkx-map-service" ';
					html += 'data-page-id="' + pageId + '" data-service-id="' + service.id + '">';
					html += (bkxFb.i18n.add || 'Add');
					html += '</button>';
					html += '</div>';
				});
			}

			if (!html) {
				html = '<p>' + (bkxFb.i18n.noServices || 'No services available.') + '</p>';
			}

			$('#bkx-services-list').html(html);

			// Bind service mapping events
			$('.bkx-map-service').on('click', function() {
				var $btn = $(this);
				BkxFbAdmin.mapService($btn.data('page-id'), $btn.data('service-id'), $btn);
			});

			$('.bkx-unmap-service').on('click', function() {
				var $btn = $(this);
				BkxFbAdmin.unmapService($btn.data('page-id'), $btn.data('service-id'), $btn);
			});
		},

		/**
		 * Map a service to a page.
		 *
		 * @param {string} pageId    Page ID.
		 * @param {int}    serviceId Service ID.
		 * @param {jQuery} $button   Button element.
		 */
		mapService: function(pageId, serviceId, $button) {
			$button.prop('disabled', true);

			$.ajax({
				url: bkxFb.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fb_map_service',
					nonce: bkxFb.nonce,
					page_id: pageId,
					service_id: serviceId
				},
				success: function(response) {
					if (response.success) {
						BkxFbAdmin.showToast(bkxFb.i18n.serviceMapped || 'Service added', 'success');
						// Refresh the modal
						$('.bkx-manage-services[data-page-id="' + pageId + '"]').trigger('click');
					} else {
						BkxFbAdmin.showToast(response.data.message || bkxFb.i18n.error, 'error');
					}
				},
				error: function() {
					BkxFbAdmin.showToast(bkxFb.i18n.networkError, 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Unmap a service from a page.
		 *
		 * @param {string} pageId    Page ID.
		 * @param {int}    serviceId Service ID.
		 * @param {jQuery} $button   Button element.
		 */
		unmapService: function(pageId, serviceId, $button) {
			$button.prop('disabled', true);

			$.ajax({
				url: bkxFb.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fb_unmap_service',
					nonce: bkxFb.nonce,
					page_id: pageId,
					service_id: serviceId
				},
				success: function(response) {
					if (response.success) {
						BkxFbAdmin.showToast(bkxFb.i18n.serviceUnmapped || 'Service removed', 'success');
						// Refresh the modal
						$('.bkx-manage-services[data-page-id="' + pageId + '"]').trigger('click');
					} else {
						BkxFbAdmin.showToast(response.data.message || bkxFb.i18n.error, 'error');
					}
				},
				error: function() {
					BkxFbAdmin.showToast(bkxFb.i18n.networkError, 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Close modal.
		 */
		closeModal: function() {
			$('.bkx-modal').hide();
		},

		/**
		 * Cancel booking.
		 *
		 * @param {Event} e
		 */
		cancelBooking: function(e) {
			var $button = $(e.target);
			var bookingId = $button.data('booking-id');

			if (!confirm(bkxFb.i18n.confirmCancel || 'Are you sure you want to cancel this booking?')) {
				return;
			}

			$button.prop('disabled', true);

			$.ajax({
				url: bkxFb.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fb_cancel_booking',
					nonce: bkxFb.nonce,
					booking_id: bookingId
				},
				success: function(response) {
					if (response.success) {
						BkxFbAdmin.showToast(bkxFb.i18n.bookingCancelled || 'Booking cancelled', 'success');
						location.reload();
					} else {
						BkxFbAdmin.showToast(response.data.message || bkxFb.i18n.error, 'error');
					}
				},
				error: function() {
					BkxFbAdmin.showToast(bkxFb.i18n.networkError, 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Export bookings as CSV.
		 */
		exportCsv: function() {
			window.location.href = bkxFb.ajaxUrl + '?action=bkx_fb_export_csv&nonce=' + bkxFb.nonce;
		},

		/**
		 * Clear old logs.
		 */
		clearLogs: function() {
			if (!confirm(bkxFb.i18n.confirmClearLogs || 'Are you sure you want to clear old logs?')) {
				return;
			}

			$.ajax({
				url: bkxFb.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_fb_clear_logs',
					nonce: bkxFb.nonce
				},
				success: function(response) {
					if (response.success) {
						BkxFbAdmin.showToast(response.data.message || bkxFb.i18n.logsCleared || 'Logs cleared', 'success');
						location.reload();
					} else {
						BkxFbAdmin.showToast(response.data.message || bkxFb.i18n.error, 'error');
					}
				},
				error: function() {
					BkxFbAdmin.showToast(bkxFb.i18n.networkError, 'error');
				}
			});
		},

		/**
		 * Show toast notification.
		 *
		 * @param {string} message
		 * @param {string} type
		 */
		showToast: function(message, type) {
			var $toast = $('<div class="bkx-toast ' + type + '">' + message + '</div>');
			$('body').append($toast);

			setTimeout(function() {
				$toast.fadeOut(300, function() {
					$(this).remove();
				});
			}, 3000);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		BkxFbAdmin.init();
	});

	// Handle OAuth callback
	window.bkxFbOAuthCallback = function(success, message) {
		if (success) {
			BkxFbAdmin.showToast(bkxFb.i18n.pageConnected || 'Page connected successfully', 'success');
			location.reload();
		} else {
			BkxFbAdmin.showToast(message || bkxFb.i18n.error, 'error');
		}
	};

})(jQuery);
