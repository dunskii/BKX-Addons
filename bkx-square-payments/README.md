# BookingX - Square Payments Add-on

Complete Square payment gateway integration for BookingX, supporting credit cards, Apple Pay, Google Pay, and Cash App Pay.

## Version
1.0.0

## Requirements
- **PHP**: 7.4+
- **WordPress**: 5.8+
- **BookingX**: 2.0+
- **Square Account**: Required (sign up at squareup.com)

## Features

### Payment Methods
- ✅ Credit Cards (Visa, Mastercard, Amex, Discover)
- ✅ Apple Pay
- ✅ Google Pay
- ✅ Cash App Pay

### Capabilities
- ✅ Secure tokenized payments (PCI-DSS SAQ-A compliant)
- ✅ SCA/3DS support for European payments
- ✅ Full and partial refunds
- ✅ Customer synchronization to Square
- ✅ Real-time webhook notifications
- ✅ Sandbox and production modes
- ✅ Multi-currency support (USD, CAD, GBP, EUR, AUD, JPY)
- ✅ Comprehensive transaction logging
- ✅ Debug mode for troubleshooting

## Installation

### 1. Install Dependencies
```bash
cd "C:\Users\dunsk\Code\Booking X\Add-ons\bkx-square-payments"
composer install
```

This will install:
- `square/square` (v43.0+) - Square PHP SDK
- Development dependencies (PHPUnit, PHPCS, PHPStan)

### 2. Activate Plugin
```bash
wp plugin activate bkx-square-payments
```

### 3. Configure Settings

Navigate to **BookingX > Settings > Square Payments** and configure:

#### API Credentials
Get these from your [Square Developer Dashboard](https://developer.squareup.com):

**Sandbox Mode (Testing):**
- Sandbox Application ID
- Sandbox Access Token
- Sandbox Location ID

**Production Mode (Live):**
- Production Application ID
- Production Access Token
- Production Location ID

#### Webhook Configuration
1. Copy the webhook URL: `https://yoursite.com/wp-json/bookingx/v1/webhooks/square`
2. Add it to Square Developer Dashboard > Webhooks
3. Subscribe to these events:
   - `payment.created`
   - `payment.updated`
   - `refund.created`
   - `refund.updated`
4. Copy the Webhook Signature Key and paste it in plugin settings

#### Payment Options
- Enable/disable Apple Pay
- Enable/disable Google Pay
- Enable/disable Cash App Pay
- Enable customer sync to Square
- Enable auto-refund on booking cancellation
- Select currency (USD, CAD, GBP, EUR, AUD, JPY)
- Enable debug logging

## File Structure

```
bkx-square-payments/
├── bkx-square-payments.php          # Main plugin file
├── composer.json                     # Dependencies
├── autoload.php                      # PSR-4 autoloader
├── uninstall.php                     # Cleanup on uninstall
├── readme.txt                        # WordPress plugin readme
├── README.md                         # Developer documentation
│
├── src/
│   ├── SquarePayments.php            # Main addon class
│   │
│   ├── Gateway/
│   │   └── SquareGateway.php         # Payment gateway implementation
│   │
│   ├── Api/
│   │   ├── SquareClient.php          # Square API client wrapper
│   │   └── WebhookController.php     # REST API webhook handler
│   │
│   ├── Services/
│   │   ├── PaymentService.php        # Payment processing logic
│   │   ├── RefundService.php         # Refund processing logic
│   │   ├── CustomerService.php       # Customer sync logic
│   │   └── WebhookService.php        # Webhook event processing
│   │
│   ├── Admin/
│   │   └── SettingsPage.php          # Admin settings UI
│   │
│   └── Migrations/
│       └── CreateSquareTables.php    # Database schema
│
├── assets/
│   ├── css/
│   │   ├── square-admin.css          # Admin styles
│   │   └── square-checkout.css       # Checkout styles
│   │
│   └── js/
│       ├── square-admin.js           # Admin JavaScript
│       └── square-checkout.js        # Square Web Payments SDK integration
│
├── templates/
│   └── checkout/
│       └── square-form.php           # Payment form template
│
└── languages/
    └── bkx-square-payments.pot       # Translation template
```

## Database Schema

### Table: `wp_bkx_square_transactions`
Stores payment transaction details.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT(20) | Primary key |
| `booking_id` | BIGINT(20) | BookingX booking ID |
| `square_payment_id` | VARCHAR(255) | Square payment ID |
| `square_order_id` | VARCHAR(255) | Square order ID (optional) |
| `square_customer_id` | VARCHAR(255) | Square customer ID (optional) |
| `amount_money` | BIGINT(20) | Amount in smallest currency unit (cents) |
| `currency` | VARCHAR(10) | Currency code (USD, EUR, etc.) |
| `status` | VARCHAR(50) | Payment status |
| `source_type` | VARCHAR(50) | Payment source (card, apple_pay, etc.) |
| `card_brand` | VARCHAR(50) | Card brand (Visa, Mastercard, etc.) |
| `last_4` | VARCHAR(4) | Last 4 digits of card |
| `receipt_url` | TEXT | Square receipt URL |
| `created_at` | DATETIME | Created timestamp |
| `updated_at` | DATETIME | Updated timestamp |

**Indexes:**
- `idx_booking_id` on `booking_id`
- `idx_square_payment_id` on `square_payment_id`
- `idx_square_customer_id` on `square_customer_id`
- `idx_status` on `status`

### Table: `wp_bkx_square_refunds`
Stores refund details.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT(20) | Primary key |
| `transaction_id` | BIGINT(20) | FK to transactions table |
| `square_refund_id` | VARCHAR(255) | Square refund ID |
| `amount_money` | BIGINT(20) | Refund amount in cents |
| `reason` | TEXT | Refund reason |
| `status` | VARCHAR(50) | Refund status |
| `created_at` | DATETIME | Created timestamp |
| `updated_at` | DATETIME | Updated timestamp |

**Indexes:**
- `idx_transaction_id` on `transaction_id`
- `idx_square_refund_id` on `square_refund_id`
- `idx_status` on `status`

### Table: `wp_bkx_square_webhook_events`
Stores webhook events for debugging and duplicate prevention.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT(20) | Primary key |
| `square_event_id` | VARCHAR(255) | Square event ID |
| `event_type` | VARCHAR(100) | Event type |
| `payload` | LONGTEXT | Full webhook payload (JSON) |
| `processed_at` | DATETIME | Processing timestamp |

**Indexes:**
- `idx_square_event_id` on `square_event_id`
- `idx_event_type` on `event_type`

## Developer Hooks

### Actions

```php
// After successful payment
do_action( 'bkx_square_payment_completed', $booking_id, $payment, $transaction_id );

// After successful refund
do_action( 'bkx_square_refund_completed', $booking_id, $refund, $refund_id );

// When payment webhook is received
do_action( 'bkx_square_payment_webhook', $booking_id, $status, $payment_data );

// When refund webhook is received
do_action( 'bkx_square_refund_webhook', $booking_id, $status, $refund_data );

// When addon is activated
do_action( 'bkx_square_payments_activated' );

// When addon is deactivated
do_action( 'bkx_square_payments_deactivated' );
```

### Filters

```php
// Register payment gateway
add_filter( 'bkx_payment_gateways', function( $gateways ) {
    // Square gateway is auto-registered
    return $gateways;
} );

// Modify payment request before sending to Square
add_filter( 'bkx_square_payment_request', function( $request, $booking_id ) {
    // Modify request data
    return $request;
}, 10, 2 );

// Modify refund request before sending to Square
add_filter( 'bkx_square_refund_request', function( $request, $booking_id ) {
    // Modify request data
    return $request;
}, 10, 2 );
```

## Usage Examples

### Process a Payment (Programmatically)

```php
$gateway = bkx_square_payments()->get_gateway();

$payment_data = array(
    'source_id'          => 'cnon:card-nonce-ok', // From Square SDK
    'amount'             => 50.00,
    'nonce'              => wp_create_nonce( 'bkx_square_checkout' ),
    'customer_email'     => 'customer@example.com',
    'customer_name'      => 'John Doe',
    'verification_token' => '', // Optional for SCA
);

$result = $gateway->process_payment( $booking_id, $payment_data );

if ( $result['success'] ) {
    echo 'Payment successful!';
    echo 'Payment ID: ' . $result['data']['payment_id'];
} else {
    echo 'Payment failed: ' . $result['error'];
}
```

### Process a Refund

```php
$gateway = bkx_square_payments()->get_gateway();

$result = $gateway->process_refund(
    $booking_id,
    25.00,                    // Refund $25
    'Customer requested',     // Reason
    $transaction_id           // Optional
);

if ( $result['success'] ) {
    echo 'Refund successful!';
} else {
    echo 'Refund failed: ' . $result['error'];
}
```

### Access Settings

```php
$addon = bkx_square_payments();

// Get a specific setting
$mode = $addon->get_setting( 'square_mode', 'sandbox' );
$app_id = $addon->get_setting( 'square_sandbox_application_id', '' );

// Check if Apple Pay is enabled
$apple_pay_enabled = $addon->get_setting( 'enable_apple_pay', false );

// Update a setting
$addon->update_setting( 'debug_log', true );
```

## Testing

### Sandbox Mode Testing

Use these test card numbers in sandbox mode:

- **Success**: `4111 1111 1111 1111`
- **Decline**: `4000 0000 0000 0002`
- **Insufficient Funds**: `4000 0000 0000 9995`
- **CVV Mismatch**: Use any valid card with CVV `200`

For complete testing guide, see: https://developer.squareup.com/docs/devtools/sandbox/payments

### Unit Tests

```bash
composer test
```

### Code Standards

```bash
# Check coding standards
composer run phpcs

# Static analysis
composer run phpstan
```

## Security

### PCI Compliance
- Card data is tokenized client-side via Square Web Payments SDK
- No card data ever touches your server
- Meets PCI-DSS SAQ-A compliance requirements

### Webhook Verification
- All webhooks are verified using HMAC-SHA256 signatures
- Duplicate events are prevented via event ID tracking
- Signature key must be configured in settings

### Data Encryption
- API credentials should be stored securely
- Consider using environment variables for production credentials
- Access tokens are treated as sensitive data

### Nonce Verification
- All AJAX requests require valid nonces
- Payment requests verify `bkx_square_checkout` nonce
- Admin requests verify `bkx_square_admin` nonce

## Troubleshooting

### Enable Debug Logging
1. Go to Settings > Square Payments
2. Enable "Debug Logging"
3. Check logs in `wp-content/uploads/bkx-logs/`

### Common Issues

**"Square API client is not initialized"**
- Check that all API credentials are entered correctly
- Verify you're using the correct mode (sandbox/production)
- Ensure credentials match the selected mode

**"Webhook signature verification failed"**
- Verify the webhook signature key in settings
- Ensure the webhook URL is exactly as shown in settings
- Check that HTTPS is enabled (required for webhooks)

**Payment declined**
- Check transaction details in Square Dashboard
- Review decline reason in error message
- Verify card details and try again

**Apple Pay not showing**
- Verify domain with Apple in Square Dashboard
- Ensure HTTPS is enabled
- Check that Apple Pay is enabled in settings

## Support

For support:
- Documentation: https://bookingx.com/docs/square-payments
- Support: support@bookingx.com
- Square API Docs: https://developer.squareup.com/docs

## License

GPL-2.0-or-later

## Credits

- Developed by the BookingX team
- Uses Square PHP SDK: https://github.com/square/square-php-sdk
- Built on the BookingX Add-on SDK
