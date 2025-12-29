/**
 * WhatsApp Business Admin JavaScript.
 *
 * @package BookingX\WhatsAppBusiness
 * @since   1.0.0
 */

(function($) {
	'use strict';

	var currentPhone = null;
	var pollingInterval = null;

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		initConversations();
		initTemplates();
		initQuickReplies();
		initSettings();
		initMetaBox();
	});

	/**
	 * Initialize conversations tab.
	 */
	function initConversations() {
		if ($('#bkx-conversations-list').length === 0) {
			return;
		}

		loadConversations();

		// Filter change.
		$('#bkx-conversation-status-filter').on('change', loadConversations);

		// Search.
		var searchTimeout;
		$('#bkx-conversation-search').on('keyup', function() {
			clearTimeout(searchTimeout);
			searchTimeout = setTimeout(loadConversations, 300);
		});

		// Conversation click.
		$(document).on('click', '.bkx-conversation-item', function() {
			var phone = $(this).data('phone');
			var name = $(this).data('name');
			selectConversation(phone, name);
		});

		// Send message.
		$('#bkx-send-message').on('click', sendMessage);
		$('#bkx-message-input').on('keydown', function(e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				sendMessage();
			}
		});

		// Quick replies.
		$('#bkx-quick-replies-btn').on('click', toggleQuickRepliesDropdown);
		$(document).on('click', '.bkx-quick-reply-item', function() {
			var content = $(this).data('content');
			$('#bkx-message-input').val(content);
			$('#bkx-quick-replies-dropdown').hide();
		});

		// Archive conversation.
		$('#bkx-archive-conversation').on('click', archiveConversation);
	}

	/**
	 * Load conversations.
	 */
	function loadConversations() {
		var status = $('#bkx-conversation-status-filter').val();
		var search = $('#bkx-conversation-search').val();

		$.ajax({
			url: bkxWhatsAppData.ajax_url,
			type: 'GET',
			data: {
				action: 'bkx_whatsapp_get_conversations',
				nonce: bkxWhatsAppData.nonce,
				status: status,
				search: search
			},
			success: function(response) {
				if (response.success) {
					renderConversations(response.data.conversations);
				}
			}
		});
	}

	/**
	 * Render conversations list.
	 */
	function renderConversations(conversations) {
		var $list = $('#bkx-conversations-list');
		$list.empty();

		if (!conversations.length) {
			$list.html('<p class="bkx-loading">No conversations found.</p>');
			return;
		}

		conversations.forEach(function(conv) {
			var activeClass = conv.phone_number === currentPhone ? 'active' : '';
			var unreadBadge = conv.unread_count > 0
				? '<span class="unread-badge">' + conv.unread_count + '</span>'
				: '';

			var item = '<div class="bkx-conversation-item ' + activeClass + '" data-phone="' + conv.phone_number + '" data-name="' + (conv.customer_name || conv.phone_number) + '">';
			item += '<div class="contact-name">' + (conv.customer_name || conv.phone_number) + '</div>';
			item += '<div class="last-message">' + (conv.last_message || 'No messages') + '</div>';
			item += '<div class="meta">';
			item += '<span>' + formatTime(conv.last_message_at) + '</span>';
			item += unreadBadge;
			item += '</div>';
			item += '</div>';

			$list.append(item);
		});
	}

	/**
	 * Select a conversation.
	 */
	function selectConversation(phone, name) {
		currentPhone = phone;

		// Update UI.
		$('.bkx-conversation-item').removeClass('active');
		$('.bkx-conversation-item[data-phone="' + phone + '"]').addClass('active');

		$('#bkx-chat-placeholder').hide();
		$('#bkx-chat-view').show();
		$('#bkx-chat-contact-name').text(name);
		$('#bkx-chat-contact-phone').text(phone);

		loadMessages(phone);
		loadQuickRepliesDropdown();

		// Start polling for new messages.
		clearInterval(pollingInterval);
		pollingInterval = setInterval(function() {
			loadMessages(phone, true);
		}, 10000);
	}

	/**
	 * Load messages for a conversation.
	 */
	function loadMessages(phone, silent) {
		if (!silent) {
			$('#bkx-chat-messages').html('<p class="bkx-loading">Loading messages...</p>');
		}

		$.ajax({
			url: bkxWhatsAppData.ajax_url,
			type: 'GET',
			data: {
				action: 'bkx_whatsapp_get_messages',
				nonce: bkxWhatsAppData.nonce,
				phone: phone
			},
			success: function(response) {
				if (response.success) {
					renderMessages(response.data);
				}
			}
		});
	}

	/**
	 * Render messages.
	 */
	function renderMessages(messages) {
		var $container = $('#bkx-chat-messages');
		$container.empty();

		if (!messages.length) {
			$container.html('<p class="bkx-loading">No messages yet.</p>');
			return;
		}

		messages.forEach(function(msg) {
			var statusHtml = '';
			if (msg.direction === 'outbound') {
				statusHtml = '<span class="message-status ' + msg.status + '"></span>';
			}

			var message = '<div class="bkx-message ' + msg.direction + '">';
			message += '<div class="message-content">' + escapeHtml(msg.content) + '</div>';
			message += '<div class="message-time">' + formatTime(msg.created_at) + statusHtml + '</div>';
			message += '</div>';

			$container.append(message);
		});

		// Scroll to bottom.
		$container.scrollTop($container[0].scrollHeight);
	}

	/**
	 * Send a message.
	 */
	function sendMessage() {
		var message = $('#bkx-message-input').val().trim();

		if (!message || !currentPhone) {
			return;
		}

		var $button = $('#bkx-send-message');
		$button.prop('disabled', true);

		$.ajax({
			url: bkxWhatsAppData.ajax_url,
			type: 'POST',
			data: {
				action: 'bkx_whatsapp_send_message',
				nonce: bkxWhatsAppData.nonce,
				phone: currentPhone,
				message: message
			},
			success: function(response) {
				$button.prop('disabled', false);

				if (response.success) {
					$('#bkx-message-input').val('');
					loadMessages(currentPhone);
				} else {
					alert(response.data || bkxWhatsAppData.i18n.failed);
				}
			},
			error: function() {
				$button.prop('disabled', false);
				alert(bkxWhatsAppData.i18n.failed);
			}
		});
	}

	/**
	 * Toggle quick replies dropdown.
	 */
	function toggleQuickRepliesDropdown() {
		$('#bkx-quick-replies-dropdown').toggle();
	}

	/**
	 * Load quick replies dropdown.
	 */
	function loadQuickRepliesDropdown() {
		// This would normally fetch from server.
		// For now, we'll populate from a static source if available.
	}

	/**
	 * Archive conversation.
	 */
	function archiveConversation() {
		if (!currentPhone) {
			return;
		}

		// Implementation would call AJAX to archive.
		loadConversations();
		$('#bkx-chat-view').hide();
		$('.bkx-chat-placeholder').show();
		currentPhone = null;
	}

	/**
	 * Initialize templates tab.
	 */
	function initTemplates() {
		// Sync templates.
		$('#bkx-sync-templates').on('click', function() {
			var $button = $(this);
			$button.prop('disabled', true).find('.dashicons').addClass('spin');

			$.ajax({
				url: bkxWhatsAppData.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_whatsapp_sync_templates',
					nonce: bkxWhatsAppData.nonce
				},
				success: function(response) {
					$button.prop('disabled', false).find('.dashicons').removeClass('spin');

					if (response.success) {
						location.reload();
					} else {
						alert(response.data || 'Failed to sync templates.');
					}
				},
				error: function() {
					$button.prop('disabled', false).find('.dashicons').removeClass('spin');
					alert('Failed to sync templates.');
				}
			});
		});

		// Preview template.
		$(document).on('click', '.bkx-preview-template', function(e) {
			e.preventDefault();
			// Show preview modal.
			$('#bkx-template-preview-modal').show();
		});

		// Test template.
		$(document).on('click', '.bkx-test-template', function() {
			var name = $(this).data('name');
			$('#bkx-test-template-name').val(name);
			$('#bkx-test-template-modal').show();
		});

		// Close modals.
		$('.bkx-modal-close').on('click', function() {
			$(this).closest('.bkx-modal').hide();
		});
	}

	/**
	 * Initialize quick replies tab.
	 */
	function initQuickReplies() {
		// Add quick reply.
		$('#bkx-add-quick-reply').on('click', function() {
			$('#bkx-quick-reply-modal-title').text('Add Quick Reply');
			$('#bkx-quick-reply-form')[0].reset();
			$('#bkx-quick-reply-id').val('');
			$('#bkx-quick-reply-modal').show();
		});

		// Edit quick reply.
		$(document).on('click', '.bkx-edit-quick-reply', function() {
			var id = $(this).data('id');
			var $row = $(this).closest('tr');

			$('#bkx-quick-reply-modal-title').text('Edit Quick Reply');
			$('#bkx-quick-reply-id').val(id);
			$('#bkx-quick-reply-shortcut').val($row.find('code').text().replace('/', ''));
			$('#bkx-quick-reply-title').val($row.find('td:eq(1)').text());
			// Content would need to be fetched or stored as data attribute.
			$('#bkx-quick-reply-category').val($row.find('td:eq(3)').text());

			$('#bkx-quick-reply-modal').show();
		});

		// Save quick reply.
		$('#bkx-quick-reply-form').on('submit', function(e) {
			e.preventDefault();

			$.ajax({
				url: bkxWhatsAppData.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_whatsapp_save_quick_reply',
					nonce: bkxWhatsAppData.nonce,
					id: $('#bkx-quick-reply-id').val(),
					shortcut: $('#bkx-quick-reply-shortcut').val(),
					title: $('#bkx-quick-reply-title').val(),
					content: $('#bkx-quick-reply-content').val(),
					category: $('#bkx-quick-reply-category').val()
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data || 'Failed to save.');
					}
				}
			});
		});

		// Delete quick reply.
		$(document).on('click', '.bkx-delete-quick-reply', function() {
			if (!confirm(bkxWhatsAppData.i18n.confirm_delete)) {
				return;
			}

			var id = $(this).data('id');

			$.ajax({
				url: bkxWhatsAppData.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_whatsapp_delete_quick_reply',
					nonce: bkxWhatsAppData.nonce,
					id: id
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data || 'Failed to delete.');
					}
				}
			});
		});

		// Close modal.
		$('.bkx-modal-close').on('click', function() {
			$(this).closest('.bkx-modal').hide();
		});
	}

	/**
	 * Initialize settings tab.
	 */
	function initSettings() {
		// Provider toggle.
		$('#bkx-api-provider').on('change', function() {
			var provider = $(this).val();

			$('.bkx-provider-settings').hide();

			if (provider === 'cloud_api') {
				$('#bkx-cloud-api-settings').show();
			} else if (provider === 'twilio') {
				$('#bkx-twilio-settings').show();
			} else if (provider === '360dialog') {
				$('#bkx-360dialog-settings').show();
			}
		}).trigger('change');

		// Copy webhook URL.
		$('#bkx-copy-webhook').on('click', function() {
			var url = $('#bkx-webhook-url').text();
			navigator.clipboard.writeText(url).then(function() {
				alert('Webhook URL copied!');
			});
		});

		// Test connection.
		$('#bkx-test-connection').on('click', function() {
			var $button = $(this);
			var $status = $('#bkx-connection-status');

			$button.prop('disabled', true);
			$status.text('Testing...');

			$.ajax({
				url: bkxWhatsAppData.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_whatsapp_test_connection',
					nonce: bkxWhatsAppData.nonce
				},
				success: function(response) {
					$button.prop('disabled', false);

					if (response.success) {
						$status.html('<span style="color: green;">✓ ' + response.data + '</span>');
					} else {
						$status.html('<span style="color: red;">✗ ' + response.data + '</span>');
					}
				},
				error: function() {
					$button.prop('disabled', false);
					$status.html('<span style="color: red;">✗ Connection failed</span>');
				}
			});
		});

		// Save settings.
		$('#bkx-whatsapp-settings-form').on('submit', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $button = $form.find('input[type="submit"]');

			$button.prop('disabled', true).val('Saving...');

			$.ajax({
				url: bkxWhatsAppData.ajax_url,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_whatsapp_save_settings&nonce=' + bkxWhatsAppData.nonce,
				success: function(response) {
					$button.prop('disabled', false).val('Save Settings');

					if (response.success) {
						var notice = '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
						$form.before(notice);
						setTimeout(function() {
							$('.notice-success').fadeOut();
						}, 3000);
					} else {
						alert(response.data || 'Failed to save settings.');
					}
				},
				error: function() {
					$button.prop('disabled', false).val('Save Settings');
					alert('Failed to save settings.');
				}
			});
		});
	}

	/**
	 * Initialize meta box.
	 */
	function initMetaBox() {
		$('#bkx-metabox-send').on('click', function() {
			var phone = $(this).data('phone');
			var message = $('#bkx-metabox-message').val().trim();

			if (!message) {
				return;
			}

			var $button = $(this);
			$button.prop('disabled', true);

			$.ajax({
				url: bkxWhatsAppData.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_whatsapp_send_message',
					nonce: bkxWhatsAppData.nonce,
					phone: phone,
					message: message
				},
				success: function(response) {
					$button.prop('disabled', false);

					if (response.success) {
						$('#bkx-metabox-message').val('');
						location.reload();
					} else {
						alert(response.data || 'Failed to send message.');
					}
				},
				error: function() {
					$button.prop('disabled', false);
					alert('Failed to send message.');
				}
			});
		});
	}

	/**
	 * Format time.
	 */
	function formatTime(dateString) {
		if (!dateString) {
			return '';
		}

		var date = new Date(dateString);
		var now = new Date();
		var diff = now - date;

		// Today.
		if (diff < 24 * 60 * 60 * 1000 && date.getDate() === now.getDate()) {
			return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		}

		// This week.
		if (diff < 7 * 24 * 60 * 60 * 1000) {
			return date.toLocaleDateString([], { weekday: 'short' });
		}

		// Older.
		return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
	}

	/**
	 * Escape HTML.
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

})(jQuery);
