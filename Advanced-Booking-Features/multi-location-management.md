# Multi-Location Management Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Multi-Location Management
**Price:** $149
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Enterprise-grade centralized management system for businesses operating multiple locations. Features unified dashboard, location-specific configurations, cross-location resource sharing, centralized reporting, franchisee portal, location-based pricing, geolocation services, and comprehensive multi-location analytics.

### Value Proposition
- Centralized control of all locations
- Consistent brand experience across locations
- Location-specific customization
- Unified customer database
- Cross-location booking capabilities
- Consolidated reporting and analytics
- Franchise management tools
- Location performance comparison
- Automated location assignment
- Geographic expansion support

---

## 2. Features & Requirements

### Core Features
1. **Location Management**
   - Add/edit/delete locations
   - Location profiles with details
   - Operating hours per location
   - Contact information management
   - Location categories/types
   - Hierarchical location structure
   - Franchisee assignment
   - Location status (active/inactive)
   - Seasonal operations

2. **Centralized Dashboard**
   - All-locations overview
   - Key metrics aggregation
   - Location performance comparison
   - Real-time booking monitoring
   - Multi-location calendar view
   - Quick location switcher
   - Alerts and notifications
   - Customizable widgets

3. **Location-Specific Configuration**
   - Services per location
   - Staff assignment to locations
   - Pricing variations by location
   - Payment methods per location
   - Custom booking rules
   - Branding customization
   - Email templates per location
   - Timezone handling

4. **Resource Sharing**
   - Shared service catalog
   - Staff working at multiple locations
   - Equipment transfer between locations
   - Inventory synchronization
   - Shared customer database
   - Cross-location promotions
   - Resource allocation rules

5. **Geolocation Services**
   - Automatic location detection
   - Distance-based location suggestions
   - Map view of all locations
   - Radius-based search
   - Driving directions
   - Service area mapping
   - Mobile location
   - GPS integration

6. **Location-Based Booking**
   - Location selector on booking form
   - Availability by location
   - Preferred location saving
   - Alternative location suggestions
   - Multi-location package bookings
   - Location transfer for existing bookings
   - Cross-location rescheduling

7. **Franchise Management**
   - Franchisee portal
   - Commission tracking
   - Royalty fee calculation
   - Franchise performance metrics
   - Support ticket system
   - Training materials access
   - Compliance monitoring
   - Territory management

8. **Reporting & Analytics**
   - Consolidated reports across locations
   - Location-specific reports
   - Comparative analytics
   - Revenue by location
   - Performance benchmarking
   - Trend analysis
   - Custom report builder
   - Export capabilities

9. **Staff Management Across Locations**
   - Multi-location staff profiles
   - Schedule across locations
   - Location-specific permissions
   - Travel time between locations
   - Mileage tracking
   - Commission per location
   - Performance by location

10. **Inventory Management**
    - Stock levels per location
    - Inter-location transfers
    - Centralized purchasing
    - Location demand forecasting
    - Low stock alerts by location
    - Inventory rebalancing
    - Expiration tracking

### User Roles & Permissions
- **Network Admin:** Full control across all locations
- **Location Manager:** Manage specific location(s)
- **Franchisee:** Own location management with limits
- **Regional Manager:** Manage group of locations
- **Staff:** Work at assigned locations
- **Customer:** Book at any location

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP with WordPress Multisite support
- **Frontend:** React for location management UI
- **Maps:** Google Maps API / OpenStreetMap
- **Geolocation:** HTML5 Geolocation API
- **Database:** MySQL with location partitioning
- **Caching:** Redis for multi-location data
- **Sync:** Real-time updates via WebSockets

### Dependencies
- BookingX Core 2.0+
- WordPress (can work with or without Multisite)
- Google Maps API (or alternative)
- Geolocation service
- Redis (recommended)
- Action Scheduler

### API Integration Points
```php
// REST API Endpoints
GET    /wp-json/bookingx/v1/locations
POST   /wp-json/bookingx/v1/locations
GET    /wp-json/bookingx/v1/locations/{id}
PUT    /wp-json/bookingx/v1/locations/{id}
DELETE /wp-json/bookingx/v1/locations/{id}
GET    /wp-json/bookingx/v1/locations/nearby
GET    /wp-json/bookingx/v1/locations/{id}/services
GET    /wp-json/bookingx/v1/locations/{id}/staff
GET    /wp-json/bookingx/v1/locations/{id}/availability
GET    /wp-json/bookingx/v1/locations/{id}/bookings
GET    /wp-json/bookingx/v1/locations/{id}/analytics
POST   /wp-json/bookingx/v1/locations/{id}/transfer-booking
GET    /wp-json/bookingx/v1/locations/compare
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────────────────────┐
│    Central Management System        │
│  - Network Configuration            │
│  - Unified Dashboard                │
│  - Global Settings                  │
└──────────────┬──────────────────────┘
               │
    ┌──────────┴────────┬─────────┬──────────┐
    ▼                   ▼         ▼          ▼
┌──────────┐      ┌──────────┐ ┌──────────┐ ┌──────────┐
│Location 1│      │Location 2│ │Location 3│ │Location N│
│          │      │          │ │          │ │          │
│-Services │      │-Services │ │-Services │ │-Services │
│-Staff    │      │-Staff    │ │-Staff    │ │-Staff    │
│-Bookings │      │-Bookings │ │-Bookings │ │-Bookings │
└──────────┘      └──────────┘ └──────────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\MultiLocation;

class LocationManager {
    - create_location()
    - update_location()
    - delete_location()
    - get_location()
    - get_all_locations()
    - search_locations()
    - get_active_locations()
}

class LocationConfiguration {
    - set_location_settings()
    - get_location_settings()
    - apply_global_settings()
    - override_settings()
    - sync_settings()
}

class GeolocationService {
    - get_user_location()
    - find_nearest_location()
    - calculate_distance()
    - get_locations_in_radius()
    - generate_map_data()
    - get_directions()
}

class ResourceSharing {
    - share_resource()
    - get_shared_resources()
    - allocate_resource()
    - transfer_resource()
    - check_availability_across_locations()
}

class MultiLocationBooking {
    - book_at_location()
    - transfer_booking()
    - suggest_alternative_location()
    - get_availability_all_locations()
    - handle_cross_location_booking()
}

class FranchiseManager {
    - register_franchisee()
    - calculate_royalty()
    - track_commission()
    - manage_territory()
    - get_franchise_performance()
}

class LocationAnalytics {
    - get_location_metrics()
    - compare_locations()
    - aggregate_network_stats()
    - generate_location_report()
    - forecast_location_performance()
}

class StaffMultiLocation {
    - assign_staff_to_locations()
    - get_staff_schedule_all_locations()
    - calculate_travel_time()
    - track_location_performance()
}

class InventoryMultiLocation {
    - get_stock_by_location()
    - transfer_inventory()
    - sync_inventory()
    - forecast_demand_by_location()
    - rebalance_stock()
}

class CentralizedReporting {
    - generate_network_report()
    - export_multi_location_data()
    - schedule_reports()
    - distribute_reports()
}
```

---

## 5. Database Schema

### Table: `bkx_locations`
```sql
CREATE TABLE bkx_locations (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(200) NOT NULL,
    location_code VARCHAR(50) UNIQUE,
    location_type VARCHAR(50),
    parent_location_id BIGINT(20) UNSIGNED,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) NOT NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    phone VARCHAR(20),
    email VARCHAR(255),
    website VARCHAR(500),
    timezone VARCHAR(50) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    operating_hours LONGTEXT,
    seasonal_hours LONGTEXT,
    service_area_radius INT,
    manager_id BIGINT(20) UNSIGNED,
    franchisee_id BIGINT(20) UNSIGNED,
    settings LONGTEXT,
    branding LONGTEXT,
    is_bookable TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX location_code_idx (location_code),
    INDEX status_idx (status),
    INDEX parent_idx (parent_location_id),
    INDEX manager_idx (manager_id),
    INDEX franchisee_idx (franchisee_id),
    INDEX coordinates_idx (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_location_services`
```sql
CREATE TABLE bkx_location_services (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    is_available TINYINT(1) DEFAULT 1,
    price_override DECIMAL(10,2),
    duration_override INT,
    capacity_override INT,
    custom_settings LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY unique_location_service (location_id, service_id),
    INDEX location_idx (location_id),
    INDEX service_idx (service_id),
    INDEX available_idx (is_available),
    FOREIGN KEY (location_id) REFERENCES bkx_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_location_staff`
```sql
CREATE TABLE bkx_location_staff (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id BIGINT(20) UNSIGNED NOT NULL,
    staff_id BIGINT(20) UNSIGNED NOT NULL,
    is_primary_location TINYINT(1) DEFAULT 0,
    available_days VARCHAR(50),
    commission_percentage DECIMAL(5,2),
    travel_time_to_location INT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY unique_location_staff (location_id, staff_id),
    INDEX location_idx (location_id),
    INDEX staff_idx (staff_id),
    INDEX primary_idx (is_primary_location),
    FOREIGN KEY (location_id) REFERENCES bkx_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_location_inventory`
```sql
CREATE TABLE bkx_location_inventory (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id BIGINT(20) UNSIGNED NOT NULL,
    product_id BIGINT(20) UNSIGNED NOT NULL,
    quantity_available INT DEFAULT 0,
    quantity_reserved INT DEFAULT 0,
    reorder_level INT,
    reorder_quantity INT,
    last_restocked_at DATETIME,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY unique_location_product (location_id, product_id),
    INDEX location_idx (location_id),
    INDEX product_idx (product_id),
    FOREIGN KEY (location_id) REFERENCES bkx_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_location_bookings`
```sql
CREATE TABLE bkx_location_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL UNIQUE,
    location_id BIGINT(20) UNSIGNED NOT NULL,
    original_location_id BIGINT(20) UNSIGNED,
    booking_source VARCHAR(50),
    transferred_from_location BIGINT(20) UNSIGNED,
    transfer_reason TEXT,
    created_at DATETIME NOT NULL,
    INDEX booking_idx (booking_id),
    INDEX location_idx (location_id),
    INDEX original_location_idx (original_location_id),
    FOREIGN KEY (location_id) REFERENCES bkx_locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_franchisees`
```sql
CREATE TABLE bkx_franchisees (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL UNIQUE,
    franchisee_code VARCHAR(50) UNIQUE,
    company_name VARCHAR(200),
    contact_name VARCHAR(200) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(20),
    royalty_percentage DECIMAL(5,2) NOT NULL,
    territory LONGTEXT,
    start_date DATE NOT NULL,
    contract_end_date DATE,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    payment_terms TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX user_idx (user_id),
    INDEX code_idx (franchisee_code),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_location_analytics`
```sql
CREATE TABLE bkx_location_analytics (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    location_id BIGINT(20) UNSIGNED NOT NULL,
    total_bookings INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0,
    total_customers INT DEFAULT 0,
    new_customers INT DEFAULT 0,
    avg_booking_value DECIMAL(10,2),
    occupancy_rate DECIMAL(5,2),
    cancellation_rate DECIMAL(5,2),
    customer_satisfaction DECIMAL(3,2),
    updated_at DATETIME NOT NULL,
    UNIQUE KEY unique_location_date (date, location_id),
    INDEX date_idx (date),
    INDEX location_idx (location_id),
    FOREIGN KEY (location_id) REFERENCES bkx_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_location_resources`
```sql
CREATE TABLE bkx_location_resources (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id BIGINT(20) UNSIGNED NOT NULL,
    resource_type VARCHAR(50) NOT NULL,
    resource_id BIGINT(20) UNSIGNED NOT NULL,
    is_shared TINYINT(1) DEFAULT 0,
    sharing_locations TEXT,
    allocation_rules LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX location_idx (location_id),
    INDEX resource_idx (resource_type, resource_id),
    INDEX shared_idx (is_shared),
    FOREIGN KEY (location_id) REFERENCES bkx_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    // General Settings
    'enable_multi_location' => true,
    'primary_location_id' => 1,
    'default_location_assignment' => 'auto',
    'show_all_locations' => true,

    // Location Discovery
    'enable_geolocation' => true,
    'auto_detect_location' => true,
    'default_radius_km' => 25,
    'max_locations_to_show' => 10,
    'sort_by_distance' => true,

    // Cross-Location Features
    'allow_cross_location_booking' => true,
    'allow_booking_transfer' => true,
    'transfer_requires_approval' => false,
    'suggest_alternative_locations' => true,
    'max_alternative_suggestions' => 3,

    // Resource Sharing
    'enable_resource_sharing' => true,
    'share_staff' => true,
    'share_equipment' => false,
    'share_inventory' => false,
    'resource_allocation_method' => 'first_come',

    // Pricing
    'location_specific_pricing' => true,
    'show_price_range' => true,
    'currency_per_location' => true,
    'tax_rate_per_location' => true,

    // Franchise Settings
    'enable_franchise_mode' => false,
    'default_royalty_percentage' => 8,
    'royalty_calculation' => 'gross_revenue',
    'payment_frequency' => 'monthly',
    'franchise_portal_enabled' => true,

    // Branding
    'allow_location_branding' => true,
    'global_branding_elements' => ['logo', 'colors'],
    'location_custom_branding' => ['header', 'footer'],

    // Analytics
    'centralized_analytics' => true,
    'location_comparison_enabled' => true,
    'benchmark_locations' => true,
    'auto_generate_reports' => true,
    'report_frequency' => 'weekly',

    // Notifications
    'notify_location_managers' => true,
    'notify_franchisees' => true,
    'escalation_rules' => true,

    // Display
    'show_location_selector' => true,
    'location_selector_type' => 'dropdown',
    'show_map_view' => true,
    'map_provider' => 'google',
    'google_maps_api_key' => '',
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Location Selector**
   - Dropdown list of locations
   - Map view with pins
   - Distance from current location
   - Filter by service availability
   - Search by city/zip code
   - Favorite locations

2. **Location Finder Map**
   - Interactive map
   - Location markers
   - Info windows with details
   - Directions button
   - Filter by distance
   - Service availability overlay

3. **Multi-Location Calendar**
   - Tabbed view per location
   - Color-coded by location
   - Filter by location
   - Cross-location availability
   - Quick location switch

4. **Location Details Page**
   - Location information
   - Operating hours
   - Services offered
   - Staff at location
   - Customer reviews
   - Book at this location button
   - Directions/map

### Backend Components

1. **Centralized Dashboard**
   - Network overview
   - Location performance cards
   - Key metrics aggregation
   - Alert panel
   - Quick actions
   - Location switcher

2. **Location Management**
   - Locations list table
   - Add new location
   - Edit location details
   - Location settings
   - Status toggle
   - Delete/archive

3. **Location Configuration**
   - General settings
   - Operating hours
   - Service assignment
   - Staff assignment
   - Pricing overrides
   - Payment methods
   - Email templates

4. **Cross-Location Reports**
   - Revenue by location
   - Performance comparison
   - Staff productivity
   - Service popularity
   - Customer distribution
   - Export multi-location data

5. **Franchise Portal**
   - Franchisee dashboard
   - Location performance
   - Royalty statements
   - Training resources
   - Support tickets
   - Compliance checklist

6. **Resource Sharing Manager**
   - Shared resources list
   - Allocation rules
   - Transfer requests
   - Availability matrix

---

## 8. Security Considerations

### Access Control
- Location-based permissions
- Franchise data isolation
- Manager access limits
- Regional manager oversight
- Customer data privacy across locations

### Data Security
- Encrypt franchisee financial data
- Secure location data
- Audit trail for transfers
- GDPR compliance per location

---

## 9. Testing Strategy

### Unit Tests
```php
- test_location_creation()
- test_distance_calculation()
- test_nearest_location()
- test_resource_sharing()
- test_booking_transfer()
- test_royalty_calculation()
```

### Integration Tests
```php
- test_cross_location_booking()
- test_multi_location_availability()
- test_franchisee_report_generation()
- test_inventory_transfer()
```

---

## 10. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Location management
- [ ] API endpoints

### Phase 2: Geolocation (Week 3)
- [ ] Map integration
- [ ] Distance calculation
- [ ] Location finder

### Phase 3: Multi-Location Booking (Week 4)
- [ ] Location selector
- [ ] Cross-location availability
- [ ] Booking transfer

### Phase 4: Resource Sharing (Week 5)
- [ ] Resource allocation
- [ ] Staff multi-location
- [ ] Inventory sync

### Phase 5: Configuration (Week 6)
- [ ] Location settings
- [ ] Pricing overrides
- [ ] Branding customization

### Phase 6: Franchise Features (Week 7)
- [ ] Franchisee portal
- [ ] Royalty tracking
- [ ] Territory management

### Phase 7: Analytics (Week 8)
- [ ] Location analytics
- [ ] Comparison reports
- [ ] Network dashboard

### Phase 8: Testing & Launch (Week 9-10)
- [ ] Testing
- [ ] Documentation
- [ ] Release

**Total Estimated Timeline:** 10 weeks (2.5 months)

---

## 11. Success Metrics

### Technical Metrics
- Location lookup time < 500ms
- Map load time < 2 seconds
- Cross-location query < 1 second

### Business Metrics
- Multi-location booking rate > 15%
- Location utilization improvement > 25%
- Customer retention across locations > 30%
- Franchise satisfaction > 4.5/5

---

## 12. Future Enhancements

### Version 2.0 Roadmap
- [ ] AI-powered location recommendations
- [ ] Dynamic resource optimization
- [ ] Predictive staffing allocation
- [ ] Advanced territory management
- [ ] Mobile geofencing
- [ ] Voice-activated location search

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
