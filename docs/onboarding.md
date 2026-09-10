# School Onboarding

Status: **Milestone 5 — complete.** The controlled path from "a platform admin
creates a school" to "the school is ready to use", built entirely on the M1–M4
architecture (tenant context, permissions/Gate, policies).

## 1. The pieces

| Concern | Where |
|---------|-------|
| School provisioning | `App\Http\Controllers\Platform\SchoolController` + `App\Services\SchoolProvisioner` — `GET/POST /admin/schools`, `GET /admin/schools/{school}` |
| Provisioning authz | `SchoolPolicy` (`viewAny` / `create` = platform admin); group middleware `can:viewAny,App\Models\School` |
| Initial School Admin | `StoreSchoolRequest::initialAdmin()` + `User::joinSchool($school, Role::SchoolAdmin)` in the controller |
| Add existing user | `MemberController@create/@store` — `GET /members/create`, `POST /members`; `AddMemberRequest`; `MembershipPolicy::add` + `User::canGrantRole()` |
| School settings | `App\Models\SchoolSetting` (1:1, `BelongsToSchool`) + `SchoolSettingsController` — `GET/PATCH /settings/school` |
| Academic session | `App\Models\AcademicSession` (`BelongsToSchool`) + `AcademicSessionController` — `GET/POST /settings/academic-sessions`, `PATCH /settings/academic-sessions/{session}` |
| Onboarding progress | derived on the dashboard (`DashboardController::onboarding()`) |

```
Platform admin ──► /admin/schools/create ──► SchoolProvisioner::provision()
   (no tenant context)                          creates `schools` row (status = active)
        │                                       + optionally joinSchool(initialAdmin, SchoolAdmin)
        ▼
   "Enter school"  ──► POST /school (SchoolPolicy::enter) ──► TenantContext::set()
        │
        ▼
School Admin (in context) ──► add members · school settings · first academic session
        │                     (all school-owned; SchoolScope + permissions apply)
        ▼
   dashboard onboarding checklist ticks off → "onboarding complete"
```

## 2. School provisioning

- **Platform-admin only.** `/admin/schools*` is *not* tenant-scoped — a school
  shell is created from outside any school context. `SchoolPolicy` (unchanged
  from M3) is the single authority; the route group carries
  `can:viewAny,App\Models\School` and every write also calls `$this->authorize()`
  / a Form Request `authorize()`.
- **School users cannot create schools** — a School Admin, Principal, etc. gets
  403 on every `/admin/schools*` route (tested).
- **Validation** (`StoreSchoolRequest`): `name` required (2–255); `slug`
  optional, lower-cased + trimmed in `prepareForValidation`, must match
  `^[a-z0-9]+(-[a-z0-9]+)*$`, `unique:schools,slug`; `initial_admin_email`
  optional, must be an existing **active** account.
- **Slug generation** (`SchoolProvisioner`): when omitted, `Str::slug(name)`,
  then `-2`, `-3`, … until unique. Falls back to `school` for symbol-only names.
- **Default status:** `SchoolStatus::Active` (the model default). A provisioned
  school is immediately usable; suspension is a separate, later concern.
- Provisioning **does not** touch the platform admin's session / tenant context.

## 3. Initial School Admin

`StoreSchoolRequest` optionally carries `initial_admin_email`. After the school
is created the controller:

1. asserts `$actor->canGrantRole(Role::SchoolAdmin, $school)` — the M4 escalation
   rule; a platform admin passes (their authority in any school is total, see
   `docs/authorization.md` §5), anyone else would not;
2. `$initialAdmin->joinSchool($school, Role::SchoolAdmin)`.

No account is created (that is M2's job) — the email must already belong to an
active user. `school_user` is not a `BelongsToSchool` model, so this write is
safe outside a tenant context, exactly as the seeder does it.

## 4. Adding an existing user to a school

`School Admin` / `Principal` (anyone with **`member.assign-role`** in the active
school) can add an existing account:

- `GET /members/create` → `POST /members` (`AddMemberRequest`, `throttle:10,1`).
- Coarse gate: `member.assign-role` (route `->can()` + Form Request + policy
  `MembershipPolicy::add`).
- The specific role goes through `User::canGrantRole($role)` — **no privilege
  escalation**: a Principal cannot add anyone as School Admin. The `<select>` is
  pre-filtered to grantable roles and re-checked server-side.
- **Never cross-school:** the controller only ever calls
  `joinSchool($this->tenant->schoolOrFail(), $role)`. Adding someone to school A
  never creates a membership anywhere else (tested).
- **Never a context change:** adding a member does not write to the school
  session key or switch anyone's tenant. Users still resolve their own context
  via `EnforceTenant` (tested).
- **Enumeration:** `email` uses `exists:users,email` (reveals whether an account
  exists) — acceptable for an admin tool gated behind `member.assign-role`, and
  the route is rate-limited. "Already a member" is a distinct validation error.
- Invitations / creating brand-new accounts are **out of scope** — deferred.

## 5. Basic school settings

`school_settings` — one row per school (1:1), `App\Models\SchoolSetting` uses
`BelongsToSchool`:

| Column | Notes |
|--------|-------|
| `timezone` | default `Africa/Lagos` (target market; fully overridable), validated against `timezone_identifiers_list()` |
| `locale` | default `en` |
| `contact_email`, `contact_phone` | nullable |
| `completed_at` | stamped on first save — drives the onboarding checklist; **not** mass-assignable (`markReviewed()`) |

- `GET /settings/school` (`school.settings.view`) — the row is `firstOrCreate`d
  via the school relation; `school_id` is stamped from the context.
- `PATCH /settings/school` (`school.settings.update`).
- `Principal` / `Bursar` see a read-only view (they hold `school.settings.view`,
  not `update`). `Teacher` / `Staff` / `Parent` / `Student` get 403.
- Fully tenant-isolated by `SchoolScope`; another school's settings are
  unreachable (tested).
- This is **not** the full School Settings milestone — that adds columns here.

## 6. Initial academic session

`academic_sessions` — `App\Models\AcademicSession` uses `BelongsToSchool`:

| Column | Notes |
|--------|-------|
| `name` | e.g. "2025/2026" — any label, `unique(['school_id', 'name'])` |
| `starts_on`, `ends_on` | dates; `ends_on` must be after `starts_on` |
| `is_current` | at most one per school; set via `makeCurrent()` (transactional, tenant-scoped); **not** mass-assignable |

- `GET /settings/academic-sessions` (`school.settings.view`), `POST` /
  `PATCH .../{session}` (`school.settings.update`).
- The **first** session a school creates automatically becomes current.
- `{session}` is resolved by id in the controller (not route-model-bound) so the
  lookup runs *after* the `tenant` middleware — `SchoolScope` then constrains it
  and another school's id simply 404s.
- **No Nigerian assumptions.** No terms, no fixed count, no hardcoded calendar —
  the Academic Management milestone builds that structure on top.

## 7. Onboarding progress

`DashboardController::onboarding()` — only for users with
`school.settings.update` (School Admin / platform admin in context). Three
derived steps:

1. **Assign a School Admin** — `School::hasSchoolAdmin()` (a `school_user` row
   with `role = school_admin`).
2. **Create the first academic session** — `academicSessions()->exists()`.
3. **Review school settings** — `settings.completed_at` is set.

Three small indexed queries, gated by permission (a Teacher/Parent never pays the
cost). When all three are done the checklist collapses to "onboarding complete".

## 8. Security & performance

- Every state change: Form Request `authorize()` + `$this->authorize()` /
  route `->can()`. Permission strings only, never role names.
- Mass assignment: `SchoolSetting`/`AcademicSession` `$fillable` lists exclude
  `school_id`, `completed_at`, `is_current`. `School` keeps `#[Fillable(['name','slug'])]`.
- CSRF on every form; `@method` spoofing for PATCH.
- Route-model binding: `{school}` (platform, by slug); tenant-owned `{session}`
  resolved by id post-`tenant`-middleware.
- Indexes: `school_settings.school_id` unique; `academic_sessions`
  `unique(['school_id','name'])` + `index(['school_id','starts_on'])`.
- Lists paginated (`/admin/schools` 20/page, academic sessions 20/page).
- `withCount('users')` on the school list — no N+1.
- No Redis, no queues, no new packages.

## 9. Decisions

| Decision | Why |
|----------|-----|
| Provisioning is platform-level; school-owned data is configured in-context | "Platform Admin must operate through controlled school context when modifying school-owned data" — the `/admin` area only creates the shell |
| Initial admin assigned in the controller (not the service), guarded by `canGrantRole` | keeps the escalation rule visible and meaningful; the service stays a pure "create the row" step |
| Add-member reuses `member.assign-role` (+ `canGrantRole`), no new permission | it *is* "give this person a role in my school"; smaller permission surface |
| Academic sessions gated by `school.settings.*`, not `academics.*` | the year container is configuration; `academics.*` stays dormant for its own milestone |
| `SchoolSetting` typed columns, not a JSON blob or key/value table | validated, indexable; the full Settings milestone adds columns |
| `{session}` resolved by id, not route-model-bound | binding runs before `tenant`, so a `BelongsToSchool` bind would hit `SchoolScope` with no context |
| `timezone` default `Africa/Lagos` | sensible default for the initial market; a settable field, not a code assumption |

## 10. Deferred

- Invitations / email workflows; creating brand-new accounts during onboarding.
- School suspension / lifecycle, subscriptions / billing.
- The full School Settings milestone (branding, grading scheme, address, …).
- The full Academic Management milestone (terms, calendar, holidays, promotion).
- Bulk import of members; membership removal audit; onboarding-complete
  notifications.
- Admin UI for `users.status` / `users.is_platform_admin`.
