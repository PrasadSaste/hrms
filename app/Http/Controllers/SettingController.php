<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\BrandPalette;
use App\Services\MailSettings;
use App\Services\OvertimeService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(protected MailSettings $mail) {}

    public function edit(Request $request): View
    {
        abort_unless($request->user()->can('settings.view'), 403);

        return view('settings.edit', [
            'settings' => Setting::allValues(),
            'canManage' => $request->user()->can('settings.manage'),
            'timezones' => timezone_identifiers_list(),
            'mail' => $this->mail->current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:32'],
            'company_website' => ['nullable', 'url', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_tax_id' => ['nullable', 'string', 'max:64'],
            // allow_svg because a logo usually is one; BrandingController serves
            // these under a content policy that stops an SVG running scripts.
            'company_logo_file' => ['nullable', 'image:allow_svg', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
            'remove_company_logo' => ['nullable', 'boolean'],
            // Not the image rule: that one does not recognise .ico files.
            'company_favicon_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg,ico', 'max:512'],
            'remove_company_favicon' => ['nullable', 'boolean'],
            'brand_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_secondary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_tertiary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme_surface' => ['required', 'string', Rule::in(BrandPalette::SURFACES)],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'date_format' => ['required', 'string', 'max:24'],
            'employee_code_prefix' => ['required', 'string', 'max:8', 'alpha'],
            'employee_code_padding' => ['required', 'integer', 'between:2,8'],
            'financial_year_start_month' => ['required', 'integer', 'between:1,12'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],

            // Where mail goes. Left on "the environment file" nothing is
            // stored and .env keeps deciding; the SMTP fields are only
            // required once somebody chooses to send through a server here.
            'mail_transport' => ['nullable', 'string', Rule::in(array_keys(MailSettings::TRANSPORTS))],
            'mail_host' => ['exclude_unless:mail_transport,smtp', 'required', 'string', 'max:255'],
            'mail_port' => ['exclude_unless:mail_transport,smtp', 'required', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_scheme' => ['nullable', 'string', Rule::in(array_keys(MailSettings::SCHEMES))],
            'clear_mail_password' => ['nullable', 'boolean'],
            'email_notifications_enabled' => ['nullable', 'boolean'],
            'attendance_allow_self_punch' => ['nullable', 'boolean'],
            'attendance_auto_absent' => ['nullable', 'boolean'],
            'attendance_require_location' => ['nullable', 'boolean'],
            'attendance_auto_close_punches' => ['nullable', 'boolean'],
            'security_two_factor_required_roles' => ['nullable', 'array'],
            // The empty sentinel the form always posts is allowed through and
            // filtered out below; anything else must be a real role.
            'security_two_factor_required_roles.*' => ['nullable', 'string', Rule::in(Roles::all())],
            'attendance_geofence_enabled' => ['nullable', 'boolean'],
            'attendance_geofence_radius' => ['nullable', 'integer', 'between:20,50000'],
            'attendance_geofence_alert_recipients' => ['nullable', 'string', 'max:2000'],
            'attendance_geofence_alert_managers' => ['nullable', 'boolean'],
            'attendance_geofence_daily_report' => ['nullable', 'boolean'],
            'attendance_geofence_report_time' => ['nullable', 'date_format:H:i'],
            'payroll_overtime_enabled' => ['nullable', 'boolean'],
            'payroll_overtime_basis' => ['nullable', Rule::in(array_keys(OvertimeService::BASES))],
            'payroll_overtime_multiplier' => ['nullable', 'numeric', 'between:1,5'],
            'payroll_overtime_hours_per_day' => ['nullable', 'numeric', 'between:1,24'],
            'payroll_overtime_monthly_cap_hours' => ['nullable', 'numeric', 'between:0,400'],
            'payroll_tds_enabled' => ['nullable', 'boolean'],
            'settlement_per_day_divisor' => ['nullable', 'integer', 'between:1,31'],
            'settlement_gratuity_cap' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Stored as one comma-separated value, because a setting is a string
        // and TwoFactorPolicy is the only thing that reads it back.
        if ($request->has('security_two_factor_required_roles')) {
            $validated['security_two_factor_required_roles'] = implode(
                ',',
                array_values(array_intersect(
                    Roles::all(),
                    (array) ($validated['security_two_factor_required_roles'] ?? []),
                )),
            );
        } else {
            unset($validated['security_two_factor_required_roles']);
        }

        $validated['attendance_geofence_alert_recipients'] = $this->cleanRecipients(
            $validated['attendance_geofence_alert_recipients'] ?? null,
        );

        // A field the form did not send keeps whatever is stored. The screen
        // posts all of them, so this only matters to a caller sending part of
        // the form — which should not wipe the rest of it.
        foreach ([
            'attendance_geofence_radius', 'attendance_geofence_report_time',
            'payroll_overtime_basis', 'payroll_overtime_multiplier',
            'payroll_overtime_hours_per_day', 'payroll_overtime_monthly_cap_hours',
            'settlement_per_day_divisor', 'settlement_gratuity_cap',
        ] as $key) {
            if (($validated[$key] ?? null) === null) {
                unset($validated[$key]);
            }
        }

        if ($request->hasFile('company_logo_file')) {
            $this->deleteExistingLogo();
            Setting::put(
                'company_logo',
                $request->file('company_logo_file')->store('branding', 'public'),
                'branding',
            );
        } elseif ($request->boolean('remove_company_logo')) {
            $this->deleteExistingLogo();
            Setting::put('company_logo', null, 'branding');
        }

        if ($request->hasFile('company_favicon_file')) {
            $this->deleteExisting('company_favicon');
            Setting::put(
                'company_favicon',
                $request->file('company_favicon_file')->store('branding', 'public'),
                'branding',
            );
        } elseif ($request->boolean('remove_company_favicon')) {
            $this->deleteExisting('company_favicon');
            Setting::put('company_favicon', null, 'branding');
        }

        unset(
            $validated['company_logo_file'],
            $validated['remove_company_logo'],
            $validated['company_favicon_file'],
            $validated['remove_company_favicon'],
        );

        // A request that did not carry the mail block — an older form, a
        // partial post — leaves the mail server exactly as it was rather than
        // resetting it to the environment file and dropping the password.
        if ($request->filled('mail_transport')) {
            $this->storeMailPassword($request, $validated['mail_transport']);
        } else {
            unset($validated['mail_transport']);
        }

        unset($validated['mail_password'], $validated['clear_mail_password']);

        foreach (['brand_color', 'brand_secondary_color', 'brand_tertiary_color'] as $key) {
            $validated[$key] = strtolower($validated[$key]);
        }

        $booleans = [
            'email_notifications_enabled',
            'attendance_allow_self_punch',
            'attendance_auto_absent',
            'attendance_require_location',
            'attendance_auto_close_punches',
            'attendance_geofence_enabled',
            'attendance_geofence_alert_managers',
            'attendance_geofence_daily_report',
            'payroll_overtime_enabled',
            'payroll_tds_enabled',
        ];
        $groups = [
            'company_name' => 'company', 'company_email' => 'company', 'company_phone' => 'company',
            'company_website' => 'company', 'company_address' => 'company', 'company_tax_id' => 'company',
            'mail_from_address' => 'mail', 'mail_from_name' => 'mail', 'email_notifications_enabled' => 'mail',
            'attendance_allow_self_punch' => 'attendance', 'attendance_auto_absent' => 'attendance',
            'attendance_require_location' => 'attendance',
            'attendance_auto_close_punches' => 'attendance',
            'security_two_factor_required_roles' => 'security',
            'attendance_geofence_enabled' => 'attendance',
            'attendance_geofence_radius' => 'attendance',
            'attendance_geofence_alert_recipients' => 'attendance',
            'attendance_geofence_alert_managers' => 'attendance',
            'attendance_geofence_daily_report' => 'attendance',
            'attendance_geofence_report_time' => 'attendance',
            'payroll_overtime_enabled' => 'payroll',
            'payroll_tds_enabled' => 'payroll',
            'payroll_overtime_basis' => 'payroll',
            'payroll_overtime_multiplier' => 'payroll',
            'payroll_overtime_hours_per_day' => 'payroll',
            'payroll_overtime_monthly_cap_hours' => 'payroll',
            'settlement_per_day_divisor' => 'payroll',
            'settlement_gratuity_cap' => 'payroll',
            'brand_color' => 'branding',
            'brand_secondary_color' => 'branding',
            'brand_tertiary_color' => 'branding',
            'theme_surface' => 'branding',
            'mail_transport' => 'mail', 'mail_host' => 'mail', 'mail_port' => 'mail',
            'mail_username' => 'mail', 'mail_scheme' => 'mail',
        ];

        foreach ($validated as $key => $value) {
            Setting::put($key, $value, $groups[$key] ?? 'general');
        }

        foreach ($booleans as $key) {
            Setting::put($key, $request->boolean($key), $groups[$key] ?? 'general');
        }

        return back()->with('success', 'Settings saved.');
    }

    /**
     * The SMTP password, which is the one setting that must not be readable.
     *
     * An empty box means "leave what is stored alone", so an administrator can
     * change the host without retyping the password — and so the password is
     * never rendered back into the form to be re-posted. Clearing it is a
     * deliberate tick, and choosing any transport other than SMTP drops it,
     * because a password kept for a server nobody uses is only a liability.
     */
    protected function storeMailPassword(Request $request, string $transport): void
    {
        if ($transport !== 'smtp' || $request->boolean('clear_mail_password')) {
            Setting::putSecret(MailSettings::PASSWORD_KEY, null, 'mail');

            return;
        }

        if (filled($request->input('mail_password'))) {
            Setting::putSecret(MailSettings::PASSWORD_KEY, $request->string('mail_password')->toString(), 'mail');
        }
    }

    /**
     * Prove the settings work, before anybody relies on them.
     *
     * Sent through the mailer as it stands right now, with the failure
     * reported rather than swallowed: the whole point is to find out that the
     * credentials are wrong here, not at two in the morning from a queue log.
     */
    public function testMail(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $validated = $request->validate([
            'test_email' => ['required', 'email:rfc', 'max:255'],
        ], [
            'test_email.required' => 'Enter an address to send the test to.',
            'test_email.email' => 'Enter a valid email address to send the test to.',
        ]);

        $this->mail->apply();
        $address = $validated['test_email'];

        try {
            // Sent now rather than queued: a queued test would report success
            // and fail later in the worker, which is the opposite of a test.
            Mail::mailer(config('mail.default'))->raw(
                'This is a test message from '.config('app.name').", sent to check the mail settings.\n\n"
                    .'Sent at '.now()->format('d M Y, H:i').'.',
                fn (Message $message) => $message->to($address)->subject(config('app.name').' — mail settings test'),
            );
        } catch (\Throwable $e) {
            Log::warning('Mail settings test failed.', ['error' => $e->getMessage()]);

            return back()->with('error', 'The test could not be sent: '.$e->getMessage());
        }

        $redirected = config('mail.redirect.to');

        return back()->with('success', $redirected
            ? 'Test message sent. This is not production, so it went to '.$redirected.' rather than '.$address.'.'
            : 'Test message sent to '.$address.'.');
    }

    /**
     * Tidy the list of people the location alerts go to.
     *
     * Administrators type these as a list, with whatever separators come
     * naturally — commas, semicolons, one per line. Anything that is not an
     * address is dropped rather than saved and then silently failing to send.
     */
    protected function cleanRecipients(?string $value): string
    {
        return collect(preg_split('/[\s,;]+/', (string) $value) ?: [])
            ->map(fn (string $email) => trim($email))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->implode(', ');
    }

    /** Remove the stored logo file, if there is one. */
    protected function deleteExistingLogo(): void
    {
        $this->deleteExisting('company_logo');
    }

    /** Remove the file a branding setting points at, if there is one. */
    protected function deleteExisting(string $key): void
    {
        if ($existing = Setting::get($key)) {
            Storage::disk('public')->delete($existing);
        }
    }
}
