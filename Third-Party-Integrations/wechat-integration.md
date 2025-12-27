# WeChat Mini Program Integration - Development Documentation

## 1. Overview

**Add-on Name:** WeChat Mini Program Integration
**Price:** $129
**Category:** Third-Party Platform Integrations
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Complete WeChat ecosystem integration featuring Mini Program booking, Official Account messaging, WeChat Pay, and social sharing. Provides seamless booking experience for Chinese market with full localization, mobile-first design, and WeChat-native features.

### Value Proposition
- **Massive Market:** 1.3+ billion WeChat users
- **China Essential:** Dominant platform in Chinese market
- **Social Integration:** Share bookings, moments, groups
- **WeChat Pay:** Preferred payment in China
- **Mini Programs:** No app download required
- **Official Account:** Direct customer communication
- **Social Commerce:** Viral booking potential

---

## 2. Features & Requirements

### Core Features
1. **WeChat Mini Program**
   - Native booking interface
   - Service browsing
   - Real-time availability
   - Instant booking
   - Location-based search
   - QR code access
   - Offline capability

2. **WeChat Official Account**
   - Subscription messages
   - Template messages
   - Customer service chat
   - Menu navigation
   - Article publishing
   - Follower management

3. **WeChat Pay**
   - Integrated payments
   - Refund processing
   - Red packet promotions
   - Coupons and discounts
   - Transaction records
   - Wallet integration

4. **Social Features**
   - Share to moments
   - Group sharing
   - Friend referrals
   - Coupon sharing
   - Review and ratings
   - Live chat support

5. **Chinese Market Features**
   - Simplified/Traditional Chinese
   - Chinese calendar support
   - Local holidays
   - CNY currency
   - SMS via Chinese gateways
   - ICP filing compliance

### User Roles & Permissions
- **Admin:** Full WeChat ecosystem configuration
- **Manager:** Mini Program management, customer chat
- **Staff:** View WeChat bookings
- **Customer:** Book via Mini Program

---

## 3. Technical Specifications

### Technology Stack
- **Mini Program:** WXML, WXSS, JavaScript
- **Framework:** WeChat Mini Program SDK
- **Backend:** WordPress REST API
- **Payment:** WeChat Pay API v3
- **Auth:** WeChat OAuth 2.0
- **Cloud:** WeChat Cloud Development (optional)

### Dependencies
- BookingX Core 2.0+
- WeChat Official Account (Verified)
- WeChat Mini Program account
- WeChat Pay merchant account
- SSL certificate (required)
- ICP filing (for China hosting)

### API Integration Points
```php
// WeChat API endpoints
- POST /cgi-bin/token (access token)
- POST /cgi-bin/message/template/send (notifications)
- POST /sns/jscode2session (login)
- POST /pay/unifiedorder (payment)
- POST /pay/refund (refunds)

// BookingX API endpoints
- GET /wp-json/bookingx/v1/wechat/services
- GET /wp-json/bookingx/v1/wechat/availability
- POST /wp-json/bookingx/v1/wechat/bookings
- POST /wp-json/bookingx/v1/wechat/auth
- POST /wp-json/bookingx/v1/wechat/payment/notify
```

### Mini Program Structure
```
mini-program/
├── pages/
│   ├── index/          (Home)
│   ├── services/       (Service List)
│   ├── booking/        (Booking Form)
│   ├── bookings/       (My Bookings)
│   ├── profile/        (User Profile)
│   └── payment/        (Payment)
├── components/
│   ├── service-card/
│   ├── calendar/
│   ├── time-picker/
│   └── booking-card/
├── utils/
│   ├── api.js
│   ├── auth.js
│   └── payment.js
├── app.js
├── app.json
└── app.wxss
```

---

## 4. Architecture & Design

### System Architecture
```
┌─────────────────────────┐
│   WeChat Ecosystem      │
│   - Mini Program        │
│   - Official Account    │
│   - WeChat Pay          │
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│   WeChat APIs           │
│   - Login               │
│   - Payment             │
│   - Messages            │
│   - Cloud Functions     │
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│  BookingX WeChat Add-on │
│  - Mini Program Backend │
│  - Message Handler      │
│  - Payment Processor    │
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│   BookingX Core         │
│   - Services            │
│   - Bookings            │
│   - Payments            │
└─────────────────────────┘
```

### Class Structure
```php
namespace BookingX\Addons\WeChat;

class WeChatIntegration {
    - init()
    - registerAPIs()
    - getAccessToken()
    - refreshAccessToken()
}

class MiniProgramAuth {
    - handleLogin()
    - code2Session()
    - decryptData()
    - generateSessionKey()
    - validateSession()
}

class OfficialAccountHandler {
    - handleMessage()
    - sendTemplateMessage()
    - sendCustomerMessage()
    - handleEvent()
    - handleMenuClick()
}

class WeChatPayment {
    - createOrder()
    - handleNotify()
    - queryOrder()
    - refund()
    - closeOrder()
}

class MessageTemplates {
    - sendBookingConfirmation()
    - sendReminder()
    - sendCancellation()
    - sendPromotion()
}

class WeChatAnalytics {
    - trackMiniProgramVisit()
    - trackBooking()
    - trackPayment()
    - trackSharing()
    - generateReport()
}
```

---

## 5. Database Schema

### Table: `bkx_wechat_accounts`
```sql
CREATE TABLE bkx_wechat_accounts (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id BIGINT(20) UNSIGNED NOT NULL,
    account_type VARCHAR(50) NOT NULL,
    app_id VARCHAR(255) NOT NULL,
    app_secret TEXT NOT NULL,
    access_token TEXT,
    token_expires_at DATETIME,
    original_id VARCHAR(255),
    account_name VARCHAR(255),
    is_verified TINYINT(1) DEFAULT 0,
    settings LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX business_idx (business_id),
    INDEX app_id_idx (app_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_wechat_users`
```sql
CREATE TABLE bkx_wechat_users (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED,
    open_id VARCHAR(255) NOT NULL,
    union_id VARCHAR(255),
    account_id BIGINT(20) UNSIGNED NOT NULL,
    nickname VARCHAR(255),
    avatar_url VARCHAR(500),
    gender TINYINT,
    country VARCHAR(100),
    province VARCHAR(100),
    city VARCHAR(100),
    language VARCHAR(20),
    session_key TEXT,
    phone VARCHAR(50),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX user_idx (user_id),
    INDEX open_id_idx (open_id),
    INDEX union_id_idx (union_id),
    UNIQUE KEY unique_open_id (open_id, account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_wechat_bookings`
```sql
CREATE TABLE bkx_wechat_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    wechat_user_id BIGINT(20) UNSIGNED NOT NULL,
    account_id BIGINT(20) UNSIGNED NOT NULL,
    booking_source VARCHAR(50) DEFAULT 'mini_program',
    form_id VARCHAR(100),
    scene VARCHAR(100),
    share_ticket VARCHAR(255),
    metadata LONGTEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_idx (booking_id),
    INDEX user_idx (wechat_user_id),
    INDEX account_idx (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_wechat_payments`
```sql
CREATE TABLE bkx_wechat_payments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    wechat_user_id BIGINT(20) UNSIGNED NOT NULL,
    out_trade_no VARCHAR(255) NOT NULL UNIQUE,
    transaction_id VARCHAR(255),
    trade_type VARCHAR(50),
    total_fee INT NOT NULL,
    currency VARCHAR(10) DEFAULT 'CNY',
    status VARCHAR(50) NOT NULL,
    time_paid DATETIME,
    notify_data LONGTEXT,
    refund_id VARCHAR(255),
    refund_fee INT,
    refund_status VARCHAR(50),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_idx (booking_id),
    INDEX trade_no_idx (out_trade_no),
    INDEX transaction_idx (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_wechat_messages`
```sql
CREATE TABLE bkx_wechat_messages (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT(20) UNSIGNED NOT NULL,
    wechat_user_id BIGINT(20) UNSIGNED,
    msg_id VARCHAR(255),
    msg_type VARCHAR(50) NOT NULL,
    direction VARCHAR(20) NOT NULL,
    content TEXT,
    media_id VARCHAR(255),
    template_id VARCHAR(255),
    event_type VARCHAR(50),
    sent_at DATETIME,
    received_at DATETIME,
    status VARCHAR(50),
    INDEX account_idx (account_id),
    INDEX user_idx (wechat_user_id),
    INDEX msg_id_idx (msg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_wechat_analytics`
```sql
CREATE TABLE bkx_wechat_analytics (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT(20) UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    page_views INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    new_users INT DEFAULT 0,
    bookings INT DEFAULT 0,
    payments INT DEFAULT 0,
    shares INT DEFAULT 0,
    revenue DECIMAL(10,2) DEFAULT 0,
    source_breakdown LONGTEXT,
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
    // Mini Program Configuration
    'miniprogram_app_id' => '',
    'miniprogram_app_secret' => '',
    'miniprogram_original_id' => '',

    // Official Account
    'official_account_app_id' => '',
    'official_account_app_secret' => '',
    'official_account_token' => '',
    'official_account_encoding_aes_key' => '',

    // WeChat Pay
    'wechat_pay_merchant_id' => '',
    'wechat_pay_api_key' => '',
    'wechat_pay_cert_path' => '',
    'wechat_pay_key_path' => '',
    'notify_url' => '',

    // Features
    'enable_mini_program' => true,
    'enable_official_account' => true,
    'enable_wechat_pay' => true,
    'enable_template_messages' => true,
    'enable_customer_service' => true,

    // Localization
    'default_language' => 'zh_CN',
    'support_traditional' => false,
    'timezone' => 'Asia/Shanghai',
    'currency' => 'CNY',

    // Social Features
    'enable_sharing' => true,
    'enable_moments' => true,
    'enable_group_sharing' => true,
    'referral_rewards' => true,

    // Mini Program Settings
    'pages_config' => [],
    'tab_bar_config' => [],
    'window_config' => [],

    // Advanced
    'debug_mode' => false,
    'sandbox_mode' => false,
    'log_requests' => true,
]
```

---

## 7. User Interface Requirements

### Mini Program Pages

1. **Home Page (首页)**
   - Service carousel
   - Popular services
   - Promotions banner
   - Location selector
   - Quick booking

2. **Service List (服务列表)**
   - Category filter
   - Search function
   - Service cards with images
   - Price display
   - Ratings and reviews

3. **Booking Page (预约页面)**
   - Service selection
   - Date picker (Chinese calendar)
   - Time slot selection
   - Customer info form
   - Special requests
   - Total price

4. **Payment Page (支付页面)**
   - Order summary
   - Coupon application
   - WeChat Pay button
   - Payment confirmation

5. **My Bookings (我的预约)**
   - Upcoming bookings
   - Past bookings
   - Booking details
   - Cancel/Reschedule
   - Customer service chat

6. **Profile (个人中心)**
   - User info
   - Settings
   - Coupons
   - Points/Credits
   - Customer service

### Backend Components

1. **WeChat Dashboard**
   - Mini Program stats
   - Official Account followers
   - Payment summary
   - Recent bookings
   - Quick actions

2. **Configuration Pages**
   - Account setup
   - Payment configuration
   - Template message setup
   - Menu configuration
   - QR code generator

3. **Message Center**
   - Customer conversations
   - Template messages sent
   - Auto-reply settings
   - Keywords management

4. **Analytics Dashboard**
   - Mini Program analytics
   - User demographics
   - Booking trends
   - Revenue reports
   - Sharing analytics

---

## 8. Security Considerations

### Data Security
- **HTTPS Required:** All communications encrypted
- **Data Encryption:** WeChat API data decryption
- **Signature Validation:** Verify all WeChat requests
- **Token Security:** Secure access token storage
- **Payment Security:** WeChat Pay security standards
- **User Privacy:** PIPL compliance (China)

### Authentication & Authorization
- **WeChat OAuth:** Secure user authentication
- **Session Management:** Secure session keys
- **OpenID/UnionID:** User identification
- **Phone Verification:** SMS verification for bookings
- **Admin Access:** Role-based permissions

### Compliance
- **ICP Filing:** Required for China hosting
- **Personal Information Protection Law (PIPL):** China privacy law
- **Cybersecurity Law:** China regulations
- **WeChat Platform Rules:** Full compliance
- **Payment Regulations:** PBoC requirements

---

## 9. Testing Strategy

### Unit Tests
```php
- test_wechat_auth()
- test_access_token_refresh()
- test_mini_program_login()
- test_payment_creation()
- test_payment_notification()
- test_template_message()
- test_data_decryption()
```

### Integration Tests
```php
- test_complete_booking_flow()
- test_wechat_pay_flow()
- test_official_account_messaging()
- test_mini_program_sharing()
- test_refund_processing()
```

### Test Scenarios
1. **Mini Program Login:** WeChat OAuth flow
2. **Service Browsing:** View and filter services
3. **Booking Creation:** Complete booking process
4. **WeChat Payment:** Pay with WeChat Pay
5. **Template Message:** Receive confirmation
6. **Sharing:** Share to moments/groups
7. **Cancellation:** Cancel and refund
8. **Customer Service:** Chat with business
9. **Coupon Usage:** Apply discount code
10. **Review:** Leave rating and review

### WeChat Testing Tools
- WeChat Developer Tools
- Mini Program Simulator
- Payment Sandbox
- Message Testing
- QR Code Testing

---

## 10. Error Handling

### Error Categories
1. **Auth Errors:** Login failures, token expiry
2. **Payment Errors:** Transaction failures, insufficient balance
3. **API Errors:** WeChat API failures, rate limits
4. **Network Errors:** Connection issues, timeouts
5. **Validation Errors:** Invalid input, data errors

### User-Facing Messages (Chinese)
```php
// Error messages in Simplified Chinese
'auth_failed' => '登录失败，请重试',
'payment_failed' => '支付失败，请重试',
'booking_unavailable' => '该时段已被预约',
'network_error' => '网络错误，请检查网络连接',
'system_error' => '系统错误，请稍后再试',
'invalid_input' => '输入信息有误，请检查',
'session_expired' => '登录已过期，请重新登录',
```

### Logging
- All API requests to WeChat
- Payment transactions
- User actions
- Booking operations
- Errors and exceptions
- Performance metrics

---

## 11. WeChat Pay Integration

### Payment Flow
```javascript
// Mini Program payment
wx.requestPayment({
  timeStamp: '',
  nonceStr: '',
  package: 'prepay_id=xxx',
  signType: 'MD5',
  paySign: '',
  success: function(res) {
    // Payment successful
    wx.navigateTo({
      url: '/pages/booking/success'
    })
  },
  fail: function(res) {
    // Payment failed
    wx.showToast({
      title: '支付失败',
      icon: 'none'
    })
  }
})
```

### Payment Notification Handler
```php
public function handlePaymentNotify() {
    $xml = file_get_contents('php://input');
    $data = $this->xmlToArray($xml);

    // Verify signature
    if (!$this->verifySignature($data)) {
        return $this->failResponse();
    }

    // Check result code
    if ($data['result_code'] === 'SUCCESS') {
        // Update booking status
        $this->updateBookingPaid($data['out_trade_no']);

        // Send confirmation message
        $this->sendBookingConfirmation($data['openid']);
    }

    return $this->successResponse();
}
```

---

## 12. Template Messages

### Booking Confirmation Template
```json
{
  "touser": "OPENID",
  "template_id": "TEMPLATE_ID",
  "page": "pages/bookings/detail",
  "data": {
    "first": {
      "value": "您的预约已确认",
      "color": "#173177"
    },
    "keyword1": {
      "value": "美发服务",
      "color": "#173177"
    },
    "keyword2": {
      "value": "2025年11月13日 14:00",
      "color": "#173177"
    },
    "keyword3": {
      "value": "¥150",
      "color": "#173177"
    },
    "remark": {
      "value": "期待您的光临！",
      "color": "#173177"
    }
  }
}
```

---

## 13. Performance Optimization

### Mini Program Optimization
- Image lazy loading
- Request caching
- Local data storage
- Component reuse
- Code splitting

### Backend Optimization
- API response caching
- Database query optimization
- CDN for static assets
- Async processing
- Queue management

### WeChat API Optimization
- Access token caching (2 hours)
- Batch API calls
- Rate limit compliance
- Retry mechanism

---

## 14. Internationalization

### Language Support
```php
Primary:
- Simplified Chinese (zh_CN)

Optional:
- Traditional Chinese (zh_TW, zh_HK)
- English (en_US) for international users
```

### Chinese Localization
- Chinese calendar support
- CNY currency formatting
- Chinese mobile number validation
- Chinese address format
- Local payment methods
- Chinese holidays

---

## 15. Development Timeline

### Phase 1: Backend Setup (Week 1-2)
- [ ] Database schema
- [ ] WeChat API integration
- [ ] OAuth authentication
- [ ] Payment integration
- [ ] Admin settings

### Phase 2: Mini Program Development (Week 3-6)
- [ ] UI/UX design (Chinese style)
- [ ] Home page
- [ ] Service list
- [ ] Booking flow
- [ ] Payment integration
- [ ] My bookings

### Phase 3: Official Account (Week 7-8)
- [ ] Message handler
- [ ] Template messages
- [ ] Customer service
- [ ] Menu configuration
- [ ] Auto-reply

### Phase 4: Social Features (Week 9)
- [ ] Sharing functionality
- [ ] Referral system
- [ ] Coupon system
- [ ] Review system

### Phase 5: Testing (Week 10-11)
- [ ] Mini Program testing
- [ ] Payment testing
- [ ] Message testing
- [ ] User testing

### Phase 6: Launch (Week 12)
- [ ] WeChat review submission
- [ ] Documentation
- [ ] Training materials
- [ ] Production release

**Total Estimated Timeline:** 12 weeks (3 months)

---

## 16. Maintenance & Support

### Update Schedule
- **Security Updates:** As needed
- **Bug Fixes:** Weekly
- **WeChat API Updates:** As released
- **Feature Updates:** Monthly
- **Festival Updates:** Before Chinese holidays

### Support Channels
- WeChat customer service
- Email support (Chinese)
- Documentation (Chinese/English)
- Video tutorials (Chinese)
- WeChat support group

### Monitoring
- Mini Program analytics
- Payment success rate
- API error rate
- User engagement
- Conversion rate

---

## 17. Dependencies & Requirements

### Required WordPress Plugins
- BookingX Core 2.0+

### WeChat Requirements
- Verified WeChat Official Account
- WeChat Mini Program account
- WeChat Pay merchant account
- ICP filing (for China)
- Chinese business license

### Server Requirements
- PHP 7.4+ (8.0+ recommended)
- MySQL 5.7+
- SSL Certificate (required)
- Server in China (recommended)
- WordPress 5.8+

---

## 18. Success Metrics

### Technical Metrics
- Mini Program load time < 2 seconds
- Payment success rate > 95%
- API error rate < 2%
- Message delivery > 98%

### Business Metrics
- Mini Program MAU (Monthly Active Users)
- Booking conversion rate > 15%
- WeChat Pay adoption > 90%
- Share rate > 10%
- Repeat booking rate

### User Metrics
- User retention (30-day) > 40%
- Average session duration
- Pages per session
- User satisfaction

---

## 19. Known Limitations

1. **Geographic:** Primarily for Chinese market
2. **Account Requirements:** Verified accounts needed
3. **ICP Filing:** Required for China deployment
4. **Payment:** WeChat Pay only (in China)
5. **Approval:** WeChat review required
6. **Customization:** Must follow WeChat guidelines
7. **Data:** Data must be stored in China
8. **Updates:** Subject to WeChat policy changes

---

## 20. Future Enhancements

### Version 2.0 Roadmap
- [ ] WeChat Work integration
- [ ] Mini Program live streaming
- [ ] Video consultation
- [ ] AI chatbot
- [ ] Advanced analytics
- [ ] Multi-location support
- [ ] Franchise management
- [ ] Loyalty program
- [ ] Membership cards
- [ ] Group buying

### Version 3.0 Roadmap
- [ ] WeChat Channels integration
- [ ] AR/VR experiences
- [ ] Gamification
- [ ] Social commerce features
- [ ] Advanced AI recommendations
- [ ] Blockchain-based rewards

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Author:** BookingX Development Team
**Status:** Ready for Development
