# Mobile Booking Optimization Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Mobile Booking Optimization
**Price:** $99
**Category:** Mobile & Progressive Web Apps
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+
**Mobile Browsers:** iOS Safari 14+, Chrome Android 90+

### Description
Mobile-first booking experience with touch-optimized interfaces, swipe gestures, and mobile payment integration. Optimizes the entire booking flow for mobile devices with native-like interactions, gesture controls, and seamless mobile payment options including Apple Pay, Google Pay, and mobile wallet integration.

### Value Proposition
- 40% faster mobile booking completion
- Touch-optimized UI reduces errors by 60%
- Native gesture support improves UX
- One-tap mobile payment options
- Reduced cart abandonment on mobile
- Mobile-first responsive design
- Optimized for thumb navigation
- Progressive enhancement for all devices

---

## 2. Features & Requirements

### Core Features
1. **Touch-Optimized Interface**
   - Large touch targets (minimum 44×44px)
   - Touch feedback (haptic/visual)
   - Swipe-friendly navigation
   - Pinch-to-zoom disabled on forms
   - Proper input types for mobile keyboards
   - Auto-capitalize/auto-correct control
   - Touch-friendly date/time pickers
   - Bottom-aligned action buttons

2. **Gesture-Based Interactions**
   - Swipe to navigate (left/right)
   - Swipe to delete bookings
   - Pull-to-refresh
   - Long-press for quick actions
   - Pinch-to-zoom for calendar
   - Drag-and-drop for scheduling
   - Shake to undo
   - Edge swipe for back navigation

3. **Mobile Payment Integration**
   - Apple Pay integration
   - Google Pay integration
   - Samsung Pay support
   - Mobile wallet APIs
   - One-tap payment
   - Payment Request API
   - Saved payment methods
   - Biometric payment authentication
   - Digital wallet pass generation

4. **Mobile-First Booking Flow**
   - Single-column layout
   - Step-by-step wizard
   - Progress indicators
   - Smart form field ordering
   - Autocomplete and suggestions
   - Inline validation
   - Contextual help
   - Quick booking shortcuts

5. **Performance Optimization**
   - Lazy loading
   - Image optimization (WebP/AVIF)
   - Reduced payload size
   - Debounced searches
   - Optimistic UI updates
   - Skeleton screens
   - Minimal reflows
   - Hardware acceleration

6. **Mobile UX Patterns**
   - Bottom sheets
   - Slide-up panels
   - Sticky headers
   - Floating action buttons
   - Toast notifications
   - Modal dialogs
   - Drawer navigation
   - Card-based layouts

### User Roles & Permissions
- **Customer:** Full mobile booking experience
- **Staff:** Mobile schedule management
- **Manager:** Mobile reporting and oversight
- **Admin:** Configuration and analytics

---

## 3. Technical Specifications

### Technology Stack
- **Frontend Framework:** Vanilla JS or React/Vue with mobile focus
- **Touch Events:** Hammer.js or custom touch handlers
- **Animations:** CSS transitions, GSAP for complex animations
- **Payment SDKs:**
  - Apple Pay JS
  - Google Pay API for Web
  - Payment Request API
- **Form Management:** Mobile-optimized form library
- **Gestures:** Touch events, Pointer events API
- **Testing:** BrowserStack, Sauce Labs for mobile testing

### Dependencies
- BookingX Core 2.0+
- Payment gateway integration (Stripe, PayPal, etc.)
- SSL certificate (required for payment APIs)
- Modern mobile browser with Payment Request API support

### API Integration Points
```javascript
// BookingX Mobile Optimization API
POST   /wp-json/bookingx/v2/mobile/booking-start
POST   /wp-json/bookingx/v2/mobile/booking-validate
POST   /wp-json/bookingx/v2/mobile/booking-complete
GET    /wp-json/bookingx/v2/mobile/quick-booking
POST   /wp-json/bookingx/v2/mobile/payment-request
POST   /wp-json/bookingx/v2/mobile/apple-pay-session
POST   /wp-json/bookingx/v2/mobile/google-pay-token
GET    /wp-json/bookingx/v2/mobile/payment-methods
POST   /wp-json/bookingx/v2/mobile/wallet-pass
GET    /wp-json/bookingx/v2/mobile/analytics
```

---

## 4. Architecture & Design

### Mobile-First Architecture
```
┌─────────────────────────────────────┐
│     Mobile Device (Touch)           │
│  ┌────────────────────────────┐    │
│  │  Touch Event Layer         │    │
│  │  - Tap, Swipe, Long-press  │    │
│  │  - Gesture Recognition     │    │
│  └────────┬───────────────────┘    │
│           │                         │
│  ┌────────▼───────────────────┐    │
│  │  Mobile UI Components      │    │
│  │  - Touch Controls          │    │
│  │  - Mobile Forms            │    │
│  │  - Gesture Handlers        │    │
│  └────────┬───────────────────┘    │
│           │                         │
│  ┌────────▼───────────────────┐    │
│  │  Booking Flow Manager      │    │
│  │  - Step Validation         │    │
│  │  - Progress Tracking       │    │
│  │  - State Management        │    │
│  └────────┬───────────────────┘    │
│           │                         │
│  ┌────────▼───────────────────┐    │
│  │  Mobile Payment Handler    │    │
│  │  - Apple Pay              │    │
│  │  - Google Pay             │    │
│  │  - Payment Request API    │    │
│  └────────┬───────────────────┘    │
└───────────┼─────────────────────────┘
            │ HTTPS
            ▼
┌─────────────────────────────────────┐
│      WordPress Backend              │
│  ┌────────────────────────────┐    │
│  │  BookingX Core             │    │
│  ├────────────────────────────┤    │
│  │  Mobile Optimization       │    │
│  │  - Device Detection        │    │
│  │  - Touch-optimized API     │    │
│  │  - Mobile Payment Gateway  │    │
│  └────────────────────────────┘    │
└─────────────────────────────────────┘
```

### Touch Event Handling
```javascript
class TouchHandler {
  constructor(element) {
    this.element = element;
    this.touchStartX = 0;
    this.touchStartY = 0;
    this.touchEndX = 0;
    this.touchEndY = 0;
    this.minSwipeDistance = 50;

    this.attachListeners();
  }

  attachListeners() {
    this.element.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: true });
    this.element.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: true });
    this.element.addEventListener('touchend', this.handleTouchEnd.bind(this));
  }

  handleTouchStart(e) {
    this.touchStartX = e.changedTouches[0].screenX;
    this.touchStartY = e.changedTouches[0].screenY;
  }

  handleTouchMove(e) {
    this.touchEndX = e.changedTouches[0].screenX;
    this.touchEndY = e.changedTouches[0].screenY;
  }

  handleTouchEnd(e) {
    this.detectSwipe();
  }

  detectSwipe() {
    const deltaX = this.touchEndX - this.touchStartX;
    const deltaY = this.touchEndY - this.touchStartY;

    if (Math.abs(deltaX) > Math.abs(deltaY)) {
      // Horizontal swipe
      if (Math.abs(deltaX) > this.minSwipeDistance) {
        if (deltaX > 0) {
          this.onSwipeRight();
        } else {
          this.onSwipeLeft();
        }
      }
    } else {
      // Vertical swipe
      if (Math.abs(deltaY) > this.minSwipeDistance) {
        if (deltaY > 0) {
          this.onSwipeDown();
        } else {
          this.onSwipeUp();
        }
      }
    }
  }

  onSwipeLeft() { /* Override */ }
  onSwipeRight() { /* Override */ }
  onSwipeUp() { /* Override */ }
  onSwipeDown() { /* Override */ }
}
```

### Mobile Booking Flow State Machine
```javascript
class MobileBookingFlow {
  constructor() {
    this.currentStep = 'service-selection';
    this.bookingData = {};
    this.steps = [
      'service-selection',
      'date-time',
      'customer-info',
      'payment',
      'confirmation'
    ];
  }

  nextStep() {
    const currentIndex = this.steps.indexOf(this.currentStep);
    if (currentIndex < this.steps.length - 1) {
      this.currentStep = this.steps[currentIndex + 1];
      this.renderStep();
      this.updateProgress();
    }
  }

  previousStep() {
    const currentIndex = this.steps.indexOf(this.currentStep);
    if (currentIndex > 0) {
      this.currentStep = this.steps[currentIndex - 1];
      this.renderStep();
      this.updateProgress();
    }
  }

  validateStep(step) {
    const validators = {
      'service-selection': () => !!this.bookingData.serviceId,
      'date-time': () => !!this.bookingData.dateTime,
      'customer-info': () => this.validateCustomerInfo(),
      'payment': () => this.validatePayment(),
    };

    return validators[step] ? validators[step]() : true;
  }

  updateProgress() {
    const progress = ((this.steps.indexOf(this.currentStep) + 1) / this.steps.length) * 100;
    document.querySelector('.progress-bar').style.width = `${progress}%`;
  }
}
```

---

## 5. Database Schema

### Table: `bkx_mobile_sessions`
```sql
CREATE TABLE bkx_mobile_sessions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    user_id BIGINT(20) UNSIGNED,
    device_type VARCHAR(50) NOT NULL,
    device_info TEXT,
    screen_size VARCHAR(50),
    current_step VARCHAR(50),
    booking_data LONGTEXT,
    started_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    completed_at DATETIME,
    abandoned TINYINT(1) DEFAULT 0,
    INDEX session_id_idx (session_id),
    INDEX user_id_idx (user_id),
    INDEX completed_at_idx (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_mobile_payments`
```sql
CREATE TABLE bkx_mobile_payments (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    wallet_type VARCHAR(50),
    transaction_id VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    device_type VARCHAR(50),
    payment_token TEXT,
    status VARCHAR(20) NOT NULL,
    error_message TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX booking_id_idx (booking_id),
    INDEX payment_method_idx (payment_method),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_mobile_analytics`
```sql
CREATE TABLE bkx_mobile_analytics (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data TEXT,
    step VARCHAR(50),
    time_spent INT,
    device_type VARCHAR(50),
    screen_size VARCHAR(50),
    connection_type VARCHAR(20),
    created_at DATETIME NOT NULL,
    INDEX session_id_idx (session_id),
    INDEX event_type_idx (event_type),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Mobile Optimization Settings
```php
[
    // Touch Interface
    'touch_target_size' => 44, // pixels
    'enable_haptic_feedback' => true,
    'enable_swipe_gestures' => true,
    'swipe_threshold' => 50, // pixels
    'enable_pull_to_refresh' => true,

    // Booking Flow
    'mobile_booking_flow' => 'wizard', // wizard, single-page
    'steps_per_screen' => 1,
    'enable_progress_bar' => true,
    'enable_step_navigation' => true,
    'save_progress' => true,
    'progress_expiry' => 3600, // 1 hour

    // Mobile Payments
    'enable_apple_pay' => true,
    'enable_google_pay' => true,
    'enable_samsung_pay' => true,
    'enable_payment_request_api' => true,
    'enable_biometric_payment' => true,
    'quick_payment_threshold' => 100, // amount for express checkout

    // Performance
    'lazy_load_images' => true,
    'image_format' => 'webp', // webp, avif, jpg
    'enable_skeleton_screens' => true,
    'debounce_delay' => 300, // ms
    'enable_optimistic_ui' => true,

    // UX Patterns
    'bottom_sheet_enabled' => true,
    'floating_action_button' => true,
    'sticky_header' => true,
    'enable_toast_notifications' => true,

    // Responsive Breakpoints
    'breakpoints' => [
        'mobile' => 480,
        'tablet' => 768,
        'desktop' => 1024,
    ],

    // Analytics
    'track_mobile_sessions' => true,
    'track_abandonment' => true,
    'track_gestures' => false, // privacy consideration
]
```

---

## 7. User Interface Requirements

### Mobile-Optimized Booking Form

#### Step 1: Service Selection (Mobile)
```html
<div class="mobile-booking-container">
  <!-- Progress Bar -->
  <div class="progress-bar-container">
    <div class="progress-bar" style="width: 20%"></div>
    <span class="progress-text">Step 1 of 5</span>
  </div>

  <!-- Service Cards (Swipeable) -->
  <div class="service-carousel">
    <div class="service-card" data-service-id="1">
      <img src="service1.jpg" alt="Haircut" loading="lazy">
      <div class="service-info">
        <h3>Haircut</h3>
        <p class="duration">45 min</p>
        <p class="price">$30</p>
      </div>
      <button class="btn-select" aria-label="Select Haircut">
        Select
      </button>
    </div>
  </div>

  <!-- Search with Mobile Keyboard -->
  <input
    type="search"
    placeholder="Search services..."
    inputmode="search"
    autocomplete="off"
    autocapitalize="off"
    class="mobile-search"
  >

  <!-- Bottom Action Bar -->
  <div class="bottom-action-bar">
    <button class="btn-primary btn-large" disabled>
      Continue
    </button>
  </div>
</div>
```

#### Step 2: Date & Time Selection (Mobile)
```html
<div class="mobile-booking-container">
  <div class="progress-bar-container">
    <div class="progress-bar" style="width: 40%"></div>
    <span class="progress-text">Step 2 of 5</span>
  </div>

  <!-- Date Selector (Touch-friendly) -->
  <div class="date-selector">
    <div class="date-carousel">
      <div class="date-item" data-date="2025-11-13">
        <span class="day">Wed</span>
        <span class="date">13</span>
        <span class="month">Nov</span>
      </div>
      <!-- More date items... -->
    </div>
  </div>

  <!-- Time Slots (Large Touch Targets) -->
  <div class="time-slots">
    <button class="time-slot" data-time="09:00">
      9:00 AM
    </button>
    <button class="time-slot" data-time="09:30">
      9:30 AM
    </button>
    <!-- More time slots... -->
  </div>

  <!-- Navigation -->
  <div class="bottom-action-bar">
    <button class="btn-secondary" onclick="previousStep()">
      Back
    </button>
    <button class="btn-primary" onclick="nextStep()">
      Continue
    </button>
  </div>
</div>
```

#### Step 3: Customer Information (Mobile-Optimized Form)
```html
<div class="mobile-booking-container">
  <div class="progress-bar-container">
    <div class="progress-bar" style="width: 60%"></div>
    <span class="progress-text">Step 3 of 5</span>
  </div>

  <form class="mobile-form" autocomplete="on">
    <!-- Name -->
    <div class="form-group">
      <label for="name">Full Name</label>
      <input
        type="text"
        id="name"
        name="name"
        autocomplete="name"
        inputmode="text"
        required
        class="form-control-large"
      >
    </div>

    <!-- Email -->
    <div class="form-group">
      <label for="email">Email</label>
      <input
        type="email"
        id="email"
        name="email"
        autocomplete="email"
        inputmode="email"
        required
        class="form-control-large"
      >
    </div>

    <!-- Phone -->
    <div class="form-group">
      <label for="phone">Phone Number</label>
      <input
        type="tel"
        id="phone"
        name="phone"
        autocomplete="tel"
        inputmode="tel"
        placeholder="(555) 123-4567"
        required
        class="form-control-large"
      >
    </div>

    <!-- Notes (Optional) -->
    <div class="form-group">
      <label for="notes">Special Requests (Optional)</label>
      <textarea
        id="notes"
        name="notes"
        rows="3"
        class="form-control-large"
        placeholder="Any special requests..."
      ></textarea>
    </div>
  </form>

  <div class="bottom-action-bar">
    <button class="btn-secondary" onclick="previousStep()">Back</button>
    <button class="btn-primary" onclick="nextStep()">Continue</button>
  </div>
</div>
```

#### Step 4: Mobile Payment
```html
<div class="mobile-booking-container">
  <div class="progress-bar-container">
    <div class="progress-bar" style="width: 80%"></div>
    <span class="progress-text">Step 4 of 5</span>
  </div>

  <!-- Booking Summary -->
  <div class="booking-summary-card">
    <h3>Booking Summary</h3>
    <div class="summary-row">
      <span>Haircut</span>
      <span>$30.00</span>
    </div>
    <div class="summary-row total">
      <span>Total</span>
      <span>$30.00</span>
    </div>
  </div>

  <!-- Quick Payment Options -->
  <div class="payment-options">
    <!-- Apple Pay -->
    <button
      class="payment-button apple-pay"
      onclick="payWithApplePay()"
      style="display: none"
      id="apple-pay-button"
    >
      <span class="apple-pay-logo"></span>
    </button>

    <!-- Google Pay -->
    <button
      class="payment-button google-pay"
      onclick="payWithGooglePay()"
      style="display: none"
      id="google-pay-button"
    >
      <span class="google-pay-logo"></span>
    </button>

    <!-- Payment Request API -->
    <button
      class="payment-button payment-request"
      onclick="payWithPaymentRequest()"
      id="payment-request-button"
    >
      Pay Now
    </button>

    <div class="divider">
      <span>or pay with card</span>
    </div>

    <!-- Traditional Card Form -->
    <div class="card-form-container">
      <!-- Card form here -->
    </div>
  </div>

  <div class="bottom-action-bar">
    <button class="btn-secondary" onclick="previousStep()">Back</button>
  </div>
</div>
```

### Touch-Optimized CSS
```css
/* Mobile-First Styles */
:root {
  --touch-target-size: 44px;
  --spacing-mobile: 16px;
  --border-radius: 12px;
  --animation-duration: 300ms;
}

/* Touch-friendly buttons */
.btn-large {
  min-height: var(--touch-target-size);
  min-width: var(--touch-target-size);
  padding: 12px 24px;
  font-size: 16px;
  border-radius: var(--border-radius);
  transition: all var(--animation-duration);
  -webkit-tap-highlight-color: transparent;
}

/* Active state feedback */
.btn-large:active {
  transform: scale(0.98);
  opacity: 0.8;
}

/* Form controls */
.form-control-large {
  min-height: var(--touch-target-size);
  padding: 12px 16px;
  font-size: 16px; /* Prevents zoom on iOS */
  border-radius: var(--border-radius);
}

/* Bottom action bar (thumb-friendly) */
.bottom-action-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 16px;
  background: white;
  border-top: 1px solid #e5e7eb;
  display: flex;
  gap: 12px;
  z-index: 100;
  /* Safe area for notch devices */
  padding-bottom: calc(16px + env(safe-area-inset-bottom));
}

/* Swipeable card */
.service-card {
  touch-action: pan-y;
  transition: transform var(--animation-duration);
}

.service-card.swiping {
  transition: none;
}

/* Pull-to-refresh */
.ptr-container {
  overflow: hidden;
  position: relative;
}

.ptr-loader {
  position: absolute;
  top: -60px;
  left: 50%;
  transform: translateX(-50%);
  transition: top var(--animation-duration);
}

.ptr-container.pulling .ptr-loader {
  top: 0;
}

/* Mobile keyboard safe area */
@supports (padding: env(safe-area-inset-bottom)) {
  .mobile-form {
    padding-bottom: calc(60px + env(safe-area-inset-bottom));
  }
}
```

---

## 8. Mobile Payment Implementation

### Apple Pay Integration
```javascript
class ApplePayHandler {
  constructor() {
    this.merchantId = 'merchant.com.bookingx';
    this.displayName = 'BookingX';
  }

  async checkAvailability() {
    if (!window.ApplePaySession) {
      return false;
    }

    return ApplePaySession.canMakePayments();
  }

  async createSession(amount, currency = 'USD') {
    const paymentRequest = {
      countryCode: 'US',
      currencyCode: currency,
      merchantCapabilities: ['supports3DS'],
      supportedNetworks: ['visa', 'masterCard', 'amex', 'discover'],
      total: {
        label: this.displayName,
        amount: amount.toFixed(2),
        type: 'final'
      }
    };

    const session = new ApplePaySession(10, paymentRequest);

    session.onvalidatemerchant = async (event) => {
      const merchantSession = await this.validateMerchant(event.validationURL);
      session.completeMerchantValidation(merchantSession);
    };

    session.onpaymentauthorized = async (event) => {
      const result = await this.processPayment(event.payment);
      session.completePayment(result);
    };

    session.begin();
    return session;
  }

  async validateMerchant(validationURL) {
    const response = await fetch('/wp-json/bookingx/v2/mobile/apple-pay-session', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ validationURL })
    });

    return response.json();
  }

  async processPayment(payment) {
    const response = await fetch('/wp-json/bookingx/v2/mobile/process-payment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        paymentMethod: 'apple_pay',
        token: payment.token,
        bookingData: this.bookingData
      })
    });

    const result = await response.json();

    return result.success
      ? ApplePaySession.STATUS_SUCCESS
      : ApplePaySession.STATUS_FAILURE;
  }
}
```

### Google Pay Integration
```javascript
class GooglePayHandler {
  constructor() {
    this.baseRequest = {
      apiVersion: 2,
      apiVersionMinor: 0
    };

    this.tokenizationSpecification = {
      type: 'PAYMENT_GATEWAY',
      parameters: {
        gateway: 'stripe',
        'stripe:version': '2018-10-31',
        'stripe:publishableKey': STRIPE_PUBLIC_KEY
      }
    };

    this.allowedCardNetworks = ['AMEX', 'DISCOVER', 'MASTERCARD', 'VISA'];
    this.allowedCardAuthMethods = ['PAN_ONLY', 'CRYPTOGRAM_3DS'];
  }

  async initialize() {
    this.paymentsClient = new google.payments.api.PaymentsClient({
      environment: 'PRODUCTION' // or 'TEST'
    });
  }

  async isReadyToPay() {
    const isReadyToPayRequest = {
      ...this.baseRequest,
      allowedPaymentMethods: [this.getBaseCardPaymentMethod()]
    };

    const response = await this.paymentsClient.isReadyToPay(isReadyToPayRequest);
    return response.result;
  }

  getBaseCardPaymentMethod() {
    return {
      type: 'CARD',
      parameters: {
        allowedAuthMethods: this.allowedCardAuthMethods,
        allowedCardNetworks: this.allowedCardNetworks
      }
    };
  }

  getCardPaymentMethod() {
    return {
      ...this.getBaseCardPaymentMethod(),
      tokenizationSpecification: this.tokenizationSpecification
    };
  }

  async loadPaymentData(amount, currency = 'USD') {
    const paymentDataRequest = {
      ...this.baseRequest,
      allowedPaymentMethods: [this.getCardPaymentMethod()],
      transactionInfo: {
        totalPriceStatus: 'FINAL',
        totalPrice: amount.toFixed(2),
        currencyCode: currency,
        countryCode: 'US'
      },
      merchantInfo: {
        merchantName: 'BookingX',
        merchantId: GOOGLE_MERCHANT_ID
      }
    };

    const paymentData = await this.paymentsClient.loadPaymentData(paymentDataRequest);
    return this.processPayment(paymentData);
  }

  async processPayment(paymentData) {
    const paymentToken = paymentData.paymentMethodData.tokenizationData.token;

    const response = await fetch('/wp-json/bookingx/v2/mobile/process-payment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        paymentMethod: 'google_pay',
        token: paymentToken,
        bookingData: this.bookingData
      })
    });

    return response.json();
  }
}
```

### Payment Request API
```javascript
class PaymentRequestHandler {
  async checkAvailability() {
    if (!window.PaymentRequest) {
      return false;
    }

    const supportedMethods = [
      {
        supportedMethods: 'basic-card',
        data: {
          supportedNetworks: ['visa', 'mastercard', 'amex'],
          supportedTypes: ['credit', 'debit']
        }
      }
    ];

    const details = {
      total: {
        label: 'Total',
        amount: { currency: 'USD', value: '1.00' }
      }
    };

    try {
      const request = new PaymentRequest(supportedMethods, details);
      const canMakePayment = await request.canMakePayment();
      return canMakePayment;
    } catch (e) {
      return false;
    }
  }

  async createPaymentRequest(bookingData) {
    const supportedMethods = [
      {
        supportedMethods: 'basic-card',
        data: {
          supportedNetworks: ['visa', 'mastercard', 'amex', 'discover'],
          supportedTypes: ['credit', 'debit']
        }
      }
    ];

    const details = {
      total: {
        label: 'BookingX - ' + bookingData.serviceName,
        amount: {
          currency: bookingData.currency || 'USD',
          value: bookingData.amount.toFixed(2)
        }
      },
      displayItems: [
        {
          label: bookingData.serviceName,
          amount: {
            currency: bookingData.currency || 'USD',
            value: bookingData.amount.toFixed(2)
          }
        }
      ]
    };

    const options = {
      requestPayerName: true,
      requestPayerEmail: true,
      requestPayerPhone: true,
      requestShipping: false
    };

    return new PaymentRequest(supportedMethods, details, options);
  }

  async processPayment(bookingData) {
    try {
      const request = await this.createPaymentRequest(bookingData);
      const response = await request.show();

      // Process payment on server
      const result = await this.sendPaymentToServer(response, bookingData);

      if (result.success) {
        await response.complete('success');
        return { success: true, bookingId: result.bookingId };
      } else {
        await response.complete('fail');
        return { success: false, error: result.error };
      }
    } catch (error) {
      return { success: false, error: error.message };
    }
  }

  async sendPaymentToServer(paymentResponse, bookingData) {
    const response = await fetch('/wp-json/bookingx/v2/mobile/process-payment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        paymentMethod: 'payment_request',
        paymentData: {
          methodName: paymentResponse.methodName,
          details: paymentResponse.details
        },
        bookingData
      })
    });

    return response.json();
  }
}
```

---

## 9. Gesture Implementation

### Swipe Gesture Handler
```javascript
class SwipeGestureHandler {
  constructor(options = {}) {
    this.swipeThreshold = options.threshold || 50;
    this.velocityThreshold = options.velocity || 0.3;
    this.restraint = options.restraint || 100;
    this.allowedTime = options.allowedTime || 300;
  }

  attachToElement(element, callbacks = {}) {
    let startX, startY, distX, distY, startTime;

    element.addEventListener('touchstart', (e) => {
      const touchobj = e.changedTouches[0];
      startX = touchobj.pageX;
      startY = touchobj.pageY;
      startTime = new Date().getTime();

      if (callbacks.onStart) {
        callbacks.onStart(e);
      }
    }, { passive: true });

    element.addEventListener('touchmove', (e) => {
      if (callbacks.onMove) {
        const touchobj = e.changedTouches[0];
        const currentX = touchobj.pageX;
        const deltaX = currentX - startX;
        callbacks.onMove(deltaX, e);
      }
    }, { passive: true });

    element.addEventListener('touchend', (e) => {
      const touchobj = e.changedTouches[0];
      distX = touchobj.pageX - startX;
      distY = touchobj.pageY - startY;
      const elapsedTime = new Date().getTime() - startTime;

      // Check if swipe meets requirements
      if (elapsedTime <= this.allowedTime) {
        if (Math.abs(distX) >= this.swipeThreshold && Math.abs(distY) <= this.restraint) {
          const direction = distX < 0 ? 'left' : 'right';

          if (callbacks.onSwipe) {
            callbacks.onSwipe(direction, e);
          }

          if (direction === 'left' && callbacks.onSwipeLeft) {
            callbacks.onSwipeLeft(e);
          } else if (direction === 'right' && callbacks.onSwipeRight) {
            callbacks.onSwipeRight(e);
          }
        }
      }

      if (callbacks.onEnd) {
        callbacks.onEnd(e);
      }
    });
  }
}

// Usage example
const swipeHandler = new SwipeGestureHandler({
  threshold: 75,
  velocity: 0.5
});

swipeHandler.attachToElement(document.querySelector('.booking-card'), {
  onSwipeLeft: () => {
    // Delete booking
    showDeleteConfirmation();
  },
  onSwipeRight: () => {
    // View details
    showBookingDetails();
  },
  onMove: (deltaX) => {
    // Visual feedback during swipe
    element.style.transform = `translateX(${deltaX}px)`;
  },
  onEnd: () => {
    // Reset position
    element.style.transform = 'translateX(0)';
  }
});
```

### Pull-to-Refresh
```javascript
class PullToRefresh {
  constructor(container, onRefresh) {
    this.container = container;
    this.onRefresh = onRefresh;
    this.startY = 0;
    this.currentY = 0;
    this.dragging = false;
    this.threshold = 80;

    this.createLoader();
    this.attachListeners();
  }

  createLoader() {
    this.loader = document.createElement('div');
    this.loader.className = 'ptr-loader';
    this.loader.innerHTML = `
      <svg class="ptr-spinner" viewBox="0 0 50 50">
        <circle cx="25" cy="25" r="20" fill="none" stroke="#4f46e5" stroke-width="4"/>
      </svg>
    `;
    this.container.insertBefore(this.loader, this.container.firstChild);
  }

  attachListeners() {
    this.container.addEventListener('touchstart', (e) => {
      if (this.container.scrollTop === 0) {
        this.startY = e.touches[0].pageY;
        this.dragging = true;
      }
    }, { passive: true });

    this.container.addEventListener('touchmove', (e) => {
      if (!this.dragging) return;

      this.currentY = e.touches[0].pageY;
      const diff = this.currentY - this.startY;

      if (diff > 0) {
        e.preventDefault();
        const distance = Math.min(diff * 0.5, this.threshold);
        this.container.style.transform = `translateY(${distance}px)`;
        this.loader.style.opacity = distance / this.threshold;

        if (distance >= this.threshold) {
          this.loader.classList.add('ready');
        }
      }
    });

    this.container.addEventListener('touchend', async () => {
      if (!this.dragging) return;

      this.dragging = false;
      const diff = this.currentY - this.startY;

      if (diff >= this.threshold) {
        // Trigger refresh
        this.loader.classList.add('refreshing');
        await this.onRefresh();
        this.loader.classList.remove('refreshing', 'ready');
      }

      // Reset
      this.container.style.transform = '';
      this.loader.style.opacity = '0';
    });
  }
}

// Usage
const ptr = new PullToRefresh(
  document.querySelector('.bookings-list'),
  async () => {
    await fetchLatestBookings();
  }
);
```

---

## 10. Performance Optimization

### Image Optimization
```javascript
class ImageOptimizer {
  static getSrcSet(imagePath, sizes = [480, 768, 1200]) {
    return sizes
      .map(size => `${imagePath}?w=${size} ${size}w`)
      .join(', ');
  }

  static lazyLoad(selector = 'img[loading="lazy"]') {
    if ('loading' in HTMLImageElement.prototype) {
      // Native lazy loading supported
      return;
    }

    // Fallback for browsers without native support
    const images = document.querySelectorAll(selector);
    const imageObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const img = entry.target;
          img.src = img.dataset.src;
          imageObserver.unobserve(img);
        }
      });
    });

    images.forEach((img) => imageObserver.observe(img));
  }

  static convertToWebP(imagePath) {
    // Check WebP support
    if (this.supportsWebP()) {
      return imagePath.replace(/\\.(jpg|jpeg|png)$/, '.webp');
    }
    return imagePath;
  }

  static supportsWebP() {
    const canvas = document.createElement('canvas');
    return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
  }
}
```

### Debouncing for Search
```javascript
class Debouncer {
  static debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
}

// Usage
const searchInput = document.querySelector('#service-search');
const debouncedSearch = Debouncer.debounce(async (query) => {
  const results = await searchServices(query);
  displayResults(results);
}, 300);

searchInput.addEventListener('input', (e) => {
  debouncedSearch(e.target.value);
});
```

### Skeleton Screens
```html
<!-- Loading skeleton -->
<div class="service-card skeleton">
  <div class="skeleton-image"></div>
  <div class="skeleton-content">
    <div class="skeleton-title"></div>
    <div class="skeleton-text"></div>
    <div class="skeleton-text short"></div>
  </div>
</div>

<style>
.skeleton {
  pointer-events: none;
}

.skeleton > * {
  background: linear-gradient(
    90deg,
    #f0f0f0 25%,
    #e0e0e0 50%,
    #f0f0f0 75%
  );
  background-size: 200% 100%;
  animation: skeleton-loading 1.5s ease-in-out infinite;
}

@keyframes skeleton-loading {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.skeleton-image {
  width: 100%;
  height: 200px;
  border-radius: 8px;
}

.skeleton-title {
  width: 70%;
  height: 24px;
  margin: 12px 0;
  border-radius: 4px;
}

.skeleton-text {
  width: 100%;
  height: 16px;
  margin: 8px 0;
  border-radius: 4px;
}

.skeleton-text.short {
  width: 40%;
}
</style>
```

---

## 11. Testing Strategy

### Mobile Device Testing
```javascript
// Responsive breakpoint tests
describe('Mobile Responsive Design', () => {
  const breakpoints = {
    mobile: { width: 375, height: 667 },
    tablet: { width: 768, height: 1024 },
    desktop: { width: 1440, height: 900 }
  };

  Object.entries(breakpoints).forEach(([device, dimensions]) => {
    test(`should display correctly on ${device}`, async () => {
      await page.setViewport(dimensions);
      await page.goto('http://localhost:3000/booking');

      const screenshot = await page.screenshot();
      expect(screenshot).toMatchImageSnapshot();
    });
  });
});

// Touch interaction tests
describe('Touch Interactions', () => {
  test('should handle swipe gestures', async () => {
    const card = await page.$('.booking-card');

    await card.touchStart({ x: 200, y: 100 });
    await card.touchMove({ x: 50, y: 100 });
    await card.touchEnd();

    const isDeleted = await page.evaluate(() => {
      return document.querySelector('.booking-card').classList.contains('deleted');
    });

    expect(isDeleted).toBe(true);
  });

  test('should show touch feedback', async () => {
    const button = await page.$('.btn-primary');

    await button.touchStart();
    const hasActiveClass = await button.evaluate(el => el.classList.contains('active'));

    expect(hasActiveClass).toBe(true);
  });
});
```

### Payment Testing
```javascript
describe('Mobile Payment Integration', () => {
  test('Apple Pay button should be visible on iOS', async () => {
    // Mock iOS user agent
    await page.setUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)...');

    await page.goto('http://localhost:3000/booking/payment');

    const isVisible = await page.evaluate(() => {
      const button = document.querySelector('#apple-pay-button');
      return button && window.getComputedStyle(button).display !== 'none';
    });

    expect(isVisible).toBe(true);
  });

  test('Google Pay should process payment', async () => {
    const mockPaymentData = {
      // Mock payment data
    };

    // Test payment processing
    const result = await processGooglePayPayment(mockPaymentData);
    expect(result.success).toBe(true);
  });
});
```

---

## 12. Development Timeline

### Phase 1: Touch Interface (Weeks 1-2)
- [ ] Implement touch-friendly UI components
- [ ] Create mobile-optimized forms
- [ ] Add haptic feedback
- [ ] Implement bottom action bars
- [ ] Touch target optimization

### Phase 2: Gesture Support (Week 3)
- [ ] Swipe gesture handler
- [ ] Pull-to-refresh
- [ ] Long-press actions
- [ ] Pinch-to-zoom calendar
- [ ] Edge swipe navigation

### Phase 3: Mobile Booking Flow (Weeks 4-5)
- [ ] Step-by-step wizard
- [ ] Progress tracking
- [ ] Form validation
- [ ] Session management
- [ ] Auto-save functionality

### Phase 4: Mobile Payments (Weeks 6-7)
- [ ] Apple Pay integration
- [ ] Google Pay integration
- [ ] Payment Request API
- [ ] Biometric authentication
- [ ] Payment error handling

### Phase 5: Performance (Week 8)
- [ ] Image optimization
- [ ] Lazy loading
- [ ] Code splitting
- [ ] Debouncing
- [ ] Skeleton screens

### Phase 6: Testing & QA (Week 9)
- [ ] Device testing
- [ ] Payment testing
- [ ] Gesture testing
- [ ] Performance testing
- [ ] Accessibility testing

### Phase 7: Launch (Week 10)
- [ ] Documentation
- [ ] Admin configuration
- [ ] Beta testing
- [ ] Production deployment
- [ ] Monitoring setup

**Total Estimated Timeline:** 10 weeks (2.5 months)

---

## 13. Success Metrics

### Technical Metrics
- Mobile booking completion rate > 70%
- Average booking time < 2 minutes
- Touch target compliance: 100%
- Mobile page load < 2 seconds
- Payment success rate > 98%

### Business Metrics
- Mobile conversion rate increase: +40%
- Cart abandonment decrease: -30%
- Mobile payment adoption: > 60%
- Customer satisfaction: > 4.5/5
- Mobile bookings vs total: > 60%

---

## 14. Known Limitations

1. **iOS Safari Limitations**
   - No Vibration API support
   - Limited gesture customization
   - Payment Request API partial support

2. **Payment Method Availability**
   - Apple Pay: iOS/macOS only
   - Google Pay: Limited on iOS
   - Regional availability varies

3. **Gesture Conflicts**
   - Browser native gestures may conflict
   - Edge swipe varies by device
   - Accessibility considerations

---

## 15. Future Enhancements

### Version 2.0 Roadmap
- [ ] Voice booking commands
- [ ] AR service preview
- [ ] Smart booking suggestions (AI)
- [ ] Wearable device support (Watch)
- [ ] Advanced biometric options
- [ ] Cryptocurrency payment support
- [ ] QR code quick booking
- [ ] NFC check-in support

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development
