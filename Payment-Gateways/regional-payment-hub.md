# Regional Payment Hub Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Regional Payment Hub
**Price:** $79
**Category:** Payment Gateways

### Description
Multi-regional payment processor supporting local payment methods across emerging markets with unified reporting.

---

## 2. Key Features

### Supported Regions & Methods
- **Africa:** M-Pesa, MTN Mobile Money, Airtel Money
- **Southeast Asia:** GrabPay, Touch 'n Go, GCash
- **Latin America:** Mercado Pago, OXXO, Boleto
- **Middle East:** Fawry, CashU, OneCard

### Core Features
- Unified API for all regional methods
- Automatic currency conversion
- Local language support
- Regional compliance (data residency)
- Unified reporting dashboard

---

## 3. Technical Stack

- Aggregated payment gateway APIs
- Currency conversion API
- Multi-region webhook handling

### Database Schema
```sql
CREATE TABLE bkx_regional_payments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    payment_method VARCHAR(50),
    region VARCHAR(50),
    transaction_id VARCHAR(100),
    local_amount DECIMAL(10,2),
    local_currency VARCHAR(3),
    converted_amount DECIMAL(10,2),
    base_currency VARCHAR(3),
    status VARCHAR(50),
    created_at DATETIME NOT NULL
);
```

---

## 4. Configuration

```php
[
    'enabled_regions' => ['africa', 'southeast_asia', 'latam', 'middle_east'],
    'base_currency' => 'USD',
    'auto_currency_conversion' => true,
    // Region-specific API credentials
    'mpesa_api_key' => '',
    'grabpay_api_key' => '',
    'mercadopago_api_key' => '',
]
```

---

## 5. Development Timeline
**Total:** 6 weeks (due to multiple integrations)

---

**Status:** Ready for Development
