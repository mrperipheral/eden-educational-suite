# Project Status

_Last updated: 2026-09-11_

## Current milestone

**Milestone 3 — Multi-School / Strict Tenant Isolation: COMPLETE.**

Next up: **Roles & Permissions** (sequenced with / after Multi-School; evaluate
Spatie Permission then), then **School Onboarding**. Not started — do not begin
without picking it up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-11)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.22.0 / 10.9.4 |
| Database | MySQL 8.4 `schoolmanagement_db` |
| Tests | `php artisan test` — 124 passing (331 assertions) |
| Build | `npm run build` — passing |
| Formatting | `vendor/bin/pint --test` — passing |

## Delivered

- **M1 — Platform Foundation** (tag `foundation-complete`): conventions,
  responsive Blade shell + UI kit, `TenantContext` seam, `/health`, testing &
  docs foundation.
- **M2 — Authentication & User Foundation** (tag `authentication-complete`):
  registration / login / logout / password reset / email verification / password
  confirmation, account status, profile settings, security baseline. Native
  Laravel, no package. `docs/authentication.md`.
- **M3 — Multi-School / Strict Tenant Isolation** (this milestone): see below.

## Delivered in Milestone 3

- **Schema** — `schools` (`name, slug, status`), `school_user` membership pivot,
  `users.is_platform_admin`. `SchoolStatus` enum. No domain tables.
- **`TenantContext`** — request-scoped; `set()/setId()`, `id()/idOrFail()`,
  `school()/schoolOrFail()`, `forget()`, `isBypassed()`, `runWithoutScope()`.
- **`EnforceTenant` middleware** (`tenant`) — resolves the active school from a
  re-validated session selection or auto-selects a single-school member; else
  redirects to the picker. Session holds only an id, re-checked every request.
- **`BelongsToSchool` trait + `SchoolScope`** — global scope constrains every
  read/update/delete to the active school and **throws** rather than run
  unscoped; `creating` hook stamps `school_id` and rejects mismatches;
  `updating` hook makes `school_id` immutable. `withoutSchoolScope()` /
  `forSchool()` escape hatches.
- **`MissingTenantContextException`** (fail-closed), **`TenantMismatchException`**
  (403).
- **`SchoolPolicy`** — platform actions require `is_platform_admin`; `view` /
  `enter` allow members; `enter` also requires an active school. No
  `Gate::before` blanket-allow.
- **School picker / switcher** — `SchoolContextController`, `GET/POST /school`,
  paginated + searchable; `SelectSchoolRequest`. Platform admins see all schools
  and always pick; members see only their own and single-school auto-resolves.
- **UI** — `schools/select` view, current-school badge + Switch link in
  `<x-layouts.authenticated>`, dashboard shows the active school. Reuses the
  existing component kit.
- **Docs** — new `docs/tenancy.md`; updated `architecture.md`, `security.md`,
  `database-design.md`, `scalability.md`, `roadmap.md`, `CLAUDE.md`, `AGENTS.md`.
- **Base `Controller`** now uses `AuthorizesRequests`.

## Explicitly NOT done (by design)

Roles/permissions & Spatie Permission · `school_user` role/default columns ·
membership management / invitations UI · school onboarding / provisioning /
settings / subscriptions · students / guardians / teachers / staff / academics /
attendance / results / fees / Paystack / CBT / portals · queue-job tenant
propagation (no jobs yet) · subdomain/path tenant routing · 2FA · auth/tenant
audit logging · Redis / queues / object storage · any website functionality ·
any 500-school limit.

## Database

Framework tables + `users.status` + `users.is_platform_admin` + `schools` +
`school_user`. Migrations `2026_09_11_100000/100010/100020`. No domain tables.

## Routes (application, `--except-vendor`)

Unchanged from M2 except: `/dashboard` now sits behind the `tenant` middleware,
and `GET/POST /school` (`school-context.create` / `school-context.store`) added.

## Tests

124 passing / 331 assertions. New in M3:
`Unit/Enums/SchoolStatusTest`, `Unit/Tenancy/TenantContextTest` (rewritten),
`Feature/Tenancy/{BelongsToSchool, EnforceTenant, CrossSchoolIsolation,
SchoolContextController, SchoolAndMembership, SchoolPolicy}Test`. Test support:
`tests/Concerns/InteractsWithTenancy`, `tests/Fixtures/Tenancy/TenantThing`
(fixture model — no domain table shipped). M1/M2 tests green (2 M2 tests updated
to attach a school for `/dashboard`).

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`,
  `APP_DEBUG=false`.
- When queues arrive: add tenant-id capture/restore to a base job (documented in
  `docs/tenancy.md` §7).
- Roles milestone: add `school_user.role`, membership management, and compose
  permission checks with `TenantContext`.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
- Consider audit logging of school-context switches.
