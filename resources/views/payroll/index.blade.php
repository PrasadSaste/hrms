<x-app-layout title="Payroll">
    <x-page-header title="Payroll runs" subtitle="Monthly salary processing by branch">
        <x-slot:actions>
            @can('create', App\Models\Payroll::class)
                <x-button href="{{ route('payroll.create') }}"><x-icon.plus class="size-4" /> New payroll run</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('payroll.index')">
        <x-select label="Year" name="year" :selected="request('year')" placeholder="All years"
            :options="collect($years)->mapWithKeys(fn ($y) => [$y => $y])->all()" class="sm:w-32" />
        <x-select label="Company" name="company_id" :selected="request('company_id')" placeholder="All companies"
            :options="$companies->pluck('name', 'id')->all()" class="sm:w-48" />
        <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-48" />
        <x-select label="Status" name="status" :selected="request('status')" placeholder="All statuses"
            :options="$statuses" class="sm:w-44" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
        @if (request()->hasAny(['year', 'company_id', 'branch_id', 'status']))
            <x-button href="{{ route('payroll.index') }}" variant="ghost">Clear</x-button>
        @endif
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Run</th><th>Company</th><th>Period</th><th>Branch</th>
                        <th class="num">Employees</th><th class="num">Gross</th>
                        <th class="num">Deductions</th><th class="num">Net</th>
                        <th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr>
                            <td>
                                <a href="{{ route('payroll.show', $run) }}" class="font-medium text-slate-900 hover-ink">
                                    {{ $run->title }}
                                </a>
                                <div class="font-mono text-xs text-slate-500">{{ $run->reference }}</div>
                            </td>
                            <td class="whitespace-nowrap">{{ $run->company?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $run->periodLabel() }}</td>
                            <td>{{ $run->branch?->name ?? 'All branches' }}</td>
                            <td class="num tabular-nums">{{ $run->payslips_count }}</td>
                            <td class="num tabular-nums"><x-money :amount="$run->total_gross" :decimals="0" /></td>
                            <td class="num tabular-nums"><x-money :amount="$run->total_deductions" :decimals="0" /></td>
                            <td class="num font-semibold tabular-nums"><x-money :amount="$run->total_net" :decimals="0" /></td>
                            <td><x-badge :color="$run->status->color()" dot>{{ $run->status->label() }}</x-badge></td>
                            <td class="num">
                                <x-button href="{{ route('payroll.show', $run) }}" variant="ghost" size="sm">Open</x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <x-empty title="No payroll runs" message="Create a run to generate salary slips for a month.">
                                    <x-slot:action>
                                        @can('create', App\Models\Payroll::class)
                                            <x-button href="{{ route('payroll.create') }}">New payroll run</x-button>
                                        @endcan
                                    </x-slot:action>
                                </x-empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $runs->links() }}</div>
</x-app-layout>
