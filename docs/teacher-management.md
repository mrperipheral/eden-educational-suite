# Teacher Management

Status: **Milestone 11 — complete.** The tenant-scoped teacher record, an
optional link to an application account, and the teaching-assignment foundation
the later Timetable, Attendance, Assessment and Results modules build on. Built
on the M3/M4/M7/M8 seams — `TenantContext` + `BelongsToSchool`,
`App\Enums\Permission`, `module:staff` — no new authorization or tenancy
mechanism, no new packages, no Redis/queues.

M11 is **records + assignment foundation only**. It does **not** build teacher
invitations, password creation, authentication, a teacher portal, timetable,
attendance, assessments, marks/results, payroll or workload calculation.

## 1. The pieces

| Concern | Model | Table | Belongs to |
|---------|-------|-------|------------|
| Teacher record | `App\Models\Teacher` | `teachers` | school (optional `user_id`) |
| Teaching assignment over time | `App\Models\TeacherAssignment` | `teacher_assignments` | school **+** teacher |

Enums: `App\Enums\TeacherStatus`, `App\Enums\TeacherAssignmentStatus`.
Controllers: `App\Http\Controllers\Teacher\{Teacher,TeacherAssignment}Controller`.
Form Requests: `App\Http\Requests\Teacher\*`. Views: `resources/views/teachers/*`.

```
School
 └── Teacher                     status: active | inactive | suspended | resigned
       ├── User (optional)        a professional record is NOT automatically a login
       └── TeacherAssignment      status: active | ended  (history preserved)
             ├── AcademicSession  (required)
             ├── AcademicPeriod   (optional)
             ├── AcademicLevel    (required)
             ├── LevelArm         (optional)
             └── Subject          (required)
```

## 2. Routes

All under `Route::middleware(['tenant', 'module:staff'])->prefix('teachers')`.
Every route carries **both** gates:

- `module:staff` — is the feature on for this school? Off ⇒ 404. (`staff` now
  **depends on** `academics` — see §6.)
- `->can('staff.view')` (reads) / `->can('staff.manage')` (writes).

| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/teachers` (`?q=` search, `?status=` filter) | `teachers.index` | `staff.view` |
| GET/POST | `/teachers/create` · `/teachers` | `teachers.create` / `.store` | `staff.manage` |
| GET | `/teachers/{teacher}` | `teachers.show` | `staff.view` |
| GET/PATCH | `/teachers/{teacher}[/edit]` | `teachers.edit` / `.update` | `staff.manage` |
| PATCH | `/teachers/{teacher}/status` | `teachers.status` | `staff.manage` |
| PATCH | `/teachers/{teacher}/user` | `teachers.user` | `staff.manage` |
| GET/POST | `/teachers/{teacher}/assignments[/create]` | `teachers.assignments.create` / `.store` | `staff.manage` |
| GET/PATCH | `/teachers/assignments/{assignment}[/edit]` | `teachers.assignments.edit` / `.update` | `staff.manage` |
| DELETE | `/teachers/assignments/{assignment}` | `teachers.assignments.destroy` | `staff.manage` |

Tenant-owned ids (`{teacher}`, `{assignment}`) are **not** route-model-bound —
they are resolved in the controller with `Model::query()->findOrFail($id)`
(tenant-scoped, runs after the `tenant` middleware), so another school's id 404s.
`TeacherAssignmentRequest` also `abort(404)`s in `prepareForValidation` if the
route's teacher / assignment is not a record of the active school — so a
cross-school route id never reaches the rules (whose "invalid" messages would
otherwise be a weak oracle for another school's academic ids).

## 3. Teacher record

`teachers` — school-owned (`BelongsToSchool`). `school_id` is stamped from the
tenant context and is **never** in `$fillable` / read from input.

| Column | Notes |
|--------|-------|
| `first_name`, `last_name` | required, ≤ 60 |
| `middle_name`, `preferred_name` | optional, ≤ 60. `preferred_name` is shown in lists when set. |
| `employee_number` | required; **`unique(school_id, employee_number)`** — per school, never global |
| `email`, `phone` | optional — a way to reach the teacher |
| `employed_on` | optional start date |
| `status` | `App\Enums\TeacherStatus`, default `active` — **not mass-assignable**; changed only via `PATCH /teachers/{teacher}/status` |
| `address_line1/2`, `city`, `state` | optional |
| `notes` | optional free text, ≤ 2000 |
| `user_id` | optional FK to `users` — **not mass-assignable**; set only via `PATCH /teachers/{teacher}/user` |

**Minimal professional data by design.** No NIN / BVN / government ID, no
financial / bank / pension data, no medical data, no next-of-kin, no photo, and
**no authentication credentials**. HR / payroll is out of scope.

**No hard delete.** A teacher who leaves is `resigned`; the record and its
assignment history stay (`TeacherStatus::isEmployed()` is `true` for everything
except `resigned`).

Indexes: `unique(school_id, employee_number)`, `unique(school_id, user_id)`
(multiple `NULL`s allowed), `index(school_id, status)`,
`index(school_id, last_name, first_name)`.

## 4. Teacher ↔ User link

A teacher is a **professional record first**. Creating one never creates a login.
`user_id` is nullable and set only through the dedicated endpoint. M11 builds no
invitation flow, no password creation, no authentication and no portal.

- The linked account must be an **existing member of the active school**
  (`Rule::exists('school_user', 'user_id')->where('school_id', <tenant>)`), so a
  stranger or another school's member is rejected with a plain "invalid".
- `unique(school_id, user_id)` — one teacher record per account per school. The
  same person can hold a teacher record (and be linked) in more than one school.
- The FK is `nullOnDelete`: deleting the account leaves the professional record
  intact and unlinked.

The Teacher → User relation exists so the future Attendance / Assessment / Portal
milestones can answer "which teacher is this signed-in user?" without a new
mechanism.

## 5. Teaching assignments

`teacher_assignments` — school-owned (`BelongsToSchool`) **and** scoped to its
teacher. `teacher_id` is set from the parent relation on create and never
changes.

| Column | Notes |
|--------|-------|
| `academic_session_id` | **required** — `Rule::exists(...)->where('school_id', <tenant>)` |
| `academic_period_id` | optional — must belong to the chosen session |
| `academic_level_id` | **required** — tenant-scoped `exists` |
| `level_arm_id` | optional — must belong to the chosen level |
| `subject_id` | **required** — tenant-scoped `exists` |
| `status` | `App\Enums\TeacherAssignmentStatus` (`active` / `ended`) |
| `started_on` | required date |
| `ended_on` | optional; `after_or_equal:started_on`; **required if** `status = ended` |

- **History is preserved.** An assignment that finishes is marked `ended`
  (`ended_on` set) — the row stays. `TeacherAssignment::end()` does this on the
  model; in the UI it is an edit. `DELETE` is a hard delete kept for a
  mis-entered row (guarded by a confirm dialog that points at "set status to
  Ended" instead).
- **Duplicate protection.** A teacher may teach many subjects to many classes,
  but the **same `(teacher, session, period, level, arm, subject)` must not be an
  *active* assignment twice** (`TeacherAssignmentRequest::withValidator`). An
  `ended` duplicate is allowed (re-assignment / history). Deliberately **not** a
  DB constraint — future scheduling needs room.
- **Consistency checks.** The period↔session and arm↔level pairs must line up; a
  mismatch or a cross-school id fails with a plain "invalid" / "not part of the
  selected …" message — no information leak.
- The assignment form is an Alpine cascade: session → term, level → arm, plus an
  independent subject select. **JavaScript filtering is convenience only** — the
  Form Request re-checks every id and every relationship server-side.

Indexes: `index(school_id, teacher_id, status)` (a teacher's assignments /
current ones), `index(school_id, academic_session_id, academic_level_id,
level_arm_id)` (future class rosters — "who teaches this class"),
`index(school_id, subject_id)` ("who teaches this subject").

## 6. Module activation

`/teachers/*` sits behind `module:staff`. `Module::Staff->isAvailable()` is now
**`true`** and its default is **on**.

**New in M11:** `Module::Staff` now **depends on `Module::Academics`** — an
assignment has nothing to point at without sessions, levels and subjects. A
school cannot disable Academics while Staff is on, and cannot enable Staff unless
Academics is on (M7 dependency validation). Both are default-on, so the catalogue
stays consistent.

Module activation and authorization stay independent: the Staff module being on
does not give a Parent `staff.view` (tested).

## 7. Authorization

Reuses the M4 `staff.view` / `staff.manage` permissions — previously declared but
dormant, now enforced.

| Role | Teacher access |
|------|----------------|
| School Admin, Principal | manage (records, status, account link, assignments) |
| Bursar, Teacher, Staff | view only (list, profile, assignments) |
| Parent, Student, role-less | none → 403 |

`Permission::StaffManage` was added to the **Principal** bundle in M11;
`Permission::StaffView` was added to **Bursar**, **Teacher** and **Staff**
(Principal already held `StaffView` from M4). A **Teacher** role holder can
therefore *see* the staff list and profiles but **cannot manage other teachers** —
there is no self-service or escalation path. Every write Form Request re-checks
`staff.manage` in `authorize()`.

## 8. Performance

- List: `paginate(25)`, name-ordered on an index, `LIKE` search across
  `first_name` / `last_name` / `preferred_name` / `employee_number` / `email`,
  status filter on an index. `withCount('activeAssignments')` and a lean
  `user:id,name` eager-load — a small constant number of queries regardless of
  page size (tested).
- Profile: all assignments + their five academic relations, and the linked user,
  loaded in one pass (tested — `< 15` queries for 6 assignments).
- No full-population selects; no Redis, no queues, no new packages.

## 9. Decisions

| Decision | Why |
|----------|-----|
| Teacher record is separate from `User`; `user_id` nullable, set via a dedicated endpoint | "do not automatically make every teacher a login" — the professional record stands alone; a link is added only when access is actually needed |
| Linked user must be a member of the active school; `unique(school_id, user_id)` | tenant-safe linkage; one teacher record per account per school; the same person can teach at two schools |
| `user_id` `nullOnDelete` | deleting an account must not destroy the professional/HR record |
| Two status enums — `TeacherStatus` (employment) and `TeacherAssignmentStatus` (one assignment) | related but distinct, mirroring the M9 Student / Enrollment split |
| `status` / `user_id` not mass-assignable; changed via dedicated endpoints | lifecycle and account-link changes get one focused, auditable seam each; the edit form stays single-purpose |
| A proper `TeacherAssignment` model, not `current_*` columns on `teachers` | history is first-class; the future Timetable / Attendance / Results modules extend one model |
| Session **required**, period / arm optional; subject required | mirrors M9 enrollment; an assignment is meaningful within a school year, and always teaches *something* |
| Duplicate protection in the Form Request, not a DB unique constraint | reject the obvious mistake (same active class+subject twice) without over-constraining future scheduling |
| No hard delete for teachers; `ended` (not delete) is the normal assignment lifecycle | referential integrity for every module built on top; `DELETE` stays only for a mis-entered row |
| `Module::Staff` depends on `Module::Academics` | assignments are meaningless without the academic structure; a minimal, correct extension of the M7 catalogue |

## 10. Deferred

- **Teacher portal** (`module:staff` + a `portal.*`-style permission) — teacher
  sign-in, invitations, password setup. M11 stores **no** credentials.
- Non-teaching staff records (the module is "Teacher / Staff" — M11 ships
  teachers; other staff types can reuse the same table or gain their own later).
- Timetable slots, class registers / attendance, assessment & mark entry,
  results — each its own module, each extending `TeacherAssignment`.
- Teacher payroll, salary, workload / periods-per-week calculation, contracts.
- Qualifications, subjects-qualified-to-teach, certifications, documents, photo.
- Next-of-kin / emergency contact, medical information.
- Bulk teacher import / CSV; a class → teachers roster view ("who teaches JSS1").
- Head-of-department / form-teacher / class-teacher designations.
- Audit trail of record, status and assignment changes.
