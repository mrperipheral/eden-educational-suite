# Communication & Notification Foundation

Status: **Milestone 18 — complete.** The school's Communication Hub as the
system of record for school communication, plus a shared in-app
notification centre used by staff, the Parent Portal and the Student
Portal alike. Delivery channels are represented as a small, honest
abstraction — only in-app notifications are actually delivered; WhatsApp,
SMS and email are declared, with no provider code behind them.

## 1. Communication Hub — threads & messages

```
CommunicationThread          (school-owned, BelongsToSchool)
  ├─ optional student_id     → App\Models\Student
  ├─ optional guardian_id    → App\Models\Guardian
  ├─ created_by, assigned_to → App\Models\User
  ├─ category                App\Enums\CommunicationCategory
  └─ status                  App\Enums\CommunicationStatus (open → resolved | escalated, reopenable)
       └─ messages()         CommunicationMessage (school-owned; sender_id, body)
```

The Hub is **staff-facing in this milestone** — every participant (`created_by`,
`assigned_to`, a message's `sender_id`) is a `User`, never a guardian or
student directly. `communication.view` holders see a shared, school-wide
inbox (like every other staff list in the app) — recipient-only visibility
is a Notifications concern, not a Communication Hub one. `status`,
`created_by`, `resolved_at`/`escalated_at` are **not** mass-assignable;
transitions go through `CommunicationThread::resolve()` / `escalate()` /
`reopen()` only. Nothing here is ever hard-deleted.

Routes (`/communication/threads/*`, gated `module:notifications` +
`communication.view`/`.create`/`.manage`/`.resolve`/`.escalate`):

```
GET  /communication/threads                 communication.threads.index
GET  /communication/threads/create          communication.threads.create
POST /communication/threads                 communication.threads.store
GET  /communication/threads/{thread}        communication.threads.show
PATCH /communication/threads/{thread}       communication.threads.update       (communication.manage)
POST /communication/threads/{thread}/messages   communication.threads.messages.store
POST /communication/threads/{thread}/resolve    communication.threads.resolve
POST /communication/threads/{thread}/escalate   communication.threads.escalate
POST /communication/threads/{thread}/reopen     communication.threads.reopen
```

## 2. Announcements

```
Announcement   (school-owned, BelongsToSchool)
  ├─ status     App\Enums\AnnouncementStatus (draft → published)
  └─ audience   App\Enums\AnnouncementAudience (everyone / all_staff / teachers / parents / students)
```

`status` / `published_at` are **not** mass-assignable — only
`Announcement::publish()` / `unpublish()` set them, and `publish()` fires
`App\Events\AnnouncementPublished`. Visibility is a **query scope**, not
just a route gate: `Announcement::scopeVisibleToRole()` restricts a
non-manager to published announcements whose `audience` targets their own
`App\Enums\Role` — so even a direct id in the URL 404s for the wrong
audience, not just a menu link that happens to be hidden.

`AnnouncementController@index` / `@show` are mounted at **three route
names** — `announcements.*` (staff), `parent.announcements.*`,
`student.announcements.*` — one controller, no duplication, the same
shared-renderer pattern M16/M17 used for report cards. `create` / `store` /
`edit` / `update` / `publish` / `unpublish` are staff-only
(`announcement.manage`).

Audience targeting is coarse and role-shaped on purpose: the existing
architecture has no generic "school group" beyond the academic structure
(levels/arms), so level/arm-scoped targeting ("Parents of Primary 1") is a
deliberate, documented limitation, not built here.

## 3. In-app notifications

```
Notification            (school-owned, BelongsToSchool, table `user_notifications`)
  ├─ user_id             the recipient
  ├─ type                App\Enums\NotificationType
  ├─ channel             App\Enums\NotificationChannel (always `in_app` in this milestone)
  ├─ title, message, url
  ├─ data                small extensible JSON payload
  └─ read_at
```

Deliberately **its own table**, not Laravel's conventional polymorphic
`notifications` table: the framework default has no `school_id` and would
bypass `SchoolScope`, and its shape (`notifiable_type`/`notifiable_id`)
doesn't fit a single, always-a-`User` recipient. Rows are only ever created
by `App\Services\Notifications\NotificationDispatcher` — never from request
input.

The notification centre (`NotificationController@index`/`read`/`readAll`)
needs **no extra permission** — every query is scoped to
`auth()->user()` and the active tenant, so a member only ever sees their
own notifications, the same way `/settings/profile` needs no extra gate.
It is mounted at three route names — `notifications.*`, `parent.
notifications.*`, `student.notifications.*` — sharing one controller and
one view, reused verbatim by staff and both portals:

```
GET  /notifications                index    (unread count, paginated recent list)
POST /notifications/{n}/read       read     (marks read, redirects to its url)
POST /notifications/read-all       readAll
```

## 4. Event-driven notification foundation

`App\Services\Notifications\NotificationDispatcher` is the single seam any
module uses to raise a notification without knowing about delivery
channels:

```php
$dispatcher->sendToUser($user, $type, $title, $message, $url, $data);
$dispatcher->sendToUsers($users, $type, $title, $message, $url, $data);   // one bulk insert
$dispatcher->usersWithPermission($permission);                            // e.g. escalation handlers
$dispatcher->notifyAnnouncementAudience($announcement);
```

Three domain events, dispatched synchronously (no queue introduced) and
**auto-discovered** by Laravel's event discovery (listeners live under
`App\Listeners\Notifications`, no manual `Event::listen()` — that would
double-register them):

| Event | Fired from | Notifies |
|---|---|---|
| `AnnouncementPublished` | `Announcement::publish()` | every member whose role the announcement's audience targets |
| `CommunicationMessageAdded` | a thread's store/reply controllers | the thread's `assigned_to`, unless they sent the message themselves |
| `CommunicationThreadEscalated` | `CommunicationThread::escalate()` | every member holding `communication.manage` |

Per the spec, **not every future notification is built now** — only the
events M18's own features raise. Assignment/result/attendance events are
left for whichever future milestone owns that workflow to wire up, using
this same dispatcher.

## 5. Channel abstraction

`App\Enums\NotificationChannel`: `InApp` (implemented), `Whatsapp`, `Sms`,
`Email` (declared, `isImplemented() === false`, no provider class of any
kind — wiring a real provider behind one is future work).

## 6. Permissions

New, added to `App\Enums\Permission` and slotted into the M4 role bundles:

| Permission | SchoolAdmin | Principal | Bursar | Teacher | Staff | Parent/Student |
|---|---|---|---|---|---|---|
| `communication.view` / `.create` | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| `communication.resolve` / `.escalate` | ✅ | ✅ | — | ✅ | — | — |
| `communication.manage` | ✅ | ✅ | — | — | — | — |
| `announcement.view` | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| `announcement.manage` | ✅ | ✅ | — | — | — | — |

Parent/Student reach announcements and notifications through their
existing `portal.parent` / `portal.student` permission (declared since M4,
no new permission) — the same pattern M16/M17 used.

## 7. Tenant isolation

Every new model is `BelongsToSchool`; every route id (`{thread}`,
`{announcement}`, `{notification}`) is resolved by tenant-scoped
`findOrFail`, so another school's id 404s. `student_id` / `guardian_id` /
`assigned_to` on a new thread are validated to belong to the active school
(`Rule::exists(...)->where('school_id', $schoolId)`) — never trusted as-is.
A notification is additionally scoped to `auth()->user()->id`, so even a
same-school id belonging to a different member 404s.

## 8. Performance

Every new table leads its lookup indexes with `school_id`. Lists are
paginated. `NotificationDispatcher` bulk-inserts (one query fanning a
published announcement out to an entire school's audience, not one insert
per recipient). No Redis, no queue — dispatch is synchronous, matching the
rest of the app.

## 9. Deferred

WhatsApp / SMS / email provider integration; two-way portal messaging
(guardians/students can view announcements & their own notifications but
do not reply into a Communication Hub thread — it stays staff-facing);
level/arm-scoped ("selected school groups") announcement targeting; wiring
assignment/result/attendance events into the dispatcher; a full audit
trail of communication access; push notifications; message attachments.
