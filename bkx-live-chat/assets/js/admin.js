/**
 * Live Chat Admin JavaScript.
 *
 * @package BookingX\LiveChat
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BkxLiveChatAdmin = {
		currentChatId: null,
		pollInterval: null,
		isPolling: false,

		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.startPolling();
			this.initCannedResponses();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Operator status.
			$(document).on('change', '#bkx-operator-status', this.updateOperatorStatus.bind(this));

			// Chat filters.
			$(document).on('click', '.bkx-filter-btn', this.filterChats.bind(this));

			// Chat selection.
			$(document).on('click', '.bkx-chat-item', this.selectChat.bind(this));

			// Send message.
			$(document).on('submit', '#bkx-chat-form', this.sendMessage.bind(this));
			$(document).on('keydown', '#bkx-chat-textarea', this.handleKeydown.bind(this));

			// Chat actions.
			$(document).on('click', '.bkx-accept-chat', this.acceptChat.bind(this));
			$(document).on('click', '.bkx-close-chat', this.closeChat.bind(this));
			$(document).on('click', '.bkx-transfer-chat', this.transferChat.bind(this));

			// Canned responses.
			$(document).on('click', '.bkx-canned-btn', this.toggleCannedDropdown.bind(this));
			$(document).on('click', '.bkx-canned-item', this.insertCannedResponse.bind(this));

			// History page.
			$(document).on('click', '.bkx-view-transcript', this.viewTranscript.bind(this));

			// Responses page.
			$(document).on('click', '#bkx-add-response', this.openResponseModal.bind(this));
			$(document).on('click', '.bkx-edit-response', this.editResponse.bind(this));
			$(document).on('click', '.bkx-delete-response', this.deleteResponse.bind(this));
			$(document).on('submit', '#bkx-response-form', this.saveResponse.bind(this));

			// Modal close.
			$(document).on('click', '.bkx-modal-close', this.closeModal.bind(this));
			$(document).on('click', '.bkx-modal', function(e) {
				if (e.target === this) {
					$(this).hide();
				}
			});

			// Operators page.
			$(document).on('submit', '#bkx-add-operator-form', this.addOperator.bind(this));
		},

		/**
		 * Start polling for updates.
		 */
		startPolling: function() {
			if (this.isPolling) {
				return;
			}

			this.isPolling = true;
			this.poll();
			this.pollInterval = setInterval(this.poll.bind(this), 5000);
		},

		/**
		 * Poll for updates.
		 */
		poll: function() {
			// Update chat list.
			this.loadChatList();

			// Update current chat messages.
			if (this.currentChatId) {
				this.loadMessages(this.currentChatId);
			}

			// Update active visitors.
			this.loadActiveVisitors();
		},

		/**
		 * Load chat list.
		 */
		loadChatList: function() {
			const filter = $('.bkx-filter-btn.active').data('filter') || 'all';

			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_get_chats',
					nonce: bkxLivechatAdmin.nonce,
					filter: filter
				},
				success: function(response) {
					if (response.success && response.data.chats) {
						BkxLiveChatAdmin.renderChatList(response.data.chats);
					}
				}
			});
		},

		/**
		 * Render chat list.
		 *
		 * @param {Array} chats
		 */
		renderChatList: function(chats) {
			const $list = $('.bkx-chat-list');
			if (!$list.length) {
				return;
			}

			if (chats.length === 0) {
				$list.html('<div class="bkx-no-chats">No active chats</div>');
				return;
			}

			let html = '';
			chats.forEach(function(chat) {
				const isActive = chat.id == BkxLiveChatAdmin.currentChatId ? ' active' : '';
				const statusClass = chat.status === 'pending' ? ' pending' : (chat.status === 'active' ? ' active-status' : '');

				html += '<div class="bkx-chat-item' + isActive + statusClass + '" data-id="' + chat.id + '">';
				html += '<div class="visitor-name">' + BkxLiveChatAdmin.escapeHtml(chat.visitor_name || 'Anonymous') + '</div>';
				html += '<div class="last-message">' + BkxLiveChatAdmin.escapeHtml(chat.last_message || 'No messages') + '</div>';
				html += '<div class="meta">';
				html += '<span>' + chat.time_ago + '</span>';
				if (chat.unread_count > 0) {
					html += '<span class="bkx-unread-count">' + chat.unread_count + '</span>';
				}
				html += '</div>';
				html += '</div>';
			});

			$list.html(html);
		},

		/**
		 * Filter chats.
		 *
		 * @param {Event} e
		 */
		filterChats: function(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);

			$('.bkx-filter-btn').removeClass('active');
			$btn.addClass('active');

			this.loadChatList();
		},

		/**
		 * Select chat.
		 *
		 * @param {Event} e
		 */
		selectChat: function(e) {
			const $item = $(e.currentTarget);
			const chatId = $item.data('id');

			$('.bkx-chat-item').removeClass('active');
			$item.addClass('active');

			this.currentChatId = chatId;
			this.loadChatView(chatId);
		},

		/**
		 * Load chat view.
		 *
		 * @param {number} chatId
		 */
		loadChatView: function(chatId) {
			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_get_chat',
					nonce: bkxLivechatAdmin.nonce,
					chat_id: chatId
				},
				success: function(response) {
					if (response.success) {
						BkxLiveChatAdmin.renderChatView(response.data);
					}
				}
			});
		},

		/**
		 * Render chat view.
		 *
		 * @param {Object} data
		 */
		renderChatView: function(data) {
			const $panel = $('.bkx-chat-view-panel');

			// Show active chat view.
			$panel.find('.bkx-no-chat-selected').hide();
			$panel.find('.bkx-active-chat').show();

			// Update header.
			$panel.find('.bkx-visitor-info strong').text(data.chat.visitor_name || 'Anonymous');
			$panel.find('.bkx-visitor-info span').text(data.chat.visitor_email || '');

			// Update actions based on status.
			const $actions = $panel.find('.bkx-chat-actions');
			$actions.empty();

			if (data.chat.status === 'pending') {
				$actions.append('<button type="button" class="button button-primary bkx-accept-chat">Accept</button>');
			} else if (data.chat.status === 'active') {
				$actions.append('<button type="button" class="button bkx-transfer-chat">Transfer</button>');
				$actions.append('<button type="button" class="button bkx-close-chat">Close</button>');
			}

			// Render messages.
			this.renderMessages(data.messages);

			// Update visitor panel.
			this.renderVisitorDetails(data.chat);

			// Scroll to bottom.
			const $messages = $panel.find('.bkx-chat-messages');
			$messages.scrollTop($messages[0].scrollHeight);
		},

		/**
		 * Load messages.
		 *
		 * @param {number} chatId
		 */
		loadMessages: function(chatId) {
			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_get_messages',
					nonce: bkxLivechatAdmin.nonce,
					chat_id: chatId
				},
				success: function(response) {
					if (response.success) {
						BkxLiveChatAdmin.renderMessages(response.data.messages);
					}
				}
			});
		},

		/**
		 * Render messages.
		 *
		 * @param {Array} messages
		 */
		renderMessages: function(messages) {
			const $container = $('.bkx-chat-messages');
			const wasAtBottom = $container[0].scrollHeight - $container.scrollTop() <= $container.outerHeight() + 50;

			let html = '';
			messages.forEach(function(msg) {
				const typeClass = msg.sender_type === 'visitor' ? 'visitor' : (msg.sender_type === 'operator' ? 'operator' : 'system');

				html += '<div class="bkx-message ' + typeClass + '">';
				html += '<div class="message-content">' + BkxLiveChatAdmin.escapeHtml(msg.message) + '</div>';
				html += '<span class="message-time">' + msg.time + '</span>';
				html += '</div>';
			});

			$container.html(html);

			// Auto-scroll if was at bottom.
			if (wasAtBottom) {
				$container.scrollTop($container[0].scrollHeight);
			}
		},

		/**
		 * Render visitor details.
		 *
		 * @param {Object} chat
		 */
		renderVisitorDetails: function(chat) {
			const $panel = $('.bkx-visitor-details');
			if (!$panel.length) {
				return;
			}

			let html = '';
			html += '<p><strong>Name</strong>' + this.escapeHtml(chat.visitor_name || 'Anonymous') + '</p>';
			html += '<p><strong>Email</strong>' + this.escapeHtml(chat.visitor_email || '-') + '</p>';
			html += '<p><strong>Current Page</strong>' + this.escapeHtml(chat.page_url || '-') + '</p>';
			html += '<p><strong>Browser</strong>' + this.escapeHtml(chat.user_agent || '-') + '</p>';
			html += '<p><strong>Started</strong>' + chat.started_at + '</p>';

			$panel.html(html);
		},

		/**
		 * Send message.
		 *
		 * @param {Event} e
		 */
		sendMessage: function(e) {
			e.preventDefault();

			const $textarea = $('#bkx-chat-textarea');
			const message = $textarea.val().trim();

			if (!message || !this.currentChatId) {
				return;
			}

			// Check for canned response shortcut.
			if (message.startsWith('/')) {
				const shortcut = message.substring(1);
				const $response = $('.bkx-canned-item[data-shortcut="' + shortcut + '"]');
				if ($response.length) {
					$textarea.val($response.data('content'));
					return;
				}
			}

			$textarea.val('').focus();

			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_send_message',
					nonce: bkxLivechatAdmin.nonce,
					chat_id: this.currentChatId,
					message: message
				},
				success: function(response) {
					if (response.success) {
						BkxLiveChatAdmin.loadMessages(BkxLiveChatAdmin.currentChatId);
					}
				}
			});
		},

		/**
		 * Handle keydown in textarea.
		 *
		 * @param {Event} e
		 */
		handleKeydown: function(e) {
			// Enter without shift sends message.
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				$('#bkx-chat-form').submit();
			}
		},

		/**
		 * Accept chat.
		 *
		 * @param {Event} e
		 */
		acceptChat: function(e) {
			e.preventDefault();

			if (!this.currentChatId) {
				return;
			}

			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_accept_chat',
					nonce: bkxLivechatAdmin.nonce,
					chat_id: this.currentChatId
				},
				success: function(response) {
					if (response.success) {
						BkxLiveChatAdmin.loadChatView(BkxLiveChatAdmin.currentChatId);
						BkxLiveChatAdmin.loadChatList();
					}
				}
			});
		},

		/**
		 * Close chat.
		 *
		 * @param {Event} e
		 */
		closeChat: function(e) {
			e.preventDefault();

			if (!this.currentChatId) {
				return;
			}

			if (!confirm('Are you sure you want to close this chat?')) {
				return;
			}

			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_close_chat',
					nonce: bkxLivechatAdmin.nonce,
					chat_id: this.currentChatId
				},
				success: function(response) {
					if (response.success) {
						BkxLiveChatAdmin.currentChatId = null;
						BkxLiveChatAdmin.loadChatList();

						// Reset view.
						$('.bkx-active-chat').hide();
						$('.bkx-no-chat-selected').show();
					}
				}
			});
		},

		/**
		 * Transfer chat.
		 *
		 * @param {Event} e
		 */
		transferChat: function(e) {
			e.preventDefault();
			// TODO: Implement transfer modal.
			alert('Transfer functionality coming soon.');
		},

		/**
		 * Update operator status.
		 *
		 * @param {Event} e
		 */
		updateOperatorStatus: function(e) {
			const status = $(e.currentTarget).val();

			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_update_operator_status',
					nonce: bkxLivechatAdmin.nonce,
					status: status
				},
				success: function(response) {
					if (response.success) {
						// Update UI.
						$('.bkx-operator-status-indicator')
							.removeClass('online away busy offline')
							.addClass(status);
					}
				}
			});
		},

		/**
		 * Load active visitors.
		 */
		loadActiveVisitors: function() {
			const $container = $('.bkx-active-visitors');
			if (!$container.length) {
				return;
			}

			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_get_active_visitors',
					nonce: bkxLivechatAdmin.nonce
				},
				success: function(response) {
					if (response.success && response.data.visitors) {
						BkxLiveChatAdmin.renderActiveVisitors(response.data.visitors);
					}
				}
			});
		},

		/**
		 * Render active visitors.
		 *
		 * @param {Array} visitors
		 */
		renderActiveVisitors: function(visitors) {
			const $container = $('.bkx-active-visitors');

			if (visitors.length === 0) {
				$container.html('<p class="bkx-no-visitors">No active visitors</p>');
				return;
			}

			let html = '';
			visitors.forEach(function(visitor) {
				html += '<div class="bkx-visitor-item">';
				html += '<strong>' + BkxLiveChatAdmin.escapeHtml(visitor.name || 'Anonymous') + '</strong>';
				html += '<div class="bkx-visitor-page" title="' + BkxLiveChatAdmin.escapeHtml(visitor.page_url) + '">';
				html += BkxLiveChatAdmin.escapeHtml(visitor.page_title || visitor.page_url);
				html += '</div>';
				html += '</div>';
			});

			$container.html(html);
		},

		/**
		 * Initialize canned responses.
		 */
		initCannedResponses: function() {
			// Close dropdown on outside click.
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.bkx-canned-responses').length) {
					$('.bkx-canned-dropdown').hide();
				}
			});
		},

		/**
		 * Toggle canned dropdown.
		 *
		 * @param {Event} e
		 */
		toggleCannedDropdown: function(e) {
			e.preventDefault();
			$('.bkx-canned-dropdown').toggle();
		},

		/**
		 * Insert canned response.
		 *
		 * @param {Event} e
		 */
		insertCannedResponse: function(e) {
			e.preventDefault();
			const content = $(e.currentTarget).data('content');

			$('#bkx-chat-textarea').val(content).focus();
			$('.bkx-canned-dropdown').hide();

			// Update use count.
			const responseId = $(e.currentTarget).data('id');
			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_use_response',
					nonce: bkxLivechatAdmin.nonce,
					response_id: responseId
				}
			});
		},

		/**
		 * View transcript.
		 *
		 * @param {Event} e
		 */
		viewTranscript: function(e) {
			e.preventDefault();
			const chatId = $(e.currentTarget).data('id');

			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_get_transcript',
					nonce: bkxLivechatAdmin.nonce,
					chat_id: chatId
				},
				success: function(response) {
					if (response.success) {
						$('#bkx-transcript-content').html(response.data.html);
						$('#bkx-transcript-modal').show();
					}
				}
			});
		},

		/**
		 * Open response modal.
		 *
		 * @param {Event} e
		 */
		openResponseModal: function(e) {
			e.preventDefault();

			$('#bkx-response-modal-title').text('Add Canned Response');
			$('#bkx-response-form')[0].reset();
			$('#bkx-response-id').val('');
			$('#bkx-response-modal').show();
		},

		/**
		 * Edit response.
		 *
		 * @param {Event} e
		 */
		editResponse: function(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);

			$('#bkx-response-modal-title').text('Edit Canned Response');
			$('#bkx-response-id').val($btn.data('id'));
			$('#bkx-response-shortcut').val($btn.data('shortcut'));
			$('#bkx-response-title').val($btn.data('title'));
			$('#bkx-response-content').val($btn.data('content'));
			$('#bkx-response-category').val($btn.data('category'));
			$('#bkx-response-modal').show();
		},

		/**
		 * Delete response.
		 *
		 * @param {Event} e
		 */
		deleteResponse: function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to delete this response?')) {
				return;
			}

			const responseId = $(e.currentTarget).data('id');

			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_delete_response',
					nonce: bkxLivechatAdmin.nonce,
					response_id: responseId
				},
				success: function(response) {
					if (response.success) {
						$('#bkx-responses-table tr[data-id="' + responseId + '"]').fadeOut(function() {
							$(this).remove();
						});
					}
				}
			});
		},

		/**
		 * Save response.
		 *
		 * @param {Event} e
		 */
		saveResponse: function(e) {
			e.preventDefault();
			const $form = $(e.currentTarget);

			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_save_response',
					nonce: bkxLivechatAdmin.nonce,
					id: $('#bkx-response-id').val(),
					shortcut: $('#bkx-response-shortcut').val(),
					title: $('#bkx-response-title').val(),
					content: $('#bkx-response-content').val(),
					category: $('#bkx-response-category').val()
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Error saving response.');
					}
				}
			});
		},

		/**
		 * Add operator.
		 *
		 * @param {Event} e
		 */
		addOperator: function(e) {
			e.preventDefault();
			const $form = $(e.currentTarget);

			$.ajax({
				url: bkxLivechatAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_livechat_add_operator',
					nonce: bkxLivechatAdmin.nonce,
					user_id: $form.find('[name="user_id"]').val(),
					max_chats: $form.find('[name="max_chats"]').val()
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Error adding operator.');
					}
				}
			});
		},

		/**
		 * Close modal.
		 *
		 * @param {Event} e
		 */
		closeModal: function(e) {
			e.preventDefault();
			$(e.currentTarget).closest('.bkx-modal').hide();
		},

		/**
		 * Escape HTML.
		 *
		 * @param {string} str
		 * @return {string}
		 */
		escapeHtml: function(str) {
			if (!str) {
				return '';
			}
			const div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}
	};

	$(document).ready(function() {
		BkxLiveChatAdmin.init();
	});

})(jQuery);
