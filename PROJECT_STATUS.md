# Project Status

_Last updated: 2026-09-13_

## Current milestone

**Milestone 5 — School Onboarding: COMPLETE.**

Next up: **Domain Modules** (Milestone 6+) — Staff, Students & Guardians,
Classes/Subjects, Enrolment, Attendance, Results, Fees, CBT, portals. Not
started — do not begin without picking it up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-13)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.22.0 / 10.9.4 |
| Database | MySQL 8.4 `schoolmanagement_db` |
| Tests | `php artisan test` — 211 passing |
| Build | `npm run build` — passing |
| Formatting | `vendor/bin/pint --test` — passing |

## Delivered

- **M1 — Platform Foundation** (`foundation-complete`).
- **M2 — Authentication & User Foundation** (`authentication-complete`) —
  `docs/authentication.md`.
- **M3 — Multi-School / Strict Tenant Isolation** (`multischool-foundation-complete`) —
  `docs/tenancy.md`.
- **M4 — Roles & Permissions** (`roles-permissions-complete`) —
  `docs/authorization.md`.
- **M5 — School Onboarding** (this milestone) — `docs/onboarding.md`; see below.

## Delivered in Milestone 5

- **School provisioning** — `App\Http\Controllers\Platform\SchoolController` +
  `App\Services\SchoolProvisioner`; `GET/POST /admin/schools`,
  `GET /admin/schools/{school}`. Platform-admin only (`SchoolPolicy` + group
  `can:viewAny,School` + per-action `authorize()`). `name` / `slug` validation,
  duplicate-slug prevention, unique-slug generation, new schools start
  `SchoolStatus::Active`. Provisioning never sets the platform admin's tenant
  context.
- **Initial School Admin** — optional `initial_admin_email` (existing active
  account) seated as `Role::SchoolAdmin`, guarded by `canGrantRole()`.
- **Add existing user to a school** — `MemberController@create/@store`,
  `GET /members/create`, `POST /members` (`throttle:10,1`); `AddMemberRequest`;
  `MembershipPolicy::add` + `User::canGrantRole()` (tier — no escalation);
  never cross-school, never a context change.
- **`App\Models\SchoolSetting`** (1:1, `BelongsToSchool`) + `SchoolSettingsController`,
  `GET/PATCH /settings/school` (`school.settings.view` / `.update`). `timezone`
  (default `Africa/Lagos`, validated), `locale`, `contact_email/phone`,
  `completed_at`. Read-only view for `school.settings.view`-only roles.
- **`App\Models\AcademicSession`** (`BelongsToSchool`) + `AcademicSessionController`,
  `GET/POST /settings/academic-sessions`, `PATCH .../{session}`
  (`school.settings.*`). Structure-agnostic (no terms / Nigerian assumptions).
  First session auto-current; `makeCurrent()` transactional + tenant-scoped.
- **Onboarding checklist** — derived on the dashboard for `school.settings.update`
  holders (admin seated · first session · settings reviewed → "complete").
- **Migrations** — `2026_09_13_100000_create_school_settings_table`,
  `2026_09_13_100010_create_academic_sessions_table`.
- **Docs** — new `docs/onboarding.md`; updated `architecture.md`, `security.md`,
  `tenancy.md`, `authorization.md`, `database-design.md`, `roadmap.md`,
  `CLAUDE.md`, `AGENTS.md`.
- No new packages, no Redis / queues.

## Explicitly NOT done (by design)

Invitations / email workflows / creating brand-new accounts during onboarding ·
school suspension / lifecycle / subscriptions / billing · full School Settings
milestone (branding, grading, address) · full Academic Management (terms,
calendar, promotion) · admin UI for `users.status` / `is_platform_admin` ·
students / guardians / staff / classes / attendance / results / fees / Paystack
/ CBT / portals / notifications · queue-job tenant propagation · 2FA · audit
logging · any website functionality · any 500-school limit.

## Database

Adds `school_settings` (1:1, unique `school_id`) and `academic_sessions`
(`unique(school_id,name)`, `index(school_id,starts_on)`). Both school-owned via
`BelongsToSchool`. No other schema changes.

## Routes (application, additions)

Platform (`auth · verified · active`, `can:viewAny,School`):

| Method | URI | Name |
|--------|-----|------|
| GET | `/admin/schools` · `/admin/schools/create` · `/admin/schools/{school}` | `admin.schools.index` / `.create` / `.show` |
| POST | `/admin/schools` | `admin.schools.store` |

Tenant-scoped (`… · tenant`):

| Method | URI | Name |
|--------|-----|------|
| GET/POST | `/members/create` · `/members` | `members.create` / `members.store` |
| GET/PATCH | `/settings/school` | `settings.school.edit` / `.update` |
| GET/POST | `/settings/academic-sessions` | `academic-sessions.index` / `.store` |
| PATCH | `/settings/academic-sessions/{session}` | `academic-sessions.update` |

## Tests

211 passing. New in M5: `Feature/Platform/{SchoolProvisioning,SchoolProvisioner}Test`,
`Feature/Members/AddMemberTest`, `Feature/Settings/{SchoolSettings,AcademicSession}Test`,
`Feature/Onboarding/OnboardingChecklistTest`. `InteractsWithTenancy` gained a
`stranger()` helper. M1–M4 tests green.

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Next milestone (Domain Modules): each new school-owned table follows the
  `BelongsToSchool` convention; `academics.*` / `student.*` / … permissions move
  from dormant to enforced in their own modules.
- Invitations & brand-new-account onboarding; school suspension.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
- Audit logging of membership / role / provisioning changes.
