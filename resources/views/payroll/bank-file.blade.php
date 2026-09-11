<x-app-layout title="Bank file">
    <x-page-header title="Bank payment file"
        :subtitle="$payroll->title . ' · ' . $payroll->periodLabel() . ' · ' . $payroll->reference"
        :back="route('payroll.show', $payroll)">
        <x-slot:actions>
            <x-badge :color="$payroll->status->color()" dot class="text-sm">{{ $payroll->status->label() }}</x-badge>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            @if ($problems)
                <x-card title="Fix these first"
                    subtitle="The file is not produced while any of these stand. A bank rejects the whole upload over one bad row.">
                    <ul class="space-y-2 text-sm">
                        @foreach ($problems as $problem)
                            <li class="flex items-start gap-2.5">
                                <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-rose-500"></span>
                                <span class="text-slate-700">{{ $problem }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
                        Bank details live on the employee record, under Bank &amp; statutory. The
                        account the money leaves is on the company, under
                        <a href="{{ route('companies.index') }}" class="link font-medium">Payroll → Companies</a>.
                    </p>
                </x-card>
            @else
                <x-card title="Ready to pay">
                    <p class="text-sm text-slate-600">
                        Every row has an account number and an IFSC that looks like one, and the run
                        is approved. Choose the layout your bank expects and download the file.
                    </p>
                </x-card>
            @endif

            <x-card title="What will be in the file"
                :subtitle="$payable->count() . ' ' . Str::plural('payment', $payable->count()) . ', ' . App\Support\Money::withSymbol($total, $payroll->company?->currency ?? 'INR') . ' in total'">
                @if ($payable->isEmpty())
                    <x-empty title="Nobody is paid by bank transfer in this run."
                        description="Payment mode is set on the salary structure." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Account</th>
                                    <th>IFSC</th>
                                    <th>Type</th>
                                    <th class="num">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payable as $slip)
                                    @php $issues = app(App\Services\Bank\BankFileService::class)->problemsWith($slip); @endphp
                                    <tr @class(['bg-rose-50/40' => $issues !== []])>
                                        <td>
                                            <span class="font-medium text-slate-900">{{ $slip->employee?->full_name }}</span>
                                            <span class="block text-xs text-slate-500">{{ $slip->employee?->employee_code }}</span>
                                        </td>
                                        <td class="font-mono text-xs text-slate-700">
                                            {{ $slip->employee?->bank_account_number ?: '—' }}
                                        </td>
                                        <td class="font-mono text-xs text-slate-700">
                                            {{ $slip->employee?->bank_ifsc ?: '—' }}
                                        </td>
                                        <td>
                                            <x-badge color="slate">{{ app(App\Services\Bank\BankFileService::class)->transactionType($slip, $payroll->company) }}</x-badge>
                                        </td>
                                        <td class="num font-medium text-slate-900">
                                            <x-money :amount="$slip->net_pay" :currency="$slip->currency" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="eyebrow">Total</td>
                                    <td class="num font-semibold text-slate-900">
                                        <x-money :amount="$total" :currency="$payroll->company?->currency ?? 'INR'" />
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </x-card>

            @if ($excluded->isNotEmpty())
                <x-alert type="info" :dismissible="false">
                    <span class="font-medium">{{ $excluded->count() }} {{ Str::plural('person', $excluded->count()) }}</span>
                    in this run {{ $excluded->count() === 1 ? 'is' : 'are' }} not paid by transfer and
                    {{ $excluded->count() === 1 ? 'is' : 'are' }} left out of the file:
                    {{ $excluded->map(fn ($slip) => $slip->employee?->full_name)->filter()->join(', ') }}.
                    They still have payslips, and the file's total will not match the run's.
                </x-alert>
            @endif

            <x-card title="The columns this layout writes"
                subtitle="Compare these against the template your bank sent you before the first upload.">
                <ol class="grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                    @foreach ($format['columns'] as $index => $column)
                        <li class="flex items-baseline gap-2">
                            <span class="w-5 shrink-0 text-right text-xs text-slate-400">{{ $index + 1 }}</span>
                            <span class="font-medium text-slate-900">{{ $column['heading'] ?? Str::headline($column['field']) }}</span>
                            <span class="text-xs text-slate-500">{{ App\Support\BankFormats::FIELDS[$column['field']] ?? '' }}</span>
                        </li>
                    @endforeach
                </ol>

                <p class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500">
                    {{ $format['header'] ? 'Written with a heading row.' : 'Written without a heading row.' }}
                    {{ $format['delimiter'] === "\t" ? 'Tab separated.' : 'Comma separated.' }}
                    Dates written as {{ $valueDate->format($format['date_format']) }}. Amounts unformatted,
                    two decimal places, no currency symbol.
                </p>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Download">
                {{-- Its own form, and a GET: choosing a layout reloads the page so
                     the columns listed beside it are the ones about to be written. --}}
                <form method="GET" action="{{ route('payroll.bank-file', $payroll) }}" class="space-y-4">
                    <x-select label="Bank layout" name="format" :selected="$formatKey"
                        :options="App\Support\BankFormats::options()" data-auto-submit
                        help="Remembered against the company once you download, so next month starts here." />
                </form>

                <p class="mt-4 text-xs text-slate-500">{{ $format['description'] }}</p>

                <form method="POST" action="{{ route('payroll.bank-file.download', $payroll) }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="format" value="{{ $formatKey }}">

                    <x-input label="Value date" name="value_date" type="date"
                        :value="$valueDate->toDateString()"
                        help="The date you want the money to move." />

                    @if ($problems)
                        <x-button class="w-full" disabled>Fix the problems first</x-button>
                    @elseif (! $canDownload)
                        <p class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">
                            @if (! auth()->user()->can('payroll.bank-file'))
                                Your role does not include downloading the bank file.
                            @else
                                A bank file is only produced once the run has been approved.
                            @endif
                        </p>
                    @else
                        <x-button class="w-full">
                            <x-icon.download class="size-4" /> Download the file
                        </x-button>
                    @endif
                </form>
            </x-card>

            <x-card title="Paying from">
                @if ($payroll->company?->bank_account_number)
                    <dl class="space-y-2.5 text-sm">
                        <div>
                            <dt class="text-slate-500">Account name</dt>
                            <dd class="text-slate-900">{{ $payroll->company->bank_account_name ?: $payroll->company->displayName() }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Account number</dt>
                            <dd class="font-mono text-xs text-slate-900">{{ $payroll->company->bank_account_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">IFSC</dt>
                            <dd class="font-mono text-xs text-slate-900">{{ $payroll->company->bank_ifsc ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Bank</dt>
                            <dd class="text-slate-900">{{ $payroll->company->bank_name ?: '—' }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-slate-600">
                        No account on record for {{ $payroll->company?->displayName() ?? 'this company' }}.
                        @can('companies.manage')
                            <a href="{{ route('companies.index') }}" class="link font-medium">Add one</a>
                            and the file can name it as the account the money leaves.
                        @endcan
                    </p>
                @endif
            </x-card>

            <x-card title="What happens next">
                <ol class="space-y-2.5 text-sm text-slate-600">
                    <li><span class="font-medium text-slate-900">1.</span> Upload the file to your bank.</li>
                    <li><span class="font-medium text-slate-900">2.</span> Check the total the bank reports against
                        <x-money :amount="$total" :currency="$payroll->company?->currency ?? 'INR'" /> above.</li>
                    <li><span class="font-medium text-slate-900">3.</span> Once the money has moved, come back and
                        <a href="{{ route('payroll.show', $payroll) }}" class="link font-medium">mark the run paid</a>
                        with the bank's reference. Downloading a file does not do that on its own — the money leaves at the bank, not here.</li>
                </ol>
            </x-card>
        </div>
    </div>
</x-app-layout>
