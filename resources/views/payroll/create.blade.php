<x-app-layout title="New payroll run">
    <x-page-header title="New payroll run" subtitle="Choose the period and branch, then generate payslips"
        :back="route('payroll.index')" />

    <form method="POST" action="{{ route('payroll.store') }}">
        @csrf
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Run details" class="lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select label="Company" name="company_id" required class="sm:col-span-2"
                        :selected="old('company_id', $defaultCompany?->id)"
                        :options="$companies->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()"
                        help="Payroll runs for one legal entity at a time. Only that company's employees are included." />

                    <x-select label="Month" name="month" required :selected="$defaultMonth"
                        :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(null, $m, 1)->format('F')])->all()" />
                    <x-input label="Year" name="year" type="number" min="2000" max="2100" :value="$defaultYear" required />

                    <x-select label="Branch" name="branch_id" placeholder="All branches"
                        :options="$branches->pluck('name', 'id')->all()"
                        help="Leave blank to process every branch in one run." />
                    <x-input label="Payment date" name="payment_date" type="date"
                        help="Defaults to the last day of the period." />

                    <x-input label="Title" name="title" class="sm:col-span-2"
                        placeholder="Generated from the period if left blank" />
                    <x-textarea label="Notes" name="notes" rows="3" class="sm:col-span-2" />

                    <x-checkbox label="Generate payslips immediately" name="generate_now" :checked="true"
                        help="Uncheck to create an empty draft you can generate later." class="sm:col-span-2" />
                </div>
            </x-card>

            <div class="space-y-6">
                <x-card title="How a run works">
                    <ol class="space-y-3 text-sm text-slate-600">
                        <li><span class="font-medium text-slate-900">1. Generate.</span>
                            Each eligible employee's active salary structure is resolved and prorated
                            by their paid days for the period.</li>
                        <li><span class="font-medium text-slate-900">2. Review.</span>
                            Check the register, then submit the run for approval.</li>
                        <li><span class="font-medium text-slate-900">3. Approve.</span>
                            Payslips are published and become visible to employees.</li>
                        <li><span class="font-medium text-slate-900">4. Pay and email.</span>
                            Mark the run paid and send each payslip as a PDF.</li>
                    </ol>
                </x-card>

                <x-card title="Before you generate">
                    <ul class="space-y-2 text-sm text-slate-600">
                        <li>Employees without an active salary structure are skipped and listed by name.</li>
                        <li>Attendance and approved leave for the period drive the paid days.</li>
                        <li>A draft run can be regenerated as many times as you need.</li>
                    </ul>
                </x-card>

            </div>
        </div>

        <x-form-actions>
            <x-button size="lg">Create run</x-button>
            <x-button href="{{ route('payroll.index') }}" variant="secondary" size="lg">Cancel</x-button>
        </x-form-actions>
    </form>
</x-app-layout>
