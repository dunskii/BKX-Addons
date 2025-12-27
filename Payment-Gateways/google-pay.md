# Google Pay Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Google Pay
**Price:** $39
**Category:** Payment Gateways

### Description
Enable customers to use Google Pay for quick, secure payments. Requires Stripe gateway installation for functionality.

---

## 2. Key Features

- One-tap checkout with Google Pay
- Saved payment methods from Google account
- Mobile-optimized experience
- Works with Stripe Payment Intents
- Automatic billing address collection

---

## 3. Technical Stack

- Google Pay API for Web
- Stripe Payment Intents API
- Integration with Stripe gateway add-on

### Dependencies
- **Required:** BookingX Stripe Payments add-on
- Google Pay merchant account

---

## 4. Configuration

```php
[
    'google_pay_merchant_id' => '',
    'google_pay_merchant_name' => '',
    'google_pay_environment' => 'TEST|PRODUCTION',
    'allowed_card_networks' => ['VISA', 'MASTERCARD', 'AMEX'],
    'button_color' => 'black|white',
    'button_type' => 'buy|donate|pay',
]
```

---

## 5. Development Timeline
**Total:** 2 weeks

---

**Status:** Ready for Development
