# School ERP — Project Instructions

Multi-tenant school management SaaS for schools in Bangladesh. One codebase, many schools.
Current goal: a **working MVP demo** of 7 modules, shown to a school owner, then piloted in that school.

Read these before starting any task:
- `docs/planning.md` — build order, current status, acceptance checklist per module
- `docs/data-model.md` — tables and relationships (source of truth for migrations)
- `docs/business-rules.md` — GPA, fees, attendance rules (source of truth for logic)
- `docs/docker.md` — the containerized dev environment (this project runs in Docker)

Update the **Status** table in `docs/planning.md` when a module is finished.

## Stack

- Laravel 12 (PHP 8.3), Inertia.js v2, Vue 3 Composition API with `<script setup lang="ts">`, TypeScript
- Tailwind CSS, the Laravel Vue starter kit's UI components (shadcn-vue). Do not add another UI library.
- MySQL 8 (dev can use MySQL or SQLite; code must work on MySQL)
- Pinia only for state that is truly shared across pages (current school, current academic year, locale). Page data comes from Inertia props.
- `spatie/laravel-permission` with **teams enabled** (team = school)
- PDF: `spatie/browsershot` (headless Chrome) so Bangla text renders correctly. Do NOT use dompdf for anything containing Bangla — it breaks conjuncts.
- Queues: `database` driver for the demo. Redis later.
- Tests: Pest.

## Keep it simple (important)

This is an MVP demo with a deadline. Prefer the simplest thing that works and is correct.
- No repository pattern, no DDD layers, no event sourcing, no microservices, no extra packages unless listed here or approved.
- Controllers stay thin. Put real business logic (GPA, fee dues, promotion) in `app/Services/<Module>/` classes so it can be unit tested.
- Use Form Request classes for validation.
- Do not build features not listed in `docs/planning.md`. If something seems missing, ask instead of inventing it.

## Multi-tenancy rules (never break these)

- Every tenant-owned table has a `school_id` foreign key (indexed).
- Every tenant-owned model uses the `BelongsToSchool` trait (`app/Models/Concerns/BelongsToSchool.php`), which:
  - adds a global scope filtering by the current school, and
  - sets `school_id` automatically on `creating`.
- The current school is resolved from the subdomain by `ResolveSchool` middleware (`{school}.localhost` in dev) and stored in a singleton `App\Support\CurrentSchool`.
- Never query tenant tables with `withoutGlobalScopes()` except in super-admin code under `app/Http/Controllers/Platform/`.
- Route model binding must also respect the scope (a school must get 404 for another school's record).
- Every module needs at least one test proving school A cannot see or edit school B's data.

## Domain rules (short version — full detail in docs/business-rules.md)

- A student's class, section, group and roll belong to an **Enrollment** (one per student per academic year). Never store class/section/roll on `students`.
- Attendance, invoices, marks all reference `enrollment_id`, not `student_id`.
- **Invoices and payments are separate tables.** A payment can be partial; dues = invoice total − waivers − payments.
- Money: `decimal(12,2)`, cast `decimal:2`, never floats in PHP math — use integers of paisa or `bcmath` in services.
- GPA calculation lives only in `App\Services\Exams\GradeCalculator`. Nothing else computes grades.
- Published results are locked. Changing a mark after publishing requires unpublishing, and every mark change is written to `mark_audits`.
- Guardians can have several children. Phone number is NOT unique per student.

## Localization

- UI supports English and Bangla (`lang/en`, `lang/bn`, `vue-i18n` on the frontend). All user-facing strings go through translation keys — no hardcoded text in components.
- Students and guardians have `name_en` and `name_bn`.
- Mark sheets, receipts and certificates can render Bangla digits (helper `bn_digits()` in PHP, `toBnDigits()` in TS).
- Dates shown as `DD/MM/YYYY`. Timezone `Asia/Dhaka`.

## Conventions

- Folder per module on the frontend: `resources/js/pages/<Module>/Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue`; shared parts in `resources/js/components/`.
- Reusable `DataTable.vue` (server-side pagination, search, filters) and `FormField` components — school software is mostly tables and forms, build these once and reuse.
- Types for Inertia props in `resources/js/types/`.
- Route names: `<module>.<action>` e.g. `students.index`.
- Migrations: one per table, foreign keys with `constrained()->cascadeOnDelete()` only where deleting the parent should delete children; otherwise `restrictOnDelete()`.
- Soft deletes on students, staff, guardians, invoices, payments.
- Seeders must be idempotent. `php artisan migrate:fresh --seed` must always work.

## Workflow for each task

1. Read the relevant section of `docs/planning.md` and the docs it points to.
2. **Propose a plan first** (migrations, models, services, routes, pages, tests) and wait for approval before writing code.
3. Build in small steps. After each step run `php artisan test` and `npm run build` (or `vue-tsc --noEmit`).
4. Fix failing tests before moving on. Never delete or weaken a test to make it pass.
5. Summarize what changed and tick the module's checklist in `docs/planning.md`.

## Environment — Docker (important)

The project runs in Docker Compose (`app`, `vite`, `queue`, `scheduler`, `mysql`, `redis`).
**Never run `php`, `composer`, `npm` or `artisan` on the host.** Always run them inside the container:

- `docker compose exec app php artisan <cmd>`
- `docker compose exec app composer <cmd>`
- `docker compose exec app npm <cmd>`
- `docker compose exec app php artisan test` — run tests
- `docker compose exec app php artisan migrate:fresh --seed` — reset with demo data
- `docker compose up -d --build` / `docker compose down` — start / stop

Container-specific rules:
- DB host is `mysql`, Redis host is `redis` — never `127.0.0.1`.
- Browsershot must use `->setChromePath(config('services.chrome.path'))` and `->noSandbox()`; Chromium and Bengali fonts are in the image (see `docs/docker.md`).
- Vite must bind `0.0.0.0` with `hmr.host = 'localhost'` and `watch.usePolling = true`.
- New PHP extensions or system packages mean editing the `Dockerfile` and rebuilding, not installing inside a running container.
