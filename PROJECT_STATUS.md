# Project Status

_Last updated: 2026-09-20_

## Current milestone

**Milestone 12 — Timetable Management: COMPLETE.**

Next up: **Domain Modules** (Milestone 13+) — Attendance, Assessments & Results,
Fees, CBT, Notifications, Portals, Promotion. Not started — do not begin without
picking it up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 · Alpine.js ·
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-20)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 (app) · SQLite `:memory:` (tests) |
| Local mail | Mailpit (`127.0.0.1:1025`, UI `:8025`) — `.env` only, not committed |
| Tests | `php artisan test` — 472 passing |
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
- **M9 — Student Management** (`student-management-complete`) — `docs/student-management.md`.
- **M10 — Guardian / Parent Management** (`guardian-management-complete`) — `docs/guardian-management.md`.
- **M11 — Teacher Management** (`teacher-management-complete`) — `docs/teacher-management.md`.
- **M12 — Timetable Management** (this milestone, `timetable-management-complete`) —
  `docs/timetable-management.md`; see below.

## Delivered in Milestone 12

A tenant-scoped, configurable weekly timetable with server-side conflict
detection and a draft → published lifecycle. Scheduling foundation only — no
attendance, marks, notifications, rooms/facilities module, workload/payroll,
auto-optimisation or student/parent views. Built on the existing `TenantContext`
+ `BelongsToSchool` + `Permission` + `module:timetable` seams and the M11
`TeacherAssignment` — no new mechanism, no new packages, no Redis/queues.

- **Enum** — `App\Enums\TimetableStatus` (`draft` / `published`). `App\Enums\Weekday`
  (M6) reused and gained `all()` (Monday-first) + `short()` helpers — no
  Monday–Friday assumption anywhere.
- **`App\Models\Timetable`** — school-owned. Scoped to one `AcademicSession`
  (fixed at creation) and optionally one `AcademicPeriod`. `status` /
  `published_at` **not** mass-assignable — `publish()` runs behind a conflict
  guard. Multiple timetables per session allowed (history preserved). A
  published timetable can't be deleted (return to draft first).
- **`App\Models\TimetableEntry`** — school-owned **and** timetable-scoped. A
  lesson = level + **arm (required — scheduled per class)** + subject + teacher +
  `weekday` + `start_time`/`end_time` (`HH:MM` strings, **half-open** `[start,
  end)`) + optional free-text `room`. Session/period are **not** on the entry —
  inherited from the parent, so lessons structurally can't cross sessions.
- **Migrations** `2026_09_20_100000` (`timetables`), `…100010`
  (`timetable_entries`) — both `BelongsToSchool`, `school_id`-leading indexes,
  one narrow index per booked resource (teacher / class / room) for overlap
  checks.
- **`App\Support\Timetable\TimetableConflictScanner`** — one indexed self-join
  finding every clashing lesson pair in a timetable; backs the publish guard and
  the warning banner. Per-lesson checks are DB existence queries in the Form
  Request — entries are never loaded into PHP to detect clashes.
- **`App\Http\Controllers\Timetable\{Timetable,TimetableEntry}Controller`** +
  `App\Http\Requests\Timetable\*` (`TimetableRequest`,
  `UpdateTimetableStatusRequest`, `TimetableEntryRequest`) +
  `resources/views/timetables/*` — list (session/status filter + pagination),
  create/edit, the timetable page (desktop **day × time grid**, mobile **stacked
  day list**, filters by class/arm/teacher/weekday, publish controls, conflict
  banner), an Alpine-cascade lesson form, and a **teacher timetable** view.
- **Scheduling rules** (server-side, `TimetableEntryRequest`): end after start;
  arm↔level; subject offered by the level (`level_subject`); an **active M11
  `TeacherAssignment`** must back `(teacher, subject, level)` for the session;
  no teacher / class / room double-booking (half-open overlap, per timetable).
- **Routes** — `/timetables/*` behind `['tenant', 'module:timetable']`, gated
  `timetable.view` (reads) / `timetable.manage` (writes).
- **`Module::Timetable->isAvailable()`** flipped to `true` (still **off by
  default** — a specialised opt-in); **`Module::Timetable` now depends on
  `Module::Academics` and `Module::Staff`**. "Timetable" is a top-level nav item.
- **Permissions** — new `timetable.view` / `timetable.manage`; `timetable.manage`
  + `timetable.view` added to **Principal**, `timetable.view` to **Teacher** and
  **Staff** (the roles with `academics.view`). Bursar has no academic access, so
  no timetable access.
- **Seeder** — Alpha (timetable module already on) gets one published
  "First Term 2025/26" timetable with 8 non-conflicting lessons across
  Mon–Fri, 4 classes, 4 teachers, 4 subjects.
- **Docs** — new `docs/timetable-management.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `scalability.md`,
  `tenancy.md`, `module-activation.md`, `roadmap.md`, `ui-ux-guidelines.md`,
  `CLAUDE.md`, `AGENTS.md`.

## Authorization & tenant controls

- **Two gates on every timetable route:** `module:timetable` (404 when off)
  **and** `->can('timetable.view'|'.manage')`; write Form Requests re-check
  `timetable.manage`. Module gate ≠ permission (a Bursar with the module on still
  can't see timetables — tested).
- `Timetable` / `TimetableEntry` are `BelongsToSchool`; the entry also carries
  `timetable_id`. `school_id` never from input, immutable
  (`TenantMismatchException`); a `school_id` in a payload is ignored (tested).
- `{timetable}` / `{entry}` resolved by tenant-scoped `findOrFail`; the Form
  Requests `abort(404)` on a cross-school route parent before validation. Every
  session / period / level / arm / subject / teacher id in a payload uses
  `Rule::exists(...)->where('school_id', <tenant>)` → generic "invalid", no leak.
  The conflict self-join is filtered by `timetable_id` **and** `school_id`.
  Explicit HTTP + model cross-school isolation tests (view / edit / delete /
  publish / create-with-foreign-entities).

## Database

M12 adds `timetables` and `timetable_entries`. No other schema changes.

## Routes (application, additions in M12)

Tenant-scoped + `module:timetable`, gated `timetable.view` / `timetable.manage`.
14 routes under `/timetables/` (`timetables.*`, `timetables.entries.*`,
`timetables.teacher`).

## Tests

472 passing (was 427 at M11; +45 in M12, M1–M11 intact). New
`tests/Feature/Timetable/*` (+ `TimetableTestCase` base) — `TimetableTest`,
`TimetableEntryTest`, `TimetablePublishTest`, `TimetableStructureTest`:
create / edit / delete / validation / list filters + pagination; the session is
immutable after creation; a draft can be deleted but a published one can't;
scheduling — valid lesson, end-after-start, teacher / class / room overlap,
back-to-back lessons allowed, different-day lessons don't clash, subject↔level,
active teacher assignment required (ended doesn't count), arm↔level, cross-school
ids rejected without leak; publishing — valid draft publishes, empty can't,
conflicting can't, unpublish clears `published_at`, published state visible;
tenant isolation of timetables + entries, ownership immutability, `school_id`
spoof ignored, tenant-safe route resolution; authorization per role, module
disabled → 404, module enabled without permission → 403; half-open `clashingWith`
scope; N+1 guards on the grid, the teacher view and the add-lesson path; bounded
overlap-query count against a busy board. New
`tests/Unit/Enums/TimetableEnumsTest`. `Unit/Enums/ModuleTest` updated (available
list).

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Next milestone: Attendance (per-lesson registers, building on the timetable).
- **Timetable follow-ups** — student/parent timetable views (portals), publish
  notifications, a rooms/facilities module, timetable templates / term cloning,
  named period grids, teacher workload limits.
- **Teacher portal** / **Parent portal** — sign-in + invitations.
- Promotion / graduation workflow; bulk import; documents / photo.
- Audit trail + data-erasure handling for student / guardian / teacher / timetable records.
- Apply the stored `timezone` / `locale` / `date_format` at render time.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
