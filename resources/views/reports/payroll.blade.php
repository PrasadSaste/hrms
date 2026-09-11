<x-app-layout title="Payroll report">
    <x-page-header title="Payroll report" :subtitle="'Salary cost across ' . $yearLabel . ' · ' . $period"
        :back="route('reports.index')" />

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Payslips issued" :value="$totals['slips']" color="brand">
            <x-slot:icon><x-icon.receipt class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Gross paid" :value="\App\Support\Money::withSymbol($totals['gross'], null, 0)" color="sky">
            <x-slot:icon><x-icon.currency class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Deductions" :value="\App\Support\Money::withSymbol($totals['deductions'], null, 0)" color="amber">
            <x-slot:icon><x-icon.scale class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Net paid" :value="\App\Support\Money::withSymbol($totals['net'], null, 0)" color="emerald">
            <x-slot:icon><x-icon.check class="size-5" /></x-slot:icon>
        </x-stat>
    </div>

    <x-filter-bar :action="route('reports.payroll')">
        <x-select label="Year" name="year" :selected="$year" :options="$years" class="sm:w-40" />
        <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-48" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Run report</x-button>
    </x-filter-bar>

    <x-card title="Monthly cost" class="mb-6">
        @php $peak = max(1, $byMonth->max('net')); @endphp
        <div class="flex h-48 items-stretch gap-2">
            @foreach ($byMonth as $row)
                <div class="group flex flex-1 flex-col items-center gap-1.5">
                    <span class="text-[10px] font-medium text-slate-500 opacity-0 transition group-hover:opacity-100">
                        {{ $row['net'] > 0 ? number_format($row['net'] / 1000, 0) . 'k' : '' }}
                    </span>
                    <div class="flex w-full flex-1 items-end">
                        <div class="w-full rounded-t bg-brand-500/80 transition group-hover:bg-brand-600"
                            style="height: {{ max(1, round($row['net'] / $peak * 100)) }}%"
                            title="{{ $row['label'] }}: {{ \App\Support\Money::withSymbol($row['net']) }} for {{ $row['employees'] }} employee(s)"></div>
                    </div>
                    <span class="text-[10px] text-slate-400">{{ $row['label'] }}</span>
                </div>
            @endforeach
        </div>
    </x-card>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Month by month" :padded="false">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Month</th><th class="num">Employees</th><th class="num">Gross</th>
                            <th class="num">Deductions</th><th class="num">Net</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($byMonth as $row)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $row['label'] }}</td>
                                <td class="num tabular-nums">{{ $row['employees'] }}</td>
                                <td class="num tabular-nums"><x-money :amount="$row['gross']" :decimals="0" /></td>
                                <td class="num tabular-nums"><x-money :amount="$row['deductions']" :decimals="0" /></td>
                                <td class="num font-semibold tabular-nums"><x-money :amount="$row['net']" :decimals="0" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                        <tr class="text-sm font-semibold">
                            <td class="px-4 py-3">Total</td>
                            <td class="px-4 py-3 text-right"></td>
                            <td class="px-4 py-3 text-right tabular-nums"><x-money :amount="$totals['gross']" :decimals="0" /></td>
                            <td class="px-4 py-3 text-right tabular-nums"><x-money :amount="$totals['deductions']" :decimals="0" /></td>
                            <td class="px-4 py-3 text-right tabular-nums"><x-money :amount="$totals['net']" :decimals="0" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-card>

        <x-card title="Cost by department">
            @php $maxDept = max(1, $byDepartment->max('net') ?? 1); @endphp
            @forelse ($byDepartment as $row)
                <div class="border-b border-slate-100 py-3 first:pt-0 last:border-0 last:pb-0">
                    <div class="mb-1.5 flex justify-between text-sm">
                        <span class="text-slate-700">{{ $row['department'] }}</span>
                        <span class="font-medium text-slate-900"><x-money :amount="$row['net']" :decimals="0" /></span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ round($row['net'] / $maxDept * 100) }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $row['employees'] }} employee(s)</p>
                </div>
            @empty
                <x-empty title="No payroll data for {{ $yearLabel }}" />
            @endforelse
        </x-card>
    </div>
</x-app-layout>
