# Business Intelligence Dashboard - Development Documentation

## 1. Overview

**Add-on Name:** Business Intelligence Dashboard
**Price:** $149
**Category:** Analytics & Business Intelligence
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Enterprise-grade BI platform with advanced analytics, revenue forecasting, customer lifetime value analysis, predictive modeling, and comprehensive business metrics. Transform booking data into actionable insights with interactive dashboards, automated reporting, and real-time KPI monitoring.

### Value Proposition
- Advanced revenue forecasting with machine learning
- Customer lifetime value (CLV) prediction
- Real-time business intelligence dashboards
- Predictive analytics and trend analysis
- Executive-level reporting and insights
- Multi-dimensional data analysis
- Automated alerts and notifications
- Custom KPI tracking and monitoring
- Data-driven decision making tools
- Export to all major formats

---

## 2. Features & Requirements

### Core Features
1. **Revenue Forecasting**
   - Time series analysis
   - Seasonal decomposition
   - Trend prediction (7, 30, 90, 365 days)
   - Multiple forecasting models (ARIMA, exponential smoothing)
   - Confidence intervals and variance analysis
   - Revenue scenario planning
   - Growth rate calculations
   - Year-over-year comparisons

2. **Customer Lifetime Value (CLV) Analysis**
   - Historical CLV calculation
   - Predictive CLV modeling
   - Customer segmentation by value
   - Cohort analysis
   - Retention rate analysis
   - Churn prediction
   - Customer acquisition cost (CAC) tracking
   - CLV/CAC ratio optimization

3. **Executive Dashboards**
   - Real-time KPI monitoring
   - Customizable dashboard layouts
   - Interactive data visualizations
   - Drill-down capabilities
   - Multi-metric comparisons
   - Goal tracking and progress
   - Performance scorecards
   - Alert configuration

4. **Advanced Analytics**
   - Multi-dimensional analysis
   - Correlation analysis
   - Regression modeling
   - What-if scenario analysis
   - Statistical significance testing
   - Outlier detection
   - Pattern recognition
   - Anomaly detection

5. **Predictive Modeling**
   - Demand forecasting
   - Revenue predictions
   - Customer churn prediction
   - Booking likelihood scoring
   - Optimal pricing recommendations
   - Capacity optimization
   - Resource allocation forecasting

6. **Custom Reports & Exports**
   - Scheduled report generation
   - PDF, Excel, CSV exports
   - Email distribution
   - Report templates
   - White-label reporting
   - Interactive web reports
   - API data access

### User Roles & Permissions
- **Executive:** Full dashboard access, all reports
- **Admin:** Configuration, report creation, data access
- **Manager:** View all dashboards, generate reports
- **Analyst:** Data analysis, custom queries
- **Staff:** Limited dashboard views only

---

## 3. Technical Specifications

### Technology Stack
- **Frontend Framework:** React.js 18+ with TypeScript
- **Chart Library:** Chart.js 4.x, D3.js 7.x, Recharts 2.x
- **Data Visualization:** Apache ECharts 5.x
- **Grid System:** AG-Grid Enterprise 30.x
- **Statistical Library:** Simple Statistics, Math.js
- **Export Engine:** jsPDF, SheetJS (xlsx)
- **State Management:** Redux Toolkit
- **API:** WordPress REST API + Custom Endpoints

### Dependencies
- BookingX Core 2.0+
- PHP 7.4+ (8.0+ recommended for better performance)
- MySQL 5.7+ or MariaDB 10.3+
- PHP Extensions: mysqli, gd, zip, mbstring
- Node.js 16+ (for build process)
- WordPress REST API enabled
- Adequate server memory (2GB+ recommended)

### Visualization Libraries
```javascript
// Chart.js - Primary charting library
{
  "chart.js": "^4.4.0",
  "react-chartjs-2": "^5.2.0"
}

// D3.js - Advanced visualizations
{
  "d3": "^7.8.5",
  "d3-scale": "^4.0.2",
  "d3-shape": "^3.2.0"
}

// Apache ECharts - Business charts
{
  "echarts": "^5.4.3",
  "echarts-for-react": "^3.0.2"
}

// Recharts - React native charts
{
  "recharts": "^2.10.0"
}

// Additional utilities
{
  "date-fns": "^2.30.0",
  "lodash": "^4.17.21",
  "numeral": "^2.0.6",
  "simple-statistics": "^7.8.3"
}
```

### API Integration Points
```php
// Custom REST API endpoints
- GET  /bookingx/v1/bi/dashboard-metrics
- GET  /bookingx/v1/bi/revenue-forecast
- GET  /bookingx/v1/bi/clv-analysis
- GET  /bookingx/v1/bi/cohort-analysis
- GET  /bookingx/v1/bi/trend-analysis
- GET  /bookingx/v1/bi/predictive-model/{type}
- POST /bookingx/v1/bi/custom-query
- POST /bookingx/v1/bi/export-report
- GET  /bookingx/v1/bi/kpi-tracking
- POST /bookingx/v1/bi/save-dashboard-layout
```

---

## 4. Architecture & Design

### System Architecture
```
┌────────────────────────────────────────────┐
│         Executive Dashboard UI             │
│  (React + Redux + Chart.js + D3.js)       │
└────────────┬───────────────────────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│      Business Intelligence API Layer       │
│  - Metric Calculations                     │
│  - Forecast Engine                         │
│  - CLV Calculator                          │
│  - Report Generator                        │
└────────────┬───────────────────────────────┘
             │
             ├──────────┬──────────┬──────────┐
             ▼          ▼          ▼          ▼
┌───────────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐
│  Data         │ │ Forecast │ │   CLV    │ │   Report   │
│  Aggregator   │ │  Engine  │ │ Analyzer │ │  Builder   │
└───────────────┘ └──────────┘ └──────────┘ └────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│        Analytics Data Warehouse            │
│  - Fact Tables                             │
│  - Dimension Tables                        │
│  - Aggregated Metrics                      │
└────────────────────────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\BusinessIntelligence;

class BIDashboardManager {
    - init()
    - register_endpoints()
    - get_dashboard_metrics()
    - save_dashboard_layout()
    - get_user_dashboards()
}

class RevenueForecastEngine {
    - forecast_revenue($days, $method)
    - calculate_trend($data)
    - seasonal_decomposition($data)
    - apply_arima_model($data)
    - calculate_confidence_intervals()
    - get_forecast_accuracy()
}

class CLVAnalyzer {
    - calculate_historical_clv($customer_id)
    - predict_future_clv($customer_id)
    - segment_by_clv()
    - calculate_cohort_clv()
    - get_retention_rates()
    - calculate_churn_probability()
}

class MetricsCalculator {
    - calculate_kpi($metric_name, $period)
    - get_real_time_metrics()
    - compare_periods($metric, $period1, $period2)
    - calculate_growth_rate()
    - calculate_variance()
}

class DataAggregator {
    - aggregate_daily_metrics()
    - aggregate_weekly_metrics()
    - aggregate_monthly_metrics()
    - build_fact_tables()
    - update_dimension_tables()
}

class PredictiveModel {
    - predict_demand($date_range)
    - predict_churn($customer_id)
    - predict_booking_probability()
    - recommend_pricing()
    - optimize_capacity()
}

class ReportBuilder {
    - create_report($template, $parameters)
    - schedule_report($config)
    - export_to_pdf($report_data)
    - export_to_excel($report_data)
    - email_report($recipients, $report)
}

class ChartDataProvider {
    - prepare_line_chart_data()
    - prepare_bar_chart_data()
    - prepare_pie_chart_data()
    - prepare_heatmap_data()
    - prepare_scatter_data()
}
```

---

## 5. Database Schema

### Table: `bkx_bi_fact_revenue`
```sql
CREATE TABLE bkx_bi_fact_revenue (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_key INT NOT NULL,
    customer_key BIGINT(20) UNSIGNED,
    service_key BIGINT(20) UNSIGNED,
    staff_key BIGINT(20) UNSIGNED,
    location_key BIGINT(20) UNSIGNED,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    revenue_amount DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    net_revenue DECIMAL(12,2) NOT NULL,
    transaction_count INT DEFAULT 1,
    new_customer TINYINT(1) DEFAULT 0,
    payment_method VARCHAR(50),
    booking_source VARCHAR(50),
    created_at DATETIME NOT NULL,
    INDEX date_key_idx (date_key),
    INDEX customer_key_idx (customer_key),
    INDEX service_key_idx (service_key),
    INDEX staff_key_idx (staff_key),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_bi_fact_bookings`
```sql
CREATE TABLE bkx_bi_fact_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_key INT NOT NULL,
    customer_key BIGINT(20) UNSIGNED,
    service_key BIGINT(20) UNSIGNED,
    staff_key BIGINT(20) UNSIGNED,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    booking_status VARCHAR(50) NOT NULL,
    duration_minutes INT,
    is_completed TINYINT(1) DEFAULT 0,
    is_cancelled TINYINT(1) DEFAULT 0,
    is_no_show TINYINT(1) DEFAULT 0,
    booking_channel VARCHAR(50),
    lead_time_days INT,
    created_at DATETIME NOT NULL,
    INDEX date_key_idx (date_key),
    INDEX customer_key_idx (customer_key),
    INDEX service_key_idx (service_key),
    INDEX booking_status_idx (booking_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_bi_dim_date`
```sql
CREATE TABLE bkx_bi_dim_date (
    date_key INT PRIMARY KEY,
    date_value DATE NOT NULL UNIQUE,
    day_of_week TINYINT,
    day_name VARCHAR(10),
    day_of_month TINYINT,
    day_of_year SMALLINT,
    week_of_year TINYINT,
    month_number TINYINT,
    month_name VARCHAR(10),
    quarter TINYINT,
    year SMALLINT,
    is_weekend TINYINT(1),
    is_holiday TINYINT(1),
    fiscal_year SMALLINT,
    fiscal_quarter TINYINT,
    INDEX date_value_idx (date_value),
    INDEX year_month_idx (year, month_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_bi_customer_metrics`
```sql
CREATE TABLE bkx_bi_customer_metrics (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL UNIQUE,
    first_booking_date DATE,
    last_booking_date DATE,
    total_bookings INT DEFAULT 0,
    completed_bookings INT DEFAULT 0,
    cancelled_bookings INT DEFAULT 0,
    no_show_count INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0.00,
    average_booking_value DECIMAL(10,2) DEFAULT 0.00,
    lifetime_value DECIMAL(12,2) DEFAULT 0.00,
    predicted_clv DECIMAL(12,2),
    churn_probability DECIMAL(5,4),
    customer_segment VARCHAR(50),
    rfm_score VARCHAR(10),
    recency_days INT,
    frequency_score TINYINT,
    monetary_score TINYINT,
    last_calculated_at DATETIME,
    INDEX customer_id_idx (customer_id),
    INDEX customer_segment_idx (customer_segment),
    INDEX lifetime_value_idx (lifetime_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_bi_revenue_forecast`
```sql
CREATE TABLE bkx_bi_revenue_forecast (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    forecast_date DATE NOT NULL,
    forecast_horizon_days INT NOT NULL,
    forecast_method VARCHAR(50) NOT NULL,
    predicted_revenue DECIMAL(12,2) NOT NULL,
    lower_bound DECIMAL(12,2),
    upper_bound DECIMAL(12,2),
    confidence_level DECIMAL(5,2),
    actual_revenue DECIMAL(12,2),
    forecast_accuracy DECIMAL(5,2),
    model_parameters LONGTEXT,
    created_at DATETIME NOT NULL,
    UNIQUE KEY forecast_unique (forecast_date, forecast_horizon_days, forecast_method),
    INDEX forecast_date_idx (forecast_date),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_bi_cohort_analysis`
```sql
CREATE TABLE bkx_bi_cohort_analysis (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cohort_month DATE NOT NULL,
    period_number INT NOT NULL,
    customers_count INT NOT NULL,
    active_customers INT NOT NULL,
    retention_rate DECIMAL(5,2),
    total_revenue DECIMAL(12,2),
    average_revenue_per_customer DECIMAL(10,2),
    cumulative_revenue DECIMAL(12,2),
    churn_count INT DEFAULT 0,
    churn_rate DECIMAL(5,2),
    calculated_at DATETIME NOT NULL,
    UNIQUE KEY cohort_period_idx (cohort_month, period_number),
    INDEX cohort_month_idx (cohort_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_bi_kpi_tracking`
```sql
CREATE TABLE bkx_bi_kpi_tracking (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kpi_name VARCHAR(100) NOT NULL,
    kpi_category VARCHAR(50) NOT NULL,
    measurement_date DATE NOT NULL,
    actual_value DECIMAL(15,4) NOT NULL,
    target_value DECIMAL(15,4),
    previous_value DECIMAL(15,4),
    variance_percent DECIMAL(7,2),
    status VARCHAR(20),
    unit_type VARCHAR(20),
    metadata LONGTEXT,
    created_at DATETIME NOT NULL,
    UNIQUE KEY kpi_date_idx (kpi_name, measurement_date),
    INDEX kpi_name_idx (kpi_name),
    INDEX measurement_date_idx (measurement_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_bi_dashboards`
```sql
CREATE TABLE bkx_bi_dashboards (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    dashboard_name VARCHAR(255) NOT NULL,
    dashboard_type VARCHAR(50) DEFAULT 'custom',
    layout_config LONGTEXT NOT NULL,
    widgets LONGTEXT NOT NULL,
    refresh_interval INT DEFAULT 300,
    is_default TINYINT(1) DEFAULT 0,
    is_shared TINYINT(1) DEFAULT 0,
    shared_with LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX user_id_idx (user_id),
    INDEX dashboard_type_idx (dashboard_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_bi_scheduled_reports`
```sql
CREATE TABLE bkx_bi_scheduled_reports (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_name VARCHAR(255) NOT NULL,
    report_type VARCHAR(50) NOT NULL,
    schedule_frequency VARCHAR(20) NOT NULL,
    schedule_time TIME,
    schedule_day VARCHAR(20),
    recipients LONGTEXT NOT NULL,
    report_config LONGTEXT NOT NULL,
    export_format VARCHAR(20) DEFAULT 'pdf',
    is_active TINYINT(1) DEFAULT 1,
    last_sent_at DATETIME,
    next_send_at DATETIME,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX schedule_frequency_idx (schedule_frequency),
    INDEX next_send_at_idx (next_send_at),
    INDEX is_active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_bi_predictive_models`
```sql
CREATE TABLE bkx_bi_predictive_models (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_type VARCHAR(50) NOT NULL,
    model_name VARCHAR(255) NOT NULL,
    algorithm VARCHAR(50) NOT NULL,
    training_data_start DATE,
    training_data_end DATE,
    model_parameters LONGTEXT,
    feature_importance LONGTEXT,
    accuracy_score DECIMAL(5,4),
    precision_score DECIMAL(5,4),
    recall_score DECIMAL(5,4),
    f1_score DECIMAL(5,4),
    is_active TINYINT(1) DEFAULT 1,
    trained_at DATETIME,
    last_used_at DATETIME,
    usage_count INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX model_type_idx (model_type),
    INDEX is_active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    'enable_bi_dashboard' => true,
    'enable_forecasting' => true,
    'enable_clv_analysis' => true,
    'enable_predictive_models' => true,

    // Data refresh settings
    'auto_aggregate_data' => true,
    'aggregation_frequency' => 'hourly', // hourly, daily
    'data_retention_days' => 730, // 2 years

    // Forecasting settings
    'default_forecast_method' => 'exponential_smoothing',
    'forecast_confidence_level' => 95,
    'enable_seasonal_adjustment' => true,

    // CLV settings
    'clv_calculation_method' => 'predictive', // historical, predictive
    'discount_rate' => 0.10, // 10% annual discount rate
    'clv_time_horizon_months' => 24,

    // Dashboard settings
    'default_date_range' => '30_days',
    'enable_real_time_updates' => true,
    'dashboard_refresh_interval' => 300, // seconds
    'max_widgets_per_dashboard' => 12,

    // Export settings
    'enable_pdf_export' => true,
    'enable_excel_export' => true,
    'enable_csv_export' => true,
    'max_export_rows' => 10000,

    // Alert settings
    'enable_kpi_alerts' => true,
    'alert_notification_methods' => ['email', 'in_app'],
    'alert_check_frequency' => 'hourly',

    // Performance settings
    'enable_query_caching' => true,
    'cache_ttl_seconds' => 600,
    'max_concurrent_calculations' => 5,

    // Access control
    'allowed_roles' => ['administrator', 'executive', 'manager'],
    'enable_dashboard_sharing' => true,
    'allow_custom_queries' => false,
]
```

---

## 7. Revenue Forecasting Engine

### Forecasting Algorithms

#### Exponential Smoothing
```php
class ExponentialSmoothingForecaster {

    public function forecast($historical_data, $periods_ahead, $alpha = 0.3) {
        $forecasts = [];
        $smoothed_values = [];

        // Initialize with first value
        $smoothed_values[0] = $historical_data[0];

        // Calculate smoothed values
        for ($i = 1; $i < count($historical_data); $i++) {
            $smoothed_values[$i] = $alpha * $historical_data[$i] +
                                   (1 - $alpha) * $smoothed_values[$i - 1];
        }

        // Forecast future periods
        $last_smoothed = end($smoothed_values);
        for ($i = 1; $i <= $periods_ahead; $i++) {
            $forecasts[$i] = $last_smoothed;
        }

        return [
            'forecasts' => $forecasts,
            'smoothed_values' => $smoothed_values,
            'method' => 'exponential_smoothing',
            'alpha' => $alpha,
        ];
    }

    public function holt_winters($data, $periods_ahead, $season_length = 7) {
        // Holt-Winters method with seasonal adjustment
        $alpha = 0.3; // Level
        $beta = 0.1;  // Trend
        $gamma = 0.2; // Seasonal

        $level = [];
        $trend = [];
        $seasonal = [];
        $forecasts = [];

        // Initialize components
        $level[0] = $data[0];
        $trend[0] = ($data[1] - $data[0]);

        // Initialize seasonal factors
        for ($i = 0; $i < $season_length; $i++) {
            $seasonal[$i] = $data[$i] / $level[0];
        }

        // Calculate level, trend, and seasonal components
        for ($i = 1; $i < count($data); $i++) {
            $season_idx = $i % $season_length;

            $level[$i] = $alpha * ($data[$i] / $seasonal[$season_idx]) +
                        (1 - $alpha) * ($level[$i - 1] + $trend[$i - 1]);

            $trend[$i] = $beta * ($level[$i] - $level[$i - 1]) +
                        (1 - $beta) * $trend[$i - 1];

            $seasonal[$i + $season_length] = $gamma * ($data[$i] / $level[$i]) +
                                             (1 - $gamma) * $seasonal[$season_idx];
        }

        // Generate forecasts
        $last_level = end($level);
        $last_trend = end($trend);

        for ($i = 1; $i <= $periods_ahead; $i++) {
            $season_idx = (count($data) + $i - 1) % $season_length;
            $forecast = ($last_level + $i * $last_trend) *
                       $seasonal[count($data) + $season_idx - $season_length];
            $forecasts[$i] = $forecast;
        }

        return [
            'forecasts' => $forecasts,
            'method' => 'holt_winters',
            'seasonal_length' => $season_length,
        ];
    }
}
```

#### Moving Average & Trend Analysis
```php
class TrendAnalyzer {

    public function calculate_moving_average($data, $window_size = 7) {
        $moving_averages = [];

        for ($i = $window_size - 1; $i < count($data); $i++) {
            $window = array_slice($data, $i - $window_size + 1, $window_size);
            $moving_averages[$i] = array_sum($window) / $window_size;
        }

        return $moving_averages;
    }

    public function linear_regression($data) {
        $n = count($data);
        $x_values = range(1, $n);
        $y_values = array_values($data);

        $x_mean = array_sum($x_values) / $n;
        $y_mean = array_sum($y_values) / $n;

        $numerator = 0;
        $denominator = 0;

        for ($i = 0; $i < $n; $i++) {
            $numerator += ($x_values[$i] - $x_mean) * ($y_values[$i] - $y_mean);
            $denominator += pow($x_values[$i] - $x_mean, 2);
        }

        $slope = $numerator / $denominator;
        $intercept = $y_mean - $slope * $x_mean;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'equation' => "y = {$slope}x + {$intercept}",
        ];
    }

    public function forecast_with_trend($data, $periods_ahead) {
        $regression = $this->linear_regression($data);
        $forecasts = [];

        $n = count($data);
        for ($i = 1; $i <= $periods_ahead; $i++) {
            $x = $n + $i;
            $forecasts[$i] = $regression['slope'] * $x + $regression['intercept'];
        }

        return $forecasts;
    }

    public function calculate_confidence_intervals($forecast, $historical_data, $confidence_level = 0.95) {
        $std_dev = $this->calculate_standard_deviation($historical_data);
        $z_score = $this->get_z_score($confidence_level);

        return [
            'forecast' => $forecast,
            'lower_bound' => $forecast - ($z_score * $std_dev),
            'upper_bound' => $forecast + ($z_score * $std_dev),
            'confidence_level' => $confidence_level * 100 . '%',
        ];
    }

    private function calculate_standard_deviation($data) {
        $mean = array_sum($data) / count($data);
        $variance = 0;

        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }

        return sqrt($variance / count($data));
    }

    private function get_z_score($confidence_level) {
        $z_scores = [
            0.90 => 1.645,
            0.95 => 1.96,
            0.99 => 2.576,
        ];

        return $z_scores[$confidence_level] ?? 1.96;
    }
}
```

---

## 8. Customer Lifetime Value (CLV) Analysis

### CLV Calculation Methods

```php
class CLVCalculator {

    public function calculate_historical_clv($customer_id) {
        global $wpdb;

        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_bookings,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_booking_value,
                MIN(created_at) as first_booking,
                MAX(created_at) as last_booking,
                DATEDIFF(MAX(created_at), MIN(created_at)) as customer_age_days
            FROM {$wpdb->prefix}bookingx_bookings
            WHERE customer_id = %d
            AND status = 'completed'
        ", $customer_id));

        if (!$metrics || $metrics->total_bookings == 0) {
            return null;
        }

        // Calculate purchase frequency
        $customer_age_months = max(1, $metrics->customer_age_days / 30);
        $purchase_frequency = $metrics->total_bookings / $customer_age_months;

        return [
            'customer_id' => $customer_id,
            'historical_clv' => $metrics->total_revenue,
            'total_bookings' => $metrics->total_bookings,
            'average_booking_value' => $metrics->avg_booking_value,
            'purchase_frequency_monthly' => $purchase_frequency,
            'customer_age_days' => $metrics->customer_age_days,
            'first_booking_date' => $metrics->first_booking,
            'last_booking_date' => $metrics->last_booking,
        ];
    }

    public function predict_future_clv($customer_id, $time_horizon_months = 24, $discount_rate = 0.10) {
        $historical = $this->calculate_historical_clv($customer_id);

        if (!$historical) {
            return null;
        }

        // Get retention probability
        $retention_rate = $this->calculate_retention_rate($customer_id);

        // Predict future value using probabilistic model
        $predicted_clv = 0;
        $monthly_rate = $discount_rate / 12;

        for ($month = 1; $month <= $time_horizon_months; $month++) {
            // Probability customer is still active in this month
            $survival_probability = pow($retention_rate, $month);

            // Expected revenue this month
            $expected_monthly_revenue = $historical['average_booking_value'] *
                                       $historical['purchase_frequency_monthly'] *
                                       $survival_probability;

            // Discount to present value
            $discount_factor = 1 / pow(1 + $monthly_rate, $month);
            $predicted_clv += $expected_monthly_revenue * $discount_factor;
        }

        return [
            'customer_id' => $customer_id,
            'predicted_clv' => round($predicted_clv, 2),
            'historical_clv' => $historical['historical_clv'],
            'total_clv' => round($historical['historical_clv'] + $predicted_clv, 2),
            'time_horizon_months' => $time_horizon_months,
            'retention_rate' => $retention_rate,
            'discount_rate' => $discount_rate,
        ];
    }

    public function calculate_retention_rate($customer_id) {
        global $wpdb;

        // Get booking patterns
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(created_at) as booking_date
            FROM {$wpdb->prefix}bookingx_bookings
            WHERE customer_id = %d
            AND status = 'completed'
            ORDER BY created_at
        ", $customer_id));

        if (count($bookings) < 2) {
            return 0.5; // Default 50% retention for new customers
        }

        // Calculate average days between bookings
        $gaps = [];
        for ($i = 1; $i < count($bookings); $i++) {
            $date1 = new DateTime($bookings[$i - 1]->booking_date);
            $date2 = new DateTime($bookings[$i]->booking_date);
            $gaps[] = $date2->diff($date1)->days;
        }

        $avg_gap_days = array_sum($gaps) / count($gaps);

        // Calculate retention rate based on booking consistency
        // Lower gap = higher retention
        $retention_rate = 1 - min(0.5, $avg_gap_days / 365);

        return max(0.1, min(0.95, $retention_rate));
    }

    public function segment_customers_by_clv() {
        global $wpdb;

        // Get all customers with CLV
        $customers = $wpdb->get_results("
            SELECT customer_id, lifetime_value, predicted_clv
            FROM {$wpdb->prefix}bkx_bi_customer_metrics
            WHERE lifetime_value > 0
            ORDER BY lifetime_value DESC
        ");

        $total = count($customers);
        $segments = [
            'high_value' => [],    // Top 20%
            'medium_value' => [],  // Middle 30%
            'low_value' => [],     // Bottom 50%
        ];

        foreach ($customers as $index => $customer) {
            $percentile = ($index + 1) / $total;

            if ($percentile <= 0.20) {
                $segments['high_value'][] = $customer;
            } elseif ($percentile <= 0.50) {
                $segments['medium_value'][] = $customer;
            } else {
                $segments['low_value'][] = $customer;
            }
        }

        return $segments;
    }
}
```

### Cohort Analysis
```php
class CohortAnalyzer {

    public function generate_cohort_analysis($months_back = 12) {
        global $wpdb;

        $cohorts = [];
        $start_date = date('Y-m-01', strtotime("-{$months_back} months"));

        // Get customers grouped by first booking month
        $cohort_data = $wpdb->get_results($wpdb->prepare("
            SELECT
                DATE_FORMAT(first_booking.created_at, '%%Y-%%m-01') as cohort_month,
                COUNT(DISTINCT first_booking.customer_id) as cohort_size
            FROM (
                SELECT customer_id, MIN(created_at) as created_at
                FROM {$wpdb->prefix}bookingx_bookings
                WHERE status = 'completed'
                AND created_at >= %s
                GROUP BY customer_id
            ) as first_booking
            GROUP BY cohort_month
            ORDER BY cohort_month
        ", $start_date));

        foreach ($cohort_data as $cohort) {
            $cohort_month = $cohort->cohort_month;
            $cohort_size = $cohort->cohort_size;

            // Calculate retention for each period
            $retention_data = $this->calculate_cohort_retention(
                $cohort_month,
                $cohort_size
            );

            $cohorts[$cohort_month] = [
                'cohort_month' => $cohort_month,
                'cohort_size' => $cohort_size,
                'retention_data' => $retention_data,
            ];
        }

        return $cohorts;
    }

    private function calculate_cohort_retention($cohort_month, $cohort_size) {
        global $wpdb;

        $retention = [];
        $max_periods = 12; // Track up to 12 months

        for ($period = 0; $period <= $max_periods; $period++) {
            $period_start = date('Y-m-01', strtotime("+{$period} months", strtotime($cohort_month)));
            $period_end = date('Y-m-t', strtotime($period_start));

            $active_customers = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT b.customer_id)
                FROM {$wpdb->prefix}bookingx_bookings b
                INNER JOIN (
                    SELECT customer_id
                    FROM {$wpdb->prefix}bookingx_bookings
                    WHERE DATE_FORMAT(created_at, '%%Y-%%m-01') = %s
                    AND status = 'completed'
                ) as cohort ON b.customer_id = cohort.customer_id
                WHERE b.created_at BETWEEN %s AND %s
                AND b.status = 'completed'
            ", $cohort_month, $period_start, $period_end));

            $retention_rate = ($active_customers / $cohort_size) * 100;

            $retention[$period] = [
                'period' => $period,
                'active_customers' => $active_customers,
                'retention_rate' => round($retention_rate, 2),
            ];
        }

        return $retention;
    }
}
```

---

## 9. Chart Configurations

### Chart.js Configuration Templates

```javascript
// Revenue Forecast Line Chart
const revenueForecastConfig = {
    type: 'line',
    data: {
        labels: [], // Date labels
        datasets: [
            {
                label: 'Actual Revenue',
                data: [],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Forecasted Revenue',
                data: [],
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderDash: [5, 5],
                tension: 0.4,
                fill: true
            },
            {
                label: 'Upper Confidence',
                data: [],
                borderColor: 'rgba(255, 99, 132, 0.3)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                borderDash: [2, 2],
                fill: '+1',
                pointRadius: 0
            },
            {
                label: 'Lower Confidence',
                data: [],
                borderColor: 'rgba(255, 99, 132, 0.3)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                borderDash: [2, 2],
                fill: '-1',
                pointRadius: 0
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Revenue Forecast (Next 90 Days)',
                font: { size: 16, weight: 'bold' }
            },
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': $' +
                               context.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
};

// CLV Distribution Histogram
const clvDistributionConfig = {
    type: 'bar',
    data: {
        labels: ['$0-100', '$100-250', '$250-500', '$500-1000', '$1000-2500', '$2500+'],
        datasets: [{
            label: 'Number of Customers',
            data: [],
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)',
                'rgba(255, 159, 64, 0.7)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Customer Lifetime Value Distribution',
                font: { size: 16, weight: 'bold' }
            },
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Customers'
                }
            }
        }
    }
};

// Cohort Retention Heatmap (using Chart.js Matrix)
const cohortHeatmapConfig = {
    type: 'matrix',
    data: {
        datasets: [{
            label: 'Retention Rate',
            data: [], // [{x: 'Jan', y: 'Month 0', v: 100}, ...]
            backgroundColor(context) {
                const value = context.dataset.data[context.dataIndex].v;
                const alpha = (value / 100) * 0.8 + 0.2;
                return `rgba(75, 192, 192, ${alpha})`;
            },
            borderWidth: 1,
            borderColor: '#fff',
            width: ({chart}) => (chart.chartArea || {}).width / 12,
            height: ({chart}) => (chart.chartArea || {}).height / 12
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Cohort Retention Analysis',
                font: { size: 16, weight: 'bold' }
            },
            tooltip: {
                callbacks: {
                    title: function() { return ''; },
                    label: function(context) {
                        const v = context.dataset.data[context.dataIndex];
                        return [
                            'Cohort: ' + v.x,
                            'Period: ' + v.y,
                            'Retention: ' + v.v.toFixed(1) + '%'
                        ];
                    }
                }
            }
        },
        scales: {
            x: {
                type: 'category',
                title: {
                    display: true,
                    text: 'Cohort Month'
                }
            },
            y: {
                type: 'category',
                title: {
                    display: true,
                    text: 'Months Since First Booking'
                }
            }
        }
    }
};

// KPI Gauge Chart
const kpiGaugeConfig = {
    type: 'doughnut',
    data: {
        labels: ['Achieved', 'Remaining'],
        datasets: [{
            data: [75, 25], // Percentage achieved vs remaining
            backgroundColor: [
                'rgba(75, 192, 192, 0.8)',
                'rgba(200, 200, 200, 0.2)'
            ],
            circumference: 180,
            rotation: 270
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Monthly Revenue Target',
                font: { size: 14, weight: 'bold' }
            },
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed + '%';
                    }
                }
            }
        }
    }
};
```

### D3.js Advanced Visualizations

```javascript
// Scatter Plot: Revenue vs Booking Count
class RevenueScatterPlot {
    constructor(containerId, data) {
        this.container = d3.select(`#${containerId}`);
        this.data = data;
        this.margin = { top: 20, right: 30, bottom: 50, left: 60 };
        this.init();
    }

    init() {
        const containerNode = this.container.node();
        const width = containerNode.clientWidth - this.margin.left - this.margin.right;
        const height = 400 - this.margin.top - this.margin.bottom;

        // Create SVG
        const svg = this.container
            .append('svg')
            .attr('width', width + this.margin.left + this.margin.right)
            .attr('height', height + this.margin.top + this.margin.bottom)
            .append('g')
            .attr('transform', `translate(${this.margin.left},${this.margin.top})`);

        // Scales
        const xScale = d3.scaleLinear()
            .domain([0, d3.max(this.data, d => d.bookingCount)])
            .range([0, width]);

        const yScale = d3.scaleLinear()
            .domain([0, d3.max(this.data, d => d.revenue)])
            .range([height, 0]);

        const colorScale = d3.scaleOrdinal()
            .domain(['high_value', 'medium_value', 'low_value'])
            .range(['#4CAF50', '#2196F3', '#FF9800']);

        // Axes
        svg.append('g')
            .attr('transform', `translate(0,${height})`)
            .call(d3.axisBottom(xScale));

        svg.append('g')
            .call(d3.axisLeft(yScale));

        // Dots
        svg.selectAll('circle')
            .data(this.data)
            .enter()
            .append('circle')
            .attr('cx', d => xScale(d.bookingCount))
            .attr('cy', d => yScale(d.revenue))
            .attr('r', 6)
            .attr('fill', d => colorScale(d.segment))
            .attr('opacity', 0.7)
            .on('mouseover', function(event, d) {
                d3.select(this)
                    .transition()
                    .duration(200)
                    .attr('r', 10)
                    .attr('opacity', 1);

                // Show tooltip
                showTooltip(event, d);
            })
            .on('mouseout', function() {
                d3.select(this)
                    .transition()
                    .duration(200)
                    .attr('r', 6)
                    .attr('opacity', 0.7);

                hideTooltip();
            });

        // Labels
        svg.append('text')
            .attr('x', width / 2)
            .attr('y', height + 40)
            .attr('text-anchor', 'middle')
            .text('Number of Bookings');

        svg.append('text')
            .attr('transform', 'rotate(-90)')
            .attr('x', -height / 2)
            .attr('y', -50)
            .attr('text-anchor', 'middle')
            .text('Total Revenue ($)');
    }
}

// Sankey Diagram: Customer Journey Flow
class CustomerJourneySankey {
    constructor(containerId, data) {
        this.container = d3.select(`#${containerId}`);
        this.data = data;
        this.init();
    }

    init() {
        const width = 800;
        const height = 600;

        const svg = this.container
            .append('svg')
            .attr('width', width)
            .attr('height', height);

        const sankey = d3.sankey()
            .nodeWidth(15)
            .nodePadding(10)
            .extent([[1, 1], [width - 1, height - 6]]);

        const graph = sankey(this.data);

        // Links
        svg.append('g')
            .selectAll('path')
            .data(graph.links)
            .enter()
            .append('path')
            .attr('d', d3.sankeyLinkHorizontal())
            .attr('stroke-width', d => Math.max(1, d.width))
            .attr('stroke', '#000')
            .attr('stroke-opacity', 0.2)
            .attr('fill', 'none');

        // Nodes
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
            .attr('opacity', 0.8);

        // Labels
        svg.append('g')
            .selectAll('text')
            .data(graph.nodes)
            .enter()
            .append('text')
            .attr('x', d => d.x0 < width / 2 ? d.x1 + 6 : d.x0 - 6)
            .attr('y', d => (d.y1 + d.y0) / 2)
            .attr('dy', '0.35em')
            .attr('text-anchor', d => d.x0 < width / 2 ? 'start' : 'end')
            .text(d => d.name);
    }
}
```

---

## 10. Export Functionality

### PDF Export
```php
class PDFReportExporter {

    private $pdf;

    public function __construct() {
        require_once BOOKINGX_BI_PATH . 'vendor/tecnickcom/tcpdf/tcpdf.php';
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8');
    }

    public function generate_executive_report($data, $options = []) {
        // Set document properties
        $this->pdf->SetCreator('BookingX BI Dashboard');
        $this->pdf->SetAuthor(get_bloginfo('name'));
        $this->pdf->SetTitle('Executive Business Intelligence Report');

        // Set margins
        $this->pdf->SetMargins(15, 27, 15);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);

        // Add page
        $this->pdf->AddPage();

        // Title
        $this->pdf->SetFont('helvetica', 'B', 20);
        $this->pdf->Cell(0, 15, 'Business Intelligence Report', 0, 1, 'C');
        $this->pdf->Ln(5);

        // Date range
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->Cell(0, 5, 'Report Period: ' . $data['date_range'], 0, 1, 'C');
        $this->pdf->Ln(10);

        // Key Metrics Summary
        $this->add_metrics_summary($data['kpis']);

        // Revenue Analysis
        $this->pdf->AddPage();
        $this->add_section_title('Revenue Analysis');
        $this->add_revenue_chart($data['revenue_data']);
        $this->add_revenue_table($data['revenue_breakdown']);

        // Customer Insights
        $this->pdf->AddPage();
        $this->add_section_title('Customer Insights');
        $this->add_clv_analysis($data['clv_data']);
        $this->add_cohort_table($data['cohort_data']);

        // Forecasts
        $this->pdf->AddPage();
        $this->add_section_title('Revenue Forecast');
        $this->add_forecast_chart($data['forecast_data']);
        $this->add_forecast_summary($data['forecast_summary']);

        // Save or output
        if ($options['save_to_file'] ?? false) {
            $filename = $options['filename'] ?? 'bi-report-' . date('Y-m-d') . '.pdf';
            $filepath = wp_upload_dir()['path'] . '/' . $filename;
            $this->pdf->Output($filepath, 'F');
            return $filepath;
        } else {
            $this->pdf->Output('bi-report.pdf', 'D');
        }
    }

    private function add_metrics_summary($kpis) {
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->Cell(0, 10, 'Key Performance Indicators', 0, 1);
        $this->pdf->Ln(5);

        $this->pdf->SetFont('helvetica', '', 10);

        // Create KPI grid
        $col_width = 45;
        $row_height = 25;
        $x_start = $this->pdf->GetX();
        $y_start = $this->pdf->GetY();

        $row = 0;
        $col = 0;
        $max_cols = 4;

        foreach ($kpis as $kpi) {
            $x = $x_start + ($col * $col_width);
            $y = $y_start + ($row * $row_height);

            $this->pdf->SetXY($x, $y);

            // KPI Box
            $this->pdf->SetFillColor(240, 240, 240);
            $this->pdf->Rect($x, $y, $col_width - 2, $row_height - 2, 'F');

            // KPI Label
            $this->pdf->SetXY($x + 2, $y + 3);
            $this->pdf->SetFont('helvetica', '', 8);
            $this->pdf->Cell($col_width - 4, 5, $kpi['label'], 0, 1);

            // KPI Value
            $this->pdf->SetXY($x + 2, $y + 10);
            $this->pdf->SetFont('helvetica', 'B', 14);
            $this->pdf->Cell($col_width - 4, 8, $kpi['value'], 0, 1);

            // KPI Change
            $this->pdf->SetXY($x + 2, $y + 18);
            $this->pdf->SetFont('helvetica', '', 8);
            $color = $kpi['change'] >= 0 ? [0, 150, 0] : [200, 0, 0];
            $this->pdf->SetTextColor($color[0], $color[1], $color[2]);
            $this->pdf->Cell($col_width - 4, 4, $kpi['change_text'], 0, 1);
            $this->pdf->SetTextColor(0, 0, 0);

            $col++;
            if ($col >= $max_cols) {
                $col = 0;
                $row++;
            }
        }

        $this->pdf->SetY($y_start + (ceil(count($kpis) / $max_cols) * $row_height) + 5);
    }

    private function add_revenue_chart($revenue_data) {
        // Generate chart image using Chart.js (server-side rendering)
        $chart_image = $this->generate_chart_image($revenue_data, 'line');

        if ($chart_image) {
            $this->pdf->Image($chart_image, 15, $this->pdf->GetY(), 180, 80, 'PNG');
            $this->pdf->Ln(85);
        }
    }

    private function add_section_title($title) {
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->Cell(0, 10, $title, 0, 1);
        $this->pdf->Ln(3);
    }

    private function generate_chart_image($data, $type) {
        // Use QuickChart API or node.js service to render chart
        // This is a placeholder - actual implementation would call chart rendering service
        return null;
    }
}
```

### Excel Export
```php
class ExcelReportExporter {

    private $spreadsheet;

    public function __construct() {
        require_once BOOKINGX_BI_PATH . 'vendor/phpoffice/phpspreadsheet/src/Bootstrap.php';
        $this->spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    }

    public function export_analytics_data($data, $filename) {
        // Summary Sheet
        $this->create_summary_sheet($data['summary']);

        // Revenue Sheet
        $this->create_revenue_sheet($data['revenue']);

        // Customer Metrics Sheet
        $this->create_customer_sheet($data['customers']);

        // Forecast Sheet
        $this->create_forecast_sheet($data['forecast']);

        // CLV Analysis Sheet
        $this->create_clv_sheet($data['clv']);

        // Save file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        $writer->save($filepath);

        return $filepath;
    }

    private function create_summary_sheet($data) {
        $sheet = $this->spreadsheet->getActiveSheet();
        $sheet->setTitle('Summary');

        // Header styling
        $sheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A1', 'Business Intelligence Summary');
        $sheet->mergeCells('A1:B1');

        // KPIs
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Key Performance Indicators');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row += 2;

        foreach ($data['kpis'] as $kpi) {
            $sheet->setCellValue('A' . $row, $kpi['label']);
            $sheet->setCellValue('B' . $row, $kpi['value']);
            $sheet->setCellValue('C' . $row, $kpi['change']);

            // Conditional formatting for change
            if ($kpi['change'] >= 0) {
                $sheet->getStyle('C' . $row)->getFont()->getColor()
                     ->setARGB('FF00AA00');
            } else {
                $sheet->getStyle('C' . $row)->getFont()->getColor()
                     ->setARGB('FFAA0000');
            }

            $row++;
        }

        // Auto-size columns
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
    }

    private function create_revenue_sheet($revenue_data) {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Revenue Analysis');

        // Headers
        $headers = ['Date', 'Revenue', 'Bookings', 'Avg Booking Value', 'Growth %'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $col++;
        }

        // Data
        $row = 2;
        foreach ($revenue_data as $item) {
            $sheet->setCellValue('A' . $row, $item['date']);
            $sheet->setCellValue('B' . $row, $item['revenue']);
            $sheet->setCellValue('C' . $row, $item['bookings']);
            $sheet->setCellValue('D' . $row, $item['avg_value']);
            $sheet->setCellValue('E' . $row, $item['growth']);

            // Number formatting
            $sheet->getStyle('B' . $row)->getNumberFormat()
                 ->setFormatCode('$#,##0.00');
            $sheet->getStyle('D' . $row)->getNumberFormat()
                 ->setFormatCode('$#,##0.00');
            $sheet->getStyle('E' . $row)->getNumberFormat()
                 ->setFormatCode('0.00%');

            $row++;
        }

        // Create chart
        $dataSeriesLabels = [
            new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
                \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING,
                'Revenue Analysis!$B$1',
                null,
                1
            ),
        ];

        $xAxisTickValues = [
            new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
                \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_STRING,
                'Revenue Analysis!$A$2:$A$' . ($row - 1),
                null,
                $row - 2
            ),
        ];

        $dataSeriesValues = [
            new \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues(
                \PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues::DATASERIES_TYPE_NUMBER,
                'Revenue Analysis!$B$2:$B$' . ($row - 1),
                null,
                $row - 2
            ),
        ];

        $series = new \PhpOffice\PhpSpreadsheet\Chart\DataSeries(
            \PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_LINECHART,
            null,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );

        $plotArea = new \PhpOffice\PhpSpreadsheet\Chart\PlotArea(null, [$series]);
        $legend = new \PhpOffice\PhpSpreadsheet\Chart\Legend(
            \PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_TOPRIGHT,
            null,
            false
        );

        $title = new \PhpOffice\PhpSpreadsheet\Chart\Title('Revenue Trend');
        $chart = new \PhpOffice\PhpSpreadsheet\Chart\Chart(
            'revenue_chart',
            $title,
            $legend,
            $plotArea
        );

        $chart->setTopLeftPosition('G2');
        $chart->setBottomRightPosition('O20');
        $sheet->addChart($chart);

        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function create_clv_sheet($clv_data) {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('CLV Analysis');

        // Headers
        $headers = [
            'Customer ID',
            'Customer Name',
            'Segment',
            'Total Bookings',
            'Historical CLV',
            'Predicted CLV',
            'Total CLV',
            'Retention Rate',
            'Churn Probability'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $col++;
        }

        // Data
        $row = 2;
        foreach ($clv_data as $customer) {
            $sheet->setCellValue('A' . $row, $customer['customer_id']);
            $sheet->setCellValue('B' . $row, $customer['name']);
            $sheet->setCellValue('C' . $row, $customer['segment']);
            $sheet->setCellValue('D' . $row, $customer['total_bookings']);
            $sheet->setCellValue('E' . $row, $customer['historical_clv']);
            $sheet->setCellValue('F' . $row, $customer['predicted_clv']);
            $sheet->setCellValue('G' . $row, $customer['total_clv']);
            $sheet->setCellValue('H' . $row, $customer['retention_rate']);
            $sheet->setCellValue('I' . $row, $customer['churn_probability']);

            // Formatting
            foreach (['E', 'F', 'G'] as $col) {
                $sheet->getStyle($col . $row)->getNumberFormat()
                     ->setFormatCode('$#,##0.00');
            }

            foreach (['H', 'I'] as $col) {
                $sheet->getStyle($col . $row)->getNumberFormat()
                     ->setFormatCode('0.00%');
            }

            // Conditional formatting for segment
            $color = match($customer['segment']) {
                'high_value' => 'FF90EE90',
                'medium_value' => 'FFFFD700',
                'low_value' => 'FFFFB6C1',
                default => 'FFFFFFFF'
            };

            $sheet->getStyle('C' . $row)->getFill()
                 ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                 ->getStartColor()->setARGB($color);

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
```

---

## 11. Dashboard UI Components

### React Dashboard Layout
```typescript
// Dashboard.tsx
import React, { useState, useEffect } from 'react';
import { useSelector, useDispatch } from 'react-redux';
import GridLayout from 'react-grid-layout';
import { Card, CardContent, CardHeader } from './components/Card';
import RevenueChart from './widgets/RevenueChart';
import CLVWidget from './widgets/CLVWidget';
import ForecastWidget from './widgets/ForecastWidget';
import KPIWidget from './widgets/KPIWidget';
import CohortHeatmap from './widgets/CohortHeatmap';

interface DashboardWidget {
    id: string;
    type: string;
    config: any;
    layout: { x: number; y: number; w: number; h: number };
}

const BIDashboard: React.FC = () => {
    const [layout, setLayout] = useState<DashboardWidget[]>([]);
    const [isEditing, setIsEditing] = useState(false);
    const dispatch = useDispatch();

    useEffect(() => {
        loadDashboardLayout();
    }, []);

    const loadDashboardLayout = async () => {
        const response = await fetch('/wp-json/bookingx/v1/bi/dashboard-layout');
        const data = await response.json();
        setLayout(data.widgets);
    };

    const saveDashboardLayout = async (newLayout: any[]) => {
        await fetch('/wp-json/bookingx/v1/bi/dashboard-layout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ layout: newLayout })
        });
    };

    const renderWidget = (widget: DashboardWidget) => {
        const commonProps = {
            config: widget.config,
            isEditing
        };

        switch (widget.type) {
            case 'revenue_chart':
                return <RevenueChart {...commonProps} />;
            case 'clv_analysis':
                return <CLVWidget {...commonProps} />;
            case 'forecast':
                return <ForecastWidget {...commonProps} />;
            case 'kpi':
                return <KPIWidget {...commonProps} />;
            case 'cohort_heatmap':
                return <CohortHeatmap {...commonProps} />;
            default:
                return <div>Unknown widget type</div>;
        }
    };

    return (
        <div className="bi-dashboard">
            <div className="dashboard-header">
                <h1>Business Intelligence Dashboard</h1>
                <div className="dashboard-controls">
                    <button onClick={() => setIsEditing(!isEditing)}>
                        {isEditing ? 'Done Editing' : 'Edit Layout'}
                    </button>
                    <button onClick={exportDashboard}>Export PDF</button>
                    <DateRangePicker />
                </div>
            </div>

            <GridLayout
                className="dashboard-grid"
                layout={layout.map(w => w.layout)}
                cols={12}
                rowHeight={80}
                width={1200}
                isDraggable={isEditing}
                isResizable={isEditing}
                onLayoutChange={(newLayout) => {
                    if (isEditing) {
                        const updatedLayout = layout.map((widget, i) => ({
                            ...widget,
                            layout: newLayout[i]
                        }));
                        setLayout(updatedLayout);
                        saveDashboardLayout(updatedLayout);
                    }
                }}
            >
                {layout.map(widget => (
                    <div key={widget.id} className="dashboard-widget">
                        <Card>
                            <CardHeader>
                                {widget.config.title}
                                {isEditing && (
                                    <button onClick={() => removeWidget(widget.id)}>
                                        ×
                                    </button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {renderWidget(widget)}
                            </CardContent>
                        </Card>
                    </div>
                ))}
            </GridLayout>
        </div>
    );
};

export default BIDashboard;
```

---

## 12. Security Considerations

### Data Security
- **Data Encryption:** Encrypt sensitive business metrics at rest
- **Access Control:** Role-based permissions for dashboard access
- **API Authentication:** Nonce verification for all AJAX requests
- **SQL Injection Prevention:** Prepared statements for all queries
- **XSS Protection:** Sanitize all output data

### Privacy & Compliance
- **GDPR Compliance:** Anonymize customer data in reports
- **Data Retention:** Configurable retention policies
- **Audit Logging:** Track all data access and exports
- **Export Controls:** Limit export permissions by role

### Performance Security
- **Query Optimization:** Indexed queries for fast analytics
- **Rate Limiting:** Prevent abuse of forecast/calculation endpoints
- **Cache Control:** Proper cache headers for sensitive data
- **Resource Limits:** Prevent resource exhaustion

---

## 13. Testing Strategy

### Unit Tests
```php
- test_revenue_forecast_calculation()
- test_clv_prediction_accuracy()
- test_cohort_retention_calculation()
- test_trend_analysis()
- test_kpi_calculation()
- test_data_aggregation()
- test_export_generation()
```

### Integration Tests
```php
- test_dashboard_data_loading()
- test_real_time_metric_updates()
- test_forecast_accuracy_validation()
- test_pdf_export_generation()
- test_excel_export_generation()
- test_scheduled_report_delivery()
```

### Performance Tests
- Dashboard load time < 2 seconds
- Chart rendering < 500ms
- Forecast calculation < 3 seconds
- Export generation < 10 seconds
- API response time < 500ms

---

## 14. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema creation
- [ ] Data warehouse setup
- [ ] Basic API endpoints
- [ ] Data aggregation engine

### Phase 2: Analytics Core (Week 3-4)
- [ ] Metrics calculator
- [ ] KPI tracking system
- [ ] Basic reporting
- [ ] Chart data providers

### Phase 3: Forecasting Engine (Week 5-6)
- [ ] Trend analysis
- [ ] Forecast algorithms
- [ ] Confidence intervals
- [ ] Model validation

### Phase 4: CLV Analysis (Week 7-8)
- [ ] CLV calculator
- [ ] Cohort analysis
- [ ] Customer segmentation
- [ ] Churn prediction

### Phase 5: Dashboard UI (Week 9-10)
- [ ] React components
- [ ] Chart integrations
- [ ] Interactive widgets
- [ ] Layout system

### Phase 6: Export & Reports (Week 11-12)
- [ ] PDF export
- [ ] Excel export
- [ ] Scheduled reports
- [ ] Email delivery

### Phase 7: Predictive Models (Week 13-14)
- [ ] Demand forecasting
- [ ] Pricing optimization
- [ ] Capacity planning
- [ ] Model training

### Phase 8: Testing & Launch (Week 15-16)
- [ ] Comprehensive testing
- [ ] Performance optimization
- [ ] Documentation
- [ ] Production release

**Total Timeline:** 16 weeks (4 months)

---

## 15. Success Metrics

### Technical Metrics
- Dashboard load time < 2 seconds
- Forecast accuracy > 85%
- CLV prediction accuracy > 80%
- Report generation time < 10 seconds
- API uptime > 99.9%

### Business Metrics
- User adoption rate > 40%
- Daily active usage > 60%
- Report generation frequency > 10/day
- User satisfaction > 4.5/5
- ROI improvement > 25%

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
