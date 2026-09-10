# Architecture

Status: Milestone 4 (Roles & Permissions) complete. This describes the intended
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

### Deferred

- Queue jobs capture/restore the tenant id (no jobs exist yet — see
  `docs/tenancy.md` §7).
- Multi-role per school, custom/runtime-editable roles, invitations / add-member
  flow, admin UI for `status` / `is_platform_admin`, subdomain routing.

## 3. Application layers & conventions

| Layer | Directory | Responsibility |
|-------|-----------|----------------|
| Routing | `routes/web.php`, `routes/auth.php` | thin; names every route; auth flow split into its own file |
| Controllers | `app/Http/Controllers` (`Auth/`, `Settings/`) | HTTP orchestration only; resourceful naming |
| Form Requests | `app/Http/Requests` (`Auth/`, `Settings/`) | validation + request-scoped authorization |
| Policies | `app/Policies` | model authorization, invoked server-side |
| Services | `app/Services` | multi-step / cross-model business operations |
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
