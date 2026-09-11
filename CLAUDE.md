# BySure HRMS

Laravel 13 HRMS. `README.md` says what each module does and how payroll is
calculated; `docs/DEPLOYMENT.md` says how it is installed and run.

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed     # MySQL in production, sqlite for a quick trial
php artisan test
```

## Two rules above the rest

- **Domain logic lives in `app/Services`,** not in controllers. `AttendanceService`,
  `LeaveService` and `PayrollService` own the rules; controllers validate input,
  call a service, render. Put new business rules there.
- **Anything extensible is a catalogue** — a PHP array under `app/Support` that
  the screens, seeders and commands all read, so what an administrator sees and
  what actually runs cannot drift. Add to the catalogue, never to the screen.

| Catalogue | Holds | The rule that matters |
| --- | --- | --- |
| `Permissions.php` | Every permission | Never hard-code a permission string in a Blade view without adding it here first. |
| `NotificationEvents.php` | Every message sent, its channels, placeholders and default wording | Everything goes through `NotificationDispatcher`. Never mail from a controller or add a bespoke mailable — the notification console would not know about it. Admin edits live in `notification_templates`. |
| `Automations.php` | Every scheduled job | `routes/console.php` schedules the catalogue in a loop and lists nothing by hand. A job extends `AutomationCommand`, does its work in `work()` and returns a sentence; the base class records the run in `automation_runs`. Whether a job is off is decided when it runs, so switching it off takes effect that evening. |
| `LetterTypes.php` | What the company can issue | `LetterService::issue()` **freezes** the words, signatory name and signature path onto the `letters` row. Never re-render an old letter from today's template. |
| `ImportTypes.php` | What a spreadsheet can load | Each type has a `RowImporter` in `app/Services/Import`; `DataImportService` checks a file whole before writing a single row. Never write to tables from an importer — call the services the screens call. |
| `SettlementLines.php` | What a full and final can be made of | `SettlementService` computes three of them — the final month, encashment of leave that carries forward, and gratuity under the Act — and every other line is typed in against a named key. The figures **and their bases** are frozen onto the row at approval, like a payslip: a settlement is the arithmetic behind a payment already made. Each line keeps its reasoning in `basis`, because "Gratuity ₹9,23,077" is not checkable and is exactly what gets queried. |
| `AssetTypes.php` | What the company hands out | `AssetService` is the only thing that writes an assignment: an asset is with at most one person at a time, and `assets.status` is derived from its assignments rather than typed in, so "issued" and "who has it" cannot disagree. A handover is a return and an issue, never an edited row. `returnable` decides who a leaver is chased for. |
| `TaxRegimes.php` / `TaxDeductionSections.php` | The slabs, rebates, surcharge bands and cess; and the sections somebody may declare under | Keyed by **financial year**, never by "current": a slip issued last March was taxed under last March's Finance Act. An unknown year falls back to the latest known and sets `assumed`, which the screens print. A section declares which regimes it survives in, and a declaration under a regime that disallows it is still *shown* at zero — that comparison is what the employee is deciding. |
| `BankFormats.php` | The shapes a bank will accept a payment file in | Each layout is a column list drawn from one vocabulary of fields, resolved by `BankFileService::value()`, so a new bank is an entry and nothing else. The file is checked **whole** before a byte is written — one bad row and the bank rejects the upload, days later. Amounts go out unformatted: this is the one place `Money` stays out of. |
| `BackgroundCheckRequirements.php` | What can be asked of a joiner | Uploads go to the private disk and are streamed through `BackgroundCheckController::document()`, never linked publicly. |

Templates in all of these are **substituted, never evaluated**: no Blade, no `eval`.

Help guides are the same idea in the database: `help_articles`, seeded by
`HelpArticleSeeder`, editable from Administration → Self Assistance. A new
screen should come with one — add it to the seeder with its `route_name` and
the permission that guards the screen.

## Payroll and money

- **A statutory deduction is not a percentage.** `SalaryComponent::resolveAmount()`
  applies, in order: `eligibility_ceiling` (above it the component is zero —
  state insurance), then a `slab` table (professional tax), then `wage_ceiling`
  capping the base before the percentage (provident fund). The rules live on the
  component, not on a payroll run. Slabs are stored lowest ceiling first and read
  first-match; a null ceiling is the top band. The controller sorts them on save.
- **Three traps in settlement arithmetic, each of which was live.** A full final
  month paid at the twenty-six day rate pays 30/26 of a salary — that divisor
  comes from the Gratuity Act's definition of a month's wages and belongs to
  gratuity, encashment and notice, not to prorating a month. Length of service
  is compared **unrounded**: rounded to two places, four years and 364 days
  becomes 5.00 and grants a statutory payment a day early. And a per-day rate is
  rounded **before** it is multiplied, so the statement adds up when somebody
  checks it with a calculator.

- **Money is written in one place.** `App\Support\Money` groups rupees the Indian
  way (₹9,90,000.00) and spells them in lakh and crore; other currencies keep
  Western grouping. Use `Money::withSymbol()` or `x-money`, never `number_format`,
  so a payslip and a letter about the same salary cannot disagree. Raw CSV
  exports are the exception and keep unformatted numbers.
- **Overtime is optional and starts off.** `OvertimeService` is the only thing
  that turns `attendances.overtime_minutes` into money, and does nothing until
  `payroll_overtime_enabled` is set. The rate is derived from the month — the
  ordinary monthly wage (basic or gross, **before** loss of pay, never including
  overtime) over the month's own working days × hours in a day, times a
  multiplier. The line is handed to `resolveComponentAmounts()` as an extra
  earning rather than appended after, so it joins gross **before** the deductions
  resolve: state insurance is due on overtime. Hours and rate are frozen onto the
  payslip.
- **Income tax is projection, not a percentage.** `IncomeTaxService` estimates
  the whole year, applies the declared (or HR-verified) deductions, taxes it
  under `TaxRegimes`, takes off what has already been deducted and spreads the
  rest over the months that remain — which is why a rise in October changes
  every remaining slip. It returns **the working, not a number**: the screens
  and the statement render that array, because "your tax is ₹1,04,000" is not
  checkable and is exactly what gets queried. Nothing chooses a regime; both are
  computed and the employee decides. `TaxWithholdingService` decides only
  *whether* to ask, is **off by default**, and resolves `IncomeTaxService`
  lazily — that one needs `PayrollService`, so a constructor dependency would
  close the loop. A computed TDS line **replaces** the structure's flat-rate TDS
  component rather than joining it, and replaces it even at zero.
- **A company is the employer, a branch is the place.** Every employee, payroll
  run and payslip carries a `company_id`; the slip's own company — not global
  settings — supplies its letterhead. Only fall back to `Setting::get('company_*')`
  for slips that predate this.

## Attendance and leave

- **The working week has one arbiter:** `WorkCalendar::isWorkingDay()`, including
  a branch's alternate-Saturday pattern (`branches.saturday_offs`). Leave day
  counting, payroll working days, the roster and every calendar ask it rather
  than reading `working_days` themselves.
- **A day is sessions and breaks.** `attendance_sessions` holds each punch pair,
  `attendance_breaks` each break; the `attendances` row is a summary rebuilt by
  `AttendanceService::rebuild()`. Never write `check_in`/`check_out` directly —
  write a session and rebuild, as `record()` does.
- **Leave is granted yearly or earned monthly.** A monthly type grants nothing up
  front: `LeaveAccrualService` writes one `leave_accruals` row per person per
  month and adds it to the allocation, which is why the nightly job is safe to
  repeat and why a balance can be explained. Never recalculate `allocated_days`
  for a monthly type — an imported opening balance lives there.
- **A forgotten punch-out costs hours, never pay.** A day is counted present on
  the **check-in**, and while it is open it is judged on arrival alone — so the
  salary is safe. But `duration_minutes` is only written when a session closes,
  so an abandoned punch reports nought hours for ever.
  `AttendanceService::closeAbandonedSessions()` closes yesterday's at the
  **shift's end** — never at the hour the job runs, never before the punch
  itself — and sets `needs_correction`, which is the deliverable: a wrong number
  somebody is asked to fix beats a quiet zero. **The switch is checked in the
  service, not only in the command**, because writing a punch-out nobody made is
  what an organisation opts into. Only `record()` clears the flag.
- **A branch has a place on the map.** `GeofenceService` measures each punch
  against `branches.latitude/longitude` and freezes the distance and verdict onto
  the session. A punch is never refused for being far away — it is recorded,
  flagged and reported. Alert recipients fall back to HR and administrators so an
  alert is never sent nowhere.
- **Three settings change arithmetic, not wording.**
  `AttendanceService::selfPunchAllowed()` is read by `AttendancePolicy::punch()`,
  so one switch closes the web form, the API and the buttons together.
  `autoAbsent()` decides whether an unrecorded past working day is `Absent` or
  `NotMarked`; the latter lands in `totals['unmarked_days']` and is *paid* by
  `PayrollService`. `Support\FinancialYear` drives the reports only — leave years
  stay calendar years.

## Documents and branding

- **Every generated document shares one pad:** `resources/views/pdf/partials/`
  (`pad`, `pad-styles`, `watermark`, `foot`). Payslips and letters include them
  rather than drawing their own headers, so a new document type gets the
  letterhead for free. `Letterhead::forCompany()` is the only source of what goes
  on it. A company with no logo prints its name — never another entity's — so
  `logoDataUri()` takes `fallBackToGroup: false` for a company, and falls back
  only for a document with no company at all. The watermark is CSS
  (`position: fixed` plus a rotation), which DomPDF repeats on every page.
- **A signatory is a person, not a field.** `signatories` holds who may sign for a
  company; the `companies.signatory_name` columns survive only as the fallback
  for an installation that predates the list. Specimen signatures live on the
  private disk, streamed through `SignatoryController::signature()`.
- **A link uses the ink, never the 600.** The 600 step is the colour an
  administrator chose *to press* — it has to look right filling a button, and
  nothing makes it readable as small text: a teal measures 3.4:1 against the
  page where 4.5 is the floor, a yellow 1.4:1. `BrandPalette::ink()` walks the
  same hue darker until it clears, leaves a colour that already passes alone,
  and emits `--color-{token}-ink`. Use `.link` (underlined) or `.link-plain`,
  and `.hover-ink` where a row title turns brand on hover. **Colour is never
  the only signal** — a dark navy clears contrast easily and then sits 1.2:1
  from the body text beside it, legible and not recognisable as a link.
- **The theme is three colours and a surface.** `BrandPalette` derives ten shades
  from each of `brand_color`, `brand_secondary_color` and `brand_tertiary_color`
  and emits `--color-brand-*`, `--color-accent-*`, `--color-tertiary-*` plus the
  `--surface-*` variables the shell paints itself with. Add a colour by adding to
  `BrandPalette::ROLES`. The shell uses variables rather than utility classes
  because the navigation column can be light or dark and one set of classes
  cannot be both. **`resources/js/bootstrap.js` mirrors the mix and the surface
  rules for the live preview — **including `inkFor()`, which mirrors
  `BrandPalette::ink()`. Change one and you must change both.**

## Mail

- **The transport can come from settings.** `MailSettings::apply()` lays the
  stored transport over `config('mail.*')`, called from `applyStoredSettings()`
  on every request and from `ResetMailerBeforeJob` before every queued job, so a
  long-running worker picks up a change. Left on `MailSettings::FROM_ENV` nothing
  is overridden. The SMTP password is a `Setting::SECRET`: encrypted in its
  column, excluded from `allValues()` so plaintext never reaches the cache, read
  only through `Setting::secret()`.
- **Nothing reaches a real person outside production.** `RedirectOutgoingMail`
  listens on `MessageSending` and diverts every message to
  `config('mail.redirect.to')`, keeping real recipients as `X-Original-*` headers.
  The production guard is in `config/mail.php`, not in the deployment, so an
  address left in a production `.env` still cannot divert live mail. The suite is
  exempt via `phpunit.xml`; a test that needs the redirect sets it itself.

## Charts

- **Series colours are fixed, not the brand colour.** `resources/css/app.css`
  holds three validated categorical slots used in a fixed order. An
  administrator may set `brand_color` to anything, and a pair chosen at runtime
  cannot be checked for the separation two series need — so the brand hue is
  used only where there is **one** series and identity is not at stake. A fourth
  series folds into "Other"; it never becomes a fourth hue.
- **Status is not a series.** Pending, approved, declined and withdrawn wear the
  reserved status colours and **always** ship an icon and a written label beside
  the count. Approved-green against declined-red measures 4.1 apart under
  deuteranopia: the colour is the last channel, never the only one.
- **Every chart carries its figures.** `<x-chart.*>` renders a `See the figures`
  table underneath, which is both the accessibility path and the relief the
  contrast rule requires for the lighter series. Nothing is gated behind
  hovering.
- **Label selectively.** One value on the tallest column, values at every bar
  tip, nothing on a line except its end. A number on every point goes unread.
- Marks follow one spec: columns capped at 24px with a 4px rounded data-end,
  2px lines, an 8px end dot with a 2px surface ring, a 2px surface gap between
  stacked segments, and hairline recessive axes.

## How it looks

The language is an **operations console**, not a website: this is read all day
by people doing payroll, so density and legibility beat generosity.

- **A register is a grid.** `.table` rules its columns, bands its rows and
  tints its header, and **a cell does not wrap** — one long designation turning
  every row three lines tall destroys the scan down a column, so a wide table
  scrolls sideways inside `.table-wrap` instead. Opt a genuinely prose cell
  back in with `class="wrap"`.
- **A figure column is `.num`** — right-aligned, tabular figures, so decimal
  points line up down the column. That one detail is most of what separates a
  payroll register from a web page.
- **Every page opens with `.page-chrome`,** a band spanning the content area
  with a brand hairline along its top: breadcrumb, title, `actions`, an
  optional `meta` strip of key facts and optional `tabs` docked to its lower
  edge. A record page puts its identity **in the band**, never in a card
  repeating the title underneath.
- **Filters attach to what they filter.** `<x-toolbar>` inside the panel, or
  `<x-filter-bar>` which wears the same clothes directly above it. The toolbar
  sizes its own controls: **that rule is deliberately outside every `@layer`,**
  because cascade layers beat specificity outright and a field component's
  `w-full` sits in the later `utilities` layer — no rule inside `@layer
  components` can win, however specific.
- **Panels, not floating cards.** `.card` is square-ish and led by its border.
  Section labels are `.eyebrow`: small caps, letterspaced.
- **`.page-tab` must light up on both `.is-active` and
  `[aria-selected="true"]`** — the tabs component toggles the attribute from
  script, a link in the band carries the class, and styling only one leaves the
  highlight behind when somebody clicks.

## Messages and questions

- **Nothing uses the browser's own `alert()` or `confirm()`.** `resources/js/toast.js`
  owns both jobs: `showToast(message, type)` says something, and
  `showConfirmToast(message)` asks something and resolves `true` or `false`.
  Both are on `window.hrms` for a view that is not part of the bundle, and
  `ToastTest` fails the build if a native dialog reappears anywhere under
  `resources/`.
- **A destructive button carries `data-confirm`,** and the delegated handler in
  `bootstrap.js` does the rest. Because the question no longer blocks, the click
  is stopped and replayed: `form.requestSubmit(button)` with the button as the
  submitter, so its `name`, `value` and `formaction` still decide what the
  server is asked to do. Never mark anything inside the toaster itself with
  `data-confirm` — the handler would treat answering as an action needing
  confirmation and swallow the answer.
- **A question is never trimmed.** The stack drops its oldest toast past four,
  but a confirmation is exempt: something is awaiting its promise, and removing
  it would leave a delete hanging for ever. Anything that does remove one
  settles it `false` on the way out.
- **Flashed messages are toasts, not banners.** `<x-toaster />` in the layout
  reads `session('success'|'error'|'warning'|'info'|'status')`, the skipped-rows
  report and the validation summary, and hands them to the page as JSON rather
  than inline script. `<x-flash />` still exists but draws nothing, so no screen
  had to change. An `<x-alert>` that is *page content* — the warning that
  somebody still holds a laptop — stays where it is; that is not a notification.

- **A form submits from `<x-form-actions>`,** which is `position: sticky;
  bottom: 0` and nothing cleverer. While there is still form below it the bar
  floats at the foot of the screen so Save is always one click away; once the
  end of the form scrolls into view it settles there. No measuring, no
  JavaScript deciding, nothing to go wrong when a section expands or the window
  is resized — the only script involved adds a shadow while it floats. A form
  whose Save already stays in view inside a sticky side column (settings,
  branches, the help guides) is left alone.

## The rich text editor

- **It writes Markdown, not HTML.** `x-rich-text` puts a TipTap toolbar over a
  plain Markdown textarea, which stays the source of truth. Only offer a button
  for something GitHub-flavoured Markdown carries and `Support\Markdown` renders
  — underline is left out for that reason. Adding a block means adding its styles
  to **both** `.rich-text-content` (the editor) and `.prose-preview` /
  `.prose-letter` / `.prose-help` (the reader), or it renders unstyled.
- **It must be told how its field is rendered.** Pass `hard-breaks` when the
  reader sees it through `Markdown::html($x, hardBreaks: true)` — letters,
  announcements, notifications — and leave it off where a wrapped paragraph flows,
  as in a help guide. The editor turns every newline into a break and no parser
  option changes that, so the difference is made either side: `flowSoftBreaks()`
  joins wrapped prose on the way in, `toMarkdown()` drops Markdown's two-space
  hard break on the way out. Get it wrong and a template merely opened and saved
  comes back reformatted.

## Setup and access

- **Setup is a wizard; its lock is a file.** `Installer` owns every step, so the
  four screens in `Install\InstallController` and `hrms:install` reach the same
  state by the same route. `storage/installed.json` is what closes it, not a row:
  a restored backup must never reopen setup on a live server. A system with a
  super admin and no lock file predates the wizard and locks itself rather than
  showing a setup screen. `EnsureInstalled` sits **ahead of the authentication
  check in the middleware priority list** — appending is not enough.
  `InstallServiceProvider` makes an unconfigured clone bootable at all, and does
  none of it once the lock exists. `SystemRequirements` separates what the
  software will not start without from what is merely worth having.
- **A second factor is TOTP, written out rather than pulled in.** RFC 6238 is a
  page of arithmetic that publishes test vectors, so `TwoFactorService` holds it
  and `TwoFactorTest` pins it against the RFC's own numbers — a library can only
  be checked against its agreement with itself. A window of one step either
  side; a code is refused if its timestep has already been accepted, or one read
  over a shoulder stays good for half a minute; recovery codes are hashed,
  single use and shown exactly once.
- **`RequireTwoFactor` lets a different set of routes through in each state,
  and that *is* the security.** Somebody enrolled but unchallenged may reach the
  challenge and nothing else — **not the setup screen**, which can turn the
  factor off behind a password, and the attacker here is precisely somebody
  holding a stolen password. Somebody required but not enrolled may reach setup
  and nothing else. Signing out is allowed from both, because a person who
  cannot leave a screen they cannot pass asks for the feature to be removed.
- **Authorisation is policy-driven.** Policies in `app/Policies`, registered in
  `AppServiceProvider`. A super admin bypasses every check via `Gate::before`,
  with a deliberate exception for self-deletion.
- **Branch scoping** happens in `Employee::scopeVisibleTo()` and the policies'
  `sharesScope()` helpers. A branch manager must never see another branch.

## Conventions worth keeping

- Date-only columns cast as `date:Y-m-d`. Anything else stores a time component
  and breaks equality and range queries on SQLite while looking fine on MySQL.
- Money is `decimal(n, 2)` in the database, cast to `float` in models. Round to
  two places whenever you compute a component amount.
- Mailables implement `ShouldQueue`, so assert with `Mail::assertQueued`, not
  `assertSent`. `NotificationService::mail()` logs delivery failures rather than
  throwing, so an SMTP outage cannot block an approval.
- **Three Blade traps, each of which has broken a page here.** `$component` is
  reserved by Blade's component runtime — never a loop variable in a view that
  renders components. `$lines` is what `pdf/partials/foot` reads, and a loop
  variable of that name leaks into the include and prints an array. And a
  directive is only recognised when a **non-word character precedes it**:
  `}}h@if`, `above@endif` and the like compile to literal text and take the
  whole block down with them — build the string in `@php` instead.

## Testing

`php artisan test` runs against in-memory SQLite. `tests/TestCase.php` provides
`makeEmployee($role, $attributes)`, which creates a branch, department,
designation, shift, user and employee in one call. Freeze time with
`Carbon::setTestNow()` in any test touching attendance or leave.

## Verifying in a browser

Some things only a browser can prove: a new screen, a multi-step form, a
generated PDF, anything with JavaScript in it. **Do it when the change is one of
those, and do not skip it to save time.** The setup wizard would have shipped
broken three times over without it — an empty database name that killed the
migration, a finish page lost when the session driver flips from file to
database, and a duplicate company left behind by a migration's backfill. None of
the three showed up in the suite.

Be economical about what comes *back*, not about whether to look:

- `playwright-core` is installed; `playwright` is not. Launch with
  `chromium.launch({ executablePath: '/opt/pw-browsers/chromium' })`, and keep
  the script in the project root so node resolves `node_modules`.
- Have the script `console.log` its assertions — the URL reached, the text
  found, the count. A screenshot costs far more to read back than a line of
  text, so read one when the *look* is the question, not when a string answers it.
- Write screenshots to the scratchpad and hand them over with `SendUserFile`
  rather than reading every one into context.
- Faking a fresh deployment means moving `.env` aside and deleting
  `storage/installed.json` — **and checking for a stray
  `database/database.sqlite`**, which is gitignored, holds a real administrator,
  and will make the system correctly report itself as already installed.
