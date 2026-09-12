# Question Bank

Status: **Milestone 24 — complete.** Evolves M23's minimal `Question`/
`QuestionOption` structure into a proper reusable, searchable, school-scoped
Question Bank — the same two models, extended in place, never a parallel
question system. See `docs/cbt.md` for the exam/attempt/marking/result-release
architecture this bank feeds into.

## 1. What changed from M23

M23 shipped `App\Models\Question` as deliberately minimal groundwork: a
subject, text, type, marks and a single `is_active` boolean. M24 adds:

- **Optional level/arm scoping** (`academic_level_id`/`level_arm_id`,
  both nullable) — a question can stay subject-only and reusable across
  every level of that subject, or be scoped to one specific class.
- **`topic`** — a free-text field, not a taxonomy. Filterable/searchable
  alongside `question_text`.
- **`difficulty`** (`App\Enums\QuestionDifficulty`: `Easy`/`Medium`/`Hard`)
  — a fixed three-level scale, not a school-configurable list.
- **A real `status` lifecycle** (`App\Enums\QuestionStatus`:
  `Active`/`Inactive`/`Archived`) replacing the M23 `is_active` boolean.

Nothing about `QuestionOption`, `ExaminationQuestion`,
`ExaminationQuestionOption`, or the attach-time snapshot mechanism changed
— see §3.

## 2. Lifecycle

`Question::activate()` / `deactivate()` / `archive()` — plain,
unrestricted transitions between the three states (no "invalid
transition" guard the way `Examination`'s status has one): a school can
freely move a question between all three, including re-activating an
archived one. This is deliberate — unlike an examination's own lifecycle
(where a wrong transition has real consequences for students mid-attempt),
a question's status only ever governs whether it can be **newly**
attached to a *future* exam, so there is no unsafe transition to guard
against.

`status`/`difficulty` are **not** mass-assignable — set only via
`Question::activate()`/`deactivate()`/`archive()` and the controller's
explicit `$question->difficulty = $request->input('difficulty')`
assignment (mirroring `type`'s existing M23 convention), never through
`$fillable`.

Only `QuestionStatus::Active->isSelectable()` questions may be **newly**
attached to an examination (`App\Enums\QuestionStatus::isSelectable()`).
`Inactive`/`Archived` questions:

- are excluded from `App\Http\Controllers\Cbt\ExaminationQuestionController
  ::create()`'s attach picker;
- are rejected server-side even via a direct/tampered request — the
  controller checks `$question->isSelectable()` explicitly, **and**
  `App\Services\Cbt\ExaminationQuestionService::attach()` re-checks it
  independently as a second, defence-in-depth gate (so any future caller
  of the service gets the same protection, not just this one controller
  action).

**No hard delete, ever** — not even for a question never attached to
anything. A school corrects a mistake by editing or archiving, never by
removing the row. This matters doubly here: `App\Models\ExaminationQuestion
.question_id` is a nullable, `nullOnDelete` traceability pointer *precisely*
so a hard-deleted source question would never break a snapshot — but M24
doesn't expose a delete action at all, so that safety net is never
actually exercised in normal use.

## 3. Relationship to the M23 exam snapshot (unchanged)

`App\Services\Cbt\ExaminationQuestionService::attach()` still copies a
question's `question_text`/`type`/`marks` and every option's
`option_text`/`is_correct`/`position` into a new `ExaminationQuestion` (+
`ExaminationQuestionOption` rows) the **moment** it is attached — nothing
here reads `topic`/`difficulty`/`academic_level_id`/`level_arm_id` at all,
since those are Question Bank *discovery* metadata (search/filter/attach-
picker compatibility), not exam content. Editing, deactivating or
archiving a Question Bank entry **after** attach changes nothing about any
exam that already uses it — verified by an explicit test that edits a
question's `question_text`/`marks`/`options` via the real HTTP `update()`
endpoint after attach and asserts the snapshot is untouched, and another
that archives the source question after attach and confirms the exam still
schedules, and a student can still start/answer/submit it normally.

## 4. Authorization

Reuses M23's `cbt.view`/`cbt.author`/`cbt.manage` permissions and
`App\Support\Cbt\CbtAuthorizer` — no second authorization system.

`CbtAuthorizer::canManageQuestionFor(User $user, int $subjectId, ?int
$levelId, ?int $armId)` mirrors `canAuthorFor()` (M23's exam-authorship
check) with one difference: because a question's level/arm are **optional**,
a Teacher without `cbt.manage` needs only **some** active M11
`TeacherAssignment` for that subject when `$levelId` is null (a
level-agnostic question); when a level is given, the check narrows to that
exact level (+ arm-agnostic-or-matching arm), identical to the exam-side
rule. `canManageQuestion(User $user, Question $question)` is the
already-existing-row form, used for edit/activate/deactivate/archive.

*Viewing* the bank (`cbt.view`) is **not** further scoped by class — every
staff role that can see it sees the whole school's reusable question list;
scoping only applies to **write** actions (create/edit/archive), matching
the spec's explicit "Teacher: manage questions only for subjects/classes
covered by their active TeacherAssignments" (view access for Staff/Teacher
was already unrestricted since M23).

`store()`/`update()` both re-derive the authorization check from the
**submitted** `subject_id`/`academic_level_id`/`level_arm_id` (after
validation, never trusted directly) — so a Teacher cannot create a
question for, or move an existing one into, a subject/level outside their
own assignments, even if the edit form's own client-side picker would
never have offered it.

## 5. Tenant isolation

Every `Question`/`QuestionOption` row is `BelongsToSchool`, exactly like
every other CBT table. Every `{question}` route parameter is resolved by
tenant-scoped `findOrFail` — another school's id 404s on view/edit/
activate/deactivate/archive, proven by an explicit test that uses the
*acting* school's own valid `subject_id` in the request body specifically
so the assertion isolates the **route parameter's** tenant check from the
separate (and already-covered) `subject_id` validation-rule check. The
bank's own index list is naturally tenant-scoped by the same global scope
— a cross-school question is never listed, searched into, or attach-able.

## 6. Validation

`App\Http\Requests\Cbt\QuestionRequest` (evolved from M23, same class):

- `subject_id` — required, tenant-scoped `Rule::exists`.
- `academic_level_id` — nullable, tenant-scoped `Rule::exists`.
- `level_arm_id` — nullable, tenant-scoped `Rule::exists`; `withValidator`
  checks it belongs to the given level (mirrors every other arm↔level
  check in this codebase) and — if an arm is given without a level — is
  rejected outright.
- if a level **is** given, `withValidator` checks the subject is actually
  offered at that level via the `level_subject` pivot (the same check
  `TimetableEntryRequest`/`ExaminationRequest`/`LearningMaterialRequest`
  already use) — skipped entirely when the question stays level-agnostic,
  since "offered at a level" is meaningless without one.
- `difficulty` — required, `Rule::enum(QuestionDifficulty::class)`.
- `topic` — optional, `max:150`.
- `type`/options — unchanged from M23: exactly one correct option, and
  the type-appropriate option count (exactly two for true/false, two or
  more for multiple choice).

## 7. CBT attach-time compatibility

`App\Models\Question::scopeCompatibleWith(int $subjectId, int $levelId,
?int $armId)` is the single query scope both the attach picker
(`ExaminationQuestionController::create()`) and the attach action's own
server-side re-check (`store()`) use: subject must match exactly; the
question's `academic_level_id` must be null (any level) or match; its
`level_arm_id` must be null (any arm of that level) or match. A question
scoped to a *different* level than the exam's own is simply invisible in
the picker and rejected (`422`) if attached via a direct/tampered request
— proven by an explicit test.

## 8. Performance

The bank index (`GET /cbt/questions`) is paginated (15/page), every filter
(subject/level/arm/type/difficulty/status) and the `q` search
(`question_text`/`topic` `LIKE`) apply as `WHERE` clauses on an indexed,
tenant-scoped query — `school_id`-leading indexes exist for
`(academic_level_id, level_arm_id)`, `status` and `difficulty`
independently (`questions_class_index`, `questions_school_id_status_index`,
`questions_school_id_difficulty_index`). Eager-loads `subject`/`level`/
`arm`/`options` — no N+1 as the list grows, proven by an explicit
query-count regression test (with and without filters applied).

## 9. UI

`resources/views/cbt/questions/*` — `index.blade.php` (search + six
filters, status/difficulty/type badges, Preview/Edit/Activate/Deactivate/
Archive actions inline), `_form.blade.php` (shared create/edit partial;
mirrors `cbt/examinations/_form.blade.php`'s own unrestricted-cascade vs.
scoped-Teacher-picker split), `preview.blade.php` (new — a read-only,
staff-only view with the correct option highlighted, same pattern as
`cbt/examinations/preview.blade.php`). No new frontend framework, no SPA —
plain Blade + Tailwind + a small amount of Alpine for the dynamic options
editor and level→arm/assignment cascades, identical in shape to every
other create/edit form in this app.

## 10. Deferred / not built here

- Tagging beyond the single free-text `topic` field, question pools,
  randomised selection, versioning, bulk import/export, a shared
  cross-school library — the full M24-named "Question Bank" ambition the
  M23 docs already flagged as deliberately deferred; this milestone is
  the *evolution* of the minimal structure into something genuinely
  reusable and filterable, not that larger system.
- Essay/manual-marking question types (still M23's explicit scope
  boundary).
- A detailed answer-review screen for students (still deferred — see
  `docs/cbt.md` §15).
- Per-teacher "my questions" filtered view (the bank index shows
  everything; a Teacher's *write* scope is still enforced, just not a
  dedicated "mine only" list toggle).
