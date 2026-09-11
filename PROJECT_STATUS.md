# Project Status

_Last updated: 2026-09-22_

## Current milestone

**Milestone 14 — Assessment & Assignments: COMPLETE.**

Next up: **Domain Modules** (Milestone 15+) — Results & Report Cards, Fees, CBT,
Notifications, Portals, Promotion. Not started — do not begin without picking it
up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 · Alpine.js ·
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-22)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 (app) · SQLite `:memory:` (tests) |
| Local mail | Mailpit (`127.0.0.1:1025`, UI `:8025`) — `.env` only, not committed |
| Tests | `php artisan test` — 615 passing |
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
- **M13 — Attendance Management** (`attendance-management-complete`) — `docs/attendance-management.md`.
- **M14 — Assessment & Assignments** (this milestone, `assessment-assignments-complete`) —
  `docs/assessment-management.md`; see below.

## Delivered in Milestone 14

A configurable, tenant-scoped assessment and assignment foundation — the source
data for M15 Results & Report Cards. Built on the existing `TenantContext` +
`BelongsToSchool` + `Permission` + `module:assessments` seams, the M9
`Enrollment` history and the M11 `TeacherAssignment` — no new mechanism, no new
packages, no Redis/queues.

- **Enums** — `App\Enums\AssessmentStatus` (`draft` / `published` / `locked`),
  `App\Enums\AssignmentStatus` (`draft` / `published` / `closed`),
  `App\Enums\AssignmentSubmissionStatus` (`pending` / `submitted` / `late` /
  `exempt`).
- **`App\Models\AssessmentCategory`** — school-configured category (name / code /
  position / active), unique per school. Seeded examples (Classwork / Homework /
  Test / Examination) are fully editable. Managed at `/assessments/categories`
  (`assessment.manage`).
- **`App\Models\Assessment`** — school-owned; academic context (session +
  period + level + arm + subject) **fixed at creation**. `status` /
  `published_at` / `locked_*` / `created_by` **not** mass-assignable. Optional
  `assignment_id` link (no calculation). `eligibleStudents()` (shared
  `HasClassRoster` trait) + `summary()`. Lifecycle `publish()` / `unpublish()` /
  `lock(User)` / `unlock()`.
- **`App\Models\AssessmentScore`** — school-owned **+** assessment-scoped.
  Nullable `score` (`decimal(6,2)`, null = not entered), `comment`,
  `recorded_at` / `recorded_by`. Never deleted. **No** grade / percentage /
  rank stored.
- **`App\Models\Assignment`** — school-owned; same context rules + `due_on >=
  assigned_on`, both in the session. `teacher_id` owner (from the creator's
  `Teacher` record), `created_by`, optional `max_score`. Carries **no scores**.
  Lifecycle `publish()` / `unpublish()` / `close()` / `reopen()`.
- **`App\Models\AssignmentSubmission`** — completion tracking only; `status`
  defaults to `pending`, `submitted_on`, `remark`. No file upload / portal.
- **Migrations** `2026_09_22_100000`–`100040` (categories, assignments,
  assessments, assessment_scores, assignment_submissions) — all
  `BelongsToSchool`, `school_id`-leading indexes, `unique(assessment_id,
  student_id)` / `unique(assignment_id, student_id)`, **no** global uniqueness on
  assessments (multiple of a category on different dates are legitimate).
- **`App\Support\Assessment\AssessmentAuthorizer`** — `assessment.manage` → any
  class + subject; `assessment.record` only → a `(level, subject)` the teacher
  holds an **active** M11 assignment for. Tenant scoped; degrades safely with
  the Staff module off.
- **`App\Http\Controllers\Assessment\{AssessmentCategory,Assessment,AssessmentScore,Assignment,AssignmentSubmission}Controller`**
  + `App\Http\Requests\Assessment\*` (11 requests) +
  `resources/views/{assessments,assignments}/*` (13 views) — list (filters +
  pagination), Alpine-cascade create forms, draft-only edit forms, detail with
  lifecycle controls, mobile-first bulk score / completion sheets, inline
  category CRUD.
- **Eligibility & workflow** — the roster is snapshotted at creation (one bulk
  `insert`, one row per then-eligible student). Scores start `null`; a draft's
  roster can be re-synced with current enrolment; publishing freezes it. Locking
  freezes scores; only `assessment.manage` unlocks.
- **`Module::Assessments->isAvailable()`** flipped to `true` (on by default);
  depends on **`Module::Academics` + `Module::Students`** — not Timetable,
  Attendance, Results or CBT. "Assessments" is a top-level nav item.
- **Permissions** — new `assessment.view` / `assessment.record` /
  `assessment.manage` (27 → 30). `assessment.manage` on Principal; `.view` +
  `.record` on Teacher; `.view` on Staff. School Admin auto.
- **Seeder** — Alpha gets 4 categories, 3 assessments (locked / published /
  draft, mixed scores), 2 assignments (published w/ mixed completion, draft).
- **Docs** — new `docs/assessment-management.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `scalability.md`,
  `module-activation.md`, `roadmap.md`, `PROJECT_STATUS.md`, `CLAUDE.md`,
  `AGENTS.md`.

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

## Authorization & tenant controls (M14)

- **Two gates on every `/assessments/*` route:** `module:assessments` (404 when
  off) **and** `->can('assessment.view'|'.record'|'.manage')`; write Form
  Requests re-check via `AssessmentModuleRequest` + `AssessmentAuthorizer`.
  Module gate ≠ permission (a Bursar with the module on still can't see
  assessments — tested).
- `Assessment` / `AssessmentScore` / `Assignment` / `AssignmentSubmission` /
  `AssessmentCategory` are `BelongsToSchool`; the child rows also carry their
  parent FK. `school_id` never from input, immutable (`TenantMismatchException`);
  a `school_id` in a payload is ignored (tested). Roster bulk `insert`s set
  `school_id` from `TenantContext` explicitly.
- `{assessment}` / `{assignment}` / `{category}` resolved by tenant-scoped
  `findOrFail`; the write Form Requests `abort(404)` on a cross-school route
  parent before validation. Every session / period / level / arm / subject /
  category / assignment id in a payload uses
  `Rule::exists(...)->where('school_id', <tenant>)` → generic "invalid", no leak.
  A score / submission for a student not on the snapshotted roster is rejected —
  this blocks cross-school and wrong-class student ids. `AssessmentAuthorizer`
  additionally scopes a teacher to their assigned `(level, subject)`. Explicit
  HTTP + model cross-school isolation tests (view / edit / score / publish /
  lock / unlock / delete / create-with-foreign-context / post-foreign-student).

## Database

M14 adds `assessment_categories`, `assignments`, `assessments`,
`assessment_scores` and `assignment_submissions`. No other schema changes.

## Routes (application, additions in M14)

Tenant-scoped + `module:assessments`, gated `assessment.view` /
`assessment.record` / `assessment.manage`. 30 routes under `/assessments/`
(`assessments.*` — index/create/store/show/edit/update/destroy/scores.edit/
scores.update/scores.sync/publish/unpublish/lock/unlock; `assessments.categories.*`;
`assessments.assignments.*` — index/create/store/show/edit/update/destroy/
submissions.edit/submissions.update/publish/unpublish/close/reopen).

## Tests

615 passing (was 526 at M13; +89 in M14, M1–M13 intact). New
`tests/Feature/Assessment/*` (+ `AssessmentTestCase` base) — `AssessmentTest`,
`AssessmentEligibilityTest`, `AssessmentScoreTest`, `AssessmentLifecycleTest`,
`AssessmentAuthorizationTest`, `AssessmentTeacherScopeTest`,
`AssessmentCategoryTest`, `AssessmentStructureTest`, `AssignmentTest`:
assessment create / required-field / max-score-precision / date-vs-session-term /
period-vs-session / arm-vs-level / subject-offered-at-level / inactive-category /
empty-class / list filters + pagination / context-immutable-after-creation;
eligibility — exact-class enrolment, not-yet-enrolled excluded, left-before
excluded but leaving-after included, cross-school student never scored, draft
roster re-sync, published roster frozen; scores — zero / maximum / decimal /
negative-rejected / over-max-rejected / non-numeric-rejected / >2dp-rejected /
blank-clears / comment length / correction before lock / not-on-roster rejected /
DB duplicate prevention / full class one request / locked = 403; lifecycle —
draft→published→locked, unpublish, structure frozen once published, scores while
published, only manager unlocks, locked/scored can't delete, max-score frozen
once any score is recorded (both directions);
authorization — all 7 roles + role-less, module off → 404, module on without
permission → 403, only managers touch categories; teacher scope — assigned
class+subject only, wrong class / wrong subject / no Teacher record / other-school
assignment all rejected; categories — CRUD, upper-cased code, duplicate name/code
per school, same name in another school, tenant isolation, code format;
structure — column allow-lists (no `final_grade` / `percentage` / `position` /
`gpa`), no result-module relations, historical score survives withdrawal /
class-change, delete-student cascades scores keeps assessment, locked stays
readable, N+1 guards on the score sheet / lists / bulk save; assignments — create
with completion roster + teacher ownership, due-date validation, lifecycle
draft→published→closed→reopen, structure frozen once published, completion
tracking + validation, closed rejects edits, recorded-submission blocks delete,
teacher scope, tenant isolation, ownership immutable, N+1 guard, no-score column
check, DB duplicate prevention, assessment↔assignment link (same class only). New
`tests/Unit/Enums/AssessmentEnumsTest`. `Unit/Enums/ModuleTest` updated
(available list).

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Next milestone: Results & Report Cards (compile from M14 assessment scores).
- **Assessment follow-ups** — grading schemes, report cards, subject/term/session
  averages, positions/ranking, GPA, assignment file attachments + online
  submission, automated grading, assessment weighting, per-student submission on
  the portal.
- **Attendance follow-ups** — attendance rate / percentage analytics, term &
  monthly reports, per-lesson (timetable-driven) registers, portal attendance
  views, absence notifications, an attendance-reason taxonomy, half-day records.
- **Timetable follow-ups** — student/parent timetable views (portals), publish
  notifications, a rooms/facilities module, timetable templates / term cloning,
  named period grids, teacher workload limits.
- **Teacher portal** / **Parent portal** — sign-in + invitations.
- Promotion / graduation workflow; bulk import; documents / photo.
- Audit trail + data-erasure handling for student / guardian / teacher / timetable / attendance / assessment records.
- Apply the stored `timezone` / `locale` / `date_format` at render time.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
