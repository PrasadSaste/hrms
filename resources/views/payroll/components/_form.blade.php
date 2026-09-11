<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <x-card title="Component">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Name" name="name" :value="$salaryComponent->name" required />
                <x-input label="Code" name="code" :value="$salaryComponent->code" required
                    help="Referenced on payslips, e.g. BASIC or HRA." />
                <x-select label="Type" name="type" required :selected="$salaryComponent->type?->value ?? 'earning'"
                    :options="App\Enums\ComponentType::options()" />
                <x-input label="Sequence" name="sequence" type="number" min="0" max="999"
                    :value="$salaryComponent->sequence ?? 0" required
                    help="Controls the order lines appear on a payslip." />
                <x-textarea label="Description" name="description" :value="$salaryComponent->description" rows="3" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card title="Calculation">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select label="Calculation type" name="calculation_type" required
                    :selected="$salaryComponent->calculation_type ?? 'fixed'"
                    :options="App\Models\SalaryComponent::CALCULATION_TYPES" />
                <x-select label="Percentage of" name="percentage_of" :selected="$salaryComponent->percentage_of"
                    placeholder="Not applicable" :options="App\Models\SalaryComponent::PERCENTAGE_BASES"
                    help="Only used when the calculation type is a percentage." />
                <x-input label="Default value" name="default_value" type="number" step="0.01" min="0"
                    :value="$salaryComponent->default_value ?? 0" required
                    help="A rupee amount, or a percentage when calculated that way." />
            </div>

            {{-- What makes a statutory deduction right. A percentage of a
                 figure is not what any of the three actually are. --}}
            <div class="mt-5 border-t border-slate-100 pt-5">
                <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Statutory rules</p>
                <p class="mt-1 text-xs text-slate-500">
                    Leave these empty for an ordinary component. They are what make provident fund,
                    employee state insurance and professional tax come out right.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-input label="Wage ceiling" name="wage_ceiling" type="number" step="0.01" min="0"
                        :value="$salaryComponent->wage_ceiling"
                        help="The percentage is worked out on a wage capped at this. Provident fund: 15000." />
                    <x-input label="Applies up to" name="eligibility_ceiling" type="number" step="0.01" min="0"
                        :value="$salaryComponent->eligibility_ceiling"
                        help="Above this the component does not apply at all. Employee state insurance: 21000." />
                </div>

                <div class="mt-4" data-slabs>
                    <p class="form-label">Slab table</p>
                    <p class="form-help mb-2">
                        Used when the calculation type is a slab table. Each row is the amount charged
                        up to and including that figure; leave the last row's ceiling empty for
                        everything above. Professional tax is set by each state — check yours.
                    </p>

                    <div class="space-y-2" data-slab-rows>
                        @php
                            $slabs = old('slabs', $salaryComponent->slabs ?: [['up_to' => '', 'amount' => '']]);
                        @endphp

                        @foreach ($slabs as $index => $slab)
                            <div class="flex items-center gap-2" data-slab-row>
                                <input type="number" step="0.01" min="0" class="form-input"
                                    name="slabs[{{ $index }}][up_to]" placeholder="Up to (empty = no limit)"
                                    value="{{ $slab['up_to'] ?? '' }}">
                                <input type="number" step="0.01" min="0" class="form-input"
                                    name="slabs[{{ $index }}][amount]" placeholder="Amount"
                                    value="{{ $slab['amount'] ?? '' }}">
                                <button type="button" class="shrink-0 rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                                    data-slab-remove aria-label="Remove this slab">
                                    <x-icon.trash class="size-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <x-button type="button" variant="secondary" size="sm" class="mt-2" data-slab-add>
                        <x-icon.plus class="size-3.5" /> Add a slab
                    </x-button>
                </div>

                <x-input label="Note" name="statutory_note" :value="$salaryComponent->statutory_note"
                    class="mt-4"
                    help="Shown beside the component, so whoever runs payroll knows which rules are loaded." />
            </div>

            <div class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                <x-checkbox label="Counts towards gross" name="affects_gross" :checked="$salaryComponent->affects_gross ?? true"
                    help="Deductions normally leave this off." />
                <x-checkbox label="Prorate on loss of pay" name="prorate_on_lop" :checked="$salaryComponent->prorate_on_lop ?? true"
                    help="Scales the amount by the employee's paid days." />
                <x-checkbox label="Taxable" name="is_taxable" :checked="$salaryComponent->is_taxable ?? true" />
                <x-checkbox label="Statutory" name="is_statutory" :checked="$salaryComponent->is_statutory ?? false"
                    help="Marks the line as a legal contribution such as PF or ESI." />
            </div>
        </x-card>
    </div>

    <div class="space-y-6">
        <x-card title="Status">
            <x-select label="Status" name="status" :selected="$salaryComponent->status ?? 'active'" required
                :options="['active' => 'Active', 'inactive' => 'Inactive']"
                help="Inactive components are not added to new salary structures." />
        </x-card>

        <div class="flex gap-2">
            <x-button class="flex-1">{{ $salaryComponent->exists ? 'Save changes' : 'Create component' }}</x-button>
            <x-button href="{{ route('salary-components.index') }}" variant="secondary">Cancel</x-button>
        </div>
    </div>
</div>
