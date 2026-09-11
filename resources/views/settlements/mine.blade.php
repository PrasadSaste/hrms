<x-app-layout title="My settlement">
    <x-page-header title="Your full and final settlement"
        :subtitle="$settlement->reference.' · last working day '.$settlement->last_working_day?->format('d M Y')">
        <x-slot:actions>
            <x-button :href="route('settlements.download', $settlement)">
                <x-icon.download class="size-4" /> Download the statement
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            @foreach ([['What you are owed', $settlement->earnings], ['What is being recovered', $settlement->deductions]] as [$heading, $lines])
                @if ($lines->isNotEmpty())
                    <x-card :title="$heading">
                        <ul class="divide-y divide-slate-100">
                            @foreach ($lines as $line)
                                <li class="flex items-start justify-between gap-4 py-3 first:pt-0">
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $line->label }}</p>
                                        @if ($line->basis)
                                            <p class="text-xs text-slate-500">{{ $line->basis }}</p>
                                        @endif
                                    </div>
                                    <span class="shrink-0 font-medium tabular-nums">
                                        <x-money :amount="$line->amount" :currency="$settlement->currency" />
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif
            @endforeach
        </div>

        <x-card>
            <div class="text-center">
                <p class="text-xs tracking-wide text-slate-500 uppercase">{{ $settlement->netLabel() }}</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-slate-900">
                    <x-money :amount="abs($settlement->net_payable)" :currency="$settlement->currency" />
                </p>
                <p class="mt-1 text-xs text-slate-500 italic">{{ $settlement->netInWords() }}</p>
                <div class="mt-3"><x-badge :color="$settlement->statusColour()">{{ $settlement->statusLabel() }}</x-badge></div>
            </div>

            <p class="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500">
                Every line says how it was worked out. If any of it looks wrong, raise it with Human Resources
                quoting {{ $settlement->reference }} — it is far easier to correct before the payment than after.
            </p>
        </x-card>
    </div>
</x-app-layout>
