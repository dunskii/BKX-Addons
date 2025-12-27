# Stripe Payments Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Stripe Payments
**Price:** $99
**Category:** Payment Gateways
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Complete Stripe integration with credit card processing, subscription support, and advanced reporting. Includes fraud detection, recurring payment support, and refund functionality for cancelled bookings.

### Value Proposition
- Industry-leading payment processing with global coverage
- Advanced fraud detection and prevention
- Seamless recurring payment handling
- Comprehensive reporting and reconciliation
- PCI DSS compliance built-in

---

## 2. Features & Requirements

### Core Features
1. **Payment Processing**
   - Credit card payments (Visa, Mastercard, Amex, Discover)
   - Digital wallets (Apple Pay, Google Pay via Stripe)
   - ACH/Bank transfers
   - International payment methods (iDEAL, SEPA, etc.)

2. **Subscription Management**
   - Recurring payment setup
   - Subscription modification
   - Cancellation handling
   - Proration support

3. **Refund Management**
   - Full refunds
   - Partial refunds
   - Automated refund on cancellation
   - Refund tracking and reporting

4. **Fraud Detection**
   - Stripe Radar integration
   - Customizable risk thresholds
   - 3D Secure (SCA compliance)
   - Address verification service (AVS)
   - CVC verification

5. **Reporting & Analytics**
   - Transaction reports
   - Failed payment tracking
   - Revenue analytics
   - Dispute management
   - Export to CSV/Excel

### User Roles & Permissions
- **Admin:** Full configuration access, view all transactions
- **Manager:** View transactions, process refunds
- **Staff:** View assigned booking payments only
- **Customer:** View own payment history

---

## 3. Technical Specifications

### Technology Stack
- **API Version:** Stripe API v2023-10-16
- **PHP SDK:** stripe/stripe-php v10.0+
- **JavaScript:** Stripe.js v3
- **Payment Elements:** Stripe Payment Element
- **Webhook Handler:** Custom WordPress endpoint

### Dependencies
- BookingX Core 2.0+
- PHP cURL extension
- PHP JSON extension
- SSL certificate (required for production)
- WordPress REST API enabled

### API Integration Points
```php
// Primary Stripe API endpoints used
- POST /v1/payment_intents
- POST /v1/customers
- POST /v1/subscriptions
- POST /v1/refunds
- POST /v1/payment_methods
- GET /v1/charges
- GET /v1/balance_transactions
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────┐
│  BookingX Core  │
└────────┬────────┘
         │
         ▼
┌─────────────────────────┐
│  Payment Gateway API    │
│  (Abstract Interface)   │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  Stripe Payment Class   │
│  - Process Payment      │
│  - Handle Webhooks      │
│  - Manage Subscriptions │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│    Stripe API SDK       │
└─────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\Stripe;

class StripePaymentGateway extends PaymentGateway {
    - init()
    - process_payment()
    - process_refund()
    - create_subscription()
    - handle_webhook()
    - verify_payment_intent()
}

class StripeWebhookHandler {
    - handle_payment_succeeded()
    - handle_payment_failed()
    - handle_subscription_updated()
    - handle_charge_refunded()
    - handle_dispute_created()
}

class StripeCustomerManager {
    - create_customer()
    - update_customer()
    - attach_payment_method()
    - get_customer_payment_methods()
}

class StripeReporting {
    - get_transactions()
    - get_revenue_report()
    - export_transactions()
    - get_dispute_report()
}
```

---

## 5. Database Schema

### Table: `bkx_stripe_transactions`
```sql
CREATE TABLE bkx_stripe_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    stripe_payment_intent_id VARCHAR(255) NOT NULL,
    stripe_charge_id VARCHAR(255),
    stripe_customer_id VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(50) NOT NULL,
    payment_method_type VARCHAR(50),
    receipt_url VARCHAR(500),
    failure_code VARCHAR(100),
    failure_message TEXT,
    metadata LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX payment_intent_idx (stripe_payment_intent_id),
    INDEX customer_id_idx (stripe_customer_id),
    INDEX status_idx (status),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_stripe_subscriptions`
```sql
CREATE TABLE bkx_stripe_subscriptions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED,
    stripe_subscription_id VARCHAR(255) NOT NULL UNIQUE,
    stripe_customer_id VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    interval VARCHAR(20) NOT NULL,
    interval_count INT NOT NULL DEFAULT 1,
    current_period_start DATETIME,
    current_period_end DATETIME,
    cancel_at_period_end TINYINT(1) DEFAULT 0,
    canceled_at DATETIME,
    metadata LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX subscription_id_idx (stripe_subscription_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_stripe_refunds`
```sql
CREATE TABLE bkx_stripe_refunds (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    stripe_refund_id VARCHAR(255) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    reason VARCHAR(100),
    status VARCHAR(50) NOT NULL,
    failure_reason TEXT,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX transaction_id_idx (transaction_id),
    INDEX booking_id_idx (booking_id),
    INDEX refund_id_idx (stripe_refund_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_stripe_webhook_events`
```sql
CREATE TABLE bkx_stripe_webhook_events (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stripe_event_id VARCHAR(255) NOT NULL UNIQUE,
    event_type VARCHAR(100) NOT NULL,
    payload LONGTEXT NOT NULL,
    processed TINYINT(1) DEFAULT 0,
    processing_error TEXT,
    received_at DATETIME NOT NULL,
    processed_at DATETIME,
    INDEX event_id_idx (stripe_event_id),
    INDEX event_type_idx (event_type),
    INDEX processed_idx (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    'stripe_mode' => 'live|test',
    'stripe_live_publishable_key' => '',
    'stripe_live_secret_key' => '',
    'stripe_test_publishable_key' => '',
    'stripe_test_secret_key' => '',
    'stripe_webhook_secret' => '',
    'enable_apple_pay' => true,
    'enable_google_pay' => true,
    'enable_link' => true,
    'statement_descriptor' => '',
    'capture_method' => 'automatic|manual',
    'enable_3d_secure' => true,
    'radar_risk_threshold' => 75,
    'auto_refund_on_cancel' => true,
    'save_payment_methods' => true,
    'currency' => 'USD',
    'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Payment Form**
   - Stripe Payment Element integration
   - Card element with validation
   - Payment method selection
   - Save card checkbox
   - Security badges (SSL, PCI)
   - Error message display
   - Loading states

2. **Saved Payment Methods**
   - List of customer payment methods
   - Add new payment method
   - Delete payment method
   - Set default payment method

3. **Payment Confirmation**
   - Receipt display
   - Download receipt button
   - Email receipt option
   - Transaction details

### Backend Components

1. **Settings Page**
   - API key configuration
   - Test/Live mode toggle
   - Webhook URL display
   - Feature toggles
   - Risk settings

2. **Transaction List**
   - Searchable transaction table
   - Filter by status, date, amount
   - Bulk actions
   - Export functionality
   - Refund action

3. **Transaction Details**
   - Full payment information
   - Stripe Dashboard link
   - Refund interface
   - Event timeline
   - Customer details

---

## 8. Security Considerations

### Data Security
- **PCI DSS Compliance:** All card data handled by Stripe (never touches server)
- **API Key Storage:** Encrypted storage of secret keys
- **Webhook Validation:** Signature verification for all webhooks
- **HTTPS Required:** Enforce SSL for all payment pages
- **XSS Prevention:** Sanitize all user inputs
- **SQL Injection:** Use prepared statements

### Authentication & Authorization
- WordPress nonce verification for all admin actions
- Capability checks for refunds and transaction viewing
- Customer data access restricted to account owner
- Rate limiting on payment attempts

### Compliance
- **GDPR:** Right to data export/deletion
- **PCI DSS:** Level 1 compliance via Stripe
- **SCA (3D Secure):** European regulation compliance
- **Data Retention:** Configurable retention policies

---

## 9. Testing Strategy

### Unit Tests
```php
- test_payment_intent_creation()
- test_successful_payment_processing()
- test_failed_payment_handling()
- test_refund_processing()
- test_subscription_creation()
- test_webhook_signature_validation()
- test_customer_creation()
- test_payment_method_attachment()
```

### Integration Tests
```php
- test_complete_booking_payment_flow()
- test_recurring_payment_flow()
- test_refund_on_cancellation()
- test_webhook_event_processing()
- test_3d_secure_authentication()
- test_currency_conversion()
```

### Test Scenarios
1. **Successful Payment:** Complete booking with valid card
2. **Declined Card:** Handle declined payment gracefully
3. **3D Secure:** Test SCA authentication flow
4. **Refund:** Process full and partial refunds
5. **Subscription:** Create and manage recurring payments
6. **Webhook Processing:** Handle all webhook event types
7. **Error Recovery:** Network failures, API errors
8. **Edge Cases:** Zero amount, duplicate payments, race conditions

### Test Cards (Stripe Test Mode)
```
Success: 4242 4242 4242 4242
Decline: 4000 0000 0000 0002
3D Secure: 4000 0025 0000 3155
Insufficient Funds: 4000 0000 0000 9995
```

---

## 10. Error Handling

### Error Categories
1. **API Errors:** Connection failures, invalid requests
2. **Card Errors:** Declined, insufficient funds, expired
3. **Validation Errors:** Invalid amount, currency mismatch
4. **Webhook Errors:** Invalid signature, processing failures

### Error Messages (User-Facing)
```php
'card_declined' => 'Your card was declined. Please try a different payment method.',
'insufficient_funds' => 'Your card has insufficient funds.',
'expired_card' => 'Your card has expired.',
'incorrect_cvc' => 'Your card\'s security code is incorrect.',
'processing_error' => 'An error occurred processing your payment. Please try again.',
'authentication_required' => 'Please complete the authentication to continue.',
```

### Logging
- All API requests/responses (in test mode)
- Payment failures with error codes
- Webhook events and processing results
- Refund transactions
- Admin actions (refunds, configuration changes)

---

## 11. Webhooks

### Supported Events
```php
payment_intent.succeeded
payment_intent.payment_failed
payment_intent.canceled
charge.refunded
charge.dispute.created
charge.dispute.closed
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted
invoice.payment_succeeded
invoice.payment_failed
```

### Webhook Handler Implementation
```php
public function handle_webhook() {
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sig_header,
            $this->webhook_secret
        );

        // Store event
        $this->store_webhook_event($event);

        // Process based on type
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handle_payment_succeeded($event->data->object);
                break;
            // ... additional handlers
        }

        http_response_code(200);
    } catch (\Exception $e) {
        http_response_code(400);
        $this->log_error('Webhook error: ' . $e->getMessage());
    }
}
```

---

## 12. Performance Optimization

### Caching Strategy
- Cache customer Stripe IDs (reduce API calls)
- Cache payment method lists (TTL: 5 minutes)
- Cache exchange rates (TTL: 1 hour)

### Database Optimization
- Indexed queries on transaction lookups
- Archival of old transactions (1+ year)
- Pagination for transaction lists

### API Rate Limiting
- Implement exponential backoff
- Queue webhook processing
- Batch operations where possible

---

## 13. Internationalization

### Supported Currencies
- Configure primary currency
- Support 135+ currencies via Stripe
- Automatic currency conversion
- Display amounts in customer's currency

### Languages
- Translatable strings via WordPress i18n
- RTL support
- Currency formatting per locale
- Date/time localization

---

## 14. Documentation Requirements

### User Documentation
1. **Installation Guide**
   - Plugin installation
   - API key configuration
   - Webhook setup
   - Test mode verification

2. **User Guide**
   - Making payments
   - Viewing receipts
   - Managing saved cards
   - Understanding payment status

3. **Admin Guide**
   - Configuration options
   - Processing refunds
   - Viewing reports
   - Handling disputes
   - Troubleshooting

### Developer Documentation
1. **API Reference**
   - Filter hooks
   - Action hooks
   - Public methods
   - Data structures

2. **Integration Guide**
   - Custom payment flows
   - Webhook extensions
   - Custom reporting
   - Third-party integrations

---

## 15. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema creation
- [ ] Basic plugin structure
- [ ] Stripe SDK integration
- [ ] Settings page UI
- [ ] API key configuration

### Phase 2: Core Payment Processing (Week 3-4)
- [ ] Payment Intent creation
- [ ] Payment form integration
- [ ] Payment processing logic
- [ ] Success/failure handling
- [ ] Customer management

### Phase 3: Advanced Features (Week 5-6)
- [ ] Refund functionality
- [ ] Subscription support
- [ ] Saved payment methods
- [ ] 3D Secure implementation
- [ ] Multi-currency support

### Phase 4: Webhooks & Automation (Week 7)
- [ ] Webhook endpoint creation
- [ ] Event handler implementation
- [ ] Automated refund on cancellation
- [ ] Email notifications

### Phase 5: Reporting & Admin (Week 8)
- [ ] Transaction listing
- [ ] Report generation
- [ ] Export functionality
- [ ] Admin dashboard widgets

### Phase 6: Testing & QA (Week 9-10)
- [ ] Unit test development
- [ ] Integration testing
- [ ] Security audit
- [ ] Performance testing
- [ ] User acceptance testing

### Phase 7: Documentation & Launch (Week 11-12)
- [ ] User documentation
- [ ] Developer documentation
- [ ] Video tutorials
- [ ] Beta testing
- [ ] Production release

**Total Estimated Timeline:** 12 weeks (3 months)

---

## 16. Maintenance & Support

### Update Schedule
- **Security Updates:** As needed
- **Bug Fixes:** Bi-weekly
- **Feature Updates:** Quarterly
- **Stripe API Updates:** As released

### Support Channels
- Email support
- Documentation portal
- Video tutorials
- Community forum

### Monitoring
- Transaction success rates
- Error rate tracking
- API performance monitoring
- Webhook delivery monitoring

---

## 17. Dependencies & Requirements

### Required WordPress Plugins
- BookingX Core 2.0+

### Optional Compatible Plugins
- WooCommerce (for product integration)
- BookingX Recurring Bookings
- BookingX Deposits

### Server Requirements
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- SSL Certificate (required)
- cURL with TLS 1.2+
- PHP JSON extension
- WordPress 5.8+

---

## 18. Success Metrics

### Technical Metrics
- Payment success rate > 98%
- Page load time < 2 seconds
- API response time < 500ms
- Webhook processing < 5 seconds
- Zero data breaches

### Business Metrics
- Activation rate > 30%
- Monthly active rate > 70%
- Customer satisfaction > 4.5/5
- Support ticket volume < 5% of users
- Churn rate < 10% annually

---

## 19. Known Limitations

1. **Currency Limitations:** Some currencies don't support decimals
2. **Payment Methods:** Availability varies by country
3. **Refund Window:** Stripe limits refund period (typically 180 days)
4. **Recurring Payments:** Minimum interval is 1 day
5. **API Rate Limits:** 100 requests/second (burst), 25/second (sustained)

---

## 20. Future Enhancements

### Version 2.0 Roadmap
- [ ] Stripe Terminal integration (in-person payments)
- [ ] Stripe Billing Portal
- [ ] Advanced fraud rules configuration
- [ ] Multi-account support (Stripe Connect)
- [ ] Installment payments
- [ ] Cryptocurrency support
- [ ] Buy Now Pay Later (Klarna, Afterpay)

### Version 3.0 Roadmap
- [ ] AI-powered fraud detection
- [ ] Predictive analytics
- [ ] Custom payment flows builder
- [ ] White-label payment pages
- [ ] Advanced revenue optimization

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
