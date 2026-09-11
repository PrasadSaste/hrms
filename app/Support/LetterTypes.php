<?php

namespace App\Support;

/**
 * Every letter the company issues to an employee.
 *
 * The same catalogue idea as permissions and notifications: this list is what
 * the interface renders, so adding a letter is an entry here and nothing else.
 * The wording an administrator saves lives in `letter_templates` and is layered
 * over these defaults; the values that fill the gaps come from the employee,
 * their pay and their company.
 *
 * Each entry declares:
 *   label        what it is called
 *   group        where it sits on the screen
 *   code         goes into the reference number, e.g. BSPL/OFR/2026/0007
 *   summary      one line for the card
 *   description  when it is issued and what it says
 *   fields       what the issuer has to type in, beyond what we already know
 *   placeholders token => what it stands for
 *   subject      the letter's own heading
 *   body         the default wording, in markdown
 *
 * Wording is substituted, never evaluated — no Blade, no `eval` — so the worst
 * an administrator can do to a letter is write it badly.
 */
final class LetterTypes
{
    public const OFFER = 'offer';

    public const APPOINTMENT = 'appointment';

    public const CONFIRMATION = 'confirmation';

    public const INCREMENT = 'increment';

    public const EXPERIENCE = 'experience';

    public const RELIEVING = 'relieving';

    public const NOC = 'noc';

    public const SALARY_CERTIFICATE = 'salary-certificate';

    public const EMPLOYMENT_PROOF = 'employment-proof';

    public const WARNING = 'warning';

    /** Values every letter can use, whatever its type. */
    public const COMMON_PLACEHOLDERS = [
        'employee_name' => 'Their full name',
        'first_name' => 'Their first name',
        'employee_code' => 'Their employee code',
        'designation' => 'Their job title',
        'department' => 'Their department',
        'branch' => 'The branch they work at',
        'company_name' => 'The company employing them',
        'date_of_joining' => 'The day they joined',
        'date_of_exit' => 'Their last working day, where there is one',
        'today' => 'The date the letter is issued',
        'reference' => 'The letter’s reference number',
        'signatory_name' => 'Who signs it',
        'signatory_designation' => 'Their title',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [

            // ------------------------------------------------------- joining
            self::OFFER => [
                'label' => 'Offer letter',
                'group' => 'Joining',
                'code' => 'OFR',
                'icon' => 'document',
                'summary' => 'The offer of employment, with the pay and the start date.',
                'description' => 'Issued once a candidate is selected and before they accept. '
                    .'It states the role, the pay offered and when they are expected to start.',
                'fields' => [
                    'offered_ctc' => ['label' => 'Offered CTC per year', 'type' => 'money', 'required' => true, 'help' => 'The annual cost to company being offered.'],
                    'start_date' => ['label' => 'Expected start date', 'type' => 'date', 'required' => true],
                    'accept_by' => ['label' => 'Accept by', 'type' => 'date', 'required' => false, 'help' => 'The date the offer lapses. Left empty, that sentence is dropped.'],
                    'reporting_to' => ['label' => 'Reporting to', 'type' => 'text', 'required' => false, 'help' => 'Defaults to their manager on record.'],
                ],
                'placeholders' => [
                    'offered_ctc' => 'The annual CTC offered, in words and figures',
                    'start_date' => 'When they are expected to start',
                    'accept_by' => 'The date the offer lapses',
                    'reporting_to' => 'Who they will report to',
                ],
                'subject' => 'Offer of employment',
                'body' => <<<'MD'
                    Dear {{ first_name }},

                    We are pleased to offer you the position of **{{ designation }}** at {{ company_name }}, based at our {{ branch }} office.

                    Your annual cost to company will be **{{ offered_ctc }}**. A detailed breakdown of the salary structure is set out in the appointment letter, which will be issued to you when you join.

                    We would like you to start on **{{ start_date }}**, reporting to {{ reporting_to }}.

                    This offer is subject to verification of the documents and references you have provided. Please confirm your acceptance by signing and returning a copy of this letter by **{{ accept_by }}**.

                    We look forward to having you with us.
                    MD,
            ],

            self::APPOINTMENT => [
                'label' => 'Appointment letter',
                'group' => 'Joining',
                'code' => 'APT',
                'icon' => 'badge',
                'summary' => 'The full terms of employment, issued when they join.',
                'description' => 'The formal terms: the role, the pay, the probation and notice '
                    .'periods, working hours and the standing obligations.',
                'fields' => [
                    'probation_months' => ['label' => 'Probation period (months)', 'type' => 'number', 'required' => true, 'default' => 6],
                    'working_hours' => ['label' => 'Working hours', 'type' => 'text', 'required' => false, 'help' => 'Defaults to the branch’s hours.'],
                ],
                'placeholders' => [
                    'ctc_annual' => 'Their annual CTC from the salary structure',
                    'monthly_gross' => 'Their monthly gross',
                    'basic_salary' => 'Their monthly basic',
                    'probation_months' => 'How long probation runs',
                    'notice_period_days' => 'The notice period on their record',
                    'working_hours' => 'The working hours',
                    'working_days' => 'The working week',
                    'reporting_to' => 'Who they report to',
                ],
                'subject' => 'Letter of appointment',
                'body' => <<<'MD'
                    Dear {{ first_name }},

                    Further to your acceptance of our offer, we are pleased to confirm your appointment as **{{ designation }}** in the {{ department }} department at {{ company_name }}, with effect from **{{ date_of_joining }}**.

                    **Employee code:** {{ employee_code }}
                    **Place of work:** {{ branch }}
                    **Reporting to:** {{ reporting_to }}
                    **Annual cost to company:** {{ ctc_annual }}
                    **Monthly gross:** {{ monthly_gross }}
                    **Basic salary:** {{ basic_salary }} a month

                    **Working week:** {{ working_days }}
                    **Working hours:** {{ working_hours }}

                    You will be on probation for **{{ probation_months }} months** from your date of joining. On satisfactory completion you will be confirmed in writing.

                    Either party may end this employment by giving **{{ notice_period_days }} days** written notice, or salary in lieu of it.

                    You are expected to keep the company's business, client and employee information confidential both during and after your employment, and to devote your whole working time to the company.

                    Please sign and return a copy of this letter in acceptance of these terms.
                    MD,
            ],

            self::CONFIRMATION => [
                'label' => 'Confirmation letter',
                'group' => 'Joining',
                'code' => 'CNF',
                'icon' => 'check',
                'summary' => 'Confirms the employee as permanent at the end of probation.',
                'description' => 'Issued when probation ends. Worth issuing on the day their '
                    .'record is confirmed, since that is also when their leave starts accruing at '
                    .'the confirmed rate.',
                'fields' => [
                    'effective_from' => ['label' => 'Confirmed with effect from', 'type' => 'date', 'required' => true, 'help' => 'Defaults to their confirmation date on record.'],
                    'revised_ctc' => ['label' => 'Revised CTC per year', 'type' => 'money', 'required' => false, 'help' => 'Only if the pay changes on confirmation. Left empty, that paragraph is dropped.'],
                ],
                'placeholders' => [
                    'effective_from' => 'The date they are confirmed from',
                    'probation_months' => 'How long they were on probation',
                    'revised_ctc' => 'The new CTC, where confirmation comes with one',
                    'ctc_annual' => 'Their current annual CTC',
                ],
                'subject' => 'Confirmation of employment',
                'body' => <<<'MD'
                    Dear {{ first_name }},

                    We are pleased to inform you that, following the satisfactory completion of your probation, your services with {{ company_name }} are confirmed with effect from **{{ effective_from }}**.

                    You continue as **{{ designation }}** in the {{ department }} department at our {{ branch }} office. All other terms of your appointment letter remain unchanged.

                    Your annual cost to company with effect from this date is **{{ revised_ctc }}**.

                    We thank you for your contribution so far and look forward to your continued association with us.
                    MD,
            ],

            // ------------------------------------------------------------ pay
            self::INCREMENT => [
                'label' => 'Increment letter',
                'group' => 'Pay',
                'code' => 'INC',
                'icon' => 'currency',
                'summary' => 'A salary revision, with the old and new figures.',
                'description' => 'Issued when pay is revised. The current CTC is read from the '
                    .'salary structure in force, so only the new figure has to be typed and the '
                    .'increase works itself out.',
                'fields' => [
                    'revised_ctc' => ['label' => 'Revised CTC per year', 'type' => 'money', 'required' => true],
                    'effective_from' => ['label' => 'With effect from', 'type' => 'date', 'required' => true],
                    'revised_designation' => ['label' => 'New designation', 'type' => 'text', 'required' => false, 'help' => 'Only if this is a promotion as well. Left empty, the title is unchanged.'],
                ],
                'placeholders' => [
                    'previous_ctc' => 'The CTC before this revision',
                    'revised_ctc' => 'The CTC after it',
                    'increase_amount' => 'The difference between the two',
                    'increase_percentage' => 'The increase as a percentage',
                    'effective_from' => 'The date the new pay starts',
                    'revised_designation' => 'Their title after the revision',
                ],
                'subject' => 'Revision of compensation',
                'body' => <<<'MD'
                    Dear {{ first_name }},

                    In recognition of your performance and contribution, we are pleased to inform you that your compensation has been revised with effect from **{{ effective_from }}**.

                    **Present cost to company:** {{ previous_ctc }} a year
                    **Revised cost to company:** {{ revised_ctc }} a year
                    **Increase:** {{ increase_amount }} ({{ increase_percentage }})
                    **Designation:** {{ revised_designation }}

                    All other terms and conditions of your employment remain unchanged.

                    We thank you for your efforts and look forward to your continued contribution.
                    MD,
            ],

            self::SALARY_CERTIFICATE => [
                'label' => 'Salary certificate',
                'group' => 'Pay',
                'code' => 'SAL',
                'icon' => 'receipt',
                'summary' => 'Certifies current pay, for a bank or a visa.',
                'description' => 'The letter a bank or consulate asks for. It states the role, the '
                    .'date of joining and the current salary, addressed to whoever asked.',
                'fields' => [
                    'addressed_to' => ['label' => 'Addressed to', 'type' => 'text', 'required' => false, 'help' => 'A bank or consulate. Left empty it reads "To whomsoever it may concern".'],
                    'purpose' => ['label' => 'Purpose', 'type' => 'text', 'required' => false, 'help' => 'e.g. a home loan application.'],
                ],
                'placeholders' => [
                    'addressed_to' => 'Who the letter is for',
                    'purpose' => 'Why it was asked for',
                    'ctc_annual' => 'Annual cost to company',
                    'monthly_gross' => 'Monthly gross salary',
                    'monthly_net' => 'Monthly take-home, from the most recent payslip',
                    'basic_salary' => 'Monthly basic',
                ],
                'subject' => 'Salary certificate',
                'body' => <<<'MD'
                    {{ addressed_to }}

                    This is to certify that **{{ employee_name }}** ({{ employee_code }}) has been employed with {{ company_name }} since **{{ date_of_joining }}** and currently holds the position of **{{ designation }}** in the {{ department }} department at our {{ branch }} office.

                    Their present compensation is as follows:

                    **Annual cost to company:** {{ ctc_annual }}
                    **Monthly gross salary:** {{ monthly_gross }}
                    **Monthly basic salary:** {{ basic_salary }}
                    **Most recent monthly net pay:** {{ monthly_net }}

                    This certificate is issued at the employee's request for {{ purpose }}.
                    MD,
            ],

            // ------------------------------------------------------- leaving
            self::EXPERIENCE => [
                'label' => 'Experience letter',
                'group' => 'Leaving',
                'code' => 'EXP',
                'icon' => 'badge',
                'summary' => 'Certifies the dates worked and the role held.',
                'description' => 'The letter a former employee shows their next employer. It '
                    .'states the period of service and the role, and nothing that was not asked for.',
                'fields' => [
                    'conduct' => ['label' => 'Conduct', 'type' => 'text', 'required' => false, 'default' => 'good', 'help' => 'A single word, e.g. good or satisfactory.'],
                ],
                'placeholders' => [
                    'period_of_service' => 'The dates worked, written out',
                    'tenure' => 'How long that was, in years and months',
                    'conduct' => 'How their conduct is described',
                    'last_designation' => 'The role they held when they left',
                ],
                'subject' => 'Experience certificate',
                'body' => <<<'MD'
                    **To whomsoever it may concern**

                    This is to certify that **{{ employee_name }}** ({{ employee_code }}) was employed with {{ company_name }} from **{{ date_of_joining }}** to **{{ date_of_exit }}**, a period of {{ tenure }}.

                    At the time of leaving they held the position of **{{ last_designation }}** in the {{ department }} department at our {{ branch }} office.

                    During their tenure with us we found their conduct to be {{ conduct }} and their performance satisfactory.

                    We wish them every success in their future endeavours.
                    MD,
            ],

            self::RELIEVING => [
                'label' => 'Relieving letter',
                'group' => 'Leaving',
                'code' => 'REL',
                'icon' => 'logout',
                'summary' => 'Confirms they have been relieved and dues are settled.',
                'description' => 'Issued on or after the last working day. It confirms the '
                    .'resignation was accepted, the handover completed and the dues settled.',
                'fields' => [
                    'resignation_date' => ['label' => 'Resignation received on', 'type' => 'date', 'required' => false],
                    'dues_settled' => ['label' => 'Full and final settlement complete', 'type' => 'boolean', 'required' => false, 'default' => true],
                ],
                'placeholders' => [
                    'resignation_date' => 'When they resigned',
                    'last_working_day' => 'Their last working day',
                    'settlement_sentence' => 'The sentence about dues, which changes with the tick box',
                    'last_designation' => 'The role they held when they left',
                ],
                'subject' => 'Relieving letter',
                'body' => <<<'MD'
                    Dear {{ first_name }},

                    This is with reference to your resignation dated **{{ resignation_date }}**.

                    We confirm that you have been relieved from the services of {{ company_name }} at the close of business on **{{ last_working_day }}**. You held the position of **{{ last_designation }}** in the {{ department }} department at the time of leaving.

                    {{ settlement_sentence }}

                    We thank you for your service and wish you the very best in your future endeavours.
                    MD,
            ],

            self::NOC => [
                'label' => 'No objection certificate',
                'group' => 'Leaving',
                'code' => 'NOC',
                'icon' => 'shield',
                'summary' => 'States the company has no objection to a stated purpose.',
                'description' => 'Usually asked for by a consulate for a visa, or by another '
                    .'employer. Say what the certificate is for and it is written into the letter.',
                'fields' => [
                    'purpose' => ['label' => 'No objection to', 'type' => 'text', 'required' => true, 'help' => 'e.g. travel to Singapore between 10 and 20 October 2026.'],
                    'addressed_to' => ['label' => 'Addressed to', 'type' => 'text', 'required' => false],
                ],
                'placeholders' => [
                    'purpose' => 'What there is no objection to',
                    'addressed_to' => 'Who the letter is for',
                ],
                'subject' => 'No objection certificate',
                'body' => <<<'MD'
                    {{ addressed_to }}

                    This is to certify that **{{ employee_name }}** ({{ employee_code }}) has been employed with {{ company_name }} since **{{ date_of_joining }}** and currently holds the position of **{{ designation }}**.

                    The company has **no objection** to {{ purpose }}.

                    Their employment with us remains unaffected, and they are expected to resume their duties as usual thereafter.

                    This certificate is issued at the employee's request.
                    MD,
            ],

            // ------------------------------------------------------ in between
            self::EMPLOYMENT_PROOF => [
                'label' => 'Employment and address proof',
                'group' => 'On request',
                'code' => 'EMP',
                'icon' => 'home',
                'summary' => 'Confirms current employment and the address on record.',
                'description' => 'The letter asked for when opening a bank account or renting a '
                    .'home. It confirms employment and states the address held on file.',
                'fields' => [
                    'addressed_to' => ['label' => 'Addressed to', 'type' => 'text', 'required' => false],
                ],
                'placeholders' => [
                    'addressed_to' => 'Who the letter is for',
                    'employee_address' => 'The address on their record',
                    'ctc_annual' => 'Annual cost to company',
                ],
                'subject' => 'Employment and address verification',
                'body' => <<<'MD'
                    {{ addressed_to }}

                    This is to certify that **{{ employee_name }}** ({{ employee_code }}) is a permanent employee of {{ company_name }}, working as **{{ designation }}** in the {{ department }} department at our {{ branch }} office since **{{ date_of_joining }}**.

                    The address recorded with us is:

                    {{ employee_address }}

                    Their annual cost to company is {{ ctc_annual }}.

                    This letter is issued at the employee's request and may be relied upon for verification purposes.
                    MD,
            ],

            self::WARNING => [
                'label' => 'Warning letter',
                'group' => 'On request',
                'code' => 'WRN',
                'icon' => 'info',
                'summary' => 'A formal warning, with what happened and what must change.',
                'description' => 'Kept deliberately plain: what happened, when, what is expected '
                    .'instead, and by when. It becomes part of the employee record.',
                'fields' => [
                    'subject_matter' => ['label' => 'Concerning', 'type' => 'text', 'required' => true, 'help' => 'e.g. repeated late arrival.'],
                    'incident_details' => ['label' => 'What happened', 'type' => 'textarea', 'required' => true],
                    'expected_action' => ['label' => 'What is expected', 'type' => 'textarea', 'required' => true],
                    'respond_by' => ['label' => 'Respond by', 'type' => 'date', 'required' => false],
                ],
                'placeholders' => [
                    'subject_matter' => 'What the warning concerns',
                    'incident_details' => 'What happened',
                    'expected_action' => 'What is expected instead',
                    'respond_by' => 'When a written explanation is due',
                ],
                'subject' => 'Letter of warning — {{ subject_matter }}',
                'body' => <<<'MD'
                    Dear {{ first_name }},

                    This letter concerns **{{ subject_matter }}**.

                    {{ incident_details }}

                    This does not meet the standard expected of you as {{ designation }} at {{ company_name }}.

                    {{ expected_action }}

                    Please treat this as a formal warning. We expect your written explanation by **{{ respond_by }}**. A copy of this letter will be placed on your employee record.

                    We trust you will take this in the right spirit and that no further action will be necessary.
                    MD,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? $key;
    }

    public static function code(string $key): string
    {
        return self::all()[$key]['code'] ?? strtoupper(substr($key, 0, 3));
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * The catalogue arranged by the heading it sits under.
     *
     * Keys are preserved: the screens need the type's key, not its position.
     */
    public static function grouped(): array
    {
        return collect(self::all())
            ->groupBy(fn (array $type) => $type['group'], preserveKeys: true)
            ->map(fn ($group) => $group->all())
            ->all();
    }

    /** The fields the issuer has to fill in for one type. */
    public static function fields(string $key): array
    {
        return self::all()[$key]['fields'] ?? [];
    }

    /** Every placeholder a type may use, its own and the common ones. */
    public static function placeholders(string $key): array
    {
        return array_merge(self::COMMON_PLACEHOLDERS, self::all()[$key]['placeholders'] ?? []);
    }

    /** @return array<string, string> key => label, for a picker */
    public static function options(): array
    {
        return collect(self::all())->map(fn (array $type) => $type['label'])->all();
    }
}
