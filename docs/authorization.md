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
| Features that use it | Members (`/members*`), school settings + module activation (`school.settings.*`), academic structure (`/academic/*`, `academics.*` — M8), students (`/students/*`, `student.*` — M9), guardians (`/guardians/*`, `guardian.*` — M10), teachers (`/teachers/*`, `staff.*` — M11), timetable (`/timetables/*`, `timetable.*` — M12), attendance (`/attendance/*`, `attendance.*` — M13), assessments (`/assessments/*`, `assessment.*` — M14), results & report cards (`/results/*`, `result.*` — M15), Parent Portal (`/parent/*`, `portal.parent` — M16), school provisioning (`SchoolPolicy`) |

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

30 coarse permissions, dotted strings, grouped in the enum (order is
presentational only — nothing depends on it):

- **School config (enforced — M5/M6):** `school.settings.view`,
  `school.settings.update` — gate all school settings sections (profile,
  branding, regional — M6) and module activation (M7). School Admin holds
  `.update`; Principal and Bursar hold only `.view`.
- **Academic structure (enforced — M8):** `academics.view`, `academics.manage` —
  gate `/academic/*` (sessions, periods, levels, arms, subjects — moved here
  from `school.settings.*`). School Admin + Principal manage; Teacher + Staff
  read; Bursar / Parent / Student get 403. See `docs/academic-foundation.md`.
- **Students (enforced — M9):** `student.view`, `student.manage` — gate
  `/students/*` (records + enrollment history). School Admin + Principal manage;
  Bursar + Teacher + Staff read; Parent / Student get 403. See
  `docs/student-management.md`.
- **Guardians (enforced — M10):** `guardian.view`, `guardian.manage` — gate
  `/guardians/*` (guardian records + student ↔ guardian links). School Admin +
  Principal manage; Bursar + Teacher + Staff read; Parent / Student get 403.
  `guardian.view` was added to the Staff bundle in M10 (the third read-only role
  that already holds `student.view`). See `docs/guardian-management.md`.
- **Teachers (enforced — M11):** `staff.view`, `staff.manage` — gate
  `/teachers/*` (teacher records, optional account link, teaching-assignment
  foundation). School Admin + Principal manage; Bursar + Teacher + Staff read;
  Parent / Student get 403. `staff.manage` was added to Principal, `staff.view`
  to Bursar / Teacher / Staff in M11 (Principal already held `staff.view`). A
  Teacher role holder can *see* the staff area but cannot manage other teachers.
  See `docs/teacher-management.md`.
- **Timetable (enforced — M12):** `timetable.view`, `timetable.manage` — gate
  `/timetables/*` (weekly class/teacher schedules + conflict detection + a
  draft/published lifecycle). School Admin + Principal manage; Teacher + Staff
  read; **Bursar / Parent / Student get 403** (Bursar has no academic access, so
  no timetable access — the same shape as `academics.*`). `timetable.manage` +
  `timetable.view` were added to Principal, `timetable.view` to Teacher / Staff
  in M12. See `docs/timetable-management.md`.
- **Attendance (enforced — M13):** `attendance.view`, `attendance.record`,
  `attendance.manage` — gate `/attendance/*` (daily class registers + a
  draft/submitted lifecycle). School Admin + Principal manage (`.manage` adds
  reopening a locked register and recording for any class); Teacher records
  (class-scoped by an active M11 assignment via `AttendanceAuthorizer`); Staff
  reads; **Bursar / Parent / Student get 403**. `attendance.manage` was added to
  Principal in M13; `attendance.view` / `attendance.record` already sat on
  Teacher / Staff from M7. Attendance does **not** depend on the Timetable
  module. See `docs/attendance-management.md`.
- **Assessments & assignments (enforced — M14):** `assessment.view`,
  `assessment.record`, `assessment.manage` — gate `/assessments/*` (configurable
  categories, assessments with a draft/published/locked lifecycle, bulk score
  entry, and class assignments with completion tracking). School Admin +
  Principal manage (`.manage` adds category management, recording for any class +
  subject, and unlocking a locked assessment); Teacher records (scoped to their
  assigned `(level, subject)` by an active M11 assignment via
  `AssessmentAuthorizer`); Staff reads; **Bursar / Parent / Student get 403**.
  All three were added in M14 — `.manage` to Principal, `.view` + `.record` to
  Teacher, `.view` to Staff. Assessments do **not** depend on the Timetable,
  Attendance, Results or CBT modules. See `docs/assessment-management.md`.
- **Results & report cards (enforced — M15):** `result.view`, `result.enter`,
  `result.manage`, `result.publish`, `result.adjust` — gate `/results/*`
  (grading/weighting schemes, a result run's compile→review→approve→publish→
  lock lifecycle, a per-subject adjustment workflow, and report-card
  configuration + generation). School Admin + Principal hold all five;
  Teacher holds `.view` + `.enter` (a class-teacher comment, scoped to a
  class they hold an active M11 assignment for, via `ResultAuthorizer` — the
  same shape as `AttendanceAuthorizer`, but with no subject dimension since a
  class-teacher comment is class-wide); Staff holds `.view`; **Bursar /
  Parent / Student get 403**. Only `result.manage` and `result.adjust` are
  new this milestone — `.view` / `.enter` / `.publish` were already declared
  from M4's scaffolding. Results depend on the **Assessments module only**
  (not Timetable/Attendance/CBT). See `docs/results-report-cards.md`.
- **People & access (enforced):** `member.view`, `member.assign-role`
  (also gates *adding* an existing user — M5), `member.remove`
- **Parent Portal (enforced — M16):** `portal.parent` — gates `/parent/*` (a
  read-only, child-scoped window onto a parent's own children's published
  data). **No new permission** — `portal.parent` was declared since M4;
  `Role::Parent` is the only role holding it on its own (School Admin also
  holds it via its full bundle, but is never itself a `Guardian`, so it sees
  the same "no linked children" empty state as an unlinked parent — the
  permission and `App\Support\Portal\ParentPortalAuthorizer`'s
  Guardian-linkage check protect the portal at two independent layers).
  **Bursar / Principal / Teacher / Staff / Student / role-less get 403.** See
  `docs/parent-portal.md`.
- **Declared for later domain milestones** (not yet enforced — the modules that
  check them don't exist): `finance.*`, `portal.student`

They exist now so the role bundles are meaningful and testable. A domain
milestone that needs finer control adds a case and slots it into the relevant
`Role::permissions()` bundles — nothing else changes.

### Permissions vs. module activation (M7)

`App\Enums\Module` / the `module:` middleware (`docs/module-activation.md`)
answer a **different** question: "is this feature switched on for this school?"
They are **orthogonal to authorization** and grant nothing. A domain route
carries both, independently:

```php
Route::middleware(['tenant', 'module:attendance'])   // feature on for the school?
    ->get('attendance', …)->can('attendance.view');  // may THIS user? (authoritative)
```

Turning a module on never gives a user a permission; turning it off never
removes one. Module activation itself is gated by the existing
`school.settings.*` permissions — no new permission was added.

## 4. Roles (`App\Enums\Role`)

Seven per-school roles, each a static bundle of permissions plus a `tier`:

| Role | tier | Holds |
|------|-----:|-------|
| `school_admin` | 100 | **every** permission |
| `principal` | 80 | school settings (view), member view + assign-role, all student/guardian/staff/academics/timetable/attendance/assessment/result permissions, finance (view) |
| `bursar` | 50 | school settings (view), student/guardian/staff (view), finance (view + manage) |
| `teacher` | 50 | student/guardian/staff/academics/timetable (view), attendance (view + record), assessment (view + record), result (view + enter) |
| `staff` | 30 | student (view), guardian (view), staff (view), academics (view), timetable (view), attendance (view), assessment (view), result (view) |
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
| `member.*` + `academics.*` (M8) + `student.*` (M9) + `guardian.*` (M10) + `staff.*` (M11) + `timetable.*` (M12) + `attendance.*` (M13) + `assessment.*` (M14) + `result.*` (M15) + `portal.parent` (M16) enforced; the rest declared but dormant | the vocabulary the role bundles need, activated module by module |
| `guardian.view` added to Staff in M10, `staff.*` widened in M11, `timetable.*` added in M12 (Principal → manage; Teacher/Staff → view; Bursar → none) | `timetable.*` follows `academics.*` exactly — the roles with academic access get it; Bursar has no academic access, so no timetable access |
| `attendance.manage` added in M13 (Principal); `attendance.view` / `attendance.record` kept on Teacher/Staff from M7; a third ability, not a role check, scopes a teacher to their assigned classes (`AttendanceAuthorizer`) | "record for any class" vs "record for my class" is a real distinction the two-ability `view`/`manage` shape can't carry; expressing it as a permission + a tenant-scoped assignment check keeps role names out of the logic |
| `assessment.{view,record,manage}` added in M14 (Principal → all; Teacher → view + record; Staff → view; Bursar → none); `AssessmentAuthorizer` scopes a teacher to their assigned `(level, subject)` | mirrors `attendance.*` — the roles with academic access get it; a teacher records for the subject they teach that class, not any class; `.manage` also gates category config + unlocking a locked assessment |
| `result.manage` + `result.adjust` added in M15 (Principal, joining the already-declared `.view`/`.enter`/`.publish`); `result.view` added to Staff; `ResultAuthorizer` scopes a teacher's comment to their assigned class, with **no subject dimension** | 9 originally-sketched permissions (including a separate `report.*` set) were consolidated to 5 by reusing M4's scaffolding and folding report-card viewing/configuring into `result.view`/`result.manage` — a report card is just another view of a result run, not a separate resource; a class-teacher comment is class-wide, unlike a per-subject score, so its authorizer carries no subject check |
| `portal.parent` enforced as-is in M16, no new permission | it was declared since M4 for exactly this moment; every finer "which student may this parent see" question is a **data-linkage** question (`ParentPortalAuthorizer`, via the M10 Guardian link), not a permission-granularity one — adding `portal.parent.<something>` cases would have modeled a question the Gate can't actually answer |
| Module activation (M7) reuses `school.settings.*`, stays orthogonal to permissions | it is configuration ("is the feature on for this school?"), not "may this user…"; a domain route checks both |
| `academics.*` (M8) reused as-is, no finer split; sessions re-gated from `school.settings.*` | coarse-on-purpose; the academic structure is one thing under one permission (see `docs/academic-foundation.md`) |

## 11. Deferred

- Multi-role per school; custom/per-school roles; runtime-editable permissions.
- Invitation / brand-new-account flow (M5 adds *existing* users only).
- Admin UI for `users.status` and `users.is_platform_admin`.
- Enforcing the remaining dormant permissions — happens in each domain module's
  milestone (`academics.*` in M8, `student.*` in M9, `guardian.*` in M10,
  `staff.*` in M11, `timetable.*` in M12, `attendance.*` in M13,
  `assessment.*` in M14, `result.*` in M15, `portal.parent` in M16).
- Audit logging of role changes.
- `@role` / permission Blade directives beyond the built-in `@can`.
