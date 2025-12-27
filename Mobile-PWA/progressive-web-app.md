# Progressive Web App (PWA) Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Progressive Web App (PWA)
**Price:** $149
**Category:** Mobile & Progressive Web Apps
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+
**Browser Support:** Chrome 90+, Firefox 88+, Safari 14+, Edge 90+

### Description
App-like web experience with offline functionality, push notifications, and mobile-optimized interfaces. Transform BookingX into a Progressive Web App that users can install on their devices and use like a native application, complete with offline booking capabilities and push notifications.

### Value Proposition
- No app store approval needed
- Instant updates without app store delays
- Single codebase for all platforms
- 90% smaller than native apps
- SEO-friendly and discoverable
- Lower development and maintenance costs
- Cross-platform compatibility
- Offline-first architecture
- Native-like user experience

---

## 2. Features & Requirements

### Core Features
1. **PWA Foundation**
   - Service Worker implementation
   - Web App Manifest
   - HTTPS requirement enforcement
   - Installable on all platforms
   - Splash screens
   - App icons (multiple sizes)
   - Standalone display mode
   - Theme customization

2. **Offline Functionality**
   - Service Worker caching strategies
   - IndexedDB for data storage
   - Background sync API
   - Offline booking queue
   - Cached service catalog
   - Offline page fallback
   - Cache versioning and updates
   - Network-first/Cache-first strategies

3. **Push Notifications**
   - Web Push API integration
   - Firebase Cloud Messaging (FCM)
   - Notification permission management
   - Rich notifications with actions
   - Background notification handling
   - Notification click handling
   - Silent push for data sync
   - Badge API support

4. **Mobile-Optimized UI**
   - Touch-optimized controls
   - Responsive design patterns
   - Pull-to-refresh
   - Swipe gestures
   - Bottom sheets
   - Native-like animations
   - Loading skeletons
   - Optimistic UI updates

5. **Performance Features**
   - Lazy loading images
   - Code splitting
   - Resource preloading
   - HTTP/2 server push
   - Brotli compression
   - Critical CSS inlining
   - Intersection Observer
   - Web Workers for heavy tasks

6. **Device Integration**
   - Add to Home Screen
   - Share API
   - Credential Management API
   - Payment Request API
   - Geolocation API
   - Web Share Target
   - Media Capture API
   - Vibration API

### Browser Compatibility
- **Chrome/Edge:** Full support for all features
- **Firefox:** Full support (except iOS)
- **Safari:** Partial support (no Background Sync, limited Push)
- **Opera:** Full support
- **Samsung Internet:** Full support

### User Roles & Permissions
- **Customer:** Full booking interface, offline capabilities
- **Staff:** Schedule view, booking management
- **Manager:** Reporting, staff oversight
- **Admin:** Configuration, analytics, manifest management

---

## 3. Technical Specifications

### Technology Stack
- **Service Worker:** Workbox 7.0+
- **Build Tool:** Webpack 5+ or Vite 4+
- **Frontend Framework:** Vanilla JS, React, or Vue 3
- **Storage:** IndexedDB (Dexie.js or idb)
- **Push Service:** Firebase Cloud Messaging or OneSignal
- **PWA Builder:** PWA Builder CLI or Workbox
- **Testing:** Lighthouse CI, PWA Builder
- **Manifest Generator:** PWA Manifest Generator

### Dependencies
- BookingX Core 2.0+
- SSL Certificate (required)
- Modern browser with service worker support
- Firebase project (for push notifications)
- Web server with HTTPS

### API Integration Points
```javascript
// BookingX PWA API endpoints
GET    /wp-json/bookingx/v2/pwa/manifest
GET    /wp-json/bookingx/v2/pwa/config
POST   /wp-json/bookingx/v2/pwa/push-subscribe
DELETE /wp-json/bookingx/v2/pwa/push-unsubscribe
POST   /wp-json/bookingx/v2/pwa/sync
GET    /wp-json/bookingx/v2/pwa/offline-data
POST   /wp-json/bookingx/v2/pwa/analytics
GET    /wp-json/bookingx/v2/pwa/version
```

### Service Worker Architecture
```javascript
// Service Worker Lifecycle
self.addEventListener('install', (event) => {
  // Cache essential assets
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ESSENTIAL_ASSETS);
    })
  );
});

self.addEventListener('activate', (event) => {
  // Clean up old caches
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

self.addEventListener('fetch', (event) => {
  // Routing strategies
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────────────────────┐
│         User's Device               │
│  ┌────────────────────────────┐    │
│  │    Browser Application      │    │
│  │  ┌──────────────────────┐  │    │
│  │  │   BookingX PWA UI    │  │    │
│  │  └──────────┬───────────┘  │    │
│  │             │               │    │
│  │  ┌──────────▼───────────┐  │    │
│  │  │   Service Worker     │  │    │
│  │  │  - Caching           │  │    │
│  │  │  - Background Sync   │  │    │
│  │  │  - Push Handling     │  │    │
│  │  └──────────┬───────────┘  │    │
│  │             │               │    │
│  │  ┌──────────▼───────────┐  │    │
│  │  │   Cache Storage      │  │    │
│  │  └──────────────────────┘  │    │
│  │             │               │    │
│  │  ┌──────────▼───────────┐  │    │
│  │  │   IndexedDB          │  │    │
│  │  └──────────────────────┘  │    │
│  └─────────────┬───────────────┘    │
└────────────────┼────────────────────┘
                 │ HTTPS
                 ▼
┌─────────────────────────────────────┐
│      WordPress Backend              │
│  ┌────────────────────────────┐    │
│  │    BookingX Core Plugin    │    │
│  ├────────────────────────────┤    │
│  │    PWA Module              │    │
│  │  - Manifest Generator      │    │
│  │  - Push Notification Mgr   │    │
│  │  - Offline Sync Handler    │    │
│  └────────────────────────────┘    │
└─────────────────┬───────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│    Firebase Cloud Messaging         │
│    (Push Notification Service)      │
└─────────────────────────────────────┘
```

### Caching Strategies
```javascript
// Workbox caching strategies

// 1. Network First (API calls)
workbox.routing.registerRoute(
  /\/wp-json\/bookingx\/v2\/(bookings|availability)/,
  new workbox.strategies.NetworkFirst({
    cacheName: 'api-cache',
    networkTimeoutSeconds: 5,
    plugins: [
      new workbox.expiration.ExpirationPlugin({
        maxEntries: 50,
        maxAgeSeconds: 5 * 60, // 5 minutes
      }),
    ],
  })
);

// 2. Cache First (static assets)
workbox.routing.registerRoute(
  /\.(?:png|jpg|jpeg|svg|gif|webp)$/,
  new workbox.strategies.CacheFirst({
    cacheName: 'image-cache',
    plugins: [
      new workbox.expiration.ExpirationPlugin({
        maxEntries: 100,
        maxAgeSeconds: 30 * 24 * 60 * 60, // 30 days
      }),
    ],
  })
);

// 3. Stale While Revalidate (CSS/JS)
workbox.routing.registerRoute(
  /\.(?:js|css)$/,
  new workbox.strategies.StaleWhileRevalidate({
    cacheName: 'static-resources',
  })
);

// 4. Network Only (POST requests)
workbox.routing.registerRoute(
  ({ request }) => request.method === 'POST',
  new workbox.strategies.NetworkOnly()
);
```

### File Structure
```
bookingx-pwa/
├── public/
│   ├── manifest.json
│   ├── service-worker.js
│   ├── offline.html
│   └── icons/
│       ├── icon-72x72.png
│       ├── icon-96x96.png
│       ├── icon-128x128.png
│       ├── icon-144x144.png
│       ├── icon-152x152.png
│       ├── icon-192x192.png
│       ├── icon-384x384.png
│       └── icon-512x512.png
├── src/
│   ├── js/
│   │   ├── app.js
│   │   ├── pwa-handler.js
│   │   ├── offline-manager.js
│   │   ├── push-manager.js
│   │   ├── sync-manager.js
│   │   └── db.js
│   ├── css/
│   │   ├── main.css
│   │   └── mobile.css
│   └── components/
│       ├── booking-form.js
│       ├── service-list.js
│       └── offline-indicator.js
└── workbox-config.js
```

---

## 5. Database Schema

### IndexedDB Structure
```javascript
// IndexedDB stores using Dexie.js
const db = new Dexie('BookingXDB');

db.version(1).stores({
  // Services cache
  services: '++id, server_id, name, category_id, updated_at',

  // Staff cache
  staff: '++id, server_id, name, services, updated_at',

  // Bookings cache
  bookings: '++id, server_id, user_id, service_id, booking_date, status, sync_status, created_at',

  // Sync queue
  syncQueue: '++id, entity_type, action, data, status, retry_count, created_at',

  // Settings
  settings: 'key, value, updated_at',

  // Push subscriptions
  subscriptions: '++id, endpoint, auth, p256dh, created_at',

  // Cache metadata
  cacheMetadata: 'key, timestamp, version'
});

// Service store schema
{
  id: number,
  server_id: number,
  name: string,
  description: string,
  duration: number,
  price: number,
  category_id: number,
  image_url: string,
  staff_ids: number[],
  updated_at: timestamp
}

// Booking store schema
{
  id: number,
  server_id: number | null,
  user_id: number,
  service_id: number,
  staff_id: number,
  booking_date: string,
  start_time: string,
  end_time: string,
  status: string,
  notes: string,
  sync_status: 'pending' | 'synced' | 'conflict',
  created_at: timestamp,
  updated_at: timestamp
}

// Sync queue schema
{
  id: number,
  entity_type: string,
  action: 'create' | 'update' | 'delete',
  data: object,
  status: 'pending' | 'processing' | 'completed' | 'failed',
  retry_count: number,
  error: string,
  created_at: timestamp
}
```

### WordPress Backend Tables

#### Table: `bkx_pwa_subscriptions`
```sql
CREATE TABLE bkx_pwa_subscriptions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    endpoint VARCHAR(500) NOT NULL UNIQUE,
    auth_key VARCHAR(255) NOT NULL,
    p256dh_key VARCHAR(255) NOT NULL,
    user_agent TEXT,
    platform VARCHAR(50),
    subscribed_at DATETIME NOT NULL,
    last_sent DATETIME,
    status VARCHAR(20) DEFAULT 'active',
    INDEX user_id_idx (user_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table: `bkx_pwa_notifications`
```sql
CREATE TABLE bkx_pwa_notifications (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    subscription_id BIGINT(20) UNSIGNED,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    icon VARCHAR(500),
    badge VARCHAR(500),
    data_json TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    sent_at DATETIME,
    clicked_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX user_id_idx (user_id),
    INDEX status_idx (status),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table: `bkx_pwa_sync_log`
```sql
CREATE TABLE bkx_pwa_sync_log (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    sync_type VARCHAR(50) NOT NULL,
    items_synced INT NOT NULL,
    items_failed INT DEFAULT 0,
    sync_duration INT,
    user_agent TEXT,
    synced_at DATETIME NOT NULL,
    INDEX user_id_idx (user_id),
    INDEX synced_at_idx (synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Web App Manifest
```json
{
  "name": "BookingX - Appointment Booking",
  "short_name": "BookingX",
  "description": "Book appointments quickly and easily",
  "start_url": "/booking/?utm_source=pwa",
  "display": "standalone",
  "orientation": "portrait-primary",
  "background_color": "#ffffff",
  "theme_color": "#4f46e5",
  "icons": [
    {
      "src": "/icons/icon-72x72.png",
      "sizes": "72x72",
      "type": "image/png",
      "purpose": "any"
    },
    {
      "src": "/icons/icon-96x96.png",
      "sizes": "96x96",
      "type": "image/png",
      "purpose": "any"
    },
    {
      "src": "/icons/icon-128x128.png",
      "sizes": "128x128",
      "type": "image/png",
      "purpose": "any"
    },
    {
      "src": "/icons/icon-144x144.png",
      "sizes": "144x144",
      "type": "image/png",
      "purpose": "any"
    },
    {
      "src": "/icons/icon-152x152.png",
      "sizes": "152x152",
      "type": "image/png",
      "purpose": "any"
    },
    {
      "src": "/icons/icon-192x192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/icons/icon-384x384.png",
      "sizes": "384x384",
      "type": "image/png",
      "purpose": "any"
    },
    {
      "src": "/icons/icon-512x512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ],
  "screenshots": [
    {
      "src": "/screenshots/home.png",
      "sizes": "540x720",
      "type": "image/png",
      "platform": "wide",
      "label": "Home Screen"
    },
    {
      "src": "/screenshots/booking.png",
      "sizes": "540x720",
      "type": "image/png",
      "platform": "wide",
      "label": "Booking Screen"
    }
  ],
  "categories": ["business", "lifestyle"],
  "shortcuts": [
    {
      "name": "New Booking",
      "short_name": "Book",
      "description": "Create a new booking",
      "url": "/booking/new",
      "icons": [
        {
          "src": "/icons/shortcut-booking.png",
          "sizes": "96x96"
        }
      ]
    },
    {
      "name": "My Bookings",
      "short_name": "Bookings",
      "description": "View your bookings",
      "url": "/booking/my-bookings",
      "icons": [
        {
          "src": "/icons/shortcut-list.png",
          "sizes": "96x96"
        }
      ]
    }
  ],
  "share_target": {
    "action": "/booking/share",
    "method": "POST",
    "enctype": "multipart/form-data",
    "params": {
      "title": "name",
      "text": "description",
      "url": "link"
    }
  }
}
```

### WordPress Admin Settings
```php
[
    'pwa_enabled' => true,
    'pwa_name' => 'BookingX',
    'pwa_short_name' => 'BookingX',
    'pwa_description' => 'Book appointments quickly and easily',
    'pwa_theme_color' => '#4f46e5',
    'pwa_background_color' => '#ffffff',
    'pwa_start_url' => '/booking/',
    'pwa_display' => 'standalone', // standalone, fullscreen, minimal-ui
    'pwa_orientation' => 'portrait-primary',

    // Offline settings
    'offline_mode_enabled' => true,
    'offline_cache_strategy' => 'network-first',
    'offline_cache_duration' => 86400, // 24 hours
    'offline_max_bookings' => 10,

    // Push notifications
    'push_enabled' => true,
    'push_public_key' => '',
    'push_private_key' => '',
    'firebase_config' => [
        'apiKey' => '',
        'authDomain' => '',
        'projectId' => '',
        'messagingSenderId' => '',
        'appId' => '',
    ],

    // Performance
    'enable_service_worker' => true,
    'cache_version' => '1.0.0',
    'precache_assets' => true,
    'lazy_load_images' => true,
    'enable_background_sync' => true,

    // Features
    'install_prompt_enabled' => true,
    'install_prompt_delay' => 3, // visits
    'offline_page_enabled' => true,
    'share_target_enabled' => true,
]
```

---

## 7. User Interface Requirements

### PWA-Specific UI Components

#### 1. Install Banner
```html
<!-- Custom install prompt -->
<div id="install-banner" class="pwa-install-banner hidden">
  <div class="install-content">
    <img src="/icons/icon-96x96.png" alt="BookingX">
    <div class="install-text">
      <h3>Install BookingX</h3>
      <p>Add to home screen for quick access</p>
    </div>
  </div>
  <div class="install-actions">
    <button id="install-button" class="btn-primary">Install</button>
    <button id="install-dismiss" class="btn-text">Not now</button>
  </div>
</div>
```

#### 2. Offline Indicator
```html
<!-- Offline status banner -->
<div id="offline-indicator" class="status-banner hidden">
  <svg class="icon-offline">...</svg>
  <span>You're offline. Changes will sync when connected.</span>
</div>
```

#### 3. Update Available
```html
<!-- Update notification -->
<div id="update-banner" class="update-banner hidden">
  <span>A new version is available!</span>
  <button id="update-button" class="btn-update">Update Now</button>
</div>
```

#### 4. Sync Status
```html
<!-- Sync indicator -->
<div id="sync-status" class="sync-status">
  <svg class="spinner">...</svg>
  <span>Syncing <span id="sync-count">3</span> items...</span>
</div>
```

### Mobile-Optimized Patterns
1. **Touch-Friendly Buttons:** Minimum 44×44px touch targets
2. **Pull-to-Refresh:** Native-like refresh gesture
3. **Bottom Navigation:** Easy thumb access on mobile
4. **Swipe Gestures:** Swipe to delete, swipe between views
5. **Bottom Sheets:** Modal dialogs from bottom
6. **Loading Skeletons:** Content placeholders during load
7. **Sticky Headers:** Fixed navigation during scroll
8. **Safe Area Insets:** iPhone notch compatibility

---

## 8. Security Considerations

### HTTPS Requirement
- **Mandatory:** Service Workers only work over HTTPS
- **Development:** localhost exception for testing
- **Production:** Valid SSL certificate required
- **Mixed Content:** Block HTTP resources

### Service Worker Security
```javascript
// Service Worker security best practices
self.addEventListener('fetch', (event) => {
  // Only cache same-origin requests
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  // Validate request integrity
  if (event.request.mode !== 'navigate' &&
      event.request.mode !== 'same-origin') {
    return;
  }

  // Handle request securely
  event.respondWith(handleRequest(event.request));
});
```

### Data Protection
- **Encrypted Storage:** Sensitive data in IndexedDB
- **Token Management:** Secure JWT storage
- **XSS Prevention:** Sanitize all user input
- **CSP Headers:** Content Security Policy
- **Permissions:** Request only necessary permissions

### Push Notification Security
```javascript
// Verify push subscription
const subscription = await registration.pushManager.subscribe({
  userVisibleOnly: true,
  applicationServerKey: urlBase64ToUint8Array(PUBLIC_VAPID_KEY)
});

// Send encrypted to server
await fetch('/api/push/subscribe', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    subscription,
    timestamp: Date.now(),
    signature: await signData(subscription)
  })
});
```

---

## 9. Testing Strategy

### Lighthouse Testing
```bash
# Run Lighthouse CI
npx lighthouse-ci autorun \
  --collect.url=https://example.com \
  --collect.numberOfRuns=5 \
  --assert.preset=lighthouse:recommended

# PWA scoring criteria
- Performance: > 90
- Accessibility: > 90
- Best Practices: > 90
- SEO: > 90
- PWA: 100
```

### Service Worker Testing
```javascript
// Service Worker unit tests
describe('Service Worker', () => {
  test('should install and activate', async () => {
    const registration = await navigator.serviceWorker.register('/sw.js');
    expect(registration.installing).toBeTruthy();
  });

  test('should cache essential assets', async () => {
    const cache = await caches.open(CACHE_NAME);
    const requests = await cache.keys();
    expect(requests.length).toBeGreaterThan(0);
  });

  test('should handle offline requests', async () => {
    // Simulate offline
    self.offline = true;
    const response = await fetch('/api/services');
    expect(response.ok).toBeTruthy();
  });
});
```

### Offline Testing
```javascript
// Simulate offline scenarios
describe('Offline Functionality', () => {
  test('should queue bookings when offline', async () => {
    await offlineManager.setOffline(true);

    const booking = await createBooking(bookingData);
    const queue = await syncQueue.getAll();

    expect(queue.length).toBe(1);
    expect(booking.sync_status).toBe('pending');
  });

  test('should sync when back online', async () => {
    await offlineManager.setOffline(false);
    await syncManager.sync();

    const queue = await syncQueue.getAll();
    expect(queue.length).toBe(0);
  });
});
```

### Browser Testing Matrix
- **Desktop:** Chrome, Firefox, Edge, Safari (macOS)
- **Mobile:** Chrome Android, Safari iOS, Samsung Internet
- **Tablet:** iPad Safari, Android Chrome
- **Network:** 3G, 4G, WiFi, Offline

---

## 10. Error Handling

### Offline Error Handling
```javascript
class OfflineManager {
  async handleOfflineRequest(request) {
    try {
      // Try network first
      const response = await fetch(request);
      return response;
    } catch (error) {
      // Network failed, try cache
      const cached = await caches.match(request);
      if (cached) return cached;

      // Show offline page for navigation requests
      if (request.mode === 'navigate') {
        return caches.match('/offline.html');
      }

      // Return custom offline response
      return new Response(
        JSON.stringify({
          error: 'offline',
          message: 'You are currently offline',
          cached: false
        }),
        {
          status: 503,
          statusText: 'Service Unavailable',
          headers: { 'Content-Type': 'application/json' }
        }
      );
    }
  }
}
```

### Sync Error Handling
```javascript
// Background sync with retry logic
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-bookings') {
    event.waitUntil(
      syncBookings().catch((error) => {
        // Log error
        console.error('Sync failed:', error);

        // Retry with exponential backoff
        if (event.lastChance) {
          // Notify user of permanent failure
          self.registration.showNotification('Sync Failed', {
            body: 'Please check your bookings and try again',
            tag: 'sync-error',
            requireInteraction: true
          });
        }
      })
    );
  }
});
```

### User-Facing Error Messages
```javascript
const ERROR_MESSAGES = {
  'offline': 'You are offline. Your changes will be saved and synced when connection is restored.',
  'sync_failed': 'Failed to sync your booking. Please try again.',
  'cache_full': 'Local storage is full. Please clear some cached data.',
  'service_worker_failed': 'App update failed. Please refresh the page.',
  'push_permission_denied': 'Push notifications are blocked. Enable them in browser settings.',
  'install_failed': 'Installation failed. Please try again or use the browser menu.',
};
```

---

## 11. Push Notifications Implementation

### Web Push Setup
```javascript
// Push notification manager
class PushManager {
  async initialize() {
    // Check support
    if (!('serviceWorker' in navigator)) {
      throw new Error('Service Workers not supported');
    }

    if (!('PushManager' in window)) {
      throw new Error('Push notifications not supported');
    }

    // Register service worker
    const registration = await navigator.serviceWorker.register('/sw.js');

    // Request permission
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      throw new Error('Permission denied');
    }

    // Subscribe to push
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: this.urlBase64ToUint8Array(PUBLIC_KEY)
    });

    // Send to server
    await this.sendSubscriptionToServer(subscription);

    return subscription;
  }

  async sendSubscriptionToServer(subscription) {
    const response = await fetch('/wp-json/bookingx/v2/pwa/push-subscribe', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${this.getToken()}`
      },
      body: JSON.stringify({
        endpoint: subscription.endpoint,
        keys: {
          auth: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('auth')))),
          p256dh: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('p256dh'))))
        }
      })
    });

    return response.json();
  }

  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
      .replace(/\\-/g, '+')
      .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }
}
```

### Notification Handling
```javascript
// Service Worker push event handler
self.addEventListener('push', (event) => {
  const data = event.data.json();

  const options = {
    body: data.body,
    icon: data.icon || '/icons/icon-192x192.png',
    badge: '/icons/badge-72x72.png',
    image: data.image,
    vibrate: [200, 100, 200],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: data.id,
      url: data.url
    },
    actions: [
      {
        action: 'view',
        title: 'View Booking',
        icon: '/icons/action-view.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss',
        icon: '/icons/action-dismiss.png'
      }
    ],
    requireInteraction: data.requireInteraction || false,
    tag: data.tag || 'booking-notification',
    renotify: true
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  if (event.action === 'view') {
    event.waitUntil(
      clients.openWindow(event.notification.data.url)
    );
  } else if (event.action === 'dismiss') {
    // Just close, already done above
  } else {
    // Default action - open app
    event.waitUntil(
      clients.openWindow('/booking/my-bookings')
    );
  }
});
```

---

## 12. Performance Optimization

### Critical Rendering Path
```html
<!-- Inline critical CSS -->
<style>
  /* Critical above-the-fold styles */
  body { margin: 0; font-family: system-ui; }
  .header { height: 60px; background: #4f46e5; }
  /* ... */
</style>

<!-- Preload key resources -->
<link rel="preload" href="/fonts/main.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/api/services" as="fetch" crossorigin>

<!-- Preconnect to APIs -->
<link rel="preconnect" href="https://api.bookingx.com">
<link rel="dns-prefetch" href="https://api.bookingx.com">
```

### Code Splitting
```javascript
// Dynamic imports for routes
const routes = {
  '/booking': () => import('./pages/booking.js'),
  '/services': () => import('./pages/services.js'),
  '/profile': () => import('./pages/profile.js'),
};

// Load on demand
async function navigateTo(path) {
  const loadPage = routes[path];
  if (loadPage) {
    const page = await loadPage();
    page.render();
  }
}
```

### Image Optimization
```html
<!-- Responsive images -->
<img
  srcset="
    /images/service-small.webp 480w,
    /images/service-medium.webp 768w,
    /images/service-large.webp 1200w
  "
  sizes="(max-width: 480px) 480px,
         (max-width: 768px) 768px,
         1200px"
  src="/images/service-medium.webp"
  alt="Service"
  loading="lazy"
  decoding="async"
>
```

### Performance Budget
```javascript
// webpack.config.js
module.exports = {
  performance: {
    maxAssetSize: 250000, // 250KB
    maxEntrypointSize: 250000,
    hints: 'error',
  },
};
```

### Metrics Targets
- **First Contentful Paint:** < 1.5s
- **Largest Contentful Paint:** < 2.5s
- **Time to Interactive:** < 3.5s
- **Cumulative Layout Shift:** < 0.1
- **First Input Delay:** < 100ms
- **Total Blocking Time:** < 300ms

---

## 13. Internationalization

### Multi-language Support
```javascript
// Language detection and loading
class I18nManager {
  constructor() {
    this.currentLang = this.detectLanguage();
    this.translations = {};
  }

  detectLanguage() {
    // Check saved preference
    const saved = localStorage.getItem('lang');
    if (saved) return saved;

    // Check browser language
    const browserLang = navigator.language.split('-')[0];
    return ['en', 'es', 'fr', 'de'].includes(browserLang) ? browserLang : 'en';
  }

  async loadTranslations(lang) {
    const response = await fetch(`/lang/${lang}.json`);
    this.translations[lang] = await response.json();
    return this.translations[lang];
  }

  t(key) {
    return this.translations[this.currentLang]?.[key] || key;
  }
}
```

### RTL Support
```css
/* RTL styles */
[dir="rtl"] {
  direction: rtl;
  text-align: right;
}

[dir="rtl"] .booking-form {
  flex-direction: row-reverse;
}

/* Logical properties (direction-agnostic) */
.container {
  margin-inline-start: 20px;
  padding-inline-end: 20px;
  border-inline-start: 1px solid #ccc;
}
```

---

## 14. Documentation Requirements

### User Documentation
1. **Installation Guide**
   - How to install PWA on different devices
   - iOS Safari installation steps
   - Android Chrome installation steps
   - Desktop installation (Chrome, Edge)

2. **Offline Usage Guide**
   - What works offline
   - How to sync when back online
   - Managing cached data

3. **Troubleshooting**
   - Push notifications not working
   - Installation issues
   - Sync problems
   - Cache clearing

### Developer Documentation
1. **Setup Guide**
   - Environment configuration
   - SSL certificate setup
   - Service worker registration
   - Push notification setup (VAPID keys)

2. **Architecture Guide**
   - Service Worker architecture
   - Caching strategies
   - Offline sync flow
   - IndexedDB schema

3. **API Reference**
   - PWA endpoints
   - Manifest configuration
   - Push notification API
   - Sync API

---

## 15. Development Timeline

### Phase 1: Foundation (Weeks 1-2)
- [ ] Service Worker implementation
- [ ] Web App Manifest creation
- [ ] HTTPS setup verification
- [ ] Basic caching strategy
- [ ] IndexedDB setup

### Phase 2: Offline Functionality (Weeks 3-4)
- [ ] Offline detection
- [ ] Cache management
- [ ] Offline booking queue
- [ ] Background sync implementation
- [ ] Conflict resolution

### Phase 3: Push Notifications (Week 5)
- [ ] Firebase setup
- [ ] Push subscription flow
- [ ] Notification handling
- [ ] Notification actions
- [ ] Backend notification sender

### Phase 4: UI Optimization (Weeks 6-7)
- [ ] Mobile-responsive design
- [ ] Touch-optimized controls
- [ ] Loading states
- [ ] Offline indicators
- [ ] Install prompts

### Phase 5: Performance (Week 8)
- [ ] Code splitting
- [ ] Image optimization
- [ ] Critical CSS
- [ ] Resource preloading
- [ ] Lighthouse optimization

### Phase 6: Testing (Week 9)
- [ ] Service Worker tests
- [ ] Offline scenario testing
- [ ] Push notification testing
- [ ] Cross-browser testing
- [ ] Performance testing

### Phase 7: Documentation & Launch (Week 10)
- [ ] User documentation
- [ ] Developer documentation
- [ ] Admin configuration UI
- [ ] Beta testing
- [ ] Production deployment

**Total Estimated Timeline:** 10 weeks (2.5 months)

---

## 16. Maintenance & Support

### Update Strategy
- **Service Worker:** Versioned updates with user prompt
- **Cache:** Automatic invalidation on version change
- **Dependencies:** Monthly security updates
- **Browser Support:** Track 2 versions back

### Monitoring
- Service Worker activation rates
- Installation rates
- Offline usage analytics
- Push notification delivery rates
- Performance metrics
- Error tracking

---

## 17. Success Metrics

### Technical Metrics
- Lighthouse PWA score: 100/100
- Service Worker activation: > 95%
- Cache hit rate: > 80%
- Offline functionality: > 90%
- Push delivery rate: > 95%

### Business Metrics
- Installation rate: > 15%
- Active PWA users: > 40%
- Offline bookings: > 5%
- Push notification CTR: > 20%
- User satisfaction: > 4.5/5

---

## 18. Known Limitations

1. **iOS Limitations**
   - No Background Sync API
   - Push notifications require iOS 16.4+
   - Limited storage quota
   - Install prompt restrictions

2. **Storage Limitations**
   - IndexedDB quota varies by browser
   - Cache storage limits
   - No guaranteed persistence

3. **Browser Support**
   - Safari has partial PWA support
   - IE11 not supported
   - Some features require recent browser versions

---

## 19. Future Enhancements

### Version 2.0 Roadmap
- [ ] Advanced offline capabilities
- [ ] Background fetch for large files
- [ ] Periodic background sync
- [ ] Web Share Target API level 2
- [ ] Badging API
- [ ] File System Access API
- [ ] Contact Picker API
- [ ] Web Bluetooth for check-ins

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development
