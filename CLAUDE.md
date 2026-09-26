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

- Laravel 12 (PHP 8.4), Inertia.js v2, Vue 3 Composition API with `<script setup lang="ts">`, TypeScript
- Tailwind CSS, the Laravel Vue starter kit's UI components (shadcn-vue). Do not add another UI library.
- MySQL 8 everywhere — dev uses `school_erp`, tests use `school_erp_test` (set in `phpunit.xml`). No SQLite.
- Pinia only for state that is truly shared across pages (current school, current academic year, locale). Page data comes from Inertia props.
- `spatie/laravel-permission` with **teams enabled** (team = school)
- Excel/CSV import: `rap2hpoutre/fast-excel` (see `StudentImporter`).
- PDF: `spatie/browsershot` (headless Chrome) so Bangla text renders correctly. Do NOT use dompdf for anything containing Bangla — it breaks conjuncts.
- Queues, cache and sessions: `database` driver for the demo. Redis later (the image has no phpredis extension yet).
- Tests: Pest (`pestphp/pest`, `pestphp/pest-plugin-laravel`) for PHP; Vitest for TS helpers (`npm test`, files named `*.test.ts` next to the code).
- i18n: `vue-i18n` (frontend strings). `laravel-lang/lang` (dev) supplies Laravel's own messages in Bangla — validation, auth, pagination — in `lang/bn`. Language is a per-session choice (বাংলা/EN toggle), not a URL prefix; don't add `mcamara/laravel-localization`.
- Bangla UI font: `@fontsource/hind-siliguri` (self-hosted, Bengali subset imported in `resources/js/app.ts`). PDFs use Noto Sans Bengali from the Docker image.

## Keep it simple (important)

This is an MVP demo with a deadline. Prefer the simplest thing that works and is correct.
- No repository pattern, no DDD layers, no event sourcing, no microservices, no extra packages unless listed here or approved.
- Controllers stay thin. Put real business logic (GPA, fee dues, promotion) in `app/Services/<Module>/` classes so it can be unit tested.
- A service refuses a rule-breaking action by throwing `App\Exceptions\BusinessRuleException` with a translated message; it becomes an error toast. Controllers delete with `$this->destroyOrRefuse($model)`, which turns a foreign-key refusal into an "in use" message.
- Use Form Request classes for validation.
- Do not build features not listed in `docs/planning.md`. If something seems missing, ask instead of inventing it.

## Laravel skills (`.claude/skills/laravel-*`)

The skills are general-purpose. **This file wins wherever they disagree** — do not half-adopt their architecture.

Ignore:
- `laravel-patterns` layering: no `app/Domain/`, Repositories, repository interfaces, Query classes or Actions. Logic goes in `app/Services/<Module>/`; simple CRUD uses Eloquent in the controller.
- `laravel-rest-api` API rules: no JSON Resources, no "never query in a controller" rule, no single-action controllers by default. This is an Inertia app — controllers return `Inertia::render()` with props. List queries may live in the controller or a model scope.
- `laravel-testing` repository fakes and SQLite config. Tests run on MySQL (`school_erp_test`).
- `laravel-eloquent` recommended packages (`tpetry/laravel-query-expressions`, `indexzer0/eloquent-filtering`, `rap2hpoutre/fast-excel`) unless approved.

Use:
- One Policy per model; exactly one authorization check per route.
- Scoped route model binding for nested routes; cross-school access returns 404 (see Multi-tenancy rules).
- `with()` for every relation a page reads; `Model::shouldBeStrict()` outside production so N+1 fails the tests.
- `DB::transaction()` for multi-step writes; jobs and events dispatched after commit.
- Idempotent jobs with `ShouldBeUnique` for SMS and bulk PDFs. The cache store is `database`, so no `Cache::tags()`.

## Multi-tenancy rules (never break these)

- Every tenant-owned table has a `school_id` foreign key (indexed).
- Every tenant-owned model uses the `BelongsToSchool` trait (`app/Models/Concerns/BelongsToSchool.php`), which:
  - adds a global scope filtering by the current school, and
  - sets `school_id` automatically on `creating`.
- The current school is resolved from the subdomain by `ResolveSchool` middleware (`{school}.localhost` in dev) and stored in a singleton `App\Support\CurrentSchool`.
- With no current school the scope matches nothing, and creating a tenant row throws. Console commands and seeders set the school first: `app(CurrentSchool::class)->set($school)` (this also sets the spatie team).
- `User` is the one exception: it does not use the trait (platform admins have no school, and auth loads the user before the school is known). Login filters by `school_id`, and `ResolveSchool` logs out a user whose `school_id` doesn't match. Any user list must filter by the current school explicitly.
- Never query tenant tables with `withoutGlobalScopes()` except in super-admin code under `app/Http/Controllers/Platform/`.
- Route model binding must also respect the scope (a school must get 404 for another school's record).
- Validation rules bypass the scope: never use plain `exists:`/`unique:` on tenant tables. Use `existsInSchool()` / `uniqueInSchool()` from `App\Http\Requests\Concerns\SchoolRules`.
- Every module needs at least one test proving school A cannot see or edit school B's data.
- School routes go inside the `Route::middleware('school')` group in `routes/web.php` (404 on the bare domain).
- Tests: `$this->inSchool($school)` sends requests to that school's subdomain and makes it current; `$this->schoolUser()` creates a user in a (new) school; `$this->forSchool($other, fn () => ...)` creates another school's records. See `tests/Feature/Tenancy/` and the `resources` dataset in `tests/Feature/Setup/SetupCrudTest.php`.

## Roles & permissions — spatie/laravel-permission only

- All roles and permissions go through `spatie/laravel-permission` (teams on, team key `school_id`). No custom roles/permissions tables, no role enums or `role` columns, no `if ($user->user_type === ...)` authorization checks.
- `users.user_type` (`platform_admin` | `staff` | `guardian`) says what kind of account it is, not what it may do. `platform_admin` has no access to school data (business-rules §6) — no `Gate::before` bypass.
- Roles: `school_admin`, `accountant`, `teacher`, `guardian` (see `docs/data-model.md`). Create them once with `school_id = null` so every school shares the definitions; assign them to users per school.
- Permissions are named `<module>.<action>`, matching route names (`students.create`, `fees.collect`, `marks.enter`). Seed roles and permissions in `RolesAndPermissionsSeeder` using `firstOrCreate` (idempotent), then call `app(PermissionRegistrar::class)->forgetCachedPermissions()`.
- Check **permissions, not roles**: `$user->can('fees.collect')` inside Policies, or `can:` route middleware — one check per route. `hasRole()` is only for the few places that are truly role-specific (e.g. "teachers see only their assigned subjects").
- `ResolveSchool` middleware calls `setPermissionsTeamId($school->id)` before any authorization runs. Tests that act as a school user must set it too.
- The frontend receives `auth.permissions` (array of names) as a shared Inertia prop to hide buttons and menu items. That is cosmetic — the server check is what protects the route.

## Domain rules (short version — full detail in docs/business-rules.md)

- A student's class, section, group and roll belong to an **Enrollment** (one per student per academic year). Never store class/section/roll on `students`.
- Attendance, invoices, marks all reference `enrollment_id`, not `student_id`.
- **Invoices and payments are separate tables.** A payment can be partial; dues = invoice total − waivers − payments.
- Money: `decimal(12,2)`, cast `decimal:2`, never floats in PHP math — use integers of paisa or `bcmath` in services.
- GPA calculation lives only in `App\Services\Exams\GradeCalculator`. Nothing else computes grades.
- Published results are locked. Changing a mark after publishing requires unpublishing, and every mark change is written to `mark_audits`.
- Guardians can have several children. Phone number is NOT unique per student. Phones are stored normalized (`App\Support\Phone::normalize`, 11 digits `01…`); siblings share a guardian found by phone.
- A student's personal photo and other private files go on the `local` disk and are served through an authorized route, never `public`.

## Localization

- UI supports English and Bangla (`lang/en`, `lang/bn`, `vue-i18n` on the frontend). All user-facing strings go through translation keys — no hardcoded text in components.
- Students and guardians have `name_en` and `name_bn`.
- Mark sheets, receipts and certificates can render Bangla digits (helper `bn_digits()` in PHP, `toBnDigits()` in TS).
- Dates shown as `DD/MM/YYYY` via `formatDate()` (`resources/js/lib/format.ts`, Bangla digits in bn). Timezone `Asia/Dhaka`. Cast date columns as `immutable_date:Y-m-d` so JSON carries the plain date, not a UTC-shifted timestamp.

## Conventions

- Folder per module on the frontend: `resources/js/pages/<Module>/Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue`; shared parts in `resources/js/components/`.
- Reusable `DataTable.vue` (server-side pagination, search, filters) and `FormField.vue` — school software is mostly tables and forms, reuse them. Controllers return `->paginate()->withQueryString()` plus `'filters' => $request->only(...)`. Use `NativeSelect` (`components/ui/native-select`) for dropdowns. Small lookup lists edit in a dialog with `useCrudForm()`; see `pages/Setup/`.
- The academic year a screen shows: `app(CurrentAcademicYear::class)->get()` (the user's top-bar choice, else the school's current year). The school's current year changes only in Setup › Academic years.
- Sidebar items live in `resources/js/navigation.ts`, each with the permission that guards its routes. Use `usePermissions().can()` to hide buttons.
- Frontend strings: `resources/js/i18n/en.json` and `bn.json` (same keys — a Vitest test checks). Breadcrumb and nav `title`s are translation keys. Backend messages: `lang/en`, `lang/bn` — add Bangla names for new form fields under `attributes` in `lang/bn/validation.php`. `php artisan lang:update` refreshes the package files; check `git diff lang/` afterwards for our overrides (e.g. `auth.failed`). Pint skips `lang/` (see `pint.json`).
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
