# eWay Add-on - Development Documentation

## 1. Overview

**Add-on Name:** eWay
**Price:** $69
**Category:** Payment Gateways
**Version:** 1.0.0

### Description
Australian eWay payment gateway integration with live/sandbox environments, subscription support, and automated refund processing.

---

## 2. Key Features

- **Payment Processing:** Credit/debit cards (Australian focus)
- **eWay Methods:** Rapid API, Transparent Redirect, Responsive Shared Page
- **Recurring Payments:** Token-based recurring billing
- **Refunds:** Full and partial refund support
- **Fraud:** Basic fraud detection
- **Multi-Currency:** AUD, NZD, USD, GBP, EUR

---

## 3. Technical Specifications

### API Integration
- **API:** eWay Rapid API v3
- **SDK:** eWay PHP Rapid SDK
- **Authentication:** API Key + Password
- **Regions:** Australia, New Zealand, UK, Singapore

### Database Schema
```sql
CREATE TABLE bkx_eway_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    transaction_id VARCHAR(50) NOT NULL,
    token_customer_id VARCHAR(50),
    amount INT NOT NULL,
    currency VARCHAR(3) DEFAULT 'AUD',
    status VARCHAR(50),
    response_code VARCHAR(10),
    created_at DATETIME NOT NULL
);
```

---

## 4. Configuration

```php
[
    'eway_mode' => 'live|sandbox',
    'api_key' => '',
    'api_password' => '',
    'integration_method' => 'rapid_api|transparent_redirect|shared_page',
    'currency' => 'AUD',
    'enable_recurring' => true,
]
```

---

## 5. Development Timeline

- **Week 1-2:** Core integration
- **Week 3:** Recurring billing
- **Week 4:** Testing & launch

**Total:** 4 weeks

---

**Status:** Ready for Development
