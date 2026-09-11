<x-app-layout title="Automations">
    <x-page-header title="Automations"
        subtitle="What the system does on its own, when it last did it, and whether it worked" />

    @unless ($schedulerSeen)
        <x-alert type="warning" class="mb-4" :dismissible="false">
            Nothing below has ever run on a schedule. That is expected on a fresh install, but on a
            live server it means <span class="font-mono text-xs">php artisan schedule:run</span> is not
            being called every minute from cron — see the deployment guide. Until it is, none of this
            happens on its own.
        </x-alert>
    @endunless

    <div class="space-y-6">
        @foreach ($grouped as $group => $automations)
            <x-card :title="$group" :padded="false">
                <div class="divide-y divide-slate-100">
                    @foreach ($automations as $key => $automation)
                        @php
                            $run = $latest[$key] ?? null;
                            $enabled = App\Support\Automations::enabled($key);
                        @endphp

                        <div class="p-4 sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-medium text-slate-900">{{ $automation['label'] }}</h3>
                                        @if (! $enabled)
                                            <x-badge color="slate">Off</x-badge>
                                        @endif
                                        @if ($run?->failed())
                                            <x-badge color="rose" dot>Last run failed</x-badge>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-slate-600">{{ $automation['summary'] }}</p>
                                    <p class="mt-1.5 text-xs text-slate-500">{{ $automation['detail'] }}</p>

                                    <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs">
                                        <div>
                                            <dt class="inline text-slate-400">Runs</dt>
                                            <dd class="inline text-slate-700">
                                                {{ App\Support\Automations::frequencyLabel($key) }}
                                                at {{ App\Support\Automations::time($key) }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="inline text-slate-400">Command</dt>
                                            <dd class="inline font-mono text-slate-600">{{ $automation['command'] }}</dd>
                                        </div>
                                        <div>
                                            <dt class="inline text-slate-400">Last run</dt>
                                            <dd class="inline text-slate-700">
                                                @if ($run)
                                                    {{ $run->started_at->diffForHumans() }}
                                                    @if ($run->trigger) by {{ $run->trigger->name }} @endif
                                                @else
                                                    never
                                                @endif
                                            </dd>
                                        </div>
                                    </dl>

                                    @if ($run?->summary)
                                        <p @class([
                                            'mt-2 rounded-md px-2.5 py-1.5 text-xs',
                                            'bg-rose-50 text-rose-700' => $run->failed(),
                                            'bg-slate-50 text-slate-600' => ! $run->failed(),
                                        ])>
                                            {{ $run->summary }}
                                            @if ($run->failed() && $run->error)
                                                <span class="mt-1 block font-mono">{{ $run->error }}</span>
                                            @endif
                                        </p>
                                    @endif
                                </div>

                                <form method="POST" action="{{ route('automations.run', $key) }}" class="shrink-0">
                                    @csrf
                                    <x-button variant="secondary" size="sm"
                                        data-confirm="Run {{ strtolower($automation['label']) }} now?">
                                        Run now
                                    </x-button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('automations.update', $key) }}"
                                class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
                                @csrf @method('PUT')

                                {{-- Written out rather than using x-input: each of these
                                     forms posts a field called "time", but every id on
                                     the page has to be its own. --}}
                                @php $id = 'automation-'.md5($key); @endphp

                                <div class="w-36">
                                    <label class="form-label" for="{{ $id }}-time">Time</label>
                                    <input type="time" name="time" id="{{ $id }}-time" class="form-input"
                                        value="{{ App\Support\Automations::time($key) }}" required>
                                </div>

                                <label class="flex items-center gap-2.5 pb-2.5">
                                    <input type="hidden" name="enabled" value="0">
                                    <input type="checkbox" name="enabled" value="1" class="form-checkbox"
                                        id="{{ $id }}-enabled" @checked($enabled)>
                                    <span class="text-sm text-slate-700">Switched on</span>
                                </label>

                                <x-button variant="secondary" size="sm" class="mb-1">Save</x-button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endforeach

        <x-card title="Recent runs" subtitle="The last twenty, newest first" :padded="false">
            <div class="table-wrap is-scrollable">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Automation</th>
                            <th class="w-44">Started</th>
                            <th class="w-24">Took</th>
                            <th class="w-28">Result</th>
                            <th>What it did</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $run)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $run->label() }}</td>
                                <td class="text-slate-600">
                                    {{ $run->started_at->format('d M Y, H:i') }}
                                    @if ($run->trigger)
                                        <span class="block text-xs text-slate-400">by {{ $run->trigger->name }}</span>
                                    @endif
                                </td>
                                <td class="text-slate-600">{{ $run->duration() ?? '—' }}</td>
                                <td>
                                    <x-badge :color="$run->succeeded() ? 'emerald' : ($run->failed() ? 'rose' : 'amber')" dot>
                                        {{ ucfirst($run->status) }}
                                    </x-badge>
                                </td>
                                <td class="text-slate-600">{{ $run->summary ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty title="Nothing has run yet"
                                        message="Each run is recorded here as it happens, whether the scheduler started it or somebody pressed Run now." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-app-layout>
