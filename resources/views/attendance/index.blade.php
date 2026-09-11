<x-app-layout title="My attendance">
    <x-page-header title="My attendance" :subtitle="$month->format('F Y')">
        <x-slot:actions>
            <x-button href="{{ route('attendance.regularizations.index') }}" variant="secondary">
                <x-icon.pencil class="size-4" /> Regularisations
            </x-button>
            <x-button href="{{ route('attendance.export', array_merge(['employee' => $employee->id], request()->query())) }}"
                variant="secondary">
                <x-icon.download class="size-4" /> Export
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $assumed = collect($summary['days'] ?? [])
            ->pluck('attendance')
            ->filter(fn ($a) => $a?->needs_correction)
            ->values();
    @endphp

    @if ($assumed->isNotEmpty())
        {{-- Page content, not a notification: it stays until it is dealt with. --}}
        <x-alert type="warning" class="mb-5" :dismissible="false">
            <span class="font-medium">
                {{ trans_choice('One day|:count days', $assumed->count()) }} this month
                {{ $assumed->count() === 1 ? 'has' : 'have' }} an assumed punch-out.
            </span>
            You punched in on
            {{ $assumed->map(fn ($a) => $a->date->format('d M'))->join(', ', ' and ') }}
            and never punched out, so the day was closed at the end of your shift. Your pay is not
            affected — but the hours are our guess, and only you know when you actually left.
            {{-- A link rather than a button: it carries the day, so the form
                 opens on the date in question instead of on today. --}}
            <a href="{{ route('attendance.index', ['year' => $month->year, 'month' => $month->month, 'correct' => $assumed->first()->date->toDateString()]) }}"
                class="link font-medium">Correct the times</a>
        </x-alert>
    @endif

    {{-- Punch card --}}
    <x-card class="mb-6">
        <div class="flex flex-wrap items-center gap-6">
            <div class="flex-1">
                <p class="text-xs tracking-wide text-slate-500 uppercase">{{ now()->format('l, d F Y') }}</p>
                <p class="mt-1 text-4xl font-semibold tabular-nums text-slate-900" data-clock>--:--:--</p>
                <p class="mt-1 text-sm text-slate-500">
                    @if ($today?->sessions->isNotEmpty())
                        {{ trans_choice(':count session|:count sessions', $today->sessions->count()) }}
                        since {{ $today->check_in->format('h:i A') }}
                        @if ($today->isOnBreak())
                            &middot; <span class="font-medium text-amber-600">on a {{ strtolower($today->openBreak()->label()) }} break</span>
                        @elseif ($today->isPunchedIn())
                            &middot; still working
                        @else
                            &middot; last out at {{ $today->check_out?->format('h:i A') }}
                        @endif
                        @if ($today->breaks->isNotEmpty())
                            &middot; breaks {{ $today->breaks->sum('duration_minutes') }}m
                        @endif
                    @else
                        Not punched in yet.
                    @endif
                </p>
            </div>

            @can('punch', App\Models\Attendance::class)
                <div class="flex flex-wrap gap-2">
                    @if ($today?->isOnBreak())
                        <form method="POST" action="{{ route('attendance.break.end') }}">
                            @csrf
                            <x-button size="lg">
                                <x-icon.clock class="size-4" />
                                End {{ strtolower($today->openBreak()->label()) }}
                            </x-button>
                        </form>
                    @elseif ($today?->isPunchedIn())
                        <x-button type="button" variant="secondary" size="lg" data-dialog-open="break">
                            <x-icon.clock class="size-4" /> Start break
                        </x-button>

                        <x-punch-form :action="route('attendance.check-out')" label="Punch out"
                            variant="danger" size="lg">
                            <x-slot:icon><x-icon.logout class="size-4" /></x-slot:icon>
                        </x-punch-form>
                    @else
                        {{-- Punching in again is allowed: a day can hold several sessions. --}}
                        <x-punch-form :action="route('attendance.check-in')"
                            :label="$today?->sessions->isNotEmpty() ? 'Punch in again' : 'Punch in'"
                            variant="success" size="lg">
                            <x-slot:icon><x-icon.check class="size-4" /></x-slot:icon>
                        </x-punch-form>
                    @endif

                    <x-button type="button" variant="ghost" size="lg" data-dialog-open="regularize">
                        Request correction
                    </x-button>
                </div>
            @else
                @can('attendance.punch')
                    <div class="max-w-sm text-sm text-slate-500">
                        Recording your own attendance is switched off for this organisation. Your
                        days are entered by HR — raise a correction if one of them is wrong.
                        <div class="mt-3">
                            <x-button type="button" variant="secondary" size="sm" data-dialog-open="regularize">
                                Request correction
                            </x-button>
                        </div>
                    </div>
                @endcan
            @endcan
        </div>

        <div data-punch-anchor></div>

        @if ($today?->sessions->isNotEmpty() || $today?->breaks->isNotEmpty())
            <div class="mt-5 grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        Sessions
                        <span class="ml-1 font-normal normal-case">
                            &middot; {{ $today->sessions->sum('duration_minutes') }}m at work
                        </span>
                    </p>
                    <ul class="mt-2 space-y-1.5 text-sm">
                        @foreach ($today->sessions as $session)
                            <li class="flex items-center justify-between gap-3">
                                <span class="text-slate-700">
                                    {{ $session->started_at->format('h:i A') }}
                                    &rarr;
                                    {{ $session->ended_at?->format('h:i A') ?? 'now' }}
                                </span>
                                <span class="tabular-nums text-slate-500">
                                    @if ($session->isOpen())
                                        <x-badge color="emerald" dot>Running</x-badge>
                                    @else
                                        {{ $session->duration_minutes }}m
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        Breaks
                        <span class="ml-1 font-normal normal-case">
                            &middot; {{ $today->breaks->sum('duration_minutes') }}m away
                        </span>
                    </p>

                    @if ($today->breaks->isEmpty())
                        <p class="mt-2 text-sm text-slate-500">None today.</p>
                    @else
                        <ul class="mt-2 space-y-1.5 text-sm">
                            @foreach ($today->breaks as $break)
                                <li class="flex items-start justify-between gap-3">
                                    <span class="min-w-0 text-slate-700">
                                        {{ $break->label() }}
                                        <span class="block text-xs text-slate-500">
                                            {{ $break->started_at->format('h:i A') }}
                                            &rarr; {{ $break->ended_at?->format('h:i A') ?? 'now' }}
                                            @if ($break->comment) &middot; {{ $break->comment }} @endif
                                        </span>
                                    </span>
                                    <span class="shrink-0 tabular-nums text-slate-500">
                                        @if ($break->isOpen())
                                            <x-badge color="amber" dot>Running</x-badge>
                                        @else
                                            {{ $break->duration_minutes }}m
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        {{-- Only worth saying while there is a punch to make. --}}
        @if (App\Services\AttendanceService::locationRequired() && auth()->user()->can('punch', App\Models\Attendance::class))
            <p class="mt-4 flex items-start gap-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
                <x-icon.check class="mt-0.5 size-4 shrink-0 text-slate-400" />
                Your location is recorded when you check in and out. Your browser will ask
                for permission the first time.
            </p>
        @endif
    </x-card>

    <x-card :title="$month->format('F Y') . ' summary'" class="mb-6">
        <x-slot:actions>
            @include('attendance._month-picker', ['action' => route('attendance.index')])
        </x-slot:actions>
        @include('attendance._totals')

        <div class="mt-6 border-t border-slate-100 pt-5">
            @include('attendance._calendar')
        </div>
    </x-card>

    <x-dialog name="regularize" title="Request attendance correction"
        :open="request()->filled('correct')">
        <form id="regularize-form" method="POST" action="{{ route('attendance.regularizations.store') }}">
            @csrf
            <div class="space-y-4 px-5 py-5">
                <p class="text-sm text-slate-500">
                    Use this when you forgot to punch or the recorded times are wrong.
                    Your manager reviews the request before the record changes.
                </p>
                <x-input label="Date" name="date" type="date" required
                    :value="request('correct', now()->toDateString())" max="{{ now()->toDateString() }}" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input label="Check-in time" name="requested_check_in" type="time" />
                    <x-input label="Check-out time" name="requested_check_out" type="time" />
                </div>
                <x-textarea label="Reason" name="reason" rows="3" required
                    help="Explain what happened so your approver has the context." />
            </div>
        </form>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" data-dialog-close="regularize">Cancel</x-button>
                <x-button form="regularize-form">Submit request</x-button>
            </div>
        </x-slot:footer>
    </x-dialog>
</x-app-layout>
