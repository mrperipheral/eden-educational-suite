# Feature / Module Activation

Status: **Milestone 7 — complete.** Each school can turn the application's
feature modules on or off independently. Built entirely on the M3/M4 seams
(`TenantContext`, `BelongsToSchool`, permissions/Gate) — no new authorization or
tenancy mechanism, no new packages, no Redis/queues.

M7 ships the **activation system**, not the modules themselves. Every domain
module (Students, Attendance, Fees, …) is a later milestone.

## 1. The pieces

| Concern | Where |
|---------|-------|
| Catalogue (code-defined) | `App\Enums\Module` — 14 cases, each with label / description / group / dependencies / default / availability |
| Per-school state | `school_modules` table / `App\Models\SchoolModule` (`BelongsToSchool`) — one row *only* where a school overrides a default |
| Resolver | `App\Support\Modules\SchoolModules` — request-scoped, reads once, memoised |
| Admin screen | `App\Http\Controllers\SchoolModuleController` — `GET/PATCH /settings/school/modules` |
| Validation | `App\Http\Requests\Settings\UpdateSchoolModuleRequest` + dependency checks in the controller |
| Reuse for future modules | `module:` route middleware (`App\Http\Middleware\EnsureModuleEnabled`) · `@module('…')` Blade directive · `SchoolModules::enabled()` |
| Migration | `2026_09_15_100000_create_school_modules_table` |

```
Module::cases()               ← the catalogue (code)
        │  enabledByDefault()
        ▼
school_modules row?  ──no──►  use the catalogue default
        │ yes
        ▼
SchoolModules::enabled(Module) ← one query per request, memoised, tenant-scoped
        │
        ├── UI: Modules admin page (toggle, gated school.settings.update)
        ├── routes: ->middleware('module:attendance')
        └── views: @module('attendance') … @endmodule
```

## 2. The catalogue (`App\Enums\Module`)

Adding a module is a new enum case plus its metadata — nothing else in the
activation system changes.

| Module | id | Group | Default | Depends on |
|--------|-----|-------|:-------:|------------|
| Academic Management | `academics` | Academics | on | — |
| Student Management | `students` | People | on | academics |
| Parent / Guardian Management | `guardians` | People | on | students |
| Teacher / Staff Management | `staff` | People | on | academics |
| Timetable | `timetable` | Academics | **off** | academics, staff |
| Attendance | `attendance` | Academics | on | academics, students |
| Assessments | `assessments` | Assessment | on | academics, students |
| Results & Report Cards | `results` | Assessment | on | assessments |
| Fees & Payments | `fees` | Finance | on | students |
| Learning Materials | `learning-materials` | Academics | **off** | academics |
| CBT / Online Examinations | `cbt` | Assessment | **off** | assessments |
| Notifications | `notifications` | Communication | on | — |
| Parent Portal | `parent-portal` | Portals | on | guardians |
| Student Portal | `student-portal` | Portals | on | students |

- **`isAvailable()`** — whether the functionality behind the module is actually
  built. Each domain milestone flips its own module to `true` when it ships
  something usable; the admin screen badges the rest **Planned**. As of **M14**:
  `academics` (`docs/academic-foundation.md`), `students`
  (`docs/student-management.md`), `guardians` (`docs/guardian-management.md`),
  `staff` (`docs/teacher-management.md`), `timetable`
  (`docs/timetable-management.md`), `attendance`
  (`docs/attendance-management.md`) and `assessments`
  (`docs/assessment-management.md`); everything else is still Planned. `timetable`
  is available but stays **off by default** — a specialised opt-in; `attendance`
  and `assessments` are **on by default**. `assessments` depends on `academics` +
  `students` only — not `timetable` / `attendance` / `results` / `cbt`.
- **Defaults** lean toward a private nursery/primary/secondary school: core
  management on, specialised tools (timetable, learning materials, CBT) off. A
  freshly onboarded school writes **zero** rows — every module simply uses its
  default until the school changes one.
- The catalogue is *not* Nigeria-specific and carries no academic-structure
  assumptions.

## 3. Storage — override-only

`school_modules`: `id, school_id, module (string 40), enabled (bool), timestamps`,
`unique(['school_id', 'module'])` (also the lookup index — `school_id` leads).

A row exists **only** when a school has explicitly set a module away from — or
deliberately pinned it to — the catalogue default. `SchoolModule` is
`BelongsToSchool`: `school_id` is stamped from `TenantContext` on create and
immutable after; it is never in `$fillable` and never read from input. `module`
is stored as a plain string (not cast) so a retired identifier can never break a
page load.

## 4. The resolver (`SchoolModules`)

Registered `scoped()` — one instance per request. `enabled(Module)` /
`states()` read the school's override rows **once** (keyed by the active school
id) and serve everything else from memory; `set(Module, bool)` upserts the one
row and updates the cache. Reads are tenant-scoped by `SchoolScope` and fail
closed (`MissingTenantContextException`) with no context.

```php
app(SchoolModules::class)->enabled(Module::Attendance);   // bool
app(SchoolModules::class)->states();                       // ['academics' => true, …]
```

## 5. Administration screen

`/settings/school/modules` — a fourth-and-a-bit tab in the school-settings
sub-nav.

| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/settings/school/modules` | `settings.school.modules.edit` | `school.settings.view` |
| PATCH | `/settings/school/modules/{module}` | `settings.school.modules.update` | `school.settings.update` |

- Modules are grouped (People / Academics / Assessment / Finance / Communication
  / Portals). Each row shows name, description, an **Available / Planned** badge,
  an **Enabled / Disabled** badge, its dependency list, and (for editors) an
  Enable / Disable button — one small `PATCH` form per module.
- **Planned** modules are still toggleable: the switch is forward-looking
  configuration, and the badge + the page's intro text make clear the feature is
  not built yet. No control implies a module is implemented — there are no
  "open module" / "configure" links.
- `{module}` is validated in the controller against `Module::tryFrom()`; an
  unknown identifier is a **404**.
- View-only roles (`school.settings.view` without `.update`) get the page with
  no toggle controls and a read-only note.

### Dependencies

Checked in the controller against the school's current states:

- **enable** `X` → every module in `X->dependencies()` must already be enabled,
  else a `module` validation error ("Enable *Assessments* first…").
- **disable** `X` → no *enabled* module may list `X` as a dependency, else a
  `module` validation error ("Disable *Results* first…").

Single-level, no cascade — deliberately simple. The dependency graph is a small
DAG (asserted acyclic by a test), and every default-on module has all its
dependencies default-on (also tested).

## 6. Reuse by future modules

Two orthogonal checks — a domain route typically has **both**:

```php
Route::middleware(['tenant', 'module:academics'])->group(function () {
    Route::get('academic/levels', …)->can('academics.view');
});
```

- **`module:academics`** middleware — is the module switched on for this school?
  If not, the route 404s (the feature is not part of that school's app).
- **`->can('academics.view')`** — may *this user* do it? (M4, unchanged.)

M8's Academic Foundation, M9's Student Management, M10's Guardian Management,
M11's Teacher Management, M12's Timetable Management, M13's Attendance Management
and M14's Assessment & Assignments are the real consumers of this seam — see
`docs/academic-foundation.md` §6, `docs/student-management.md` §6,
`docs/guardian-management.md` §6, `docs/teacher-management.md` §6,
`docs/timetable-management.md` §7, `docs/attendance-management.md` §8 and
`docs/assessment-management.md` §9. `module:guardians` depends on
`module:students`; `module:students` and `module:staff` depend on
`module:academics`; `module:timetable` depends on both `module:academics` and
`module:staff`; `module:attendance` and `module:assessments` depend on
`module:academics` and `module:students` — **not** `module:timetable`.

`@module('attendance') … @endmodule` is the Blade equivalent for nav/dashboards.

**Activation grants nothing.** Turning a module on does not give any user a
permission; turning it off does not take one away. M4 authorization stays the
sole authority on "may this user…". (Tested.)

## 7. Authorization & tenancy

- Reuses `school.settings.view` / `school.settings.update` — module activation is
  school configuration, exactly like the M6 settings sections. **No new
  permission.** School Admin manages; Principal & Bursar are read-only;
  Teacher / Staff / Parent / Student get 403.
- Platform Admin operates only through a selected tenant context: no active
  school → school-picker redirect; in-context → full access, scoped to that one
  school.
- `school_id` is never accepted from input; School A can neither read nor change
  School B's module state (tested, both the resolver and the HTTP layer).

## 8. Performance

- One indexed read of `school_modules` per request, memoised for the rest of it
  (`SchoolModules`, request-scoped). `enabled()` / `states()` / `@module` /
  `module:` middleware all share that single load. Verified by a query-count
  test.
- No row at all for a school on catalogue defaults — the common case costs one
  empty result set.
- `unique(['school_id','module'])` is the only index needed.
- No Redis, no queues, no cache layer.

## 9. Decisions

| Decision | Why |
|----------|-----|
| Catalogue is an enum, not a table or config file | modules are behaviour; the list is reviewed like any code, and `isAvailable()` / dependencies live next to it |
| `school_modules` is override-only | a new school needs zero writes; "default" stays a single source of truth in the enum |
| Reuse `school.settings.*`, no new permission | activation is configuration; keeps the permission surface small (consistent with M5/M6) |
| `module` column not cast to the enum | a retired/unknown identifier must never 500 a page — the resolver skips it, failing safe to defaults |
| Toggles shown for Planned modules, clearly badged | the switch is configuration, not a claim of implementation; zero UI change when a module ships |
| Single-level dependency validation, no cascade | "keep it simple" — reject with a clear message, let the admin resolve the order |
| `module:` middleware + `@module` directive shipped now | the reusable seam every future domain module needs; each is a few lines and independently tested |
| Resolver re-resolves `TenantContext` per read, cache keyed by school id | correct even if the scoped instance outlives one context (console, tests, Octane); still one query per request |

## 10. Deferred

- The modules themselves (every domain milestone).
- Bulk "enable/disable all" / preset bundles.
- Disable-with-cascade or an "are you sure, this will hide X" confirmation flow.
- Per-module configuration sub-pages (belong to each module's milestone).
- An audit trail of who toggled what (part of platform-wide audit logging).
- Platform-admin defaults / plan-based module entitlements (subscription/billing
  is its own later concern).
