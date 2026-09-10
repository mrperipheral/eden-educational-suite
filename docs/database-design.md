# Database Design

Status: Milestone 1. **No domain tables exist yet.** This document records the
conventions every future migration follows.

## Current schema

Only the Laravel framework tables, from the default migrations:

| Table | Purpose |
|-------|---------|
| `users` | authentication identities (domain profile fields come later) |
| `password_reset_tokens`, `sessions` | auth/session plumbing |
| `cache`, `cache_locks` | `CACHE_STORE=database` |
| `jobs`, `job_batches`, `failed_jobs` | `QUEUE_CONNECTION=database` |
| `migrations` | Laravel bookkeeping |

Engine: MySQL 8 / MariaDB, InnoDB, `utf8mb4`.

## Multi-tenant conventions (to apply from the next milestone on)

1. **`school_id` on every school-owned table.**
   `$table->foreignId('school_id')->constrained()->cascadeOnDelete();`
   Reference data that is genuinely global (e.g. country list) is the only
   exception and must be called out in review.

2. **Composite indexes lead with `school_id`.** Every query is tenant-scoped, so
   the tenant key belongs first:
   `$table->index(['school_id', 'created_at']);`
   `$table->unique(['school_id', 'admission_number']);`
   Uniqueness is almost always *per school*, never global.

3. **Foreign keys stay within the same tenant.** A `class_id` on a table that
   also has `school_id` must point at a `classes` row with the same `school_id`.
   Enforced by the `BelongsToSchool` global scope at the application layer and by
   validation rules scoped to `TenantContext::id()`.

4. **No cross-tenant leakage through nullable FKs or polymorphic types** without
   an explicit tenant check.

5. **Timestamps** on every table. **Soft deletes** only where there is a real
   recovery/audit need (not by default).

6. **Money** stored as integer minor units (kobo) + a currency column, never
   floats. Nigeria-first but currency is explicit from day one.

7. **Enums** as short strings with a DB-level `check`/`enum` or an app cast, not
   magic integers.

8. **Migrations are additive and reversible.** No editing a shipped migration;
   add a new one. `down()` is implemented.

## Indexing checklist for new tables

- [ ] `school_id` first column of the primary lookup index
- [ ] FK columns indexed (Laravel does this for `constrained()`)
- [ ] columns used in `WHERE` / `ORDER BY` on list screens covered
- [ ] unique constraints scoped to `school_id`
- [ ] no unbounded `SELECT *` list endpoint without pagination

## Not yet designed (later milestones, will be added here)

`schools`, roles/permissions, students, guardians, staff, classes/sections,
subjects, enrolment, attendance, assessments/results, fees/invoices/payments,
CBT, audit log. Each gets an entry here when built.
