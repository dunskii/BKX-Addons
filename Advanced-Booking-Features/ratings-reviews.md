# Ratings & Reviews System Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Ratings & Reviews System
**Price:** $79
**Category:** Advanced Booking Features
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Comprehensive customer rating and review system that allows customers to rate and review completed bookings. Includes admin response functionality, review moderation, aggregate rating display, and integration with schema markup for SEO benefits.

### Value Proposition
- Build trust with authentic customer reviews
- Improve SEO with structured data markup
- Enhance service quality through customer feedback
- Increase conversion rates with social proof
- Engage customers with admin responses
- Filter and showcase top-rated services

---

## 2. Features & Requirements

### Core Features
1. **Customer Reviews**
   - Star rating system (1-5 stars)
   - Written review with title and description
   - Photo/image upload with reviews
   - Review after booking completion
   - Edit own reviews (time-limited)
   - Anonymous review option

2. **Admin Response System**
   - Reply to customer reviews
   - Thank customers for positive feedback
   - Address negative reviews professionally
   - Edit/update admin responses
   - Response notification to customers

3. **Review Moderation**
   - Automatic or manual approval workflow
   - Spam detection and filtering
   - Profanity filtering
   - Flag inappropriate reviews
   - Bulk moderation actions
   - Review status tracking

4. **Display & Widgets**
   - Service aggregate rating display
   - Recent reviews widget
   - Top-rated services showcase
   - Review carousel/slider
   - Filtering by rating
   - Sort by date, rating, helpfulness

5. **Analytics & Reporting**
   - Average rating trends
   - Review volume over time
   - Response rate tracking
   - Sentiment analysis
   - Service comparison reports

### User Roles & Permissions
- **Admin:** Full moderation, respond to all reviews, configure settings
- **Manager:** Moderate reviews, respond to reviews for managed services
- **Staff:** Respond to reviews for their services (if enabled)
- **Customer:** Submit reviews, edit own reviews, vote on helpfulness

---

## 3. Technical Specifications

### Technology Stack
- **Frontend:** React/Vue.js for dynamic review interface
- **Backend:** WordPress REST API endpoints
- **Star Rating:** Custom SVG icons with accessibility
- **Image Handling:** WordPress Media Library integration
- **Schema Markup:** JSON-LD for review snippets
- **Moderation:** Custom WordPress admin interface

### Dependencies
- BookingX Core 2.0+
- WordPress Media Library
- PHP GD or Imagick extension (for image processing)
- WordPress REST API enabled
- jQuery (for frontend interactions)

### API Integration Points
```php
// REST API Endpoints
POST   /wp-json/bookingx/v1/reviews
GET    /wp-json/bookingx/v1/reviews/{id}
PUT    /wp-json/bookingx/v1/reviews/{id}
DELETE /wp-json/bookingx/v1/reviews/{id}
POST   /wp-json/bookingx/v1/reviews/{id}/response
POST   /wp-json/bookingx/v1/reviews/{id}/flag
POST   /wp-json/bookingx/v1/reviews/{id}/helpful
GET    /wp-json/bookingx/v1/reviews/stats
GET    /wp-json/bookingx/v1/reviews/service/{service_id}
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────┐
│   BookingX Core     │
│  (Booking System)   │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────┐
│  Review Management System   │
│  - Submit Review            │
│  - Moderate Review          │
│  - Admin Response           │
└──────────┬──────────────────┘
           │
           ├──────────────────────────┐
           ▼                          ▼
┌──────────────────────┐   ┌──────────────────────┐
│  Display Engine      │   │  Analytics Engine    │
│  - Widgets           │   │  - Rating Trends     │
│  - Schema Markup     │   │  - Reports           │
│  - Filters           │   │  - Sentiment         │
└──────────────────────┘   └──────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\Reviews;

class ReviewManager {
    - submit_review()
    - update_review()
    - delete_review()
    - get_reviews()
    - calculate_average_rating()
    - validate_review_eligibility()
}

class ReviewModeration {
    - approve_review()
    - reject_review()
    - flag_review()
    - check_spam()
    - filter_profanity()
    - bulk_moderate()
}

class AdminResponse {
    - add_response()
    - update_response()
    - delete_response()
    - notify_customer()
    - get_response_stats()
}

class ReviewDisplay {
    - render_review_form()
    - render_review_list()
    - render_rating_summary()
    - generate_schema_markup()
    - render_review_widget()
}

class ReviewAnalytics {
    - get_rating_trends()
    - get_review_volume()
    - calculate_response_rate()
    - generate_sentiment_score()
    - export_review_data()
}

class ReviewNotifications {
    - notify_admin_new_review()
    - notify_customer_response()
    - notify_helpful_votes()
    - send_review_request()
}
```

---

## 5. Database Schema

### Table: `bkx_reviews`
```sql
CREATE TABLE bkx_reviews (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    service_id BIGINT(20) UNSIGNED NOT NULL,
    customer_id BIGINT(20) UNSIGNED NOT NULL,
    staff_id BIGINT(20) UNSIGNED,
    rating TINYINT(1) NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(200),
    review_text TEXT,
    is_anonymous TINYINT(1) DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    flagged TINYINT(1) DEFAULT 0,
    flag_reason TEXT,
    helpful_count INT DEFAULT 0,
    not_helpful_count INT DEFAULT 0,
    verified_booking TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    approved_at DATETIME,
    approved_by BIGINT(20) UNSIGNED,
    INDEX booking_id_idx (booking_id),
    INDEX service_id_idx (service_id),
    INDEX customer_id_idx (customer_id),
    INDEX staff_id_idx (staff_id),
    INDEX status_idx (status),
    INDEX rating_idx (rating),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_review_images`
```sql
CREATE TABLE bkx_review_images (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id BIGINT(20) UNSIGNED NOT NULL,
    attachment_id BIGINT(20) UNSIGNED NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(500),
    display_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX review_id_idx (review_id),
    INDEX attachment_id_idx (attachment_id),
    FOREIGN KEY (review_id) REFERENCES bkx_reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_review_responses`
```sql
CREATE TABLE bkx_review_responses (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id BIGINT(20) UNSIGNED NOT NULL,
    response_text TEXT NOT NULL,
    responder_id BIGINT(20) UNSIGNED NOT NULL,
    responder_name VARCHAR(200),
    responder_role VARCHAR(50),
    is_public TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX review_id_idx (review_id),
    INDEX responder_id_idx (responder_id),
    FOREIGN KEY (review_id) REFERENCES bkx_reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_review_votes`
```sql
CREATE TABLE bkx_review_votes (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED,
    ip_address VARCHAR(45),
    vote_type VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX review_id_idx (review_id),
    INDEX user_id_idx (user_id),
    UNIQUE KEY unique_vote (review_id, user_id),
    FOREIGN KEY (review_id) REFERENCES bkx_reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_review_stats`
```sql
CREATE TABLE bkx_review_stats (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT(20) UNSIGNED NOT NULL,
    average_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
    total_reviews INT DEFAULT 0,
    rating_5_count INT DEFAULT 0,
    rating_4_count INT DEFAULT 0,
    rating_3_count INT DEFAULT 0,
    rating_2_count INT DEFAULT 0,
    rating_1_count INT DEFAULT 0,
    last_review_date DATETIME,
    updated_at DATETIME NOT NULL,
    INDEX entity_idx (entity_type, entity_id),
    INDEX rating_idx (average_rating),
    UNIQUE KEY unique_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
// Settings structure
[
    // General Settings
    'enable_reviews' => true,
    'review_required_status' => 'completed',
    'allow_anonymous_reviews' => false,
    'verified_badge' => true,

    // Submission Settings
    'min_rating' => 1,
    'max_rating' => 5,
    'require_review_text' => true,
    'min_review_length' => 50,
    'max_review_length' => 2000,
    'allow_images' => true,
    'max_images_per_review' => 5,
    'allow_video' => false,

    // Moderation Settings
    'auto_approve' => false,
    'require_moderation_below_rating' => 3,
    'enable_spam_detection' => true,
    'enable_profanity_filter' => true,
    'profanity_action' => 'flag',
    'duplicate_detection' => true,

    // Edit & Delete
    'allow_customer_edit' => true,
    'edit_time_limit' => 7,
    'allow_customer_delete' => false,

    // Response Settings
    'enable_admin_responses' => true,
    'staff_can_respond' => true,
    'response_notification' => true,

    // Display Settings
    'reviews_per_page' => 10,
    'default_sort' => 'newest',
    'show_helpful_voting' => true,
    'show_verified_badge' => true,
    'show_response_time' => true,
    'minimum_rating_display' => 1,

    // Schema Markup
    'enable_schema_markup' => true,
    'schema_business_name' => '',
    'schema_business_type' => 'LocalBusiness',

    // Notifications
    'notify_admin_new_review' => true,
    'notify_staff_new_review' => true,
    'notify_customer_response' => true,
    'auto_review_request' => true,
    'review_request_delay_days' => 3,
]
```

---

## 7. User Interface Requirements

### Frontend Components

1. **Review Submission Form**
   - Star rating selector (interactive)
   - Review title input
   - Review text area (with character counter)
   - Image upload interface
   - Anonymous option checkbox
   - Terms agreement checkbox
   - Submit button with loading state
   - Validation messages

2. **Review Display**
   - Star rating visual display
   - Review title and text
   - Customer name/avatar (or "Anonymous")
   - Verified booking badge
   - Date posted
   - Admin response section
   - Helpful voting buttons
   - Flag review option
   - Image gallery/lightbox

3. **Rating Summary**
   - Overall average rating (large display)
   - Total number of reviews
   - Rating distribution bars (5-4-3-2-1 stars)
   - Percentage for each rating level
   - Filter by rating buttons

4. **Review List**
   - Sort dropdown (newest, highest, lowest, helpful)
   - Filter by rating
   - Pagination controls
   - Load more button option
   - Empty state message

### Backend Components

1. **Review Management Page**
   - Reviews list table with columns:
     - Customer, Service, Rating, Date, Status
   - Bulk actions (approve, reject, delete)
   - Filter by status, rating, date, service
   - Search functionality
   - Quick approve/reject buttons
   - View/edit modal

2. **Review Details Modal**
   - Full review information
   - Customer details with booking link
   - Moderation actions
   - Response editor
   - Activity log
   - Flag history

3. **Analytics Dashboard**
   - Rating trends chart
   - Review volume graph
   - Response rate metrics
   - Top/bottom rated services
   - Recent reviews widget
   - Export reports button

4. **Settings Page**
   - General settings tab
   - Moderation settings tab
   - Display settings tab
   - Notification settings tab
   - Schema markup settings tab

---

## 8. Security Considerations

### Data Security
- **Input Sanitization:** Clean all review text and titles
- **XSS Prevention:** Escape output, strip dangerous HTML
- **SQL Injection:** Use prepared statements
- **Image Upload:** Validate file types, scan for malware
- **Rate Limiting:** Prevent review spam (max 1 review per booking)

### Authentication & Authorization
- Verify customer made the booking before allowing review
- Nonce verification for form submissions
- Capability checks for moderation actions
- IP tracking to prevent abuse
- User agent logging for spam detection

### Privacy & Compliance
- **GDPR:** Allow customers to export/delete their reviews
- **Anonymous Option:** Don't store identifiable info for anonymous reviews
- **Data Retention:** Configurable retention policy
- **Right to be Forgotten:** Complete data removal on request

---

## 9. Testing Strategy

### Unit Tests
```php
- test_review_submission()
- test_rating_validation()
- test_review_eligibility()
- test_average_rating_calculation()
- test_spam_detection()
- test_profanity_filtering()
- test_admin_response()
- test_helpful_voting()
- test_schema_markup_generation()
```

### Integration Tests
```php
- test_complete_review_workflow()
- test_moderation_approval_process()
- test_review_with_images()
- test_admin_response_notification()
- test_review_stats_update()
- test_review_editing_within_time_limit()
```

### Test Scenarios
1. **Submit Review:** Customer submits review after booking completion
2. **Multiple Ratings:** Submit various star ratings and verify display
3. **Moderation:** Test approve/reject workflow
4. **Admin Response:** Add response and verify customer notification
5. **Image Upload:** Upload multiple images with review
6. **Spam Detection:** Submit spam content and verify filtering
7. **Helpful Voting:** Vote on review helpfulness
8. **Anonymous Review:** Submit anonymous review and verify privacy
9. **Edit Review:** Edit within time limit, attempt edit after expiry
10. **Schema Markup:** Verify correct JSON-LD output

---

## 10. Error Handling

### Error Categories
1. **Validation Errors:** Invalid rating, text too short/long, missing required fields
2. **Authorization Errors:** Not eligible to review, already reviewed
3. **Upload Errors:** Invalid image format, file too large
4. **Rate Limit Errors:** Too many attempts, spam detected

### Error Messages (User-Facing)
```php
'not_eligible' => 'You can only review bookings you have completed.',
'already_reviewed' => 'You have already reviewed this booking.',
'rating_required' => 'Please select a star rating.',
'text_too_short' => 'Your review must be at least {min} characters long.',
'text_too_long' => 'Your review cannot exceed {max} characters.',
'invalid_image' => 'Please upload a valid image file (JPG, PNG, GIF).',
'image_too_large' => 'Image file size cannot exceed {size}MB.',
'spam_detected' => 'Your review could not be submitted. Please contact support.',
'edit_expired' => 'The time limit for editing this review has passed.',
```

### Logging
- All review submissions (including failed attempts)
- Moderation actions with admin user
- Spam detection triggers
- Admin responses
- Flagged reviews with reasons
- Failed image uploads

---

## 11. Performance Optimization

### Caching Strategy
- Cache aggregate ratings (TTL: 1 hour, clear on new review)
- Cache review counts per service
- Cache top-rated services list
- Fragment caching for review widgets
- Object caching for review stats

### Database Optimization
- Indexed queries on service_id and customer_id
- Maintain separate stats table for aggregates
- Archive old reviews (2+ years)
- Pagination for large review lists
- Lazy load images in review gallery

### Frontend Optimization
- Lazy load review images
- Infinite scroll for review lists
- Debounce helpful voting
- Compress uploaded images
- CDN delivery for review images

---

## 12. Internationalization

### Supported Languages
- Translatable strings via WordPress i18n
- RTL support for review text
- Date/time localization
- Number formatting (ratings display)

### Multi-language Reviews
- Language detection for reviews
- Filter reviews by language (optional)
- Translate interface strings
- Support for non-Latin characters

---

## 13. Documentation Requirements

### User Documentation
1. **Customer Guide**
   - How to submit a review
   - Adding images to reviews
   - Editing your review
   - Voting on helpful reviews
   - Understanding verified badges

2. **Admin Guide**
   - Moderating reviews
   - Responding to reviews
   - Managing flagged content
   - Configuring settings
   - Viewing analytics
   - Handling spam

### Developer Documentation
1. **API Reference**
   - REST API endpoints
   - Filter hooks for customization
   - Action hooks for extensions
   - Schema markup customization

2. **Integration Guide**
   - Display reviews on custom pages
   - Custom review widgets
   - Third-party review platforms
   - Email template customization

---

## 14. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema creation and testing
- [ ] Core ReviewManager class
- [ ] REST API endpoint structure
- [ ] Basic admin settings page
- [ ] User capability system

### Phase 2: Review Submission (Week 3)
- [ ] Review eligibility validation
- [ ] Frontend review form
- [ ] Star rating component
- [ ] Image upload functionality
- [ ] Review submission processing

### Phase 3: Moderation System (Week 4)
- [ ] Admin moderation interface
- [ ] Spam detection algorithm
- [ ] Profanity filter implementation
- [ ] Bulk moderation actions
- [ ] Review flagging system

### Phase 4: Display & Widgets (Week 5)
- [ ] Review list display
- [ ] Rating summary component
- [ ] Review widgets
- [ ] Schema markup generation
- [ ] Helpful voting system

### Phase 5: Admin Response (Week 6)
- [ ] Response submission interface
- [ ] Response display integration
- [ ] Customer notifications
- [ ] Response analytics
- [ ] Staff permission handling

### Phase 6: Analytics & Reporting (Week 7)
- [ ] Rating trends calculation
- [ ] Analytics dashboard
- [ ] Review statistics
- [ ] Export functionality
- [ ] Report generation

### Phase 7: Testing & QA (Week 8-9)
- [ ] Unit test development
- [ ] Integration testing
- [ ] Security audit
- [ ] Performance testing
- [ ] User acceptance testing

### Phase 8: Documentation & Launch (Week 10)
- [ ] User documentation
- [ ] Admin guide
- [ ] Video tutorials
- [ ] Beta testing
- [ ] Production release

**Total Estimated Timeline:** 10 weeks (2.5 months)

---

## 15. Maintenance & Support

### Update Schedule
- **Security Updates:** As needed
- **Bug Fixes:** Bi-weekly
- **Feature Updates:** Quarterly
- **WordPress Compatibility:** With major WP releases

### Support Channels
- Email support
- Documentation portal
- Video tutorials
- Community forum
- Priority support (addon customers)

### Monitoring
- Review submission rates
- Approval/rejection rates
- Response rates
- Spam detection accuracy
- Customer satisfaction metrics

---

## 16. Dependencies & Requirements

### Required WordPress Plugins
- BookingX Core 2.0+

### Optional Compatible Plugins
- WooCommerce (for product reviews integration)
- BuddyPress (for social profile integration)
- bbPress (for forum integration)

### Server Requirements
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- PHP GD or Imagick extension
- WordPress 5.8+
- Memory: 128MB+ PHP memory limit

---

## 17. Success Metrics

### Technical Metrics
- Review submission success rate > 95%
- Page load time < 2 seconds
- Image upload success rate > 98%
- Spam detection accuracy > 90%
- Zero SQL injection vulnerabilities

### Business Metrics
- Review collection rate > 20% of bookings
- Admin response rate > 60%
- Average rating display on all services
- Increase in booking conversions > 15%
- Customer satisfaction > 4.5/5

---

## 18. Known Limitations

1. **Review Eligibility:** One review per booking (can't review same service twice from one booking)
2. **Edit Window:** Time-limited editing (default 7 days)
3. **Image Size:** Upload size limited by server configuration
4. **Spam Detection:** May occasionally flag legitimate reviews
5. **Anonymous Reviews:** Cannot be edited after submission
6. **Video Reviews:** Not supported in v1.0

---

## 19. Future Enhancements

### Version 2.0 Roadmap
- [ ] Video review support
- [ ] Review rewards program integration
- [ ] AI-powered sentiment analysis
- [ ] Multi-language review translation
- [ ] Review request automation
- [ ] Integration with third-party review platforms
- [ ] Review syndication to Google/Yelp
- [ ] Advanced spam detection with ML

### Version 3.0 Roadmap
- [ ] Voice review submission
- [ ] Live review streaming
- [ ] Review analytics predictions
- [ ] Automated response suggestions
- [ ] Review gamification
- [ ] Social media review sharing
- [ ] Review comparison tools

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
