# Project Status

_Last updated: 2026-09-21_

## Current milestone

**Milestone 13 — Attendance Management: COMPLETE.**

Next up: **Domain Modules** (Milestone 14+) — Assessments & Results, Fees, CBT,
Notifications, Portals, Promotion. Not started — do not begin without picking it
up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 · Alpine.js ·
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-21)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 (app) · SQLite `:memory:` (tests) |
| Local mail | Mailpit (`127.0.0.1:1025`, UI `:8025`) — `.env` only, not committed |
| Tests | `php artisan test` — 526 passing |
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
- **M12 — Timetable Management** (`timetable-management-complete`) — `docs/timetable-management.md`.
- **M13 — Attendance Management** (this milestone, `attendance-management-complete`) —
  `docs/attendance-management.md`; see below.

## Delivered in Milestone 13

A tenant-scoped daily student-attendance foundation with a draft → submitted
(locked) lifecycle, **independent of the Timetable module**. Built on the
existing `TenantContext` + `BelongsToSchool` + `Permission` + `module:attendance`
seams, the M9 `Enrollment` history and the M11 `TeacherAssignment` — no new
mechanism, no new packages, no Redis/queues.

- **Enums** — `App\Enums\AttendanceStatus` (`present` / `absent` / `late` /
  `excused` — one controlled column, no `is_present` booleans, no minutes-late
  field) and `App\Enums\AttendanceRegisterStatus` (`draft` / `submitted`).
- **`App\Models\AttendanceRegister`** — school-owned. One class's attendance for
  one day: `(academic_session, optional academic_period, academic_level,
  level_arm, attendance_date)`. `status` / `submitted_*` **not** mass-assignable
  — `submit()` / `reopen()` only. `eligibleStudents()` is the enrollment-based
  eligibility rule; `summary()` gives register-level totals from loaded records.
- **`App\Models\AttendanceRecord`** — school-owned **and** register-scoped. One
  student's mark: nullable `status` (null = unmarked), optional `note`,
  `recorded_at` / `recorded_by` stamped when a mark changes. Never deleted for
  historical reasons.
- **Migrations** `2026_09_21_100000` (`attendance_registers`), `…100010`
  (`attendance_records`) — both `BelongsToSchool`, `school_id`-leading indexes,
  `unique(school_id, level_arm_id, attendance_date)` and
  `unique(attendance_register_id, student_id)`.
- **`App\Support\Attendance\AttendanceAuthorizer`** — the class-scoping rule M4
  permissions can't express: `attendance.manage` → any class; `attendance.record`
  only → a class the teacher holds an **active** M11 assignment for. Tenant
  scoped; degrades safely with the Staff module off.
- **`App\Http\Controllers\Attendance\AttendanceRegisterController`** +
  `App\Http\Requests\Attendance\*` (`AttendanceModuleRequest`, `RegisterRequest`,
  `RecordAttendanceRequest`, `SubmitRegisterRequest`) +
  `resources/views/attendance/*` — list (date/session/level/arm/status filter +
  pagination), create (session→period / level→arm Alpine cascade), the
  taking screen (per-student status buttons, bulk "mark all present" / "clear
  all", per-student note, save draft / save & submit, sticky footer, mobile
  first), and the register detail (summary counts, submitted-by indicator,
  read-only roster when locked, reopen control for managers).
- **Eligibility & workflow** — the roster is **snapshotted** at creation (one
  bulk `insert`, one record per then-eligible student). Marks start **unmarked**
  (the safe default — an unmarked student is never counted present); a register
  cannot be submitted while any record is unmarked. A submitted register is
  locked; only an `attendance.manage` holder can `reopen()` it for correction.
- **`Module::Attendance->isAvailable()`** flipped to `true` (on by default);
  **`Module::Attendance` depends on `Module::Academics` + `Module::Students`**
  — **not** `Timetable`. "Attendance" is a top-level nav item.
- **Permissions** — new `attendance.manage` (added to **Principal**;
  School Admin auto). `attendance.view` / `attendance.record` already existed
  from M7 and were left on Teacher / Staff as declared.
- **Seeder** — Alpha gets 10 extra enrolled students, one **submitted** register
  (Primary 1 Gold, mixed marks) and one **draft** register (Primary 2 Gold,
  unmarked).
- **Docs** — new `docs/attendance-management.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `scalability.md`,
  `tenancy.md`, `module-activation.md`, `roadmap.md`, `ui-ux-guidelines.md`,
  `CLAUDE.md`, `AGENTS.md`.

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

## Authorization & tenant controls (M13)

- **Two gates on every `/attendance/*` route:** `module:attendance` (404 when
  off) **and** `->can('attendance.view'|'.record'|'.manage')`; write Form
  Requests re-check via `AttendanceModuleRequest` + `AttendanceAuthorizer`.
  Module gate ≠ permission (a Bursar with the module on still can't see
  attendance — tested).
- `AttendanceRegister` / `AttendanceRecord` are `BelongsToSchool`; the record
  also carries `attendance_register_id`. `school_id` never from input, immutable
  (`TenantMismatchException`); a `school_id` in a payload is ignored (tested).
  The roster bulk `insert` sets `school_id` from `TenantContext` explicitly.
- `{register}` resolved by tenant-scoped `findOrFail`; the write Form Requests
  `abort(404)` on a cross-school route parent before validation. Every session /
  period / level / arm id in the create payload uses
  `Rule::exists(...)->where('school_id', <tenant>)` → generic "invalid", no leak.
  A mark for a student not on the register's snapshotted roster is rejected —
  this blocks cross-school and wrong-class student ids. Explicit HTTP + model
  cross-school isolation tests (view / records / submit / reopen / delete /
  create-with-foreign-class / post-foreign-student).

## Database

M13 adds `attendance_registers` and `attendance_records`. No other schema
changes.

## Routes (application, additions in M13)

Tenant-scoped + `module:attendance`, gated `attendance.view` /
`attendance.record` / `attendance.manage`. 8 routes under `/attendance/`
(`attendance.index|create|store|show|records|submit|reopen|destroy`).

## Tests

526 passing (was 472 at M12; +54 in M13, M1–M12 intact). New
`tests/Feature/Attendance/*` (+ `AttendanceTestCase` base) — `AttendanceRegisterTest`,
`AttendanceEligibilityTest`, `AttendanceRecordTest`, `AttendanceLifecycleTest`,
`AttendanceAuthorizationTest`, `AttendanceStructureTest`, `AttendanceTimetableTest`:
register create / validation / date-vs-session-period / arm-vs-level / duplicate
prevention / list filters + pagination; eligibility — only students enrolled in
the exact class on the date, not-yet-enrolled excluded, enrollment-ended-before
excluded but ended-after included, cross-school student can never be added;
records — present/absent/late/excused, notes + length limit, correction before
lock, invalid status rejected, student-not-on-register rejected, DB duplicate
prevention, full-class one request; lifecycle — submit locks, unmarked blocks
submit, save+submit still needs all marked, locked rejects edits, only a manager
reopens, draft deletable; authorization — all 7 roles + role-less, module off →
404, module on without permission → 403, teacher only for assigned class,
teacher with no Teacher record can't, teacher assigned in another school can't;
timetable — full workflow with Timetable module off, dependency graph excludes
Timetable; tenant isolation of registers + records, ownership immutability,
`school_id` spoof ignored, tenant-safe route resolution; historical correctness —
record survives a later withdrawal, deleting a student cascades records but keeps
the register; N+1 guards on the taking screen, the list and bulk save. New
`tests/Unit/Enums/AttendanceEnumsTest`. `Unit/Enums/ModuleTest` updated
(available list).

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Next milestone: Assessments & Results.
- **Attendance follow-ups** — attendance rate / percentage analytics, term &
  monthly reports, per-lesson (timetable-driven) registers, portal attendance
  views, absence notifications, an attendance-reason taxonomy, half-day records.
- **Timetable follow-ups** — student/parent timetable views (portals), publish
  notifications, a rooms/facilities module, timetable templates / term cloning,
  named period grids, teacher workload limits.
- **Teacher portal** / **Parent portal** — sign-in + invitations.
- Promotion / graduation workflow; bulk import; documents / photo.
- Audit trail + data-erasure handling for student / guardian / teacher / timetable / attendance records.
- Apply the stored `timezone` / `locale` / `date_format` at render time.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
