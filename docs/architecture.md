# Architecture

Status: Milestone 16 (Parent Portal) complete. This describes the intended
shape of the system and what exists today.

## 1. High-level model

```
Authenticated User
        │
        ▼
 Current School          ← chosen at login / from route, one active at a time
        │
        ▼
 Tenant Context          ← App\Support\Tenancy\TenantContext (request-scoped)
        │
        ▼
 Tenant-aware logic      ← global query scope + model hooks + policies
        │
        ▼
 School data             ← rows carry school_id; School A never sees School B
```

One Laravel application, one shared database, **logical** multi-tenancy keyed by
`school_id`. This is a deliberate choice over database-per-tenant:

- thousands of schools without thousands of schemas/connections to migrate;
- cross-cutting platform features (billing, support, analytics) stay simple;
- horizontal scale is achieved at the database and app tier, not by sharding
  tenants into separate schemas.

There is **no 500-school limit** anywhere in the design. Growth beyond that is a
capacity exercise (indexes, read replicas, caching, queue workers), not an
architectural rewrite.

## 2. Multi-school tenant isolation (implemented — Milestone 3)

One Laravel app, one shared database, logical isolation keyed by `school_id`.
Full reference: **`docs/tenancy.md`**. Summary:

- **`schools`** is the tenant root; **`school_user`** maps a many-to-many
  membership; **`users.is_platform_admin`** is the platform-owner primitive
  (separate from any per-school role, no `Gate::before` blanket-allow).
- **`App\Support\Tenancy\TenantContext`** (request-scoped singleton) holds the
  active school. `set()/setId()`, `id()/idOrFail()`, `school()/schoolOrFail()`,
  `forget()`, `isBypassed()`, `runWithoutScope()`.
- **`App\Http\Middleware\EnforceTenant`** (`tenant` alias) resolves the school
  from the session selection (re-validated every request) or auto-selects a
  single-school member, else redirects to the picker (`GET/POST /school`,
  `SchoolContextController`).
- **`App\Support\Tenancy\Concerns\BelongsToSchool`** + **`SchoolScope`** apply to
  every school-owned model: a global scope that constrains reads/updates/deletes
  to the active school (and **throws** — `MissingTenantContextException` — rather
  than run unscoped), a `creating` hook that stamps `school_id` from the context
  and rejects mismatches (`TenantMismatchException`), and an `updating` hook that
  makes `school_id` immutable.
- **`App\Policies\SchoolPolicy`** authorizes the school resource; platform
  actions require `is_platform_admin`, `view`/`enter` allow members too.

**Rule:** application code never reads `school_id` from the request. It asks
`TenantContext`. Isolation is a property of the framework wiring, not of every
developer remembering a `where()` clause.

## 2b. Roles & permissions (implemented — Milestone 4)

Full reference: **`docs/authorization.md`**. Summary:

- **`App\Enums\Permission`** — the fixed, code-defined authorization vocabulary
  (no DB table). **`App\Enums\Role`** — seven per-school roles, each a static
  bundle of permissions + a `tier`.
- Assignment is one nullable column: **`school_user.role`** (`App\Models\SchoolUser`
  pivot). One role per (user, school).
- **`App\Providers\AuthServiceProvider`** registers every permission as a Gate
  ability delegating to **`User::hasPermission()`**, which is composed with the
  active `TenantContext` — a permission only applies inside the school in
  context, and there is **no `Gate::before()`** blanket bypass.
- Platform admins hold every permission *within a school they have entered*;
  `SchoolScope` still constrains which rows they touch.
- **`App\Policies\MembershipPolicy`** adds per-row rules (no self-edit, no
  privilege escalation).
- Evaluated Spatie laravel-permission; not adopted (its Teams feature is a
  second ambient tenant id competing with `TenantContext`). See
  `docs/authorization.md` §2.

## 2c. School onboarding (implemented — Milestone 5)

Full reference: **`docs/onboarding.md`**. The controlled path from provisioning
to a usable school, built on the M1–M4 seams:

- **Provisioning** (`Platform\SchoolController` + `SchoolProvisioner`,
  `/admin/schools*`) is **platform-level** — a school shell (`schools` row,
  status `active`, unique slug) is created outside any tenant context;
  `SchoolPolicy` is the authority. Optionally seats an existing active account as
  the initial `Role::SchoolAdmin` (guarded by `canGrantRole`).
- **School-owned onboarding data** — `App\Models\SchoolSetting` (1:1) and
  `App\Models\AcademicSession`, the first real `BelongsToSchool` models — is
  created by the school's own administrators **inside** the tenant context,
  gated by `school.settings.*`.
- **Adding an existing user** (`/members/create`) reuses `member.assign-role` +
  `canGrantRole` (tier); it never crosses a school boundary or changes anyone's
  context.
- The dashboard derives an **onboarding checklist** (admin seated, first session
  created, settings reviewed) for administrators.

## 2d. School settings & configuration (implemented — Milestone 6)

Full reference: **`docs/school-settings.md`**. Expands the single M5
`SchoolSetting` row into the full per-school configuration record — typed
columns, no JSON blob — split into three focused sections behind a shared
sub-nav:

- **Profile** (`PATCH /settings/school`) — contact details + address; the
  school's `name`/`slug`/`status` stay platform-controlled and read-only.
- **Branding** (`/settings/school/branding`) — an optional logo stored on the
  **private `local` disk** and served only through a `school.settings.view`-gated
  route with **no path parameter** (no traversal, tenant-scoped), plus
  `brand_color`.
- **Regional** (`/settings/school/regional`) — `timezone`, `locale`, `currency`,
  `date_format` (`App\Enums\DateFormat`), `week_starts_on` (`App\Enums\Weekday`),
  `academic_year_start_month` (read later by Academic Management).

Reuses the existing seams unchanged: `school.settings.view` / `.update`,
`TenantContext` + `SchoolScope`, the M3 storage abstraction. Principal & Bursar
get the read-only view; Platform Admin operates only through a selected context.
Reference lists live in `config/school-settings.php`.

## 2e. Feature / module activation (implemented — Milestone 7)

Full reference: **`docs/module-activation.md`**. Each school turns the
application's feature modules on or off independently. M7 ships the activation
*system*; the modules themselves are later milestones.

- **`App\Enums\Module`** — the code-defined catalogue (14 cases: Academics,
  Students, Guardians, Staff, Timetable, Attendance, Assessments, Results, Fees,
  Learning Materials, CBT, Notifications, Parent/Student Portal). Each case
  carries a label, description, group, dependency list, default, and
  `isAvailable()` (all `false` until each domain milestone flips its own).
- **`school_modules`** (`App\Models\SchoolModule`, `BelongsToSchool`) —
  override-only: a row exists only where a school departs from the catalogue
  default, so a new school writes nothing.
- **`App\Support\Modules\SchoolModules`** — request-scoped resolver: reads the
  override rows once (keyed by active school id), memoised; `enabled(Module)` /
  `states()` / `set()`.
- **Admin screen** — `GET/PATCH /settings/school/modules`, gated
  `school.settings.view` / `.update` (no new permission). Single-level
  dependency validation on toggle. Not-yet-built modules are badged **Planned**.
- **Reuse seam for future modules** — `module:` route middleware
  (`EnsureModuleEnabled`, 404s when off) and the `@module('…')` Blade directive.
  Activation is **configuration, not authorization** — it grants nothing; a
  domain route checks both `module:` *and* `->can('…')`.

## 2f. Academic foundation (implemented — Milestone 8)

Full reference: **`docs/academic-foundation.md`**. The configurable academic
structure the Student / Teacher / Timetable / Attendance / Assessment / Results
modules will build on. Structure only — no people, no timetable, no marks.

- **`App\Models\AcademicSession`** (M5, extended) — the year; one `is_current`
  per school. Now has `AcademicPeriod` children.
- **`App\Models\AcademicPeriod`** — a term / semester within a session.
  `BelongsToSchool` *and* scoped to its session; one `is_current` per session;
  any number per session (no "three terms" assumption).
- **`App\Models\AcademicLevel`** + **`App\Models\LevelArm`** — classes and their
  streams. Named, coded, ordered, active/inactive. No level names hard-coded.
- **`App\Models\Subject`** + the `level_subject` link — school subjects and
  which levels offer them. The only cross-model relationship M8 ships.
- **`App\Http\Controllers\Academic\*`**, `/academic/*` routes behind
  `['tenant', 'module:academics']`, gated `academics.view` / `academics.manage`
  (M4 permissions, previously dormant — now enforced). Academic sessions moved
  here from `settings/academic-sessions` (M5).

## 2g. Student management (implemented — Milestone 9)

Full reference: **`docs/student-management.md`**. The student record + enrollment
history the Guardian / Attendance / Assessment / Results / Fees / Promotion /
Portal modules will hang off. Records only — no people beyond students, no
placement workflow.

- **`App\Models\Student`** — school-owned. Minimal PII (name, DOB, optional
  gender, admission details, contact/address, notes). `status`
  (`App\Enums\StudentStatus`: active / inactive / withdrawn / graduated) is not
  mass-assignable — changed via a dedicated endpoint. Never hard-deleted.
- **`App\Models\Enrollment`** — school-owned *and* scoped to its student. Points
  at an `AcademicSession` (+ optional `AcademicPeriod`), `AcademicLevel` (+
  optional `LevelArm`). One `active` enrollment per student = the current class
  (`Enrollment::makeActive()`, the M8 `makeCurrent()` pattern — **not** a
  promotion workflow). History is preserved: placements are closed, never
  deleted.
- **`App\Http\Controllers\Student\*`**, `/students/*` routes behind
  `['tenant', 'module:students']`, gated `student.view` / `student.manage` (M4
  permissions, previously dormant — now enforced). `Module::Students` now
  **depends on `Module::Academics`**.
- Level ↔ arm and session ↔ period consistency, and cross-school ids, are
  rejected in the Form Request with generic messages (no leak); the enrollment
  Form Request `abort(404)`s on a cross-school route parent.

## 2h. Guardian management (implemented — Milestone 10)

Full reference: **`docs/guardian-management.md`**. Tenant-scoped guardian /
parent records and the student ↔ guardian relationship the later Parent Portal /
Notifications modules build on. Records + linkage only — no portal, no
credentials, no messaging.

- **`App\Models\Guardian`** — school-owned. Minimal contact data (name, phone,
  alternate phone, email, address, notes). No identity / financial / medical /
  emergency data, no portal credentials. No global uniqueness; never
  hard-deleted.
- **`App\Models\GuardianStudent`** — the link. School-owned *and* carries
  `student_id` + `guardian_id`. `relationship`
  (`App\Enums\GuardianRelationship`), `is_primary` (at most one per student via
  `makePrimary()`, the M8/M9 "one at a time" pattern). `unique(student_id,
  guardian_id)` — no duplicate links.
- **`App\Http\Controllers\Guardian\*`**, `/guardians/*` routes behind
  `['tenant', 'module:guardians']`, gated `guardian.view` / `guardian.manage`
  (M4 permissions, previously dormant — now enforced). `Module::Guardians`
  **depends on `Module::Students`**. `guardian.view` added to the Staff bundle.
- Links are created from the student's profile; `student_id` / `guardian_id` are
  validated tenant-scoped (`Rule::exists(...)->where('school_id', …)`), and the
  `{link}` route id `abort(404)`s on a cross-school id before validation.

## 2i. Teacher management (implemented — Milestone 11)

Full reference: **`docs/teacher-management.md`**. The teacher professional
record, an optional link to an application account, and the teaching-assignment
foundation the later Timetable / Attendance / Assessment / Results modules build
on. Records + assignment foundation only — no teacher portal, no credentials, no
timetable/attendance/marks, no payroll.

- **`App\Models\Teacher`** — school-owned. Minimal professional data (name,
  `employee_number`, email, phone, start date, address, notes). No identity /
  financial / medical / credential fields. `status`
  (`App\Enums\TeacherStatus`: active / inactive / suspended / resigned) and
  `user_id` are **not** mass-assignable — each changed via a dedicated endpoint.
  Never hard-deleted.
- **Teacher ↔ User** — nullable `teachers.user_id`, `unique(school_id, user_id)`,
  `nullOnDelete`. A teacher record is not automatically a login; it is linked
  only to an **existing member of the active school**, via
  `PATCH /teachers/{teacher}/user`. No invitation / password / portal flow.
- **`App\Models\TeacherAssignment`** — school-owned *and* scoped to its teacher.
  Points at a `Subject`, an `AcademicLevel` (+ optional `LevelArm`) for an
  `AcademicSession` (+ optional `AcademicPeriod`). `status` `active` / `ended` —
  history preserved (`end()`), `DELETE` kept only for a mis-entered row.
  Duplicate **active** `(teacher, session, period, level, arm, subject)` rejected
  in the Form Request, not by a DB constraint.
- **`App\Http\Controllers\Teacher\*`**, `/teachers/*` routes behind
  `['tenant', 'module:staff']`, gated `staff.view` / `staff.manage` (M4
  permissions, previously dormant — now enforced). `Module::Staff` now
  **depends on `Module::Academics`**. `staff.manage` added to Principal;
  `staff.view` added to Bursar / Teacher / Staff.
- Level ↔ arm and session ↔ period consistency, and cross-school academic /
  user ids, are rejected in the Form Request with generic messages (no leak);
  the assignment Form Request `abort(404)`s on a cross-school route parent.

## 2j. Timetable management (implemented — Milestone 12)

Full reference: **`docs/timetable-management.md`**. A tenant-scoped, configurable
weekly timetable with server-side conflict detection and a draft → published
lifecycle. Scheduling foundation only — no attendance, marks, notifications,
rooms module, workload/payroll, auto-optimisation or student/parent views.

- **`App\Models\Timetable`** — school-owned. Scoped to one `AcademicSession`
  (fixed at creation) + optional `AcademicPeriod`; lessons inherit that scope so
  they can't cross sessions. `status` (`draft` / `published`) and `published_at`
  are **not** mass-assignable — `publish()` runs behind a conflict guard.
- **`App\Models\TimetableEntry`** — school-owned *and* timetable-scoped. A lesson
  = level + **arm (required — scheduled per class)** + subject + teacher +
  `weekday` (`App\Enums\Weekday`, no Mon–Fri assumption) + `start_time` /
  `end_time` (`HH:MM` strings, **half-open** `[start, end)`) + optional text
  `room`.
- **Scheduling rules** (`TimetableEntryRequest`, all server-side DB existence
  queries): end after start; arm↔level; subject offered by the level
  (`level_subject`); an **active M11 `TeacherAssignment`** backs `(teacher,
  subject, level)` for the session; no teacher / class / room double-booking.
  `App\Support\Timetable\TimetableConflictScanner` (one indexed self-join) backs
  the publish guard.
- **`App\Http\Controllers\Timetable\*`**, `/timetables/*` routes behind
  `['tenant', 'module:timetable']`, gated `timetable.view` / `timetable.manage`
  (new M4 permissions). `Module::Timetable->isAvailable()` is now `true` (still
  **off by default**); it **depends on `Module::Academics` and `Module::Staff`**.
  `timetable.*` added to Principal / Teacher / Staff (the roles with academic
  access) — Bursar has none.
- Desktop **day × time grid**, mobile **stacked day list**; a **teacher
  timetable** view; filters by class / arm / teacher / weekday.

## 2k. Attendance management (implemented — Milestone 13)

Full reference: **`docs/attendance-management.md`**. A tenant-scoped daily
student-attendance foundation with a draft → submitted (locked) lifecycle,
**independent of the Timetable module**. No attendance analytics, term/monthly
reports, portal views, notifications, per-lesson registers or a full audit
trail.

- **`App\Models\AttendanceRegister`** — school-owned. One class's attendance for
  one day: `(academic_session, optional academic_period, academic_level,
  level_arm, attendance_date)`. `status` (`draft` / `submitted`) and
  `submitted_at` / `submitted_by` are **not** mass-assignable — `submit()` /
  `reopen()` only. `eligibleStudents()` is the enrollment-based eligibility rule;
  `summary()` gives register-level totals.
- **`App\Models\AttendanceRecord`** — school-owned *and* register-scoped. One
  student's mark: nullable `status` (null = unmarked; `present` / `absent` /
  `late` / `excused` — one controlled enum, no `is_present` booleans, no
  minutes-late field), optional `note`, `recorded_at` / `recorded_by` stamped
  when a mark changes. Never deleted (historical correctness).
- **Eligibility** — a student is on a register iff they hold an M9 `Enrollment`
  for the register's exact session/level/arm whose `[started_on, ended_on]`
  range contains the date. Current status is *not* a filter, so a later
  withdrawal doesn't rewrite history. The roster is **snapshotted** at creation
  (one bulk `insert`).
- **Workflow** — marks start `null`; a register can't be submitted while any
  record is unmarked (an unmarked student is never counted present). A submitted
  register is locked; only an `attendance.manage` holder can `reopen()` it.
- **`App\Support\Attendance\AttendanceAuthorizer`** — `attendance.manage` →
  any class; `attendance.record` only → a class the teacher holds an active M11
  `TeacherAssignment` for. Assignments *scope* teachers; they are not a
  dependency (a Staff-off school records through `attendance.manage` holders).
- **`App\Http\Controllers\Attendance\AttendanceRegisterController`**,
  `/attendance/*` routes behind `['tenant', 'module:attendance']`, gated
  `attendance.view` / `attendance.record` / `attendance.manage`.
  `Module::Attendance->isAvailable()` is now `true` (**on by default**); it
  **depends on `Module::Academics` and `Module::Students` — not
  `Module::Timetable`**. `attendance.manage` added to Principal.
- List (date / session / level / arm / status filters + pagination), an
  Alpine-cascade create form, a mobile-first taking screen (per-student status
  buttons, bulk "mark all present" / "clear all", per-student note, save draft /
  save & submit), and a register detail with summary counts and a
  reopen-for-correction control.

## 2l. Assessment & assignments (implemented — Milestone 14)

Full reference: **`docs/assessment-management.md`**. A configurable,
tenant-scoped assessment and assignment foundation — the source data for M15
Results & Report Cards. No final grades, report cards, averages, positions/GPA,
promotion, CBT, portal views, or a full audit trail.

- **`App\Models\AssessmentCategory`** — school-configured category (Classwork /
  Test / Exam / …), unique per school, editable; seeded examples only. Managed
  under `assessment.manage`.
- **`App\Models\Assessment`** — school-owned. Academic context (session +
  period + level + arm + subject) **fixed at creation**. `status`
  (`draft` / `published` / `locked`), `published_at` / `locked_*` / `created_by`
  **not** mass-assignable. Optional `assignment_id` link (records the relation,
  computes nothing). `eligibleStudents()` + `summary()`.
- **`App\Models\AssessmentScore`** — school-owned *and* assessment-scoped.
  Nullable `score` (`decimal(6,2)`, null = not entered), `comment`,
  `recorded_at` / `recorded_by`. Never deleted. **No** grade / percentage / rank
  stored anywhere — that is M15's to derive.
- **`App\Models\Assignment`** — school-owned; same context rules plus `due_on >=
  assigned_on`, both in the session. `teacher_id` owner, `created_by`, optional
  `max_score`. Carries **no scores** — tracks completion via
  `App\Models\AssignmentSubmission` (`pending` / `submitted` / `late` /
  `exempt`). No file upload, no student-facing submission flow.
- **Eligibility** — a student is on a roster iff they hold an M9 `Enrollment`
  for the exact session/level/arm whose `[started_on, ended_on]` range contains
  the assessment / assigned-on date (shared `App\Models\Concerns\HasClassRoster`;
  same historical rule as M13). Rosters are snapshotted at creation (one bulk
  `insert`); a draft assessment's roster can be re-synced with current enrolment,
  publishing freezes it.
- **Lifecycle** — `draft` (structure + scores editable) → `published`
  (scores only, roster frozen) → `locked` (nothing, until an `assessment.manage`
  holder `unlock()`s it). An assessment with recorded scores, and any locked one,
  cannot be deleted. Assignments: `draft` → `published` → `closed`, with
  `unpublish` / `reopen`.
- **`App\Support\Assessment\AssessmentAuthorizer`** — `assessment.manage` → any
  class + subject; `assessment.record` only → a `(level, subject)` the teacher
  holds an active M11 `TeacherAssignment` for. Assignments *scope* teachers, not
  a dependency (a Staff-off school records through `assessment.manage` holders).
- **`App\Http\Controllers\Assessment\*`** (5 controllers), `/assessments/*`
  routes behind `['tenant', 'module:assessments']`, gated
  `assessment.view` / `assessment.record` / `assessment.manage` (new M4
  permissions). `Module::Assessments->isAvailable()` is now `true` (**on by
  default**); it **depends on `Module::Academics` and `Module::Students`** — not
  Timetable / Attendance / Results / CBT. `assessment.manage` added to Principal.
- List screens (filters + pagination), Alpine-cascade create forms, draft-only
  edit forms, detail with lifecycle controls, mobile-first bulk score /
  completion sheets, and inline category CRUD.

### Deferred

- Queue jobs capture/restore the tenant id (no jobs exist yet — see
  `docs/tenancy.md` §7).
- Invitations / brand-new-account onboarding, school suspension / subscription,
  notification & payment config, the remaining domain modules behind the M7
  catalogue (fees, CBT, portals, promotion workflow, bulk import), a
  rooms/facilities module + timetable templates, the Parent Portal (guardian
  sign-in) and Teacher Portal (teacher sign-in), non-teaching staff records,
  admin UI for `status` / `is_platform_admin`, subdomain routing.

## 2m. Results & report cards (implemented — Milestone 15)

Full reference: **`docs/results-report-cards.md`**. Turns M14's locked
assessment scores into configurable student results and printable report
cards. No CBT/Question Bank, no student/parent portal views, no promotion,
no PDF library, no full audit-trail platform.

- **`App\Models\GradingScheme` + `GradingSchemeGrade`** — school-configured
  percentage bands (non-overlapping among active bands, unique code per
  scheme). `gradeFor(float): ?GradingSchemeGrade` is the **only** place a
  percentage becomes a grade — never typed directly into a result row.
- **`App\Models\ResultWeightingScheme` + `ResultWeightingSchemeItem`** — ties
  an M14 `AssessmentCategory` to a `weight_percentage`; must sum to exactly
  100% (checked server-side before a run can use the scheme).
- **`App\Models\ResultRun`** — one class, one term (`unique(session, period,
  level, arm)`, never spans terms). `status`
  (`draft`→`compiled`→`reviewed`→`approved`→`published`→`locked`) and every
  `*_by`/`*_at` pair **not** mass-assignable — only the lifecycle methods
  (`review()`/`approve()`/`publish()`/`lock()`) change them.
- **`App\Models\StudentResult` / `StudentSubjectResult` /
  `StudentSubjectResultComponent`** — compiled results, computed once by
  `App\Services\Results\ResultCompiler` and **snapshotted** (percentage,
  grade, raw score, weight) so they survive a later grading/weighting-scheme
  edit or assessment-category rename unchanged.
- **`App\Services\Results\ResultCompiler`** — all-or-nothing: computes fully
  in memory first, collects every missing-score issue, and writes **nothing**
  if any exist (never manufactures a missing score as zero). Bulk `insert`s
  and a `CASE id WHEN ... END` bulk-update helper — never a query per
  student.
- **`App\Support\Results\RankingCalculator`** — pure-PHP competition ranking
  (`1,2,2,4`), scoped to the run's own roster, never the whole school.
- **`App\Models\ResultAdjustment`** — a controlled propose → apply/reject
  correction workflow for an approved-or-later run; a proposal alone changes
  nothing; applying re-derives the grade via `gradeFor()` and triggers a full
  ranking recompute. No bulk "unlock" of an approved+ run.
- **`App\Models\ReportCardConfiguration`** — 24 independent typed `show_*`
  booleans (not a JSON blob); school-wide/session/term scope with documented
  precedence; a second `result_run_id`-tagged row is the frozen snapshot a
  **locked** run's report card renders from (an unlocked, even published,
  run always reflects the *live* configuration). The same Blade view renders
  both the live preview and the final output.
- **`App\Http\Controllers\Results\*`** (8 controllers), `/results/*` routes
  behind `['tenant', 'module:results']`, gated `result.view` / `.enter` /
  `.manage` / `.publish` / `.adjust`. `Module::Results->isAvailable()` is now
  `true` (**on by default**); it **depends on `Module::Assessments` only** —
  not Timetable / Attendance / CBT. `result.manage` + `result.adjust` are new;
  `result.view` / `.enter` / `.publish` reuse M4's original scaffolding.

### Deferred

Question Bank / CBT engine, Entry/Placement Assessment admin UI, student
portal result views (parent views ship in M16), promotion & graduation,
advanced transcripts, automatic report-card comments, a full drag-and-drop
report-card designer, PDF export (browser print for now), per-level
report-card overrides, bulk unlock of an approved+ run, per-run
signature-image snapshotting, student photo capture, a full audit-trail /
retention workflow.

## 2n. Parent Portal (implemented — Milestone 16)

Full reference: **`docs/parent-portal.md`**. A secure, read-only,
child-scoped window for a signed-in parent onto their own children's
published data. No communication hub, no fee/payment visibility, no Student
Portal, no full audit trail.

- **`Guardian.user_id`** — new, additive column (M10's own migration
  untouched); nullable, unique per school, **not** mass-assignable, set only
  through `GuardianController::updateUser()` — mirrors `Teacher.user_id`
  (M11) exactly. A `User` is not automatically a `Guardian`.
- **`App\Support\Portal\ParentPortalAuthorizer`** — the single seam every
  portal controller uses: `guardianFor()`, `studentsFor()`,
  `authorizedStudent()`. Tenant-scoped for free (`Guardian` is
  `BelongsToSchool` — no second tenancy mechanism); a student id is never
  trusted from the URL until proven to be one of that guardian's own linked
  children via M10's `guardian_student` link — no second student-parent
  table.
- **`App\Services\Results\ReportCardRenderer`** — extracted from M15's own
  `Results\ReportCardController` during this milestone (identical
  behaviour; M15's own test suite passed unchanged). The school/staff report
  card and the Parent Portal's report card share this **one** renderer —
  they differ only in *who may ask for which run/student*, never in how the
  report card itself is built.
- **Result & report-card visibility** — `ResultRunStatus::
  visibleToParents()` (new, small, additive method): only `published` /
  `locked` runs are ever shown to a parent. M15 has no dedicated
  parent-visibility flag; this is the documented, safest interpretation.
  `AssignmentStatus::visibleToParents()` similarly excludes `draft`
  assignments.
- **`App\Http\Controllers\Portal\*`** (8 thin controllers, one concern
  each), `/parent/*` routes behind `['tenant', 'module:parent-portal']`,
  gated `->can('portal.parent')` — **no new permission**, `Permission::
  PortalParent` was declared since M4. `Module::ParentPortal->isAvailable()`
  is now `true` (**on by default**); it **depends on `Module::Guardians`
  only**. A Parent-role member is redirected from `/dashboard` straight to
  `/parent`; the main nav renders a portal-specific link set for them.
- Attendance/assignments/timetable/results all degrade to a clean, rendered
  empty state (not a 404) when their underlying module is off, checked via
  `SchoolModules` inside the controller — a route-level `module:` gate on
  those specific pages would have 404'd the whole child page instead of
  degrading just that one section.
- Profile (`/parent/profile`) is deliberately **read-only** — M10 remains
  the source of truth for guardian data; no write route exists for a parent
  to self-edit their guardian record or M10 relationships.

### Deferred

A communication hub (WhatsApp/SMS/email — this milestone is the foundation
it plugs into), fee/payment visibility (no finance module yet), the Student
Portal (kept deliberately separate — no shared "generic portal" abstraction
was introduced), a full platform audit trail of parent access events, parent
self-service editing of guardian contact details, push notifications.

## 3. Application layers & conventions

| Layer | Directory | Responsibility |
|-------|-----------|----------------|
| Routing | `routes/web.php`, `routes/auth.php` | thin; names every route; auth flow split into its own file |
| Controllers | `app/Http/Controllers` (`Auth/`, `Settings/`, `Platform/`) | HTTP orchestration only; resourceful naming |
| Form Requests | `app/Http/Requests` (`Auth/`, `Settings/`, `Platform/`) | validation + request-scoped authorization |
| Policies | `app/Policies` | model authorization, invoked server-side |
| Services | `app/Services` | multi-step business operations (`SchoolProvisioner`) |
| Models | `app/Models` | persistence, casts, mass-assignment guards, scopes |
| Enums | `app/Enums` | closed value sets (`UserStatus`, `SchoolStatus`, `Role`, `Permission`), backed by string columns / static bundles |
| Middleware | `app/Http/Middleware` | cross-cutting request guards (`EnsureAccountIsActive`, `EnforceTenant`) |
| Providers | `app/Providers` | `AppServiceProvider` (tenant seam, model strictness), `AuthServiceProvider` (Gate abilities per permission, policy map) |
| Support | `app/Support` | framework-agnostic helpers; `Support\Tenancy` = tenant context, `BelongsToSchool` trait, `SchoolScope`, exceptions |
| Views | `resources/views/<area>` | pages |
| UI components | `resources/views/components` | layouts + design-system primitives |

Authentication is documented in full in `docs/authentication.md`.

Deliberately **not** adopted now: repository pattern, a DTO for every payload,
a generic "BaseService", event sourcing. Introduce an abstraction when a second concrete
use case demands it, and record the decision here.

## 4. Frontend architecture

Blade renders HTML. Tailwind v4 (configured in `resources/css/app.css` via
`@theme`, no `tailwind.config.js`). Alpine.js provides the small amount of
interactivity the shell needs (nav drawer, dropdowns, confirm dialogs) and is
loaded/started in `resources/js/app.js`. Vite builds both.

No SPA framework. If a future feature genuinely needs one, that decision is
recorded here first with its trade-offs.

### Application shell

`resources/views/components/layouts/app.blade.php`:

- **Desktop:** fixed left sidebar (`lg:` breakpoint) + sticky top header +
  scrollable `<main>`.
- **Mobile:** compact header with a hamburger; sidebar becomes an off-canvas
  drawer toggled by Alpine (`sidebarOpen`) with a backdrop.
- Navigation is injected via the `navigation` slot — role-specific menus are a
  later milestone, not baked in here.

`resources/views/components/layouts/guest.blade.php` is a centred card layout for
pre-auth screens.

## 5. Health & observability

- `/up` — Laravel's built-in framework boot check.
- `/health` — `HealthController`, returns JSON `{status, time, checks:{database}}`
  with HTTP 200/503. Unauthenticated, side-effect free, leaks no config. Suitable
  for load-balancer and uptime-monitor probes.

## 6. Decision log

| Date | Decision | Why |
|------|----------|-----|
| 2026-09-10 | Shared DB + logical `school_id` tenancy | scale to many thousands of schools without schema sprawl |
| 2026-09-10 | `TenantContext` as the single tenant seam | isolation becomes framework wiring, not developer discipline |
| 2026-09-10 | Alpine.js added; no SPA framework | shell needs minimal interactivity only |
| 2026-09-10 | UI primitives as anonymous Blade components | simple, no build-time component registry |
| 2026-09-10 | No Spatie Permission / Paystack / Redis yet | not needed by the foundation; belong to later milestones |
| 2026-09-10 | Auth hand-rolled on Laravel primitives; no Breeze/Fortify/Jetstream | a starter package brings a conflicting UI/Tailwind layer for controllers we own anyway (see `docs/authentication.md` §13) |
| 2026-09-10 | Account status = `users.status` string enum, gated at login + per-request middleware | three fixed states; login-only checks leave suspended sessions live |
| 2026-09-10 | `verified` middleware on the app group only; `password.confirm` on account deletion only | users must be able to verify / leave; avoid unnecessary password prompts |
| 2026-09-10 | Role/permission system still deferred | belongs to its own milestone; direction documented in `docs/authentication.md` §11 |
| 2026-09-11 | `SchoolScope` throws on missing tenant context (fail closed) | an unscoped query would leak every school's data |
| 2026-09-11 | `creating` hook forces `school_id` from context; `school_id` immutable on update | `school_id` cannot be spoofed via mass assignment or explicit set |
| 2026-09-11 | Many-to-many `User`↔`School`; `is_platform_admin` boolean, no `Gate::before` | multi-campus staff are real; platform admins still act within a chosen tenant, not around it |
| 2026-09-11 | School context in the session, re-validated every request | stateless-friendly; tampering with the stored id is inert |
| 2026-09-12 | Roles/permissions in-house (enums), not Spatie laravel-permission | Spatie's Teams adds a second ambient tenant id competing with `TenantContext`; our permission set is static/code-defined (see `docs/authorization.md` §2) |
| 2026-09-12 | Each permission is a Gate ability; still **no `Gate::before`** | `$user->can()` / `@can` / route `->can()` all work and stay tenant-composed; platform admins get school-admin reach *inside* an entered school only |
| 2026-09-12 | One role per (user, school), tier-based escalation guard | matches "roles = bundles"; invariant "never grant a role above your own" is simple and testable |
| 2026-09-13 | Provisioning is platform-level; school-owned onboarding data is configured in-context | "operate through controlled school context when modifying school-owned data" — `/admin` only creates the shell |
| 2026-09-13 | Add-existing-user reuses `member.assign-role` + `canGrantRole`; academic sessions gated by `school.settings.*` | smallest permission surface; the year container is configuration, `academics.*` stays dormant for its milestone |
| 2026-09-13 | Tenant-owned route params resolved by id in-controller, not route-model-bound | binding runs before the `tenant` middleware, so a `BelongsToSchool` bind would hit `SchoolScope` with no context |
| 2026-09-14 | School settings expanded as typed columns on `SchoolSetting`, three sectioned pages; logo on the private disk behind a gated no-path route | validated/queryable, no JSON blob or second settings system; prevents logo traversal / cross-tenant access (see `docs/school-settings.md`) |
| 2026-09-10 | Module catalogue is `App\Enums\Module`; `school_modules` is override-only; reuse `school.settings.*` | modules are behaviour reviewed as code; a new school writes nothing; activation is configuration, not a new permission (see `docs/module-activation.md`) |
| 2026-09-10 | Module activation is orthogonal to authorization — `module:` middleware + `->can()` are both required on a domain route | turning a feature on must never grant a permission; M4 stays the sole authority on "may this user…" |
| 2026-09-16 | Academic sessions moved from `school.settings.*` to `academics.*` + `module:academics`; `AcademicPeriod` / `LevelArm` are `BelongsToSchool` in their own right | sessions/periods/levels/subjects are one structure under one permission; child models stay tenant-safe without the parent in the join (see `docs/academic-foundation.md`) |
| 2026-09-16 | No hard delete for academic entities — only `is_active` | preserves referential integrity for the modules built on top; deletion / archival is a later concern |
| 2026-09-17 | Current class is the one `active` `Enrollment`, never a column on `students` | history is first-class; "where now" and "where before" are one model; promotion later just adds rows (see `docs/student-management.md`) |
| 2026-09-17 | Two status enums (`StudentStatus` vs `EnrollmentStatus`); student `status` not mass-assignable | student↔school vs one-placement are distinct; lifecycle changes get one dedicated, auditable seam |
| 2026-09-17 | `Module::Students` depends on `Module::Academics` | enrollment is meaningless without sessions/levels — a minimal, correct extension of the M7 catalogue |
| 2026-09-18 | Student ↔ guardian is a dedicated `GuardianStudent` link model (carries `school_id` + both FKs), not a bare pivot | the link carries behaviour (`relationship`, `is_primary`, `makePrimary()`) and must be `BelongsToSchool` and tenant-safe in its own right (see `docs/guardian-management.md`) |
| 2026-09-18 | One primary guardian per student, enforced transactionally (not a partial unique index); links created from the student workflow | portable across MySQL/SQLite; matches how a school thinks about "this child's parents"; no unbounded student picker |
| 2026-09-18 | Guardians store contact data only — no ID/financial/medical/emergency data, no portal credentials | "do not collect unnecessary sensitive information"; sign-in is the Parent Portal's concern, a later milestone |
| 2026-09-19 | Teacher record is separate from `User`; `user_id` nullable, set via a dedicated endpoint, linked only to an existing member | a teacher is a professional record first, not automatically a login; M11 builds no invitation / credential / portal flow (see `docs/teacher-management.md`) |
| 2026-09-19 | Teaching assignments are a `TeacherAssignment` model (session req, period/arm opt, subject req); duplicate-active check in the Form Request, not a DB constraint | history is first-class and the Timetable / Attendance / Results modules extend one model; a hard unique key would over-constrain future scheduling |
| 2026-09-19 | `Module::Staff` depends on `Module::Academics` | a teaching assignment is meaningless without sessions / levels / subjects — a minimal, correct extension of the M7 catalogue |
| 2026-09-20 | Timetable = a `Timetable` (session-scoped) + `TimetableEntry` (lesson) pair; session/period on the parent only | lessons structurally can't cross sessions; the entry stays lean for the Attendance module to extend (see `docs/timetable-management.md`) |
| 2026-09-20 | Lesson times are `HH:MM` strings + half-open `[start, end)`; overlap checks are DB existence queries / one self-join, never loaded into PHP | portable string comparison across SQLite/MySQL; back-to-back lessons don't clash; conflict detection stays index-bound on a busy board |
| 2026-09-20 | `room` is plain text, not a FK / facilities module; `Module::Timetable` depends on Academics + Staff and stays off by default | M12 is scheduling, not facilities management; a timetable needs the academic structure *and* teachers-with-assignments; timetable is a specialised opt-in (unchanged M7 intent) |
| 2026-09-21 | Attendance = an `AttendanceRegister` (one class, one day) + `AttendanceRecord` (one student's mark) pair; a **daily** register, not per-lesson; `Module::Attendance` depends on Academics + Students, **never Timetable** | every school needs a daily register and must record attendance with the timetable off; a per-lesson register can be layered on later as an optional enhancement without schema change |
| 2026-09-21 | One `AttendanceStatus` enum (`present`/`absent`/`late`/`excused`); a record's `status` is **nullable** (unmarked) and a register can't be submitted while any is unmarked | a single status column beats a spread of `is_present` booleans; the unmarked state + submit guard is the safe operational default — an absent child is never silently recorded present |
| 2026-09-21 | Eligibility is the enrollment date-range (`[started_on, ended_on]` contains the register date), not current status; the roster is snapshotted at creation | historical registers stay correct when a student later withdraws / changes arm / graduates; the register records who was in the class *that day* |
| 2026-09-21 | Locked registers are corrected by an `attendance.manage` `reopen()`, not an approval workflow or an audit-log system | the spec forbids a full audit trail here; `recorded_by`/`submitted_by` timestamps preserve the structure a later audit feature needs without building it now |
| 2026-09-22 | Assessment categories are a per-school `AssessmentCategory` table, not a hard-coded enum | "CA / Test / Exam" differ by school and country; a table lets each school name, order and retire its own; uniqueness stays school-scoped |
| 2026-09-22 | `Assessment` context (session/period/level/arm/subject/date) is fixed at creation; a draft edits title/category/instructions, and max-score too — but max-score freezes once any score is recorded (raising or lowering it silently rescales entered scores) | mirrors the timetable's fixed session — a moved assessment would orphan its snapshotted roster and its eligibility basis; recreate instead |
| 2026-09-22 | Scores are `decimal(6,2)`, **nullable**, bounded `0..max_score` in the Form Request (max lives on the parent); **no** grade / % / average / rank / GPA column anywhere | M14 stores source data only; M15 computes results from it without a schema change; a DB CHECK against a parent column isn't portable |
| 2026-09-22 | Assessment lifecycle `draft → published → locked`; `unlock` is `assessment.manage` only | draft = still configuring, published = definition settled + scores flowing, locked = finalized; an authorized unlock (not an approval workflow) is the controlled correction path |
| 2026-09-22 | `Assignment` is separate from `Assessment` — it carries no score, tracks completion via `AssignmentSubmission`, and only *optionally* links to an assessment (`assessments.assignment_id`) | an assignment is set work; grading it is a distinct act; keeping them separate lets M15 decide if/how an assignment contributes to a result without reworking M14 |
| 2026-09-22 | Assignment file attachments deferred — no `attachment_path` in M14 | the only file handling today is the M6 private-disk logo; safe uploads need a private disk + gated per-file download route + retention, which is its own piece of work |
| 2026-09-23 | `AssessmentPurpose` / `ScoreSource` added to M14's tables via **new, additive migrations** rather than editing the original M14 migrations | keeps "don't reopen completed milestones" while letting the schema grow forward-compatibly for a future CBT/import pipeline (see `docs/results-report-cards.md` §13) |
| 2026-09-23 | Subjects entering a `ResultRun` are derived from which subjects actually have a **locked, academic-purpose** M14 assessment in the run's exact class/term — never the full `level_subject` catalogue | a subject nobody has assessed yet this term should not appear at all, let alone as a missing-score error |
| 2026-09-23 | Compilation computes everything in memory first and writes nothing if any score is missing anywhere | "never manufacture a missing score as zero" is a correctness requirement, not just a UX nicety — a partial write would be worse than no write |
| 2026-09-23 | Class position is a pure-PHP competition-ranking helper, not a SQL `RANK()` window function | portable across the app's MySQL and the test suite's SQLite |
| 2026-09-23 | A result correction is a `ResultAdjustment` propose→apply/reject workflow, not a direct field edit, and there is no bulk "unlock" of an approved+ run | keeps `result.adjust` from becoming a general editing bypass once numbers are meant to be frozen; a wholesale re-mark stays a deliberate, visible, per-subject act |
| 2026-09-23 | `ReportCardConfiguration` is 24 independent typed boolean columns, not a JSON blob; a **second row** (`result_run_id` set) is the frozen per-run snapshot, taken at publish, used only once the run is **locked** | typed/relational stays queryable and matches every other model in the app; distinguishing "live" from "historical" by an extra row (not a duplicated column set on `ResultRun`) keeps one schema doing both jobs, at the cost of every live-scope query needing an explicit `whereNull('result_run_id')` guard |
| 2026-09-23 | Signature *images* are not copied into the per-run snapshot — only the show/hide toggle freezes | re-signing after a staff change is expected to be more common than needing a pixel-identical historical signature; documented as a reviewable trade-off |
| 2026-09-24 | `Guardian.user_id` added via a **new, additive migration** rather than editing M10's original `guardians` migration | keeps "don't reopen completed milestones" while letting the schema grow forward-compatibly, exactly the pattern M15 used for `assessments.purpose`/`assessment_scores.source` |
| 2026-09-24 | Result/report-card visibility for parents uses `ResultRunStatus::visibleToParents()` (publication status alone), not a new dedicated flag on `ResultRun` | M15 has no such flag; adding one would mean reopening M15 for a single caller when the existing lifecycle already expresses "is this finished" precisely enough |
| 2026-09-24 | `App\Services\Results\ReportCardRenderer` extracted from M15's `ReportCardController` rather than writing a second, parent-specific report-card query | "one secure renderer, authorization differs" avoids the two views drifting apart over time — a real bug (a stale staff-only back-link) surfaced immediately from sharing the exact same Blade template |
| 2026-09-24 | The Parent Portal's "selected child" is **not** a second global tenant context — every child-scoped route names the student explicitly in its own URL | keeps URLs shareable/bookmarkable and re-validates ownership on every request; avoids inventing session-based portal state alongside the existing `TenantContext` |
| 2026-09-24 | A parent's guardian profile (`/parent/profile`) is read-only; no write route was built for guardian self-edit | M10 remains the source of truth for guardian data; a self-edit workflow (with its own validation/authorization) was judged out of scope for "the smallest clean changes necessary" |
