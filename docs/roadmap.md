# Roadmap

High-level milestone order. Each milestone ships working, tested software and
updates `PROJECT_STATUS.md`. **Do not start a milestone before the previous one
is accepted.**

## ✅ Milestone 1 — Platform Foundation (complete, 2026-09-10)

Project inspection; conventions for controllers / models / Form Requests /
policies / services / views / components / tests; base responsive Blade shell;
small reusable UI kit; `TenantContext` seam; `/health` endpoint; testing
foundation; config & `.gitignore` safety review; documentation structure.

No domain modules, no website features, no artificial school limit.

## ✅ Milestone 2 — Authentication & User Foundation (complete, 2026-09-10)

Registration, login, logout, password reset, email verification, password
confirmation, account status (`active`/`suspended`/`disabled`) enforced
server-side, account/profile settings (name, email, password, delete),
authenticated shell + placeholder dashboard, polished auth UI on the Milestone 1
component kit, security baseline (throttling, session fixation, generic errors,
password policy), authorization *direction* documented. Native Laravel — no auth
package. Full detail in `docs/authentication.md`.

Deferred to their own milestone: **role & permission model** (evaluate Spatie
Permission then), 2FA, auth audit logging.

## ✅ Milestone 3 — Multi-School / Strict Tenant Isolation (complete, 2026-09-11)

`schools` table + model, `school_user` membership, `users.is_platform_admin`,
`TenantContext` (resolve/set/bypass), `EnforceTenant` middleware, school picker /
switcher, `BelongsToSchool` trait + `SchoolScope` global scope + `creating` /
`updating` hooks (unspoofable, immutable `school_id`), `SchoolPolicy`,
`MissingTenantContext` / `TenantMismatch` exceptions, cross-school isolation test
suite. Full detail in `docs/tenancy.md`.

Deferred: `school_user` roles, membership management UI, queue-job tenant
propagation, subdomain routing.

## Milestone 3.x — Roles & Permissions

Permission-based authorization (roles = permission bundles), the planned role
set (Super Admin · School Admin · Principal · Teacher · Accountant/Bursar ·
Staff · Parent · Student), Policy/Gate wiring, admin UI for `users.status`.
Evaluate Spatie Permission vs. a small in-house model at that point. Composed
with the tenant context so a permission only applies within the acting school.
Sequenced with Milestone 3 since most permissions are per-school.

## Milestone 4 — School Onboarding

School registration/provisioning flow, initial admin user, school settings,
academic session / term setup.

## Milestone 5+ — Domain Modules

Staff · Students & Guardians · Classes/Sections/Subjects · Enrolment ·
Attendance · Assessments & Results · Fees / Invoices / Payments (Paystack) ·
CBT · Role-specific portals & dashboards · Notifications · Reporting.

## Cross-cutting, introduced when first needed

Queues & Redis, object/S3 storage abstraction for uploads, audit logging,
full-text search, caching layer, background exports.

## Permanently out of scope

Public school websites, website builder, themes, website engine, public school
pages, public content management.
