<x-app-layout :title="$department->name">
    <x-page-header :title="$department->name"
        :subtitle="$department->code . ' · ' . ($department->branch?->name ?? 'Organisation wide')"
        :back="route('departments.index')">
        <x-slot:actions>
            @can('update', $department)
                <x-button href="{{ route('departments.edit', $department) }}" variant="secondary"><x-icon.pencil class="size-3.5" /> Edit</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Employees" :subtitle="$employees->total() . ' in this department'" :padded="false">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Employee</th><th>Designation</th><th>Joined</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($employees as $employee)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <x-avatar :name="$employee->full_name" :src="$employee->photoUrl()" size="sm" />
                                            <div class="min-w-0">
                                                <a href="{{ route('employees.show', $employee) }}" class="font-medium text-slate-900 hover-ink">
                                                    {{ $employee->full_name }}
                                                </a>
                                                <div class="font-mono text-xs text-slate-500">{{ $employee->employee_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $employee->designation?->name ?? '-' }}</td>
                                    <td class="whitespace-nowrap">{{ $employee->date_of_joining->format('d M Y') }}</td>
                                    <td>
                                        <x-badge :color="$employee->employment_status->color()">
                                            {{ $employee->employment_status->label() }}
                                        </x-badge>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-empty title="No employees in this department" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
            <div class="mt-4">{{ $employees->links() }}</div>
        </div>

        <div class="space-y-6">
            <x-card title="Overview">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Head of department</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $department->head?->full_name ?? 'Not assigned' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Branch</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $department->branch?->name ?? 'Organisation wide' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Description</dt>
                        <dd class="mt-0.5 text-slate-800">{{ $department->description ?: 'Not set' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Designations">
                @forelse ($department->designations as $designation)
                    <div class="flex items-center justify-between border-b border-slate-100 py-2.5 first:pt-0 last:border-0 last:pb-0">
                        <div>
                            <p class="text-sm text-slate-800">{{ $designation->name }}</p>
                            <p class="text-xs text-slate-500">Level {{ $designation->level }}</p>
                        </div>
                        <span class="text-sm text-slate-600">{{ $designation->employees_count }}</span>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-slate-500">No designations defined.</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-app-layout>
