<x-app-layout title="Settlements">
    <x-page-header title="Full and final settlements" subtitle="What people are owed, or owe, on their last day">
        <x-slot:actions>
            @if ($canManage)
                <x-button :href="route('settlements.create')"><x-icon.plus class="size-4" /> Prepare a settlement</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($awaiting->isNotEmpty() && $canManage)
        <x-alert type="warning" class="mb-5">
            <p class="font-semibold">
                {{ $awaiting->count() }} {{ Str::plural('person', $awaiting->count()) }}
                {{ $awaiting->count() === 1 ? 'has' : 'have' }} left with nothing prepared.
            </p>
            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                @foreach ($awaiting as $person)
                    <li>
                        <a class="font-medium underline"
                            href="{{ route('settlements.create', ['employee_id' => $person->id]) }}">
                            {{ $person->full_name }}
                        </a>
                        <span class="text-slate-500">left {{ $person->date_of_exit?->format('d M Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <x-filter-bar :action="route('settlements.index')">
        <x-input label="Search" name="q" :value="$filters['q']" placeholder="Name, code or reference" class="sm:w-56" />
        <x-select label="Status" name="status" :selected="$filters['status']" placeholder="Any status"
            :options="$statuses" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Show</x-button>
    </x-filter-bar>

    <x-card>
        @if ($settlements->isEmpty())
            <x-empty title="No settlements yet"
                message="When somebody leaves, prepare their full and final here." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 text-left text-xs tracking-wide text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 font-medium">Reference</th>
                            <th class="px-4 py-3 font-medium">Who</th>
                            <th class="px-4 py-3 font-medium">Last day</th>
                            <th class="px-4 py-3 text-right font-medium">Net</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($settlements as $settlement)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $settlement->reference }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('settlements.show', $settlement) }}"
                                        class="font-medium text-slate-900 hover-ink">
                                        {{ $settlement->employee?->full_name }}
                                    </a>
                                    <p class="text-xs text-slate-500">
                                        {{ $settlement->employee?->employee_code }}
                                        @if ($settlement->employee?->designation)
                                            · {{ $settlement->employee->designation->name }}
                                        @endif
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $settlement->last_working_day?->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span @class(['font-medium', 'text-rose-600' => $settlement->isRecoverable()])>
                                        <x-money :amount="abs($settlement->net_payable)" :currency="$settlement->currency" />
                                    </span>
                                    @if ($settlement->isRecoverable())
                                        <p class="text-xs text-rose-500">recoverable</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :color="$settlement->statusColour()">{{ $settlement->statusLabel() }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-button :href="route('settlements.show', $settlement)" variant="ghost" size="sm">Open</x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3">{{ $settlements->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
