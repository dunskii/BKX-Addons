# QuickBooks Integration - Development Documentation

## 1. Overview

**Add-on Name:** QuickBooks Integration
**Price:** $119
**Category:** Accounting & Financial Systems

### Description
Complete QuickBooks integration with automated sales entry creation, invoice generation, and comprehensive financial reporting.

---

## 2. Key Features

- **QuickBooks Online Integration**
- **Automated Invoice Creation**
- **Sales Receipt Generation**
- **Customer Synchronization**
- **Payment Tracking**
- **Tax Calculation**
- **Item/Service Mapping**
- **Multi-Currency Support**
- **Refund Processing (Credit Memos)**

---

## 3. Technical Specifications

### API Integration
- **API:** QuickBooks Online API v3
- **Auth:** OAuth 2.0
- **SDK:** quickbooks/v3-php-sdk
- **Scopes:** com.intuit.quickbooks.accounting

### Database Schema
```sql
CREATE TABLE bkx_quickbooks_connections (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    realm_id VARCHAR(50) NOT NULL,
    company_name VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    connected_at DATETIME
);

CREATE TABLE bkx_quickbooks_invoices (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    qb_invoice_id VARCHAR(50),
    doc_number VARCHAR(50),
    customer_ref VARCHAR(50),
    total_amount DECIMAL(10,2),
    status VARCHAR(50),
    synced_at DATETIME
);
```

---

## 4. Configuration

```php
[
    'qb_client_id' => '',
    'qb_client_secret' => '',
    'qb_environment' => 'production|sandbox',
    'auto_create_invoices' => true,
    'sync_customers' => true,
    'default_income_account' => 'Sales',
    'payment_method' => 'Cash',
]
```

---

## 5. Invoice Creation

```php
public function createQBInvoice($booking) {
    $customer = $this->getOrCreateCustomer($booking->customer);

    $invoice = Invoice::create([
        'CustomerRef' => $customer->Id,
        'DocNumber' => 'BKX-' . $booking->id,
        'TxnDate' => $booking->booking_date,
        'Line' => [
            [
                'Amount' => $booking->total_amount,
                'DetailType' => 'SalesItemLineDetail',
                'SalesItemLineDetail' => [
                    'ItemRef' => $this->getServiceItem($booking->service_id),
                    'Qty' => 1,
                    'UnitPrice' => $booking->total_amount,
                ],
            ]
        ],
    ]);

    $this->dataService->Add($invoice);
}
```

---

## 6. Development Timeline
**Total:** 6 weeks

---

**Status:** Ready for Development
