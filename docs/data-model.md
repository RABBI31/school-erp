# Data Model

Source of truth for migrations. `T` = tenant table (has `school_id` + `BelongsToSchool`).
All tables have `id`, `timestamps`. Types are suggestions; keep names.

## Platform

### schools
- name_en, name_bn, slug (unique, used as subdomain), eiin (nullable), address, phone, email
- logo_path, principal_signature_path (nullable)
- default_locale (`bn`|`en`), is_active
- sms_balance (int, number of SMS remaining)

### users
- name, email (nullable, unique per school), phone (nullable), password
- school_id (nullable — null only for platform super admin)
- user_type: `platform_admin` | `staff` | `guardian`
- Unique index on (school_id, phone) and (school_id, email)

Roles via spatie/laravel-permission with teams (team_id = school_id):
`school_admin`, `accountant`, `teacher`, `guardian`.

## School setup

### academic_years (T)
- name (e.g. "2026"), starts_on, ends_on, is_current (only one current per school)

### shifts (T)
- name (Morning / Day)

### versions (T)
- name (Bangla / English)

### classes (T) — model name `SchoolClass` (`class` is reserved)
- name_en, name_bn, numeric_order (6, 7, 8…), has_groups (bool, true for 9–10)

### groups (T)
- name (Science / Business Studies / Humanities)

### sections (T)
- school_class_id, shift_id, version_id, name ("A"), class_teacher_id (staff, nullable), capacity

### subjects (T)
- school_class_id, group_id (nullable = common for all groups)
- name_en, name_bn, code
- combined_key (nullable string — subjects with the same key are combined, e.g. Bangla 1st + 2nd = "bangla")
- is_optional_allowed (bool — can be chosen as 4th subject)
- display_order

## Students

### guardians (T)
- name_en, name_bn, relation (father/mother/other), phone, alt_phone, occupation, nid (nullable)
- user_id (nullable — set when a guardian login is created)

### students (T)
- student_uid (unique per school, e.g. 2026-0001), name_en, name_bn
- date_of_birth, gender, blood_group (nullable), religion (nullable)
- father_name, mother_name, present_address, permanent_address
- photo_path (nullable), birth_reg_no (nullable)
- admission_date, status: `active` | `left` | `transferred` | `graduated`
- soft deletes

### guardian_student (pivot)
- guardian_id, student_id, is_primary (bool — receives SMS)

### enrollments (T)
- student_id, academic_year_id, section_id, group_id (nullable), roll (int)
- optional_subject_id (nullable — the 4th subject)
- status: `active` | `promoted` | `retained` | `left`
- Unique: (student_id, academic_year_id)
- Unique: (section_id, academic_year_id, roll)

## Staff

### staff (T)
- employee_code, name_en, name_bn, designation, phone, email, joining_date
- user_id (nullable), photo_path, status, soft deletes

### subject_teachers (T)
- section_id, subject_id, staff_id, academic_year_id

## Attendance

### student_attendances (T)
- enrollment_id, date, status: `present` | `absent` | `late` | `leave`
- taken_by (user_id), sms_sent_at (nullable)
- Unique: (enrollment_id, date)

### staff_attendances (T)
- staff_id, date, status, in_time (nullable), out_time (nullable)
- Unique: (staff_id, date)

## Fees

### fee_heads (T)
- name_en, name_bn, type: `monthly` | `one_time` | `yearly`

### fee_structures (T)
- academic_year_id, school_class_id, fee_head_id, amount
- Unique: (academic_year_id, school_class_id, fee_head_id)

### student_fee_waivers (T)
- enrollment_id, fee_head_id, type: `percent` | `fixed`, value, reason

### invoices (T)
- enrollment_id, invoice_no (unique per school), month (nullable, YYYY-MM for monthly), issue_date, due_date
- subtotal, waiver_total, fine_total, total, paid_total, status: `unpaid` | `partial` | `paid` | `cancelled`
- soft deletes

### invoice_items (T)
- invoice_id, fee_head_id, amount, waiver_amount, net_amount

### payments (T)
- receipt_no (unique per school), enrollment_id, paid_at, amount
- method: `cash` | `bkash` | `nagad` | `bank` | `card`, transaction_ref (nullable)
- received_by (user_id), note, soft deletes

### payment_allocations (T)
- payment_id, invoice_id, amount
(A payment can cover several invoices; allocate oldest due first.)

## Exams

### grading_schemes (T)
- name, is_default

### grade_rules (T)
- grading_scheme_id, min_percent, max_percent, letter (A+, A, A-, B, C, D, F), point (5.00…0.00)

### exams (T)
- academic_year_id, name_en, name_bn (Half Yearly / Annual), grading_scheme_id
- start_date, status: `draft` | `marks_entry` | `published`
- published_at, published_by

### exam_subjects (T)
- exam_id, subject_id, school_class_id
- Parts with full & pass marks (nullable if part not used):
  cq_full, cq_pass, mcq_full, mcq_pass, practical_full, practical_pass
- total_full, total_pass (used when parts are not used)

### marks (T)
- exam_id, exam_subject_id, enrollment_id
- cq, mcq, practical (nullable decimals), total, is_absent (bool)
- entered_by, updated_by
- Unique: (exam_subject_id, enrollment_id)

### mark_audits (T)
- mark_id, changed_by, old_values (json), new_values (json), changed_at

### exam_results (T) — generated by GradeCalculator, not edited by hand
- exam_id, enrollment_id, total_marks, gpa (decimal 3,2), gpa_without_optional, letter, fail_count
- merit_position_section, merit_position_class
- Unique: (exam_id, enrollment_id)

### subject_results (T)
- exam_result_id, subject_id (or combined_key), marks, percent, letter, point, is_pass, is_optional

## Notices & SMS

### notices (T)
- title_en, title_bn, body_en, body_bn, audience: `all` | `guardians` | `staff` | `class`
- school_class_id (nullable), published_at, attachment_path (nullable)

### sms_logs (T)
- to_phone, message, type: `absent` | `result` | `due` | `notice` | `custom`
- status: `queued` | `sent` | `failed`, provider_response (json), parts (int), sent_at

## Relationship summary

```
School ─┬─ AcademicYear
        ├─ SchoolClass ─┬─ Section
        │               └─ Subject
        ├─ Student ── Enrollment (per year) ─┬─ StudentAttendance
        │     └─ Guardian (many-to-many)     ├─ Invoice ─ InvoiceItem
        │                                    ├─ Payment ─ PaymentAllocation ─ Invoice
        │                                    ├─ Mark ─ MarkAudit
        │                                    └─ ExamResult ─ SubjectResult
        ├─ Staff ─ SubjectTeacher
        ├─ Exam ─ ExamSubject
        └─ Notice, SmsLog
```
