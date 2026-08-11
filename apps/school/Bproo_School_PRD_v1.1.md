# Bproo School - Product Requirements Document (PRD)

## Purpose
Bproo School is a configurable School ERP designed to digitize the complete lifecycle of a school. This document focuses on business requirements and functional specifications only.

# Product Principles
- French is the default language.
- Multi-language support.
- Configuration over customization.
- Reuse existing entities instead of duplicating them.
- Permanent student history.
- Complete audit trail.

# Localization
## Default Language
French is the primary language.

## Multi-language
Support French, English, Spanish, Portuguese, Arabic and additional languages.
Administrators can enable/disable languages.
Users can choose their preferred language.

# Academic Year Management
The Academic Year is the central workspace.

When creating a new Academic Year the administrator can reuse:
- Classes
- Subjects
- Teachers
- Fee Structures
- Timetables
- Grading Systems
- Exam Sessions

The system should not duplicate existing data.

# Student Management
Every student has one permanent profile containing:
- Configurable Student ID
- Photo
- Personal Information
- Parent Information
- Academic History
- Financial History
- Attendance
- Examination Results

History must remain available even after 20 years.

# Configurable Student ID
Student IDs are generated automatically from configurable patterns.

Examples:
- 2026-000001
- SCH-2026-0001
- PRI-26-000001

# Student Enrollment
Student Creation occurs once.
Enrollment happens every Academic Year.

If the student already exists:
- Do not create another profile.
- Enroll the existing profile.

The same philosophy applies to Classes, Subjects and other master data.

# Flexible Enrollment
Enrollment should never be blocked because the profile is incomplete.

The profile displays a completion percentage and identifies missing information.

# Student ID Cards
Generate:
- Single ID Card
- Batch ID Cards

Batch filters:
- Academic Year
- Class
- Section
- Gender
- Status

Cards support Photo, QR Code, Barcode, Student ID, Class, School Logo and PDF printing.

# Financial Management
## Bank Payment
1. Parent pays at bank.
2. Parent receives receipt.
3. School verifies receipt.
4. Payment registered.
5. Balance updated.
6. SMS/Email sent.

## School Payment
Parents can also pay directly at school.
Receipt generated immediately.
SMS/Email notification sent.

# Examination Management
Teachers create exams and enter marks.

# Configurable Result Calculation
Administrators configure:
- Subject coefficients
- Assessment weights
- Grade scales
- Pass marks
- Ranking
- Promotion rules

No hardcoded calculation.

# Result Publication
Results are published only if configurable business rules are satisfied.
Example:
- Fees paid
- Marks validated
- Director approval

# Report Cards
Generate:
- Report Cards
- Academic Transcripts
- Result Sheets
- Academic History

Support Preview, Print and PDF.

# Notifications
Automatic notifications:
- Enrollment
- Payments
- Results
- Report Cards
- Announcements

Channels:
- SMS
- Email

# Search
Search by:
- Student ID
- Name
- Parent
- Phone
- Email
- Class

# Audit
Log all critical actions.

# Acceptance Criteria
- French default language.
- Multi-language support.
- Academic Year reuses existing entities.
- Student creation separated from enrollment.
- Enrollment works with incomplete profiles.
- Profile completion percentage available.
- Configurable Student IDs.
- Student ID Cards (single & batch).
- Bank and School payments.
- Automatic SMS/Email.
- Configurable grading engine.
- Configurable publication rules.
- One-click report cards.
- Permanent student history.
