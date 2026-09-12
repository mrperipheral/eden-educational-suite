# CBT / Online Examinations

Status: **Milestones 23–24 — complete.** School-scoped online examinations
with multiple-choice and true/false questions, one timed attempt per
student, server-side automatic marking, and immediate or scheduled result
release (M23), drawing from a proper, searchable, filterable Question Bank
(M24 — `docs/question-bank.md`). Essay/manual-marking questions, proctoring
(webcam/screen recording/AI/biometric/browser lockdown) and a detailed
answer-review screen are explicitly **not** built.

## 1. Data model

```
Question               (school-owned; reusable bank entry — M24-compatible)
  └─ QuestionOption     (school-owned; MCQ options, or exactly 2 for true/false)

Examination             (school-owned; one class's exam)
  └─ ExaminationQuestion       (school-owned; a SNAPSHOT of a Question, taken at attach time)
       └─ ExaminationQuestionOption   (school-owned; snapshot of that question's options)

ExamAttempt              (school-owned + student-scoped; one timed attempt)
  └─ ExamAnswer          (school-owned; one row per ExaminationQuestion, bulk-inserted at start)
```

`Question`/`QuestionOption` are the reusable bank — deliberately minimal
groundwork for a future M24 Question Bank, not the bank itself (no topic
tags, difficulty, or authoring history yet). They are never read directly
by a live or historical exam; see §6.

## 2. Examination lifecycle

`App\Enums\ExaminationStatus`: `Draft` → `Scheduled` → `Closed`, one-way
(mirrors `ResultRunStatus`'s own one-way chain more than `AssessmentStatus`'s
reversible one — an exam, once live, is not meant to be pulled back to
draft). Deliberately **separate** from a student's own attempt lifecycle
(§7) — closing an exam stops new attempts from *starting*; an attempt
already in progress is still individually finalised the next time it's
touched, by its own expiry check, not force-submitted by the close action.

- `Draft` — `structureEditable()` and `metadataEditable()` both true:
  questions can be attached/removed/reordered, and title/timing/pass-mark/
  release settings can change freely.
- `Scheduled` — set by `Examination::schedule()` (requires ≥1 question,
  throws otherwise), stamps `scheduled_at`. Now visible/startable to its
  class, subject to the time window below. Both `structureEditable()` and
  `metadataEditable()` are now false — invalid transitions (e.g. editing a
  scheduled exam's metadata, or attaching a question to one) are rejected
  with a 403/exception, not silently allowed.
- `Closed` — set by `Examination::close()` (only from `Scheduled`), stamps
  `closed_at`. No new attempt can start; already-completed attempts and
  their results are untouched.

`Examination::isWithinWindow()` / `isOpenForAttempts()` additionally gate
on `starts_at`/`ends_at` against the server clock — a `Scheduled` exam
outside its own window simply isn't startable yet (or any more); staff use
`close()` to end it explicitly and permanently regardless of `ends_at`.

## 3. Result release

Every examination has `result_release` (`App\Enums\ResultReleaseMode`:
`Immediate` / `Scheduled`) and, when `Scheduled`, a required
`result_release_at` timestamp (validated: required exactly when
`Scheduled`, and never before the exam's own `starts_at`).

`ExamAttempt::isResultVisible()` is the single gate:

- `Immediate` → visible the moment the attempt is `Completed`.
- `Scheduled` → visible once `Completed` **and** the server clock
  (`Carbon::now()`, never a client-submitted or browser value) has reached
  `result_release_at`.

This is evaluated **inline, at read time** — there is no queue or
scheduler in this application (confirmed: no `Schedule::` calls anywhere,
`QUEUE_CONNECTION=sync` in tests) and M23 deliberately doesn't add one. A
release "happens" simply because the next time anyone asks
`isResultVisible()`, the clock has moved past `result_release_at` — no
manual staff action, no background job, mirroring the existing
`ResultRunStatus::visibleToParents()` precedent (a coarser, lifecycle-based
version of the same idea) but with an actual timestamp instead of a
discrete stage.

Before release, `student.cbt.result` shows only "Examination submitted
successfully — your result will be available on [date/time]" — never
score, percentage or pass/fail.

## 4. Result vs. correct answers

Deliberately separate concerns. `isResultVisible()` only ever gates
score/max score/percentage/pass-fail. **Correct answers are never
automatically exposed to a student, at any point, regardless of result
release** — there is no per-question "you answered X, the correct answer
was Y" screen in M23 (explicitly deferred; see §12). The take-page's own
question payload only ever includes option `id`/`text` (`ExaminationQuestionOption`'s
`is_correct` column is excluded at the query level —
`StudentExamAttemptController::take()` selects `id, examination_question_id,
option_text, position` only), so even inspecting the page's own HTML/JS
never leaks it.

## 5. Questions

`Question`: `subject_id` (required), `academic_level_id`/`level_arm_id`
(both optional — M24; null means reusable across every level of that
subject), `question_text`, `topic` (optional free text — M24), `type`
(`App\Enums\ExaminationQuestionType`: `MultipleChoice` / `TrueFalse`, not
mass-assignable), `marks`, `difficulty` (`App\Enums\QuestionDifficulty`:
`Easy`/`Medium`/`Hard` — M24, not mass-assignable), `status`
(`App\Enums\QuestionStatus`: `Active`/`Inactive`/`Archived` — M24,
replaces the original M23 `is_active` boolean, not mass-assignable, changed
only via `activate()`/`deactivate()`/`archive()`). Never hard-deleted —
deleting one is not offered at all, since `ExaminationQuestion.question_id`
is `nullOnDelete` and would silently detach traceability for no real
benefit. Full Question Bank detail (search/filter/lifecycle/authorization)
in `docs/question-bank.md`.

Both question types share the **same** `QuestionOption` table —
true/false is simply a question constrained to exactly two option rows
("True"/"False"). This keeps scoring, snapshotting and validation
completely type-agnostic; `ExaminationQuestionType::fixedOptionCount()`
(`2` for true/false, `null`/"two or more" for multiple choice) is the only
place the type distinction actually matters, and it's enforced in
`App\Http\Requests\Cbt\QuestionRequest::withValidator()` alongside "exactly
one option must be marked correct" — both application-layer invariants
(mirrors `grading_scheme_grades`' own non-overlap rule), not DB
constraints.

## 6. Exam question snapshot

`App\Services\Cbt\ExaminationQuestionService::attach()` copies a
`Question`'s current `question_text`/`type`/`marks` (and every
`QuestionOption` row's `option_text`/`is_correct`/`position`) into a new
`ExaminationQuestion` (+ `ExaminationQuestionOption` rows) **the moment
it's attached** — not deferred until the exam is scheduled. `question_id`
is kept only as a soft traceability pointer (`nullOnDelete`); nothing ever
re-reads the source `Question` for content after attach time.

This was a deliberate design choice over "live while draft, frozen at
schedule time": attach-time snapshotting is simpler to reason about, and
re-attaching (detach + re-attach) already covers "I want to pull in an
edit I just made to the bank question" during `draft`, without a second
freeze mechanism. `App\Http\Controllers\Cbt\ExaminationQuestionController`
enforces `structureEditable()` on both attach and detach, so once
`Scheduled`, an exam's own question set is permanently fixed.

## 7. Student attempts

`App\Enums\ExamAttemptStatus`: `InProgress` / `Completed`. "Not started" is
never a stored state — no `ExamAttempt` row exists at all until the
student actually starts. `unique(examination_id, student_id)` at the DB
level is the real guarantee behind "one attempt per student per
examination" (M23 spec) — not just an application check; a concurrent
double-start (two tabs, a double-click) is caught by the unique constraint
and resolved by resuming the row that won the race
(`App\Services\Cbt\ExamAttemptService::start()`), never a 500 and never a
duplicate row.

Every `ExamAnswer` row for an attempt's questions is bulk-inserted,
unanswered (`selected_option_id` null), the moment the attempt starts —
one per `ExaminationQuestion` — mirroring M13's own `AttendanceRecord`
roster-snapshot convention. "Answered" is always a plain
`whereNotNull('selected_option_id')` check, never a derived "missing row"
case.

## 8. Server-side timing

`expires_at` is computed **once**, at `started_at + duration_minutes`
(capped to never exceed the exam's own `ends_at`), and never
recalculated — the sole timing authority. Nothing about a client-submitted
duration or the browser's own clock is ever trusted for enforcement; the
take-page's countdown is a UI aid only (`x-data`'s `remaining` ticks down
from `expires_at`, computed client-side, purely for display).

Every write path (`answer()`, `submit()`) re-checks `Carbon::now()` against
`expires_at` via `ExamAttemptService::assertActionable()` before accepting
anything:

- if the attempt is already `Completed`, the write is rejected outright;
- otherwise `finalizeIfExpired()` runs first — if the deadline has passed,
  the attempt is auto-marked and finalised right there (`auto_submitted =
  true`), and the original write is then rejected as "already submitted."

A browser refresh/reconnect never loses or duplicates the attempt:
`take()` always re-resolves the *same* row via `(examination_id,
student_id)`, and if it discovers the deadline has already passed, it
finalises it and redirects straight to the result page instead of
re-rendering a stale question form.

## 9. Marking

`ExamAttemptService`'s private `finalize()` is the single seam every
submission (student-initiated or auto-expiry-triggered) goes through, row
locking the attempt (`lockForUpdate()`) first so a concurrent
expiry-triggered and student-triggered submission can never both mark it.
For each `ExamAnswer`, it compares `selected_option_id` against the
snapshotted `ExaminationQuestionOption.is_correct` — never a
client-submitted correctness/marks value — awarding the question's full
`marks` on a match, zero otherwise (including every unanswered question).
`score`/`max_score` are summed with `bcmath` (money-style precision, no
float drift), `percentage = round(score / max_score * 100, 2)`, `passed =
percentage >= pass_mark_percentage`. Resubmitting an already-`Completed`
attempt is a safe no-op — idempotent, never a duplicate marking pass.

Nothing about student ID, school ID, marks, score, percentage, correctness
or pass/fail is ever accepted from the client at any point in this
pipeline — every one of those is either resolved server-side from the
authenticated session (`StudentPortalAuthorizer`) or computed by
`ExamAttemptService` itself.

## 10. Student eligibility

A student sees/may take an examination only when it targets their
*current* enrollment's exact `(academic_session_id, academic_level_id,
level_arm_id)` — `App\Support\Cbt\CbtAuthorizer::studentCanAccess()`. Unlike
`learning_materials`, there is no "whole level" broadcast case: an exam
always belongs to one specific arm, so the match is exact, not
null-tolerant. `Draft` exams are never shown to students at all.
`Examination::rosterDate()` (the exam's own `starts_at` date) plugs it into
the existing `HasClassRoster` trait (shared with `Assessment`/`Assignment`)
for the **staff-side** "who's eligible" question — the student-facing
"which exams can I see" direction is the inverse query in
`StudentExaminationController`, scoped the same way. No id supplied by the
browser is ever trusted — every controller action re-resolves the
signed-in student from the session and re-checks eligibility from scratch,
never trusting a route `{examination}` id alone.

## 11. Teacher authorization

`App\Support\Cbt\CbtAuthorizer` mirrors `App\Support\Assessment\
AssessmentAuthorizer` exactly:

- `cbt.manage` (School Admin, Principal) → any class/subject, full
  authority (create/edit/attach questions/schedule/close any exam, view
  all attempts/results).
- `cbt.author` **without** `cbt.manage` (Teacher) → only a `(level,
  subject)` they hold an **active** M11 `TeacherAssignment` for (that arm,
  or an arm-agnostic assignment) — checked identically to how M14 scopes
  assessment score entry and M22 scopes learning-material uploads. An
  ended assignment stops granting access immediately.

The create-form itself reflects this: a Teacher without `.manage` sees a
pre-filtered "your classes" picker built from their own active
assignments, not a free session/level/arm/subject cascade — so they can't
even attempt an unauthorised combination client-side; the server
re-validates via `CbtAuthorizer` regardless.

## 12. Permissions & module

`cbt.view` / `cbt.author` / `cbt.manage` / `cbt.take` — new in M23, no
existing permission reused: School Admin (all) → Principal `.view` +
`.author` + `.manage` → Teacher `.view` + `.author` (scoped) → Staff
`.view` only → Bursar/Parent none → Student `cbt.take` (the single gate for
every `/student/cbt/*` route, exactly like `portal.student` gates the rest
of the Student Portal).

`Module::Cbt` (`cbt`) — off by default (a specialised opt-in, like
Timetable/Learning Materials), depends on `Module::Assessments` only.
Staff `/cbt/*` routes are hard-gated by `module:cbt` middleware; the
student `/student/cbt` **index** is not (it degrades to a friendly empty
state instead, like Assignments/Learning Materials), but every deeper,
state-mutating student action (show/start/take/answer/submit/result) *is*
middleware-gated, so an exam can never actually be sat while the module is
off even via a direct URL.

## 13. Tenant security

Every table is school-owned (`BelongsToSchool`), `school_id`-leading
indexed. Every academic id in a request (`Rule::exists(...)->where(
'school_id', …)`) and every route id (`{examination}`/`{question}`/
`{examinationQuestion}`) is tenant-scoped `findOrFail` — another school's
id 404s, never leaks a record. `school_id` is never accepted from the
browser (not fillable anywhere; stamped by `BelongsToSchool` from
`TenantContext`, proven by an explicit test posting a bogus `school_id`
and asserting the real tenant's id wins). Cross-student attempt access is
blocked structurally — every student-facing query resolves the attempt via
`(examination_id, student_id)` from the *signed-in* student's own
`StudentPortalAuthorizer::studentFor()`, never a submitted attempt id.
Cross-school IDOR, manipulated exam/question/student ids, a tampered
score/percentage/correctness value, a timer bypass and a duplicate
submission are each covered by an explicit test (see `tests/Feature/Cbt/`).

## 14. M15 Results integration — deferred, documented extension point

CBT results are **not** wired into M15's `ResultRun`/`StudentResult`
pipeline in this milestone — doing so would mean compiling from a second,
structurally different source mid-run, which the spec explicitly asked not
to force. The extension point already exists and needs no new mechanism:
`App\Enums\ScoreSource` on M14's own `AssessmentScore` already has an
`OnlineCbt` case, reserved (per its own docblock) precisely so a future
CBT-to-Results bridge can write a compiled `ExamAttempt` score into an
`AssessmentScore` row with `source = OnlineCbt`, and M15 compiles it like
any other assessment — no second result pipeline, no `ResultRun` changes.
Building that bridge (deciding which exams count toward a result run, how
partial-credit/weighting applies, etc.) is left for a later milestone.

## 15. Deferred / not built here

- Essay/manual-marking questions of any kind.
- Advanced Question Bank taxonomy beyond the M24 `topic`/`difficulty`
  fields — full tagging, question analytics, shared cross-school
  libraries, question pools/randomised selection, versioning, bulk
  import/export (see `docs/question-bank.md` §10).
- A detailed per-question answer-review screen ("you answered X, the
  correct answer was Y") — explicitly out of scope; §4 protects against
  ever exposing correct answers to a student in the meantime.
- Multiple attempts per student per examination.
- Any proctoring: webcam monitoring, screen recording, AI proctoring,
  biometric monitoring, browser lockdown.
- M15 `ResultRun` integration (see §14 for the documented extension
  point).
- Notification (M18) hooks (exam published, result released, etc.).
- Bulk question import, exam templates/cloning.
- Staff analytics beyond the plain attempts/results list.
