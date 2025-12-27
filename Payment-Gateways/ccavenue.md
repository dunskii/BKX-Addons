# CCAvenue Payment Gateway Add-on - Development Documentation

## 1. Overview

**Add-on Name:** CCAvenue Payment Gateway
**Price:** $59
**Category:** Payment Gateways
**Version:** 1.0.0

### Description
Indian market payment gateway supporting multiple payment methods, recurring transactions, and localized fraud detection.

---

## 2. Key Features

- **Payment Methods:** Credit/debit cards, net banking, UPI, wallets
- **Indian Focus:** Supports all major Indian banks
- **Multi-Currency:** 27+ currencies supported
- **EMI Options:** Easy installment payment options
- **Recurring:** Subscription and standing instruction support
- **Mobile:** Responsive and mobile-optimized checkout

---

## 3. Technical Specifications

### API Integration
- **Method:** Redirect-based integration
- **Encryption:** AES-128 encryption
- **Authentication:** Merchant ID + Working Key + Access Code

### Database Schema
```sql
CREATE TABLE bkx_ccavenue_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    order_id VARCHAR(50) NOT NULL UNIQUE,
    tracking_id VARCHAR(50),
    amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'INR',
    payment_mode VARCHAR(50),
    status VARCHAR(50),
    created_at DATETIME NOT NULL
);
```

---

## 4. Configuration

```php
[
    'ccavenue_merchant_id' => '',
    'ccavenue_working_key' => '',
    'ccavenue_access_code' => '',
    'currency' => 'INR',
    'enable_emi' => true,
    'enable_wallets' => true,
]
```

---

## 5. Development Timeline

- **Week 1-2:** Integration & encryption
- **Week 3:** Testing & launch

**Total:** 3 weeks

---

**Status:** Ready for Development
