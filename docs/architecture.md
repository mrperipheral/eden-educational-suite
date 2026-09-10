# Architecture

Status: Milestone 9 (Student Management) complete. This describes the intended
shape of the system and what exists today.

## 1. High-level model

```
Authenticated User
        │
        ▼
 Current School          ← chosen at login / from route, one active at a time
        │
        ▼
 Tenant Context          ← App\Support\Tenancy\TenantContext (request-scoped)
        │
        ▼
 Tenant-aware logic      ← global query scope + model hooks + policies
        │
        ▼
 School data             ← rows carry school_id; School A never sees School B
```

One Laravel application, one shared database, **logical** multi-tenancy keyed by
`school_id`. This is a deliberate choice over database-per-tenant:

- thousands of schools without thousands of schemas/connections to migrate;
- cross-cutting platform features (billing, support, analytics) stay simple;
- horizontal scale is achieved at the database and app tier, not by sharding
  tenants into separate schemas.

There is **no 500-school limit** anywhere in the design. Growth beyond that is a
capacity exercise (indexes, read replicas, caching, queue workers), not an
architectural rewrite.

## 2. Multi-school tenant isolation (implemented — Milestone 3)

One Laravel app, one shared database, logical isolation keyed by `school_id`.
Full reference: **`docs/tenancy.md`**. Summary:

- **`schools`** is the tenant root; **`school_user`** maps a many-to-many
  membership; **`users.is_platform_admin`** is the platform-owner primitive
  (separate from any per-school role, no `Gate::before` blanket-allow).
- **`App\Support\Tenancy\TenantContext`** (request-scoped singleton) holds the
  active school. `set()/setId()`, `id()/idOrFail()`, `school()/schoolOrFail()`,
  `forget()`, `isBypassed()`, `runWithoutScope()`.
- **`App\Http\Middleware\EnforceTenant`** (`tenant` alias) resolves the school
  from the session selection (re-validated every request) or auto-selects a
  single-school member, else redirects to the picker (`GET/POST /school`,
  `SchoolContextController`).
- **`App\Support\Tenancy\Concerns\BelongsToSchool`** + **`SchoolScope`** apply to
  every school-owned model: a global scope that constrains reads/updates/deletes
  to the active school (and **throws** — `MissingTenantContextException` — rather
  than run unscoped), a `creating` hook that stamps `school_id` from the context
  and rejects mismatches (`TenantMismatchException`), and an `updating` hook that
  makes `school_id` immutable.
- **`App\Policies\SchoolPolicy`** authorizes the school resource; platform
  actions require `is_platform_admin`, `view`/`enter` allow members too.

**Rule:** application code never reads `school_id` from the request. It asks
`TenantContext`. Isolation is a property of the framework wiring, not of every
developer remembering a `where()` clause.

## 2b. Roles & permissions (implemented — Milestone 4)

Full reference: **`docs/authorization.md`**. Summary:

- **`App\Enums\Permission`** — the fixed, code-defined authorization vocabulary
  (no DB table). **`App\Enums\Role`** — seven per-school roles, each a static
  bundle of permissions + a `tier`.
- Assignment is one nullable column: **`school_user.role`** (`App\Models\SchoolUser`
  pivot). One role per (user, school).
- **`App\Providers\AuthServiceProvider`** registers every permission as a Gate
  ability delegating to **`User::hasPermission()`**, which is composed with the
  active `TenantContext` — a permission only applies inside the school in
  context, and there is **no `Gate::before()`** blanket bypass.
- Platform admins hold every permission *within a school they have entered*;
  `SchoolScope` still constrains which rows they touch.
- **`App\Policies\MembershipPolicy`** adds per-row rules (no self-edit, no
  privilege escalation).
- Evaluated Spatie laravel-permission; not adopted (its Teams feature is a
  second ambient tenant id competing with `TenantContext`). See
  `docs/authorization.md` §2.

## 2c. School onboarding (implemented — Milestone 5)

Full reference: **`docs/onboarding.md`**. The controlled path from provisioning
to a usable school, built on the M1–M4 seams:

- **Provisioning** (`Platform\SchoolController` + `SchoolProvisioner`,
  `/admin/schools*`) is **platform-level** — a school shell (`schools` row,
  status `active`, unique slug) is created outside any tenant context;
  `SchoolPolicy` is the authority. Optionally seats an existing active account as
  the initial `Role::SchoolAdmin` (guarded by `canGrantRole`).
- **School-owned onboarding data** — `App\Models\SchoolSetting` (1:1) and
  `App\Models\AcademicSession`, the first real `BelongsToSchool` models — is
  created by the school's own administrators **inside** the tenant context,
  gated by `school.settings.*`.
- **Adding an existing user** (`/members/create`) reuses `member.assign-role` +
  `canGrantRole` (tier); it never crosses a school boundary or changes anyone's
  context.
- The dashboard derives an **onboarding checklist** (admin seated, first session
  created, settings reviewed) for administrators.

## 2d. School settings & configuration (implemented — Milestone 6)

Full reference: **`docs/school-settings.md`**. Expands the single M5
`SchoolSetting` row into the full per-school configuration record — typed
columns, no JSON blob — split into three focused sections behind a shared
sub-nav:

- **Profile** (`PATCH /settings/school`) — contact details + address; the
  school's `name`/`slug`/`status` stay platform-controlled and read-only.
- **Branding** (`/settings/school/branding`) — an optional logo stored on the
  **private `local` disk** and served only through a `school.settings.view`-gated
  route with **no path parameter** (no traversal, tenant-scoped), plus
  `brand_color`.
- **Regional** (`/settings/school/regional`) — `timezone`, `locale`, `currency`,
  `date_format` (`App\Enums\DateFormat`), `week_starts_on` (`App\Enums\Weekday`),
  `academic_year_start_month` (read later by Academic Management).

Reuses the existing seams unchanged: `school.settings.view` / `.update`,
`TenantContext` + `SchoolScope`, the M3 storage abstraction. Principal & Bursar
get the read-only view; Platform Admin operates only through a selected context.
Reference lists live in `config/school-settings.php`.

## 2e. Feature / module activation (implemented — Milestone 7)

Full reference: **`docs/module-activation.md`**. Each school turns the
application's feature modules on or off independently. M7 ships the activation
*system*; the modules themselves are later milestones.

- **`App\Enums\Module`** — the code-defined catalogue (14 cases: Academics,
  Students, Guardians, Staff, Timetable, Attendance, Assessments, Results, Fees,
  Learning Materials, CBT, Notifications, Parent/Student Portal). Each case
  carries a label, description, group, dependency list, default, and
  `isAvailable()` (all `false` until each domain milestone flips its own).
- **`school_modules`** (`App\Models\SchoolModule`, `BelongsToSchool`) —
  override-only: a row exists only where a school departs from the catalogue
  default, so a new school writes nothing.
- **`App\Support\Modules\SchoolModules`** — request-scoped resolver: reads the
  override rows once (keyed by active school id), memoised; `enabled(Module)` /
  `states()` / `set()`.
- **Admin screen** — `GET/PATCH /settings/school/modules`, gated
  `school.settings.view` / `.update` (no new permission). Single-level
  dependency validation on toggle. Not-yet-built modules are badged **Planned**.
- **Reuse seam for future modules** — `module:` route middleware
  (`EnsureModuleEnabled`, 404s when off) and the `@module('…')` Blade directive.
  Activation is **configuration, not authorization** — it grants nothing; a
  domain route checks both `module:` *and* `->can('…')`.

## 2f. Academic foundation (implemented — Milestone 8)

Full reference: **`docs/academic-foundation.md`**. The configurable academic
structure the Student / Teacher / Timetable / Attendance / Assessment / Results
modules will build on. Structure only — no people, no timetable, no marks.

- **`App\Models\AcademicSession`** (M5, extended) — the year; one `is_current`
  per school. Now has `AcademicPeriod` children.
- **`App\Models\AcademicPeriod`** — a term / semester within a session.
  `BelongsToSchool` *and* scoped to its session; one `is_current` per session;
  any number per session (no "three terms" assumption).
- **`App\Models\AcademicLevel`** + **`App\Models\LevelArm`** — classes and their
  streams. Named, coded, ordered, active/inactive. No level names hard-coded.
- **`App\Models\Subject`** + the `level_subject` link — school subjects and
  which levels offer them. The only cross-model relationship M8 ships.
- **`App\Http\Controllers\Academic\*`**, `/academic/*` routes behind
  `['tenant', 'module:academics']`, gated `academics.view` / `academics.manage`
  (M4 permissions, previously dormant — now enforced). Academic sessions moved
  here from `settings/academic-sessions` (M5).

## 2g. Student management (implemented — Milestone 9)

Full reference: **`docs/student-management.md`**. The student record + enrollment
history the Guardian / Attendance / Assessment / Results / Fees / Promotion /
Portal modules will hang off. Records only — no people beyond students, no
placement workflow.

- **`App\Models\Student`** — school-owned. Minimal PII (name, DOB, optional
  gender, admission details, contact/address, notes). `status`
  (`App\Enums\StudentStatus`: active / inactive / withdrawn / graduated) is not
  mass-assignable — changed via a dedicated endpoint. Never hard-deleted.
- **`App\Models\Enrollment`** — school-owned *and* scoped to its student. Points
  at an `AcademicSession` (+ optional `AcademicPeriod`), `AcademicLevel` (+
  optional `LevelArm`). One `active` enrollment per student = the current class
  (`Enrollment::makeActive()`, the M8 `makeCurrent()` pattern — **not** a
  promotion workflow). History is preserved: placements are closed, never
  deleted.
- **`App\Http\Controllers\Student\*`**, `/students/*` routes behind
  `['tenant', 'module:students']`, gated `student.view` / `student.manage` (M4
  permissions, previously dormant — now enforced). `Module::Students` now
  **depends on `Module::Academics`**.
- Level ↔ arm and session ↔ period consistency, and cross-school ids, are
  rejected in the Form Request with generic messages (no leak); the enrollment
  Form Request `abort(404)`s on a cross-school route parent.

### Deferred

- Queue jobs capture/restore the tenant id (no jobs exist yet — see
  `docs/tenancy.md` §7).
- Invitations / brand-new-account onboarding, school suspension / subscription,
  notification & payment config, the remaining domain modules behind the M7
  catalogue (guardians, staff, timetable, attendance, results, fees, CBT,
  portals, promotion workflow, bulk student import), admin UI for `status` /
  `is_platform_admin`, subdomain routing.

## 3. Application layers & conventions

| Layer | Directory | Responsibility |
|-------|-----------|----------------|
| Routing | `routes/web.php`, `routes/auth.php` | thin; names every route; auth flow split into its own file |
| Controllers | `app/Http/Controllers` (`Auth/`, `Settings/`, `Platform/`) | HTTP orchestration only; resourceful naming |
| Form Requests | `app/Http/Requests` (`Auth/`, `Settings/`, `Platform/`) | validation + request-scoped authorization |
| Policies | `app/Policies` | model authorization, invoked server-side |
| Services | `app/Services` | multi-step business operations (`SchoolProvisioner`) |
| Models | `app/Models` | persistence, casts, mass-assignment guards, scopes |
| Enums | `app/Enums` | closed value sets (`UserStatus`, `SchoolStatus`, `Role`, `Permission`), backed by string columns / static bundles |
| Middleware | `app/Http/Middleware` | cross-cutting request guards (`EnsureAccountIsActive`, `EnforceTenant`) |
| Providers | `app/Providers` | `AppServiceProvider` (tenant seam, model strictness), `AuthServiceProvider` (Gate abilities per permission, policy map) |
| Support | `app/Support` | framework-agnostic helpers; `Support\Tenancy` = tenant context, `BelongsToSchool` trait, `SchoolScope`, exceptions |
| Views | `resources/views/<area>` | pages |
| UI components | `resources/views/components` | layouts + design-system primitives |

Authentication is documented in full in `docs/authentication.md`.

Deliberately **not** adopted now: repository pattern, a DTO for every payload,
a generic "BaseService", event sourcing. Introduce an abstraction when a second concrete
use case demands it, and record the decision here.

## 4. Frontend architecture

Blade renders HTML. Tailwind v4 (configured in `resources/css/app.css` via
`@theme`, no `tailwind.config.js`). Alpine.js provides the small amount of
interactivity the shell needs (nav drawer, dropdowns, confirm dialogs) and is
loaded/started in `resources/js/app.js`. Vite builds both.

No SPA framework. If a future feature genuinely needs one, that decision is
recorded here first with its trade-offs.

### Application shell

`resources/views/components/layouts/app.blade.php`:

- **Desktop:** fixed left sidebar (`lg:` breakpoint) + sticky top header +
  scrollable `<main>`.
- **Mobile:** compact header with a hamburger; sidebar becomes an off-canvas
  drawer toggled by Alpine (`sidebarOpen`) with a backdrop.
- Navigation is injected via the `navigation` slot — role-specific menus are a
  later milestone, not baked in here.

`resources/views/components/layouts/guest.blade.php` is a centred card layout for
pre-auth screens.

## 5. Health & observability

- `/up` — Laravel's built-in framework boot check.
- `/health` — `HealthController`, returns JSON `{status, time, checks:{database}}`
  with HTTP 200/503. Unauthenticated, side-effect free, leaks no config. Suitable
  for load-balancer and uptime-monitor probes.

## 6. Decision log

| Date | Decision | Why |
|------|----------|-----|
| 2026-09-10 | Shared DB + logical `school_id` tenancy | scale to many thousands of schools without schema sprawl |
| 2026-09-10 | `TenantContext` as the single tenant seam | isolation becomes framework wiring, not developer discipline |
| 2026-09-10 | Alpine.js added; no SPA framework | shell needs minimal interactivity only |
| 2026-09-10 | UI primitives as anonymous Blade components | simple, no build-time component registry |
| 2026-09-10 | No Spatie Permission / Paystack / Redis yet | not needed by the foundation; belong to later milestones |
| 2026-09-10 | Auth hand-rolled on Laravel primitives; no Breeze/Fortify/Jetstream | a starter package brings a conflicting UI/Tailwind layer for controllers we own anyway (see `docs/authentication.md` §13) |
| 2026-09-10 | Account status = `users.status` string enum, gated at login + per-request middleware | three fixed states; login-only checks leave suspended sessions live |
| 2026-09-10 | `verified` middleware on the app group only; `password.confirm` on account deletion only | users must be able to verify / leave; avoid unnecessary password prompts |
| 2026-09-10 | Role/permission system still deferred | belongs to its own milestone; direction documented in `docs/authentication.md` §11 |
| 2026-09-11 | `SchoolScope` throws on missing tenant context (fail closed) | an unscoped query would leak every school's data |
| 2026-09-11 | `creating` hook forces `school_id` from context; `school_id` immutable on update | `school_id` cannot be spoofed via mass assignment or explicit set |
| 2026-09-11 | Many-to-many `User`↔`School`; `is_platform_admin` boolean, no `Gate::before` | multi-campus staff are real; platform admins still act within a chosen tenant, not around it |
| 2026-09-11 | School context in the session, re-validated every request | stateless-friendly; tampering with the stored id is inert |
| 2026-09-12 | Roles/permissions in-house (enums), not Spatie laravel-permission | Spatie's Teams adds a second ambient tenant id competing with `TenantContext`; our permission set is static/code-defined (see `docs/authorization.md` §2) |
| 2026-09-12 | Each permission is a Gate ability; still **no `Gate::before`** | `$user->can()` / `@can` / route `->can()` all work and stay tenant-composed; platform admins get school-admin reach *inside* an entered school only |
| 2026-09-12 | One role per (user, school), tier-based escalation guard | matches "roles = bundles"; invariant "never grant a role above your own" is simple and testable |
| 2026-09-13 | Provisioning is platform-level; school-owned onboarding data is configured in-context | "operate through controlled school context when modifying school-owned data" — `/admin` only creates the shell |
| 2026-09-13 | Add-existing-user reuses `member.assign-role` + `canGrantRole`; academic sessions gated by `school.settings.*` | smallest permission surface; the year container is configuration, `academics.*` stays dormant for its milestone |
| 2026-09-13 | Tenant-owned route params resolved by id in-controller, not route-model-bound | binding runs before the `tenant` middleware, so a `BelongsToSchool` bind would hit `SchoolScope` with no context |
| 2026-09-14 | School settings expanded as typed columns on `SchoolSetting`, three sectioned pages; logo on the private disk behind a gated no-path route | validated/queryable, no JSON blob or second settings system; prevents logo traversal / cross-tenant access (see `docs/school-settings.md`) |
| 2026-09-10 | Module catalogue is `App\Enums\Module`; `school_modules` is override-only; reuse `school.settings.*` | modules are behaviour reviewed as code; a new school writes nothing; activation is configuration, not a new permission (see `docs/module-activation.md`) |
| 2026-09-10 | Module activation is orthogonal to authorization — `module:` middleware + `->can()` are both required on a domain route | turning a feature on must never grant a permission; M4 stays the sole authority on "may this user…" |
| 2026-09-16 | Academic sessions moved from `school.settings.*` to `academics.*` + `module:academics`; `AcademicPeriod` / `LevelArm` are `BelongsToSchool` in their own right | sessions/periods/levels/subjects are one structure under one permission; child models stay tenant-safe without the parent in the join (see `docs/academic-foundation.md`) |
| 2026-09-16 | No hard delete for academic entities — only `is_active` | preserves referential integrity for the modules built on top; deletion / archival is a later concern |
| 2026-09-17 | Current class is the one `active` `Enrollment`, never a column on `students` | history is first-class; "where now" and "where before" are one model; promotion later just adds rows (see `docs/student-management.md`) |
| 2026-09-17 | Two status enums (`StudentStatus` vs `EnrollmentStatus`); student `status` not mass-assignable | student↔school vs one-placement are distinct; lifecycle changes get one dedicated, auditable seam |
| 2026-09-17 | `Module::Students` depends on `Module::Academics` | enrollment is meaningless without sessions/levels — a minimal, correct extension of the M7 catalogue |
