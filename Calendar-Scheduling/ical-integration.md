# iCal Integration - Development Documentation

## 1. Overview

**Add-on Name:** iCal Integration
**Price:** $79
**Category:** Calendar & Scheduling Integrations

### Description
Enhanced iCal integration supporting two-way sync, multiple calendar formats, automated booking updates, and cross-platform compatibility.

---

## 2. Key Features

- **iCalendar Feed Generation:** Unique URLs per staff/service
- **Subscription Support:** Users can subscribe in any calendar app
- **Import Functionality:** Import bookings from .ics files
- **Export Bookings:** Individual or batch export as .ics
- **Auto-Updates:** Real-time feed updates
- **Cross-Platform:** Works with Apple Calendar, Outlook, Thunderbird, etc.
- **Recurring Events:** RRULE support
- **Alarms/Reminders:** VALARM components
- **Timezone Support:** VTIMEZONE components

---

## 3. Technical Specifications

### Technology Stack
- **Format:** RFC 5545 (iCalendar)
- **Library:** sabre/vobject PHP library
- **MIME Type:** text/calendar
- **Encoding:** UTF-8

### Database Schema
```sql
CREATE TABLE bkx_ical_feeds (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    feed_token VARCHAR(64) NOT NULL UNIQUE,
    feed_type VARCHAR(20) NOT NULL,
    filter_params TEXT,
    is_active TINYINT(1) DEFAULT 1,
    last_accessed_at DATETIME,
    created_at DATETIME NOT NULL
);

CREATE TABLE bkx_ical_imports (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    filename VARCHAR(255),
    events_imported INT DEFAULT 0,
    status VARCHAR(50),
    imported_at DATETIME
);
```

---

## 4. iCalendar Feed Structure

```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//BookingX//Booking Calendar//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH

BEGIN:VEVENT
UID:booking-123@bookingx.example.com
DTSTAMP:20251112T120000Z
DTSTART:20251115T100000Z
DTEND:20251115T110000Z
SUMMARY:Haircut - John Doe
DESCRIPTION:Service: Haircut\nCustomer: John Doe\nNotes: Regular trim
LOCATION:Main Street Salon
STATUS:CONFIRMED
SEQUENCE:1
END:VEVENT

END:VCALENDAR
```

---

## 5. Feed Generation

```php
public function generateFeed($token) {
    $feed = $this->getFeedByToken($token);
    $bookings = $this->getBookingsForFeed($feed);

    $calendar = new VObject\Component\VCalendar();

    foreach ($bookings as $booking) {
        $event = $calendar->add('VEVENT', [
            'SUMMARY' => $this->getEventTitle($booking),
            'DTSTART' => new DateTime($booking->start_time),
            'DTEND' => new DateTime($booking->end_time),
            'UID' => 'booking-' . $booking->id . '@' . $this->getDomain(),
            'DESCRIPTION' => $this->getEventDescription($booking),
        ]);

        // Add alarm (reminder)
        $event->add('VALARM', [
            'ACTION' => 'DISPLAY',
            'TRIGGER' => '-PT15M', // 15 minutes before
        ]);
    }

    return $calendar->serialize();
}
```

---

## 6. Feed URLs

```php
// Staff calendar feed
/ical/feed/{token}/staff/{staff_id}

// Service calendar feed
/ical/feed/{token}/service/{service_id}

// All bookings feed
/ical/feed/{token}/all

// Customer bookings feed
/ical/feed/{token}/customer/{customer_id}
```

---

## 7. Import Functionality

```php
public function importICS($file) {
    $vcalendar = VObject\Reader::read($file);
    $imported = 0;

    foreach ($vcalendar->VEVENT as $event) {
        $booking_data = [
            'start_time' => $event->DTSTART->getDateTime(),
            'end_time' => $event->DTEND->getDateTime(),
            'title' => (string)$event->SUMMARY,
            'description' => (string)$event->DESCRIPTION,
        ];

        $this->createBookingFromEvent($booking_data);
        $imported++;
    }

    return $imported;
}
```

---

## 8. Configuration

```php
[
    'enable_public_feeds' => false,
    'require_authentication' => true,
    'feed_cache_duration' => 3600, // 1 hour
    'include_cancelled' => false,
    'include_customer_details' => true,
    'default_reminder_minutes' => 15,
    'export_format' => 'icalendar',
]
```

---

## 9. Security

- **Token-Based Access:** Unique, unguessable feed tokens
- **Token Regeneration:** Allow users to regenerate tokens
- **Optional Authentication:** Require login for feeds
- **Rate Limiting:** Prevent feed scraping
- **PII Protection:** Option to exclude customer details

---

## 10. Development Timeline

- **Week 1:** Feed generation
- **Week 2:** Import/export functionality
- **Week 3:** UI and security
- **Week 4:** Testing & launch

**Total:** 4 weeks

---

**Status:** Ready for Development
