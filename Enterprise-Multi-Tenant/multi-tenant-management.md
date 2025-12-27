# Multi-Tenant Management Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Multi-Tenant Management
**Price:** $299
**Category:** Enterprise & Multi-Tenant
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Enterprise-grade multi-tenant booking platform enabling SaaS providers, franchises, and large enterprises to manage multiple independent tenants from a single installation. Features complete tenant isolation, centralized control, location-specific settings, white-label capabilities per tenant, and franchise-ready management tools.

### Value Proposition
- **Single Installation, Multiple Tenants:** Reduce infrastructure costs by 70%
- **Complete Tenant Isolation:** Data security and privacy by default
- **Centralized Management:** Oversee all tenants from unified dashboard
- **Scalable Architecture:** Support unlimited tenants with predictable performance
- **Franchise-Ready:** Built-in royalty tracking, territory management
- **SaaS-Enabled:** Subscription billing, usage metering, tenant provisioning
- **Location-Specific Configuration:** Each tenant manages own settings
- **White-Label Capable:** Per-tenant branding and customization
- **Cost Efficiency:** 70% reduction in server costs vs separate installations

---

## 2. Features & Requirements

### Core Features

1. **Tenant Provisioning & Management**
   - Automated tenant creation workflow
   - Tenant onboarding wizard
   - Subdomain/custom domain assignment
   - Tenant activation/deactivation
   - Tenant cloning (duplicate configuration)
   - Bulk tenant operations
   - Tenant migration tools
   - Self-service tenant registration
   - Trial period management
   - Tenant suspension/termination

2. **Tenant Isolation Architecture**
   - Database-level isolation (schema per tenant)
   - File storage isolation
   - Cache isolation per tenant
   - Session isolation
   - User authentication separation
   - No cross-tenant data leakage
   - Separate encryption keys per tenant
   - Isolated backups per tenant
   - Resource quotas per tenant
   - Network-level isolation options

3. **Centralized Control Dashboard**
   - Master admin overview
   - All-tenant metrics aggregation
   - Tenant health monitoring
   - Performance analytics across tenants
   - Usage statistics per tenant
   - Resource utilization tracking
   - Financial overview (all tenants)
   - Alert and notification center
   - Tenant comparison tools
   - Quick tenant switcher

4. **Location-Specific Settings**
   - Independent tenant configuration
   - Per-tenant service catalog
   - Custom pricing per tenant
   - Timezone and locale settings
   - Currency per tenant
   - Payment gateway configuration
   - Tax settings per jurisdiction
   - Business hours by tenant
   - Custom fields per tenant
   - Workflow customization

5. **Franchise Management**
   - Franchisee portal access
   - Royalty fee calculation engine
   - Commission tracking automation
   - Territory management & mapping
   - Franchise agreement tracking
   - Performance benchmarking
   - Training & compliance modules
   - Support ticket system
   - Marketing fund management
   - Franchise reporting suite

6. **Multi-Tenant Billing**
   - Usage-based billing
   - Subscription tier management
   - Metered resource charging
   - Invoice generation per tenant
   - Payment collection automation
   - Revenue share calculations
   - Credit/refund management
   - Billing portal for tenants
   - Payment method management
   - Dunning management

7. **Resource Management**
   - CPU/Memory quotas per tenant
   - Storage limits enforcement
   - Bandwidth monitoring
   - Database connection pooling
   - API rate limiting per tenant
   - Email sending limits
   - User account limits
   - Booking volume limits
   - Concurrent session limits
   - Resource scaling automation

8. **Tenant White-Labeling**
   - Custom branding per tenant
   - Logo and color scheme
   - Email template customization
   - Custom CSS per tenant
   - Booking form theming
   - Receipt/invoice templates
   - Custom domain mapping
   - SSL certificate management
   - Favicon customization
   - Language/translation overrides

9. **Centralized Updates & Maintenance**
   - Single-point updates for all tenants
   - Staged rollout capabilities
   - Tenant-specific feature flags
   - A/B testing framework
   - Database migration orchestration
   - Backup automation (all tenants)
   - Disaster recovery procedures
   - Health check automation
   - Performance monitoring
   - Security patch management

10. **Inter-Tenant Features**
    - Cross-tenant booking (optional)
    - Shared resource pools
    - Network-wide customer database
    - Centralized customer loyalty
    - Consolidated reporting
    - Network marketing campaigns
    - Shared staff across locations
    - Inventory transfers
    - Revenue sharing models
    - Partner integrations

### User Roles & Permissions

- **Super Admin:** Full system access, all tenants management
- **Tenant Admin:** Full control within own tenant
- **Franchisee:** Own tenant + franchise-specific features
- **Regional Manager:** Manage group of tenants
- **Support Staff:** Cross-tenant support access (limited)
- **Tenant Staff:** Standard staff within own tenant
- **Tenant Customer:** Standard customer within tenant

---

## 3. Technical Specifications

### Multi-Tenancy Architecture Strategy

**Chosen Approach:** Hybrid Multi-Tenancy (Database per Tenant + Shared Infrastructure)

```
┌─────────────────────────────────────────────────┐
│         Application Layer (Shared)              │
│  - Single Codebase                              │
│  - Tenant Context Resolution                    │
│  - Routing & Middleware                         │
└─────────────────┬───────────────────────────────┘
                  │
    ┌─────────────┴─────────────┐
    ▼                           ▼
┌───────────────┐       ┌──────────────────┐
│ Shared Tables │       │ Tenant Databases │
│ - Tenants     │       │ Database_Tenant1 │
│ - Users Map   │       │ Database_Tenant2 │
│ - Billing     │       │ Database_Tenant3 │
└───────────────┘       │ Database_TenantN │
                        └──────────────────┘
```

**Why Hybrid Approach:**
- **Data Isolation:** Complete data separation per tenant
- **Performance:** Optimized queries within tenant scope
- **Security:** Breach affects only one tenant
- **Compliance:** Easier GDPR/data residency compliance
- **Scalability:** Can move tenants to separate servers
- **Backup/Restore:** Per-tenant granularity
- **Cost Efficiency:** Shared infrastructure and codebase

### Technology Stack

- **Backend:** PHP 7.4+ with Laravel-style architecture
- **Database:** MySQL 8.0+ with separate schemas per tenant
- **Caching:** Redis with namespace per tenant
- **Queue:** Redis Queue with tenant-aware jobs
- **Storage:** S3-compatible with tenant prefix isolation
- **CDN:** CloudFlare with tenant-specific subdomains
- **Session:** Redis-backed with tenant namespace
- **Search:** Elasticsearch with tenant index isolation
- **Monitoring:** Prometheus + Grafana for metrics
- **Logging:** ELK Stack with tenant tagging

### Dependencies

- BookingX Core 2.0+
- PHP PDO with multiple database support
- Redis 5.0+ for caching and queues
- Composer for dependency management
- Node.js for build tools
- S3-compatible object storage
- SSL certificates (Let's Encrypt or commercial)
- Cron or system scheduler
- Elasticsearch 7.0+ (optional but recommended)

### API Integration Points

```php
// Tenant Management API
POST   /wp-json/bookingx/v1/tenants
GET    /wp-json/bookingx/v1/tenants
GET    /wp-json/bookingx/v1/tenants/{id}
PUT    /wp-json/bookingx/v1/tenants/{id}
DELETE /wp-json/bookingx/v1/tenants/{id}
POST   /wp-json/bookingx/v1/tenants/{id}/activate
POST   /wp-json/bookingx/v1/tenants/{id}/suspend
POST   /wp-json/bookingx/v1/tenants/{id}/clone

// Tenant Configuration API
GET    /wp-json/bookingx/v1/tenants/{id}/config
PUT    /wp-json/bookingx/v1/tenants/{id}/config
POST   /wp-json/bookingx/v1/tenants/{id}/config/reset

// Tenant Billing API
GET    /wp-json/bookingx/v1/tenants/{id}/billing
GET    /wp-json/bookingx/v1/tenants/{id}/usage
POST   /wp-json/bookingx/v1/tenants/{id}/billing/invoice
GET    /wp-json/bookingx/v1/tenants/{id}/billing/history

// Resource Management API
GET    /wp-json/bookingx/v1/tenants/{id}/resources
PUT    /wp-json/bookingx/v1/tenants/{id}/resources/limits
GET    /wp-json/bookingx/v1/tenants/{id}/resources/usage

// Network Analytics API
GET    /wp-json/bookingx/v1/network/analytics
GET    /wp-json/bookingx/v1/network/tenants/metrics
POST   /wp-json/bookingx/v1/network/reports/generate

// Franchise Management API
GET    /wp-json/bookingx/v1/franchisees
POST   /wp-json/bookingx/v1/franchisees
GET    /wp-json/bookingx/v1/franchisees/{id}/royalties
POST   /wp-json/bookingx/v1/franchisees/{id}/royalties/calculate
GET    /wp-json/bookingx/v1/franchisees/{id}/territories
```

---

## 4. Architecture & Design

### System Architecture

```
┌──────────────────────────────────────────────────────────┐
│                    Load Balancer                         │
│                  (Multi-Tenant Router)                   │
└────────────────────────┬─────────────────────────────────┘
                         │
        ┌────────────────┴──────────────────┐
        ▼                                   ▼
┌─────────────────┐              ┌─────────────────┐
│  Web Server 1   │              │  Web Server N   │
│  - Tenant Router│              │  - Tenant Router│
│  - App Layer    │              │  - App Layer    │
└────────┬────────┘              └────────┬────────┘
         │                                │
         └────────────────┬───────────────┘
                          ▼
         ┌────────────────────────────────┐
         │   Tenant Context Resolver      │
         │  - Identify tenant from domain │
         │  - Load tenant configuration   │
         │  - Set database connection     │
         └────────────────┬───────────────┘
                          │
         ┌────────────────┴────────────────┐
         ▼                                 ▼
┌──────────────────┐           ┌─────────────────────┐
│  Master Database │           │  Tenant Databases   │
│  - Tenants       │           │  ┌───────────────┐  │
│  - Billing       │           │  │ Tenant1 DB    │  │
│  - Users         │           │  ├───────────────┤  │
│  - Subscriptions │           │  │ Tenant2 DB    │  │
└──────────────────┘           │  ├───────────────┤  │
                               │  │ TenantN DB    │  │
                               │  └───────────────┘  │
                               └─────────────────────┘
         │
         ▼
┌──────────────────────────────────┐
│      Shared Services Layer       │
│  - Redis Cache (Namespaced)      │
│  - Queue System (Tenant-aware)   │
│  - File Storage (Isolated)       │
│  - Email Service (Branded)       │
│  - Search Engine (Indexed)       │
└──────────────────────────────────┘
```

### Tenant Isolation Patterns

**1. Database Isolation**
```sql
-- Master database stores tenant metadata
-- Each tenant gets dedicated database schema

CREATE DATABASE bkx_master;
USE bkx_master;

CREATE TABLE tenants (
    id BIGINT PRIMARY KEY,
    tenant_key VARCHAR(100) UNIQUE,
    db_name VARCHAR(100) UNIQUE,
    domain VARCHAR(255) UNIQUE,
    status ENUM('active', 'suspended', 'trial'),
    ...
);

-- Tenant-specific databases
CREATE DATABASE bkx_tenant_1;
CREATE DATABASE bkx_tenant_2;
-- Each contains full BookingX schema
```

**2. Connection Management**
```php
class TenantDatabaseManager {
    private static $connections = [];

    public static function connection($tenantId) {
        if (!isset(self::$connections[$tenantId])) {
            $tenant = Tenant::find($tenantId);

            self::$connections[$tenantId] = new PDO(
                "mysql:host={$tenant->db_host};dbname={$tenant->db_name}",
                $tenant->db_user,
                $tenant->db_pass,
                [PDO::ATTR_PERSISTENT => true]
            );
        }

        return self::$connections[$tenantId];
    }
}
```

**3. Cache Isolation**
```php
class TenantCache {
    private $redis;
    private $tenantId;

    public function get($key) {
        $namespaced = "tenant:{$this->tenantId}:{$key}";
        return $this->redis->get($namespaced);
    }

    public function set($key, $value, $ttl = 3600) {
        $namespaced = "tenant:{$this->tenantId}:{$key}";
        return $this->redis->setex($namespaced, $ttl, $value);
    }
}
```

**4. File Storage Isolation**
```php
class TenantStorage {
    public function getPath($tenantId, $filename) {
        // S3: s3://bucket/tenant-{id}/uploads/{filename}
        // Local: /var/www/storage/tenant-{id}/uploads/{filename}
        return "tenant-{$tenantId}/uploads/{$filename}";
    }

    public function upload($tenantId, $file) {
        $path = $this->getPath($tenantId, $file->name);
        return $this->s3->putObject($path, $file->content);
    }
}
```

### Class Structure

```php
namespace BookingX\Addons\MultiTenant;

class TenantManager {
    - create_tenant($data)
    - provision_tenant($tenantId)
    - activate_tenant($tenantId)
    - suspend_tenant($tenantId)
    - delete_tenant($tenantId)
    - clone_tenant($sourceTenantId, $newData)
    - migrate_tenant($tenantId, $targetServer)
    - get_tenant_by_domain($domain)
    - get_tenant_by_key($key)
    - list_tenants($filters)
}

class TenantResolver {
    - resolve_from_request($request)
    - resolve_from_domain($domain)
    - resolve_from_subdomain($subdomain)
    - resolve_from_header($headers)
    - set_current_tenant($tenant)
    - get_current_tenant()
    - is_tenant_context()
}

class TenantDatabaseManager {
    - create_database($tenant)
    - migrate_database($tenant)
    - connection($tenantId)
    - disconnect($tenantId)
    - backup_database($tenantId)
    - restore_database($tenantId, $backupFile)
    - clone_database($sourceTenantId, $targetTenantId)
    - optimize_database($tenantId)
}

class TenantConfigManager {
    - get_config($tenantId, $key)
    - set_config($tenantId, $key, $value)
    - get_all_config($tenantId)
    - reset_config($tenantId)
    - inherit_config($tenantId, $parentTenantId)
    - export_config($tenantId)
    - import_config($tenantId, $configData)
}

class TenantBillingManager {
    - calculate_usage($tenantId, $period)
    - generate_invoice($tenantId)
    - process_payment($tenantId, $amount)
    - apply_credit($tenantId, $amount)
    - handle_subscription($tenantId, $planId)
    - track_metered_usage($tenantId, $metric, $value)
    - calculate_royalty($franchiseeId)
}

class TenantResourceManager {
    - set_quota($tenantId, $resource, $limit)
    - check_quota($tenantId, $resource)
    - track_usage($tenantId, $resource, $amount)
    - get_usage_stats($tenantId)
    - enforce_limits($tenantId)
    - scale_resources($tenantId, $scaleFactor)
    - alert_quota_exceeded($tenantId, $resource)
}

class TenantIsolationMiddleware {
    - identify_tenant($request)
    - validate_tenant_access($tenant)
    - set_tenant_context($tenant)
    - isolate_database($tenant)
    - isolate_cache($tenant)
    - isolate_session($tenant)
    - verify_no_cross_tenant_access()
}

class FranchiseManager {
    - register_franchisee($data)
    - assign_territory($franchiseeId, $territory)
    - calculate_royalties($franchiseeId, $period)
    - track_performance($franchiseeId)
    - manage_compliance($franchiseeId)
    - process_royalty_payment($franchiseeId)
    - generate_franchise_report($franchiseeId)
}

class TenantProvisioningService {
    - validate_tenant_data($data)
    - create_tenant_record($data)
    - provision_database($tenant)
    - provision_storage($tenant)
    - configure_domain($tenant)
    - setup_ssl($tenant)
    - seed_initial_data($tenant)
    - send_welcome_email($tenant)
    - notify_admin($tenant)
}

class TenantMigrationService {
    - export_tenant_data($tenantId)
    - import_tenant_data($tenantId, $data)
    - migrate_to_server($tenantId, $targetServer)
    - verify_migration($tenantId)
    - rollback_migration($tenantId)
}

class NetworkAnalytics {
    - aggregate_metrics($tenants)
    - compare_tenants($tenantIds)
    - calculate_network_stats()
    - generate_network_report($period)
    - track_growth_metrics()
    - predict_resource_needs()
}

class TenantBrandingManager {
    - set_branding($tenantId, $brandingData)
    - get_branding($tenantId)
    - apply_branding($tenantId)
    - validate_branding($brandingData)
    - upload_logo($tenantId, $file)
    - generate_css($tenantId)
}

class DomainManager {
    - map_domain($tenantId, $domain)
    - verify_domain($domain)
    - provision_ssl($domain)
    - configure_dns($domain, $tenantId)
    - validate_ssl($domain)
    - renew_ssl($domain)
}
```

---

## 5. Database Schema

### Master Database Tables

#### Table: `bkx_tenants`
```sql
CREATE TABLE bkx_tenants (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_key VARCHAR(100) NOT NULL UNIQUE,
    tenant_name VARCHAR(200) NOT NULL,
    tenant_type ENUM('standard', 'franchise', 'enterprise') DEFAULT 'standard',
    status ENUM('active', 'suspended', 'trial', 'expired', 'pending') DEFAULT 'pending',

    -- Database Configuration
    db_host VARCHAR(255) DEFAULT 'localhost',
    db_name VARCHAR(100) NOT NULL UNIQUE,
    db_user VARCHAR(100),
    db_password VARCHAR(255),
    db_prefix VARCHAR(20) DEFAULT 'bkx_',

    -- Domain Configuration
    primary_domain VARCHAR(255) UNIQUE,
    custom_domain VARCHAR(255) UNIQUE,
    subdomain VARCHAR(100) UNIQUE,
    ssl_enabled TINYINT(1) DEFAULT 0,
    ssl_certificate TEXT,
    ssl_private_key TEXT,

    -- Storage Configuration
    storage_driver VARCHAR(50) DEFAULT 's3',
    storage_path VARCHAR(500),
    storage_quota_gb INT DEFAULT 10,
    storage_used_gb DECIMAL(10,2) DEFAULT 0,

    -- Resource Limits
    max_users INT DEFAULT 10,
    max_bookings_per_month INT DEFAULT 1000,
    max_staff INT DEFAULT 5,
    max_services INT DEFAULT 50,
    api_rate_limit INT DEFAULT 1000,

    -- Billing
    subscription_plan_id BIGINT(20) UNSIGNED,
    billing_cycle ENUM('monthly', 'yearly') DEFAULT 'monthly',
    subscription_amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',
    billing_email VARCHAR(255),
    payment_status ENUM('active', 'past_due', 'canceled') DEFAULT 'active',
    next_billing_date DATE,
    trial_ends_at DATETIME,

    -- Franchisee Info (if applicable)
    franchisee_id BIGINT(20) UNSIGNED,
    royalty_percentage DECIMAL(5,2),
    territory_data LONGTEXT,

    -- Configuration
    timezone VARCHAR(50) DEFAULT 'UTC',
    locale VARCHAR(10) DEFAULT 'en_US',
    settings LONGTEXT,
    metadata LONGTEXT,

    -- Branding
    branding LONGTEXT,
    custom_css TEXT,

    -- Timestamps
    provisioned_at DATETIME,
    activated_at DATETIME,
    suspended_at DATETIME,
    expires_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX tenant_key_idx (tenant_key),
    INDEX status_idx (status),
    INDEX primary_domain_idx (primary_domain),
    INDEX custom_domain_idx (custom_domain),
    INDEX subdomain_idx (subdomain),
    INDEX franchisee_idx (franchisee_id),
    INDEX subscription_plan_idx (subscription_plan_id),
    INDEX next_billing_idx (next_billing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table: `bkx_tenant_users`
```sql
CREATE TABLE bkx_tenant_users (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    role VARCHAR(50) NOT NULL,
    is_owner TINYINT(1) DEFAULT 0,
    permissions LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    UNIQUE KEY unique_tenant_user (tenant_id, user_id),
    INDEX tenant_idx (tenant_id),
    INDEX user_idx (user_id),
    FOREIGN KEY (tenant_id) REFERENCES bkx_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table: `bkx_tenant_billing`
```sql
CREATE TABLE bkx_tenant_billing (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT(20) UNSIGNED NOT NULL,
    invoice_number VARCHAR(100) UNIQUE,
    billing_period_start DATE NOT NULL,
    billing_period_end DATE NOT NULL,

    -- Usage Metrics
    bookings_count INT DEFAULT 0,
    users_count INT DEFAULT 0,
    storage_gb DECIMAL(10,2) DEFAULT 0,
    api_calls INT DEFAULT 0,
    email_sent INT DEFAULT 0,

    -- Charges
    base_charge DECIMAL(10,2) DEFAULT 0,
    usage_charges DECIMAL(10,2) DEFAULT 0,
    overage_charges DECIMAL(10,2) DEFAULT 0,
    royalty_fee DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,

    -- Payment
    status ENUM('draft', 'pending', 'paid', 'overdue', 'canceled') DEFAULT 'draft',
    payment_method VARCHAR(50),
    payment_date DATETIME,
    payment_transaction_id VARCHAR(255),

    invoice_data LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX tenant_idx (tenant_id),
    INDEX status_idx (status),
    INDEX billing_period_idx (billing_period_start, billing_period_end),
    FOREIGN KEY (tenant_id) REFERENCES bkx_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table: `bkx_tenant_usage`
```sql
CREATE TABLE bkx_tenant_usage (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT(20) UNSIGNED NOT NULL,
    metric VARCHAR(50) NOT NULL,
    value DECIMAL(15,2) NOT NULL,
    unit VARCHAR(20),
    recorded_at DATETIME NOT NULL,
    metadata LONGTEXT,

    INDEX tenant_idx (tenant_id),
    INDEX metric_idx (metric),
    INDEX recorded_at_idx (recorded_at),
    INDEX tenant_metric_date_idx (tenant_id, metric, recorded_at),
    FOREIGN KEY (tenant_id) REFERENCES bkx_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table: `bkx_franchisees`
```sql
CREATE TABLE bkx_franchisees (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    franchisee_code VARCHAR(50) UNIQUE,
    company_name VARCHAR(200) NOT NULL,
    owner_name VARCHAR(200) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address LONGTEXT,

    -- Contract Details
    contract_start_date DATE NOT NULL,
    contract_end_date DATE,
    contract_status ENUM('active', 'suspended', 'terminated', 'expired') DEFAULT 'active',

    -- Financial
    royalty_percentage DECIMAL(5,2) NOT NULL,
    marketing_fee_percentage DECIMAL(5,2) DEFAULT 0,
    initial_franchise_fee DECIMAL(10,2),
    monthly_fee DECIMAL(10,2),

    -- Territory
    territory_definition LONGTEXT,
    exclusive_territory TINYINT(1) DEFAULT 1,

    -- Performance
    performance_score DECIMAL(5,2),
    compliance_score DECIMAL(5,2),
    last_audit_date DATE,
    next_audit_date DATE,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX franchisee_code_idx (franchisee_code),
    INDEX contract_status_idx (contract_status),
    INDEX owner_email_idx (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table: `bkx_franchisee_royalties`
```sql
CREATE TABLE bkx_franchisee_royalties (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    franchisee_id BIGINT(20) UNSIGNED NOT NULL,
    tenant_id BIGINT(20) UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    -- Revenue
    gross_revenue DECIMAL(10,2) NOT NULL,
    net_revenue DECIMAL(10,2) NOT NULL,

    -- Fees
    royalty_percentage DECIMAL(5,2) NOT NULL,
    royalty_amount DECIMAL(10,2) NOT NULL,
    marketing_fee DECIMAL(10,2) DEFAULT 0,
    technology_fee DECIMAL(10,2) DEFAULT 0,
    other_fees DECIMAL(10,2) DEFAULT 0,
    total_fees DECIMAL(10,2) NOT NULL,

    -- Payment
    status ENUM('calculated', 'invoiced', 'paid', 'overdue') DEFAULT 'calculated',
    due_date DATE,
    paid_date DATE,
    payment_reference VARCHAR(255),

    calculation_data LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX franchisee_idx (franchisee_id),
    INDEX tenant_idx (tenant_id),
    INDEX period_idx (period_start, period_end),
    INDEX status_idx (status),
    FOREIGN KEY (franchisee_id) REFERENCES bkx_franchisees(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES bkx_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table: `bkx_subscription_plans`
```sql
CREATE TABLE bkx_subscription_plans (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(100) NOT NULL,
    plan_slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,

    -- Pricing
    monthly_price DECIMAL(10,2),
    yearly_price DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'USD',

    -- Limits
    max_users INT DEFAULT 10,
    max_bookings_per_month INT DEFAULT 1000,
    max_staff INT DEFAULT 5,
    max_services INT DEFAULT 50,
    storage_quota_gb INT DEFAULT 10,
    api_rate_limit INT DEFAULT 1000,

    -- Features
    features LONGTEXT,
    custom_domain TINYINT(1) DEFAULT 0,
    white_label TINYINT(1) DEFAULT 0,
    api_access TINYINT(1) DEFAULT 0,
    priority_support TINYINT(1) DEFAULT 0,

    -- Status
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX plan_slug_idx (plan_slug),
    INDEX is_active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table: `bkx_tenant_activity_log`
```sql
CREATE TABLE bkx_tenant_activity_log (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT(20) UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    user_id BIGINT(20) UNSIGNED,
    description TEXT,
    metadata LONGTEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME NOT NULL,

    INDEX tenant_idx (tenant_id),
    INDEX action_idx (action),
    INDEX category_idx (category),
    INDEX created_at_idx (created_at),
    FOREIGN KEY (tenant_id) REFERENCES bkx_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tenant-Specific Database Schema

Each tenant database contains the full BookingX schema:
- `bkx_{tenant}_bookings`
- `bkx_{tenant}_customers`
- `bkx_{tenant}_services`
- `bkx_{tenant}_staff`
- `bkx_{tenant}_payments`
- All other BookingX core tables

---

## 6. Configuration & Settings

### Master Admin Settings

```php
[
    // Multi-Tenancy Settings
    'multi_tenancy_enabled' => true,
    'isolation_strategy' => 'database', // database, schema, shared
    'auto_provisioning' => true,
    'trial_period_days' => 14,
    'default_subscription_plan' => 'starter',

    // Domain Management
    'master_domain' => 'bookingx.io',
    'subdomain_pattern' => '{tenant}.bookingx.io',
    'allow_custom_domains' => true,
    'auto_ssl_provision' => true,
    'ssl_provider' => 'letsencrypt',

    // Database Settings
    'db_host' => 'localhost',
    'db_prefix_pattern' => 'bkx_tenant_{id}_',
    'connection_pooling' => true,
    'max_connections_per_tenant' => 10,

    // Resource Management
    'default_storage_quota_gb' => 10,
    'default_user_limit' => 10,
    'default_booking_limit' => 1000,
    'enforce_quotas' => true,
    'quota_grace_percentage' => 10,

    // Billing
    'billing_enabled' => true,
    'billing_cycle' => 'monthly',
    'auto_suspend_on_nonpayment' => true,
    'grace_period_days' => 7,
    'payment_gateway' => 'stripe',

    // Franchise Settings
    'franchise_mode_enabled' => true,
    'default_royalty_percentage' => 8,
    'royalty_calculation_method' => 'gross_revenue',
    'royalty_billing_frequency' => 'monthly',

    // Tenant Provisioning
    'seed_demo_data' => true,
    'onboarding_wizard' => true,
    'send_welcome_email' => true,
    'admin_notification_email' => 'admin@bookingx.io',

    // Performance
    'cache_tenant_config' => true,
    'cache_ttl_seconds' => 3600,
    'use_redis_cache' => true,
    'enable_query_cache' => true,

    // Security
    'enforce_tenant_isolation' => true,
    'cross_tenant_access_logging' => true,
    'encrypt_tenant_db_credentials' => true,
    'rotate_encryption_keys' => false,

    // Monitoring
    'enable_metrics' => true,
    'track_usage_metrics' => true,
    'alert_quota_threshold' => 90,
    'health_check_interval' => 300,
]
```

### Tenant-Specific Settings

```php
[
    // Inherited from plan
    'max_users' => 10,
    'max_bookings_per_month' => 1000,
    'storage_quota_gb' => 10,

    // Customizable
    'business_name' => '',
    'timezone' => 'America/New_York',
    'locale' => 'en_US',
    'currency' => 'USD',

    // Branding
    'logo_url' => '',
    'primary_color' => '#007bff',
    'secondary_color' => '#6c757d',
    'custom_css' => '',

    // Features (plan-dependent)
    'custom_domain_enabled' => false,
    'api_access_enabled' => false,
    'white_label_enabled' => false,
]
```

---

## 7. User Interface Requirements

### Super Admin Dashboard

1. **Network Overview**
   - Total tenants count
   - Active/suspended/trial breakdown
   - Total revenue (all tenants)
   - Total bookings (all tenants)
   - Growth metrics
   - System health status
   - Resource utilization
   - Recent tenant activity

2. **Tenant Management**
   - Tenants list table
   - Filter by status, plan, date
   - Quick actions (activate, suspend, view)
   - Bulk operations
   - Tenant search
   - Export tenant data

3. **Tenant Details Page**
   - Tenant information
   - Subscription details
   - Usage metrics
   - Billing history
   - Activity log
   - Resource usage graphs
   - Quick actions panel
   - Login as tenant admin

4. **Billing Dashboard**
   - Revenue overview
   - Pending invoices
   - Payment failures
   - Subscription renewals
   - MRR/ARR metrics
   - Churn rate
   - Export financial reports

5. **Franchise Management**
   - Franchisees list
   - Royalty calculations
   - Territory map view
   - Performance metrics
   - Compliance tracking
   - Contract management

6. **System Configuration**
   - Global settings
   - Subscription plans
   - Resource limits
   - Domain management
   - Email templates
   - Feature flags

### Tenant Admin Dashboard

1. **Tenant Dashboard**
   - Own tenant metrics
   - Booking overview
   - Revenue stats
   - Quick actions
   - Notifications

2. **Settings & Configuration**
   - Business information
   - Branding customization
   - Feature settings
   - Payment configuration
   - Staff management

3. **Billing Portal**
   - Current plan
   - Usage statistics
   - Invoice history
   - Payment methods
   - Upgrade/downgrade options

### Franchisee Portal

1. **Performance Dashboard**
   - Revenue metrics
   - Booking statistics
   - Customer satisfaction
   - Territory performance

2. **Royalty Center**
   - Current period summary
   - Payment history
   - Fee breakdown
   - Download statements

3. **Support & Resources**
   - Training materials
   - Marketing assets
   - Support tickets
   - Compliance checklists

---

## 8. Security Considerations

### Tenant Isolation Security

1. **Database-Level Isolation**
   - Separate database per tenant
   - No shared tables with tenant data
   - Encrypted credentials per tenant
   - Connection pooling with tenant context
   - Prevent SQL injection across tenants

2. **Application-Level Isolation**
   - Tenant context validation on every request
   - No cross-tenant queries allowed
   - Tenant ID in all data access
   - Middleware enforcement
   - Session isolation

3. **Data Encryption**
   - Encrypt tenant database credentials
   - Separate encryption keys per tenant
   - Encrypt sensitive tenant configuration
   - SSL/TLS for all connections
   - Encrypted backups

4. **Access Control**
   - Role-based permissions per tenant
   - Super admin separate from tenant admin
   - API authentication per tenant
   - Rate limiting per tenant
   - Login audit trail

5. **Attack Prevention**
   - Tenant enumeration protection
   - Brute force protection per tenant
   - DDoS mitigation
   - Input validation and sanitization
   - XSS/CSRF protection

### Compliance

- **GDPR:** Per-tenant data processing records
- **SOC 2:** Audit trail for all tenant operations
- **PCI DSS:** Isolated payment processing per tenant
- **Data Residency:** Option to specify tenant data location
- **Right to be Forgotten:** Per-tenant data deletion

---

## 9. Testing Strategy

### Unit Tests

```php
- test_tenant_creation()
- test_tenant_database_provisioning()
- test_tenant_isolation()
- test_tenant_resolver()
- test_quota_enforcement()
- test_usage_tracking()
- test_billing_calculation()
- test_royalty_calculation()
- test_domain_mapping()
- test_ssl_provisioning()
```

### Integration Tests

```php
- test_complete_tenant_provisioning()
- test_cross_tenant_isolation()
- test_tenant_migration()
- test_subscription_lifecycle()
- test_franchisee_workflow()
- test_multi_tenant_queries()
- test_resource_limit_enforcement()
```

### Load Testing

- Concurrent tenant access (1000+ tenants)
- Database connection pooling
- Cache performance
- API rate limiting
- Resource quota enforcement

### Security Testing

- Tenant enumeration attempts
- Cross-tenant data access attempts
- SQL injection in multi-tenant context
- Session hijacking prevention
- Privilege escalation testing

---

## 10. Performance Optimization

### Database Optimization

```php
// Connection Pooling
class TenantConnectionPool {
    private static $pool = [];
    private const MAX_CONNECTIONS = 100;

    public static function getConnection($tenantId) {
        // Reuse existing connections
        // Close idle connections
        // Limit per-tenant connections
    }
}

// Query Optimization
- Index on tenant_id in all shared tables
- Partition large tables by tenant_id
- Cache frequent tenant config queries
- Use prepared statements
- Connection persistence
```

### Caching Strategy

```php
// Multi-layer caching
- L1: Application cache (in-memory, per-request)
- L2: Redis cache (per-tenant namespace)
- L3: Database query cache

// Cache keys
"tenant:{id}:config"
"tenant:{id}:settings"
"tenant:{id}:branding"
"tenant:{id}:usage:{date}"
```

### Resource Management

```php
// Quota Enforcement
class ResourceGuard {
    public function checkQuota($tenantId, $resource) {
        $usage = $this->getCurrentUsage($tenantId, $resource);
        $limit = $this->getQuota($tenantId, $resource);

        if ($usage >= $limit) {
            throw new QuotaExceededException();
        }

        return true;
    }
}
```

---

## 11. Scalability Patterns

### Horizontal Scaling

```
┌─────────────────────────────────────────┐
│         Load Balancer (Nginx)           │
└────────────────┬────────────────────────┘
                 │
    ┌────────────┴──────────────┐
    ▼                           ▼
┌─────────┐                ┌─────────┐
│ App     │                │ App     │
│ Server1 │                │ Server2 │
└────┬────┘                └────┬────┘
     │                          │
     └─────────┬────────────────┘
               ▼
     ┌─────────────────┐
     │  Database       │
     │  Cluster        │
     │  (Read/Write    │
     │   Replicas)     │
     └─────────────────┘
```

### Database Sharding

```php
// Shard tenants across multiple database servers
class TenantShardManager {
    public function getShardForTenant($tenantId) {
        // Consistent hashing
        $shard = $tenantId % $this->shardCount;
        return $this->shards[$shard];
    }
}
```

### Microservices Architecture (Optional)

```
- Tenant Management Service
- Billing Service
- Provisioning Service
- Analytics Service
- Notification Service
```

---

## 12. Development Timeline

### Phase 1: Core Infrastructure (Weeks 1-3)
- [ ] Database schema design
- [ ] Tenant provisioning system
- [ ] Tenant resolver middleware
- [ ] Database connection management
- [ ] Basic tenant CRUD operations

### Phase 2: Isolation & Security (Weeks 4-5)
- [ ] Implement tenant isolation
- [ ] Cache isolation
- [ ] File storage isolation
- [ ] Session isolation
- [ ] Security testing

### Phase 3: Billing System (Weeks 6-7)
- [ ] Subscription plan management
- [ ] Usage tracking
- [ ] Invoice generation
- [ ] Payment processing integration
- [ ] Billing notifications

### Phase 4: Franchise Management (Week 8)
- [ ] Franchisee registration
- [ ] Royalty calculation engine
- [ ] Territory management
- [ ] Franchise reporting

### Phase 5: Resource Management (Week 9)
- [ ] Quota system
- [ ] Usage metering
- [ ] Resource enforcement
- [ ] Alerts and notifications

### Phase 6: UI Development (Weeks 10-11)
- [ ] Super admin dashboard
- [ ] Tenant management interface
- [ ] Billing portal
- [ ] Franchisee portal
- [ ] Tenant settings UI

### Phase 7: Advanced Features (Week 12)
- [ ] Domain management
- [ ] SSL provisioning
- [ ] Branding customization
- [ ] Tenant cloning
- [ ] Migration tools

### Phase 8: Testing & Optimization (Weeks 13-14)
- [ ] Unit testing
- [ ] Integration testing
- [ ] Load testing
- [ ] Security audit
- [ ] Performance optimization

### Phase 9: Documentation (Week 15)
- [ ] Technical documentation
- [ ] API documentation
- [ ] Admin guide
- [ ] User guide
- [ ] Video tutorials

### Phase 10: Launch (Week 16)
- [ ] Beta testing
- [ ] Bug fixes
- [ ] Production deployment
- [ ] Monitoring setup
- [ ] Support documentation

**Total Estimated Timeline:** 16 weeks (4 months)

---

## 13. Monitoring & Maintenance

### Metrics to Track

```php
// System-wide metrics
- Total active tenants
- Total bookings across network
- System resource utilization
- API response times
- Database connection pool usage

// Per-tenant metrics
- Active users
- Booking volume
- Storage usage
- API calls
- Error rates
- Response times

// Business metrics
- Monthly Recurring Revenue (MRR)
- Churn rate
- Average Revenue Per Tenant (ARPT)
- Customer Lifetime Value (CLV)
- Tenant growth rate
```

### Health Checks

```php
class TenantHealthCheck {
    public function check($tenantId) {
        return [
            'database_accessible' => $this->checkDatabase($tenantId),
            'storage_accessible' => $this->checkStorage($tenantId),
            'cache_working' => $this->checkCache($tenantId),
            'quota_status' => $this->checkQuotas($tenantId),
            'ssl_valid' => $this->checkSSL($tenantId),
        ];
    }
}
```

### Automated Tasks

```php
// Daily tasks
- Calculate usage metrics
- Generate billing data
- Cleanup expired trials
- Health check all tenants
- Backup tenant databases

// Weekly tasks
- Generate invoices
- Process royalty payments
- Optimize databases
- Review quota usage
- Security scans

// Monthly tasks
- Process subscriptions
- Archive old data
- Generate reports
- Performance review
- Capacity planning
```

---

## 14. Migration & Deployment

### Deployment Strategy

1. **Blue-Green Deployment**
   - Zero downtime updates
   - Quick rollback capability
   - Test with subset of tenants

2. **Database Migrations**
   ```bash
   # Migrate master database
   php artisan migrate --database=master

   # Migrate all tenant databases
   php artisan tenants:migrate --all

   # Migrate specific tenant
   php artisan tenants:migrate --tenant=123
   ```

3. **Feature Flags**
   ```php
   if (Feature::enabled('new_billing_system', $tenantId)) {
       // Use new billing system
   } else {
       // Use old billing system
   }
   ```

---

## 15. Success Metrics

### Technical Metrics
- **Tenant Provisioning Time:** < 60 seconds
- **API Response Time:** < 200ms (95th percentile)
- **Database Query Time:** < 50ms average
- **Uptime:** 99.9% SLA
- **Zero Cross-Tenant Data Leaks:** Critical requirement

### Business Metrics
- **Tenant Activation Rate:** > 40%
- **Tenant Retention:** > 85% annually
- **Support Ticket Volume:** < 5% of tenants
- **Franchisee Satisfaction:** > 4.5/5
- **System Scalability:** Support 10,000+ tenants

---

## 16. Known Limitations

1. **Database Scaling:** Limited by database server capacity
2. **Tenant Count:** Optimal performance up to 10,000 tenants per instance
3. **Real-time Sync:** Cross-tenant features have eventual consistency
4. **Customization:** Tenant-specific code customization not supported
5. **Data Migration:** Moving tenants between instances requires downtime

---

## 17. Future Enhancements

### Version 2.0 Roadmap
- [ ] Kubernetes-based auto-scaling
- [ ] Multi-region deployment
- [ ] Advanced analytics dashboard
- [ ] AI-powered resource optimization
- [ ] Tenant marketplace
- [ ] Mobile app for franchise management
- [ ] GraphQL API
- [ ] Webhook system per tenant
- [ ] Advanced white-labeling (mobile apps)
- [ ] Blockchain-based royalty payments

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development
