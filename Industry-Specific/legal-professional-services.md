# Legal & Professional Services Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Legal & Professional Services
**Price:** $179
**Category:** Industry-Specific Solutions
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Comprehensive practice management solution designed for law firms, legal consultants, accountants, consultants, and professional service providers. Features advanced time tracking, billable hours management, document management with client portals, retainer tracking, trust accounting integration, conflict checking, case/matter management, and professional compliance tools.

### Value Proposition
- Precise time tracking with billable hour management
- Secure document management and client portals
- Automated invoice generation from time entries
- Retainer and trust account tracking
- Conflict of interest checking
- Matter-centric organization
- Client communication tracking
- Expense tracking and reimbursement
- Professional compliance management
- Integration with legal/accounting software
- Statute of limitations tracking
- Secure client data handling

---

## 2. Features & Requirements

### Core Features

1. **Time Tracking & Billable Hours**
   - Timer-based time tracking
   - Manual time entry
   - Activity-based time codes
   - Billable vs non-billable classification
   - Multiple billing rate support
   - Time entry approval workflow
   - Bulk time entry editing
   - Time entry templates
   - Mobile time tracking
   - Automatic time rounding rules
   - Minimum billing increments
   - Time entry audit trail

2. **Matter/Case Management**
   - Matter creation and organization
   - Matter status tracking
   - Client-matter relationships
   - Matter type categorization
   - Responsible attorney assignment
   - Originating attorney tracking
   - Matter team management
   - Matter budget tracking
   - Milestone and deadline tracking
   - Custom matter fields
   - Matter archival system
   - Matter templates

3. **Document Management**
   - Secure document storage
   - Version control
   - Document categorization
   - Matter-document linking
   - Document templates
   - E-signature integration
   - Document sharing controls
   - Download tracking
   - Document expiration dates
   - Full-text search
   - Document annotations
   - Automatic document retention

4. **Client Portal**
   - Secure client login
   - Document access and download
   - Invoice viewing and payment
   - Appointment scheduling
   - Secure messaging
   - Case status updates
   - Time entry visibility
   - Expense approval
   - Retainer balance display
   - Client document upload
   - Portal activity logging
   - Mobile responsive design

5. **Invoice & Billing Management**
   - Automated invoice generation
   - Time and expense billing
   - Flat fee invoicing
   - Retainer billing
   - Progress billing
   - Contingency fee support
   - Multiple billing formats
   - Invoice customization
   - Payment tracking
   - Trust accounting integration
   - Write-off management
   - Aging reports

6. **Retainer & Trust Account Management**
   - Retainer agreement tracking
   - Trust account ledgers
   - Automatic retainer depletion
   - Trust reconciliation
   - IOLTA compliance
   - Retainer replenishment alerts
   - Three-way reconciliation
   - Trust transaction reporting
   - Client fund protection
   - Audit trail maintenance
   - Interest calculation
   - State bar compliance

7. **Conflict Checking**
   - Client name checking
   - Opposing party checking
   - Matter conflict detection
   - Relationship mapping
   - Conflict waiver tracking
   - Automated conflict alerts
   - Historical conflict search
   - Family/affiliate checking
   - Ethical wall management
   - Conflict resolution workflow
   - Conflict check reporting

8. **Expense Tracking**
   - Expense entry and categorization
   - Receipt attachment
   - Client expense allocation
   - Matter expense tracking
   - Reimbursable vs non-reimbursable
   - Markup calculation
   - Expense approval workflow
   - Expense reporting
   - Integration with accounting
   - Mileage tracking
   - Credit card integration
   - Expense budgets

9. **Calendar & Deadline Management**
   - Court date tracking
   - Statute of limitations calculator
   - Rule-based deadline calculation
   - Recurring deadline templates
   - Multi-user calendar sync
   - Deadline reminder system
   - Conflict scheduling prevention
   - Calendar sharing rules
   - Court calendar integration
   - Deadline priority levels
   - Delegation tracking
   - Calendar reporting

10. **Client Communication Tracking**
    - Communication log
    - Email integration
    - Phone call logging
    - Meeting notes
    - Letter correspondence tracking
    - Communication templates
    - Follow-up reminders
    - Communication timeline
    - Privileged communication marking
    - Communication billing
    - Client contact history
    - Communication analytics

11. **Professional Compliance**
    - Bar association rules engine
    - Continuing legal education (CLE) tracking
    - License renewal reminders
    - Professional liability insurance tracking
    - Ethics compliance monitoring
    - Client trust account rules
    - Attorney-client privilege protection
    - Engagement letter management
    - Conflict waiver documentation
    - Professional responsibility reporting
    - Malpractice prevention tools
    - Regulatory deadline tracking

12. **Reporting & Analytics**
    - Billable hours reports
    - Realization rate analysis
    - Matter profitability
    - Client revenue reports
    - Work-in-progress (WIP) reports
    - Collection reports
    - Attorney productivity reports
    - Time utilization analysis
    - Revenue forecasting
    - Budget vs actual reports
    - Trust account reports
    - Custom report builder

### User Roles & Permissions

- **Managing Partner:** Full access, firm-wide reporting, financial oversight
- **Partner/Attorney:** Matter management, time tracking, client access, billing
- **Associate Attorney:** Matter access, time tracking, limited billing access
- **Paralegal:** Matter support, time tracking, document management, research
- **Legal Secretary:** Calendar management, document prep, communication logging
- **Billing Administrator:** Invoicing, payment tracking, trust accounting
- **Office Manager:** Staff management, compliance tracking, resource allocation
- **Client:** Portal access, document viewing, invoice payment, scheduling

---

## 3. Technical Specifications

### Technology Stack
- **Frontend:** React for client portal and time tracking interface
- **Backend:** WordPress REST API with role-based access
- **Document Storage:** Encrypted storage with version control
- **Time Tracking:** JavaScript timer with localStorage backup
- **E-Signature:** DocuSign or Adobe Sign integration
- **Calendar:** FullCalendar.js with conflict detection
- **Accounting Integration:** QuickBooks, Xero API connections
- **Search:** Elasticsearch for document full-text search
- **Reporting:** Advanced Custom Fields + Chart.js

### Dependencies
- BookingX Core 2.0+
- WooCommerce (for invoicing and payments)
- PHP OpenSSL extension (for document encryption)
- PHP PDO extension (for database operations)
- SSL certificate (required for client portal)
- Optional: DocuSign/Adobe Sign API
- Optional: QuickBooks/Xero integration
- Optional: Microsoft Office 365 (for calendar sync)

### API Integration Points
```php
// Time Tracking API
POST   /wp-json/bookingx/v1/legal/time/start
POST   /wp-json/bookingx/v1/legal/time/stop
POST   /wp-json/bookingx/v1/legal/time/entry
GET    /wp-json/bookingx/v1/legal/time/entries
PUT    /wp-json/bookingx/v1/legal/time/entry/{id}
DELETE /wp-json/bookingx/v1/legal/time/entry/{id}
POST   /wp-json/bookingx/v1/legal/time/approve

// Matter Management API
POST   /wp-json/bookingx/v1/legal/matters
GET    /wp-json/bookingx/v1/legal/matters/{id}
PUT    /wp-json/bookingx/v1/legal/matters/{id}
GET    /wp-json/bookingx/v1/legal/matters/client/{client_id}
POST   /wp-json/bookingx/v1/legal/matters/{id}/team
GET    /wp-json/bookingx/v1/legal/matters/{id}/time-entries
GET    /wp-json/bookingx/v1/legal/matters/{id}/financials

// Document Management API
POST   /wp-json/bookingx/v1/legal/documents/upload
GET    /wp-json/bookingx/v1/legal/documents/{id}
PUT    /wp-json/bookingx/v1/legal/documents/{id}
DELETE /wp-json/bookingx/v1/legal/documents/{id}
GET    /wp-json/bookingx/v1/legal/documents/matter/{matter_id}
POST   /wp-json/bookingx/v1/legal/documents/{id}/share
GET    /wp-json/bookingx/v1/legal/documents/search

// Client Portal API
GET    /wp-json/bookingx/v1/legal/portal/documents
GET    /wp-json/bookingx/v1/legal/portal/invoices
GET    /wp-json/bookingx/v1/legal/portal/matters
POST   /wp-json/bookingx/v1/legal/portal/message
GET    /wp-json/bookingx/v1/legal/portal/time-entries
POST   /wp-json/bookingx/v1/legal/portal/payment

// Billing API
POST   /wp-json/bookingx/v1/legal/invoices/generate
GET    /wp-json/bookingx/v1/legal/invoices/{id}
POST   /wp-json/bookingx/v1/legal/invoices/{id}/send
PUT    /wp-json/bookingx/v1/legal/invoices/{id}/payment
GET    /wp-json/bookingx/v1/legal/invoices/matter/{matter_id}
GET    /wp-json/bookingx/v1/legal/invoices/aging

// Trust Account API
POST   /wp-json/bookingx/v1/legal/trust/deposit
POST   /wp-json/bookingx/v1/legal/trust/withdrawal
GET    /wp-json/bookingx/v1/legal/trust/ledger/{client_id}
GET    /wp-json/bookingx/v1/legal/trust/reconciliation
POST   /wp-json/bookingx/v1/legal/trust/transfer
GET    /wp-json/bookingx/v1/legal/trust/balance/{client_id}

// Conflict Checking API
POST   /wp-json/bookingx/v1/legal/conflicts/check
GET    /wp-json/bookingx/v1/legal/conflicts/matter/{id}
POST   /wp-json/bookingx/v1/legal/conflicts/waiver
GET    /wp-json/bookingx/v1/legal/conflicts/history

// Expense Tracking API
POST   /wp-json/bookingx/v1/legal/expenses
GET    /wp-json/bookingx/v1/legal/expenses/{id}
PUT    /wp-json/bookingx/v1/legal/expenses/{id}
GET    /wp-json/bookingx/v1/legal/expenses/matter/{matter_id}
POST   /wp-json/bookingx/v1/legal/expenses/{id}/approve

// Calendar API
GET    /wp-json/bookingx/v1/legal/calendar/deadlines
POST   /wp-json/bookingx/v1/legal/calendar/deadline
PUT    /wp-json/bookingx/v1/legal/calendar/deadline/{id}
POST   /wp-json/bookingx/v1/legal/calendar/calculate-deadline
GET    /wp-json/bookingx/v1/legal/calendar/conflicts

// Reporting API
GET    /wp-json/bookingx/v1/legal/reports/billable-hours
GET    /wp-json/bookingx/v1/legal/reports/wip
GET    /wp-json/bookingx/v1/legal/reports/realization
GET    /wp-json/bookingx/v1/legal/reports/matter-profitability
GET    /wp-json/bookingx/v1/legal/reports/collections
POST   /wp-json/bookingx/v1/legal/reports/custom
```

---

## 4. Architecture & Design

### System Architecture
```
┌────────────────────────────────────┐
│   Client Portal                    │
│  - Document Access                 │
│  - Invoice Payment                 │
│  - Secure Messaging                │
└───────────┬────────────────────────┘
            │
            ▼
┌────────────────────────────────────┐
│   BookingX Legal Core              │
│  - Matter Management               │
│  - Time Tracking                   │
│  - Document Management             │
└───────────┬────────────────────────┘
            │
            ├──────────────┬───────────────┬──────────────┬────────────────┐
            ▼              ▼               ▼              ▼                ▼
┌─────────────┐  ┌──────────────┐  ┌─────────────┐  ┌──────────┐  ┌──────────────┐
│ Billing &   │  │ Trust        │  │ Conflict    │  │ Calendar │  │ Compliance   │
│ Invoicing   │  │ Accounting   │  │ Checking    │  │ Manager  │  │ Manager      │
└─────────────┘  └──────────────┘  └─────────────┘  └──────────┘  └──────────────┘
```

### Data Flow: Time Entry to Invoice
```
1. Attorney starts timer → Work performed → Timer stopped
2. Time entry created → Matter assigned → Activity code selected
3. Billable status confirmed → Rate applied → Entry saved
4. Manager reviews → Approves time → Ready for billing
5. Billing period ends → Invoice generated → Time entries included
6. Invoice sent to client → Client pays via portal
7. Payment recorded → Trust account updated → Matter updated
```

### Class Structure
```php
namespace BookingX\Addons\Legal;

class TimeTracker {
    - start_timer()
    - stop_timer()
    - pause_timer()
    - create_time_entry()
    - edit_time_entry()
    - delete_time_entry()
    - approve_time_entry()
    - calculate_billable_amount()
    - apply_rounding_rules()
    - generate_timesheet()
}

class MatterManager {
    - create_matter()
    - update_matter()
    - assign_team()
    - track_status()
    - set_budget()
    - calculate_financials()
    - generate_matter_report()
    - archive_matter()
    - manage_milestones()
}

class DocumentManager {
    - upload_document()
    - version_document()
    - categorize_document()
    - link_to_matter()
    - share_document()
    - track_access()
    - search_documents()
    - apply_retention_policy()
    - encrypt_document()
    - generate_signature_request()
}

class ClientPortal {
    - authenticate_client()
    - display_documents()
    - display_invoices()
    - enable_messaging()
    - show_matter_status()
    - process_payment()
    - upload_client_document()
    - schedule_appointment()
    - view_time_entries()
}

class BillingManager {
    - generate_invoice()
    - calculate_time_charges()
    - calculate_expense_charges()
    - apply_retainer()
    - send_invoice()
    - record_payment()
    - track_write_offs()
    - generate_aging_report()
    - handle_payment_plans()
}

class TrustAccountManager {
    - create_trust_account()
    - record_deposit()
    - record_withdrawal()
    - transfer_to_operating()
    - reconcile_account()
    - calculate_interest()
    - generate_ledger()
    - track_client_funds()
    - ensure_compliance()
}

class ConflictChecker {
    - perform_conflict_check()
    - check_client_conflicts()
    - check_opposing_parties()
    - check_related_matters()
    - document_conflict()
    - obtain_waiver()
    - track_ethical_walls()
    - generate_conflict_report()
}

class ExpenseTracker {
    - record_expense()
    - attach_receipt()
    - categorize_expense()
    - allocate_to_matter()
    - calculate_markup()
    - approve_expense()
    - track_reimbursements()
    - integrate_with_accounting()
}

class CalendarManager {
    - create_deadline()
    - calculate_deadline()
    - set_reminders()
    - sync_calendars()
    - check_conflicts()
    - track_statute_limitations()
    - manage_court_dates()
    - delegate_tasks()
}

class CommunicationTracker {
    - log_communication()
    - integrate_email()
    - track_phone_calls()
    - document_meetings()
    - mark_privileged()
    - create_timeline()
    - bill_communication_time()
    - generate_communication_report()
}

class ComplianceManager {
    - track_bar_licenses()
    - manage_cle_credits()
    - monitor_trust_compliance()
    - ensure_privilege_protection()
    - manage_engagement_letters()
    - track_conflict_waivers()
    - generate_compliance_reports()
    - set_regulatory_reminders()
}

class ReportingEngine {
    - generate_billable_hours_report()
    - calculate_realization_rate()
    - analyze_matter_profitability()
    - track_wip()
    - generate_collection_report()
    - analyze_productivity()
    - forecast_revenue()
    - create_custom_report()
}
```

---

## 5. Database Schema

### Table: `bkx_legal_matters`
```sql
CREATE TABLE bkx_legal_matters (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matter_number VARCHAR(50) NOT NULL UNIQUE,
    matter_name VARCHAR(255) NOT NULL,
    matter_type VARCHAR(100),
    practice_area VARCHAR(100),
    client_id BIGINT(20) UNSIGNED NOT NULL,
    responsible_attorney BIGINT(20) UNSIGNED NOT NULL,
    originating_attorney BIGINT(20) UNSIGNED,
    billing_attorney BIGINT(20) UNSIGNED,
    status VARCHAR(50) DEFAULT 'open',
    open_date DATE NOT NULL,
    close_date DATE,
    description TEXT,
    opposing_party VARCHAR(255),
    court_jurisdiction VARCHAR(100),
    case_number VARCHAR(100),
    billing_type VARCHAR(50) NOT NULL,
    hourly_rate DECIMAL(10,2),
    flat_fee DECIMAL(10,2),
    contingency_percentage DECIMAL(5,2),
    budget_amount DECIMAL(10,2),
    budget_hours DECIMAL(10,2),
    retainer_amount DECIMAL(10,2),
    trust_balance DECIMAL(10,2) DEFAULT 0,
    total_time_billed DECIMAL(10,2) DEFAULT 0,
    total_expenses DECIMAL(10,2) DEFAULT 0,
    total_billed DECIMAL(10,2) DEFAULT 0,
    total_collected DECIMAL(10,2) DEFAULT 0,
    conflict_checked TINYINT(1) DEFAULT 0,
    conflict_check_date DATE,
    statute_of_limitations DATE,
    custom_fields TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX matter_number_idx (matter_number),
    INDEX client_id_idx (client_id),
    INDEX responsible_attorney_idx (responsible_attorney),
    INDEX status_idx (status),
    INDEX practice_area_idx (practice_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_legal_time_entries`
```sql
CREATE TABLE bkx_legal_time_entries (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matter_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    entry_date DATE NOT NULL,
    start_time DATETIME,
    end_time DATETIME,
    duration DECIMAL(10,2) NOT NULL,
    rounded_duration DECIMAL(10,2),
    activity_code VARCHAR(50),
    activity_description TEXT NOT NULL,
    billable TINYINT(1) DEFAULT 1,
    billing_rate DECIMAL(10,2) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    approved_by BIGINT(20) UNSIGNED,
    approved_at DATETIME,
    invoiced TINYINT(1) DEFAULT 0,
    invoice_id BIGINT(20) UNSIGNED,
    write_off_amount DECIMAL(10,2) DEFAULT 0,
    write_off_reason TEXT,
    timer_used TINYINT(1) DEFAULT 0,
    edited TINYINT(1) DEFAULT 0,
    edit_history TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX matter_id_idx (matter_id),
    INDEX user_id_idx (user_id),
    INDEX entry_date_idx (entry_date),
    INDEX status_idx (status),
    INDEX billable_idx (billable),
    INDEX invoiced_idx (invoiced)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_legal_documents`
```sql
CREATE TABLE bkx_legal_documents (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_name VARCHAR(255) NOT NULL,
    document_type VARCHAR(100),
    matter_id BIGINT(20) UNSIGNED NOT NULL,
    client_id BIGINT(20) UNSIGNED NOT NULL,
    attachment_id BIGINT(20) UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT(20),
    mime_type VARCHAR(100),
    version INT DEFAULT 1,
    parent_document_id BIGINT(20) UNSIGNED,
    is_current_version TINYINT(1) DEFAULT 1,
    document_category VARCHAR(100),
    document_tags TEXT,
    description TEXT,
    uploaded_by BIGINT(20) UNSIGNED NOT NULL,
    is_privileged TINYINT(1) DEFAULT 1,
    is_encrypted TINYINT(1) DEFAULT 1,
    encryption_key_id VARCHAR(100),
    client_accessible TINYINT(1) DEFAULT 0,
    access_level VARCHAR(20) DEFAULT 'firm',
    download_count INT DEFAULT 0,
    last_accessed_at DATETIME,
    requires_signature TINYINT(1) DEFAULT 0,
    signature_status VARCHAR(20),
    signature_request_id VARCHAR(100),
    retention_date DATE,
    expiration_date DATE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX matter_id_idx (matter_id),
    INDEX client_id_idx (client_id),
    INDEX document_type_idx (document_type),
    INDEX document_category_idx (document_category),
    INDEX client_accessible_idx (client_accessible),
    FULLTEXT INDEX document_search_idx (document_name, description, document_tags)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_legal_invoices`
```sql
CREATE TABLE bkx_legal_invoices (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    matter_id BIGINT(20) UNSIGNED NOT NULL,
    client_id BIGINT(20) UNSIGNED NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    billing_period_start DATE,
    billing_period_end DATE,
    time_charges DECIMAL(10,2) DEFAULT 0,
    expense_charges DECIMAL(10,2) DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    retainer_applied DECIMAL(10,2) DEFAULT 0,
    amount_due DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    balance DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    sent_date DATETIME,
    payment_terms VARCHAR(100),
    notes TEXT,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX invoice_number_idx (invoice_number),
    INDEX matter_id_idx (matter_id),
    INDEX client_id_idx (client_id),
    INDEX invoice_date_idx (invoice_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_legal_trust_accounts`
```sql
CREATE TABLE bkx_legal_trust_accounts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT(20) UNSIGNED NOT NULL,
    matter_id BIGINT(20) UNSIGNED NOT NULL,
    account_number VARCHAR(50) NOT NULL UNIQUE,
    account_type VARCHAR(50) DEFAULT 'trust',
    bank_name VARCHAR(255),
    account_status VARCHAR(20) DEFAULT 'active',
    opening_balance DECIMAL(10,2) DEFAULT 0,
    current_balance DECIMAL(10,2) DEFAULT 0,
    minimum_balance DECIMAL(10,2) DEFAULT 0,
    last_reconciliation_date DATE,
    interest_earned DECIMAL(10,2) DEFAULT 0,
    iolta_account TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX client_id_idx (client_id),
    INDEX matter_id_idx (matter_id),
    INDEX account_number_idx (account_number),
    INDEX account_status_idx (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_legal_trust_transactions`
```sql
CREATE TABLE bkx_legal_trust_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trust_account_id BIGINT(20) UNSIGNED NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    transaction_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    reference_number VARCHAR(100),
    related_invoice_id BIGINT(20) UNSIGNED,
    check_number VARCHAR(50),
    payment_method VARCHAR(50),
    reconciled TINYINT(1) DEFAULT 0,
    reconciliation_date DATE,
    transferred_to_operating TINYINT(1) DEFAULT 0,
    transfer_date DATE,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX trust_account_id_idx (trust_account_id),
    INDEX transaction_type_idx (transaction_type),
    INDEX transaction_date_idx (transaction_date),
    INDEX reconciled_idx (reconciled),
    FOREIGN KEY (trust_account_id) REFERENCES bkx_legal_trust_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_legal_conflicts`
```sql
CREATE TABLE bkx_legal_conflicts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    check_id VARCHAR(100) NOT NULL UNIQUE,
    matter_id BIGINT(20) UNSIGNED,
    check_date DATETIME NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    opposing_parties TEXT,
    related_parties TEXT,
    matter_description TEXT,
    conflict_detected TINYINT(1) DEFAULT 0,
    conflict_details TEXT,
    conflicted_matters TEXT,
    conflict_level VARCHAR(20),
    waiver_required TINYINT(1) DEFAULT 0,
    waiver_obtained TINYINT(1) DEFAULT 0,
    waiver_date DATE,
    waiver_document_id BIGINT(20) UNSIGNED,
    ethical_wall_implemented TINYINT(1) DEFAULT 0,
    cleared_by BIGINT(20) UNSIGNED,
    cleared_date DATE,
    status VARCHAR(20) DEFAULT 'pending',
    checked_by BIGINT(20) UNSIGNED NOT NULL,
    notes TEXT,
    created_at DATETIME NOT NULL,
    INDEX check_id_idx (check_id),
    INDEX matter_id_idx (matter_id),
    INDEX check_date_idx (check_date),
    INDEX conflict_detected_idx (conflict_detected),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_legal_expenses`
```sql
CREATE TABLE bkx_legal_expenses (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matter_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    expense_date DATE NOT NULL,
    expense_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    markup_percentage DECIMAL(5,2) DEFAULT 0,
    markup_amount DECIMAL(10,2) DEFAULT 0,
    billable_amount DECIMAL(10,2) NOT NULL,
    reimbursable TINYINT(1) DEFAULT 1,
    receipt_attachment_id BIGINT(20) UNSIGNED,
    vendor VARCHAR(255),
    payment_method VARCHAR(50),
    approved TINYINT(1) DEFAULT 0,
    approved_by BIGINT(20) UNSIGNED,
    approved_at DATETIME,
    invoiced TINYINT(1) DEFAULT 0,
    invoice_id BIGINT(20) UNSIGNED,
    reimbursed TINYINT(1) DEFAULT 0,
    reimbursement_date DATE,
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX matter_id_idx (matter_id),
    INDEX user_id_idx (user_id),
    INDEX expense_date_idx (expense_date),
    INDEX expense_type_idx (expense_type),
    INDEX approved_idx (approved),
    INDEX invoiced_idx (invoiced)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_legal_deadlines`
```sql
CREATE TABLE bkx_legal_deadlines (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matter_id BIGINT(20) UNSIGNED NOT NULL,
    deadline_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    due_date DATE NOT NULL,
    due_time TIME,
    priority VARCHAR(20) DEFAULT 'normal',
    responsible_attorney BIGINT(20) UNSIGNED NOT NULL,
    delegated_to BIGINT(20) UNSIGNED,
    court_date TINYINT(1) DEFAULT 0,
    statute_of_limitations TINYINT(1) DEFAULT 0,
    rule_based VARCHAR(100),
    trigger_date DATE,
    days_offset INT,
    reminder_days TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    completed TINYINT(1) DEFAULT 0,
    completion_date DATE,
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX matter_id_idx (matter_id),
    INDEX due_date_idx (due_date),
    INDEX responsible_attorney_idx (responsible_attorney),
    INDEX status_idx (status),
    INDEX completed_idx (completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_legal_communications`
```sql
CREATE TABLE bkx_legal_communications (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matter_id BIGINT(20) UNSIGNED NOT NULL,
    client_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    communication_type VARCHAR(50) NOT NULL,
    communication_date DATETIME NOT NULL,
    direction VARCHAR(20) NOT NULL,
    subject VARCHAR(500),
    body TEXT,
    duration INT,
    participants TEXT,
    is_privileged TINYINT(1) DEFAULT 1,
    is_billable TINYINT(1) DEFAULT 0,
    time_entry_id BIGINT(20) UNSIGNED,
    follow_up_required TINYINT(1) DEFAULT 0,
    follow_up_date DATE,
    email_message_id VARCHAR(255),
    attachments TEXT,
    notes TEXT,
    created_at DATETIME NOT NULL,
    INDEX matter_id_idx (matter_id),
    INDEX client_id_idx (client_id),
    INDEX user_id_idx (user_id),
    INDEX communication_type_idx (communication_type),
    INDEX communication_date_idx (communication_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_legal_compliance`
```sql
CREATE TABLE bkx_legal_compliance (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    compliance_type VARCHAR(100) NOT NULL,
    license_number VARCHAR(100),
    jurisdiction VARCHAR(100),
    issue_date DATE,
    expiration_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    verification_document_id BIGINT(20) UNSIGNED,
    cle_credits_required INT,
    cle_credits_earned INT DEFAULT 0,
    insurance_carrier VARCHAR(255),
    policy_number VARCHAR(100),
    coverage_amount DECIMAL(12,2),
    reminder_sent TINYINT(1) DEFAULT 0,
    reminder_date DATE,
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX user_id_idx (user_id),
    INDEX compliance_type_idx (compliance_type),
    INDEX expiration_date_idx (expiration_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    // General Legal Settings
    'enable_legal_features' => true,
    'firm_name' => '',
    'firm_type' => 'law_firm', // law_firm, solo_practice, accounting_firm, consulting
    'practice_areas' => [],
    'jurisdiction' => '',
    'bar_number' => '',

    // Time Tracking Settings
    'enable_time_tracking' => true,
    'timer_enabled' => true,
    'minimum_billing_increment' => 0.1, // hours (6 minutes)
    'rounding_method' => 'up', // up, down, nearest
    'require_activity_codes' => true,
    'allow_backdated_entries' => false,
    'max_backdate_days' => 7,
    'require_time_approval' => true,
    'billable_by_default' => true,
    'track_non_billable' => true,

    // Matter Management
    'matter_numbering_format' => 'YYYY-NNNN',
    'require_conflict_check' => true,
    'require_engagement_letter' => true,
    'track_matter_budget' => true,
    'budget_alert_threshold' => 80, // percentage
    'enable_matter_templates' => true,

    // Billing Settings
    'billing_frequency' => 'monthly',
    'default_payment_terms' => 'net_30',
    'enable_progress_billing' => true,
    'enable_flat_fee_billing' => true,
    'enable_contingency_billing' => true,
    'automatic_late_fees' => false,
    'late_fee_percentage' => 5.0,
    'late_fee_grace_period_days' => 15,
    'send_invoice_reminders' => true,
    'reminder_days' => [7, 3, 1],

    // Document Management
    'enable_document_management' => true,
    'encrypt_documents' => true,
    'enable_version_control' => true,
    'enable_esignature' => true,
    'esignature_provider' => 'docusign', // docusign, adobe_sign
    'document_retention_years' => 7,
    'enable_document_expiration' => true,
    'auto_categorize_documents' => false,

    // Client Portal
    'enable_client_portal' => true,
    'portal_document_access' => true,
    'portal_invoice_access' => true,
    'portal_time_entry_visibility' => true,
    'portal_payment_enabled' => true,
    'portal_messaging_enabled' => true,
    'require_portal_2fa' => true,
    'portal_branding_enabled' => true,

    // Trust Accounting
    'enable_trust_accounting' => true,
    'require_trust_reconciliation' => true,
    'reconciliation_frequency' => 'monthly',
    'three_way_reconciliation' => true,
    'iolta_compliant' => true,
    'auto_retainer_application' => true,
    'trust_low_balance_alert' => 500.00,

    // Conflict Checking
    'enable_conflict_checking' => true,
    'auto_conflict_check' => true,
    'check_client_relationships' => true,
    'check_opposing_parties' => true,
    'check_related_entities' => true,
    'require_waiver_documentation' => true,
    'enable_ethical_walls' => true,

    // Compliance Settings
    'track_bar_licenses' => true,
    'track_cle_credits' => true,
    'track_malpractice_insurance' => true,
    'license_expiry_warning_days' => 60,
    'cle_reporting_period' => 'annual',
    'require_insurance_verification' => true,

    // Calendar & Deadlines
    'enable_deadline_calculator' => true,
    'court_rule_templates' => true,
    'auto_generate_reminders' => true,
    'default_reminder_days' => [30, 7, 3, 1],
    'sync_with_google_calendar' => false,
    'sync_with_outlook' => false,

    // Communication Tracking
    'enable_communication_logging' => true,
    'integrate_email' => false,
    'email_provider' => '', // gmail, outlook
    'track_phone_calls' => true,
    'mark_privileged_default' => true,
    'auto_bill_communications' => false,

    // Expense Tracking
    'enable_expense_tracking' => true,
    'require_receipt_attachment' => true,
    'default_expense_markup' => 0,
    'expense_approval_required' => true,
    'track_mileage' => true,
    'mileage_rate' => 0.655, // per mile/km
    'integrate_credit_cards' => false,

    // Reporting & Analytics
    'enable_advanced_reporting' => true,
    'track_realization_rate' => true,
    'track_matter_profitability' => true,
    'wip_reporting_enabled' => true,
    'collection_rate_tracking' => true,
    'productivity_analytics' => true,

    // Security & Privacy
    'attorney_client_privilege_protection' => true,
    'encrypt_sensitive_data' => true,
    'audit_all_access' => true,
    'data_retention_policy' => true,
    'gdpr_compliance' => true,
    'session_timeout_minutes' => 30,
]
```

---

## 7. Industry-Specific Workflows

### Workflow 1: New Matter Intake
```
1. Initial consultation booked → Client information collected
2. Conflict check performed → Conflicts cleared/waived
3. Engagement letter generated → Signed electronically
4. Matter created → Matter number assigned
5. Retainer agreement → Trust deposit received
6. Team assigned → Calendar deadlines set
7. Document folder created → Client portal activated
8. First invoice generated (retainer) → Trust account updated
```

### Workflow 2: Time Entry to Invoice
```
1. Attorney works on matter → Time tracked
2. Time entry created → Activity code assigned
3. Entry submitted for approval → Manager reviews
4. Entries approved → Ready for billing
5. Billing period ends → Invoice generated
6. Invoice reviewed → Sent to client
7. Client views in portal → Payment processed
8. Payment applied → Trust/operating account updated
```

### Workflow 3: Document Management
```
1. Document created → Uploaded to matter
2. Categorized and tagged → Version tracked
3. Matter team has access → Client access granted (if applicable)
4. Client views in portal → Download tracked
5. Document needs signature → E-signature request sent
6. Client signs → Completed document stored
7. Retention period tracked → Auto-archival scheduled
```

### Workflow 4: Trust Account Management
```
1. Retainer received → Deposited to trust
2. Trust transaction recorded → Client ledger updated
3. Work performed → Time/expenses billed
4. Invoice generated → Applied against retainer
5. Trust transfer to operating → Three-way reconciliation
6. Monthly reconciliation → Reports generated
7. Low balance alert → Retainer replenishment requested
```

---

## 8. Compliance & Professional Standards

### Bar Association Compliance
- Attorney-client privilege protection
- Conflict of interest rules
- Client confidentiality requirements
- Fee agreement documentation
- Trust account regulations
- Engagement letter requirements
- Ethical wall implementation

### Trust Accounting Standards
- IOLTA (Interest on Lawyers Trust Accounts) compliance
- Three-way reconciliation
- Client ledger maintenance
- Monthly reconciliation reports
- Separate trust accounting
- Client fund protection
- State bar reporting

### Document Retention
- State-specific retention periods
- Closed matter archival
- Privileged document protection
- Secure destruction protocols
- E-discovery readiness
- Chain of custody maintenance

### Legal Ethics
- Malpractice prevention tools
- Statute of limitations tracking
- Deadline management
- Competent representation support
- Fee reasonableness tracking
- Client communication requirements

---

## 9. Testing Strategy

### Unit Tests
```php
- test_time_entry_calculation()
- test_rounding_rules()
- test_matter_creation()
- test_conflict_checking()
- test_trust_transaction()
- test_invoice_generation()
- test_retainer_application()
- test_document_encryption()
- test_deadline_calculation()
- test_billing_rate_application()
```

### Integration Tests
```php
- test_time_to_invoice_workflow()
- test_trust_deposit_to_transfer()
- test_document_upload_to_client_access()
- test_conflict_check_to_matter_creation()
- test_matter_creation_to_first_invoice()
```

---

## 10. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Core architecture
- [ ] Settings framework
- [ ] API structure

### Phase 2: Time Tracking (Week 3-4)
- [ ] Timer functionality
- [ ] Time entry management
- [ ] Approval workflow
- [ ] Rate calculation

### Phase 3: Matter Management (Week 5-6)
- [ ] Matter CRUD
- [ ] Team assignment
- [ ] Budget tracking
- [ ] Status management

### Phase 4: Document Management (Week 7-8)
- [ ] Upload/download
- [ ] Version control
- [ ] Encryption
- [ ] Search functionality

### Phase 5: Billing System (Week 9-10)
- [ ] Invoice generation
- [ ] Payment tracking
- [ ] Multiple billing types
- [ ] Aging reports

### Phase 6: Trust Accounting (Week 11-12)
- [ ] Trust accounts
- [ ] Transactions
- [ ] Reconciliation
- [ ] Client ledgers

### Phase 7: Conflict Checking (Week 13)
- [ ] Conflict detection
- [ ] Waiver management
- [ ] Relationship mapping
- [ ] Historical search

### Phase 8: Client Portal (Week 14-15)
- [ ] Portal authentication
- [ ] Document access
- [ ] Invoice viewing
- [ ] Payment processing

### Phase 9: Calendar & Deadlines (Week 16)
- [ ] Deadline management
- [ ] Calculator
- [ ] Reminders
- [ ] Calendar sync

### Phase 10: Compliance & Reporting (Week 17-18)
- [ ] License tracking
- [ ] CLE management
- [ ] Compliance reports
- [ ] Analytics dashboard

### Phase 11: UI Development (Week 19-21)
- [ ] Admin interface
- [ ] Client portal UI
- [ ] Mobile responsiveness
- [ ] Reporting dashboards

### Phase 12: Testing & Launch (Week 22-24)
- [ ] Comprehensive testing
- [ ] Security audit
- [ ] Beta with law firms
- [ ] Production deployment

**Total Estimated Timeline:** 24 weeks (6 months)

---

## 11. Success Metrics

### Business Metrics
- 95% time capture rate
- 80% realization rate
- 30% faster billing cycle
- 90% trust account compliance
- 50% reduction in conflicts

### Technical Metrics
- Time entry accuracy 100%
- Invoice generation < 10 seconds
- Document search < 1 second
- Portal uptime > 99.9%
- Zero trust account errors

---

## 12. Known Limitations

1. **Accounting Integration:** Limited to major platforms (QuickBooks, Xero)
2. **E-Signature:** Requires third-party service subscription
3. **Email Integration:** Requires OAuth2 authentication setup
4. **Conflict Checking:** Manual verification still recommended
5. **Trust Accounting:** State-specific rules may require customization
6. **Document OCR:** Not included in v1.0

---

## 13. Future Enhancements

### Version 2.0 Roadmap
- [ ] AI-powered conflict detection
- [ ] Predictive deadline calculation
- [ ] Natural language time entry
- [ ] Advanced document OCR
- [ ] Legal research integration (Lexis, Westlaw)
- [ ] Court filing system integration
- [ ] Blockchain-based document verification
- [ ] Advanced analytics with ML

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development

**Target Industries:**
- Law Firms
- Solo Practitioners
- Legal Consultants
- Accounting Firms
- Management Consultants
- Business Advisors
- Tax Professionals
- Financial Advisors
- Compliance Consultants
