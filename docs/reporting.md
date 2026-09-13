# Advanced Reporting & Analytics

Status: **Milestone 27 — complete.** A consolidated reporting/analytics layer
over the data M9–M26 already produce: school dashboard KPI cards, a Reports
hub with nine domain report areas, CSV export, and a separate Platform
Reports screen for platform administrators.

**This is reporting on existing data, not a BI platform.** No data
warehouse, no ETL, no Elasticsearch/OpenSearch, no Redis, no queues, no
scheduled report delivery, no user-built report designer, no predictive/AI
analytics — see §9 for the full explicitly-out-of-scope list. Every number
shown here is computed live, on request, from the same tables every other
milestone already writes.

## 1. What this is

`app/Reports/*` — one plain, constructor-injected class per domain
(`AcademicReport`, `AttendanceReport`, `FeeReport`, `StudentReport`,
`StaffReport`, `CbtReport`, `PromotionReport`, `LearningMaterialReport`,
`CommunicationReport`, `DashboardReport`, `PlatformReport`). Each method
returns either a `LengthAwarePaginator` (row-shaped reports) or a small
`Collection`/array (aggregate summaries) — never a bespoke "report engine"
abstraction. `app/Http/Controllers/Reports/*` are thin controllers, one per
domain, each with an `index()` (a `?tab=` switch where a domain has more
than one named report) and an `export()`. `App\Support\Reports\
ReportAuthorizer` is the one shared helper for "what does this Teacher's
active `TeacherAssignment`s cover" — every report's own private
`scopeToTeacher()` calls it, mirroring the identical pattern
`AssessmentAuthorizer`/`CbtAuthorizer`/`LearningMaterialAuthorizer`/
`ResultAuthorizer` already each implement independently.

## 2. Reports hub and dashboard KPIs

`ReportsHomeController` (`/reports`) lists every report area this viewer may
open — each entry gated by the same `module enabled? && permission held?`
check the route itself enforces, so there is never a visible link to a page
that would 403. `DashboardReport::kpis()` adds KPI cards (Students, Staff,
Attendance, Results, Fees, CBT) to the existing dashboard
(`resources/views/dashboard.blade.php`) — each card independently gated by
its own module + permission. **A disabled module or a lacking permission
omits that card entirely; it never shows a misleading zero.** Every card is
a small, fixed number of cheap indexed `COUNT`/`GROUP BY` queries, never a
loop over students or classes.

## 3. Academic reports

`AcademicReport` (`/reports/academic`, tabs `runs|student|subject|class`) —
result-run summary, student performance, subject performance, class/arm
performance. Reads only already-compiled, **frozen** `StudentResult`/
`StudentSubjectResult` columns (percentages, positions, grade-code
snapshots) from M15 — never recomputes a grade or a rank, and a
locked/published run's numbers are read-only here exactly as everywhere
else in the app. `studentPerformance()`/`subjectPerformance()`/
`classPerformance()` only ever consider `Published`/`Locked` runs — a draft
or compiled-but-unreviewed run's numbers are not yet a fact worth reporting.

## 4. Attendance reports

`AttendanceReport` (`/reports/attendance`, tabs `student|class|trend`) —
per-student counts, per-class/arm aggregate, a date-based trend. M13 itself
deferred all analytics (`docs/attendance-management.md` §"Deferred"), so
this is the first place that data is aggregated. Only `submitted` (locked)
registers are ever counted — an in-progress draft register is not yet a
fact. "Present" always means `AttendanceStatus::isAttending()` (Present +
Late together), the same rule used everywhere else attendance is
summarised — a school reading "days present: 2" for one present + one late
mark is intentional, not a bug.

## 5. Fee reports

`FeeReport` (`/reports/fees`, tabs `summary|outstanding|payments`) —
collection summary, per-student outstanding balances, payment activity
(including Paystack-originated payments, M20). Every total is computed via
SQL `SUM()`/`bcmath`, never a PHP loop over `FeeStatementBuilder::
statementFor()` per student (that call is fine for one student's statement
page, not for a school-wide aggregate). `FeePayment::notVoided()` is always
applied.

`outstandingBalancesQuery()` folds the allocated-amount-per-charge in as a
correlated SQL subquery (`SELECT SUM(...) FROM fee_payment_allocations
WHERE student_fee_charge_id = student_fee_charges.id AND ...`), summed with
a `CASE` per charge and grouped per student, then filters with `HAVING
total_outstanding > 0` — genuinely only students who still owe something
ever appear, computed by the database in one query rather than a second
batched per-page lookup. `collectionSummary()`'s outstanding figure relies
on the already-enforced M19 invariant that an allocation never exceeds its
charge's own outstanding balance, so no `GREATEST()`/`MAX()` SQL (which
differs between MySQL and SQLite) is ever needed to floor-clamp a row.

## 6. Student and staff reports

`StudentReport::enrollmentSummary()` (`/reports/students`) — total, by
status, by gender, by level, by arm. Aggregate-only: the existing
`/students` list already provides the per-student roster, so this never
re-lists individual students. `StaffReport::summary()` (`/reports/staff`) —
total, by status, by subject, and a "workload" figure that is only ever
*how many active class assignments* a teacher currently holds — a
scheduling fact already stored on `TeacherAssignment`, explicitly never an
HR/payroll metric (the spec excludes HR/payroll analytics by name).

## 7. CBT reports

`CbtReport` (`/reports/cbt`) — examination summary (attempts, completed,
pass rate, average %) and a per-examination attempt drill-down. **Staff-only
surface** — gated `cbt.view`, no student-facing report route exists at all.
Staff already see attempt scores/percentages/pass-fail unconditionally in
the existing M23 `ExaminationAttemptController` (it never calls
`ExamAttempt::isResultVisible()`), and this report mirrors that exactly: it
reads persisted `score`/`percentage`/`passed` columns directly, never
recomputes them, and never touches `ExaminationQuestionOption.is_correct`.
Because this surface is staff-only by construction, no result-release
gating logic was needed here — M23's result-release rule only ever governs
what a *student* may see, and no student ever reaches this route.

## 8. Promotion, learning materials, communication

`PromotionReport` (`/reports/promotion`) — promotion batch history and
graduation history, read directly from M21's `PromotionBatch`/
`PromotionRecord`/`students.status`/`graduated_*` columns. No placement- or
promotion-recommendation logic of any kind — this only reports what already
happened. `LearningMaterialReport::summary()` (`/reports/learning-
materials`) — count, by subject, by type, 10 most recent uploads; M22 has no
view/download analytics to report on (deferred per
`docs/learning-materials.md`), so this never attempts "who viewed this."
`CommunicationReport::summary()` (`/reports/communication`) — thread counts
by status/category, published-announcement count, 10 most recent threads; a
lightweight administrative summary, not a BI system around communications.

## 9. Platform reports

`PlatformReport` + `Platform\ReportController` (`/admin/reports`) —
school overview (total/active/suspended, 10 most recently created), module
adoption (per-module enabled-school count across all 16 catalogue modules),
platform usage (total users, students, teachers across every school, recent
audit activity). Gated by the exact same `SchoolPolicy::viewAny`
(`isPlatformAdmin()`) check `Platform\SchoolController` already uses — **not
a new permission**: this is the same "may this user administer the platform
at all" question the existing Schools screen already answers, not a
separate reporting capability, and it is **not** a tenant bypass — a
platform admin views this deliberately cross-school, bounded, aggregated
screen, then picks a school and uses that school's own normal reports for
detail.

`moduleAdoption()` derives its counts from the override-only
`school_modules` table (`DB::table('school_modules')->select('module',
'enabled')->get()->groupBy('module')`) combined with each
`Module::enabledByDefault()` — `enabledCount = enabledByDefault ? (total -
overrides) + enabledOverrides : enabledOverrides` — one query plus
in-PHP arithmetic per module case, never a per-school loop.
`platformUsage()` wraps its two genuinely cross-school counts
(`Student::count()`, `Teacher::count()`) in
`TenantContext::runWithoutScope()` — the one sanctioned escape hatch, used
narrowly and only for these two counts, never a silent bypass elsewhere.

## 10. Filters

A consistent, server-side filter set per report — session, period, level,
arm, subject, date range, status, as each report's own data supports (a
report never shows a filter it can't actually apply — Fee payments, for
example, only filter by method/date range, since `FeePayment` itself
carries no session/period/level/arm). `App\Http\Controllers\Reports\
Concerns\BuildsReportFilterOptions` builds the session/level/subject
dropdown options, mirroring the exact same query shape
`ResultRunController`/`ExaminationController` already use for their own
filter dropdowns. Filters are read from the request and applied entirely
server-side (`array_filter([...])` in each controller's private `filters()`
method) — a client can never bypass a report's authorization or
tenant/teacher scoping by editing the query string; the school-id-injection
and IDOR tests in `tests/Feature/Reports/TenantIsolationTest.php` prove this
directly (a `?school_id=` parameter is simply never read by any filter
array).

## 11. Export

Every report with a meaningful tabular export exposes CSV via
`response()->streamDownload()`. Every Report class method that returns a
`LengthAwarePaginator` also exposes a sibling `*Query()` method returning the
same filtered/tenant/teacher-scoped `Builder`, unpaginated — the export
action `chunk(200, ...)`s that exact query rather than loading a whole
school's data into memory, mirroring the pattern M24/M25/M26 already
established for their own exports. The already-small, bounded aggregate
reports (subject/class performance, student/staff/materials/communication
summaries) iterate their already-in-memory `Collection` directly — chunking
would add nothing there. Every export re-runs both authorization checks
(`reports.export` **and** the report's own domain permission) independently
of the index action — an export URL is never reachable with looser
authorization than the page it exports. No export ever writes a Paystack
secret, password, or token to a row.

## 12. Permissions

Two new, deliberately coarse permissions:

- `reports.view` — may this user open the Reports area at all.
- `reports.export` — may this user export a report as CSV.

**Neither alone unlocks anything.** Every controller action composes
`reports.view`/`.export` with that report's own pre-existing domain
permission (`result.view`, `attendance.view`, `fees.report`, `student.view`,
`staff.view`, `cbt.view`, `promotion.view`, `material.view`,
`communication.view`) — e.g. Academic reports require **both**
`reports.view` **and** `result.view`. This is why a Bursar (who holds
`reports.view`) still cannot open Academic reports: Bursar never holds
`result.view`. Role grants, added to the existing M4 bundles:

- School Admin → automatic (`Permission::all()`).
- Principal → `reports.view` + `reports.export` (plus every domain
  permission Principal already held).
- Bursar → `reports.view` + `reports.export` (Bursar already holds
  `fees.report`, `student.view`, `staff.view` from M4 — so Bursar can open
  Fee reports and Student/Staff reports, never Academic/Attendance/CBT).
- Teacher / Staff → `reports.view` only (no export) — combined with each
  role's own already-broad set of `*.view` permissions, a Teacher can open
  most report areas (never Fees — no role below Bursar holds
  `fees.report` anywhere in this app), a Staff member's Teacher-shaped
  export attempt is refused since Staff never holds `reports.export`.
- Parent / Student → neither permission — every report route 403s
  immediately at `->authorize('reports.view')`, before any domain check
  even runs.

Platform reports use `SchoolPolicy::viewAny` (§9), not `reports.view` — a
school-scoped permission a school user holds says nothing about platform
administration, and the platform route never even calls
`Gate::authorize('reports.view')`.

## 13. Teacher scoping

A Teacher without a domain's own "manage" permission
(`Permission::ResultManage`, `AttendanceManage`, `CbtManage`) is scoped to
only the classes/subjects they hold an active M11 `TeacherAssignment` for —
never a separate authorization system, just `ReportAuthorizer::
levelIdsFor()`/`subjectIdsFor()` (querying the exact same
`teacher_assignments` table every other module's own authorizer already
does) applied as a `whereIn(...)` inside each report's private
`scopeToTeacher()`. A Teacher holding the domain's "manage" permission
bypasses scoping entirely and sees the whole school, matching the rule
every other manage-tier permission already follows elsewhere in this app. A
Teacher with **no** active assignment at all sees an empty report, not an
error.

## 14. Tenant isolation

Every report relies on the ordinary `SchoolScope` global scope already
applied by `BelongsToSchool` — **no report ever writes a manual
`where('school_id', …)`**, and no report ever reads a school id from the
request. `PlatformReport` is the sole, explicit exception (§9): every other
class's cross-school safety is the same global scope every other milestone
already depends on. `CbtReportController::attempts({examination})` resolves
its route parameter through the tenant-scoped `Examination::findOrFail()` —
an id belonging to another school 404s, proven by an explicit
cross-school-IDOR test. `tests/Feature/Reports/TenantIsolationTest.php`
covers: another school's students/results never appearing in a report,
`?school_id=`/`?school=` query-string injection having no effect, a
same-school-only fee outstanding-balance figure, and a platform-report route
staying unreachable from a normal school user's own request context.

## 15. Performance

Every report is SQL aggregates (`SUM`/`COUNT`/`AVG`/`GROUP BY`), indexed
`whereIn`, eager loading, and pagination — never a per-student or per-class
query loop, never an unbounded `->get()` over a whole table. Where a
`selectRaw()`/`groupBy()` query's projection doesn't include a relation's
foreign key (this app runs with `Model::shouldBeStrict()` /
`preventAccessingMissingAttributes()` — see `CLAUDE.md`), the report looks
the small number of distinct related names up directly by id
(`AcademicReport::classPerformance()`) rather than eager-loading through a
column that was never selected — one small bounded extra query, never N+1.
Query-count regression tests (`tests/Feature/Reports/PerformanceTest.php`)
prove the dashboard, the student enrollment report, and both an
admin's-eye and a Teacher's-eye academic report stay flat as row counts
grow, mirroring the exact pattern `AuditPerformanceTest`/
`CbtPerformanceTest`/`EntryAssessmentPerformanceTest` already established.

### The `->toBase()` enum-pluck gotcha

Grouping an Eloquent query by an enum-cast column and calling
`->pluck($value, $enumCastColumn)` throws, because Eloquent casts the
plucked array *key* to the enum instance, and a PHP array cannot use an
enum instance as a key. Every place this milestone groups by an enum-cast
column (`status`, `gender`, `category`, `type` — nine call sites across
`StudentReport`, `StaffReport`, `DashboardReport`, `PlatformReport`,
`CommunicationReport`, `LearningMaterialReport`) inserts `->toBase()`
immediately before `.pluck(...)`: it strips Eloquent's hydration/casting
while still preserving the already-applied `SchoolScope` global-scope
`WHERE` clause, since `toBase()` internally calls `applyScopes()` before
converting to the plain query builder. Any future report that groups by an
enum-cast column needs the same fix.

## 16. UI

`resources/views/reports/*` (one folder per domain) and
`resources/views/platform/reports/index.blade.php` — the existing
Blade + Tailwind + Alpine component set (`<x-card>`, `<x-badge>`,
`<x-empty-state>`, `<x-button>`), a `?tab=` query-param switch for domains
with more than one named report, an Alpine `x-model` level→arm cascading
filter, pagination via the existing paginator views, empty/loading/error
states matching every other milestone's own pages. No chart library — a
trend or percentage is rendered as a plain CSS width bar
(`style="width: {{ $percentage }}%"` inside a fixed-height rounded div),
exactly the level of visualization the spec allows without adding a
dependency.

## 17. Deliberately not built

Per the spec's explicit exclusions: a data warehouse, a BI platform, Power
BI integration, Elasticsearch/OpenSearch, Redis for analytics, complex ETL,
real-time streaming analytics, predictive analytics, AI/ML analytics,
advanced anomaly detection, subscription billing or entitlement engines,
scheduled report delivery or email report automation, a user-facing
drag-and-drop report/dashboard builder, a complex charting framework,
HR/payroll analytics, and no new third-party reporting package (Spatie or
otherwise) — every report is hand-written Eloquent/SQL against the existing
schema.

## 18. Future extension points

- Every Report class's `*Query()` companion methods are already
  chunk-ready, so a future scheduled CSV-to-email delivery (if ever built as
  its own, separately-scoped milestone) would not need to touch the report
  classes themselves.
- `ReportAuthorizer` is intentionally generic (`levelIdsFor()`/
  `subjectIdsFor()`) — a future report domain needing the identical
  Teacher-assignment scoping can reuse it directly rather than writing a
  sixth copy of the same query.
- The `Module::Reports` case's own `dependencies()` entry
  (`[self::Academics]`) is deliberately minimal — Reports itself never
  requires Fees/Attendance/CBT/etc. to be enabled, since each report area
  already independently no-ops (an empty summary, not an error) when its
  own underlying module is off. Only the Fees/Attendance/CBT/etc. **routes**
  additionally require their own module via `module:<domain>` middleware —
  see §5 of `routes/web.php`'s Reporting block.
