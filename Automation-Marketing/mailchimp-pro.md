# MailChimp Pro Integration - Development Documentation

## 1. Overview

**Add-on Name:** MailChimp Pro
**Price:** $89
**Category:** Automation & Marketing
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Advanced MailChimp integration with automated list management, targeted campaign creation, customer segmentation, and win-back sequences. Transform booking data into intelligent email marketing campaigns with sophisticated automation.

### Value Proposition
- Advanced audience segmentation
- Automated list management
- Behavioral email campaigns
- Customer journey automation
- Win-back campaign sequences
- Purchase-based personalization
- Abandoned booking recovery
- Analytics and reporting integration

---

## 2. Features & Requirements

### Core Features
1. **Automated List Management**
   - Auto-subscribe on booking
   - List segmentation by service
   - Dynamic tag assignment
   - Merge field synchronization
   - Unsubscribe management
   - List cleanup automation
   - Compliance management (GDPR)

2. **Targeted Campaigns**
   - Service-based campaigns
   - Frequency-based targeting
   - Spending tier campaigns
   - Location-based targeting
   - Seasonal campaigns
   - Personalized content
   - A/B test integration

3. **Customer Win-Back Sequences**
   - Dormant customer detection
   - Progressive discount offers
   - Re-engagement campaigns
   - Churn prevention
   - Loyalty rewards promotion
   - Feedback requests
   - Special occasion outreach

4. **Automation Journeys**
   - Welcome series
   - Post-booking follow-up
   - Birthday/anniversary emails
   - Review request sequence
   - Upsell campaigns
   - Cross-sell opportunities
   - Referral program promotion

5. **Advanced Segmentation**
   - RFM analysis (Recency, Frequency, Monetary)
   - Behavioral segments
   - Service preferences
   - Booking patterns
   - Engagement levels
   - Geographic segments
   - Custom field segments

6. **Abandoned Booking Recovery**
   - Cart abandonment tracking
   - Automated reminder emails
   - Incentive offers
   - Progressive reminders
   - Recovery analytics

### User Roles & Permissions
- **Admin:** Full configuration, campaign management
- **Marketing Manager:** Create campaigns, view reports
- **Manager:** View campaigns, basic list management
- **Staff:** View subscriber stats only

---

## 3. Technical Specifications

### Technology Stack
- **API Version:** MailChimp Marketing API v3.0
- **PHP SDK:** mailchimp/marketing (composer)
- **Authentication:** OAuth 2.0 + API Key
- **Webhook Support:** Real-time event notifications
- **Data Format:** JSON

### Dependencies
- BookingX Core 2.0+
- PHP cURL extension
- PHP JSON extension
- SSL certificate (required)
- MailChimp account (Standard or higher)

### API Integration Points
```php
// MailChimp Marketing API Endpoints
- POST /lists/{list_id}/members (Add subscriber)
- PATCH /lists/{list_id}/members/{subscriber_hash} (Update subscriber)
- DELETE /lists/{list_id}/members/{subscriber_hash} (Remove subscriber)
- POST /lists/{list_id}/segments (Create segment)
- POST /campaigns (Create campaign)
- POST /automations/{workflow_id}/emails (Add automation email)
- POST /lists/{list_id}/members/{subscriber_hash}/tags (Add tags)
- GET /lists/{list_id}/members (Get subscribers)
- GET /campaigns/{campaign_id}/reports (Get campaign report)
- POST /batches (Batch operations)
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
│   MailChimp Pro Integration │
│   - Subscriber Manager      │
│   - Campaign Creator        │
│   - Automation Trigger      │
│   - Segment Builder         │
└────────┬────────────────────┘
         │
         ├────────────┬────────────┬────────────┐
         ▼            ▼            ▼            ▼
┌──────────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐
│ Subscriber   │ │ Campaign │ │ Segment  │ │ Automation │
│   Sync       │ │  Manager │ │ Builder  │ │  Manager   │
└──────────────┘ └──────────┘ └──────────┘ └────────────┘
         │
         ▼
┌─────────────────────────┐
│   MailChimp API         │
└─────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\MailChimp;

class MailChimpProIntegration {
    - init()
    - authenticate()
    - test_connection()
    - get_lists()
    - sync_historical_data()
}

class SubscriberManager {
    - add_subscriber()
    - update_subscriber()
    - remove_subscriber()
    - sync_merge_fields()
    - batch_subscribe()
    - manage_tags()
}

class CampaignManager {
    - create_campaign()
    - schedule_campaign()
    - send_campaign()
    - replicate_campaign()
    - get_campaign_stats()
}

class AutomationManager {
    - create_automation()
    - trigger_automation()
    - add_subscriber_to_journey()
    - pause_automation()
    - resume_automation()
}

class SegmentBuilder {
    - create_segment()
    - update_segment()
    - get_segment_members()
    - create_rfm_segments()
    - create_behavior_segments()
}

class AbandonmentTracker {
    - track_abandoned_booking()
    - send_recovery_email()
    - apply_recovery_incentive()
    - measure_recovery_rate()
}

class WinBackCampaign {
    - identify_dormant_customers()
    - create_winback_sequence()
    - send_progressive_offers()
    - track_reactivation()
}

class MergeFieldMapper {
    - map_customer_fields()
    - create_custom_merge_fields()
    - sync_merge_field_values()
}
```

---

## 5. Database Schema

### Table: `bkx_mc_subscribers`
```sql
CREATE TABLE bkx_mc_subscribers (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    list_id VARCHAR(50) NOT NULL,
    subscriber_id VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL,
    last_synced_at DATETIME,
    sync_error TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY customer_list_idx (customer_id, list_id),
    INDEX subscriber_id_idx (subscriber_id),
    INDEX email_idx (email),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_mc_campaigns`
```sql
CREATE TABLE bkx_mc_campaigns (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id VARCHAR(50) NOT NULL UNIQUE,
    campaign_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    subject_line VARCHAR(255),
    list_id VARCHAR(50) NOT NULL,
    segment_id VARCHAR(50),
    status VARCHAR(20) NOT NULL,
    send_time DATETIME,
    recipients_count INT DEFAULT 0,
    opens INT DEFAULT 0,
    clicks INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX campaign_id_idx (campaign_id),
    INDEX campaign_type_idx (campaign_type),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_mc_segments`
```sql
CREATE TABLE bkx_mc_segments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    segment_id VARCHAR(50) NOT NULL UNIQUE,
    list_id VARCHAR(50) NOT NULL,
    segment_name VARCHAR(255) NOT NULL,
    segment_type VARCHAR(50) NOT NULL,
    conditions LONGTEXT,
    member_count INT DEFAULT 0,
    auto_update TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX segment_id_idx (segment_id),
    INDEX list_id_idx (list_id),
    INDEX segment_type_idx (segment_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_mc_automations`
```sql
CREATE TABLE bkx_mc_automations (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    automation_id VARCHAR(50) NOT NULL UNIQUE,
    workflow_id VARCHAR(50) NOT NULL,
    automation_name VARCHAR(255) NOT NULL,
    trigger_event VARCHAR(100) NOT NULL,
    trigger_settings LONGTEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    emails_sent INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX automation_id_idx (automation_id),
    INDEX trigger_event_idx (trigger_event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_mc_abandoned_bookings`
```sql
CREATE TABLE bkx_mc_abandoned_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    booking_date DATETIME,
    total_amount DECIMAL(10,2),
    abandoned_at DATETIME NOT NULL,
    recovery_email_sent TINYINT(1) DEFAULT 0,
    recovery_email_count INT DEFAULT 0,
    last_reminder_sent DATETIME,
    recovered TINYINT(1) DEFAULT 0,
    recovered_at DATETIME,
    booking_id BIGINT(20) UNSIGNED,
    INDEX session_id_idx (session_id),
    INDEX customer_email_idx (customer_email),
    INDEX abandoned_at_idx (abandoned_at),
    INDEX recovered_idx (recovered)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_mc_merge_fields`
```sql
CREATE TABLE bkx_mc_merge_fields (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    list_id VARCHAR(50) NOT NULL,
    merge_id VARCHAR(10) NOT NULL,
    merge_tag VARCHAR(50) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    field_type VARCHAR(50) NOT NULL,
    bookingx_field VARCHAR(100),
    is_required TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    UNIQUE KEY list_merge_idx (list_id, merge_tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    'mc_api_key' => '',
    'mc_server_prefix' => '', // us1, us2, etc.
    'default_list_id' => '',
    'enable_double_optin' => false,
    'auto_subscribe_on_booking' => true,
    'update_existing_subscribers' => true,
    'enable_tags' => true,
    'tag_prefix' => 'bookingx_',
    'auto_tags' => [
        'customer' => true,
        'service_type' => true,
        'booking_count' => true,
    ],
    'enable_automation' => true,
    'enable_abandoned_recovery' => true,
    'abandonment_threshold_minutes' => 30,
    'recovery_email_sequence' => [
        ['delay' => 60, 'discount' => 0],     // 1 hour
        ['delay' => 1440, 'discount' => 10],  // 24 hours - 10% off
        ['delay' => 4320, 'discount' => 15],  // 72 hours - 15% off
    ],
    'enable_winback_campaign' => true,
    'dormant_threshold_days' => 90,
    'winback_discount_percent' => 20,
    'sync_custom_fields' => true,
    'merge_field_mappings' => [],
    'enable_webhooks' => true,
    'gdpr_compliance' => true,
]
```

---

## 7. Subscriber Management

### Add/Update Subscriber
```php
public function sync_subscriber($customer_id, $list_id = null) {
    $customer = $this->get_customer($customer_id);
    $list_id = $list_id ?: $this->settings['default_list_id'];

    // Prepare subscriber data
    $subscriber_data = [
        'email_address' => $customer->email,
        'status' => $this->settings['enable_double_optin'] ? 'pending' : 'subscribed',
        'merge_fields' => $this->map_merge_fields($customer),
        'tags' => $this->generate_auto_tags($customer),
    ];

    // Get subscriber hash
    $subscriber_hash = md5(strtolower($customer->email));

    // Check if subscriber exists
    $existing = $this->get_subscriber($list_id, $subscriber_hash);

    if ($existing) {
        // Update existing subscriber
        $result = $this->mc_api->updateListMember(
            $list_id,
            $subscriber_hash,
            $subscriber_data
        );
    } else {
        // Add new subscriber
        $result = $this->mc_api->addListMember($list_id, $subscriber_data);
    }

    if ($result) {
        $this->store_subscriber_mapping(
            $customer_id,
            $list_id,
            $subscriber_hash,
            $customer->email,
            $subscriber_data['status']
        );

        return true;
    }

    return false;
}
```

### Auto-Tag Generation
```php
private function generate_auto_tags($customer) {
    $tags = [];
    $prefix = $this->settings['tag_prefix'];

    // Basic customer tag
    if ($this->settings['auto_tags']['customer']) {
        $tags[] = $prefix . 'customer';
    }

    // Booking count tags
    if ($this->settings['auto_tags']['booking_count']) {
        $booking_count = $this->get_booking_count($customer->id);

        if ($booking_count === 1) {
            $tags[] = $prefix . 'new_customer';
        } elseif ($booking_count >= 10) {
            $tags[] = $prefix . 'vip';
        } elseif ($booking_count >= 5) {
            $tags[] = $prefix . 'loyal';
        }
    }

    // Service type tags
    if ($this->settings['auto_tags']['service_type']) {
        $services = $this->get_customer_service_types($customer->id);
        foreach ($services as $service) {
            $tags[] = $prefix . 'service_' . sanitize_title($service->name);
        }
    }

    // Spending tier tags
    $total_spent = $this->get_customer_total_spent($customer->id);
    if ($total_spent >= 1000) {
        $tags[] = $prefix . 'high_value';
    } elseif ($total_spent >= 500) {
        $tags[] = $prefix . 'medium_value';
    }

    return $tags;
}
```

---

## 8. Advanced Segmentation

### RFM Segmentation (Recency, Frequency, Monetary)
```php
public function create_rfm_segments($list_id) {
    global $wpdb;

    // Calculate RFM scores for all customers
    $rfm_data = $wpdb->get_results("
        SELECT
            customer_id,
            DATEDIFF(NOW(), MAX(created_at)) as recency_days,
            COUNT(*) as frequency,
            SUM(total_amount) as monetary
        FROM {$wpdb->prefix}bookingx_bookings
        WHERE status = 'completed'
        GROUP BY customer_id
    ");

    // Create segments
    $segments = [
        'champions' => [],
        'loyal_customers' => [],
        'potential_loyalists' => [],
        'at_risk' => [],
        'hibernating' => [],
        'lost' => [],
    ];

    foreach ($rfm_data as $customer) {
        $rfm_score = $this->calculate_rfm_score($customer);

        // Classify customer
        if ($rfm_score['recency'] >= 4 && $rfm_score['frequency'] >= 4 && $rfm_score['monetary'] >= 4) {
            $segments['champions'][] = $customer->customer_id;
        } elseif ($rfm_score['frequency'] >= 3 && $rfm_score['monetary'] >= 3) {
            $segments['loyal_customers'][] = $customer->customer_id;
        } elseif ($rfm_score['recency'] >= 3 && $rfm_score['frequency'] <= 2) {
            $segments['potential_loyalists'][] = $customer->customer_id;
        } elseif ($rfm_score['recency'] <= 2 && $rfm_score['frequency'] >= 3) {
            $segments['at_risk'][] = $customer->customer_id;
        } elseif ($rfm_score['recency'] <= 2 && $rfm_score['frequency'] <= 2) {
            $segments['hibernating'][] = $customer->customer_id;
        } elseif ($rfm_score['recency'] === 1) {
            $segments['lost'][] = $customer->customer_id;
        }
    }

    // Create MailChimp segments
    foreach ($segments as $segment_name => $customer_ids) {
        if (empty($customer_ids)) {
            continue;
        }

        $this->create_mailchimp_segment(
            $list_id,
            ucfirst(str_replace('_', ' ', $segment_name)),
            $customer_ids
        );
    }
}

private function calculate_rfm_score($customer) {
    // Score recency (1-5, 5 being most recent)
    if ($customer->recency_days <= 30) {
        $recency = 5;
    } elseif ($customer->recency_days <= 60) {
        $recency = 4;
    } elseif ($customer->recency_days <= 90) {
        $recency = 3;
    } elseif ($customer->recency_days <= 180) {
        $recency = 2;
    } else {
        $recency = 1;
    }

    // Score frequency (1-5)
    if ($customer->frequency >= 10) {
        $frequency = 5;
    } elseif ($customer->frequency >= 5) {
        $frequency = 4;
    } elseif ($customer->frequency >= 3) {
        $frequency = 3;
    } elseif ($customer->frequency >= 2) {
        $frequency = 2;
    } else {
        $frequency = 1;
    }

    // Score monetary (1-5)
    if ($customer->monetary >= 1000) {
        $monetary = 5;
    } elseif ($customer->monetary >= 500) {
        $monetary = 4;
    } elseif ($customer->monetary >= 250) {
        $monetary = 3;
    } elseif ($customer->monetary >= 100) {
        $monetary = 2;
    } else {
        $monetary = 1;
    }

    return [
        'recency' => $recency,
        'frequency' => $frequency,
        'monetary' => $monetary,
        'total' => $recency + $frequency + $monetary,
    ];
}
```

---

## 9. Abandoned Booking Recovery

### Track Abandoned Bookings
```php
public function track_abandoned_booking($session_id, $data) {
    global $wpdb;
    $table = $wpdb->prefix . 'bkx_mc_abandoned_bookings';

    $abandoned = [
        'session_id' => $session_id,
        'customer_email' => $data['email'],
        'service_id' => $data['service_id'],
        'booking_date' => $data['booking_date'],
        'total_amount' => $data['total_amount'],
        'abandoned_at' => current_time('mysql'),
    ];

    $wpdb->insert($table, $abandoned);

    // Schedule first recovery email
    $this->schedule_recovery_email($wpdb->insert_id);
}

private function schedule_recovery_email($abandonment_id) {
    $sequence = $this->settings['recovery_email_sequence'];

    foreach ($sequence as $index => $step) {
        wp_schedule_single_event(
            time() + ($step['delay'] * 60),
            'bookingx_mc_send_recovery_email',
            [$abandonment_id, $index]
        );
    }
}
```

### Send Recovery Email
```php
public function send_recovery_email($abandonment_id, $sequence_index) {
    $abandonment = $this->get_abandonment($abandonment_id);

    // Check if already recovered
    if ($abandonment->recovered) {
        return;
    }

    $sequence = $this->settings['recovery_email_sequence'][$sequence_index];
    $discount = $sequence['discount'];

    // Generate recovery link with discount
    $recovery_link = $this->generate_recovery_link(
        $abandonment->session_id,
        $discount
    );

    // Create and send campaign
    $campaign_data = [
        'type' => 'regular',
        'recipients' => [
            'list_id' => $this->settings['default_list_id'],
            'segment_opts' => [
                'match' => 'all',
                'conditions' => [
                    [
                        'condition_type' => 'EmailAddress',
                        'op' => 'is',
                        'value' => $abandonment->customer_email,
                    ],
                ],
            ],
        ],
        'settings' => [
            'subject_line' => $this->get_recovery_subject($discount, $sequence_index),
            'preview_text' => 'Complete your booking and save!',
            'from_name' => get_bloginfo('name'),
            'reply_to' => get_option('admin_email'),
        ],
    ];

    $campaign = $this->mc_api->createCampaign($campaign_data);

    // Set campaign content
    $content = $this->render_recovery_email_content(
        $abandonment,
        $recovery_link,
        $discount
    );

    $this->mc_api->setCampaignContent($campaign['id'], [
        'html' => $content,
    ]);

    // Send campaign
    $this->mc_api->sendCampaign($campaign['id']);

    // Update abandonment record
    $this->update_abandonment_recovery_status($abandonment_id, $sequence_index);
}
```

---

## 10. Win-Back Campaigns

### Identify Dormant Customers
```php
public function identify_dormant_customers() {
    global $wpdb;

    $threshold_days = $this->settings['dormant_threshold_days'];

    // Find customers who haven't booked in X days
    $dormant = $wpdb->get_results($wpdb->prepare("
        SELECT DISTINCT c.*
        FROM {$wpdb->prefix}bookingx_customers c
        INNER JOIN {$wpdb->prefix}bookingx_bookings b ON c.id = b.customer_id
        WHERE b.customer_id IN (
            SELECT customer_id
            FROM {$wpdb->prefix}bookingx_bookings
            GROUP BY customer_id
            HAVING MAX(created_at) < DATE_SUB(NOW(), INTERVAL %d DAY)
            AND MIN(created_at) < DATE_SUB(NOW(), INTERVAL %d DAY)
        )
    ", $threshold_days, $threshold_days + 90)); // Had at least one booking before

    return $dormant;
}
```

### Create Win-Back Segment
```php
public function create_winback_segment() {
    $list_id = $this->settings['default_list_id'];
    $dormant_customers = $this->identify_dormant_customers();

    if (empty($dormant_customers)) {
        return false;
    }

    // Tag dormant customers
    foreach ($dormant_customers as $customer) {
        $subscriber_hash = md5(strtolower($customer->email));

        // Add dormant tag
        $this->mc_api->updateListMemberTags(
            $list_id,
            $subscriber_hash,
            [
                'tags' => [
                    ['name' => 'dormant', 'status' => 'active'],
                    ['name' => 'winback_target', 'status' => 'active'],
                ],
            ]
        );
    }

    // Create segment with dormant tag
    $segment = $this->mc_api->createListSegment($list_id, [
        'name' => 'Win-Back Campaign - Dormant Customers',
        'static_segment' => [],
        'options' => [
            'match' => 'all',
            'conditions' => [
                [
                    'condition_type' => 'StaticSegment',
                    'field' => 'static_segment',
                    'op' => 'static_is',
                    'value' => array_map(function($c) {
                        return md5(strtolower($c->email));
                    }, $dormant_customers),
                ],
            ],
        ],
    ]);

    return $segment;
}
```

### Send Win-Back Campaign
```php
public function send_winback_campaign($segment_id) {
    $discount = $this->settings['winback_discount_percent'];

    $campaign = $this->mc_api->createCampaign([
        'type' => 'regular',
        'recipients' => [
            'list_id' => $this->settings['default_list_id'],
            'segment_opts' => [
                'saved_segment_id' => $segment_id,
            ],
        ],
        'settings' => [
            'subject_line' => sprintf('We miss you! Come back and save %d%%', $discount),
            'preview_text' => 'Special offer just for you',
            'from_name' => get_bloginfo('name'),
            'reply_to' => get_option('admin_email'),
        ],
    ]);

    // Set content
    $content = $this->render_winback_email_content($discount);
    $this->mc_api->setCampaignContent($campaign['id'], ['html' => $content]);

    // Schedule campaign
    $send_time = strtotime('+1 day 10:00:00'); // Next day at 10 AM
    $this->mc_api->scheduleCampaign($campaign['id'], [
        'schedule_time' => date('Y-m-d\TH:i:s\Z', $send_time),
    ]);

    return $campaign['id'];
}
```

---

## 11. Automation Workflows

### Welcome Series Automation
```php
public function create_welcome_automation() {
    $automation = $this->mc_api->createAutomation([
        'recipients' => [
            'list_id' => $this->settings['default_list_id'],
        ],
        'trigger_settings' => [
            'workflow_type' => 'emailAdded',
        ],
        'settings' => [
            'title' => 'Welcome Series - New Customers',
            'from_name' => get_bloginfo('name'),
            'reply_to' => get_option('admin_email'),
        ],
    ]);

    // Email 1: Immediate welcome
    $this->add_automation_email($automation['id'], [
        'delay' => [
            'type' => 'now',
        ],
        'settings' => [
            'subject_line' => 'Welcome to ' . get_bloginfo('name'),
        ],
        'content' => $this->render_welcome_email_1(),
    ]);

    // Email 2: After 3 days - services overview
    $this->add_automation_email($automation['id'], [
        'delay' => [
            'type' => 'day',
            'amount' => 3,
        ],
        'settings' => [
            'subject_line' => 'Discover our services',
        ],
        'content' => $this->render_welcome_email_2(),
    ]);

    // Email 3: After 7 days - special offer
    $this->add_automation_email($automation['id'], [
        'delay' => [
            'type' => 'day',
            'amount' => 7,
        ],
        'settings' => [
            'subject_line' => 'Special offer for new customers',
        ],
        'content' => $this->render_welcome_email_3(),
    ]);

    return $automation['id'];
}
```

---

## 12. Security Considerations

### Data Security
- **API Key Encryption:** Secure storage of API keys
- **HTTPS Required:** All API calls over SSL
- **Data Validation:** Sanitize all subscriber data
- **Unsubscribe Management:** Honor opt-out requests
- **GDPR Compliance:** Data export and deletion support

### Privacy & Compliance
- **Double Opt-in:** Optional confirmation email
- **Consent Tracking:** Record marketing consent
- **Unsubscribe Links:** Include in all emails
- **Data Retention:** Configurable retention policies
- **Audit Logging:** Track all sync activities

---

## 13. Testing Strategy

### Unit Tests
```php
- test_subscriber_creation()
- test_subscriber_update()
- test_tag_assignment()
- test_segment_creation()
- test_campaign_creation()
- test_automation_trigger()
- test_merge_field_mapping()
- test_abandoned_booking_tracking()
```

### Integration Tests
```php
- test_complete_subscriber_sync()
- test_campaign_send_flow()
- test_automation_sequence()
- test_recovery_email_sequence()
- test_winback_campaign()
- test_rfm_segmentation()
```

---

## 14. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] API integration
- [ ] Settings page
- [ ] List management

### Phase 2: Subscriber Management (Week 3-4)
- [ ] Subscriber sync
- [ ] Merge field mapping
- [ ] Tag management
- [ ] Batch operations

### Phase 3: Campaigns & Segments (Week 5-6)
- [ ] Campaign creation
- [ ] Segment builder
- [ ] RFM segmentation
- [ ] Targeted campaigns

### Phase 4: Automation (Week 7-8)
- [ ] Welcome series
- [ ] Post-booking automation
- [ ] Review requests
- [ ] Birthday emails

### Phase 5: Advanced Features (Week 9-10)
- [ ] Abandoned booking recovery
- [ ] Win-back campaigns
- [ ] Analytics integration
- [ ] Reporting dashboard

### Phase 6: Testing & Launch (Week 11-12)
- [ ] Testing
- [ ] Documentation
- [ ] QA and launch

**Total Timeline:** 12 weeks (3 months)

---

## 15. Success Metrics

### Technical Metrics
- Subscriber sync success rate > 99%
- Campaign delivery rate > 98%
- Automation trigger success > 97%
- API response time < 1 second

### Business Metrics
- Email open rate > 25%
- Click-through rate > 3%
- Abandonment recovery rate > 15%
- Win-back success rate > 10%
- Customer retention improvement > 20%

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
