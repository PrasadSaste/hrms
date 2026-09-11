@php
    // Each report is a card under the heading it belongs to. A section that has
    // nothing the viewer may open simply does not appear.
    $sections = [
        'Attendance' => [
            [
                'can' => $can['attendance'],
                'route' => 'reports.breaks',
                'icon' => 'clock',
                'tint' => 'bg-orange-50 text-orange-600',
                'title' => 'Break report',
                'description' => 'Every break your employees took, by day, or totalled per person for a month.',
            ],
            [
                'can' => $can['attendance'],
                'route' => 'reports.attendance.daily',
                'icon' => 'list',
                'tint' => 'bg-slate-100 text-slate-600',
                'title' => 'Daily attendance report',
                'description' => 'One day across the organisation: shift, arrival, punch in and out, and where from.',
            ],
            [
                'can' => $can['attendance'],
                'route' => 'reports.attendance.monthly',
                'icon' => 'calendar',
                'tint' => 'bg-sky-50 text-sky-600',
                'title' => 'Monthly attendance report',
                'description' => 'A whole month as a grid: working time, breaks, overtime or lateness, day by day.',
            ],
            [
                'can' => $can['attendance'],
                'route' => 'reports.attendance.in-out',
                'icon' => 'history',
                'tint' => 'bg-amber-50 text-amber-600',
                'title' => 'Monthly in-out report',
                'description' => 'The first punch in and last punch out of every day, for the whole month.',
            ],
            [
                'can' => $can['attendance'],
                'route' => 'reports.attendance',
                'icon' => 'chart',
                'tint' => 'bg-emerald-50 text-emerald-600',
                'title' => 'Attendance summary',
                'description' => 'Present, absent and late days per employee for a month, with hours and overtime.',
            ],
        ],
        'Leave' => [
            [
                'can' => $can['leave'],
                'route' => 'reports.leave',
                'icon' => 'calendar',
                'tint' => 'bg-sky-50 text-sky-600',
                'title' => 'Leave report',
                'description' => 'Approved leave over any period, broken down by employee and by leave type.',
            ],
        ],
        'Payroll' => [
            [
                'can' => $can['payroll'],
                'route' => 'reports.payroll',
                'icon' => 'currency',
                'tint' => 'bg-amber-50 text-amber-600',
                'title' => 'Payroll report',
                'description' => 'Salary cost by month and department, with gross, deductions and net paid.',
            ],
        ],
        'Workforce' => [
            [
                'can' => $can['employees'],
                'route' => 'reports.employees',
                'icon' => 'users',
                'tint' => 'bg-violet-50 text-violet-600',
                'title' => 'Workforce report',
                'description' => 'Headcount by department, branch and tenure, plus joiners, exits and attrition.',
            ],
        ],
    ];
@endphp

<x-app-layout title="Reports">
    <x-page-header title="Reports" subtitle="Attendance, leave, payroll and workforce insights" />

    <div class="space-y-8">
        @foreach ($sections as $heading => $cards)
            @php $visible = collect($cards)->where('can', true); @endphp
            @continue($visible->isEmpty())

            <section>
                <h2 class="border-b border-slate-200 pb-2 text-base font-semibold text-slate-900">{{ $heading }}</h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($visible as $card)
                        <a href="{{ route($card['route']) }}"
                            class="card flex flex-col p-5 transition hover:border-brand-300 hover:shadow-sm">
                            <span class="flex size-11 items-center justify-center rounded-lg {{ $card['tint'] }}">
                                <x-dynamic-component :component="'icon.' . $card['icon']" class="size-5" />
                            </span>
                            <h3 class="mt-3 font-semibold text-slate-900">{{ $card['title'] }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $card['description'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    @unless (collect($can)->contains(true))
        <x-card class="mt-6">
            <x-empty title="No reports available"
                message="Your role does not include access to any reports. Ask an administrator if you need them." />
        </x-card>
    @endunless
</x-app-layout>
