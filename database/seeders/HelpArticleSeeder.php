<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

/**
 * A guide for every screen in the HRMS.
 *
 * Re-running this restores the shipped wording for any guide whose slug is
 * listed here, and leaves guides a company has written themselves alone.
 */
class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->articles() as $position => $article) {
            HelpArticle::updateOrCreate(
                ['slug' => $article['slug']],
                array_merge($article, ['position' => $position]),
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function articles(): array
    {
        return [
            // ------------------------------------------------ getting started
            [
                'slug' => 'dashboard',
                'group' => 'Getting started',
                'title' => 'Dashboard',
                'icon' => 'home',
                'route_name' => 'dashboard',
                'summary' => 'What the figures on the landing screen count, and what to do with them.',
                'body' => <<<'MD'
                    The first screen after signing in, and a summary of the whole system: who is
                    in today, what is waiting on someone, and where the month's payroll has got to.

                    Everything here is a shortcut. Nothing is edited on this page — each card
                    leads to the screen where the work is done. What you see depends on your role:
                    an employee sees their own day, a manager sees their team, and an administrator
                    sees the whole organisation.
                    MD,
                'fields' => [
                    ['term' => 'Headcount', 'description' => 'Active employees, counted across every branch you can see. People who have been offboarded are not included.'],
                    ['term' => 'Present today', 'description' => 'Employees who have checked in today, as a share of headcount. It moves through the morning as people punch in.'],
                    ['term' => 'On leave', 'description' => 'Approved leave that covers today. Requests still waiting for a decision are not counted here.'],
                    ['term' => 'Pending approvals', 'description' => 'Leave requests and attendance corrections waiting on you. If it shows a number, someone is waiting.'],
                ],
                'tasks' => [
                    ['question' => 'How do I check in or out?', 'answer' => 'Use the **Check in** button in the header. Your browser will ask permission to share your location the first time — that is expected, and the punch is refused without it.'],
                    ['question' => 'How do I see only my own branch?', 'answer' => "You already are. The dashboard only counts branches your role can see; a branch manager never sees another branch's numbers."],
                ],
                'troubleshooting' => [
                    ['problem' => 'The figures look out of date.', 'answer' => 'They are counted when the page loads. Refresh the page rather than waiting.'],
                    ['problem' => 'A card is missing.', 'answer' => 'Cards follow permissions. If you cannot see payroll figures, your role does not include payroll.'],
                ],
            ],

            // -------------------------------------------------- my workspace
            [
                'slug' => 'my-attendance',
                'group' => 'My workspace',
                'title' => 'My attendance',
                'icon' => 'clock',
                'route_name' => 'attendance.index',
                'permission' => 'attendance.view-own',
                'summary' => 'Your own check-ins, hours and any days that need correcting.',
                'body' => <<<'MD'
                    Every day you have worked, with the times you punched in and out, the hours
                    that were counted and how the day was marked.

                    A day is made of **sessions** and **breaks**. Punch in when you start, punch
                    out when you leave, and do it as many times as the day needs — out to a client
                    at eleven, back at three, out again at five. Start a break when you step away
                    and say what it is for; the working-time counter pauses until you end it.
                    MD,
                'fields' => [
                    ['term' => 'Status', 'description' => 'Present, absent, half day, on leave or holiday. It is worked out from your shift and your punches, not set by hand.'],
                    ['term' => 'Sessions', 'description' => 'Each punch in and the punch out that closed it. A day can hold as many as you need.'],
                    ['term' => 'Breaks', 'description' => 'Time away from work with the reason you gave. Every break is taken off your working time.'],
                    ['term' => 'Worked hours', 'description' => 'All your sessions added up, less the breaks. If you never use the break button, the unpaid break on your shift is deducted instead.'],
                    ['term' => 'Late by', 'description' => 'How far past your shift start you checked in, after the grace period. Blank means you were on time.'],
                ],
                'tasks' => [
                    ['question' => 'How do I record a client visit in the middle of the day?', 'answer' => 'Two ways, and both are fine. **Start break** with *Client visit* if you are staying punched in, or **Punch out** when you leave and **Punch in again** when you return — the second is better when you will be gone for hours, because your location is captured each time.'],
                    ['question' => 'How do I take a break?', 'answer' => '**Start break**, choose what it is for, and add a comment if it helps. The counter in the header pauses. Press **End** when you are back.'],
                    ['question' => 'How do I fix a day I forgot to punch out on?', 'answer' => 'Open **Regularisations** and raise a correction for that date with the times you actually worked and a short reason. Your manager approves it, and the day is recalculated.'],
                    ['question' => 'How do I export my attendance?', 'answer' => 'Use **Export** at the top of the screen. It downloads the period you are looking at as a CSV file.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'The punch-in button is not there.', 'answer' => 'You are already punched in — the buttons are **Start break** and **Punch out** instead. If none of them show, your account has no employee record; ask HR to link it.'],
                    ['problem' => 'My working time looks short.', 'answer' => 'Breaks are taken off it. Check the breaks list under the punch card: an hour of lunch is an hour off your working time.'],
                    ['problem' => 'I forgot to end a break.', 'answer' => 'Punching out ends it for you, at the moment you punched out. If that is wrong, raise a correction.'],
                    ['problem' => '"Location permission is required to punch."', 'answer' => 'Your browser blocked the location request. Click the padlock next to the address bar, allow location for this site, and try again. On a phone, check that location services are on for the browser itself.'],
                ],
            ],
            [
                'slug' => 'leave',
                'group' => 'My workspace',
                'title' => 'Leave',
                'icon' => 'calendar',
                'route_name' => 'leave.index',
                'permission' => 'leave.view-own',
                'summary' => 'Applying for leave, and following a request through to a decision.',
                'body' => <<<'MD'
                    Every leave request you can see: your own, and — if you approve for others —
                    the requests waiting on you.

                    A request moves from **pending** to **approved** or **rejected**, and can be
                    **cancelled** by the person who applied. Balance is only deducted when a
                    request is approved, and given back if an approved request is later cancelled.
                    MD,
                'fields' => [
                    ['term' => 'Reference', 'description' => 'The identifier for one request, quoted in every email about it.'],
                    ['term' => 'Working days', 'description' => 'The days actually deducted: weekends and holidays in the range are not counted, and a half day counts as 0.5.'],
                    ['term' => 'Contact while away', 'description' => 'How the team can reach you if something cannot wait. Optional, but managers appreciate it.'],
                ],
                'tasks' => [
                    ['question' => 'How do I apply for leave?', 'answer' => '**Apply for leave**, then pick the type, the dates and whether it is a full or half day. The screen shows the balance you have left before you submit.'],
                    ['question' => 'How do I cancel leave I no longer need?', 'answer' => 'Open the request and use **Cancel**. If it had already been approved, the days go back to your balance and the approver is told.'],
                    ['question' => 'How do I approve someone else\'s leave?', 'answer' => 'Open the request and use **Approve** or **Reject**. Anything you write in the remarks goes to the employee in the email, so say why.'],
                ],
                'troubleshooting' => [
                    ['problem' => '"You do not have enough balance."', 'answer' => 'The days requested exceed what is left for that leave type this year. Check **Leave balance**, or ask HR to review your allocation.'],
                    ['problem' => '"This overlaps an existing request."', 'answer' => 'You already have a request covering one of those dates. Cancel the old one first, or pick different dates.'],
                ],
            ],
            [
                'slug' => 'leave-balance',
                'group' => 'My workspace',
                'title' => 'Leave balance',
                'icon' => 'scale',
                'route_name' => 'leave.balance',
                'permission' => 'leave.view-own',
                'summary' => 'How many days of each type you have left this year.',
                'body' => <<<'MD'
                    One line per leave type, showing what you were allocated for the year, what has
                    been used, and what is left.

                    Balances are per calendar year and per leave type. Unpaid leave has no balance
                    to run out of, so it does not appear here.
                    MD,
                'fields' => [
                    ['term' => 'Allocated', 'description' => 'The days set for you this year, either by policy or by HR for you specifically.'],
                    ['term' => 'Used', 'description' => 'Days on approved requests. Pending requests are not counted until someone decides.'],
                    ['term' => 'Carried forward', 'description' => 'Days brought in from last year, where the leave type allows it.'],
                ],
                'tasks' => [
                    ['question' => 'How do I get more days added?', 'answer' => 'Balances are set by HR under **Leave allocations**. Ask them; you cannot change your own.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'A leave type is missing.', 'answer' => 'You have no allocation for it this year. That usually means the type does not apply to your employment type, or HR has not allocated it yet.'],
                ],
            ],
            [
                'slug' => 'salary-slips',
                'group' => 'My workspace',
                'title' => 'Salary slips',
                'icon' => 'receipt',
                'route_name' => 'payslips.index',
                'permission' => 'payslips.view-own',
                'summary' => 'Reading a salary slip, and downloading it as a PDF.',
                'body' => <<<'MD'
                    Your salary slips, newest first. A slip appears here once the payroll run it
                    belongs to has been approved — not while it is still being prepared.

                    Every slip can be read on screen, printed, or downloaded as a PDF for a bank
                    or a landlord.
                    MD,
                'fields' => [
                    ['term' => 'Gross earnings', 'description' => 'Everything added up before any deduction: basic, allowances and anything one-off.'],
                    ['term' => 'Deductions', 'description' => 'Everything taken off: statutory contributions, tax and any recovery.'],
                    ['term' => 'Net pay', 'description' => 'What actually reaches your account. Gross earnings less deductions.'],
                    ['term' => 'Paid days', 'description' => 'Days you were paid for, out of the working days in the period. Less than the full month usually means unpaid leave or a mid-month start.'],
                ],
                'tasks' => [
                    ['question' => 'How do I download my slip as a PDF?', 'answer' => 'Open the slip and use **Download**. The PDF carries the company logo and is the version to send to a bank.'],
                    ['question' => 'How do I get a slip emailed to me again?', 'answer' => 'Ask payroll to re-send it from the payroll run. Slips are emailed when a run is published.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'A month is missing.', 'answer' => 'The run for that month has not been approved yet, or you were not employed for it.'],
                    ['problem' => 'A figure looks wrong.', 'answer' => 'Contact payroll within seven days of receiving the slip, quoting the slip number.'],
                ],
            ],
            [
                'slug' => 'holidays',
                'group' => 'My workspace',
                'title' => 'Holidays',
                'icon' => 'sun',
                'route_name' => 'holidays.index',
                'permission' => 'holidays.view',
                'summary' => 'The published holiday calendar, and how it affects attendance and leave.',
                'body' => <<<'MD'
                    The holidays declared for the year. A holiday is not a working day: attendance
                    is not expected, and a leave request spanning one does not spend a day of
                    balance on it.

                    Holidays can be set for one branch or for everybody, so two offices can keep
                    different calendars.
                    MD,
                'tasks' => [
                    ['question' => 'How do I add a holiday?', 'answer' => 'You need the holidays permission. Use **Add holiday**, choose the date, and leave the branch empty to apply it everywhere.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'A holiday is not showing for my team.', 'answer' => 'It was probably set for one branch only. Check the branch column.'],
                ],
            ],
            [
                'slug' => 'announcements',
                'group' => 'My workspace',
                'title' => 'Announcements',
                'icon' => 'megaphone',
                'route_name' => 'announcements.index',
                'permission' => 'announcements.view',
                'summary' => 'Posting news to the whole company, one branch or one department.',
                'body' => <<<'MD'
                    Company news. An announcement can go to everybody, or be narrowed to a branch,
                    a department, or both — useful when only one office needs to know.

                    Publishing puts it in the notification bell for its audience. Ticking the email
                    box also sends it as an email, so keep that for things people must not miss.
                    MD,
                'fields' => [
                    ['term' => 'Audience', 'description' => 'Branch and department together. Leaving both empty means everyone.'],
                    ['term' => 'Notify by email', 'description' => 'Sends the announcement as an email as well as putting it in the bell. Off by default.'],
                ],
                'tasks' => [
                    ['question' => 'How do I format an announcement?', 'answer' => 'The body is a rich text editor: headings, bold, lists, tick-boxes, quotes, links and tables are all on the toolbar. What you see is what people read.'],
                    ['question' => 'How do I post to one office only?', 'answer' => 'Choose that branch under audience before publishing. Everyone else will not see it at all.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Nobody received the email.', 'answer' => 'Check that **Notify by email** was ticked, that email is on in Settings, and that the notification is switched on under Administration → Notifications.'],
                ],
            ],

            // ---------------------------------------------------------- people
            [
                'slug' => 'employees',
                'group' => 'People',
                'title' => 'Employees',
                'icon' => 'users',
                'route_name' => 'employees.index',
                'permission' => 'employees.view',
                'summary' => 'Adding people, keeping their records current and offboarding leavers.',
                'body' => <<<'MD'
                    The employee register. Everything else in the system hangs off a record here:
                    attendance, leave balance, salary structure and salary slips.

                    Creating an employee can also create their login and email them their password,
                    so onboarding is one form rather than three.
                    MD,
                'fields' => [
                    ['term' => 'Employee code', 'description' => 'The identifier on every slip and report. Generated from the prefix in Settings, and never reused.'],
                    ['term' => 'Reports to', 'description' => 'Their line manager, who approves their leave and attendance corrections.'],
                    ['term' => 'Employment status', 'description' => 'Probation, permanent, notice or exited. It does not change pay by itself — it is there for policy and reporting.'],
                    ['term' => 'Status', 'description' => 'Active or inactive. An inactive employee keeps their history but cannot sign in and is left out of payroll.'],
                ],
                'tasks' => [
                    ['question' => 'How do I add someone who starts next month?', 'answer' => 'Add them now with their real date of joining. Attendance is only expected from that date, and their first salary slip is prorated automatically.'],
                    ['question' => 'How do I give an existing employee a login?', 'answer' => 'Open their record and use **Create login**. A password is generated and emailed to them, and they choose their own on first sign-in.'],
                    ['question' => 'How do I offboard someone?', 'answer' => 'Use **Offboard** on their record and give the last working day. Their login is disabled and they stop appearing in headcount, but their slips and history stay.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'I cannot see an employee I know exists.', 'answer' => 'Branch managers only see their own branch. If you need to see everybody, you need the "across all branches" permission.'],
                    ['problem' => 'The welcome email did not arrive.', 'answer' => 'Check the address on the record, then that email is on in Settings, and that the queue worker is running — mail is queued, not sent in the request.'],
                ],
            ],

            [
                'slug' => 'companies',
                'group' => 'People',
                'title' => 'Companies',
                'icon' => 'badge',
                'route_name' => 'companies.index',
                'permission' => 'companies.view',
                'summary' => 'The legal entities that employ people and issue salary slips.',
                'body' => <<<'MD'
                    A group often runs several companies — one for the broking business, another
                    for the services arm. Each is a separate employer with its own registration
                    numbers, and a salary slip has to say which one paid.

                    An employee belongs to exactly one company, chosen when they are onboarded.
                    Payroll runs for one company at a time, and the slip carries that company's
                    name, registration numbers and logo.

                    A company is not a branch. A branch is where somebody sits; a company is who
                    employs them. One office can hold staff of two companies, and one company can
                    have staff in five offices.
                    MD,
                'fields' => [
                    ['term' => 'Short name', 'description' => 'What appears in lists and filters, e.g. Beyond Sure.'],
                    ['term' => 'Registered name', 'description' => 'The full legal name printed on the salary slip, e.g. Beyondsure Private Limited.'],
                    ['term' => 'Salary slip prefix', 'description' => 'Slip numbers begin with it, so a slip says which entity issued it without being opened.'],
                    ['term' => 'Default', 'description' => 'The company chosen automatically when nobody picks one. Exactly one company is the default.'],
                    ['term' => 'Letterhead logo', 'description' => 'The logo printed on this company\'s documents, separate from the one shown on screen. Leave it empty to use the screen logo. PNG, JPEG or WebP — the PDF renderer cannot rasterise an SVG.'],
                    ['term' => 'Footer line', 'description' => 'Printed at the foot of every document this company issues: a registered office, a CIN, a licence number.'],
                    ['term' => 'Watermark', 'description' => 'A pale diagonal mark across every page of its documents, so a photocopy is visibly a copy of something. Reads the short name unless you give it other wording.'],
                ],
                'tasks' => [
                    ['question' => 'How do I add another company?', 'answer' => '**Add a company**, give it a name, code and slip prefix, then fill in the registration numbers. Employees can be assigned to it straight away.'],
                    ['question' => 'How do I move someone to another company?', 'answer' => 'Edit the employee and change their payroll company. Salary slips already issued keep pointing at whoever paid them — a transfer never rewrites history.'],
                    ['question' => 'How do I give one company its own letterhead?', 'answer' => 'Open the company and fill in **Document pad**: a letterhead logo, a footer line and the watermark. Every salary slip and letter it issues is printed on that pad.'],
                    ['question' => 'Our second company is printing the parent\'s logo.', 'answer' => 'It is not — a document never borrows another entity\'s logo. A company with none prints its name instead. Upload a letterhead logo on that company to change it.'],
                    ['question' => 'How do I stop the watermark on our payslips?', 'answer' => 'Clear **Watermark its documents** on that company. It is per company, so one entity can be marked and another not.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'A company cannot be deleted.', 'answer' => 'It employs people or has payroll history, and its slips must keep pointing at it. Mark it inactive instead — it then cannot be chosen for new employees or new runs.'],
                    ['problem' => 'The letterhead logo does not print.', 'answer' => 'PDFs cannot render SVG, so an SVG is refused here on purpose. Upload a PNG, JPEG or WebP.'],
                    ['problem' => 'The address prints as one long run-on line.', 'answer' => 'The whole address is sitting in Address line 1. Split it across line 1, line 2, city, state and postal code and the pad prints it as a block.'],
                    ['problem' => 'Somebody was left out of a payroll run.', 'answer' => 'They probably belong to a different company. Check the payroll company on their record, and run payroll for that entity.'],
                    ['problem' => 'Two companies need the same month.', 'answer' => 'That is fine — create one run per company. A period is unique per company, not across the group.'],
                ],
            ],
            [
                'slug' => 'signatories',
                'group' => 'People',
                'title' => 'Signatories',
                'icon' => 'badge',
                'route_name' => 'companies.signatories.index',
                'permission' => 'companies.view',
                'summary' => 'Who may sign for a company, and whose name prints on its documents.',
                'body' => <<<'MD'
                    Every letter and every salary slip carries a name at the foot. This is the list
                    of people entitled to be that name for one company.

                    One of them is the **default**. The default signs the company's salary slips and
                    is offered first when somebody issues a letter — whoever issues it can pick
                    anybody else on the list, which is what makes a letter possible while a director
                    is away.

                    What a letter went out over is **frozen onto the letter** the moment it is
                    issued: the name, the title and the signature image as they stood that day.
                    Retiring somebody here, correcting a title or replacing a signature changes the
                    next letter, never one already in somebody's hands.

                    A specimen signature is optional. If one is uploaded it prints above the name on
                    letters and salary slips. It is held on the private disk and served only through
                    this screen, never on a public address, because an image of somebody's signature
                    is worth lifting.
                    MD,
                'fields' => [
                    ['term' => 'Name', 'description' => 'As it should print, e.g. Anita Rao.'],
                    ['term' => 'Designation', 'description' => 'Printed under the name. Left blank, a document prints “Authorised Signatory”.'],
                    ['term' => 'Email', 'description' => 'For your own reference. Nothing is ever sent to it.'],
                    ['term' => 'Specimen signature', 'description' => 'An optional PNG or JPEG up to 512 KB. A transparent or white background reads best.'],
                    ['term' => 'Order', 'description' => 'Lower numbers come first in the list and in the letter screen.'],
                    ['term' => 'Status', 'description' => 'Retired keeps every letter they signed but stops them being offered again.'],
                ],
                'tasks' => [
                    ['question' => 'How do I change who signs the salary slips?', 'answer' => 'Open the company\'s signatories and press **Make default** beside the person. The next slip generated carries their name; slips already issued keep the name they went out with.'],
                    ['question' => 'How do I let a second person sign while a director is away?', 'answer' => 'Add them here. Anybody issuing a letter can then choose them under **Signed by** on the issue screen.'],
                    ['question' => 'How do I add a signature image?', 'answer' => 'Edit the signatory and upload a PNG of the signature on a plain background. It prints above the name on letters and slips.'],
                    ['question' => 'Somebody has left. What do I do?', 'answer' => 'Set them to **Retired**. Every letter they signed is untouched, and they stop appearing on the issue screen.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'A letter printed “Authorised Signatory” with no name.', 'answer' => 'That company had nobody on its list when the letter was issued. Add a signatory and issue any future letters again — the one already issued keeps what it went out with.'],
                    ['problem' => 'Somebody cannot be removed.', 'answer' => 'They have signed letters, so they are retired rather than deleted; the register still has to be able to say who signed what.'],
                    ['problem' => 'The signature image does not print.', 'answer' => 'SVG is skipped deliberately because the PDF engine renders it unreliably. Upload a PNG or JPEG instead.'],
                    ['problem' => 'The wrong name appears on a letter.', 'answer' => 'The signatory is chosen when the letter is issued, and frozen then. Issue a fresh letter over the right name and remove the wrong one from the register.'],
                ],
            ],

            [
                'slug' => 'branches',
                'group' => 'People',
                'title' => 'Branches',
                'icon' => 'building',
                'route_name' => 'branches.index',
                'permission' => 'branches.view',
                'summary' => 'Offices, their working week, and who sees whose data.',
                'body' => <<<'MD'
                    Each office or site. A branch carries its own working days and hours, so a
                    six-day office and a five-day office can run side by side.

                    A branch that works Saturdays can take some of them off — first and third,
                    or second and fourth. An off Saturday counts as a weekly off everywhere it
                    matters: leave taken across it does not spend a day, payroll does not count it
                    as a working day, and the roster shows it as an off rather than an absence.

                    Branches are also the boundary for who sees what: a branch manager sees their
                    own branch and no other.
                    MD,
                'fields' => [
                    ['term' => 'Working days', 'description' => 'The days of the week this branch works. Attendance and leave both count days against this.'],
                    ['term' => 'Saturdays off', 'description' => 'Which Saturdays of the month are not worked, when Saturday is a working day at all. The first Saturday is the one falling on the 1st to the 7th, the third on the 15th to the 21st, and so on.'],
                    ['term' => 'Manager', 'description' => 'The employee who approves for this branch when someone has no line manager of their own.'],
                ],
                'tasks' => [
                    ['question' => 'How do I change a branch working week?', 'answer' => 'Edit the branch and tick the working days. It applies from now on; days already recorded are not recalculated.'],
                    ['question' => 'How do I set alternate Saturdays off?', 'answer' => 'Tick **Sat** under working days, then tick **1st Saturday** and **3rd Saturday** under Saturdays off. The 2nd, 4th and any 5th are then worked.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'The Saturday pattern is not reaching anybody.', 'answer' => 'A shift that lists its own working days overrides the branch. Add Saturday to that shift, or clear the shift\'s working days so it follows the branch.'],
                    ['problem' => 'A branch cannot be deleted.', 'answer' => 'Employees are still attached to it. Move them first, or mark the branch inactive instead.'],
                ],
            ],
            [
                'slug' => 'departments',
                'group' => 'People',
                'title' => 'Departments and designations',
                'icon' => 'squares',
                'route_name' => 'departments.index',
                'permission' => 'departments.view',
                'summary' => 'The teams people belong to and the titles they hold.',
                'body' => <<<'MD'
                    **Departments** are the teams — Finance, Engineering, Operations. **Designations**
                    are job titles — Analyst, Senior Analyst, Manager.

                    Both are used to group people in reports and to aim announcements, so keep the
                    lists short and meaningful rather than one entry per person.
                    MD,
                'tasks' => [
                    ['question' => 'How do I rename a department?', 'answer' => 'Edit it. Everyone in it follows the new name straight away; nothing needs reassigning.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'It will not let me delete one.', 'answer' => 'People are still assigned to it. Move them to another one first.'],
                ],
            ],
            [
                'slug' => 'shifts',
                'group' => 'People',
                'title' => 'Shifts',
                'icon' => 'clock',
                'route_name' => 'shifts.index',
                'permission' => 'shifts.view',
                'summary' => 'Working hours, grace periods and what counts as a full day.',
                'body' => <<<'MD'
                    A shift is the pattern someone is expected to work: when the day starts and
                    ends, how long the unpaid break is, and how many hours make a full or half day.

                    Attendance is judged against the shift on the employee's record. Change the
                    shift and today's judgement changes with it; days already recorded stay as they
                    were.
                    MD,
                'fields' => [
                    ['term' => 'Grace minutes', 'description' => 'How late someone may check in before the day is marked late. Fifteen minutes is common.'],
                    ['term' => 'Full day hours', 'description' => 'Hours needed for a full day. Below this but above the half-day figure, the day is marked as a half day.'],
                    ['term' => 'Break minutes', 'description' => 'Unpaid break, subtracted from the time between check-in and check-out.'],
                ],
                'tasks' => [
                    ['question' => 'How do I put someone on a different shift?', 'answer' => 'Edit the employee, not the shift. The shift is chosen on their record.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Everyone is suddenly marked late.', 'answer' => 'Check the shift start time and grace period, and that the branch timezone in Settings matches where people actually work.'],
                ],
            ],

            [
                'slug' => 'my-verification',
                'group' => 'My workspace',
                'title' => 'My verification',
                'icon' => 'shield',
                'route_name' => 'my-verification.edit',
                'permission' => 'bgv.complete-own',
                'summary' => 'The documents a new joiner provides before their onboarding is confirmed.',
                'body' => <<<'MD'
                    Before your onboarding is confirmed we check a few things: who you are, where
                    you live, what you studied and where you worked before.

                    The screen walks you through it one step at a time: each document is a step
                    with the details to type and the file to attach, and the last step is the
                    submit button. Steps you have done fold up with a tick; the next one to do is
                    open. Nothing is sent to HR until you press submit, so you can start today and
                    finish tomorrow.

                    Until HR has verified your documents, **My Attendance**, **Leave** and **Salary
                    Slips** stay locked in the menu. They open on their own the moment your
                    verification clears, and you are emailed to say so.
                    MD,
                'fields' => [
                    ['term' => 'Not provided', 'description' => 'Nothing uploaded against this one yet.'],
                    ['term' => 'Awaiting review', 'description' => 'You have provided it and HR has not looked at it yet.'],
                    ['term' => 'Verified', 'description' => 'Accepted. There is nothing more to do on that one.'],
                    ['term' => 'Rejected', 'description' => 'Something was wrong with it. The note underneath says what, and you can replace it.'],
                ],
                'tasks' => [
                    ['question' => 'What counts as an identity document?', 'answer' => 'A government photo identity document: a passport, a driving licence or a national identity card. A photograph of it is fine as long as all four corners and the number are readable.'],
                    ['question' => 'How do I replace something I got wrong?', 'answer' => 'Upload the new file against the same item and save it. The old one is replaced, and any note from HR is cleared.'],
                    ['question' => 'What if I cannot produce one of them?', 'answer' => 'Provide everything else, submit, and tell HR why the last one is missing. They can decide whether to go ahead without it.'],
                    ['question' => 'What happens after I submit?', 'answer' => 'HR is emailed straight away. They check each document and either confirm your onboarding or send back the ones that need redoing.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'The submit button will not press.', 'answer' => 'Something on the list has no document against it yet. The panel on the right says how many are left.'],
                    ['problem' => 'I cannot change anything any more.', 'answer' => 'You have already submitted. Once HR looks at it, it either clears or comes back to you with notes.'],
                    ['problem' => 'My Attendance, Leave and Salary Slips are greyed out.', 'answer' => 'They are locked until your verification is complete. Finish the steps here and submit; once HR verifies them the menu opens up by itself.'],
                    ['problem' => 'The upload is refused.', 'answer' => 'Files must be a PDF or a photograph, under 10 MB. A phone photograph is often larger than that — email it to yourself first, or use your phone camera\'s smaller size setting.'],
                ],
            ],
            [
                'slug' => 'background-verification',
                'group' => 'People',
                'title' => 'Background verification',
                'icon' => 'shield',
                'route_name' => 'background-checks.index',
                'permission' => 'bgv.view',
                'summary' => 'Asking new joiners for their documents, checking them, and onboarding.',
                'body' => <<<'MD'
                    Every new joiner's verification, and where each one has got to. The cases
                    waiting on you come first.

                    A case moves through **invited** → **in progress** → **awaiting review** →
                    **verified**, and can be sent back to the employee as often as it takes.
                    Clearing the last document is what onboards them, so nothing separate has to
                    be remembered afterwards.
                    MD,
                'fields' => [
                    ['term' => 'Awaiting review', 'description' => 'The employee has provided everything and is waiting on you. This is the queue to work.'],
                    ['term' => 'Changes requested', 'description' => 'You sent something back and the employee has not replaced it yet.'],
                    ['term' => 'Overdue', 'description' => 'The completion date you set has passed and the case is not finished. It is a prompt, not a block.'],
                    ['term' => 'Documents', 'description' => 'How many of the requested documents have been provided, whether or not they have been accepted.'],
                ],
                'tasks' => [
                    ['question' => 'How do I ask someone for their documents?', 'answer' => 'Use **Ask someone for documents**, choose the person, tick what you need and set a date. They are emailed the checklist and complete it in the portal. You can also tick this on the employee creation form so it goes out with the welcome email.'],
                    ['question' => 'How do I send one document back?', 'answer' => 'Open the case, write what is wrong in the note against that document and press **Send back**. Do that for each one, then press **Ask for changes** — that is the message the employee receives.'],
                    ['question' => 'How do I onboard someone?', 'answer' => '**Verify and onboard** on the case. It accepts anything still unreviewed, records who decided and when, and emails the employee to say they are all set.'],
                    ['question' => 'I asked for the wrong documents. Can I change the list?', 'answer' => 'Yes, until the case is verified. Open it and press **Change what is asked for** under *The case*. Untick what does not apply, for example an experience letter from someone who has never worked, or tick something extra. Anything new is emailed to the employee; if they had already submitted, the case goes back to them for the new item. Unticking something they have already uploaded deletes that file.'],
                    ['question' => 'Can I ask for something unusual?', 'answer' => 'The list of requestable documents is fixed in the application. Ask for the closest match and use the note to explain, or ask a developer to add it to the requirement catalogue.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Onboarding is refused.', 'answer' => 'A required document has not been provided at all. Either wait for it, or send the case back asking for that one.'],
                    ['problem' => 'Somebody is not in the invite list.', 'answer' => 'They already have a case — find them in the table — or they are inactive, or at a branch you cannot see.'],
                    ['problem' => 'The employee says they never got the email.', 'answer' => 'Check the address on their record, that email is on in Settings, and that **Verification requested** is switched on under Administration → Notifications.'],
                ],
            ],

            // ---------------------------------------------- attendance & leave
            [
                'slug' => 'daily-roster',
                'group' => 'Attendance & leave',
                'title' => 'Daily roster',
                'icon' => 'list',
                'route_name' => 'attendance.daily',
                'permission' => 'attendance.view-team',
                'summary' => 'Who is in, who is late and who is missing, for one day.',
                'body' => <<<'MD'
                    One day at a time, across everyone you can see: who checked in, when, from
                    where, and who has not appeared at all.

                    This is the screen to have open in the morning. Corrections are made here too,
                    for the days when someone's punch did not record.
                    MD,
                'fields' => [
                    ['term' => 'Punch location', 'description' => 'Where the check-in was made, captured from the browser. Blank means the punch predates location being required.'],
                    ['term' => 'Not marked', 'description' => 'No punch and no leave — someone who is expected but has not appeared. Worth a phone call.'],
                ],
                'tasks' => [
                    ['question' => 'How do I record attendance for someone who could not punch?', 'answer' => 'Use **Add attendance**, choose the person and the date, and enter the times. It is recorded as an administrative entry, so the audit trail shows it was you.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Someone is missing from the list.', 'answer' => 'They may be on approved leave, at a branch you cannot see, or not yet started.'],
                ],
            ],
            [
                'slug' => 'location-alerts',
                'group' => 'Attendance & leave',
                'title' => 'Punches away from the branch',
                'icon' => 'map-pin',
                'route_name' => 'attendance.location-alerts',
                'permission' => 'attendance.view-team',
                'summary' => 'The day\'s check-ins and check-outs made further from the branch than it allows.',
                'body' => <<<'MD'
                    Every branch can be given its own coordinates, and every punch already
                    records where the person was standing. This screen lists the days'
                    punches where those two were further apart than the branch allows —
                    200 metres unless you have changed it.

                    A punch is never refused for being too far away. A client visit, a site
                    inspection, or a phone with a poor fix indoors all look identical from
                    here, so this is a list to read rather than a verdict to act on.

                    The same list is emailed to the people named under Settings, Attendance:
                    each one as it happens, and the whole day again each evening.
                    MD,
                'fields' => [
                    ['term' => 'Distance', 'description' => 'From the branch\'s coordinates to where the device said the person was, worked out and frozen at the moment of the punch. Correcting the branch later does not rewrite it.'],
                    ['term' => 'Allowed', 'description' => 'That branch\'s radius. A branch with no radius of its own uses the organisation-wide default.'],
                    ['term' => 'Reported accuracy', 'description' => 'How precise the device claimed to be. A punch 300 m out with an accuracy of 500 m says very little.'],
                ],
                'tasks' => [
                    ['question' => 'How do I set where a branch is?', 'answer' => 'Open the branch, and under **Location and geofence** either type the coordinates or, standing at the branch, press **Use my current location**. A branch with no coordinates is never checked.'],
                    ['question' => 'How do I change who is alerted?', 'answer' => 'Administration, **Settings**, the Attendance tab. The addresses there receive both the immediate alerts and the evening report. Leave it empty and it falls back to every HR manager and administrator.'],
                    ['question' => 'One site is spread out and keeps being flagged.', 'answer' => 'Give that branch its own radius on the branch screen instead of raising the default for everybody.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Nothing is ever listed here.', 'answer' => 'Check that location checking is on under Settings, Attendance, and that the branches have coordinates — the panel on the right names any that do not.'],
                    ['problem' => 'The evening report never arrives.', 'answer' => 'It is sent by the scheduler, which needs a cron entry on the server. Ask whoever installed it to confirm `php artisan schedule:run` is running every minute.'],
                    ['problem' => 'Somebody legitimately works elsewhere every day.', 'answer' => 'Nothing here stops them working or being paid. If the noise is not useful, either widen that branch\'s radius or switch the check off entirely.'],
                ],
            ],
            [
                'slug' => 'regularisations',
                'group' => 'Attendance & leave',
                'title' => 'Attendance corrections',
                'icon' => 'pencil',
                'route_name' => 'attendance.regularizations.index',
                'permission' => 'attendance.view-own',
                'summary' => 'Asking for a wrong attendance day to be fixed, and approving those requests.',
                'body' => <<<'MD'
                    When a day is wrong — a missed punch, a forgotten check-out, a day worked
                    off-site — the employee raises a correction rather than an administrator
                    quietly editing the record.

                    Approving one rewrites that day's attendance and recalculates the hours. Both
                    the request and the decision are kept, so the history shows what changed and
                    who agreed to it.
                    MD,
                'fields' => [
                    ['term' => 'Requested check-in / check-out', 'description' => 'The times the employee says they actually worked. Either can be left out if only one is wrong.'],
                    ['term' => 'Review remarks', 'description' => 'What the approver wrote. It reaches the employee in the decision email, so it is worth a sentence.'],
                ],
                'tasks' => [
                    ['question' => 'How do I raise a correction?', 'answer' => 'Use **Request a correction**, pick the date, give the right times and say what happened.'],
                    ['question' => 'Who approves mine?', 'answer' => 'Your line manager, or your branch manager if you have no line manager set.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'The day still looks wrong after approval.', 'answer' => 'Only the times that were filled in are applied. If the check-out was left blank, it stays as it was — raise another correction with both times.'],
                ],
            ],
            [
                'slug' => 'leave-calendar',
                'group' => 'Attendance & leave',
                'title' => 'Leave calendar',
                'icon' => 'calendar',
                'route_name' => 'leave.calendar',
                'permission' => 'leave.view-team',
                'summary' => 'Who is away and when, so cover can be arranged.',
                'body' => <<<'MD'
                    Approved leave laid out by day, for everyone you can see. Use it before
                    approving a request to check that the whole team is not away at once.

                    Only approved leave appears. A pending request is not shown, because it may
                    yet be rejected.
                    MD,
                'tasks' => [
                    ['question' => 'How do I check a week before I approve?', 'answer' => 'Open the calendar on that week. If several people from one team are already off, say so in your remarks when you decide.'],
                ],
            ],
            [
                'slug' => 'leave-types',
                'group' => 'Attendance & leave',
                'title' => 'Leave types',
                'icon' => 'tag',
                'route_name' => 'leave-types.index',
                'permission' => 'leave.manage-types',
                'summary' => 'The kinds of leave people can apply for, the rules on each, and how they are earned.',
                'body' => <<<'MD'
                    Casual, sick, earned, unpaid — each with its own rules: whether it is paid,
                    whether unused days carry forward, how much notice is expected, and how the
                    entitlement is earned in the first place.

                    **Granted for the year** hands over the whole entitlement at the start,
                    prorated for somebody who joins mid-year. **Earned monthly** credits a fixed
                    number of days each month instead — 1.5 days once confirmed and 1 day while on
                    probation is the usual arrangement — which is fairer to everybody and is what
                    most employers here run.

                    Monthly credits are written overnight, one row per person per month, and every
                    one of them is listed on the employee's own Leave Balance screen. A month
                    somebody joins or leaves part-way through is credited for the part they were
                    here.

                    Changing a rule affects new requests. Balances already allocated for the year
                    stay as they are until you change them under **Leave allocations**.
                    MD,
                'fields' => [
                    ['term' => 'Earned', 'description' => 'Granted for the year, or earned monthly. Monthly is credited a month at a time and never hands over days somebody has not worked for yet.'],
                    ['term' => 'Days per month / on probation', 'description' => 'What a full month earns. Leave the probation figure empty and everybody earns the same rate.'],
                    ['term' => 'Start crediting from', 'description' => 'The first month to credit. Months before it are left alone, so switching a type to monthly in September does not silently hand everybody January to August.'],
                    ['term' => 'Credit at the start of the month', 'description' => 'Off means a month is credited once it has been worked, which is the safer default. On hands it over on the 1st.'],
                    ['term' => 'Is paid', 'description' => 'Whether days on this type are paid. Unpaid leave reduces paid days on the salary slip.'],
                    ['term' => 'Carry forward', 'description' => 'Whether unused days survive into next year, and up to what cap.'],
                    ['term' => 'Days per year', 'description' => 'Granted up front for a yearly type. For a monthly type it is a ceiling instead: 1.5 a month reaches 18 in a year, so a limit of 12 would stop crediting in August. Zero means no ceiling.'],
                ],
                'tasks' => [
                    ['question' => 'How do I add a new kind of leave?', 'answer' => 'Create the type here, then allocate it to people under **Leave allocations**. Until they have an allocation nobody can apply for it.'],
                    ['question' => 'How do I set up 1.5 days a month, 1 day on probation?', 'answer' => 'Open the type, set **Earned** to "Earned monthly", put 1.5 in days per month and 1 in days per month on probation, and set **Start crediting from** to the month you want to begin. The overnight job does the rest.'],
                    ['question' => 'Who counts as being on probation?', 'answer' => 'Somebody whose confirmation date has not yet passed. Where no confirmation date is recorded, their employment status is used instead. The rate is whichever applied at the end of the month being credited, so confirming somebody in July gives them the probation rate for June and the full rate from July.'],
                    ['question' => 'We just switched a type to monthly and people still have last year\'s figure.', 'answer' => 'Credits are added to whatever was already allocated. If you want balances to start from the monthly credits alone, zero the allocations for the year under **Leave allocations** first, then let the job run.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Nobody can apply for a type I just created.', 'answer' => 'It has no allocations yet. Use the bulk allocation on the leave allocations screen.'],
                ],
            ],
            [
                'slug' => 'leave-allocations',
                'group' => 'Attendance & leave',
                'title' => 'Leave allocations',
                'icon' => 'scale',
                'route_name' => 'leave-allocations.index',
                'permission' => 'leave.manage-allocations',
                'summary' => 'Giving people their days for the year, one at a time or in bulk.',
                'body' => <<<'MD'
                    An allocation is one person's days of one leave type for one year. Without an
                    allocation, that person cannot apply for that type at all.

                    At the start of a year, use the bulk allocation to give everybody the annual
                    quota in one go, then adjust the exceptions by hand.
                    MD,
                'tasks' => [
                    ['question' => 'How do I set up a new year?', 'answer' => "Use **Allocate in bulk**, choose the year and the leave type, and it gives every active employee the type's annual quota. Anyone who already has an allocation is left alone."],
                    ['question' => 'How do I give one person extra days?', 'answer' => 'Find their row and edit the allocated figure. Their balance updates immediately.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Someone has a negative balance.', 'answer' => 'Their allocation was reduced after leave had already been approved. Either restore the days or cancel the approved request.'],
                ],
            ],

            // --------------------------------------------------------- payroll
            [
                'slug' => 'payroll-runs',
                'group' => 'Payroll',
                'title' => 'Payroll runs',
                'icon' => 'currency',
                'route_name' => 'payroll.index',
                'permission' => 'payroll.view',
                'summary' => 'Running a month end from generating slips to marking it paid.',
                'body' => <<<'MD'
                    A run is one month of payroll for one set of employees. It moves through
                    **draft**, **generated**, **submitted**, **approved** and finally **paid**, and
                    can only go forwards.

                    Generating is safe: it recalculates every slip from the salary structures and
                    the attendance for the month, and it can be re-run as often as you like while
                    the run is still a draft. Once approved, the figures are fixed.
                    MD,
                'fields' => [
                    ['term' => 'Company', 'description' => 'The legal entity being paid. A run covers one company only, and its slips are issued in that company\'s name.'],
                    ['term' => 'Working days', 'description' => "The month's working days for that employee's branch. Pay is prorated against this figure."],
                    ['term' => 'Paid days', 'description' => 'Working days the employee is actually paid for, after unpaid leave and any mid-month start or exit.'],
                    ['term' => 'Submitted', 'description' => 'Prepared and handed to whoever approves. Slips are not visible to employees at this point.'],
                    ['term' => 'Approved', 'description' => 'Signed off. Slips become visible to employees and can be emailed.'],
                ],
                'tasks' => [
                    ['question' => 'How do I run this month\'s payroll?', 'answer' => 'Create the run for the month, press **Generate**, check the totals, then **Submit**. Someone with the approval permission approves it, and you mark it paid once the money has gone.'],
                    ['question' => 'How do I fix a wrong figure?', 'answer' => 'While the run is a draft, correct the salary structure or the attendance and press **Generate** again. After approval, the run is fixed — handle it in the next month.'],
                    ['question' => 'How do I email everyone their slip?', 'answer' => 'From the approved run, use **Email salary slips**. Each employee gets their own slip as a PDF.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Someone was left out of the run.', 'answer' => 'They have no active salary structure, or they were not employed during the month. Add the structure and generate again.'],
                    ['problem' => 'A new joiner was paid a full month.', 'answer' => 'Check their date of joining is right, then generate again — proration comes from that date.'],
                ],
            ],
            [
                'slug' => 'bank-file',
                'group' => 'Payroll',
                'title' => 'Paying the salaries',
                'icon' => 'currency',
                'route_name' => 'payroll.index',
                'permission' => 'payroll.bank-file',
                'summary' => 'Turning an approved run into a file your bank will accept.',
                'body' => <<<'MD'
                    An approved run carries a **Bank file** button. The screen behind it checks the
                    whole run before it writes anything — a missing account number, an IFSC that is
                    not one, two people sharing an account, a net pay of nothing — and refuses to
                    produce a file while any of those stand.

                    That refusal is the point. A bank rejects the entire upload over one bad row,
                    and it tells you so days later, after the salary date. Better to hear it here.

                    **The file does not pay anybody.** It is what you upload to your bank. Once the
                    money has actually moved, come back and mark the run paid with the bank's own
                    reference.
                    MD,
                'fields' => [
                    ['term' => 'Bank layout', 'description' => 'Which shape of file your bank accepts. The columns each one writes are listed on the screen — compare them against the template your bank sent you before the first upload, because these specifications are revised from time to time.'],
                    ['term' => 'Value date', 'description' => 'The date you want the money to move. Defaults to the run\'s payment date.'],
                    ['term' => 'Payment type', 'description' => 'Worked out per person: an account at your own bank never leaves it, two lakh or more has to go by RTGS, and everything else is NEFT.'],
                    ['term' => 'Debit account', 'description' => 'The account the money leaves, held on the company rather than in settings — two legal entities do not share one. Set it under Payroll → Companies.'],
                    ['term' => 'Narration', 'description' => 'What the employee sees on their statement. Kept to letters, digits and spaces, because a comma or an accent is the usual reason a file is rejected.'],
                ],
                'tasks' => [
                    ['question' => 'How do I pay this month\'s salaries?', 'answer' => 'Approve the run, open **Bank file**, fix anything it lists, choose your bank\'s layout and download. Upload that file to your bank, check the total it reports against the one on the screen, then come back and mark the run paid.'],
                    ['question' => 'My bank is not in the list.', 'answer' => 'Use **Generic CSV**. It carries every field with plain headings, and most banks\' upload screens let you map columns to their own. If your bank needs a fixed layout it does not have here, send us the template.'],
                    ['question' => 'The total does not match the run.', 'answer' => 'Somebody in the run is paid in cash or by cheque, and is left out of a bank file by definition. The screen names them and says so.'],
                    ['question' => 'Does downloading the file mark the run paid?', 'answer' => 'No, deliberately. The money leaves at the bank, not here, and a file that was downloaded and never uploaded would otherwise look like a payment that happened.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'The download button is greyed out.', 'answer' => 'There is something in the list above it to fix first. Bank details are on the employee record under Bank & statutory.'],
                    ['problem' => 'The bank rejected the file.', 'answer' => 'Compare the columns listed on the screen against your bank\'s current template — they revise these. The layout is chosen per company, so changing it once is enough.'],
                    ['problem' => 'There is no Bank file button.', 'answer' => 'The run has not been approved. A file is only produced for a run somebody has signed off.'],
                ],
            ],
            [
                'slug' => 'income-tax',
                'group' => 'Payroll',
                'title' => 'Income tax',
                'icon' => 'scale',
                'route_name' => 'tax.index',
                'permission' => 'tax.view',
                'summary' => 'Declarations, verification, and the tax deducted from each salary.',
                'body' => <<<'MD'
                    Tax deducted at source is not a percentage of a salary slip. It is a projection:
                    the whole year's income is estimated, the year's tax on it worked out, what has
                    already been deducted taken off, and what is left spread across the months that
                    remain. That is why somebody's deduction changes when they get a rise in October,
                    and again when they declare an investment in January.

                    Employees choose a regime and declare what they intend to invest under **My
                    Income Tax**. You check those declarations against their proofs here and record
                    what you accept, which may be less than was claimed. Both figures are kept — a
                    declaration that was cut down still shows what was originally put in.

                    **Deducting tax starts switched off.** Until somebody turns it on under
                    Settings → Payroll, salaries are paid without tax taken off.
                    MD,
                'fields' => [
                    ['term' => 'Regime', 'description' => 'The new one has lower rates and almost no deductions; the old one keeps the deductions. Both are worked out on every screen and the difference shown — it is the employee\'s choice, and the software does not make it for them.'],
                    ['term' => 'Declared', 'description' => 'What the employee says they will invest by March. Payroll uses it until you rule otherwise, because tax has to be deducted on something in the meantime.'],
                    ['term' => 'Accepted', 'description' => 'What you allow after seeing the proof. Leave the box empty and the declared figure stands; type a nought to accept nothing, which is a decision and is recorded as one.'],
                    ['term' => 'Sent back', 'description' => 'Returns it to the employee to correct, with your reason. They can then change it and hand it in again.'],
                    ['term' => 'Computation sheet', 'description' => 'Every line of the working, in the order it is applied: gross, each exemption, taxable income, the tax at each band, rebate, surcharge, cess, and what is left to deduct.'],
                    ['term' => 'Statement', 'description' => 'The year\'s salary and tax as a PDF. It is not a Form 16 and says so on its face — Part A comes from TRACES against the returns actually filed and cannot be produced here.'],
                ],
                'tasks' => [
                    ['question' => 'How do we start deducting tax?', 'answer' => 'Ask employees to declare and choose a regime, check their proofs here, then turn **Deduct income tax from salaries** on under Settings → Payroll. Somebody who has declared nothing is taxed on their whole salary — correct, and also what you will be asked about, so it is worth chasing declarations first.'],
                    ['question' => 'Somebody declared more than the section allows.', 'answer' => 'Nothing to do. The figure is accepted and capped at the ceiling, and the computation sheet shows both numbers so they can see why.'],
                    ['question' => 'How is house rent relief worked out?', 'answer' => 'The least of three figures: the allowance actually paid, half of salary in Delhi, Mumbai, Kolkata or Chennai (two fifths elsewhere), and the rent above a tenth of salary. The employee declares the rent and ticks the city; the arithmetic is done for them. Somebody paid no house rent allowance gets no relief however much rent they pay — that is the law, not an oversight.'],
                    ['question' => 'Which regime should somebody choose?', 'answer' => 'Their screen shows both totals side by side on what they have declared, and which is cheaper. Above a certain salary the new regime often wins even with every deduction given up, which surprises people.'],
                    ['question' => 'Why did this month\'s deduction change?', 'answer' => 'The projection was re-run. A rise, a month with loss of pay, a new declaration or your verification all change the year\'s estimate, and the balance is spread over the months that are left.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'A salary slip still shows a flat five per cent.', 'answer' => 'Deduction is not switched on. The default component set carries a flat-percentage TDS line as a placeholder; turning the feature on replaces it on every slip with the real computation.'],
                    ['problem' => 'The rates look out of date.', 'answer' => 'The computation sheet names the year whose rules produced it and warns when it has fallen back to an older Finance Act. Check the figures against the current one — they are revised most years.'],
                    ['problem' => 'Somebody says their deduction is too high.', 'answer' => 'Open their computation sheet. It shows every line of the working, including which of their declarations were disallowed and why. Nine times in ten they are on the new regime with deductions declared against it.'],
                ],
            ],
            [
                'slug' => 'salary-structures',
                'group' => 'Payroll',
                'title' => 'Salary structures',
                'icon' => 'layers',
                'route_name' => 'salary-structures.index',
                'permission' => 'payroll.manage-structures',
                'summary' => 'What each person is paid, broken into its components.',
                'body' => <<<'MD'
                    One structure per employee: their annual cost to company, their basic pay, and
                    the components that make up the rest.

                    A structure has a date it takes effect from. Give someone a rise by creating a
                    new structure from the date it starts rather than editing the old one — the old
                    slips then keep telling the truth about what was paid at the time.
                    MD,
                'fields' => [
                    ['term' => 'Effective from', 'description' => 'The date this structure starts applying. Payroll uses whichever structure covers the month being run.'],
                    ['term' => 'CTC', 'description' => 'Annual cost to company. A reference figure — the slip is built from the components, not from this.'],
                ],
                'tasks' => [
                    ['question' => 'How do I give someone a rise?', 'answer' => 'Create a new structure for them, effective from the month the rise starts. Do not edit the old one.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'The slip does not match the structure.', 'answer' => 'Pay is prorated by paid days, so a month with unpaid leave will be lower. Check paid days against working days on the slip.'],
                ],
            ],
            [
                'slug' => 'salary-components',
                'group' => 'Payroll',
                'title' => 'Salary components',
                'icon' => 'puzzle',
                'route_name' => 'salary-components.index',
                'permission' => 'payroll.manage-components',
                'summary' => 'The building blocks a salary is made of: allowances and deductions.',
                'body' => <<<'MD'
                    Every line that can appear on a salary slip: basic, house rent allowance,
                    provident fund, professional tax and anything else you add.

                    A component is either an **earning** or a **deduction**, and its amount is
                    either a fixed sum or a percentage of another figure. Earnings are worked out
                    first, so a deduction set as a percentage of gross sees the final gross.
                    MD,
                'fields' => [
                    ['term' => 'Wage ceiling', 'description' => 'The percentage is worked out on a wage capped at this figure. Provident fund is 12% of a basic capped at ₹15,000, so a large basic contributes the same as one at the cap.'],
                    ['term' => 'Applies up to', 'description' => 'Above this the component does not apply at all. Employee state insurance stops at ₹21,000 gross — over it somebody is outside the scheme rather than paying on a capped wage.'],
                    ['term' => 'Slab table', 'description' => 'A table of bands rather than a percentage, for professional tax. Each row is the amount charged up to and including that figure; the last row with an empty ceiling covers everything above.'],
                    ['term' => 'Note', 'description' => 'Says which rules are loaded, so whoever runs payroll can see at a glance whether they are the right ones.'],
                    ['term' => 'Calculation', 'description' => 'Fixed amount, a percentage of basic, or a percentage of gross.'],
                    ['term' => 'Taxable', 'description' => 'Whether this earning counts towards taxable income. It is for reporting; no tax is computed here.'],
                ],
                'tasks' => [
                    ['question' => 'How do I add a new allowance?', 'answer' => 'Create the component here, then add it to the salary structures that should carry it. Existing slips are not changed.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Provident fund looks too small on a senior salary.', 'answer' => 'That is the wage ceiling doing its job. Provident fund is 12% of a basic capped at ₹15,000, so everybody above the cap contributes the same ₹1,800.'],
                    ['problem' => 'Professional tax is wrong for our state.', 'answer' => 'It is set by each state, and Karnataka\'s slabs are loaded as an example. Edit the slab table on the Professional Tax component to your state\'s bands.'],
                    ['problem' => 'Employee state insurance is not being deducted.', 'answer' => 'It only applies where monthly gross is ₹21,000 or less, and it is switched off by default. Turn the component on if your establishment is covered.'],
                    ['problem' => 'A percentage component comes out as zero.', 'answer' => 'It is a percentage of a figure that is itself zero — usually basic pay missing from the structure.'],
                ],
            ],

            // --------------------------------------------------------- reports
            [
                'slug' => 'reports',
                'group' => 'Insights',
                'title' => 'Reports',
                'icon' => 'chart',
                'route_name' => 'reports.index',
                'permission' => 'reports.attendance',
                'summary' => 'Attendance, breaks, leave, payroll and headcount, for a period you choose.',
                'body' => <<<'MD'
                    Reports grouped by what they are about: five on attendance, then leave,
                    payroll and workforce. Each can be narrowed by branch and department, and
                    exported as CSV for a spreadsheet.

                    Reports read the same data as the screens they summarise, so a figure that
                    looks wrong here is usually wrong at the source.
                    MD,
                'fields' => [
                    ['term' => 'Break report', 'description' => 'Every break taken on a day — who, what for, and how long. The monthly view totals each person’s time away by reason instead.'],
                    ['term' => 'Daily attendance report', 'description' => 'One day across the organisation: shift, whether they arrived on time, punch in and out, where from, and hours worked.'],
                    ['term' => 'Monthly attendance report', 'description' => 'A whole month as a grid, a cell per day. Choose whether the cells show working time, break time, overtime or lateness.'],
                    ['term' => 'Monthly in-out report', 'description' => 'The same month showing the first punch in and the last punch out of each day, rather than a total.'],
                    ['term' => 'Attendance summary', 'description' => 'One row per employee for a month: present, absent and late days, hours and overtime.'],
                ],
                'tasks' => [
                    ['question' => 'How do I get this into a spreadsheet?', 'answer' => 'Use **Export** on the report. It downloads the rows you are looking at, with the filters applied.'],
                    ['question' => 'How do I see a whole month at once?', 'answer' => 'Use the **Monthly attendance report**. The employee column and the dates stay put while the days scroll sideways, so you never lose track of whose row you are reading.'],
                    ['question' => 'How do I find who is taking long breaks?', 'answer' => 'Open the **Break report** and switch it to **Monthly**. Each person’s time away is totalled by reason, so a long lunch reads differently from a day of client visits.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'The totals differ from the dashboard.', 'answer' => 'The dashboard counts today; a report counts the period you chose. Check the dates.'],
                    ['problem' => 'A day is red but the person was there.', 'answer' => 'Red means no punch was recorded, not that somebody was away. If they forgot, they can raise a regularisation and the day is recalculated.'],
                    ['problem' => 'Break time is an hour for somebody who never pressed the break button.', 'answer' => 'A day with no recorded break still has the shift’s own unpaid break taken off. Once real breaks are recorded, those replace it.'],
                ],
            ],

            // -------------------------------------------------- administration
            [
                'slug' => 'user-accounts',
                'group' => 'Administration',
                'title' => 'User accounts',
                'icon' => 'key',
                'route_name' => 'users.index',
                'permission' => 'users.view',
                'summary' => 'Who can sign in, what role they hold, and resetting a password.',
                'body' => <<<'MD'
                    An account is a login. An employee is a person. Most people have both, linked
                    together, but an auditor might have an account and no employee record.

                    Roles are set here. What a role can actually do is set under **Roles and
                    permissions**.
                    MD,
                'fields' => [
                    ['term' => 'Active', 'description' => 'An inactive account cannot sign in. Their records stay exactly as they are.'],
                    ['term' => 'Must change password', 'description' => 'Set when a password has been issued. They are asked to choose their own before they can go anywhere.'],
                ],
                'tasks' => [
                    ['question' => 'How do I reset someone\'s password?', 'answer' => 'Open the account and use **Reset password**. A new temporary password is generated and emailed to them.'],
                    ['question' => 'How do I stop someone signing in immediately?', 'answer' => 'Mark the account inactive. It takes effect on their next request; deleting is rarely what you want.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Someone cannot sign in.', 'answer' => 'Check the account is active, then that they are using the right address. Repeated failures are rate-limited for a minute.'],
                ],
            ],
            [
                'slug' => 'roles-and-permissions',
                'group' => 'Administration',
                'title' => 'Roles and permissions',
                'icon' => 'shield',
                'route_name' => 'roles.index',
                'permission' => 'roles.view',
                'summary' => 'What each role can see and do, screen by screen.',
                'body' => <<<'MD'
                    A role is a bundle of permissions, and every screen and action checks one.
                    Change a role and everyone holding it is affected at once.

                    Super Admin passes every check by design — with the deliberate exception that
                    nobody can delete their own account.
                    MD,
                'fields' => [
                    ['term' => 'View own / team / all', 'description' => 'How wide someone can see. "Team" means the people who report to them; "all" ignores branch boundaries.'],
                ],
                'tasks' => [
                    ['question' => 'How do I let branch managers approve leave?', 'answer' => 'Tick **Approve or reject leave** on the Branch Manager role. It applies to every branch manager immediately.'],
                    ['question' => 'How do I make a read-only role?', 'answer' => 'Copy an existing role, then untick everything that is not a "view" permission.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Someone still cannot see a screen after I gave them the permission.', 'answer' => 'Ask them to sign out and back in. Permissions are read when a session starts.'],
                ],
            ],
            [
                'slug' => 'notifications-console',
                'group' => 'Administration',
                'title' => 'Notifications',
                'icon' => 'bell',
                'route_name' => 'notification-templates.index',
                'permission' => 'notifications.manage',
                'summary' => 'Every message the system sends: what it says, and whether it is sent at all.',
                'body' => <<<'MD'
                    Every email and bell notification, in one list. Each can be switched off, or
                    reworded — subject, message, button text, and the short line in the bell.

                    A field left blank keeps the wording the system ships with, so you can change a
                    subject and leave the rest alone. Placeholders like `{{ employee_name }}` are
                    filled in when the message is sent; a preview beside the form shows the result
                    as you type.
                    MD,
                'fields' => [
                    ['term' => 'Email / In app', 'description' => 'The two channels. Off means that message is simply not sent that way.'],
                    ['term' => 'Edited', 'description' => 'Somebody has changed the shipped wording. Restoring it is one button on the guide.'],
                ],
                'tasks' => [
                    ['question' => 'How do I stop a particular email?', 'answer' => 'Switch **Email** off on that row. In-app notifications keep working.'],
                    ['question' => 'How do I try wording before anyone sees it?', 'answer' => 'Open the guide, type an address beside **Send a test** (it starts as your own) and press the button. It sends what is on screen, with example details, to that address.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'No email is going out at all.', 'answer' => 'Check the master switch in **Settings → Email** first — it overrides everything here — then that the queue worker is running.'],
                ],
            ],
            [
                'slug' => 'self-assistance-guides',
                'group' => 'Administration',
                'title' => 'Self Assistance guides',
                'icon' => 'document',
                'route_name' => 'help-articles.index',
                'permission' => 'help.manage',
                'summary' => 'The help you are reading now, and how to make it your own.',
                'body' => <<<'MD'
                    Every guide under Self Assistance is stored in the system, not in the code, so
                    you can rewrite any of it to match how your company actually works.

                    A guide has four parts and all of them are optional: what the page is for, what
                    the words on it mean, how to do the jobs people come to it for, and what to try
                    when it misbehaves.
                    MD,
                'fields' => [
                    ['term' => 'The screen this describes', 'description' => 'Ties a guide to a page, so "Help for this page" in the header finds it.'],
                    ['term' => 'Only show to people who can', 'description' => 'Hides the guide from people who could not open that screen anyway.'],
                    ['term' => 'Draft', 'description' => 'Written but not shown to readers yet. Only people who can edit guides see it.'],
                ],
                'tasks' => [
                    ['question' => 'How do I add a guide of our own?', 'answer' => '**New guide**, give it a title and a section, and write it. Leave the screen empty if it is a policy note rather than a page.'],
                    ['question' => 'How do I get the original wording back?', 'answer' => 'Run `php artisan db:seed --class=HelpArticleSeeder`. It restores the guides that shipped and leaves yours alone.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'A guide is not in the reader list.', 'answer' => 'It is either a draft, or restricted to a permission you do not hold.'],
                ],
            ],
            [
                'slug' => 'automations',
                'group' => 'Administration',
                'title' => 'Automations',
                'icon' => 'clock',
                'route_name' => 'automations.index',
                'permission' => 'automations.manage',
                'summary' => 'What the system does on its own, and whether it worked.',
                'body' => <<<'MD'
                    Some things happen without anybody asking: leave is credited each month, the leave year opens on 1 January, probation ends, birthdays are announced, and the evening report of punches away from the branch goes out.

                    This screen is the list. For each one it says what it does, how often, at what hour, when it last ran and what it did — and whether that run worked.

                    You can move the hour, switch one off, or press **Run now** to make it happen this minute. Running one by hand works even when it is switched off, because pressing the button is a decision.

                    None of it happens at all unless the server calls `php artisan schedule:run` every minute from cron. If nothing here has ever run, that is the first thing to check, and the screen says so.
                    MD,
                'fields' => [
                    ['term' => 'Runs', 'description' => 'How often, and at what hour. The hour is yours to move; the frequency is fixed.'],
                    ['term' => 'Last run', 'description' => 'When it last happened, and who asked for it if a person pressed Run now rather than the scheduler.'],
                    ['term' => 'What it did', 'description' => 'One sentence from the job itself — how many people were credited, how many punches were flagged.'],
                    ['term' => 'Switched on', 'description' => 'Off means the scheduler skips it. Run now still works.'],
                ],
                'tasks' => [
                    ['question' => 'How do I move something to a different time?', 'answer' => 'Change the **Time** beside it and press Save. The new hour applies once the scheduler is restarted or the next deployment goes out.'],
                    ['question' => 'How do I make one happen right now?', 'answer' => 'Press **Run now**. It runs while you wait and the result appears at the top of the screen and in the run list.'],
                    ['question' => 'How do I stop one?', 'answer' => 'Clear **Switched on** and save. Nothing is deleted; the scheduler simply skips it until you turn it back on.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Nothing has ever run.', 'answer' => 'The server is not calling the scheduler. Add the cron entry from the deployment guide — one line, every minute. Until then none of this happens on its own.'],
                    ['problem' => 'One of them says the last run failed.', 'answer' => 'The error is shown under it. Fix the cause and press Run now — most of these are safe to run again, because they work out what is still owed rather than blindly repeating.'],
                    ['problem' => 'A run is still marked as running.', 'answer' => 'That job started and never finished — the process was killed or the server restarted mid-run. Press Run now to try again.'],
                ],
            ],

            [
                'slug' => 'two-factor',
                'group' => 'Administration',
                'title' => 'Two-step verification',
                'icon' => 'shield',
                'route_name' => 'two-factor.setup',
                'permission' => null,
                'summary' => 'A code from your phone on top of your password, and what to do if you lose it.',
                'body' => <<<'MD'
                    Your password is one leaked spreadsheet away from being somebody else's, and
                    this system holds salaries, bank details and identity documents. A second
                    factor means knowing your password is no longer enough.

                    Scan the code with an authenticator app — Google Authenticator, Microsoft
                    Authenticator and 1Password all work — and type the six digits it shows.
                    From then on you are asked for a code each time you sign in.

                    **Save the recovery codes.** They are shown once, when you enrol, and each
                    signs you in once if you lose your phone. They are stored hashed, so nobody
                    can read them back to you — not even an administrator.
                    MD,
                'fields' => [
                    ['term' => 'Authenticator app', 'description' => 'An app on your phone that shows a six-digit code changing every thirty seconds. The code is worked out on the phone itself, so it keeps working with no signal.'],
                    ['term' => 'Recovery code', 'description' => 'A one-off code that stands in for your phone. You get eight; each works once.'],
                    ['term' => 'Required for your role', 'description' => 'An administrator has said everybody with your role must use a second factor. You are sent to set one up rather than refused, and you cannot turn it off again.'],
                ],
                'tasks' => [
                    ['question' => 'The app says my code is wrong every time.', 'answer' => 'Almost always the phone\'s clock. The code is worked out from the time, so a phone a minute out produces codes this system will not accept. Turn on "set automatically" in your phone\'s date and time settings and try again.'],
                    ['question' => 'I have lost my phone.', 'answer' => 'Use a recovery code on the sign-in screen — open "I do not have my phone". If you have none left, ask an administrator to clear your second factor; you can then set it up again on your new phone.'],
                    ['question' => 'I am running out of recovery codes.', 'answer' => 'Make a new set from the two-step verification screen. It asks for your password, and the old codes stop working the moment the new ones appear.'],
                    ['question' => 'How do I require it of my payroll staff?', 'answer' => 'Settings → Security, tick the roles that must use it. They are sent to set one up the next time they open anything, and cannot turn it off. Nobody is locked out by this — the requirement is a door, not a wall.'],
                    ['question' => 'Somebody has left and I need into their account.', 'answer' => 'Do not. Clear their second factor only to help them back in; to read their records, use your own account and the permissions you hold.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'I am stuck on the code screen and cannot get out.', 'answer' => 'Sign out is always available from that screen, whatever else is refused.'],
                    ['problem' => 'A code I just used will not work again.', 'answer' => 'That is deliberate. Each code works once — wait for the app to show the next one.'],
                    ['problem' => 'Somebody turned mine off and it was not me.', 'answer' => 'You will have had an email saying when and from where. Change your password immediately and tell your administrator: whoever did it had your password.'],
                ],
            ],
            [
                'slug' => 'settings',
                'group' => 'Administration',
                'title' => 'Settings',
                'icon' => 'cog',
                'route_name' => 'settings.edit',
                'permission' => 'settings.view',
                'summary' => 'Company details, branding, formats and the switches that affect everyone.',
                'body' => <<<'MD'
                    The system-wide settings, in tabs: branding, company details, regional formats,
                    email, attendance rules and payroll.

                    These apply to everybody, so a change here is felt across the whole system.
                    Most take effect on the next page load; nothing needs a deployment.
                    MD,
                'fields' => [
                    ['term' => 'Primary colour', 'description' => 'What you press: buttons, links, the screen you are on, the app icon. The ten lighter and darker shades are worked out from it.'],
                    ['term' => 'Secondary colour', 'description' => 'Supporting surfaces: the tinted background, the dark navigation column and a second series on a chart.'],
                    ['term' => 'Tertiary colour', 'description' => 'Highlights that are neither an action nor a status.'],
                    ['term' => 'Background', 'description' => 'Light leaves the shell neutral. Tinted washes the page with the faintest secondary shade. Dark inverts the navigation column, tinted by the secondary colour.'],
                    ['term' => 'Logo', 'description' => 'Replaces the company name in the sidebar and on the sign-in page, and appears on salary slips.'],
                    ['term' => 'Browser tab icon', 'description' => 'The small icon on the browser tab. Until one is uploaded it shows your initials on a primary-to-secondary gradient, matching the icon in the navigation column.'],
                    ['term' => 'Email notifications enabled', 'description' => 'The master switch for outgoing email. Off means nothing is sent, whatever the notification console says.'],
                    ['term' => 'Send mail through', 'description' => 'Where messages actually go. Left on the environment file, MAIL_MAILER in .env decides; choose an SMTP server to set the host and credentials here instead, or the log to send nothing at all.'],
                    ['term' => 'Password (mail server)', 'description' => 'Stored encrypted and never shown again. Leave the box empty to keep the one already saved; ticking Remove clears it.'],
                    ['term' => 'Encryption', 'description' => 'STARTTLS is usual on port 587 and SSL/TLS on 465. Left on "decide from the port", the connection works it out.'],
                    ['term' => 'Require location to punch', 'description' => 'When on, a check-in or check-out without a location is refused.'],
                    ['term' => 'Let employees check in and out themselves', 'description' => 'Off hides the punch buttons and closes the mobile API to punching at the same moment. Attendance is then entered by HR, and people can still raise corrections.'],
                    ['term' => 'Close punches that were never punched out', 'description' => 'Somebody who punches in and forgets keeps their pay — the day counts present on the check-in — but the hours stay at nought while the punch is open, and it stays open indefinitely. On, a nightly job closes yesterday\'s at the end of their shift, marks the day as needing a correction, and writes to the employee. Off, the day keeps reading "still working" until somebody notices.'],
                    ['term' => 'Treat unmarked past working days as absent', 'description' => 'On, a past working day nobody recorded is an absence and becomes loss of pay. Off, it shows as “Not marked” and is paid, so a forgotten punch costs nobody their salary.'],
                    ['term' => 'Financial year starts in', 'description' => 'Used by the payroll cost report and the joiner and exit figures, which then run April to March and are labelled FY 2026–27. Leave allocations stay on the calendar year.'],
                    ['term' => 'Pay for overtime', 'description' => 'Off — as it starts — the hours worked beyond a full day are recorded and reported but nobody is paid for them. On, they appear as an Overtime line on the salary slip.'],
                    ['term' => 'Rate is a share of', 'description' => 'Whether an overtime hour is priced off basic salary or off gross earnings. Overtime is never part of that figure itself: nobody is paid overtime on overtime.'],
                    ['term' => 'Times the ordinary rate', 'description' => 'The multiplier. Two is what section 59 of the Factories Act 1948 asks for.'],
                    ['term' => 'Most hours paid in a month', 'description' => 'Hours past this are still recorded and still shown on the slip, simply not paid. Zero means no limit.'],
                    ['term' => 'Eligible for overtime pay', 'description' => 'On the employee\'s own record, not here. People on a manager\'s grade are usually left out.'],
                ],
                'tasks' => [
                    ['question' => 'How do I change the look to our brand?', 'answer' => 'On the **Branding** tab set the three colours, choose a background and upload the logo and tab icon. The layout preview under the pickers repaints as you move them, so you see the real thing before saving.'],
                    ['question' => 'We want the dark sidebar we had in our old system.', 'answer' => 'Choose **Dark** under Background. The navigation column inverts and takes its darkest tones from your secondary colour, so it is your dark rather than a generic grey.'],
                    ['question' => 'Which of the three colours should I set first?', 'answer' => 'Primary. It is the one people see most — every button, link and active screen. Secondary and tertiary only need to sit beside it without clashing.'],
                    ['question' => 'Somebody forgot to punch out. Have they lost the day?', 'answer' => 'No. A day counts as present on the check-in, so pay is never affected by a missing punch-out. What is lost is the hours: a session\'s length is only recorded when it closes, so the day reads nought hours until somebody fixes it. Turn on **Close punches that were never punched out** and the system closes it at the end of their shift overnight, marks it, and emails them to correct the time.'],
                    ['question' => 'Somebody\'s punch card says "still working" days later.', 'answer' => 'They never punched out and nothing closes it on its own. Switch on **Close punches that were never punched out** under Attendance settings; until then the fix is a correction request from the employee.'],
                    ['question' => 'People keep forgetting to punch and losing pay.', 'answer' => 'Turn **Treat unmarked past working days as absent** off. Those days then read as "Not marked" and are paid, and HR can fill in the real times at leisure.'],
                    ['question' => 'We want HR to record attendance rather than employees.', 'answer' => 'Turn **Let employees check in and out themselves** off. The buttons disappear, the API refuses a punch, and HR records days under Daily Roster.'],
                    ['question' => 'How do I stop all email while we test?', 'answer' => 'Turn **Email notifications** off on the Email tab. In-app notifications carry on as normal.'],
                    ['question' => 'How do I change our mail provider?', 'answer' => 'On the Email tab set **Send mail through** to an SMTP server, fill in the host, port, username and password, save, then press **Send test**. The mail server\'s own reply is shown, so you know before anybody relies on it.'],
                    ['question' => 'I changed the mail server but nothing sends.', 'answer' => 'Press **Send test** — it reports what the server actually said, whether that is a refused connection or a rejected password. A queue worker picks the change up on its next job.'],
                    ['question' => 'Do we have to pay overtime?', 'answer' => 'No — it arrives switched off, and the extra hours are recorded and reported all the same. Turn **Pay for overtime** on under the Payroll tab when you want them paid, and leave the individual people who should not be paid it unticked on their own record.'],
                    ['question' => 'How is an overtime hour priced?', 'answer' => 'The month\'s basic (or gross, if you choose that) is divided by the month\'s own working days and the hours in a working day, then multiplied by the rate you set. February pays slightly more an hour than March, because the same salary covers fewer days. The rate used is printed on the slip beside the hours, so an old slip still explains itself after you change the setting.'],
                    ['question' => 'Will overtime change what we deduct?', 'answer' => 'Yes, where a deduction is charged on gross wages — state insurance is. Overtime joins gross before the deductions are worked out, which is what the law expects.'],
                    ['question' => 'Will testing on staging email our staff?', 'answer' => 'No. Anywhere but production every message goes to the one inbox named in the environment file instead of the person it names, and the console says so at the top when that is happening. Who it would have reached is kept on the message as an X-Original-To header.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'The logo does not appear on salary slips.', 'answer' => 'PDFs cannot render SVG reliably, so an SVG logo is skipped there. Upload a PNG if you want it on slips.'],
                    ['problem' => 'A colour was refused when saving.', 'answer' => 'Each one has to be a six-digit hex such as #2563EB. A name like "teal" or an rgb() value is not accepted.'],
                    ['problem' => 'The mail password box looks empty after saving.', 'answer' => 'It is meant to. The password is stored encrypted and never rendered back; an empty box means "keep what is saved". The placeholder shows dots when one is stored.'],
                    ['problem' => 'Mail still goes through the old server.', 'answer' => 'Check the line under the sender fields — it says what is actually in force. If it still names the old one, the transport is probably still set to the environment file.'],
                    ['problem' => 'The interface did not change after saving.', 'answer' => 'Reload the page. The theme is written into each page as it is served, so a tab left open still shows the old one.'],
                ],
            ],
            [
                'slug' => 'letters',
                'group' => 'People',
                'title' => 'Letters',
                'icon' => 'document',
                'route_name' => 'letters.index',
                'permission' => 'letters.view',
                'summary' => 'Offer, appointment, confirmation, increment, experience and the rest — issued on the company letterhead.',
                'body' => <<<'MD'
                    Ten kinds of letter, grouped by when they are issued: the joining set (offer,
                    appointment, confirmation), the pay ones (increment, salary certificate), the
                    leaving ones (experience, relieving, no objection) and the ones asked for in
                    between (employment and address proof, warning).

                    Pick the person and the letter, fill in the few things the letter needs, read
                    the preview and issue it. Everything else — their code, their designation,
                    their joining date, their current pay, the company's own details — is read
                    from the record, so an increment letter works out its own percentage and a
                    salary certificate quotes a figure nobody had to retype.

                    Once issued, a letter is numbered and its words are fixed. Rewording the
                    template afterwards changes the next letter and leaves this one exactly as it
                    went out, which is what somebody holding a signed copy would expect.
                    MD,
                'fields' => [
                    ['term' => 'Reference', 'description' => 'The letter\'s number, e.g. BSPL/INC/2026/0007 — the company, the kind of letter, the year and a running count.'],
                    ['term' => 'Effective from', 'description' => 'For a confirmation or an increment, the date the change takes effect, which is not the date the letter was written.'],
                    ['term' => 'Emailed', 'description' => 'Whether it has gone out. Either way the employee can download it from their own My Letters screen.'],
                ],
                'tasks' => [
                    ['question' => 'How do I issue a letter?', 'answer' => 'From **Issue a letter**, or from the button on the employee\'s own profile. Choose the letter, fill in what it asks for — an increment wants the new CTC and the date — read the preview and issue it.'],
                    ['question' => 'Can I change what a letter says?', 'answer' => 'Administration, **Wording**. Each letter has its own template with the values you can drop in. Letters already issued keep their own words.'],
                    ['question' => 'Somebody has lost their appointment letter.', 'answer' => 'They can download it themselves from My Letters. You can also open it here and email it again.'],
                    ['question' => 'A figure on the letter is wrong.', 'answer' => 'Correct it at the source — the salary structure, the joining date, the company details — then issue the letter again. Editing the template will not fix a wrong figure.'],
                    ['question' => 'How do I reword a letter?', 'answer' => 'Administration → **Letter Wording**, pick the letter and edit it. The body is a rich text editor: headings, bold, lists, tables and links are on the toolbar, and a line you put on its own line stays there. **Reset** puts the shipped wording back.'],
                    ['question' => 'Who signs the letters?', 'answer' => 'Whoever is chosen under **Signed by** when the letter is issued, from the company\'s signatories. The company default is offered first. Manage the list under Companies → Signatories.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'An experience or relieving letter is refused.', 'answer' => 'Those need a last working day. Record the exit on the employee\'s profile first.'],
                    ['problem' => 'The letter shows a dash where a figure should be.', 'answer' => 'That value is not on the record. A dash where the CTC belongs means there is no salary structure in force; a dash for a manager means nobody is set as their reporting line.'],
                    ['problem' => 'The logo is missing from the PDF.', 'answer' => 'PDFs cannot render SVG reliably, so an SVG logo is skipped. Upload a PNG on the company record.'],
                ],
            ],
            [
                'slug' => 'letter-wording',
                'group' => 'Administration',
                'title' => 'Letter wording',
                'icon' => 'pencil',
                'route_name' => 'letter-templates.index',
                'permission' => 'letters.manage-templates',
                'summary' => 'What each kind of letter says, in your own words.',
                'body' => <<<'MD'
                    Every letter ships with wording that reads properly on day one. This is where
                    you change it to your own.

                    Write in markdown. A line break is kept as a line break, so a block of details
                    stays a block rather than running into a paragraph. Drop in a value with
                    `{{ token }}` — the list of what is available for that letter is beside the
                    editor, and a value nobody has filled in prints as a dash rather than a gap.

                    Templates are substituted, never run. Whatever you type stays text, so the
                    worst you can do to a letter is word it badly.

                    Changes apply to the next letter issued. Letters already sent keep their own
                    words.
                    MD,
                'tasks' => [
                    ['question' => 'How do I see what my wording looks like?', 'answer' => '**Preview with sample values** renders what is on screen — not what was last saved — against an example employee, so you can try a paragraph before keeping it.'],
                    ['question' => 'I have made a mess of one.', 'answer' => '**Reset to the original** puts that letter back to the wording it shipped with. Nothing else is affected.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'A placeholder prints as a dash.', 'answer' => 'Either the token is misspelled — check the list beside the editor — or that value is genuinely empty on the employee\'s record.'],
                ],
            ],
            [
                'slug' => 'my-letters',
                'group' => 'My workspace',
                'title' => 'My letters',
                'icon' => 'document',
                'route_name' => 'my-letters.index',
                'permission' => 'letters.view-own',
                'summary' => 'The letters the company has issued to you.',
                'body' => <<<'MD'
                    Your appointment letter, any increment letters, a confirmation, an experience
                    certificate — whatever has been issued to you, kept here for you to download
                    whenever you need it.

                    Each one is a PDF on the company letterhead, exactly as it was issued.
                    MD,
                'tasks' => [
                    ['question' => 'I need a salary certificate for a bank.', 'answer' => 'Ask HR to issue one. It appears here as soon as they do, and they can email it to you at the same time.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'A letter I was sent is not here.', 'answer' => 'Only letters issued through the system appear. Anything HR sent you by email before this was set up will not.'],
                ],
            ],
            [
                'slug' => 'my-assets',
                'group' => 'My workspace',
                'title' => 'My assets',
                'icon' => 'tag',
                'route_name' => 'my-assets.index',
                'permission' => 'assets.view-own',
                'summary' => 'Company property issued to you, and what you have handed back.',
                'body' => <<<'MD'
                    A laptop, a phone, a SIM card, an access card — anything the company has
                    handed you is listed here, with the day it was issued and the condition it
                    was in at the time.

                    That condition matters. It is recorded when you receive something and again
                    when you give it back, so a scratch that was already there stays already
                    there.

                    You cannot mark something returned yourself. Hand it to whoever is receiving
                    it and they record it, which is what makes the record worth anything. You get
                    an email receipt the moment they do — keep it.
                    MD,
                'tasks' => [
                    ['question' => 'Something I hold is not listed.', 'answer' => 'It was never recorded on the register. Tell HR or IT so it can be added — an unrecorded asset is one nobody can prove you returned.'],
                    ['question' => 'I am leaving. What do I have to give back?', 'answer' => 'Everything under "With you now". Hand it over before your last working day; a relieving letter is issued on the understanding that nothing is outstanding.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Something I returned still shows as with me.', 'answer' => 'Whoever received it has not recorded it yet. Ask them to, and check back — your receipt email is what confirms it.'],
                    ['problem' => 'My laptop was damaged or lost.', 'answer' => 'Say so straight away rather than at handover. The condition it comes back in is recorded either way; telling somebody early is what turns it into a repair instead of a dispute.'],
                ],
            ],
            [
                'slug' => 'assets',
                'group' => 'People',
                'title' => 'Asset register',
                'icon' => 'tag',
                'route_name' => 'assets.index',
                'permission' => 'assets.view',
                'summary' => 'What the company owns, who is holding each of it, and getting it back.',
                'body' => <<<'MD'
                    Every laptop, phone, SIM, access card and vehicle the company owns, each with
                    a tag, and each showing who has it right now.

                    **What is asked for depends on the kind.** A laptop has a serial number, a SIM
                    card has a number that is dialled, an access card has a badge number and
                    nothing else. Choose the kind first and the form asks for the right things.

                    **Issuing and taking back.** An asset is with at most one person at a time, so
                    one that is already out has to be taken back before it can be issued again.
                    Both the state it went out in and the state it came back in are recorded,
                    which is what settles an argument about when something was damaged. A handover
                    straight from one person to another is recorded as a return and an issue, so
                    both periods are kept.

                    **On the way out.** Somebody leaving with company property is flagged on their
                    employee page and again on the relieving letter screen. It does not stop the
                    letter being issued — whoever signs it may have a good reason — but nobody
                    signs it without being told.
                    MD,
                'tasks' => [
                    ['question' => 'Issue a laptop to somebody', 'answer' => 'Open the asset and press "Issue to somebody". Record the condition honestly: it is what protects both sides later.'],
                    ['question' => 'Somebody is leaving', 'answer' => 'Their employee page lists what they still hold. Take each back before their last day; the relieving letter screen warns you if anything is outstanding.'],
                    ['question' => 'A laptop moved from one person to another', 'answer' => 'Take it back from the first, then issue it to the second. Two records rather than one, which is what lets you say who had it when.'],
                    ['question' => 'Something came back broken', 'answer' => 'Record the condition as damaged and set where it goes to "In repair". It cannot be issued to anybody while it is in that state.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'I cannot issue an asset.', 'answer' => 'It is either already with somebody, or marked in repair, lost or retired. The status on the asset says which.'],
                    ['problem' => 'A branch manager cannot see an asset.', 'answer' => 'Assets are scoped by branch. One with no branch set is visible to everybody; set the branch on the asset to scope it.'],
                    ['problem' => 'I cannot remove an asset.', 'answer' => 'Take it back from whoever holds it first. Removing it is a soft delete, so who held it and when is kept either way.'],
                ],
            ],
            [
                'slug' => 'settlements',
                'group' => 'Payroll',
                'title' => 'Full and final settlements',
                'icon' => 'scale',
                'route_name' => 'settlements.index',
                'permission' => 'settlements.view',
                'summary' => 'What somebody is owed, or owes, on their last day.',
                'body' => <<<'MD'
                    When somebody leaves, three things are worked out from the record and the law:

                    **Salary for the final month**, prorated across the month's own days. If payroll
                    already published a slip for that month, this line is left off — a settlement
                    that quietly pays September twice is worse than one that leaves it out.

                    **Leave encashment**, on the leave types that carry forward. An allowance that
                    lapses at the end of the year does not become money because somebody left in
                    November, so casual and sick leave are not encashed.

                    **Gratuity**, under the Payment of Gratuity Act: fifteen days' wages for each
                    completed year, at last drawn basic times 15/26, once five years are complete,
                    capped at the statutory maximum. Five years means five *completed* years — four
                    years and 364 days is not eligible.

                    Everything else is your judgement and is typed in against a named line: a bonus
                    that was promised, a laptop that never came back, an advance still outstanding.

                    **Every line keeps its reasoning beside its number.** "Gratuity ₹9,23,077" is
                    not something anybody can check; "8 completed years at 15/26 of a last drawn
                    basic of 2,00,000" is. That sentence is printed on the statement.

                    **Approving fixes the figures.** Nothing is recalculated afterwards, because
                    from that moment somebody is holding a statement of them.
                    MD,
                'tasks' => [
                    ['question' => 'Somebody has left', 'answer' => 'The register lists anybody gone with nothing prepared. Open one of them, check the last working day, and press Work it out — nothing is written down until you prepare it.'],
                    ['question' => 'They still have the laptop', 'answer' => 'The screen tells you what they hold. Take it back through the asset register if it is coming back, or add a recovery line if it is not.'],
                    ['question' => 'The settlement comes out negative', 'answer' => 'That is fine and it is shown as recoverable rather than payable. It means what is being recovered is more than what is owed.'],
                    ['question' => 'Somebody disputes a figure', 'answer' => 'The statement says how every line was arrived at, and the bases — last drawn basic, length of service, the days a month was divided by — are on the settlement screen under "How it was worked out".'],
                ],
                'troubleshooting' => [
                    ['problem' => 'Gratuity is missing for somebody who has been here years.', 'answer' => 'Check their date of joining, and that five years are genuinely complete on the last working day. The screen shows length of service to two places.'],
                    ['problem' => 'The final month is not on the settlement.', 'answer' => 'Payroll already published a slip for that month, so it has been paid. That is deliberate.'],
                    ['problem' => 'I need to change an approved settlement.', 'answer' => 'You cannot, by design — somebody is holding a statement of those figures. Raise the difference separately so there is a record of what changed and why.'],
                ],
            ],
            [
                'slug' => 'data-import',
                'group' => 'Administration',
                'title' => 'Data import',
                'icon' => 'upload',
                'route_name' => 'data-import.index',
                'permission' => 'data.import',
                'summary' => 'Bringing an old system\'s people, balances and pay in from spreadsheets.',
                'body' => <<<'MD'
                    Seven kinds of file, meant to be loaded in the order the screen lists them:
                    branches, departments and designations first, then the employees, then their
                    leave balances, their pay, and finally any attendance history you want to keep.
                    Each file names the ones above it by code, so working down the list is not a
                    suggestion — an employee row cannot find a branch that has not been loaded yet.

                    Every upload is checked before anything is written. You get back the number of
                    rows that are ready, the ones that are not, and the reason against each. Only
                    then is there a button to import, and the import happens in one transaction:
                    if something fails part way, the whole file is rolled back.

                    Loading the same file twice is safe. A row whose code already exists updates
                    that record instead of creating a second one, so the usual way to fix a bad
                    file is to correct it and upload it again.
                    MD,
                'fields' => [
                    ['term' => 'Ready to import', 'description' => 'Rows with nothing wrong with them. This is what the button will write.'],
                    ['term' => 'With problems', 'description' => 'Rows that cannot be used, each with the reason. Download them, fix them in the spreadsheet, and upload that file on its own.'],
                    ['term' => 'Added and updated', 'description' => 'How the import went. "Updated" means the code was already in the system.'],
                    ['term' => 'Skipped', 'description' => 'Rows left alone because they had problems, when you chose to import the good ones anyway.'],
                ],
                'tasks' => [
                    ['question' => 'Where do I start?', 'answer' => 'Download the template for what you are loading. It carries the exact column names and one example row. Paste your export into it and save as CSV — in Excel, File then Save as, then pick CSV.'],
                    ['question' => 'Do my column names have to match exactly?', 'answer' => 'No. Case, spaces and hyphens are all ignored, and the common names an old system uses are recognised — "Emp ID" for the employee code, "DOJ" for the joining date. The column reference on each screen lists what else is read.'],
                    ['question' => 'Will everybody be emailed when I import employees?', 'answer' => 'No. Logins are created but nothing is sent. Send each person their credentials from their own screen when you are ready for them to sign in.'],
                    ['question' => 'The manager is further down the file than their team.', 'answer' => 'That is fine. Reporting lines are joined up after the whole file has been read.'],
                    ['question' => 'My file has 40,000 rows.', 'answer' => 'Split it, or ask whoever runs the server to load it with `php artisan hrms:import`. The browser is limited to 20,000 rows at a time.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'The file was refused before it was even read.', 'answer' => 'It is missing a column that is required. The message names which one. Download the template and compare the header row.'],
                    ['problem' => 'Every row says the branch does not exist.', 'answer' => 'The branch column wants the branch code, not its name — BLR rather than Bengaluru Head Office. Load the branches first if you have not.'],
                    ['problem' => 'The dates came in wrong.', 'answer' => 'Dates are read day first: 01/04/2023 is the first of April. Use YYYY-MM-DD if you want to be certain.'],
                    ['problem' => 'I imported the wrong file.', 'answer' => 'Nothing here deletes, so a wrong import leaves records rather than removing them. Correct the file and upload it again — the same codes will be updated — and remove anything created in error from its own screen.'],
                ],
            ],
            [
                'slug' => 'activity-log',
                'group' => 'Administration',
                'title' => 'Activity log',
                'icon' => 'history',
                'route_name' => 'activity.index',
                'permission' => 'activity.view',
                'summary' => 'Who did what, and when — for the questions that come up later.',
                'body' => <<<'MD'
                    A record of the actions people take: who signed in, who approved what, who
                    changed a salary structure.

                    It is written automatically and cannot be edited from the interface, which is
                    the point of keeping it.
                    MD,
                'tasks' => [
                    ['question' => 'How do I find out who approved a leave request?', 'answer' => 'Filter by that user or search the reference. The decision and the remarks are both recorded.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'An action is not in the log.', 'answer' => 'Reading a screen is not logged — only changes are. Very old entries may also have been cleared.'],
                ],
            ],
            [
                'slug' => 'my-profile',
                'group' => 'Administration',
                'title' => 'My profile',
                'icon' => 'user',
                'route_name' => 'profile.edit',
                'summary' => 'Your own details, photo and password.',
                'body' => <<<'MD'
                    Your own record: the details you can change yourself, your photo, and your
                    password.

                    Anything that affects pay or reporting — your designation, your branch, who you
                    report to — is changed by HR rather than by you.
                    MD,
                'tasks' => [
                    ['question' => 'How do I change my password?', 'answer' => 'Use the password section here. You need your current password, and the new one must be at least eight characters.'],
                    ['question' => 'How do I correct my job title?', 'answer' => 'Ask HR. Designation, branch and reporting line are set on your employee record, not here.'],
                ],
                'troubleshooting' => [
                    ['problem' => 'This screen says I have no employee record.', 'answer' => 'Your login is not linked to an employee. Ask HR to link them — until then, screens about "my" data have nothing to show.'],
                ],
            ],
        ];
    }
}
