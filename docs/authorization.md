# Roles & Permissions

Status: **Milestone 4 — complete.** Permission-based authorization for the fixed
role set, composed with the strict `TenantContext` from Milestone 3.

## 1. The pieces

| Concern | Where |
|---------|-------|
| Permission vocabulary | `App\Enums\Permission` (code-defined, static — no DB table) |
| Roles (permission bundles) | `App\Enums\Role` |
| Per-school assignment | `school_user.role` column / `App\Models\SchoolUser` pivot |
| Platform owner | `users.is_platform_admin` (unchanged from M3 — *not* a role) |
| Runtime check | `User::hasPermission()` / `roleIn()` / `permissionsIn()` / `canGrantRole()` |
| Gate wiring | `App\Providers\AuthServiceProvider` — one `Gate::define()` per permission |
| Fine-grained rules | `App\Policies\MembershipPolicy` (self / escalation guards) |
| Features that use it | Members management (`/members*`), school settings + academic sessions (`school.settings.*`), school provisioning (`SchoolPolicy`) |

```
Request → auth · verified · active · tenant  (TenantContext::set(School))
                                        │
$user->can('finance.manage')  ──────────┤
@can('member.view')            Gate ability → User::hasPermission(Permission, TenantContext::id())
$this->authorize('assignRole') ─────────┤        │
                                        │        ├─ platform admin?  → true  (within the entered school only)
                                        │        └─ role in this school → Role::grants(Permission)
                                        ▼
                        allowed only *within the school currently in context*
```

## 2. Why not Spatie laravel-permission

Evaluated and **not adopted**. Spatie is excellent for runtime-editable,
database-stored roles/permissions, but for this architecture it adds friction:

| Concern | This project | Spatie |
|---------|--------------|--------|
| Permission set | fixed, code-defined (an enum) — reviewed like any code | DB rows you seed and must keep in sync with code |
| Roles | fixed set, static permission bundles | DB rows, runtime-editable |
| Multi-tenancy | one seam — `TenantContext` (request-scoped) | its "Teams" feature adds a **second** ambient tenant id (`PermissionRegistrar::setPermissionsTeamId()`, a global static) that must be kept in lock-step with `TenantContext` — a security-critical duplication |
| Schema | one nullable column on an existing pivot | 5 tables + morph columns + `team_id` |
| Surface | `hasPermission()` + a Gate loop (~40 lines) | `HasRoles` trait, wildcard permissions, its own middleware, a permission cache to invalidate |

The in-house version is smaller, has exactly one tenant seam, and every
permission check demonstrably runs through `TenantContext`. If runtime-editable
per-school permissions are ever needed, revisit — the `Permission`/`Role` enums
and `hasPermission()` are the only things that would change.

## 3. Permissions (`App\Enums\Permission`)

24 coarse permissions, dotted strings, grouped in the enum:

- **School config (enforced — M5/M6):** `school.settings.view`,
  `school.settings.update` — gate all school settings sections (profile,
  branding, regional — M6) *and* the academic sessions. School Admin holds
  `.update`; Principal and Bursar hold only `.view` (read-only settings). No
  finer-grained settings permission was added in M6 — editing school-wide
  configuration is a School Admin function; revisit if a school needs a
  Principal who can edit (see `docs/school-settings.md` §5).
- **People & access (enforced):** `member.view`, `member.assign-role`
  (also gates *adding* an existing user — M5), `member.remove`
- **Declared for later domain milestones** (not yet enforced — the modules that
  check them don't exist): `student.*`, `guardian.*`, `staff.*`, `academics.*`,
  `attendance.*`, `result.*`, `finance.*`, `portal.parent`, `portal.student`

They exist now so the role bundles are meaningful and testable. A domain
milestone that needs finer control adds a case and slots it into the relevant
`Role::permissions()` bundles — nothing else changes.

## 4. Roles (`App\Enums\Role`)

Seven per-school roles, each a static bundle of permissions plus a `tier`:

| Role | tier | Holds |
|------|-----:|-------|
| `school_admin` | 100 | **every** permission |
| `principal` | 80 | school settings (view), member view + assign-role, all student/guardian/academics/attendance/result permissions, finance (view) |
| `bursar` | 50 | school settings (view), student/guardian (view), finance (view + manage) |
| `teacher` | 50 | student/guardian (view), academics (view), attendance (view + record), result (view + enter) |
| `staff` | 30 | student (view), academics (view), attendance (view) |
| `parent` | 10 | `portal.parent` |
| `student` | 10 | `portal.student` |

- A user's role is **per school** (`school_user.role`). One role per school —
  multi-role-per-school is a future extension.
- A membership may have **no role** (`null`) → zero permissions in that school.
- `Role::SchoolAdmin` is always a superset of every other role (asserted by a test).

## 5. Platform admin

Unchanged primitive (`users.is_platform_admin`). Within a school they have
**entered** (a `TenantContext` is active), `hasPermission()` returns `true` for
every permission — so a platform admin can administer any school. This does not
weaken isolation:

- they must still select a school (`EnforceTenant` never auto-resolves them);
- `SchoolScope` still constrains every row they touch to that one school;
- with **no** active school, `hasPermission()` / every Gate ability returns
  `false` — there is no platform-wide bypass, and **no `Gate::before()`**.

## 6. Checking permissions

Always through the Gate / policies — **never role-name checks in controllers**.

```php
// route middleware
Route::get('members', ...)->can('member.view');

// controller / form request
$this->authorize('assignRole', [$membership, $role]);
$request->user()->hasPermission(Permission::MemberRemove);

// Blade (UI convenience only — the route is still protected)
@can('finance.manage') ... @endcan
```

`User::hasPermission(Permission, ?School)` — school defaults to the active
tenant; pass an explicit school for cross-context checks. Returns `false` (never
throws) when no school is in play, so it is safe in layouts and account pages.

Per-request the resolved role for a school is memoised on the `User` instance
(one indexed pivot query, then cached).

## 7. `MembershipPolicy`

Coarse "can touch the Members area" is the `member.*` Gate abilities. The policy
(`SchoolUser` model) adds the per-row rules:

| Ability | Rule |
|---------|------|
| `viewAny` | `member.view` |
| `add` | `member.assign-role` (coarse gate for adding an existing user — M5; the target role is checked in the controller via `canGrantRole()`) |
| `assignRole($membership, $target)` | not your own membership; `canGrantRole($target)` — **no privilege escalation** (target tier ≤ your role's tier); platform admins may grant any role |
| `remove($membership)` | not your own membership; `member.remove` |

The Members controller only ever loads `SchoolUser` rows for the **current**
school, so a cross-school membership can never reach the policy.

## 8. Members management (the milestone's concrete feature)

`GET /members` (`->can('member.view')`), `PATCH /members/{user}` (assign role),
`DELETE /members/{user}` (remove) — all under the `tenant` middleware.

- List is scoped to the current school, filterable by role, paginated (25/page).
- `{user}` is a global bind; the controller 404s if that user is not a member of
  the active school — so it doubles as tenant isolation and as
  "does this user exist" non-disclosure.
- Role changes go through `User::assignRoleInSchool()` (updates that one pivot
  row only); removal through `leaveSchool()`.
- The role `<select>` is pre-filtered to grantable roles **and** re-checked
  server-side.

## 9. Efficiency

- `hasPermission()` / `roleIn()`: one indexed query on the `school_user` PK per
  school, memoised for the rest of the request.
- The `(school_id, role)` index serves the Members list's role filter.
- Permissions and role bundles are in-process enums — no DB, no cache to
  invalidate, no extra tables.
- No Redis, no queues, no package.

## 10. Decisions

| Decision | Why |
|----------|-----|
| In-house enums, not Spatie | one tenant seam, static/code-defined permissions, minimal schema — see §2 |
| Permissions as a Gate ability each (loop), **no `Gate::before`** | `$user->can('x')` / `@can` / route `->can()` all work and stay tenant-composed; no blanket platform bypass |
| One role per (user, school), nullable | matches "roles are bundles"; multi-role is a rare need, deferred |
| Tier-based escalation guard (`target.tier ≤ granter.tier`) | models org hierarchy; the hard invariant "never grant a role above your own" is simple and testable |
| Platform admin = all permissions *within an entered school* | "retain platform-wide administration" without weakening row-level isolation (`SchoolScope` still applies) |
| `member.*` permissions enforced; the rest declared but dormant | the vocabulary the role bundles need, without starting domain modules |

## 11. Deferred

- Multi-role per school; custom/per-school roles; runtime-editable permissions.
- Invitation / brand-new-account flow (M5 adds *existing* users only).
- Admin UI for `users.status` and `users.is_platform_admin`.
- Enforcing the dormant permissions — happens in each domain module's milestone.
- Audit logging of role changes.
- `@role` / permission Blade directives beyond the built-in `@can`.
