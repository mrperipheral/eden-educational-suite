# School Management Platform — Development Guide

> **Development-only documentation.** This file (and everything under `docs/`) is
> guidance for people and coding agents working on the codebase. It is **never** a
> runtime dependency: no application code reads it, and the app must build, boot
> and pass tests with these files absent.

## What this project is

A production-quality, multi-school **School Management SaaS**. Initial target:
private nursery, primary and secondary schools in Nigeria. The architecture stays
flexible enough to support other institution types later.

The product is **school management and portals only**. Explicitly **out of scope**:
public school websites, website builder / themes / engine, public school pages,
public content management.

## Tech stack

| Area        | Choice |
|-------------|--------|
| Language    | PHP 8.3+ |
| Framework   | Laravel 13 |
| Database    | MySQL / MariaDB (one shared DB, logical tenant isolation) |
| Views       | Blade |
| CSS         | Tailwind CSS v4 (`@tailwindcss/vite`, config lives in `resources/css/app.css`) |
| JS          | Alpine.js (the only JS framework — no React / Vue / Inertia without an explicit decision in `docs/architecture.md`) |
| Build       | Vite |
| Tests       | PHPUnit (`php artisan test`) |
| Formatting  | Laravel Pint (`vendor/bin/pint`) |

## Local setup

PHP / Composer / Node are provided here by Laragon; `php`, `composer` and `node`
may not be on `PATH` in every shell. Laragon paths:
`C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64`, `C:\laragon\bin\composer`,
`C:\laragon\bin\nodejs\node-v22`.

```sh
composer install
npm install
cp .env.example .env        # then set DB_* and run key:generate
php artisan key:generate
php artisan migrate
npm run build               # or: npm run dev
php artisan test
```

## Project conventions

See `docs/architecture.md` for the full rationale. In short:

- **Controllers** (`app/Http/Controllers`) — thin. Resourceful where it fits
  (`index/create/store/show/edit/update/destroy`). No business logic beyond
  orchestration; push anything non-trivial into a service.
- **Form Requests** (`app/Http/Requests`) — every write action validates through a
  Form Request. Authorization that depends on the request lives in `authorize()`.
- **Policies** (`app/Policies`) — model authorization. Controllers call
  `authorize()` / `Gate`. **Frontend hiding is never authorization.**
- **Services** (`app/Services`) — multi-step or cross-model business operations.
  Plain classes, constructor-injected. Do not create a service/repository layer
  for simple CRUD.
- **Models** (`app/Models`) — guard mass assignment (`$fillable` or the PHP 8
  `#[Fillable]` attribute, as `User` does). Casts via `casts()`.
- **Tenancy** (see `docs/tenancy.md`) — every school-owned model
  `use App\Support\Tenancy\Concerns\BelongsToSchool` (adds the `SchoolScope`
  global scope + stamps/locks `school_id`). **Never** add `school_id` to
  `$fillable`, never write a manual `where('school_id', …)`, never read
  `school_id` from the request. The active school is
  `App\Support\Tenancy\TenantContext` (request-scoped); routes that touch tenant
  data get the `tenant` middleware. Cross-tenant work is explicit
  (`TenantContext::runWithoutScope()`). Every school-owned migration leads its
  lookup indexes with `school_id`.
- **Enums** (`app/Enums`) — closed value sets backed by string columns
  (`UserStatus`). Add behaviour to the enum, not `match` ladders in callers.
- **Middleware** (`app/Http/Middleware`) — cross-cutting request guards; register
  aliases in `bootstrap/app.php`.
- **Views** — pages in `resources/views/<area>/`, layouts and reusable UI as Blade
  components in `resources/views/components/`. Signed-in pages use
  `<x-layouts.authenticated>`; pre-auth pages use `<x-layouts.guest>`.
- **Auth** — native Laravel, no starter package. Flow routes in `routes/auth.php`;
  see `docs/authentication.md`. Passwords via `Password::defaults()`.
- **Authorization** (see `docs/authorization.md`) — check **permissions** through
  the Gate (`$user->can('member.view')`, `@can`, `->can()` route middleware,
  `$this->authorize(...)`), **never role names**. Permissions are
  `App\Enums\Permission`; roles (`App\Enums\Role`) are static bundles assigned per
  school on `school_user.role`. Every permission check is composed with
  `TenantContext` (applies only inside the school in context). No `Gate::before`.
  Role assignment / adding members goes through `User::canGrantRole()` (tier
  guard — no escalation).
- **Platform screens** (`app/Http/Controllers/Platform/`, `/admin/*`) are
  **not** tenant-scoped — platform-admin only via `SchoolPolicy`. School-owned
  data is only ever created/edited inside a tenant context. Onboarding:
  `docs/onboarding.md`.
- **Tests** — feature tests for every route and each authorization boundary; unit
  tests for services, enums and value objects. Tenant isolation gets explicit
  cross-tenant "cannot see / cannot touch" tests once schools exist. Tests that
  render views call `$this->withoutVite()`.

## Guardrails

- Server-side authorization, validation and CSRF on every state change.
- No secrets in the repo. `.env` is git-ignored; `.env.example` carries
  placeholders only.
- No artificial tenant/school limit anywhere.
- Design for scale (indexes, pagination, no N+1, caching, queues later) but do not
  build infrastructure the current milestone does not need.

## Milestones

Tracked in `PROJECT_STATUS.md` and `docs/roadmap.md`. **Milestones 1–25 (Platform
Foundation, Authentication, Multi-School Tenant Isolation, Roles & Permissions,
School Onboarding, School Settings & Configuration, Feature / Module Activation,
Academic Foundation, Student Management, Guardian / Parent Management, Teacher
Management, Timetable Management, Attendance Management, Assessment &
Assignments, Results & Report Cards, Parent Portal, Student Portal,
Communication & Notification Foundation, Fees & Fee Management, Online Fee
Payment / Paystack, Promotion & Graduation, Learning Materials, CBT / Online
Examinations, Question Bank, Entry / Placement Assessment) are complete.** School settings: `docs/school-settings.md`; module activation:
`docs/module-activation.md`; academic structure:
`docs/academic-foundation.md`; students + enrollment:
`docs/student-management.md`; guardians + student ↔ guardian links:
`docs/guardian-management.md`; teachers + teaching assignments:
`docs/teacher-management.md`; timetables + lessons + conflict rules:
`docs/timetable-management.md`; attendance registers + records + eligibility:
`docs/attendance-management.md`; assessment categories + assessments + scores +
assignments: `docs/assessment-management.md`; grading/weighting schemes +
result runs + report cards: `docs/results-report-cards.md`; the parent-facing
read-only window onto a child's own data: `docs/parent-portal.md`; the
student-facing read-only window onto their own data: `docs/student-portal.md`;
the Communication Hub, announcements and in-app notification foundation:
`docs/communication.md`; fee categories/structures/charges, manual payments
and the fee statement: `docs/fees.md`; online fee payment through Paystack:
`docs/paystack.md`; promoting students between sessions/classes and
graduating them: `docs/promotion.md`; uploading and accessing class
learning materials: `docs/learning-materials.md`; online examinations —
exam lifecycle, snapshots, timed attempts, marking, result release:
`docs/cbt.md`; the reusable Question Bank — fields, lifecycle,
authorization, exam-attach compatibility: `docs/question-bank.md`; Entry /
Placement Assessment — recording (not deciding) an assessment for a
prospective or newly admitted student: `docs/entry-placement-assessment.md`.
Do not start any further domain module (reporting, …) without picking up
the next milestone explicitly.

Module activation is **configuration, not authorization**: a domain route checks
both its `App\Enums\Module` flag (`module:` middleware / `@module`) **and** its M4
permission — enabling a module grants nothing. Tenant-owned route ids are
resolved by tenant-scoped `findOrFail` in the controller, not route-model-bound.

A **person's domain record is separate from their `User` account**: a `Teacher`
(M11) — and future staff-like records — is a professional record first, with an
optional nullable `user_id` linked only to an **existing member of the active
school**, set via a dedicated endpoint (never mass-assigned, never an auto-created
login). Lifecycle `status` columns stay out of `$fillable` and change only
through their own endpoint, as `Student` (M9) and `Teacher` (M11) do.
