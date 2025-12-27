# Elementor Page Builder Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Elementor Page Builder Integration
**Price:** $69
**Category:** WordPress Ecosystem
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+
**Elementor Version:** 3.0+

### Description
Native Elementor integration providing drag-and-drop booking widgets and modules. Build stunning booking pages with live preview, responsive design controls, and seamless Elementor workflow integration. Includes booking forms, service displays, calendar views, staff grids, and testimonial widgets.

### Value Proposition
- Native Elementor widgets with live editing
- Full design control within Elementor interface
- Pre-built booking page templates
- Responsive design controls for all devices
- Dynamic content integration
- Custom CSS and styling options
- Elementor Pro compatibility
- Theme Builder support

---

## 2. Features & Requirements

### Core Features
1. **Booking Form Widget**
   - Step-by-step booking wizard
   - Service selection
   - Date and time picker
   - Staff selection
   - Contact form integration
   - Real-time availability check
   - Custom field support
   - Multi-step layouts

2. **Service Display Widgets**
   - Service grid
   - Service list
   - Service carousel/slider
   - Featured service showcase
   - Service categories filter
   - Price display options
   - Image gallery integration
   - Read more functionality

3. **Calendar Widgets**
   - Availability calendar
   - Booking calendar view
   - Month/week/day views
   - Staff schedule calendar
   - Color-coded availability
   - Event-style display
   - Legend customization
   - Mobile-responsive calendar

4. **Staff Widgets**
   - Staff grid layout
   - Staff carousel
   - Individual staff profiles
   - Staff bio cards
   - Social media integration
   - Availability display
   - Book with staff button
   - Skill/specialty badges

5. **Review & Testimonial Widgets**
   - Review grid
   - Review carousel
   - Star rating display
   - Customer testimonials
   - Service-specific reviews
   - Average rating widget
   - Review submission form

6. **Info & Display Widgets**
   - Business hours widget
   - Location map widget
   - Pricing table
   - Service comparison table
   - FAQ accordion
   - Call-to-action buttons
   - Countdown timers
   - Booking stats/counter

7. **Theme Builder Integration**
   - Single service templates
   - Service archive templates
   - Staff profile templates
   - Location templates
   - Custom headers/footers
   - Dynamic content tags

### User Roles & Permissions
- **Admin:** Full widget configuration access
- **Editor:** Use and configure widgets
- **Designer:** Design with all widgets
- **Staff:** View limitations for sensitive data
- **Customer:** Frontend display only

---

## 3. Technical Specifications

### Technology Stack
- **Elementor Core:** 3.0+
- **Elementor Pro:** 3.0+ (optional, for Theme Builder)
- **JavaScript:** Vanilla JS + Elementor APIs
- **CSS:** Custom controls + Elementor design system
- **React:** Admin settings UI
- **PHP:** 7.4+ (8.0+ recommended)

### Dependencies
- BookingX Core 2.0+
- Elementor 3.0+
- WordPress 5.8+
- jQuery (included with WordPress)

### Elementor API Integration
```php
// Widget Registration
add_action('elementor/widgets/widgets_registered', 'bkx_register_elementor_widgets');

// Controls Registration
add_action('elementor/controls/controls_registered', 'bkx_register_elementor_controls');

// Categories
add_action('elementor/elements/categories_registered', 'bkx_add_elementor_widget_categories');

// Dynamic Tags
add_action('elementor/dynamic_tags/register_tags', 'bkx_register_dynamic_tags');

// Template Library
add_action('elementor/editor/after_enqueue_scripts', 'bkx_enqueue_editor_scripts');
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────────────────────────────┐
│           BookingX Core                     │
│  - Services Data                            │
│  - Staff Data                               │
│  - Booking Logic                            │
│  - Availability System                      │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│     Elementor Integration Layer             │
│  - Widget Base Classes                      │
│  - Control Definitions                      │
│  - Template System                          │
│  - Dynamic Content                          │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│           Elementor Core                    │
│  - Widget System                            │
│  - Control System                           │
│  - Editor Interface                         │
│  - Frontend Rendering                       │
└─────────────────────────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\Elementor;

class ElementorIntegration {
    - init()
    - register_widgets()
    - register_controls()
    - register_categories()
    - enqueue_scripts()
    - enqueue_styles()
}

// Base Widget Class
abstract class WidgetBase extends \Elementor\Widget_Base {
    - get_name()
    - get_title()
    - get_icon()
    - get_categories()
    - get_keywords()
    - register_controls()
    - render()
    - content_template()
}

// Individual Widgets
class BookingFormWidget extends WidgetBase {
    - register_form_controls()
    - register_style_controls()
    - render_booking_form()
    - render_steps()
}

class ServiceGridWidget extends WidgetBase {
    - register_query_controls()
    - register_layout_controls()
    - register_style_controls()
    - render_service_grid()
}

class CalendarWidget extends WidgetBase {
    - register_calendar_controls()
    - register_appearance_controls()
    - render_calendar()
    - enqueue_calendar_assets()
}

class StaffGridWidget extends WidgetBase {
    - register_staff_controls()
    - register_card_controls()
    - render_staff_grid()
}

// Template Library
class TemplateLibrary {
    - register_templates()
    - get_template_content()
    - import_template()
    - get_template_categories()
}

// Dynamic Tags
class ServiceDynamicTag extends \Elementor\Core\DynamicTags\Tag {
    - get_name()
    - get_title()
    - get_categories()
    - render()
}
```

---

## 5. Widget Implementation

### Booking Form Widget
```php
namespace BookingX\Addons\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class BookingFormWidget extends Widget_Base {

    public function get_name() {
        return 'bkx_booking_form';
    }

    public function get_title() {
        return __('Booking Form', 'bookingx-elementor');
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return ['bookingx'];
    }

    public function get_keywords() {
        return ['booking', 'form', 'appointment', 'reservation'];
    }

    protected function register_controls() {
        // Content Tab - Form Settings
        $this->start_controls_section(
            'section_form_settings',
            [
                'label' => __('Form Settings', 'bookingx-elementor'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'form_layout',
            [
                'label' => __('Layout', 'bookingx-elementor'),
                'type' => Controls_Manager::SELECT,
                'default' => 'multi-step',
                'options' => [
                    'single-page' => __('Single Page', 'bookingx-elementor'),
                    'multi-step' => __('Multi-Step', 'bookingx-elementor'),
                    'compact' => __('Compact', 'bookingx-elementor'),
                ],
            ]
        );

        $this->add_control(
            'show_service_images',
            [
                'label' => __('Show Service Images', 'bookingx-elementor'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'enable_staff_selection',
            [
                'label' => __('Enable Staff Selection', 'bookingx-elementor'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'default_service',
            [
                'label' => __('Default Service', 'bookingx-elementor'),
                'type' => Controls_Manager::SELECT2,
                'options' => $this->get_services_list(),
                'label_block' => true,
            ]
        );

        $this->end_controls_section();

        // Style Tab - Form Style
        $this->start_controls_section(
            'section_form_style',
            [
                'label' => __('Form Style', 'bookingx-elementor'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'form_typography',
                'selector' => '{{WRAPPER}} .bkx-booking-form',
            ]
        );

        $this->add_control(
            'form_background_color',
            [
                'label' => __('Background Color', 'bookingx-elementor'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .bkx-booking-form' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'form_border',
                'selector' => '{{WRAPPER}} .bkx-booking-form',
            ]
        );

        $this->add_responsive_control(
            'form_padding',
            [
                'label' => __('Padding', 'bookingx-elementor'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .bkx-booking-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Tab - Button Style
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => __('Button Style', 'bookingx-elementor'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .bkx-booking-form button',
            ]
        );

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab(
            'button_normal',
            ['label' => __('Normal', 'bookingx-elementor')]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => __('Background Color', 'bookingx-elementor'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .bkx-booking-form button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => __('Text Color', 'bookingx-elementor'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .bkx-booking-form button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'button_hover',
            ['label' => __('Hover', 'bookingx-elementor')]
        );

        $this->add_control(
            'button_background_color_hover',
            [
                'label' => __('Background Color', 'bookingx-elementor'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .bkx-booking-form button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_text_color_hover',
            [
                'label' => __('Text Color', 'bookingx-elementor'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .bkx-booking-form button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        ?>
        <div class="bkx-elementor-booking-form">
            <?php
            echo do_shortcode('[bkx_booking_form
                layout="' . esc_attr($settings['form_layout']) . '"
                default_service="' . esc_attr($settings['default_service']) . '"
                show_images="' . esc_attr($settings['show_service_images']) . '"
                enable_staff="' . esc_attr($settings['enable_staff_selection']) . '"
            ]');
            ?>
        </div>
        <?php
    }

    protected function content_template() {
        ?>
        <#
        var layout = settings.form_layout;
        var defaultService = settings.default_service;
        #>
        <div class="bkx-elementor-booking-form">
            <div class="bkx-booking-form bkx-layout-{{{ layout }}}">
                <div class="bkx-form-preview">
                    <p><?php _e('Booking Form Preview', 'bookingx-elementor'); ?></p>
                    <p><?php _e('Layout:', 'bookingx-elementor'); ?> {{{ layout }}}</p>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_services_list() {
        $services = bkx_get_all_services();
        $options = ['' => __('Select Service', 'bookingx-elementor')];

        foreach ($services as $service) {
            $options[$service->ID] = $service->name;
        }

        return $options;
    }
}
```

### Service Grid Widget
```php
class ServiceGridWidget extends Widget_Base {

    public function get_name() {
        return 'bkx_service_grid';
    }

    public function get_title() {
        return __('Service Grid', 'bookingx-elementor');
    }

    public function get_icon() {
        return 'eicon-gallery-grid';
    }

    public function get_categories() {
        return ['bookingx'];
    }

    protected function register_controls() {
        // Query Controls
        $this->start_controls_section(
            'section_query',
            [
                'label' => __('Query', 'bookingx-elementor'),
            ]
        );

        $this->add_control(
            'services_per_page',
            [
                'label' => __('Services Per Page', 'bookingx-elementor'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'min' => 1,
                'max' => 100,
            ]
        );

        $this->add_control(
            'service_category',
            [
                'label' => __('Category', 'bookingx-elementor'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => $this->get_service_categories(),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label' => __('Order By', 'bookingx-elementor'),
                'type' => Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' => __('Date', 'bookingx-elementor'),
                    'title' => __('Title', 'bookingx-elementor'),
                    'menu_order' => __('Menu Order', 'bookingx-elementor'),
                    'price' => __('Price', 'bookingx-elementor'),
                    'popularity' => __('Popularity', 'bookingx-elementor'),
                ],
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => __('Order', 'bookingx-elementor'),
                'type' => Controls_Manager::SELECT,
                'default' => 'desc',
                'options' => [
                    'asc' => __('ASC', 'bookingx-elementor'),
                    'desc' => __('DESC', 'bookingx-elementor'),
                ],
            ]
        );

        $this->end_controls_section();

        // Layout Controls
        $this->start_controls_section(
            'section_layout',
            [
                'label' => __('Layout', 'bookingx-elementor'),
            ]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label' => __('Columns', 'bookingx-elementor'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'tablet_default' => '2',
                'mobile_default' => '1',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
                'selectors' => [
                    '{{WRAPPER}} .bkx-service-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
                ],
            ]
        );

        $this->add_responsive_control(
            'column_gap',
            [
                'label' => __('Column Gap', 'bookingx-elementor'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .bkx-service-grid' => 'grid-column-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'row_gap',
            [
                'label' => __('Row Gap', 'bookingx-elementor'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .bkx-service-grid' => 'grid-row-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Content Controls
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'bookingx-elementor'),
            ]
        );

        $this->add_control(
            'show_image',
            [
                'label' => __('Show Image', 'bookingx-elementor'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Show Title', 'bookingx-elementor'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_excerpt',
            [
                'label' => __('Show Excerpt', 'bookingx-elementor'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'excerpt_length',
            [
                'label' => __('Excerpt Length', 'bookingx-elementor'),
                'type' => Controls_Manager::NUMBER,
                'default' => 20,
                'condition' => [
                    'show_excerpt' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'show_price',
            [
                'label' => __('Show Price', 'bookingx-elementor'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_duration',
            [
                'label' => __('Show Duration', 'bookingx-elementor'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_button',
            [
                'label' => __('Show Book Button', 'bookingx-elementor'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Button Text', 'bookingx-elementor'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Book Now', 'bookingx-elementor'),
                'condition' => [
                    'show_button' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Style sections would follow here...
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $args = [
            'posts_per_page' => $settings['services_per_page'],
            'orderby' => $settings['orderby'],
            'order' => $settings['order'],
        ];

        if (!empty($settings['service_category'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'bkx_service_category',
                    'field' => 'term_id',
                    'terms' => $settings['service_category'],
                ],
            ];
        }

        $services = bkx_get_services($args);
        ?>
        <div class="bkx-service-grid bkx-columns-<?php echo esc_attr($settings['columns']); ?>">
            <?php foreach ($services as $service) : ?>
                <div class="bkx-service-card">
                    <?php if ($settings['show_image'] === 'yes') : ?>
                        <div class="bkx-service-image">
                            <?php echo get_the_post_thumbnail($service->ID, 'medium'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="bkx-service-content">
                        <?php if ($settings['show_title'] === 'yes') : ?>
                            <h3 class="bkx-service-title">
                                <a href="<?php echo get_permalink($service->ID); ?>">
                                    <?php echo esc_html($service->name); ?>
                                </a>
                            </h3>
                        <?php endif; ?>

                        <?php if ($settings['show_excerpt'] === 'yes') : ?>
                            <div class="bkx-service-excerpt">
                                <?php echo wp_trim_words($service->description, $settings['excerpt_length']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="bkx-service-meta">
                            <?php if ($settings['show_price'] === 'yes') : ?>
                                <span class="bkx-service-price">
                                    <?php echo bkx_format_price($service->price); ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($settings['show_duration'] === 'yes') : ?>
                                <span class="bkx-service-duration">
                                    <?php echo esc_html($service->duration . ' min'); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($settings['show_button'] === 'yes') : ?>
                            <a href="<?php echo bkx_get_booking_url($service->ID); ?>"
                               class="bkx-book-button">
                                <?php echo esc_html($settings['button_text']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
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

### Calendar Widget
```php
class CalendarWidget extends Widget_Base {

    public function get_name() {
        return 'bkx_calendar';
    }

    public function get_title() {
        return __('Availability Calendar', 'bookingx-elementor');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_categories() {
        return ['bookingx'];
    }

    public function get_script_depends() {
        return ['bkx-calendar'];
    }

    public function get_style_depends() {
        return ['bkx-calendar'];
    }

    protected function register_controls() {
        // Calendar Settings
        $this->start_controls_section(
            'section_calendar_settings',
            [
                'label' => __('Calendar Settings', 'bookingx-elementor'),
            ]
        );

        $this->add_control(
            'calendar_view',
            [
                'label' => __('Default View', 'bookingx-elementor'),
                'type' => Controls_Manager::SELECT,
                'default' => 'month',
                'options' => [
                    'month' => __('Month', 'bookingx-elementor'),
                    'week' => __('Week', 'bookingx-elementor'),
                    'day' => __('Day', 'bookingx-elementor'),
                ],
            ]
        );

        $this->add_control(
            'show_legend',
            [
                'label' => __('Show Legend', 'bookingx-elementor'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'enable_booking_click',
            [
                'label' => __('Enable Click to Book', 'bookingx-elementor'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Style Controls
        $this->start_controls_section(
            'section_calendar_style',
            [
                'label' => __('Calendar Style', 'bookingx-elementor'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'available_color',
            [
                'label' => __('Available Color', 'bookingx-elementor'),
                'type' => Controls_Manager::COLOR,
                'default' => '#4CAF50',
                'selectors' => [
                    '{{WRAPPER}} .bkx-calendar .available' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'booked_color',
            [
                'label' => __('Booked Color', 'bookingx-elementor'),
                'type' => Controls_Manager::COLOR,
                'default' => '#F44336',
                'selectors' => [
                    '{{WRAPPER}} .bkx-calendar .booked' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'unavailable_color',
            [
                'label' => __('Unavailable Color', 'bookingx-elementor'),
                'type' => Controls_Manager::COLOR,
                'default' => '#CCCCCC',
                'selectors' => [
                    '{{WRAPPER}} .bkx-calendar .unavailable' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        ?>
        <div class="bkx-elementor-calendar">
            <?php
            echo do_shortcode('[bkx_calendar
                view="' . esc_attr($settings['calendar_view']) . '"
                show_legend="' . esc_attr($settings['show_legend']) . '"
                enable_booking="' . esc_attr($settings['enable_booking_click']) . '"
            ]');
            ?>
        </div>
        <?php
    }
}
```

---

## 6. Dynamic Content Tags

### Service Dynamic Tags
```php
namespace BookingX\Addons\Elementor\DynamicTags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use Elementor\Controls_Manager;

class ServiceNameTag extends Tag {

    public function get_name() {
        return 'bkx-service-name';
    }

    public function get_title() {
        return __('Service Name', 'bookingx-elementor');
    }

    public function get_group() {
        return 'bookingx';
    }

    public function get_categories() {
        return [Module::TEXT_CATEGORY];
    }

    public function render() {
        $service = bkx_get_current_service();
        if ($service) {
            echo esc_html($service->name);
        }
    }
}

class ServicePriceTag extends Tag {

    public function get_name() {
        return 'bkx-service-price';
    }

    public function get_title() {
        return __('Service Price', 'bookingx-elementor');
    }

    public function get_group() {
        return 'bookingx';
    }

    public function get_categories() {
        return [Module::TEXT_CATEGORY];
    }

    protected function register_controls() {
        $this->add_control(
            'format',
            [
                'label' => __('Format', 'bookingx-elementor'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'plain' => __('Plain Number', 'bookingx-elementor'),
                    'formatted' => __('Formatted Price', 'bookingx-elementor'),
                ],
                'default' => 'formatted',
            ]
        );
    }

    public function render() {
        $service = bkx_get_current_service();
        if ($service) {
            $settings = $this->get_settings();
            if ($settings['format'] === 'formatted') {
                echo bkx_format_price($service->price);
            } else {
                echo esc_html($service->price);
            }
        }
    }
}

class ServiceDurationTag extends Tag {

    public function get_name() {
        return 'bkx-service-duration';
    }

    public function get_title() {
        return __('Service Duration', 'bookingx-elementor');
    }

    public function get_group() {
        return 'bookingx';
    }

    public function get_categories() {
        return [Module::TEXT_CATEGORY];
    }

    public function render() {
        $service = bkx_get_current_service();
        if ($service) {
            echo esc_html($service->duration . ' minutes');
        }
    }
}

/**
 * Register dynamic tags
 */
add_action('elementor/dynamic_tags/register_tags', 'bkx_register_dynamic_tags');
function bkx_register_dynamic_tags($dynamic_tags) {
    // Register tag group
    $dynamic_tags->register_group('bookingx', [
        'title' => __('BookingX', 'bookingx-elementor'),
    ]);

    // Register tags
    $dynamic_tags->register(new ServiceNameTag());
    $dynamic_tags->register(new ServicePriceTag());
    $dynamic_tags->register(new ServiceDurationTag());
    $dynamic_tags->register(new ServiceDescriptionTag());
    $dynamic_tags->register(new StaffNameTag());
    $dynamic_tags->register(new LocationAddressTag());
}
```

---

## 7. Template Library

### Pre-built Templates
```php
namespace BookingX\Addons\Elementor;

class TemplateLibrary {

    private $templates = [];

    public function __construct() {
        $this->init_templates();
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_library_scripts']);
        add_action('elementor/ajax/register_actions', [$this, 'register_ajax_actions']);
    }

    private function init_templates() {
        $this->templates = [
            'booking-page-full' => [
                'title' => __('Full Booking Page', 'bookingx-elementor'),
                'category' => 'booking-pages',
                'thumbnail' => BKX_ELEMENTOR_URL . 'assets/templates/booking-full.jpg',
                'content' => $this->get_template_content('booking-page-full'),
            ],
            'service-showcase' => [
                'title' => __('Service Showcase', 'bookingx-elementor'),
                'category' => 'service-pages',
                'thumbnail' => BKX_ELEMENTOR_URL . 'assets/templates/service-showcase.jpg',
                'content' => $this->get_template_content('service-showcase'),
            ],
            'staff-profiles' => [
                'title' => __('Staff Profiles', 'bookingx-elementor'),
                'category' => 'staff-pages',
                'thumbnail' => BKX_ELEMENTOR_URL . 'assets/templates/staff-profiles.jpg',
                'content' => $this->get_template_content('staff-profiles'),
            ],
            'calendar-view' => [
                'title' => __('Calendar Booking', 'bookingx-elementor'),
                'category' => 'booking-pages',
                'thumbnail' => BKX_ELEMENTOR_URL . 'assets/templates/calendar-view.jpg',
                'content' => $this->get_template_content('calendar-view'),
            ],
        ];
    }

    public function get_templates() {
        return $this->templates;
    }

    public function get_template_content($template_id) {
        $file = BKX_ELEMENTOR_PATH . 'templates/' . $template_id . '.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        }
        return [];
    }

    public function register_ajax_actions($ajax) {
        $ajax->register_ajax_action('get_bkx_templates', [$this, 'ajax_get_templates']);
        $ajax->register_ajax_action('import_bkx_template', [$this, 'ajax_import_template']);
    }

    public function ajax_get_templates() {
        return $this->get_templates();
    }

    public function ajax_import_template($data) {
        $template_id = $data['template_id'];
        if (isset($this->templates[$template_id])) {
            return $this->templates[$template_id]['content'];
        }
        return [];
    }
}
```

---

## 8. Theme Builder Integration

### Custom Post Type Templates
```php
/**
 * Register BookingX templates for Theme Builder
 */
add_action('elementor/theme/register_locations', 'bkx_register_elementor_locations');
function bkx_register_elementor_locations($elementor_theme_manager) {
    $elementor_theme_manager->register_location('bkx_single_service');
    $elementor_theme_manager->register_location('bkx_service_archive');
    $elementor_theme_manager->register_location('bkx_single_staff');
    $elementor_theme_manager->register_location('bkx_single_location');
}

/**
 * Add template conditions for BookingX
 */
add_action('elementor/theme/register_conditions', 'bkx_register_elementor_conditions');
function bkx_register_elementor_conditions($conditions_manager) {
    // Service post type condition
    $conditions_manager->get_condition('post')->register_sub_condition(
        new BKX_Service_Condition()
    );

    // Staff post type condition
    $conditions_manager->get_condition('post')->register_sub_condition(
        new BKX_Staff_Condition()
    );

    // Location post type condition
    $conditions_manager->get_condition('post')->register_sub_condition(
        new BKX_Location_Condition()
    );
}

/**
 * Service condition class
 */
class BKX_Service_Condition extends \ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base {

    public static function get_type() {
        return 'singular';
    }

    public function get_name() {
        return 'bkx_service';
    }

    public function get_label() {
        return __('Service', 'bookingx-elementor');
    }

    public function check($args) {
        return is_singular('bkx_service');
    }
}
```

---

## 9. Responsive Design Controls

### Mobile-First Approach
```php
/**
 * Add responsive controls for all widgets
 */
protected function add_responsive_layout_controls() {
    $this->add_responsive_control(
        'mobile_columns',
        [
            'label' => __('Columns', 'bookingx-elementor'),
            'type' => Controls_Manager::SELECT,
            'devices' => ['desktop', 'tablet', 'mobile'],
            'desktop_default' => '3',
            'tablet_default' => '2',
            'mobile_default' => '1',
            'options' => range(1, 6),
            'selectors' => [
                '{{WRAPPER}} .bkx-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
            ],
        ]
    );

    $this->add_responsive_control(
        'mobile_padding',
        [
            'label' => __('Padding', 'bookingx-elementor'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'devices' => ['desktop', 'tablet', 'mobile'],
            'selectors' => [
                '{{WRAPPER}} .bkx-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]
    );

    $this->add_responsive_control(
        'mobile_font_size',
        [
            'label' => __('Font Size', 'bookingx-elementor'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', 'rem'],
            'devices' => ['desktop', 'tablet', 'mobile'],
            'range' => [
                'px' => [
                    'min' => 10,
                    'max' => 100,
                ],
            ],
            'selectors' => [
                '{{WRAPPER}} .bkx-widget' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]
    );
}
```

---

## 10. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Plugin structure
- [ ] Widget registration system
- [ ] Category registration
- [ ] Base widget class

### Phase 2: Core Widgets (Week 3-5)
- [ ] Booking Form widget
- [ ] Service Grid widget
- [ ] Calendar widget
- [ ] Staff Grid widget

### Phase 3: Advanced Widgets (Week 6-7)
- [ ] Review widgets
- [ ] Info widgets
- [ ] Dynamic content tags

### Phase 4: Theme Builder (Week 8)
- [ ] Template locations
- [ ] Conditions
- [ ] Dynamic templates

### Phase 5: Templates (Week 9)
- [ ] Pre-built templates
- [ ] Template library
- [ ] Import/export

### Phase 6: Testing & Launch (Week 10-11)
- [ ] Testing
- [ ] Documentation
- [ ] Release

**Total Estimated Timeline:** 11 weeks (2.75 months)

---

## 11. Success Metrics

### Technical Metrics
- Widget load time: <100ms
- Editor performance: No lag
- Mobile responsive: 100%
- Browser compatibility: 98%+

### Business Metrics
- Activation rate: >35%
- Monthly active rate: >70%
- Customer satisfaction: >4.6/5

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development
