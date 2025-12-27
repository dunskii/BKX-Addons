# Gravity Forms Integration Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Gravity Forms Integration
**Price:** $59
**Category:** WordPress Ecosystem
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+
**Gravity Forms Version:** 2.5+

### Description
Advanced Gravity Forms integration enabling sophisticated booking workflows with pre-populated data, custom field mapping, conditional logic, and seamless form submission handling. Extend booking forms with custom fields, collect additional customer information, and automate booking creation from form submissions.

### Value Proposition
- Extend booking forms with unlimited custom fields
- Pre-populate forms with booking and user data
- Advanced conditional logic for dynamic forms
- Automatic booking creation from submissions
- Custom field mapping to booking meta
- Multi-page form support
- File upload integration
- Payment gateway compatibility
- Entry management and reporting

---

## 2. Features & Requirements

### Core Features
1. **Custom Booking Fields**
   - Add custom fields to booking forms
   - Field types: text, textarea, select, radio, checkbox
   - Date/time pickers
   - File uploads
   - Calculations
   - Signature fields
   - Address fields
   - Phone fields

2. **Pre-populated Data**
   - Auto-fill user information
   - Service details pre-population
   - Staff information
   - Location data
   - Pricing information
   - Availability data
   - Previous booking history

3. **Custom Field Mapping**
   - Map form fields to booking fields
   - Map to customer profile fields
   - Map to custom meta data
   - Dynamic field population
   - Field validation rules
   - Required field enforcement

4. **Conditional Logic**
   - Show/hide fields based on selections
   - Service-specific fields
   - Location-specific requirements
   - Staff-specific questions
   - Price-based conditions
   - Time-based rules
   - User role conditions

5. **Form Submission Handling**
   - Automatic booking creation
   - Status assignment
   - Payment processing trigger
   - Email notifications
   - Confirmation messages
   - Redirect to booking page
   - Entry to booking sync

6. **Payment Integration**
   - Gravity Forms payment add-ons
   - Stripe integration
   - PayPal integration
   - Square integration
   - Authorize.net
   - Product fields
   - Price calculations

7. **Advanced Features**
   - Multi-page booking forms
   - Save and continue later
   - Partial entries
   - Form analytics
   - Entry notes
   - Duplicate detection
   - Spam protection

### User Roles & Permissions
- **Admin:** Full form configuration access
- **Form Manager:** Create and edit forms
- **Editor:** View form entries
- **Staff:** View assigned booking entries
- **Customer:** Submit forms only

---

## 3. Technical Specifications

### Technology Stack
- **Gravity Forms:** 2.5+
- **Gravity Forms API:** Core hooks and filters
- **JavaScript:** Form interaction
- **PHP:** 7.4+ (8.0+ recommended)
- **MySQL:** Entry storage

### Dependencies
- BookingX Core 2.0+
- Gravity Forms 2.5+
- WordPress 5.8+
- PHP 7.4+

### Gravity Forms Hooks Architecture
```php
// Form Display Hooks
add_filter('gform_pre_render', 'bkx_pre_populate_form');
add_filter('gform_field_value_service_id', 'bkx_populate_service_id');
add_filter('gform_field_value_user_email', 'bkx_populate_user_email');

// Validation Hooks
add_filter('gform_field_validation', 'bkx_validate_booking_fields', 10, 4);
add_filter('gform_validation', 'bkx_validate_availability', 10, 2);

// Submission Hooks
add_action('gform_after_submission', 'bkx_create_booking_from_entry', 10, 2);
add_action('gform_post_payment_completed', 'bkx_confirm_paid_booking', 10, 2);
add_action('gform_post_payment_refunded', 'bkx_handle_refund', 10, 2);

// Entry Hooks
add_filter('gform_entry_meta', 'bkx_add_entry_meta', 10, 2);
add_action('gform_delete_entry', 'bkx_handle_entry_deletion', 10, 2);

// Field Rendering
add_filter('gform_field_content', 'bkx_modify_field_content', 10, 5);
add_filter('gform_field_css_class', 'bkx_add_custom_classes', 10, 3);

// Conditional Logic
add_filter('gform_pre_render', 'bkx_apply_conditional_logic');
add_filter('gform_form_tag', 'bkx_add_form_attributes', 10, 2);
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────────────────────────────┐
│           BookingX Core                     │
│  - Booking Engine                           │
│  - Service Management                       │
│  - Availability System                      │
│  - Customer Data                            │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│     Gravity Forms Integration Layer         │
│  - Form Pre-population                      │
│  - Field Mapping                            │
│  - Validation                               │
│  - Entry Processing                         │
│  - Booking Creation                         │
└────────────────┬────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────┐
│         Gravity Forms Core                  │
│  - Form Builder                             │
│  - Entry Management                         │
│  - Conditional Logic                        │
│  - Payment Processing                       │
└─────────────────────────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\GravityForms;

class GravityFormsIntegration {
    - init()
    - register_hooks()
    - register_form_settings()
    - register_field_types()
}

class FormPrePopulator {
    - populate_service_fields()
    - populate_user_fields()
    - populate_booking_fields()
    - populate_custom_fields()
    - get_dynamic_values()
}

class FieldMapper {
    - map_form_to_booking()
    - map_custom_fields()
    - get_field_mapping_config()
    - save_mapping()
    - validate_mapping()
}

class ValidationHandler {
    - validate_availability()
    - validate_service_selection()
    - validate_date_time()
    - validate_staff_availability()
    - validate_custom_fields()
}

class EntryProcessor {
    - create_booking_from_entry()
    - update_booking_from_entry()
    - sync_entry_to_booking()
    - handle_payment_completion()
    - handle_entry_deletion()
}

class ConditionalLogic {
    - apply_service_conditions()
    - apply_location_conditions()
    - apply_price_conditions()
    - apply_availability_conditions()
}

class PaymentHandler {
    - process_booking_payment()
    - handle_payment_callback()
    - update_booking_status()
    - process_refund()
}

class CustomFields {
    - register_booking_fields()
    - render_service_selector()
    - render_date_time_picker()
    - render_staff_selector()
    - validate_custom_fields()
}
```

---

## 5. Database Schema

### Table: `bkx_gf_form_settings`
```sql
CREATE TABLE bkx_gf_form_settings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    enable_booking_creation TINYINT(1) DEFAULT 1,
    field_mapping JSON,
    default_booking_status VARCHAR(50) DEFAULT 'pending',
    auto_confirm_on_payment TINYINT(1) DEFAULT 1,
    send_notifications TINYINT(1) DEFAULT 1,
    redirect_to_booking TINYINT(1) DEFAULT 1,
    custom_settings LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX form_id_idx (form_id),
    UNIQUE KEY form_id_unique (form_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_gf_entry_bookings`
```sql
CREATE TABLE bkx_gf_entry_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id INT UNSIGNED NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    entry_data LONGTEXT,
    sync_status VARCHAR(50) DEFAULT 'synced',
    last_synced_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX entry_id_idx (entry_id),
    INDEX booking_id_idx (booking_id),
    INDEX form_id_idx (form_id),
    UNIQUE KEY entry_booking_unique (entry_id, booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_gf_field_mapping`
```sql
CREATE TABLE bkx_gf_field_mapping (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    gf_field_id VARCHAR(20) NOT NULL,
    bkx_field_type VARCHAR(50) NOT NULL,
    bkx_field_key VARCHAR(100) NOT NULL,
    transformation_rule TEXT,
    is_required TINYINT(1) DEFAULT 0,
    validation_rule TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX form_id_idx (form_id),
    INDEX field_type_idx (bkx_field_type),
    UNIQUE KEY form_field_unique (form_id, gf_field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Form Pre-population Implementation

### Pre-populate Booking Fields
```php
namespace BookingX\Addons\GravityForms;

class FormPrePopulator {

    /**
     * Pre-populate form fields with booking data
     */
    public function populate_form($form) {
        $service_id = isset($_GET['service_id']) ? absint($_GET['service_id']) : 0;
        $staff_id = isset($_GET['staff_id']) ? absint($_GET['staff_id']) : 0;

        foreach ($form['fields'] as &$field) {
            switch ($field->type) {
                case 'bkx_service_selector':
                    $field->defaultValue = $service_id;
                    break;

                case 'bkx_staff_selector':
                    $field->defaultValue = $staff_id;
                    break;

                case 'email':
                    if (is_user_logged_in()) {
                        $field->defaultValue = wp_get_current_user()->user_email;
                    }
                    break;
            }
        }

        return $form;
    }

    /**
     * Populate service ID field
     */
    public function populate_service_id($value) {
        return isset($_GET['service_id']) ? absint($_GET['service_id']) : $value;
    }

    /**
     * Populate user email
     */
    public function populate_user_email($value) {
        if (is_user_logged_in()) {
            return wp_get_current_user()->user_email;
        }
        return $value;
    }

    /**
     * Populate user name
     */
    public function populate_user_name($value) {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return $user->first_name . ' ' . $user->last_name;
        }
        return $value;
    }

    /**
     * Populate service details
     */
    public function populate_service_details($value) {
        $service_id = isset($_GET['service_id']) ? absint($_GET['service_id']) : 0;
        if ($service_id) {
            $service = bkx_get_service($service_id);
            if ($service) {
                return wp_json_encode([
                    'id' => $service->ID,
                    'name' => $service->name,
                    'price' => $service->price,
                    'duration' => $service->duration,
                ]);
            }
        }
        return $value;
    }
}

/**
 * Register pre-population hooks
 */
add_filter('gform_pre_render', 'bkx_gf_pre_populate_form');
function bkx_gf_pre_populate_form($form) {
    $populator = new BookingX\Addons\GravityForms\FormPrePopulator();
    return $populator->populate_form($form);
}

add_filter('gform_field_value_service_id', 'bkx_gf_populate_service_id');
function bkx_gf_populate_service_id($value) {
    $populator = new BookingX\Addons\GravityForms\FormPrePopulator();
    return $populator->populate_service_id($value);
}

add_filter('gform_field_value_user_email', 'bkx_gf_populate_user_email');
function bkx_gf_populate_user_email($value) {
    $populator = new BookingX\Addons\GravityForms\FormPrePopulator();
    return $populator->populate_user_email($value);
}

add_filter('gform_field_value_user_name', 'bkx_gf_populate_user_name');
function bkx_gf_populate_user_name($value) {
    $populator = new BookingX\Addons\GravityForms\FormPrePopulator();
    return $populator->populate_user_name($value);
}
```

---

## 7. Custom Field Types

### Service Selector Field
```php
namespace BookingX\Addons\GravityForms\Fields;

use GF_Field;

class ServiceSelectorField extends GF_Field {

    public $type = 'bkx_service_selector';

    public function get_form_editor_field_title() {
        return esc_html__('Service Selector', 'bookingx-gf');
    }

    public function get_form_editor_button() {
        return [
            'group' => 'bookingx_fields',
            'text' => $this->get_form_editor_field_title(),
        ];
    }

    public function get_field_input($form, $value = '', $entry = null) {
        $form_id = absint($form['id']);
        $is_entry_detail = $this->is_entry_detail();
        $is_form_editor = $this->is_form_editor();

        $id = (int)$this->id;
        $field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";

        // Get all services
        $services = bkx_get_all_services();

        $html = sprintf(
            "<select name='input_%d' id='%s' class='bkx-service-selector medium' %s>",
            $id,
            $field_id,
            $this->get_tabindex()
        );

        $html .= '<option value="">' . esc_html__('Select a service', 'bookingx-gf') . '</option>';

        foreach ($services as $service) {
            $selected = selected($value, $service->ID, false);
            $html .= sprintf(
                '<option value="%d" data-price="%s" data-duration="%d" %s>%s - %s</option>',
                $service->ID,
                esc_attr($service->price),
                $service->duration,
                $selected,
                esc_html($service->name),
                bkx_format_price($service->price)
            );
        }

        $html .= '</select>';

        return $html;
    }

    public function validate($value, $form) {
        if ($this->isRequired && empty($value)) {
            $this->failed_validation = true;
            $this->validation_message = empty($this->errorMessage) ?
                esc_html__('Please select a service.', 'bookingx-gf') :
                $this->errorMessage;
        } else if (!empty($value)) {
            // Validate that service exists
            $service = bkx_get_service($value);
            if (!$service) {
                $this->failed_validation = true;
                $this->validation_message = esc_html__('Invalid service selected.', 'bookingx-gf');
            }
        }
    }

    public function get_value_entry_detail($value, $currency = '', $use_text = false, $format = 'html', $media = 'screen') {
        if (empty($value)) {
            return '';
        }

        $service = bkx_get_service($value);
        if (!$service) {
            return $value;
        }

        return sprintf(
            '%s (%s - %d min)',
            esc_html($service->name),
            bkx_format_price($service->price),
            $service->duration
        );
    }
}
```

### Date Time Picker Field
```php
class DateTimePickerField extends GF_Field {

    public $type = 'bkx_datetime_picker';

    public function get_form_editor_field_title() {
        return esc_html__('Booking Date & Time', 'bookingx-gf');
    }

    public function get_form_editor_button() {
        return [
            'group' => 'bookingx_fields',
            'text' => $this->get_form_editor_field_title(),
        ];
    }

    public function get_field_input($form, $value = '', $entry = null) {
        $form_id = absint($form['id']);
        $id = (int)$this->id;
        $field_id = 'input_' . $form_id . "_$id";

        // Enqueue date picker assets
        wp_enqueue_script('bkx-datepicker');
        wp_enqueue_style('bkx-datepicker');

        $html = sprintf(
            '<div class="bkx-datetime-picker-container" data-form-id="%d" data-field-id="%d">',
            $form_id,
            $id
        );

        // Date input
        $html .= sprintf(
            '<input type="text" name="input_%d[date]" id="%s_date" class="bkx-date-picker medium" placeholder="%s" value="%s" %s />',
            $id,
            $field_id,
            esc_attr__('Select date', 'bookingx-gf'),
            esc_attr($value),
            $this->get_tabindex()
        );

        // Time slots container
        $html .= '<div class="bkx-time-slots" id="' . $field_id . '_time_slots"></div>';

        // Hidden time input
        $html .= sprintf(
            '<input type="hidden" name="input_%d[time]" id="%s_time" />',
            $id,
            $field_id
        );

        $html .= '</div>';

        return $html;
    }

    public function validate($value, $form) {
        if ($this->isRequired) {
            if (empty($value['date']) || empty($value['time'])) {
                $this->failed_validation = true;
                $this->validation_message = esc_html__('Please select both date and time.', 'bookingx-gf');
                return;
            }
        }

        if (!empty($value['date']) && !empty($value['time'])) {
            // Validate availability
            $service_id = $this->get_service_id_from_form($form);
            $staff_id = $this->get_staff_id_from_form($form);

            $is_available = bkx_check_availability(
                $service_id,
                $value['date'],
                $value['time'],
                $staff_id
            );

            if (!$is_available) {
                $this->failed_validation = true;
                $this->validation_message = esc_html__('Selected time slot is not available.', 'bookingx-gf');
            }
        }
    }

    private function get_service_id_from_form($form) {
        // Get service ID from service selector field
        $service_field = $this->find_field_by_type($form, 'bkx_service_selector');
        if ($service_field) {
            return rgpost('input_' . $service_field->id);
        }
        return 0;
    }

    private function find_field_by_type($form, $type) {
        foreach ($form['fields'] as $field) {
            if ($field->type === $type) {
                return $field;
            }
        }
        return null;
    }
}

/**
 * Register custom fields
 */
add_action('gform_loaded', 'bkx_gf_register_custom_fields', 5);
function bkx_gf_register_custom_fields() {
    if (!method_exists('GF_Fields', 'register')) {
        return;
    }

    GF_Fields::register(new BookingX\Addons\GravityForms\Fields\ServiceSelectorField());
    GF_Fields::register(new BookingX\Addons\GravityForms\Fields\DateTimePickerField());
    GF_Fields::register(new BookingX\Addons\GravityForms\Fields\StaffSelectorField());
}

/**
 * Add custom field group
 */
add_filter('gform_add_field_buttons', 'bkx_gf_add_field_group');
function bkx_gf_add_field_group($field_groups) {
    $field_groups[] = [
        'name' => 'bookingx_fields',
        'label' => esc_html__('BookingX Fields', 'bookingx-gf'),
        'fields' => [],
    ];

    return $field_groups;
}
```

---

## 8. Field Mapping Configuration

### Mapping Interface
```php
namespace BookingX\Addons\GravityForms;

class FieldMapper {

    /**
     * Get field mapping configuration for a form
     */
    public function get_mapping_config($form_id) {
        global $wpdb;

        $mappings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bkx_gf_field_mapping WHERE form_id = %d",
            $form_id
        ), ARRAY_A);

        return $mappings;
    }

    /**
     * Save field mapping
     */
    public function save_mapping($form_id, $mappings) {
        global $wpdb;
        $table = $wpdb->prefix . 'bkx_gf_field_mapping';

        // Delete existing mappings
        $wpdb->delete($table, ['form_id' => $form_id]);

        // Insert new mappings
        foreach ($mappings as $mapping) {
            $wpdb->insert($table, [
                'form_id' => $form_id,
                'gf_field_id' => $mapping['gf_field_id'],
                'bkx_field_type' => $mapping['bkx_field_type'],
                'bkx_field_key' => $mapping['bkx_field_key'],
                'transformation_rule' => $mapping['transformation_rule'] ?? '',
                'is_required' => $mapping['is_required'] ?? 0,
                'validation_rule' => $mapping['validation_rule'] ?? '',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
        }

        return true;
    }

    /**
     * Map form entry to booking data
     */
    public function map_entry_to_booking($entry, $form) {
        $form_id = $form['id'];
        $mappings = $this->get_mapping_config($form_id);

        $booking_data = [
            'customer' => [],
            'booking' => [],
            'meta' => [],
        ];

        foreach ($mappings as $mapping) {
            $field_value = rgar($entry, $mapping['gf_field_id']);

            // Apply transformation if exists
            if (!empty($mapping['transformation_rule'])) {
                $field_value = $this->apply_transformation($field_value, $mapping['transformation_rule']);
            }

            // Map to appropriate category
            switch ($mapping['bkx_field_type']) {
                case 'customer':
                    $booking_data['customer'][$mapping['bkx_field_key']] = $field_value;
                    break;

                case 'booking':
                    $booking_data['booking'][$mapping['bkx_field_key']] = $field_value;
                    break;

                case 'meta':
                    $booking_data['meta'][$mapping['bkx_field_key']] = $field_value;
                    break;
            }
        }

        return $booking_data;
    }

    /**
     * Apply transformation rule to field value
     */
    private function apply_transformation($value, $rule) {
        switch ($rule) {
            case 'to_uppercase':
                return strtoupper($value);

            case 'to_lowercase':
                return strtolower($value);

            case 'sanitize_phone':
                return preg_replace('/[^0-9+]/', '', $value);

            case 'format_date':
                return date('Y-m-d', strtotime($value));

            default:
                return $value;
        }
    }

    /**
     * Get available booking fields for mapping
     */
    public function get_bookable_fields() {
        return [
            'customer' => [
                'first_name' => __('First Name', 'bookingx-gf'),
                'last_name' => __('Last Name', 'bookingx-gf'),
                'email' => __('Email', 'bookingx-gf'),
                'phone' => __('Phone', 'bookingx-gf'),
                'address' => __('Address', 'bookingx-gf'),
                'city' => __('City', 'bookingx-gf'),
                'state' => __('State', 'bookingx-gf'),
                'zip' => __('Zip Code', 'bookingx-gf'),
                'country' => __('Country', 'bookingx-gf'),
            ],
            'booking' => [
                'service_id' => __('Service', 'bookingx-gf'),
                'staff_id' => __('Staff Member', 'bookingx-gf'),
                'location_id' => __('Location', 'bookingx-gf'),
                'booking_date' => __('Booking Date', 'bookingx-gf'),
                'booking_time' => __('Booking Time', 'bookingx-gf'),
                'duration' => __('Duration', 'bookingx-gf'),
                'price' => __('Price', 'bookingx-gf'),
                'notes' => __('Notes', 'bookingx-gf'),
            ],
            'meta' => [
                'custom_field_1' => __('Custom Field 1', 'bookingx-gf'),
                'custom_field_2' => __('Custom Field 2', 'bookingx-gf'),
                'special_requests' => __('Special Requests', 'bookingx-gf'),
                'referral_source' => __('Referral Source', 'bookingx-gf'),
            ],
        ];
    }
}
```

---

## 9. Entry to Booking Processing

### Automatic Booking Creation
```php
namespace BookingX\Addons\GravityForms;

class EntryProcessor {

    /**
     * Create booking from form entry
     */
    public function create_booking_from_entry($entry, $form) {
        // Check if booking creation is enabled for this form
        $settings = $this->get_form_settings($form['id']);
        if (!$settings['enable_booking_creation']) {
            return;
        }

        // Map entry data to booking
        $mapper = new FieldMapper();
        $booking_data = $mapper->map_entry_to_booking($entry, $form);

        // Create or update customer
        $customer_id = $this->get_or_create_customer($booking_data['customer']);

        // Prepare booking data
        $booking_args = array_merge($booking_data['booking'], [
            'customer_id' => $customer_id,
            'status' => $settings['default_booking_status'],
            'payment_status' => 'pending',
            'source' => 'gravity_forms',
            'source_id' => $entry['id'],
        ]);

        // Create booking
        $booking_id = bkx_create_booking($booking_args);

        if ($booking_id) {
            // Add custom meta
            foreach ($booking_data['meta'] as $key => $value) {
                bkx_update_booking_meta($booking_id, $key, $value);
            }

            // Link entry to booking
            $this->link_entry_to_booking($entry['id'], $form['id'], $booking_id, $entry);

            // Add entry note
            $this->add_entry_note($entry['id'], sprintf(
                __('Booking created: #%d', 'bookingx-gf'),
                $booking_id
            ));

            // Send notifications if enabled
            if ($settings['send_notifications']) {
                do_action('bkx_booking_created', $booking_id, $booking_args);
            }

            // Handle payment if required
            if ($entry['payment_status'] === 'Approved') {
                $this->handle_payment_completion($entry, $form, $booking_id);
            }

            return $booking_id;
        }

        return false;
    }

    /**
     * Get or create customer from entry data
     */
    private function get_or_create_customer($customer_data) {
        // Check if customer exists by email
        $existing_customer = bkx_get_customer_by_email($customer_data['email']);

        if ($existing_customer) {
            // Update customer data
            bkx_update_customer($existing_customer->ID, $customer_data);
            return $existing_customer->ID;
        }

        // Create new customer
        return bkx_create_customer($customer_data);
    }

    /**
     * Link entry to booking
     */
    private function link_entry_to_booking($entry_id, $form_id, $booking_id, $entry_data) {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'bkx_gf_entry_bookings', [
            'entry_id' => $entry_id,
            'form_id' => $form_id,
            'booking_id' => $booking_id,
            'entry_data' => wp_json_encode($entry_data),
            'sync_status' => 'synced',
            'last_synced_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
        ]);

        // Add entry meta
        gform_add_meta($entry_id, 'booking_id', $booking_id);
    }

    /**
     * Handle payment completion
     */
    public function handle_payment_completion($entry, $form, $booking_id = null) {
        if (!$booking_id) {
            $booking_id = gform_get_meta($entry['id'], 'booking_id');
        }

        if ($booking_id) {
            // Update booking status
            bkx_update_booking_status($booking_id, 'confirmed');

            // Update payment status
            bkx_update_booking_meta($booking_id, 'payment_status', 'paid');
            bkx_update_booking_meta($booking_id, 'payment_method', $entry['payment_method']);
            bkx_update_booking_meta($booking_id, 'transaction_id', $entry['transaction_id']);

            // Trigger confirmation actions
            do_action('bkx_booking_confirmed', $booking_id);

            // Add note to entry
            $this->add_entry_note($entry['id'], __('Booking confirmed after payment', 'bookingx-gf'));
        }
    }

    /**
     * Handle entry deletion
     */
    public function handle_entry_deletion($entry_id, $entry) {
        // Get associated booking
        $booking_id = gform_get_meta($entry_id, 'booking_id');

        if ($booking_id) {
            $settings = $this->get_form_settings($entry['form_id']);

            if ($settings['cancel_booking_on_delete']) {
                // Cancel the booking
                bkx_update_booking_status($booking_id, 'cancelled');
                bkx_update_booking_meta($booking_id, 'cancellation_reason', 'Form entry deleted');
            }
        }
    }

    /**
     * Add note to entry
     */
    private function add_entry_note($entry_id, $note) {
        GFFormsModel::add_note($entry_id, 0, 'BookingX', $note);
    }

    /**
     * Get form settings
     */
    private function get_form_settings($form_id) {
        global $wpdb;

        $settings = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bkx_gf_form_settings WHERE form_id = %d",
            $form_id
        ), ARRAY_A);

        if (!$settings) {
            return $this->get_default_settings();
        }

        return array_merge($this->get_default_settings(), $settings);
    }

    /**
     * Get default settings
     */
    private function get_default_settings() {
        return [
            'enable_booking_creation' => true,
            'default_booking_status' => 'pending',
            'auto_confirm_on_payment' => true,
            'send_notifications' => true,
            'redirect_to_booking' => true,
            'cancel_booking_on_delete' => true,
        ];
    }
}

/**
 * Register entry processing hooks
 */
add_action('gform_after_submission', 'bkx_gf_process_entry', 10, 2);
function bkx_gf_process_entry($entry, $form) {
    $processor = new BookingX\Addons\GravityForms\EntryProcessor();
    $processor->create_booking_from_entry($entry, $form);
}

add_action('gform_post_payment_completed', 'bkx_gf_handle_payment_completion', 10, 2);
function bkx_gf_handle_payment_completion($entry, $action) {
    $processor = new BookingX\Addons\GravityForms\EntryProcessor();
    $processor->handle_payment_completion($entry, GFAPI::get_form($entry['form_id']));
}

add_action('gform_delete_entry', 'bkx_gf_handle_entry_deletion', 10, 2);
function bkx_gf_handle_entry_deletion($entry_id, $entry) {
    $processor = new BookingX\Addons\GravityForms\EntryProcessor();
    $processor->handle_entry_deletion($entry_id, $entry);
}
```

---

## 10. Conditional Logic Implementation

### Dynamic Conditional Logic
```php
/**
 * Apply conditional logic based on booking data
 */
add_filter('gform_pre_render', 'bkx_gf_apply_conditional_logic');
function bkx_gf_apply_conditional_logic($form) {
    $service_id = rgpost('input_service_selector');

    if ($service_id) {
        $service = bkx_get_service($service_id);

        // Show/hide fields based on service
        foreach ($form['fields'] as &$field) {
            // Show location field only for services with multiple locations
            if ($field->cssClass === 'bkx-location-field') {
                $locations = bkx_get_service_locations($service_id);
                if (count($locations) <= 1) {
                    $field->visibility = 'hidden';
                }
            }

            // Show special requirements field for specific services
            if ($field->cssClass === 'bkx-special-requirements') {
                if (in_array($service->category_id, [1, 3, 5])) { // Specific categories
                    $field->isRequired = true;
                }
            }

            // Show equipment field for outdoor services
            if ($field->cssClass === 'bkx-equipment-field') {
                if ($service->category_name === 'Outdoor Activities') {
                    $field->visibility = 'visible';
                } else {
                    $field->visibility = 'hidden';
                }
            }
        }
    }

    return $form;
}
```

---

## 11. Form Settings Interface

### Admin Settings Page
```php
/**
 * Add BookingX settings to Gravity Forms form settings
 */
add_filter('gform_form_settings', 'bkx_gf_add_form_settings', 10, 2);
function bkx_gf_add_form_settings($settings, $form) {
    $form_id = $form['id'];
    $processor = new BookingX\Addons\GravityForms\EntryProcessor();
    $current_settings = $processor->get_form_settings($form_id);

    $settings['BookingX'] = '
        <tr>
            <th><label for="bkx_enable_booking">' . esc_html__('Enable Booking Creation', 'bookingx-gf') . '</label></th>
            <td>
                <input type="checkbox" name="bkx_enable_booking" id="bkx_enable_booking" value="1" ' . checked($current_settings['enable_booking_creation'], true, false) . ' />
                <label for="bkx_enable_booking">' . esc_html__('Automatically create bookings from form submissions', 'bookingx-gf') . '</label>
            </td>
        </tr>
        <tr>
            <th><label for="bkx_booking_status">' . esc_html__('Default Booking Status', 'bookingx-gf') . '</label></th>
            <td>
                <select name="bkx_booking_status" id="bkx_booking_status">
                    <option value="pending" ' . selected($current_settings['default_booking_status'], 'pending', false) . '>' . esc_html__('Pending', 'bookingx-gf') . '</option>
                    <option value="confirmed" ' . selected($current_settings['default_booking_status'], 'confirmed', false) . '>' . esc_html__('Confirmed', 'bookingx-gf') . '</option>
                    <option value="approved" ' . selected($current_settings['default_booking_status'], 'approved', false) . '>' . esc_html__('Approved', 'bookingx-gf') . '</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="bkx_auto_confirm">' . esc_html__('Auto-confirm on Payment', 'bookingx-gf') . '</label></th>
            <td>
                <input type="checkbox" name="bkx_auto_confirm" id="bkx_auto_confirm" value="1" ' . checked($current_settings['auto_confirm_on_payment'], true, false) . ' />
                <label for="bkx_auto_confirm">' . esc_html__('Automatically confirm booking when payment is completed', 'bookingx-gf') . '</label>
            </td>
        </tr>
    ';

    return $settings;
}

/**
 * Save BookingX form settings
 */
add_filter('gform_pre_form_settings_save', 'bkx_gf_save_form_settings');
function bkx_gf_save_form_settings($form) {
    global $wpdb;

    $form_id = $form['id'];
    $table = $wpdb->prefix . 'bkx_gf_form_settings';

    $settings = [
        'form_id' => $form_id,
        'enable_booking_creation' => isset($_POST['bkx_enable_booking']) ? 1 : 0,
        'default_booking_status' => sanitize_text_field($_POST['bkx_booking_status'] ?? 'pending'),
        'auto_confirm_on_payment' => isset($_POST['bkx_auto_confirm']) ? 1 : 0,
        'updated_at' => current_time('mysql'),
    ];

    // Check if settings exist
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE form_id = %d",
        $form_id
    ));

    if ($exists) {
        $wpdb->update($table, $settings, ['form_id' => $form_id]);
    } else {
        $settings['created_at'] = current_time('mysql');
        $wpdb->insert($table, $settings);
    }

    return $form;
}
```

---

## 12. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Plugin structure
- [ ] Gravity Forms compatibility check

### Phase 2: Custom Fields (Week 3-4)
- [ ] Service selector field
- [ ] Date/time picker field
- [ ] Staff selector field
- [ ] Field registration

### Phase 3: Pre-population (Week 5)
- [ ] Pre-population system
- [ ] Dynamic value population
- [ ] User data integration

### Phase 4: Field Mapping (Week 6-7)
- [ ] Mapping interface
- [ ] Save/load mappings
- [ ] Transformation rules
- [ ] Validation

### Phase 5: Entry Processing (Week 8)
- [ ] Booking creation
- [ ] Customer management
- [ ] Entry linking
- [ ] Payment handling

### Phase 6: Testing & Launch (Week 9-10)
- [ ] Testing
- [ ] Documentation
- [ ] Release

**Total Estimated Timeline:** 10 weeks (2.5 months)

---

## 13. Success Metrics

### Technical Metrics
- Form submission success: >98%
- Booking creation accuracy: 100%
- Field validation accuracy: 100%
- Performance impact: <100ms

### Business Metrics
- Activation rate: >30%
- Monthly active rate: >60%
- Customer satisfaction: >4.5/5

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development
