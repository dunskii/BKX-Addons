# PayPal Pro Add-on - Development Documentation

## 1. Overview

**Add-on Name:** PayPal Pro
**Price:** $89
**Category:** Payment Gateways

### Description
Advanced PayPal integration with credit card processing, recurring payments, advanced reporting, and enhanced security features.

---

## 2. Key Features

- Direct credit card processing (no PayPal account required)
- PayPal account payments
- Recurring billing/subscriptions
- Reference transactions
- Fraud management filters
- Advanced checkout customization
- Billing agreements

---

## 3. Technical Stack

- PayPal Pro (DoDirectPayment API)
- PayPal REST API v2
- Billing Agreements API
- Vault API for token storage

### Database Schema
```sql
CREATE TABLE bkx_paypal_pro_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    transaction_id VARCHAR(50) NOT NULL UNIQUE,
    billing_agreement_id VARCHAR(50),
    amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(20),
    status VARCHAR(50),
    created_at DATETIME NOT NULL
);
```

---

## 4. Configuration

```php
[
    'paypal_mode' => 'live|sandbox',
    'api_username' => '',
    'api_password' => '',
    'api_signature' => '',
    'enable_billing_agreements' => true,
    'fraud_management' => true,
]
```

---

## 5. Development Timeline
**Total:** 4 weeks

---

**Status:** Ready for Development
