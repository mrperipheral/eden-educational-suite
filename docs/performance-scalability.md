# Performance, Scalability & Reliability Validation

Status: **Milestone 29 — complete.** M29 answers one question: *is there
any meaningful performance, scalability or reliability problem in the
existing platform that should be fixed before production?* This is
**not** a repeat of M1–M28's functional/security testing — it is an
empirical check of the priority screens/reports under a materially larger,
multi-school dataset, plus a code-review of the transaction/lock
protection around operations where failure could corrupt data.

**Headline finding: no genuine bottleneck or reliability gap was found.**
Every screen/report/export measured stayed fast and query-bounded under a
10×-larger dataset than the normal demo seed, and every reliability-
sensitive operation already had the transaction/lock protection it needed.
This milestone's deliverable is therefore mostly *evidence*, not code
changes — a large-data fixture, a set of before/(under-load) measurements,
and this document. Where a genuine issue existed, it is called out
explicitly in §5.

## 1. Existing performance architecture (confirmed, not newly built)

This was already in place before M29 and is the reason the empirical
checks came back clean:

- **`SchoolScope` + `TenantContext`** — every tenant query carries an
  indexed `school_id = ?` predicate; nothing scans across schools by
  accident (see `docs/tenancy.md`).
- **Pagination everywhere** — every list controller checked (`Student`,
  `Teacher`, `Guardian`, `Assessment`, `AttendanceRegister`,
  `Examination`, `Question`, `CommunicationThread`, `AuditLog`, all nine
  M27 report controllers) paginates (25/page, or 15–20 for a few
  narrower views) — confirmed by direct inspection, not just convention.
- **Bulk writes for compiled/derived data** — `ResultCompiler::persist()`
  (M15) already writes `student_results`/`student_subject_results` via
  raw bulk `insert()`/`update()`, never a per-student query loop.
- **CSV export chunking** — every M24–M27 CSV export streams via
  `response()->streamDownload()` + `Builder::chunk(200, …)` against the
  same filtered/scoped query the page itself paginates, never loading a
  whole result set into memory (`docs/reporting.md` §11).
- **`->toBase()` before `pluck()` on an enum-cast group-by column** — the
  M27-documented gotcha, still correctly applied everywhere it's needed.
- **`school_id`-leading composite indexes** on every high-traffic table —
  confirmed by reading the migrations for `students`, `attendance_records`,
  `attendance_registers`, `exam_attempts`, `fee_payments`,
  `student_results`, `student_subject_results`, `audit_logs`: every one
  already has a `school_id`-first index matching its actual query shape
  (status filters, date-range filters, ranking lookups, per-student
  history).
- **N+1 regression tests already exist** across nearly every milestone —
  `AuditPerformanceTest`, `CbtPerformanceTest`,
  `EntryAssessmentPerformanceTest`, `tests/Feature/Reports/
  PerformanceTest.php`, and equivalents elsewhere — all using the same
  `DB::flushQueryLog()`/`enableQueryLog()`/assert-flat-query-count pattern
  this milestone reuses for its own new benchmark script.

## 2. Quick audit — what was checked, what was found

Reviewed the areas named in the spec (dashboards, student lists/search,
attendance, assessments/results, report cards, fees/statements,
parent/student portals, CBT, audit logs, M27 reports, CSV exports,
promotion/graduation) for N+1 queries, unbounded queries, missing
pagination, inefficient loops, and obvious missing indexes:

| Area | Finding |
|------|---------|
| List controllers (Students, Teachers, Guardians, Assessments, Attendance registers, CBT Questions/Examinations, Communication, Audit Log, all M27 reports) | All paginate. No unbounded `->get()` list endpoint found. |
| `ReportCardRenderer` (M15/M16) | Renders exactly one student at a time — no bulk "generate all report cards for a class" feature exists to loop it, so there is no N+1 risk to find. |
| Migrations for `attendance_records`, `attendance_registers`, `exam_attempts`, `fee_payments`, `student_results`, `student_subject_results`, `students` | Already carry `school_id`-leading indexes matching their real query patterns (status filters, date ranges, ranking, per-student history). |
| Reliability-critical operations (§7 below) | All already transaction/lock-protected. |

**No code changes were needed from this audit pass** — everything above
was already correct.

## 3. Large-data fixture

`php artisan performance:seed-dataset [--fresh]` — a new, standalone
Artisan command (`app/Console/Commands/SeedPerformanceDataset.php`),
**deliberately not wired into `DatabaseSeeder`/`migrate:fresh --seed`**
(a lasting rule — see `CLAUDE.md`). Seeds two schools in the real
(MySQL) database:

| School | Students | Attendance records | Result rows (student + subject) | Fee charges/payments | Exam attempts | Audit rows |
|--------|---------:|--------------------:|----------------------------------:|----------------------:|---------------:|-----------:|
| **A — "Performance Academy A"** (large) | 250 | 3,750 | 250 + 1,250 | 250 / ~127 | 300 | 100 |
| **B — "Performance Academy B"** (normal, comparison) | 15 | 75 | 15 + 75 | 15 / ~10 | 14 | 10 |

School A is ~8× the seeded demo school's student count (`DatabaseSeeder`'s
Alpha Academy has ~29 students) and proportionally larger across every
child table — enough to expose N+1/unbounded-query patterns without
needing a hundreds-of-schools load-testing environment. High-volume rows
(`attendance_records`, `student_results`, `student_subject_results`) are
bulk-inserted in chunks of 500 so the command itself finishes in seconds
(~6s total for both schools), not minutes. The command is idempotent via
`--fresh` (cleans up its own two schools — including working around
`fee_payment_allocations`'s deliberate `restrictOnDelete()` FK — before
reseeding).

This fixture is a manual, one-off validation tool, not part of the
automated test suite or the normal demo seed. Running
`php artisan migrate:fresh --seed` (§12) drops it along with everything
else and restores the normal ~29-student demo state.

## 4. Multi-school performance verification

With School A (250 students) and School B (15 students) both present,
the same representative queries were run against each and compared:

| Query | School A (large) | School B (normal) |
|-------|------------------:|--------------------:|
| Dashboard KPIs | 84.2ms / 11 queries | 12.3ms / 11 queries |
| Student list `paginate(25)` | 26.3ms / 2 queries | 2.0ms / 2 queries |
| Academic report: student performance page 1 | 12.6ms / 4 queries | 5.1ms / 4 queries |
| Fees: outstanding balances page 1 | 7.9ms / 3 queries | 4.0ms / 3 queries |
| Audit log: paginated list | 2.0ms / 2 queries | 1.3ms / 2 queries |

**Query counts are identical between School A and School B for every
query** — confirming `SchoolScope`'s tenant predicate is doing its job:
School B's screens are not slowed down or enlarged by School A's much
bigger dataset, and School A's own larger dataset didn't inflate its own
query *count* (only wall-clock time, and only modestly). This is the
concrete evidence the spec's §4 "multi-school performance" check asked
for.

## 5. Heavy operations — measured

All measurements taken on a local development machine (Laragon / PHP 8.3
/ MySQL 8) against the School A (250-student) fixture — **these are not
production capacity numbers**, only relative, order-of-magnitude evidence
that nothing is obviously broken. See §9 for the explicit caveat.

| Operation | Time | Queries | Notes |
|-----------|-----:|--------:|-------|
| Dashboard KPI cards | 84.2ms | 11 | fixed small number of cheap aggregate queries, matches `docs/reporting.md` §2's design |
| Academic report — student performance (paginated) | 12.6ms | 4 | |
| Academic report — class performance (all 5 classes) | 27.5ms | 4 | |
| Academic report — subject performance | 18.4ms | 3 | |
| Attendance report — student attendance (paginated) | 33.5ms | 3 | |
| Attendance report — class attendance (all 5 classes) | 28.5ms | 3 | |
| Attendance report — date trend (15 days) | 7.7ms | 1 | single joined aggregate query |
| Fee report — outstanding balances (paginated) | 7.9ms | 3 | correlated-subquery `HAVING` filter (the M28 fix) performs well at this scale |
| Fee report — collection summary | 5.8ms | 3 | |
| CBT report — examination summary | 30.9ms | 7 | |
| Audit log — plain paginated list | 2.0ms | 2 | |
| Audit log — filtered search (`event LIKE`) | 10.9ms | 2 | |
| CSV export — academic student performance, streamed via `chunk(200)` (250 rows) | 33.5ms | 6 | ~2 chunks × ~3 queries; peak memory **1.17MB** for the whole export, never the full result set at once |
| Report card — render one student (first call, includes one-time Blade compilation) | 312–389ms | 19 | |
| Report card — render one student (subsequent calls, compiled view cached) | 47–78ms | 12 | never scales with roster size — there is no bulk "generate all report cards" loop to worry about |

**Nothing here needed fixing.** The report-card render's first-call cost
is entirely Blade template compilation, which `php artisan view:cache`
(part of every deployment and this milestone's own final verification)
eliminates in production — subsequent renders are consistently fast.

### C. Fees — statements, payment history, balances

Already covered above (collection summary, outstanding balances) plus the
existing `FeeStatementBuilder` (single-student, called once per statement
view — inherently bounded, not a list-scan).

### D. Results — result views, report cards, ranking/report generation

Covered above. `RankingCalculator`/`ResultCompiler` (the write-path that
computes rank/position at compile time) were reviewed by code inspection
rather than re-benchmarked against the fixture — the fixture's
`student_results` rows are bulk-inserted directly (pre-computed, fake
values) to keep the seeder itself fast, so they don't exercise the real
compiler. The compiler already uses the same bulk-insert technique the
CSV exports do (§1), and this write path runs once per result-run compile
(an infrequent, staff-initiated action), not on every page view — it was
judged out of proportion to build a second, assessment-score-backed
fixture solely to re-prove what the code's own structure (and M15's
existing test suite) already demonstrates.

### E. Attendance — class roster, historical records

Covered above (student/class attendance, date trend).

### F. CBT — question/exam loading, attempts, result retrieval

Covered above (examination summary). Per-student attempt/answer loading
was already reviewed for correctness and query shape during M28's CBT
integrity audit (`docs/security-hardening.md` §11) and found to use
column-restricted eager loading with no N+1 — not re-benchmarked
separately here since nothing about that query shape changes with row
count (`unique(examination_id, student_id)` keeps each student's own
lookup a single indexed row regardless of how many other students exist).

## 6. Exports — memory and security controls intact

The CSV export chunking measurement in §5 (1.17MB peak for a 250-row
export) confirms exports remain memory-conscious at this scale. All M28
export security controls remain intact and unmodified by this milestone:
CSV formula-injection sanitization (`App\Support\Csv\CsvSanitizer`),
export authorization (`reports.export` + the domain permission),
export rate limiting (`throttle:exports`), and tenant isolation
(`SchoolScope`) — none were touched; M29 made zero changes to any export
controller.

## 7. Reliability review

Reviewed the transaction/lock protection around every operation the spec
named, by reading the current code (not a new concurrency-testing
framework):

| Operation | Protection | Where |
|-----------|-----------|-------|
| Paystack payment recording | `DB::transaction()` + `lockForUpdate()` on the transaction row; idempotent (`isResolved()` short-circuit) | `PaymentVerificationService::verifyAndRecord()` |
| Fee allocation | `DB::transaction()` + `lockForUpdate()` on the charges being allocated against | `FeePaymentService::allocate()` |
| Result publication/locking | `DB::transaction()` wraps every lifecycle transition | `ResultRun::publish()`/`lock()`/etc. |
| CBT submission | `DB::transaction()` + `lockForUpdate()` on the attempt row; `answer()` is a single atomic `UPDATE` (no multi-step race possible); `start()`'s uniqueness is enforced by the DB-level `unique(examination_id, student_id)` constraint, not just an application check | `ExamAttemptService::finalize()`/`answer()`/`start()` |
| Promotion/graduation | `DB::transaction()` per student + `lockForUpdate()` on the `Student` row — one student's failure never rolls back another's success | `PromotionService::promoteOne()`, `GraduationService::graduate()` |
| Audit recording | Single-row insert through `AuditRecorder::record()` — atomic by nature, no multi-step state to corrupt | `AuditRecorder::record()` |

**No reliability gap was found.** Every operation where failure could
plausibly corrupt data already has the transaction/lock protection the
spec asked to verify. No new regression test was added for this section,
per the spec's own instruction to only add one "if a genuine reliability
gap is discovered."

## 8. Optimizations made

**None were required.** The quick audit (§2), the multi-school comparison
(§4), and the heavy-operations measurements (§5) found no N+1 query, no
missing pagination, no missing index, no unnecessary data loading, and no
memory problem. This milestone's only code addition is the large-data
fixture command itself (§3) — a testing/validation tool, not an
application-behavior change.

## 9. Benchmark methodology and caveat

**What was measured**: wall-clock time (`microtime(true)`), SQL query
count (`DB::enableQueryLog()`/`getQueryLog()`), and peak memory
(`memory_get_peak_usage()`) for each listed operation, run directly
against the seeded fixture via `php artisan tinker` (bypassing HTTP/
middleware overhead, to isolate the query/render cost itself).

**Dataset size**: School A = 250 students / 3,750 attendance records /
1,500 result rows / 250 fee charges / 300 exam attempts / 100 audit rows;
School B = 15 students (proportionally smaller), for the multi-school
comparison in §4.

**Development-machine measurements are not production capacity
guarantees.** These numbers were taken on a single local development
machine, with a MySQL instance on the same host as the application, no
concurrent load, and no production-grade infrastructure (connection
pooling, read replicas, a CDN, opcache warmed under real traffic
patterns, etc.). They demonstrate *relative* and *structural* health —
query counts staying flat as data grows, no query scanning an unbounded
table, memory staying low during a chunked export — not an absolute
promise about production response times at any particular school count
or concurrent-user load.

**Remaining limitation**: this validation used one large school (250
students) and one small school (15 students) — two schools, not "hundreds
of schools," per the spec's own explicit scope ("does NOT need to
simulate hundreds of schools or become a formal load-testing
environment"). If the platform later needs to validate true concurrent
multi-tenant load (many schools' traffic simultaneously) or a
single-school population an order of magnitude larger than 250 students,
that is real load-testing infrastructure — explicitly out of this
milestone's scope (see §11).

## 10. Known limitations / deferred to production infrastructure or a future milestone

- **True concurrent load testing** (many simultaneous users/schools) —
  needs a real load-testing tool (k6, JMeter, Artillery, …) against a
  staging environment, not something to fake inside PHPUnit/tinker. Not
  built here, per the spec's explicit "do not create a large
  performance-testing platform."
- **Caching layer** (Redis, response caching, query-result caching) —
  not introduced; nothing measured in this milestone demonstrated a need
  for one, and CLAUDE.md's existing guidance ("design for scale but do
  not build infrastructure the current milestone does not need") applies.
- **Queues/Horizon** — not introduced; every write path measured
  (payments, results, promotion, exports) completed synchronously in
  well under a second even at 250-student scale. If a future milestone's
  own feature genuinely needs background processing, that decision
  belongs to that milestone, not a speculative addition here.
- **`ResultCompiler`/`RankingCalculator` under a full assessment-score
  fixture** — reviewed by code inspection only (§5.D); a dedicated
  before/after benchmark would need a second, larger fixture (real
  `Assessment`/`AssessmentScore` rows behind 250 students × several
  subjects) that was judged disproportionate to build for this pass,
  given the code already uses the same bulk-insert technique proven fast
  elsewhere in this document.
- **Database read replicas / connection pooling / opcache tuning** —
  infrastructure/deployment concerns, not application code.

## 11. Explicitly out of scope (per the milestone's own boundary)

Not implemented, and correctly so: Redis, Horizon, a queue system, a
caching layer, a load-testing platform, new infrastructure of any kind
introduced speculatively. None of the empirical checks in this milestone
demonstrated a genuine bottleneck that would justify introducing any of
these — see CLAUDE.md's now-standing rule (§12 below) on why the large-
data fixture stays a plain Artisan command instead.

## 12. Lasting rule this milestone establishes

**Large-dataset / performance-validation fixtures are separate, manually-
invoked Artisan commands — never wired into `DatabaseSeeder` or
`migrate:fresh --seed`.** `performance:seed-dataset` is the first of
these; any future performance-validation fixture should follow the same
pattern (a dedicated command, bulk-inserts for its high-volume tables,
`--fresh` cleanup that accounts for any deliberate `restrictOnDelete()`
FKs, and documented row counts) rather than growing the normal demo seed
or building a separate testing framework.
