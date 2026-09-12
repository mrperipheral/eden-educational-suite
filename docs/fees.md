# Fees & Fee Management

Status: **Milestone 19 — complete.** A production-ready, tenant-safe fee
management system: school-configured fee categories and structures,
student-specific charges snapshotted so a structure can change later
without touching history, manual payment recording with allocation across
one or more charges, and a server-calculated fee statement shared by staff
and the Parent/Student portals. Online payment (Paystack) is **not** built
here — that is Milestone 20.

## 1. Data model

```
FeeCategory            (school-owned; school-defined, e.g. Tuition/Books/Transport)
  └─ FeeStructure       (school-owned; category × session × period? × level × arm?, amount)
       └─ StudentFeeCharge   (school-owned + student-scoped; SNAPSHOTS the structure)
                                  ├─ discount_amount (via applyDiscount())
                                  └─ waived_at/by/reason (via waive()/unwaive())

FeePayment              (school-owned + student-scoped; manual receipt)
  └─ FeePaymentAllocation   (school-owned; how much of the payment → which charge)
```

`FeeCategory` mirrors `App\Models\AssessmentCategory` (M14) exactly — a
school-defined, freely-renameable list, **not** a hard-coded enum. Nothing in
the seed data ("Tuition", "Registration", …) is baked into code.

`FeeStructure` is ordinary, freely-editable configuration — editing its
`amount` (or anything else) **never** touches a `StudentFeeCharge` already
raised from it, because the charge copies every field it needs (category,
session, period, level, arm, amount, description) onto itself at creation
time via `App\Services\Fees\FeeChargeService`. This is the entire answer to
"a fee structure can change later without altering historical student
charges."

A charge's class context (`academic_level_id` / `level_arm_id`) is always
taken from the student's **current enrollment** at the moment of charging —
never a client-supplied value — so a charge always reflects the class the
student was actually in. A student with no current enrollment cannot be
charged at all (`FeeChargeService` throws `DomainException`, caught by the
controller as a friendly error).

## 2. Financial integrity

- **`amount` on a charge or payment is immutable** — set once at creation,
  never mass-assignable, never edited by any route. A correction is a
  distinct, audited action:
  - a charge: `StudentFeeCharge::applyDiscount()` (rejected if it would drop
    the payable amount below what is already allocated) or `waive()` /
    `unwaive()` (forgives/restores the remaining balance; the original
    `amount` never changes).
  - a payment: `FeePayment::void()` (a reversal — `voided_at`/`voided_by`/
    `void_reason` — excludes it from every balance calculation, but the row
    itself, and its allocations, are kept for the audit trail). There is no
    edit or destroy route for a payment.
- **The outstanding balance is always server-calculated**, never trusted
  from client input: `amount − discount_amount − (sum of non-voided
  allocations)`, floored at zero, `0` outright once waived
  (`StudentFeeCharge::outstandingBalance()`).
- **No floating-point arithmetic for money.** Every column is
  `decimal(12,2)` (cast `decimal:2`, which Eloquent returns as a string);
  every calculation — discount limits, allocation totals, statement sums —
  uses `bcmath` (`bcadd`/`bcsub`/`bccomp`) on those strings, never a native
  `+`/`-`/`<` on a float. Dashboard-wide totals use `SELECT SUM(...)` (a
  database-computed `DECIMAL`, read as a string), never Eloquent's
  `->sum()` (which casts through PHP float).
- **`App\Services\Fees\FeePaymentService`** is the only place a payment and
  its allocations are created, inside one `DB::transaction()` with the
  target charges `lockForUpdate()`-ed for the duration. It rejects, as one
  atomic unit (nothing is partially applied):
  - a non-positive allocation amount,
  - an allocation against a charge that is not this payment's own student's,
  - an allocation exceeding that charge's own outstanding balance,
  - allocations that in total exceed the payment's own amount.
- **Duplicate payment references are rejected** — `unique(school_id,
  reference)` at the database level plus `Rule::unique(...)` in the Form
  Request, so the same reference is fine in a different school but never
  twice in one.
- A payment may be **partially allocated** (a genuine advance credit,
  `FeePayment::unallocatedAmount()`) or left fully unallocated at recording
  time and allocated later.

## 3. Fee statement

`App\Services\Fees\FeeStatementBuilder::statementFor($student)` is the
single seam the staff statement page, the Parent Portal and the Student
Portal all call — the M16 shared-renderer pattern applied to fees, so a
statement can never drift between audiences. It eager-loads
`charges.allocations.payment` and `payments.allocations.charge` once, then
sums everything with `bcmath` over the already-loaded rows — no query per
charge or per payment, however many transactions a student has.

Returned totals: `totalCharged`, `totalDiscount`, `totalWaived`,
`totalPaid`, `totalOutstanding`, `totalReceived` (all non-voided payments),
`totalUnallocated` (advance credit not yet applied to any charge).

## 4. Payments — manual only

`App\Enums\PaymentMethod`: `Cash`, `BankTransfer`, `Pos`, `Cheque`, `Other`
— a closed, technical classification (unlike `FeeCategory`, which is a
school's own naming choice), so a proper enum, extended the same way every
other enum in this codebase is. **No online gateway integration, no
provider code of any kind, in this milestone** — every payment here is
recorded by a `fees.record-payment` holder after money already changed
hands elsewhere (cash in hand, a bank transfer received, …).

M20 (`docs/paystack.md`) later added a `Paystack` case to this same enum
for its own verified online payments — the manual-entry form and
`PaymentRequest` here explicitly exclude it: nobody recording a payment by
hand may claim it was paid online.

## 5. Permissions

New, added to `App\Enums\Permission` and slotted into the M4 role bundles:

| Permission | SchoolAdmin | Principal | Bursar | Teacher/Staff | Parent/Student |
|---|---|---|---|---|---|
| `fees.view` (one student's statement) | ✅ | ✅ | ✅ | — | — |
| `fees.report` (school-wide dashboard) | ✅ | ✅ | ✅ | — | — |
| `fees.manage` (categories, structures, raise a charge) | ✅ | — | ✅ | — | — |
| `fees.record-payment` | ✅ | — | ✅ | — | — |
| `fees.adjust` (discount/waive/unwaive/void) | ✅ | — | ✅ | — | — |

Principal is deliberately **view + report only** — oversight without
day-to-day cash handling or the ability to alter a balance, matching the
"existing financial-control design" precedent (`finance.view` was already
Principal-only, `finance.manage` Bursar-only, since M4). Teacher and Staff
get no fee permission at all — no existing permission supports a limited
read case for them. Parent/Student reach their own/linked child's statement
through their existing `portal.parent` / `portal.student` permission (no
new permission — the M16/M17/M18 pattern), never `fees.*`.

## 6. Routes

```
GET   /fees                                    fees.index             (fees.report)
GET   /fees/categories                         fees.categories.index  (fees.manage)
POST  /fees/categories                         fees.categories.store
PATCH /fees/categories/{category}               fees.categories.update
GET   /fees/structures                         fees.structures.index  (fees.manage)
GET   /fees/structures/create                  fees.structures.create
POST  /fees/structures                         fees.structures.store
GET   /fees/structures/{structure}/edit        fees.structures.edit
PATCH /fees/structures/{structure}              fees.structures.update
GET   /fees/students/{student}                 fees.students.show     (fees.view)
GET   /fees/students/{student}/charges/create  fees.students.charges.create  (fees.manage)
POST  /fees/students/{student}/charges         fees.students.charges.store
GET   /fees/students/{student}/payments/create fees.students.payments.create (fees.record-payment)
POST  /fees/students/{student}/payments        fees.students.payments.store
POST  /fees/charges/{charge}/discount          fees.charges.discount  (fees.adjust)
POST  /fees/charges/{charge}/waive             fees.charges.waive
POST  /fees/charges/{charge}/unwaive           fees.charges.unwaive
POST  /fees/payments/{payment}/void            fees.payments.void

GET   /parent/children/{student}/fees          parent.fees.show   (portal.parent, read-only)
GET   /student/fees                            student.fees.show  (portal.student, read-only)
```

All gated `module:fees`. Every id (`{category}`, `{structure}`,
`{student}`, `{charge}`, `{payment}`) is resolved by tenant-scoped
`findOrFail`, so another school's id 404s — never route-model-bound, never
trusted as-is.

## 7. Tenant isolation

Every table (`fee_categories`, `fee_structures`, `student_fee_charges`,
`fee_payments`, `fee_payment_allocations`) is `BelongsToSchool` and leads
its lookup indexes with `school_id`. `school_id` is never in any
`$fillable` and never read from request input on any model. Every
academic/category/structure id a Form Request accepts is checked with
`Rule::exists(...)->where('school_id', $schoolId)` — a cross-school id
fails validation with a generic "invalid" message, no leak.
`FeePaymentService::allocate()` additionally checks, inside the same
locked transaction, that a charge being allocated to actually belongs to
the payment's own student (not just the same school) — a same-school,
different-student charge is rejected exactly like a cross-school one.

Explicit tests (`tests/Feature/Fees/FeeTenantIsolationTest.php`) prove:
School A cannot read School B's charges; cannot create a charge using a
School B student; cannot allocate a payment to a School B charge; cannot
edit a School B fee category; a parent cannot open another parent's/family's
child; a student's own route carries no `{student}` parameter at all to
manipulate; `school_id` posted in any Fees form is silently ignored, never
honoured; and route ids across every Fees resource IDOR-404 for a foreign
school. Platform/Super Admin continues to operate only through the existing
selected-school context — no blanket tenant bypass was introduced.

## 8. Module activation

Reuses the pre-declared `Module::Fees` (declared since M7, description
updated), now `isAvailable()`. Depends on `Module::Students` only (already
declared). On by default. `module:fees` gates every staff route directly;
the Parent/Student portal fee routes are nested inside the existing
`module:parent-portal` / `module:student-portal` groups with an additional
`module:fees` layer, exactly like M18's announcements/notifications —
module activation is configuration, never authorization; every route still
carries its own permission gate independently.

## 9. UI

Reuses the existing shell and component kit throughout — `x-card`,
`x-badge`, `x-button`, `x-empty-state`, `x-alert`, `x-confirm` (financial
actions — waive, void — get an explicit confirmation dialog before
submitting), `x-input`. `fees/_statement-body.blade.php` is one shared
partial rendering the charges/payments lists and totals, included by the
staff statement page, the Parent Portal page and the Student Portal page
alike (`readOnly` toggles whether any action control renders) — no
duplicated markup between the three audiences. The dashboard
(`/fees`) supports search and pagination; the per-student statement lists
every charge and payment without pagination (bounded by one student's own
history, consistent with how the Parent/Student portals already render
results/attendance in full).

## 10. Performance

Every table's indexes lead with `school_id`, then the columns each screen
actually filters/joins on (`student_id`; `academic_session_id` +
`academic_period_id`; `fee_category_id`; `reference` unique;
`fee_payment_id` / `student_fee_charge_id` on the allocation table). The
dashboard and structures list are paginated. The statement builder and the
dashboard's per-student balances both eager-load once and compute in PHP —
proven by an explicit N+1 regression test (`FeeStatementTest::
test_statement_does_not_n_plus_one_for_a_student_with_many_transactions`).
No Redis, no queue — every write here is synchronous and small.

## 11. Deferred

Online payment / Paystack integration was explicitly out of scope for this
milestone and has since shipped in M20 (`docs/paystack.md`) — it extends
this architecture (a verified online payment becomes an ordinary
`FeePayment` with `method = paystack`) rather than replacing any of it.
A full discount/waiver audit-log table (the current `discount_amount` +
`waived_*` columns on the charge give a single-step audit — who/when/why —
without a separate append-only ledger; a richer history is a reasonable
future enhancement, not required by this milestone); bulk fee-structure
assignment / invoicing runs; fee reminders / overdue notifications (the
M18 notification foundation could carry these later); refunds (only a void
of an unallocated or newly-allocated payment is supported — reversing a
payment whose allocation has already been partly spent down by other
corrections is a manual, case-by-case operation for now); receipts/PDF
export beyond the browser-printable statement page; multi-currency.
