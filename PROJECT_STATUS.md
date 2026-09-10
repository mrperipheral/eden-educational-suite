# Project Status

_Last updated: 2026-09-19_

## Current milestone

**Milestone 11 — Teacher Management: COMPLETE.**

Next up: **Domain Modules** (Milestone 12+) — Class rosters, Timetable,
Attendance, Assessments & Results, Fees, CBT, Notifications, Portals, Promotion.
Not started — do not begin without picking it up explicitly. See
`docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-19)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 (app) · SQLite `:memory:` (tests) |
| Local mail | Mailpit (`127.0.0.1:1025`, UI `:8025`) — `.env` only, not committed |
| Tests | `php artisan test` — 427 passing |
| Build | `npm run build` — passing |
| Formatting | `vendor/bin/pint --test` — passing |

## Delivered

- **M1 — Platform Foundation** (`foundation-complete`).
- **M2 — Authentication & User Foundation** (`authentication-complete`).
- **M3 — Multi-School / Strict Tenant Isolation** (`multischool-foundation-complete`) — `docs/tenancy.md`.
- **M4 — Roles & Permissions** (`roles-permissions-complete`) — `docs/authorization.md`.
- **M5 — School Onboarding** (`school-onboarding-complete`) — `docs/onboarding.md`.
- **M6 — School Settings & Configuration** (`school-settings-complete`) — `docs/school-settings.md`.
- **M7 — Feature / Module Activation** (`feature-activation-complete`) — `docs/module-activation.md`.
- **M8 — Academic Foundation** (`academic-foundation-complete`) — `docs/academic-foundation.md`.
- **M9 — Student Management** (`student-management-complete`) — `docs/student-management.md`.
- **M10 — Guardian / Parent Management** (`guardian-management-complete`) — `docs/guardian-management.md`.
- **M11 — Teacher Management** (this milestone, `teacher-management-complete`) —
  `docs/teacher-management.md`; see below.

## Delivered in Milestone 11

The tenant-scoped teacher record, an optional link to an application account, and
the teaching-assignment foundation for the later Timetable / Attendance /
Assessment / Results modules. Records + assignment foundation only — no teacher
portal, no invitations, no credentials, no timetable/attendance/marks, no
payroll. Built on the existing `TenantContext` + `BelongsToSchool` +
`Permission` + `module:staff` seams — no new mechanism, no new packages, no
Redis/queues.

- **Enums** — `App\Enums\TeacherStatus` (active / inactive / suspended /
  resigned), `App\Enums\TeacherAssignmentStatus` (active / ended).
- **`App\Models\Teacher`** — school-owned. Minimal professional data: name
  (first / middle / last / preferred), `employee_number`
  (`unique(school_id, employee_number)`), email, phone, start date,
  address, notes. **No** NIN / BVN / ID, financial, medical or credential
  fields. `status` and `user_id` **not** mass-assignable — each changed via a
  dedicated endpoint. Never hard-deleted. `search()` / `ordered()` scopes.
- **`App\Models\TeacherAssignment`** — school-owned **and** teacher-scoped. FKs
  to `AcademicSession` (req), `AcademicPeriod` (opt), `AcademicLevel` (req),
  `LevelArm` (opt), `Subject` (req); `status`, `started_on`, `ended_on`. History
  preserved — an assignment is `ended` (`end()`), never dropped; `DELETE` stays
  for a mis-entered row. Duplicate **active** `(teacher, session, period, level,
  arm, subject)` rejected in the Form Request (not a DB constraint).
- **Teacher ↔ User** — nullable `teachers.user_id`, `unique(school_id, user_id)`,
  `nullOnDelete`. Linked only to an **existing member of the active school**
  (`school_user`), via `PATCH /teachers/{teacher}/user`. A teacher record is a
  professional record first — creating one never creates a login, and M11 builds
  no invitation / password / portal flow.
- **Migrations** `2026_09_19_100000` (`teachers`), `…100010`
  (`teacher_assignments`) — both `BelongsToSchool`, indexes leading with
  `school_id`, FKs cascade.
- **`App\Http\Controllers\Teacher\{Teacher,TeacherAssignment}Controller`** +
  `App\Http\Requests\Teacher\*` (`TeacherRequest`, `UpdateTeacherStatusRequest`,
  `LinkTeacherUserRequest`, `TeacherAssignmentRequest`) +
  `resources/views/teachers/*` — list (search + status filter + pagination),
  dedicated create/edit, profile with employment-status control + account-link
  control + assignment list (current / past) + an Alpine-cascade assignment form
  (session→term, level→arm, subject).
- **Routes** — `/teachers/*` behind `['tenant', 'module:staff']`, gated
  `staff.view` (reads) / `staff.manage` (writes) — M4 permissions, previously
  dormant, now enforced (School Admin + Principal manage; Bursar + Teacher +
  Staff read; Parent / Student → 403).
- **`Module::Staff->isAvailable()`** flipped to `true`; **`Module::Staff` now
  depends on `Module::Academics`**. "Teachers" is a top-level nav item
  (permission- + module-filtered).
- **Permissions** — `Permission::StaffManage` added to the **Principal** bundle;
  `Permission::StaffView` added to **Bursar**, **Teacher** and **Staff**
  (Principal already held `StaffView` from M4). A Teacher role holder can see
  the staff area but cannot manage other teachers.
- **Seeder** — Alpha gets 5 teachers (one linked to the Tomiwa Teacher account,
  one resigned) and 8 assignments (4 active + 4 prior-year, kept as history).
- **Docs** — new `docs/teacher-management.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `tenancy.md`,
  `module-activation.md`, `roadmap.md`, `ui-ux-guidelines.md`, `CLAUDE.md`,
  `AGENTS.md`.

## Authorization & tenant controls

- **Two gates on every teacher route:** `module:staff` (404 when off) **and**
  `->can('staff.view'|'.manage')`; write Form Requests re-check `staff.manage`.
  Module gate ≠ permission (a Parent still can't see teachers — tested).
- `Teacher` / `TeacherAssignment` are `BelongsToSchool`; the assignment also
  carries `teacher_id`. `school_id` never from input, immutable
  (`TenantMismatchException`); a `school_id` in the create payload is ignored
  (tested). `status` / `user_id` not mass-assignable (a `status` in the edit
  payload is ignored — tested).
- Route ids resolved by tenant-scoped `findOrFail`; `TeacherAssignmentRequest`
  `abort(404)`s on a cross-school `{teacher}` / `{assignment}` before validation.
  Academic ids and `user_id` validated with `Rule::exists(...)->where('school_id'
  | school_user, <tenant>)` → generic "invalid" for a cross-school id, no leak.
  Level↔arm / session↔period consistency checked. Explicit HTTP + model
  cross-school isolation tests for teacher access, updates, assignment
  create/edit/delete and account linking.

## Database

M11 adds `teachers` and `teacher_assignments`. No other schema changes.

## Routes (application, additions in M11)

Tenant-scoped + `module:staff`, gated `staff.view` / `staff.manage`. 13 routes
under `/teachers/` (`teachers.*`, `teachers.assignments.*`).

## Tests

427 passing (was 374 at M10; +53 in M11, M1–M10 intact). New
`tests/Feature/Teacher/*` (+ `TeacherTestCase` base) — `TeacherTest`,
`TeacherUserLinkTest`, `TeacherAssignmentTest`, `TeacherStructureTest`:
creation / editing / validation; employee-number uniqueness per school (and
reusable across schools); statuses via the dedicated endpoint (+
not-mass-assignable); PII-minimisation column check; search / status filter /
pagination; account link / unlink (member-only, one-per-school, cross-school
rejected, survives account deletion, same person in two schools); assignment
creation / editing / ending / removal; historical assignments retained;
duplicate active-assignment rejection; session/period & level/arm consistency;
cross-school academic ids rejected without leak; authorization per role;
module-disabled 404s; module-enabled-without-permission 403s; cross-school
teacher & assignment isolation; ownership immutability; tenant-safe route
resolution; N+1 guards on the teacher list and profile. New
`tests/Unit/Enums/TeacherEnumsTest`. `Unit/Enums/ModuleTest` updated (available
list).

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Next milestone: class rosters / "who teaches this class" views, then timetable.
- **Teacher portal** — teacher sign-in + invitations (M11 stores no
  credentials); non-teaching staff types.
- **Parent Portal** — guardian sign-in + portal accounts.
- Promotion / graduation workflow; bulk student / guardian / teacher import;
  student & teacher documents / photo; teacher qualifications & subjects-qualified.
- Audit trail + data-erasure handling for student, guardian and teacher records.
- Apply the stored `timezone` / `locale` / `date_format` at render time.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
