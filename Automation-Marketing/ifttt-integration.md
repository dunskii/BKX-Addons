# IFTTT Integration - Development Documentation

## 1. Overview

**Add-on Name:** IFTTT Integration
**Price:** $99
**Category:** Automation & Marketing
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Seamless IFTTT (If This Then That) integration enabling BookingX to connect with smart home devices, IoT platforms, and 600+ services. Create automated workflows with conditional logic and trigger actions based on booking events.

### Value Proposition
- Connect with 600+ apps and services
- IoT device integration (Smart lights, locks, thermostats)
- Location-based automation
- Voice assistant integration (Alexa, Google Assistant)
- Simple conditional logic
- Mobile-first automation

---

## 2. Features & Requirements

### Core Features
1. **Trigger Events**
   - New booking created
   - Booking confirmed
   - Booking cancelled
   - Customer arrived (check-in)
   - Customer departed (check-out)
   - Payment completed
   - Reminder time reached
   - Service started
   - Service completed
   - Staff assigned

2. **Action Events**
   - Create booking
   - Update booking status
   - Send notification
   - Check customer in
   - Check customer out
   - Mark service complete
   - Trigger reminder
   - Update availability

3. **IoT Device Actions**
   - Smart lock control (unlock/lock)
   - Smart lighting (on/off/dimming)
   - Thermostat adjustment
   - Security system control
   - Camera notifications
   - Door sensors
   - Occupancy detection

4. **Conditional Logic**
   - Time-based conditions
   - Day of week filters
   - Service type filters
   - Customer type filters
   - Location-based triggers
   - Status-based conditions
   - Amount thresholds

5. **Location Services**
   - Geofencing triggers
   - Proximity detection
   - Location-based notifications
   - Distance calculations
   - Arrival detection

### User Roles & Permissions
- **Admin:** Full IFTTT configuration, all applet management
- **Manager:** Create applets, view activity
- **Staff:** View active applets only
- **Customer:** Personal applets via IFTTT app

---

## 3. Technical Specifications

### Technology Stack
- **Platform:** IFTTT Platform v3
- **Integration Type:** Webhook-based
- **Authentication:** OAuth 2.0
- **Webhook Format:** JSON
- **Real-time Protocol:** HTTPS POST

### Dependencies
- BookingX Core 2.0+
- WordPress REST API
- PHP cURL extension
- SSL certificate (required)
- IFTTT account (free or Pro)

### API Integration Points
```php
// IFTTT Webhook Endpoints
- POST /trigger/{event}/with/key/{key} (Trigger event)
- POST /v3/triggers/{trigger_id}/fields (Trigger fields)
- POST /v3/actions/{action_id}/fields (Action fields)

// BookingX IFTTT Endpoints
- /wp-json/bookingx/v1/ifttt/triggers (List triggers)
- /wp-json/bookingx/v1/ifttt/trigger/{event} (Fire trigger)
- /wp-json/bookingx/v1/ifttt/actions (List actions)
- /wp-json/bookingx/v1/ifttt/action/{action} (Execute action)
- /wp-json/bookingx/v1/ifttt/test (Test connection)
```

---

## 4. Architecture & Design

### System Architecture
```
┌──────────────────┐
│   BookingX Core  │
│   Event System   │
└────────┬─────────┘
         │
         ▼
┌─────────────────────────┐
│   IFTTT Integration     │
│   - Trigger Manager     │
│   - Action Handler      │
│   - Webhook Sender      │
└────────┬────────────────┘
         │
         ├────────────┬────────────┐
         ▼            ▼            ▼
┌──────────────┐ ┌──────────┐ ┌──────────────┐
│   Trigger    │ │  Action  │ │   Condition  │
│   Events     │ │ Executor │ │   Evaluator  │
└──────────────┘ └──────────┘ └──────────────┘
         │
         ▼
┌─────────────────────────┐
│   IFTTT Platform API    │
│   (Maker Webhooks)      │
└─────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\IFTTT;

class IFTTTIntegration {
    - init()
    - register_triggers()
    - register_actions()
    - authenticate()
    - get_webhook_key()
}

class IFTTTTriggerManager {
    - fire_trigger()
    - format_trigger_data()
    - send_webhook()
    - validate_trigger()
    - log_trigger_event()
}

class IFTTTActionHandler {
    - execute_action()
    - validate_action_data()
    - process_booking_action()
    - process_notification_action()
    - process_iot_action()
}

class IFTTTConditionEvaluator {
    - evaluate_conditions()
    - check_time_condition()
    - check_service_condition()
    - check_amount_condition()
    - check_location_condition()
}

class IFTTTWebhookSender {
    - send_maker_webhook()
    - format_payload()
    - handle_response()
    - retry_failed()
}

class IFTTTDeviceController {
    - control_smart_lock()
    - control_lights()
    - control_thermostat()
    - get_device_status()
}
```

---

## 5. Database Schema

### Table: `bkx_ifttt_applets`
```sql
CREATE TABLE bkx_ifttt_applets (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applet_id VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    trigger_event VARCHAR(100) NOT NULL,
    trigger_conditions LONGTEXT,
    action_type VARCHAR(100),
    action_params LONGTEXT,
    webhook_key VARCHAR(100),
    user_id BIGINT(20) UNSIGNED,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_triggered_at DATETIME,
    trigger_count INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX trigger_event_idx (trigger_event),
    INDEX user_id_idx (user_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_ifttt_trigger_log`
```sql
CREATE TABLE bkx_ifttt_trigger_log (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applet_id BIGINT(20) UNSIGNED,
    trigger_event VARCHAR(100) NOT NULL,
    trigger_data LONGTEXT,
    conditions_met TINYINT(1) DEFAULT 1,
    webhook_sent TINYINT(1) DEFAULT 0,
    response_code INT,
    response_body TEXT,
    execution_time FLOAT,
    created_at DATETIME NOT NULL,
    INDEX applet_id_idx (applet_id),
    INDEX trigger_event_idx (trigger_event),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_ifttt_iot_devices`
```sql
CREATE TABLE bkx_ifttt_iot_devices (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL UNIQUE,
    device_name VARCHAR(255) NOT NULL,
    device_type VARCHAR(50) NOT NULL,
    location VARCHAR(255),
    service_id BIGINT(20) UNSIGNED,
    webhook_key VARCHAR(100),
    device_config LONGTEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_triggered_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX device_type_idx (device_type),
    INDEX service_id_idx (service_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_ifttt_webhook_keys`
```sql
CREATE TABLE bkx_ifttt_webhook_keys (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    webhook_key VARCHAR(100) NOT NULL UNIQUE,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    label VARCHAR(100),
    last_used_at DATETIME,
    created_at DATETIME NOT NULL,
    INDEX webhook_key_idx (webhook_key),
    INDEX user_id_idx (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    'ifttt_enabled' => true,
    'webhook_key' => '',
    'auto_generate_keys' => true,
    'enable_iot_features' => true,
    'enable_location_services' => true,
    'log_trigger_events' => true,
    'log_retention_days' => 30,
    'webhook_timeout' => 15, // seconds
    'retry_failed_webhooks' => true,
    'max_retry_attempts' => 2,
    'allowed_triggers' => [
        'booking_created',
        'booking_confirmed',
        'booking_cancelled',
        'customer_arrived',
        'payment_completed',
    ],
    'allowed_actions' => [
        'create_booking',
        'update_status',
        'send_notification',
        'control_smart_lock',
        'control_lights',
    ],
    'location_radius_meters' => 100,
    'enable_voice_commands' => true,
]
```

---

## 7. Trigger Implementation

### Booking Confirmed Trigger
```php
public function trigger_booking_confirmed($booking_id) {
    $booking = $this->get_booking($booking_id);

    // Check if there are active applets for this trigger
    $applets = $this->get_active_applets('booking_confirmed');

    foreach ($applets as $applet) {
        // Evaluate conditions
        if (!$this->evaluate_conditions($applet, $booking)) {
            continue;
        }

        // Format trigger data
        $data = [
            'value1' => $booking->booking_number,
            'value2' => $booking->customer_name,
            'value3' => $booking->service_name,
        ];

        // Send webhook
        $this->send_ifttt_webhook($applet->webhook_key, 'booking_confirmed', $data);

        // Log trigger
        $this->log_trigger($applet->id, 'booking_confirmed', $data);
    }
}
```

### Customer Arrived Trigger (Location-Based)
```php
public function trigger_customer_arrived($booking_id, $location) {
    $booking = $this->get_booking($booking_id);
    $service = $this->get_service($booking->service_id);

    // Calculate distance from service location
    $distance = $this->calculate_distance(
        $location['lat'],
        $location['lng'],
        $service->latitude,
        $service->longitude
    );

    $radius = get_option('bkx_ifttt_location_radius_meters', 100);

    if ($distance > $radius) {
        return; // Not close enough
    }

    // Get applets for arrival trigger
    $applets = $this->get_active_applets('customer_arrived');

    foreach ($applets as $applet) {
        // Check if applet is for this service
        if ($applet->service_id && $applet->service_id != $service->id) {
            continue;
        }

        $data = [
            'value1' => $booking->customer_name,
            'value2' => $service->name,
            'value3' => date('Y-m-d H:i:s'),
        ];

        $this->send_ifttt_webhook($applet->webhook_key, 'customer_arrived', $data);

        // Trigger IoT devices (unlock door, turn on lights, etc.)
        $this->trigger_iot_devices($service->id, 'arrival');
    }
}
```

---

## 8. Action Implementation

### Create Booking Action
```php
public function action_create_booking($data) {
    // Parse IFTTT data
    $service_id = $data['service_id'];
    $customer_email = $data['customer_email'];
    $customer_name = $data['customer_name'];
    $datetime = $data['datetime'];

    // Validate
    if (!$this->validate_booking_data($data)) {
        return [
            'success' => false,
            'error' => 'Invalid booking data',
        ];
    }

    // Check availability
    $available = $this->check_availability($service_id, $datetime);
    if (!$available) {
        return [
            'success' => false,
            'error' => 'Time slot not available',
        ];
    }

    // Create booking
    $booking = new BookingX_Booking();
    $booking->service_id = $service_id;
    $booking->customer_email = $customer_email;
    $booking->customer_name = $customer_name;
    $booking->start_datetime = $datetime;
    $booking->status = 'pending';
    $booking->source = 'ifttt';

    $booking_id = $booking->save();

    return [
        'success' => true,
        'booking_id' => $booking_id,
        'booking_number' => $booking->booking_number,
    ];
}
```

### Smart Lock Control Action
```php
public function action_control_smart_lock($data) {
    $device_id = $data['device_id'];
    $action = $data['action']; // 'lock' or 'unlock'
    $duration = $data['duration'] ?? 0; // Auto-lock after X seconds

    // Get device configuration
    $device = $this->get_iot_device($device_id);

    if (!$device || $device->device_type !== 'smart_lock') {
        return [
            'success' => false,
            'error' => 'Invalid device or device type',
        ];
    }

    // Send IFTTT webhook to control lock
    $webhook_data = [
        'value1' => $action,
        'value2' => $device->device_name,
        'value3' => $duration,
    ];

    $result = $this->send_ifttt_webhook(
        $device->webhook_key,
        'control_smart_lock',
        $webhook_data
    );

    if ($result) {
        // Log the action
        $this->log_iot_action($device_id, $action);

        // Schedule auto-lock if needed
        if ($action === 'unlock' && $duration > 0) {
            wp_schedule_single_event(
                time() + $duration,
                'bookingx_ifttt_auto_lock',
                [$device_id]
            );
        }

        return ['success' => true];
    }

    return [
        'success' => false,
        'error' => 'Failed to control device',
    ];
}
```

### Smart Lighting Control Action
```php
public function action_control_lights($data) {
    $device_id = $data['device_id'];
    $action = $data['action']; // 'on', 'off', 'dim'
    $brightness = $data['brightness'] ?? 100; // 0-100%
    $color = $data['color'] ?? null; // Hex color code

    $device = $this->get_iot_device($device_id);

    if (!$device || $device->device_type !== 'smart_light') {
        return ['success' => false, 'error' => 'Invalid device'];
    }

    $webhook_data = [
        'value1' => $action,
        'value2' => $brightness,
        'value3' => $color,
    ];

    $result = $this->send_ifttt_webhook(
        $device->webhook_key,
        'control_lights',
        $webhook_data
    );

    return $result ? ['success' => true] : ['success' => false];
}
```

---

## 9. Condition Evaluation

### Condition Evaluator
```php
public function evaluate_conditions($applet, $booking) {
    $conditions = json_decode($applet->trigger_conditions, true);

    if (empty($conditions)) {
        return true; // No conditions = always trigger
    }

    foreach ($conditions as $condition) {
        $met = false;

        switch ($condition['type']) {
            case 'time_range':
                $met = $this->check_time_range(
                    $booking->start_datetime,
                    $condition['start_time'],
                    $condition['end_time']
                );
                break;

            case 'day_of_week':
                $met = $this->check_day_of_week(
                    $booking->start_datetime,
                    $condition['days']
                );
                break;

            case 'service_type':
                $met = in_array($booking->service_id, $condition['service_ids']);
                break;

            case 'amount_threshold':
                $met = $this->check_amount(
                    $booking->total_amount,
                    $condition['operator'],
                    $condition['amount']
                );
                break;

            case 'customer_type':
                $met = $this->check_customer_type(
                    $booking->customer_id,
                    $condition['customer_types']
                );
                break;
        }

        if (!$met) {
            return false; // All conditions must be met
        }
    }

    return true;
}

private function check_time_range($datetime, $start_time, $end_time) {
    $time = date('H:i', strtotime($datetime));
    return ($time >= $start_time && $time <= $end_time);
}

private function check_day_of_week($datetime, $allowed_days) {
    $day = date('N', strtotime($datetime)); // 1-7 (Monday-Sunday)
    return in_array($day, $allowed_days);
}

private function check_amount($amount, $operator, $threshold) {
    switch ($operator) {
        case 'greater_than':
            return $amount > $threshold;
        case 'less_than':
            return $amount < $threshold;
        case 'equals':
            return $amount == $threshold;
        default:
            return false;
    }
}
```

---

## 10. Webhook Management

### Send IFTTT Webhook
```php
public function send_ifttt_webhook($webhook_key, $event, $data) {
    $url = sprintf(
        'https://maker.ifttt.com/trigger/%s/with/key/%s',
        $event,
        $webhook_key
    );

    $response = wp_remote_post($url, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($data),
        'timeout' => 15,
        'blocking' => true,
    ]);

    if (is_wp_error($response)) {
        $this->log_error('Webhook failed: ' . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);

    if ($code >= 200 && $code < 300) {
        return true;
    }

    $this->log_error('Webhook failed with code: ' . $code);
    return false;
}
```

---

## 11. IoT Device Integration

### Device Registration
```php
public function register_iot_device($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'bkx_ifttt_iot_devices';

    $device = [
        'device_id' => $data['device_id'],
        'device_name' => $data['device_name'],
        'device_type' => $data['device_type'],
        'location' => $data['location'],
        'service_id' => $data['service_id'] ?? null,
        'webhook_key' => $data['webhook_key'],
        'device_config' => json_encode($data['config']),
        'status' => 'active',
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql'),
    ];

    $result = $wpdb->insert($table, $device);

    return $result ? $wpdb->insert_id : false;
}
```

### Automated IoT Triggers
```php
public function trigger_iot_devices($service_id, $event_type) {
    $devices = $this->get_service_devices($service_id);

    foreach ($devices as $device) {
        $config = json_decode($device->device_config, true);

        // Check if device should trigger for this event
        if (!in_array($event_type, $config['trigger_events'] ?? [])) {
            continue;
        }

        switch ($device->device_type) {
            case 'smart_lock':
                if ($event_type === 'arrival') {
                    $this->action_control_smart_lock([
                        'device_id' => $device->device_id,
                        'action' => 'unlock',
                        'duration' => $config['auto_lock_delay'] ?? 3600,
                    ]);
                }
                break;

            case 'smart_light':
                if ($event_type === 'arrival') {
                    $this->action_control_lights([
                        'device_id' => $device->device_id,
                        'action' => 'on',
                        'brightness' => $config['brightness'] ?? 100,
                    ]);
                }
                break;

            case 'thermostat':
                if ($event_type === 'arrival') {
                    $this->action_control_thermostat([
                        'device_id' => $device->device_id,
                        'temperature' => $config['arrival_temperature'],
                    ]);
                }
                break;
        }
    }
}
```

---

## 12. Security Considerations

### Data Security
- **Webhook Key Protection:** Store keys securely, never expose in frontend
- **HTTPS Required:** All webhook communication over SSL
- **Input Validation:** Sanitize all incoming data
- **Output Encoding:** Properly encode webhook payloads
- **Device Authentication:** Verify device ownership before control

### Access Control
- **User Permissions:** WordPress capability checks
- **Device Ownership:** Users can only control their devices
- **Applet Scoping:** Users see only their applets
- **Service Restrictions:** Limit device access per service

### Privacy
- **GDPR Compliance:** Support data export/deletion
- **Location Privacy:** Opt-in for location services
- **Activity Logging:** Transparent action logging
- **Data Retention:** Configurable log retention

---

## 13. Testing Strategy

### Unit Tests
```php
- test_trigger_creation()
- test_condition_evaluation()
- test_webhook_sending()
- test_action_execution()
- test_iot_device_control()
- test_location_detection()
- test_time_based_conditions()
```

### Integration Tests
```php
- test_complete_applet_flow()
- test_iot_automation()
- test_location_based_trigger()
- test_multi_condition_applet()
- test_voice_command_integration()
```

### Test Scenarios
1. **Basic Applet:** New booking → Send notification
2. **IoT Control:** Customer arrived → Unlock door
3. **Conditional:** Booking after 5pm → Turn on lights
4. **Location:** Customer within 100m → Send welcome message
5. **Voice Command:** "Hey Google, create booking" → Create booking

---

## 14. Error Handling

### Error Categories
1. **Webhook Errors:** Delivery failures
2. **Device Errors:** IoT device unreachable
3. **Validation Errors:** Invalid applet configuration
4. **Condition Errors:** Condition evaluation failures
5. **Authentication Errors:** Invalid webhook keys

### User-Facing Messages
```php
'webhook_failed' => 'Failed to trigger IFTTT applet. Check your webhook key.',
'device_offline' => 'The smart device is currently offline.',
'invalid_condition' => 'Invalid condition configuration.',
'location_unavailable' => 'Location services are not available.',
'webhook_key_invalid' => 'Your IFTTT webhook key is invalid.',
```

---

## 15. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Plugin structure
- [ ] Webhook integration
- [ ] Settings page
- [ ] Key management

### Phase 2: Triggers (Week 3-4)
- [ ] Trigger registration
- [ ] Core triggers implementation
- [ ] Condition evaluator
- [ ] Webhook sender

### Phase 3: Actions (Week 5-6)
- [ ] Action handlers
- [ ] Booking actions
- [ ] Notification actions
- [ ] Status updates

### Phase 4: IoT Integration (Week 7-8)
- [ ] Device registration
- [ ] Smart lock control
- [ ] Lighting control
- [ ] Thermostat control
- [ ] Automated triggers

### Phase 5: Advanced Features (Week 9-10)
- [ ] Location services
- [ ] Voice commands
- [ ] Activity logging
- [ ] Admin dashboard

### Phase 6: Testing & Launch (Week 11-12)
- [ ] Unit tests
- [ ] Integration tests
- [ ] Documentation
- [ ] QA and launch

**Total Estimated Timeline:** 12 weeks (3 months)

---

## 16. Popular Applet Examples

### Example 1: Arrival Automation
```
IF: Customer arrives at location
THEN: Unlock smart door + Turn on lights + Adjust thermostat
```

### Example 2: Booking Notification
```
IF: New booking created
THEN: Send SMS + Log to Google Sheets + Notify Slack
```

### Example 3: Voice Booking
```
IF: "Hey Google, book appointment"
THEN: Create BookingX booking
```

### Example 4: Payment Alert
```
IF: Payment completed over $100
THEN: Send push notification + Log to spreadsheet
```

---

## 17. Success Metrics

### Technical Metrics
- Webhook delivery success rate > 98%
- IoT device response time < 2 seconds
- Applet execution time < 3 seconds
- Uptime > 99.5%

### Business Metrics
- Active applets per user > 2
- IoT device adoption > 15%
- Customer satisfaction > 4.4/5
- Support ticket rate < 5%

---

## 18. Known Limitations

1. **Webhook Limitations:** IFTTT Maker webhooks support only 3 values (value1, value2, value3)
2. **Polling Frequency:** IFTTT checks triggers every 15 minutes minimum
3. **Device Compatibility:** Limited to IFTTT-compatible IoT devices
4. **Condition Complexity:** Limited to simple AND conditions
5. **Rate Limits:** Subject to IFTTT platform rate limits

---

## 19. Future Enhancements

### Version 2.0 Roadmap
- [ ] Advanced condition builder (OR logic)
- [ ] More IoT device types
- [ ] Custom webhook fields
- [ ] Applet templates library
- [ ] Multi-language voice commands
- [ ] Real-time device status monitoring

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
