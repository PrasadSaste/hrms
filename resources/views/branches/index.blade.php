<x-app-layout title="Branches">
    <x-page-header title="Branches" subtitle="Offices and locations across the organisation">
        <x-slot:actions>
            @can('create', App\Models\Branch::class)
                <x-button href="{{ route('branches.create') }}"><x-icon.plus class="size-4" /> New branch</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('branches.index')">
        <x-input label="Search" name="search" :value="request('search')" placeholder="Name, code or city" class="sm:w-64" />
        <x-select label="Status" name="status" :selected="request('status')" placeholder="All statuses"
            :options="['active' => 'Active', 'inactive' => 'Inactive']" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
        @if (request()->hasAny(['search', 'status']))
            <x-button href="{{ route('branches.index') }}" variant="ghost">Clear</x-button>
        @endif
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Location</th>
                        <th>Manager</th>
                        <th>Working hours</th>
                        <th class="num">Employees</th>
                        <th class="num">Departments</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>
                                <a href="{{ route('branches.show', $branch) }}" class="font-medium text-slate-900 hover-ink">
                                    {{ $branch->name }}
                                </a>
                                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                    <span class="font-mono">{{ $branch->code }}</span>
                                    @if ($branch->is_head_office)
                                        <x-badge color="brand">Head office</x-badge>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="text-slate-700">{{ $branch->city ?: '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $branch->state }}</div>
                            </td>
                            <td>{{ $branch->manager?->full_name ?? '-' }}</td>
                            <td class="text-xs whitespace-nowrap text-slate-500">
                                {{ \Illuminate\Support\Carbon::parse($branch->work_start_time)->format('h:i A') }}
                                &ndash;
                                {{ \Illuminate\Support\Carbon::parse($branch->work_end_time)->format('h:i A') }}
                            </td>
                            <td class="num font-medium tabular-nums">{{ $branch->employees_count }}</td>
                            <td class="num tabular-nums">{{ $branch->departments_count }}</td>
                            <td>
                                <x-badge :color="$branch->status === 'active' ? 'emerald' : 'slate'" dot>
                                    {{ ucfirst($branch->status) }}
                                </x-badge>
                            </td>
                            <td class="num whitespace-nowrap">
                                @can('update', $branch)
                                    <x-button href="{{ route('branches.edit', $branch) }}" variant="ghost" size="sm"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                                @endcan
                                @can('delete', $branch)
                                    <form method="POST" action="{{ route('branches.destroy', $branch) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <x-button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50"
                                            data-confirm="Remove {{ $branch->name }}? This cannot be undone."><x-icon.trash class="size-3.5" /> Delete</x-button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty title="No branches yet" message="Add your first office to start assigning employees.">
                                    <x-slot:action>
                                        @can('create', App\Models\Branch::class)
                                            <x-button href="{{ route('branches.create') }}">Add branch</x-button>
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

    <div class="mt-4">{{ $branches->links() }}</div>
</x-app-layout>
