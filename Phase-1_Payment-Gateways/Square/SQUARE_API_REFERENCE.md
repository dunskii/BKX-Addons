# Square Payments API Integration Reference

## Overview

- **API Base URL (Production):** `https://connect.squareup.com`
- **API Base URL (Sandbox):** `https://connect.squareupsandbox.com`
- **Official Documentation:** https://developer.squareup.com/docs
- **API Reference:** https://developer.squareup.com/reference/square
- **PHP SDK:** `composer require square/square`
- **Current SDK Version:** 43.2.0.20251016 (requires PHP ^8.1)
- **Web Payments SDK:** `https://web.squarecdn.com/v1/square.js` (Production)
- **Web Payments SDK (Sandbox):** `https://sandbox.web.squarecdn.com/v1/square.js`

---

## Authentication

### Method: OAuth 2.0 / Personal Access Token

Square supports two authentication approaches:

1. **Personal Access Token** - For accessing your own Square account
2. **OAuth 2.0** - For accessing other merchants' Square accounts

### Personal Access Token (Recommended for Initial Development)

```php
use Square\SquareClient;
use Square\Environment;

// Production
$client = new SquareClient([
    'accessToken' => $access_token,
    'environment' => Environment::PRODUCTION,
]);

// Sandbox
$client = new SquareClient([
    'accessToken' => $sandbox_access_token,
    'environment' => Environment::SANDBOX,
]);
```

### OAuth 2.0 Flow (For Multi-Merchant Applications)

Square uses standard OAuth 2.0 with two flow types:

#### Code Flow (For Server-Side Applications)
```
1. Redirect to Authorization URL:
   https://connect.squareup.com/oauth2/authorize?client_id={APP_ID}&scope={PERMISSIONS}&session=false&state={RANDOM_STRING}

2. Capture authorization code from callback

3. Exchange code for access token:
   POST /oauth2/token
   {
       "client_id": "YOUR_APP_ID",
       "client_secret": "YOUR_APP_SECRET",
       "code": "AUTHORIZATION_CODE",
       "grant_type": "authorization_code"
   }
```

#### PKCE Flow (For Public Clients - Mobile/SPA)
Uses `code_verifier` instead of `client_secret` for enhanced security.

### Required Credentials

| Credential | Description | Where to Get |
|------------|-------------|--------------|
| Application ID | Identifies your application | Developer Console > Applications > Credentials |
| Access Token | API authentication token | Developer Console > Applications > Credentials |
| Location ID | Identifies the business location | Developer Console > Applications > Locations |
| Client Secret | OAuth app secret (OAuth only) | Developer Console > Applications > Credentials (OAuth) |

### Token Expiration

- **Authorization Codes:** Expire after 5 minutes
- **OAuth Access Tokens:** Expire after 30 days
- **Refresh Tokens (Code Flow):** Never expire
- **Refresh Tokens (PKCE Flow):** Expire after 90 days (single-use)

### Permissions (Scopes)

Common permissions for payment gateway:
- `PAYMENTS_READ` - Read payment and refund data
- `PAYMENTS_WRITE` - Create and update payments
- `CUSTOMERS_READ` - Read customer data
- `CUSTOMERS_WRITE` - Create and update customers
- `MERCHANT_PROFILE_READ` - Read merchant information

### Environment Switching

```php
// Toggle between environments
use Square\Environment;

$environment = $is_sandbox ? Environment::SANDBOX : Environment::PRODUCTION;

$client = new SquareClient([
    'accessToken' => $access_token,
    'environment' => $environment,
]);
```

---

## Payments API

### Create Payment

**Endpoint:** `POST /v2/payments`

**Permissions:** `PAYMENTS_WRITE`

**Request:**
```json
{
    "idempotency_key": "4935a656-a929-4792-b97c-8848be85c27c",
    "source_id": "cnon:card-nonce-ok",
    "amount_money": {
        "amount": 1000,
        "currency": "USD"
    },
    "location_id": "L0Z7HXQNKRCYX",
    "customer_id": "CUSTOMER_ID_123",
    "reference_id": "booking_123",
    "note": "Booking for John Doe",
    "autocomplete": true,
    "tip_money": {
        "amount": 100,
        "currency": "USD"
    }
}
```

**Required Parameters:**
- `idempotency_key` (string, max 45 chars) - Unique identifier to prevent duplicate charges
- `source_id` (string) - Payment token from Web Payments SDK or card ID for card-on-file
- `amount_money` (object) - Amount in smallest currency unit (cents)
  - `amount` (integer) - Amount in smallest unit
  - `currency` (string) - ISO 4217 currency code

**Optional Parameters:**
- `location_id` (string) - Square location (defaults to account's default location)
- `customer_id` (string) - Link payment to customer profile
- `reference_id` (string) - External reference (e.g., booking ID)
- `note` (string) - Description visible to customer
- `autocomplete` (boolean) - Auto-complete payment (default: true)
- `tip_money` (object) - Tip amount
- `app_fee_money` (object) - Application fee to collect
- `billing_address` (object) - Customer billing address
- `shipping_address` (object) - Customer shipping address

**Response:**
```json
{
    "payment": {
        "id": "R2B3Z8WMVt3EAmzYWLZvz7Y69EbZY",
        "created_at": "2024-12-27T10:30:00.000Z",
        "updated_at": "2024-12-27T10:30:00.000Z",
        "amount_money": {
            "amount": 1000,
            "currency": "USD"
        },
        "tip_money": {
            "amount": 100,
            "currency": "USD"
        },
        "total_money": {
            "amount": 1100,
            "currency": "USD"
        },
        "status": "COMPLETED",
        "source_type": "CARD",
        "card_details": {
            "status": "CAPTURED",
            "card": {
                "card_brand": "VISA",
                "last_4": "1111",
                "exp_month": 12,
                "exp_year": 2025,
                "fingerprint": "sq-1-...",
                "card_type": "CREDIT"
            },
            "entry_method": "KEYED",
            "cvv_status": "CVV_ACCEPTED",
            "avs_status": "AVS_ACCEPTED"
        },
        "location_id": "L0Z7HXQNKRCYX",
        "reference_id": "booking_123",
        "customer_id": "CUSTOMER_ID_123",
        "receipt_number": "R2B3",
        "receipt_url": "https://squareup.com/receipt/preview/R2B3..."
    }
}
```

**Payment Status Values:**
- `APPROVED` - Authorized, awaiting completion or cancellation
- `COMPLETED` - Payment captured, funds credited to merchant
- `CANCELED` - Payment canceled, funds released
- `FAILED` - Payment declined by bank/processor

**PHP Example (New SDK v41.0.0+):**
```php
use Square\SquareClient;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Types\Money;
use Square\Types\Currency;

$client = new SquareClient([
    'accessToken' => $access_token,
    'environment' => Environment::PRODUCTION,
]);

$response = $client->payments->create(
    request: new CreatePaymentRequest([
        'idempotencyKey' => wp_generate_uuid4(),
        'sourceId' => $payment_token, // From Web Payments SDK
        'amountMoney' => new Money([
            'amount' => 1000, // $10.00 in cents
            'currency' => Currency::Usd->value
        ]),
        'locationId' => $location_id,
        'customerId' => $customer_id,
        'referenceId' => 'booking_' . $booking_id,
        'note' => 'Booking payment',
        'autocomplete' => true,
    ])
);

if ($response->isSuccess()) {
    $payment = $response->getResult()->getPayment();
    $payment_id = $payment->getId();
    $status = $payment->getStatus();
} else {
    $errors = $response->getErrors();
    // Handle errors
}
```

**PHP Example (Legacy SDK Pre-v41.0.0):**
```php
use Square\SquareClient;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;

$client = new SquareClient([
    'accessToken' => $access_token,
    'environment' => getenv('ENVIRONMENT')
]);

$payments_api = $client->getPaymentsApi();

$money = new Money();
$money->setAmount(1000);
$money->setCurrency('USD');

$create_payment_request = new CreatePaymentRequest(
    $payment_token,
    wp_generate_uuid4(),
    $money
);
$create_payment_request->setLocationId($location_id);
$create_payment_request->setCustomerId($customer_id);
$create_payment_request->setReferenceId('booking_' . $booking_id);
$create_payment_request->setAutocomplete(true);

$response = $payments_api->createPayment($create_payment_request);

if ($response->isSuccess()) {
    $payment = $response->getResult()->getPayment();
} else {
    $errors = $response->getErrors();
}
```

### Minimum Payment Amounts

| Payment Type | Minimum Amount |
|--------------|----------------|
| Card (Most Countries) | $0.01 USD |
| Card (Japan) | ¥1 JPY |
| ACH Bank Transfer (US) | $0.01 USD |
| Cash/External | $0.00 |
| Afterpay | $1.00 USD (max $2,000, $4,000 monthly) |

### Get Payment

**Endpoint:** `GET /v2/payments/{payment_id}`

**Permissions:** `PAYMENTS_READ`

**PHP Example:**
```php
$response = $client->getPaymentsApi()->getPayment($payment_id);

if ($response->isSuccess()) {
    $payment = $response->getResult()->getPayment();
    $status = $payment->getStatus();
}
```

### List Payments

**Endpoint:** `GET /v2/payments`

**Query Parameters:**
- `begin_time` - Start of time range (RFC 3339)
- `end_time` - End of time range
- `sort_order` - ASC or DESC
- `cursor` - Pagination cursor
- `location_id` - Filter by location
- `total` - Filter by exact amount
- `last_4` - Filter by card last 4 digits
- `card_brand` - Filter by card brand

**PHP Example:**
```php
$response = $client->getPaymentsApi()->listPayments(
    $begin_time,
    $end_time,
    $sort_order,
    $cursor,
    $location_id
);

if ($response->isSuccess()) {
    $payments = $response->getResult()->getPayments();
    $cursor = $response->getResult()->getCursor(); // For pagination
}
```

---

## Web Payments SDK (Frontend Card Tokenization)

The Web Payments SDK runs in the browser to securely collect payment information and generate single-use payment tokens.

### Include SDK Script

```html
<!-- Production -->
<script type="text/javascript" src="https://web.squarecdn.com/v1/square.js"></script>

<!-- Sandbox -->
<script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
```

### Initialize SDK

```javascript
// Initialize with Application ID and Location ID
const payments = window.Square.payments(APPLICATION_ID, LOCATION_ID);
```

### Create Card Payment Form

```javascript
async function initializeCard(payments) {
    // Create card instance
    const card = await payments.card();

    // Attach to DOM element
    await card.attach('#card-container');

    return card;
}

// On form submission
async function handlePaymentMethodSubmission(event, card) {
    event.preventDefault();

    // Tokenize card with verification
    const tokenResult = await card.tokenize({
        verificationDetails: {
            amount: '10.00',
            currencyCode: 'USD',
            intent: 'CHARGE',
            billingContact: {
                givenName: 'John',
                familyName: 'Doe',
                email: 'john@example.com',
                phone: '+1-555-555-5555',
                addressLines: ['123 Main St'],
                city: 'San Francisco',
                state: 'CA',
                postalCode: '94103',
                countryCode: 'US'
            }
        }
    });

    if (tokenResult.status === 'OK') {
        // Send token to server
        const token = tokenResult.token;

        fetch('/process-payment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                sourceId: token,
                amount: 1000, // cents
                currency: 'USD'
            })
        });
    } else {
        // Handle tokenization errors
        console.error('Tokenization errors:', tokenResult.errors);
    }
}

// Initialize
const payments = Square.payments(APPLICATION_ID, LOCATION_ID);
const card = await initializeCard(payments);

// Attach to form
document.getElementById('payment-form').addEventListener('submit', async (event) => {
    await handlePaymentMethodSubmission(event, card);
});
```

### HTML Structure

```html
<form id="payment-form">
    <div id="card-container"></div>
    <button id="card-button" type="submit">Pay $10.00</button>
</form>
```

### Verification Details (Required as of October 1, 2025)

Starting October 1, 2025, Square requires Strong Customer Authentication (SCA) via the `verificationDetails` parameter in `card.tokenize()`:

```javascript
const tokenResult = await card.tokenize({
    verificationDetails: {
        amount: '10.00',          // String amount
        currencyCode: 'USD',       // 3-letter currency code
        intent: 'CHARGE',          // CHARGE or STORE
        billingContact: {          // Required billing information
            givenName: 'John',
            familyName: 'Doe',
            email: 'john@example.com',
            addressLines: ['123 Main St'],
            city: 'San Francisco',
            state: 'CA',
            postalCode: '94103',
            countryCode: 'US'
        }
    }
});
```

### Styling Card Form

```javascript
const card = await payments.card({
    style: {
        'input': {
            'fontSize': '16px',
            'color': '#373F4A',
            'fontFamily': 'Helvetica Neue, Helvetica, Arial, sans-serif'
        },
        'input::placeholder': {
            'color': '#BDBDBD'
        },
        'input.is-error': {
            'color': '#D32F2F'
        }
    }
});
```

### Other Payment Methods

```javascript
// Google Pay
const googlePay = await payments.googlePay({
    merchantId: 'MERCHANT_ID',
    buttonColor: 'black',
    buttonSizeMode: 'fill',
    buttonType: 'long'
});
await googlePay.attach('#google-pay-button');

// Apple Pay
const applePay = await payments.applePay();
await applePay.attach('#apple-pay-button');

// ACH Bank Transfer
const ach = await payments.ach();
await ach.attach('#ach-button');

// Gift Card
const giftCard = await payments.giftCard();
await giftCard.attach('#gift-card-container');
```

---

## Refunds API

### Refund Payment

**Endpoint:** `POST /v2/refunds`

**Permissions:** `PAYMENTS_WRITE`

**Request (Full Refund):**
```json
{
    "idempotency_key": "9b7f2dcf-49da-4411-b23e-a2d6af21333a",
    "payment_id": "R2B3Z8WMVt3EAmzYWLZvz7Y69EbZY",
    "amount_money": {
        "amount": 1000,
        "currency": "USD"
    },
    "reason": "Customer requested refund"
}
```

**Request (Partial Refund):**
```json
{
    "idempotency_key": "unique-key-123",
    "payment_id": "R2B3Z8WMVt3EAmzYWLZvz7Y69EbZY",
    "amount_money": {
        "amount": 500,
        "currency": "USD"
    },
    "reason": "Partial cancellation"
}
```

**Required Parameters:**
- `idempotency_key` (string, max 45 chars) - Unique refund identifier
- `amount_money` (object) - Refund amount

**Optional Parameters:**
- `payment_id` (string) - Required for linked refunds
- `reason` (string, max 192 chars) - Refund description
- `location_id` (string) - Required for unlinked refunds
- `customer_id` (string) - For card-on-file refunds
- `unlinked` (boolean) - Set true for non-Square payment refunds
- `app_fee_money` (object) - Developer contribution to refund

**Response:**
```json
{
    "refund": {
        "id": "REFUND_ID_123",
        "status": "PENDING",
        "amount_money": {
            "amount": 1000,
            "currency": "USD"
        },
        "payment_id": "R2B3Z8WMVt3EAmzYWLZvz7Y69EbZY",
        "location_id": "L0Z7HXQNKRCYX",
        "reason": "Customer requested refund",
        "created_at": "2024-12-27T11:00:00.000Z",
        "updated_at": "2024-12-27T11:00:00.000Z"
    }
}
```

**Refund Status Values:**
- `PENDING` - Refund initiated, processing
- `COMPLETED` - Refund completed
- `REJECTED` - Refund rejected (insufficient funds, etc.)
- `FAILED` - Refund failed

**PHP Example:**
```php
use Square\Models\Money;
use Square\Models\RefundPaymentRequest;

$refunds_api = $client->getRefundsApi();

$amount_money = new Money();
$amount_money->setAmount(1000);
$amount_money->setCurrency('USD');

$body = new RefundPaymentRequest(
    wp_generate_uuid4(),
    $amount_money
);
$body->setPaymentId($payment_id);
$body->setReason('Customer requested refund');

$response = $refunds_api->refundPayment($body);

if ($response->isSuccess()) {
    $refund = $response->getResult()->getRefund();
    $refund_id = $refund->getId();
    $status = $refund->getStatus();
} else {
    $errors = $response->getErrors();
}
```

### Refund Restrictions

- Cannot refund payments older than 1 year
- Maximum 20 refunds per payment
- Cannot refund more than original payment amount
- Can only refund `COMPLETED` payments
- Split tender payments require full or per-tender refunds
- Most refunds complete within a few hours (max 14 days pending)
- Funds appear in customer account in 7-10 business days

### Get Refund

**Endpoint:** `GET /v2/refunds/{refund_id}`

**PHP Example:**
```php
$response = $client->getRefundsApi()->getRefund($refund_id);

if ($response->isSuccess()) {
    $refund = $response->getResult()->getRefund();
}
```

### List Payment Refunds

**Endpoint:** `GET /v2/refunds`

**Query Parameters:**
- `begin_time` - Start time (RFC 3339)
- `end_time` - End time
- `sort_order` - ASC or DESC
- `cursor` - Pagination cursor
- `location_id` - Filter by location
- `status` - Filter by status
- `source_type` - Filter by source type

**PHP Example:**
```php
$response = $client->getRefundsApi()->listPaymentRefunds(
    $begin_time,
    $end_time,
    $sort_order,
    $cursor,
    $location_id,
    $status,
    $source_type
);

if ($response->isSuccess()) {
    $refunds = $response->getResult()->getRefunds();
}
```

---

## Cards API (Save Card on File)

### Create Card

**Endpoint:** `POST /v2/cards`

**Permissions:** `PAYMENTS_WRITE`

**Request:**
```json
{
    "idempotency_key": "unique-card-key-123",
    "source_id": "cnon:card-nonce-ok",
    "card": {
        "customer_id": "CUSTOMER_ID_123",
        "billing_address": {
            "address_line_1": "123 Main St",
            "locality": "San Francisco",
            "administrative_district_level_1": "CA",
            "postal_code": "94103",
            "country": "US"
        },
        "cardholder_name": "John Doe"
    }
}
```

**Required Parameters:**
- `idempotency_key` (string) - Unique identifier
- `source_id` (string) - Payment token from Web Payments SDK or payment ID
- `card.customer_id` (string) - Square customer ID

**Important:**
- Postal code in `billing_address` must match the postal code from the SDK tokenization
- In Sandbox, postal code must be `94103` when using test tokens
- Always obtain customer permission before saving cards (checkbox in checkout flow)

**Response:**
```json
{
    "card": {
        "id": "ccof:CARD_ID_123",
        "card_brand": "VISA",
        "last_4": "1111",
        "exp_month": 12,
        "exp_year": 2025,
        "cardholder_name": "John Doe",
        "billing_address": {
            "address_line_1": "123 Main St",
            "locality": "San Francisco",
            "administrative_district_level_1": "CA",
            "postal_code": "94103",
            "country": "US"
        },
        "fingerprint": "sq-1-...",
        "customer_id": "CUSTOMER_ID_123",
        "merchant_id": "MERCHANT_ID",
        "enabled": true,
        "card_type": "CREDIT",
        "prepaid_type": "NOT_PREPAID",
        "bin": "411111"
    }
}
```

**PHP Example:**
```php
use Square\Models\Card;
use Square\Models\Address;
use Square\Models\CreateCardRequest;

$cards_api = $client->getCardsApi();

$billing_address = new Address();
$billing_address->setAddressLine1('123 Main St');
$billing_address->setLocality('San Francisco');
$billing_address->setAdministrativeDistrictLevel1('CA');
$billing_address->setPostalCode('94103');
$billing_address->setCountry('US');

$card = new Card();
$card->setCustomerId($customer_id);
$card->setBillingAddress($billing_address);
$card->setCardholderName('John Doe');

$body = new CreateCardRequest(
    wp_generate_uuid4(),
    $payment_token, // From Web Payments SDK
    $card
);

$response = $cards_api->createCard($body);

if ($response->isSuccess()) {
    $card = $response->getResult()->getCard();
    $card_id = $card->getId(); // Use this for future payments
} else {
    $errors = $response->getErrors();
}
```

### Use Saved Card for Payment

```php
// Use card ID as source_id in CreatePayment
$create_payment_request = new CreatePaymentRequest([
    'idempotencyKey' => wp_generate_uuid4(),
    'sourceId' => 'ccof:CARD_ID_123', // Card on file ID
    'amountMoney' => new Money([
        'amount' => 2000,
        'currency' => Currency::Usd->value
    ]),
    'customerId' => $customer_id,
    'autocomplete' => true,
]);

$response = $client->payments->create(request: $create_payment_request);
```

### Disable Card

**Endpoint:** `POST /v2/cards/{card_id}/disable`

**PHP Example:**
```php
$response = $client->getCardsApi()->disableCard($card_id);

if ($response->isSuccess()) {
    $card = $response->getResult()->getCard();
    // Card is now disabled (enabled = false)
}
```

### List Customer Cards

**Endpoint:** `GET /v2/cards`

**Query Parameters:**
- `customer_id` - Filter by customer
- `include_disabled` - Include disabled cards
- `cursor` - Pagination cursor

**PHP Example:**
```php
$response = $client->getCardsApi()->listCards(
    $cursor,
    $customer_id,
    $include_disabled
);

if ($response->isSuccess()) {
    $cards = $response->getResult()->getCards();
}
```

### Card Auto-Updates

Square automatically updates card expiration dates when notified by participating banks. No action required.

---

## Customers API

### Create Customer

**Endpoint:** `POST /v2/customers`

**Permissions:** `CUSTOMERS_WRITE`

**Request:**
```json
{
    "idempotency_key": "unique-customer-key-123",
    "given_name": "John",
    "family_name": "Doe",
    "email_address": "john@example.com",
    "phone_number": "+1-555-555-5555",
    "address": {
        "address_line_1": "123 Main St",
        "locality": "San Francisco",
        "administrative_district_level_1": "CA",
        "postal_code": "94103",
        "country": "US"
    },
    "reference_id": "user_123",
    "note": "Customer from BookingX"
}
```

**Response:**
```json
{
    "customer": {
        "id": "CUSTOMER_ID_123",
        "created_at": "2024-12-27T10:00:00.000Z",
        "updated_at": "2024-12-27T10:00:00.000Z",
        "given_name": "John",
        "family_name": "Doe",
        "email_address": "john@example.com",
        "phone_number": "+1-555-555-5555",
        "address": {
            "address_line_1": "123 Main St",
            "locality": "San Francisco",
            "administrative_district_level_1": "CA",
            "postal_code": "94103",
            "country": "US"
        },
        "reference_id": "user_123",
        "note": "Customer from BookingX",
        "preferences": {
            "email_unsubscribed": false
        }
    }
}
```

**PHP Example:**
```php
use Square\Models\Address;
use Square\Models\CreateCustomerRequest;

$customers_api = $client->getCustomersApi();

$address = new Address();
$address->setAddressLine1('123 Main St');
$address->setLocality('San Francisco');
$address->setAdministrativeDistrictLevel1('CA');
$address->setPostalCode('94103');
$address->setCountry('US');

$body = new CreateCustomerRequest();
$body->setIdempotencyKey(wp_generate_uuid4());
$body->setGivenName('John');
$body->setFamilyName('Doe');
$body->setEmailAddress('john@example.com');
$body->setPhoneNumber('+1-555-555-5555');
$body->setAddress($address);
$body->setReferenceId('user_123');

$response = $customers_api->createCustomer($body);

if ($response->isSuccess()) {
    $customer = $response->getResult()->getCustomer();
    $customer_id = $customer->getId();
} else {
    $errors = $response->getErrors();
}
```

### Search Customers

**Endpoint:** `POST /v2/customers/search`

**Request:**
```json
{
    "query": {
        "filter": {
            "email_address": {
                "exact": "john@example.com"
            }
        }
    },
    "limit": 10
}
```

**PHP Example:**
```php
use Square\Models\SearchCustomersRequest;
use Square\Models\CustomerQuery;
use Square\Models\CustomerFilter;
use Square\Models\CustomerTextFilter;

$email_filter = new CustomerTextFilter();
$email_filter->setExact('john@example.com');

$filter = new CustomerFilter();
$filter->setEmailAddress($email_filter);

$query = new CustomerQuery();
$query->setFilter($filter);

$body = new SearchCustomersRequest();
$body->setQuery($query);
$body->setLimit(10);

$response = $client->getCustomersApi()->searchCustomers($body);

if ($response->isSuccess()) {
    $customers = $response->getResult()->getCustomers();
}
```

### Retrieve Customer

**Endpoint:** `GET /v2/customers/{customer_id}`

**PHP Example:**
```php
$response = $client->getCustomersApi()->retrieveCustomer($customer_id);

if ($response->isSuccess()) {
    $customer = $response->getResult()->getCustomer();
}
```

### Update Customer

**Endpoint:** `PUT /v2/customers/{customer_id}`

**PHP Example:**
```php
use Square\Models\UpdateCustomerRequest;

$body = new UpdateCustomerRequest();
$body->setEmailAddress('newemail@example.com');
$body->setPhoneNumber('+1-555-555-1234');

$response = $client->getCustomersApi()->updateCustomer($customer_id, $body);
```

---

## Webhooks

### Available Events

#### Payments Events

| Event | Trigger | Payload |
|-------|---------|---------|
| `payment.created` | Payment object created | Payment object |
| `payment.updated` | Payment fields changed (status, card details) | Payment object |

#### Refunds Events

| Event | Trigger | Payload |
|-------|---------|---------|
| `refund.created` | Refund initiated | Refund object |
| `refund.updated` | Refund status changed | Refund object |

#### Customer Events

| Event | Trigger | Payload |
|-------|---------|---------|
| `customer.created` | Customer profile created | Customer object |
| `customer.updated` | Customer attributes changed | Customer object |
| `customer.deleted` | Customer profile deleted | Customer object |

#### Other Events

- `dispute.created` - Chargeback/dispute created
- `dispute.state.updated` - Dispute status changed
- `gift_card.created` - Gift card created
- `gift_card.updated` - Gift card balance changed
- `subscription.created` - Subscription created
- `subscription.updated` - Subscription changed

### Webhook Registration

1. **Via Developer Console:**
   - Navigate to Applications > Webhooks
   - Click "Add Subscription"
   - Enter notification URL (must be HTTPS)
   - Select events to subscribe
   - Save to receive signature key

2. **Via API:** Not currently supported - must use Developer Console

### Webhook Payload Format

```json
{
    "merchant_id": "MERCHANT_ID",
    "location_id": "LOCATION_ID",
    "type": "payment.updated",
    "event_id": "unique-event-id-123",
    "created_at": "2024-12-27T10:30:00.000Z",
    "data": {
        "type": "payment",
        "id": "R2B3Z8WMVt3EAmzYWLZvz7Y69EbZY",
        "object": {
            "payment": {
                "id": "R2B3Z8WMVt3EAmzYWLZvz7Y69EbZY",
                "status": "COMPLETED",
                "amount_money": {
                    "amount": 1000,
                    "currency": "USD"
                }
            }
        }
    }
}
```

### Webhook Headers

- `x-square-hmacsha256-signature` - HMAC-SHA256 signature for verification
- `square-environment` - "Production" or "Sandbox"
- `Content-Type` - "application/json"

### Signature Verification

**Important:** Always verify webhook signatures to prevent malicious requests.

**PHP Example (Constant-Time Comparison):**
```php
/**
 * Verify Square webhook signature
 *
 * @param string $payload Raw POST body
 * @param string $signature Value from x-square-hmacsha256-signature header
 * @param string $signature_key Webhook signature key from Developer Console
 * @param string $notification_url Your webhook URL
 * @return bool True if signature is valid
 */
function verify_square_webhook( $payload, $signature, $signature_key, $notification_url ) {
    // Concatenate notification URL and request body
    $body = $notification_url . $payload;

    // Generate HMAC-SHA256 signature
    $expected_signature = hash_hmac( 'sha256', $body, $signature_key, false );

    // Use constant-time comparison to prevent timing attacks
    return hash_equals( $expected_signature, $signature );
}

// In webhook handler
$payload = file_get_contents( 'php://input' );
$signature = $_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] ?? '';
$signature_key = get_option( 'bkx_square_webhook_signature_key' );
$notification_url = 'https://example.com/wp-json/bookingx/v1/square/webhook';

if ( ! verify_square_webhook( $payload, $signature, $signature_key, $notification_url ) ) {
    http_response_code( 401 );
    wp_die( 'Invalid signature', 'Webhook Error', array( 'response' => 401 ) );
}

// Signature valid - process webhook
$event = json_decode( $payload, true );
$event_type = $event['type'];
$payment_id = $event['data']['object']['payment']['id'] ?? null;

// Process based on event type
switch ( $event_type ) {
    case 'payment.updated':
        // Update booking payment status
        break;
    case 'refund.updated':
        // Update refund status
        break;
}

// Return 2xx status quickly
http_response_code( 200 );
echo 'OK';
```

**Using Square SDK (Node.js - for reference):**
```javascript
const { WebhooksHelper } = require('square');

const isValidSignature = WebhooksHelper.verifySignature({
    requestBody: req.body,
    signatureHeader: req.headers['x-square-hmacsha256-signature'],
    signatureKey: SIGNATURE_KEY,
    notificationUrl: NOTIFICATION_URL
});
```

### Webhook Response Requirements

- **Must** respond with `2xx` status code quickly (within seconds)
- Return response **before** processing (process asynchronously if needed)
- Square retries failed webhooks for up to 24 hours using exponential backoff
- Repeated failures may result in subscription being disabled

### Retry Policy

- Initial retry: Immediately
- Subsequent retries: Exponential backoff
- Maximum retry period: 24 hours
- After 24 hours: Webhook event is dropped

### Webhook Testing

Use the Square Sandbox to test webhooks:
1. Create webhook subscription in Sandbox environment
2. Trigger test events via API calls
3. Verify your endpoint receives and processes events correctly
4. Check signature verification works

---

## Error Handling

### Error Response Format

```json
{
    "errors": [
        {
            "category": "INVALID_REQUEST_ERROR",
            "code": "INVALID_CARD_DATA",
            "detail": "Card verification failed",
            "field": "source_id"
        }
    ]
}
```

**Error Object Fields:**
- `category` - Error category
- `code` - Specific error code
- `detail` - Human-readable description
- `field` - Field that caused the error (if applicable)

### Error Categories

| Category | Description |
|----------|-------------|
| `API_ERROR` | Square service error |
| `AUTHENTICATION_ERROR` | Invalid or missing access token |
| `INVALID_REQUEST_ERROR` | Malformed request or invalid parameters |
| `RATE_LIMIT_ERROR` | Too many requests |
| `PAYMENT_METHOD_ERROR` | Payment method declined or invalid |
| `REFUND_ERROR` | Refund cannot be processed |

### Common Error Codes

#### Payment Errors

| Code | Description | Resolution |
|------|-------------|------------|
| `GENERIC_DECLINE` | Card declined by issuer | Ask customer to use different card |
| `CVV_FAILURE` | CVV verification failed | Ask customer to verify CVV |
| `ADDRESS_VERIFICATION_FAILURE` | AVS check failed | Verify billing address |
| `INVALID_CARD_DATA` | Invalid card data | Re-tokenize card |
| `CARD_EXPIRED` | Card has expired | Ask for updated card |
| `INSUFFICIENT_FUNDS` | Insufficient funds | Ask customer to use different payment method |
| `INVALID_PIN` | Invalid PIN (debit cards) | Ask customer to re-enter PIN |
| `TEMPORARILY_BLOCKED` | Card temporarily blocked | Ask customer to contact bank |
| `CARD_NOT_SUPPORTED` | Card type not supported | Ask for different card |
| `INVALID_ACCOUNT` | Invalid card account | Ask for different card |

#### Refund Errors

| Code | Description | Resolution |
|------|-------------|------------|
| `REFUND_AMOUNT_INVALID` | Refund exceeds payment amount | Reduce refund amount |
| `PAYMENT_NOT_REFUNDABLE` | Payment too old or in dispute | Cannot refund |
| `INSUFFICIENT_PERMISSIONS_FOR_REFUND` | Missing permissions | Check OAuth scopes |

#### API Errors

| Code | Description | Resolution |
|------|-------------|------------|
| `UNAUTHORIZED` | Invalid access token | Refresh token or re-authenticate |
| `ACCESS_TOKEN_EXPIRED` | Access token expired | Use refresh token to get new access token |
| `INSUFFICIENT_SCOPES` | Missing required permissions | Request additional OAuth scopes |
| `RATE_LIMITED` | Too many requests | Implement exponential backoff |
| `SERVICE_UNAVAILABLE` | Square service temporarily down | Retry with exponential backoff |
| `GATEWAY_TIMEOUT` | Request timed out | Retry request |

### PHP Error Handling Example

```php
use Square\Exceptions\ApiException;

try {
    $response = $client->payments->create(
        request: new CreatePaymentRequest([
            'idempotencyKey' => wp_generate_uuid4(),
            'sourceId' => $payment_token,
            'amountMoney' => new Money([
                'amount' => 1000,
                'currency' => Currency::Usd->value
            ]),
        ])
    );

    if ( $response->isSuccess() ) {
        $payment = $response->getResult()->getPayment();
        // Success
    } else {
        $errors = $response->getErrors();
        foreach ( $errors as $error ) {
            $category = $error->getCategory();
            $code = $error->getCode();
            $detail = $error->getDetail();

            // Log error
            error_log( sprintf(
                'Square Payment Error: %s - %s - %s',
                $category,
                $code,
                $detail
            ) );

            // Handle specific errors
            if ( $code === 'CARD_DECLINED' ) {
                // Show user-friendly message
                throw new Exception( 'Your card was declined. Please try a different payment method.' );
            }
        }
    }
} catch ( ApiException $e ) {
    // Network or API error
    error_log( 'Square API Exception: ' . $e->getMessage() );
    throw new Exception( 'Payment processing error. Please try again.' );
}
```

### Retry Strategy

**Recommended Approach:**

1. **Idempotent Requests:** Use unique `idempotency_key` for all API calls
2. **Exponential Backoff:** For rate limits and service errors
3. **Don't Retry:** Payment declines, invalid data errors
4. **Retry Once:** Network errors, timeouts
5. **Retry with Backoff:** Rate limits, service unavailable

**PHP Retry Example:**
```php
function call_square_api_with_retry( callable $api_call, $max_retries = 3 ) {
    $retry_count = 0;
    $base_delay = 1; // seconds

    while ( $retry_count < $max_retries ) {
        try {
            $response = $api_call();

            if ( $response->isSuccess() ) {
                return $response->getResult();
            }

            $errors = $response->getErrors();
            $should_retry = false;

            foreach ( $errors as $error ) {
                $category = $error->getCategory();

                // Retry on rate limits and API errors
                if ( $category === 'RATE_LIMIT_ERROR' || $category === 'API_ERROR' ) {
                    $should_retry = true;
                    break;
                }
            }

            if ( ! $should_retry ) {
                return $errors; // Don't retry
            }

        } catch ( ApiException $e ) {
            // Network error - retry
            $should_retry = true;
        }

        if ( $should_retry && $retry_count < $max_retries - 1 ) {
            $retry_count++;
            $delay = $base_delay * pow( 2, $retry_count ); // Exponential backoff
            sleep( $delay );
        } else {
            throw new Exception( 'Max retries exceeded' );
        }
    }
}
```

### Idempotency

All mutation endpoints (CreatePayment, RefundPayment, CreateCard, CreateCustomer) require an `idempotency_key`:

- **Purpose:** Prevent duplicate operations from network retries
- **Format:** Any string, max 45 characters
- **Lifetime:** Keys are stored for 24 hours
- **Best Practice:** Use UUID v4

**WordPress UUID Generation:**
```php
$idempotency_key = wp_generate_uuid4();
```

**PHP UUID (without WordPress):**
```php
function generate_idempotency_key() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff )
    );
}
```

---

## Sandbox Testing

### Sandbox Environment

- **Base URL:** `https://connect.squareupsandbox.com`
- **Web Payments SDK:** `https://sandbox.web.squarecdn.com/v1/square.js`
- **Access:** Free, unlimited API calls
- **Isolation:** Completely separate from production

### Getting Sandbox Credentials

1. Open [Developer Console](https://developer.squareup.com/apps)
2. Toggle to **Sandbox** mode (top of page)
3. Navigate to your application
4. Go to **Credentials** tab
   - Copy **Sandbox Application ID**
   - Copy **Sandbox Access Token**
5. Go to **Locations** tab
   - Copy **Sandbox Location ID**

### Test Payment Token

**Standard test token:** `cnon:card-nonce-ok`

Use this token directly in CreatePayment requests without Web Payments SDK:

```php
$create_payment_request = new CreatePaymentRequest([
    'idempotencyKey' => wp_generate_uuid4(),
    'sourceId' => 'cnon:card-nonce-ok', // Test token
    'amountMoney' => new Money([
        'amount' => 1000,
        'currency' => Currency::Usd->value
    ]),
]);
```

### Test Card Numbers

When using Web Payments SDK in Sandbox, use these test cards:

| Card Number | Brand | Behavior |
|-------------|-------|----------|
| 4111 1111 1111 1111 | Visa | Success |
| 5105 1051 0510 5100 | Mastercard | Success |
| 3782 822463 10005 | American Express | Success |
| 6011 1111 1111 1117 | Discover | Success |
| 3566 0020 2036 0505 | JCB | Success |
| 4000 0000 0000 0002 | Visa | CVV failure |
| 4000 0000 0000 0010 | Visa | AVS failure |
| 4000 0000 0000 0028 | Visa | Generic decline |

**Additional test details:**
- **Expiration:** Any future date
- **CVV:** Any 3-4 digits
- **Postal Code (Sandbox only):** Must use `94103` for card-on-file requests

### Test ACH Bank Transfer

- **Username:** `user_good`
- **Password:** `pass_good`

(Provided by Plaid API integration)

### Test Gift Card

Use Sandbox gift cards created in the Sandbox Seller Dashboard.

### Environment Switching in Code

```php
class BKX_Square_Gateway {
    private $client;

    public function __construct() {
        $is_sandbox = get_option( 'bkx_square_sandbox_mode', true );

        $access_token = $is_sandbox
            ? get_option( 'bkx_square_sandbox_access_token' )
            : get_option( 'bkx_square_production_access_token' );

        $environment = $is_sandbox
            ? Environment::SANDBOX
            : Environment::PRODUCTION;

        $this->client = new SquareClient([
            'accessToken' => $access_token,
            'environment' => $environment,
        ]);
    }

    public function get_web_sdk_url() {
        $is_sandbox = get_option( 'bkx_square_sandbox_mode', true );

        return $is_sandbox
            ? 'https://sandbox.web.squarecdn.com/v1/square.js'
            : 'https://web.squarecdn.com/v1/square.js';
    }
}
```

### Sandbox Limitations

- Cannot process real payments
- No fees charged
- Webhook events may behave slightly differently
- Some features may not be available in Sandbox

### Best Practices for Testing

1. **Always test in Sandbox first** before deploying to production
2. **Test all scenarios:**
   - Successful payments
   - Failed payments (declined cards)
   - Partial refunds
   - Full refunds
   - Card-on-file creation
   - Webhook signature verification
3. **Switch to Production:**
   - Update access tokens
   - Update Application ID
   - Update Location ID
   - Change environment in SDK client
   - Update Web Payments SDK URL

---

## Data Models

### Payment Object

```php
[
    'id'              => 'R2B3Z8WMVt3EAmzYWLZvz7Y69EbZY',  // string
    'created_at'      => '2024-12-27T10:30:00.000Z',       // ISO 8601
    'updated_at'      => '2024-12-27T10:30:00.000Z',       // ISO 8601
    'amount_money'    => [
        'amount'      => 1000,                              // integer (cents)
        'currency'    => 'USD',                             // string (ISO 4217)
    ],
    'tip_money'       => [
        'amount'      => 100,
        'currency'    => 'USD',
    ],
    'total_money'     => [
        'amount'      => 1100,
        'currency'    => 'USD',
    ],
    'approved_money'  => [                                 // Amount approved (may differ from total)
        'amount'      => 1100,
        'currency'    => 'USD',
    ],
    'status'          => 'COMPLETED',                      // enum: APPROVED, COMPLETED, CANCELED, FAILED
    'source_type'     => 'CARD',                           // enum: CARD, BANK_ACCOUNT, WALLET, etc.
    'card_details'    => [
        'status'          => 'CAPTURED',                   // enum: AUTHORIZED, CAPTURED, VOIDED, FAILED
        'card'            => [
            'card_brand'  => 'VISA',                       // enum: VISA, MASTERCARD, AMEX, etc.
            'last_4'      => '1111',                       // string
            'exp_month'   => 12,                           // integer
            'exp_year'    => 2025,                         // integer
            'fingerprint' => 'sq-1-...',                   // string (unique card identifier)
            'card_type'   => 'CREDIT',                     // enum: CREDIT, DEBIT
            'prepaid_type'=> 'NOT_PREPAID',               // enum: PREPAID, NOT_PREPAID, UNKNOWN
            'bin'         => '411111',                     // string (first 6 digits)
        ],
        'entry_method'    => 'KEYED',                      // enum: KEYED, SWIPED, EMV, etc.
        'cvv_status'      => 'CVV_ACCEPTED',              // enum: CVV_ACCEPTED, CVV_REJECTED, CVV_NOT_CHECKED
        'avs_status'      => 'AVS_ACCEPTED',              // enum: AVS_ACCEPTED, AVS_REJECTED, AVS_NOT_CHECKED
        'statement_description' => 'SQUARE *MERCHANT',     // string
        'card_payment_timeline' => [
            'authorized_at' => '2024-12-27T10:30:00.000Z',
            'captured_at'   => '2024-12-27T10:30:01.000Z',
        ],
    ],
    'location_id'     => 'L0Z7HXQNKRCYX',                 // string
    'customer_id'     => 'CUSTOMER_ID_123',               // string (optional)
    'reference_id'    => 'booking_123',                   // string (optional, external reference)
    'note'            => 'Booking payment',               // string (optional)
    'receipt_number'  => 'R2B3',                          // string
    'receipt_url'     => 'https://squareup.com/receipt/preview/...',  // string
    'delay_duration'  => 'PT30M',                         // string (ISO 8601 duration - delay before capture)
    'delay_action'    => 'CANCEL',                        // enum: CANCEL, COMPLETE
    'delayed_until'   => '2024-12-27T11:00:00.000Z',      // ISO 8601
    'version_token'   => 'abc123',                        // string (for optimistic concurrency)
    'order_id'        => 'ORDER_ID_123',                  // string (optional)
    'app_fee_money'   => [                                // Application fee collected
        'amount'      => 50,
        'currency'    => 'USD',
    ],
]
```

### Refund Object

```php
[
    'id'              => 'REFUND_ID_123',                 // string
    'location_id'     => 'L0Z7HXQNKRCYX',                 // string
    'payment_id'      => 'R2B3Z8WMVt3EAmzYWLZvz7Y69EbZY', // string
    'status'          => 'PENDING',                       // enum: PENDING, COMPLETED, REJECTED, FAILED
    'amount_money'    => [
        'amount'      => 1000,
        'currency'    => 'USD',
    ],
    'app_fee_money'   => [                                // Developer contribution to refund
        'amount'      => 50,
        'currency'    => 'USD',
    ],
    'processing_fee'  => [                                // Processing fee refunded
        [
            'amount_money' => [
                'amount'   => 30,
                'currency' => 'USD',
            ],
            'effective_at' => '2024-12-27T11:00:00.000Z',
            'type'         => 'INITIAL',
        ],
    ],
    'reason'          => 'Customer requested refund',     // string
    'created_at'      => '2024-12-27T11:00:00.000Z',      // ISO 8601
    'updated_at'      => '2024-12-27T11:00:00.000Z',      // ISO 8601
    'order_id'        => 'ORDER_ID_123',                  // string (optional)
    'team_member_id'  => 'TEAM_MEMBER_ID',                // string (optional)
    'unlinked'        => false,                           // boolean
]
```

### Card Object

```php
[
    'id'              => 'ccof:CARD_ID_123',              // string
    'card_brand'      => 'VISA',                          // enum: VISA, MASTERCARD, AMEX, DISCOVER, JCB, etc.
    'last_4'          => '1111',                          // string
    'exp_month'       => 12,                              // integer (1-12)
    'exp_year'        => 2025,                            // integer
    'cardholder_name' => 'John Doe',                      // string
    'billing_address' => [
        'address_line_1'                   => '123 Main St',
        'address_line_2'                   => 'Apt 4B',
        'locality'                         => 'San Francisco',  // City
        'administrative_district_level_1'  => 'CA',            // State
        'postal_code'                      => '94103',
        'country'                          => 'US',            // ISO 3166-1 alpha-2
    ],
    'fingerprint'     => 'sq-1-...',                      // string (unique card identifier)
    'customer_id'     => 'CUSTOMER_ID_123',               // string
    'merchant_id'     => 'MERCHANT_ID',                   // string
    'reference_id'    => 'user_card_1',                   // string (optional, external reference)
    'enabled'         => true,                            // boolean
    'card_type'       => 'CREDIT',                        // enum: CREDIT, DEBIT
    'prepaid_type'    => 'NOT_PREPAID',                   // enum: PREPAID, NOT_PREPAID, UNKNOWN
    'bin'             => '411111',                        // string (first 6 digits)
    'version'         => 1,                               // integer
]
```

### Customer Object

```php
[
    'id'              => 'CUSTOMER_ID_123',               // string
    'created_at'      => '2024-12-27T10:00:00.000Z',      // ISO 8601
    'updated_at'      => '2024-12-27T10:00:00.000Z',      // ISO 8601
    'given_name'      => 'John',                          // string
    'family_name'     => 'Doe',                           // string
    'email_address'   => 'john@example.com',              // string
    'phone_number'    => '+1-555-555-5555',               // string (E.164 format)
    'company_name'    => 'Acme Corp',                     // string (optional)
    'nickname'        => 'Johnny',                        // string (optional)
    'address'         => [
        'address_line_1'                   => '123 Main St',
        'locality'                         => 'San Francisco',
        'administrative_district_level_1'  => 'CA',
        'postal_code'                      => '94103',
        'country'                          => 'US',
    ],
    'birthday'        => '1990-01-15',                    // string (YYYY-MM-DD)
    'reference_id'    => 'user_123',                      // string (external reference)
    'note'            => 'Customer from BookingX',        // string
    'preferences'     => [
        'email_unsubscribed' => false,                    // boolean
    ],
    'creation_source' => 'THIRD_PARTY',                   // enum: APPOINTMENTS, COUPON, etc.
    'group_ids'       => ['GROUP_ID_1', 'GROUP_ID_2'],    // array of strings
    'segment_ids'     => ['SEGMENT_ID_1'],                // array of strings
    'version'         => 1,                               // integer
]
```

### Money Object

```php
[
    'amount'   => 1000,     // integer - Amount in smallest currency unit (e.g., cents)
    'currency' => 'USD',    // string - ISO 4217 currency code
]
```

**Currency Examples:**
- `USD` - US Dollars (cents)
- `EUR` - Euros (cents)
- `GBP` - British Pounds (pence)
- `JPY` - Japanese Yen (yen - no fractional unit)
- `CAD` - Canadian Dollars (cents)
- `AUD` - Australian Dollars (cents)

### Address Object

```php
[
    'address_line_1'                   => '123 Main St',         // string
    'address_line_2'                   => 'Apt 4B',              // string (optional)
    'address_line_3'                   => '',                    // string (optional)
    'locality'                         => 'San Francisco',       // string (city)
    'sublocality'                      => '',                    // string (neighborhood)
    'administrative_district_level_1'  => 'CA',                  // string (state/province)
    'administrative_district_level_2'  => '',                    // string (county)
    'administrative_district_level_3'  => '',                    // string
    'postal_code'                      => '94103',               // string
    'country'                          => 'US',                  // string (ISO 3166-1 alpha-2)
    'first_name'                       => 'John',                // string (optional)
    'last_name'                        => 'Doe',                 // string (optional)
]
```

---

## Rate Limiting

### Rate Limits

Square enforces rate limits to ensure API stability:

- **Default Limit:** 1000 requests per minute per application
- **Burst Limit:** Can exceed briefly, but sustained high rates will be throttled
- **Per-Endpoint Limits:** Some endpoints have stricter limits

### Rate Limit Response

When rate limited, API returns:
- **HTTP Status:** `429 Too Many Requests`
- **Error Category:** `RATE_LIMIT_ERROR`
- **Retry-After Header:** Seconds to wait before retrying

**Example Error:**
```json
{
    "errors": [
        {
            "category": "RATE_LIMIT_ERROR",
            "code": "RATE_LIMITED",
            "detail": "Rate limit exceeded. Retry after 60 seconds."
        }
    ]
}
```

### Handling Rate Limits

```php
function handle_square_request( callable $api_call ) {
    $max_retries = 3;
    $retry_count = 0;

    while ( $retry_count < $max_retries ) {
        try {
            $response = $api_call();

            if ( $response->isSuccess() ) {
                return $response->getResult();
            }

            $errors = $response->getErrors();
            foreach ( $errors as $error ) {
                if ( $error->getCategory() === 'RATE_LIMIT_ERROR' ) {
                    $retry_count++;

                    // Exponential backoff: 2^retry seconds
                    $wait_time = pow( 2, $retry_count );
                    sleep( $wait_time );

                    continue 2; // Continue outer while loop
                }
            }

            // Non-rate-limit error
            throw new Exception( $error->getDetail() );

        } catch ( ApiException $e ) {
            throw new Exception( 'API error: ' . $e->getMessage() );
        }
    }

    throw new Exception( 'Max retries exceeded due to rate limiting' );
}
```

### Best Practices

1. **Cache frequently accessed data** (location info, customer data)
2. **Batch operations** when possible
3. **Implement exponential backoff** for retries
4. **Monitor rate limit headers** (if available)
5. **Use webhooks** instead of polling for status updates

---

## BookingX Integration Notes

### Mapping to SDK

**Extends:** `AbstractPaymentGateway`
**Traits:** `HasWebhooks`, `HasSettings`, `HasDatabase`

### Class Structure

```php
<?php
namespace BookingX\Square;

use BookingX\SDK\Abstracts\AbstractPaymentGateway;
use BookingX\SDK\Traits\HasWebhooks;
use BookingX\SDK\Traits\HasSettings;
use BookingX\SDK\Services\EncryptionService;
use Square\SquareClient;
use Square\Environment;

class SquareGateway extends AbstractPaymentGateway {
    use HasWebhooks, HasSettings;

    private $client;
    private $encryption;

    public function get_id(): string {
        return 'square';
    }

    public function get_name(): string {
        return __( 'Square', 'bkx-square' );
    }

    public function get_description(): string {
        return __( 'Accept payments via Square', 'bkx-square' );
    }

    public function is_available(): bool {
        return $this->get_access_token() && $this->get_location_id();
    }

    protected function get_client(): SquareClient {
        if ( ! $this->client ) {
            $is_sandbox = $this->get_setting( 'sandbox_mode', true );
            $access_token = $this->get_access_token();

            $this->client = new SquareClient([
                'accessToken' => $access_token,
                'environment' => $is_sandbox ? Environment::SANDBOX : Environment::PRODUCTION,
            ]);
        }

        return $this->client;
    }

    private function get_access_token(): ?string {
        $is_sandbox = $this->get_setting( 'sandbox_mode', true );
        $key = $is_sandbox ? 'sandbox_access_token' : 'production_access_token';
        $encrypted = $this->get_setting( $key );

        if ( ! $encrypted ) {
            return null;
        }

        return EncryptionService::decrypt( $encrypted );
    }

    private function get_location_id(): ?string {
        $is_sandbox = $this->get_setting( 'sandbox_mode', true );
        return $is_sandbox
            ? $this->get_setting( 'sandbox_location_id' )
            : $this->get_setting( 'production_location_id' );
    }

    public function process_payment( array $data ): array {
        // Implementation
    }

    public function process_refund( string $transaction_id, float $amount ): array {
        // Implementation
    }

    public function get_payment_status( string $transaction_id ): string {
        // Implementation
    }

    public function handle_webhook(): void {
        // Implementation
    }
}
```

### Settings Fields

```php
protected function get_settings_fields(): array {
    return [
        'sandbox_mode' => [
            'title'       => __( 'Sandbox Mode', 'bkx-square' ),
            'type'        => 'checkbox',
            'description' => __( 'Enable to use Square Sandbox for testing', 'bkx-square' ),
            'default'     => true,
        ],
        'sandbox_application_id' => [
            'title'       => __( 'Sandbox Application ID', 'bkx-square' ),
            'type'        => 'text',
            'description' => __( 'Get from Developer Console > Credentials (Sandbox mode)', 'bkx-square' ),
        ],
        'sandbox_access_token' => [
            'title'       => __( 'Sandbox Access Token', 'bkx-square' ),
            'type'        => 'password',
            'description' => __( 'Get from Developer Console > Credentials (Sandbox mode)', 'bkx-square' ),
            'encrypt'     => true, // Auto-encrypt with EncryptionService
        ],
        'sandbox_location_id' => [
            'title'       => __( 'Sandbox Location ID', 'bkx-square' ),
            'type'        => 'text',
            'description' => __( 'Get from Developer Console > Locations (Sandbox mode)', 'bkx-square' ),
        ],
        'production_application_id' => [
            'title'       => __( 'Production Application ID', 'bkx-square' ),
            'type'        => 'text',
            'description' => __( 'Get from Developer Console > Credentials (Production mode)', 'bkx-square' ),
        ],
        'production_access_token' => [
            'title'       => __( 'Production Access Token', 'bkx-square' ),
            'type'        => 'password',
            'description' => __( 'Get from Developer Console > Credentials (Production mode)', 'bkx-square' ),
            'encrypt'     => true,
        ],
        'production_location_id' => [
            'title'       => __( 'Production Location ID', 'bkx-square' ),
            'type'        => 'text',
            'description' => __( 'Get from Developer Console > Locations (Production mode)', 'bkx-square' ),
        ],
        'webhook_signature_key' => [
            'title'       => __( 'Webhook Signature Key', 'bkx-square' ),
            'type'        => 'password',
            'description' => __( 'Get from Developer Console > Webhooks', 'bkx-square' ),
            'encrypt'     => true,
        ],
        'save_cards' => [
            'title'       => __( 'Save Cards on File', 'bkx-square' ),
            'type'        => 'checkbox',
            'description' => __( 'Allow customers to save cards for future bookings', 'bkx-square' ),
            'default'     => false,
        ],
        'statement_descriptor' => [
            'title'       => __( 'Statement Descriptor', 'bkx-square' ),
            'type'        => 'text',
            'description' => __( 'Text on customer credit card statement (optional)', 'bkx-square' ),
            'maxlength'   => 22,
        ],
    ];
}
```

### Database Tables

**Payment Tokens Table:**
```sql
CREATE TABLE {$wpdb->prefix}bkx_square_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    square_customer_id VARCHAR(255) NOT NULL,
    square_card_id VARCHAR(255) NOT NULL,
    card_brand VARCHAR(50),
    last_4 VARCHAR(4),
    exp_month TINYINT,
    exp_year SMALLINT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id (booking_id),
    INDEX customer_id (customer_id),
    INDEX square_customer_id (square_customer_id)
);
```

**Transaction Log Table:**
```sql
CREATE TABLE {$wpdb->prefix}bkx_square_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    square_payment_id VARCHAR(255) NOT NULL,
    square_refund_id VARCHAR(255),
    transaction_type ENUM('payment', 'refund') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    status VARCHAR(50) NOT NULL,
    receipt_url VARCHAR(500),
    error_code VARCHAR(100),
    error_message TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id (booking_id),
    INDEX square_payment_id (square_payment_id),
    INDEX square_refund_id (square_refund_id),
    INDEX status (status)
);
```

### Frontend Payment Form

```php
public function render_payment_form( $booking_id ) {
    $is_sandbox = $this->get_setting( 'sandbox_mode', true );
    $application_id = $is_sandbox
        ? $this->get_setting( 'sandbox_application_id' )
        : $this->get_setting( 'production_application_id' );
    $location_id = $this->get_location_id();
    $sdk_url = $this->get_web_sdk_url();

    ?>
    <script src="<?php echo esc_url( $sdk_url ); ?>"></script>
    <script>
    const appId = '<?php echo esc_js( $application_id ); ?>';
    const locationId = '<?php echo esc_js( $location_id ); ?>';

    async function initializeSquare() {
        const payments = Square.payments(appId, locationId);
        const card = await payments.card();
        await card.attach('#square-card-container');

        document.getElementById('square-payment-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const result = await card.tokenize({
                verificationDetails: {
                    amount: '<?php echo esc_js( $booking_amount ); ?>',
                    currencyCode: '<?php echo esc_js( $currency ); ?>',
                    intent: 'CHARGE',
                    billingContact: {
                        givenName: document.getElementById('billing_first_name').value,
                        familyName: document.getElementById('billing_last_name').value,
                        email: document.getElementById('billing_email').value,
                        phone: document.getElementById('billing_phone').value,
                        addressLines: [document.getElementById('billing_address').value],
                        city: document.getElementById('billing_city').value,
                        state: document.getElementById('billing_state').value,
                        postalCode: document.getElementById('billing_zip').value,
                        countryCode: 'US'
                    }
                }
            });

            if (result.status === 'OK') {
                document.getElementById('square_token').value = result.token;
                document.getElementById('square-payment-form').submit();
            } else {
                console.error(result.errors);
                alert('Payment tokenization failed. Please try again.');
            }
        });
    }

    initializeSquare();
    </script>

    <form id="square-payment-form" method="post">
        <div id="square-card-container"></div>
        <input type="hidden" name="square_token" id="square_token">
        <input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">
        <?php wp_nonce_field( 'bkx_square_payment', 'bkx_square_nonce' ); ?>
        <button type="submit"><?php esc_html_e( 'Pay Now', 'bkx-square' ); ?></button>
    </form>
    <?php
}
```

### Webhook Handler

```php
public function handle_webhook(): void {
    $payload = file_get_contents( 'php://input' );
    $signature = $_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] ?? '';

    // Verify signature
    if ( ! $this->verify_webhook_signature( $payload, $signature ) ) {
        http_response_code( 401 );
        wp_die( 'Invalid signature', 'Webhook Error', array( 'response' => 401 ) );
    }

    $event = json_decode( $payload, true );
    $event_type = $event['type'] ?? '';

    // Log webhook
    $this->log_webhook( $event_type, $event );

    // Process event
    switch ( $event_type ) {
        case 'payment.updated':
            $this->handle_payment_updated( $event );
            break;
        case 'refund.updated':
            $this->handle_refund_updated( $event );
            break;
    }

    // Return 200 immediately
    http_response_code( 200 );
    echo 'OK';
    exit;
}

private function verify_webhook_signature( string $payload, string $signature ): bool {
    $signature_key = $this->get_setting( 'webhook_signature_key' );

    if ( ! $signature_key ) {
        return false;
    }

    $signature_key = EncryptionService::decrypt( $signature_key );
    $notification_url = rest_url( 'bookingx/v1/square/webhook' );

    $body = $notification_url . $payload;
    $expected = hash_hmac( 'sha256', $body, $signature_key, false );

    return hash_equals( $expected, $signature );
}
```

---

## Resources

### Official Documentation
- [Square Developer Portal](https://developer.squareup.com/)
- [Square API Reference](https://developer.squareup.com/reference/square)
- [Payments API Guide](https://developer.squareup.com/docs/payments-api/overview)
- [Web Payments SDK Guide](https://developer.squareup.com/docs/web-payments/overview)
- [Webhooks Guide](https://developer.squareup.com/docs/webhooks/overview)

### PHP SDK
- [GitHub - square/square-php-sdk](https://github.com/square/square-php-sdk)
- [Packagist - square/square](https://packagist.org/packages/square/square)
- [PHP SDK Documentation](https://developer.squareup.com/docs/sdks/php)

### Testing & Tools
- [Sandbox Overview](https://developer.squareup.com/docs/devtools/sandbox/overview)
- [Sandbox Test Values](https://developer.squareup.com/docs/devtools/sandbox/testing)
- [API Explorer](https://developer.squareup.com/explorer/square)
- [Webhook Testing](https://developer.squareup.com/docs/webhooks/step3validate)

### Developer Console
- [Developer Dashboard](https://developer.squareup.com/apps)
- [Application Credentials](https://developer.squareup.com/apps) (Navigate to your app > Credentials)
- [Locations](https://developer.squareup.com/apps) (Navigate to your app > Locations)
- [Webhooks](https://developer.squareup.com/apps) (Navigate to your app > Webhooks)

### Community
- [Square Developer Forums](https://developer.squareup.com/forums)
- [Stack Overflow - square-connect](https://stackoverflow.com/questions/tagged/square-connect)

---

## Version History

| Date | Version | Notes |
|------|---------|-------|
| 2024-12-27 | 1.0 | Initial API reference document |

---

## Notes

- **SDK Breaking Change:** Version 41.0.0.20250220 is a complete rewrite with breaking changes
- **SCA Requirement:** As of October 1, 2025, must use `verificationDetails` in `card.tokenize()`
- **Deprecated:** `CreateCustomerCard` endpoint - use `CreateCard` in Cards API instead
- **Deprecated:** `verifyBuyer()` method - use `card.tokenize()` with `verificationDetails` instead
- **Gift Card Refunds:** Cross-method refunds require API version 2024-08-21 or later
- **Rate Limits:** 1000 requests per minute (default)
- **Webhook Retries:** 24 hours with exponential backoff
- **Token Expiration:** OAuth access tokens expire after 30 days
- **Idempotency:** All mutation endpoints support idempotency keys (24-hour lifetime)
