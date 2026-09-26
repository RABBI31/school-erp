# Claude Code Session Prompts

Start a fresh session (`/clear`) for each step so context stays small. Use plan mode (Shift+Tab) for the first message of each step, approve the plan, then let it build.
Or just run the custom command: `/module 6` (see `.claude/commands/module.md`).

---

## Step 0 — Setup
```
Read CLAUDE.md and docs/planning.md. Do step 0 (Project setup) only.
The Laravel app with the Vue starter kit is already created in this folder.
Install and configure the listed packages, set timezone/locale, add the Bangla font,
and create bn_digits()/toBnDigits() helpers with tests. Propose the plan first.
```

## Step 1 — Tenancy, auth, roles, shell
```
Read CLAUDE.md, docs/data-model.md (Platform section) and docs/planning.md step 1.
Implement multi-tenancy exactly as described in CLAUDE.md "Multi-tenancy rules":
schools table, BelongsToSchool trait, CurrentSchool, ResolveSchool subdomain middleware,
users with school_id and user_type, spatie permission with teams = school.
Build the app layout (role-based sidebar, school name, academic year switcher placeholder,
bn/en toggle) and reusable DataTable.vue + form components.
Write the cross-tenant isolation test. Propose the plan first, keep it simple.
```

## Step 2 — School setup
```
Read CLAUDE.md, docs/data-model.md (School setup) and docs/planning.md step 2.
Build CRUD pages for academic years, shifts, versions, classes, groups, sections, subjects,
and the school profile page. Use the existing DataTable and form components.
Wire the academic year switcher to the current year. Include tenant isolation tests.
Propose the plan first.
```

## Step 3 — Students & admission
```
Read CLAUDE.md, docs/data-model.md (Students), docs/business-rules.md §1 and planning step 3.
Class/section/roll live on enrollments, never on students. Admission creates student,
guardian(s) (reuse existing guardian found by phone), and enrollment in one transaction.
Build admission form, student list with filters, student profile, and CSV/Excel import with
a per-row error report. Propose the plan first.
```

## Step 4 — Staff
```
Read CLAUDE.md, docs/data-model.md (Staff) and planning step 4.
Staff CRUD with optional login + role, class teacher and subject teacher assignment for the
current academic year. Keep it minimal. Propose the plan first.
```

## Step 5 — Attendance
```
Read CLAUDE.md, docs/data-model.md (Attendance), docs/business-rules.md §4 and §5, planning step 5.
Build the take-attendance screen (mobile-first: big touch targets, all present by default),
absent SMS via a queued job using SmsService with a log driver, monthly section sheet,
and staff attendance. Respect teacher permissions. Tests for once-per-day SMS and permissions.
Propose the plan first.
```

## Step 6 — Fees
```
Read CLAUDE.md, docs/data-model.md (Fees), docs/business-rules.md §3 and planning step 6.
Put logic in app/Services/Fees (InvoiceGenerator, PaymentService, NumberGenerator).
Build fee heads, fee structure, waivers, fees:generate command, collect payment screen,
receipt PDF with Browsershot (bn/en), cancel payment, and the three reports.
Write every test listed in business-rules.md §3 FIRST, then implement until they pass.
Propose the plan first.
```

## Step 7 — Exams & results
```
Read CLAUDE.md, docs/data-model.md (Exams), docs/business-rules.md §2 and planning step 7.
Start with app/Services/Exams/GradeCalculator as a pure class with no HTTP code.
Write every test listed in business-rules.md §2 FIRST, then implement until they pass.
Only after that: exam setup, exam subjects with part-wise marks, the mark entry grid
(keyboard-fast, autosave, validation), publish flow with locking and mark_audits,
mark sheet PDF, bulk section PDF (queued), tabulation sheet, result summary, result SMS.
Propose the plan first.
```

## Step 8 — Notices & SMS
```
Read CLAUDE.md, docs/business-rules.md §5 and planning step 8.
Notice CRUD with audience, custom SMS with part count/cost preview, SMS log page with balance.
Propose the plan first.
```

## Step 9 — Dashboard
```
Read CLAUDE.md and planning step 9. Build the school_admin dashboard with the listed
widgets using simple queries (cache 5 minutes). Use a small chart library only if already
installed; otherwise simple bars in CSS. Propose the plan first.
```

## Step 10 — Demo data
```
Read CLAUDE.md, docs/business-rules.md and planning step 10.
Create DemoSchoolSeeder producing exactly the listed data. Names must be realistic
Bangladeshi names in both English and Bangla. Use the real services (InvoiceGenerator,
PaymentService, GradeCalculator) to create invoices, payments and results so the data is
consistent. `php artisan migrate:fresh --seed` must finish in under a minute.
```

## Step 11 — Review
```
Act as a strict reviewer. Go through every route and page for: missing tenant scoping,
missing authorization, N+1 queries, missing translation keys, pages that break on a
375px-wide screen, and PDF Bangla rendering. List problems by severity, then fix them.
```

---

## Useful follow-up prompts

- "Run the tests and fix failures without changing the tests."
- "This is over-engineered for an MVP. Simplify it and remove anything not required by planning.md."
- "Show me the diff summary before continuing."
- "Update docs/planning.md: tick completed items for this step."
- When a school gives you their real mark sheet: "Here is the school's real mark sheet (image). Change the mark sheet PDF layout to match it exactly."
