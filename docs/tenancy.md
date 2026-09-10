# Multi-School Tenant Isolation

Status: **Milestone 3 — complete.** One Laravel app, one shared database, logical
isolation keyed by `school_id`. This document is the reference for how isolation
is enforced and how to write tenant-aware code.

## 1. The pieces

| Concern | Where |
|---------|-------|
| Tenant root | `schools` table / `App\Models\School` |
| Membership | `school_user` pivot / `User::schools()` |
| Platform owner | `users.is_platform_admin` / `User::isPlatformAdmin()` |
| Active tenant (per request) | `App\Support\Tenancy\TenantContext` (scoped singleton) |
| Context resolution | `App\Http\Middleware\EnforceTenant` (alias `tenant`) |
| School picker / switcher | `App\Http\Controllers\SchoolContextController` — `GET/POST /school` |
| Model protection | `App\Support\Tenancy\Concerns\BelongsToSchool` + `SchoolScope` |
| Authorization | `App\Policies\SchoolPolicy` |
| Failure modes | `MissingTenantContextException`, `TenantMismatchException` |

```
Authenticated User
      │  (auth · verified · active)
      ▼
EnforceTenant middleware ──► no usable school ──► GET /school (picker)
      │  resolves & validates
      ▼
TenantContext::set(School)
      │
      ▼
BelongsToSchool models ──► SchoolScope reads TenantContext::idOrFail()
      │                      + creating hook stamps school_id
      ▼
Only the active school's rows are ever visible / writable
```

## 2. Data model

### `schools`
`id, name, slug (unique), status, timestamps`. `status` is
`App\Enums\SchoolStatus` (`active` / `suspended`); only an **active** school can
be entered. Route key is `slug`. Not itself tenant-scoped. `status` and `slug`
changes are platform-admin actions; `status` is not mass-assignable.

### `school_user`
`school_id, user_id, role (nullable), timestamps`, composite PK
`(school_id, user_id)`, extra index `(school_id, role)`. `role` is the
per-school `App\Enums\Role` (Milestone 4). `ON DELETE CASCADE` both ways.
Modelled by `App\Models\SchoolUser` (the `->using()` pivot for
`User::schools()` / `School::users()`).

### `users.is_platform_admin`
Boolean, default false, **not mass-assignable**, set via seeder / future admin
tooling only. A *platform* capability, completely separate from any per-school
role. A platform admin:

- can enter **any** active school's context (and is never auto-resolved into
  one — they always pick);
- manages the `schools` resource itself (`SchoolPolicy` platform actions);
- still reads and writes school data **through** a selected `TenantContext`,
  exactly like a member — there is deliberately **no `Gate::before()`
  blanket-allow**.

## 3. `TenantContext`

Request-scoped singleton (bound in `AppServiceProvider`). Holds the active school
and nothing else.

| Method | Purpose |
|--------|---------|
| `set(School $s)` | establish the tenant from a loaded model (middleware) |
| `setId(int $id)` | establish from an id alone (queue jobs / console) |
| `id()` / `has()` | read the current tenant id |
| `idOrFail()` | read, or throw `MissingTenantContextException` |
| `school()` / `schoolOrFail()` | the `School` model (lazy-loads if only an id was set) |
| `forget()` | clear it |
| `isBypassed()` | queried by `SchoolScope` |
| `runWithoutScope(fn)` | run cross-tenant code (platform reports, maintenance) — nestable, restores state even on exception |

**Rule:** application code never reads `school_id` from the request. It asks
`TenantContext`.

## 4. `EnforceTenant` middleware (`tenant`)

Runs after `auth` / `verified` / `active`. Resolution order:

1. **Session selection** (`tenant.school_id`, written only by the school
   picker): honoured *only if* the school still exists, is active, and
   `User::canAccessSchool()` passes. Otherwise the stale value is dropped.
2. **Auto-select**: a non-platform-admin who is a member of exactly one active
   school gets it automatically (no picker).
3. **Otherwise**: redirect to `GET /school`.

The session holds only an id; access is re-checked from the database every
request, so tampering with the stored value achieves nothing (proven by
`EnforceTenantTest` / `CrossSchoolIsolationTest`).

Applied in `routes/web.php` to the tenant-scoped group: `/dashboard`,
`/members*`, `/settings/school*`, `/academic/*` (also `module:academics`),
`/students/*` (also `module:students`), `/guardians/*` (also `module:guardians`),
`/teachers/*` (also `module:staff`), `/timetables/*` (also `module:timetable`),
`/attendance/*` (also `module:attendance`).
Account-level routes (`/settings/profile`, `/settings/password`, `/school`) and
platform routes (`/admin/schools*`) do **not** use it.

## 5. `BelongsToSchool` — writing a tenant-owned model

The first real school-owned models ship with Milestone 5
(`App\Models\SchoolSetting`, `App\Models\AcademicSession`); Milestone 7 adds
`App\Models\SchoolModule`; Milestone 8 the academic structure
(`AcademicPeriod`, `AcademicLevel`, `LevelArm`, `Subject`); Milestone 9
`App\Models\Student` and `App\Models\Enrollment`; Milestone 10
`App\Models\Guardian` and `App\Models\GuardianStudent`; Milestone 11
`App\Models\Teacher` and `App\Models\TeacherAssignment`; Milestone 12
`App\Models\Timetable` and `App\Models\TimetableEntry`; Milestone 13
`App\Models\AttendanceRegister` and `App\Models\AttendanceRecord`. Child / link
models (`AcademicPeriod`, `LevelArm`, `Enrollment`, `GuardianStudent`,
`TeacherAssignment`, `TimetableEntry`, `AttendanceRecord`) carry `school_id`
*and* their parent FK(s) so a query is tenant-safe without the parent in the
join (see `docs/academic-foundation.md`, `docs/student-management.md`,
`docs/guardian-management.md`, `docs/teacher-management.md`,
`docs/timetable-management.md`, `docs/attendance-management.md`). All follow the
pattern:

```php
use App\Support\Tenancy\Concerns\BelongsToSchool;

class Announcement extends Model
{
    use BelongsToSchool;

    protected $fillable = ['title', 'body']; // NEVER include school_id
}
```

Migration:

```php
Schema::create('announcements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('school_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    // ...
    $table->timestamps();
    $table->index(['school_id', 'created_at']); // school_id leads every lookup index
});
```

What the trait guarantees:

| Operation | Behaviour |
|-----------|-----------|
| `Model::all()` / `find()` / `where()` / `count()` … | constrained to `TenantContext::idOrFail()` by `SchoolScope` |
| `Model::create([...])` | `school_id` stamped from the context; a **different** supplied `school_id` → `TenantMismatchException`; no context → `MissingTenantContextException` |
| `$model->update(['school_id' => x])` | `TenantMismatchException` — ownership is immutable |
| cross-tenant `find()` | returns `null` (route-model binding → 404) |
| cross-tenant `where(...)->update()` / `->delete()` | affects 0 rows |

Escape hatches (platform tooling / jobs only):

- `TenantContext::runWithoutScope(fn () => …)` — see everything;
- `Model::query()->withoutSchoolScope()` — drop the scope for one query;
- `Model::forSchool($school)` — target one specific school explicitly.

## 6. Authorization — `SchoolPolicy`

| Ability | Allowed to |
|---------|-----------|
| `viewAny`, `create`, `update`, `delete` | platform admins |
| `view` | members + platform admins |
| `enter` | members + platform admins, **and** the school must be active |

Auto-discovered (Laravel maps `App\Policies\SchoolPolicy` to `App\Models\School`).
Controllers call `$this->authorize('enter', $school)` etc.

**Per-school roles & permissions** (Milestone 4, `docs/authorization.md`) are
built directly on this seam: `User::hasPermission()` reads `TenantContext::id()`,
so a permission only applies inside the school in context. The `school_user`
pivot now carries a `role` column; permission checks go through the Gate
(`$user->can('member.view')`, `@can`, `->can()` route middleware), never role
names.

## 7. Console & queue guidance

`SchoolScope` throws if it runs with no context and no bypass — this is
intentional (fail closed). So:

- **Seeders / commands** that touch tenant models must either
  `TenantContext::set()/setId()` first or wrap the work in `runWithoutScope()`.
- **Queue jobs** (none exist yet) must capture `TenantContext::id()` at dispatch
  and restore it via `setId()` when they run. When the queue milestone lands,
  add a job middleware / base job for this and document it here.

## 8. Indexing rules (see also `docs/database-design.md`)

- Every school-owned table: `school_id` is the **first** column of its primary
  lookup index.
- Uniqueness is per school: `unique(['school_id', 'code'])`, never global.
- FKs between two tenant-owned tables must stay within one school (enforced by
  the scope + tenant-scoped validation).
- The `school_user` reverse lookup (`schools for this user`) rides the `user_id`
  FK index; the composite PK covers `members of this school`.

## 9. Scalability notes

- One indexed predicate (`school_id = ?`) is added to every tenant query — cheap
  and index-friendly.
- `EnforceTenant` costs: one `schools` row load + one `school_user` existence
  check (both PK/indexed). Auto-resolve does one small `LIMIT 2` membership read.
- School picker lists are **paginated** (15/page) with an optional name search.
- Nothing here needs Redis or queues. When scale demands it, the `school_id`
  predicate is exactly what read replicas / partitioning key on.

## 10. Decisions

| Decision | Why |
|----------|-----|
| Logical isolation (shared DB + `school_id`) | thousands of schools without schema sprawl (unchanged from M1) |
| Global scope that **throws** on missing context | fail closed — an unscoped query would leak every school |
| `creating` hook forces `school_id` from context (rejects mismatches) | `school_id` can never be spoofed via mass assignment or explicit set |
| `school_id` immutable on update | ownership transfer is not a real use case; blocking it removes a class of bugs |
| Many-to-many `User`↔`School` | proprietors / multi-campus staff are real; pivot stays role-free for now |
| `is_platform_admin` boolean, no `Gate::before` | minimal platform-owner primitive; they still act within a chosen tenant, not around it |
| School context in the **session**, re-validated each request | stateless-friendly, and tampering with the id is inert |
| `tenant` middleware only on `/dashboard` for now | no domain models exist yet; the seam is proven by tests + the real request path |
| Test-only `TenantThing` fixture model | exercises the real trait/scope without shipping a domain table before its milestone |

## 11. Deferred

- `school_user` `is_default` column, invitations / brand-new-account flow.
- School suspension / lifecycle, subscription / billing.
- Queue-job tenant propagation (no jobs yet).
- Subdomain / path-based tenant routing (slug is ready for it).
- Per-tenant rate limiting, per-tenant caching keys, audit logging of context
  switches.
