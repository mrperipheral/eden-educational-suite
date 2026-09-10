# Database Design

Status: Milestone 8. Tenant + roles + onboarding + school settings + module
activation + academic foundation. School-owned tables: `school_settings` (M6),
`school_modules` (M7), and the academic structure — `academic_sessions`,
`academic_periods`, `academic_levels`, `level_arms`, `subjects`, `level_subject`
(M8). No student / guardian / staff / timetable / attendance / results tables
yet. This document records the conventions every future migration follows.

## Current schema

| Table | Purpose |
|-------|---------|
| `users` | auth identities. `id, name, email (unique), email_verified_at, password, status, is_platform_admin, remember_token, timestamps` |
| `schools` | tenant root. `id, name, slug (unique), status, timestamps` |
| `school_user` | User↔School membership + per-school `role`. PK `(school_id, user_id)`, index `(school_id, role)`, cascade both ways |
| `school_settings` | per-school config (1:1). `school_id` unique. School-owned. Profile (contact + address), branding (`logo_path`, `brand_color`), regional (`timezone, locale, currency, date_format, week_starts_on, academic_year_start_month`). |
| `academic_sessions` | a school's academic years. School-owned. `unique(school_id, name)`, `index(school_id, starts_on)`. One `is_current` per school. |
| `academic_periods` | terms / semesters within a session. School-owned **+** `academic_session_id`. `unique(session_id, name)`, `unique(session_id, position)`. One `is_current` per session. |
| `academic_levels` | classes / year groups. School-owned. `unique(school_id, name/code/position)`. |
| `level_arms` | streams within a level. School-owned **+** `academic_level_id`. `unique(level_id, name/code/position)`. |
| `subjects` | school subjects. School-owned. `unique(school_id, name)`, `unique(school_id, code)`. |
| `level_subject` | which subjects a level offers. School-owned. `unique(academic_level_id, subject_id)`, `index(school_id, academic_level_id)`. |
| `school_modules` | per-school feature-module on/off overrides. School-owned. `unique(school_id, module)`. Override-only — a row exists only where a school departs from the `App\Enums\Module` default. |
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

### `2026_09_13_100000_create_school_settings_table`
1:1 with `schools` (`school_id` **unique** — both the tenant key and the
constraint). `timezone` (default `Africa/Lagos`), `locale` (default `en`),
`contact_email` / `contact_phone` (nullable), `completed_at` (nullable — set on
first save, drives the onboarding checklist). School-owned (`BelongsToSchool`).
See `docs/onboarding.md`.

### `2026_09_14_100000_add_configuration_to_school_settings_table`
Milestone 6 — expands `school_settings` (still 1:1, no new index needed). Typed
columns, not a JSON blob. Adds: `address_line1/2`, `city(120)`, `state(120)`,
`postal_code(20)`, `country char(2)` default `NG`, `website_url`, `logo_path`
(**guarded** — written only by `SchoolSetting::putLogo()`), `brand_color char(7)`,
`currency char(3)` default `NGN`, `date_format(20)` default `d/m/Y`,
`week_starts_on tinyint` default `1` (0=Sun…6=Sat), `academic_year_start_month
tinyint` default `9`. Column defaults, the model `$attributes`, and
`config('school-settings.defaults')` are kept in sync. See `docs/school-settings.md`.

### `2026_09_13_100010_create_academic_sessions_table`
`name` (`unique(school_id, name)`), `starts_on` / `ends_on` (dates), `is_current`
(bool, at most one per school — enforced in `AcademicSession::makeCurrent()`).
`index(school_id, starts_on)` for the list. School-owned. M8 builds the term /
level / subject structure on top (below); the sessions table itself is unchanged.

### `2026_09_16_100000_*` — Academic Foundation (Milestone 8)
Five migrations, all school-owned (`BelongsToSchool`), all indexes leading with
`school_id` (or a tenant-scoped parent id). See `docs/academic-foundation.md` §3.

- **`academic_periods`** — `academic_session_id` FK (cascade), `name`,
  `starts_on`/`ends_on`, `position`, `is_active`, `is_current`.
  `unique(academic_session_id, name)`, `unique(academic_session_id, position)`,
  `index(school_id, academic_session_id, position)`. Any number per session.
- **`academic_levels`** — `name`, `code`, `position`, `is_active`.
  `unique(school_id, name)` / `(school_id, code)` / `(school_id, position)`.
- **`level_arms`** — `academic_level_id` FK (cascade), `name`, `code`,
  `position`, `is_active`. `unique(academic_level_id, name/code/position)`,
  `index(school_id, academic_level_id, position)`.
- **`subjects`** — `name`, `code`, `description` (nullable), `position`
  (non-unique), `is_active`. `unique(school_id, name)` / `(school_id, code)`.
- **`level_subject`** — `academic_level_id` + `subject_id` FKs (cascade),
  `school_id` (written from `TenantContext` during the sync).
  `unique(academic_level_id, subject_id)`, `index(school_id, academic_level_id)`,
  `index(school_id, subject_id)`. The only cross-model link M8 ships.

### `2026_09_15_100000_create_school_modules_table`
Milestone 7 — per-school feature/module activation. `module` (`string(40)`, an
`App\Enums\Module` value, **not** cast so an unknown id can't break a page),
`enabled` (bool). `unique(['school_id','module'])` is both the 1-per-module
constraint and the lookup index (`school_id` leads). **Override-only**: absence
of a row means "use `Module::enabledByDefault()`", so a freshly onboarded school
writes nothing. School-owned (`App\Models\SchoolModule` uses `BelongsToSchool`);
written only via `App\Support\Modules\SchoolModules`. See `docs/module-activation.md`.

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

`school_user.is_default`, holiday / calendar events, students, guardians, staff,
class/arm membership, teacher assignment, enrolment, attendance,
assessments/results, fees/invoices/payments, CBT, audit log. Each gets an entry
here when built.

Permissions and roles are **not** in the database — they are code (`App\Enums`).
