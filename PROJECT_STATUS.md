# Project Status

_Last updated: 2026-09-18_

## Current milestone

**Milestone 10 — Guardian / Parent Management: COMPLETE.**

Next up: **Domain Modules** (Milestone 11+) — Staff, Class rosters & teacher
assignment, Timetable, Attendance, Assessments & Results, Fees, CBT,
Notifications, Portals, Promotion. Not started — do not begin without picking it
up explicitly. See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-18)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.x |
| Database | MySQL 8 (app) · SQLite `:memory:` (tests) |
| Local mail | Mailpit (`127.0.0.1:1025`, UI `:8025`) — `.env` only, not committed |
| Tests | `php artisan test` — 374 passing |
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
- **M10 — Guardian / Parent Management** (this milestone, `guardian-management-complete`) —
  `docs/guardian-management.md`; see below.

## Delivered in Milestone 10

Tenant-scoped guardian / parent records and the student ↔ guardian relationship —
the contact foundation the later Parent Portal / Notifications modules build on.
Records + linkage only — no portal, no credentials, no messaging. Built on the
existing `TenantContext` + `BelongsToSchool` + `Permission` + `module:guardians`
seams — no new mechanism, no new packages, no Redis/queues.

- **Enum** — `App\Enums\GuardianRelationship` (mother / father / grandparent /
  aunt_uncle / sibling / legal_guardian / other).
- **`App\Models\Guardian`** — school-owned. Minimal contact data: name (first /
  middle / last / preferred), phone, alternate phone, email, address, notes. No
  identity / financial / medical / emergency data, no portal credentials. No
  global uniqueness; never hard-deleted. `search()` / `ordered()` scopes.
- **`App\Models\GuardianStudent`** — the link. School-owned **and** carries
  `student_id` + `guardian_id`. `relationship` (required), `is_primary` (at most
  one per student — `makePrimary()`, the M8/M9 "one at a time" pattern, scoped
  per student). `unique(student_id, guardian_id)` — no duplicate links.
- **Migrations** `2026_09_18_100000` (`guardians`), `…100010` (`guardian_student`)
  — both `BelongsToSchool`, indexes leading with `school_id`, FKs cascade.
- **`App\Http\Controllers\Guardian\{Guardian,GuardianLink}Controller`** +
  `App\Http\Requests\Guardian\*` (`GuardianRequest`, `GuardianLinkRequest`) +
  `resources/views/guardians/*` — list (search + pagination), dedicated
  create/edit, guardian profile with linked-students management, and a "link a
  guardian" page reached from the student profile. The student profile gains a
  "Parents / guardians" card (add / edit relationship / unlink).
- **Routes** — `/guardians/*` behind `['tenant', 'module:guardians']`, gated
  `guardian.view` (reads) / `guardian.manage` (writes) — M4 permissions,
  previously dormant, now enforced (School Admin + Principal manage; Bursar +
  Teacher + Staff read; Parent / Student → 403).
- **`Module::Guardians->isAvailable()`** flipped to `true`; **`Module::Guardians`
  depends on `Module::Students`** (already declared in the M7 catalogue).
  "Guardians" is a top-level nav item (permission- + module-filtered).
- **Permissions** — `Permission::GuardianView` added to the **Staff** role
  bundle (the third read-only role that already holds `student.view`); no other
  bundle change (Principal / Bursar / Teacher already carried the guardian
  permissions from M4).
- **Seeder** — Alpha gets 17 guardians / 18 links: a primary contact per student
  for the first dozen, a second guardian for a few, and one guardian shared
  across two siblings.
- **Docs** — new `docs/guardian-management.md`; updated `architecture.md`,
  `authorization.md`, `database-design.md`, `security.md`, `tenancy.md`,
  `module-activation.md`, `roadmap.md`, `ui-ux-guidelines.md`,
  `student-management.md`, `CLAUDE.md`, `AGENTS.md`.

## Authorization & tenant controls

- **Two gates on every guardian route:** `module:guardians` (404 when off)
  **and** `->can('guardian.view'|'.manage')`; write Form Requests re-check
  `guardian.manage`. Module gate ≠ permission (a Parent still can't see guardians
  — tested).
- `Guardian` / `GuardianStudent` are `BelongsToSchool`; the link also carries
  `student_id` + `guardian_id`. `school_id` never from input, immutable
  (`TenantMismatchException`); a `school_id` in the create payload is ignored
  (tested).
- Route ids resolved by tenant-scoped `findOrFail`; `GuardianLinkRequest`
  `abort(404)`s on a cross-school `{link}` before validation. `student_id` /
  `guardian_id` in the link payload use `Rule::exists(...)->where('school_id', <tenant>)`
  → generic "invalid" for a cross-school id, no leak. Duplicate links rejected
  (`unique(student_id, guardian_id)` + a friendly Form Request message).
  Explicit HTTP + model cross-school isolation tests.

## Database

M10 adds `guardians` and `guardian_student`. No other schema changes.

## Routes (application, additions in M10)

Tenant-scoped + `module:guardians`, gated `guardian.view` / `guardian.manage`.
10 routes under `/guardians/` (`guardians.*`, `guardians.links.*`).

## Tests

374 passing (was 340 at M9; +34 in M10, M1–M9 intact). New
`tests/Feature/Guardian/*` (+ `GuardianTestCase` base) — `GuardianTest`,
`GuardianStudentLinkTest`, `GuardianStructureTest`: guardian creation / editing /
validation; PII-minimisation column check; search / pagination; linking from the
student workflow; multiple guardians per student; a guardian linked to multiple
students; explicit relationship type (required + validated); one-primary-per-student;
duplicate-link prevention; link edit (relationship + promote to primary); link
removal keeps both records; FK cascade; authorization per role; module-disabled
404s; cross-school guardian isolation; cross-school student/guardian linking
rejection without leak; `school_id` spoof / immutability; tenant-safe route
resolution; N+1 guards on the student profile and the guardian profile. New
`tests/Unit/Enums/GuardianEnumsTest`. `Unit/Enums/ModuleTest` updated (available
list).

## Known follow-ups / recommendations

- Production env: `SESSION_SECURE_COOKIE=true`, real `MAIL_MAILER`, `APP_DEBUG=false`.
- Next milestone: staff / teachers, then class rosters & teacher assignment.
- **Parent Portal** — guardian sign-in + portal accounts (M10 stores no
  credentials); guardian messaging / notifications.
- Promotion / graduation workflow; bulk student & guardian import; student ID /
  photo / documents; medical & emergency info; transfer records.
- Audit trail + data-erasure handling for student and guardian records.
- Apply the stored `timezone` / `locale` / `date_format` at render time.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
