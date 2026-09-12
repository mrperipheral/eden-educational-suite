# Entry / Placement Assessment

Status: **Milestone 25 — complete.** A simple, school-scoped record of an
assessment conducted for a prospective or newly admitted student.

**Entry / Placement Assessment records the assessment conducted for an
applicant or student. It does not make placement decisions or automatically
change student enrolment.** There is no recommended class/arm, no placement
decision workflow, no automatic placement or enrolment, and no CBT engine of
its own — see §10 for the full list of what this milestone deliberately does
not build.

## 1. What this is

`App\Models\EntryAssessment` — one row per candidate per subject assessed.
A candidate tested in several subjects gets several rows sharing the same
`candidate_name`/`admission_reference`/`assessed_on` (no batch/grouping
entity is introduced for this — see §3). Fields: an optional link to an
existing `Student`, a candidate name (always stored explicitly — see §2), an
optional admission/application reference, the intended/assessed level (+
optional arm — it may not be decided yet), the subject, the assessment date,
score/max score, a free-text result label, notes, the assessor (captured
automatically), and a retention `status`.

## 2. Data model decisions

- **`candidate_name` is always stored**, independent of whether
  `student_id` is set. This is a self-contained historical record: it never
  depends on a `Student` row existing, and it is immune to that student's
  name changing later. A genuinely prospective candidate has no `Student`
  row at all — `student_id` stays `null`.
- **One record = one subject.** "Subject(s)" in the spec is satisfied by
  recording multiple rows for a multi-subject candidate rather than
  inventing a batch/grouping entity — the same choice `App\Models\Assessment`
  (M14) already makes (one class, one subject, per row).
- **`percentage` is never stored.** It is always safely derivable from
  `score`/`max_score` via `EntryAssessment::percentage()` (bcmath, returns
  `null` when no score is entered yet). Storing it would be exactly the kind
  of unnecessary derived value the spec warns against.
- **`result` is a free-text field (`max:50`), not an enum.** The
  pass/fail/merit/whatever vocabulary a school wants here is theirs to
  choose — inventing a fixed taxonomy would be over-engineering a field the
  spec explicitly marks "where applicable". It never drives any automatic
  action — see §10.
- **`assessor_id` is captured from the authenticated user at creation**,
  never a request-supplied name — mirrors `assessments.created_by` /
  `learning_materials.uploaded_by`. Not mass-assignable; unchanged by a
  later edit (an edit corrects details, it doesn't retroactively change who
  administered the assessment).
- **`academic_level_id` is required; `level_arm_id` is nullable** — the
  intended class is always known, the specific arm may not be yet.
- **`score` is nullable** — a record can be created ahead of the assessment
  actually being conducted (e.g. scheduling it), then edited once the score
  is in. `max_score` is always required, since it defines the scale even
  before a score exists.

## 3. Lifecycle

`App\Enums\EntryAssessmentStatus` is deliberately just two cases —
`Active`/`Archived` — the record's own retention lifecycle only. This is
**not** the assessment's conducted/pending state (read directly from
`score === null`) and **not** a pass/fail outcome (the free-text `result`
field). `EntryAssessment::archive()`/`restore()` are freely-reversible, like
`Question::archive()`/`activate()` (M24) — nothing ever attaches to an entry
assessment record the way an exam attaches to a Question Bank entry, so
there is no downstream historical-integrity concern an archived-then-
restored record could break. **No hard delete, ever** — archiving is the
only retirement action, matching this codebase's project-wide convention for
`Student`/`Teacher`/`Guardian`/`Question`.

## 4. Authorization

New permissions `placement.view` / `.record` / `.manage`, slotted
into the existing M4 role bundles:

- School Admin → everything (automatic, `Permission::all()`).
- Principal → `.view` + `.record` + `.manage` (full, any class) — the same
  three-permission bundle Principal already holds for CBT.
- Bursar → none (explicit "no access" per spec).
- Teacher → `.view` + `.record`, scoped to classes/subjects they hold an
  active M11 `TeacherAssignment` for.
- Staff → `.view` only (view-only, matching the Staff-gets-view-only
  precedent already established for Academics/Timetable/Attendance/
  Assessment/Result/LearningMaterial/CBT — the spec's own authorization
  list was silent on Staff specifically).
- Parent / Student → none.

`App\Support\EntryAssessment\EntryAssessmentAuthorizer::canManageFor(User
$user, int $levelId, ?int $armId, int $subjectId)` mirrors
`App\Support\Cbt\CbtAuthorizer::canAuthorFor()` exactly: `.manage` → any
class/subject; `.record` without `.manage` (Teacher) → only a `(level,
subject)` they hold an active assignment for (that arm, or an arm-agnostic
assignment). `canManage(User $user, EntryAssessment $assessment)` is the
already-existing-row form, used for edit/archive/restore. No second
authorization system — this is the same class/subject-scoping seam every
other M14/M22/M23/M24 write action already uses.

*Viewing* the list (including export) stays gated by the coarse
`placement.view` permission alone — not further scoped by teaching
assignment, the same choice M23/M24 make for their own staff-facing lists.

`store()`/`update()` both re-derive the authorization check from the
**submitted** `academic_level_id`/`level_arm_id`/`subject_id` (after
validation, never trusted directly) — a Teacher cannot record for, or move
an existing record into, a class outside their own assignments even via a
direct/tampered request.

## 5. Tenant isolation

`EntryAssessment` is `BelongsToSchool` — every query is scoped to the active
tenant automatically, `school_id` is stamped from `TenantContext` and can
never be supplied or changed by the client. The `{entryAssessment}` route
parameter is resolved by tenant-scoped `findOrFail` in the controller (not
route-model-bound, per this app's convention) — another school's id 404s on
view/edit/archive/restore. The index and export queries are naturally
tenant-scoped by the same global scope — a cross-school record is never
listed, searched into, or exported.

## 6. Validation

`App\Http\Requests\EntryAssessment\EntryAssessmentRequest`:

- `student_id` — nullable, tenant-scoped `Rule::exists`.
- `candidate_name` — required, `max:150`.
- `admission_reference` — nullable, `max:100`.
- `academic_level_id` — required, tenant-scoped `Rule::exists`.
- `level_arm_id` — nullable, tenant-scoped `Rule::exists`; `withValidator`
  checks it belongs to the given level.
- `subject_id` — required, tenant-scoped `Rule::exists`; `withValidator`
  checks the subject is actually offered at the given level via the
  `level_subject` pivot (the same check `QuestionRequest`/
  `TimetableEntryRequest`/`ExaminationRequest` already use).
- `assessed_on` — required, a valid date.
- `score` — nullable, numeric, `min:0`; `withValidator` rejects a score
  greater than `max_score` (a cross-field invariant no single column rule
  expresses).
- `max_score` — required, numeric, `min:0.01`.
- `result` — nullable, `max:50`, free text.
- `notes` — nullable, `max:2000`.

## 7. Export

`GET /entry-assessments/export` streams a CSV (`response()->streamDownload()`
+ `fputcsv()` — no new package) built from the **exact same filtered,
tenant-scoped query** the index page uses (`EntryAssessmentController::
filteredQuery()`, shared by both actions) — so the export always reflects
whatever search/filters are currently active, and can never include another
school's rows. Gated by `placement.view` alone, same as the list.

Columns: candidate name, admission reference, assessment date, level, arm,
subject, score, max score, percentage (computed at export time, never
stored), result, status, assessor name, notes. Deliberately excludes
internal ids (`school_id`, `assessor_id`, `student_id`) — only human-facing
fields.

Iterates via `Builder::chunk(200, …)`, not `cursor()` — `cursor()` skips
Eloquent's eager-loading entirely (it would N+1 `level`/`arm`/`subject`/
`assessor` once per row), while `chunk()` keeps memory bounded to one page
at a time while still eager-loading each page's relations in bulk.

## 8. Performance

The index (`GET /entry-assessments`) is paginated (15/page); every filter
(level/arm/subject/status) and the `q` search (`candidate_name`/
`admission_reference` `LIKE`) apply as `WHERE` clauses on an indexed,
tenant-scoped query — `school_id`-leading indexes exist for
`(academic_level_id, level_arm_id)` (`entry_assessments_class_index`),
`subject_id`, `student_id`, `status` and `assessed_on`. Eager-loads
`student`/`level`/`arm`/`subject`/`assessor` (selected columns only) — no
N+1 as the list grows, proven by an explicit query-count regression test
(with and without filters applied). No caching, no queue, no Redis — none
of this milestone's actual need calls for it.

## 9. UI

`resources/views/entry-assessments/*` — `index.blade.php` (search + four
filters, status/result badges, View/Edit/Archive/Restore actions inline, an
Export CSV button that carries the active filters through as query
parameters), `_form.blade.php` (shared create/edit partial; mirrors `cbt/
questions/_form.blade.php`'s own unrestricted-cascade vs. scoped-Teacher-
assignment-picker split, plus an optional student-link `<select>`),
`show.blade.php` (a read-only detail page), `create.blade.php`/
`edit.blade.php` (thin wrappers around the shared form, matching every
other module's own create/edit pair). A new "Entry Assessment" nav item,
gated the same way as every other module link. No new frontend framework,
no SPA — plain Blade + Tailwind + a small amount of Alpine for the level→
arm/assignment cascades, identical in shape to every other create/edit form
in this app.

## 10. Deferred / explicitly out of scope

Per the M25 spec, none of the following are built here, and nothing in this
milestone provides an extension point that assumes they will be added the
same way:

- Placement recommendation, recommended class/arm, a placement decision
  workflow, automatic placement, automatic enrolment change, promotion or
  graduation, AI-based placement decisions.
- An admissions CRM, application payment, interview scheduling, document
  management.
- Psychometric testing, proctoring, adaptive testing.
- A new CBT engine, question pools, randomisation — the existing M23/M24
  Question Bank/CBT system is untouched by this milestone; nothing here
  reads from or writes to it.
- Notifications, SMS/WhatsApp workflows.
- Complex reporting/analytics beyond the list/filter/export this milestone
  ships.
- Bulk import.

Recording only. Whatever the school decides to do with a candidate's
assessment result — placement, admission, rejection — happens entirely
outside this feature, exactly as the spec requires.
