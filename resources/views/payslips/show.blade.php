<x-app-layout :title="'Salary slip ' . $payslip->periodLabel()">
    <div class="no-print">
        <x-page-header :title="'Salary slip for ' . $payslip->periodLabel()"
            :subtitle="$payslip->employee->full_name . ' · ' . $payslip->slip_number"
            :back="route('payslips.index')">
            <x-slot:actions>
                <x-badge :color="$payslip->isPaid() ? 'emerald' : 'slate'" dot>{{ ucfirst($payslip->payment_status) }}</x-badge>
                @can('download', $payslip)
                    <x-button href="{{ route('payslips.print', $payslip) }}" variant="secondary" target="_blank">
                        <x-icon.printer class="size-4" /> Print
                    </x-button>
                    <x-button href="{{ route('payslips.download', $payslip) }}">
                        <x-icon.download class="size-4" /> Download PDF
                    </x-button>
                @endcan
                @can('email', $payslip)
                    <form method="POST" action="{{ route('payslips.email', $payslip) }}" class="inline">
                        @csrf
                        <x-button variant="secondary" data-confirm="Email this payslip to {{ $payslip->employee->email }}?">
                            <x-icon.mail class="size-4" /> Email
                        </x-button>
                    </form>
                @endcan
            </x-slot:actions>
        </x-page-header>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card :padded="false">
                {{-- Header --}}
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            @if (! empty($company['logo']))
                                <img src="{{ route('branding.logo') }}?v={{ substr(md5($company['logo']), 0, 8) }}" alt="{{ $company['name'] }}"
                                    class="mb-2 max-h-12 max-w-40 object-contain">
                            @endif
                            <p class="text-lg font-semibold text-slate-900">{{ $company['name'] }}</p>
                            <p class="mt-0.5 text-xs whitespace-pre-line text-slate-500">{{ $company['address'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Salary slip</p>
                            <p class="mt-0.5 text-sm font-medium text-slate-900">{{ $payslip->periodLabel() }}</p>
                            <p class="font-mono text-xs text-slate-500">{{ $payslip->slip_number }}</p>
                        </div>
                    </div>
                </div>

                {{-- Employee details --}}
                <div class="grid gap-4 border-b border-slate-200 px-6 py-5 sm:grid-cols-2">
                    <dl class="space-y-2 text-sm">
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-slate-500">Employee</dt>
                            <dd class="font-medium text-slate-900">{{ $payslip->employee->full_name }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-slate-500">Employee code</dt>
                            <dd class="font-mono text-slate-900">{{ $payslip->employee->employee_code }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-slate-500">Designation</dt>
                            <dd class="text-slate-900">{{ $payslip->employee->designation?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-slate-500">Department</dt>
                            <dd class="text-slate-900">{{ $payslip->employee->department?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-slate-500">Date of joining</dt>
                            <dd class="text-slate-900">{{ $payslip->employee->date_of_joining->format('d M Y') }}</dd>
                        </div>
                    </dl>

                    <dl class="space-y-2 text-sm">
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-slate-500">Branch</dt>
                            <dd class="text-slate-900">{{ $payslip->employee->branch?->name ?? '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-slate-500">Bank account</dt>
                            <dd class="font-mono text-slate-900">{{ $payslip->employee->bank_account_number ?: '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-slate-500">PAN</dt>
                            <dd class="font-mono text-slate-900">{{ $payslip->employee->pan_number ?: '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-slate-500">UAN</dt>
                            <dd class="font-mono text-slate-900">{{ $payslip->employee->uan_number ?: '-' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-32 shrink-0 text-slate-500">Payment mode</dt>
                            <dd class="text-slate-900">
                                {{ App\Models\SalaryStructure::PAYMENT_MODES[$payslip->payment_mode] ?? $payslip->payment_mode }}
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Attendance --}}
                <div class="grid grid-cols-2 gap-4 border-b border-slate-200 bg-slate-50 px-6 py-4 text-sm sm:grid-cols-5">
                    <div>
                        <p class="text-xs tracking-wide text-slate-500 uppercase">Working days</p>
                        <p class="mt-0.5 font-semibold text-slate-900">{{ rtrim(rtrim(number_format($payslip->working_days, 1), '0'), '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wide text-slate-500 uppercase">Paid days</p>
                        <p class="mt-0.5 font-semibold text-slate-900">{{ rtrim(rtrim(number_format($payslip->paid_days, 1), '0'), '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wide text-slate-500 uppercase">Paid leave</p>
                        <p class="mt-0.5 font-semibold text-slate-900">{{ rtrim(rtrim(number_format($payslip->paid_leave_days, 1), '0'), '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wide text-slate-500 uppercase">Loss of pay</p>
                        <p class="mt-0.5 font-semibold text-rose-600">{{ rtrim(rtrim(number_format($payslip->lop_days, 1), '0'), '.') }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-wide text-slate-500 uppercase">Overtime</p>
                        <p class="mt-0.5 font-semibold text-slate-900">{{ $payslip->overtime_hours }} h</p>
                        @if ($payslip->overtime_rate > 0)
                            <p class="text-xs text-slate-500">paid at <x-money :amount="$payslip->overtime_rate" :currency="$payslip->currency" />/h</p>
                        @endif
                    </div>
                </div>

                {{-- Earnings and deductions --}}
                <div class="grid sm:grid-cols-2">
                    <div class="border-b border-slate-200 sm:border-r sm:border-b-0">
                        <p class="border-b border-slate-200 bg-emerald-50 px-6 py-2.5 text-xs font-semibold tracking-wide text-emerald-800 uppercase">
                            Earnings
                        </p>
                        <div class="px-6 py-3">
                            @foreach ($payslip->earnings as $item)
                                <div class="flex justify-between border-b border-slate-100 py-2 text-sm last:border-0">
                                    <span class="text-slate-700">{{ $item->name }}</span>
                                    <span class="font-medium tabular-nums text-slate-900">
                                        <x-money :amount="$item->amount" :currency="$payslip->currency" />
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between border-t border-slate-200 bg-slate-50 px-6 py-3 text-sm font-semibold">
                            <span class="text-slate-700">Gross earnings</span>
                            <span class="tabular-nums text-slate-900"><x-money :amount="$payslip->gross_earnings" :currency="$payslip->currency" /></span>
                        </div>
                    </div>

                    <div>
                        <p class="border-b border-slate-200 bg-rose-50 px-6 py-2.5 text-xs font-semibold tracking-wide text-rose-800 uppercase">
                            Deductions
                        </p>
                        <div class="px-6 py-3">
                            @forelse ($payslip->deductions as $item)
                                <div class="flex justify-between border-b border-slate-100 py-2 text-sm last:border-0">
                                    <span class="text-slate-700">{{ $item->name }}</span>
                                    <span class="font-medium tabular-nums text-slate-900">
                                        <x-money :amount="$item->amount" :currency="$payslip->currency" />
                                    </span>
                                </div>
                            @empty
                                <p class="py-3 text-sm text-slate-500">No deductions.</p>
                            @endforelse
                        </div>
                        <div class="flex justify-between border-t border-slate-200 bg-slate-50 px-6 py-3 text-sm font-semibold">
                            <span class="text-slate-700">Total deductions</span>
                            <span class="tabular-nums text-slate-900"><x-money :amount="$payslip->total_deductions" :currency="$payslip->currency" /></span>
                        </div>
                    </div>
                </div>

                {{-- Net --}}
                <div class="border-t-2 border-slate-300 bg-brand-50 px-6 py-5">
                    <div class="flex flex-wrap items-baseline justify-between gap-3">
                        <span class="text-sm font-semibold tracking-wide text-brand-900 uppercase">Net pay</span>
                        <span class="text-3xl font-bold tabular-nums text-brand-900">
                            <x-money :amount="$payslip->net_pay" :currency="$payslip->currency" />
                        </span>
                    </div>
                    @if ($payslip->net_pay_words)
                        <p class="mt-1.5 text-sm text-brand-800">{{ $payslip->net_pay_words }}</p>
                    @endif
                </div>

                <div class="px-6 py-4 text-xs text-slate-500">
                    This is a computer-generated salary slip and does not require a signature.
                    @if ($payslip->remarks)
                        <span class="mt-1 block">{{ $payslip->remarks }}</span>
                    @endif
                </div>
            </x-card>
        </div>

        <div class="no-print space-y-6">
            <x-card title="Payment">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Status</dt>
                        <dd><x-badge :color="$payslip->isPaid() ? 'emerald' : 'slate'" dot>{{ ucfirst($payslip->payment_status) }}</x-badge></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Payment date</dt>
                        <dd class="text-right text-slate-900">{{ $payslip->payment_date?->format('d M Y') ?? 'Pending' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Reference</dt>
                        <dd class="text-right font-mono text-slate-900">{{ $payslip->payment_reference ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Emailed</dt>
                        <dd class="text-right text-slate-900">{{ $payslip->emailed_at?->format('d M Y') ?? 'Not sent' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Attendance</dt>
                        <dd class="text-right text-slate-900">{{ $payslip->attendancePercentage() }}%</dd>
                    </div>
                </dl>
            </x-card>

            @can('update', $payslip)
                <x-card title="Adjust payslip">
                    <form method="POST" action="{{ route('payslips.update', $payslip) }}" class="space-y-4">
                        @csrf @method('PUT')
                        <x-input label="Payment reference" name="payment_reference" :value="$payslip->payment_reference" />
                        <x-textarea label="Remarks" name="remarks" :value="$payslip->remarks" rows="3"
                            help="Printed at the bottom of the salary slip." />
                        <x-button class="w-full">Save</x-button>
                    </form>
                </x-card>
            @endcan

            <x-card title="Payroll run">
                <p class="text-sm text-slate-900">{{ $payslip->payroll->title }}</p>
                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ $payslip->payroll->reference }}</p>
                <div class="mt-2">
                    <x-badge :color="$payslip->payroll->status->color()" dot>{{ $payslip->payroll->status->label() }}</x-badge>
                </div>
                @can('view', $payslip->payroll)
                    <x-button href="{{ route('payroll.show', $payslip->payroll) }}" variant="secondary" size="sm" class="mt-4 w-full">
                        Open run
                    </x-button>
                @endcan
            </x-card>
        </div>
    </div>
</x-app-layout>
