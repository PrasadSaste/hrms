<x-app-layout title="Salary structure">
    <x-page-header :title="$structure->employee->full_name"
        :subtitle="'Salary structure effective from ' . $structure->effective_from->format('d M Y')"
        :back="route('salary-structures.index')">
        <x-slot:actions>
            <x-button href="{{ route('salary-structures.edit', $structure) }}" variant="secondary"><x-icon.pencil class="size-3.5" /> Edit</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Annual CTC" :value="\App\Support\Money::withSymbol($structure->ctc_annual, null, 0)" color="brand">
            <x-slot:icon><x-icon.currency class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Monthly gross" :value="\App\Support\Money::withSymbol($structure->gross_monthly, null, 0)" color="sky">
            <x-slot:icon><x-icon.receipt class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Deductions" :value="\App\Support\Money::withSymbol($structure->totalDeductions(), null, 0)" color="amber">
            <x-slot:icon><x-icon.scale class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Net monthly" :value="\App\Support\Money::withSymbol($structure->netMonthly(), null, 0)" color="emerald">
            <x-slot:icon><x-icon.check class="size-5" /></x-slot:icon>
        </x-stat>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Component breakdown" :padded="false">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Component</th><th>Type</th><th>Basis</th><th class="num">Monthly amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($structure->components->sortBy(fn ($c) => $c->salaryComponent?->sequence) as $line)
                                <tr>
                                    <td>
                                        <div class="font-medium text-slate-900">{{ $line->salaryComponent?->name }}</div>
                                        <div class="font-mono text-xs text-slate-500">{{ $line->salaryComponent?->code }}</div>
                                    </td>
                                    <td>
                                        <x-badge :color="$line->salaryComponent?->isEarning() ? 'emerald' : 'rose'">
                                            {{ $line->salaryComponent?->type->label() }}
                                        </x-badge>
                                    </td>
                                    <td class="text-sm text-slate-600">
                                        @if ($line->calculation_type === 'percentage')
                                            {{ rtrim(rtrim(number_format($line->value, 2), '0'), '.') }}% of
                                            {{ App\Models\SalaryComponent::PERCENTAGE_BASES[$line->salaryComponent?->percentage_of] ?? 'basic' }}
                                        @else
                                            Fixed
                                        @endif
                                    </td>
                                    <td class="num font-medium tabular-nums">
                                        <x-money :amount="$line->computed_amount" :currency="$structure->currency" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 text-sm">
                            <tr>
                                <td colspan="3" class="px-4 py-2.5 text-right font-medium text-slate-600">Gross earnings</td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-slate-900">
                                    <x-money :amount="$structure->totalEarnings()" :currency="$structure->currency" />
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="px-4 py-2.5 text-right font-medium text-slate-600">Total deductions</td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-rose-700">
                                    &minus;<x-money :amount="$structure->totalDeductions()" :currency="$structure->currency" />
                                </td>
                            </tr>
                            <tr class="border-t border-slate-200">
                                <td colspan="3" class="px-4 py-3 text-right font-semibold text-slate-900">Net monthly pay</td>
                                <td class="px-4 py-3 text-right text-lg font-bold tabular-nums text-emerald-700">
                                    <x-money :amount="$structure->netMonthly()" :currency="$structure->currency" />
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Employee">
                <div class="flex items-center gap-3">
                    <x-avatar :name="$structure->employee->full_name" :src="$structure->employee->photoUrl()" size="lg" />
                    <div class="min-w-0">
                        <a href="{{ route('employees.show', $structure->employee) }}" class="font-medium text-slate-900 hover-ink">
                            {{ $structure->employee->full_name }}
                        </a>
                        <p class="text-sm text-slate-500">{{ $structure->employee->designation?->name ?? '-' }}</p>
                        <p class="font-mono text-xs text-slate-400">{{ $structure->employee->employee_code }}</p>
                    </div>
                </div>
            </x-card>

            <x-card title="Details">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Effective from</dt>
                        <dd class="text-right text-slate-900">{{ $structure->effective_from->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Effective to</dt>
                        <dd class="text-right text-slate-900">{{ $structure->effective_to?->format('d M Y') ?? 'Ongoing' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Monthly CTC</dt>
                        <dd class="text-right text-slate-900"><x-money :amount="$structure->monthlyCtc()" :currency="$structure->currency" /></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Payment mode</dt>
                        <dd class="text-right text-slate-900">
                            {{ App\Models\SalaryStructure::PAYMENT_MODES[$structure->payment_mode] ?? $structure->payment_mode }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Created by</dt>
                        <dd class="text-right text-slate-900">{{ $structure->creator?->name ?? 'System' }}</dd>
                    </div>
                    @if ($structure->notes)
                        <div>
                            <dt class="text-slate-500">Notes</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-slate-900">{{ $structure->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            <x-card title="Remove structure">
                <form method="POST" action="{{ route('salary-structures.destroy', $structure) }}">
                    @csrf @method('DELETE')
                    <x-button variant="danger" class="w-full"
                        data-confirm="Delete this salary structure? Existing payslips are not affected.">
                        Delete structure
                    </x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
