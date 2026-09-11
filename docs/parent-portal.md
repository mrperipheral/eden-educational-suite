# Parent Portal

Status: **Milestone 16 — complete.** A secure, read-only, child-scoped window
for a signed-in parent onto their own children's published data: results,
report cards, attendance, assignments and timetable. Built on the existing
`TenantContext` + `BelongsToSchool` + `Permission` + `module:parent-portal`
seams, the M2 `User` account, M10's `Guardian`/`GuardianStudent` relationship,
and M9/M12/M13/M14/M15's own data — no second authentication system, no
second tenancy mechanism, no second report-card generator. Full detail below;
see also `docs/guardian-management.md` for the M10 relationship this
milestone builds on.

## 1. The core relationship

```
User (M2 login)
  ↓  Guardian.user_id  (nullable, set only via GuardianController::updateUser())
Guardian (M10 person record)
  ↓  guardian_student   (M10 link — relationship, is_primary)
Student (M9)
```

A `User` is **not** automatically a `Guardian` — they are separate concepts,
exactly like `Teacher.user_id` (M11). `Guardian` gained one additive column,
`user_id` (migration `2026_09_24_100000_add_user_id_to_guardians_table.php` —
M10's own migration is untouched), nullable, unique per school, **not**
mass-assignable. An admin links an *existing* member to a guardian record at
`/guardians/{guardian}` — M16 creates no accounts and sends no invitations,
identical in spirit to M11's teacher-account link.

The Parent Portal reuses M10's `guardian_student` link as the **only**
source of truth for "which children can this parent see" — no second
student-parent relationship table was created.

## 2. Authorization: `App\Support\Portal\ParentPortalAuthorizer`

The single seam every portal controller uses:

```php
$authorizer->guardianFor($user): ?Guardian        // this user's Guardian, in the active school
$authorizer->studentsFor($user): Collection        // every linked, authorized child
$authorizer->authorizedStudent($user, $studentId): ?Student   // one child, or null
```

`guardianFor()` queries `Guardian::where('user_id', $user->id)` — since
`Guardian` is `BelongsToSchool`, this is **already tenant-scoped** by the
existing `SchoolScope` global scope; there is no second tenant mechanism to
maintain. `authorizedStudent()` resolves a student **only** through
`$guardian->students()->whereKey($id)` — a student id is never trusted from
the URL alone, and a wrong id, another family's child, or a cross-school id
all resolve to `null` (callers `abort(404)`) identically. Every `{student}`
route parameter is resolved this way — **never** route-model-bound.

A parent with no linked `Guardian`, or a `Guardian` with no linked students,
resolves to an empty collection — a safe empty state, never an error, and
never a query that "loads everything then filters in Blade."

Gated by the existing `Permission::PortalParent` (`portal.parent`) — declared
since M4, enforced for the first time here. **No new permission was added.**
`Role::Parent` is the only role holding it on its own; `Role::SchoolAdmin`
also holds it (via its full bundle) but is never itself a `Guardian`, so it
sees the same empty state as an unlinked parent — permission and data-linkage
protect the portal independently, at two different layers.

## 3. Routes

```
GET  /parent                                          parent.dashboard
GET  /parent/children/{student}                       parent.children.show
GET  /parent/children/{student}/results                parent.results.index
GET  /parent/children/{student}/results/{run}          parent.results.show
GET  /parent/children/{student}/report-cards            parent.report-cards.index
GET  /parent/children/{student}/report-cards/{run}      parent.report-cards.show
GET  /parent/children/{student}/attendance              parent.attendance.index
GET  /parent/children/{student}/assignments             parent.assignments.index
GET  /parent/children/{student}/timetable               parent.timetable.index
GET  /parent/profile                                    parent.profile.edit
```

Two gates on every route: `module:parent-portal` (404 when off) **and**
`->can('portal.parent')`. `{student}`/`{run}` are `->whereNumber(...)`
(numeric-only, tenant-safe route params, matching every prior milestone) and
resolved inside the controller via `ParentPortalAuthorizer`, never route-model
bound. `App\Http\Controllers\Portal\*` (8 thin controllers, one concern each:
`ParentPortalController`, `ParentStudentController`, `ParentResultController`,
`ParentReportCardController`, `ParentAttendanceController`,
`ParentAssignmentController`, `ParentTimetableController`,
`ParentProfileController`) — no giant catch-all controller.

## 4. Multiple children & the child switcher

One `Guardian` may be linked to any number of students; the dashboard
(`/parent`) shows a card per child (name, admission number, class + arm,
session, term, status). Each child-scoped page includes a **"Switch child"**
dropdown (`resources/views/parent/_child-nav.blade.php`) listing every one of
the parent's own authorized children — switching only ever lands on another
page the same `ParentPortalAuthorizer` check re-validates on that new
request. There is **no second global tenant context** for "the selected
child" — every child-scoped route names the student explicitly in its own
URL (`/parent/children/{student}/...`), so tampering with the id in any of
those URLs is caught by the same per-request authorization check that
protects the profile page itself, not by trusting a prior selection.

## 5. Result & report-card visibility

M15 has **no dedicated parent-visibility flag** on `ResultRun`. The
documented, safest interpretation — `App\Enums\ResultRunStatus::
visibleToParents()` — is that a parent may see a run only once it is
`published` or `locked`; `draft`/`compiled`/`reviewed`/`approved` runs stay
admin/staff-only, however close to finished they are. `ParentResultController`
and `ParentReportCardController` both filter on this before a run is ever
resolved, and independently re-check it on `show()` — a parent can never
guess a run id into an in-progress result.

`App\Services\Results\ReportCardRenderer` (extracted from M15's own
`Results\ReportCardController` during this milestone, with identical
behaviour — the existing M15 test suite passed unchanged) is the **one**
place a report card is built. `Results\ReportCardController` (staff/admin)
and `Portal\ParentReportCardController` (this milestone) both call it — they
differ only in *who may ask for which run/student*, never in how the report
card itself is rendered. The Parent Portal's plain "Results" page
(`ParentResultController`) also reuses the renderer's
`subjectResultsFor()` — the exact query the report card's subject table is
built from — so a parent's results view and their report card for the same
term can never drift apart.

The M15 report card's "← Result run" back-link (which pointed at the
staff-only `results.runs.show` page) is now audience-aware — it shows the
staff link only `@can('result.view')`, and a "← Report cards" link into the
Parent Portal otherwise. This was a genuine bug the M16 reuse surfaced: a
parent clicking the old link would have hit a 403. **One remaining, deliberately
undisturbed limitation inherited from M15**: the report card's branding logo
only renders for a viewer holding `school.settings.view` (an M15 decision to
avoid a broken-image icon for viewers without that permission) — a Teacher,
Staff, or Parent viewer never sees the school logo on a report card. Fixing
that would mean widening `school.settings.view` or adding a second,
differently-gated logo route, which is out of scope for a milestone whose
mandate is "the smallest clean changes necessary."

## 6. Attendance, assignments, timetable

- **Attendance** (`ParentAttendanceController`) reuses
  `App\Support\Results\AttendanceSummarizer` (M15) verbatim — no attendance
  data is duplicated or recomputed. Only **submitted** (locked) M13 registers
  ever count; internal recording metadata (`recorded_by`, register ids) is
  never exposed, only the day counts and percentage the summarizer already
  returns. Shown per term across every session the child has an enrollment
  in — never assumes three terms.
- **Assignments** (`ParentAssignmentController`) reads the child's own M14
  `AssignmentSubmission` rows — the query is always `where('student_id',
  $studentModel->id)`, so another student's submission can never appear.
  `App\Enums\AssignmentStatus::visibleToParents()` (new, small, additive
  method — `published`/`closed`, never `draft`) gates which assignments show
  at all. Nothing on this page can modify a teacher-owned record.
- **Timetable** (`ParentTimetableController`) resolves the child's *current*
  `Enrollment` and shows only a **published** M12 `Timetable` for that
  session, filtered to the enrollment's `level_arm_id`. A draft timetable
  never renders.

Every one of these three (plus Results/Report cards) degrades to a clean,
rendered "not currently available" empty state — never a 404 or a broken
page — when its underlying module (`Attendance`, `Assessments`, `Timetable`,
`Results`) is off for the school, checked in the controller via
`SchoolModules`, not route middleware (a route-level `module:` gate would
have 404'd the *whole* Parent Portal page rather than degrading just that one
section).

## 7. Profile

`/parent/profile` shows the linked `Guardian`'s contact details —
**deliberately read-only**. M10 remains the source of truth for guardian
contact data and guardian ↔ student relationships; a parent who needs a
detail corrected contacts the school (no write route exists for a parent to
self-edit their own guardian record, or to add/remove a student link, remove
another guardian, change a relationship type, or make themselves primary —
those stay `guardian.manage`-gated M10 actions). Login identity
(name/email/password) is the `User` account, edited at the existing, unchanged
`/settings/profile` — never duplicated onto `Guardian`.

## 8. Module & navigation

`Module::ParentPortal` (`parent-portal`) — declared since M7 — is flipped
`isAvailable() => true` this milestone (on by default; depends on
`Module::Guardians` only, already declared). A Parent-role member is
redirected from the generic `/dashboard` straight to `/parent`
(`DashboardController`, guarded by the module check so a school that somehow
still has Parent-role members with the module off falls back to the normal
dashboard rather than a 404 loop). The main authenticated nav
(`resources/views/components/layouts/authenticated.blade.php`) renders a
**different**, portal-specific link set (My Children · Profile · Account
settings) for a Parent-role viewer instead of the admin/staff list, which
would otherwise render almost empty for them.

## 9. Tenant isolation

Non-negotiable, enforced server-side, identical in shape to every prior
milestone — and additionally proven for the *family* boundary a portal
introduces:

- `Guardian.user_id` lookups, `guardian->students()`, every `ResultRun` /
  `AttendanceRegister` / `Assignment` / `Timetable` query the portal
  controllers issue are all `BelongsToSchool`-scoped; nothing here bypasses
  or duplicates `SchoolScope`.
- Feature tests prove: a parent cannot open an unrelated student in the
  *same* school; cannot reach a student from *another* school by id; cannot
  use a second school membership to reach that school's other families'
  children (switching context resolves *that* school's own Guardian link, or
  the empty state — never leaks across); a result/report-card/attendance/
  assignment/timetable page for a child they are not linked to 404s
  regardless of whether real data exists for that child.
- Admin-side linking (`GuardianController::updateUser()`) is tenant-scoped
  exactly like `TeacherController::updateUser()`: the account must be a
  member of the active school, the `{guardian}` route id is resolved by
  tenant-scoped `findOrFail`, and the same account may be linked to a
  *different* guardian in a *different* school without conflict (`unique`
  per school, not globally).

## 10. Performance

- The dashboard and every child-scoped page issue the **same** query count
  regardless of how many *other* students/guardians the school has, and
  regardless of how many children the signed-in parent themselves has —
  proved by `tests/Feature/Portal/ParentPortalStructureTest.php` (a 3-child
  parent in a 55-student school costs the same queries as a 1-child parent in
  a tiny one).
- `authorizedStudent()` never scans the student table — one indexed,
  tenant-scoped `guardian_student` join resolves the single row.
- Assignments are paginated (15/page, joined + ordered by due date server
  side, not sorted in PHP). Attendance summaries reuse M15's already-batched
  `AttendanceSummarizer` (2 queries per term, not per student). No
  Redis/queues were introduced.

## 11. Privacy

Data minimization throughout: a parent never sees another student, another
parent, staff-only notes, internal ids, internal audit information, medical
information, or (no finance module exists yet) financial information. The
report card and results pages show only what M15's own configuration and
publication rules already permit a viewer to see — the portal adds an
*additional* restriction (own child, published only), never a wider one.

## 12. Seed data

`DatabaseSeeder` (Alpha school): a Parent-role account
(`parent@example.com` / `Folake Ade`) linked to the existing "siblings
sharing a guardian" `Guardian` record from M10's own seed data (no duplicate
guardian created), now covering **three** children across three different
classes — the two original siblings, plus one student pulled from the
Primary 1 Gold roster that already has the full M13 (attendance) / M14
(assessments) / M15 (published, locked result run + report card) / M12
(published timetable) picture, so the portal demo has one richly-populated
child alongside two with no results yet. `dual@example.com` (Parent at Beta,
Teacher at Alpha) demonstrates the "no linked guardian" empty state, since
Beta has no guardian records at all — and, incidentally, the *other* family's
case: two different parents, in two different schools, each seeing only
their own.

## 13. Deferred

WhatsApp / SMS / email notifications and any parent-school communication hub
(a future **Communication Hub** milestone plugs into the portal foundation
built here, not the other way round) · fee/payment visibility (no finance
module exists yet) · the Student Portal (a distinct future milestone — this
one deliberately keeps parent authorization separate rather than building a
shared "portal" abstraction) · a full platform audit trail of parent access
events (flagged here for that future Administration/Audit milestone, not
built as a parallel system) · parent self-service editing of guardian contact
details · push notifications · a parent-facing search across the whole
school · per-parent analytics.
