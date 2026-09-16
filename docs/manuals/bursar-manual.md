# Accountant / Bursar Manual

A finance-focused guide for the **Bursar** role. You have full operational
control over fees and payments, plus read-only access to student/guardian/
staff records (so you can find who you're billing) — you have **no**
access to academic data (results, attendance, assessments, CBT) at all.

---

## Login

Go to the sign-in page, enter your **Email address** and **Password**,
click **Sign in**. Use **Forgot password?** if needed.

## Dashboard

After signing in you land on the **Dashboard**. It shows a school-wide
snapshot, including a **Fees** KPI card (total outstanding / collected) if
the Reporting module is on. Your actual working area is **Fees**, in the
left navigation.

## Fee categories

**Purpose:** The named types of charge your school uses (Tuition, Books,
PTA levy, etc.) — set up once, reused everywhere.
**Who can do this:** School Admin and Bursar.

**Steps:** Go to **Fees** → **Categories**. Add a category with a name.
That's it — categories have no amount of their own; the amount comes from
a **Fee structure**.

## Fee structures

**Purpose:** Define how much a category costs for a given session/term/
level/(arm).
**Who can do this:** School Admin and Bursar.

**Steps:**
1. Go to **Fees** → **Fee structures** → **Create**.
2. Choose the category, session, optional term, level, and optional arm.
3. Enter the amount.
4. Save.

**Important:** Editing a structure later never changes charges already
raised from it — each raised charge is a permanent snapshot of the amount
at the time it was charged. Create a new structure (or edit this one) for
future terms only.

## Student charges

**Purpose:** Actually bill a student — creates a charge on their
statement.
**Who can do this:** School Admin and Bursar.

**Steps:**
1. Go to **Fees**, search for the student, open their statement.
2. Under **Charges**, there's a way to raise a new charge for that
   student (from a structure, or a one-off manual amount + description).
3. Save — it appears immediately on their statement as **Outstanding**.

## Discounts / waivers

**Purpose:** Reduce or forgive part or all of a specific charge.
**Who can do this:** School Admin and Bursar.

**Steps — apply a discount:**
1. On the student's statement, find the charge.
2. Click **Discount**, enter an amount and optional reason, click
   **Apply**. This reduces the payable amount — the charge is not
   removed, just reduced.

**Steps — waive a charge outright:**
1. Click **Waive** next to the charge, confirm.
2. This forgives the *remaining* balance completely. It's reversible —
   click **Reverse waiver** if it was applied in error; the balance
   becomes payable again.

**Common mistakes:** A discount can't exceed what's still owed on that
charge — the form will reject an over-large discount.

## Recording payments

**Purpose:** Log money actually received (cash, bank transfer, POS,
cheque, or other — not online/Paystack, which records itself
automatically).
**Who can do this:** School Admin and Bursar.

**Steps:**
1. Open the student's statement → **Record payment**.
2. Enter **Amount received**, **Payment date**, a **Reference / receipt
   number** (must be unique per school — duplicates are rejected), and the
   **Method**.
3. Optionally fill in payer name/phone/email and notes.
4. If there are outstanding charges, you can **allocate** the payment to
   one or more of them right away (enter how much goes to each) — or leave
   it unallocated as a credit you can allocate later.
5. Click **Record payment**.

**Expected result:** The payment appears on the statement; any allocated
amount reduces the matching charge's outstanding balance immediately.

**Common mistakes:**
- Reference numbers must be unique — reusing one from a previous receipt
  is rejected.
- You can't allocate more to a charge than it actually still owes, or more
  in total than the payment itself.

## Payment allocation

Covered above — allocation happens at the moment you record a payment, or
you can leave a payment unallocated and come back to allocate it against a
charge later from the same statement screen.

## Outstanding balances

**Purpose:** See who owes what, right now.
**Steps:**
1. Go to **Fees** — shows school-wide totals (charged/discounted/
   collected) and a searchable list of every student with their current
   outstanding balance.
2. Search by name or admission number.
3. For a full breakdown per class/level, use **Reports** → **Fees** (see
   below) with filters.

## Statements

**Purpose:** A single student's complete financial picture.
**Steps:** Open the student from the **Fees** list (or search). You'll see
five summary figures (Charged, Discounted, Waived, Paid, Outstanding), the
full list of **Charges** (with status badges: Outstanding / Paid /
Waived), and the full list of **Payments** (with allocation detail).

## Payment history

The **Payments** panel on a student's statement is the full history —
every payment ever recorded for that student, including voided ones
(shown with a **Voided** badge and the void reason).

**Voiding a payment:** click **Void** next to a payment, confirm. This
reverses it from balance calculations but **keeps the record** — nothing
is ever deleted. This cannot be undone from the UI; a genuine reversal
needs a new corrective entry if money needs to move again.

## Online payment visibility

If your school has Paystack configured (School Admin sets this up under
**School settings** → **Payments**), parents/students see a **Pay online**
button on their own statement. A successful online payment is verified by
the system itself and appears automatically as a payment on the student's
statement, tagged with the **Paystack** method — you don't need to do
anything manually, but you can see it in the payment history like any
other payment. You never see or handle the Paystack secret key — that's
configuration-only, entered once by School Admin.

## Financial reports

**Purpose:** Filtered, exportable views across the whole school's fee
activity.
**Steps:**
1. Go to **Reports** → **Fees & collections**.
2. Tabs: **Collection summary**, **Outstanding balances** (filterable by
   session/period/level/arm), **Payment activity** (filterable by method/
   date range — includes Paystack payments).
3. **Export CSV** for any of these, respecting your current filters.

## Relevant audit information

You do **not** hold audit-log access (`audit.view`) unless your School
Admin has also granted you the Principal role elsewhere — as Bursar alone,
you won't see the **Audit Log** link. Every payment you record, void, or
adjust is still tracked internally by the system for the administrators
who do have that access.

---

## What you can do vs. what's reserved for others

| Action | You (Bursar) |
|---|---|
| View students/guardians/staff | ✓ (read-only, for billing) |
| Record/void payments, discount/waive charges | ✓ |
| Configure fee categories/structures | ✓ |
| View/export fee reports | ✓ |
| View/edit **any** academic data (results, attendance, assessments, CBT) | ✗ — no access at all |
| Manage school settings, modules, or members | ✗ — School Admin only |
| View the Audit Log | ✗ — School Admin/Principal only |
