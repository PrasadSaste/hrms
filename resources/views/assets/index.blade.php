<x-app-layout title="Assets">
    <x-page-header title="Asset register" subtitle="What the company owns, and who is holding it">
        <x-slot:actions>
            @if ($canManage)
                <x-button :href="route('assets.create')"><x-icon.plus class="size-4" /> Add asset</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="On the register" :value="$counts['all']" color="brand" :href="route('assets.index')" />
        <x-stat label="In stock" :value="$counts['in_stock']" color="emerald"
            :href="route('assets.index', ['status' => 'in_stock'])" sublabel="Free to issue" />
        <x-stat label="Issued" :value="$counts['issued']" color="sky"
            :href="route('assets.index', ['status' => 'issued'])" sublabel="With somebody" />
        <x-stat label="In repair or lost" :value="$counts['in_repair'] + $counts['lost']" color="amber"
            :href="route('assets.index', ['status' => 'in_repair'])" />
    </div>

    <x-filter-bar :action="route('assets.index')">
        <x-input label="Search" name="q" :value="$filters['q']" placeholder="Tag, name or serial" class="sm:w-56" />
        <x-select label="Type" name="type" :selected="$filters['type']" placeholder="Any kind"
            :options="$types" class="sm:w-44" />
        <x-select label="Status" name="status" :selected="$filters['status']" placeholder="Any status"
            :options="$statuses" class="sm:w-40" />
        <x-select label="Branch" name="branch_id" :selected="$filters['branch_id']" placeholder="Anywhere"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Show</x-button>
    </x-filter-bar>

    <x-card>
        @if ($assets->isEmpty())
            <x-empty title="Nothing on the register"
                message="Add the first laptop, phone or access card and it can be issued to somebody." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 text-left text-xs tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 font-medium">Tag</th>
                            <th class="px-4 py-3 font-medium">Asset</th>
                            <th class="px-4 py-3 font-medium">Kind</th>
                            <th class="px-4 py-3 font-medium">Where</th>
                            <th class="px-4 py-3 font-medium">Held by</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($assets as $asset)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $asset->asset_tag }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('assets.show', $asset) }}" class="font-medium text-slate-900 hover-ink">
                                        {{ $asset->name }}
                                    </a>
                                    @if ($asset->identifier())
                                        <p class="text-xs text-slate-500">{{ $asset->identifier() }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $asset->typeLabel() }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $asset->branch?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($holder = $asset->currentAssignment?->employee)
                                        <span class="text-slate-900">{{ $holder->full_name }}</span>
                                        <p class="text-xs text-slate-500">
                                            since {{ $asset->currentAssignment->issued_on->format('d M Y') }}
                                        </p>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :color="$asset->statusColour()">{{ $asset->statusLabel() }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-button :href="route('assets.show', $asset)" variant="ghost" size="sm">Open</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 px-4 py-3">{{ $assets->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
