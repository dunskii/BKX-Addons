# Booking Reminders System Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Booking Reminders System
**Price:** $59
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Comprehensive automated reminder system with email and SMS notifications on multiple schedules. Send appointment reminders, follow-ups, review requests, and custom messages to reduce no-shows and improve customer engagement.

### Value Proposition
- Reduce no-show rates by up to 80%
- Automate customer communication
- Multi-channel reminders (email, SMS, push)
- Flexible scheduling (before/after bookings)
- Customizable message templates
- Increase review collection
- Improve customer satisfaction
- Track delivery and engagement metrics

---

## 2. Features & Requirements

### Core Features
1. **Multiple Reminder Schedules**
   - Pre-booking reminders (24h, 2h, 30min before)
   - Booking confirmation (immediate)
   - Post-booking follow-up
   - Review request reminders
   - Custom interval reminders
   - Recurring reminder series
   - Time zone aware scheduling

2. **Multi-Channel Delivery**
   - Email notifications
   - SMS text messages
   - Push notifications (web/mobile)
   - WhatsApp messages (Business API)
   - Voice call reminders (optional)
   - In-app notifications

3. **Template Management**
   - Pre-built templates library
   - Custom template creation
   - Dynamic variable insertion
   - HTML email templates
   - Plain text SMS templates
   - Multi-language templates
   - A/B testing support

4. **Smart Scheduling**
   - Business hours enforcement
   - Time zone detection
   - Optimal send time calculation
   - Batch sending
   - Priority queue
   - Retry failed deliveries
   - Delivery window restrictions

5. **Customer Preferences**
   - Opt-in/opt-out management
   - Channel preferences (email vs SMS)
   - Frequency control
   - Do Not Disturb hours
   - Unsubscribe handling
   - Preference center

6. **Analytics & Reporting**
   - Delivery rate tracking
   - Open rate monitoring
   - Click-through tracking
   - Response rate analysis
   - No-show reduction metrics
   - ROI calculation
   - Export reports

### User Roles & Permissions
- **Admin:** Full system configuration, template management, analytics
- **Manager:** Configure reminders for managed services, view reports
- **Staff:** View reminder logs for their bookings
- **Customer:** Set reminder preferences, opt-in/out

---

## 3. Technical Specifications

### Technology Stack
- **Email:** WordPress wp_mail() + SMTP plugins
- **SMS:** Twilio API, Nexmo/Vonage, ClickSend
- **Push:** OneSignal, Firebase Cloud Messaging
- **WhatsApp:** WhatsApp Business API
- **Scheduling:** WordPress Cron + Action Scheduler
- **Tracking:** Custom pixel tracking for opens/clicks

### Dependencies
- BookingX Core 2.0+
- WordPress Cron or Action Scheduler
- SMTP plugin (for reliable email)
- Twilio/SMS provider account (for SMS)
- OneSignal account (for push notifications)
- SSL certificate (required for security)

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/reminders
GET    /wp-json/bookingx/v1/reminders/{id}
PUT    /wp-json/bookingx/v1/reminders/{id}
DELETE /wp-json/bookingx/v1/reminders/{id}
GET    /wp-json/bookingx/v1/reminders/templates
POST   /wp-json/bookingx/v1/reminders/test-send
GET    /wp-json/bookingx/v1/reminders/analytics
POST   /wp-json/bookingx/v1/reminders/preferences
GET    /wp-json/bookingx/v1/reminders/queue

// Webhook Endpoints (for delivery tracking)
POST   /wp-json/bookingx/v1/webhooks/email-opened
POST   /wp-json/bookingx/v1/webhooks/email-clicked
POST   /wp-json/bookingx/v1/webhooks/sms-delivered
POST   /wp-json/bookingx/v1/webhooks/sms-failed
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│   BookingX Core     │
│  (Booking Events)   │
└──────────┬──────────┘
           │
           ▼
┌──────────────────────────────┐
│   Reminder Scheduler         │
│   - Calculate Send Times     │
│   - Queue Messages           │
│   - Apply Preferences        │
└──────────┬───────────────────┘
           │
           ├────────────┬─────────────┬──────────────┐
           ▼            ▼             ▼              ▼
    ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐
    │  Email   │  │   SMS    │  │   Push   │  │ WhatsApp │
    │ Sender   │  │  Sender  │  │  Sender  │  │  Sender  │
    └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘
         │             │              │             │
         └─────────────┴──────────────┴─────────────┘
                       │
                       ▼
              ┌──────────────────┐
              │ Delivery Tracker │
              │ & Analytics      │
              └──────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\Reminders;

class ReminderScheduler {
    - schedule_reminder()
    - calculate_send_time()
    - queue_reminder()
    - process_queue()
    - reschedule_failed()
    - cancel_reminder()
}

class ReminderEngine {
    - create_reminder()
    - update_reminder()
    - delete_reminder()
    - get_reminder_schedule()
    - apply_customer_preferences()
    - validate_send_time()
}

class EmailSender {
    - send_email()
    - prepare_email_content()
    - apply_template()
    - track_email_open()
    - track_email_click()
    - handle_email_error()
}

class SMSSender {
    - send_sms()
    - prepare_sms_content()
    - validate_phone_number()
    - check_sms_credits()
    - handle_delivery_status()
    - calculate_sms_cost()
}

class PushSender {
    - send_push_notification()
    - prepare_push_content()
    - register_device()
    - unregister_device()
    - handle_push_error()
}

class TemplateManager {
    - get_template()
    - create_template()
    - update_template()
    - delete_template()
    - parse_variables()
    - render_template()
}

class PreferenceManager {
    - get_customer_preferences()
    - update_preferences()
    - check_opt_in_status()
    - handle_unsubscribe()
    - apply_dnd_hours()
}

class DeliveryTracker {
    - track_sent()
    - track_delivered()
    - track_opened()
    - track_clicked()
    - track_failed()
    - get_delivery_stats()
}

class ReminderAnalytics {
    - get_delivery_rates()
    - get_engagement_metrics()
    - calculate_no_show_reduction()
    - get_channel_performance()
    - export_analytics_report()
}
```

---

## 5. Database Schema

### Table: `bkx_reminder_schedules`
```sql
CREATE TABLE bkx_reminder_schedules (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_name VARCHAR(200) NOT NULL,
    reminder_type VARCHAR(50) NOT NULL,
    trigger_event VARCHAR(50) NOT NULL,
    time_offset INT NOT NULL,
    time_unit VARCHAR(20) NOT NULL,
    channels VARCHAR(100) NOT NULL,
    template_email_id BIGINT(20) UNSIGNED,
    template_sms_id BIGINT(20) UNSIGNED,
    template_push_id BIGINT(20) UNSIGNED,
    service_ids TEXT,
    is_active TINYINT(1) DEFAULT 1,
    priority INT DEFAULT 5,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX reminder_type_idx (reminder_type),
    INDEX trigger_event_idx (trigger_event),
    INDEX is_active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_reminder_queue`
```sql
CREATE TABLE bkx_reminder_queue (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    channel VARCHAR(20) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(500),
    message TEXT NOT NULL,
    scheduled_send_time DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    sent_at DATETIME,
    delivered_at DATETIME,
    opened_at DATETIME,
    clicked_at DATETIME,
    failed_at DATETIME,
    failure_reason TEXT,
    retry_count INT DEFAULT 0,
    external_id VARCHAR(255),
    created_at DATETIME NOT NULL,
    INDEX schedule_id_idx (schedule_id),
    INDEX booking_id_idx (booking_id),
    INDEX customer_id_idx (customer_id),
    INDEX status_idx (status),
    INDEX scheduled_send_idx (scheduled_send_time),
    INDEX channel_idx (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_reminder_templates`
```sql
CREATE TABLE bkx_reminder_templates (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(200) NOT NULL,
    template_type VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    subject VARCHAR(500),
    content TEXT NOT NULL,
    variables TEXT,
    language VARCHAR(10) DEFAULT 'en',
    is_default TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX template_type_idx (template_type),
    INDEX channel_idx (channel),
    INDEX language_idx (language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_customer_reminder_preferences`
```sql
CREATE TABLE bkx_customer_reminder_preferences (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL UNIQUE,
    email_enabled TINYINT(1) DEFAULT 1,
    sms_enabled TINYINT(1) DEFAULT 1,
    push_enabled TINYINT(1) DEFAULT 1,
    whatsapp_enabled TINYINT(1) DEFAULT 0,
    preferred_channel VARCHAR(20),
    dnd_start_time TIME,
    dnd_end_time TIME,
    reminder_frequency VARCHAR(20) DEFAULT 'normal',
    unsubscribed TINYINT(1) DEFAULT 0,
    unsubscribe_token VARCHAR(64),
    unsubscribed_at DATETIME,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX unsubscribe_token_idx (unsubscribe_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_reminder_analytics`
```sql
CREATE TABLE bkx_reminder_analytics (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    channel VARCHAR(20) NOT NULL,
    reminder_type VARCHAR(50) NOT NULL,
    total_scheduled INT DEFAULT 0,
    total_sent INT DEFAULT 0,
    total_delivered INT DEFAULT 0,
    total_opened INT DEFAULT 0,
    total_clicked INT DEFAULT 0,
    total_failed INT DEFAULT 0,
    total_unsubscribed INT DEFAULT 0,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY unique_analytics (date, channel, reminder_type),
    INDEX date_idx (date),
    INDEX channel_idx (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    // General Settings
    'enable_reminders' => true,
    'default_channel' => 'email',
    'fallback_channel' => 'sms',

    // Email Settings
    'from_name' => 'BookingX',
    'from_email' => 'noreply@example.com',
    'reply_to_email' => 'support@example.com',
    'enable_email_tracking' => true,

    // SMS Settings
    'sms_provider' => 'twilio',
    'twilio_account_sid' => '',
    'twilio_auth_token' => '',
    'twilio_phone_number' => '',
    'sms_character_limit' => 160,
    'enable_sms_unicode' => true,

    // Push Settings
    'push_provider' => 'onesignal',
    'onesignal_app_id' => '',
    'onesignal_api_key' => '',

    // WhatsApp Settings
    'enable_whatsapp' => false,
    'whatsapp_business_id' => '',
    'whatsapp_api_token' => '',

    // Scheduling Settings
    'respect_business_hours' => true,
    'business_hours_start' => '09:00',
    'business_hours_end' => '18:00',
    'max_retry_attempts' => 3,
    'retry_interval_minutes' => 30,
    'batch_size' => 50,

    // Default Reminder Schedules
    'confirmation_enabled' => true,
    'reminder_24h_enabled' => true,
    'reminder_2h_enabled' => true,
    'followup_enabled' => true,
    'followup_delay_hours' => 24,
    'review_request_enabled' => true,
    'review_request_delay_days' => 3,

    // Compliance
    'require_opt_in' => true,
    'show_unsubscribe_link' => true,
    'gdpr_compliant' => true,
    'tcpa_compliant' => true,

    // Analytics
    'track_opens' => true,
    'track_clicks' => true,
    'retention_days' => 365,
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Customer Preference Center**
   - Channel toggles (Email, SMS, Push, WhatsApp)
   - Preferred channel selector
   - Frequency control
   - Do Not Disturb hours
   - Unsubscribe option
   - Save preferences button

2. **Reminder Preview (Customer)**
   - Upcoming reminders list
   - Scheduled send times
   - Channel indicators
   - Opt-out links

### Backend Components

1. **Reminder Schedule Manager**
   - List of all reminder schedules
   - Add new schedule button
   - Edit/delete actions
   - Enable/disable toggles
   - Test send functionality
   - Template selection
   - Service assignment

2. **Template Editor**
   - Template type selector
   - Channel tabs (Email/SMS/Push)
   - WYSIWYG editor (for email)
   - Plain text editor (for SMS)
   - Variable insertion tool
   - Preview functionality
   - Test send option
   - Save template button

3. **Reminder Queue Management**
   - Pending reminders list
   - Filter by status, channel, date
   - Search by customer/booking
   - Manual send/cancel options
   - Retry failed reminders
   - Queue statistics

4. **Analytics Dashboard**
   - Delivery rate chart
   - Open rate chart
   - Click rate chart
   - Channel comparison
   - No-show reduction metric
   - Date range selector
   - Export report button

5. **Settings Page**
   - Email configuration
   - SMS provider setup
   - Push notification config
   - Default schedule settings
   - Business hours
   - Compliance options

---

## 8. Security Considerations

### Data Security
- **Unsubscribe Tokens:** Unique, non-guessable tokens
- **Phone Number Encryption:** Encrypt stored phone numbers
- **API Keys:** Encrypted storage of provider API keys
- **Email Tracking:** Secure pixel implementation
- **XSS Prevention:** Sanitize template content

### Compliance
- **GDPR:** Right to opt-out, data export, deletion
- **TCPA:** Obtain consent for SMS, respect opt-outs
- **CAN-SPAM:** Include physical address, unsubscribe link
- **CASL:** Canadian anti-spam compliance

### Rate Limiting
- Prevent sending spam
- Enforce provider rate limits
- Throttle batch sending
- Detect abuse patterns

---

## 9. Testing Strategy

### Unit Tests
```php
- test_send_time_calculation()
- test_template_variable_parsing()
- test_customer_preference_application()
- test_business_hours_enforcement()
- test_unsubscribe_processing()
- test_retry_logic()
```

### Integration Tests
```php
- test_email_sending()
- test_sms_sending()
- test_push_notification()
- test_complete_reminder_workflow()
- test_tracking_pixel()
- test_webhook_delivery_status()
```

### Test Scenarios
1. **Booking Confirmation:** Send immediate confirmation
2. **24h Reminder:** Send 24 hours before booking
3. **SMS Reminder:** Send SMS 2 hours before
4. **Follow-up:** Send follow-up after completion
5. **Review Request:** Send review request 3 days after
6. **Opt-out Respected:** Don't send to opted-out customer
7. **Business Hours:** Delay send until business hours
8. **Failed Retry:** Retry failed email delivery
9. **Template Variables:** Correctly replace all variables
10. **Tracking:** Track email open and link click

---

## 10. Error Handling

### Error Messages (User-Facing)
```php
'invalid_phone' => 'Please provide a valid phone number for SMS reminders.',
'sms_credits_low' => 'SMS sending is temporarily unavailable.',
'unsubscribed' => 'You have unsubscribed from reminders.',
'invalid_email' => 'Please provide a valid email address.',
'template_missing' => 'Reminder template not found.',
```

### Logging
- All reminder sends (success/failure)
- Delivery status updates
- Opt-out actions
- Template changes
- Provider API errors
- Tracking events

---

## 11. Performance Optimization

### Queue Processing
- Batch process reminders
- Use Action Scheduler for reliability
- Prioritize by send time
- Parallel channel sending
- Background processing

### Caching
- Cache templates (clear on update)
- Cache customer preferences
- Cache provider API tokens

### Database
- Index on scheduled_send_time
- Archive sent reminders (90+ days)
- Optimize analytics queries

---

## 12. Development Timeline

### Phase 1: Foundation (Week 1)
- [ ] Database schema
- [ ] Core classes
- [ ] REST API endpoints
- [ ] Settings page

### Phase 2: Email System (Week 2)
- [ ] Email sending logic
- [ ] Template engine
- [ ] Tracking implementation
- [ ] Email queue processing

### Phase 3: SMS System (Week 3)
- [ ] Twilio integration
- [ ] SMS sending logic
- [ ] Delivery tracking
- [ ] Character limit handling

### Phase 4: Scheduling (Week 4)
- [ ] Reminder scheduler
- [ ] Queue management
- [ ] Business hours logic
- [ ] Retry mechanism

### Phase 5: Templates & Preferences (Week 5)
- [ ] Template manager
- [ ] Variable parsing
- [ ] Preference center
- [ ] Opt-out handling

### Phase 6: Analytics (Week 6)
- [ ] Delivery tracking
- [ ] Analytics dashboard
- [ ] Reports generation
- [ ] Export functionality

### Phase 7: Testing & Launch (Week 7-8)
- [ ] Unit tests
- [ ] Integration testing
- [ ] Documentation
- [ ] Production release

**Total Estimated Timeline:** 8 weeks (2 months)

---

## 13. Success Metrics

### Technical Metrics
- Email delivery rate > 95%
- SMS delivery rate > 98%
- Queue processing time < 5 min
- Tracking accuracy > 99%

### Business Metrics
- No-show reduction > 50%
- Email open rate > 40%
- SMS response rate > 20%
- Customer satisfaction > 4.5/5
- Opt-out rate < 2%

---

## 14. Future Enhancements

### Version 2.0 Roadmap
- [ ] Voice call reminders
- [ ] Multi-language auto-detection
- [ ] AI-optimized send times
- [ ] Advanced A/B testing
- [ ] RCS messaging support
- [ ] Two-way SMS conversations

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
