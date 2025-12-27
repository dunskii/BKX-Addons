# WooCommerce Pro Integration Add-on - Development Documentation

## 1. Overview

**Add-on Name:** WooCommerce Pro Integration
**Price:** $129
**Category:** WordPress Ecosystem
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+
**WooCommerce Version:** 6.0+

### Description
Complete WooCommerce integration enabling advanced e-commerce capabilities for booking services. Sell products during booking process, manage inventory synchronization, handle recurring subscriptions, calculate shipping for physical products, and leverage WooCommerce's extensive payment gateway ecosystem.

### Value Proposition
- Combine service bookings with product sales in unified checkout
- Leverage WooCommerce's 100+ payment gateways
- Inventory synchronization between bookings and products
- Subscription management using WooCommerce Subscriptions
- Shipping calculations for physical add-on products
- Advanced reporting combining bookings and product sales
- Full WooCommerce ecosystem compatibility

---

## 2. Features & Requirements

### Core Features
1. **Unified Checkout Experience**
   - Combine service bookings with WooCommerce products
   - Seamless cart integration
   - Unified checkout process
   - Single payment for combined orders
   - Order management integration

2. **Product Sales During Booking**
   - Add products to booking cart
   - Related product suggestions
   - Upsell/cross-sell integration
   - Product bundles with services
   - Quantity-based pricing

3. **Inventory Management**
   - Sync booking slots with product stock
   - Resource-based inventory tracking
   - Staff equipment inventory
   - Automated stock updates
   - Low stock notifications
   - Backorder handling

4. **WooCommerce Subscriptions Integration**
   - Recurring service bookings
   - Subscription-based memberships
   - Variable subscription pricing
   - Subscription pause/resume
   - Proration handling
   - Subscription upgrades/downgrades

5. **Shipping Integration**
   - Calculate shipping for physical products
   - Multiple shipping methods
   - Zone-based shipping
   - Free shipping thresholds
   - Shipping class support
   - Real-time carrier rates

6. **Payment Gateway Ecosystem**
   - Access to 100+ WooCommerce payment gateways
   - PayPal, Stripe, Square integration
   - Buy Now Pay Later options
   - Cryptocurrency payments
   - Regional payment methods
   - Split payments support

7. **Advanced Reporting & Analytics**
   - Combined booking and product revenue
   - Product performance by booking type
   - Customer lifetime value
   - Inventory turnover reports
   - Subscription analytics
   - Export to WooCommerce reports

### User Roles & Permissions
- **Shop Manager:** Full e-commerce and booking access
- **Admin:** Complete configuration control
- **Staff:** View assigned bookings and inventory
- **Customer:** Book services and purchase products
- **Subscriber:** Access subscription-based bookings

---

## 3. Technical Specifications

### Technology Stack
- **WooCommerce Core:** 6.0+
- **WooCommerce Subscriptions:** 4.0+ (optional)
- **WooCommerce Bookings:** Compatibility layer
- **REST API:** WooCommerce REST API v3
- **Action Scheduler:** Background job processing
- **React:** Admin UI components

### Dependencies
- BookingX Core 2.0+
- WooCommerce 6.0+
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- WordPress 5.8+

### WordPress Hooks Architecture
```php
// Action Hooks
add_action('bkx_booking_created', 'sync_to_woocommerce_order', 10, 2);
add_action('woocommerce_checkout_order_processed', 'process_booking_in_order', 10, 3);
add_action('woocommerce_order_status_completed', 'confirm_booking_from_order', 10, 1);
add_action('woocommerce_order_status_cancelled', 'cancel_booking_from_order', 10, 1);
add_action('woocommerce_order_refunded', 'handle_booking_refund', 10, 2);
add_action('woocommerce_add_to_cart', 'add_booking_to_cart', 10, 6);
add_action('woocommerce_cart_item_removed', 'remove_booking_from_cart', 10, 2);
add_action('woocommerce_subscription_status_changed', 'update_recurring_booking', 10, 3);

// Filter Hooks
add_filter('woocommerce_cart_item_price', 'modify_booking_cart_price', 10, 3);
add_filter('woocommerce_get_cart_item_from_session', 'restore_booking_cart_data', 10, 3);
add_filter('woocommerce_add_cart_item_data', 'add_booking_cart_metadata', 10, 3);
add_filter('woocommerce_order_item_meta_start', 'display_booking_meta', 10, 4);
add_filter('woocommerce_product_is_in_stock', 'check_booking_availability', 10, 2);
add_filter('woocommerce_cart_needs_payment', 'require_payment_for_bookings', 10, 2);
add_filter('woocommerce_available_payment_gateways', 'filter_gateways_for_bookings', 10, 1);
```

### API Integration Points
```php
// WooCommerce REST API endpoints utilized
- POST /wp-json/wc/v3/orders
- GET /wp-json/wc/v3/products
- POST /wp-json/wc/v3/products/{id}/stock
- GET /wp-json/wc/v3/subscriptions
- POST /wp-json/wc/v3/orders/{id}/refunds
- GET /wp-json/wc/v3/shipping/zones
- GET /wp-json/wc/v3/reports/sales
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────────────────────────────┐
│           BookingX Core                     │
│  - Services                                 │
│  - Staff                                    │
│  - Availability                             │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│     WooCommerce Integration Layer           │
│  - Cart Synchronization                     │
│  - Order Processing                         │
│  - Product Mapping                          │
│  - Inventory Sync                           │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│           WooCommerce Core                  │
│  - Products                                 │
│  - Orders                                   │
│  - Cart & Checkout                          │
│  - Payment Gateways                         │
│  - Subscriptions                            │
│  - Shipping                                 │
└─────────────────────────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\WooCommerce;

class WooCommerceIntegration {
    - init()
    - register_hooks()
    - register_product_types()
    - setup_cart_integration()
    - setup_order_integration()
}

class BookingProductType extends WC_Product {
    - get_type()
    - is_purchasable()
    - get_price()
    - is_virtual()
    - get_booking_data()
    - calculate_price_with_addons()
}

class CartManager {
    - add_booking_to_cart()
    - remove_booking_from_cart()
    - update_booking_in_cart()
    - get_booking_cart_items()
    - validate_booking_cart_item()
    - calculate_booking_totals()
}

class OrderProcessor {
    - create_order_from_booking()
    - process_booking_in_order()
    - create_booking_from_order()
    - sync_order_status()
    - handle_order_refund()
}

class InventorySync {
    - sync_booking_to_stock()
    - check_resource_availability()
    - reserve_inventory()
    - release_inventory()
    - update_stock_status()
}

class SubscriptionManager {
    - create_recurring_booking()
    - handle_subscription_renewal()
    - pause_subscription_bookings()
    - cancel_subscription_bookings()
    - update_subscription_pricing()
}

class ShippingCalculator {
    - calculate_shipping_for_products()
    - get_available_shipping_methods()
    - validate_shipping_address()
    - get_shipping_zones()
}

class ProductBundleHandler {
    - create_booking_bundle()
    - add_products_to_bundle()
    - calculate_bundle_price()
    - validate_bundle_availability()
}
```

---

## 5. Database Schema

### Table: `bkx_wc_booking_products`
```sql
CREATE TABLE bkx_wc_booking_products (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_service_id BIGINT(20) UNSIGNED NOT NULL,
    wc_product_id BIGINT(20) UNSIGNED NOT NULL,
    sync_pricing TINYINT(1) DEFAULT 1,
    sync_inventory TINYINT(1) DEFAULT 0,
    inventory_mapping JSON,
    pricing_rules JSON,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX service_id_idx (booking_service_id),
    INDEX product_id_idx (wc_product_id),
    UNIQUE KEY service_product_unique (booking_service_id, wc_product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_wc_order_bookings`
```sql
CREATE TABLE bkx_wc_order_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    order_id BIGINT(20) UNSIGNED NOT NULL,
    order_item_id BIGINT(20) UNSIGNED NOT NULL,
    booking_status VARCHAR(50) NOT NULL,
    order_status VARCHAR(50) NOT NULL,
    sync_status TINYINT(1) DEFAULT 1,
    booking_data LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX order_id_idx (order_id),
    INDEX order_item_id_idx (order_item_id),
    UNIQUE KEY booking_order_unique (booking_id, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_wc_inventory_reservations`
```sql
CREATE TABLE bkx_wc_inventory_reservations (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    product_id BIGINT(20) UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    reserved_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'reserved',
    released_at DATETIME,
    INDEX booking_id_idx (booking_id),
    INDEX product_id_idx (product_id),
    INDEX status_idx (status),
    INDEX expires_at_idx (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_wc_subscriptions`
```sql
CREATE TABLE bkx_wc_subscriptions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    booking_service_id BIGINT(20) UNSIGNED NOT NULL,
    booking_frequency VARCHAR(50) NOT NULL,
    next_booking_date DATETIME,
    status VARCHAR(50) NOT NULL,
    total_bookings INT DEFAULT 0,
    auto_schedule TINYINT(1) DEFAULT 1,
    subscription_data LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX subscription_id_idx (subscription_id),
    INDEX customer_id_idx (customer_id),
    INDEX status_idx (status),
    INDEX next_booking_idx (next_booking_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// WooCommerce Integration Settings
[
    'enable_integration' => true,
    'cart_integration_mode' => 'unified|separate',
    'order_creation' => 'automatic|manual',
    'inventory_sync' => true,
    'sync_booking_status' => true,
    'create_product_for_services' => true,

    // Product Settings
    'product_category_for_bookings' => 0,
    'virtual_product_default' => true,
    'downloadable_product_default' => false,
    'tax_class' => 'standard',

    // Order Settings
    'order_status_on_booking' => 'processing',
    'order_status_on_completion' => 'completed',
    'auto_complete_virtual' => true,
    'send_order_emails' => true,

    // Subscription Settings
    'enable_subscriptions' => true,
    'auto_schedule_bookings' => true,
    'schedule_advance_days' => 7,
    'subscription_reminder_days' => 3,

    // Shipping Settings
    'enable_shipping' => true,
    'shipping_for_bookings' => false,
    'combine_shipping' => true,
    'free_shipping_threshold' => 0,

    // Inventory Settings
    'inventory_reservation_minutes' => 30,
    'auto_release_expired' => true,
    'overselling_prevention' => true,
    'stock_sync_frequency' => 'realtime|hourly|daily',
]
```

---

## 7. WordPress Hooks Implementation

### Action Hooks - Detailed Implementation

```php
/**
 * Sync booking to WooCommerce order on creation
 */
add_action('bkx_booking_created', 'bkx_wc_sync_booking_to_order', 10, 2);
function bkx_wc_sync_booking_to_order($booking_id, $booking_data) {
    $integration = new BookingX\Addons\WooCommerce\OrderProcessor();
    $integration->create_order_from_booking($booking_id, $booking_data);
}

/**
 * Process booking data during WooCommerce checkout
 */
add_action('woocommerce_checkout_order_processed', 'bkx_wc_process_booking_order', 10, 3);
function bkx_wc_process_booking_order($order_id, $posted_data, $order) {
    $processor = new BookingX\Addons\WooCommerce\OrderProcessor();
    $processor->process_booking_in_order($order_id, $order);
}

/**
 * Confirm booking when order is completed
 */
add_action('woocommerce_order_status_completed', 'bkx_wc_confirm_booking', 10, 1);
function bkx_wc_confirm_booking($order_id) {
    $bookings = bkx_wc_get_bookings_by_order($order_id);
    foreach ($bookings as $booking_id) {
        do_action('bkx_confirm_booking', $booking_id);
    }
}

/**
 * Cancel booking when order is cancelled
 */
add_action('woocommerce_order_status_cancelled', 'bkx_wc_cancel_booking', 10, 1);
function bkx_wc_cancel_booking($order_id) {
    $bookings = bkx_wc_get_bookings_by_order($order_id);
    foreach ($bookings as $booking_id) {
        do_action('bkx_cancel_booking', $booking_id);
    }
}

/**
 * Handle subscription renewal
 */
add_action('woocommerce_subscription_renewal_payment_complete', 'bkx_wc_renew_subscription_booking', 10, 2);
function bkx_wc_renew_subscription_booking($subscription, $last_order) {
    $manager = new BookingX\Addons\WooCommerce\SubscriptionManager();
    $manager->handle_subscription_renewal($subscription->get_id());
}

/**
 * Update inventory on booking status change
 */
add_action('bkx_booking_status_changed', 'bkx_wc_update_inventory', 10, 3);
function bkx_wc_update_inventory($booking_id, $old_status, $new_status) {
    $inventory = new BookingX\Addons\WooCommerce\InventorySync();
    $inventory->sync_booking_to_stock($booking_id, $new_status);
}
```

### Filter Hooks - Detailed Implementation

```php
/**
 * Modify booking price in cart
 */
add_filter('woocommerce_cart_item_price', 'bkx_wc_booking_cart_price', 10, 3);
function bkx_wc_booking_cart_price($price, $cart_item, $cart_item_key) {
    if (isset($cart_item['booking_data'])) {
        $booking_price = bkx_calculate_booking_price($cart_item['booking_data']);
        return wc_price($booking_price);
    }
    return $price;
}

/**
 * Add booking metadata to cart
 */
add_filter('woocommerce_add_cart_item_data', 'bkx_wc_add_booking_cart_metadata', 10, 3);
function bkx_wc_add_booking_cart_metadata($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['booking_data'])) {
        $cart_item_data['booking_data'] = sanitize_booking_data($_POST['booking_data']);
        $cart_item_data['booking_id'] = sanitize_text_field($_POST['booking_id']);
        $cart_item_data['unique_key'] = md5(microtime() . rand());
    }
    return $cart_item_data;
}

/**
 * Check booking availability as stock check
 */
add_filter('woocommerce_product_is_in_stock', 'bkx_wc_check_booking_availability', 10, 2);
function bkx_wc_check_booking_availability($is_in_stock, $product) {
    if (bkx_is_booking_product($product->get_id())) {
        $booking_data = bkx_get_cart_booking_data();
        if ($booking_data) {
            return bkx_check_slot_availability($booking_data);
        }
    }
    return $is_in_stock;
}

/**
 * Filter payment gateways based on booking requirements
 */
add_filter('woocommerce_available_payment_gateways', 'bkx_wc_filter_payment_gateways', 10, 1);
function bkx_wc_filter_payment_gateways($available_gateways) {
    if (bkx_cart_has_bookings()) {
        $booking_settings = bkx_get_payment_settings();
        if (isset($booking_settings['allowed_gateways'])) {
            foreach ($available_gateways as $gateway_id => $gateway) {
                if (!in_array($gateway_id, $booking_settings['allowed_gateways'])) {
                    unset($available_gateways[$gateway_id]);
                }
            }
        }
    }
    return $available_gateways;
}

/**
 * Modify order item meta display
 */
add_filter('woocommerce_order_item_meta_start', 'bkx_wc_display_booking_meta', 10, 4);
function bkx_wc_display_booking_meta($item_id, $item, $order, $plain_text) {
    $booking_id = wc_get_order_item_meta($item_id, '_booking_id', true);
    if ($booking_id) {
        $booking = bkx_get_booking($booking_id);
        echo '<div class="booking-details">';
        echo '<strong>' . __('Booking Details:', 'bookingx-wc') . '</strong><br>';
        echo __('Service:', 'bookingx-wc') . ' ' . $booking->get_service_name() . '<br>';
        echo __('Date:', 'bookingx-wc') . ' ' . $booking->get_date() . '<br>';
        echo __('Time:', 'bookingx-wc') . ' ' . $booking->get_time() . '<br>';
        echo '</div>';
    }
}
```

---

## 8. Shortcode System

### Booking Product Shortcodes

```php
/**
 * Display booking service as WooCommerce product
 *
 * Usage: [bkx_wc_service id="123" show_price="yes" show_cart="yes"]
 */
add_shortcode('bkx_wc_service', 'bkx_wc_service_shortcode');
function bkx_wc_service_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => 0,
        'show_price' => 'yes',
        'show_cart' => 'yes',
        'show_description' => 'yes',
        'layout' => 'default'
    ], $atts);

    ob_start();
    bkx_wc_render_service_product($atts);
    return ob_get_clean();
}

/**
 * Display booking form with WooCommerce cart integration
 *
 * Usage: [bkx_wc_booking_form service="123" show_related="yes"]
 */
add_shortcode('bkx_wc_booking_form', 'bkx_wc_booking_form_shortcode');
function bkx_wc_booking_form_shortcode($atts) {
    $atts = shortcode_atts([
        'service' => 0,
        'show_related' => 'yes',
        'show_upsells' => 'yes',
        'redirect' => 'cart'
    ], $atts);

    ob_start();
    bkx_wc_render_booking_form($atts);
    return ob_get_clean();
}

/**
 * Display products available with booking
 *
 * Usage: [bkx_wc_booking_products service="123" category="equipment"]
 */
add_shortcode('bkx_wc_booking_products', 'bkx_wc_booking_products_shortcode');
function bkx_wc_booking_products_shortcode($atts) {
    $atts = shortcode_atts([
        'service' => 0,
        'category' => '',
        'limit' => 4,
        'columns' => 4,
        'orderby' => 'menu_order'
    ], $atts);

    ob_start();
    bkx_wc_render_booking_products($atts);
    return ob_get_clean();
}

/**
 * Display customer bookings from WooCommerce orders
 *
 * Usage: [bkx_wc_my_bookings limit="10" status="confirmed"]
 */
add_shortcode('bkx_wc_my_bookings', 'bkx_wc_my_bookings_shortcode');
function bkx_wc_my_bookings_shortcode($atts) {
    $atts = shortcode_atts([
        'limit' => 10,
        'status' => 'all',
        'show_order_link' => 'yes'
    ], $atts);

    if (!is_user_logged_in()) {
        return '<p>' . __('Please login to view your bookings.', 'bookingx-wc') . '</p>';
    }

    ob_start();
    bkx_wc_render_customer_bookings($atts);
    return ob_get_clean();
}

/**
 * Display subscription booking schedule
 *
 * Usage: [bkx_wc_subscription_schedule subscription_id="123"]
 */
add_shortcode('bkx_wc_subscription_schedule', 'bkx_wc_subscription_schedule_shortcode');
function bkx_wc_subscription_schedule_shortcode($atts) {
    $atts = shortcode_atts([
        'subscription_id' => 0,
        'show_upcoming' => 'yes',
        'limit' => 5
    ], $atts);

    ob_start();
    bkx_wc_render_subscription_schedule($atts);
    return ob_get_clean();
}
```

---

## 9. User Interface Requirements

### Frontend Components

1. **Booking Product Display**
   - Service information
   - Pricing display
   - Availability calendar
   - Add to cart button
   - Related products section
   - Upsell products

2. **Unified Cart**
   - Booking items with details
   - Product items
   - Combined totals
   - Shipping calculator
   - Coupon application
   - Cart editing

3. **Checkout Integration**
   - Booking time slot confirmation
   - Contact information
   - Billing/shipping details
   - Payment method selection
   - Terms acceptance
   - Order review

4. **My Account - Bookings Tab**
   - Upcoming bookings
   - Past bookings
   - Booking details
   - Related order link
   - Cancellation options
   - Reschedule options

5. **Subscription Dashboard**
   - Active subscriptions
   - Upcoming bookings
   - Pause/resume controls
   - Schedule preferences
   - Payment history

### Backend Components

1. **Product Settings**
   - Link service to product
   - Pricing sync options
   - Inventory mapping
   - Tax class selection
   - Shipping settings

2. **Order Details - Booking Meta**
   - Booking reference
   - Service details
   - Appointment time
   - Staff assignment
   - Quick actions (reschedule, cancel)

3. **Inventory Management**
   - Stock by booking slot
   - Resource allocation
   - Reservation queue
   - Expiration management

4. **Subscription Management**
   - Booking frequency
   - Auto-scheduling settings
   - Next booking date
   - Schedule history

5. **Reporting Dashboard**
   - Combined revenue reports
   - Product performance by booking
   - Subscription analytics
   - Inventory turnover

---

## 10. Widget/Block Implementation

### Gutenberg Blocks

```php
/**
 * Register BookingX WooCommerce Blocks
 */
function bkx_wc_register_blocks() {
    // Booking Service Product Block
    register_block_type('bookingx/wc-service-product', [
        'attributes' => [
            'serviceId' => ['type' => 'number'],
            'showPrice' => ['type' => 'boolean', 'default' => true],
            'showDescription' => ['type' => 'boolean', 'default' => true],
            'buttonText' => ['type' => 'string', 'default' => 'Book Now'],
        ],
        'render_callback' => 'bkx_wc_render_service_block',
    ]);

    // Booking Products Grid Block
    register_block_type('bookingx/wc-products-grid', [
        'attributes' => [
            'serviceId' => ['type' => 'number'],
            'columns' => ['type' => 'number', 'default' => 3],
            'limit' => ['type' => 'number', 'default' => 6],
        ],
        'render_callback' => 'bkx_wc_render_products_grid_block',
    ]);

    // My Bookings Block
    register_block_type('bookingx/wc-my-bookings', [
        'attributes' => [
            'limit' => ['type' => 'number', 'default' => 10],
            'showOrderLink' => ['type' => 'boolean', 'default' => true],
        ],
        'render_callback' => 'bkx_wc_render_my_bookings_block',
    ]);
}
add_action('init', 'bkx_wc_register_blocks');
```

### Classic Widgets

```php
/**
 * BookingX WooCommerce Service Widget
 */
class BKX_WC_Service_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'bkx_wc_service',
            __('BookingX Service Product', 'bookingx-wc'),
            ['description' => __('Display a booking service as WooCommerce product', 'bookingx-wc')]
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        bkx_wc_render_service_widget($instance);

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $service_id = !empty($instance['service_id']) ? $instance['service_id'] : 0;
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">
                <?php _e('Title:', 'bookingx-wc'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo $this->get_field_id('title'); ?>"
                   name="<?php echo $this->get_field_name('title'); ?>"
                   type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('service_id'); ?>">
                <?php _e('Service:', 'bookingx-wc'); ?>
            </label>
            <select class="widefat"
                    id="<?php echo $this->get_field_id('service_id'); ?>"
                    name="<?php echo $this->get_field_name('service_id'); ?>">
                <?php echo bkx_get_services_options($service_id); ?>
            </select>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['service_id'] = (!empty($new_instance['service_id'])) ? absint($new_instance['service_id']) : 0;
        return $instance;
    }
}

/**
 * Register widgets
 */
function bkx_wc_register_widgets() {
    register_widget('BKX_WC_Service_Widget');
}
add_action('widgets_init', 'bkx_wc_register_widgets');
```

---

## 11. Security Considerations

### Data Security
- **Nonce Verification:** All AJAX requests verify WordPress nonces
- **Capability Checks:** Permission validation for order/booking access
- **Input Sanitization:** All user inputs sanitized and validated
- **SQL Injection Prevention:** Prepared statements for all queries
- **XSS Protection:** Output escaping for all user-generated content

### E-commerce Security
- **Payment Data:** Handled by WooCommerce payment gateways
- **Order Data:** Encrypted sensitive order information
- **Inventory Locking:** Prevent race conditions with database transactions
- **Price Manipulation:** Server-side price validation
- **Cart Tampering:** Validate cart data before checkout

### API Security
```php
// REST API endpoint security
add_action('rest_api_init', function() {
    register_rest_route('bookingx-wc/v1', '/booking/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'bkx_wc_get_booking_api',
        'permission_callback' => function($request) {
            return current_user_can('read_shop_orders');
        },
        'args' => [
            'id' => [
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);
});
```

---

## 12. Testing Strategy

### Unit Tests
```php
class WooCommerceIntegrationTest extends WP_UnitTestCase {

    public function test_add_booking_to_cart() {
        $service_id = $this->create_test_service();
        $product_id = $this->create_linked_product($service_id);

        $cart = WC()->cart;
        $booking_data = ['service_id' => $service_id, 'date' => '2025-12-01'];

        $cart_item_key = bkx_wc_add_booking_to_cart($product_id, $booking_data);

        $this->assertNotFalse($cart_item_key);
        $this->assertEquals(1, $cart->get_cart_contents_count());
    }

    public function test_create_order_with_booking() {
        $booking_id = $this->create_test_booking();
        $order_id = bkx_wc_create_order_from_booking($booking_id);

        $order = wc_get_order($order_id);
        $this->assertInstanceOf('WC_Order', $order);
        $this->assertEquals(1, count($order->get_items()));
    }

    public function test_inventory_sync() {
        $service_id = $this->create_test_service();
        $product_id = $this->create_linked_product($service_id);

        $product = wc_get_product($product_id);
        $initial_stock = $product->get_stock_quantity();

        $booking_id = $this->create_test_booking(['service_id' => $service_id]);
        bkx_wc_sync_booking_to_stock($booking_id, 'confirmed');

        $product = wc_get_product($product_id);
        $this->assertEquals($initial_stock - 1, $product->get_stock_quantity());
    }
}
```

### Integration Tests
```php
- test_complete_booking_checkout_flow()
- test_subscription_renewal_creates_booking()
- test_order_cancellation_cancels_booking()
- test_refund_releases_inventory()
- test_shipping_calculation_with_booking()
- test_cart_validation_for_unavailable_slots()
- test_payment_gateway_compatibility()
```

---

## 13. Performance Optimization

### Caching Strategy
```php
// Cache booking product associations
function bkx_wc_get_product_for_service($service_id) {
    $cache_key = 'bkx_wc_product_' . $service_id;
    $product_id = wp_cache_get($cache_key, 'bookingx');

    if (false === $product_id) {
        global $wpdb;
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT wc_product_id FROM {$wpdb->prefix}bkx_wc_booking_products
             WHERE booking_service_id = %d",
            $service_id
        ));
        wp_cache_set($cache_key, $product_id, 'bookingx', 3600);
    }

    return $product_id;
}

// Cache availability checks
function bkx_wc_check_slot_availability($service_id, $date, $time) {
    $cache_key = 'bkx_availability_' . md5($service_id . $date . $time);
    $available = wp_cache_get($cache_key, 'bookingx');

    if (false === $available) {
        $available = bkx_check_availability($service_id, $date, $time);
        wp_cache_set($cache_key, $available, 'bookingx', 300); // 5 minutes
    }

    return $available;
}
```

### Database Optimization
- Indexed queries on booking-order relationships
- Batch inventory updates
- Scheduled cleanup of expired reservations
- Archive old order-booking relationships

### Action Scheduler Integration
```php
// Schedule background jobs
function bkx_wc_schedule_inventory_sync() {
    if (false === as_next_scheduled_action('bkx_wc_sync_inventory')) {
        as_schedule_recurring_action(
            time(),
            HOUR_IN_SECONDS,
            'bkx_wc_sync_inventory',
            [],
            'bookingx-wc'
        );
    }
}

// Process scheduled inventory sync
add_action('bkx_wc_sync_inventory', 'bkx_wc_process_inventory_sync');
function bkx_wc_process_inventory_sync() {
    $sync = new BookingX\Addons\WooCommerce\InventorySync();
    $sync->batch_sync_all_bookings();
}
```

---

## 14. Internationalization

### Translation Support
```php
// Load plugin text domain
function bkx_wc_load_textdomain() {
    load_plugin_textdomain(
        'bookingx-wc',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'bkx_wc_load_textdomain');

// Translatable strings
__('Book Now', 'bookingx-wc');
__('Add booking to cart', 'bookingx-wc');
__('Booking confirmed', 'bookingx-wc');
__('Service', 'bookingx-wc');
__('Appointment Date', 'bookingx-wc');
__('Appointment Time', 'bookingx-wc');
```

### Currency Support
- Support all WooCommerce currencies
- Dynamic currency conversion
- Multi-currency plugin compatibility
- Currency symbol positioning

---

## 15. Documentation Requirements

### User Documentation
1. **Installation Guide**
   - Plugin installation
   - WooCommerce setup
   - Service-product linking
   - Payment gateway configuration

2. **User Guide**
   - Booking with products
   - Managing subscriptions
   - Viewing orders
   - Cancellation process

3. **Admin Guide**
   - Product configuration
   - Inventory management
   - Order processing
   - Subscription management
   - Reporting

### Developer Documentation
1. **Hook Reference**
   - All action hooks
   - All filter hooks
   - Parameters and examples

2. **API Reference**
   - Public functions
   - Class methods
   - REST API endpoints

3. **Integration Guide**
   - Custom product types
   - Payment gateway extensions
   - Theme customization
   - Third-party plugin compatibility

---

## 16. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema creation
- [ ] Plugin structure setup
- [ ] WooCommerce compatibility layer
- [ ] Basic hooks registration
- [ ] Settings page UI

### Phase 2: Cart Integration (Week 3-4)
- [ ] Custom product type
- [ ] Cart functionality
- [ ] Cart item display
- [ ] Price calculation
- [ ] Cart validation

### Phase 3: Checkout & Orders (Week 5-6)
- [ ] Checkout integration
- [ ] Order creation
- [ ] Booking-order linking
- [ ] Order status synchronization
- [ ] Email notifications

### Phase 4: Inventory Management (Week 7-8)
- [ ] Inventory sync system
- [ ] Resource allocation
- [ ] Reservation system
- [ ] Stock management
- [ ] Expiration handling

### Phase 5: Subscriptions (Week 9-10)
- [ ] WooCommerce Subscriptions integration
- [ ] Recurring booking creation
- [ ] Subscription management
- [ ] Auto-scheduling
- [ ] Renewal handling

### Phase 6: Shipping & Products (Week 11)
- [ ] Shipping calculations
- [ ] Product bundles
- [ ] Related products
- [ ] Upsells/cross-sells

### Phase 7: Frontend UI (Week 12-13)
- [ ] Booking forms
- [ ] My Account integration
- [ ] Subscription dashboard
- [ ] Cart customization
- [ ] Responsive design

### Phase 8: Backend UI (Week 14)
- [ ] Admin settings
- [ ] Order meta display
- [ ] Inventory dashboard
- [ ] Reporting integration

### Phase 9: Testing (Week 15-16)
- [ ] Unit testing
- [ ] Integration testing
- [ ] Payment gateway testing
- [ ] Subscription testing
- [ ] Performance testing

### Phase 10: Launch (Week 17-18)
- [ ] Documentation
- [ ] Beta testing
- [ ] Bug fixes
- [ ] Production release

**Total Estimated Timeline:** 18 weeks (4.5 months)

---

## 17. Maintenance & Support

### Update Schedule
- **Security Updates:** As needed
- **Bug Fixes:** Bi-weekly
- **Feature Updates:** Quarterly
- **WooCommerce Compatibility:** With each major WooCommerce release

### Compatibility Testing
- Test with latest WooCommerce version
- Test with popular payment gateways
- Test with popular themes
- Test with WooCommerce extensions

---

## 18. Success Metrics

### Technical Metrics
- Cart conversion rate > 85%
- Order processing time < 3 seconds
- Inventory sync accuracy > 99.9%
- Page load time < 2 seconds
- Zero payment failures due to integration

### Business Metrics
- Activation rate > 40%
- Monthly active rate > 75%
- Average order value increase > 25%
- Customer satisfaction > 4.7/5
- Support ticket volume < 3% of users

---

## 19. Known Limitations

1. **WooCommerce Dependency:** Requires WooCommerce to be active
2. **Product Type:** One service per product (not variable products)
3. **Subscription Plugin:** Requires WooCommerce Subscriptions for recurring
4. **Inventory Sync:** Realtime sync may impact performance on high-traffic sites
5. **Timezone Handling:** Uses WooCommerce timezone settings

---

## 20. Future Enhancements

### Version 2.0 Roadmap
- [ ] Variable product support for services
- [ ] Multiple booking slots per order
- [ ] Advanced bundle builder
- [ ] Dynamic pricing rules
- [ ] Gift certificates integration
- [ ] Affiliate tracking for bookings

### Version 3.0 Roadmap
- [ ] Multi-vendor support (Dokan, WCFM)
- [ ] Booking marketplace features
- [ ] Advanced analytics dashboard
- [ ] AI-powered upsell recommendations
- [ ] Mobile app integration

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development
