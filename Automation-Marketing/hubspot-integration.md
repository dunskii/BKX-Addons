# HubSpot Integration - Development Documentation

## 1. Overview

**Add-on Name:** HubSpot Integration
**Price:** $179
**Category:** Automation & Marketing
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Enterprise-grade HubSpot CRM integration providing comprehensive contact management, deal pipeline automation, marketing automation workflows, email tracking, and advanced analytics. Transform BookingX into a complete customer relationship management solution.

### Value Proposition
- Full CRM integration
- Automated deal pipeline management
- Advanced contact lifecycle tracking
- Marketing automation workflows
- Email marketing integration
- Analytics and reporting
- Lead scoring and nurturing
- Sales process automation
- Customer journey mapping

---

## 2. Features & Requirements

### Core Features
1. **Contact Management**
   - Two-way contact synchronization
   - Custom property mapping
   - Contact lifecycle stages
   - Contact lists and segments
   - Company associations
   - Contact timeline tracking
   - Duplicate management
   - Contact scoring

2. **Deal Pipeline Automation**
   - Auto-create deals from bookings
   - Deal stage automation
   - Pipeline customization
   - Deal value tracking
   - Probability-based forecasting
   - Deal associations
   - Win/loss analysis
   - Revenue attribution

3. **Marketing Automation**
   - Workflow automation
   - Email sequences
   - Lead nurturing campaigns
   - Behavioral triggers
   - Multi-channel campaigns
   - A/B testing
   - Smart content personalization
   - Campaign ROI tracking

4. **Sales Process Integration**
   - Lead assignment rules
   - Task automation
   - Meeting scheduling sync
   - Quote generation
   - Sales email tracking
   - Call logging
   - Document sharing
   - Sales analytics

5. **Analytics & Reporting**
   - Custom dashboards
   - Revenue reporting
   - Conversion analytics
   - Attribution reporting
   - Pipeline analytics
   - Marketing performance
   - Sales productivity
   - Customer lifecycle reports

6. **Email Marketing**
   - Campaign creation
   - Email templates
   - Personalization tokens
   - Send time optimization
   - Engagement tracking
   - List segmentation
   - Automated follow-ups

### User Roles & Permissions
- **Admin:** Full configuration, all features
- **Sales Manager:** Deal management, reporting
- **Marketing Manager:** Campaign creation, workflows
- **Sales Rep:** Contact management, deals
- **Support:** View contacts and tickets only

---

## 3. Technical Specifications

### Technology Stack
- **API Version:** HubSpot API v3
- **PHP SDK:** hubspot/api-client
- **Authentication:** OAuth 2.0 + Private App Token
- **Webhook Support:** Real-time notifications
- **Data Format:** JSON

### Dependencies
- BookingX Core 2.0+
- PHP cURL extension
- PHP JSON extension
- SSL certificate (required)
- HubSpot account (Marketing Hub Professional or higher)

### API Integration Points
```php
// HubSpot CRM API Endpoints
- POST /crm/v3/objects/contacts (Create contact)
- PATCH /crm/v3/objects/contacts/{contactId} (Update contact)
- GET /crm/v3/objects/contacts (List contacts)
- POST /crm/v3/objects/deals (Create deal)
- PATCH /crm/v3/objects/deals/{dealId} (Update deal)
- POST /crm/v3/objects/deals/{dealId}/associations (Associate deal)
- POST /crm/v3/objects/companies (Create company)
- GET /crm/v3/pipelines/deals (Get pipelines)
- POST /crm/v3/objects/notes (Create note)
- POST /crm/v3/objects/tasks (Create task)
- GET /marketing/v3/emails (Get emails)
- POST /automation/v4/flows (Create workflow)
- POST /marketing/v3/campaigns (Create campaign)
- GET /analytics/v2/reports (Get reports)
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
┌─────────────────────────────┐
│   HubSpot Integration       │
│   - Contact Manager         │
│   - Deal Pipeline           │
│   - Workflow Automation     │
│   - Analytics Engine        │
└────────┬────────────────────┘
         │
         ├────────────┬────────────┬────────────┐
         ▼            ▼            ▼            ▼
┌──────────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐
│   Contact    │ │   Deal   │ │ Workflow │ │  Campaign  │
│    Sync      │ │  Manager │ │  Engine  │ │  Manager   │
└──────────────┘ └──────────┘ └──────────┘ └────────────┘
         │
         ▼
┌─────────────────────────┐
│   HubSpot API v3        │
└─────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\HubSpot;

class HubSpotIntegration {
    - init()
    - authenticate()
    - test_connection()
    - refresh_token()
    - get_account_info()
}

class ContactManager {
    - create_contact()
    - update_contact()
    - sync_contact()
    - find_contact()
    - merge_contacts()
    - get_contact_timeline()
    - update_lifecycle_stage()
}

class DealManager {
    - create_deal()
    - update_deal()
    - move_deal_stage()
    - associate_deal()
    - close_deal()
    - calculate_deal_value()
    - forecast_revenue()
}

class PropertyMapper {
    - map_contact_properties()
    - map_deal_properties()
    - create_custom_property()
    - sync_property_values()
}

class WorkflowManager {
    - create_workflow()
    - trigger_workflow()
    - enroll_contact()
    - unenroll_contact()
    - get_workflow_status()
}

class CampaignManager {
    - create_campaign()
    - schedule_campaign()
    - send_email()
    - track_engagement()
    - get_campaign_stats()
}

class AnalyticsEngine {
    - create_dashboard()
    - generate_report()
    - track_attribution()
    - calculate_roi()
    - export_data()
}

class TaskManager {
    - create_task()
    - assign_task()
    - update_task_status()
    - create_reminder()
}

class TimelineManager {
    - add_timeline_event()
    - track_interaction()
    - log_booking_event()
    - track_email_activity()
}
```

---

## 5. Database Schema

### Table: `bkx_hs_contacts`
```sql
CREATE TABLE bkx_hs_contacts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    hs_contact_id VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    lifecycle_stage VARCHAR(50),
    lead_status VARCHAR(50),
    last_synced_at DATETIME,
    sync_status VARCHAR(20) DEFAULT 'synced',
    sync_error TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY customer_id_idx (customer_id),
    INDEX hs_contact_id_idx (hs_contact_id),
    INDEX email_idx (email),
    INDEX lifecycle_stage_idx (lifecycle_stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_hs_deals`
```sql
CREATE TABLE bkx_hs_deals (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    hs_deal_id VARCHAR(50) NOT NULL,
    hs_contact_id VARCHAR(50) NOT NULL,
    dealname VARCHAR(255) NOT NULL,
    dealstage VARCHAR(50) NOT NULL,
    pipeline VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    closedate DATE,
    deal_owner VARCHAR(50),
    probability DECIMAL(5,2),
    forecast_amount DECIMAL(10,2),
    last_synced_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY booking_id_idx (booking_id),
    INDEX hs_deal_id_idx (hs_deal_id),
    INDEX dealstage_idx (dealstage),
    INDEX pipeline_idx (pipeline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_hs_companies`
```sql
CREATE TABLE bkx_hs_companies (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED,
    hs_company_id VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255),
    industry VARCHAR(100),
    num_employees INT,
    annual_revenue DECIMAL(12,2),
    last_synced_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX hs_company_id_idx (hs_company_id),
    INDEX customer_id_idx (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_hs_workflows`
```sql
CREATE TABLE bkx_hs_workflows (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hs_workflow_id VARCHAR(50) NOT NULL UNIQUE,
    workflow_name VARCHAR(255) NOT NULL,
    workflow_type VARCHAR(50) NOT NULL,
    trigger_event VARCHAR(100) NOT NULL,
    trigger_conditions LONGTEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    enrollments INT DEFAULT 0,
    last_triggered_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX hs_workflow_id_idx (hs_workflow_id),
    INDEX trigger_event_idx (trigger_event),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_hs_timeline_events`
```sql
CREATE TABLE bkx_hs_timeline_events (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hs_contact_id VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_data LONGTEXT,
    booking_id BIGINT(20) UNSIGNED,
    occurred_at DATETIME NOT NULL,
    synced_to_hubspot TINYINT(1) DEFAULT 0,
    synced_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX hs_contact_id_idx (hs_contact_id),
    INDEX event_type_idx (event_type),
    INDEX booking_id_idx (booking_id),
    INDEX occurred_at_idx (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_hs_property_mappings`
```sql
CREATE TABLE bkx_hs_property_mappings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_type VARCHAR(50) NOT NULL,
    bookingx_field VARCHAR(100) NOT NULL,
    hs_property_name VARCHAR(100) NOT NULL,
    hs_property_label VARCHAR(255),
    property_type VARCHAR(50) NOT NULL,
    mapping_direction VARCHAR(20) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    UNIQUE KEY mapping_idx (object_type, bookingx_field, hs_property_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_hs_campaigns`
```sql
CREATE TABLE bkx_hs_campaigns (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hs_campaign_id VARCHAR(50) NOT NULL UNIQUE,
    campaign_name VARCHAR(255) NOT NULL,
    campaign_type VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL,
    sent_count INT DEFAULT 0,
    delivered_count INT DEFAULT 0,
    opened_count INT DEFAULT 0,
    clicked_count INT DEFAULT 0,
    sent_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX hs_campaign_id_idx (hs_campaign_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    'hs_auth_type' => 'oauth', // 'oauth' or 'private_app'
    'hs_access_token' => '',
    'hs_refresh_token' => '',
    'hs_private_app_token' => '',
    'hs_portal_id' => '',
    'enable_contact_sync' => true,
    'sync_direction' => 'both', // 'to_hs', 'from_hs', 'both'
    'auto_create_contacts' => true,
    'enable_company_sync' => true,
    'enable_deal_sync' => true,
    'default_pipeline' => '',
    'deal_stage_mappings' => [
        'pending' => 'appointmentscheduled',
        'confirmed' => 'qualifiedtobuy',
        'completed' => 'closedwon',
        'cancelled' => 'closedlost',
    ],
    'enable_workflow_automation' => true,
    'enable_email_campaigns' => true,
    'enable_timeline_events' => true,
    'lifecycle_stage_mapping' => [
        'new' => 'lead',
        'active' => 'customer',
        'loyal' => 'evangelist',
    ],
    'lead_assignment' => 'round_robin', // 'round_robin', 'territory', 'manual'
    'enable_lead_scoring' => true,
    'sync_custom_properties' => true,
    'property_mappings' => [],
    'enable_analytics' => true,
    'sync_frequency' => 'realtime', // 'realtime', 'hourly', 'daily'
]
```

---

## 7. Contact Synchronization

### Create/Update Contact
```php
public function sync_contact($customer_id) {
    $customer = $this->get_customer($customer_id);

    // Check if contact exists
    $hs_contact = $this->find_hs_contact($customer->email);

    // Map properties
    $properties = $this->map_contact_properties($customer);

    if ($hs_contact) {
        // Update existing contact
        $result = $this->hs_api->updateContact($hs_contact['id'], [
            'properties' => $properties,
        ]);
    } else {
        // Create new contact
        $result = $this->hs_api->createContact([
            'properties' => $properties,
        ]);

        // Set lifecycle stage
        if ($result && isset($result['id'])) {
            $this->update_lifecycle_stage(
                $result['id'],
                $this->determine_lifecycle_stage($customer)
            );
        }
    }

    if ($result && isset($result['id'])) {
        $this->store_contact_mapping(
            $customer_id,
            $result['id'],
            $customer->email
        );

        // Associate with company if applicable
        if ($customer->company_name) {
            $this->associate_company($result['id'], $customer);
        }

        // Add timeline events
        $this->sync_timeline_events($result['id'], $customer_id);

        return $result['id'];
    }

    return false;
}
```

### Property Mapping
```php
private function map_contact_properties($customer) {
    $properties = [
        'email' => $customer->email,
        'firstname' => $customer->first_name,
        'lastname' => $customer->last_name,
        'phone' => $customer->phone,
        'address' => $customer->address,
        'city' => $customer->city,
        'state' => $customer->state,
        'zip' => $customer->zip_code,
        'country' => $customer->country,
    ];

    // Add custom properties
    $custom_mappings = $this->get_property_mappings('contact');

    foreach ($custom_mappings as $mapping) {
        if ($mapping->mapping_direction === 'from_hs') {
            continue;
        }

        $value = $this->get_customer_field_value(
            $customer,
            $mapping->bookingx_field
        );

        if ($value !== null) {
            $properties[$mapping->hs_property_name] = $this->format_property_value(
                $value,
                $mapping->property_type
            );
        }
    }

    // Add booking statistics
    $properties['total_bookings'] = $this->get_booking_count($customer->id);
    $properties['total_revenue'] = $this->get_total_revenue($customer->id);
    $properties['last_booking_date'] = $this->get_last_booking_date($customer->id);
    $properties['first_booking_date'] = $this->get_first_booking_date($customer->id);

    return $properties;
}
```

---

## 8. Deal Pipeline Management

### Create Deal from Booking
```php
public function create_deal_from_booking($booking_id) {
    $booking = $this->get_booking($booking_id);
    $hs_contact_id = $this->get_hs_contact_id($booking->customer_id);

    if (!$hs_contact_id) {
        $hs_contact_id = $this->sync_contact($booking->customer_id);
    }

    // Get pipeline and stage
    $pipeline = $this->settings['default_pipeline'];
    $dealstage = $this->get_deal_stage($booking->status);

    // Calculate close date
    $closedate = $this->calculate_close_date($booking);

    // Prepare deal properties
    $properties = [
        'dealname' => sprintf(
            '%s - Booking #%s',
            $booking->service_name,
            $booking->booking_number
        ),
        'pipeline' => $pipeline,
        'dealstage' => $dealstage,
        'amount' => $booking->total_amount,
        'closedate' => date('Y-m-d\TH:i:s.000\Z', strtotime($closedate)),
        'booking_id' => $booking->id,
        'booking_date' => date('Y-m-d\TH:i:s.000\Z', strtotime($booking->start_datetime)),
        'service_name' => $booking->service_name,
        'deal_currency_code' => $booking->currency,
    ];

    // Create deal
    $result = $this->hs_api->createDeal([
        'properties' => $properties,
    ]);

    if ($result && isset($result['id'])) {
        // Associate deal with contact
        $this->associate_deal_to_contact($result['id'], $hs_contact_id);

        // Store mapping
        $this->store_deal_mapping(
            $booking_id,
            $result['id'],
            $hs_contact_id,
            $dealstage
        );

        // Create note
        $this->add_deal_note(
            $result['id'],
            sprintf('Deal created from BookingX booking #%s', $booking->booking_number)
        );

        // Calculate and update probability
        $this->update_deal_probability($result['id'], $dealstage);

        return $result['id'];
    }

    return false;
}
```

### Deal Stage Automation
```php
public function update_deal_stage($booking_id, $new_status) {
    $deal = $this->get_deal_by_booking($booking_id);

    if (!$deal) {
        return false;
    }

    $new_dealstage = $this->get_deal_stage($new_status);
    $booking = $this->get_booking($booking_id);

    // Update deal
    $result = $this->hs_api->updateDeal($deal->hs_deal_id, [
        'properties' => [
            'dealstage' => $new_dealstage,
            'amount' => $booking->total_amount, // Update amount in case it changed
        ],
    ]);

    if ($result) {
        // Update probability
        $this->update_deal_probability($deal->hs_deal_id, $new_dealstage);

        // Add timeline note
        $this->add_deal_note(
            $deal->hs_deal_id,
            sprintf('Booking status changed to: %s', $new_status)
        );

        // Close deal if completed or cancelled
        if ($new_status === 'completed') {
            $this->close_deal($deal->hs_deal_id, 'won');
        } elseif ($new_status === 'cancelled') {
            $this->close_deal($deal->hs_deal_id, 'lost');
        }

        // Update local record
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'bkx_hs_deals',
            [
                'dealstage' => $new_dealstage,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $deal->id]
        );

        return true;
    }

    return false;
}

private function update_deal_probability($deal_id, $dealstage) {
    $probability_map = [
        'appointmentscheduled' => 20,
        'qualifiedtobuy' => 40,
        'presentationscheduled' => 60,
        'decisionmakerboughtin' => 80,
        'contractsent' => 90,
        'closedwon' => 100,
        'closedlost' => 0,
    ];

    $probability = $probability_map[$dealstage] ?? 50;

    // Calculate forecast amount
    $deal = $this->hs_api->getDeal($deal_id);
    $amount = $deal['properties']['amount'] ?? 0;
    $forecast_amount = $amount * ($probability / 100);

    $this->hs_api->updateDeal($deal_id, [
        'properties' => [
            'hs_deal_stage_probability' => $probability,
            'hs_forecast_amount' => $forecast_amount,
        ],
    ]);
}
```

---

## 9. Marketing Automation Workflows

### Create Post-Booking Workflow
```php
public function create_post_booking_workflow() {
    $workflow = [
        'name' => 'Post-Booking Follow-Up',
        'type' => 'PROPERTY_ANCHOR',
        'enabled' => true,
        'actions' => [
            // Wait 24 hours
            [
                'type' => 'DELAY',
                'delayMilliseconds' => 86400000, // 24 hours
            ],
            // Send thank you email
            [
                'type' => 'SEND_EMAIL',
                'emailId' => $this->get_email_template_id('thank_you'),
            ],
            // Wait 7 days
            [
                'type' => 'DELAY',
                'delayMilliseconds' => 604800000, // 7 days
            ],
            // Send review request
            [
                'type' => 'SEND_EMAIL',
                'emailId' => $this->get_email_template_id('review_request'),
            ],
            // Create task for follow-up
            [
                'type' => 'CREATE_TASK',
                'taskType' => 'TODO',
                'subject' => 'Follow up with customer',
                'dueDate' => '+14 days',
            ],
        ],
        'enrollmentTriggers' => [
            [
                'filterFamily' => 'PropertyValue',
                'property' => 'last_booking_date',
                'operator' => 'HAS_PROPERTY',
            ],
        ],
    ];

    $result = $this->hs_api->createWorkflow($workflow);

    if ($result && isset($result['id'])) {
        $this->store_workflow_mapping(
            $result['id'],
            'post_booking',
            'booking_completed'
        );

        return $result['id'];
    }

    return false;
}
```

### Enroll Contact in Workflow
```php
public function enroll_in_workflow($hs_contact_id, $workflow_name) {
    $workflow = $this->get_workflow_by_name($workflow_name);

    if (!$workflow) {
        return false;
    }

    $result = $this->hs_api->enrollContactInWorkflow(
        $workflow->hs_workflow_id,
        $hs_contact_id
    );

    if ($result) {
        // Update workflow enrollment count
        global $wpdb;
        $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->prefix}bkx_hs_workflows
            SET enrollments = enrollments + 1,
                last_triggered_at = %s
            WHERE id = %d
        ", current_time('mysql'), $workflow->id));

        return true;
    }

    return false;
}
```

---

## 10. Timeline Event Tracking

### Add Booking Event to Timeline
```php
public function add_booking_to_timeline($booking_id) {
    $booking = $this->get_booking($booking_id);
    $hs_contact_id = $this->get_hs_contact_id($booking->customer_id);

    if (!$hs_contact_id) {
        return false;
    }

    $event = [
        'eventTypeId' => 'booking_created',
        'occurredAt' => strtotime($booking->created_at) * 1000,
        'objectId' => $hs_contact_id,
        'properties' => [
            'booking_number' => $booking->booking_number,
            'service_name' => $booking->service_name,
            'booking_date' => $booking->start_datetime,
            'total_amount' => $booking->total_amount,
            'status' => $booking->status,
        ],
    ];

    $result = $this->hs_api->createTimelineEvent($event);

    if ($result) {
        // Store locally
        $this->store_timeline_event(
            $hs_contact_id,
            'booking_created',
            json_encode($event['properties']),
            $booking_id
        );

        return true;
    }

    return false;
}
```

---

## 11. Email Campaign Management

### Create and Send Campaign
```php
public function create_email_campaign($params) {
    // Create campaign
    $campaign = $this->hs_api->createCampaign([
        'name' => $params['campaign_name'],
        'subject' => $params['subject'],
        'fromName' => get_bloginfo('name'),
        'replyTo' => get_option('admin_email'),
    ]);

    if (!$campaign || !isset($campaign['id'])) {
        return false;
    }

    // Set campaign content
    $this->hs_api->updateCampaignContent($campaign['id'], [
        'html' => $params['html_content'],
    ]);

    // Set recipients
    if (isset($params['list_id'])) {
        $this->hs_api->setCampaignRecipients($campaign['id'], [
            'listIds' => [$params['list_id']],
        ]);
    }

    // Schedule or send
    if (isset($params['send_time'])) {
        $this->hs_api->scheduleCampaign($campaign['id'], $params['send_time']);
    } else {
        $this->hs_api->sendCampaign($campaign['id']);
    }

    // Store campaign record
    $this->store_campaign_record($campaign['id'], $params);

    return $campaign['id'];
}
```

---

## 12. Lead Scoring

### Calculate and Update Lead Score
```php
public function calculate_lead_score($customer_id) {
    $score = 0;

    // Booking activity scoring
    $bookings = $this->get_customer_bookings($customer_id);
    $score += count($bookings) * 15; // 15 points per booking

    // Revenue scoring
    $total_revenue = $this->get_total_revenue($customer_id);
    $score += floor($total_revenue / 20); // 1 point per $20

    // Recency scoring
    $last_booking = $this->get_last_booking($customer_id);
    if ($last_booking) {
        $days_ago = (time() - strtotime($last_booking->created_at)) / 86400;
        if ($days_ago < 30) {
            $score += 25;
        } elseif ($days_ago < 90) {
            $score += 15;
        } elseif ($days_ago < 180) {
            $score += 5;
        }
    }

    // Email engagement (if available from HubSpot)
    $hs_contact_id = $this->get_hs_contact_id($customer_id);
    if ($hs_contact_id) {
        $engagement = $this->get_email_engagement($hs_contact_id);
        $score += $engagement['email_opens'] * 2;
        $score += $engagement['email_clicks'] * 5;
    }

    // Update score in HubSpot
    if ($hs_contact_id) {
        $this->hs_api->updateContact($hs_contact_id, [
            'properties' => [
                'hs_lead_score' => $score,
            ],
        ]);

        // Update lifecycle stage based on score
        if ($score >= 100) {
            $this->update_lifecycle_stage($hs_contact_id, 'marketingqualifiedlead');
        } elseif ($score >= 50) {
            $this->update_lifecycle_stage($hs_contact_id, 'lead');
        }
    }

    return $score;
}
```

---

## 13. Analytics & Reporting

### Generate Revenue Report
```php
public function generate_revenue_report($start_date, $end_date) {
    global $wpdb;

    // Get booking revenue data
    $revenue_data = $wpdb->get_results($wpdb->prepare("
        SELECT
            DATE(b.created_at) as date,
            COUNT(*) as booking_count,
            SUM(b.total_amount) as total_revenue,
            AVG(b.total_amount) as avg_booking_value
        FROM {$wpdb->prefix}bookingx_bookings b
        WHERE b.status = 'completed'
        AND b.created_at BETWEEN %s AND %s
        GROUP BY DATE(b.created_at)
        ORDER BY date ASC
    ", $start_date, $end_date));

    // Create custom report in HubSpot
    $report = $this->hs_api->createCustomReport([
        'name' => 'BookingX Revenue Report',
        'dataType' => 'DEALS',
        'metrics' => ['amount', 'count'],
        'dimensions' => ['closedate'],
        'filters' => [
            [
                'property' => 'closedate',
                'operator' => 'BETWEEN',
                'value' => [$start_date, $end_date],
            ],
        ],
    ]);

    return [
        'revenue_data' => $revenue_data,
        'hubspot_report_id' => $report['id'] ?? null,
    ];
}
```

---

## 14. Security Considerations

### Data Security
- **Token Encryption:** Secure storage of OAuth tokens
- **HTTPS Required:** All API calls over SSL
- **Token Refresh:** Automatic token renewal
- **Data Validation:** Sanitize all data before sending
- **Access Control:** WordPress capability checks
- **Audit Logging:** Track all API activities

### Privacy & Compliance
- **GDPR:** Support data export and deletion
- **Consent Management:** Track marketing consent
- **Data Retention:** Configurable retention policies
- **Opt-out Support:** Honor unsubscribe requests
- **Data Encryption:** Encrypted sensitive data storage

---

## 15. Testing Strategy

### Unit Tests
```php
- test_contact_creation()
- test_contact_update()
- test_deal_creation()
- test_property_mapping()
- test_workflow_enrollment()
- test_timeline_events()
- test_lead_scoring()
- test_campaign_creation()
```

### Integration Tests
```php
- test_complete_sync_flow()
- test_deal_lifecycle()
- test_workflow_automation()
- test_two_way_sync()
- test_token_refresh()
- test_batch_operations()
```

---

## 16. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] OAuth authentication
- [ ] API client setup
- [ ] Settings page

### Phase 2: Contact Management (Week 3-4)
- [ ] Contact sync
- [ ] Property mapping
- [ ] Company association
- [ ] Lifecycle stages

### Phase 3: Deal Pipeline (Week 5-6)
- [ ] Deal creation
- [ ] Stage automation
- [ ] Pipeline management
- [ ] Forecasting

### Phase 4: Marketing Automation (Week 7-9)
- [ ] Workflow creation
- [ ] Email campaigns
- [ ] Timeline events
- [ ] Lead scoring

### Phase 5: Analytics (Week 10-11)
- [ ] Reporting integration
- [ ] Custom dashboards
- [ ] Attribution tracking
- [ ] ROI calculation

### Phase 6: Testing & Launch (Week 12-14)
- [ ] Testing
- [ ] Documentation
- [ ] QA and launch

**Total Timeline:** 14 weeks (3.5 months)

---

## 17. Success Metrics

### Technical Metrics
- Contact sync success rate > 99%
- Deal creation accuracy > 98%
- API response time < 1 second
- Workflow trigger success > 97%
- Zero data loss incidents

### Business Metrics
- Sales pipeline visibility improvement > 90%
- Lead conversion rate increase > 20%
- Customer retention improvement > 25%
- Marketing ROI increase > 35%
- Sales productivity increase > 30%

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
