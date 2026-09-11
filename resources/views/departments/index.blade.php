<x-app-layout title="Departments">
    <x-page-header title="Departments" subtitle="Functional teams within each branch">
        <x-slot:actions>
            @can('create', App\Models\Department::class)
                <x-button href="{{ route('departments.create') }}"><x-icon.plus class="size-4" /> New department</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('departments.index')">
        <x-input label="Search" name="search" :value="request('search')" placeholder="Department name" class="sm:w-64" />
        <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-56" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
        @if (request()->hasAny(['search', 'branch_id']))
            <x-button href="{{ route('departments.index') }}" variant="ghost">Clear</x-button>
        @endif
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Branch</th>
                        <th>Head</th>
                        <th class="num">Employees</th>
                        <th class="num">Designations</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td>
                                <a href="{{ route('departments.show', $department) }}" class="font-medium text-slate-900 hover-ink">
                                    {{ $department->name }}
                                </a>
                                <div class="font-mono text-xs text-slate-500">{{ $department->code }}</div>
                            </td>
                            <td>{{ $department->branch?->name ?? 'Organisation wide' }}</td>
                            <td>{{ $department->head?->full_name ?? '-' }}</td>
                            <td class="num font-medium tabular-nums">{{ $department->employees_count }}</td>
                            <td class="num tabular-nums">{{ $department->designations_count }}</td>
                            <td>
                                <x-badge :color="$department->status === 'active' ? 'emerald' : 'slate'" dot>
                                    {{ ucfirst($department->status) }}
                                </x-badge>
                            </td>
                            <td class="num whitespace-nowrap">
                                @can('update', $department)
                                    <x-button href="{{ route('departments.edit', $department) }}" variant="ghost" size="sm"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                                @endcan
                                @can('delete', $department)
                                    <form method="POST" action="{{ route('departments.destroy', $department) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <x-button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50"
                                            data-confirm="Remove {{ $department->name }}?"><x-icon.trash class="size-3.5" /> Delete</x-button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty title="No departments found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $departments->links() }}</div>
</x-app-layout>
