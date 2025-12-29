/**
 * Push Notifications Frontend JavaScript.
 *
 * @package BookingX\PushNotifications
 * @since   1.0.0
 */

(function() {
	'use strict';

	const BkxPush = {
		/**
		 * Initialize.
		 */
		init: function() {
			if (!this.isSupported()) {
				console.log('Push notifications not supported');
				return;
			}

			this.registerServiceWorker();
		},

		/**
		 * Check if push is supported.
		 *
		 * @return {boolean}
		 */
		isSupported: function() {
			return 'serviceWorker' in navigator &&
			       'PushManager' in window &&
			       'Notification' in window;
		},

		/**
		 * Register service worker.
		 */
		registerServiceWorker: function() {
			const self = this;

			navigator.serviceWorker.register(bkxPushConfig.serviceWorkerUrl)
				.then(function(registration) {
					console.log('BKX Push: Service Worker registered');
					self.serviceWorkerRegistration = registration;

					// Check current subscription.
					return registration.pushManager.getSubscription();
				})
				.then(function(subscription) {
					if (subscription) {
						// Already subscribed.
						console.log('BKX Push: Already subscribed');
						return;
					}

					// Show prompt after delay.
					if (Notification.permission === 'default') {
						setTimeout(function() {
							self.showPrompt();
						}, bkxPushConfig.promptDelay);
					}
				})
				.catch(function(error) {
					console.error('BKX Push: Service Worker error', error);
				});
		},

		/**
		 * Show subscription prompt.
		 */
		showPrompt: function() {
			const self = this;

			// Create prompt element.
			const prompt = document.createElement('div');
			prompt.className = 'bkx-push-prompt';
			prompt.innerHTML = `
				<div class="bkx-push-prompt-content">
					<span class="bkx-push-icon">🔔</span>
					<div class="bkx-push-text">
						<strong>${bkxPushConfig.promptMessage || 'Get notified about your bookings!'}</strong>
						<p>Enable push notifications for booking updates and reminders.</p>
					</div>
					<div class="bkx-push-actions">
						<button class="bkx-push-allow">Allow</button>
						<button class="bkx-push-dismiss">Not now</button>
					</div>
				</div>
			`;

			// Add styles.
			const style = document.createElement('style');
			style.textContent = `
				.bkx-push-prompt {
					position: fixed;
					bottom: 20px;
					left: 20px;
					right: 20px;
					max-width: 400px;
					background: white;
					border-radius: 12px;
					box-shadow: 0 4px 20px rgba(0,0,0,0.15);
					z-index: 999999;
					animation: bkx-slide-up 0.3s ease;
				}
				@keyframes bkx-slide-up {
					from { transform: translateY(100%); opacity: 0; }
					to { transform: translateY(0); opacity: 1; }
				}
				.bkx-push-prompt-content {
					display: flex;
					align-items: flex-start;
					gap: 15px;
					padding: 20px;
				}
				.bkx-push-icon {
					font-size: 32px;
				}
				.bkx-push-text {
					flex: 1;
				}
				.bkx-push-text strong {
					display: block;
					margin-bottom: 5px;
				}
				.bkx-push-text p {
					margin: 0;
					font-size: 13px;
					color: #666;
				}
				.bkx-push-actions {
					display: flex;
					flex-direction: column;
					gap: 8px;
				}
				.bkx-push-allow {
					background: #2271b1;
					color: white;
					border: none;
					padding: 10px 20px;
					border-radius: 6px;
					cursor: pointer;
					font-weight: 600;
				}
				.bkx-push-allow:hover {
					background: #135e96;
				}
				.bkx-push-dismiss {
					background: none;
					border: none;
					color: #666;
					cursor: pointer;
					font-size: 12px;
				}
				@media (max-width: 480px) {
					.bkx-push-prompt {
						left: 10px;
						right: 10px;
					}
					.bkx-push-prompt-content {
						flex-direction: column;
						text-align: center;
					}
					.bkx-push-actions {
						width: 100%;
						flex-direction: row;
						justify-content: center;
					}
				}
			`;

			document.head.appendChild(style);
			document.body.appendChild(prompt);

			// Bind events.
			prompt.querySelector('.bkx-push-allow').addEventListener('click', function() {
				prompt.remove();
				self.subscribe();
			});

			prompt.querySelector('.bkx-push-dismiss').addEventListener('click', function() {
				prompt.remove();
				// Don't ask again for 7 days.
				localStorage.setItem('bkx_push_dismissed', Date.now());
			});

			// Check if dismissed recently.
			const dismissed = localStorage.getItem('bkx_push_dismissed');
			if (dismissed && Date.now() - dismissed < 7 * 24 * 60 * 60 * 1000) {
				prompt.remove();
			}
		},

		/**
		 * Subscribe to push notifications.
		 */
		subscribe: function() {
			const self = this;

			Notification.requestPermission()
				.then(function(permission) {
					if (permission !== 'granted') {
						console.log('BKX Push: Permission denied');
						return;
					}

					// Subscribe.
					return self.serviceWorkerRegistration.pushManager.subscribe({
						userVisibleOnly: true,
						applicationServerKey: self.urlBase64ToUint8Array(bkxPushConfig.vapidPublicKey)
					});
				})
				.then(function(subscription) {
					if (!subscription) {
						return;
					}

					// Send subscription to server.
					const key = subscription.getKey('p256dh');
					const auth = subscription.getKey('auth');

					return self.sendSubscription({
						endpoint: subscription.endpoint,
						p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(key))),
						auth: btoa(String.fromCharCode.apply(null, new Uint8Array(auth)))
					});
				})
				.then(function(response) {
					if (response && response.success) {
						console.log('BKX Push: Subscribed successfully');
					}
				})
				.catch(function(error) {
					console.error('BKX Push: Subscription error', error);
				});
		},

		/**
		 * Send subscription to server.
		 *
		 * @param {Object} subscription
		 * @return {Promise}
		 */
		sendSubscription: function(subscription) {
			const formData = new FormData();
			formData.append('action', 'bkx_push_subscribe');
			formData.append('nonce', bkxPushConfig.nonce);
			formData.append('endpoint', subscription.endpoint);
			formData.append('p256dh', subscription.p256dh);
			formData.append('auth', subscription.auth);

			return fetch(bkxPushConfig.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			}).then(function(response) {
				return response.json();
			});
		},

		/**
		 * Convert VAPID key to Uint8Array.
		 *
		 * @param {string} base64String
		 * @return {Uint8Array}
		 */
		urlBase64ToUint8Array: function(base64String) {
			const padding = '='.repeat((4 - base64String.length % 4) % 4);
			const base64 = (base64String + padding)
				.replace(/-/g, '+')
				.replace(/_/g, '/');

			const rawData = window.atob(base64);
			const outputArray = new Uint8Array(rawData.length);

			for (let i = 0; i < rawData.length; ++i) {
				outputArray[i] = rawData.charCodeAt(i);
			}

			return outputArray;
		}
	};

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			BkxPush.init();
		});
	} else {
		BkxPush.init();
	}

})();
