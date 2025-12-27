# Slack Integration Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Slack Integration
**Price:** $49
**Category:** Communication & Notifications
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Seamless Slack workspace integration for real-time booking notifications, team collaboration, and booking management directly from Slack. Receive instant alerts, respond to bookings, and manage appointments without leaving your Slack workspace.

### Value Proposition
- Real-time booking notifications in Slack
- Manage bookings via Slack commands
- Team collaboration on bookings
- Custom channel routing
- Rich interactive messages
- Two-way communication

---

## 2. Features & Requirements

### Core Features
1. **Slack Notifications**
   - New booking alerts
   - Booking confirmations
   - Cancellation notifications
   - Rescheduling alerts
   - Payment notifications
   - Customer messages
   - Custom event notifications

2. **Slack Commands**
   - `/booking list` - List upcoming bookings
   - `/booking view [id]` - View booking details
   - `/booking confirm [id]` - Confirm booking
   - `/booking cancel [id]` - Cancel booking
   - `/booking reschedule [id]` - Reschedule booking
   - `/booking stats` - View statistics
   - `/booking help` - Command help

3. **Interactive Messages**
   - Approve/reject buttons
   - Quick actions
   - Inline booking forms
   - Status updates
   - Message threading
   - Emoji reactions

4. **Channel Routing**
   - Route by service type
   - Route by staff member
   - Route by priority
   - Route by booking status
   - Custom routing rules
   - Multi-channel support

5. **Team Features**
   - Mentions for assignments
   - Team notifications
   - Shared booking threads
   - Internal notes
   - Staff availability updates
   - Shift change notifications

6. **App Home**
   - Booking dashboard
   - Today's schedule
   - Quick actions
   - Recent activity
   - Personal stats

### User Roles & Permissions
- **Admin:** Full configuration, all notifications
- **Manager:** Manage bookings, view all channels
- **Staff:** View assigned bookings, respond to notifications
- **Bot:** Automated notifications and commands

---

## 3. Technical Specifications

### Technology Stack
- **Slack API:** Web API, Events API, Slash Commands
- **Slack SDK:** PHP Slack SDK, slack/slack-php-api
- **OAuth:** Slack OAuth 2.0
- **Webhooks:** Incoming webhooks, Interactive components
- **Block Kit:** Slack Block Kit for rich messages
- **Real-time:** Socket Mode (optional)

### Dependencies
- BookingX Core 2.0+
- PHP cURL extension
- PHP JSON extension
- WordPress REST API enabled
- SSL Certificate (required for webhooks)

### API Integration Points
```php
// Slack Web API
- POST https://slack.com/api/chat.postMessage
- POST https://slack.com/api/chat.update
- POST https://slack.com/api/chat.delete
- GET https://slack.com/api/conversations.list
- GET https://slack.com/api/users.list
- POST https://slack.com/api/views.open
- POST https://slack.com/api/views.update

// OAuth API
- GET https://slack.com/oauth/v2/authorize
- POST https://slack.com/api/oauth.v2.access

// Slash Commands endpoint
- POST /wp-json/bookingx/v1/slack/commands

// Interactive Components endpoint
- POST /wp-json/bookingx/v1/slack/interactive

// Events API endpoint
- POST /wp-json/bookingx/v1/slack/events

// Incoming Webhooks
- POST https://hooks.slack.com/services/{workspace}/{channel}/{token}
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│  BookingX Event     │
│  (Booking Created)  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────┐
│  Slack Integration Manager  │
│  - Event Handler           │
│  - Channel Router          │
└──────────┬──────────────────┘
           │
           ├─────────────────────────┬──────────────────┐
           ▼                         ▼                  ▼
┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
│  Message Builder │  │  Command Handler │  │  Interactive     │
│  (Block Kit)     │  │  (Slash Commands)│  │  Handler         │
└──────────┬───────┘  └─────────┬────────┘  └─────────┬────────┘
           │                    │                      │
           └────────────────────┴──────────────────────┘
                                │
                                ▼
                   ┌────────────────────────┐
                   │  Slack API             │
                   │  - Post Message        │
                   │  - Update Message      │
                   │  - Open Modal          │
                   └────────────────────────┘
                                │
                                ▼
                   ┌────────────────────────┐
                   │  Slack Workspace       │
                   │  - Channels            │
                   │  - Users               │
                   │  - App Home            │
                   └────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\SlackIntegration;

class SlackIntegrationManager {
    - init()
    - send_notification()
    - handle_event()
    - route_message()
}

class SlackAPI {
    - authenticate()
    - post_message()
    - update_message()
    - delete_message()
    - get_channels()
    - get_users()
    - open_modal()
    - upload_file()
}

class OAuthHandler {
    - initiate_oauth()
    - handle_callback()
    - refresh_token()
    - revoke_token()
}

class MessageBuilder {
    - build_booking_notification()
    - build_cancellation_notice()
    - build_confirmation_request()
    - add_actions()
    - format_blocks()
}

class CommandHandler {
    - register_commands()
    - handle_list_command()
    - handle_view_command()
    - handle_confirm_command()
    - handle_cancel_command()
    - handle_stats_command()
}

class InteractiveHandler {
    - handle_button_click()
    - handle_menu_selection()
    - handle_modal_submission()
    - handle_view_closed()
}

class EventsHandler {
    - verify_request()
    - handle_message()
    - handle_app_mention()
    - handle_app_home_opened()
    - handle_member_joined()
}

class ChannelRouter {
    - determine_channel()
    - apply_routing_rules()
    - get_default_channel()
    - validate_channel()
}

class UserMapper {
    - map_wordpress_to_slack()
    - get_slack_user_id()
    - sync_users()
    - cache_mappings()
}

class AppHomeBuilder {
    - build_home_view()
    - build_today_schedule()
    - build_quick_actions()
    - build_stats_section()
}

class NotificationFormatter {
    - format_booking_data()
    - format_customer_data()
    - format_datetime()
    - add_emoji()
}
```

---

## 5. Database Schema

### Table: `bkx_slack_workspaces`
```sql
CREATE TABLE bkx_slack_workspaces (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id VARCHAR(50) NOT NULL UNIQUE,
    workspace_name VARCHAR(255) NOT NULL,
    team_name VARCHAR(255),
    access_token TEXT NOT NULL,
    bot_user_id VARCHAR(50),
    bot_access_token TEXT,
    app_id VARCHAR(50),
    enterprise_id VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    installed_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX workspace_id_idx (workspace_id),
    INDEX is_active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_slack_channels`
```sql
CREATE TABLE bkx_slack_channels (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT(20) UNSIGNED NOT NULL,
    channel_id VARCHAR(50) NOT NULL,
    channel_name VARCHAR(255) NOT NULL,
    is_private TINYINT(1) DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0,
    notification_types TEXT,
    routing_rules LONGTEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY workspace_channel (workspace_id, channel_id),
    INDEX channel_id_idx (channel_id),
    INDEX is_default_idx (is_default),
    FOREIGN KEY (workspace_id) REFERENCES bkx_slack_workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_slack_messages`
```sql
CREATE TABLE bkx_slack_messages (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT(20) UNSIGNED NOT NULL,
    channel_id VARCHAR(50) NOT NULL,
    message_ts VARCHAR(50) NOT NULL,
    thread_ts VARCHAR(50),
    booking_id BIGINT(20) UNSIGNED,
    message_type VARCHAR(100),
    blocks LONGTEXT,
    text TEXT,
    user_id VARCHAR(50),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX workspace_id_idx (workspace_id),
    INDEX booking_id_idx (booking_id),
    INDEX message_ts_idx (message_ts),
    INDEX thread_ts_idx (thread_ts),
    FOREIGN KEY (workspace_id) REFERENCES bkx_slack_workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_slack_user_mappings`
```sql
CREATE TABLE bkx_slack_user_mappings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT(20) UNSIGNED NOT NULL,
    wordpress_user_id BIGINT(20) UNSIGNED NOT NULL,
    slack_user_id VARCHAR(50) NOT NULL,
    slack_username VARCHAR(100),
    slack_email VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY workspace_wp_user (workspace_id, wordpress_user_id),
    INDEX slack_user_id_idx (slack_user_id),
    INDEX wordpress_user_id_idx (wordpress_user_id),
    FOREIGN KEY (workspace_id) REFERENCES bkx_slack_workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_slack_interactions`
```sql
CREATE TABLE bkx_slack_interactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT(20) UNSIGNED NOT NULL,
    booking_id BIGINT(20) UNSIGNED,
    slack_user_id VARCHAR(50) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    action_id VARCHAR(100),
    value TEXT,
    message_ts VARCHAR(50),
    response_url TEXT,
    created_at DATETIME NOT NULL,
    INDEX workspace_id_idx (workspace_id),
    INDEX booking_id_idx (booking_id),
    INDEX action_type_idx (action_type),
    INDEX created_at_idx (created_at),
    FOREIGN KEY (workspace_id) REFERENCES bkx_slack_workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_slack_routing_rules`
```sql
CREATE TABLE bkx_slack_routing_rules (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workspace_id BIGINT(20) UNSIGNED NOT NULL,
    rule_name VARCHAR(255) NOT NULL,
    conditions LONGTEXT NOT NULL,
    channel_id VARCHAR(50) NOT NULL,
    priority INT DEFAULT 10,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX workspace_id_idx (workspace_id),
    INDEX priority_idx (priority),
    INDEX is_active_idx (is_active),
    FOREIGN KEY (workspace_id) REFERENCES bkx_slack_workspaces(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    // General Settings
    'enabled' => true,
    'workspace_installed' => false,
    'default_channel' => '',
    'thread_replies' => true,
    'use_mentions' => true,

    // OAuth Configuration
    'client_id' => '',
    'client_secret' => '',
    'signing_secret' => '',
    'verification_token' => '',

    // Notification Settings
    'notify_new_booking' => true,
    'notify_cancellation' => true,
    'notify_rescheduling' => true,
    'notify_payment' => true,
    'notify_customer_message' => true,
    'notify_status_change' => true,

    // Command Settings
    'enable_slash_commands' => true,
    'command_prefix' => '/booking',
    'require_authentication' => true,
    'allowed_commands' => ['list', 'view', 'confirm', 'cancel', 'stats'],

    // Interactive Features
    'enable_quick_actions' => true,
    'enable_approve_reject' => true,
    'enable_inline_forms' => true,
    'enable_modals' => true,

    // Channel Routing
    'enable_routing' => true,
    'route_by_service' => false,
    'route_by_staff' => false,
    'route_by_status' => false,
    'fallback_channel' => '',

    // Message Formatting
    'include_customer_details' => true,
    'include_service_details' => true,
    'include_pricing' => true,
    'include_notes' => true,
    'use_emoji' => true,
    'emoji_set' => 'default',

    // App Home
    'enable_app_home' => true,
    'show_today_schedule' => true,
    'show_quick_actions' => true,
    'show_stats' => true,

    // User Mapping
    'auto_map_users' => true,
    'map_by_email' => true,
    'sync_interval' => 3600, // seconds

    // Rate Limiting
    'rate_limit_enabled' => true,
    'max_messages_per_minute' => 20,

    // Advanced
    'use_socket_mode' => false,
    'socket_token' => '',
    'debug_mode' => false,
    'log_all_events' => false,
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **WordPress Admin Interface**
   - OAuth connection button
   - Workspace status display
   - Channel selector
   - Test notification button
   - Disconnect workspace button

### Backend Components

1. **Settings Page**
   - Slack App configuration
   - OAuth authorization flow
   - Channel management
   - Routing rules builder
   - User mapping interface
   - Test tools

2. **Channel Manager**
   - Available channels list
   - Default channel selection
   - Routing rule assignment
   - Channel testing

3. **User Mapping**
   - WordPress-Slack user table
   - Manual mapping interface
   - Auto-sync button
   - Sync status

4. **Message Log**
   - Sent messages list
   - Message preview
   - Delivery status
   - Interaction history
   - Resend option

5. **Routing Rules Builder**
   - Visual rule creator
   - Condition selection
   - Channel assignment
   - Priority ordering
   - Test rule

### Slack Components

1. **App Home**
   - Today's bookings
   - Quick action buttons
   - Recent activity
   - Personal statistics

2. **Notification Messages**
   - Booking details card
   - Action buttons
   - Thread replies
   - Status updates

3. **Slash Command Responses**
   - Formatted booking lists
   - Detailed booking views
   - Confirmation messages
   - Error messages

4. **Modals**
   - Booking detail modal
   - Reschedule modal
   - Cancel confirmation
   - Note adding modal

---

## 8. Security Considerations

### API Security
- **OAuth 2.0:** Secure token exchange
- **Signing Secret:** Request signature verification
- **Token Storage:** Encrypted access tokens
- **Scopes:** Minimal required permissions
- **SSL/TLS:** All communications encrypted

### Webhook Security
- **Signature Verification:** Validate Slack signatures
- **Timestamp Validation:** Prevent replay attacks
- **IP Whitelisting:** Optional Slack IP validation
- **Rate Limiting:** Prevent abuse

### Data Security
- **Access Control:** Role-based permissions
- **Data Encryption:** Sensitive data encrypted
- **Audit Logging:** Track all actions
- **PII Protection:** Minimize personal data in Slack

### Compliance
- **GDPR:** Data processing agreements
- **Data Retention:** Configurable retention
- **Right to Delete:** Remove workspace data
- **Consent:** User acknowledgment

---

## 9. Testing Strategy

### Unit Tests
```php
- test_oauth_flow()
- test_message_building()
- test_command_parsing()
- test_interactive_handling()
- test_channel_routing()
- test_user_mapping()
- test_signature_verification()
```

### Integration Tests
```php
- test_workspace_installation()
- test_message_sending()
- test_slash_commands()
- test_button_interactions()
- test_modal_submissions()
- test_app_home_rendering()
- test_webhook_handling()
```

### Slack Testing
1. **Workspace Setup:** Install app in test workspace
2. **Message Testing:** Send all notification types
3. **Command Testing:** Test all slash commands
4. **Interactive Testing:** Click buttons, submit modals
5. **App Home:** Verify home tab rendering
6. **Permissions:** Test with different user roles
7. **Error Handling:** Test failure scenarios

---

## 10. Error Handling

### Error Categories
1. **OAuth Errors:** Authorization failures
2. **API Errors:** Slack API failures
3. **Permission Errors:** Missing scopes
4. **Channel Errors:** Invalid/deleted channels
5. **User Errors:** Invalid user mappings

### Error Messages (User-Facing)
```php
'workspace_not_connected' => 'Slack workspace not connected. Please authorize the app.',
'channel_not_found' => 'Slack channel not found. Please select a valid channel.',
'permission_denied' => 'Missing required Slack permissions. Please reinstall the app.',
'api_error' => 'Failed to communicate with Slack. Please try again.',
'user_not_mapped' => 'Your Slack user is not mapped. Contact administrator.',
```

### Logging
- All Slack API requests/responses
- Webhook events
- Command executions
- Interactive actions
- OAuth events
- Errors and failures

### Retry Logic
```php
// Automatic retry for failed API calls
Attempt 1: Immediate
Attempt 2: 2 seconds later
Attempt 3: 10 seconds later
Attempt 4: 60 seconds later (final)
```

---

## 11. Webhooks

### Slack Webhooks
```php
// Events API endpoint
/wp-json/bookingx/v1/slack/events

// Supported events
- app_home_opened
- app_mention
- message.channels
- member_joined_channel

// Interactive Components endpoint
/wp-json/bookingx/v1/slack/interactive

// Action types
- block_actions
- view_submission
- view_closed

// Slash Commands endpoint
/wp-json/bookingx/v1/slack/commands

// Commands
- /booking [subcommand]
```

---

## 12. Performance Optimization

### API Optimization
- Rate limit compliance (1+ message per second)
- Request batching where possible
- Token caching
- Channel list caching

### Caching Strategy
- Cache workspace details (TTL: 1 hour)
- Cache channel list (TTL: 30 minutes)
- Cache user mappings (TTL: 15 minutes)
- Cache routing rules (TTL: 5 minutes)

### Database Optimization
- Indexed queries on workspace/channel lookups
- Pagination for message logs
- Archival of old interactions
- Efficient user mapping queries

### Message Optimization
- Lazy loading of booking details
- Efficient Block Kit structure
- Minimal message payloads
- Thread organization

---

## 13. Internationalization

### Multi-language Support
- Message translations
- Command help translations
- Error message localization
- Date/time formatting

### Timezone Handling
- Display times in user timezone
- Convert booking times appropriately
- Respect workspace timezone settings

---

## 14. Documentation Requirements

### User Documentation
1. **Installation Guide**
   - Slack App creation
   - WordPress plugin setup
   - OAuth authorization
   - Channel configuration

2. **User Guide**
   - Using slash commands
   - Interacting with notifications
   - App Home navigation
   - Managing preferences

### Admin Documentation
1. **Setup Guide**
   - Slack App configuration
   - Scopes and permissions
   - Channel setup
   - User mapping
   - Testing

2. **Management Guide**
   - Routing rules
   - Channel management
   - User administration
   - Troubleshooting

### Developer Documentation
1. **API Reference**
   - Hooks and filters
   - Custom commands
   - Custom routing rules
   - Event handlers

2. **Customization Guide**
   - Custom message formats
   - Additional commands
   - Advanced routing
   - Extending functionality

---

## 15. Development Timeline

### Phase 1: Foundation (Week 1)
- [ ] Database schema creation
- [ ] Plugin structure
- [ ] Slack SDK integration
- [ ] Admin settings framework

### Phase 2: OAuth & Connection (Week 2)
- [ ] OAuth flow implementation
- [ ] Workspace installation
- [ ] Token management
- [ ] Connection testing

### Phase 3: Messaging (Week 3-4)
- [ ] Message builder (Block Kit)
- [ ] Notification sending
- [ ] Channel routing
- [ ] Thread management

### Phase 4: Slash Commands (Week 5)
- [ ] Command registration
- [ ] Command handlers
- [ ] Response formatting
- [ ] Permission checking

### Phase 5: Interactive Components (Week 6)
- [ ] Button actions
- [ ] Modal views
- [ ] Menu selections
- [ ] Form submissions

### Phase 6: App Home (Week 7)
- [ ] Home tab view
- [ ] Today's schedule
- [ ] Quick actions
- [ ] Stats display

### Phase 7: Advanced Features (Week 8)
- [ ] User mapping
- [ ] Routing rules engine
- [ ] Message threading
- [ ] Webhook handling

### Phase 8: UI Development (Week 9)
- [ ] WordPress admin interface
- [ ] Settings pages
- [ ] Channel manager
- [ ] Rule builder
- [ ] Message log

### Phase 9: Testing & QA (Week 10-11)
- [ ] Unit testing
- [ ] Integration testing
- [ ] Slack workspace testing
- [ ] Security audit
- [ ] Performance testing

### Phase 10: Documentation & Launch (Week 12)
- [ ] User documentation
- [ ] Admin guide
- [ ] Developer documentation
- [ ] Video tutorials
- [ ] Beta testing
- [ ] Production release

**Total Estimated Timeline:** 12 weeks (3 months)

---

## 16. Maintenance & Support

### Update Schedule
- **Security Updates:** As needed
- **Slack API Updates:** Quarterly
- **Bug Fixes:** Bi-weekly
- **Feature Updates:** Quarterly

### Support Channels
- Email support
- Documentation portal
- Video tutorials
- Community forum
- Slack workspace for users

### Monitoring
- API usage tracking
- Error rate monitoring
- Message delivery rate
- Command usage statistics
- User engagement metrics

---

## 17. Dependencies & Requirements

### Required WordPress Plugins
- BookingX Core 2.0+

### Optional Compatible Plugins
- BookingX Payments (payment notifications)
- BookingX Calendar (schedule in App Home)

### Server Requirements
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- SSL Certificate (required)
- cURL extension
- JSON extension
- WordPress 5.8+

### External Services
- Slack workspace
- Slack App created
- OAuth credentials configured

### Slack App Scopes Required
**Bot Token Scopes:**
- chat:write
- chat:write.public
- channels:read
- channels:history
- users:read
- users:read.email
- commands
- app_mentions:read

**User Token Scopes:**
- channels:read (optional)

---

## 18. Success Metrics

### Technical Metrics
- Message delivery rate > 99%
- Command response time < 2 seconds
- API error rate < 1%
- Webhook processing < 500ms
- OAuth success rate > 95%

### Business Metrics
- Workspace installation rate > 30%
- Daily active users > 60%
- Command usage > 20 per day
- Notification engagement > 40%
- User satisfaction > 4.6/5

---

## 19. Known Limitations

1. **Rate Limits:** Slack API rate limits (1+ msg/sec)
2. **Message Size:** Block Kit message size limits
3. **User Mapping:** Requires email matching or manual setup
4. **Permissions:** Requires specific Slack scopes
5. **Modal Size:** Limited content in modals
6. **File Size:** Upload size limits
7. **Enterprise Grid:** Limited support initially

---

## 20. Future Enhancements

### Version 2.0 Roadmap
- [ ] Slack workflow integration
- [ ] Custom emoji reactions
- [ ] Advanced analytics dashboard
- [ ] Multi-workspace support
- [ ] Slack Connect support
- [ ] Voice/video call integration
- [ ] Advanced automation workflows
- [ ] AI-powered responses

### Version 3.0 Roadmap
- [ ] Slack Canvas integration
- [ ] Enterprise Grid full support
- [ ] Advanced reporting in Slack
- [ ] Customer portal in Slack
- [ ] Payment processing via Slack
- [ ] Video consultation launch from Slack
- [ ] AI booking assistant

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
