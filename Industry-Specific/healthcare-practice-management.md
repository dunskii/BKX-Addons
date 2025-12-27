# Healthcare Practice Management Add-on - Development Documentation

## 1. Overview

**Add-on Name:** Healthcare Practice Management
**Price:** $199
**Category:** Industry-Specific Solutions
**Version:** 1.0.0
**Requires BookingX:** 2.0+
**WordPress Version:** 5.8+
**PHP Version:** 7.4+

### Description
Comprehensive healthcare practice management system designed for medical offices, dental practices, clinics, telemedicine providers, and healthcare professionals. Features electronic patient records, insurance verification, eligibility checking, medical billing with ICD-10/CPT coding, telehealth integration, prescription management, lab integration, patient communication tools, and full HIPAA compliance infrastructure.

### Value Proposition
- Electronic Health Records (EHR) management
- Real-time insurance eligibility verification
- Automated medical billing with coding
- Integrated telehealth platform
- E-prescription (eRx) capability
- Lab and diagnostic integration
- Patient portal with PHI security
- Claims submission and tracking
- Payment posting and reconciliation
- Referral management
- Quality measure tracking (HEDIS, MIPS)
- Multi-specialty practice support

---

## 2. Features & Requirements

### Core Features

1. **Patient Records Management**
   - Comprehensive patient demographics
   - Medical history documentation
   - Chief complaint and HPI tracking
   - Physical examination notes
   - Problem list maintenance
   - Medication list management
   - Allergy and adverse reaction tracking
   - Immunization records
   - Family medical history
   - Social history documentation
   - Review of systems (ROS)
   - Vital signs tracking
   - Growth charts (pediatrics)
   - Advance directives

2. **Insurance Verification & Eligibility**
   - Real-time eligibility checking
   - Insurance card scanning
   - Coverage verification
   - Co-pay and deductible calculation
   - Prior authorization tracking
   - Multi-payer support
   - Secondary insurance handling
   - Medicare/Medicaid verification
   - Benefits summary display
   - Eligibility history tracking
   - Automated verification scheduling
   - EOB (Explanation of Benefits) tracking

3. **Medical Billing & Coding**
   - ICD-10 diagnosis coding
   - CPT/HCPCS procedure coding
   - Automated code suggestion
   - Diagnosis-procedure linking
   - Claim scrubbing
   - Electronic claims submission (837)
   - Claim status tracking
   - ERA (Electronic Remittance Advice) processing
   - Payment posting
   - Denial management
   - Adjustment tracking
   - Secondary claim filing

4. **Telehealth Integration**
   - HIPAA-compliant video consultations
   - Waiting room functionality
   - Screen sharing capability
   - Virtual examination tools
   - Remote vital sign integration
   - Session recording (with consent)
   - Telehealth billing support
   - Multi-participant support
   - Chat and messaging
   - File sharing during consult
   - Post-visit summary
   - Telehealth compliance tracking

5. **E-Prescription (eRx)**
   - Electronic prescription writing
   - Drug database integration
   - Drug interaction checking
   - Allergy cross-checking
   - Pharmacy network integration
   - Controlled substance prescribing (EPCS)
   - Prescription history tracking
   - Refill request management
   - Prior authorization handling
   - Formulary checking
   - Generic substitution suggestions
   - Prescription printing (backup)

6. **Lab & Diagnostic Integration**
   - Electronic lab orders
   - Lab results interface
   - Result abnormality flagging
   - Result trending and graphing
   - Radiology order integration
   - DICOM image viewing
   - Pathology report integration
   - Reference range comparison
   - Critical value alerts
   - Result acknowledgment tracking
   - Patient result portal access
   - Lab workflow management

7. **Clinical Documentation**
   - SOAP note templates
   - Specialty-specific templates
   - Voice-to-text dictation
   - Clinical decision support
   - Order sets
   - Progress note documentation
   - Procedure notes
   - Operative reports
   - Discharge summaries
   - Consultation reports
   - Document signing and locking
   - Addendum capabilities

8. **Scheduling & Patient Flow**
   - Multi-provider scheduling
   - Multi-location support
   - Resource allocation (rooms, equipment)
   - Appointment types with durations
   - Color-coded appointment categories
   - Recurring appointment patterns
   - Waitlist management
   - Patient check-in/check-out
   - Queue management
   - Average wait time tracking
   - No-show tracking
   - Automated appointment reminders

9. **Patient Portal**
   - Secure patient login
   - Appointment scheduling
   - Medical record access
   - Lab result viewing
   - Prescription refill requests
   - Secure messaging with providers
   - Bill pay and payment history
   - Insurance information update
   - Forms and questionnaire completion
   - Immunization record access
   - Referral tracking
   - Educational resources

10. **Referral Management**
    - Electronic referral creation
    - Specialist directory
    - Referral tracking
    - Authorization tracking
    - Referral status updates
    - Consultation report receipt
    - Referring provider notifications
    - Referral analytics
    - Network management
    - Patient referral portal
    - Automated referral reminders

11. **Practice Management**
    - Provider credentialing tracking
    - Staff license management
    - DEA license tracking
    - Malpractice insurance monitoring
    - Continuing medical education (CME) tracking
    - Quality measure reporting (MIPS, HEDIS)
    - Patient satisfaction surveys
    - Revenue cycle analytics
    - Productivity dashboards
    - Payer contract management
    - Compliance monitoring
    - Policy and procedure management

12. **Reporting & Analytics**
    - Patient demographics reports
    - Appointment utilization
    - No-show and cancellation rates
    - Revenue by provider/procedure
    - Insurance collection rates
    - Days in A/R (accounts receivable)
    - Top diagnosis/procedure codes
    - Patient volume trends
    - Quality measure tracking
    - Meaningful Use/MIPS reporting
    - Custom report builder
    - Data export capabilities

### User Roles & Permissions

- **Physician/Provider:** Full clinical access, prescribing, documentation, ordering
- **Nurse Practitioner/PA:** Clinical access based on scope, prescribing (with oversight)
- **Nurse/MA:** Vital signs, rooming, medication administration, triage
- **Front Desk:** Scheduling, check-in/out, insurance verification, payment collection
- **Billing Specialist:** Coding, claim submission, payment posting, denial management
- **Office Manager:** Practice management, reporting, staff management, compliance
- **Lab Tech:** Lab orders, result entry, specimen tracking
- **Patient:** Portal access to own records, scheduling, communication
- **IT Administrator:** System configuration, user management, security settings

---

## 3. Technical Specifications

### Technology Stack
- **Frontend:** React for patient portal and clinical interfaces
- **Backend:** WordPress REST API with healthcare extensions
- **EHR Core:** Custom PHP framework with HL7 FHIR compatibility
- **Telehealth:** WebRTC with Twilio Video or Zoom Healthcare API
- **E-Prescription:** Integration with Surescripts or DrFirst
- **Insurance:** Real-time eligibility via Availity, Change Healthcare, or Waystar
- **Medical Billing:** EDI 837 for claims, 835 for remittance
- **Lab Integration:** HL7 v2.x messaging
- **Security:** AES-256 encryption, HIPAA-compliant infrastructure
- **Database:** MySQL with encrypted PHI fields

### Dependencies
- BookingX Core 2.0+
- HIPAA Compliance Add-on (recommended)
- PHP OpenSSL extension
- PHP SOAP extension (for insurance APIs)
- PHP XML extension (for HL7/EDI)
- SSL/TLS certificate (required)
- Node.js (for real-time features)
- Redis or Memcached (for session management)

### External Integrations
- **E-Prescription:** Surescripts, DrFirst, RxNT
- **Insurance Verification:** Availity, Change Healthcare, Waystar, Eligible API
- **Lab Systems:** LabCorp, Quest Diagnostics (HL7 interface)
- **Clearinghouses:** Change Healthcare, Availity, Waystar, Trizetto
- **Telehealth:** Twilio Video, Zoom for Healthcare, Doxy.me
- **Payment Processing:** Stripe, Square, InstaMed
- **Pharmacy Network:** SureScripts NCPDP

### API Integration Points
```php
// Patient Records API
GET    /wp-json/bookingx/v1/healthcare/patients/{id}
PUT    /wp-json/bookingx/v1/healthcare/patients/{id}
GET    /wp-json/bookingx/v1/healthcare/patients/{id}/medical-history
POST   /wp-json/bookingx/v1/healthcare/patients/{id}/encounter
GET    /wp-json/bookingx/v1/healthcare/patients/{id}/encounters
PUT    /wp-json/bookingx/v1/healthcare/encounters/{id}

// Insurance API
POST   /wp-json/bookingx/v1/healthcare/insurance/verify
GET    /wp-json/bookingx/v1/healthcare/insurance/eligibility/{patient_id}
POST   /wp-json/bookingx/v1/healthcare/insurance/authorization
GET    /wp-json/bookingx/v1/healthcare/insurance/coverage/{patient_id}

// Medical Billing API
POST   /wp-json/bookingx/v1/healthcare/billing/charge
POST   /wp-json/bookingx/v1/healthcare/billing/claim
GET    /wp-json/bookingx/v1/healthcare/billing/claims/{id}
POST   /wp-json/bookingx/v1/healthcare/billing/payment
GET    /wp-json/bookingx/v1/healthcare/billing/aging
POST   /wp-json/bookingx/v1/healthcare/billing/denial

// Telehealth API
POST   /wp-json/bookingx/v1/healthcare/telehealth/session
GET    /wp-json/bookingx/v1/healthcare/telehealth/session/{id}
POST   /wp-json/bookingx/v1/healthcare/telehealth/join/{id}
PUT    /wp-json/bookingx/v1/healthcare/telehealth/session/{id}/end
GET    /wp-json/bookingx/v1/healthcare/telehealth/recordings/{id}

// E-Prescription API
POST   /wp-json/bookingx/v1/healthcare/prescription/create
GET    /wp-json/bookingx/v1/healthcare/prescription/{id}
POST   /wp-json/bookingx/v1/healthcare/prescription/send
POST   /wp-json/bookingx/v1/healthcare/prescription/refill
GET    /wp-json/bookingx/v1/healthcare/prescription/patient/{patient_id}
POST   /wp-json/bookingx/v1/healthcare/prescription/check-interactions

// Lab Integration API
POST   /wp-json/bookingx/v1/healthcare/lab/order
GET    /wp-json/bookingx/v1/healthcare/lab/results/{patient_id}
PUT    /wp-json/bookingx/v1/healthcare/lab/result/{id}/acknowledge
GET    /wp-json/bookingx/v1/healthcare/lab/pending
POST   /wp-json/bookingx/v1/healthcare/lab/result/comment

// Clinical Documentation API
POST   /wp-json/bookingx/v1/healthcare/clinical/note
GET    /wp-json/bookingx/v1/healthcare/clinical/note/{id}
PUT    /wp-json/bookingx/v1/healthcare/clinical/note/{id}
POST   /wp-json/bookingx/v1/healthcare/clinical/note/{id}/sign
GET    /wp-json/bookingx/v1/healthcare/clinical/templates
POST   /wp-json/bookingx/v1/healthcare/clinical/addendum

// Patient Portal API
GET    /wp-json/bookingx/v1/healthcare/portal/records
GET    /wp-json/bookingx/v1/healthcare/portal/lab-results
POST   /wp-json/bookingx/v1/healthcare/portal/appointment
POST   /wp-json/bookingx/v1/healthcare/portal/refill-request
POST   /wp-json/bookingx/v1/healthcare/portal/message
GET    /wp-json/bookingx/v1/healthcare/portal/billing

// Referral API
POST   /wp-json/bookingx/v1/healthcare/referral/create
GET    /wp-json/bookingx/v1/healthcare/referral/{id}
PUT    /wp-json/bookingx/v1/healthcare/referral/{id}/status
GET    /wp-json/bookingx/v1/healthcare/referral/patient/{patient_id}
POST   /wp-json/bookingx/v1/healthcare/referral/{id}/authorization

// Quality Measures API
GET    /wp-json/bookingx/v1/healthcare/quality/measures
GET    /wp-json/bookingx/v1/healthcare/quality/mips-score
POST   /wp-json/bookingx/v1/healthcare/quality/report
GET    /wp-json/bookingx/v1/healthcare/quality/hedis
```

---

## 4. Architecture & Design

### System Architecture
```
┌──────────────────────────────────────┐
│   Patient Portal (React)             │
│  - Appointments                      │
│  - Medical Records                   │
│  - Secure Messaging                  │
│  - Bill Pay                          │
└───────────┬──────────────────────────┘
            │
            ▼
┌──────────────────────────────────────┐
│   Clinical Workstation               │
│  - EHR Documentation                 │
│  - E-Prescribing                     │
│  - Lab Orders/Results                │
│  - CPOE (Computerized Orders)        │
└───────────┬──────────────────────────┘
            │
            ▼
┌──────────────────────────────────────┐
│   BookingX Healthcare Core           │
│  - Patient Management                │
│  - Scheduling                        │
│  - Clinical Documentation            │
│  - HIPAA Compliance Layer            │
└───────────┬──────────────────────────┘
            │
            ├──────────┬───────────┬──────────┬───────────┬──────────┐
            ▼          ▼           ▼          ▼           ▼          ▼
┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────┐  ┌──────┐  ┌────────┐
│Insurance │  │ Medical  │  │Telehealth│  │  eRx   │  │ Lab  │  │Quality │
│Verifier  │  │ Billing  │  │          │  │        │  │ Integ│  │Reports │
└──────────┘  └──────────┘  └──────────┘  └────────┘  └──────┘  └────────┘
```

### Data Flow: Patient Visit Workflow
```
1. Patient schedules appointment → Appointment confirmed
2. Insurance verified → Eligibility confirmed → Co-pay calculated
3. Patient arrives → Checked in → Forms completed
4. Nurse rooms patient → Vitals entered → Chief complaint documented
5. Provider sees patient → Clinical note documented → Orders placed
6. E-prescription sent → Lab orders transmitted
7. Encounter closed → Charges captured → Diagnosis/procedure codes assigned
8. Claim generated → Scrubbed → Submitted to payer
9. Payment received → Posted → Patient statement generated
10. Lab results received → Provider reviews → Patient notified
```

### Class Structure
```php
namespace BookingX\Addons\Healthcare;

class PatientManager {
    - create_patient()
    - update_demographics()
    - get_medical_history()
    - add_allergy()
    - add_medication()
    - update_problem_list()
    - track_immunizations()
    - record_vitals()
    - manage_insurance()
    - merge_patients()
}

class EncounterManager {
    - create_encounter()
    - document_chief_complaint()
    - document_hpi()
    - document_ros()
    - document_physical_exam()
    - document_assessment_plan()
    - add_diagnosis()
    - order_lab_test()
    - prescribe_medication()
    - sign_encounter()
    - create_addendum()
}

class InsuranceVerifier {
    - verify_eligibility()
    - scan_insurance_card()
    - parse_coverage_details()
    - calculate_patient_responsibility()
    - check_prior_authorization()
    - track_authorization_status()
    - store_verification_history()
    - generate_verification_report()
}

class MedicalBillingManager {
    - create_charge()
    - assign_icd10_code()
    - assign_cpt_code()
    - link_diagnosis_procedure()
    - scrub_claim()
    - generate_837_claim()
    - submit_claim()
    - track_claim_status()
    - process_835_remittance()
    - post_payment()
    - manage_denial()
    - generate_statement()
}

class TelehealthManager {
    - create_session()
    - generate_meeting_link()
    - manage_waiting_room()
    - initiate_video_call()
    - share_screen()
    - record_session()
    - end_session()
    - generate_telehealth_note()
    - bill_telehealth_visit()
    - track_consent()
}

class PrescriptionManager {
    - create_prescription()
    - check_drug_interactions()
    - check_allergies()
    - search_formulary()
    - transmit_to_pharmacy()
    - prescribe_controlled_substance()
    - track_prescription_history()
    - manage_refill_request()
    - handle_prior_authorization()
    - print_prescription()
}

class LabIntegrationManager {
    - create_lab_order()
    - transmit_order_hl7()
    - receive_result()
    - parse_hl7_result()
    - flag_abnormal_results()
    - acknowledge_result()
    - notify_provider()
    - notify_patient()
    - store_result_document()
    - trend_results()
}

class ClinicalDocumentation {
    - load_template()
    - create_soap_note()
    - document_procedure()
    - create_operative_report()
    - generate_discharge_summary()
    - apply_voice_dictation()
    - provide_clinical_decision_support()
    - create_order_set()
    - lock_document()
}

class PatientPortal {
    - authenticate_patient()
    - display_health_summary()
    - show_lab_results()
    - enable_appointment_booking()
    - process_refill_request()
    - facilitate_secure_messaging()
    - display_billing()
    - accept_payment()
    - complete_questionnaire()
}

class ReferralManager {
    - create_referral()
    - select_specialist()
    - transmit_referral()
    - track_referral_status()
    - receive_consultation_report()
    - manage_authorization()
    - notify_referring_provider()
    - close_referral_loop()
}

class PracticeManagement {
    - track_provider_credentials()
    - manage_licenses()
    - monitor_dea_expiration()
    - track_cme_credits()
    - monitor_malpractice_insurance()
    - calculate_quality_measures()
    - generate_mips_report()
    - track_patient_satisfaction()
    - manage_payer_contracts()
}

class ComplianceManager {
    - enforce_hipaa_rules()
    - audit_phi_access()
    - track_breach_incidents()
    - manage_business_associates()
    - ensure_meaningful_use()
    - track_oig_exclusions()
    - monitor_stark_compliance()
    - manage_policies()
}

class ReportingEngine {
    - patient_demographics_report()
    - appointment_utilization()
    - revenue_by_provider()
    - insurance_collection_rate()
    - days_in_ar_report()
    - quality_measure_report()
    - mips_scoring()
    - custom_report_builder()
}
```

---

## 5. Database Schema

### Table: `bkx_healthcare_patients`
```sql
CREATE TABLE bkx_healthcare_patients (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED,
    mrn VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender VARCHAR(20),
    ssn VARCHAR(255),
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'USA',
    phone_primary VARCHAR(50),
    phone_secondary VARCHAR(50),
    email VARCHAR(255),
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(50),
    emergency_contact_relation VARCHAR(50),
    primary_language VARCHAR(50) DEFAULT 'English',
    race VARCHAR(100),
    ethnicity VARCHAR(100),
    marital_status VARCHAR(50),
    preferred_pharmacy_id BIGINT(20) UNSIGNED,
    primary_provider_id BIGINT(20) UNSIGNED,
    status VARCHAR(20) DEFAULT 'active',
    deceased TINYINT(1) DEFAULT 0,
    deceased_date DATE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX mrn_idx (mrn),
    INDEX user_id_idx (user_id),
    INDEX date_of_birth_idx (date_of_birth),
    INDEX primary_provider_idx (primary_provider_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_insurance`
```sql
CREATE TABLE bkx_healthcare_insurance (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'primary',
    insurance_company VARCHAR(255) NOT NULL,
    payer_id VARCHAR(50),
    plan_name VARCHAR(255),
    member_id VARCHAR(100) NOT NULL,
    group_number VARCHAR(100),
    policy_holder_name VARCHAR(255),
    policy_holder_dob DATE,
    policy_holder_relation VARCHAR(50),
    effective_date DATE,
    termination_date DATE,
    copay DECIMAL(10,2),
    deductible DECIMAL(10,2),
    deductible_met DECIMAL(10,2) DEFAULT 0,
    out_of_pocket_max DECIMAL(10,2),
    out_of_pocket_met DECIMAL(10,2) DEFAULT 0,
    last_verification_date DATETIME,
    verification_status VARCHAR(50),
    eligibility_details TEXT,
    card_front_image VARCHAR(500),
    card_back_image VARCHAR(500),
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX patient_id_idx (patient_id),
    INDEX priority_idx (priority),
    INDEX payer_id_idx (payer_id),
    INDEX status_idx (status),
    FOREIGN KEY (patient_id) REFERENCES bkx_healthcare_patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_encounters`
```sql
CREATE TABLE bkx_healthcare_encounters (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    encounter_number VARCHAR(50) NOT NULL UNIQUE,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    provider_id BIGINT(20) UNSIGNED NOT NULL,
    encounter_date DATETIME NOT NULL,
    encounter_type VARCHAR(100) NOT NULL,
    location VARCHAR(100),
    chief_complaint TEXT,
    hpi TEXT,
    ros TEXT,
    physical_exam TEXT,
    assessment TEXT,
    plan TEXT,
    soap_note TEXT,
    visit_duration INT,
    level_of_service VARCHAR(20),
    is_telehealth TINYINT(1) DEFAULT 0,
    telehealth_session_id BIGINT(20) UNSIGNED,
    status VARCHAR(20) DEFAULT 'open',
    signed TINYINT(1) DEFAULT 0,
    signed_by BIGINT(20) UNSIGNED,
    signed_at DATETIME,
    locked TINYINT(1) DEFAULT 0,
    has_addendum TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX encounter_number_idx (encounter_number),
    INDEX patient_id_idx (patient_id),
    INDEX provider_id_idx (provider_id),
    INDEX encounter_date_idx (encounter_date),
    INDEX status_idx (status),
    FOREIGN KEY (patient_id) REFERENCES bkx_healthcare_patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_diagnoses`
```sql
CREATE TABLE bkx_healthcare_diagnoses (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    encounter_id BIGINT(20) UNSIGNED,
    icd10_code VARCHAR(20) NOT NULL,
    icd10_description TEXT NOT NULL,
    diagnosis_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'active',
    is_chronic TINYINT(1) DEFAULT 0,
    onset_date DATE,
    resolution_date DATE,
    notes TEXT,
    diagnosed_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    INDEX patient_id_idx (patient_id),
    INDEX encounter_id_idx (encounter_id),
    INDEX icd10_code_idx (icd10_code),
    INDEX status_idx (status),
    FOREIGN KEY (patient_id) REFERENCES bkx_healthcare_patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_medications`
```sql
CREATE TABLE bkx_healthcare_medications (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    medication_name VARCHAR(255) NOT NULL,
    generic_name VARCHAR(255),
    ndc_code VARCHAR(50),
    strength VARCHAR(50),
    dosage_form VARCHAR(50),
    sig TEXT,
    quantity INT,
    refills INT DEFAULT 0,
    days_supply INT,
    prescriber_id BIGINT(20) UNSIGNED NOT NULL,
    prescribed_date DATE NOT NULL,
    start_date DATE,
    end_date DATE,
    status VARCHAR(20) DEFAULT 'active',
    is_controlled TINYINT(1) DEFAULT 0,
    schedule VARCHAR(10),
    pharmacy_id BIGINT(20) UNSIGNED,
    prescription_number VARCHAR(100),
    transmitted TINYINT(1) DEFAULT 0,
    transmission_date DATETIME,
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX patient_id_idx (patient_id),
    INDEX prescriber_id_idx (prescriber_id),
    INDEX status_idx (status),
    INDEX is_controlled_idx (is_controlled),
    FOREIGN KEY (patient_id) REFERENCES bkx_healthcare_patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_allergies`
```sql
CREATE TABLE bkx_healthcare_allergies (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    allergen VARCHAR(255) NOT NULL,
    allergen_type VARCHAR(50) NOT NULL,
    reaction TEXT,
    severity VARCHAR(20),
    onset_date DATE,
    notes TEXT,
    status VARCHAR(20) DEFAULT 'active',
    verified_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX patient_id_idx (patient_id),
    INDEX allergen_type_idx (allergen_type),
    INDEX severity_idx (severity),
    FOREIGN KEY (patient_id) REFERENCES bkx_healthcare_patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_vitals`
```sql
CREATE TABLE bkx_healthcare_vitals (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    encounter_id BIGINT(20) UNSIGNED,
    measurement_date DATETIME NOT NULL,
    height DECIMAL(5,2),
    height_unit VARCHAR(10) DEFAULT 'cm',
    weight DECIMAL(5,2),
    weight_unit VARCHAR(10) DEFAULT 'kg',
    bmi DECIMAL(4,2),
    temperature DECIMAL(4,2),
    temperature_unit VARCHAR(10) DEFAULT 'F',
    blood_pressure_systolic INT,
    blood_pressure_diastolic INT,
    pulse INT,
    respiration_rate INT,
    oxygen_saturation INT,
    pain_scale INT,
    head_circumference DECIMAL(5,2),
    notes TEXT,
    recorded_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX patient_id_idx (patient_id),
    INDEX encounter_id_idx (encounter_id),
    INDEX measurement_date_idx (measurement_date),
    FOREIGN KEY (patient_id) REFERENCES bkx_healthcare_patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_lab_orders`
```sql
CREATE TABLE bkx_healthcare_lab_orders (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    encounter_id BIGINT(20) UNSIGNED,
    provider_id BIGINT(20) UNSIGNED NOT NULL,
    order_date DATETIME NOT NULL,
    lab_name VARCHAR(255),
    test_code VARCHAR(50),
    test_name VARCHAR(255) NOT NULL,
    test_category VARCHAR(100),
    priority VARCHAR(20) DEFAULT 'routine',
    specimen_type VARCHAR(100),
    collection_date DATETIME,
    clinical_info TEXT,
    status VARCHAR(20) DEFAULT 'ordered',
    result_received TINYINT(1) DEFAULT 0,
    result_date DATETIME,
    result_status VARCHAR(50),
    critical_result TINYINT(1) DEFAULT 0,
    acknowledged TINYINT(1) DEFAULT 0,
    acknowledged_by BIGINT(20) UNSIGNED,
    acknowledged_at DATETIME,
    hl7_message TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX order_number_idx (order_number),
    INDEX patient_id_idx (patient_id),
    INDEX provider_id_idx (provider_id),
    INDEX status_idx (status),
    INDEX result_received_idx (result_received),
    FOREIGN KEY (patient_id) REFERENCES bkx_healthcare_patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_charges`
```sql
CREATE TABLE bkx_healthcare_charges (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    encounter_id BIGINT(20) UNSIGNED NOT NULL,
    provider_id BIGINT(20) UNSIGNED NOT NULL,
    service_date DATE NOT NULL,
    procedure_code VARCHAR(20) NOT NULL,
    procedure_description VARCHAR(500) NOT NULL,
    modifier1 VARCHAR(10),
    modifier2 VARCHAR(10),
    modifier3 VARCHAR(10),
    modifier4 VARCHAR(10),
    diagnosis_pointer VARCHAR(10),
    units INT DEFAULT 1,
    charge_amount DECIMAL(10,2) NOT NULL,
    allowed_amount DECIMAL(10,2),
    paid_amount DECIMAL(10,2) DEFAULT 0,
    adjustment_amount DECIMAL(10,2) DEFAULT 0,
    patient_responsibility DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'unbilled',
    claim_id BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX patient_id_idx (patient_id),
    INDEX encounter_id_idx (encounter_id),
    INDEX provider_id_idx (provider_id),
    INDEX service_date_idx (service_date),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_claims`
```sql
CREATE TABLE bkx_healthcare_claims (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_number VARCHAR(50) NOT NULL UNIQUE,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    insurance_id BIGINT(20) UNSIGNED NOT NULL,
    provider_id BIGINT(20) UNSIGNED NOT NULL,
    claim_date DATE NOT NULL,
    service_date_from DATE NOT NULL,
    service_date_to DATE NOT NULL,
    total_charge DECIMAL(10,2) NOT NULL,
    claim_type VARCHAR(20) DEFAULT 'professional',
    claim_frequency VARCHAR(10) DEFAULT '1',
    place_of_service VARCHAR(10),
    status VARCHAR(50) DEFAULT 'created',
    submitted_date DATETIME,
    payer_claim_number VARCHAR(100),
    adjudication_date DATE,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    patient_responsibility DECIMAL(10,2) DEFAULT 0,
    denial_reason TEXT,
    edi_837_file VARCHAR(255),
    edi_835_file VARCHAR(255),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX claim_number_idx (claim_number),
    INDEX patient_id_idx (patient_id),
    INDEX insurance_id_idx (insurance_id),
    INDEX status_idx (status),
    INDEX submitted_date_idx (submitted_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_telehealth_sessions`
```sql
CREATE TABLE bkx_healthcare_telehealth_sessions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL UNIQUE,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    provider_id BIGINT(20) UNSIGNED NOT NULL,
    scheduled_time DATETIME NOT NULL,
    start_time DATETIME,
    end_time DATETIME,
    duration INT,
    session_status VARCHAR(20) DEFAULT 'scheduled',
    video_platform VARCHAR(50),
    meeting_link VARCHAR(500),
    patient_join_link VARCHAR(500),
    waiting_room_enabled TINYINT(1) DEFAULT 1,
    recording_enabled TINYINT(1) DEFAULT 0,
    recording_url VARCHAR(500),
    recording_consent TINYINT(1) DEFAULT 0,
    encounter_id BIGINT(20) UNSIGNED,
    notes TEXT,
    technical_issues TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX session_id_idx (session_id),
    INDEX patient_id_idx (patient_id),
    INDEX provider_id_idx (provider_id),
    INDEX scheduled_time_idx (scheduled_time),
    INDEX session_status_idx (session_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `bkx_healthcare_referrals`
```sql
CREATE TABLE bkx_healthcare_referrals (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referral_number VARCHAR(50) NOT NULL UNIQUE,
    patient_id BIGINT(20) UNSIGNED NOT NULL,
    referring_provider_id BIGINT(20) UNSIGNED NOT NULL,
    specialist_id BIGINT(20) UNSIGNED,
    specialty VARCHAR(100) NOT NULL,
    reason_for_referral TEXT NOT NULL,
    diagnosis_codes TEXT,
    urgency VARCHAR(20) DEFAULT 'routine',
    referral_date DATE NOT NULL,
    expiration_date DATE,
    authorization_required TINYINT(1) DEFAULT 0,
    authorization_number VARCHAR(100),
    authorization_date DATE,
    visits_authorized INT,
    visits_used INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    appointment_scheduled TINYINT(1) DEFAULT 0,
    appointment_date DATE,
    consultation_received TINYINT(1) DEFAULT 0,
    consultation_date DATE,
    consultation_summary TEXT,
    closed TINYINT(1) DEFAULT 0,
    closed_date DATE,
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX referral_number_idx (referral_number),
    INDEX patient_id_idx (patient_id),
    INDEX referring_provider_idx (referring_provider_id),
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Configuration & Settings

### Admin Settings Panel
```php
[
    // General Healthcare Settings
    'enable_healthcare_features' => true,
    'practice_name' => '',
    'practice_type' => 'medical', // medical, dental, mental_health, chiropractic
    'npi_number' => '',
    'tax_id' => '',
    'place_of_service_code' => '11',
    'default_location' => '',

    // Patient Registration
    'require_ssn' => false,
    'require_insurance' => true,
    'collect_race_ethnicity' => true,
    'require_emergency_contact' => true,
    'auto_generate_mrn' => true,
    'mrn_format' => 'numeric',

    // Insurance Verification
    'enable_insurance_verification' => true,
    'auto_verify_on_checkin' => true,
    'verification_provider' => 'availity', // availity, change_healthcare, waystar
    'verify_secondary_insurance' => true,
    'cache_verification_hours' => 24,

    // Medical Billing
    'enable_medical_billing' => true,
    'clearinghouse_provider' => 'change_healthcare',
    'auto_submit_claims' => false,
    'claim_scrubbing_enabled' => true,
    'require_diagnosis_linking' => true,
    'enable_secondary_billing' => true,
    'patient_statement_cycle' => 'monthly',

    // Clinical Documentation
    'enable_ehr' => true,
    'require_signed_encounters' => true,
    'lock_signed_encounters' => true,
    'allow_addendum' => true,
    'enable_voice_dictation' => false,
    'clinical_decision_support' => true,
    'enable_templates' => true,

    // E-Prescribing
    'enable_eprescribing' => true,
    'erx_provider' => 'surescripts', // surescripts, drfirst
    'enable_controlled_substance' => true,
    'require_epcs_authentication' => true,
    'check_drug_interactions' => true,
    'check_allergies' => true,
    'prefer_generic' => true,

    // Telehealth
    'enable_telehealth' => true,
    'telehealth_platform' => 'twilio', // twilio, zoom, doxy
    'waiting_room_enabled' => true,
    'allow_session_recording' => true,
    'require_recording_consent' => true,
    'telehealth_billing_enabled' => true,

    // Lab Integration
    'enable_lab_orders' => true,
    'enable_lab_interface' => true,
    'lab_interface_type' => 'hl7', // hl7, api
    'auto_flag_abnormal' => true,
    'require_result_acknowledgment' => true,
    'notify_patient_of_results' => true,
    'portal_result_delay_hours' => 24,

    // Patient Portal
    'enable_patient_portal' => true,
    'portal_appointment_booking' => true,
    'portal_prescription_refills' => true,
    'portal_lab_results' => true,
    'portal_medical_records' => true,
    'portal_secure_messaging' => true,
    'portal_bill_pay' => true,
    'require_portal_identity_verification' => true,

    // Referrals
    'enable_referral_management' => true,
    'track_authorization' => true,
    'close_referral_loop' => true,
    'referral_expiration_days' => 90,

    // Quality Measures
    'enable_quality_measures' => true,
    'track_mips' => true,
    'track_hedis' => false,
    'meaningful_use_reporting' => true,
    'patient_satisfaction_surveys' => true,

    // Compliance & Security
    'hipaa_compliance_required' => true,
    'audit_phi_access' => true,
    'encrypt_patient_data' => true,
    'breach_notification_enabled' => true,
    'require_baa_with_vendors' => true,

    // Scheduling
    'enable_multi_provider' => true,
    'enable_multi_location' => true,
    'enable_resource_booking' => true,
    'default_appointment_duration' => 15,
    'allow_online_scheduling' => true,
    'require_insurance_for_booking' => false,

    // Provider Management
    'track_provider_credentials' => true,
    'track_dea_licenses' => true,
    'track_cme_credits' => true,
    'credential_expiry_warning_days' => 60,
    'require_malpractice_insurance' => true,

    // Notifications
    'appointment_reminder_enabled' => true,
    'reminder_hours_before' => 24,
    'lab_result_notification' => true,
    'prescription_ready_notification' => true,
    'referral_status_notification' => true,

    // Reporting
    'enable_advanced_analytics' => true,
    'track_revenue_cycle' => true,
    'track_provider_productivity' => true,
    'track_no_show_rate' => true,
    'export_to_csv' => true,
    'export_to_excel' => true,
]
```

---

## 7. Industry-Specific Workflows

### Workflow 1: New Patient Registration
```
1. Patient completes online forms → Demographics captured
2. Insurance cards uploaded → Auto-parsed
3. Insurance verified → Eligibility confirmed
4. Medical history questionnaire → Completed
5. Consent forms signed electronically → Stored
6. Appointment scheduled → Confirmation sent
7. MRN generated → Patient portal account created
8. Welcome email sent → Portal credentials provided
```

### Workflow 2: Patient Office Visit
```
1. Patient arrives → Checked in
2. Forms reviewed → Insurance verified
3. Nurse rooms patient → Vitals entered
4. Chief complaint documented → Provider notified
5. Provider enters → Encounter documentation
6. Diagnosis assigned (ICD-10) → Orders placed
7. Prescriptions sent electronically → Lab orders transmitted
8. Encounter signed → Charges captured
9. Checkout → Co-pay collected
10. Follow-up scheduled → After-visit summary printed
```

### Workflow 3: Medical Billing Cycle
```
1. Encounter closed → Charges captured
2. Diagnosis codes linked → Procedure codes assigned
3. Claim scrubbed → Errors corrected
4. Claim submitted (EDI 837) → Payer receives
5. Payer adjudicates → ERA received (EDI 835)
6. Payment posted → Patient balance calculated
7. Statement generated → Patient billed
8. Payment received → Applied to account
9. Denials managed → Corrected and resubmitted
```

### Workflow 4: Telehealth Visit
```
1. Patient schedules telehealth → Consent obtained
2. Reminder sent → Patient joins waiting room
3. Provider starts session → Video call initiated
4. Clinical assessment → Screen sharing used
5. E-prescription sent → Follow-up scheduled
6. Session ended → Encounter documented
7. Telehealth billing code applied → Claim submitted
8. Recording archived (if consented) → Stored securely
```

---

## 8. Compliance & Regulatory Requirements

### HIPAA Compliance
- Full integration with HIPAA Compliance Add-on
- PHI encryption at rest and in transit
- Audit logging of all PHI access
- Business Associate Agreements (BAA)
- Breach notification procedures
- Patient privacy rights management

### Meaningful Use / Promoting Interoperability
- Certified EHR Technology (CEHRT) standards
- Clinical quality measures (CQM) reporting
- Patient electronic access
- Care coordination through HIE
- E-prescribing capability
- Immunization registry reporting

### MIPS (Merit-based Incentive Payment System)
- Quality measure tracking
- Improvement activities documentation
- Advancing care information
- Cost measure reporting
- Composite score calculation

### State Medical Board
- Provider license tracking
- DEA license management
- CME credit tracking
- Scope of practice compliance
- Telemedicine regulations

### Insurance & Billing Compliance
- CMS-1500 compliance
- HIPAA 5010 EDI standards
- ICD-10-CM/PCS coding
- CPT/HCPCS coding standards
- Modifier usage rules
- LCD/NCD compliance

---

## 9. Testing Strategy

### Unit Tests
```php
- test_patient_registration()
- test_insurance_verification()
- test_encounter_documentation()
- test_icd10_cpt_linking()
- test_claim_generation()
- test_eprescription_transmission()
- test_lab_order_creation()
- test_telehealth_session()
- test_payment_posting()
- test_referral_creation()
```

### Integration Tests
```php
- test_patient_visit_workflow()
- test_billing_cycle()
- test_prescription_to_pharmacy()
- test_lab_order_to_result()
- test_claim_to_payment()
- test_telehealth_end_to_end()
```

---

## 10. Development Timeline

### Phase 1: Foundation (Week 1-3)
- [ ] Database schema
- [ ] Core architecture
- [ ] HIPAA infrastructure
- [ ] Settings framework

### Phase 2: Patient Management (Week 4-5)
- [ ] Patient registration
- [ ] Demographics
- [ ] Medical history
- [ ] Insurance management

### Phase 3: Clinical Documentation (Week 6-8)
- [ ] Encounter management
- [ ] SOAP notes
- [ ] Templates
- [ ] Signing/locking

### Phase 4: E-Prescribing (Week 9-10)
- [ ] Surescripts integration
- [ ] Drug interaction checking
- [ ] Formulary checking
- [ ] Controlled substance

### Phase 5: Lab Integration (Week 11-12)
- [ ] Lab orders
- [ ] HL7 interface
- [ ] Result parsing
- [ ] Result notification

### Phase 6: Medical Billing (Week 13-15)
- [ ] Charge capture
- [ ] ICD-10/CPT coding
- [ ] Claim generation
- [ ] EDI 837/835

### Phase 7: Insurance Verification (Week 16)
- [ ] Eligibility checking
- [ ] Real-time verification
- [ ] Coverage details
- [ ] Prior authorization

### Phase 8: Telehealth (Week 17-18)
- [ ] Video platform integration
- [ ] Waiting room
- [ ] Session recording
- [ ] Telehealth billing

### Phase 9: Patient Portal (Week 19-20)
- [ ] Patient authentication
- [ ] Medical records access
- [ ] Lab results
- [ ] Secure messaging

### Phase 10: Referral Management (Week 21)
- [ ] Referral creation
- [ ] Authorization tracking
- [ ] Consultation reports
- [ ] Loop closure

### Phase 11: Quality & Reporting (Week 22-23)
- [ ] MIPS measures
- [ ] Quality reporting
- [ ] Analytics dashboard
- [ ] Revenue cycle reports

### Phase 12: UI & Testing (Week 24-28)
- [ ] Clinical UI
- [ ] Patient portal UI
- [ ] Mobile responsiveness
- [ ] Comprehensive testing
- [ ] Security audit
- [ ] Beta with practices

### Phase 13: Launch (Week 29-30)
- [ ] Final testing
- [ ] Documentation
- [ ] Training materials
- [ ] Production deployment

**Total Estimated Timeline:** 30 weeks (7.5 months)

---

## 11. Success Metrics

### Clinical Metrics
- 95% encounter documentation completion
- 100% prescription transmission success
- 90% lab result acknowledgment rate
- 85% patient portal adoption

### Financial Metrics
- 98% claim acceptance rate
- 80% first-pass claim rate
- 90% collection rate
- <30 days in A/R

### Technical Metrics
- System uptime > 99.9%
- Insurance verification < 5 seconds
- ePrescribe send < 3 seconds
- Zero PHI breaches

---

## 12. Known Limitations

1. **Integration Complexity:** Requires third-party service subscriptions
2. **State Regulations:** Telehealth/prescribing varies by state
3. **Lab Interface:** Custom HL7 mapping may be needed
4. **Clearinghouse:** Limited to supported clearinghouses
5. **Controlled Substances:** EPCS requires additional hardware (token)
6. **DICOM Imaging:** Basic viewing only, full PACS not included

---

## 13. Future Enhancements

### Version 2.0 Roadmap
- [ ] Full EHR/EMR capabilities
- [ ] HL7 FHIR API
- [ ] Health Information Exchange (HIE)
- [ ] Population health management
- [ ] AI clinical decision support
- [ ] Voice recognition dictation
- [ ] Mobile provider app
- [ ] Patient engagement tools
- [ ] Chronic care management
- [ ] Remote patient monitoring

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** BookingX Development Team
**Status:** Ready for Development

**Target Industries:**
- Primary Care Practices
- Specialty Medical Practices
- Dental Practices
- Mental Health Providers
- Urgent Care Centers
- Telemedicine Providers
- Chiropractic Clinics
- Physical Therapy Clinics
- Podiatry Practices
- Dermatology Clinics

**Required Add-ons:**
- HIPAA Compliance Add-on (strongly recommended)

**Recommended Add-ons:**
- Advanced Security & Audit
- Data Backup & Recovery
- Multi-Location Management
