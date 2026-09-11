<x-app-layout title="Prepare a settlement">
    <x-page-header title="Prepare a settlement"
        subtitle="Check the figures before anything is written down — nothing is saved until you say so"
        :back="route('settlements.index')" />

    <form method="GET" action="{{ route('settlements.create') }}" class="mb-5">
        <x-card class="p-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-select label="Who is leaving" name="employee_id" :selected="$employee?->id"
                    placeholder="Choose somebody" data-auto-submit
                    :options="$employees->mapWithKeys(fn ($e) => [
                        $e->id => $e->full_name.' ('.$e->employee_code.')'
                            .($e->date_of_exit ? ' — left '.$e->date_of_exit->format('d M Y') : ''),
                    ])->all()" />
                <x-input label="Last working day" name="last_working_day" type="date"
                    :value="$lastDay?->toDateString()" />
                <div class="flex items-end">
                    <x-button variant="secondary"><x-icon.search class="size-4" /> Work it out</x-button>
                </div>
            </div>
        </x-card>
    </form>

    @if ($preview)
        {{-- One form around the whole result, so its own action bar submits it
             rather than a button reaching for it with JavaScript. --}}
        <form method="POST" action="{{ route('settlements.store') }}">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
            <input type="hidden" name="last_working_day" value="{{ $lastDay->toDateString() }}">

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <x-card title="What this comes to" subtitle="Everything the record already decides. You can add the rest afterwards.">
                    @if (empty($preview['lines']))
                        <x-empty title="Nothing to compute"
                            message="No salary structure, no encashable leave and not enough service for gratuity. You can still prepare a settlement and enter the lines by hand." />
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($preview['lines'] as $line)
                                <li class="flex items-start justify-between gap-4 py-3 first:pt-0">
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $line['label'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $line['basis'] }}</p>
                                    </div>
                                    <span @class([
                                        'shrink-0 font-medium tabular-nums',
                                        'text-rose-600' => $line['type'] === App\Support\SettlementLines::DEDUCTION,
                                    ])>
                                        {{ $line['type'] === App\Support\SettlementLines::DEDUCTION ? '−' : '' }}<x-money :amount="$line['amount']" />
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                @if ($preview['outstanding_assets']->isNotEmpty())
                    <x-alert type="warning">
                        <p class="font-semibold">
                            {{ $employee->first_name }} still holds
                            {{ $preview['outstanding_assets']->count() }}
                            {{ Str::plural('item', $preview['outstanding_assets']->count()) }} of company property.
                        </p>
                        <ul class="mt-2 list-disc space-y-0.5 pl-5 text-sm">
                            @foreach ($preview['outstanding_assets'] as $assignment)
                                <li>{{ $assignment->asset->name }}
                                    <span class="text-slate-500">(tag {{ $assignment->asset->asset_tag }})</span></li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-sm">
                            Take these back, or add a recovery line to the settlement once it is prepared.
                        </p>
                    </x-alert>
                @endif
            </div>

            <div class="space-y-5">
                <x-card title="The bases" subtitle="Frozen onto the settlement when you prepare it">
                    <dl class="space-y-3 text-sm">
                        @foreach ([
                            'Last drawn basic' => App\Support\Money::withSymbol($preview['last_drawn_basic']),
                            'Monthly salary' => App\Support\Money::withSymbol($preview['last_drawn_gross']),
                            'Per day' => App\Support\Money::withSymbol($preview['per_day']).' (over '.$preview['per_day_divisor'].' days)',
                            'Length of service' => number_format($preview['service_years'], 2).' years',
                            'Encashable days' => number_format($preview['encashable_days'], 2),
                            'Gratuity' => $preview['gratuity_eligible'] ? 'Eligible' : 'Not yet — under five years',
                        ] as $label => $value)
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">{{ $label }}</dt>
                                <dd class="text-right font-medium text-slate-900">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>

                <x-card class="p-5">
                    <x-input label="Reason for leaving" name="exit_reason"
                        :value="old('exit_reason', $employee->exit_reason)" />
                    <div class="mt-4">
                        <x-textarea label="Notes" name="notes" rows="3"
                            help="Printed on the statement.">{{ old('notes') }}</x-textarea>
                    </div>
                </x-card>
            </div>
        </div>

        <x-form-actions note="Nothing is written down until you prepare it. Every line stays editable until it is approved.">
            <x-button size="lg">Prepare this settlement</x-button>
            <x-button :href="route('settlements.index')" variant="secondary" size="lg">Cancel</x-button>
        </x-form-actions>
        </form>
    @endif
</x-app-layout>
