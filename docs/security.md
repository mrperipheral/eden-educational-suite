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
4. **Least privilege.** Roles/permissions (Milestone 2) grant the minimum;
   platform-admin capability is separate from any school role.
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

## Deferred (with the milestone that owns them)

- **M2:** authentication flows, login throttling / lockout, password-reset token
  expiry, "remember me" scope, re-auth for sensitive actions, role/permission
  enforcement, session fixation handling on login.
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
