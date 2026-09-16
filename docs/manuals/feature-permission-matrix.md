# Feature → Role Permission Matrix

This matrix is generated directly from the application's own authorization
code (`App\Enums\Permission`, `App\Enums\Role`) — not inferred from role
names. Every row is a real permission the Gate checks; every ✓ means that
role's fixed bundle (`Role::permissions()`) actually contains that
permission today. If the code changes, this table needs updating — it is
not automatically kept in sync.

**Two important rules that apply to every row below:**

1. **A permission only works if the module it belongs to is also switched
   on** for the school (see `docs/module-activation.md`). Module activation
   and permissions are independent — enabling a module never grants a
   permission, and holding a permission does nothing if the module is off.
2. **`School Admin` always has every permission** (the codebase treats it
   as "all permissions", not a hand-picked list) — it is omitted from
   individual rows below and shown once at the top for clarity.
3. A **Platform Admin** (`users.is_platform_admin`) is a separate, cross-
   school concept — not a per-school role. Inside a school they have
   entered, they hold every permission that school's `School Admin` would.
   Outside any school, on the `/admin/*` screens, they manage the schools
   themselves — see the "Platform administration" row.

Legend: **V** = View, **C** = Create, **E** = Edit, **D** = Delete,
**A** = Approve / Publish / Lock, **M** = Manage (broader operational
control, e.g. configuring categories/structures, reopening a locked
record, waiving a charge).

## People & access

| Feature | School Admin | Principal | Teacher | Bursar | Staff | Parent | Student |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Members (view list) | ✓ | V | – | – | – | – | – |
| Members (add / change role / remove) | ✓ | C·E·D | – | – | – | – | – |
| Students (view) | ✓ | V | V | V | V | – | – |
| Students (add / edit / status / enrollment) | ✓ | C·E·M | – | – | – | – | – |
| Guardians (view) | ✓ | V | V | V | V | – | – |
| Guardians (add / edit / link to student) | ✓ | C·E·M | – | – | – | – | – |
| Teachers / staff (view) | ✓ | V | V | V | V | – | – |
| Teachers / staff (add / edit / assign classes) | ✓ | C·E·M | – | – | – | – | – |

## Academics

| Feature | School Admin | Principal | Teacher | Bursar | Staff | Parent | Student |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Academic structure — view sessions/levels/arms/subjects | ✓ | V | V | – | V | – | – |
| Academic structure — create/edit | ✓ | C·E·M | – | – | – | – | – |
| Timetable (view) | ✓ | V | V | – | V | – | – |
| Timetable (create/edit/publish) | ✓ | C·E·A | – | – | – | – | – |
| Attendance (view) | ✓ | V | V | – | V | – | – |
| Attendance (record — own assigned classes only) | ✓ | C | C | – | – | – | – |
| Attendance (record for any class, reopen locked) | ✓ | M | – | – | – | – | – |
| Learning materials (view) | ✓ | V | V | – | V | – | – |
| Learning materials (upload — own assigned classes) | ✓ | C | C | – | – | – | – |
| Learning materials (manage/delete any) | ✓ | M | – | – | – | – | – |

## Assessment & results

| Feature | School Admin | Principal | Teacher | Bursar | Staff | Parent | Student |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Assessments (view) | ✓ | V | V | – | V | – | – |
| Assessments (create/score — own assigned classes) | ✓ | C | C | – | – | – | – |
| Assessments (categories, lock/unlock, any class) | ✓ | M | – | – | – | – | – |
| Results (view) | ✓ | V | V | – | V | – | – |
| Results (class-teacher comment — own assigned class) | ✓ | E | E | – | – | – | – |
| Results (compile/review) | ✓ | M | – | – | – | – | – |
| Results (approve/publish/lock) | ✓ | A | – | – | – | – | – |
| Results (adjustment after lock) | ✓ | A | – | – | – | – | – |
| Grading & weighting schemes, report-card config | ✓ | M | – | – | – | – | – |
| CBT (view examinations/attempts) | ✓ | V | V | – | V | – | – |
| CBT (author exams/questions — own assigned classes) | ✓ | C | C | – | – | – | – |
| CBT (manage any exam, schedule/close) | ✓ | M | – | – | – | – | – |
| CBT (sit an exam) | – | – | – | – | – | – | ✓ |
| Question Bank (view) — part of CBT permissions above | ✓ | V | V | – | V | – | – |
| Entry / Placement Assessment (view) | ✓ | V | V | – | V | – | – |
| Entry / Placement Assessment (record — own classes) | ✓ | C | C | – | – | – | – |
| Entry / Placement Assessment (manage any) | ✓ | M | – | – | – | – | – |
| Promotion (view) | ✓ | V | V | – | V | – | – |
| Promotion (run a promotion batch) | ✓ | M | – | – | – | – | – |
| Graduation (graduate / reactivate a student) | ✓ | M | – | – | – | – | – |

## Finance

| Feature | School Admin | Principal | Teacher | Bursar | Staff | Parent | Student |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Fees — overview/statements (view) | ✓ | – | – | V | – | – | – |
| Fees — record payment / raise charge | ✓ | – | – | C | – | – | – |
| Fees — discount / waive / void | ✓ | – | – | M | – | – | – |
| Fees — categories & fee structures | ✓ | – | – | M | – | – | – |
| Fees — reports (see "Reporting" below) | ✓ | V (report only) | – | V | – | – | – |
| Own child's / own fee statement | – | – | – | – | – | ✓ (own linked children) | ✓ (own record) |
| Pay online (Paystack) | – | – | – | – | – | ✓ (own linked children) | ✓ (own record) |

Principal holds `fees.view` + `fees.report` (oversight only — cannot
record a payment, raise a charge, or adjust a balance; only Bursar and
School Admin can).

## Communication

| Feature | School Admin | Principal | Teacher | Bursar | Staff | Parent | Student |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Communication Hub (view threads) | ✓ | V | V | V | – | – | – |
| Communication Hub (log a message) | ✓ | C | C | C | – | – | – |
| Communication Hub (resolve/escalate) | ✓ | M | C | – | – | – | – |
| Communication Hub (full manage) | ✓ | M | – | – | – | – | – |
| Announcements (view) | ✓ | V | V | V | V | ✓ (own audience) | ✓ (own audience) |
| Announcements (publish) | ✓ | M | – | – | – | – | – |
| Notification centre (own notifications) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

## Reporting & administration

| Feature | School Admin | Principal | Teacher | Bursar | Staff | Parent | Student |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Reports area (open at all) | ✓ | V·export | V | V·export | V | – | – |
| Academic / Attendance / CBT reports | ✓ | ✓ | ✓ (own classes) | – | ✓ | – | – |
| Fee reports | ✓ | ✓ (view only) | – | ✓ | – | – | – |
| Student / Staff / Promotion / Communication / Learning-material reports | ✓ | ✓ | partial¹ | – | partial¹ | – | – |
| Report exports (CSV) | ✓ | ✓ | – | ✓ | – | – | – |
| Audit Log (view/export) | ✓ | V | – | – | – | – | – |
| Platform Reports (`/admin/reports`) | Platform Admin only | – | – | – | – | – | – |
| School settings (view) | ✓ | V | – | V | – | – | – |
| School settings (edit, module activation) | ✓ | – | – | – | – | – | – |
| Platform administration (`/admin/schools`) | Platform Admin only | – | – | – | – | – | – |

¹ Teacher/Staff can open Student/Staff/Promotion/Learning-materials/
Communication reports only if they also hold that report's own domain
permission (`student.view`, `staff.view`, `promotion.view`,
`material.view`, `communication.view`) — Staff holds all of these
read-only; Teacher holds most of them too (see the People/Academics
tables above), which is why both can typically reach these reports.
Neither ever gets a Fee report, since neither holds `fees.report`.

## Portals

| Feature | Parent | Student |
|---|:-:|:-:|
| My Children / Dashboard | ✓ | ✓ |
| Own/child's results & report cards | ✓ (published only) | ✓ (published only) |
| Own/child's attendance | ✓ | ✓ |
| Own/child's assignments | ✓ (view only) | ✓ (view + see submission status) |
| Own/child's timetable | ✓ | ✓ |
| Own/child's learning materials | – (not built for Parent Portal) | ✓ |
| CBT exams | – | ✓ (`cbt.take` — sit an exam, see own released results only) |
| Own/child's fee statement + online payment | ✓ | ✓ |
| Announcements addressed to their audience | ✓ | ✓ |
| Notifications | ✓ | ✓ |
| Profile | ✓ | ✓ |

Neither Parent nor Student ever reaches a staff-facing screen — they hold
no permission from the tables above at all, only `portal.parent` /
`portal.student` (+ `cbt.take` for Student). A parent only ever sees their
own **linked** children (via the guardian↔student link a School Admin/
Principal creates) — never another family's child, and never a
school-wide list.
