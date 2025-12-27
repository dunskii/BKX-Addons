# PayTM Add-on - Development Documentation

## 1. Overview

**Add-on Name:** PayTM
**Price:** $49
**Category:** Payment Gateways

### Description
Popular Indian digital wallet and payment gateway integration with recurring payment support and fraud detection.

---

## 2. Key Features

- PayTM Wallet payments
- Credit/debit card processing
- Net banking integration
- UPI payments
- EMI options
- QR code payments
- Recurring payments

---

## 3. Technical Stack

- PayTM Payment Gateway API v3
- Checksum generation/verification
- Webhook integration

### Database Schema
```sql
CREATE TABLE bkx_paytm_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    order_id VARCHAR(50) NOT NULL UNIQUE,
    txn_id VARCHAR(50),
    amount DECIMAL(10,2),
    status VARCHAR(50),
    payment_mode VARCHAR(50),
    created_at DATETIME NOT NULL
);
```

---

## 4. Development Timeline
**Total:** 3 weeks

---

**Status:** Ready for Development
