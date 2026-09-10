# Student Management

Status: **Milestone 9 — complete.** The tenant-scoped student record and
enrollment-history foundation the later Guardian, Attendance, Assessment,
Results, Fees, Promotion and Portal modules build on. Built on the M3/M4/M8
seams — `TenantContext` + `BelongsToSchool`, `App\Enums\Permission`,
`module:students`. No new authorization or tenancy mechanism, no new packages,
no Redis/queues.

M9 is **records only**: who the students are and where they have been placed.
It does *not* build guardians, teachers, attendance, assessments, results, fees,
promotion, CBT, portals, or bulk import.

## 1. The pieces

| Concern | Model | Table | Belongs to |
|---------|-------|-------|------------|
| Student record | `App\Models\Student` | `students` | school |
| Academic placement over time | `App\Models\Enrollment` | `enrollments` | school **+** student |

Enums: `App\Enums\StudentStatus`, `App\Enums\EnrollmentStatus`, `App\Enums\Gender`.
Controllers: `App\Http\Controllers\Student\{Student,Enrollment}Controller`.
Form Requests: `App\Http\Requests\Student\*`. Views: `resources/views/students/*`.

```
School
 └── Student                       status: active | inactive | withdrawn | graduated
       └── Enrollment (history)    one `active` per student = the current class
             ├── AcademicSession   (required)
             ├── AcademicPeriod    (optional)
             ├── AcademicLevel     (required)
             └── LevelArm          (optional)
```

## 2. Routes

All under `Route::middleware(['tenant', 'module:students'])->prefix('students')`.
Every route carries **both** gates:

- `module:students` — is the feature on for this school? Off ⇒ 404. (`students`
  now **depends on** `academics` — see §6.)
- `->can('student.view')` (reads) / `->can('student.manage')` (writes).

| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/students` (`?q=` search, `?status=` filter) | `students.index` | `student.view` |
| GET/POST | `/students/create` · `/students` | `students.create` / `.store` | `student.manage` |
| GET | `/students/{student}` | `students.show` | `student.view` |
| GET/PATCH | `/students/{student}[/edit]` | `students.edit` / `.update` | `student.manage` |
| PATCH | `/students/{student}/status` | `students.status` | `student.manage` |
| GET/POST | `/students/{student}/enrollments[/create]` | `students.enrollments.create` / `.store` | `student.manage` |
| GET/PATCH | `/students/enrollments/{enrollment}[/edit]` | `students.enrollments.edit` / `.update` | `student.manage` |

Tenant-owned ids (`{student}`, `{enrollment}`) are **not** route-model-bound —
they are resolved in the controller with `Model::query()->findOrFail($id)`
(tenant-scoped, runs after the `tenant` middleware), so another school's id 404s.
The enrollment Form Request also `abort(404)`s in `prepareForValidation` if the
route's student / enrollment is not a record of the active school — so a
cross-school route id never reaches the rules (whose "invalid" messages would
otherwise be a weak oracle for another school's academic ids).

## 3. Student record

`students` — school-owned (`BelongsToSchool`). `school_id` is stamped from the
tenant context and is **never** in `$fillable` / read from input.

| Column | Notes |
|--------|-------|
| `first_name`, `last_name` | required, ≤ 60 |
| `middle_name`, `preferred_name` | optional, ≤ 60. `preferred_name` is shown in lists when set. |
| `date_of_birth` | optional; `before:today`, sane floor |
| `gender` | optional; `App\Enums\Gender` (`male` / `female` / `other`) — a school records it only if it needs to |
| `admission_number` | required; **`unique(school_id, admission_number)`** — per school, never global |
| `admitted_on` | optional date |
| `status` | `App\Enums\StudentStatus`, default `active` — **not mass-assignable**; changed only via `PATCH /students/{student}/status` |
| `contact_email`, `contact_phone`, `address_line1/2`, `city`, `state` | optional — a way to reach the family |
| `notes` | optional free text, ≤ 2000 |

**Minimal PII by design.** Nothing on identity grounds — no religion,
ethnicity, national ID, medical/blood group, previous school, or photo. Those
are not M9's concern and add legal/consent burden without a current use.

**No hard delete.** A student who leaves is `withdrawn` / `graduated`; the
record and its enrollment history stay. `active` / `inactive` count as "on the
roll" (`StudentStatus::isEnrolled()`).

Indexes: `unique(school_id, admission_number)`, `index(school_id, status)`,
`index(school_id, last_name, first_name)`.

## 4. Enrollment history

`enrollments` — school-owned (`BelongsToSchool`) **and** scoped to its student.
`student_id` is set from the parent relation on create and never changes.

| Column | Notes |
|--------|-------|
| `academic_session_id` | **required** — `Rule::exists(...)->where('school_id', <tenant>)` |
| `academic_period_id` | optional ("where appropriate") — must belong to the chosen session |
| `academic_level_id` | **required** — tenant-scoped `exists` |
| `level_arm_id` | optional ("where applicable") — must belong to the chosen level |
| `status` | `App\Enums\EnrollmentStatus` (`active` / `completed` / `withdrawn`) |
| `started_on` | required date |
| `ended_on` | optional; `after_or_equal:started_on`; **required unless** `status = active` |

- **The current class is derived, never stored on `students`.** It is the one
  `Enrollment` with `status = active` (`Student::currentEnrollment()`).
- **One active enrollment per student** — `Enrollment::makeActive()` (a
  transaction that closes any other open enrollment: `status = completed`,
  `ended_on` filled). This is the M8 `makeCurrent()` pattern. It is a "one class
  at a time" invariant, **not** a promotion / bulk roll-over workflow — that is
  deferred.
- On store, `make_active` (a checkbox, default on) decides whether the new row
  becomes current; a historical placement can be recorded with it unchecked.
- **Consistency checks** (`EnrollmentRequest::withValidator`): the
  period↔session and arm↔level pairs must line up. A mismatched or cross-school
  id fails with a plain "invalid" / "not part of the selected …" message — no
  information leak.

Indexes: `index(school_id, student_id, status)` (history / current lookup),
`index(school_id, academic_session_id, academic_level_id, level_arm_id)` (future
class rosters).

## 5. Relationships (only what M9 + the next modules need)

- `School` → `students` (`HasMany`)
- `Student` → `enrollments` (`HasMany`), `currentEnrollment` (`HasOne`, `status = active`)
- `Enrollment` → `student`, `session`, `period`, `level`, `arm` (`BelongsTo`)

No guardian, teacher, attendance, assessment, result or fee relationships are
created. The parent academic records are resolved tenant-safely: the `BelongsTo`
relations go through each M8 model's `SchoolScope`, and the list / profile
screens eager-load them.

## 6. Module activation

`/students/*` sits behind `module:students`. `Module::Students->isAvailable()` is
now **`true`** and its default is **on**.

**New in M9:** `Module::Students` now **depends on `Module::Academics`** —
enrollment has nothing to point at without sessions and levels. A school cannot
disable Academics while Students is on, and cannot enable Students unless
Academics is on (M7 dependency validation). Both are default-on, so the catalogue
stays consistent.

Module activation and authorization stay independent: the Students module being
on does not give a Parent `student.view` (tested).

## 7. Performance

- `unique(school_id, admission_number)` → exact student-number lookup.
- List: `paginate(25)`, name-ordered on an index, `LIKE` search across
  `first_name` / `last_name` / `preferred_name` / `admission_number`, status
  filter on an index. `currentEnrollment.level` / `.arm` are eager-loaded — the
  list is a small constant number of queries regardless of page size (tested).
- Profile: all enrollments + their four academic relations loaded in one pass.
- No full-population loads; no Redis, no queues, no new packages.

## 8. Decisions

| Decision | Why |
|----------|-----|
| Two status enums — `StudentStatus` (student ↔ school) and `EnrollmentStatus` (one placement) | related but distinct: a `graduated` student can have a `completed` last enrollment |
| Current class is the one `active` enrollment, never a column on `students` | history is first-class; "where is this student now" and "where have they been" are one model, and promotion later just adds rows |
| `Enrollment::makeActive()` enforces one active placement | the M8 `makeCurrent()` pattern; a "one class at a time" invariant, not a workflow |
| `students.status` not mass-assignable; changed via a dedicated endpoint | keeps the demographic edit form single-purpose and gives lifecycle changes one auditable seam |
| No hard delete for students or enrollments | referential integrity for every module built on top; a leaver is a status change |
| `academic_period_id` / `level_arm_id` nullable | not every school runs terms or streams; "where appropriate / applicable" |
| `Module::Students` depends on `Module::Academics` | enrollment is meaningless without the academic structure; a minimal, correct extension of the M7 catalogue |
| Enrollment Form Request `abort(404)`s on a cross-school parent id | a 404 (not a validation response) is the right non-leaking answer, matching the M8 tenant-safe-resolution rule |
| Minimal PII (name / DOB / optional gender / admission / contact / notes) | "do not collect unnecessary sensitive information" — everything else is a later, consent-gated concern |

## 9. Deferred

- **Guardians / parents** — the linkage, the screens, contact-of-record. (M9's
  student `contact_*` fields are a stopgap.)
- Teacher assignment, class rosters, attendance, assessments, results, grading,
  fees — each its own module.
- **Promotion / graduation workflow** — bulk term roll-over, "promote class X to
  level Y", repeat-year handling.
- Bulk student import / CSV, student ID card / photo, document attachments,
  medical & emergency contact info, transfer-in / transfer-out records.
- A `unique(student_id, academic_session_id)` enrollment constraint (a student
  appearing once per session) — left flexible for now.
- Soft deletes / data-erasure (GDPR-style) handling, audit trail of record and
  status changes.
- Student Portal (`module:student-portal`).
