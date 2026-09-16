# School Administrator Manual

A practical operating guide for the **School Admin** role. School Admin
automatically holds every permission in the system within your school — if
a screen exists, you can reach it. This manual walks through the areas
you'll actually use, in the order a new school typically sets them up.

For a condensed "what do I do on day 1" version, see
[Quick-Start Guide](quick-start-guide.md). For exactly who else can do what,
see [Feature → Role Matrix](feature-permission-matrix.md).

---

## 1. Logging in

**Purpose:** Get into the system.
**Who can do this:** Anyone with an account.
**Prerequisites:** An email + password given to you by whoever set up your
account (or your own, if you registered).

**Steps:**
1. Go to the application's web address. You'll land on the **Sign in**
   page.
2. Enter your **Email address** and **Password**.
3. Optionally tick **Remember me on this device**.
4. Click **Sign in**.
5. If you belong to more than one school (rare for a School Admin), you'll
   land on a **Choose a school** page first — click **Enter** next to the
   right one.
6. You'll land on the **Dashboard**.

**Expected result:** You see the Dashboard with your school's name shown
top-right.

**Common mistakes / troubleshooting:**
- Wrong password three times in a short period will briefly lock login
  attempts — wait a minute and try again.
- Forgot your password? Use the **Forgot password?** link on the sign-in
  page and follow the email that arrives.
- If your account has been suspended or disabled, you'll be signed out
  automatically — contact your platform administrator.

---

## 2. Understanding the dashboard

**Purpose:** A one-page snapshot of your school right now.
**Who can do this:** Everyone with a staff role sees some version of it;
School Admin sees the fullest version.

**What's on it:**
- **Current school** — confirms which school you're working in (you'll
  only ever see that school's data).
- **School onboarding checklist** (only while incomplete) — links to
  finish assigning a School Admin, reviewing settings, and creating your
  first academic session.
- **Administration panel** (if you hold `audit.view`) — active/suspended
  member counts, how many modules are enabled, and the 5 most recent
  administrative actions, with a link to the full **Audit Log**.
- **KPI cards** (only shown once the **Reporting & Analytics** module is
  on, and only the cards you have permission to see) — Students, Staff,
  Attendance (last 30 days), Results, Fees, CBT. A card is simply absent
  if its module is off — this is deliberate, not a bug.
- **View all reports →** link into the full Reports area.

**Common mistakes:** Expecting a KPI card to appear when its module is
switched off — turn the module on first (see §14).

---

## 3. School profile / settings

**Purpose:** Your school's contact details, branding, regional
preferences, and (if enabled) online-payment configuration.
**Who can do this:** School Admin edits everything; Principal and Bursar
have read-only access to the same pages.
**Prerequisites:** None.

**Steps:**
1. Click **School settings** in the left navigation (or go to
   `/settings/school`).
2. You'll land on **School settings**, with sub-tabs across the top:
   **Profile**, **Branding**, **Regional**, **Modules**, and (if the Fees
   module and Paystack are relevant) **Payments**.
3. **Profile** tab: contact email/phone, website, and full address. Edit
   the fields and click **Save profile**.
4. **Branding** tab: upload a logo (used on printed report cards) and set
   a brand colour, then save.
5. **Regional** tab: timezone, locale, currency, date format, and which
   day the week starts on.
6. **Modules** tab: see §14 below.
7. **Payments** tab: see §22 (Payments).

**Expected result:** A green confirmation banner after each save; the new
values are shown immediately.

**Common mistakes:**
- The school **Name**, **Slug**, and **Status** shown at the top of the
  Profile tab are **read-only** — those are set by platform administration
  when the school is created, not editable here.
- Uploading a very small or non-image file as a logo will be rejected —
  use a real image file within the size the form allows.

---

## 4. Academic session setup

**Purpose:** The academic year(s) everything else (classes, attendance,
results, fees) is scoped to.
**Who can do this:** School Admin, Principal manage; Teacher, Staff view
only.
**Prerequisites:** The **Academic Management** module must be on (it is,
by default).

**Steps:**
1. Go to **Academic** in the left navigation → you land on **Academic
   sessions**.
2. Under **Add a session**, enter a **Name** (e.g. `2025/2026`), **Starts
   on** and **Ends on** dates, and optionally tick **Make this the current
   session**.
3. Click **Add session**.
4. To later mark a different session current, click **Make current** next
   to it in the list.
5. Click **Terms** next to a session to add its periods (see §5).

**Expected result:** The session appears in the list, badged **Current**
if you ticked the box.

**Common mistakes:**
- Only one session can be "current" at a time — marking a new one current
  un-marks the previous one automatically.
- You can create a session without any terms yet — you don't have to
  finish everything in one sitting.

---

## 5. Period / term configuration

**Purpose:** Terms/semesters within a session (e.g. First Term, Second
Term).
**Who can do this:** Same as academic sessions.
**Prerequisites:** At least one academic session exists.

**Steps:**
1. From **Academic sessions**, click **Terms** next to the session.
2. Add a term with a **Name**, **Starts on**/**Ends on**, and its order
   position.
3. Use **Make current** to mark the active term within that session.

**Expected result:** The term appears under that session, and is now
selectable wherever a screen asks for "Term / period" (attendance,
assessments, results, fees, reports).

**Common mistakes:** Forgetting to mark a new term current — several
screens default their filters to "the current term," so an un-marked term
won't show up as the default anywhere until you do.

---

## 6. Levels

**Purpose:** Your school's classes / year groups (e.g. "Primary 1", "JSS
2") — nothing is preset, you name and order them yourself.
**Who can do this:** School Admin, Principal manage; others view.
**Prerequisites:** None beyond the Academic module being on.

**Steps:**
1. Go to **Academic** → **Levels** tab.
2. Under **Add a level**, enter a **Name** (e.g. `Primary 1`), a **Short
   code** (e.g. `PRI1`), and its **Order** (a number — lower shows first).
3. Click **Add level**.
4. Click **Arms & subjects** next to a level to add its arms and offered
   subjects (§7, §8).

**Expected result:** The level appears in the ordered list.

**Common mistakes:** Two levels with the same order number — the system
allows it, but the display order becomes ambiguous; use distinct numbers.

---

## 7. Level arms

**Purpose:** Streams/sections within a level (e.g. "Gold", "Silver") —
optional; many schools don't split every level into arms.
**Who can do this:** Same as levels.
**Prerequisites:** At least one level exists.

**Steps:**
1. From **Levels**, click **Arms & subjects** next to the level.
2. Under **Add an arm / stream**, enter **Name** (e.g. `Gold`), **Code**
   (e.g. `G`), and **Order**.
3. Click **Add arm**.

**Expected result:** The arm appears under that level and becomes
selectable everywhere a class is chosen (attendance, assessments,
timetable, results, fees).

**Common mistakes:** Adding arms to a level that doesn't need them —
completely optional; a level with no arms just means "the whole level is
one class."

---

## 8. Subjects

**Purpose:** Your school's subject list, and which levels offer which
subjects.
**Who can do this:** Same as levels/arms.
**Prerequisites:** None to create a subject; a level must exist to offer
one to it.

**Steps:**
1. Go to **Academic** → **Subjects** tab.
2. Under **Add a subject**, enter **Name** (e.g. `Mathematics`), **Code**
   (e.g. `MTH`), **Order**, and an optional **Description**.
3. Click **Add subject**.
4. To offer it at a level: go to that level's **Arms & subjects** page,
   tick the subject's checkbox under **Subjects offered**, and click
   **Save subjects**.

**Expected result:** The subject is available to select wherever that
level's subjects are relevant (assessments, timetable, CBT, learning
materials, teacher assignments).

**Common mistakes:** Creating a subject but forgetting to tick it "offered"
at any level — it will exist but never appear in class-scoped dropdowns
until you do.

---

## 9. Teachers

**Purpose:** Teacher/staff professional records — separate from any login
account (a teacher record can exist with no linked account at all).
**Who can do this:** School Admin, Principal manage; Bursar, Teacher, Staff
view only.
**Prerequisites:** **Teacher / Staff Management** module on (it is, by
default).

**Steps:**
1. Go to **Teachers** in the left navigation.
2. Click **Add teacher** (top-right, or from the empty state).
3. Fill in **Name** (first/middle/last, optional preferred name),
   **Employment** (employee number, start date), **Contact**
   (email/phone/address), and any **Notes**.
4. Click **Add teacher**.
5. Open the teacher's profile → **Application account** section to link
   them to an existing login (see §12) — optional, only needed if they'll
   sign in themselves.
6. Use **Add assignment** on their profile to assign them to teach a
   subject at a class (see below).

**Assigning a teacher to a class + subject:**
1. Open the teacher's profile.
2. Click **Add assignment**.
3. Choose the session, level, arm (if used), and subject.
4. Click **Add assignment**.

**Expected result:** The teacher appears in the Teachers list; their
assignment appears on their profile and makes them eligible to record
attendance/assessments/CBT/learning materials for that specific class +
subject (Teachers are automatically scoped to only their own assignments —
see the permission matrix).

**Common mistakes:**
- Forgetting the assignment step — a teacher record alone does not let a
  Teacher-role user record anything; the **assignment** is what scopes
  them to a specific class/subject.
- Trying to link a teacher's account before that person has actually
  registered/been added as a member — they must already be a **member of
  the school** (see §12) before you can link them.

---

## 10. Students

**Purpose:** The student record + their class placement over time.
**Who can do this:** School Admin, Principal manage; Bursar, Teacher, Staff
view only.
**Prerequisites:** **Student Management** module on (default) and at least
one level exists if you intend to enroll them right away.

**Steps — add a student:**
1. Go to **Students** in the left navigation.
2. Click **Add student**.
3. Fill in **Name**, **Personal** (date of birth, gender — optional),
   **Admission** (admission number — required, and admission date),
   **Contact**, and **Notes**.
4. Click **Add student**.

**Steps — place them in a class (enroll them):**
1. Open the student's profile.
2. Under **Academic placement**, click **Add enrollment**.
3. Choose the session, level, arm, and a start date.
4. Save.

**Steps — change their class later (promotion, transfer):**
- Prefer the dedicated **Promotion** workflow (§23) for end-of-term
  promotion of a whole class. For a one-off individual change, add a new
  enrollment the same way — the old one is automatically closed, never
  deleted.

**Steps — link a login / change status:**
- **Application account** section: link an existing member so the student
  can sign in to the Student Portal (needs the Student role too — see
  §11/§12).
- **Lifecycle status** section: change between Active / Inactive /
  Withdrawn / Graduated. (Graduating should normally go through the
  dedicated **Graduation** workflow, §24, which also closes their
  enrollment correctly — changing status here alone does not.)

**Expected result:** The student appears in searches/lists; once enrolled,
they appear on that class's attendance registers, assessments, results,
and (if guardians are linked) the Parent Portal.

**Common mistakes:**
- Adding a student without ever enrolling them — they'll exist but won't
  show up on any class roster (attendance, assessments) until enrolled.
- Admission numbers must be unique within your school — a duplicate will
  be rejected.

---

## 11. Guardians

**Purpose:** Parent/guardian contact records, and linking them to their
children.
**Who can do this:** School Admin, Principal manage; Bursar, Teacher, Staff
view only.
**Prerequisites:** **Parent / Guardian Management** module on (default).

**Steps — add a guardian:**
1. Go to **Guardians** in the left navigation.
2. Click **Add guardian**.
3. Fill in name and contact details.
4. Click **Add guardian**.

**Steps — link a guardian to a student:**
1. Open the student's profile.
2. Under **Parents / guardians**, click **Add guardian**.
3. Choose (or create) the guardian, set the **relationship** (mother,
   father, etc.), and whether they're the **primary** contact.
4. Save.

**Steps — let the guardian sign in (Parent Portal):**
- On the guardian's own profile, link them to an existing member account
  the same way you would a teacher (§12) — they also need the **Parent**
  role assigned (§12) and the **Parent Portal** module on (§14).

**Expected result:** The guardian appears on the student's profile; once
linked to a login with the Parent role, they see that child (and any
siblings linked to them) in the Parent Portal — never another family's
child.

**Common mistakes:** Linking a guardian's contact record without also
completing the login-account link — the guardian will be recorded, but
won't be able to sign in until both the account link **and** the Parent
role are set.

---

## 12. User accounts (members)

**Purpose:** Who can sign in to your school, and what role they hold.
**Who can do this:** School Admin, Principal (`member.view` +
`member.assign-role`).
**Prerequisites:** The person must already have a registered account on
the platform (this screen adds an **existing** account to your school — it
does not create new logins or send invitations).

**Steps:**
1. Go to **Members** in the left navigation.
2. Click **Add member**.
3. Enter their **Account email** (must match an existing registered
   account) and choose a **Role** from the dropdown (only roles you're
   allowed to grant appear — you can never grant a role more senior than
   your own).
4. Click **Add member**.
5. To change someone's role later or remove them, use the actions on the
   Members list.

**Expected result:** The person appears in the Members list with their
role; they can now sign in and see the school (subject to whatever their
role permits).

**Common mistakes:**
- If the person hasn't registered an account yet, ask them to go to the
  registration page first — this screen only adds *existing* accounts.
- You cannot change your own role or remove yourself from the Members
  list — this is a deliberate safety rule, not a bug.
- Granting the wrong role is easy to fix — just change it again from the
  Members list.

---

## 13. Roles and permissions

**Purpose:** Understand what each role can and can't do — you don't
configure this; it's fixed by the application.
**Who can do this:** N/A — this is reference knowledge, not an action.

There are seven roles: **School Admin**, **Principal**, **Bursar**,
**Teacher**, **Staff**, **Parent**, **Student**. Each is a fixed bundle of
permissions (see the full [Feature → Role Matrix](feature-permission-matrix.md)).
You assign a role to a member (§12); you cannot invent a new role or edit
what an existing role can do.

**Key things to know:**
- A Teacher only sees/records data for the specific classes and subjects
  they've been assigned (§9) — not the whole school.
- A Bursar has zero access to academic data (results, attendance,
  assessments) beyond simply *viewing* students/guardians/staff for
  billing purposes.
- A Principal can do almost everything a School Admin can *except* manage
  school settings/module activation and add/remove members' roles at will
  — Principal is oversight-heavy, not full control.
- Parent and Student never see any staff-facing screen at all — only their
  own portal.

---

## 14. Module activation

**Purpose:** Turn parts of the application on or off for your school —
this is **configuration**, not permission. Turning a module on grants
nobody anything; turning it off doesn't remove anyone's role.
**Who can do this:** School Admin only (`school.settings.update`);
Principal/Bursar can view the same page read-only.
**Prerequisites:** None.

**Steps:**
1. Go to **School settings** → **Modules** tab.
2. Modules are grouped (People, Academics, Assessment, Finance, Reporting,
   Communication, Portals). Each shows its current state
   (**Enabled**/**Disabled**) and whether it's **Available** or still
   **Planned** (not yet built — your choice is saved for when it ships).
3. Click **Enable** or **Disable** next to a module.
4. Some modules require others first (shown as **Requires: …**) — e.g.
   **Timetable** requires **Academic Management** and **Teacher / Staff
   Management**.

**Which modules are on by default for a new school:** Academic
Management, Student Management, Parent/Guardian Management, Teacher/Staff
Management, Promotion & Graduation, Attendance, Assessments, Results &
Report Cards, Fees & Payments, Reporting & Analytics, Notifications,
Parent Portal, Student Portal.

**Which start off (opt-in specialised tools):** Timetable, Learning
Materials, CBT / Online Examinations, Entry / Placement Assessment.

**Expected result:** The relevant nav link appears/disappears immediately
for every user in your school once you enable/disable a module.

**Common mistakes:**
- Trying to enable a module whose dependency isn't on yet — enable the
  dependency first (the page tells you what's required).
- Expecting disabling a module to delete its data — it doesn't. Data is
  preserved; only access through the UI is hidden.

---

## 15. Timetable

**Purpose:** Weekly period schedules for classes and teachers.
**Who can do this:** School Admin, Principal manage; Teacher, Staff view.
**Prerequisites:** **Timetable** module on (off by default — enable it
first); Academic and Teacher/Staff modules on.

**Steps:**
1. Go to **Timetable** in the left navigation.
2. Click to create a new timetable for a session/level/arm.
3. Add entries — day, period time, subject, teacher, optional room.
4. The system checks for conflicts (double-booked teacher/class/room)
   automatically.
5. Publish the timetable when it's ready — a draft timetable is not yet
   visible outside staff editing it.

**Expected result:** A published timetable shows on the class's schedule
and (if the Student/Parent Portal is on) the student's/parent's own
timetable view.

**Common mistakes:** Trying to publish with an unresolved conflict — fix
the flagged clash first.

---

## 16. Attendance

**Purpose:** Daily class attendance registers.
**Who can do this:** School Admin, Principal manage any class (and reopen
a locked register); Teacher records only their own assigned classes; Staff
view only.
**Prerequisites:** **Attendance** module on (default); the class must have
enrolled students.

**Steps — take attendance:**
1. Go to **Attendance** → **Create register** (or **New attendance
   register**).
2. Choose the session, term (optional), level, arm, and date (cannot be a
   future date).
3. Click **Create register** — the class roster on that date is captured
   automatically.
4. On the register, mark each student **Present / Absent / Late /
   Excused** using the single-letter buttons, or use **Mark all present**
   then adjust exceptions.
5. Click **Save draft** to save progress, or **Save & submit** to lock it
   in (only enabled once every student is marked).

**Steps — correct a submitted register:** A School Admin/Principal can
click **Reopen for correction** on a locked register, make changes, and
resubmit.

**Expected result:** Attendance percentages roll up into the student's
result-card attendance summary and the Attendance report.

**Common mistakes:**
- Trying to submit with unmarked students — the **Save & submit** button
  stays disabled until everyone has a mark.
- A Teacher trying to record for a class they aren't assigned to — they
  won't see it as an option; only School Admin/Principal can record for
  any class.

---

## 17. Assessments

**Purpose:** Score entry for classwork, tests, exams — the source data for
Results.
**Who can do this:** School Admin, Principal manage any class + configure
categories; Teacher records only their own assigned (level, subject); Staff
view only.
**Prerequisites:** **Assessments** module on (default); at least one
**assessment category** exists (School Admin/Principal create these under
**Assessments** → **Categories**, e.g. "Classwork", "Exam").

**Steps — create an assessment:**
1. Go to **Assessments** → **Create** (or **New assessment**).
2. Choose the academic context (session, term, level, arm, subject —
   Teachers only see their own assigned combinations).
3. Choose the **Category**, enter **Maximum score**, a **Title**, the
   **Assessment date**, and optional **Instructions**.
4. Click **Create assessment** — the class roster on that date is
   captured.

**Steps — enter scores:**
1. Open the assessment → **Score sheet**.
2. Enter each student's score (leave blank if not yet scored) and an
   optional per-student comment.
3. Click **Save scores**.
4. **Publish** the assessment once scoring is complete, then **Lock** it —
   a locked assessment's scores feed into Results and can no longer be
   edited except by a `.manage`-holder unlocking it.

**Expected result:** Locked, published assessment scores become the input
data a Result Run compiles from (§19).

**Common mistakes:**
- Entering a score above the assessment's own maximum — rejected.
- Forgetting to **Lock** the assessment — an unlocked assessment's scores
  are not picked up when a result run compiles.

---

## 18. Assignments

**Purpose:** Homework/take-home work with a due date and simple completion
tracking (not scored — just pending/submitted/late/exempt).
**Who can do this:** Same tiering as Assessments.
**Prerequisites:** Assessments module on.

**Steps:**
1. Go to **Assessments** → **Assignments** tab → **Create**.
2. Set the class, subject, due date, and instructions.
3. **Publish** it — students/parents can now see it.
4. Mark student submission status from the assignment's **Submissions**
   screen.
5. **Close** the assignment when it's done.

**Expected result:** Published assignments show in the Student/Parent
Portal's own Assignments view.

**Common mistakes:** There is no file upload for assignments in this
version — it tracks completion status only, not attached work.

---

## 19. Results

**Purpose:** Compile a class's locked assessment scores into a termly
result — a controlled multi-step lifecycle, not a single save.
**Who can do this:** School Admin, Principal (full lifecycle including
approve/publish/lock); Teacher only adds a class-teacher comment on their
own assigned class; Staff view only.
**Prerequisites:** **Results & Report Cards** module on (default); a
grading scheme and weighting scheme configured (**Results** →
**Grading schemes** / **Weighting schemes**); every subject's assessments
for the term locked (§17).

**Steps:**
1. Go to **Results** → **Create** (a **result run** is one class, one
   term).
2. Choose session, term, level, arm, grading scheme, weighting scheme.
3. Open the run and click **Compile** — this reads every locked,
   published assessment score for that class/term and computes each
   student's subject percentages, overall average, grade, and (if ranking
   is on) class position.
4. Review the compiled numbers.
5. Click **Mark reviewed**, then **Approve** (numbers freeze from this
   point — further fixes need the adjustment workflow), then **Publish**
   (now visible to parents/students, if the portals are on), then **Lock**
   (the permanent historical record).
6. If a genuine correction is needed **after** locking, use the
   **adjustment** workflow on the affected student's subject result
   (propose → apply/reject) rather than editing anything directly.

**Expected result:** Each student gets a compiled result, a class
position (if enabled), and a printable report card.

**Common mistakes:**
- Trying to compile before every relevant assessment is locked —
  compilation is blocked with a clear list of what's missing.
- Trying to edit a published/locked result directly — not possible by
  design; use the adjustment workflow.
- Deleting a run — only possible while it has no compiled student results
  yet.

---

## 20. Report cards

**Purpose:** The printable/viewable per-student output of a result run.
**Who can do this:** Anyone who can view results can view a report card
for a run that's been compiled; Parent/Student see only published/locked
ones for their own child/self.
**Prerequisites:** A compiled result run.

**Steps:**
1. Open a result run → find the student → click **Report card**.
2. It renders with your school's logo, the configured visible fields
   (see **Results** → **Report card configuration**, where you control
   which of ~24 fields show — comments, attendance summary, signatures,
   etc.), and (if configured) principal/class-teacher signature images.
3. Print via your browser's print function — there is no separate PDF
   export button; the page is designed to print cleanly.

**Common mistakes:** Expecting a bulk "print all report cards" button —
this version renders one student at a time; open each student's card
individually (or loop through the class from the result run's student
list).

---

## 21. Fees

**Purpose:** Fee categories, structures, and the school-wide fee overview.
**Who can do this:** School Admin full control; Bursar full operational
control; Principal view/report only; Teacher/Staff no access.
**Prerequisites:** **Fees & Payments** module on (default).

**Steps — set up categories and structures (do this once per term/type):**
1. Go to **Fees** → **Categories** — add categories like "Tuition",
   "Books" (mirrors how assessment categories work).
2. Go to **Fees** → **Fee structures** → **Create** — define an amount for
   a category × session × (optional term) × level × (optional arm).

**Steps — see the school-wide picture:**
1. Go to **Fees** — shows total charged / discounted / collected across
   the school, and a searchable list of students with their outstanding
   balance.
2. Click a student to open their full statement (see §22 for what Bursar
   does there).

**Expected result:** Structures let you raise identical charges quickly
for a whole class (Bursar does the actual charging — §22); the overview
gives you a school-wide balance snapshot.

**Common mistakes:** Editing a fee structure after charges have already
been raised from it doesn't change those existing charges — each charge is
a frozen snapshot of the structure at the time it was raised. Change the
structure for *future* charges only.

---

## 22. Payments

**Purpose:** Recording money received and tracking balances — day-to-day
work is normally Bursar's (see the [Bursar Manual](bursar-manual.md) for
full detail); School Admin has the same capability.
**Who can do this:** School Admin, Bursar.

**Quick summary** (full detail in the Bursar manual):
1. Open a student's fee statement (**Fees** → search → click their name).
2. **Charges** panel: raise a charge, apply a discount, waive a charge.
3. **Payments** panel: **Record payment** — amount, date, reference,
   method, and (optionally) allocate it straight to specific outstanding
   charges.
4. A payment can be **voided** later (reverses it from balances, keeps the
   record — never deleted).

**Online payments (Paystack):** if configured (**School settings** →
**Payments** tab — enter your Paystack keys), parents/students see a **Pay
online** button on their own fee statement once something is outstanding.
A successful online payment is verified server-side and appears
automatically as a payment on the statement — no manual entry needed.

---

## 23. Promotion

**Purpose:** Bulk-move a class of students to a new session/level/arm at
term/year end, preserving full history.
**Who can do this:** School Admin, Principal.
**Prerequisites:** **Promotion & Graduation** module on (default); a
target session/level/arm must already exist.

**Steps:**
1. Go to **Promotion** → **Create** (or **Promote students**).
2. **Step 1**: choose the **source** session, level, and (optional) arm —
   click **Find eligible students**.
3. **Step 2**: choose the **target** session, level, arm, add optional
   notes, then tick which students to promote (or **Select all**).
4. Click **Promote selected students**.
5. You'll see a success/failure summary — one student's issue never blocks
   the rest of the batch.

**Expected result:** Each promoted student gets a **new** enrollment in
the target class; their old enrollment is preserved, closed — never
deleted or rewritten. You can view a batch's outcome later from
**Promotion** → open the batch.

**Common mistakes:**
- Expecting an automatic pass/fail rule — there isn't one; every promotion
  is a deliberate administrative decision you make by selecting students.
- Re-running a promotion for a student already in the target session is
  safely skipped, not duplicated.

---

## 24. Graduation

**Purpose:** Formally graduate students, ending their active enrollment
while preserving their full history.
**Who can do this:** School Admin, Principal.
**Prerequisites:** Promotion & Graduation module on.

**Steps:**
1. Go to **Promotion** → **Graduation** → **Graduate students**.
2. **Step 1**: choose a session/level/(arm) to browse → **Find students**.
3. **Step 2**: set the graduating session, optional notes, tick students
   (or **Select all**) → **Graduate selected students**.
4. To reverse a mistaken graduation, use **Reactivate** on that student
   (available from the graduation history).

**Expected result:** Graduated students' status changes to **Graduated**;
their historical enrollments and results remain fully intact and
viewable.

**Common mistakes:** Graduating the wrong class by mistake — use
**Reactivate** rather than trying to "undo" any other way.

---

## 25. Learning materials

**Purpose:** Share files (PDF, image, audio) with a specific class +
subject.
**Who can do this:** School Admin, Principal upload for any class; Teacher
uploads only their own assigned class/subject; Staff/Bursar no access.
**Prerequisites:** **Learning Materials** module on (off by default —
enable it first).

**Steps:**
1. Go to **Learning Materials** → **Upload**.
2. Choose session, (optional) term, level/arm/subject (or, for a Teacher,
   pick from **Select one of your assigned classes**), a **Title**,
   optional description, and the **File** (PDF, JPG/PNG/WEBP, or
   MP3/M4A/WAV — max 20MB; video is not supported in this version).
3. Click **Upload & save**.

**Expected result:** Students in that class (and, via the Student Portal,
that specific student) can view/download it.

**Common mistakes:** Uploading a video file — rejected regardless of file
extension (the system checks actual file content, not just the name).

---

## 26. CBT (Computer-Based Testing)

**Purpose:** Online multiple-choice/true-false examinations.
**Who can do this:** School Admin, Principal manage any exam; Teacher
authors only for their own assigned class/subject; Staff view only;
Student sits exams (separate `cbt.take` permission).
**Prerequisites:** **CBT / Online Examinations** module on (off by
default).

**Steps — build and run an exam:**
1. Go to **CBT** → **Create examination**.
2. Set the class/subject, duration, time window (start/end), pass mark,
   and **result release** (Immediate, or Scheduled for a specific
   date/time).
3. Save it as a **Draft**, then add questions — either write new ones or
   pull from the **Question Bank** (§27).
4. Click **Schedule** — this locks the question set and makes the exam
   visible to its class within its time window. (Draft exams are invisible
   to students.)
5. After the window closes (or you choose to), click **Close** — no
   further attempts can start. An attempt already in progress still
   finishes on its own via its own timer.
6. View **Attempts & results** from the exam's page for the full attempt
   list.

**Expected result:** Students in the assigned class see the exam appear in
their own CBT list once scheduled, sit it within the time window, and (per
your release setting) see their score immediately or from the scheduled
time.

**Common mistakes:**
- Trying to edit questions on a scheduled/closed exam — not possible;
  editing questions is only allowed while the exam is still a **Draft**.
- Forgetting to add any questions before scheduling — an exam needs at
  least one question to be scheduled.

---

## 27. Question Bank

**Purpose:** A reusable, searchable pool of multiple-choice/true-false
questions you can attach to any compatible exam.
**Who can do this:** Same tiering as CBT.
**Prerequisites:** CBT module on.

**Steps:**
1. Go to **CBT** → **Questions** (Question Bank).
2. Click **Add question** — write the question text, choose type
   (multiple-choice or true/false), enter options and mark the correct
   one(s), optionally scope it to a subject/level/arm, set a topic and
   difficulty.
3. Use **Activate / Deactivate / Archive** to control whether a question
   can be newly attached to an exam (existing exams that already used it
   are never affected by a later change here).

**Expected result:** Only **Active** questions can be newly attached to an
exam; editing or archiving a question after it's attached never changes an
exam that's already using it — each exam keeps its own frozen snapshot.

---

## 28. Entry / placement assessment

**Purpose:** Record an assessment for a prospective or newly admitted
student — a record only, never an automatic placement decision.
**Who can do this:** School Admin, Principal manage any; Teacher records
only their own assigned classes; Staff view only.
**Prerequisites:** **Entry / Placement Assessment** module on (off by
default).

**Steps:**
1. Go to **Entry Assessment** → **Record an assessment**.
2. Enter the candidate's name and (if they have one) an admission
   reference, the level/arm/subject assessed, a score against a maximum,
   assessment date, and a free-text **result** label your school defines
   (e.g. "Pass"/"Recommend Primary 2").
3. Save.
4. If the candidate proceeds and later gets a real `Student` record, you
   can optionally link this assessment to it.

**Expected result:** A searchable, exportable record — the system never
turns this into a placement decision on its own; that stays a human
judgment call.

---

## 29. Communication

**Purpose:** A staff-facing hub for logging communication with parents
(calls, visits, concerns) plus school-wide announcements.
**Who can do this:** Communication Hub — School Admin, Principal (full
manage), Bursar/Teacher/Staff (log + view, Teacher can also
resolve/escalate); Announcements — School Admin, Principal publish, others
view.
**Prerequisites:** **Notifications** module on (default).

**Steps — log a communication thread:**
1. Go to **Communication** → **New thread** (or **Log communication**).
2. Enter a subject, category, optionally link a student and/or guardian,
   and the message body.
3. Click **Log communication**.
4. Use **Resolve** / **Escalate** / **Reopen** as the conversation
   progresses.

**Steps — publish an announcement:**
1. Go to **Announcements** → **Create**.
2. Write it, choose an **audience** (everyone / all staff / teachers /
   parents / students), save as draft, then **Publish**.

**Expected result:** Published announcements appear to their audience,
including the Parent/Student Portal if relevant; threads remain a
staff-only inbox (parents/students never reply into a thread — they only
see announcements and their own notifications).

---

## 30. Reports / analytics

**Purpose:** Dashboard KPIs plus a full set of filterable reports across
every module, with CSV export.
**Who can do this:** School Admin, Principal, Bursar (fee reports),
Teacher/Staff (their own accessible domains) — see the permission matrix.
**Prerequisites:** **Reporting & Analytics** module on (default).

**Steps:**
1. Go to **Reports** — you'll see a card for each report area your role
   and enabled modules give you.
2. Open one (e.g. **Academic performance**) — it has tabs (Result runs,
   Student performance, Subject performance, Class/arm performance),
   filters (session/period/level/arm/subject/status), and pagination.
3. Click **Export CSV** (only visible if you hold export rights) to
   download exactly what the filtered/paginated view is showing, as a
   file.

**Expected result:** Numbers always match what the underlying module
itself shows — reports never invent or recompute anything independently.

**Common mistakes:** Expecting a report for a module that's switched off
to show data — a disabled module's report area is simply not reachable
(no broken page, no misleading zero).

---

## 31. Audit logs

**Purpose:** An accountability trail of significant administrative
actions — who did what, when.
**Who can do this:** School Admin, Principal (`audit.view`); no one else.
**Prerequisites:** None — always on, not a toggleable module.

**Steps:**
1. Go to **Audit Log** in the left navigation.
2. Search/filter by event type, actor, affected record, or date range.
3. Click any entry for full detail (including a before/after diff where
   relevant).
4. **Export CSV** for the filtered view.

**Expected result:** A read-only, permanent record — there is no edit or
delete function anywhere for an audit entry, by design.

**Common mistakes:** Expecting every click to be logged — only genuinely
significant actions are (role changes, module toggles, student/teacher
creation and status changes, result publish/lock, payments, exam
scheduling, etc.), not routine page views.

---

## 32. Parent Portal

**Purpose:** What a Parent-role user sees — see the full
[Parent Manual](parent-manual.md).
**Your job as School Admin:** Ensure the **Parent Portal** module is on
(§14), the guardian is linked to their child (§11), and the guardian's
account has the **Parent** role (§12).

---

## 33. Student Portal

**Purpose:** What a Student-role user sees — see the full
[Student Manual](student-manual.md).
**Your job as School Admin:** Ensure the **Student Portal** module is on
(§14), the student's account is linked (§10), and their account has the
**Student** role (§12).

---

## 34. Account / security management

**Purpose:** Your own account's settings.
**Who can do this:** Every signed-in user, for their own account.

**Steps:**
1. Click your name (top-right) → **Account settings**, or go to
   **Settings** → **Profile**.
2. Update your name/email, or change your password (**Settings** →
   **Password**).
3. **Switch school** (top-right, if you belong to more than one school or
   are a platform admin) to change which school you're currently working
   in.
4. **Sign out** from the same top-right menu.

**Common mistakes:** Changing your email requires re-verifying it before
some features work again — check for a verification email after changing
it.
