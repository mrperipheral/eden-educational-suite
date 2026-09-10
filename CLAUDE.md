# School Management Platform — Development Guide

> **Development-only documentation.** This file (and everything under `docs/`) is
> guidance for people and coding agents working on the codebase. It is **never** a
> runtime dependency: no application code reads it, and the app must build, boot
> and pass tests with these files absent.

## What this project is

A production-quality, multi-school **School Management SaaS**. Initial target:
private nursery, primary and secondary schools in Nigeria. The architecture stays
flexible enough to support other institution types later.

The product is **school management and portals only**. Explicitly **out of scope**:
public school websites, website builder / themes / engine, public school pages,
public content management.

## Tech stack

| Area        | Choice |
|-------------|--------|
| Language    | PHP 8.3+ |
| Framework   | Laravel 13 |
| Database    | MySQL / MariaDB (one shared DB, logical tenant isolation) |
| Views       | Blade |
| CSS         | Tailwind CSS v4 (`@tailwindcss/vite`, config lives in `resources/css/app.css`) |
| JS          | Alpine.js (the only JS framework — no React / Vue / Inertia without an explicit decision in `docs/architecture.md`) |
| Build       | Vite |
| Tests       | PHPUnit (`php artisan test`) |
| Formatting  | Laravel Pint (`vendor/bin/pint`) |

## Local setup

PHP / Composer / Node are provided here by Laragon; `php`, `composer` and `node`
may not be on `PATH` in every shell. Laragon paths:
`C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64`, `C:\laragon\bin\composer`,
`C:\laragon\bin\nodejs\node-v22`.

```sh
composer install
npm install
cp .env.example .env        # then set DB_* and run key:generate
php artisan key:generate
php artisan migrate
npm run build               # or: npm run dev
php artisan test
```

## Project conventions

See `docs/architecture.md` for the full rationale. In short:

- **Controllers** (`app/Http/Controllers`) — thin. Resourceful where it fits
  (`index/create/store/show/edit/update/destroy`). No business logic beyond
  orchestration; push anything non-trivial into a service.
- **Form Requests** (`app/Http/Requests`) — every write action validates through a
  Form Request. Authorization that depends on the request lives in `authorize()`.
- **Policies** (`app/Policies`) — model authorization. Controllers call
  `authorize()` / `Gate`. **Frontend hiding is never authorization.**
- **Services** (`app/Services`) — multi-step or cross-model business operations.
  Plain classes, constructor-injected. Do not create a service/repository layer
  for simple CRUD.
- **Models** (`app/Models`) — guard mass assignment (`$fillable` or the PHP 8
  `#[Fillable]` attribute, as `User` does). Casts via `casts()`. Tenant-owned
  models will use the `BelongsToSchool` trait (later milestone) — never a manual
  `where('school_id', …)` scattered through the codebase.
- **Tenancy** — `App\Support\Tenancy\TenantContext` (request-scoped singleton) is
  the single source of truth for "which school are we acting as". Resolve it from
  the container; never read a raw `school_id` from user input.
- **Enums** (`app/Enums`) — closed value sets backed by string columns
  (`UserStatus`). Add behaviour to the enum, not `match` ladders in callers.
- **Middleware** (`app/Http/Middleware`) — cross-cutting request guards; register
  aliases in `bootstrap/app.php`.
- **Views** — pages in `resources/views/<area>/`, layouts and reusable UI as Blade
  components in `resources/views/components/`. Signed-in pages use
  `<x-layouts.authenticated>`; pre-auth pages use `<x-layouts.guest>`.
- **Auth** — native Laravel, no starter package. Flow routes in `routes/auth.php`;
  see `docs/authentication.md`. Passwords via `Password::defaults()`. Check
  **permissions**, never role names (roles/permissions not built yet).
- **Tests** — feature tests for every route and each authorization boundary; unit
  tests for services, enums and value objects. Tenant isolation gets explicit
  cross-tenant "cannot see / cannot touch" tests once schools exist. Tests that
  render views call `$this->withoutVite()`.

## Guardrails

- Server-side authorization, validation and CSRF on every state change.
- No secrets in the repo. `.env` is git-ignored; `.env.example` carries
  placeholders only.
- No artificial tenant/school limit anywhere.
- Design for scale (indexes, pagination, no N+1, caching, queues later) but do not
  build infrastructure the current milestone does not need.

## Milestones

Tracked in `PROJECT_STATUS.md` and `docs/roadmap.md`. **Milestones 1 (Platform
Foundation) and 2 (Authentication & User Foundation) are complete.** Do not start
Multi-School Core, Roles & Permissions, Onboarding or any domain module without
picking up the next milestone explicitly.
