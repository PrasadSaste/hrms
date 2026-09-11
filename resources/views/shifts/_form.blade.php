@php
    $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $selectedDays = old('working_days', $shift->working_days ?? [1, 2, 3, 4, 5]);
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <x-card title="Shift">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Name" name="name" :value="$shift->name" required />
                <x-input label="Code" name="code" :value="$shift->code" required />
                <x-select label="Branch" name="branch_id" :selected="$shift->branch_id" placeholder="All branches"
                    :options="$branches->pluck('name', 'id')->all()" />
            </div>
        </x-card>

        <x-card title="Timing">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Start time" name="start_time" type="time" required
                    :value="\Illuminate\Support\Carbon::parse($shift->start_time ?? '09:30')->format('H:i')" />
                <x-input label="End time" name="end_time" type="time" required
                    :value="\Illuminate\Support\Carbon::parse($shift->end_time ?? '18:30')->format('H:i')"
                    help="An end time before the start time is treated as an overnight shift." />
                <x-input label="Grace period (minutes)" name="grace_minutes" type="number" min="0" max="240"
                    :value="$shift->grace_minutes ?? 15" required
                    help="Arrivals within this window are not marked late." />
                <x-input label="Break (minutes)" name="break_minutes" type="number" min="0" max="240"
                    :value="$shift->break_minutes ?? 60" required
                    help="Deducted from the time between check-in and check-out." />
                <x-input label="Full day hours" name="full_day_hours" type="number" step="0.25" min="0" max="24"
                    :value="$shift->full_day_hours ?? 8" required
                    help="Anything beyond this counts as overtime." />
                <x-input label="Half day hours" name="half_day_hours" type="number" step="0.25" min="0" max="24"
                    :value="$shift->half_day_hours ?? 4" required
                    help="Below this the day is marked as a half day." />
            </div>

            <div class="mt-5">
                <span class="form-label">Working days <span class="text-rose-500">*</span></span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($dayNames as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm transition has-checked:border-brand-500 has-checked:bg-brand-50 has-checked:text-brand-700">
                            <input type="checkbox" name="working_days[]" value="{{ $value }}" class="form-checkbox"
                                @checked(in_array($value, (array) $selectedDays))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('working_days')<p class="form-error">{{ $message }}</p>@enderror
            </div>
        </x-card>
    </div>

    <div class="space-y-6">
        <x-card title="Options">
            <div class="space-y-4">
                <x-select label="Status" name="status" :selected="$shift->status ?? 'active'" required
                    :options="['active' => 'Active', 'inactive' => 'Inactive']" />
                <x-checkbox label="Use as the default shift" name="is_default" :checked="$shift->is_default ?? false"
                    help="Applied to employees who have no shift assigned." />
            </div>
        </x-card>

        <div class="flex gap-2">
            <x-button class="flex-1">{{ $shift->exists ? 'Save changes' : 'Create shift' }}</x-button>
            <x-button href="{{ route('shifts.index') }}" variant="secondary">Cancel</x-button>
        </div>
    </div>
</div>
