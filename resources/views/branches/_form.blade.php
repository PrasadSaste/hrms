@php
    $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $selectedDays = old('working_days', $branch->working_days ?? [1, 2, 3, 4, 5]);
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <x-card title="Branch details">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Branch name" name="name" :value="$branch->name" required />
                <x-input label="Branch code" name="code" :value="$branch->code" required
                    help="A short unique code such as BLR or HYD." />
                <x-input label="Email" name="email" type="email" :value="$branch->email" />
                <x-input label="Phone" name="phone" :value="$branch->phone" />
            </div>
        </x-card>

        <x-card title="Address">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Address line 1" name="address_line1" :value="$branch->address_line1" class="sm:col-span-2" />
                <x-input label="Address line 2" name="address_line2" :value="$branch->address_line2" class="sm:col-span-2" />
                <x-input label="City" name="city" :value="$branch->city" />
                <x-input label="State" name="state" :value="$branch->state" />
                <x-input label="Country" name="country" :value="$branch->country ?? 'India'" />
                <x-input label="Postal code" name="postal_code" :value="$branch->postal_code" />
            </div>
        </x-card>

        <x-card title="Location and geofence"
            subtitle="Where the branch is, and how far from it a punch may be made">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Latitude" name="latitude" :value="$branch->latitude" inputmode="decimal"
                    placeholder="17.4401234" help="Between -90 and 90." />
                <x-input label="Longitude" name="longitude" :value="$branch->longitude" inputmode="decimal"
                    placeholder="78.3489012" help="Between -180 and 180." />
                <x-input label="Allowed radius (metres)" name="geofence_radius_metres" type="number" min="20"
                    max="50000" :value="$branch->geofence_radius_metres"
                    :help="'Leave empty to use the organisation-wide default of ' . (int) \App\Models\Setting::get('attendance_geofence_radius', 200) . ' m.'" />

                <div class="flex items-end">
                    <x-button type="button" variant="secondary" class="w-full" data-locate
                        data-locate-latitude="latitude" data-locate-longitude="longitude">
                        Use my current location
                    </x-button>
                </div>
            </div>

            <p class="form-help mt-3" data-locate-status role="status"></p>

            <p class="mt-3 text-sm text-slate-500">
                Punches made further than the radius from this point are flagged, the people
                you nominate under Settings are alerted, and the day's flagged punches are
                emailed as a report each evening. A branch left without coordinates is never
                flagged.
                @if ($branch->hasCoordinates())
                    <a href="{{ $branch->mapUrl() }}" target="_blank" rel="noopener"
                        class="link font-medium">See the saved point on a map</a>.
                @endif
            </p>
        </x-card>

        <x-card title="Working hours" subtitle="Used to calculate late arrivals and working days">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Start time" name="work_start_time" type="time" required
                    :value="\Illuminate\Support\Carbon::parse($branch->work_start_time ?? '09:30')->format('H:i')" />
                <x-input label="End time" name="work_end_time" type="time" required
                    :value="\Illuminate\Support\Carbon::parse($branch->work_end_time ?? '18:30')->format('H:i')" />
            </div>

            <div class="mt-5">
                <span class="form-label">Working days <span class="text-rose-500">*</span></span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($days as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm transition has-checked:border-brand-500 has-checked:bg-brand-50 has-checked:text-brand-700">
                            <input type="checkbox" name="working_days[]" value="{{ $value }}" class="form-checkbox"
                                @checked(in_array($value, (array) $selectedDays))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('working_days')<p class="form-error">{{ $message }}</p>@enderror
            </div>

            @php $saturdayOffs = old('saturday_offs', $branch->saturday_offs ?? []); @endphp
            <div class="mt-5 border-t border-slate-100 pt-5">
                <span class="form-label">Saturdays off</span>
                <p class="form-help mb-2">
                    Only applies while Saturday is ticked above. Choose which Saturdays of the
                    month are <em>not</em> worked — first and third is the usual alternate-Saturday
                    week. Leave them all clear and every Saturday is worked.
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach ([1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th'] as $week => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm transition has-checked:border-brand-500 has-checked:bg-brand-50 has-checked:text-brand-700">
                            <input type="checkbox" name="saturday_offs[]" value="{{ $week }}" class="form-checkbox"
                                @checked(in_array($week, (array) $saturdayOffs))>
                            {{ $label }} Saturday
                        </label>
                    @endforeach
                </div>
                @error('saturday_offs')<p class="form-error">{{ $message }}</p>@enderror

                <p class="mt-3 text-xs text-slate-500">
                    An off Saturday counts as a weekly off everywhere: leave taken across it does
                    not spend a day, payroll does not count it as a working day, and the roster
                    shows it as an off rather than an absence.
                    <span class="mt-1 block">
                        A shift that lists its own working days overrides this branch, so add
                        Saturday to that shift — or clear its working days so it follows the
                        branch — before the pattern reaches anybody on it.
                    </span>
                </p>
            </div>
        </x-card>
    </div>

    <div class="space-y-6">
        <x-card title="Configuration">
            <div class="space-y-4">
                <x-select label="Time zone" name="timezone" :selected="$branch->timezone ?? config('app.timezone')" required
                    :options="collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => $tz])->all()" />

                <x-select label="Branch manager" name="manager_id" :selected="$branch->manager_id" placeholder="Not assigned"
                    :options="$managers->mapWithKeys(fn ($e) => [$e->id => $e->first_name . ' ' . $e->last_name . ' (' . $e->employee_code . ')'])->all()"
                    help="Approves leave and attendance for this branch." />

                <x-select label="Status" name="status" :selected="$branch->status ?? 'active'" required
                    :options="['active' => 'Active', 'inactive' => 'Inactive']" />

                <x-checkbox label="This is the head office" name="is_head_office" :checked="$branch->is_head_office ?? false" />
            </div>
        </x-card>

        <div class="flex gap-2">
            <x-button class="flex-1">{{ $branch->exists ? 'Save changes' : 'Create branch' }}</x-button>
            <x-button href="{{ route('branches.index') }}" variant="secondary">Cancel</x-button>
        </div>
    </div>
</div>
