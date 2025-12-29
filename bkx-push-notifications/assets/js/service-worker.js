/**
 * Push Notifications Service Worker.
 *
 * @package BookingX\PushNotifications
 * @since   1.0.0
 */

/* global self, clients */

self.addEventListener('push', function(event) {
	if (!event.data) {
		return;
	}

	const data = event.data.json();

	const options = {
		body: data.body || '',
		icon: data.icon || '/wp-content/plugins/bkx-push-notifications/assets/images/icon-192.png',
		badge: data.badge || '/wp-content/plugins/bkx-push-notifications/assets/images/badge-96.png',
		data: data.data || {},
		tag: data.tag || 'bkx-notification',
		requireInteraction: data.requireInteraction || false,
		actions: data.actions || []
	};

	event.waitUntil(
		self.registration.showNotification(data.title || 'Notification', options)
	);

	// Track delivery.
	if (data.data && data.data.tracking_url && data.data.log_id) {
		fetch(data.data.tracking_url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				log_id: data.data.log_id,
				event: 'delivered'
			})
		}).catch(function() {
			// Ignore tracking errors.
		});
	}
});

self.addEventListener('notificationclick', function(event) {
	event.notification.close();

	const data = event.notification.data || {};
	let url = data.url || '/';

	// Handle action clicks.
	if (event.action) {
		const action = event.notification.data.actions?.find(a => a.action === event.action);
		if (action && action.url) {
			url = action.url;
		}
	}

	event.waitUntil(
		clients.matchAll({ type: 'window', includeUncontrolled: true })
			.then(function(clientList) {
				// Try to focus existing window.
				for (let i = 0; i < clientList.length; i++) {
					const client = clientList[i];
					if (client.url === url && 'focus' in client) {
						return client.focus();
					}
				}

				// Open new window.
				if (clients.openWindow) {
					return clients.openWindow(url);
				}
			})
	);

	// Track click.
	if (data.tracking_url && data.log_id) {
		fetch(data.tracking_url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			},
			body: JSON.stringify({
				log_id: data.log_id,
				event: 'clicked'
			})
		}).catch(function() {
			// Ignore tracking errors.
		});
	}
});

self.addEventListener('notificationclose', function(event) {
	// Could track dismissals here if needed.
});

self.addEventListener('install', function(event) {
	self.skipWaiting();
});

self.addEventListener('activate', function(event) {
	event.waitUntil(clients.claim());
});
