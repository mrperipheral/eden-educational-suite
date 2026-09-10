# Database Design

Status: Milestone 4. The tenant + roles foundation exists; no school-*domain*
tables (students, staff, classes …) yet. This document records the conventions
every future migration follows.

## Current schema

| Table | Purpose |
|-------|---------|
| `users` | auth identities. `id, name, email (unique), email_verified_at, password, status, is_platform_admin, remember_token, timestamps` |
| `schools` | tenant root. `id, name, slug (unique), status, timestamps` |
| `school_user` | User↔School membership + per-school `role`. PK `(school_id, user_id)`, index `(school_id, role)`, cascade both ways |
| `password_reset_tokens`, `sessions` | auth/session plumbing |
| `cache`, `cache_locks` | `CACHE_STORE=database` |
| `jobs`, `job_batches`, `failed_jobs` | `QUEUE_CONNECTION=database` |
| `migrations` | Laravel bookkeeping |

Engine: MySQL 8 / MariaDB, InnoDB, `utf8mb4`.

### `2026_09_10_120000_add_status_to_users_table`
`users.status` — `string(20)`, default `'active'`, **indexed**. `App\Enums\UserStatus`.

### `2026_09_11_100000_create_schools_table`
Tenant root. `status` `string(20)` default `'active'` **indexed**
(`App\Enums\SchoolStatus`: `active` / `suspended`). `slug` unique, used as the
route key. Not tenant-scoped.

### `2026_09_11_100010_create_school_user_table`
Membership pivot. Composite PK `(school_id, user_id)` covers "members of school";
the `user_id` FK index covers "schools for user". No role column (later milestone).

### `2026_09_11_100020_add_is_platform_admin_to_users_table`
`users.is_platform_admin` boolean default false. Not indexed (tiny cardinality),
not mass-assignable. The platform-owner primitive — see `docs/tenancy.md` §2.

### `2026_09_12_100000_add_role_to_school_user_table`
`school_user.role` — `string(30)` **nullable** (`App\Enums\Role`: `school_admin`
/ `principal` / `bursar` / `teacher` / `staff` / `parent` / `student`). One role
per (user, school); `null` = member with no permissions. Index `(school_id, role)`
for the "members with role X" query. Not mass-assignable — written only via
`User::joinSchool()` / `assignRoleInSchool()`. See `docs/authorization.md`.

## Multi-tenant conventions (ACTIVE — enforced by `BelongsToSchool` from M3 on)

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
   validation rules scoped to `TenantContext::id()`. Never add `school_id` to a
   model's `$fillable` — the `BelongsToSchool` trait is the only writer.

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

`school_user.is_default`, students, guardians, staff, classes/sections,
subjects, enrolment, attendance, assessments/results, fees/invoices/payments,
CBT, audit log. Each gets an entry here when built.

Permissions and roles are **not** in the database — they are code (`App\Enums`).
