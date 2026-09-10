# AGENTS.md

Guidance for coding agents working in this repository.

The full development guide is **[CLAUDE.md](CLAUDE.md)** and the documents under
**[docs/](docs/)**. Read them before making changes. Key points:

- **Product:** multi-school School Management SaaS (management + portals only).
  No public website / website-builder features — ever.
- **Stack:** PHP 8.3+, Laravel 13, MySQL, Blade + Tailwind v4 + Alpine.js + Vite,
  PHPUnit, Pint. No React/Vue/Inertia.
- **Tenancy** (`docs/tenancy.md`): one shared DB, logical isolation via
  `school_id`. School-owned models `use BelongsToSchool`; never put `school_id`
  in `$fillable`, never `where('school_id')` by hand, never read `school_id` from
  the request. Active school = `App\Support\Tenancy\TenantContext`; tenant routes
  get the `tenant` middleware.
- **Security:** server-side authorization, Form Request validation, CSRF on every
  write. Frontend hiding is not authorization.
- **Auth:** native Laravel, no starter package. See `docs/authentication.md`.
- **Authorization** (`docs/authorization.md`): check **permissions** via the Gate
  (`$user->can('x')`, `@can`, `->can()`), never role names. `App\Enums\Permission`
  + `App\Enums\Role` (bundles), assigned per school on `school_user.role`, every
  check composed with `TenantContext`. No `Gate::before`.
- **Onboarding / platform admin:** `docs/onboarding.md`. `/admin/*`
  (`app/Http/Controllers/Platform/`) is platform-admin only and not
  tenant-scoped; school-owned data is created/edited only inside a tenant context.
- **Scope discipline:** work the current milestone only. `PROJECT_STATUS.md` and
  `docs/roadmap.md` say where we are. Milestones 1–10 (Platform Foundation,
  Authentication, Multi-School Tenant Isolation, Roles & Permissions, School
  Onboarding, School Settings — `docs/school-settings.md`, Feature / Module
  Activation — `docs/module-activation.md`, Academic Foundation —
  `docs/academic-foundation.md`, Student Management —
  `docs/student-management.md`, Guardian / Parent Management —
  `docs/guardian-management.md`) are done.
- **Module activation ≠ authorization:** a domain route checks both its
  `App\Enums\Module` flag (`module:` middleware / `@module`) and its permission;
  enabling a module grants nothing.
- **Tenant-owned route ids:** resolve by `Model::query()->findOrFail($id)` in the
  controller (tenant-scoped, 404s cross-school), not route-model binding — it
  runs before the `tenant` middleware.
- **Before finishing:** `php artisan test`, `vendor/bin/pint`, `npm run build`.
- This file and `docs/` are development-only and must never become a runtime
  dependency of the application.
