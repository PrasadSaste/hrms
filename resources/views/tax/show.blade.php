<x-app-layout title="Tax declaration">
    <x-page-header :title="$declaration->employee?->full_name . ' — income tax'"
        :subtitle="$declaration->yearLabel() . ' · ' . $declaration->regimeLabel() . ' · ' . ($declaration->employee?->employee_code ?? '')"
        :back="route('tax.index', ['year' => $declaration->financial_year])">
        <x-slot:actions>
            <x-badge :color="$declaration->statusColor()" dot class="text-sm">{{ $declaration->statusLabel() }}</x-badge>

            <x-button variant="secondary"
                href="{{ route('tax.computation', ['employee' => $declaration->employee, 'year' => $declaration->financial_year]) }}">
                <x-icon.list class="size-4" /> Computation sheet
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="What was declared, and what you accept"
                subtitle="Leave a box empty and the declared figure stands. Type a nought to accept nothing — that is a decision, and it is recorded as one.">

                @if ($declaration->items->isEmpty())
                    <x-empty title="Nothing declared yet."
                        description="The employee has opened the screen but not entered anything." />
                @else
                    <form method="POST" action="{{ route('tax.verify', $declaration) }}" class="space-y-5">
                        @csrf

                        @foreach ($declaration->items as $item)
                            @php $section = $sections[$item->section] ?? null; @endphp
                            <div class="grid gap-4 border-b border-slate-100 pb-5 last:border-0 last:pb-0 sm:grid-cols-[1fr_10rem]">
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $item->label() }}</p>
                                    <p class="mt-0.5 text-sm text-slate-600">
                                        Declared <span class="font-medium"><x-money :amount="$item->declared_amount" /></span>
                                        @if ($section && $section['ceiling'])
                                            <span class="text-xs text-slate-500">· ceiling {{ App\Support\Money::withSymbol($section['ceiling'], null, 0) }}</span>
                                        @endif
                                    </p>
                                    @if ($section)
                                        <p class="mt-1 text-xs text-slate-500">Expect: {{ $section['proof'] }}</p>
                                    @endif

                                    @if ($item->proof_path)
                                        <a href="{{ route('tax.proof', $item) }}"
                                            class="mt-1.5 inline-flex items-center gap-1 text-xs link font-medium">
                                            <x-icon.document class="size-3.5" /> Open the proof
                                        </a>
                                    @else
                                        <p class="mt-1.5 text-xs text-amber-700">No proof attached.</p>
                                    @endif
                                </div>

                                <div>
                                    <x-input label="Accepted" :name="'verified[' . $item->section . ']'"
                                        type="number" step="1" min="0"
                                        :value="$item->verified_amount === null ? null : (int) $item->verified_amount"
                                        :disabled="! $canVerify"
                                        placeholder="Not ruled on" />
                                </div>
                            </div>
                        @endforeach

                        @if ($canVerify)
                            <x-textarea label="Remarks" name="remarks" rows="2" :value="$declaration->remarks"
                                help="Seen by the employee. Say why anything was cut down." />

                            <x-form-actions>
                                <x-button variant="success"><x-icon.check class="size-4" /> Record what I accepted</x-button>
                            </x-form-actions>
                        @endif
                    </form>
                @endif
            </x-card>

            @if ($canVerify && $declaration->items->isNotEmpty())
                <x-card title="Send it back"
                    subtitle="For a proof that is missing or does not match what was claimed. The employee can then change it and hand it in again.">
                    <form method="POST" action="{{ route('tax.send-back', $declaration) }}" class="space-y-4">
                        @csrf
                        <x-textarea label="What needs fixing" name="remarks" rows="2" required
                            placeholder="The 80C figure includes the employer's provident fund contribution, which is not yours to claim." />
                        <x-button variant="secondary">Send back to the employee</x-button>
                    </form>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Which regime costs them less"
                :subtitle="App\Support\TaxRegimes::label($comparison['cheaper']) . ' is cheaper by ' . App\Support\Money::withSymbol($comparison['saving'])">
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
                                @if ($key === $declaration->regime) their choice @else not chosen @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="On their choice">
                @include('tax._computation', ['computation' => $comparison['regimes'][$declaration->regime]])
            </x-card>

            @if ($declaration->verified_at)
                <x-card title="Verified">
                    <p class="text-sm text-slate-600">
                        By {{ $declaration->verifier?->name ?? 'somebody who has since been removed' }}
                        on {{ $declaration->verified_at->format('d M Y') }}.
                    </p>
                    @if ($declaration->remarks)
                        <p class="mt-2 text-sm text-slate-700">{{ $declaration->remarks }}</p>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
