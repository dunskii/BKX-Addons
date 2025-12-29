/**
 * Live Chat Widget JavaScript.
 *
 * @package BookingX\LiveChat
 * @since   1.0.0
 */

(function() {
	'use strict';

	const BkxLiveChatWidget = {
		chatId: null,
		sessionId: null,
		isOpen: false,
		pollInterval: null,
		lastMessageId: 0,

		/**
		 * Initialize.
		 */
		init: function() {
			this.sessionId = this.getSessionId();
			this.bindEvents();
			this.checkExistingChat();
		},

		/**
		 * Get or create session ID.
		 *
		 * @return {string}
		 */
		getSessionId: function() {
			let sessionId = localStorage.getItem('bkx_chat_session');
			if (!sessionId) {
				sessionId = 'bkx_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
				localStorage.setItem('bkx_chat_session', sessionId);
			}
			return sessionId;
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			const self = this;

			// Toggle chat window.
			document.querySelector('.bkx-chat-button')?.addEventListener('click', function() {
				self.toggleChat();
			});

			// Minimize button.
			document.querySelector('.bkx-chat-minimize')?.addEventListener('click', function(e) {
				e.stopPropagation();
				self.closeChat();
			});

			// Pre-chat form.
			document.querySelector('.bkx-prechat-form form')?.addEventListener('submit', function(e) {
				e.preventDefault();
				self.startChat();
			});

			// Message form.
			document.querySelector('.bkx-chat-input form')?.addEventListener('submit', function(e) {
				e.preventDefault();
				self.sendMessage();
			});

			// Enter key to send.
			document.querySelector('.bkx-chat-input textarea')?.addEventListener('keydown', function(e) {
				if (e.key === 'Enter' && !e.shiftKey) {
					e.preventDefault();
					self.sendMessage();
				}
			});

			// Rating form.
			document.querySelectorAll('.bkx-rating-star').forEach(function(star) {
				star.addEventListener('click', function() {
					self.selectRating(parseInt(this.dataset.rating));
				});
			});

			document.querySelector('.bkx-submit-rating-btn')?.addEventListener('click', function() {
				self.submitRating();
			});

			document.querySelector('.bkx-skip-rating-btn')?.addEventListener('click', function() {
				self.skipRating();
			});

			// Track page views.
			this.trackPageView();
		},

		/**
		 * Toggle chat window.
		 */
		toggleChat: function() {
			if (this.isOpen) {
				this.closeChat();
			} else {
				this.openChat();
			}
		},

		/**
		 * Open chat.
		 */
		openChat: function() {
			this.isOpen = true;
			document.querySelector('.bkx-chat-button')?.classList.add('open');
			document.querySelector('.bkx-chat-window')?.classList.add('open');

			// Clear unread badge.
			const badge = document.querySelector('.bkx-unread-badge');
			if (badge) {
				badge.style.display = 'none';
				badge.textContent = '0';
			}

			// Focus input.
			setTimeout(function() {
				const input = document.querySelector('.bkx-prechat-form input[name="name"]') ||
				              document.querySelector('.bkx-chat-input textarea');
				input?.focus();
			}, 100);
		},

		/**
		 * Close chat.
		 */
		closeChat: function() {
			this.isOpen = false;
			document.querySelector('.bkx-chat-button')?.classList.remove('open');
			document.querySelector('.bkx-chat-window')?.classList.remove('open');
		},

		/**
		 * Check for existing chat.
		 */
		checkExistingChat: function() {
			const self = this;
			const storedChatId = localStorage.getItem('bkx_chat_id');

			if (!storedChatId) {
				return;
			}

			this.ajax('bkx_livechat_check_chat', {
				chat_id: storedChatId,
				session_id: this.sessionId
			}, function(response) {
				if (response.success && response.data.active) {
					self.chatId = storedChatId;
					self.showChatView();
					self.loadMessages();
					self.startPolling();
				} else {
					localStorage.removeItem('bkx_chat_id');
				}
			});
		},

		/**
		 * Start chat.
		 */
		startChat: function() {
			const self = this;
			const form = document.querySelector('.bkx-prechat-form form');

			const name = form.querySelector('[name="name"]')?.value.trim();
			const email = form.querySelector('[name="email"]')?.value.trim();
			const message = form.querySelector('[name="message"]')?.value.trim();

			if (!message) {
				return;
			}

			this.ajax('bkx_livechat_start_chat', {
				session_id: this.sessionId,
				name: name,
				email: email,
				message: message,
				page_url: window.location.href,
				page_title: document.title
			}, function(response) {
				if (response.success) {
					self.chatId = response.data.chat_id;
					localStorage.setItem('bkx_chat_id', self.chatId);
					self.showChatView();
					self.loadMessages();
					self.startPolling();
				} else {
					alert(response.data?.message || 'Unable to start chat. Please try again.');
				}
			});
		},

		/**
		 * Show chat view.
		 */
		showChatView: function() {
			document.querySelector('.bkx-prechat-form')?.classList.add('hidden');
			document.querySelector('.bkx-chat-messages')?.classList.remove('hidden');
			document.querySelector('.bkx-chat-input')?.classList.remove('hidden');
		},

		/**
		 * Show pre-chat form.
		 */
		showPrechatForm: function() {
			document.querySelector('.bkx-prechat-form')?.classList.remove('hidden');
			document.querySelector('.bkx-chat-messages')?.classList.add('hidden');
			document.querySelector('.bkx-chat-input')?.classList.add('hidden');
			document.querySelector('.bkx-rating-form')?.classList.add('hidden');
		},

		/**
		 * Send message.
		 */
		sendMessage: function() {
			const self = this;
			const textarea = document.querySelector('.bkx-chat-input textarea');
			const message = textarea?.value.trim();

			if (!message || !this.chatId) {
				return;
			}

			textarea.value = '';

			// Add message optimistically.
			this.addMessage({
				message: message,
				sender_type: 'visitor',
				time: 'Just now'
			});

			this.ajax('bkx_livechat_send_message', {
				chat_id: this.chatId,
				session_id: this.sessionId,
				message: message
			}, function(response) {
				if (!response.success) {
					// Remove optimistic message on error.
					self.loadMessages();
				}
			});
		},

		/**
		 * Load messages.
		 */
		loadMessages: function() {
			const self = this;

			if (!this.chatId) {
				return;
			}

			this.ajax('bkx_livechat_get_messages', {
				chat_id: this.chatId,
				session_id: this.sessionId,
				last_id: this.lastMessageId
			}, function(response) {
				if (response.success) {
					self.renderMessages(response.data.messages);

					// Check if chat is closed.
					if (response.data.status === 'closed') {
						self.handleChatClosed();
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
			const container = document.querySelector('.bkx-chat-messages');
			if (!container) {
				return;
			}

			const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;

			// Clear and re-render all messages.
			container.innerHTML = '';

			messages.forEach(function(msg) {
				this.addMessage(msg, false);
				if (msg.id > this.lastMessageId) {
					this.lastMessageId = msg.id;
				}
			}.bind(this));

			// Auto-scroll.
			if (wasAtBottom) {
				container.scrollTop = container.scrollHeight;
			}

			// Update unread badge if chat is closed.
			if (!this.isOpen && messages.length > 0) {
				const unreadCount = messages.filter(m => m.sender_type === 'operator' && m.id > this.lastMessageId).length;
				if (unreadCount > 0) {
					this.updateUnreadBadge(unreadCount);
				}
			}
		},

		/**
		 * Add message to chat.
		 *
		 * @param {Object} msg
		 * @param {boolean} scroll
		 */
		addMessage: function(msg, scroll = true) {
			const container = document.querySelector('.bkx-chat-messages');
			if (!container) {
				return;
			}

			const div = document.createElement('div');
			div.className = 'bkx-message ' + msg.sender_type;

			const content = document.createElement('div');
			content.className = 'message-content';
			content.textContent = msg.message;
			div.appendChild(content);

			const time = document.createElement('span');
			time.className = 'message-time';
			time.textContent = msg.time;
			div.appendChild(time);

			container.appendChild(div);

			if (scroll) {
				container.scrollTop = container.scrollHeight;
			}
		},

		/**
		 * Update unread badge.
		 *
		 * @param {number} count
		 */
		updateUnreadBadge: function(count) {
			const badge = document.querySelector('.bkx-unread-badge');
			if (badge) {
				badge.style.display = count > 0 ? 'flex' : 'none';
				badge.textContent = count > 9 ? '9+' : count;
			}
		},

		/**
		 * Start polling.
		 */
		startPolling: function() {
			if (this.pollInterval) {
				return;
			}

			const self = this;
			this.pollInterval = setInterval(function() {
				self.loadMessages();
			}, 3000);
		},

		/**
		 * Stop polling.
		 */
		stopPolling: function() {
			if (this.pollInterval) {
				clearInterval(this.pollInterval);
				this.pollInterval = null;
			}
		},

		/**
		 * Handle chat closed.
		 */
		handleChatClosed: function() {
			this.stopPolling();

			// Show rating form if enabled.
			const ratingForm = document.querySelector('.bkx-rating-form');
			if (ratingForm && bkxLivechat.enableRatings) {
				document.querySelector('.bkx-chat-input')?.classList.add('hidden');
				ratingForm.classList.remove('hidden');
			} else {
				this.endChat();
			}
		},

		/**
		 * Select rating.
		 *
		 * @param {number} rating
		 */
		selectRating: function(rating) {
			document.querySelectorAll('.bkx-rating-star').forEach(function(star) {
				const starRating = parseInt(star.dataset.rating);
				star.classList.toggle('selected', starRating <= rating);
			});

			document.querySelector('.bkx-rating-form')?.setAttribute('data-rating', rating);
		},

		/**
		 * Submit rating.
		 */
		submitRating: function() {
			const self = this;
			const form = document.querySelector('.bkx-rating-form');
			const rating = parseInt(form?.getAttribute('data-rating') || '0');
			const feedback = form?.querySelector('textarea')?.value.trim();

			if (rating === 0) {
				alert('Please select a rating.');
				return;
			}

			this.ajax('bkx_livechat_submit_rating', {
				chat_id: this.chatId,
				session_id: this.sessionId,
				rating: rating,
				feedback: feedback
			}, function() {
				self.endChat();
			});
		},

		/**
		 * Skip rating.
		 */
		skipRating: function() {
			this.endChat();
		},

		/**
		 * End chat.
		 */
		endChat: function() {
			this.chatId = null;
			this.lastMessageId = 0;
			localStorage.removeItem('bkx_chat_id');

			// Show thank you message.
			const messages = document.querySelector('.bkx-chat-messages');
			if (messages) {
				messages.innerHTML = '<div class="bkx-message system">Thank you for chatting with us!</div>';
			}

			// Reset after delay.
			setTimeout(function() {
				this.showPrechatForm();
				this.closeChat();

				// Reset form.
				const form = document.querySelector('.bkx-prechat-form form');
				if (form) {
					form.reset();
				}
			}.bind(this), 3000);
		},

		/**
		 * Track page view.
		 */
		trackPageView: function() {
			this.ajax('bkx_livechat_track_visitor', {
				session_id: this.sessionId,
				page_url: window.location.href,
				page_title: document.title,
				referrer: document.referrer
			});
		},

		/**
		 * AJAX helper.
		 *
		 * @param {string} action
		 * @param {Object} data
		 * @param {Function} callback
		 */
		ajax: function(action, data, callback) {
			const formData = new FormData();
			formData.append('action', action);
			formData.append('nonce', bkxLivechat.nonce);

			for (const key in data) {
				if (data.hasOwnProperty(key)) {
					formData.append(key, data[key]);
				}
			}

			fetch(bkxLivechat.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(function(response) {
				return response.json();
			})
			.then(function(response) {
				if (callback) {
					callback(response);
				}
			})
			.catch(function(error) {
				console.error('BKX LiveChat Error:', error);
			});
		}
	};

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			BkxLiveChatWidget.init();
		});
	} else {
		BkxLiveChatWidget.init();
	}

})();
