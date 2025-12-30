<?php
/**
 * Service Worker Service.
 *
 * @package BookingX\PWA\Services
 */

namespace BookingX\PWA\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ServiceWorkerService class.
 */
class ServiceWorkerService {

	/**
	 * Serve service worker.
	 */
	public function serve_service_worker() {
		$addon = \BookingX\PWA\PWAAddon::get_instance();

		header( 'Content-Type: application/javascript' );
		header( 'Service-Worker-Allowed: /' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		$cache_strategy   = $addon->get_setting( 'cache_strategy', 'network-first' );
		$cache_expiry     = $addon->get_setting( 'cache_expiry', 86400 );
		$offline_bookings = $addon->get_setting( 'offline_bookings', true );
		$precache_pages   = $addon->get_setting( 'precache_pages', array() );
		$offline_page     = $addon->get_setting( 'offline_page' ) ?: home_url( '/offline/' );

		// Get cache URLs.
		$cache_manager = $addon->get_service( 'cache_manager' );
		$cache_urls    = $cache_manager->get_precache_urls();

		echo $this->generate_service_worker(
			array(
				'version'          => BKX_PWA_VERSION . '.' . time(),
				'cache_name'       => 'bkx-pwa-v' . BKX_PWA_VERSION,
				'cache_strategy'   => $cache_strategy,
				'cache_expiry'     => $cache_expiry,
				'offline_page'     => $offline_page,
				'offline_bookings' => $offline_bookings,
				'precache_urls'    => $cache_urls,
				'api_url'          => home_url( '/wp-json/bkx-pwa/v1/' ),
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Generate service worker JavaScript.
	 *
	 * @param array $config Configuration.
	 * @return string
	 */
	private function generate_service_worker( $config ) {
		$precache_json = wp_json_encode( $config['precache_urls'] );

		return <<<JS
/**
 * BookingX PWA Service Worker
 * Version: {$config['version']}
 */

const CACHE_NAME = '{$config['cache_name']}';
const CACHE_STRATEGY = '{$config['cache_strategy']}';
const CACHE_EXPIRY = {$config['cache_expiry']};
const OFFLINE_PAGE = '{$config['offline_page']}';
const OFFLINE_BOOKINGS = {$this->bool_to_js($config['offline_bookings'])};
const API_URL = '{$config['api_url']}';
const AJAX_URL = '{$config['ajax_url']}';

const PRECACHE_URLS = {$precache_json};

// IndexedDB for offline bookings
const DB_NAME = 'bkx-pwa-db';
const DB_VERSION = 1;
const BOOKING_STORE = 'offline-bookings';

/**
 * Install event - precache assets
 */
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[BKX PWA] Precaching assets');
                return cache.addAll(PRECACHE_URLS);
            })
            .then(() => self.skipWaiting())
    );
});

/**
 * Activate event - cleanup old caches
 */
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name.startsWith('bkx-pwa-') && name !== CACHE_NAME)
                        .map((name) => caches.delete(name))
                );
            })
            .then(() => self.clients.claim())
    );
});

/**
 * Fetch event - handle requests
 */
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests for caching (but handle POST for offline bookings)
    if (request.method === 'POST' && OFFLINE_BOOKINGS && url.pathname.includes('/wp-json/')) {
        event.respondWith(handleBookingRequest(request));
        return;
    }

    if (request.method !== 'GET') {
        return;
    }

    // Skip admin and login pages
    if (url.pathname.includes('/wp-admin') || url.pathname.includes('/wp-login')) {
        return;
    }

    // Apply caching strategy
    switch (CACHE_STRATEGY) {
        case 'cache-first':
            event.respondWith(cacheFirst(request));
            break;
        case 'network-first':
            event.respondWith(networkFirst(request));
            break;
        case 'stale-while-revalidate':
            event.respondWith(staleWhileRevalidate(request));
            break;
        default:
            event.respondWith(networkFirst(request));
    }
});

/**
 * Cache-first strategy
 */
async function cacheFirst(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        return caches.match(OFFLINE_PAGE);
    }
}

/**
 * Network-first strategy
 */
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match(OFFLINE_PAGE);
        }

        return new Response('Offline', { status: 503 });
    }
}

/**
 * Stale-while-revalidate strategy
 */
async function staleWhileRevalidate(request) {
    const cachedResponse = await caches.match(request);

    const fetchPromise = fetch(request)
        .then((networkResponse) => {
            if (networkResponse.ok) {
                const cache = caches.open(CACHE_NAME);
                cache.then((c) => c.put(request, networkResponse.clone()));
            }
            return networkResponse;
        })
        .catch(() => cachedResponse || caches.match(OFFLINE_PAGE));

    return cachedResponse || fetchPromise;
}

/**
 * Handle booking requests (for offline support)
 */
async function handleBookingRequest(request) {
    try {
        const response = await fetch(request.clone());
        return response;
    } catch (error) {
        if (!OFFLINE_BOOKINGS) {
            throw error;
        }

        // Store booking for later sync
        const bookingData = await request.clone().json();
        await saveOfflineBooking(bookingData);

        return new Response(JSON.stringify({
            success: true,
            offline: true,
            message: 'Booking saved offline. Will sync when back online.'
        }), {
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

/**
 * Save booking to IndexedDB
 */
async function saveOfflineBooking(bookingData) {
    const db = await openDatabase();
    const tx = db.transaction(BOOKING_STORE, 'readwrite');
    const store = tx.objectStore(BOOKING_STORE);

    bookingData.offlineId = Date.now();
    bookingData.createdAt = new Date().toISOString();
    bookingData.synced = false;

    await store.add(bookingData);
}

/**
 * Open IndexedDB database
 */
function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(BOOKING_STORE)) {
                db.createObjectStore(BOOKING_STORE, { keyPath: 'offlineId' });
            }
        };
    });
}

/**
 * Sync offline bookings when back online
 */
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-bookings') {
        event.waitUntil(syncOfflineBookings());
    }
});

/**
 * Sync all offline bookings
 */
async function syncOfflineBookings() {
    const db = await openDatabase();
    const tx = db.transaction(BOOKING_STORE, 'readonly');
    const store = tx.objectStore(BOOKING_STORE);
    const bookings = await store.getAll();

    for (const booking of bookings) {
        if (booking.synced) continue;

        try {
            const response = await fetch(API_URL + 'sync-booking', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(booking)
            });

            if (response.ok) {
                // Mark as synced
                const writeTx = db.transaction(BOOKING_STORE, 'readwrite');
                const writeStore = writeTx.objectStore(BOOKING_STORE);
                booking.synced = true;
                writeStore.put(booking);
            }
        } catch (error) {
            console.error('[BKX PWA] Failed to sync booking:', error);
        }
    }
}

/**
 * Handle push notifications
 */
self.addEventListener('push', (event) => {
    if (!event.data) return;

    const data = event.data.json();
    const options = {
        body: data.body || '',
        icon: data.icon || '/wp-content/plugins/bkx-pwa/assets/icons/notification-icon.png',
        badge: data.badge || '/wp-content/plugins/bkx-pwa/assets/icons/badge-icon.png',
        vibrate: [100, 50, 100],
        data: data.data || {},
        actions: data.actions || []
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'BookingX', options)
    );
});

/**
 * Handle notification clicks
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const urlToOpen = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                for (const client of clientList) {
                    if (client.url === urlToOpen && 'focus' in client) {
                        return client.focus();
                    }
                }
                return clients.openWindow(urlToOpen);
            })
    );
});

/**
 * Periodic background sync
 */
self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'sync-bookings-periodic') {
        event.waitUntil(syncOfflineBookings());
    }
});

console.log('[BKX PWA] Service Worker loaded - Version: {$config['version']}');
JS;
	}

	/**
	 * Convert PHP boolean to JavaScript.
	 *
	 * @param bool $value Value.
	 * @return string
	 */
	private function bool_to_js( $value ) {
		return $value ? 'true' : 'false';
	}
}
