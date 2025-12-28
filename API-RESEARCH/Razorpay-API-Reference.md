# Razorpay Payment Gateway API Integration Reference

## Overview

- **API Base URL**: `https://api.razorpay.com/v1`
- **Official Documentation**: https://razorpay.com/docs/api/
- **API Reference**: https://razorpay.com/docs/api/
- **PHP SDK**: `composer require razorpay/razorpay`
- **SDK Repository**: https://github.com/razorpay/razorpay-php
- **Current API Version**: v1 (2024-2025)

---

## Authentication

### Method: Basic Auth

Razorpay uses **Basic Authentication** for all API requests.

```php
// Authentication using PHP SDK
use Razorpay\Api\Api;

$api = new Api($keyId, $keySecret);
```

### Authorization Header Format

```
Authorization: Basic base64(KEY_ID:KEY_SECRET)
```

**Critical Format Requirements:**
- Must be exactly `Basic base64token` (capital B, lowercase asic)
- Invalid formats include: `BASIC`, `basic`, `Basic "base64token"`, `Basic $base64token`

### Required Credentials

| Credential | Description | Where to Get |
|------------|-------------|--------------|
| **Key ID** | Public API identifier | Dashboard → Account & Settings → API Keys |
| **Key Secret** | Private API secret (never share) | Dashboard → Account & Settings → API Keys |

### Test Mode vs Live Mode

| Mode | Purpose | Key Generation |
|------|---------|----------------|
| **Test Mode** | Integration testing, no real money | Dashboard → API Keys → Generate Test Key |
| **Live Mode** | Accept real customer payments | Dashboard → API Keys → Generate Live Key |

**Important Notes:**
- Only ONE set of API keys can be active per mode at a time
- Test mode payments cannot process real transactions
- Keys apply universally across all whitelisted websites/apps
- Download and securely store keys immediately after generation
- Never share Key Secret publicly or in version control
- Regenerate keys if compromised or lost

---

## PHP SDK Installation

### Via Composer (Recommended)

```bash
composer require razorpay/razorpay
```

### Manual Installation

Download the latest release from https://github.com/razorpay/razorpay-php/releases and include:

```php
require_once 'path/to/razorpay-php/Razorpay.php';
```

### Basic SDK Setup

```php
<?php
require_once 'vendor/autoload.php';

use Razorpay\Api\Api;

$keyId = 'rzp_test_xxxxxxxxxxxxxxxx';
$keySecret = 'xxxxxxxxxxxxxxxxxxxxxxxxx';

$api = new Api($keyId, $keySecret);
```

---

## Core Endpoints

### 1. Create Order

**Purpose**: Create an order before accepting payment. Orders prevent tampering with payment amounts and enable automatic capture.

**Endpoint:**
```
POST /v1/orders
```

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `amount` | integer | Yes | Amount in smallest currency unit (e.g., 5000 = ₹50.00 for INR) |
| `currency` | string | Yes | ISO 3-letter currency code (e.g., "INR", "USD") |
| `receipt` | string | No | Your internal reference number (max 40 chars, must be unique) |
| `notes` | object | No | Key-value metadata (max 15 pairs, 256 chars each) |

**Important Currency Notes:**
- For INR: 5000 = ₹50.00
- For three-decimal currencies (KWD, BHD, OMR): Last digit must be "0" (e.g., 99990 for 99.991 KD)

**Request Example (cURL):**

```bash
curl -u rzp_test_xxxxxxx:xxxxxxxxx \
  -X POST https://api.razorpay.com/v1/orders \
  -H "content-type: application/json" \
  -d '{
    "amount": 5000,
    "currency": "INR",
    "receipt": "booking_123",
    "notes": {
      "booking_id": "123",
      "customer_name": "John Doe"
    }
  }'
```

**Request Example (PHP SDK):**

```php
$orderData = [
    'amount'   => 5000, // Amount in paise (₹50)
    'currency' => 'INR',
    'receipt'  => 'booking_123',
    'notes'    => [
        'booking_id'    => '123',
        'customer_name' => 'John Doe'
    ]
];

try {
    $order = $api->order->create($orderData);
    $orderId = $order->id;
} catch (\Exception $e) {
    // Handle error
    error_log('Order creation failed: ' . $e->getMessage());
}
```

**Response Format:**

```json
{
  "id": "order_RB58MiP5SPFYyM",
  "entity": "order",
  "amount": 5000,
  "amount_paid": 0,
  "amount_due": 5000,
  "currency": "INR",
  "receipt": "booking_123",
  "status": "created",
  "attempts": 0,
  "notes": {
    "booking_id": "123",
    "customer_name": "John Doe"
  },
  "created_at": 1756455561,
  "offer_id": null
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Unique order identifier (starts with `order_`) |
| `entity` | string | Always "order" |
| `amount` | integer | Order amount in smallest currency unit |
| `amount_paid` | integer | Amount received so far (0 for new orders) |
| `amount_due` | integer | Remaining amount to be paid |
| `currency` | string | Currency code |
| `receipt` | string | Your internal reference |
| `status` | string | `created`, `attempted`, or `paid` |
| `attempts` | integer | Number of payment attempts (successful + failed) |
| `notes` | object | Your custom metadata |
| `created_at` | integer | Unix timestamp |

**Order Status Flow:**
- `created` → Order created, no payment attempted yet
- `attempted` → Payment attempt in progress, stays until successful capture
- `paid` → Payment captured successfully, no further payments allowed

**Common Errors:**

| Status | Error | Description | Resolution |
|--------|-------|-------------|------------|
| 401 | Authentication failed | Invalid API credentials | Verify Key ID and Secret match Dashboard |
| 400 | Order amount less than minimum | Amount below ₹1.00 (100 paise) | Increase amount to minimum |
| 400 | Field required | Missing mandatory parameter | Check all required fields are provided |

---

### 2. Standard Checkout Integration

**Purpose**: Display Razorpay's hosted checkout form to collect payment from customers.

**Checkout Script:**

```html
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
```

**Frontend JavaScript Integration:**

```javascript
var options = {
    "key": "rzp_test_xxxxxxxxxxxxxxxx", // Your API Key ID
    "amount": "5000", // Amount in paise
    "currency": "INR",
    "name": "Your Business Name",
    "description": "Booking Payment",
    "image": "https://yourdomain.com/logo.png",
    "order_id": "order_RB58MiP5SPFYyM", // Order ID from Create Order API
    "callback_url": "https://yourdomain.com/payment/callback",
    "prefill": {
        "name": "John Doe",
        "email": "john@example.com",
        "contact": "9999999999"
    },
    "notes": {
        "booking_id": "123"
    },
    "theme": {
        "color": "#F37254"
    }
};

var rzp1 = new Razorpay(options);
rzp1.open();
```

**Using Handler Function (Alternative to callback_url):**

```javascript
var options = {
    // ... other options
    "handler": function (response){
        // Send these to your server for verification
        console.log(response.razorpay_payment_id);
        console.log(response.razorpay_order_id);
        console.log(response.razorpay_signature);

        // AJAX call to verify signature on server
        fetch('/verify-payment', {
            method: 'POST',
            body: JSON.stringify(response),
            headers: {'Content-Type': 'application/json'}
        });
    }
};
```

**Checkout Response (on success):**

The checkout returns these three critical values:

| Field | Description |
|-------|-------------|
| `razorpay_payment_id` | Unique payment identifier (e.g., `pay_xxxxx`) |
| `razorpay_order_id` | Order ID that was passed to checkout |
| `razorpay_signature` | HMAC SHA256 signature for verification |

**MANDATORY: Server-Side Signature Verification**

This step confirms the payment is authentic and not tampered with.

**PHP Signature Verification (using SDK):**

```php
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

$api = new Api($keyId, $keySecret);

$attributes = [
    'razorpay_order_id'   => $_POST['razorpay_order_id'],
    'razorpay_payment_id' => $_POST['razorpay_payment_id'],
    'razorpay_signature'  => $_POST['razorpay_signature']
];

try {
    $api->utility->verifyPaymentSignature($attributes);
    // Signature is valid - payment is authentic
    // Update booking status to confirmed
} catch(SignatureVerificationError $e) {
    // Signature verification failed - possible fraud
    // Log error and do NOT confirm booking
    error_log('Payment signature verification failed: ' . $e->getMessage());
}
```

**Manual Signature Verification (without SDK):**

```php
$razorpayOrderId   = $_POST['razorpay_order_id'];
$razorpayPaymentId = $_POST['razorpay_payment_id'];
$razorpaySignature = $_POST['razorpay_signature'];
$keySecret         = 'your_key_secret';

// Construct the expected signature
$generatedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $keySecret);

if (hash_equals($generatedSignature, $razorpaySignature)) {
    // Valid signature
} else {
    // Invalid signature
}
```

**Important Integration Notes:**
- Payments without `order_id` cannot be captured and are auto-refunded
- Always verify signature on server-side (never trust client-side data)
- Use the `order_id` from YOUR database, not `razorpay_order_id` from response
- After verification, capture the payment (if manual capture is enabled)

---

### 3. Capture Payment

**Purpose**: Capture an authorized payment to settle funds to your account.

**Endpoint:**
```
POST /v1/payments/{payment_id}/capture
```

**When to Use:**
- By default, payments are auto-captured
- Use manual capture if you need to verify order before charging (e.g., verify stock availability)
- Configure capture settings in Dashboard or via Orders API

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `payment_id` | string | Yes | Path parameter - unique payment identifier |
| `amount` | integer | Yes | Amount to capture in smallest currency unit (must equal authorized amount) |
| `currency` | string | Yes | ISO currency code (must match original payment) |

**Request Example (cURL):**

```bash
curl -u rzp_test_xxxxxxx:xxxxxxxxx \
  -X POST https://api.razorpay.com/v1/payments/pay_29QQoUBi66xm2f/capture \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 5000,
    "currency": "INR"
  }'
```

**Request Example (PHP SDK):**

```php
$paymentId = 'pay_29QQoUBi66xm2f';

try {
    $payment = $api->payment->fetch($paymentId);

    // Capture the payment
    $payment->capture([
        'amount' => 5000, // Amount in paise
        'currency' => 'INR'
    ]);

    // Payment captured successfully
} catch (\Exception $e) {
    error_log('Payment capture failed: ' . $e->getMessage());
}
```

**Response Format:**

```json
{
  "id": "pay_29QQoUBi66xm2f",
  "entity": "payment",
  "amount": 5000,
  "currency": "INR",
  "status": "captured",
  "order_id": "order_RB58MiP5SPFYyM",
  "method": "card",
  "description": "Booking Payment",
  "captured": true,
  "email": "john@example.com",
  "contact": "9999999999",
  "created_at": 1756455561
}
```

**Common Errors:**

| Status | Error | Description |
|--------|-------|-------------|
| 400 | Capture amount must equal authorized amount | Amount mismatch |
| 400 | Payment already captured | Duplicate capture attempt |
| 400 | Payment not in authorized state | Can only capture authorized payments |
| 404 | Payment not found | Invalid payment_id |

**Settlement Timeline:**
- Domestic transactions: T+2 days
- International transactions: T+7 business days

---

### 4. Fetch Payment Details

**Endpoint:**
```
GET /v1/payments/{payment_id}
```

**Request Example (PHP SDK):**

```php
$paymentId = 'pay_29QQoUBi66xm2f';

try {
    $payment = $api->payment->fetch($paymentId);

    echo $payment->status;      // captured, authorized, failed, refunded
    echo $payment->amount;      // Amount in paise
    echo $payment->method;      // card, netbanking, upi, wallet, etc.
    echo $payment->email;       // Customer email
    echo $payment->order_id;    // Associated order ID
} catch (\Exception $e) {
    error_log('Fetch payment failed: ' . $e->getMessage());
}
```

**Payment Statuses:**

| Status | Description |
|--------|-------------|
| `created` | Payment initiated but not processed |
| `authorized` | Payment authorized, awaiting capture |
| `captured` | Payment captured, funds will be settled |
| `refunded` | Full refund processed |
| `failed` | Payment failed |

---

### 5. Create Refund (Full/Partial)

**Purpose**: Refund a captured payment fully or partially.

**Endpoint:**
```
POST /v1/payments/{payment_id}/refund
```

**Request Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `payment_id` | string | Yes | Path parameter - payment to refund |
| `amount` | integer | No | Refund amount in smallest currency unit (omit for full refund) |
| `speed` | string | No | `normal` (5-7 days) or `optimum` (based on bank support) |
| `notes` | object | No | Key-value metadata |
| `receipt` | string | No | Your internal reference |

**Full Refund Example (PHP SDK):**

```php
$paymentId = 'pay_29QQoUBi66xm2f';

try {
    // Full refund (omit amount parameter)
    $refund = $api->payment->fetch($paymentId)->refund();

    echo $refund->id;          // rfnd_xxxxx
    echo $refund->amount;      // Refunded amount
    echo $refund->status;      // pending/processed/failed
} catch (\Exception $e) {
    error_log('Refund failed: ' . $e->getMessage());
}
```

**Partial Refund Example (PHP SDK):**

```php
$paymentId = 'pay_29QQoUBi66xm2f';

try {
    // Partial refund of ₹30 (3000 paise)
    $refund = $api->payment->fetch($paymentId)->refund([
        'amount' => 3000,
        'speed'  => 'optimum',
        'notes'  => [
            'reason' => 'Customer requested partial refund'
        ]
    ]);

    echo $refund->id;          // rfnd_xxxxx
    echo $refund->amount;      // 3000
} catch (\Exception $e) {
    error_log('Partial refund failed: ' . $e->getMessage());
}
```

**Request Example (cURL):**

```bash
curl -u rzp_test_xxxxxxx:xxxxxxxxx \
  -X POST https://api.razorpay.com/v1/payments/pay_29QQoUBi66xm2f/refund \
  -H 'Content-Type: application/json' \
  -d '{
    "amount": 3000,
    "speed": "optimum",
    "notes": {
      "reason": "Customer cancellation"
    }
  }'
```

**Response Format:**

```json
{
  "id": "rfnd_FgRAHdNOM4ZVbO",
  "entity": "refund",
  "amount": 3000,
  "currency": "INR",
  "payment_id": "pay_29QQoUBi66xm2f",
  "status": "processed",
  "speed_processed": "normal",
  "speed_requested": "optimum",
  "acquirer_data": {
    "arn": "10000000000000"
  },
  "created_at": 1756455561,
  "notes": {
    "reason": "Customer cancellation"
  }
}
```

**Refund Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Unique refund identifier (starts with `rfnd_`) |
| `amount` | integer | Refunded amount in smallest currency unit |
| `status` | string | `pending`, `processed`, or `failed` |
| `speed_processed` | string | Actual processing speed used |
| `speed_requested` | string | Speed requested in API call |
| `acquirer_data` | object | Bank reference (RRN, ARN, or UTR) for customer tracking |

**Refund Processing Times:**

| Speed | Timeline |
|-------|----------|
| `normal` | 5-7 business days |
| `optimum` | Instant (if supported by bank), otherwise normal |

**Important Refund Notes:**
- Can only refund payments in `captured` state
- Multiple partial refunds allowed (sum cannot exceed captured amount)
- Payment status remains `captured` until fully refunded
- Cannot refund payments older than 6 months
- Minimum refund: ₹1.00 (100 paise)
- Authorized (uncaptured) payments are auto-refunded after 3 days

**Partial Refund Example:**
- Original payment: ₹150.00 (15000 paise)
- First partial refund: ₹50.00 (5000 paise)
- Second partial refund: ₹30.00 (3000 paise)
- Remaining capturable: ₹70.00 (7000 paise)

---

## Webhooks

### Overview

Webhooks allow you to receive real-time notifications for payment events. Razorpay sends POST requests to your configured webhook URL.

**Setup:** Dashboard → Settings → Webhooks → Add Webhook URL

**Webhook URL Requirements:**
- Must use port 80 (HTTP) or 443 (HTTPS)
- Must return 2xx HTTP status for successful delivery
- Can create up to 30 different webhook URLs for Payments

**Retry Policy:**
- Non-2xx responses trigger retry
- Exponential backoff for 24 hours after event creation
- At-least-once delivery semantics (may receive duplicates)

### Available Webhook Events

#### Payment Events

| Event | Description | When Triggered |
|-------|-------------|----------------|
| `payment.authorized` | Payment authorized | After successful authorization (before capture) |
| `payment.captured` | Payment captured | After successful fund capture |
| `payment.failed` | Payment failed | After payment attempt fails (not during authorization) |
| `payment.pending` | Payment pending | Payment requires additional action |

#### Order Events

| Event | Description | When Triggered |
|-------|-------------|----------------|
| `order.paid` | Order paid | After associated payment is captured |

#### Refund Events

| Event | Description | When Triggered |
|-------|-------------|----------------|
| `refund.created` | Refund initiated | Immediately after refund request |
| `refund.processed` | Refund processed | When refund is successfully processed |
| `refund.speed_changed` | Refund speed changed | Refund processing speed updated |

### Webhook Payload Format

**Common Structure:**

```json
{
  "entity": "event",
  "account_id": "acc_xxxxxxxxxxxxxxxx",
  "event": "payment.captured",
  "contains": ["payment"],
  "payload": {
    "payment": {
      "entity": {
        "id": "pay_29QQoUBi66xm2f",
        "entity": "payment",
        "amount": 5000,
        "currency": "INR",
        "status": "captured",
        "order_id": "order_RB58MiP5SPFYyM",
        "method": "card",
        "email": "john@example.com",
        "contact": "9999999999",
        "created_at": 1756455561,
        "notes": {
          "booking_id": "123"
        }
      }
    }
  },
  "created_at": 1756455561
}
```

**payment.captured Payload:**

```json
{
  "entity": "event",
  "event": "payment.captured",
  "payload": {
    "payment": {
      "entity": {
        "id": "pay_xxxxx",
        "amount": 5000,
        "status": "captured",
        "order_id": "order_xxxxx",
        "method": "card"
      }
    }
  }
}
```

**payment.failed Payload:**

```json
{
  "entity": "event",
  "event": "payment.failed",
  "payload": {
    "payment": {
      "entity": {
        "id": "pay_xxxxx",
        "amount": 5000,
        "status": "failed",
        "error_code": "BAD_REQUEST_ERROR",
        "error_description": "Payment failed"
      }
    }
  }
}
```

**order.paid Payload:**

```json
{
  "entity": "event",
  "event": "order.paid",
  "payload": {
    "order": {
      "entity": {
        "id": "order_xxxxx",
        "amount": 5000,
        "status": "paid",
        "amount_paid": 5000
      }
    },
    "payment": {
      "entity": {
        "id": "pay_xxxxx",
        "status": "captured"
      }
    }
  }
}
```

### Webhook Signature Verification

**CRITICAL:** Always verify webhook signatures to prevent fraud.

**Signature Header:**
```
X-Razorpay-Signature: {signature}
```

**Algorithm:** HMAC SHA256
- **Key:** Webhook secret (configured in Dashboard)
- **Message:** Raw webhook request body

**PHP Verification (using SDK):**

```php
<?php
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// Initialize API
$api = new Api($keyId, $keySecret);

// Get webhook data
$webhookSecret = 'whsec_xxxxxxxxxxxxxxxx'; // From Dashboard
$webhookBody   = file_get_contents('php://input');
$webhookSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'];

try {
    // Verify signature
    $api->utility->verifyWebhookSignature($webhookBody, $webhookSignature, $webhookSecret);

    // Signature is valid - process webhook
    $payload = json_decode($webhookBody, true);
    $event = $payload['event'];

    switch ($event) {
        case 'payment.captured':
            $paymentId = $payload['payload']['payment']['entity']['id'];
            // Update booking to confirmed
            break;

        case 'payment.failed':
            $paymentId = $payload['payload']['payment']['entity']['id'];
            // Mark booking as failed
            break;

        case 'order.paid':
            $orderId = $payload['payload']['order']['entity']['id'];
            // Order fully paid
            break;
    }

    // Return 200 OK
    http_response_code(200);
    echo 'Webhook processed';

} catch(SignatureVerificationError $e) {
    // Invalid signature - possible fraud attempt
    error_log('Webhook signature verification failed: ' . $e->getMessage());
    http_response_code(400);
    exit;
}
```

**Manual Signature Verification (without SDK):**

```php
<?php
$webhookSecret = 'whsec_xxxxxxxxxxxxxxxx';
$webhookBody   = file_get_contents('php://input'); // MUST be raw body
$webhookSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'];

// Generate expected signature
$expectedSignature = hash_hmac('sha256', $webhookBody, $webhookSecret);

// Compare using timing-safe comparison
if (hash_equals($expectedSignature, $webhookSignature)) {
    // Valid signature
    $payload = json_decode($webhookBody, true);
    // Process webhook
} else {
    // Invalid signature
    http_response_code(400);
    exit;
}
```

**Critical Signature Verification Rules:**

1. **Use Raw Body:** Do NOT parse/encode the body before verification
   ```php
   // CORRECT
   $webhookBody = file_get_contents('php://input');

   // WRONG - will fail verification
   $webhookBody = json_encode($_POST);
   ```

2. **Webhook Secret ≠ API Secret:** The webhook secret is different from your API Key Secret. Get it from Dashboard → Webhooks.

3. **Handle Secret Rotation:** When rotating secrets, keep the old secret temporarily to verify retry webhooks.

4. **Timing-Safe Comparison:** Always use `hash_equals()` to prevent timing attacks.

### Handling Duplicate Webhooks

**Idempotency Check:**

```php
// Check if webhook already processed
$eventId = $_SERVER['HTTP_X_RAZORPAY_EVENT_ID'];

$alreadyProcessed = check_if_event_processed($eventId); // Your database check

if ($alreadyProcessed) {
    // Already processed - return success to prevent retry
    http_response_code(200);
    echo 'Already processed';
    exit;
}

// Process webhook
process_webhook($payload);

// Store event ID to prevent duplicate processing
store_processed_event_id($eventId);
```

**Why Duplicates Occur:**
- At-least-once delivery guarantee
- Server accepts but doesn't respond within 5 seconds (timeout)
- Network issues during response transmission

---

## Error Handling

### Error Response Format

```json
{
  "error": {
    "code": "BAD_REQUEST_ERROR",
    "description": "The api key provided is invalid",
    "field": "api_key",
    "source": "business",
    "step": "payment_authentication",
    "reason": "invalid_api_key"
  }
}
```

### Common Error Codes

| HTTP Status | Error Code | Description | Resolution |
|-------------|------------|-------------|------------|
| 400 | `BAD_REQUEST_ERROR` | Invalid request parameters | Check parameter format and values |
| 401 | `UNAUTHORIZED` | Authentication failed | Verify API Key ID and Secret |
| 404 | `NOT_FOUND` | Resource not found | Check payment/order ID |
| 500 | `SERVER_ERROR` | Razorpay server error | Retry with exponential backoff |
| 502 | `GATEWAY_ERROR` | Gateway error | Retry request |

### Authentication Errors

| Error | Cause | Resolution |
|-------|-------|------------|
| Authentication failed | Invalid API credentials | Check Key ID and Secret match Dashboard |
| Invalid API key | Wrong key for mode | Use Test keys for test mode, Live keys for live mode |
| Expired API key | API key expired | Generate new API keys |

### Payment-Specific Errors

| Error | Description | Action |
|-------|-------------|--------|
| Payment already captured | Duplicate capture attempt | Check payment status before capturing |
| Payment not authorized | Cannot capture non-authorized payment | Ensure payment is in authorized state |
| Invalid amount | Amount mismatch or below minimum | Verify amount is correct and ≥ ₹1.00 |
| Payment method not enabled | Payment method disabled | Enable method in Dashboard |

### Refund-Specific Errors

| Error | Description | Action |
|-------|-------------|--------|
| Payment not captured | Cannot refund uncaptured payment | Only refund captured payments |
| Refund amount exceeds payment | Partial refunds exceed total | Check remaining refundable amount |
| Payment too old | Payment > 6 months old | Cannot refund payments older than 6 months |

### Retry Strategy

**Exponential Backoff:**

```php
function apiCallWithRetry($callable, $maxRetries = 3) {
    $retries = 0;
    $delay = 1; // Start with 1 second

    while ($retries < $maxRetries) {
        try {
            return $callable();
        } catch (\Exception $e) {
            $retries++;

            if ($retries >= $maxRetries) {
                throw $e;
            }

            // Exponential backoff: 1s, 2s, 4s
            sleep($delay);
            $delay *= 2;
        }
    }
}

// Usage
$payment = apiCallWithRetry(function() use ($api, $paymentId) {
    return $api->payment->fetch($paymentId);
});
```

### Idempotency Keys

Razorpay does not currently support custom idempotency keys. Use webhooks and database checks to handle duplicate processing.

---

## Data Models

### Payment Object

```php
[
    'id'          => 'pay_29QQoUBi66xm2f',     // string, unique identifier
    'entity'      => 'payment',                 // string, always "payment"
    'amount'      => 5000,                      // integer, amount in paise
    'currency'    => 'INR',                     // string, ISO 4217 currency code
    'status'      => 'captured',                // enum: created, authorized, captured, refunded, failed
    'order_id'    => 'order_RB58MiP5SPFYyM',   // string, associated order ID
    'method'      => 'card',                    // string: card, netbanking, wallet, upi, emi
    'amount_refunded' => 0,                     // integer, total refunded amount
    'refund_status'   => null,                  // string: null, partial, full
    'captured'    => true,                      // boolean, whether payment is captured
    'description' => 'Booking Payment',         // string, payment description
    'card_id'     => 'card_xxxxx',             // string, card identifier (if card payment)
    'bank'        => null,                      // string, bank code (if netbanking)
    'wallet'      => null,                      // string, wallet name (if wallet payment)
    'vpa'         => 'user@upi',               // string, UPI ID (if UPI payment)
    'email'       => 'john@example.com',       // string, customer email
    'contact'     => '+919999999999',          // string, customer phone
    'customer_id' => 'cust_xxxxx',             // string, customer ID (if saved)
    'notes'       => [                          // array, custom metadata
        'booking_id' => '123'
    ],
    'fee'         => 100,                       // integer, Razorpay fee in paise
    'tax'         => 18,                        // integer, tax on fee in paise
    'error_code'  => null,                      // string, error code if failed
    'error_description' => null,                // string, error message if failed
    'error_source'      => null,                // string, error source if failed
    'error_step'        => null,                // string, error step if failed
    'error_reason'      => null,                // string, error reason if failed
    'acquirer_data'     => [                    // object, bank/gateway data
        'bank_transaction_id' => 'xxxxx'
    ],
    'created_at'  => 1756455561                 // integer, Unix timestamp
]
```

### Order Object

```php
[
    'id'          => 'order_RB58MiP5SPFYyM',   // string, unique identifier
    'entity'      => 'order',                   // string, always "order"
    'amount'      => 5000,                      // integer, order amount in paise
    'amount_paid' => 5000,                      // integer, amount paid so far
    'amount_due'  => 0,                         // integer, remaining amount
    'currency'    => 'INR',                     // string, ISO 4217 currency code
    'receipt'     => 'booking_123',             // string, your internal reference
    'status'      => 'paid',                    // enum: created, attempted, paid
    'attempts'    => 1,                         // integer, payment attempts count
    'notes'       => [                          // array, custom metadata
        'booking_id' => '123'
    ],
    'created_at'  => 1756455561,                // integer, Unix timestamp
    'offer_id'    => null                       // string, offer ID if applied
]
```

### Refund Object

```php
[
    'id'          => 'rfnd_FgRAHdNOM4ZVbO',    // string, unique identifier
    'entity'      => 'refund',                  // string, always "refund"
    'amount'      => 3000,                      // integer, refund amount in paise
    'currency'    => 'INR',                     // string, ISO 4217 currency code
    'payment_id'  => 'pay_29QQoUBi66xm2f',     // string, associated payment ID
    'status'      => 'processed',               // enum: pending, processed, failed
    'speed_requested' => 'optimum',             // string: normal, optimum
    'speed_processed' => 'normal',              // string: normal, instant
    'receipt'     => 'refund_123',              // string, your internal reference
    'notes'       => [                          // array, custom metadata
        'reason' => 'Customer cancellation'
    ],
    'acquirer_data' => [                        // object, bank reference data
        'arn' => '10000000000000'               // ARN/RRN/UTR for customer tracking
    ],
    'batch_id'    => null,                      // string, batch refund ID
    'created_at'  => 1756455561                 // integer, Unix timestamp
]
```

### Customer Object

```php
[
    'id'          => 'cust_xxxxxxxxxxxxx',     // string, unique identifier
    'entity'      => 'customer',                // string, always "customer"
    'name'        => 'John Doe',                // string, customer name
    'email'       => 'john@example.com',       // string, customer email
    'contact'     => '+919999999999',          // string, customer phone
    'gstin'       => null,                      // string, GST number (India)
    'notes'       => [],                        // array, custom metadata
    'created_at'  => 1756455561                 // integer, Unix timestamp
]
```

### Webhook Event Object

```php
[
    'entity'      => 'event',                   // string, always "event"
    'account_id'  => 'acc_xxxxxxxxxxxxxxxx',   // string, your Razorpay account ID
    'event'       => 'payment.captured',        // string, event name
    'contains'    => ['payment'],               // array, entities in payload
    'payload'     => [                          // object, event data
        'payment' => [
            'entity' => [/* Payment Object */]
        ]
    ],
    'created_at'  => 1756455561                 // integer, Unix timestamp
]
```

---

## BookingX Integration Notes

### Mapping to BookingX Add-on SDK

**Extends:** `AbstractPaymentGateway`

**Traits Needed:**
- `HasSettings` - Settings management
- `HasWebhooks` - Webhook handling
- `HasLicense` - EDD license validation

**Class Structure:**

```php
namespace BookingX\Razorpay;

use BookingX\AddonSDK\Abstracts\AbstractPaymentGateway;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasWebhooks;
use BookingX\AddonSDK\Traits\HasLicense;

class RazorpayGateway extends AbstractPaymentGateway {
    use HasSettings, HasWebhooks, HasLicense;

    protected $id = 'razorpay';
    protected $title = 'Razorpay';
    protected $description = 'Accept payments via Razorpay';

    public function processPayment($booking_id, $amount) {
        // Create order
        // Return checkout form
    }

    public function verifyPayment($payment_data) {
        // Verify signature
        // Return true/false
    }

    public function capturePayment($payment_id, $amount) {
        // Capture authorized payment
    }

    public function refundPayment($payment_id, $amount) {
        // Process refund
    }

    public function handleWebhook() {
        // Verify signature
        // Process event
    }
}
```

### Settings Fields Configuration

```php
public function getSettingsFields() {
    return [
        'enabled' => [
            'title'       => __('Enable/Disable', 'bkx-razorpay'),
            'type'        => 'checkbox',
            'label'       => __('Enable Razorpay Payment Gateway', 'bkx-razorpay'),
            'default'     => 'no'
        ],
        'test_mode' => [
            'title'       => __('Test Mode', 'bkx-razorpay'),
            'type'        => 'checkbox',
            'label'       => __('Enable Test Mode', 'bkx-razorpay'),
            'default'     => 'yes',
            'description' => __('Use test API keys for testing payments', 'bkx-razorpay')
        ],
        'test_key_id' => [
            'title'       => __('Test Key ID', 'bkx-razorpay'),
            'type'        => 'text',
            'description' => __('Get your Test API keys from Razorpay Dashboard', 'bkx-razorpay'),
            'placeholder' => 'rzp_test_xxxxxxxxxxxxxxxx'
        ],
        'test_key_secret' => [
            'title'       => __('Test Key Secret', 'bkx-razorpay'),
            'type'        => 'password',
            'description' => __('Never share your Key Secret publicly', 'bkx-razorpay')
        ],
        'live_key_id' => [
            'title'       => __('Live Key ID', 'bkx-razorpay'),
            'type'        => 'text',
            'description' => __('Get your Live API keys from Razorpay Dashboard', 'bkx-razorpay'),
            'placeholder' => 'rzp_live_xxxxxxxxxxxxxxxx'
        ],
        'live_key_secret' => [
            'title'       => __('Live Key Secret', 'bkx-razorpay'),
            'type'        => 'password',
            'description' => __('Never share your Key Secret publicly', 'bkx-razorpay')
        ],
        'webhook_secret' => [
            'title'       => __('Webhook Secret', 'bkx-razorpay'),
            'type'        => 'password',
            'description' => sprintf(
                __('Enter webhook secret from Dashboard. Webhook URL: %s', 'bkx-razorpay'),
                $this->getWebhookUrl()
            )
        ],
        'auto_capture' => [
            'title'       => __('Auto Capture', 'bkx-razorpay'),
            'type'        => 'checkbox',
            'label'       => __('Automatically capture payments', 'bkx-razorpay'),
            'default'     => 'yes',
            'description' => __('If disabled, you must manually capture payments', 'bkx-razorpay')
        ],
        'payment_description' => [
            'title'       => __('Payment Description', 'bkx-razorpay'),
            'type'        => 'text',
            'default'     => __('Booking Payment', 'bkx-razorpay'),
            'description' => __('Description shown to customer during checkout', 'bkx-razorpay')
        ],
        'brand_name' => [
            'title'       => __('Brand Name', 'bkx-razorpay'),
            'type'        => 'text',
            'default'     => get_bloginfo('name'),
            'description' => __('Business name shown in checkout', 'bkx-razorpay')
        ],
        'brand_logo' => [
            'title'       => __('Brand Logo URL', 'bkx-razorpay'),
            'type'        => 'text',
            'description' => __('Logo URL shown in checkout (recommended: 256x256px)', 'bkx-razorpay')
        ],
        'theme_color' => [
            'title'       => __('Theme Color', 'bkx-razorpay'),
            'type'        => 'color',
            'default'     => '#F37254',
            'description' => __('Checkout form theme color', 'bkx-razorpay')
        ]
    ];
}
```

### Encryption for Sensitive Data

**Use SDK EncryptionService for API keys:**

```php
use BookingX\AddonSDK\Services\EncryptionService;

// Store encrypted
$keySecret = $this->getSettingValue('live_key_secret');
$encrypted = EncryptionService::encrypt($keySecret);
update_option('bkx_razorpay_key_secret_encrypted', $encrypted);

// Retrieve decrypted
$encrypted = get_option('bkx_razorpay_key_secret_encrypted');
$keySecret = EncryptionService::decrypt($encrypted);
```

### Database Tables Required

**Custom Table: `wp_bkx_razorpay_transactions`**

Store Razorpay-specific transaction data:

```php
use BookingX\AddonSDK\Database\Schema;

Schema::create('bkx_razorpay_transactions', function($table) {
    $table->id();
    $table->unsignedBigInteger('booking_id');
    $table->string('razorpay_order_id', 40)->unique();
    $table->string('razorpay_payment_id', 40)->nullable()->unique();
    $table->string('razorpay_signature', 255)->nullable();
    $table->integer('amount'); // in paise
    $table->string('currency', 3)->default('INR');
    $table->enum('status', ['created', 'authorized', 'captured', 'refunded', 'failed'])->default('created');
    $table->string('payment_method', 20)->nullable(); // card, upi, netbanking, etc.
    $table->string('customer_email', 100)->nullable();
    $table->string('customer_phone', 20)->nullable();
    $table->text('error_message')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamp('authorized_at')->nullable();
    $table->timestamp('captured_at')->nullable();
    $table->timestamps();

    $table->index('booking_id');
    $table->index('status');
    $table->index(['razorpay_order_id', 'razorpay_payment_id']);
});
```

**Custom Table: `wp_bkx_razorpay_refunds`**

Track refunds separately:

```php
Schema::create('bkx_razorpay_refunds', function($table) {
    $table->id();
    $table->unsignedBigInteger('transaction_id');
    $table->string('razorpay_refund_id', 40)->unique();
    $table->string('razorpay_payment_id', 40);
    $table->integer('amount'); // in paise
    $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
    $table->string('speed', 10)->default('normal'); // normal, optimum
    $table->text('reason')->nullable();
    $table->json('acquirer_data')->nullable();
    $table->timestamps();

    $table->index('transaction_id');
    $table->index('razorpay_payment_id');
});
```

### Webhook Event Handlers

```php
protected function getWebhookHandlers() {
    return [
        'payment.authorized' => [$this, 'handlePaymentAuthorized'],
        'payment.captured'   => [$this, 'handlePaymentCaptured'],
        'payment.failed'     => [$this, 'handlePaymentFailed'],
        'order.paid'         => [$this, 'handleOrderPaid'],
        'refund.processed'   => [$this, 'handleRefundProcessed'],
    ];
}

public function handlePaymentCaptured($payload) {
    $payment = $payload['payment']['entity'];
    $paymentId = $payment['id'];
    $orderId = $payment['order_id'];

    // Find booking by order_id
    $transaction = $this->getTransactionByOrderId($orderId);

    if (!$transaction) {
        $this->log('Transaction not found for order: ' . $orderId);
        return;
    }

    // Update transaction status
    $this->updateTransaction($transaction->id, [
        'razorpay_payment_id' => $paymentId,
        'status' => 'captured',
        'payment_method' => $payment['method'],
        'customer_email' => $payment['email'],
        'customer_phone' => $payment['contact'],
        'captured_at' => current_time('mysql')
    ]);

    // Update booking status in BookingX
    $this->updateBookingStatus($transaction->booking_id, 'bkx-ack');

    // Fire BookingX action
    do_action('bkx_payment_completed', $transaction->booking_id, $paymentId);
}
```

### Integration with BookingX Hooks

```php
// Register gateway with BookingX
add_filter('bkx_payment_gateways', function($gateways) {
    $gateways['razorpay'] = RazorpayGateway::class;
    return $gateways;
});

// Add settings tab
add_filter('bkx_settings_tabs', function($tabs) {
    $tabs['razorpay'] = __('Razorpay', 'bkx-razorpay');
    return $tabs;
});

// After booking created
add_action('bkx_booking_created', function($booking_id, $booking_data) {
    // Create Razorpay order
    // Store order_id in transaction table
}, 10, 2);

// Before booking cancelled
add_action('bkx_before_booking_cancelled', function($booking_id) {
    // Process refund if payment was captured
}, 10);
```

### Currency Support

**Supported Currencies:**

Razorpay supports 100+ currencies. Most common for BookingX:

| Currency | Code | Minimum Amount |
|----------|------|----------------|
| Indian Rupee | INR | ₹1.00 (100 paise) |
| US Dollar | USD | $0.50 (50 cents) |
| Euro | EUR | €0.50 (50 cents) |
| British Pound | GBP | £0.30 (30 pence) |
| Australian Dollar | AUD | A$0.50 (50 cents) |
| Singapore Dollar | SGD | S$0.50 (50 cents) |
| Malaysian Ringgit | MYR | RM2 (200 sen) |

**Amount Conversion:**

```php
// Convert BookingX amount to Razorpay format (paise/cents)
public function convertToMinorUnit($amount, $currency = 'INR') {
    // Three-decimal currencies (KWD, BHD, OMR)
    $threeDecimalCurrencies = ['KWD', 'BHD', 'OMR'];

    if (in_array($currency, $threeDecimalCurrencies)) {
        return intval($amount * 1000);
    }

    // Standard two-decimal currencies
    return intval($amount * 100);
}

// Convert Razorpay amount to BookingX format
public function convertFromMinorUnit($amount, $currency = 'INR') {
    $threeDecimalCurrencies = ['KWD', 'BHD', 'OMR'];

    if (in_array($currency, $threeDecimalCurrencies)) {
        return $amount / 1000;
    }

    return $amount / 100;
}
```

### Payment Flow Summary

1. **Customer initiates booking** → BookingX creates booking record
2. **Process Payment called** → Addon creates Razorpay order via API
3. **Display Checkout** → Frontend loads Razorpay.js, opens checkout modal
4. **Customer pays** → Razorpay processes payment, returns payment_id + signature
5. **Verify Signature** → Server-side verification of razorpay_signature
6. **Capture Payment** → Auto-capture or manual capture via API
7. **Webhook Received** → `payment.captured` webhook updates booking status
8. **Confirmation** → Booking status updated to confirmed, customer notified

---

## Testing & Development

### Test Card Details

**Successful Payment:**
- Card Number: `4111 1111 1111 1111`
- CVV: Any 3 digits
- Expiry: Any future date

**Failed Payment:**
- Card Number: `4000 0000 0000 0002`
- CVV: Any 3 digits
- Expiry: Any future date

**UPI Test ID:**
- `success@razorpay`
- `failure@razorpay`

### Sandbox Environment

- Use Test Mode API keys from Dashboard
- No real money is charged in test mode
- All payment methods are simulated
- Use test card numbers for testing

### Webhook Testing

**Local Development:**

Use tools like ngrok to expose local server:

```bash
ngrok http 80
```

Then configure webhook URL in Razorpay Dashboard:
```
https://your-ngrok-url.ngrok.io/wp-json/bkx-razorpay/v1/webhook
```

**Manual Webhook Testing:**

Use curl to test webhook handler:

```bash
curl -X POST https://yoursite.com/wp-json/bkx-razorpay/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-Razorpay-Signature: test_signature" \
  -d '{
    "entity": "event",
    "event": "payment.captured",
    "payload": {
      "payment": {
        "entity": {
          "id": "pay_test123",
          "amount": 5000,
          "status": "captured"
        }
      }
    }
  }'
```

### Logging Best Practices

```php
use BookingX\AddonSDK\Services\LoggerService;

// Log API requests (without sensitive data)
LoggerService::info('Creating Razorpay order', [
    'booking_id' => $booking_id,
    'amount' => $amount,
    'currency' => $currency
]);

// Log errors
try {
    $order = $api->order->create($orderData);
} catch (\Exception $e) {
    LoggerService::error('Order creation failed', [
        'booking_id' => $booking_id,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}

// Log webhook events
LoggerService::info('Webhook received', [
    'event' => $event,
    'payment_id' => $payment_id,
    'order_id' => $order_id
]);
```

---

## Security Checklist

- [ ] API keys stored encrypted using `EncryptionService`
- [ ] Webhook signatures verified on every request
- [ ] Payment signatures verified before confirming bookings
- [ ] Use HTTPS for all API communication
- [ ] Sanitize and validate all user inputs
- [ ] Escape all outputs
- [ ] Verify nonces on AJAX requests
- [ ] Check user capabilities before admin actions
- [ ] Log all payment transactions (with audit trail)
- [ ] Rate limit payment processing endpoints
- [ ] Implement CSRF protection on forms
- [ ] Never log API secrets or sensitive customer data
- [ ] Use timing-safe comparison for signature verification
- [ ] Handle webhook duplicate events (idempotency)
- [ ] Validate payment amounts match booking amounts
- [ ] Implement database transaction rollback on failures

---

## Resources

### Official Documentation
- **API Reference**: https://razorpay.com/docs/api/
- **Payment Gateway Integration**: https://razorpay.com/docs/payments/payment-gateway/
- **PHP SDK**: https://razorpay.com/docs/payments/server-integration/php/
- **Webhooks**: https://razorpay.com/docs/webhooks/

### Developer Resources
- **PHP SDK GitHub**: https://github.com/razorpay/razorpay-php
- **API Postman Collection**: https://www.postman.com/razorpaydev/razorpay-public-workspace/
- **Dashboard**: https://dashboard.razorpay.com/
- **Support**: https://razorpay.com/support/

### Important Links
- **Generate API Keys**: https://dashboard.razorpay.com/#/app/keys
- **Webhook Setup**: Dashboard → Settings → Webhooks
- **Test Credentials**: https://razorpay.com/docs/payments/payments/test-card-details/

---

## Implementation Checklist

### Phase 1: Basic Setup
- [ ] Install Razorpay PHP SDK via Composer
- [ ] Create addon class extending `AbstractPaymentGateway`
- [ ] Implement settings fields (API keys, test mode, etc.)
- [ ] Add encryption for API keys using `EncryptionService`
- [ ] Test API authentication with test keys

### Phase 2: Order & Payment Processing
- [ ] Implement `processPayment()` method (create order)
- [ ] Create frontend checkout integration (Razorpay.js)
- [ ] Implement payment signature verification
- [ ] Add payment capture functionality
- [ ] Create database migration for transactions table
- [ ] Test end-to-end payment flow

### Phase 3: Webhooks
- [ ] Register webhook endpoint in WordPress REST API
- [ ] Implement webhook signature verification
- [ ] Add webhook event handlers (payment.captured, payment.failed, etc.)
- [ ] Test webhook delivery and processing
- [ ] Add idempotency check for duplicate webhooks
- [ ] Configure webhook URL in Razorpay Dashboard

### Phase 4: Refunds
- [ ] Implement refund functionality (full and partial)
- [ ] Create refunds database table
- [ ] Add refund webhook handlers
- [ ] Test refund processing
- [ ] Add refund UI in BookingX admin

### Phase 5: Testing & Security
- [ ] Write PHPUnit tests for all methods
- [ ] Run PHPCS for WordPress coding standards
- [ ] Security audit (nonce, capability checks, sanitization)
- [ ] Test with various payment methods (card, UPI, netbanking)
- [ ] Test edge cases (network failures, duplicate payments, etc.)
- [ ] Load testing for concurrent bookings

### Phase 6: Documentation & Launch
- [ ] Write user documentation (setup guide)
- [ ] Write developer documentation (hooks, filters)
- [ ] Create setup wizard for first-time configuration
- [ ] Add contextual help in settings
- [ ] Generate changelog
- [ ] Submit for code review

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-12-28 | Initial API reference document created |

---

**Document Created By:** API Integration Research Specialist
**Last Updated:** 2025-12-28
**API Version Covered:** Razorpay API v1 (Current as of 2024-2025)
