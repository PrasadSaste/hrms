# Tech stack

Everything the application is built from, and why each piece is there. Versions
are the ones the project is developed and tested against; the constraint in
brackets is what `composer.json` or `package.json` actually requires.

---

## Runtime

| | Version | Notes |
| --- | --- | --- |
| **PHP** | 8.4 (requires `^8.3`) | Needs `mbstring`, `intl`, `bcmath`, `xml`, `curl`, `zip`, `gd`, `pdo_mysql`. `intl` is not optional — net pay is written out in words with `NumberFormatter`. |
| **Laravel** | 13.30 (`^13.17`) | Application framework. |
| **MySQL** | 8.0, or MariaDB 10.6+ | The supported production database. Create the schema as `utf8mb4` / `utf8mb4_unicode_ci`. |
| **Node** | 22 LTS | Build-time only. The server never runs Node — it serves the files Vite produced. |
| **nginx** | 1.24 (Ubuntu 24.04 default) | See [DEPLOYMENT.md](DEPLOYMENT.md). |

SQLite is supported for a throwaway local trial and is what the test suite runs
on, in memory. It is not a production target.

## PHP packages

| Package | Version | What it does here |
| --- | --- | --- |
| `laravel/framework` | 13.30 | — |
| `laravel/sanctum` | 4.3 | Bearer tokens for the REST API. Tokens expire after 30 days. |
| `spatie/laravel-permission` | 8.3 | The 70-permission catalogue and five roles. |
| `barryvdh/laravel-dompdf` | 3.1 | Salary slip PDFs. Renders offline, which is why the logo is embedded as a data URI and SVG logos are skipped. |
| `laravel/tinker` | 3.0 | REPL, used by the maintenance commands in the deployment guide. |

Development only: `laravel/pint` (formatting), `phpunit/phpunit` 12.5,
`fakerphp/faker`, `mockery/mockery`, `nunomaduro/collision`, `laravel/pail`.

## Front end

No SPA framework. Server-rendered Blade with a small amount of vanilla
JavaScript, which keeps the whole application inside one deployable artefact.

| Package | Version | What it does here |
| --- | --- | --- |
| `tailwindcss` + `@tailwindcss/vite` | 4.x | Styling. The brand colour is applied at runtime through CSS custom properties, so changing it in Settings re-themes everything with no rebuild. |
| `vite` | 8.x | Asset build. |
| `laravel-vite-plugin` | 3.x | Manifest and hot reload. |
| `@tiptap/*` | 3.31 | The rich text editor behind `x-rich-text`, on the notification wording, letter wording, announcement and help guide screens. Core, starter kit, markdown, plus the table and list extensions. Loaded by dynamic import **only** on pages that carry one — the bundle every other page downloads is about 14 KB. |

`resources/js/bootstrap.js` holds the interface behaviour: the sidebar drawer,
dropdown menus, dialogs, tabs, the punch forms' geolocation, the live clock and
working-time counter, form repeaters. Each feature starts inside its own guard,
so a failure in one cannot take the navigation down with it.

## Infrastructure this expects

| Concern | Default | Why |
| --- | --- | --- |
| Queue | `database` | Mail is queued, so a mail outage never blocks an approval or a payroll run. **A worker must be running or no email is ever sent.** |
| Cache | `database` | Settings, the resolved notification templates and the default company are cached and busted on save. Redis works if you have it. |
| Sessions | `database` | Survives a restart, and works unchanged behind more than one web server. |
| Filesystem | `local` (private) | Identity documents, experience letters and salary slip PDFs live off the public disk and are streamed through the application. Logos and photographs use the `public` disk. |
| Mail | SMTP | Amazon SES in production. `ResetMailerBeforeJob` drops the resolved mailer before each queued job, because SES closes an idle SMTP connection well before Symfony would notice. |
| Scheduler | cron, every minute | `routes/console.php` holds the schedule: the evening report of out-of-range punches, and the overnight crediting of monthly leave. **Without the cron entry neither fires.** |

---

## How the code is organised

```
app/
  Enums/          Attendance, leave, payroll and employment status types
  Http/
    Controllers/  Web controllers, plus Api/ for the JSON endpoints
    Middleware/   Active account, forced password change, verification gate,
                  activity logging
    Requests/     Form request validation
    Resources/    API response shaping
  Listeners/      Payslip delivery stamping, failed-job logging, mailer reset
  Models/         Eloquent models with their relationships and scopes
  Policies/       Per-model authorisation, including branch scoping
  Services/       The domain logic, with Import/ for the spreadsheet loaders
  Support/        The catalogues: permissions, notifications, break reasons,
                  background check requirements, markdown
database/
  migrations/     41 migrations covering the whole schema
  seeders/        Reference data, the guides, and an optional demo company
resources/views/
  components/     Reusable Blade components
  layouts/        Application and guest shells
  mail/           The shell every managed email is rendered into
  pdf/            The salary slip and letter templates, over one shared
                  letterhead pad in pdf/partials/
routes/
  web.php         Web routes
  api.php         Versioned JSON API
```

### The rules worth knowing before you change anything

These are the decisions the codebase leans on. Breaking one tends to break
something a long way from where you are working.

**Domain logic lives in `app/Services`, not in controllers.** `AttendanceService`,
`LeaveService`, `PayrollService`, `BackgroundCheckService` own the rules;
controllers validate input, call a service and render.

**The catalogues are the source of truth.** Permissions
(`Support/Permissions.php`), notifications (`Support/NotificationEvents.php`),
break reasons (`Support/BreakReasons.php`), verification requirements
(`Support/BackgroundCheckRequirements.php`), what can be imported
(`Support/ImportTypes.php`) and the letters that can be issued
(`Support/LetterTypes.php`) are declared in code and read everywhere. Adding an
entry is enough to make it appear in the interface.

**One class writes down an amount.** `Support/Money.php` groups rupee digits the
Indian way and spells them in lakh and crore; the `x-money` component, the PDFs,
the letters and the notification placeholders all call it, so no two screens can
disagree about the same figure. Raw CSV exports deliberately keep unformatted
numbers.

**A document's signature is frozen, like its words.** `signatories` lists who may
sign for a company with one marked default; `LetterService::issue()` copies the
name, title and signature path onto the letter, and nothing re-reads the
signatory afterwards.

**Leave is earned or granted, never both.** A type set to accrue monthly grants
nothing up front; its balance is the running total of `leave_accruals`, one row
per person per month, which is what makes the nightly job idempotent and a
balance explicable. `LeaveService::proratedEntitlement()` returns zero for those
types deliberately.

**One method decides whether a day is worked.** `WorkCalendar::isWorkingDay()`
is the choke point for the weekly off, the alternate-Saturday pattern and
everything downstream — leave day counting, payroll working days, the roster,
the calendars. Add a rule there and it reaches all of them.

**A day is sessions and breaks.** `attendance_sessions` holds each punch pair
and `attendance_breaks` each break. The `attendances` row is a *summary* rebuilt
from them by `AttendanceService::rebuild()`, because payroll and every report
read it. Never write `check_in`/`check_out` directly.

**A company is the employer, a branch is the place.** Every employee, payroll
run and payslip carries a `company_id`. Payroll is generated per company, and
the slip's own company supplies the letterhead.

**A document keeps its own words.** A payslip carries its company; a letter
carries the wording it was issued with. Neither is re-derived from today's
settings or today's template, because somebody is holding a printed copy of what
was sent.

**Money and dates.** Money is `decimal(n, 2)` in the database, cast to float,
rounded to two places on every computed component. Date-only columns cast as
`date:Y-m-d` — anything else stores a time component and breaks equality and
range queries on SQLite while looking fine on MySQL.

**Notifications are never sent from a controller.** Everything goes through
`NotificationDispatcher`, so the console can switch it off or reword it.
Templates are substituted, never evaluated: no Blade, no `eval`.

**Authorisation is policy-driven.** Policies in `app/Policies`, registered in
`AppServiceProvider`. A super admin bypasses every check via `Gate::before`,
with a deliberate exception for self-deletion. Branch scoping happens in
`Employee::scopeVisibleTo()` and the policies' `sharesScope()` helpers.

---

## Testing

```bash
php artisan test          # 266 tests, in-memory SQLite
vendor/bin/pint           # format
vendor/bin/pint --test    # check formatting without writing
```

`tests/TestCase.php` provides `makeEmployee($role, $attributes)`, which creates
a company, branch, department, designation, shift, user and employee in one
call. Freeze time with `Carbon::setTestNow()` in any test touching attendance or
leave. Mailables implement `ShouldQueue`, so assert with `Mail::assertQueued`,
not `assertSent`.
