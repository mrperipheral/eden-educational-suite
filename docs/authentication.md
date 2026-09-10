# Authentication & User Foundation

Status: **Milestone 2 — complete.** Built on Laravel's native authentication
primitives. No authentication package (Breeze / Fortify / Jetstream / Sanctum)
is installed — see [Decisions](#decisions).

## 1. Overview

| Concern | Implementation |
|---------|----------------|
| Registration | `Auth\RegisteredUserController` + `Auth\RegisterRequest` |
| Login | `Auth\AuthenticatedSessionController` + `Auth\LoginRequest` (throttle + status gate) |
| Logout | `AuthenticatedSessionController@destroy` — invalidate + token regenerate |
| Password reset | Laravel `Password` broker + `Auth\PasswordResetLinkController` / `Auth\NewPasswordController` |
| Email verification | `MustVerifyEmail` on `User` + framework `verification.*` routes |
| Password confirmation | Framework `password.confirm` middleware + `Auth\ConfirmablePasswordController` |
| Account status | `App\Enums\UserStatus` + `EnsureAccountIsActive` middleware + `LoginRequest` check |
| Profile / password / delete | `Settings\ProfileController`, `Settings\PasswordController` |

Routes live in `routes/auth.php` (guest + auth auth-flow routes) and the
authenticated application group in `routes/web.php`.

## 2. Account lifecycle

```
                     register
                        │
             ┌──────────▼───────────┐
             │ status = active      │  (email unverified)
             │ email_verified_at =∅ │
             └──────────┬───────────┘
                        │ click signed verification link
             ┌──────────▼───────────┐
             │ verified, active     │  ← full access
             └──────────┬───────────┘
        admin action    │
       ┌────────────────┼───────────────┐
       ▼                ▼               ▼
   suspended         disabled        (deleted by user)
   cannot log in     cannot log in   row removed
   ejected mid-      ejected mid-
   session           session
```

- **`status`** (`app/Enums/UserStatus.php`): `active` | `suspended` | `disabled`.
  Column `users.status`, `string(20)`, default `active`, **indexed**.
- Only `active` may authenticate. Enforced in two places, both server-side:
  1. `LoginRequest::authenticate()` — after the password is verified, a
     non-active user is immediately logged out with a generic message.
  2. `EnsureAccountIsActive` (`active` middleware) on every authenticated route —
     a session whose account was suspended/disabled after login is invalidated
     on the next request.
- `status` is **not mass-assignable** (not in `User::$fillable`). It is an
  administrative flag; admin tooling to change it arrives with the roles / school
  milestones.
- There is no elaborate state machine — three states, one transition rule.

## 3. Registration

Collects **only** `name`, `email`, `password` (`RegisterRequest`). Explicitly
not collected: NIN/BVN/government ID, student/school/bank data — those belong to
later, documented domain workflows.

- `email` normalised to lower-case, `unique:users,email`.
- `password`: `confirmed` + `Password::defaults()` (see §7).
- Password hashed with `Hash::make` (bcrypt, cost 12; the model's `hashed` cast
  is idempotent).
- Fires `Registered` → framework sends the verification email.
- User is logged in and redirected to the verification notice.

## 4. Login

- `email` + `password`, optional `remember`.
- **Throttled**: 5 failed attempts per `lower(email)|ip` → lockout with
  `Lockout` event; `POST /register`, `POST /forgot-password`,
  `POST /reset-password`, verification and password-confirm routes carry an
  additional `throttle:6,1`.
- **Session fixation**: `session()->regenerate()` after a successful attempt.
- **Generic errors**: wrong password and unknown address both return
  `auth.failed` ("These credentials do not match our records.") — no account
  enumeration via the login form.
- Account status enforced (§2).
- Credentials are never logged (no `Log::` calls in the auth path; requests are
  not dumped).

## 5. Logout

`POST /logout` (auth only): `Auth::logout()`, `session()->invalidate()`,
`session()->regenerateToken()` (fresh CSRF token), redirect to `/`.

## 6. Password reset

Standard Laravel broker (`config/auth.php` → `passwords.users`):

- Token table `password_reset_tokens`, **expiry 60 min**, broker throttle 60 s.
- `POST /forgot-password` always responds with the same neutral flash message,
  whether or not the address exists — no enumeration.
- `NewPasswordController` sets the new hash and rotates `remember_token`
  (invalidating "remember me" cookies). The broker deletes the reset token on
  success, so it is single-use.
- `PasswordReset` event fired.

## 7. Password policy

One definition, applied everywhere via `Password::defaults()` (set in
`AppServiceProvider::boot()`):

| Environment | Rule |
|-------------|------|
| local / testing | min 8, ≥1 letter, ≥1 number |
| production | min 10, mixed case, ≥1 letter, ≥1 number, **not in a known breach** (`uncompromised()`) |

`uncompromised()` calls the k-anonymity HIBP range API; it is deliberately
disabled outside production to keep tests offline and fast.

## 8. Email verification

- `User implements MustVerifyEmail`. New accounts have `email_verified_at = null`.
- `verification.verify` is a **signed**, throttled route; the hash is
  `sha1(user email)`, so a link for the wrong address 403s.
- **Which routes require `verified`:** the authenticated application group
  (`dashboard`, `settings/*`). The verification notice / resend / verify routes
  and `logout` require only `auth` — otherwise an unverified user could never
  act to become verified.
- Changing the email address in profile settings nulls `email_verified_at` and
  sends a fresh verification email for the new address.
- Mail transport is `log` for now; no SMS/notification infrastructure was added.

## 9. Password confirmation

Framework `password.confirm` middleware + `Auth\ConfirmablePasswordController`
and `auth/confirm-password` view. Confirmation is remembered for
`config('auth.password_timeout')` (3 h).

Applied to: **account deletion** (`DELETE /settings/profile`). That is the only
current sensitive action without an inherent password step. The plumbing is
ready for later 2FA management, API tokens, financial and administrative actions
— add the middleware to those routes, do not scatter ad-hoc password prompts.

## 10. Session & cookie behaviour

- Driver: `database` (`sessions` table). Stateless app servers; no Redis.
- `http_only` true, `same_site` lax (framework defaults, unchanged).
- `session.secure` is **forced true in production** by `AppServiceProvider`
  regardless of the env file; locally it follows `SESSION_SECURE_COOKIE`.
- CSRF: Laravel's `web` middleware group on every state-changing route; all
  forms use `@csrf`, method-spoofed forms use `@method`.
- Login regenerates the session id; logout invalidates it and the CSRF token.

## 11. Authorization direction (foundation only — not implemented here)

```
User ──▶ Role / Permission ──▶ School Context ──▶ Resource Policy ──▶ Action
        (Milestone 2.x/3)      (Milestone 3)       (per-model)        (controller)
```

Conventions established now, to be filled in by later milestones:

- **Roles and permissions are separate concepts.** Code checks *permissions*
  (`$user->can('invoice.create')`), never role names. Roles are only bundles of
  permissions.
- Planned roles: Super Admin, School Admin, Principal, Teacher,
  Accountant/Bursar, Staff, Parent, Student. Super Admin is a *platform* role,
  outside any school; the rest are *per school*.
- **No role name checks in controllers.** Authorization goes through Policies /
  Gates. Controllers call `$this->authorize(...)` / `Gate::authorize(...)`.
- The eventual permission check will be composed with `TenantContext` so a
  permission only applies within the acting school.
- **Spatie Permission is not installed.** It remains a candidate for the
  role/permission milestone; the decision will be recorded in
  `docs/architecture.md` when taken.

## 12. Performance notes

Login is a high-frequency path and is kept cheap:

- One indexed lookup by `email` (unique index) to load the user; one bcrypt
  verify. No eager loads, no domain queries, no future-module tables touched.
- `status` is a column on the already-loaded `users` row — the status gate adds
  no query.
- `EnsureAccountIsActive` reads `$request->user()` (already resolved by the
  `auth` middleware) — no extra query.
- Throttling uses the cache store (`array` in tests, `database` in local, swap to
  Redis later via config only).
- No queues, no Redis introduced for authentication.

## 13. Decisions

| Decision | Rationale |
|----------|-----------|
| Hand-rolled auth on framework primitives, **no starter package** | Breeze/Fortify would bring a second set of layouts, components and a Tailwind config that conflict with the Milestone 1 UI foundation, for controllers we'd own anyway. The framework already provides guards, the `Password` broker, `MustVerifyEmail`, `RateLimiter`, and all needed middleware. |
| `status` as a string enum column, not a separate `account_states` table | Three fixed states; a lookup table is over-engineering. |
| Account-status check at login **and** per-request middleware | Login-only leaves existing sessions valid after suspension. |
| `password.confirm` wired to account deletion only | Task: "do not add unnecessary password prompts". One real use proves the flow; the rest is opt-in later. |
| `verified` on the app group, not on verification/logout routes | Users must be able to verify or leave. |
| Keep `lang/en/auth.php` in the repo | Auth error copy is product copy and should be reviewed. |

## 14. Deferred

- Roles & permissions (own milestone; evaluate Spatie then).
- User ↔ school relationship, `EnforceTenant`, tenant global scope (Milestone 3).
- 2FA / TOTP, "log out other devices" on password change, session listing.
- Admin UI for changing `status`.
- Social login, SSO.
- Audit logging of auth events (login, lockout, password change).
- Real mail transport / templated verification & reset emails.
