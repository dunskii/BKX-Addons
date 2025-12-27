# Waiting Lists & Queue Management Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Waiting Lists & Queue Management
**Price:** $69
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Automated waiting list system with priority queuing, automatic slot assignment when availability opens, cancellation waitlist, virtual queue management, and real-time notifications. Maximize booking utilization by filling last-minute cancellations and managing high-demand services.

### Value Proposition
- Reduce lost revenue from cancellations
- Fill 100% of available slots
- Automate waitlist management
- Priority customer handling
- Improve customer satisfaction
- Real-time availability notifications
- Smart queue positioning
- Analytics on demand patterns

---

## 2. Features & Requirements

### Core Features
1. **Waiting List Management**
   - Add customers to waiting list
   - Multiple waiting lists (per service/staff/date)
   - Position tracking
   - Estimated wait time calculation
   - Automatic list cleanup
   - Priority positioning
   - Bulk waitlist actions
   - Waitlist capacity limits

2. **Priority Queuing**
   - VIP customer priority
   - Loyalty tier-based priority
   - First-come-first-served option
   - Manual priority adjustment
   - Priority scoring system
   - Emergency/urgent requests
   - Business rules for priority
   - Fair queuing algorithms

3. **Automatic Slot Assignment**
   - Monitor for cancellations
   - Auto-notify waitlist customers
   - Time-limited acceptance window
      - Auto-move to next if no response
   - Batch notification option
   - Smart matching (preferences)
   - Assignment history tracking

4. **Cancellation Waitlist**
   - Specific date/time requests
   - Alternative date suggestions
   - Range-based waiting (any date in range)
   - Cancellation alert subscriptions
   - Multi-option preferences
   - Automatic booking on acceptance

5. **Virtual Queue System**
   - Real-time queue positions
   - Live wait time estimates
   - Queue joining via app/website
   - SMS queue updates
   - Position advancement notifications
   - Queue abandonment tracking
   - Re-entry handling

6. **Notifications & Alerts**
   - Slot availability alerts
   - Position advancement notices
   - Approaching turn notifications
   - Time-limited offer alerts
   - Acceptance confirmations
   - Expiration warnings
   - Custom notification rules

7. **Customer Self-Service**
   - Join waitlist online
   - View position in queue
   - Update preferences
   - Remove from waitlist
   - Accept/decline offers
   - Snooze option
   - Waitlist history

8. **Analytics & Reporting**
   - Waitlist conversion rates
   - Average wait times
   - Queue length trends
   - Demand forecasting
   - Service popularity analysis
   - No-show after waitlist
   - Revenue from waitlist bookings

### User Roles & Permissions
- **Admin:** Full waitlist management, configure priority rules
- **Manager:** Manage waitlists for services, manual assignments
- **Staff:** View waitlist for their services, notify customers
- **Customer:** Join/leave waitlist, accept offers, view position

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP with WordPress Cron
- **Queue Management:** Custom queue engine
- **Notifications:** Email, SMS, Push integration
- **Real-time Updates:** WebSockets (optional)
- **Scheduling:** Action Scheduler for automation
- **Caching:** Redis for queue state

### Dependencies
- BookingX Core 2.0+
- Action Scheduler
- WordPress Cron
- SMS provider (Twilio, etc.)
- Push notification service (optional)
- Redis (recommended for performance)

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/waitlist/join
DELETE /wp-json/bookingx/v1/waitlist/leave
GET    /wp-json/bookingx/v1/waitlist/{id}/position
PUT    /wp-json/bookingx/v1/waitlist/{id}/priority
POST   /wp-json/bookingx/v1/waitlist/{id}/notify
POST   /wp-json/bookingx/v1/waitlist/offer/{id}/accept
POST   /wp-json/bookingx/v1/waitlist/offer/{id}/decline
GET    /wp-json/bookingx/v1/waitlist/queue/{service_id}
GET    /wp-json/bookingx/v1/waitlist/analytics
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│   BookingX Core     │
│ (Cancellations/     │
│  Availability)      │
└──────────┬──────────┘
           │
           ▼
┌──────────────────────────────┐
│    Waitlist Engine           │
│  - Queue Management          │
│  - Priority Calculation      │
│  - Auto-Assignment           │
└──────────┬───────────────────┘
           │
     ┌─────┴─────┬────────┬──────────┐
     ▼           ▼        ▼          ▼
┌─────────┐ ┌────────┐ ┌──────┐ ┌──────────┐
│ Queue   │ │Priority│ │Notify│ │Analytics │
│ Manager │ │ Engine │ │System│ │ Engine   │
└─────────┘ └────────┘ └──────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\Waitlist;

class WaitlistManager {
    - add_to_waitlist()
    - remove_from_waitlist()
    - get_position()
    - update_position()
    - process_waitlist()
    - cleanup_expired()
}

class QueueEngine {
    - create_queue()
    - join_queue()
    - leave_queue()
    - advance_queue()
    - get_queue_length()
    - calculate_wait_time()
}

class PriorityCalculator {
    - calculate_priority_score()
    - apply_priority_rules()
    - reorder_queue()
    - get_priority_factors()
    - override_priority()
}

class SlotAssigner {
    - detect_availability()
    - match_customer_to_slot()
    - send_offer()
    - handle_acceptance()
    - handle_decline()
    - move_to_next()
}

class WaitlistNotifications {
    - notify_slot_available()
    - notify_position_change()
    - notify_approaching_turn()
    - notify_offer_expiring()
    - notify_removed_from_list()
}

class OfferManager {
    - create_offer()
    - expire_offer()
    - track_offer_response()
    - get_pending_offers()
    - auto_decline_expired()
}

class WaitlistAnalytics {
    - calculate_conversion_rate()
    - get_average_wait_time()
    - track_demand_patterns()
    - forecast_queue_length()
    - export_waitlist_report()
}
```

---

## 5. Database Schema

### Table: `bkx_waitlist_entries`
```sql
CREATE TABLE bkx_waitlist_entries (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    staff_id BIGINT(20) UNSIGNED,
    location_id BIGINT(20) UNSIGNED,
    requested_date DATE,
    requested_time_start TIME,
    requested_time_end TIME,
    flexible_dates TINYINT(1) DEFAULT 1,
    date_range_start DATE,
    date_range_end DATE,
    preferences TEXT,
    priority_score INT DEFAULT 0,
    queue_position INT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    estimated_wait_minutes INT,
    joined_at DATETIME NOT NULL,
    expires_at DATETIME,
    notified_count INT DEFAULT 0,
    last_notified_at DATETIME,
    INDEX customer_id_idx (customer_id),
    INDEX service_id_idx (service_id),
    INDEX status_idx (status),
    INDEX priority_idx (priority_score),
    INDEX position_idx (queue_position),
    INDEX requested_date_idx (requested_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_waitlist_offers`
```sql
CREATE TABLE bkx_waitlist_offers (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    waitlist_entry_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    available_slot_id BIGINT(20) UNSIGNED NOT NULL,
    offer_date DATE NOT NULL,
    offer_time TIME NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    staff_id BIGINT(20) UNSIGNED,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    offered_at DATETIME NOT NULL,
    responded_at DATETIME,
    accepted_booking_id BIGINT(20) UNSIGNED,
    decline_reason TEXT,
    INDEX waitlist_entry_idx (waitlist_entry_id),
    INDEX customer_id_idx (customer_id),
    INDEX status_idx (status),
    INDEX expires_at_idx (expires_at),
    FOREIGN KEY (waitlist_entry_id) REFERENCES bkx_waitlist_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_waitlist_priority_rules`
```sql
CREATE TABLE bkx_waitlist_priority_rules (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(200) NOT NULL,
    rule_type VARCHAR(50) NOT NULL,
    criteria LONGTEXT NOT NULL,
    priority_modifier INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    apply_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX rule_type_idx (rule_type),
    INDEX active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_waitlist_notifications`
```sql
CREATE TABLE bkx_waitlist_notifications (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    waitlist_entry_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    sent_at DATETIME,
    delivered_at DATETIME,
    error_message TEXT,
    created_at DATETIME NOT NULL,
    INDEX waitlist_entry_idx (waitlist_entry_id),
    INDEX customer_id_idx (customer_id),
    INDEX status_idx (status),
    INDEX type_idx (notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_virtual_queue`
```sql
CREATE TABLE bkx_virtual_queue (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id VARCHAR(64) NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    ticket_number VARCHAR(20) NOT NULL,
    position INT NOT NULL,
    estimated_wait_minutes INT,
    status VARCHAR(20) NOT NULL DEFAULT 'waiting',
    checked_in_at DATETIME NOT NULL,
    called_at DATETIME,
    served_at DATETIME,
    abandoned_at DATETIME,
    INDEX queue_id_idx (queue_id),
    INDEX service_id_idx (service_id),
    INDEX customer_id_idx (customer_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_waitlist_analytics`
```sql
CREATE TABLE bkx_waitlist_analytics (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    service_id BIGINT(20) UNSIGNED,
    total_entries INT DEFAULT 0,
    total_offers INT DEFAULT 0,
    total_accepted INT DEFAULT 0,
    total_declined INT DEFAULT 0,
    total_expired INT DEFAULT 0,
    average_wait_minutes INT,
    conversion_rate DECIMAL(5,2),
    revenue_generated DECIMAL(10,2),
    updated_at DATETIME NOT NULL,
    UNIQUE KEY unique_analytics (date, service_id),
    INDEX date_idx (date),
    INDEX service_idx (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    // General Settings
    'enable_waitlist' => true,
    'enable_virtual_queue' => true,
    'max_waitlist_size' => 50,
    'auto_cleanup_days' => 30,

    // Priority Rules
    'enable_priority_queue' => true,
    'default_priority_method' => 'fcfs',
    'vip_priority_boost' => 100,
    'loyalty_tier_boost' => [
        'platinum' => 50,
        'gold' => 30,
        'silver' => 10
    ],
    'allow_manual_priority' => true,

    // Offer Management
    'offer_acceptance_window_hours' => 2,
    'max_offers_per_customer' => 3,
    'auto_offer_next_customer' => true,
    'offer_next_count' => 3,
    'require_confirmation' => true,

    // Notifications
    'notify_on_join' => true,
    'notify_position_change' => true,
    'notify_approaching_turn' => true,
    'approaching_turn_threshold' => 3,
    'notification_channels' => ['email', 'sms'],
    'reminder_before_expiry_minutes' => 30,

    // Waitlist Behavior
    'allow_flexible_dates' => true,
    'max_date_range_days' => 30,
    'auto_match_preferences' => true,
    'allow_customer_removal' => true,
    'allow_snooze' => true,
    'snooze_duration_hours' => 24,

    // Virtual Queue
    'virtual_queue_enabled' => true,
    'queue_check_in_method' => 'qr_code',
    'show_queue_position' => true,
    'show_estimated_wait' => true,
    'abandon_after_minutes' => 60,

    // Analytics
    'track_conversion_rates' => true,
    'track_wait_times' => true,
    'track_demand_patterns' => true,
    'retention_period_days' => 365,
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Join Waitlist Form**
   - Service selector
   - Date picker (with flexible option)
   - Time preferences
   - Alternative dates
   - Preferences/notes
   - Contact method selection
   - Submit button

2. **Waitlist Status Widget**
   - Current position
   - Estimated wait time
   - Progress indicator
   - Leave waitlist button
   - Update preferences link

3. **Slot Offer Interface**
   - Available slot details
   - Service/staff information
   - Time countdown
   - Accept button
   - Decline button
   - Snooze option

4. **Virtual Queue Display**
   - Ticket number (large)
   - Current position
   - Estimated wait time
   - "Now Serving" display
   - Refresh status button

5. **Waitlist History**
   - Past waitlist entries
   - Offers received
   - Acceptance/decline status
   - Booked appointments from waitlist

### Backend Components

1. **Waitlist Management Dashboard**
   - Active waitlists overview
   - Queue length per service
   - Pending offers
   - Recent acceptances
   - Quick actions panel

2. **Queue Manager**
   - List of customers in queue
   - Position reordering (drag-drop)
   - Priority adjustment
   - Manual notify
   - Bulk actions
   - Filter/search

3. **Priority Rules Configuration**
   - Add/edit priority rules
   - Rule testing
   - Priority scoring preview
   - Rule activation toggle

4. **Offer Management**
   - Pending offers list
   - Expiring soon alerts
   - Manual offer creation
   - Response tracking
   - Auto-offer settings

5. **Analytics Dashboard**
   - Conversion rate chart
   - Average wait time graph
   - Demand heatmap
   - Service popularity
   - Revenue from waitlist
   - Export reports

---

## 8. Security Considerations

### Data Security
- **Access Control:** Customers see only own waitlist
- **Offer Validation:** Verify offer legitimacy
- **Position Integrity:** Prevent position manipulation
- **Rate Limiting:** Prevent spam joining

### Privacy
- **GDPR Compliance:** Data export/deletion
- **Opt-in Required:** Explicit consent for notifications
- **Data Minimization:** Collect only necessary info

---

## 9. Testing Strategy

### Unit Tests
```php
- test_join_waitlist()
- test_priority_calculation()
- test_position_update()
- test_offer_creation()
- test_offer_acceptance()
- test_auto_advance_queue()
- test_wait_time_estimation()
```

### Integration Tests
```php
- test_complete_waitlist_workflow()
- test_cancellation_triggers_waitlist()
- test_offer_expiration()
- test_multi_customer_priority_queue()
- test_notification_delivery()
```

### Test Scenarios
1. **Join Waitlist:** Customer joins for specific date
2. **Cancellation Occurs:** Waitlist triggered, offer sent
3. **Accept Offer:** Customer accepts, booking created
4. **Decline Offer:** Next customer notified
5. **Offer Expires:** Auto-move to next customer
6. **Priority Queue:** VIP moves ahead in line
7. **Flexible Dates:** Match any date in range
8. **Virtual Queue:** Check-in, wait, called, served

---

## 10. Development Timeline

### Phase 1: Foundation (Week 1)
- [ ] Database schema
- [ ] Core waitlist classes
- [ ] API endpoints

### Phase 2: Queue Management (Week 2)
- [ ] Join/leave waitlist
- [ ] Position tracking
- [ ] Queue ordering

### Phase 3: Priority System (Week 3)
- [ ] Priority calculation
- [ ] Priority rules
- [ ] Queue reordering

### Phase 4: Offer Management (Week 4)
- [ ] Availability detection
- [ ] Offer creation
- [ ] Acceptance/decline handling

### Phase 5: Notifications (Week 5)
- [ ] Notification system
- [ ] Multi-channel delivery
- [ ] Offer expiration alerts

### Phase 6: Virtual Queue (Week 6)
- [ ] Queue check-in
- [ ] Position display
- [ ] Queue advancement

### Phase 7: Analytics (Week 7)
- [ ] Analytics tracking
- [ ] Reports
- [ ] Dashboard

### Phase 8: Testing & Launch (Week 8-9)
- [ ] Testing
- [ ] Documentation
- [ ] Release

**Total Estimated Timeline:** 9 weeks (2.25 months)

---

## 11. Success Metrics

### Technical Metrics
- Offer delivery time < 30 seconds
- Position calculation accuracy 100%
- Notification delivery > 98%

### Business Metrics
- Waitlist conversion rate > 40%
- Booking utilization increase > 25%
- Revenue from waitlist bookings > 15%
- Customer satisfaction > 4/5

---

## 12. Future Enhancements

### Version 2.0 Roadmap
- [ ] AI-powered demand forecasting
- [ ] Dynamic pricing for waitlist offers
- [ ] Auction-based slot allocation
- [ ] Mobile app integration
- [ ] Geofencing for queue check-in
- [ ] Predictive wait time ML

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
