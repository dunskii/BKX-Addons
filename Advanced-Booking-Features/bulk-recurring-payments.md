# Bulk & Recurring Payments Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Bulk & Recurring Payments
**Price:** $89
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Enable customers to prepay for multiple bookings or set up automatic recurring payment schedules. Perfect for subscription services, memberships, maintenance contracts, and regular service appointments. Includes flexible payment plans, automated billing, and comprehensive payment tracking.

### Value Proposition
- Prepay for multiple appointments at once
- Automated recurring billing
- Flexible payment schedules (weekly, monthly, quarterly)
- Improved cash flow with upfront payments
- Reduced payment processing overhead
- Customer convenience with auto-pay
- Subscription-based revenue model
- Credit-based prepayment system

---

## 2. Features & Requirements

### Core Features
1. **Bulk Payment Options**
   - Pay for multiple bookings upfront
   - Bundle discount for bulk payments
   - Credit-based prepayment system
   - Flexible booking redemption
   - Unused credit refunds
   - Credit expiration management
   - Transfer credits between services

2. **Recurring Payment Schedules**
   - Weekly, bi-weekly, monthly, quarterly, annual
   - Custom interval support
   - Fixed amount or variable billing
   - Automatic retry on failure
   - Payment schedule preview
   - Pause/resume subscriptions
   - Proration handling

3. **Payment Plans**
   - Installment payments
   - Deposit + scheduled payments
   - Subscription tiers
   - Trial periods
   - Setup fees
   - Cancellation fees
   - Early termination handling

4. **Automated Billing**
   - Automatic charge on schedule
   - Payment method on file
   - Retry logic for failed payments
   - Dunning management
   - Invoice generation
   - Payment receipts
   - Billing notifications

5. **Credit Management**
   - Credit balance tracking
   - Credit usage history
   - Expiration dates
   - Refund unused credits
   - Transfer credits
   - Gift credits
   - Bonus credits

6. **Subscription Management**
   - Create subscription plans
   - Upgrade/downgrade plans
   - Add-ons and extras
   - Proration calculations
   - Cancellation handling
   - Grace periods
   - Renewal management

### User Roles & Permissions
- **Admin:** Full payment and subscription management
- **Manager:** View payments, manage customer subscriptions
- **Accountant:** View financial reports, export data
- **Customer:** Manage own subscriptions, view payment history

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP 7.4+, WordPress REST API
- **Payment Processing:** Stripe, PayPal, Authorize.net
- **Database:** MySQL 5.7+ with InnoDB
- **Cron:** WordPress Cron for automated billing
- **Invoicing:** PDF generation for invoices

### Dependencies
- BookingX Core 2.0+
- Payment gateway with subscription support
- WordPress Cron or system cron
- PHP OpenSSL extension

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/bulk-payments
GET    /wp-json/bookingx/v1/bulk-payments
GET    /wp-json/bookingx/v1/bulk-payments/{id}

POST   /wp-json/bookingx/v1/subscriptions
GET    /wp-json/bookingx/v1/subscriptions
GET    /wp-json/bookingx/v1/subscriptions/{id}
PUT    /wp-json/bookingx/v1/subscriptions/{id}
DELETE /wp-json/bookingx/v1/subscriptions/{id}
POST   /wp-json/bookingx/v1/subscriptions/{id}/pause
POST   /wp-json/bookingx/v1/subscriptions/{id}/resume
POST   /wp-json/bookingx/v1/subscriptions/{id}/cancel

POST   /wp-json/bookingx/v1/payment-plans
GET    /wp-json/bookingx/v1/payment-plans/{id}
PUT    /wp-json/bookingx/v1/payment-plans/{id}

GET    /wp-json/bookingx/v1/credits
POST   /wp-json/bookingx/v1/credits/purchase
POST   /wp-json/bookingx/v1/credits/redeem
GET    /wp-json/bookingx/v1/credits/balance
GET    /wp-json/bookingx/v1/credits/history

POST   /wp-json/bookingx/v1/invoices/generate
GET    /wp-json/bookingx/v1/invoices/{id}
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│  BookingX Core      │
│  - Payment System   │
└──────────┬──────────┘
           │
           ▼
┌──────────────────────────────┐
│  Bulk Payment Module         │
│  - Subscription Manager      │
│  - Credit Manager            │
│  - Payment Plan Engine       │
└──────────┬───────────────────┘
           │
           ├──────────┬──────────┐
           ▼          ▼          ▼
┌──────────┐ ┌──────────┐ ┌──────────┐
│Automated │ │ Invoice  │ │  Dunning │
│ Billing  │ │Generator │ │  Manager │
└──────────┘ └──────────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\BulkPayments;

class BulkPaymentManager {
    - create_bulk_payment()
    - process_bulk_payment()
    - get_bulk_payments()
    - refund_bulk_payment()
    - calculate_bulk_discount()
}

class SubscriptionManager {
    - create_subscription()
    - update_subscription()
    - cancel_subscription()
    - pause_subscription()
    - resume_subscription()
    - upgrade_subscription()
    - downgrade_subscription()
    - calculate_proration()
}

class PaymentPlanManager {
    - create_payment_plan()
    - get_payment_schedule()
    - process_installment()
    - calculate_installments()
    - handle_missed_payment()
}

class RecurringBillingEngine {
    - process_recurring_payments()
    - retry_failed_payments()
    - update_payment_method()
    - send_billing_notifications()
    - handle_webhook_events()
}

class CreditManager {
    - purchase_credits()
    - redeem_credits()
    - get_credit_balance()
    - transfer_credits()
    - expire_credits()
    - refund_credits()
    - get_credit_history()
}

class InvoiceGenerator {
    - generate_invoice()
    - get_invoice()
    - send_invoice()
    - mark_paid()
    - generate_pdf()
}

class DunningManager {
    - handle_failed_payment()
    - send_payment_reminder()
    - retry_payment()
    - suspend_subscription()
    - cancel_subscription()
}

class SubscriptionPlanManager {
    - create_plan()
    - update_plan()
    - get_plans()
    - archive_plan()
    - set_pricing_tiers()
}
```

---

## 5. Database Schema

### Table: `bkx_bulk_payments`
```sql
CREATE TABLE bkx_bulk_payments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED,
    payment_type ENUM('bulk_bookings', 'credit_purchase', 'subscription_upfront') NOT NULL,
    booking_count INT COMMENT 'Number of bookings paid for',
    credit_amount DECIMAL(10,2) COMMENT 'Credit purchased',
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    amount_paid DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    payment_id BIGINT(20) UNSIGNED,
    transaction_id VARCHAR(255),
    gateway VARCHAR(50),
    bookings_used INT DEFAULT 0,
    credits_used DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending', 'active', 'depleted', 'expired', 'refunded') NOT NULL DEFAULT 'pending',
    expires_at DATE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX service_id_idx (service_id),
    INDEX status_idx (status),
    INDEX expires_at_idx (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_subscriptions`
```sql
CREATE TABLE bkx_subscriptions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    plan_id BIGINT(20) UNSIGNED NOT NULL,
    external_subscription_id VARCHAR(255) COMMENT 'Gateway subscription ID',
    status ENUM('active', 'paused', 'cancelled', 'expired', 'past_due') NOT NULL DEFAULT 'active',
    billing_cycle ENUM('weekly', 'biweekly', 'monthly', 'quarterly', 'yearly', 'custom') NOT NULL,
    billing_interval INT DEFAULT 1,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    trial_end_date DATE,
    current_period_start DATE NOT NULL,
    current_period_end DATE NOT NULL,
    next_billing_date DATE,
    cancel_at_period_end TINYINT(1) DEFAULT 0,
    cancelled_at DATETIME,
    cancellation_reason TEXT,
    paused_at DATETIME,
    resume_at DATE,
    failed_payment_count INT DEFAULT 0,
    last_payment_date DATETIME,
    last_payment_status VARCHAR(50),
    metadata LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX plan_id_idx (plan_id),
    INDEX status_idx (status),
    INDEX next_billing_date_idx (next_billing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_subscription_plans`
```sql
CREATE TABLE bkx_subscription_plans (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    billing_cycle ENUM('weekly', 'biweekly', 'monthly', 'quarterly', 'yearly') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    setup_fee DECIMAL(10,2) DEFAULT 0,
    trial_period_days INT DEFAULT 0,
    bookings_per_cycle INT COMMENT 'Included bookings per billing cycle',
    credit_amount DECIMAL(10,2) COMMENT 'Credit amount per cycle',
    service_ids LONGTEXT COMMENT 'JSON array of included service IDs',
    features LONGTEXT COMMENT 'JSON array of plan features',
    max_subscribers INT COMMENT 'Maximum number of subscribers',
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_payment_plans`
```sql
CREATE TABLE bkx_payment_plans (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED,
    total_amount DECIMAL(10,2) NOT NULL,
    deposit_amount DECIMAL(10,2) DEFAULT 0,
    remaining_amount DECIMAL(10,2) NOT NULL,
    installment_count INT NOT NULL,
    installment_amount DECIMAL(10,2) NOT NULL,
    installments_paid INT DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    frequency ENUM('weekly', 'biweekly', 'monthly') NOT NULL,
    start_date DATE NOT NULL,
    next_payment_date DATE,
    status ENUM('active', 'completed', 'defaulted', 'cancelled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX booking_id_idx (booking_id),
    INDEX status_idx (status),
    INDEX next_payment_date_idx (next_payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_recurring_payments`
```sql
CREATE TABLE bkx_recurring_payments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT(20) UNSIGNED,
    payment_plan_id BIGINT(20) UNSIGNED,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    payment_type ENUM('subscription', 'installment', 'recurring') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    due_date DATE NOT NULL,
    payment_id BIGINT(20) UNSIGNED,
    transaction_id VARCHAR(255),
    gateway VARCHAR(50),
    status ENUM('pending', 'processing', 'paid', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    attempt_count INT DEFAULT 0,
    last_attempt_at DATETIME,
    paid_at DATETIME,
    failure_reason TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX subscription_id_idx (subscription_id),
    INDEX payment_plan_id_idx (payment_plan_id),
    INDEX customer_id_idx (customer_id),
    INDEX due_date_idx (due_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_credits`
```sql
CREATE TABLE bkx_credits (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    bulk_payment_id BIGINT(20) UNSIGNED,
    credit_type ENUM('purchased', 'bonus', 'refund', 'transfer') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    remaining_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    source VARCHAR(255),
    expires_at DATE,
    status ENUM('active', 'depleted', 'expired') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX status_idx (status),
    INDEX expires_at_idx (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_credit_transactions`
```sql
CREATE TABLE bkx_credit_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    credit_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED,
    transaction_type ENUM('purchase', 'redemption', 'refund', 'transfer_in', 'transfer_out', 'expiration', 'adjustment') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    description TEXT,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    INDEX credit_id_idx (credit_id),
    INDEX customer_id_idx (customer_id),
    INDEX booking_id_idx (booking_id),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    'bulk_payment_settings' => [
        'enable_bulk_payments' => true,
        'min_bulk_bookings' => 5,
        'max_bulk_bookings' => 50,
        'bulk_discount_type' => 'percentage', // percentage|fixed
        'bulk_discount_value' => 10,
        'bulk_expiration_days' => 365,
    ],

    'subscription_settings' => [
        'enable_subscriptions' => true,
        'allow_trial_periods' => true,
        'default_trial_days' => 7,
        'allow_plan_changes' => true,
        'proration_enabled' => true,
        'grace_period_days' => 3,
        'auto_cancel_after_failures' => 3,
    ],

    'payment_plan_settings' => [
        'enable_payment_plans' => true,
        'min_installments' => 2,
        'max_installments' => 12,
        'require_deposit' => true,
        'default_deposit_percentage' => 20,
        'allowed_frequencies' => ['weekly', 'biweekly', 'monthly'],
    ],

    'credit_settings' => [
        'enable_credits' => true,
        'min_credit_purchase' => 50,
        'max_credit_purchase' => 5000,
        'credit_bonus_threshold' => 500,
        'credit_bonus_percentage' => 5,
        'credit_expiration_days' => 365,
        'allow_credit_transfer' => true,
        'transfer_fee_percentage' => 2,
    ],

    'recurring_billing_settings' => [
        'retry_failed_payments' => true,
        'max_retry_attempts' => 3,
        'retry_interval_days' => [1, 3, 7],
        'send_payment_reminders' => true,
        'reminder_days_before' => [7, 3, 1],
        'dunning_emails_enabled' => true,
    ],

    'invoice_settings' => [
        'auto_generate_invoices' => true,
        'invoice_number_prefix' => 'INV-',
        'invoice_number_padding' => 5,
        'include_tax' => true,
        'tax_rate' => 0,
        'company_info' => [
            'name' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'logo_url' => '',
        ],
    ],

    'notification_settings' => [
        'subscription_created' => true,
        'payment_successful' => true,
        'payment_failed' => true,
        'subscription_cancelled' => true,
        'subscription_renewed' => true,
        'credit_low_threshold' => 50,
        'credit_expiring_days' => 30,
    ],
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Bulk Payment Options**
   - Number of bookings selector
   - Discount calculator
   - Total price display
   - Savings indicator
   - Credit purchase interface

2. **Subscription Plans Display**
   - Plan comparison table
   - Feature lists
   - Pricing display
   - Trial period badge
   - Subscribe button

3. **Payment Plan Calculator**
   - Total amount input
   - Deposit calculator
   - Installment preview
   - Payment schedule display
   - Apply button

4. **Customer Dashboard**
   - Active subscriptions
   - Credit balance
   - Payment history
   - Upcoming payments
   - Manage subscriptions
   - Download invoices

5. **Subscription Management**
   - Pause/resume subscription
   - Cancel subscription
   - Change plan
   - Update payment method
   - View billing history

### Backend Components

1. **Subscription Manager**
   - Subscription list
   - Filter by status, plan, customer
   - Bulk actions
   - Revenue metrics
   - Churn analysis

2. **Plan Builder**
   - Create subscription plans
   - Pricing configuration
   - Feature assignment
   - Trial period setup
   - Service inclusion

3. **Payment Schedule View**
   - Upcoming recurring payments
   - Failed payment list
   - Retry queue
   - Revenue forecast
   - Export options

4. **Credit Management**
   - Customer credit balances
   - Credit purchase history
   - Credit redemptions
   - Expiring credits alert
   - Adjustment interface

5. **Invoice Manager**
   - Invoice list
   - Generate invoices
   - Send invoices
   - Mark paid/unpaid
   - Export to PDF

6. **Reports & Analytics**
   - Subscription revenue
   - MRR/ARR metrics
   - Churn rate
   - Payment success rate
   - Credit usage analytics

---

## 8. Security Considerations

### Data Security
- **Payment Method Storage:** PCI-compliant tokenization
- **Subscription Data:** Encrypted storage
- **Credit Balances:** Accurate tracking with audit trail
- **SQL Injection:** Prepared statements
- **XSS Prevention:** Sanitize inputs

### Authorization
- Customers manage own subscriptions only
- Admin/Manager can manage all
- Capability checks for all operations
- Secure webhook endpoints

### Business Logic Security
- Validate payment amounts
- Prevent negative credits
- Verify subscription status
- Secure proration calculations
- Audit trail for all transactions

---

## 9. Testing Strategy

### Unit Tests
```php
- test_bulk_payment_creation()
- test_bulk_discount_calculation()
- test_subscription_creation()
- test_recurring_billing()
- test_proration_calculation()
- test_credit_purchase()
- test_credit_redemption()
- test_payment_retry_logic()
- test_invoice_generation()
```

### Integration Tests
```php
- test_complete_subscription_flow()
- test_payment_plan_workflow()
- test_failed_payment_retry()
- test_subscription_upgrade()
- test_credit_usage_workflow()
```

### Test Scenarios
1. **Bulk Purchase:** Buy 10 bookings with discount
2. **Subscription:** Monthly subscription with trial
3. **Payment Plan:** 6-month installment plan
4. **Failed Payment:** Retry and dunning workflow
5. **Plan Upgrade:** Upgrade with proration
6. **Credit Redemption:** Use credits for booking
7. **Subscription Cancellation:** Cancel and refund

---

## 10. Error Handling

### Error Messages (User-Facing)
```php
'insufficient_credits' => 'Insufficient credit balance. Current balance: %s',
'payment_failed' => 'Payment failed. Please update your payment method.',
'subscription_cancelled' => 'Your subscription has been cancelled.',
'plan_not_available' => 'This subscription plan is no longer available.',
'invalid_installments' => 'Invalid number of installments.',
'credit_expired' => 'Your credits have expired.',
```

---

## 11. Cron Jobs & Automation

### Scheduled Tasks
```php
// Daily tasks
bkx_process_recurring_payments - Process due payments
bkx_retry_failed_payments - Retry failed payments
bkx_send_payment_reminders - Send upcoming payment reminders
bkx_check_credit_expiration - Mark expired credits

// Weekly tasks
bkx_subscription_metrics - Calculate subscription metrics
```

---

## 12. Performance Optimization

### Caching Strategy
- Cache subscription plans (TTL: 1 hour)
- Cache credit balances (TTL: 5 minutes)
- Cache customer subscriptions (TTL: 10 minutes)

### Database Optimization
- Indexed queries
- Optimize payment processing
- Batch billing operations

---

## 13. Development Timeline

### Total Estimated Timeline: 11 weeks (2.75 months)

---

## 14. Success Metrics

### Business Metrics
- Subscription activation rate > 20%
- MRR growth rate > 10% monthly
- Payment success rate > 95%
- Customer LTV increase > 30%

---

## 15. Dependencies & Requirements

### Required
- BookingX Core 2.0+
- Payment gateway with subscription support

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
