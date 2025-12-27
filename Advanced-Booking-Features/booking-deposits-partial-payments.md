# Booking Deposits & Partial Payments Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Booking Deposits & Partial Payments
**Price:** $79
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Flexible payment system allowing deposit-based bookings with balance collection, installment plans, payment schedules, automatic payment processing, and comprehensive payment tracking. Reduce booking abandonment with flexible payment options while securing revenue with deposits.

### Value Proposition
- Reduce booking abandonment
- Secure revenue with deposits
- Flexible payment options
- Automated payment collection
- Reduce no-shows with financial commitment
- Cash flow management
- Installment plans for high-value services
- Payment reminders automation
- Increase booking conversion

---

## 2. Features & Requirements

### Core Features
1. **Deposit Management**
   - Fixed amount deposits
   - Percentage-based deposits
   - Per-service deposit rules
   - Minimum deposit requirements
   - Deposit at booking time
   - Refundable vs. non-refundable
   - Deposit expiration
   - Auto-apply deposit rules

2. **Partial Payment Plans**
   - Custom payment schedules
   - Fixed installment plans
   - Flexible payment dates
   - Equal/unequal installments
   - Down payment + installments
   - Pay-over-time options
   - Early payment discounts
   - Payment plan templates

3. **Balance Collection**
   - Automatic balance reminders
   - Multiple payment collection points
   - Payment before service date
   - Pay at appointment
   - Auto-charge saved cards
   - Manual payment recording
   - Balance due tracking
   - Overdue payment handling

4. **Payment Scheduling**
   - Schedule future payments
   - Recurring payment setup
   - Payment milestones
   - Due date configuration
   - Grace period settings
   - Late payment fees
   - Payment plan modification
   - Auto-payment processing

5. **Payment Tracking**
   - Payment history per booking
   - Outstanding balance display
   - Payment status tracking
   - Receipt generation
   - Payment allocation
   - Refund tracking
   - Payment timeline
   - Aging reports

6. **Automated Reminders**
   - Payment due reminders
   - Balance reminder emails/SMS
   - Overdue payment alerts
   - Payment confirmation
   - Receipt delivery
   - Failed payment notifications
   - Custom reminder schedules

7. **Cancellation & Refunds**
   - Deposit refund rules
   - Partial refund calculations
   - Cancellation fee deduction
   - Refund processing
   - Store credit option
   - Refund tracking
   - Prorated refunds

8. **Reporting & Analytics**
   - Deposit collection reports
   - Outstanding balance reports
   - Payment plan performance
   - Default rate tracking
   - Cash flow projections
   - Revenue recognition
   - Export financial data

### User Roles & Permissions
- **Admin:** Full payment management, configure all rules
- **Manager:** Process payments, view reports, approve refunds
- **Staff:** View payment status for their bookings
- **Accountant:** Financial reports, reconciliation
- **Customer:** View balance, make payments, request plans

---

## 3. Technical Specifications

### Technology Stack
- **Payment Processing:** Integration with payment gateways
- **Scheduling:** WordPress Cron + Action Scheduler
- **Calculations:** Custom payment calculation engine
- **Notifications:** Email/SMS for payment reminders
- **Reporting:** Custom reporting engine
- **Security:** PCI compliance through tokenization

### Dependencies
- BookingX Core 2.0+
- Payment Gateway add-on (Stripe, PayPal, etc.)
- Action Scheduler
- WordPress Cron
- SSL certificate (required)

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/payments/deposit
POST   /wp-json/bookingx/v1/payments/partial
GET    /wp-json/bookingx/v1/payments/balance/{booking_id}
POST   /wp-json/bookingx/v1/payments/installment
GET    /wp-json/bookingx/v1/payments/schedule/{booking_id}
POST   /wp-json/bookingx/v1/payments/process-due
POST   /wp-json/bookingx/v1/payments/refund
GET    /wp-json/bookingx/v1/payments/outstanding
GET    /wp-json/bookingx/v1/payments/reports
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│   BookingX Core     │
│  (Booking System)   │
└──────────┬──────────┘
           │
           ▼
┌──────────────────────────────┐
│   Payment Manager            │
│  - Deposit Rules             │
│  - Balance Calculation       │
│  - Schedule Management       │
└──────────┬───────────────────┘
           │
    ┌──────┴──────┬─────────┬──────────┐
    ▼             ▼         ▼          ▼
┌─────────┐ ┌──────────┐ ┌──────┐ ┌──────────┐
│ Deposit │ │Installment│ │Auto  │ │ Reminder │
│ Manager │ │  Plans   │ │ Charge│ │  System  │
└─────────┘ └──────────┘ └──────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\Payments;

class DepositManager {
    - calculate_deposit()
    - apply_deposit_rule()
    - process_deposit()
    - refund_deposit()
    - get_deposit_amount()
}

class PartialPaymentManager {
    - create_payment_plan()
    - calculate_installments()
    - process_installment()
    - modify_payment_plan()
    - get_payment_schedule()
}

class BalanceTracker {
    - calculate_balance()
    - get_outstanding_amount()
    - track_payment()
    - allocate_payment()
    - generate_statement()
}

class PaymentScheduler {
    - schedule_payment()
    - process_due_payments()
    - auto_charge_card()
    - handle_failed_payment()
    - reschedule_payment()
}

class PaymentRules {
    - get_deposit_rule()
    - validate_payment_plan()
    - apply_late_fee()
    - check_grace_period()
    - calculate_refund()
}

class PaymentReminders {
    - send_due_reminder()
    - send_overdue_alert()
    - send_receipt()
    - schedule_reminders()
}

class RefundManager {
    - calculate_refund_amount()
    - process_refund()
    - apply_cancellation_fee()
    - issue_store_credit()
}

class PaymentReports {
    - get_outstanding_report()
    - get_deposit_collection()
    - get_cash_flow_projection()
    - export_payment_data()
}
```

---

## 5. Database Schema

### Table: `bkx_booking_deposits`
```sql
CREATE TABLE bkx_booking_deposits (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    deposit_amount DECIMAL(10,2) NOT NULL,
    deposit_type VARCHAR(20) NOT NULL,
    deposit_percentage DECIMAL(5,2),
    is_refundable TINYINT(1) DEFAULT 1,
    refund_policy TEXT,
    payment_transaction_id BIGINT(20) UNSIGNED,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    paid_at DATETIME,
    refunded_at DATETIME,
    refund_amount DECIMAL(10,2),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_payment_plans`
```sql
CREATE TABLE bkx_payment_plans (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    deposit_amount DECIMAL(10,2) DEFAULT 0,
    remaining_amount DECIMAL(10,2) NOT NULL,
    number_of_installments INT NOT NULL,
    installment_frequency VARCHAR(20) NOT NULL,
    plan_status VARCHAR(20) NOT NULL DEFAULT 'active',
    start_date DATE NOT NULL,
    end_date DATE,
    auto_charge TINYINT(1) DEFAULT 0,
    payment_method_id BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX customer_id_idx (customer_id),
    INDEX status_idx (plan_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_payment_installments`
```sql
CREATE TABLE bkx_payment_installments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_plan_id BIGINT(20) UNSIGNED NOT NULL,
    installment_number INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    payment_transaction_id BIGINT(20) UNSIGNED,
    paid_at DATETIME,
    grace_period_end DATE,
    late_fee DECIMAL(10,2) DEFAULT 0,
    reminder_sent_count INT DEFAULT 0,
    last_reminder_sent DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX plan_id_idx (payment_plan_id),
    INDEX due_date_idx (due_date),
    INDEX status_idx (status),
    FOREIGN KEY (payment_plan_id) REFERENCES bkx_payment_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_booking_payments`
```sql
CREATE TABLE bkx_booking_payments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    payment_type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(255),
    gateway VARCHAR(50),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    notes TEXT,
    payment_date DATETIME,
    recorded_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX customer_id_idx (customer_id),
    INDEX transaction_idx (transaction_id),
    INDEX status_idx (status),
    INDEX payment_date_idx (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_payment_rules`
```sql
CREATE TABLE bkx_payment_rules (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(200) NOT NULL,
    rule_type VARCHAR(50) NOT NULL,
    applies_to VARCHAR(50) NOT NULL,
    entity_ids TEXT,
    deposit_type VARCHAR(20),
    deposit_amount DECIMAL(10,2),
    deposit_percentage DECIMAL(5,2),
    is_refundable TINYINT(1) DEFAULT 1,
    cancellation_fee_type VARCHAR(20),
    cancellation_fee_amount DECIMAL(10,2),
    grace_period_days INT DEFAULT 0,
    late_fee_type VARCHAR(20),
    late_fee_amount DECIMAL(10,2),
    is_active TINYINT(1) DEFAULT 1,
    priority INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX rule_type_idx (rule_type),
    INDEX applies_to_idx (applies_to),
    INDEX active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_payment_reminders`
```sql
CREATE TABLE bkx_payment_reminders (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    installment_id BIGINT(20) UNSIGNED,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    reminder_type VARCHAR(50) NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    scheduled_send_date DATETIME NOT NULL,
    sent_at DATETIME,
    channel VARCHAR(20),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX customer_id_idx (customer_id),
    INDEX scheduled_idx (scheduled_send_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_payment_refunds`
```sql
CREATE TABLE bkx_payment_refunds (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    payment_id BIGINT(20) UNSIGNED NOT NULL,
    refund_amount DECIMAL(10,2) NOT NULL,
    cancellation_fee DECIMAL(10,2) DEFAULT 0,
    refund_reason TEXT,
    refund_method VARCHAR(50),
    refund_transaction_id VARCHAR(255),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    processed_by BIGINT(20) UNSIGNED,
    processed_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX payment_id_idx (payment_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    // Deposit Settings
    'enable_deposits' => true,
    'default_deposit_type' => 'percentage',
    'default_deposit_percentage' => 50,
    'default_deposit_amount' => 0,
    'require_deposit' => true,
    'deposits_refundable' => true,

    // Payment Plans
    'enable_payment_plans' => true,
    'min_booking_amount_for_plans' => 100,
    'max_installments' => 12,
    'installment_frequencies' => ['weekly', 'biweekly', 'monthly'],
    'allow_custom_plans' => true,
    'require_approval' => false,

    // Balance Collection
    'collect_balance_before_service' => true,
    'balance_due_days_before' => 3,
    'allow_pay_at_appointment' => true,
    'auto_charge_enabled' => true,

    // Grace Periods & Fees
    'grace_period_days' => 3,
    'enable_late_fees' => true,
    'late_fee_type' => 'percentage',
    'late_fee_amount' => 5,
    'late_fee_cap' => 50,

    // Cancellation & Refunds
    'cancellation_fee_enabled' => true,
    'cancellation_fee_type' => 'percentage',
    'cancellation_fee_amount' => 10,
    'refund_processing_days' => 7,
    'offer_store_credit' => true,

    // Reminders
    'send_payment_reminders' => true,
    'reminder_days_before' => [7, 3, 1],
    'overdue_reminder_frequency' => 'daily',
    'reminder_channels' => ['email', 'sms'],

    // Automation
    'auto_process_due_payments' => true,
    'retry_failed_payments' => true,
    'retry_attempts' => 3,
    'retry_interval_hours' => 24,

    // Reporting
    'revenue_recognition_method' => 'accrual',
    'cash_flow_forecast_months' => 6,

    // Notifications
    'notify_customer_payment_received' => true,
    'notify_customer_payment_failed' => true,
    'notify_admin_overdue' => true,
    'notify_admin_threshold' => 100,
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Deposit Payment Interface**
   - Booking summary
   - Total amount display
   - Deposit amount (calculated)
   - Remaining balance
   - Payment method selector
   - Pay deposit button
   - Refund policy display

2. **Payment Plan Selector**
   - Payment plan options
   - Installment calculator
   - Payment schedule preview
   - Down payment amount
   - Auto-payment checkbox
   - Terms acceptance
   - Select plan button

3. **Balance Payment Portal**
   - Outstanding balance
   - Payment history
   - Make payment button
   - Payment plan details
   - Next payment due
   - Pay full balance option

4. **Payment Schedule View**
   - Timeline visualization
   - Installment list (past/future)
   - Payment status indicators
   - Due dates
   - Amount per installment
   - Pay now options

### Backend Components

1. **Payment Management Dashboard**
   - Outstanding balances
   - Due payments today
   - Overdue payments
   - Payment plans overview
   - Quick actions

2. **Deposit Rules Configuration**
   - Add/edit deposit rules
   - Service-specific rules
   - Rule priority
   - Test calculator

3. **Payment Plan Manager**
   - Active plans list
   - Plan details view
   - Modify plan
   - Cancel plan
   - Process manual payment

4. **Refund Processing**
   - Refund request queue
   - Refund calculator
   - Cancellation fee application
   - Process refund
   - Refund history

5. **Financial Reports**
   - Outstanding balance report
   - Deposit collection report
   - Payment plan performance
   - Cash flow projection
   - Revenue recognition
   - Export data

---

## 8. Security Considerations

### Payment Security
- **PCI Compliance:** Never store raw card data
- **Tokenization:** Use payment gateway tokens
- **SSL Required:** Enforce HTTPS
- **Encryption:** Encrypt sensitive payment data

### Access Control
- Role-based payment management
- Customer can only view/pay own bookings
- Admin approval for large refunds

---

## 9. Testing Strategy

### Unit Tests
```php
- test_deposit_calculation()
- test_installment_schedule()
- test_balance_tracking()
- test_late_fee_calculation()
- test_refund_calculation()
- test_payment_allocation()
```

### Integration Tests
```php
- test_complete_payment_plan()
- test_auto_charge_process()
- test_failed_payment_retry()
- test_refund_processing()
```

---

## 10. Development Timeline

### Phase 1: Foundation (Week 1)
- [ ] Database schema
- [ ] Core payment classes
- [ ] API endpoints

### Phase 2: Deposits (Week 2)
- [ ] Deposit calculation
- [ ] Deposit rules
- [ ] Deposit collection

### Phase 3: Payment Plans (Week 3)
- [ ] Installment calculator
- [ ] Schedule creation
- [ ] Plan management

### Phase 4: Balance & Auto-charge (Week 4)
- [ ] Balance tracking
- [ ] Auto-charge system
- [ ] Payment processing

### Phase 5: Reminders (Week 5)
- [ ] Reminder scheduling
- [ ] Multi-channel delivery
- [ ] Overdue alerts

### Phase 6: Refunds (Week 6)
- [ ] Refund calculation
- [ ] Refund processing
- [ ] Cancellation fees

### Phase 7: Reporting (Week 7)
- [ ] Financial reports
- [ ] Cash flow projections
- [ ] Export functionality

### Phase 8: Testing & Launch (Week 8-9)
- [ ] Testing
- [ ] Documentation
- [ ] Release

**Total Estimated Timeline:** 9 weeks (2.25 months)

---

## 11. Success Metrics

### Technical Metrics
- Payment processing success > 98%
- Auto-charge success rate > 95%
- Reminder delivery rate > 99%

### Business Metrics
- Booking conversion increase > 20%
- No-show reduction > 50%
- Payment collection rate > 95%
- Cash flow improvement > 30%

---

## 12. Future Enhancements

### Version 2.0 Roadmap
- [ ] Buy Now Pay Later integration
- [ ] Cryptocurrency payments
- [ ] Subscription-based bookings
- [ ] Dynamic deposit calculation
- [ ] AI-powered payment plans
- [ ] Automated collections

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
