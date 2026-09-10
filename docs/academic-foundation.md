# Academic Foundation

Status: **Milestone 8 — complete.** The configurable academic structure every
later domain module (Students, Teachers, Timetable, Attendance, Assessments,
Results) hangs off. Built on the existing seams — `TenantContext` +
`BelongsToSchool` for isolation, `App\Enums\Permission` for authorization,
`module:academics` for activation. No new authorization or tenancy mechanism, no
new packages, no Redis/queues.

M8 builds **structure only**. It does not touch students, guardians, staff,
timetables, attendance, assessments, results, grading, fees, promotion or CBT.

## 1. The pieces

| Concern | Model | Table | Belongs to |
|---------|-------|-------|------------|
| Academic year | `App\Models\AcademicSession` (M5, extended) | `academic_sessions` | school |
| Term / semester | `App\Models\AcademicPeriod` | `academic_periods` | school **+** session |
| Level / class | `App\Models\AcademicLevel` | `academic_levels` | school |
| Arm / stream | `App\Models\LevelArm` | `level_arms` | school **+** level |
| Subject | `App\Models\Subject` | `subjects` | school |
| Subjects a level offers | pivot (`AcademicLevel::subjects()`) | `level_subject` | school |

Controllers: `App\Http\Controllers\Academic\{Session,Period,Level,Arm,Subject}Controller`.
Form Requests: `App\Http\Requests\Academic\*`. Views: `resources/views/academic/*`
behind the shared `academic/_nav` sub-nav (Sessions & terms · Levels & arms ·
Subjects).

```
School
 ├── AcademicSession (year)        one `is_current` per school
 │     └── AcademicPeriod (term)   one `is_current` per session; any number of them
 ├── AcademicLevel (class)         ordered, active/inactive
 │     ├── LevelArm (stream)       ordered, active/inactive
 │     └── level_subject ──► Subject
 └── Subject                       ordered, active/inactive
```

## 2. Routes

All under `Route::middleware(['tenant', 'module:academics'])->prefix('academic')`.
Every route carries **both** gates:

- `module:academics` — is the feature switched on for this school? Off ⇒ 404.
- `->can('academics.view')` (reads) / `->can('academics.manage')` (writes).

| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/academic/sessions` | `academic.sessions.index` | `academics.view` |
| POST | `/academic/sessions` | `academic.sessions.store` | `academics.manage` |
| GET | `/academic/sessions/{session}` | `academic.sessions.show` | `academics.view` |
| GET/PATCH | `/academic/sessions/{session}[/edit]` | `academic.sessions.edit` / `.update` | `academics.manage` |
| PUT | `/academic/sessions/{session}/current` | `academic.sessions.current` | `academics.manage` |
| POST | `/academic/sessions/{session}/periods` | `academic.periods.store` | `academics.manage` |
| GET/PATCH | `/academic/periods/{period}[/edit]` | `academic.periods.edit` / `.update` | `academics.manage` |
| PUT | `/academic/periods/{period}/current` | `academic.periods.current` | `academics.manage` |
| GET | `/academic/levels` | `academic.levels.index` | `academics.view` |
| POST | `/academic/levels` | `academic.levels.store` | `academics.manage` |
| GET | `/academic/levels/{level}` | `academic.levels.show` | `academics.view` |
| GET/PATCH | `/academic/levels/{level}[/edit]` | `academic.levels.edit` / `.update` | `academics.manage` |
| PUT | `/academic/levels/{level}/subjects` | `academic.levels.subjects` | `academics.manage` |
| POST | `/academic/levels/{level}/arms` | `academic.arms.store` | `academics.manage` |
| GET/PATCH | `/academic/arms/{arm}[/edit]` | `academic.arms.edit` / `.update` | `academics.manage` |
| GET | `/academic/subjects` | `academic.subjects.index` (`?q=` search) | `academics.view` |
| POST | `/academic/subjects` | `academic.subjects.store` | `academics.manage` |
| GET/PATCH | `/academic/subjects/{subject}[/edit]` | `academic.subjects.edit` / `.update` | `academics.manage` |

> M5 shipped academic sessions under `settings/academic-sessions` gated by
> `school.settings.*`. M8 moves them into the academic area and re-gates them to
> `academics.*` + `module:academics` — sessions and periods are one structure and
> belong under one permission. The dashboard onboarding checklist and the
> primary nav were updated accordingly.

## 3. Models & columns

Every model `use`s `BelongsToSchool`: `school_id` is stamped from
`TenantContext` on create, is never in `$fillable`, is never read from request
input, and is immutable after create.

### `AcademicSession` — the year (M5, extended in M8)
`name` (`unique(school_id, name)`), `starts_on` / `ends_on` (`ends_on` after
`starts_on`), `is_current` (bool, at most one per school — `makeCurrent()`,
transactional + tenant-scoped, not mass-assignable). M8 adds `periods()` /
`currentPeriod()` relations. Sessions are **never deleted** — history is
preserved; a mistake is edited.

### `AcademicPeriod` — the term / semester (new)
`academic_session_id` (FK, set from the parent relation, never changed),
`school_id`, `name`, `starts_on` / `ends_on` (`ends_on` after `starts_on`),
`position` (1-based order), `is_active`, `is_current`.
- `unique(academic_session_id, name)` and `unique(academic_session_id, position)`
  — names and order are unique **within a session**, not globally.
- `index(school_id, academic_session_id, position)` — tenant-scoped ordered reads.
- `makeCurrent()` demotes any sibling **in the same session** and activates the
  period (an inactive "current" term is a contradiction). Current periods in
  *different* sessions don't collide — "one current period per session".
- **No assumption about the number of periods.** A school configures 1, 2, 3, 4…
  as its calendar needs.

### `AcademicLevel` — the class / year group (new)
`school_id`, `name`, `code` (short code), `position`, `is_active`.
`unique(school_id, name)`, `unique(school_id, code)`, `unique(school_id, position)`.
Relations: `arms()`, `subjects()` (via `level_subject`). No level names are
hard-coded — "Primary 1", "JSS 1", "Grade 4", "Reception", anything.

### `LevelArm` — the stream / division (new)
`academic_level_id` (FK, from the parent relation, never changed), `school_id`,
`name`, `code`, `position`, `is_active`.
`unique(academic_level_id, name/code/position)` — unique **within the level**.
`index(school_id, academic_level_id, position)`.

### `Subject` (new)
`school_id`, `name`, `code`, `description` (nullable), `position` (soft ordering
hint — **not** unique), `is_active`.
`unique(school_id, name)`, `unique(school_id, code)`. No subject list is baked in.

### `level_subject` — which subjects a level offers (new)
`school_id`, `academic_level_id`, `subject_id`, timestamps.
`unique(academic_level_id, subject_id)`, `index(school_id, academic_level_id)`,
`index(school_id, subject_id)`.
- Carries `school_id` like every other school-owned table — written from
  `TenantContext` during the sync, gives a tenant-scoped lookup index for the
  later modules, and keeps the "FKs stay within one tenant" rule verifiable.
- The **only** cross-model link M8 builds. No teacher, no timetable, no student
  enrolment, no per-subject weighting — those are their own milestones.

`code` on levels / arms / subjects is normalised to trimmed upper-case in
`prepareForValidation` and validated `^[A-Z0-9][A-Z0-9 -]*$`.

## 4. Authorization

Reuses the **already-defined, previously-dormant** M4 permissions — no new
permission, no new mechanism:

| Permission | Held by (from the M4 role bundles) |
|------------|-----------------------------------|
| `academics.view` | School Admin, Principal, Teacher, Staff |
| `academics.manage` | School Admin, Principal |

- **School Admin / Principal** → full management.
- **Teacher / Staff** → read-only (they see the structure their teaching hangs
  off; they cannot change it).
- **Bursar / Parent / Student / role-less** → 403 (no academic permission).
- **Platform Admin** → only through a selected tenant context, scoped to that
  school; no active school ⇒ school-picker redirect.

Each route has `->can()`; each write Form Request re-checks `academics.manage` in
`authorize()`. Academic configuration grants **no** unrelated permission — it is
orthogonal to students/staff/finance/etc.

## 5. Tenant isolation

Mandatory and tested at the HTTP layer for every entity:

- Every record belongs to exactly one school, directly (`school_id`) or through
  a securely-constrained parent (`academic_session_id` / `academic_level_id`
  where the parent is itself tenant-scoped).
- `school_id` is **never** accepted from user input; a `school_id` in a payload
  is ignored. Ownership is **immutable** (`updating` hook →
  `TenantMismatchException`), covered by a test per model.
- **Route-model binding stays tenant-safe:** tenant-owned ids are *not*
  route-model-bound (binding runs before the `tenant` middleware). They are
  resolved in the controller with `Model::query()->findOrFail($id)`, which is
  tenant-scoped, so another school's id 404s. Form Requests that need a parent id
  for a uniqueness rule resolve it **tenant-scoped** too (`AcademicSession::query()
  ->whereKey(...)->value('id')`), so a cross-school id becomes a clean 404 rather
  than leaking through a validation query.
- `level_subject` sync validates every `subject_id` with
  `Rule::exists('subjects','id')->where('school_id', <tenant>)` — a cross-school
  subject id is rejected, and the `belongsToMany` read applies `Subject`'s scope,
  so School B never sees School A's links.
- Explicit cross-school "cannot read / create / edit / delete / promote" tests
  for sessions, periods, levels, arms, subjects and the level↔subject link.

## 6. Module activation

The whole `/academic/*` prefix sits behind `module:academics`. `Module::Academics`
`isAvailable()` is now **`true`** (M8 ships usable functionality) and its default
is **on**. A school that turns Academics off gets a 404 on every academic route
and the "Academic" nav item disappears; the dashboard onboarding checklist drops
its academic-session step. Turning the module back on restores everything —
records are untouched.

Module activation and authorization are independent: a Teacher passes the module
gate (Academics is on) but still can't manage config (no `academics.manage`).

## 7. Database / performance

- FKs everywhere, `cascadeOnDelete` to the parent / school.
- Composite indexes lead with `school_id` (or the tenant-scoped parent id).
- Uniqueness is scoped to school / session / level, never global.
- Current-session / current-period lookups: `scopeCurrent()` on an indexed
  boolean, or the `currentPeriod()` `HasOne`.
- Lists are paginated (sessions 20, levels 30, subjects 30) and use
  `withCount()` / `with()` eager loading — no N+1 on the list or show screens.
- No Redis, no queues, no new packages.

## 8. Decisions

| Decision | Why |
|----------|-----|
| Academic sessions moved from `school.settings.*` to `academics.*` + `module:academics` | sessions, periods, levels, subjects are one structure under one permission; M5's "sessions are just configuration" no longer holds once the module exists |
| `AcademicPeriod` / `LevelArm` are `BelongsToSchool` in their own right (not only child FKs) | every query is tenant-safe even without the parent in the join; `SchoolScope` still fails closed |
| Tenant-owned ids resolved by `findOrFail` in the controller, not route-model-bound | binding runs before the `tenant` middleware — a `BelongsToSchool` bind would hit `SchoolScope` with no context (same rule as M5) |
| Form Requests resolve parent ids tenant-scoped | a cross-school parent id then yields a clean 404, not a validation error that leaks "position taken" |
| `level_subject` carries `school_id`, written from `TenantContext` | consistency with every school-owned table + a tenant-scoped index the later modules will query |
| No hard delete for any academic entity (only `is_active`) | preserves referential integrity for the modules built on top; deletion / archival is a later concern |
| Sessions have no `is_active` | item spec is name / dates / current status; a past session is simply past |
| `position` unique for levels / arms / periods, not for subjects | the first three are an ordered sequence; subjects are a flat list where order is a display hint |
| Reuse `academics.view` / `academics.manage`, no finer split | coarse-on-purpose (M4); a later module that needs finer control adds a case |

## 9. Deferred

- Students (**done in M9** — `docs/student-management.md`: `enrollments` links a
  student to a session / period / level / arm).
- Guardians, staff, class/arm membership.
- Teacher → subject / class assignment.
- Timetable, attendance, assessments, results, grading scales, promotion, CBT.
- Per-subject metadata for assessments (credit units, weighting, pass mark).
- Period-within-session date-bounds and non-overlap validation (only
  `ends_on > starts_on` is enforced now).
- Bulk import / cloning a previous year's structure into a new session.
- Hard delete / archival of academic entities; audit trail of changes.
