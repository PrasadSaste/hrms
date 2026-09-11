<?php

namespace App\Support;

/**
 * Every notification the HRMS can send.
 *
 * This is the single list the administration console renders, so adding an
 * event here is enough to make it manageable from the interface: its channels,
 * its wording and its placeholders all come from this catalogue, with any
 * overrides layered on top from the notification_templates table.
 *
 * Each entry declares:
 *   group        where it sits on the console
 *   label        what it is called
 *   description  when it fires and who receives it
 *   channels     which delivery channels are possible at all
 *   placeholders token => what it stands for
 *   email        default subject and markdown body
 *   database     default title and one-line message
 */
final class NotificationEvents
{
    public const EMPLOYEE_WELCOME = 'employee.welcome';

    public const ACCOUNT_CREDENTIALS = 'account.credentials';

    public const ACCOUNT_PASSWORD_RESET_BY_ADMIN = 'account.password-reset';

    public const PASSWORD_RESET_LINK = 'password.reset-link';

    public const LEAVE_SUBMITTED = 'leave.submitted';

    public const LEAVE_APPROVED = 'leave.approved';

    public const LEAVE_REJECTED = 'leave.rejected';

    public const LEAVE_CANCELLED = 'leave.cancelled';

    public const REGULARIZATION_SUBMITTED = 'attendance.regularization-submitted';

    public const REGULARIZATION_ACTIONED = 'attendance.regularization-actioned';

    public const TWO_FACTOR_ENABLED = 'security.two-factor-enabled';

    public const TWO_FACTOR_DISABLED = 'security.two-factor-disabled';

    public const PUNCH_NOT_CLOSED = 'attendance.punch-not-closed';

    public const GEOFENCE_BREACH = 'attendance.geofence-breach';

    public const GEOFENCE_REPORT = 'attendance.geofence-report';

    public const BGV_INVITED = 'bgv.invited';

    public const BGV_SUBMITTED = 'bgv.submitted';

    public const BGV_CHANGES_REQUESTED = 'bgv.changes-requested';

    public const BGV_VERIFIED = 'bgv.verified';

    public const PAYSLIP_PUBLISHED = 'payroll.payslip';

    public const LETTER_ISSUED = 'letter.issued';

    public const ANNOUNCEMENT_PUBLISHED = 'announcement.published';

    public const PROBATION_CONFIRMED = 'people.probation-confirmed';

    public const CELEBRATIONS_TODAY = 'people.celebrations';

    public const REMINDER_WAITING = 'reminders.waiting-on-you';

    public const REMINDER_DOCUMENTS = 'reminders.documents-outstanding';

    public const PAYROLL_READINESS = 'payroll.readiness';

    public const ASSET_ISSUED = 'assets.issued';

    public const ASSET_RETURNED = 'assets.returned';

    public const SETTLEMENT_READY = 'settlements.ready';

    public const SETTLEMENT_PAID = 'settlements.paid';

    /** Placeholders every event can use. */
    public const COMMON_PLACEHOLDERS = [
        'company_name' => 'Your company name',
        'recipient_name' => 'The name of the person receiving this',
        'app_url' => 'A link to the portal',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [

            // ------------------------------------------------------ onboarding
            self::EMPLOYEE_WELCOME => [
                'group' => 'Onboarding',
                'label' => 'Welcome a new employee',
                'description' => 'Sent to an employee when their record is created, with their sign-in details.',
                'channels' => ['mail'],
                'placeholders' => [
                    'first_name' => 'Their first name',
                    'employee_name' => 'Their full name',
                    'employee_code' => 'Their employee code',
                    'designation' => 'Their job title',
                    'department' => 'Their department',
                    'branch' => 'Their branch',
                    'date_of_joining' => 'The day they start',
                    'manager' => 'Who they report to',
                    'email' => 'Their sign-in email',
                    'temporary_password' => 'The password issued to them',
                    'login_url' => 'A link to the sign-in page',
                ],
                'email' => [
                    'subject' => 'Welcome to {{ company_name }}, {{ first_name }}!',
                    'body' => <<<'MD'
                        # Welcome to {{ company_name }}, {{ first_name }}

                        We are glad to have you on board. Your employee record has been created and your details are below.

                        **Employee code:** {{ employee_code }}
                        **Designation:** {{ designation }}
                        **Department:** {{ department }}
                        **Branch:** {{ branch }}
                        **Date of joining:** {{ date_of_joining }}
                        **Reports to:** {{ manager }}

                        You can sign in to the employee portal with these credentials. You will be asked to choose your own password the first time you sign in.

                        **Email:** {{ email }}
                        **Temporary password:** {{ temporary_password }}

                        From the portal you can check in and out, apply for leave, view your salary slips and keep your personal details current.

                        If anything above looks wrong, reply to this email and HR will correct it.
                        MD,
                    'action' => ['label' => 'Sign in to the portal', 'url' => '{{ login_url }}'],
                ],
            ],

            self::ACCOUNT_CREDENTIALS => [
                'group' => 'Accounts',
                'label' => 'Account created',
                'description' => 'Sent when an administrator creates a login, with the temporary password.',
                'channels' => ['mail'],
                'placeholders' => [
                    'user_name' => 'The name on the account',
                    'email' => 'Their sign-in email',
                    'temporary_password' => 'The password issued to them',
                    'login_url' => 'A link to the sign-in page',
                ],
                'email' => [
                    'subject' => 'Your {{ company_name }} account is ready',
                    'body' => <<<'MD'
                        # Your account is ready

                        Hello {{ user_name }},

                        An account has been created for you on {{ company_name }}. Sign in with the details below. You will be asked to choose your own password on first use.

                        **Email:** {{ email }}
                        **Temporary password:** {{ temporary_password }}

                        If you did not expect this email, please tell your HR team.
                        MD,
                    'action' => ['label' => 'Sign in', 'url' => '{{ login_url }}'],
                ],
            ],

            self::ACCOUNT_PASSWORD_RESET_BY_ADMIN => [
                'group' => 'Accounts',
                'label' => 'Password reset by an administrator',
                'description' => 'Sent when an administrator issues a new temporary password.',
                'channels' => ['mail'],
                'placeholders' => [
                    'user_name' => 'The name on the account',
                    'email' => 'Their sign-in email',
                    'temporary_password' => 'The new password issued to them',
                    'login_url' => 'A link to the sign-in page',
                ],
                'email' => [
                    'subject' => 'Your {{ company_name }} password has been reset',
                    'body' => <<<'MD'
                        # Your password has been reset

                        Hello {{ user_name }},

                        An administrator has reset the password on your {{ company_name }} account. Sign in with the temporary password below and choose a new one straight away.

                        **Email:** {{ email }}
                        **Temporary password:** {{ temporary_password }}

                        If you did not expect this email, please tell your HR team.
                        MD,
                    'action' => ['label' => 'Sign in', 'url' => '{{ login_url }}'],
                ],
            ],

            self::PASSWORD_RESET_LINK => [
                'group' => 'Accounts',
                'label' => 'Forgotten password link',
                'description' => 'Sent when someone asks to reset their own password from the sign-in page.',
                'channels' => ['mail'],
                'placeholders' => [
                    'user_name' => 'The name on the account',
                    'reset_url' => 'The one-time reset link',
                    'expires_in_minutes' => 'How long the link lasts',
                ],
                'email' => [
                    'subject' => 'Reset your {{ company_name }} password',
                    'body' => <<<'MD'
                        # Reset your password

                        Hello {{ user_name }},

                        We received a request to reset the password on your {{ company_name }} account. The link below expires in {{ expires_in_minutes }} minutes.

                        If you did not ask for a reset, no action is needed and your password stays as it is.
                        MD,
                    'action' => ['label' => 'Reset password', 'url' => '{{ reset_url }}'],
                ],
            ],

            // ----------------------------------------------------------- leave
            self::TWO_FACTOR_ENABLED => [
                'group' => 'Security',
                'label' => 'Two-factor turned on',
                'description' => 'Sent to the person themselves when a second factor is added to '
                    .'their account, so that somebody else doing it does not go unnoticed.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'name' => 'Whose account',
                    'when' => 'When it happened',
                    'ip' => 'The address it was done from',
                    'url' => 'A link to their security settings',
                ],
                'email' => [
                    'subject' => 'Two-factor authentication was turned on',
                    'body' => <<<'MD'
                        # Two-factor authentication is now on

                        Hello {{ name }},

                        A second factor was added to your account on **{{ when }}** from {{ ip }}. From now on you will be asked for a code from your authenticator app when you sign in.

                        **If that was not you, tell your administrator now** — somebody else may have your password.
                        MD,
                    'action' => ['label' => 'Review your security settings', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Two-factor turned on',
                    'message' => 'A second factor was added to your account on {{ when }}.',
                ],
            ],

            self::TWO_FACTOR_DISABLED => [
                'group' => 'Security',
                'label' => 'Two-factor turned off',
                'description' => 'Sent to the person themselves when their second factor is '
                    .'removed. This is the one somebody needs to see: it is what an intruder '
                    .'would do first.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'name' => 'Whose account',
                    'when' => 'When it happened',
                    'ip' => 'The address it was done from',
                    'url' => 'A link to their security settings',
                ],
                'email' => [
                    'subject' => 'Two-factor authentication was turned off',
                    'body' => <<<'MD'
                        # Two-factor authentication is now off

                        Hello {{ name }},

                        The second factor on your account was removed on **{{ when }}** from {{ ip }}. Your password is now the only thing protecting it.

                        **If that was not you, change your password and tell your administrator immediately.**
                        MD,
                    'action' => ['label' => 'Turn it back on', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Two-factor turned off',
                    'message' => 'The second factor on your account was removed on {{ when }}.',
                ],
            ],

            self::PUNCH_NOT_CLOSED => [
                'group' => 'Attendance',
                'label' => 'You forgot to punch out',
                'description' => 'Sent to the employee the morning after a day they punched in '
                    .'for and never punched out of. The day is still counted present — pay is '
                    .'never affected — but the hours on it are a guess until they correct it.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'employee_name' => 'Who forgot',
                    'first_name' => 'Their first name',
                    'date' => 'The day in question',
                    'check_in' => 'When they punched in',
                    'assumed_check_out' => 'The time the system closed the day at',
                    'hours' => 'The hours that leaves recorded',
                    'url' => 'A link to raise the correction',
                ],
                'email' => [
                    'subject' => 'You did not punch out on {{ date }}',
                    // One line per paragraph: these are rendered with hard
                    // breaks on, so a wrapped sentence would be chopped in half.
                    'body' => <<<'MD'
                        # Your punch on {{ date }} was left open

                        Hello {{ first_name }},

                        You punched in at **{{ check_in }}** on {{ date }} and never punched out, so we closed the day at **{{ assumed_check_out }}** — the end of your shift. That records **{{ hours }}**.

                        **Your pay is not affected.** The day counts as present either way. But the hours are our assumption rather than what actually happened, and only you know when you really left.

                        If that time is wrong, raise a correction and your manager can approve it.
                        MD,
                    'action' => ['label' => 'Correct the times', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'You forgot to punch out',
                    'message' => 'Your day on {{ date }} was closed at {{ assumed_check_out }}. Correct it if that is wrong.',
                ],
            ],

            self::LEAVE_SUBMITTED => [
                'group' => 'Leave',
                'label' => 'Leave applied for',
                'description' => 'Sent to the line manager and branch manager when someone applies for leave.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'employee_name' => 'Who applied',
                    'employee_code' => 'Their employee code',
                    'leave_type' => 'The kind of leave',
                    'period' => 'The dates requested',
                    'days' => 'How many working days',
                    'reason' => 'The reason they gave',
                    'applied_on' => 'When they applied',
                    'contact' => 'How to reach them while away',
                    'reference' => 'The request reference',
                    'url' => 'A link to the request',
                ],
                'email' => [
                    'subject' => 'Leave request from {{ employee_name }}',
                    'body' => <<<'MD'
                        # Leave request awaiting your approval

                        **{{ employee_name }}** ({{ employee_code }}) has applied for leave and needs your decision.

                        **Leave type:** {{ leave_type }}
                        **Period:** {{ period }}
                        **Working days:** {{ days }}
                        **Applied on:** {{ applied_on }}
                        **Contact while away:** {{ contact }}

                        **Reason given**

                        {{ reason }}

                        Reference {{ reference }}.
                        MD,
                    'action' => ['label' => 'Review the request', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'New leave request',
                    'message' => '{{ employee_name }} applied for {{ leave_type }} ({{ period }}).',
                ],
            ],

            self::LEAVE_APPROVED => [
                'group' => 'Leave',
                'label' => 'Leave approved',
                'description' => 'Sent to the employee when their leave is approved.',
                'channels' => ['mail', 'database'],
                'placeholders' => self::leavePlaceholders(),
                'email' => [
                    'subject' => 'Your leave request has been approved',
                    'body' => <<<'MD'
                        # Your leave request was approved

                        Hello {{ first_name }},

                        Your request for **{{ leave_type }}** covering {{ period }} has been **approved**.

                        **Reference:** {{ reference }}
                        **Working days:** {{ days }}
                        **Decision by:** {{ approver }}
                        **Decided on:** {{ actioned_on }}

                        **Remarks**

                        {{ remarks }}

                        Your leave balance has been updated. Please hand over any ongoing work before you leave.
                        MD,
                    'action' => ['label' => 'View the request', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Leave approved',
                    'message' => 'Your {{ leave_type }} request for {{ period }} was approved.',
                ],
            ],

            self::LEAVE_REJECTED => [
                'group' => 'Leave',
                'label' => 'Leave rejected',
                'description' => 'Sent to the employee when their leave is turned down.',
                'channels' => ['mail', 'database'],
                'placeholders' => self::leavePlaceholders(),
                'email' => [
                    'subject' => 'Your leave request has been rejected',
                    'body' => <<<'MD'
                        # Your leave request was rejected

                        Hello {{ first_name }},

                        Your request for **{{ leave_type }}** covering {{ period }} has been **rejected**.

                        **Reference:** {{ reference }}
                        **Decision by:** {{ approver }}
                        **Decided on:** {{ actioned_on }}

                        **Reason given**

                        {{ remarks }}

                        Speak to your manager if you would like to discuss this, or apply again for different dates.
                        MD,
                    'action' => ['label' => 'View the request', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Leave rejected',
                    'message' => 'Your {{ leave_type }} request for {{ period }} was rejected.',
                ],
            ],

            self::LEAVE_CANCELLED => [
                'group' => 'Leave',
                'label' => 'Leave cancelled',
                'description' => 'Sent to the approver when an employee cancels a request that was already approved.',
                'channels' => ['mail', 'database'],
                'placeholders' => self::leavePlaceholders(),
                'email' => [
                    'subject' => '{{ employee_name }} cancelled their leave',
                    'body' => <<<'MD'
                        # Leave cancelled

                        **{{ employee_name }}** has cancelled their **{{ leave_type }}** for {{ period }}.

                        **Reference:** {{ reference }}
                        **Reason:** {{ remarks }}

                        The balance has been returned to them. No action is needed unless cover had been arranged.
                        MD,
                    'action' => ['label' => 'View the request', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Leave cancelled',
                    'message' => '{{ employee_name }} cancelled their {{ leave_type }} for {{ period }}.',
                ],
            ],

            // ------------------------------------------------------ attendance
            self::REGULARIZATION_SUBMITTED => [
                'group' => 'Attendance',
                'label' => 'Attendance correction requested',
                'description' => 'Sent to the approver when an employee asks to correct an attendance record.',
                'channels' => ['mail', 'database'],
                'placeholders' => self::regularizationPlaceholders(),
                'email' => [
                    'subject' => 'Attendance correction from {{ employee_name }}',
                    'body' => <<<'MD'
                        # Attendance correction awaiting your approval

                        **{{ employee_name }}** has asked to correct their attendance for **{{ date }}**.

                        **Requested check-in:** {{ requested_check_in }}
                        **Requested check-out:** {{ requested_check_out }}

                        **Reason given**

                        {{ reason }}
                        MD,
                    'action' => ['label' => 'Review the request', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Attendance correction request',
                    'message' => '{{ employee_name }} requested a correction for {{ date }}.',
                ],
            ],

            self::REGULARIZATION_ACTIONED => [
                'group' => 'Attendance',
                'label' => 'Attendance correction decided',
                'description' => 'Sent to the employee once their correction is approved or rejected.',
                'channels' => ['mail', 'database'],
                'placeholders' => self::regularizationPlaceholders(),
                'email' => [
                    'subject' => 'Your attendance correction was {{ status }}',
                    'body' => <<<'MD'
                        # Attendance correction {{ status }}

                        Hello {{ first_name }},

                        Your request to correct the attendance record for **{{ date }}** has been **{{ status }}**.

                        **Requested check-in:** {{ requested_check_in }}
                        **Requested check-out:** {{ requested_check_out }}
                        **Reviewed by:** {{ reviewer }}

                        **Remarks**

                        {{ remarks }}
                        MD,
                    'action' => ['label' => 'View your corrections', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Attendance correction {{ status }}',
                    'message' => 'Your correction for {{ date }} was {{ status }}.',
                ],
            ],

            self::GEOFENCE_BREACH => [
                'group' => 'Attendance',
                'label' => 'Punch made away from the branch',
                'description' => 'Sent the moment somebody punches in or out further from their '
                    .'branch than the allowed radius. Goes to the people named under '
                    .'Settings, Attendance.',
                'channels' => ['mail', 'database'],
                'placeholders' => self::geofencePlaceholders(),
                'email' => [
                    'subject' => '{{ employee_name }} recorded a {{ punch }} {{ distance }} from {{ branch }}',
                    'body' => <<<'MD'
                        # Punch away from the branch

                        **{{ employee_name }}** ({{ employee_code }}) recorded a **{{ punch }}** at **{{ punch_time }}**, **{{ distance }}** from **{{ branch }}**.

                        **Allowed radius:** {{ allowed_radius }}
                        **Where they were:** {{ location }}
                        **Department:** {{ department }}

                        There can be a good reason for this — a client visit, a site inspection, a phone with a poor fix indoors. This is a note to look, not a finding.
                        MD,
                    'action' => ['label' => 'See the day\'s flagged punches', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Punch away from {{ branch }}',
                    'message' => '{{ employee_name }} recorded a {{ punch }} {{ distance }} from {{ branch }}.',
                ],
            ],

            self::GEOFENCE_REPORT => [
                'group' => 'Attendance',
                'label' => 'Daily out-of-range punch report',
                'description' => 'The evening summary of every punch made outside the allowed '
                    .'radius that day. Goes to the same people as the individual alerts, and is '
                    .'sent even on a quiet day so its silence is not mistaken for nothing '
                    .'happening.',
                'channels' => ['mail'],
                'placeholders' => [
                    'date' => 'The day being reported',
                    'punch_count' => 'How many punches were outside the radius',
                    'employee_count' => 'How many people that was',
                    'summary' => 'A one-line summary of the day',
                    'report_table' => 'The table of flagged punches',
                    'url' => 'A link to the flagged punches for that day',
                ],
                'email' => [
                    'subject' => 'Out-of-range punches for {{ date }}: {{ punch_count }}',
                    'body' => <<<'MD'
                        # Punches away from the branch on {{ date }}

                        {{ summary }}

                        {{ report_table }}

                        Distances are measured from the branch's own coordinates to where the device reported the person standing, and were recorded at the moment of the punch.
                        MD,
                    'action' => ['label' => 'Open the day in the portal', 'url' => '{{ url }}'],
                ],
            ],

            // -------------------------------------- background verification
            self::BGV_INVITED => [
                'group' => 'Background verification',
                'label' => 'Verification requested',
                'description' => 'Sent to a new joiner with the list of documents they need to provide.',
                'channels' => ['mail', 'database'],
                'placeholders' => self::bgvPlaceholders(),
                'email' => [
                    'subject' => 'Next step at {{ company_name }}: your background verification',
                    'body' => <<<'MD'
                        # A few documents before you start

                        Hello {{ first_name }},

                        Welcome aboard. Before your first day we need to verify a few details. It takes about ten minutes and it is all done in the employee portal.

                        **What we need from you**

                        {{ checklist }}

                        **How it works**

                        1. Sign in to the portal and open **Background verification**.
                        2. Upload each document and fill in the few details asked beside it.
                        3. Submit the whole set when you are done.
                        4. Our HR team checks it and confirms your onboarding.

                        Please complete this by **{{ due_on }}**.

                        Scans and clear photographs are both fine, as long as every corner of the document is readable. If you cannot produce something on the list, submit the rest and tell HR why.
                        MD,
                    'action' => ['label' => 'Start my verification', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Background verification requested',
                    'message' => 'Please provide {{ required_count }} document(s) by {{ due_on }}.',
                ],
            ],

            self::BGV_SUBMITTED => [
                'group' => 'Background verification',
                'label' => 'Verification submitted for review',
                'description' => 'Sent to HR when a new joiner has provided everything and is waiting on a decision.',
                'channels' => ['mail', 'database'],
                'placeholders' => self::bgvPlaceholders(),
                'email' => [
                    'subject' => '{{ employee_name }} has submitted their background verification',
                    'body' => <<<'MD'
                        # Background verification awaiting your review

                        **{{ employee_name }}** ({{ employee_code }}) has provided their documents and is waiting to be onboarded.

                        **Designation:** {{ designation }}
                        **Branch:** {{ branch }}
                        **Date of joining:** {{ date_of_joining }}
                        **Submitted on:** {{ submitted_on }}
                        **Documents provided:** {{ uploaded_count }} of {{ required_count }}

                        {{ checklist }}

                        Open each document, accept the ones that are in order, and send back anything that is not. Clearing the last one onboards them.
                        MD,
                    'action' => ['label' => 'Review the documents', 'url' => '{{ review_url }}'],
                ],
                'database' => [
                    'title' => 'Verification ready to review',
                    'message' => '{{ employee_name }} submitted {{ uploaded_count }} document(s) for verification.',
                ],
            ],

            self::BGV_CHANGES_REQUESTED => [
                'group' => 'Background verification',
                'label' => 'More needed from the employee',
                'description' => 'Sent to the employee when something they provided was not accepted.',
                'channels' => ['mail', 'database'],
                'placeholders' => self::bgvPlaceholders(),
                'email' => [
                    'subject' => 'We need one more thing for your verification',
                    'body' => <<<'MD'
                        # Almost there

                        Hello {{ first_name }},

                        Thank you for sending your documents. We need a little more before we can finish your onboarding.

                        **What still needs your attention**

                        {{ outstanding }}

                        **Notes from our team**

                        {{ remarks }}

                        Open the portal, replace the documents listed above, and submit again. Nothing else you have already provided needs doing twice.
                        MD,
                    'action' => ['label' => 'Update my documents', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Verification needs your attention',
                    'message' => '{{ outstanding_count }} document(s) need replacing before we can finish.',
                ],
            ],

            self::BGV_VERIFIED => [
                'group' => 'Background verification',
                'label' => 'Verification cleared and onboarded',
                'description' => 'Sent to the employee once their documents are accepted and they are onboarded.',
                'channels' => ['mail', 'database'],
                'placeholders' => self::bgvPlaceholders(),
                'email' => [
                    'subject' => 'You are all set at {{ company_name }}',
                    'body' => <<<'MD'
                        # Your verification is complete

                        Hello {{ first_name }},

                        Your documents have been checked and accepted, and your onboarding is confirmed. There is nothing further for you to do.

                        **Employee code:** {{ employee_code }}
                        **Designation:** {{ designation }}
                        **Branch:** {{ branch }}
                        **Date of joining:** {{ date_of_joining }}
                        **Cleared on:** {{ reviewed_on }}

                        **Notes from our team**

                        {{ remarks }}

                        From the portal you can now check in and out, apply for leave and see your salary slips. Welcome aboard.
                        MD,
                    'action' => ['label' => 'Open the portal', 'url' => '{{ app_url }}'],
                ],
                'database' => [
                    'title' => 'Verification complete',
                    'message' => 'Your background verification is cleared and your onboarding is confirmed.',
                ],
            ],

            // --------------------------------------------------------- payroll
            self::PAYSLIP_PUBLISHED => [
                'group' => 'Payroll',
                'label' => 'Salary slip',
                'description' => 'Sent to the employee with their salary slip attached as a PDF.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'first_name' => 'Their first name',
                    'employee_name' => 'Their full name',
                    'period' => 'The pay period',
                    'slip_number' => 'The salary slip number',
                    'period_start' => 'First day of the period',
                    'period_end' => 'Last day of the period',
                    'paid_days' => 'Days paid',
                    'working_days' => 'Working days in the period',
                    'gross' => 'Gross earnings',
                    'deductions' => 'Total deductions',
                    'net_pay' => 'Net pay',
                    'payment_date' => 'When it was paid',
                    'payment_reference' => 'The payment reference',
                    'url' => 'A link to the salary slip',
                ],
                'email' => [
                    'subject' => 'Salary slip for {{ period }}',
                    'body' => <<<'MD'
                        # Salary slip for {{ period }}

                        Hello {{ first_name }},

                        Your salary slip for {{ period }} is attached to this email as a PDF.

                        **Slip number:** {{ slip_number }}
                        **Pay period:** {{ period_start }} to {{ period_end }}
                        **Paid days:** {{ paid_days }} of {{ working_days }}
                        **Gross earnings:** {{ gross }}
                        **Total deductions:** {{ deductions }}
                        **Net pay:** {{ net_pay }}

                        Please keep this document safe. If any figure looks wrong, contact the payroll team within seven days of receiving it.
                        MD,
                    'action' => ['label' => 'View it in the portal', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Salary slip available',
                    'message' => 'Your salary slip for {{ period }} is ready to view.',
                ],
            ],

            // --------------------------------------------------- announcements
            // -------------------------------------------------------- reminders
            self::REMINDER_WAITING => [
                'group' => 'Reminders',
                'label' => 'Something is waiting on you',
                'description' => 'Sent to an approver when leave requests or attendance '
                    .'corrections have been sitting unanswered. One message per person, '
                    .'listing everything, rather than one per request.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'first_name' => 'Their first name',
                    'total' => 'How many things are waiting',
                    'leave_count' => 'How many leave requests',
                    'regularization_count' => 'How many attendance corrections',
                    'oldest_days' => 'How long the oldest has been waiting, in days',
                    'items' => 'The list of what is waiting',
                    'url' => 'A link to the approvals',
                ],
                'email' => [
                    'subject' => '{{ total }} waiting on you at {{ company_name }}',
                    'body' => <<<'MD'
                        Hello {{ first_name }},

                        There are **{{ total }}** things waiting for your decision. The oldest has been waiting {{ oldest_days }} days.

                        {{ items }}

                        Nobody can plan around a request that has not been answered, so please have a look when you get a moment.
                        MD,
                    'action' => ['label' => 'Open them', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => '{{ total }} waiting on you',
                    'message' => 'The oldest has been waiting {{ oldest_days }} days.',
                    'url' => '{{ url }}',
                ],
            ],

            self::REMINDER_DOCUMENTS => [
                'group' => 'Reminders',
                'label' => 'Documents still outstanding',
                'description' => 'Sent to a joiner who was invited to upload their verification '
                    .'documents and has not. They are the only person who can act on it.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'first_name' => 'Their first name',
                    'employee_name' => 'Their full name',
                    'invited_on' => 'The day they were invited',
                    'waiting_days' => 'How long it has been, in days',
                    'due_on' => 'The day it is due, where one is set',
                    'outstanding' => 'What is still missing',
                    'url' => 'A link to their verification screen',
                ],
                'email' => [
                    'subject' => 'Your documents are still outstanding',
                    'body' => <<<'MD'
                        Hello {{ first_name }},

                        We asked for your verification documents on {{ invited_on }}, and they have not come through yet — that is {{ waiting_days }} days ago.

                        {{ outstanding }}

                        It only takes a few minutes, and some things cannot be finished until it is done.
                        MD,
                    'action' => ['label' => 'Upload them', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Your documents are still outstanding',
                    'message' => 'Asked for on {{ invited_on }}. It only takes a few minutes.',
                    'url' => '{{ url }}',
                ],
            ],

            self::PAYROLL_READINESS => [
                'group' => 'Payroll',
                'label' => 'The month is ready for payroll',
                'description' => 'Sent before payroll is run, listing anything that would make '
                    .'the figures wrong — days nobody recorded, leave still undecided.',
                'channels' => ['mail'],
                'placeholders' => [
                    'period' => 'The month being checked',
                    'verdict' => 'Whether anything needs attention',
                    'unmarked_count' => 'How many people have days nobody recorded',
                    'pending_leave_count' => 'How many leave requests are still undecided',
                    'details' => 'The list of what needs attention',
                    'url' => 'A link to payroll',
                ],
                'email' => [
                    'subject' => 'Payroll for {{ period }}: {{ verdict }}',
                    'body' => <<<'MD'
                        # {{ period }}

                        {{ verdict }}

                        {{ details }}

                        Anything left unresolved is paid on whatever the record says on the day payroll runs, so it is worth settling first.
                        MD,
                    'action' => ['label' => 'Open payroll', 'url' => '{{ url }}'],
                ],
            ],

            // ----------------------------------------------------------- assets
            self::ASSET_ISSUED => [
                'group' => 'Assets',
                'label' => 'An asset has been issued',
                'description' => 'Sent to somebody when company property is handed to them, '
                    .'so what they are holding and the state it was in is written down at the time.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'first_name' => 'Their first name',
                    'employee_name' => 'Their full name',
                    'asset_name' => 'What it is',
                    'asset_tag' => 'The tag on the side of it',
                    'asset_type' => 'What kind of thing it is',
                    'serial_number' => 'Its serial or reference number',
                    'issued_on' => 'The day it was handed over',
                    'condition_out' => 'The state it was in',
                    'remarks' => 'Anything noted at the time',
                ],
                'email' => [
                    'subject' => '{{ asset_name }} has been issued to you',
                    'body' => <<<'MD'
                        # {{ asset_name }}

                        Hello {{ first_name }}, the following has been issued to you on {{ issued_on }}.

                        **What:** {{ asset_type }} — {{ asset_name }}
                        **Tag:** {{ asset_tag }}
                        **Serial:** {{ serial_number }}
                        **Condition:** {{ condition_out }}
                        **Notes:** {{ remarks }}

                        It stays your responsibility until it is handed back, and it is expected back on your last working day. Please tell us straight away if it is lost or damaged.
                        MD,
                    'action' => ['label' => 'See what I hold', 'url' => '{{ app_url }}/my-assets'],
                ],
                'database' => [
                    'title' => '{{ asset_name }} issued to you',
                    'message' => '{{ asset_type }} ({{ asset_tag }}) was issued to you on {{ issued_on }}.',
                    'icon' => 'box',
                ],
            ],

            self::ASSET_RETURNED => [
                'group' => 'Assets',
                'label' => 'An asset has been returned',
                'description' => 'The receipt for handing something back. Worth sending because '
                    .'"I gave that back months ago" is otherwise one word against another.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'first_name' => 'Their first name',
                    'employee_name' => 'Their full name',
                    'asset_name' => 'What it is',
                    'asset_tag' => 'The tag on the side of it',
                    'asset_type' => 'What kind of thing it is',
                    'serial_number' => 'Its serial or reference number',
                    'returned_on' => 'The day it came back',
                    'condition_in' => 'The state it came back in',
                    'remarks' => 'Anything noted at the time',
                ],
                'email' => [
                    'subject' => '{{ asset_name }} has been returned — thank you',
                    'body' => <<<'MD'
                        # Received, with thanks

                        Hello {{ first_name }}, we have taken the following back on {{ returned_on }}. You are no longer responsible for it.

                        **What:** {{ asset_type }} — {{ asset_name }}
                        **Tag:** {{ asset_tag }}
                        **Condition on return:** {{ condition_in }}
                        **Notes:** {{ remarks }}

                        Keep this message: it is your receipt.
                        MD,
                ],
                'database' => [
                    'title' => '{{ asset_name }} returned',
                    'message' => 'We received {{ asset_name }} ({{ asset_tag }}) back on {{ returned_on }}.',
                    'icon' => 'check',
                ],
            ],

            // ----------------------------------------------------- settlement
            self::SETTLEMENT_READY => [
                'group' => 'Payroll',
                'label' => 'A settlement has been approved',
                'description' => 'Sent to somebody leaving once their full and final has been signed off, '
                    .'so the figures reach them before the money does rather than after it.',
                'channels' => ['mail'],
                'placeholders' => [
                    'first_name' => 'Their first name',
                    'employee_name' => 'Their full name',
                    'employee_code' => 'Their employee code',
                    'reference' => 'The settlement reference',
                    'last_working_day' => 'Their last working day',
                    'total_earnings' => 'Everything owed to them',
                    'total_deductions' => 'Everything being recovered',
                    'net_payable' => 'The net amount',
                    'net_label' => 'Whether that is payable or recoverable',
                ],
                'email' => [
                    'subject' => 'Your full and final settlement — {{ reference }}',
                    'body' => <<<'MD'
                        # Your settlement

                        Hello {{ first_name }}, your full and final settlement has been approved. Here is what it comes to.

                        **Reference:** {{ reference }}
                        **Last working day:** {{ last_working_day }}
                        **Total owed to you:** {{ total_earnings }}
                        **Total recovered:** {{ total_deductions }}
                        **{{ net_label }}:** {{ net_payable }}

                        The statement attached to your record sets out every line and how it was worked out. If any of it looks wrong, reply to this message before the payment is made — it is far easier to correct now than afterwards.
                        MD,
                ],
            ],

            self::SETTLEMENT_PAID => [
                'group' => 'Payroll',
                'label' => 'A settlement has been paid',
                'description' => 'The receipt. Worth sending because the alternative is somebody wondering '
                    .'for a fortnight whether the payment went out.',
                'channels' => ['mail'],
                'placeholders' => [
                    'first_name' => 'Their first name',
                    'reference' => 'The settlement reference',
                    'net_payable' => 'The net amount',
                    'net_label' => 'Whether that is payable or recoverable',
                    'settled_on' => 'The day it was paid',
                ],
                'email' => [
                    'subject' => 'Your settlement has been paid',
                    'body' => <<<'MD'
                        # Paid

                        Hello {{ first_name }}, your full and final settlement was paid on {{ settled_on }}.

                        **Reference:** {{ reference }}
                        **{{ net_label }}:** {{ net_payable }}

                        Keep this message: it is your receipt. Thank you for your time with us, and we wish you well.
                        MD,
                ],
            ],

            // ------------------------------------------------------- milestones
            self::PROBATION_CONFIRMED => [
                'group' => 'People',
                'label' => 'Probation has ended',
                'description' => 'Sent when somebody passes their confirmation date, so a '
                    .'confirmation letter is issued rather than forgotten.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'employee_name' => 'Their full name',
                    'first_name' => 'Their first name',
                    'employee_code' => 'Their employee code',
                    'designation' => 'Their job title',
                    'department' => 'Their department',
                    'branch' => 'Their branch',
                    'date_of_joining' => 'The day they started',
                    'date_of_confirmation' => 'The day their probation ended',
                    'manager' => 'Who they report to',
                    'url' => 'A link to their record',
                ],
                'email' => [
                    'subject' => '{{ employee_name }} is due confirmation',
                    'body' => <<<'MD'
                        {{ employee_name }} ({{ employee_code }}) reached the end of probation on **{{ date_of_confirmation }}** and has been moved off probation.

                        **Designation:** {{ designation }}
                        **Department:** {{ department }}
                        **Branch:** {{ branch }}
                        **Joined:** {{ date_of_joining }}
                        **Reports to:** {{ manager }}

                        They now earn leave at the confirmed rate. A confirmation letter has not been issued — that is still yours to do, and it is on the Letters screen.
                        MD,
                    'action' => ['label' => 'Open their record', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'Probation ended',
                    'message' => '{{ employee_name }} is off probation and due a confirmation letter.',
                    'url' => '{{ url }}',
                ],
            ],

            self::CELEBRATIONS_TODAY => [
                'group' => 'People',
                'label' => 'Birthdays and work anniversaries',
                'description' => 'The morning note about who is celebrating today. Sent to the '
                    .'people named under Attendance settings, falling back to HR.',
                'channels' => ['mail'],
                'placeholders' => [
                    'today' => 'Today’s date',
                    'birthdays' => 'Who has a birthday today',
                    'anniversaries' => 'Who has a work anniversary today, and how many years',
                    'url' => 'A link to the people list',
                ],
                'email' => [
                    'subject' => 'Celebrating today at {{ company_name }}',
                    'body' => <<<'MD'
                        Today is {{ today }}.

                        **Birthdays**

                        {{ birthdays }}

                        **Work anniversaries**

                        {{ anniversaries }}
                        MD,
                    'action' => ['label' => 'Open the people list', 'url' => '{{ url }}'],
                ],
            ],

            self::LETTER_ISSUED => [
                'group' => 'Letters',
                'label' => 'A letter has been issued',
                'description' => 'Sent to the employee when a letter is issued to them, with the '
                    .'letter attached. They can also fetch it themselves from My Letters.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'first_name' => 'Their first name',
                    'employee_name' => 'Their full name',
                    'letter_type' => 'What kind of letter it is',
                    'subject' => 'The letter’s own heading',
                    'reference' => 'Its reference number',
                    'issued_on' => 'The date it was issued',
                    'url' => 'A link to their letters',
                ],
                'email' => [
                    'subject' => '{{ letter_type }} — {{ reference }}',
                    'body' => <<<'MD'
                        Hello {{ first_name }},

                        Please find attached your **{{ letter_type }}**, issued on {{ issued_on }} under reference **{{ reference }}**.

                        A copy is also kept in the portal, so you can download it again whenever you need it without asking us.

                        If anything on it looks wrong, reply to this email and we will put it right.
                        MD,
                    'action' => ['label' => 'Open your letters', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => '{{ letter_type }} issued',
                    'message' => 'Your {{ letter_type }} ({{ reference }}) is ready to download.',
                ],
            ],

            self::ANNOUNCEMENT_PUBLISHED => [
                'group' => 'Announcements',
                'label' => 'Announcement published',
                'description' => 'Sent to the audience of an announcement when email delivery is switched on for it.',
                'channels' => ['mail', 'database'],
                'placeholders' => [
                    'title' => 'The announcement heading',
                    'body' => 'The announcement text',
                    'author' => 'Who posted it',
                    'published_on' => 'When it was posted',
                    'url' => 'A link to the announcement',
                ],
                'email' => [
                    'subject' => '{{ title }}',
                    'body' => <<<'MD'
                        # {{ title }}

                        {{ body }}

                        Posted by {{ author }} on {{ published_on }}.
                        MD,
                    'action' => ['label' => 'Read it in the portal', 'url' => '{{ url }}'],
                ],
                'database' => [
                    'title' => 'New announcement',
                    'message' => '{{ title }}',
                ],
            ],
        ];
    }

    /** The icon an in-app notification is listed with. */
    private const ICONS = [
        self::LEAVE_SUBMITTED => 'calendar',
        self::LEAVE_APPROVED => 'check',
        self::LEAVE_REJECTED => 'check',
        self::LEAVE_CANCELLED => 'calendar',
        self::REGULARIZATION_SUBMITTED => 'clock',
        self::REGULARIZATION_ACTIONED => 'clock',
        self::GEOFENCE_BREACH => 'map-pin',
        self::GEOFENCE_REPORT => 'map-pin',
        self::BGV_INVITED => 'shield',
        self::BGV_SUBMITTED => 'shield',
        self::BGV_CHANGES_REQUESTED => 'shield',
        self::BGV_VERIFIED => 'shield',
        self::PAYSLIP_PUBLISHED => 'currency',
        self::LETTER_ISSUED => 'document',
        self::ANNOUNCEMENT_PUBLISHED => 'megaphone',
    ];

    public static function icon(string $key): string
    {
        return self::ICONS[$key] ?? 'bell';
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /** Events keyed by the group they belong to, in catalogue order. */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::all() as $key => $event) {
            $grouped[$event['group']][$key] = $event;
        }

        return $grouped;
    }

    /** Every placeholder an event understands, including the common ones. */
    public static function placeholdersFor(string $key): array
    {
        $event = self::find($key);

        return array_merge($event['placeholders'] ?? [], self::COMMON_PLACEHOLDERS);
    }

    public static function supports(string $key, string $channel): bool
    {
        return in_array($channel, self::find($key)['channels'] ?? [], true);
    }

    /** @return array<string, string> */
    private static function leavePlaceholders(): array
    {
        return [
            'first_name' => 'The employee\'s first name',
            'employee_name' => 'The employee\'s full name',
            'employee_code' => 'Their employee code',
            'leave_type' => 'The kind of leave',
            'period' => 'The dates requested',
            'days' => 'How many working days',
            'reason' => 'The reason they gave',
            'status' => 'Approved, rejected or cancelled',
            'approver' => 'Who made the decision',
            'actioned_on' => 'When it was decided',
            'remarks' => 'The remarks left with the decision',
            'reference' => 'The request reference',
            'url' => 'A link to the request',
        ];
    }

    /** @return array<string, string> */
    private static function bgvPlaceholders(): array
    {
        return [
            'first_name' => 'The employee\'s first name',
            'employee_name' => 'The employee\'s full name',
            'employee_code' => 'Their employee code',
            'designation' => 'Their job title',
            'branch' => 'Their branch',
            'date_of_joining' => 'The day they start',
            'checklist' => 'The list of documents asked for, with their state',
            'outstanding' => 'Only the documents still needing work',
            'required_count' => 'How many documents were asked for',
            'uploaded_count' => 'How many have been provided',
            'outstanding_count' => 'How many still need work',
            'due_on' => 'The date it should be completed by',
            'submitted_on' => 'When the employee submitted it',
            'reviewed_on' => 'When it was decided',
            'reviewer' => 'Who reviewed it',
            'remarks' => 'The notes left with the decision',
            'status' => 'Where the case has got to',
            'url' => 'A link to the employee\'s own checklist',
            'review_url' => 'A link to the review screen for HR',
        ];
    }

    /** @return array<string, string> */
    private static function regularizationPlaceholders(): array
    {
        return [
            'first_name' => 'The employee\'s first name',
            'employee_name' => 'The employee\'s full name',
            'date' => 'The date being corrected',
            'requested_check_in' => 'The check-in time asked for',
            'requested_check_out' => 'The check-out time asked for',
            'reason' => 'The reason they gave',
            'status' => 'Approved or rejected',
            'reviewer' => 'Who reviewed it',
            'remarks' => 'The remarks left with the decision',
            'url' => 'A link to the request',
        ];
    }

    /** @return array<string, string> */
    private static function geofencePlaceholders(): array
    {
        return [
            'employee_name' => 'The employee\'s full name',
            'employee_code' => 'Their employee code',
            'department' => 'Their department',
            'branch' => 'The branch they belong to',
            'punch' => 'Check-in or check-out',
            'punch_time' => 'When the punch was made',
            'distance' => 'How far from the branch they were',
            'allowed_radius' => 'The radius allowed for that branch',
            'location' => 'The coordinates the device reported',
            'map_url' => 'A link to those coordinates on a map',
            'url' => 'A link to the day\'s flagged punches',
        ];
    }
}
