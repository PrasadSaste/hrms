<x-app-layout :title="'Settlement '.$settlement->reference">
    <x-page-header :title="$settlement->employee?->full_name ?? 'Settlement'"
        :subtitle="$settlement->reference.' · last day '.$settlement->last_working_day?->format('d M Y')"
        :back="route('settlements.index')">
        <x-slot:actions>
            <x-button :href="route('settlements.download', $settlement)" variant="secondary">
                <x-icon.download class="size-4" /> Statement
            </x-button>
            @if ($canApprove && $settlement->isDraft())
                <x-button data-confirm="Approve this settlement? The figures are fixed afterwards and {{ $settlement->employee?->first_name }} is sent the statement."
                    data-confirm-label="Approve it"
                    form="approve-settlement">
                    <x-icon.check class="size-4" /> Approve
                </x-button>
            @elseif ($canApprove && $settlement->isApproved() && ! $settlement->isPaid())
                <x-button type="button" data-dialog-open="mark-paid" variant="success">
                    <x-icon.currency class="size-4" /> Mark paid
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($canApprove && $settlement->isDraft())
        <form method="POST" action="{{ route('settlements.approve', $settlement) }}" id="approve-settlement" class="hidden">@csrf</form>
    @endif

    @if ($settlement->isDraft())
        <x-alert type="info" class="mb-5">
            This is a draft. Check every line, add anything the system could not know about, and approve it
            when it is right — the figures are fixed from then on, because somebody will be holding a statement of them.
        </x-alert>
    @endif

    @if ($outstandingAssets->isNotEmpty())
        <x-alert type="warning" class="mb-5">
            <p class="font-semibold">
                {{ $settlement->employee?->first_name }} still holds
                {{ $outstandingAssets->count() }} {{ Str::plural('item', $outstandingAssets->count()) }} of company property.
            </p>
            <ul class="mt-2 list-disc space-y-0.5 pl-5 text-sm">
                @foreach ($outstandingAssets as $assignment)
                    <li>{{ $assignment->asset->name }} <span class="text-slate-500">(tag {{ $assignment->asset->asset_tag }})</span></li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            @foreach ([['Earnings', $settlement->earnings, 'emerald'], ['Deductions', $settlement->deductions, 'rose']] as [$heading, $lines, $tint])
                <x-card :title="$heading">
                    @if ($lines->isEmpty())
                        <p class="text-sm text-slate-500">Nothing under {{ strtolower($heading) }}.</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($lines as $line)
                                <li class="flex items-start justify-between gap-4 py-3 first:pt-0">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900">
                                            {{ $line->label }}
                                            @if ($line->is_computed)
                                                <x-badge color="slate" class="ml-1">worked out</x-badge>
                                            @endif
                                        </p>
                                        @if ($line->basis)
                                            <p class="text-xs text-slate-500">{{ $line->basis }}</p>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="font-medium tabular-nums text-{{ $tint }}-700">
                                            <x-money :amount="$line->amount" :currency="$settlement->currency" />
                                        </span>
                                        @if ($canManage && $settlement->isEditable())
                                            <form method="POST" action="{{ route('settlements.lines.destroy', [$settlement, $line]) }}">
                                                @csrf @method('DELETE')
                                                <x-button variant="ghost" size="sm"
                                                    data-confirm="Remove {{ $line->label }} from this settlement?">
                                                    <x-icon.trash class="size-3.5" /> Remove
                                                </x-button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endforeach

            @if ($canManage && $settlement->isEditable())
                <x-card title="Add a line" subtitle="Anything the system cannot know about — a bonus promised, a laptop not returned">
                    <form method="POST" action="{{ route('settlements.lines.store', $settlement) }}"
                        class="grid gap-4 sm:grid-cols-4">
                        @csrf
                        <x-select label="What" name="key" required placeholder="Choose"
                            :options="$earningOptions + $deductionOptions" class="sm:col-span-2" />
                        <x-input label="Amount" name="amount" type="number" step="0.01" min="0.01" required />
                        <div class="flex items-end">
                            <x-button class="w-full"><x-icon.plus class="size-4" /> Add</x-button>
                        </div>
                        <x-input label="Call it" name="label" class="sm:col-span-2"
                            help="Leave blank to use the standard wording." />
                        <x-input label="Why" name="basis" class="sm:col-span-2"
                            help="Printed under the line on the statement." />
                    </form>
                </x-card>
            @endif
        </div>

        <div class="space-y-5">
            <x-card>
                <div class="text-center">
                    <p class="text-xs tracking-wide text-slate-500 uppercase">{{ $settlement->netLabel() }}</p>
                    <p @class([
                        'mt-1 text-3xl font-semibold tracking-tight',
                        'text-slate-900' => ! $settlement->isRecoverable(),
                        'text-rose-600' => $settlement->isRecoverable(),
                    ])>
                        <x-money :amount="abs($settlement->net_payable)" :currency="$settlement->currency" />
                    </p>
                    <p class="mt-1 text-xs text-slate-500 italic">{{ $settlement->netInWords() }}</p>
                    <div class="mt-3"><x-badge :color="$settlement->statusColour()">{{ $settlement->statusLabel() }}</x-badge></div>
                </div>

                <dl class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Total earnings</dt>
                        <dd class="font-medium"><x-money :amount="$settlement->total_earnings" :currency="$settlement->currency" /></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Total deductions</dt>
                        <dd class="font-medium text-rose-600">−<x-money :amount="$settlement->total_deductions" :currency="$settlement->currency" /></dd></div>
                </dl>
            </x-card>

            <x-card title="How it was worked out">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        'Joined' => $settlement->date_of_joining?->format('d M Y'),
                        'Last working day' => $settlement->last_working_day?->format('d M Y'),
                        'Length of service' => number_format($settlement->service_years, 2).' years',
                        'Last drawn basic' => App\Support\Money::withSymbol($settlement->last_drawn_basic, $settlement->currency),
                        'Monthly salary' => App\Support\Money::withSymbol($settlement->last_drawn_gross, $settlement->currency),
                        'Per day over' => $settlement->per_day_divisor.' days',
                        'Encashable days' => number_format($settlement->encashable_days, 2),
                        'Gratuity' => $settlement->gratuity_eligible ? 'Eligible' : 'Not eligible',
                    ] as $label => $value)
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    Frozen when this settlement was prepared. Nothing above is recalculated afterwards.
                </p>
            </x-card>

            @if ($settlement->preparer || $settlement->approver)
                <x-card title="Trail">
                    <dl class="space-y-2 text-sm">
                        @if ($settlement->preparer)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Prepared by</dt>
                                <dd class="text-right">{{ $settlement->preparer->name }}</dd></div>
                        @endif
                        @if ($settlement->approver)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Approved by</dt>
                                <dd class="text-right">{{ $settlement->approver->name }}<br>
                                    <span class="text-xs text-slate-500">{{ $settlement->approved_at?->format('d M Y') }}</span></dd></div>
                        @endif
                        @if ($settlement->settled_on)
                            <div class="flex justify-between gap-3"><dt class="text-slate-500">Paid</dt>
                                <dd class="text-right">{{ $settlement->settled_on->format('d M Y') }}</dd></div>
                        @endif
                    </dl>
                </x-card>
            @endif
        </div>
    </div>

    @if ($canApprove && $settlement->isApproved() && ! $settlement->isPaid())
        <x-dialog name="mark-paid" title="Mark this settlement paid"
            description="Records the day the money actually went out, and sends a receipt.">
            <form method="POST" action="{{ route('settlements.paid', $settlement) }}" class="space-y-4">
                @csrf
                <x-input label="Paid on" name="settled_on" type="date" :value="now()->toDateString()" />
                <x-button class="w-full">Mark it paid</x-button>
            </form>
        </x-dialog>
    @endif
</x-app-layout>
