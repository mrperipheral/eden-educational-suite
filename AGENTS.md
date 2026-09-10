# AGENTS.md

Guidance for coding agents working in this repository.

The full development guide is **[CLAUDE.md](CLAUDE.md)** and the documents under
**[docs/](docs/)**. Read them before making changes. Key points:

- **Product:** multi-school School Management SaaS (management + portals only).
  No public website / website-builder features — ever.
- **Stack:** PHP 8.3+, Laravel 13, MySQL, Blade + Tailwind v4 + Alpine.js + Vite,
  PHPUnit, Pint. No React/Vue/Inertia.
- **Tenancy:** one shared database, logical isolation via `school_id`. Use
  `App\Support\Tenancy\TenantContext`; never trust a `school_id` from user input.
- **Security:** server-side authorization (Policies), Form Request validation,
  CSRF on every write. Frontend hiding is not authorization.
- **Scope discipline:** work the current milestone only. `PROJECT_STATUS.md` and
  `docs/roadmap.md` say where we are. Milestone 1 (Platform Foundation) is done.
- **Before finishing:** `php artisan test`, `vendor/bin/pint`, `npm run build`.
- This file and `docs/` are development-only and must never become a runtime
  dependency of the application.
