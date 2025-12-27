# Xero Integration - Development Documentation

## 1. Overview

**Add-on Name:** Xero Integration
**Price:** $99
**Category:** Accounting & Financial Systems

### Description
Comprehensive Xero accounting integration automatically adding prepaid bookings as sales entries with full API connectivity and real-time synchronization.

---

## 2. Key Features

- **Automatic Sales Entry:** Prepaid bookings → Xero invoices
- **Customer Sync:** Two-way customer/contact synchronization
- **Payment Reconciliation:** Match payments to invoices
- **Chart of Accounts:** Map services to account codes
- **Tax Handling:** Automatic tax calculation per region
- **Multi-Currency:** Support for Xero multi-currency
- **Real-Time Sync:** Immediate invoice creation
- **Reports:** Revenue reports, tax reports
- **Refund Handling:** Credit notes for refunds

---

## 3. Technical Specifications

### API Integration
- **API:** Xero Accounting API v2
- **Auth:** OAuth 2.0 (PKCE flow)
- **SDK:** xero/xero-php library
- **Scopes:** accounting.transactions, accounting.contacts, accounting.settings

### Database Schema
```sql
CREATE TABLE bkx_xero_connections (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(255) NOT NULL,
    tenant_name VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    connected_at DATETIME,
    last_sync_at DATETIME
);

CREATE TABLE bkx_xero_invoices (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    xero_invoice_id VARCHAR(255) NOT NULL,
    invoice_number VARCHAR(50),
    contact_id VARCHAR(255),
    amount DECIMAL(10,2),
    status VARCHAR(50),
    synced_at DATETIME
);

CREATE TABLE bkx_xero_account_mappings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT(20) UNSIGNED,
    xero_account_code VARCHAR(20),
    xero_account_name VARCHAR(255),
    tax_type VARCHAR(50)
);
```

---

## 4. Configuration

```php
[
    'xero_client_id' => '',
    'xero_client_secret' => '',
    'auto_sync_invoices' => true,
    'sync_contacts' => true,
    'invoice_prefix' => 'BKX-',
    'default_account_code' => '200',
    'default_tax_type' => 'OUTPUT2', // 20% VAT
    'sync_status' => ['paid', 'confirmed'],
    'create_draft_invoices' => false,
]
```

---

## 5. Class Structure

```php
namespace BookingX\Addons\Xero;

class XeroIntegration {
    - connectToXero()
    - refreshToken()
    - getTenants()
}

class XeroInvoiceSync {
    - createInvoice()
    - updateInvoice()
    - markAsPaid()
    - createCreditNote()
}

class XeroContactSync {
    - syncContact()
    - findContact()
    - createContact()
}

class XeroAccountMapping {
    - mapServiceToAccount()
    - getTaxType()
}
```

---

## 6. Invoice Creation Flow

```php
public function createInvoiceForBooking($booking) {
    $contact = $this->getOrCreateContact($booking->customer);

    $invoice = [
        'Type' => 'ACCREC',
        'Contact' => ['ContactID' => $contact->ContactID],
        'Date' => $booking->booking_date,
        'DueDate' => $booking->booking_date,
        'InvoiceNumber' => $this->generateInvoiceNumber($booking),
        'Reference' => 'Booking #' . $booking->id,
        'Status' => $booking->payment_status === 'paid' ? 'PAID' : 'DRAFT',
        'LineItems' => [
            [
                'Description' => $booking->service_name,
                'Quantity' => 1,
                'UnitAmount' => $booking->total_amount,
                'AccountCode' => $this->getAccountCode($booking->service_id),
                'TaxType' => $this->getTaxType($booking->service_id),
            ]
        ],
    ];

    $response = $this->xero_api->createInvoice($invoice);
    $this->storeInvoiceMapping($booking->id, $response->InvoiceID);

    if ($booking->payment_status === 'paid') {
        $this->recordPayment($response->InvoiceID, $booking);
    }
}
```

---

## 7. Payment Reconciliation

```php
public function recordPayment($invoice_id, $booking) {
    $payment = [
        'Invoice' => ['InvoiceID' => $invoice_id],
        'Account' => ['Code' => $this->getPaymentAccountCode()],
        'Date' => $booking->paid_at,
        'Amount' => $booking->total_amount,
    ];

    $this->xero_api->createPayment($payment);
}
```

---

## 8. Development Timeline

- **Week 1-2:** OAuth setup & API integration
- **Week 3:** Invoice sync implementation
- **Week 4:** Contact sync & account mapping
- **Week 5:** Testing & launch

**Total:** 5 weeks

---

**Status:** Ready for Development
