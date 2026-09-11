<x-app-layout title="Salary structures">
    <x-page-header title="Salary structures" subtitle="What each employee is paid, and from when">
        <x-slot:actions>
            <x-button href="{{ route('salary-structures.create') }}"><x-icon.plus class="size-4" /> New structure</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('salary-structures.index')">
        <x-select label="Employee" name="employee_id" :selected="request('employee_id')" placeholder="All employees"
            :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->first_name . ' ' . $e->last_name . ' (' . $e->employee_code . ')'])->all()"
            class="sm:w-64" />
        <x-select label="Status" name="status" :selected="request('status')" placeholder="All statuses"
            :options="['active' => 'Active', 'inactive' => 'Inactive']" class="sm:w-40" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
        @if (request()->hasAny(['employee_id', 'status']))
            <x-button href="{{ route('salary-structures.index') }}" variant="ghost">Clear</x-button>
        @endif
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th><th>Effective</th>
                        <th class="num">Annual CTC</th><th class="num">Monthly gross</th>
                        <th class="num">Basic</th><th>Payment mode</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($structures as $structure)
                        <tr>
                            <td>
                                <div class="font-medium text-slate-900">{{ $structure->employee->full_name }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $structure->employee->employee_code }} &middot;
                                    {{ $structure->employee->designation?->name ?? '-' }}
                                </div>
                            </td>
                            <td class="text-sm whitespace-nowrap">
                                {{ $structure->effective_from->format('d M Y') }}
                                <div class="text-xs text-slate-500">
                                    {{ $structure->effective_to ? 'to ' . $structure->effective_to->format('d M Y') : 'ongoing' }}
                                </div>
                            </td>
                            <td class="num tabular-nums"><x-money :amount="$structure->ctc_annual" :currency="$structure->currency" :decimals="0" /></td>
                            <td class="num tabular-nums"><x-money :amount="$structure->gross_monthly" :currency="$structure->currency" /></td>
                            <td class="num tabular-nums"><x-money :amount="$structure->basic_salary" :currency="$structure->currency" /></td>
                            <td class="text-sm text-slate-600">
                                {{ App\Models\SalaryStructure::PAYMENT_MODES[$structure->payment_mode] ?? $structure->payment_mode }}
                            </td>
                            <td><x-badge :color="$structure->status === 'active' ? 'emerald' : 'slate'" dot>{{ ucfirst($structure->status) }}</x-badge></td>
                            <td class="num whitespace-nowrap">
                                <x-button href="{{ route('salary-structures.show', $structure) }}" variant="ghost" size="sm">View</x-button>
                                <x-button href="{{ route('salary-structures.edit', $structure) }}" variant="ghost" size="sm"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty title="No salary structures"
                                    message="Payroll skips employees who have no active structure.">
                                    <x-slot:action>
                                        <x-button href="{{ route('salary-structures.create') }}">Create structure</x-button>
                                    </x-slot:action>
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $structures->links() }}</div>
</x-app-layout>
