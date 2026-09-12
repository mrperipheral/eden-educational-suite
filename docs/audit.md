# Administration & Audit

Status: **Milestone 26 — complete.** A tenant-scoped, immutable audit trail
for administrative and security-relevant events, a staff-facing Audit Log
viewer, and a small administrative panel on the existing dashboard.

**This is accountability and traceability, not a SIEM.** No centralized
security monitoring, no anomaly detection, no infrastructure/server logs, no
compliance-reporting engine — see §10 for the full explicitly-out-of-scope
list. The goal is a single, honest answer to "who did what, in this school,
and when".

## 1. What this is

`App\Models\AuditLog` — one row per audited event: the acting school (where
one exists), the actor (user id + a name snapshot), an `event` string, the
affected record (type + id + a human-readable label snapshot), a short
human-readable `summary`, a redacted `changes` before/after diff where one
applies, and request metadata (IP, user agent). Every row is written through
`App\Services\Audit\AuditRecorder::record()` — the single seam every module
uses, mirroring how `NotificationDispatcher` (M18) is the one seam for
in-app notifications. There is no `AuditLogRequest` and no edit/destroy
route anywhere in the app; that absence, not a model-level guard, is what
makes a record immutable from the UI.

## 2. Why `school_id` is nullable

`AuditLog` is deliberately **not** `BelongsToSchool`. Every business/
administrative action happens on a route behind the `tenant` middleware, so
`AuditRecorder` reads `TenantContext::id()` and it is always present for
those. But a handful of genuine account-level security events run on routes
that carry **no** `tenant` middleware at all — `routes/auth.php` in full
(login, logout, password reset, email verification) and
`settings/password` (`routes/web.php`'s account-level group). At the moment
those fire, there is no active school to attribute the event to — a user
can belong to several schools, and login itself resolves no tenant at all
(`EnforceTenant` never runs on that route).

Rather than force a fake attribution (guessing a school from whichever one
the user happens to belong to), these events are recorded honestly with
`school_id = null`. The practical consequence: **the per-school Audit Log
viewer never shows them** — `AuditLogController` always filters
`where('school_id', TenantContext::idOrFail())`, and a null-school row
matches no school's filter. This is a deliberate scope boundary, not a bug:
a school-scoped viewer cannot meaningfully show data that isn't scoped to a
school. The events are still centrally recorded and queryable (proven by
`AuditAuthenticationTest`), just not through the per-school page.

One route creates a school *before* any tenant context can exist —
`Platform\SchoolController::store()` — so it passes an explicit `schoolId`
override to `AuditRecorder::record()` (the newly created school's own id)
rather than relying on `TenantContext`.

## 3. Audited events

Grouped as the spec asks, each event string is `noun.verb` (or
`noun.verb_ed`), e.g. `student.created`, `member.role_changed`:

**Authentication / security** (all `school_id = null` — see §2):
`auth.login.success`, `auth.login.failed` (email only, never the password —
`Illuminate\Auth\Events\Failed::$credentials` carries the raw submitted
password and is never read), `auth.login.blocked` (a suspended/disabled
account attempting to sign in — recorded in addition to the
`auth.login.success` the underlying `Auth::attempt()` call still fires,
since the password *did* check out; distinguishing "briefly authenticated,
then the app enforced status" from an ordinary failed password is the
point), `auth.logout`, `auth.password.changed` (Settings), `auth.password.reset`
(forgot-password email flow), `auth.email.verified`.

**User / access administration**: `member.created`, `member.role_changed`
(before/after role), `member.removed` (before = the role they held).

**School administration**: `school.created`, `settings.profile_updated`,
`settings.branding_updated`, `settings.regional_updated`,
`settings.payments_updated` (never includes the Paystack secret key itself
— see §4), `module.toggled` (before/after `enabled`).

**Academic / operational** (a representative, not exhaustive, slice — see
§6 for why): `student.created`, `student.status_changed`,
`teacher.created`, `teacher.status_changed`, `guardian.created`,
`academic_session.created`, `academic_session.made_current`,
`result_run.published`, `result_run.locked`, `fee_payment.recorded`,
`fee_payment.voided`, `examination.scheduled`, `examination.closed`,
`question.status_changed` (activate/deactivate/archive, all three funnel
through one event with before/after status), `entry_assessment.created`,
`entry_assessment.archived`, `entry_assessment.restored`.

## 4. Sensitive data — excluded or redacted

`AuditRecorder::redact()` is a **blanket, key-name filter**, not a
per-model allow-list: any attribute key containing `password`,
`remember_token`, `secret`, `token`, `api_key`, `apikey`, or `private_key`
(case-insensitive substring match) is replaced with the literal string
`[redacted]` before the payload is ever written — so a future caller can
never forget to protect a newly added secret-shaped column, and the shape
of what changed stays visible without exposing the value. Proven by
`AuditLogTest::test_paystack_secret_key_is_redacted_from_settings_audit_payload`
and `::test_profile_settings_update_redacts_any_secret_shaped_column`
(the latter covers the case where the secret column merely happens to be
present in a broader settings snapshot, not the field being edited).

Beyond the blanket filter, `updatePayments()` additionally never includes
the raw secret key in its payload at all — only a boolean
`paystack_key_was_rotated` flag (deliberately *not* named with `secret` in
it, so the filter does not also mask a harmless true/false). `Failed`
login's raw credentials (including the password) are never read by
`RecordFailedLogin` — only the attempted email, and only in the summary
text, never as a separate queryable column (to avoid building an
account-enumeration oracle out of the audit viewer itself).

## 5. Authorization

One new permission, `audit.view` — a single tier, since nothing in this
milestone ever *writes* to an audit record through the application:

- School Admin → automatic (`Permission::all()`).
- Principal → `audit.view` (added to the M4 bundle).
- Teacher / Bursar / Staff → **no** audit access, matching the spec exactly
  ("no audit access unless explicitly granted") — neither bundle grants it.
- Parent / Student → none.

No scattered role-name checks — the Gate ability (`->can('audit.view')`,
`$this->authorize('audit.view')`) is the only check, composed with
`TenantContext` like every other M4 permission. There is no
`audit.manage`/`.export` permission: viewing and exporting are the same
capability (the export is the same data in a different format), matching
the precedent Question Bank/Entry Assessment already set for their own
view-covers-export design.

## 6. Why these actions and not every model

The spec is explicit: "do not blindly audit every model... focus on
actions where accountability is useful." Rather than a magic model-event
hook that would fire on every `Model::factory()->create()` call across the
*existing* 1189-test suite (including internal, non-user-driven saves), every
audited action is an **explicit call** at a genuine controller mutation
point — the same one-line-per-call-site shape `NotificationDispatcher`
already established for M18. This keeps the audit trail to moments a human
administrator actually cares about (a role changed, a payment was
recorded, an exam was scheduled) rather than every touched timestamp.

The academic/operational list (§3) covers every category the spec names by
name at least once — students, guardians, teachers, academic sessions,
results/report-card publishing, fees, CBT examinations, Question Bank
lifecycle, Entry/Placement Assessment — without attempting exhaustive
field-level coverage of every edit to every one of those models. A student
or teacher's routine profile edit is not audited, only their creation and
status change — the two moments with real accountability weight.

## 7. Tenant isolation

`AuditLogController` never relies on a global scope (there is none — see
§2): every query explicitly adds `where('school_id',
TenantContext::idOrFail())`, and the `{auditLog}` route parameter is
resolved by that same tenant-scoped `findOrFail`, so another school's id
404s on both `show` and any attempt to reach it — proven by
`AuditIsolationTest`. The actor/event/type filter dropdowns are built from
this school's own rows only (`AuditLogController::filterOptions()`), so a
cross-school user or event string never even appears as an option. Export
reuses the exact same tenant-scoped, filtered query the index page uses.

## 8. Performance

Synchronous, single-row inserts — no queue, no Redis, matching the spec's
explicit allowance ("a synchronous audit record is acceptable... where
consistency/accountability is more important than throughput"). Four
`school_id`-leading composite indexes exist for the query shapes the
viewer actually uses: `(school_id, created_at)` for the chronological list,
`(school_id, event)` and `(school_id, actor_id)` for their respective
filters, `(school_id, auditable_type, auditable_id)` for "show this
record's audit history" lookups (not yet surfaced in the UI, but the index
is ready for it — see §11). The index (paginated, 25/page) and export both
eager-load `actor` and use `chunk()` rather than `cursor()` for export (the
same reasoning as the Entry Assessment/Question Bank exports: `cursor()`
skips Eloquent's eager-loading entirely). No N+1 as entries grow, proven by
an explicit query-count regression test with and without filters applied.

## 9. UI

`resources/views/administration/audit-log/*` — `index.blade.php` (search +
four filters: event, user, affected type, date range; an Export CSV button
that carries the active filters through as query parameters),
`show.blade.php` (event, actor, timestamp, IP, affected record, and a
before/after JSON diff rendered as two side-by-side panels when present).
One new "Audit Log" nav item, gated `audit.view` alone (no module gate —
see §5 of the milestone rationale below). The existing dashboard
(`resources/views/dashboard.blade.php`) gained a small "Administration"
card — active/suspended member counts, enabled-module count, and the last
5 audit entries with a link to the full log — visible only to `audit.view`
holders, built from queries already cheap at this scale (a handful of rows
per school). No new frontend framework, no SPA.

## 10. Deliberately not built

Per the spec's explicit exclusions: a SIEM, centralized security
monitoring, Elasticsearch/OpenSearch, a real-time security operations
center, anomaly detection, AI security analysis, full application
performance monitoring, infrastructure/server/network/firewall logs,
advanced compliance reporting, automated audit-retention deletion, a
workflow-approval engine, a new authentication framework (Breeze / Fortify
/ Jetstream / Spatie were not introduced — the existing hand-rolled M2
authentication is untouched; only its already-fired events gained
listeners).

**Retention** is deliberately left undocumented-as-a-mechanism rather than
implemented: nothing in the existing architecture (no queue, no scheduler —
confirmed zero `Schedule::` calls, `sync` queue in tests, per every prior
milestone's own audit of this app) calls for building one now, and the
spec explicitly prefers documenting the gap over adding destructive
retention this milestone. If a school's audit history needs to be pruned
later, `AuditLog` rows are ordinary, ungated rows a future scheduled
command could delete by `created_at` age — no schema change would be
needed, only that command.

**Account activation/deactivation/suspension** (the M2/M4-level
`User.status` field) has no admin UI anywhere in this app, before or after
this milestone (`docs/authentication.md` §14 already lists "Admin UI for
changing `status`" as deferred). It was deliberately **not** added here:
`User.status` is account-wide, not per-school, so a School Admin toggling
it would suspend that person's access to *every* school they belong to —
a cross-school-impacting action a single school's administrator should not
have. Building it correctly (platform-admin-only? some other rule?) is a
real design decision belonging to whichever milestone actually introduces
the feature, not a side effect of adding auditing for it. What M26 *does*
audit is the one place account status already changes today —
`EnsureAccountIsActive`'s forced mid-session logout of a status-blocked
account fires the ordinary `Illuminate\Auth\Events\Logout` event, which
`RecordLogout` already captures as `auth.logout`.

## 11. Future extension points

- `auditable_type`/`auditable_id` are already indexed together
  (`audit_logs_school_auditable_index`) for a natural "show this record's
  own audit history" panel on a model's own show page later — not built
  in this milestone, since none of the spec's UI requirements asked for it
  and it would be scope creep to add now.
- `AuditRecorder::record()` is the only integration point a future module
  needs — no schema change, no second audit table, ever.
- A scheduled retention command (see §10) is the natural next step if a
  school ever needs one, once this app actually has a scheduler running
  anything.
