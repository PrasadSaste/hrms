<x-app-layout title="Income tax">
    <x-page-header title="Income tax declarations"
        :subtitle="'What everybody has declared for ' . App\Support\FinancialYear::label($year) . ', and what still needs checking'">
        <x-slot:actions>
            <form method="GET" action="{{ route('tax.index') }}" class="inline">
                <x-select name="year" :selected="$year" :options="$years" data-auto-submit class="w-40" />
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($statuses as $key => $label)
            <a href="{{ route('tax.index', ['year' => $year, 'status' => $key]) }}"
                @class([
                    'block rounded-xl border bg-white px-4 py-3 transition hover:border-brand-300',
                    'border-brand-400 ring-1 ring-brand-200' => request('status') === $key,
                    'border-slate-200' => request('status') !== $key,
                ])>
                <p class="text-xs tracking-wide text-slate-500 uppercase">{{ $label }}</p>
                <p class="mt-0.5 text-2xl font-semibold text-slate-900">{{ $counts[$key] ?? 0 }}</p>
            </a>
        @endforeach
    </div>

    @if (request('status'))
        <p class="mb-4 text-sm text-slate-600">
            Showing {{ strtolower($statuses[request('status')] ?? request('status')) }} only.
            <a href="{{ route('tax.index', ['year' => $year]) }}" class="link font-medium">Show everybody</a>
        </p>
    @endif

    <x-card>
        @if ($declarations->isEmpty())
            <x-empty title="Nobody has started a declaration for this year."
                description="One appears here as soon as an employee opens their own tax screen." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Regime</th>
                            <th class="num">Declared</th>
                            <th class="num">Verified</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($declarations as $declaration)
                            @php
                                $declared = $declaration->items->sum('declared_amount');
                                $verified = $declaration->items->whereNotNull('verified_amount')->sum('verified_amount');
                            @endphp
                            <tr>
                                <td>
                                    <span class="row-title">{{ $declaration->employee?->full_name }}</span>
                                    <span class="row-sub">
                                        {{ $declaration->employee?->employee_code }}
                                        @if ($declaration->employee?->department) · {{ $declaration->employee->department->name }} @endif
                                    </span>
                                </td>
                                <td class="text-slate-700">{{ $declaration->regimeLabel() }}</td>
                                <td class="num text-slate-900"><x-money :amount="$declared" /></td>
                                <td class="num">
                                    @if ($declaration->items->whereNotNull('verified_amount')->count())
                                        <x-money :amount="$verified" />
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td>
                                    <x-badge :color="$declaration->statusColor()" dot>{{ $declaration->statusLabel() }}</x-badge>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('tax.show', $declaration) }}" title="Open">
                                            <x-icon.eye class="size-4" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $declarations->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
