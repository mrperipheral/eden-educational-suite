# Student Portal

Status: **Milestone 17 — complete.** A secure, read-only window for a
signed-in student onto their own published data: profile, results, report
cards, attendance, assignments, timetable. Mirrors the Parent Portal (M16,
`docs/parent-portal.md`) but for the student's own record directly — no
"which child" question, since a student has at most one linked `Student`
record. Built on the same seams: `TenantContext` + `BelongsToSchool` +
`Permission` + `module:student-portal`, the M2 `User` account, and
M9/M12/M13/M14/M15's own data. No second authentication system, no second
tenancy mechanism, no second report-card generator.

## 1. Account linkage

```
User (M2 login)
  ↓  Student.user_id  (nullable, set only via StudentController::updateUser())
Student (M9 person record)
```

`Student` gained one additive column, `user_id` (migration
`2026_09_25_100000_add_user_id_to_students_table.php` — M9's own migration
untouched), nullable, unique per school, **not** mass-assignable. Set only
through the admin-facing `PATCH /students/{student}/user`
(`LinkStudentUserRequest`, `student.manage`) — identical in shape to
`Guardian.user_id` (M16) / `Teacher.user_id` (M11). The student never
controls this link themselves. A `User` is not automatically a `Student`.

## 2. Authorization: `App\Support\Portal\StudentPortalAuthorizer`

```php
$authorizer->studentFor($user): ?Student                 // this user's own Student, in the active school
$authorizer->authorizedStudent($user, $studentId): ?Student  // that student, only if it's really them
```

`studentFor()` queries `Student::where('user_id', $user->id)` —
tenant-scoped for free (`Student` is `BelongsToSchool`). Unlike the Parent
Portal there is no "which of several" question: a student has **at most
one** linked record, so every controller resolves it directly from the
authenticated user and never accepts a student id from the URL at all — the
Student Portal's routes carry **no `{student}` parameter**. The only ids
students ever supply are `{run}` (for results/report cards), which are
independently re-checked (`where('student_id', $student->id)`) against their
own resolved record.

## 3. Routes

```
GET  /student                         student.dashboard
GET  /student/profile                 student.profile.edit
GET  /student/results                 student.results.index
GET  /student/results/{run}           student.results.show
GET  /student/report-cards            student.report-cards.index
GET  /student/report-cards/{run}      student.report-cards.show
GET  /student/attendance              student.attendance.index
GET  /student/assignments             student.assignments.index
GET  /student/timetable               student.timetable.index
```

Two gates: `module:student-portal` (404 when off) + `->can('portal.student')`
— **no new permission**, `Permission::PortalStudent` was declared since M4.
7 thin controllers under `App\Http\Controllers\Portal\Student*Controller`.

## 4. Reuse (no domain logic duplicated)

- **Results & report cards** — `ResultRunStatus::visibleToParents()` (shared
  with M16; published/locked only) and `App\Services\Results\
  ReportCardRenderer` (the same renderer the school/staff side and the
  Parent Portal both use). The shared report-card view's back-link gained a
  third, `portal.student`-aware branch.
- **Attendance** — `App\Support\Results\AttendanceSummarizer` (M15),
  unchanged.
- **Assignments** — M14's `AssignmentSubmission`, scoped
  `where('student_id', $student->id)`; `AssignmentStatus::
  visibleToParents()` (shared with M16) excludes drafts. **View only** — M14
  has no online submission workflow; building one is explicitly deferred,
  not redesigned here.
- **Timetable** — the child's *current* enrollment resolves a **published**
  M12 `Timetable`, filtered to its `level_arm_id`; a draft is never shown.

Every section (Results/Report cards/Attendance/Assignments/Timetable)
degrades to a clean, rendered empty state — not a 404 — when its own module
is off, checked via `SchoolModules` inside the controller.

## 5. Profile

`/student/profile` is **read-only** — M9 remains the source of truth; no
self-edit route exists. Login identity (name/email/password) is the `User`
account at the existing `/settings/profile`.

## 6. Tenant isolation

Every query the portal issues goes through an already-`BelongsToSchool`
model; nothing here introduces a second tenancy mechanism. Feature tests
prove: a student sees only their own profile; a `{run}` that doesn't include
them 404s even when it exists and is published; a run/report card from
another school 404s; a suspended/disabled account cannot authenticate at
all; a Student-role account cannot reach any management route
(`/students`, `/guardians`, `/results/runs`, `/settings/school`, …).

## 7. Navigation

The main authenticated nav branches to a Student-specific link set
(Dashboard · My Profile · Results · Report cards · Attendance · Assignments
· Timetable · Account settings) for a Student-role viewer, each entry hidden
when its module is off — no dead links. `DashboardController` redirects a
Student-role member from `/dashboard` straight to `/student`.

## 8. Performance

Query-count regression tests prove the dashboard and profile pages cost the
same regardless of how many *other* students the school has, and that
`StudentPortalAuthorizer` never scans the full student table.

## 9. Deferred

Everything M16 defers (communication hub, fees, Student/CBT overlap, a full
audit trail, push notifications) plus: online assignment submission (M14 has
no submission upload workflow yet — this milestone only ever *views*
assignment/submission status), any generic "portal" abstraction shared with
the Parent Portal (kept deliberately separate), a mobile app/API.
