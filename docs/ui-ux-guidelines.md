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

## Not yet defined

Dark mode, dense/table layouts, data-grid, charts, iconography system,
role-specific navigation (the current nav is a fixed two-item placeholder),
notification/toast system. Add here when built.
