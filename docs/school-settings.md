# School Settings & Configuration

Status: **Milestone 6 — complete.** Expands the M5 `SchoolSetting` foundation
into the full per-school configuration record, split into focused sections.
Built entirely on the M1–M5 architecture (tenant context, permissions/Gate,
policies, the storage abstraction) — no new authorization or tenancy mechanism,
no new packages, no Redis/queues.

## 1. The pieces

| Concern | Where |
|---------|-------|
| Model | `App\Models\SchoolSetting` (1:1 with `School`, `BelongsToSchool`) |
| Controller | `App\Http\Controllers\SchoolSettingsController` |
| Form Requests | `App\Http\Requests\Settings\UpdateSchool{Profile,Branding,Regional}Request` |
| Reference data | `config/school-settings.php` (currencies, countries, locales, defaults) |
| Enums | `App\Enums\DateFormat` (string, a PHP `date()` format), `App\Enums\Weekday` (int, Carbon numbering) |
| Views | `resources/views/settings/school/{profile,branding,regional}.blade.php` + `_nav.blade.php` |
| Migration | `2026_09_14_100000_add_configuration_to_school_settings_table` |

## 2. Sections & routes

All tenant-scoped (`auth · verified · active · tenant`). `school.settings.view`
reads; `school.settings.update` writes. Route `->can()` **and** every Form
Request `authorize()` enforce it.

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

Three pages rather than one long form (`academic-sessions.*` is the fourth tab in
the shared `_nav` partial). Each page shows an editable form for
`school.settings.update` holders and a read-only `<dl>` fallback for view-only
roles.

## 3. Columns

`school_settings` — one row per school (1:1, unique `school_id`). Typed columns,
not a JSON blob: every value is validated, defaulted, and queryable by later
modules.

### Profile (`UpdateSchoolProfileRequest`)

| Column | Rules |
|--------|-------|
| `contact_email` | nullable, `email:rfc`, max 255 |
| `contact_phone` | nullable, max 40, `regex` digits/`+`/`()`/`-`/space |
| `website_url` | nullable, `url:http,https`, max 255 |
| `address_line1`, `address_line2` | nullable, max 255 |
| `city`, `state` | nullable, max 120 |
| `postal_code` | nullable, max 20 |
| `country` | nullable, ISO 3166-1 alpha-2, must be a key of `config('school-settings.countries')`; upper-cased in `prepareForValidation` |

The school's `name` / `slug` / `status` are **not** here — they are
platform-controlled tenant identifiers, shown read-only on the Profile page.

### Branding (`UpdateSchoolBrandingRequest`)

| Column | Rules |
|--------|-------|
| `brand_color` | nullable, `regex:/^#[0-9a-fA-F]{6}$/`; lower-cased in `prepareForValidation` |
| `logo_path` | **guarded** — never mass-assignable; written only by `SchoolSetting::putLogo()` / `clearLogo()` |
| `logo` (input, not a column) | nullable, `file`, `mimetypes:image/jpeg,image/png,image/webp` (content sniff, not extension), `max:2048` KB, `dimensions` 48–1600px each side |

### Regional (`UpdateSchoolRegionalRequest`)

| Column | Rules | Default |
|--------|-------|---------|
| `timezone` | required, in `timezone_identifiers_list()` | `Africa/Lagos` |
| `locale` | required, key of `config('school-settings.locales')` | `en` |
| `currency` | required, key of `config('school-settings.currencies')` (ISO 4217); upper-cased | `NGN` |
| `date_format` | required, `App\Enums\DateFormat` | `d/m/Y` |
| `week_starts_on` | required, `App\Enums\Weekday` (int; selects cast to int in `prepareForValidation`) | `1` (Monday) |
| `academic_year_start_month` | required, integer 1–12 | `9` (September) |

`academic_year_start_month` is stored here for the **Academic Management**
milestone to read when it proposes sessions — M6 only stores it, it does not
build sessions/terms.

Defaults lean toward the initial Nigerian market but nothing is Nigeria-only:
every list carries the common alternatives and each school picks its own values.
Model `$attributes`, the migration column defaults, and
`config('school-settings.defaults')` are kept in sync.

## 4. Branding upload & serving

- Stored on the **private `local` disk** (`config/filesystems.php`), never the
  public disk: `storage/app/private/school-logos/{tenant_id}/{random}.{ext}`.
- Served only through `GET /settings/school/branding/logo`, gated
  `school.settings.view`, via `Storage::disk('local')->response()` with
  `Cache-Control: private`. **No path parameter** → no traversal; the handler
  only ever serves the current tenant's `logo_path`.
- Cross-school isolation: a School B user in B's context hits B's (empty)
  branding and 404s — school A's file is unreachable (tested).
- `putLogo()` deletes the previous file on replace; `clearLogo()` deletes on
  remove. Both go through the model, never mass assignment.

## 5. Authorization & tenancy

- **No new mechanism.** `school.settings.view` / `.update` are the existing M4
  permissions; `TenantContext` + `SchoolScope` do the isolation.
- **School Admin** manages settings (`school.settings.update`).
- **Principal** and **Bursar** hold `school.settings.view` → read-only access.
  Editing school-wide administrative/financial configuration is a School Admin
  function; no new fine-grained permission was added (keeps the permission
  surface small — see `docs/authorization.md`). Revisit if a school asks for a
  Principal who can edit.
- **Teacher / Staff / Parent / Student** — no access (403).
- **Platform Admin** operates only through a selected tenant context: no active
  school → redirected to the school picker; in-context → full access, scoped to
  that school.
- `school_id` is **never** read from input — stamped by `BelongsToSchool` on
  create, immutable on update.

## 6. Performance

- `SchoolSetting` is 1:1 on the already-unique `school_id` — no extra index.
- One `firstOrCreate` per request; no N+1 (the read pages touch a single row).
- Reference lists are config (config-cacheable), not DB lookups.

## 7. Decisions

| Decision | Why |
|----------|-----|
| Extend `SchoolSetting`, typed columns | M6 brief; validated/queryable, no duplicate settings system, no JSON blob |
| Three section pages, not one form or JS tabs | focused forms, independent validation, simpler read-only fallback |
| `logo` on the private disk, served via a gated route with no path param | prevents unauthorized access and traversal; reuses the M3 storage abstraction |
| Currencies/countries/locales in `config/`, not a package or table | small, rarely-changed, safe to config-cache; no new dependency |
| `DateFormat` value = a `date()` string; `Weekday` = Carbon numbering | callers format/compute directly with no mapping layer |
| Principal/Bursar read-only, no new permission | administrative config is a School Admin function; smaller permission surface |
| `academic_year_start_month` lives in settings | it is a school-wide calendar preference; Academic Management reads it |

## 8. Deferred

- Grading scheme / result templates, term structure, holiday calendar — the
  **Academic Management** milestone.
- Notification channel config, payment-gateway credentials — their own modules
  (the brief lists them as "foundations where they genuinely belong" — none
  genuinely belonged in school settings yet beyond what is above).
- Feature activation / per-school module toggles.
- Applying `date_format` / `timezone` / `locale` app-wide at render time (the
  values are stored and validated; wiring display formatting is incremental).
- Logo CDN / `s3` disk in production (the disk abstraction already supports it).
