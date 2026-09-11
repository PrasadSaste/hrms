<x-app-layout :title="$payroll->title">
    <x-page-header :title="$payroll->title"
        :subtitle="($payroll->company?->displayName() ?? 'No company') . ' · ' . $payroll->periodLabel() . ' · ' . ($payroll->branch?->name ?? 'All branches') . ' · ' . $payroll->reference"
        :back="route('payroll.index')">
        <x-slot:actions>
            <x-badge :color="$payroll->status->color()" dot class="text-sm">{{ $payroll->status->label() }}</x-badge>

            <x-button href="{{ route('payroll.export', $payroll) }}" variant="secondary">
                <x-icon.download class="size-4" /> Register CSV
            </x-button>

            @if (in_array($payroll->status->value, ['approved', 'paid'], true))
                <x-button href="{{ route('payroll.bank-file', $payroll) }}" variant="secondary">
                    <x-icon.currency class="size-4" /> Bank file
                </x-button>
            @endif

            @can('generate', $payroll)
                <form method="POST" action="{{ route('payroll.generate', $payroll) }}" class="inline">
                    @csrf
                    <x-button variant="secondary"
                        data-confirm="Regenerate payslips? Existing draft payslips in this run are replaced.">
                        Generate payslips
                    </x-button>
                </form>
            @endcan

            @can('update', $payroll)
                @if ($payroll->status === App\Enums\PayrollStatus::Draft && $payroll->payslips()->exists())
                    <form method="POST" action="{{ route('payroll.submit', $payroll) }}" class="inline">
                        @csrf
                        <x-button variant="secondary">Submit for approval</x-button>
                    </form>
                @endif
            @endcan

            @can('approve', $payroll)
                <form method="POST" action="{{ route('payroll.approve', $payroll) }}" class="inline">
                    @csrf
                    <x-button variant="success"
                        data-confirm="Approve this run? Payslips become visible to employees.">
                        <x-icon.check class="size-4" /> Approve
                    </x-button>
                </form>
            @endcan

            @can('markPaid', $payroll)
                <x-button type="button" data-dialog-open="mark-paid">Mark as paid</x-button>
            @endcan

            @can('payslips.email')
                @if ($payroll->isApproved())
                    <form method="POST" action="{{ route('payroll.email', $payroll) }}" class="inline">
                        @csrf
                        <x-button variant="secondary" data-confirm="Email every published payslip in this run?">
                            <x-icon.mail class="size-4" /> Email payslips
                        </x-button>
                    </form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Employees" :value="$payroll->total_employees" color="brand">
            <x-slot:icon><x-icon.users class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Gross earnings" :value="\App\Support\Money::withSymbol($payroll->total_gross, null, 0)" color="sky">
            <x-slot:icon><x-icon.currency class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Deductions" :value="\App\Support\Money::withSymbol($payroll->total_deductions, null, 0)" color="amber">
            <x-slot:icon><x-icon.scale class="size-5" /></x-slot:icon>
        </x-stat>
        <x-stat label="Net payable" :value="\App\Support\Money::withSymbol($payroll->total_net, null, 0)" color="emerald">
            <x-slot:icon><x-icon.receipt class="size-5" /></x-slot:icon>
        </x-stat>
    </div>

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="lg:col-span-3">
            <x-card title="Payslip register" :subtitle="$payslips->total() . ' payslip(s)'" :padded="false">
                <div class="table-wrap is-scrollable">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th><th>Slip number</th>
                                <th class="num">Paid days</th><th class="num">LOP</th>
                                <th class="num">Gross</th><th class="num">Deductions</th>
                                <th class="num">Net pay</th><th>Payment</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payslips as $payslip)
                                <tr>
                                    <td>
                                        <div class="font-medium text-slate-900">{{ $payslip->employee->full_name }}</div>
                                        <div class="text-xs text-slate-500">
                                            {{ $payslip->employee->employee_code }} &middot;
                                            {{ $payslip->employee->department?->name ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="font-mono text-xs text-slate-500">{{ $payslip->slip_number }}</td>
                                    <td class="num tabular-nums">
                                        {{ rtrim(rtrim(number_format($payslip->paid_days, 1), '0'), '.') }}
                                        <span class="text-xs text-slate-400">/ {{ rtrim(rtrim(number_format($payslip->working_days, 1), '0'), '.') }}</span>
                                    </td>
                                    <td class="num tabular-nums">
                                        @if ($payslip->lop_days > 0)
                                            <span class="text-rose-600">{{ rtrim(rtrim(number_format($payslip->lop_days, 1), '0'), '.') }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="num tabular-nums"><x-money :amount="$payslip->gross_earnings" :currency="$payslip->currency" /></td>
                                    <td class="num tabular-nums"><x-money :amount="$payslip->total_deductions" :currency="$payslip->currency" /></td>
                                    <td class="num font-semibold tabular-nums"><x-money :amount="$payslip->net_pay" :currency="$payslip->currency" /></td>
                                    <td>
                                        <x-badge :color="$payslip->isPaid() ? 'emerald' : 'slate'" dot>
                                            {{ ucfirst($payslip->payment_status) }}
                                        </x-badge>
                                    </td>
                                    <td class="num whitespace-nowrap">
                                        <x-button href="{{ route('payslips.show', $payslip) }}" variant="ghost" size="sm">View</x-button>
                                        @can('download', $payslip)
                                            <x-button href="{{ route('payslips.download', $payslip) }}" variant="ghost" size="sm">PDF</x-button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <x-empty title="No payslips yet"
                                            message="Generate the run to create a payslip for every eligible employee.">
                                            <x-slot:action>
                                                @can('generate', $payroll)
                                                    <form method="POST" action="{{ route('payroll.generate', $payroll) }}">
                                                        @csrf
                                                        <x-button>Generate payslips</x-button>
                                                    </form>
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

            <div class="mt-4">{{ $payslips->links() }}</div>
        </div>

        <div class="space-y-6">
            <x-card title="Run details">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Company</dt>
                        <dd class="mt-0.5 text-sm text-slate-900">{{ $payroll->company?->displayName() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Period</dt>
                        <dd class="mt-0.5 text-slate-900">
                            {{ $payroll->period_start->format('d M') }} to {{ $payroll->period_end->format('d M Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Payment date</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $payroll->payment_date?->format('d M Y') ?? 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Generated by</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $payroll->generatedBy?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-500 uppercase">Approved by</dt>
                        <dd class="mt-0.5 text-slate-900">
                            {{ $payroll->approvedBy?->name ?? 'Not approved' }}
                            @if ($payroll->approved_at)
                                <span class="block text-xs text-slate-500">{{ $payroll->approved_at->format('d M Y, h:i A') }}</span>
                            @endif
                        </dd>
                    </div>
                    @if ($payroll->notes)
                        <div>
                            <dt class="text-xs tracking-wide text-slate-500 uppercase">Notes</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-slate-900">{{ $payroll->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @can('update', $payroll)
                <x-card title="Edit run">
                    <form method="POST" action="{{ route('payroll.update', $payroll) }}" class="space-y-4">
                        @csrf @method('PUT')
                        <x-input label="Title" name="title" :value="$payroll->title" required />
                        <x-input label="Payment date" name="payment_date" type="date" :value="$payroll->payment_date?->toDateString()" />
                        <x-textarea label="Notes" name="notes" :value="$payroll->notes" rows="3" />
                        <x-button class="w-full">Save changes</x-button>
                    </form>
                </x-card>
            @endcan

            @can('delete', $payroll)
                <x-card title="Delete run">
                    <p class="text-sm text-slate-500">
                        Deleting a draft run removes its payslips. Approved and paid runs cannot be deleted.
                    </p>
                    <form method="POST" action="{{ route('payroll.destroy', $payroll) }}" class="mt-3">
                        @csrf @method('DELETE')
                        <x-button variant="danger" class="w-full"
                            data-confirm="Delete this payroll run and all its payslips?">Delete run</x-button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>

    @can('markPaid', $payroll)
        <x-dialog name="mark-paid" title="Mark payroll as paid" size="sm">
            <form id="mark-paid-form" method="POST" action="{{ route('payroll.mark-paid', $payroll) }}">
                @csrf
                <div class="space-y-4 px-5 py-5">
                    <p class="text-sm text-slate-600">
                        This marks every payslip in the run as paid and stamps the payment date.
                    </p>
                    <x-input label="Payment date" name="payment_date" type="date"
                        :value="$payroll->payment_date?->toDateString() ?? now()->toDateString()" />
                    <x-input label="Payment reference" name="payment_reference" placeholder="NEFT batch or transaction id" />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" data-dialog-close="mark-paid">Cancel</x-button>
                    <x-button form="mark-paid-form" variant="success">Confirm payment</x-button>
                </div>
            </x-slot:footer>
        </x-dialog>
    @endcan
</x-app-layout>
