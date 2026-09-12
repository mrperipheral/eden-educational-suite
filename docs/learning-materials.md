# Learning Materials

Status: **Milestone 22 — complete.** A teacher or admin uploads a single
file for a subject + class; students in that class can view and download
it. Intentionally small: no drafts, no autosave, no versioning, no approval
workflow — the workflow is upload → save → student access.

## 1. Supported file types

| Type | Extensions | Status |
|---|---|---|
| Document | `pdf` | enabled |
| Image | `jpg`, `jpeg`, `png`, `webp` | enabled |
| Audio | `mp3`, `m4a`, `wav` | enabled |
| Video | `mp4`, `mov`, `avi`, `webm` | **declared, disabled** |

`App\Enums\LearningMaterialType` is the single source of truth for this
table. `type` on a material is **derived automatically** from the uploaded
file's extension — never chosen by the uploader.

**Video is future-ready, not a M22 feature.** It has real extensions/MIME
types defined in the enum (so nothing needs to change shape later) but
`LearningMaterialType::isEnabled()` excludes it everywhere that matters:

- the Form Request's `mimetypes:` whitelist (`LearningMaterialType::
  enabledMimeTypes()`) — a real video file is rejected here regardless of
  its extension, since Laravel's `mimetypes:` rule sniffs actual file
  content, not the filename;
- the create-form's file-type hint text;
- `LearningMaterialUploadService`'s own independent check
  (`LearningMaterialType::fromExtension($ext)?->isEnabled()`) — a second,
  server-side gate that does not depend on the Form Request having run at
  all, so a direct/tampered call still gets rejected
  (`UnsupportedMaterialTypeException`).

Enabling video later is flipping `LearningMaterialType::Video->isEnabled()`
to `true` — no migration, no model change, no new storage mechanism. No
streaming, transcoding, compression, thumbnails or video player is built in
this milestone.

## 2. Data model

```
LearningMaterial   (school-owned; one uploaded file for a subject + class)
```

There is deliberately **no separate history/draft table** — a material
exists the moment it's uploaded and is immediately visible to its class.
Deleting one is a hard delete of both the row and the stored file together
(`LearningMaterial::deleteWithFile()`) — nothing else in the schema
references a material, so there's no historical-integrity reason to keep a
soft-deleted husk around, unlike `FeePayment` or `Enrollment`.

`academic_period_id` and `level_arm_id` are **nullable** — a material can
target a whole session (not one term) and/or a whole level (every arm),
mirroring `promotion_batches`' nullability rather than `assessments`'
always-fully-scoped shape. `subject_id` is **required**: every material
belongs to exactly one subject label, matching the spec's "Select
Subject/Class" step.

`type` / `file_path` / `file_name` / `file_size` / `mime_type` / `extension`
/ `uploaded_by` are **not** mass-assignable — set only by
`App\Services\LearningMaterials\LearningMaterialUploadService`, derived
from the file itself, never from request input directly.

## 3. Storage

Private, local disk (`LearningMaterial::FILE_DISK = 'local'`, same disk M6
school logos and M15 report-card signatures use) — never a public URL.
Files are written to `learning-materials/<school_id>/<random-name>`, using
Laravel's own generated filename (never the client's original filename,
which is preserved only as the `file_name` column for display/download).

Upload is atomic: `LearningMaterialUploadService::upload()` stores the file
to disk **first**, then creates the `LearningMaterial` row inside a
`DB::transaction()`. If that transaction throws for any reason (a bad
foreign key, an unexpected DB error), the just-stored file is deleted
before the exception is rethrown — there is never an orphan file on disk
for a row that doesn't exist, and never a row pointing at a file that was
never written.

Every download — staff or student — is served through an authorization-
gated controller action (`Storage::disk('local')->download(...)`), never a
direct filesystem/public path.

## 4. Class/subject scoping

`App\Support\LearningMaterials\LearningMaterialAuthorizer` mirrors
`App\Support\Assessment\AssessmentAuthorizer` exactly:

- `material.manage` (School Admin, Principal) → any class/subject, and may
  delete any material;
- `material.upload` **without** `.manage` (Teacher) → only a
  `(level, subject)` they hold an **active** M11 `TeacherAssignment` for
  (that arm, or an arm-agnostic assignment), and may delete only material
  they uploaded themselves.

The create-form itself reflects this: a Teacher without `.manage` sees a
single "class & subject" picker built from their own active assignments
only — not a free level/arm/subject cascade — so they can't even attempt an
unauthorised combination client-side. The server re-checks via the
authorizer regardless (the Form Request's `authorize()` only checks the
coarse permission, not the class).

## 5. Student visibility

A student sees a material if it targets their **current** enrollment's
session + level, and either has no arm (visible to the whole level) or
matches their own arm exactly (`LearningMaterial::scopeForClass()`). A
material's `subject_id` is a **label**, not an extra access filter — every
student in a level already takes the same subject list
(`level_subject`), so materials aren't additionally hidden by subject.

`StudentLearningMaterialController` degrades cleanly when the module is off
or the student has no current enrollment (an empty state, like
`StudentAssignmentController`/`StudentTimetableController`) rather than
404ing the whole portal page. A download re-derives the same
session/level/arm scope from the student's own enrollment before serving
the file — a material id is never trusted alone (IDOR protection).

Parent Portal access is **out of scope** for M22 — the spec's workflow ends
at "Student Access." A parent does not see their child's learning
materials yet; see §7.

## 6. Permissions

`material.view`, `material.upload`, `material.manage` — new in M22:

| Role | `.view` | `.upload` | `.manage` |
|---|---|---|---|
| School Admin | ✅ | ✅ | ✅ |
| Principal | ✅ | ✅ | ✅ |
| Bursar | – | – | – |
| Teacher | ✅ | ✅ (scoped) | – |
| Staff | ✅ | – | – |
| Parent / Student | – | – | – |

Student access is through the existing `portal.student` permission
(reused, no new permission) — the same pattern every prior portal milestone
uses.

## 7. Module & routes

`Module::LearningMaterials` (`learning-materials`) — **off by default**
(a specialised opt-in, like Timetable/CBT), depends on `Module::Academics`
only. `/learning-materials/*` (staff) sits behind `module:learning-
materials` **and** its permission — module-on grants nothing by itself.
`/student/learning-materials/*` is **not** middleware-gated by the module
(unlike the Fees portal routes) — the controller checks it itself and
degrades to an empty state, matching Assignments/Timetable.

Every academic id (session, period, level, arm, subject) is checked with
`Rule::exists(...)->where('school_id', <tenant>)`; `subject_id` is
additionally cross-checked against `level_subject` for the chosen level.
`{material}` is resolved by tenant-scoped `findOrFail`, so another school's
id 404s on both the staff and the student side.

## 8. Deferred / not built here

- Parent Portal visibility of learning materials.
- Editing an uploaded material's title/description/file (delete + re-upload
  covers a correction today).
- Multiple files per material / attachments.
- Video upload, streaming, transcoding, thumbnails, a video player.
- Download/view analytics ("who has seen this").
- Notification (M18) hooks when a new material is uploaded.
- Bulk upload.
