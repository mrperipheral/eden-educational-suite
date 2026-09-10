# Scalability

Target: comfortably serve many thousands of schools on one Laravel application
and one shared database. **No artificial 500-school ceiling exists** — the number
is bounded only by capacity, which is addressed with well-understood levers
below, not a rewrite.

## Design rules (apply from Milestone 2 on)

### Database access
- Every list query is **paginated** (`paginate()` / `cursorPaginate()`), never an
  unbounded fetch.
- Every tenant query is **indexed with `school_id` first** (see
  `database-design.md`).
- **No N+1.** Eager-load relations; `Model::preventLazyLoading()` is on outside
  production so violations surface in dev and tests.
- Prefer `select` of needed columns on hot paths; avoid `SELECT *` for wide rows.
- Aggregate in the database, not in PHP loops.

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
