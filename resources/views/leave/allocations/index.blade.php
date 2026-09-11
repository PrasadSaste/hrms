<x-app-layout title="Leave allocations">
    <x-page-header title="Leave allocations" :subtitle="'Entitlements granted for ' . $year">
        <x-slot:actions>
            <x-button type="button" variant="secondary" data-dialog-open="add-allocation">
                <x-icon.plus class="size-4" /> Add allocation
            </x-button>
            <x-button type="button" data-dialog-open="bulk-allocate">Allocate for a year</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('leave-allocations.index')">
        <x-select label="Year" name="year" :selected="$year"
            :options="collect(range((int) date('Y') + 1, (int) date('Y') - 4))->mapWithKeys(fn ($y) => [$y => $y])->all()"
            class="sm:w-32" />
        <x-select label="Employee" name="employee_id" :selected="request('employee_id')" placeholder="All employees"
            :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->first_name . ' ' . $e->last_name])->all()" class="sm:w-52" />
        <x-select label="Leave type" name="leave_type_id" :selected="request('leave_type_id')" placeholder="All types"
            :options="$leaveTypes->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th><th>Leave type</th>
                        <th class="num">Allocated</th><th class="num">Carried</th>
                        <th class="num">Entitled</th><th class="num">Used</th>
                        <th class="num">Remaining</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($allocations as $allocation)
                        <tr>
                            <td>
                                <div class="font-medium text-slate-900">{{ $allocation->employee->full_name }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $allocation->employee->employee_code }}</div>
                            </td>
                            <td>
                                <span class="flex items-center gap-1.5">
                                    <span class="size-2 rounded-full" style="background-color: {{ $allocation->leaveType->color }}"></span>
                                    {{ $allocation->leaveType->name }}
                                </span>
                            </td>
                            <td class="num tabular-nums">{{ rtrim(rtrim(number_format($allocation->allocated_days, 1), '0'), '.') }}</td>
                            <td class="num tabular-nums">{{ rtrim(rtrim(number_format($allocation->carried_forward_days, 1), '0'), '.') }}</td>
                            <td class="num font-medium tabular-nums">{{ rtrim(rtrim(number_format($allocation->entitledDays(), 1), '0'), '.') }}</td>
                            <td class="num tabular-nums">{{ rtrim(rtrim(number_format($allocation->used_days, 1), '0'), '.') }}</td>
                            <td class="num font-semibold tabular-nums text-emerald-700">
                                {{ rtrim(rtrim(number_format($allocation->remainingDays(), 1), '0'), '.') }}
                            </td>
                            <td class="num">
                                <x-button type="button" variant="ghost" size="sm"
                                    data-dialog-open="edit-allocation"
                                    data-action="{{ route('leave-allocations.update', $allocation) }}"
                                    data-fill-allocated_days="{{ $allocation->allocated_days }}"
                                    data-fill-carried_forward_days="{{ $allocation->carried_forward_days }}"
                                    data-fill-used_days="{{ $allocation->used_days }}"
                                    data-fill-notes="{{ $allocation->notes }}"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty title="No allocations for {{ $year }}"
                                    message="Run a bulk allocation to grant the year's entitlement to everyone." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $allocations->links() }}</div>

    <x-dialog name="bulk-allocate" title="Allocate leave for a year" size="sm">
        <form id="bulk-allocate-form" method="POST" action="{{ route('leave-allocations.bulk') }}">
            @csrf
            <div class="space-y-4 px-5 py-5">
                <p class="text-sm text-slate-600">
                    Grants every active employee their entitlement for the chosen year,
                    prorated by joining date, and carries forward unused days where the policy allows it.
                    Existing allocations are refreshed, not duplicated.
                </p>
                <x-input label="Year" name="year" type="number" min="2000" max="2100" :value="$year" required />
                <x-select label="Branch" name="branch_id" placeholder="All branches"
                    :options="$branches->pluck('name', 'id')->all()" />
            </div>
        </form>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" data-dialog-close="bulk-allocate">Cancel</x-button>
                <x-button form="bulk-allocate-form">Run allocation</x-button>
            </div>
        </x-slot:footer>
    </x-dialog>

    <x-dialog name="add-allocation" title="Add or update an allocation" size="sm">
        <form id="add-allocation-form" method="POST" action="{{ route('leave-allocations.store') }}">
            @csrf
            <div class="space-y-4 px-5 py-5">
                <x-select label="Employee" name="employee_id" required placeholder="Choose an employee"
                    :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->first_name . ' ' . $e->last_name . ' (' . $e->employee_code . ')'])->all()" />
                <x-select label="Leave type" name="leave_type_id" required placeholder="Choose a leave type"
                    :options="$leaveTypes->pluck('name', 'id')->all()" />
                <x-input label="Year" name="year" type="number" min="2000" max="2100" :value="$year" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input label="Allocated days" name="allocated_days" type="number" step="0.5" min="0" required value="0" />
                    <x-input label="Carried forward" name="carried_forward_days" type="number" step="0.5" min="0" value="0" />
                </div>
                <x-textarea label="Notes" name="notes" rows="2" />
            </div>
        </form>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" data-dialog-close="add-allocation">Cancel</x-button>
                <x-button form="add-allocation-form">Save allocation</x-button>
            </div>
        </x-slot:footer>
    </x-dialog>

    <x-dialog name="edit-allocation" title="Edit allocation" size="sm">
        <form id="edit-allocation-form" method="POST" action="">
            @csrf @method('PUT')
            <div class="space-y-4 px-5 py-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input label="Allocated days" name="allocated_days" type="number" step="0.5" min="0" required />
                    <x-input label="Carried forward" name="carried_forward_days" type="number" step="0.5" min="0" required />
                </div>
                <x-input label="Used days" name="used_days" type="number" step="0.5" min="0" required
                    help="Adjust only to correct a mistake. Approvals update this automatically." />
                <x-textarea label="Notes" name="notes" rows="2" />
            </div>
        </form>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-button type="button" variant="secondary" data-dialog-close="edit-allocation">Cancel</x-button>
                <x-button form="edit-allocation-form">Save changes</x-button>
            </div>
        </x-slot:footer>
    </x-dialog>
</x-app-layout>
