# Square Payments Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Square Payments
**Price:** $79
**Category:** Payment Gateways
**Version:** 1.0.0

### Description
Square payment integration for both in-person and online payments. Features inventory sync, customer sync, recurring payments, and refund functionality.

---

## 2. Key Features

- **Payment Processing:** Credit cards, digital wallets (Apple Pay, Google Pay, Cash App Pay)
- **In-Person Payments:** Square Terminal/Reader integration
- **Online Payments:** Square Web Payment SDK
- **Customer Sync:** Automatic customer directory synchronization
- **Inventory Management:** Optional service inventory tracking
- **Recurring Payments:** Subscription and installment support
- **Refund Processing:** Full and partial refunds
- **Reporting:** Transaction reports and analytics

---

## 3. Technical Specifications

### Technology Stack
- **API:** Square API v2
- **SDK:** Square PHP SDK
- **JavaScript:** Square Web Payments SDK
- **OAuth:** Square OAuth for merchant authorization

### Dependencies
- BookingX Core 2.0+
- PHP 7.4+
- WordPress 5.8+
- SSL Certificate

---

## 4. Database Schema

### Table: `bkx_square_transactions`
```sql
CREATE TABLE bkx_square_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    square_payment_id VARCHAR(255) NOT NULL UNIQUE,
    square_order_id VARCHAR(255),
    square_customer_id VARCHAR(255),
    amount_money BIGINT NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status VARCHAR(50) NOT NULL,
    source_type VARCHAR(50),
    card_brand VARCHAR(20),
    last_4 VARCHAR(4),
    receipt_url VARCHAR(500),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_idx (booking_id),
    INDEX payment_idx (square_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Table: `bkx_square_refunds`
```sql
CREATE TABLE bkx_square_refunds (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT(20) UNSIGNED NOT NULL,
    square_refund_id VARCHAR(255) NOT NULL UNIQUE,
    amount_money BIGINT NOT NULL,
    reason VARCHAR(255),
    status VARCHAR(50),
    created_at DATETIME NOT NULL,
    INDEX transaction_idx (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 5. Configuration Settings

```php
[
    'square_mode' => 'production|sandbox',
    'square_application_id' => '',
    'square_access_token' => '',
    'square_location_id' => '',
    'enable_customer_sync' => true,
    'enable_inventory_sync' => false,
    'auto_refund_on_cancel' => true,
    'accepted_payment_methods' => ['card', 'apple_pay', 'google_pay', 'cash_app_pay'],
    'currency' => 'USD',
]
```

---

## 6. Class Structure

```php
namespace BookingX\Addons\Square;

class SquarePaymentGateway extends PaymentGateway {
    - process_payment()
    - process_refund()
    - create_customer()
    - sync_customers()
}

class SquareWebPayments {
    - initialize_payment_form()
    - tokenize_card()
    - create_payment()
}

class SquareCustomerSync {
    - sync_to_square()
    - sync_from_square()
    - update_customer()
}
```

---

## 7. Integration Points

### Square APIs Used
- **Payments API:** Process payments
- **Orders API:** Create orders for bookings
- **Customers API:** Customer directory sync
- **Refunds API:** Process refunds
- **Catalog API:** Optional inventory sync
- **OAuth API:** Merchant authorization

---

## 8. Security Features

- OAuth 2.0 merchant authorization
- PCI DSS Level 1 compliance via Square
- Tokenized card data (never touches server)
- TLS 1.2+ encrypted communications
- Webhook signature verification
- Role-based access control

---

## 9. Testing Strategy

### Test Environment
- Square Sandbox mode
- Test cards provided by Square
- Mock customer data
- Simulated refund scenarios

### Test Cards
```
Visa: 4111 1111 1111 1111
Mastercard: 5105 1051 0510 5100
Amex: 3782 822463 10005
```

---

## 10. Development Timeline

- **Week 1-2:** Core payment processing
- **Week 3:** Customer & inventory sync
- **Week 4:** Refunds & recurring payments
- **Week 5:** Testing & documentation

**Total:** 5 weeks

---

## 11. Success Metrics

- Payment success rate > 98%
- API response time < 800ms
- Customer sync accuracy > 99%
- Support ticket rate < 5%

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Status:** Ready for Development
