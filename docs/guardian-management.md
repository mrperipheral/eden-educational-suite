# Guardian / Parent Management

Status: **Milestone 10 — complete.** Tenant-scoped guardian / parent records and
the student ↔ guardian relationship. Built on the M3/M4/M7/M9 seams —
`TenantContext` + `BelongsToSchool`, `App\Enums\Permission`, `module:guardians` —
no new authorization or tenancy mechanism, no new packages, no Redis/queues.

M10 is **records + linkage only**: who a student's parents / guardians are and
how to reach them. It does **not** build the parent portal, portal credentials,
messaging / notifications, pickup authorisation, custody documents, or any
financial / medical / emergency data.

## 1. The pieces

| Concern | Model | Table | Belongs to |
|---------|-------|-------|------------|
| Guardian record | `App\Models\Guardian` | `guardians` | school |
| Student ↔ guardian link | `App\Models\GuardianStudent` | `guardian_student` | school **+** student **+** guardian |

Enum: `App\Enums\GuardianRelationship`.
Controllers: `App\Http\Controllers\Guardian\{Guardian,GuardianLink}Controller`.
Form Requests: `App\Http\Requests\Guardian\*`. Views: `resources/views/guardians/*`
(+ a "Parents / guardians" card on the student profile).

```
School
 ├── Guardian  (contact record: name, phone, alt phone, email, address, notes)
 └── Student
       └── GuardianStudent (link)   relationship: mother | father | grandparent
             ├── Guardian            aunt_uncle | sibling | legal_guardian | other
             └── is_primary          at most one primary guardian per student
```

A student may have several guardians; a guardian may be linked to several
students **within the same school**. A `(student_id, guardian_id)` pair is unique
— no duplicate links.

## 2. Routes

All under `Route::middleware(['tenant', 'module:guardians'])->prefix('guardians')`.
Every route carries **both** gates:

- `module:guardians` — is the feature on for this school? Off ⇒ 404.
  (`guardians` **depends on** `students`, which depends on `academics`.)
- `->can('guardian.view')` (reads) / `->can('guardian.manage')` (writes).

| Method | URI | Name | Permission |
|--------|-----|------|------------|
| GET | `/guardians` (`?q=` search) | `guardians.index` | `guardian.view` |
| GET/POST | `/guardians/create` · `/guardians` | `guardians.create` / `.store` | `guardian.manage` |
| GET | `/guardians/{guardian}` | `guardians.show` | `guardian.view` |
| GET/PATCH | `/guardians/{guardian}[/edit]` | `guardians.edit` / `.update` | `guardian.manage` |
| GET | `/guardians/students/{student}/link` | `guardians.links.create` | `guardian.manage` |
| POST | `/guardians/links` | `guardians.links.store` | `guardian.manage` |
| PATCH | `/guardians/links/{link}` | `guardians.links.update` | `guardian.manage` |
| DELETE | `/guardians/links/{link}` | `guardians.links.destroy` | `guardian.manage` |

Tenant-owned ids (`{guardian}`, `{link}`, `{student}`) are **not**
route-model-bound — they are resolved in the controller with
`Model::query()->findOrFail($id)` (tenant-scoped, runs after the `tenant`
middleware), so another school's id 404s. `GuardianLinkRequest` also
`abort(404)`s in `prepareForValidation` if the route's `{link}` is not a record
of the active school — so a cross-school route id never reaches the rules.

## 3. Guardian record

`guardians` — school-owned (`BelongsToSchool`). `school_id` is stamped from the
tenant context and is **never** in `$fillable` / read from input.

| Column | Notes |
|--------|-------|
| `first_name`, `last_name` | required, ≤ 60 |
| `middle_name`, `preferred_name` | optional, ≤ 60. `preferred_name` is shown in lists when set. |
| `phone`, `alt_phone` | optional, ≤ 40; digits / spaces / `+ ( ) -` only |
| `email` | optional, `email:rfc`, ≤ 255 |
| `address_line1/2`, `city`, `state` | optional |
| `notes` | optional free text, ≤ 2000 (custody arrangements, pickup notes, …) |

**Minimal contact data by design.** No government ID / BVN / NIN, no financial,
medical, or emergency data. No portal credentials — signing a parent in is the
Parent Portal's concern, a later milestone.

**No global uniqueness.** A guardian has no natural school-owned identifier;
`email` / `phone` are optional and may legitimately be shared within a household.
Duplicates are prevented at the *link* level, not on guardian identity. No
guardian is hard-deleted by the UI; removing a **link** leaves both records.

Indexes: `index(school_id, last_name, first_name)` (list + name search),
`index(school_id, phone)`, `index(school_id, email)` (search box).

## 4. The student ↔ guardian link

`guardian_student` — school-owned (`BelongsToSchool`) **and** it carries both
`student_id` and `guardian_id`, so a query is tenant-safe without a parent in the
join. `student_id` is set from the parent relation on create and never changes.

| Column | Notes |
|--------|-------|
| `relationship` | **required** — `App\Enums\GuardianRelationship` |
| `is_primary` | the student's main contact — **at most one per student** |

- **One primary per student** — `GuardianStudent::makePrimary()` (a transaction
  that clears any other primary link the student has). This is the M8
  `makeCurrent()` / M9 `makeActive()` pattern. The flag is scoped **per student**:
  the same guardian can be the primary contact for two of their children.
- **No duplicate links** — `unique(student_id, guardian_id)`, plus a
  `Rule::unique` on the create request for a friendly message.
- Links are created from the **student's profile** ("Add guardian" → pick an
  existing guardian, or create a new one first). They can be edited (relationship
  / primary) or removed from either the student or the guardian profile.
- **Cross-school linking fails safely.** `student_id` / `guardian_id` in the
  payload are validated with `Rule::exists(...)->where('school_id', <tenant>)`, so
  a cross-school id comes back as a plain "invalid" — never a 500, never an
  oracle for another school's records.

Indexes: `index(school_id, student_id, is_primary)` ("guardians for this student"
+ the primary lookup), `index(school_id, guardian_id)` ("students for this
guardian"). FKs cascade on delete, so a future student / guardian data-erasure
tool cleans its links automatically.

## 5. Relationships (only what M10 needs)

- `School` → `guardians` (`HasMany`)
- `Guardian` ↔ `students` (`BelongsToMany` through `guardian_student`, with
  `relationship` + `is_primary` on the pivot), `guardian->studentLinks` (`HasMany`)
- `Student` ↔ `guardians` (`BelongsToMany`), `student->guardianLinks` (`HasMany`)

No teacher, attendance, fee, portal, messaging or notification relationships are
created. `Guardian` has no `user` / portal-account relation — that is deferred.

## 6. Module activation

`/guardians/*` sits behind `module:guardians`. `Module::Guardians->isAvailable()`
is now **`true`** and its default is **on**.

`Module::Guardians` **depends on `Module::Students`** (a guardian record is only
meaningful once there are students to link). A school cannot enable Guardians
unless Students is on, and cannot disable Students while Guardians is on (M7
single-level dependency validation). All of `academics` → `students` →
`guardians` are default-on, so the catalogue stays consistent.

Module activation and authorization stay independent: the Guardians module being
on does not give a Parent `guardian.view` (tested).

## 7. Authorization

Reuses the M4 `guardian.view` / `guardian.manage` permissions — previously
declared but dormant, now enforced.

| Role | Guardian access |
|------|-----------------|
| School Admin, Principal | manage (view + create + edit + link/unlink) |
| Bursar, Teacher, Staff | view only (list, profile, guardians on a student) |
| Parent, Student, role-less | none → 403 |

`Permission::GuardianView` was added to the **Staff** bundle so that all three
read-only roles that already hold `student.view` can also see the guardians shown
on a student's profile. Every write Form Request re-checks `guardian.manage` in
`authorize()`. There is no self-service / escalation path — a Parent role holds
only `portal.parent` and gets 403 on every `/guardians/*` route.

## 8. Performance

- List: `paginate(25)`, name-ordered on an index, `LIKE` search across
  `first_name` / `last_name` / `preferred_name` / `email` / `phone` / `alt_phone`,
  `withCount('students')` for the link count — a small constant number of queries
  regardless of page size.
- Student profile eager-loads `guardianLinks.guardian`; guardian profile
  eager-loads `studentLinks.student` (+ the student's current placement). Both
  have an explicit N+1 regression test (`< 15` queries for 6 links).
- No full-population selects; no Redis, no queues, no new packages.

## 9. Decisions

| Decision | Why |
|----------|-----|
| A dedicated `GuardianStudent` link model (not a bare pivot) | the link carries behaviour (`relationship`, `is_primary`, `makePrimary()`) and must be `BelongsToSchool` and tenant-safe in its own right |
| The link carries `school_id` **and** both FKs | a query is tenant-safe without joining through a parent — same rule as `Enrollment` (M9) and `LevelArm` (M8) |
| `is_primary` enforced in `makePrimary()`, not a partial unique index | portable across MySQL / SQLite; the "one at a time" invariant is the M8/M9 pattern, and it is per-student not global |
| Links created from the **student** workflow | matches how a school thinks ("this child's parents"); the guardian side is view + edit/remove, so no unbounded student picker is needed |
| No uniqueness on guardian `email` / `phone` | a guardian has no natural identifier; a household may share contact details; duplicates are prevented on the *link*, not the person |
| Minimal contact data (name / phones / email / address / notes) | "do not collect unnecessary sensitive information"; identity, financial, medical, emergency and portal-credential data are later, consent-gated concerns |
| No hard delete for guardians; unlink ≠ delete | referential integrity for the Parent Portal and any future module; removing a relationship is the reversible action |
| `Module::Guardians` depends on `Module::Students` | a guardian record with nothing to link to is meaningless — a minimal, correct extension of the M7 catalogue |

## 10. Deferred

- **Parent Portal** (`module:parent-portal`) — guardian sign-in, a portal
  account (`Guardian`↔`User` linkage), per-child dashboards. M10 stores **no**
  credentials.
- Messaging / notifications to guardians (their own module).
- Pickup / collection authorisation, custody & legal documents, emergency
  contacts distinct from guardians, guardian photo / ID.
- Bulk guardian import / CSV; merging duplicate guardian records.
- Occupation / employer / financial-responsibility fields (belong to Fees).
- Audit trail of link changes and guardian record edits (part of platform-wide
  audit logging).
- A typeahead student-search endpoint for linking from the guardian side at very
  large schools (the current flow links from the student profile, so it is not
  needed yet).
