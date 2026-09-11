<x-app-layout :title="'Leave ' . $request->reference">
    <x-page-header :title="$request->leaveType->name" :subtitle="$request->reference . ' · ' . $request->periodLabel()"
        :back="route('leave.index')">
        <x-slot:actions>
            <x-badge :color="$request->status->color()" dot class="text-sm">{{ $request->status->label() }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Request">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Leave type</dt>
                        <dd class="mt-0.5 flex items-center gap-2 text-slate-900">
                            <span class="size-2.5 rounded-full" style="background-color: {{ $request->leaveType->color }}"></span>
                            {{ $request->leaveType->name }}
                            <x-badge :color="$request->leaveType->is_paid ? 'emerald' : 'amber'">
                                {{ $request->leaveType->is_paid ? 'Paid' : 'Unpaid' }}
                            </x-badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Duration</dt>
                        <dd class="mt-0.5 text-slate-900">
                            {{ rtrim(rtrim(number_format($request->total_days, 1), '0'), '.') }} working day(s)
                            &middot; {{ $request->day_type->label() }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">From</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $request->start_date->format('l, d F Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">To</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $request->end_date->format('l, d F Y') }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Reason</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-slate-900">{{ $request->reason }}</dd>
                    </div>
                    @if ($request->contact_during_leave)
                        <div>
                            <dt class="text-xs tracking-wide text-slate-500 uppercase">Contact while away</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $request->contact_during_leave }}</dd>
                        </div>
                    @endif
                    @if ($request->attachment_path)
                        <div>
                            <dt class="text-xs tracking-wide text-slate-500 uppercase">Attachment</dt>
                            <dd class="mt-0.5"><x-badge color="sky">Document attached</x-badge></dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @if ($request->actioned_at)
                <x-card :title="'Decision: ' . $request->status->label()">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs tracking-wide text-slate-500 uppercase">Actioned by</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $request->approver?->name ?? 'System' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs tracking-wide text-slate-500 uppercase">Actioned on</dt>
                            <dd class="mt-0.5 text-slate-900">{{ $request->actioned_at->format('d M Y, h:i A') }}</dd>
                        </div>
                        @if ($request->approver_remarks)
                            <div class="sm:col-span-2">
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">Remarks</dt>
                                <dd class="mt-0.5 whitespace-pre-line text-slate-900">{{ $request->approver_remarks }}</dd>
                            </div>
                        @endif
                        @if ($request->cancel_reason)
                            <div class="sm:col-span-2">
                                <dt class="text-xs tracking-wide text-slate-500 uppercase">Cancellation reason</dt>
                                <dd class="mt-0.5 text-slate-900">{{ $request->cancel_reason }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-card>
            @endif

            {{-- ------------------------------------------------- approver actions --}}
            @can('approve', $request)
                <x-card title="Your decision" subtitle="The employee is notified by email either way">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <form method="POST" action="{{ route('leave.approve', $request) }}" class="space-y-3">
                            @csrf
                            <x-textarea label="Approval remarks (optional)" name="approver_remarks" rows="3" />
                            <x-button variant="success" class="w-full"><x-icon.check class="size-4" /> Approve request</x-button>
                        </form>

                        <form method="POST" action="{{ route('leave.reject', $request) }}" class="space-y-3">
                            @csrf
                            <x-textarea label="Reason for rejection" name="approver_remarks" rows="3" required />
                            <x-button variant="danger" class="w-full"><x-icon.x class="size-4" /> Reject request</x-button>
                        </form>
                    </div>
                </x-card>
            @endcan

            @can('cancel', $request)
                <x-card title="Cancel this request">
                    <form method="POST" action="{{ route('leave.cancel', $request) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <x-input label="Reason (optional)" name="cancel_reason" class="flex-1" />
                        <x-button variant="secondary" data-confirm="Cancel this leave request? The balance is returned if it was approved.">
                            Cancel request
                        </x-button>
                    </form>
                </x-card>
            @endcan
        </div>

        <div class="space-y-6">
            <x-card title="Employee">
                <div class="flex items-center gap-3">
                    <x-avatar :name="$request->employee->full_name" :src="$request->employee->photoUrl()" size="lg" />
                    <div class="min-w-0">
                        <a href="{{ route('employees.show', $request->employee) }}" class="font-medium text-slate-900 hover-ink">
                            {{ $request->employee->full_name }}
                        </a>
                        <p class="text-sm text-slate-500">{{ $request->employee->designation?->name ?? '-' }}</p>
                        <p class="font-mono text-xs text-slate-400">{{ $request->employee->employee_code }}</p>
                    </div>
                </div>

                <dl class="mt-4 space-y-2.5 border-t border-slate-100 pt-4 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Department</dt>
                        <dd class="text-right text-slate-900">{{ $request->employee->department?->name ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Reports to</dt>
                        <dd class="text-right text-slate-900">{{ $request->employee->manager?->full_name ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Applied on</dt>
                        <dd class="text-right text-slate-900">{{ $request->applied_on?->format('d M Y, h:i A') }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card :title="'Balance in ' . $request->start_date->format('Y')">
                @foreach ($balance as $row)
                    <div @class([
                        'flex items-baseline justify-between gap-2 rounded-lg px-2 py-2',
                        'bg-brand-50' => $row['leave_type']->id === $request->leave_type_id,
                    ])>
                        <span class="text-sm text-slate-700">{{ $row['leave_type']->name }}</span>
                        <span class="text-sm font-semibold text-slate-900">
                            {{ rtrim(rtrim(number_format($row['remaining'], 1), '0'), '.') }}
                            <span class="text-xs font-normal text-slate-400">/ {{ rtrim(rtrim(number_format($row['entitled'], 1), '0'), '.') }}</span>
                        </span>
                    </div>
                @endforeach
            </x-card>
        </div>
    </div>
</x-app-layout>
