# Staff Performance Analytics - Development Documentation

## 1. Overview

**Add-on Name:** Staff Performance Analytics
**Price:** $79
**Category:** Analytics & Business Intelligence
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Comprehensive staff performance tracking and analytics with individual metrics, productivity analysis, revenue generation tracking, utilization rates, customer satisfaction scores, and performance benchmarking. Empower managers with data-driven insights for staff scheduling, training needs, and performance reviews.

### Value Proposition
- Individual staff performance dashboards
- Revenue generation by staff member
- Utilization rate tracking
- Customer satisfaction by staff
- Productivity metrics and KPIs
- Performance benchmarking
- Goal tracking and achievement
- Commission calculation support
- Training needs identification
- Performance review reports

---

## 2. Features & Requirements

### Core Features
1. **Staff Performance Metrics**
   - Bookings per staff member
   - Revenue generated per staff
   - Average booking value
   - Completion rate
   - Cancellation rate
   - Customer retention rate
   - Rebooking rate
   - Performance trends

2. **Utilization Analysis**
   - Schedule utilization rate
   - Billable hours tracking
   - Idle time analysis
   - Peak productivity times
   - Workload distribution
   - Capacity vs demand
   - Overtime tracking
   - Efficiency metrics

3. **Revenue Analytics**
   - Revenue per staff member
   - Commission calculations
   - Revenue per hour
   - Service mix by staff
   - Upsell performance
   - Add-on sales tracking
   - Revenue trends
   - Target vs actual

4. **Customer Satisfaction**
   - Average rating by staff
   - Review sentiment analysis
   - Customer feedback tracking
   - Net Promoter Score (NPS)
   - Complaint tracking
   - Compliment tracking
   - Customer preferences
   - Loyalty metrics

5. **Productivity Analysis**
   - Bookings per day/week/month
   - Service completion time
   - Break time analysis
   - Multi-tasking efficiency
   - Response time metrics
   - Task completion rate
   - Quality scores
   - Speed vs quality balance

6. **Performance Benchmarking**
   - Staff comparisons
   - Team averages
   - Top performer identification
   - Performance rankings
   - Goal achievement tracking
   - Historical comparisons
   - Industry benchmarks
   - Best practice identification

7. **Goal Tracking**
   - Individual goals
   - Team goals
   - Revenue targets
   - Booking targets
   - Quality targets
   - Goal progress tracking
   - Achievement badges
   - Incentive tracking

### User Roles & Permissions
- **Manager:** Full access, all staff metrics
- **Staff Member:** Own performance only
- **Admin:** Configuration, report access
- **HR:** Performance reviews, training data

---

## 3. Technical Specifications

### Technology Stack
- **Analytics Engine:** PHP + MySQL
- **Visualization:** Chart.js, Recharts
- **Dashboard:** React components
- **Export:** PDF, Excel, CSV
- **Notifications:** Email alerts
- **Gamification:** Points & badges system

### Dependencies
- BookingX Core 2.0+
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- PHP Extensions: mysqli, gd, json

### API Integration Points
```php
// Custom REST API endpoints
- GET  /bookingx/v1/staff/performance-metrics/{staff_id}
- GET  /bookingx/v1/staff/utilization-analysis
- GET  /bookingx/v1/staff/revenue-analytics
- GET  /bookingx/v1/staff/customer-satisfaction
- GET  /bookingx/v1/staff/productivity-metrics
- GET  /bookingx/v1/staff/performance-comparison
- GET  /bookingx/v1/staff/goal-tracking
- POST /bookingx/v1/staff/set-goal
- GET  /bookingx/v1/staff/export-report
- GET  /bookingx/v1/staff/rankings
```

---

## 4. Architecture & Design

### System Architecture
```
┌────────────────────────────────────────────┐
│    Staff Performance Dashboard             │
│  (Individual + Manager Views)              │
└────────────┬───────────────────────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│     Staff Performance Engine               │
│  - Metrics Calculator                      │
│  - Utilization Tracker                     │
│  - Revenue Analyzer                        │
│  - Satisfaction Scorer                     │
└────────────┬───────────────────────────────┘
             │
             ├──────────┬──────────┬──────────┐
             ▼          ▼          ▼          ▼
┌──────────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐
│   Metrics    │ │Utilization││  Revenue  │ │   Goals    │
│  Calculator  │ │  Tracker  │ │ Analyzer │ │  Tracker   │
└──────────────┘ └──────────┘ └──────────┘ └────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│      Staff Analytics Data Store            │
│  - Performance Metrics                     │
│  - Utilization Data                        │
│  - Revenue Data                            │
│  - Goal Progress                           │
└────────────────────────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\StaffPerformance;

class StaffPerformanceManager {
    - init()
    - register_endpoints()
    - get_staff_dashboard($staff_id)
    - calculate_all_metrics($staff_id)
}

class StaffMetricsCalculator {
    - calculate_booking_metrics($staff_id, $period)
    - calculate_revenue_metrics($staff_id, $period)
    - calculate_utilization_rate($staff_id, $period)
    - calculate_satisfaction_score($staff_id, $period)
    - calculate_productivity_score($staff_id)
}

class UtilizationTracker {
    - track_schedule_utilization($staff_id)
    - calculate_billable_hours($staff_id, $period)
    - calculate_idle_time($staff_id, $period)
    - analyze_peak_productivity_times($staff_id)
    - get_workload_distribution()
}

class RevenueAnalyzer {
    - calculate_staff_revenue($staff_id, $period)
    - calculate_commission($staff_id, $period)
    - calculate_revenue_per_hour($staff_id)
    - analyze_service_mix($staff_id)
    - track_upsell_performance($staff_id)
}

class SatisfactionScorer {
    - calculate_average_rating($staff_id)
    - analyze_review_sentiment($staff_id)
    - calculate_nps($staff_id)
    - track_complaints($staff_id)
    - track_compliments($staff_id)
}

class PerformanceComparer {
    - compare_staff_performance($staff_ids)
    - calculate_team_averages()
    - identify_top_performers($metric)
    - generate_rankings($metric)
    - benchmark_against_industry()
}

class GoalTracker {
    - set_staff_goal($staff_id, $goal_data)
    - track_goal_progress($staff_id)
    - check_goal_achievement($staff_id)
    - award_achievement_badge($staff_id, $badge)
    - calculate_incentive_payout($staff_id)
}

class PerformanceReporter {
    - generate_individual_report($staff_id)
    - generate_team_report()
    - generate_review_report($staff_id)
    - export_report($report_data, $format)
}
```

---

## 5. Database Schema

### Table: `bkx_staff_performance_metrics`
```sql
CREATE TABLE bkx_staff_performance_metrics (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    metric_date DATE NOT NULL,
    total_bookings INT DEFAULT 0,
    completed_bookings INT DEFAULT 0,
    cancelled_bookings INT DEFAULT 0,
    no_show_bookings INT DEFAULT 0,
    completion_rate DECIMAL(5,2) DEFAULT 0.00,
    cancellation_rate DECIMAL(5,2) DEFAULT 0.00,
    total_revenue DECIMAL(12,2) DEFAULT 0.00,
    avg_booking_value DECIMAL(10,2) DEFAULT 0.00,
    commission_earned DECIMAL(10,2) DEFAULT 0.00,
    hours_worked DECIMAL(6,2) DEFAULT 0.00,
    billable_hours DECIMAL(6,2) DEFAULT 0.00,
    utilization_rate DECIMAL(5,2) DEFAULT 0.00,
    avg_rating DECIMAL(3,2) DEFAULT 0.00,
    reviews_count INT DEFAULT 0,
    productivity_score DECIMAL(5,2) DEFAULT 0.00,
    created_at DATETIME NOT NULL,
    UNIQUE KEY staff_date_idx (staff_id, metric_date),
    INDEX staff_id_idx (staff_id),
    INDEX metric_date_idx (metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_utilization_tracking`
```sql
CREATE TABLE bkx_staff_utilization_tracking (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    tracking_date DATE NOT NULL,
    time_slot TIME NOT NULL,
    duration_minutes INT NOT NULL,
    slot_type VARCHAR(20) NOT NULL,
    booking_id BIGINT(20) UNSIGNED,
    is_billable TINYINT(1) DEFAULT 0,
    is_break TINYINT(1) DEFAULT 0,
    is_idle TINYINT(1) DEFAULT 0,
    activity_notes TEXT,
    created_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX tracking_date_idx (tracking_date),
    INDEX slot_type_idx (slot_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_revenue_tracking`
```sql
CREATE TABLE bkx_staff_revenue_tracking (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    service_date DATE NOT NULL,
    service_revenue DECIMAL(10,2) NOT NULL,
    addon_revenue DECIMAL(10,2) DEFAULT 0.00,
    tip_amount DECIMAL(10,2) DEFAULT 0.00,
    total_revenue DECIMAL(10,2) NOT NULL,
    commission_rate DECIMAL(5,2),
    commission_amount DECIMAL(10,2),
    payment_method VARCHAR(50),
    is_upsell TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX service_date_idx (service_date),
    INDEX booking_id_idx (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_satisfaction_scores`
```sql
CREATE TABLE bkx_staff_satisfaction_scores (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    rating INT NOT NULL,
    review_text TEXT,
    sentiment_score DECIMAL(5,4),
    sentiment VARCHAR(20),
    is_complaint TINYINT(1) DEFAULT 0,
    is_compliment TINYINT(1) DEFAULT 0,
    nps_score INT,
    created_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX rating_idx (rating),
    INDEX sentiment_idx (sentiment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_goals`
```sql
CREATE TABLE bkx_staff_goals (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    goal_type VARCHAR(50) NOT NULL,
    goal_name VARCHAR(255) NOT NULL,
    target_value DECIMAL(12,2) NOT NULL,
    current_value DECIMAL(12,2) DEFAULT 0.00,
    measurement_unit VARCHAR(50),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    achievement_percent DECIMAL(5,2) DEFAULT 0.00,
    achieved_at DATETIME,
    reward_type VARCHAR(50),
    reward_value DECIMAL(10,2),
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX goal_type_idx (goal_type),
    INDEX status_idx (status),
    INDEX end_date_idx (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_achievements`
```sql
CREATE TABLE bkx_staff_achievements (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    achievement_type VARCHAR(50) NOT NULL,
    achievement_name VARCHAR(255) NOT NULL,
    description TEXT,
    badge_icon VARCHAR(100),
    points_earned INT DEFAULT 0,
    achieved_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX achievement_type_idx (achievement_type),
    INDEX achieved_at_idx (achieved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_rankings`
```sql
CREATE TABLE bkx_staff_rankings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ranking_period VARCHAR(20) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    metric_type VARCHAR(50) NOT NULL,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    rank_position INT NOT NULL,
    metric_value DECIMAL(12,2) NOT NULL,
    percentile DECIMAL(5,2),
    created_at DATETIME NOT NULL,
    UNIQUE KEY ranking_unique (ranking_period, period_start, metric_type, staff_id),
    INDEX staff_id_idx (staff_id),
    INDEX metric_type_idx (metric_type),
    INDEX rank_position_idx (rank_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_staff_commission_rules`
```sql
CREATE TABLE bkx_staff_commission_rules (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED,
    commission_type VARCHAR(20) NOT NULL,
    commission_rate DECIMAL(5,2),
    flat_amount DECIMAL(10,2),
    threshold_amount DECIMAL(12,2),
    tier_level INT,
    is_active TINYINT(1) DEFAULT 1,
    effective_from DATE NOT NULL,
    effective_to DATE,
    created_at DATETIME NOT NULL,
    INDEX staff_id_idx (staff_id),
    INDEX service_id_idx (service_id),
    INDEX is_active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    // Performance tracking
    'enable_staff_analytics' => true,
    'auto_calculate_metrics' => true,
    'metrics_calculation_frequency' => 'daily',

    // Utilization tracking
    'track_utilization' => true,
    'track_idle_time' => true,
    'target_utilization_rate' => 75, // percent

    // Revenue tracking
    'enable_commission_tracking' => true,
    'default_commission_rate' => 20, // percent
    'track_tips' => true,
    'track_upsells' => true,

    // Satisfaction tracking
    'enable_rating_system' => true,
    'enable_sentiment_analysis' => true,
    'track_nps' => true,
    'min_acceptable_rating' => 4.0,

    // Goal setting
    'enable_goal_tracking' => true,
    'allow_staff_self_goals' => false,
    'goal_types' => ['revenue', 'bookings', 'satisfaction', 'utilization'],

    // Gamification
    'enable_achievements' => true,
    'enable_rankings' => true,
    'enable_leaderboard' => true,
    'points_system' => true,

    // Benchmarking
    'enable_team_comparison' => true,
    'show_rankings_to_staff' => true,
    'anonymize_comparisons' => false,

    // Performance reviews
    'review_frequency' => 'quarterly',
    'auto_generate_review_reports' => true,

    // Notifications
    'notify_goal_achievement' => true,
    'notify_low_performance' => true,
    'notify_top_performer' => true,

    // Privacy
    'staff_can_view_own_data' => true,
    'staff_can_view_team_data' => false,
    'managers_can_view_all' => true,

    // Data retention
    'metrics_retention_months' => 24,
    'archive_old_data' => true,
]
```

---

## 7. Staff Metrics Calculator

### Performance Metrics Calculation
```php
class StaffMetricsCalculator {

    public function calculate_comprehensive_metrics($staff_id, $start_date, $end_date) {
        return [
            'staff_id' => $staff_id,
            'period' => ['start' => $start_date, 'end' => $end_date],
            'booking_metrics' => $this->calculate_booking_metrics($staff_id, $start_date, $end_date),
            'revenue_metrics' => $this->calculate_revenue_metrics($staff_id, $start_date, $end_date),
            'utilization_metrics' => $this->calculate_utilization_metrics($staff_id, $start_date, $end_date),
            'satisfaction_metrics' => $this->calculate_satisfaction_metrics($staff_id, $start_date, $end_date),
            'productivity_score' => $this->calculate_productivity_score($staff_id, $start_date, $end_date)
        ];
    }

    private function calculate_booking_metrics($staff_id, $start_date, $end_date) {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_shows
            FROM {$wpdb->prefix}bookingx_bookings
            WHERE staff_id = %d
            AND booking_date BETWEEN %s AND %s
        ", $staff_id, $start_date, $end_date));

        $completion_rate = ($stats->total_bookings > 0) ?
            ($stats->completed / $stats->total_bookings) * 100 : 0;

        $cancellation_rate = ($stats->total_bookings > 0) ?
            ($stats->cancelled / $stats->total_bookings) * 100 : 0;

        $no_show_rate = ($stats->total_bookings > 0) ?
            ($stats->no_shows / $stats->total_bookings) * 100 : 0;

        // Calculate rebooking rate
        $rebooking_rate = $this->calculate_rebooking_rate($staff_id, $start_date, $end_date);

        return [
            'total_bookings' => $stats->total_bookings,
            'completed_bookings' => $stats->completed,
            'cancelled_bookings' => $stats->cancelled,
            'no_show_bookings' => $stats->no_shows,
            'completion_rate' => round($completion_rate, 2),
            'cancellation_rate' => round($cancellation_rate, 2),
            'no_show_rate' => round($no_show_rate, 2),
            'rebooking_rate' => round($rebooking_rate, 2),
            'avg_bookings_per_day' => $this->calculate_avg_bookings_per_day($staff_id, $start_date, $end_date)
        ];
    }

    private function calculate_revenue_metrics($staff_id, $start_date, $end_date) {
        global $wpdb;

        $revenue_stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(total_revenue) as total_revenue,
                SUM(commission_amount) as total_commission,
                SUM(tip_amount) as total_tips,
                AVG(total_revenue) as avg_booking_value,
                COUNT(*) as revenue_bookings
            FROM {$wpdb->prefix}bkx_staff_revenue_tracking
            WHERE staff_id = %d
            AND service_date BETWEEN %s AND %s
        ", $staff_id, $start_date, $end_date));

        // Calculate revenue per hour
        $hours_worked = $this->get_hours_worked($staff_id, $start_date, $end_date);
        $revenue_per_hour = ($hours_worked > 0) ?
            $revenue_stats->total_revenue / $hours_worked : 0;

        // Service mix analysis
        $service_mix = $this->analyze_service_mix($staff_id, $start_date, $end_date);

        // Upsell performance
        $upsell_stats = $this->calculate_upsell_performance($staff_id, $start_date, $end_date);

        return [
            'total_revenue' => round($revenue_stats->total_revenue, 2),
            'total_commission' => round($revenue_stats->total_commission, 2),
            'total_tips' => round($revenue_stats->total_tips, 2),
            'avg_booking_value' => round($revenue_stats->avg_booking_value, 2),
            'revenue_per_hour' => round($revenue_per_hour, 2),
            'service_mix' => $service_mix,
            'upsell_performance' => $upsell_stats
        ];
    }

    private function calculate_utilization_metrics($staff_id, $start_date, $end_date) {
        global $wpdb;

        // Get schedule data
        $schedule_stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                SUM(duration_minutes) / 60 as total_scheduled_hours,
                SUM(CASE WHEN is_billable = 1 THEN duration_minutes ELSE 0 END) / 60 as billable_hours,
                SUM(CASE WHEN is_idle = 1 THEN duration_minutes ELSE 0 END) / 60 as idle_hours,
                SUM(CASE WHEN is_break = 1 THEN duration_minutes ELSE 0 END) / 60 as break_hours
            FROM {$wpdb->prefix}bkx_staff_utilization_tracking
            WHERE staff_id = %d
            AND tracking_date BETWEEN %s AND %s
        ", $staff_id, $start_date, $end_date));

        $utilization_rate = ($schedule_stats->total_scheduled_hours > 0) ?
            ($schedule_stats->billable_hours / $schedule_stats->total_scheduled_hours) * 100 : 0;

        $idle_time_percent = ($schedule_stats->total_scheduled_hours > 0) ?
            ($schedule_stats->idle_hours / $schedule_stats->total_scheduled_hours) * 100 : 0;

        return [
            'total_scheduled_hours' => round($schedule_stats->total_scheduled_hours, 2),
            'billable_hours' => round($schedule_stats->billable_hours, 2),
            'idle_hours' => round($schedule_stats->idle_hours, 2),
            'break_hours' => round($schedule_stats->break_hours, 2),
            'utilization_rate' => round($utilization_rate, 2),
            'idle_time_percent' => round($idle_time_percent, 2),
            'peak_productivity_times' => $this->identify_peak_times($staff_id, $start_date, $end_date)
        ];
    }

    private function calculate_satisfaction_metrics($staff_id, $start_date, $end_date) {
        global $wpdb;

        $satisfaction_stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                AVG(rating) as avg_rating,
                COUNT(*) as total_reviews,
                SUM(CASE WHEN is_complaint = 1 THEN 1 ELSE 0 END) as complaints,
                SUM(CASE WHEN is_compliment = 1 THEN 1 ELSE 0 END) as compliments,
                AVG(sentiment_score) as avg_sentiment,
                AVG(nps_score) as avg_nps
            FROM {$wpdb->prefix}bkx_staff_satisfaction_scores
            WHERE staff_id = %d
            AND created_at BETWEEN %s AND %s
        ", $staff_id, $start_date, $end_date));

        // Rating distribution
        $rating_distribution = $this->get_rating_distribution($staff_id, $start_date, $end_date);

        return [
            'avg_rating' => round($satisfaction_stats->avg_rating, 2),
            'total_reviews' => $satisfaction_stats->total_reviews,
            'complaints' => $satisfaction_stats->complaints,
            'compliments' => $satisfaction_stats->compliments,
            'avg_sentiment' => round($satisfaction_stats->avg_sentiment, 4),
            'nps_score' => round($satisfaction_stats->avg_nps, 2),
            'rating_distribution' => $rating_distribution
        ];
    }

    private function calculate_productivity_score($staff_id, $start_date, $end_date) {
        $metrics = $this->calculate_comprehensive_metrics($staff_id, $start_date, $end_date);

        // Weighted productivity score (0-100)
        $score = 0;

        // Booking completion (25%)
        $score += ($metrics['booking_metrics']['completion_rate'] * 0.25);

        // Utilization rate (25%)
        $score += ($metrics['utilization_metrics']['utilization_rate'] * 0.25);

        // Customer satisfaction (30%)
        $rating_score = ($metrics['satisfaction_metrics']['avg_rating'] / 5) * 100;
        $score += ($rating_score * 0.30);

        // Revenue performance (20%)
        $target_revenue = $this->get_revenue_target($staff_id);
        $revenue_achievement = ($target_revenue > 0) ?
            min(100, ($metrics['revenue_metrics']['total_revenue'] / $target_revenue) * 100) : 50;
        $score += ($revenue_achievement * 0.20);

        return round($score, 2);
    }

    private function calculate_rebooking_rate($staff_id, $start_date, $end_date) {
        global $wpdb;

        // Get customers who booked with this staff in the period
        $customers_in_period = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT customer_id
            FROM {$wpdb->prefix}bookingx_bookings
            WHERE staff_id = %d
            AND booking_date BETWEEN %s AND %s
            AND status = 'completed'
        ", $staff_id, $start_date, $end_date));

        if (empty($customers_in_period)) {
            return 0;
        }

        // Count how many came back for another booking
        $placeholders = implode(',', array_fill(0, count($customers_in_period), '%d'));

        $returning_customers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT customer_id)
            FROM {$wpdb->prefix}bookingx_bookings
            WHERE staff_id = %d
            AND customer_id IN ($placeholders)
            AND booking_date > %s
            AND status IN ('completed', 'confirmed')
        ", $staff_id, ...$customers_in_period, $end_date));

        return ($returning_customers / count($customers_in_period)) * 100;
    }
}
```

---

## 8. Performance Comparison & Rankings

### Staff Performance Comparer
```php
class PerformanceComparer {

    public function compare_staff_performance($staff_ids, $period) {
        $comparison = [];

        foreach ($staff_ids as $staff_id) {
            $calculator = new StaffMetricsCalculator();
            $metrics = $calculator->calculate_comprehensive_metrics(
                $staff_id,
                $period['start'],
                $period['end']
            );

            $comparison[] = [
                'staff_id' => $staff_id,
                'staff_name' => $this->get_staff_name($staff_id),
                'metrics' => $metrics,
                'overall_score' => $metrics['productivity_score']
            ];
        }

        // Sort by productivity score
        usort($comparison, function($a, $b) {
            return $b['overall_score'] <=> $a['overall_score'];
        });

        // Add rankings
        foreach ($comparison as $index => &$staff) {
            $staff['rank'] = $index + 1;
            $staff['percentile'] = (1 - ($index / count($comparison))) * 100;
        }

        return [
            'period' => $period,
            'staff_count' => count($comparison),
            'comparison' => $comparison,
            'team_averages' => $this->calculate_team_averages($comparison),
            'top_performers' => array_slice($comparison, 0, 3)
        ];
    }

    private function calculate_team_averages($comparison) {
        $metrics_sum = [
            'total_bookings' => 0,
            'total_revenue' => 0,
            'avg_rating' => 0,
            'utilization_rate' => 0,
            'productivity_score' => 0
        ];

        foreach ($comparison as $staff) {
            $metrics_sum['total_bookings'] += $staff['metrics']['booking_metrics']['total_bookings'];
            $metrics_sum['total_revenue'] += $staff['metrics']['revenue_metrics']['total_revenue'];
            $metrics_sum['avg_rating'] += $staff['metrics']['satisfaction_metrics']['avg_rating'];
            $metrics_sum['utilization_rate'] += $staff['metrics']['utilization_metrics']['utilization_rate'];
            $metrics_sum['productivity_score'] += $staff['overall_score'];
        }

        $count = count($comparison);

        return [
            'avg_bookings' => round($metrics_sum['total_bookings'] / $count, 2),
            'avg_revenue' => round($metrics_sum['total_revenue'] / $count, 2),
            'avg_rating' => round($metrics_sum['avg_rating'] / $count, 2),
            'avg_utilization' => round($metrics_sum['utilization_rate'] / $count, 2),
            'avg_productivity' => round($metrics_sum['productivity_score'] / $count, 2)
        ];
    }

    public function generate_rankings($metric_type, $period) {
        global $wpdb;

        // Get all active staff
        $staff_ids = $wpdb->get_col("
            SELECT id
            FROM {$wpdb->prefix}bookingx_staff
            WHERE is_active = 1
        ");

        $rankings = [];

        foreach ($staff_ids as $staff_id) {
            $metric_value = $this->get_metric_value($staff_id, $metric_type, $period);

            $rankings[] = [
                'staff_id' => $staff_id,
                'staff_name' => $this->get_staff_name($staff_id),
                'metric_value' => $metric_value
            ];
        }

        // Sort by metric value (descending)
        usort($rankings, function($a, $b) {
            return $b['metric_value'] <=> $a['metric_value'];
        });

        // Add rank positions
        foreach ($rankings as $index => &$rank) {
            $rank['position'] = $index + 1;
            $rank['percentile'] = round((1 - ($index / count($rankings))) * 100, 2);
        }

        // Store rankings
        $this->store_rankings($rankings, $metric_type, $period);

        return $rankings;
    }

    private function get_metric_value($staff_id, $metric_type, $period) {
        $calculator = new StaffMetricsCalculator();
        $metrics = $calculator->calculate_comprehensive_metrics(
            $staff_id,
            $period['start'],
            $period['end']
        );

        switch ($metric_type) {
            case 'revenue':
                return $metrics['revenue_metrics']['total_revenue'];
            case 'bookings':
                return $metrics['booking_metrics']['total_bookings'];
            case 'satisfaction':
                return $metrics['satisfaction_metrics']['avg_rating'];
            case 'productivity':
                return $metrics['productivity_score'];
            default:
                return 0;
        }
    }

    public function identify_top_performers($metric_type, $period, $limit = 5) {
        $rankings = $this->generate_rankings($metric_type, $period);

        return array_slice($rankings, 0, $limit);
    }
}
```

---

## 9. Goal Tracking System

### Goal Tracker
```php
class GoalTracker {

    public function set_staff_goal($staff_id, $goal_data) {
        global $wpdb;

        $goal = [
            'staff_id' => $staff_id,
            'goal_type' => $goal_data['type'],
            'goal_name' => $goal_data['name'],
            'target_value' => $goal_data['target'],
            'measurement_unit' => $goal_data['unit'],
            'start_date' => $goal_data['start_date'],
            'end_date' => $goal_data['end_date'],
            'status' => 'active',
            'reward_type' => $goal_data['reward_type'] ?? null,
            'reward_value' => $goal_data['reward_value'] ?? null,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $wpdb->insert($wpdb->prefix . 'bkx_staff_goals', $goal);

        return $wpdb->insert_id;
    }

    public function track_goal_progress($staff_id) {
        global $wpdb;

        // Get active goals
        $goals = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}bkx_staff_goals
            WHERE staff_id = %d
            AND status = 'active'
            AND end_date >= CURDATE()
        ", $staff_id));

        $goal_progress = [];

        foreach ($goals as $goal) {
            $current_value = $this->calculate_current_value($goal);
            $achievement_percent = ($goal->target_value > 0) ?
                ($current_value / $goal->target_value) * 100 : 0;

            // Update goal progress
            $wpdb->update(
                $wpdb->prefix . 'bkx_staff_goals',
                [
                    'current_value' => $current_value,
                    'achievement_percent' => $achievement_percent,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $goal->id]
            );

            // Check if goal achieved
            if ($achievement_percent >= 100 && $goal->status !== 'achieved') {
                $this->mark_goal_achieved($goal->id, $staff_id);
            }

            $goal_progress[] = [
                'goal_id' => $goal->id,
                'goal_name' => $goal->goal_name,
                'target_value' => $goal->target_value,
                'current_value' => $current_value,
                'achievement_percent' => round($achievement_percent, 2),
                'remaining' => max(0, $goal->target_value - $current_value),
                'days_remaining' => $this->calculate_days_remaining($goal->end_date),
                'on_track' => $this->is_on_track($goal, $current_value)
            ];
        }

        return $goal_progress;
    }

    private function calculate_current_value($goal) {
        $calculator = new StaffMetricsCalculator();
        $metrics = $calculator->calculate_comprehensive_metrics(
            $goal->staff_id,
            $goal->start_date,
            date('Y-m-d')
        );

        switch ($goal->goal_type) {
            case 'revenue':
                return $metrics['revenue_metrics']['total_revenue'];
            case 'bookings':
                return $metrics['booking_metrics']['total_bookings'];
            case 'satisfaction':
                return $metrics['satisfaction_metrics']['avg_rating'];
            case 'utilization':
                return $metrics['utilization_metrics']['utilization_rate'];
            default:
                return 0;
        }
    }

    private function mark_goal_achieved($goal_id, $staff_id) {
        global $wpdb;

        // Update goal status
        $wpdb->update(
            $wpdb->prefix . 'bkx_staff_goals',
            [
                'status' => 'achieved',
                'achieved_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $goal_id]
        );

        // Award achievement badge
        $goal = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}bkx_staff_goals WHERE id = %d
        ", $goal_id));

        $this->award_achievement_badge($staff_id, [
            'type' => 'goal_achievement',
            'name' => 'Goal Achieved: ' . $goal->goal_name,
            'description' => 'Achieved goal: ' . $goal->goal_name,
            'points' => 100
        ]);

        // Send notification
        $this->send_goal_achievement_notification($staff_id, $goal);
    }

    private function award_achievement_badge($staff_id, $badge_data) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'bkx_staff_achievements',
            [
                'staff_id' => $staff_id,
                'achievement_type' => $badge_data['type'],
                'achievement_name' => $badge_data['name'],
                'description' => $badge_data['description'],
                'points_earned' => $badge_data['points'],
                'achieved_at' => current_time('mysql')
            ]
        );
    }

    private function is_on_track($goal, $current_value) {
        $days_total = (strtotime($goal->end_date) - strtotime($goal->start_date)) / 86400;
        $days_elapsed = (time() - strtotime($goal->start_date)) / 86400;

        if ($days_total <= 0) {
            return false;
        }

        $expected_progress = ($days_elapsed / $days_total) * $goal->target_value;

        return $current_value >= $expected_progress;
    }
}
```

---

## 10. Visualization Components

### Chart Configurations
```javascript
// Staff Performance Radar Chart
const performanceRadarConfig = {
    type: 'radar',
    data: {
        labels: ['Bookings', 'Revenue', 'Utilization', 'Satisfaction', 'Productivity'],
        datasets: [{
            label: 'Staff Performance',
            data: [],
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)'
        }]
    },
    options: {
        scales: {
            r: {
                beginAtZero: true,
                max: 100
            }
        }
    }
};

// Revenue Trend Line Chart
const revenueTrendConfig = {
    type: 'line',
    data: {
        labels: [],
        datasets: [{
            label: 'Revenue',
            data: [],
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.4
        }]
    }
};

// Staff Comparison Bar Chart
const staffComparisonConfig = {
    type: 'bar',
    data: {
        labels: [],
        datasets: [{
            label: 'Performance Score',
            data: [],
            backgroundColor: 'rgba(75, 192, 192, 0.8)'
        }]
    }
};
```

---

## 11. Security & Privacy

### Data Security
- **Access Control:** Role-based report access
- **Privacy:** Staff only see own data by default
- **Sensitive Data:** Protect personal information
- **Audit Logging:** Track all access to reports

---

## 12. Testing Strategy

### Unit Tests
```php
- test_metrics_calculation()
- test_utilization_tracking()
- test_goal_tracking()
- test_performance_comparison()
```

---

## 13. Development Timeline

**Total Timeline:** 10 weeks (2.5 months)

---

## 14. Success Metrics

### Technical Metrics
- Metrics calculation < 2 seconds
- Dashboard load time < 2 seconds
- Data accuracy > 99%

### Business Metrics
- Staff engagement improvement > 20%
- Performance visibility > 90%
- Goal achievement rate > 60%

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
