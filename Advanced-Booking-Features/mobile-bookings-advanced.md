# Mobile Bookings Advanced Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Mobile Bookings Advanced
**Price:** $99
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Advanced mobile booking features with Google Maps integration, travel time calculation, distance-based scheduling, and location-aware booking optimization. Perfect for mobile service providers, field services, home services, and on-location businesses.

### Value Proposition
- Automatic travel time calculation using Google Maps
- Distance-based scheduling and routing
- Location-aware booking optimization
- Service area management with geo-fencing
- Real-time traffic consideration
- Route optimization for providers
- Mobile-first booking interface
- GPS check-in verification

---

## 2. Features & Requirements

### Core Features
1. **Google Maps Integration**
   - Distance calculation between locations
   - Travel time estimation
   - Real-time traffic data
   - Multiple route options
   - Map visualization
   - Address autocomplete
   - Geolocation support

2. **Travel Time Scheduling**
   - Automatic travel time buffer
   - Dynamic schedule adjustment
   - Traffic-aware scheduling
   - Provider location tracking
   - Multi-stop optimization
   - Return trip calculation

3. **Distance-Based Pricing**
   - Calculate service fees by distance
   - Tiered distance pricing
   - Travel surcharges
   - Minimum distance requirements
   - Maximum service radius
   - Zone-based pricing

4. **Service Area Management**
   - Define service areas (zip codes, radius, polygons)
   - Geo-fencing
   - Multi-location support
   - Coverage map display
   - Area-specific pricing
   - Service availability by area

5. **Route Optimization**
   - Optimize provider daily routes
   - Minimize travel time
   - Cluster nearby appointments
   - Suggest optimal order
   - Multi-destination routing
   - Export to navigation apps

6. **Mobile Location Features**
   - GPS check-in verification
   - Location-based services
   - Nearby provider matching
   - Customer location capture
   - Provider real-time tracking
   - Arrival time estimation

### User Roles & Permissions
- **Admin:** Configure maps, service areas, pricing
- **Manager:** View routes, optimize schedules
- **Provider:** View routes, navigation, check-in
- **Customer:** Enter location, view on map

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP 7.4+, WordPress REST API
- **Maps API:** Google Maps Platform (Maps, Directions, Distance Matrix, Geocoding)
- **Frontend:** Google Maps JavaScript API
- **Geolocation:** HTML5 Geolocation API
- **Database:** MySQL 5.7+ with spatial data support

### Dependencies
- BookingX Core 2.0+
- Google Maps API key (required)
- PHP cURL extension
- MySQL with spatial extensions
- HTTPS (required for geolocation)

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/location/calculate-distance
POST   /wp-json/bookingx/v1/location/calculate-travel-time
GET    /wp-json/bookingx/v1/location/check-service-area
POST   /wp-json/bookingx/v1/location/geocode

GET    /wp-json/bookingx/v1/provider-routes/{provider_id}
POST   /wp-json/bookingx/v1/provider-routes/optimize
GET    /wp-json/bookingx/v1/provider-routes/{provider_id}/daily

POST   /wp-json/bookingx/v1/mobile/check-in
GET    /wp-json/bookingx/v1/mobile/nearby-providers
POST   /wp-json/bookingx/v1/mobile/update-location

GET    /wp-json/bookingx/v1/service-areas
POST   /wp-json/bookingx/v1/service-areas
PUT    /wp-json/bookingx/v1/service-areas/{id}
DELETE /wp-json/bookingx/v1/service-areas/{id}

// Google Maps API Calls (server-side)
- Distance Matrix API
- Directions API
- Geocoding API
- Places API
- Time Zone API
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│  BookingX Core      │
│  - Scheduling       │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────┐
│  Mobile Location Module     │
│  - Maps Integration         │
│  - Distance Calculator      │
│  - Route Optimizer          │
└──────────┬──────────────────┘
           │
           ├──────────┬──────────┬──────────┐
           ▼          ▼          ▼          ▼
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│  Google  │ │ Service  │ │  Travel  │ │   GPS    │
│   Maps   │ │   Area   │ │   Time   │ │ Tracking │
└──────────┘ └──────────┘ └──────────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\MobileBookings;

class GoogleMapsIntegration {
    - calculate_distance()
    - calculate_travel_time()
    - get_directions()
    - geocode_address()
    - reverse_geocode()
    - get_place_details()
}

class DistanceCalculator {
    - calculate_distance_between_points()
    - calculate_travel_duration()
    - get_traffic_data()
    - calculate_with_waypoints()
}

class TravelTimeScheduler {
    - add_travel_buffer()
    - calculate_schedule_with_travel()
    - adjust_for_traffic()
    - validate_schedule_feasibility()
    - get_next_available_slot()
}

class RouteOptimizer {
    - optimize_daily_route()
    - calculate_optimal_order()
    - minimize_travel_time()
    - cluster_nearby_bookings()
    - get_route_summary()
}

class ServiceAreaManager {
    - create_service_area()
    - check_address_in_area()
    - get_areas_by_location()
    - calculate_coverage()
    - get_area_boundaries()
}

class DistancePricingEngine {
    - calculate_distance_fee()
    - apply_tiered_pricing()
    - calculate_zone_pricing()
    - get_travel_surcharge()
}

class LocationTracker {
    - update_provider_location()
    - get_provider_location()
    - calculate_eta()
    - track_movement()
    - verify_checkin_location()
}

class MobileBookingManager {
    - create_mobile_booking()
    - validate_location()
    - calculate_pricing_with_distance()
    - send_location_to_customer()
    - get_nearby_providers()
}
```

---

## 5. Database Schema

### Table: `bkx_locations`
```sql
CREATE TABLE bkx_locations (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED,
    customer_id BIGINT(20) UNSIGNED,
    provider_id BIGINT(20) UNSIGNED,
    location_type ENUM('customer', 'provider', 'service', 'waypoint') NOT NULL,
    address VARCHAR(500) NOT NULL,
    address_line_2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    zip_code VARCHAR(20),
    country VARCHAR(100),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    location_point POINT,
    formatted_address TEXT,
    place_id VARCHAR(255) COMMENT 'Google Maps Place ID',
    location_notes TEXT,
    is_verified TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX customer_id_idx (customer_id),
    INDEX provider_id_idx (provider_id),
    SPATIAL INDEX location_point_idx (location_point)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_travel_times`
```sql
CREATE TABLE bkx_travel_times (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    provider_id BIGINT(20) UNSIGNED NOT NULL,
    from_location_id BIGINT(20) UNSIGNED NOT NULL,
    to_location_id BIGINT(20) UNSIGNED NOT NULL,
    distance_km DECIMAL(10, 2) NOT NULL,
    distance_miles DECIMAL(10, 2) NOT NULL,
    duration_minutes INT NOT NULL,
    duration_in_traffic_minutes INT,
    route_polyline TEXT,
    calculated_at DATETIME NOT NULL,
    traffic_model VARCHAR(50),
    departure_time DATETIME,
    created_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX provider_id_idx (provider_id),
    INDEX from_location_idx (from_location_id),
    INDEX to_location_idx (to_location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_service_areas`
```sql
CREATE TABLE bkx_service_areas (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    service_id BIGINT(20) UNSIGNED,
    provider_id BIGINT(20) UNSIGNED,
    area_type ENUM('radius', 'zip_codes', 'polygon', 'city', 'state') NOT NULL,
    center_latitude DECIMAL(10, 8),
    center_longitude DECIMAL(11, 8),
    radius_km DECIMAL(10, 2),
    radius_miles DECIMAL(10, 2),
    zip_codes TEXT COMMENT 'Comma-separated zip codes',
    polygon_coordinates LONGTEXT COMMENT 'JSON array of lat/lng coordinates',
    cities TEXT COMMENT 'Comma-separated cities',
    states TEXT COMMENT 'Comma-separated states',
    distance_pricing_enabled TINYINT(1) DEFAULT 0,
    base_travel_fee DECIMAL(10, 2) DEFAULT 0,
    per_km_rate DECIMAL(10, 2) DEFAULT 0,
    per_mile_rate DECIMAL(10, 2) DEFAULT 0,
    min_distance DECIMAL(10, 2) DEFAULT 0,
    max_distance DECIMAL(10, 2),
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX service_id_idx (service_id),
    INDEX provider_id_idx (provider_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_provider_routes`
```sql
CREATE TABLE bkx_provider_routes (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT(20) UNSIGNED NOT NULL,
    route_date DATE NOT NULL,
    start_location_id BIGINT(20) UNSIGNED,
    end_location_id BIGINT(20) UNSIGNED,
    total_distance_km DECIMAL(10, 2),
    total_distance_miles DECIMAL(10, 2),
    total_travel_time_minutes INT,
    total_bookings INT,
    route_order LONGTEXT COMMENT 'JSON array of booking IDs in order',
    is_optimized TINYINT(1) DEFAULT 0,
    optimized_at DATETIME,
    route_polyline TEXT,
    status ENUM('planned', 'in_progress', 'completed') NOT NULL DEFAULT 'planned',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX provider_id_idx (provider_id),
    INDEX route_date_idx (route_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_gps_checkins`
```sql
CREATE TABLE bkx_gps_checkins (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    provider_id BIGINT(20) UNSIGNED NOT NULL,
    checkin_type ENUM('arrival', 'departure', 'waypoint') NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy DECIMAL(10, 2) COMMENT 'GPS accuracy in meters',
    distance_from_location DECIMAL(10, 2) COMMENT 'Distance from booking location',
    is_verified TINYINT(1) DEFAULT 0,
    verification_radius DECIMAL(10, 2) DEFAULT 100,
    checkin_time DATETIME NOT NULL,
    device_info TEXT,
    INDEX booking_id_idx (booking_id),
    INDEX provider_id_idx (provider_id),
    INDEX checkin_time_idx (checkin_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    'google_maps_settings' => [
        'api_key' => '',
        'enable_maps' => true,
        'default_map_zoom' => 12,
        'default_center_lat' => 40.7128,
        'default_center_lng' => -74.0060,
        'map_style' => 'roadmap', // roadmap|satellite|hybrid|terrain
        'enable_traffic_layer' => true,
        'enable_directions' => true,
    ],

    'distance_calculation' => [
        'unit' => 'miles', // miles|kilometers
        'calculation_method' => 'google_maps', // google_maps|haversine
        'include_traffic' => true,
        'traffic_model' => 'best_guess', // best_guess|pessimistic|optimistic
        'cache_duration_minutes' => 30,
    ],

    'travel_time_settings' => [
        'add_travel_buffer' => true,
        'travel_buffer_percentage' => 20,
        'min_buffer_minutes' => 10,
        'max_buffer_minutes' => 60,
        'round_up_to_minutes' => 5,
        'consider_traffic' => true,
    ],

    'service_area_settings' => [
        'enforce_service_areas' => true,
        'show_coverage_map' => true,
        'allow_outside_area_requests' => false,
        'default_radius_miles' => 25,
        'verify_address' => true,
    ],

    'distance_pricing' => [
        'enable_distance_pricing' => true,
        'base_travel_fee' => 10,
        'per_mile_rate' => 2.50,
        'free_distance_miles' => 5,
        'max_distance_miles' => 50,
        'tiered_pricing' => [
            ['min' => 0, 'max' => 10, 'rate' => 2.00],
            ['min' => 10, 'max' => 25, 'rate' => 1.50],
            ['min' => 25, 'max' => 50, 'rate' => 1.00],
        ],
    ],

    'route_optimization' => [
        'enable_optimization' => true,
        'auto_optimize_daily' => true,
        'optimization_algorithm' => 'nearest_neighbor', // nearest_neighbor|genetic
        'consider_appointment_time_windows' => true,
        'start_from_provider_home' => true,
        'return_to_provider_home' => true,
    ],

    'gps_settings' => [
        'enable_gps_checkin' => true,
        'verification_radius_meters' => 100,
        'require_gps_verification' => false,
        'track_provider_location' => true,
        'location_update_interval_seconds' => 300,
    ],

    'mobile_features' => [
        'enable_nearby_provider_search' => true,
        'search_radius_miles' => 10,
        'show_provider_eta' => true,
        'enable_navigation_export' => true,
        'supported_nav_apps' => ['google_maps', 'waze', 'apple_maps'],
    ],

    'notification_settings' => [
        'notify_customer_on_route' => true,
        'notify_eta_changes' => true,
        'eta_change_threshold_minutes' => 15,
        'send_arrival_notification' => true,
        'arrival_notification_minutes_before' => 10,
    ],
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Address Input with Autocomplete**
   - Google Places autocomplete
   - Current location button
   - Address validation
   - Map preview
   - Service area check

2. **Interactive Map Display**
   - Service area visualization
   - Provider location markers
   - Customer location marker
   - Route display
   - Distance/time overlay

3. **Distance & Time Display**
   - Travel distance shown
   - Estimated travel time
   - Traffic indicator
   - Total price including travel
   - Breakdown of charges

4. **Provider Route View**
   - Daily schedule on map
   - Multiple stops shown
   - Optimized route display
   - Navigation buttons
   - ETA for each stop

5. **Mobile Check-in Interface**
   - GPS check-in button
   - Location verification status
   - Distance from location
   - Photo capture option
   - Notes field

6. **Nearby Providers**
   - Provider list with distances
   - Map view of providers
   - Filter by service
   - Real-time availability
   - Book now buttons

### Backend Components

1. **Service Area Manager**
   - Create/edit areas
   - Visual area definition on map
   - Coverage preview
   - Pricing configuration
   - Area statistics

2. **Route Optimization Dashboard**
   - Daily route overview
   - Optimization controls
   - Route comparison
   - Time/distance savings
   - Export routes

3. **Location Analytics**
   - Service area heat map
   - Distance distribution
   - Travel time analysis
   - Coverage gaps
   - Revenue by area

4. **GPS Check-in Log**
   - Check-in history
   - Verification status
   - Location accuracy
   - Distance from target
   - Map view

5. **Maps Settings**
   - API key configuration
   - Map customization
   - Feature toggles
   - Pricing setup
   - Test connection

---

## 8. Security Considerations

### Data Security
- **API Key Security:** Restrict API key by domain/IP
- **Location Privacy:** Encrypt sensitive location data
- **GPS Data:** Secure transmission and storage
- **SQL Injection:** Prepared statements

### Authorization
- Customers access own locations only
- Providers see assigned routes only
- Admin has full access
- Validate GPS check-in authenticity

### API Security
- Rate limiting on Maps API calls
- Cache results to minimize API usage
- Validate API responses
- Handle API errors gracefully

---

## 9. Testing Strategy

### Unit Tests
```php
- test_distance_calculation()
- test_travel_time_calculation()
- test_service_area_validation()
- test_route_optimization()
- test_distance_pricing()
- test_gps_verification()
- test_geocoding()
```

### Integration Tests
```php
- test_complete_mobile_booking_flow()
- test_route_optimization_with_bookings()
- test_gps_checkin_workflow()
- test_distance_pricing_integration()
```

### Test Scenarios
1. **Distance Booking:** Book with address, calculate distance pricing
2. **Route Optimization:** Optimize provider daily route
3. **Service Area Check:** Validate address in/out of service area
4. **GPS Check-in:** Provider checks in at location
5. **Travel Time Buffer:** Schedule with automatic travel time
6. **Nearby Provider:** Find providers within radius

---

## 10. Error Handling

### Error Messages (User-Facing)
```php
'outside_service_area' => 'This address is outside our service area.',
'invalid_address' => 'Unable to verify address. Please check and try again.',
'distance_too_far' => 'Location exceeds maximum service distance of %d miles.',
'maps_api_error' => 'Unable to calculate distance. Please try again.',
'gps_unavailable' => 'GPS is unavailable. Please enable location services.',
'location_verification_failed' => 'Unable to verify your location.',
```

### Logging
- All Maps API calls
- Distance calculations
- Route optimizations
- GPS check-ins
- Service area validations
- API errors

---

## 11. Performance Optimization

### Caching Strategy
- Cache distance calculations (TTL: 1 hour)
- Cache geocoding results (TTL: 24 hours)
- Cache service area checks (TTL: 30 minutes)
- Cache route data (TTL: 1 day)

### API Optimization
- Batch geocoding requests
- Use Distance Matrix for multiple destinations
- Implement request throttling
- Monitor API quota usage

---

## 12. Development Timeline

**Total Estimated Timeline:** 12 weeks (3 months)

---

## 13. Success Metrics

### Business Metrics
- Mobile booking adoption > 40%
- Route optimization time savings > 20%
- GPS check-in adoption > 60%
- Distance pricing revenue > $10k/month

---

## 14. Dependencies & Requirements

### Required
- BookingX Core 2.0+
- Google Maps API key with billing enabled
- HTTPS (required for geolocation)

### API Requirements
- Maps JavaScript API
- Directions API
- Distance Matrix API
- Geocoding API
- Places API

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
