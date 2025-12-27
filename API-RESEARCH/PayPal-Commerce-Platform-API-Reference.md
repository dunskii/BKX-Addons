# PayPal Commerce Platform API Integration Reference

**Document Version:** 1.0
**Last Updated:** December 26, 2025
**Target Integration:** BookingX Payment Gateway Add-on

---

## Overview

- **API Base URL (Sandbox):** `https://api-m.sandbox.paypal.com`
- **API Base URL (Live):** `https://api-m.paypal.com`
- **API Version:** v2 (Orders API v2, Payments API v2)
- **Official Documentation:** https://developer.paypal.com/api/rest/
- **Orders API Reference:** https://developer.paypal.com/docs/api/orders/v2/
- **Payments API Reference:** https://developer.paypal.com/docs/api/payments/v2/

### PHP SDK Options

| Package | Status | Installation | Recommendation |
|---------|--------|--------------|----------------|
| `paypal/rest-api-sdk-php` | **DEPRECATED** (Archived Aug 2025) | `composer require paypal/rest-api-sdk-php:^1.0` | Do NOT use for new projects |
| `paypal/PayPal-PHP-Server-SDK` | **CURRENT** (Official) | `composer require paypal/paypal-php-server-sdk` | **Recommended** for new integrations |
| `shopware/paypal-sdk` | Active (Third-party) | `composer require shopware/paypal-sdk` | Alternative modern interface |
| `angelleye/paypal-php-library` | Active (Third-party) | `composer require angelleye/paypal-php-library` | Supports both REST and Classic APIs |

**Recommendation for BookingX:** Use **direct REST API calls** via `HttpClient` service from the SDK to maintain full control and avoid dependency on potentially deprecated SDKs.

---

## Authentication

### Method: OAuth 2.0 Client Credentials Flow

PayPal uses OAuth 2.0 access tokens to authenticate all REST API requests. Your integration must exchange client credentials for an access token before making any API calls.

### Required Credentials

| Credential | Description | Where to Get |
|------------|-------------|--------------|
| **Client ID** | Identifies your PayPal app | PayPal Developer Dashboard > My Apps & Credentials |
| **Client Secret** | Authenticates your Client ID | PayPal Developer Dashboard > My Apps & Credentials |

#### Getting Credentials

**Sandbox Credentials:**
1. Log in to https://developer.paypal.com/
2. Navigate to **Dashboard** > **My Apps & Credentials**
3. Toggle to **Sandbox** mode
4. Click **Create App** under REST API apps
5. Enter app name and click **Create App**
6. Copy **Client ID** and **Secret**

**Live Credentials:**
1. Same steps as above, but toggle to **Live** mode
2. Ensure your PayPal Business account is verified before creating live apps

### Get Access Token

**Endpoint:**
```
POST /v1/oauth2/token
```

**Request Headers:**
```http
Authorization: Basic {BASE64_ENCODED_CREDENTIALS}
Content-Type: application/x-www-form-urlencoded
```

**Base64 Encoding:**
```php
$credentials = base64_encode( $client_id . ':' . $client_secret );
// Authorization: Basic {$credentials}
```

**Request Body:**
```
grant_type=client_credentials
```

**cURL Example:**
```bash
curl -X POST https://api-m.sandbox.paypal.com/v1/oauth2/token \
  -H "Authorization: Basic {BASE64_CREDENTIALS}" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials"
```

**PHP Example:**
```php
$client_id = 'YOUR_CLIENT_ID';
$client_secret = 'YOUR_CLIENT_SECRET';
$credentials = base64_encode( $client_id . ':' . $client_secret );

$ch = curl_init();
curl_setopt( $ch, CURLOPT_URL, 'https://api-m.sandbox.paypal.com/v1/oauth2/token' );
curl_setopt( $ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $credentials,
    'Content-Type: application/x-www-form-urlencoded',
] );
curl_setopt( $ch, CURLOPT_POST, true );
curl_setopt( $ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials' );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

$response = curl_exec( $ch );
curl_close( $ch );

$token_data = json_decode( $response, true );
$access_token = $token_data['access_token'];
$expires_in = $token_data['expires_in']; // Typically 32400 seconds (9 hours)
```

**Response (Success):**
```json
{
  "scope": "https://uri.paypal.com/services/invoicing https://uri.paypal.com/services/vault/payment-tokens/read...",
  "access_token": "A21AAFEbLw3L...",
  "token_type": "Bearer",
  "app_id": "APP-80W284485P519543T",
  "expires_in": 32400,
  "nonce": "2024-12-26T10:30:00Z_abcd1234"
}
```

**Response (Error - 401 Unauthorized):**
```json
{
  "error": "invalid_client",
  "error_description": "Client Authentication failed"
}
```

### Token Expiration & Refresh

- **Token Lifetime:** 8-9 hours (28800-32400 seconds)
- **Strategy:** Cache the access token with its expiration time
- **Handling Expiration:**
  - Track `expires_in` value and request new token before expiry
  - OR handle HTTP 401 responses and request new token automatically

**PHP Token Management Example:**
```php
// Store in WordPress transients
$token = get_transient( 'bkx_paypal_access_token' );

if ( ! $token ) {
    $token_data = $this->get_new_access_token();
    $token = $token_data['access_token'];

    // Cache for 8 hours (add 1 hour buffer before actual expiry)
    set_transient( 'bkx_paypal_access_token', $token, 8 * HOUR_IN_SECONDS );
}

return $token;
```

### Using Access Token

Include the access token in the `Authorization` header for all subsequent API calls:

```http
Authorization: Bearer {ACCESS_TOKEN}
Content-Type: application/json
```

### Test Mode vs Production Mode

| Environment | Base URL | Credentials | Use Case |
|-------------|----------|-------------|----------|
| **Sandbox** | `https://api-m.sandbox.paypal.com` | Sandbox Client ID/Secret | Development and testing |
| **Live** | `https://api-m.paypal.com` | Live Client ID/Secret | Production transactions |

**Important Notes:**
- Sandbox and Live credentials are **completely separate** and not interchangeable
- Sandbox accounts use test money - no real funds are transferred
- Test credit cards work in Sandbox (use PayPal's test card numbers)
- Live mode requires a verified PayPal Business account

---

## Orders API v2

The Orders API creates an order that captures a payment, authorizes a payment, or sets up a billing agreement.

### Create Order

**Endpoint:**
```
POST /v2/checkout/orders
```

**Headers:**
```http
Authorization: Bearer {ACCESS_TOKEN}
Content-Type: application/json
PayPal-Request-Id: {UNIQUE_ID}
Prefer: return=representation
```

**Request Headers Explained:**
- `PayPal-Request-Id` - Unique idempotency key to prevent duplicate orders (recommended)
- `Prefer: return=representation` - Returns full order details (default is `return=minimal`)

**Minimal Request Body:**
```json
{
  "intent": "CAPTURE",
  "purchase_units": [
    {
      "reference_id": "booking-123",
      "amount": {
        "currency_code": "USD",
        "value": "100.00"
      }
    }
  ]
}
```

**Complete Request Body (with all options):**
```json
{
  "intent": "CAPTURE",
  "purchase_units": [
    {
      "reference_id": "booking-456",
      "description": "Booking #456 - Haircut Appointment",
      "custom_id": "bkx_booking_456",
      "soft_descriptor": "BOOKINGX",
      "amount": {
        "currency_code": "USD",
        "value": "125.00",
        "breakdown": {
          "item_total": {
            "currency_code": "USD",
            "value": "100.00"
          },
          "tax_total": {
            "currency_code": "USD",
            "value": "10.00"
          },
          "shipping": {
            "currency_code": "USD",
            "value": "15.00"
          }
        }
      },
      "items": [
        {
          "name": "Haircut Service",
          "description": "Professional haircut with senior stylist",
          "unit_amount": {
            "currency_code": "USD",
            "value": "100.00"
          },
          "quantity": "1",
          "category": "DIGITAL_GOODS"
        }
      ],
      "shipping": {
        "name": {
          "full_name": "John Doe"
        },
        "address": {
          "address_line_1": "123 Main St",
          "admin_area_2": "San Jose",
          "admin_area_1": "CA",
          "postal_code": "95131",
          "country_code": "US"
        }
      }
    }
  ],
  "application_context": {
    "brand_name": "BookingX",
    "locale": "en-US",
    "landing_page": "BILLING",
    "shipping_preference": "NO_SHIPPING",
    "user_action": "PAY_NOW",
    "return_url": "https://example.com/return?booking_id=456",
    "cancel_url": "https://example.com/cancel?booking_id=456"
  }
}
```

**Key Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `intent` | enum | Yes | `CAPTURE` (immediate) or `AUTHORIZE` (hold funds) |
| `purchase_units[].amount.value` | string | Yes | Total amount (e.g., "100.00") |
| `purchase_units[].amount.currency_code` | string | Yes | ISO 4217 currency code (e.g., "USD") |
| `purchase_units[].reference_id` | string | No | Your internal reference (e.g., booking ID) |
| `purchase_units[].custom_id` | string | No | Custom identifier for reconciliation |
| `purchase_units[].description` | string | No | Purchase description |
| `application_context.return_url` | string | No | URL to redirect after approval |
| `application_context.cancel_url` | string | No | URL to redirect after cancellation |
| `application_context.user_action` | enum | No | `PAY_NOW` or `CONTINUE` (button text) |

**Response (Success - HTTP 201 Created):**
```json
{
  "id": "5O190127TN364715T",
  "status": "CREATED",
  "links": [
    {
      "href": "https://api-m.sandbox.paypal.com/v2/checkout/orders/5O190127TN364715T",
      "rel": "self",
      "method": "GET"
    },
    {
      "href": "https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T",
      "rel": "approve",
      "method": "GET"
    },
    {
      "href": "https://api-m.sandbox.paypal.com/v2/checkout/orders/5O190127TN364715T",
      "rel": "update",
      "method": "PATCH"
    },
    {
      "href": "https://api-m.sandbox.paypal.com/v2/checkout/orders/5O190127TN364715T/capture",
      "rel": "capture",
      "method": "POST"
    }
  ]
}
```

**PHP Example:**
```php
$access_token = $this->get_access_token();

$order_data = [
    'intent' => 'CAPTURE',
    'purchase_units' => [
        [
            'reference_id' => 'booking-' . $booking_id,
            'amount' => [
                'currency_code' => 'USD',
                'value' => '100.00',
            ],
            'description' => 'Booking #' . $booking_id,
        ],
    ],
    'application_context' => [
        'return_url' => home_url( '/booking-confirmation/?booking_id=' . $booking_id ),
        'cancel_url' => home_url( '/booking-cancelled/?booking_id=' . $booking_id ),
        'brand_name' => get_bloginfo( 'name' ),
        'user_action' => 'PAY_NOW',
    ],
];

$ch = curl_init();
curl_setopt( $ch, CURLOPT_URL, 'https://api-m.sandbox.paypal.com/v2/checkout/orders' );
curl_setopt( $ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json',
    'Prefer: return=representation',
] );
curl_setopt( $ch, CURLOPT_POST, true );
curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $order_data ) );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

$response = curl_exec( $ch );
$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
curl_close( $ch );

$order = json_decode( $response, true );

if ( $http_code === 201 ) {
    // Redirect user to approval URL
    $approve_link = '';
    foreach ( $order['links'] as $link ) {
        if ( $link['rel'] === 'approve' ) {
            $approve_link = $link['href'];
            break;
        }
    }
    wp_redirect( $approve_link );
    exit;
}
```

### Get Order Details

**Endpoint:**
```
GET /v2/checkout/orders/{order_id}
```

**Headers:**
```http
Authorization: Bearer {ACCESS_TOKEN}
```

**Response (HTTP 200 OK):**
```json
{
  "id": "5O190127TN364715T",
  "status": "APPROVED",
  "intent": "CAPTURE",
  "purchase_units": [
    {
      "reference_id": "booking-123",
      "amount": {
        "currency_code": "USD",
        "value": "100.00"
      },
      "payee": {
        "email_address": "merchant@example.com",
        "merchant_id": "C7CYMKZDG8D6E"
      }
    }
  ],
  "payer": {
    "name": {
      "given_name": "John",
      "surname": "Doe"
    },
    "email_address": "john.doe@example.com",
    "payer_id": "BXCZQZWZTJQJE"
  },
  "create_time": "2024-12-26T10:30:00Z",
  "update_time": "2024-12-26T10:32:15Z",
  "links": [...]
}
```

**Order Status Values:**
- `CREATED` - Order created, awaiting buyer approval
- `SAVED` - Order saved but not approved
- `APPROVED` - Buyer approved, ready to capture
- `VOIDED` - Order voided, cannot be captured
- `COMPLETED` - Order completed successfully
- `PAYER_ACTION_REQUIRED` - Requires buyer action

### Capture Payment for Order

After the buyer approves the order, capture the payment to complete the transaction.

**Endpoint:**
```
POST /v2/checkout/orders/{order_id}/capture
```

**Headers:**
```http
Authorization: Bearer {ACCESS_TOKEN}
Content-Type: application/json
PayPal-Request-Id: {UNIQUE_ID}
Prefer: return=representation
```

**Request Body:**
```json
{}
```
(Usually empty for full capture)

**Response (HTTP 201 Created):**
```json
{
  "id": "5O190127TN364715T",
  "status": "COMPLETED",
  "purchase_units": [
    {
      "reference_id": "booking-123",
      "payments": {
        "captures": [
          {
            "id": "3C679366HH908993F",
            "status": "COMPLETED",
            "amount": {
              "currency_code": "USD",
              "value": "100.00"
            },
            "final_capture": true,
            "seller_protection": {
              "status": "ELIGIBLE",
              "dispute_categories": [
                "ITEM_NOT_RECEIVED",
                "UNAUTHORIZED_TRANSACTION"
              ]
            },
            "seller_receivable_breakdown": {
              "gross_amount": {
                "currency_code": "USD",
                "value": "100.00"
              },
              "paypal_fee": {
                "currency_code": "USD",
                "value": "3.20"
              },
              "net_amount": {
                "currency_code": "USD",
                "value": "96.80"
              }
            },
            "create_time": "2024-12-26T10:35:00Z",
            "update_time": "2024-12-26T10:35:00Z"
          }
        ]
      }
    }
  ],
  "payer": {
    "name": {
      "given_name": "John",
      "surname": "Doe"
    },
    "email_address": "john.doe@example.com",
    "payer_id": "BXCZQZWZTJQJE"
  },
  "links": [...]
}
```

**PHP Example:**
```php
$access_token = $this->get_access_token();
$order_id = sanitize_text_field( $_GET['token'] ); // From return URL

$ch = curl_init();
curl_setopt( $ch, CURLOPT_URL, 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' . $order_id . '/capture' );
curl_setopt( $ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json',
    'Prefer: return=representation',
] );
curl_setopt( $ch, CURLOPT_POST, true );
curl_setopt( $ch, CURLOPT_POSTFIELDS, '{}' );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

$response = curl_exec( $ch );
$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
curl_close( $ch );

$capture = json_decode( $response, true );

if ( $http_code === 201 && $capture['status'] === 'COMPLETED' ) {
    // Payment successful - update booking status
    $capture_id = $capture['purchase_units'][0]['payments']['captures'][0]['id'];
    update_post_meta( $booking_id, '_paypal_capture_id', $capture_id );
    update_post_meta( $booking_id, '_payment_status', 'completed' );
}
```

### Authorize Payment (Hold Funds)

Use `AUTHORIZE` intent to place a hold on funds without immediate capture.

**Create Order with AUTHORIZE intent:**
```json
{
  "intent": "AUTHORIZE",
  "purchase_units": [
    {
      "amount": {
        "currency_code": "USD",
        "value": "100.00"
      }
    }
  ]
}
```

**Authorize Endpoint:**
```
POST /v2/checkout/orders/{order_id}/authorize
```

**Response includes `authorization_id`:**
```json
{
  "purchase_units": [
    {
      "payments": {
        "authorizations": [
          {
            "id": "0AW2184448535812N",
            "status": "CREATED",
            "amount": {
              "currency_code": "USD",
              "value": "100.00"
            }
          }
        ]
      }
    }
  ]
}
```

**Authorization Validity:**
- Authorization is valid for **29 days**
- PayPal recommends capturing within **3 days** (honor period)
- After 29 days, authorization expires and funds are released

---

## Payments API v2

### Capture Authorized Payment

After authorizing a payment, capture it when ready to receive funds.

**Endpoint:**
```
POST /v2/payments/authorizations/{authorization_id}/capture
```

**Request Body (Full Capture):**
```json
{}
```

**Request Body (Partial Capture):**
```json
{
  "amount": {
    "currency_code": "USD",
    "value": "75.00"
  },
  "final_capture": false,
  "note_to_payer": "Partial payment for booking"
}
```

**Response (HTTP 201 Created):**
```json
{
  "id": "3C679366HH908993F",
  "status": "COMPLETED",
  "amount": {
    "currency_code": "USD",
    "value": "100.00"
  },
  "seller_receivable_breakdown": {
    "gross_amount": {
      "currency_code": "USD",
      "value": "100.00"
    },
    "paypal_fee": {
      "currency_code": "USD",
      "value": "3.20"
    },
    "net_amount": {
      "currency_code": "USD",
      "value": "96.80"
    }
  },
  "final_capture": true,
  "create_time": "2024-12-26T11:00:00Z"
}
```

### Refund Captured Payment

Issue full or partial refunds for captured payments.

**Endpoint:**
```
POST /v2/payments/captures/{capture_id}/refund
```

**Request Body (Full Refund):**
```json
{}
```

**Request Body (Partial Refund):**
```json
{
  "amount": {
    "currency_code": "USD",
    "value": "25.00"
  },
  "note_to_payer": "Partial refund for cancellation"
}
```

**Response (HTTP 201 Created):**
```json
{
  "id": "1JU08902781691411",
  "status": "COMPLETED",
  "amount": {
    "currency_code": "USD",
    "value": "25.00"
  },
  "seller_payable_breakdown": {
    "gross_amount": {
      "currency_code": "USD",
      "value": "25.00"
    },
    "paypal_fee": {
      "currency_code": "USD",
      "value": "0.80"
    },
    "net_amount": {
      "currency_code": "USD",
      "value": "24.20"
    },
    "total_refunded_amount": {
      "currency_code": "USD",
      "value": "25.00"
    }
  },
  "create_time": "2024-12-26T14:00:00Z",
  "update_time": "2024-12-26T14:00:00Z",
  "links": [
    {
      "href": "https://api-m.sandbox.paypal.com/v2/payments/refunds/1JU08902781691411",
      "rel": "self",
      "method": "GET"
    },
    {
      "href": "https://api-m.sandbox.paypal.com/v2/payments/captures/3C679366HH908993F",
      "rel": "up",
      "method": "GET"
    }
  ]
}
```

**PHP Refund Example:**
```php
$access_token = $this->get_access_token();
$capture_id = get_post_meta( $booking_id, '_paypal_capture_id', true );

$refund_data = [
    'amount' => [
        'currency_code' => 'USD',
        'value' => '50.00', // Partial refund
    ],
    'note_to_payer' => 'Refund for booking cancellation',
];

$ch = curl_init();
curl_setopt( $ch, CURLOPT_URL, 'https://api-m.sandbox.paypal.com/v2/payments/captures/' . $capture_id . '/refund' );
curl_setopt( $ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json',
] );
curl_setopt( $ch, CURLOPT_POST, true );
curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $refund_data ) );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

$response = curl_exec( $ch );
$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
curl_close( $ch );

$refund = json_decode( $response, true );

if ( $http_code === 201 && $refund['status'] === 'COMPLETED' ) {
    update_post_meta( $booking_id, '_paypal_refund_id', $refund['id'] );
    update_post_meta( $booking_id, '_refund_status', 'completed' );
}
```

**Refund Limitations:**
- Default refund window: **180 days** from transaction date
- Multiple partial refunds allowed up to total captured amount
- Cannot refund if there's an open dispute on the capture
- PayPal fees are **not** returned to the seller

### Get Refund Details

**Endpoint:**
```
GET /v2/payments/refunds/{refund_id}
```

**Response (HTTP 200 OK):**
```json
{
  "id": "1JU08902781691411",
  "status": "COMPLETED",
  "amount": {
    "currency_code": "USD",
    "value": "25.00"
  },
  "create_time": "2024-12-26T14:00:00Z",
  "update_time": "2024-12-26T14:00:00Z"
}
```

**Refund Status Values:**
- `CANCELLED` - Refund cancelled
- `PENDING` - Refund pending
- `COMPLETED` - Refund completed

---

## Webhooks

PayPal webhooks provide real-time notifications about events in your PayPal account (e.g., payment completed, refund issued).

### Available Webhook Events

**Critical Events for Booking Systems:**

| Event Type | Description | When to Use |
|------------|-------------|-------------|
| `CHECKOUT.ORDER.APPROVED` | Buyer approved the order | Trigger payment capture |
| `PAYMENT.CAPTURE.COMPLETED` | Payment successfully captured | Confirm booking, send receipt |
| `PAYMENT.CAPTURE.PENDING` | Payment initiated but not complete | Wait before fulfilling order |
| `PAYMENT.CAPTURE.DENIED` | Payment capture denied | Cancel booking, notify customer |
| `PAYMENT.CAPTURE.REFUNDED` | Full refund issued | Update booking status |
| `CHECKOUT.PAYMENT-APPROVAL.REVERSED` | Approval reversed before capture | Cancel/notify customer |
| `CUSTOMER.DISPUTE.CREATED` | Customer opened a dispute | Alert admin, freeze funds |
| `CUSTOMER.DISPUTE.RESOLVED` | Dispute resolved | Update booking based on outcome |

**All Available Events:** https://developer.paypal.com/api/rest/webhooks/event-names/

### Register a Webhook

**Via PayPal Developer Dashboard (Recommended):**
1. Log in to https://developer.paypal.com/
2. Navigate to **Dashboard** > **My Apps & Credentials**
3. Select your app
4. Scroll to **Webhooks** section
5. Click **Add Webhook**
6. Enter Webhook URL: `https://yoursite.com/wp-json/bkx/v1/paypal-webhook`
7. Select event types to subscribe to
8. Click **Save**
9. **Copy the Webhook ID** (needed for verification)

**Via Webhooks Management API:**

**Endpoint:**
```
POST /v1/notifications/webhooks
```

**Request:**
```json
{
  "url": "https://yoursite.com/wp-json/bkx/v1/paypal-webhook",
  "event_types": [
    {
      "name": "PAYMENT.CAPTURE.COMPLETED"
    },
    {
      "name": "PAYMENT.CAPTURE.DENIED"
    },
    {
      "name": "PAYMENT.CAPTURE.REFUNDED"
    }
  ]
}
```

**Response:**
```json
{
  "id": "8PT597110X687430LKGECATA",
  "url": "https://yoursite.com/wp-json/bkx/v1/paypal-webhook",
  "event_types": [
    {
      "name": "PAYMENT.CAPTURE.COMPLETED",
      "description": "A payment capture completes."
    }
  ],
  "links": [...]
}
```

### Webhook Payload Format

**HTTP Headers:**
```http
PAYPAL-TRANSMISSION-ID: 8e2b6d50-df5f-11ea-87d0-0242ac130003
PAYPAL-TRANSMISSION-TIME: 2024-12-26T10:35:00Z
PAYPAL-TRANSMISSION-SIG: p6KVhli...
PAYPAL-AUTH-ALGO: SHA256withRSA
PAYPAL-CERT-URL: https://api.paypal.com/v1/notifications/certs/CERT-360caa42-fca2a594-1d93a270
Content-Type: application/json
```

**Example Payload (PAYMENT.CAPTURE.COMPLETED):**
```json
{
  "id": "WH-2WR32451HC0233532-67976317FL4543714",
  "event_version": "1.0",
  "create_time": "2024-12-26T10:35:00Z",
  "resource_type": "capture",
  "resource_version": "2.0",
  "event_type": "PAYMENT.CAPTURE.COMPLETED",
  "summary": "Payment completed for USD 100.0",
  "resource": {
    "id": "3C679366HH908993F",
    "status": "COMPLETED",
    "amount": {
      "currency_code": "USD",
      "value": "100.00"
    },
    "final_capture": true,
    "seller_protection": {
      "status": "ELIGIBLE",
      "dispute_categories": [
        "ITEM_NOT_RECEIVED",
        "UNAUTHORIZED_TRANSACTION"
      ]
    },
    "seller_receivable_breakdown": {
      "gross_amount": {
        "currency_code": "USD",
        "value": "100.00"
      },
      "paypal_fee": {
        "currency_code": "USD",
        "value": "3.20"
      },
      "net_amount": {
        "currency_code": "USD",
        "value": "96.80"
      }
    },
    "custom_id": "bkx_booking_456",
    "create_time": "2024-12-26T10:35:00Z",
    "update_time": "2024-12-26T10:35:00Z",
    "links": [...]
  },
  "links": [
    {
      "href": "https://api.paypal.com/v1/notifications/webhooks-events/WH-2WR32451HC0233532-67976317FL4543714",
      "rel": "self",
      "method": "GET"
    }
  ]
}
```

### Webhook Signature Verification

**CRITICAL:** Always verify webhook signatures to prevent fraudulent requests.

#### Method 1: PayPal Verify Webhook Signature API (Recommended)

**Endpoint:**
```
POST /v1/notifications/verify-webhook-signature
```

**Request:**
```json
{
  "auth_algo": "SHA256withRSA",
  "cert_url": "https://api.paypal.com/v1/notifications/certs/CERT-360caa42-fca2a594-1d93a270",
  "transmission_id": "8e2b6d50-df5f-11ea-87d0-0242ac130003",
  "transmission_sig": "p6KVhli...",
  "transmission_time": "2024-12-26T10:35:00Z",
  "webhook_id": "8PT597110X687430LKGECATA",
  "webhook_event": {
    "id": "WH-2WR32451HC0233532-67976317FL4543714",
    "event_type": "PAYMENT.CAPTURE.COMPLETED",
    "resource": {...}
  }
}
```

**Response (Valid):**
```json
{
  "verification_status": "SUCCESS"
}
```

**Response (Invalid):**
```json
{
  "verification_status": "FAILURE"
}
```

**PHP Verification Example:**
```php
public function verify_webhook_signature() {
    // Get webhook headers
    $headers = getallheaders();
    $transmission_id = $headers['PAYPAL-TRANSMISSION-ID'] ?? '';
    $transmission_time = $headers['PAYPAL-TRANSMISSION-TIME'] ?? '';
    $transmission_sig = $headers['PAYPAL-TRANSMISSION-SIG'] ?? '';
    $cert_url = $headers['PAYPAL-CERT-URL'] ?? '';
    $auth_algo = $headers['PAYPAL-AUTH-ALGO'] ?? '';

    // Get raw POST body (IMPORTANT: must be exact, no modifications)
    $webhook_event = file_get_contents( 'php://input' );
    $webhook_event_json = json_decode( $webhook_event, true );

    // Get stored webhook ID from settings
    $webhook_id = $this->get_webhook_id();

    // Prepare verification request
    $verify_data = [
        'auth_algo' => $auth_algo,
        'cert_url' => $cert_url,
        'transmission_id' => $transmission_id,
        'transmission_sig' => $transmission_sig,
        'transmission_time' => $transmission_time,
        'webhook_id' => $webhook_id,
        'webhook_event' => $webhook_event_json,
    ];

    $access_token = $this->get_access_token();

    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, 'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
    ] );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $verify_data ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

    $response = curl_exec( $ch );
    curl_close( $ch );

    $result = json_decode( $response, true );

    return isset( $result['verification_status'] ) && $result['verification_status'] === 'SUCCESS';
}
```

**Important Notes:**
- The `webhook_event` MUST be the exact payload received (no formatting changes)
- Parsing to array and re-encoding can fail verification
- Only real webhooks can be verified (simulator events will fail)
- Store the `webhook_id` in your plugin settings

#### Method 2: Self-Verification (Offline)

For advanced users who want to avoid API dependency:

1. Extract certificate from `PAYPAL-CERT-URL`
2. Cache certificate for future use
3. Verify signature using certificate and RSA-SHA256

**This method is complex and error-prone. Use Method 1 unless you have specific requirements.**

### Webhook Response & Retry

**Your endpoint must:**
- Return HTTP 200 (or any 2xx status) to acknowledge receipt
- Respond within 20 seconds
- Return 2xx even if business logic fails (handle errors asynchronously)

**PayPal Retry Policy:**
- Non-2xx response triggers retry
- Retries up to **25 times** over **3 days**
- Exponential backoff between retries
- After 3 days, marked as FAILED (can be manually resent)

**PHP Webhook Handler Example:**
```php
public function handle_webhook() {
    // Verify signature first
    if ( ! $this->verify_webhook_signature() ) {
        http_response_code( 401 );
        wp_send_json_error( 'Invalid signature' );
        exit;
    }

    // Get webhook data
    $webhook_data = json_decode( file_get_contents( 'php://input' ), true );
    $event_type = $webhook_data['event_type'] ?? '';
    $resource = $webhook_data['resource'] ?? [];

    // Respond immediately
    http_response_code( 200 );
    wp_send_json_success();

    // Process async (after response sent)
    fastcgi_finish_request(); // If available

    // Handle events
    switch ( $event_type ) {
        case 'PAYMENT.CAPTURE.COMPLETED':
            $this->handle_payment_completed( $resource );
            break;

        case 'PAYMENT.CAPTURE.DENIED':
            $this->handle_payment_denied( $resource );
            break;

        case 'PAYMENT.CAPTURE.REFUNDED':
            $this->handle_payment_refunded( $resource );
            break;
    }
}

private function handle_payment_completed( $resource ) {
    $capture_id = $resource['id'];
    $custom_id = $resource['custom_id'] ?? '';

    // Extract booking ID from custom_id
    $booking_id = str_replace( 'bkx_booking_', '', $custom_id );

    if ( ! $booking_id ) {
        return;
    }

    // Update booking status
    wp_update_post( [
        'ID' => $booking_id,
        'post_status' => 'bkx-completed',
    ] );

    update_post_meta( $booking_id, '_paypal_capture_id', $capture_id );
    update_post_meta( $booking_id, '_payment_status', 'completed' );

    // Log
    do_action( 'bkx_paypal_payment_completed', $booking_id, $resource );
}
```

---

## PayPal JavaScript SDK

The PayPal JavaScript SDK allows you to render PayPal buttons and card fields directly on your site.

### Loading the SDK

**Basic Script Tag:**
```html
<script src="https://www.paypal.com/sdk/js?client-id=YOUR_CLIENT_ID&currency=USD"></script>
```

**With Additional Parameters:**
```html
<script src="https://www.paypal.com/sdk/js?client-id=YOUR_CLIENT_ID&currency=USD&intent=capture&components=buttons,card-fields&disable-funding=credit,card"></script>
```

**Query Parameters:**

| Parameter | Required | Description | Example |
|-----------|----------|-------------|---------|
| `client-id` | Yes | Your PayPal Client ID | `AeA1QIZXm...` |
| `currency` | No | Currency code (default: USD) | `USD`, `EUR`, `GBP` |
| `intent` | No | `capture` or `authorize` | `capture` |
| `components` | No | SDK components to load | `buttons,card-fields,marks` |
| `disable-funding` | No | Disable specific payment methods | `credit,card,venmo` |
| `locale` | No | Language/locale | `en_US`, `fr_FR` |

### PayPal Buttons Integration

**HTML Container:**
```html
<div id="paypal-button-container"></div>
```

**JavaScript:**
```javascript
paypal.Buttons({
    // Create order on your server
    createOrder: function(data, actions) {
        return fetch('/wp-json/bkx/v1/paypal-create-order', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                booking_id: 456,
                amount: '100.00'
            })
        }).then(function(res) {
            return res.json();
        }).then(function(orderData) {
            return orderData.id; // Return order ID
        });
    },

    // Capture payment on your server
    onApprove: function(data, actions) {
        return fetch('/wp-json/bkx/v1/paypal-capture-order', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_id: data.orderID,
                booking_id: 456
            })
        }).then(function(res) {
            return res.json();
        }).then(function(captureData) {
            // Show success message
            alert('Payment completed!');
            window.location.href = '/booking-confirmation/?id=456';
        });
    },

    // Handle errors
    onError: function(err) {
        console.error('PayPal error:', err);
        alert('Payment failed. Please try again.');
    },

    // Handle cancellation
    onCancel: function(data) {
        console.log('Payment cancelled:', data);
    }

}).render('#paypal-button-container');
```

### Card Fields Integration (Advanced Card Checkout)

**HTML Structure:**
```html
<div id="card-form">
    <div id="card-number" class="card-field"></div>
    <div id="card-expiry" class="card-field"></div>
    <div id="card-cvv" class="card-field"></div>
    <button id="card-submit">Pay Now</button>
</div>
```

**JavaScript:**
```javascript
if (paypal.CardFields.isEligible()) {
    const cardField = paypal.CardFields({
        createOrder: function(data) {
            return fetch('/wp-json/bkx/v1/paypal-create-order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booking_id: 456, amount: '100.00' })
            }).then(res => res.json()).then(data => data.id);
        },

        onApprove: function(data) {
            return fetch('/wp-json/bkx/v1/paypal-capture-order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: data.orderID })
            }).then(res => res.json()).then(function(captureData) {
                window.location.href = '/booking-confirmation/?id=456';
            });
        }
    });

    // Render individual card fields
    cardField.NumberField().render('#card-number');
    cardField.ExpiryField().render('#card-expiry');
    cardField.CVVField().render('#card-cvv');

    // Submit on button click
    document.getElementById('card-submit').addEventListener('click', function() {
        cardField.submit().catch(function(err) {
            console.error('Card payment failed:', err);
        });
    });
}
```

**3D Secure (SCA) Support:**

To trigger 3D Secure authentication (required in Europe for PSD2 compliance):

```json
{
  "intent": "CAPTURE",
  "payment_source": {
    "card": {
      "attributes": {
        "verification": {
          "method": "SCA_WHEN_REQUIRED"
        }
      }
    }
  }
}
```

- `SCA_ALWAYS` - Trigger authentication for every transaction
- `SCA_WHEN_REQUIRED` - Trigger only when required by regulation

---

## Error Handling

### Error Response Format

All API errors follow this structure:

```json
{
  "name": "VALIDATION_ERROR",
  "message": "Invalid request - see details",
  "debug_id": "8e2b6d50df5f",
  "details": [
    {
      "field": "/purchase_units/0/amount/value",
      "value": "-10.00",
      "issue": "INVALID_PARAMETER_VALUE",
      "description": "Amount value must be greater than 0"
    }
  ],
  "links": [
    {
      "href": "https://developer.paypal.com/docs/api/orders/v2/#error-INVALID_PARAMETER_VALUE",
      "rel": "information_link"
    }
  ]
}
```

### Common HTTP Status Codes

| Code | Meaning | Description |
|------|---------|-------------|
| 200 OK | Success | Request succeeded (GET, PATCH) |
| 201 Created | Created | Resource created (POST) |
| 204 No Content | Success | Resource deleted |
| 400 Bad Request | Client Error | Invalid request (check details) |
| 401 Unauthorized | Auth Error | Invalid/expired access token |
| 403 Forbidden | Permission Error | Not authorized for this action |
| 404 Not Found | Not Found | Resource doesn't exist |
| 422 Unprocessable Entity | Validation Error | Business logic validation failed |
| 429 Too Many Requests | Rate Limited | Too many requests, retry later |
| 500 Internal Server Error | Server Error | PayPal server error, retry |
| 503 Service Unavailable | Unavailable | Temporary downtime, retry |

### Common Error Names

| Error Name | HTTP Code | Cause | Solution |
|------------|-----------|-------|----------|
| `AUTHENTICATION_FAILURE` | 401 | Invalid credentials | Check Client ID/Secret |
| `INVALID_REQUEST` | 400 | Malformed request | Validate JSON structure |
| `VALIDATION_ERROR` | 400 | Invalid parameter values | Check field requirements |
| `RESOURCE_NOT_FOUND` | 404 | Order/capture not found | Verify ID exists |
| `ORDER_NOT_APPROVED` | 422 | Cannot capture unapproved order | Wait for approval first |
| `PERMISSION_DENIED` | 403 | Not authorized | Check account permissions |
| `RATE_LIMIT_REACHED` | 429 | Too many requests | Implement exponential backoff |

### Retry Strategy

**Idempotent Requests (Safe to Retry):**
- GET requests
- POST with `PayPal-Request-Id` header

**Non-Idempotent Requests:**
- POST without idempotency key (may create duplicates)

**Recommended Retry Logic:**
```php
function make_paypal_request( $url, $data, $max_retries = 3 ) {
    $attempt = 0;

    while ( $attempt < $max_retries ) {
        $response = $this->http_client->post( $url, $data );
        $http_code = wp_remote_retrieve_response_code( $response );

        // Success
        if ( $http_code >= 200 && $http_code < 300 ) {
            return json_decode( wp_remote_retrieve_body( $response ), true );
        }

        // Don't retry client errors (except 429)
        if ( $http_code >= 400 && $http_code < 500 && $http_code !== 429 ) {
            throw new Exception( 'Client error: ' . $http_code );
        }

        // Retry server errors and rate limits
        if ( $http_code >= 500 || $http_code === 429 ) {
            $attempt++;
            $wait_time = pow( 2, $attempt ); // Exponential backoff: 2, 4, 8 seconds
            sleep( $wait_time );
            continue;
        }

        // Unknown error
        throw new Exception( 'HTTP ' . $http_code );
    }

    throw new Exception( 'Max retries exceeded' );
}
```

### Idempotency

Use the `PayPal-Request-Id` header to prevent duplicate transactions:

```php
$idempotency_key = wp_generate_uuid4();

$headers = [
    'Authorization' => 'Bearer ' . $access_token,
    'Content-Type' => 'application/json',
    'PayPal-Request-Id' => $idempotency_key,
];

// Store idempotency key with booking
update_post_meta( $booking_id, '_paypal_idempotency_key', $idempotency_key );
```

If the same request is sent again with the same `PayPal-Request-Id`, PayPal will return the original response instead of creating a duplicate.

---

## Data Models

### Order Object

```php
[
    'id'                  => 'string',          // Order ID
    'status'              => 'enum',            // CREATED, SAVED, APPROVED, VOIDED, COMPLETED
    'intent'              => 'enum',            // CAPTURE, AUTHORIZE
    'purchase_units'      => [                  // Array of purchase units
        [
            'reference_id' => 'string',         // Your reference
            'amount'       => [
                'currency_code' => 'string',    // ISO 4217 (USD, EUR, etc.)
                'value'         => 'string',    // Amount (e.g., "100.00")
            ],
            'payee'        => [
                'email_address' => 'string',
                'merchant_id'   => 'string',
            ],
            'payments'     => [                 // After capture/authorize
                'captures' => [...],
                'authorizations' => [...],
            ],
        ],
    ],
    'payer'               => [
        'name' => [
            'given_name' => 'string',
            'surname'    => 'string',
        ],
        'email_address' => 'string',
        'payer_id'      => 'string',
    ],
    'create_time'         => 'string',          // ISO 8601
    'update_time'         => 'string',          // ISO 8601
    'links'               => [                  // HATEOAS links
        [
            'href'   => 'string',
            'rel'    => 'string',               // self, approve, capture, etc.
            'method' => 'string',               // GET, POST, PATCH
        ],
    ],
]
```

### Capture Object

```php
[
    'id'                         => 'string',   // Capture ID
    'status'                     => 'enum',     // COMPLETED, DECLINED, PARTIALLY_REFUNDED, PENDING, REFUNDED
    'amount'                     => [
        'currency_code' => 'string',
        'value'         => 'string',
    ],
    'final_capture'              => 'boolean',
    'seller_protection'          => [
        'status'             => 'string',       // ELIGIBLE, PARTIALLY_ELIGIBLE, NOT_ELIGIBLE
        'dispute_categories' => ['string'],     // ITEM_NOT_RECEIVED, UNAUTHORIZED_TRANSACTION
    ],
    'seller_receivable_breakdown' => [
        'gross_amount'  => [...],
        'paypal_fee'    => [...],
        'net_amount'    => [...],
    ],
    'invoice_id'                 => 'string',   // Optional invoice ID
    'custom_id'                  => 'string',   // Your custom ID
    'create_time'                => 'string',   // ISO 8601
    'update_time'                => 'string',   // ISO 8601
]
```

### Refund Object

```php
[
    'id'                      => 'string',      // Refund ID
    'status'                  => 'enum',        // CANCELLED, PENDING, COMPLETED
    'amount'                  => [
        'currency_code' => 'string',
        'value'         => 'string',
    ],
    'invoice_id'              => 'string',
    'note_to_payer'           => 'string',
    'seller_payable_breakdown' => [
        'gross_amount'           => [...],
        'paypal_fee'             => [...],
        'net_amount'             => [...],
        'total_refunded_amount'  => [...],
    ],
    'create_time'             => 'string',      // ISO 8601
    'update_time'             => 'string',      // ISO 8601
]
```

---

## BookingX Integration Notes

### Extending AbstractPaymentGateway

```php
<?php
namespace BookingX\PayPal;

use BookingX\SDK\Abstracts\AbstractPaymentGateway;
use BookingX\SDK\Traits\HasWebhooks;
use BookingX\SDK\Traits\HasSettings;

class PayPalGateway extends AbstractPaymentGateway {
    use HasWebhooks, HasSettings;

    protected $id = 'paypal';
    protected $name = 'PayPal Commerce Platform';
    protected $supports = [ 'refunds', 'webhooks', 'tokenization' ];

    public function process_payment( $booking_id, $amount ) {
        // Create order via API
        // Redirect to approval URL
        // Return redirect URL
    }

    public function process_refund( $booking_id, $amount ) {
        // Call refund API
        // Return true/false
    }

    protected function get_webhook_events() {
        return [
            'PAYMENT.CAPTURE.COMPLETED',
            'PAYMENT.CAPTURE.DENIED',
            'PAYMENT.CAPTURE.REFUNDED',
        ];
    }
}
```

### Settings Fields

```php
protected function get_settings_fields() {
    return [
        'enabled' => [
            'title'   => __( 'Enable/Disable', 'bkx-paypal' ),
            'type'    => 'checkbox',
            'label'   => __( 'Enable PayPal', 'bkx-paypal' ),
            'default' => 'no',
        ],
        'test_mode' => [
            'title'       => __( 'Test Mode', 'bkx-paypal' ),
            'type'        => 'checkbox',
            'label'       => __( 'Enable sandbox mode for testing', 'bkx-paypal' ),
            'default'     => 'yes',
            'description' => __( 'Use sandbox credentials for testing.', 'bkx-paypal' ),
        ],
        'client_id_live' => [
            'title'       => __( 'Live Client ID', 'bkx-paypal' ),
            'type'        => 'text',
            'description' => __( 'Get your Client ID from PayPal Developer Dashboard.', 'bkx-paypal' ),
        ],
        'client_secret_live' => [
            'title' => __( 'Live Client Secret', 'bkx-paypal' ),
            'type'  => 'password',
        ],
        'client_id_sandbox' => [
            'title'       => __( 'Sandbox Client ID', 'bkx-paypal' ),
            'type'        => 'text',
            'description' => __( 'For testing only.', 'bkx-paypal' ),
        ],
        'client_secret_sandbox' => [
            'title' => __( 'Sandbox Client Secret', 'bkx-paypal' ),
            'type'  => 'password',
        ],
        'webhook_id' => [
            'title'       => __( 'Webhook ID', 'bkx-paypal' ),
            'type'        => 'text',
            'description' => __( 'Copy from PayPal Developer Dashboard after creating webhook.', 'bkx-paypal' ),
        ],
    ];
}
```

### Encryption for Credentials

Always encrypt sensitive credentials using the SDK's `EncryptionService`:

```php
use BookingX\SDK\Services\EncryptionService;

$encryption = new EncryptionService();
$encrypted_secret = $encryption->encrypt( $client_secret );

// Store encrypted
update_option( 'bkx_paypal_client_secret', $encrypted_secret );

// Retrieve and decrypt
$encrypted_secret = get_option( 'bkx_paypal_client_secret' );
$client_secret = $encryption->decrypt( $encrypted_secret );
```

### Database Requirements

No custom tables required. Use WordPress post meta to store transaction IDs:

```php
// On order creation
update_post_meta( $booking_id, '_paypal_order_id', $order_id );

// On capture
update_post_meta( $booking_id, '_paypal_capture_id', $capture_id );
update_post_meta( $booking_id, '_paypal_fee', $fee_amount );
update_post_meta( $booking_id, '_paypal_net_amount', $net_amount );

// On refund
update_post_meta( $booking_id, '_paypal_refund_id', $refund_id );
update_post_meta( $booking_id, '_paypal_refund_amount', $refund_amount );
```

### REST API Endpoints

Register custom endpoints for JavaScript SDK integration:

```php
public function register_rest_routes() {
    register_rest_route( 'bkx/v1', '/paypal-create-order', [
        'methods'             => 'POST',
        'callback'            => [ $this, 'rest_create_order' ],
        'permission_callback' => '__return_true', // Validate nonce in callback
    ] );

    register_rest_route( 'bkx/v1', '/paypal-capture-order', [
        'methods'             => 'POST',
        'callback'            => [ $this, 'rest_capture_order' ],
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( 'bkx/v1', '/paypal-webhook', [
        'methods'             => 'POST',
        'callback'            => [ $this, 'handle_webhook' ],
        'permission_callback' => '__return_true', // Verify signature instead
    ] );
}
```

### Logging

Use the SDK's `LoggerService` for comprehensive logging:

```php
use BookingX\SDK\Services\LoggerService;

$logger = new LoggerService( 'paypal' );

// Log API requests
$logger->info( 'Creating PayPal order', [
    'booking_id' => $booking_id,
    'amount'     => $amount,
] );

// Log errors
$logger->error( 'PayPal capture failed', [
    'order_id' => $order_id,
    'error'    => $error_message,
] );

// Log webhooks
$logger->debug( 'Webhook received', [
    'event_type' => $event_type,
    'resource_id' => $resource_id,
] );
```

---

## Testing Checklist

### Sandbox Testing

- [ ] Create sandbox business account
- [ ] Create sandbox personal account (for testing payments)
- [ ] Generate sandbox API credentials
- [ ] Test order creation
- [ ] Test payment approval flow
- [ ] Test payment capture
- [ ] Test payment authorization (hold)
- [ ] Test authorization capture
- [ ] Test full refund
- [ ] Test partial refund
- [ ] Test webhook signature verification
- [ ] Test webhook event handling
- [ ] Test error scenarios (declined cards, insufficient funds)
- [ ] Test idempotency (duplicate requests)

### Production Checklist

- [ ] Obtain live API credentials
- [ ] Verify business account
- [ ] Configure live webhook
- [ ] Test with small real transaction
- [ ] Verify funds received in PayPal account
- [ ] Test refund in production
- [ ] Monitor error logs
- [ ] Set up access token caching
- [ ] Implement rate limiting
- [ ] Add admin notifications for failed webhooks

---

## Resources

### Official Documentation
- **Main REST API Portal:** https://developer.paypal.com/api/rest/
- **Orders API v2 Reference:** https://developer.paypal.com/docs/api/orders/v2/
- **Payments API v2 Reference:** https://developer.paypal.com/docs/api/payments/v2/
- **Webhooks Guide:** https://developer.paypal.com/api/rest/webhooks/
- **Webhook Events List:** https://developer.paypal.com/api/rest/webhooks/event-names/
- **JavaScript SDK Reference:** https://developer.paypal.com/sdk/js/reference/
- **Authentication Guide:** https://developer.paypal.com/api/rest/authentication/

### Developer Tools
- **PayPal Developer Dashboard:** https://developer.paypal.com/dashboard/
- **Sandbox Accounts:** https://developer.paypal.com/dashboard/accounts
- **Webhook Simulator:** https://developer.paypal.com/dashboard/webhooks-simulator
- **API Explorer (Postman):** https://www.postman.com/paypal/workspace/paypal-public-api

### Testing Resources
- **Sandbox Testing Guide:** https://developer.paypal.com/tools/sandbox/
- **Test Card Numbers:** Use PayPal sandbox test accounts (no test cards needed)
- **Webhook Testing Tool:** https://www.hooklistener.com/ (third-party)

### SDK & Libraries
- **PayPal PHP Server SDK (Official):** https://github.com/paypal/PayPal-PHP-Server-SDK
- **Shopware PayPal SDK:** https://packagist.org/packages/shopware/paypal-sdk
- **AngelLeye PayPal Library:** https://packagist.org/packages/angelleye/paypal-php-library

### Migration Guides
- **Migrate from v1 to v2:** https://developer.paypal.com/docs/checkout/advanced/upgrade/
- **Deprecated SDK Notice:** https://github.com/paypal/PayPal-PHP-SDK (archived)

---

## Appendix: Quick Reference

### Base URLs
```
Sandbox: https://api-m.sandbox.paypal.com
Live:    https://api-m.paypal.com
```

### Authentication Endpoint
```
POST /v1/oauth2/token
Authorization: Basic {BASE64(CLIENT_ID:CLIENT_SECRET)}
Body: grant_type=client_credentials
```

### Core Endpoints
```
POST   /v2/checkout/orders                    - Create order
GET    /v2/checkout/orders/{id}               - Get order
PATCH  /v2/checkout/orders/{id}               - Update order
POST   /v2/checkout/orders/{id}/capture       - Capture payment
POST   /v2/checkout/orders/{id}/authorize     - Authorize payment

POST   /v2/payments/authorizations/{id}/capture - Capture authorized payment
POST   /v2/payments/captures/{id}/refund      - Refund payment
GET    /v2/payments/refunds/{id}              - Get refund details

POST   /v1/notifications/webhooks             - Register webhook
POST   /v1/notifications/verify-webhook-signature - Verify webhook
```

### Key Status Values
```
Order:   CREATED, APPROVED, COMPLETED, VOIDED
Capture: COMPLETED, DECLINED, PENDING, PARTIALLY_REFUNDED, REFUNDED
Refund:  COMPLETED, PENDING, CANCELLED
```

---

**Document End**

*This reference document was created for BookingX PayPal Commerce Platform integration on December 26, 2025. Always refer to official PayPal documentation for the latest updates.*
