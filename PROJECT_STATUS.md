# Project Status

_Last updated: 2026-09-16_

## Current milestone

**Milestone 8 — Academic Foundation: COMPLETE.**

Next up: **Domain Modules** (Milestone 9+) — Students & Guardians, Staff,
Enrolment / class membership, Teacher assignment, Timetable, Attendance,
Assessments & Results, Fees, CBT, Notifications, Portals. Not started — do not
begin without picking it up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-16)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 (app) · SQLite `:memory:` (tests) |
| Tests | `php artisan test` — 305 passing |
| Build | `npm run build` — passing |
| Formatting | `vendor/bin/pint --test` — passing |

## Delivered

- **M1 — Platform Foundation** (`foundation-complete`).
- **M2 — Authentication & User Foundation** (`authentication-complete`).
- **M3 — Multi-School / Strict Tenant Isolation** (`multischool-foundation-complete`) — `docs/tenancy.md`.
- **M4 — Roles & Permissions** (`roles-permissions-complete`) — `docs/authorization.md`.
- **M5 — School Onboarding** (`school-onboarding-complete`) — `docs/onboarding.md`.
- **M6 — School Settings & Configuration** (`school-settings-complete`) — `docs/school-settings.md`.
- **M7 — Feature / Module Activation** (`feature-activation-complete`) — `docs/module-activation.md`.
- **M8 — Academic Foundation** (this milestone, `academic-foundation-complete`) —
  `docs/academic-foundation.md`; see below.

## Delivered in Milestone 8

The configurable academic structure every later domain module builds on.
Structure only — no students, guardians, staff, timetable, attendance,
assessments, results, grading, promotion or CBT. Built on the existing
`TenantContext` + `BelongsToSchool` + `Permission` + `module:academics` seams —
no new authorization or tenancy mechanism, no new packages, no Redis/queues.

- **`App\Models\AcademicSession`** (M5, extended) — the year; one `is_current`
  per school; never deleted. Gains `periods()` / `currentPeriod()`.
- **`App\Models\AcademicPeriod`** (new) — a term / semester within a session.
  `BelongsToSchool` **and** scoped to its session. `name`, `starts_on`/`ends_on`,
  `position`, `is_active`, `is_current` (one per session, `makeCurrent()`
  demotes siblings + activates). **Any number of periods** — no "three terms".
- **`App\Models\AcademicLevel`** (new) — class / year group. `name`, `code`,
  `position`, `is_active`; `arms()` + `subjects()`. No level names hard-coded.
- **`App\Models\LevelArm`** (new) — stream within a level. `BelongsToSchool` +
  scoped to its level. `name`, `code`, `position`, `is_active`.
- **`App\Models\Subject`** (new) — `name`, `code`, `description`, `position`
  (soft), `is_active`. No subject list hard-coded.
- **`level_subject`** (new) — which subjects a level offers; carries `school_id`,
  written from `TenantContext` during the sync. The only cross-model link M8
  ships (no teacher / timetable / enrolment).
- **Migrations** `2026_09_16_100000`–`…100040` — 5 tables, all `BelongsToSchool`,
  indexes leading with `school_id` / a tenant-scoped parent id, uniqueness scoped
  to school / session / level.
- **`App\Http\Controllers\Academic\{Session,Period,Level,Arm,Subject}Controller`**
  + `App\Http\Requests\Academic\*` + `resources/views/academic/*` (Sessions &
  terms · Levels & arms · Subjects sub-nav). Inline "add" cards for editors,
  dedicated `edit` pages, `show` pages for children, `?q=` subject search,
  pagination, empty states, `is_current` / `Inactive` badges.
- **Routes** — `/academic/*` behind `['tenant', 'module:academics']`, gated
  `academics.view` (reads) / `academics.manage` (writes). Sessions **moved here**
  from `settings/academic-sessions` (M5) and re-gated from `school.settings.*`.
- **`Module::Academics->isAvailable()`** flipped to `true`. "Academic" is a
  top-level nav item (permission- + module-filtered). Dashboard onboarding
  checklist's session step points to the new route and is module-gated.
- **Seeder** — Alpha gets a current session with 3 terms, 5 levels × 2 arms,
  6 subjects, and every level linked to the subject set.
- **Docs** — new `docs/academic-foundation.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `tenancy.md`,
  `module-activation.md`, `onboarding.md`, `roadmap.md`, `ui-ux-guidelines.md`,
  `CLAUDE.md`, `AGENTS.md`.

## Authorization & tenant controls

- **Two gates on every academic route:** `module:academics` (404 when off) **and**
  `->can('academics.view'|'.manage')`; Form Requests re-check `academics.manage`.
- School Admin + Principal manage; Teacher + Staff read-only; Bursar / Parent /
  Student / role-less → 403. Module gate and permissions are independent.
- Every model `BelongsToSchool`; child models also carry `school_id`. `school_id`
  never from input, immutable (`TenantMismatchException`). Route ids resolved by
  tenant-scoped `findOrFail` (not route-model-bound) → cross-school id 404s. Form
  Requests resolve parent ids tenant-scoped so a cross-school id 404s rather than
  leaking a validation error. `level_subject` sync validates subject ids against
  the active school. Explicit cross-school tests for all six of these.

## Database

M8 adds `academic_periods`, `academic_levels`, `level_arms`, `subjects`,
`level_subject`. `academic_sessions` unchanged. No other schema changes.

## Routes (application, additions in M8)

Tenant-scoped + `module:academics`, gated `academics.view` / `academics.manage`.
23 routes under `/academic/` (`academic.{sessions,periods,levels,arms,subjects}.*`).
The M5 `/settings/academic-sessions` routes and `academic-sessions.*` names are
**removed** — replaced by `/academic/sessions` / `academic.sessions.*`.

## Tests

305 passing (was 264 at M7; +41 in M8, M1–M7 intact). New
`tests/Feature/Academic/*` — `AcademicSessionTest`, `AcademicPeriodTest`,
`AcademicLevelTest`, `LevelArmTest`, `SubjectTest`, `AcademicStructureTest` (+ an
`AcademicTestCase` base): creation / editing / validation / uniqueness,
one-current-per-scope, configurable period count, relationships, level↔subject
sync + its cross-school rejection, authorization per role, module-disabled 404s,
cross-school isolation, ownership immutability, route-binding isolation.
`Unit/Enums/ModuleTest` and `Feature/Onboarding/OnboardingChecklistTest` updated
for the moved route + available module.

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Next milestone: students / guardians / staff, class & arm membership, teacher →
  subject/class assignment — each behind its `module:*` + permissions.
- Period-within-session bounds & non-overlap validation; bulk import / cloning a
  previous year's structure; hard delete / archival of academic entities.
- Apply the stored `timezone` / `locale` / `date_format` at render time.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
- Audit logging of academic / settings / module / membership changes.
