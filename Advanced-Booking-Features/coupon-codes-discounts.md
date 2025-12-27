# Coupon Codes & Discounts Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Coupon Codes & Discounts
**Price:** $69
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Comprehensive coupon and discount management system with gift certificates, promotional codes, referral discounts, and loyalty rewards. Create targeted marketing campaigns, track redemption rates, and increase bookings with strategic discount offerings.

### Value Proposition
- Flexible coupon code system
- Gift certificate sales and redemption
- Percentage or fixed amount discounts
- Service-specific or site-wide coupons
- Time-limited promotional offers
- Referral discount programs
- Bulk coupon generation
- Usage tracking and analytics
- Increase conversions by 15-30%

---

## 2. Features & Requirements

### Core Features
1. **Coupon Management**
   - Create unlimited coupons
   - Percentage or fixed discounts
   - Single-use or multi-use coupons
   - Usage limits per coupon
   - Usage limits per customer
   - Expiration dates
   - Auto-generation of codes

2. **Gift Certificates**
   - Sell gift certificates
   - Custom denominations
   - Email delivery
   - Physical certificate printing
   - Balance tracking
   - Partial redemption
   - Gift certificate codes

3. **Discount Types**
   - Percentage discount (e.g., 20% off)
   - Fixed amount discount (e.g., $25 off)
   - Free service addon
   - BOGO (Buy One Get One)
   - Tiered discounts
   - Bundle discounts

4. **Targeting & Restrictions**
   - Service-specific coupons
   - Provider-specific coupons
   - Location-based restrictions
   - Customer group targeting
   - First-time customer only
   - Minimum purchase amount
   - Maximum discount cap

5. **Time-Based Offers**
   - Date range validity
   - Day of week restrictions
   - Time of day restrictions
   - Seasonal promotions
   - Flash sales
   - Countdown timers

6. **Referral System**
   - Referral code generation
   - Referrer rewards
   - Referee discounts
   - Multi-tier referrals
   - Tracking referral chains
   - Automated rewards

7. **Loyalty & Rewards**
   - Points-based rewards
   - Loyalty tiers
   - Birthday discounts
   - Anniversary rewards
   - Repeat customer bonuses
   - VIP customer codes

### User Roles & Permissions
- **Admin:** Full coupon management, analytics
- **Manager:** Create/edit coupons, view reports
- **Marketing:** Campaign management, performance tracking
- **Customer:** Redeem coupons, view available offers

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP 7.4+, WordPress REST API
- **Database:** MySQL 5.7+ with InnoDB
- **Email:** WordPress mail system
- **PDF:** PDF generation for gift certificates
- **Analytics:** Usage tracking and reporting

### Dependencies
- BookingX Core 2.0+
- WordPress email functionality
- PHP OpenSSL for code generation

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/coupons
GET    /wp-json/bookingx/v1/coupons
GET    /wp-json/bookingx/v1/coupons/{id}
PUT    /wp-json/bookingx/v1/coupons/{id}
DELETE /wp-json/bookingx/v1/coupons/{id}

POST   /wp-json/bookingx/v1/coupons/validate
POST   /wp-json/bookingx/v1/coupons/apply
POST   /wp-json/bookingx/v1/coupons/bulk-generate

POST   /wp-json/bookingx/v1/gift-certificates/purchase
GET    /wp-json/bookingx/v1/gift-certificates/{code}
POST   /wp-json/bookingx/v1/gift-certificates/redeem
GET    /wp-json/bookingx/v1/gift-certificates/{code}/balance

POST   /wp-json/bookingx/v1/referrals/generate-code
GET    /wp-json/bookingx/v1/referrals/track/{code}
POST   /wp-json/bookingx/v1/referrals/reward

GET    /wp-json/bookingx/v1/discounts/available
GET    /wp-json/bookingx/v1/discounts/customer-eligible
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│  BookingX Core      │
│  - Pricing System   │
└──────────┬──────────┘
           │
           ▼
┌────────────────────────────┐
│  Discount System Module    │
│  - Coupon Manager          │
│  - Validation Engine       │
│  - Redemption Tracker      │
└──────────┬─────────────────┘
           │
           ├──────────┬──────────┬──────────┐
           ▼          ▼          ▼          ▼
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│   Gift   │ │ Referral │ │  Loyalty │ │Analytics │
│   Cert   │ │  System  │ │  Rewards │ │  Engine  │
└──────────┘ └──────────┘ └──────────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\Coupons;

class CouponManager {
    - create_coupon()
    - update_coupon()
    - delete_coupon()
    - get_coupon()
    - validate_coupon()
    - apply_coupon()
    - increment_usage()
}

class CouponValidator {
    - validate_code()
    - check_expiration()
    - check_usage_limits()
    - check_customer_eligibility()
    - check_service_eligibility()
    - check_restrictions()
}

class DiscountCalculator {
    - calculate_percentage_discount()
    - calculate_fixed_discount()
    - calculate_tiered_discount()
    - apply_max_discount()
    - get_final_price()
}

class GiftCertificateManager {
    - create_certificate()
    - purchase_certificate()
    - send_certificate()
    - redeem_certificate()
    - check_balance()
    - update_balance()
}

class ReferralManager {
    - generate_referral_code()
    - track_referral()
    - reward_referrer()
    - reward_referee()
    - get_referral_stats()
}

class LoyaltyRewards {
    - calculate_points()
    - award_points()
    - redeem_points()
    - get_tier()
    - apply_tier_discount()
}

class BulkCouponGenerator {
    - generate_codes()
    - generate_unique_code()
    - export_codes()
    - import_codes()
}

class CouponAnalytics {
    - get_redemption_rate()
    - get_revenue_impact()
    - get_popular_coupons()
    - get_customer_acquisition_cost()
    - export_report()
}

class CampaignManager {
    - create_campaign()
    - assign_coupons()
    - track_performance()
    - get_campaign_roi()
}
```

---

## 5. Database Schema

### Table: `bkx_coupons`
```sql
CREATE TABLE bkx_coupons (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    discount_type ENUM('percentage', 'fixed', 'free_service', 'bogo') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    max_discount_amount DECIMAL(10,2),
    min_purchase_amount DECIMAL(10,2) DEFAULT 0,
    usage_limit INT COMMENT 'Total uses allowed, NULL for unlimited',
    usage_limit_per_customer INT DEFAULT 1,
    times_used INT DEFAULT 0,
    valid_from DATETIME,
    valid_until DATETIME,
    applicable_services LONGTEXT COMMENT 'JSON array of service IDs',
    applicable_providers LONGTEXT COMMENT 'JSON array of provider IDs',
    excluded_services LONGTEXT COMMENT 'JSON array of excluded service IDs',
    customer_restrictions ENUM('all', 'new_only', 'existing_only', 'specific') DEFAULT 'all',
    specific_customers LONGTEXT COMMENT 'JSON array of customer IDs',
    day_restrictions VARCHAR(50) COMMENT 'Days of week (0-6)',
    time_restrictions VARCHAR(100) COMMENT 'Time range',
    status ENUM('active', 'inactive', 'expired', 'depleted') NOT NULL DEFAULT 'active',
    campaign_id BIGINT(20) UNSIGNED,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX code_idx (code),
    INDEX status_idx (status),
    INDEX campaign_id_idx (campaign_id),
    INDEX valid_until_idx (valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_coupon_usage`
```sql
CREATE TABLE bkx_coupon_usage (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coupon_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    original_price DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    final_price DECIMAL(10,2) NOT NULL,
    used_at DATETIME NOT NULL,
    INDEX coupon_id_idx (coupon_id),
    INDEX booking_id_idx (booking_id),
    INDEX customer_id_idx (customer_id),
    INDEX used_at_idx (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_gift_certificates`
```sql
CREATE TABLE bkx_gift_certificates (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    original_amount DECIMAL(10,2) NOT NULL,
    current_balance DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    purchaser_id BIGINT(20) UNSIGNED,
    purchaser_name VARCHAR(255),
    purchaser_email VARCHAR(255),
    recipient_name VARCHAR(255),
    recipient_email VARCHAR(255),
    personal_message TEXT,
    purchase_date DATETIME NOT NULL,
    delivery_date DATE,
    delivery_method ENUM('email', 'print', 'both') DEFAULT 'email',
    expiration_date DATE,
    status ENUM('active', 'redeemed', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    payment_id BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX code_idx (code),
    INDEX status_idx (status),
    INDEX expiration_date_idx (expiration_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_gift_certificate_redemptions`
```sql
CREATE TABLE bkx_gift_certificate_redemptions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    certificate_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    amount_used DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    redeemed_by BIGINT(20) UNSIGNED NOT NULL,
    redeemed_at DATETIME NOT NULL,
    INDEX certificate_id_idx (certificate_id),
    INDEX booking_id_idx (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_referrals`
```sql
CREATE TABLE bkx_referrals (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id BIGINT(20) UNSIGNED NOT NULL,
    referral_code VARCHAR(50) NOT NULL UNIQUE,
    referee_id BIGINT(20) UNSIGNED,
    referrer_reward_type ENUM('percentage', 'fixed', 'credit', 'points') NOT NULL,
    referrer_reward_value DECIMAL(10,2) NOT NULL,
    referee_reward_type ENUM('percentage', 'fixed', 'credit') NOT NULL,
    referee_reward_value DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'rewarded') NOT NULL DEFAULT 'pending',
    referred_at DATETIME,
    first_booking_at DATETIME,
    rewarded_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX referrer_id_idx (referrer_id),
    INDEX referral_code_idx (referral_code),
    INDEX referee_id_idx (referee_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_loyalty_points`
```sql
CREATE TABLE bkx_loyalty_points (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    points_earned INT DEFAULT 0,
    points_redeemed INT DEFAULT 0,
    current_balance INT DEFAULT 0,
    tier ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    tier_updated_at DATETIME,
    lifetime_points INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX tier_idx (tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_loyalty_transactions`
```sql
CREATE TABLE bkx_loyalty_transactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    transaction_type ENUM('earned', 'redeemed', 'expired', 'adjusted') NOT NULL,
    points INT NOT NULL,
    balance_before INT NOT NULL,
    balance_after INT NOT NULL,
    booking_id BIGINT(20) UNSIGNED,
    description TEXT,
    created_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX booking_id_idx (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_campaigns`
```sql
CREATE TABLE bkx_campaigns (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    campaign_type ENUM('coupon', 'referral', 'loyalty', 'gift_certificate') NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME,
    budget DECIMAL(10,2),
    spent DECIMAL(10,2) DEFAULT 0,
    target_audience LONGTEXT COMMENT 'JSON targeting criteria',
    performance_metrics LONGTEXT COMMENT 'JSON metrics',
    status ENUM('draft', 'active', 'paused', 'completed') NOT NULL DEFAULT 'draft',
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX campaign_type_idx (campaign_type),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    'coupon_settings' => [
        'enable_coupons' => true,
        'code_prefix' => 'BKX',
        'code_length' => 8,
        'case_sensitive' => false,
        'allow_multiple_coupons' => false,
        'auto_apply_best_discount' => true,
        'show_coupon_field_checkout' => true,
    ],

    'gift_certificate_settings' => [
        'enable_gift_certificates' => true,
        'allow_purchase' => true,
        'min_amount' => 25,
        'max_amount' => 500,
        'preset_amounts' => [50, 100, 150, 200],
        'allow_custom_amount' => true,
        'validity_days' => 365,
        'allow_partial_redemption' => true,
        'send_reminder_before_expiry_days' => 30,
    ],

    'referral_settings' => [
        'enable_referrals' => true,
        'referrer_reward_type' => 'fixed',
        'referrer_reward_value' => 25,
        'referee_reward_type' => 'percentage',
        'referee_reward_value' => 20,
        'min_referral_purchase' => 50,
        'referral_code_format' => 'custom', // custom|name_based
    ],

    'loyalty_settings' => [
        'enable_loyalty' => true,
        'points_per_dollar' => 10,
        'points_redemption_rate' => 100, // 100 points = $1
        'min_redemption_points' => 500,
        'points_expiration_days' => 365,
        'tiers' => [
            ['name' => 'Bronze', 'min_points' => 0, 'discount' => 0],
            ['name' => 'Silver', 'min_points' => 1000, 'discount' => 5],
            ['name' => 'Gold', 'min_points' => 5000, 'discount' => 10],
            ['name' => 'Platinum', 'min_points' => 10000, 'discount' => 15],
        ],
        'birthday_bonus_points' => 100,
    ],

    'discount_limits' => [
        'max_discount_percentage' => 100,
        'max_discount_amount' => 500,
        'allow_free_bookings' => false,
        'min_final_price' => 1,
    ],

    'notification_settings' => [
        'send_coupon_confirmation' => true,
        'send_gift_certificate_email' => true,
        'send_referral_reward_email' => true,
        'send_points_earned_email' => true,
        'send_expiry_reminders' => true,
    ],

    'display_settings' => [
        'show_available_coupons' => true,
        'show_savings_amount' => true,
        'show_loyalty_points_balance' => true,
        'show_referral_link' => true,
    ],
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Coupon Input Field**
   - Coupon code input
   - Apply button
   - Validation feedback
   - Remove coupon option
   - Discount display

2. **Available Discounts Display**
   - List of applicable coupons
   - Auto-apply best discount
   - Savings calculator
   - Expiration countdown

3. **Gift Certificate Purchase**
   - Amount selector
   - Recipient information
   - Personal message
   - Delivery options
   - Preview certificate

4. **Referral Dashboard**
   - Unique referral code
   - Share buttons (social media)
   - Referral statistics
   - Rewards earned
   - Pending referrals

5. **Loyalty Points Display**
   - Points balance
   - Points history
   - Tier status
   - Rewards catalog
   - Redeem points option

### Backend Components

1. **Coupon Manager**
   - Coupon list table
   - Create/edit coupon form
   - Bulk actions
   - Usage statistics
   - Export/import

2. **Bulk Code Generator**
   - Quantity selector
   - Pattern configuration
   - Preview codes
   - Download CSV

3. **Gift Certificate Manager**
   - Certificate list
   - Balance tracking
   - Send certificate
   - Mark as used
   - Refund option

4. **Campaign Manager**
   - Create campaign
   - Assign coupons
   - Set budget
   - Track performance
   - ROI calculator

5. **Analytics Dashboard**
   - Redemption rates
   - Revenue impact
   - Popular coupons
   - Customer acquisition cost
   - Trend charts

6. **Referral Tracking**
   - Referral list
   - Conversion tracking
   - Reward processing
   - Performance metrics

---

## 8. Security Considerations

### Data Security
- **Code Generation:** Cryptographically secure random codes
- **SQL Injection:** Prepared statements
- **XSS Prevention:** Sanitize inputs
- **CSRF Protection:** WordPress nonces

### Authorization
- Validate coupon ownership
- Prevent code manipulation
- Secure gift certificate codes
- Audit trail for all operations

### Business Logic Security
- Prevent code reuse (if single-use)
- Validate usage limits
- Check expiration dates
- Prevent stacking (if disabled)
- Verify customer eligibility

---

## 9. Testing Strategy

### Unit Tests
```php
- test_coupon_validation()
- test_discount_calculation()
- test_usage_limit_enforcement()
- test_expiration_check()
- test_gift_certificate_redemption()
- test_referral_tracking()
- test_loyalty_points_calculation()
```

### Integration Tests
```php
- test_complete_coupon_flow()
- test_gift_certificate_purchase_and_redeem()
- test_referral_workflow()
- test_loyalty_points_redemption()
```

### Test Scenarios
1. **Coupon Redemption:** Apply and validate coupon
2. **Gift Certificate:** Purchase, send, and redeem
3. **Referral:** Generate code, track referral, reward both parties
4. **Loyalty Points:** Earn and redeem points
5. **Expiration:** Validate expired coupons rejected
6. **Usage Limits:** Test single-use and per-customer limits

---

## 10. Error Handling

### Error Messages
```php
'invalid_coupon' => 'Invalid coupon code.',
'expired_coupon' => 'This coupon has expired.',
'usage_limit_reached' => 'Coupon usage limit reached.',
'not_eligible' => 'You are not eligible for this coupon.',
'min_purchase_not_met' => 'Minimum purchase amount not met.',
'service_not_eligible' => 'This coupon is not valid for selected service.',
'gift_certificate_invalid' => 'Invalid gift certificate code.',
'insufficient_balance' => 'Insufficient gift certificate balance.',
```

---

## 11. Performance Optimization

### Caching Strategy
- Cache active coupons (TTL: 15 minutes)
- Cache customer eligibility (TTL: 5 minutes)
- Cache loyalty tier (TTL: 1 hour)

---

## 12. Development Timeline

**Total Estimated Timeline:** 8 weeks (2 months)

---

## 13. Success Metrics

### Business Metrics
- Coupon redemption rate > 25%
- Gift certificate sales > $5k/month
- Referral conversion > 15%
- Loyalty program enrollment > 50%
- Revenue increase > 20%

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
