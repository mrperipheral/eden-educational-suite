# Project Status

_Last updated: 2026-09-10_

## Current milestone

**Milestone 7 — Feature / Module Activation: COMPLETE.**

Next up: **Domain Modules** (Milestone 8+) — Academic Management, Students,
Guardians, Staff, Timetable, Attendance, Assessments, Results, Fees, Learning
Materials, CBT, Notifications, Portals. Not started — do not begin without
picking it up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-10)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 (app) · SQLite `:memory:` (tests) |
| Tests | `php artisan test` — 264 passing |
| Build | `npm run build` — passing |
| Formatting | `vendor/bin/pint --test` — passing |

## Delivered

- **M1 — Platform Foundation** (`foundation-complete`).
- **M2 — Authentication & User Foundation** (`authentication-complete`) —
  `docs/authentication.md`.
- **M3 — Multi-School / Strict Tenant Isolation** (`multischool-foundation-complete`) —
  `docs/tenancy.md`.
- **M4 — Roles & Permissions** (`roles-permissions-complete`) —
  `docs/authorization.md`.
- **M5 — School Onboarding** (`school-onboarding-complete`) — `docs/onboarding.md`.
- **M6 — School Settings & Configuration** (`school-settings-complete`) —
  `docs/school-settings.md`.
- **M7 — Feature / Module Activation** (this milestone,
  `feature-activation-complete`) — `docs/module-activation.md`; see below.

## Delivered in Milestone 7

Per-school enable/disable of the application's feature modules, built on the
existing M3/M4 tenant + permission architecture. No new authorization or tenancy
mechanism, no new packages, no Redis/queues. M7 ships the **activation system** —
not the modules, which are each a later milestone.

- **`App\Enums\Module`** — the code-defined catalogue: 14 modules (Academic
  Management, Student Management, Parent/Guardian Management, Teacher/Staff
  Management, Timetable, Attendance, Assessments, Results & Report Cards, Fees &
  Payments, Learning Materials, CBT, Notifications, Parent Portal, Student
  Portal). Each case has `label()`, `description()`, `group()`,
  `dependencies()`, `enabledByDefault()`, `isAvailable()` (all `false` in M7 —
  each domain milestone flips its own), plus `grouped()` / `defaults()` helpers.
- **Migration** `2026_09_15_100000_create_school_modules_table` —
  `school_modules` (`school_id`, `module` string(40) uncast, `enabled` bool),
  `unique(['school_id','module'])` (also the lookup index). **Override-only**: a
  row exists only where a school departs from a default, so a new school writes
  nothing.
- **`App\Models\SchoolModule`** — `BelongsToSchool`; `$fillable` = `module`,
  `enabled` only (never `school_id`); `enabled` cast bool, `module` left a plain
  string so a retired identifier can't break a page.
- **`App\Support\Modules\SchoolModules`** — request-scoped resolver
  (`AppServiceProvider::scoped`). `enabled(Module)` / `states()` / `set()`. Reads
  the override rows **once per request** (cache keyed by active school id, so it
  self-heals if the instance outlives a context), tenant-scoped, fails closed.
- **`App\Http\Controllers\SchoolModuleController`** + `UpdateSchoolModuleRequest`
  — `GET /settings/school/modules` (`school.settings.view`),
  `PATCH /settings/school/modules/{module}` (`school.settings.update`). Unknown
  `{module}` → 404. Single-level dependency validation (enable needs deps on;
  disable blocked by enabled dependents) → `module` validation error.
- **Reuse seam for future modules** — `App\Http\Middleware\EnsureModuleEnabled`
  registered as the **`module:`** alias (404s when the module is off; runs after
  `tenant`), and the **`@module('…')`** Blade directive. Both check the flag
  only — activation grants no permission.
- **View** — `resources/views/settings/school/modules.blade.php`: modules grouped
  by area, each row with name / description / Available|Planned badge /
  Enabled|Disabled badge / dependency list / one-button toggle form. Read-only
  (no buttons) for `school.settings.view`-only roles. New "Modules" tab in
  `settings/school/_nav.blade.php`.
- **Seeder** — Alpha Academy overrides two modules (Timetable on, Fees off);
  everything else (and all of Beta) uses catalogue defaults.
- **Docs** — new `docs/module-activation.md`; updated `architecture.md`,
  `security.md`, `database-design.md`, `authorization.md`, `roadmap.md`,
  `ui-ux-guidelines.md`, `CLAUDE.md`, `AGENTS.md`.

## Explicitly NOT done (by design)

The domain modules themselves (Academic Management, Students, Guardians, Staff,
Timetable, Attendance, Assessments, Results, Fees, Learning Materials, CBT,
Notifications, Portals) · preset/bundle activation · disable-with-cascade or
"this will hide X" confirmation flow · per-module configuration sub-pages ·
activation audit trail · plan-based module entitlements (subscription/billing) ·
everything from the M5/M6 "NOT done" lists.

## Database

Milestone 7 adds `school_modules` (school-owned, `unique(school_id, module)`,
override-only). No other schema changes.

## Routes (application, additions in M7)

Tenant-scoped (`auth · verified · active · tenant`):

| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/settings/school/modules` | `settings.school.modules.edit` | `school.settings.view` |
| PATCH | `/settings/school/modules/{module}` | `settings.school.modules.update` | `school.settings.update` |

New middleware alias: `module:<name>` (`EnsureModuleEnabled`) — not yet used by
any route; the seam for domain milestones.

## Tests

264 passing (was 231 at M6; +33 in M7, M1–M6 intact). New:
`tests/Unit/Enums/ModuleTest` (catalogue integrity, acyclic dependency graph,
default/dependency consistency), `tests/Feature/Modules/SchoolModulesTest`
(resolver: defaults, overrides, isolation, fail-safe, one-query-per-request),
`tests/Feature/Modules/ModuleActivationMiddlewareTest` (the `module:` gate),
`tests/Feature/Settings/SchoolModulesTest` (page + toggle: auth, view-only,
enable/disable, validation, dependencies, unknown id, cross-school isolation,
platform-admin context, activation grants no permission).

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Next milestone (Domain Modules): each module's routes carry both
  `module:<name>` and their `->can('…')`; each module flips its
  `App\Enums\Module::isAvailable()` to `true` when it ships something usable.
- Apply the stored `timezone` / `locale` / `date_format` at render time.
- Invitations & brand-new-account onboarding; school suspension.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
- Audit logging of settings / module / membership / role / provisioning changes.
- Logo: virus scanning, object-storage (`s3`) delivery in production.
