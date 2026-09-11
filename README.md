# Beyond Sure HRMS

A complete Human Resource Management System built on Laravel 13: employee records,
attendance, leave, payroll and salary slips, wired together so a single approval
flows through to the payslip PDF that lands in someone's inbox.

Everything is driven by role-based permissions, and every screen is also available
as a JSON API for a mobile app or a third-party integration.

---

## What it does

| Module | What it covers |
| --- | --- |
| **Employees** | Full personal, employment, bank and statutory records; documents with expiry tracking; reporting lines; onboarding with an auto-provisioned login; offboarding that disables access. |
| **Background verification** | New joiners upload their identity, address, education and experience documents; HR reviews each one, sends back what is wrong, and onboards when it clears. |
| **Companies** | Several legal entities under one installation: employees are assigned to one at onboarding, payroll runs per entity, and each salary slip carries that company's name, registration numbers and logo. |
| **Organisation** | Branches with their own working hours, working week and holiday calendars — including alternate Saturdays off — departments, designations with seniority levels, and shifts. |
| **Attendance** | Self-service punching as many times a day as needed, breaks with a reason and a live working-time counter, late and overtime calculation from the shift, manual entry by HR, a daily roster across the team, monthly calendars and correction (regularisation) requests with approval. |
| **Location checks** | Branches carry their own coordinates and radius; a punch made further away than that is flagged, the people you nominate are alerted, and the day's flagged punches are emailed as a report each evening. |
| **Leave** | Configurable leave types earned either yearly or month by month — 1.5 days a month once confirmed, 1 on probation — with carry-forward, applications validated against balance, notice period and consecutive-day caps, approval workflow, and a shared team calendar. |
| **Payroll** | Salary structures with percentage or fixed components, monthly runs per legal entity that prorate pay from real attendance, optional overtime pay, optional income tax with investment declarations and their verification, draft to approved to paid workflow, a bank payment file checked before it is written, and a register export. |
| **Salary slips** | Per-employee payslips with an earnings and deductions breakdown, net pay in words, a print-ready PDF and one-click email delivery. |
| **Letters** | Offer, appointment, confirmation, increment, experience, relieving, no objection, salary certificate, employment proof and warning — issued on the company letterhead, numbered, emailed, and downloadable by the employee themselves. |
| **Email and notifications** | Sixteen message types — welcome and credentials, leave submitted and decided, payslip with PDF attached, announcements, attendance corrections, password resets — each switchable and rewordable from the interface. |
| **Self Assistance** | A guide to every screen, searchable, reachable from the header, and editable by administrators. |
| **Role-wise access** | Five built-in roles over a 70-permission catalogue, plus custom roles. Branch managers are scoped to their own branch. |
| **Reports** | Attendance, leave, payroll cost and workforce composition, each filterable and exportable to CSV. |
| **Data import** | Bringing an old HRMS in from spreadsheets: branches, departments, designations, employees, leave balances, salary structures and attendance history, each checked row by row before anything is written. |
| **Administration** | User accounts, roles and permissions, company settings and branding, the notification console, the help guides, holiday calendar and a full activity log. |

---

## Documentation

| Document | What it covers |
| --- | --- |
| [docs/TECH-STACK.md](docs/TECH-STACK.md) | Every version and package the application is built from, how the code is organised, and the decisions the codebase leans on. |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | A copy-and-paste installation on Ubuntu 24.04 with nginx, PHP-FPM, MySQL, TLS and the queue worker, plus the routine for shipping an update. |
| [docs/MIGRATION.md](docs/MIGRATION.md) | Moving in from another HRMS: which method to use, the order to load in, and a cutover plan. |
| [docs/API.md](docs/API.md) | The REST API in full: every endpoint, its fields, its rules and its response. |
| [docs/hrms-api.postman_collection.json](docs/hrms-api.postman_collection.json) | The same API as an importable Postman collection. Set `base_url`, run **Login**, and the token is captured for every other request. |

---

## Requirements

- PHP 8.3 or newer, with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`,
  `dom`, `libxml`, `iconv`, `intl`, `ctype`, `json`, `filter`, `session` and
  `fileinfo`
- Composer 2
- **MySQL 8** or MariaDB 10.6 or newer
- Node 20 or newer (only to build the CSS and JavaScript)

`intl` is on that list rather than the next one because every payslip writes
its net pay out in words, and that is `NumberFormatter`. `gd` and `curl` are
worth having — `gd` draws a photographic logo on a letterhead — but nothing
refuses to start without them. The setup wizard checks
all of this and says which of the two lists anything missing is on.

---

## Installation

There are two ways in. Both end at the same place: the schema, the reference
data, your company and one administrator who can sign in.

### In a browser

```bash
git clone <repository-url> hrms-bysure
cd hrms-bysure

composer install
npm install && npm run build
```

Point a browser at the site and it takes over from there. There is no `.env` to
copy and no key to generate — the first request writes both, because a
deployment that greets you with a stack trace about a missing key is not a
deployment anybody wants to receive. Four screens follow:

1. **Server check** — the PHP version, the extensions and the folders that have
   to be writable, each answered with what was actually found.
2. **Database** — host, port, name and login. *Test connection* proves them and
   changes nothing; the database itself is created if this user is allowed to.
   Saving writes `.env`, creates every table and loads the reference data.
3. **Your company** — name, address, currency, time zone, logo and the three
   theme colours. This becomes the first payroll entity, its head office branch
   and a general shift, so an employee can be added the minute you are in.
4. **Administrator** — the first account, with every permission there is.

Then the wizard writes `storage/installed.json` and shuts. From that moment
`/install` answers nothing but a redirect to the sign-in page, whoever asks.

> **Finish the wizard as soon as the site is up.** Until it is done, setup is
> open to anybody who can reach the address — the same as any other web
> installer. A deployment that provisions from the command line instead should
> set `INSTALL_LOCKED=true` in `.env`, and then the wizard can never be reached
> over the web at all.

### From the command line

For a scripted deployment. Put the database credentials in `.env` first, the
way any Laravel application expects, and then:

```bash
php artisan hrms:install \
    --company="Acme Analytics" \
    --admin-name="Meera Krishnan" \
    --admin-email=meera@acme.example \
    --admin-password='a long password' \
    --no-interaction
```

Left interactive it asks for whatever it was not given. It checks the same
requirements, runs the same seeder and writes the same lock file, so a system
set up this way and one set up in a browser are identical. It refuses to run
over a finished installation unless given `--force`.

### By hand

The wizard is a convenience, not a gate. The old route still works:

```bash
cp .env.example .env
php artisan key:generate
```

Create the database with a UTF-8 collation that supports every name and the
currency symbol:

```sql
CREATE DATABASE hrms_bysure CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hrms'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON hrms_bysure.* TO 'hrms'@'localhost';
FLUSH PRIVILEGES;
```

Point `.env` at it:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrms_bysure
DB_USERNAME=hrms
DB_PASSWORD=a-strong-password
```

Then build the schema and the front-end assets:

```bash
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

`db:seed` runs `FrameworkSeeder` — roles and permissions, the settings, the
leave types, the salary components and a help guide for every screen — and then
Beyond Sure's own companies and branches on top. The setup wizard runs the
first of those and stops, which is the difference between an installation that
is yours and one that arrives pre-named.

The seeder always creates one administrator, and having one is also what tells
an upgraded installation it is already set up: a system with a super admin and
no lock file writes the lock file rather than showing anybody a setup screen.
In `local` and `staging`, or when `SEED_DEMO_DATA=true`, it also creates a
26-person demo company with three months of attendance, leave history and two
completed payroll runs.

### Demo accounts

All demo accounts use the password `Password123!`.

| Role | Email |
| --- | --- |
| Super Admin | `admin@beyondsure.example` |
| HR Manager | `priya.raghavan@beyondsure.example` |
| Accountant | `vikram.desai@beyondsure.example` |
| Branch Manager | `ananya.iyer@beyondsure.example` |
| Employee | `arjun.sharma@beyondsure.example` |

Change the administrator password before exposing the application to anyone.

---

## Messages and confirmations

The application never uses the browser's own `alert()` or `confirm()` boxes.
Everything it says appears as a toast in the top corner, and anything
irreversible asks first in the same place.

- **Saying something:** `showToast('Saved.', 'success')` — `success`, `error`,
  `warning` or `info`. Errors interrupt a screen reader and stay twice as long;
  everything else waits its turn and fades. Hovering or tabbing onto a toast
  stops its clock so it can be read.
- **Asking something:** `if (await showConfirmToast('Delete this?')) { ... }`.
  Focus moves to the question and lands on **Cancel** — these are nearly all
  delete buttons, and a stray Enter should give the safe answer. Escape cancels,
  Tab stays inside, and focus returns to where it started.
- **In a Blade view,** put `data-confirm="Delete this?"` on the button and the
  shell does the rest, preserving the button's own `name` and `value`.

Server-side, `back()->with('success', '…')` still works exactly as it did — the
message arrives as a toast rather than a banner. A burst of activity collapses
repeats into one toast with a count and keeps at most four on screen, and an
open question survives any amount of noise around it.

---

### Long forms

A form taller than the screen keeps its Save button within reach: the action bar
sticks to the bottom of the window while there is still form below it, and
settles at the end when there is not. On a phone it spans the full width so a
thumb reaches it without aiming. Nothing has to be scrolled to submit anything.

---

## Dashboards

Three shapes, not one page with everything on it.

- **An employee** sees their own fortnight of hours, where their own leave
  requests stand, and the days they have left.
- **HR, an administrator or a branch manager** sees the organisation: attendance
  over a fortnight, headcount by department, leave requests raised against
  decided week by week, where the whole queue stands, how long a decision takes
  on average, and which kinds of leave are actually being taken.
- **An accountant** additionally sees the payroll panel.

A manager's own attendance is on *My Attendance*, where they would look for it,
rather than repeated here.

Every chart can be read without seeing colour: two series carry a legend and an
end label, states carry an icon and a name beside the count, and each chart has
a **See the figures** table underneath with the numbers in full.

---

## Branding

Your logo and colour are settings, not code. Open **Settings → Branding** as an
administrator and you can change both without a deployment.

### Three theme colours

**Settings → Branding** takes three colours, and every shade between them is
worked out for you. Ten blues that agree with each other is not a thing anybody
should have to pick by hand, and a ramp derived by one rule cannot drift.

| | What it drives |
| --- | --- |
| **Primary** | What you press: buttons, links, focus rings, the screen you are on, the app icon. |
| **Secondary** | Supporting surfaces: the tinted background, the dark navigation column, a second series on a chart. |
| **Tertiary** | Highlights that are neither an action nor a status. |

Each one is a six-digit hex, and its ten shades (50 to 900) are derived by
mixing towards white and black, with 600 being the colour itself.

### The background

The shell is not fixed either. **Background** offers three:

- **Light** — neutral page, white navigation column. The default.
- **Tinted** — the page washed with the faintest shade of the secondary colour.
- **Dark** — the navigation column inverted, taking its darkest tones from the
  secondary colour so it belongs to the same palette rather than being a generic
  slate. The active item is filled with the primary colour, and the text on it
  is black or white depending on which stays readable.

The page background, the navigation column, its icons and the active pill are
all CSS variables re-declared per request, so changing a colour re-themes the
whole interface without rebuilding the stylesheet.

**A layout preview sits under the pickers** — navigation column, top bar, stat
row, chart and buttons — and repaints as you move them, using the same
arithmetic the server does. You can see the layout before you save it.
- **Logo.** Upload a PNG, JPEG, WebP or SVG up to 1 MB. It appears in the
  sidebar, on the sign-in page and at the top of every salary slip, and it
  **replaces the company name** wherever it shows: a logo already says who you
  are, so the name is not printed beside it. Square or wide both work — only the
  height is fixed, so a wordmark keeps its proportions. Until a logo is
  uploaded, the company initials and name stand in.
- **Browser tab icon.** Upload a PNG, ICO, WebP or SVG up to 512 KB, ideally
  square and at least 64×64. Until one is uploaded the tab shows the company
  initials on a primary-to-secondary gradient, matching the app icon in the
  navigation column, so it is recognisable from the first day.
- **Company name.** Set under **Settings → Company**. It appears in the page
  title, every email and the salary slip header, and in the sidebar until a logo
  replaces it.

Both images are served through the application rather than the public symlink,
so they work whether or not `storage:link` has been run, and they go out under a
content policy that stops an uploaded SVG running scripts.

Salary slip PDFs render offline, so the logo is embedded in the document rather
than linked. SVG logos are skipped in the PDF only, because the PDF renderer
does not rasterise them reliably; upload a PNG if you want the logo on payslips.

---

## Companies and payroll

A group usually runs more than one legal entity — Beyondsure Private Limited and
Shrigoda Insurance Brokers Limited, say. Each is a separate employer, so each
issues its own salary slips under its own registration numbers.

- **An employee belongs to exactly one company**, chosen when they are onboarded
  (Employment → Payroll company). Nothing else about them changes.
- **Payroll runs for one company at a time.** Creating a run asks which entity;
  only that company's employees are included, and two companies can both run the
  same month without colliding.
- **The salary slip carries its company** — name, registered address, tax and
  registration numbers, provident fund and ESI numbers, its own logo, and the
  name of whoever signs for it. Slip numbers begin with the company's prefix, so
  `SIBL-202609-EMP0042` says who issued it without opening it.
- **Transfers do not rewrite history.** The company is stamped on each slip when
  it is generated, so moving somebody to another entity next month leaves last
  March's slip saying who actually paid it.

**A company is not a branch.** A branch is where somebody sits and sets their
working week; a company is who employs them and signs their payslip. One office
can hold staff of two companies.

### The document pad

Every document the system generates — a salary slip, an offer letter, an
experience certificate — is printed on the pad of the company that employs the
person. One shared template draws it, so a payslip and a letter from the same
entity look like they came from the same entity.

Each company sets its own under **Companies → Edit → Document pad**:

- **Letterhead logo** — the logo printed on documents, kept apart from the one
  shown on screen: the interface wants something small on a coloured bar, a
  document wants something that survives a black and white printer. Leave it
  empty and the screen logo is used. **A document never falls back to another
  entity's logo** — a second employer printing the parent's mark misstates who
  issued the paper, so a company without a logo prints its name instead.
  PNG, JPEG or WebP; no SVG, because the PDF renderer cannot rasterise one.
- **Footer line** — printed at the foot of every page: a registered office, a
  CIN, a licence number.
- **Watermark** — a pale diagonal mark across every page, so a photocopy is
  visibly a copy of something. It reads the company's short name unless you
  give it other wording, takes the palest step of your theme colour, and can be
  switched off per company.

The pad also carries the address as a block, the registration, tax, provident
fund and ESI numbers, and the signatory — everything a printed document has to
say about who issued it.

### Signatories

Every letter and every salary slip carries a name at the foot. **Companies →
Signatories** is the list of people entitled to be that name for one entity.

- **One of them is the default.** They sign the company's salary slips and are
  offered first when somebody issues a letter.
- **A letter may be signed by anybody on the list**, chosen under *Signed by* on
  the issue screen — which is what makes a letter possible while a director is
  away.
- **A specimen signature is optional.** Upload a PNG and it prints above the name
  on letters and salary slips. It is held on the private disk and served only
  through the screen, never on a public address.
- **What a letter went out over is frozen onto it** — the name, the title and the
  signature image as they stood that day. Correcting a title, replacing a
  signature or retiring somebody changes the next letter, never one already in
  somebody's hands.
- **Somebody who has signed is retired, not deleted**, so the register can always
  say who signed what.

A company that never opened the screen falls back to the single signatory name
on its own record, so nothing already set is lost.

### Money is written the Indian way

Rupees group two digits at a time above the hundreds — **₹9,90,000.00**, not
₹990,000.00 — and are spelled out in lakh and crore: *Rupees Nine Lakh Ninety
Thousand Only*. Every amount the system prints goes through
`App\Support\Money`, so a payslip, a letter and a report about the same salary
cannot disagree with each other or with a bank statement. A company paying in
another currency keeps the grouping its readers expect: `$990,000.00`.

Payslips already issued keep the words they were generated with — like
everything else on a slip, they are a record of what went out.

Manage them under **People → Companies** (`companies.view` to see,
`companies.manage` to change). A company that employs people or has payroll
history cannot be deleted — mark it inactive instead, and it stops being
offered for new employees and new runs.

**Upgrading an existing installation.** The migration creates one company from
your existing settings and assigns every employee, run and slip to it, so
nothing changes until you add a second entity. `CompanySeeder` then names the
two entities above; edit them, or add your own, on the companies screen. An
employee who somehow has no company is still paid by the default company's run
rather than being silently left out.

---

## Background verification

New joiners are onboarded only after their documents have been checked. The
whole conversation happens in the system, so nothing is chased over email
attachments.

**How it runs**

1. **HR adds the employee.** The creation form has a *Background verification*
   section: tick what to ask for and set a date to complete by. It can also be
   started later from **People → Verification**.
2. **The employee is emailed the checklist** — what is needed, how it works, and
   a link to their own screen. The welcome email with their sign-in details goes
   separately, so each says one thing.
   If the wrong things were ticked, the list can be changed from the case at any
   point before it is verified: unticked items are dropped (with their file, if
   one was uploaded), new ones are emailed to the employee, and a case that had
   already been submitted goes back to them for the new item.
3. **The employee uploads each document** with the details asked for beside it —
   the university and year on a degree certificate, the employer and dates on an
   experience letter. They can do it over several sittings; nothing reaches HR
   until they submit.
4. **HR is emailed when it is submitted.** The review screen shows each document
   with its details, opens the file, and takes an accept or a send-back with a
   reason.
5. **Sending anything back** emails the employee with just those items. Anything
   already accepted stays accepted, so nothing is redone twice.
6. **Verify and onboard** clears the case, records who decided and when, and
   emails the employee to say they are all set.

**Until it clears, attendance, leave and salary are closed.** A joiner with an
open case sees *My Attendance*, *Leave*, *Leave Balance* and *Salary Slips*
locked in the menu, their dashboard shows how far along the verification is
instead of the punch card, and the routes themselves (web and API) refuse with a
redirect to the verification screen or a 403. Deciding other people's leave and
looking at the team roster are unaffected, so a manager with an open case can
still do their job. The gate is the `bgv.cleared` middleware; someone who was
never asked for verification is not affected by it.

**What can be asked for**

| Requirement | What is captured alongside the file |
| --- | --- |
| Identity document | Which document, its number, issue and expiry dates |
| Proof of address | Which document, and the address on it |
| Education certificate | Qualification, institution, year completed |
| Experience letter | Employer, job title, dates, an optional reference |
| Previous salary slip | Employer and month |
| Bank account proof | Bank and account number |
| Photograph | — |

The first four are asked for by default; HR ticks what applies to each joiner.
The list itself lives in `app/Support/BackgroundCheckRequirements.php` — adding
an entry there makes it requestable, with its own fields, everywhere.

**Where the files live.** Uploads go to the private disk and are streamed
through the application, never served from public storage. Only the employee
themselves and people who can see that employee's record can open one. Replacing
a document deletes the file it replaced rather than leaving an identity document
lying around.

**Permissions.** `bgv.complete-own` lets someone fill in their own (every role
has it); `bgv.view` sees the cases; `bgv.manage` invites, reviews and onboards.
HR Manager and Super Admin hold all three by default, branch managers can see
but not decide.

All four emails — invitation, submitted, changes requested, verified — are in
the notification console like everything else, so their wording and whether they
are sent at all is yours to set.

---

## Self Assistance

Every screen has a guide, reachable from the **?** icon in the header next to
the notification bell. The menu there offers:

- **Self Assistance** — the whole guide, grouped the same way as the main menu.
- **Help for this page** — the guide for the screen you are on, when one exists.
- **Search help** — a plain search across every guide you are allowed to read.
- **Contact support** — an email to the company address set in Settings.

A guide has four parts, each optional: what the page is for, what the words on
it mean, how to do the handful of jobs people come to it for, and what to try
when it misbehaves. A guide tied to a permission is hidden from people who could
not open that screen anyway, so an employee never lands on a payroll guide.

### Managing the guides

Guides live in the database, not in the code, so they can be rewritten to match
how a particular company works. **Administration → Self Assistance guides**
(permission `help.manage`) lists them all and lets you:

- edit any part of a guide, in Markdown, with repeating rows for the fields,
  tasks and troubleshooting entries;
- write guides of your own — a leave policy, an internal process — with or
  without tying them to a screen;
- keep one as a **draft** while you rewrite it, visible only to other writers;
- restrict a guide to a permission, or leave it open to everyone;
- set the section and the order it appears in.

The shipped guides are loaded by a seeder, so restoring the original wording is:

```bash
php artisan db:seed --class=HelpArticleSeeder
```

That rewrites the guides that ship with the application and leaves any you have
written yourself untouched. Guide text is rendered as Markdown with raw HTML
escaped, so nothing typed into a guide can run in a reader's browser.

---

## Two-step verification

This system holds salaries, bank details and identity documents, so a password
on its own is one leaked spreadsheet away from being somebody else's. Anybody
can add a second factor from their profile; an administrator can require it of
chosen **roles** under Settings → Security.

- **A code from an authenticator app**, not an SMS — Google Authenticator,
  Microsoft Authenticator and 1Password all work. The algorithm is TOTP as
  published in RFC 6238, implemented here rather than taken from a package so
  that the suite can check it against the RFC's own test vectors.
- **A code works once.** Accepting the same six digits twice inside their
  half-minute would make a code read over a shoulder as good as the phone.
- **Eight recovery codes**, shown exactly once when you enrol. They are stored
  hashed, so nobody — including an administrator — can read them back to you.
  Each signs you in once.
- **Nobody is locked out.** Somebody whose role requires it and has not enrolled
  is sent to the setup screen, not refused; signing out always works; and an
  administrator can clear a lost second factor from Administration → User
  Accounts, which tells the person it happened.
- **Turning it on or off emails the account holder**, because a second factor
  quietly removed is the first thing an intruder would do.

Requirement is per role rather than for everybody on purpose: requiring an
authenticator app of a factory hand who punches in from a shared terminal is how
a security control ends up switched off for everyone.

## Roles and permissions

Permissions are defined once in `app/Support/Permissions.php` and grouped by
module, so the roles screen renders itself from that catalogue. Five roles ship
by default, and you can add more from **Administration → Roles**.

| Role | Scope |
| --- | --- |
| **Super Admin** | Everything, including roles and settings. Cannot delete their own account. |
| **HR Manager** | Employees, attendance, leave and reports across all branches. Cannot change roles, approve payroll or edit settings. |
| **Accountant** | Payroll, salary structures, payslips and financial reports across all branches. Cannot approve their own runs. |
| **Branch Manager** | Their own branch only: team attendance, leave approvals, announcements and reports. |
| **Employee** | Their own attendance, leave, payslips and profile. |

Two safeguards are enforced in code rather than by convention: the last super
admin cannot be demoted or deleted, and nobody may approve their own leave
request or delete their own account.

---

## Sessions and breaks

A working day is not one arrival and one departure. People go out to a client at
eleven and come back at three; they take lunch, sit in a meeting, deal with a
personal call. The system records the day as it actually happened.

**Punching.** Punch in when you start and punch out when you leave, as many
times as the day needs. Each pair is a **session**, with its own location
captured at both ends. The day's summary — first in, last out, hours worked —
is rebuilt from the sessions every time one changes, so payroll, the monthly
sheet and every report keep reading the single figure they always have.

**A punch survives everything except forgetting.** The moment somebody presses
the button a row is written; nothing about it lives in the browser or the login
session. Closing the tab, logging out, a flat battery, moving to a phone — the
punch is still there, the counter still reads correctly when they come back
(it is computed from the punch, not run in the browser), and Punch out works
from anywhere.

**Forgetting to punch out is different, and it never costs anybody their pay.**
A day counts as present on the **check-in**, so the salary is safe either way.
What is lost is the hours: a session's length is only written when it closes, so
until somebody closes it the day reports nought hours and the card keeps reading
*"still working"*. Nothing closes it on its own.

So there is a switch — **Close punches that were never punched out**, under
Attendance settings, **off until you turn it on**. With it on, a nightly job:

- closes yesterday's open punches **at the end of that person's shift**, never
  at the hour the job happens to run, and never before the punch itself — a
  punch made after the shift ended credits no hours at all rather than
  inventing a number;
- closes any break left running with it, so lunch does not count all night;
- marks the day **needs a correction**, which is the point: a wrong number
  somebody is asked to fix beats a zero nobody notices until payday. It shows
  on the punch card, the roster and the monthly calendar;
- **writes to the employee and to nobody else.** It is not a disciplinary
  matter and their pay has not moved — it is a correction only they can make.

The flag clears when a correction is approved, and only then.

**Breaks.** Start a break, say what it is for, and add a comment if it helps.
The working-time counter in the header pauses until the break ends. What can be
chosen lives in `app/Support/BreakReasons.php`:

| Away for | |
| --- | --- |
| Lunch | Personal telephonic call |
| Outside meeting | Client — telephonic call |
| Client visit | Team meeting |
| Product training | Paperwork |
| Other | |

**How the hours add up.** Worked time is every session added together, less
every break. Somebody who never touches the break button is treated exactly as
before: the unpaid break configured on their shift comes off instead. Real
breaks replace that nominal one rather than stacking on top of it, so an
employee who records twenty minutes of lunch is not also charged the shift's
hour.

**A break needs an open session** — you cannot be on a break while punched out —
and punching out ends a break you forgot, at the moment you punched out, rather
than leaving it counting overnight.

**Corrections.** A manual entry by HR, and an approved attendance
regularisation, replace the day's sessions with the times entered. Breaks the
employee recorded themselves are left alone.

**On the API.** `POST /api/v1/attendance/check-in` and `check-out` behave the
same way — several a day. `GET /api/v1/attendance/break-reasons` lists the
reasons so a mobile app can draw the picker, and `POST
/api/v1/attendance/break/start` and `break/end` do the rest.

---

## Attendance and location

By default an employee must share their location to check in or out, and the
position is stored against the record.

- The browser asks for permission on the first punch. If it is refused, the
  punch is blocked and the reason is explained on screen, along with how to
  allow it again. Permission denied, no position fix, a timeout and an
  unsupported browser each get their own message, because only some of those
  are something a person can act on.
- The same rule is enforced on the server, so a request that skips the browser,
  including one from the mobile API, is refused rather than silently recorded.
- Coordinates are stored with the accuracy the device reported, and each punch
  links to a map from the roster, the monthly sheet and the record editor.
- HR entering attendance on someone's behalf is not asked for a location, since
  they are not at the place the punch describes.

**Browsers only offer location over HTTPS.** On plain HTTP the punch buttons
report that location is unavailable, so serve the application over TLS. The
exception is `localhost`, which browsers treat as secure for development.

To turn the requirement off, clear **Require location to check in and out**
under Settings, Attendance. Punching then works without a position, which suits
a desk-bound office where devices have no reliable fix.

---

## Punches away from the branch

A branch carries the coordinates of the place itself, set on the branch screen —
either typed in, or captured with **Use my current location** while standing
there. Every punch already records where the person was, so the distance between
the two is worked out at the moment of the punch and kept beside it.

Anything further than the branch allows — **200 metres** by default, and a
branch can be given its own radius — is flagged.

- **The punch is still recorded.** A client visit, a site inspection or a phone
  with a poor fix indoors all look identical from here, so refusing the punch
  would lose real attendance to catch the rare case of somebody punching in from
  home. It is a note to look at, not a verdict.
- **The people you nominate are told straight away**, by email and in the bell
  menu. Set them under Settings, Attendance; leave the list empty and it falls
  back to every active HR manager and administrator, so an alert is never sent
  nowhere. The employee's own reporting and branch managers can be added too.
- **The employee is not told on themselves.** They know where they were standing.
- **The whole day is emailed as a report each evening**, at an hour you choose.
  It is sent even on a day with nothing to report, because otherwise silence
  from the report and silence from a stopped scheduler look the same.
- **Attendance & leave → Location Alerts** lists the same punches on screen with
  the distance, the reported accuracy and a link to the map, scoped so a branch
  manager sees their own branch only.
- **A branch with no coordinates is never checked**, and the screen names any
  branch in that state rather than leaving you to wonder.

The distance and the verdict are frozen onto the punch. Correcting a branch's
coordinates or widening its radius later changes what happens next, not what was
recorded at the time.

The evening report needs the scheduler — one cron entry, described in
[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). The wording of both the alert and the
report is editable under Administration → Notifications, where either can be
switched off.

---

## The working week, and alternate Saturdays

A branch carries its own working days, so a five-day office and a six-day
office run side by side. A six-day branch can also take **some** of its
Saturdays off: tick Sat under working days, then tick the ones that are not
worked — **1st and 3rd** is the usual alternate-Saturday week, and 2nd and 4th
is equally supported.

Which Saturday a date is comes from its position in the month: the 5th of a
month is the first Saturday, the 19th is the third.

An off Saturday is a weekly off everywhere it matters, because one method
answers the question and everything else asks it:

- leave taken across it does not spend a day on it
- payroll does not count it among the month's working days
- the roster and the monthly calendar show it as an off, not an absence
- attendance reports count it as a weekly off

One thing to watch: a **shift** that lists its own working days overrides the
branch. Add Saturday to that shift, or clear the shift's working days so it
follows the branch, before the pattern reaches anybody on it.

---

## How leave is earned

A leave type is earned one of two ways.

**Granted for the year** hands over the whole entitlement at the start of the
year, prorated for somebody who joins mid-year. Straightforward, and generous
to anybody who leaves in March.

**Earned monthly** credits a fixed number of days each month, and a lower
number while somebody is on probation — **1.5 days once confirmed and 1 day on
probation** is the usual arrangement. Set it on the leave type: choose *Earned
monthly*, give the two rates, and say which month to start crediting from.

- **The rate follows the person, month by month.** Whoever is on probation at
  the end of a month earns the probation rate for it. Confirming somebody on
  15 July gives them 1 day for June and 1.5 from July, without anybody
  recalculating anything.
- **Probation is decided by the confirmation date** where one is recorded, and
  by employment status where it is not.
- **A part month is credited for the part worked.** Joining on 20 June earns
  eleven thirtieths of the month's rate; leaving on the 10th earns ten of them.
- **Every credit is a row.** The employee's Leave Balance screen lists each
  month, the rate it was credited at, and whether it was a part month — so
  "why do I have 12 days" has an answer with dates against it.
- **The job is safe to run repeatedly.** Credits are written overnight by the
  scheduler; a month already credited is skipped, and a month the server missed
  is caught up the next night.
- **Credits are added, never recalculated.** An opening balance brought in from
  an old system survives.
- **Days per year becomes a ceiling** for a monthly type rather than a grant.
  1.5 a month reaches 18 in a year, so a limit of 12 would stop crediting in
  August. Zero means no ceiling.

```bash
php artisan hrms:accrue-leave --dry-run   # what would be credited
php artisan hrms:accrue-leave             # credit it
```

Switching an existing type to monthly leaves any allocation already granted
this year in place — credits are added to it. If you want balances to start
from the monthly credits alone, zero that year's allocations under Leave
Allocations first.

---

## Three settings that change the arithmetic

**Let employees check in and out themselves.** Off hides the punch buttons and
closes the same door on the mobile API — the switch lives in the policy, not
the views, so there is no way round it. Attendance is then entered by HR, and
employees can still raise corrections.

**Treat unmarked past working days as absent.** On, a past working day nobody
recorded is an absence and becomes loss of pay. Off, it reads as *Not marked*
on the roster and the calendar, and payroll pays it — a forgotten punch then
costs nobody their salary. This is the safer default for an office that is
still bedding the system in.

**Financial year starts in.** April by default. The payroll cost report and the
joiner and exit figures run April to March and are labelled *FY 2026–27*; set
it to January and everything reads as the calendar year again. Leave
allocations stay on the calendar year either way, which is what most Indian
employers actually run.

---

## How payroll is calculated

### Statutory deductions follow their real rules

A percentage of a figure is not what provident fund, employee state insurance or
professional tax actually are, and the difference only shows on somebody well
paid — which is exactly who was being over-charged. Each component can now carry
the rule that governs it, on **Payroll → Salary Components**:

| | The rule | What it means |
| --- | --- | --- |
| **Wage ceiling** | The percentage is taken on a wage capped at this | Provident fund is 12% of a basic capped at ₹15,000, so somebody on ₹1,84,000 contributes ₹1,800, not ₹22,080 |
| **Applies up to** | Above this the component does not apply at all | Employee state insurance stops at ₹21,000 gross — over that somebody is outside the scheme, not paying on a capped wage |
| **Slab table** | A table of bands rather than a percentage | Professional tax: the amount for the first band the wage does not exceed, with the last band open-ended |

The bands are stored lowest first whatever order they are typed, because the
first band a wage does not exceed decides the amount.

**Check these before your first run.** The defaults are the current national
figures for provident fund and state insurance, but **professional tax is set by
each state** — Karnataka's slabs are loaded as an example, and every component
carries a note saying which rules are in it. Payslips already issued keep the
figures they went out with; these rules apply to the next run.


1. A run is created for a month, optionally limited to one branch.
2. For each employee the salary structure in effect on the period end date is resolved. Anyone without one is skipped and named in the result.
3. Attendance and approved leave for the period produce the **paid days**: days present plus paid leave. Unpaid leave and unexplained absence become **loss of pay**.
4. Earnings resolve first, so a deduction expressed as a percentage of gross sees the final gross. Percentage components resolve against basic, gross or monthly CTC as configured.
5. Components marked *prorate on loss of pay* scale by `paid days ÷ the month's working days`. A mid-month joiner or leaver is therefore paid for their share of the month, and days outside their employment are not counted as loss of pay.
6. Overtime, **if it has been switched on**, is added as its own earning before the deductions are resolved.
7. Net pay is gross earnings minus deductions, and is also written out in words for the payslip.

### Overtime is optional, and starts switched off

Attendance has always recorded the minutes worked beyond a full day. Whether
they are *paid* is a separate question, and the answer starts as no: an
installation that never visits **Settings → Payroll** reports the hours on the
attendance screens and on the payslip and pays nobody for them, which is what a
salaried office wants.

Switch **Pay for overtime** on and each month's recorded hours become an
`Overtime` line on the slip:

```
hourly rate = ordinary monthly wage ÷ (the month's working days × hours in a working day)
paid        = hourly rate × multiplier × hours
```

- The **ordinary monthly wage** is basic or gross, whichever you choose, before
  any loss of pay — an hour worked is worth the same whether or not the employee
  was absent on some other day. It never includes overtime itself: nobody is
  paid overtime on overtime.
- The **multiplier** defaults to two, which is what section 59 of the Factories
  Act 1948 requires.
- Dividing by *the month's own* working days means an hour in February is worth
  a little more than an hour in March. That is the same salary over fewer days,
  not an error.
- A **monthly cap** stops the pay, not the record: hours past it still appear on
  the slip and in the reports, they are simply not paid.
- An individual can be left out on their own record — **Eligible for overtime
  pay**, on the Employment tab. People on a manager's grade usually are.
- Overtime joins gross **before** the deductions are worked out, so a deduction
  charged on gross wages — state insurance is — is charged on it too.

The hours and the rate are frozen onto the payslip, so a slip issued last March
still explains itself after the rate is changed.

The run then moves through **draft → pending approval → approved → paid**.
Approving publishes the payslips so employees can see them; marking the run paid
stamps the payment date and reference on every slip.

### Income tax, if you want it deducted

Tax deducted at source is not a percentage of a payslip. It is a projection: the
whole year's income estimated, the year's tax on it worked out, what has already
been deducted taken off, and the rest spread over the months that remain. A rise
in October therefore changes every remaining payslip, and so does an investment
declared in January.

**It starts switched off.** Until somebody turns on *Deduct income tax from
salaries* under Settings → Payroll, salaries are paid gross of tax exactly as
they were before this existed. Taking money out of somebody's pay because the
software decided to is a worse failure than not taking it: the second is noticed
in April and corrected, the first is noticed on payday.

- **Employees declare, HR verifies.** Both figures are kept. Somebody declares
  in April what they intend to invest by March, and payroll has to deduct on
  something meanwhile; HR sees the proof in January and may allow less. An
  unruled item counts at what was declared — nobody having looked is not the
  same as having accepted nothing. Proofs go to the private disk and are
  streamed, never linked.
- **Nothing chooses a regime.** Both are worked out on every screen, side by
  side, with the difference named. Above a certain salary the new regime often
  wins even with every deduction given up, which surprises people — which is
  exactly why the comparison is shown rather than a recommendation.
- **The rules are a catalogue keyed by financial year.** `TaxRegimes` holds the
  slabs, the section 87A rebate, the surcharge bands and the cess;
  `TaxDeductionSections` holds 80C and its companions, 80D, 24(b), the house
  rent exemption and the rest, with their ceilings and which regime each
  survives in. A year the catalogue does not know falls back to the latest it
  does **and says so on the screen**. Check them against the current Finance Act
  — they are revised most years.
- **Both marginal reliefs are implemented**, because without them a rupee of
  extra income costs tens of thousands: section 87A's, and the one beside the
  surcharge thresholds.
- **Every figure is explainable.** The computation sheet is not a number but the
  working — gross, each exemption with the reason it was capped or disallowed,
  taxable income, the tax at each band, rebate, surcharge, cess, what has been
  paid and what is left. "Your tax is ₹1,04,000" is not checkable and is exactly
  what gets queried.
- **The computed line replaces the flat one.** The default component set carries
  a placeholder TDS component at a flat share of gross. Switching deduction on
  replaces it on every slip — including when the answer is nothing, so turning
  the feature on for a low earner leaves them alone rather than falling back to
  the percentage.

The annual **statement** is the Part B content of a Form 16 — the salary
breakdown and the tax computation — and says on its face that it is not a Form
16. Part A carries the deductor's TAN and the challan identification numbers the
department itself holds, is issued from TRACES against the returns actually
filed, and is not ours to invent. A document that looked official while carrying
figures nobody had filed would be worse than no document at all.

### Paying them: the bank file

An approved run produces a file a bank will accept — which is not the payroll
register beside it. The register is a report somebody reads, and a column out of
place is a nuisance. A bank file is parsed by a bank, and the failure it guards
against is money reaching the wrong account.

So it is **checked whole before a byte is written**, the way a spreadsheet import
is: a missing account number, an IFSC that is not one, two people sharing an
account, a net pay of nothing. Every problem is listed against the person it
belongs to, and no file is produced while any of them stands. A bank rejects the
whole upload over one bad row and tells you days later, after the salary date.

- **The layouts live in `app/Support/BankFormats.php`,** each one a column list
  drawn from a single vocabulary of fields. A new bank is an entry there and
  nothing else. Generic CSV, HDFC, ICICI, SBI and Axis ship. **The screen prints
  the columns it will write** — compare them against the template your bank sent
  you before the first upload, because banks revise these.
- **The payment type is worked out per person:** an account at your own bank
  never leaves it, two lakh or more must go by RTGS, everything else is NEFT.
- **The account the money leaves is on the company,** not in settings — two
  legal entities do not share a bank account.
- **Amounts are unformatted.** `1234.50`, never `₹1,234.50`. This is the one
  place `Money` deliberately stays out of.
- **Somebody paid in cash or by cheque is not in the file** and is counted
  separately, so the file's total and the run's total can be reconciled.
- **Downloading does not mark the run paid.** The money leaves at the bank; a
  file downloaded and never uploaded must not look like a payment that happened.

---

## How it looks

The interface is an **operations console** rather than a website. It is read all
day by people doing payroll and attendance, so it is built for density and for
scanning:

- **Registers are grids** — ruled columns, banded rows, a tinted header, and
  rows that do not wrap. A long designation does not turn every row three lines
  tall; a wide table scrolls sideways inside its panel instead.
- **Money and dates are right-aligned in tabular figures,** so decimal points
  line up down a column and a total can be checked by eye.
- **Every page opens with a band** carrying the breadcrumb, the title, the
  actions and — on a record — a strip of key facts and the section tabs, so a
  person's code, company, branch, manager and joining date are on one line
  under their name instead of behind three clicks.
- **Filters sit on the register they filter,** with the result count beside
  them, rather than floating in a separate box above it.
- **The three brand colours still drive everything.** Changing them under
  Settings → Branding repaints the whole console, including the active rule
  down the left of the navigation column.

## Coming from another HRMS

Administration → Data Import loads an old system's data from spreadsheets. Seven
kinds of file, meant to be loaded in the order the screen lists them, because
each one names the ones above it by code:

**branches → departments → designations → employees → leave balances → salary
structures → attendance history**

- **Download the template** for what you are loading. It carries the exact
  header row and one example, so there is nothing to guess.
- **Every upload is checked first.** You get the number of rows that are ready,
  the ones that are not, and the reason against each — before anything is
  written. Bad rows download as their own file with a `problem` column, to fix
  and upload on their own.
- **The import is one transaction.** If something fails part way, the whole file
  is rolled back.
- **Re-importing is safe.** A row whose code already exists updates that record
  rather than creating a second one, so the way to fix a bad file is to correct
  it and send it again.
- **Column names are matched loosely** — `Employee Code`, `employee_code` and
  `EMP ID` are the same column — dates read day-first, and amounts keep the
  separators people type.
- **Nobody is emailed.** Logins are created; welcome messages are not sent.
- **Large files** go through the command line instead:
  `php artisan hrms:import employees people.csv --commit`.

[docs/MIGRATION.md](docs/MIGRATION.md) covers the whole cutover, including when
a direct database migration is the better answer.

---

## Assets

What the company hands out and expects back: laptops, phones, SIM cards,
access cards, vehicles, software seats. **People → Assets** is the register;
**My workspace → My Assets** is what one person is holding.

What is asked for depends on the kind, from the catalogue in
`app/Support/AssetTypes.php`. A laptop has a serial number, a SIM card has a
number that is dialled, an access card has a badge number and nothing else —
so the form asks for the right things rather than showing one form that is
three-quarters blank whatever you are recording.

Two rules do the work:

- **An asset is with at most one person at a time.** Issuing one that is
  already out is refused rather than quietly opening a second assignment and
  losing the first. A handover straight from one person to another is recorded
  as a return and an issue, so both periods survive.
- **Condition is recorded both ways.** What state it went out in, and what
  state it came back in. That is what settles an argument about when something
  was damaged, and it is why the person gets an email at both ends — their
  receipt.

Nobody marks their own asset returned; that goes through whoever receives it,
on the web and over the API alike.

**On the way out**, somebody leaving with company property is flagged on their
employee page and again on the relieving letter screen. It does not block the
letter — whoever signs it may have a good reason — but nobody signs it without
being told.

---

## Full and final settlements

When somebody leaves, **Payroll → Settlements** works out what they are owed.

Three lines come from the record and the law. **Salary for the final month**,
prorated across the month's own days — and left off entirely if payroll already
published a slip for that month, so nobody is paid September twice. **Leave
encashment**, on the types that carry forward only: an allowance that lapses in
December does not become money because somebody left in November. **Gratuity**
under the Payment of Gratuity Act — fifteen days' wages a year at last drawn
basic times 15/26, once five *completed* years are up, capped at the statutory
maximum.

Everything else is a judgement and is typed in against a named line: a bonus
promised, a laptop never returned, an advance outstanding. The asset register
is read directly, so whoever prepares the settlement is told what the person
still holds.

**Every line keeps its reasoning beside its number** — "8 completed years at
15/26 of a last drawn basic of 2,00,000" rather than a bare figure — and that
sentence is printed on the statement. Approving fixes the figures: nothing is
recalculated afterwards, because from that moment somebody is holding a
statement of them. A settlement can come out negative, and is then shown as
recoverable rather than payable.

Preparing and approving are separate permissions.

---

## Letters

Ten kinds of letter, issued on the letterhead of the company that employs the
person:

| When | Letters |
| --- | --- |
| **Joining** | Offer, appointment, confirmation |
| **Pay** | Increment / salary revision, salary certificate |
| **Leaving** | Experience, relieving, no objection certificate |
| **On request** | Employment and address proof, warning |

Pick the person and the letter, fill in the few things that letter needs, read
the preview and issue it. Everything else is read from the record: the employee
code, designation and joining date, the salary structure in force, the
company's own details and signatory. **An increment letter works out its own
increase and percentage**; a salary certificate quotes the current gross and the
last net pay without anybody retyping a figure.

- **Numbered on issue** — `BSPL/INC/2026/0007`: the company, the kind of letter,
  the year, a running count.
- **Signed by somebody real.** *Signed by* offers the company's signatories, the
  default first; their name, title and specimen signature are frozen onto the
  letter as it is issued.
- **The words are frozen.** Rewording a template changes the *next* letter and
  leaves an issued one exactly as it went out — which is what somebody holding a
  signed copy would expect.
- **Employees download their own.** *My Letters* keeps every letter issued to
  them, so an appointment letter needed three years later does not start with an
  email to HR.
- **Emailed with the PDF attached**, through the same notification console as
  everything else, so the covering note is yours to reword or switch off.
- **The wording is yours.** **Administration → Letter Wording** lists all ten
  and edits each one in markdown, with the values you can drop in shown beside
  it and a preview built from sample details. Templates are substituted, never
  evaluated: no Blade, no `eval`. **Reset** puts the shipped wording back.
- **Refused when it makes no sense.** An experience or relieving letter needs a
  last working day on the record, rather than printing a certificate with a
  blank where the leaving date belongs.

Letters are separate from **Documents** on the employee's profile, which is
where scanned and uploaded files live.

---

## What the system does on its own

**Administration → Automations** lists every scheduled job with what it does,
when it runs, when it last ran, what it did and whether it worked — and lets you
move the hour, switch one off, or run one now.

| | Runs |
| --- | --- |
| **Credit monthly leave** | Every day at 01:15 |
| **Open the new leave year** | 1 January at 02:00 |
| **Confirm people whose probation has ended** | Every day at 02:15 |
| **Birthdays and work anniversaries** | Every day at 08:00 |
| **Chase what is waiting on somebody** | Every day at 09:30 |
| **Check the month is fit to be paid** | 1st of the month at 07:00 |
| **Punches away from the branch** | Every day at 19:30 |

Three of those are new, and two of them were bugs wearing the clothes of a
missing feature:

- **Probation never ended by itself.** Somebody past their confirmation date
  stayed marked as on probation in every list and report, and where no
  confirmation date had been recorded at all they kept earning the lower
  probation leave rate indefinitely. The job moves them and tells HR a
  confirmation letter is due — issuing it stays a deliberate act.
- **The leave year only opened if somebody pressed a button.** Carry-forward was
  written and correct, and fired only when a person remembered. A year opened
  late is a year nobody can apply for leave in.
- **Birthdays were worked out for the dashboard and then dropped.** Now they are
  a notification like any other, with work anniversaries beside them.

Two more chase what is sitting still. **Chase what is waiting on somebody**
finds leave and attendance corrections untouched for three days and sends the
approver one message listing all of it — a manager with six waiting does not
need six emails — and reminds joiners who never sent their documents. **Check
the month is fit to be paid** runs on the 1st for the month just ended and lists
what would make the pay wrong: days nobody recorded, leave still undecided. It
changes nothing; payroll pays whatever the record says on the day it runs, and
the point is to be told before rather than corrected after.

Every run is recorded, so a job that failed at two in the morning is visible on
the screen instead of being found out weeks later. The screen says plainly when
nothing has ever run on a schedule, which is what an installation missing its
cron entry looks like.

---

## REST API

Every employee-facing feature is available under `/api/v1`, authenticated with
Sanctum personal access tokens. The summary below is the shape of it;
[docs/API.md](docs/API.md) documents every endpoint in full, and
[docs/hrms-api.postman_collection.json](docs/hrms-api.postman_collection.json)
is the same thing ready to import into Postman.

```bash
curl -X POST https://your-host/api/v1/login \
  -H 'Accept: application/json' \
  -d 'email=arjun.sharma@beyondsure.example&password=Password123!&device_name=mobile'
```

Send the returned token as `Authorization: Bearer <token>` on every other call.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/v1/login` | Exchange credentials for a 30-day token |
| `GET` | `/api/v1/me` | Profile, roles and permissions |
| `POST` | `/api/v1/logout` | Revoke the current token |
| `POST` | `/api/v1/change-password` | Change the signed-in user's password |
| `GET` | `/api/v1/dashboard` | Compact home-screen payload |
| `GET` | `/api/v1/attendance/today` | Today's punch state, and whether a location is required |
| `POST` | `/api/v1/attendance/check-in` | Check in; `latitude` and `longitude` are required while location is enforced |
| `POST` | `/api/v1/attendance/check-out` | Check out; same location rule as check in |
| `GET` | `/api/v1/attendance/break-reasons` | The break picker's contents |
| `POST` | `/api/v1/attendance/break/start` | Start a break, with a reason and an optional comment |
| `POST` | `/api/v1/attendance/break/end` | End the running break |
| `GET` | `/api/v1/attendance/summary` | Monthly totals and a per-day breakdown |
| `GET` | `/api/v1/leave/balance` | Balance per leave type |
| `GET` | `/api/v1/leave/types` | Leave types the caller may use |
| `POST` | `/api/v1/leave` | Apply for leave |
| `POST` | `/api/v1/leave/{id}/cancel` | Cancel a request |
| `GET` | `/api/v1/leave/pending-approvals` | Requests awaiting the caller |
| `POST` | `/api/v1/leave/{id}/approve` | Approve a request |
| `POST` | `/api/v1/leave/{id}/reject` | Reject a request |
| `GET` | `/api/v1/payslips` | Published payslips |
| `GET` | `/api/v1/payslips/{id}/download` | Payslip as a PDF |
| `GET` | `/api/v1/letters` | Letters issued to the caller |
| `GET` | `/api/v1/letters/{id}/download` | An issued letter as a PDF, on its company's letterhead |
| `GET` | `/api/v1/background-check` | A joiner's own checklist, and what each item asks for |
| `POST` | `/api/v1/background-check/items/{item}` | Upload or replace one document |
| `POST` | `/api/v1/background-check/submit` | Hand the set to HR |
| `GET` | `/api/v1/my-assets` | Company property issued to the caller |
| `GET` | `/api/v1/help` | Self Assistance guides the caller may read |
| `GET` | `/api/v1/employees` | Directory, scoped to what the caller may see |
| `GET` | `/api/v1/holidays` | Holiday calendar for the caller's branch |
| `GET` | `/api/v1/announcements` | Published announcements |
| `GET` | `/api/v1/notifications` | In-app notifications |

Authorisation is identical to the web application: the same policies apply, so
an employee calling `/api/v1/employees` sees only their own record.

---

## Email

Outgoing mail uses the standard Laravel mailer, configured in `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="hrms@your-company.com"
MAIL_FROM_NAME="Your Company HRMS"
```

Set `MAIL_MAILER=log` in development to write messages to
`storage/logs/laravel.log` instead of sending them. The sender name and address
can also be overridden from **Settings**, and all outgoing email can be switched
off there without touching the mail driver.

### The mail server can be set from the interface

**Settings → Email → Mail server** decides where messages actually go, without a
deployment. The choices are:

| | What it does |
| --- | --- |
| **Use the environment file** | The default. Nothing is stored and `MAIL_MAILER` in `.env` keeps deciding. |
| **An SMTP server, set below** | Host, port, username, password and encryption typed here override `.env`. |
| **Write to the log, send nothing** | Messages go to `storage/logs/laravel.log`. |
| **Discard everything, send nothing** | Messages are thrown away — a dry run. |

- **The password is encrypted** in its row and never rendered back into the
  form. Leave the box empty to keep the one already saved; removing it is a
  deliberate tick. It is also the one setting kept out of the shared settings
  cache, because that cache is the database and the plaintext would land in it.
- **Choosing anything but SMTP drops the stored password**, since a password
  kept for a server nobody sends through is only a liability.
- **A test message** can be sent from the same screen against the settings as
  saved. It is sent immediately rather than queued — a queued test would report
  success and fail later in the worker — and the mail server's own error is
  shown, so a wrong password is found here rather than at two in the morning.
- **A queue worker picks up a change on its next job**, not on the next
  deployment: the settings are re-applied before each one.
- The line under the sender fields always states what is actually in force, so
  there is no guessing whether `.env` or the screen won.

Leaving the choice on the environment file is still the right answer for a
production box managed by deployment — this exists so an administrator without
shell access can change providers.

### Nothing reaches a real person outside production

A staging server is usually seeded from a copy of the live database. Testing an
approval on it would email the employee it names. So **in every environment
except production, every message goes to one inbox instead**:

```dotenv
MAIL_REDIRECT_ALL_TO=api@shrigodatechlabs.com
MAIL_REDIRECT_ALL_NAME="HRMS test inbox"
```

- **Production ignores it entirely.** The guard is in `config/mail.php`, not in
  the deployment, so leaving the address set in a production `.env` by mistake
  still cannot divert live mail. Unsetting it is the belt; `APP_ENV=production`
  is the braces.
- **The real recipients travel with the message** as `X-Original-To`,
  `X-Original-Cc` and `X-Original-Bcc` headers, which is the first thing you want
  to know when a test message lands in a shared inbox. Each redirect is logged
  with the subject and who it would have gone to.
- **Copies are dropped rather than redirected**, so one test message does not
  arrive three times.
- **It is visible.** The notification console and **Settings → Email** both say
  so, because a redirect nobody can see is worse than no redirect — the point of
  testing is knowing what actually happened.
- The test suite is exempt (`MAIL_REDIRECT_ALL_TO=""` in `phpunit.xml`), since
  it asserts the real recipient of each message.

### Writing a message

Wherever the system takes a long message — a notification's wording, an
announcement, a letter's draft, a help guide — the field is a **rich text
editor** over Markdown. The toolbar
carries bold, italic, strikethrough and inline code; three heading levels and a
plain-paragraph button; bulleted, numbered and **tick-box** lists; quotes, fenced
code and a divider; links, unlinking and **tables** (with row and column
controls that appear when the cursor is inside one); clear formatting, undo and
redo.

Two things are deliberate:

- **It writes Markdown, not HTML.** The textarea underneath stays the real
  field, so the same text renders on screen, in the email and in the plain-text
  fallback, and it stays readable if you ever edit it by hand. Turn JavaScript
  off and you get that textarea.
- **Underline is not offered.** Markdown has no syntax for it, so a button would
  lose the formatting the moment you saved. Everything on the toolbar survives
  the round trip.

Raw HTML typed or pasted into one of these fields is escaped, never run.

One difference between the fields is deliberate. In a **letter**, an
**announcement** and a **notification**, a line you put on its own line stays
there — a block of details reads as a block. In a **help guide** a paragraph
wrapped across several lines flows into one paragraph, because a guide is prose.
The editor is told which of the two it is sitting on, so what you type is what
gets read either way.

### Notifications, managed from the interface

Every message the system can send is listed under **Administration →
Notifications**, and nothing about it is hard-coded in a template file. For each
one you can:

- switch **email** or the **notification bell** on or off independently,
- reword the subject, the message, the button text, and the short title and
  line that appear in the bell,
- drop in placeholders such as `{{ employee_name }}` or `{{ period }}` by
  clicking them,
- watch a preview built from example details update as you type,
- send yourself a test of the wording currently on screen, and
- restore the wording the application ships with.

The messages are these:

| Notification | Goes to | Email | In app |
| --- | --- | :---: | :---: |
| Welcome a new employee | The employee, with their sign-in details | ● | |
| Account created | The account holder, with a temporary password | ● | |
| Password reset by an administrator | The account holder | ● | |
| Forgotten password link | Whoever asked for the reset | ● | |
| Verification requested | The new joiner, with the checklist | ● | ● |
| Verification submitted | HR, when a joiner has provided everything | ● | ● |
| More needed from the employee | The joiner, listing only what to redo | ● | ● |
| Verification cleared | The joiner, confirming their onboarding | ● | ● |
| Leave applied for | The line manager and the branch manager | ● | ● |
| Leave approved | The employee, with the approver's remarks | ● | ● |
| Leave rejected | The employee, with the reason given | ● | ● |
| Leave cancelled | The approvers | ● | ● |
| Attendance correction requested | The approver | ● | ● |
| Attendance correction decided | The employee | ● | ● |
| Salary slip | The employee, with the PDF attached | ● | ● |
| Announcement published | Everyone in its branch and department | ● | ● |

Every email is sent as both HTML and plain text.

**How editing works.** A field left empty keeps the wording the application
ships with, so you can change a subject and leave the body alone; wording that
matches the default is not stored as an edit, which means later improvements to
the shipped copy still reach you. Templates are substituted, never executed:
`{{ ... }}` is replaced by a value and nothing else, and markup typed into a
message is escaped rather than run. A placeholder with no value behind it shows
as a dash. The button on an email always links back into the portal — its
address is set by the system, only its label is yours.

**Adding a new one.** Add an entry to `app/Support/NotificationEvents.php` with
its group, channels, placeholders and default wording, then send it with
`NotificationDispatcher`. The console picks it up with no further work.

**Switching things off.** The master switch in **Settings** stops all outgoing
email whatever the console says; the per-event switches are finer-grained. In-app
notifications are unaffected by the master switch. Note that switching the
forgotten-password email off means people cannot reset their own passwords —
an administrator has to do it for them.

Managing notifications needs the `notifications.manage` permission, which only
Super Admin holds on install. After upgrading an existing installation, run
`php artisan db:seed --class=RolePermissionSeeder` so the new permission exists.

### Queue worker

**Mail is queued, so nothing is delivered until a worker runs.** In production
keep one running under a process supervisor:

```bash
php artisan queue:work --tries=3
```

Because delivery happens in the worker rather than in the web request, a mail
server outage never blocks a leave approval or a payroll run. Failed messages
retry, then land in the `failed_jobs` table and are written to the application
log so a problem is visible where the team already looks. Retry them with
`php artisan queue:retry all`.

For the same reason, a payslip is only marked as emailed once the message has
actually left. Until then the interface reports it as queued for delivery.

---

## Testing

```bash
php artisan test
```

The suite runs against an in-memory SQLite database and covers authentication
and rate limiting, the permission catalogue and branch scoping, attendance
calculation, leave policy and balances, payroll proration, PDF generation, the
notification catalogue and console, the Self Assistance guides, background
verification end to end, multi-company payroll separation, sessions and breaks,
and the API.

---

## Project layout

```
app/
  Enums/          Attendance, leave, payroll and employment status types
  Http/
    Controllers/  Web controllers, plus Api/ for the JSON endpoints
    Middleware/   Active-account, forced password change, activity logging
    Requests/     Form request validation
    Resources/    API response shaping
  Models/         Eloquent models with relationships and scopes
  Policies/       Per-model authorisation, including branch scoping
  Services/       The domain logic: attendance, leave, payroll, notifications
  Support/        Role, permission and notification catalogues, markdown
database/
  migrations/     34 migrations covering the whole schema
  seeders/        Reference data plus an optional demo company
resources/views/
  components/     Reusable Blade UI components
  layouts/        Application and guest shells
  mail/           The shell every managed email is rendered into
  pdf/            The salary slip PDF template
routes/
  web.php         Web routes
  api.php         Versioned JSON API
```

---

## Production notes

[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) walks through a complete Ubuntu 24.04
and nginx installation. The short version:

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Run `php artisan migrate --force` on deploy, then cache the framework: `php artisan config:cache route:cache view:cache`.
- Keep a queue worker running so emails and PDF attachments are delivered.
- Uploaded documents and photos go to `storage/app`; run `php artisan storage:link` so public avatars resolve.
- Employee documents are served through a controller that checks the policy first, so they are never publicly reachable by URL.
- Serve the site over HTTPS. Browsers only offer a location over a secure connection, so on plain HTTP nobody can punch in.
- Back up the database before each payroll run, and back up `storage/app` with it — documents and payslip PDFs live there and nowhere else. Approved and paid runs cannot be deleted from the interface, but the data is only as safe as your backups.

## Licence

MIT.
