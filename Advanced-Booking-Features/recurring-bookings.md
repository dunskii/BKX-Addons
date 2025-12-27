# Recurring Bookings Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Recurring Bookings
**Price:** $129
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Enable customers to book recurring appointments with customizable frequency patterns (daily, weekly, bi-weekly, monthly, custom intervals). Includes bulk payment processing, automatic booking creation, and intelligent conflict detection across recurring instances.

### Value Proposition
- Save time with automated recurring appointment scheduling
- Increase customer retention through subscription-based bookings
- Reduce administrative overhead for regular clients
- Flexible payment options (pay per booking or bulk payment)
- Smart conflict detection prevents double-booking
- Automatic reminders for entire series

---

## 2. Features & Requirements

### Core Features
1. **Recurring Pattern Configuration**
   - Daily recurrence (every N days)
   - Weekly recurrence (specific days of week)
   - Bi-weekly scheduling
   - Monthly recurrence (specific date or day of month)
   - Custom interval patterns
   - End date or occurrence count limits
   - Skip holidays option

2. **Bulk Payment Processing**
   - Pay for entire series upfront
   - Per-booking payment option
   - Subscription-based payment plans
   - Automatic payment collection
   - Deposit + recurring payments
   - Partial payment support

3. **Series Management**
   - View all instances in series
   - Edit individual occurrence
   - Cancel single instance
   - Cancel entire series
   - Reschedule series
   - Pause/resume series
   - Clone recurring pattern

4. **Conflict Detection**
   - Check availability across all instances
   - Provider availability validation
   - Resource conflict detection
   - Customer double-booking prevention
   - Alternative time suggestions

5. **Notifications & Reminders**
   - Series confirmation email
   - Individual booking reminders
   - Upcoming series notifications
   - Cancellation notifications
   - Payment reminders

### User Roles & Permissions
- **Admin:** Full series management, configure patterns
- **Manager:** Create/edit series for any provider
- **Staff:** View assigned recurring bookings
- **Customer:** Create recurring bookings, manage own series

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP 7.4+, WordPress REST API
- **Frontend:** React.js for calendar UI
- **Database:** MySQL 5.7+ with InnoDB
- **Cron:** WordPress Cron for automated tasks
- **Date Library:** Carbon PHP for date manipulation

### Dependencies
- BookingX Core 2.0+
- PHP intl extension (for date formatting)
- WordPress Cron (or system cron)
- Compatible payment gateway add-on

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/recurring-bookings
GET    /wp-json/bookingx/v1/recurring-bookings/{id}
PUT    /wp-json/bookingx/v1/recurring-bookings/{id}
DELETE /wp-json/bookingx/v1/recurring-bookings/{id}
POST   /wp-json/bookingx/v1/recurring-bookings/{id}/cancel
POST   /wp-json/bookingx/v1/recurring-bookings/{id}/pause
POST   /wp-json/bookingx/v1/recurring-bookings/{id}/resume
GET    /wp-json/bookingx/v1/recurring-bookings/{id}/instances
PUT    /wp-json/bookingx/v1/recurring-bookings/instance/{instance_id}
DELETE /wp-json/bookingx/v1/recurring-bookings/instance/{instance_id}
POST   /wp-json/bookingx/v1/recurring-bookings/validate
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│  BookingX Core      │
│  - Booking Engine   │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────┐
│  Recurring Booking Module   │
│  - Pattern Generator        │
│  - Series Manager           │
│  - Conflict Detector        │
└──────────┬──────────────────┘
           │
           ├──────────┐
           ▼          ▼
┌─────────────┐  ┌──────────────┐
│  Payment    │  │  Notification│
│  Processor  │  │  Manager     │
└─────────────┘  └──────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\RecurringBookings;

class RecurringBookingManager {
    - create_series()
    - update_series()
    - cancel_series()
    - pause_series()
    - resume_series()
    - get_series_instances()
    - validate_series()
}

class RecurrencePatternGenerator {
    - generate_daily_pattern()
    - generate_weekly_pattern()
    - generate_monthly_pattern()
    - generate_custom_pattern()
    - calculate_occurrences()
    - apply_exclusions()
    - skip_holidays()
}

class RecurringBookingInstance {
    - get_instance()
    - update_instance()
    - cancel_instance()
    - reschedule_instance()
    - is_part_of_series()
    - get_series_info()
}

class RecurringConflictDetector {
    - check_provider_availability()
    - check_resource_conflicts()
    - check_customer_conflicts()
    - get_alternative_times()
    - validate_entire_series()
}

class BulkPaymentProcessor {
    - calculate_series_total()
    - process_upfront_payment()
    - setup_recurring_payments()
    - handle_partial_payment()
    - refund_unused_instances()
}

class RecurringNotificationManager {
    - send_series_confirmation()
    - send_instance_reminders()
    - send_cancellation_notice()
    - send_payment_reminders()
    - send_series_summary()
}
```

---

## 5. Database Schema

### Table: `bkx_recurring_bookings`
```sql
CREATE TABLE bkx_recurring_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    provider_id BIGINT(20) UNSIGNED,
    series_name VARCHAR(255),
    recurrence_type ENUM('daily', 'weekly', 'biweekly', 'monthly', 'custom') NOT NULL,
    recurrence_interval INT NOT NULL DEFAULT 1,
    weekly_days VARCHAR(50) COMMENT 'Comma-separated: 0-6 (Sunday-Saturday)',
    monthly_type ENUM('date', 'day_of_week', 'last_day'),
    monthly_day INT COMMENT 'Day of month (1-31)',
    monthly_week INT COMMENT 'Week of month (1-5)',
    monthly_weekday INT COMMENT 'Day of week (0-6)',
    start_date DATE NOT NULL,
    end_date DATE,
    occurrence_count INT,
    start_time TIME NOT NULL,
    duration INT NOT NULL COMMENT 'Duration in minutes',
    status ENUM('active', 'paused', 'cancelled', 'completed') NOT NULL DEFAULT 'active',
    skip_holidays TINYINT(1) DEFAULT 0,
    payment_type ENUM('per_booking', 'bulk', 'subscription') NOT NULL,
    total_amount DECIMAL(10,2),
    paid_amount DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    cancelled_at DATETIME,
    INDEX customer_id_idx (customer_id),
    INDEX service_id_idx (service_id),
    INDEX provider_id_idx (provider_id),
    INDEX status_idx (status),
    INDEX start_date_idx (start_date),
    INDEX end_date_idx (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_recurring_booking_instances`
```sql
CREATE TABLE bkx_recurring_booking_instances (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recurring_booking_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED COMMENT 'Actual booking ID after creation',
    instance_date DATE NOT NULL,
    instance_time TIME NOT NULL,
    occurrence_number INT NOT NULL,
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled',
    is_modified TINYINT(1) DEFAULT 0 COMMENT 'Modified from original pattern',
    original_date DATE,
    original_time TIME,
    cancellation_reason TEXT,
    cancelled_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX recurring_id_idx (recurring_booking_id),
    INDEX booking_id_idx (booking_id),
    INDEX instance_date_idx (instance_date),
    INDEX status_idx (status),
    UNIQUE KEY unique_instance (recurring_booking_id, occurrence_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_recurring_exclusions`
```sql
CREATE TABLE bkx_recurring_exclusions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recurring_booking_id BIGINT(20) UNSIGNED NOT NULL,
    exclusion_date DATE NOT NULL,
    reason VARCHAR(255),
    created_at DATETIME NOT NULL,
    INDEX recurring_id_idx (recurring_booking_id),
    INDEX exclusion_date_idx (exclusion_date),
    UNIQUE KEY unique_exclusion (recurring_booking_id, exclusion_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_recurring_payments`
```sql
CREATE TABLE bkx_recurring_payments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recurring_booking_id BIGINT(20) UNSIGNED NOT NULL,
    payment_id BIGINT(20) UNSIGNED,
    payment_type ENUM('initial', 'deposit', 'installment', 'per_booking') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    due_date DATE,
    paid_date DATETIME,
    status ENUM('pending', 'processing', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    transaction_id VARCHAR(255),
    gateway VARCHAR(50),
    metadata LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX recurring_id_idx (recurring_booking_id),
    INDEX payment_id_idx (payment_id),
    INDEX status_idx (status),
    INDEX due_date_idx (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    'enable_recurring_bookings' => true,
    'max_occurrences' => 52, // Maximum number of instances
    'max_series_duration_days' => 365,
    'allow_customer_creation' => true,
    'require_approval' => false,
    'payment_options' => [
        'per_booking' => true,
        'bulk_payment' => true,
        'subscription' => true,
    ],
    'bulk_payment_discount' => 10, // Percentage
    'allow_skip_holidays' => true,
    'holiday_calendar' => 'default', // Integration with holiday calendar
    'conflict_check_enabled' => true,
    'auto_create_instances' => 'immediate', // immediate|on_confirmation|scheduled
    'instance_creation_advance_days' => 30,
    'allow_individual_modifications' => true,
    'cancellation_policy' => [
        'single_instance' => 'allowed',
        'entire_series' => 'allowed',
        'refund_policy' => 'prorated',
    ],
    'notification_settings' => [
        'series_confirmation' => true,
        'instance_reminders' => true,
        'payment_reminders' => true,
        'series_summary_frequency' => 'weekly',
    ],
    'minimum_advance_booking_hours' => 24,
    'supported_patterns' => [
        'daily' => true,
        'weekly' => true,
        'biweekly' => true,
        'monthly' => true,
        'custom' => true,
    ],
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Recurring Booking Form**
   - Service selection
   - Recurrence pattern selector
   - Start date picker
   - End date/occurrence count selector
   - Day of week selector (weekly)
   - Day of month selector (monthly)
   - Time selection
   - Duration display
   - Preview calendar with all instances
   - Conflict warnings
   - Total price calculator
   - Payment option selector

2. **Series Preview Calendar**
   - Visual calendar showing all instances
   - Highlighted booking dates
   - Conflict indicators
   - Drag-and-drop rescheduling
   - Remove individual dates
   - Add exclusions

3. **Series Management Dashboard**
   - List of all recurring series
   - Filter by status (active/paused/cancelled)
   - Quick actions (pause, resume, cancel)
   - Instance completion tracker
   - Payment status overview
   - Upcoming instances widget

4. **Instance Detail View**
   - Instance information
   - Link to full series
   - Modify instance option
   - Cancel instance option
   - Payment information
   - Customer notes

### Backend Components

1. **Recurring Bookings List**
   - Searchable table of all series
   - Filter by customer, service, provider, status
   - Bulk actions (cancel, pause, resume)
   - Export functionality
   - Series details modal

2. **Series Editor**
   - Edit recurrence pattern
   - Modify future instances
   - Add/remove exclusions
   - Adjust pricing
   - Change provider
   - Update notes

3. **Payment Management**
   - Payment schedule view
   - Process payments manually
   - Issue refunds
   - View payment history
   - Generate invoices

4. **Reports & Analytics**
   - Recurring booking revenue
   - Series completion rates
   - Cancellation analysis
   - Popular recurrence patterns
   - Customer retention metrics

---

## 8. Security Considerations

### Data Security
- **Input Validation:** Validate all recurrence pattern parameters
- **Date Validation:** Prevent invalid date ranges and patterns
- **SQL Injection:** Prepared statements for all queries
- **XSS Prevention:** Sanitize all user inputs
- **CSRF Protection:** WordPress nonces on all forms

### Authorization
- Customer can only manage own recurring bookings
- Staff can only view assigned series
- Managers can manage all series
- Admin has full access
- Capability checks for all operations

### Payment Security
- Secure storage of payment information
- PCI compliance for card storage
- Encrypted transaction data
- Audit trail for all payment operations

### Business Logic Security
- Prevent booking beyond available capacity
- Validate provider availability
- Enforce maximum occurrence limits
- Check service availability dates
- Prevent past date bookings

---

## 9. Testing Strategy

### Unit Tests
```php
- test_daily_pattern_generation()
- test_weekly_pattern_generation()
- test_monthly_pattern_generation()
- test_custom_pattern_generation()
- test_skip_holidays_functionality()
- test_occurrence_count_limit()
- test_end_date_calculation()
- test_conflict_detection()
- test_series_cancellation()
- test_instance_modification()
- test_bulk_payment_calculation()
- test_prorated_refund_calculation()
```

### Integration Tests
```php
- test_complete_series_creation_flow()
- test_series_with_payment_processing()
- test_series_modification_and_instances()
- test_conflict_detection_across_series()
- test_series_cancellation_with_refund()
- test_instance_rescheduling()
- test_notification_sending()
- test_cron_instance_creation()
```

### Test Scenarios
1. **Daily Recurrence:** Book every 2 days for 30 days
2. **Weekly Recurrence:** Tuesday and Thursday for 12 weeks
3. **Monthly Recurrence:** First Monday of month for 6 months
4. **Conflict Detection:** Overlapping series for same provider
5. **Series Cancellation:** Cancel with partial completion
6. **Instance Modification:** Reschedule single instance
7. **Payment Processing:** Bulk payment for entire series
8. **Holiday Skipping:** Skip national holidays in pattern

---

## 10. Error Handling

### Error Categories
1. **Validation Errors:** Invalid patterns, dates, or parameters
2. **Conflict Errors:** Provider/resource unavailability
3. **Payment Errors:** Failed payments, insufficient funds
4. **System Errors:** Database failures, cron issues

### Error Messages (User-Facing)
```php
'invalid_pattern' => 'The selected recurrence pattern is invalid.',
'date_range_invalid' => 'End date must be after start date.',
'max_occurrences_exceeded' => 'Maximum number of occurrences is %d.',
'provider_unavailable' => 'Provider is not available for all selected dates.',
'resource_conflict' => 'Resource conflict detected on %s.',
'payment_failed' => 'Payment processing failed. Please try again.',
'series_not_found' => 'Recurring booking series not found.',
'instance_already_completed' => 'Cannot modify completed booking instance.',
'cancellation_not_allowed' => 'Series cancellation not allowed at this time.',
```

### Logging
- All series creation/modification events
- Instance cancellations and modifications
- Payment transactions
- Conflict detection results
- Cron job execution
- Error conditions with full context

---

## 11. Cron Jobs & Automation

### Scheduled Tasks
```php
// Daily tasks
bkx_recurring_create_instances - Create upcoming instances
bkx_recurring_send_reminders - Send instance reminders
bkx_recurring_process_payments - Process due payments
bkx_recurring_check_completion - Mark completed series

// Weekly tasks
bkx_recurring_series_summary - Send weekly series summaries
bkx_recurring_cleanup_old - Clean old cancelled series

// Hourly tasks
bkx_recurring_conflict_check - Proactive conflict detection
```

### Cron Implementation
```php
public function register_cron_jobs() {
    if (!wp_next_scheduled('bkx_recurring_create_instances')) {
        wp_schedule_event(time(), 'daily', 'bkx_recurring_create_instances');
    }

    if (!wp_next_scheduled('bkx_recurring_send_reminders')) {
        wp_schedule_event(time(), 'hourly', 'bkx_recurring_send_reminders');
    }

    if (!wp_next_scheduled('bkx_recurring_process_payments')) {
        wp_schedule_event(time(), 'daily', 'bkx_recurring_process_payments');
    }
}
```

---

## 12. Performance Optimization

### Caching Strategy
- Cache recurrence patterns for active series (TTL: 1 hour)
- Cache conflict check results (TTL: 5 minutes)
- Cache customer series list (TTL: 10 minutes)
- Cache provider availability (TTL: 15 minutes)

### Database Optimization
- Indexed queries for date range lookups
- Batch instance creation (chunks of 100)
- Pagination for large series lists
- Archive completed series after 1 year
- Optimize conflict queries with proper indexes

### Background Processing
- Queue instance creation for large series
- Async conflict checking
- Background payment processing
- Batch notification sending

---

## 13. Internationalization

### Date & Time Handling
- Support for multiple date formats
- Timezone awareness for all instances
- Locale-specific calendar displays
- First day of week configuration
- Regional holiday calendars

### Languages
- Translatable strings via WordPress i18n
- RTL support
- Currency formatting per locale
- Number formatting (occurrence counts)

---

## 14. Documentation Requirements

### User Documentation
1. **Getting Started Guide**
   - How to create recurring bookings
   - Understanding recurrence patterns
   - Payment options explained
   - Managing your series

2. **Customer Guide**
   - Booking recurring appointments
   - Modifying series
   - Cancellation policies
   - Payment management

3. **Admin Guide**
   - Configuration options
   - Managing recurring bookings
   - Handling conflicts
   - Payment processing
   - Reports and analytics

### Developer Documentation
1. **API Reference**
   - REST API endpoints
   - Filter hooks
   - Action hooks
   - Data structures

2. **Integration Guide**
   - Custom recurrence patterns
   - Payment gateway integration
   - Notification customization
   - Calendar integration

---

## 15. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema creation and migration
- [ ] Basic plugin structure and activation
- [ ] Core class architecture
- [ ] Settings page UI and configuration
- [ ] Pattern generator foundation

### Phase 2: Pattern Generation (Week 3-4)
- [ ] Daily recurrence implementation
- [ ] Weekly recurrence implementation
- [ ] Monthly recurrence implementation
- [ ] Custom pattern support
- [ ] Holiday exclusion logic
- [ ] Occurrence calculation engine

### Phase 3: Series Management (Week 5-6)
- [ ] Series creation API
- [ ] Instance generation logic
- [ ] Series editing functionality
- [ ] Instance modification support
- [ ] Cancellation handling
- [ ] Pause/resume functionality

### Phase 4: Conflict Detection (Week 7)
- [ ] Provider availability checking
- [ ] Resource conflict detection
- [ ] Customer conflict prevention
- [ ] Alternative time suggestions
- [ ] Validation engine

### Phase 5: Payment Integration (Week 8-9)
- [ ] Bulk payment processing
- [ ] Per-booking payment setup
- [ ] Subscription payment support
- [ ] Refund calculation
- [ ] Payment schedule management

### Phase 6: User Interface (Week 10-11)
- [ ] Recurring booking form
- [ ] Series preview calendar
- [ ] Management dashboard
- [ ] Instance editor
- [ ] Payment interface

### Phase 7: Automation & Notifications (Week 12)
- [ ] Cron job implementation
- [ ] Automatic instance creation
- [ ] Notification system
- [ ] Email templates
- [ ] Reminder scheduling

### Phase 8: Testing & QA (Week 13-14)
- [ ] Unit test development
- [ ] Integration testing
- [ ] User acceptance testing
- [ ] Performance testing
- [ ] Security audit

### Phase 9: Documentation & Launch (Week 15-16)
- [ ] User documentation
- [ ] Admin documentation
- [ ] Developer documentation
- [ ] Video tutorials
- [ ] Beta testing
- [ ] Production release

**Total Estimated Timeline:** 16 weeks (4 months)

---

## 16. Maintenance & Support

### Update Schedule
- **Bug Fixes:** Bi-weekly releases
- **Feature Updates:** Quarterly
- **Security Patches:** As needed
- **Compatibility Updates:** With WordPress/BookingX updates

### Support Channels
- Email support
- Documentation portal
- Video tutorials
- Community forum
- Priority support for license holders

### Monitoring
- Series creation success rates
- Instance generation monitoring
- Payment processing success rates
- Conflict detection accuracy
- Cron job execution monitoring
- Error rate tracking

---

## 17. Dependencies & Requirements

### Required WordPress Plugins
- BookingX Core 2.0+

### Optional Compatible Plugins
- BookingX Payment Gateways (any)
- BookingX Calendar Sync
- BookingX Deposits
- BookingX Email Notifications Pro

### Server Requirements
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- WordPress 5.8+
- PHP intl extension
- WordPress Cron (or system cron)
- Minimum 128MB PHP memory limit
- Max execution time: 60+ seconds

---

## 18. Success Metrics

### Technical Metrics
- Series creation success rate > 99%
- Instance generation accuracy 100%
- Conflict detection accuracy > 95%
- Payment processing success > 98%
- Cron job reliability > 99.9%
- Page load time < 3 seconds

### Business Metrics
- Activation rate > 25%
- Monthly active rate > 60%
- Customer retention improvement > 20%
- Average series length > 12 weeks
- Bulk payment adoption > 40%
- Customer satisfaction > 4.5/5

---

## 19. Known Limitations

1. **Maximum Occurrences:** Limited to 52 instances per series (configurable)
2. **Pattern Complexity:** Complex patterns may require custom development
3. **Time Zone Handling:** Series uses single timezone
4. **Past Modifications:** Cannot modify past instances
5. **Payment Gateway:** Depends on gateway capabilities for subscriptions
6. **Cron Dependency:** Requires reliable cron execution
7. **Bulk Operations:** Large series (50+) may have performance impact

---

## 20. Future Enhancements

### Version 2.0 Roadmap
- [ ] AI-powered pattern suggestions
- [ ] Flexible pricing per instance
- [ ] Multi-timezone series support
- [ ] Advanced conflict resolution
- [ ] Series templates
- [ ] Customer self-service portal
- [ ] Mobile app integration
- [ ] Automatic rescheduling suggestions

### Version 3.0 Roadmap
- [ ] Predictive availability analysis
- [ ] Dynamic pricing optimization
- [ ] Advanced analytics dashboard
- [ ] Machine learning for conflict prevention
- [ ] Multi-service series booking
- [ ] Team-based recurring bookings
- [ ] Integration with external calendars

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
