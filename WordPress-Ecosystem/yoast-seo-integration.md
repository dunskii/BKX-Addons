# Yoast SEO Integration Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Yoast SEO Integration
**Price:** $59
**Category:** WordPress Ecosystem
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+
**Yoast SEO Version:** 19.0+

### Description
Comprehensive Yoast SEO integration for BookingX services, locations, and staff pages. Automatically generates schema.org markup for service bookings, optimizes OpenGraph tags for social sharing, creates SEO-friendly breadcrumbs, and adds booking services to XML sitemaps for improved search engine visibility.

### Value Proposition
- Automatic schema.org markup for local business and services
- Enhanced social media sharing with booking-specific OpenGraph tags
- SEO-optimized URLs and breadcrumbs for booking pages
- XML sitemap integration for all booking services
- Rich snippets for service listings in search results
- Google Knowledge Panel optimization
- Local SEO improvements for multi-location bookings

---

## 2. Features & Requirements

### Core Features
1. **Schema.org Markup**
   - LocalBusiness schema for service providers
   - Service schema for individual services
   - Schedule schema for availability
   - AggregateRating schema for reviews
   - Organization schema
   - Person schema for staff profiles
   - Event schema for classes/appointments

2. **OpenGraph Optimization**
   - Service-specific OG tags
   - Dynamic image generation for services
   - Booking availability status
   - Pricing information
   - Staff profile OG tags
   - Location-specific tags

3. **SEO-Friendly Breadcrumbs**
   - Service category hierarchy
   - Location-based breadcrumbs
   - Staff profile breadcrumbs
   - Booking confirmation breadcrumbs
   - Customizable breadcrumb structure

4. **XML Sitemap Integration**
   - Service pages in sitemap
   - Location pages in sitemap
   - Staff profile pages
   - Service category pages
   - Priority and frequency settings
   - Image sitemap for service galleries

5. **Meta Data Optimization**
   - Dynamic title templates
   - Meta description templates
   - Canonical URL management
   - Robots meta tags
   - Keywords optimization

6. **Social Media Cards**
   - Twitter Card markup
   - Facebook OG tags
   - LinkedIn optimization
   - Pinterest Rich Pins
   - Dynamic social images

7. **Local SEO**
   - NAP (Name, Address, Phone) consistency
   - Google My Business integration
   - Multi-location optimization
   - Local business hours markup
   - Service area definition

### User Roles & Permissions
- **SEO Manager:** Full SEO configuration access
- **Admin:** Complete control over SEO settings
- **Editor:** Edit meta data for services
- **Staff:** View SEO recommendations
- **Customer:** No SEO backend access

---

## 3. Technical Specifications

### Technology Stack
- **Yoast SEO:** 19.0+
- **Schema.org:** Latest specification
- **OpenGraph Protocol:** 1.0
- **JSON-LD:** For structured data
- **PHP:** 7.4+ (8.0+ recommended)

### Dependencies
- BookingX Core 2.0+
- Yoast SEO 19.0+ (Free or Premium)
- WordPress 5.8+
- PHP JSON extension

### WordPress Hooks Architecture
```php
// Yoast SEO Hooks
add_filter('wpseo_schema_graph_pieces', 'bkx_add_booking_schema', 11, 2);
add_filter('wpseo_opengraph_type', 'bkx_set_opengraph_type', 10, 1);
add_filter('wpseo_opengraph_title', 'bkx_opengraph_title', 10, 1);
add_filter('wpseo_opengraph_desc', 'bkx_opengraph_description', 10, 1);
add_filter('wpseo_opengraph_image', 'bkx_opengraph_image', 10, 1);
add_filter('wpseo_twitter_card_type', 'bkx_twitter_card_type', 10, 1);
add_filter('wpseo_breadcrumb_links', 'bkx_breadcrumb_links', 10, 1);
add_filter('wpseo_sitemap_entry', 'bkx_sitemap_entry', 10, 3);
add_filter('wpseo_metadesc', 'bkx_meta_description', 10, 1);
add_filter('wpseo_title', 'bkx_page_title', 10, 1);
add_filter('wpseo_canonical', 'bkx_canonical_url', 10, 1);

// BookingX Specific Hooks
add_action('bkx_service_updated', 'bkx_yoast_reindex_service', 10, 1);
add_action('bkx_location_updated', 'bkx_yoast_reindex_location', 10, 1);
add_action('bkx_staff_updated', 'bkx_yoast_reindex_staff', 10, 1);
```

### Schema.org Types Implemented
```php
// Primary schema types
- LocalBusiness
- Service
- Schedule
- AggregateRating
- Review
- Organization
- Person
- PostalAddress
- GeoCoordinates
- OpeningHoursSpecification
- Offer
- PriceSpecification
- Event (for classes)
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────────────────────────────┐
│           BookingX Core                     │
│  - Services                                 │
│  - Locations                                │
│  - Staff                                    │
│  - Reviews                                  │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│     Yoast SEO Integration Layer             │
│  - Schema Generator                         │
│  - OpenGraph Manager                        │
│  - Breadcrumb Builder                       │
│  - Sitemap Handler                          │
│  - Meta Data Optimizer                      │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│           Yoast SEO Core                    │
│  - Schema Output                            │
│  - OpenGraph Tags                           │
│  - XML Sitemap                              │
│  - Breadcrumbs                              │
└─────────────────────────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\YoastSEO;

class YoastSEOIntegration {
    - init()
    - register_hooks()
    - check_yoast_version()
    - setup_schema_integration()
}

class SchemaGenerator {
    - add_local_business_schema()
    - add_service_schema()
    - add_schedule_schema()
    - add_rating_schema()
    - add_person_schema()
    - add_organization_schema()
    - generate_json_ld()
}

class OpenGraphManager {
    - set_og_type()
    - set_og_title()
    - set_og_description()
    - set_og_image()
    - set_og_url()
    - add_custom_og_tags()
}

class BreadcrumbBuilder {
    - build_service_breadcrumbs()
    - build_location_breadcrumbs()
    - build_staff_breadcrumbs()
    - build_category_breadcrumbs()
    - customize_breadcrumb_separator()
}

class SitemapHandler {
    - add_services_to_sitemap()
    - add_locations_to_sitemap()
    - add_staff_to_sitemap()
    - set_priority()
    - set_frequency()
    - add_images_to_sitemap()
}

class MetaDataOptimizer {
    - generate_title()
    - generate_description()
    - set_canonical_url()
    - set_robots_meta()
    - add_keywords()
}

class LocalSEOManager {
    - format_nap_data()
    - add_business_hours()
    - add_service_area()
    - sync_with_google_business()
}
```

---

## 5. Database Schema

### Table: `bkx_yoast_seo_meta`
```sql
CREATE TABLE bkx_yoast_seo_meta (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id BIGINT(20) UNSIGNED NOT NULL,
    object_type VARCHAR(50) NOT NULL,
    meta_title VARCHAR(255),
    meta_description TEXT,
    focus_keyword VARCHAR(255),
    canonical_url VARCHAR(500),
    og_title VARCHAR(255),
    og_description TEXT,
    og_image_id BIGINT(20) UNSIGNED,
    twitter_title VARCHAR(255),
    twitter_description TEXT,
    twitter_image_id BIGINT(20) UNSIGNED,
    schema_type VARCHAR(50),
    schema_data LONGTEXT,
    robots_index VARCHAR(20) DEFAULT 'index',
    robots_follow VARCHAR(20) DEFAULT 'follow',
    sitemap_priority DECIMAL(2,1) DEFAULT 0.5,
    sitemap_frequency VARCHAR(20) DEFAULT 'weekly',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX object_idx (object_id, object_type),
    INDEX keyword_idx (focus_keyword),
    INDEX robots_idx (robots_index, robots_follow)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_yoast_breadcrumbs`
```sql
CREATE TABLE bkx_yoast_breadcrumbs (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id BIGINT(20) UNSIGNED NOT NULL,
    object_type VARCHAR(50) NOT NULL,
    breadcrumb_path TEXT NOT NULL,
    breadcrumb_json JSON,
    parent_id BIGINT(20) UNSIGNED,
    hierarchy_level INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX object_idx (object_id, object_type),
    INDEX parent_idx (parent_id),
    INDEX level_idx (hierarchy_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_yoast_sitemap_cache`
```sql
CREATE TABLE bkx_yoast_sitemap_cache (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sitemap_type VARCHAR(50) NOT NULL,
    object_id BIGINT(20) UNSIGNED NOT NULL,
    url VARCHAR(500) NOT NULL,
    last_modified DATETIME NOT NULL,
    priority DECIMAL(2,1) DEFAULT 0.5,
    change_frequency VARCHAR(20) DEFAULT 'weekly',
    image_urls TEXT,
    is_active TINYINT(1) DEFAULT 1,
    INDEX sitemap_type_idx (sitemap_type),
    INDEX object_id_idx (object_id),
    INDEX active_idx (is_active),
    INDEX modified_idx (last_modified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Yoast SEO Integration Settings
[
    'enable_schema' => true,
    'enable_opengraph' => true,
    'enable_breadcrumbs' => true,
    'enable_sitemap' => true,

    // Schema Settings
    'schema_organization_name' => '',
    'schema_organization_logo' => '',
    'schema_business_type' => 'LocalBusiness',
    'enable_service_schema' => true,
    'enable_rating_schema' => true,
    'enable_schedule_schema' => true,

    // OpenGraph Settings
    'og_default_image' => '',
    'og_image_size' => [1200, 630],
    'enable_pricing_in_og' => true,
    'enable_availability_in_og' => true,

    // Breadcrumb Settings
    'breadcrumb_separator' => '/',
    'breadcrumb_home_text' => 'Home',
    'show_service_category_in_breadcrumb' => true,
    'show_location_in_breadcrumb' => true,

    // Sitemap Settings
    'services_sitemap_priority' => 0.8,
    'services_sitemap_frequency' => 'weekly',
    'locations_sitemap_priority' => 0.7,
    'staff_sitemap_priority' => 0.6,
    'exclude_private_services' => true,
    'include_service_images' => true,

    // Meta Title Templates
    'service_title_template' => '%%title%% | %%sitename%%',
    'location_title_template' => '%%title%% - %%location%% | %%sitename%%',
    'staff_title_template' => '%%name%% - %%role%% | %%sitename%%',

    // Meta Description Templates
    'service_description_template' => '%%description%% Book now at %%sitename%%.',
    'location_description_template' => 'Services at %%location%%. %%services_list%%',

    // Local SEO
    'enable_local_seo' => true,
    'business_hours_schema' => true,
    'service_area_schema' => true,
]
```

---

## 7. Schema.org Implementation

### Local Business Schema
```php
/**
 * Generate Local Business Schema
 */
class LocalBusinessSchema implements WPSEO_Graph_Piece {

    public function generate() {
        $location = bkx_get_primary_location();

        $schema = [
            '@type' => 'LocalBusiness',
            '@id' => get_site_url() . '#localbusiness',
            'name' => get_bloginfo('name'),
            'image' => $this->get_business_image(),
            'telephone' => $location->phone,
            'priceRange' => $this->get_price_range(),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $location->address,
                'addressLocality' => $location->city,
                'addressRegion' => $location->state,
                'postalCode' => $location->zip,
                'addressCountry' => $location->country,
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
            ],
            'openingHoursSpecification' => $this->get_opening_hours(),
            'aggregateRating' => $this->get_aggregate_rating(),
        ];

        return $schema;
    }

    private function get_opening_hours() {
        $hours = bkx_get_business_hours();
        $specifications = [];

        foreach ($hours as $day => $schedule) {
            if ($schedule['is_open']) {
                $specifications[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ucfirst($day),
                    'opens' => $schedule['open'],
                    'closes' => $schedule['close'],
                ];
            }
        }

        return $specifications;
    }
}
```

### Service Schema
```php
/**
 * Generate Service Schema for individual services
 */
class ServiceSchema implements WPSEO_Graph_Piece {

    private $service_id;

    public function __construct($service_id) {
        $this->service_id = $service_id;
    }

    public function generate() {
        $service = bkx_get_service($this->service_id);

        $schema = [
            '@type' => 'Service',
            '@id' => get_permalink($this->service_id) . '#service',
            'name' => $service->name,
            'description' => $service->description,
            'image' => get_the_post_thumbnail_url($this->service_id, 'full'),
            'provider' => [
                '@id' => get_site_url() . '#localbusiness',
            ],
            'serviceType' => $service->category,
            'offers' => [
                '@type' => 'Offer',
                'price' => $service->price,
                'priceCurrency' => get_option('bookingx_currency', 'USD'),
                'availability' => $this->get_availability_status(),
                'url' => get_permalink($this->service_id),
            ],
            'aggregateRating' => $this->get_service_rating(),
        ];

        if ($service->duration) {
            $schema['duration'] = 'PT' . $service->duration . 'M';
        }

        return $schema;
    }

    private function get_availability_status() {
        $is_available = bkx_check_service_availability($this->service_id);
        return $is_available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
    }
}
```

### Person Schema (Staff Profiles)
```php
/**
 * Generate Person Schema for staff profiles
 */
class PersonSchema implements WPSEO_Graph_Piece {

    private $staff_id;

    public function __construct($staff_id) {
        $this->staff_id = $staff_id;
    }

    public function generate() {
        $staff = bkx_get_staff($this->staff_id);

        $schema = [
            '@type' => 'Person',
            '@id' => get_permalink($this->staff_id) . '#person',
            'name' => $staff->name,
            'description' => $staff->bio,
            'image' => get_the_post_thumbnail_url($this->staff_id, 'full'),
            'jobTitle' => $staff->role,
            'worksFor' => [
                '@id' => get_site_url() . '#organization',
            ],
        ];

        if ($staff->email) {
            $schema['email'] = $staff->email;
        }

        if ($staff->phone) {
            $schema['telephone'] = $staff->phone;
        }

        return $schema;
    }
}
```

### Aggregate Rating Schema
```php
/**
 * Generate Aggregate Rating Schema
 */
private function get_aggregate_rating() {
    $reviews = bkx_get_service_reviews($this->service_id);

    if (empty($reviews)) {
        return null;
    }

    $total = count($reviews);
    $sum = array_sum(array_column($reviews, 'rating'));
    $average = $sum / $total;

    return [
        '@type' => 'AggregateRating',
        'ratingValue' => number_format($average, 1),
        'reviewCount' => $total,
        'bestRating' => 5,
        'worstRating' => 1,
    ];
}
```

---

## 8. OpenGraph Implementation

### Service OpenGraph Tags
```php
/**
 * Set OpenGraph tags for booking services
 */
add_filter('wpseo_opengraph_type', 'bkx_yoast_og_type');
function bkx_yoast_og_type($type) {
    if (is_singular('bkx_service')) {
        return 'product';
    }
    return $type;
}

add_filter('wpseo_opengraph_title', 'bkx_yoast_og_title');
function bkx_yoast_og_title($title) {
    if (is_singular('bkx_service')) {
        $service = bkx_get_current_service();
        return $service->name . ' - Book Now';
    }
    return $title;
}

add_filter('wpseo_opengraph_desc', 'bkx_yoast_og_description');
function bkx_yoast_og_description($description) {
    if (is_singular('bkx_service')) {
        $service = bkx_get_current_service();
        $desc = wp_trim_words($service->description, 30);

        // Add pricing and duration
        $meta = sprintf(
            ' | Duration: %d min | From $%s',
            $service->duration,
            number_format($service->price, 2)
        );

        return $desc . $meta;
    }
    return $description;
}

add_filter('wpseo_opengraph_image', 'bkx_yoast_og_image');
function bkx_yoast_og_image($image) {
    if (is_singular('bkx_service')) {
        $service_id = get_the_ID();
        $custom_image = get_post_meta($service_id, '_bkx_og_image', true);

        if ($custom_image) {
            return $custom_image;
        }

        // Fallback to featured image
        if (has_post_thumbnail($service_id)) {
            return get_the_post_thumbnail_url($service_id, 'full');
        }
    }
    return $image;
}

/**
 * Add custom OpenGraph tags
 */
add_action('wpseo_opengraph', 'bkx_yoast_custom_og_tags');
function bkx_yoast_custom_og_tags() {
    if (is_singular('bkx_service')) {
        $service = bkx_get_current_service();

        // Price
        echo '<meta property="product:price:amount" content="' . esc_attr($service->price) . '" />' . "\n";
        echo '<meta property="product:price:currency" content="' . esc_attr(get_option('bookingx_currency', 'USD')) . '" />' . "\n";

        // Availability
        $available = bkx_check_service_availability($service->ID);
        $availability = $available ? 'instock' : 'out of stock';
        echo '<meta property="product:availability" content="' . esc_attr($availability) . '" />' . "\n";

        // Category
        if ($service->category) {
            echo '<meta property="product:category" content="' . esc_attr($service->category) . '" />' . "\n";
        }
    }
}
```

### Twitter Card Implementation
```php
/**
 * Set Twitter Card type for bookings
 */
add_filter('wpseo_twitter_card_type', 'bkx_yoast_twitter_card_type');
function bkx_yoast_twitter_card_type($type) {
    if (is_singular('bkx_service')) {
        return 'summary_large_image';
    }
    return $type;
}

/**
 * Add custom Twitter meta tags
 */
add_action('wpseo_twitter', 'bkx_yoast_custom_twitter_tags');
function bkx_yoast_custom_twitter_tags() {
    if (is_singular('bkx_service')) {
        $service = bkx_get_current_service();

        echo '<meta name="twitter:label1" content="Duration" />' . "\n";
        echo '<meta name="twitter:data1" content="' . esc_attr($service->duration) . ' minutes" />' . "\n";

        echo '<meta name="twitter:label2" content="Price" />' . "\n";
        echo '<meta name="twitter:data2" content="$' . esc_attr(number_format($service->price, 2)) . '" />' . "\n";
    }
}
```

---

## 9. Breadcrumb Implementation

### Service Breadcrumbs
```php
/**
 * Add booking-specific breadcrumbs
 */
add_filter('wpseo_breadcrumb_links', 'bkx_yoast_breadcrumbs');
function bkx_yoast_breadcrumbs($links) {
    if (is_singular('bkx_service')) {
        $service = bkx_get_current_service();
        $new_links = [];

        // Home
        $new_links[] = $links[0];

        // Services archive
        $new_links[] = [
            'url' => get_post_type_archive_link('bkx_service'),
            'text' => __('Services', 'bookingx-yoast'),
        ];

        // Service category
        if ($service->category_id) {
            $category = get_term($service->category_id, 'bkx_service_category');
            $new_links[] = [
                'url' => get_term_link($category),
                'text' => $category->name,
            ];
        }

        // Location (if applicable)
        if ($service->location_id && get_option('bkx_yoast_show_location_breadcrumb')) {
            $location = bkx_get_location($service->location_id);
            $new_links[] = [
                'url' => get_permalink($location->ID),
                'text' => $location->name,
            ];
        }

        // Current service
        $new_links[] = [
            'url' => get_permalink($service->ID),
            'text' => $service->name,
        ];

        return $new_links;
    }

    if (is_singular('bkx_staff')) {
        $staff = bkx_get_current_staff();
        $new_links = [];

        $new_links[] = $links[0]; // Home

        // Staff archive
        $new_links[] = [
            'url' => get_post_type_archive_link('bkx_staff'),
            'text' => __('Our Team', 'bookingx-yoast'),
        ];

        // Current staff
        $new_links[] = [
            'url' => get_permalink($staff->ID),
            'text' => $staff->name,
        ];

        return $new_links;
    }

    return $links;
}

/**
 * Customize breadcrumb separator
 */
add_filter('wpseo_breadcrumb_separator', 'bkx_yoast_breadcrumb_separator');
function bkx_yoast_breadcrumb_separator($separator) {
    $custom_separator = get_option('bkx_yoast_breadcrumb_separator');
    return $custom_separator ? $custom_separator : $separator;
}
```

---

## 10. XML Sitemap Integration

### Add Services to Sitemap
```php
/**
 * Register custom sitemap for booking services
 */
add_filter('wpseo_sitemap_index', 'bkx_yoast_add_sitemap_index');
function bkx_yoast_add_sitemap_index($sitemap_index) {
    $sitemap_index .= '<sitemap>' . "\n";
    $sitemap_index .= '<loc>' . home_url('/bkx-services-sitemap.xml') . '</loc>' . "\n";
    $sitemap_index .= '<lastmod>' . date('c', current_time('timestamp')) . '</lastmod>' . "\n";
    $sitemap_index .= '</sitemap>' . "\n";

    return $sitemap_index;
}

/**
 * Generate services sitemap
 */
add_action('init', 'bkx_yoast_register_sitemap');
function bkx_yoast_register_sitemap() {
    add_action('do_sitemap_bkx-services', 'bkx_yoast_generate_services_sitemap');
}

function bkx_yoast_generate_services_sitemap() {
    $services = bkx_get_all_services([
        'status' => 'publish',
        'per_page' => -1,
    ]);

    $sitemap = new WPSEO_Sitemap();
    $output = '';

    foreach ($services as $service) {
        // Get last modified date
        $modified = get_post_modified_time('c', false, $service->ID);

        // Build URL entry
        $url_entry = [
            'loc' => get_permalink($service->ID),
            'lastmod' => $modified,
            'pri' => get_option('bkx_yoast_services_priority', 0.8),
            'chf' => get_option('bkx_yoast_services_frequency', 'weekly'),
        ];

        // Add images if enabled
        if (get_option('bkx_yoast_include_service_images')) {
            $images = [];

            // Featured image
            if (has_post_thumbnail($service->ID)) {
                $images[] = [
                    'src' => get_the_post_thumbnail_url($service->ID, 'full'),
                    'title' => $service->name,
                    'alt' => get_post_meta(get_post_thumbnail_id($service->ID), '_wp_attachment_image_alt', true),
                ];
            }

            // Gallery images
            $gallery = get_post_meta($service->ID, '_bkx_gallery', true);
            if ($gallery) {
                foreach ($gallery as $image_id) {
                    $images[] = [
                        'src' => wp_get_attachment_url($image_id),
                        'title' => get_the_title($image_id),
                        'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
                    ];
                }
            }

            $url_entry['images'] = $images;
        }

        $output .= $sitemap->sitemap_url($url_entry);
    }

    $sitemap->set_sitemap($output);
}

/**
 * Modify sitemap entry for services
 */
add_filter('wpseo_sitemap_entry', 'bkx_yoast_modify_sitemap_entry', 10, 3);
function bkx_yoast_modify_sitemap_entry($url, $type, $post) {
    if ($post->post_type === 'bkx_service') {
        // Check if service is bookable
        $is_bookable = get_post_meta($post->ID, '_bkx_is_bookable', true);
        if (!$is_bookable) {
            return false; // Exclude from sitemap
        }

        // Adjust priority based on popularity
        $booking_count = bkx_get_service_booking_count($post->ID);
        if ($booking_count > 100) {
            $url['pri'] = 1.0;
        } elseif ($booking_count > 50) {
            $url['pri'] = 0.9;
        }

        // Update change frequency based on last booking
        $last_booking = bkx_get_last_booking_date($post->ID);
        if ($last_booking && strtotime($last_booking) > strtotime('-7 days')) {
            $url['chf'] = 'daily';
        }
    }

    return $url;
}
```

---

## 11. Meta Data Templates

### Dynamic Title Generation
```php
/**
 * Generate SEO title for booking pages
 */
add_filter('wpseo_title', 'bkx_yoast_dynamic_title');
function bkx_yoast_dynamic_title($title) {
    if (is_singular('bkx_service')) {
        $service = bkx_get_current_service();
        $template = get_option('bkx_yoast_service_title_template',
            '%%title%% | Book Online | %%sitename%%');

        $replacements = [
            '%%title%%' => $service->name,
            '%%sitename%%' => get_bloginfo('name'),
            '%%price%%' => '$' . number_format($service->price, 2),
            '%%duration%%' => $service->duration . ' min',
            '%%category%%' => $service->category_name,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    if (is_singular('bkx_location')) {
        $location = bkx_get_current_location();
        $template = get_option('bkx_yoast_location_title_template',
            '%%title%% - %%address%% | %%sitename%%');

        $replacements = [
            '%%title%%' => $location->name,
            '%%sitename%%' => get_bloginfo('name'),
            '%%address%%' => $location->city . ', ' . $location->state,
            '%%services_count%%' => count($location->services),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    return $title;
}
```

### Dynamic Meta Description
```php
/**
 * Generate SEO meta description
 */
add_filter('wpseo_metadesc', 'bkx_yoast_dynamic_description');
function bkx_yoast_dynamic_description($description) {
    if (is_singular('bkx_service')) {
        $service = bkx_get_current_service();

        // Check for custom meta description
        $custom_desc = get_post_meta($service->ID, '_bkx_meta_description', true);
        if ($custom_desc) {
            return $custom_desc;
        }

        $template = get_option('bkx_yoast_service_description_template',
            'Book %%title%% online. Duration: %%duration%%. Price: %%price%%. %%excerpt%%');

        $replacements = [
            '%%title%%' => $service->name,
            '%%duration%%' => $service->duration . ' minutes',
            '%%price%%' => '$' . number_format($service->price, 2),
            '%%excerpt%%' => wp_trim_words($service->description, 20),
            '%%sitename%%' => get_bloginfo('name'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    return $description;
}
```

### Canonical URL Management
```php
/**
 * Set canonical URL for booking pages
 */
add_filter('wpseo_canonical', 'bkx_yoast_canonical_url');
function bkx_yoast_canonical_url($canonical) {
    if (is_singular('bkx_service')) {
        $service_id = get_the_ID();

        // Check for custom canonical
        $custom_canonical = get_post_meta($service_id, '_bkx_canonical_url', true);
        if ($custom_canonical) {
            return $custom_canonical;
        }

        // Ensure clean URL without query parameters
        return get_permalink($service_id);
    }

    return $canonical;
}
```

---

## 12. Shortcode System

### SEO-Optimized Service Display
```php
/**
 * Display service with SEO markup
 *
 * Usage: [bkx_yoast_service id="123" show_schema="yes"]
 */
add_shortcode('bkx_yoast_service', 'bkx_yoast_service_shortcode');
function bkx_yoast_service_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => 0,
        'show_schema' => 'yes',
        'show_breadcrumbs' => 'yes',
    ], $atts);

    ob_start();

    $service = bkx_get_service($atts['id']);

    // Output schema if enabled
    if ($atts['show_schema'] === 'yes') {
        $schema_generator = new BookingX\Addons\YoastSEO\ServiceSchema($atts['id']);
        $schema = $schema_generator->generate();
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }

    // Output breadcrumbs if enabled
    if ($atts['show_breadcrumbs'] === 'yes' && function_exists('yoast_breadcrumb')) {
        yoast_breadcrumb('<div id="breadcrumbs">', '</div>');
    }

    // Output service content
    bkx_render_service_with_seo($service);

    return ob_get_clean();
}

/**
 * Display services grid with schema
 *
 * Usage: [bkx_yoast_services_grid category="spa" limit="6"]
 */
add_shortcode('bkx_yoast_services_grid', 'bkx_yoast_services_grid_shortcode');
function bkx_yoast_services_grid_shortcode($atts) {
    $atts = shortcode_atts([
        'category' => '',
        'limit' => 6,
        'show_schema' => 'yes',
    ], $atts);

    $services = bkx_get_services([
        'category' => $atts['category'],
        'limit' => $atts['limit'],
    ]);

    ob_start();

    // ItemList schema
    if ($atts['show_schema'] === 'yes') {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => [],
        ];

        foreach ($services as $index => $service) {
            $schema['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => [
                    '@type' => 'Service',
                    'name' => $service->name,
                    'url' => get_permalink($service->ID),
                ],
            ];
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }

    bkx_render_services_grid($services);

    return ob_get_clean();
}
```

---

## 13. Testing Strategy

### Unit Tests
```php
class YoastSEOIntegrationTest extends WP_UnitTestCase {

    public function test_service_schema_generation() {
        $service_id = $this->create_test_service();
        $schema_generator = new BookingX\Addons\YoastSEO\ServiceSchema($service_id);
        $schema = $schema_generator->generate();

        $this->assertArrayHasKey('@type', $schema);
        $this->assertEquals('Service', $schema['@type']);
        $this->assertArrayHasKey('offers', $schema);
    }

    public function test_opengraph_tags() {
        $service_id = $this->create_test_service();
        $this->go_to(get_permalink($service_id));

        $og_title = apply_filters('wpseo_opengraph_title', '');
        $this->assertNotEmpty($og_title);
        $this->assertStringContainsString('Book Now', $og_title);
    }

    public function test_breadcrumb_generation() {
        $service_id = $this->create_test_service();
        $this->go_to(get_permalink($service_id));

        $breadcrumbs = apply_filters('wpseo_breadcrumb_links', []);
        $this->assertGreaterThan(2, count($breadcrumbs));
    }
}
```

---

## 14. Performance Optimization

### Caching Schema Data
```php
/**
 * Cache generated schema markup
 */
function bkx_yoast_get_cached_schema($object_id, $object_type) {
    $cache_key = 'bkx_schema_' . $object_type . '_' . $object_id;
    $schema = wp_cache_get($cache_key, 'bookingx_yoast');

    if (false === $schema) {
        $schema = bkx_yoast_generate_schema($object_id, $object_type);
        wp_cache_set($cache_key, $schema, 'bookingx_yoast', DAY_IN_SECONDS);
    }

    return $schema;
}

/**
 * Clear schema cache when content updates
 */
add_action('bkx_service_updated', 'bkx_yoast_clear_schema_cache');
function bkx_yoast_clear_schema_cache($service_id) {
    $cache_key = 'bkx_schema_service_' . $service_id;
    wp_cache_delete($cache_key, 'bookingx_yoast');
}
```

---

## 15. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Plugin structure
- [ ] Yoast compatibility check
- [ ] Settings page

### Phase 2: Schema Implementation (Week 3-4)
- [ ] LocalBusiness schema
- [ ] Service schema
- [ ] Person schema
- [ ] Rating schema

### Phase 3: OpenGraph & Social (Week 5)
- [ ] OpenGraph tags
- [ ] Twitter Cards
- [ ] Social image generation

### Phase 4: Breadcrumbs & Sitemap (Week 6)
- [ ] Breadcrumb integration
- [ ] XML sitemap
- [ ] Image sitemap

### Phase 5: Meta Data & Templates (Week 7)
- [ ] Title templates
- [ ] Description templates
- [ ] Canonical URLs

### Phase 6: Testing & Launch (Week 8-9)
- [ ] Unit testing
- [ ] Integration testing
- [ ] Documentation
- [ ] Release

**Total Estimated Timeline:** 9 weeks (2.25 months)

---

## 16. Success Metrics

### Technical Metrics
- Schema validation score: 100%
- OpenGraph validation: Pass
- Page speed impact: <50ms
- Search visibility: +30%

### Business Metrics
- Organic traffic: +25%
- Click-through rate: +15%
- Social shares: +40%
- Search rankings: Top 10 for target keywords

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development
