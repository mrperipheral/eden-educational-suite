# Project Status

_Last updated: 2026-09-17_

## Current milestone

**Milestone 9 — Student Management: COMPLETE.**

Next up: **Domain Modules** (Milestone 10+) — Guardians, Staff, Class rosters &
teacher assignment, Timetable, Attendance, Assessments & Results, Fees, CBT,
Notifications, Portals, Promotion. Not started — do not begin without picking it
up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-17)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 (app) · SQLite `:memory:` (tests) |
| Local mail | Mailpit (`127.0.0.1:1025`, UI `:8025`) — `.env` only, not committed |
| Tests | `php artisan test` — 340 passing |
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
- **M8 — Academic Foundation** (`academic-foundation-complete`) — `docs/academic-foundation.md`.
- **M9 — Student Management** (this milestone, `student-management-complete`) —
  `docs/student-management.md`; see below.

## Delivered in Milestone 9

The tenant-scoped student record + enrollment-history foundation for the later
Guardian / Attendance / Assessment / Results / Fees / Promotion / Portal
modules. Records only — no people beyond students, no placement workflow. Built
on the existing `TenantContext` + `BelongsToSchool` + `Permission` +
`module:students` seams — no new mechanism, no new packages, no Redis/queues.

- **Enums** — `App\Enums\StudentStatus` (active / inactive / withdrawn /
  graduated), `App\Enums\EnrollmentStatus` (active / completed / withdrawn),
  `App\Enums\Gender` (male / female / other).
- **`App\Models\Student`** — school-owned. Minimal PII: name (first / middle /
  last / preferred), DOB, optional gender, `admission_number`
  (`unique(school_id, admission_number)`), admission date, contact/address,
  notes. `status` **not** mass-assignable — model default `active`, changed only
  via a dedicated endpoint. Never hard-deleted. `search()` / `ordered()` scopes.
- **`App\Models\Enrollment`** — school-owned **and** student-scoped. FKs to
  `AcademicSession` (req), `AcademicPeriod` (opt), `AcademicLevel` (req),
  `LevelArm` (opt); `status`, `started_on`, `ended_on`. `makeActive()` (a
  transaction closing any other open enrollment) enforces **one active
  enrollment per student** — the current class, derived, never a column on
  `students`. Not a promotion workflow.
- **Migrations** `2026_09_17_100000` (`students`), `…100010` (`enrollments`) —
  both `BelongsToSchool`, indexes leading with `school_id` (or `student_id`),
  `admission_number` unique per school, a roster index for future modules.
- **`App\Http\Controllers\Student\{Student,Enrollment}Controller`** +
  `App\Http\Requests\Student\*` (`StudentRequest`, `UpdateStudentStatusRequest`,
  `EnrollmentRequest`) + `resources/views/students/*` — list (search + status
  filter + pagination), dedicated create/edit, profile with lifecycle-status
  control + enrollment history, Alpine-cascade enrollment form (session→term,
  level→arm).
- **Routes** — `/students/*` behind `['tenant', 'module:students']`, gated
  `student.view` (reads) / `student.manage` (writes) — M4 permissions,
  previously dormant, now enforced (School Admin + Principal manage; Bursar +
  Teacher + Staff read; Parent / Student → 403).
- **`Module::Students->isAvailable()`** flipped to `true`; **`Module::Students`
  now depends on `Module::Academics`** (enrollment needs the academic
  structure). "Students" is a top-level nav item (permission- + module-filtered).
- **Seeder** — Alpha gets 18 enrolled students + 1 graduated alumnus with a
  completed placement (history retained).
- **Docs** — new `docs/student-management.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `tenancy.md`,
  `module-activation.md`, `roadmap.md`, `ui-ux-guidelines.md`,
  `academic-foundation.md`, `CLAUDE.md`, `AGENTS.md`.

## Authorization & tenant controls

- **Two gates on every student route:** `module:students` (404 when off) **and**
  `->can('student.view'|'.manage')`; write Form Requests re-check `student.manage`.
  Module gate ≠ permission (a Parent still can't see students — tested).
- `Student` / `Enrollment` are `BelongsToSchool`; `Enrollment` also carries
  `student_id`. `school_id` never from input, immutable (`TenantMismatchException`).
  `students.status` not mass-assignable (a `status` in the edit payload is
  ignored — tested).
- Route ids resolved by tenant-scoped `findOrFail`; `EnrollmentRequest`
  `abort(404)`s on a cross-school student/enrollment before validation runs, so
  a cross-school route id never produces an information-leaking validation
  response. Academic ids validated with `Rule::exists(...)->where('school_id', <tenant>)`
  → generic "invalid" for a cross-school id. Level↔arm / session↔period
  consistency checked. Explicit HTTP + model cross-school isolation tests.

## Database

M9 adds `students` and `enrollments`. No other schema changes.

## Routes (application, additions in M9)

Tenant-scoped + `module:students`, gated `student.view` / `student.manage`.
11 routes under `/students/` (`students.*`, `students.enrollments.*`).

## Tests

340 passing (was 305 at M8; +35 in M9, M1–M8 intact). New
`tests/Feature/Student/*` (+ `StudentTestCase` base) — `StudentTest`,
`EnrollmentTest`, `StudentStructureTest`: creation / editing / validation;
admission-number uniqueness per school (and reusable across schools); statuses
via the dedicated endpoint (+ not-mass-assignable); search / pagination;
enrollment creation / editing; one-active-per-student; historical enrollments
retained; session/period/level/arm relations; invalid level/arm & period/session
combinations; cross-school academic ids rejected without leaking; authorization
per role; module-disabled 404s; cross-school read/create/edit isolation;
ownership immutability; tenant-safe route resolution; a list N+1 guard. New
`tests/Unit/Enums/StudentEnumsTest`. `Unit/Enums/ModuleTest` updated (available
list).

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Next milestone: guardians / parents (linkage + screens — M9's student
  `contact_*` fields are a stopgap), then class rosters / teacher assignment.
- Promotion / graduation workflow; bulk student import; student ID / photo /
  documents; medical & emergency info; transfer records.
- Audit trail + data-erasure handling for student records.
- Apply the stored `timezone` / `locale` / `date_format` at render time.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
