<x-app-layout title="Tax computation">
    <x-page-header :title="$employee->full_name . ' — tax computation'"
        :subtitle="App\Support\FinancialYear::label($year) . ' · ' . App\Support\TaxRegimes::label($regime) . ' · ' . $employee->employee_code">
        <x-slot:actions>
            <form method="GET" action="{{ route('tax.computation', $employee) }}" class="inline-flex gap-2">
                <x-select name="year" :selected="$year" :options="$years" data-auto-submit class="w-40" />
                <x-select name="regime" :selected="$regime" :options="$regimes" data-auto-submit class="w-40" />
            </form>

            <x-button variant="secondary"
                href="{{ route('tax.statement', ['employee' => $employee, 'year' => $year]) }}">
                <x-icon.download class="size-4" /> Statement
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="How the year's tax was arrived at"
                subtitle="Every line of the working, in the order it is applied. Nothing here is rounded until the end.">
                @include('tax._computation', ['computation' => $computation])
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="The other regime"
                :subtitle="'On the same figures, ' . App\Support\TaxRegimes::label($comparison['cheaper']) . ' costs less'">
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
                                <x-money :amount="$result['monthly']" /> a month
                            </p>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Where this comes from">
                <ul class="space-y-2.5 text-sm text-slate-600">
                    <li>Months already paid are read from the salary slips themselves, so a month with loss of pay in it projects honestly.</li>
                    <li>Months ahead are projected from the salary structure in force today.</li>
                    <li>Deductions are what HR has verified, or what was declared where nobody has ruled yet.</li>
                    @if ($declaration)
                        <li>
                            <a href="{{ route('tax.show', $declaration) }}" class="link font-medium">
                                Open the declaration
                            </a>
                        </li>
                    @else
                        <li class="text-amber-700">No declaration for this year, so no deductions are counted.</li>
                    @endif
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
