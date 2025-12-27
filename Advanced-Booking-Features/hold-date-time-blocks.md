# Hold Date/Time Blocks Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Hold Date/Time Blocks
**Price:** $59
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Allow customers to temporarily reserve time slots with countdown timers before completing payment. Reduce booking conflicts, create urgency, and improve conversion rates with smart hold functionality. Perfect for high-demand services and peak booking times.

### Value Proposition
- Temporarily reserve time slots during checkout
- Countdown timer creates urgency
- Prevent double-booking during checkout
- Configurable hold duration
- Automatic release of expired holds
- Queue management for popular times
- Real-time availability updates
- Improved booking completion rates

---

## 2. Features & Requirements

### Core Features
1. **Time Slot Hold Management**
   - Reserve slot on booking initiation
   - Configurable hold duration (5-30 minutes)
   - Countdown timer display
   - Automatic release on expiration
   - Manual release option
   - Hold extension capability
   - Priority hold for members

2. **Visual Countdown Timer**
   - Real-time countdown display
   - Warning at final minute
   - Expiration notifications
   - Mobile-friendly timer
   - Audio/visual alerts
   - Customizable timer styles

3. **Automatic Release System**
   - Release expired holds
   - Return slots to availability
   - Notify waiting customers
   - Queue promotion
   - Release confirmation
   - Cleanup old holds

4. **Hold Queue Management**
   - Multiple customers can queue
   - Position tracking
   - Automatic promotion
   - Queue notifications
   - Priority handling
   - Queue analytics

5. **Hold Analytics**
   - Hold conversion rates
   - Average hold duration
   - Expiration rates
   - Popular hold times
   - Performance metrics

### User Roles & Permissions
- **Admin:** Configure hold settings, view analytics
- **Manager:** Manage holds, release manually
- **Staff:** View holds, limited management
- **Customer:** Initiate holds, see countdown

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP 7.4+, WordPress REST API
- **Frontend:** JavaScript for countdown timer
- **Real-time:** AJAX polling for updates
- **Database:** MySQL 5.7+ with InnoDB
- **Cron:** WordPress Cron for cleanup

### Dependencies
- BookingX Core 2.0+
- WordPress Transient API
- JavaScript (vanilla or jQuery)
- WordPress Cron

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/holds/create
GET    /wp-json/bookingx/v1/holds/{id}
DELETE /wp-json/bookingx/v1/holds/{id}
POST   /wp-json/bookingx/v1/holds/{id}/extend
POST   /wp-json/bookingx/v1/holds/{id}/release

GET    /wp-json/bookingx/v1/holds/check-availability
GET    /wp-json/bookingx/v1/holds/active
POST   /wp-json/bookingx/v1/holds/cleanup

GET    /wp-json/bookingx/v1/hold-queue/{slot_id}
POST   /wp-json/bookingx/v1/hold-queue/join
DELETE /wp-json/bookingx/v1/hold-queue/leave
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│  BookingX Core      │
│  - Availability     │
└──────────┬──────────┘
           │
           ▼
┌────────────────────────────┐
│  Hold Management Module    │
│  - Hold Manager            │
│  - Timer Controller        │
│  - Release Engine          │
└──────────┬─────────────────┘
           │
           ├──────────┬──────────┐
           ▼          ▼          ▼
┌──────────┐ ┌──────────┐ ┌──────────┐
│  Queue   │ │Countdown │ │ Cleanup  │
│ Manager  │ │  Timer   │ │ Service  │
└──────────┘ └──────────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\HoldBlocks;

class HoldManager {
    - create_hold()
    - get_hold()
    - release_hold()
    - extend_hold()
    - validate_hold()
    - is_slot_held()
    - get_active_holds()
}

class TimerController {
    - start_timer()
    - get_remaining_time()
    - is_expired()
    - send_expiry_warning()
    - handle_expiration()
}

class ReleaseEngine {
    - auto_release_expired()
    - manual_release()
    - release_on_completion()
    - release_on_cancellation()
    - cleanup_old_holds()
}

class HoldQueueManager {
    - add_to_queue()
    - remove_from_queue()
    - get_queue_position()
    - promote_from_queue()
    - notify_queue_members()
}

class AvailabilityChecker {
    - check_slot_availability()
    - exclude_held_slots()
    - get_available_slots()
    - calculate_real_time_availability()
}

class HoldAnalytics {
    - get_conversion_rate()
    - get_average_hold_duration()
    - get_expiration_rate()
    - get_popular_hold_times()
    - export_analytics()
}
```

---

## 5. Database Schema

### Table: `bkx_time_holds`
```sql
CREATE TABLE bkx_time_holds (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED,
    session_id VARCHAR(255) NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    provider_id BIGINT(20) UNSIGNED,
    hold_date DATE NOT NULL,
    hold_time TIME NOT NULL,
    duration INT NOT NULL COMMENT 'Service duration in minutes',
    hold_duration INT NOT NULL COMMENT 'How long to hold in minutes',
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    extended_count INT DEFAULT 0,
    status ENUM('active', 'completed', 'expired', 'released') NOT NULL DEFAULT 'active',
    booking_id BIGINT(20) UNSIGNED COMMENT 'Set when booking is completed',
    release_reason VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    INDEX customer_id_idx (customer_id),
    INDEX session_id_idx (session_id),
    INDEX service_id_idx (service_id),
    INDEX provider_id_idx (provider_id),
    INDEX hold_datetime_idx (hold_date, hold_time),
    INDEX expires_at_idx (expires_at),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_hold_queue`
```sql
CREATE TABLE bkx_hold_queue (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED,
    session_id VARCHAR(255) NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    provider_id BIGINT(20) UNSIGNED,
    desired_date DATE NOT NULL,
    desired_time TIME NOT NULL,
    queue_position INT NOT NULL,
    priority INT DEFAULT 0,
    status ENUM('waiting', 'notified', 'promoted', 'expired', 'left') NOT NULL DEFAULT 'waiting',
    notified_at DATETIME,
    notification_expires_at DATETIME,
    joined_at DATETIME NOT NULL,
    promoted_at DATETIME,
    INDEX customer_id_idx (customer_id),
    INDEX service_id_idx (service_id),
    INDEX desired_datetime_idx (desired_date, desired_time),
    INDEX status_idx (status),
    INDEX queue_position_idx (queue_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_hold_extensions`
```sql
CREATE TABLE bkx_hold_extensions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hold_id BIGINT(20) UNSIGNED NOT NULL,
    extended_minutes INT NOT NULL,
    new_expires_at DATETIME NOT NULL,
    extended_at DATETIME NOT NULL,
    INDEX hold_id_idx (hold_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    'hold_settings' => [
        'enable_holds' => true,
        'default_hold_duration' => 15, // minutes
        'min_hold_duration' => 5,
        'max_hold_duration' => 30,
        'allow_hold_extension' => true,
        'max_extensions' => 2,
        'extension_duration' => 5, // minutes
    ],

    'timer_settings' => [
        'show_countdown_timer' => true,
        'timer_position' => 'top', // top|bottom|floating
        'warning_threshold_seconds' => 60,
        'enable_audio_alert' => false,
        'enable_visual_alert' => true,
        'timer_color_normal' => '#28a745',
        'timer_color_warning' => '#ffc107',
        'timer_color_critical' => '#dc3545',
    ],

    'release_settings' => [
        'auto_release_expired' => true,
        'cleanup_interval_minutes' => 5,
        'keep_expired_holds_hours' => 24,
        'notify_on_release' => true,
        'allow_manual_release' => true,
    ],

    'queue_settings' => [
        'enable_queue' => true,
        'max_queue_size' => 10,
        'queue_notification_expires_minutes' => 30,
        'auto_promote_from_queue' => true,
        'priority_for_members' => true,
    ],

    'availability_settings' => [
        'exclude_holds_from_availability' => true,
        'show_hold_indicator' => true,
        'refresh_availability_seconds' => 30,
    ],

    'notification_settings' => [
        'hold_created_notification' => true,
        'expiry_warning_notification' => true,
        'warning_before_seconds' => 120,
        'hold_expired_notification' => true,
        'queue_promotion_notification' => true,
    ],

    'restrictions' => [
        'max_holds_per_customer' => 3,
        'max_holds_per_session' => 1,
        'require_login_for_holds' => false,
        'allow_concurrent_holds' => false,
    ],
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Countdown Timer Widget**
   - Real-time countdown display
   - Minutes and seconds
   - Visual progress bar
   - Color changes (green → yellow → red)
   - Warning message at 1 minute
   - Expiration alert
   - Extend button (if allowed)

2. **Hold Confirmation**
   - Slot hold notification
   - Time remaining display
   - Proceed to payment button
   - Release hold button
   - Hold details summary

3. **Queue Join Interface**
   - Slot unavailable message
   - Join queue button
   - Current queue position
   - Estimated wait time
   - Notification preferences

4. **Availability Calendar**
   - Visual hold indicators
   - Real-time updates
   - Held slots shown differently
   - Auto-refresh on release

5. **Mobile Timer**
   - Sticky timer on mobile
   - Push notifications
   - Compact display
   - Quick actions

### Backend Components

1. **Active Holds Dashboard**
   - List of active holds
   - Time remaining for each
   - Customer information
   - Slot details
   - Release button
   - Extend button

2. **Hold Analytics**
   - Conversion rate chart
   - Average hold duration
   - Expiration rate
   - Popular times
   - Queue statistics

3. **Queue Management**
   - Queue list by slot
   - Position management
   - Manual promotion
   - Notification status
   - Queue analytics

4. **Settings Page**
   - Hold duration configuration
   - Timer customization
   - Queue settings
   - Notification preferences

---

## 8. Security Considerations

### Data Security
- **Session Security:** Secure session handling
- **SQL Injection:** Prepared statements
- **XSS Prevention:** Sanitize inputs
- **Rate Limiting:** Prevent hold spam

### Authorization
- Validate customer/session ownership
- Prevent hold manipulation
- Secure release operations
- Queue access control

### Business Logic Security
- Validate slot availability
- Prevent overlapping holds
- Verify hold ownership
- Audit trail for operations
- Prevent abuse (too many holds)

---

## 9. Testing Strategy

### Unit Tests
```php
- test_create_hold()
- test_hold_expiration()
- test_extend_hold()
- test_release_hold()
- test_queue_addition()
- test_queue_promotion()
- test_availability_check()
- test_cleanup_expired()
```

### Integration Tests
```php
- test_complete_hold_to_booking_flow()
- test_hold_expiration_and_release()
- test_queue_notification_workflow()
- test_concurrent_hold_prevention()
- test_timer_countdown_accuracy()
```

### Test Scenarios
1. **Normal Hold Flow:** Create hold, complete booking
2. **Hold Expiration:** Let hold expire, verify release
3. **Hold Extension:** Extend hold before expiration
4. **Queue Join:** Slot held, join queue, get promoted
5. **Concurrent Holds:** Prevent same slot multiple holds
6. **Timer Accuracy:** Verify countdown accuracy
7. **Auto Cleanup:** Verify expired holds cleanup

---

## 10. Error Handling

### Error Messages (User-Facing)
```php
'slot_already_held' => 'This time slot is temporarily unavailable.',
'hold_limit_reached' => 'You have reached the maximum number of holds.',
'hold_expired' => 'Your hold has expired. Please select a new time.',
'extension_not_allowed' => 'Hold extension is not available.',
'extension_limit_reached' => 'Maximum extensions reached.',
'queue_full' => 'The queue for this time slot is full.',
'invalid_hold' => 'Invalid hold reference.',
```

### Logging
- Hold creation/release
- Extensions
- Expirations
- Queue operations
- Cleanup operations
- Error conditions

---

## 11. Cron Jobs & Automation

### Scheduled Tasks
```php
// Every 5 minutes
bkx_cleanup_expired_holds - Release expired holds
bkx_promote_queue - Promote from queue to holds
bkx_send_expiry_warnings - Send expiration warnings

// Hourly
bkx_cleanup_old_holds - Archive old expired holds
```

---

## 12. Performance Optimization

### Caching Strategy
- Cache available slots (TTL: 30 seconds)
- Cache active holds count (TTL: 1 minute)
- Real-time updates via AJAX

### Database Optimization
- Indexed queries for holds
- Optimize expiration checks
- Efficient queue queries
- Archive old data

---

## 13. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Core hold management
- [ ] Settings page

### Phase 2: Timer Implementation (Week 3)
- [ ] Countdown timer
- [ ] AJAX updates
- [ ] Visual alerts

### Phase 3: Release & Queue (Week 4-5)
- [ ] Auto-release system
- [ ] Queue management
- [ ] Notifications

### Phase 4: Testing & Launch (Week 6)
- [ ] Testing
- [ ] Documentation
- [ ] Production release

**Total Estimated Timeline:** 6 weeks (1.5 months)

---

## 14. Success Metrics

### Technical Metrics
- Hold creation success > 99%
- Timer accuracy 100%
- Release execution < 5 seconds
- Page load impact < 100ms

### Business Metrics
- Hold conversion rate > 70%
- Average hold duration < 10 minutes
- Expiration rate < 20%
- Queue promotion rate > 50%

---

## 15. Dependencies & Requirements

### Required
- BookingX Core 2.0+

### Server Requirements
- PHP 7.4+
- MySQL 5.7+
- WordPress 5.8+
- WordPress Cron

---

## 16. Known Limitations

1. **Timer Accuracy:** Dependent on client-side clock
2. **Concurrent Holds:** Server-side race conditions possible
3. **Queue Size:** Limited to prevent performance issues
4. **Mobile Timers:** May pause when app backgrounded

---

## 17. Future Enhancements

### Version 2.0 Roadmap
- [ ] WebSocket for real-time updates
- [ ] Mobile app push notifications
- [ ] Advanced queue algorithms
- [ ] AI-powered hold duration optimization
- [ ] Blockchain-based hold verification

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
