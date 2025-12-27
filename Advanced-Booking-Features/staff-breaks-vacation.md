# Staff Breaks & Vacation Management Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Staff Breaks & Vacation Management
**Price:** $69
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Comprehensive staff scheduling system with automated break management, vacation time tracking, time-off requests, and availability blocking. Prevents customer bookings during staff breaks, lunch hours, and approved vacation periods while maintaining service coverage.

### Value Proposition
- Prevent booking conflicts during staff breaks
- Automated lunch/break scheduling
- Self-service vacation request system
- Maintain service coverage analysis
- Reduce scheduling errors
- Improve staff work-life balance
- Track PTO (Paid Time Off) balances
- Calendar synchronization for time-off

---

## 2. Features & Requirements

### Core Features
1. **Break Management**
   - Configure break duration and timing
   - Automatic break scheduling
   - Multiple breaks per shift
   - Flexible vs. fixed break times
   - Break coverage requirements
   - Override breaks for urgent bookings
   - Break time reports

2. **Vacation/Time-Off Requests**
   - Staff submit time-off requests
   - Multi-level approval workflow
   - Partial day requests (AM/PM)
   - Recurring time-off patterns
   - Blackout dates (no time-off allowed)
   - Request status tracking
   - Email notifications

3. **PTO Balance Tracking**
   - Accrual rate configuration
   - Annual PTO allocation
   - Used vs. available tracking
   - PTO balance display for staff
   - Expiration warnings
   - Rollover policy support
   - PTO payout calculations

4. **Availability Blocking**
   - Block specific dates/times
   - Recurring unavailability
   - Emergency availability changes
   - Bulk blocking operations
   - Import/export blocked periods
   - Sync with external calendars

5. **Coverage Analysis**
   - Minimum staff requirements per service
   - Coverage gap detection
   - Alternative staff suggestions
   - Conflict warnings before approval
   - Service suspension alerts
   - Staff coverage reports

6. **Calendar Integration**
   - Display breaks on booking calendar
   - Color-coded availability
   - Staff schedule overview
   - Sync with Google Calendar
   - iCal feed for time-off
   - Outlook integration

### User Roles & Permissions
- **Admin:** Full system configuration, approve all time-off, override settings
- **Manager:** Approve time-off for managed staff, view all schedules
- **Staff:** Request time-off, set break preferences, view own PTO balance
- **Scheduler:** Manage schedules, approve requests (limited)

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP with WordPress scheduling system
- **Calendar:** FullCalendar.js for display
- **Frontend:** React for request interface
- **Calculations:** Custom availability engine
- **Notifications:** WordPress email + SMS integration
- **Sync:** CalDAV/iCal protocols

### Dependencies
- BookingX Core 2.0+
- WordPress User Roles & Capabilities
- WordPress Cron for accruals
- WordPress REST API
- Optional: Google Calendar API
- Optional: Microsoft Graph API

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/staff/breaks
GET    /wp-json/bookingx/v1/staff/{id}/breaks
DELETE /wp-json/bookingx/v1/staff/breaks/{id}
POST   /wp-json/bookingx/v1/staff/time-off-request
GET    /wp-json/bookingx/v1/staff/{id}/time-off
PUT    /wp-json/bookingx/v1/staff/time-off/{id}/approve
PUT    /wp-json/bookingx/v1/staff/time-off/{id}/reject
GET    /wp-json/bookingx/v1/staff/{id}/pto-balance
GET    /wp-json/bookingx/v1/staff/availability/{date}
GET    /wp-json/bookingx/v1/staff/coverage-analysis
POST   /wp-json/bookingx/v1/staff/block-time
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│   BookingX Core     │
│  (Booking Engine)   │
└──────────┬──────────┘
           │
           ▼
┌──────────────────────────────┐
│  Staff Availability Engine   │
│  - Calculate Available Slots │
│  - Apply Break Rules         │
│  - Check Time-Off            │
└──────────┬───────────────────┘
           │
           ├─────────────┬─────────────┬──────────────┐
           ▼             ▼             ▼              ▼
    ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐
    │  Break   │  │ Time-Off │  │   PTO    │  │ Coverage │
    │ Manager  │  │ Requests │  │ Tracker  │  │ Analysis │
    └──────────┘  └──────────┘  └──────────┘  └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\StaffSchedule;

class BreakManager {
    - create_break_rule()
    - apply_breaks_to_schedule()
    - get_staff_breaks()
    - validate_break_coverage()
    - override_break()
    - calculate_next_break()
}

class TimeOffManager {
    - submit_request()
    - approve_request()
    - reject_request()
    - cancel_request()
    - check_conflicts()
    - get_pending_requests()
    - bulk_approve()
}

class PTOTracker {
    - calculate_accrual()
    - get_balance()
    - deduct_pto()
    - add_pto_manual()
    - check_expiration()
    - apply_rollover()
    - generate_pto_report()
}

class AvailabilityEngine {
    - get_available_slots()
    - apply_break_blocks()
    - apply_time_off_blocks()
    - calculate_working_hours()
    - check_availability()
    - sync_external_calendar()
}

class CoverageAnalyzer {
    - check_minimum_coverage()
    - detect_coverage_gaps()
    - suggest_alternatives()
    - validate_time_off_request()
    - get_coverage_report()
}

class ScheduleNotifications {
    - notify_time_off_approved()
    - notify_time_off_rejected()
    - notify_break_reminder()
    - notify_coverage_gap()
    - notify_pto_expiring()
}

class CalendarSync {
    - sync_to_google_calendar()
    - sync_to_outlook()
    - generate_ical_feed()
    - import_external_blocks()
    - handle_webhook_updates()
}
```

---

## 5. Database Schema

### Table: `bkx_staff_breaks`
```sql
CREATE TABLE bkx_staff_breaks (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    break_type VARCHAR(50) NOT NULL,
    duration_minutes INT NOT NULL,
    start_time TIME,
    is_flexible TINYINT(1) DEFAULT 0,
    min_hours_before_break DECIMAL(4,2),
    max_hours_before_break DECIMAL(4,2),
    apply_days VARCHAR(50),
    require_coverage TINYINT(1) DEFAULT 0,
    is_paid TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX break_type_idx (break_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_break_instances`
```sql
CREATE TABLE bkx_staff_break_instances (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    break_rule_id BIGINT(20) UNSIGNED NOT NULL,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    break_date DATE NOT NULL,
    scheduled_start DATETIME NOT NULL,
    scheduled_end DATETIME NOT NULL,
    actual_start DATETIME,
    actual_end DATETIME,
    status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
    override_reason TEXT,
    created_at DATETIME NOT NULL,
    INDEX staff_date_idx (staff_id, break_date),
    INDEX scheduled_start_idx (scheduled_start),
    FOREIGN KEY (break_rule_id) REFERENCES bkx_staff_breaks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_time_off_requests`
```sql
CREATE TABLE bkx_staff_time_off_requests (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    request_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_partial_day TINYINT(1) DEFAULT 0,
    partial_period VARCHAR(10),
    total_days DECIMAL(4,2) NOT NULL,
    reason TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT(20) UNSIGNED,
    reviewed_at DATETIME,
    review_notes TEXT,
    pto_deducted DECIMAL(4,2),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX status_idx (status),
    INDEX date_range_idx (start_date, end_date),
    INDEX reviewed_by_idx (reviewed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_pto_balances`
```sql
CREATE TABLE bkx_staff_pto_balances (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL UNIQUE,
    pto_type VARCHAR(50) NOT NULL DEFAULT 'general',
    annual_allocation DECIMAL(5,2) NOT NULL DEFAULT 0,
    accrual_rate DECIMAL(5,4),
    accrual_period VARCHAR(20),
    current_balance DECIMAL(5,2) NOT NULL DEFAULT 0,
    pending_balance DECIMAL(5,2) DEFAULT 0,
    used_this_year DECIMAL(5,2) DEFAULT 0,
    carry_over_balance DECIMAL(5,2) DEFAULT 0,
    max_carry_over DECIMAL(5,2),
    expires_at DATE,
    last_accrual_date DATE,
    next_accrual_date DATE,
    updated_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX pto_type_idx (pto_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_pto_transactions`
```sql
CREATE TABLE bkx_staff_pto_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    amount DECIMAL(5,2) NOT NULL,
    balance_after DECIMAL(5,2) NOT NULL,
    time_off_request_id BIGINT(20) UNSIGNED,
    description TEXT,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX transaction_type_idx (transaction_type),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_availability_blocks`
```sql
CREATE TABLE bkx_staff_availability_blocks (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    block_type VARCHAR(50) NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    is_recurring TINYINT(1) DEFAULT 0,
    recurrence_rule TEXT,
    reason TEXT,
    external_event_id VARCHAR(255),
    external_calendar VARCHAR(50),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX datetime_range_idx (start_datetime, end_datetime),
    INDEX block_type_idx (block_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_coverage_requirements`
```sql
CREATE TABLE bkx_staff_coverage_requirements (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT(20) UNSIGNED,
    day_of_week TINYINT(1),
    time_slot_start TIME,
    time_slot_end TIME,
    minimum_staff INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX service_id_idx (service_id),
    INDEX day_of_week_idx (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    // Break Settings
    'enable_breaks' => true,
    'auto_schedule_breaks' => true,
    'default_break_duration' => 15,
    'lunch_break_duration' => 60,
    'min_shift_for_break' => 4,
    'min_shift_for_lunch' => 6,
    'break_buffer_minutes' => 5,

    // Time-Off Settings
    'enable_time_off_requests' => true,
    'require_approval' => true,
    'multi_level_approval' => false,
    'approval_levels' => 1,
    'min_notice_days' => 7,
    'max_consecutive_days' => 14,
    'allow_partial_days' => true,

    // PTO Settings
    'enable_pto_tracking' => true,
    'annual_pto_days' => 15,
    'accrual_method' => 'monthly',
    'accrual_start_date' => 'hire_date',
    'enable_carry_over' => true,
    'max_carry_over_days' => 5,
    'pto_expiration_months' => 12,
    'probation_period_months' => 3,

    // Coverage Settings
    'enforce_coverage_requirements' => true,
    'min_coverage_warning' => true,
    'auto_reject_coverage_conflicts' => false,
    'suggest_alternative_staff' => true,

    // Blackout Dates
    'enable_blackout_dates' => true,
    'blackout_dates' => [],
    'blackout_reason_required' => true,

    // Calendar Sync
    'enable_google_calendar_sync' => false,
    'enable_outlook_sync' => false,
    'enable_ical_feed' => true,
    'sync_direction' => 'bidirectional',

    // Notifications
    'notify_request_submitted' => true,
    'notify_request_approved' => true,
    'notify_request_rejected' => true,
    'notify_coverage_gaps' => true,
    'notify_pto_expiring' => true,
    'pto_expiry_warning_days' => 30,
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Staff Time-Off Request Form**
   - Date range picker
   - Request type selector (vacation, sick, personal)
   - Partial day option (AM/PM)
   - Reason text area
   - PTO balance display
   - Days calculation
   - Coverage conflict warning
   - Submit button

2. **Staff PTO Dashboard**
   - Current PTO balance (prominent display)
   - Pending requests
   - Upcoming time-off
   - PTO usage history
   - Accrual schedule
   - Expiration warnings
   - Request time-off button

3. **Break Schedule Display**
   - Daily break schedule
   - Next break countdown
   - Break duration
   - Override break option
   - Break history

4. **Staff Calendar View**
   - Monthly/weekly calendar
   - Color-coded availability
   - Breaks displayed
   - Approved time-off highlighted
   - Pending requests indicated
   - Working hours shown

### Backend Components

1. **Time-Off Request Management**
   - Pending requests queue
   - Approve/reject buttons
   - Request details modal
   - Coverage analysis display
   - Bulk approval
   - Filter by staff, status, date
   - Calendar view of all requests

2. **Break Configuration**
   - Staff-specific break rules
   - Break schedule generator
   - Break coverage settings
   - Override break interface
   - Break reports

3. **PTO Management**
   - Staff PTO balances table
   - Manual PTO adjustments
   - Accrual configuration per staff
   - Bulk PTO allocation
   - PTO payout calculator
   - Expiration management

4. **Coverage Analysis Dashboard**
   - Coverage gaps report
   - Staffing levels chart
   - Service coverage matrix
   - Alternative staff suggestions
   - Conflict warnings

5. **Settings Page**
   - Break rules configuration
   - Time-off policies
   - PTO accrual settings
   - Coverage requirements
   - Blackout dates management
   - Calendar sync settings

---

## 8. Security Considerations

### Data Security
- **Access Control:** Staff can only view/edit own requests
- **SQL Injection:** Use prepared statements
- **XSS Prevention:** Sanitize all input fields
- **Data Validation:** Validate date ranges, overlaps

### Authorization
- Verify staff membership before requests
- Admin/Manager capability checks for approvals
- Prevent backdating requests without permission
- Audit trail for all approvals/rejections

### Privacy
- **GDPR:** Allow staff to export their PTO data
- **Data Retention:** Configurable retention period
- **Access Logs:** Track who viewed staff schedules

---

## 9. Testing Strategy

### Unit Tests
```php
- test_break_scheduling()
- test_pto_accrual_calculation()
- test_time_off_conflict_detection()
- test_coverage_gap_detection()
- test_availability_calculation()
- test_partial_day_calculation()
- test_pto_expiration()
```

### Integration Tests
```php
- test_complete_time_off_request_workflow()
- test_approval_with_coverage_check()
- test_pto_deduction_on_approval()
- test_calendar_sync()
- test_break_blocking_bookings()
- test_blackout_date_enforcement()
```

### Test Scenarios
1. **Submit Time-Off:** Staff requests vacation
2. **Approval Workflow:** Manager approves with coverage check
3. **PTO Deduction:** Verify balance update
4. **Break Scheduling:** Auto-generate break for 8-hour shift
5. **Coverage Conflict:** Request denied due to minimum coverage
6. **Partial Day:** Request afternoon off
7. **Blackout Date:** Attempt request during blackout
8. **PTO Accrual:** Monthly accrual processing
9. **Calendar Sync:** Sync approved time-off to Google Calendar
10. **Expiration:** PTO expires after 12 months

---

## 10. Error Handling

### Error Messages (User-Facing)
```php
'insufficient_pto' => 'You don\'t have enough PTO balance. Required: {required} days, Available: {available} days.',
'coverage_conflict' => 'Your request cannot be approved due to minimum coverage requirements.',
'blackout_date' => 'Time-off requests are not allowed during this period: {reason}.',
'overlap_request' => 'You already have a time-off request for these dates.',
'min_notice' => 'Time-off requests must be submitted at least {days} days in advance.',
'max_consecutive' => 'You cannot request more than {days} consecutive days off.',
'break_conflict' => 'This time slot conflicts with your scheduled break.',
'invalid_date_range' => 'End date must be after start date.',
```

### Logging
- All time-off requests
- Approval/rejection decisions with notes
- PTO adjustments
- Coverage warnings
- Break overrides
- Calendar sync errors

---

## 11. Performance Optimization

### Caching
- Cache staff availability calculations (TTL: 15 min)
- Cache PTO balances (clear on transaction)
- Cache coverage requirements
- Cache approved time-off per staff

### Database Optimization
- Index on date ranges for quick lookups
- Composite index on staff_id + date
- Archive old requests (2+ years)
- Optimize coverage queries

### Calculation Efficiency
- Pre-calculate break times for shifts
- Batch process PTO accruals
- Lazy load availability blocks
- Queue calendar sync operations

---

## 12. Development Timeline

### Phase 1: Foundation (Week 1)
- [ ] Database schema creation
- [ ] Core class structure
- [ ] REST API endpoints
- [ ] Admin settings page

### Phase 2: Break Management (Week 2)
- [ ] Break scheduling logic
- [ ] Break application to availability
- [ ] Override functionality
- [ ] Break display UI

### Phase 3: Time-Off Requests (Week 3)
- [ ] Request submission system
- [ ] Approval workflow
- [ ] Conflict detection
- [ ] Request management UI

### Phase 4: PTO Tracking (Week 4)
- [ ] PTO balance system
- [ ] Accrual calculations
- [ ] Transaction tracking
- [ ] PTO dashboard

### Phase 5: Coverage Analysis (Week 5)
- [ ] Coverage requirements
- [ ] Gap detection
- [ ] Alternative suggestions
- [ ] Coverage reports

### Phase 6: Calendar Integration (Week 6)
- [ ] iCal feed generation
- [ ] Google Calendar sync
- [ ] Outlook integration
- [ ] Availability display

### Phase 7: Testing & QA (Week 7-8)
- [ ] Unit tests
- [ ] Integration testing
- [ ] User acceptance testing

### Phase 8: Documentation & Launch (Week 9)
- [ ] User documentation
- [ ] Admin guide
- [ ] Production release

**Total Estimated Timeline:** 9 weeks (2.25 months)

---

## 13. Maintenance & Support

### Monitoring
- Time-off request volume
- Approval/rejection rates
- Coverage gap frequency
- PTO liability tracking
- Calendar sync errors

---

## 14. Success Metrics

### Technical Metrics
- Availability calculation accuracy: 100%
- PTO balance accuracy: 100%
- Calendar sync success rate > 95%

### Business Metrics
- Time-off request turnaround < 24 hours
- Coverage conflicts < 5%
- Staff satisfaction with scheduling > 4/5
- Reduction in booking conflicts > 90%

---

## 15. Future Enhancements

### Version 2.0 Roadmap
- [ ] Shift swapping between staff
- [ ] On-call scheduling
- [ ] Overtime tracking
- [ ] Mobile app for time-off requests
- [ ] Advanced workforce analytics
- [ ] AI-powered coverage optimization

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
