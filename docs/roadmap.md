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

## ✅ Milestone 4 — Roles & Permissions (complete, 2026-09-12)

`App\Enums\Permission` (24 code-defined permissions) + `App\Enums\Role` (7
per-school roles as static permission bundles + tiers), `school_user.role`
column + `App\Models\SchoolUser` pivot, `AuthServiceProvider` registering every
permission as a tenant-composed Gate ability (no `Gate::before`),
`MembershipPolicy` (self / escalation guards), and the Members-management
feature (`/members`). Spatie laravel-permission evaluated and not adopted. Full
detail in `docs/authorization.md`.

Deferred: multi-role per school, custom/runtime roles, invitations, admin UI for
`status` / `is_platform_admin`.

## ✅ Milestone 5 — School Onboarding (complete, 2026-09-13)

Platform-admin school provisioning (`/admin/schools`, `SchoolProvisioner`,
unique-slug generation), optional initial School Admin assignment, adding
existing users to a school (`/members/create`, `member.assign-role` + tier
guard), basic school settings (`school_settings`, 1:1, `BelongsToSchool`), the
initial academic session (`academic_sessions`, structure-agnostic), and a
derived dashboard onboarding checklist. Full detail in `docs/onboarding.md`.

Deferred: invitations / brand-new-account onboarding, school suspension /
subscription, the Academic Management milestone.

## ✅ Milestone 6 — School Settings & Configuration (complete, 2026-09-10)

Expanded `school_settings` (typed columns, no JSON blob) into the full
per-school configuration record, split into three sections — **Profile**
(contact + address), **Branding** (private-disk logo upload served through a
gated no-path route, `brand_color`), **Regional** (`timezone`, `locale`,
`currency`, `date_format`, `week_starts_on`, `academic_year_start_month`) — plus
`config/school-settings.php` reference data and the `DateFormat` / `Weekday`
enums. Built on the existing M3/M4 tenant + permission architecture
(`school.settings.view` / `.update`); Principal & Bursar read-only. Full detail
in `docs/school-settings.md`.

Deferred: grading scheme / term structure / holiday calendar (Academic
Management), notification & payment-gateway config (their own modules), feature
activation, app-wide render-time application of the formatting preferences.

## ✅ Milestone 7 — Feature / Module Activation (complete, 2026-09-10)

Per-school enable/disable of the application's feature modules. `App\Enums\Module`
(14 code-defined modules with label / description / group / dependencies /
default / `isAvailable()`), `school_modules` override-only table +
`App\Models\SchoolModule` (`BelongsToSchool`), the request-scoped
`App\Support\Modules\SchoolModules` resolver (one query per request, memoised),
a Modules admin page under school settings (`/settings/school/modules`, gated
`school.settings.*`), and the reusable `module:` route middleware + `@module`
Blade directive for future modules. Activation is configuration only — it grants
no permissions. Full detail in `docs/module-activation.md`.

Deferred: the modules themselves (each domain milestone), preset bundles,
disable-with-cascade, per-module config pages, activation audit trail,
plan-based entitlements.

## ✅ Milestone 8 — Academic Foundation (complete, 2026-09-16)

The configurable academic structure the later modules build on: academic
sessions (M5, extended) + `AcademicPeriod` (terms/semesters, any number),
`AcademicLevel` + `LevelArm` (classes and streams), `Subject`, and the
`level_subject` "which level offers which subject" link. All `BelongsToSchool`;
`/academic/*` gated by `academics.view` / `academics.manage` (M4 permissions,
previously dormant) **and** `module:academics`. `Module::Academics->isAvailable()`
is now `true`. Structure only — no students, teachers, timetable, attendance,
assessments or results. Full detail in `docs/academic-foundation.md`.

Deferred: students / guardians / staff, class & arm membership, teacher
assignment, timetable, attendance, assessments, results, grading, promotion,
CBT, per-subject assessment metadata, bulk import / year cloning, hard delete /
archival.

## ✅ Milestone 9 — Student Management (complete, 2026-09-17)

The tenant-scoped student record + enrollment-history foundation. `App\Models\Student`
(minimal PII; `StudentStatus` active/inactive/withdrawn/graduated; never hard-deleted)
+ `App\Models\Enrollment` (school-owned + student-scoped; points at session /
optional period / level / optional arm; one `active` = the current class via
`Enrollment::makeActive()` — not a promotion workflow). `/students/*` gated
`student.view` / `student.manage` (M4 permissions, previously dormant) **and**
`module:students` — which now depends on `module:academics`.
`Module::Students->isAvailable()` is now `true`. Full detail in
`docs/student-management.md`.

Deferred: guardians / parents (linkage + screens), teacher assignment, class
rosters, attendance, assessments, results, fees, **promotion / graduation
workflow**, bulk student import, student ID / photo / documents, medical info,
transfer records, student portal.

## Milestone 10+ — Domain Modules

Guardians · Staff · Class rosters & teacher assignment · Timetable · Attendance ·
Assessments & Results · Fees / Invoices / Payments (Paystack) · CBT ·
Role-specific portals & dashboards · Notifications · Reporting · Promotion.

Each domain module checks its `App\Enums\Module` flag (`module:` middleware /
`@module`) **and** its M4 permissions — the two stay orthogonal.

## Cross-cutting, introduced when first needed

Queues & Redis, object/S3 storage abstraction for uploads, audit logging,
full-text search, caching layer, background exports.

## Permanently out of scope

Public school websites, website builder, themes, website engine, public school
pages, public content management.
