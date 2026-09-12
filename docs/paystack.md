# Online Fee Payment — Paystack

Status: **Milestone 20 — complete.** Lets an authorised parent or student
start an online fee payment through Paystack and have a genuinely verified
successful payment recorded into the exact same fee/payment system M19
built — never a parallel or duplicated one. Reference:
[Paystack's own API docs](https://paystack.com/docs/api/transaction/) for
the Initialize/Verify Transaction endpoints and
[webhook signature verification](https://paystack.com/docs/payments/webhooks/).

## 1. What M20 is, and isn't

M20 adds exactly one new payment *channel* on top of M19's existing
payment/allocation/balance architecture. It does **not** introduce a second
notion of "payment" — a verified online payment ends its life as an
ordinary `App\Models\FeePayment` row (`method = paystack`), allocated by the
exact same `App\Services\Fees\FeePaymentService` every manual payment goes
through. Balance calculation, the fee statement, and payment history are
untouched M19 code, reused as-is.

Paystack integration lives entirely under `App\Services\Paystack\*` and
`App\Http\Controllers\PaystackWebhookController` /
`App\Http\Controllers\Portal\*OnlinePaymentController` — a deliberate
boundary. Swapping the provider later means replacing that namespace, not
touching `App\Models\FeePayment`, `FeePaymentService`, or the statement
builder.

## 2. Data model

```
PaystackTransaction          (school-owned, BelongsToSchool, its own table)
  ├─ student_id               who it's for
  ├─ initiated_by             the User who started it (parent or student)
  ├─ reference                ours; globally unique; sent to Paystack as its reference
  ├─ amount, currency         the expected naira amount/currency, snapshotted at initiation
  ├─ status                   App\Enums\PaystackTransactionStatus
  ├─ authorization_url, access_code   from Paystack's initialize response
  ├─ provider_transaction_id, gateway_response, channel, paid_at   from verify
  ├─ failure_reason
  └─ fee_payment_id           nullable, UNIQUE — set exactly once, on success
```

`PaystackTransaction` is deliberately **separate** from `FeePayment`: an
attempt can fail or be abandoned and must never become an authoritative
payment. Only a `successful` transaction ever gets a `fee_payment_id` —
that column being set is the idempotency guard described below.

## 3. Lifecycle

`App\Enums\PaystackTransactionStatus`: `pending` → `successful` |
`failed` | `abandoned` | `verification_failed`. `pending` is the only
non-terminal state; nothing ever moves a transaction out of a terminal
state. No route lets an ordinary user set this directly — every transition
goes through `PaystackTransaction::markSuccessful()` /
`markFailed()` / `markAbandoned()` / `markVerificationFailed()`, called
only from `App\Services\Paystack\PaymentVerificationService` inside a
row-locked DB transaction.

## 4. Initiation — `App\Services\Paystack\PaymentInitiationService`

1. Confirms the school has Paystack `enabled` **and** both keys configured
   (`SchoolSetting::paystackReady()`) — a school is never required to turn
   this on; M19 works fully with it off.
2. Validates the requested amount against the student's **current**
   outstanding balance via M19's own `FeeStatementBuilder` — never a
   client-submitted total, and never more than what M19 says is actually
   owed (M19 has no overpayment concept, so neither does this).
3. Creates the local `PaystackTransaction` row, then calls Paystack's
   `POST /transaction/initialize` with `email` (the initiating user's own),
   `amount` (**kobo** — `amount × 100`, since Paystack's API is always in
   the smallest currency unit), `currency`, `reference`, `callback_url`,
   and non-sensitive `metadata` (`student_id`, `school_id`).
4. If the Paystack call fails, the whole thing — local row included — rolls
   back inside one DB transaction. There is never a `pending` row with
   nothing behind it at Paystack.
5. The browser is redirected to Paystack's returned `authorization_url` —
   Paystack's own hosted checkout page (the "Standard"/redirect flow). The
   application never collects, transmits, or stores a card number, CVV, or
   any other raw card detail; Paystack's page handles that entirely.

## 5. Verification — the idempotency core

`App\Services\Paystack\PaymentVerificationService::verifyAndRecord($reference)`
is the **one** place a transaction is ever resolved, called identically by
the browser callback and the webhook:

1. Look up the `PaystackTransaction` by `reference`, bypassing the tenant
   scope (nothing is scoped yet) — this is a plain read, not a trust
   decision.
2. Anchor `TenantContext` to *that row's own* `school_id`.
3. Inside a DB transaction, `lockForUpdate()` the row. If it is no longer
   `pending`, return it unchanged — a duplicate webhook, a reloaded
   callback page, or a webhook racing the callback all land here and no-op.
4. Otherwise, call Paystack's `GET /transaction/verify/:reference` with
   *that school's own* secret key — the sole authoritative source of truth.
   The webhook's own JSON body is never trusted for the actual charge
   status; only its `reference` is used, purely to know what to verify.
5. Check, in order: `data.status === 'success'` (anything else —
   `abandoned`, `failed`, or a genuinely unrecognized/in-progress value —
   is handled without ever guessing, see below); the verified `amount`
   (kobo) matches the expected amount; `currency` matches; and, as a
   defense-in-depth extra, the echoed `metadata.student_id` matches.
6. Only if every check passes: `FeePaymentService::planFifoAllocation()`
   computes which of the student's *currently* outstanding charges the
   amount covers (oldest first — a parent pays a total, not individual
   charges), then `FeePaymentService::record()` creates the real
   `FeePayment` + allocations, and the transaction is marked `successful`
   with `fee_payment_id` set.

**Ambiguous results are never guessed at.** A `data.status` that is neither
`success`, `failed`, nor `abandoned` (still processing, an unrecognized
value) leaves the transaction `pending` for a later retry. A failure to
even *reach* Paystack (`PaystackApiException`) rolls the whole DB
transaction back — status stays `pending`, nothing is recorded — rather
than being interpreted as a failed payment.

## 6. Webhook — `App\Http\Controllers\PaystackWebhookController`

Registered outside `auth`/`tenant`/`module` entirely (`POST
/webhooks/paystack`, CSRF-exempted in `bootstrap/app.php`) — Paystack
cannot authenticate as one of our users.

**Which school's secret verifies the signature?** Each school can hold its
own Paystack account, so there is no single app-wide webhook secret. The
(as yet unverified) payload's `data.reference` is looked up against our own
`paystack_transactions` first — a plain database read, not a trust
decision — to find *whose* transaction this claims to be; only *that*
school's stored secret key is then used to compute and compare the
signature (`hash_hmac('sha512', $rawBody, $secretKey)` against the
`x-paystack-signature` header, via `hash_equals()`). An unrecognized
reference is acknowledged (`200`) and ignored before any signature check
runs, since there is no key to check it against. **The school is determined
entirely from our own trusted stored data — never from anything in the
request** (M20 spec §6).

An invalid or missing signature is rejected with `401` and nothing is
processed. A valid one delegates straight to
`PaymentVerificationService::verifyAndRecord()`. A `PaystackApiException`
while processing returns `500`, which tells Paystack to retry delivery
later; every other outcome (including "unknown reference" and "already
processed") returns `200` so Paystack stops retrying.

## 7. Configuration — `App\Models\SchoolSetting`

Additive columns on M6's `school_settings` table: `paystack_enabled`
(bool), `paystack_public_key` (string), `paystack_secret_key` (`encrypted`
cast — Laravel's native `Crypt` facade keyed by `APP_KEY`, no new
infrastructure), `paystack_test_mode` (bool, display-only — Paystack itself
determines live/test from which key prefix is stored). Edited at
`/settings/school/payments`, gated by the *existing* `school.settings.view`
/ `.update` permissions (no new permission needed) — the same section
pattern as Branding/Regional. The secret key is never rendered back into
the edit form; a blank field on save means "keep the existing key",
implemented in `SchoolSettingsController::updatePayments()`.

Local development uses Paystack's test keys (`pk_test_…` / `sk_test_…`) —
get a free test-mode account and turn "test mode" on in the settings form.
No live credentials are needed to build against this integration; nothing
is hard-coded, and nothing is committed to the repo.

## 8. Permissions

**No new `Permission` cases.** Paystack routes reuse the existing
`portal.parent` / `portal.student` (Parent/Student can initiate/view their
own payments, exactly like every other portal feature since M16) and
`school.settings.view` / `.update` (config). The M20 spec's suggested
`fees.pay_online` / `fees.payment_view` were considered and deliberately
not added: they would duplicate a boundary `portal.parent`/`portal.student`
already draws precisely, and M16–M19 established the same "reuse the
portal permission, don't invent a parallel one" pattern for every prior
portal feature (announcements, notifications, the fee statement itself).
Staff never initiate online payments — that capability is portal-only by
design (Bursars/Admins keep using the M19 manual-payment flow).

## 9. Tenant isolation

Every route id (`{student}`) is resolved through the same
`ParentPortalAuthorizer` / `StudentPortalAuthorizer` every other portal
controller uses — never trusted from the URL. Student ids are never reused
across schools (one global sequence), so "does this reference's
`student_id` match the student I resolved" is on its own a reliable
cross-school/cross-family check, applied on every callback before a receipt
is ever rendered. The webhook determines its school from its own stored
transaction row, never from request input (§6). `TenantContext` is
explicitly restored to the request's original school immediately after
`verifyAndRecord()` returns in both portal callback controllers, so a
malicious/foreign reference can never leave the rest of that response
rendered under the wrong tenant. See `tests/Feature/Paystack/*` for the
explicit cross-school/cross-family test suite.

## 10. Money handling

Exactly M19's convention: `decimal(12,2)` storage, `bcmath` for every
comparison/arithmetic operation, never a native float. The one
Paystack-specific wrinkle is the kobo conversion at the API boundary
(`bcmul($amountInNaira, '100', 0)` going out, compared back as an integer
string coming in) — Paystack's API always operates in the smallest
currency unit.

## 11. Performance

`reference` is a unique index (transaction lookup, both from the browser
callback and the webhook, is a single indexed `WHERE`); `fee_payment_id` is
a unique index (the idempotency guard is an index lookup, not a table
scan); `(school_id, student_id)` and `(school_id, status)` cover the
remaining lookups. No Redis, no queue — Paystack's own webhook retry
mechanism is the retry story for a transient failure on our side, so
nothing here needed to build one.

## 12. Deferred

A dedicated staff-facing list of online-payment attempts (successful ones
already appear in the ordinary fee statement/payment history; failed/
abandoned attempts are visible only via `paystack_transactions` directly —
a support/reconciliation screen for those is a reasonable future addition,
not required here); refunds/reversals through Paystack's own refund API
(a manual `FeePayment::void()` already covers "this shouldn't count", per
M19); recurring/subscription billing; any payment channel other than
Paystack (explicitly out of scope for M20); split payments across multiple
students in one checkout; a dedicated inline-JS/Popup checkout (the
redirect flow was chosen as the simplest, dependency-free integration for
this server-rendered app — switching later is a `PaymentInitiationService`
change only, not a redesign).
