# Security

Security is an architectural requirement, not a later hardening pass. This
document is the standing checklist; each milestone adds to the "implemented"
column.

## Principles

1. **Server-side authorization always.** Hiding a button, link or menu item is a
   UX choice and carries **zero** security weight. Every state-changing route and
   every record read that could cross a tenant boundary is checked on the server
   via a Policy / Gate / explicit query scope.
2. **Validate every input** through a Form Request (or `validate()` for trivial
   read filters). Never trust shape, type, range or ownership of request data.
3. **Tenant isolation is enforced, not assumed.** School A must never read or
   mutate School B data. Guaranteed by `TenantContext` + the `BelongsToSchool`
   global scope (`SchoolScope`, which *throws* rather than run unscoped) + the
   `creating`/`updating` hooks that make `school_id` unspoofable and immutable,
   and proven by the cross-tenant test suite. Full reference: `docs/tenancy.md`.
4. **Least privilege.** Roles are bundles of permissions granted per school
   (`school_user.role`); code checks **permissions** via the Gate, never role
   names. Platform-admin capability is separate from any school role and confers
   nothing without an active school context. Full reference: `docs/authorization.md`.
5. **Fail safe.** Missing tenant context throws (`idOrFail`), it does not fall
   back to "all schools".

## Implemented in Milestone 1

| Control | State |
|---------|-------|
| CSRF protection | Laravel default on all `web` POST/PUT/PATCH/DELETE; forms use `@csrf`, the `confirm` component spoofs method + includes token |
| Mass-assignment protection | `User` uses `#[Fillable]`; convention documented for all models |
| Password hashing | bcrypt, `BCRYPT_ROUNDS=12` (4 in tests only) |
| Session security | `SESSION_DRIVER=database`, `SESSION_ENCRYPT` available, 120-min lifetime |
| Safe error handling | `APP_DEBUG=false` in production; `/health` leaks no env/config; JSON errors only for `api/*` / explicit JSON requests |
| Transport security | `URL::forceScheme('https')` when `APP_ENV=production` |
| Secret management | `.env` git-ignored; `.env.example` placeholders only; no credentials in code |
| Strict models | `Model::shouldBeStrict()` / `preventLazyLoading()` outside production — catches accidental data exposure early |
| Tenant seam | `TenantContext` request-scoped; raw `school_id` from input is forbidden by convention |

## Implemented in Milestone 2 (Authentication)

Full detail in `docs/authentication.md`. Summary of controls:

| Control | State |
|---------|-------|
| Session fixation | `session()->regenerate()` after successful login |
| Secure logout | `logout()` + `session()->invalidate()` + `regenerateToken()` |
| Login throttling | 5 failures per `lower(email)\|ip` → `Lockout`; `throttle:6,1` on register / reset / verify / confirm POSTs |
| Account enumeration | login returns generic `auth.failed`; `forgot-password` returns a neutral message for any address |
| Account status gate | `UserStatus` enum, checked in `LoginRequest` **and** `EnsureAccountIsActive` middleware on every authenticated request |
| Password policy | single `Password::defaults()` — stricter in production (`min(10)`, mixed case, `uncompromised()`) |
| Password hashing | bcrypt via `Hash::make` / `hashed` cast (idempotent) |
| Password reset | 60-min token expiry, single-use, `remember_token` rotated on reset |
| Email verification | `MustVerifyEmail`; signed + throttled verify route; `verified` on the app route group |
| Re-auth for sensitive actions | `password.confirm` on account deletion; plumbing ready for future admin/financial actions |
| Mass assignment | `status` excluded from `User::$fillable`; registration/profile use Form Requests + `validated()` |
| Credential logging | none — no `Log::` in the auth path, requests not dumped |
| Transport / cookies | `session.secure` forced true in production by `AppServiceProvider`; `http_only` + `same_site=lax` defaults |
| Authorization direction | documented (User → Permission → School → Policy → Action); roles/permissions not yet implemented |

## Implemented in Milestone 3 (Multi-School Tenant Isolation)

Full detail in `docs/tenancy.md`. Summary of controls:

| Control | State |
|---------|-------|
| Cross-tenant reads | `SchoolScope` global scope constrains every `BelongsToSchool` query to `TenantContext::idOrFail()` |
| Fail closed | no active context + no explicit bypass ⇒ `MissingTenantContextException`, never an unscoped query |
| `school_id` spoofing | `creating` hook stamps it from the context and rejects any different supplied value (`TenantMismatchException`); column is never in `$fillable` |
| `school_id` tampering | `updating` hook makes it immutable |
| Cross-tenant find / update / delete | scoped query returns `null` / affects 0 rows (route binding ⇒ 404) |
| Context resolution | `EnforceTenant` re-checks `School` existence + status + `User::canAccessSchool()` **every request**; session holds only an id |
| Session tampering | changing `tenant.school_id` is inert — access is re-validated server-side |
| Platform admin | `is_platform_admin` (not mass-assignable); no `Gate::before` blanket-allow — acts through a chosen tenant context |
| School resource authz | `SchoolPolicy` — platform actions require platform admin; `enter` also requires an active school |
| Suspended school | cannot be entered (`SchoolPolicy::enter`, `EnforceTenant`) |
| Tenant-aware indexes | `school_id`-leading composite indexes are a documented requirement (`docs/database-design.md`) |

## Implemented in Milestone 4 (Roles & Permissions)

Full detail in `docs/authorization.md`. Summary of controls:

| Control | State |
|---------|-------|
| Permission enforcement | every `App\Enums\Permission` is a Gate ability delegating to `User::hasPermission()` |
| Tenant composition | `hasPermission()` reads `TenantContext::id()`; a permission applies only inside the school in context, and returns `false` with no context |
| No platform-wide bypass | **no `Gate::before()`**; a platform admin holds permissions only inside a school they have entered, and `SchoolScope` still limits the rows |
| Privilege escalation | `MembershipPolicy` + `User::canGrantRole()` — you can never grant a role of a higher tier than your own |
| Self-modification | you cannot change or remove your own membership |
| Cross-school role management | Members controller only loads `school_user` rows for `TenantContext::idOrFail()`; another school's member 404s |
| Role tampering | `school_user.role` is set only via `User::assignRoleInSchool()` / `joinSchool()`; not part of any `$fillable`; validated against the `Role` enum |
| Role storage | static enums (no `permissions` table to keep in sync, no cache to poison) |

## Implemented in Milestone 5 (School Onboarding)

Full detail in `docs/onboarding.md`. Summary of controls:

| Control | State |
|---------|-------|
| School provisioning authz | `/admin/schools*` — platform-admin only (`SchoolPolicy` + `can:viewAny,School` group middleware + per-action `authorize()`); school users get 403 |
| Provisioning ≠ context change | provisioning never writes the school session key or sets `TenantContext` |
| Initial admin escalation | `canGrantRole(SchoolAdmin, $school)` asserted before seating; account must be existing + active |
| Add-member escalation | `member.assign-role` gate + `canGrantRole($role)` — a Principal cannot add a School Admin |
| Add-member isolation | controller only `joinSchool($tenant->schoolOrFail(), …)`; never a cross-school membership; never a context switch |
| Add-member rate limit | `throttle:10,1` on `POST /members` |
| School-owned data | `SchoolSetting`, `AcademicSession` use `BelongsToSchool` — `SchoolScope` + unspoofable/immutable `school_id`; `$fillable` excludes `school_id`/`completed_at`/`is_current` |
| Tenant-owned route binding | `{session}` resolved by id **after** the `tenant` middleware, so `SchoolScope` scopes it and another school's id 404s |
| Settings / session validation | Form Requests; `timezone` against the identifier list; `ends_on` after `starts_on`; session name unique per (tenant) school |
| Enumeration | add-member / initial-admin email uses `exists:users` (admin tool, gated + throttled); documented trade-off |

## Implemented in Milestone 6 (School Settings & Configuration)

Full detail in `docs/school-settings.md`. Summary of controls:

| Control | State |
|---------|-------|
| Settings authz | every section route carries `->can('school.settings.view'|'.update')` **and** each Form Request `authorize()` re-checks `school.settings.update`; view-only roles (Principal/Bursar) get the read-only page, others 403 |
| Tenant isolation | `SchoolSetting` is `BelongsToSchool`; the row is always resolved via `TenantContext->schoolOrFail()->settings()`; `school_id` never read from input, immutable on update |
| Protected columns | `$fillable` excludes `school_id`, `completed_at`, `logo_path`; written only via `markReviewed()` / `putLogo()` / `clearLogo()` (mass-assignment attempt tested) |
| Logo upload validation | `mimetypes` content sniff (jpeg/png/webp, not extension), `max:2048` KB, `dimensions` 48–1600px; invalid type / tiny image rejected (tested) |
| Logo storage | private `local` disk (`storage/app/private/school-logos/{tenant_id}/…`), never the public disk / `/storage` symlink |
| Logo serving | only `GET /settings/school/branding/logo`, gated `school.settings.view`, **no path parameter** → no traversal; serves only the current tenant's `logo_path`; a School B user cannot fetch School A's logo (tested) |
| Logo lifecycle | replace/remove delete the previous file through the model, not mass assignment |
| Platform admin | no active school → school picker redirect; in-context → scoped to that one school (tested) |
| Input normalisation | `country` / `currency` upper-cased, `brand_color` lower-cased, `week_starts_on` cast to int in `prepareForValidation` |
| CSRF | on every form; `@method('PATCH'|'DELETE')` spoofing |

## Implemented in Milestone 7 (Feature / Module Activation)

Full detail in `docs/module-activation.md`. Summary of controls:

| Control | State |
|---------|-------|
| Activation authz | `/settings/school/modules` route carries `->can('school.settings.view'|'.update')` **and** `UpdateSchoolModuleRequest::authorize()` re-checks `school.settings.update`; view-only roles get the page without toggle controls, others 403 |
| Activation ≠ authorization | enabling a module grants **no** permission and disabling removes none — M4 is the sole authority; the `module:` middleware and `->can()` are orthogonal and a domain route needs both (tested) |
| Tenant isolation | `SchoolModule` is `BelongsToSchool`; `SchoolModules` reads/writes only the active school's rows and fails closed with no context; School A cannot read or change School B's module state (tested at the resolver and HTTP layers) |
| `school_id` protection | never in `$fillable`, never read from input, stamped from `TenantContext` on create, immutable after; a `school_id` in the PATCH body is ignored (tested) |
| Unknown module id | `Module::tryFrom()` in the controller → **404**; the `module:` middleware throws on an unknown name (route misconfiguration, not user input) |
| Invalid state | `enabled` is `required|boolean` (Form Request); dependency rules rejected with a `module` validation error, no row written |
| Safe defaults | absence of a row ⇒ `Module::enabledByDefault()`; a stale/retired `module` string in the table is ignored by the resolver, never fatal (tested) |
| Platform admin | no active school → school-picker redirect; in-context → scoped to that one school (tested) |
| Efficient lookup | one memoised read of `school_modules` per request (`SchoolModules`, request-scoped), verified by a query-count test |
| CSRF | on every toggle form; `@method('PATCH')` spoofing |

## Implemented in Milestone 8 (Academic Foundation)

Full detail in `docs/academic-foundation.md`. Summary of controls:

| Control | State |
|---------|-------|
| Two gates on every academic route | `module:academics` (feature on for the school? else 404) **and** `->can('academics.view'|'.manage')`; the Form Requests re-check `academics.manage` in `authorize()` |
| Enforced permissions | `academics.view` (School Admin, Principal, Teacher, Staff) / `academics.manage` (School Admin, Principal); Bursar / Parent / Student / role-less → 403 (tested per entity) |
| Activation ≠ authorization | Teacher passes the module gate but cannot manage config (tested) |
| Tenant isolation | every model `BelongsToSchool`; children (`AcademicPeriod`, `LevelArm`) also carry `school_id` so queries are tenant-safe without the parent in the join; explicit cross-school "cannot read/create/edit/delete/promote" tests for sessions, periods, levels, arms, subjects and the level↔subject link |
| `school_id` protection | never in `$fillable`, never read from input, stamped from `TenantContext`, immutable (`updating` hook → `TenantMismatchException`, tested per model); a `school_id` in a payload is ignored |
| Route-model binding | tenant-owned ids are **not** route-model-bound; resolved by tenant-scoped `findOrFail` in the controller *after* the `tenant` middleware — another school's id 404s |
| Validation-query leakage | Form Requests resolve parent ids (`academic_session_id` / `academic_level_id`) **tenant-scoped**, so a cross-school id yields a clean 404, not a "position taken" validation error |
| Cross-school links | `level_subject` sync validates every `subject_id` with `Rule::exists('subjects','id')->where('school_id', <tenant>)`; the `belongsToMany` read applies `Subject`'s scope (tested) |
| Uniqueness | per school / session / level, never global; edit forms `->ignore()` the row's own id |
| CSRF | every form; `@method('PATCH'|'PUT')` spoofing |

## Implemented in Milestone 9 (Student Management)

Full detail in `docs/student-management.md`. Summary of controls:

| Control | State |
|---------|-------|
| Two gates on every student route | `module:students` (feature on? else 404) **and** `->can('student.view'|'.manage')`; write Form Requests re-check `student.manage` |
| Enforced permissions | `student.view` (School Admin, Principal, Bursar, Teacher, Staff) / `student.manage` (School Admin, Principal); Parent / Student / role-less → 403 (tested) |
| Activation ≠ authorization | Students module on does not give a Parent `student.view` (tested) |
| Tenant isolation | `Student` and `Enrollment` are `BelongsToSchool`; `Enrollment` also carries `student_id`. School A's students / enrollments cannot be read, created, edited or deleted from School B (explicit HTTP + model tests) |
| `school_id` protection | never in `$fillable`, never from input, stamped from `TenantContext`, immutable (`updating` hook → `TenantMismatchException`, tested per model) |
| `students.status` | **not mass-assignable** — a `status` in the demographic edit payload is ignored (tested); changed only via `PATCH /students/{student}/status` |
| Route-model binding | tenant-owned ids resolved by tenant-scoped `findOrFail` in the controller; another school's id 404s |
| Cross-school id leakage | `EnrollmentRequest` `abort(404)`s in `prepareForValidation` if the route's student / enrollment is not the active school's — a cross-school parent never reaches the rules; academic ids use `Rule::exists(...)->where('school_id', <tenant>)`, so a cross-school id fails with a plain "invalid" message, never a 500 or an oracle |
| Invalid combinations | period↔session and arm↔level consistency checked (tenant-scoped) with generic "not part of the selected …" messages |
| Uniqueness | `admission_number` unique per school; the same number is allowed in another school (tested); edit `->ignore()`s the student's own id |
| PII minimisation | name / DOB / optional gender / admission / contact / notes only — nothing on identity grounds |
| No hard delete | students and enrollments are never deleted; a leaver is a `status` change, history retained |
| CSRF | every form; `@method('PATCH')` spoofing |

## Implemented in Milestone 10 (Guardian / Parent Management)

Full detail in `docs/guardian-management.md`. Summary of controls:

| Control | State |
|---------|-------|
| Two gates on every guardian route | `module:guardians` (feature on? else 404) **and** `->can('guardian.view'|'.manage')`; write Form Requests re-check `guardian.manage` |
| Enforced permissions | `guardian.view` (School Admin, Principal, Bursar, Teacher, Staff) / `guardian.manage` (School Admin, Principal); Parent / Student / role-less → 403 (tested) |
| Activation ≠ authorization | Guardians module on does not give a Parent `guardian.view` (tested) |
| Tenant isolation | `Guardian` and `GuardianStudent` are `BelongsToSchool`; the link also carries `student_id` + `guardian_id`. School A's guardians / links cannot be read, created, edited or deleted from School B (explicit HTTP + model tests) |
| `school_id` protection | never in `$fillable`, never from input, stamped from `TenantContext`, immutable (`updating` hook → `TenantMismatchException`, tested per model); a `school_id` in the create payload is ignored (tested) |
| Route-model binding | tenant-owned ids (`{guardian}`, `{link}`, `{student}`) resolved by tenant-scoped `findOrFail`; another school's id 404s |
| Cross-school id leakage | `GuardianLinkRequest` `abort(404)`s on a cross-school `{link}` before validation; `student_id` / `guardian_id` in the link payload use `Rule::exists(...)->where('school_id', <tenant>)`, so a cross-school id fails with a plain "invalid" message, never a 500 or an oracle |
| Duplicate relationships | `unique(student_id, guardian_id)` + a friendly `Rule::unique` message; a repeat link is rejected, no row written |
| Primary-guardian invariant | at most one `is_primary` link per student, enforced transactionally in `GuardianStudent::makePrimary()` (scoped per student) |
| PII minimisation | name / phones / email / address / notes only — no government ID / BVN / NIN, no financial / medical / emergency data, **no portal credentials** |
| No hard delete | guardians are never deleted by the UI; removing a link keeps both records; FKs cascade for a future data-erasure tool |
| CSRF | every form; `@method('PATCH'|'DELETE')` spoofing |

## Implemented in Milestone 11 (Teacher Management)

Full detail in `docs/teacher-management.md`. Summary of controls:

| Control | State |
|---------|-------|
| Two gates on every teacher route | `module:staff` (feature on? else 404) **and** `->can('staff.view'|'.manage')`; write Form Requests re-check `staff.manage` |
| Enforced permissions | `staff.view` (School Admin, Principal, Bursar, Teacher, Staff) / `staff.manage` (School Admin, Principal); Parent / Student / role-less → 403 (tested). A **Teacher** role holder can view but **cannot manage other teachers** |
| Activation ≠ authorization | Staff module on does not give a Parent `staff.view` (tested) |
| Tenant isolation | `Teacher` and `TeacherAssignment` are `BelongsToSchool`; the assignment also carries `teacher_id`. School A's teachers / assignments cannot be read, created, edited or deleted from School B (explicit HTTP + model tests) |
| `school_id` protection | never in `$fillable`, never from input, stamped from `TenantContext`, immutable (`updating` hook → `TenantMismatchException`, tested); a `school_id` in the create payload is ignored (tested) |
| `status` / `user_id` protection | **not mass-assignable** — a `status` in the edit payload is ignored (tested); each changes only via its dedicated `PATCH` endpoint |
| Teacher ↔ User linkage | linked only to an **existing member of the active school** (`Rule::exists('school_user', 'user_id')->where('school_id', <tenant>)`); a stranger / another school's member is rejected; `unique(school_id, user_id)` blocks a second teacher per account; `nullOnDelete` keeps the professional record when the account is deleted (all tested) |
| Route-model binding | tenant-owned ids (`{teacher}`, `{assignment}`) resolved by tenant-scoped `findOrFail`; another school's id 404s |
| Cross-school id leakage | `TeacherAssignmentRequest` `abort(404)`s on a cross-school `{teacher}` / `{assignment}` before validation; academic ids and `user_id` use `Rule::exists(...)->where('school_id' | school_user, <tenant>)`, so a cross-school id fails with a plain "invalid" — never a 500 or an oracle |
| Invalid combinations | period↔session and arm↔level consistency checked (tenant-scoped) with generic "not part of the selected …" messages |
| Duplicate assignments | a duplicate **active** `(teacher, session, period, level, arm, subject)` is rejected in the Form Request; an `ended` duplicate is allowed (history) |
| PII minimisation | name / employee number / email / phone / start date / address / notes only — no NIN / BVN / ID, no financial / bank / pension, no medical, **no credentials** |
| No hard delete | teachers are never deleted (a leaver is `resigned`); assignments are `ended`, not dropped — `DELETE` stays only for a mis-entered row |
| CSRF | every form; `@method('PATCH'|'DELETE')` spoofing |

## Implemented in Milestone 12 (Timetable Management)

Full detail in `docs/timetable-management.md`. Summary of controls:

| Control | State |
|---------|-------|
| Two gates on every timetable route | `module:timetable` (feature on? else 404) **and** `->can('timetable.view'|'.manage')`; write Form Requests re-check `timetable.manage` |
| Enforced permissions | `timetable.view` (School Admin, Principal, Teacher, Staff) / `timetable.manage` (School Admin, Principal); **Bursar / Parent / Student / role-less → 403** (Bursar has no academic access, so no timetable access — tested) |
| Activation ≠ authorization | Timetable module on does not give a Bursar `timetable.view` (tested) |
| Tenant isolation | `Timetable` and `TimetableEntry` are `BelongsToSchool`; the entry also carries `timetable_id`. School A cannot view / edit / delete / publish School B's timetable, cannot create one with School B's session, and cannot schedule a lesson with School B's level / arm / subject / teacher (explicit HTTP + model tests) |
| `school_id` protection | never in `$fillable`, never from input, stamped from `TenantContext`, immutable (`TenantMismatchException`, tested); a `school_id` in the create payload is ignored (tested) |
| `status` / `published_at` protection | **not mass-assignable** — publishing goes through `PATCH /timetables/{t}/status`, which is refused unless the timetable has lessons and no clash |
| Route-model binding | `{timetable}` / `{entry}` resolved by tenant-scoped `findOrFail`; the Form Requests `abort(404)` on a cross-school route parent before validation |
| Cross-school id leakage | every session / period / level / arm / subject / teacher id validated with `Rule::exists(...)->where('school_id', <tenant>)` → plain "invalid"; the conflict self-join is filtered by `timetable_id` **and** `school_id` |
| Overlap correctness | half-open `[start, end)`, same weekday, per timetable; `HH:MM` string comparison; DB existence queries only — entries are never loaded into PHP to detect clashes |
| Teacher authorization not duplicated | a lesson is only allowed when an **active M11 `TeacherAssignment`** backs `(teacher, subject, level)` for the session — the timetable asks M11, it does not re-implement the rule |
| PII minimisation | no personal data on the timetable — it references existing records only |
| Data integrity | published timetables cannot be deleted (draft first); FK deletes cascade for the required parents (never hard-deleted in practice), `academic_period_id` is `nullOnDelete` |
| CSRF | every form; `@method('PATCH'|'DELETE')` spoofing |

## Implemented in Milestone 13 (Attendance Management)

Full detail in `docs/attendance-management.md`. Summary of controls:

| Control | How |
|---------|-----|
| Two gates on every attendance route | `module:attendance` (feature on? else 404) **and** `->can('attendance.view'|'.record'|'.manage')`; write Form Requests re-check via `AttendanceModuleRequest` + `AttendanceAuthorizer` |
| Enforced permissions | `attendance.view` (School Admin, Principal, Teacher, Staff) / `attendance.record` (School Admin, Principal, Teacher — class-scoped for teachers) / `attendance.manage` (School Admin, Principal); **Bursar / Parent / Student / role-less → 403** (tested) |
| Activation ≠ authorization | Attendance module on does not give a Bursar `attendance.view` (tested) |
| Timetable independence | `Module::Attendance` depends on Academics + Students only; the full workflow is exercised with the Timetable module **off** (tested); no `timetable_id` column, no lesson picker |
| Tenant isolation | `AttendanceRegister` and `AttendanceRecord` are `BelongsToSchool`; the record also carries `attendance_register_id`. School A cannot view / record / submit / reopen / delete School B's register, cannot create one with School B's class, and cannot POST School B's student ids (explicit HTTP + model tests) |
| `status` / `submitted_*` protection | **not mass-assignable** — locking goes through `submit()` (only when every student is marked); unlocking through `reopen()` (`attendance.manage` only) |
| Route-model binding | `{register}` resolved by tenant-scoped `findOrFail`; the write Form Requests `abort(404)` on a cross-school route parent before validation |
| Cross-school id leakage | every session / period / level / arm id validated with `Rule::exists(...)->where('school_id', <tenant>)` → plain "invalid"; a mark for a student not on the snapshotted roster is rejected, which also blocks cross-school / wrong-class student ids |
| Roster bulk insert | `AttendanceRecord::insert()` bypasses the `creating` hook, so `school_id` is set explicitly from `TenantContext::idOrFail()` |
| Accidental false attendance | records start `null` (unmarked); a register can't be submitted while any is unmarked; "Save & submit" only locks a fully-marked class |
| PII minimisation | no personal data on attendance — student references only, plus a short optional free-text `note` |
| Historical correctness | records are never hard-deleted; eligibility is the enrollment date range, not current status, so a later withdrawal doesn't alter past registers; correction after locking is an explicit authorized `reopen()`, not a silent edit |
| CSRF | every form; `@method('PATCH'|'DELETE')` spoofing |

## Deferred (with the milestone that owns them)

- **Auth follow-ups:** 2FA, "log out other devices" on password change, session
  listing, auth-event audit logging, templated transactional emails.
- **Authz follow-ups:** multi-role per school, custom/runtime roles, invitations
  / brand-new-account onboarding, admin UI for `status` / `is_platform_admin`,
  audit logging of role & membership changes, enforcing the remaining dormant
  domain permissions (each in its module — `academics.*` in M8, `student.*` in
  M9, `guardian.*` in M10, `staff.*` in M11, `timetable.*` in M12,
  `attendance.*` in M13).
- **Tenancy follow-ups:** queue-job tenant propagation, per-tenant rate limiting,
  per-tenant cache keys, audit logging of context switches.
- **Later:** audit logging (who did what, per school — incl. student record /
  status changes), virus scanning of uploads (type/size/dimension validation and
  out-of-webroot storage are done in M6 — see `docs/school-settings.md` §4),
  encryption of sensitive PII at rest, data export / erasure (GDPR-style)
  handling for student / guardian / teacher records, 2FA for admins, security
  headers (CSP) review, dependency scanning in CI.

## Review checklist for every PR

- [ ] New write route has a Policy check or documented reason it is public
- [ ] New write route validates through a Form Request
- [ ] New query on tenant data goes through the tenant scope (no bare `school_id`)
- [ ] No secret, key or credential added to tracked files
- [ ] Error/exception paths do not disclose stack traces or config in production
- [ ] User-supplied identifiers are authorised for the current tenant/user
