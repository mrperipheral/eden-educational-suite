# Project Status

_Last updated: 2026-09-24_

## Current milestone

**Milestone 16 — Parent Portal: COMPLETE.**

Next up: further **Domain Modules** — Fees, CBT, Notifications, Student
Portal, Promotion. Not started — do not begin without picking one up
explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 · Alpine.js ·
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-24)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 (app) · SQLite `:memory:` (tests) |
| Local mail | Mailpit (`127.0.0.1:1025`, UI `:8025`) — `.env` only, not committed |
| Tests | `php artisan test` — 761 passing |
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
- **M14 — Assessment & Assignments** (`assessment-assignments-complete`) —
  `docs/assessment-management.md`.
- **M15 — Results & Report Cards** (`results-report-cards-complete`) —
  `docs/results-report-cards.md`.
- **M16 — Parent Portal** (this milestone, `parent-portal-complete`) —
  `docs/parent-portal.md`; see below.

## Delivered in Milestone 16

A secure, read-only, child-scoped window for a signed-in parent onto their
own children's published data. Built on the existing `TenantContext` +
`BelongsToSchool` + `Permission` + `module:parent-portal` seams, the M2
`User` account, M10's `Guardian`/`GuardianStudent` relationship, and
M9/M12/M13/M14/M15's own data — no second authentication system, no second
tenancy mechanism, no second report-card generator. Full detail in
`docs/parent-portal.md`.

- **`Guardian.user_id`** (new, additive column via migration
  `2026_09_24_100000_add_user_id_to_guardians_table.php` — M10's own
  migration untouched) — nullable, unique per school, **not**
  mass-assignable, set only through `GuardianController::updateUser()`
  (mirrors `Teacher.user_id` from M11 exactly). A `User` is not automatically
  a `Guardian` — the two stay separate concepts.
- **`App\Support\Portal\ParentPortalAuthorizer`** — the one seam every portal
  controller uses: `guardianFor()`, `studentsFor()`, `authorizedStudent()`.
  Tenant-scoped for free (`Guardian` is `BelongsToSchool`); a student id is
  never trusted from the URL until proven to be one of that guardian's own
  linked children.
- **`App\Services\Results\ReportCardRenderer`** — extracted from M15's own
  `Results\ReportCardController` (identical behaviour; the M15 test suite
  passed unchanged) so the school/staff report card and the Parent Portal's
  report card share **one** renderer, never a duplicated generator.
- **`App\Http\Controllers\Portal\*`** (8 thin controllers) +
  `resources/views/parent/*` — dashboard ("My Children"), child profile, a
  child-scoped tab nav + "switch child" dropdown
  (`resources/views/parent/_child-nav.blade.php`), results, report cards
  (reusing the renderer above), attendance (reusing M15's
  `AttendanceSummarizer`), assignments (M14's `AssignmentSubmission`,
  paginated), timetable (M12's published timetable for the child's current
  class), and a read-only guardian profile.
- **Result & report-card visibility** — `App\Enums\ResultRunStatus::
  visibleToParents()` (new, small, additive method): only `published` /
  `locked` runs are ever shown to a parent — M15 has no dedicated
  parent-visibility flag, documented as the safest interpretation.
  `App\Enums\AssignmentStatus::visibleToParents()` similarly gates
  assignments to `published`/`closed`, never `draft`.
- **Bug fixed during this milestone's own reuse of the M15 report card
  view**: its "← Result run" back-link pointed at a staff-only page — a
  parent clicking it would have hit a 403. Now audience-aware
  (`@can('result.view')` vs. a Parent Portal link).
- **`Module::ParentPortal->isAvailable()`** flipped to `true` (on by default,
  declared since M7); depends on **`Module::Guardians` only**. A Parent-role
  member is redirected from `/dashboard` straight to `/parent`
  (`DashboardController`); the main nav renders a portal-specific link set
  for them instead of the (near-empty, for a parent) admin nav.
- **No new permission** — reuses `Permission::PortalParent` (`portal.parent`),
  declared since M4, enforced for the first time here. `Role::SchoolAdmin`
  also holds it (full bundle) but is never itself a `Guardian`, so it sees
  the same safe empty state as an unlinked parent.
- **Seeder** — a Parent account (`parent@example.com`) linked to the existing
  M10 "siblings sharing a guardian" record, extended to three children across
  three classes (one with full M12/M13/M14/M15 data); `dual@example.com`
  (Parent at Beta, Teacher at Alpha) demonstrates the "no linked guardian"
  empty state and the two-different-families-two-different-schools case.
- **Docs** — new `docs/parent-portal.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `scalability.md`,
  `tenancy.md`, `roadmap.md`, `PROJECT_STATUS.md`, `ui-ux-guidelines.md`,
  `CLAUDE.md`, `AGENTS.md`.

## Delivered in Milestone 15

Turns M14's locked assessment scores into configurable student results and
printable report cards: school-defined grading + weighting schemes, a
compile → review → approve → publish → lock lifecycle, class-position
ranking, a controlled per-subject adjustment workflow, and a report card whose
visible fields the school configures. Built on the existing `TenantContext` +
`BelongsToSchool` + `Permission` + `module:results` seams, M8's
`AcademicSession`/`AcademicPeriod` and M14's `Assessment`/`AssessmentScore` —
no new mechanism, no new packages, no Redis/queues, no PDF library. Full
detail in `docs/results-report-cards.md`.

- **Enums** — `App\Enums\ResultRunStatus` (`draft`/`compiled`/`reviewed`/
  `approved`/`published`/`locked`), `App\Enums\ResultAdjustmentStatus`
  (`pending`/`applied`/`rejected`), `App\Enums\AssessmentPurpose`
  (`academic`/`practice`/`entry_placement` — added to M14's `Assessment` via
  an additive migration), `App\Enums\ScoreSource`
  (`manual`/`online_cbt`/`imported` — added to M14's `AssessmentScore`).
- **`App\Models\GradingScheme` + `GradingSchemeGrade`** — school-configured
  percentage bands; `gradeFor(float): ?GradingSchemeGrade` is the one place a
  percentage becomes a grade. Overlap/ordering validated server-side.
- **`App\Models\ResultWeightingScheme` + `ResultWeightingSchemeItem`** — ties
  an M14 `AssessmentCategory` to a weight; must sum to exactly 100%.
- **`App\Models\ResultRun`** — one class, one term (`unique` per
  session+period+level+arm); `status` / lifecycle timestamps **not**
  mass-assignable — `review()`/`approve()`/`publish()`/`lock()` only.
  `reportCardSnapshot(): HasOne` for the frozen per-run config.
- **`App\Models\StudentResult` / `StudentSubjectResult` /
  `StudentSubjectResultComponent`** — compiled results, **snapshotted** at
  compile time (percentage, grade, raw score, weight) so they never change
  when the grading/weighting scheme changes later.
- **`App\Models\ResultAdjustment`** — a controlled propose → apply/reject
  correction workflow; a proposal alone changes nothing; `apply()` re-derives
  the grade via `GradingScheme::gradeFor()` and triggers a full ranking
  recompute.
- **`App\Models\ReportCardConfiguration`** — 24 independent `show_*`
  booleans (typed/relational, not a JSON blob); school-wide/session/term
  scope with documented precedence; a second, `result_run_id`-tagged row is
  the immutable snapshot a locked run's report card renders from.
- **Migrations** `2026_09_23_100000`–`100110` — 2 additive columns onto
  M14's tables (`assessments.purpose`, `assessment_scores.source`) plus 10
  new tables, all `BelongsToSchool`, `school_id`-leading indexes.
- **`App\Services\Results\ResultCompiler`** — all-or-nothing compilation
  (computes fully in memory, collects every missing-score issue, writes
  nothing if any exist); bulk `insert`s (chunked 500); ranking + overall
  totals refreshed via a single `UPDATE ... CASE id WHEN ... END` per chunk,
  never one query per student.
- **`App\Support\Results\{RankingCalculator,AttendanceSummarizer,
  ResultAuthorizer}`** — pure-PHP competition ranking (portable across
  MySQL/SQLite); a 2-query attendance rollup from M13 data (no duplication);
  class-scoped teacher comment authorization (M13-pattern, no subject
  dimension).
- **`App\Http\Controllers\Results\*`** (8 controllers) +
  `App\Http\Requests\Results\*` (10 requests) + `resources/views/results/*`
  — grading/weighting scheme management, result run lifecycle with itemized
  blocking-issue display, per-student adjustment UI, report-card
  configuration (scope switcher, signature upload), and the report card
  itself (same view for preview and final output).
- **`Module::Results->isAvailable()`** flipped to `true` (on by default);
  depends on **`Module::Assessments` only** — not Timetable/Attendance/CBT.
  "Results" is a top-level nav item.
- **Permissions** — new `result.manage` / `result.adjust`, reusing
  `result.view`/`.enter`/`.publish` already declared in M4 (30 → 32).
  `result.manage` + `.adjust` added to **Principal** (joining the existing
  `.view`/`.enter`/`.publish`); `result.view` added to **Staff**. School
  Admin auto.
- **Seeder** — Alpha gets a Standard Grading scheme, a Standard Weighting
  scheme, extra locked Mathematics/English assessments, and a fully
  compiled → reviewed → approved → published → **locked** result run with a
  snapshotted report-card configuration.
- **Docs** — new `docs/results-report-cards.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `scalability.md`,
  `tenancy.md`, `roadmap.md`, `PROJECT_STATUS.md`, `ui-ux-guidelines.md`,
  `CLAUDE.md`, `AGENTS.md`.

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

## Authorization & tenant controls (M16)

- **Two gates on every `/parent/*` route:** `module:parent-portal` (404 when
  off) **and** `->can('portal.parent')`. No new permission — `portal.parent`
  was declared since M4.
- Every `{student}` route param is resolved via `App\Support\Portal\
  ParentPortalAuthorizer::authorizedStudent()`, never route-model-bound and
  never trusted from the URL — it 404s unless the signed-in parent's own
  `Guardian` record (in the active school) is linked to that exact student.
  `{run}` is additionally re-checked against `ResultRunStatus::
  visibleToParents()` before any result or report card is returned.
- `Guardian.user_id` lookups are tenant-scoped for free (`Guardian` is
  `BelongsToSchool`) — no second tenancy mechanism. Explicit HTTP tests prove
  a parent cannot open an unrelated student in the same school, cannot reach
  a student from another school by id, cannot use a second school membership
  to reach that school's other families' children, and every child-scoped
  page (results/report-cards/attendance/assignments/timetable) 404s for an
  unauthorized child regardless of whether real data exists for them.
- Admin-side linking (`GuardianController::updateUser()`, `guardian.manage`)
  is tenant-scoped exactly like M11's `TeacherController::updateUser()`: the
  account must be a member of the active school, `{guardian}` is
  tenant-scoped `findOrFail`, and the same account may be linked to a
  *different* guardian in a *different* school (`unique` per school).

## Authorization & tenant controls (M15)

- **Two gates on every `/results/*` route:** `module:results` (404 when off)
  **and** `->can('result.view'|'.enter'|'.manage'|'.publish'|'.adjust')`.
  Module gate ≠ permission (a Bursar with the module on still gets 403 —
  tested).
- `ResultRun` / `StudentResult` / `StudentSubjectResult` /
  `StudentSubjectResultComponent` / `ResultAdjustment` /
  `ReportCardConfiguration` / `GradingScheme(Grade)` /
  `ResultWeightingScheme(Item)` are all `BelongsToSchool`; child rows also
  carry their parent FK. `school_id` never from input, immutable. Bulk
  `insert`s in `ResultCompiler` set `school_id` from `TenantContext`
  explicitly (raw `insert` bypasses the `creating` hook).
- Every id in a run/adjustment/config payload uses
  `Rule::exists(...)->where('school_id', <tenant>)` → generic "invalid", no
  leak. Route ids resolved by tenant-scoped `findOrFail`. A
  `ReportCardConfiguration` write always goes through
  `exactScopeRow()`/`whereNull('result_run_id')` — never a raw
  `updateOrCreate` on scope columns alone, which could otherwise match and
  silently corrupt a locked run's frozen snapshot (the same (session=null,
  period=null) scope columns are shared by the school-wide default and every
  per-run snapshot; only `result_run_id` tells them apart). Explicit HTTP +
  model cross-school isolation tests (view / compile / review / approve /
  publish / lock / adjust / comment / configure-report-card / view-report-card
  / view-signature — all 404 or 403 for School B).

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

M16 adds one column: `guardians.user_id` (nullable, additive — M10's own
migration is untouched).

M15 adds two columns to M14's tables (`assessments.purpose`,
`assessment_scores.source`, via additive migrations — the original M14
migrations are untouched) and 10 new tables: `grading_schemes`,
`grading_scheme_grades`, `result_weighting_schemes`,
`result_weighting_scheme_items`, `result_runs`, `student_results`,
`student_subject_results`, `student_subject_result_components`,
`result_adjustments`, `report_card_configurations`.

M14 adds `assessment_categories`, `assignments`, `assessments`,
`assessment_scores` and `assignment_submissions`. No other schema changes.

## Routes (application, additions in M16)

Tenant-scoped + `module:parent-portal`, gated `portal.parent`. 10 routes
under `/parent/` (`parent.dashboard`, `.children.show`, `.results.*`,
`.report-cards.*`, `.attendance.index`, `.assignments.index`,
`.timetable.index`, `.profile.edit`) plus one addition to the existing
Guardian routes: `PATCH /guardians/{guardian}/user` (`guardians.user`,
`guardian.manage`).

## Routes (application, additions in M15)

Tenant-scoped + `module:results`, gated `result.view` / `.enter` / `.manage` /
`.publish` / `.adjust`. ~30 routes under `/results/` (`results.grading-
schemes.*`, `.grading-schemes.grades.*`, `.weighting-schemes.*`,
`.weighting-schemes.items.*`, `.report-card-configuration.*` +
`.principal-signature.*` + `.class-teacher-signature.*`, `.runs.*` —
index/create/store/show/destroy/compile/review/approve/publish/lock/
students.comment/students.report-card, `.adjustments.*` —
store/apply/reject).

## Routes (application, additions in M14)

Tenant-scoped + `module:assessments`, gated `assessment.view` /
`assessment.record` / `assessment.manage`. 30 routes under `/assessments/`
(`assessments.*` — index/create/store/show/edit/update/destroy/scores.edit/
scores.update/scores.sync/publish/unpublish/lock/unlock; `assessments.categories.*`;
`assessments.assignments.*` — index/create/store/show/edit/update/destroy/
submissions.edit/submissions.update/publish/unpublish/close/reopen).

## Tests

761 passing (was 699 at M15; +62 in M16, M1–M15 intact). New
`tests/Feature/Portal/*` (+ `ParentPortalTestCase` base) —
`ParentAuthorizationTest`, `ParentChildAccessTest`, `ParentResultTest`,
`ParentReportCardTest`, `ParentAttendanceTest`, `ParentAssignmentTest`,
`ParentTimetableTest`, `ParentProfileTest`, `ParentGuardianProtectionTest`,
`ParentPortalStructureTest`: module off → 404, denied roles → 403,
unauthenticated → redirect to login, no-linked-guardian /
guardian-with-no-students safe empty states, School Admin sees the empty
state rather than a leak, a Guardian record without the Parent role still
denies access; own child accessible, unrelated student in the same school
404s, student-id tampering blocked, cross-school student blocked, switching
active school never exposes another family's child, multiple children all
accessible, the child switcher never mixes up page content (only the
switcher's own list of names), non-numeric/nonexistent id 404s; results —
approved-but-unpublished invisible, published/locked visible with the
correct weighted breakdown, wrong child / wrong school inaccessible, module
off degrades gracefully; report cards — unpublished invisible, published
renders via the shared `ReportCardRenderer` (and its audience-aware back
link), locked stays reachable, wrong child / wrong school inaccessible;
attendance — only the child's own data, a sibling's attendance never leaks
into another child's page, no-data-yet and module-off empty states;
assignments — published/closed visible, draft invisible, another student's
submission never leaks, module-off empty state; timetable — published
visible, draft invisible, no-current-enrollment and module-off empty states;
guardian protection — a parent cannot link/unlink a student, create a link by
posting directly, change a relationship or make themselves primary, or link
their account to a different guardian record; structure — dashboard and
child-page query counts stay flat as unrelated school size and the parent's
own child count grow, resolving one authorized student never scans the whole
table. New `tests/Feature/Guardian/GuardianUserLinkTest.php` (admin-side
linking: link/unlink, must be a school member, uniqueness per school, same
account linkable in a different school, view-only roles forbidden, tenant
isolation). `Unit/Enums/ModuleTest` updated (available list).
`Tests\Feature\Onboarding\OnboardingChecklistTest` updated (a Parent-role
member is now redirected to the Parent Portal instead of seeing `/dashboard`).

699 passing (was 615 at M14; +84 in M15, M1–M14 intact). New
`tests/Feature/Results/*` (+ `ResultsTestCase` base) — `GradingSchemeTest`,
`WeightingSchemeTest`, `ResultRunTest`, `ResultCompilationTest`,
`ResultLifecycleTest`, `ResultAdjustmentTest`, `ResultAuthorizationTest`,
`ReportCardTest`, `ResultStructureTest`: grading scheme create/edit,
overlap/ordering/duplicate-code rejection, `gradeFor()` calculation; weighting
scheme create/edit, duplicate-category rejection, cross-school category
rejection; run creation, cross-context rejection, incomplete-weighting
rejection, one-run-per-class-per-term uniqueness, list filters, tenant
isolation; compilation — only-locked-included, draft/unlocked doesn't
count/block, missing-score blocks with itemized issues, no-students/no-subjects
block, weighted percentage + grade math, competition ranking with ties,
position scoped to the run not the school, ranking-disabled means no position,
recompiling replaces stale rows, no cross-school leakage; lifecycle — full
draft→locked path, out-of-order transitions rejected, role restrictions,
locked run rejects recompilation, run-with-results can't be deleted;
adjustment — proposal inert until applied, applying updates value + re-derives
grade + recomputes whole-run ranking, rejection inert, decided can't
re-decide, unavailable before approval, value-must-differ, teacher forbidden,
tenant isolation; authorization — all 7 roles + role-less, module off → 404,
module on without permission → 403, class-scoped teacher comment (own class
only, principal comment blocked); report card — fields-shown-per-config, scope
precedence, branding/comments/position, unavailable before compilation, tenant
isolation, publish snapshots the config, a later config change never alters a
**locked** run's report card, an unlocked run's preview reflects the *live*
config; structure — column allow-lists (no premature derived fields), raw
score/max populated from the locked assessment, compiling/index/show/report-
card query counts stay flat as class size and subject count grow, a locked
run's grade/weights/category-name/results survive a later grading-scheme
edit / weighting-scheme edit / category rename / student status change. New
`tests/Unit/Enums/ResultsEnumsTest`. `Unit/Enums/ModuleTest` updated
(available list).

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
- Next milestone: pick a further domain module (Fees, CBT, Notifications,
  Student Portal, Promotion) — see `docs/roadmap.md`.
- **Parent Portal follow-ups** — a communication hub (WhatsApp/SMS/email;
  this milestone is the foundation it will plug into), fee/payment visibility
  (no finance module exists yet), the Student Portal (kept deliberately
  separate), a full platform audit trail of parent access events, parent
  self-service editing of guardian contact details, push notifications, the
  inherited M15 report-card branding-logo gap (never renders for a Teacher /
  Staff / Parent viewer — see `docs/parent-portal.md` §5).
- **Results follow-ups** — PDF export (browser print covers it for now),
  per-level/per-arm report-card configuration overrides, bulk "unlock" of an
  approved/published/locked run, signature-image snapshotting per run,
  student photo capture (M9), CBT/Question Bank (the `AssessmentPurpose` /
  `ScoreSource` enums are ready for it), advanced result analytics, automatic
  report-card comments, a full drag-and-drop report-card designer.
- **Assessment follow-ups** — assignment file attachments + online
  submission, automated grading, a Student Portal submission view (the
  Parent Portal, M16, only ever reads an assignment's status).
- **Attendance follow-ups** — attendance rate / percentage analytics, term &
  monthly reports, per-lesson (timetable-driven) registers, a Student Portal
  attendance view (the parent one shipped in M16), absence notifications, an
  attendance-reason taxonomy, half-day records.
- **Timetable follow-ups** — a Student Portal timetable view (the parent one
  shipped in M16), publish notifications, a rooms/facilities module,
  timetable templates / term cloning, named period grids, teacher workload
  limits.
- **Teacher portal** — sign-in + invitations (Parent Portal delivered in M16).
- Promotion / graduation workflow; bulk import; documents / photo.
- Audit trail + data-erasure handling for student / guardian / teacher / timetable / attendance / assessment records.
- Apply the stored `timezone` / `locale` / `date_format` at render time.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
