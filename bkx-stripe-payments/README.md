# BookingX - Stripe Payments Add-on

Complete Stripe payment gateway integration for BookingX booking system.

## Overview

This add-on provides seamless integration with Stripe's payment processing platform, supporting modern payment methods including credit cards, Apple Pay, Google Pay, and Stripe Link. Built on the BookingX Add-on SDK with comprehensive security features and WordPress coding standards compliance.

## Features

### Payment Processing
- **Stripe PaymentIntents API** - Latest Stripe API with full SCA support
- **3D Secure Authentication** - Automatic Strong Customer Authentication
- **Manual/Automatic Capture** - Authorize now, capture later
- **Multiple Payment Methods** - Cards, Apple Pay, Google Pay, Link
- **Saved Payment Methods** - Customer payment method storage
- **Zero-Decimal Currencies** - Support for JPY, KRW, etc.

### Subscriptions
- **Recurring Bookings** - Full subscription support
- **Subscription Management** - Create, update, cancel subscriptions
- **Webhook Synchronization** - Real-time subscription status updates

### Refunds & Disputes
- **Full & Partial Refunds** - Process refunds via admin or automatically
- **Auto-Refund on Cancel** - Configurable automatic refunds
- **Dispute Notifications** - Alert admins of chargebacks

### Security
- **Webhook Signature Verification** - Stripe signature validation
- **PCI Compliance** - No card data touches your server
- **Encrypted API Keys** - AES-256 encryption for credentials
- **Nonce Verification** - CSRF protection on all endpoints
- **Capability Checks** - Proper authorization checks

### Developer Features
- **Test Mode** - Full sandbox environment
- **Comprehensive Logging** - Debug mode with detailed logs
- **REST API** - RESTful endpoints for frontend integration
- **Action Hooks** - Extensible via WordPress hooks
- **PHPDoc Blocks** - Complete inline documentation

## Requirements

- **WordPress**: 5.8+
- **PHP**: 7.4+
- **BookingX**: 2.0+
- **Stripe Account**: Active Stripe account
- **SSL Certificate**: Required for live payments

## Installation

### 1. Install Dependencies

```bash
cd C:\Users\dunsk\Code\Booking X\Add-ons\bkx-stripe-payments
composer install
```

This will install:
- `stripe/stripe-php` (^10.0) - Official Stripe PHP library
- BookingX Add-on SDK (via local path)

### 2. Activate Plugin

```bash
wp plugin activate bkx-stripe-payments --path="C:\Users\dunsk\Local Sites\booking-x\app\public"
```

Or activate via WordPress Admin → Plugins.

### 3. Configure API Keys

1. Navigate to **BookingX → Settings → Stripe Payments**
2. Enter your Stripe API keys:
   - Test Publishable Key (pk_test_...)
   - Test Secret Key (sk_test_...)
   - Live Publishable Key (pk_live_...)
   - Live Secret Key (sk_live_...)

### 4. Configure Webhooks

1. Go to [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks)
2. Click **"Add endpoint"**
3. Enter webhook URL: `https://yoursite.com/wp-json/bookingx/v1/stripe/webhook`
4. Select events:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `payment_intent.canceled`
   - `charge.refunded`
   - `charge.dispute.created`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
5. Copy the **Signing secret** (whsec_...) to plugin settings

## Architecture

### Directory Structure

```
bkx-stripe-payments/
├── bkx-stripe-payments.php    # Main plugin file
├── composer.json               # Composer dependencies
├── autoload.php               # PSR-4 autoloader
├── uninstall.php              # Uninstall cleanup
├── readme.txt                 # WordPress.org readme
├── .gitignore                 # Git ignore rules
│
├── src/                       # Source code (PSR-4)
│   ├── StripePayments.php     # Main addon class
│   ├── Gateway/
│   │   └── StripeGateway.php  # Payment gateway implementation
│   ├── Admin/
│   │   └── SettingsPage.php   # Admin settings UI
│   ├── Api/
│   │   └── WebhookController.php  # REST API controller
│   ├── Services/
│   │   ├── PaymentService.php     # Payment processing
│   │   ├── RefundService.php      # Refund handling
│   │   ├── CustomerService.php    # Customer management
│   │   └── WebhookService.php     # Webhook processing
│   └── Migrations/
│       └── CreateStripeTables.php # Database setup
│
├── assets/                    # Frontend assets
│   ├── css/
│   │   ├── admin.css         # Admin styles
│   │   └── stripe-checkout.css # Checkout styles
│   └── js/
│       ├── admin.js          # Admin JavaScript
│       └── stripe-checkout.js # Stripe.js integration
│
├── templates/                 # PHP templates
│   └── checkout/
│       └── stripe-form.php    # Payment form template
│
└── languages/                 # Translation files
    └── bkx-stripe-payments.pot
```

### Database Schema

#### `wp_bkx_stripe_transactions`
Stores payment transaction records.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT(20) | Primary key |
| booking_id | BIGINT(20) | Related booking ID |
| stripe_payment_intent_id | VARCHAR(100) | Stripe PaymentIntent ID |
| stripe_transaction_id | VARCHAR(100) | Stripe Charge ID |
| stripe_customer_id | VARCHAR(100) | Stripe Customer ID |
| amount | DECIMAL(10,2) | Payment amount |
| currency | VARCHAR(10) | Currency code |
| status | VARCHAR(50) | Payment status |
| payment_method_type | VARCHAR(50) | Payment method type |
| metadata | TEXT | JSON metadata |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Update timestamp |

**Indexes:** booking_id, stripe_payment_intent_id, status

#### `wp_bkx_stripe_subscriptions`
Stores recurring subscription records.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT(20) | Primary key |
| booking_id | BIGINT(20) | Related booking ID |
| stripe_subscription_id | VARCHAR(100) | Stripe Subscription ID (unique) |
| stripe_customer_id | VARCHAR(100) | Stripe Customer ID |
| status | VARCHAR(50) | Subscription status |
| current_period_start | DATETIME | Period start |
| current_period_end | DATETIME | Period end |
| cancel_at_period_end | TINYINT(1) | Cancel flag |
| metadata | TEXT | JSON metadata |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Update timestamp |

#### `wp_bkx_stripe_refunds`
Stores refund records.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT(20) | Primary key |
| booking_id | BIGINT(20) | Related booking ID |
| transaction_id | BIGINT(20) | Original transaction ID |
| stripe_refund_id | VARCHAR(100) | Stripe Refund ID |
| stripe_payment_intent_id | VARCHAR(100) | PaymentIntent ID |
| amount | DECIMAL(10,2) | Refund amount |
| currency | VARCHAR(10) | Currency code |
| status | VARCHAR(50) | Refund status |
| reason | VARCHAR(100) | Refund reason |
| metadata | TEXT | JSON metadata |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Update timestamp |

#### `wp_bkx_stripe_webhook_events`
Logs all webhook events for debugging.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT(20) | Primary key |
| stripe_event_id | VARCHAR(100) | Stripe Event ID (unique) |
| event_type | VARCHAR(100) | Event type |
| payload | LONGTEXT | Full event payload |
| created_at | TIMESTAMP | Event timestamp |

## Usage

### Basic Payment Flow

1. Customer selects Stripe as payment method
2. Frontend creates PaymentIntent via REST API
3. Stripe Payment Element mounts on page
4. Customer enters card details
5. JavaScript confirms payment with 3D Secure if needed
6. Webhook updates booking status
7. Customer redirected to confirmation page

### Code Examples

#### Processing a Payment

```php
$addon = bkx_stripe();
$payment_service = $addon->get_payment_service();

$result = $payment_service->process_payment( $booking_id, [
    'payment_method_id' => 'pm_xxx',
] );

if ( $result['success'] ) {
    // Payment successful
    echo 'Payment ID: ' . $result['data']['payment_intent_id'];
} else {
    // Payment failed
    echo 'Error: ' . $result['error'];
}
```

#### Processing a Refund

```php
$addon = bkx_stripe();
$refund_service = $addon->get_refund_service();

$result = $refund_service->process_refund(
    $booking_id,
    50.00, // Amount
    'Customer requested refund'
);

if ( $result['success'] ) {
    echo 'Refund ID: ' . $result['refund_id'];
}
```

#### Capturing an Authorized Payment

```php
$addon = bkx_stripe();
$payment_service = $addon->get_payment_service();

$result = $payment_service->capture_payment( $booking_id );
```

### Action Hooks

```php
// After payment succeeds
add_action( 'bkx_stripe_payment_succeeded', function( $booking_id, $payment_intent ) {
    // Send confirmation email, update inventory, etc.
}, 10, 2 );

// After payment fails
add_action( 'bkx_stripe_payment_failed', function( $booking_id, $payment_intent ) {
    // Log failure, notify customer
}, 10, 2 );

// After charge is refunded
add_action( 'bkx_stripe_charge_refunded', function( $booking_id, $charge ) {
    // Update booking, send notification
}, 10, 2 );

// When dispute is created
add_action( 'bkx_stripe_dispute_created', function( $booking_id, $dispute ) {
    // Alert admin, gather evidence
}, 10, 2 );
```

## Security Features

### 1. Webhook Signature Verification
All webhooks are verified using Stripe's signature validation:

```php
$event = Webhook::constructEvent(
    $payload,
    $signature,
    $webhook_secret
);
```

### 2. Nonce Verification
All AJAX and REST requests verify WordPress nonces:

```php
if ( ! wp_verify_nonce( $nonce, 'bkx_stripe_nonce' ) ) {
    wp_die( 'Security check failed.' );
}
```

### 3. Capability Checks
Admin operations require proper capabilities:

```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Insufficient permissions.' );
}
```

### 4. Encrypted Storage
API keys are encrypted before storage using the SDK's EncryptionService.

### 5. Escaping & Sanitization
All output is escaped, all input is sanitized:

```php
echo esc_html( $user_input );
$booking_id = absint( $_POST['booking_id'] );
```

## Testing

### Test Cards

Use these test cards in **Test Mode**:

| Card Number | Scenario |
|-------------|----------|
| 4242 4242 4242 4242 | Success |
| 4000 0025 0000 3155 | Requires 3D Secure |
| 4000 0000 0000 9995 | Declined |
| 4000 0000 0000 0069 | Expired card |

### Running Tests

```bash
# Run PHPUnit tests
composer run test

# Run PHPCS code standards check
composer run phpcs

# Run PHPStan static analysis
composer run phpstan
```

## Configuration Options

### Payment Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| stripe_mode | select | test | Test or Live mode |
| stripe_live_publishable_key | text | - | Live publishable key |
| stripe_live_secret_key | encrypted | - | Live secret key |
| stripe_test_publishable_key | text | - | Test publishable key |
| stripe_test_secret_key | encrypted | - | Test secret key |
| stripe_webhook_secret | encrypted | - | Webhook signing secret |

### Features

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| enable_apple_pay | checkbox | false | Enable Apple Pay |
| enable_google_pay | checkbox | false | Enable Google Pay |
| enable_link | checkbox | false | Enable Stripe Link |
| save_payment_methods | checkbox | true | Save payment methods |

### Capture & Refunds

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| capture_method | select | automatic | Automatic or manual capture |
| auto_refund_on_cancel | checkbox | false | Auto-refund on booking cancel |

### Advanced

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| statement_descriptor | text | Site name | Credit card statement text |
| enable_3d_secure | checkbox | true | Enable 3D Secure/SCA |
| radar_risk_threshold | number | 75 | Stripe Radar risk threshold |
| debug_log | checkbox | false | Enable detailed logging |

## Troubleshooting

### Common Issues

**"Stripe API key not configured"**
- Check that API keys are entered in settings
- Ensure keys match the selected mode (test/live)
- Verify keys start with correct prefix (pk_/sk_)

**"Webhook signature verification failed"**
- Ensure webhook secret is correctly copied from Stripe
- Check that webhook URL matches exactly
- Verify SSL certificate is valid

**"Payment requires action"**
- Customer needs to complete 3D Secure authentication
- Ensure Stripe.js is loaded correctly
- Check browser console for JavaScript errors

**"No successful payment found for this booking"**
- Payment may have failed or not completed
- Check transaction table for status
- Review Stripe Dashboard for payment details

### Debug Mode

Enable debug logging in settings to view detailed logs:

```php
$addon->get_logger()->info( 'Payment processed', [
    'booking_id' => $booking_id,
    'amount' => $amount,
] );
```

Logs are stored in: `wp-content/uploads/bkx-addon-logs/bkx_stripe_payments.log`

## Performance Considerations

- **Lazy Loading**: Stripe.js only loads on booking pages
- **Caching**: Customer IDs cached to reduce API calls
- **Async Webhooks**: Webhook processing is non-blocking
- **Database Indexes**: Optimized queries with proper indexing

## Support

- **Documentation**: https://bookingx.com/docs/stripe-payments
- **Support**: https://bookingx.com/support
- **Stripe Docs**: https://stripe.com/docs

## License

GPL-2.0-or-later

## Credits

Developed by BookingX Team using the BookingX Add-on SDK.

Built with [Stripe PHP](https://github.com/stripe/stripe-php) library.
