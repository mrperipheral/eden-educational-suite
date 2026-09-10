# Project Status

_Last updated: 2026-09-12_

## Current milestone

**Milestone 4 — Roles & Permissions: COMPLETE.**

Next up: **Milestone 5 — School Onboarding** (school provisioning, initial admin,
adding existing users to a school, settings, academic sessions). Not started —
do not begin without picking it up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-12)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.22.0 / 10.9.4 |
| Database | MySQL 8.4 `schoolmanagement_db` |
| Tests | `php artisan test` — 164 passing (571 assertions) |
| Build | `npm run build` — passing |
| Formatting | `vendor/bin/pint --test` — passing |

## Delivered

- **M1 — Platform Foundation** (`foundation-complete`): conventions, Blade shell +
  UI kit, `TenantContext` seam, `/health`, testing & docs foundation.
- **M2 — Authentication & User Foundation** (`authentication-complete`):
  registration / login / logout / reset / verification / password confirmation,
  account status, profile settings, security baseline. `docs/authentication.md`.
- **M3 — Multi-School / Strict Tenant Isolation** (`multischool-foundation-complete`):
  `schools`, `school_user`, `is_platform_admin`, `TenantContext`, `EnforceTenant`,
  `BelongsToSchool` + `SchoolScope`, `SchoolPolicy`, school picker.
  `docs/tenancy.md`.
- **M4 — Roles & Permissions** (this milestone): see below.

## Delivered in Milestone 4

- **`App\Enums\Permission`** — 24 code-defined permissions (no DB table). The
  only thing code checks; never role names.
- **`App\Enums\Role`** — 7 per-school roles (School Admin, Principal, Bursar,
  Teacher, Staff, Parent, Student) as static permission bundles + `tier`.
  School Admin is a superset of all.
- **`school_user.role`** (nullable) + **`App\Models\SchoolUser`** pivot
  (migration `2026_09_12_100000`). One role per (user, school).
- **`App\Providers\AuthServiceProvider`** — registers every permission as a Gate
  ability delegating to `User::hasPermission()`, **composed with `TenantContext`**;
  maps `MembershipPolicy`. **No `Gate::before()`**.
- **`User`** — `roleIn()`, `permissionsIn()`, `hasPermission()`, `canGrantRole()`
  (tier-based anti-escalation), `joinSchool()` / `assignRoleInSchool()` /
  `leaveSchool()`; per-request role memo.
- **Platform admin** — holds every permission *within a school they have entered*;
  `SchoolScope` still limits the rows; nothing without an active context.
- **`App\Policies\MembershipPolicy`** — no self-edit, no privilege escalation.
- **Members management** — `MemberController` + `GET/PATCH/DELETE /members`
  (`->can('member.view')`, `AssignMemberRoleRequest`), tenant-scoped, role
  filter, pagination; `resources/views/members/index.blade.php`; conditional
  "Members" nav link in `<x-layouts.authenticated>`.
- **Spatie laravel-permission evaluated and not adopted** — rationale in
  `docs/authorization.md` §2.
- **Docs** — new `docs/authorization.md`; updated `architecture.md`,
  `security.md`, `tenancy.md`, `database-design.md`, `roadmap.md`, `CLAUDE.md`,
  `AGENTS.md`.

## Explicitly NOT done (by design)

Multi-role per school · custom / runtime-editable roles · Spatie Permission ·
invitations / add-brand-new-member flow · admin UI for `status` /
`is_platform_admin` · enforcing the dormant domain permissions (each in its
module) · school onboarding / provisioning / settings / subscriptions ·
students / guardians / teachers / staff / academics / attendance / results /
fees / Paystack / CBT / portals · queue-job tenant propagation · 2FA · audit
logging · Redis / queues / object storage · any website functionality · any
500-school limit.

## Database

Framework tables + `users.status` + `users.is_platform_admin` + `schools` +
`school_user` (now with `role`). Migration `2026_09_12_100000_add_role_to_school_user_table`.
No domain tables. Permissions/roles are code, not tables.

## Routes (application, `--except-vendor`)

Adds (under `auth · verified · active · tenant`):

| Method | URI | Name |
|--------|-----|------|
| GET | `/members` | `members.index` _(`->can('member.view')`)_ |
| PATCH | `/members/{user}` | `members.update-role` |
| DELETE | `/members/{user}` | `members.destroy` |

## Tests

164 passing / 571 assertions. New in M4: `Unit/Enums/{Role,Permission}Test`,
`Feature/Authorization/{PermissionResolution,GateIntegration,MembershipPolicy}Test`,
`Feature/Members/{MemberManagement,MemberCrossSchool}Test`. `InteractsWithTenancy`
gained role-aware helpers. M1–M3 tests green.

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- School Onboarding milestone: `school_user.is_default`, invitations / add-existing-user,
  and (likely) an admin UI for `users.status` / `is_platform_admin`.
- When queues arrive: tenant-id capture/restore in a base job (`docs/tenancy.md` §7).
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
- Consider audit logging of role changes and school-context switches.
