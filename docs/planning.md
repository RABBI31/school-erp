# Planning — MVP Build

Goal: working demo of the 7 MVP modules with realistic seed data, then a pilot in one school.
Build strictly in this order; each step depends on the previous ones.

## Status

| # | Step | Status |
|---|---|---|
| 0 | Project setup | ☐ |
| 1 | Tenancy, auth, roles, app shell | ☐ |
| 2 | School setup | ☐ |
| 3 | Students & admission | ☐ |
| 4 | Staff | ☐ |
| 5 | Attendance | ☐ |
| 6 | Fees | ☐ |
| 7 | Exams & results | ☐ |
| 8 | Notices & SMS | ☐ |
| 9 | Dashboard | ☐ |
| 10 | Demo seed data | ☐ |
| 11 | Polish & rehearsal | ☐ |

Priority if time runs short: **7 (results) and 6 (fees) must be fully working.** 4, 8 and 9 can be thinner.

---

## 0. Project setup
- [ ] Laravel 12 project with the Vue starter kit (Inertia + TS)
- [ ] Docker: `docker compose up -d --build`, .env pointed at mysql/redis, app reachable on localhost:8000 and demo.localhost:8000
- [ ] Install: spatie/laravel-permission (teams on), spatie/browsershot (+ puppeteer), pestphp, vue-i18n
- [ ] `.env` timezone Asia/Dhaka, locale bn with en fallback
- [ ] Bangla font (Noto Sans Bengali / Hind Siliguri) loaded for UI and PDFs
- [ ] `bn_digits()` / `toBnDigits()` helpers with tests

## 1. Tenancy, auth, roles, app shell
- [ ] `schools` table, `BelongsToSchool` trait, `CurrentSchool` singleton, `ResolveSchool` middleware (subdomain)
- [ ] Users with `school_id`, `user_type`; login per school subdomain
- [ ] Roles + permissions seeded; policies base
- [ ] App layout: sidebar by role, top bar with school name, academic year switcher, bn/en toggle
- [ ] Reusable `DataTable.vue` (server pagination, search, filters) and form components
- [ ] Test: school A user cannot access school B records (404)

## 2. School setup
- [ ] CRUD: academic years (one current), shifts, versions, classes, groups, sections, subjects
- [ ] Subjects: combined_key, optional-allowed flag, ordering
- [ ] School profile page: logo, signature upload, bn/en names
- [ ] Setup is simple forms; no wizard needed for MVP

## 3. Students & admission
- [ ] Admission form: student + guardian(s) + enrollment (year, section, group, roll, optional subject) in one transaction
- [ ] Reuse existing guardian by phone search (siblings)
- [ ] Student list: filter by class/section/status, search by name/roll/uid
- [ ] Student profile page: info, guardians, enrollment history, (later) attendance/fees/results tabs
- [ ] Excel/CSV import with validation report (rows with errors listed, valid rows imported)
- [ ] Auto `student_uid`, auto-suggest next roll

## 4. Staff
- [ ] Staff CRUD with optional login creation and role
- [ ] Assign class teachers and subject teachers per section for the current year

## 5. Attendance
- [ ] Take attendance: pick section + date, all present by default, toggle absent/late/leave, save — must work well on a phone screen
- [ ] Absent SMS queued (log driver)
- [ ] Section monthly attendance sheet (grid: students × days)
- [ ] Student attendance % on profile
- [ ] Staff attendance (simple daily entry)

## 6. Fees
- [ ] Fee heads, fee structure per class/year
- [ ] Waivers per enrollment
- [ ] `fees:generate {month}` command + button in UI
- [ ] Collect payment screen: search student → show dues by invoice → enter amount/method → save → print receipt
- [ ] Receipt PDF (bn/en, school logo) — A5 or half-A4
- [ ] Reports: daily collection, dues list (with "send due SMS" action), monthly summary
- [ ] Cancel payment (admin, with reason)
- [ ] All fee tests in business-rules.md §3 pass

## 7. Exams & results
- [ ] Grading scheme + rules (seed the default)
- [ ] Exam create; exam subjects with part-wise full/pass marks (copy from previous exam)
- [ ] Mark entry grid per section + subject: fast keyboard entry (Tab/Enter moves down), absent checkbox, autosave draft, validation against full marks
- [ ] Teachers limited to assigned subjects
- [ ] Publish → GradeCalculator → exam_results + subject_results
- [ ] Mark sheet PDF per student (bn/en), bulk PDF per section (queued)
- [ ] Tabulation sheet per section (landscape PDF)
- [ ] Result summary: pass rate, GPA distribution, subject-wise fail count
- [ ] Result SMS to guardians
- [ ] Mark audit log visible to admin
- [ ] All GradeCalculator tests in business-rules.md §2 pass

## 8. Notices & SMS
- [ ] Notice CRUD with audience
- [ ] Custom SMS: choose class/section/all guardians, preview part count and cost
- [ ] SMS log page with balance

## 9. Dashboard (school_admin)
- [ ] Today: students present/absent %, staff present
- [ ] This month: fees collected vs billed, total dues
- [ ] Latest exam: pass rate
- [ ] Quick actions: take attendance, collect fee, enter marks

## 10. Demo seed data (`DemoSchoolSeeder`)
- [ ] One school "Tongi Model High School" (subdomain `demo`), current year 2026
- [ ] Classes 6–10, sections A/B, Science/Business/Humanities for 9–10, real NCTB-style subject lists
- [ ] ~200 students with realistic Bangladeshi names (en + bn), some siblings sharing a guardian
- [ ] 15 staff with subject assignments
- [ ] Last 30 working days of attendance (~92% present, a few chronic absentees)
- [ ] Fee structure; invoices Jan–Sep; mostly paid, ~15% with dues, some partial
- [ ] Half-yearly exam published with realistic mark distribution (some A+, some F)
- [ ] Users: admin@demo / accountant@demo / teacher@demo (password: `password`)

## 11. Polish & rehearsal
- [ ] Click through `docs/demo-script.md` 3 times; fix anything that breaks
- [ ] Check every page on a phone-sized screen
- [ ] Bangla toggle on every demo page shows no missing keys
- [ ] Record a backup screen video of the demo flow
- [ ] No new features on rehearsal day

## Post-MVP (do not build now)
Parent app/portal, online payment (bKash/Nagad/SSLCommerz), class routine, accounts, payroll, ID/admit cards & certificates, biometric sync, promotion, LMS, library, transport, hostel, website builder, analytics.
