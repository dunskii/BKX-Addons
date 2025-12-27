# Authorize.NET Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Authorize.NET
**Price:** $79
**Category:** Payment Gateways
**Version:** 1.0.0

### Description
Authorize.NET payment gateway with live/sandbox modes, recurring payment support, automated refunds, and comprehensive fraud detection.

---

## 2. Key Features

- **Payment Methods:** Credit cards, eChecks, digital wallets
- **Transaction Types:** Authorization, capture, sale, refund, void
- **Recurring Billing:** ARB (Automated Recurring Billing)
- **Customer Profiles:** CIM (Customer Information Manager)
- **Fraud Detection:** Advanced Fraud Detection Suite (AFDS)
- **Accept Hosted:** Secure hosted payment page option
- **Accept.js:** Direct API integration with tokenization

---

## 3. Technical Specifications

### API Integration
- **API:** Authorize.NET API v3.1
- **SDK:** authorizenet/authorizenet PHP SDK
- **Methods:** Accept.js (tokenization), Accept Hosted (iframe)
- **Authentication:** API Login ID + Transaction Key

### Database Tables
```sql
-- bkx_authnet_transactions
-- bkx_authnet_customer_profiles
-- bkx_authnet_recurring_profiles
-- bkx_authnet_refunds
```

---

## 4. Configuration

```php
[
    'authnet_mode' => 'live|sandbox',
    'api_login_id' => '',
    'transaction_key' => '',
    'signature_key' => '',
    'integration_method' => 'accept_js|accept_hosted',
    'enable_cim' => true,
    'enable_arb' => true,
    'enable_afds' => true,
    'require_cvv' => true,
]
```

---

## 5. Core Components

```php
class AuthorizeNetGateway extends PaymentGateway {
    - createTransaction()
    - createCustomerProfile()
    - createSubscription()
    - processRefund()
    - voidTransaction()
}

class AuthorizeNetFraudDetection {
    - evaluateTransaction()
    - applyFilters()
}
```

---

## 6. Development Timeline

- **Week 1-2:** Core payment processing
- **Week 3:** Customer profiles & recurring billing
- **Week 4:** Fraud detection & refunds
- **Week 5:** Testing & launch

**Total:** 5 weeks

---

**Status:** Ready for Development
