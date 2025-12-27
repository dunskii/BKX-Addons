# Salesforce Connector - Development Documentation

## 1. Overview

**Add-on Name:** Salesforce Connector
**Price:** $199
**Category:** Automation & Marketing
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Enterprise-grade Salesforce CRM integration providing full synchronization of contacts, leads, opportunities, accounts, and custom objects. Advanced reporting dashboards, custom field mapping, process automation, and comprehensive sales pipeline management for large-scale booking operations.

### Value Proposition
- Complete Salesforce CRM integration
- Lead and opportunity management
- Account hierarchy synchronization
- Custom field mapping and validation
- Process Builder automation
- Advanced reporting and analytics
- Einstein Analytics integration
- Multi-cloud support (Sales, Service, Marketing)
- Enterprise security and governance
- Scalable architecture for large volumes

---

## 2. Features & Requirements

### Core Features
1. **Lead Management**
   - Auto-create leads from bookings
   - Lead qualification workflows
   - Lead assignment rules
   - Lead scoring integration
   - Lead conversion tracking
   - Lead source attribution
   - Web-to-Lead integration
   - Lead nurturing campaigns

2. **Opportunity Tracking**
   - Auto-create opportunities from bookings
   - Stage-based pipeline management
   - Forecasting and quota tracking
   - Close date automation
   - Win/loss analysis
   - Opportunity splits
   - Product line items
   - Competitor tracking

3. **Account & Contact Management**
   - Two-way contact synchronization
   - Account hierarchy support
   - Contact roles and relationships
   - Account territories
   - Parent-child account relationships
   - Contact merge and deduplication
   - Person Accounts support
   - B2B and B2C models

4. **Custom Field Mapping**
   - Flexible field mapping UI
   - Support for all Salesforce field types
   - Picklist value mapping
   - Formula field support
   - Validation rule handling
   - Required field management
   - Record type mapping
   - Multi-select picklist support

5. **Process Automation**
   - Trigger Salesforce Process Builder
   - Flow automation
   - Approval process integration
   - Email alert triggers
   - Task and event creation
   - Chatter posts
   - Custom Apex invocation

6. **Advanced Reporting**
   - Custom report creation
   - Dashboard integration
   - Einstein Analytics datasets
   - Real-time sync status reports
   - Error tracking and monitoring
   - Performance analytics
   - Revenue forecasting
   - Pipeline health reports

7. **Multi-Cloud Support**
   - Sales Cloud integration
   - Service Cloud (Cases, Knowledge)
   - Marketing Cloud (Journey Builder)
   - Commerce Cloud
   - Community Cloud
   - Platform Events

### User Roles & Permissions
- **Admin:** Full configuration, all Salesforce access
- **Sales Manager:** Opportunity management, reporting
- **Sales Rep:** Lead and opportunity management
- **Marketing:** Campaign management, lead generation
- **Support:** Case management, knowledge base
- **Analyst:** Reporting and analytics access

---

## 3. Technical Specifications

### Technology Stack
- **API Version:** Salesforce REST API v58.0
- **PHP SDK:** phpforce/soap-client or REST client
- **Authentication:** OAuth 2.0 (Web Server Flow, JWT Bearer Flow)
- **Real-time Integration:** Platform Events, Streaming API
- **Bulk Operations:** Bulk API 2.0
- **Data Format:** JSON/XML

### Dependencies
- BookingX Core 2.0+
- PHP cURL extension
- PHP JSON/XML extensions
- PHP OpenSSL extension
- SSL certificate (required)
- Salesforce licenses (Sales Cloud, Service Cloud)

### API Integration Points
```php
// Salesforce REST API Endpoints
- POST /services/data/v58.0/sobjects/Lead (Create Lead)
- PATCH /services/data/v58.0/sobjects/Lead/{Id} (Update Lead)
- POST /services/data/v58.0/sobjects/Contact (Create Contact)
- PATCH /services/data/v58.0/sobjects/Contact/{Id} (Update Contact)
- POST /services/data/v58.0/sobjects/Account (Create Account)
- POST /services/data/v58.0/sobjects/Opportunity (Create Opportunity)
- PATCH /services/data/v58.0/sobjects/Opportunity/{Id} (Update Opportunity)
- POST /services/data/v58.0/sobjects/OpportunityLineItem (Add Product)
- POST /services/data/v58.0/sobjects/Task (Create Task)
- POST /services/data/v58.0/sobjects/Event (Create Event)
- POST /services/data/v58.0/sobjects/Case (Create Case)
- GET /services/data/v58.0/query (SOQL Query)
- POST /services/data/v58.0/composite/batch (Batch Operations)
- POST /services/data/v58.0/composite/tree (Composite Tree)
```

---

## 4. Architecture & Design

### System Architecture
```
┌──────────────────┐
│   BookingX Core  │
│   Event System   │
└────────┬─────────┘
         │
         ▼
┌─────────────────────────────────┐
│   Salesforce Connector          │
│   - Lead Manager                │
│   - Opportunity Manager         │
│   - Account/Contact Sync        │
│   - Field Mapper                │
│   - Process Automation          │
└────────┬────────────────────────┘
         │
         ├────────────┬────────────┬────────────┐
         ▼            ▼            ▼            ▼
┌──────────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐
│   Lead &     │ │   Opp    │ │  Field   │ │  Reporting │
│   Contact    │ │ Pipeline │ │  Mapper  │ │   Engine   │
└──────────────┘ └──────────┘ └──────────┘ └────────────┘
         │
         ├────────────┬────────────┐
         ▼            ▼            ▼
┌──────────────┐ ┌──────────┐ ┌────────────────┐
│ Salesforce   │ │  Bulk    │ │   Streaming    │
│  REST API    │ │   API    │ │      API       │
└──────────────┘ └──────────┘ └────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\Salesforce;

class SalesforceConnector {
    - init()
    - authenticate()
    - refresh_access_token()
    - test_connection()
    - get_org_info()
}

class LeadManager {
    - create_lead()
    - update_lead()
    - convert_lead()
    - assign_lead()
    - qualify_lead()
    - merge_leads()
}

class OpportunityManager {
    - create_opportunity()
    - update_opportunity()
    - move_stage()
    - close_opportunity()
    - add_line_item()
    - calculate_amount()
    - forecast_opportunity()
}

class AccountContactManager {
    - create_account()
    - update_account()
    - create_contact()
    - update_contact()
    - sync_contact()
    - associate_contact_to_account()
    - manage_hierarchy()
}

class FieldMapper {
    - map_field()
    - get_field_metadata()
    - validate_field_value()
    - transform_picklist_value()
    - handle_record_types()
    - map_custom_fields()
}

class ProcessAutomation {
    - trigger_process_builder()
    - invoke_flow()
    - create_task()
    - create_event()
    - post_to_chatter()
    - trigger_approval()
}

class BulkOperations {
    - bulk_insert()
    - bulk_update()
    - bulk_upsert()
    - check_job_status()
    - get_batch_results()
}

class ReportingEngine {
    - create_report()
    - run_report()
    - create_dashboard()
    - export_data()
    - einstein_analytics()
}

class StreamingAPIListener {
    - subscribe_to_topic()
    - handle_push_notification()
    - process_platform_event()
}

class ErrorHandler {
    - log_api_error()
    - retry_failed_sync()
    - handle_rate_limits()
    - notify_admin()
}
```

---

## 5. Database Schema

### Table: `bkx_sf_connections`
```sql
CREATE TABLE bkx_sf_connections (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    instance_url VARCHAR(255) NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    token_type VARCHAR(50),
    token_expires_at DATETIME,
    organization_id VARCHAR(50),
    user_id VARCHAR(50),
    last_connected_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX organization_id_idx (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_sf_leads`
```sql
CREATE TABLE bkx_sf_leads (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED,
    sf_lead_id VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL,
    lead_source VARCHAR(100),
    rating VARCHAR(20),
    converted TINYINT(1) DEFAULT 0,
    converted_date DATETIME,
    converted_account_id VARCHAR(50),
    converted_contact_id VARCHAR(50),
    converted_opportunity_id VARCHAR(50),
    last_synced_at DATETIME,
    sync_error TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY customer_id_idx (customer_id),
    INDEX sf_lead_id_idx (sf_lead_id),
    INDEX status_idx (status),
    INDEX converted_idx (converted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_sf_opportunities`
```sql
CREATE TABLE bkx_sf_opportunities (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    sf_opportunity_id VARCHAR(50) NOT NULL,
    sf_account_id VARCHAR(50) NOT NULL,
    sf_contact_id VARCHAR(50),
    opportunity_name VARCHAR(255) NOT NULL,
    stage_name VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    probability DECIMAL(5,2),
    forecast_category VARCHAR(50),
    close_date DATE,
    is_won TINYINT(1) DEFAULT 0,
    is_closed TINYINT(1) DEFAULT 0,
    owner_id VARCHAR(50),
    record_type_id VARCHAR(50),
    last_synced_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY booking_id_idx (booking_id),
    INDEX sf_opportunity_id_idx (sf_opportunity_id),
    INDEX stage_name_idx (stage_name),
    INDEX close_date_idx (close_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_sf_accounts`
```sql
CREATE TABLE bkx_sf_accounts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED,
    sf_account_id VARCHAR(50) NOT NULL UNIQUE,
    account_name VARCHAR(255) NOT NULL,
    account_type VARCHAR(50),
    industry VARCHAR(100),
    parent_account_id VARCHAR(50),
    billing_street TEXT,
    billing_city VARCHAR(100),
    billing_state VARCHAR(100),
    billing_postal_code VARCHAR(20),
    billing_country VARCHAR(100),
    phone VARCHAR(50),
    website VARCHAR(255),
    annual_revenue DECIMAL(15,2),
    number_of_employees INT,
    last_synced_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX sf_account_id_idx (sf_account_id),
    INDEX customer_id_idx (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_sf_contacts`
```sql
CREATE TABLE bkx_sf_contacts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    sf_contact_id VARCHAR(50) NOT NULL,
    sf_account_id VARCHAR(50),
    email VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    title VARCHAR(100),
    phone VARCHAR(50),
    mobile_phone VARCHAR(50),
    mailing_street TEXT,
    mailing_city VARCHAR(100),
    mailing_state VARCHAR(100),
    mailing_postal_code VARCHAR(20),
    mailing_country VARCHAR(100),
    lead_source VARCHAR(100),
    last_synced_at DATETIME,
    sync_error TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY customer_id_idx (customer_id),
    INDEX sf_contact_id_idx (sf_contact_id),
    INDEX sf_account_id_idx (sf_account_id),
    INDEX email_idx (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_sf_field_mappings`
```sql
CREATE TABLE bkx_sf_field_mappings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sf_object_type VARCHAR(50) NOT NULL,
    bookingx_field VARCHAR(100) NOT NULL,
    sf_field_api_name VARCHAR(100) NOT NULL,
    sf_field_label VARCHAR(255),
    sf_field_type VARCHAR(50) NOT NULL,
    is_required TINYINT(1) DEFAULT 0,
    is_custom TINYINT(1) DEFAULT 0,
    mapping_direction VARCHAR(20) NOT NULL,
    default_value TEXT,
    transformation_rule TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY mapping_idx (sf_object_type, bookingx_field, sf_field_api_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_sf_sync_queue`
```sql
CREATE TABLE bkx_sf_sync_queue (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_type VARCHAR(50) NOT NULL,
    object_id BIGINT(20) UNSIGNED NOT NULL,
    sf_id VARCHAR(50),
    operation VARCHAR(20) NOT NULL,
    payload LONGTEXT,
    priority INT DEFAULT 5,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    last_attempt_at DATETIME,
    processed_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX object_type_idx (object_type),
    INDEX status_idx (status),
    INDEX priority_idx (priority),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_sf_sync_log`
```sql
CREATE TABLE bkx_sf_sync_log (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sync_type VARCHAR(50) NOT NULL,
    direction VARCHAR(20) NOT NULL,
    object_type VARCHAR(50) NOT NULL,
    object_id VARCHAR(50),
    operation VARCHAR(20) NOT NULL,
    request_data LONGTEXT,
    response_data LONGTEXT,
    status_code INT,
    success TINYINT(1) DEFAULT 0,
    error_message TEXT,
    execution_time FLOAT,
    created_at DATETIME NOT NULL,
    INDEX sync_type_idx (sync_type),
    INDEX object_type_idx (object_type),
    INDEX success_idx (success),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    'sf_auth_type' => 'oauth', // 'oauth' or 'jwt'
    'sf_client_id' => '',
    'sf_client_secret' => '',
    'sf_username' => '',
    'sf_password' => '',
    'sf_security_token' => '',
    'sf_instance_url' => '',
    'sf_api_version' => '58.0',
    'sf_sandbox' => false,

    // Lead Settings
    'enable_lead_sync' => true,
    'lead_source' => 'BookingX',
    'lead_status_new' => 'Open - Not Contacted',
    'lead_assignment_rule_id' => '',
    'auto_convert_leads' => false,
    'lead_record_type_id' => '',

    // Opportunity Settings
    'enable_opportunity_sync' => true,
    'default_opportunity_owner' => '',
    'opportunity_record_type_id' => '',
    'stage_mappings' => [
        'pending' => 'Qualification',
        'confirmed' => 'Needs Analysis',
        'completed' => 'Closed Won',
        'cancelled' => 'Closed Lost',
    ],
    'create_opportunity_products' => true,

    // Account/Contact Settings
    'enable_account_sync' => true,
    'enable_contact_sync' => true,
    'contact_record_type_id' => '',
    'account_record_type_id' => '',
    'person_accounts' => false,
    'auto_create_accounts' => true,

    // Sync Settings
    'sync_direction' => 'both', // 'to_sf', 'from_sf', 'both'
    'sync_mode' => 'realtime', // 'realtime', 'bulk', 'scheduled'
    'bulk_batch_size' => 200,
    'enable_streaming_api' => true,

    // Field Mappings
    'custom_field_mappings' => [],

    // Process Automation
    'trigger_process_builder' => true,
    'trigger_workflows' => true,
    'create_tasks' => true,
    'post_to_chatter' => false,

    // Error Handling
    'retry_failed_syncs' => true,
    'max_retry_attempts' => 3,
    'retry_delay_seconds' => 300,
    'error_notification_email' => get_option('admin_email'),

    // Logging
    'enable_sync_logging' => true,
    'log_retention_days' => 90,
    'log_success_operations' => false,
]
```

---

## 7. Lead Management

### Create Lead from Booking
```php
public function create_lead_from_booking($booking_id) {
    $booking = $this->get_booking($booking_id);
    $customer = $this->get_customer($booking->customer_id);

    // Check if lead already exists
    $existing_lead = $this->find_lead_by_email($customer->email);

    if ($existing_lead && !$existing_lead->converted) {
        // Update existing lead
        return $this->update_lead($existing_lead->sf_lead_id, $booking);
    }

    // Prepare lead data
    $lead_data = [
        'FirstName' => $customer->first_name,
        'LastName' => $customer->last_name,
        'Email' => $customer->email,
        'Phone' => $customer->phone,
        'Company' => $customer->company_name ?: 'Individual',
        'LeadSource' => $this->settings['lead_source'],
        'Status' => $this->settings['lead_status_new'],
        'Street' => $customer->address,
        'City' => $customer->city,
        'State' => $customer->state,
        'PostalCode' => $customer->zip_code,
        'Country' => $customer->country,
    ];

    // Add custom fields
    $custom_fields = $this->map_custom_fields('Lead', $customer, $booking);
    $lead_data = array_merge($lead_data, $custom_fields);

    // Add to queue or create immediately
    if ($this->settings['sync_mode'] === 'realtime') {
        $result = $this->sf_api->createLead($lead_data);

        if ($result && isset($result['id'])) {
            $this->store_lead_mapping(
                $customer->id,
                $booking->id,
                $result['id'],
                $customer->email
            );

            // Trigger assignment rule if configured
            if ($this->settings['lead_assignment_rule_id']) {
                $this->assign_lead($result['id']);
            }

            // Auto-convert if setting enabled
            if ($this->settings['auto_convert_leads']) {
                $this->convert_lead($result['id']);
            }

            return $result['id'];
        }
    } else {
        $this->add_to_sync_queue('Lead', $customer->id, 'create', $lead_data);
    }

    return false;
}
```

### Convert Lead
```php
public function convert_lead($sf_lead_id) {
    $lead = $this->get_lead_by_sf_id($sf_lead_id);

    if (!$lead || $lead->converted) {
        return false;
    }

    // Prepare conversion data
    $conversion_data = [
        'leadId' => $sf_lead_id,
        'convertedStatus' => 'Converted',
        'doNotCreateOpportunity' => false,
    ];

    // Perform conversion
    $result = $this->sf_api->convertLead($conversion_data);

    if ($result && $result['success']) {
        // Update lead record
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'bkx_sf_leads',
            [
                'converted' => 1,
                'converted_date' => current_time('mysql'),
                'converted_account_id' => $result['accountId'],
                'converted_contact_id' => $result['contactId'],
                'converted_opportunity_id' => $result['opportunityId'],
                'updated_at' => current_time('mysql'),
            ],
            ['sf_lead_id' => $sf_lead_id]
        );

        // Store account mapping
        if ($result['accountId']) {
            $this->store_account_mapping($lead->customer_id, $result['accountId']);
        }

        // Store contact mapping
        if ($result['contactId']) {
            $this->store_contact_mapping($lead->customer_id, $result['contactId']);
        }

        // Store opportunity mapping
        if ($result['opportunityId'] && $lead->booking_id) {
            $this->store_opportunity_mapping($lead->booking_id, $result['opportunityId']);
        }

        return $result;
    }

    return false;
}
```

---

## 8. Opportunity Management

### Create Opportunity from Booking
```php
public function create_opportunity_from_booking($booking_id) {
    $booking = $this->get_booking($booking_id);
    $customer = $this->get_customer($booking->customer_id);

    // Get or create account and contact
    $sf_account_id = $this->get_or_create_account($customer);
    $sf_contact_id = $this->get_or_create_contact($customer, $sf_account_id);

    if (!$sf_account_id) {
        $this->log_error('Failed to get/create account for booking ' . $booking_id);
        return false;
    }

    // Prepare opportunity data
    $opp_data = [
        'Name' => sprintf(
            '%s - %s (#%s)',
            $customer->first_name . ' ' . $customer->last_name,
            $booking->service_name,
            $booking->booking_number
        ),
        'AccountId' => $sf_account_id,
        'ContactId' => $sf_contact_id,
        'StageName' => $this->get_opportunity_stage($booking->status),
        'Amount' => $booking->total_amount,
        'CloseDate' => $this->calculate_close_date($booking),
        'Probability' => $this->calculate_probability($booking->status),
        'LeadSource' => 'BookingX',
        'Type' => 'New Customer',
        'Description' => sprintf(
            'Booking for %s on %s',
            $booking->service_name,
            date('F j, Y', strtotime($booking->start_datetime))
        ),
    ];

    // Add record type if configured
    if ($this->settings['opportunity_record_type_id']) {
        $opp_data['RecordTypeId'] = $this->settings['opportunity_record_type_id'];
    }

    // Add custom fields
    $custom_fields = $this->map_custom_fields('Opportunity', $customer, $booking);
    $opp_data = array_merge($opp_data, $custom_fields);

    // Create opportunity
    $result = $this->sf_api->createOpportunity($opp_data);

    if ($result && isset($result['id'])) {
        $this->store_opportunity_mapping(
            $booking_id,
            $result['id'],
            $sf_account_id,
            $sf_contact_id
        );

        // Add line items if enabled
        if ($this->settings['create_opportunity_products']) {
            $this->add_opportunity_line_items($result['id'], $booking);
        }

        // Create initial task
        if ($this->settings['create_tasks']) {
            $this->create_follow_up_task($result['id'], $booking);
        }

        return $result['id'];
    }

    return false;
}
```

### Update Opportunity Stage
```php
public function update_opportunity_stage($booking_id, $new_status) {
    $opportunity = $this->get_opportunity_by_booking($booking_id);

    if (!$opportunity) {
        return false;
    }

    $new_stage = $this->get_opportunity_stage($new_status);
    $booking = $this->get_booking($booking_id);

    $update_data = [
        'StageName' => $new_stage,
        'Amount' => $booking->total_amount,
        'Probability' => $this->calculate_probability($new_status),
    ];

    // Mark as closed if completed or cancelled
    if ($new_status === 'completed') {
        $update_data['IsWon'] = true;
        $update_data['IsClosed'] = true;
        $update_data['CloseDate'] = date('Y-m-d');
    } elseif ($new_status === 'cancelled') {
        $update_data['IsWon'] = false;
        $update_data['IsClosed'] = true;
        $update_data['CloseDate'] = date('Y-m-d');
    }

    // Update opportunity
    $result = $this->sf_api->updateOpportunity($opportunity->sf_opportunity_id, $update_data);

    if ($result) {
        // Update local record
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'bkx_sf_opportunities',
            [
                'stage_name' => $new_stage,
                'amount' => $booking->total_amount,
                'probability' => $update_data['Probability'],
                'is_won' => $update_data['IsWon'] ?? 0,
                'is_closed' => $update_data['IsClosed'] ?? 0,
                'last_synced_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $opportunity->id]
        );

        // Post to Chatter if enabled
        if ($this->settings['post_to_chatter']) {
            $this->post_stage_change_to_chatter(
                $opportunity->sf_opportunity_id,
                $new_stage
            );
        }

        return true;
    }

    return false;
}
```

---

## 9. Custom Field Mapping

### Field Mapper Implementation
```php
public function map_custom_fields($object_type, $customer, $booking = null) {
    $mappings = $this->get_field_mappings($object_type);
    $field_values = [];

    foreach ($mappings as $mapping) {
        if ($mapping->mapping_direction === 'from_sf') {
            continue; // Skip fields that sync from Salesforce
        }

        // Get the value from BookingX
        $value = null;

        if (strpos($mapping->bookingx_field, 'customer.') === 0) {
            $field = str_replace('customer.', '', $mapping->bookingx_field);
            $value = $customer->$field ?? null;
        } elseif ($booking && strpos($mapping->bookingx_field, 'booking.') === 0) {
            $field = str_replace('booking.', '', $mapping->bookingx_field);
            $value = $booking->$field ?? null;
        }

        // Apply transformation if defined
        if ($mapping->transformation_rule && $value !== null) {
            $value = $this->apply_transformation($value, $mapping->transformation_rule);
        }

        // Use default value if no value found
        if ($value === null && $mapping->default_value) {
            $value = $mapping->default_value;
        }

        // Validate and format based on field type
        if ($value !== null) {
            $formatted_value = $this->format_field_value(
                $value,
                $mapping->sf_field_type
            );

            if ($formatted_value !== null) {
                $field_values[$mapping->sf_field_api_name] = $formatted_value;
            }
        } elseif ($mapping->is_required) {
            $this->log_error(sprintf(
                'Required field %s is missing for %s',
                $mapping->sf_field_api_name,
                $object_type
            ));
        }
    }

    return $field_values;
}

private function format_field_value($value, $field_type) {
    switch ($field_type) {
        case 'boolean':
            return (bool) $value;

        case 'currency':
        case 'double':
        case 'percent':
            return (float) $value;

        case 'int':
            return (int) $value;

        case 'date':
            return date('Y-m-d', strtotime($value));

        case 'datetime':
            return date('Y-m-d\TH:i:s\Z', strtotime($value));

        case 'picklist':
        case 'multipicklist':
            return $this->map_picklist_value($value, $field_type);

        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;

        case 'phone':
            return preg_replace('/[^0-9+\-() ]/', '', $value);

        case 'url':
            return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;

        case 'string':
        case 'textarea':
        case 'reference':
        default:
            return (string) $value;
    }
}
```

---

## 10. Bulk Operations

### Batch Sync Implementation
```php
public function process_bulk_sync() {
    $queue = $this->get_pending_sync_items(200); // Get batch

    if (empty($queue)) {
        return;
    }

    // Group by object type
    $grouped = [];
    foreach ($queue as $item) {
        $grouped[$item->object_type][] = $item;
    }

    // Process each object type
    foreach ($grouped as $object_type => $items) {
        $this->bulk_sync_objects($object_type, $items);
    }
}

private function bulk_sync_objects($object_type, $items) {
    $records = [];

    foreach ($items as $item) {
        $payload = json_decode($item->payload, true);

        $record = [
            'attributes' => ['type' => $object_type],
        ];

        // Add reference ID for tracking
        $record['attributes']['referenceId'] = 'ref_' . $item->id;

        // Merge payload
        $record = array_merge($record, $payload);

        // Add ID if update operation
        if ($item->operation === 'update' && $item->sf_id) {
            $record['Id'] = $item->sf_id;
        }

        $records[] = $record;
    }

    // Execute bulk operation via Composite API
    $result = $this->sf_api->compositeRequest([
        'allOrNone' => false,
        'records' => $records,
    ]);

    // Process results
    foreach ($result as $index => $res) {
        $queue_item = $items[$index];

        if ($res['success']) {
            $this->mark_sync_complete($queue_item->id, $res['id']);
        } else {
            $this->mark_sync_failed(
                $queue_item->id,
                implode(', ', array_column($res['errors'], 'message'))
            );
        }
    }
}
```

---

## 11. Process Automation

### Trigger Process Builder
```php
public function trigger_process_builder($booking_id) {
    if (!$this->settings['trigger_process_builder']) {
        return;
    }

    $booking = $this->get_booking($booking_id);
    $opportunity = $this->get_opportunity_by_booking($booking_id);

    if (!$opportunity) {
        return;
    }

    // Update a field that triggers Process Builder
    $this->sf_api->updateOpportunity($opportunity->sf_opportunity_id, [
        'Booking_Status_Change__c' => $booking->status,
        'Last_Updated_From_BookingX__c' => date('Y-m-d\TH:i:s\Z'),
    ]);
}
```

### Create Task
```php
public function create_follow_up_task($sf_opportunity_id, $booking) {
    if (!$this->settings['create_tasks']) {
        return false;
    }

    $task_data = [
        'WhatId' => $sf_opportunity_id,
        'Subject' => 'Follow up on booking #' . $booking->booking_number,
        'Status' => 'Not Started',
        'Priority' => 'Normal',
        'ActivityDate' => date('Y-m-d', strtotime($booking->start_datetime . ' -1 day')),
        'Description' => sprintf(
            'Follow up with customer before their appointment on %s for %s',
            date('F j, Y', strtotime($booking->start_datetime)),
            $booking->service_name
        ),
    ];

    $result = $this->sf_api->createTask($task_data);

    return $result['id'] ?? false;
}
```

---

## 12. Advanced Reporting

### Create Custom Dashboard
```php
public function create_bookingx_dashboard() {
    $dashboard_metadata = [
        'DeveloperName' => 'BookingX_Performance',
        'Title' => 'BookingX Performance Dashboard',
        'components' => [
            [
                'type' => 'Metric',
                'title' => 'Total Bookings This Month',
                'reportId' => $this->create_bookings_report(),
            ],
            [
                'type' => 'Chart',
                'title' => 'Revenue by Service',
                'reportId' => $this->create_revenue_report(),
            ],
            [
                'type' => 'Table',
                'title' => 'Top Customers',
                'reportId' => $this->create_top_customers_report(),
            ],
        ],
    ];

    // Create dashboard via Metadata API or Tooling API
    $result = $this->sf_api->createDashboard($dashboard_metadata);

    return $result['id'] ?? false;
}
```

### Einstein Analytics Integration
```php
public function sync_to_einstein_analytics() {
    global $wpdb;

    // Get booking data
    $bookings = $wpdb->get_results("
        SELECT
            b.id,
            b.booking_number,
            b.customer_id,
            b.service_id,
            b.start_datetime,
            b.total_amount,
            b.status,
            b.created_at,
            c.email,
            c.first_name,
            c.last_name,
            s.name as service_name
        FROM {$wpdb->prefix}bookingx_bookings b
        LEFT JOIN {$wpdb->prefix}bookingx_customers c ON b.customer_id = c.id
        LEFT JOIN {$wpdb->prefix}bookingx_services s ON b.service_id = s.id
        WHERE b.updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");

    // Format for Einstein Analytics
    $dataset = [];
    foreach ($bookings as $booking) {
        $dataset[] = [
            'BookingId' => $booking->id,
            'BookingNumber' => $booking->booking_number,
            'CustomerEmail' => $booking->email,
            'CustomerName' => $booking->first_name . ' ' . $booking->last_name,
            'ServiceName' => $booking->service_name,
            'BookingDate' => date('Y-m-d', strtotime($booking->start_datetime)),
            'Amount' => (float) $booking->total_amount,
            'Status' => $booking->status,
            'CreatedDate' => date('Y-m-d', strtotime($booking->created_at)),
        ];
    }

    // Upload to Einstein Analytics
    $result = $this->sf_api->uploadToEinsteinAnalytics(
        'BookingX_Bookings',
        $dataset
    );

    return $result;
}
```

---

## 13. Security Considerations

### Data Security
- **OAuth Token Encryption:** Secure token storage
- **HTTPS Required:** All API calls over SSL
- **Token Refresh:** Automatic token renewal
- **Field-Level Security:** Respect Salesforce FLS
- **Sharing Rules:** Honor Salesforce sharing settings
- **IP Restrictions:** Optional IP whitelisting
- **Audit Trail:** Complete activity logging

### Error Handling
- **API Rate Limits:** Implement rate limiting
- **Retry Logic:** Exponential backoff
- **Error Notifications:** Admin alerts
- **Graceful Degradation:** Queue failed syncs
- **Data Validation:** Pre-sync validation

### Compliance
- **GDPR:** Support data export/deletion
- **CCPA:** California privacy compliance
- **HIPAA:** Healthcare data protection (if applicable)
- **SOC 2:** Security compliance
- **Field History Tracking:** Enable on key fields

---

## 14. Testing Strategy

### Unit Tests
```php
- test_lead_creation()
- test_opportunity_creation()
- test_field_mapping()
- test_bulk_operations()
- test_error_handling()
- test_token_refresh()
- test_queue_processing()
```

### Integration Tests
```php
- test_complete_sync_flow()
- test_lead_conversion()
- test_opportunity_lifecycle()
- test_two_way_sync()
- test_process_builder_trigger()
- test_bulk_sync()
```

### Salesforce Sandbox Testing
- Test with Salesforce Developer or Sandbox org
- Use test data generator
- Validate field mappings
- Test all object types
- Verify process automation

---

## 15. Development Timeline

### Phase 1: Foundation (Week 1-3)
- [ ] Database schema
- [ ] OAuth authentication
- [ ] API client implementation
- [ ] Settings framework
- [ ] Error handling system

### Phase 2: Lead & Contact Sync (Week 4-6)
- [ ] Lead management
- [ ] Contact sync
- [ ] Account creation
- [ ] Lead conversion
- [ ] Field mapping

### Phase 3: Opportunity Management (Week 7-9)
- [ ] Opportunity creation
- [ ] Stage automation
- [ ] Line items
- [ ] Forecasting
- [ ] Close logic

### Phase 4: Advanced Features (Week 10-13)
- [ ] Custom field mapping UI
- [ ] Bulk operations
- [ ] Process automation
- [ ] Task/event creation
- [ ] Streaming API

### Phase 5: Reporting & Analytics (Week 14-15)
- [ ] Report creation
- [ ] Dashboard integration
- [ ] Einstein Analytics
- [ ] Sync monitoring

### Phase 6: Testing & Launch (Week 16-18)
- [ ] Comprehensive testing
- [ ] Documentation
- [ ] Performance tuning
- [ ] QA and launch

**Total Timeline:** 18 weeks (4.5 months)

---

## 16. Success Metrics

### Technical Metrics
- Sync success rate > 99%
- API response time < 2 seconds
- Bulk operation success > 98%
- Token refresh success > 99.9%
- Zero data loss incidents

### Business Metrics
- CRM adoption rate > 80%
- Sales pipeline visibility > 95%
- Lead conversion rate improvement > 25%
- Sales cycle time reduction > 20%
- Revenue forecasting accuracy > 90%
- Customer data completeness > 95%

---

## 17. Known Limitations

1. **API Rate Limits:** Salesforce imposes API call limits based on license type
2. **Bulk API:** Maximum 10,000 records per batch
3. **Field Types:** Some complex field types may have limitations
4. **Custom Objects:** Require additional configuration
5. **Process Builder:** Cannot directly invoke from external systems
6. **Streaming API:** Requires additional setup and monitoring

---

## 18. Future Enhancements

### Version 2.0 Roadmap
- [ ] Salesforce Mobile app integration
- [ ] Voice integration (Salesforce Voice)
- [ ] Einstein AI recommendations
- [ ] Advanced territory management
- [ ] Multi-currency support enhancement
- [ ] CPQ (Configure, Price, Quote) integration
- [ ] Service Cloud integration (Cases)
- [ ] Community Cloud portal
- [ ] Platform Events for real-time sync
- [ ] GraphQL API support

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
