<x-app-layout title="Leave">
    <x-page-header title="Leave requests" subtitle="Applications, approvals and history">
        <x-slot:actions>
            <x-button href="{{ route('leave.export', request()->query()) }}" variant="secondary">
                <x-icon.download class="size-4" /> Export
            </x-button>
            @can('create', App\Models\LeaveRequest::class)
                <x-button href="{{ route('leave.create') }}"><x-icon.plus class="size-4" /> Apply for leave</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($balance->isNotEmpty())
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($balance->take(4) as $row)
                <x-card class="p-4">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="flex min-w-0 items-center gap-2 text-xs font-medium tracking-wide text-slate-500 uppercase">
                            <span class="size-2 shrink-0 rounded-full" style="background-color: {{ $row['leave_type']->color }}"></span>
                            <span class="truncate">{{ $row['leave_type']->name }}</span>
                        </p>
                    </div>
                    <p class="mt-1.5 text-2xl font-semibold text-slate-900">
                        {{ rtrim(rtrim(number_format($row['remaining'], 1), '0'), '.') }}
                        <span class="text-sm font-normal text-slate-400">/ {{ rtrim(rtrim(number_format($row['entitled'], 1), '0'), '.') }}</span>
                    </p>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full" style="width: {{ $row['entitled'] > 0 ? min(100, round($row['used'] / $row['entitled'] * 100)) : 0 }}%; background-color: {{ $row['leave_type']->color }}"></div>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">
                        {{ rtrim(rtrim(number_format($row['used'], 1), '0'), '.') }} used
                        @if ($row['pending'] > 0)
                            &middot; {{ rtrim(rtrim(number_format($row['pending'], 1), '0'), '.') }} pending
                        @endif
                    </p>
                </x-card>
            @endforeach
        </div>
    @endif

    @if ($canApprove && $pendingCount > 0)
        <x-alert type="warning" class="mb-4" :dismissible="false">
            {{ $pendingCount }} request(s) are waiting for a decision.
            <a href="{{ route('leave.index', ['status' => 'pending']) }}" class="font-medium underline">Show only pending</a>
        </x-alert>
    @endif

    <x-filter-bar :action="route('leave.index')">
        <x-select label="Status" name="status" :selected="request('status')" placeholder="All statuses"
            :options="$statuses" class="sm:w-40" />
        <x-select label="Leave type" name="leave_type_id" :selected="request('leave_type_id')" placeholder="All types"
            :options="$leaveTypes->pluck('name', 'id')->all()" class="sm:w-44" />
        @can('leave.view-all')
            <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
                :options="$branches->pluck('name', 'id')->all()" class="sm:w-40" />
            <x-select label="Department" name="department_id" :selected="request('department_id')" placeholder="All departments"
                :options="$departments->pluck('name', 'id')->all()" class="sm:w-44" />
        @endcan
        <x-input label="From" name="from" type="date" :value="request('from')" class="sm:w-40" />
        <x-input label="To" name="to" type="date" :value="request('to')" class="sm:w-40" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
        @if (request()->hasAny(['status', 'leave_type_id', 'branch_id', 'department_id', 'from', 'to']))
            <x-button href="{{ route('leave.index') }}" variant="ghost">Clear</x-button>
        @endif
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference</th><th>Employee</th><th>Type</th><th>Period</th>
                        <th class="num">Days</th><th>Status</th><th>Applied</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $leaveRequest)
                        <tr>
                            <td class="font-mono text-xs text-slate-500">{{ $leaveRequest->reference }}</td>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <x-avatar :name="$leaveRequest->employee->full_name" size="xs" />
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-slate-900">{{ $leaveRequest->employee->full_name }}</div>
                                        <div class="truncate text-xs text-slate-500">{{ $leaveRequest->employee->department?->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="flex items-center gap-1.5">
                                    <span class="size-2 rounded-full" style="background-color: {{ $leaveRequest->leaveType->color }}"></span>
                                    {{ $leaveRequest->leaveType->name }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap">{{ $leaveRequest->periodLabel() }}</td>
                            <td class="num font-medium tabular-nums">
                                {{ rtrim(rtrim(number_format($leaveRequest->total_days, 1), '0'), '.') }}
                            </td>
                            <td><x-badge :color="$leaveRequest->status->color()" dot>{{ $leaveRequest->status->label() }}</x-badge></td>
                            <td class="text-xs whitespace-nowrap text-slate-500">
                                {{ $leaveRequest->applied_on?->format('d M Y') }}
                            </td>
                            <td class="num">
                                <x-button href="{{ route('leave.show', $leaveRequest) }}" variant="ghost" size="sm">
                                    {{ $leaveRequest->isPending() && auth()->user()->can('approve', $leaveRequest) ? 'Review' : 'View' }}
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty title="No leave requests" message="Nothing matches the current filters.">
                                    <x-slot:action>
                                        @can('create', App\Models\LeaveRequest::class)
                                            <x-button href="{{ route('leave.create') }}">Apply for leave</x-button>
                                        @endcan
                                    </x-slot:action>
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $requests->links() }}</div>
</x-app-layout>
