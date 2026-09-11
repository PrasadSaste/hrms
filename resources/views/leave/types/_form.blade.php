<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <x-card title="Leave type">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Name" name="name" :value="$type->name" required />
                <x-input label="Code" name="code" :value="$type->code" required help="Short unique code, e.g. CL." />
                <x-textarea label="Description" name="description" :value="$type->description" rows="3" class="sm:col-span-2"
                    help="Shown to employees when they choose this type." />
            </div>
        </x-card>

        <x-card title="How it is earned"
            subtitle="A year granted up front, or days credited month by month">
            <div class="space-y-4">
                <x-select label="Earned" name="accrual" :selected="$type->accrual ?? 'yearly'" required
                    :options="['yearly' => 'Granted for the year', 'monthly' => 'Earned monthly']"
                    help="Monthly is how most employers here run it: a fixed number of days credited each month, and less of it while somebody is on probation." />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input label="Days per month" name="days_per_month" type="number" step="0.5" min="0" max="31"
                        :value="$type->days_per_month ?? 1.5"
                        help="What a confirmed employee earns for a full month." />
                    <x-input label="Days per month on probation" name="probation_days_per_month" type="number"
                        step="0.5" min="0" max="31" :value="$type->probation_days_per_month"
                        help="Leave empty and probation earns the same as everybody else." />
                    <x-input label="Start crediting from" name="accrual_starts_on" type="date"
                        :value="optional($type->accrual_starts_on)->toDateString() ?? now()->startOfMonth()->toDateString()"
                        help="Months before this are left alone. Set it to January to fill in the year so far." />
                    <div class="flex items-end">
                        <x-checkbox label="Credit at the start of the month" name="accrue_in_advance"
                            :checked="$type->accrue_in_advance ?? false"
                            help="Off means a month is credited once it has been worked, which is the safer default." />
                    </div>
                </div>

                <p class="rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    Credits are written by the nightly scheduler, one row per person per month, so
                    running it twice never pays twice and a day the server was down is caught up.
                    A month somebody joins or leaves part-way through is credited for the part they
                    were here.
                </p>
            </div>
        </x-card>

        <x-card title="Entitlement">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Days per year" name="days_per_year" type="number" step="0.5" min="0" max="365"
                    :value="$type->days_per_year ?? 0" required
                    help="Granted up front for a yearly type, prorated for mid-year joiners. For a monthly type this is the ceiling instead: 1.5 a month reaches 18 in a year, so a limit of 12 would stop crediting in August. Zero means no ceiling." />
                <x-input label="Maximum consecutive days" name="max_consecutive_days" type="number" min="0" max="365"
                    :value="$type->max_consecutive_days ?? 0" required help="Zero means no limit." />
                <x-input label="Minimum notice (days)" name="min_notice_days" type="number" min="0" max="365"
                    :value="$type->min_notice_days ?? 0" required
                    help="How far in advance the request must be made." />
                <x-input label="Available after (months of service)" name="applicable_after_months" type="number" min="0" max="120"
                    :value="$type->applicable_after_months ?? 0" required />
                <x-input label="Maximum carry-forward days" name="max_carry_forward_days" type="number" step="0.5" min="0" max="365"
                    :value="$type->max_carry_forward_days ?? 0" required />
                <x-select label="Applies to" name="applicable_gender" :selected="$type->applicable_gender ?? 'any'" required
                    :options="['any' => 'Everyone', 'male' => 'Male employees', 'female' => 'Female employees', 'other' => 'Other']" />
            </div>
        </x-card>

        <x-card title="Rules">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-checkbox label="Paid leave" name="is_paid" :checked="$type->is_paid ?? true"
                    help="Unpaid leave becomes loss of pay in payroll." />
                <x-checkbox label="Requires approval" name="requires_approval" :checked="$type->requires_approval ?? true"
                    help="Turn off to auto-approve on submission." />
                <x-checkbox label="Allow half days" name="allow_half_day" :checked="$type->allow_half_day ?? true" />
                <x-checkbox label="Carry unused days forward" name="carry_forward" :checked="$type->carry_forward ?? false" />
                <x-checkbox label="Require a supporting document" name="requires_attachment" :checked="$type->requires_attachment ?? false" />
            </div>
        </x-card>
    </div>

    <div class="space-y-6">
        <x-card title="Appearance">
            <div class="space-y-4">
                <div>
                    <label class="form-label" for="color">Calendar colour</label>
                    <input type="color" name="color" id="color" value="{{ old('color', $type->color ?? '#2563eb') }}"
                        class="h-10 w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
                    <p class="form-help">Used on the leave calendar and balance cards.</p>
                </div>
                <x-select label="Status" name="status" :selected="$type->status ?? 'active'" required
                    :options="['active' => 'Active', 'inactive' => 'Inactive']" />
            </div>
        </x-card>

        <div class="flex gap-2">
            <x-button class="flex-1">{{ $type->exists ? 'Save changes' : 'Create leave type' }}</x-button>
            <x-button href="{{ route('leave-types.index') }}" variant="secondary">Cancel</x-button>
        </div>
    </div>
</div>
