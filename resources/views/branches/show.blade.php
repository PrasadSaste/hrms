<x-app-layout :title="$branch->name">
    <x-page-header :title="$branch->name" :subtitle="$branch->code . ' · ' . ($branch->city ?: 'Location not set')"
        :back="route('branches.index')">
        <x-slot:actions>
            @can('update', $branch)
                <x-button href="{{ route('branches.edit', $branch) }}" variant="secondary">Edit branch</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Employees" :value="$employeeCount" color="brand">
            <x-slot:icon><x-icon.users class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Departments" :value="$branch->departments->count()" color="sky">
            <x-slot:icon><x-icon.squares class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Manager" :value="$branch->manager?->first_name ?? '-'" color="violet"
            :sublabel="$branch->manager?->designation?->name">
            <x-slot:icon><x-icon.badge class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Status" :value="ucfirst($branch->status)" :color="$branch->status === 'active' ? 'emerald' : 'slate'">
            <x-slot:icon><x-icon.check class="size-5" /></x-slot:icon>
        </x-stat>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Departments">
                @forelse ($branch->departments as $department)
                    <div class="flex items-center justify-between border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0">
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $department->name }}</p>
                            <p class="text-xs text-slate-500">{{ $department->code }}</p>
                        </div>
                        <span class="text-sm text-slate-600">{{ $department->employees_count }} employee(s)</span>
                    </div>
                @empty
                    <x-empty title="No departments" message="Create departments to organise this branch." />
                @endforelse
            </x-card>

            <x-card title="Recent joiners">
                @forelse ($recentEmployees as $employee)
                    <div class="flex items-center gap-3 border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0">
                        <x-avatar :name="$employee->full_name" :src="$employee->photoUrl()" size="sm" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('employees.show', $employee) }}" class="truncate text-sm font-medium text-slate-900 hover-ink">
                                {{ $employee->full_name }}
                            </a>
                            <p class="truncate text-xs text-slate-500">{{ $employee->designation?->name ?? 'Not assigned' }}</p>
                        </div>
                        <span class="text-xs text-slate-500">{{ $employee->date_of_joining->format('d M Y') }}</span>
                    </div>
                @empty
                    <x-empty title="No employees yet" />
                @endforelse
            </x-card>
        </div>

        <x-card title="Branch information">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Address</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $branch->fullAddress() ?: 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Email</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $branch->email ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Phone</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $branch->phone ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Working hours</dt>
                    <dd class="mt-0.5 text-slate-800">
                        {{ \Illuminate\Support\Carbon::parse($branch->work_start_time)->format('h:i A') }}
                        to
                        {{ \Illuminate\Support\Carbon::parse($branch->work_end_time)->format('h:i A') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Working days</dt>
                    <dd class="mt-0.5 text-slate-800">
                        @php $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun']; @endphp
                        {{ collect($branch->workingDays())->map(fn ($d) => $names[$d] ?? $d)->implode(', ') }}
                        <span class="block text-xs text-slate-500">{{ $branch->saturdayLabel() }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Time zone</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $branch->timezone }}</dd>
                </div>
                <div>
                    <dt class="text-xs tracking-wide text-slate-500 uppercase">Location</dt>
                    <dd class="mt-0.5 text-slate-800">
                        @if ($branch->hasCoordinates())
                            <a href="{{ $branch->mapUrl() }}" target="_blank" rel="noopener"
                                class="link font-medium">
                                {{ number_format($branch->latitude, 5) }}, {{ number_format($branch->longitude, 5) }}
                            </a>
                            <span class="block text-xs text-slate-500">
                                Punches are flagged beyond {{ number_format($branch->geofenceRadius()) }} m
                                @unless ($branch->geofence_radius_metres) (the organisation-wide default) @endunless
                            </span>
                        @else
                            <span class="text-slate-500">Not set — punches from this branch are never flagged</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-card>
    </div>
</x-app-layout>
