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

## ✅ Milestone 20 — Online Fee Payment / Paystack (complete, 2026-09-28)

Lets an authorised parent or student start an online fee payment through
Paystack and have a genuinely verified successful payment recorded into
M19's existing fee/payment system — extends it, never a parallel one.
`App\Models\PaystackTransaction` (school-owned + student-scoped, its own
table — an attempt can fail/be abandoned and must never become an
authoritative payment; `App\Enums\PaystackTransactionStatus` not
mass-assignable). `App\Services\Paystack\PaymentInitiationService`
validates the amount against the student's *current* outstanding balance
via M19's own `FeeStatementBuilder`, then calls Paystack's
`/transaction/initialize` (redirect checkout — no card data ever touches
this app); a failed call rolls the local row back too.
`App\Services\Paystack\PaymentVerificationService::verifyAndRecord()` is
the single idempotent core both the browser callback and the webhook call
— resolves the transaction, anchors `TenantContext` to its own stored
school, row-locks it, and (only if still `pending`) re-verifies with
Paystack's own `/transaction/verify` (never trusting a webhook body or a
browser query param alone) before calling M19's own
`FeePaymentService::record()` (with a new `planFifoAllocation()` helper).
A resolved row short-circuits every later call to a no-op — the guard
against duplicate webhooks, a reloaded callback, or a webhook/callback
race. `App\Http\Controllers\PaystackWebhookController` sits outside
`auth`/`tenant`/`module` entirely, resolves *which* school's secret to
verify the signature with from its own stored transaction data (never
from the request), and checks `HMAC-SHA512` via `hash_equals()`.
`SchoolSetting` gains `paystack_enabled`/`paystack_public_key`/
`paystack_secret_key` (`encrypted` cast)/`paystack_test_mode`, edited
under the existing `school.settings.*` permissions. No new permissions —
reuses `portal.parent`/`portal.student` and `school.settings.*`. Full
detail in `docs/paystack.md`.

Deferred: a dedicated staff-facing list of online-payment attempts,
refunds through Paystack's own API, recurring/subscription billing, an
inline-JS/Popup checkout alternative, any payment channel other than
Paystack.

## ✅ Milestone 21 — Promotion & Graduation (complete, 2026-09-29)

A safe, auditable academic progression workflow built entirely on M9's
existing `Student`/`Enrollment` architecture. `App\Models\PromotionBatch` +
`PromotionRecord` (school-owned; one row per student per batch, `promoted`/
`skipped`/`failed`) record a bulk promotion; graduation has no separate
table — four additive `graduated_*` columns on `students` are the entire
audit trail (a student can only be graduated once at a time, unlike
promotion). `App\Services\Promotion\PromotionService::promoteBatch()`
processes each selected student in its **own** `DB::transaction()` with the
`Student` row `lockForUpdate()`-ed — one student's failure never rolls back
another's success — and reuses M9's own `Enrollment::makeActive()` unchanged
to create the new placement while preserving the old one, closed
(`completed`), never deleted or rewritten.
`App\Services\Promotion\PromotionEligibilityService` is the single seam
both the roster UI and the service itself use to decide who may be
promoted (active status + a matching active enrollment — no invented
pass/fail rule). `App\Services\Promotion\GraduationService::graduate()` /
`reactivate()` transition `StudentStatus::Graduated` and back — never a
deletion — with no hard-coded graduating level. New permissions
`promotion.view` / `promotion.manage` / `graduation.manage` (School Admin +
Principal full; Teacher/Staff view-only; Bursar/Parent/Student none — they
reach current placement through the existing portals, unchanged, since
`currentEnrollment` is a live relation). `Module::Promotion`, on by default,
depends on `Students` only. Full detail in `docs/promotion.md`.

Deferred: automatic pass/fail promotion rules of any kind, a promotion
approval step distinct from running the batch, bulk import, bulk-undo of a
completed batch, notification (M18) hooks on promotion/graduation.

## ✅ Milestone 22 — Learning Materials (complete, 2026-09-30)

A teacher or admin uploads a single file (PDF, image or audio — video is
declared in `App\Enums\LearningMaterialType` but disabled) for a subject +
class; students in that class view/download it. Intentionally small: no
drafts, autosave, versioning or approval workflow.
`App\Models\LearningMaterial` (school-owned; `type`/`file_*`/`uploaded_by`
not mass-assignable, derived from the file itself) is stored on the same
private `local` disk M6/M15 already use, never a public URL.
`App\Services\LearningMaterials\LearningMaterialUploadService` stores the
file **first**, then creates the row inside a `DB::transaction()` — a
failure after the file is written deletes the orphan file before rethrowing,
so there's never a row without a file or a file without a row. Video is
rejected at two independent layers: the Form Request's `mimetypes:`
whitelist (real content-sniffed, so a video file renamed with a `.pdf`
extension still fails) and the service's own
`LearningMaterialType::isEnabled()` check (a defence-in-depth gate that
doesn't depend on the Form Request having run). New permissions
`material.view` / `.upload` / `.manage` — School Admin + Principal full;
Teacher `.upload` scoped to classes/subjects they hold an active M11
`TeacherAssignment` for (`App\Support\LearningMaterials\
LearningMaterialAuthorizer`, mirrors `AssessmentAuthorizer`); Staff view-
only; Bursar/Parent/Student none — a student reaches their own current
class's materials through the existing `portal.student` permission, no new
one needed. `Module::LearningMaterials`, off by default (a specialised
opt-in), depends on `Academics` only. Full detail in
`docs/learning-materials.md`.

Deferred: Parent Portal visibility, editing an uploaded material (delete +
re-upload covers a correction), multiple files per material, video
upload/streaming/transcoding/thumbnails/player, download analytics,
notification (M18) hooks, bulk upload.

## ✅ Milestone 23 — CBT / Online Examinations (complete, 2026-10-01)

School-scoped online examinations with multiple-choice and true/false
questions, one timed attempt per student, server-side automatic marking,
and immediate or scheduled result release. No essay/manual-marking
questions, no proctoring. `App\Models\Question` + `QuestionOption`
(school-owned, reusable bank — deliberately minimal M24-compatible
groundwork, not the bank itself) are never read directly by a live exam:
`App\Services\Cbt\ExaminationQuestionService::attach()` snapshots a
question's content into `App\Models\ExaminationQuestion` +
`ExaminationQuestionOption` the moment it's attached, so a later edit to
the bank question never changes an exam that already uses it.
`App\Enums\ExaminationStatus` (`Draft` → `Scheduled` → `Closed`, one-way)
is deliberately separate from `App\Enums\ExamAttemptStatus`
(`InProgress`/`Completed`) — closing an exam stops new attempts starting;
one already in progress is still individually finalised by its own expiry
check. `unique(examination_id, student_id)` at the DB level is the real
"one attempt per student" guarantee — a concurrent double-start is caught
and resolved by resuming the row that won the race, never a duplicate or a
500. `App\Services\Cbt\ExamAttemptService` computes `expires_at` once at
`started_at + duration_minutes` (capped to the exam's own `ends_at`) and
re-checks the server clock — never a client value — before accepting any
answer or letting a submission stand; an expired attempt is auto-marked
and finalised the next time anything touches it. Marking compares each
answer's `selected_option_id` against the snapshotted option's
`is_correct` server-side only. Result release
(`App\Enums\ResultReleaseMode`: `Immediate`/`Scheduled`) is evaluated
inline at read time via `ExamAttempt::isResultVisible()` — no queue or
scheduler exists in this app and M23 doesn't add one, mirroring
`ResultRunStatus::visibleToParents()`'s own lifecycle-gate precedent but
with an actual timestamp. Correct answers are never exposed to a student
at any point, independent of result release. New permissions `cbt.view` /
`.author` / `.manage` / `.take` — Teacher `.author` scoped to
classes/subjects they hold an active M11 `TeacherAssignment` for
(`App\Support\Cbt\CbtAuthorizer`, mirrors `AssessmentAuthorizer`
exactly); Student reaches it through `cbt.take`, the single gate for the
whole `/student/cbt/*` surface. `Module::Cbt`, off by default, depends on
`Assessments` only. CBT results are **not** compiled into M15's
`ResultRun` in this milestone — the documented extension point is M14's
own `ScoreSource::OnlineCbt` case on `AssessmentScore`, reserved
precisely for this. Full detail in `docs/cbt.md`.

Deferred: essay/manual-marking questions, a detailed per-question
answer-review screen, multiple attempts per exam, any proctoring
(webcam/screen recording/AI/biometric/browser lockdown), M15 `ResultRun`
integration, notification (M18) hooks, bulk exam templates, staff
analytics beyond a plain attempts list.

## ✅ Milestone 24 — Question Bank (complete, 2026-10-02)

Evolves M23's minimal `Question`/`QuestionOption` structure — the same two
models, extended in place, never a parallel system — into a proper
reusable, searchable, filterable Question Bank, without touching the M23
exam/attempt/marking/result-release architecture at all: the attach-time
snapshot mechanism (`App\Services\Cbt\ExaminationQuestionService::attach()`)
is unchanged and verified still fully isolates a live/historical exam from
any later Question Bank edit, deactivation or archival. Adds optional
level/arm scoping (`academic_level_id`/`level_arm_id`, both nullable — a
question can stay subject-only and reusable at any level, or be scoped to
one class), a free-text `topic`, a fixed `difficulty` scale
(`App\Enums\QuestionDifficulty`), and a real lifecycle
(`App\Enums\QuestionStatus`: Active/Inactive/Archived, replacing the M23
`is_active` boolean) with unrestricted, freely-reversible transitions — a
question's status only ever gates whether it can be **newly** attached to
a future exam. Only `Active` questions may be newly attached, enforced at
two independent layers (the attach picker's own filter, and
`ExaminationQuestionService::attach()`'s own re-check) so a
direct/tampered request is rejected server-side regardless of the UI.
`App\Support\Cbt\CbtAuthorizer::canManageQuestionFor()` mirrors M23's own
`canAuthorFor()` exam-authorship check exactly — a Teacher without
`cbt.manage` may only create/edit/archive a question for a subject (+
level/arm, if set) they hold an active M11 `TeacherAssignment` for; no
second authorization system, and *viewing* the bank stays unscoped since
it's a shared, reusable resource. Full detail in `docs/question-bank.md`.

Deferred: tagging beyond the single `topic` field, question pools/
randomised selection, versioning, bulk import/export, a shared
cross-school library, a per-teacher "my questions" filtered view.

## ✅ Milestone 25 — Entry / Placement Assessment (complete, 2026-10-03)

A simple, school-scoped record of an assessment conducted for a prospective
or newly admitted student — recording only, never a placement decision.
`App\Models\EntryAssessment` — one row per candidate per subject assessed
(a multi-subject candidate gets several rows sharing the same candidate/
admission-reference/date, no batch entity introduced); `candidate_name` is
always stored explicitly, independent of the optional `student_id` link, so
the record is self-contained even for a genuinely prospective candidate
with no `Student` row at all. `academic_level_id` is required,
`level_arm_id` nullable (the arm may not be decided yet); `score` is
nullable (a record can exist ahead of the assessment being conducted),
`max_score` always required. `percentage` is never stored — always derived
via `EntryAssessment::percentage()` (`bcmath`); `result` is a free-text,
school-defined outcome label (e.g. "Pass"/"Fail"), not an enum, and never
drives any automatic action. `App\Enums\EntryAssessmentStatus`
(`Active`/`Archived` only) is the record's own retention lifecycle —
freely reversible, like `Question::archive()`/`activate()` (M24); no hard
delete, ever. New permissions `entry_assessment.view`/`.record`/`.manage`
reuse the M23/M24 three-permission shape exactly (School Admin + Principal
full; Teacher `.record` scoped to classes/subjects they hold an active M11
`TeacherAssignment` for via `App\Support\EntryAssessment\
EntryAssessmentAuthorizer`, mirroring `CbtAuthorizer::canAuthorFor()`
precisely; Staff `.view` only; Bursar/Parent/Student none). A CSV export
(`response()->streamDownload()`, no new package) shares the exact same
filtered, tenant-scoped query the list page uses, so it always reflects the
active search/filters and can never include another school's rows;
iterates via `chunk()` rather than `cursor()` so eager-loaded relations
stay batched (avoiding N+1) while memory stays bounded. `Module::
EntryAssessment`, off by default (a specialised opt-in, like Timetable/
Learning Materials/CBT), depends on `Academics` only — a linked `Student`
record is optional, not required. No CBT engine of its own; the existing
M23/M24 Question Bank/CBT system is untouched. Full detail in
`docs/entry-placement-assessment.md`.

Deferred (explicitly out of scope per spec): placement recommendation,
recommended class/arm, a placement decision workflow, automatic placement/
enrolment, promotion/graduation, AI-based placement decisions, an
admissions CRM, application payment, interviews, document management,
psychometric testing, proctoring, adaptive testing, question pools/
randomisation, a second CBT engine, notifications, SMS/WhatsApp workflows,
complex reporting/analytics, bulk import.

## ✅ Milestone 26 — Administration & Audit (complete, 2026-10-04)

A tenant-scoped, immutable audit trail for administrative and
security-relevant events, a staff-facing Audit Log viewer, and a small
administrative dashboard panel — accountability and traceability, not a
SIEM. `App\Models\AuditLog` is written exclusively through `App\Services\
Audit\AuditRecorder::record()` (the single seam every module uses,
mirroring `NotificationDispatcher`'s role for M18); there is no edit/
destroy route anywhere in the app, which is what makes a record immutable
from the UI rather than a model-level guard. `school_id` is **nullable** —
deliberately not `BelongsToSchool` — because a handful of genuine
account-level security events (login, logout, password reset, email
verification) run on routes with no `tenant` middleware and have no school
to attribute to; they are recorded honestly with `school_id = null` and
never appear in any school's filtered viewer, a documented scope boundary
rather than a bug. Auth integration reuses Laravel's own already-fired
events (`Login`/`Failed`/`Logout`/`PasswordReset`/`Verified`) via listeners
under `App\Listeners\Audit\*` — no changes to the existing hand-rolled M2
authentication, no auth package introduced. Every `changes` payload passes
through a blanket, key-name-based redaction filter
(`password`/`secret`/`token`/`api_key`/`private_key`-shaped keys become
`[redacted]`), not a per-model allow-list; the Paystack secret key is
additionally never included in its own settings-update audit payload at
all. One new permission, `audit.view` (School Admin + Principal only —
Teacher/Bursar/Staff hold it in no bundle, matching "no audit access
unless explicitly granted"), gated by no `Module::` since audit
accountability is core administration, not an optional domain feature.
Every audited action is an explicit call at a genuine controller mutation
point — not a magic model-event hook that would fire on every factory
call across the existing test suite — covering every category the spec
names by name: user/access administration (membership created/role-
changed/removed), school administration (school created, settings/
branding/regional/payments updated, module toggled), and a representative
slice of academic/operational actions (students, guardians, teachers,
academic sessions, result-run publish/lock, fee payments, CBT
examinations, Question Bank lifecycle, Entry/Placement Assessment).
Deliberately not built: account activation/deactivation/suspension admin
UI (`User.status` is account-wide, not per-school — a cross-school-
impacting action needing its own design, not a side effect of auditing),
a scheduled retention command (no scheduler exists in this app to run
one). Full detail in `docs/audit.md`.

Deferred: a scheduled audit-retention/pruning command, a per-record "view
this record's own audit history" panel (the `auditable_type`/
`auditable_id` composite index is ready for it), account activation/
deactivation/suspension admin UI, exhaustive field-level audit coverage of
every edit to every business record (only creation and status/lifecycle
changes are audited for most models — the moments with real
accountability weight).

## ✅ Milestone 27 — Advanced Reporting & Analytics (complete, 2026-09-13)

A consolidated reporting/analytics layer over the data M9–M26 already
produce — school dashboard KPI cards, a Reports hub with nine domain report
areas, CSV export, and a separate Platform Reports screen for platform
administrators. Reporting on existing data, not a BI platform: no data
warehouse, ETL, Elasticsearch/OpenSearch, Redis, queues, scheduled report
delivery, or user-built report designer. `app/Reports/*` — one plain,
constructor-injected class per domain (`AcademicReport`, `AttendanceReport`,
`FeeReport`, `StudentReport`, `StaffReport`, `CbtReport`, `PromotionReport`,
`LearningMaterialReport`, `CommunicationReport`, `DashboardReport`,
`PlatformReport`), each returning a `LengthAwarePaginator` or a small
`Collection`/array — never a bespoke "report engine" abstraction. Every
total is a SQL aggregate (`SUM`/`COUNT`/`AVG`/`GROUP BY`), indexed
`whereIn`, eager loading, and pagination — never a per-student/per-class
query loop. `App\Support\Reports\ReportAuthorizer` is the one shared helper
generalising the `(level, subject)` active-`TeacherAssignment` scoping
pattern every other module's own authorizer (`AssessmentAuthorizer`/
`CbtAuthorizer`/`LearningMaterialAuthorizer`/`ResultAuthorizer`) already
implements independently — a Teacher without a domain's own "manage"
permission is scoped to only their own assigned classes/subjects, bypassed
entirely for a "manage" holder.

Two new, deliberately coarse permissions — `reports.view`/`reports.export`
— are always **composed with**, never a substitute for, each report's own
pre-existing domain permission (Academic reports require both
`reports.view` **and** `result.view`; Fee reports require both
`reports.view` **and** `fees.report`) — this is why a Bursar (who holds
`reports.view`) still cannot open Academic reports. Every report/export
route additionally requires its own underlying domain module
(`module:results` on `/reports/academic`, `module:fees` on
`/reports/fees`, …) on top of `module:reports`, mirroring every other
feature area's own routes, so a school that has turned a domain module off
cannot reach it through the reporting back door. Platform Reports
(`/admin/reports`) reuses the exact same `SchoolPolicy::viewAny`
(`isPlatformAdmin()`) check `Platform\SchoolController` already uses — not
a new permission, not a tenant bypass — with its two genuinely cross-school
counts wrapped in `TenantContext::runWithoutScope()`, the one sanctioned
escape hatch. Every report with a meaningful tabular export streams CSV via
`response()->streamDownload()` + `chunk(200, ...)` against the exact same
filtered/scoped query the page itself uses — never loading a whole school's
data into memory, never exposing a secret/token/password. Full detail in
`docs/reporting.md`.

Deferred (explicitly out of scope per spec): a data warehouse, BI platform,
Power BI integration, Elasticsearch/OpenSearch, Redis for analytics,
complex ETL, real-time streaming analytics, predictive/AI/ML analytics,
advanced anomaly detection, subscription billing/entitlement engines,
scheduled report delivery or email automation, a drag-and-drop report/
dashboard builder, a complex charting framework, HR/payroll analytics, any
new third-party reporting package.

## Milestone 28+ — Domain Modules

Each domain module checks its `App\Enums\Module` flag (`module:` middleware /
`@module`) **and** its M4 permissions — the two stay orthogonal.

## Cross-cutting, introduced when first needed

Queues & Redis, object/S3 storage abstraction for uploads, audit logging,
full-text search, caching layer, background exports.

## Permanently out of scope

Public school websites, website builder, themes, website engine, public school
pages, public content management.
