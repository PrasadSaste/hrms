# Moving in from another HRMS

Everything needed to leave an old system and start on this one, in the order it
has to happen, with the trade-offs of each way of getting the data across.

---

## Which method

| Method | Good for | What it costs |
| --- | --- | --- |
| **Spreadsheet import** (built in) | Almost everybody. Any old system can export CSV, and any old system's export can be pasted into a template. | An afternoon of tidying columns. |
| **Direct database migration** | 5,000+ employees, or an old system whose export is unusable. | A one-off script written against their schema, and a dump of their database. |
| **The REST API** | Keeping two systems in step over a period, rather than one cutover. | The API is read-only for employee data today; the write endpoints would have to be added. |
| **Typing it in** | Under about twenty people. | An hour, and no file wrangling at all. |

**Use the spreadsheet import unless you have a reason not to.** It is the only
one of the four that validates every row against the same rules the application
itself enforces, tells you what is wrong before anything is written, and can be
re-run safely after you fix the file.

A **direct database migration** is worth it when the export is the problem — an
old system that dumps HTML tables, or one whose attendance history runs to
millions of rows. It means writing a script that reads their tables and calls
the same services the importers do (`EmployeeService::create`,
`AttendanceService::record`, and so on) rather than writing to our tables
directly, so the same rules apply. Ask, and hand over their schema or a dump.

---

## The order

Each file names the ones above it by **code**, so this order is not a
suggestion: an employee row cannot find a branch that has not been loaded yet.

1. **Branches** — the offices. Everything else hangs off these.
2. **Departments** — each belongs to one branch.
3. **Designations** — job titles, organisation-wide.
4. **Employees** — the people, their employment, bank and statutory details.
5. **Leave balances** — what everybody had left, so nobody starts on zero.
6. **Salary structures** — current pay, so payroll can be run.
7. **Attendance history** — optional, and the largest. Load only as far back as
   you need; for payroll that is the current financial year.

Before step 4, set up in the interface the handful of things that are choices
rather than data: your **companies** (the legal entities that employ people),
your **shifts**, your **leave types** and their codes, and your **salary
components** and their codes. The importers point at these by code.

---

## Doing it

**Administration → Data Import.** For each kind of file:

1. **Download the template.** It carries the exact header row and one example.
2. **Paste your export into it** and save as CSV. In Excel: File → Save as →
   CSV UTF-8. In Google Sheets: File → Download → Comma-separated values.
3. **Upload it.** Every row is checked; nothing is written.
4. **Read what came back** — how many rows are ready, and the reason against
   each one that is not. Bad rows download as their own file with a `problem`
   column, ready to fix and upload on their own.
5. **Import.** One transaction: if anything fails part way, the whole file is
   rolled back and nothing is saved.

For a file too large for a browser, or to script the whole thing:

```bash
# Check only — writes nothing.
php artisan hrms:import employees people.csv

# Write it, listing anything skipped.
php artisan hrms:import employees people.csv --commit

# Everything or nothing, and the failures in a file to fix.
php artisan hrms:import employees people.csv --commit --strict --errors=fix-me.csv

# What can be imported, in order.
php artisan hrms:import
```

---

## What the importers do for you

**Column names are matched loosely.** `Employee Code`, `employee_code` and
`EMPLOYEE-CODE` are the same column. Common names from other systems are
recognised too — `Emp ID`, `DOJ`, `Work Email`, `Reports To`, `IFSC`. Each
type's screen lists the alternatives it accepts. Extra columns are ignored, and
optional columns can be deleted from the file entirely.

**Dates are read day first.** `01/04/2023` is the first of April.
`YYYY-MM-DD` is unambiguous and always right. `15-Aug-1992` and `15.08.1992`
also read.

**Times are read in any usual form.** `09:30`, `9:30 AM` and `0930`.

**Amounts keep their separators.** `9,90,000` and `1,200.50` are numbers.

**Yes and no are both.** `yes`, `y`, `true`, `1` and `on` are all true.

**Blank lines and empty rows are skipped**, so a trailing newline is not an
error.

**Re-importing is safe.** A row whose code already exists updates that record
instead of creating a second one. The usual way to fix a bad file is to correct
it and upload it again.

**Nothing is emailed.** Importing employees creates their logins but sends no
welcome messages — six hundred people emailed a password in the middle of a
migration is nobody's idea of a good morning. Send credentials from each
employee's own screen when you are ready.

**The uploaded files are kept.** They live on the private disk under
`storage/app/private/imports`, never anywhere a browser can reach, so a run can
be looked at again months later. They hold employee data, so they belong in the
same backup as everything else — and once the migration is behind you, there is
no harm in clearing the directory.

---

## The files, column by column

The full column reference, with what each one means and an example, is on the
screen for each type — Administration → Data Import → the type. What follows is
what to watch for.

### Branches

`code` is what every later file points at, so use the codes your old system
used if people know them. `working_days` accepts `Mon-Fri`, `1,2,3,4,5` or
`Mon, Wed, Fri`. `latitude` and `longitude` are optional and switch on the
location check for punches at that branch.

### Employees

The one file worth a careful pass before uploading.

- `employee_code` — their code in the old system. Keep it: payslips, letters
  and people's memories all use it. Leave it empty and one is generated.
- `email` is the sign-in name, so it has to be unique. Two rows with the same
  email are caught before anything is written, and the message names both rows.
- `branch_code`, `department_code`, `designation_code`, `shift_code` and
  `company_code` are **codes, not names**.
- `manager_code` may point at somebody further down the file. Reporting lines
  are joined after the whole file is read.
- `date_of_joining` is their **original** joining date, not the migration date.
  Leave entitlements are prorated from it.
- `date_of_exit` on somebody who has already left marks them inactive, which
  keeps them out of payroll and the roster while keeping their history.
- `role` decides what their login can do. Leave the column out and everybody is
  an ordinary employee, which is usually right — promote the few who are not
  afterwards.

### Leave balances

One row per employee per leave type per year. `allocated_days` plus
`carried_forward_days` is what they were entitled to; `used_days` and
`encashed_days` are what has gone. A row that would leave a negative balance is
refused rather than quietly clamped.

Import the **current year** at minimum. Earlier years are only worth loading if
you want the history visible.

### Salary structures

The fixed columns give the CTC, the basic and when the pay started. The pay
itself arrives in columns named after your salary components —
`component:basic`, `component:hra`, `component:pf` — and the template lists the
ones you have. An empty amount means that component is not part of that
person's pay, which is not the same as zero.

Amounts are taken as fixed monthly figures, whatever the component is normally
calculated as: a spreadsheet holds what people are actually paid.

Importing a second structure for somebody closes the first one the day before
the new one starts, so nobody ever has two open structures.

### Attendance history

`employee_code`, `date`, and the times. A day with no times and no status is
recorded as absent; a day with times is recorded as present unless you say
otherwise. Imported days go through the same code a live punch does, so late
minutes, overtime and worked hours all come out the way the reports expect.

A date before somebody joined, or in the future, is refused.

---

## A sensible cutover

**A week before.** Load branches, departments, designations and a handful of
test employees on a copy of the system. Fix whatever the checker complains
about — that is the pass that finds the real problems.

**The day before.** Freeze the old system: no new joiners, no leave approvals,
no attendance edits. Take the final exports.

**Cutover day.**

1. Back up: `mysqldump` the database and copy `storage/app` (see
   [DEPLOYMENT.md](DEPLOYMENT.md)).
2. Load the files in order, reading the check screen each time.
3. Spot-check ten people against the old system: their branch, their manager,
   their leave balance, their salary.
4. Run a payroll **draft** for the current month and compare a few payslips
   with the old system's. Do not approve it — a draft can be deleted.
5. Sign in as an ordinary employee and look at their own screens.

**Then, and only then**, send people their credentials from the employee
screens, and tell them the old system is read-only.

---

## When something goes wrong

**Nothing here deletes.** A wrong import leaves records rather than removing
them. Correct the file and upload it again — the same codes are updated — and
remove anything created in error from its own screen.

**A whole file went in wrong.** Restore the backup you took before the
cutover. This is the reason for the backup, and the reason to import in
separate steps rather than one long afternoon.

**Every row says the branch does not exist.** The column wants the branch
**code**, not its name.

**The file was refused before being read.** It is missing a required column.
The message names it; compare the header row with the template.

**Somebody has two records.** Two rows carried different employee codes for the
same person, or one had no code at all so a new one was generated. Deactivate
the duplicate; do not delete it if any attendance or payslip already points at
it.
