# Attendance Management

Status: **Milestone 13 — complete.** A tenant-scoped daily student-attendance
foundation with a draft → submitted (locked) lifecycle. Built on the
M3/M4/M7/M8/M9/M11 seams — `TenantContext` + `BelongsToSchool`,
`App\Enums\Permission`, `module:attendance`, the M9 `Enrollment` history and the
M11 `TeacherAssignment` — no new authorization or tenancy mechanism, no new
packages, no Redis/queues.

M13 is the **attendance foundation**. It does **not** build attendance
percentage analytics, term/monthly reports, chart dashboards, predictive
analytics, notifications, student/parent portal views, a full audit-trail
system, approval workflows, or a per-lesson timetable-driven register. It works
with the **Timetable module turned off**.

## 1. The pieces

| Concern | Model | Table | Belongs to |
|---------|-------|-------|------------|
| One class's attendance for one day | `App\Models\AttendanceRegister` | `attendance_registers` | school |
| One student's mark on a register | `App\Models\AttendanceRecord` | `attendance_records` | school **+** register |

Enums: `App\Enums\AttendanceStatus` (`present` / `absent` / `late` / `excused`),
`App\Enums\AttendanceRegisterStatus` (`draft` / `submitted`).
Controller: `App\Http\Controllers\Attendance\AttendanceRegisterController`.
Form Requests: `App\Http\Requests\Attendance\*` (`AttendanceModuleRequest` base,
`RegisterRequest`, `RecordAttendanceRequest`, `SubmitRegisterRequest`).
Class-scoping rule: `App\Support\Attendance\AttendanceAuthorizer`.
Views: `resources/views/attendance/*`.

```
School
 └── AttendanceRegister                status: draft | submitted
       │  academic_session   (required)
       │  academic_period    (optional — "whole session" when null)
       │  academic_level     (required)
       │  level_arm          (required — a register is one class/arm)
       │  attendance_date    (required — the day being recorded)
       │  notes              (optional free text)
       │  submitted_at / submitted_by  (stamped on submit, cleared on reopen)
       └── AttendanceRecord (one per eligible student, snapshotted at creation)
             ├── student_id     (required)
             ├── status         null = unmarked · present | absent | late | excused
             ├── note           optional free text (max 255)
             └── recorded_at / recorded_by  (stamped when a mark actually changes)
```

**Nothing is hardcoded** — not the term count, the school year, the class names,
or the working days. A register is whatever `(session, period, level, arm, date)`
the school picks, validated against its own academic structure.

## 2. Student eligibility rule

> A student is on a register **iff** they hold an `Enrollment` (M9) for that
> register's **exact `(academic_session_id, academic_level_id, level_arm_id)`**
> whose date range **`[started_on, ended_on]` contains `attendance_date`**
> (`started_on <= date` and (`ended_on IS NULL` or `ended_on >= date`)).

- The enrollment's / student's **current** status is deliberately **not** a
  filter. A student withdrawn *after* the register date was in that class that
  day and stays on that day's register; a student whose enrollment ended
  *before* the date is excluded by the range. This keeps historical registers
  correct when a student later leaves, transfers arm, or graduates.
- The roster is **snapshotted** when the register is created: one
  `AttendanceRecord` per then-eligible student, written in a single bulk
  `insert`. Later enrollment changes do not add or remove rows from an existing
  register — the register is a record of who was in that class on that day.
- Creating a register for a class with **no** eligible student on the date is
  rejected (`level_arm_id` error). Marking a student who is **not** on the
  register's roster is rejected (`records` error) — this is also what blocks a
  cross-school or wrong-class student id.
- `AttendanceRegister::eligibleStudents()` is the single source of the rule;
  `RegisterRequest` re-expresses the same date-range `whereHas` for its
  "class has students" check.

## 3. Statuses

`AttendanceStatus` is one controlled enum column — **never** a spread of
`is_present` / `is_absent` / `is_late` booleans.

| Value | `isAttending()` | Badge |
|-------|-----------------|-------|
| `present` | true | success |
| `absent` | false | danger |
| `late` | true | warning |
| `excused` | false | gray |

A record with **`status = null`** is *unmarked* — it has not been decided yet.
This is the safe default (see §5). There is **no minutes-late / lateness-time
field**: `late` plus the optional free-text `note` is enough for a daily
register, and per-lesson tardiness metrics are out of scope. `note` is a short
optional reason (e.g. "Medical appointment"), capped at 255 characters, and
holds no sensitive data by design.

## 4. Register lifecycle

```
        create ─────────────▶  DRAFT  ──── submit (all students marked) ────▶  SUBMITTED (locked)
                                 ▲                                                    │
                                 └──────────── reopen  (attendance.manage) ───────────┘
```

- **Draft** — the register is being filled in. Any user who may record for the
  class can change marks and notes freely, correct mistakes, and delete the
  whole register (while it has no marks, or after reopening).
- **Submitted** — locked. `PATCH …/records`, `POST …/submit` and `DELETE` are
  refused for normal users (`403` / redirect-with-error). The read screen shows
  a read-only roster and who submitted it when.
- **Reopen** — only an `attendance.manage` holder (School Admin, Principal) can
  return a submitted register to draft (`POST …/reopen`). This clears
  `submitted_at` / `submitted_by`, corrections are made in draft, and the
  register is submitted again. Corrections are therefore **explicit and
  authorized**, never a silent history rewrite. There is **no approval
  workflow** and **no full audit trail** yet — but `recorded_at` / `recorded_by`
  on every record and `submitted_at` / `submitted_by` on the register preserve
  the structure a later audit feature would build on.

## 5. Workflow & the safe default

Create screen: pick session → (optional) period → level → arm → date. On save
the roster is snapshotted. The taking screen then shows every student (name,
admission number, current status) with:

- four single-tap status buttons per student,
- **"Mark all present" / "Clear all"** bulk actions,
- an optional per-student note,
- **"Save draft"** (keep working) and **"Save & submit"** (lock).

**The safer operational design was chosen: students start `unmarked`, not
`present`.** An unmarked student is never counted as present. A register
**cannot be submitted while any record is unmarked** — "Save & submit" only
locks when the whole class has an explicit mark, and otherwise saves the marks
and reports what is missing. This prevents an absent child being silently
recorded present because a row was skipped. Bulk "Mark all present" is one tap
for the common case, and any row can be corrected before saving.

## 6. Timetable integration decision

**Attendance does not integrate with the Timetable module at all, by design.**

- `Module::Attendance` depends on **`Academics` + `Students` only** — never
  `Timetable`. A school with the timetable disabled records attendance normally;
  the routes stay available.
- There is **no `timetable_id` / `timetable_entry_id`** column on
  `attendance_registers` and no lesson picker in the UI. A register is a
  **daily class register**, keyed by `(level_arm, date)` — not a per-lesson one.
- Teacher assignments (M11) are used **only** to scope *which* classes a
  `attendance.record`-only teacher may mark (see §7) — that is a permission
  concern, not a scheduling dependency, and it degrades safely: a school with
  the Staff module off still records attendance through its `attendance.manage`
  holders.

Rationale: the spec requires attendance to work with Timetable off, and a daily
register is the operational primitive every school needs. A per-lesson register
tied to timetable lessons can be added later as an *optional* enhancement
without changing this schema.

## 7. Teacher integration

`AttendanceAuthorizer::canRecordForClass($user, $levelId, $armId)`:

1. no `attendance.record` permission → **no**;
2. has `attendance.manage` (School Admin, Principal) → **any class**;
3. otherwise (Teacher) → only a class they hold an **active** M11
   `TeacherAssignment` for (`academic_level_id` matches and the assignment's
   `level_arm_id` is null *or* matches the register's arm).

A Teacher-role user with **no linked `Teacher` record** cannot record. All
lookups are tenant-scoped, so a teacher assigned in school B cannot record in
school A. Teacher assignment is a **scoping filter, not the foundation** — with
no assignments at all, `attendance.manage` holders still record for every class.
No workload or teaching-load calculation is done.

## 8. Authorization & module

Two gates on every `/attendance/*` route: **`module:attendance`** (404 when the
module is off) **and** an M4 permission `->can(...)`. Enabling the module grants
nothing.

| Permission | Holders (M4 bundles) | Grants |
|------------|----------------------|--------|
| `attendance.view` | School Admin, Principal, Teacher, Staff | see the list and register detail |
| `attendance.record` | School Admin, Principal, Teacher | create a register, save marks, submit, delete a draft — *class-scoped for teachers* |
| `attendance.manage` | School Admin, Principal | reopen a locked register; record for any class |

- **Bursar / Parent / Student / role-less → 403** on every attendance route.
  Bursar has no academic access, so no attendance access.
- School Admin holds every school permission automatically; Principal's bundle
  gained `attendance.manage`; Teacher and Staff bundles were unchanged (they
  already carried `attendance.view` / `attendance.record` from M7).
- Route ids (`{register}`) are **not** route-model-bound — resolved by
  tenant-scoped `findOrFail`, so a cross-school id is a plain 404. The write
  Form Requests `abort(404)` on a cross-school `{register}` before validation.
  FK ids in the create payload use
  `Rule::exists('<table>', 'id')->where('school_id', <tenant>)` → generic
  "invalid" message, so nothing leaks about another school's data.

## 9. Tenant isolation

Non-negotiable, and enforced server-side:

- `AttendanceRegister` and `AttendanceRecord` both `use BelongsToSchool` —
  `SchoolScope` global scope on every query, `school_id` stamped from
  `TenantContext` on create, **never** in `$fillable`, **never** read from
  input, immutable on update (`TenantMismatchException`). A `school_id` key in a
  payload is silently ignored.
- The bulk roster `insert` in `store()` sets `school_id` explicitly from
  `TenantContext::idOrFail()` (raw `insert` bypasses the model's `creating`
  hook).
- Every session / period / level / arm / student id is validated inside the
  active school; the eligible-student query and the roster check are tenant
  scoped; a mark for a student not on the register's roster is rejected.
- HTTP tests prove School A cannot view / submit / reopen / delete School B's
  register (404), cannot create a register with School B's class ("invalid"),
  and cannot post School B's student ids ("not part of this register").

## 10. Indexing & performance

`attendance_registers`:

| Index | For |
|-------|-----|
| `unique(school_id, level_arm_id, attendance_date)` | one register per class per day (DB-enforced; the Form Request gives the friendly message) |
| `(school_id, attendance_date)` | the dated list / "today's registers" |
| `(school_id, academic_session_id, academic_period_id)` | session/period filter |
| `(school_id, status)` | draft vs submitted filter |

`attendance_records`:

| Index | For |
|-------|-----|
| `unique(attendance_register_id, student_id)` | no duplicate student on a register (DB-enforced) |
| `(school_id, student_id)` | a student's attendance history |
| `(school_id, attendance_register_id, status)` | register summary counts / status filter |

Query discipline:

- **Roster snapshot** on create is a single bulk `insert` — never one insert per
  student.
- The **list** uses `withCount` sub-queries for the total / present counts and
  eager-loads `session/period/level/arm` — no N+1 across pages of 20.
- The **taking screen** eager-loads `records.student` with an explicit column
  list; the summary is computed from the loaded collection (no extra query).
- **Bulk save** loads the register's existing records once (`keyBy` student id),
  then writes only the rows whose status/note actually changed — no per-student
  `SELECT`.
- Regression tests assert bounded query counts for a 30-student taking screen, a
  12-register list, and a full-class bulk save.

## 11. Seed data

`DatabaseSeeder` (Alpha school): 6 extra students enrolled in Primary 1 Gold and
4 in Primary 2 Gold, then

- a **submitted** register for Primary 1 Gold (2025-09-16) with mixed marks
  (present / absent / late / excused, one excused note), locked via
  `submit($admin)`;
- a **draft** register for Primary 2 Gold (2025-09-17) with an unmarked roster.

## 12. Deferred

Attendance percentage / rate analytics · term & monthly attendance reports ·
chart dashboards · predictive / at-risk analytics · per-lesson (timetable-driven)
registers · parent / student portal attendance views · absence notifications ·
a full audit-trail and data-retention / erasure workflow · bulk register
creation · attendance reasons taxonomy · half-day / session-of-day attendance.
