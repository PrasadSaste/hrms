<x-app-layout title="My tax declaration">
    <x-page-header title="Income tax"
        :subtitle="'What you plan to invest this year, and what it means for the tax taken from your salary · ' . App\Support\FinancialYear::label($year)">
        <x-slot:actions>
            <x-badge :color="$declaration->statusColor()" dot class="text-sm">{{ $declaration->statusLabel() }}</x-badge>

            <form method="GET" action="{{ route('tax.mine') }}" class="inline">
                <x-select name="year" :selected="$year" :options="$years" data-auto-submit class="w-40" />
            </form>

            <x-button variant="secondary"
                href="{{ route('tax.statement', ['employee' => $employee, 'year' => $year]) }}">
                <x-icon.download class="size-4" /> My statement
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($declaration->status === App\Models\TaxDeclaration::RETURNED && $declaration->remarks)
        <x-alert type="warning" class="mb-6" :dismissible="false">
            <span class="font-medium">Sent back to you:</span> {{ $declaration->remarks }}
        </x-alert>
    @endif

    <form method="POST" action="{{ route('tax.update', $declaration) }}">
        @csrf @method('PUT')

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">

                <x-card title="Which regime"
                    subtitle="The new one has lower rates and almost no deductions; the old one keeps the deductions. Both are worked out below — this is your choice, not ours.">
                    <div class="space-y-4">
                        <x-select label="Tax regime" name="regime" :selected="old('regime', $declaration->regime)"
                            :options="$regimes" :disabled="! $editable" />

                        <x-checkbox label="I rent in Delhi, Mumbai, Kolkata or Chennai" name="metro"
                            :checked="old('metro', $declaration->metro)" :disabled="! $editable"
                            help="Half of salary rather than two fifths counts towards the house rent exemption in those four cities." />
                    </div>
                </x-card>

                @foreach ($groups as $groupKey => $groupLabel)
                    @php $inGroup = App\Support\TaxDeductionSections::inGroup($groupKey); @endphp

                    @if (count($inGroup))
                        <x-card :title="$groupLabel">
                            <div class="space-y-5">
                                @foreach ($inGroup as $key => $section)
                                    @php
                                        $item = $declaration->items->firstWhere('section', $key);
                                        $counts = in_array($declaration->regime, $section['regimes'], true);
                                    @endphp

                                    <div @class(['border-b border-slate-100 pb-5 last:border-0 last:pb-0'])>
                                        <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                                            <div>
                                                <p @class(['text-sm font-medium', 'text-slate-900' => $counts, 'text-slate-400' => ! $counts])>
                                                    {{ $section['label'] }}
                                                    @unless ($counts)
                                                        <x-badge color="slate" class="ml-1.5">Not under the {{ strtolower($declaration->regimeLabel()) }}</x-badge>
                                                    @endunless
                                                </p>
                                                <p class="mt-0.5 text-xs text-slate-500">{{ $section['description'] }}</p>
                                                <p class="mt-1 text-xs text-slate-400">Proof: {{ $section['proof'] }}</p>

                                                @if ($item?->proof_path)
                                                    <a href="{{ route('tax.proof', $item) }}"
                                                        class="mt-1.5 inline-flex items-center gap-1 text-xs link font-medium">
                                                        <x-icon.document class="size-3.5" /> Proof attached
                                                    </a>
                                                @endif

                                                @if ($item?->isVerified())
                                                    <p class="mt-1.5 text-xs font-medium text-emerald-700">
                                                        HR accepted {{ App\Support\Money::withSymbol($item->verified_amount) }}.
                                                    </p>
                                                @endif
                                            </div>

                                            <div>
                                                <x-input :label="$key === 'hra' ? 'Rent paid this year' : 'Amount'"
                                                    :name="'amounts[' . $key . ']'" type="number" step="1" min="0"
                                                    :value="old('amounts.' . $key, $item?->declared_amount > 0 ? (int) $item->declared_amount : null)"
                                                    :disabled="! $editable"
                                                    :help="$section['ceiling'] ? 'Up to ' . App\Support\Money::withSymbol($section['ceiling'], null, 0) : null" />
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-card>
                    @endif
                @endforeach

                @if ($editable)
                    <x-form-actions>
                        <x-button name="submit" value="0">Save for now</x-button>
                        <x-button name="submit" value="1" variant="success"
                            data-confirm="Hand this in? You will not be able to change it until HR sends it back.">
                            <x-icon.check class="size-4" /> Save and hand in
                        </x-button>
                    </x-form-actions>
                @else
                    <x-alert type="info" :dismissible="false">
                        This declaration is with HR and cannot be changed until they send it back.
                    </x-alert>
                @endif
            </div>

            <div class="space-y-6">
                <x-card title="Which regime costs you less"
                    :subtitle="'On what you have declared, ' . App\Support\TaxRegimes::label($comparison['cheaper']) . ' is cheaper by ' . App\Support\Money::withSymbol($comparison['saving'])">
                    <div class="space-y-3">
                        @foreach ($comparison['regimes'] as $key => $result)
                            <div @class([
                                'rounded-lg border px-4 py-3',
                                'border-emerald-200 bg-emerald-50/50' => $key === $comparison['cheaper'],
                                'border-slate-200' => $key !== $comparison['cheaper'],
                            ])>
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="text-sm font-medium text-slate-900">{{ $result['regime_label'] }}</span>
                                    <span class="text-sm font-semibold text-slate-900"><x-money :amount="$result['tax']['total']" /></span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    on <x-money :amount="$result['taxable_income']" /> taxable
                                    @if ($key === $declaration->regime) · your choice @endif
                                </p>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
                        Worked out on what is declared today. It moves as you declare more, and again
                        when HR checks your proofs.
                    </p>
                </x-card>

                <x-card title="How this year's tax is worked out">
                    @include('tax._computation', ['computation' => $comparison['regimes'][$declaration->regime]])
                </x-card>
            </div>
        </div>
    </form>

    @if ($editable)
        {{-- Outside the declaration form: a form cannot be nested in another. --}}
        <x-card title="Attach a proof" class="mt-6"
            subtitle="A certificate, receipt or statement for one of the sections above. Stored privately and only ever streamed to you and HR.">
            <form method="POST" action="{{ route('tax.proof.upload', $declaration) }}" enctype="multipart/form-data"
                class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                @csrf
                <x-select label="For which section" name="section"
                    :options="collect($sections)->map(fn ($s) => $s['label'])->all()" />
                <x-input label="File" name="proof" type="file" accept=".pdf,.png,.jpg,.jpeg,.webp"
                    help="PDF or an image, up to 4 MB." />
                <x-button variant="secondary"><x-icon.upload class="size-4" /> Attach</x-button>
            </form>
        </x-card>
    @endif
</x-app-layout>
