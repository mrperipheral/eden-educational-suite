# Scalability

Target: comfortably serve many thousands of schools on one Laravel application
and one shared database. **No artificial 500-school ceiling exists** — the number
is bounded only by capacity, which is addressed with well-understood levers
below, not a rewrite.

## Design rules (apply from Milestone 2 on)

### Database access
- Every list query is **paginated** (`paginate()` / `cursorPaginate()`), never an
  unbounded fetch. (The school picker already follows this — 15/page.)
- Every tenant query is **indexed with `school_id` first** (see
  `database-design.md`). `SchoolScope` adds exactly one `school_id = ?` predicate
  — the same column read replicas and future partitioning would key on.
- `EnforceTenant` costs one PK `schools` load + one indexed `school_user`
  existence check per tenant request; it loads no domain data.
- **No N+1.** Eager-load relations; `Model::preventLazyLoading()` is on outside
  production so violations surface in dev and tests.
- Prefer `select` of needed columns on hot paths; avoid `SELECT *` for wide rows.
- Aggregate in the database, not in PHP loops.
- **Consistency / conflict checks are DB queries, not PHP scans.** Timetable
  overlap detection (M12) uses one narrow indexed existence query per booked
  resource, and the publish guard is a single self-join — timetable entries are
  never loaded into PHP to find clashes. The same pattern applies to any future
  "does a conflicting row exist" rule.
- **Bulk writes are one statement.** Attendance (M13) snapshots a class roster
  with a single `AttendanceRecord::insert()` (never one insert per student), and
  bulk mark-saving loads the register's records once (`keyBy` student id) then
  writes only the changed rows — no per-student `SELECT`. Register summary
  counts come from `withCount` sub-queries on the list and from the already
  loaded collection on the detail screen. Regression tests assert bounded query
  counts for a full-class taking screen, the register list and a bulk save.
- Assessments (M14) follow the same pattern: score / completion rosters are one
  bulk `insert` at creation; the bulk score / completion sheets load the roster
  and its existing rows in a fixed number of queries (`keyBy` student id) and
  write only changed rows; list totals are `withCount` sub-queries; the score
  summary is computed from the loaded collection. Regression tests bound the
  query count for a 40-student score sheet, the assessment / assignment lists and
  a 40-student bulk save. A class of 100+ costs one snapshot insert, one roster
  read and at most one write per changed student.
- Results (M15) push this further: `ResultCompiler` reads every locked score,
  assessment and weighting item **once** each (never per student) and writes
  via chunked bulk `insert`s; class-position ranking and the overall-totals
  refresh after an adjustment use a single `UPDATE ... CASE id WHEN ... END`
  statement per 500-row chunk (`bulkUpdateById()`) instead of one `UPDATE` per
  student. A regression test proves a 12-student compile issues the **exact
  same** query count as a 2-student compile. The run index/show pages and the
  report-card view eager-load their relations and group results once per
  student in PHP rather than re-querying per row. Attendance roll-up for a
  report card is exactly 2 queries regardless of class size (reused from the
  M13 pattern above).
- The Parent Portal (M16) resolves every child through one tenant-scoped
  `Guardian` ↔ `Student` join (`ParentPortalAuthorizer`) — never "load every
  student, filter to this parent's in Blade." Regression tests prove the
  dashboard and child pages issue the **same** query count regardless of how
  many *other* students/guardians the school has, and regardless of how many
  children the signed-in parent themselves has. Assignments are paginated
  (15/page, sorted server-side via a join, never in PHP); attendance and
  report-card rendering reuse M15's already-batched
  `AttendanceSummarizer`/`ReportCardRenderer` rather than recomputing.

### Caching
- Cache expensive, read-mostly, tenant-scoped computations with a
  `school_id`-prefixed key and explicit invalidation on write.
- Config/route/view caching in production (`php artisan config:cache` etc.).
- Cache store is swappable (`CACHE_STORE`); database today, Redis when load
  justifies it — no code change required.

### Background work
- Anything slow or external (email, bulk import, report generation, result
  publishing, payment reconciliation) goes to a **queue**, not the request.
- Jobs must carry and restore their tenant context.
- Queue connection is config-driven; `database` today, Redis/SQS later.

### Files
- File storage goes through Laravel's `Storage` abstraction from day one.
- `local` disk now; swap to S3-compatible object storage via `FILESYSTEM_DISK`
  with no code change. Never build paths that assume local disk.

### Application tier
- Keep app servers **stateless**: session, cache and queue are in shared backends
  (already configured), so the app scales horizontally behind a load balancer.
- Health probes (`/up`, `/health`) support rolling deploys and autoscaling.

## Scaling levers, in the order we would reach for them

1. Add indexes / fix slow queries (ongoing).
2. `config:cache`, `route:cache`, `view:cache`, OPcache in production.
3. Redis for cache + sessions + queue.
4. Dedicated queue workers, scaled independently.
5. MySQL read replicas; route read-heavy reporting to replicas.
6. CDN + object storage for static and uploaded assets.
7. Table partitioning / archival for the largest append-only tables
   (attendance, audit log) — partition key includes `school_id`.
8. Only if ever truly required: shard the largest tenants onto a second database
   cluster — the `TenantContext` seam and config-driven connections make this
   possible without touching feature code.

## Explicitly not done now (avoid premature infrastructure)

Redis, dedicated workers, read replicas, object storage, partitioning,
microservices. The abstractions that let us adopt them later (Storage facade,
config-driven cache/queue, tenant seam, pagination discipline) are what
Milestone 1 puts in place.
