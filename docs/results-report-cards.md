# Results & Report Cards

Status: **Milestone 15 — complete.** Turns M14's locked assessment scores into
configurable student results and printable report cards: schools define their
own grading scheme (percentage bands) and result weighting scheme (how much
each assessment category counts), compile a class's term results from locked,
academic-purpose M14 scores, review/approve/publish/lock the run, and generate
a report card whose visible fields the school controls. Built on the existing
seams — `TenantContext` + `BelongsToSchool`, `App\Enums\Permission`,
`module:results`, M8's `AcademicSession`/`AcademicPeriod`, M14's `Assessment`/
`AssessmentScore` — no new authorization or tenancy mechanism, no new packages,
no Redis/queues, no PDF library (deferred — see §12).

M15 does **not** build the CBT engine, Question Bank, online exam delivery,
Entry/Placement testing, the student/parent portal, promotion/graduation,
transcripts, automated report-card comments, a full drag-and-drop report
builder, or a full audit-trail platform — see §13.

## 1. The pieces

| Concern | Model | Table | Belongs to |
|---------|-------|-------|------------|
| A percentage-band scale (A–F, etc.) | `App\Models\GradingScheme` | `grading_schemes` | school |
| One band of a grading scheme | `App\Models\GradingSchemeGrade` | `grading_scheme_grades` | school + scheme |
| How much each category counts | `App\Models\ResultWeightingScheme` | `result_weighting_schemes` | school |
| One category's weight | `App\Models\ResultWeightingSchemeItem` | `result_weighting_scheme_items` | school + scheme |
| One class's compiled term results | `App\Models\ResultRun` | `result_runs` | school |
| A student's overall result in a run | `App\Models\StudentResult` | `student_results` | school + run |
| A student's per-subject result in a run | `App\Models\StudentSubjectResult` | `student_subject_results` | school + run |
| One category's contribution to a subject result | `App\Models\StudentSubjectResultComponent` | `student_subject_result_components` | school + subject result |
| A proposed correction to a subject result | `App\Models\ResultAdjustment` | `result_adjustments` | school + subject result |
| Which report-card fields are visible | `App\Models\ReportCardConfiguration` | `report_card_configurations` | school |

Enums: `App\Enums\ResultRunStatus` (`draft`/`compiled`/`reviewed`/`approved`/
`published`/`locked`), `App\Enums\ResultAdjustmentStatus` (`pending`/`applied`/
`rejected`), `App\Enums\AssessmentPurpose` (`academic`/`practice`/
`entry_placement` — added to M14's `Assessment`, see §13), `App\Enums\ScoreSource`
(`manual`/`online_cbt`/`imported` — added to M14's `AssessmentScore`, see §13).

Controllers: `App\Http\Controllers\Results\{GradingScheme,GradingSchemeGrade,
ResultWeightingScheme,ResultWeightingSchemeItem,ResultRun,ResultAdjustment,
ReportCardConfiguration,ReportCard}Controller`. Form Requests:
`App\Http\Requests\Results\*`. Compilation: `App\Services\Results\ResultCompiler`
(+ `CompilationIssue`/`CompilationOutcome`). Class-scoped comment authorization:
`App\Support\Results\ResultAuthorizer`. Ranking:
`App\Support\Results\RankingCalculator`. Attendance rollup:
`App\Support\Results\AttendanceSummarizer`. Shared roster rule:
`App\Models\Concerns\HasClassRoster` (reused from M13/M14 — `ResultRun`
implements `rosterDate()` as the term's `ends_on`). Views:
`resources/views/results/*`.

```
School
 ├── GradingScheme                    name / is_default / is_active
 │     └── GradingSchemeGrade         code · min/max_percentage · remark · position
 ├── ResultWeightingScheme            name / is_default / is_active
 │     └── ResultWeightingSchemeItem  assessment_category_id · weight_percentage · position
 ├── ResultRun                        one class, one term — status: draft…locked
 │     │  session / period / level / arm  (never spans terms)
 │     │  grading_scheme · weighting_scheme · ranking_enabled
 │     │  compiled/reviewed/approved/published/locked_by + _at
 │     ├── StudentResult              total / average / position / class_size / grade / comments / attendance
 │     ├── StudentSubjectResult       percentage / grade / subject_position / is_adjusted
 │     │     ├── StudentSubjectResultComponent   one per weighted category — raw + weighted, all snapshotted
 │     │     └── ResultAdjustment     pending → applied|rejected — never auto-effective
 │     └── ReportCardConfiguration    (a second row, result_run_id set — the frozen snapshot; see §7)
 └── ReportCardConfiguration          (school-wide / session / session+period scope — the live config; see §7)
```

**Nothing is hardcoded** — not grade bands, not a category weighting, not a
three-term calendar. Grading and weighting are the school's to configure; the
academic context is M8's own `AcademicSession`/`AcademicPeriod`.

## 2. Grading schemes

`GradingScheme` is `BelongsToSchool`; `name` unique per school. Its grades
(`GradingSchemeGrade`) each carry `code` (unique within the scheme),
`min_percentage`/`max_percentage` (`decimal(5,2)`, 0–100), `remark`, `position`
and `is_active`. `GradingSchemeGradeRequest::withValidator` rejects an **active**
band that overlaps another active band in the same scheme (inactive bands are
excluded from the overlap check, so a school can retire a band without deleting
it). `GradingScheme::gradeFor(float $percentage): ?GradingSchemeGrade` is the
**single** place a percentage becomes a grade — both compilation and adjustment
application call it; there is no code path where a grade string is typed
directly into a result row. Managed at `/results/grading-schemes`, gated
`result.manage`.

## 3. Result weighting schemes

`ResultWeightingScheme` mirrors `GradingScheme`'s shape. Its items
(`ResultWeightingSchemeItem`) each tie one **M14 `AssessmentCategory`** to a
`weight_percentage` (unique per scheme — one weight per category);
`ResultWeightingScheme::totalWeight()` sums them. `ResultRunRequest` refuses to
create a run against a weighting scheme whose items don't sum to exactly 100%
(±0.001 for float rounding), naming the actual total in the error. Managed at
`/results/weighting-schemes`, gated `result.manage`.

## 4. The three-term structure, without hardcoding

A `ResultRun` is scoped to exactly one `(academic_session_id, academic_period_id,
academic_level_id, level_arm_id)` — `unique` in the database
(`result_runs_class_term_unique`) and re-checked in `ResultRunRequest`. It never
spans multiple terms. Nigeria's First/Second/Third Term is simply *whatever
periods the school's M8 `AcademicSession` has* — a school running semesters or
any other period count works identically; M15 has no concept of "term 1/2/3",
only "one run per `AcademicPeriod`".

## 5. Result compilation

`ResultCompiler::compile(ResultRun $run, User $by): CompilationOutcome`
(`app/Services/Results/ResultCompiler.php`):

1. Loads the run's eligible students (`HasClassRoster`, same historical
   eligibility rule as M13/M14: enrolled in this exact class as of the term's
   `ends_on`).
2. Derives the **subjects in scope from the data, not the catalogue**: a
   subject enters the run only if it has at least one M14 assessment that is
   both **`locked`** and **`purpose = academic`** in this run's exact
   `(session, period, level, arm)`. A subject nobody has assessed yet this
   term simply does not appear — that is not an error.
3. For each subject, for each weighting-scheme category, for each student: if
   the category was never assessed for that subject, or a student has no score
   row, or has a score row with `score === null`, that is a **blocking
   issue** — compilation **never manufactures a missing score as zero**.
4. Only if the whole pass collects **zero** issues does it write anything: one
   bulk delete of the run's stale rows, then bulk `insert`s (chunked at 500)
   for `student_subject_results`, `student_subject_result_components` and
   `student_results` — never one query per student. A blocked compile leaves
   the run in `draft` and returns every issue
   (`CompilationIssue::message()`) so the UI can name exactly which
   student/subject/category is missing.
5. Recompiling a `draft`/`compiled`/`reviewed` run **replaces** its results
   (delete + reinsert) rather than accumulating stale rows.

Each subject's weighted percentage is `Σ (category_percentage × category_weight
/ 100)`, rounded to 2dp per component and again for the total; a student's
overall `total_percentage` is the sum of their subject percentages,
`average_percentage` the mean. Every percentage is converted to a grade via
`GradingScheme::gradeFor()` and **snapshotted** (`grade_code_snapshot`,
`grade_remark_snapshot`, plus the FK) onto the row — never left to be
recomputed at render time (see §8).

**Raw scores are preserved too**: each `StudentSubjectResultComponent` stores
`raw_score`/`raw_max_score` — summed across every locked assessment in that
category (ordinarily one) — alongside `score_percentage` and
`weighted_contribution`, so a report card can show "Classwork 18/20" and not
just "90%".

## 6. Class position

`App\Support\Results\RankingCalculator::rank(array $scoresByKey): array` is a
small, pure-PHP **competition ranking** (`1, 2, 2, 4` — a tie shares the lower
rank, the next distinct value skips by the tie count) — deliberately not a raw
SQL `RANK()` window function, for portability across MySQL and SQLite.
Positions are computed twice: `StudentResult.position` within the run's whole
roster (the class), and `StudentSubjectResult.subject_position` per subject
within the same roster — **never** the whole school, and never across runs.
`ResultRun.ranking_enabled` (default on) turns position off entirely for a run
that doesn't want it; when off every position column is `null` and the report
card hides the position/class-size rows.

Ranking runs once after compilation and again after any adjustment is applied
(`ResultCompiler::recomputeRanking()`) — an adjusted score can move the whole
class's order, so a changed subject percentage first triggers
`refreshOverallTotals()` (recomputing each student's `total_percentage`/
`average_percentage`/overall grade from their current subject rows) and then
re-ranks both tiers. Both the totals refresh and the two ranking passes use a
small `UPDATE ... CASE id WHEN ... END` bulk-update helper
(`ResultCompiler::bulkUpdateById()`) — one statement per 500-row chunk, never
one `UPDATE` per student.

## 7. Report-card configuration

`ReportCardConfiguration` is **typed and relational, not a JSON blob** — 24
independent `show_*` boolean columns (student info, per-subject columns,
overall performance, attendance, comments, signatures), each toggled
independently. `ReportCardConfiguration::FIELDS` lists them; the edit form and
the request validation both iterate that one array rather than duplicating the
list.

**Scope & precedence.** A configuration row has nullable `academic_session_id`
+ `academic_period_id`. `forScope($sessionId, $periodId)` resolves, in order:
exact `(session, period)` → `(session, null)` (session-wide) → `(null, null)`
(school-wide default) → an **unsaved** all-fields-on instance if the school has
configured nothing at all. At most one row exists per exact scope, enforced at
the **application layer** (`ReportCardConfiguration::exactScopeRow()`,
find-or-new by exact scope) — like M8's `AcademicSession::makeCurrent()`, not a
database constraint, because MySQL/SQLite treat two `NULL`s as distinct in a
composite unique index. This ships the school-wide default now; per-level
overrides are a natural, additive extension later (add a nullable
`academic_level_id` column and one more precedence step) — the architecture
does not block it.

**Signatures** (`principal_signature_path`/`class_teacher_signature_path`)
follow M6's private-logo pattern exactly (`SIGNATURE_DISK = 'local'`, never
web-served directly — streamed through a gated, `result.view`-checked route).

## 8. Historical snapshots

Once a run is **locked**, its report card must remain reproducible even if the
grading scheme, weighting scheme, assessment categories, or report-card
configuration change afterwards. Two separate mechanisms give this:

- **Result data**: every percentage/grade/weight/raw-score on
  `StudentSubjectResult`/`StudentSubjectResultComponent`/`StudentResult` is
  computed **once**, at compile time, and stored — never re-derived from
  today's grading/weighting scheme when a report card is rendered. Renaming a
  category, moving a grade boundary, or rebalancing a weighting scheme after
  compilation has **zero** effect on an already-compiled result
  (`ResultStructureTest` proves this for a grading-scheme edit, a
  weighting-scheme edit, and a category rename, all after locking).
- **Report-card field visibility**: `ReportCardConfiguration::snapshotForRun()`
  is called once, when a run is first **published**, and copies the live
  scope-resolved configuration's 24 toggles onto a **second row** tied to that
  run (`result_run_id` set, via `ResultRun::reportCardSnapshot(): HasOne` — the
  FK is set through the relation, never mass-assigned). `ReportCardController`
  picks the source deliberately by lifecycle stage:
  - **unlocked** (draft/compiled/reviewed/approved/**published**) → always the
    *live* `forScope()` config, so a school can still see accurate previews
    and correct its configuration right up to the point of locking;
  - **locked** → the frozen `forRun()` snapshot, so a later configuration
    change never rewrites a historical, already-locked report card.

  Signature **images** are deliberately **not** copied into the snapshot — only
  the show/hide toggle is frozen. A locked report card always renders whatever
  signature image is live at view time (or none, if the toggle is off). This
  is a documented trade-off (see §14); re-signing after a staff change is
  expected to be more common than needing pixel-identical historical
  signatures.

  The **same view** (`results/report-card/show.blade.php`) renders both the
  live preview and the final locked output — there is no second rendering
  path to drift out of sync.

- **A snapshot row shares the school-default's (null, null) scope columns** —
  distinguished from it only by `result_run_id`. Every *live*-scope read or
  write (`forScope()`, the config edit form, the signature upload/remove/show
  actions, `exactScopeRow()`) therefore filters `whereNull('result_run_id')`
  explicitly; anything that queried plain `(academic_session_id,
  academic_period_id)` without that filter could find and silently overwrite a
  locked run's frozen snapshot. `ReportCardConfiguration::exactScopeRow()` is
  the one place that invariant is enforced — every write path (the controller,
  the seeder) goes through it rather than a raw `updateOrCreate`/`firstOrCreate`.

## 9. Manual score entry & score correction

M15 introduces **no new score-entry system**. A score is still an M14
`AssessmentScore`, entered through M14's existing screens; `ScoreSource`
(`manual`/`online_cbt`/`imported`, added to `assessment_scores` via an additive
migration) records how it got there — M15 v1 only ever writes `manual`, but the
column already exists for a future CBT/import pipeline (see §13).

**Correcting a compiled result** is a controlled, auditable workflow — never a
free-text edit:

```
propose (result.adjust) ──▶ ResultAdjustment (pending)
                                  │
                      apply (result.adjust)          reject (result.adjust)
                                  │                          │
                                  ▼                          ▼
                    StudentSubjectResult updated;      no effect, status = rejected
                    grade re-derived via gradeFor();
                    status = applied; ranking recomputed
```

A `ResultAdjustment` alone changes **nothing** — proposing one records
`original_value`/`adjusted_value`/`reason`/`requested_by`/`requested_at` only.
`ResultAdjustment::apply()` is the only place a subject result's percentage
changes after approval: it updates `percentage`, re-derives the grade through
`GradingScheme::gradeFor()` (never types one directly), sets `is_adjusted =
true` + `adjusted_by`/`adjusted_at`, then the controller calls
`ResultCompiler::recomputeRanking()` so the change propagates to the whole
run's position. Adjustments are only available once a run
`requiresAdjustment()` (`approved`/`published`/`locked` — see §10); before
that, recompiling is the correction path. There is **no bulk "unlock"** for a
result run — approved-or-later numbers move only one subject result at a time,
through this workflow (see §14).

## 10. Lifecycle & approval

```
draft ── compile ──▶ compiled ── review ──▶ reviewed ── approve ──▶ approved ── publish ──▶ published ── lock ──▶ locked
  ▲          (result.manage)      (result.manage)      (result.publish)         (result.publish)        (result.publish)
  └── recompile (result.manage, draft/compiled/reviewed only) ──┘
```

`ResultRunStatus::recompilable()` is true for `draft`/`compiled`/`reviewed` —
compiling again before approval simply replaces the results (§5). Each
transition is `DB::transaction`-wrapped on the model (`ResultRun::review()`/
`approve()`/`publish()`/`lock()`) and stamps `*_by`/`*_at`; `publish()` also
calls `ReportCardConfiguration::snapshotForRun()`. `requiresAdjustment()` is
true for `approved`/`published`/`locked` — from approval onward, a correction
goes through §9's adjustment workflow, not recompilation. There is no partial
audit-trail claim beyond these timestamps — see §14.

| Transition | Holders |
|---|---|
| create / compile / recompile | `result.manage` (School Admin, Principal) |
| review | `result.manage` |
| approve / publish / lock | `result.publish` (School Admin, Principal) |
| propose / apply / reject an adjustment | `result.adjust` (School Admin, Principal) |
| view / record a class-teacher comment | `result.enter` (+ Teacher, class-scoped — §11) |
| view only | `result.view` (+ Staff) |

## 11. Authorization & module

Two gates on every `/results/*` route: **`module:results`** (404 when the
module is off) **and** an M4 permission `->can(...)`. `Module::Results`
depends only on `Module::Assessments` (never Timetable/Attendance/CBT).

| Permission | Holders (M4 bundles) | Grants |
|------------|----------------------|--------|
| `result.view` | School Admin, Principal, Teacher, Staff | see runs, results, grading/weighting schemes, report cards |
| `result.enter` | School Admin, Principal, Teacher | record a class teacher comment — *class-scoped for teachers* |
| `result.manage` | School Admin, Principal | create/compile/recompile/review runs; manage grading & weighting schemes; manage report-card configuration |
| `result.publish` | School Admin, Principal | approve, publish, lock a run |
| `result.adjust` | School Admin, Principal | propose, apply, reject a result adjustment |

A class-teacher comment is authorized by `ResultAuthorizer::canCommentOnRun()`
— identical in shape to M13's `AttendanceAuthorizer` but with no subject
dimension (a class-teacher comment is class-wide, not per-subject): a
`result.manage` holder can comment on any run; a Teacher can comment only if
they hold an active M11 `TeacherAssignment` for the run's `(level, arm)`
(arm-agnostic assignments included); a principal comment can only ever be set
by a `result.manage` holder, even on the same request (`ResultCommentRequest`
conditionally includes `principal_comment` in its payload). Bursar / Parent /
Student / role-less → 403 on every result route. Route ids are **not**
route-model-bound — resolved by tenant-scoped `findOrFail`.

**Consolidated from the originally-suggested 9 permissions to 5** (see §14):
`result.view`/`result.enter`/`result.publish` already existed from M4's
scaffolding; only `result.manage` and `result.adjust` are new this milestone.
The separately-suggested `report.view`/`report.generate`/`report.configure`
were folded into the existing `result.view`/`result.manage` — a report card is
just another view of a result run, and its configuration is just another
`result.manage` action, so a dedicated permission set would have been
redundant granularity.

## 12. Report card output

Print-friendly HTML/A4 (`results/report-card/show.blade.php`) — a `@media
print` rule hides everything outside the card and a "Print" button calls
`window.print()`. **PDF generation is deferred**: the project has no existing
PDF dependency, and adding one is out of scope for "no new heavy package" —
the browser's own print-to-PDF covers the near-term need. Sections render only
per the resolved `ReportCardConfiguration` (§7): student info, per-subject
breakdown (raw score, %, grade, remark, position — each independently
toggleable), overall performance (total/average/position/class size),
attendance (rolled up via `App\Support\Results\AttendanceSummarizer` from
M13's `AttendanceRegister`/`AttendanceRecord` — **no duplicated attendance
storage**, 2 queries regardless of class size, scoped to submitted-only
registers for the run's `(session, period)`), class-teacher/principal
comments, and class-teacher/principal signatures (M6-pattern private images).
Branding (school name/address/contact/logo) reuses M6's `SchoolSetting`,
gated identically (the logo is only shown if the viewer holds
`school.settings.view`, so a Teacher/Staff viewer without that permission
never gets a broken-image icon).

**Student photo has no image to show** — M9 never built student photo
capture. `show_student_photo` exists as a toggle for forward compatibility
only; today it is a no-op even when on (see §14).

## 13. M15 → future CBT compatibility

Two enums exist specifically so a future Question Bank/CBT milestone slots in
without redesigning M15:

- **`AssessmentPurpose`** (added to `assessments` via an additive migration —
  M14's table is never edited directly): `Academic` (M14's only value today,
  and the only one `countsTowardResults()`), `Practice`, `EntryPlacement`. A
  future entry/placement test can use the same `Assessment`/`AssessmentScore`
  tables without ever leaking into a term's `ResultRun` — compilation filters
  `purpose = academic` explicitly (§5).
- **`ScoreSource`** (added to `assessment_scores`): `Manual` (M14/M15's only
  value today), `OnlineCbt`, `Imported`. Both a future **Question Bank → CBT →
  Assessment Attempt → AssessmentScore** pipeline and a **Paper Exam → Manual
  Score Entry → AssessmentScore** pipeline converge on the same
  `assessment_scores` table `ResultCompiler` already reads — no new result
  engine needed when CBT ships, only a new score-entry path feeding the
  existing one.

## 14. Architectural decisions flagged for review

Four decisions made without an explicit spec answer, documented here so they
can be revisited:

1. **Permission consolidation** (§11) — 5 permissions instead of the
   originally-sketched 9, by reusing `result.view`/`.enter`/`.publish` from
   M4's scaffolding and folding the suggested `report.*` set into
   `result.view`/`result.manage`.
2. **No bulk "unlock"** for a result run (§9) — once `approved`, numbers move
   only through the per-subject adjustment workflow, never a full
   re-compile-and-overwrite. This is more auditable but slower for a
   wholesale re-mark; a future milestone could add a guarded bulk-unlock if
   that need shows up in practice.
3. **Signatures are not snapshotted per run** (§8) — only the show/hide toggle
   freezes at publish; the image itself is always the live one. A school that
   needs a pixel-identical historical signature (e.g. after a principal
   change) is not served by this today.
4. **`show_student_photo` renders nothing** (§12) — the toggle exists purely
   so a future M9 photo-capture feature doesn't require another
   report-card-configuration migration; today it has no visible effect either
   way.

## 15. Tenant isolation

Non-negotiable, enforced server-side, identical in shape to every prior
milestone:

- Every model `use`s `BelongsToSchool` — `SchoolScope` global scope on every
  query, `school_id` stamped from `TenantContext` on create, **never** in
  `$fillable`, **never** read from input. Bulk `insert`s in `ResultCompiler`
  set `school_id` explicitly from `TenantContext::idOrFail()` (raw `insert`
  bypasses the `creating` hook).
- Every session/period/level/arm/scheme id is validated inside the active
  school (`Rule::exists(...)->where('school_id', ...)`, generic "invalid", no
  leak). Route ids are resolved by tenant-scoped `findOrFail`, never
  route-model-bound.
- Feature + model tests prove School A cannot view/compile/review/approve/
  publish/lock/adjust/comment-on/configure-report-card-for School B's run
  (404/403), cannot create a run referencing School B's session/level/scheme
  ("invalid"), and a report card / signature route for another school's run
  or configuration 404s.

## 16. Indexing & performance

`result_runs`: `unique(school_id, academic_session_id, academic_period_id,
academic_level_id, level_arm_id)`, `(school_id, status)`.
`student_results`: `unique(result_run_id, student_id)`, `(school_id,
student_id)`, `(school_id, result_run_id, position)`.
`student_subject_results`: `unique(result_run_id, student_id, subject_id)`,
`(school_id, student_id)`, `(school_id, result_run_id, subject_id)`.
`student_subject_result_components`: `(school_id, student_subject_result_id)`.
`result_adjustments`: `(school_id, student_subject_result_id)`, `(school_id,
status)`. `report_card_configurations`: `unique(school_id,
academic_session_id, academic_period_id)`, `unique(result_run_id)`.

Query discipline, proved by `tests/Feature/Results/ResultStructureTest.php`:

- Compilation reads scores, assessments and the weighting scheme **once**
  each (never per student) and writes via chunked bulk `insert`s; a 12-student
  compile issues the **same** query count as a 2-student compile.
- Ranking and the overall-totals refresh use a single `UPDATE ... CASE id
  WHEN ... END` statement per 500-row chunk (`bulkUpdateById()`) instead of
  one `UPDATE` per student.
- The run index/show pages and the report-card view eager-load their
  relations (`with(['subject', 'components', 'adjustments' => ...])`,
  grouped once by student) — no N+1 as class size or subject count grows.
- Attendance rollup is exactly 2 queries per run regardless of class size.

## 17. Seed data

`DatabaseSeeder` (Alpha school): a **Standard Grading** scheme (A 70–100 /
B 60–69.99 / C 50–59.99 / D 45–49.99 / E 40–44.99 / F 0–39.99) and a
**Standard Weighting** scheme (Classwork 20% / Test 30% / Examination 50%);
locked Mathematics and English assessments across all three categories with
real per-student scores; a `ResultRun` for Primary 1 Gold / First Term,
compiled → reviewed → approved → published → **locked**, with a snapshotted
report-card configuration; a school-wide default `ReportCardConfiguration`
(`show_student_photo` off, since M9 has no photos yet). Verified via
`migrate:fresh --seed`: 8 student results with positions 1–8 (no ties in this
data), 16 subject results, 48 components, grades computed correctly against
the thresholds above.

## 18. Deferred

Question Bank · CBT engine / online exam delivery · Entry/Placement
Assessment admin UI · question randomization / anti-cheating / CBT analytics ·
student & parent portal result views · WhatsApp/SMS/email notifications ·
promotion & graduation · advanced transcripts · automatic report-card comments
· a full drag-and-drop report-card designer · a full audit-trail /
data-retention platform · advanced result analytics · PDF export (the browser
print dialog covers this for now) · per-level/per-arm report-card
configuration overrides (the scope model supports adding this additively,
§7) · bulk "unlock" of an approved/published/locked run (§14) · signature
image snapshotting per run (§14) · student photo capture (§14, M9).
