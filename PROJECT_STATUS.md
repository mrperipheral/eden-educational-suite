# Project Status

_Last updated: 2026-09-10_

## Current milestone

**Milestone 2 — Authentication & User Foundation: COMPLETE.**

Next up: **Milestone 3 — Multi-School Core** (not started; do not begin without
picking it up explicitly). A Roles & Permissions milestone is sequenced with it.
See `docs/roadmap.md`.

## What the application is

Multi-school School Management SaaS (management + portals only — no website
features). PHP 8.3 · Laravel 13.31 · MySQL 8 · Blade + Tailwind v4 + Alpine.js +
Vite · PHPUnit · Pint.

## Environment (verified 2026-09-10)

| Item | Value |
|------|-------|
| PHP | 8.3.30 (Laragon) |
| Laravel | 13.31.0 |
| Node / npm | 22.22.0 / 10.9.4 |
| Database | MySQL 8.4 `schoolmanagement_db` |
| Tests | `php artisan test` — 72 passing (206 assertions) |
| Build | `npm run build` — passing |
| Formatting | `vendor/bin/pint --test` — passing |

## Delivered in Milestone 1

Platform inspection; project conventions; responsive Blade shell + UI kit;
`TenantContext` seam; `/health` endpoint; testing & documentation foundation.
(See git tag `foundation-complete`.)

## Delivered in Milestone 2

- **User model foundation** — `status` column + `App\Enums\UserStatus`
  (`active`/`suspended`/`disabled`); `MustVerifyEmail`; `status` kept out of
  `$fillable`. No `school_id`, no domain/role tables.
- **Registration** — name/email/password only, lower-cased unique email,
  `Password::defaults()` policy, bcrypt, fires `Registered`, logs in → verify
  notice.
- **Login** — throttled (5/identity+ip, `Lockout` event), session regeneration,
  generic `auth.failed` (no enumeration), account-status gate.
- **Logout** — session invalidate + CSRF token regenerate.
- **Password reset** — Laravel broker, 60-min single-use token, neutral
  "maybe-sent" response, `remember_token` rotation.
- **Email verification** — signed + throttled verify route; `verified` required
  on the authenticated app group only; email change re-triggers verification.
- **Password confirmation** — `password.confirm` flow wired to account deletion;
  reusable for future sensitive actions.
- **Account/profile settings** — update name/email, change password
  (`current_password` required), delete account (behind password confirmation).
- **Authenticated shell** — `<x-layouts.authenticated>` (nav + user menu) and a
  **placeholder** dashboard. No role-specific dashboards.
- **Auth UI** — login, register, forgot/reset password, verify email, confirm
  password — polished, responsive, accessible, on the Milestone 1 component kit
  plus `x-checkbox`, `x-dropdown`, `x-auth-heading`.
- **Security baseline & `AppServiceProvider`** — `Password::defaults()` (stricter
  in prod), `session.secure` forced in production.
- **Authorization direction documented** (User → Permission → School → Policy →
  Action); not implemented.
- **Docs** — new `docs/authentication.md`; updated `architecture.md`,
  `security.md`, `database-design.md`, `ui-ux-guidelines.md`, `roadmap.md`,
  `CLAUDE.md`.

## Explicitly NOT done (by design)

Roles/permissions & Spatie Permission · `schools` table / tenant middleware /
global scopes / school switching · onboarding / subscriptions · students /
guardians / teachers / staff / academics / attendance / results / fees /
Paystack / CBT / portals · 2FA / social login · auth audit logging · real mail
transport · Redis / queues / object storage · any website functionality · any
500-school limit.

## Database

Framework tables + `users.status` (migration
`2026_09_10_120000_add_status_to_users_table`). No other schema changes.

## Routes (application, `--except-vendor`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/` | `home` |
| GET | `/health` | `health` |
| GET/POST | `/register` | `register` |
| GET/POST | `/login` · POST `/logout` | `login` / `logout` |
| GET/POST | `/forgot-password` | `password.request` / `password.email` |
| GET/POST | `/reset-password/{token}` · `/reset-password` | `password.reset` / `password.store` |
| GET | `/verify-email` | `verification.notice` |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` (signed) |
| POST | `/email/verification-notification` | `verification.send` |
| GET/POST | `/confirm-password` | `password.confirm` |
| GET | `/dashboard` | `dashboard` _(auth, verified, active)_ |
| GET/PATCH/DELETE | `/settings/profile` | `settings.profile.*` _(DELETE + password.confirm)_ |
| PUT | `/settings/password` | `settings.password.update` |

## Tests

72 passing / 206 assertions. New: `Unit/Auth/UserStatusTest`,
`Feature/Auth/{Registration,Authentication,Logout,AccountStatus,PasswordReset,EmailVerification,PasswordConfirmation,AuthPages}Test`,
`Feature/Settings/ProfileUpdateTest`. Milestone 1 tests unchanged and green.

## Known follow-ups / recommendations

- Production env must set `SESSION_SECURE_COOKIE=true` (also force-set in code),
  a real `MAIL_MAILER`, and `APP_DEBUG=false`.
- Consider "log out other devices" on password change, and auth-event audit
  logging, when the audit-log milestone lands.
- Add a CI workflow (Pint + PHPUnit + `npm run build`).
- `password.confirm` is currently exercised only by account deletion — extend to
  admin/financial actions as they are built.
