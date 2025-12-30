/**
 * PWA Frontend Application
 *
 * @package BookingX\PWA
 */

(function() {
	'use strict';

	/**
	 * BKX PWA Application
	 */
	var BkxPwa = {
		deferredPrompt: null,
		isOnline: navigator.onLine,

		/**
		 * Initialize
		 */
		init: function() {
			this.registerServiceWorker();
			this.bindEvents();
			this.initOfflineDetection();
			this.initInstallPrompt();
			this.syncOfflineBookings();
		},

		/**
		 * Register Service Worker
		 */
		registerServiceWorker: function() {
			if (!('serviceWorker' in navigator)) {
				console.log('[BKX PWA] Service workers not supported');
				return;
			}

			navigator.serviceWorker.register(bkxPwa.swUrl, { scope: '/' })
				.then(function(registration) {
					console.log('[BKX PWA] Service Worker registered:', registration.scope);

					// Check for updates
					registration.addEventListener('updatefound', function() {
						var newWorker = registration.installing;
						newWorker.addEventListener('statechange', function() {
							if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
								BkxPwa.showUpdateNotification();
							}
						});
					});
				})
				.catch(function(error) {
					console.error('[BKX PWA] Service Worker registration failed:', error);
				});
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			// Online/offline events
			window.addEventListener('online', this.handleOnline.bind(this));
			window.addEventListener('offline', this.handleOffline.bind(this));

			// Before install prompt
			window.addEventListener('beforeinstallprompt', this.handleBeforeInstallPrompt.bind(this));

			// App installed
			window.addEventListener('appinstalled', this.handleAppInstalled.bind(this));

			// Install prompt buttons
			document.addEventListener('click', function(e) {
				if (e.target.matches('.bkx-install-button')) {
					BkxPwa.triggerInstall();
				}
				if (e.target.matches('.bkx-install-dismiss, .bkx-install-close')) {
					BkxPwa.dismissInstallPrompt();
				}
				if (e.target.matches('.bkx-ios-dismiss, .bkx-ios-close')) {
					BkxPwa.dismissIosPrompt();
				}
			});
		},

		/**
		 * Initialize offline detection
		 */
		initOfflineDetection: function() {
			this.updateOnlineStatus();
		},

		/**
		 * Handle online event
		 */
		handleOnline: function() {
			this.isOnline = true;
			this.updateOnlineStatus();
			this.syncOfflineBookings();
		},

		/**
		 * Handle offline event
		 */
		handleOffline: function() {
			this.isOnline = false;
			this.updateOnlineStatus();
		},

		/**
		 * Update online status indicator
		 */
		updateOnlineStatus: function() {
			var indicator = document.querySelector('.bkx-offline-indicator');

			if (!this.isOnline) {
				if (!indicator) {
					indicator = document.createElement('div');
					indicator.className = 'bkx-offline-indicator';
					indicator.innerHTML = '<span class="bkx-offline-dot"></span><span>You are offline</span>';
					document.body.appendChild(indicator);
				}
				indicator.classList.remove('bkx-online');
			} else {
				if (indicator) {
					indicator.classList.add('bkx-online');
					indicator.querySelector('span:last-child').textContent = 'Back online';
					setTimeout(function() {
						indicator.remove();
					}, 3000);
				}
			}
		},

		/**
		 * Initialize install prompt
		 */
		initInstallPrompt: function() {
			if (!bkxPwa.installPrompt) return;

			// Check if already installed
			if (window.matchMedia('(display-mode: standalone)').matches) {
				return;
			}

			// Check if previously dismissed
			if (this.getCookie('bkx_pwa_prompt_dismissed')) {
				return;
			}

			// Show prompt after delay
			setTimeout(function() {
				if (BkxPwa.isIos()) {
					BkxPwa.showIosPrompt();
				}
				// For other browsers, wait for beforeinstallprompt event
			}, bkxPwa.promptDelay);
		},

		/**
		 * Handle before install prompt
		 */
		handleBeforeInstallPrompt: function(e) {
			e.preventDefault();
			this.deferredPrompt = e;

			// Show custom prompt after delay if not dismissed
			if (!this.getCookie('bkx_pwa_prompt_dismissed')) {
				setTimeout(function() {
					BkxPwa.showInstallPrompt();
				}, bkxPwa.promptDelay);
			}
		},

		/**
		 * Show install prompt
		 */
		showInstallPrompt: function() {
			var prompt = document.getElementById('bkx-pwa-install-prompt');
			if (prompt) {
				prompt.style.display = 'block';
				this.trackEvent('prompt_shown');
			}
		},

		/**
		 * Show iOS prompt
		 */
		showIosPrompt: function() {
			var prompt = document.getElementById('bkx-ios-install-prompt');
			if (prompt) {
				prompt.style.display = 'flex';
				this.trackEvent('prompt_shown');
			}
		},

		/**
		 * Trigger install
		 */
		triggerInstall: function() {
			if (!this.deferredPrompt) return;

			this.deferredPrompt.prompt();

			this.deferredPrompt.userChoice.then(function(choiceResult) {
				if (choiceResult.outcome === 'accepted') {
					BkxPwa.trackEvent('install_accepted');
				} else {
					BkxPwa.trackEvent('install_dismissed');
				}
				BkxPwa.deferredPrompt = null;
			});

			this.hideInstallPrompt();
		},

		/**
		 * Dismiss install prompt
		 */
		dismissInstallPrompt: function() {
			this.hideInstallPrompt();
			this.setCookie('bkx_pwa_prompt_dismissed', '1', 7);
			this.trackEvent('install_dismissed');
		},

		/**
		 * Dismiss iOS prompt
		 */
		dismissIosPrompt: function() {
			var prompt = document.getElementById('bkx-ios-install-prompt');
			if (prompt) {
				prompt.style.display = 'none';
			}
			this.setCookie('bkx_pwa_prompt_dismissed', '1', 7);
		},

		/**
		 * Hide install prompt
		 */
		hideInstallPrompt: function() {
			var prompt = document.getElementById('bkx-pwa-install-prompt');
			if (prompt) {
				prompt.style.display = 'none';
			}
		},

		/**
		 * Handle app installed
		 */
		handleAppInstalled: function() {
			this.hideInstallPrompt();
			this.deferredPrompt = null;
			console.log('[BKX PWA] App installed');
		},

		/**
		 * Show update notification
		 */
		showUpdateNotification: function() {
			var notification = document.createElement('div');
			notification.className = 'bkx-update-notification';
			notification.innerHTML = '<p>A new version is available.</p><button onclick="window.location.reload()">Update</button>';
			document.body.appendChild(notification);
		},

		/**
		 * Sync offline bookings
		 */
		syncOfflineBookings: function() {
			if (!this.isOnline || !bkxPwa.offlineBookings) return;

			// Request background sync if supported
			if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
				navigator.serviceWorker.ready.then(function(registration) {
					registration.sync.register('sync-bookings');
				});
			} else {
				// Fallback: manual sync
				this.manualSyncBookings();
			}
		},

		/**
		 * Manual sync for browsers without background sync
		 */
		manualSyncBookings: function() {
			var dbRequest = indexedDB.open('bkx-pwa-db', 1);

			dbRequest.onsuccess = function(event) {
				var db = event.target.result;

				if (!db.objectStoreNames.contains('offline-bookings')) {
					return;
				}

				var tx = db.transaction('offline-bookings', 'readonly');
				var store = tx.objectStore('offline-bookings');
				var request = store.getAll();

				request.onsuccess = function() {
					var bookings = request.result.filter(function(b) { return !b.synced; });

					if (bookings.length === 0) return;

					BkxPwa.showSyncStatus('Syncing ' + bookings.length + ' booking(s)...');

					fetch(bkxPwa.ajaxUrl + '?action=bkx_pwa_sync_offline_bookings&nonce=' + bkxPwa.nonce, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify(bookings)
					})
					.then(function(response) { return response.json(); })
					.then(function(data) {
						if (data.success) {
							BkxPwa.showSyncStatus('Bookings synced!', 'success');
							BkxPwa.markBookingsSynced(data.data.synced);
						} else {
							BkxPwa.showSyncStatus('Sync failed', 'error');
						}
					})
					.catch(function() {
						BkxPwa.showSyncStatus('Sync failed', 'error');
					});
				};
			};
		},

		/**
		 * Mark bookings as synced in IndexedDB
		 */
		markBookingsSynced: function(syncedBookings) {
			var dbRequest = indexedDB.open('bkx-pwa-db', 1);

			dbRequest.onsuccess = function(event) {
				var db = event.target.result;
				var tx = db.transaction('offline-bookings', 'readwrite');
				var store = tx.objectStore('offline-bookings');

				syncedBookings.forEach(function(booking) {
					var getRequest = store.get(booking.offline_id);
					getRequest.onsuccess = function() {
						var record = getRequest.result;
						if (record) {
							record.synced = true;
							record.booking_id = booking.booking_id;
							store.put(record);
						}
					};
				});
			};
		},

		/**
		 * Show sync status
		 */
		showSyncStatus: function(message, type) {
			var existing = document.querySelector('.bkx-sync-status');
			if (existing) existing.remove();

			var status = document.createElement('div');
			status.className = 'bkx-sync-status';

			var icon = '';
			if (type === 'success') {
				icon = '<span class="bkx-sync-success">&#10003;</span>';
			} else if (type === 'error') {
				icon = '<span class="bkx-sync-error">&#10007;</span>';
			} else {
				icon = '<span class="bkx-sync-spinner"></span>';
			}

			status.innerHTML = '<h4>' + icon + ' Sync Status</h4><p>' + message + '</p>';
			document.body.appendChild(status);

			if (type) {
				setTimeout(function() {
					status.remove();
				}, 5000);
			}
		},

		/**
		 * Track event
		 */
		trackEvent: function(event) {
			fetch(bkxPwa.ajaxUrl + '?action=bkx_pwa_track_event&nonce=' + bkxPwa.nonce + '&event=' + event, {
				method: 'POST'
			}).catch(function() {});
		},

		/**
		 * Check if iOS
		 */
		isIos: function() {
			return /iphone|ipad|ipod/.test(navigator.userAgent.toLowerCase()) && !window.MSStream;
		},

		/**
		 * Set cookie
		 */
		setCookie: function(name, value, days) {
			var expires = '';
			if (days) {
				var date = new Date();
				date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
				expires = '; expires=' + date.toUTCString();
			}
			document.cookie = name + '=' + (value || '') + expires + '; path=/';
		},

		/**
		 * Get cookie
		 */
		getCookie: function(name) {
			var nameEQ = name + '=';
			var ca = document.cookie.split(';');
			for (var i = 0; i < ca.length; i++) {
				var c = ca[i];
				while (c.charAt(0) === ' ') c = c.substring(1, c.length);
				if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
			}
			return null;
		}
	};

	/**
	 * Initialize when DOM is ready
	 */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			BkxPwa.init();
		});
	} else {
		BkxPwa.init();
	}

	// Expose for debugging
	window.BkxPwa = BkxPwa;

})();
