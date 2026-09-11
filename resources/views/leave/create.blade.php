<x-app-layout title="Apply for leave">
    <x-page-header title="Apply for leave" subtitle="Your balance is checked before the request is submitted"
        :back="route('leave.index')" />

    <form method="POST" action="{{ route('leave.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Request details" class="lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select label="Leave type" name="leave_type_id" required placeholder="Choose a leave type"
                        :options="$leaveTypes->mapWithKeys(fn ($t) => [$t->id => $t->name . ($t->is_paid ? '' : ' (unpaid)')])->all()"
                        class="sm:col-span-2" />

                    <x-input label="From" name="start_date" type="date" required :value="now()->addDay()->toDateString()" />
                    <x-input label="To" name="end_date" type="date" required :value="now()->addDay()->toDateString()" />

                    <x-select label="Duration" name="day_type" required :options="$dayTypes" selected="full_day"
                        help="Half days must start and end on the same date." />
                    <x-input label="Contact while away" name="contact_during_leave"
                        help="A phone number your team can reach you on." />

                    <x-textarea label="Reason" name="reason" rows="4" required class="sm:col-span-2"
                        help="Give your approver enough context to decide." />

                    <div class="sm:col-span-2">
                        <label class="form-label" for="attachment">Supporting document</label>
                        <input type="file" name="attachment" id="attachment" accept=".pdf,.jpg,.jpeg,.png"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
                        <p class="form-help">Optional for most types. Required for medical and maternity leave.</p>
                        @error('attachment')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-card>

            <div class="space-y-6">
                <x-card title="Your balance">
                    @forelse ($balance as $row)
                        <div class="border-b border-slate-100 py-2.5 first:pt-0 last:border-0 last:pb-0">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="flex items-center gap-2 text-sm text-slate-700">
                                    <span class="size-2 rounded-full" style="background-color: {{ $row['leave_type']->color }}"></span>
                                    {{ $row['leave_type']->name }}
                                </span>
                                <span class="text-sm font-semibold text-slate-900">
                                    {{ rtrim(rtrim(number_format($row['remaining'], 1), '0'), '.') }}
                                </span>
                            </div>
                            @if ($row['pending'] > 0)
                                <p class="mt-0.5 text-xs text-amber-600">
                                    {{ rtrim(rtrim(number_format($row['pending'], 1), '0'), '.') }} day(s) already pending
                                </p>
                            @endif
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-slate-500">No leave types are available to you yet.</p>
                    @endforelse
                </x-card>

                <x-card title="Before you apply">
                    <ul class="space-y-2 text-sm text-slate-600">
                        <li>Weekends and public holidays in the range are not counted against your balance.</li>
                        <li>Some leave types need advance notice or cap the number of consecutive days.</li>
                        <li>Your approver is notified by email as soon as you submit.</li>
                        <li>You can cancel a request until its last day passes.</li>
                    </ul>
                </x-card>

                <div class="flex gap-2">
                    <x-button class="flex-1">Submit request</x-button>
                    <x-button href="{{ route('leave.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
