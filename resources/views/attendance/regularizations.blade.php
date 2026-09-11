<x-app-layout title="Attendance regularisations">
    <x-page-header title="Attendance regularisations"
        subtitle="Corrections requested when a punch was missed or recorded wrongly">
        <x-slot:actions>
            <x-button type="button" data-dialog-open="new-regularization">
                <x-icon.plus class="size-4" /> New request
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('attendance.regularizations.index')">
        <x-select label="Status" name="status" :selected="request('status')" placeholder="All statuses"
            :options="$statuses" class="sm:w-44" />
        <x-button variant="secondary">Filter</x-button>
        @if (request('status'))
            <x-button href="{{ route('attendance.regularizations.index') }}" variant="ghost">Clear</x-button>
        @endif
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th><th>Date</th><th>Requested times</th><th>Reason</th>
                        <th>Status</th><th>Reviewed by</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $regularization)
                        <tr>
                            <td>
                                <div class="font-medium text-slate-900">{{ $regularization->employee->full_name }}</div>
                                <div class="text-xs text-slate-500">{{ $regularization->employee->department?->name }}</div>
                            </td>
                            <td class="whitespace-nowrap">{{ $regularization->date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap tabular-nums">
                                {{ $regularization->requested_check_in?->format('h:i A') ?? '-' }}
                                &ndash;
                                {{ $regularization->requested_check_out?->format('h:i A') ?? '-' }}
                            </td>
                            <td class="max-w-64 text-sm text-slate-600">{{ $regularization->reason }}</td>
                            <td>
                                <x-badge :color="$regularization->status->color()">{{ $regularization->status->label() }}</x-badge>
                                @if ($regularization->review_remarks)
                                    <div class="mt-0.5 max-w-48 truncate text-xs text-slate-500">{{ $regularization->review_remarks }}</div>
                                @endif
                            </td>
                            <td class="text-sm text-slate-600">
                                {{ $regularization->reviewer?->name ?? '-' }}
                                @if ($regularization->reviewed_at)
                                    <div class="text-xs text-slate-400">{{ $regularization->reviewed_at->format('d M Y') }}</div>
                                @endif
                            </td>
                            <td class="num whitespace-nowrap">
                                @if ($canApprove && $regularization->isPending())
                                    <x-button type="button" size="sm" variant="success"
                                        data-dialog-open="approve-regularization"
                                        data-action="{{ route('attendance.regularizations.approve', $regularization) }}">
                                        Approve
                                    </x-button>
                                    <x-button type="button" size="sm" variant="secondary"
                                        data-dialog-open="reject-regularization"
                                        data-action="{{ route('attendance.regularizations.reject', $regularization) }}">
                                        Reject
                                    </x-button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No regularisation requests" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $requests->links() }}</div>

    <x-dialog name="new-regularization" title="Request attendance correction">
        <form id="new-regularization-form" method="POST" action="{{ route('attendance.regularizations.store') }}">
            @csrf
            <div class="space-y-4 px-5 py-5">
                <x-input label="Date" name="date" type="date" required :value="now()->toDateString()" max="{{ now()->toDateString() }}" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input label="Check-in time" name="requested_check_in" type="time" />
                    <x-input label="Check-out time" name="requested_check_out" type="time" />
                </div>
                <x-textarea label="Reason" name="reason" rows="3" required />
            </div>
        </form>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" data-dialog-close="new-regularization">Cancel</x-button>
                <x-button form="new-regularization-form">Submit request</x-button>
            </div>
        </x-slot:footer>
    </x-dialog>

    @if ($canApprove)
        <x-dialog name="approve-regularization" title="Approve regularisation" size="sm">
            <form id="approve-regularization-form" method="POST" action="">
                @csrf
                <div class="space-y-4 px-5 py-5">
                    <p class="text-sm text-slate-600">
                        Approving writes the requested times into the attendance record and recalculates worked hours.
                    </p>
                    <x-textarea label="Remarks (optional)" name="review_remarks" rows="2" />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" data-dialog-close="approve-regularization">Cancel</x-button>
                    <x-button form="approve-regularization-form" variant="success">Approve and update</x-button>
                </div>
            </x-slot:footer>
        </x-dialog>

        <x-dialog name="reject-regularization" title="Reject regularisation" size="sm">
            <form id="reject-regularization-form" method="POST" action="">
                @csrf
                <div class="space-y-4 px-5 py-5">
                    <x-textarea label="Reason for rejection" name="review_remarks" rows="3" required
                        help="The employee sees this in their notification." />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" data-dialog-close="reject-regularization">Cancel</x-button>
                    <x-button form="reject-regularization-form" variant="danger">Reject request</x-button>
                </div>
            </x-slot:footer>
        </x-dialog>
    @endif
</x-app-layout>
