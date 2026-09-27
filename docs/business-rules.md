# Business Rules

Source of truth for logic. Anything marked **[confirm]** is a default assumption to verify with the pilot school; keep it configurable.

## 1. Academic year and enrollment

- Exactly one `is_current` academic year per school. Most screens default to it; a year switcher is in the top bar.
- A student has at most one enrollment per academic year.
- Roll is unique within (section, academic year).
- Promotion (post-MVP): creates a new enrollment in the next year; the old enrollment's status becomes `promoted` or `retained`. Old enrollments are never modified otherwise.

## 2. Grading (GradeCalculator)

Default grading scheme (Bangladesh GPA 5.0):

| Percent | Letter | Point |
|---|---|---|
| 80–100 | A+ | 5.00 |
| 70–79 | A | 4.00 |
| 60–69 | A- | 3.50 |
| 50–59 | B | 3.00 |
| 40–49 | C | 2.00 |
| 33–39 | D | 1.00 |
| 0–32 | F | 0.00 |

Percent is compared after rounding to nearest whole number **[confirm]**. Store the scheme in `grade_rules`, never hardcode.

### Subject pass/fail
- If an exam subject uses parts (CQ / MCQ / practical), the student must reach the pass mark in **each part used**, AND the total. Failing any part = F for that subject, regardless of total.
- If parts are not used, only `total_pass` applies.
- `is_absent` = F with 0 marks. For combined papers, absent in any paper = F for the combined subject.
- Subject percent = total obtained / total_full × 100.

### Combined subjects
- Subjects sharing a `combined_key` (e.g. Bangla 1st + 2nd, English 1st + 2nd) produce ONE subject result.
- Combined marks = sum of each paper; combined full = sum of full marks; each part's pass check uses the summed part marks against summed part pass marks **[confirm]**.
- The combined result counts as one subject in GPA.

### GPA
- A student sits every exam subject of their class that is common or belongs to their group. A subject that may be a 4th subject but that the student did not choose counts as compulsory (e.g. Science: Higher Math as 4th → Biology compulsory) **[confirm]**.
- Compulsory subjects = all subjects for the student's class (and group) except their optional (4th) subject.
- If ANY compulsory subject is F → GPA = 0.00, letter F.
- Otherwise:
  - `base = sum(points of compulsory subjects)`
  - `bonus = max(0, optional_point − 2)` (only if the student has an optional subject)
  - `GPA = min(5.00, (base + bonus) / count(compulsory subjects))`
  - Round GPA to 2 decimals (truncate vs round **[confirm]**; default: round half up).
- Failing the optional subject does NOT fail the student; bonus is just 0.
- Also store `gpa_without_optional` (= base / count), since mark sheets show both.
- Letter for the overall GPA uses the point thresholds: 5.00 A+, ≥4 A, ≥3.5 A-, ≥3 B, ≥2 C, ≥1 D, else F.

### Merit position
Sort within section (and separately within class):
1. Passed students before failed students
2. GPA descending
3. Total marks descending
4. Fail count ascending (only matters among failed)
5. Roll ascending
Equal on 2–3 = same position? **[confirm]** Default: no ties, roll breaks them.

### Publishing
- Exam status flow: `draft` → `marks_entry` → `published`.
- Only `school_admin` can publish. Publishing runs GradeCalculator for all active enrollments of the exam's classes, fills `exam_results` and `subject_results`. Publishing is refused while any mark is missing (a blank is never treated as F); mark the student absent instead.
- After publishing, marks are read-only. Unpublish (admin only) → edit → republish recalculates.
- Every mark create/update writes a `mark_audits` row.
- Teachers can only enter marks for (section, subject) pairs assigned to them in `subject_teachers`.

### Tests required (GradeCalculator)
- All A+ student → 5.00
- One compulsory F → 0.00 F even with high total
- Optional subject A+ adds 3 points bonus; capped at 5.00
- Optional F → no penalty
- Passed total but failed MCQ part → subject F
- Combined Bangla 1st+2nd → one result
- Absent → F
- Merit order with ties on GPA broken by total then roll

## 3. Fees

- Fee structure = amount per (academic year, class, fee head).
- Monthly invoice generation: artisan command `fees:generate {month}` creates one invoice per active enrollment for the monthly heads, plus any one-time heads not yet billed. Idempotent — running twice for a month must not duplicate invoices.
- Waivers apply per enrollment per fee head (percent or fixed) and are applied at invoice generation. Invoice stores the waiver amount per item. A fixed waiver is capped at the fee; a fully waived invoice is `paid`. Changing a waiver does not change invoices already issued.
- Heads: `monthly` heads are billed every month; `yearly` heads once per enrollment (per academic year); `one_time` heads once per student ever (e.g. admission fee). All are billed on the first invoice generated after they are set in the fee structure.
- Default due date: 10th of the month (`config/school.php` → `fees.due_day`) **[confirm]**. Late fine: fixed amount per invoice after due date, configurable, default 0 **[confirm]** — not built in the MVP (`fine_total` stays 0).
- Payment:
  - Can be any amount ≥ 1.
  - Allocated to the enrollment's unpaid invoices, oldest first, through `payment_allocations`.
  - Overpayment beyond all dues: reject in MVP (advance payment is post-MVP).
  - Updates each invoice's `paid_total` and status (`unpaid` / `partial` / `paid`).
- Receipt numbers and invoice numbers are sequential per school per year, e.g. `R-2026-000123`. Generate inside a DB transaction with a lock to avoid duplicates.
- Deleting a payment is not allowed; cancel it with a reason (soft delete + reverses allocations).
- Due for a student = sum(invoice.total − invoice.paid_total) for non-cancelled invoices.
- Reports: daily collection (by method and by collector), dues list by class/section with guardian phone, monthly collection summary.

### Tests required (fees)
- Generating twice doesn't duplicate
- 50% waiver on tuition applied correctly
- Partial payment → status partial; paying the rest → paid
- One payment covering two months allocates oldest first
- Receipt numbers are unique under concurrent requests

## 4. Attendance

- One record per enrollment per date. Teachers take attendance for sections where they are class teacher or subject teacher **[confirm]**.
- Default all students to present on the entry screen; teacher marks exceptions.
- Editing past attendance: allowed for the same day for teachers; any day for school_admin.
- Absent SMS: after attendance is saved, queue one SMS per absent student to the primary guardian, only once per day (`sms_sent_at`), only if the school has SMS balance and the setting is on.
- Friday (and school-configured holidays) are not working days **[confirm]**; attendance % = present (+ late) / working days recorded.

## 5. SMS

- `SmsService` with a driver interface. Drivers: `log` (default in dev — writes to `sms_logs` and laravel.log) and one real provider later.
- Bangla SMS is Unicode: 70 characters per part (67 for multipart). English: 160 (153 multipart). Count parts and deduct from `sms_balance`.
- Never send if balance is insufficient; mark as `failed` with reason.
- Bulk sends (custom SMS, notice SMS) go to the primary guardian of each active student in the year, one SMS per phone number (siblings sharing a guardian get one). The whole send is refused up front if the balance cannot cover every recipient.
- Notices: staff see published notices for `all` / `staff` on the dashboard. There is no guardian portal in the MVP, so guardian and class notices reach guardians only through the optional "also send by SMS" when the notice is created.
- Templates (bn default):
  - Absent: "প্রিয় অভিভাবক, আপনার সন্তান {name} (রোল {roll}) আজ {date} তারিখে বিদ্যালয়ে অনুপস্থিত। — {school}"
  - Due: "{name} এর বকেয়া বেতন {amount} টাকা। অনুগ্রহ করে {due_date} এর মধ্যে পরিশোধ করুন। — {school}"
  - Result: "{name} {exam} পরীক্ষায় GPA {gpa} পেয়েছে। — {school}"

## 6. Access control

| Role | Access |
|---|---|
| platform_admin | Create/activate schools, add SMS balance. Not school data by default. |
| school_admin | Everything within the school; publish results; cancel payments |
| accountant | Fees, payments, reports; read-only students |
| teacher | Attendance and marks for assigned sections/subjects; read-only students in those sections |
| guardian | Post-MVP portal: own children only |

Enforce with policies + permissions, not just hidden buttons.
