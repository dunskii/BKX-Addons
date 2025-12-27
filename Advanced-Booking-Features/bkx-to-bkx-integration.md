# BookingX to BookingX Integration Add-on - Development Documentation

## 1. Overview

**Add-on Name:** BookingX to BookingX Integration
**Price:** $199
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Enterprise-level multi-site integration allowing multiple BookingX installations to share resources, staff, inventory, customers, and bookings across locations. Create a unified booking ecosystem for franchises, multi-location businesses, or partnership networks with centralized management and distributed operations.

### Value Proposition
- Unified resource management across locations
- Shared staff scheduling across sites
- Centralized customer database
- Cross-location booking capabilities
- Consolidated reporting and analytics
- Inventory synchronization
- Franchise management features
- Load balancing for high-traffic
- Partner network creation
- Revenue sharing and tracking

---

## 2. Features & Requirements

### Core Features
1. **Multi-Site Connection Management**
   - Connect multiple BookingX installations
   - Hub-and-spoke architecture
   - Peer-to-peer network option
   - Secure API authentication
   - Connection health monitoring
   - Automatic failover
   - Version compatibility checking
   - Connection status dashboard

2. **Shared Resource Management**
   - Staff sharing across locations
   - Equipment/room sharing
   - Service catalog synchronization
   - Category alignment
   - Pricing synchronization
   - Availability pooling
   - Resource allocation rules
   - Conflict resolution

3. **Unified Customer Database**
   - Single customer record across sites
   - Automatic profile synchronization
   - Merge duplicate profiles
   - Unified booking history
   - Loyalty points pooling
   - Shared payment methods
   - Privacy-compliant data sharing
   - Customer preference sync

4. **Cross-Location Bookings**
   - Book services at any location
   - Automatic location selection
   - Location-based pricing
   - Transfer bookings between sites
   - Multi-location packages
   - Centralized booking calendar
   - Smart location recommendations
   - Geolocation-based suggestions

5. **Inventory Synchronization**
   - Real-time inventory updates
   - Stock level synchronization
   - Inter-location transfers
   - Low stock alerts
   - Automatic reordering
   - Inventory reservation
   - Expiration tracking
   - Audit trail

6. **Centralized Administration**
   - Network-wide settings
   - Global user management
   - Unified reporting dashboard
   - System health monitoring
   - Performance analytics
   - Security audit logs
   - Bulk configuration updates
   - Role-based access control

7. **Revenue Management**
   - Revenue sharing configuration
   - Location-based commission
   - Consolidated billing
   - Multi-location invoicing
   - Revenue allocation rules
   - Financial reporting
   - Tax handling per location
   - Payment reconciliation

8. **Data Synchronization**
   - Bi-directional sync
   - Conflict resolution
   - Delta synchronization
   - Scheduled sync jobs
   - Manual sync triggers
   - Sync history and logs
   - Rollback capabilities
   - Bandwidth optimization

### User Roles & Permissions
- **Network Admin:** Full control across all sites
- **Location Manager:** Manage individual location
- **Hub Administrator:** Central management of network
- **Staff (Shared):** Work across multiple locations
- **Customer:** Book across all network locations
- **Franchisee:** Limited admin for franchised location

---

## 3. Technical Specifications

### Technology Stack
- **Communication:** REST API + WebSockets
- **Authentication:** JWT tokens with rotation
- **Encryption:** SSL/TLS + payload encryption
- **Sync Engine:** Custom delta sync algorithm
- **Queue:** Redis or database-based queue
- **Caching:** Redis/Memcached for performance
- **Load Balancing:** Optional nginx/HAProxy
- **Monitoring:** Custom health check system

### Dependencies
- BookingX Core 2.0+
- WordPress REST API
- PHP cURL extension
- OpenSSL extension
- Redis (recommended) or Memcached
- Action Scheduler
- Database with InnoDB support

### API Integration Points
```php
// Hub-to-Node Communication
POST   /wp-json/bookingx-network/v1/handshake
POST   /wp-json/bookingx-network/v1/sync
GET    /wp-json/bookingx-network/v1/resources
POST   /wp-json/bookingx-network/v1/bookings
PUT    /wp-json/bookingx-network/v1/customers
GET    /wp-json/bookingx-network/v1/inventory
POST   /wp-json/bookingx-network/v1/staff
GET    /wp-json/bookingx-network/v1/analytics
POST   /wp-json/bookingx-network/v1/sync/conflict-resolve

// Webhooks for Real-time Updates
POST   /wp-json/bookingx-network/v1/webhook/booking-created
POST   /wp-json/bookingx-network/v1/webhook/customer-updated
POST   /wp-json/bookingx-network/v1/webhook/inventory-changed
POST   /wp-json/bookingx-network/v1/webhook/resource-unavailable
```

---

## 4. Architecture & Design

### System Architecture
```
┌──────────────────────────────────────────┐
│         Central Hub (Master)             │
│  - Network Configuration                 │
│  - Centralized Database                  │
│  - Conflict Resolution                   │
│  - Analytics Aggregation                 │
└──────────────┬───────────────────────────┘
               │
    ┌──────────┴──────────┬──────────┬──────────┐
    ▼                     ▼          ▼          ▼
┌─────────┐          ┌─────────┐ ┌─────────┐ ┌─────────┐
│ Node 1  │◄────────►│ Node 2  │ │ Node 3  │ │ Node N  │
│Location │          │Location │ │Location │ │Location │
│   A     │          │    B    │ │    C    │ │   ...   │
└─────────┘          └─────────┘ └─────────┘ └─────────┘
     │                    │          │          │
     └────────────────────┴──────────┴──────────┘
              Peer-to-Peer Sync (Optional)
```

### Class Structure
```php
namespace BookingX\Addons\NetworkIntegration;

class NetworkHub {
    - register_node()
    - authenticate_node()
    - sync_to_nodes()
    - aggregate_data()
    - resolve_conflicts()
    - monitor_health()
}

class NetworkNode {
    - connect_to_hub()
    - send_data_to_hub()
    - receive_updates()
    - process_remote_booking()
    - sync_local_changes()
    - handle_offline_mode()
}

class SyncEngine {
    - calculate_delta()
    - queue_sync_job()
    - process_sync()
    - handle_conflict()
    - rollback_sync()
    - get_sync_status()
}

class ResourceSharing {
    - share_resource()
    - request_resource()
    - check_availability()
    - reserve_resource()
    - release_resource()
    - transfer_resource()
}

class CustomerSync {
    - sync_customer_profile()
    - merge_profiles()
    - resolve_duplicate()
    - update_across_network()
    - get_unified_history()
}

class BookingOrchestrator {
    - create_cross_location_booking()
    - transfer_booking()
    - check_multi_site_availability()
    - suggest_alternative_location()
    - handle_location_routing()
}

class InventorySync {
    - sync_inventory_levels()
    - reserve_inventory()
    - transfer_stock()
    - update_stock_across_network()
    - handle_low_stock()
}

class RevenueManager {
    - calculate_revenue_split()
    - track_location_revenue()
    - generate_consolidated_invoice()
    - allocate_payments()
    - reconcile_transactions()
}

class ConflictResolver {
    - detect_conflict()
    - apply_resolution_strategy()
    - notify_conflict()
    - manual_resolution()
    - log_conflict()
}

class NetworkAnalytics {
    - aggregate_metrics()
    - generate_network_report()
    - compare_locations()
    - track_cross_location_bookings()
    - export_consolidated_data()
}
```

---

## 5. Database Schema

### Table: `bkx_network_nodes`
```sql
CREATE TABLE bkx_network_nodes (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    node_id VARCHAR(64) NOT NULL UNIQUE,
    node_name VARCHAR(200) NOT NULL,
    node_url VARCHAR(500) NOT NULL,
    node_type VARCHAR(20) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    api_secret VARCHAR(255) NOT NULL,
    is_hub TINYINT(1) DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_sync_at DATETIME,
    last_heartbeat_at DATETIME,
    version VARCHAR(20),
    location_data LONGTEXT,
    sync_settings LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX node_id_idx (node_id),
    INDEX status_idx (status),
    INDEX type_idx (node_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_network_sync_queue`
```sql
CREATE TABLE bkx_network_sync_queue (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sync_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT(20) UNSIGNED NOT NULL,
    source_node_id VARCHAR(64) NOT NULL,
    target_node_ids TEXT,
    operation VARCHAR(20) NOT NULL,
    payload LONGTEXT NOT NULL,
    priority INT DEFAULT 5,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    retry_count INT DEFAULT 0,
    scheduled_at DATETIME NOT NULL,
    processed_at DATETIME,
    error_message TEXT,
    created_at DATETIME NOT NULL,
    INDEX sync_type_idx (sync_type),
    INDEX status_idx (status),
    INDEX scheduled_at_idx (scheduled_at),
    INDEX source_node_idx (source_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_network_shared_resources`
```sql
CREATE TABLE bkx_network_shared_resources (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    global_resource_id VARCHAR(64) NOT NULL UNIQUE,
    resource_type VARCHAR(50) NOT NULL,
    local_resource_id BIGINT(20) UNSIGNED NOT NULL,
    owning_node_id VARCHAR(64) NOT NULL,
    sharing_nodes TEXT,
    sharing_rules LONGTEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX global_id_idx (global_resource_id),
    INDEX resource_type_idx (resource_type),
    INDEX owning_node_idx (owning_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_network_customers`
```sql
CREATE TABLE bkx_network_customers (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    global_customer_id VARCHAR(64) NOT NULL UNIQUE,
    primary_node_id VARCHAR(64) NOT NULL,
    customer_data LONGTEXT NOT NULL,
    location_specific_data LONGTEXT,
    last_sync_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX global_customer_idx (global_customer_id),
    INDEX primary_node_idx (primary_node_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_network_customer_mapping`
```sql
CREATE TABLE bkx_network_customer_mapping (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    global_customer_id VARCHAR(64) NOT NULL,
    node_id VARCHAR(64) NOT NULL,
    local_customer_id BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY unique_mapping (global_customer_id, node_id),
    INDEX global_customer_idx (global_customer_id),
    INDEX node_id_idx (node_id),
    INDEX local_customer_idx (local_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_network_bookings`
```sql
CREATE TABLE bkx_network_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    global_booking_id VARCHAR(64) NOT NULL UNIQUE,
    originating_node_id VARCHAR(64) NOT NULL,
    service_node_id VARCHAR(64) NOT NULL,
    global_customer_id VARCHAR(64) NOT NULL,
    booking_data LONGTEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    revenue_split LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX global_booking_idx (global_booking_id),
    INDEX originating_node_idx (originating_node_id),
    INDEX service_node_idx (service_node_id),
    INDEX customer_idx (global_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_network_conflicts`
```sql
CREATE TABLE bkx_network_conflicts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conflict_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(64) NOT NULL,
    source_node_id VARCHAR(64) NOT NULL,
    target_node_id VARCHAR(64) NOT NULL,
    source_data LONGTEXT NOT NULL,
    target_data LONGTEXT NOT NULL,
    resolution_strategy VARCHAR(50),
    resolved_data LONGTEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    resolved_by BIGINT(20) UNSIGNED,
    resolved_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX conflict_type_idx (conflict_type),
    INDEX status_idx (status),
    INDEX entity_idx (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_network_revenue_tracking`
```sql
CREATE TABLE bkx_network_revenue_tracking (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    global_booking_id VARCHAR(64) NOT NULL,
    total_revenue DECIMAL(10,2) NOT NULL,
    node_id VARCHAR(64) NOT NULL,
    node_share DECIMAL(10,2) NOT NULL,
    share_percentage DECIMAL(5,2) NOT NULL,
    commission_amount DECIMAL(10,2),
    payment_status VARCHAR(20) DEFAULT 'pending',
    paid_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX booking_idx (global_booking_id),
    INDEX node_idx (node_id),
    INDEX payment_status_idx (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_network_sync_log`
```sql
CREATE TABLE bkx_network_sync_log (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sync_job_id BIGINT(20) UNSIGNED,
    node_id VARCHAR(64) NOT NULL,
    sync_type VARCHAR(50) NOT NULL,
    operation VARCHAR(20) NOT NULL,
    entity_count INT DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    duration_ms INT,
    error_details TEXT,
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    INDEX node_id_idx (node_id),
    INDEX sync_type_idx (sync_type),
    INDEX started_at_idx (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    // Network Configuration
    'network_mode' => 'hub_spoke|peer_to_peer|hybrid',
    'network_name' => 'BookingX Network',
    'hub_url' => '',
    'is_hub' => false,

    // Connection Settings
    'api_authentication' => 'jwt',
    'api_key_rotation_days' => 30,
    'connection_timeout_seconds' => 30,
    'max_retry_attempts' => 3,
    'enable_ssl_verification' => true,

    // Sync Settings
    'sync_frequency' => 'realtime|hourly|daily',
    'sync_batch_size' => 100,
    'enable_delta_sync' => true,
    'sync_priority_order' => ['customers', 'bookings', 'resources', 'inventory'],
    'offline_mode_enabled' => true,
    'sync_conflict_resolution' => 'hub_wins|node_wins|manual|newest',

    // Resource Sharing
    'enable_resource_sharing' => true,
    'share_staff' => true,
    'share_equipment' => true,
    'share_services' => true,
    'auto_sync_availability' => true,

    // Customer Data
    'enable_unified_customers' => true,
    'auto_merge_duplicates' => true,
    'customer_match_criteria' => ['email', 'phone'],
    'sync_customer_preferences' => true,
    'sync_loyalty_points' => true,

    // Cross-Location Booking
    'enable_cross_location_booking' => true,
    'auto_suggest_locations' => true,
    'max_distance_km' => 50,
    'enable_booking_transfer' => true,
    'transfer_approval_required' => true,

    // Revenue Management
    'enable_revenue_sharing' => true,
    'default_revenue_split' => [
        'originating_location' => 20,
        'service_location' => 80
    ],
    'commission_calculation' => 'percentage|fixed',
    'auto_reconciliation' => false,

    // Performance
    'enable_caching' => true,
    'cache_ttl_seconds' => 300,
    'use_redis' => true,
    'redis_host' => 'localhost',
    'redis_port' => 6379,
    'enable_compression' => true,

    // Monitoring
    'enable_health_checks' => true,
    'health_check_interval_minutes' => 5,
    'alert_on_sync_failure' => true,
    'alert_email' => '',
    'enable_detailed_logging' => true,
    'log_retention_days' => 30,

    // Security
    'require_two_factor' => true,
    'ip_whitelist' => [],
    'enable_rate_limiting' => true,
    'rate_limit_per_minute' => 60,
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Network Dashboard (Hub)**
   - Connected nodes map view
   - Node status indicators (online/offline/syncing)
   - Network health overview
   - Recent sync activity
   - Pending conflicts
   - Performance metrics
   - Quick actions panel

2. **Node Management**
   - Add new node wizard
   - Node configuration
   - Connection testing
   - Sync settings per node
   - Disconnect/reconnect options
   - Node details modal

3. **Cross-Location Booking Interface**
   - Location selector
   - Multi-location availability calendar
   - Distance/travel time indicator
   - Location comparison
   - Book at alternative location option

4. **Resource Sharing Manager**
   - Shared resources list
   - Sharing rules configuration
   - Availability across locations
   - Resource allocation chart
   - Transfer resource interface

5. **Customer Network View**
   - Unified customer profile
   - Booking history across all locations
   - Location preference indicators
   - Loyalty points total
   - Profile merge interface

### Backend Components

1. **Network Configuration**
   - Hub/node setup wizard
   - API key generation
   - Connection settings
   - Sync configuration
   - Security settings

2. **Sync Management**
   - Sync queue monitor
   - Manual sync triggers
   - Sync history log
   - Conflict resolution interface
   - Rollback functionality

3. **Revenue Dashboard**
   - Revenue by location
   - Commission tracking
   - Payment reconciliation
   - Revenue split configuration
   - Financial reports

4. **Analytics & Reporting**
   - Network-wide analytics
   - Location comparison reports
   - Cross-location booking analytics
   - Customer movement tracking
   - Performance benchmarking

5. **Conflict Resolution Center**
   - Pending conflicts list
   - Conflict details view
   - Resolution options
   - Bulk resolution
   - Conflict history

---

## 8. Security Considerations

### Network Security
- **End-to-End Encryption:** All data in transit
- **JWT Authentication:** Rotating tokens
- **API Key Management:** Secure storage and rotation
- **IP Whitelisting:** Restrict access to known IPs
- **Rate Limiting:** Prevent abuse
- **SSL/TLS Required:** Enforce HTTPS

### Data Security
- **Data Isolation:** Logical separation per location
- **Audit Trail:** All cross-location operations logged
- **Access Control:** Role-based across network
- **Data Validation:** Sanitize all synced data
- **Conflict Detection:** Prevent data corruption

### Compliance
- **GDPR:** Data residency and transfer agreements
- **Multi-jurisdictional:** Respect location-specific regulations
- **Data Sovereignty:** Control data storage location
- **Privacy:** Customer consent for data sharing

---

## 9. Testing Strategy

### Unit Tests
```php
- test_node_registration()
- test_sync_delta_calculation()
- test_conflict_detection()
- test_resource_reservation()
- test_customer_merge()
- test_revenue_split_calculation()
- test_jwt_authentication()
```

### Integration Tests
```php
- test_full_sync_workflow()
- test_cross_location_booking()
- test_real_time_sync()
- test_offline_mode_recovery()
- test_conflict_resolution()
- test_failover_mechanism()
```

### Test Scenarios
1. **Initial Setup:** Connect two new nodes to hub
2. **Customer Sync:** Create customer at Node A, verify at Node B
3. **Cross-Location Booking:** Book from Node A for service at Node B
4. **Resource Sharing:** Share staff member across 3 locations
5. **Conflict Resolution:** Simultaneous updates at 2 nodes
6. **Offline Recovery:** Node offline, then resync
7. **Load Test:** 1000 concurrent cross-location bookings
8. **Failover:** Hub goes down, peer-to-peer takes over

---

## 10. Performance Optimization

### Sync Optimization
- Delta sync (only changes)
- Batch processing
- Compression of payloads
- Async processing
- Priority queue for critical data

### Caching
- Redis for frequently accessed data
- API response caching
- Resource availability caching
- Customer data caching

### Database
- Partition large tables by node
- Index on global IDs
- Archive old sync logs
- Query optimization for cross-location searches

---

## 11. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Core network classes
- [ ] API authentication

### Phase 2: Hub-Spoke Architecture (Week 3-4)
- [ ] Hub implementation
- [ ] Node registration
- [ ] Basic sync engine

### Phase 3: Resource Sharing (Week 5-6)
- [ ] Share resources
- [ ] Availability sync
- [ ] Reservation system

### Phase 4: Customer Unification (Week 7-8)
- [ ] Customer sync
- [ ] Profile merging
- [ ] Unified history

### Phase 5: Cross-Location Booking (Week 9-10)
- [ ] Booking orchestration
- [ ] Location routing
- [ ] Multi-site availability

### Phase 6: Revenue Management (Week 11)
- [ ] Revenue tracking
- [ ] Split calculation
- [ ] Reconciliation

### Phase 7: Conflict Resolution (Week 12)
- [ ] Conflict detection
- [ ] Resolution engine
- [ ] Manual resolution UI

### Phase 8: Analytics & Monitoring (Week 13)
- [ ] Network analytics
- [ ] Health monitoring
- [ ] Reporting

### Phase 9: Testing & Launch (Week 14-16)
- [ ] Comprehensive testing
- [ ] Documentation
- [ ] Beta testing
- [ ] Production release

**Total Estimated Timeline:** 16 weeks (4 months)

---

## 12. Success Metrics

### Technical Metrics
- Sync latency < 5 seconds (realtime)
- API response time < 500ms
- Sync success rate > 99.5%
- Conflict rate < 1%
- Uptime > 99.9%

### Business Metrics
- Cross-location bookings > 15%
- Customer retention across network > 30%
- Revenue increase per location > 25%
- Operational cost reduction > 20%

---

## 13. Future Enhancements

### Version 2.0 Roadmap
- [ ] Blockchain-based sync verification
- [ ] AI-powered conflict resolution
- [ ] Global load balancing
- [ ] Multi-currency support
- [ ] Advanced franchise management
- [ ] Predictive resource allocation
- [ ] GraphQL API support

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
