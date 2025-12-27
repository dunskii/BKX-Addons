# Group Bookings & Quantity Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Group Bookings & Quantity
**Price:** $99
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Enable customers to book for multiple people in a single transaction with party organizer management. Perfect for events, tours, parties, group activities, and any service where one person books for multiple attendees. Includes guest management, quantity-based pricing, and organizer controls.

### Value Proposition
- Book multiple spots in one transaction
- Dedicated party/group organizer features
- Quantity-based pricing tiers
- Guest information collection
- Group coordinator dashboard
- Split payment options
- Headcount management
- Event organizer tools

---

## 2. Features & Requirements

### Core Features
1. **Quantity Selection**
   - Book for multiple people/spots
   - Dynamic quantity selector (1-100+)
   - Real-time price calculation
   - Capacity validation
   - Per-person vs. per-group pricing
   - Minimum/maximum group sizes
   - Quantity limits per booking

2. **Party Organizer Management**
   - Organizer profile and contact info
   - Manage group attendees
   - Add/remove guests
   - Guest information collection
   - Guest communication tools
   - Organizer dashboard
   - Transfer organizer role

3. **Guest Management**
   - Individual guest details
   - Name, age, preferences
   - Dietary restrictions
   - Special requirements
   - Guest check-in tracking
   - Guest modifications
   - Export guest lists

4. **Pricing Models**
   - Per-person pricing
   - Per-group flat rate
   - Tiered quantity pricing (e.g., 1-5 @ $50, 6-10 @ $45)
   - Group discounts
   - Organizer free/discounted
   - Children vs. adult pricing
   - Early bird group rates

5. **Payment Options**
   - Single payment by organizer
   - Split payment among guests
   - Deposit + balance collection
   - Per-person payment links
   - Group payment tracking
   - Partial payment support

6. **Capacity Management**
   - Reserve multiple spots
   - Group capacity limits
   - Available spots display
   - Group size restrictions
   - Waitlist for groups
   - Release unreserved spots

### User Roles & Permissions
- **Admin:** Full group booking management
- **Manager:** Create/modify group bookings
- **Staff:** View group details, check-in guests
- **Organizer:** Manage own group, add/remove guests
- **Customer:** Book for groups, view own bookings

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP 7.4+, WordPress REST API
- **Frontend:** React.js for quantity selector and guest management
- **Database:** MySQL 5.7+ with InnoDB
- **Payment:** Multi-payment gateway support
- **Email:** Automated notifications to organizer and guests

### Dependencies
- BookingX Core 2.0+
- Compatible payment gateway add-on
- PHP JSON extension
- WordPress email functionality

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/group-bookings
GET    /wp-json/bookingx/v1/group-bookings
GET    /wp-json/bookingx/v1/group-bookings/{id}
PUT    /wp-json/bookingx/v1/group-bookings/{id}
DELETE /wp-json/bookingx/v1/group-bookings/{id}

POST   /wp-json/bookingx/v1/group-bookings/{id}/guests
GET    /wp-json/bookingx/v1/group-bookings/{id}/guests
PUT    /wp-json/bookingx/v1/group-bookings/{id}/guests/{guest_id}
DELETE /wp-json/bookingx/v1/group-bookings/{id}/guests/{guest_id}

POST   /wp-json/bookingx/v1/group-bookings/{id}/payments
GET    /wp-json/bookingx/v1/group-bookings/{id}/payments
POST   /wp-json/bookingx/v1/group-bookings/{id}/payment-links

GET    /wp-json/bookingx/v1/group-bookings/{id}/organizer
PUT    /wp-json/bookingx/v1/group-bookings/{id}/organizer
POST   /wp-json/bookingx/v1/group-bookings/{id}/transfer-organizer

POST   /wp-json/bookingx/v1/group-bookings/calculate-price
GET    /wp-json/bookingx/v1/group-bookings/availability
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
┌─────────────────────────────┐
│  Group Booking Module       │
│  - Quantity Manager         │
│  - Guest Manager            │
│  - Organizer Controller     │
└──────────┬──────────────────┘
           │
           ├──────────┬──────────┐
           ▼          ▼          ▼
┌──────────┐ ┌──────────┐ ┌──────────┐
│ Pricing  │ │ Payment  │ │ Capacity │
│ Engine   │ │ Splitter │ │ Manager  │
└──────────┘ └──────────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\GroupBookings;

class GroupBookingManager {
    - create_group_booking()
    - update_group_booking()
    - cancel_group_booking()
    - get_group_booking()
    - get_group_bookings()
    - validate_quantity()
    - check_capacity()
}

class GuestManager {
    - add_guest()
    - update_guest()
    - remove_guest()
    - get_guests()
    - get_guest()
    - import_guests()
    - export_guests()
    - validate_guest_data()
}

class OrganizerManager {
    - set_organizer()
    - get_organizer_info()
    - transfer_organizer()
    - get_organizer_bookings()
    - send_organizer_notification()
    - get_organizer_dashboard_data()
}

class QuantityPricingEngine {
    - calculate_per_person_price()
    - calculate_per_group_price()
    - calculate_tiered_price()
    - apply_group_discount()
    - calculate_children_pricing()
    - get_final_price()
}

class PaymentSplitter {
    - calculate_split_amounts()
    - generate_payment_links()
    - track_individual_payments()
    - get_payment_status()
    - send_payment_reminders()
    - process_split_payment()
}

class GroupCapacityManager {
    - reserve_spots()
    - release_spots()
    - check_available_capacity()
    - validate_group_size()
    - calculate_remaining_spots()
    - handle_partial_groups()
}

class GuestCheckInManager {
    - check_in_guest()
    - check_in_group()
    - get_check_in_status()
    - mark_no_show()
    - export_check_in_list()
}
```

---

## 5. Database Schema

### Table: `bkx_group_bookings`
```sql
CREATE TABLE bkx_group_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Main booking reference',
    organizer_id BIGINT(20) UNSIGNED NOT NULL,
    organizer_name VARCHAR(255) NOT NULL,
    organizer_email VARCHAR(255) NOT NULL,
    organizer_phone VARCHAR(50),
    service_id BIGINT(20) UNSIGNED NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    total_quantity INT NOT NULL,
    adults_count INT DEFAULT 0,
    children_count INT DEFAULT 0,
    infants_count INT DEFAULT 0,
    pricing_type ENUM('per_person', 'per_group', 'tiered') NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    payment_type ENUM('full', 'split', 'deposit') NOT NULL DEFAULT 'full',
    amount_paid DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    special_requests TEXT,
    notes TEXT,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX organizer_id_idx (organizer_id),
    INDEX service_id_idx (service_id),
    INDEX booking_date_idx (booking_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_group_guests`
```sql
CREATE TABLE bkx_group_guests (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_booking_id BIGINT(20) UNSIGNED NOT NULL,
    guest_type ENUM('adult', 'child', 'infant', 'other') DEFAULT 'adult',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    age INT,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say'),
    dietary_restrictions TEXT,
    special_requirements TEXT,
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(50),
    checked_in TINYINT(1) DEFAULT 0,
    check_in_time DATETIME,
    notes TEXT,
    custom_fields LONGTEXT COMMENT 'JSON for custom fields',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX group_booking_id_idx (group_booking_id),
    INDEX email_idx (email),
    INDEX checked_in_idx (checked_in)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_group_payments`
```sql
CREATE TABLE bkx_group_payments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_booking_id BIGINT(20) UNSIGNED NOT NULL,
    guest_id BIGINT(20) UNSIGNED COMMENT 'NULL for organizer payment',
    payment_id BIGINT(20) UNSIGNED,
    payment_type ENUM('organizer', 'guest', 'deposit', 'balance') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    payment_link VARCHAR(500),
    payment_link_expires DATETIME,
    status ENUM('pending', 'processing', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    paid_at DATETIME,
    transaction_id VARCHAR(255),
    gateway VARCHAR(50),
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX group_booking_id_idx (group_booking_id),
    INDEX guest_id_idx (guest_id),
    INDEX payment_id_idx (payment_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_group_pricing_tiers`
```sql
CREATE TABLE bkx_group_pricing_tiers (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    min_quantity INT NOT NULL,
    max_quantity INT NOT NULL,
    price_per_person DECIMAL(10,2),
    flat_rate DECIMAL(10,2),
    discount_percentage DECIMAL(5,2),
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX service_id_idx (service_id),
    INDEX active_idx (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_group_capacity_reservations`
```sql
CREATE TABLE bkx_group_capacity_reservations (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_booking_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    reserved_spots INT NOT NULL,
    confirmed_spots INT DEFAULT 0,
    status ENUM('reserved', 'confirmed', 'released') NOT NULL DEFAULT 'reserved',
    expires_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX group_booking_id_idx (group_booking_id),
    INDEX service_id_idx (service_id),
    INDEX booking_datetime_idx (booking_date, booking_time),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    'enable_group_bookings' => true,
    'enable_quantity_selection' => true,

    'quantity_settings' => [
        'default_min_quantity' => 1,
        'default_max_quantity' => 50,
        'allow_unlimited_quantity' => false,
        'quantity_step' => 1,
        'show_quantity_selector' => true,
    ],

    'guest_management' => [
        'require_guest_details' => true,
        'required_guest_fields' => ['first_name', 'last_name'],
        'optional_guest_fields' => ['email', 'phone', 'age', 'dietary_restrictions'],
        'collect_emergency_contact' => false,
        'allow_guest_modifications' => true,
        'guest_modification_deadline_hours' => 24,
    ],

    'organizer_settings' => [
        'allow_organizer_transfer' => true,
        'organizer_discount' => 0,
        'organizer_free_spot' => false,
        'require_organizer_approval' => false,
    ],

    'pricing_settings' => [
        'default_pricing_type' => 'per_person',
        'enable_tiered_pricing' => true,
        'enable_group_discounts' => true,
        'children_pricing_type' => 'percentage', // percentage|fixed|free
        'children_discount_percentage' => 50,
        'infant_pricing' => 'free',
        'children_age_limit' => 12,
        'infant_age_limit' => 2,
    ],

    'payment_settings' => [
        'allow_split_payment' => true,
        'default_payment_type' => 'full',
        'split_payment_method' => 'equal', // equal|custom
        'payment_link_expiration_days' => 7,
        'deposit_percentage' => 20,
        'balance_due_days_before' => 7,
    ],

    'capacity_settings' => [
        'reserve_spots_on_booking' => true,
        'release_unreserved_hours' => 24,
        'allow_partial_groups' => false,
        'group_size_minimum' => 2,
        'group_size_maximum' => 100,
    ],

    'notification_settings' => [
        'notify_organizer_on_booking' => true,
        'notify_guests_on_addition' => true,
        'send_payment_reminders' => true,
        'payment_reminder_days' => [7, 3, 1],
        'send_organizer_summary' => true,
    ],
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Quantity Selector**
   - Plus/minus buttons
   - Direct number input
   - Adult/child/infant breakdown
   - Real-time price update
   - Capacity warning display
   - Minimum/maximum indicators

2. **Guest Information Form**
   - Guest details collection
   - Add multiple guests
   - Remove guest option
   - Guest type selector
   - Special requirements fields
   - Dietary restrictions
   - Import from CSV option

3. **Organizer Dashboard**
   - Group booking overview
   - Guest list management
   - Add/edit/remove guests
   - Payment status tracker
   - Send payment links
   - Export guest list
   - Communication tools

4. **Group Booking Form**
   - Service selection
   - Date/time picker
   - Quantity selector
   - Organizer information
   - Guest information section
   - Special requests
   - Payment options
   - Price breakdown display

5. **Payment Split Interface**
   - Payment distribution setup
   - Generate individual links
   - Track payment status
   - Send reminders
   - View payment history

### Backend Components

1. **Group Booking Manager**
   - Group bookings list
   - Filter by organizer, service, date, status
   - Guest count display
   - Payment status indicator
   - Quick actions (edit, cancel, view details)
   - Export functionality

2. **Group Details View**
   - Full booking information
   - Organizer details
   - Complete guest list
   - Payment breakdown
   - Check-in status
   - Edit options
   - Communication tools

3. **Pricing Configuration**
   - Tiered pricing builder
   - Add pricing tiers
   - Per-person rates
   - Group discounts
   - Children pricing
   - Preview calculator

4. **Guest Management Interface**
   - Searchable guest list
   - Bulk import
   - Individual edit
   - Check-in interface
   - Export to Excel/CSV
   - Print guest list

5. **Reports & Analytics**
   - Group booking revenue
   - Average group size
   - Popular group services
   - Payment collection rates
   - Organizer statistics
   - Guest demographics

---

## 8. Security Considerations

### Data Security
- **Personal Data:** Secure storage of guest information
- **Payment Data:** Encrypted payment links
- **GDPR Compliance:** Data export and deletion
- **SQL Injection:** Prepared statements
- **XSS Prevention:** Sanitize all inputs

### Authorization
- Organizers can only manage own groups
- Staff can view but not modify
- Managers can modify all groups
- Admin has full access
- Secure payment link generation

### Business Logic Security
- Validate quantity against capacity
- Verify organizer permissions
- Validate payment amounts
- Prevent negative quantities
- Secure guest data access
- Audit trail for modifications

---

## 9. Testing Strategy

### Unit Tests
```php
- test_group_booking_creation()
- test_quantity_validation()
- test_guest_addition()
- test_guest_removal()
- test_price_calculation_per_person()
- test_price_calculation_tiered()
- test_split_payment_calculation()
- test_capacity_reservation()
- test_organizer_transfer()
```

### Integration Tests
```php
- test_complete_group_booking_flow()
- test_split_payment_workflow()
- test_guest_management_workflow()
- test_capacity_management()
- test_organizer_dashboard_operations()
```

### Test Scenarios
1. **Large Group Booking:** Book for 30 people
2. **Split Payment:** 10 guests, split payment equally
3. **Tiered Pricing:** Book 15 people, get tier discount
4. **Guest Management:** Add/remove guests post-booking
5. **Organizer Transfer:** Transfer to new organizer
6. **Mixed Age Groups:** 5 adults, 3 children, 2 infants
7. **Capacity Check:** Attempt booking exceeding capacity
8. **Payment Links:** Generate and track payment links

---

## 10. Error Handling

### Error Categories
1. **Quantity Errors:** Exceeds capacity, below minimum
2. **Guest Errors:** Invalid data, required fields missing
3. **Payment Errors:** Failed splits, invalid amounts
4. **Capacity Errors:** Insufficient spots, conflicts

### Error Messages (User-Facing)
```php
'quantity_exceeds_capacity' => 'Only %d spots available. Please reduce quantity.',
'quantity_below_minimum' => 'Minimum group size is %d people.',
'invalid_guest_data' => 'Please provide valid information for all guests.',
'required_guest_fields' => 'Please complete all required guest fields.',
'capacity_not_available' => 'Not enough capacity for this group size.',
'payment_link_expired' => 'This payment link has expired.',
'split_payment_failed' => 'Unable to process split payment.',
'organizer_transfer_failed' => 'Unable to transfer organizer role.',
'guest_limit_reached' => 'Maximum number of guests reached.',
```

### Logging
- All group bookings
- Guest modifications
- Payment transactions
- Organizer transfers
- Capacity reservations
- Error conditions

---

## 11. Performance Optimization

### Caching Strategy
- Cache pricing tiers (TTL: 1 hour)
- Cache capacity calculations (TTL: 2 minutes)
- Cache organizer dashboard data (TTL: 5 minutes)

### Database Optimization
- Indexed queries for guest lookups
- Optimize capacity calculations
- Paginate guest lists
- Batch guest imports

---

## 12. Internationalization

### Multi-Language Support
- Translatable strings
- RTL support
- Date/time localization
- Number formatting

### Multi-Currency Support
- Pricing in multiple currencies
- Currency conversion
- Payment in customer's currency

---

## 13. Documentation Requirements

### User Documentation
1. **Organizer Guide**
   - Booking for groups
   - Managing guests
   - Payment options
   - Communication tools

2. **Admin Guide**
   - Configuring group bookings
   - Pricing tiers
   - Managing group bookings
   - Reports

---

## 14. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Core plugin structure
- [ ] Settings page

### Phase 2: Quantity & Pricing (Week 3-4)
- [ ] Quantity selector
- [ ] Pricing engine
- [ ] Tiered pricing
- [ ] Price calculation

### Phase 3: Guest Management (Week 5-6)
- [ ] Guest data collection
- [ ] Guest CRUD operations
- [ ] Import/export
- [ ] Validation

### Phase 4: Organizer Features (Week 7)
- [ ] Organizer dashboard
- [ ] Guest management interface
- [ ] Transfer functionality

### Phase 5: Payment Integration (Week 8-9)
- [ ] Split payment system
- [ ] Payment link generation
- [ ] Payment tracking

### Phase 6: Capacity Management (Week 10)
- [ ] Reservation system
- [ ] Capacity validation
- [ ] Release logic

### Phase 7: Testing & QA (Week 11-12)
- [ ] Unit testing
- [ ] Integration testing
- [ ] User acceptance testing

### Phase 8: Documentation & Launch (Week 13)
- [ ] Documentation
- [ ] Video tutorials
- [ ] Production release

**Total Estimated Timeline:** 13 weeks (3.25 months)

---

## 16. Maintenance & Support

### Update Schedule
- Bug fixes: Bi-weekly
- Feature updates: Quarterly
- Security patches: As needed

### Monitoring
- Group booking rates
- Average group size
- Payment collection rates
- Guest data quality

---

## 17. Dependencies & Requirements

### Required
- BookingX Core 2.0+
- Payment gateway add-on

### Server Requirements
- PHP 7.4+
- MySQL 5.7+
- WordPress 5.8+
- 128MB+ PHP memory

---

## 18. Success Metrics

### Technical Metrics
- Booking success rate > 98%
- Price calculation accuracy 100%
- Payment link success > 95%
- Page load time < 3 seconds

### Business Metrics
- Activation rate > 25%
- Average group size > 5
- Payment collection rate > 90%
- Customer satisfaction > 4.4/5

---

## 19. Known Limitations

1. **Maximum Group Size:** 100 guests (configurable)
2. **Payment Links:** 7-day expiration
3. **Guest Import:** CSV format only
4. **Split Payment:** Maximum 50 splits

---

## 20. Future Enhancements

### Version 2.0 Roadmap
- [ ] Guest self-registration portal
- [ ] Mobile app for organizers
- [ ] Advanced payment splits
- [ ] Group chat functionality
- [ ] Automated guest reminders
- [ ] Integration with CRM systems

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
