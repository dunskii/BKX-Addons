# Fitness & Sports Management Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Fitness & Sports Management
**Price:** $129
**Category:** Industry-Specific Solutions
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Comprehensive fitness and sports facility management solution designed for gyms, fitness studios, yoga centers, sports clubs, and personal training businesses. Features class capacity management, membership integration, equipment booking, fitness tracking, workout plans, body measurement tracking, and specialized tools for group classes and personal training sessions.

### Value Proposition
- Dynamic class capacity management with waitlists
- Seamless membership and payment integration
- Equipment and facility resource booking
- Client fitness tracking and progress monitoring
- Personalized workout plan management
- Body measurement and goal tracking
- Instructor schedule optimization
- Member check-in and attendance tracking
- Challenge and competition management
- Nutritional guidance integration
- Video workout library
- Member mobile app compatibility

---

## 2. Features & Requirements

### Core Features

1. **Class Capacity Management**
   - Dynamic capacity limits per class
   - Real-time availability display
   - Automatic waitlist management
   - Spot release notifications
   - Multi-class booking restrictions
   - Class cancellation policies
   - No-show tracking and penalties
   - Class popularity analytics
   - Capacity utilization reporting
   - Instructor substitution management

2. **Membership Integration**
   - Flexible membership tiers
   - Recurring payment automation
   - Membership access rules
   - Class credit allocation
   - Drop-in and punch card support
   - Membership freeze functionality
   - Auto-renewal management
   - Family and corporate memberships
   - Guest pass management
   - Membership upgrade/downgrade paths

3. **Equipment & Facility Booking**
   - Equipment reservation system
   - Court/field/lane booking
   - Personal training room scheduling
   - Equipment maintenance tracking
   - Usage time limits
   - Equipment availability calendar
   - Damage reporting system
   - Cleaning schedule integration
   - Equipment replacement tracking
   - Multi-resource bundling

4. **Fitness Tracking & Progress**
   - Body measurement tracking
   - Weight and body composition
   - Progress photo documentation
   - Fitness assessment records
   - Goal setting and tracking
   - Achievement milestones
   - Performance metrics
   - Strength and cardio progress
   - Flexibility measurements
   - Personal records (PR) tracking

5. **Workout Plan Management**
   - Customized workout plans
   - Exercise library integration
   - Progressive overload planning
   - Plan templates and variations
   - Video exercise demonstrations
   - Rep and set tracking
   - Rest period management
   - Plan assignment to members
   - Workout completion tracking
   - Plan effectiveness analytics

6. **Personal Training Sessions**
   - One-on-one session booking
   - Semi-private training options
   - Trainer availability management
   - Session package management
   - Session notes and feedback
   - Client progress reviews
   - Training goal documentation
   - Trainer client matching
   - Session video analysis
   - Virtual training sessions

7. **Member Check-In System**
   - QR code/barcode check-in
   - Facial recognition integration
   - Attendance tracking
   - Facility access control
   - Peak time analytics
   - Member engagement scoring
   - Automated attendance rewards
   - No-show tracking
   - Check-in history reporting
   - Access restriction management

8. **Challenges & Competitions**
   - Fitness challenge creation
   - Team and individual competitions
   - Leaderboard management
   - Challenge progress tracking
   - Prize and reward management
   - Social sharing features
   - Challenge templates
   - Automated winner selection
   - Participation tracking
   - Challenge analytics

9. **Nutritional Guidance**
   - Meal plan templates
   - Nutrition tracking integration
   - Dietary preference management
   - Supplement recommendations
   - Calorie and macro tracking
   - Nutritionist consultation booking
   - Recipe library
   - Shopping list generation
   - Progress correlation with diet
   - Integration with nutrition apps

10. **Group Class Features**
    - Class schedule management
    - Instructor assignment
    - Class level designation
    - Equipment requirements listing
    - Class description and photos
    - Recurring class series
    - Drop-in rate configuration
    - Class review and ratings
    - Virtual class streaming
    - Hybrid class management

11. **Health & Safety Compliance**
    - Health screening questionnaires
    - Liability waiver management
    - Emergency contact information
    - Medical condition documentation
    - COVID-19 protocols
    - Equipment sanitization logs
    - Incident reporting
    - First aid certification tracking
    - Safety inspection checklists
    - AED and emergency equipment tracking

12. **Member Engagement Tools**
    - Achievement badges
    - Progress milestones
    - Social feed and community
    - Member referral program
    - Birthday rewards
    - Workout streak tracking
    - Member spotlight features
    - Event management
    - Newsletter integration
    - Push notification system

### User Roles & Permissions

- **Gym Owner/Manager:** Full system access, reporting, financial management
- **Fitness Director:** Class scheduling, instructor management, program oversight
- **Instructor/Trainer:** Class roster, member notes, schedule management
- **Front Desk:** Check-in, class booking, membership sales, equipment booking
- **Nutritionist:** Meal plans, nutrition consultations, dietary guidance
- **Maintenance:** Equipment status, facility booking, cleaning schedules
- **Member:** Class booking, fitness tracking, workout plans, profile management

---

## 3. Technical Specifications

### Technology Stack
- **Frontend:** React for member portal and real-time capacity updates
- **Backend:** WordPress REST API with WebSocket for live updates
- **Check-In System:** QR/Barcode generation and scanning (HTML5)
- **Video Streaming:** WebRTC or third-party integration (Zoom, Vimeo)
- **Payment Processing:** Stripe Subscriptions, PayPal recurring
- **Mobile App:** React Native or PWA
- **Fitness Tracking:** Integration with Apple Health, Google Fit, Fitbit
- **Calendar:** FullCalendar.js with drag-and-drop
- **Analytics:** Chart.js and D3.js for fitness metrics

### Dependencies
- BookingX Core 2.0+
- WooCommerce Subscriptions (for memberships)
- PHP GD or Imagick extension (for progress photos)
- WebSocket server (Node.js or Pusher)
- SSL certificate (required)
- Optional: Stripe Subscriptions
- Optional: Zoom API (for virtual classes)
- Optional: Twilio (for SMS notifications)

### API Integration Points
```php
// Class Management API
GET    /wp-json/bookingx/v1/fitness/classes
GET    /wp-json/bookingx/v1/fitness/classes/{id}
POST   /wp-json/bookingx/v1/fitness/classes/{id}/book
DELETE /wp-json/bookingx/v1/fitness/classes/{id}/cancel
GET    /wp-json/bookingx/v1/fitness/classes/{id}/capacity
POST   /wp-json/bookingx/v1/fitness/classes/{id}/waitlist

// Membership API
GET    /wp-json/bookingx/v1/fitness/memberships/{member_id}
POST   /wp-json/bookingx/v1/fitness/memberships/subscribe
PUT    /wp-json/bookingx/v1/fitness/memberships/{id}/freeze
PUT    /wp-json/bookingx/v1/fitness/memberships/{id}/upgrade
GET    /wp-json/bookingx/v1/fitness/memberships/{id}/credits

// Equipment Booking API
GET    /wp-json/bookingx/v1/fitness/equipment/availability
POST   /wp-json/bookingx/v1/fitness/equipment/book
GET    /wp-json/bookingx/v1/fitness/equipment/{id}/schedule
PUT    /wp-json/bookingx/v1/fitness/equipment/{id}/maintenance

// Fitness Tracking API
GET    /wp-json/bookingx/v1/fitness/tracking/{member_id}
POST   /wp-json/bookingx/v1/fitness/tracking/measurement
GET    /wp-json/bookingx/v1/fitness/tracking/{member_id}/progress
POST   /wp-json/bookingx/v1/fitness/tracking/photo
GET    /wp-json/bookingx/v1/fitness/tracking/{member_id}/goals

// Workout Plans API
GET    /wp-json/bookingx/v1/fitness/workouts/{member_id}
POST   /wp-json/bookingx/v1/fitness/workouts/create
PUT    /wp-json/bookingx/v1/fitness/workouts/{id}
POST   /wp-json/bookingx/v1/fitness/workouts/{id}/log
GET    /wp-json/bookingx/v1/fitness/workouts/templates

// Check-In API
POST   /wp-json/bookingx/v1/fitness/checkin
GET    /wp-json/bookingx/v1/fitness/checkin/history/{member_id}
GET    /wp-json/bookingx/v1/fitness/checkin/current
POST   /wp-json/bookingx/v1/fitness/checkin/qrcode/{member_id}

// Challenge API
GET    /wp-json/bookingx/v1/fitness/challenges
POST   /wp-json/bookingx/v1/fitness/challenges/join/{id}
GET    /wp-json/bookingx/v1/fitness/challenges/{id}/leaderboard
POST   /wp-json/bookingx/v1/fitness/challenges/{id}/progress

// Nutrition API
GET    /wp-json/bookingx/v1/fitness/nutrition/{member_id}/plans
POST   /wp-json/bookingx/v1/fitness/nutrition/meal-plan
GET    /wp-json/bookingx/v1/fitness/nutrition/recipes
POST   /wp-json/bookingx/v1/fitness/nutrition/tracking

// Personal Training API
GET    /wp-json/bookingx/v1/fitness/training/sessions/{member_id}
POST   /wp-json/bookingx/v1/fitness/training/book
PUT    /wp-json/bookingx/v1/fitness/training/{id}/notes
GET    /wp-json/bookingx/v1/fitness/training/packages
```

---

## 4. Architecture & Design

### System Architecture
```
┌──────────────────────────────────┐
│   Member Portal & Mobile App     │
│  - Class Booking                 │
│  - Fitness Tracking              │
│  - Workout Plans                 │
└───────────┬──────────────────────┘
            │
            ▼
┌──────────────────────────────────┐
│   BookingX Fitness Core          │
│  - Class Management              │
│  - Membership Engine             │
│  - Check-In System               │
└───────────┬──────────────────────┘
            │
            ├────────────┬───────────┬──────────────┬────────────────┐
            ▼            ▼           ▼              ▼                ▼
┌─────────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────┐
│ Equipment   │  │ Fitness  │  │ Workout  │  │Challenge │  │ Nutrition    │
│ Booking     │  │ Tracking │  │ Plans    │  │ Manager  │  │ Manager      │
└─────────────┘  └──────────┘  └──────────┘  └──────────┘  └──────────────┘
```

### Data Flow: Class Booking with Capacity
```
1. Member views class schedule → Real-time capacity display
2. Member selects class → Capacity check → Available/Waitlist
3. If available → Book → Credits deducted → Confirmation
4. If full → Join waitlist → Position assigned → Notification enabled
5. If spot opens → Waitlist notification → Auto-book option
6. Check-in time → QR scan → Attendance marked
7. No-show detection → Credit refund (policy based)
8. Post-class → Workout logged → Progress updated
```

### Class Structure
```php
namespace BookingX\Addons\Fitness;

class ClassManager {
    - create_class()
    - update_capacity()
    - check_availability()
    - manage_waitlist()
    - process_booking()
    - cancel_booking()
    - handle_no_show()
    - substitute_instructor()
    - duplicate_recurring_class()
}

class CapacityManager {
    - get_current_capacity()
    - calculate_available_spots()
    - add_to_waitlist()
    - promote_from_waitlist()
    - notify_spot_available()
    - enforce_booking_limits()
    - track_utilization()
}

class MembershipManager {
    - create_membership()
    - process_subscription()
    - allocate_credits()
    - deduct_credits()
    - freeze_membership()
    - unfreeze_membership()
    - upgrade_membership()
    - downgrade_membership()
    - cancel_membership()
    - manage_guest_passes()
}

class EquipmentManager {
    - add_equipment()
    - book_equipment()
    - check_availability()
    - track_usage()
    - schedule_maintenance()
    - report_damage()
    - calculate_utilization()
    - manage_cleaning_schedule()
}

class FitnessTracker {
    - log_measurement()
    - upload_progress_photo()
    - set_goal()
    - track_progress()
    - calculate_metrics()
    - generate_progress_report()
    - track_personal_records()
    - compare_periods()
}

class WorkoutPlanManager {
    - create_plan()
    - assign_plan()
    - log_workout()
    - track_completion()
    - adjust_plan()
    - generate_template()
    - recommend_exercises()
    - calculate_volume()
}

class CheckInSystem {
    - generate_qr_code()
    - process_checkin()
    - verify_membership()
    - record_attendance()
    - track_facility_usage()
    - calculate_engagement_score()
    - enforce_access_rules()
}

class ChallengeManager {
    - create_challenge()
    - enroll_participant()
    - track_progress()
    - update_leaderboard()
    - determine_winners()
    - distribute_prizes()
    - generate_challenge_report()
}

class NutritionManager {
    - create_meal_plan()
    - track_nutrition()
    - recommend_meals()
    - calculate_macros()
    - generate_shopping_list()
    - log_meals()
    - correlate_with_fitness()
}

class TrainingSessionManager {
    - book_pt_session()
    - assign_trainer()
    - track_session_notes()
    - manage_packages()
    - schedule_assessments()
    - track_trainer_availability()
    - conduct_virtual_session()
}

class ComplianceManager {
    - collect_health_screening()
    - manage_waivers()
    - store_emergency_contacts()
    - track_medical_conditions()
    - log_incidents()
    - manage_certifications()
    - enforce_safety_protocols()
}

class EngagementManager {
    - award_badges()
    - track_milestones()
    - manage_referrals()
    - send_notifications()
    - create_social_posts()
    - celebrate_achievements()
    - calculate_engagement_score()
}

class ReportingEngine {
    - class_attendance_report()
    - membership_revenue_report()
    - equipment_utilization_report()
    - member_retention_report()
    - trainer_performance_report()
    - peak_time_analysis()
    - member_progress_report()
}
```

---

## 5. Database Schema

### Table: `bkx_fitness_classes`
```sql
CREATE TABLE bkx_fitness_classes (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL,
    class_type VARCHAR(100),
    class_level VARCHAR(50),
    description TEXT,
    instructor_id BIGINT(20) UNSIGNED NOT NULL,
    location VARCHAR(100),
    room_facility VARCHAR(100),
    capacity INT NOT NULL,
    current_bookings INT DEFAULT 0,
    waitlist_enabled TINYINT(1) DEFAULT 1,
    waitlist_count INT DEFAULT 0,
    duration INT NOT NULL,
    equipment_required TEXT,
    class_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    recurring TINYINT(1) DEFAULT 0,
    recurring_pattern VARCHAR(100),
    drop_in_price DECIMAL(10,2),
    credit_cost INT DEFAULT 1,
    is_virtual TINYINT(1) DEFAULT 0,
    virtual_link VARCHAR(500),
    status VARCHAR(20) DEFAULT 'scheduled',
    cancellation_deadline INT DEFAULT 2,
    no_show_penalty DECIMAL(10,2),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX instructor_id_idx (instructor_id),
    INDEX class_date_idx (class_date),
    INDEX class_type_idx (class_type),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_class_bookings`
```sql
CREATE TABLE bkx_fitness_class_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id BIGINT(20) UNSIGNED NOT NULL,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    booking_type VARCHAR(20) NOT NULL DEFAULT 'confirmed',
    waitlist_position INT,
    booking_date DATETIME NOT NULL,
    credits_used INT DEFAULT 1,
    drop_in_payment DECIMAL(10,2),
    checked_in TINYINT(1) DEFAULT 0,
    checkin_time DATETIME,
    no_show TINYINT(1) DEFAULT 0,
    cancelled TINYINT(1) DEFAULT 0,
    cancellation_date DATETIME,
    cancellation_reason TEXT,
    notes TEXT,
    INDEX class_id_idx (class_id),
    INDEX member_id_idx (member_id),
    INDEX booking_type_idx (booking_type),
    INDEX checked_in_idx (checked_in),
    FOREIGN KEY (class_id) REFERENCES bkx_fitness_classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_memberships`
```sql
CREATE TABLE bkx_fitness_memberships (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    membership_type VARCHAR(100) NOT NULL,
    subscription_id VARCHAR(100),
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    start_date DATE NOT NULL,
    end_date DATE,
    billing_cycle VARCHAR(20),
    monthly_price DECIMAL(10,2),
    monthly_credits INT,
    credits_remaining INT,
    credits_rollover TINYINT(1) DEFAULT 0,
    max_rollover_credits INT,
    class_access TEXT,
    guest_passes INT DEFAULT 0,
    guest_passes_used INT DEFAULT 0,
    frozen TINYINT(1) DEFAULT 0,
    freeze_start_date DATE,
    freeze_end_date DATE,
    freeze_days_used INT DEFAULT 0,
    max_freeze_days INT DEFAULT 30,
    auto_renew TINYINT(1) DEFAULT 1,
    last_payment_date DATE,
    next_billing_date DATE,
    cancellation_date DATE,
    cancellation_reason TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX member_id_idx (member_id),
    INDEX membership_type_idx (membership_type),
    INDEX status_idx (status),
    INDEX next_billing_date_idx (next_billing_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_equipment`
```sql
CREATE TABLE bkx_fitness_equipment (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_name VARCHAR(255) NOT NULL,
    equipment_type VARCHAR(100),
    equipment_code VARCHAR(50) UNIQUE,
    location VARCHAR(100),
    capacity INT DEFAULT 1,
    bookable TINYINT(1) DEFAULT 1,
    booking_duration INT DEFAULT 60,
    max_booking_duration INT DEFAULT 120,
    booking_price DECIMAL(10,2),
    requires_certification TINYINT(1) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'available',
    maintenance_schedule VARCHAR(100),
    last_maintenance_date DATE,
    next_maintenance_date DATE,
    purchase_date DATE,
    warranty_expiry DATE,
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX equipment_type_idx (equipment_type),
    INDEX status_idx (status),
    INDEX bookable_idx (bookable)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_equipment_bookings`
```sql
CREATE TABLE bkx_fitness_equipment_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id BIGINT(20) UNSIGNED NOT NULL,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration INT NOT NULL,
    status VARCHAR(20) DEFAULT 'confirmed',
    payment_amount DECIMAL(10,2),
    checked_in TINYINT(1) DEFAULT 0,
    checked_out TINYINT(1) DEFAULT 0,
    notes TEXT,
    created_at DATETIME NOT NULL,
    INDEX equipment_id_idx (equipment_id),
    INDEX member_id_idx (member_id),
    INDEX booking_date_idx (booking_date),
    FOREIGN KEY (equipment_id) REFERENCES bkx_fitness_equipment(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_tracking`
```sql
CREATE TABLE bkx_fitness_tracking (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    tracking_date DATE NOT NULL,
    weight DECIMAL(5,2),
    weight_unit VARCHAR(10) DEFAULT 'kg',
    body_fat_percentage DECIMAL(5,2),
    muscle_mass DECIMAL(5,2),
    bmi DECIMAL(4,2),
    chest_measurement DECIMAL(5,2),
    waist_measurement DECIMAL(5,2),
    hips_measurement DECIMAL(5,2),
    arms_measurement DECIMAL(5,2),
    thighs_measurement DECIMAL(5,2),
    measurement_unit VARCHAR(10) DEFAULT 'cm',
    resting_heart_rate INT,
    blood_pressure VARCHAR(20),
    vo2_max DECIMAL(5,2),
    flexibility_score INT,
    notes TEXT,
    recorded_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    INDEX member_id_idx (member_id),
    INDEX tracking_date_idx (tracking_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_progress_photos`
```sql
CREATE TABLE bkx_fitness_progress_photos (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    tracking_id BIGINT(20) UNSIGNED,
    attachment_id BIGINT(20) UNSIGNED NOT NULL,
    photo_type VARCHAR(20) NOT NULL,
    photo_url VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(500),
    photo_date DATE NOT NULL,
    visibility VARCHAR(20) DEFAULT 'private',
    notes TEXT,
    created_at DATETIME NOT NULL,
    INDEX member_id_idx (member_id),
    INDEX tracking_id_idx (tracking_id),
    INDEX photo_date_idx (photo_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_goals`
```sql
CREATE TABLE bkx_fitness_goals (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    goal_type VARCHAR(100) NOT NULL,
    goal_description TEXT NOT NULL,
    target_value DECIMAL(10,2),
    current_value DECIMAL(10,2),
    unit VARCHAR(20),
    start_date DATE NOT NULL,
    target_date DATE NOT NULL,
    achieved TINYINT(1) DEFAULT 0,
    achieved_date DATE,
    progress_percentage DECIMAL(5,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX member_id_idx (member_id),
    INDEX goal_type_idx (goal_type),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_workout_plans`
```sql
CREATE TABLE bkx_fitness_workout_plans (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(255) NOT NULL,
    member_id BIGINT(20) UNSIGNED,
    trainer_id BIGINT(20) UNSIGNED,
    plan_type VARCHAR(50),
    difficulty_level VARCHAR(20),
    duration_weeks INT,
    workouts_per_week INT,
    description TEXT,
    exercises TEXT,
    is_template TINYINT(1) DEFAULT 0,
    start_date DATE,
    end_date DATE,
    status VARCHAR(20) DEFAULT 'active',
    completion_percentage DECIMAL(5,2) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX member_id_idx (member_id),
    INDEX trainer_id_idx (trainer_id),
    INDEX is_template_idx (is_template)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_workout_logs`
```sql
CREATE TABLE bkx_fitness_workout_logs (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    workout_plan_id BIGINT(20) UNSIGNED,
    workout_date DATE NOT NULL,
    workout_type VARCHAR(100),
    duration INT,
    exercises_completed TEXT,
    sets_reps_details TEXT,
    weight_used TEXT,
    calories_burned INT,
    heart_rate_avg INT,
    heart_rate_max INT,
    difficulty_rating TINYINT(1),
    energy_level TINYINT(1),
    notes TEXT,
    created_at DATETIME NOT NULL,
    INDEX member_id_idx (member_id),
    INDEX workout_plan_id_idx (workout_plan_id),
    INDEX workout_date_idx (workout_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_checkins`
```sql
CREATE TABLE bkx_fitness_checkins (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    checkin_datetime DATETIME NOT NULL,
    checkout_datetime DATETIME,
    checkin_method VARCHAR(20),
    location VARCHAR(100),
    class_id BIGINT(20) UNSIGNED,
    equipment_booking_id BIGINT(20) UNSIGNED,
    duration INT,
    notes TEXT,
    INDEX member_id_idx (member_id),
    INDEX checkin_datetime_idx (checkin_datetime),
    INDEX class_id_idx (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_challenges`
```sql
CREATE TABLE bkx_fitness_challenges (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    challenge_name VARCHAR(255) NOT NULL,
    challenge_type VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    goal_metric VARCHAR(100),
    goal_value DECIMAL(10,2),
    participation_type VARCHAR(20) DEFAULT 'individual',
    entry_fee DECIMAL(10,2) DEFAULT 0,
    prize_description TEXT,
    max_participants INT,
    current_participants INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'upcoming',
    rules TEXT,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX challenge_type_idx (challenge_type),
    INDEX start_date_idx (start_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_challenge_participants`
```sql
CREATE TABLE bkx_fitness_challenge_participants (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    challenge_id BIGINT(20) UNSIGNED NOT NULL,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    team_name VARCHAR(100),
    join_date DATETIME NOT NULL,
    current_progress DECIMAL(10,2) DEFAULT 0,
    completion_percentage DECIMAL(5,2) DEFAULT 0,
    rank INT,
    completed TINYINT(1) DEFAULT 0,
    completion_date DATETIME,
    prize_won TINYINT(1) DEFAULT 0,
    notes TEXT,
    INDEX challenge_id_idx (challenge_id),
    INDEX member_id_idx (member_id),
    INDEX rank_idx (rank),
    FOREIGN KEY (challenge_id) REFERENCES bkx_fitness_challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_fitness_training_sessions`
```sql
CREATE TABLE bkx_fitness_training_sessions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id BIGINT(20) UNSIGNED NOT NULL,
    trainer_id BIGINT(20) UNSIGNED NOT NULL,
    session_type VARCHAR(50) NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration INT NOT NULL,
    location VARCHAR(100),
    is_virtual TINYINT(1) DEFAULT 0,
    virtual_link VARCHAR(500),
    package_id BIGINT(20) UNSIGNED,
    session_price DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'scheduled',
    trainer_notes TEXT,
    exercises_performed TEXT,
    client_feedback TEXT,
    next_session_goals TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX member_id_idx (member_id),
    INDEX trainer_id_idx (trainer_id),
    INDEX session_date_idx (session_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    // General Fitness Settings
    'enable_fitness_features' => true,
    'facility_type' => 'gym', // gym, yoga_studio, crossfit, sports_club, martial_arts
    'timezone' => 'America/New_York',
    'enable_mobile_app' => true,

    // Class Management
    'enable_class_booking' => true,
    'enable_class_capacity' => true,
    'default_class_capacity' => 20,
    'enable_waitlist' => true,
    'auto_promote_waitlist' => true,
    'waitlist_notification_minutes' => 60,
    'allow_late_booking_minutes' => 30,
    'cancellation_deadline_hours' => 2,
    'enable_recurring_classes' => true,
    'max_bookings_per_member' => 3,

    // Membership Settings
    'enable_memberships' => true,
    'membership_tiers' => ['Basic', 'Premium', 'Elite'],
    'enable_credit_system' => true,
    'enable_credit_rollover' => false,
    'max_rollover_credits' => 2,
    'enable_membership_freeze' => true,
    'max_freeze_days_per_year' => 30,
    'freeze_processing_fee' => 10.00,
    'enable_guest_passes' => true,
    'guest_pass_limit' => 2,

    // Equipment Booking
    'enable_equipment_booking' => true,
    'default_booking_duration' => 60,
    'max_booking_duration' => 120,
    'equipment_booking_price' => 10.00,
    'enable_equipment_maintenance_tracking' => true,

    // Check-In System
    'enable_checkin' => true,
    'checkin_method' => 'qr_code', // qr_code, barcode, facial_recognition, manual
    'auto_checkout_hours' => 4,
    'track_facility_usage' => true,
    'enable_access_control' => true,

    // Fitness Tracking
    'enable_fitness_tracking' => true,
    'enable_progress_photos' => true,
    'enable_body_measurements' => true,
    'enable_goal_tracking' => true,
    'measurement_system' => 'metric', // metric, imperial
    'enable_fitness_assessments' => true,

    // Workout Plans
    'enable_workout_plans' => true,
    'enable_exercise_library' => true,
    'enable_video_demos' => true,
    'enable_workout_logging' => true,
    'integrate_fitness_apps' => true,

    // Personal Training
    'enable_personal_training' => true,
    'enable_pt_packages' => true,
    'enable_virtual_training' => true,
    'enable_semi_private_training' => true,
    'max_semi_private_size' => 3,

    // Challenges & Competitions
    'enable_challenges' => true,
    'enable_leaderboards' => true,
    'enable_team_challenges' => true,
    'challenge_entry_fees' => true,

    // Nutrition
    'enable_nutrition_tracking' => true,
    'enable_meal_plans' => true,
    'enable_nutrition_consultations' => true,
    'integrate_nutrition_apps' => true,

    // Member Engagement
    'enable_achievement_badges' => true,
    'enable_milestone_tracking' => true,
    'enable_social_features' => true,
    'enable_referral_program' => true,
    'referral_reward_type' => 'credits', // credits, discount, free_month
    'referral_reward_amount' => 50,

    // Health & Safety
    'require_health_screening' => true,
    'require_liability_waiver' => true,
    'collect_emergency_contacts' => true,
    'enable_covid_protocols' => true,
    'enable_incident_reporting' => true,

    // No-Show Policy
    'track_no_shows' => true,
    'no_show_penalty_type' => 'credit', // credit, fee, suspension
    'no_show_penalty_amount' => 1,
    'no_show_threshold' => 3,
    'no_show_suspension_days' => 7,

    // Notifications
    'class_reminder_hours' => 2,
    'membership_expiry_warning_days' => 7,
    'credit_low_warning' => 2,
    'waitlist_notification_enabled' => true,
    'challenge_updates_enabled' => true,
    'achievement_notifications' => true,

    // Reporting & Analytics
    'track_class_attendance' => true,
    'track_equipment_utilization' => true,
    'track_member_retention' => true,
    'track_revenue_by_service' => true,
    'track_peak_times' => true,
    'calculate_engagement_scores' => true,
]
```

---

## 7. Industry-Specific Workflows

### Workflow 1: New Member Onboarding
```
1. Member signs up → Membership selected → Payment processed
2. Health screening questionnaire → Liability waiver signed
3. Emergency contact collected → Member profile created
4. Fitness assessment scheduled → Goals documented
5. QR check-in code generated → Mobile app access granted
6. Welcome email sent → Orientation class recommended
7. Workout plan assigned → First check-in reward
```

### Workflow 2: Class Booking with Capacity
```
1. Member views schedule → Real-time capacity displayed
2. Class selected → Availability checked
   - If available → Booking confirmed → Credits deducted
   - If full → Join waitlist → Position assigned
3. Reminder sent 2 hours before → Prep instructions included
4. Member checks in with QR code → Attendance marked
5. Class completes → Workout logged automatically
6. Feedback request sent → Rebook suggestion triggered
```

### Workflow 3: Personal Training Package
```
1. Member purchases PT package → Package activated
2. Trainer assigned based on goals → Introduction email sent
3. Initial assessment scheduled → Assessment completed
4. Workout plan created → Exercises demonstrated
5. Sessions scheduled in advance → Reminders sent
6. After each session → Progress notes added
7. Package nearing completion → Renewal offer sent
```

### Workflow 4: Fitness Challenge
```
1. Challenge created → Details published → Registrations open
2. Members join → Entry fee processed (if applicable)
3. Challenge starts → Progress tracking begins
4. Daily/weekly updates → Leaderboard updated real-time
5. Members log workouts → Progress auto-calculated
6. Challenge ends → Winners determined
7. Prizes distributed → Challenge recap shared
```

---

## 8. Testing Strategy

### Unit Tests
```php
- test_class_capacity_enforcement()
- test_waitlist_promotion()
- test_credit_deduction()
- test_membership_freeze()
- test_equipment_booking_conflict()
- test_checkin_qr_code_generation()
- test_fitness_tracking_calculation()
- test_goal_progress_update()
- test_workout_plan_assignment()
- test_challenge_leaderboard_ranking()
```

### Integration Tests
```php
- test_member_signup_to_first_checkin()
- test_class_booking_to_attendance()
- test_waitlist_to_confirmed_booking()
- test_pt_package_purchase_to_sessions()
- test_challenge_enrollment_to_completion()
- test_membership_freeze_to_unfreeze()
```

---

## 9. Development Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Database schema
- [ ] Core class structure
- [ ] Settings framework
- [ ] API endpoints

### Phase 2: Class Management (Week 3-4)
- [ ] Class CRUD operations
- [ ] Capacity management
- [ ] Waitlist system
- [ ] Booking/cancellation

### Phase 3: Membership System (Week 5-6)
- [ ] Membership tiers
- [ ] Credit system
- [ ] Freeze functionality
- [ ] Subscription integration

### Phase 4: Equipment Booking (Week 7)
- [ ] Equipment management
- [ ] Booking system
- [ ] Maintenance tracking
- [ ] Conflict resolution

### Phase 5: Check-In System (Week 8)
- [ ] QR code generation
- [ ] Check-in processing
- [ ] Attendance tracking
- [ ] Access control

### Phase 6: Fitness Tracking (Week 9-10)
- [ ] Measurement logging
- [ ] Progress photos
- [ ] Goal tracking
- [ ] Progress reports

### Phase 7: Workout Plans (Week 11)
- [ ] Plan creation
- [ ] Exercise library
- [ ] Workout logging
- [ ] Plan templates

### Phase 8: Personal Training (Week 12)
- [ ] Session booking
- [ ] Package management
- [ ] Trainer notes
- [ ] Virtual sessions

### Phase 9: Challenges (Week 13)
- [ ] Challenge creation
- [ ] Participant enrollment
- [ ] Progress tracking
- [ ] Leaderboards

### Phase 10: Nutrition (Week 14)
- [ ] Meal plans
- [ ] Nutrition tracking
- [ ] Recipe library
- [ ] App integrations

### Phase 11: UI Development (Week 15-17)
- [ ] Member portal
- [ ] Admin dashboard
- [ ] Mobile app/PWA
- [ ] Class schedule display

### Phase 12: Testing & Launch (Week 18-20)
- [ ] Comprehensive testing
- [ ] Beta with gyms
- [ ] Documentation
- [ ] Production launch

**Total Estimated Timeline:** 20 weeks (5 months)

---

## 10. Success Metrics

### Business Metrics
- 60% class utilization rate
- 40% member retention improvement
- 25% increase in PT package sales
- 80% check-in adoption rate
- 30% challenge participation rate

### Technical Metrics
- Booking success rate > 99%
- Check-in speed < 2 seconds
- Waitlist promotion < 1 minute
- System uptime > 99.9%
- Mobile app rating > 4.5/5

---

## 11. Known Limitations

1. **Real-Time Capacity:** Requires WebSocket for instant updates
2. **Facial Recognition:** Requires additional hardware/software
3. **Fitness App Sync:** Limited to major platforms (Apple Health, Google Fit, Fitbit)
4. **Virtual Classes:** Video quality depends on internet bandwidth
5. **Equipment Tracking:** Cannot physically prevent unauthorized use

---

## 12. Future Enhancements

### Version 2.0 Roadmap
- [ ] AI workout plan generation
- [ ] Computer vision form checking
- [ ] Wearable device integration (smartwatches)
- [ ] Biometric check-in
- [ ] Social fitness feed
- [ ] Gamification enhancements
- [ ] Advanced analytics dashboard
- [ ] Multi-location membership

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development

**Target Industries:**
- Gyms & Fitness Centers
- Yoga Studios
- CrossFit Boxes
- Pilates Studios
- Martial Arts Schools
- Sports Clubs
- Personal Training Studios
- Wellness Centers
- Climbing Gyms
- Cycling Studios
