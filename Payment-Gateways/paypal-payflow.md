# PayPal Payflow Add-on - Development Documentation

## 1. Overview

**Add-on Name:** PayPal Payflow
**Price:** $89
**Category:** Payment Gateways
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
PayPal Payflow payment functions supporting both Pro and Link versions. Includes live/sandbox modes, recurring payment hooks, refund capabilities, and fraud detection measures.

### Value Proposition
- Enterprise-grade payment processing
- Support for both Payflow Pro and Link
- Advanced fraud protection tools
- Seamless recurring billing
- Comprehensive transaction management

---

## 2. Features & Requirements

### Core Features
1. **Payment Processing**
   - Credit card processing via Payflow Pro
   - Hosted payment pages via Payflow Link
   - Digital wallet support
   - ACH/eCheck processing
   - International payment methods

2. **Payflow Variants**
   - Payflow Pro (direct API integration)
   - Payflow Link (hosted payment pages)
   - Automatic failover between methods

3. **Recurring Billing**
   - Subscription setup and management
   - Automated recurring charges
   - Trial periods support
   - Billing cycle modifications
   - Cancellation handling

4. **Fraud Detection**
   - PayPal Fraud Protection Services
   - Address Verification Service (AVS)
   - Card Security Code (CSC)
   - Velocity filters
   - Risk-based decisioning

5. **Refund Management**
   - Full and partial refunds
   - Automated refunds on cancellation
   - Refund tracking and reporting
   - Void transactions (before settlement)

### User Roles & Permissions
- **Admin:** Full configuration, all transaction access
- **Manager:** View transactions, process refunds
- **Staff:** View assigned booking payments
- **Customer:** View payment history, manage billing

---

## 3. Technical Specifications

### Technology Stack
- **API Version:** PayPal Payflow Gateway API
- **Integration Method:** HTTPS POST with NVP (Name-Value Pair)
- **PHP Requirements:** cURL, OpenSSL
- **Hosted Pages:** Payflow Link with embedded forms
- **Security:** TLS 1.2+, PCI DSS Level 1

### Dependencies
- BookingX Core 2.0+
- PHP cURL extension
- PHP OpenSSL extension
- SSL certificate (required)

### API Integration Points
```php
// Payflow API Endpoints
Production: https://payflowpro.paypal.com
Sandbox: https://pilot-payflowpro.paypal.com

// Primary transaction types
- Sale (Authorization + Capture)
- Authorization
- Delayed Capture
- Credit (Refund)
- Void
- Recurring Billing Profile
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
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│ PayPal Payflow Class    │
│ - Process Payment       │
│ - Recurring Billing     │
│ - Refund Processing     │
└────────┬────────────────┘
         │
         ├──────────────┬──────────────┐
         ▼              ▼              ▼
┌──────────────┐ ┌──────────┐ ┌──────────────┐
│ Payflow Pro  │ │ Payflow  │ │   Fraud      │
│   Gateway    │ │   Link   │ │  Protection  │
└──────────────┘ └──────────┘ └──────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\PayPalPayflow;

class PayflowPaymentGateway extends PaymentGateway {
    - init()
    - process_payment()
    - process_refund()
    - void_transaction()
    - create_recurring_profile()
    - cancel_recurring_profile()
}

class PayflowProProcessor {
    - build_nvp_string()
    - send_transaction()
    - parse_response()
    - handle_errors()
}

class PayflowLinkProcessor {
    - generate_secure_token()
    - create_hosted_form()
    - process_return()
    - verify_response()
}

class PayflowRecurringBilling {
    - create_profile()
    - modify_profile()
    - cancel_profile()
    - inquiry_profile()
}

class PayflowFraudProtection {
    - configure_filters()
    - evaluate_transaction()
    - handle_fraud_response()
}
```

---

## 5. Database Schema

### Table: `bkx_payflow_transactions`
```sql
CREATE TABLE bkx_payflow_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    pnref VARCHAR(12) NOT NULL UNIQUE,
    transaction_id VARCHAR(50),
    transaction_type VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(50) NOT NULL,
    result_code INT,
    result_message VARCHAR(255),
    avs_result VARCHAR(10),
    cvv_result VARCHAR(10),
    auth_code VARCHAR(6),
    payment_method_last4 VARCHAR(4),
    payment_method_type VARCHAR(20),
    fraud_protection_result TEXT,
    request_data LONGTEXT,
    response_data LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX pnref_idx (pnref),
    INDEX status_idx (status),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_payflow_recurring_profiles`
```sql
CREATE TABLE bkx_payflow_recurring_profiles (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED,
    profile_id VARCHAR(50) NOT NULL UNIQUE,
    status VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    billing_frequency VARCHAR(20) NOT NULL,
    billing_period INT NOT NULL,
    num_payments INT,
    payments_made INT DEFAULT 0,
    start_date DATE,
    next_payment_date DATE,
    end_date DATE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX profile_id_idx (profile_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_payflow_refunds`
```sql
CREATE TABLE bkx_payflow_refunds (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    pnref VARCHAR(12) NOT NULL UNIQUE,
    original_pnref VARCHAR(12) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    reason VARCHAR(100),
    status VARCHAR(50) NOT NULL,
    result_code INT,
    result_message VARCHAR(255),
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX transaction_id_idx (transaction_id),
    INDEX booking_id_idx (booking_id),
    INDEX pnref_idx (pnref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    'payflow_mode' => 'live|sandbox',
    'payflow_vendor' => '',
    'payflow_partner' => 'PayPal',
    'payflow_user' => '',
    'payflow_password' => '',
    'integration_type' => 'pro|link',
    'enable_recurring' => true,
    'require_cvv' => true,
    'avs_enforcement' => 'none|warning|strict',
    'fraud_protection' => true,
    'auto_settle' => true,
    'settlement_delay' => 0,
    'currency' => 'USD',
    'test_mode_logging' => true,
]
```

---

## 7. Payment Processing Flow

### Payflow Pro (Direct)
```php
1. Customer enters card details
2. JavaScript validates form
3. Data sent to WordPress via AJAX
4. Server builds NVP request
5. Posts to Payflow Gateway
6. Receives and parses response
7. Updates booking status
8. Returns confirmation to customer
```

### Payflow Link (Hosted)
```php
1. Generate secure token via API
2. Embed Payflow Link iframe
3. Customer completes payment on PayPal
4. Silent POST to return URL
5. Verify transaction
6. Update booking status
7. Redirect to confirmation
```

---

## 8. Security Considerations

### Data Security
- **PCI Compliance:** Hosted forms never expose card data to server
- **Secure Token:** One-time use, expires after 30 minutes
- **Password Storage:** Encrypted credentials in database
- **TLS Requirements:** Minimum TLS 1.2
- **IP Restrictions:** Optional IP whitelisting

### Fraud Prevention
- **AVS Matching:** Configurable address verification
- **CVV Validation:** Required for card-not-present
- **Velocity Filters:** Transaction frequency limits
- **Amount Filters:** Threshold-based blocking
- **Duplicate Prevention:** Transaction fingerprinting

---

## 9. Testing Strategy

### Test Accounts
```
Vendor: Use sandbox credentials from PayPal Manager
User: Same as vendor (or created in Manager)
Partner: PayPal or authorized partner
Password: Sandbox password
```

### Test Cards
```
Visa: 4111111111111111
Mastercard: 5105105105105100
Amex: 378282246310005
Discover: 6011111111111117
```

### Test Scenarios
1. **Successful Sale**
2. **Declined Transaction**
3. **AVS Mismatch**
4. **CVV Failure**
5. **Insufficient Funds**
6. **Recurring Profile Creation**
7. **Profile Cancellation**
8. **Full Refund**
9. **Partial Refund**
10. **Void Before Settlement**

---

## 10. Error Handling

### Result Codes
```php
0 = Approved
1 = User authentication failed
2 = Invalid tender type
3 = Invalid transaction type
4 = Invalid amount format
12 = Decline
23 = Invalid account number
24 = Invalid expiration date
112 = Failed AVS check
114 = Referral - call for authorization
```

### User-Facing Messages
```php
'declined' => 'Your payment was declined. Please check your card details.',
'invalid_card' => 'The card number appears to be invalid.',
'expired_card' => 'Your card has expired.',
'avs_failed' => 'The billing address does not match. Please verify.',
'cvv_failed' => 'The security code is incorrect.',
'system_error' => 'A payment processing error occurred. Please try again.',
```

---

## 11. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Plugin structure
- [ ] Settings page
- [ ] API connectivity

### Phase 2: Payflow Pro (Week 3-4)
- [ ] Payment form
- [ ] Transaction processing
- [ ] Response handling
- [ ] Error management

### Phase 3: Payflow Link (Week 5)
- [ ] Secure token generation
- [ ] Hosted form integration
- [ ] Silent POST handling
- [ ] Return URL processing

### Phase 4: Advanced Features (Week 6-7)
- [ ] Recurring billing
- [ ] Refund processing
- [ ] Void functionality
- [ ] Fraud protection

### Phase 5: Testing & Documentation (Week 8-9)
- [ ] Unit tests
- [ ] Integration tests
- [ ] Documentation
- [ ] QA and launch

**Total Timeline:** 9 weeks (2.25 months)

---

## 12. Documentation Requirements

### User Documentation
- Installation and setup guide
- Obtaining API credentials
- Configuring fraud protection
- Processing refunds
- Managing recurring billing

### Developer Documentation
- API integration guide
- Filter and action hooks
- Custom transaction types
- Extending fraud rules

---

## 13. Success Metrics

- Payment success rate > 97%
- Average transaction time < 3 seconds
- Fraud detection accuracy > 95%
- Customer satisfaction > 4.3/5
- Support ticket rate < 8%

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
