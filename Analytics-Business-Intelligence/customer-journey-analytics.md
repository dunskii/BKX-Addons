# Customer Journey Analytics - Development Documentation

## 1. Overview

**Add-on Name:** Customer Journey Analytics
**Price:** $119
**Category:** Analytics & Business Intelligence
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Advanced customer behavior tracking and booking funnel analysis with journey mapping, touchpoint analytics, conversion optimization, and behavioral insights. Track every customer interaction from first visit to booking completion with comprehensive funnel visualization and drop-off analysis.

### Value Proposition
- Complete customer journey mapping
- Booking funnel visualization and optimization
- Multi-channel attribution tracking
- Behavior pattern recognition
- Drop-off point identification
- Conversion rate optimization
- Customer touchpoint analysis
- Session replay and heatmaps
- Path analysis and flow visualization
- A/B test integration

---

## 2. Features & Requirements

### Core Features
1. **Journey Mapping**
   - Visual customer journey flows
   - Touchpoint identification
   - Path analysis and visualization
   - Multi-session tracking
   - Cross-device journey tracking
   - Journey duration analysis
   - Entry and exit point tracking
   - Journey segmentation

2. **Booking Funnel Analysis**
   - Funnel stage tracking
   - Conversion rate by stage
   - Drop-off analysis
   - Time spent per stage
   - Funnel comparison
   - Cohort funnel analysis
   - Mobile vs desktop funnels
   - A/B test funnel variants

3. **Behavior Tracking**
   - Page view tracking
   - Click tracking
   - Form interaction tracking
   - Scroll depth analysis
   - Time on page
   - Navigation patterns
   - Search behavior
   - Service browsing patterns

4. **Attribution Tracking**
   - Multi-touch attribution
   - First-touch attribution
   - Last-touch attribution
   - Linear attribution
   - Time-decay attribution
   - Channel attribution
   - Campaign attribution
   - UTM parameter tracking

5. **Session Analytics**
   - Session recording
   - Heatmap generation
   - Click maps
   - Scroll maps
   - Attention maps
   - Device and browser tracking
   - Geographic tracking
   - Referrer analysis

6. **Conversion Optimization**
   - Conversion bottleneck identification
   - Form abandonment tracking
   - Error tracking
   - User experience scoring
   - Mobile optimization insights
   - Loading time impact
   - Recommendation engine

### User Roles & Permissions
- **Admin:** Full access, configuration
- **Marketing Manager:** View all analytics, create reports
- **Analyst:** Advanced analytics, custom queries
- **Manager:** Standard analytics views
- **Staff:** Limited journey views

---

## 3. Technical Specifications

### Technology Stack
- **Frontend Tracking:** Custom JavaScript SDK
- **Analytics Engine:** PHP + MySQL
- **Visualization:** D3.js, Sankey diagrams, Flow charts
- **Heatmaps:** Hotjar-style custom implementation
- **Session Replay:** rrweb library
- **Chart Library:** Chart.js, Apache ECharts
- **Data Storage:** Custom fact tables + time-series data
- **Real-time Processing:** AJAX polling / WebSockets (optional)

### Dependencies
- BookingX Core 2.0+
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- PHP Extensions: json, mysqli, gd
- Modern browser with JavaScript enabled
- Adequate storage for session data

### JavaScript Tracking SDK
```javascript
// bookingx-analytics.js - Client-side tracking library
{
  "name": "@bookingx/analytics-sdk",
  "version": "1.0.0",
  "dependencies": {
    "rrweb": "^2.0.0",
    "hotjar-js": "^1.0.0",
    "fingerprint2": "^2.1.4"
  }
}
```

### API Integration Points
```php
// Custom REST API endpoints
- POST /bookingx/v1/analytics/track-event
- POST /bookingx/v1/analytics/track-pageview
- POST /bookingx/v1/analytics/track-session
- GET  /bookingx/v1/analytics/journey-map/{customer_id}
- GET  /bookingx/v1/analytics/funnel-analysis
- GET  /bookingx/v1/analytics/conversion-metrics
- GET  /bookingx/v1/analytics/attribution-report
- GET  /bookingx/v1/analytics/session-replay/{session_id}
- GET  /bookingx/v1/analytics/heatmap-data
- POST /bookingx/v1/analytics/record-interaction
```

---

## 4. Architecture & Design

### System Architecture
```
┌────────────────────────────────────────────┐
│        Customer Frontend (Browser)         │
│  - Tracking SDK                            │
│  - Event Listeners                         │
│  - Session Recording                       │
└────────────┬───────────────────────────────┘
             │ (AJAX/Beacon API)
             ▼
┌────────────────────────────────────────────┐
│      Analytics Collection Endpoint         │
│  - Event Ingestion                         │
│  - Data Validation                         │
│  - Real-time Processing                    │
└────────────┬───────────────────────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│        Analytics Processing Engine         │
│  - Journey Builder                         │
│  - Funnel Calculator                       │
│  - Attribution Model                       │
│  - Behavior Analyzer                       │
└────────────┬───────────────────────────────┘
             │
             ├──────────┬──────────┬──────────┐
             ▼          ▼          ▼          ▼
┌──────────────┐ ┌───────────┐ ┌──────────┐ ┌────────────┐
│   Journey    │ │  Funnel   │ │  Heatmap │ │ Attribution│
│   Mapper     │ │  Analyzer │ │Generator │ │   Engine   │
└──────────────┘ └───────────┘ └──────────┘ └────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│       Analytics Data Warehouse             │
│  - Events Table                            │
│  - Sessions Table                          │
│  - Journey Table                           │
│  - Funnel Metrics                          │
└────────────────────────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\CustomerJourneyAnalytics;

class AnalyticsTracker {
    - track_event($event_data)
    - track_pageview($page_data)
    - track_interaction($interaction_data)
    - start_session()
    - end_session()
    - identify_visitor()
}

class JourneyMapper {
    - build_customer_journey($customer_id)
    - get_journey_stages()
    - calculate_journey_duration()
    - identify_touchpoints()
    - map_journey_flow()
    - segment_journeys($criteria)
}

class FunnelAnalyzer {
    - define_funnel_stages()
    - calculate_conversion_rates()
    - identify_drop_offs()
    - analyze_funnel_by_segment()
    - compare_funnels($funnel1, $funnel2)
    - calculate_time_per_stage()
}

class BehaviorAnalyzer {
    - track_page_views()
    - track_clicks()
    - track_scroll_depth()
    - analyze_navigation_patterns()
    - identify_behavior_segments()
    - calculate_engagement_score()
}

class AttributionEngine {
    - calculate_multi_touch_attribution()
    - calculate_first_touch()
    - calculate_last_touch()
    - calculate_linear_attribution()
    - calculate_time_decay_attribution()
    - assign_channel_credit()
}

class SessionReplayManager {
    - record_session()
    - store_session_data()
    - retrieve_session($session_id)
    - generate_replay_url()
    - compress_session_data()
}

class HeatmapGenerator {
    - generate_click_heatmap($page_url)
    - generate_scroll_heatmap($page_url)
    - generate_attention_map($page_url)
    - aggregate_interaction_data()
    - render_heatmap_overlay()
}

class ConversionOptimizer {
    - identify_bottlenecks()
    - suggest_improvements()
    - calculate_optimization_impact()
    - track_form_abandonment()
    - analyze_error_patterns()
}
```

---

## 5. Database Schema

### Table: `bkx_analytics_events`
```sql
CREATE TABLE bkx_analytics_events (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    visitor_id VARCHAR(100) NOT NULL,
    customer_id BIGINT(20) UNSIGNED,
    event_type VARCHAR(50) NOT NULL,
    event_name VARCHAR(100) NOT NULL,
    event_category VARCHAR(50),
    page_url VARCHAR(500),
    page_title VARCHAR(255),
    referrer_url VARCHAR(500),
    event_data LONGTEXT,
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    utm_term VARCHAR(100),
    utm_content VARCHAR(100),
    device_type VARCHAR(20),
    browser VARCHAR(50),
    os VARCHAR(50),
    screen_resolution VARCHAR(20),
    user_agent TEXT,
    ip_address VARCHAR(45),
    country VARCHAR(2),
    city VARCHAR(100),
    created_at DATETIME NOT NULL,
    INDEX session_id_idx (session_id),
    INDEX visitor_id_idx (visitor_id),
    INDEX customer_id_idx (customer_id),
    INDEX event_type_idx (event_type),
    INDEX created_at_idx (created_at),
    INDEX utm_campaign_idx (utm_campaign)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_analytics_sessions`
```sql
CREATE TABLE bkx_analytics_sessions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL UNIQUE,
    visitor_id VARCHAR(100) NOT NULL,
    customer_id BIGINT(20) UNSIGNED,
    session_start DATETIME NOT NULL,
    session_end DATETIME,
    duration_seconds INT,
    page_views INT DEFAULT 0,
    interactions INT DEFAULT 0,
    entry_page VARCHAR(500),
    exit_page VARCHAR(500),
    referrer_url VARCHAR(500),
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    device_type VARCHAR(20),
    browser VARCHAR(50),
    os VARCHAR(50),
    country VARCHAR(2),
    city VARCHAR(100),
    converted TINYINT(1) DEFAULT 0,
    booking_id BIGINT(20) UNSIGNED,
    session_recording LONGTEXT,
    created_at DATETIME NOT NULL,
    INDEX session_id_idx (session_id),
    INDEX visitor_id_idx (visitor_id),
    INDEX customer_id_idx (customer_id),
    INDEX converted_idx (converted),
    INDEX utm_campaign_idx (utm_campaign)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_analytics_journeys`
```sql
CREATE TABLE bkx_analytics_journeys (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    visitor_id VARCHAR(100) NOT NULL,
    journey_start DATETIME NOT NULL,
    journey_end DATETIME,
    total_sessions INT DEFAULT 0,
    total_touchpoints INT DEFAULT 0,
    journey_stages LONGTEXT,
    touchpoint_sequence LONGTEXT,
    channel_sequence LONGTEXT,
    total_duration_seconds INT,
    converted TINYINT(1) DEFAULT 0,
    booking_id BIGINT(20) UNSIGNED,
    first_touch_channel VARCHAR(100),
    last_touch_channel VARCHAR(100),
    journey_path TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX visitor_id_idx (visitor_id),
    INDEX converted_idx (converted),
    INDEX journey_start_idx (journey_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_analytics_funnel_stages`
```sql
CREATE TABLE bkx_analytics_funnel_stages (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    funnel_name VARCHAR(100) NOT NULL,
    stage_order INT NOT NULL,
    stage_name VARCHAR(100) NOT NULL,
    stage_type VARCHAR(50) NOT NULL,
    matching_rule LONGTEXT NOT NULL,
    is_required TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY funnel_stage_idx (funnel_name, stage_order),
    INDEX funnel_name_idx (funnel_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_analytics_funnel_metrics`
```sql
CREATE TABLE bkx_analytics_funnel_metrics (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    funnel_name VARCHAR(100) NOT NULL,
    metric_date DATE NOT NULL,
    stage_name VARCHAR(100) NOT NULL,
    stage_order INT NOT NULL,
    entries INT DEFAULT 0,
    exits INT DEFAULT 0,
    conversions INT DEFAULT 0,
    drop_offs INT DEFAULT 0,
    conversion_rate DECIMAL(5,2),
    avg_time_seconds INT,
    device_type VARCHAR(20),
    traffic_source VARCHAR(100),
    created_at DATETIME NOT NULL,
    UNIQUE KEY funnel_metric_idx (funnel_name, metric_date, stage_order, device_type),
    INDEX funnel_name_idx (funnel_name),
    INDEX metric_date_idx (metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_analytics_page_interactions`
```sql
CREATE TABLE bkx_analytics_page_interactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    page_url VARCHAR(500) NOT NULL,
    interaction_type VARCHAR(50) NOT NULL,
    element_selector VARCHAR(255),
    element_text VARCHAR(255),
    element_position VARCHAR(50),
    click_x INT,
    click_y INT,
    scroll_depth_percent INT,
    time_on_page_seconds INT,
    created_at DATETIME NOT NULL,
    INDEX session_id_idx (session_id),
    INDEX page_url_idx (page_url),
    INDEX interaction_type_idx (interaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_analytics_attribution`
```sql
CREATE TABLE bkx_analytics_attribution (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    journey_id BIGINT(20) UNSIGNED NOT NULL,
    channel VARCHAR(100) NOT NULL,
    touchpoint_order INT NOT NULL,
    touchpoint_timestamp DATETIME NOT NULL,
    attribution_model VARCHAR(50) NOT NULL,
    attribution_credit DECIMAL(5,4) NOT NULL,
    revenue_attributed DECIMAL(10,2),
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    created_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX customer_id_idx (customer_id),
    INDEX journey_id_idx (journey_id),
    INDEX channel_idx (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_analytics_heatmap_data`
```sql
CREATE TABLE bkx_analytics_heatmap_data (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_url VARCHAR(500) NOT NULL,
    heatmap_type VARCHAR(20) NOT NULL,
    coordinate_x INT NOT NULL,
    coordinate_y INT NOT NULL,
    interaction_count INT DEFAULT 1,
    viewport_width INT,
    viewport_height INT,
    date_collected DATE NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX page_url_idx (page_url),
    INDEX heatmap_type_idx (heatmap_type),
    INDEX date_collected_idx (date_collected)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_analytics_form_abandonment`
```sql
CREATE TABLE bkx_analytics_form_abandonment (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    form_id VARCHAR(100) NOT NULL,
    form_url VARCHAR(500) NOT NULL,
    fields_total INT,
    fields_completed INT,
    last_field_interacted VARCHAR(100),
    time_spent_seconds INT,
    abandonment_reason VARCHAR(100),
    error_encountered VARCHAR(255),
    device_type VARCHAR(20),
    recovered TINYINT(1) DEFAULT 0,
    abandoned_at DATETIME NOT NULL,
    recovered_at DATETIME,
    INDEX session_id_idx (session_id),
    INDEX form_id_idx (form_id),
    INDEX recovered_idx (recovered)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    'enable_tracking' => true,
    'enable_session_recording' => true,
    'enable_heatmaps' => true,
    'enable_journey_mapping' => true,

    // Tracking settings
    'track_pageviews' => true,
    'track_clicks' => true,
    'track_scroll_depth' => true,
    'track_form_interactions' => true,
    'track_custom_events' => true,

    // Session settings
    'session_timeout_minutes' => 30,
    'enable_cross_device_tracking' => true,
    'anonymize_ip_addresses' => true,
    'respect_do_not_track' => true,

    // Recording settings
    'record_session_replay' => true,
    'session_replay_sample_rate' => 10, // Record 10% of sessions
    'max_recording_duration_minutes' => 30,
    'compress_recordings' => true,

    // Heatmap settings
    'heatmap_resolution' => 10, // pixels
    'min_interactions_for_heatmap' => 100,
    'heatmap_data_retention_days' => 90,

    // Funnel settings
    'default_funnel_stages' => [
        'landing_page',
        'service_selection',
        'datetime_selection',
        'customer_details',
        'payment',
        'confirmation'
    ],
    'track_partial_conversions' => true,

    // Attribution settings
    'attribution_window_days' => 30,
    'default_attribution_model' => 'multi_touch',
    'attribution_models_enabled' => [
        'first_touch',
        'last_touch',
        'linear',
        'time_decay',
        'multi_touch'
    ],

    // Privacy settings
    'gdpr_compliance_mode' => true,
    'cookie_consent_required' => true,
    'data_retention_days' => 365,
    'exclude_admin_tracking' => true,
    'exclude_ip_addresses' => [],

    // Performance settings
    'async_tracking' => true,
    'batch_events' => true,
    'batch_size' => 10,
    'data_sampling_rate' => 100, // 100% = track all

    // Export settings
    'enable_raw_data_export' => true,
    'enable_api_access' => false,
]
```

---

## 7. Journey Mapping & Visualization

### Journey Builder
```php
class JourneyMapper {

    public function build_customer_journey($customer_id) {
        global $wpdb;

        // Get all sessions for customer
        $sessions = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}bkx_analytics_sessions
            WHERE customer_id = %d
            OR visitor_id IN (
                SELECT DISTINCT visitor_id
                FROM {$wpdb->prefix}bkx_analytics_sessions
                WHERE customer_id = %d
            )
            ORDER BY session_start ASC
        ", $customer_id, $customer_id));

        // Get all events for these sessions
        $session_ids = array_column($sessions, 'session_id');
        $placeholders = implode(',', array_fill(0, count($session_ids), '%s'));

        $events = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}bkx_analytics_events
            WHERE session_id IN ($placeholders)
            ORDER BY created_at ASC
        ", ...$session_ids));

        // Build journey structure
        $journey = [
            'customer_id' => $customer_id,
            'start_date' => $sessions[0]->session_start,
            'end_date' => end($sessions)->session_end,
            'total_sessions' => count($sessions),
            'total_events' => count($events),
            'touchpoints' => [],
            'stages' => [],
            'channels' => [],
            'conversion' => null
        ];

        // Analyze touchpoints
        $touchpoints = $this->extract_touchpoints($sessions, $events);
        $journey['touchpoints'] = $touchpoints;

        // Map to journey stages
        $stages = $this->map_to_stages($touchpoints);
        $journey['stages'] = $stages;

        // Extract channel sequence
        $journey['channels'] = $this->extract_channel_sequence($sessions);

        // Check for conversion
        $journey['conversion'] = $this->check_conversion($customer_id);

        return $journey;
    }

    private function extract_touchpoints($sessions, $events) {
        $touchpoints = [];

        foreach ($events as $event) {
            $touchpoint = [
                'timestamp' => $event->created_at,
                'type' => $event->event_type,
                'name' => $event->event_name,
                'page' => $event->page_url,
                'channel' => $this->determine_channel($event),
                'device' => $event->device_type,
                'data' => json_decode($event->event_data, true)
            ];

            $touchpoints[] = $touchpoint;
        }

        return $touchpoints;
    }

    private function map_to_stages($touchpoints) {
        $stages = [
            'awareness' => [],
            'consideration' => [],
            'decision' => [],
            'action' => [],
            'retention' => []
        ];

        foreach ($touchpoints as $touchpoint) {
            $stage = $this->classify_stage($touchpoint);
            $stages[$stage][] = $touchpoint;
        }

        return $stages;
    }

    private function classify_stage($touchpoint) {
        // Stage classification logic
        $url = $touchpoint['page'];
        $event_type = $touchpoint['type'];

        if (strpos($url, 'services') !== false || $event_type === 'page_view') {
            return 'awareness';
        } elseif (strpos($url, 'booking') !== false && $event_type === 'page_view') {
            return 'consideration';
        } elseif ($event_type === 'form_interaction' || $event_type === 'datetime_selected') {
            return 'decision';
        } elseif ($event_type === 'booking_completed' || $event_type === 'payment_success') {
            return 'action';
        } else {
            return 'consideration';
        }
    }

    private function extract_channel_sequence($sessions) {
        $channels = [];

        foreach ($sessions as $session) {
            $channel = $this->determine_channel_from_session($session);
            $channels[] = [
                'channel' => $channel,
                'timestamp' => $session->session_start,
                'utm_source' => $session->utm_source,
                'utm_medium' => $session->utm_medium,
                'utm_campaign' => $session->utm_campaign
            ];
        }

        return $channels;
    }

    private function determine_channel_from_session($session) {
        if (!empty($session->utm_source)) {
            return $session->utm_source;
        }

        if (!empty($session->referrer_url)) {
            if (strpos($session->referrer_url, 'google') !== false) {
                return 'organic_search';
            } elseif (strpos($session->referrer_url, 'facebook') !== false) {
                return 'social_facebook';
            } elseif (strpos($session->referrer_url, 'instagram') !== false) {
                return 'social_instagram';
            }
            return 'referral';
        }

        return 'direct';
    }

    public function generate_sankey_data($customer_ids) {
        // Generate Sankey diagram data for journey visualization
        $nodes = [];
        $links = [];
        $node_index = [];

        foreach ($customer_ids as $customer_id) {
            $journey = $this->build_customer_journey($customer_id);

            $previous_node = null;
            foreach ($journey['touchpoints'] as $touchpoint) {
                $node_name = $touchpoint['page'];

                // Add node if not exists
                if (!isset($node_index[$node_name])) {
                    $node_index[$node_name] = count($nodes);
                    $nodes[] = ['name' => $node_name];
                }

                // Add link from previous node
                if ($previous_node !== null) {
                    $link_key = $previous_node . '->' . $node_name;
                    if (!isset($links[$link_key])) {
                        $links[$link_key] = [
                            'source' => $node_index[$previous_node],
                            'target' => $node_index[$node_name],
                            'value' => 0
                        ];
                    }
                    $links[$link_key]['value']++;
                }

                $previous_node = $node_name;
            }
        }

        return [
            'nodes' => $nodes,
            'links' => array_values($links)
        ];
    }
}
```

---

## 8. Funnel Analysis

### Funnel Calculator
```php
class FunnelAnalyzer {

    public function analyze_funnel($funnel_name, $date_range) {
        global $wpdb;

        // Get funnel stages
        $stages = $this->get_funnel_stages($funnel_name);

        if (empty($stages)) {
            return null;
        }

        $funnel_data = [
            'funnel_name' => $funnel_name,
            'date_range' => $date_range,
            'stages' => [],
            'overall_conversion_rate' => 0,
            'total_entries' => 0,
            'total_completions' => 0
        ];

        // Analyze each stage
        $previous_stage_sessions = null;

        foreach ($stages as $stage_index => $stage) {
            $stage_metrics = $this->calculate_stage_metrics(
                $stage,
                $date_range,
                $previous_stage_sessions
            );

            $funnel_data['stages'][] = [
                'order' => $stage->stage_order,
                'name' => $stage->stage_name,
                'entries' => $stage_metrics['entries'],
                'exits' => $stage_metrics['exits'],
                'conversions' => $stage_metrics['conversions'],
                'drop_offs' => $stage_metrics['drop_offs'],
                'conversion_rate' => $stage_metrics['conversion_rate'],
                'avg_time_seconds' => $stage_metrics['avg_time'],
                'drop_off_rate' => $stage_metrics['drop_off_rate']
            ];

            // Track sessions that made it through this stage
            $previous_stage_sessions = $stage_metrics['session_ids'];

            if ($stage_index === 0) {
                $funnel_data['total_entries'] = $stage_metrics['entries'];
            }

            if ($stage_index === count($stages) - 1) {
                $funnel_data['total_completions'] = $stage_metrics['conversions'];
            }
        }

        // Calculate overall conversion rate
        $funnel_data['overall_conversion_rate'] =
            ($funnel_data['total_entries'] > 0) ?
            round(($funnel_data['total_completions'] / $funnel_data['total_entries']) * 100, 2) :
            0;

        return $funnel_data;
    }

    private function calculate_stage_metrics($stage, $date_range, $previous_sessions = null) {
        global $wpdb;

        $matching_rule = json_decode($stage->matching_rule, true);

        // Build WHERE clause based on matching rule
        $where_conditions = $this->build_matching_conditions($matching_rule);

        // Get sessions that reached this stage
        $query = "
            SELECT DISTINCT session_id, MIN(created_at) as first_interaction
            FROM {$wpdb->prefix}bkx_analytics_events
            WHERE created_at BETWEEN %s AND %s
            AND $where_conditions
        ";

        if ($previous_sessions !== null && !empty($previous_sessions)) {
            $placeholders = implode(',', array_fill(0, count($previous_sessions), '%s'));
            $query .= " AND session_id IN ($placeholders)";
        }

        $query .= " GROUP BY session_id";

        $params = [$date_range['start'], $date_range['end']];
        if ($previous_sessions !== null && !empty($previous_sessions)) {
            $params = array_merge($params, $previous_sessions);
        }

        $stage_sessions = $wpdb->get_results($wpdb->prepare($query, ...$params));

        $entries = count($stage_sessions);
        $session_ids = array_column($stage_sessions, 'session_id');

        // Calculate conversions (sessions that made it to next stage)
        // This would be determined by the next stage's entries
        // For now, we'll consider all entries as potential conversions

        // Calculate drop-offs
        $expected_sessions = ($previous_sessions !== null) ? count($previous_sessions) : $entries;
        $drop_offs = $expected_sessions - $entries;
        $drop_off_rate = ($expected_sessions > 0) ?
            round(($drop_offs / $expected_sessions) * 100, 2) : 0;

        // Calculate average time spent on stage
        $avg_time = $this->calculate_avg_time_on_stage($session_ids, $stage);

        return [
            'entries' => $entries,
            'exits' => 0, // Would need next stage data
            'conversions' => $entries, // Simplified
            'drop_offs' => $drop_offs,
            'drop_off_rate' => $drop_off_rate,
            'conversion_rate' => ($expected_sessions > 0) ?
                round(($entries / $expected_sessions) * 100, 2) : 100,
            'avg_time' => $avg_time,
            'session_ids' => $session_ids
        ];
    }

    private function build_matching_conditions($matching_rule) {
        $conditions = [];

        if (isset($matching_rule['event_type'])) {
            $conditions[] = "event_type = '" . esc_sql($matching_rule['event_type']) . "'";
        }

        if (isset($matching_rule['page_url_contains'])) {
            $conditions[] = "page_url LIKE '%" . esc_sql($matching_rule['page_url_contains']) . "%'";
        }

        if (isset($matching_rule['event_name'])) {
            $conditions[] = "event_name = '" . esc_sql($matching_rule['event_name']) . "'";
        }

        return implode(' AND ', $conditions);
    }

    private function calculate_avg_time_on_stage($session_ids, $stage) {
        if (empty($session_ids)) {
            return 0;
        }

        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($session_ids), '%s'));

        $avg_time = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(time_on_page_seconds)
            FROM {$wpdb->prefix}bkx_analytics_page_interactions
            WHERE session_id IN ($placeholders)
        ", ...$session_ids));

        return round($avg_time ?? 0);
    }

    public function compare_funnels($funnel1_data, $funnel2_data) {
        $comparison = [
            'funnel1' => $funnel1_data['funnel_name'],
            'funnel2' => $funnel2_data['funnel_name'],
            'conversion_rate_diff' => $funnel1_data['overall_conversion_rate'] -
                                      $funnel2_data['overall_conversion_rate'],
            'stage_comparison' => []
        ];

        $max_stages = max(count($funnel1_data['stages']), count($funnel2_data['stages']));

        for ($i = 0; $i < $max_stages; $i++) {
            $stage1 = $funnel1_data['stages'][$i] ?? null;
            $stage2 = $funnel2_data['stages'][$i] ?? null;

            if ($stage1 && $stage2) {
                $comparison['stage_comparison'][] = [
                    'stage' => $stage1['name'],
                    'conversion_diff' => $stage1['conversion_rate'] - $stage2['conversion_rate'],
                    'drop_off_diff' => $stage1['drop_off_rate'] - $stage2['drop_off_rate'],
                    'time_diff' => $stage1['avg_time_seconds'] - $stage2['avg_time_seconds']
                ];
            }
        }

        return $comparison;
    }
}
```

---

## 9. Attribution Modeling

### Attribution Calculator
```php
class AttributionEngine {

    public function calculate_attribution($booking_id, $model = 'multi_touch') {
        // Get customer journey for this booking
        $journey = $this->get_booking_journey($booking_id);

        if (!$journey || empty($journey['touchpoints'])) {
            return null;
        }

        $attribution_data = [];

        switch ($model) {
            case 'first_touch':
                $attribution_data = $this->first_touch_attribution($journey);
                break;

            case 'last_touch':
                $attribution_data = $this->last_touch_attribution($journey);
                break;

            case 'linear':
                $attribution_data = $this->linear_attribution($journey);
                break;

            case 'time_decay':
                $attribution_data = $this->time_decay_attribution($journey);
                break;

            case 'multi_touch':
            default:
                $attribution_data = $this->multi_touch_attribution($journey);
                break;
        }

        // Store attribution data
        $this->store_attribution($booking_id, $attribution_data, $model);

        return $attribution_data;
    }

    private function first_touch_attribution($journey) {
        $first_touchpoint = $journey['touchpoints'][0];

        return [
            [
                'channel' => $first_touchpoint['channel'],
                'touchpoint_order' => 1,
                'credit' => 1.0,
                'timestamp' => $first_touchpoint['timestamp']
            ]
        ];
    }

    private function last_touch_attribution($journey) {
        $last_touchpoint = end($journey['touchpoints']);

        return [
            [
                'channel' => $last_touchpoint['channel'],
                'touchpoint_order' => count($journey['touchpoints']),
                'credit' => 1.0,
                'timestamp' => $last_touchpoint['timestamp']
            ]
        ];
    }

    private function linear_attribution($journey) {
        $touchpoint_count = count($journey['touchpoints']);
        $credit_per_touchpoint = 1.0 / $touchpoint_count;

        $attribution = [];

        foreach ($journey['touchpoints'] as $index => $touchpoint) {
            $attribution[] = [
                'channel' => $touchpoint['channel'],
                'touchpoint_order' => $index + 1,
                'credit' => $credit_per_touchpoint,
                'timestamp' => $touchpoint['timestamp']
            ];
        }

        return $attribution;
    }

    private function time_decay_attribution($journey) {
        $touchpoint_count = count($journey['touchpoints']);
        $half_life = 7; // 7 days half-life

        // Calculate decay weights
        $weights = [];
        $total_weight = 0;

        $conversion_time = strtotime($journey['end_date']);

        foreach ($journey['touchpoints'] as $touchpoint) {
            $touchpoint_time = strtotime($touchpoint['timestamp']);
            $days_before_conversion = ($conversion_time - $touchpoint_time) / (60 * 60 * 24);

            // Exponential decay: weight = 2^(-days/half_life)
            $weight = pow(2, -$days_before_conversion / $half_life);
            $weights[] = $weight;
            $total_weight += $weight;
        }

        // Normalize weights to sum to 1.0
        $attribution = [];

        foreach ($journey['touchpoints'] as $index => $touchpoint) {
            $credit = ($total_weight > 0) ? $weights[$index] / $total_weight : 0;

            $attribution[] = [
                'channel' => $touchpoint['channel'],
                'touchpoint_order' => $index + 1,
                'credit' => $credit,
                'timestamp' => $touchpoint['timestamp']
            ];
        }

        return $attribution;
    }

    private function multi_touch_attribution($journey) {
        // Position-based: 40% first, 40% last, 20% distributed among middle
        $touchpoint_count = count($journey['touchpoints']);

        if ($touchpoint_count === 1) {
            return $this->first_touch_attribution($journey);
        }

        if ($touchpoint_count === 2) {
            return [
                [
                    'channel' => $journey['touchpoints'][0]['channel'],
                    'touchpoint_order' => 1,
                    'credit' => 0.5,
                    'timestamp' => $journey['touchpoints'][0]['timestamp']
                ],
                [
                    'channel' => $journey['touchpoints'][1]['channel'],
                    'touchpoint_order' => 2,
                    'credit' => 0.5,
                    'timestamp' => $journey['touchpoints'][1]['timestamp']
                ]
            ];
        }

        $attribution = [];
        $middle_touchpoints = $touchpoint_count - 2;
        $middle_credit = $middle_touchpoints > 0 ? 0.20 / $middle_touchpoints : 0;

        foreach ($journey['touchpoints'] as $index => $touchpoint) {
            if ($index === 0) {
                $credit = 0.40; // First touch
            } elseif ($index === $touchpoint_count - 1) {
                $credit = 0.40; // Last touch
            } else {
                $credit = $middle_credit; // Middle touches
            }

            $attribution[] = [
                'channel' => $touchpoint['channel'],
                'touchpoint_order' => $index + 1,
                'credit' => $credit,
                'timestamp' => $touchpoint['timestamp']
            ];
        }

        return $attribution;
    }

    public function generate_attribution_report($date_range, $model = 'multi_touch') {
        global $wpdb;

        $report = $wpdb->get_results($wpdb->prepare("
            SELECT
                channel,
                COUNT(DISTINCT booking_id) as conversions,
                SUM(attribution_credit) as total_credit,
                SUM(revenue_attributed) as total_revenue
            FROM {$wpdb->prefix}bkx_analytics_attribution
            WHERE attribution_model = %s
            AND created_at BETWEEN %s AND %s
            GROUP BY channel
            ORDER BY total_credit DESC
        ", $model, $date_range['start'], $date_range['end']));

        return $report;
    }
}
```

---

## 10. Heatmap Generation

### Heatmap Data Processor
```php
class HeatmapGenerator {

    public function generate_click_heatmap($page_url, $date_range = null) {
        global $wpdb;

        $where = "page_url = %s AND interaction_type = 'click'";
        $params = [$page_url];

        if ($date_range) {
            $where .= " AND created_at BETWEEN %s AND %s";
            $params[] = $date_range['start'];
            $params[] = $date_range['end'];
        }

        // Aggregate click data
        $click_data = $wpdb->get_results($wpdb->prepare("
            SELECT
                click_x,
                click_y,
                COUNT(*) as click_count,
                viewport_width,
                viewport_height
            FROM {$wpdb->prefix}bkx_analytics_page_interactions
            WHERE $where
            AND click_x IS NOT NULL
            AND click_y IS NOT NULL
            GROUP BY click_x, click_y, viewport_width, viewport_height
        ", ...$params));

        // Normalize to standard viewport (1920x1080)
        $normalized_data = $this->normalize_coordinates($click_data, 1920, 1080);

        // Generate heatmap grid
        $heatmap_grid = $this->generate_heatmap_grid($normalized_data, 1920, 1080, 10);

        return [
            'page_url' => $page_url,
            'heatmap_type' => 'click',
            'grid_data' => $heatmap_grid,
            'max_intensity' => max(array_column($heatmap_grid, 'intensity')),
            'total_clicks' => array_sum(array_column($click_data, 'click_count'))
        ];
    }

    public function generate_scroll_heatmap($page_url, $date_range = null) {
        global $wpdb;

        $where = "page_url = %s AND scroll_depth_percent IS NOT NULL";
        $params = [$page_url];

        if ($date_range) {
            $where .= " AND created_at BETWEEN %s AND %s";
            $params[] = $date_range['start'];
            $params[] = $date_range['end'];
        }

        $scroll_data = $wpdb->get_results($wpdb->prepare("
            SELECT
                scroll_depth_percent,
                COUNT(*) as user_count
            FROM {$wpdb->prefix}bkx_analytics_page_interactions
            WHERE $where
            GROUP BY scroll_depth_percent
            ORDER BY scroll_depth_percent
        ", ...$params));

        // Calculate fold positions (25%, 50%, 75%, 100%)
        $fold_metrics = [
            '25' => 0,
            '50' => 0,
            '75' => 0,
            '100' => 0
        ];

        $total_users = array_sum(array_column($scroll_data, 'user_count'));

        foreach ($scroll_data as $row) {
            $depth = $row->scroll_depth_percent;

            if ($depth >= 25) $fold_metrics['25'] += $row->user_count;
            if ($depth >= 50) $fold_metrics['50'] += $row->user_count;
            if ($depth >= 75) $fold_metrics['75'] += $row->user_count;
            if ($depth >= 100) $fold_metrics['100'] += $row->user_count;
        }

        // Calculate percentages
        foreach ($fold_metrics as $fold => $count) {
            $fold_metrics[$fold] = ($total_users > 0) ?
                round(($count / $total_users) * 100, 2) : 0;
        }

        return [
            'page_url' => $page_url,
            'heatmap_type' => 'scroll',
            'fold_metrics' => $fold_metrics,
            'scroll_distribution' => $scroll_data,
            'avg_scroll_depth' => $this->calculate_avg_scroll_depth($scroll_data)
        ];
    }

    private function normalize_coordinates($data, $target_width, $target_height) {
        $normalized = [];

        foreach ($data as $point) {
            $scale_x = $target_width / $point->viewport_width;
            $scale_y = $target_height / $point->viewport_height;

            $normalized[] = [
                'x' => round($point->click_x * $scale_x),
                'y' => round($point->click_y * $scale_y),
                'count' => $point->click_count
            ];
        }

        return $normalized;
    }

    private function generate_heatmap_grid($data, $width, $height, $resolution) {
        $grid = [];

        // Initialize grid
        for ($y = 0; $y < $height; $y += $resolution) {
            for ($x = 0; $x < $width; $x += $resolution) {
                $grid["{$x},{$y}"] = [
                    'x' => $x,
                    'y' => $y,
                    'intensity' => 0
                ];
            }
        }

        // Populate grid with click data
        foreach ($data as $point) {
            $grid_x = floor($point['x'] / $resolution) * $resolution;
            $grid_y = floor($point['y'] / $resolution) * $resolution;
            $key = "{$grid_x},{$grid_y}";

            if (isset($grid[$key])) {
                $grid[$key]['intensity'] += $point['count'];
            }
        }

        return array_values($grid);
    }

    private function calculate_avg_scroll_depth($scroll_data) {
        $total_depth = 0;
        $total_users = 0;

        foreach ($scroll_data as $row) {
            $total_depth += $row->scroll_depth_percent * $row->user_count;
            $total_users += $row->user_count;
        }

        return ($total_users > 0) ? round($total_depth / $total_users, 2) : 0;
    }
}
```

---

## 11. Visualization Components

### D3.js Journey Flow Diagram
```javascript
class JourneyFlowVisualization {
    constructor(containerId, journeyData) {
        this.container = d3.select(`#${containerId}`);
        this.data = journeyData;
        this.width = 1000;
        this.height = 600;
        this.init();
    }

    init() {
        const svg = this.container
            .append('svg')
            .attr('width', this.width)
            .attr('height', this.height);

        // Create Sankey diagram
        const sankey = d3.sankey()
            .nodeWidth(20)
            .nodePadding(20)
            .extent([[10, 10], [this.width - 10, this.height - 10]]);

        const graph = sankey({
            nodes: this.data.nodes.map(d => Object.assign({}, d)),
            links: this.data.links.map(d => Object.assign({}, d))
        });

        // Add links
        svg.append('g')
            .selectAll('path')
            .data(graph.links)
            .enter()
            .append('path')
            .attr('d', d3.sankeyLinkHorizontal())
            .attr('stroke', d => this.getLinkColor(d.value))
            .attr('stroke-width', d => Math.max(1, d.width))
            .attr('fill', 'none')
            .attr('opacity', 0.5)
            .on('mouseover', (event, d) => this.showLinkTooltip(event, d))
            .on('mouseout', () => this.hideTooltip());

        // Add nodes
        svg.append('g')
            .selectAll('rect')
            .data(graph.nodes)
            .enter()
            .append('rect')
            .attr('x', d => d.x0)
            .attr('y', d => d.y0)
            .attr('height', d => d.y1 - d.y0)
            .attr('width', d => d.x1 - d.x0)
            .attr('fill', '#4CAF50')
            .attr('opacity', 0.8)
            .on('mouseover', (event, d) => this.showNodeTooltip(event, d))
            .on('mouseout', () => this.hideTooltip());

        // Add labels
        svg.append('g')
            .selectAll('text')
            .data(graph.nodes)
            .enter()
            .append('text')
            .attr('x', d => d.x0 < this.width / 2 ? d.x1 + 6 : d.x0 - 6)
            .attr('y', d => (d.y1 + d.y0) / 2)
            .attr('dy', '0.35em')
            .attr('text-anchor', d => d.x0 < this.width / 2 ? 'start' : 'end')
            .text(d => d.name)
            .style('font-size', '12px');
    }

    getLinkColor(value) {
        const scale = d3.scaleLinear()
            .domain([0, 100])
            .range(['#E3F2FD', '#1976D2']);
        return scale(value);
    }

    showLinkTooltip(event, d) {
        // Show tooltip implementation
    }

    showNodeTooltip(event, d) {
        // Show tooltip implementation
    }

    hideTooltip() {
        // Hide tooltip implementation
    }
}
```

### Funnel Visualization Chart
```javascript
const funnelChartConfig = {
    type: 'funnel',
    data: {
        labels: [],
        datasets: [{
            data: [],
            backgroundColor: [
                'rgba(54, 162, 235, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(75, 192, 192, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Booking Funnel Analysis',
                font: { size: 16, weight: 'bold' }
            },
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const stage = context.label;
                        const value = context.parsed;
                        const total = context.dataset.data[0];
                        const percentage = ((value / total) * 100).toFixed(2);
                        return [
                            `${stage}: ${value.toLocaleString()}`,
                            `Conversion: ${percentage}%`
                        ];
                    }
                }
            }
        }
    }
};
```

---

## 12. Security Considerations

### Data Security
- **IP Anonymization:** Hash IP addresses for GDPR compliance
- **Session Encryption:** Encrypt session recording data
- **Access Control:** Role-based permissions for analytics access
- **API Authentication:** Nonce verification for tracking endpoints
- **XSS Prevention:** Sanitize all tracked data

### Privacy & Compliance
- **GDPR Compliance:** Cookie consent, data export/deletion
- **Do Not Track:** Respect DNT browser settings
- **Data Minimization:** Track only necessary data
- **Anonymization:** Option to anonymize all tracking
- **Retention Policies:** Automatic data cleanup

---

## 13. Testing Strategy

### Unit Tests
```php
- test_journey_mapping()
- test_funnel_calculation()
- test_attribution_models()
- test_heatmap_generation()
- test_session_tracking()
- test_event_recording()
```

### Integration Tests
```php
- test_complete_journey_tracking()
- test_funnel_analytics_flow()
- test_attribution_calculation()
- test_heatmap_data_collection()
```

---

## 14. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Tracking SDK
- [ ] Event collection API

### Phase 2: Journey Mapping (Week 3-4)
- [ ] Journey builder
- [ ] Touchpoint tracking
- [ ] Stage classification

### Phase 3: Funnel Analysis (Week 5-6)
- [ ] Funnel calculator
- [ ] Drop-off analysis
- [ ] Visualization

### Phase 4: Attribution (Week 7-8)
- [ ] Attribution models
- [ ] Multi-touch calculation
- [ ] Reporting

### Phase 5: Session Analytics (Week 9-10)
- [ ] Session recording
- [ ] Heatmap generation
- [ ] Interaction tracking

### Phase 6: Testing & Launch (Week 11-12)
- [ ] Comprehensive testing
- [ ] Documentation
- [ ] Production release

**Total Timeline:** 12 weeks (3 months)

---

## 15. Success Metrics

### Technical Metrics
- Tracking accuracy > 99%
- Page load impact < 100ms
- Data processing time < 2 seconds
- Dashboard load time < 2 seconds

### Business Metrics
- Funnel optimization improvement > 15%
- Attribution accuracy > 85%
- User adoption rate > 35%
- Conversion rate improvement > 10%

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
