# BookingX Add-on Suite: Comprehensive Development Plan

## Executive Summary

This document provides a detailed technical plan for developing the 75 BookingX add-ons as outlined in the Development Priority Roadmap. It covers architecture, shared infrastructure, development standards, and implementation strategies.

**Total Portfolio:** 75 Add-ons across 13 categories
**Total Development Effort:** ~750 developer-weeks
**Recommended Timeline:** 36 months with parallel teams
**Total Marketplace Value:** $7,362

---

## Table of Contents

1. [Shared Infrastructure & Foundation](#1-shared-infrastructure--foundation)
2. [Add-on Architecture Standard](#2-add-on-architecture-standard)
3. [Phase 1: Foundation & Quick Wins](#3-phase-1-foundation--quick-wins)
4. [Phase 2: Competitive Differentiation](#4-phase-2-competitive-differentiation)
5. [Phase 3: Industry Vertical Expansion](#5-phase-3-industry-vertical-expansion)
6. [Phase 4: Analytics & Intelligence](#6-phase-4-analytics--intelligence)
7. [Phase 5: Omnichannel & Communications](#7-phase-5-omnichannel--communications)
8. [Phase 6: Enterprise & Advanced Features](#8-phase-6-enterprise--advanced-features)
9. [Development Standards & Best Practices](#9-development-standards--best-practices)
10. [Testing Strategy](#10-testing-strategy)
11. [Deployment & Release Process](#11-deployment--release-process)

---

## 1. Shared Infrastructure & Foundation

Before developing individual add-ons, we must build a shared foundation that all add-ons will utilize.

### 1.1 Add-on SDK (BKX Add-on Framework)

**Purpose:** Provide a standardized base for all add-ons with common functionality.

**Location:** `C:\Users\dunsk\Code\Booking X\Add-ons\_shared\bkx-addon-sdk`

#### SDK Components

```
bkx-addon-sdk/
├── src/
│   ├── Abstracts/
│   │   ├── AbstractAddon.php           # Base class for all add-ons
│   │   ├── AbstractPaymentGateway.php  # Payment gateway base
│   │   ├── AbstractIntegration.php     # Third-party integration base
│   │   ├── AbstractNotification.php    # Notification provider base
│   │   ├── AbstractCalendar.php        # Calendar provider base
│   │   └── AbstractAnalytics.php       # Analytics module base
│   ├── Traits/
│   │   ├── HasSettings.php             # Settings management
│   │   ├── HasLicense.php              # License validation
│   │   ├── HasWebhooks.php             # Webhook handling
│   │   ├── HasCron.php                 # Cron job management
│   │   ├── HasDatabase.php             # Database migrations
│   │   ├── HasRestApi.php              # REST API registration
│   │   └── HasAjax.php                 # AJAX handler registration
│   ├── Contracts/
│   │   ├── AddonInterface.php
│   │   ├── PaymentGatewayInterface.php
│   │   ├── IntegrationInterface.php
│   │   └── SettingsInterface.php
│   ├── Services/
│   │   ├── LicenseService.php          # EDD license management
│   │   ├── UpdateService.php           # Plugin updates
│   │   ├── LoggerService.php           # Centralized logging
│   │   ├── CacheService.php            # Caching utilities
│   │   ├── EncryptionService.php       # API key encryption
│   │   └── WebhookService.php          # Webhook management
│   ├── Admin/
│   │   ├── SettingsPage.php            # Settings page builder
│   │   ├── SettingsField.php           # Field types
│   │   └── AdminNotices.php            # Admin notices
│   ├── Database/
│   │   ├── Migration.php               # Migration runner
│   │   ├── Schema.php                  # Schema builder
│   │   └── Seeder.php                  # Data seeding
│   └── Utilities/
│       ├── Sanitizer.php               # Input sanitization
│       ├── Validator.php               # Validation helpers
│       ├── Currency.php                # Currency handling
│       ├── DateTime.php                # Date/time utilities
│       └── HttpClient.php              # API request wrapper
├── assets/
│   ├── css/
│   │   └── bkx-addon-admin.css         # Shared admin styles
│   └── js/
│       └── bkx-addon-admin.js          # Shared admin scripts
├── templates/
│   ├── admin/
│   │   ├── settings-page.php
│   │   └── license-form.php
│   └── emails/
│       └── base-template.php
└── composer.json
```

#### AbstractAddon Base Class

```php
<?php
namespace BookingX\AddonSDK\Abstracts;

abstract class AbstractAddon {

    protected string $addon_id;
    protected string $addon_name;
    protected string $version;
    protected string $text_domain;
    protected string $min_bkx_version = '2.0.0';
    protected string $plugin_file;

    // Traits for common functionality
    use \BookingX\AddonSDK\Traits\HasSettings;
    use \BookingX\AddonSDK\Traits\HasLicense;
    use \BookingX\AddonSDK\Traits\HasDatabase;

    /**
     * Initialize the add-on
     */
    public function init(): void {
        // Check BookingX is active
        if (!$this->check_dependencies()) {
            return;
        }

        // Check license
        if (!$this->is_license_valid() && !$this->is_in_trial()) {
            $this->show_license_notice();
            return;
        }

        // Run migrations
        $this->run_migrations();

        // Register with BookingX Framework
        $this->register_with_framework();

        // Initialize hooks
        $this->init_hooks();

        // Load admin
        if (is_admin()) {
            $this->init_admin();
        }

        // Load frontend
        $this->init_frontend();
    }

    /**
     * Check if BookingX core is active and compatible
     */
    protected function check_dependencies(): bool {
        if (!function_exists('BKX')) {
            add_action('admin_notices', [$this, 'missing_bookingx_notice']);
            return false;
        }

        if (version_compare(BKX()->version, $this->min_bkx_version, '<')) {
            add_action('admin_notices', [$this, 'incompatible_version_notice']);
            return false;
        }

        return true;
    }

    /**
     * Get add-on info for license system
     */
    public function get_addon_info(): array {
        return [
            'addon_name'  => $this->addon_name,
            'key_name'    => $this->addon_id . '_license_key',
            'status'      => $this->addon_id . '_license_status',
            'text_domain' => $this->text_domain,
        ];
    }

    /**
     * Register with BookingX Framework registries
     */
    abstract protected function register_with_framework(): void;

    /**
     * Initialize WordPress hooks
     */
    abstract protected function init_hooks(): void;

    /**
     * Initialize admin functionality
     */
    abstract protected function init_admin(): void;

    /**
     * Initialize frontend functionality
     */
    abstract protected function init_frontend(): void;

    /**
     * Get database migrations
     */
    abstract public function get_migrations(): array;
}
```

### 1.2 Shared Database Schema Patterns

All add-ons should follow consistent database patterns:

```sql
-- Standard table naming: {wp_prefix}bkx_{addon}_{entity}
-- Example: wp_bkx_stripe_transactions

-- Always include:
CREATE TABLE {$wpdb->prefix}bkx_{addon}_{entity} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT(20) UNSIGNED DEFAULT NULL,
    customer_id BIGINT(20) UNSIGNED DEFAULT NULL,

    -- Entity-specific columns

    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    metadata JSON DEFAULT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_booking_id (booking_id),
    KEY idx_customer_id (customer_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.3 License Server Setup

**Recommended Platform:** Easy Digital Downloads (EDD) with Software Licensing extension

**License Endpoints:**
- Activation: `https://bookingx.com/edd-sl/activate`
- Deactivation: `https://bookingx.com/edd-sl/deactivate`
- Status Check: `https://bookingx.com/edd-sl/check`

---

## 2. Add-on Architecture Standard

### 2.1 Standard Add-on File Structure

Each add-on follows this structure:

```
bkx-{addon-name}/
├── bkx-{addon-name}.php              # Main plugin file
├── composer.json                      # Dependencies
├── package.json                       # JS/CSS build
├── readme.txt                         # WordPress.org readme
├── CHANGELOG.md                       # Version history
├── uninstall.php                      # Clean uninstall
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   ├── js/
│   │   ├── admin.js
│   │   └── frontend.js
│   └── images/
├── includes/
│   ├── class-{addon}-loader.php      # Main loader class
│   ├── class-{addon}-activator.php   # Activation hooks
│   ├── class-{addon}-deactivator.php # Deactivation hooks
│   ├── Admin/
│   │   ├── class-{addon}-admin.php
│   │   └── class-{addon}-settings.php
│   ├── Frontend/
│   │   └── class-{addon}-frontend.php
│   ├── Api/
│   │   └── class-{addon}-rest-api.php
│   ├── Database/
│   │   ├── migrations/
│   │   │   └── 001-create-{entity}-table.php
│   │   └── class-{addon}-db.php
│   └── Services/
│       └── class-{addon}-service.php
├── templates/
│   ├── admin/
│   └── frontend/
├── languages/
│   └── bkx-{addon-name}.pot
└── tests/
    ├── Unit/
    └── Integration/
```

### 2.2 Main Plugin File Template

```php
<?php
/**
 * Plugin Name: BookingX - {Addon Name}
 * Plugin URI: https://bookingx.com/addons/{addon-slug}
 * Description: {Short description of the addon}
 * Version: 1.0.0
 * Author: Booking X
 * Author URI: https://dunskii.com
 * Text Domain: bkx-{addon-slug}
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

// Define constants
define('BKX_{ADDON}_VERSION', '1.0.0');
define('BKX_{ADDON}_FILE', __FILE__);
define('BKX_{ADDON}_PATH', plugin_dir_path(__FILE__));
define('BKX_{ADDON}_URL', plugin_dir_url(__FILE__));
define('BKX_{ADDON}_BASENAME', plugin_basename(__FILE__));

// Autoloader
require_once BKX_{ADDON}_PATH . 'vendor/autoload.php';

/**
 * Get addon info for BookingX license system
 */
function bkx_{addon_slug}_addon_info(): array {
    return [
        'addon_name'  => '{Addon Name}',
        'key_name'    => 'bkx_{addon_slug}_license_key',
        'status'      => 'bkx_{addon_slug}_license_status',
        'text_domain' => 'bkx-{addon-slug}',
    ];
}

/**
 * Initialize the addon
 */
function bkx_{addon_slug}_init(): void {
    // Load text domain
    load_plugin_textdomain(
        'bkx-{addon-slug}',
        false,
        dirname(BKX_{ADDON}_BASENAME) . '/languages'
    );

    // Initialize addon
    $addon = new \BookingX\{AddonNamespace}\Loader();
    $addon->init();
}
add_action('plugins_loaded', 'bkx_{addon_slug}_init', 20);

// Activation/Deactivation
register_activation_hook(__FILE__, ['BookingX\\{AddonNamespace}\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['BookingX\\{AddonNamespace}\\Deactivator', 'deactivate']);
```

### 2.3 Framework Integration Pattern

```php
<?php
namespace BookingX\StripePayments;

use BookingX\AddonSDK\Abstracts\AbstractAddon;

class Loader extends AbstractAddon {

    protected string $addon_id = 'bkx_stripe';
    protected string $addon_name = 'Stripe Payments';
    protected string $version = BKX_STRIPE_VERSION;
    protected string $text_domain = 'bkx-stripe-payments';

    protected function register_with_framework(): void {
        // Register payment gateway with BookingX Framework
        add_action('bookingx_register_payment_gateways', function($registry) {
            $registry->register('stripe', new Gateway\StripeGateway());
        });

        // Register webhook handler
        add_action('bookingx_webhook_stripe', [$this, 'handle_webhook']);
    }

    protected function init_hooks(): void {
        // Add Stripe-specific hooks
        add_filter('bkx_payment_methods', [$this, 'add_payment_method']);
        add_action('bkx_booking_confirmed', [$this, 'capture_payment']);
        add_action('bkx_booking_cancelled', [$this, 'handle_refund']);
    }

    protected function init_admin(): void {
        new Admin\StripeAdmin();
        new Admin\StripeSettings();
    }

    protected function init_frontend(): void {
        new Frontend\StripeCheckout();
    }

    public function get_migrations(): array {
        return [
            '1.0.0' => [
                Database\Migrations\CreateTransactionsTable::class,
                Database\Migrations\CreateRefundsTable::class,
            ],
        ];
    }
}
```

---

## 3. Phase 1: Foundation & Quick Wins

### Timeline: Months 1-6
### Add-ons: 14
### Teams Required: 3-4 parallel teams

---

### Wave 1.1: Critical Payment Infrastructure

**Duration:** Months 1-3 (12 weeks)
**Team Assignment:** 2 dedicated payment teams

#### 3.1.1 Stripe Payments ($99)

**Priority Score:** 95 | **Timeline:** 12 weeks

**Technical Architecture:**

```
bkx-stripe-payments/
├── includes/
│   ├── Gateway/
│   │   ├── StripeGateway.php           # Main gateway class
│   │   ├── PaymentIntentHandler.php    # Payment Intent API
│   │   ├── SetupIntentHandler.php      # Card saving
│   │   ├── SubscriptionHandler.php     # Recurring billing
│   │   └── WebhookHandler.php          # Stripe webhooks
│   ├── Checkout/
│   │   ├── CheckoutSession.php         # Stripe Checkout
│   │   ├── ElementsHandler.php         # Stripe Elements
│   │   └── PaymentRequestButton.php    # Apple/Google Pay
│   ├── Admin/
│   │   ├── StripeSettings.php          # Settings page
│   │   └── TransactionList.php         # Transaction viewer
│   ├── Api/
│   │   └── StripeRestApi.php           # REST endpoints
│   └── Database/
│       └── migrations/
│           ├── 001-create-transactions.php
│           ├── 002-create-customers.php
│           ├── 003-create-subscriptions.php
│           └── 004-create-webhooks.php
```

**Database Schema:**

```sql
-- Stripe Transactions
CREATE TABLE {$wpdb->prefix}bkx_stripe_transactions (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED DEFAULT NULL,
    stripe_customer_id VARCHAR(255) DEFAULT NULL,
    stripe_payment_intent_id VARCHAR(255) NOT NULL,
    stripe_charge_id VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    payment_method_type VARCHAR(50) DEFAULT NULL,
    last_four VARCHAR(4) DEFAULT NULL,
    card_brand VARCHAR(20) DEFAULT NULL,
    receipt_url TEXT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_payment_intent (stripe_payment_intent_id),
    KEY idx_booking_id (booking_id),
    KEY idx_stripe_customer (stripe_customer_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stripe Customers (for saved cards)
CREATE TABLE {$wpdb->prefix}bkx_stripe_customers (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    stripe_customer_id VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    default_payment_method VARCHAR(255) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_user_id (user_id),
    UNIQUE KEY idx_stripe_customer (stripe_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stripe Subscriptions (for recurring bookings)
CREATE TABLE {$wpdb->prefix}bkx_stripe_subscriptions (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    recurring_booking_id BIGINT(20) UNSIGNED NOT NULL,
    stripe_subscription_id VARCHAR(255) NOT NULL,
    stripe_customer_id VARCHAR(255) NOT NULL,
    stripe_price_id VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    current_period_start DATETIME DEFAULT NULL,
    current_period_end DATETIME DEFAULT NULL,
    cancel_at_period_end TINYINT(1) DEFAULT 0,
    canceled_at DATETIME DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_stripe_subscription (stripe_subscription_id),
    KEY idx_recurring_booking (recurring_booking_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Features Implementation:**

1. **Payment Intent Flow**
   - Create Payment Intent on checkout
   - Handle 3D Secure authentication
   - Capture vs authorize-only modes
   - Partial capture support

2. **Stripe Elements Integration**
   - Card Element with validation
   - Payment Request Button (Apple Pay, Google Pay)
   - Link (one-click checkout)

3. **Webhook Handling**
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
   - `customer.subscription.*`
   - `invoice.payment_failed`

4. **Admin Features**
   - Transaction history with search/filter
   - Refund interface
   - Webhook event log
   - Test mode toggle

**Development Milestones:**

| Week | Milestone | Deliverables |
|------|-----------|--------------|
| 1-2 | Core Gateway | Gateway class, API integration, basic checkout |
| 3-4 | Payment Intents | Create, confirm, capture payment intents |
| 5-6 | Stripe Elements | Frontend card form, validation, error handling |
| 7-8 | 3D Secure & SCA | Authentication flows, redirect handling |
| 9-10 | Webhooks & Admin | Webhook handler, admin interface, transaction list |
| 11-12 | Subscriptions & Polish | Recurring billing, testing, documentation |

---

#### 3.1.2 PayPal Pro ($89)

**Priority Score:** 88 | **Timeline:** 4 weeks

**Technical Architecture:**

```
bkx-paypal-pro/
├── includes/
│   ├── Gateway/
│   │   ├── PayPalProGateway.php       # Main gateway
│   │   ├── DoDirectPayment.php        # Direct credit card
│   │   ├── BillingAgreement.php       # Reference transactions
│   │   └── WebhookHandler.php
│   ├── Admin/
│   │   └── PayPalSettings.php
│   └── Database/
│       └── migrations/
│           └── 001-create-transactions.php
```

**Key Features:**
- Direct credit card processing (DoDirectPayment API)
- Reference transactions for recurring
- Fraud management filters integration
- PayPal Express Checkout fallback

**Development Milestones:**

| Week | Milestone |
|------|-----------|
| 1 | Core gateway, DoDirectPayment integration |
| 2 | Reference transactions, recurring support |
| 3 | Admin interface, error handling |
| 4 | Testing, fraud filters, documentation |

---

#### 3.1.3 Square Payments ($79)

**Priority Score:** 86 | **Timeline:** 5 weeks

**Key Features:**
- Square Web Payments SDK
- In-person + online unified
- Customer sync
- Inventory sync (for extras/products)
- Recurring payments (Card on File)

---

#### 3.1.4 Authorize.NET ($79)

**Priority Score:** 82 | **Timeline:** 5 weeks

**Key Features:**
- Accept.js tokenization
- Accept Hosted (hosted form option)
- Customer Information Manager (CIM)
- Advanced Fraud Detection Suite (AFDS)
- Automated Recurring Billing (ARB)

---

### Wave 1.2: Essential Booking Features

**Duration:** Months 2-4 (staggered starts)
**Team Assignment:** 1 dedicated feature team

#### 3.2.1 Booking Reminders ($59)

**Priority Score:** 92 | **Timeline:** 7 weeks

**Technical Architecture:**

```
bkx-booking-reminders/
├── includes/
│   ├── Reminders/
│   │   ├── ReminderScheduler.php       # Cron scheduling
│   │   ├── ReminderProcessor.php       # Send reminders
│   │   ├── ReminderTemplates.php       # Email/SMS templates
│   │   └── ReminderRules.php           # Timing rules
│   ├── Channels/
│   │   ├── EmailChannel.php
│   │   └── SmsChannel.php              # If SMS addon active
│   ├── Admin/
│   │   ├── ReminderSettings.php
│   │   └── ReminderTemplateEditor.php
│   └── Database/
│       └── migrations/
│           ├── 001-create-reminder-rules.php
│           └── 002-create-reminder-log.php
```

**Database Schema:**

```sql
-- Reminder Rules
CREATE TABLE {$wpdb->prefix}bkx_reminder_rules (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    trigger_type ENUM('before_booking', 'after_booking', 'before_start', 'after_end') NOT NULL,
    trigger_value INT NOT NULL,
    trigger_unit ENUM('minutes', 'hours', 'days') NOT NULL,
    channels JSON NOT NULL, -- ["email", "sms"]
    template_id BIGINT(20) UNSIGNED DEFAULT NULL,
    conditions JSON DEFAULT NULL, -- Service/seat filters
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reminder Log
CREATE TABLE {$wpdb->prefix}bkx_reminder_log (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    channel VARCHAR(50) NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    scheduled_at DATETIME NOT NULL,
    sent_at DATETIME DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rule_id (rule_id),
    KEY idx_booking_id (booking_id),
    KEY idx_scheduled_at (scheduled_at),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Features:**
- Multiple reminder timing rules
- Email and SMS channels
- Custom template editor with merge tags
- Smart scheduling (business hours only option)
- Timezone-aware delivery
- Reminder log and analytics

---

#### 3.2.2 Coupon Codes & Discounts ($69)

**Priority Score:** 90 | **Timeline:** 8 weeks

**Database Schema:**

```sql
-- Coupons
CREATE TABLE {$wpdb->prefix}bkx_coupons (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    description TEXT DEFAULT NULL,
    discount_type ENUM('percentage', 'fixed', 'fixed_service') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_booking_amount DECIMAL(10,2) DEFAULT NULL,
    max_discount DECIMAL(10,2) DEFAULT NULL,
    usage_limit INT DEFAULT NULL,
    usage_limit_per_user INT DEFAULT NULL,
    usage_count INT DEFAULT 0,
    valid_from DATETIME DEFAULT NULL,
    valid_until DATETIME DEFAULT NULL,
    applicable_services JSON DEFAULT NULL, -- Service IDs or null for all
    applicable_seats JSON DEFAULT NULL, -- Seat IDs or null for all
    exclude_services JSON DEFAULT NULL,
    first_booking_only TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_code (code),
    KEY idx_is_active (is_active),
    KEY idx_valid_dates (valid_from, valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coupon Usage
CREATE TABLE {$wpdb->prefix}bkx_coupon_usage (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED DEFAULT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_coupon_id (coupon_id),
    KEY idx_booking_id (booking_id),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gift Certificates
CREATE TABLE {$wpdb->prefix}bkx_gift_certificates (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL,
    initial_amount DECIMAL(10,2) NOT NULL,
    remaining_amount DECIMAL(10,2) NOT NULL,
    purchaser_email VARCHAR(255) NOT NULL,
    recipient_email VARCHAR(255) DEFAULT NULL,
    recipient_name VARCHAR(255) DEFAULT NULL,
    message TEXT DEFAULT NULL,
    purchase_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_code (code),
    KEY idx_purchaser_email (purchaser_email),
    KEY idx_recipient_email (recipient_email),
    KEY idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Features:**
- Percentage and fixed discounts
- Usage limits (total and per-user)
- Date range validity
- Service/seat restrictions
- Gift certificates with balance tracking
- Bulk coupon code generation
- Referral program integration
- Loyalty tier discounts

---

#### 3.2.3 Google Calendar Integration ($149)

**Priority Score:** 89 | **Timeline:** 9 weeks

**Technical Architecture:**

```
bkx-google-calendar/
├── includes/
│   ├── OAuth/
│   │   ├── GoogleOAuthHandler.php      # OAuth 2.0 flow
│   │   └── TokenManager.php            # Token refresh
│   ├── Sync/
│   │   ├── CalendarSync.php            # Main sync engine
│   │   ├── EventMapper.php             # BKX booking -> GCal event
│   │   ├── ConflictResolver.php        # Handle conflicts
│   │   └── BidirectionalSync.php       # Two-way sync
│   ├── Availability/
│   │   └── GCalAvailabilityProvider.php
│   ├── Admin/
│   │   ├── ConnectionManager.php       # Connect calendars
│   │   └── SyncSettings.php
│   └── Api/
│       └── GoogleCalendarRestApi.php
```

**Key Features:**
- Two-way synchronization
- Multiple calendar support per seat
- Conflict detection
- Busy/free time availability
- Custom event details mapping
- Color coding by service type
- Webhook-based real-time sync

---

#### 3.2.4 SMS Notifications Pro ($79)

**Priority Score:** 87 | **Timeline:** 11 weeks

**Key Features:**
- Multi-provider support (Twilio, MessageBird, Nexmo)
- Template builder with merge tags
- Delivery status tracking
- Opt-in/opt-out management
- Two-way SMS (appointment confirmation via reply)
- Cost estimation and budgeting

---

#### 3.2.5 User Profiles Advanced ($79)

**Priority Score:** 85 | **Timeline:** 9 weeks

**Key Features:**
- Enhanced customer profiles
- Booking history with filtering
- Favorite services/seats
- Custom profile fields
- Profile photo
- Communication preferences
- Family/group member profiles

---

### Wave 1.3: Revenue Accelerators

**Duration:** Months 4-6
**Team Assignment:** 2 teams (1 complex features, 1 simpler features)

#### 3.3.1 Recurring Bookings ($129)

**Priority Score:** 91 | **Timeline:** 16 weeks

**This is the most complex add-on in Phase 1.**

**Technical Architecture:**

```
bkx-recurring-bookings/
├── includes/
│   ├── Patterns/
│   │   ├── RecurrencePattern.php       # Abstract pattern
│   │   ├── DailyPattern.php
│   │   ├── WeeklyPattern.php
│   │   ├── BiweeklyPattern.php
│   │   ├── MonthlyPattern.php
│   │   └── CustomPattern.php
│   ├── Scheduler/
│   │   ├── InstanceGenerator.php       # Generate booking instances
│   │   ├── ConflictChecker.php         # Check availability
│   │   └── AutoScheduler.php           # Cron-based generation
│   ├── Payments/
│   │   ├── RecurringPaymentHandler.php # Payment integration
│   │   └── BulkPaymentProcessor.php    # Charge all at once option
│   ├── Management/
│   │   ├── RecurrenceManager.php       # CRUD operations
│   │   ├── InstanceModifier.php        # Modify single/all instances
│   │   └── CancellationHandler.php     # Handle cancellations
│   ├── Frontend/
│   │   ├── RecurringBookingForm.php    # Enhanced booking form
│   │   └── RecurrenceCalendar.php      # Visual calendar
│   └── Database/
│       └── migrations/
│           ├── 001-create-recurring-bookings.php
│           ├── 002-create-instances.php
│           └── 003-create-exclusions.php
```

**Database Schema:**

```sql
-- Recurring Booking Series
CREATE TABLE {$wpdb->prefix}bkx_recurring_bookings (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    seat_id BIGINT(20) UNSIGNED NOT NULL,
    base_id BIGINT(20) UNSIGNED NOT NULL,

    -- Recurrence Pattern
    pattern_type ENUM('daily', 'weekly', 'biweekly', 'monthly', 'custom') NOT NULL,
    pattern_interval INT NOT NULL DEFAULT 1,
    pattern_days JSON DEFAULT NULL, -- For weekly: ["monday", "wednesday"]
    pattern_day_of_month INT DEFAULT NULL, -- For monthly
    pattern_week_of_month INT DEFAULT NULL, -- For monthly (1st, 2nd, last)

    -- Time
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'UTC',

    -- Duration
    series_start_date DATE NOT NULL,
    series_end_date DATE DEFAULT NULL, -- NULL = no end
    occurrence_limit INT DEFAULT NULL, -- Alternative to end date

    -- Payment
    payment_type ENUM('per_booking', 'bulk_monthly', 'subscription') NOT NULL DEFAULT 'per_booking',
    payment_gateway VARCHAR(50) DEFAULT NULL,
    subscription_id VARCHAR(255) DEFAULT NULL, -- If using subscription payment

    -- Status
    status ENUM('active', 'paused', 'cancelled', 'completed') NOT NULL DEFAULT 'active',
    instances_generated_until DATE DEFAULT NULL,

    metadata JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customer_id (customer_id),
    KEY idx_seat_id (seat_id),
    KEY idx_status (status),
    KEY idx_series_dates (series_start_date, series_end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recurring Booking Instances
CREATE TABLE {$wpdb->prefix}bkx_recurring_instances (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    recurring_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED DEFAULT NULL, -- Links to actual booking
    instance_date DATE NOT NULL,
    instance_start_time TIME NOT NULL,
    instance_end_time TIME NOT NULL,
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'skipped') NOT NULL DEFAULT 'scheduled',
    is_modified TINYINT(1) DEFAULT 0, -- Instance differs from series
    modified_data JSON DEFAULT NULL, -- What was changed
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_recurring_date (recurring_id, instance_date),
    KEY idx_booking_id (booking_id),
    KEY idx_instance_date (instance_date),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recurring Exclusions (holidays, vacations)
CREATE TABLE {$wpdb->prefix}bkx_recurring_exclusions (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    recurring_id BIGINT(20) UNSIGNED NOT NULL,
    exclusion_date DATE NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_recurring_date (recurring_id, exclusion_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Development Milestones:**

| Week | Milestone |
|------|-----------|
| 1-2 | Database schema, pattern classes |
| 3-4 | Instance generator, conflict detection |
| 5-6 | CRUD operations, admin interface |
| 7-8 | Frontend booking form enhancement |
| 9-10 | Payment integration (per-booking) |
| 11-12 | Subscription payment integration |
| 13-14 | Modification handling (single/all) |
| 15-16 | Testing, edge cases, documentation |

---

#### 3.3.2 Booking Packages ($119)

**Priority Score:** 88 | **Timeline:** 14 weeks

**Key Features:**
- Session packages (10 sessions)
- Credit-based packages
- Unlimited packages with time limit
- Package sharing between family members
- Auto-renewal options
- Expiration management

---

#### 3.3.3 Multiple Service Booking ($79)

**Priority Score:** 86 | **Timeline:** 10 weeks

**Key Features:**
- Cart-like experience
- Service ordering (which comes first)
- Buffer time between services
- Combined pricing
- Single checkout for multiple services

---

#### 3.3.4 Booking Deposits & Partial Payments ($79)

**Priority Score:** 85 | **Timeline:** 9 weeks

**Key Features:**
- Percentage or fixed deposits
- Payment plans (installments)
- Balance due reminders
- Auto-charge remaining balance
- Grace periods and late fees

---

#### 3.3.5 Ratings & Reviews ($79)

**Priority Score:** 84 | **Timeline:** 9 weeks

**Key Features:**
- Post-booking review requests
- Star ratings (overall + categories)
- Review moderation
- Response capability
- Display on booking pages
- SEO schema markup

---

## 4. Phase 2: Competitive Differentiation

### Timeline: Months 7-12
### Add-ons: 11
### Teams Required: 2-3 parallel teams

---

### Wave 2.1: Marketing Automation (Months 7-9)

#### 4.1.1 Zapier Integration ($149)

**Priority Score:** 94 | **Timeline:** 12 weeks

**Technical Architecture:**

```
bkx-zapier/
├── includes/
│   ├── Triggers/
│   │   ├── BookingCreated.php
│   │   ├── BookingConfirmed.php
│   │   ├── BookingCancelled.php
│   │   ├── BookingCompleted.php
│   │   ├── CustomerCreated.php
│   │   └── PaymentReceived.php
│   ├── Actions/
│   │   ├── CreateBooking.php
│   │   ├── UpdateBooking.php
│   │   ├── CreateCustomer.php
│   │   └── SendNotification.php
│   ├── Searches/
│   │   ├── FindBooking.php
│   │   ├── FindCustomer.php
│   │   └── GetAvailability.php
│   ├── Auth/
│   │   └── ApiKeyAuth.php
│   └── Webhooks/
│       └── ZapierWebhookManager.php
```

**Key Features:**
- 10+ triggers (booking events, customer events, payments)
- 5+ actions (create/update bookings, customers)
- 3+ searches (find booking, customer, availability)
- REST Hook subscriptions
- OAuth or API key authentication
- Sample data for Zap building

---

#### 4.1.2 MailChimp Pro ($89)

**Priority Score:** 89 | **Timeline:** 12 weeks

**Key Features:**
- Audience sync
- Booking-triggered automations
- Segment by booking behavior
- E-commerce tracking
- Campaign templates

---

#### 4.1.3 ActiveCampaign ($129)

**Priority Score:** 82 | **Timeline:** 14 weeks

**Key Features:**
- Contact sync with custom fields
- Deal/pipeline integration
- Automation triggers
- Site tracking
- Event tracking

---

#### 4.1.4 Rewards Points System ($79)

**Priority Score:** 86 | **Timeline:** 10 weeks

**Database Schema:**

```sql
-- Points Balance
CREATE TABLE {$wpdb->prefix}bkx_points_balance (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    total_earned BIGINT DEFAULT 0,
    total_spent BIGINT DEFAULT 0,
    current_balance BIGINT DEFAULT 0,
    tier_level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    tier_points BIGINT DEFAULT 0, -- Points toward next tier
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_customer_id (customer_id),
    KEY idx_tier_level (tier_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Points Transactions
CREATE TABLE {$wpdb->prefix}bkx_points_transactions (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED DEFAULT NULL,
    transaction_type ENUM('earn', 'spend', 'expire', 'adjust') NOT NULL,
    points INT NOT NULL, -- Positive or negative
    description VARCHAR(255) DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customer_id (customer_id),
    KEY idx_booking_id (booking_id),
    KEY idx_transaction_type (transaction_type),
    KEY idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Features:**
- Points per dollar spent
- Bonus points events
- Tiered rewards (bronze, silver, gold, platinum)
- Points expiration
- Redemption for discounts
- Referral bonuses

---

### Wave 2.2: Advanced Calendar & Scheduling (Months 8-10)

#### 4.2.1 Outlook 365 Integration ($129)

**Timeline:** 7 weeks

Similar to Google Calendar but using Microsoft Graph API.

---

#### 4.2.2 iCal Integration ($79)

**Timeline:** 4 weeks

**Key Features:**
- Export bookings as .ics
- Subscribe URL for calendar apps
- Import external calendars for availability blocking

---

#### 4.2.3 Staff Breaks & Vacation Management ($69)

**Timeline:** 8 weeks

**Key Features:**
- Break scheduling (lunch, short breaks)
- Vacation/time-off management
- Holiday calendars
- Approval workflow for time-off
- Availability impact on bookings

---

#### 4.2.4 Waiting Lists & Queue Management ($69)

**Timeline:** 8 weeks

**Key Features:**
- Automatic waitlist when full
- Priority queuing options
- Auto-offer when slot opens
- Time-limited offers
- Queue position display

---

### Wave 2.3: Class & Group Bookings (Months 10-12)

#### 4.3.1 Class Bookings ($149)

**Priority Score:** 88 | **Timeline:** 20 weeks

**This is the most complex add-on in Phase 2.**

**Database Schema:**

```sql
-- Classes
CREATE TABLE {$wpdb->prefix}bkx_classes (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    category_id BIGINT(20) UNSIGNED DEFAULT NULL,
    instructor_id BIGINT(20) UNSIGNED DEFAULT NULL, -- Links to seat
    room_id BIGINT(20) UNSIGNED DEFAULT NULL,
    max_participants INT NOT NULL DEFAULT 10,
    min_participants INT DEFAULT 1,
    duration INT NOT NULL, -- Minutes
    price DECIMAL(10,2) NOT NULL,
    drop_in_allowed TINYINT(1) DEFAULT 1,
    late_booking_cutoff INT DEFAULT 0, -- Minutes before class
    cancellation_cutoff INT DEFAULT 60, -- Minutes before class
    waitlist_enabled TINYINT(1) DEFAULT 1,
    waitlist_max INT DEFAULT NULL,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    metadata JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_category_id (category_id),
    KEY idx_instructor_id (instructor_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class Schedule
CREATE TABLE {$wpdb->prefix}bkx_class_schedule (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id BIGINT(20) UNSIGNED NOT NULL,
    recurrence_rule VARCHAR(500) DEFAULT NULL, -- RRULE format
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    start_time TIME NOT NULL,
    day_of_week TINYINT DEFAULT NULL, -- 0=Sunday, 6=Saturday
    override_instructor_id BIGINT(20) UNSIGNED DEFAULT NULL,
    override_room_id BIGINT(20) UNSIGNED DEFAULT NULL,
    override_max_participants INT DEFAULT NULL,
    status ENUM('active', 'cancelled') DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_class_id (class_id),
    KEY idx_date_range (start_date, end_date),
    KEY idx_day_of_week (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class Instances (actual occurrences)
CREATE TABLE {$wpdb->prefix}bkx_class_instances (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id BIGINT(20) UNSIGNED NOT NULL,
    schedule_id BIGINT(20) UNSIGNED DEFAULT NULL,
    instance_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    instructor_id BIGINT(20) UNSIGNED DEFAULT NULL,
    room_id BIGINT(20) UNSIGNED DEFAULT NULL,
    max_participants INT NOT NULL,
    current_participants INT DEFAULT 0,
    waitlist_count INT DEFAULT 0,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    cancellation_reason TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_class_instance (class_id, instance_date, start_time),
    KEY idx_instance_date (instance_date),
    KEY idx_instructor_id (instructor_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class Bookings (attendees)
CREATE TABLE {$wpdb->prefix}bkx_class_bookings (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    instance_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED DEFAULT NULL, -- Links to main booking
    package_usage_id BIGINT(20) UNSIGNED DEFAULT NULL, -- If using a package
    status ENUM('booked', 'waitlisted', 'checked_in', 'completed', 'no_show', 'cancelled') DEFAULT 'booked',
    waitlist_position INT DEFAULT NULL,
    checked_in_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_instance_customer (instance_id, customer_id),
    KEY idx_customer_id (customer_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key Features:**
- Class definition with recurring schedules
- Instructor assignment
- Room/resource management
- Capacity management
- Waitlist with auto-enrollment
- QR code check-in
- Attendance tracking
- Drop-in vs. pre-booked
- Class series/courses

---

#### 4.3.2 Group Bookings & Quantity ($99)

**Timeline:** 13 weeks

**Key Features:**
- Multiple guests per booking
- Organizer management
- Group discounts
- Split payment option
- Guest registration

---

#### 4.3.3 Hold Date/Time Blocks ($59)

**Timeline:** 6 weeks

**Key Features:**
- Temporary holds during checkout
- Configurable hold duration
- Auto-release expired holds
- Hold visualization on calendar

---

## 5. Phase 3: Industry Vertical Expansion

### Timeline: Months 13-18
### Add-ons: 12
### Teams Required: 2-3 parallel teams

---

### Wave 3.1: Additional Payment Gateways (Months 13-14)

Quick implementations of regional payment gateways using the patterns established in Phase 1.

| Add-on | Timeline | Region |
|--------|----------|--------|
| PayPal Payflow | 9 weeks | Global |
| Google Pay | 2 weeks | Global (requires Stripe) |
| Regional Payment Hub | 6 weeks | Multi-region |

---

### Wave 3.2: Industry-Specific Solutions (Months 14-18)

#### 5.2.1 Beauty & Wellness Suite ($149)

**Timeline:** 20 weeks

**Specialized Features:**
- Service add-ons (hair color, extensions)
- Stylist preferences
- Before/after photo gallery
- Product recommendations
- Commission tracking
- Chair/station management
- Walk-in management

---

#### 5.2.2 Fitness & Sports Management ($129)

**Timeline:** 18 weeks

**Specialized Features:**
- Class pack credits
- Membership integration
- Equipment reservations
- Workout tracking
- Trainer scheduling
- Court/field booking

---

#### 5.2.3 Healthcare Practice Management ($199)

**Timeline:** 25 weeks

**Specialized Features:**
- Patient intake forms
- Insurance verification (basic)
- HIPAA-compliant notes
- Prescription reminders
- Provider credentials display
- Telehealth integration
- Consent forms

---

#### 5.2.4 Legal & Professional Services ($179)

**Timeline:** 22 weeks

**Specialized Features:**
- Client portal
- Document upload/signing
- Retainer tracking
- Conflict checking
- Matter management
- Billable time tracking
- Trust accounting basics

---

### Wave 3.3: WordPress Ecosystem (Months 16-18)

#### 5.3.1 WooCommerce Pro Integration ($129)

**Timeline:** 18 weeks

**Key Features:**
- Booking as WooCommerce product
- Cart integration
- WooCommerce checkout
- WooCommerce orders
- Coupon compatibility
- Product bundles with bookings
- Subscription products

---

#### 5.3.2 Yoast SEO Integration ($59)

**Timeline:** 10 weeks

**Key Features:**
- Schema.org markup for services
- Local business schema
- Breadcrumb integration
- XML sitemap inclusion
- Open Graph for bookings
- Twitter Cards

---

#### 5.3.3 Elementor Page Builder ($69)

**Timeline:** 12 weeks

**Key Features:**
- Booking form widget
- Service list widget
- Staff/seat grid widget
- Calendar widget
- Availability checker widget
- Custom styling controls

---

#### 5.3.4 Gravity Forms Integration ($59)

**Timeline:** 9 weeks

**Key Features:**
- Booking field for forms
- Form submission creates booking
- Pre-fill from form data
- Conditional booking fields
- Payment add-on compatibility

---

## 6. Phase 4: Analytics & Intelligence

### Timeline: Months 19-24
### Add-ons: 12
### Teams Required: 2-3 parallel teams

---

### Wave 4.1: Business Intelligence (Months 19-21)

#### 6.1.1 Business Intelligence Dashboard ($149)

**Timeline:** 16 weeks

**Key Features:**
- Executive KPI dashboard
- Revenue analytics
- Booking trends
- Staff utilization
- Customer lifetime value
- Cohort analysis
- Custom report builder
- Scheduled report emails
- Data export (CSV, Excel, PDF)

---

#### 6.1.2 Advanced Booking Analytics ($119)

**Timeline:** 12 weeks

**Key Features:**
- Booking funnel analysis
- Abandonment tracking
- Peak time analysis
- Service popularity
- Seasonal trends
- Forecasting

---

#### 6.1.3 Customer Journey Analytics ($119)

**Timeline:** 14 weeks

**Key Features:**
- Customer touchpoint tracking
- Conversion attribution
- Retention analysis
- Churn prediction
- Customer segmentation

---

#### 6.1.4 Marketing ROI Tracker ($69)

**Timeline:** 10 weeks

**Key Features:**
- UTM parameter tracking
- Campaign performance
- Coupon effectiveness
- Referral tracking
- Revenue attribution

---

### Wave 4.2: Financial Reporting & Accounting (Months 20-22)

#### 6.2.1 QuickBooks Integration ($119)

**Timeline:** 6 weeks

**Key Features:**
- Invoice sync
- Payment sync
- Customer sync
- Chart of accounts mapping
- Tax tracking

---

#### 6.2.2 Xero Integration ($99)

**Timeline:** 5 weeks

Similar to QuickBooks but for Xero API.

---

#### 6.2.3 Financial Reporting Suite ($99)

**Timeline:** 13 weeks

**Key Features:**
- P&L by service
- Revenue recognition
- Tax reports
- Accounts receivable aging
- Staff payroll data

---

#### 6.2.4 Staff Performance Analytics ($79)

**Timeline:** 11 weeks

**Key Features:**
- Utilization rates
- Revenue per staff
- Customer ratings by staff
- No-show rates by staff
- Commission calculations

---

### Wave 4.3: Advanced Pricing & Revenue Management (Months 22-24)

#### 6.3.1 Sliding Pricing (Peak/Off-Peak) ($89)

**Timeline:** 10 weeks

**Key Features:**
- Time-based pricing rules
- Day-of-week pricing
- Demand-based pricing
- Early bird discounts
- Last-minute pricing
- Special date pricing

---

#### 6.3.2 Mobile Bookings Advanced ($99)

**Timeline:** 12 weeks

**Key Features:**
- Mobile-optimized booking flow
- Travel time calculation
- Location-based services
- Mobile check-in
- Push notifications

---

#### 6.3.3 Bulk & Recurring Payments ($89)

**Timeline:** 11 weeks

**Key Features:**
- Batch payment processing
- Subscription billing
- Failed payment retry
- Payment plans
- Automatic receipts

---

#### 6.3.4 Advanced Booking Reports ($89)

**Timeline:** 12 weeks

**Key Features:**
- Custom report builder
- Scheduled reports
- Multi-format export
- Comparison reports
- Trend analysis

---

## 7. Phase 5: Omnichannel & Communications

### Timeline: Months 25-30
### Add-ons: 12
### Teams Required: 2-3 parallel teams

---

### Wave 5.1: Communication Channels (Months 25-27)

#### 7.1.1 Video Consultation Add-on ($129)

**Timeline:** 14 weeks

**Key Features:**
- Integrated video calls (Zoom, Twilio Video, Jitsi)
- Virtual waiting room
- Screen sharing
- Recording (with consent)
- Calendar integration
- Automatic meeting links

---

#### 7.1.2 WhatsApp Business Integration ($89)

**Timeline:** 8 weeks

**Key Features:**
- Booking notifications via WhatsApp
- Two-way messaging
- Template messages
- Quick replies
- Media sharing

---

#### 7.1.3 Live Chat Integration ($89)

**Timeline:** 12 weeks

**Key Features:**
- Embedded chat widget
- Booking from chat
- Chat history
- Canned responses
- Agent availability

---

#### 7.1.4 Advanced Email Templates ($69)

**Timeline:** 10 weeks

**Key Features:**
- Drag-and-drop email builder
- Custom templates per event
- Dynamic content blocks
- A/B testing
- Email analytics

---

#### 7.1.5 Push Notifications ($59)

**Timeline:** 9 weeks

**Key Features:**
- Web push notifications
- Mobile push (if PWA)
- Notification preferences
- Segmented notifications
- Scheduled notifications

---

### Wave 5.2: Voice & Social Platforms (Months 27-29)

#### 7.2.1 Google Assistant Integration ($149)

**Timeline:** 11 weeks

**Key Features:**
- Voice booking commands
- Availability queries
- Booking confirmations
- Reminders via Google Home
- Actions on Google integration

---

#### 7.2.2 Amazon Alexa Integration ($149)

**Timeline:** 11 weeks

Similar to Google Assistant but for Alexa Skills Kit.

---

#### 7.2.3 Reserve with Google Maps ($179)

**Timeline:** 12 weeks

**Key Features:**
- Google Business Profile integration
- "Book" button on Google Maps
- Availability sync
- Booking sync back to BookingX
- Review integration

---

#### 7.2.4 Facebook Booking Application ($129)

**Timeline:** 10 weeks

**Key Features:**
- Facebook Page integration
- Book Now button
- Messenger bot for booking
- Availability display
- Booking notifications in Messenger

---

### Wave 5.3: Team Collaboration (Months 28-30)

#### 7.3.1 Slack Integration ($49)

**Timeline:** 7 weeks

**Key Features:**
- Booking notifications to channel
- Daily digest
- Interactive booking management
- Slash commands

---

#### 7.3.2 Discord Notifications ($39)

**Timeline:** 6 weeks

**Key Features:**
- Webhook notifications
- Rich embeds
- Bot commands

---

#### 7.3.3 CRM Enhanced ($99)

**Timeline:** 11 weeks

**Key Features:**
- Contact management
- Interaction history
- Notes and tags
- Follow-up reminders
- Sales pipeline
- Email integration

---

## 8. Phase 6: Enterprise & Advanced Features

### Timeline: Months 31-36
### Add-ons: 14
### Teams Required: 3-4 parallel teams

---

### Wave 6.1: Enterprise Integrations (Months 31-33)

#### 8.1.1 Salesforce Connector ($199)

**Timeline:** 18 weeks

**Key Features:**
- Bidirectional contact sync
- Lead/opportunity creation
- Custom object mapping
- Booking as Activity
- Campaign attribution
- Reports in Salesforce

---

#### 8.1.2 HubSpot Integration ($179)

**Timeline:** 16 weeks

**Key Features:**
- Contact sync
- Deal creation
- Timeline events
- Marketing automation triggers
- Form integration
- Live chat handoff

---

#### 8.1.3 Multi-Location Management ($149)

**Timeline:** 14 weeks

**Key Features:**
- Centralized dashboard
- Location-specific services/staff
- Cross-location booking
- Location performance comparison
- Franchise management
- Resource sharing

---

#### 8.1.4 BKX to BKX Integration ($199)

**Timeline:** 15 weeks

**Key Features:**
- Inter-business booking
- Resource sharing marketplace
- Referral network
- Unified availability
- Cross-business payments

---

### Wave 6.2: Compliance & Security (Months 32-34)

#### 8.2.1 GDPR/CCPA Compliance Suite ($149)

**Timeline:** 15 weeks

**Key Features:**
- Consent management
- Data export (right to access)
- Data deletion (right to be forgotten)
- Cookie consent
- Privacy policy generator
- Data processing agreements

---

#### 8.2.2 Advanced Security & Audit ($129)

**Timeline:** 15 weeks

**Key Features:**
- Activity logging
- Audit trail
- Two-factor authentication
- IP allowlisting
- Session management
- Security alerts

---

#### 8.2.3 Data Backup & Recovery ($79)

**Timeline:** 14 weeks

**Key Features:**
- Automated backups
- Off-site storage
- Point-in-time recovery
- Backup encryption
- Restore testing

---

#### 8.2.4 PCI DSS Compliance Tools ($99)

**Timeline:** 14 weeks

**Key Features:**
- SAQ guidance
- Security scanning
- Vulnerability assessment
- Compliance reporting
- Policy templates

---

### Wave 6.3: Regional Payment Gateways (Months 33-35)

Quick implementations of remaining regional gateways:

| Add-on | Timeline | Region |
|--------|----------|--------|
| eWay | 4 weeks | Australia |
| CCAvenue | 3 weeks | India |
| PayTM | 3 weeks | India |
| DirectPay EU | 4 weeks | Europe |
| PayMaya | 3 weeks | Philippines |
| CitrusPay | 3 weeks | India |

---

## 9. Development Standards & Best Practices

### 9.1 Coding Standards

All add-ons must follow:

1. **WordPress Coding Standards**
   - PHP: [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
   - JavaScript: [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
   - CSS: [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)

2. **PSR Standards**
   - PSR-4: Autoloading
   - PSR-12: Extended Coding Style

3. **Security Best Practices**
   - Always sanitize inputs
   - Always escape outputs
   - Use prepared statements for SQL
   - Validate user capabilities
   - Use nonces for forms
   - Encrypt sensitive data at rest

### 9.2 Git Workflow

```
main (production)
├── develop (integration)
│   ├── feature/BKX-123-feature-name
│   ├── bugfix/BKX-456-bug-description
│   └── hotfix/BKX-789-critical-fix
└── release/v1.2.0
```

**Commit Message Format:**
```
[BKX-123] Short description (50 chars max)

- Detailed bullet point
- Another detail

Refs: #123
```

### 9.3 Documentation Requirements

Each add-on must include:

1. **README.md** - Installation and quick start
2. **CHANGELOG.md** - Version history
3. **docs/admin-guide.md** - Admin configuration
4. **docs/user-guide.md** - End user documentation
5. **docs/developer-guide.md** - API and hooks
6. **Inline code comments** - PHPDoc for all public methods

### 9.4 Internationalization

All strings must be translatable:

```php
// Text
__('Book Now', 'bkx-stripe-payments');

// Text with context
_x('Book', 'verb', 'bkx-stripe-payments');

// Pluralization
_n('1 booking', '%s bookings', $count, 'bkx-stripe-payments');

// With variables
sprintf(__('Hello, %s', 'bkx-stripe-payments'), $name);
```

---

## 10. Testing Strategy

### 10.1 Test Requirements

| Test Type | Coverage Target | Tools |
|-----------|-----------------|-------|
| Unit Tests | 80% | PHPUnit |
| Integration Tests | Key flows | PHPUnit + WordPress |
| E2E Tests | Critical paths | Playwright/Cypress |
| Manual Testing | All features | Test scripts |

### 10.2 Test Environment

```
Local Development:
├── Docker (wp-env or Local by Flywheel)
├── PHP 7.4 / 8.0 / 8.1
├── MySQL 5.7 / 8.0
└── WordPress 5.8+ / 6.x

CI/CD (GitHub Actions):
├── Matrix testing (PHP versions)
├── WordPress version matrix
├── Code style checks (PHPCS)
├── Static analysis (PHPStan)
└── Security scanning (SAST)
```

### 10.3 Test Scenarios

Each add-on must test:

1. **Installation/Activation**
   - Fresh install
   - Upgrade from previous version
   - Activation with missing dependencies
   - Multisite activation

2. **Core Functionality**
   - All CRUD operations
   - Edge cases
   - Error handling

3. **Integration**
   - BookingX Core interaction
   - Other add-on compatibility
   - Third-party API mocking

4. **Performance**
   - Database query efficiency
   - Memory usage
   - Load testing for high-traffic scenarios

---

## 11. Deployment & Release Process

### 11.1 Release Checklist

- [ ] All tests passing
- [ ] Code review approved
- [ ] Documentation updated
- [ ] CHANGELOG updated
- [ ] Version bumped
- [ ] Translation files updated
- [ ] Security scan passed
- [ ] Performance benchmarks met
- [ ] Compatibility tested with latest WordPress
- [ ] Compatibility tested with latest BookingX Core

### 11.2 Deployment Pipeline

```
1. Development Complete
   ↓
2. Pull Request → Code Review
   ↓
3. Merge to develop
   ↓
4. Automated Testing (CI)
   ↓
5. QA Testing on Staging
   ↓
6. Release Branch Created
   ↓
7. Final QA Sign-off
   ↓
8. Merge to main
   ↓
9. Tag Release
   ↓
10. Build & Package
    ↓
11. Upload to License Server
    ↓
12. Update Checker Notified
    ↓
13. Customers Receive Update
```

### 11.3 Version Numbering

Semantic Versioning (SemVer):
- **MAJOR.MINOR.PATCH** (e.g., 1.2.3)
- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes

---

## Summary

This comprehensive development plan covers all 75 BookingX add-ons across 6 phases over 36 months. Key takeaways:

1. **Build the SDK first** - Invest in shared infrastructure before individual add-ons
2. **Start with payments** - Revenue-generating add-ons fund further development
3. **Maintain quality** - Testing and documentation are non-negotiable
4. **Plan for scale** - Architecture decisions now affect long-term maintainability
5. **Listen to customers** - Adjust roadmap based on real feedback

The add-on suite represents a significant investment but positions BookingX as a comprehensive booking platform capable of serving diverse industries and use cases.

---

**Document Version:** 1.0
**Created:** 2025-12-26
**Author:** BookingX Development Team
**Status:** Ready for Implementation
