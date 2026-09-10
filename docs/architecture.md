# Architecture

Status: Milestone 2 (Authentication & User Foundation) complete. This describes
the intended shape of the system and what exists today.

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

## 2. Tenant context (implemented)

`App\Support\Tenancy\TenantContext` is a request-scoped singleton
(`$this->app->scoped(...)` in `AppServiceProvider`). It holds the active
`school_id` and nothing else.

| Method | Purpose |
|--------|---------|
| `set(int $id)` / `forget()` | lifecycle, called by middleware (later milestone) |
| `has()` / `id()` | read current tenant |
| `idOrFail()` | read, or throw — for code that must be scoped |
| `runWithoutScope(callable)` | explicit, narrow escape hatch for platform-wide jobs |
| `isBypassed()` | queried by the future global scope |

**Rule:** application code never reads `school_id` from the request. It asks
`TenantContext`. This makes isolation a property of the framework wiring, not of
every developer remembering a `where()` clause.

### Planned build-out (not in this milestone)

- `EnforceTenant` middleware: resolves the school for the authenticated user /
  route, calls `TenantContext::set()`, 403s if the user has no access to it.
- `BelongsToSchool` trait: adds a global scope filtering by
  `TenantContext::id()` (unless `isBypassed()`), and a `creating` hook that
  stamps `school_id`. Tenant-owned models `use BelongsToSchool`.
- Queue jobs serialise and restore the tenant id.

## 3. Application layers & conventions

| Layer | Directory | Responsibility |
|-------|-----------|----------------|
| Routing | `routes/web.php`, `routes/auth.php` | thin; names every route; auth flow split into its own file |
| Controllers | `app/Http/Controllers` (`Auth/`, `Settings/`) | HTTP orchestration only; resourceful naming |
| Form Requests | `app/Http/Requests` (`Auth/`, `Settings/`) | validation + request-scoped authorization |
| Policies | `app/Policies` | model authorization, invoked server-side |
| Services | `app/Services` | multi-step / cross-model business operations |
| Models | `app/Models` | persistence, casts, mass-assignment guards, scopes |
| Enums | `app/Enums` | closed value sets (`UserStatus`), backed by string columns |
| Middleware | `app/Http/Middleware` | cross-cutting request guards (`EnsureAccountIsActive`) |
| Support | `app/Support` | framework-agnostic helpers, value objects, tenancy |
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
