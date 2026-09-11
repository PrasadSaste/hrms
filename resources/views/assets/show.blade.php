<x-app-layout :title="$asset->name">
    <x-page-header :title="$asset->name" :subtitle="$asset->typeLabel().' · tag '.$asset->asset_tag" :back="route('assets.index')">
        <x-slot:actions>
            @if ($canAssign)
                @if ($current)
                    <x-button type="button" data-dialog-open="return-asset">
                        <x-icon.check class="size-4" /> Take it back
                    </x-button>
                @elseif ($asset->isAvailable())
                    <x-button type="button" data-dialog-open="issue-asset">
                        <x-icon.user class="size-4" /> Issue to somebody
                    </x-button>
                @endif
            @endif
            @if ($canManage)
                <x-button :href="route('assets.edit', $asset)" variant="secondary">
                    <x-icon.pencil class="size-4" /> Edit
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            @if ($current)
                <x-card class="border-sky-200 bg-sky-50/60 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs tracking-wide text-sky-700 uppercase">Currently with</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $current->employee->full_name }}</p>
                            <p class="text-sm text-slate-600">
                                Since {{ $current->issued_on->format('d M Y') }}
                                ({{ $current->heldDays() }} days), handed over in
                                {{ strtolower(\App\Models\Asset::CONDITIONS[$current->condition_out] ?? $current->condition_out) }} condition.
                            </p>
                            @if ($current->issue_remarks)
                                <p class="mt-1 text-sm text-slate-500">{{ $current->issue_remarks }}</p>
                            @endif
                        </div>
                        <x-badge color="sky">Issued</x-badge>
                    </div>
                </x-card>
            @endif

            <x-card class="p-5">
                <h3 class="mb-4 text-sm font-semibold tracking-wide text-slate-500 uppercase">Details</h3>

                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-500">Kind</dt><dd class="text-slate-900">{{ $asset->typeLabel() }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Status</dt>
                        <dd><x-badge :color="$asset->statusColour()">{{ $asset->statusLabel() }}</x-badge></dd></div>
                    <div><dt class="text-xs text-slate-500">Condition</dt><dd class="text-slate-900">{{ $asset->conditionLabel() }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Make and model</dt>
                        <dd class="text-slate-900">{{ trim($asset->make.' '.$asset->model) ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Serial number</dt><dd class="text-slate-900">{{ $asset->serial_number ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Branch</dt><dd class="text-slate-900">{{ $asset->branch?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Company</dt><dd class="text-slate-900">{{ $asset->company?->name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Bought on</dt><dd class="text-slate-900">{{ $asset->purchased_on?->format('d M Y') ?? '—' }}</dd></div>
                    @if ($asset->purchase_cost)
                        <div><dt class="text-xs text-slate-500">Cost</dt><dd class="text-slate-900"><x-money :amount="$asset->purchase_cost" /></dd></div>
                    @endif
                    @if ($asset->warranty_expires_on)
                        <div><dt class="text-xs text-slate-500">Warranty until</dt>
                            <dd @class(['text-slate-900', 'text-rose-600' => $asset->warranty_expires_on->isPast()])>
                                {{ $asset->warranty_expires_on->format('d M Y') }}
                            </dd></div>
                    @endif

                    @foreach ($asset->fields() as $field => $definition)
                        @if ($value = $asset->detail($field))
                            <div><dt class="text-xs text-slate-500">{{ $definition['label'] }}</dt>
                                <dd class="text-slate-900">{{ $value }}</dd></div>
                        @endif
                    @endforeach
                </dl>

                @if ($asset->notes)
                    <p class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-600">{{ $asset->notes }}</p>
                @endif
            </x-card>

            <x-card>
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold tracking-wide text-slate-500 uppercase">Who has had it</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Every period, kept whole — which is what settles an argument about when something was damaged.
                    </p>
                </div>

                @if ($asset->assignments->isEmpty())
                    <x-empty title="Never issued" message="This has not been given to anybody yet." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($asset->assignments as $assignment)
                            <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $assignment->employee?->full_name ?? 'Somebody since removed' }}</p>
                                    <p class="text-sm text-slate-600">
                                        {{ $assignment->issued_on->format('d M Y') }}
                                        &rarr;
                                        {{ $assignment->returned_on?->format('d M Y') ?? 'still out' }}
                                        <span class="text-slate-400">({{ $assignment->heldDays() }} days)</span>
                                    </p>
                                    @if ($assignment->issue_remarks || $assignment->return_remarks)
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $assignment->return_remarks ?: $assignment->issue_remarks }}
                                        </p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    @if ($assignment->isOpen())
                                        <x-badge color="sky">Out</x-badge>
                                    @elseif ($assignment->deteriorated())
                                        <x-badge color="amber">Came back worse</x-badge>
                                    @else
                                        <x-badge color="slate">Returned</x-badge>
                                    @endif
                                    <span class="text-xs text-slate-500">
                                        {{ \App\Models\Asset::CONDITIONS[$assignment->condition_out] ?? $assignment->condition_out }}
                                        @if ($assignment->condition_in)
                                            &rarr; {{ \App\Models\Asset::CONDITIONS[$assignment->condition_in] ?? $assignment->condition_in }}
                                        @endif
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-5">
            @if ($canManage && ! $asset->isIssued())
                <x-card class="p-5">
                    <h3 class="mb-2 text-sm font-semibold tracking-wide text-slate-500 uppercase">Retire it</h3>
                    <p class="mb-4 text-sm text-slate-600">
                        Takes it off the register. Who held it and when is kept.
                    </p>
                    <form method="POST" action="{{ route('assets.destroy', $asset) }}">
                        @csrf @method('DELETE')
                        <x-button variant="danger" class="w-full"
                            data-confirm="Remove {{ $asset->name }} from the register?"
                            data-confirm-label="Remove it">
                            <x-icon.trash class="size-4" /> Remove
                        </x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>

    {{-- Issue --}}
    @if ($canAssign && ! $current && $asset->isAvailable())
        <x-dialog name="issue-asset" title="Issue this asset"
            description="The person becomes responsible for it from the day it is handed over.">
            <form method="POST" action="{{ route('assets.issue', $asset) }}" class="space-y-4">
                @csrf
                <x-select label="Issue to" name="employee_id" required placeholder="Choose somebody"
                    :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->full_name.' ('.$e->employee_code.')'])->all()" />
                <x-input label="Handed over on" name="issued_on" type="date" :value="now()->toDateString()" required />
                <x-select label="Condition it is in" name="condition_out" required
                    :selected="$asset->condition" :options="$conditions" />
                <x-textarea label="Anything worth noting" name="issue_remarks" rows="2"
                    help="Scratches, a missing charger — anything that would otherwise be argued about later." />
                <x-button class="w-full">Issue it</x-button>
            </form>
        </x-dialog>
    @endif

    {{-- Return --}}
    @if ($canAssign && $current)
        <x-dialog name="return-asset" title="Take it back"
            :description="'Handing back from '.$current->employee->full_name.'.'">
            <form method="POST" action="{{ route('assets.return', $asset) }}" class="space-y-4">
                @csrf
                <x-input label="Returned on" name="returned_on" type="date" :value="now()->toDateString()" required />
                <x-select label="Condition it came back in" name="condition_in" required
                    :selected="$current->condition_out" :options="$conditions"
                    help="Recorded against them, and copied onto the asset for whoever gets it next." />
                <x-select label="Where it goes now" name="status"
                    :selected="\App\Models\Asset::IN_STOCK"
                    :options="collect($statuses)->only([
                        \App\Models\Asset::IN_STOCK, \App\Models\Asset::IN_REPAIR,
                        \App\Models\Asset::LOST, \App\Models\Asset::RETIRED,
                    ])->all()" />
                <x-textarea label="Anything worth noting" name="return_remarks" rows="2" />
                <x-button class="w-full">Take it back</x-button>
            </form>
        </x-dialog>
    @endif
</x-app-layout>
