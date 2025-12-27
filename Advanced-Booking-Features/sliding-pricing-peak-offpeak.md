# Sliding Pricing Peak/Off-Peak Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Sliding Pricing Peak/Off-Peak
**Price:** $89
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Implement dynamic pricing based on time of day, day of week, season, and demand. Maximize revenue with intelligent pricing strategies including peak/off-peak rates, seasonal adjustments, surge pricing, and early bird discounts. Perfect for businesses with variable demand patterns.

### Value Proposition
- Optimize revenue with time-based pricing
- Peak and off-peak rate management
- Seasonal pricing adjustments
- Weekend vs weekday pricing
- Hour-of-day pricing variations
- Holiday and special event pricing
- Early bird and last-minute discounts
- Demand-based surge pricing
- Increase revenue by 20-40%

---

## 2. Features & Requirements

### Core Features
1. **Peak/Off-Peak Pricing**
   - Define peak hours/days
   - Off-peak discount rates
   - Premium peak pricing
   - Configurable time ranges
   - Day-of-week variations
   - Multiple peak periods per day

2. **Seasonal Pricing**
   - Seasonal rate adjustments
   - Holiday pricing
   - Special event pricing
   - Date range based pricing
   - Seasonal multipliers
   - Custom season definitions

3. **Time-of-Day Pricing**
   - Hourly rate variations
   - Morning/afternoon/evening rates
   - Night premium pricing
   - Transition pricing
   - Custom time slots

4. **Dynamic Surge Pricing**
   - Demand-based pricing
   - Capacity-based adjustments
   - Real-time price updates
   - Minimum/maximum limits
   - Surge multipliers
   - Cooling-off periods

5. **Discount Strategies**
   - Early bird discounts
   - Last-minute deals
   - Advance booking discounts
   - Fill-rate optimization
   - Promotional periods

6. **Price Rules Engine**
   - Multiple rule stacking
   - Priority-based rules
   - Conditional pricing
   - Service-specific rules
   - Provider-specific rules
   - Location-based pricing

### User Roles & Permissions
- **Admin:** Configure all pricing rules
- **Manager:** Create/edit pricing strategies
- **Accountant:** View pricing reports
- **Customer:** View applicable prices

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP 7.4+, WordPress REST API
- **Price Engine:** Custom calculation engine
- **Database:** MySQL 5.7+ with InnoDB
- **Caching:** WordPress Object Cache
- **Analytics:** Price optimization analytics

### Dependencies
- BookingX Core 2.0+
- PHP JSON extension
- WordPress Cron for price updates

### API Integration Points
```php
// REST API Endpoints
GET    /wp-json/bookingx/v1/pricing/calculate
POST   /wp-json/bookingx/v1/pricing/rules
GET    /wp-json/bookingx/v1/pricing/rules
GET    /wp-json/bookingx/v1/pricing/rules/{id}
PUT    /wp-json/bookingx/v1/pricing/rules/{id}
DELETE /wp-json/bookingx/v1/pricing/rules/{id}

GET    /wp-json/bookingx/v1/pricing/peak-times
POST   /wp-json/bookingx/v1/pricing/peak-times
PUT    /wp-json/bookingx/v1/pricing/peak-times/{id}

GET    /wp-json/bookingx/v1/pricing/seasons
POST   /wp-json/bookingx/v1/pricing/seasons
PUT    /wp-json/bookingx/v1/pricing/seasons/{id}

GET    /wp-json/bookingx/v1/pricing/surge-status
POST   /wp-json/bookingx/v1/pricing/surge-update

GET    /wp-json/bookingx/v1/pricing/calendar-view
GET    /wp-json/bookingx/v1/pricing/preview
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│  BookingX Core      │
│  - Base Pricing     │
└──────────┬──────────┘
           │
           ▼
┌────────────────────────────┐
│  Dynamic Pricing Module    │
│  - Price Rules Engine      │
│  - Calculation Engine      │
│  - Surge Manager           │
└──────────┬─────────────────┘
           │
           ├──────────┬──────────┬──────────┐
           ▼          ▼          ▼          ▼
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│   Peak   │ │ Seasonal │ │  Surge   │ │ Discount │
│  Manager │ │  Manager │ │  Engine  │ │  Manager │
└──────────┘ └──────────┘ └──────────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\SlidingPricing;

class PricingRulesEngine {
    - evaluate_rules()
    - get_applicable_rules()
    - calculate_final_price()
    - apply_rule_priority()
    - stack_modifiers()
}

class PeakPricingManager {
    - define_peak_hours()
    - define_peak_days()
    - is_peak_time()
    - get_peak_multiplier()
    - calculate_peak_price()
}

class SeasonalPricingManager {
    - create_season()
    - get_current_season()
    - get_seasonal_rate()
    - is_holiday()
    - apply_seasonal_adjustment()
}

class TimeOfDayPricer {
    - define_time_slots()
    - get_time_slot()
    - calculate_hourly_rate()
    - apply_time_multiplier()
}

class SurgePricingEngine {
    - calculate_demand()
    - get_surge_multiplier()
    - apply_surge_pricing()
    - check_capacity_threshold()
    - update_surge_status()
}

class DiscountManager {
    - calculate_early_bird()
    - calculate_last_minute()
    - apply_advance_booking()
    - get_promotional_rate()
}

class PriceCalculator {
    - get_base_price()
    - apply_all_modifiers()
    - calculate_final_price()
    - get_price_breakdown()
    - validate_min_max()
}

class PricingCalendar {
    - get_calendar_prices()
    - generate_price_heatmap()
    - show_best_prices()
    - highlight_peak_times()
}

class PricingAnalytics {
    - get_revenue_by_time()
    - get_conversion_by_price()
    - get_demand_patterns()
    - optimize_pricing()
    - export_analytics()
}
```

---

## 5. Database Schema

### Table: `bkx_pricing_rules`
```sql
CREATE TABLE bkx_pricing_rules (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    rule_type ENUM('peak', 'seasonal', 'time_of_day', 'surge', 'discount', 'custom') NOT NULL,
    service_ids LONGTEXT COMMENT 'JSON array of service IDs, NULL for all',
    provider_ids LONGTEXT COMMENT 'JSON array of provider IDs, NULL for all',
    location_ids LONGTEXT COMMENT 'JSON array of location IDs, NULL for all',
    adjustment_type ENUM('percentage', 'fixed', 'multiplier') NOT NULL,
    adjustment_value DECIMAL(10,2) NOT NULL,
    min_price DECIMAL(10,2),
    max_price DECIMAL(10,2),
    priority INT DEFAULT 0,
    conditions LONGTEXT COMMENT 'JSON conditions object',
    valid_from DATE,
    valid_until DATE,
    status ENUM('active', 'inactive', 'scheduled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX rule_type_idx (rule_type),
    INDEX status_idx (status),
    INDEX priority_idx (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_peak_times`
```sql
CREATE TABLE bkx_peak_times (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    peak_type ENUM('hour', 'day', 'weekend', 'custom') NOT NULL,
    days_of_week VARCHAR(50) COMMENT 'Comma-separated: 0-6',
    start_time TIME,
    end_time TIME,
    date_from DATE,
    date_to DATE,
    price_multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    fixed_adjustment DECIMAL(10,2) DEFAULT 0,
    is_premium TINYINT(1) DEFAULT 1,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX peak_type_idx (peak_type),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_seasonal_pricing`
```sql
CREATE TABLE bkx_seasonal_pricing (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    season_name VARCHAR(255) NOT NULL,
    season_type ENUM('spring', 'summer', 'fall', 'winter', 'holiday', 'custom') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    recurring_yearly TINYINT(1) DEFAULT 0,
    price_multiplier DECIMAL(5,2) DEFAULT 1.00,
    percentage_adjustment DECIMAL(5,2),
    fixed_adjustment DECIMAL(10,2),
    service_ids LONGTEXT COMMENT 'JSON array, NULL for all',
    priority INT DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX season_type_idx (season_type),
    INDEX date_range_idx (start_date, end_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_surge_pricing`
```sql
CREATE TABLE bkx_surge_pricing (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    date DATE NOT NULL,
    time_slot TIME,
    current_bookings INT NOT NULL,
    total_capacity INT NOT NULL,
    capacity_percentage DECIMAL(5,2),
    surge_multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    surge_level ENUM('normal', 'moderate', 'high', 'very_high') DEFAULT 'normal',
    is_active TINYINT(1) DEFAULT 0,
    updated_at DATETIME NOT NULL,
    INDEX service_id_idx (service_id),
    INDEX date_idx (date),
    INDEX surge_level_idx (surge_level),
    UNIQUE KEY unique_surge (service_id, date, time_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_discount_schedules`
```sql
CREATE TABLE bkx_discount_schedules (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    discount_type ENUM('early_bird', 'last_minute', 'advance_booking', 'promotional') NOT NULL,
    discount_percentage DECIMAL(5,2),
    discount_amount DECIMAL(10,2),
    advance_booking_days INT,
    last_minute_hours INT,
    time_window_start TIME,
    time_window_end TIME,
    applicable_days VARCHAR(50) COMMENT 'Days of week',
    valid_from DATE,
    valid_until DATE,
    max_uses INT,
    times_used INT DEFAULT 0,
    service_ids LONGTEXT COMMENT 'JSON array',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX discount_type_idx (discount_type),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_price_history`
```sql
CREATE TABLE bkx_price_history (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    final_price DECIMAL(10,2) NOT NULL,
    applied_rules LONGTEXT COMMENT 'JSON array of applied rules',
    price_breakdown LONGTEXT COMMENT 'JSON price calculation details',
    peak_multiplier DECIMAL(5,2),
    seasonal_adjustment DECIMAL(10,2),
    surge_multiplier DECIMAL(5,2),
    discount_amount DECIMAL(10,2),
    calculated_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX service_id_idx (service_id),
    INDEX calculated_at_idx (calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    'general_settings' => [
        'enable_dynamic_pricing' => true,
        'show_price_breakdown' => true,
        'show_savings_amount' => true,
        'highlight_best_prices' => true,
        'price_cache_ttl' => 300, // seconds
    ],

    'peak_pricing' => [
        'enable_peak_pricing' => true,
        'peak_multiplier_weekday' => 1.25,
        'peak_multiplier_weekend' => 1.50,
        'peak_hours' => [
            ['start' => '09:00', 'end' => '12:00', 'multiplier' => 1.3],
            ['start' => '17:00', 'end' => '20:00', 'multiplier' => 1.4],
        ],
        'peak_days' => [5, 6], // Friday, Saturday
    ],

    'seasonal_pricing' => [
        'enable_seasonal' => true,
        'seasons' => [
            [
                'name' => 'Summer Peak',
                'start' => '06-01',
                'end' => '08-31',
                'multiplier' => 1.30,
            ],
            [
                'name' => 'Holiday Season',
                'start' => '12-15',
                'end' => '01-05',
                'multiplier' => 1.50,
            ],
        ],
    ],

    'surge_pricing' => [
        'enable_surge' => true,
        'capacity_thresholds' => [
            ['min' => 0, 'max' => 50, 'multiplier' => 1.00],
            ['min' => 50, 'max' => 75, 'multiplier' => 1.15],
            ['min' => 75, 'max' => 90, 'multiplier' => 1.30],
            ['min' => 90, 'max' => 100, 'multiplier' => 1.50],
        ],
        'max_surge_multiplier' => 2.00,
        'surge_cooldown_minutes' => 30,
        'update_interval_minutes' => 15,
    ],

    'discount_settings' => [
        'enable_early_bird' => true,
        'early_bird_days' => 14,
        'early_bird_discount' => 15, // percentage
        'enable_last_minute' => true,
        'last_minute_hours' => 24,
        'last_minute_discount' => 20,
        'enable_advance_booking' => true,
        'advance_booking_tiers' => [
            ['days' => 7, 'discount' => 5],
            ['days' => 14, 'discount' => 10],
            ['days' => 30, 'discount' => 15],
        ],
    ],

    'rule_priority' => [
        'priority_order' => [
            'surge',
            'peak',
            'seasonal',
            'time_of_day',
            'discount',
        ],
        'allow_stacking' => true,
        'max_total_adjustment_percentage' => 200,
    ],

    'price_limits' => [
        'enforce_min_price' => true,
        'global_min_price' => 10,
        'enforce_max_price' => true,
        'global_max_price' => 1000,
        'max_discount_percentage' => 50,
    ],

    'display_settings' => [
        'show_original_price' => true,
        'show_you_save' => true,
        'show_price_badge' => true,
        'badge_labels' => [
            'peak' => 'Peak Time',
            'surge' => 'High Demand',
            'discount' => 'Special Offer',
            'early_bird' => 'Early Bird',
        ],
    ],

    'analytics' => [
        'track_price_changes' => true,
        'track_conversion_by_price' => true,
        'generate_optimization_reports' => true,
    ],
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Price Display with Breakdown**
   - Original price (strikethrough)
   - Final price (prominent)
   - Savings amount
   - Price modifiers list
   - Discount badges
   - Peak/off-peak indicator

2. **Pricing Calendar**
   - Color-coded pricing
   - Best price highlights
   - Peak time indicators
   - Hover for details
   - Month/week view

3. **Price Comparison Widget**
   - Compare time slots
   - Show price variations
   - Recommend best times
   - Savings calculator

4. **Dynamic Price Update**
   - Real-time price changes
   - Surge notifications
   - Price countdown (for discounts)
   - Availability alerts

### Backend Components

1. **Pricing Rules Manager**
   - Create/edit rules
   - Rule priority management
   - Visual rule builder
   - Test calculator
   - Bulk operations

2. **Peak Times Configuration**
   - Visual time selector
   - Day/week picker
   - Multiplier settings
   - Preview calendar

3. **Seasonal Pricing Setup**
   - Season definition
   - Recurring seasons
   - Holiday calendar
   - Preview tool

4. **Surge Pricing Dashboard**
   - Real-time surge status
   - Capacity monitoring
   - Threshold configuration
   - Manual override

5. **Pricing Analytics**
   - Revenue by time period
   - Conversion by price point
   - Demand heatmap
   - Optimization suggestions
   - A/B test results

6. **Price Preview Tool**
   - Test pricing scenarios
   - Date/time selector
   - Rule simulation
   - Price calculator

---

## 8. Security Considerations

### Data Security
- **Price Integrity:** Prevent price manipulation
- **SQL Injection:** Prepared statements
- **XSS Prevention:** Sanitize inputs

### Business Logic Security
- Validate price calculations
- Enforce min/max limits
- Prevent negative pricing
- Audit trail for price changes
- Rule conflict detection

---

## 9. Testing Strategy

### Unit Tests
```php
- test_peak_pricing_calculation()
- test_seasonal_pricing()
- test_surge_multiplier()
- test_discount_application()
- test_rule_stacking()
- test_price_limits()
- test_rule_priority()
```

### Integration Tests
```php
- test_complete_pricing_flow()
- test_multiple_rules_applied()
- test_surge_pricing_updates()
- test_booking_with_dynamic_price()
```

### Test Scenarios
1. **Peak Weekend:** Weekend premium pricing
2. **Summer Season:** Seasonal rate increase
3. **Surge Pricing:** High demand multiplier
4. **Early Bird:** Advance booking discount
5. **Rule Stacking:** Multiple rules applied
6. **Price Limits:** Min/max enforcement

---

## 10. Error Handling

### Error Messages
```php
'invalid_price_rule' => 'Invalid pricing rule configuration.',
'price_below_minimum' => 'Calculated price is below minimum allowed.',
'price_above_maximum' => 'Calculated price exceeds maximum allowed.',
'conflicting_rules' => 'Conflicting pricing rules detected.',
'surge_calculation_error' => 'Unable to calculate surge pricing.',
```

---

## 11. Performance Optimization

### Caching Strategy
- Cache calculated prices (TTL: 5 minutes)
- Cache applicable rules (TTL: 15 minutes)
- Cache pricing calendar (TTL: 1 hour)

### Database Optimization
- Indexed queries for rule lookup
- Optimize date range queries
- Cache frequently accessed rules

---

## 12. Development Timeline

**Total Estimated Timeline:** 10 weeks (2.5 months)

---

## 13. Success Metrics

### Business Metrics
- Revenue increase > 25%
- Peak utilization > 80%
- Off-peak utilization > 60%
- Average booking value increase > 30%

---

## 14. Dependencies & Requirements

### Required
- BookingX Core 2.0+

### Server Requirements
- PHP 7.4+
- MySQL 5.7+
- WordPress 5.8+

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
