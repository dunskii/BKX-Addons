# Google Calendar Integration - Full - Development Documentation

## 1. Overview

**Add-on Name:** Google Calendar Integration - Full
**Price:** $149
**Category:** Calendar & Scheduling Integrations
**Version:** 1.0.0

### Description
Complete two-way sync with Google Calendar using OAuth2. Features multiple calendar support, conflict prevention, automatic booking updates, and busy time blocking.

---

## 2. Key Features

- **Two-Way Sync:** Bookings → Google Calendar, Google Calendar → Bookings
- **OAuth2 Authentication:** Secure Google account authorization
- **Multiple Calendars:** Support for multiple Google calendars per staff member
- **Conflict Prevention:** Real-time availability checking
- **Busy Time Blocking:** Automatically block times with existing events
- **Automatic Updates:** Sync booking changes, cancellations, reschedules
- **Event Details:** Include customer info, service details, notes
- **Timezone Support:** Automatic timezone conversion
- **Recurring Events:** Handle recurring Google Calendar events
- **Color Coding:** Visual categorization by service/status

---

## 3. Technical Specifications

### Technology Stack
- **API:** Google Calendar API v3
- **Auth:** Google OAuth 2.0
- **SDK:** google/apiclient PHP library
- **Protocol:** REST over HTTPS
- **Webhook:** Google Calendar Push Notifications

### Dependencies
- BookingX Core 2.0+
- PHP 7.4+
- google/apiclient-services
- SSL certificate

---

## 4. Database Schema

### Table: `bkx_google_calendar_connections`
```sql
CREATE TABLE bkx_google_calendar_connections (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    google_email VARCHAR(255) NOT NULL,
    calendar_id VARCHAR(255) NOT NULL,
    calendar_name VARCHAR(255),
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    token_expires_at DATETIME,
    sync_enabled TINYINT(1) DEFAULT 1,
    sync_direction VARCHAR(20) DEFAULT 'both',
    last_sync_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX user_idx (user_id),
    INDEX calendar_idx (calendar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `bkx_google_calendar_events`
```sql
CREATE TABLE bkx_google_calendar_events (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED,
    connection_id BIGINT(20) UNSIGNED NOT NULL,
    google_event_id VARCHAR(255) NOT NULL,
    calendar_id VARCHAR(255) NOT NULL,
    event_title VARCHAR(500),
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    is_recurring TINYINT(1) DEFAULT 0,
    recurring_event_id VARCHAR(255),
    sync_status VARCHAR(50),
    last_synced_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_idx (booking_id),
    INDEX event_idx (google_event_id),
    INDEX calendar_idx (calendar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `bkx_google_calendar_sync_log`
```sql
CREATE TABLE bkx_google_calendar_sync_log (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connection_id BIGINT(20) UNSIGNED NOT NULL,
    sync_type VARCHAR(50) NOT NULL,
    direction VARCHAR(20),
    events_processed INT DEFAULT 0,
    events_created INT DEFAULT 0,
    events_updated INT DEFAULT 0,
    events_deleted INT DEFAULT 0,
    errors TEXT,
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    INDEX connection_idx (connection_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 5. Configuration Settings

```php
[
    'google_client_id' => '',
    'google_client_secret' => '',
    'google_redirect_uri' => '',
    'default_sync_direction' => 'both|to_google|from_google',
    'auto_sync_interval' => 15, // minutes
    'conflict_resolution' => 'booking_priority|calendar_priority',
    'include_customer_details' => true,
    'block_busy_times' => true,
    'sync_cancelled_bookings' => true,
    'event_color_by' => 'service|status|staff',
    'timezone_handling' => 'auto|manual',
]
```

---

## 6. Class Structure

```php
namespace BookingX\Addons\GoogleCalendar;

class GoogleCalendarIntegration {
    - initOAuth()
    - authorizeUser()
    - handleCallback()
    - refreshAccessToken()
}

class GoogleCalendarSync {
    - syncToGoogle()
    - syncFromGoogle()
    - twoWaySync()
    - handleConflicts()
}

class GoogleCalendarEventManager {
    - createEvent()
    - updateEvent()
    - deleteEvent()
    - getEvents()
    - checkAvailability()
}

class GoogleCalendarWebhook {
    - registerWebhook()
    - handleNotification()
    - renewChannel()
}
```

---

## 7. OAuth2 Flow

### Authorization Process
1. User clicks "Connect Google Calendar"
2. Redirect to Google OAuth consent screen
3. User authorizes BookingX
4. Google redirects back with authorization code
5. Exchange code for access token + refresh token
6. Store tokens securely (encrypted)
7. Fetch available calendars
8. User selects calendar(s) to sync

### Token Refresh
```php
public function refreshAccessToken($connection) {
    $client = new Google_Client();
    $client->setClientId($this->client_id);
    $client->setClientSecret($this->client_secret);
    $client->refreshToken($connection->refresh_token);

    $new_token = $client->getAccessToken();
    $this->updateStoredToken($connection->id, $new_token);
}
```

---

## 8. Sync Logic

### Booking → Google Calendar
```php
// Trigger: booking created/updated/cancelled
public function syncBookingToGoogle($booking) {
    $connection = $this->getStaffConnection($booking->staff_id);

    $event = [
        'summary' => $this->buildEventTitle($booking),
        'description' => $this->buildEventDescription($booking),
        'start' => ['dateTime' => $booking->start_time],
        'end' => ['dateTime' => $booking->end_time],
        'colorId' => $this->getColorId($booking),
    ];

    if ($booking->google_event_id) {
        $this->updateGoogleEvent($connection, $booking->google_event_id, $event);
    } else {
        $google_event = $this->createGoogleEvent($connection, $event);
        $booking->google_event_id = $google_event->id;
        $booking->save();
    }
}
```

### Google Calendar → Booking (Busy Time Blocking)
```php
public function syncBusyTimesFromGoogle($staff_id) {
    $connection = $this->getStaffConnection($staff_id);
    $events = $this->getGoogleEvents($connection, $start, $end);

    foreach ($events as $event) {
        if (!$this->isBookingXEvent($event)) {
            $this->blockTimeSlot($staff_id, $event->start, $event->end);
        }
    }
}
```

---

## 9. Conflict Prevention

### Real-Time Availability Check
```php
public function checkAvailability($staff_id, $start, $end) {
    // Check BookingX bookings
    $bkx_conflicts = $this->checkBookingConflicts($staff_id, $start, $end);

    // Check Google Calendar
    $connection = $this->getStaffConnection($staff_id);
    $google_events = $this->getGoogleEvents($connection, $start, $end);

    return empty($bkx_conflicts) && empty($google_events);
}
```

### Conflict Resolution Strategies
1. **Booking Priority:** BookingX bookings take precedence, block Google Calendar
2. **Calendar Priority:** Google Calendar events take precedence, prevent booking
3. **Manual Review:** Flag conflicts for admin review
4. **Auto-Reschedule:** Suggest alternative times

---

## 10. Webhook/Push Notifications

### Setup Watch Channel
```php
public function setupWebhook($connection) {
    $channel = [
        'id' => uniqid('bkx_'),
        'type' => 'web_hook',
        'address' => site_url('/wp-json/bookingx/v1/google-calendar/webhook'),
        'expiration' => time() + (7 * 24 * 60 * 60) * 1000, // 7 days
    ];

    $service = $this->getCalendarService($connection);
    $watch = $service->events->watch($connection->calendar_id, new Google_Service_Calendar_Channel($channel));

    $this->storeChannelInfo($connection->id, $watch);
}
```

### Handle Webhook
```php
public function handleWebhook() {
    $channel_id = $_SERVER['HTTP_X_GOOG_CHANNEL_ID'];
    $resource_state = $_SERVER['HTTP_X_GOOG_RESOURCE_STATE'];

    if ($resource_state === 'sync') {
        // Initial sync notification
        return;
    }

    $connection = $this->getConnectionByChannelId($channel_id);
    $this->queueSync($connection->id);
}
```

---

## 11. Security Considerations

- **OAuth2 Best Practices:** Authorization code flow with PKCE
- **Token Storage:** Encrypt access/refresh tokens in database
- **Scope Minimization:** Request only calendar.events scope
- **HTTPS Required:** All communications over SSL/TLS
- **State Parameter:** CSRF protection in OAuth flow
- **Token Expiry:** Automatic refresh before expiration
- **Revocation:** Support for disconnecting calendars

---

## 12. Testing Strategy

### Unit Tests
- OAuth flow simulation
- Token refresh mechanism
- Event creation/update/deletion
- Conflict detection
- Timezone conversion

### Integration Tests
- End-to-end booking sync
- Two-way sync scenarios
- Webhook handling
- Recurring event handling
- Multiple calendar support

### Test Scenarios
1. Connect Google Calendar successfully
2. Create booking → appears in Google Calendar
3. Update booking → updates Google Calendar event
4. Cancel booking → deletes/marks Google Calendar event
5. Create Google event → blocks BookingX time
6. Delete Google event → unblocks BookingX time
7. Handle token expiry/refresh
8. Multiple staff members with different calendars

---

## 13. Performance Optimization

- **Batch API Calls:** Group requests when possible
- **Incremental Sync:** Use sync tokens for changed events only
- **Caching:** Cache calendar list, event details (TTL: 5 min)
- **Queue Processing:** Background sync jobs via WP-Cron
- **Rate Limiting:** Respect Google API quotas (10,000 requests/day)

---

## 14. User Interface

### Staff Settings Page
- "Connect Google Calendar" button
- List of connected calendars
- Sync direction selector
- Last sync time display
- Disconnect option
- Manual sync button

### Admin Dashboard
- Overview of all staff connections
- Sync status indicators
- Error log viewer
- Bulk disconnect/reconnect
- Sync statistics

---

## 15. Error Handling

### Common Errors
```php
'auth_failed' => 'Google Calendar authorization failed. Please reconnect.',
'token_expired' => 'Calendar connection expired. Reconnecting...',
'calendar_not_found' => 'Google Calendar not found. Please reauthorize.',
'quota_exceeded' => 'API quota exceeded. Sync will resume shortly.',
'network_error' => 'Unable to connect to Google Calendar. Retrying...',
'sync_conflict' => 'Sync conflict detected. Please review bookings.',
```

### Logging
- All API requests/responses (in debug mode)
- Token refresh events
- Sync operations and results
- Webhook notifications
- Errors and exceptions

---

## 16. Development Timeline

### Phase 1: OAuth & Authentication (Week 1-2)
- [ ] Google Cloud Console project setup
- [ ] OAuth2 flow implementation
- [ ] Token storage and encryption
- [ ] Authorization UI

### Phase 2: Core Sync (Week 3-4)
- [ ] Event CRUD operations
- [ ] Booking → Google sync
- [ ] Google → Booking sync
- [ ] Conflict detection

### Phase 3: Advanced Features (Week 5-6)
- [ ] Multiple calendar support
- [ ] Recurring events
- [ ] Timezone handling
- [ ] Color coding

### Phase 4: Webhooks & Real-time (Week 7)
- [ ] Push notification setup
- [ ] Webhook endpoint
- [ ] Channel renewal
- [ ] Real-time sync

### Phase 5: Testing & Polish (Week 8-9)
- [ ] Comprehensive testing
- [ ] Error handling refinement
- [ ] Performance optimization
- [ ] Documentation

**Total Timeline:** 9 weeks

---

## 17. API Quotas & Limits

- **Calendar API Quota:** 1,000,000 queries/day
- **Per User Quota:** 10,000 queries/day
- **Rate Limit:** 10 queries/second/user
- **Push Notifications:** Max 1,000,000 channels
- **Channel Expiration:** 7 days (must renew)

---

## 18. Future Enhancements

### Version 2.0
- [ ] Google Workspace integration
- [ ] Google Meet link generation
- [ ] Calendar sharing/delegation support
- [ ] Advanced recurring rules
- [ ] Smart conflict resolution
- [ ] Bulk import from Google Calendar

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Status:** Ready for Development
