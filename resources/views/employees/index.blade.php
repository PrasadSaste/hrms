<x-app-layout title="Employees">
    <x-page-header title="Employees" subtitle="Everyone on the roll, across branches and departments">
        <x-slot:actions>
            <x-button href="{{ route('employees.export', request()->query()) }}" variant="secondary">
                <x-icon.download class="size-4" /> Export CSV
            </x-button>
            @can('create', App\Models\Employee::class)
                <x-button href="{{ route('employees.create') }}"><x-icon.plus class="size-4" /> Add employee</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Active headcount" :value="$stats['total']" color="brand">
            <x-slot:icon><x-icon.users class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="On probation" :value="$stats['on_probation']" color="amber">
            <x-slot:icon><x-icon.clock class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="On notice" :value="$stats['notice_period']" color="rose">
            <x-slot:icon><x-icon.logout class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Joined this month" :value="$stats['joined_this_month']" color="emerald">
            <x-slot:icon><x-icon.plus class="size-5" /></x-slot:icon>
        </x-stat>
    </div>

    <x-card :padded="false">
        {{-- Attached to the register it filters, not floating above it. --}}
        <x-toolbar :action="route('employees.index')" :count="$employees->count()" :of="$employees->total()" noun="employee">
            <x-input label="Search" name="search" :value="request('search')" placeholder="Name, code, email or phone" />
            <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
                :options="$branches->pluck('name', 'id')->all()" data-auto-submit />
            <x-select label="Department" name="department_id" :selected="request('department_id')" placeholder="All departments"
                :options="$departments->pluck('name', 'id')->all()" data-auto-submit />
            <x-select label="Employment status" name="employment_status" :selected="request('employment_status')"
                placeholder="All statuses" :options="App\Enums\EmploymentStatus::options()" data-auto-submit />
            <x-select label="Record status" name="status" :selected="request('status', 'active')"
                :options="['active' => 'Active', 'inactive' => 'Inactive']" data-auto-submit />
            <x-button variant="secondary" size="sm"><x-icon.search class="size-3.5" /> Search</x-button>
            @if (request()->hasAny(['search', 'branch_id', 'department_id', 'employment_status']))
                <a href="{{ route('employees.index') }}" class="text-[12px] font-medium text-slate-500 hover-ink">Clear</a>
            @endif
        </x-toolbar>

        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th><th>Email</th><th>Designation</th><th>Department</th><th>Branch</th>
                        <th>Reports to</th><th class="num">Joined</th><th>Status</th><th>Login</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <x-avatar :name="$employee->full_name" :src="$employee->photoUrl()" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('employees.show', $employee) }}" class="row-title">
                                            {{ $employee->full_name }}
                                        </a>
                                        <span class="row-sub font-mono">{{ $employee->employee_code }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="truncate-cell text-slate-500">{{ $employee->email }}</td>
                            <td>{{ $employee->designation?->name ?? '—' }}</td>
                            <td>{{ $employee->department?->name ?? '—' }}</td>
                            <td>{{ $employee->branch?->name ?? '—' }}</td>
                            <td>{{ $employee->manager?->full_name ?? '—' }}</td>
                            <td class="num">{{ $employee->date_of_joining->format('d M Y') }}</td>
                            <td>
                                <x-badge :color="$employee->employment_status->color()">
                                    {{ $employee->employment_status->label() }}
                                </x-badge>
                            </td>
                            <td>
                                @if ($employee->user)
                                    <x-badge color="emerald" dot>Active</x-badge>
                                @else
                                    <x-badge color="slate">No login</x-badge>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('employees.show', $employee) }}" title="Open"><x-icon.eye class="size-4" /></a>
                                    @can('update', $employee)
                                        <a href="{{ route('employees.edit', $employee) }}" title="Edit"><x-icon.pencil class="size-4" /></a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="wrap">
                                <x-empty title="No employees found" message="Adjust the filters, or add your first employee.">
                                    <x-slot:action>
                                        @can('create', App\Models\Employee::class)
                                            <x-button href="{{ route('employees.create') }}">Add employee</x-button>
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

    <div class="mt-4">{{ $employees->links() }}</div>
</x-app-layout>
