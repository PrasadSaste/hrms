@php
    $selected = $selected ?? collect();
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <x-tabs key="salary-structure" :tabs="['structure' => 'Structure', 'components' => 'Components']">

        <div data-tab-panel="structure">
        <x-card title="Structure">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select label="Employee" name="employee_id" required placeholder="Choose an employee"
                    :selected="$structure->employee_id"
                    :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->first_name . ' ' . $e->last_name . ' (' . $e->employee_code . ')'])->all()"
                    class="sm:col-span-2" />

                <x-input label="Effective from" name="effective_from" type="date" required
                    :value="$structure->effective_from?->toDateString() ?? now()->startOfMonth()->toDateString()"
                    help="Any open structure for this employee is closed the day before." />
                <x-input label="Effective to" name="effective_to" type="date"
                    :value="$structure->effective_to?->toDateString()"
                    help="Leave blank while this is the current structure." />

                <x-input label="Annual CTC" name="ctc_annual" type="number" step="0.01" min="0"
                    :value="$structure->ctc_annual ?? 0" required
                    help="Total cost to company for a full year." />
                <x-input label="Monthly basic salary" name="basic_salary" type="number" step="0.01" min="0"
                    :value="$structure->basic_salary ?? 0" required
                    help="The base for percentage components such as HRA and PF." />

                <x-input label="Currency" name="currency" :value="$structure->currency ?? 'INR'" required maxlength="3" />
                <x-select label="Payment mode" name="payment_mode" required
                    :selected="$structure->payment_mode ?? 'bank_transfer'"
                    :options="App\Models\SalaryStructure::PAYMENT_MODES" />

                <x-textarea label="Notes" name="notes" :value="$structure->notes" rows="2" class="sm:col-span-2" />
            </div>
        </x-card>

        </div>

        <div data-tab-panel="components">
        <x-card title="Components" subtitle="Tick a line to include it, then set its value">
            <div class="space-y-6">
                @foreach (['earning' => 'Earnings', 'deduction' => 'Deductions'] as $type => $heading)
                    <div>
                        <h3 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $heading }}</h3>
                        <div class="space-y-2">
                            @foreach ($components->where('type.value', $type) as $line)
                                @php
                                    $existing = $selected->get($line->id);
                                    $isOn = old("components.{$line->id}.enabled", $existing !== null || ! $structure->exists);
                                    $value = old("components.{$line->id}.value", $existing?->value ?? $line->default_value);
                                    $calc = old("components.{$line->id}.calculation_type", $existing?->calculation_type ?? $line->calculation_type);
                                @endphp
                                <div data-structure-row class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-3">
                                    <label class="flex min-w-52 flex-1 items-center gap-2.5">
                                        <input type="hidden" name="components[{{ $line->id }}][enabled]" value="0">
                                        <input type="checkbox" data-structure-enabled class="form-checkbox"
                                            name="components[{{ $line->id }}][enabled]" value="1" @checked($isOn)>
                                        <span>
                                            <span class="text-sm font-medium text-slate-800">{{ $line->name }}</span>
                                            <span class="block font-mono text-xs text-slate-500">{{ $line->code }}</span>
                                        </span>
                                    </label>

                                    <select name="components[{{ $line->id }}][calculation_type]" data-structure-input
                                        class="form-select w-40 py-1.5 text-sm">
                                        @foreach (App\Models\SalaryComponent::CALCULATION_TYPES as $key => $label)
                                            <option value="{{ $key }}" @selected($calc === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>

                                    <input type="number" step="0.01" min="0" data-structure-input
                                        name="components[{{ $line->id }}][value]" value="{{ $value }}"
                                        class="form-input w-32 py-1.5 text-sm text-right">

                                    <span class="w-28 text-right text-xs text-slate-500">
                                        {{ $line->calculation_type === 'percentage'
                                            ? '% of ' . (App\Models\SalaryComponent::PERCENTAGE_BASES[$line->percentage_of] ?? 'basic')
                                            : 'fixed amount' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
        </div>

        </x-tabs>
    </div>

    <div class="space-y-6">
        <x-card title="Status">
            <x-select label="Status" name="status" :selected="$structure->status ?? 'active'" required
                :options="['active' => 'Active', 'inactive' => 'Inactive']"
                help="Only an active structure is picked up by payroll." />
        </x-card>

        <x-card title="How amounts resolve">
            <ul class="space-y-2 text-sm text-slate-600">
                <li>Percentage lines are resolved against basic, gross or monthly CTC as configured on the component.</li>
                <li>Earnings are computed first so deductions expressed as a percentage of gross see the final figure.</li>
                <li>Lines marked "prorate on loss of pay" scale with the employee's paid days each month.</li>
            </ul>
        </x-card>

    </div>
</div>

<x-form-actions>
    <x-button size="lg">{{ $structure->exists ? 'Save changes' : 'Create structure' }}</x-button>
    <x-button href="{{ route('salary-structures.index') }}" variant="secondary" size="lg">Cancel</x-button>
</x-form-actions>
