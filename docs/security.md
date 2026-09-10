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
   mutate School B data. This is guaranteed by `TenantContext` + the (upcoming)
   `BelongsToSchool` global scope + tenant-scoped validation, and proven by
   dedicated cross-tenant tests.
4. **Least privilege.** Roles/permissions (a later milestone) grant the minimum;
   platform-admin capability is separate from any school role. Code checks
   permissions, never role names.
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

## Deferred (with the milestone that owns them)

- **Auth follow-ups:** role/permission enforcement, 2FA, "log out other devices"
  on password change, session listing, auth-event audit logging, templated
  transactional emails.
- **M3:** `EnforceTenant` middleware, `BelongsToSchool` scope, cross-tenant test
  suite, per-tenant rate limiting considerations.
- **Later:** audit logging (who did what, per school), secure file upload
  (type/size validation, out-of-webroot or object storage, virus posture),
  encryption of sensitive PII at rest, data export/erasure handling, 2FA for
  admins, security headers (CSP) review, dependency scanning in CI.

## Review checklist for every PR

- [ ] New write route has a Policy check or documented reason it is public
- [ ] New write route validates through a Form Request
- [ ] New query on tenant data goes through the tenant scope (no bare `school_id`)
- [ ] No secret, key or credential added to tracked files
- [ ] Error/exception paths do not disclose stack traces or config in production
- [ ] User-supplied identifiers are authorised for the current tenant/user
