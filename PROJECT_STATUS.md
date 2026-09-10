# Project Status

_Last updated: 2026-09-10_

## Current milestone

**Milestone 1 — Platform Foundation: COMPLETE.**

Next up: **Milestone 2 — Authentication & User Foundation** (not started; do not
begin without picking it up explicitly). See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-10)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Composer | 2.x (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.22.0 / 10.9.4 |
| Database | MySQL 8.4 `schoolmanagement_db` (framework tables only) |
| Tests | `php artisan test` — 14 passing |
| Build | `npm run build` — passing |
| Formatting | `vendor/bin/pint --test` — passing |

## Delivered in Milestone 1

- **Inspection** of the existing skeleton; environment confirmed (above).
- **Conventions** documented in `CLAUDE.md` + `docs/architecture.md` and seeded on
  disk: `app/Http/Requests`, `app/Policies`, `app/Services`, `app/Support`.
- **Tenant seam:** `App\Support\Tenancy\TenantContext`, request-scoped singleton
  (bound in `AppServiceProvider`). Full multi-school implementation is Milestone 3.
- **Application shell:** `resources/views/components/layouts/app.blade.php`
  (desktop sidebar+header+main, mobile drawer via Alpine) and
  `…/layouts/guest.blade.php`.
- **UI kit:** `button, input, alert, badge, card, page-header, empty-state,
  spinner, modal, confirm` Blade components.
- **Health check:** `GET /health` (`HealthController`) → JSON status + DB probe,
  alongside the framework's `/up`.
- **Production-safety wiring:** strict models & no-lazy-loading outside
  production, `forceScheme('https')` in production.
- **Testing foundation:** PHPUnit; example tests replaced with real foundation
  tests (health, home page, UI components, tenant context, container binding).
- **Config / git hygiene:** `.env.example` aligned to the MySQL setup with
  placeholders only; `.gitignore` reviewed (`.env`, keys, build output, vendor,
  node_modules all ignored); no secrets tracked.
- **Documentation structure:** `docs/architecture.md`, `database-design.md`,
  `roadmap.md`, `security.md`, `scalability.md`, `ui-ux-guidelines.md`;
  `CLAUDE.md` and `AGENTS.md` rewritten as development-only guides.

## Explicitly NOT done (by design)

Auth flows · roles/permissions (no Spatie Permission yet) · `schools` table or
any tenant middleware/global scope · students/parents/teachers/classes/
attendance/fees/payments/results/CBT/portals · Paystack · Redis/queues/object
storage infrastructure · any school-website functionality · any 500-school limit.

## Database

Framework tables only (`users`, `sessions`, `password_reset_tokens`, `cache*`,
`jobs*`, `failed_jobs`, `migrations`). No new migrations in this milestone.

## Routes

| Method | URI | Name | Handler |
|--------|-----|------|---------|
| GET | `/` | `home` | `welcome` view |
| GET | `/health` | `health` | `HealthController` |
| GET | `/up` | — | framework (built-in) |

## Known follow-ups / recommendations

- `schoolmanagement_db` is a shared local dev database; confirm staging/prod get
  their own and that `DB_*` are set via environment, never committed.
- Tests that render views call `$this->withoutVite()`, so `php artisan test`
  does not require a built manifest. A real CI pipeline should still run
  `npm ci && npm run build` to catch asset-build regressions.
- Consider adding Pint + PHPUnit (+ `npm run build`) to a CI workflow in
  Milestone 2.
