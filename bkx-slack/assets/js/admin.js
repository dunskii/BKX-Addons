/**
 * Slack Integration Admin JavaScript
 *
 * @package BookingX\Slack
 */

(function($) {
	'use strict';

	var BkxSlackAdmin = {
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
			// Settings form.
			$('#bkx-slack-settings-form, #bkx-slack-notifications-form').on('submit', this.saveSettings.bind(this));

			// Toggle password visibility.
			$('.bkx-toggle-password').on('click', this.togglePassword.bind(this));

			// Copy to clipboard.
			$('.bkx-copy-btn').on('click', this.copyToClipboard.bind(this));

			// Test notification.
			$(document).on('click', '.bkx-test-notification', this.testNotification.bind(this));

			// Disconnect workspace.
			$(document).on('click', '.bkx-disconnect-workspace', this.disconnectWorkspace.bind(this));

			// Manage channels.
			$(document).on('click', '.bkx-manage-channels', this.openChannelsModal.bind(this));

			// Close modal.
			$(document).on('click', '.bkx-modal-close', this.closeModal.bind(this));
			$(document).on('click', '.bkx-modal', function(e) {
				if ($(e.target).hasClass('bkx-modal')) {
					BkxSlackAdmin.closeModal();
				}
			});

			// Clear logs.
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
				url: bkxSlack.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_slack_save_settings&nonce=' + bkxSlack.nonce,
				success: function(response) {
					if (response.success) {
						BkxSlackAdmin.showToast(bkxSlack.i18n.saved, 'success');
					} else {
						BkxSlackAdmin.showToast(response.data.message || bkxSlack.i18n.error, 'error');
					}
				},
				error: function() {
					BkxSlackAdmin.showToast(bkxSlack.i18n.error, 'error');
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
				$button.text('Hide');
			} else {
				$input.attr('type', 'password');
				$button.text('Show');
			}
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
				$button.text('Copied!');

				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			}).catch(function() {
				// Fallback for older browsers.
				var textarea = document.createElement('textarea');
				textarea.value = text;
				document.body.appendChild(textarea);
				textarea.select();
				document.execCommand('copy');
				document.body.removeChild(textarea);

				var originalText = $button.text();
				$button.text('Copied!');

				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			});
		},

		/**
		 * Test notification.
		 *
		 * @param {Event} e
		 */
		testNotification: function(e) {
			var $button = $(e.target);
			var workspaceId = $button.data('workspace-id');
			var channelId = $button.data('channel-id') || '';

			$button.prop('disabled', true).text('Sending...');

			$.ajax({
				url: bkxSlack.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_slack_test_notification',
					nonce: bkxSlack.nonce,
					workspace_id: workspaceId,
					channel_id: channelId
				},
				success: function(response) {
					if (response.success) {
						BkxSlackAdmin.showToast(bkxSlack.i18n.testSent, 'success');
					} else {
						BkxSlackAdmin.showToast(response.data.message || bkxSlack.i18n.error, 'error');
					}
				},
				error: function() {
					BkxSlackAdmin.showToast(bkxSlack.i18n.error, 'error');
				},
				complete: function() {
					$button.prop('disabled', false).text('Test');
				}
			});
		},

		/**
		 * Disconnect workspace.
		 *
		 * @param {Event} e
		 */
		disconnectWorkspace: function(e) {
			var $button = $(e.target);
			var workspaceId = $button.data('workspace-id');

			if (!confirm(bkxSlack.i18n.confirmDisconnect)) {
				return;
			}

			$button.prop('disabled', true);

			$.ajax({
				url: bkxSlack.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_slack_disconnect_workspace',
					nonce: bkxSlack.nonce,
					workspace_id: workspaceId
				},
				success: function(response) {
					if (response.success) {
						BkxSlackAdmin.showToast('Workspace disconnected', 'success');
						location.reload();
					} else {
						BkxSlackAdmin.showToast(response.data.message || bkxSlack.i18n.error, 'error');
					}
				},
				error: function() {
					BkxSlackAdmin.showToast(bkxSlack.i18n.error, 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Open channels modal.
		 *
		 * @param {Event} e
		 */
		openChannelsModal: function(e) {
			var $button = $(e.target);
			var workspaceId = $button.data('workspace-id');

			$('#bkx-channels-modal').show();
			$('#bkx-channels-list').html('<p>Loading...</p>');

			// Load channels.
			$.ajax({
				url: bkxSlack.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_slack_get_channels',
					nonce: bkxSlack.nonce,
					workspace_id: workspaceId
				},
				success: function(response) {
					if (response.success) {
						BkxSlackAdmin.renderChannels(workspaceId, response.data);
					} else {
						$('#bkx-channels-list').html('<p>' + (response.data.message || bkxSlack.i18n.error) + '</p>');
					}
				},
				error: function() {
					$('#bkx-channels-list').html('<p>' + bkxSlack.i18n.error + '</p>');
				}
			});
		},

		/**
		 * Render channels list.
		 *
		 * @param {int}    workspaceId Workspace ID.
		 * @param {object} data        Channels data.
		 */
		renderChannels: function(workspaceId, data) {
			var html = '<p>Select channels to receive booking notifications:</p>';

			if (data.channels && data.channels.length > 0) {
				html += '<div class="bkx-channels-list">';

				data.channels.forEach(function(channel) {
					var isEnabled = data.enabled.indexOf(channel.id) !== -1;

					html += '<div class="bkx-channel-item">';
					html += '<label>';
					html += '<input type="checkbox" ' + (isEnabled ? 'checked' : '') + ' ';
					html += 'data-workspace-id="' + workspaceId + '" ';
					html += 'data-channel-id="' + channel.id + '" ';
					html += 'data-channel-name="' + channel.name + '" ';
					html += 'class="bkx-channel-toggle">';
					html += ' #' + channel.name;
					html += '</label>';
					html += '</div>';
				});

				html += '</div>';
			} else {
				html += '<p>No channels found. Make sure the bot has been added to at least one channel.</p>';
			}

			$('#bkx-channels-list').html(html);

			// Bind toggle events.
			$('.bkx-channel-toggle').on('change', function() {
				var $checkbox = $(this);
				var isChecked = $checkbox.is(':checked');

				$.ajax({
					url: bkxSlack.ajaxUrl,
					type: 'POST',
					data: {
						action: isChecked ? 'bkx_slack_add_channel' : 'bkx_slack_remove_channel',
						nonce: bkxSlack.nonce,
						workspace_id: $checkbox.data('workspace-id'),
						channel_id: $checkbox.data('channel-id'),
						channel_name: $checkbox.data('channel-name')
					}
				});
			});
		},

		/**
		 * Close modal.
		 */
		closeModal: function() {
			$('.bkx-modal').hide();
		},

		/**
		 * Clear logs.
		 */
		clearLogs: function() {
			if (!confirm('Are you sure you want to clear old logs (older than 30 days)?')) {
				return;
			}

			$.ajax({
				url: bkxSlack.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_slack_clear_logs',
					nonce: bkxSlack.nonce
				},
				success: function(response) {
					if (response.success) {
						BkxSlackAdmin.showToast('Logs cleared', 'success');
						location.reload();
					} else {
						BkxSlackAdmin.showToast(response.data.message || bkxSlack.i18n.error, 'error');
					}
				},
				error: function() {
					BkxSlackAdmin.showToast(bkxSlack.i18n.error, 'error');
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

	// Initialize on document ready.
	$(document).ready(function() {
		BkxSlackAdmin.init();
	});

})(jQuery);
