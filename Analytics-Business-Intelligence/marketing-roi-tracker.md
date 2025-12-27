# Marketing ROI Tracker - Development Documentation

## 1. Overview

**Add-on Name:** Marketing ROI Tracker
**Price:** $69
**Category:** Analytics & Business Intelligence
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Comprehensive marketing analytics with campaign tracking, ROI calculation, conversion attribution, customer acquisition cost (CAC) analysis, and channel performance measurement. Track every marketing dollar spent and measure its impact on bookings and revenue with detailed attribution modeling and campaign effectiveness reporting.

### Value Proposition
- Complete campaign ROI tracking
- Multi-channel attribution analysis
- Customer acquisition cost (CAC) calculation
- Lifetime value to CAC ratio (LTV:CAC)
- Conversion tracking by source
- Campaign effectiveness measurement
- Ad spend optimization
- Marketing budget allocation
- A/B test result tracking
- Real-time campaign performance

---

## 2. Features & Requirements

### Core Features
1. **Campaign Tracking**
   - UTM parameter tracking
   - Campaign performance metrics
   - Multi-channel campaign tracking
   - Campaign cost tracking
   - Impression and click tracking
   - Conversion tracking
   - Revenue attribution
   - Campaign comparison

2. **ROI Calculation**
   - Marketing spend tracking
   - Revenue attribution by campaign
   - ROI calculation (Revenue - Cost / Cost)
   - Return on Ad Spend (ROAS)
   - Cost per acquisition (CPA)
   - Cost per click (CPC)
   - Cost per impression (CPM)
   - Profit margin by campaign

3. **Attribution Analysis**
   - First-touch attribution
   - Last-touch attribution
   - Linear attribution
   - Time-decay attribution
   - Position-based attribution
   - Custom attribution models
   - Multi-touch attribution
   - Channel contribution analysis

4. **Customer Acquisition Analytics**
   - CAC by channel
   - CAC by campaign
   - CAC trends over time
   - LTV:CAC ratio
   - Payback period
   - Acquisition funnel analysis
   - Source quality scoring
   - Conversion rate by source

5. **Channel Performance**
   - Performance by marketing channel
   - Organic vs paid comparison
   - Social media performance
   - Email marketing metrics
   - Referral tracking
   - Direct traffic analysis
   - Search engine performance
   - Display advertising metrics

6. **Budget Optimization**
   - Budget allocation recommendations
   - Spend efficiency analysis
   - Underperforming campaign identification
   - High-ROI channel identification
   - Budget reallocation suggestions
   - Forecasted ROI by budget
   - Cost optimization opportunities

### User Roles & Permissions
- **Marketing Manager:** Full access, all campaigns
- **Admin:** Configuration, view all reports
- **Analyst:** Advanced analytics, custom reports
- **Manager:** View campaign performance

---

## 3. Technical Specifications

### Technology Stack
- **Analytics Engine:** PHP + MySQL
- **Tracking:** UTM parameter capture, cookies
- **Visualization:** Chart.js, Apache ECharts
- **Attribution:** Multi-touch attribution algorithms
- **Export:** PDF, Excel, CSV
- **Integration:** Google Analytics, Facebook Pixel

### Dependencies
- BookingX Core 2.0+
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- PHP Extensions: mysqli, json, curl

### API Integration Points
```php
// Custom REST API endpoints
- POST /bookingx/v1/marketing/track-utm
- GET  /bookingx/v1/marketing/campaign-performance
- GET  /bookingx/v1/marketing/roi-analysis
- GET  /bookingx/v1/marketing/attribution-report
- GET  /bookingx/v1/marketing/cac-analysis
- GET  /bookingx/v1/marketing/channel-performance
- GET  /bookingx/v1/marketing/budget-recommendations
- POST /bookingx/v1/marketing/campaign-cost
- GET  /bookingx/v1/marketing/conversion-funnel
- GET  /bookingx/v1/marketing/export-report
```

---

## 4. Architecture & Design

### System Architecture
```
┌────────────────────────────────────────────┐
│     Marketing Analytics Dashboard          │
│  (ROI, Attribution, Campaigns)             │
└────────────┬───────────────────────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│      Marketing ROI Analytics Engine        │
│  - Campaign Tracker                        │
│  - ROI Calculator                          │
│  - Attribution Engine                      │
│  - CAC Analyzer                            │
└────────────┬───────────────────────────────┘
             │
             ├──────────┬──────────┬──────────┐
             ▼          ▼          ▼          ▼
┌──────────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐
│   Campaign   │ │    ROI   │ │Attribution│ │    CAC     │
│   Tracker    │ │Calculator│ │  Engine   │ │  Analyzer  │
└──────────────┘ └──────────┘ └──────────┘ └────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│      Marketing Data Warehouse              │
│  - UTM Tracking Data                       │
│  - Campaign Costs                          │
│  - Conversion Data                         │
│  - Attribution Data                        │
└────────────────────────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\MarketingROI;

class MarketingROIManager {
    - init()
    - register_endpoints()
    - track_utm_parameters()
    - capture_marketing_data()
}

class CampaignTracker {
    - track_campaign($utm_data)
    - get_campaign_performance($campaign_id)
    - track_campaign_costs($campaign_id, $cost_data)
    - calculate_campaign_metrics($campaign_id)
    - compare_campaigns($campaign_ids)
}

class ROICalculator {
    - calculate_roi($campaign_id)
    - calculate_roas($campaign_id)
    - calculate_cpa($campaign_id)
    - calculate_profit_margin($campaign_id)
    - forecast_roi($campaign_id, $budget_increase)
}

class AttributionEngine {
    - calculate_attribution($booking_id, $model)
    - first_touch_attribution($journey)
    - last_touch_attribution($journey)
    - linear_attribution($journey)
    - time_decay_attribution($journey)
    - position_based_attribution($journey)
    - multi_touch_attribution($journey)
}

class CACAnalyzer {
    - calculate_cac($channel, $period)
    - calculate_cac_by_campaign($campaign_id)
    - calculate_ltv_cac_ratio($channel)
    - calculate_payback_period($channel)
    - analyze_cac_trends($period)
    - identify_cost_efficient_channels()
}

class ChannelPerformanceAnalyzer {
    - analyze_channel_performance($channel)
    - compare_channels()
    - identify_best_performing_channels()
    - calculate_channel_roi()
    - get_channel_conversion_rates()
}

class BudgetOptimizer {
    - recommend_budget_allocation()
    - identify_underperforming_campaigns()
    - suggest_budget_reallocation()
    - calculate_optimal_spend($channel)
    - forecast_budget_impact($scenarios)
}

class ConversionFunnelTracker {
    - track_funnel_by_source($source)
    - calculate_conversion_rates($source)
    - identify_drop_offs($source)
    - compare_source_funnels()
}
```

---

## 5. Database Schema

### Table: `bkx_marketing_utm_tracking`
```sql
CREATE TABLE bkx_marketing_utm_tracking (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    visitor_id VARCHAR(100) NOT NULL,
    customer_id BIGINT(20) UNSIGNED,
    booking_id BIGINT(20) UNSIGNED,
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    utm_term VARCHAR(100),
    utm_content VARCHAR(100),
    referrer_url VARCHAR(500),
    landing_page VARCHAR(500),
    conversion_page VARCHAR(500),
    converted TINYINT(1) DEFAULT 0,
    conversion_value DECIMAL(10,2),
    first_touch TINYINT(1) DEFAULT 0,
    last_touch TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    converted_at DATETIME,
    INDEX session_id_idx (session_id),
    INDEX visitor_id_idx (visitor_id),
    INDEX customer_id_idx (customer_id),
    INDEX booking_id_idx (booking_id),
    INDEX utm_campaign_idx (utm_campaign),
    INDEX utm_source_idx (utm_source),
    INDEX converted_idx (converted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_marketing_campaigns`
```sql
CREATE TABLE bkx_marketing_campaigns (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_name VARCHAR(255) NOT NULL,
    campaign_code VARCHAR(100) NOT NULL UNIQUE,
    campaign_type VARCHAR(50) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    total_budget DECIMAL(12,2) DEFAULT 0.00,
    spent_budget DECIMAL(12,2) DEFAULT 0.00,
    target_conversions INT,
    target_revenue DECIMAL(12,2),
    status VARCHAR(20) DEFAULT 'active',
    description TEXT,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX campaign_code_idx (campaign_code),
    INDEX channel_idx (channel),
    INDEX status_idx (status),
    INDEX start_date_idx (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_marketing_campaign_costs`
```sql
CREATE TABLE bkx_marketing_campaign_costs (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT(20) UNSIGNED NOT NULL,
    cost_date DATE NOT NULL,
    cost_type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'USD',
    platform VARCHAR(50),
    notes TEXT,
    created_at DATETIME NOT NULL,
    INDEX campaign_id_idx (campaign_id),
    INDEX cost_date_idx (cost_date),
    INDEX cost_type_idx (cost_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_marketing_conversions`
```sql
CREATE TABLE bkx_marketing_conversions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    campaign_id BIGINT(20) UNSIGNED,
    utm_source VARCHAR(100),
    utm_medium VARCHAR(100),
    utm_campaign VARCHAR(100),
    conversion_date DATETIME NOT NULL,
    conversion_value DECIMAL(10,2) NOT NULL,
    attributed_channel VARCHAR(100),
    attribution_model VARCHAR(50),
    first_touch_source VARCHAR(100),
    last_touch_source VARCHAR(100),
    touchpoint_count INT DEFAULT 1,
    customer_journey_days INT,
    is_new_customer TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX customer_id_idx (customer_id),
    INDEX campaign_id_idx (campaign_id),
    INDEX conversion_date_idx (conversion_date),
    INDEX attributed_channel_idx (attributed_channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_marketing_channel_performance`
```sql
CREATE TABLE bkx_marketing_channel_performance (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    performance_date DATE NOT NULL,
    channel VARCHAR(100) NOT NULL,
    total_spend DECIMAL(12,2) DEFAULT 0.00,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    visitors INT DEFAULT 0,
    conversions INT DEFAULT 0,
    revenue DECIMAL(12,2) DEFAULT 0.00,
    ctr DECIMAL(5,2),
    conversion_rate DECIMAL(5,2),
    cpc DECIMAL(10,2),
    cpa DECIMAL(10,2),
    roi DECIMAL(10,2),
    roas DECIMAL(10,2),
    created_at DATETIME NOT NULL,
    UNIQUE KEY channel_date_idx (channel, performance_date),
    INDEX performance_date_idx (performance_date),
    INDEX channel_idx (channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_marketing_attribution`
```sql
CREATE TABLE bkx_marketing_attribution (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    touchpoint_order INT NOT NULL,
    touchpoint_source VARCHAR(100) NOT NULL,
    touchpoint_medium VARCHAR(100),
    touchpoint_campaign VARCHAR(100),
    touchpoint_date DATETIME NOT NULL,
    attribution_model VARCHAR(50) NOT NULL,
    attribution_credit DECIMAL(5,4) NOT NULL,
    revenue_attributed DECIMAL(10,2),
    created_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX customer_id_idx (customer_id),
    INDEX touchpoint_source_idx (touchpoint_source),
    INDEX attribution_model_idx (attribution_model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_marketing_cac_tracking`
```sql
CREATE TABLE bkx_marketing_cac_tracking (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_period VARCHAR(20) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    channel VARCHAR(100) NOT NULL,
    campaign_id BIGINT(20) UNSIGNED,
    total_spend DECIMAL(12,2) NOT NULL,
    new_customers INT NOT NULL,
    cac DECIMAL(10,2) NOT NULL,
    avg_ltv DECIMAL(10,2),
    ltv_cac_ratio DECIMAL(5,2),
    payback_period_days INT,
    created_at DATETIME NOT NULL,
    UNIQUE KEY cac_unique (tracking_period, period_start, channel),
    INDEX channel_idx (channel),
    INDEX period_start_idx (period_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    // General settings
    'enable_marketing_tracking' => true,
    'enable_utm_tracking' => true,
    'track_referrers' => true,

    // Campaign tracking
    'auto_detect_campaigns' => true,
    'require_utm_parameters' => false,
    'default_attribution_model' => 'multi_touch',

    // Cost tracking
    'enable_cost_tracking' => true,
    'default_currency' => 'USD',
    'auto_import_ad_costs' => false,

    // Attribution settings
    'attribution_window_days' => 30,
    'attribution_models' => [
        'first_touch',
        'last_touch',
        'linear',
        'time_decay',
        'position_based',
        'multi_touch'
    ],
    'time_decay_half_life' => 7, // days

    // ROI calculation
    'include_overhead_costs' => false,
    'overhead_percentage' => 20,
    'calculate_profit_margin' => true,

    // Channel settings
    'tracked_channels' => [
        'organic_search',
        'paid_search',
        'social_organic',
        'social_paid',
        'email',
        'referral',
        'direct',
        'display',
        'affiliate'
    ],

    // CAC settings
    'include_all_marketing_costs' => true,
    'count_returning_customers' => false,
    'target_ltv_cac_ratio' => 3.0,

    // Budget optimization
    'enable_budget_recommendations' => true,
    'min_campaign_conversions' => 10,
    'confidence_threshold' => 0.8,

    // Privacy & compliance
    'respect_do_not_track' => true,
    'cookie_consent_required' => true,
    'data_retention_days' => 365,

    // Performance
    'enable_tracking_cache' => true,
    'cache_ttl_minutes' => 30,

    // Integrations
    'google_analytics_integration' => false,
    'facebook_pixel_integration' => false,
    'google_ads_api_key' => '',
    'facebook_ads_api_key' => '',
]
```

---

## 7. Campaign Tracking & ROI Calculation

### Campaign Tracker & ROI Calculator
```php
class CampaignTracker {

    public function track_campaign($utm_data) {
        global $wpdb;

        // Store UTM tracking data
        $tracking_data = [
            'session_id' => $utm_data['session_id'],
            'visitor_id' => $utm_data['visitor_id'],
            'customer_id' => $utm_data['customer_id'] ?? null,
            'utm_source' => $utm_data['utm_source'] ?? null,
            'utm_medium' => $utm_data['utm_medium'] ?? null,
            'utm_campaign' => $utm_data['utm_campaign'] ?? null,
            'utm_term' => $utm_data['utm_term'] ?? null,
            'utm_content' => $utm_data['utm_content'] ?? null,
            'referrer_url' => $utm_data['referrer'] ?? null,
            'landing_page' => $utm_data['landing_page'],
            'first_touch' => $this->is_first_touch($utm_data['visitor_id']),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert(
            $wpdb->prefix . 'bkx_marketing_utm_tracking',
            $tracking_data
        );

        return $wpdb->insert_id;
    }

    public function get_campaign_performance($campaign_id, $start_date, $end_date) {
        global $wpdb;

        $campaign = $this->get_campaign($campaign_id);

        if (!$campaign) {
            return null;
        }

        // Get conversions
        $conversions = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_conversions,
                SUM(conversion_value) as total_revenue,
                AVG(conversion_value) as avg_order_value
            FROM {$wpdb->prefix}bkx_marketing_conversions
            WHERE utm_campaign = %s
            AND conversion_date BETWEEN %s AND %s
        ", $campaign->campaign_code, $start_date, $end_date));

        // Get costs
        $costs = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(amount) as total_cost,
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks
            FROM {$wpdb->prefix}bkx_marketing_campaign_costs
            WHERE campaign_id = %d
            AND cost_date BETWEEN %s AND %s
        ", $campaign_id, $start_date, $end_date));

        // Get traffic metrics
        $traffic = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(DISTINCT visitor_id) as visitors,
                COUNT(*) as sessions
            FROM {$wpdb->prefix}bkx_marketing_utm_tracking
            WHERE utm_campaign = %s
            AND created_at BETWEEN %s AND %s
        ", $campaign->campaign_code, $start_date, $end_date));

        // Calculate ROI metrics
        $roi_calculator = new ROICalculator();
        $roi_metrics = $roi_calculator->calculate_campaign_roi([
            'revenue' => $conversions->total_revenue,
            'cost' => $costs->total_cost,
            'conversions' => $conversions->total_conversions,
            'clicks' => $costs->total_clicks,
            'impressions' => $costs->total_impressions
        ]);

        return [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->campaign_name,
                'code' => $campaign->campaign_code,
                'channel' => $campaign->channel,
                'budget' => $campaign->total_budget
            ],
            'period' => [
                'start' => $start_date,
                'end' => $end_date
            ],
            'traffic' => [
                'impressions' => $costs->total_impressions,
                'clicks' => $costs->total_clicks,
                'visitors' => $traffic->visitors,
                'sessions' => $traffic->sessions
            ],
            'conversions' => [
                'total' => $conversions->total_conversions,
                'revenue' => round($conversions->total_revenue, 2),
                'avg_order_value' => round($conversions->avg_order_value, 2)
            ],
            'costs' => [
                'total' => round($costs->total_cost, 2),
                'budget_remaining' => round($campaign->total_budget - $costs->total_cost, 2),
                'budget_used_percent' => ($campaign->total_budget > 0) ?
                    round(($costs->total_cost / $campaign->total_budget) * 100, 2) : 0
            ],
            'metrics' => $roi_metrics
        ];
    }
}

class ROICalculator {

    public function calculate_campaign_roi($data) {
        $revenue = $data['revenue'] ?? 0;
        $cost = $data['cost'] ?? 0;
        $conversions = $data['conversions'] ?? 0;
        $clicks = $data['clicks'] ?? 0;
        $impressions = $data['impressions'] ?? 0;

        // ROI = (Revenue - Cost) / Cost * 100
        $roi = ($cost > 0) ? (($revenue - $cost) / $cost) * 100 : 0;

        // ROAS = Revenue / Cost
        $roas = ($cost > 0) ? $revenue / $cost : 0;

        // CPA = Cost / Conversions
        $cpa = ($conversions > 0) ? $cost / $conversions : 0;

        // CPC = Cost / Clicks
        $cpc = ($clicks > 0) ? $cost / $clicks : 0;

        // CPM = (Cost / Impressions) * 1000
        $cpm = ($impressions > 0) ? ($cost / $impressions) * 1000 : 0;

        // CTR = (Clicks / Impressions) * 100
        $ctr = ($impressions > 0) ? ($clicks / $impressions) * 100 : 0;

        // Conversion Rate = (Conversions / Clicks) * 100
        $conversion_rate = ($clicks > 0) ? ($conversions / $clicks) * 100 : 0;

        // Profit = Revenue - Cost
        $profit = $revenue - $cost;

        // Profit Margin = (Profit / Revenue) * 100
        $profit_margin = ($revenue > 0) ? ($profit / $revenue) * 100 : 0;

        return [
            'roi' => round($roi, 2),
            'roas' => round($roas, 2),
            'cpa' => round($cpa, 2),
            'cpc' => round($cpc, 2),
            'cpm' => round($cpm, 2),
            'ctr' => round($ctr, 2),
            'conversion_rate' => round($conversion_rate, 2),
            'profit' => round($profit, 2),
            'profit_margin' => round($profit_margin, 2)
        ];
    }

    public function calculate_roi_by_channel($channel, $period) {
        global $wpdb;

        // Get all campaigns for this channel
        $campaigns = $wpdb->get_col($wpdb->prepare("
            SELECT id
            FROM {$wpdb->prefix}bkx_marketing_campaigns
            WHERE channel = %s
            AND status = 'active'
        ", $channel));

        if (empty($campaigns)) {
            return null;
        }

        $total_revenue = 0;
        $total_cost = 0;
        $total_conversions = 0;
        $total_clicks = 0;
        $total_impressions = 0;

        foreach ($campaigns as $campaign_id) {
            $tracker = new CampaignTracker();
            $performance = $tracker->get_campaign_performance(
                $campaign_id,
                $period['start'],
                $period['end']
            );

            if ($performance) {
                $total_revenue += $performance['conversions']['revenue'];
                $total_cost += $performance['costs']['total'];
                $total_conversions += $performance['conversions']['total'];
                $total_clicks += $performance['traffic']['clicks'];
                $total_impressions += $performance['traffic']['impressions'];
            }
        }

        return $this->calculate_campaign_roi([
            'revenue' => $total_revenue,
            'cost' => $total_cost,
            'conversions' => $total_conversions,
            'clicks' => $total_clicks,
            'impressions' => $total_impressions
        ]);
    }

    public function forecast_roi($campaign_id, $budget_increase) {
        // Get historical performance
        $tracker = new CampaignTracker();
        $historical = $tracker->get_campaign_performance(
            $campaign_id,
            date('Y-m-d', strtotime('-30 days')),
            date('Y-m-d')
        );

        if (!$historical) {
            return null;
        }

        $current_cost = $historical['costs']['total'];
        $current_revenue = $historical['conversions']['revenue'];
        $current_conversions = $historical['conversions']['total'];

        // Simple linear projection (could be more sophisticated)
        $budget_increase_percent = $budget_increase / $current_cost;
        $forecasted_revenue = $current_revenue * (1 + $budget_increase_percent * 0.8); // 80% efficiency
        $forecasted_cost = $current_cost + $budget_increase;
        $forecasted_conversions = $current_conversions * (1 + $budget_increase_percent * 0.8);

        return [
            'current' => [
                'cost' => $current_cost,
                'revenue' => $current_revenue,
                'conversions' => $current_conversions,
                'roi' => $historical['metrics']['roi']
            ],
            'forecast' => [
                'cost' => $forecasted_cost,
                'revenue' => round($forecasted_revenue, 2),
                'conversions' => round($forecasted_conversions),
                'roi' => round((($forecasted_revenue - $forecasted_cost) / $forecasted_cost) * 100, 2)
            ],
            'assumptions' => [
                'efficiency_factor' => 0.8,
                'based_on_days' => 30
            ]
        ];
    }
}
```

---

## 8. Attribution Analysis

### Multi-Touch Attribution Engine
```php
class AttributionEngine {

    public function calculate_attribution($booking_id, $model = 'multi_touch') {
        // Get customer journey for this booking
        $journey = $this->get_customer_journey($booking_id);

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

            case 'position_based':
                $attribution_data = $this->position_based_attribution($journey);
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

    private function get_customer_journey($booking_id) {
        global $wpdb;

        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}bookingx_bookings WHERE id = %d
        ", $booking_id));

        if (!$booking) {
            return null;
        }

        // Get all touchpoints for this customer
        $touchpoints = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}bkx_marketing_utm_tracking
            WHERE visitor_id IN (
                SELECT DISTINCT visitor_id
                FROM {$wpdb->prefix}bkx_marketing_utm_tracking
                WHERE booking_id = %d
                OR customer_id = %d
            )
            AND created_at <= %s
            ORDER BY created_at ASC
        ", $booking_id, $booking->customer_id, $booking->created_at));

        return [
            'booking_id' => $booking_id,
            'customer_id' => $booking->customer_id,
            'booking_value' => $booking->total_amount,
            'booking_date' => $booking->created_at,
            'touchpoints' => $touchpoints
        ];
    }

    private function first_touch_attribution($journey) {
        $first_touchpoint = $journey['touchpoints'][0];

        return [[
            'touchpoint_order' => 1,
            'touchpoint_source' => $first_touchpoint->utm_source ?? 'direct',
            'touchpoint_medium' => $first_touchpoint->utm_medium,
            'touchpoint_campaign' => $first_touchpoint->utm_campaign,
            'touchpoint_date' => $first_touchpoint->created_at,
            'attribution_credit' => 1.0,
            'revenue_attributed' => $journey['booking_value']
        ]];
    }

    private function last_touch_attribution($journey) {
        $last_touchpoint = end($journey['touchpoints']);

        return [[
            'touchpoint_order' => count($journey['touchpoints']),
            'touchpoint_source' => $last_touchpoint->utm_source ?? 'direct',
            'touchpoint_medium' => $last_touchpoint->utm_medium,
            'touchpoint_campaign' => $last_touchpoint->utm_campaign,
            'touchpoint_date' => $last_touchpoint->created_at,
            'attribution_credit' => 1.0,
            'revenue_attributed' => $journey['booking_value']
        ]];
    }

    private function linear_attribution($journey) {
        $touchpoint_count = count($journey['touchpoints']);
        $credit_per_touchpoint = 1.0 / $touchpoint_count;
        $revenue_per_touchpoint = $journey['booking_value'] / $touchpoint_count;

        $attribution = [];

        foreach ($journey['touchpoints'] as $index => $touchpoint) {
            $attribution[] = [
                'touchpoint_order' => $index + 1,
                'touchpoint_source' => $touchpoint->utm_source ?? 'direct',
                'touchpoint_medium' => $touchpoint->utm_medium,
                'touchpoint_campaign' => $touchpoint->utm_campaign,
                'touchpoint_date' => $touchpoint->created_at,
                'attribution_credit' => $credit_per_touchpoint,
                'revenue_attributed' => $revenue_per_touchpoint
            ];
        }

        return $attribution;
    }

    private function time_decay_attribution($journey) {
        $half_life_days = 7;
        $conversion_time = strtotime($journey['booking_date']);

        // Calculate decay weights
        $weights = [];
        $total_weight = 0;

        foreach ($journey['touchpoints'] as $touchpoint) {
            $touchpoint_time = strtotime($touchpoint->created_at);
            $days_before_conversion = ($conversion_time - $touchpoint_time) / 86400;

            // Exponential decay
            $weight = pow(2, -$days_before_conversion / $half_life_days);
            $weights[] = $weight;
            $total_weight += $weight;
        }

        // Normalize weights
        $attribution = [];

        foreach ($journey['touchpoints'] as $index => $touchpoint) {
            $credit = ($total_weight > 0) ? $weights[$index] / $total_weight : 0;

            $attribution[] = [
                'touchpoint_order' => $index + 1,
                'touchpoint_source' => $touchpoint->utm_source ?? 'direct',
                'touchpoint_medium' => $touchpoint->utm_medium,
                'touchpoint_campaign' => $touchpoint->utm_campaign,
                'touchpoint_date' => $touchpoint->created_at,
                'attribution_credit' => $credit,
                'revenue_attributed' => $journey['booking_value'] * $credit
            ];
        }

        return $attribution;
    }

    private function position_based_attribution($journey) {
        // 40% first, 40% last, 20% distributed among middle
        $touchpoint_count = count($journey['touchpoints']);

        if ($touchpoint_count === 1) {
            return $this->first_touch_attribution($journey);
        }

        if ($touchpoint_count === 2) {
            $half_revenue = $journey['booking_value'] / 2;
            return [
                [
                    'touchpoint_order' => 1,
                    'touchpoint_source' => $journey['touchpoints'][0]->utm_source ?? 'direct',
                    'touchpoint_medium' => $journey['touchpoints'][0]->utm_medium,
                    'touchpoint_campaign' => $journey['touchpoints'][0]->utm_campaign,
                    'touchpoint_date' => $journey['touchpoints'][0]->created_at,
                    'attribution_credit' => 0.5,
                    'revenue_attributed' => $half_revenue
                ],
                [
                    'touchpoint_order' => 2,
                    'touchpoint_source' => $journey['touchpoints'][1]->utm_source ?? 'direct',
                    'touchpoint_medium' => $journey['touchpoints'][1]->utm_medium,
                    'touchpoint_campaign' => $journey['touchpoints'][1]->utm_campaign,
                    'touchpoint_date' => $journey['touchpoints'][1]->created_at,
                    'attribution_credit' => 0.5,
                    'revenue_attributed' => $half_revenue
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
                'touchpoint_order' => $index + 1,
                'touchpoint_source' => $touchpoint->utm_source ?? 'direct',
                'touchpoint_medium' => $touchpoint->utm_medium,
                'touchpoint_campaign' => $touchpoint->utm_campaign,
                'touchpoint_date' => $touchpoint->created_at,
                'attribution_credit' => $credit,
                'revenue_attributed' => $journey['booking_value'] * $credit
            ];
        }

        return $attribution;
    }

    public function generate_attribution_report($period, $model = 'multi_touch') {
        global $wpdb;

        $report = $wpdb->get_results($wpdb->prepare("
            SELECT
                touchpoint_source as source,
                COUNT(DISTINCT booking_id) as conversions,
                SUM(attribution_credit) as total_credit,
                SUM(revenue_attributed) as total_revenue
            FROM {$wpdb->prefix}bkx_marketing_attribution
            WHERE attribution_model = %s
            AND created_at BETWEEN %s AND %s
            GROUP BY touchpoint_source
            ORDER BY total_credit DESC
        ", $model, $period['start'], $period['end']));

        return [
            'period' => $period,
            'attribution_model' => $model,
            'sources' => $report,
            'total_conversions' => array_sum(array_column((array)$report, 'conversions')),
            'total_revenue' => array_sum(array_column((array)$report, 'total_revenue'))
        ];
    }
}
```

---

## 9. CAC Analysis

### Customer Acquisition Cost Analyzer
```php
class CACAnalyzer {

    public function calculate_cac($channel, $start_date, $end_date) {
        global $wpdb;

        // Get total marketing spend for channel
        $total_spend = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(cc.amount)
            FROM {$wpdb->prefix}bkx_marketing_campaign_costs cc
            JOIN {$wpdb->prefix}bkx_marketing_campaigns c ON cc.campaign_id = c.id
            WHERE c.channel = %s
            AND cc.cost_date BETWEEN %s AND %s
        ", $channel, $start_date, $end_date));

        // Get new customers acquired through this channel
        $new_customers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT customer_id)
            FROM {$wpdb->prefix}bkx_marketing_conversions
            WHERE attributed_channel = %s
            AND is_new_customer = 1
            AND conversion_date BETWEEN %s AND %s
        ", $channel, $start_date, $end_date));

        $cac = ($new_customers > 0) ? $total_spend / $new_customers : 0;

        // Calculate LTV for these customers
        $avg_ltv = $this->calculate_avg_ltv($channel, $start_date, $end_date);

        // Calculate LTV:CAC ratio
        $ltv_cac_ratio = ($cac > 0) ? $avg_ltv / $cac : 0;

        // Calculate payback period
        $payback_period = $this->calculate_payback_period($channel, $cac);

        return [
            'channel' => $channel,
            'period' => ['start' => $start_date, 'end' => $end_date],
            'total_spend' => round($total_spend, 2),
            'new_customers' => $new_customers,
            'cac' => round($cac, 2),
            'avg_ltv' => round($avg_ltv, 2),
            'ltv_cac_ratio' => round($ltv_cac_ratio, 2),
            'payback_period_days' => round($payback_period),
            'health_status' => $this->assess_cac_health($ltv_cac_ratio)
        ];
    }

    private function calculate_avg_ltv($channel, $start_date, $end_date) {
        global $wpdb;

        // Get customers acquired in this period
        $customer_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT customer_id
            FROM {$wpdb->prefix}bkx_marketing_conversions
            WHERE attributed_channel = %s
            AND is_new_customer = 1
            AND conversion_date BETWEEN %s AND %s
        ", $channel, $start_date, $end_date));

        if (empty($customer_ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($customer_ids), '%d'));

        $total_ltv = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(total_amount)
            FROM {$wpdb->prefix}bookingx_bookings
            WHERE customer_id IN ($placeholders)
            AND status = 'completed'
        ", ...$customer_ids));

        return $total_ltv / count($customer_ids);
    }

    private function calculate_payback_period($channel, $cac) {
        // Simplified: average revenue per booking × average bookings per month
        global $wpdb;

        $avg_revenue_per_booking = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(total_amount)
            FROM {$wpdb->prefix}bookingx_bookings
            WHERE customer_id IN (
                SELECT DISTINCT customer_id
                FROM {$wpdb->prefix}bkx_marketing_conversions
                WHERE attributed_channel = %s
            )
        ", $channel));

        $avg_bookings_per_month = 1.5; // Simplified assumption

        if ($avg_revenue_per_booking <= 0) {
            return 0;
        }

        $monthly_revenue = $avg_revenue_per_booking * $avg_bookings_per_month;

        return ($cac / $monthly_revenue) * 30;
    }

    private function assess_cac_health($ltv_cac_ratio) {
        if ($ltv_cac_ratio >= 3) {
            return 'excellent';
        } elseif ($ltv_cac_ratio >= 2) {
            return 'good';
        } elseif ($ltv_cac_ratio >= 1) {
            return 'acceptable';
        } else {
            return 'poor';
        }
    }

    public function analyze_cac_trends($channel, $months = 6) {
        $trends = [];

        for ($i = 0; $i < $months; $i++) {
            $end_date = date('Y-m-t', strtotime("-{$i} months"));
            $start_date = date('Y-m-01', strtotime("-{$i} months"));

            $cac_data = $this->calculate_cac($channel, $start_date, $end_date);

            $trends[] = [
                'month' => date('Y-m', strtotime($start_date)),
                'cac' => $cac_data['cac'],
                'new_customers' => $cac_data['new_customers'],
                'ltv_cac_ratio' => $cac_data['ltv_cac_ratio']
            ];
        }

        return array_reverse($trends);
    }

    public function identify_cost_efficient_channels($period) {
        $tracked_channels = [
            'organic_search',
            'paid_search',
            'social_organic',
            'social_paid',
            'email',
            'referral',
            'direct'
        ];

        $channel_performance = [];

        foreach ($tracked_channels as $channel) {
            $cac_data = $this->calculate_cac($channel, $period['start'], $period['end']);

            if ($cac_data['new_customers'] > 0) {
                $channel_performance[] = [
                    'channel' => $channel,
                    'cac' => $cac_data['cac'],
                    'ltv_cac_ratio' => $cac_data['ltv_cac_ratio'],
                    'new_customers' => $cac_data['new_customers'],
                    'efficiency_score' => $cac_data['ltv_cac_ratio']
                ];
            }
        }

        // Sort by efficiency
        usort($channel_performance, function($a, $b) {
            return $b['efficiency_score'] <=> $a['efficiency_score'];
        });

        return $channel_performance;
    }
}
```

---

## 10. Budget Optimization

### Budget Optimizer
```php
class BudgetOptimizer {

    public function recommend_budget_allocation($total_budget, $period) {
        // Get performance data for all channels
        $cac_analyzer = new CACAnalyzer();
        $channels = $cac_analyzer->identify_cost_efficient_channels($period);

        $recommendations = [];
        $allocated_budget = 0;

        // Allocate budget based on efficiency
        foreach ($channels as $index => $channel) {
            // Higher efficiency = higher allocation
            $allocation_percent = $this->calculate_allocation_percent(
                $channel['efficiency_score'],
                $channels
            );

            $allocated_amount = $total_budget * $allocation_percent;
            $allocated_budget += $allocated_amount;

            $recommendations[] = [
                'channel' => $channel['channel'],
                'current_cac' => $channel['cac'],
                'ltv_cac_ratio' => $channel['ltv_cac_ratio'],
                'recommended_budget' => round($allocated_amount, 2),
                'allocation_percent' => round($allocation_percent * 100, 2),
                'expected_customers' => round($allocated_amount / $channel['cac']),
                'expected_ltv' => round(($allocated_amount / $channel['cac']) * ($channel['cac'] * $channel['ltv_cac_ratio']))
            ];
        }

        return [
            'total_budget' => $total_budget,
            'allocated_budget' => round($allocated_budget, 2),
            'recommendations' => $recommendations,
            'optimization_strategy' => 'efficiency_based'
        ];
    }

    private function calculate_allocation_percent($efficiency, $all_channels) {
        $total_efficiency = array_sum(array_column($all_channels, 'efficiency_score'));

        return ($total_efficiency > 0) ? $efficiency / $total_efficiency : 0;
    }

    public function identify_underperforming_campaigns($period) {
        global $wpdb;

        $campaigns = $wpdb->get_results($wpdb->prepare("
            SELECT id, campaign_name, channel
            FROM {$wpdb->prefix}bkx_marketing_campaigns
            WHERE status = 'active'
            AND start_date <= %s
        ", $period['end']));

        $underperforming = [];

        foreach ($campaigns as $campaign) {
            $tracker = new CampaignTracker();
            $performance = $tracker->get_campaign_performance(
                $campaign->id,
                $period['start'],
                $period['end']
            );

            if (!$performance) {
                continue;
            }

            $roi = $performance['metrics']['roi'];
            $conversion_rate = $performance['metrics']['conversion_rate'];

            // Define underperforming criteria
            if ($roi < 0 || $conversion_rate < 1) {
                $underperforming[] = [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->campaign_name,
                    'channel' => $campaign->channel,
                    'roi' => $roi,
                    'conversion_rate' => $conversion_rate,
                    'issue' => $this->identify_issue($roi, $conversion_rate),
                    'recommendation' => $this->get_recommendation($roi, $conversion_rate)
                ];
            }
        }

        return $underperforming;
    }

    private function identify_issue($roi, $conversion_rate) {
        if ($roi < 0 && $conversion_rate < 1) {
            return 'low_roi_and_conversions';
        } elseif ($roi < 0) {
            return 'negative_roi';
        } elseif ($conversion_rate < 1) {
            return 'low_conversion_rate';
        }

        return 'unknown';
    }

    private function get_recommendation($roi, $conversion_rate) {
        if ($roi < -50) {
            return 'Pause or terminate campaign immediately';
        } elseif ($roi < 0) {
            return 'Reduce budget and optimize targeting';
        } elseif ($conversion_rate < 0.5) {
            return 'Improve landing page and ad creative';
        } else {
            return 'Monitor closely and test variations';
        }
    }
}
```

---

## 11. Visualization Components

### Chart Configurations
```javascript
// ROI by Campaign Bar Chart
const roiCampaignChartConfig = {
    type: 'bar',
    data: {
        labels: [],
        datasets: [{
            label: 'ROI (%)',
            data: [],
            backgroundColor: function(context) {
                const value = context.dataset.data[context.dataIndex];
                return value >= 0 ? 'rgba(75, 192, 192, 0.8)' : 'rgba(255, 99, 132, 0.8)';
            }
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Campaign ROI Comparison'
            }
        }
    }
};

// Attribution Model Pie Chart
const attributionPieConfig = {
    type: 'pie',
    data: {
        labels: [],
        datasets: [{
            data: [],
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Revenue Attribution by Source'
            }
        }
    }
};

// CAC Trend Line Chart
const cacTrendConfig = {
    type: 'line',
    data: {
        labels: [],
        datasets: [{
            label: 'CAC',
            data: [],
            borderColor: 'rgb(255, 99, 132)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Customer Acquisition Cost Trend'
            }
        },
        scales: {
            y: {
                ticks: {
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            }
        }
    }
};
```

---

## 12. Security & Privacy

### Data Security
- **Cookie Consent:** Respect GDPR requirements
- **Do Not Track:** Honor DNT browser settings
- **Data Anonymization:** Remove PII from reports
- **Access Control:** Role-based permissions

---

## 13. Testing Strategy

### Unit Tests
```php
- test_utm_tracking()
- test_roi_calculation()
- test_attribution_models()
- test_cac_calculation()
```

---

## 14. Development Timeline

**Total Timeline:** 10 weeks (2.5 months)

---

## 15. Success Metrics

### Technical Metrics
- Tracking accuracy > 98%
- Attribution accuracy > 85%
- Report generation < 3 seconds

### Business Metrics
- Marketing efficiency improvement > 25%
- Budget optimization > 20%
- User satisfaction > 4.5/5

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
