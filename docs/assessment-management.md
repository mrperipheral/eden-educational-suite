# Assessment & Assignments

Status: **Milestone 14 — complete.** A configurable, tenant-scoped assessment and
assignment foundation: schools define their own assessment categories, create
assessments for a class + subject in an academic context, enter student scores in
bulk, and track completion of class assignments. Built on the existing seams —
`TenantContext` + `BelongsToSchool`, `App\Enums\Permission`,
`module:assessments`, the M9 `Enrollment` history and the M11
`TeacherAssignment` — no new authorization or tenancy mechanism, no new packages,
no Redis/queues.

M14 is the **source-data foundation for M15 Results & Report Cards**. It does
**not** compute final grades, report cards, subject/term/session averages,
positions/ranking or GPA; it does not build promotion, graduation, CBT, or
parent/student portal views; it does not build a full audit-trail system or
data-retention workflows.

## 1. The pieces

| Concern | Model | Table | Belongs to |
|---------|-------|-------|------------|
| A school-configured assessment category | `App\Models\AssessmentCategory` | `assessment_categories` | school |
| A gradeable assessment for one class + subject | `App\Models\Assessment` | `assessments` | school |
| One student's score in an assessment | `App\Models\AssessmentScore` | `assessment_scores` | school **+** assessment |
| A piece of set work for a class | `App\Models\Assignment` | `assignments` | school |
| Whether one student turned an assignment in | `App\Models\AssignmentSubmission` | `assignment_submissions` | school **+** assignment |

Enums: `App\Enums\AssessmentStatus` (`draft` / `published` / `locked`),
`App\Enums\AssignmentStatus` (`draft` / `published` / `closed`),
`App\Enums\AssignmentSubmissionStatus` (`pending` / `submitted` / `late` / `exempt`).
Controllers: `App\Http\Controllers\Assessment\{AssessmentCategory,Assessment,AssessmentScore,Assignment,AssignmentSubmission}Controller`.
Form Requests: `App\Http\Requests\Assessment\*`.
Class/subject scoping: `App\Support\Assessment\AssessmentAuthorizer`.
Shared roster rule: `App\Models\Concerns\HasClassRoster`.
Views: `resources/views/assessments/*`, `resources/views/assignments/*`.

```
School
 ├── AssessmentCategory              name / code / position / active  (configurable)
 ├── Assessment                      status: draft | published | locked
 │     │  academic_session / academic_period / academic_level / level_arm / subject   (fixed at creation)
 │     │  assessment_category · title · assessment_date · max_score · instructions
 │     │  assignment_id (optional — the assignment this assessment grades; no calculation)
 │     │  published_at / locked_at / locked_by / created_by
 │     └── AssessmentScore (one per eligible student, snapshotted at creation)
 │           score  (nullable = not entered) · comment · recorded_at / recorded_by
 └── Assignment                      status: draft | published | closed
       │  same academic context (fixed at creation) · title · instructions
       │  assigned_on · due_on · max_score (optional) · teacher_id (owner) · created_by
       └── AssignmentSubmission (one per eligible student, snapshotted at creation)
             status (default pending) · submitted_on · remark · recorded_at / recorded_by
```

**Nothing is hardcoded** — not "CA / Test / Exam", not a term count, not a
school calendar. Categories are the school's to configure; the academic context
comes from the school's own `AcademicSession` / `AcademicPeriod` /
`AcademicLevel` / `LevelArm` / `Subject` rows.

## 2. Assessment categories

`AssessmentCategory` is a `BelongsToSchool` model. `name` and `code` are unique
**within the school** (never globally); `code` is normalised to upper-case and
validated `^[A-Z0-9][A-Z0-9 -]*$`. A category has `position` (display order) and
`is_active`. The seeder ships **Classwork / Homework / Test / Examination** as
examples — every one is renameable, reorderable and deactivatable. An assessment
must reference an **active** category at creation; deactivating a category later
does not touch assessments that already use it. Categories are managed at
`/assessments/categories` and require `assessment.manage` to change.

## 3. Assessment academic context

An assessment belongs to a school, an `academic_session`, an `academic_period`
(required — an assessment sits in one term), an `academic_level`, a `level_arm`
and a `subject`. On creation the server validates:

- every id belongs to the active school (`Rule::exists(...)->where('school_id')`
  → generic "invalid", no leak);
- the arm belongs to the level;
- the period belongs to the session;
- the subject is offered by the level through `level_subject`;
- `assessment_date` falls inside the session **and** the period.

The context is then **fixed** (like a timetable's session). Editing an
assessment (`/assessments/{id}/edit`, draft only) changes the title, category,
maximum score and instructions — never the class, subject or date. To move an
assessment to a different class, delete it (allowed while it has no recorded
scores) and recreate it.

## 4. Student eligibility rule

> A student is eligible for an assessment iff they hold an `Enrollment` (M9) for
> the assessment's **exact `(academic_session_id, academic_level_id,
> level_arm_id)`** whose date range **`[started_on, ended_on]` contains
> `assessment_date`** (`started_on <= assessment_date` AND (`ended_on IS NULL`
> OR `ended_on >= assessment_date`)).

The same historical principle as M13 attendance: the enrollment's / student's
*current* status is **not** a filter. A student who withdraws or changes class
*after* the assessment date was in that class that day and stays on the score
sheet; one whose enrollment ended *before* the date is excluded. This keeps
historical assessment participation correct.

- The roster is **snapshotted at creation** — one `AssessmentScore` (score
  `null`) per then-eligible student, in a single bulk `insert`. A cross-school
  or wrong-class student can never be on it.
- Because scores may be entered while the assessment is still a **draft**, a
  draft's roster can be **reconciled** with current enrolment on demand
  ("Sync roster with current enrolment", `POST /assessments/{id}/scores/sync`) —
  it *adds* rows for newly-eligible students and never removes one. **Publishing
  freezes the roster.**
- Creating an assessment for a class with no eligible student on the date is
  rejected (`level_arm_id` error). Posting a score for a student not on the
  snapshotted roster is rejected (`scores` error).

`Assessment::eligibleStudents()` (via `HasClassRoster`) is the single source of
the rule.

## 5. Scores

`AssessmentScore.score` is a `decimal(6,2)`, **nullable** — `null` means "not
entered yet". Validation on save (`ScoreRequest`):

- `nullable` (blank clears the score) · `numeric` (rejects `NaN` / `Infinity` /
  non-numeric) · `min:0` (no negatives) · `decimal:0,2` (at most two decimal
  places) · `<= max_score` (checked server-side against the parent's maximum).
- `comment` — optional, `max:500`. No sensitive data by design.

A percentage may be **displayed** (score ÷ max_score) in the UI, but M14 stores
**no** percentage, grade, average, position, GPA or overall result — that is
M15's to derive. `max_score` on a draft cannot be reduced below a score already
recorded.

## 6. Assessment lifecycle

```
        create ───▶  DRAFT  ──── publish ────▶  PUBLISHED  ──── lock ────▶  LOCKED
                       ▲  │                        ▲  │                       │
              unpublish │  │ (structure + scores)  │  │ (scores only)         │
                        └──┘                       └──┘  ◀──── unlock ─────────┘
                                                          (assessment.manage)
```

- **Draft** — structure (title / category / max score / instructions) and scores
  are editable. The roster can be synced. A draft with no recorded scores can be
  deleted.
- **Published** — the structure is frozen; scores are still entered / corrected
  normally. The roster is frozen. `unpublish` returns it to draft.
- **Locked** — neither structure nor scores can be changed by ordinary users
  (`PATCH .../scores` and the score screen return 403). A correction requires an
  **`assessment.manage`** holder to `unlock()` it (→ published). An assessment
  with recorded scores, and any locked assessment, **cannot be deleted**.
- `published_at` / `locked_at` / `locked_by` / `created_by` on the assessment and
  `recorded_at` / `recorded_by` on each score are preserved for future auditing.
  There is **no approval workflow** and **no full audit trail** yet.

## 7. Assignments

`Assignment` is deliberately lean: the academic context (validated and fixed as
for assessments, plus `due_on >= assigned_on` and both within the session),
`title`, `instructions`, `assigned_on`, `due_on`, an optional `max_score`, an
owning `teacher_id` (stamped from the creator's linked `Teacher` record, nullable
when an admin without one creates it) and `created_by`.

An assignment carries **no scores**. It tracks **completion** through
`AssignmentSubmission`: one row per eligible student, snapshotted at creation,
`status` defaulting to `pending` (real information — the student has not turned
it in *yet*). A teacher moves each student to `submitted` / `late` / `exempt`,
optionally with a `submitted_on` date and a `remark`, on a bulk screen
(`/assessments/assignments/{id}/submissions`).

Lifecycle `draft → published → closed`, with `unpublish` (→ draft) and `reopen`
(closed → published). Completion is editable while draft or published; a
**closed** assignment freezes its completion records. An assignment with any
non-`pending` submission cannot be deleted.

**Assignment ↔ assessment link.** An `Assessment` may optionally reference an
`assignment_id` (same class + subject, validated). This records "this assessment
grades that assignment" — M14 computes nothing from it. Everything else is
deferred: no student-facing submission UI, no file upload / online submission, no
automated grading, no plagiarism checks, no notifications.

**Attachments are deferred.** The only file-handling the platform has today is
the M6 private-disk school logo. Adding assignment file uploads (upload
validation, a private disk, a gated per-file download route, retention) is its
own piece of work — M14 has no `attachment_path` column.

## 8. Teacher authorization

`AssessmentAuthorizer::canRecordFor($user, $levelId, $armId, $subjectId)`:

1. no `assessment.record` permission → **no**;
2. has `assessment.manage` (School Admin, Principal) → **any class + subject**;
3. otherwise (Teacher) → only a `(level, subject)` they hold an **active** M11
   `TeacherAssignment` for, and only that arm (or an arm-agnostic assignment).

A Teacher-role user with **no linked `Teacher` record** cannot record. All
lookups are tenant-scoped, so a teacher assigned in school B cannot record in
school A. Teacher assignment is a **scoping filter, not the foundation** — with
no assignments at all, `assessment.manage` holders still record for every class,
so a school with the Staff module off is unaffected. No teacher workload is
calculated.

## 9. Authorization & module

Two gates on every `/assessments/*` route: **`module:assessments`** (404 when the
module is off) **and** an M4 permission `->can(...)`. Enabling the module grants
nothing.

| Permission | Holders (M4 bundles) | Grants |
|------------|----------------------|--------|
| `assessment.view` | School Admin, Principal, Teacher, Staff | see assessments, categories, assignments and their detail |
| `assessment.record` | School Admin, Principal, Teacher | create assessments/assignments, enter scores, track completion, run the lifecycle — *class + subject-scoped for teachers* |
| `assessment.manage` | School Admin, Principal | manage categories; record for any class; **unlock** a locked assessment |

- **Bursar / Parent / Student / role-less → 403** on every assessment route.
- New permissions this milestone: `assessment.view`, `assessment.record`,
  `assessment.manage` (permission count 27 → 30). School Admin holds all
  automatically; Principal's bundle gained all three; Teacher gained
  `assessment.view` + `assessment.record`; Staff gained `assessment.view`.
- Route ids (`{assessment}`, `{assignment}`, `{category}`) are **not**
  route-model-bound — resolved by tenant-scoped `findOrFail`, so a cross-school
  id is a plain 404. Write Form Requests `abort(404)` on a cross-school route
  parent before validation.

## 10. Tenant isolation

Non-negotiable, enforced server-side:

- Every model `use`s `BelongsToSchool` — `SchoolScope` global scope on every
  query, `school_id` stamped from `TenantContext` on create, **never** in
  `$fillable`, **never** read from input, immutable on update
  (`TenantMismatchException`). A `school_id` key in a payload is ignored.
- The bulk roster `insert`s in the controllers set `school_id` explicitly from
  `TenantContext::idOrFail()` (raw `insert` bypasses the `creating` hook).
- Every session / period / level / arm / subject / category / assignment id is
  validated inside the active school; the eligible-student query and the roster
  check are tenant-scoped; a score / submission for a student not on the
  snapshotted roster is rejected.
- HTTP + model tests prove School A cannot view / edit / score / publish / lock /
  unlock / delete School B's assessment or assignment (404), cannot create one
  with School B's context ("invalid"), and cannot post School B's student ids.
  Nothing leaks whether another school's assessments / students / subjects /
  assignments exist.

## 11. Indexing & performance

`assessments`: `(school_id, academic_session_id, academic_period_id)`,
`(school_id, academic_level_id, level_arm_id)`, `(school_id, subject_id)`,
`(school_id, assessment_category_id)`, `(school_id, status)`,
`(school_id, assessment_date)`. **No** unique constraint — multiple assessments
of the same category for the same class on different dates are legitimate.

`assessment_scores`: `unique(assessment_id, student_id)` (no duplicate student),
`(school_id, student_id)` (a student's score history), `(school_id,
assessment_id)` (load a sheet tenant-scoped).

`assignments`: `(school_id, session, period)`, `(school_id, level, arm)`,
`(school_id, subject_id)`, `(school_id, teacher_id)`, `(school_id, status)`,
`(school_id, due_on)`.
`assignment_submissions`: `unique(assignment_id, student_id)`, `(school_id,
student_id)`, `(school_id, assignment_id, status)`.

Query discipline:

- Roster snapshots (scores, submissions) are a single bulk `insert` — never one
  per student.
- The list screens use `withCount` sub-queries for the totals and eager-load the
  academic relations — no N+1 across pages of 20.
- The score / completion sheets eager-load `*.student` with an explicit column
  list; the summary is computed from the loaded collection.
- Bulk save loads the roster's existing rows **once** (`keyBy` student id) and
  writes only rows whose value changed — no per-student `SELECT`.
- Regression tests assert bounded query counts for a 40-student score sheet, a
  15-assessment list, a 12-assignment list and a 40-student bulk save.

## 12. M15 compatibility

M14 stores **source data** so M15 can compute results without redesigning these
tables:

- Every score is `(assessment, student, score, max_score)` with the assessment
  carrying `academic_session` / `academic_period` / `academic_level` /
  `level_arm` / `subject` / `assessment_category` — everything a subject-result
  or report-card calculation needs to group and weight.
- `AssessmentStatus::Locked` marks the point after which score data is stable —
  M15 can safely compile from locked (or published) assessments.
- Categories carry `position`; M15's grading scheme can attach weighting to a
  category without touching the assessment tables.
- **No derived fields are stored** — no `final_grade`, `percentage_grade`,
  `subject_average`, `position`, `gpa` or `final_result` anywhere. Adding a
  grading scheme + results tables in M15 is additive.

## 13. Seed data

`DatabaseSeeder` (Alpha school): 4 categories (Classwork, Homework, Test,
Examination); for Primary 1 Gold / Mathematics — a **locked** assessment (all
scores entered), a **published** assessment (about half entered), a **draft**
assessment (none entered); a **published** assignment with mixed completion and a
**draft** assignment (all pending).

## 14. Deferred

Final grades / report cards / grading schemes · subject / term / session
averages · positions & ranking · GPA · promotion & graduation · CBT / online
exams · parent & student portal assessment views · assignment file attachments &
online submission · automated grading / plagiarism checks · assessment
weighting · notifications · a full audit-trail and data-retention / erasure
workflow · bulk assessment creation / cloning across terms.
