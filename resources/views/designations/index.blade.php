<x-app-layout title="Designations">
    <x-page-header title="Designations" subtitle="Job titles and their seniority levels">
        <x-slot:actions>
            @can('create', App\Models\Designation::class)
                <x-button href="{{ route('designations.create') }}"><x-icon.plus class="size-4" /> New designation</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('designations.index')">
        <x-input label="Search" name="search" :value="request('search')" placeholder="Title" class="sm:w-64" />
        <x-select label="Department" name="department_id" :selected="request('department_id')" placeholder="All departments"
            :options="$departments->pluck('name', 'id')->all()" class="sm:w-56" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
        @if (request()->hasAny(['search', 'department_id']))
            <x-button href="{{ route('designations.index') }}" variant="ghost">Clear</x-button>
        @endif
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Designation</th><th>Department</th><th class="num">Level</th>
                        <th class="num">Employees</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($designations as $designation)
                        <tr>
                            <td>
                                <div class="font-medium text-slate-900">{{ $designation->name }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $designation->code }}</div>
                            </td>
                            <td>{{ $designation->department?->name ?? 'Cross functional' }}</td>
                            <td class="num tabular-nums">{{ $designation->level }}</td>
                            <td class="num font-medium tabular-nums">{{ $designation->employees_count }}</td>
                            <td>
                                <x-badge :color="$designation->status === 'active' ? 'emerald' : 'slate'" dot>
                                    {{ ucfirst($designation->status) }}
                                </x-badge>
                            </td>
                            <td class="num whitespace-nowrap">
                                @can('update', $designation)
                                    <x-button href="{{ route('designations.edit', $designation) }}" variant="ghost" size="sm"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                                @endcan
                                @can('delete', $designation)
                                    <form method="POST" action="{{ route('designations.destroy', $designation) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <x-button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50"
                                            data-confirm="Remove {{ $designation->name }}?"><x-icon.trash class="size-3.5" /> Delete</x-button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="No designations found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $designations->links() }}</div>
</x-app-layout>
