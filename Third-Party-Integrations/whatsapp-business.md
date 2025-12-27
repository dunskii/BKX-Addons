# WhatsApp Business API Integration - Development Documentation

## 1. Overview

**Add-on Name:** WhatsApp Business API Integration
**Price:** $89
**Category:** Third-Party Platform Integrations
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Complete WhatsApp Business API integration enabling conversational booking through the world's most popular messaging app. Features include automated responses, rich media messages, interactive buttons, booking confirmations, and broadcast messaging to 2+ billion users globally.

### Value Proposition
- **Global Reach:** 2+ billion active users worldwide
- **High Engagement:** 98% open rate, 45% response rate
- **Trusted Platform:** Preferred communication channel
- **Rich Messaging:** Images, videos, documents, buttons
- **End-to-End Encrypted:** Privacy-first messaging
- **No App Required:** Works on existing WhatsApp
- **Mobile-First:** Perfect for on-the-go bookings

---

## 2. Features & Requirements

### Core Features
1. **Conversational Booking**
   - Chat-based booking flow
   - Natural language processing
   - Quick reply buttons
   - List messages
   - Interactive buttons
   - Catalog integration

2. **Automated Messaging**
   - Welcome messages
   - Away messages
   - Quick replies
   - Template messages
   - Booking confirmations
   - Reminders
   - Follow-ups

3. **Rich Media**
   - Service images
   - Location sharing
   - Document sending (receipts)
   - Video previews
   - Audio messages
   - Stickers

4. **Business Features**
   - Business profile
   - Verified badge
   - Catalog showcase
   - Labels and tags
   - Message templates
   - Broadcast lists
   - Analytics

5. **Integration Features**
   - CRM synchronization
   - Calendar integration
   - Payment links
   - Customer database
   - Multi-agent inbox
   - Chatbot automation

### User Roles & Permissions
- **Admin:** Full WhatsApp Business configuration
- **Manager:** Message management, customer chat
- **Staff:** Assigned conversations, booking management
- **Customer:** Book via WhatsApp chat

---

## 3. Technical Specifications

### Technology Stack
- **Platform:** WhatsApp Business API (Cloud API)
- **Provider:** Meta (Facebook) or BSP
- **Protocol:** HTTPS webhooks
- **Media:** WhatsApp Media API
- **Templates:** WhatsApp Message Templates
- **SDK:** Meta Business SDK for PHP

### Dependencies
- BookingX Core 2.0+
- WhatsApp Business Account
- Meta Business Manager
- Phone number (dedicated for WhatsApp)
- SSL certificate (required)
- WordPress REST API enabled

### API Integration Points
```php
// WhatsApp Cloud API endpoints
- POST /v16.0/{phone-number-id}/messages
- GET /v16.0/{phone-number-id}/media/{media-id}
- POST /v16.0/{phone-number-id}/media
- POST /v16.0/{business-id}/message_templates
- GET /v16.0/{business-id}/phone_numbers

// BookingX webhook endpoints
- POST /wp-json/bookingx/v1/whatsapp/webhook
- GET /wp-json/bookingx/v1/whatsapp/webhook (verification)
- POST /wp-json/bookingx/v1/whatsapp/status
```

### Webhook Message Format
```json
{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "WHATSAPP_BUSINESS_ACCOUNT_ID",
      "changes": [
        {
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "display_phone_number": "15551234567",
              "phone_number_id": "PHONE_NUMBER_ID"
            },
            "contacts": [
              {
                "profile": {
                  "name": "John Doe"
                },
                "wa_id": "15555551234"
              }
            ],
            "messages": [
              {
                "from": "15555551234",
                "id": "wamid.xxx",
                "timestamp": "1699876543",
                "text": {
                  "body": "I want to book an appointment"
                },
                "type": "text"
              }
            ]
          },
          "field": "messages"
        }
      ]
    }
  ]
}
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────────┐
│   WhatsApp Users        │
│   - Mobile App          │
│   - Web WhatsApp        │
│   - Desktop App         │
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│   WhatsApp Platform     │
│   - Message Routing     │
│   - Media Storage       │
│   - End-to-End Encrypt  │
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│   WhatsApp Business API │
│   (Cloud API / BSP)     │
│   - Webhooks            │
│   - Templates           │
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│  BookingX WA Add-on     │
│  - Message Handler      │
│  - Conversation Manager │
│  - Booking Processor    │
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│   BookingX Core         │
│   - Services            │
│   - Bookings            │
│   - Customers           │
└─────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\WhatsApp;

class WhatsAppIntegration {
    - init()
    - registerWebhook()
    - verifyWebhook()
    - sendMessage()
    - getAccessToken()
}

class MessageHandler {
    - handleIncoming()
    - handleText()
    - handleButton()
    - handleList()
    - handleMedia()
    - handleLocation()
}

class ConversationManager {
    - startConversation()
    - continueConversation()
    - getContext()
    - updateContext()
    - endConversation()
}

class BookingFlow {
    - initiateBooking()
    - selectService()
    - selectDateTime()
    - collectCustomerInfo()
    - confirmBooking()
    - processPayment()
}

class MessageBuilder {
    - buildTextMessage()
    - buildButtonMessage()
    - buildListMessage()
    - buildTemplateMessage()
    - buildMediaMessage()
    - buildLocationMessage()
}

class TemplateManager {
    - createTemplate()
    - submitTemplate()
    - sendTemplate()
    - getTemplates()
    - deleteTemplate()
}

class WhatsAppAnalytics {
    - trackMessage()
    - trackConversion()
    - measureResponseTime()
    - generateReport()
}
```

---

## 5. Database Schema

### Table: `bkx_whatsapp_accounts`
```sql
CREATE TABLE bkx_whatsapp_accounts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT(20) UNSIGNED NOT NULL,
    phone_number VARCHAR(50) NOT NULL,
    phone_number_id VARCHAR(255) NOT NULL UNIQUE,
    waba_id VARCHAR(255) NOT NULL,
    display_name VARCHAR(255),
    quality_rating VARCHAR(50),
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    access_token TEXT NOT NULL,
    business_account_id VARCHAR(255),
    is_verified TINYINT(1) DEFAULT 0,
    settings LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX business_idx (business_id),
    INDEX phone_idx (phone_number_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_whatsapp_conversations`
```sql
CREATE TABLE bkx_whatsapp_conversations (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT(20) UNSIGNED NOT NULL,
    wa_id VARCHAR(50) NOT NULL,
    contact_name VARCHAR(255),
    status VARCHAR(50) NOT NULL DEFAULT 'open',
    assigned_to BIGINT(20) UNSIGNED,
    last_message_at DATETIME,
    context_data LONGTEXT,
    booking_id BIGINT(20) UNSIGNED,
    labels VARCHAR(500),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX account_idx (account_id),
    INDEX wa_id_idx (wa_id),
    INDEX status_idx (status),
    INDEX assigned_idx (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_whatsapp_messages`
```sql
CREATE TABLE bkx_whatsapp_messages (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT(20) UNSIGNED NOT NULL,
    message_id VARCHAR(255) NOT NULL UNIQUE,
    direction VARCHAR(20) NOT NULL,
    type VARCHAR(50) NOT NULL,
    content TEXT,
    media_url VARCHAR(500),
    media_id VARCHAR(255),
    button_payload TEXT,
    template_name VARCHAR(100),
    status VARCHAR(50),
    error_code VARCHAR(50),
    error_message TEXT,
    timestamp DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX conversation_idx (conversation_id),
    INDEX message_idx (message_id),
    INDEX timestamp_idx (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_whatsapp_templates`
```sql
CREATE TABLE bkx_whatsapp_templates (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT(20) UNSIGNED NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    template_id VARCHAR(255),
    category VARCHAR(50) NOT NULL,
    language VARCHAR(10) NOT NULL,
    status VARCHAR(50) NOT NULL,
    components LONGTEXT NOT NULL,
    rejected_reason TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX account_idx (account_id),
    INDEX template_name_idx (template_name),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_whatsapp_bookings`
```sql
CREATE TABLE bkx_whatsapp_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    conversation_id BIGINT(20) UNSIGNED NOT NULL,
    wa_id VARCHAR(50) NOT NULL,
    booking_source VARCHAR(50) DEFAULT 'whatsapp',
    confirmation_sent TINYINT(1) DEFAULT 0,
    reminder_sent TINYINT(1) DEFAULT 0,
    metadata LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_idx (booking_id),
    INDEX conversation_idx (conversation_id),
    INDEX wa_id_idx (wa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_whatsapp_analytics`
```sql
CREATE TABLE bkx_whatsapp_analytics (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT(20) UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    messages_sent INT DEFAULT 0,
    messages_received INT DEFAULT 0,
    conversations_started INT DEFAULT 0,
    bookings_completed INT DEFAULT 0,
    response_time_avg INT,
    conversion_rate DECIMAL(5,2),
    message_breakdown LONGTEXT,
    created_at DATETIME NOT NULL,
    INDEX account_idx (account_id),
    INDEX event_date_idx (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    // WhatsApp Business Account
    'phone_number' => '',
    'phone_number_id' => '',
    'waba_id' => '',
    'access_token' => '',
    'verify_token' => '',

    // Business Profile
    'business_name' => '',
    'business_description' => '',
    'business_address' => '',
    'business_email' => '',
    'business_website' => '',
    'profile_picture_url' => '',

    // Features
    'enable_auto_reply' => true,
    'enable_quick_replies' => true,
    'enable_interactive_messages' => true,
    'enable_catalog' => false,
    'enable_payment_links' => true,

    // Automation
    'welcome_message' => 'Hi! How can I help you book an appointment?',
    'away_message' => 'We\'re currently away. We\'ll respond soon!',
    'working_hours_start' => '09:00',
    'working_hours_end' => '18:00',
    'auto_reply_outside_hours' => true,

    // Booking Flow
    'require_phone' => true,
    'require_email' => false,
    'send_confirmation' => true,
    'send_reminder' => true,
    'reminder_hours_before' => 24,

    // Templates
    'default_language' => 'en_US',
    'booking_confirmation_template' => '',
    'reminder_template' => '',
    'cancellation_template' => '',

    // Notifications
    'notify_admin_new_message' => true,
    'notify_staff_assigned' => true,
    'admin_notification_email' => '',

    // Advanced
    'debug_mode' => false,
    'log_messages' => true,
    'webhook_verify_token' => '',
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **WhatsApp Widget**
   - Floating WhatsApp button
   - Click-to-chat link
   - QR code for desktop
   - Pre-filled message option

### Backend Components

1. **WhatsApp Dashboard**
   - Connection status
   - Phone number info
   - Quality rating
   - Recent messages
   - Quick stats
   - Active conversations

2. **Inbox**
   - Conversation list
   - Real-time messages
   - Quick replies
   - Emoji picker
   - Media upload
   - Conversation assignment
   - Label management

3. **Template Manager**
   - Template list
   - Create template
   - Template preview
   - Submission status
   - Rejection reasons
   - Language versions

4. **Analytics Dashboard**
   - Message volume
   - Response time
   - Booking conversion
   - Customer satisfaction
   - Peak hours
   - Template performance

5. **Settings**
   - Account configuration
   - Business profile
   - Automated responses
   - Quick replies
   - Working hours
   - Notification preferences

---

## 8. Security Considerations

### Data Security
- **End-to-End Encryption:** WhatsApp encryption
- **Webhook Validation:** Verify all incoming requests
- **Token Security:** Secure access token storage
- **HTTPS Required:** All communications encrypted
- **Media Security:** Secure media URL handling
- **Data Privacy:** GDPR compliance

### Authentication & Authorization
- **Webhook Verification:** Verify token on setup
- **Signature Validation:** Validate request signatures
- **Access Control:** Role-based message access
- **Token Rotation:** Regular token updates
- **API Key Protection:** Secure key storage

### Compliance
- **WhatsApp Business Policy:** Full compliance
- **Meta Business Terms:** Adherence to terms
- **GDPR:** European data protection
- **Privacy Policy:** Required disclosure
- **Opt-In Required:** Customer consent for messaging

---

## 9. Testing Strategy

### Unit Tests
```php
- test_webhook_verification()
- test_message_parsing()
- test_message_sending()
- test_button_handling()
- test_template_creation()
- test_conversation_management()
- test_booking_creation()
```

### Integration Tests
```php
- test_complete_booking_flow()
- test_interactive_message_flow()
- test_media_message_handling()
- test_template_message_sending()
- test_multi_turn_conversation()
```

### Test Scenarios
1. **Initial Contact:** Customer sends first message
2. **Service Selection:** Choose service via buttons
3. **Date/Time Selection:** Interactive list picker
4. **Customer Info:** Collect name, phone, email
5. **Confirmation:** Confirm booking details
6. **Payment Link:** Send payment if required
7. **Confirmation Message:** Template message sent
8. **Reminder:** 24h before appointment
9. **Cancellation:** Cancel via WhatsApp
10. **Follow-up:** Post-appointment feedback

### WhatsApp Testing Tools
- WhatsApp Business API Simulator
- Webhook Testing Tool
- Template Testing
- Button Message Testing
- Test Phone Numbers

---

## 10. Error Handling

### Error Categories
1. **API Errors:** Rate limits, invalid requests
2. **Message Errors:** Failed delivery, invalid format
3. **Template Errors:** Rejected templates, approval issues
4. **Webhook Errors:** Signature failures, processing errors
5. **Business Errors:** Booking conflicts, unavailability

### User-Facing Messages
```php
'message_failed' => 'Message delivery failed. Please try again.',
'booking_unavailable' => 'That time is no longer available. Here are other options:',
'invalid_input' => 'I didn\'t understand that. Please try again.',
'system_error' => 'Something went wrong. Our team has been notified.',
'outside_hours' => 'We\'re currently closed. We\'ll respond during business hours.',
```

### Logging
- All incoming messages
- All outgoing messages
- Webhook events
- Template submissions
- Booking transactions
- Errors and failures
- Response times

---

## 11. Message Types & Examples

### Text Message
```php
$this->sendMessage($wa_id, [
    'type' => 'text',
    'text' => [
        'body' => 'Great! What service would you like to book?'
    ]
]);
```

### Interactive Button Message
```php
$this->sendMessage($wa_id, [
    'type' => 'interactive',
    'interactive' => [
        'type' => 'button',
        'body' => [
            'text' => 'Choose a service:'
        ],
        'action' => [
            'buttons' => [
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'service_1',
                        'title' => 'Haircut'
                    ]
                ],
                [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'service_2',
                        'title' => 'Massage'
                    ]
                ]
            ]
        ]
    ]
]);
```

### Interactive List Message
```php
$this->sendMessage($wa_id, [
    'type' => 'interactive',
    'interactive' => [
        'type' => 'list',
        'header' => [
            'type' => 'text',
            'text' => 'Available Times'
        ],
        'body' => [
            'text' => 'Select your preferred time:'
        ],
        'action' => [
            'button' => 'View Times',
            'sections' => [
                [
                    'title' => 'Morning',
                    'rows' => [
                        [
                            'id' => 'time_9am',
                            'title' => '9:00 AM',
                            'description' => 'Available'
                        ],
                        [
                            'id' => 'time_10am',
                            'title' => '10:00 AM',
                            'description' => 'Available'
                        ]
                    ]
                ]
            ]
        ]
    ]
]);
```

### Template Message
```php
$this->sendMessage($wa_id, [
    'type' => 'template',
    'template' => [
        'name' => 'booking_confirmation',
        'language' => [
            'code' => 'en_US'
        ],
        'components' => [
            [
                'type' => 'body',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => 'John Doe'
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Haircut'
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Nov 13, 2025 at 2:00 PM'
                    ]
                ]
            ]
        ]
    ]
]);
```

---

## 12. Performance Optimization

### Message Delivery
- Queue message sending
- Batch processing where possible
- Retry failed messages
- Track delivery status
- Optimize media uploads

### Webhook Processing
- Async webhook processing
- Queue heavy operations
- Fast response to webhooks
- Efficient database queries

### Caching Strategy
- Cache conversation context
- Cache customer data
- Cache service catalog
- Cache templates (TTL: 1 hour)

---

## 13. Internationalization

### Supported Languages
```php
Template Languages:
- English (en, en_US, en_GB)
- Spanish (es, es_ES, es_MX)
- French (fr)
- German (de)
- Italian (it)
- Portuguese (pt_BR, pt_PT)
- Arabic (ar)
- Hindi (hi)
- Chinese (zh_CN, zh_TW)
- Japanese (ja)
- Korean (ko)
```

### Language Detection
- Detect from phone number region
- Detect from first message language
- Allow user to select language
- Remember language preference

---

## 14. Documentation Requirements

### User Documentation
1. **Setup Guide**
   - WhatsApp Business Account setup
   - Phone number registration
   - Webhook configuration
   - Template creation

2. **User Guide**
   - Managing conversations
   - Sending messages
   - Using templates
   - Handling bookings
   - Analytics interpretation

### Developer Documentation
1. **API Reference**
   - Webhook payload formats
   - Message types
   - Template structure
   - Filter hooks
   - Action hooks

---

## 15. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] WhatsApp Cloud API integration
- [ ] Webhook setup
- [ ] Message handling
- [ ] Settings page

### Phase 2: Core Features (Week 3-4)
- [ ] Conversation management
- [ ] Message types (text, buttons, lists)
- [ ] Quick replies
- [ ] Media handling
- [ ] Customer database

### Phase 3: Booking Flow (Week 5-6)
- [ ] Conversational booking
- [ ] Service selection
- [ ] Date/time picker
- [ ] Customer info collection
- [ ] Booking confirmation

### Phase 4: Templates & Automation (Week 7-8)
- [ ] Template manager
- [ ] Template submission
- [ ] Automated responses
- [ ] Welcome messages
- [ ] Reminders

### Phase 5: Inbox & Management (Week 9)
- [ ] Message inbox UI
- [ ] Real-time updates
- [ ] Conversation assignment
- [ ] Label management
- [ ] Agent features

### Phase 6: Testing & Launch (Week 10)
- [ ] Testing
- [ ] Documentation
- [ ] WhatsApp verification
- [ ] Production release

**Total Estimated Timeline:** 10 weeks (2.5 months)

---

## 16. Maintenance & Support

### Update Schedule
- **Security Updates:** As needed
- **Bug Fixes:** Weekly
- **WhatsApp API Updates:** As released
- **Feature Updates:** Monthly
- **Template Optimization:** Ongoing

### Support Channels
- Email support
- Documentation
- Video tutorials
- WhatsApp support (meta)
- Community forum

### Monitoring
- Message delivery rate
- Template approval rate
- Response time
- Booking conversion
- Customer satisfaction
- API error rate

---

## 17. Dependencies & Requirements

### Required WordPress Plugins
- BookingX Core 2.0+

### WhatsApp Requirements
- WhatsApp Business Account
- Meta Business Manager
- Verified business
- Dedicated phone number
- Business display name

### Server Requirements
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+
- SSL Certificate (required)
- Webhook publicly accessible
- WordPress 5.8+

---

## 18. Success Metrics

### Technical Metrics
- Message delivery rate > 99%
- Webhook processing < 2 seconds
- Template approval rate > 90%
- API error rate < 1%

### Business Metrics
- WhatsApp booking conversion > 25%
- Average response time < 5 minutes
- Customer satisfaction > 4.5/5
- Repeat booking rate via WhatsApp

### User Metrics
- Conversation completion rate
- Messages per conversation
- Peak messaging hours
- Template engagement rate

---

## 19. Known Limitations

1. **24-Hour Window:** Free-form messaging only within 24h of user message
2. **Templates Required:** Marketing messages need approved templates
3. **Phone Number:** Requires dedicated business phone number
4. **Rate Limits:** API rate limiting applies
5. **Media Size:** File size restrictions
6. **Message Types:** Limited message format options
7. **No Broadcast:** No mass messaging without user opt-in
8. **Approval Process:** Templates need Meta approval

---

## 20. Future Enhancements

### Version 2.0 Roadmap
- [ ] WhatsApp Flows (interactive forms)
- [ ] Catalog integration
- [ ] Collections showcase
- [ ] WhatsApp Payments
- [ ] Advanced chatbot with AI
- [ ] Multi-agent routing
- [ ] CRM integration
- [ ] Advanced analytics
- [ ] Group messaging support
- [ ] WhatsApp Business API on-premise

### Version 3.0 Roadmap
- [ ] AI-powered responses
- [ ] Sentiment analysis
- [ ] Voice message handling
- [ ] Video consultation booking
- [ ] Advanced automation
- [ ] Predictive analytics
- [ ] Multi-language AI bot

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
