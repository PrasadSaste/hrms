<x-app-layout title="Leave balance">
    <x-page-header title="Leave balance" :subtitle="'Entitlement and usage for ' . $year">
        <x-slot:actions>
            <form method="GET" class="flex items-center gap-2">
                <select name="year" class="form-select w-28 py-1.5 text-sm" data-auto-submit>
                    @foreach (range((int) date('Y'), (int) date('Y') - 4) as $y)
                        <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <x-button href="{{ route('leave.create') }}"><x-icon.plus class="size-4" /> Apply</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($balance as $row)
            <x-card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 font-medium text-slate-900">
                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $row['leave_type']->color }}"></span>
                            <span class="truncate">{{ $row['leave_type']->name }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $row['leave_type']->code }}
                            @if ($row['leave_type']->accruesMonthly())
                                &middot; {{ $row['leave_type']->entitlementLabel() }}
                            @endif
                        </p>
                    </div>
                    <x-badge :color="$row['leave_type']->is_paid ? 'emerald' : 'amber'">
                        {{ $row['leave_type']->is_paid ? 'Paid' : 'Unpaid' }}
                    </x-badge>
                </div>

                <p class="mt-4 text-3xl font-semibold text-slate-900">
                    {{ rtrim(rtrim(number_format($row['remaining'], 1), '0'), '.') }}
                    <span class="text-base font-normal text-slate-400">days left</span>
                </p>

                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full"
                        style="width: {{ $row['entitled'] > 0 ? min(100, round($row['used'] / $row['entitled'] * 100)) : 0 }}%; background-color: {{ $row['leave_type']->color }}"></div>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Allocated</dt>
                        <dd class="font-medium text-slate-900">{{ rtrim(rtrim(number_format($row['allocated'], 1), '0'), '.') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Carried</dt>
                        <dd class="font-medium text-slate-900">{{ rtrim(rtrim(number_format($row['carried_forward'], 1), '0'), '.') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Used</dt>
                        <dd class="font-medium text-slate-900">{{ rtrim(rtrim(number_format($row['used'], 1), '0'), '.') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Pending</dt>
                        <dd class="font-medium text-amber-600">{{ rtrim(rtrim(number_format($row['pending'], 1), '0'), '.') }}</dd>
                    </div>
                </dl>
            </x-card>
        @empty
            <div class="lg:col-span-3">
                <x-card><x-empty title="No leave allocated" message="Ask HR to allocate leave for this year." /></x-card>
            </div>
        @endforelse
    </div>

    @if ($accruals->isNotEmpty())
        <x-card :title="'Monthly leave credited in ' . $year" :padded="false"
            subtitle="Each month is credited once it has been earned. A part month means you joined or left part way through it.">
            <div class="table-wrap is-scrollable">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Month</th><th>Type</th><th>At</th>
                            <th class="num">Credited</th><th>Credited on</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accruals as $accrual)
                            <tr>
                                <td class="whitespace-nowrap font-medium">{{ $accrual->periodLabel() }}</td>
                                <td>{{ $accrual->leaveType->name }}</td>
                                <td>
                                    <span class="text-sm text-slate-600">
                                        {{ rtrim(rtrim(number_format($accrual->rate, 2), '0'), '.') }} a month
                                    </span>
                                    <span class="block text-xs text-slate-400">{{ $accrual->basisLabel() }}</span>
                                </td>
                                <td class="num tabular-nums font-medium">
                                    {{ rtrim(rtrim(number_format($accrual->days, 2), '0'), '.') }}
                                    @if ($accrual->isPartial())
                                        <span class="block text-xs font-normal text-slate-400">part month</span>
                                    @endif
                                </td>
                                <td class="text-sm text-slate-500">{{ $accrual->created_at->format('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200 font-medium">
                            <td colspan="3" class="text-slate-500">Credited so far</td>
                            <td class="num tabular-nums">
                                {{ rtrim(rtrim(number_format($accruals->sum('days'), 2), '0'), '.') }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-card>

        <div class="h-6"></div>
    @endif

    <x-card :title="'Leave history in ' . $year" :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th><th>Type</th><th>Period</th>
                        <th class="num">Days</th><th>Status</th><th>Reason</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $leaveRequest)
                        <tr>
                            <td class="font-mono text-xs text-slate-500">{{ $leaveRequest->reference }}</td>
                            <td>{{ $leaveRequest->leaveType->name }}</td>
                            <td class="whitespace-nowrap">{{ $leaveRequest->periodLabel() }}</td>
                            <td class="num tabular-nums">
                                {{ rtrim(rtrim(number_format($leaveRequest->total_days, 1), '0'), '.') }}
                            </td>
                            <td><x-badge :color="$leaveRequest->status->color()">{{ $leaveRequest->status->label() }}</x-badge></td>
                            <td class="max-w-64 truncate text-sm text-slate-600">{{ $leaveRequest->reason }}</td>
                            <td class="num">
                                <x-button href="{{ route('leave.show', $leaveRequest) }}" variant="ghost" size="sm">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No leave taken in {{ $year }}" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
