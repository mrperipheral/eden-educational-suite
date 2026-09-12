# Project Status

_Last updated: 2026-10-03_

## Current milestone

**Milestone 25 — Entry / Placement Assessment: COMPLETE.**

Next up: further **Domain Modules** — Reporting. Not started — do not
begin without picking one up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 · Alpine.js ·
Vite · PHPUnit · Pint.

## Environment (verified 2026-10-02)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 (app) · SQLite `:memory:` (tests) |
| Local mail | Mailpit (`127.0.0.1:1025`, UI `:8025`) — `.env` only, not committed |
| Tests | `php artisan test` — 1138 passing |
| Build | `npm run build` — passing |
| Formatting | `vendor/bin/pint --test` — passing |

## Delivered

- **M1 — Platform Foundation** (`foundation-complete`).
- **M2 — Authentication & User Foundation** (`authentication-complete`).
- **M3 — Multi-School / Strict Tenant Isolation** (`multischool-foundation-complete`) — `docs/tenancy.md`.
- **M4 — Roles & Permissions** (`roles-permissions-complete`) — `docs/authorization.md`.
- **M5 — School Onboarding** (`school-onboarding-complete`) — `docs/onboarding.md`.
- **M6 — School Settings & Configuration** (`school-settings-complete`) — `docs/school-settings.md`.
- **M7 — Feature / Module Activation** (`feature-activation-complete`) — `docs/module-activation.md`.
- **M8 — Academic Foundation** (`academic-foundation-complete`) — `docs/academic-foundation.md`.
- **M9 — Student Management** (`student-management-complete`) — `docs/student-management.md`.
- **M10 — Guardian / Parent Management** (`guardian-management-complete`) — `docs/guardian-management.md`.
- **M11 — Teacher Management** (`teacher-management-complete`) — `docs/teacher-management.md`.
- **M12 — Timetable Management** (`timetable-management-complete`) — `docs/timetable-management.md`.
- **M13 — Attendance Management** (`attendance-management-complete`) — `docs/attendance-management.md`.
- **M14 — Assessment & Assignments** (`assessment-assignments-complete`) —
  `docs/assessment-management.md`.
- **M15 — Results & Report Cards** (`results-report-cards-complete`) —
  `docs/results-report-cards.md`.
- **M16 — Parent Portal** (`parent-portal-complete`) — `docs/parent-portal.md`.
- **M17 — Student Portal** (`student-portal-complete`) — `docs/student-portal.md`.
- **M18 — Communication & Notification Foundation** (`communication-notifications-complete`) — `docs/communication.md`.
- **M19 — Fees & Fee Management** (`fees-management-complete`) — `docs/fees.md`.
- **M20 — Online Fee Payment / Paystack** (`online-payment-paystack-complete`) — `docs/paystack.md`.
- **M21 — Promotion & Graduation** (`promotion-graduation-complete`) — `docs/promotion.md`.
- **M22 — Learning Materials** (`learning-materials-complete`) — `docs/learning-materials.md`.
- **M23 — CBT / Online Examinations** (`cbt-online-examinations-complete`) — `docs/cbt.md`.
- **M24 — Question Bank** (`question-bank-complete`) — `docs/question-bank.md`.
- **M25 — Entry / Placement Assessment** (this milestone,
  `entry-placement-assessment-complete`) — `docs/entry-placement-assessment.md`; see below.

## Delivered in Milestone 25

A simple, school-scoped record of an assessment conducted for a prospective
or newly admitted student — **recording only, never a placement decision**.
Full detail in `docs/entry-placement-assessment.md`.

- **`App\Models\EntryAssessment`** (school-owned) — one row per candidate
  per subject assessed; "Subject(s)" is satisfied by recording several rows
  sharing the same `candidate_name`/`admission_reference`/`assessed_on`
  rather than inventing a batch/grouping entity, the same one-class-one-
  subject-per-row choice `Assessment` (M14) already makes.
  `candidate_name` is always stored explicitly, independent of the
  optional `student_id` link — a self-contained historical record even for
  a genuinely prospective candidate with no `Student` row at all.
  `academic_level_id` is required, `level_arm_id` nullable (the arm may not
  be decided yet); `score` is nullable (a record can exist ahead of the
  assessment being conducted), `max_score` always required. `percentage`
  is **never stored** — always derived via `EntryAssessment::percentage()`
  (`bcmath`, `null` until a score exists). `result` is a free-text,
  school-defined outcome label (e.g. "Pass"/"Fail"), **not** an enum — the
  vocabulary is the school's to choose, and it never drives any automatic
  action. `assessor_id` is captured from the authenticated user at
  creation, mirroring `assessments.created_by`/`learning_materials.
  uploaded_by` — never a request-supplied name, never mass-assignable.
- **A minimal lifecycle** — `App\Enums\EntryAssessmentStatus`
  (`Active`/`Archived` only) is the record's own retention lifecycle,
  deliberately **not** the assessment's conducted/pending state (read
  directly from `score === null`) and **not** a pass/fail outcome (the
  free-text `result` field). `archive()`/`restore()` are freely reversible,
  like `Question::archive()`/`activate()` (M24) — nothing ever attaches to
  an entry assessment record the way an exam attaches to a Question Bank
  entry. No hard delete, ever.
- **New permissions** `entry_assessment.view`/`.record`/`.manage` (stored
  as `placement.view`/`.record`/`.manage` — the Permission enum's own
  dotted-value convention doesn't allow an underscore in the first
  segment), reusing the exact M23/M24 three-permission shape: School Admin
  + Principal → full; Teacher → `.view` + `.record`, scoped via
  `App\Support\EntryAssessment\EntryAssessmentAuthorizer::canManageFor()`
  (mirrors `CbtAuthorizer::canAuthorFor()` precisely) to classes/subjects
  they hold an active M11 `TeacherAssignment` for; Staff → `.view` only;
  Bursar/Parent/Student → none. *Viewing* the list (including export) stays
  unscoped by teaching assignment, the same choice M23/M24 make for their
  own staff-facing lists. No second authorization system.
- **CSV export** (`GET /entry-assessments/export`,
  `response()->streamDownload()` + `fputcsv()` — no new package) shares the
  **exact same filtered, tenant-scoped query** the list page uses, so it
  always reflects the active search/filters and can never include another
  school's rows. Iterates via `Builder::chunk(200, …)` rather than
  `cursor()` — `cursor()` skips Eloquent's eager-loading entirely (would
  N+1 `level`/`arm`/`subject`/`assessor` per row); `chunk()` keeps memory
  bounded to one page at a time while still eager-loading each page's
  relations in bulk. Excludes internal ids — only human-facing columns.
- **Reuses `Module::EntryAssessment`** (new) — off by default (a
  specialised opt-in, like Timetable/Learning Materials/CBT), depends on
  `Module::Academics` only — a linked `Student` record is optional, not
  required.
- **No CBT engine of its own** — the existing M23/M24 Question Bank/CBT
  system is completely untouched; nothing in this milestone reads from or
  writes to it. No placement recommendation, recommended class/arm,
  placement decision workflow, automatic placement/enrolment, promotion/
  graduation, or AI-based placement decisions — see `docs/entry-placement-
  assessment.md` §10 for the full explicitly-out-of-scope list.
- **1 new table** (migration `2026_10_03_100000`): `entry_assessments` —
  `school_id`-leading indexed (`entry_assessments_class_index` plus
  `subject_id`/`student_id`/`status`/`assessed_on`).
- **5 new views** — `resources/views/entry-assessments/*`: a searchable/
  filterable `index.blade.php` (level/arm/subject/status filters, an Export
  CSV button that carries the active filters through as query parameters),
  a shared `_form.blade.php` (mirrors `cbt/questions/_form.blade.php`'s own
  unrestricted-cascade vs. scoped-Teacher-assignment-picker split, plus an
  optional student-link select), `show.blade.php` (read-only detail page),
  `create.blade.php`/`edit.blade.php` (thin wrappers). One new "Entry
  Assessment" staff nav link, module + permission gated.
- **Seeder** — Alpha Academy's Entry Assessment module is explicitly turned
  on (off by default); one record links to the same Primary 1 Gold student
  used throughout the Parent/Student Portal demos and is recorded by Tomiwa
  Teacher (his real M11 assignment — a genuine demonstration of the Teacher
  scoping, not a bypass); two more are genuine prospective candidates with
  no `Student` row at all — one still awaiting its score, one archived
  (did not proceed with admission) to demonstrate the retention lifecycle.
- **Docs** — new `docs/entry-placement-assessment.md`; `PROJECT_STATUS.md`,
  `docs/roadmap.md`, `docs/database-design.md`, `CLAUDE.md` updated.
- **51 new tests** under `tests/Feature/EntryAssessment/*` (+
  `EntryAssessmentTestCase` base) — record creation (with and without a
  score, with and without a linked `Student`), percentage computed not
  stored, field validation (candidate name/level/subject/date/max-score
  required, score-exceeds-max-score rejected, arm-belongs-to-level,
  subject-offered-at-level), editing (assessor/status untouched by an
  edit), viewing, search/filter/pagination, archive/restore via HTTP (never
  a hard delete, an archived record stays viewable, editing one record
  never touches another's status), authorization (School Admin/Principal
  full access, Teacher scoped by subject+level+arm and rejected outside it,
  Staff view-only, Bursar/Parent/Student/role-less forbidden, module-off
  404), cross-school isolation + IDOR protection (view/edit/archive/index/
  export all reject another school's data, `school_id` from the request
  ignored), export (CSV header + rows, respects search/status filters,
  never includes another school's records, shows the assessor's name not
  their internal id, Bursar forbidden), and N+1 regression on the index
  (with and without filters).

## Delivered in Milestone 24

Evolves M23's minimal `Question`/`QuestionOption` structure — the same two
models, extended in place — into a proper reusable, searchable Question
Bank, without touching the M23 exam/attempt/marking/result-release
architecture at all. Full detail in `docs/question-bank.md`.

- **Optional level/arm scoping** (`academic_level_id`/`level_arm_id`, both
  nullable) — a question stays subject-only and reusable across every
  level of that subject, or is scoped to one specific class. A free-text
  `topic` field and a fixed `difficulty` scale
  (`App\Enums\QuestionDifficulty`: Easy/Medium/Hard) round out the
  Question Bank fields the spec asked for — deliberately **not** a
  configurable taxonomy, tagging system or question pool.
- **A real lifecycle** — `App\Enums\QuestionStatus`
  (Active/Inactive/Archived) replaces M23's `is_active` boolean;
  `Question::activate()`/`deactivate()`/`archive()` are unrestricted,
  freely-reversible transitions (unlike an exam's own one-way lifecycle) —
  a question's status only ever gates whether it can be **newly** attached
  to a future exam, never anything already using it. No hard delete, ever.
- **The M23 attach-time snapshot is completely unaffected** — editing,
  deactivating or archiving a Question Bank entry after it's been attached
  to an exam changes nothing about that exam; verified by tests that edit
  a question via the real HTTP endpoint post-attach and archive a source
  question post-attach, in both cases asserting the exam's own snapshot
  (and, for the archive case, a student's ability to start/answer/submit
  normally) is completely untouched.
- **Only `Active` questions may be newly attached** — enforced at two
  independent layers: the attach picker excludes non-active/incompatible
  questions, and `App\Services\Cbt\ExaminationQuestionService::attach()`
  itself re-checks `isSelectable()` and subject/level/arm compatibility
  (`Question::scopeCompatibleWith()`), so even a direct/tampered request
  is rejected server-side regardless of what the UI offers.
- **`App\Support\Cbt\CbtAuthorizer::canManageQuestionFor()`** mirrors the
  existing M23 `canAuthorFor()` exam-authorship check exactly: School
  Admin/Principal (`cbt.manage`) → any subject/level; Teacher
  (`cbt.author` only) → only a subject (+ level/arm, if the question sets
  one) they hold an active M11 `TeacherAssignment` for. *Viewing* the bank
  stays unscoped (a shared, reusable resource); only create/edit/archive
  are scoped. No second authorization system.
- **New/extended search & filters** on the existing Question Bank list —
  free-text search (question text + topic), plus subject/level/arm/type/
  difficulty/status filters, all as indexed, paginated, eager-loaded
  queries — proven flat (no N+1) as the bank grows, filtered or not.
- **New view** — `cbt/questions/preview.blade.php` (staff-only, correct
  option highlighted, same pattern as the existing exam preview); the
  question list and create/edit form gained the new filters/fields, the
  old single toggle-active action became three explicit
  activate/deactivate/archive actions.
- **1 additive migration** (`2026_10_02_100000`) onto M23's `questions`
  table — existing rows are data-migrated (`is_active` true/false →
  `status` active/inactive) before the old column is dropped; no data
  loss, and every M23 exam snapshot is unaffected either way since it
  never reads this table.
- **Seeder** — the existing M23 demo questions now carry realistic
  topic/difficulty values and (for the Mathematics ones, created by
  Tomiwa Teacher) explicit level scoping matching his real M11
  assignment; one additional, never-attached, archived question
  demonstrates the lifecycle state in the bank's own list.
- **Docs** — new `docs/question-bank.md`; `docs/cbt.md`,
  `PROJECT_STATUS.md`, `docs/roadmap.md`, `docs/database-design.md`
  updated.
- **34 new tests** under `tests/Feature/Cbt/*` — question creation/
  validation with the new level/arm/topic/difficulty fields (arm-belongs-
  to-level, subject-offered-at-level, difficulty required, preview);
  lifecycle (activate/deactivate/archive via HTTP, an archived question
  can be reactivated, inactive/archived excluded from the attach picker
  and rejected server-side even via a direct request, a level-mismatched
  question rejected as incompatible, a level-agnostic question compatible
  with any matching-subject exam); authorization (Admin/Principal
  unrestricted, Teacher scoped by subject alone or subject+level, a
  Teacher blocked from a foreign subject/level on both create and update,
  cross-school 404 on preview/edit/archive/list, Bursar/Parent/Student
  forbidden); snapshot integrity after archive (exam still schedules,
  student can still attempt it); N+1 regression on the bank index with
  and without filters. All existing M23 CBT tests continue to pass
  unchanged (aside from the two shared test helpers/factory updated for
  the `is_active` → `status` migration).

## Delivered in Milestone 23

School-scoped online examinations: multiple-choice and true/false
questions, one timed attempt per student, server-side automatic marking,
and immediate or scheduled result release. No essay/manual-marking
questions, no proctoring. Full detail in `docs/cbt.md`.

- **`App\Models\Question` + `QuestionOption`** (school-owned; a reusable,
  subject-scoped bank — deliberately minimal M24-compatible groundwork,
  not the bank itself) are never read directly by a live exam.
  **`App\Services\Cbt\ExaminationQuestionService::attach()`** snapshots a
  question's `question_text`/`type`/`marks` and every option's
  `option_text`/`is_correct`/`position` into a new
  **`App\Models\ExaminationQuestion` + `ExaminationQuestionOption`** the
  moment it's attached — never re-read from the source afterwards, so
  editing or deleting the bank question never changes an exam that
  already uses it.
- **`App\Enums\ExaminationStatus`** (`Draft` → `Scheduled` → `Closed`,
  one-way, not mass-assignable) is deliberately separate from
  **`App\Enums\ExamAttemptStatus`** (`InProgress`/`Completed`) — closing an
  exam stops new attempts *starting*; one already in progress is still
  individually finalised by its own expiry check, never force-submitted by
  the close action. `Examination::schedule()` requires ≥1 question and
  freezes both structure and metadata; `isOpenForAttempts()` additionally
  gates on `starts_at`/`ends_at` against the server clock.
- **`App\Services\Cbt\ExamAttemptService`** is the single seam every
  student-facing action goes through. `start()` bulk-inserts one
  unanswered `ExamAnswer` per question up front (mirrors M13's
  `AttendanceRecord` roster-snapshot convention) and computes `expires_at`
  once at `started_at + duration_minutes` (capped to the exam's own
  `ends_at`) — never recalculated, never trusting a client value.
  `answer()`/`submit()` re-check the server clock against `expires_at`
  before accepting anything; an expired attempt is auto-marked and
  finalised (`auto_submitted = true`) the next time anything touches it.
  `submit()` is idempotent — resubmitting an already-`Completed` attempt
  is a safe no-op. **`unique(examination_id, student_id)`** at the DB
  level is the real "one attempt per student" guarantee; a concurrent
  double-start is caught by the constraint and resolved by resuming the
  row that won the race, never a 500 or a duplicate.
- **Marking** compares each answer's `selected_option_id` against the
  snapshotted option's `is_correct` server-side only, via `bcmath`
  (`score`/`max_score`/`percentage`/`passed` — never float drift, never a
  client-submitted mark). Unanswered questions score zero. Correct answers
  are never exposed to a student at any point — the take-page's own
  question payload excludes `is_correct` at the query level.
- **Result release** (`App\Enums\ResultReleaseMode`: `Immediate`/
  `Scheduled` + a validated `result_release_at`) is evaluated inline, at
  read time, via `ExamAttempt::isResultVisible()` — this app has no
  queue/scheduler at all (confirmed: zero `Schedule::` calls, `sync` queue
  in tests) and M23 doesn't add one; a release "happens" simply because
  the clock has moved past `result_release_at` the next time anyone asks,
  mirroring `ResultRunStatus::visibleToParents()`'s own lifecycle-gate
  precedent with an actual timestamp instead.
- **`App\Support\Cbt\CbtAuthorizer`** mirrors `AssessmentAuthorizer` (M14)
  exactly: `cbt.manage` (School Admin, Principal) → any class/subject;
  `cbt.author` without `.manage` (Teacher) → only a `(level, subject)` they
  hold an active M11 `TeacherAssignment` for. The create-form only offers
  a Teacher's own assigned classes.
- **New permissions** `cbt.view` / `.author` / `.manage` / `.take`,
  slotted into the existing role tiers: School Admin + Principal → full;
  Teacher → `.view` + `.author` (scoped); Staff → `.view` only;
  Bursar/Parent → none; Student → `cbt.take` (the single gate for the
  whole `/student/cbt/*` surface, like `portal.student` gates the rest of
  the Student Portal).
- **Reuses `Module::Cbt`** (declared since M7, flipped available this
  milestone) — off by default (a specialised opt-in), depends on
  `Module::Assessments` only. Staff `/cbt/*` routes are hard-gated by
  `module:cbt`; the student index degrades to an empty state instead, but
  every deeper student action (show/start/take/answer/submit/result) is
  still hard-gated.
- **M15 integration deferred, extension point documented** — CBT results
  are not compiled into `ResultRun` in this milestone; the path is M14's
  own `ScoreSource::OnlineCbt` case on `AssessmentScore`, already reserved
  for exactly this.
- **7 new tables** (migrations `2026_10_01_100000`–`100060`): `questions`,
  `question_options`, `examinations`, `examination_questions`,
  `examination_question_options`, `exam_attempts`, `exam_answers` — all
  `school_id`-leading indexed.
- **16 new views** — staff question-bank CRUD (a single Alpine-driven
  form handling both MCQ and true/false option authoring), examination
  CRUD + preview (correct answers shown to staff only) + schedule/close +
  question attach/detach + a plain attempts/results list; Student Portal
  exam list + instructions + a single-page Alpine exam-taking interface
  (server-synchronised countdown display, question navigation with
  answered/unanswered indicators, auto-save per answer, automatic expiry
  handling) + a result page that respects release visibility. One new
  staff nav link and one new Student Portal tab.
- **Seeder** — Alpha Academy's CBT module is explicitly turned on (off by
  default); a Mathematics quiz (immediate release) is left completely
  unattempted for a live demo, and an English quiz (scheduled release) has
  one genuinely-submitted, genuinely-marked attempt by the same Primary 1
  Gold demo student the Portal/Learning-Materials demos already use — a
  real "submitted but not yet visible" demonstration, not simulated. Both
  exams' questions go through the real bank-create → attach-and-snapshot →
  schedule flow.
- **Docs** — new `docs/cbt.md`; `PROJECT_STATUS.md`, `docs/roadmap.md`,
  `docs/database-design.md`, `CLAUDE.md` updated.
- **77 new tests** under `tests/Feature/Cbt/*` (+ `CbtTestCase` base) —
  question creation/validation (exactly one correct option, type-correct
  option counts, tenant isolation); examination CRUD + lifecycle (draft
  editable, scheduling requires ≥1 question, invalid transitions
  rejected, metadata frozen once scheduled); question snapshotting
  (editing/deleting the source question never changes an attached
  snapshot, attach/detach only while draft); the full attempt service
  (start/resume/duplicate-prevention, answering, correct/wrong/unanswered
  scoring, idempotent submission, server-side expiry + auto-finalisation,
  `expires_at` capped to the exam window); result release (immediate vs.
  scheduled visibility, hidden-then-visible-at-the-server-clock,
  correct-answers-never-exposed); authorization (all 7 roles + role-less,
  module-off 404 without affecting Assessments/Results, teacher scoping
  including an ended assignment losing access, tenant isolation on every
  surface); the full student HTTP flow (eligibility by exact class match,
  draft exams never shown, start→take→answer→submit→result,
  cross-student and cross-school access prevention, a tampered
  foreign-question option rejected); N+1 regression guards on both listing
  pages.

## Delivered in Milestone 22

Lets a teacher or admin upload a single file (PDF, image or audio — video
declared but disabled) for a subject + class; students in that class
view/download it. Deliberately small: no drafts, autosave, versioning or
approval workflow. Full detail in `docs/learning-materials.md`.

- **`App\Models\LearningMaterial`** (school-owned; `academic_session_id` +
  optional `academic_period_id` + `academic_level_id` + optional
  `level_arm_id` + required `subject_id`; `type`/`file_path`/`file_name`/
  `file_size`/`mime_type`/`extension`/`uploaded_by` not mass-assignable,
  derived from the uploaded file itself). No status/draft column — a row is
  live the moment it exists. Deleting one is a genuine hard delete of the
  row **and** its stored file together (`deleteWithFile()`) — nothing else
  references a material, unlike `FeePayment`/`Enrollment`.
- **`App\Enums\LearningMaterialType`** (`document`/`image`/`audio`/`video`)
  is the single source of truth for supported extensions/MIME types.
  `video` is declared with real extensions/MIME types but
  `isEnabled()` excludes it everywhere — the Form Request's `mimetypes:`
  whitelist (content-sniffed, so a video file renamed with a `.pdf`
  extension still fails), the create-form's hint text, and the service's
  own independent `isEnabled()` check. Enabling video later is flipping one
  boolean — no migration, no model change, no new storage mechanism.
- **`App\Services\LearningMaterials\LearningMaterialUploadService`** stores
  the file **first** (a Laravel-generated name, school-id-prefixed folder,
  private `local` disk — same one M6/M15 already use, never a public URL),
  then creates the row inside a `DB::transaction()`; a failure after the
  file is written deletes the orphan file before rethrowing — never a row
  without a file or a file without a row.
- **`App\Support\LearningMaterials\LearningMaterialAuthorizer`** mirrors
  `AssessmentAuthorizer` (M14) exactly: `material.manage` (School Admin,
  Principal) → any class/subject and may delete anything; `material.upload`
  without `.manage` (Teacher) → only a `(level, subject)` they hold an
  active M11 `TeacherAssignment` for, and may delete only their own
  uploads. The create-form itself only offers a Teacher's own assigned
  classes — not a free picker they'd then get rejected from.
- **New permissions** `material.view` / `.upload` / `.manage`, slotted into
  the existing role tiers: School Admin + Principal → full; Teacher →
  `.view` + `.upload` (scoped); Staff → `.view` only; Bursar/Parent/Student
  → none. A student reaches their own current class's materials through
  the existing `portal.student` permission — no new one needed.
- **Reuses `Module::LearningMaterials`** (declared since M7, flipped
  available this milestone) — off by default (a specialised opt-in, like
  Timetable/CBT), depends on `Module::Academics` only.
- **1 new table** (migration `2026_09_30_100000`): `learning_materials`,
  `school_id`-leading indexed.
- **6 new views** — `resources/views/learning-materials/*` (list with
  level/subject filters, a single-page upload form — full cascade for
  `.manage` holders, a pre-filtered "your classes" picker for scoped
  Teachers) + `resources/views/student/learning-materials/index.blade.php`
  (Student Portal, degrades to an empty state when the module is off or the
  student has no current enrollment, like Assignments/Timetable); one new
  staff nav link and one new Student Portal tab, both module + permission
  gated.
- **Seeder** — Alpha Academy's Learning Materials module is explicitly
  turned on (off by default); Tomiwa Teacher uploads a Mathematics PDF for
  Primary 1 Gold specifically (his real M11 assignment — a genuine
  demonstration of the scoping, not a bypass), and the School Admin uploads
  an image for the whole of Primary 1 (`level_arm_id` null). Both go
  through the real upload service, not direct inserts.
- **Docs** — new `docs/learning-materials.md`; `PROJECT_STATUS.md`,
  `docs/roadmap.md`, `docs/database-design.md`, `CLAUDE.md` updated.
- **39 new tests** under `tests/Feature/LearningMaterials/*` (+
  `LearningMaterialTestCase` base) and `tests/Unit/Enums/
  LearningMaterialTypeTest` — type resolution + enabled/disabled sets;
  upload service (PDF/image/audio succeed, an honest video is rejected and
  never stored, a video disguised with a `.pdf` extension is still
  rejected, an unrecognised extension is rejected, a DB failure after the
  file is stored removes the orphan file); HTTP authorization (all 7 roles
  + role-less, module-off 404, a Teacher can upload only for their own
  assigned class and not another, an arm-agnostic assignment covers every
  arm of that level, a tampered video upload is rejected server-side even
  with a disguised extension, tenant isolation on view/upload/delete,
  `school_id` from the browser ignored, a Teacher can delete only their own
  upload while a `.manage` holder can delete any, subject-not-offered-at-
  level rejected); Student Portal (sees only their current class's
  materials, whole-level materials visible regardless of arm, a different
  arm's/session's material never leaks, module-off and unlinked-student
  safe empty states, non-student role forbidden, download scoped to the
  student's own class — cross-class and cross-school ids 404).

## Delivered in Milestone 21

A safe, auditable academic progression workflow built entirely on M9's
existing `Student`/`Enrollment` architecture — promoting a student never
rewrites history, it creates a new enrollment for the target session while
the old one is preserved, closed. Full detail in `docs/promotion.md`.

- **`App\Models\PromotionBatch`** (school-owned; one bulk promotion run —
  source session/period/level/arm, target session/level/arm, `created_by`,
  aggregate `status` not mass-assignable) + **`PromotionRecord`**
  (school-owned + student-scoped; one row per student per batch —
  `promoted`/`skipped`/`failed`, `unique(school_id, promotion_batch_id,
  student_id)`). Graduation has **no** separate batch/history table — four
  additive `graduated_*` columns on `students` are the entire audit trail
  (a student is graduated at most once at a time, unlike promotion).
- **`App\Services\Promotion\PromotionEligibilityService`** — the single
  seam both the roster UI and `PromotionService` use to decide who may be
  promoted (active status + a matching active enrollment; no invented
  pass/fail rule) and whether a student is already in the target session.
  **`PromotionService::promoteBatch()`** processes every selected student
  in its **own** `DB::transaction()` with the `Student` row
  `lockForUpdate()`-ed — one student's failure never rolls back another's
  success — and reuses M9's own `Enrollment::makeActive()` unchanged to
  create the new placement while closing (never deleting) the old one.
  **`GraduationService::graduate()`/`reactivate()`** transition
  `StudentStatus::Graduated` and back, with no hard-coded graduating level;
  `graduateBatch()` isolates one student's failure from the rest the same
  way.
- **No DB-level uniqueness constraint on `enrollments(session, level,
  arm)`** for promotion's own duplicate/concurrency protection —
  deliberately rejected because it would have broken M9's own
  `test_historical_enrollments_are_preserved`. Concurrency safety instead
  comes from the `Student` row lock plus an "already in target
  session"/"already graduated" business-rule re-check after the lock is
  acquired.
- **New permissions** `promotion.view` / `promotion.manage` /
  `graduation.manage`, slotted into the existing role tiers: School Admin +
  Principal → full; Teacher/Staff → `.view` only, no execution;
  Bursar/Parent/Student → none (Parent/Student reach their own/linked
  child's *current* placement/status through the existing portals,
  unchanged — `Student::currentEnrollment` is a live relation, so no portal
  code changes were needed).
- **Reuses `Module::Promotion`** (new, on by default) — depends on
  `Module::Students` only, not Fees/CBT/Learning Materials.
- **One targeted addition to an existing request** —
  `App\Http\Requests\Student\EnrollmentRequest` (M9) now rejects creating a
  **new** enrollment for a graduated student ("Reactivate them before
  adding a new enrollment") — the explicit, authorised reversal path the
  spec requires, without building a second system.
- **2 new tables** (migrations `2026_09_29_100000`–`100010`):
  `promotion_batches`, `promotion_records` — both `school_id`-leading
  indexed. 1 additive migration (`2026_09_29_100020`) onto M9's `students`
  table (`graduated_at`/`graduated_academic_session_id`/`graduation_notes`/
  `graduated_by`).
- **6 new views** under `resources/views/promotion/*` — a two-step,
  bookmarkable **GET** flow (pick source class → eligible roster + target
  picker + confirm) rather than a stateful wizard, deliberately avoiding a
  generic workflow engine; bulk select-all/individual checkboxes; a batch
  result summary; a graduation history list + source-picker → roster →
  confirm flow; one new staff nav link (`Promotion`, module + permission
  gated).
- **Seeder** — Alpha Academy gets a second, non-current academic session
  (`2026/2027`) to promote into, one Primary 1 Gold student promoted to
  Primary 2 Gold there (the *other* Primary 1 Gold student — the Parent/
  Student Portal, Communication and Fees demo child used throughout the
  seed — is deliberately left untouched), and one JSS 2 student graduated
  outright. Both go through the real services, not direct inserts.
- **Docs** — new `docs/promotion.md`; `PROJECT_STATUS.md`,
  `docs/roadmap.md`, `docs/database-design.md`, `CLAUDE.md` updated.
- **50 new tests** under `tests/Feature/Promotion/*` (+ `PromotionTestCase`
  base) — eligibility (active+matching-enrollment required, graduated/
  withdrawn/wrong-arm/wrong-status excluded, cross-school never leaks,
  already-in-target-session detection), promotion service (single, bulk,
  historical enrollment preservation, duplicate/re-run skip-not-duplicate,
  a graduated student in the selection fails safely, a nonexistent student
  id fails without aborting the rest of the batch, exactly one record per
  student per batch, aggregate status Completed/PartiallyCompleted/Failed),
  graduation service (lifecycle + metadata, enrollment closed but
  preserved, no-current-enrollment still succeeds, duplicate/withdrawn
  rejected, batch isolation, reactivate + its own rejection, the
  EnrollmentRequest guard), and authorization/tenant isolation for both
  promotion and graduation (all 7 roles + role-less, module-off 404,
  cross-school batch/student/session ids rejected, `school_id` from the
  browser ignored, successful end-to-end HTTP flows).

## Delivered in Milestone 20

Lets an authorised parent or student start an online fee payment through
Paystack and have a genuinely verified successful payment recorded into
M19's existing fee/payment system — never a parallel one. Full detail in
`docs/paystack.md`.

- **`App\Models\PaystackTransaction`** (school-owned + student-scoped, its
  own table — deliberately separate from `FeePayment`: an attempt can fail
  or be abandoned and must never become an authoritative payment).
  `App\Enums\PaystackTransactionStatus` (`pending` → `successful` /
  `failed` / `abandoned` / `verification_failed`) is not mass-assignable —
  only the model's own `markSuccessful()`/`markFailed()`/`markAbandoned()`/
  `markVerificationFailed()`, called from inside a row-locked DB
  transaction, ever move it.
- **`App\Services\Paystack\PaymentInitiationService`** validates the
  requested amount against the student's *current* outstanding balance via
  M19's own `FeeStatementBuilder` (never a client-submitted total, never
  more than actually owed), then initializes the transaction with
  Paystack's `/transaction/initialize` (redirect/"Standard" checkout — the
  app never collects, stores, or transmits raw card data). A failed
  Paystack call rolls the local row back too — no orphan `pending` rows.
- **`App\Services\Paystack\PaymentVerificationService::verifyAndRecord()`**
  is the single idempotent core both the browser callback and the webhook
  call: resolves the transaction by reference (bypassing tenant scope,
  since neither caller has one yet), anchors `TenantContext` to *that row's
  own* school, row-locks it inside a DB transaction, and — only if still
  `pending` — re-verifies with Paystack's `/transaction/verify` (never
  trusting a webhook body's own claims or a browser query parameter alone),
  checks amount/currency/context match, and only then calls M19's own
  `FeePaymentService::record()` (with a new `planFifoAllocation()` helper —
  oldest outstanding charge first) to create the real payment. A resolved
  (non-`pending`) row short-circuits to a no-op — the guard against
  duplicate webhooks, a reloaded callback, or a webhook/callback race.
- **`App\Http\Controllers\PaystackWebhookController`** — outside
  `auth`/`tenant`/`module` entirely, CSRF-exempted. Determines *which*
  school's secret key to verify the signature with by first looking up the
  payload's `reference` against our own stored transactions (a plain read,
  never a trust decision) — the school is always resolved from trusted
  stored data, never from the request itself. `HMAC-SHA512` over the raw
  body via `hash_equals()`, per Paystack's documented mechanism.
- **`SchoolSetting`** gains `paystack_enabled` / `paystack_public_key` /
  `paystack_secret_key` (`encrypted` cast — Laravel's native `Crypt`,
  keyed by `APP_KEY`, no new infrastructure) / `paystack_test_mode`
  (additive onto M6's table), edited at `/settings/school/payments` under
  the *existing* `school.settings.view`/`.update` permissions. The secret
  is never rendered back into the form; a blank field on save keeps the
  existing key.
- **No new permissions.** Reuses `portal.parent` / `portal.student` (M16/
  M17) for initiation/receipt visibility and `school.settings.*` (M6) for
  configuration — the M20 spec's suggested `fees.pay_online` /
  `fees.payment_view` were considered and deliberately not added (see
  `docs/paystack.md` §8 for the reasoning).
- **Portal integration** — `ParentOnlinePaymentController` /
  `StudentOnlinePaymentController` (create/store/callback), each resolving
  its student through the same `ParentPortalAuthorizer` /
  `StudentPortalAuthorizer` every other portal controller uses. A "Pay
  online" button appears on the existing fee statement only when Paystack
  is actually configured and something is outstanding. A receipt view
  (school, student, payer, amount, date, internal + Paystack reference,
  status, fee allocation) renders on successful callback.
- **1 new table** (migration `2026_09_28_100010`): `paystack_transactions`
  — `reference` unique, `fee_payment_id` nullable-unique (the idempotency
  guard is an index lookup). 1 additive migration
  (`2026_09_28_100000`) onto M6's `school_settings`.
- **Seeder** — Alpha Academy has Paystack enabled in test mode with
  placeholder (non-functional) test-style keys, so the settings screen and
  the "Pay online" entry point are demoable; an actual checkout attempt
  fails gracefully (the "could not confirm payment yet" path) since no
  real Paystack account backs the demo keys.
- **Docs** — new `docs/paystack.md`; `PROJECT_STATUS.md`, `docs/roadmap.md`,
  `docs/database-design.md`, `docs/fees.md` updated.
- **44 new tests** under `tests/Feature/Paystack/*` (extending M19's own
  `FeesTestCase`) — configuration (encryption at rest, secret never
  re-rendered, blank-keeps-existing), initiation (authorised parent/
  student, unrelated student, wrong school, amount-exceeds-balance,
  disabled config, orphan-row-on-API-failure, reference uniqueness),
  verification (success/failed/abandoned, amount/currency mismatch,
  unknown reference, repeated verification is a no-op, cross-school
  attempt, unreachable API leaves it `pending`), webhook (valid/invalid/
  missing signature, duplicate delivery, webhook-after-callback race,
  unknown reference, wrong-school-secret forgery attempt), financial
  integrity (FIFO allocation across multiple charges, statement/history
  visibility, void behaves identically to a manual payment), and portal
  isolation (parent/student IDOR attempts, module-off, role restrictions).

## Delivered in Milestone 19

A production-ready, tenant-safe fee management system: school-configured
fee categories and structures, student-specific charges snapshotted from a
structure (immune to a later structure edit), manual payment recording
with allocation across one or more charges, and a server-calculated fee
statement shared by staff and the Parent/Student portals. Online payment
(Paystack) is deliberately **not** built here — M20. Full detail in
`docs/fees.md`.

- **`App\Models\FeeCategory`** (school-owned, mirrors `AssessmentCategory`
  from M14 exactly — school-defined, not hard-coded) + **`FeeStructure`**
  (category × session × optional period × level × optional arm, amount;
  freely editable) + **`StudentFeeCharge`** (school-owned + student-scoped;
  **snapshots** the structure's amount/context at creation — a later
  structure edit never touches an existing charge; `discount_amount` /
  `waived_at`/`waived_by`/`waiver_reason` not mass-assignable, changed only
  through `applyDiscount()` / `waive()` / `unwaive()`) + **`FeePayment`**
  (manual receipt — cash/bank transfer/POS/cheque/other via
  `App\Enums\PaymentMethod`; `reference` unique per school; never edited or
  deleted, only `void()`-ed, a reversal that excludes it from balances
  while keeping the row) + **`FeePaymentAllocation`** (how much of a
  payment applies to which charge). No hard delete anywhere in the module.
- **`App\Services\Fees\FeeChargeService`** (raise a charge from a
  structure or manually; always uses the student's *current* enrollment for
  class context, never client input) + **`FeePaymentService`** (records a
  payment and its allocations in one `DB::transaction()` with the target
  charges `lockForUpdate()`-ed; rejects a non-positive allocation, an
  allocation against another student's charge, an allocation exceeding a
  charge's own outstanding balance, and allocations exceeding the payment's
  own total — all atomically) + **`FeeStatementBuilder`** (the single seam
  the staff statement page and both portals call — the M16 shared-renderer
  pattern applied to fees).
- **No floating-point money.** Every amount column is `decimal(12,2)`
  (cast `decimal:2`); every calculation uses `bcmath` on those strings —
  discount limits, allocation totals, statement sums, dashboard-wide totals
  (via `SELECT SUM(...)`, never Eloquent's float-casting `->sum()`).
  Outstanding balance is always computed server-side, never trusted from a
  client total.
- **New permissions** `fees.view` / `.report` / `.manage` /
  `.record-payment` / `.adjust`, slotted into the existing role tiers:
  Bursar → all five (full operational fee management); Principal →
  `.view` + `.report` only (oversight without write access, matching the
  pre-existing `finance.view`-only precedent since M4); Teacher/Staff →
  none; Parent/Student reach their own/linked child's statement through
  the existing `portal.parent` / `portal.student` (no new permission — the
  M16/M17/M18 pattern), strictly read-only.
- **Reuses `Module::Fees`** (declared since M7) — now `isAvailable()`, on
  by default, depends on `students` only.
- **5 new tables** (migrations `2026_09_27_100000`–`100040`):
  `fee_categories`, `fee_structures`, `student_fee_charges`, `fee_payments`,
  `fee_payment_allocations` — every one school_id-leading-indexed;
  `fee_payments` additionally unique on `(school_id, reference)`.
  `Student` gained `feeCharges()` / `feePayments()` relations.
  `fees/_statement-body.blade.php` is one shared partial rendering the
  charges/payments lists, included by the staff, Parent Portal and Student
  Portal statement pages alike — no duplicated markup.
- **Seeder** — Tuition (mandatory) and Books (optional) categories +
  structures for Primary 1 Gold's first term, charges raised across the
  whole roster, a discount and a hardship waiver each on one student, and a
  partial bank-transfer payment (with allocation) for the Student Portal
  demo child, so the fee statement has real data in all three audiences on
  a fresh `migrate:fresh --seed`. Alpha Academy's module override for Fees
  was removed (it predated this milestone, from the M7 demonstration seed)
  so the module now simply uses its on-by-default catalogue value.
- **Docs** — new `docs/fees.md`; `PROJECT_STATUS.md`, `docs/roadmap.md`,
  `docs/database-design.md`, `CLAUDE.md` updated.
- **56 new tests** under `tests/Feature/Fees/*` (+ `FeesTestCase` base) —
  category/structure CRUD + permissions, charge creation (from a structure
  and manually) + historical-integrity-under-structure-edit, discount/
  waiver limits, payment recording + allocation + duplicate-reference +
  over-allocation + invalid-amount rejection + void, statement totals +
  parent/student visibility, an explicit tenant-isolation checklist
  (`FeeTenantIsolationTest`) covering every item the spec named by name,
  module-off 404s, and an N+1 regression test for a student with many
  transactions.

## Delivered in Milestone 18

The school's Communication Hub as the system of record for school
communication, plus a shared in-app notification centre used by staff, the
Parent Portal and the Student Portal alike. Delivery channels are a small,
honest abstraction — only in-app notifications are actually delivered; no
WhatsApp/SMS/email provider code exists yet. Full detail in
`docs/communication.md`.

- **`App\Models\CommunicationThread` + `CommunicationMessage`** (school-owned;
  staff-facing shared inbox in this milestone) — optional `student_id` /
  `guardian_id` link, `category` (`App\Enums\CommunicationCategory`),
  `status` (`App\Enums\CommunicationStatus`: open → resolved/escalated,
  reopenable), not mass-assignable, never hard-deleted.
  `/communication/threads/*` gated `module:notifications` +
  `communication.view`/`.create`/`.manage`/`.resolve`/`.escalate`.
- **`App\Models\Announcement`** (school-owned) — `status`
  (`App\Enums\AnnouncementStatus`: draft → published, not mass-assignable) +
  `audience` (`App\Enums\AnnouncementAudience`: everyone/all_staff/teachers/
  parents/students). `AnnouncementController@index`/`@show` mounted at three
  route names (`announcements.*` staff, `parent.announcements.*`,
  `student.announcements.*`) — one controller, no duplication (the M16/M17
  shared-renderer pattern); visibility is a query scope
  (`Announcement::scopeVisibleToRole()`), not just a route gate.
- **`App\Models\Notification`** (school-owned, its own `user_notifications`
  table — deliberately not Laravel's polymorphic `notifications` table,
  which has no `school_id`) + **`App\Services\Notifications\
  NotificationDispatcher`** (bulk-inserts; the single seam any module uses
  to raise a notification without knowing about delivery channels). The
  notification centre (`NotificationController`) needs no extra permission —
  self-scoped to `auth()->user()`, tenant-scoped for free — and is shared
  verbatim by staff/Parent Portal/Student Portal via three route names.
- **Event-driven foundation** — three domain events (`AnnouncementPublished`,
  `CommunicationMessageAdded`, `CommunicationThreadEscalated`),
  auto-discovered listeners under `App\Listeners\Notifications`, dispatched
  synchronously (no queue introduced). Only M18's own events are wired up —
  the mechanism is established for future modules to reuse, not every future
  notification.
- **`App\Enums\NotificationChannel`** (`in_app`/`whatsapp`/`sms`/`email`) —
  only `in_app` is implemented (`isImplemented()`); the rest are declared
  with no provider code.
- **New permissions** `communication.view`/`.create`/`.manage`/`.resolve`/
  `.escalate`, `announcement.view`/`.manage`, slotted into the existing role
  tiers (Principal → manage everything; Bursar/Teacher/Staff → view + create,
  Teacher also resolve/escalate; Parent/Student reuse `portal.parent`/
  `portal.student`, no new permission needed for their side).
- **Reuses `Module::Notifications`** (declared since M7, description
  updated) for the whole surface — now `isAvailable()`, on by default,
  depends on nothing.
- **4 new tables** (migrations `2026_09_26_100000`–`100030`):
  `communication_threads`, `communication_messages`, `announcements`,
  `user_notifications` — every one school_id-leading-indexed.
- **Seeder** — two announcements (one published to everyone, one draft to
  teachers) and one escalated communication thread about the Student Portal
  demo child, demonstrating both notification events end-to-end.
- **Docs** — new `docs/communication.md`; `PROJECT_STATUS.md`,
  `docs/roadmap.md` updated.
- **35 new tests** under `tests/Feature/Communication/*` (+
  `CommunicationTestCase` base) — thread CRUD/lifecycle, permission tiers,
  announcement draft/publish/audience-visibility (staff + both portals),
  notification read/unread/mark-all, event-driven notification dispatch
  (message → assignee, escalation → managers, announcement → audience),
  cross-school isolation, module-off 404s, N+1 regression, hard-delete
  absence.

## Delivered in Milestone 17

A secure, read-only window for a signed-in student onto their own published
data — mirrors the Parent Portal (M16) but for the student's own record
directly (no "which child" question). Built on the same seams; no second
authentication system, no second tenancy mechanism, no second report-card
generator. Full detail in `docs/student-portal.md`.

- **`Student.user_id`** (new, additive column, migration
  `2026_09_25_100000_add_user_id_to_students_table.php` — M9's own migration
  untouched) — nullable, unique per school, **not** mass-assignable, set only
  through `StudentController::updateUser()` (mirrors `Guardian.user_id` /
  `Teacher.user_id` exactly).
- **`App\Support\Portal\StudentPortalAuthorizer`** — resolves the signed-in
  user's own `Student` record; no `{student}` route parameter exists at all
  in the Student Portal (unlike the Parent Portal, a student has at most one
  linked record).
- **7 thin controllers** under `App\Http\Controllers\Portal\Student*` +
  `resources/views/student/*` — dashboard, read-only profile, results,
  report cards (reusing M15/M16's `ReportCardRenderer`), attendance (reusing
  `AttendanceSummarizer`), assignments (M14, view-only — no submission
  workflow yet), timetable (M12, published + current class only).
  `ResultRunStatus::visibleToParents()` / `AssignmentStatus::
  visibleToParents()` are reused as-is from M16 (identical visibility rule
  for any non-staff portal viewer).
- **No new permission** — reuses `Permission::PortalStudent`
  (`portal.student`), declared since M4. **No new module logic** — flips
  `Module::StudentPortal->isAvailable()` to `true` (on by default, depends on
  `Module::Students` only, declared since M7).
- The shared M15 report-card view's audience-aware back-link gained a third
  branch for `portal.student`.
- **Seeder** — a Student-role account (`student@example.com`) linked to the
  same Primary 1 Gold student already used for the Parent Portal demo (a
  school can enable both portals for one child); demonstrates the "no linked
  student record" empty state implicitly via every other seeded student.
- **Docs** — new `docs/student-portal.md`; `PROJECT_STATUS.md`,
  `docs/roadmap.md` updated.

## Delivered in Milestone 16

A secure, read-only, child-scoped window for a signed-in parent onto their
own children's published data. Built on the existing `TenantContext` +
`BelongsToSchool` + `Permission` + `module:parent-portal` seams, the M2
`User` account, M10's `Guardian`/`GuardianStudent` relationship, and
M9/M12/M13/M14/M15's own data — no second authentication system, no second
tenancy mechanism, no second report-card generator. Full detail in
`docs/parent-portal.md`.

- **`Guardian.user_id`** (new, additive column via migration
  `2026_09_24_100000_add_user_id_to_guardians_table.php` — M10's own
  migration untouched) — nullable, unique per school, **not**
  mass-assignable, set only through `GuardianController::updateUser()`
  (mirrors `Teacher.user_id` from M11 exactly). A `User` is not automatically
  a `Guardian` — the two stay separate concepts.
- **`App\Support\Portal\ParentPortalAuthorizer`** — the one seam every portal
  controller uses: `guardianFor()`, `studentsFor()`, `authorizedStudent()`.
  Tenant-scoped for free (`Guardian` is `BelongsToSchool`); a student id is
  never trusted from the URL until proven to be one of that guardian's own
  linked children.
- **`App\Services\Results\ReportCardRenderer`** — extracted from M15's own
  `Results\ReportCardController` (identical behaviour; the M15 test suite
  passed unchanged) so the school/staff report card and the Parent Portal's
  report card share **one** renderer, never a duplicated generator.
- **`App\Http\Controllers\Portal\*`** (8 thin controllers) +
  `resources/views/parent/*` — dashboard ("My Children"), child profile, a
  child-scoped tab nav + "switch child" dropdown
  (`resources/views/parent/_child-nav.blade.php`), results, report cards
  (reusing the renderer above), attendance (reusing M15's
  `AttendanceSummarizer`), assignments (M14's `AssignmentSubmission`,
  paginated), timetable (M12's published timetable for the child's current
  class), and a read-only guardian profile.
- **Result & report-card visibility** — `App\Enums\ResultRunStatus::
  visibleToParents()` (new, small, additive method): only `published` /
  `locked` runs are ever shown to a parent — M15 has no dedicated
  parent-visibility flag, documented as the safest interpretation.
  `App\Enums\AssignmentStatus::visibleToParents()` similarly gates
  assignments to `published`/`closed`, never `draft`.
- **Bug fixed during this milestone's own reuse of the M15 report card
  view**: its "← Result run" back-link pointed at a staff-only page — a
  parent clicking it would have hit a 403. Now audience-aware
  (`@can('result.view')` vs. a Parent Portal link).
- **`Module::ParentPortal->isAvailable()`** flipped to `true` (on by default,
  declared since M7); depends on **`Module::Guardians` only**. A Parent-role
  member is redirected from `/dashboard` straight to `/parent`
  (`DashboardController`); the main nav renders a portal-specific link set
  for them instead of the (near-empty, for a parent) admin nav.
- **No new permission** — reuses `Permission::PortalParent` (`portal.parent`),
  declared since M4, enforced for the first time here. `Role::SchoolAdmin`
  also holds it (full bundle) but is never itself a `Guardian`, so it sees
  the same safe empty state as an unlinked parent.
- **Seeder** — a Parent account (`parent@example.com`) linked to the existing
  M10 "siblings sharing a guardian" record, extended to three children across
  three classes (one with full M12/M13/M14/M15 data); `dual@example.com`
  (Parent at Beta, Teacher at Alpha) demonstrates the "no linked guardian"
  empty state and the two-different-families-two-different-schools case.
- **Docs** — new `docs/parent-portal.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `scalability.md`,
  `tenancy.md`, `roadmap.md`, `PROJECT_STATUS.md`, `ui-ux-guidelines.md`,
  `CLAUDE.md`, `AGENTS.md`.

## Delivered in Milestone 15

Turns M14's locked assessment scores into configurable student results and
printable report cards: school-defined grading + weighting schemes, a
compile → review → approve → publish → lock lifecycle, class-position
ranking, a controlled per-subject adjustment workflow, and a report card whose
visible fields the school configures. Built on the existing `TenantContext` +
`BelongsToSchool` + `Permission` + `module:results` seams, M8's
`AcademicSession`/`AcademicPeriod` and M14's `Assessment`/`AssessmentScore` —
no new mechanism, no new packages, no Redis/queues, no PDF library. Full
detail in `docs/results-report-cards.md`.

- **Enums** — `App\Enums\ResultRunStatus` (`draft`/`compiled`/`reviewed`/
  `approved`/`published`/`locked`), `App\Enums\ResultAdjustmentStatus`
  (`pending`/`applied`/`rejected`), `App\Enums\AssessmentPurpose`
  (`academic`/`practice`/`entry_placement` — added to M14's `Assessment` via
  an additive migration), `App\Enums\ScoreSource`
  (`manual`/`online_cbt`/`imported` — added to M14's `AssessmentScore`).
- **`App\Models\GradingScheme` + `GradingSchemeGrade`** — school-configured
  percentage bands; `gradeFor(float): ?GradingSchemeGrade` is the one place a
  percentage becomes a grade. Overlap/ordering validated server-side.
- **`App\Models\ResultWeightingScheme` + `ResultWeightingSchemeItem`** — ties
  an M14 `AssessmentCategory` to a weight; must sum to exactly 100%.
- **`App\Models\ResultRun`** — one class, one term (`unique` per
  session+period+level+arm); `status` / lifecycle timestamps **not**
  mass-assignable — `review()`/`approve()`/`publish()`/`lock()` only.
  `reportCardSnapshot(): HasOne` for the frozen per-run config.
- **`App\Models\StudentResult` / `StudentSubjectResult` /
  `StudentSubjectResultComponent`** — compiled results, **snapshotted** at
  compile time (percentage, grade, raw score, weight) so they never change
  when the grading/weighting scheme changes later.
- **`App\Models\ResultAdjustment`** — a controlled propose → apply/reject
  correction workflow; a proposal alone changes nothing; `apply()` re-derives
  the grade via `GradingScheme::gradeFor()` and triggers a full ranking
  recompute.
- **`App\Models\ReportCardConfiguration`** — 24 independent `show_*`
  booleans (typed/relational, not a JSON blob); school-wide/session/term
  scope with documented precedence; a second, `result_run_id`-tagged row is
  the immutable snapshot a locked run's report card renders from.
- **Migrations** `2026_09_23_100000`–`100110` — 2 additive columns onto
  M14's tables (`assessments.purpose`, `assessment_scores.source`) plus 10
  new tables, all `BelongsToSchool`, `school_id`-leading indexes.
- **`App\Services\Results\ResultCompiler`** — all-or-nothing compilation
  (computes fully in memory, collects every missing-score issue, writes
  nothing if any exist); bulk `insert`s (chunked 500); ranking + overall
  totals refreshed via a single `UPDATE ... CASE id WHEN ... END` per chunk,
  never one query per student.
- **`App\Support\Results\{RankingCalculator,AttendanceSummarizer,
  ResultAuthorizer}`** — pure-PHP competition ranking (portable across
  MySQL/SQLite); a 2-query attendance rollup from M13 data (no duplication);
  class-scoped teacher comment authorization (M13-pattern, no subject
  dimension).
- **`App\Http\Controllers\Results\*`** (8 controllers) +
  `App\Http\Requests\Results\*` (10 requests) + `resources/views/results/*`
  — grading/weighting scheme management, result run lifecycle with itemized
  blocking-issue display, per-student adjustment UI, report-card
  configuration (scope switcher, signature upload), and the report card
  itself (same view for preview and final output).
- **`Module::Results->isAvailable()`** flipped to `true` (on by default);
  depends on **`Module::Assessments` only** — not Timetable/Attendance/CBT.
  "Results" is a top-level nav item.
- **Permissions** — new `result.manage` / `result.adjust`, reusing
  `result.view`/`.enter`/`.publish` already declared in M4 (30 → 32).
  `result.manage` + `.adjust` added to **Principal** (joining the existing
  `.view`/`.enter`/`.publish`); `result.view` added to **Staff**. School
  Admin auto.
- **Seeder** — Alpha gets a Standard Grading scheme, a Standard Weighting
  scheme, extra locked Mathematics/English assessments, and a fully
  compiled → reviewed → approved → published → **locked** result run with a
  snapshotted report-card configuration.
- **Docs** — new `docs/results-report-cards.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `scalability.md`,
  `tenancy.md`, `roadmap.md`, `PROJECT_STATUS.md`, `ui-ux-guidelines.md`,
  `CLAUDE.md`, `AGENTS.md`.

## Delivered in Milestone 14

A configurable, tenant-scoped assessment and assignment foundation — the source
data for M15 Results & Report Cards. Built on the existing `TenantContext` +
`BelongsToSchool` + `Permission` + `module:assessments` seams, the M9
`Enrollment` history and the M11 `TeacherAssignment` — no new mechanism, no new
packages, no Redis/queues.

- **Enums** — `App\Enums\AssessmentStatus` (`draft` / `published` / `locked`),
  `App\Enums\AssignmentStatus` (`draft` / `published` / `closed`),
  `App\Enums\AssignmentSubmissionStatus` (`pending` / `submitted` / `late` /
  `exempt`).
- **`App\Models\AssessmentCategory`** — school-configured category (name / code /
  position / active), unique per school. Seeded examples (Classwork / Homework /
  Test / Examination) are fully editable. Managed at `/assessments/categories`
  (`assessment.manage`).
- **`App\Models\Assessment`** — school-owned; academic context (session +
  period + level + arm + subject) **fixed at creation**. `status` /
  `published_at` / `locked_*` / `created_by` **not** mass-assignable. Optional
  `assignment_id` link (no calculation). `eligibleStudents()` (shared
  `HasClassRoster` trait) + `summary()`. Lifecycle `publish()` / `unpublish()` /
  `lock(User)` / `unlock()`.
- **`App\Models\AssessmentScore`** — school-owned **+** assessment-scoped.
  Nullable `score` (`decimal(6,2)`, null = not entered), `comment`,
  `recorded_at` / `recorded_by`. Never deleted. **No** grade / percentage /
  rank stored.
- **`App\Models\Assignment`** — school-owned; same context rules + `due_on >=
  assigned_on`, both in the session. `teacher_id` owner (from the creator's
  `Teacher` record), `created_by`, optional `max_score`. Carries **no scores**.
  Lifecycle `publish()` / `unpublish()` / `close()` / `reopen()`.
- **`App\Models\AssignmentSubmission`** — completion tracking only; `status`
  defaults to `pending`, `submitted_on`, `remark`. No file upload / portal.
- **Migrations** `2026_09_22_100000`–`100040` (categories, assignments,
  assessments, assessment_scores, assignment_submissions) — all
  `BelongsToSchool`, `school_id`-leading indexes, `unique(assessment_id,
  student_id)` / `unique(assignment_id, student_id)`, **no** global uniqueness on
  assessments (multiple of a category on different dates are legitimate).
- **`App\Support\Assessment\AssessmentAuthorizer`** — `assessment.manage` → any
  class + subject; `assessment.record` only → a `(level, subject)` the teacher
  holds an **active** M11 assignment for. Tenant scoped; degrades safely with
  the Staff module off.
- **`App\Http\Controllers\Assessment\{AssessmentCategory,Assessment,AssessmentScore,Assignment,AssignmentSubmission}Controller`**
  + `App\Http\Requests\Assessment\*` (11 requests) +
  `resources/views/{assessments,assignments}/*` (13 views) — list (filters +
  pagination), Alpine-cascade create forms, draft-only edit forms, detail with
  lifecycle controls, mobile-first bulk score / completion sheets, inline
  category CRUD.
- **Eligibility & workflow** — the roster is snapshotted at creation (one bulk
  `insert`, one row per then-eligible student). Scores start `null`; a draft's
  roster can be re-synced with current enrolment; publishing freezes it. Locking
  freezes scores; only `assessment.manage` unlocks.
- **`Module::Assessments->isAvailable()`** flipped to `true` (on by default);
  depends on **`Module::Academics` + `Module::Students`** — not Timetable,
  Attendance, Results or CBT. "Assessments" is a top-level nav item.
- **Permissions** — new `assessment.view` / `assessment.record` /
  `assessment.manage` (27 → 30). `assessment.manage` on Principal; `.view` +
  `.record` on Teacher; `.view` on Staff. School Admin auto.
- **Seeder** — Alpha gets 4 categories, 3 assessments (locked / published /
  draft, mixed scores), 2 assignments (published w/ mixed completion, draft).
- **Docs** — new `docs/assessment-management.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `scalability.md`,
  `module-activation.md`, `roadmap.md`, `PROJECT_STATUS.md`, `CLAUDE.md`,
  `AGENTS.md`.

## Delivered in Milestone 13

A tenant-scoped daily student-attendance foundation with a draft → submitted
(locked) lifecycle, **independent of the Timetable module**. Built on the
existing `TenantContext` + `BelongsToSchool` + `Permission` + `module:attendance`
seams, the M9 `Enrollment` history and the M11 `TeacherAssignment` — no new
mechanism, no new packages, no Redis/queues.

- **Enums** — `App\Enums\AttendanceStatus` (`present` / `absent` / `late` /
  `excused` — one controlled column, no `is_present` booleans, no minutes-late
  field) and `App\Enums\AttendanceRegisterStatus` (`draft` / `submitted`).
- **`App\Models\AttendanceRegister`** — school-owned. One class's attendance for
  one day: `(academic_session, optional academic_period, academic_level,
  level_arm, attendance_date)`. `status` / `submitted_*` **not** mass-assignable
  — `submit()` / `reopen()` only. `eligibleStudents()` is the enrollment-based
  eligibility rule; `summary()` gives register-level totals from loaded records.
- **`App\Models\AttendanceRecord`** — school-owned **and** register-scoped. One
  student's mark: nullable `status` (null = unmarked), optional `note`,
  `recorded_at` / `recorded_by` stamped when a mark changes. Never deleted for
  historical reasons.
- **Migrations** `2026_09_21_100000` (`attendance_registers`), `…100010`
  (`attendance_records`) — both `BelongsToSchool`, `school_id`-leading indexes,
  `unique(school_id, level_arm_id, attendance_date)` and
  `unique(attendance_register_id, student_id)`.
- **`App\Support\Attendance\AttendanceAuthorizer`** — the class-scoping rule M4
  permissions can't express: `attendance.manage` → any class; `attendance.record`
  only → a class the teacher holds an **active** M11 assignment for. Tenant
  scoped; degrades safely with the Staff module off.
- **`App\Http\Controllers\Attendance\AttendanceRegisterController`** +
  `App\Http\Requests\Attendance\*` (`AttendanceModuleRequest`, `RegisterRequest`,
  `RecordAttendanceRequest`, `SubmitRegisterRequest`) +
  `resources/views/attendance/*` — list (date/session/level/arm/status filter +
  pagination), create (session→period / level→arm Alpine cascade), the
  taking screen (per-student status buttons, bulk "mark all present" / "clear
  all", per-student note, save draft / save & submit, sticky footer, mobile
  first), and the register detail (summary counts, submitted-by indicator,
  read-only roster when locked, reopen control for managers).
- **Eligibility & workflow** — the roster is **snapshotted** at creation (one
  bulk `insert`, one record per then-eligible student). Marks start **unmarked**
  (the safe default — an unmarked student is never counted present); a register
  cannot be submitted while any record is unmarked. A submitted register is
  locked; only an `attendance.manage` holder can `reopen()` it for correction.
- **`Module::Attendance->isAvailable()`** flipped to `true` (on by default);
  **`Module::Attendance` depends on `Module::Academics` + `Module::Students`**
  — **not** `Timetable`. "Attendance" is a top-level nav item.
- **Permissions** — new `attendance.manage` (added to **Principal**;
  School Admin auto). `attendance.view` / `attendance.record` already existed
  from M7 and were left on Teacher / Staff as declared.
- **Seeder** — Alpha gets 10 extra enrolled students, one **submitted** register
  (Primary 1 Gold, mixed marks) and one **draft** register (Primary 2 Gold,
  unmarked).
- **Docs** — new `docs/attendance-management.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `scalability.md`,
  `tenancy.md`, `module-activation.md`, `roadmap.md`, `ui-ux-guidelines.md`,
  `CLAUDE.md`, `AGENTS.md`.

## Delivered in Milestone 12

A tenant-scoped, configurable weekly timetable with server-side conflict
detection and a draft → published lifecycle. Scheduling foundation only — no
attendance, marks, notifications, rooms/facilities module, workload/payroll,
auto-optimisation or student/parent views. Built on the existing `TenantContext`
+ `BelongsToSchool` + `Permission` + `module:timetable` seams and the M11
`TeacherAssignment` — no new mechanism, no new packages, no Redis/queues.

- **Enum** — `App\Enums\TimetableStatus` (`draft` / `published`). `App\Enums\Weekday`
  (M6) reused and gained `all()` (Monday-first) + `short()` helpers — no
  Monday–Friday assumption anywhere.
- **`App\Models\Timetable`** — school-owned. Scoped to one `AcademicSession`
  (fixed at creation) and optionally one `AcademicPeriod`. `status` /
  `published_at` **not** mass-assignable — `publish()` runs behind a conflict
  guard. Multiple timetables per session allowed (history preserved). A
  published timetable can't be deleted (return to draft first).
- **`App\Models\TimetableEntry`** — school-owned **and** timetable-scoped. A
  lesson = level + **arm (required — scheduled per class)** + subject + teacher +
  `weekday` + `start_time`/`end_time` (`HH:MM` strings, **half-open** `[start,
  end)`) + optional free-text `room`. Session/period are **not** on the entry —
  inherited from the parent, so lessons structurally can't cross sessions.
- **Migrations** `2026_09_20_100000` (`timetables`), `…100010`
  (`timetable_entries`) — both `BelongsToSchool`, `school_id`-leading indexes,
  one narrow index per booked resource (teacher / class / room) for overlap
  checks.
- **`App\Support\Timetable\TimetableConflictScanner`** — one indexed self-join
  finding every clashing lesson pair in a timetable; backs the publish guard and
  the warning banner. Per-lesson checks are DB existence queries in the Form
  Request — entries are never loaded into PHP to detect clashes.
- **`App\Http\Controllers\Timetable\{Timetable,TimetableEntry}Controller`** +
  `App\Http\Requests\Timetable\*` (`TimetableRequest`,
  `UpdateTimetableStatusRequest`, `TimetableEntryRequest`) +
  `resources/views/timetables/*` — list (session/status filter + pagination),
  create/edit, the timetable page (desktop **day × time grid**, mobile **stacked
  day list**, filters by class/arm/teacher/weekday, publish controls, conflict
  banner), an Alpine-cascade lesson form, and a **teacher timetable** view.
- **Scheduling rules** (server-side, `TimetableEntryRequest`): end after start;
  arm↔level; subject offered by the level (`level_subject`); an **active M11
  `TeacherAssignment`** must back `(teacher, subject, level)` for the session;
  no teacher / class / room double-booking (half-open overlap, per timetable).
- **Routes** — `/timetables/*` behind `['tenant', 'module:timetable']`, gated
  `timetable.view` (reads) / `timetable.manage` (writes).
- **`Module::Timetable->isAvailable()`** flipped to `true` (still **off by
  default** — a specialised opt-in); **`Module::Timetable` now depends on
  `Module::Academics` and `Module::Staff`**. "Timetable" is a top-level nav item.
- **Permissions** — new `timetable.view` / `timetable.manage`; `timetable.manage`
  + `timetable.view` added to **Principal**, `timetable.view` to **Teacher** and
  **Staff** (the roles with `academics.view`). Bursar has no academic access, so
  no timetable access.
- **Seeder** — Alpha (timetable module already on) gets one published
  "First Term 2025/26" timetable with 8 non-conflicting lessons across
  Mon–Fri, 4 classes, 4 teachers, 4 subjects.
- **Docs** — new `docs/timetable-management.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `scalability.md`,
  `tenancy.md`, `module-activation.md`, `roadmap.md`, `ui-ux-guidelines.md`,
  `CLAUDE.md`, `AGENTS.md`.

## Authorization & tenant controls (M16)

- **Two gates on every `/parent/*` route:** `module:parent-portal` (404 when
  off) **and** `->can('portal.parent')`. No new permission — `portal.parent`
  was declared since M4.
- Every `{student}` route param is resolved via `App\Support\Portal\
  ParentPortalAuthorizer::authorizedStudent()`, never route-model-bound and
  never trusted from the URL — it 404s unless the signed-in parent's own
  `Guardian` record (in the active school) is linked to that exact student.
  `{run}` is additionally re-checked against `ResultRunStatus::
  visibleToParents()` before any result or report card is returned.
- `Guardian.user_id` lookups are tenant-scoped for free (`Guardian` is
  `BelongsToSchool`) — no second tenancy mechanism. Explicit HTTP tests prove
  a parent cannot open an unrelated student in the same school, cannot reach
  a student from another school by id, cannot use a second school membership
  to reach that school's other families' children, and every child-scoped
  page (results/report-cards/attendance/assignments/timetable) 404s for an
  unauthorized child regardless of whether real data exists for them.
- Admin-side linking (`GuardianController::updateUser()`, `guardian.manage`)
  is tenant-scoped exactly like M11's `TeacherController::updateUser()`: the
  account must be a member of the active school, `{guardian}` is
  tenant-scoped `findOrFail`, and the same account may be linked to a
  *different* guardian in a *different* school (`unique` per school).

## Authorization & tenant controls (M15)

- **Two gates on every `/results/*` route:** `module:results` (404 when off)
  **and** `->can('result.view'|'.enter'|'.manage'|'.publish'|'.adjust')`.
  Module gate ≠ permission (a Bursar with the module on still gets 403 —
  tested).
- `ResultRun` / `StudentResult` / `StudentSubjectResult` /
  `StudentSubjectResultComponent` / `ResultAdjustment` /
  `ReportCardConfiguration` / `GradingScheme(Grade)` /
  `ResultWeightingScheme(Item)` are all `BelongsToSchool`; child rows also
  carry their parent FK. `school_id` never from input, immutable. Bulk
  `insert`s in `ResultCompiler` set `school_id` from `TenantContext`
  explicitly (raw `insert` bypasses the `creating` hook).
- Every id in a run/adjustment/config payload uses
  `Rule::exists(...)->where('school_id', <tenant>)` → generic "invalid", no
  leak. Route ids resolved by tenant-scoped `findOrFail`. A
  `ReportCardConfiguration` write always goes through
  `exactScopeRow()`/`whereNull('result_run_id')` — never a raw
  `updateOrCreate` on scope columns alone, which could otherwise match and
  silently corrupt a locked run's frozen snapshot (the same (session=null,
  period=null) scope columns are shared by the school-wide default and every
  per-run snapshot; only `result_run_id` tells them apart). Explicit HTTP +
  model cross-school isolation tests (view / compile / review / approve /
  publish / lock / adjust / comment / configure-report-card / view-report-card
  / view-signature — all 404 or 403 for School B).

## Authorization & tenant controls (M14)

- **Two gates on every `/assessments/*` route:** `module:assessments` (404 when
  off) **and** `->can('assessment.view'|'.record'|'.manage')`; write Form
  Requests re-check via `AssessmentModuleRequest` + `AssessmentAuthorizer`.
  Module gate ≠ permission (a Bursar with the module on still can't see
  assessments — tested).
- `Assessment` / `AssessmentScore` / `Assignment` / `AssignmentSubmission` /
  `AssessmentCategory` are `BelongsToSchool`; the child rows also carry their
  parent FK. `school_id` never from input, immutable (`TenantMismatchException`);
  a `school_id` in a payload is ignored (tested). Roster bulk `insert`s set
  `school_id` from `TenantContext` explicitly.
- `{assessment}` / `{assignment}` / `{category}` resolved by tenant-scoped
  `findOrFail`; the write Form Requests `abort(404)` on a cross-school route
  parent before validation. Every session / period / level / arm / subject /
  category / assignment id in a payload uses
  `Rule::exists(...)->where('school_id', <tenant>)` → generic "invalid", no leak.
  A score / submission for a student not on the snapshotted roster is rejected —
  this blocks cross-school and wrong-class student ids. `AssessmentAuthorizer`
  additionally scopes a teacher to their assigned `(level, subject)`. Explicit
  HTTP + model cross-school isolation tests (view / edit / score / publish /
  lock / unlock / delete / create-with-foreign-context / post-foreign-student).

## Database

M16 adds one column: `guardians.user_id` (nullable, additive — M10's own
migration is untouched).

M15 adds two columns to M14's tables (`assessments.purpose`,
`assessment_scores.source`, via additive migrations — the original M14
migrations are untouched) and 10 new tables: `grading_schemes`,
`grading_scheme_grades`, `result_weighting_schemes`,
`result_weighting_scheme_items`, `result_runs`, `student_results`,
`student_subject_results`, `student_subject_result_components`,
`result_adjustments`, `report_card_configurations`.

M14 adds `assessment_categories`, `assignments`, `assessments`,
`assessment_scores` and `assignment_submissions`. No other schema changes.

## Routes (application, additions in M16)

Tenant-scoped + `module:parent-portal`, gated `portal.parent`. 10 routes
under `/parent/` (`parent.dashboard`, `.children.show`, `.results.*`,
`.report-cards.*`, `.attendance.index`, `.assignments.index`,
`.timetable.index`, `.profile.edit`) plus one addition to the existing
Guardian routes: `PATCH /guardians/{guardian}/user` (`guardians.user`,
`guardian.manage`).

## Routes (application, additions in M15)

Tenant-scoped + `module:results`, gated `result.view` / `.enter` / `.manage` /
`.publish` / `.adjust`. ~30 routes under `/results/` (`results.grading-
schemes.*`, `.grading-schemes.grades.*`, `.weighting-schemes.*`,
`.weighting-schemes.items.*`, `.report-card-configuration.*` +
`.principal-signature.*` + `.class-teacher-signature.*`, `.runs.*` —
index/create/store/show/destroy/compile/review/approve/publish/lock/
students.comment/students.report-card, `.adjustments.*` —
store/apply/reject).

## Routes (application, additions in M14)

Tenant-scoped + `module:assessments`, gated `assessment.view` /
`assessment.record` / `assessment.manage`. 30 routes under `/assessments/`
(`assessments.*` — index/create/store/show/edit/update/destroy/scores.edit/
scores.update/scores.sync/publish/unpublish/lock/unlock; `assessments.categories.*`;
`assessments.assignments.*` — index/create/store/show/edit/update/destroy/
submissions.edit/submissions.update/publish/unpublish/close/reopen).

## Tests

761 passing (was 699 at M15; +62 in M16, M1–M15 intact). New
`tests/Feature/Portal/*` (+ `ParentPortalTestCase` base) —
`ParentAuthorizationTest`, `ParentChildAccessTest`, `ParentResultTest`,
`ParentReportCardTest`, `ParentAttendanceTest`, `ParentAssignmentTest`,
`ParentTimetableTest`, `ParentProfileTest`, `ParentGuardianProtectionTest`,
`ParentPortalStructureTest`: module off → 404, denied roles → 403,
unauthenticated → redirect to login, no-linked-guardian /
guardian-with-no-students safe empty states, School Admin sees the empty
state rather than a leak, a Guardian record without the Parent role still
denies access; own child accessible, unrelated student in the same school
404s, student-id tampering blocked, cross-school student blocked, switching
active school never exposes another family's child, multiple children all
accessible, the child switcher never mixes up page content (only the
switcher's own list of names), non-numeric/nonexistent id 404s; results —
approved-but-unpublished invisible, published/locked visible with the
correct weighted breakdown, wrong child / wrong school inaccessible, module
off degrades gracefully; report cards — unpublished invisible, published
renders via the shared `ReportCardRenderer` (and its audience-aware back
link), locked stays reachable, wrong child / wrong school inaccessible;
attendance — only the child's own data, a sibling's attendance never leaks
into another child's page, no-data-yet and module-off empty states;
assignments — published/closed visible, draft invisible, another student's
submission never leaks, module-off empty state; timetable — published
visible, draft invisible, no-current-enrollment and module-off empty states;
guardian protection — a parent cannot link/unlink a student, create a link by
posting directly, change a relationship or make themselves primary, or link
their account to a different guardian record; structure — dashboard and
child-page query counts stay flat as unrelated school size and the parent's
own child count grow, resolving one authorized student never scans the whole
table. New `tests/Feature/Guardian/GuardianUserLinkTest.php` (admin-side
linking: link/unlink, must be a school member, uniqueness per school, same
account linkable in a different school, view-only roles forbidden, tenant
isolation). `Unit/Enums/ModuleTest` updated (available list).
`Tests\Feature\Onboarding\OnboardingChecklistTest` updated (a Parent-role
member is now redirected to the Parent Portal instead of seeing `/dashboard`).

699 passing (was 615 at M14; +84 in M15, M1–M14 intact). New
`tests/Feature/Results/*` (+ `ResultsTestCase` base) — `GradingSchemeTest`,
`WeightingSchemeTest`, `ResultRunTest`, `ResultCompilationTest`,
`ResultLifecycleTest`, `ResultAdjustmentTest`, `ResultAuthorizationTest`,
`ReportCardTest`, `ResultStructureTest`: grading scheme create/edit,
overlap/ordering/duplicate-code rejection, `gradeFor()` calculation; weighting
scheme create/edit, duplicate-category rejection, cross-school category
rejection; run creation, cross-context rejection, incomplete-weighting
rejection, one-run-per-class-per-term uniqueness, list filters, tenant
isolation; compilation — only-locked-included, draft/unlocked doesn't
count/block, missing-score blocks with itemized issues, no-students/no-subjects
block, weighted percentage + grade math, competition ranking with ties,
position scoped to the run not the school, ranking-disabled means no position,
recompiling replaces stale rows, no cross-school leakage; lifecycle — full
draft→locked path, out-of-order transitions rejected, role restrictions,
locked run rejects recompilation, run-with-results can't be deleted;
adjustment — proposal inert until applied, applying updates value + re-derives
grade + recomputes whole-run ranking, rejection inert, decided can't
re-decide, unavailable before approval, value-must-differ, teacher forbidden,
tenant isolation; authorization — all 7 roles + role-less, module off → 404,
module on without permission → 403, class-scoped teacher comment (own class
only, principal comment blocked); report card — fields-shown-per-config, scope
precedence, branding/comments/position, unavailable before compilation, tenant
isolation, publish snapshots the config, a later config change never alters a
**locked** run's report card, an unlocked run's preview reflects the *live*
config; structure — column allow-lists (no premature derived fields), raw
score/max populated from the locked assessment, compiling/index/show/report-
card query counts stay flat as class size and subject count grow, a locked
run's grade/weights/category-name/results survive a later grading-scheme
edit / weighting-scheme edit / category rename / student status change. New
`tests/Unit/Enums/ResultsEnumsTest`. `Unit/Enums/ModuleTest` updated
(available list).

615 passing (was 526 at M13; +89 in M14, M1–M13 intact). New
`tests/Feature/Assessment/*` (+ `AssessmentTestCase` base) — `AssessmentTest`,
`AssessmentEligibilityTest`, `AssessmentScoreTest`, `AssessmentLifecycleTest`,
`AssessmentAuthorizationTest`, `AssessmentTeacherScopeTest`,
`AssessmentCategoryTest`, `AssessmentStructureTest`, `AssignmentTest`:
assessment create / required-field / max-score-precision / date-vs-session-term /
period-vs-session / arm-vs-level / subject-offered-at-level / inactive-category /
empty-class / list filters + pagination / context-immutable-after-creation;
eligibility — exact-class enrolment, not-yet-enrolled excluded, left-before
excluded but leaving-after included, cross-school student never scored, draft
roster re-sync, published roster frozen; scores — zero / maximum / decimal /
negative-rejected / over-max-rejected / non-numeric-rejected / >2dp-rejected /
blank-clears / comment length / correction before lock / not-on-roster rejected /
DB duplicate prevention / full class one request / locked = 403; lifecycle —
draft→published→locked, unpublish, structure frozen once published, scores while
published, only manager unlocks, locked/scored can't delete, max-score frozen
once any score is recorded (both directions);
authorization — all 7 roles + role-less, module off → 404, module on without
permission → 403, only managers touch categories; teacher scope — assigned
class+subject only, wrong class / wrong subject / no Teacher record / other-school
assignment all rejected; categories — CRUD, upper-cased code, duplicate name/code
per school, same name in another school, tenant isolation, code format;
structure — column allow-lists (no `final_grade` / `percentage` / `position` /
`gpa`), no result-module relations, historical score survives withdrawal /
class-change, delete-student cascades scores keeps assessment, locked stays
readable, N+1 guards on the score sheet / lists / bulk save; assignments — create
with completion roster + teacher ownership, due-date validation, lifecycle
draft→published→closed→reopen, structure frozen once published, completion
tracking + validation, closed rejects edits, recorded-submission blocks delete,
teacher scope, tenant isolation, ownership immutable, N+1 guard, no-score column
check, DB duplicate prevention, assessment↔assignment link (same class only). New
`tests/Unit/Enums/AssessmentEnumsTest`. `Unit/Enums/ModuleTest` updated
(available list).

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Next milestone: pick a further domain module (Reporting) — see
  `docs/roadmap.md`.
- **Entry / Placement Assessment follow-ups** — placement recommendation/
  decision workflow of any kind (deliberately not built — this stays a
  recording-only feature), an admissions CRM, application payment,
  interview scheduling, document management, psychometric testing,
  proctoring/adaptive testing, notification (M18) hooks, bulk import, a
  batch/grouping entity for a multi-subject candidate's rows.
- **Question Bank follow-ups** — tagging beyond the single `topic` field,
  question pools/randomised selection, versioning, bulk import/export, a
  shared cross-school library, a per-teacher "my questions" filtered view.
- **CBT follow-ups** — essay/manual-marking questions, a detailed
  per-question answer-review screen, multiple attempts per exam, any
  proctoring, M15 `ResultRun` integration (the extension point — M14's
  `ScoreSource::OnlineCbt` — is documented and ready), notification (M18)
  hooks, bulk exam templates, staff analytics beyond a plain attempts
  list.
- **Learning Materials follow-ups** — Parent Portal visibility, editing an
  uploaded material (delete + re-upload covers a correction today),
  multiple files per material, video upload/streaming/transcoding/
  thumbnails/player (declared but intentionally not built), download
  analytics, notification (M18) hooks, bulk upload.
- **Promotion follow-ups** — automatic pass/fail promotion rules of any
  kind (deliberately not built — promotion stays an authorised
  administrative decision), a promotion approval step distinct from
  running the batch, bulk import, bulk-undo of a completed batch,
  notification (M18) hooks on promotion/graduation.
- **Paystack follow-ups** — a dedicated staff-facing list of online-payment
  attempts (successes already show in the ordinary statement; failed/
  abandoned ones are only visible in `paystack_transactions` directly),
  refunds through Paystack's own refund API (M19's manual `void()` covers
  "this shouldn't count" today), recurring/subscription billing, an
  inline-JS/Popup checkout as an alternative to the redirect flow, any
  payment channel other than Paystack.
- **Fees follow-ups** — a full discount/waiver audit-log table, bulk
  fee-structure assignment/invoicing runs, fee reminders (the M18
  notification foundation could carry these), refunds beyond voiding an
  unallocated/newly-allocated payment, receipts/PDF export beyond the
  browser-printable statement, multi-currency.
- **Communication follow-ups** — WhatsApp/SMS/email provider integration
  behind `App\Enums\NotificationChannel`, two-way portal messaging (guardians/
  students can view announcements & their own notifications but do not reply
  into a Communication Hub thread — it stays staff-facing), level/arm-scoped
  ("selected school groups") announcement targeting, wiring assignment/
  result/attendance events into `NotificationDispatcher`, a full audit trail
  of communication access, push notifications, message attachments.
- **Parent Portal follow-ups** — a full platform audit trail of parent
  access events, parent self-service editing of guardian contact details,
  the inherited M15 report-card branding-logo gap (never renders for a
  Teacher / Staff / Parent viewer — see `docs/parent-portal.md` §5).
- **Results follow-ups** — PDF export (browser print covers it for now),
  per-level/per-arm report-card configuration overrides, bulk "unlock" of an
  approved/published/locked run, signature-image snapshotting per run,
  student photo capture (M9), CBT/Question Bank (the `AssessmentPurpose` /
  `ScoreSource` enums are ready for it), advanced result analytics, automatic
  report-card comments, a full drag-and-drop report-card designer.
- **Assessment follow-ups** — assignment file attachments + online
  submission, automated grading, a Student Portal submission view (the
  Parent Portal, M16, only ever reads an assignment's status).
- **Attendance follow-ups** — attendance rate / percentage analytics, term &
  monthly reports, per-lesson (timetable-driven) registers, a Student Portal
  attendance view (the parent one shipped in M16), absence notifications, an
  attendance-reason taxonomy, half-day records.
- **Timetable follow-ups** — a Student Portal timetable view (the parent one
  shipped in M16), publish notifications, a rooms/facilities module,
  timetable templates / term cloning, named period grids, teacher workload
  limits.
- **Teacher portal** — sign-in + invitations (Parent Portal delivered in M16).
- Bulk import; documents / photo.
- Audit trail + data-erasure handling for student / guardian / teacher / timetable / attendance / assessment records.
- Apply the stored `timezone` / `locale` / `date_format` at render time.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
