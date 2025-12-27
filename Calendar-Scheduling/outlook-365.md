# Outlook 365 Integration - Development Documentation

## 1. Overview

**Add-on Name:** Outlook 365 Integration
**Price:** $129
**Category:** Calendar & Scheduling Integrations

### Description
Microsoft Graph API integration with two-way sync, Teams integration, Exchange support, and comprehensive OAuth2 authentication.

---

## 2. Key Features

- Two-way calendar synchronization
- Microsoft Teams meeting links
- Exchange Server support
- Multiple calendar support
- Azure AD authentication (OAuth2)
- Busy time blocking
- Conflict prevention
- Timezone conversion
- Shared mailbox support
- Office 365 group calendars

---

## 3. Technical Specifications

### Technology Stack
- **API:** Microsoft Graph API v1.0
- **Auth:** Microsoft Identity Platform (OAuth 2.0)
- **SDK:** microsoft/microsoft-graph PHP SDK
- **Webhooks:** Microsoft Graph subscriptions

### Database Schema
```sql
CREATE TABLE bkx_outlook_connections (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    microsoft_email VARCHAR(255),
    calendar_id VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    subscription_id VARCHAR(255),
    subscription_expires_at DATETIME,
    created_at DATETIME NOT NULL
);

CREATE TABLE bkx_outlook_events (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED,
    connection_id BIGINT(20) UNSIGNED NOT NULL,
    outlook_event_id VARCHAR(255) NOT NULL,
    teams_meeting_url VARCHAR(500),
    sync_status VARCHAR(50),
    created_at DATETIME NOT NULL
);
```

---

## 4. Configuration

```php
[
    'microsoft_client_id' => '',
    'microsoft_client_secret' => '',
    'microsoft_tenant_id' => 'common',
    'enable_teams_meetings' => true,
    'sync_direction' => 'both',
    'auto_create_teams_link' => true,
]
```

---

## 5. Class Structure

```php
namespace BookingX\Addons\Outlook365;

class OutlookIntegration {
    - initOAuth()
    - getAuthorizationUrl()
    - handleCallback()
}

class OutlookCalendarSync {
    - syncToOutlook()
    - syncFromOutlook()
    - createEvent()
    - updateEvent()
    - deleteEvent()
}

class TeamsIntegration {
    - createTeamsMeeting()
    - getMeetingDetails()
}
```

---

## 6. Microsoft Graph API Endpoints

```
POST /me/events
GET /me/events
PATCH /me/events/{id}
DELETE /me/events/{id}
POST /me/calendar/events
POST /me/onlineMeetings (Teams)
POST /subscriptions (webhooks)
```

---

## 7. Teams Meeting Integration

```php
public function createTeamsMeeting($booking) {
    $meeting = [
        'startDateTime' => $booking->start_time,
        'endDateTime' => $booking->end_time,
        'subject' => $booking->service_name,
    ];

    $response = $this->graph->createRequest('POST', '/me/onlineMeetings')
        ->attachBody($meeting)
        ->execute();

    return $response->getJoinWebUrl();
}
```

---

## 8. Development Timeline

- **Week 1-2:** OAuth & Graph API setup
- **Week 3-4:** Calendar sync implementation
- **Week 5:** Teams integration
- **Week 6-7:** Testing & launch

**Total:** 7 weeks

---

**Status:** Ready for Development
