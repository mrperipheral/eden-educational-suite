# Salesperson / Demonstrator Guide

A click-by-click script for demonstrating the platform to a school owner
or principal, without needing a technical person in the room. Read
["Before you demo"](#before-you-demo) once, then use the **Master
Demonstration Sequence** as your script — each numbered section is one
demo "beat" with everything you need: where to click, what to say, the
business case, likely questions, and what to do if something's not
switched on or breaks.

Three ready-made routes through the sequence are given at the end:
[15-minute](#15-minute-quick-demo), [30-minute](#30-minute-standard-demo),
and [45-minute](#45-minute-detailed-demo) — each just tells you which
numbered sections to use and how long to spend on each.

## Before you demo

**Use a demo school with realistic data already in it** — empty screens
sell nothing. If your organization keeps a standing demo school seeded
with sample students/teachers/results/fees, log into that; don't build one
live in front of a prospect unless you're specifically demonstrating
onboarding speed (see §1 and §19).

**Two rules that apply to every section below, so we don't repeat them 19
times:**

- **"If the feature is unavailable"** almost always means one of two
  things: (a) that module is switched off for this school — mention that
  it's a one-click toggle in **School settings → Modules**, and either
  switch it on live (if you're the School Admin in this demo) or say
  "I'll show you that toggle in the settings section" and move on; or
  (b) you're logged in as a role that doesn't have that permission — log
  in as School Admin, which can see everything, for the main demo, and
  only switch roles deliberately when the point is *to* show role
  restriction (see §17).
- **"If a technical issue occurs"** — stay calm, don't improvise
  explanations for an error message. Say something like *"That's an odd
  one — let me note it down and our team will make sure it's not an issue
  for your school specifically."* Move on to the next section. Never try
  to debug live. Never blame the school's internet or device unless
  you've actually confirmed that's the cause.

**What to never promise, generally:** never promise a feature that isn't
in this guide exists ("can it also...?" → "let me check and get back to
you," never guess); never promise a specific go-live date until contracts/
onboarding logistics are actually agreed; never promise data migration
from their current system is automatic (there is no bulk import feature in
this version — say so plainly, see §1).

---

## Master Demonstration Sequence

### 1. Platform / Admin introduction

- **Start:** Sign-in page.
- **Click:** Sign in as a **Platform Admin** account (if your organization
  runs multiple schools on one platform instance) → lands on **Choose a
  school**, or go straight to `/admin/schools` if already signed in.
- **Enter:** Nothing yet — just show the **Schools** list.
- **Screen does:** Lists every school on the platform with its status
  (Active/Suspended).
- **Say while doing it:** *"Every school on this platform is completely
  separate — your data, your students, your fees. Nobody at another
  school can ever see yours, and we can prove that later if you'd like."*
- **Business problem:** Schools worry about a shared system meaning shared
  (or leaked) data.
- **Business benefit:** Hard tenant isolation — enforced at the database
  level, not just hidden in the interface.
- **Talking points:** *"Think of it like separate buildings on one street
  — same street, locked doors."*
- **Likely questions:** *"Can platform support see our data?"* — Yes,
  platform administrators can access any school for support purposes, but
  every school-level user only ever sees their own school.
- **Never promise:** That platform admin access is disabled entirely —
  it's needed for support; be honest about it.
- **If unavailable:** If you don't have platform-admin credentials handy,
  skip straight to §2 — this section is optional context, not core to the
  sale.

### 2. School setup

- **Start:** `/admin/schools/create` (as Platform Admin), or skip and use
  an already-created demo school.
- **Click:** **Create school** (if on the Schools list).
- **Enter:** **School name**, optionally a **Slug**, and optionally an
  **Initial School Admin** email (an existing account to make the first
  admin).
- **Screen does:** Creates the school with a sensible set of modules on by
  default (see §"Module activation" in the School Admin Manual);
  redirects to the school list.
- **Say while doing it:** *"This is literally how fast a new school gets
  set up — one form, and you have a working environment with the core
  features already on."*
- **Business problem:** Owners fear a long, expensive, consultant-driven
  setup process.
- **Business benefit:** A school exists and is usable in under a minute;
  everything else (classes, students, fees) is the school's own
  configuration, done at their own pace.
- **Talking points:** *"You're not locked into our timeline — you can be
  live today and fill in the details over your first week."* (Point to
  the [Quick-Start Guide](quick-start-guide.md) if they ask "then what?")
- **Likely questions:** *"Can we bring our existing student data in?"* —
  Be honest: there is no automated bulk-import feature in this version;
  students are added one at a time (still fast — a couple of minutes
  each). Don't promise otherwise.
- **Never promise:** Automatic data migration from a spreadsheet or
  another system.
- **If a technical issue occurs:** Have a pre-made demo school ready as a
  fallback so a failed live creation doesn't stall the demo.

### 3. School dashboard

- **Start:** Log in as the school's **School Admin** → lands on
  **Dashboard**.
- **Click:** Nothing required — just look at the page. Scroll to show the
  KPI cards.
- **Screen does:** Shows onboarding checklist (if incomplete), an
  administration panel (member counts, recent activity), and KPI cards for
  Students/Staff/Attendance/Results/Fees/CBT — each only shown if its
  module is on and the viewer has permission.
- **Say while doing it:** *"This is what a head teacher or administrator
  sees the moment they log in — the whole school's pulse, in one screen,
  no digging."*
- **Business problem:** Leadership doesn't have quick visibility into
  day-to-day school health.
- **Business benefit:** A live, permission-aware summary — nobody sees a
  number they shouldn't, and a switched-off feature simply doesn't clutter
  the screen.
- **Talking points:** *"Notice there's no fake zero for a feature you
  haven't turned on yet — if you don't use CBT, that card just isn't
  there, not showing a confusing '0'."*
- **Likely questions:** *"Can we customize what's on the dashboard?"* —
  Not in this version; it's a fixed, sensible set tied to your modules and
  permissions.
- **If unavailable:** If a KPI card the owner expects isn't showing,
  check that module is on (§ "Module activation" in the School Admin
  Manual) — this is itself a good teaching moment, not a failure.

### 4. Student management

- **Start:** Click **Students** in the left navigation.
- **Click:** **Add student**, or open an existing student from the demo
  data.
- **Enter (if adding live):** First/last name, admission number, class
  placement (via **Add enrollment** on their profile afterward).
- **Screen does:** Creates the record; shows a rich profile page (contact
  info, current class, linked guardians, application account link,
  lifecycle status, full enrollment history).
- **Say while doing it:** *"Every student has a full history — when they
  joined, every class they've ever been in, never overwritten. If a
  student changes class mid-year, you see both, not just the latest."*
- **Business problem:** Schools lose historical records when using
  spreadsheets or ad-hoc systems — no audit trail of a student's journey.
- **Business benefit:** Permanent, structured history per student; a
  parent portal and report cards both build directly off this same
  record — enter it once, it's used everywhere.
- **Talking points:** *"You'll never have to explain 'why does this
  student not show up in last term's records' — nothing is ever silently
  overwritten."*
- **Likely questions:** *"What if a student leaves and comes back?"* —
  Their status changes (Withdrawn), and a new enrollment reactivates them
  later; history is preserved either way.
- **Never promise:** A student photo/document upload feature — it doesn't
  exist in this version.
- **If unavailable:** Requires the **Student Management** module (on by
  default) — if off, switch it on in Modules settings.

### 5. Teacher management

- **Start:** Click **Teachers**.
- **Click:** **Add teacher**, or open an existing one → **Add assignment**.
- **Enter:** Name, employee number; then for the assignment: session,
  level, arm, subject.
- **Screen does:** Creates the teacher record; the **assignment** is what
  scopes that teacher (once they have a login with the Teacher role) to
  exactly that class + subject everywhere else in the system.
- **Say while doing it:** *"This one assignment step is what makes the
  whole system self-policing — a teacher physically cannot see or touch
  another teacher's class, because the system itself doesn't show it to
  them."*
- **Business problem:** Owners worry about staff overreach — a teacher
  seeing or editing another class's grades.
- **Business benefit:** Automatic, built-in scoping with zero manual
  policing needed.
- **Talking points:** *"You set this once per teacher, and it's enforced
  everywhere — attendance, grading, CBT, materials — automatically."*
- **Likely questions:** *"What if a teacher teaches five different
  classes?"* — Add five assignments; there's no limit.
- **If unavailable:** Requires **Teacher / Staff Management** (on by
  default).

### 6. Academic setup

- **Start:** Click **Academic** in the left navigation.
- **Click:** Walk through the **Sessions**, **Levels**, and **Subjects**
  tabs.
- **Enter:** (If live) a session name/dates, a level name/code, a subject
  name/code.
- **Screen does:** Builds the structural backbone every other feature
  (attendance, assessments, fees, timetable) is scoped to.
- **Say while doing it:** *"Nothing about your class structure is
  preset or assumed — you name your own classes, your own subjects, your
  own terms. If you call it 'JSS 1' or 'Grade 7,' that's entirely up to
  you."*
- **Business problem:** Rigid systems force a school to adapt its own
  naming/structure to the software.
- **Business benefit:** The structure adapts to the school, not the other
  way round.
- **Talking points:** *"This is a five-minute, one-time setup — and it's
  the foundation everything else stands on."*
- **Likely questions:** *"What if we restructure classes next year?"* —
  You just add a new session and rebuild levels/arms as needed; old data
  stays intact under the old session.
- **If unavailable:** Always available — this is core, not a toggleable
  module.

### 7. Attendance

- **Start:** Click **Attendance** → **Create register** (as a Teacher, or
  School Admin demonstrating on their behalf).
- **Click:** Pick a class and date → **Create register**. Then click
  through marking students, using **Mark all present** then adjusting
  exceptions.
- **Enter:** Attendance status per student (Present/Absent/Late/Excused).
- **Screen does:** Captures the class roster automatically, locks the
  register once submitted, and rolls the numbers up into the student's
  results/report card and the Attendance report.
- **Say while doing it:** *"Watch how fast this is — mark everyone
  present, then just fix the two or three exceptions. Thirty seconds for
  a class of forty."*
- **Business problem:** Paper attendance registers are slow, easy to lose,
  and impossible to report on later.
- **Business benefit:** Fast entry, permanent digital record, automatic
  reporting.
- **Talking points:** *"Once submitted, it's locked — nobody 'quietly
  edits' a past attendance record. If a genuine correction is needed, an
  administrator has to deliberately reopen it, and that's logged."*
- **Likely questions:** *"What if we forget to take attendance one day?"*
  — You can create a register for a past date and back-fill it; there's no
  penalty, just an accurate record.
- **If unavailable:** Requires the **Attendance** module (on by default).

### 8. Assessment / Assignments

- **Start:** Click **Assessments** → **New assessment** (or
  **Assignments** tab for homework).
- **Click:** Create an assessment → open its **Score sheet** → enter a
  few scores → **Save scores** → **Publish** → **Lock**.
- **Enter:** Category, maximum score, title, date; then per-student
  scores.
- **Screen does:** Locked, published assessment scores become the raw
  material Results compiles from later.
- **Say while doing it:** *"This score sheet is deliberately simple — one
  row per student, big number field, nothing fiddly. And notice it won't
  even let you save a score above the maximum."*
- **Business problem:** Manual grade books are error-prone and don't feed
  into report cards automatically.
- **Business benefit:** Direct line from a single score entry to the final
  report card — no re-typing anywhere.
- **Talking points:** *"Locking an assessment is a deliberate,
  irreversible-by-a-teacher step — it's what guarantees a report card's
  numbers can't quietly drift after the fact."*
- **Likely questions:** *"Can we weight tests differently from
  classwork?"* — Yes, that's configured once in the Weighting Scheme
  (shown in §9).
- **If unavailable:** Requires **Assessments** module (on by default).

### 9. Results / Report Cards

- **Start:** Click **Results** → open a result run with compiled data (or
  walk through **Compile → Mark reviewed → Approve → Publish → Lock** live
  if you have locked assessment data ready).
- **Click:** Open a student's **Report card**.
- **Screen does:** Compiles every locked assessment for the term into a
  final grade, class position, and a printable, branded report card.
- **Say while doing it:** *"This is the payoff of everything we just did
  — every score you entered becomes this, automatically, with your
  school's own logo and grading scale."*
- **Business problem:** Manually compiling report cards at term-end is
  slow, stressful, and error-prone.
- **Business benefit:** One click compiles an entire class; the multi-step
  approval process (review → approve → publish → lock) means nothing goes
  out to parents by accident.
- **Talking points:** *"Notice the deliberate checkpoints — nothing
  reaches a parent's screen until someone explicitly clicks Publish. And
  once locked, it's the permanent historical record — any later
  correction goes through a tracked adjustment, never a silent edit."*
- **Likely questions:** *"Can we design our own report card layout?"* —
  You control which of about two dozen fields show (comments, attendance
  summary, signatures, etc.) and your branding, but the overall layout
  itself is fixed, not a drag-and-drop designer.
- **Never promise:** A fully custom report-card designer, or bulk PDF
  export of a whole class at once (report cards are viewed/printed one
  student at a time).
- **If unavailable:** Requires **Results & Report Cards** module (on by
  default) and at least one locked, scored assessment to compile from —
  use the pre-seeded demo data if you don't have time to build this live.

### 10. Fees

- **Start:** Click **Fees**.
- **Click:** Open a student's statement → point out charges, discounts,
  payments, and the outstanding balance.
- **Enter:** (Optional live demo) **Record payment** — amount, date,
  reference, method.
- **Screen does:** Shows a complete, always-accurate financial picture per
  student, with every charge, discount, waiver, and payment kept as a
  permanent record.
- **Say while doing it:** *"Every number here is calculated by the system
  in real time — nobody's manually tallying a spreadsheet, and nothing
  can be quietly deleted. A correction always leaves a trace."*
- **Business problem:** Manual fee tracking leads to disputes ("I paid
  that already!") with no clean record to settle them.
- **Business benefit:** A permanent, auditable ledger per student that
  both the school and the parent can see (their own child only).
- **Talking points:** *"If a payment needs to be reversed, we don't
  delete it — we void it, and the void reason stays on record forever.
  That protects you in a dispute."*
- **Likely questions:** *"Can a parent see other students' fees?"* —
  Absolutely not; a parent only ever sees their own linked child's
  statement, enforced the same way as every other portal restriction.
- **If unavailable:** Requires the **Fees & Payments** module (on by
  default).

### 11. Online payment

- **Start:** School settings → **Payments** tab (to show the
  configuration), then a parent/student's **Fees** → **Pay online**
  button.
- **Click:** **Pay online** → shows the redirect to the secure payment
  provider (Paystack).
- **Screen does:** Validates the amount against the actual outstanding
  balance server-side (a parent cannot pay more than is actually owed via
  a trick), redirects to Paystack's own secure checkout, and records the
  verified payment automatically once confirmed.
- **Say while doing it:** *"We never see or store a card number — that
  all happens on Paystack's own secure page. Our part is just confirming
  the payment really went through before we mark it paid."*
- **Business problem:** Manual bank transfers are slow to reconcile and
  parents want a modern, convenient way to pay.
- **Business benefit:** Instant, verified, automatically-recorded
  payments — no reconciliation headache.
- **Talking points:** *"This isn't a shortcut around your normal
  payments — it's the exact same ledger, just with an extra, faster way
  to pay into it."*
- **Likely questions:** *"What are Paystack's fees?"* — That's between the
  school and Paystack directly; don't quote a number unless you actually
  know their current rate card.
- **Never promise:** A payment gateway other than Paystack, or that funds
  land in the school's account instantly (that's Paystack's own
  settlement timeline, not something this system controls).
- **If unavailable:** Needs Paystack keys configured in **School
  settings → Payments** first — if not configured, explain the setup
  step rather than trying to force a live payment.

### 12. Parent portal

- **Start:** Log out, log back in as a demo **Parent** account (or open a
  second browser/incognito window so you can show both sides at once).
- **Click:** Walk through **My Children** → a child's **Results**,
  **Report cards**, **Attendance**, **Fees** tabs.
- **Screen does:** A read-only window scoped to exactly their own linked
  children — nothing else.
- **Say while doing it:** *"This is the exact same data we just entered
  as staff — but the parent only ever sees their own child, and only what
  you've actually published."*
- **Business problem:** Parents constantly call/visit the school asking
  "how is my child doing?" — a burden on staff time.
- **Business benefit:** Parents self-serve for routine questions, freeing
  staff for things that actually need a human.
- **Talking points:** *"Notice there's no way for them to even guess at
  another family's data — it's not hidden by the interface, it's
  genuinely inaccessible."*
- **Likely questions:** *"Can parents message teachers through this?"* —
  Not in this version — the Communication Hub is staff-facing; parents see
  announcements and their own notifications, but a two-way parent-to-
  teacher chat isn't built yet. Be honest about this.
- **If unavailable:** Requires **Parent Portal** module on, the guardian
  linked to the student, and the guardian's account holding the Parent
  role — all three, shown in the School Admin Manual §11–§12.

### 13. Student portal

- **Start:** Log in as a demo **Student** account.
- **Click:** Walk through **Results**, **Report cards**, **Attendance**,
  **Assignments**, **Timetable**.
- **Screen does:** The same shape as the Parent Portal, but for the
  student's own record directly.
- **Say while doing it:** *"Same principle — a student sees exactly their
  own record, nothing about classmates."*
- **Business problem:** Students (especially older ones) want direct
  access to their own academic information, not filtered through a
  parent.
- **Business benefit:** Self-service for the student, same strict privacy
  guarantee.
- **Likely questions:** *"Can a student edit their own profile?"* — They
  can view their profile; substantive edits (contact info, status) stay
  with school staff.
- **If unavailable:** Requires **Student Portal** module on, the
  student's account linked, and the Student role assigned.

### 14. Learning materials

- **Start:** Click **Learning Materials** → **Upload**.
- **Click:** Choose a class/subject, title, and a file → **Upload &
  save**.
- **Screen does:** Stores the file securely; students in that exact class
  can view/download it — nobody else.
- **Say while doing it:** *"A teacher can share a worksheet, a scanned
  handout, or an audio file for a language class, in seconds."*
- **Business problem:** Sharing class materials via WhatsApp/email is
  disorganized and gets lost.
- **Business benefit:** One organized place, scoped correctly, permanent.
- **Likely questions:** *"Can we upload videos?"* — Not in this version —
  PDF, images, and audio only; be upfront about this limit.
- **Never promise:** Video support, or unlimited file size (there's a size
  cap).
- **If unavailable:** **Learning Materials** module is off by default —
  switch it on to demo, or explain the toggle.

### 15. CBT (Computer-Based Testing)

- **Start:** Click **CBT** → open an existing scheduled exam, or create
  one live.
- **Click:** Show question setup, then switch to a **Student** account and
  actually **sit the exam** — watch the countdown timer, answer a
  question (note it auto-saves instantly), and **Submit**.
- **Screen does:** Times the attempt server-side (not trusting the
  student's device clock), auto-saves every answer, auto-submits when
  time runs out, and marks it automatically.
- **Say while doing it:** *"Watch — I answer, and it's already saved,
  no separate 'save' click. And that timer isn't just cosmetic — even if
  their browser crashes, the server enforces the real time limit."*
- **Business problem:** Paper exams are slow to mark and easy to lose;
  schools want modern testing without buying separate exam software.
- **Business benefit:** Automatic marking, tamper-resistant timing, and
  configurable result release (instant or scheduled) — all built in.
- **Talking points:** *"You choose whether students see their score the
  moment they submit, or on a date you set — useful if you want to review
  results before releasing them."*
- **Likely questions:** *"Can it do essay questions?"* — Not in this
  version — multiple-choice and true/false only, auto-marked; no
  manually-graded essay questions and no exam proctoring/lockdown
  features. Be upfront.
- **Never promise:** Essay/manual-marking support, proctoring, or that a
  student's identity is verified beyond their login.
- **If unavailable:** **CBT** module is off by default — switch it on to
  demo.

### 16. Question Bank

- **Start:** Click **CBT** → **Questions**.
- **Click:** **Add question** → show writing one, then show reusing it by
  attaching it to a different exam.
- **Screen does:** A reusable pool of questions independent of any one
  exam — editing or archiving a bank question never changes an exam that
  already used it (each exam keeps its own frozen copy).
- **Say while doing it:** *"Build your question bank once, reuse it every
  term — and if you improve a question later, it never silently changes a
  test a student already sat."*
- **Business problem:** Rewriting exam questions from scratch every term
  wastes teacher time.
- **Business benefit:** Write once, reuse forever, with historical
  integrity guaranteed.
- **Likely questions:** *"Can multiple teachers share a question bank?"*
  — Yes, it's shared across your whole school (a Teacher can create/edit
  only within their own assigned subjects, but everyone can browse the
  bank).
- **If unavailable:** Part of the **CBT** module — same toggle as §15.

### 17. Communication

- **Start:** Click **Communication** (threads) and **Announcements**.
- **Click:** Log a new thread; publish a test announcement to a chosen
  audience.
- **Screen does:** A staff-facing log of parent interactions, plus
  school-wide announcements that reach the right audience (everyone, all
  staff, teachers, parents, or students).
- **Say while doing it:** *"This isn't a chat app — it's an
  accountability log. If a parent calls about a concern, log it here, and
  anyone on staff can see the history next time."*
- **Business problem:** Parent complaints/concerns get lost between staff
  members with no shared record.
- **Business benefit:** A shared, searchable log with a clear
  open/resolved/escalated status.
- **Likely questions:** *"Does this send SMS or WhatsApp messages?"* — Not
  in this version — it's in-app only (announcements + notifications); no
  SMS/WhatsApp/email delivery yet. Be honest.
- **Never promise:** SMS/WhatsApp/email delivery, or two-way parent
  messaging into a thread.
- **If unavailable:** Requires the **Notifications** module (on by
  default).

### 18. Reports / Analytics + Audit / Security

- **Start:** Click **Reports**.
- **Click:** Open **Academic performance** or **Fees & collections**,
  show the filters and **Export CSV**. Then click **Audit Log**.
- **Screen does:** Filterable, exportable reports across every enabled
  module; the Audit Log shows a permanent, tamper-proof trail of
  significant administrative actions.
- **Say while doing it:** *"Everything you've seen today rolls up into
  these reports — and separately, the Audit Log answers 'who did that,
  and when' for anything that matters, permanently. Nobody, including us,
  can edit or delete an audit entry."*
- **Business problem:** Owners want oversight without having to
  personally check every screen; they also want accountability when
  something goes wrong.
- **Business benefit:** Reporting gives visibility; the audit trail gives
  accountability — both without any extra manual work.
- **Likely questions:** *"Can I see this from my phone?"* — Yes, the whole
  system is responsive and works in a mobile browser (there's no separate
  native app).
- **If unavailable:** Reports need the **Reporting & Analytics** module
  on (default); Audit Log is always available to School Admin/Principal,
  not a toggle.

### 19. Closing / pilot discussion

- **Start:** Return to the Dashboard, or simply stop clicking.
- **Say:** Summarize the 2–3 things that matter most to *this* school
  based on what they reacted to during the demo — don't repeat the whole
  tour. Propose next steps: a pilot period, who on their side needs
  training (point to the
  [Training Handover Checklist](training-handover-checklist.md)), and a
  realistic first-week plan (point to the
  [Quick-Start Guide](quick-start-guide.md)).
- **Business benefit reminder:** Tenant isolation, permission-aware
  screens, permanent audit trails, and a workflow that mirrors how the
  school already operates — not a rigid system they must adapt to.
- **Likely questions:** *"What does it cost?"* / *"How long does
  onboarding take?"* — Use your organization's actual current pricing and
  onboarding timeline; this guide does not set those.
- **Never promise:** A specific price, contract term, or go-live date —
  that's a commercial conversation, not part of the product demo.
- **If a technical issue occurred earlier:** Reassure them it's logged and
  will be looked at — don't let one glitch overshadow the rest of the
  demo.

---

## 15-minute quick demo

Fast pace, dashboard-first, hits the emotional highlights: visibility,
security, and the parent experience.

| Time | Section |
|---|---|
| 0–2 min | §1–2 (skip if using a pre-built demo school) — quick context only |
| 2–5 min | §3 Dashboard |
| 5–8 min | §4 Students **or** §9 Results (pick whichever matches the owner's stated priority) |
| 8–11 min | §12 Parent Portal |
| 11–13 min | §10 Fees |
| 13–15 min | §19 Closing |

## 30-minute standard demo

Adds the daily-operations proof points and reporting.

| Time | Section |
|---|---|
| 0–3 min | §3 Dashboard |
| 3–6 min | §6 Academic setup |
| 6–9 min | §4 Students |
| 9–11 min | §5 Teachers |
| 11–15 min | §7 Attendance |
| 15–19 min | §8 Assessment/Assignments |
| 19–23 min | §9 Results / Report Cards |
| 23–26 min | §10 Fees |
| 26–28 min | §12 Parent Portal |
| 28–30 min | §19 Closing |

## 45-minute detailed demo

The full tour, for a serious buyer or a committee evaluation.

| Time | Section |
|---|---|
| 0–2 min | §1 Platform/Admin intro (if relevant audience) |
| 2–4 min | §2 School setup |
| 4–6 min | §3 Dashboard |
| 6–9 min | §6 Academic setup |
| 9–12 min | §4 Students |
| 12–14 min | §5 Teachers |
| 14–18 min | §7 Attendance |
| 18–22 min | §8 Assessment/Assignments |
| 22–27 min | §9 Results / Report Cards |
| 27–30 min | §10 Fees |
| 30–32 min | §11 Online payment |
| 32–35 min | §12 Parent Portal |
| 35–37 min | §13 Student Portal |
| 37–39 min | §14 Learning materials |
| 39–42 min | §15 CBT (+ §16 Question Bank if there's time) |
| 42–43 min | §17 Communication |
| 43–44 min | §18 Reports/Analytics + Audit |
| 44–45 min | §19 Closing/pilot discussion |
