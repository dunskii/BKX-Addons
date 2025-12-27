# Booking Packages Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Booking Packages
**Price:** $119
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Create and sell service packages with bundled pricing, session credits, and validity periods. Perfect for gyms, salons, wellness centers, and any business offering package deals. Includes credit tracking, expiration management, and flexible redemption options.

### Value Proposition
- Increase revenue through upfront package sales
- Encourage customer commitment with bundled services
- Flexible package configurations (sessions, credits, time-based)
- Automatic credit tracking and deduction
- Expiration management with notifications
- Package transfer and sharing options
- Comprehensive reporting on package sales and usage

---

## 2. Features & Requirements

### Core Features
1. **Package Configuration**
   - Session-based packages (e.g., 10 sessions)
   - Credit-based packages (e.g., $500 credit)
   - Time-based packages (e.g., unlimited for 30 days)
   - Multi-service packages (bundle different services)
   - Tiered pricing (different package sizes)
   - Custom validity periods
   - Auto-renewal options

2. **Package Pricing**
   - Discounted bundled pricing
   - Percentage or fixed amount discounts
   - Dynamic pricing based on quantity
   - Promotional package pricing
   - Early bird discounts
   - Seasonal package offers
   - Member vs. non-member pricing

3. **Credit Management**
   - Automatic credit deduction on booking
   - Real-time credit balance tracking
   - Credit expiration handling
   - Manual credit adjustments
   - Credit transfer between customers
   - Partial credit usage
   - Credit history and audit trail

4. **Package Redemption**
   - Book using package credits
   - Service-specific credit values
   - Peak/off-peak credit usage
   - Provider restrictions
   - Location restrictions
   - Advance booking requirements
   - Blackout dates

5. **Expiration Management**
   - Configurable expiration periods
   - Extension options
   - Expiration notifications
   - Grace period settings
   - Auto-renewal before expiration
   - Refund policies for unused credits

6. **Package Sharing**
   - Family packages (multiple users)
   - Corporate packages
   - Gift packages
   - Transfer packages to other customers
   - Split package usage tracking

### User Roles & Permissions
- **Admin:** Create packages, set pricing, view all transactions
- **Manager:** Sell packages, manage customer packages, reports
- **Staff:** View customer package status, redeem credits
- **Customer:** Purchase packages, view credits, book with packages

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP 7.4+, WordPress REST API
- **Frontend:** React.js for package selection UI
- **Database:** MySQL 5.7+ with InnoDB
- **Payment:** Integration with BookingX payment gateways
- **Reporting:** Chart.js for analytics

### Dependencies
- BookingX Core 2.0+
- Compatible payment gateway add-on
- WordPress Cron for expiration checks
- PHP JSON extension

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/packages
GET    /wp-json/bookingx/v1/packages
GET    /wp-json/bookingx/v1/packages/{id}
PUT    /wp-json/bookingx/v1/packages/{id}
DELETE /wp-json/bookingx/v1/packages/{id}

POST   /wp-json/bookingx/v1/customer-packages/purchase
GET    /wp-json/bookingx/v1/customer-packages
GET    /wp-json/bookingx/v1/customer-packages/{id}
PUT    /wp-json/bookingx/v1/customer-packages/{id}
POST   /wp-json/bookingx/v1/customer-packages/{id}/redeem
POST   /wp-json/bookingx/v1/customer-packages/{id}/transfer
POST   /wp-json/bookingx/v1/customer-packages/{id}/extend

GET    /wp-json/bookingx/v1/customer-packages/credits
POST   /wp-json/bookingx/v1/customer-packages/credits/adjust
GET    /wp-json/bookingx/v1/customer-packages/history
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│  BookingX Core      │
│  - Booking Engine   │
│  - Payment System   │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────┐
│  Package Management Module  │
│  - Package Builder          │
│  - Credit Manager           │
│  - Redemption Engine        │
└──────────┬──────────────────┘
           │
           ├──────────┬──────────┐
           ▼          ▼          ▼
┌─────────────┐ ┌──────────┐ ┌──────────┐
│  Pricing    │ │Expiration│ │ Reports  │
│  Engine     │ │ Manager  │ │ Module   │
└─────────────┘ └──────────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\Packages;

class PackageManager {
    - create_package()
    - update_package()
    - delete_package()
    - get_packages()
    - get_package()
    - calculate_package_price()
    - validate_package()
}

class PackageBuilder {
    - set_type()
    - set_services()
    - set_credits()
    - set_validity()
    - set_pricing()
    - set_restrictions()
    - build()
}

class CustomerPackageManager {
    - purchase_package()
    - get_customer_packages()
    - get_package_credits()
    - extend_package()
    - transfer_package()
    - cancel_package()
}

class CreditManager {
    - get_available_credits()
    - deduct_credits()
    - add_credits()
    - adjust_credits()
    - calculate_credit_value()
    - get_credit_history()
    - validate_sufficient_credits()
}

class PackageRedemptionEngine {
    - can_redeem()
    - redeem_for_booking()
    - calculate_credit_cost()
    - validate_restrictions()
    - apply_package_to_booking()
}

class ExpirationManager {
    - check_expiration()
    - send_expiration_notices()
    - auto_extend_packages()
    - handle_expired_packages()
    - calculate_refund()
}

class PackagePricingEngine {
    - calculate_discount()
    - apply_tiered_pricing()
    - calculate_final_price()
    - get_pricing_tiers()
}

class PackageReporting {
    - get_sales_report()
    - get_redemption_report()
    - get_revenue_analysis()
    - get_expiration_report()
    - export_package_data()
}
```

---

## 5. Database Schema

### Table: `bkx_packages`
```sql
CREATE TABLE bkx_packages (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    package_type ENUM('session', 'credit', 'unlimited', 'multi_service') NOT NULL,
    session_count INT COMMENT 'Number of sessions (for session-based)',
    credit_amount DECIMAL(10,2) COMMENT 'Credit value (for credit-based)',
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    discount_type ENUM('percentage', 'fixed'),
    discount_value DECIMAL(10,2),
    validity_period INT COMMENT 'Validity in days',
    validity_type ENUM('from_purchase', 'from_first_use', 'fixed_date'),
    expiry_date DATE COMMENT 'For fixed date validity',
    is_unlimited TINYINT(1) DEFAULT 0,
    max_bookings_per_day INT,
    max_bookings_per_week INT,
    allow_sharing TINYINT(1) DEFAULT 0,
    max_users INT DEFAULT 1,
    auto_renewal TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX status_idx (status),
    INDEX package_type_idx (package_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_package_services`
```sql
CREATE TABLE bkx_package_services (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    credit_cost DECIMAL(10,2) COMMENT 'Credits required per booking',
    session_count INT COMMENT 'Sessions allocated for this service',
    is_included TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    INDEX package_id_idx (package_id),
    INDEX service_id_idx (service_id),
    UNIQUE KEY unique_package_service (package_id, service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_package_restrictions`
```sql
CREATE TABLE bkx_package_restrictions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_id BIGINT(20) UNSIGNED NOT NULL,
    restriction_type ENUM('provider', 'location', 'day_of_week', 'time_range', 'blackout_date') NOT NULL,
    restriction_value VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX package_id_idx (package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_customer_packages`
```sql
CREATE TABLE bkx_customer_packages (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    purchase_date DATETIME NOT NULL,
    activation_date DATETIME COMMENT 'First use date',
    expiry_date DATE,
    original_sessions INT,
    remaining_sessions INT,
    original_credits DECIMAL(10,2),
    remaining_credits DECIMAL(10,2),
    price_paid DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    payment_id BIGINT(20) UNSIGNED,
    status ENUM('active', 'expired', 'cancelled', 'completed') NOT NULL DEFAULT 'active',
    is_shared TINYINT(1) DEFAULT 0,
    shared_with LONGTEXT COMMENT 'JSON array of user IDs',
    auto_renew TINYINT(1) DEFAULT 0,
    renewal_package_id BIGINT(20) UNSIGNED,
    notes TEXT,
    purchased_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX package_id_idx (package_id),
    INDEX customer_id_idx (customer_id),
    INDEX status_idx (status),
    INDEX expiry_date_idx (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_package_usage`
```sql
CREATE TABLE bkx_package_usage (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_package_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    usage_type ENUM('session', 'credit') NOT NULL,
    sessions_used INT DEFAULT 0,
    credits_used DECIMAL(10,2) DEFAULT 0,
    used_by BIGINT(20) UNSIGNED NOT NULL,
    used_at DATETIME NOT NULL,
    notes TEXT,
    INDEX customer_package_id_idx (customer_package_id),
    INDEX booking_id_idx (booking_id),
    INDEX used_at_idx (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_package_credit_adjustments`
```sql
CREATE TABLE bkx_package_credit_adjustments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_package_id BIGINT(20) UNSIGNED NOT NULL,
    adjustment_type ENUM('add', 'deduct', 'correction') NOT NULL,
    sessions_adjusted INT DEFAULT 0,
    credits_adjusted DECIMAL(10,2) DEFAULT 0,
    reason VARCHAR(255),
    adjusted_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX customer_package_id_idx (customer_package_id),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_package_transfers`
```sql
CREATE TABLE bkx_package_transfers (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_package_id BIGINT(20) UNSIGNED NOT NULL,
    from_customer_id BIGINT(20) UNSIGNED NOT NULL,
    to_customer_id BIGINT(20) UNSIGNED NOT NULL,
    transfer_type ENUM('full', 'partial') NOT NULL,
    sessions_transferred INT DEFAULT 0,
    credits_transferred DECIMAL(10,2) DEFAULT 0,
    transfer_reason TEXT,
    transferred_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    INDEX customer_package_id_idx (customer_package_id),
    INDEX from_customer_id_idx (from_customer_id),
    INDEX to_customer_id_idx (to_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    'enable_packages' => true,
    'allow_customer_purchase' => true,
    'require_package_approval' => false,
    'package_display_mode' => 'grid', // grid|list|table
    'show_savings_amount' => true,
    'show_per_session_price' => true,
    'expiration_settings' => [
        'enable_expiration' => true,
        'default_validity_days' => 365,
        'grace_period_days' => 7,
        'allow_extensions' => true,
        'extension_fee_type' => 'percentage', // percentage|fixed
        'extension_fee_amount' => 10,
    ],
    'notification_settings' => [
        'purchase_confirmation' => true,
        'expiration_warning_days' => [30, 7, 1],
        'low_credit_threshold' => 3,
        'renewal_reminder_days' => 14,
    ],
    'sharing_settings' => [
        'allow_package_sharing' => true,
        'allow_package_transfer' => true,
        'transfer_fee_type' => 'fixed',
        'transfer_fee_amount' => 25,
        'require_transfer_approval' => false,
    ],
    'refund_policy' => [
        'allow_refunds' => true,
        'refund_unused_only' => true,
        'refund_percentage' => 80,
        'admin_fee' => 20,
    ],
    'auto_renewal_settings' => [
        'enable_auto_renewal' => true,
        'renewal_discount' => 5,
        'auto_renew_days_before' => 7,
        'max_renewal_attempts' => 3,
    ],
    'credit_settings' => [
        'allow_partial_credits' => true,
        'round_credits' => false,
        'peak_credit_multiplier' => 1.5,
    ],
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Package Selection Page**
   - Grid/list view of available packages
   - Package cards with details
   - Pricing display with savings
   - Comparison table
   - Filter by service/price/duration
   - "Most Popular" badges
   - Add to cart functionality

2. **Package Details Modal**
   - Complete package information
   - Included services list
   - Terms and conditions
   - Validity information
   - Restrictions display
   - Purchase button
   - Gift package option

3. **Customer Package Dashboard**
   - Active packages list
   - Credit/session balance
   - Expiry date countdown
   - Usage history
   - Purchase new package button
   - Package details link
   - Transfer/share options

4. **Package Redemption Interface**
   - Select package for booking
   - Show credit cost
   - Available balance display
   - Insufficient credit warning
   - Alternative package suggestions
   - Pay difference option

### Backend Components

1. **Package Builder**
   - Package information form
   - Service selection interface
   - Pricing configuration
   - Credit allocation
   - Validity settings
   - Restriction builder
   - Preview functionality

2. **Package Management List**
   - Searchable package table
   - Filter by type/status
   - Bulk actions (activate/deactivate)
   - Clone package option
   - Sales statistics column
   - Quick edit functionality

3. **Customer Package Manager**
   - Customer package list
   - Filter by customer/status/expiry
   - Manual credit adjustment
   - Extend package interface
   - Transfer package interface
   - Cancel/refund options
   - Usage history view

4. **Package Reports**
   - Sales report (revenue, quantity)
   - Redemption report
   - Expiration report
   - Popular packages analysis
   - Customer lifetime value
   - Export to CSV/PDF

---

## 8. Security Considerations

### Data Security
- **Payment Data:** Secure handling through payment gateway
- **Credit Validation:** Server-side credit verification
- **SQL Injection:** Prepared statements for all queries
- **XSS Prevention:** Sanitize all user inputs
- **CSRF Protection:** WordPress nonces on all forms

### Authorization
- Customers can only access own packages
- Staff limited to viewing customer packages
- Manager can manage and adjust packages
- Admin has full control
- Capability checks for all operations

### Business Logic Security
- Prevent negative credit balances
- Validate package availability before purchase
- Verify credit sufficiency before redemption
- Prevent unauthorized transfers
- Audit trail for all credit adjustments
- Prevent expired package usage

### Payment Security
- Secure payment processing
- Refund verification
- Transaction logging
- Payment gateway compliance

---

## 9. Testing Strategy

### Unit Tests
```php
- test_package_creation()
- test_package_pricing_calculation()
- test_credit_deduction()
- test_session_counting()
- test_expiration_logic()
- test_package_purchase()
- test_redemption_validation()
- test_transfer_logic()
- test_refund_calculation()
- test_auto_renewal()
```

### Integration Tests
```php
- test_complete_purchase_flow()
- test_redemption_with_booking()
- test_package_expiration_handling()
- test_credit_adjustment_workflow()
- test_package_transfer_flow()
- test_auto_renewal_process()
- test_multi_user_package_sharing()
- test_refund_processing()
```

### Test Scenarios
1. **Purchase Session Package:** Buy 10-session package
2. **Credit Package Redemption:** Use credits for booking
3. **Package Expiration:** Test expiration and notifications
4. **Package Transfer:** Transfer to another customer
5. **Shared Package:** Multiple users using same package
6. **Auto-Renewal:** Test auto-renewal before expiration
7. **Partial Credits:** Use partial credits for booking
8. **Refund:** Request refund for unused package

---

## 10. Error Handling

### Error Categories
1. **Purchase Errors:** Payment failures, invalid packages
2. **Redemption Errors:** Insufficient credits, expired packages
3. **Transfer Errors:** Invalid recipients, transfer restrictions
4. **Validation Errors:** Invalid data, business rule violations

### Error Messages (User-Facing)
```php
'package_not_found' => 'The selected package is not available.',
'package_inactive' => 'This package is no longer active.',
'insufficient_credits' => 'You don\'t have enough credits. Current balance: %d',
'package_expired' => 'This package expired on %s.',
'invalid_service' => 'This service is not included in your package.',
'restriction_violation' => 'This booking violates package restrictions.',
'payment_failed' => 'Package purchase failed. Please try again.',
'transfer_not_allowed' => 'This package cannot be transferred.',
'already_redeemed' => 'All package sessions have been used.',
'max_users_reached' => 'Maximum number of users for this package reached.',
```

### Logging
- All package purchases
- Credit usage and adjustments
- Package transfers
- Expiration events
- Refund transactions
- Error conditions with context

---

## 11. Cron Jobs & Automation

### Scheduled Tasks
```php
// Daily tasks
bkx_packages_check_expiration - Mark expired packages
bkx_packages_send_expiration_notices - Send upcoming expiration warnings
bkx_packages_auto_renewal - Process auto-renewals

// Weekly tasks
bkx_packages_usage_summary - Send weekly usage summaries
bkx_packages_cleanup_old - Archive old cancelled packages
```

---

## 12. Performance Optimization

### Caching Strategy
- Cache active packages list (TTL: 1 hour)
- Cache customer package data (TTL: 5 minutes)
- Cache credit calculations (TTL: 1 minute)
- Cache package pricing (TTL: 30 minutes)

### Database Optimization
- Indexed queries for customer package lookup
- Optimize credit balance calculations
- Paginate usage history
- Archive expired packages after 1 year

### Query Optimization
- Use database views for complex reports
- Batch expiration checks
- Optimize redemption validation queries

---

## 13. Internationalization

### Multi-Currency Support
- Package pricing in multiple currencies
- Currency conversion for credits
- Display prices in customer's currency

### Languages
- Translatable strings via WordPress i18n
- RTL support
- Currency and number formatting
- Date format localization

---

## 14. Documentation Requirements

### User Documentation
1. **Customer Guide**
   - How to purchase packages
   - Using package credits
   - Understanding expiration
   - Transferring packages

2. **Admin Guide**
   - Creating packages
   - Pricing strategies
   - Managing customer packages
   - Handling refunds
   - Reports and analytics

### Developer Documentation
1. **API Reference**
   - REST API endpoints
   - Filter hooks
   - Action hooks

2. **Integration Guide**
   - Custom package types
   - Payment gateway integration
   - Custom pricing logic

---

## 15. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema creation
- [ ] Core plugin structure
- [ ] Settings page implementation
- [ ] Basic package management

### Phase 2: Package Builder (Week 3-4)
- [ ] Package creation interface
- [ ] Service association
- [ ] Pricing configuration
- [ ] Restriction builder
- [ ] Validation logic

### Phase 3: Purchase Flow (Week 5-6)
- [ ] Package selection UI
- [ ] Purchase processing
- [ ] Payment integration
- [ ] Confirmation emails
- [ ] Customer package activation

### Phase 4: Credit Management (Week 7-8)
- [ ] Credit tracking system
- [ ] Redemption engine
- [ ] Credit adjustment interface
- [ ] Usage history tracking
- [ ] Balance calculations

### Phase 5: Advanced Features (Week 9-10)
- [ ] Package sharing functionality
- [ ] Transfer system
- [ ] Auto-renewal implementation
- [ ] Expiration management
- [ ] Refund processing

### Phase 6: Reporting (Week 11)
- [ ] Sales reports
- [ ] Usage analytics
- [ ] Revenue analysis
- [ ] Export functionality

### Phase 7: Testing & QA (Week 12-13)
- [ ] Unit testing
- [ ] Integration testing
- [ ] User acceptance testing
- [ ] Performance testing
- [ ] Security audit

### Phase 8: Documentation & Launch (Week 14)
- [ ] User documentation
- [ ] Admin guide
- [ ] Video tutorials
- [ ] Production release

**Total Estimated Timeline:** 14 weeks (3.5 months)

---

## 16. Maintenance & Support

### Update Schedule
- Bug fixes: Bi-weekly
- Feature updates: Quarterly
- Security patches: As needed

### Monitoring
- Package sales tracking
- Redemption rates
- Expiration rates
- Refund requests
- Error rates

---

## 17. Dependencies & Requirements

### Required
- BookingX Core 2.0+
- Payment gateway add-on

### Server Requirements
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+
- WordPress 5.8+
- 128MB+ PHP memory

---

## 18. Success Metrics

### Technical Metrics
- Purchase success rate > 99%
- Credit calculation accuracy 100%
- Page load time < 2 seconds
- Zero credit discrepancies

### Business Metrics
- Activation rate > 30%
- Package sales conversion > 15%
- Average package value > $200
- Customer satisfaction > 4.5/5
- Redemption rate > 80%

---

## 19. Known Limitations

1. **Credit Precision:** Limited to 2 decimal places
2. **Sharing Limit:** Maximum 10 users per package
3. **Service Limit:** Maximum 50 services per package
4. **Transfer Restrictions:** Cannot transfer partially used packages (configurable)

---

## 20. Future Enhancements

### Version 2.0 Roadmap
- [ ] Dynamic package builder
- [ ] AI-powered package recommendations
- [ ] Loyalty program integration
- [ ] Gift card integration
- [ ] Marketplace for buying/selling packages
- [ ] Mobile wallet integration

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
