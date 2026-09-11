<x-app-layout title="Salary components">
    <x-page-header title="Salary components" subtitle="The earning and deduction lines that make up a salary structure">
        <x-slot:actions>
            <x-button href="{{ route('salary-components.create') }}"><x-icon.plus class="size-4" /> New component</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :padded="false">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="num">Seq</th><th>Component</th><th>Type</th><th>Calculation</th>
                        <th class="num">Default</th><th>Flags</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($components as $salaryComponent)
                        <tr>
                            <td class="num tabular-nums text-slate-400">{{ $salaryComponent->sequence }}</td>
                            <td>
                                <div class="font-medium text-slate-900">{{ $salaryComponent->name }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $salaryComponent->code }}</div>
                            </td>
                            <td>
                                <x-badge :color="$salaryComponent->isEarning() ? 'emerald' : 'rose'">
                                    {{ $salaryComponent->type->label() }}
                                </x-badge>
                            </td>
                            <td class="text-sm text-slate-600">
                                @if ($salaryComponent->calculation_type === 'percentage')
                                    Percentage of {{ App\Models\SalaryComponent::PERCENTAGE_BASES[$salaryComponent->percentage_of] ?? $salaryComponent->percentage_of }}
                                @else
                                    Fixed amount
                                @endif
                            </td>
                            <td class="num tabular-nums">
                                {{ $salaryComponent->calculation_type === 'percentage'
                                    ? rtrim(rtrim(number_format($salaryComponent->default_value, 2), '0'), '.') . '%'
                                    : \App\Support\Money::withSymbol($salaryComponent->default_value) }}
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @if ($salaryComponent->is_statutory)<x-badge color="violet">Statutory</x-badge>@endif
                                    @if ($salaryComponent->is_taxable)<x-badge color="amber">Taxable</x-badge>@endif
                                    @if ($salaryComponent->prorate_on_lop)<x-badge color="slate">Prorated</x-badge>@endif
                                </div>
                            </td>
                            <td><x-badge :color="$salaryComponent->status === 'active' ? 'emerald' : 'slate'" dot>{{ ucfirst($salaryComponent->status) }}</x-badge></td>
                            <td class="num whitespace-nowrap">
                                <x-button href="{{ route('salary-components.edit', $salaryComponent) }}" variant="ghost" size="sm"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                                <form method="POST" action="{{ route('salary-components.destroy', $salaryComponent) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <x-button variant="ghost" size="sm" class="text-rose-600 hover:bg-rose-50"
                                        data-confirm="Remove {{ $salaryComponent->name }}?"><x-icon.trash class="size-3.5" /> Delete</x-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="No salary components defined" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $components->links() }}</div>
</x-app-layout>
