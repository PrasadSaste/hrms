<x-app-layout title="Salary slips">
    <x-page-header title="Salary slips"
        :subtitle="$canViewAll ? 'Payslips across the organisation' : 'Your published salary slips'" />

    <x-filter-bar :action="route('payslips.index')">
        <x-select label="Year" name="year" :selected="request('year')" placeholder="All years"
            :options="collect($years)->mapWithKeys(fn ($y) => [$y => $y])->all()" class="sm:w-32" />
        <x-select label="Month" name="month" :selected="request('month')" placeholder="All months"
            :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(null, $m, 1)->format('F')])->all()"
            class="sm:w-40" />
        @if ($canViewAll)
            <x-select label="Employee" name="employee_id" :selected="request('employee_id')" placeholder="All employees"
                :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->first_name . ' ' . $e->last_name])->all()"
                class="sm:w-52" />
            <x-select label="Branch" name="branch_id" :selected="request('branch_id')" placeholder="All branches"
                :options="$branches->pluck('name', 'id')->all()" class="sm:w-44" />
            <x-select label="Payment" name="payment_status" :selected="request('payment_status')" placeholder="All"
                :options="['paid' => 'Paid', 'unpaid' => 'Unpaid']" class="sm:w-36" />
        @endif
        <x-button variant="secondary"><x-icon.search class="size-4" /> Filter</x-button>
        @if (request()->hasAny(['year', 'month', 'employee_id', 'branch_id', 'payment_status']))
            <x-button href="{{ route('payslips.index') }}" variant="ghost">Clear</x-button>
        @endif
    </x-filter-bar>

    <x-card :padded="false">
        <div class="table-wrap is-scrollable">
            <table class="table">
                <thead>
                    <tr>
                        <th>Period</th>
                        @if ($canViewAll)<th>Employee</th>@endif
                        <th>Slip number</th>
                        <th class="num">Paid days</th><th class="num">Gross</th>
                        <th class="num">Deductions</th><th class="num">Net pay</th>
                        <th>Payment</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payslips as $payslip)
                        <tr>
                            <td class="font-medium whitespace-nowrap text-slate-900">{{ $payslip->periodLabel() }}</td>
                            @if ($canViewAll)
                                <td>
                                    <div class="text-slate-900">{{ $payslip->employee->full_name }}</div>
                                    <div class="font-mono text-xs text-slate-500">{{ $payslip->employee->employee_code }}</div>
                                </td>
                            @endif
                            <td class="font-mono text-xs text-slate-500">{{ $payslip->slip_number }}</td>
                            <td class="num tabular-nums">
                                {{ rtrim(rtrim(number_format($payslip->paid_days, 1), '0'), '.') }}
                                <span class="text-xs text-slate-400">/ {{ rtrim(rtrim(number_format($payslip->working_days, 1), '0'), '.') }}</span>
                            </td>
                            <td class="num tabular-nums"><x-money :amount="$payslip->gross_earnings" :currency="$payslip->currency" /></td>
                            <td class="num tabular-nums"><x-money :amount="$payslip->total_deductions" :currency="$payslip->currency" /></td>
                            <td class="num font-semibold tabular-nums"><x-money :amount="$payslip->net_pay" :currency="$payslip->currency" /></td>
                            <td>
                                <x-badge :color="$payslip->isPaid() ? 'emerald' : 'slate'" dot>{{ ucfirst($payslip->payment_status) }}</x-badge>
                                @if ($payslip->payment_date)
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $payslip->payment_date->format('d M Y') }}</div>
                                @endif
                            </td>
                            <td class="num whitespace-nowrap">
                                <x-button href="{{ route('payslips.show', $payslip) }}" variant="ghost" size="sm">View</x-button>
                                @can('download', $payslip)
                                    <x-button href="{{ route('payslips.download', $payslip) }}" variant="ghost" size="sm">
                                        <x-icon.download class="size-4" />
                                    </x-button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canViewAll ? 9 : 8 }}">
                                <x-empty title="No salary slips"
                                    message="Payslips appear here once a payroll run is approved." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $payslips->links() }}</div>
</x-app-layout>
