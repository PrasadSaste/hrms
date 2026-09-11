<x-app-layout title="Edit attendance">
    <x-page-header :title="'Edit attendance for ' . $attendance->employee->full_name"
        :subtitle="$attendance->date->format('l, d F Y')"
        :back="route('attendance.daily', ['date' => $attendance->date->toDateString()])" />

    <form method="POST" action="{{ route('attendance.update', $attendance) }}">
        @csrf @method('PUT')
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Attendance entry" class="lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input label="Check-in time" name="check_in" type="time"
                        :value="$attendance->check_in?->format('H:i')" />
                    <x-input label="Check-out time" name="check_out" type="time"
                        :value="$attendance->check_out?->format('H:i')" />
                    <x-select label="Shift" name="shift_id" :selected="$attendance->shift_id" placeholder="Employee default"
                        :options="$shifts->pluck('name', 'id')->all()" />
                    <x-select label="Status" name="status" required :options="$statuses" :selected="$attendance->status->value" />
                    <x-textarea label="Remarks" name="remarks" :value="$attendance->remarks" rows="3" class="sm:col-span-2" />
                </div>
            </x-card>

            <div class="space-y-6">
                <x-card title="Current values">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Worked</dt>
                            <dd class="font-medium text-slate-900">{{ $attendance->durationLabel() }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Late</dt>
                            <dd class="font-medium text-slate-900">{{ $attendance->late_minutes }} min</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Overtime</dt>
                            <dd class="font-medium text-slate-900">{{ $attendance->overtimeHours() }} h</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Source</dt>
                            <dd class="font-medium text-slate-900">{{ ucfirst($attendance->source) }}</dd>
                        </div>
                        @if ($attendance->hasCheckInCoordinates() || $attendance->hasCheckOutCoordinates())
                            <div class="flex items-start justify-between gap-2 border-t border-slate-100 pt-2.5">
                                <dt class="text-slate-500">Recorded at</dt>
                                <dd class="text-right">
                                    @if ($attendance->hasCheckInCoordinates())
                                        <x-punch-place :attendance="$attendance" side="check_in" class="justify-end" />
                                    @endif
                                    @if ($attendance->hasCheckOutCoordinates())
                                        <x-punch-place :attendance="$attendance" side="check_out" class="justify-end" />
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>
                </x-card>

                <div class="flex gap-2">
                    <x-button class="flex-1">Save changes</x-button>
                    @can('delete', $attendance)
                        <x-button type="button" variant="secondary" form="delete-attendance">Cancel</x-button>
                    @endcan
                </div>

                @can('delete', $attendance)
                    <x-card title="Delete this record">
                        <p class="text-sm text-slate-500">
                            Removing the entry marks the employee absent for this date unless it is a holiday or week off.
                        </p>
                    </x-card>
                @endcan
            </div>
        </div>
    </form>

    @can('delete', $attendance)
        <form method="POST" action="{{ route('attendance.destroy', $attendance) }}" class="mt-4">
            @csrf @method('DELETE')
            <x-button variant="danger" data-confirm="Delete this attendance record?">Delete record</x-button>
        </form>
    @endcan
</x-app-layout>
