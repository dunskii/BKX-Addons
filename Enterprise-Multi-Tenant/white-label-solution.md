# White-Label Solution Add-on - Development Documentation

## 1. Overview

**Add-on Name:** White-Label Solution
**Price:** $499
**Category:** Enterprise & Multi-Tenant
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Complete white-label booking platform enabling agencies, resellers, and SaaS providers to rebrand BookingX as their own product. Includes unlimited customization capabilities, custom domain management, full API access, reseller program infrastructure, client management portal, and revenue-sharing automation. Turn BookingX into your branded booking solution.

### Value Proposition
- **Complete Brand Ownership:** Remove all BookingX branding
- **Unlimited Customization:** Full control over appearance and functionality
- **Reseller Program:** Build recurring revenue business
- **Client Management:** Sophisticated multi-client portal
- **Revenue Sharing:** Automated commission and payment system
- **Custom Domains:** Unlimited white-labeled domains
- **API Access:** Build custom integrations and features
- **Mobile App Branding:** Extend white-labeling to mobile apps
- **Partner Resources:** Sales tools, marketing materials, documentation
- **ROI Potential:** 300%+ profit margins for resellers

---

## 2. Features & Requirements

### Core Features

1. **Complete Branding Removal**
   - Remove all BookingX logos
   - Remove "Powered by BookingX" footer
   - Replace admin panel branding
   - Custom login screen
   - Custom email signatures
   - Custom PDF invoices/receipts
   - Remove update notices
   - Custom admin welcome screen
   - Rebrand plugin/add-on names
   - Custom documentation URLs

2. **Visual Customization**
   - Unlimited color schemes
   - Custom CSS editor with preview
   - Custom JavaScript injection
   - Font family customization
   - Icon library replacement
   - Button style customization
   - Form field styling
   - Modal/popup themes
   - Animation customization
   - Responsive breakpoint control

3. **Custom Domain Management**
   - Unlimited custom domains
   - Subdomain wildcard support
   - SSL certificate auto-provisioning
   - DNS management interface
   - Domain verification system
   - CNAME/A record configuration
   - Let's Encrypt integration
   - Commercial SSL support
   - Domain transfer tools
   - Multi-domain routing

4. **Admin Panel Customization**
   - Custom admin theme
   - Rebrand menu items
   - Custom dashboard widgets
   - Logo and favicon upload
   - Color scheme editor
   - Custom welcome message
   - Hide/show menu items
   - Reorder navigation
   - Custom help documentation
   - Embedded video tutorials

5. **Email Template Customization**
   - Visual email builder
   - Unlimited templates
   - Custom HTML/CSS
   - Dynamic variable system
   - Multi-language support
   - A/B testing capability
   - Preview and test sending
   - Template versioning
   - Conditional content
   - Personalization tokens

6. **Document Branding**
   - Custom invoice templates
   - Receipt customization
   - Quote/estimate templates
   - Report header/footer
   - PDF watermarks
   - Custom letterhead
   - Digital signature
   - QR code integration
   - Multi-language documents
   - Template library

7. **Reseller Program Infrastructure**
   - Reseller registration system
   - Commission structure management
   - Tiered pricing models
   - Revenue share automation
   - Reseller dashboard
   - Client management portal
   - License key generation
   - Activation/deactivation system
   - Usage tracking
   - Payout automation

8. **Client Management Portal**
   - Multi-client dashboard
   - Client provisioning workflow
   - Usage metering per client
   - Client billing management
   - Support ticket system
   - Client communication tools
   - Resource allocation
   - Performance monitoring
   - Client analytics
   - Bulk client operations

9. **API Access & Customization**
   - Full REST API access
   - Custom API endpoints
   - Webhook configuration
   - API key management
   - Rate limiting control
   - API documentation generator
   - Sandbox environment
   - API versioning
   - OAuth 2.0 support
   - GraphQL support (optional)

10. **Mobile App White-Labeling**
    - Custom app name
    - App icon customization
    - Splash screen branding
    - Color scheme configuration
    - Push notification branding
    - App store listing content
    - Deep linking configuration
    - In-app branding
    - Custom app domains
    - App publishing support

11. **Marketing & Sales Tools**
    - Lead capture forms
    - Demo environment provisioning
    - Sales proposal generator
    - ROI calculator
    - Pricing table builder
    - Comparison charts
    - Testimonial management
    - Case study templates
    - Marketing material library
    - Email campaign tools

12. **Documentation & Support**
    - Custom knowledge base
    - Video tutorial hosting
    - User guide generator
    - API documentation
    - Developer portal
    - Support portal customization
    - Training material templates
    - Onboarding wizard builder
    - Changelog management
    - FAQ system

### User Roles & Permissions

- **Platform Owner:** Full system access, reseller management
- **Reseller Admin:** Client management, branding, billing
- **Reseller Support:** Limited support access to clients
- **Client Admin:** Full access within own account
- **Client Staff:** Limited access within client account
- **End User:** Customer-facing booking interface

---

## 3. Technical Specifications

### Technology Stack

- **Frontend Framework:** React/Vue.js for customization UI
- **CSS Preprocessor:** SASS with variable system
- **Template Engine:** Twig/Blade for email templates
- **PDF Generation:** TCPDF/FPDF with custom templates
- **Asset Management:** Webpack for bundling custom assets
- **Image Processing:** GD/ImageMagick for logo manipulation
- **Storage:** S3-compatible for client assets
- **CDN:** CloudFlare for white-labeled assets
- **DNS Management:** Route53 API or similar
- **SSL Provisioning:** ACME protocol (Let's Encrypt)

### Dependencies

- BookingX Core 2.0+
- Multi-Tenant Management Add-on (recommended)
- PHP GD or ImageMagick extension
- PHP OpenSSL for SSL management
- cURL for API integrations
- Node.js for asset compilation
- Composer for PHP dependencies
- S3-compatible storage
- Redis for caching
- Action Scheduler for automation

### API Integration Points

```php
// White-Label Configuration API
GET    /wp-json/bookingx/v1/whitelabel/config
PUT    /wp-json/bookingx/v1/whitelabel/config
POST   /wp-json/bookingx/v1/whitelabel/branding/logo
POST   /wp-json/bookingx/v1/whitelabel/branding/colors
GET    /wp-json/bookingx/v1/whitelabel/branding/preview

// Domain Management API
POST   /wp-json/bookingx/v1/whitelabel/domains
GET    /wp-json/bookingx/v1/whitelabel/domains
PUT    /wp-json/bookingx/v1/whitelabel/domains/{id}
DELETE /wp-json/bookingx/v1/whitelabel/domains/{id}
POST   /wp-json/bookingx/v1/whitelabel/domains/{id}/verify
POST   /wp-json/bookingx/v1/whitelabel/domains/{id}/ssl

// Reseller Management API
POST   /wp-json/bookingx/v1/resellers
GET    /wp-json/bookingx/v1/resellers
GET    /wp-json/bookingx/v1/resellers/{id}
PUT    /wp-json/bookingx/v1/resellers/{id}
GET    /wp-json/bookingx/v1/resellers/{id}/clients
GET    /wp-json/bookingx/v1/resellers/{id}/revenue
GET    /wp-json/bookingx/v1/resellers/{id}/commissions

// Client Management API
POST   /wp-json/bookingx/v1/clients
GET    /wp-json/bookingx/v1/clients
GET    /wp-json/bookingx/v1/clients/{id}
PUT    /wp-json/bookingx/v1/clients/{id}
DELETE /wp-json/bookingx/v1/clients/{id}
GET    /wp-json/bookingx/v1/clients/{id}/usage
POST   /wp-json/bookingx/v1/clients/{id}/suspend

// Template Management API
GET    /wp-json/bookingx/v1/whitelabel/templates
POST   /wp-json/bookingx/v1/whitelabel/templates
GET    /wp-json/bookingx/v1/whitelabel/templates/{id}
PUT    /wp-json/bookingx/v1/whitelabel/templates/{id}
POST   /wp-json/bookingx/v1/whitelabel/templates/{id}/preview

// Custom API Endpoints (for resellers)
ANY    /wp-json/custom/{reseller_id}/{endpoint}
```

---

## 4. Architecture & Design

### System Architecture

```
┌──────────────────────────────────────────────────┐
│         Platform Owner Dashboard                 │
│    - Manage Resellers                            │
│    - Global Configuration                        │
│    - Revenue Overview                            │
└─────────────────┬────────────────────────────────┘
                  │
    ┌─────────────┴──────────────┐
    ▼                            ▼
┌─────────────────┐      ┌─────────────────┐
│  Reseller 1     │      │  Reseller N     │
│  Dashboard      │      │  Dashboard      │
│  - Branding     │      │  - Branding     │
│  - Clients      │      │  - Clients      │
│  - Revenue      │      │  - Revenue      │
└────────┬────────┘      └────────┬────────┘
         │                        │
         ▼                        ▼
    ┌─────────────┐          ┌─────────────┐
    │  Client 1   │          │  Client 2   │
    │  Client 2   │          │  Client 3   │
    │  Client 3   │          │  Client 4   │
    └─────────────┘          └─────────────┘
```

### White-Label Asset Management

```
┌─────────────────────────────────────────┐
│       Asset Storage Structure           │
├─────────────────────────────────────────┤
│ /whitelabel/                            │
│   /reseller-{id}/                       │
│     /branding/                          │
│       - logo.png                        │
│       - logo-dark.png                   │
│       - favicon.ico                     │
│       - app-icon.png                    │
│     /css/                               │
│       - custom.css                      │
│       - variables.scss                  │
│     /js/                                │
│       - custom.js                       │
│     /emails/                            │
│       - template-1.html                 │
│       - template-2.html                 │
│     /documents/                         │
│       - invoice-template.pdf            │
│       - receipt-template.pdf            │
│     /client-{id}/                       │
│       /branding/                        │
│       /custom/                          │
└─────────────────────────────────────────┘
```

### Branding Hierarchy

```
Platform Defaults
    ↓ (overridden by)
Reseller Branding
    ↓ (overridden by)
Client Custom Branding
    ↓ (overridden by)
End-User Preferences
```

### Class Structure

```php
namespace BookingX\Addons\WhiteLabel;

class WhiteLabelManager {
    - enable_white_label($entityId, $entityType)
    - disable_white_label($entityId)
    - get_branding($entityId)
    - set_branding($entityId, $brandingData)
    - apply_branding($entityId)
    - reset_to_defaults($entityId)
    - clone_branding($sourceId, $targetId)
}

class BrandingEngine {
    - load_branding($entityId)
    - compile_css($brandingConfig)
    - generate_variables($colors, $fonts)
    - cache_compiled_assets($entityId)
    - invalidate_cache($entityId)
    - apply_to_request($branding)
}

class DomainManager {
    - add_domain($entityId, $domain)
    - verify_domain($domain)
    - configure_dns($domain, $records)
    - provision_ssl($domain)
    - renew_ssl($domain)
    - map_domain_to_entity($domain, $entityId)
    - get_domains($entityId)
    - delete_domain($domainId)
}

class SSLManager {
    - request_certificate($domain)
    - install_certificate($domain, $cert, $key)
    - verify_ssl($domain)
    - auto_renew($domain)
    - get_certificate_info($domain)
    - revoke_certificate($domain)
}

class ResellerManager {
    - register_reseller($data)
    - activate_reseller($resellerId)
    - suspend_reseller($resellerId)
    - set_commission_rate($resellerId, $rate)
    - get_reseller_clients($resellerId)
    - calculate_revenue($resellerId, $period)
    - process_payout($resellerId)
}

class ClientManager {
    - create_client($resellerId, $clientData)
    - provision_client($clientId)
    - suspend_client($clientId)
    - activate_client($clientId)
    - set_client_limits($clientId, $limits)
    - track_client_usage($clientId)
    - generate_client_invoice($clientId)
}

class CommissionCalculator {
    - calculate_commission($resellerId, $revenue)
    - apply_tier_rules($resellerId, $amount)
    - track_commission($resellerId, $commission)
    - generate_payout_report($resellerId, $period)
    - process_payment($resellerId, $amount)
}

class TemplateManager {
    - create_template($type, $content)
    - get_template($templateId)
    - update_template($templateId, $content)
    - render_template($templateId, $variables)
    - clone_template($templateId)
    - get_templates_by_type($type)
}

class EmailTemplateBuilder {
    - create_email_template($name, $html)
    - add_variable($template, $variable)
    - preview_template($templateId, $sampleData)
    - test_send($templateId, $email)
    - version_template($templateId)
    - export_template($templateId)
    - import_template($html)
}

class DocumentGenerator {
    - generate_invoice_pdf($bookingId, $templateId)
    - generate_receipt_pdf($paymentId, $templateId)
    - apply_branding($pdf, $brandingId)
    - add_watermark($pdf, $image)
    - add_signature($pdf, $signature)
    - get_document($documentId)
}

class CustomizationEngine {
    - apply_custom_css($entityId)
    - apply_custom_js($entityId)
    - compile_assets($entityId)
    - minify_assets($assets)
    - deploy_to_cdn($assets)
    - version_assets($entityId)
}

class APICustomizer {
    - create_custom_endpoint($resellerId, $endpoint)
    - register_webhook($resellerId, $url, $events)
    - generate_api_key($resellerId)
    - revoke_api_key($keyId)
    - set_rate_limits($resellerId, $limits)
    - track_api_usage($resellerId)
}

class MobileAppBranding {
    - configure_app_branding($resellerId, $config)
    - generate_app_icons($logo)
    - create_splash_screen($branding)
    - configure_deep_links($appId, $domain)
    - generate_app_config($resellerId)
    - export_branding_package($resellerId)
}

class MarketingToolkit {
    - generate_proposal($resellerId, $prospectData)
    - create_demo_environment($resellerId)
    - generate_roi_calculator($resellerId)
    - create_comparison_chart($features)
    - export_marketing_materials($resellerId)
}

class LicenseManager {
    - generate_license_key($clientId)
    - validate_license($licenseKey)
    - activate_license($licenseKey, $domain)
    - deactivate_license($licenseKey)
    - check_license_status($licenseKey)
    - suspend_license($licenseKey)
}

class AssetOptimizer {
    - optimize_logo($image)
    - generate_responsive_images($image)
    - compress_css($css)
    - minify_javascript($js)
    - cache_bust($assets)
}
```

---

## 5. Database Schema

### Table: `bkx_whitelabel_config`
```sql
CREATE TABLE bkx_whitelabel_config (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id BIGINT(20) UNSIGNED NOT NULL,
    entity_type ENUM('platform', 'reseller', 'client') NOT NULL,

    -- Branding
    brand_name VARCHAR(200),
    logo_url VARCHAR(500),
    logo_dark_url VARCHAR(500),
    favicon_url VARCHAR(500),
    app_icon_url VARCHAR(500),

    -- Colors
    primary_color VARCHAR(7),
    secondary_color VARCHAR(7),
    accent_color VARCHAR(7),
    text_color VARCHAR(7),
    background_color VARCHAR(7),
    color_scheme LONGTEXT,

    -- Typography
    font_family VARCHAR(200),
    heading_font VARCHAR(200),
    font_config LONGTEXT,

    -- Customization
    custom_css LONGTEXT,
    custom_js LONGTEXT,
    custom_html_header TEXT,
    custom_html_footer TEXT,

    -- Email Branding
    email_header_color VARCHAR(7),
    email_footer_text TEXT,
    email_signature TEXT,

    -- Document Branding
    invoice_template_id BIGINT(20) UNSIGNED,
    receipt_template_id BIGINT(20) UNSIGNED,
    document_header LONGTEXT,
    document_footer LONGTEXT,

    -- Settings
    hide_powered_by TINYINT(1) DEFAULT 1,
    custom_login_page TINYINT(1) DEFAULT 0,
    custom_admin_theme TINYINT(1) DEFAULT 0,
    remove_branding TINYINT(1) DEFAULT 1,

    -- Metadata
    parent_config_id BIGINT(20) UNSIGNED,
    inherit_from_parent TINYINT(1) DEFAULT 1,
    overrides LONGTEXT,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    UNIQUE KEY unique_entity (entity_id, entity_type),
    INDEX entity_idx (entity_id, entity_type),
    INDEX parent_idx (parent_config_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_whitelabel_domains`
```sql
CREATE TABLE bkx_whitelabel_domains (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id BIGINT(20) UNSIGNED NOT NULL,
    entity_type ENUM('platform', 'reseller', 'client') NOT NULL,

    domain VARCHAR(255) NOT NULL UNIQUE,
    subdomain VARCHAR(100),
    is_primary TINYINT(1) DEFAULT 0,
    status ENUM('pending', 'verified', 'active', 'failed') DEFAULT 'pending',

    -- DNS Configuration
    dns_records LONGTEXT,
    verification_token VARCHAR(255),
    verification_method ENUM('txt', 'cname', 'file') DEFAULT 'txt',
    verified_at DATETIME,

    -- SSL Configuration
    ssl_enabled TINYINT(1) DEFAULT 0,
    ssl_provider VARCHAR(50),
    ssl_certificate TEXT,
    ssl_private_key TEXT,
    ssl_issued_at DATETIME,
    ssl_expires_at DATETIME,
    auto_renew_ssl TINYINT(1) DEFAULT 1,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX entity_idx (entity_id, entity_type),
    INDEX domain_idx (domain),
    INDEX status_idx (status),
    INDEX ssl_expires_idx (ssl_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_resellers`
```sql
CREATE TABLE bkx_resellers (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reseller_code VARCHAR(50) UNIQUE NOT NULL,
    company_name VARCHAR(200) NOT NULL,
    contact_name VARCHAR(200) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    website VARCHAR(255),

    -- Business Info
    business_type VARCHAR(100),
    tax_id VARCHAR(100),
    address LONGTEXT,
    country VARCHAR(100),

    -- Account Status
    status ENUM('pending', 'active', 'suspended', 'terminated') DEFAULT 'pending',
    tier VARCHAR(50) DEFAULT 'basic',
    activated_at DATETIME,
    suspended_at DATETIME,

    -- Commission Structure
    commission_type ENUM('percentage', 'flat', 'tiered') DEFAULT 'percentage',
    commission_rate DECIMAL(5,2),
    commission_tiers LONGTEXT,
    minimum_payout DECIMAL(10,2) DEFAULT 100,

    -- Limits & Quotas
    max_clients INT DEFAULT 10,
    max_domains INT DEFAULT 5,
    api_access TINYINT(1) DEFAULT 1,
    white_label_enabled TINYINT(1) DEFAULT 1,
    custom_branding TINYINT(1) DEFAULT 1,

    -- Billing
    billing_email VARCHAR(255),
    payment_method VARCHAR(50),
    payment_details LONGTEXT,
    payout_frequency ENUM('weekly', 'biweekly', 'monthly') DEFAULT 'monthly',
    next_payout_date DATE,

    -- Performance
    total_clients INT DEFAULT 0,
    active_clients INT DEFAULT 0,
    total_revenue DECIMAL(15,2) DEFAULT 0,
    total_commission DECIMAL(15,2) DEFAULT 0,
    pending_payout DECIMAL(10,2) DEFAULT 0,

    -- Settings
    settings LONGTEXT,
    metadata LONGTEXT,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX reseller_code_idx (reseller_code),
    INDEX status_idx (status),
    INDEX email_idx (email),
    INDEX tier_idx (tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_reseller_clients`
```sql
CREATE TABLE bkx_reseller_clients (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reseller_id BIGINT(20) UNSIGNED NOT NULL,
    client_name VARCHAR(200) NOT NULL,
    client_email VARCHAR(255) NOT NULL,
    client_code VARCHAR(50) UNIQUE,

    -- Account
    status ENUM('active', 'trial', 'suspended', 'canceled') DEFAULT 'trial',
    plan_id BIGINT(20) UNSIGNED,
    subscription_amount DECIMAL(10,2),
    billing_cycle ENUM('monthly', 'yearly') DEFAULT 'monthly',

    -- Trial
    trial_enabled TINYINT(1) DEFAULT 1,
    trial_days INT DEFAULT 14,
    trial_ends_at DATETIME,

    -- Limits (inherited from plan)
    max_bookings INT,
    max_users INT,
    storage_gb INT,

    -- Domain
    primary_domain VARCHAR(255),
    white_label_enabled TINYINT(1) DEFAULT 0,

    -- Billing
    last_invoice_date DATE,
    next_billing_date DATE,
    total_revenue DECIMAL(10,2) DEFAULT 0,

    -- Timestamps
    activated_at DATETIME,
    suspended_at DATETIME,
    canceled_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX reseller_idx (reseller_id),
    INDEX status_idx (status),
    INDEX client_code_idx (client_code),
    INDEX next_billing_idx (next_billing_date),
    FOREIGN KEY (reseller_id) REFERENCES bkx_resellers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_reseller_commissions`
```sql
CREATE TABLE bkx_reseller_commissions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reseller_id BIGINT(20) UNSIGNED NOT NULL,
    client_id BIGINT(20) UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    -- Revenue
    client_revenue DECIMAL(10,2) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,

    -- Status
    status ENUM('pending', 'approved', 'paid', 'canceled') DEFAULT 'pending',
    approved_at DATETIME,
    paid_at DATETIME,
    payment_reference VARCHAR(255),

    -- Details
    calculation_data LONGTEXT,
    notes TEXT,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX reseller_idx (reseller_id),
    INDEX client_idx (client_id),
    INDEX period_idx (period_start, period_end),
    INDEX status_idx (status),
    FOREIGN KEY (reseller_id) REFERENCES bkx_resellers(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES bkx_reseller_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_custom_templates`
```sql
CREATE TABLE bkx_custom_templates (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id BIGINT(20) UNSIGNED NOT NULL,
    entity_type ENUM('platform', 'reseller', 'client') NOT NULL,

    template_name VARCHAR(200) NOT NULL,
    template_type ENUM('email', 'invoice', 'receipt', 'document', 'page') NOT NULL,
    template_slug VARCHAR(100),

    -- Content
    subject VARCHAR(500),
    html_content LONGTEXT,
    css_content LONGTEXT,
    variables LONGTEXT,

    -- Settings
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    version VARCHAR(20) DEFAULT '1.0',

    -- Metadata
    description TEXT,
    thumbnail_url VARCHAR(500),
    category VARCHAR(50),

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX entity_idx (entity_id, entity_type),
    INDEX type_idx (template_type),
    INDEX slug_idx (template_slug),
    INDEX active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_license_keys`
```sql
CREATE TABLE bkx_license_keys (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(255) NOT NULL UNIQUE,
    client_id BIGINT(20) UNSIGNED NOT NULL,
    reseller_id BIGINT(20) UNSIGNED NOT NULL,

    -- License Info
    license_type ENUM('trial', 'standard', 'premium', 'enterprise') NOT NULL,
    status ENUM('active', 'inactive', 'suspended', 'expired') DEFAULT 'inactive',

    -- Activation
    max_activations INT DEFAULT 1,
    activation_count INT DEFAULT 0,
    activated_domains TEXT,

    -- Validity
    issued_at DATETIME NOT NULL,
    expires_at DATETIME,
    last_validated DATETIME,

    -- Features
    features LONGTEXT,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX license_key_idx (license_key),
    INDEX client_idx (client_id),
    INDEX reseller_idx (reseller_id),
    INDEX status_idx (status),
    INDEX expires_idx (expires_at),
    FOREIGN KEY (client_id) REFERENCES bkx_reseller_clients(id) ON DELETE CASCADE,
    FOREIGN KEY (reseller_id) REFERENCES bkx_resellers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_api_keys`
```sql
CREATE TABLE bkx_api_keys (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id BIGINT(20) UNSIGNED NOT NULL,
    entity_type ENUM('reseller', 'client') NOT NULL,

    api_key VARCHAR(255) NOT NULL UNIQUE,
    api_secret VARCHAR(255),
    key_name VARCHAR(200),

    -- Permissions
    permissions LONGTEXT,
    allowed_endpoints TEXT,

    -- Rate Limiting
    rate_limit_per_hour INT DEFAULT 1000,
    rate_limit_per_day INT DEFAULT 10000,

    -- Status
    status ENUM('active', 'suspended', 'revoked') DEFAULT 'active',
    expires_at DATETIME,

    -- Usage Tracking
    last_used_at DATETIME,
    total_requests BIGINT DEFAULT 0,

    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX api_key_idx (api_key),
    INDEX entity_idx (entity_id, entity_type),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Platform Owner Settings

```php
[
    // White-Label Program
    'enable_white_label_program' => true,
    'enable_reseller_program' => true,
    'auto_approve_resellers' => false,
    'require_reseller_verification' => true,

    // Commission Settings
    'default_commission_rate' => 30,
    'commission_tiers' => [
        'basic' => 20,
        'silver' => 30,
        'gold' => 40,
        'platinum' => 50,
    ],
    'minimum_payout' => 100,
    'payout_frequency' => 'monthly',

    // Reseller Limits
    'default_max_clients' => 10,
    'default_max_domains' => 5,
    'allow_unlimited_branding' => true,
    'api_access_default' => true,

    // Domain Management
    'auto_provision_ssl' => true,
    'ssl_provider' => 'letsencrypt',
    'allow_custom_domains' => true,
    'domain_verification_required' => true,

    // Branding
    'allow_complete_rebranding' => true,
    'allow_custom_css' => true,
    'allow_custom_js' => true,
    'allow_logo_upload' => true,

    // Resources
    'provide_marketing_materials' => true,
    'provide_demo_accounts' => true,
    'provide_training' => true,
]
```

### Reseller Settings

```php
[
    // Branding
    'brand_name' => '',
    'logo_url' => '',
    'color_scheme' => [],
    'custom_css' => '',
    'custom_domain' => '',

    // Client Management
    'auto_provision_clients' => true,
    'default_trial_days' => 14,
    'default_client_plan' => 'starter',
    'allow_client_branding' => true,

    // Billing
    'billing_email' => '',
    'payment_method' => 'stripe',
    'auto_invoice' => true,
    'invoice_template_id' => null,

    // Features
    'api_access' => true,
    'webhook_url' => '',
    'custom_endpoints' => [],

    // Support
    'support_email' => '',
    'support_url' => '',
    'knowledge_base_url' => '',
]
```

---

## 7. User Interface Requirements

### Platform Owner Dashboard

1. **Reseller Management**
   - Reseller list with stats
   - Approval workflow
   - Commission management
   - Performance analytics
   - Payout processing

2. **Revenue Dashboard**
   - Total revenue
   - Revenue by reseller
   - Commission breakdown
   - Payout history
   - Growth metrics

3. **White-Label Configuration**
   - Global branding defaults
   - Template library
   - Asset management
   - Documentation portal

### Reseller Dashboard

1. **Overview**
   - Active clients count
   - Monthly revenue
   - Pending commission
   - Quick stats

2. **Client Management**
   - Client list table
   - Add new client
   - Client status overview
   - Usage monitoring
   - Billing management

3. **Branding Center**
   - Logo upload
   - Color scheme editor
   - Custom CSS editor
   - Template customization
   - Preview mode

4. **Domain Management**
   - Add custom domain
   - DNS configuration guide
   - SSL status
   - Domain verification

5. **Revenue & Commissions**
   - Revenue dashboard
   - Commission reports
   - Payout history
   - Invoice management

6. **Marketing Tools**
   - Demo environment access
   - Proposal generator
   - Marketing materials
   - Sales resources

### Client Admin Interface

1. **Branded Admin Panel**
   - Reseller branding applied
   - Custom logo and colors
   - White-labeled interface
   - Custom help documentation

2. **Subscription Management**
   - Current plan details
   - Usage statistics
   - Billing information
   - Upgrade options

---

## 8. Security Considerations

### Access Control
- Hierarchical permission system (Platform → Reseller → Client)
- API key authentication with scoped permissions
- Rate limiting per reseller and client
- Audit trail for all configuration changes
- Secure credential storage (encrypted)

### Data Isolation
- Complete separation between resellers
- Client data isolation within reseller scope
- Encrypted API keys and secrets
- Secure asset storage with access control

### Domain Security
- Domain ownership verification
- SSL certificate validation
- DNS hijacking prevention
- HTTPS enforcement

### Branding Security
- XSS prevention in custom CSS/JS
- Content Security Policy enforcement
- Sanitize uploaded files
- Malware scanning for uploaded assets

---

## 9. Testing Strategy

### Unit Tests
```php
- test_reseller_creation()
- test_commission_calculation()
- test_branding_application()
- test_domain_verification()
- test_ssl_provisioning()
- test_template_rendering()
- test_license_key_generation()
- test_api_key_validation()
```

### Integration Tests
```php
- test_complete_reseller_onboarding()
- test_client_provisioning_workflow()
- test_branding_inheritance()
- test_custom_domain_setup()
- test_commission_payout_flow()
- test_white_label_api_access()
```

### UI Tests
- Branding preview accuracy
- Template rendering consistency
- Admin panel customization
- Client interface white-labeling

---

## 10. Development Timeline

### Phase 1: Core Infrastructure (Weeks 1-3)
- [ ] Database schema
- [ ] Reseller management system
- [ ] Client management system
- [ ] Basic API framework

### Phase 2: Branding Engine (Weeks 4-5)
- [ ] Branding configuration system
- [ ] CSS/JS customization
- [ ] Logo and asset management
- [ ] Template system
- [ ] Preview functionality

### Phase 3: Domain Management (Week 6)
- [ ] Domain registration/mapping
- [ ] DNS configuration
- [ ] SSL provisioning
- [ ] Domain verification

### Phase 4: Commission System (Week 7)
- [ ] Commission calculation
- [ ] Revenue tracking
- [ ] Payout automation
- [ ] Reporting

### Phase 5: Template System (Week 8)
- [ ] Email template builder
- [ ] Document templates
- [ ] Template variables
- [ ] Preview system

### Phase 6: UI Development (Weeks 9-11)
- [ ] Platform owner dashboard
- [ ] Reseller dashboard
- [ ] Branding center
- [ ] Client management UI
- [ ] Marketing tools

### Phase 7: API & Customization (Week 12)
- [ ] Custom API endpoints
- [ ] Webhook system
- [ ] API documentation
- [ ] Developer portal

### Phase 8: Testing & QA (Weeks 13-14)
- [ ] Comprehensive testing
- [ ] Security audit
- [ ] Performance optimization
- [ ] Bug fixes

### Phase 9: Documentation (Week 15)
- [ ] User documentation
- [ ] API documentation
- [ ] Reseller guide
- [ ] Marketing materials

### Phase 10: Launch (Week 16)
- [ ] Beta testing
- [ ] Production deployment
- [ ] Support setup
- [ ] Marketing launch

**Total Estimated Timeline:** 16 weeks (4 months)

---

## 11. Success Metrics

### Technical Metrics
- Branding apply time < 2 seconds
- Domain verification success rate > 95%
- SSL provisioning success rate > 98%
- API response time < 300ms
- System uptime 99.9%

### Business Metrics
- Reseller activation rate > 40%
- Average clients per reseller > 5
- Reseller retention > 80% annually
- Average commission > $500/month
- Client satisfaction > 4.5/5

---

## 12. Known Limitations

1. **Mobile App Publishing:** Requires separate app store accounts
2. **Custom Code:** JavaScript execution has security restrictions
3. **Template Complexity:** Advanced template logic requires developer knowledge
4. **Domain Propagation:** DNS changes can take 24-48 hours
5. **SSL Renewal:** Requires domain accessibility for auto-renewal

---

## 13. Future Enhancements

### Version 2.0 Roadmap
- [ ] Mobile app builder (no-code)
- [ ] Advanced API gateway with GraphQL
- [ ] AI-powered template generator
- [ ] Marketplace for templates and extensions
- [ ] Multi-language admin interface
- [ ] Advanced analytics for resellers
- [ ] Automated marketing campaigns
- [ ] White-label mobile apps (iOS/Android)
- [ ] Partner API for third-party integrations
- [ ] Advanced fraud detection

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development
