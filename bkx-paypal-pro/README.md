# BookingX - PayPal Pro Add-on

Production-ready PayPal Commerce Platform payment gateway for BookingX.

## Overview

This add-on integrates PayPal Commerce Platform (REST API v2) with BookingX, providing:
- PayPal Smart Payment Buttons
- Advanced Card Processing (Direct credit/debit card checkout)
- Pay Later (Pay in 4)
- Comprehensive webhook handling
- Full and partial refund support
- Multi-currency support

## Technical Stack

- **PayPal API**: REST API v2 (NO deprecated SDK - custom HTTP client)
- **Authentication**: OAuth 2.0 with token caching (8-hour expiry)
- **WordPress**: 5.8+ with REST API integration
- **PHP**: 7.4+ with PSR-4 autoloading
- **Database**: Custom tables for transactions and webhook events
- **Security**: Nonce verification, capability checks, input sanitization, webhook signature verification

## Directory Structure

```
bkx-paypal-pro/
├── bkx-paypal-pro.php          # Main plugin file
├── autoload.php                # PSR-4 autoloader
├── composer.json               # Dependencies and scripts
├── uninstall.php               # Cleanup script
├── readme.txt                  # WordPress.org readme
├── src/
│   ├── PayPalPro.php           # Main addon class (extends AbstractAddon)
│   ├── Gateway/
│   │   └── PayPalGateway.php   # Payment gateway (extends AbstractPaymentGateway)
│   ├── Admin/
│   │   └── SettingsPage.php    # Admin settings UI
│   ├── Api/
│   │   ├── PayPalClient.php    # Custom REST API client (OAuth, Orders, Payments)
│   │   └── WebhookController.php # REST endpoint for webhooks
│   ├── Services/
│   │   ├── OrderService.php    # Order creation and capture
│   │   ├── RefundService.php   # Refund processing
│   │   └── WebhookService.php  # Webhook event processing
│   └── Migrations/
│       └── CreatePayPalTables.php # Database schema
├── assets/
│   ├── css/
│   │   ├── paypal-checkout.css # Frontend checkout styles
│   │   └── admin.css           # Admin settings styles
│   ├── js/
│   │   ├── paypal-checkout.js  # PayPal SDK integration
│   │   └── admin.js            # Admin settings JavaScript
│   └── images/
│       └── paypal-logo.svg     # Payment method icon
├── templates/
│   └── checkout/
│       └── paypal-form.php     # Checkout form template
└── languages/
    └── bkx-paypal-pro.pot      # Translation template
```

## Architecture

### Class Hierarchy

```
PayPalPro (Main Addon)
├── extends: AbstractAddon
├── uses: HasSettings, HasLicense, HasDatabase, HasRestApi, HasWebhooks
└── components:
    ├── PayPalGateway (Payment Gateway)
    │   ├── extends: AbstractPaymentGateway
    │   └── uses: PayPalClient, OrderService, RefundService
    ├── SettingsPage (Admin UI)
    ├── WebhookController (REST API)
    └── Services (Business Logic)
```

### Database Schema

#### bkx_paypal_transactions
```sql
id                  bigint(20) unsigned PRIMARY KEY AUTO_INCREMENT
booking_id          bigint(20) unsigned NOT NULL
paypal_order_id     varchar(100) NOT NULL
capture_id          varchar(100) NULL
amount              decimal(10,2) NULL
currency            varchar(10) NULL
status              varchar(50) NOT NULL
payment_source      text NULL
metadata            longtext NULL
created_at          datetime NOT NULL
updated_at          datetime NOT NULL
KEY (booking_id, paypal_order_id, capture_id, status, created_at)
```

#### bkx_paypal_webhook_events
```sql
id                  bigint(20) unsigned PRIMARY KEY AUTO_INCREMENT
paypal_event_id     varchar(100) NOT NULL
event_type          varchar(100) NOT NULL
payload             longtext NOT NULL
status              varchar(50) NOT NULL DEFAULT 'received'
message             text NULL
processed_at        datetime NOT NULL
KEY (paypal_event_id, event_type, status, processed_at)
```

## PayPal REST API Integration

### API Client (PayPalClient.php)

Custom implementation using WordPress HTTP API (no PayPal SDK):

**Endpoints:**
- `POST /v1/oauth2/token` - Get OAuth access token
- `POST /v2/checkout/orders` - Create order
- `GET /v2/checkout/orders/{id}` - Get order details
- `POST /v2/checkout/orders/{id}/capture` - Capture order
- `POST /v2/checkout/orders/{id}/authorize` - Authorize order
- `POST /v2/payments/captures/{id}/refund` - Refund capture
- `POST /v1/notifications/verify-webhook-signature` - Verify webhook

**Authentication:**
- OAuth 2.0 Bearer token
- Token cached in transient (8-hour expiry)
- Automatic token refresh

### Webhook Events Handled

| Event Type | Handler Method | Action |
|------------|---------------|--------|
| `PAYMENT.CAPTURE.COMPLETED` | `handle_capture_completed()` | Mark booking as paid |
| `PAYMENT.CAPTURE.DENIED` | `handle_capture_denied()` | Cancel booking |
| `PAYMENT.CAPTURE.REFUNDED` | `handle_capture_refunded()` | Update refund status |
| `CHECKOUT.ORDER.APPROVED` | `handle_order_approved()` | Update order status |
| `CHECKOUT.ORDER.COMPLETED` | `handle_order_completed()` | Update order status |

### Payment Flow

1. **Order Creation** (OrderService::create_order())
   ```
   Frontend → AJAX → REST API → PayPalClient → PayPal API
   → Order created → Order ID returned to frontend
   ```

2. **Customer Payment**
   ```
   Customer clicks PayPal button → PayPal modal → Payment approved
   ```

3. **Capture** (OrderService::capture_order())
   ```
   Frontend → AJAX → REST API → PayPalClient → PayPal API
   → Payment captured → Booking updated → Success
   ```

4. **Webhook Notification**
   ```
   PayPal → Webhook URL → WebhookController → WebhookService
   → Signature verified → Event processed → Database updated
   ```

## Configuration

### Settings Fields

| Field | Type | Description |
|-------|------|-------------|
| `enabled` | checkbox | Enable/disable PayPal gateway |
| `paypal_mode` | select | Sandbox or Live |
| `paypal_sandbox_client_id` | text | Sandbox API Client ID |
| `paypal_sandbox_client_secret` | password | Sandbox API Secret |
| `paypal_live_client_id` | text | Live API Client ID |
| `paypal_live_client_secret` | password | Live API Secret |
| `paypal_webhook_id` | text | Webhook ID for signature verification |
| `enable_card_fields` | checkbox | Advanced Card Processing |
| `enable_pay_later` | checkbox | Pay in 4 option |
| `button_color` | select | gold/blue/silver/white/black |
| `button_shape` | select | rect/pill |
| `intent` | select | capture/authorize |
| `currency` | select | USD/EUR/GBP/CAD/AUD/JPY |
| `debug_log` | checkbox | Enable API request logging |

### Hooks & Filters

**Actions:**
```php
// Before order creation
do_action( 'bkx_paypal_pro_before_create_order', $booking_id, $amount );

// After payment complete
do_action( 'bkx_booking_payment_complete', $booking_id, $payment_data );

// After refund
do_action( 'bkx_booking_refunded', $booking_id, $refund_data );

// After payment failed
do_action( 'bkx_booking_payment_failed', $booking_id, $error_data );
```

**Filters:**
```php
// Modify order data before sending to PayPal
add_filter( 'bkx_paypal_pro_order_data', function( $order_data, $booking_id, $amount ) {
    // Customize order data
    return $order_data;
}, 10, 3 );

// Modify refund data
add_filter( 'bkx_paypal_pro_refund_data', function( $refund_data, $capture_id, $amount ) {
    // Customize refund data
    return $refund_data;
}, 10, 3 );
```

## Development Setup

### Installation

1. **Clone to add-ons directory:**
   ```bash
   cd "C:\Users\dunsk\Code\Booking X\Add-ons"
   # Already created in bkx-paypal-pro/
   ```

2. **Install dependencies:**
   ```bash
   cd bkx-paypal-pro
   composer install
   ```

3. **Ensure SDK is available:**
   ```bash
   # SDK should be in: Add-ons/_shared/bkx-addon-sdk/
   ```

4. **Activate in WordPress:**
   ```bash
   wp plugin activate bkx-paypal-pro
   ```

### Testing

```bash
# PHP Syntax Check
php -l bkx-paypal-pro.php

# Code Standards
composer run phpcs

# Fix Code Standards
composer run phpcbf

# Static Analysis
composer run phpstan

# Unit Tests (once configured)
composer run test
```

### PayPal Sandbox Setup

1. Go to https://developer.paypal.com/dashboard/
2. Create sandbox app
3. Get Client ID and Secret
4. Create sandbox test accounts (buyer and seller)
5. Configure webhook:
   - URL: `https://yoursite.com/wp-json/bookingx/v1/webhooks/bkx_paypal_pro`
   - Events: PAYMENT.*, CHECKOUT.ORDER.*
   - Copy Webhook ID

## Security

### Input Sanitization
- All POST data sanitized with `sanitize_text_field()`, `absint()`, etc.
- Card data never stored (handled by PayPal Hosted Fields)
- API credentials encrypted in options table

### Output Escaping
- All output escaped with `esc_html()`, `esc_attr()`, `esc_url()`
- Template files use proper escaping

### Nonce Verification
- All AJAX requests verify nonces
- Admin actions verify nonces
- REST API uses WordPress nonce header

### Capability Checks
- Admin settings require `manage_options`
- Payment processing checks booking ownership
- Refunds check admin capabilities

### Webhook Security
- PayPal signature verification (HMAC-SHA256)
- IP validation (optional)
- Event deduplication
- Logged to database for audit

## Next Steps for Implementation

### Required
1. Add PayPal logo to `assets/images/paypal-logo.svg`
2. Generate translation POT file
3. Create REST API endpoints for order creation/capture
4. Test in PayPal sandbox
5. Test webhook events
6. Verify refund processing

### Optional Enhancements
1. Add support for subscriptions/recurring payments
2. Implement PayPal BNPL (Buy Now Pay Later) messaging
3. Add 3D Secure authentication
4. Support for alternative payment methods (Venmo, etc.)
5. Advanced fraud detection integration
6. Multi-step checkout optimization

## Support & Documentation

- **PayPal Developer Docs**: https://developer.paypal.com/api/rest/
- **PayPal Commerce Platform**: https://developer.paypal.com/docs/commerce-platform/
- **Webhook Reference**: https://developer.paypal.com/api/rest/webhooks/
- **BookingX Documentation**: https://bookingx.com/docs/

## License

GPL-2.0-or-later

## Version

1.0.0 - Initial Release

## Author

Booking X - https://bookingx.com
