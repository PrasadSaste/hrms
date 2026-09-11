<x-app-layout title="Settings">
    <x-page-header title="Settings" subtitle="Company details, formats and notification behaviour" />

    @unless ($canManage)
        <x-alert type="info" class="mb-4" :dismissible="false">
            You can view these settings but not change them.
        </x-alert>
    @endunless

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <x-tabs key="settings" :tabs="[
                    'branding' => 'Branding',
                    'company' => 'Company',
                    'regional' => 'Regional & formats',
                    'email' => 'Email',
                    'attendance' => 'Attendance',
                    'payroll' => 'Payroll',
                    'security' => 'Security',
                ]">

                <div data-tab-panel="branding">
                @php
                    $brand = app(App\Services\BrandPalette::class);

                    // Each colour, its label and what it is actually used for,
                    // so nobody has to guess which of three pickers to move.
                    $roles = [
                        'primary' => [
                            'field' => 'brand_color',
                            'label' => 'Primary',
                            'default' => App\Services\BrandPalette::DEFAULT,
                            'help' => 'What you press: buttons, links, the screen you are on, the app icon.',
                        ],
                        'secondary' => [
                            'field' => 'brand_secondary_color',
                            'label' => 'Secondary',
                            'default' => App\Services\BrandPalette::DEFAULT_SECONDARY,
                            'help' => 'Supporting surfaces: the tinted background, the dark navigation column, a second series on a chart.',
                        ],
                        'tertiary' => [
                            'field' => 'brand_tertiary_color',
                            'label' => 'Tertiary',
                            'default' => App\Services\BrandPalette::DEFAULT_TERTIARY,
                            'help' => 'Highlights that are neither an action nor a status.',
                        ],
                    ];

                    $surfaces = [
                        'light' => ['label' => 'Light', 'note' => 'Neutral page, white navigation column.'],
                        'tinted' => ['label' => 'Tinted', 'note' => 'Page washed with the faintest secondary shade.'],
                        'dark' => ['label' => 'Dark', 'note' => 'Navigation column inverted, tinted by the secondary colour.'],
                    ];

                    $currentSurface = old('theme_surface', $settings['theme_surface'] ?? App\Services\BrandPalette::DEFAULT_SURFACE);
                @endphp

                <x-card title="Theme colours"
                    subtitle="Three colours drive the whole interface. Every shade between them is worked out for you.">
                    <div class="space-y-6">
                        @foreach ($roles as $role => $definition)
                            @php $value = old($definition['field'], $settings[$definition['field']] ?? $definition['default']); @endphp

                            <div data-theme-role="{{ $role }}">
                                <label class="form-label" for="{{ $definition['field'] }}">{{ $definition['label'] }}</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="{{ $definition['field'] }}" id="{{ $definition['field'] }}"
                                        value="{{ $value }}"
                                        class="h-10 w-16 shrink-0 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
                                        data-theme-color @disabled(! $canManage)>
                                    <input type="text" value="{{ $value }}"
                                        class="form-input font-mono uppercase sm:max-w-40" maxlength="7"
                                        placeholder="{{ strtoupper($definition['default']) }}"
                                        data-theme-color-text @disabled(! $canManage)>

                                    <div class="hidden flex-1 overflow-hidden rounded-lg ring-1 ring-slate-200 sm:flex">
                                        @foreach ($brand->ramp(null, $role) as $weight => $shade)
                                            <span class="h-10 flex-1" style="background-color: {{ $shade }}"
                                                data-theme-swatch="{{ $weight }}" title="{{ $role }} {{ $weight }}"></span>
                                        @endforeach
                                    </div>
                                </div>
                                <p class="form-help">{{ $definition['help'] }}</p>
                                @error($definition['field'])<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach

                        <div class="border-t border-slate-100 pt-5">
                            <p class="form-label">Background</p>
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach ($surfaces as $key => $surface)
                                    <label @class([
                                        'flex cursor-pointer gap-3 rounded-lg border p-3 transition',
                                        'border-brand-500 bg-brand-50' => $currentSurface === $key,
                                        'border-slate-200 hover:border-slate-300' => $currentSurface !== $key,
                                    ]) data-surface-option="{{ $key }}">
                                        <input type="radio" name="theme_surface" value="{{ $key }}"
                                            class="mt-0.5 size-4 shrink-0 accent-[var(--color-brand-600)]"
                                            @checked($currentSurface === $key) data-surface-choice @disabled(! $canManage)>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium text-slate-900">{{ $surface['label'] }}</span>
                                            <span class="mt-0.5 block text-xs text-slate-500">{{ $surface['note'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('theme_surface')<p class="form-error">{{ $message }}</p>@enderror
                        </div>

                        {{-- The layout as it will look, repainted as the pickers
                             move, so a colour is judged in place rather than as
                             a swatch. --}}
                        <div class="border-t border-slate-100 pt-5">
                            <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Layout preview</p>
                            @include('settings.partials.theme-preview')
                        </div>
                    </div>
                </x-card>

                <x-card title="Logo and icon" class="mt-6"
                    subtitle="Shown in the navigation, on the sign-in screen, on salary slips and in the browser tab">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label class="form-label" for="company_logo_file">Logo</label>
                            <div class="flex items-start gap-4">
                                <span class="flex size-20 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-2">
                                    @if (! empty($settings['company_logo']))
                                        <img src="{{ route('branding.logo') }}?v={{ substr(md5($settings['company_logo']), 0, 8) }}" alt="Current logo"
                                            class="max-h-full max-w-full object-contain">
                                    @else
                                        <span class="text-xs text-slate-400">No logo</span>
                                    @endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    @if ($canManage)
                                        <input type="file" name="company_logo_file" id="company_logo_file"
                                            accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                                    @endif
                                    <p class="form-help">
                                        PNG, JPEG, WebP or SVG, up to 1 MB. A transparent image works best,
                                        square or wide. It appears in the sidebar, on the sign-in page and on
                                        every salary slip, and it replaces the company name wherever it shows.
                                    </p>
                                    @error('company_logo_file')<p class="form-error">{{ $message }}</p>@enderror

                                    @if (! empty($settings['company_logo']) && $canManage)
                                        <label class="mt-3 flex items-center gap-2.5">
                                            <input type="hidden" name="remove_company_logo" value="0">
                                            <input type="checkbox" name="remove_company_logo" value="1" class="form-checkbox">
                                            <span class="text-sm text-slate-700">Remove the current logo</span>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label" for="company_favicon_file">Browser tab icon</label>
                            <div class="flex items-start gap-4">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white p-1.5">
                                    <img src="{{ route('branding.favicon') }}?v={{ substr(md5((string) ($settings['company_favicon'] ?? '').($settings['brand_color'] ?? '')), 0, 8) }}"
                                        alt="Current tab icon" class="max-h-full max-w-full object-contain">
                                </span>
                                <div class="min-w-0 flex-1">
                                    @if ($canManage)
                                        <input type="file" name="company_favicon_file" id="company_favicon_file"
                                            accept="image/png,image/jpeg,image/svg+xml,image/webp,image/x-icon,.ico"
                                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                                    @endif
                                    <p class="form-help">
                                        PNG, ICO, WebP or SVG, up to 512 KB. A square image of at least 64&times;64
                                        works best. Until one is uploaded the tab shows the company initials on
                                        the brand colour.
                                    </p>
                                    @error('company_favicon_file')<p class="form-error">{{ $message }}</p>@enderror

                                    @if (! empty($settings['company_favicon']) && $canManage)
                                        <label class="mt-3 flex items-center gap-2.5">
                                            <input type="hidden" name="remove_company_favicon" value="0">
                                            <input type="checkbox" name="remove_company_favicon" value="1" class="form-checkbox">
                                            <span class="text-sm text-slate-700">Remove the current tab icon</span>
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </x-card>
                </div>

                <div data-tab-panel="company">
                <x-card title="Company" subtitle="Printed on salary slips and used in emails">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Company name" name="company_name" :value="$settings['company_name'] ?? config('app.name')"
                            required :disabled="! $canManage" />
                        <x-input label="Company email" name="company_email" type="email" :value="$settings['company_email'] ?? null"
                            :disabled="! $canManage" />
                        <x-input label="Phone" name="company_phone" :value="$settings['company_phone'] ?? null" :disabled="! $canManage" />
                        <x-input label="Website" name="company_website" type="url" :value="$settings['company_website'] ?? null"
                            :disabled="! $canManage" />
                        <x-input label="Tax identification number" name="company_tax_id" :value="$settings['company_tax_id'] ?? null"
                            :disabled="! $canManage" />
                        <x-textarea label="Registered address" name="company_address" rows="4" class="sm:col-span-2"
                            :value="$settings['company_address'] ?? null" :disabled="! $canManage" />

                    </div>
                </x-card>

                </div>

                <div data-tab-panel="regional">
                <x-card title="Regional and formats">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-select label="Currency" name="currency" required :disabled="! $canManage"
                            :selected="$settings['currency'] ?? 'INR'"
                            :options="['INR' => 'Indian Rupee (INR)', 'USD' => 'US Dollar (USD)', 'EUR' => 'Euro (EUR)', 'GBP' => 'Pound Sterling (GBP)', 'AED' => 'UAE Dirham (AED)']" />
                        <x-select label="Time zone" name="timezone" required :disabled="! $canManage"
                            :selected="$settings['timezone'] ?? config('app.timezone')"
                            :options="collect($timezones)->mapWithKeys(fn ($tz) => [$tz => $tz])->all()" />
                        <x-input label="Date format" name="date_format" :value="$settings['date_format'] ?? 'd M Y'" required
                            :disabled="! $canManage" help="PHP date format, e.g. d M Y." />
                        <x-select label="Financial year starts in" name="financial_year_start_month" required :disabled="! $canManage"
                            :selected="$settings['financial_year_start_month'] ?? 4"
                            :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(null, $m, 1)->format('F')])->all()"
                            help="Used by the payroll cost report and the joiner and exit figures, which then run April to March and are labelled FY 2026–27. Leave allocations stay on the calendar year." />
                        <x-input label="Employee code prefix" name="employee_code_prefix" required :disabled="! $canManage"
                            :value="$settings['employee_code_prefix'] ?? 'EMP'" help="Letters only, e.g. EMP." />
                        <x-input label="Employee code digits" name="employee_code_padding" type="number" min="2" max="8" required
                            :disabled="! $canManage" :value="$settings['employee_code_padding'] ?? 4"
                            help="EMP0001 uses four digits." />
                    </div>
                </x-card>

                </div>

                <div data-tab-panel="email">
                @include('partials.mail-redirect-notice')

                <x-card title="Email" subtitle="Sender identity for every notification the system sends">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="From address" name="mail_from_address" type="email" :disabled="! $canManage"
                            :value="$settings['mail_from_address'] ?? config('mail.from.address')" />
                        <x-input label="From name" name="mail_from_name" :disabled="! $canManage"
                            :value="$settings['mail_from_name'] ?? config('mail.from.name')" />
                        <x-checkbox label="Send email notifications" name="email_notifications_enabled"
                            :checked="$settings['email_notifications_enabled'] ?? true" :disabled="! $canManage"
                            help="Turn off to suppress all outgoing email without changing the mail driver."
                            class="sm:col-span-2" />
                    </div>
                    <p class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
                        Mail is going out through
                        <span class="font-mono">{{ $mail['effective_mailer'] }}</span>@if ($mail['effective_mailer'] === 'smtp' && $mail['effective_host']),
                        at <span class="font-mono">{{ $mail['effective_host'] }}</span>@endif.
                        @if (filled(config('mail.redirect.to')))
                            Outside production, <span class="font-mono">MAIL_REDIRECT_ALL_TO</span> in the
                            environment file sends every message to one inbox instead of the person it names.
                        @endif
                    </p>
                </x-card>

                <x-card title="Mail server" class="mt-6"
                    subtitle="Where messages are actually sent from. Left on the environment file, nothing here overrides it.">
                    @php
                        $transport = old('mail_transport', $mail['transport']);
                    @endphp

                    <div class="space-y-5">
                        <x-select label="Send mail through" name="mail_transport" required
                            :selected="$transport" :disabled="! $canManage"
                            :options="App\Services\MailSettings::TRANSPORTS"
                            data-mail-transport
                            help="Choosing anything but the environment file overrides MAIL_MAILER and the server it names." />

                        {{-- Only the SMTP choice needs a server; the rest send
                             nothing or send elsewhere. --}}
                        <div class="space-y-4" data-mail-smtp @class(['hidden' => $transport !== 'smtp'])>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-input label="Host" name="mail_host" :disabled="! $canManage"
                                    :value="old('mail_host', $mail['host'])"
                                    placeholder="email-smtp.ap-south-1.amazonaws.com" />
                                <x-input label="Port" name="mail_port" type="number" min="1" max="65535"
                                    :disabled="! $canManage" :value="old('mail_port', $mail['port'])" />
                                <x-input label="Username" name="mail_username" :disabled="! $canManage"
                                    :value="old('mail_username', $mail['username'])" autocomplete="off" />

                                <div>
                                    <label class="form-label" for="mail_password">Password</label>
                                    <input type="password" name="mail_password" id="mail_password"
                                        class="form-input" autocomplete="new-password"
                                        placeholder="{{ $mail['has_password'] ? '•••••••• — leave empty to keep it' : 'Not set' }}"
                                        @disabled(! $canManage)>
                                    <p class="form-help">
                                        Stored encrypted and never shown again. Leave it empty to keep the
                                        one already saved.
                                    </p>
                                    @error('mail_password')<p class="form-error">{{ $message }}</p>@enderror

                                    @if ($mail['has_password'] && $canManage)
                                        <label class="mt-2 flex items-center gap-2.5">
                                            <input type="hidden" name="clear_mail_password" value="0">
                                            <input type="checkbox" name="clear_mail_password" value="1" class="form-checkbox">
                                            <span class="text-sm text-slate-700">Remove the saved password</span>
                                        </label>
                                    @endif
                                </div>

                                <x-select label="Encryption" name="mail_scheme" :disabled="! $canManage"
                                    :selected="old('mail_scheme', $mail['scheme'])"
                                    :options="App\Services\MailSettings::SCHEMES" class="sm:col-span-2" />
                            </div>
                        </div>
                    </div>
                </x-card>

                @if ($canManage)
                    {{-- Its own form: a test has to go out against what is
                         saved, not against half-typed changes above it. --}}
                    <x-card title="Send a test message" class="mt-6"
                        subtitle="Uses the settings as they are saved right now, and reports what the mail server said.">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <x-input label="To" name="test_email" type="email" class="sm:flex-1"
                                form="mail-test-form" :value="old('test_email', auth()->user()->email)" />
                            <x-button type="submit" variant="secondary" form="mail-test-form">
                                <x-icon.mail class="size-4" /> Send test
                            </x-button>
                        </div>
                        <p class="form-help mt-2">
                            Save your changes first — the test sends through what is stored, not through
                            what is on screen.
                        </p>
                    </x-card>
                @endif

                </div>

                <div data-tab-panel="attendance">
                <x-card title="Attendance">
                    <div class="space-y-4">
                        <x-checkbox label="Let employees check in and out themselves" name="attendance_allow_self_punch"
                            :checked="$settings['attendance_allow_self_punch'] ?? true" :disabled="! $canManage"
                            help="Off hides the punch buttons and closes the same door on the mobile API. Attendance is then entered by HR, and employees can still raise corrections." />
                        <x-checkbox label="Treat unmarked past working days as absent" name="attendance_auto_absent"
                            :checked="$settings['attendance_auto_absent'] ?? true" :disabled="! $canManage"
                            help="On, a past working day nobody recorded is an absence and becomes loss of pay. Off, it reads as “Not marked” and is paid — a forgotten punch then costs nobody their salary." />
                        <x-checkbox label="Require location to check in and out" name="attendance_require_location"
                            :checked="$settings['attendance_require_location'] ?? true" :disabled="! $canManage"
                            help="Employees must allow location access before they can punch, and the position is stored against the record. The browser only offers location over HTTPS." />
                        <x-checkbox label="Close punches that were never punched out" name="attendance_auto_close_punches"
                            :checked="$settings['attendance_auto_close_punches'] ?? false" :disabled="! $canManage"
                            help="Somebody who punches in and forgets keeps their pay — the day counts present on the check-in — but the hours stay at nought while the punch is open, and it stays open for ever. On, a nightly job closes yesterday’s at the end of their shift, marks the day as needing a correction, and writes to the employee. Off, the day keeps reading “still working” until somebody notices." />
                    </div>
                </x-card>

                <x-card title="Away from the branch"
                    subtitle="Flag punches made too far from the branch, and say who hears about them">
                    <div class="space-y-4">
                        <x-checkbox label="Flag punches made outside the allowed radius" name="attendance_geofence_enabled"
                            :checked="$settings['attendance_geofence_enabled'] ?? true" :disabled="! $canManage"
                            help="Each branch carries its own coordinates, set on the branch screen. A branch left without them is never flagged." />

                        <x-input label="Allowed radius (metres)" name="attendance_geofence_radius" type="number"
                            min="20" max="50000" required :disabled="! $canManage"
                            :value="$settings['attendance_geofence_radius'] ?? 200"
                            help="The default for every branch. A branch can be given its own radius instead." />

                        <x-textarea label="Alert these people" name="attendance_geofence_alert_recipients" rows="3"
                            :disabled="! $canManage"
                            :value="$settings['attendance_geofence_alert_recipients'] ?? ''"
                            placeholder="hr@example.com, operations@example.com"
                            help="Email addresses, separated by commas or one per line. They receive both the immediate alert and the evening report. Leave this empty and it falls back to every active HR manager and administrator, so an alert is never sent nowhere." />

                        <x-checkbox label="Also alert the employee's own manager" name="attendance_geofence_alert_managers"
                            :checked="$settings['attendance_geofence_alert_managers'] ?? false" :disabled="! $canManage"
                            help="Their reporting manager and their branch manager, who are usually the two people who know whether there was a reason." />

                        <div class="border-t border-slate-100 pt-4">
                            <x-checkbox label="Email a report at the end of each day" name="attendance_geofence_daily_report"
                                :checked="$settings['attendance_geofence_daily_report'] ?? true" :disabled="! $canManage"
                                help="Every flagged punch of the day in one message. Sent even on a quiet day, so silence never has to be interpreted." />

                            <div class="mt-4 sm:max-w-48">
                                <x-input label="Send the report at" name="attendance_geofence_report_time" type="time"
                                    required :disabled="! $canManage"
                                    :value="$settings['attendance_geofence_report_time'] ?? '19:30'"
                                    help="Needs the scheduler running on the server." />
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
                        The wording of both messages is yours to change under
                        <a href="{{ route('notification-templates.index') }}" class="link font-medium">Notifications</a>,
                        where they can also be switched off entirely.
                    </p>
                </x-card>
                </div>

                <div data-tab-panel="security">
                <x-card title="Two-step verification"
                    subtitle="Available to everybody. Name the roles that must use it.">
                    @php
                        $requiredRoles = App\Support\TwoFactorPolicy::requiredRoles();
                    @endphp

                    <div class="space-y-4">
                        <p class="text-sm text-slate-600">
                            Anybody can turn on a second factor from their own profile. Ticking a
                            role here makes it compulsory: they are sent to set one up before they
                            can use anything else, and cannot turn it off again.
                        </p>

                        {{-- Written out rather than <x-checkbox>: that one pairs
                             every box with a hidden "0", which under an array
                             name would post a list of noughts alongside the
                             roles. One empty sentinel instead, so unticking
                             everything still posts the key and can clear it. --}}
                        <input type="hidden" name="security_two_factor_required_roles[]" value="">

                        <div class="space-y-2.5">
                            @foreach (App\Support\Roles::labels() as $role => $label)
                                <label class="flex items-start gap-2.5">
                                    <input type="checkbox" class="form-checkbox mt-0.5"
                                        name="security_two_factor_required_roles[]"
                                        value="{{ $role }}"
                                        @checked(in_array($role, $requiredRoles, true))
                                        @disabled(! $canManage)>
                                    <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-600">
                            <p class="font-medium text-slate-900">Before you require it</p>
                            <ul class="mt-1.5 space-y-1">
                                <li>· Nobody is locked out — somebody who has not enrolled is sent to the setup screen, not refused.</li>
                                <li>· They will need an authenticator app on a phone. People who share a terminal and have none are the reason this is per role rather than for everybody.</li>
                                <li>· Somebody who loses their phone and their recovery codes needs an administrator to reset them, from Administration → User Accounts.</li>
                            </ul>
                        </div>
                    </div>
                </x-card>
                </div>

                <div data-tab-panel="payroll">
                <x-card title="Overtime"
                    subtitle="Extra hours are always recorded. This decides whether they are also paid.">
                    <div class="space-y-4">
                        <x-checkbox label="Pay for overtime" name="payroll_overtime_enabled"
                            :checked="$settings['payroll_overtime_enabled'] ?? false" :disabled="! $canManage"
                            help="Off, the hours worked beyond a full day are reported on attendance and on the payslip but nobody is paid for them. On, they become an Overtime line on the payslip, and any statutory deduction charged on gross wages is charged on them too." />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-select label="Rate is a share of" name="payroll_overtime_basis"
                                :options="App\Services\OvertimeService::BASES"
                                :selected="$settings['payroll_overtime_basis'] ?? 'basic'"
                                :disabled="! $canManage"
                                help="Which monthly figure the hourly rate is worked out from. Overtime itself is never part of it — nobody is paid overtime on overtime." />

                            <x-input label="Times the ordinary rate" name="payroll_overtime_multiplier"
                                type="number" step="0.25" min="1" max="5" :disabled="! $canManage"
                                :value="$settings['payroll_overtime_multiplier'] ?? 2"
                                help="Section 59 of the Factories Act 1948 asks for twice the ordinary rate." />

                            <x-input label="Hours in a working day" name="payroll_overtime_hours_per_day"
                                type="number" step="0.5" min="1" max="24" :disabled="! $canManage"
                                :value="$settings['payroll_overtime_hours_per_day'] ?? 8"
                                help="Used only to turn a month's salary into an hourly rate: the month's working days multiplied by these hours." />

                            <x-input label="Most hours paid in a month" name="payroll_overtime_monthly_cap_hours"
                                type="number" step="1" min="0" max="400" :disabled="! $canManage"
                                :value="$settings['payroll_overtime_monthly_cap_hours'] ?? 0"
                                help="Hours beyond this are still recorded and reported, simply not paid. Zero means no limit." />
                        </div>

                        <p class="border-t border-slate-100 pt-4 text-xs text-slate-500">
                            An individual employee can be left out on their own record — overtime
                            is not usually paid to people on a manager's grade.
                        </p>
                    </div>
                </x-card>

                <x-card title="Income tax"
                    subtitle="Whether payroll deducts tax at source, and what it works that out from.">
                    <div class="space-y-4">
                        <x-checkbox label="Deduct income tax from salaries" name="payroll_tds_enabled"
                            :checked="$settings['payroll_tds_enabled'] ?? false" :disabled="! $canManage"
                            help="Off — as it starts — salaries are paid without tax taken off and nothing about payroll changes. On, each slip carries an Income tax (TDS) line worked out from the whole year: the year's income projected, the year's tax on it, less what has already been deducted, spread over the months that remain." />

                        <div class="rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-600">
                            <p class="font-medium text-slate-900">Before you switch this on</p>
                            <ul class="mt-1.5 space-y-1">
                                <li>· Employees choose a regime and declare their investments under <span class="font-medium">My Income Tax</span>; HR checks the proofs under <a href="{{ route('tax.index') }}" class="link font-medium">Payroll → Income Tax</a>.</li>
                                <li>· Somebody who has declared nothing is taxed on their whole salary. That is correct, and it is also what will be asked about.</li>
                                <li>· The rates are the published ones for the year the software knows about, and the computation sheet says which. Check them against the current Finance Act.</li>
                            </ul>
                        </div>
                    </div>
                </x-card>

                <x-card title="Full and final settlement"
                    subtitle="How a leaver's last figures are worked out">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Days a month is divided into" name="settlement_per_day_divisor"
                            type="number" step="1" min="1" max="31" :disabled="! $canManage"
                            :value="$settings['settlement_per_day_divisor'] ?? 26"
                            help="Twenty-six is the usual reading of the Payment of Gratuity Act, and the usual basis for leave encashment and notice recovery too." />

                        <x-input label="Most gratuity payable" name="settlement_gratuity_cap"
                            type="number" step="1" min="0" :disabled="! $canManage"
                            :value="$settings['settlement_gratuity_cap'] ?? 2000000"
                            help="The statutory ceiling, raised by notification from time to time." />
                    </div>
                </x-card>
                </div>

                </x-tabs>
            </div>

            <div class="space-y-6">
                <x-card title="System">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Application</dt>
                            <dd class="text-right text-slate-900">{{ config('app.name') }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Environment</dt>
                            <dd class="text-right text-slate-900">{{ app()->environment() }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Laravel</dt>
                            <dd class="text-right text-slate-900">{{ app()->version() }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">PHP</dt>
                            <dd class="text-right text-slate-900">{{ PHP_VERSION }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Database</dt>
                            <dd class="text-right text-slate-900">{{ config('database.default') }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Queue</dt>
                            <dd class="text-right text-slate-900">{{ config('queue.default') }}</dd>
                        </div>
                    </dl>
                </x-card>

                @if ($canManage)
                    <x-button size="lg" class="w-full">Save settings</x-button>
                @endif
            </div>
        </div>
    </form>

    @if ($canManage)
        {{-- Outside the settings form, because a form cannot be nested inside
             another. The fields above point at it with their form attribute. --}}
        <form id="mail-test-form" method="POST" action="{{ route('settings.test-email') }}" class="hidden">
            @csrf
        </form>
    @endif
</x-app-layout>
