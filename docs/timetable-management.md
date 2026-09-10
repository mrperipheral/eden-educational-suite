# Timetable Management

Status: **Milestone 12 — complete.** A tenant-scoped, configurable weekly
timetable with server-side conflict detection and a draft → published lifecycle.
Built on the M3/M4/M7/M8/M11 seams — `TenantContext` + `BelongsToSchool`,
`App\Enums\Permission`, `module:timetable`, and the M11 `TeacherAssignment`
foundation — no new authorization or tenancy mechanism, no new packages, no
Redis/queues.

M12 is the **scheduling foundation**. It does **not** build attendance,
assessments, marks, notifications, a room/facilities module, teacher
workload/payroll, automatic conflict resolution or optimisation, or
student/parent timetable views.

## 1. The pieces

| Concern | Model | Table | Belongs to |
|---------|-------|-------|------------|
| A weekly timetable / schedule | `App\Models\Timetable` | `timetables` | school |
| One scheduled lesson | `App\Models\TimetableEntry` | `timetable_entries` | school **+** timetable |

Enums: `App\Enums\TimetableStatus` (`draft` / `published`), `App\Enums\Weekday`
(M6, reused — Carbon numbering, `0 = Sunday … 6 = Saturday`).
Controllers: `App\Http\Controllers\Timetable\{Timetable,TimetableEntry}Controller`.
Form Requests: `App\Http\Requests\Timetable\*`.
Conflict scan: `App\Support\Timetable\TimetableConflictScanner`.
Views: `resources/views/timetables/*`.

```
School
 └── Timetable                     status: draft | published
       │  academic_session   (required, fixed at creation)
       │  academic_period    (optional — "whole session" when null)
       └── TimetableEntry (lesson)  the schedule itself
             ├── AcademicLevel  (required)
             ├── LevelArm       (required — lessons are scheduled per class, not per level)
             ├── Subject        (required — must be offered by the level)
             ├── Teacher        (required — must have an active M11 assignment)
             ├── weekday        (App\Enums\Weekday — no Mon–Fri assumption)
             ├── start_time     "HH:MM"   ┐ half-open [start, end)
             ├── end_time       "HH:MM"   ┘ so back-to-back lessons never clash
             └── room           optional free text
```

**Nothing is hardcoded** — not the days of the week, the number of periods, the
lesson length, a break structure, or a term count. A timetable is whatever set
of `(weekday, start, end)` lessons the school enters.

## 2. Data model decisions

| Decision | Why |
|----------|-----|
| Session / period live on the **timetable**, not the entry | a timetable's lessons structurally cannot cross academic sessions; there is no per-entry session field to get wrong |
| The session is **fixed at creation** (editable timetable only changes name + period) | changing it would orphan every lesson's teacher-assignment check |
| Lessons are scheduled at the **`LevelArm` (class)** level — `level_arm_id` is required | "Primary 1 Gold has Maths at 9am", not "Primary 1 has Maths" — a real class receives the lesson |
| Times are `HH:MM` **strings** (`CHAR(5)`), not a `TIME` type | zero-padded 24-hour strings compare correctly and identically on SQLite and MySQL; overlap SQL is plain `start < :end AND end > :start` with no driver-specific `TIME` semantics |
| The interval is **half-open** `[start, end)` | a lesson ending 10:00 and one starting 10:00 do not clash — the common "next period" case |
| `room` is **plain text** (`VARCHAR(60)`, nullable) | M12 is not a facilities module — no room inventory, capacity or building management. The column is indexed so a future rooms module can migrate to a FK without reshaping the table |
| `status` (`draft`/`published`) + `published_at` are **not mass-assignable** | publishing runs a conflict guard first — it is not a field you set |
| Several timetables may exist per session (old published + new draft) — no uniqueness constraint | history is preserved; the school decides which one is "the" schedule by publishing it |
| Deleting a **published** timetable is blocked (return it to draft first) | "do not casually cascade-delete historical timetable information"; a draft is disposable, a published schedule is a deliberate record |

## 3. Conflict / overlap rules

Enforced **server-side** in `TimetableEntryRequest` on every create and edit
(JavaScript filtering in the form is convenience only). Each check is a
tenant-scoped **database existence query** — entries are never loaded into PHP to
detect clashes.

| Rule | Rejected when |
|------|---------------|
| End after start | `end_time <= start_time` |
| Arm ↔ level | the arm does not belong to the selected level |
| Subject ↔ level | the subject is not in the level's `level_subject` offering |
| Teacher assignment (M11) | no **active** `TeacherAssignment` backs `(teacher, subject, level)` for the timetable's session — matching the assignment's arm / period where it pins them. The timetable never re-implements teacher authorization; it asks M11. |
| Teacher double-booking | the teacher already has a lesson **in this timetable** on the same weekday whose `[start, end)` overlaps |
| Class double-booking | the `level_arm` already has an overlapping lesson (same weekday, this timetable) |
| Room double-booking | a non-empty `room` already has an overlapping lesson (same weekday, this timetable). Unroomed lessons never clash on room (`SQL NULL != NULL`). |

Overlap is scoped to **one timetable** (`timetable_id`): a draft and an old
published timetable for the same session do not conflict with each other.

Cross-school ids are rejected the same way as everywhere else —
`Rule::exists(...)->where('school_id', <tenant>)` yields a plain "invalid", and
the `{timetable}` / `{entry}` route ids `abort(404)` in `prepareForValidation`
if they are not the active school's, so a cross-school id never reaches the rules.

### The whole-timetable scan

`App\Support\Timetable\TimetableConflictScanner` runs one indexed **self-join**
(`a.id < b.id`, same `timetable_id` + `weekday`, overlapping times, and
`a.teacher_id = b.teacher_id OR a.level_arm_id = b.level_arm_id OR a.room =
b.room`). It backs the **publish guard** and the warning banner on the timetable
page. It filters on `timetable_id` (all entries of a tenant-resolved timetable
are in-tenant) plus an explicit `school_id` guard.

## 4. Publishing lifecycle

`draft` → `published` → (back to) `draft`. No approval workflow.

- `PATCH /timetables/{timetable}/status` with `status=published`
  (`UpdateTimetableStatusRequest`) is **refused** unless the timetable has at
  least one lesson **and** the conflict scan is clean — a published timetable is
  always internally consistent. `Timetable::publish()` runs in a transaction and
  stamps `published_at`.
- `status=draft` returns it to draft and clears `published_at` (no guard).
- Entries of a published timetable can still be edited — the per-entry conflict
  validation always applies, so a published schedule cannot be made inconsistent.
- The **Published** badge appears on the list, the timetable page and every
  teacher-view lesson.

## 5. Views

| View | Route | Purpose |
|------|-------|---------|
| List | `GET /timetables` (`?session=`, `?status=`) | every timetable, filterable, paginated (20) |
| Create / edit | `/timetables/create`, `/timetables/{t}/edit` | name + session (create) / name + period (edit) |
| Timetable page | `GET /timetables/{t}` (`?arm=`, `?teacher=`, `?weekday=`) | the schedule — a **day × time grid** on desktop, a **stacked day-by-day list** on mobile (`hidden md:block` / `md:hidden`), never a forced horizontal-overflow grid on a phone; status + publish controls; conflict banner |
| Class timetable | the timetable page filtered by level → arm | "see lessons by day and time for this class" |
| Teacher timetable | `GET /timetables/teacher-view` (`?teacher=`) | pick a teacher → every lesson they are scheduled for, across all timetables, grouped by day, each tagged with its timetable + status |

14 routes under `/timetables/` — `timetables.*` (index, create, store, show,
edit, update, status, destroy), `timetables.entries.*` (create, store, edit,
update, destroy) and `timetables.teacher`.
| Lesson create / edit | `/timetables/{t}/entries/create`, `/timetables/entries/{e}/edit` | Alpine cascade level → arm, plus subject / teacher / weekday / times / room |

Empty, validation, success and error states throughout; the conflict banner
lists the clashing lesson pairs and blocks publishing.

## 6. Authorization

New permissions `timetable.view` / `timetable.manage` (`App\Enums\Permission`),
enforced M12.

| Role | Timetable access |
|------|------------------|
| School Admin, Principal | manage (timetables + lessons + publish) |
| Teacher, Staff | view only |
| Bursar, Parent, Student, role-less | none → 403 |

`timetable.manage` + `timetable.view` were added to **Principal**;
`timetable.view` to **Teacher** and **Staff** — the roles that already hold
`academics.view`. **Bursar has no academic access, so it gets no timetable
access** (the "view only if consistent with existing academic access" rule).
Every route carries `module:timetable` **and** `->can('timetable.view'|'.manage')`;
write Form Requests re-check `timetable.manage`. Enabling the module grants
nothing (tested).

## 7. Module activation

`Module::Timetable->isAvailable()` is now **`true`**, but it stays **off by
default** — a specialised tool a school opts into (unchanged from the M7 intent
for timetable / learning-materials / CBT).

`Module::Timetable` **depends on `Module::Academics` and `Module::Staff`** — a
lesson needs the academic structure *and* teachers with assignments. A school
cannot enable Timetable unless both are on, and cannot disable either while
Timetable is on (M7 dependency validation). Academics and Staff are default-on,
so the catalogue stays consistent.

## 8. Indexing & query strategy

`timetables`: `index(school_id, academic_session_id, academic_period_id)`,
`index(school_id, status)`.

`timetable_entries` — one narrow index per booked resource so overlap checks stay
selective:

- `index(school_id, timetable_id, weekday, start_time)` — grid render
- `index(school_id, timetable_id, teacher_id, weekday)` — teacher overlap
- `index(school_id, timetable_id, level_arm_id, weekday)` — class overlap
- `index(school_id, timetable_id, room, weekday)` — room overlap

Every overlap check is `WHERE timetable_id = ? AND weekday = ? AND <resource> = ?
AND start_time < ? AND end_time > ?` — the index narrows to a handful of rows and
the time predicate filters those. Adding a lesson to a 20-lesson board runs a
small constant number of queries, not one per existing lesson (tested). List and
grid pages eager-load `level` / `arm` / `subject` / `teacher`; N+1 regression
tests cover the grid, the teacher view and the add-lesson path. No Redis, no
queues, no packages.

## 9. Tenant isolation

- `Timetable` and `TimetableEntry` are `BelongsToSchool`; the entry also carries
  `timetable_id`. `school_id` is stamped from `TenantContext`, never read from
  input, immutable (`TenantMismatchException`); a `school_id` in a payload is
  ignored (tested).
- `{timetable}` / `{entry}` are **not** route-model-bound — resolved by
  tenant-scoped `findOrFail` (another school's id → 404). The Form Requests
  `abort(404)` on a cross-school route parent before validation.
- Every session / period / level / arm / subject / teacher id in a payload is
  validated to belong to the active school; a cross-school id is a plain
  "invalid" and reveals nothing.
- The conflict self-join is filtered by `timetable_id` **and** `school_id`.
- Explicit HTTP + model tests: School A cannot view, edit, delete or publish
  School B's timetable, cannot create a timetable with School B's session, and
  cannot schedule a lesson with School B's level / arm / subject / teacher.

## 10. Data integrity — what happens when things change

M12 prevents obviously invalid *new* scheduling; it does not build archival
workflows.

- **A teacher resigns** — existing lessons stay (`teacher_id` is never
  hard-deleted; a resigned teacher's row remains). A *new* lesson for them is
  rejected unless an **active** assignment still backs it.
- **A subject / arm is deactivated** — M8 entities are never hard-deleted (only
  `is_active = false`), so existing lessons keep resolving. A new lesson still
  validates against `level_subject` / the arm's level, but M12 does not check
  `is_active` — activating/deactivating is an academic-config concern, and
  hiding inactive options in the form is a later refinement.
- **A period changes** — the timetable keeps its `academic_period_id`; if the
  period row is deleted the FK is `nullOnDelete` and the timetable becomes
  "whole session".
- **FK delete behaviour** — `school_id` / `timetable_id` / `academic_level_id` /
  `level_arm_id` / `subject_id` / `teacher_id` cascade (consistent with every
  other domain table; the parents are never hard-deleted so it never fires in
  practice); `academic_period_id` on both tables is `nullOnDelete`.

## 11. Deferred

- Student / parent / class-noticeboard timetable views (belong to the portals).
- Notifications when a timetable is published or changed.
- A rooms / facilities module (inventory, capacity, buildings) — `room` becomes
  a FK then.
- Timetable **templates** / cloning a term's timetable to the next term.
- Recurring exceptions, one-off lesson changes, cover / substitution.
- Period/bell definitions (a named "Period 3 = 10:20–11:00" grid the school
  configures once) — M12 takes free times instead.
- Teacher workload / periods-per-week limits and warnings.
- Automatic timetable generation / optimisation / conflict resolution.
- Attendance registers per lesson, lesson-level assessment metadata.
- Hiding `is_active = false` subjects / arms / teachers from the lesson form.
- An audit trail of timetable and lesson changes.
