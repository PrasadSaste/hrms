<x-app-layout title="Dashboard">
    <x-page-header
        :title="'Good ' . (now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening')) . ', ' . (auth()->user()->employee?->first_name ?? auth()->user()->name)"
        :subtitle="$today->format('l, d F Y')" />

    {{-- ------------------------------------------------ organisation stats --}}
    @isset($org)
            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-stat label="Headcount" :value="$org['headcount']" color="brand"
                    :sublabel="$org['new_joiners'] . ' joined in 30 days'"
                    :href="Route::has('employees.index') && auth()->user()->can('employees.view') ? route('employees.index') : null">
                    <x-slot:icon><x-icon.users class="size-5" /></x-slot:icon>
                </x-stat>

                <x-stat label="Present today" :value="$org['present']" color="emerald"
                    :sublabel="$org['attendance_rate'] . '% of headcount'"
                    :href="auth()->user()->canAny(['attendance.view-team','attendance.view-all']) ? route('attendance.daily') : null">
                    <x-slot:icon><x-icon.check class="size-5" /></x-slot:icon>
                </x-stat>

                <x-stat label="On leave" :value="$org['on_leave']" color="sky"
                    :sublabel="$org['late'] . ' arrived late'"
                    :href="auth()->user()->canAny(['leave.view-team','leave.view-all']) ? route('leave.calendar') : null">
                    <x-slot:icon><x-icon.calendar class="size-5" /></x-slot:icon>
                </x-stat>

                <x-stat label="Pending approvals" :value="$org['pending_leave_count']" color="amber"
                    sublabel="Leave requests awaiting action"
                    :href="auth()->user()->can('leave.approve') ? route('leave.index', ['status' => 'pending']) : null">
                    <x-slot:icon><x-icon.pencil class="size-5" /></x-slot:icon>
                </x-stat>
            </div>
        @endisset

    {{-- ------------------------------------------ verification to finish --}}
    @isset($background_check)
        @php
            $bgvOutstanding = $background_check->itemsNeedingWork()->count();
            $bgvTotal = $background_check->requiredItems()->count();
        @endphp
        <div class="mb-6 overflow-hidden rounded-xl border border-brand-200 bg-gradient-to-br from-brand-50 via-white to-white shadow-xs">
            <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-sm">
                    <x-icon.shield class="size-7" />
                </div>

                <div class="min-w-0 flex-1">
                    @if ($background_check->isAwaitingReview())
                        <h2 class="text-lg font-semibold text-slate-900">Your documents are with HR</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Submitted on {{ $background_check->submitted_at?->format('d M Y') }}. We will email you once
                            they have been checked, usually within a couple of working days.
                        </p>
                    @elseif ($background_check->status === App\Models\BackgroundCheck::CHANGES_REQUESTED)
                        <h2 class="text-lg font-semibold text-slate-900">HR needs one or two documents again</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $bgvOutstanding }} {{ Str::plural('document', $bgvOutstanding) }} to replace, then submit again.
                        </p>
                    @else
                        <h2 class="text-lg font-semibold text-slate-900">Finish your background verification</h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $bgvTotal - $bgvOutstanding }} of {{ $bgvTotal }} documents provided.
                            @if ($background_check->due_on)
                                Please finish by <span class="font-medium text-slate-800">{{ $background_check->due_on->format('d M Y') }}</span>.
                            @endif
                        </p>
                    @endif

                    <div class="mt-3 h-2 max-w-md overflow-hidden rounded-full bg-brand-100">
                        <div class="h-full rounded-full bg-brand-600 transition-all"
                            style="width: {{ $background_check->isAwaitingReview() ? 100 : max($background_check->progress(), 3) }}%"></div>
                    </div>

                    <p class="mt-3 flex items-center gap-1.5 text-xs text-slate-500">
                        <x-icon.lock class="size-3.5" />
                        My Attendance, Leave and Salary Slips open once it is complete.
                    </p>
                </div>

                <div class="shrink-0">
                    <x-button :href="route('my-verification.edit')" size="lg">
                        {{ $background_check->isAwaitingReview() ? 'See what you sent' : 'Continue' }}
                    </x-button>
                </div>
            </div>
        </div>
    @endisset

    {{-- ------------------------------------------------------- punch card --}}
    @isset($self)
        <x-card title="Today at work" :subtitle="$today->format('d M Y')">
                            <div class="flex flex-wrap items-center gap-6">
                                <div class="flex-1">
                                    <p class="text-4xl font-semibold tabular-nums text-slate-900" data-clock>--:--:--</p>
                                    <p class="mt-1 text-sm text-slate-500">
                                        @if ($self['today_attendance']?->check_in)
                                            Checked in at {{ $self['today_attendance']->check_in->format('h:i A') }}
                                            @if ($self['today_attendance']->check_out)
                                                &middot; out at {{ $self['today_attendance']->check_out->format('h:i A') }}
                                                &middot; {{ $self['today_attendance']->durationLabel() }} worked
                                            @endif
                                            @if ($self['today_attendance']->late_minutes > 0)
                                                &middot; <span class="text-amber-600">{{ $self['today_attendance']->late_minutes }} min late</span>
                                            @endif
                                        @else
                                            You have not punched in yet today.
                                        @endif
                                        @if ($self['today_attendance']?->isOnBreak())
                                            &middot; <span class="font-medium text-amber-600">on a
                                            {{ strtolower($self['today_attendance']->openBreak()->label()) }} break</span>
                                        @endif
                                    </p>
                                </div>

                                @can('attendance.punch')
                                    {{--
                                        The whole day's controls live here as well as in the header,
                                        because the header group is hidden on a phone.
                                    --}}
                                    <div class="flex flex-wrap gap-2">
                                        @if ($self['today_attendance']?->isOnBreak())
                                            <form method="POST" action="{{ route('attendance.break.end') }}">
                                                @csrf
                                                <x-button size="lg">
                                                    <x-icon.clock class="size-4" />
                                                    End {{ strtolower($self['today_attendance']->openBreak()->label()) }}
                                                </x-button>
                                            </form>
                                        @elseif ($self['today_attendance']?->isPunchedIn())
                                            <x-button type="button" variant="secondary" size="lg"
                                                data-dialog-open="break">
                                                <x-icon.clock class="size-4" /> Start break
                                            </x-button>

                                            <x-punch-form :action="route('attendance.check-out')" label="Punch out"
                                                variant="danger" size="lg">
                                                <x-slot:icon><x-icon.logout class="size-4" /></x-slot:icon>
                                            </x-punch-form>
                                        @else
                                            <x-punch-form :action="route('attendance.check-in')"
                                                :label="$self['today_attendance']?->sessions->isNotEmpty() ? 'Punch in again' : 'Punch in'"
                                                variant="success" size="lg">
                                                <x-slot:icon><x-icon.check class="size-4" /></x-slot:icon>
                                            </x-punch-form>
                                        @endif
                                    </div>
                                @endcan
                            </div>

                            <div data-punch-anchor></div>

                            <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-4">
                                <div>
                                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Present this month</dt>
                                    <dd class="mt-1 text-xl font-semibold text-slate-900">
                                        {{ rtrim(rtrim(number_format($self['month_totals']['present_days'], 1), '0'), '.') }}
                                        <span class="text-sm font-normal text-slate-400">/ {{ $self['month_totals']['working_days'] }}</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Attendance</dt>
                                    <dd class="mt-1 text-xl font-semibold text-slate-900">{{ $self['month_totals']['attendance_percentage'] }}%</dd>
                                </div>
                                <div>
                                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Hours worked</dt>
                                    <dd class="mt-1 text-xl font-semibold text-slate-900">{{ $self['month_totals']['worked_hours'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Late arrivals</dt>
                                    <dd class="mt-1 text-xl font-semibold text-slate-900">{{ $self['month_totals']['late_days'] }}</dd>
                                </div>
                            </dl>
                        </x-card>
    @endisset

    {{-- --------------------------------------- an employee's own shape --}}
    @isset($mine)
        <div class="mb-6 grid items-start gap-6 lg:grid-cols-3">
            <x-card class="lg:col-span-2" title="Your last two weeks"
                subtitle="Hours recorded each day — a day with nothing recorded shows as a gap">
                <x-chart.columns :points="$mine['attendance']" label="Hour"
                    caption="Hours you worked on each of the last fourteen days." />
            </x-card>

            <div class="space-y-6">
                <x-card title="Your leave requests" :subtitle="'Everything you have asked for in ' . $today->format('Y')">
                    <x-chart.status-bar :segments="$mine['mix']" />
                </x-card>

                @if ($self['leave_balance']->isNotEmpty())
                    <x-card title="Days you have left" subtitle="Remaining this year">
                        <x-chart.bars :rows="$self['leave_balance']->map(fn ($row) => [
                                'label' => $row['leave_type']->name,
                                'value' => $row['remaining'],
                            ])->filter(fn ($r) => $r['value'] > 0)->values()"
                            label="Days" series="s3" />
                    </x-card>
                @endif
            </div>
        </div>
    @endisset

    {{-- ------------------------------------- wide, chart-shaped panels --}}
    @isset($org)
        {{-- items-start so a short chart is not stretched to match its neighbour --}}
        <div class="mb-6 grid items-start gap-6 lg:grid-cols-2">
            <x-card title="Attendance over the last 14 days" subtitle="Employees who checked in each day">
                <x-chart.columns :points="collect($org['attendance_trend'])->map(fn ($p) => [
                        'label' => $p['label'],
                        'value' => $p['count'],
                        'full' => \Illuminate\Support\Carbon::parse($p['date'])->format('l, d F'),
                    ])"
                    label="Present"
                    caption="How many people checked in on each of the last fourteen days." />
            </x-card>

            @if ($org['headcount_by_department']->isNotEmpty())
                <x-card title="Headcount by department" subtitle="Everybody currently on the roll">
                    <x-chart.bars :rows="$org['headcount_by_department']->map(fn ($r) => [
                            'label' => $r['department'],
                            'value' => $r['total'],
                        ])" label="People" />
                </x-card>
            @endif
        </div>
    @endisset

    {{-- ---------------------------------------------- the request queue --}}
    @isset($requests)
        <div class="mb-6 grid items-start gap-6 lg:grid-cols-3">
            <x-card class="lg:col-span-2" title="Leave requests, week by week"
                subtitle="Raised against decided — the gap between the two is the queue growing or shrinking">
                <x-chart.trend :points="$requests['trend']"
                    :series="['raised' => 'Raised', 'decided' => 'Decided']" />
            </x-card>

            <div class="space-y-6">
                <x-card title="Where they stand" :subtitle="'Everything raised in ' . $today->format('Y')">
                    <x-chart.status-bar :segments="$requests['mix']" />
                </x-card>

                <x-card title="How long a decision takes">
                    @if ($requests['speed']['days'] !== null)
                        {{-- One number, so it is written as one rather than plotted. --}}
                        <p class="text-3xl font-semibold tracking-tight text-slate-900">
                            {{ $requests['speed']['days'] }}
                            <span class="text-base font-normal text-slate-500">
                                {{ \Illuminate\Support\Str::plural('day', $requests['speed']['days']) }}
                            </span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Average across {{ $requests['speed']['decided'] }}
                            {{ \Illuminate\Support\Str::plural('decision', $requests['speed']['decided']) }}
                            in the last 90 days.
                        </p>
                    @else
                        <p class="text-sm text-slate-500">Nothing has been decided in the last 90 days.</p>
                    @endif

                    @if (($requests['speed']['oldest_waiting'] ?? null) !== null)
                        <p @class([
                            'mt-3 flex items-center gap-1.5 border-t border-slate-100 pt-3 text-xs',
                            'text-slate-500' => $requests['speed']['oldest_waiting'] < 7,
                            'font-medium text-amber-700' => $requests['speed']['oldest_waiting'] >= 7,
                        ])>
                            <x-icon.clock class="size-3.5" />
                            The longest wait so far is {{ $requests['speed']['oldest_waiting'] }}
                            {{ \Illuminate\Support\Str::plural('day', $requests['speed']['oldest_waiting']) }}.
                        </p>
                    @endif
                </x-card>
            </div>
        </div>

        @if ($requests['by_type']->isNotEmpty())
            <div class="mb-6">
                <x-card title="Leave taken this year, by kind" subtitle="Days approved across the organisation">
                    <x-chart.bars :rows="$requests['by_type']" label="Days" series="s1" />
                </x-card>
            </div>
        @endif
    @endisset

    {{--
        Everything else is an independent card, so let them flow into balanced
        columns rather than stacking into one tall rail down the right.
    --}}
    <div class="columns-1 gap-6 lg:columns-2 xl:columns-3">

        @isset($pending_leaves)
            <x-card class="mb-6 break-inside-avoid" title="Leave awaiting your approval" :subtitle="$pending_leaves->count() . ' request(s)'">
                                <x-slot:actions>
                                    <x-button href="{{ route('leave.index', ['status' => 'pending']) }}" variant="secondary" size="sm">View all</x-button>
                                </x-slot:actions>

                                @forelse ($pending_leaves as $leaveRequest)
                                    <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0">
                                        <x-avatar :name="$leaveRequest->employee->full_name" size="sm" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ $leaveRequest->employee->full_name }}</p>
                                            <p class="truncate text-xs text-slate-500">
                                                {{ $leaveRequest->leaveType->name }} &middot; {{ $leaveRequest->periodLabel() }}
                                                &middot; {{ rtrim(rtrim(number_format($leaveRequest->total_days, 1), '0'), '.') }} day(s)
                                            </p>
                                        </div>
                                        <x-button href="{{ route('leave.show', $leaveRequest) }}" size="sm" variant="secondary">Review</x-button>
                                    </div>
                                @empty
                                    <x-empty title="No pending approvals" message="Every leave request in your team has been actioned." />
                                @endforelse
                            </x-card>
        @endisset

        @isset($self)
            @unless (isset($org))
                <x-card class="mb-6 break-inside-avoid" :title="'Your ' . $today->format('F') . ' at a glance'"
                                        subtitle="Every working day this month, and how it was recorded">
                                        <x-slot:actions>
                                            <x-button href="{{ route('attendance.index') }}" variant="secondary" size="sm">
                                                Open attendance
                                            </x-button>
                                        </x-slot:actions>

                                        @php
                                            $monthDays = $self['month_days'] ?? [];
                                            $tone = [
                                                'present' => 'bg-emerald-500 text-white',
                                                'late' => 'bg-amber-500 text-white',
                                                'half_day' => 'bg-orange-400 text-white',
                                                'absent' => 'bg-rose-500 text-white',
                                                'on_leave' => 'bg-sky-500 text-white',
                                                'holiday' => 'bg-violet-400 text-white',
                                                'weekend' => 'bg-slate-200 text-slate-500',
                                                // A working day still ahead of us, not yet recorded.
                                                'upcoming' => 'border border-dashed border-slate-300 text-slate-400',
                                            ];
                                        @endphp

                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($monthDays as $date => $day)
                                                @php
                                                    $carbon = \Illuminate\Support\Carbon::parse($date);
                                                    $key = $day['status']?->value
                                                        ?? ($day['kind'] === 'working' ? 'upcoming' : 'weekend');
                                                @endphp
                                                <span @class([
                                                    'flex size-8 items-center justify-center rounded text-xs font-medium',
                                                    $tone[$key] ?? $tone['weekend'],
                                                    'ring-2 ring-brand-600 ring-offset-1' => $carbon->isToday(),
                                                ])
                                                    title="{{ $carbon->format('D, d M') }} &mdash; {{ $day['status']?->label() ?? 'Not yet recorded' }}">
                                                    {{ $carbon->format('j') }}
                                                </span>
                                            @endforeach
                                        </div>

                                        <div class="mt-4 flex flex-wrap gap-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
                                            @foreach (['present' => 'Present', 'late' => 'Late', 'half_day' => 'Half day', 'absent' => 'Absent', 'on_leave' => 'On leave', 'holiday' => 'Holiday', 'weekend' => 'Week off', 'upcoming' => 'Still to come'] as $key => $label)
                                                <span class="flex items-center gap-1.5">
                                                    <span class="size-3 rounded {{ $tone[$key] }}"></span>{{ $label }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </x-card>

                @if ($self['pending_requests']->isNotEmpty())
                    <x-card class="mb-6 break-inside-avoid" title="Your pending leave requests">
                                                @foreach ($self['pending_requests'] as $leaveRequest)
                                                    <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0">
                                                        <span class="size-2 shrink-0 rounded-full" style="background-color: {{ $leaveRequest->leaveType->color }}"></span>
                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-sm font-medium text-slate-900">{{ $leaveRequest->leaveType->name }}</p>
                                                            <p class="text-xs text-slate-500">{{ $leaveRequest->periodLabel() }}</p>
                                                        </div>
                                                        <span class="text-sm text-slate-600">
                                                            {{ rtrim(rtrim(number_format($leaveRequest->total_days, 1), '0'), '.') }}d
                                                        </span>
                                                        <x-badge :color="$leaveRequest->status->color()">{{ $leaveRequest->status->label() }}</x-badge>
                                                        <x-button href="{{ route('leave.show', $leaveRequest) }}" variant="ghost" size="sm">View</x-button>
                                                    </div>
                                                @endforeach
                                            </x-card>
                @endif
            @endunless

            <x-card class="mb-6 break-inside-avoid" title="Leave balance">
                                <x-slot:actions>
                                    <x-button href="{{ route('leave.create') }}" size="sm"><x-icon.plus class="size-4" /> Apply</x-button>
                                </x-slot:actions>

                                @forelse ($self['leave_balance']->take(5) as $row)
                                    <div class="border-b border-slate-100 py-2.5 first:pt-0 last:border-0 last:pb-0">
                                        <div class="flex items-baseline justify-between gap-2">
                                            <span class="flex items-center gap-2 text-sm text-slate-700">
                                                <span class="size-2 rounded-full" style="background-color: {{ $row['leave_type']->color }}"></span>
                                                {{ $row['leave_type']->name }}
                                            </span>
                                            <span class="text-sm font-semibold text-slate-900">
                                                {{ rtrim(rtrim(number_format($row['remaining'], 1), '0'), '.') }}
                                                <span class="text-xs font-normal text-slate-400">/ {{ rtrim(rtrim(number_format($row['entitled'], 1), '0'), '.') }}</span>
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="py-4 text-center text-sm text-slate-500">No leave types are allocated yet.</p>
                                @endforelse

                                <a href="{{ route('leave.balance') }}" class="mt-3 block text-center text-xs font-medium link-plain">
                                    Full balance breakdown
                                </a>
                            </x-card>

            @if ($self['latest_payslip'])
                <x-card class="mb-6 break-inside-avoid" title="Latest salary slip">
                                        <p class="text-sm text-slate-500">{{ $self['latest_payslip']->periodLabel() }}</p>
                                        <p class="mt-1 text-3xl font-semibold text-slate-900">
                                            <x-money :amount="$self['latest_payslip']->net_pay" :currency="$self['latest_payslip']->currency" :decimals="0" />
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Net pay &middot; {{ ucfirst($self['latest_payslip']->payment_status) }}
                                        </p>
                                        <div class="mt-4 flex gap-2">
                                            <x-button href="{{ route('payslips.show', $self['latest_payslip']) }}" size="sm" variant="secondary" class="flex-1">View</x-button>
                                            @can('payslips.download')
                                                <x-button href="{{ route('payslips.download', $self['latest_payslip']) }}" size="sm" class="flex-1">
                                                    <x-icon.download class="size-4" /> PDF
                                                </x-button>
                                            @endcan
                                        </div>
                                    </x-card>
            @endif

            @if ($self['upcoming_leave']->isNotEmpty())
                <x-card class="mb-6 break-inside-avoid" title="Your upcoming leave">
                                        @foreach ($self['upcoming_leave'] as $leaveRequest)
                                            <div class="border-b border-slate-100 py-2.5 first:pt-0 last:border-0 last:pb-0">
                                                <p class="text-sm font-medium text-slate-900">{{ $leaveRequest->leaveType->name }}</p>
                                                <p class="text-xs text-slate-500">{{ $leaveRequest->periodLabel() }}</p>
                                            </div>
                                        @endforeach
                                    </x-card>
            @endif
        @endisset

        @isset($payroll)
            @can('payroll.view')
                <x-card class="mb-6 break-inside-avoid" title="Payroll">
                                        @if ($payroll['last_run'])
                                            <p class="text-xs tracking-wide text-slate-500 uppercase">Most recent run</p>
                                            <p class="mt-1 text-sm font-medium text-slate-900">{{ $payroll['last_run']->periodLabel() }}</p>
                                            <p class="mt-0.5 text-2xl font-semibold text-slate-900">
                                                <x-money :amount="$payroll['last_run']->total_net" :decimals="0" />
                                            </p>
                                            <div class="mt-2">
                                                <x-badge :color="$payroll['last_run']->status->color()" dot>{{ $payroll['last_run']->status->label() }}</x-badge>
                                            </div>
                                            <dl class="mt-4 space-y-1.5 border-t border-slate-100 pt-3 text-sm">
                                                <div class="flex justify-between">
                                                    <dt class="text-slate-500">Employees paid</dt>
                                                    <dd class="font-medium text-slate-900">{{ $payroll['last_run']->total_employees }}</dd>
                                                </div>
                                                <div class="flex justify-between">
                                                    <dt class="text-slate-500">Net paid this year</dt>
                                                    <dd class="font-medium text-slate-900"><x-money :amount="$payroll['ytd_net']" :decimals="0" /></dd>
                                                </div>
                                                <div class="flex justify-between">
                                                    <dt class="text-slate-500">Runs in progress</dt>
                                                    <dd class="font-medium text-slate-900">{{ $payroll['pending_runs'] }}</dd>
                                                </div>
                                            </dl>
                                            <x-button href="{{ route('payroll.show', $payroll['last_run']) }}" variant="secondary" size="sm" class="mt-4 w-full">
                                                Open run
                                            </x-button>
                                        @else
                                            <x-empty title="No payroll runs yet" message="Create your first run to generate salary slips.">
                                                <x-slot:action>
                                                    @can('payroll.create')
                                                        <x-button href="{{ route('payroll.create') }}" size="sm">Create run</x-button>
                                                    @endcan
                                                </x-slot:action>
                                            </x-empty>
                                        @endif
                                    </x-card>
            @endcan
        @endisset

        @if ($announcements->isNotEmpty())
            <x-card class="mb-6 break-inside-avoid" title="Announcements">
                                @foreach ($announcements as $announcement)
                                    <a href="{{ route('announcements.show', $announcement) }}"
                                        class="block border-b border-slate-100 py-2.5 transition first:pt-0 last:border-0 last:pb-0 hover-ink">
                                        <p class="flex items-center gap-1.5 text-sm font-medium text-slate-900">
                                            @if ($announcement->is_pinned)
                                                <span class="text-amber-500">&#9733;</span>
                                            @endif
                                            {{ $announcement->title }}
                                        </p>
                                        <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ $announcement->excerpt(18) }}</p>
                                    </a>
                                @endforeach
                            </x-card>
        @endif

        @if ($holidays->isNotEmpty())
            <x-card class="mb-6 break-inside-avoid" title="Upcoming holidays">
                                @foreach ($holidays as $holiday)
                                    <div class="flex items-center gap-3 border-b border-slate-100 py-2.5 first:pt-0 last:border-0 last:pb-0">
                                        <div class="flex size-10 shrink-0 flex-col items-center justify-center rounded-lg bg-violet-50 text-violet-700">
                                            <span class="text-xs leading-none font-semibold">{{ $holiday->date->format('d') }}</span>
                                            <span class="text-[10px] leading-none">{{ $holiday->date->format('M') }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ $holiday->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $holiday->date->format('l') }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </x-card>
        @endif

        @if ($birthdays->isNotEmpty() || $anniversaries->isNotEmpty())
            <x-card class="mb-6 break-inside-avoid" title="Celebrations">
                                @foreach ($birthdays as $birthday)
                                    <div class="flex items-center gap-3 border-b border-slate-100 py-2.5 first:pt-0 last:border-0">
                                        <x-avatar :name="$birthday['employee']->full_name" :src="$birthday['employee']->photoUrl()" size="sm" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ $birthday['employee']->full_name }}</p>
                                            <p class="text-xs text-slate-500">
                                                Birthday &middot;
                                                {{ $birthday['in_days'] === 0 ? 'today' : $birthday['date']->format('d M') }}
                                            </p>
                                        </div>
                                        <span class="text-lg">&#127874;</span>
                                    </div>
                                @endforeach

                                @foreach ($anniversaries as $anniversary)
                                    <div class="flex items-center gap-3 border-b border-slate-100 py-2.5 last:border-0 last:pb-0">
                                        <x-avatar :name="$anniversary['employee']->full_name" :src="$anniversary['employee']->photoUrl()" size="sm" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ $anniversary['employee']->full_name }}</p>
                                            <p class="text-xs text-slate-500">
                                                {{ $anniversary['years'] }} year(s) &middot;
                                                {{ $anniversary['in_days'] === 0 ? 'today' : $anniversary['date']->format('d M') }}
                                            </p>
                                        </div>
                                        <span class="text-lg">&#127881;</span>
                                    </div>
                                @endforeach
                            </x-card>
        @endif
    </div>
</x-app-layout>
