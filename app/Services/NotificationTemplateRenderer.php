<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Support\Markdown;
use App\Support\NotificationEvents;
use App\Support\Placeholders;

/**
 * Turns a stored template into the words that get sent.
 *
 * Substitution is a plain string replacement of {{ token }} against a map of
 * values. Templates are never compiled, evaluated or passed to Blade, so
 * whatever an administrator types stays text: the worst they can do to the
 * message is write it badly.
 */
class NotificationTemplateRenderer
{
    /** Replace every placeholder in a piece of text. */
    public function render(?string $template, array $data): string
    {
        // The substitution itself is shared with letters, which have the same
        // need and the same rule: text in, text out, nothing evaluated.
        return Placeholders::render($template, $data, fn () => '—');
    }

    /**
     * The subject, body and action for one event's email.
     *
     * @return array{subject: string, body: string, action_label: string, action_url: string}
     */
    public function email(string $key, array $data, array $overrides = []): array
    {
        $template = $this->template($key, $overrides);
        $data = $this->withCommon($data);

        return [
            'subject' => $this->render($template['subject'] ?? '', $data),
            'body' => $this->render($template['body'] ?? '', $data),
            'action_label' => $this->render($template['action_label'] ?? '', $data),
            'action_url' => $this->render($template['action_url'] ?? '', $data),
        ];
    }

    /**
     * The title and message for one event's in-app notification.
     *
     * @return array{title: string, message: string, url: string}
     */
    public function database(string $key, array $data, array $overrides = []): array
    {
        $template = $this->template($key, $overrides);
        $data = $this->withCommon($data);

        return [
            'title' => $this->render($template['title'] ?? '', $data),
            'message' => $this->render($template['message'] ?? '', $data),
            'url' => $data['url'] ?? $this->render($template['action_url'] ?? '', $data),
        ];
    }

    /**
     * The stored template for an event, with anything the caller is holding in
     * a form laid over the top.
     *
     * The console previews and test-sends what is on screen rather than what
     * was last saved, so an administrator can try wording out before keeping
     * it. Blank fields fall through to what is stored.
     */
    protected function template(string $key, array $overrides = []): array
    {
        $template = NotificationTemplate::resolve($key) ?? [];

        foreach (['subject', 'body', 'action_label', 'title', 'message'] as $field) {
            $value = $overrides[$field] ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                $template[$field] = $value;
            }
        }

        return $template;
    }

    /**
     * Convert a rendered body to HTML for an email or a preview.
     *
     * Raw HTML in the template is escaped rather than passed through, so a
     * stray tag shows up as text instead of running in the browser of whoever
     * opens the preview.
     *
     * Hard breaks, like a letter: a block of details written one per line —
     * "Reference: ..." above "Working days: ..." — has to arrive that way. HTML
     * collapses the newlines otherwise and the whole block reads as one run-on
     * line, which is what these emails used to do. Prose that needs to flow is
     * written as one line in the catalogue rather than wrapped.
     */
    public function html(string $markdown): string
    {
        return Markdown::html($markdown, hardBreaks: true);
    }

    /**
     * Add the values every template may use, without overwriting anything the
     * caller has already provided.
     */
    public function withCommon(array $data): array
    {
        return $data + [
            'company_name' => Setting::get('company_name', config('app.name')),
            'recipient_name' => 'there',
            'app_url' => url('/'),
        ];
    }

    /**
     * Stand-in values for a preview, so an administrator can see the shape of a
     * message without waiting for a real one.
     */
    public function sampleData(string $key): array
    {
        $samples = array_merge(self::SAMPLES, self::EVENT_SAMPLES[$key] ?? []);
        $event = NotificationEvents::find($key)['placeholders'] ?? [];
        $data = [];

        foreach (array_keys($event) as $token) {
            $data[$token] = $samples[$token] ?? ucfirst(str_replace('_', ' ', $token));
        }

        // The common placeholders describe the installation rather than the
        // example, so they keep their real values in a preview.
        return $this->withCommon($data + ['recipient_name' => $samples['recipient_name']]);
    }

    /** Values that mean something different depending on the event. */
    private const EVENT_SAMPLES = [
        NotificationEvents::PAYSLIP_PUBLISHED => [
            'period' => 'September 2026',
        ],
        NotificationEvents::LEAVE_CANCELLED => [
            'status' => 'cancelled',
            'remarks' => 'Plans changed, no longer needed.',
        ],
        NotificationEvents::LEAVE_REJECTED => [
            'status' => 'rejected',
            'remarks' => 'The team is short-staffed that week.',
        ],
        NotificationEvents::REGULARIZATION_ACTIONED => [
            'period' => '09 Oct 2026',
        ],
    ];

    /** Example values used for previews and test sends. */
    private const SAMPLES = [
        'first_name' => 'Priya',
        'employee_name' => 'Priya Sharma',
        'user_name' => 'Priya Sharma',
        'recipient_name' => 'Priya Sharma',
        'employee_code' => 'EMP0042',
        'designation' => 'Senior Analyst',
        'department' => 'Finance',
        'branch' => 'Head Office',
        'date_of_joining' => '01 Apr 2026',
        'manager' => 'Anil Kumar',
        'email' => 'priya.sharma@example.com',
        'temporary_password' => 'Tf7k-92Xa',
        'login_url' => '#',
        'reset_url' => '#',
        'url' => '#',
        'expires_in_minutes' => '60',
        'leave_type' => 'Casual Leave',
        'period' => '12 Oct 2026 to 14 Oct 2026',
        'days' => '3',
        'reason' => 'Family function out of town.',
        'applied_on' => '02 Oct 2026',
        'contact' => '+91 98765 43210',
        'reference' => 'LR-000123',
        'status' => 'approved',
        'approver' => 'Anil Kumar',
        'reviewer' => 'Anil Kumar',
        'actioned_on' => '03 Oct 2026',
        'remarks' => 'Approved. Please hand over the monthly close.',
        'date' => '09 Oct 2026',
        'requested_check_in' => '09:30 AM',
        'requested_check_out' => '06:30 PM',
        'slip_number' => 'PS-2026-09-0042',
        'period_start' => '01 Sep 2026',
        'period_end' => '30 Sep 2026',
        'paid_days' => '22',
        'working_days' => '22',
        'gross' => '82,500.00',
        'deductions' => '9,350.00',
        'net_pay' => '73,150.00',
        'payment_date' => '30 Sep 2026',
        'payment_reference' => 'NEFT-88213',
        'title' => 'Office closed on Friday',
        'body' => 'The Head Office will be closed on Friday for maintenance. Please work from home.',
        'author' => 'Anil Kumar',
        'published_on' => '05 Oct 2026',
    ];
}
