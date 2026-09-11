# Roadmap

High-level milestone order. Each milestone ships working, tested software and
updates `PROJECT_STATUS.md`. **Do not start a milestone before the previous one
is accepted.**

## ✅ Milestone 1 — Platform Foundation (complete, 2026-09-10)

Project inspection; conventions for controllers / models / Form Requests /
policies / services / views / components / tests; base responsive Blade shell;
small reusable UI kit; `TenantContext` seam; `/health` endpoint; testing
foundation; config & `.gitignore` safety review; documentation structure.

No domain modules, no website features, no artificial school limit.

## ✅ Milestone 2 — Authentication & User Foundation (complete, 2026-09-10)

Registration, login, logout, password reset, email verification, password
confirmation, account status (`active`/`suspended`/`disabled`) enforced
server-side, account/profile settings (name, email, password, delete),
authenticated shell + placeholder dashboard, polished auth UI on the Milestone 1
component kit, security baseline (throttling, session fixation, generic errors,
password policy), authorization *direction* documented. Native Laravel — no auth
package. Full detail in `docs/authentication.md`.

Deferred to their own milestone: **role & permission model** (evaluate Spatie
Permission then), 2FA, auth audit logging.

## ✅ Milestone 3 — Multi-School / Strict Tenant Isolation (complete, 2026-09-11)

`schools` table + model, `school_user` membership, `users.is_platform_admin`,
`TenantContext` (resolve/set/bypass), `EnforceTenant` middleware, school picker /
switcher, `BelongsToSchool` trait + `SchoolScope` global scope + `creating` /
`updating` hooks (unspoofable, immutable `school_id`), `SchoolPolicy`,
`MissingTenantContext` / `TenantMismatch` exceptions, cross-school isolation test
suite. Full detail in `docs/tenancy.md`.

Deferred: `school_user` roles, membership management UI, queue-job tenant
propagation, subdomain routing.

## ✅ Milestone 4 — Roles & Permissions (complete, 2026-09-12)

`App\Enums\Permission` (24 code-defined permissions) + `App\Enums\Role` (7
per-school roles as static permission bundles + tiers), `school_user.role`
column + `App\Models\SchoolUser` pivot, `AuthServiceProvider` registering every
permission as a tenant-composed Gate ability (no `Gate::before`),
`MembershipPolicy` (self / escalation guards), and the Members-management
feature (`/members`). Spatie laravel-permission evaluated and not adopted. Full
detail in `docs/authorization.md`.

Deferred: multi-role per school, custom/runtime roles, invitations, admin UI for
`status` / `is_platform_admin`.

## ✅ Milestone 5 — School Onboarding (complete, 2026-09-13)

Platform-admin school provisioning (`/admin/schools`, `SchoolProvisioner`,
unique-slug generation), optional initial School Admin assignment, adding
existing users to a school (`/members/create`, `member.assign-role` + tier
guard), basic school settings (`school_settings`, 1:1, `BelongsToSchool`), the
initial academic session (`academic_sessions`, structure-agnostic), and a
derived dashboard onboarding checklist. Full detail in `docs/onboarding.md`.

Deferred: invitations / brand-new-account onboarding, school suspension /
subscription, the Academic Management milestone.

## ✅ Milestone 6 — School Settings & Configuration (complete, 2026-09-10)

Expanded `school_settings` (typed columns, no JSON blob) into the full
per-school configuration record, split into three sections — **Profile**
(contact + address), **Branding** (private-disk logo upload served through a
gated no-path route, `brand_color`), **Regional** (`timezone`, `locale`,
`currency`, `date_format`, `week_starts_on`, `academic_year_start_month`) — plus
`config/school-settings.php` reference data and the `DateFormat` / `Weekday`
enums. Built on the existing M3/M4 tenant + permission architecture
(`school.settings.view` / `.update`); Principal & Bursar read-only. Full detail
in `docs/school-settings.md`.

Deferred: grading scheme / term structure / holiday calendar (Academic
Management), notification & payment-gateway config (their own modules), feature
activation, app-wide render-time application of the formatting preferences.

## ✅ Milestone 7 — Feature / Module Activation (complete, 2026-09-10)

Per-school enable/disable of the application's feature modules. `App\Enums\Module`
(14 code-defined modules with label / description / group / dependencies /
default / `isAvailable()`), `school_modules` override-only table +
`App\Models\SchoolModule` (`BelongsToSchool`), the request-scoped
`App\Support\Modules\SchoolModules` resolver (one query per request, memoised),
a Modules admin page under school settings (`/settings/school/modules`, gated
`school.settings.*`), and the reusable `module:` route middleware + `@module`
Blade directive for future modules. Activation is configuration only — it grants
no permissions. Full detail in `docs/module-activation.md`.

Deferred: the modules themselves (each domain milestone), preset bundles,
disable-with-cascade, per-module config pages, activation audit trail,
plan-based entitlements.

## ✅ Milestone 8 — Academic Foundation (complete, 2026-09-16)

The configurable academic structure the later modules build on: academic
sessions (M5, extended) + `AcademicPeriod` (terms/semesters, any number),
`AcademicLevel` + `LevelArm` (classes and streams), `Subject`, and the
`level_subject` "which level offers which subject" link. All `BelongsToSchool`;
`/academic/*` gated by `academics.view` / `academics.manage` (M4 permissions,
previously dormant) **and** `module:academics`. `Module::Academics->isAvailable()`
is now `true`. Structure only — no students, teachers, timetable, attendance,
assessments or results. Full detail in `docs/academic-foundation.md`.

Deferred: students / guardians / staff, class & arm membership, teacher
assignment, timetable, attendance, assessments, results, grading, promotion,
CBT, per-subject assessment metadata, bulk import / year cloning, hard delete /
archival.

## ✅ Milestone 9 — Student Management (complete, 2026-09-17)

The tenant-scoped student record + enrollment-history foundation. `App\Models\Student`
(minimal PII; `StudentStatus` active/inactive/withdrawn/graduated; never hard-deleted)
+ `App\Models\Enrollment` (school-owned + student-scoped; points at session /
optional period / level / optional arm; one `active` = the current class via
`Enrollment::makeActive()` — not a promotion workflow). `/students/*` gated
`student.view` / `student.manage` (M4 permissions, previously dormant) **and**
`module:students` — which now depends on `module:academics`.
`Module::Students->isAvailable()` is now `true`. Full detail in
`docs/student-management.md`.

Deferred: guardians / parents (linkage + screens — done in M10), teacher
assignment, class rosters, attendance, assessments, results, fees, **promotion /
graduation workflow**, bulk student import, student ID / photo / documents,
medical info, transfer records, student portal.

## ✅ Milestone 10 — Guardian / Parent Management (complete, 2026-09-18)

Tenant-scoped guardian / parent records and the student ↔ guardian relationship.
`App\Models\Guardian` (minimal contact data — name / phones / email / address /
notes; no ID / financial / medical / emergency data; no portal credentials; no
global uniqueness; never hard-deleted) + `App\Models\GuardianStudent` (the link:
school-owned + carries `student_id` + `guardian_id`; explicit
`GuardianRelationship`; one `is_primary` per student via `makePrimary()`;
`unique(student_id, guardian_id)`). `/guardians/*` gated `guardian.view` /
`guardian.manage` (M4 permissions, previously dormant) **and** `module:guardians`
— which depends on `module:students`. `Module::Guardians->isAvailable()` is now
`true`; `guardian.view` added to the Staff bundle. Links are created from the
student profile. Full detail in `docs/guardian-management.md`.

Deferred: Parent Portal (guardian sign-in + portal accounts), guardian
messaging / notifications, pickup authorisation, custody documents, emergency
contacts, bulk guardian import, duplicate-guardian merge.

## ✅ Milestone 11 — Teacher Management (complete, 2026-09-19)

The tenant-scoped teacher professional record, an optional link to an existing
application account, and the teaching-assignment foundation. `App\Models\Teacher`
(minimal professional data — name / employee number / email / phone / start date
/ address / notes; no ID / financial / medical / credential fields;
`TeacherStatus` active/inactive/suspended/resigned; `status` + `user_id` not
mass-assignable; never hard-deleted) + `App\Models\TeacherAssignment`
(school-owned + teacher-scoped; teach a `Subject` to a level/arm for a
session/period; `TeacherAssignmentStatus` active/ended; history preserved;
duplicate-active check in the Form Request). Teacher ↔ User is a nullable
`user_id` linked only to a member of the active school — M11 builds no
invitation / credential / portal flow. `/teachers/*` gated `staff.view` /
`staff.manage` (M4 permissions, previously dormant) **and** `module:staff` —
which now depends on `module:academics`. `Module::Staff->isAvailable()` is now
`true`; `staff.manage` added to Principal, `staff.view` to Bursar / Teacher /
Staff. Full detail in `docs/teacher-management.md`.

Deferred: Teacher Portal (teacher sign-in + invitations), non-teaching staff
records, class rosters ("who teaches JSS1"), timetable, attendance, marks,
payroll / workload, qualifications & documents, bulk teacher import,
head-of-department / form-teacher designations.

## ✅ Milestone 12 — Timetable Management (complete, 2026-09-20)

A tenant-scoped, configurable weekly timetable with server-side conflict
detection and a draft → published lifecycle. `App\Models\Timetable`
(session-scoped, fixed at creation; `status` draft/published not mass-assignable;
publishing runs behind a conflict guard) + `App\Models\TimetableEntry` (one
lesson: level + **arm (required — scheduled per class)** + subject + teacher +
`Weekday` + `HH:MM` half-open times + optional text `room`; session/period
inherited from the parent). Scheduling rules (`TimetableEntryRequest`): end after
start, arm↔level, subject offered by the level, an **active M11
`TeacherAssignment`** backing the pairing, no teacher/class/room double-booking —
all DB existence queries. `TimetableConflictScanner` (one self-join) backs the
publish guard. `/timetables/*` gated `timetable.view` / `timetable.manage` (new
M4 permissions) **and** `module:timetable` — which now depends on
`module:academics` **and** `module:staff`. `Module::Timetable->isAvailable()` is
`true` but still **off by default**. `timetable.*` added to Principal / Teacher /
Staff (not Bursar). Desktop day×time grid, mobile stacked list, teacher-view.
Full detail in `docs/timetable-management.md`.

Deferred: student/parent timetable views (portals), publish notifications, a
rooms/facilities module, timetable templates / term cloning, named period grids,
teacher workload limits, recurring exceptions / cover, auto-generation.

## ✅ Milestone 13 — Attendance Management (complete, 2026-09-21)

Tenant-scoped daily student-attendance registers with a draft → submitted
(locked) lifecycle, **independent of the Timetable module**.
`App\Models\AttendanceRegister` (one class's attendance for one day — session /
optional period / level / arm / date; `status` + `submitted_*` not
mass-assignable; `submit()` / `reopen()`) + `App\Models\AttendanceRecord` (one
student's mark — nullable `status` = unmarked; `present` / `absent` / `late` /
`excused`; optional note; `recorded_at` / `recorded_by`). Eligibility is the M9
enrollment date range; the roster is snapshotted at creation. A register can't be
submitted while any student is unmarked; a locked register is corrected only via
an `attendance.manage` `reopen()`. `AttendanceAuthorizer` scopes a teacher to
classes they hold an active M11 assignment for. New `attendance.manage`
permission (Principal); `attendance.view` / `attendance.record` kept on Teacher /
Staff. `Module::Attendance->isAvailable()` is `true` and **on by default**;
depends on `module:academics` + `module:students` — **not** `module:timetable`.
Full detail in `docs/attendance-management.md`.

Deferred: attendance rate / percentage analytics, term & monthly reports,
per-lesson (timetable-driven) registers, portal attendance views, absence
notifications, an attendance-reason taxonomy, half-day records, a full
audit-trail / retention workflow.

## ✅ Milestone 14 — Assessment & Assignments (complete, 2026-09-22)

A configurable, tenant-scoped assessment and assignment foundation — the source
data for M15. `App\Models\AssessmentCategory` (school-configured, editable —
seeded Classwork / Homework / Test / Examination), `App\Models\Assessment`
(academic context fixed at creation; `status` draft → published → locked, not
mass-assignable; optional `assignment_id` link) + `App\Models\AssessmentScore`
(nullable `decimal(6,2)` score, `0..max_score` / 2 dp, never deleted),
`App\Models\Assignment` (`due_on >= assigned_on`, `teacher_id` owner, no scores;
draft → published → closed) + `App\Models\AssignmentSubmission` (completion only —
`pending` / `submitted` / `late` / `exempt`). Migrations
`2026_09_22_100000`–`100040`. Eligibility is the M9 enrolment date range
(`App\Models\Concerns\HasClassRoster`); rosters snapshotted at creation, a draft
assessment's roster re-syncable, publishing freezes it. Locking freezes scores;
only `assessment.manage` unlocks. `App\Support\Assessment\AssessmentAuthorizer`
scopes a teacher to their assigned `(level, subject)`. New `assessment.view` /
`assessment.record` / `assessment.manage` permissions (Principal → all; Teacher →
view + record; Staff → view). `Module::Assessments->isAvailable()` is `true`, on
by default, depends on `module:academics` + `module:students` — **not**
timetable / attendance / results / cbt. **No** final grades / percentages /
averages / positions / GPA are stored. Full detail in
`docs/assessment-management.md`.

Deferred: results & report cards, grading schemes, subject/term/session
averages, positions & ranking, GPA, promotion & graduation, CBT, portal
assessment views, assignment file attachments & online submission, automated
grading / plagiarism, assessment weighting, notifications, a full audit-trail /
retention workflow.

## ✅ Milestone 15 — Results & Report Cards (complete, 2026-09-23)

Turns M14's locked assessment scores into configurable student results and
printable report cards. `App\Models\GradingScheme` + `GradingSchemeGrade`
(school-configured percentage bands, `gradeFor()` the one place a percentage
becomes a grade) + `App\Models\ResultWeightingScheme` +
`ResultWeightingSchemeItem` (ties an M14 category to a weight, must sum to
100%) + `App\Models\ResultRun` (one class/one term, `status`
draft→compiled→reviewed→approved→published→locked, not mass-assignable) +
`App\Models\StudentResult` / `StudentSubjectResult` /
`StudentSubjectResultComponent` (compiled and **snapshotted** — immune to a
later grading/weighting-scheme edit) + `App\Models\ResultAdjustment` (a
controlled propose → apply/reject correction workflow, never a free edit) +
`App\Models\ReportCardConfiguration` (24 typed `show_*` booleans, school-wide/
session/term scope, a second `result_run_id`-tagged row as the frozen per-run
snapshot). `App\Services\Results\ResultCompiler` computes fully in memory and
writes nothing if any score is missing — never manufactures a zero;
`App\Support\Results\RankingCalculator` gives competition class-position
ranking. Migrations `2026_09_23_100000`–`100110` (2 additive columns onto
M14's tables + 10 new tables). New `result.manage` / `result.adjust`
permissions, reusing `result.view`/`.enter`/`.publish` already declared in M4
(Principal → all 5; Teacher → view + enter, class-scoped; Staff → view).
`Module::Results->isAvailable()` is `true`, on by default, depends on
`module:assessments` only. `AssessmentPurpose` / `ScoreSource` enums (added to
M14's tables) keep the door open for a future CBT pipeline without
redesigning this milestone. Full detail in `docs/results-report-cards.md`.

Deferred: Question Bank / CBT engine, Entry/Placement Assessment admin UI,
student & parent portal result views, promotion & graduation, advanced
transcripts, automatic report-card comments, a full drag-and-drop report-card
designer, PDF export (browser print for now), per-level report-card overrides,
bulk unlock of an approved+ run, per-run signature snapshotting, student photo
capture, notifications, a full audit-trail / retention workflow.

## ✅ Milestone 16 — Parent Portal (complete, 2026-09-24)

A secure, read-only, child-scoped window for a signed-in parent onto their
own children's published data. `Guardian.user_id` (new, additive column —
M10's own migration untouched; nullable, unique per school, not
mass-assignable, mirrors `Teacher.user_id` from M11) links an *existing*
member account to a `Guardian` record via `GuardianController::updateUser()`
— no accounts created, no invitations sent. `App\Support\Portal\
ParentPortalAuthorizer` is the single seam every portal controller uses to
resolve "which children may this parent see" — tenant-scoped for free
(`Guardian` is `BelongsToSchool`), never trusting a student id from the URL
until proven to be one of that guardian's own linked children via M10's
`guardian_student` link (no second student-parent table). `App\Services\
Results\ReportCardRenderer` was extracted from M15's own report-card
controller (identical behaviour, M15's test suite unchanged) so the
school/staff and parent report cards share one renderer. Result/report-card
visibility uses the documented, safest interpretation of M15's lifecycle —
`ResultRunStatus::visibleToParents()` (`published`/`locked` only), since M15
has no dedicated parent-visibility flag. Attendance reuses M15's
`AttendanceSummarizer`; assignments read M14's `AssignmentSubmission`
(`AssignmentStatus::visibleToParents()` excludes drafts); timetable shows
only a published M12 timetable for the child's current class. 8 thin
controllers under `App\Http\Controllers\Portal\*`, `/parent/*` routes gated
`module:parent-portal` (depends on `Guardians` only, declared since M7, now
flipped available) **and** `->can('portal.parent')` (no new permission —
declared since M4). A Parent-role member is redirected from `/dashboard`
straight to `/parent`; the main nav renders a portal-specific link set for
them. Full detail in `docs/parent-portal.md`.

Deferred: a communication hub (WhatsApp/SMS/email — this milestone is the
foundation it plugs into), fee/payment visibility (no finance module yet),
the Student Portal (kept deliberately separate), a full platform audit trail
of parent access events, parent self-service editing of guardian contact
details, push notifications.

## ✅ Milestone 17 — Student Portal (complete, 2026-09-25)

A secure, read-only window for a signed-in student onto their own published
data — mirrors the Parent Portal (M16) exactly but for the student's own
record directly, with no "which child" question (a student has at most one
linked record, so the Student Portal's routes carry no `{student}` parameter
at all). `Student.user_id` (new, additive column — M9's own migration
untouched) links an *existing* member account via
`StudentController::updateUser()`, mirroring `Guardian.user_id` / M11's
`Teacher.user_id`. `App\Support\Portal\StudentPortalAuthorizer` resolves the
signed-in user's own student record, tenant-scoped for free. Reuses
`App\Services\Results\ReportCardRenderer`, `AttendanceSummarizer`, and
`ResultRunStatus::visibleToParents()` / `AssignmentStatus::
visibleToParents()` from M15/M16 as-is — no domain logic duplicated. 7 thin
controllers under `App\Http\Controllers\Portal\Student*`, `/student/*`
routes gated `module:student-portal` (depends on `Students` only, declared
since M7) **and** `->can('portal.student')` (no new permission — declared
since M4). A Student-role member is redirected from `/dashboard` straight to
`/student`; the main nav renders a portal-specific link set. Full detail in
`docs/student-portal.md`.

Deferred: everything M16 defers, plus online assignment submission (M14 has
no upload workflow yet — this milestone only ever views assignment/
submission status), a generic "portal" abstraction shared with the Parent
Portal (kept deliberately separate).

## ✅ Milestone 18 — Communication & Notification Foundation (complete, 2026-09-26)

The school's Communication Hub as the system of record for school
communication, plus a shared in-app notification centre. `App\Models\
CommunicationThread` (school-owned; optional `student_id` / `guardian_id`
link; `status` open → resolved/escalated, reopenable, not mass-assignable) +
`App\Models\CommunicationMessage` (one reply; `sender_id` set from the
authenticated user, never request input) — staff-facing in this milestone
(a shared inbox, like the rest of the app's staff lists), never hard-deleted.
`App\Models\Announcement` (school-owned; `status` draft → published, not
mass-assignable; `audience` a coarse role-shaped bucket — everyone / all
staff / teachers / parents / students) — `AnnouncementController@index`/
`@show` are mounted at three route names (`announcements.*`,
`parent.announcements.*`, `student.announcements.*`), one controller, no
duplication, the M16/M17 shared-renderer pattern; visibility is a query
scope (`Announcement::scopeVisibleToRole()`), not just a route gate.
`App\Models\Notification` (its own `user_notifications` table —
deliberately **not** Laravel's conventional polymorphic `notifications`
table, which has no `school_id` and would bypass `SchoolScope`) + `App\
Services\Notifications\NotificationDispatcher` (bulk-inserts, one query per
fan-out regardless of audience size) is the single seam any module can use
to raise a notification without knowing about delivery channels
(`App\Enums\NotificationChannel`: only `in_app` implemented — `whatsapp` /
`sms` / `email` are declared, no provider code). Three domain events
(`AnnouncementPublished`, `CommunicationMessageAdded`,
`CommunicationThreadEscalated`), auto-discovered listeners, dispatched
synchronously (no queue introduced). New permissions `communication.view` /
`.create` / `.manage` / `.resolve` / `.escalate`, `announcement.view` /
`.manage`, slotted into the existing role tiers. Reuses `App\Enums\
Module::Notifications` (declared since M7) for the whole surface — now
`isAvailable()`. The notification centre itself needs no extra permission
(self-scoped to `auth()->user()`, tenant-scoped for free) and is shared
verbatim by staff, the Parent Portal and the Student Portal via three route
names pointing at one `NotificationController`. Full detail in
`docs/communication.md`.

Deferred: WhatsApp / SMS / email provider integration, two-way portal
messaging (guardians/students can view announcements & notifications but do
not reply into a thread — the Communication Hub stays staff-facing), level/
arm-scoped ("selected school groups") announcement targeting, wiring
assignment/result/attendance events into the notification foundation (the
mechanism is established; only M18's own events use it), a full audit trail
of communication access, push notifications, message attachments.

## ✅ Milestone 19 — Fees & Fee Management (complete, 2026-09-27)

A production-ready, tenant-safe fee management system: school-configured
`App\Models\FeeCategory` (mirrors `AssessmentCategory`, M14, exactly — not
hard-coded) + `App\Models\FeeStructure` (category × session × optional
period × level × optional arm, freely editable — editing it never touches
a charge already raised from it) + `App\Models\StudentFeeCharge`
(school-owned + student-scoped; **snapshots** the structure's amount and
context at creation, the whole answer to "a structure can change later
without altering history"; `discount_amount`/`waived_*` not
mass-assignable, changed only through `applyDiscount()`/`waive()`/
`unwaive()`) + `App\Models\FeePayment` (a manual receipt — cash/bank
transfer/POS/cheque/other via `App\Enums\PaymentMethod`; `reference`
unique per school; never edited/deleted, only `void()`-ed) +
`App\Models\FeePaymentAllocation` (how much of a payment applies to which
charge). `App\Services\Fees\FeeChargeService` / `FeePaymentService`
(atomic, row-locked allocation with over-allocation/cross-student guards)
/ `FeeStatementBuilder` (the single seam staff **and** both portals use for
a statement — the M16 shared-renderer pattern). Every monetary calculation
uses `bcmath` on `decimal(12,2)` columns — never native float arithmetic,
never Eloquent's float-casting `->sum()`. New `fees.view` / `.report` /
`.manage` / `.record-payment` / `.adjust` permissions (Bursar → all;
Principal → view + report only, oversight without write access, matching
the pre-existing `finance.*` precedent; Teacher/Staff → none;
Parent/Student → their own/linked child's statement via the existing
`portal.parent`/`portal.student`, read-only, no new permission). Reuses
`App\Enums\Module::Fees` (declared since M7) — now `isAvailable()`, on by
default, depends on `students` only. Full detail in `docs/fees.md`.

Deferred: Paystack / online payment (M20 — this milestone is exactly the
foundation it plugs into: `App\Enums\PaymentMethod` and every balance
calculation are provider-agnostic), a full discount/waiver audit-log table,
bulk fee-structure assignment/invoicing runs, fee reminders (the M18
notification foundation could carry these later), refunds beyond voiding
an unallocated/newly-allocated payment, receipts/PDF export beyond the
browser-printable statement, multi-currency.

## Milestone 20+ — Domain Modules

Online Payments (Paystack) · CBT · Reporting · Promotion.

Each domain module checks its `App\Enums\Module` flag (`module:` middleware /
`@module`) **and** its M4 permissions — the two stay orthogonal.

## Cross-cutting, introduced when first needed

Queues & Redis, object/S3 storage abstraction for uploads, audit logging,
full-text search, caching layer, background exports.

## Permanently out of scope

Public school websites, website builder, themes, website engine, public school
pages, public content management.
