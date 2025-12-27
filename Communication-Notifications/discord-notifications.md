# Discord Notifications Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Discord Notifications
**Price:** $39
**Category:** Communication & Notifications
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Discord server integration for real-time booking notifications with rich embeds, role mentions, and interactive components. Perfect for gaming communities, education platforms, and team-based services. Send beautiful, customizable notifications directly to Discord channels.

### Value Proposition
- Real-time Discord notifications
- Rich embed messages with branding
- Role and user mentions
- Channel routing by category
- Button interactions
- Community engagement features

---

## 2. Features & Requirements

### Core Features
1. **Discord Bot Integration**
   - Custom Discord bot
   - Webhook support (simple mode)
   - Server authorization
   - Multi-server support
   - Bot presence customization

2. **Notification Types**
   - New booking alerts
   - Booking confirmations
   - Cancellation notices
   - Rescheduling notifications
   - Payment confirmations
   - Customer inquiries
   - Daily summaries
   - Custom announcements

3. **Rich Embeds**
   - Branded embed design
   - Custom colors per notification type
   - Thumbnail images
   - Field organization
   - Timestamp display
   - Footer information
   - Author details

4. **Interactive Components**
   - Button actions (approve/reject)
   - Role assignment buttons
   - Quick response buttons
   - Link buttons
   - Select menus
   - Modal forms

5. **Channel Routing**
   - Route by service type
   - Route by booking status
   - Route by priority
   - Category-based routing
   - Multi-channel support
   - DM notifications

6. **Community Features**
   - Role mentions for assignments
   - User mentions for bookings
   - Reaction roles
   - Automated responses
   - Server statistics
   - Leaderboards

### User Roles & Permissions
- **Admin:** Full configuration, manage bot
- **Manager:** Configure channels, view notifications
- **Staff:** Respond to notifications (if bot features enabled)
- **Bot:** Send notifications, handle interactions

---

## 3. Technical Specifications

### Technology Stack
- **Discord API:** Discord REST API v10, Gateway API
- **Discord SDK:** restcord/restcord, discord-php
- **Webhooks:** Discord Incoming Webhooks
- **OAuth:** Discord OAuth2
- **WebSocket:** Discord Gateway (for bot features)
- **Embed Builder:** Custom embed builder

### Dependencies
- BookingX Core 2.0+
- PHP cURL extension
- PHP JSON extension
- WordPress REST API enabled
- SSL Certificate (recommended)

### API Integration Points
```php
// Discord REST API
- POST https://discord.com/api/v10/channels/{channel_id}/messages
- PATCH https://discord.com/api/v10/channels/{channel_id}/messages/{message_id}
- DELETE https://discord.com/api/v10/channels/{channel_id}/messages/{message_id}
- GET https://discord.com/api/v10/guilds/{guild_id}/channels
- POST https://discord.com/api/v10/interactions/{interaction_id}/{interaction_token}/callback

// Discord Webhooks (Simple Mode)
- POST https://discord.com/api/webhooks/{webhook_id}/{webhook_token}
- POST https://discord.com/api/webhooks/{webhook_id}/{webhook_token}/messages/{message_id}

// OAuth API
- GET https://discord.com/api/oauth2/authorize
- POST https://discord.com/api/oauth2/token

// Gateway WebSocket (for bot features)
- wss://gateway.discord.gg/?v=10&encoding=json

// Interaction endpoint (for buttons/modals)
- POST /wp-json/bookingx/v1/discord/interactions
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│  BookingX Event     │
│  (New Booking)      │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────┐
│  Discord Manager            │
│  - Event Router            │
│  - Channel Selector        │
└──────────┬──────────────────┘
           │
           ├──────────────────────┬────────────────────┐
           ▼                      ▼                    ▼
┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
│  Embed Builder   │  │  Webhook Sender  │  │  Bot API         │
│  (Rich Messages) │  │  (Simple)        │  │  (Advanced)      │
└──────────┬───────┘  └─────────┬────────┘  └─────────┬────────┘
           │                    │                      │
           └────────────────────┴──────────────────────┘
                                │
                                ▼
                   ┌────────────────────────┐
                   │  Discord API           │
                   │  - Send Message        │
                   │  - Update Message      │
                   │  - Handle Interaction  │
                   └────────────────────────┘
                                │
                                ▼
                   ┌────────────────────────┐
                   │  Discord Server        │
                   │  - Channels            │
                   │  - Members             │
                   │  - Roles               │
                   └────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\DiscordNotifications;

class DiscordManager {
    - init()
    - send_notification()
    - handle_interaction()
    - route_to_channel()
}

class DiscordAPI {
    - authenticate()
    - send_message()
    - update_message()
    - delete_message()
    - get_channels()
    - get_guild_info()
    - create_interaction_response()
}

class WebhookSender {
    - send_via_webhook()
    - validate_webhook()
    - format_webhook_payload()
}

class BotAPI {
    - initialize_bot()
    - connect_gateway()
    - handle_gateway_events()
    - update_presence()
}

class EmbedBuilder {
    - create_embed()
    - set_title()
    - set_description()
    - set_color()
    - add_field()
    - set_thumbnail()
    - set_footer()
    - set_timestamp()
    - build()
}

class ComponentBuilder {
    - add_button()
    - add_select_menu()
    - create_action_row()
    - build_modal()
}

class InteractionHandler {
    - verify_signature()
    - handle_button_click()
    - handle_select_menu()
    - handle_modal_submit()
    - send_response()
}

class OAuthHandler {
    - initiate_oauth()
    - handle_callback()
    - refresh_token()
    - get_user_guilds()
}

class ChannelRouter {
    - determine_channel()
    - apply_routing_rules()
    - get_default_channel()
    - validate_channel_permissions()
}

class MentionFormatter {
    - mention_role()
    - mention_user()
    - mention_channel()
    - format_mentions()
}

class NotificationFormatter {
    - format_booking_embed()
    - format_cancellation_embed()
    - format_summary_embed()
    - add_booking_fields()
}

class ServerManager {
    - add_server()
    - remove_server()
    - sync_channels()
    - sync_roles()
    - verify_permissions()
}
```

---

## 5. Database Schema

### Table: `bkx_discord_servers`
```sql
CREATE TABLE bkx_discord_servers (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guild_id VARCHAR(50) NOT NULL UNIQUE,
    guild_name VARCHAR(255) NOT NULL,
    icon_url VARCHAR(500),
    access_token TEXT,
    refresh_token TEXT,
    bot_token TEXT,
    webhook_id VARCHAR(50),
    webhook_token TEXT,
    webhook_url TEXT,
    mode VARCHAR(20) DEFAULT 'webhook',
    is_active TINYINT(1) DEFAULT 1,
    added_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX guild_id_idx (guild_id),
    INDEX mode_idx (mode),
    INDEX is_active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_discord_channels`
```sql
CREATE TABLE bkx_discord_channels (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id BIGINT(20) UNSIGNED NOT NULL,
    channel_id VARCHAR(50) NOT NULL,
    channel_name VARCHAR(255) NOT NULL,
    channel_type VARCHAR(50),
    is_default TINYINT(1) DEFAULT 0,
    notification_types TEXT,
    routing_rules LONGTEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY server_channel (server_id, channel_id),
    INDEX channel_id_idx (channel_id),
    INDEX is_default_idx (is_default),
    FOREIGN KEY (server_id) REFERENCES bkx_discord_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_discord_messages`
```sql
CREATE TABLE bkx_discord_messages (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id BIGINT(20) UNSIGNED NOT NULL,
    channel_id VARCHAR(50) NOT NULL,
    message_id VARCHAR(50) NOT NULL,
    booking_id BIGINT(20) UNSIGNED,
    message_type VARCHAR(100),
    embed_data LONGTEXT,
    components LONGTEXT,
    status VARCHAR(50) DEFAULT 'sent',
    sent_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX server_id_idx (server_id),
    INDEX booking_id_idx (booking_id),
    INDEX message_id_idx (message_id),
    INDEX message_type_idx (message_type),
    FOREIGN KEY (server_id) REFERENCES bkx_discord_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_discord_interactions`
```sql
CREATE TABLE bkx_discord_interactions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id BIGINT(20) UNSIGNED NOT NULL,
    interaction_id VARCHAR(50) NOT NULL,
    interaction_token TEXT,
    interaction_type VARCHAR(50) NOT NULL,
    booking_id BIGINT(20) UNSIGNED,
    user_id VARCHAR(50) NOT NULL,
    custom_id VARCHAR(100),
    value TEXT,
    created_at DATETIME NOT NULL,
    INDEX server_id_idx (server_id),
    INDEX booking_id_idx (booking_id),
    INDEX interaction_type_idx (interaction_type),
    FOREIGN KEY (server_id) REFERENCES bkx_discord_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_discord_roles`
```sql
CREATE TABLE bkx_discord_roles (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id BIGINT(20) UNSIGNED NOT NULL,
    role_id VARCHAR(50) NOT NULL,
    role_name VARCHAR(255) NOT NULL,
    color VARCHAR(10),
    mention_for LONGTEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY server_role (server_id, role_id),
    INDEX role_id_idx (role_id),
    FOREIGN KEY (server_id) REFERENCES bkx_discord_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_discord_routing_rules`
```sql
CREATE TABLE bkx_discord_routing_rules (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id BIGINT(20) UNSIGNED NOT NULL,
    rule_name VARCHAR(255) NOT NULL,
    conditions LONGTEXT NOT NULL,
    channel_id VARCHAR(50) NOT NULL,
    role_mentions TEXT,
    priority INT DEFAULT 10,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX server_id_idx (server_id),
    INDEX priority_idx (priority),
    INDEX is_active_idx (is_active),
    FOREIGN KEY (server_id) REFERENCES bkx_discord_servers(id) ON DELETE CASCADE
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
    'mode' => 'webhook', // webhook, bot
    'default_server' => '',
    'default_channel' => '',

    // Bot Configuration (Advanced Mode)
    'bot_enabled' => false,
    'bot_token' => '',
    'bot_client_id' => '',
    'bot_client_secret' => '',
    'bot_public_key' => '',
    'bot_presence_text' => 'Managing Bookings',
    'bot_presence_type' => 'playing', // playing, watching, listening

    // Webhook Configuration (Simple Mode)
    'webhook_url' => '',
    'webhook_username' => 'BookingX',
    'webhook_avatar_url' => '',

    // Notification Settings
    'notify_new_booking' => true,
    'notify_cancellation' => true,
    'notify_rescheduling' => true,
    'notify_payment' => true,
    'notify_customer_message' => true,
    'daily_summary' => false,
    'summary_time' => '09:00',

    // Embed Settings
    'embed_color_new_booking' => '#00ff00',
    'embed_color_cancellation' => '#ff0000',
    'embed_color_rescheduling' => '#ffaa00',
    'embed_color_payment' => '#0099ff',
    'embed_thumbnail' => true,
    'embed_footer_text' => 'BookingX Notifications',
    'embed_footer_icon' => '',
    'embed_timestamp' => true,

    // Content Settings
    'include_customer_details' => true,
    'include_service_details' => true,
    'include_pricing' => true,
    'include_notes' => true,
    'include_staff_assignment' => true,

    // Interactive Features (Bot Mode Only)
    'enable_buttons' => true,
    'enable_approve_reject' => true,
    'enable_quick_actions' => true,

    // Mentions
    'mention_roles' => true,
    'mention_staff_members' => true,
    'default_mention_role' => '',

    // Channel Routing
    'enable_routing' => false,
    'route_by_service' => false,
    'route_by_status' => false,
    'fallback_channel' => '',

    // Rate Limiting
    'rate_limit_enabled' => true,
    'max_messages_per_minute' => 30,

    // Advanced
    'tts_enabled' => false,
    'allowed_mentions_everyone' => false,
    'debug_mode' => false,
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **WordPress Admin Interface**
   - Server connection wizard
   - Webhook setup form
   - Bot authorization button
   - Test notification button

### Backend Components

1. **Settings Page**
   - Mode selector (Webhook vs Bot)
   - Server configuration
   - Channel management
   - Embed customizer with preview
   - Routing rules builder

2. **Server Manager**
   - Connected servers list
   - Server details
   - Channel sync button
   - Role sync button
   - Test connection

3. **Channel Manager**
   - Available channels list
   - Default channel selection
   - Routing rule assignment
   - Send test message

4. **Embed Customizer**
   - Color pickers for each type
   - Template fields
   - Variable inserter
   - Live preview panel

5. **Message Log**
   - Sent messages list
   - Message preview
   - Delivery status
   - Interaction history
   - Resend option

6. **Routing Rules Builder**
   - Visual rule creator
   - Condition selection
   - Channel assignment
   - Role mention selection
   - Priority ordering

### Discord Components

1. **Embed Messages**
   - Branded header
   - Booking details fields
   - Customer information
   - Service details
   - Timestamp and footer

2. **Button Components**
   - Approve/Reject buttons
   - View details button
   - Quick action buttons
   - Link buttons

---

## 8. Security Considerations

### API Security
- **Bot Token:** Securely stored, never exposed
- **Webhook URL:** Encrypted storage
- **Public Key:** Signature verification (bot mode)
- **OAuth:** Secure token exchange
- **SSL/TLS:** All API calls over HTTPS

### Webhook Security
- **Signature Verification:** Validate Discord signatures (bot mode)
- **URL Privacy:** Webhook URLs kept private
- **Rate Limiting:** Prevent abuse
- **Content Validation:** Sanitize all inputs

### Data Security
- **Access Control:** Role-based permissions
- **Data Encryption:** Sensitive tokens encrypted
- **Audit Logging:** Track all actions
- **PII Protection:** Minimal personal data in Discord

### Compliance
- **Discord ToS:** Compliance with Discord terms
- **GDPR:** Data processing considerations
- **Data Retention:** Configurable retention
- **Right to Delete:** Remove server data

---

## 9. Testing Strategy

### Unit Tests
```php
- test_embed_building()
- test_webhook_sending()
- test_bot_authentication()
- test_component_building()
- test_interaction_handling()
- test_channel_routing()
- test_mention_formatting()
```

### Integration Tests
```php
- test_server_connection()
- test_message_sending()
- test_button_interactions()
- test_channel_sync()
- test_role_sync()
- test_webhook_fallback()
- test_routing_rules()
```

### Discord Testing
1. **Server Setup:** Create test Discord server
2. **Webhook Mode:** Test webhook notifications
3. **Bot Mode:** Test bot integration
4. **Embeds:** Verify embed rendering
5. **Buttons:** Test button interactions (bot mode)
6. **Mentions:** Test role/user mentions
7. **Permissions:** Test with different permissions
8. **Rate Limits:** Test rate limiting

---

## 10. Error Handling

### Error Categories
1. **Connection Errors:** Bot/webhook connection failures
2. **API Errors:** Discord API failures
3. **Permission Errors:** Missing Discord permissions
4. **Channel Errors:** Invalid/deleted channels
5. **Rate Limit Errors:** API rate limit exceeded

### Error Messages (User-Facing)
```php
'server_not_connected' => 'Discord server not connected. Please set up integration.',
'channel_not_found' => 'Discord channel not found. Please select a valid channel.',
'permission_denied' => 'Bot lacks required permissions in Discord channel.',
'webhook_invalid' => 'Webhook URL is invalid. Please check your settings.',
'rate_limited' => 'Discord rate limit reached. Message will be queued.',
```

### Logging
- All Discord API requests/responses
- Webhook sends
- Button interactions
- Connection events
- Errors and failures
- Rate limit events

### Retry Logic
```php
// Automatic retry for failed sends
Attempt 1: Immediate
Attempt 2: 5 seconds later
Attempt 3: 30 seconds later
Attempt 4: 5 minutes later (final)
```

---

## 11. Webhooks

### Discord Webhooks (Incoming)
```php
// Webhook send endpoint
POST https://discord.com/api/webhooks/{webhook_id}/{webhook_token}

// Features
- Send embeds
- Set username and avatar
- Thread support
```

### Discord Interactions (Bot Mode)
```php
// Interaction endpoint
/wp-json/bookingx/v1/discord/interactions

// Interaction types
- PING (verification)
- APPLICATION_COMMAND
- MESSAGE_COMPONENT (buttons)
- APPLICATION_COMMAND_AUTOCOMPLETE
- MODAL_SUBMIT
```

---

## 12. Performance Optimization

### API Optimization
- Rate limit compliance (50 req/sec per bot)
- Request batching
- Token caching
- Channel list caching

### Caching Strategy
- Cache server details (TTL: 1 hour)
- Cache channel list (TTL: 30 minutes)
- Cache role list (TTL: 30 minutes)
- Cache routing rules (TTL: 5 minutes)

### Database Optimization
- Indexed queries on server/channel lookups
- Pagination for message logs
- Archival of old interactions
- Efficient routing rule evaluation

### Message Optimization
- Efficient embed structure
- Minimal component usage
- Image optimization
- Webhook reuse

---

## 13. Internationalization

### Multi-language Support
- Embed content translations
- Field name translations
- Button label translations
- Error message localization

### Timezone Handling
- Display times in appropriate format
- Convert booking times
- Timestamp support

---

## 14. Documentation Requirements

### User Documentation
1. **Installation Guide**
   - Creating Discord bot (bot mode)
   - Setting up webhook (simple mode)
   - Connecting server
   - Channel configuration

2. **User Guide**
   - Understanding notifications
   - Using button interactions (bot mode)
   - Managing preferences

### Admin Documentation
1. **Setup Guide**
   - Bot vs Webhook mode
   - Discord app creation
   - Server authorization
   - Channel setup
   - Testing

2. **Management Guide**
   - Customizing embeds
   - Routing rules
   - Role mentions
   - Troubleshooting

### Developer Documentation
1. **API Reference**
   - Hooks and filters
   - Custom embed formats
   - Custom routing rules
   - Event handlers

2. **Customization Guide**
   - Custom notification types
   - Advanced embeds
   - Custom components
   - Extending functionality

---

## 15. Development Timeline

### Phase 1: Foundation (Week 1)
- [ ] Database schema creation
- [ ] Plugin structure
- [ ] Discord API library integration
- [ ] Admin settings framework

### Phase 2: Webhook Mode (Week 2)
- [ ] Webhook sender implementation
- [ ] Embed builder
- [ ] Basic notifications
- [ ] Channel routing

### Phase 3: Bot Mode (Week 3-4)
- [ ] Bot authentication
- [ ] OAuth flow
- [ ] Gateway connection
- [ ] Advanced features

### Phase 4: Interactive Components (Week 5)
- [ ] Button components
- [ ] Interaction handler
- [ ] Response system
- [ ] Action processing

### Phase 5: Routing & Mentions (Week 6)
- [ ] Routing rules engine
- [ ] Channel router
- [ ] Role mention system
- [ ] User mentions

### Phase 6: UI Development (Week 7)
- [ ] Settings interface
- [ ] Server manager
- [ ] Channel manager
- [ ] Embed customizer
- [ ] Rule builder

### Phase 7: Advanced Features (Week 8)
- [ ] Multi-server support
- [ ] Daily summaries
- [ ] Custom embeds
- [ ] Message editing

### Phase 8: Testing & QA (Week 9-10)
- [ ] Unit testing
- [ ] Integration testing
- [ ] Discord server testing
- [ ] Security audit
- [ ] Performance testing

### Phase 9: Documentation & Launch (Week 11)
- [ ] User documentation
- [ ] Admin guide
- [ ] Developer documentation
- [ ] Video tutorials
- [ ] Beta testing
- [ ] Production release

**Total Estimated Timeline:** 11 weeks (2.75 months)

---

## 16. Maintenance & Support

### Update Schedule
- **Security Updates:** As needed
- **Discord API Updates:** Quarterly
- **Bug Fixes:** Bi-weekly
- **Feature Updates:** Quarterly

### Support Channels
- Email support
- Documentation portal
- Video tutorials
- Community forum
- Discord server for users

### Monitoring
- Message delivery rate
- API error rate
- Interaction success rate
- Rate limit tracking
- Server connection health

---

## 17. Dependencies & Requirements

### Required WordPress Plugins
- BookingX Core 2.0+

### Optional Compatible Plugins
- BookingX Payments (payment notifications)
- BookingX Calendar (schedule summaries)

### Server Requirements
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- SSL Certificate (recommended)
- cURL extension
- JSON extension
- WordPress 5.8+

### External Services
- Discord server
- Discord bot/webhook configured
- Server admin permissions

### Discord Permissions Required (Bot Mode)
- View Channels
- Send Messages
- Embed Links
- Attach Files
- Read Message History
- Use External Emojis
- Add Reactions

---

## 18. Success Metrics

### Technical Metrics
- Message delivery rate > 99%
- Embed render success > 99.5%
- Interaction response time < 3 seconds
- API error rate < 1%
- Connection uptime > 99%

### Business Metrics
- Server integration rate > 25%
- Daily active servers > 60%
- Interaction rate > 30% (bot mode)
- User satisfaction > 4.5/5
- Feature adoption > 40%

---

## 19. Known Limitations

1. **Rate Limits:** Discord API rate limits
2. **Embed Size:** 6000 character limit
3. **Field Limits:** 25 fields per embed
4. **File Size:** 8MB attachment limit
5. **Webhook Limitations:** No interaction support
6. **Bot Hosting:** Requires hosting for gateway connection
7. **Permissions:** Requires specific Discord permissions

---

## 20. Future Enhancements

### Version 2.0 Roadmap
- [ ] Slash commands
- [ ] Context menus
- [ ] Voice channel status updates
- [ ] Thread support
- [ ] Forum channel integration
- [ ] Scheduled events sync
- [ ] Advanced modals
- [ ] Stage channel announcements

### Version 3.0 Roadmap
- [ ] Discord activities integration
- [ ] Voice booking confirmations
- [ ] AI-powered responses
- [ ] Advanced analytics in Discord
- [ ] Customer portal in Discord
- [ ] Payment processing via Discord
- [ ] Video consultation launch from Discord

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
