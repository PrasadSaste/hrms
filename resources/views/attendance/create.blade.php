<x-app-layout title="Record attendance">
    <x-page-header title="Record attendance" subtitle="Add or correct an attendance entry on behalf of an employee"
        :back="route('attendance.daily')" />

    <form method="POST" action="{{ route('attendance.store') }}">
        @csrf
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Attendance entry" class="lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select label="Employee" name="employee_id" required placeholder="Choose an employee"
                        :selected="request('employee_id')"
                        :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->first_name . ' ' . $e->last_name . ' (' . $e->employee_code . ')'])->all()"
                        class="sm:col-span-2" />

                    <x-input label="Date" name="date" type="date" required :value="$date" max="{{ now()->toDateString() }}" />
                    <x-select label="Shift" name="shift_id" placeholder="Employee default"
                        :options="$shifts->pluck('name', 'id')->all()" />

                    <x-input label="Check-in time" name="check_in" type="time" />
                    <x-input label="Check-out time" name="check_out" type="time" />

                    <x-select label="Status" name="status" required :options="$statuses" selected="present" />
                    <x-textarea label="Remarks" name="remarks" rows="3" class="sm:col-span-2"
                        help="Explain why this entry was added manually." />
                </div>
            </x-card>

            <div class="space-y-6">
                <x-card title="How this works">
                    <ul class="space-y-2 text-sm text-slate-600">
                        <li>Worked hours, late minutes and overtime are recalculated from the times you enter.</li>
                        <li>If you set both times, the status is computed but your explicit choice wins.</li>
                        <li>An existing entry for the same employee and date is updated rather than duplicated.</li>
                        <li>The record is stamped with your name in the audit log.</li>
                    </ul>
                </x-card>

                <div class="flex gap-2">
                    <x-button class="flex-1">Save attendance</x-button>
                    <x-button href="{{ route('attendance.daily') }}" variant="secondary">Cancel</x-button>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
