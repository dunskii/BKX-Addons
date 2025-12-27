# Advanced User Profiles Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Advanced User Profiles
**Price:** $79
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Enhanced customer profile system with comprehensive preference management, booking history visualization, favorite services/staff, saved payment methods, personalized dashboards, family account management, and social integration. Create personalized experiences for every customer.

### Value Proposition
- Personalized customer experiences
- Faster repeat bookings with saved preferences
- Family account management
- Saved payment methods for quick checkout
- Service and staff favorites
- Comprehensive booking history
- Personalized recommendations
- Loyalty integration
- Social profile connections

---

## 2. Features & Requirements

### Core Features
1. **Enhanced Profile Management**
   - Extended profile fields
   - Profile completeness meter
   - Avatar/photo upload
   - Bio/description
   - Social media links
   - Contact preferences
   - Privacy settings
   - Profile visibility controls

2. **Preference Management**
   - Service preferences
   - Preferred staff members
   - Preferred locations
   - Time slot preferences
   - Notification preferences
   - Language preferences
   - Accessibility requirements
   - Special requests/notes

3. **Booking History & Insights**
   - Visual booking calendar
   - Upcoming bookings
   - Past bookings archive
   - Cancellation history
   - Spending analytics
   - Favorite services chart
   - Booking patterns visualization
   - Export booking history

4. **Favorites & Wishlists**
   - Favorite services
   - Favorite staff members
   - Saved service combinations
   - Wishlist items
   - Quick rebook favorites
   - Share favorites
   - Favorite locations

5. **Family/Group Account Management**
   - Add family members
   - Book for family members
   - Individual preferences per member
   - Shared payment methods
   - Family booking calendar
   - Member permissions
   - Age-based service filtering

6. **Saved Information**
   - Multiple addresses
   - Payment methods vault
   - Emergency contacts
   - Medical information (optional)
   - Insurance information
   - Document uploads
   - Preferences templates

7. **Personalized Dashboard**
   - Customizable widgets
   - Quick action buttons
   - Recommended services
   - Special offers
   - Points/rewards display
   - Upcoming events
   - Recent activity feed

8. **Social Integration**
   - Social login (Google, Facebook, Apple)
   - Share bookings on social media
   - Review connections
   - Friend referrals
   - Social proof (friends who use service)
   - Import contacts

### User Roles & Permissions
- **Admin:** Full profile management, view all profiles
- **Manager:** View and edit customer profiles
- **Staff:** View assigned customer profiles (read-only)
- **Customer:** Full control of own profile and family members
- **Family Member:** Limited profile control (based on age/permissions)

---

## 3. Technical Specifications

### Technology Stack
- **Backend:** PHP with WordPress User Meta
- **Frontend:** React for interactive profile interface
- **File Storage:** WordPress Media Library + CDN
- **Payment Vault:** Tokenization via payment gateways
- **Social Auth:** OAuth 2.0 implementation
- **Charts:** Chart.js for data visualization

### Dependencies
- BookingX Core 2.0+
- WordPress User Management
- Payment Gateway add-on (for saved cards)
- Rewards add-on (optional integration)
- Social Login plugins (optional)

### API Integration Points
```php
// REST API Endpoints
GET    /wp-json/bookingx/v1/profiles/{id}
PUT    /wp-json/bookingx/v1/profiles/{id}
POST   /wp-json/bookingx/v1/profiles/{id}/avatar
GET    /wp-json/bookingx/v1/profiles/{id}/preferences
PUT    /wp-json/bookingx/v1/profiles/{id}/preferences
GET    /wp-json/bookingx/v1/profiles/{id}/booking-history
GET    /wp-json/bookingx/v1/profiles/{id}/favorites
POST   /wp-json/bookingx/v1/profiles/{id}/favorites
DELETE /wp-json/bookingx/v1/profiles/{id}/favorites/{item_id}
GET    /wp-json/bookingx/v1/profiles/{id}/family-members
POST   /wp-json/bookingx/v1/profiles/{id}/family-members
GET    /wp-json/bookingx/v1/profiles/{id}/saved-addresses
POST   /wp-json/bookingx/v1/profiles/{id}/saved-payment-methods
GET    /wp-json/bookingx/v1/profiles/{id}/analytics
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│   WordPress User    │
│   Management        │
└──────────┬──────────┘
           │
           ▼
┌──────────────────────────────┐
│  Advanced Profile System     │
│  - Extended Fields           │
│  - Preferences Engine        │
│  - Family Management         │
└──────────┬───────────────────┘
           │
     ┌─────┴─────┬────────┬────────┬─────────┐
     ▼           ▼        ▼        ▼         ▼
┌─────────┐ ┌────────┐ ┌──────┐ ┌──────┐ ┌──────────┐
│Favorite │ │ Saved  │ │Family│ │Social│ │Analytics │
│Manager  │ │ Data   │ │ Mgmt │ │ Auth │ │ Engine   │
└─────────┘ └────────┘ └──────┘ └──────┘ └──────────┘
```

### Class Structure
```php
namespace BookingX\Addons\UserProfiles;

class ProfileManager {
    - get_profile()
    - update_profile()
    - upload_avatar()
    - calculate_completeness()
    - merge_profiles()
    - delete_profile()
}

class PreferenceManager {
    - get_preferences()
    - set_preference()
    - get_default_preferences()
    - apply_preferences_to_booking()
    - export_preferences()
}

class FavoritesManager {
    - add_favorite()
    - remove_favorite()
    - get_favorites()
    - quick_rebook_favorite()
    - share_favorite()
}

class FamilyAccountManager {
    - add_family_member()
    - remove_family_member()
    - get_family_members()
    - set_member_permissions()
    - book_for_member()
    - get_family_calendar()
}

class SavedDataManager {
    - save_address()
    - get_addresses()
    - save_payment_method()
    - get_payment_methods()
    - delete_saved_item()
    - set_default()
}

class DashboardManager {
    - get_dashboard_widgets()
    - customize_dashboard()
    - get_recommendations()
    - get_quick_actions()
}

class SocialIntegration {
    - oauth_login()
    - link_social_account()
    - import_contacts()
    - share_to_social()
    - get_social_connections()
}

class ProfileAnalytics {
    - get_booking_stats()
    - get_spending_analysis()
    - get_service_preferences()
    - generate_insights()
    - export_data()
}
```

---

## 5. Database Schema

### Table: `bkx_user_profiles_extended`
```sql
CREATE TABLE bkx_user_profiles_extended (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL UNIQUE,
    avatar_url VARCHAR(500),
    bio TEXT,
    gender VARCHAR(20),
    date_of_birth DATE,
    occupation VARCHAR(200),
    website VARCHAR(500),
    instagram VARCHAR(100),
    facebook VARCHAR(100),
    twitter VARCHAR(100),
    linkedin VARCHAR(100),
    phone_secondary VARCHAR(20),
    emergency_contact_name VARCHAR(200),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relation VARCHAR(50),
    medical_info TEXT,
    accessibility_requirements TEXT,
    profile_completeness INT DEFAULT 0,
    profile_visibility VARCHAR(20) DEFAULT 'private',
    allow_social_sharing TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX user_id_idx (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_user_preferences`
```sql
CREATE TABLE bkx_user_preferences (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    preference_type VARCHAR(50),
    updated_at DATETIME NOT NULL,
    UNIQUE KEY unique_user_pref (user_id, preference_key),
    INDEX user_id_idx (user_id),
    INDEX pref_key_idx (preference_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_user_favorites`
```sql
CREATE TABLE bkx_user_favorites (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    favorite_type VARCHAR(50) NOT NULL,
    favorite_id BIGINT(20) UNSIGNED NOT NULL,
    favorite_name VARCHAR(200),
    favorite_metadata LONGTEXT,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX user_id_idx (user_id),
    INDEX type_idx (favorite_type),
    UNIQUE KEY unique_favorite (user_id, favorite_type, favorite_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_family_members`
```sql
CREATE TABLE bkx_family_members (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    primary_user_id BIGINT(20) UNSIGNED NOT NULL,
    member_user_id BIGINT(20) UNSIGNED,
    member_name VARCHAR(200) NOT NULL,
    relationship VARCHAR(50),
    date_of_birth DATE,
    gender VARCHAR(20),
    email VARCHAR(255),
    phone VARCHAR(20),
    special_notes TEXT,
    can_book_own TINYINT(1) DEFAULT 0,
    receive_notifications TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX primary_user_idx (primary_user_id),
    INDEX member_user_idx (member_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_saved_addresses`
```sql
CREATE TABLE bkx_saved_addresses (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    address_label VARCHAR(100),
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX user_id_idx (user_id),
    INDEX default_idx (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_saved_payment_methods`
```sql
CREATE TABLE bkx_saved_payment_methods (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    payment_gateway VARCHAR(50) NOT NULL,
    token VARCHAR(255) NOT NULL,
    card_type VARCHAR(50),
    last_four VARCHAR(4),
    expiry_month VARCHAR(2),
    expiry_year VARCHAR(4),
    cardholder_name VARCHAR(200),
    billing_address_id BIGINT(20) UNSIGNED,
    is_default TINYINT(1) DEFAULT 0,
    is_expired TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX user_id_idx (user_id),
    INDEX gateway_idx (payment_gateway),
    INDEX default_idx (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_social_connections`
```sql
CREATE TABLE bkx_social_connections (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    profile_data LONGTEXT,
    connected_at DATETIME NOT NULL,
    last_sync_at DATETIME,
    UNIQUE KEY unique_connection (user_id, provider),
    INDEX user_id_idx (user_id),
    INDEX provider_idx (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_user_dashboard_widgets`
```sql
CREATE TABLE bkx_user_dashboard_widgets (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    widget_type VARCHAR(50) NOT NULL,
    widget_config LONGTEXT,
    position INT DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    updated_at DATETIME NOT NULL,
    INDEX user_id_idx (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    // Profile Settings
    'enable_extended_profiles' => true,
    'require_profile_completion' => false,
    'min_profile_completion' => 50,
    'allow_avatar_upload' => true,
    'max_avatar_size_mb' => 5,
    'require_email_verification' => true,
    'require_phone_verification' => false,

    // Preferences
    'enable_preferences' => true,
    'show_preference_wizard' => true,
    'available_preferences' => [
        'preferred_staff',
        'preferred_location',
        'preferred_time',
        'communication_channel',
        'language'
    ],

    // Family Accounts
    'enable_family_accounts' => true,
    'max_family_members' => 10,
    'require_member_verification' => false,
    'allow_minor_accounts' => true,
    'minor_age_threshold' => 18,

    // Favorites
    'enable_favorites' => true,
    'max_favorites_per_type' => 20,
    'allow_favorite_sharing' => true,

    // Saved Data
    'enable_saved_addresses' => true,
    'max_saved_addresses' => 5,
    'enable_saved_payments' => true,
    'max_saved_payment_methods' => 5,

    // Social Integration
    'enable_social_login' => true,
    'social_providers' => ['google', 'facebook', 'apple'],
    'allow_social_sharing' => true,
    'import_social_data' => true,

    // Dashboard
    'enable_custom_dashboard' => true,
    'available_widgets' => [
        'upcoming_bookings',
        'rewards',
        'recommendations',
        'quick_book',
        'history',
        'favorites'
    ],

    // Privacy & Security
    'allow_profile_visibility_control' => true,
    'enable_data_export' => true,
    'enable_account_deletion' => true,
    'anonymize_on_deletion' => true,

    // Analytics
    'show_booking_analytics' => true,
    'show_spending_insights' => true,
    'enable_recommendations' => true,
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Profile Page**
   - Profile header (avatar, name, completeness meter)
   - Tabs: Info, Preferences, Favorites, Family, Security
   - Edit profile button
   - Quick stats cards
   - Recent activity feed

2. **Preferences Editor**
   - Categorized preference sections
   - Service preferences selector
   - Staff preferences with photos
   - Location preferences map
   - Time slot preferences calendar
   - Notification preferences checkboxes
   - Save button

3. **Favorites Section**
   - Grid/list view toggle
   - Service favorites cards
   - Staff favorites with ratings
   - Quick rebook buttons
   - Share favorite option
   - Remove from favorites

4. **Family Management**
   - Family members list
   - Add member form
   - Member profile cards
   - Permissions editor
   - Family calendar view
   - Book for member button

5. **Dashboard (Personalized)**
   - Draggable widget layout
   - Upcoming bookings widget
   - Quick book widget
   - Rewards balance widget
   - Recommendations carousel
   - Activity timeline widget
   - Customize dashboard button

6. **Booking History**
   - Calendar view option
   - List view with filters
   - Search functionality
   - Spending chart
   - Export data button
   - Rebook button per entry

### Backend Components

1. **Admin Profile Manager**
   - Customer profile search
   - View/edit customer profiles
   - Profile completeness stats
   - Bulk actions
   - Export customer data
   - Merge duplicate profiles

2. **Settings Page**
   - Profile configuration
   - Family account settings
   - Social integration setup
   - Privacy settings
   - Data retention policies

---

## 8. Security Considerations

### Data Protection
- **Payment Token Security:** Never store raw card data
- **Password Security:** Strong password enforcement
- **Two-Factor Auth:** Optional 2FA setup
- **Social Token Encryption:** Encrypt OAuth tokens
- **Profile Privacy:** Respect visibility settings

### Access Control
- Family member permissions enforcement
- Age-based content filtering
- Admin access logging
- GDPR compliance (data export/deletion)

---

## 9. Testing Strategy

### Unit Tests
```php
- test_profile_update()
- test_avatar_upload()
- test_preference_save()
- test_favorite_add_remove()
- test_family_member_creation()
- test_payment_method_tokenization()
- test_profile_completeness_calculation()
```

### Integration Tests
```php
- test_complete_profile_setup()
- test_booking_with_preferences()
- test_family_booking_workflow()
- test_social_login()
- test_data_export()
```

---

## 10. Development Timeline

### Phase 1: Foundation (Week 1)
- [ ] Database schema
- [ ] Core classes
- [ ] API endpoints

### Phase 2: Profile Enhancement (Week 2)
- [ ] Extended fields
- [ ] Avatar upload
- [ ] Completeness calculation

### Phase 3: Preferences (Week 3)
- [ ] Preference management
- [ ] Preference UI
- [ ] Integration with booking

### Phase 4: Favorites (Week 4)
- [ ] Favorites system
- [ ] Quick rebook
- [ ] Sharing

### Phase 5: Family Accounts (Week 5)
- [ ] Family management
- [ ] Permissions
- [ ] Family calendar

### Phase 6: Saved Data (Week 6)
- [ ] Address management
- [ ] Payment vault
- [ ] Integration with checkout

### Phase 7: Social & Dashboard (Week 7)
- [ ] Social login
- [ ] Dashboard customization
- [ ] Analytics

### Phase 8: Testing & Launch (Week 8-9)
- [ ] Testing
- [ ] Documentation
- [ ] Release

**Total Estimated Timeline:** 9 weeks (2.25 months)

---

## 11. Success Metrics

### Technical Metrics
- Profile load time < 1 second
- Avatar upload success > 98%
- Social login success > 95%

### Business Metrics
- Profile completion rate > 60%
- Repeat booking increase > 30%
- Checkout time reduction > 40%
- Family account adoption > 15%

---

## 12. Future Enhancements

### Version 2.0 Roadmap
- [ ] AI-powered recommendations
- [ ] Voice profile updates
- [ ] Biometric authentication
- [ ] Advanced analytics dashboard
- [ ] Social network integration
- [ ] Gamification elements

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
