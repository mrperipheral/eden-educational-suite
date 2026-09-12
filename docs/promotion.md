# Promotion & Graduation

Status: **Milestone 21 — complete.** A safe, auditable academic progression
workflow: authorised school users can promote eligible students from one
academic session/class placement to another, and graduate students when
appropriate. Built entirely on M9's existing `Student`/`Enrollment`
architecture — no new enrollment mechanism, no generic workflow engine.

## 1. Data model

```
PromotionBatch          (school-owned; one bulk promotion run)
  └─ PromotionRecord     (school-owned + student-scoped; one row per student per batch)

Student
  ├─ graduated_at / graduated_academic_session_id / graduation_notes / graduated_by
  │    (additive columns — the entire graduation audit trail; no separate table)
  └─ promotionRecords()  (a student's own promotion history, across every batch)
```

A `PromotionBatch` records the **source** session/period/level/arm, the
**target** session/level/arm, who ran it, an aggregate `status`, and optional
notes. A `PromotionRecord` is the durable per-student outcome: `promoted`,
`skipped` (an eligibility rule stopped it safely, before anything was
written — not an error), or `failed` (an unexpected condition, e.g. the
student vanished from the eligible roster between page load and submission).
`unique(school_id, promotion_batch_id, student_id)` — a batch can never log
the same student's transition twice.

Graduation has **no separate batch/history table**. A student can only be
graduated once at a time (unlike promotion, which repeats every session), so
the four `graduated_*` columns on `students` **are** the history —
`Student::where('status', 'graduated')` is the graduation list. This
deliberately avoids a second, competing lifecycle mechanism (M21 spec §8).

## 2. Promotion is just a new enrollment

Promoting a student **never rewrites history**. It:

1. Creates a brand-new `Enrollment` row for the target session/level/arm.
2. Calls `Enrollment::makeActive()` — M9's own method, unchanged — which
   closes the student's previous active enrollment (`status = completed`,
   `ended_on` stamped) and makes the new one active.

The source enrollment is never edited beyond that status/`ended_on` change,
never deleted. Every result, report card, attendance record and fee charge
tied to the old enrollment/session stays exactly where it was — none of
those tables reference "current class," they reference the specific
enrollment/session they were created against.

`Student::currentEnrollment` is a **live** `hasOne` relation. The Parent
Portal, Student Portal and every staff view that shows "current class"
already query this relation, so a promoted or graduated student's new
placement/status shows up automatically — no portal code changes were
needed for this (M21 spec §11).

## 3. Eligibility

`App\Services\Promotion\PromotionEligibilityService` is the single seam both
the promotion UI (building the selectable roster) and
`PromotionService` (re-validating server-side — a submitted student id list
is never trusted alone) use.

Eligible to be promoted means: `StudentStatus::Active` **and** an `active`
`Enrollment` for exactly the given source session/level/arm. A graduated or
withdrawn student, or one already moved to a different class, is never
eligible. There is deliberately **no pass/fail or results-based rule** —
promotion is an authorised administrative decision, not an automatic
calculation (M21 spec §2).

"Already promoted to the target placement" is checked independently:
`alreadyInTargetSession()` — does the student hold *any* enrollment (any
status) for the target session already? If so, promoting again is a
**skip**, not a duplicate.

Graduation eligibility (`GraduationService::graduate()`) requires: the
student exists, is currently `Active` (not already graduated, not withdrawn
or inactive), and — if they hold a current enrollment — it's closed the
same way promotion closes one. There is **no hard-coded graduating level**:
the candidate roster is whichever class the operator chooses to browse (the
same source-picker UX as promotion), because not every school graduates
from the same level (M21 spec §6).

## 4. Bulk promotion & per-student isolation

`PromotionService::promoteBatch()`:

1. Creates the `PromotionBatch` row.
2. Resolves the eligible-id set **once** for the source class.
3. Processes each selected student **independently** — every student's
   transition is wrapped in its **own** `DB::transaction()` with the
   `Student` row `lockForUpdate()`-ed for its duration. One student's
   failure never rolls back another's success (M21 spec §4). This is a
   deliberate departure from a single batch-wide transaction.
4. Computes the batch's aggregate `status` once every student has been
   processed: `Completed` (all promoted), `Failed` (none promoted),
   `PartiallyCompleted` (a mix).

`GraduationService::graduateBatch()` follows the same per-student isolation
pattern (each student's own `PromotionException`, if any, is caught and
collected — the rest of the batch still proceeds), returning
`{graduated, failed, failures}` rather than writing a persisted batch row
(graduation has no batch concept — see §1).

### Concurrency

Two administrators promoting/graduating the same student at once is guarded
by the `Student` row lock, not a database uniqueness constraint. A DB-level
unique constraint on `(session, level, arm)` for enrollments was
**deliberately rejected** — it would have broken M9's own
`test_historical_enrollments_are_preserved`, which legitimately creates two
enrollment rows with identical (session, level, arm) for one student (one
`completed`, one `active`). Instead: whichever transaction commits first
wins; the other re-checks "already in target session" (or "already
graduated") after acquiring the lock and finds it true, so it **skips**
rather than double-enrolling or double-graduating.

## 5. Permissions

`promotion.view`, `promotion.manage`, `graduation.manage` — new in M21,
slotted into the existing role tiers:

| Role | `promotion.view` | `promotion.manage` | `graduation.manage` |
|---|---|---|---|
| School Admin | ✅ | ✅ | ✅ |
| Principal | ✅ | ✅ | ✅ |
| Bursar | – | – | – |
| Teacher | ✅ | – | – |
| Staff | ✅ | – | – |
| Parent / Student | – | – | – |

Parents/students never see a `promotion.*` permission at all — they reach
their own (or their child's) *current* placement/status through the
existing Parent/Student Portal, unchanged. No role-name checks anywhere;
every route is gated through the `Permission` enum via the Gate, exactly
like every other module.

## 6. Module & routes

`Module::Promotion` (`promotion`) — on by default, depends on
`Module::Students` only (not Fees/CBT/Learning Materials). Every
`/promotion/*` route sits behind `module:promotion` **and** its permission —
module-on grants nothing by itself.

Promotion is a two-step, bookmarkable **GET** flow rather than a stateful
wizard (deliberately, to avoid building a workflow engine): `GET
/promotion/create` (pick the source class) → `GET /promotion/roster?…`
(shows the eligible roster + target picker + confirm form) → `POST
/promotion` (executes, per §4). Graduation mirrors the same source-picker →
roster → confirm shape at `/promotion/graduation/create` →
`POST /promotion/graduation`, plus `POST
/promotion/graduation/{student}/reactivate` for the explicit reversal.

Every academic id (source/target session, period, level, arm) and every
student id in a promotion/graduation payload is checked with
`Rule::exists(...)->where('school_id', <tenant>)` — never trusted as-is.
`{batch}` and `{student}` route parameters are resolved by tenant-scoped
`findOrFail`, never route-model-bound, so another school's id 404s.

## 7. Reactivation

Graduation is a lifecycle transition, not a deletion —
`GraduationService::reactivate()` is the explicit, authorised reversal: sets
the student back to `Active` and nulls the four `graduated_*` columns. It
does **not** recreate an enrollment (a graduated student typically has none
that still makes sense to reopen) — an admin adds a fresh one normally
afterwards.

To stop a graduated student re-entering the roster silently,
`App\Http\Requests\Student\EnrollmentRequest` (M9) gained one targeted
guard: creating a **new** enrollment for a student whose status is
`graduated` is rejected with "This student has graduated. Reactivate them
before adding a new enrollment." Reactivate first, then enroll — no new
system, just one check added to the existing request.

## 8. Deferred / not built here

- Automatic pass/fail promotion rules of any kind (spec explicitly forbids
  inventing one — promotion is always an authorised administrative
  decision).
- A promotion "approval" step distinct from running the batch — running the
  batch (gated `promotion.manage`) *is* the authorisation; a separate
  pending/approved state would be workflow ceremony the spec asked not to
  build.
- Bulk import of students, or of promotion batches from a spreadsheet.
- Undoing a completed promotion batch in bulk (an individual student can be
  corrected by promoting them again, or an admin can create a fresh
  enrollment directly — the historical record of the original promotion
  stays either way).
- Any notification (M18) fired on promotion/graduation — not wired up in
  this milestone.
