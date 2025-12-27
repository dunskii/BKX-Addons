# Multiple Service Booking Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Multiple Service Booking
**Price:** $79
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Allow customers to book multiple services in a single transaction with intelligent scheduling, cart functionality, and bundled pricing. Perfect for salons, spas, medical clinics, and any business offering complementary services. Includes smart time allocation, service dependencies, and package deals.

### Value Proposition
- Book multiple services in one transaction
- Shopping cart experience for services
- Automatic time slot calculation
- Service bundles and packages
- Bundled pricing discounts
- Sequential or parallel service scheduling
- Reduce booking friction
- Increase average transaction value

---

## 2. Features & Requirements

### Core Features
1. **Service Cart**
   - Add multiple services to cart
   - Remove services from cart
   - Save cart for later
   - Cart expiration
   - Cart recovery emails
   - Quick add suggestions

2. **Smart Scheduling**
   - Sequential service scheduling
   - Parallel service scheduling (different providers)
   - Buffer time between services
   - Automatic time slot calculation
   - Provider availability across services
   - Resource conflict detection

3. **Bundle Pricing**
   - Service packages/combos
   - Bundled discount pricing
   - Volume discounts
   - Percentage or fixed discounts
   - Dynamic bundle suggestions
   - Popular combinations

4. **Service Dependencies**
   - Required service combinations
   - Recommended add-ons
   - Service prerequisites
   - Service order restrictions
   - Incompatible services
   - Complementary services

5. **Checkout Optimization**
   - Single checkout for all services
   - Combined payment processing
   - Unified confirmation
   - Single invoice/receipt
   - Batch notifications

6. **Provider Assignment**
   - Same provider for all services
   - Different providers option
   - Provider preferences
   - Automatic provider assignment
   - Provider switching

### User Roles & Permissions
- **Admin:** Full multi-service booking management
- **Manager:** Configure bundles, view reports
- **Staff:** View multi-service bookings
- **Customer:** Book multiple services, manage cart

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP 7.4+, WordPress REST API
- **Frontend:** React.js for cart interface
- **Database:** MySQL 5.7+ with InnoDB
- **Session:** WordPress transients for cart
- **Payment:** Single payment gateway transaction

### Dependencies
- BookingX Core 2.0+
- Compatible payment gateway add-on
- WordPress Transient API
- PHP JSON extension

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/cart/add
DELETE /wp-json/bookingx/v1/cart/remove/{service_id}
GET    /wp-json/bookingx/v1/cart
DELETE /wp-json/bookingx/v1/cart/clear
POST   /wp-json/bookingx/v1/cart/save

POST   /wp-json/bookingx/v1/multi-booking/checkout
POST   /wp-json/bookingx/v1/multi-booking/calculate-schedule
GET    /wp-json/bookingx/v1/multi-booking/availability

POST   /wp-json/bookingx/v1/service-bundles
GET    /wp-json/bookingx/v1/service-bundles
GET    /wp-json/bookingx/v1/service-bundles/{id}
PUT    /wp-json/bookingx/v1/service-bundles/{id}

GET    /wp-json/bookingx/v1/service-recommendations
POST   /wp-json/bookingx/v1/multi-booking/validate
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│  BookingX Core      │
│  - Booking Engine   │
└──────────┬──────────┘
           │
           ▼
┌────────────────────────────┐
│  Multi-Service Module      │
│  - Cart Manager            │
│  - Bundle Manager          │
│  - Schedule Calculator     │
└──────────┬─────────────────┘
           │
           ├──────────┬──────────┐
           ▼          ▼          ▼
┌──────────┐ ┌──────────┐ ┌──────────┐
│ Pricing  │ │Provider  │ │Dependency│
│ Engine   │ │ Allocator│ │ Manager  │
└──────────┘ └──────────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\MultiService;

class CartManager {
    - add_to_cart()
    - remove_from_cart()
    - get_cart()
    - clear_cart()
    - save_cart()
    - restore_cart()
    - get_cart_total()
    - validate_cart()
}

class MultiBookingManager {
    - create_multi_booking()
    - get_multi_booking()
    - cancel_multi_booking()
    - modify_multi_booking()
    - get_booking_services()
}

class BundleManager {
    - create_bundle()
    - get_bundles()
    - get_bundle()
    - apply_bundle_pricing()
    - suggest_bundles()
    - validate_bundle()
}

class ScheduleCalculator {
    - calculate_sequential_schedule()
    - calculate_parallel_schedule()
    - find_available_slots()
    - allocate_buffer_time()
    - validate_schedule()
    - optimize_schedule()
}

class ServiceDependencyManager {
    - get_dependencies()
    - validate_dependencies()
    - get_required_services()
    - get_recommended_services()
    - check_incompatibilities()
}

class PricingEngine {
    - calculate_bundle_price()
    - apply_volume_discount()
    - calculate_final_price()
    - get_price_breakdown()
}

class ProviderAllocator {
    - assign_providers()
    - find_available_provider()
    - check_provider_availability()
    - optimize_provider_allocation()
}

class CheckoutProcessor {
    - process_multi_checkout()
    - create_all_bookings()
    - process_payment()
    - send_confirmations()
    - handle_checkout_error()
}
```

---

## 5. Database Schema

### Table: `bkx_service_carts`
```sql
CREATE TABLE bkx_service_carts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED,
    session_id VARCHAR(255) NOT NULL,
    cart_data LONGTEXT NOT NULL COMMENT 'JSON cart contents',
    total_amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    expires_at DATETIME NOT NULL,
    saved TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX session_id_idx (session_id),
    INDEX expires_at_idx (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_multi_bookings`
```sql
CREATE TABLE bkx_multi_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    booking_group_id VARCHAR(50) NOT NULL UNIQUE,
    total_services INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    total_duration INT NOT NULL COMMENT 'Total minutes',
    total_price DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    final_price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    payment_id BIGINT(20) UNSIGNED,
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    schedule_type ENUM('sequential', 'parallel', 'mixed') NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX customer_id_idx (customer_id),
    INDEX booking_group_id_idx (booking_group_id),
    INDEX booking_date_idx (booking_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_multi_booking_services`
```sql
CREATE TABLE bkx_multi_booking_services (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    multi_booking_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Individual booking reference',
    service_id BIGINT(20) UNSIGNED NOT NULL,
    provider_id BIGINT(20) UNSIGNED,
    service_order INT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at DATETIME NOT NULL,
    INDEX multi_booking_id_idx (multi_booking_id),
    INDEX booking_id_idx (booking_id),
    INDEX service_id_idx (service_id),
    INDEX provider_id_idx (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_service_bundles`
```sql
CREATE TABLE bkx_service_bundles (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    service_ids LONGTEXT NOT NULL COMMENT 'JSON array of service IDs',
    is_flexible TINYINT(1) DEFAULT 0 COMMENT 'Allow partial bundle',
    min_services INT DEFAULT 2,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    valid_from DATE,
    valid_until DATE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_service_dependencies`
```sql
CREATE TABLE bkx_service_dependencies (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    dependency_type ENUM('requires', 'recommends', 'incompatible', 'prerequisite') NOT NULL,
    related_service_id BIGINT(20) UNSIGNED NOT NULL,
    order_constraint ENUM('before', 'after', 'any', 'none') DEFAULT 'any',
    is_mandatory TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX service_id_idx (service_id),
    INDEX related_service_id_idx (related_service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    'cart_settings' => [
        'enable_cart' => true,
        'cart_expiration_minutes' => 30,
        'max_services_per_cart' => 10,
        'allow_save_cart' => true,
        'send_cart_recovery_email' => true,
        'recovery_email_delay_hours' => 2,
    ],

    'scheduling_settings' => [
        'default_schedule_type' => 'sequential',
        'allow_parallel_booking' => true,
        'buffer_time_minutes' => 15,
        'auto_calculate_times' => true,
        'allow_same_day_multiple' => true,
    ],

    'bundle_settings' => [
        'enable_bundles' => true,
        'auto_suggest_bundles' => true,
        'show_bundle_savings' => true,
        'allow_custom_bundles' => false,
    ],

    'pricing_settings' => [
        'enable_volume_discount' => true,
        'volume_discount_tiers' => [
            ['min' => 2, 'discount' => 5],
            ['min' => 3, 'discount' => 10],
            ['min' => 5, 'discount' => 15],
        ],
        'apply_bundle_discount' => true,
        'show_price_breakdown' => true,
    ],

    'provider_settings' => [
        'default_provider_mode' => 'same', // same|different|flexible
        'allow_provider_selection' => true,
        'auto_assign_providers' => true,
        'prefer_same_provider' => true,
    ],

    'dependency_settings' => [
        'enforce_dependencies' => true,
        'show_recommendations' => true,
        'auto_add_required_services' => false,
        'warn_incompatible' => true,
    ],

    'checkout_settings' => [
        'single_payment_only' => true,
        'combine_confirmations' => true,
        'generate_single_invoice' => true,
    ],

    'notification_settings' => [
        'send_multi_booking_confirmation' => true,
        'include_all_services_in_email' => true,
        'send_individual_reminders' => true,
        'reminder_hours_before' => [24, 2],
    ],
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Service Cart Widget**
   - Cart icon with item count
   - Mini cart preview
   - Add to cart buttons
   - Remove from cart
   - Quick view cart
   - Proceed to checkout button

2. **Cart Page**
   - Service list with details
   - Quantity/duration for each
   - Remove service option
   - Continue shopping button
   - Price breakdown
   - Discount display
   - Total price
   - Checkout button

3. **Multi-Service Booking Flow**
   - Service selection interface
   - Bundle suggestions
   - Add-on recommendations
   - Schedule preview
   - Provider selection
   - Date/time picker
   - Price calculator

4. **Schedule Visualizer**
   - Timeline view of services
   - Sequential vs parallel display
   - Buffer times shown
   - Provider assignments
   - Total duration display

5. **Bundle Selector**
   - Pre-configured bundles
   - Bundle pricing display
   - Savings indicator
   - Add bundle to cart
   - Customize bundle option

6. **Checkout Summary**
   - All services listed
   - Schedule overview
   - Provider assignments
   - Price breakdown
   - Discount summary
   - Payment section
   - Terms acceptance

### Backend Components

1. **Multi-Booking Manager**
   - Multi-booking list
   - Filter by customer, date, status
   - Service count column
   - Total price display
   - Quick view details
   - Export functionality

2. **Bundle Builder**
   - Create bundles
   - Add services to bundle
   - Set discount
   - Configure flexibility
   - Set validity period
   - Preview bundle

3. **Service Dependency Manager**
   - Configure dependencies
   - Set required services
   - Add recommendations
   - Define incompatibilities
   - Order constraints

4. **Cart Analytics**
   - Abandoned carts
   - Most added services
   - Average services per cart
   - Cart conversion rate
   - Popular bundles

5. **Reports**
   - Multi-booking revenue
   - Average services per booking
   - Bundle performance
   - Provider utilization
   - Service combinations

---

## 8. Security Considerations

### Data Security
- **Cart Session:** Secure session management
- **SQL Injection:** Prepared statements
- **XSS Prevention:** Sanitize inputs
- **CSRF Protection:** WordPress nonces

### Authorization
- Customers access own carts only
- Validate service availability
- Verify provider assignments
- Secure checkout process

### Business Logic Security
- Validate cart contents
- Verify pricing calculations
- Prevent scheduling conflicts
- Validate dependencies
- Audit trail for bookings

---

## 9. Testing Strategy

### Unit Tests
```php
- test_add_to_cart()
- test_remove_from_cart()
- test_calculate_bundle_price()
- test_sequential_schedule_calculation()
- test_parallel_schedule_calculation()
- test_dependency_validation()
- test_provider_allocation()
- test_multi_checkout_process()
```

### Integration Tests
```php
- test_complete_multi_booking_flow()
- test_bundle_purchase_flow()
- test_cart_to_checkout_flow()
- test_payment_processing()
- test_schedule_optimization()
```

### Test Scenarios
1. **Multi-Service Cart:** Add 3 services, checkout
2. **Bundle Purchase:** Select bundle, book all services
3. **Sequential Booking:** Book spa services sequentially
4. **Parallel Booking:** Book with 2 different providers
5. **Dependency Check:** Add service with requirements
6. **Cart Recovery:** Abandon cart, receive recovery email
7. **Volume Discount:** Book 5+ services, get discount

---

## 10. Error Handling

### Error Messages (User-Facing)
```php
'cart_expired' => 'Your cart has expired. Please add services again.',
'service_unavailable' => 'One or more services are no longer available.',
'scheduling_conflict' => 'Unable to schedule all services. Please select different times.',
'dependency_not_met' => 'This service requires: %s',
'incompatible_services' => 'These services cannot be booked together: %s',
'provider_unavailable' => 'No provider available for the selected time.',
'payment_failed' => 'Payment failed. No bookings were created.',
```

### Logging
- Cart operations
- Multi-booking creation
- Scheduling calculations
- Payment processing
- Error conditions

---

## 11. Performance Optimization

### Caching Strategy
- Cache bundles (TTL: 1 hour)
- Cache service dependencies (TTL: 30 minutes)
- Cache pricing calculations (TTL: 5 minutes)

### Database Optimization
- Indexed queries
- Optimize schedule calculations
- Batch booking creation

---

## 12. Development Timeline

### Total Estimated Timeline: 10 weeks (2.5 months)

---

## 13. Success Metrics

### Business Metrics
- Activation rate > 30%
- Average services per booking > 2
- Cart conversion rate > 60%
- Revenue per booking increase > 40%
- Bundle adoption > 25%

---

## 14. Dependencies & Requirements

### Required
- BookingX Core 2.0+
- Payment gateway add-on

### Server Requirements
- PHP 7.4+
- MySQL 5.7+
- WordPress 5.8+

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
