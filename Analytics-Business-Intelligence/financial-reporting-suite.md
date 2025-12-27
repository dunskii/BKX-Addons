# Financial Reporting Suite - Development Documentation

## 1. Overview

**Add-on Name:** Financial Reporting Suite
**Price:** $99
**Category:** Analytics & Business Intelligence
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Comprehensive financial reporting and analysis toolkit with tax preparation support, revenue recognition, profit & loss statements, cash flow analysis, and financial forecasting. Generate IRS-compliant reports, track expenses, manage accounts receivable/payable, and monitor financial health with executive-level financial dashboards.

### Value Proposition
- Automated tax preparation and reporting
- GAAP-compliant revenue recognition
- Comprehensive P&L statements
- Cash flow analysis and forecasting
- Multi-currency financial reporting
- Expense tracking and categorization
- Accounts receivable/payable management
- Financial ratios and KPIs
- Audit trail and compliance reporting
- Integration with accounting software

---

## 2. Features & Requirements

### Core Features
1. **Tax Preparation & Reporting**
   - Quarterly tax reports (1099, sales tax)
   - Annual tax summaries
   - Revenue by tax jurisdiction
   - Deductible expense tracking
   - Tax liability calculations
   - IRS-compliant documentation
   - Export for accountants (CSV, PDF)
   - Multi-state/country tax support

2. **Revenue Recognition**
   - GAAP/IFRS compliance
   - Accrual-based accounting
   - Cash-based accounting
   - Deferred revenue tracking
   - Revenue by period
   - Contract revenue scheduling
   - Prepayment handling
   - Revenue waterfall reports

3. **Profit & Loss Analysis**
   - Income statements
   - Cost of goods sold (COGS)
   - Gross profit margins
   - Operating expenses
   - Net profit calculations
   - EBITDA tracking
   - Period comparisons
   - Budget vs actual analysis

4. **Cash Flow Management**
   - Cash flow statements
   - Operating cash flow
   - Investing cash flow
   - Financing cash flow
   - Cash position forecasting
   - Burn rate analysis
   - Runway calculations
   - Working capital analysis

5. **Financial Statements**
   - Balance sheets
   - Income statements
   - Cash flow statements
   - Statement of changes in equity
   - Notes to financial statements
   - Consolidated statements
   - Comparative statements

6. **Expense Management**
   - Expense categorization
   - Cost center allocation
   - Vendor expense tracking
   - Recurring expense management
   - Expense approval workflows
   - Receipt attachment
   - Budget tracking

7. **Accounts Receivable/Payable**
   - AR aging reports
   - AP aging reports
   - Payment tracking
   - Collections management
   - Vendor payment schedules
   - Bad debt provisions
   - DSO (Days Sales Outstanding)

### User Roles & Permissions
- **CFO/Financial Manager:** Full access, all reports
- **Accountant:** View reports, export data
- **Admin:** Configuration, report generation
- **Manager:** Limited financial views
- **Auditor:** Read-only access, audit logs

---

## 3. Technical Specifications

### Technology Stack
- **Accounting Engine:** Double-entry bookkeeping system
- **Reporting:** Chart.js, Apache ECharts, jsPDF
- **Export:** PHPSpreadsheet, TCPDF
- **Currency:** Multi-currency support with exchange rates
- **Tax Calculation:** Custom tax engine
- **Data Format:** JSON, CSV, Excel, PDF
- **API Integration:** QuickBooks, Xero, FreshBooks

### Dependencies
- BookingX Core 2.0+
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- PHP Extensions: mysqli, gd, zip, mbstring
- PHPSpreadsheet library
- TCPDF library

### API Integration Points
```php
// Custom REST API endpoints
- GET  /bookingx/v1/financial/tax-report
- GET  /bookingx/v1/financial/revenue-recognition
- GET  /bookingx/v1/financial/profit-loss
- GET  /bookingx/v1/financial/cash-flow
- GET  /bookingx/v1/financial/balance-sheet
- GET  /bookingx/v1/financial/ar-aging
- GET  /bookingx/v1/financial/ap-aging
- POST /bookingx/v1/financial/expense
- GET  /bookingx/v1/financial/export-report
- GET  /bookingx/v1/financial/audit-trail
```

---

## 4. Architecture & Design

### System Architecture
```
┌────────────────────────────────────────────┐
│     Financial Reporting Dashboard          │
│  (React + Charts + Export)                 │
└────────────┬───────────────────────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│      Financial Reporting Engine            │
│  - Tax Calculator                          │
│  - Revenue Recognizer                      │
│  - P&L Generator                           │
│  - Cash Flow Analyzer                      │
└────────────┬───────────────────────────────┘
             │
             ├──────────┬──────────┬──────────┐
             ▼          ▼          ▼          ▼
┌──────────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐
│     Tax      │ │ Revenue  │ │   P&L    │ │  Cash Flow │
│  Calculator  │ │Recognizer│ │Generator │ │  Analyzer  │
└──────────────┘ └──────────┘ └──────────┘ └────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│       Financial Data Warehouse             │
│  - General Ledger                          │
│  - Journal Entries                         │
│  - Chart of Accounts                       │
│  - Financial Transactions                  │
└────────────────────────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\FinancialReporting;

class FinancialReportingManager {
    - init()
    - register_endpoints()
    - generate_report($type, $parameters)
    - export_report($report, $format)
}

class TaxCalculator {
    - calculate_sales_tax($period)
    - calculate_income_tax($period)
    - generate_1099_report()
    - get_tax_by_jurisdiction()
    - calculate_tax_liability()
    - generate_quarterly_report()
}

class RevenueRecognizer {
    - recognize_revenue($transaction)
    - calculate_deferred_revenue()
    - get_revenue_schedule()
    - apply_revenue_rules()
    - calculate_accrued_revenue()
    - generate_revenue_report($period)
}

class ProfitLossGenerator {
    - generate_income_statement($period)
    - calculate_gross_profit()
    - calculate_operating_income()
    - calculate_net_income()
    - calculate_ebitda()
    - compare_periods($period1, $period2)
}

class CashFlowAnalyzer {
    - generate_cash_flow_statement($period)
    - calculate_operating_cash_flow()
    - calculate_free_cash_flow()
    - forecast_cash_position($days)
    - calculate_burn_rate()
    - calculate_runway()
}

class GeneralLedger {
    - post_entry($entry)
    - get_account_balance($account_id)
    - get_trial_balance($date)
    - reconcile_account($account_id)
    - close_period($period)
}

class AccountsReceivable {
    - create_invoice($booking_id)
    - record_payment($invoice_id)
    - calculate_ar_aging()
    - calculate_dso()
    - provision_bad_debt()
}

class AccountsPayable {
    - create_bill($expense)
    - record_payment($bill_id)
    - calculate_ap_aging()
    - get_payment_schedule()
    - calculate_dpo()
}

class ExpenseManager {
    - record_expense($expense_data)
    - categorize_expense($expense_id)
    - allocate_to_cost_center($expense_id)
    - track_budget_variance()
    - generate_expense_report($period)
}
```

---

## 5. Database Schema

### Table: `bkx_financial_accounts`
```sql
CREATE TABLE bkx_financial_accounts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(20) NOT NULL UNIQUE,
    account_name VARCHAR(255) NOT NULL,
    account_type VARCHAR(50) NOT NULL,
    account_category VARCHAR(50),
    parent_account_id BIGINT(20) UNSIGNED,
    currency VARCHAR(3) DEFAULT 'USD',
    opening_balance DECIMAL(15,2) DEFAULT 0.00,
    current_balance DECIMAL(15,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    tax_line_mapping VARCHAR(100),
    description TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX account_code_idx (account_code),
    INDEX account_type_idx (account_type),
    INDEX parent_account_idx (parent_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_financial_journal_entries`
```sql
CREATE TABLE bkx_financial_journal_entries (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_number VARCHAR(50) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    entry_type VARCHAR(50) NOT NULL,
    reference_type VARCHAR(50),
    reference_id BIGINT(20) UNSIGNED,
    description TEXT,
    total_debit DECIMAL(15,2) NOT NULL,
    total_credit DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    exchange_rate DECIMAL(10,6) DEFAULT 1.000000,
    posted TINYINT(1) DEFAULT 0,
    posted_at DATETIME,
    reversed TINYINT(1) DEFAULT 0,
    reversal_entry_id BIGINT(20) UNSIGNED,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX entry_number_idx (entry_number),
    INDEX entry_date_idx (entry_date),
    INDEX reference_idx (reference_type, reference_id),
    INDEX posted_idx (posted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_financial_journal_lines`
```sql
CREATE TABLE bkx_financial_journal_lines (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id BIGINT(20) UNSIGNED NOT NULL,
    line_number INT NOT NULL,
    account_id BIGINT(20) UNSIGNED NOT NULL,
    debit_amount DECIMAL(15,2) DEFAULT 0.00,
    credit_amount DECIMAL(15,2) DEFAULT 0.00,
    description TEXT,
    cost_center VARCHAR(100),
    department VARCHAR(100),
    project_code VARCHAR(50),
    created_at DATETIME NOT NULL,
    INDEX journal_entry_idx (journal_entry_id),
    INDEX account_idx (account_id),
    FOREIGN KEY (journal_entry_id) REFERENCES bkx_financial_journal_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_financial_revenue_schedule`
```sql
CREATE TABLE bkx_financial_revenue_schedule (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    total_revenue DECIMAL(12,2) NOT NULL,
    recognized_revenue DECIMAL(12,2) DEFAULT 0.00,
    deferred_revenue DECIMAL(12,2) DEFAULT 0.00,
    recognition_start_date DATE NOT NULL,
    recognition_end_date DATE NOT NULL,
    recognition_method VARCHAR(50) NOT NULL,
    recognition_schedule LONGTEXT,
    fully_recognized TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX recognition_date_idx (recognition_start_date, recognition_end_date),
    INDEX fully_recognized_idx (fully_recognized)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_financial_expenses`
```sql
CREATE TABLE bkx_financial_expenses (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_number VARCHAR(50) NOT NULL UNIQUE,
    expense_date DATE NOT NULL,
    vendor_id BIGINT(20) UNSIGNED,
    vendor_name VARCHAR(255),
    expense_category VARCHAR(100) NOT NULL,
    expense_type VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(50),
    payment_status VARCHAR(20) DEFAULT 'pending',
    cost_center VARCHAR(100),
    department VARCHAR(100),
    project_code VARCHAR(50),
    is_recurring TINYINT(1) DEFAULT 0,
    recurrence_pattern VARCHAR(50),
    description TEXT,
    receipt_url VARCHAR(500),
    approved_by BIGINT(20) UNSIGNED,
    approved_at DATETIME,
    paid_at DATETIME,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX expense_number_idx (expense_number),
    INDEX expense_date_idx (expense_date),
    INDEX vendor_idx (vendor_id),
    INDEX expense_category_idx (expense_category),
    INDEX payment_status_idx (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_financial_invoices`
```sql
CREATE TABLE bkx_financial_invoices (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL,
    amount_paid DECIMAL(12,2) DEFAULT 0.00,
    amount_due DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status VARCHAR(20) DEFAULT 'draft',
    payment_terms VARCHAR(50),
    notes TEXT,
    sent_at DATETIME,
    paid_at DATETIME,
    voided TINYINT(1) DEFAULT 0,
    voided_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX invoice_number_idx (invoice_number),
    INDEX booking_id_idx (booking_id),
    INDEX customer_id_idx (customer_id),
    INDEX invoice_date_idx (invoice_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_financial_payments`
```sql
CREATE TABLE bkx_financial_payments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(50) NOT NULL UNIQUE,
    invoice_id BIGINT(20) UNSIGNED,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    exchange_rate DECIMAL(10,6) DEFAULT 1.000000,
    reference_number VARCHAR(100),
    transaction_id VARCHAR(255),
    payment_status VARCHAR(20) DEFAULT 'completed',
    notes TEXT,
    created_at DATETIME NOT NULL,
    INDEX payment_number_idx (payment_number),
    INDEX invoice_id_idx (invoice_id),
    INDEX customer_id_idx (customer_id),
    INDEX payment_date_idx (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_financial_tax_config`
```sql
CREATE TABLE bkx_financial_tax_config (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jurisdiction VARCHAR(100) NOT NULL,
    tax_type VARCHAR(50) NOT NULL,
    tax_rate DECIMAL(5,4) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE,
    tax_account_id BIGINT(20) UNSIGNED,
    applies_to VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY jurisdiction_tax_idx (jurisdiction, tax_type, effective_from),
    INDEX tax_type_idx (tax_type),
    INDEX is_active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_financial_periods`
```sql
CREATE TABLE bkx_financial_periods (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_name VARCHAR(50) NOT NULL UNIQUE,
    period_type VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    fiscal_year INT NOT NULL,
    fiscal_quarter INT,
    status VARCHAR(20) DEFAULT 'open',
    closed_at DATETIME,
    closed_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    INDEX period_type_idx (period_type),
    INDEX fiscal_year_idx (fiscal_year),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    // Company information
    'company_name' => '',
    'tax_id_number' => '',
    'fiscal_year_start' => '01-01',
    'accounting_method' => 'accrual', // accrual or cash
    'base_currency' => 'USD',
    'decimal_places' => 2,

    // Revenue recognition
    'revenue_recognition_method' => 'service_date', // service_date, payment_date, custom
    'enable_deferred_revenue' => true,
    'deferred_revenue_account' => null,

    // Tax settings
    'enable_sales_tax' => true,
    'default_tax_rate' => 0.0,
    'tax_inclusive_pricing' => false,
    'tax_jurisdictions' => [],

    // Chart of accounts
    'revenue_account' => null,
    'ar_account' => null,
    'ap_account' => null,
    'cash_account' => null,
    'expense_account' => null,

    // Financial periods
    'enable_period_closing' => true,
    'auto_close_periods' => false,
    'period_close_day' => 5, // Days after period end

    // Reporting
    'default_report_currency' => 'USD',
    'enable_multi_currency_reports' => true,
    'consolidate_subsidiaries' => false,

    // Accounts receivable
    'default_payment_terms' => 'net_30',
    'enable_late_fees' => false,
    'late_fee_percentage' => 1.5,
    'bad_debt_threshold_days' => 90,

    // Accounts payable
    'default_vendor_payment_terms' => 'net_30',
    'enable_payment_approval' => true,
    'approval_threshold_amount' => 1000.00,

    // Audit & compliance
    'enable_audit_trail' => true,
    'require_journal_approval' => false,
    'restrict_historical_edits' => true,
    'historical_edit_days' => 30,

    // Export settings
    'default_export_format' => 'pdf',
    'enable_accountant_export' => true,
    'include_transaction_details' => true,
]
```

---

## 7. Tax Calculation & Reporting

### Tax Calculator
```php
class TaxCalculator {

    public function calculate_quarterly_taxes($year, $quarter) {
        $period = $this->get_quarter_period($year, $quarter);

        return [
            'sales_tax' => $this->calculate_sales_tax($period),
            'income_tax' => $this->calculate_income_tax($period),
            'payroll_tax' => $this->calculate_payroll_tax($period),
            'total_tax_liability' => 0 // Sum of above
        ];
    }

    private function calculate_sales_tax($period) {
        global $wpdb;

        // Get all completed bookings in period
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT
                b.id,
                b.total_amount,
                b.tax_amount,
                c.billing_country,
                c.billing_state,
                c.billing_city
            FROM {$wpdb->prefix}bookingx_bookings b
            JOIN {$wpdb->prefix}bookingx_customers c ON b.customer_id = c.id
            WHERE b.status = 'completed'
            AND b.created_at BETWEEN %s AND %s
        ", $period['start'], $period['end']));

        $tax_by_jurisdiction = [];

        foreach ($bookings as $booking) {
            $jurisdiction = $this->determine_tax_jurisdiction($booking);
            $tax_rate = $this->get_tax_rate($jurisdiction, $booking->created_at);

            if (!isset($tax_by_jurisdiction[$jurisdiction])) {
                $tax_by_jurisdiction[$jurisdiction] = [
                    'taxable_amount' => 0,
                    'tax_collected' => 0,
                    'tax_rate' => $tax_rate
                ];
            }

            $tax_by_jurisdiction[$jurisdiction]['taxable_amount'] += $booking->total_amount;
            $tax_by_jurisdiction[$jurisdiction]['tax_collected'] += $booking->tax_amount;
        }

        return [
            'period' => $period,
            'by_jurisdiction' => $tax_by_jurisdiction,
            'total_taxable' => array_sum(array_column($tax_by_jurisdiction, 'taxable_amount')),
            'total_collected' => array_sum(array_column($tax_by_jurisdiction, 'tax_collected'))
        ];
    }

    public function generate_1099_report($year) {
        global $wpdb;

        // Get all vendors with payments >= $600 (1099 threshold)
        $vendors = $wpdb->get_results($wpdb->prepare("
            SELECT
                vendor_id,
                vendor_name,
                SUM(total_amount) as total_paid
            FROM {$wpdb->prefix}bkx_financial_expenses
            WHERE YEAR(expense_date) = %d
            AND payment_status = 'paid'
            AND expense_type IN ('contractor_payment', 'service_fee')
            GROUP BY vendor_id
            HAVING total_paid >= 600
        ", $year));

        $report_1099 = [];

        foreach ($vendors as $vendor) {
            // Get vendor details
            $vendor_details = $this->get_vendor_details($vendor->vendor_id);

            $report_1099[] = [
                'vendor_id' => $vendor->vendor_id,
                'vendor_name' => $vendor->vendor_name,
                'vendor_tin' => $vendor_details->tax_id,
                'vendor_address' => $vendor_details->address,
                'total_paid' => $vendor->total_paid,
                'form_type' => '1099-NEC' // Non-employee compensation
            ];
        }

        return [
            'year' => $year,
            'vendors' => $report_1099,
            'total_vendors' => count($report_1099),
            'total_amount' => array_sum(array_column($report_1099, 'total_paid'))
        ];
    }

    private function determine_tax_jurisdiction($booking) {
        // Simplified jurisdiction determination
        $country = $booking->billing_country ?? 'US';
        $state = $booking->billing_state ?? '';

        return $country . ($state ? '-' . $state : '');
    }

    private function get_tax_rate($jurisdiction, $date) {
        global $wpdb;

        $rate = $wpdb->get_var($wpdb->prepare("
            SELECT tax_rate
            FROM {$wpdb->prefix}bkx_financial_tax_config
            WHERE jurisdiction = %s
            AND tax_type = 'sales_tax'
            AND effective_from <= %s
            AND (effective_to IS NULL OR effective_to >= %s)
            AND is_active = 1
            ORDER BY effective_from DESC
            LIMIT 1
        ", $jurisdiction, $date, $date));

        return $rate ?? 0.0;
    }
}
```

---

## 8. Revenue Recognition

### Revenue Recognizer
```php
class RevenueRecognizer {

    public function recognize_booking_revenue($booking_id) {
        global $wpdb;

        $booking = $this->get_booking($booking_id);

        if (!$booking) {
            return false;
        }

        // Determine recognition method
        $method = $this->get_recognition_method($booking);

        switch ($method) {
            case 'immediate':
                return $this->recognize_immediate($booking);

            case 'service_date':
                return $this->recognize_on_service_date($booking);

            case 'deferred':
                return $this->create_deferred_schedule($booking);

            case 'milestone':
                return $this->recognize_by_milestone($booking);

            default:
                return $this->recognize_immediate($booking);
        }
    }

    private function recognize_immediate($booking) {
        // Create journal entry for immediate recognition
        $entry = [
            'entry_date' => $booking->created_at,
            'entry_type' => 'revenue_recognition',
            'description' => 'Revenue recognition for booking #' . $booking->id,
            'reference_type' => 'booking',
            'reference_id' => $booking->id,
            'lines' => [
                [
                    'account_code' => 'AR-001', // Accounts Receivable
                    'debit_amount' => $booking->total_amount,
                    'description' => 'Customer payment due'
                ],
                [
                    'account_code' => 'REV-001', // Revenue
                    'credit_amount' => $booking->total_amount,
                    'description' => 'Service revenue'
                ]
            ]
        ];

        return $this->post_journal_entry($entry);
    }

    private function recognize_on_service_date($booking) {
        $service_date = $booking->booking_date;

        // If service date is in future, defer revenue
        if (strtotime($service_date) > time()) {
            return $this->create_deferred_schedule($booking);
        }

        // Service date has passed, recognize immediately
        return $this->recognize_immediate($booking);
    }

    private function create_deferred_schedule($booking) {
        global $wpdb;

        $service_date = $booking->booking_date;
        $duration_days = $booking->duration_days ?? 1;

        // Create revenue schedule
        $schedule = [
            'booking_id' => $booking->id,
            'total_revenue' => $booking->total_amount,
            'recognized_revenue' => 0.00,
            'deferred_revenue' => $booking->total_amount,
            'recognition_start_date' => $service_date,
            'recognition_end_date' => date('Y-m-d', strtotime($service_date . " +{$duration_days} days")),
            'recognition_method' => 'straight_line',
            'recognition_schedule' => json_encode($this->build_recognition_schedule(
                $booking->total_amount,
                $service_date,
                $duration_days
            ))
        ];

        $wpdb->insert(
            $wpdb->prefix . 'bkx_financial_revenue_schedule',
            $schedule
        );

        // Create initial deferred revenue entry
        $entry = [
            'entry_date' => $booking->created_at,
            'entry_type' => 'deferred_revenue',
            'description' => 'Deferred revenue for booking #' . $booking->id,
            'reference_type' => 'booking',
            'reference_id' => $booking->id,
            'lines' => [
                [
                    'account_code' => 'AR-001',
                    'debit_amount' => $booking->total_amount,
                    'description' => 'Customer payment due'
                ],
                [
                    'account_code' => 'LIA-002', // Deferred Revenue (liability)
                    'credit_amount' => $booking->total_amount,
                    'description' => 'Deferred service revenue'
                ]
            ]
        ];

        return $this->post_journal_entry($entry);
    }

    private function build_recognition_schedule($total_amount, $start_date, $duration_days) {
        $schedule = [];
        $daily_amount = $total_amount / $duration_days;

        for ($day = 0; $day < $duration_days; $day++) {
            $recognition_date = date('Y-m-d', strtotime($start_date . " +{$day} days"));

            $schedule[] = [
                'date' => $recognition_date,
                'amount' => $daily_amount,
                'recognized' => false
            ];
        }

        return $schedule;
    }

    public function process_daily_revenue_recognition() {
        global $wpdb;

        $today = date('Y-m-d');

        // Get all schedules with revenue to recognize today
        $schedules = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}bkx_financial_revenue_schedule
            WHERE fully_recognized = 0
            AND recognition_start_date <= %s
            AND recognition_end_date >= %s
        ", $today, $today));

        foreach ($schedules as $schedule) {
            $this->process_schedule_recognition($schedule, $today);
        }
    }

    private function process_schedule_recognition($schedule, $date) {
        $recognition_schedule = json_decode($schedule->recognition_schedule, true);
        $amount_to_recognize = 0;

        foreach ($recognition_schedule as &$item) {
            if ($item['date'] == $date && !$item['recognized']) {
                $amount_to_recognize += $item['amount'];
                $item['recognized'] = true;
            }
        }

        if ($amount_to_recognize > 0) {
            // Create revenue recognition entry
            $entry = [
                'entry_date' => $date,
                'entry_type' => 'revenue_recognition',
                'description' => 'Daily revenue recognition for booking #' . $schedule->booking_id,
                'reference_type' => 'revenue_schedule',
                'reference_id' => $schedule->id,
                'lines' => [
                    [
                        'account_code' => 'LIA-002', // Deferred Revenue
                        'debit_amount' => $amount_to_recognize,
                        'description' => 'Recognize deferred revenue'
                    ],
                    [
                        'account_code' => 'REV-001', // Revenue
                        'credit_amount' => $amount_to_recognize,
                        'description' => 'Service revenue earned'
                    ]
                ]
            ];

            $this->post_journal_entry($entry);

            // Update schedule
            global $wpdb;
            $new_recognized = $schedule->recognized_revenue + $amount_to_recognize;
            $new_deferred = $schedule->total_revenue - $new_recognized;

            $wpdb->update(
                $wpdb->prefix . 'bkx_financial_revenue_schedule',
                [
                    'recognized_revenue' => $new_recognized,
                    'deferred_revenue' => $new_deferred,
                    'recognition_schedule' => json_encode($recognition_schedule),
                    'fully_recognized' => ($new_deferred <= 0) ? 1 : 0,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $schedule->id]
            );
        }
    }
}
```

---

## 9. Profit & Loss Statement Generator

### P&L Generator
```php
class ProfitLossGenerator {

    public function generate_income_statement($start_date, $end_date) {
        // Revenue
        $revenue = $this->calculate_revenue($start_date, $end_date);

        // Cost of Goods Sold (COGS)
        $cogs = $this->calculate_cogs($start_date, $end_date);

        // Gross Profit
        $gross_profit = $revenue - $cogs;
        $gross_profit_margin = ($revenue > 0) ? ($gross_profit / $revenue) * 100 : 0;

        // Operating Expenses
        $operating_expenses = $this->calculate_operating_expenses($start_date, $end_date);

        // Operating Income
        $operating_income = $gross_profit - $operating_expenses;
        $operating_margin = ($revenue > 0) ? ($operating_income / $revenue) * 100 : 0;

        // Other Income/Expenses
        $other_income = $this->calculate_other_income($start_date, $end_date);
        $other_expenses = $this->calculate_other_expenses($start_date, $end_date);

        // EBITDA
        $depreciation = $this->calculate_depreciation($start_date, $end_date);
        $amortization = $this->calculate_amortization($start_date, $end_date);
        $interest = $this->calculate_interest($start_date, $end_date);
        $taxes = $this->calculate_taxes($start_date, $end_date);

        $ebitda = $operating_income + $depreciation + $amortization;

        // Net Income
        $net_income = $operating_income + $other_income - $other_expenses -
                     $depreciation - $amortization - $interest - $taxes;

        $net_margin = ($revenue > 0) ? ($net_income / $revenue) * 100 : 0;

        return [
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ],
            'revenue' => [
                'total' => $revenue,
                'breakdown' => $this->get_revenue_breakdown($start_date, $end_date)
            ],
            'cogs' => [
                'total' => $cogs,
                'breakdown' => $this->get_cogs_breakdown($start_date, $end_date)
            ],
            'gross_profit' => $gross_profit,
            'gross_profit_margin' => round($gross_profit_margin, 2),
            'operating_expenses' => [
                'total' => $operating_expenses,
                'breakdown' => $this->get_expense_breakdown($start_date, $end_date)
            ],
            'operating_income' => $operating_income,
            'operating_margin' => round($operating_margin, 2),
            'ebitda' => $ebitda,
            'other_income' => $other_income,
            'other_expenses' => $other_expenses,
            'depreciation' => $depreciation,
            'amortization' => $amortization,
            'interest' => $interest,
            'taxes' => $taxes,
            'net_income' => $net_income,
            'net_margin' => round($net_margin, 2)
        ];
    }

    private function calculate_revenue($start_date, $end_date) {
        global $wpdb;

        $revenue = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(credit_amount)
            FROM {$wpdb->prefix}bkx_financial_journal_lines jl
            JOIN {$wpdb->prefix}bkx_financial_journal_entries je ON jl.journal_entry_id = je.id
            JOIN {$wpdb->prefix}bkx_financial_accounts a ON jl.account_id = a.id
            WHERE a.account_type = 'revenue'
            AND je.entry_date BETWEEN %s AND %s
            AND je.posted = 1
        ", $start_date, $end_date));

        return $revenue ?? 0.00;
    }

    private function calculate_operating_expenses($start_date, $end_date) {
        global $wpdb;

        $expenses = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(debit_amount)
            FROM {$wpdb->prefix}bkx_financial_journal_lines jl
            JOIN {$wpdb->prefix}bkx_financial_journal_entries je ON jl.journal_entry_id = je.id
            JOIN {$wpdb->prefix}bkx_financial_accounts a ON jl.account_id = a.id
            WHERE a.account_type = 'expense'
            AND a.account_category = 'operating'
            AND je.entry_date BETWEEN %s AND %s
            AND je.posted = 1
        ", $start_date, $end_date));

        return $expenses ?? 0.00;
    }

    public function compare_periods($period1, $period2) {
        $pl1 = $this->generate_income_statement($period1['start'], $period1['end']);
        $pl2 = $this->generate_income_statement($period2['start'], $period2['end']);

        return [
            'period1' => $pl1,
            'period2' => $pl2,
            'comparison' => [
                'revenue_change' => $pl1['revenue']['total'] - $pl2['revenue']['total'],
                'revenue_change_percent' => $this->calculate_percent_change(
                    $pl2['revenue']['total'],
                    $pl1['revenue']['total']
                ),
                'gross_profit_change' => $pl1['gross_profit'] - $pl2['gross_profit'],
                'operating_income_change' => $pl1['operating_income'] - $pl2['operating_income'],
                'net_income_change' => $pl1['net_income'] - $pl2['net_income'],
                'margin_improvements' => [
                    'gross_margin' => $pl1['gross_profit_margin'] - $pl2['gross_profit_margin'],
                    'operating_margin' => $pl1['operating_margin'] - $pl2['operating_margin'],
                    'net_margin' => $pl1['net_margin'] - $pl2['net_margin']
                ]
            ]
        ];
    }

    private function calculate_percent_change($old_value, $new_value) {
        if ($old_value == 0) {
            return $new_value > 0 ? 100 : 0;
        }

        return round((($new_value - $old_value) / abs($old_value)) * 100, 2);
    }
}
```

---

## 10. Cash Flow Statement

### Cash Flow Analyzer
```php
class CashFlowAnalyzer {

    public function generate_cash_flow_statement($start_date, $end_date) {
        // Operating Activities
        $operating_cash = $this->calculate_operating_cash_flow($start_date, $end_date);

        // Investing Activities
        $investing_cash = $this->calculate_investing_cash_flow($start_date, $end_date);

        // Financing Activities
        $financing_cash = $this->calculate_financing_cash_flow($start_date, $end_date);

        // Net Change in Cash
        $net_change = $operating_cash['net'] + $investing_cash['net'] + $financing_cash['net'];

        // Beginning and Ending Cash Balance
        $beginning_cash = $this->get_cash_balance($start_date);
        $ending_cash = $beginning_cash + $net_change;

        return [
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date
            ],
            'operating_activities' => $operating_cash,
            'investing_activities' => $investing_cash,
            'financing_activities' => $financing_cash,
            'net_change_in_cash' => $net_change,
            'beginning_cash_balance' => $beginning_cash,
            'ending_cash_balance' => $ending_cash
        ];
    }

    private function calculate_operating_cash_flow($start_date, $end_date) {
        global $wpdb;

        // Start with net income
        $pl_generator = new ProfitLossGenerator();
        $income_statement = $pl_generator->generate_income_statement($start_date, $end_date);
        $net_income = $income_statement['net_income'];

        // Add back non-cash expenses
        $depreciation = $income_statement['depreciation'];
        $amortization = $income_statement['amortization'];

        // Changes in working capital
        $ar_change = $this->calculate_ar_change($start_date, $end_date);
        $ap_change = $this->calculate_ap_change($start_date, $end_date);
        $deferred_revenue_change = $this->calculate_deferred_revenue_change($start_date, $end_date);

        $operating_cash = $net_income +
                         $depreciation +
                         $amortization -
                         $ar_change +
                         $ap_change +
                         $deferred_revenue_change;

        return [
            'net_income' => $net_income,
            'adjustments' => [
                'depreciation' => $depreciation,
                'amortization' => $amortization
            ],
            'working_capital_changes' => [
                'accounts_receivable' => -$ar_change,
                'accounts_payable' => $ap_change,
                'deferred_revenue' => $deferred_revenue_change
            ],
            'net' => $operating_cash
        ];
    }

    public function forecast_cash_position($days_ahead = 90) {
        $today = date('Y-m-d');
        $current_cash = $this->get_cash_balance($today);

        // Get historical cash flow patterns
        $historical_data = $this->get_historical_cash_flow(90);
        $avg_daily_cash_flow = array_sum($historical_data) / count($historical_data);

        // Project future cash position
        $forecast = [
            'current_cash' => $current_cash,
            'daily_projections' => []
        ];

        $projected_cash = $current_cash;

        for ($day = 1; $day <= $days_ahead; $day++) {
            $date = date('Y-m-d', strtotime("+{$day} days"));

            // Get scheduled inflows
            $scheduled_inflows = $this->get_scheduled_inflows($date);

            // Get scheduled outflows
            $scheduled_outflows = $this->get_scheduled_outflows($date);

            // Use historical average for unscheduled
            $unscheduled = $avg_daily_cash_flow - ($scheduled_inflows - $scheduled_outflows);

            $daily_cash_flow = $scheduled_inflows - $scheduled_outflows + $unscheduled;
            $projected_cash += $daily_cash_flow;

            $forecast['daily_projections'][] = [
                'date' => $date,
                'inflows' => $scheduled_inflows,
                'outflows' => $scheduled_outflows,
                'net_cash_flow' => $daily_cash_flow,
                'projected_balance' => $projected_cash
            ];
        }

        $forecast['ending_cash'] = $projected_cash;

        return $forecast;
    }

    public function calculate_runway() {
        $current_cash = $this->get_cash_balance(date('Y-m-d'));
        $monthly_burn_rate = $this->calculate_burn_rate();

        if ($monthly_burn_rate >= 0) {
            return [
                'runway_months' => 'infinite',
                'runway_days' => 'infinite',
                'message' => 'Company is cash flow positive'
            ];
        }

        $runway_months = abs($current_cash / $monthly_burn_rate);
        $runway_days = $runway_months * 30;

        return [
            'current_cash' => $current_cash,
            'monthly_burn_rate' => abs($monthly_burn_rate),
            'runway_months' => round($runway_months, 1),
            'runway_days' => round($runway_days),
            'projected_zero_date' => date('Y-m-d', strtotime("+{$runway_days} days"))
        ];
    }

    public function calculate_burn_rate() {
        // Calculate average monthly cash burn over last 3 months
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $end_date = date('Y-m-d');

        $cash_flow = $this->generate_cash_flow_statement($start_date, $end_date);
        $monthly_burn = $cash_flow['net_change_in_cash'] / 3;

        return $monthly_burn;
    }
}
```

---

## 11. Financial Statement Visualizations

### Chart Configurations
```javascript
// P&L Waterfall Chart
const plWaterfallConfig = {
    type: 'bar',
    data: {
        labels: ['Revenue', 'COGS', 'Gross Profit', 'Operating Expenses',
                'Operating Income', 'Other', 'Net Income'],
        datasets: [{
            data: [], // Values
            backgroundColor: function(context) {
                const value = context.dataset.data[context.dataIndex];
                return value >= 0 ? 'rgba(75, 192, 192, 0.8)' : 'rgba(255, 99, 132, 0.8)';
            }
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Profit & Loss Waterfall'
            }
        }
    }
};

// Cash Flow Chart
const cashFlowChartConfig = {
    type: 'line',
    data: {
        labels: [],
        datasets: [{
            label: 'Cash Balance',
            data: [],
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Cash Flow Forecast (90 Days)'
            }
        },
        scales: {
            y: {
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
};
```

---

## 12. Security & Compliance

### Data Security
- **Encryption:** Encrypt financial data at rest
- **Access Control:** Role-based permissions
- **Audit Trail:** Complete transaction history
- **Backup:** Automated daily backups
- **Data Integrity:** Transaction validation

### Compliance
- **GAAP/IFRS:** Accounting standards compliance
- **SOX:** Internal controls documentation
- **Tax Compliance:** Accurate tax reporting
- **Audit Support:** Detailed transaction logs
- **Data Retention:** Configurable retention policies

---

## 13. Testing Strategy

### Unit Tests
```php
- test_revenue_recognition()
- test_journal_entry_posting()
- test_tax_calculation()
- test_pl_generation()
- test_cash_flow_calculation()
```

---

## 14. Development Timeline

**Total Timeline:** 14 weeks (3.5 months)

---

## 15. Success Metrics

### Technical Metrics
- Report generation < 5 seconds
- Data accuracy > 99.99%
- Export success rate > 99%

### Business Metrics
- Tax filing accuracy 100%
- Audit readiness score > 95%
- User satisfaction > 4.5/5

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
