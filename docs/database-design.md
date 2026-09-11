# Database Design

Status: Milestone 15. Tenant + roles + onboarding + school settings + module
activation + academic foundation + student management + guardian management +
teacher management + timetable management + attendance management + assessment &
assignments + results & report cards. School-owned tables: `school_settings`
(M6), `school_modules` (M7), the academic structure — `academic_sessions`,
`academic_periods`, `academic_levels`, `level_arms`, `subjects`,
`level_subject` (M8) — `students` + `enrollments` (M9), `guardians` +
`guardian_student` (M10), `teachers` + `teacher_assignments` (M11),
`timetables` + `timetable_entries` (M12), `attendance_registers` +
`attendance_records` (M13), `assessment_categories`, `assignments`,
`assessments`, `assessment_scores`, `assignment_submissions` (M14), and
`grading_schemes`, `grading_scheme_grades`, `result_weighting_schemes`,
`result_weighting_scheme_items`, `result_runs`, `student_results`,
`student_subject_results`, `student_subject_result_components`,
`result_adjustments`, `report_card_configurations` (M15). No fees tables yet.
This document records the conventions every future migration follows.

## Current schema

| Table | Purpose |
|-------|---------|
| `users` | auth identities. `id, name, email (unique), email_verified_at, password, status, is_platform_admin, remember_token, timestamps` |
| `schools` | tenant root. `id, name, slug (unique), status, timestamps` |
| `school_user` | User↔School membership + per-school `role`. PK `(school_id, user_id)`, index `(school_id, role)`, cascade both ways |
| `school_settings` | per-school config (1:1). `school_id` unique. School-owned. Profile (contact + address), branding (`logo_path`, `brand_color`), regional (`timezone, locale, currency, date_format, week_starts_on, academic_year_start_month`). |
| `academic_sessions` | a school's academic years. School-owned. `unique(school_id, name)`, `index(school_id, starts_on)`. One `is_current` per school. |
| `academic_periods` | terms / semesters within a session. School-owned **+** `academic_session_id`. `unique(session_id, name)`, `unique(session_id, position)`. One `is_current` per session. |
| `academic_levels` | classes / year groups. School-owned. `unique(school_id, name/code/position)`. |
| `level_arms` | streams within a level. School-owned **+** `academic_level_id`. `unique(level_id, name/code/position)`. |
| `subjects` | school subjects. School-owned. `unique(school_id, name)`, `unique(school_id, code)`. |
| `level_subject` | which subjects a level offers. School-owned. `unique(academic_level_id, subject_id)`, `index(school_id, academic_level_id)`. |
| `students` | student records. School-owned. `unique(school_id, admission_number)`, `index(school_id, status)`, `index(school_id, last_name, first_name)`. Never hard-deleted. |
| `enrollments` | a student's academic placement over time. School-owned **+** `student_id`. `index(school_id, student_id, status)`, roster index `(school_id, session, level, arm)`. One `active` row per student. |
| `guardians` | parent / guardian contact records. School-owned. `index(school_id, last_name, first_name)`, `index(school_id, phone)`, `index(school_id, email)`. No global uniqueness; minimal contact data; never hard-deleted. |
| `guardian_student` | student ↔ guardian link. School-owned **+** `student_id` **+** `guardian_id`. `unique(student_id, guardian_id)`, `index(school_id, student_id, is_primary)`, `index(school_id, guardian_id)`. `relationship`, `is_primary` (at most one per student). |
| `teachers` | teacher professional records. School-owned. Optional `user_id` FK (`nullOnDelete`). `unique(school_id, employee_number)`, `unique(school_id, user_id)`, `index(school_id, status)`, `index(school_id, last_name, first_name)`. Minimal professional data (no ID / financial / medical / credential fields); never hard-deleted. |
| `teacher_assignments` | a teacher's teaching assignment over time. School-owned **+** `teacher_id`. FKs to session (req) / period (opt) / level (req) / arm (opt) / subject (req). `index(school_id, teacher_id, status)`, class-roster index `(school_id, session, level, arm)`, `index(school_id, subject_id)`. `status` (`active` / `ended`); history preserved. |
| `timetables` | a weekly schedule. School-owned. `academic_session_id` (req, fixed) + optional `academic_period_id`. `status` (`draft`/`published`, not mass-assignable), `published_at`. `index(school_id, session, period)`, `index(school_id, status)`. |
| `timetable_entries` | one scheduled lesson. School-owned **+** `timetable_id`. FKs level / arm / subject / teacher (all required — scheduled per class). `weekday`, `start_time`/`end_time` (`HH:MM` strings, half-open), optional text `room`. Overlap indexes on `(school_id, timetable_id, {weekday|teacher|arm|room}, …)`. |
| `attendance_registers` | one class's attendance for one day. School-owned. FKs session (req) / period (opt) / level (req) / arm (req); `attendance_date`; `status` (`draft`/`submitted`, not mass-assignable), `submitted_at`, `submitted_by`. `unique(school_id, level_arm_id, attendance_date)`, `index(school_id, attendance_date)`, `index(school_id, session, period)`, `index(school_id, status)`. Not tied to the timetable. |
| `attendance_records` | one student's mark on a register. School-owned **+** `attendance_register_id`. `student_id` FK; `status` (nullable — null = unmarked; `present`/`absent`/`late`/`excused`), text `note`, `recorded_at`, `recorded_by`. `unique(attendance_register_id, student_id)`, `index(school_id, student_id)`, `index(school_id, attendance_register_id, status)`. Never hard-deleted for historical reasons. |
| `assessment_categories` | school-configured category ("Classwork", "Test", …). School-owned. `unique(school_id, name)`, `unique(school_id, code)`, `index(school_id, is_active, position)`. Deactivated, not deleted. |
| `assessments` | a gradeable assessment for one class + subject. School-owned. FKs session / period / level / arm / subject / category (all required), optional `assignment_id`. `title`, `assessment_date`, `max_score` (`decimal(6,2)`), `instructions`, `status` (`draft`/`published`/`locked`, not mass-assignable), `published_at`, `locked_at`, `locked_by`, `created_by`. Indexes `(school_id, session, period)`, `(school_id, level, arm)`, `(school_id, subject_id)`, `(school_id, category)`, `(school_id, status)`, `(school_id, assessment_date)`. **No** global uniqueness — many assessments of a category on different dates are valid. No derived grade / percentage / rank columns. |
| `assessment_scores` | one student's score in an assessment. School-owned **+** `assessment_id`. `student_id` FK; `score` (`decimal(6,2)`, nullable — null = not entered), `comment`, `recorded_at`, `recorded_by`. `unique(assessment_id, student_id)`, `index(school_id, student_id)`, `index(school_id, assessment_id)`. Bounds (`0..max_score`, 2 dp) enforced in the Form Request. Never hard-deleted. |
| `assignments` | a piece of set work for a class + subject. School-owned. FKs session / period / level / arm / subject (all required), optional `teacher_id` (owner, `nullOnDelete`). `title`, `instructions`, `assigned_on`, `due_on`, optional `max_score`, `status` (`draft`/`published`/`closed`, not mass-assignable), `published_at`, `created_by`. Indexes `(school_id, session, period)`, `(school_id, level, arm)`, `(school_id, subject_id)`, `(school_id, teacher_id)`, `(school_id, status)`, `(school_id, due_on)`. Holds no scores. |
| `assignment_submissions` | whether one student turned an assignment in. School-owned **+** `assignment_id`. `student_id` FK; `status` (`pending`/`submitted`/`late`/`exempt`, default `pending`), `submitted_on`, `remark`, `recorded_at`, `recorded_by`. `unique(assignment_id, student_id)`, `index(school_id, student_id)`, `index(school_id, assignment_id, status)`. Completion only — no score column. Never hard-deleted. |
| `grading_schemes` | a school-configured percentage-band scale. School-owned. `unique(school_id, name)`, `index(school_id, is_active)`. |
| `grading_scheme_grades` | one band of a grading scheme. School-owned **+** `grading_scheme_id`. `code`, `min_percentage`/`max_percentage` (`decimal(5,2)`), `remark`, `position`, `is_active`. `unique(grading_scheme_id, code)`, `index(school_id, grading_scheme_id, position)`. Non-overlapping among active bands (Form Request), not a DB constraint. |
| `result_weighting_schemes` | a school-configured category-weighting scale. School-owned. `unique(school_id, name)`, `index(school_id, is_active)`. |
| `result_weighting_scheme_items` | one category's weight. School-owned **+** `result_weighting_scheme_id`. `assessment_category_id` FK, `weight_percentage` (`decimal(5,2)`), `position`. `unique(result_weighting_scheme_id, assessment_category_id)` (short FK index name — see the migration), `index(school_id, result_weighting_scheme_id)`. Must sum to 100% per scheme (Form Request, not a DB constraint). |
| `result_runs` | one class's compiled results for one term. School-owned. FKs session / period / level / arm (all required) + grading/weighting scheme (`restrictOnDelete`). `ranking_enabled`, `status` (`draft`→`locked`, not mass-assignable), 5 `*_by`/`*_at` lifecycle pairs. `unique(school_id, session, period, level, arm)` — never spans terms. `index(school_id, status)`. |
| `student_results` | a student's overall result within a run. School-owned **+** `result_run_id`. `student_id` FK; totals/average/position/class_size, overall grade snapshot, `class_teacher_comment`/`principal_comment`, attendance rollup — all computed/snapshotted, not editable except the two comment columns. `unique(result_run_id, student_id)`, `index(school_id, student_id)`, ranking index `(school_id, result_run_id, position)`. |
| `student_subject_results` | a student's per-subject result within a run. School-owned **+** `result_run_id`. `student_id`/`subject_id` FKs; `percentage`, grade snapshot, `subject_position`, `is_adjusted` + `adjusted_by`/`adjusted_at`. `unique(result_run_id, student_id, subject_id)`, `index(school_id, student_id)`, `index(school_id, result_run_id, subject_id)`. |
| `student_subject_result_components` | one weighted category's contribution to a subject result. School-owned **+** `student_subject_result_id` (short FK index name — see the migration). `assessment_category_id` (`nullOnDelete`), `category_name_snapshot`, `weight_percentage_snapshot`, `raw_score`/`raw_max_score`, `score_percentage`, `weighted_contribution`, `position` — every value snapshotted at compile time. `index(school_id, student_subject_result_id)`. |
| `result_adjustments` | a proposed correction to a subject result. School-owned **+** `student_subject_result_id`. `field` (default `percentage`), `original_value`/`adjusted_value`, `reason`, `status` (`pending`/`applied`/`rejected`), `requested_by`/`requested_at` (required, not nullable), `decided_by`/`decided_at`. `index(school_id, student_subject_result_id)`, `index(school_id, status)`. A row alone changes nothing — only `apply()` does. |
| `report_card_configurations` | which report-card fields are visible. School-owned. Nullable `academic_session_id`/`academic_period_id` (scope) + nullable, unique `result_run_id` (a per-run frozen snapshot, distinguished from the live scope rows sharing the same null/null scope only by this column). 24 `show_*` booleans (default all true), `principal_signature_path`/`class_teacher_signature_path` (M6 private-disk pattern). `unique(school_id, academic_session_id, academic_period_id)` — backstops only the fully-specific case; "at most one per exact scope" is enforced at the application layer (`ReportCardConfiguration::exactScopeRow()`), like M8's `AcademicSession::makeCurrent()`, since MySQL/SQLite treat two `NULL`s as distinct in a composite unique index. |
| `school_modules` | per-school feature-module on/off overrides. School-owned. `unique(school_id, module)`. Override-only — a row exists only where a school departs from the `App\Enums\Module` default. |
| `password_reset_tokens`, `sessions` | auth/session plumbing |
| `cache`, `cache_locks` | `CACHE_STORE=database` |
| `jobs`, `job_batches`, `failed_jobs` | `QUEUE_CONNECTION=database` |
| `migrations` | Laravel bookkeeping |

Engine: MySQL 8 / MariaDB, InnoDB, `utf8mb4`.

### `2026_09_10_120000_add_status_to_users_table`
`users.status` — `string(20)`, default `'active'`, **indexed**. `App\Enums\UserStatus`.

### `2026_09_11_100000_create_schools_table`
Tenant root. `status` `string(20)` default `'active'` **indexed**
(`App\Enums\SchoolStatus`: `active` / `suspended`). `slug` unique, used as the
route key. Not tenant-scoped.

### `2026_09_11_100010_create_school_user_table`
Membership pivot. Composite PK `(school_id, user_id)` covers "members of school";
the `user_id` FK index covers "schools for user". No role column (later milestone).

### `2026_09_11_100020_add_is_platform_admin_to_users_table`
`users.is_platform_admin` boolean default false. Not indexed (tiny cardinality),
not mass-assignable. The platform-owner primitive — see `docs/tenancy.md` §2.

### `2026_09_12_100000_add_role_to_school_user_table`
`school_user.role` — `string(30)` **nullable** (`App\Enums\Role`: `school_admin`
/ `principal` / `bursar` / `teacher` / `staff` / `parent` / `student`). One role
per (user, school); `null` = member with no permissions. Index `(school_id, role)`
for the "members with role X" query. Not mass-assignable — written only via
`User::joinSchool()` / `assignRoleInSchool()`. See `docs/authorization.md`.

### `2026_09_13_100000_create_school_settings_table`
1:1 with `schools` (`school_id` **unique** — both the tenant key and the
constraint). `timezone` (default `Africa/Lagos`), `locale` (default `en`),
`contact_email` / `contact_phone` (nullable), `completed_at` (nullable — set on
first save, drives the onboarding checklist). School-owned (`BelongsToSchool`).
See `docs/onboarding.md`.

### `2026_09_14_100000_add_configuration_to_school_settings_table`
Milestone 6 — expands `school_settings` (still 1:1, no new index needed). Typed
columns, not a JSON blob. Adds: `address_line1/2`, `city(120)`, `state(120)`,
`postal_code(20)`, `country char(2)` default `NG`, `website_url`, `logo_path`
(**guarded** — written only by `SchoolSetting::putLogo()`), `brand_color char(7)`,
`currency char(3)` default `NGN`, `date_format(20)` default `d/m/Y`,
`week_starts_on tinyint` default `1` (0=Sun…6=Sat), `academic_year_start_month
tinyint` default `9`. Column defaults, the model `$attributes`, and
`config('school-settings.defaults')` are kept in sync. See `docs/school-settings.md`.

### `2026_09_13_100010_create_academic_sessions_table`
`name` (`unique(school_id, name)`), `starts_on` / `ends_on` (dates), `is_current`
(bool, at most one per school — enforced in `AcademicSession::makeCurrent()`).
`index(school_id, starts_on)` for the list. School-owned. M8 builds the term /
level / subject structure on top (below); the sessions table itself is unchanged.

### `2026_09_16_100000_*` — Academic Foundation (Milestone 8)
Five migrations, all school-owned (`BelongsToSchool`), all indexes leading with
`school_id` (or a tenant-scoped parent id). See `docs/academic-foundation.md` §3.

- **`academic_periods`** — `academic_session_id` FK (cascade), `name`,
  `starts_on`/`ends_on`, `position`, `is_active`, `is_current`.
  `unique(academic_session_id, name)`, `unique(academic_session_id, position)`,
  `index(school_id, academic_session_id, position)`. Any number per session.
- **`academic_levels`** — `name`, `code`, `position`, `is_active`.
  `unique(school_id, name)` / `(school_id, code)` / `(school_id, position)`.
- **`level_arms`** — `academic_level_id` FK (cascade), `name`, `code`,
  `position`, `is_active`. `unique(academic_level_id, name/code/position)`,
  `index(school_id, academic_level_id, position)`.
- **`subjects`** — `name`, `code`, `description` (nullable), `position`
  (non-unique), `is_active`. `unique(school_id, name)` / `(school_id, code)`.
- **`level_subject`** — `academic_level_id` + `subject_id` FKs (cascade),
  `school_id` (written from `TenantContext` during the sync).
  `unique(academic_level_id, subject_id)`, `index(school_id, academic_level_id)`,
  `index(school_id, subject_id)`. The only cross-model link M8 ships.

### `2026_09_17_100000_*` — Student Management (Milestone 9)
Two migrations, both school-owned (`BelongsToSchool`). See `docs/student-management.md`.

- **`students`** — `first_name` / `last_name` (req), `middle_name` /
  `preferred_name`, `date_of_birth`, `gender` (`App\Enums\Gender`),
  `admission_number` (req), `admitted_on`, `status` (`App\Enums\StudentStatus`,
  default `active`, **not** mass-assignable), `contact_email` / `contact_phone` /
  `address_line1/2` / `city` / `state`, `notes`.
  `unique(school_id, admission_number)`, `index(school_id, status)`,
  `index(school_id, last_name, first_name)`. Minimal PII; never hard-deleted.
- **`enrollments`** — `student_id` FK (cascade), `academic_session_id` /
  `academic_level_id` FK (cascade, required), `academic_period_id` /
  `level_arm_id` FK (`nullOnDelete`, optional), `status`
  (`App\Enums\EnrollmentStatus`, default `active`), `started_on`, `ended_on`.
  `index(school_id, student_id, status)`, roster index
  `(school_id, academic_session_id, academic_level_id, level_arm_id)`. One
  `active` row per student (enforced in `Enrollment::makeActive()`). History is
  preserved — placements are closed, never deleted.

### `2026_09_18_100000_*` — Guardian Management (Milestone 10)
Two migrations, both school-owned (`BelongsToSchool`). See `docs/guardian-management.md`.

- **`guardians`** — `first_name` / `last_name` (req), `middle_name` /
  `preferred_name`, `email`, `phone`, `alt_phone`, `address_line1/2` / `city` /
  `state`, `notes`. `index(school_id, last_name, first_name)`,
  `index(school_id, phone)`, `index(school_id, email)`. No uniqueness — a
  guardian has no natural school-owned identifier. Minimal contact data only (no
  ID / financial / medical / emergency data, no credentials); never hard-deleted.
- **`guardian_student`** — `student_id` FK (cascade) + `guardian_id` FK
  (cascade), `relationship` (`App\Enums\GuardianRelationship`), `is_primary`
  (bool, default false). `unique(student_id, guardian_id)` (no duplicate links),
  `index(school_id, student_id, is_primary)`, `index(school_id, guardian_id)`.
  One `is_primary` row per student (enforced in `GuardianStudent::makePrimary()`).

### `2026_09_19_100000_*` — Teacher Management (Milestone 11)
Two migrations, both school-owned (`BelongsToSchool`). See `docs/teacher-management.md`.

- **`teachers`** — `first_name` / `last_name` (req), `middle_name` /
  `preferred_name`, `employee_number` (req), `email`, `phone`, `employed_on`,
  `status` (`App\Enums\TeacherStatus`, default `active`, **not** mass-assignable),
  `address_line1/2` / `city` / `state`, `notes`, `user_id` (nullable FK to
  `users`, `nullOnDelete`, **not** mass-assignable).
  `unique(school_id, employee_number)`, `unique(school_id, user_id)` (many
  `NULL`s allowed), `index(school_id, status)`,
  `index(school_id, last_name, first_name)`. Minimal professional data — no
  identity / financial / medical / credential columns. Never hard-deleted.
- **`teacher_assignments`** — `teacher_id` FK (cascade), `academic_session_id` /
  `academic_level_id` / `subject_id` FK (cascade, required), `academic_period_id`
  / `level_arm_id` FK (`nullOnDelete`, optional), `status`
  (`App\Enums\TeacherAssignmentStatus`, default `active`), `started_on`,
  `ended_on`. `index(school_id, teacher_id, status)`, class-roster index
  `(school_id, academic_session_id, academic_level_id, level_arm_id)`,
  `index(school_id, subject_id)`. Duplicate **active**
  `(teacher, session, period, level, arm, subject)` rejected in the Form Request,
  not by a DB constraint. History preserved — an assignment is `ended`, never
  deleted (the `DELETE` route stays for a mis-entered row).

### `2026_09_20_100000_*` — Timetable Management (Milestone 12)
Two migrations, both school-owned (`BelongsToSchool`). See `docs/timetable-management.md`.

- **`timetables`** — `academic_session_id` FK (cascade, required, fixed at
  creation), `academic_period_id` FK (`nullOnDelete`, optional), `name`,
  `status` (`App\Enums\TimetableStatus` `draft` / `published`, default `draft`,
  **not** mass-assignable), `published_at` (nullable, **not** mass-assignable).
  `index(school_id, academic_session_id, academic_period_id)`,
  `index(school_id, status)`. Several timetables per session allowed (no
  uniqueness — history); a published one can't be deleted.
- **`timetable_entries`** — one lesson. `timetable_id` FK (cascade),
  `academic_level_id` / `level_arm_id` / `subject_id` / `teacher_id` FK (cascade,
  all **required** — a lesson is scheduled *per class*, so `level_arm_id` is not
  nullable here unlike M9/M11), `weekday` (`unsignedTinyInteger`,
  `App\Enums\Weekday`), `start_time` / `end_time` (`string(5)`, `HH:MM` — **not**
  a `TIME` type, so string comparison is portable and unambiguous), `room`
  (`string(60)`, nullable, plain text — not a facilities module). No
  `academic_session_id` / `academic_period_id` — inherited from the parent
  timetable so lessons can't cross sessions. Overlap indexes:
  `(school_id, timetable_id, weekday, start_time)`,
  `(school_id, timetable_id, teacher_id, weekday)`,
  `(school_id, timetable_id, level_arm_id, weekday)`,
  `(school_id, timetable_id, room, weekday)`. All scheduling rules (half-open
  `[start, end)` overlap, subject↔level, an active teacher assignment) are
  enforced in `TimetableEntryRequest`; the publish guard runs one self-join scan.

### `2026_09_21_100000_*` — Attendance Management (Milestone 13)
Two migrations, both school-owned (`BelongsToSchool`). See `docs/attendance-management.md`.

- **`attendance_registers`** — one class's attendance for one day.
  `academic_session_id` FK (cascade, required), `academic_period_id` FK
  (`nullOnDelete`, optional), `academic_level_id` FK (cascade, required),
  `level_arm_id` FK (cascade, **required** — a register is one class/arm),
  `attendance_date` (`date`), `status` (`App\Enums\AttendanceRegisterStatus`
  `draft` / `submitted`, default `draft`, **not** mass-assignable), `notes`
  (`string(255)`, nullable), `submitted_at` (nullable, **not** mass-assignable),
  `submitted_by` FK to `users` (`nullOnDelete`, **not** mass-assignable).
  `unique(school_id, level_arm_id, attendance_date)` (one register per class per
  day), `index(school_id, attendance_date)`,
  `index(school_id, academic_session_id, academic_period_id)`,
  `index(school_id, status)`. No `timetable_id` — attendance is independent of
  the timetable.
- **`attendance_records`** — one student's mark. `attendance_register_id` FK
  (cascade), `student_id` FK (cascade), `status` (`string(15)`, **nullable** —
  null = unmarked; `App\Enums\AttendanceStatus` otherwise), `note` (`string(255)`,
  nullable), `recorded_at` (nullable), `recorded_by` FK to `users`
  (`nullOnDelete`). `unique(attendance_register_id, student_id)` (no duplicate
  student on a register), `index(school_id, student_id)` (a student's history),
  `index(school_id, attendance_register_id, status)` (summary counts). Eligibility
  (an active `enrollments` row for the register's exact session/level/arm whose
  date range contains `attendance_date`) is enforced in the Form Requests and
  `AttendanceRegister::eligibleStudents()`, not by a DB constraint. Records are
  never hard-deleted — historical attendance survives a student leaving.

### `2026_09_22_100000_*` — Assessment & Assignments (Milestone 14)
Five migrations, all school-owned (`BelongsToSchool`). See `docs/assessment-management.md`.

- **`assessment_categories`** — `name`, `code` (`string(20)`, nullable,
  upper-cased), `description`, `position` (`unsignedSmallInteger`), `is_active`.
  `unique(school_id, name)`, `unique(school_id, code)`,
  `index(school_id, is_active, position)`.
- **`assignments`** — created **before** `assessments` so the optional
  `assessments.assignment_id` FK resolves. Context FKs session / period / level /
  arm / subject (cascade, all required), `teacher_id` FK (`nullOnDelete`,
  nullable — owner), `title`, `instructions` (`text`), `assigned_on` / `due_on`
  (`date`), `max_score` (`decimal(6,2)`, nullable), `status`
  (`App\Enums\AssignmentStatus`, default `draft`, **not** mass-assignable),
  `published_at`, `created_by` FK to `users` (`nullOnDelete`). Indexes lead with
  `school_id` (scope / class / subject / teacher / status / due_on). No score
  column.
- **`assessments`** — context FKs session / period / level / arm / subject /
  `assessment_category_id` (cascade, all required), `assignment_id` FK
  (`nullOnDelete`, optional), `title`, `assessment_date` (`date`), `max_score`
  (`decimal(6,2)`, **required**), `instructions` (`text`), `status`
  (`App\Enums\AssessmentStatus`, default `draft`, **not** mass-assignable),
  `published_at` / `locked_at` / `locked_by` / `created_by` (all **not**
  mass-assignable). Indexes `(school_id, session, period)`, `(school_id, level,
  arm)`, `(school_id, subject_id)`, `(school_id, category)`, `(school_id,
  status)`, `(school_id, assessment_date)`. **No** unique constraint — repeated
  assessments of the same category on different dates are legitimate. **No**
  `final_grade` / `percentage` / `subject_average` / `position` / `gpa` — M15
  derives those.
- **`assessment_scores`** — `assessment_id` FK (cascade), `student_id` FK
  (cascade), `score` (`decimal(6,2)`, **nullable** — null = not entered),
  `comment` (`string(500)`), `recorded_at`, `recorded_by` FK (`nullOnDelete`).
  `unique(assessment_id, student_id)`, `index(school_id, student_id)`,
  `index(school_id, assessment_id)`. Score bounds (`0..assessments.max_score`,
  2 dp) enforced in `ScoreRequest`, not by a DB constraint (the maximum lives on
  the parent). Never hard-deleted.
- **`assignment_submissions`** — `assignment_id` FK (cascade), `student_id` FK
  (cascade), `status` (`string(15)`, `App\Enums\AssignmentSubmissionStatus`,
  default `pending`), `submitted_on` (`date`, nullable), `remark` (`string(500)`),
  `recorded_at`, `recorded_by` FK (`nullOnDelete`).
  `unique(assignment_id, student_id)`, `index(school_id, student_id)`,
  `index(school_id, assignment_id, status)`. Completion only — no score column.
  Never hard-deleted.

The eligibility rule for both roster snapshots (an active `enrollments` row for
the exact session/level/arm whose date range contains the assessment /
assigned-on date) lives in `App\Models\Concerns\HasClassRoster`, not a DB
constraint.

### `2026_09_23_100000_*` — Results & Report Cards (Milestone 15)
Twelve migrations, all school-owned (`BelongsToSchool`). See
`docs/results-report-cards.md`.

- **`add_purpose_to_assessments_table`** / **`add_source_to_assessment_scores_table`**
  — two **additive** migrations onto M14's tables (the original M14 migrations
  are untouched): `assessments.purpose` (`string(20)`, default `academic`,
  `App\Enums\AssessmentPurpose`), `assessment_scores.source` (`string(15)`,
  default `manual`, `App\Enums\ScoreSource`). Both indexed `(school_id, ...)`.
- **`grading_schemes`** / **`grading_scheme_grades`** — `name`/`description`/
  `is_default`/`is_active` on the scheme; `code`/`min_percentage`/
  `max_percentage` (`decimal(5,2)`)/`remark`/`position`/`is_active` on each
  grade. `unique(school_id, name)` on the scheme, `unique(grading_scheme_id,
  code)` on grades. Non-overlapping active bands enforced in the Form
  Request, not a DB constraint.
- **`result_weighting_schemes`** / **`result_weighting_scheme_items`** — same
  scheme shape; each item ties one `assessment_category_id` to a
  `weight_percentage` (`decimal(5,2)`). `unique(result_weighting_scheme_id,
  assessment_category_id)` — FK given a short explicit index name
  (`weighting_scheme_items_scheme_fk`) to stay under MySQL's 64-character
  identifier limit. Must sum to 100% (Form Request).
- **`result_runs`** — context FKs session / period / level / arm (all
  required) + `grading_scheme_id`/`result_weighting_scheme_id`
  (`restrictOnDelete` — a scheme in use can't be deleted out from under a
  run), `ranking_enabled` (default true), `status` (`App\Enums\ResultRunStatus`,
  default `draft`, **not** mass-assignable), 5 nullable `*_by`(FK)/`*_at`
  lifecycle pairs. `unique(school_id, session, period, level, arm)` — one run
  per class per term. `index(school_id, status)`.
- **`student_results`** — `result_run_id` FK (cascade), `student_id` FK
  (cascade), `total_percentage`/`average_percentage` (`decimal`),
  `class_teacher_comment`/`principal_comment` (`text`, nullable — the only
  human-editable columns), `subject_count`, overall grade snapshot columns,
  `position` (nullable), `class_size`, attendance rollup columns (all
  nullable — null when the class has no submitted attendance data).
  `unique(result_run_id, student_id)`, `index(school_id, student_id)`,
  ranking index `(school_id, result_run_id, position)`.
- **`student_subject_results`** — `result_run_id`/`student_id`/`subject_id`
  FKs (cascade), `percentage`, `grading_scheme_grade_id` (`nullOnDelete`) +
  grade snapshot columns, `subject_position` (nullable), `is_adjusted`
  (default false) + `adjusted_by`/`adjusted_at`. `unique(result_run_id,
  student_id, subject_id)`, `index(school_id, student_id)`,
  `index(school_id, result_run_id, subject_id)`.
- **`student_subject_result_components`** — `student_subject_result_id` FK
  (cascade, short explicit index name `ssr_components_result_fk` for the same
  64-character reason as above), `assessment_category_id` (`nullOnDelete`,
  nullable), `category_name_snapshot`, `weight_percentage_snapshot`,
  `raw_score`/`raw_max_score` (`decimal(6,2)`, nullable), `score_percentage`,
  `weighted_contribution`, `position`. Every value is a **snapshot** taken at
  compile time — none of it is re-derived from the category/scheme at render
  time. `index(school_id, student_subject_result_id)`.
- **`result_adjustments`** — `student_subject_result_id` FK (cascade),
  `field` (default `percentage`), `original_value`/`adjusted_value`
  (`decimal(5,2)`), `reason` (`text`, required), `status`
  (`App\Enums\ResultAdjustmentStatus`, default `pending`), `requested_by` FK
  (cascade, **required**, not nullable) + `requested_at` (**required**),
  `decided_by`/`decided_at` (nullable). `index(school_id,
  student_subject_result_id)`, `index(school_id, status)`.
- **`report_card_configurations`** — nullable `academic_session_id`/
  `academic_period_id` (the live scope) + nullable, **unique**
  `result_run_id` (`constrained`, `cascadeOnDelete` — the per-run frozen
  snapshot), `name` (default `'Default'`), 24 `show_*` booleans (all default
  true), `principal_signature_path`/`class_teacher_signature_path` (nullable
  string — M6 private-disk pattern). `unique(school_id,
  academic_session_id, academic_period_id)` backstops only the fully-specific
  (non-null) case — see the model note in the table listing above for why
  "one row per scope" is an application-layer rule, not solely a DB one.

The eligible-student rule for a run (an active `enrollments` row for the
exact session/level/arm as of the term's `ends_on`) reuses
`App\Models\Concerns\HasClassRoster` (M13/M14's trait) via
`ResultRun::rosterDate()`.

### `2026_09_15_100000_create_school_modules_table`
Milestone 7 — per-school feature/module activation. `module` (`string(40)`, an
`App\Enums\Module` value, **not** cast so an unknown id can't break a page),
`enabled` (bool). `unique(['school_id','module'])` is both the 1-per-module
constraint and the lookup index (`school_id` leads). **Override-only**: absence
of a row means "use `Module::enabledByDefault()`", so a freshly onboarded school
writes nothing. School-owned (`App\Models\SchoolModule` uses `BelongsToSchool`);
written only via `App\Support\Modules\SchoolModules`. See `docs/module-activation.md`.

## Multi-tenant conventions (ACTIVE — enforced by `BelongsToSchool` from M3 on)

1. **`school_id` on every school-owned table.**
   `$table->foreignId('school_id')->constrained()->cascadeOnDelete();`
   Reference data that is genuinely global (e.g. country list) is the only
   exception and must be called out in review.

2. **Composite indexes lead with `school_id`.** Every query is tenant-scoped, so
   the tenant key belongs first:
   `$table->index(['school_id', 'created_at']);`
   `$table->unique(['school_id', 'admission_number']);`
   Uniqueness is almost always *per school*, never global.

3. **Foreign keys stay within the same tenant.** A `class_id` on a table that
   also has `school_id` must point at a `classes` row with the same `school_id`.
   Enforced by the `BelongsToSchool` global scope at the application layer and by
   validation rules scoped to `TenantContext::id()`. Never add `school_id` to a
   model's `$fillable` — the `BelongsToSchool` trait is the only writer.

4. **No cross-tenant leakage through nullable FKs or polymorphic types** without
   an explicit tenant check.

5. **Timestamps** on every table. **Soft deletes** only where there is a real
   recovery/audit need (not by default).

6. **Money** stored as integer minor units (kobo) + a currency column, never
   floats. Nigeria-first but currency is explicit from day one.

7. **Enums** as short strings with a DB-level `check`/`enum` or an app cast, not
   magic integers.

8. **Times of day** (not timestamps) stored as zero-padded `HH:MM` strings
   (`string(5)`), not a `TIME` column — string comparison is then correct and
   identical across SQLite and MySQL, which matters for range/overlap queries
   (first used by `timetable_entries`, M12).

9. **Migrations are additive and reversible.** No editing a shipped migration;
   add a new one. `down()` is implemented.

## Indexing checklist for new tables

- [ ] `school_id` first column of the primary lookup index
- [ ] FK columns indexed (Laravel does this for `constrained()`)
- [ ] columns used in `WHERE` / `ORDER BY` on list screens covered
- [ ] unique constraints scoped to `school_id`
- [ ] no unbounded `SELECT *` list endpoint without pagination

## Not yet designed (later milestones, will be added here)

`school_user.is_default`, holiday / calendar events, non-teaching staff, class
rosters, results / report cards / grading schemes, fees/invoices/payments, CBT,
rooms/facilities, audit log. Each gets an entry here when built.

Permissions and roles are **not** in the database — they are code (`App\Enums`).
