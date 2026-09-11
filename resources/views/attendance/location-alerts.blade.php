<x-app-layout title="Punches away from the branch">
    <x-page-header title="Punches away from the branch" :subtitle="$date->format('l, d F Y')">
        <x-slot:actions>
            <x-button href="{{ route('attendance.daily', ['date' => $date->toDateString()]) }}" variant="secondary">
                <x-icon.list class="size-4" /> Daily roster
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @unless ($enabled)
        <x-alert type="warning" class="mb-6">
            Location checking is switched off under Settings, Attendance. Nothing is being
            flagged and no report is being sent.
        </x-alert>
    @endunless

    <x-filter-bar :action="route('attendance.location-alerts')">
        <x-input label="Date" name="date" type="date" :value="$date->toDateString()" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Show</x-button>
        <x-button href="{{ route('attendance.location-alerts') }}" variant="ghost">Today</x-button>
    </x-filter-bar>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card :padded="false">
                <div class="table-wrap is-scrollable">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th><th>Branch</th><th>Punch</th>
                                <th class="num">Distance</th><th class="num">Allowed</th><th>Where</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <x-avatar :name="$row['employee']->full_name" :src="$row['employee']->photoUrl()" size="sm" />
                                            <div class="min-w-0">
                                                <a href="{{ route('attendance.employee', $row['employee']) }}"
                                                    class="font-medium text-slate-900 hover-ink">
                                                    {{ $row['employee']->full_name }}
                                                </a>
                                                <div class="font-mono text-xs text-slate-500">{{ $row['employee']->employee_code }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $row['branch']?->name ?? '-' }}</div>
                                        <div class="text-xs text-slate-500">{{ $row['employee']->department?->name }}</div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <x-badge :color="$row['punch'] === 'check_out' ? 'slate' : 'sky'">{{ $row['punch_label'] }}</x-badge>
                                        <div class="mt-0.5 text-xs tabular-nums text-slate-500">
                                            {{ $row['at']?->format('h:i A') ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="num font-medium tabular-nums text-rose-600">{{ $row['distance'] }}</td>
                                    <td class="num tabular-nums text-slate-500">
                                        {{ \App\Support\Geo::describeDistance($row['allowed_radius']) }}
                                    </td>
                                    <td>
                                        @if ($row['map_url'])
                                            <a href="{{ $row['map_url'] }}" target="_blank" rel="noopener"
                                                class="text-xs link font-medium">
                                                {{ $row['location'] }}
                                            </a>
                                            @if ($row['accuracy'])
                                                <div class="text-xs text-slate-500">&plusmn;{{ $row['accuracy'] }} m reported accuracy</div>
                                            @endif
                                        @else
                                            <span class="text-xs text-slate-400">Not recorded</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <x-empty title="Every punch was within range"
                                            message="Nobody checked in or out further from their branch than it allows on this day." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="How this is measured">
                <p class="text-sm text-slate-600">
                    Each branch carries its own coordinates. When somebody punches, the distance
                    between the branch and the position their device reported is worked out and
                    kept beside the punch. Anything further than the branch's radius — currently
                    {{ number_format($defaultRadius) }} m unless the branch sets its own — is listed here.
                </p>
                <p class="mt-3 text-sm text-slate-600">
                    A punch is never refused for being too far away. A client visit, a site
                    inspection or a phone with a poor fix indoors all look the same from here,
                    so this is a list to read rather than a verdict.
                </p>
            </x-card>

            <x-card title="Who is told">
                @if ($recipients->isEmpty())
                    <p class="text-sm text-slate-600">Nobody. Set the recipients under Settings, Attendance.</p>
                @else
                    <ul class="space-y-1 text-sm text-slate-700">
                        @foreach ($recipients as $email)
                            <li class="truncate">{{ $email }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-slate-500">
                        They get each alert as it happens, and the whole day as a report each evening.
                    </p>
                @endif

                @can('settings.manage')
                    <div class="mt-4">
                        <x-button href="{{ route('settings.edit') }}#attendance" variant="secondary" size="sm">
                            Change the recipients
                        </x-button>
                    </div>
                @endcan
            </x-card>

            @if ($unplacedBranches->isNotEmpty())
                <x-card title="Branches not on the map">
                    <p class="text-sm text-slate-600">
                        Punches at {{ $unplacedBranches->count() === 1 ? 'this branch are' : 'these branches are' }}
                        never checked, because nobody has set where {{ $unplacedBranches->count() === 1 ? 'it is' : 'they are' }}.
                    </p>
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($unplacedBranches as $branch)
                            <li>
                                @can('update', $branch)
                                    <a href="{{ route('branches.edit', $branch) }}" class="link font-medium">
                                        {{ $branch->name }}
                                    </a>
                                @else
                                    {{ $branch->name }}
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
