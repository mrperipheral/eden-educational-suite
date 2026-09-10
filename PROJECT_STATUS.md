# Project Status

_Last updated: 2026-09-10_

## Current milestone

**Milestone 6 — School Settings & Configuration: COMPLETE.**

Next up: **Domain Modules** (Milestone 7+) — Staff, Students & Guardians,
Classes/Subjects, Enrolment, Attendance, Results, Fees, CBT, portals. Not
started — do not begin without picking it up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-10)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 `schoolmanagement_db` |
| Tests | `php artisan test` — 231 passing |
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
- **M5 — School Onboarding** (`school-onboarding-complete`) — `docs/onboarding.md`.
- **M6 — School Settings & Configuration** (this milestone,
  `school-settings-complete`) — `docs/school-settings.md`; see below.

## Delivered in Milestone 6

Expands the M5 `SchoolSetting` foundation (1:1, `BelongsToSchool`) into the full
per-school configuration record. Typed columns, no JSON blob. Built on the
existing M3/M4 tenant + permission architecture — no new authorization or
tenancy mechanism, no new packages, no Redis/queues.

- **Migration** `2026_09_14_100000_add_configuration_to_school_settings_table` —
  13 columns: `address_line1/2`, `city`, `state`, `postal_code`, `country`,
  `website_url`, `logo_path` (guarded), `brand_color`, `currency`, `date_format`,
  `week_starts_on`, `academic_year_start_month`. No new index (1:1 on unique
  `school_id`). Column defaults kept in sync with the model `$attributes` and
  `config('school-settings.defaults')`.
- **`App\Models\SchoolSetting`** — expanded `$fillable` (still excludes
  `school_id`, `completed_at`, `logo_path`); `$attributes` defaults; casts
  `date_format` → `App\Enums\DateFormat`, `week_starts_on` → `App\Enums\Weekday`.
  `putLogo()` / `clearLogo()` / `hasLogo()` manage the private-disk file;
  `markReviewed()` unchanged.
- **Enums** — `App\Enums\DateFormat` (string-backed, value is a PHP `date()`
  format), `App\Enums\Weekday` (int-backed, Carbon 0=Sun…6=Sat numbering).
- **`config/school-settings.php`** — curated `currencies` (16, ISO 4217),
  `countries` (17, ISO 3166-1 alpha-2), `locales` (4), `defaults`. Config-cacheable.
- **`SchoolSettingsController`** — three sections, each with a read page
  (`school.settings.view`) and a write (`school.settings.update`):
  - **Profile** — `GET/PATCH /settings/school` (`UpdateSchoolProfileRequest`).
  - **Branding** — `GET/PATCH /settings/school/branding`
    (`UpdateSchoolBrandingRequest`), `DELETE /settings/school/branding/logo`,
    `GET /settings/school/branding/logo` (serves the logo, gated, no path param).
  - **Regional** — `GET/PATCH /settings/school/regional`
    (`UpdateSchoolRegionalRequest`).
- **Branding upload** — logo validated by content MIME (jpeg/png/webp), size
  (≤2 MB), dimensions (48–1600px); stored on the private `local` disk at
  `school-logos/{tenant_id}/…`; served only through the gated route (no path
  parameter → no traversal; tenant-scoped).
- **Views** — `resources/views/settings/school/{profile,branding,regional}.blade.php`
  + shared `_nav.blade.php` sub-nav (Profile · Branding · Regional · Academic
  sessions). Editable form for `.update` holders, read-only `<dl>` fallback for
  view-only roles. `academic-sessions.blade.php` switched to the shared nav; the
  old single `settings/school.blade.php` deleted.
- **Authorization** — School Admin edits; Principal & Bursar read-only (no new
  permission — documented in `docs/authorization.md` / `docs/school-settings.md`);
  Teacher/Staff/Parent/Student 403. Platform Admin only through a selected
  context (no context → school-picker redirect).
- **Seeder** — Alpha Academy now seeded with full settings values.
- **Docs** — new `docs/school-settings.md`; updated `architecture.md`,
  `security.md`, `database-design.md`, `authorization.md`, `tenancy.md`,
  `onboarding.md`, `roadmap.md`, `ui-ux-guidelines.md`, `CLAUDE.md`, `AGENTS.md`.

## Explicitly NOT done (by design)

Feature activation / per-school module toggles · grading scheme / result
templates / term structure / holiday calendar (Academic Management) ·
notification channel config · payment-gateway credentials (Fees module) ·
app-wide render-time application of `date_format` / `timezone` / `locale` ·
logo virus scanning · CDN / `s3` logo delivery · everything from the M5 "NOT
done" list (invitations, suspension, billing, domain modules, portals, …).

## Database

Milestone 6 adds 13 typed columns to `school_settings` (still 1:1, unique
`school_id`, no new index). No other schema changes. `logo_path` is guarded
(written only via the model).

## Routes (application, additions in M6)

Tenant-scoped (`auth · verified · active · tenant`):

| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/settings/school` | `settings.school.edit` | `school.settings.view` |
| PATCH | `/settings/school` | `settings.school.update` | `school.settings.update` |
| GET | `/settings/school/branding` | `settings.school.branding.edit` | `school.settings.view` |
| PATCH | `/settings/school/branding` | `settings.school.branding.update` | `school.settings.update` |
| DELETE | `/settings/school/branding/logo` | `settings.school.branding.logo.destroy` | `school.settings.update` |
| GET | `/settings/school/branding/logo` | `settings.school.branding.logo.show` | `school.settings.view` |
| GET | `/settings/school/regional` | `settings.school.regional.edit` | `school.settings.view` |
| PATCH | `/settings/school/regional` | `settings.school.regional.update` | `school.settings.update` |

(The M5 `settings.school.edit` / `.update` names are retained; the single
`PATCH /settings/school` now handles the Profile section only.)

## Tests

231 passing (was 211 at M5; +20 in M6, M1–M5 intact). New / changed:
`Feature/Settings/SchoolSettingsTest` (retargeted to the Profile section + shared
auth/isolation/protected-field coverage), new
`Feature/Settings/SchoolBrandingTest`, `Feature/Settings/SchoolRegionalTest`,
`Unit/Enums/DateFormatTest`, `Unit/Enums/WeekdayTest`.
`Feature/Onboarding/OnboardingChecklistTest` updated for the new profile payload.

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Apply the stored `timezone` / `locale` / `date_format` at render time
  (incremental; values are already stored & validated).
- Next milestone (Domain Modules): each new school-owned table follows the
  `BelongsToSchool` convention; `academics.*` / `student.*` / … permissions move
  from dormant to enforced in their own modules.
- Invitations & brand-new-account onboarding; school suspension.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
- Audit logging of settings / membership / role / provisioning changes.
- Logo: virus scanning, object-storage (`s3`) delivery in production.
