# Divi Theme Integration Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Divi Theme Integration
**Price:** $79
**Category:** WordPress Ecosystem
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+
**Divi Version:** 4.0+

### Description
Native Divi Builder integration providing custom BookingX modules with Visual Builder support. Seamlessly integrates with Divi's design system including theme color synchronization, responsive editing tools, and the Divi Library. Build beautiful booking pages using Divi's drag-and-drop interface with live frontend editing.

### Value Proposition
- Native Divi module integration
- Full Visual Builder support with live editing
- Automatic theme color and font synchronization
- Responsive design controls with preview
- Access to Divi's extensive design options
- Compatible with Divi Library
- Theme Customizer integration
- Global presets support

---

## 2. Features & Requirements

### Core Features
1. **Custom Divi Modules**
   - Booking Form Module
   - Service Grid Module
   - Service Carousel Module
   - Staff Grid Module
   - Calendar Module
   - Reviews Module
   - Pricing Table Module
   - Business Hours Module

2. **Visual Builder Integration**
   - Live frontend editing
   - Real-time preview
   - Drag-and-drop interface
   - Inline text editing
   - Visual style controls
   - Responsive preview modes
   - Copy/paste module support

3. **Theme Builder Support**
   - Custom service page layouts
   - Archive page templates
   - Staff profile layouts
   - Location page templates
   - Header/footer integration
   - Global module presets

4. **Design System Integration**
   - Divi theme color sync
   - Custom font integration
   - Button style inheritance
   - Spacing presets
   - Border styles
   - Shadow styles
   - Animation options

5. **Divi Library Integration**
   - Pre-built booking layouts
   - Exportable/importable modules
   - Layout packs
   - Module presets
   - Global modules

6. **Customizer Integration**
   - Theme options sync
   - Color scheme inheritance
   - Typography settings
   - Mobile menu integration
   - Footer widget area

7. **Advanced Features**
   - Custom CSS support
   - Module visibility conditions
   - A/B testing ready
   - Multi-language support
   - Child theme compatibility

### User Roles & Permissions
- **Admin:** Full module configuration
- **Designer:** Design and layout access
- **Editor:** Content editing only
- **Staff:** Limited module access
- **Customer:** Frontend view only

---

## 3. Technical Specifications

### Technology Stack
- **Divi Builder:** 4.0+
- **React:** Module UI components
- **JavaScript:** ES6+ with Babel
- **CSS:** Custom CSS with Divi framework
- **PHP:** 7.4+ (8.0+ recommended)
- **Divi API:** Custom modules API

### Dependencies
- BookingX Core 2.0+
- Divi Theme or Divi Builder Plugin 4.0+
- WordPress 5.8+
- PHP 7.4+

### Divi API Integration
```php
// Module Registration
add_action('et_builder_ready', 'bkx_divi_initialize_extension');

// Custom Icons
add_filter('et_pb_all_fields_unprocessed_bkx_module', 'bkx_add_custom_icons');

// Module Assets
add_action('wp_enqueue_scripts', 'bkx_divi_enqueue_assets');

// Module Defaults
add_filter('et_pb_module_shortcode_attributes', 'bkx_set_module_defaults', 10, 3);

// Visual Builder Scripts
add_action('et_fb_enqueue_assets', 'bkx_divi_enqueue_vb_assets');
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────────────────────────────┐
│           BookingX Core                     │
│  - Services API                             │
│  - Staff Management                         │
│  - Booking Engine                           │
│  - Data Models                              │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│     Divi Integration Layer                  │
│  - Module Registration                      │
│  - Field Definitions                        │
│  - Render Functions                         │
│  - Asset Management                         │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│         Divi Builder Core                   │
│  - Visual Builder                           │
│  - Module System                            │
│  - Design Options                           │
│  - Theme Engine                             │
└─────────────────────────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\Divi;

class DiviIntegration extends DiviExtension {
    - get_name()
    - initialize()
    - register_modules()
    - enqueue_assets()
}

// Base Module Class
abstract class ModuleBase extends ET_Builder_Module {
    - init()
    - get_fields()
    - get_advanced_fields_config()
    - render()
    - apply_css()
}

// Individual Modules
class BookingFormModule extends ModuleBase {
    - init()
    - get_fields()
    - render()
    - _render_module_wrapper()
}

class ServiceGridModule extends ModuleBase {
    - init()
    - get_fields()
    - render()
    - get_services_query()
}

class CalendarModule extends ModuleBase {
    - init()
    - get_fields()
    - render()
    - enqueue_calendar_assets()
}

class StaffGridModule extends ModuleBase {
    - init()
    - get_fields()
    - render()
    - get_staff_query()
}

// Theme Customizer
class ThemeCustomizer {
    - register_panels()
    - register_sections()
    - register_settings()
    - sync_with_divi()
}

// Library Manager
class LibraryManager {
    - register_layouts()
    - export_layout()
    - import_layout()
    - get_layout_categories()
}
```

---

## 5. Module Implementation

### Booking Form Divi Module
```php
namespace BookingX\Addons\Divi\Modules;

class BookingFormModule extends ET_Builder_Module {

    public $slug = 'bkx_booking_form';
    public $vb_support = 'on';

    protected $module_credits = [
        'module_uri' => 'https://bookingx.com',
        'author' => 'BookingX',
        'author_uri' => 'https://bookingx.com',
    ];

    public function init() {
        $this->name = esc_html__('Booking Form', 'bookingx-divi');
        $this->icon_path = plugin_dir_path(__FILE__) . 'icons/booking-form.svg';

        $this->settings_modal_toggles = [
            'general' => [
                'toggles' => [
                    'main_content' => esc_html__('Form Settings', 'bookingx-divi'),
                    'elements' => esc_html__('Elements', 'bookingx-divi'),
                ],
            ],
            'advanced' => [
                'toggles' => [
                    'form_styles' => esc_html__('Form Styles', 'bookingx-divi'),
                    'button_styles' => esc_html__('Button Styles', 'bookingx-divi'),
                ],
            ],
        ];
    }

    public function get_fields() {
        return [
            'form_layout' => [
                'label' => esc_html__('Form Layout', 'bookingx-divi'),
                'type' => 'select',
                'option_category' => 'layout',
                'options' => [
                    'multi-step' => esc_html__('Multi-Step', 'bookingx-divi'),
                    'single-page' => esc_html__('Single Page', 'bookingx-divi'),
                    'compact' => esc_html__('Compact', 'bookingx-divi'),
                ],
                'default' => 'multi-step',
                'toggle_slug' => 'main_content',
            ],
            'default_service' => [
                'label' => esc_html__('Default Service', 'bookingx-divi'),
                'type' => 'select',
                'option_category' => 'configuration',
                'options' => $this->get_services_list(),
                'toggle_slug' => 'main_content',
            ],
            'show_service_images' => [
                'label' => esc_html__('Show Service Images', 'bookingx-divi'),
                'type' => 'yes_no_button',
                'option_category' => 'configuration',
                'options' => [
                    'on' => esc_html__('Yes', 'bookingx-divi'),
                    'off' => esc_html__('No', 'bookingx-divi'),
                ],
                'default' => 'on',
                'toggle_slug' => 'elements',
            ],
            'enable_staff_selection' => [
                'label' => esc_html__('Enable Staff Selection', 'bookingx-divi'),
                'type' => 'yes_no_button',
                'option_category' => 'configuration',
                'options' => [
                    'on' => esc_html__('Yes', 'bookingx-divi'),
                    'off' => esc_html__('No', 'bookingx-divi'),
                ],
                'default' => 'on',
                'toggle_slug' => 'elements',
            ],
            'button_text' => [
                'label' => esc_html__('Button Text', 'bookingx-divi'),
                'type' => 'text',
                'option_category' => 'basic_option',
                'default' => esc_html__('Book Now', 'bookingx-divi'),
                'toggle_slug' => 'main_content',
            ],
        ];
    }

    public function get_advanced_fields_config() {
        return [
            'fonts' => [
                'header' => [
                    'label' => esc_html__('Title', 'bookingx-divi'),
                    'css' => [
                        'main' => "{$this->main_css_element} .bkx-form-title",
                    ],
                    'font_size' => [
                        'default' => '24px',
                    ],
                    'toggle_slug' => 'form_styles',
                ],
                'body' => [
                    'label' => esc_html__('Body', 'bookingx-divi'),
                    'css' => [
                        'main' => "{$this->main_css_element} .bkx-form-body",
                    ],
                    'font_size' => [
                        'default' => '16px',
                    ],
                    'toggle_slug' => 'form_styles',
                ],
            ],
            'background' => [
                'css' => [
                    'main' => "{$this->main_css_element}",
                ],
            ],
            'borders' => [
                'default' => [
                    'css' => [
                        'main' => [
                            'border_radii' => "{$this->main_css_element}",
                            'border_styles' => "{$this->main_css_element}",
                        ],
                    ],
                ],
            ],
            'box_shadow' => [
                'default' => [
                    'css' => [
                        'main' => "{$this->main_css_element}",
                    ],
                ],
            ],
            'margin_padding' => [
                'css' => [
                    'important' => 'all',
                ],
            ],
            'button' => [
                'button' => [
                    'label' => esc_html__('Button', 'bookingx-divi'),
                    'css' => [
                        'main' => "{$this->main_css_element} .bkx-book-button",
                    ],
                    'use_alignment' => true,
                    'box_shadow' => [
                        'css' => [
                            'main' => "{$this->main_css_element} .bkx-book-button",
                        ],
                    ],
                    'toggle_slug' => 'button_styles',
                ],
            ],
        ];
    }

    public function render($attrs, $content, $render_slug) {
        $form_layout = $this->props['form_layout'];
        $default_service = $this->props['default_service'];
        $show_images = $this->props['show_service_images'];
        $enable_staff = $this->props['enable_staff_selection'];
        $button_text = $this->props['button_text'];

        // Add custom CSS
        $this->apply_custom_css($render_slug);

        // Render the booking form
        ob_start();
        ?>
        <div class="bkx-divi-booking-form bkx-layout-<?php echo esc_attr($form_layout); ?>">
            <?php
            echo do_shortcode(sprintf(
                '[bkx_booking_form layout="%s" default_service="%s" show_images="%s" enable_staff="%s" button_text="%s"]',
                esc_attr($form_layout),
                esc_attr($default_service),
                esc_attr($show_images),
                esc_attr($enable_staff),
                esc_attr($button_text)
            ));
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function apply_custom_css($render_slug) {
        // Button alignment
        if ($this->props['button_alignment']) {
            ET_Builder_Element::set_style($render_slug, [
                'selector' => '%%order_class%% .bkx-book-button',
                'declaration' => sprintf('text-align: %s;', $this->props['button_alignment']),
            ]);
        }

        // Custom spacing
        if ($this->props['custom_padding']) {
            ET_Builder_Element::set_style($render_slug, [
                'selector' => '%%order_class%%',
                'declaration' => sprintf('padding: %s;', $this->props['custom_padding']),
            ]);
        }
    }

    private function get_services_list() {
        $services = bkx_get_all_services();
        $options = ['' => esc_html__('Select Service', 'bookingx-divi')];

        foreach ($services as $service) {
            $options[$service->ID] = $service->name;
        }

        return $options;
    }
}
```

### Service Grid Divi Module
```php
class ServiceGridModule extends ET_Builder_Module {

    public $slug = 'bkx_service_grid';
    public $vb_support = 'on';

    public function init() {
        $this->name = esc_html__('Service Grid', 'bookingx-divi');
        $this->icon_path = plugin_dir_path(__FILE__) . 'icons/service-grid.svg';

        $this->settings_modal_toggles = [
            'general' => [
                'toggles' => [
                    'query' => esc_html__('Query', 'bookingx-divi'),
                    'layout' => esc_html__('Layout', 'bookingx-divi'),
                    'content' => esc_html__('Content', 'bookingx-divi'),
                ],
            ],
        ];
    }

    public function get_fields() {
        return [
            'services_per_page' => [
                'label' => esc_html__('Services Per Page', 'bookingx-divi'),
                'type' => 'range',
                'option_category' => 'configuration',
                'range_settings' => [
                    'min' => 1,
                    'max' => 50,
                    'step' => 1,
                ],
                'default' => '6',
                'toggle_slug' => 'query',
            ],
            'service_category' => [
                'label' => esc_html__('Service Category', 'bookingx-divi'),
                'type' => 'multiple_checkboxes',
                'option_category' => 'configuration',
                'options' => $this->get_service_categories(),
                'toggle_slug' => 'query',
            ],
            'columns' => [
                'label' => esc_html__('Columns', 'bookingx-divi'),
                'type' => 'range',
                'option_category' => 'layout',
                'range_settings' => [
                    'min' => 1,
                    'max' => 6,
                    'step' => 1,
                ],
                'default' => '3',
                'mobile_options' => true,
                'toggle_slug' => 'layout',
            ],
            'columns_tablet' => [
                'type' => 'skip',
                'default' => '2',
            ],
            'columns_phone' => [
                'type' => 'skip',
                'default' => '1',
            ],
            'column_gap' => [
                'label' => esc_html__('Column Gap', 'bookingx-divi'),
                'type' => 'range',
                'option_category' => 'layout',
                'range_settings' => [
                    'min' => 0,
                    'max' => 100,
                    'step' => 1,
                ],
                'default' => '20px',
                'toggle_slug' => 'layout',
            ],
            'show_image' => [
                'label' => esc_html__('Show Image', 'bookingx-divi'),
                'type' => 'yes_no_button',
                'option_category' => 'configuration',
                'options' => [
                    'on' => esc_html__('Yes', 'bookingx-divi'),
                    'off' => esc_html__('No', 'bookingx-divi'),
                ],
                'default' => 'on',
                'toggle_slug' => 'content',
            ],
            'show_price' => [
                'label' => esc_html__('Show Price', 'bookingx-divi'),
                'type' => 'yes_no_button',
                'option_category' => 'configuration',
                'options' => [
                    'on' => esc_html__('Yes', 'bookingx-divi'),
                    'off' => esc_html__('No', 'bookingx-divi'),
                ],
                'default' => 'on',
                'toggle_slug' => 'content',
            ],
            'show_duration' => [
                'label' => esc_html__('Show Duration', 'bookingx-divi'),
                'type' => 'yes_no_button',
                'option_category' => 'configuration',
                'options' => [
                    'on' => esc_html__('Yes', 'bookingx-divi'),
                    'off' => esc_html__('No', 'bookingx-divi'),
                ],
                'default' => 'on',
                'toggle_slug' => 'content',
            ],
            'orderby' => [
                'label' => esc_html__('Order By', 'bookingx-divi'),
                'type' => 'select',
                'option_category' => 'configuration',
                'options' => [
                    'date' => esc_html__('Date', 'bookingx-divi'),
                    'title' => esc_html__('Title', 'bookingx-divi'),
                    'price' => esc_html__('Price', 'bookingx-divi'),
                    'popularity' => esc_html__('Popularity', 'bookingx-divi'),
                ],
                'default' => 'date',
                'toggle_slug' => 'query',
            ],
        ];
    }

    public function get_advanced_fields_config() {
        return [
            'fonts' => [
                'title' => [
                    'label' => esc_html__('Title', 'bookingx-divi'),
                    'css' => [
                        'main' => "{$this->main_css_element} .bkx-service-title",
                    ],
                    'font_size' => [
                        'default' => '20px',
                    ],
                ],
                'body' => [
                    'label' => esc_html__('Body', 'bookingx-divi'),
                    'css' => [
                        'main' => "{$this->main_css_element} .bkx-service-description",
                    ],
                ],
                'price' => [
                    'label' => esc_html__('Price', 'bookingx-divi'),
                    'css' => [
                        'main' => "{$this->main_css_element} .bkx-service-price",
                    ],
                    'font_size' => [
                        'default' => '18px',
                    ],
                ],
            ],
            'background' => [
                'css' => [
                    'main' => "{$this->main_css_element} .bkx-service-card",
                ],
            ],
            'borders' => [
                'default' => [
                    'css' => [
                        'main' => [
                            'border_radii' => "{$this->main_css_element} .bkx-service-card",
                            'border_styles' => "{$this->main_css_element} .bkx-service-card",
                        ],
                    ],
                ],
            ],
            'box_shadow' => [
                'default' => [
                    'css' => [
                        'main' => "{$this->main_css_element} .bkx-service-card",
                        'hover' => "{$this->main_css_element} .bkx-service-card:hover",
                    ],
                ],
            ],
            'filters' => [
                'css' => [
                    'main' => "{$this->main_css_element}",
                ],
            ],
            'margin_padding' => [
                'css' => [
                    'important' => 'all',
                ],
            ],
            'button' => [
                'button' => [
                    'label' => esc_html__('Button', 'bookingx-divi'),
                    'css' => [
                        'main' => "{$this->main_css_element} .bkx-book-button",
                    ],
                ],
            ],
        ];
    }

    public function render($attrs, $content, $render_slug) {
        $services_per_page = $this->props['services_per_page'];
        $columns = $this->props['columns'];
        $columns_tablet = $this->props['columns_tablet'];
        $columns_phone = $this->props['columns_phone'];
        $column_gap = $this->props['column_gap'];
        $show_image = $this->props['show_image'];
        $show_price = $this->props['show_price'];
        $show_duration = $this->props['show_duration'];
        $orderby = $this->props['orderby'];
        $service_category = $this->props['service_category'];

        // Apply responsive columns
        ET_Builder_Element::set_style($render_slug, [
            'selector' => '%%order_class%% .bkx-service-grid',
            'declaration' => sprintf('grid-template-columns: repeat(%s, 1fr);', $columns),
        ]);

        ET_Builder_Element::set_style($render_slug, [
            'selector' => '%%order_class%% .bkx-service-grid',
            'declaration' => sprintf('grid-column-gap: %s;', $column_gap),
        ]);

        // Get services
        $args = [
            'posts_per_page' => $services_per_page,
            'orderby' => $orderby,
        ];

        if (!empty($service_category)) {
            $categories = array_keys(array_filter((array)$service_category));
            if (!empty($categories)) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'bkx_service_category',
                        'field' => 'term_id',
                        'terms' => $categories,
                    ],
                ];
            }
        }

        $services = bkx_get_services($args);

        // Render output
        ob_start();
        ?>
        <div class="bkx-divi-service-grid">
            <div class="bkx-service-grid bkx-columns-<?php echo esc_attr($columns); ?>">
                <?php foreach ($services as $service) : ?>
                    <div class="bkx-service-card">
                        <?php if ($show_image === 'on') : ?>
                            <div class="bkx-service-image">
                                <?php echo get_the_post_thumbnail($service->ID, 'medium'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="bkx-service-content">
                            <h3 class="bkx-service-title">
                                <?php echo esc_html($service->name); ?>
                            </h3>

                            <div class="bkx-service-description">
                                <?php echo wp_trim_words($service->description, 20); ?>
                            </div>

                            <div class="bkx-service-meta">
                                <?php if ($show_price === 'on') : ?>
                                    <span class="bkx-service-price">
                                        <?php echo bkx_format_price($service->price); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($show_duration === 'on') : ?>
                                    <span class="bkx-service-duration">
                                        <?php echo esc_html($service->duration . ' min'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <a href="<?php echo bkx_get_booking_url($service->ID); ?>"
                               class="bkx-book-button et_pb_button">
                                <?php esc_html_e('Book Now', 'bookingx-divi'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_service_categories() {
        $categories = get_terms([
            'taxonomy' => 'bkx_service_category',
            'hide_empty' => false,
        ]);

        $options = [];
        foreach ($categories as $category) {
            $options[$category->term_id] = $category->name;
        }

        return $options;
    }
}
```

---

## 6. Theme Customizer Integration

### Divi Theme Options Sync
```php
namespace BookingX\Addons\Divi;

class ThemeCustomizer {

    public function __construct() {
        add_action('customize_register', [$this, 'register_bookingx_options']);
        add_filter('et_divi_font_families', [$this, 'sync_fonts']);
        add_filter('et_divi_accent_color', [$this, 'sync_accent_color']);
    }

    public function register_bookingx_options($wp_customize) {
        // Add BookingX Panel
        $wp_customize->add_panel('bkx_divi_panel', [
            'title' => __('BookingX', 'bookingx-divi'),
            'description' => __('BookingX booking system options', 'bookingx-divi'),
            'priority' => 100,
        ]);

        // Booking Form Section
        $wp_customize->add_section('bkx_booking_form', [
            'title' => __('Booking Form', 'bookingx-divi'),
            'panel' => 'bkx_divi_panel',
        ]);

        // Primary Color
        $wp_customize->add_setting('bkx_primary_color', [
            'default' => et_get_option('accent_color', '#2EA3F2'),
            'transport' => 'postMessage',
            'sanitize_callback' => 'sanitize_hex_color',
        ]);

        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'bkx_primary_color', [
            'label' => __('Primary Color', 'bookingx-divi'),
            'section' => 'bkx_booking_form',
            'settings' => 'bkx_primary_color',
        ]));

        // Button Style
        $wp_customize->add_setting('bkx_button_style', [
            'default' => 'inherit',
            'transport' => 'postMessage',
        ]);

        $wp_customize->add_control('bkx_button_style', [
            'label' => __('Button Style', 'bookingx-divi'),
            'type' => 'select',
            'section' => 'bkx_booking_form',
            'choices' => [
                'inherit' => __('Inherit from Divi', 'bookingx-divi'),
                'custom' => __('Custom Style', 'bookingx-divi'),
            ],
        ]);

        // Typography
        $wp_customize->add_setting('bkx_heading_font', [
            'default' => et_get_option('heading_font', 'Poppins'),
            'transport' => 'postMessage',
        ]);

        $wp_customize->add_control('bkx_heading_font', [
            'label' => __('Heading Font', 'bookingx-divi'),
            'type' => 'select',
            'section' => 'bkx_booking_form',
            'choices' => $this->get_divi_fonts(),
        ]);
    }

    public function sync_fonts($fonts) {
        // Ensure BookingX uses same fonts as Divi
        $heading_font = et_get_option('heading_font');
        $body_font = et_get_option('body_font');

        update_option('bkx_heading_font', $heading_font);
        update_option('bkx_body_font', $body_font);

        return $fonts;
    }

    public function sync_accent_color($color) {
        // Sync accent color with BookingX
        update_option('bkx_primary_color', $color);
        return $color;
    }

    private function get_divi_fonts() {
        $fonts = et_builder_get_websafe_fonts();
        $google_fonts = et_builder_get_google_fonts();

        return array_merge($fonts, $google_fonts);
    }
}
```

---

## 7. Divi Library Integration

### Pre-built Layouts
```php
namespace BookingX\Addons\Divi;

class LibraryManager {

    private $layouts = [];

    public function __construct() {
        add_filter('et_pb_all_layouts', [$this, 'add_bookingx_layouts']);
        add_action('et_pb_library_loaded', [$this, 'register_layout_categories']);
    }

    public function add_bookingx_layouts($layouts) {
        $bookingx_layouts = [
            [
                'id' => 'bkx_full_booking_page',
                'name' => __('Full Booking Page', 'bookingx-divi'),
                'category' => 'booking',
                'thumbnail' => BKX_DIVI_URL . 'assets/layouts/full-booking.jpg',
                'content' => $this->get_layout_content('full-booking-page'),
            ],
            [
                'id' => 'bkx_service_showcase',
                'name' => __('Service Showcase', 'bookingx-divi'),
                'category' => 'services',
                'thumbnail' => BKX_DIVI_URL . 'assets/layouts/service-showcase.jpg',
                'content' => $this->get_layout_content('service-showcase'),
            ],
            [
                'id' => 'bkx_staff_profiles',
                'name' => __('Staff Profiles', 'bookingx-divi'),
                'category' => 'staff',
                'thumbnail' => BKX_DIVI_URL . 'assets/layouts/staff-profiles.jpg',
                'content' => $this->get_layout_content('staff-profiles'),
            ],
            [
                'id' => 'bkx_pricing_page',
                'name' => __('Pricing Page', 'bookingx-divi'),
                'category' => 'services',
                'thumbnail' => BKX_DIVI_URL . 'assets/layouts/pricing-page.jpg',
                'content' => $this->get_layout_content('pricing-page'),
            ],
        ];

        return array_merge($layouts, $bookingx_layouts);
    }

    public function register_layout_categories() {
        et_pb_register_layout_category('booking', [
            'name' => __('Booking Pages', 'bookingx-divi'),
            'icon' => 'calendar',
        ]);

        et_pb_register_layout_category('services', [
            'name' => __('Service Pages', 'bookingx-divi'),
            'icon' => 'grid',
        ]);

        et_pb_register_layout_category('staff', [
            'name' => __('Staff Pages', 'bookingx-divi'),
            'icon' => 'person',
        ]);
    }

    private function get_layout_content($layout_id) {
        $file = BKX_DIVI_PATH . 'layouts/' . $layout_id . '.json';
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return '';
    }
}
```

---

## 8. Responsive Design Controls

### Mobile-First Responsive Settings
```php
/**
 * Add responsive controls to all modules
 */
protected function add_responsive_controls() {
    return [
        'columns' => [
            'label' => esc_html__('Columns', 'bookingx-divi'),
            'type' => 'range',
            'option_category' => 'layout',
            'mobile_options' => true,
            'range_settings' => [
                'min' => 1,
                'max' => 6,
                'step' => 1,
            ],
            'default' => '3',
            'default_tablet' => '2',
            'default_phone' => '1',
            'toggle_slug' => 'layout',
        ],
        'spacing' => [
            'label' => esc_html__('Spacing', 'bookingx-divi'),
            'type' => 'range',
            'option_category' => 'layout',
            'mobile_options' => true,
            'range_settings' => [
                'min' => 0,
                'max' => 100,
                'step' => 1,
            ],
            'default' => '20px',
            'default_tablet' => '15px',
            'default_phone' => '10px',
            'toggle_slug' => 'layout',
        ],
    ];
}

/**
 * Apply responsive CSS
 */
private function apply_responsive_css($render_slug) {
    // Desktop
    ET_Builder_Element::set_style($render_slug, [
        'selector' => '%%order_class%% .bkx-grid',
        'declaration' => sprintf(
            'grid-template-columns: repeat(%s, 1fr);',
            $this->props['columns']
        ),
    ]);

    // Tablet
    ET_Builder_Element::set_style($render_slug, [
        'selector' => '%%order_class%% .bkx-grid',
        'declaration' => sprintf(
            'grid-template-columns: repeat(%s, 1fr);',
            $this->props['columns_tablet']
        ),
        'media_query' => ET_Builder_Element::get_media_query('max_width_980'),
    ]);

    // Phone
    ET_Builder_Element::set_style($render_slug, [
        'selector' => '%%order_class%% .bkx-grid',
        'declaration' => sprintf(
            'grid-template-columns: repeat(%s, 1fr);',
            $this->props['columns_phone']
        ),
        'media_query' => ET_Builder_Element::get_media_query('max_width_767'),
    ]);
}
```

---

## 9. Visual Builder Support

### Live Editing Implementation
```php
/**
 * Enable Visual Builder support
 */
class BookingFormModule extends ET_Builder_Module {

    public $vb_support = 'on';

    /**
     * Define Visual Builder specific fields
     */
    public function get_fields() {
        $fields = parent::get_fields();

        // Add Visual Builder specific attributes
        foreach ($fields as $key => &$field) {
            $field['vb_support'] = 'on';
            $field['dynamic_content'] = 'text';
        }

        return $fields;
    }

    /**
     * Add inline editing support
     */
    public function render($attrs, $content, $render_slug) {
        // Check if Visual Builder is active
        $is_vb = et_fb_is_enabled();

        // Add VB-specific classes and attributes
        $vb_classes = $is_vb ? ' et-fb-live-edit' : '';

        ob_start();
        ?>
        <div class="bkx-divi-module<?php echo esc_attr($vb_classes); ?>"
             data-module="bkx_booking_form">
            <?php $this->render_content(); ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Enqueue Visual Builder assets
 */
add_action('et_fb_enqueue_assets', 'bkx_divi_enqueue_vb_assets');
function bkx_divi_enqueue_vb_assets() {
    wp_enqueue_script(
        'bkx-divi-vb',
        BKX_DIVI_URL . 'assets/js/visual-builder.js',
        ['react', 'react-dom'],
        BKX_DIVI_VERSION,
        true
    );

    wp_localize_script('bkx-divi-vb', 'bkxDiviVB', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bkx_divi_vb'),
        'services' => bkx_get_all_services(),
    ]);
}
```

---

## 10. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Extension structure
- [ ] Module registration
- [ ] Base module class
- [ ] Icon assets

### Phase 2: Core Modules (Week 3-5)
- [ ] Booking Form module
- [ ] Service Grid module
- [ ] Calendar module
- [ ] Staff Grid module

### Phase 3: Design Integration (Week 6-7)
- [ ] Theme color sync
- [ ] Font integration
- [ ] Button styles
- [ ] Customizer integration

### Phase 4: Library & Templates (Week 8)
- [ ] Pre-built layouts
- [ ] Library integration
- [ ] Export/import

### Phase 5: Visual Builder (Week 9)
- [ ] VB support
- [ ] Live editing
- [ ] Inline editing

### Phase 6: Testing & Launch (Week 10-11)
- [ ] Testing
- [ ] Documentation
- [ ] Release

**Total Estimated Timeline:** 11 weeks (2.75 months)

---

## 11. Success Metrics

### Technical Metrics
- Module load time: <100ms
- Visual Builder performance: No lag
- Theme sync: 100% accurate
- Mobile responsive: 100%

### Business Metrics
- Activation rate: >30%
- Monthly active rate: >65%
- Customer satisfaction: >4.5/5

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development
