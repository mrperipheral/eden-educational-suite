# UI / UX Guidelines

A small, consistent foundation. It will grow into a proper design system in
later milestones — keep it simple until then.

## Stack

Blade + Tailwind CSS v4 + Alpine.js. Tailwind is configured in
`resources/css/app.css` with `@theme` (there is no `tailwind.config.js`). Alpine
is started in `resources/js/app.js`.

## Layouts

| Component | Use for |
|-----------|---------|
| `<x-layouts.app>` | low-level app shell (sidebar + header + main) — rarely used directly |
| `<x-layouts.authenticated>` | **default for signed-in pages** — composes `layouts.app` with the primary nav and the user menu; `:title` renders a page header |
| `<x-layouts.guest>` | login, register, password reset, verification, password confirm |

`<x-layouts.app>` slots: `$navigation` (sidebar links), `$header` (page title area
in the top bar), `$headerActions` (top-right controls). The default `$slot` is the
main content. `<x-layouts.authenticated>` fills `navigation` and `headerActions`
for you; pass `:title` and an optional `actions` slot.

### Responsive behaviour
- Breakpoint for the desktop layout is Tailwind `lg` (1024px).
- Below `lg`: sidebar is an off-canvas drawer (`x-data="{ sidebarOpen }"`), opened
  from the header hamburger, closed by the backdrop or `Escape`.
- Content max width is governed by the page, not the shell; use `<x-card>` and
  grid utilities.

## Component kit (`resources/views/components/`)

| Component | Notes |
|-----------|-------|
| `x-button` | `variant`: primary / secondary / danger / ghost · `size`: sm / md / lg · renders `<a>` when `href` is set |
| `x-input` | label + hint + automatic validation error from `$errors` (`name` required) |
| `x-alert` | `variant`: info / success / warning / danger · optional `title` · `dismissible` (Alpine) |
| `x-badge` | `variant`: gray / brand / success / warning / danger |
| `x-card` | optional `title`, `actions` slot, `footer` slot, `padding` toggle |
| `x-page-header` | page `title` + `description` + `actions` slot |
| `x-empty-state` | `title` + `description` + `actions` slot — the standard "no data" block |
| `x-spinner` | `size`: sm / md / lg · accessible `label` · optional inline text |
| `x-modal` | generic Alpine dialog, opened via `$dispatch('open-modal', 'name')` |
| `x-confirm` | destructive-action guard: trigger button → confirm dialog → posts a spoofed-method form |
| `x-checkbox` | labelled checkbox, keeps `old()` state (used for "remember me") |
| `x-dropdown` | Alpine menu (`trigger` slot + menu items); closes on outside-click / Escape. Used for the header user menu |
| `x-auth-heading` | title + optional description block at the top of an auth card |

### Authentication pages

All under `resources/views/auth/` using `<x-layouts.guest>`: `login`, `register`,
`forgot-password`, `reset-password`, `verify-email`, `confirm-password`. Each is a
single centred card, one clear primary action, inline server-side validation via
`x-input`, `autocomplete` hints (`username` / `current-password` / `new-password`),
`autofocus` on the first field, and a visible focus ring on every control. No
illustrations, no animation beyond the drawer/menu transitions, minimal JS.

Account settings (`resources/views/settings/profile.blade.php`) uses
`<x-layouts.authenticated>` with three stacked `<x-card>` sections (profile,
password, delete) — restrained card use, one concern per card.

School settings (`resources/views/settings/school/`) is split into pages —
Profile, Branding, Regional, Modules — sharing a `_nav.blade.php` sub-nav
partial (with Academic sessions). Rather than one long form, each page holds a
focused form with its own validation and success state, and each renders a
read-only fallback for roles that hold `school.settings.view` but not `.update`.
The branding page uploads a logo (`enctype="multipart/form-data"`), previews it
via the gated serve route, and removes it through `x-confirm`. See
`docs/school-settings.md`.

The **Modules** page (`settings/school/modules`, `docs/module-activation.md`)
lists feature modules grouped by area. Each row: name, a status badge
(**Available** green / **Planned** amber), an **Enabled/Disabled** badge, the
dependency list, and a one-button `PATCH` form to flip it. Not-yet-built modules
are badged **Planned** and the card intro states plainly that the toggle is a
saved preference — no control implies a module is already implemented (no
"open"/"configure" links). View-only roles see the list with no buttons.

The **Academic** area (`resources/views/academic/`, `docs/academic-foundation.md`,
gated `academics.*` + `module:academics`) is a top-level nav item with its own
`_nav.blade.php` sub-nav — **Sessions & terms · Levels & arms · Subjects**. Each
entity has an index page with an inline "add" card (shown only to
`academics.manage`) and a list below; children (periods, arms, offered subjects)
are managed on their parent's `show` page; every entity has a dedicated `edit`
page. Lists use `<x-empty-state>`, pagination, `is_current` / `Inactive` badges,
and inline validation. Read-only roles (`academics.view`) see the lists and
detail pages without any create / edit / toggle controls.

The **Students** area (`resources/views/students/`, `docs/student-management.md`,
gated `student.*` + `module:students`) is a top-level nav item. **List** —
search (name / admission number) + status-filter chips + pagination; a shared
`_form.blade.php` drives **create** and **edit** (dedicated pages — the record
has many fields). The **profile** page shows identity, contact, a focused
lifecycle-status control, and the enrollment history with an "Add enrollment"
action. The enrollment form (`enrollments/_form.blade.php`) is an Alpine
cascade — picking a session filters the term select, picking a level filters
the arm select. Read-only roles (`student.view`) see lists and profiles with no
create / edit / status controls.

The **Guardians** area (`resources/views/guardians/`,
`docs/guardian-management.md`, gated `guardian.*` + `module:guardians`) is a
top-level nav item. **List** — name / phone / email search + pagination + a
linked-student count; a shared `_form.blade.php` drives **create** and **edit**.
The **profile** page shows contact details and the linked students, each with an
inline (Alpine) relationship-edit form and an `x-confirm` unlink. The student ↔
guardian link is added from the **student's** profile ("Add guardian" → a page
with a guardian `<select>` + relationship + primary-contact checkbox, plus a
link to create a new guardian). The student profile carries a "Parents /
guardians" card mirroring the same inline edit / unlink. Read-only roles
(`guardian.view`) see lists and profiles with no create / edit / link controls.

The **Teachers** area (`resources/views/teachers/`,
`docs/teacher-management.md`, gated `staff.*` + `module:staff`) is a top-level
nav item. **List** — name / employee-number / email search + status-filter chips
+ pagination + an active-assignment count; a shared `_form.blade.php` drives
**create** and **edit**. The **profile** page shows identity / contact, an
employment-status control, an application-account link control (a `<select>` of
school members — M11 never creates accounts), and the teaching assignments split
into **Current** and **Past**. The assignment form
(`teachers/assignments/_form.blade.php`) is an Alpine cascade — session filters
the term select, level filters the arm select, plus an independent subject
select; every id and relationship is re-checked server-side. An assignment is
removed via `x-confirm` (a hard delete for mistakes; the confirm text points at
"set status to Ended" to keep history). Read-only roles (`staff.view`) see lists
and profiles with no create / edit / status / link / assignment controls.

The **Timetable** area (`resources/views/timetables/`,
`docs/timetable-management.md`, gated `timetable.*` + `module:timetable`) is a
top-level nav item. **List** — timetables filterable by session / status,
paginated, each badged **Draft** / **Published**. The **timetable page** is the
core view: a `<x-card>` "Weekly schedule" holding a **day × time-slot grid** on
desktop (`hidden md:block`, wrapped in `overflow-x-auto`) and a **stacked
day-by-day list** on mobile (`md:hidden`) — never a forced horizontal-overflow
grid on a phone. It carries a status/publish control block, a red conflict
banner listing clashing lesson pairs (which blocks publishing), and filters
(class → arm, teacher, weekday). The lesson form
(`timetables/entries/_form.blade.php`) is an Alpine level → arm cascade plus
subject / teacher / weekday / `type="time"` start+end / optional room; every rule
is re-checked server-side. A separate **teacher-view** page (`_day-list.blade.php`
in `mode="teacher"`) lists one teacher's lessons across all timetables, grouped
by day. Read-only roles (`timetable.view`) see the grid and teacher view with no
add / edit / publish controls.

The **Attendance** area (`resources/views/attendance/`,
`docs/attendance-management.md`, gated `attendance.*` + `module:attendance`) is a
top-level nav item and does **not** depend on the Timetable module. **List** —
registers filterable by date / session / level / arm / status, paginated, each
badged **Draft** / **Submitted** and showing `present / total`. **Create** — an
Alpine session→period and level→arm cascade plus a `type="date"` (`:max` today)
and optional notes; an empty state when the school has no sessions/levels yet.
The **taking screen** is the core view, built for daily phone use: a scrollable
student list (name · admission number · status badge), four single-tap status
buttons per student with `aria-pressed`, an optional per-student note, an
Alpine-driven **"Mark all present" / "Clear all"** bar, and a **sticky footer**
with **Save draft** and **Save & submit** (the latter disabled by Alpine while
any student is unmarked). The **register detail** shows summary count chips
(`attendance/_summary.blade.php`), the submitted-by/at indicator when locked, and
— for `attendance.manage` holders — a **"Reopen for correction"** control; a
locked register renders a read-only roster. Read-only roles (`attendance.view`)
see the list and detail with no create / record / submit controls.

The **Assessments** area (`resources/views/assessments/` +
`resources/views/assignments/`, `docs/assessment-management.md`, gated
`assessment.*` + `module:assessments`) is a top-level nav item. **List** —
assessments filterable by session / level / class / subject / category / status,
paginated, each badged **Draft** / **Published** / **Locked** and showing
`entered / total scored`. **Create** — an Alpine session→term and
level→(arm, subject) cascade plus category / title / max score / instructions;
the academic context is fixed after creation, so **Edit** is a reduced form.
The **score sheet** is the core view, built for a class of 30–100+ on a phone:
a scrollable list (name · admission number), a right-aligned numeric input with
`/ max` and live client-side range validation, an optional per-student comment,
and a **sticky footer** with **Save scores** (disabled while any score is out of
range). The **assessment detail** shows summary chips
(`assessments/_summary.blade.php`) and the lifecycle controls (publish / return
to draft / lock; unlock for `assessment.manage`). **Categories** are managed
inline at `/assessments/categories`. **Assignments** mirror the shape —
list / create / detail / a **completion sheet** with a status select + date +
remark per student and a "set all" bar — plus a draft → published → closed
lifecycle. Read-only roles (`assessment.view`) see lists and detail only.

The **Results** area (`resources/views/results/`, `docs/results-report-cards.md`,
gated `result.*` + `module:results`) is a top-level nav item, split into
**Result runs / Grading schemes / Weighting schemes / Report card
configuration** — no giant single-page form. **Run list** — filterable by
session / level / class / status, paginated, each badged by lifecycle status.
**Run detail** is the core view: a header with the run's lifecycle-action
buttons (compile / review / approve / publish / lock), a compilation-blocked
state that lists every missing-score issue by name
(`$errors->get('compilation')`), and a per-student table whose rows expand
(Alpine `x-data="{open:false}"`) into a subject breakdown with an inline
adjustment disclosure (`<details>`) and comment forms gated by
`$canComment`/`$canManage`. **Grading/weighting scheme** screens use
Alpine-toggled inline edit-in-place rows for their grades/items, with a
running total-weight badge (success/warning) on the weighting scheme. **Report
card configuration** groups its 24 toggles into labelled sections (student
info / academic results / overall performance / attendance / comments /
signatures), a scope switcher (school-wide / session / session+term), and
signature upload/remove/preview widgets. The **report card** itself
(`results/report-card/show.blade.php`) is print-styled (`@media print` hides
everything but the card; a "Print" button calls `window.print()`) — the
**same** view serves the live preview and the final locked output. Read-only
roles (`result.view`) see runs, schemes and report cards with no create /
compile / lifecycle / adjust controls.

The **Parent Portal** (`resources/views/parent/`, `docs/parent-portal.md`,
gated `portal.parent` + `module:parent-portal`) gets its own, much simpler
nav — a Parent-role viewer sees **My Children** / **Profile** / **Account
settings** in the main layout instead of the admin/staff link set, which
would otherwise render almost empty for them (the layout branches on
`roleIn() === Role::Parent`, reusing `<x-layouts.authenticated>` — no
parallel layout component). **Dashboard** (`/parent`) is a grid of child
cards (name, admission number, class + arm, session, term, status badge)
linking into each child's profile. Every child-scoped page
(`results/report-cards/attendance/assignments/timetable`) shares one partial,
`resources/views/parent/_child-nav.blade.php`: a back-link to My Children, a
horizontally-scrollable tab bar (mobile-first — wraps to a compact strip
rather than overflowing the viewport), and, when the parent has more than one
child, a **"Switch child"** dropdown listing every authorized child by name.
Tabs are hidden (not merely disabled) for a section whose module is off for
the school. Empty states are specific and reassuring
(`x-empty-state`) — "No published results are available for this student
yet", "No published timetable is currently available", etc. — never a raw
"nothing found." The report card page is unchanged from M15 (print-styled,
same view, same "Print" button) except its back-link is now audience-aware —
a parent sees "← Report cards" instead of the staff-only run link. The
Profile page is read-only cards, no edit form — a link out to the shared
`/settings/profile` for login/account changes.

Flash messages (`session('success' | 'error' | 'status')`) are rendered
automatically by `<x-layouts.app>` as alerts.

## Patterns

- **Confirmation before destructive actions:** use `<x-confirm :action="..."
  method="DELETE" confirm="Delete">Delete</x-confirm>`. The dialog is a UX guard
  only — the route still enforces authorization.
- **Loading:** `x-spinner` for inline/async states; disable submit buttons on
  submit (`x-data`/`@submit`) in later work.
- **Empty states:** always use `x-empty-state` rather than a bare "No results"
  string, and give it an action where one exists.
- **Forms:** every field via `x-input` (or a sibling component); show server-side
  validation errors inline; never rely on client validation alone.

## Visual language

- Neutral gray surfaces (`bg-gray-50` page, `bg-white` cards,
  `border-gray-200`).
- Brand colour via the `brand-*` scale defined in `app.css` (`@theme`).
- Font: Instrument Sans (loaded through the Vite fonts plugin).
- Respect `prefers-reduced-motion` (handled globally in `app.css`).
- Accessibility: label every control, keep focus states visible
  (`focus-visible:ring`), use semantic elements, provide `aria-*` on custom
  widgets.

## Product identity (M29.5)

The platform's own name is **Eden Education Suite** (`config('app.name')`).
Inside a school's own portal, the school's identity dominates — the sidebar
header shows the school's name, logo (if uploaded) and "School Portal", with
a single small "Powered by Eden Education Suite" line underneath. Eden
Education Suite is never shown prominently on a page that already has an
active school context. It *is* shown prominently: on the pre-auth
`<x-layouts.guest>` pages (no school context exists yet — see
"School branding" below), on the platform home page (`resources/views/welcome.blade.php`),
and on Platform Admin screens with no active school (`/admin/*` before a
school is entered — the sidebar shows "Eden Education Suite" / "Platform
Administration" instead of a school name).

## School branding / theme (M29.5)

`SchoolSetting` (`docs/school-settings.md`) carries five branding fields:
`logo_path`, `cover_image_path` (both file uploads, guarded, served through
a gated private route — see `docs/school-settings.md` §4), `brand_color`,
`accent_color` (both `#rrggbb`, validated by regex, normalised lower-case)
and `motto` (plain text, max 160 chars). All are tenant-scoped like every
other `SchoolSetting` column — nothing here bypasses `BelongsToSchool` /
`SchoolScope`. Colour is applied narrowly and deliberately, never as a
site-wide re-theme: the sidebar's top accent bar and the cover-image overlay
use `brand_color` via a scoped inline `style` attribute on specific
elements, not a CSS variable override of the compiled `brand-*` Tailwind
scale (Tailwind v4's `@theme` tokens are compile-time, not safely
overridable per-request). `SchoolSetting::readableTextColor()` picks black
or white text by relative luminance so a custom colour never produces
unreadable text — use it (never assume a colour is "light" or "dark") if you
add another spot that paints text on top of a custom colour. This is a
bounded branding feature, not a website builder: there is no arbitrary
CSS/HTML input anywhere in it.

## Personalized greeting (M29.5)

`<x-greeting :context="...">` (`resources/views/components/greeting.blade.php`)
renders "Good morning/afternoon/evening, {first name} 👋" based on the
server clock (05:00–11:59 morning, 12:00–16:59 afternoon, 17:00–04:59
evening) plus an optional one-line contextual subtitle. Used at the top of
each dashboard (`dashboard.blade.php`, `parent/dashboard.blade.php`,
`student/dashboard.blade.php`, `fees/dashboard.blade.php`,
`platform/reports/index.blade.php`) — not in the shared layout itself, since
it's a page-level greeting, not chrome.

## Sidebar navigation — "See more" (M29.5)

The nav array for each audience (default staff/admin, Parent, Student —
still built in `authenticated.blade.php`, see below) is split after
authorization filtering: the first 7 items (6 for the Parent/Student
portals, which are already short) render directly, and everything after
that sits behind a "See more" / "See less" toggle
(`x-data="{ moreOpen: ... }"`, no new Alpine plugin — plain `x-show`/
`x-transition`, matching the existing mobile-drawer pattern). The toggle
starts open if the current page happens to be one of the collapsed items,
so a deep link never lands on an apparently-unhighlighted nav. Collapsing is
purely a `display` toggle — every authorized link is still in the rendered
HTML, so this never hides functionality from anything that inspects the
page (tests included) — it only affects what's visible without a click. Do
not add a second, independent nav-building code path for this: the split
happens once, after the existing per-audience `$navLinks` collections are
built and filtered by `allowed`, in `authenticated.blade.php`.

## Mobile CBT (M29.5)

`resources/views/student/cbt/take.blade.php` — the exam-taking screen — was
restyled for phone-first use (sticky timer/progress header under the app
bar, sticky Previous/Next/Submit footer with `env(safe-area-inset-bottom)`
padding, larger touch targets, a slim answered/total progress bar) without
touching a single line of `App\Services\Cbt\ExamAttemptService`,
`App\Models\ExamAttempt`, or `StudentExamAttemptController`. The
timer/progress/answer-selection Alpine `x-data` object is unchanged in
substance — same properties, same methods, same three server endpoints
(`student.cbt.answer`, `student.cbt.submit`). **If you touch this view
again: the client-side timer is cosmetic only** — server-side expiry
(`ExamAttempt::isExpired()` against a DB-stored `expires_at`) is the only
thing that ever actually ends an attempt; never let a UI change imply
otherwise, and never add correctness/marks data to the payload passed into
`x-data` (see `ResultReleaseTest::test_correct_answers_are_never_present_in_the_take_page_payload`).

## A constant, bounded query for branding

Because the sidebar now reads `$currentSchool->settings` on every
authenticated page (to show the logo/cover/colour), every page now costs
exactly one more query than before M29.5 — a flat, non-scaling cost (one
`SchoolSetting` row, never proportional to a collection). Two existing
N+1-regression tests had their fixed query-count ceiling raised by 1 to
absorb this (`TimetableStructureTest::test_the_teacher_view_does_not_n_plus_one`,
`AssessmentStructureTest::test_the_assessment_list_does_not_n_plus_one`) —
if you add a *new* page-level query that shows up in every request the same
way, the right response is the same: confirm it's flat (not proportional to
a collection), then adjust the specific ceiling(s) it pushes over, with a
comment saying why — never loosen a budget to "make it pass" without
identifying the actual cause first.

## Not yet defined

Dark mode, dense/table layouts, data-grid, charts, iconography system,
notification/toast system. The primary nav is permission- and
module-filtered (Dashboard · Members · Students · Guardians · Teachers ·
Timetable · Attendance · Assessments · Results · Academic · School settings ·
Schools · Account), role-specific in content (same filtering, different
audiences) and collapsible via "See more" (M29.5, above). Add here when
built.
