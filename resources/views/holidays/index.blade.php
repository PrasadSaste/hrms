<x-app-layout title="Holidays">
    <x-page-header title="Holiday calendar" :subtitle="'Public and company holidays for ' . $year">
        <x-slot:actions>
            @if ($canManage)
                <x-button type="button" data-dialog-open="add-holiday"><x-icon.plus class="size-4" /> Add holiday</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar :action="route('holidays.index')">
        <x-select label="Year" name="year" :selected="$year"
            :options="collect(range((int) date('Y') + 1, (int) date('Y') - 4))->mapWithKeys(fn ($y) => [$y => $y])->all()"
            class="sm:w-32" />
        <x-select label="Branch" name="branch_id" :selected="$branchId" placeholder="Organisation wide"
            :options="$branches->pluck('name', 'id')->all()" class="sm:w-52" />
        <x-button variant="secondary"><x-icon.search class="size-4" /> Show</x-button>
    </x-filter-bar>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($holidays as $holiday)
            <x-card class="p-4">
                <div class="flex items-start gap-4">
                    <div @class([
                        'flex size-14 shrink-0 flex-col items-center justify-center rounded-lg',
                        'bg-violet-50 text-violet-700' => ! $holiday->date->isPast(),
                        'bg-slate-100 text-slate-400' => $holiday->date->isPast(),
                    ])>
                        <span class="text-lg leading-none font-bold">{{ $holiday->date->format('d') }}</span>
                        <span class="text-[10px] leading-none tracking-wide uppercase">{{ $holiday->date->format('M') }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-slate-900">{{ $holiday->name }}</p>
                        <p class="text-xs text-slate-500">{{ $holiday->date->format('l') }}</p>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            <x-badge color="violet">{{ $holiday->typeLabel() }}</x-badge>
                            @if ($holiday->branch)
                                <x-badge color="slate">{{ $holiday->branch->name }}</x-badge>
                            @endif
                            @if ($holiday->is_recurring)
                                <x-badge color="sky">Recurring</x-badge>
                            @endif
                        </div>
                        @if ($holiday->description)
                            <p class="mt-1.5 text-xs text-slate-500">{{ $holiday->description }}</p>
                        @endif
                    </div>

                    @if ($canManage)
                        <div class="flex shrink-0 flex-col gap-1">
                            <x-button type="button" variant="ghost" size="sm"
                                data-dialog-open="edit-holiday"
                                data-action="{{ route('holidays.update', $holiday) }}"
                                data-fill-name="{{ $holiday->name }}"
                                data-fill-date="{{ $holiday->date->toDateString() }}"
                                data-fill-type="{{ $holiday->type }}"
                                data-fill-description="{{ $holiday->description }}"><x-icon.pencil class="size-3.5" /> Edit</x-button>
                            <form method="POST" action="{{ route('holidays.destroy', $holiday) }}">
                                @csrf @method('DELETE')
                                <x-button variant="ghost" size="sm" class="w-full text-rose-600 hover:bg-rose-50"
                                    data-confirm="Remove {{ $holiday->name }}?"><x-icon.trash class="size-3.5" /> Delete</x-button>
                            </form>
                        </div>
                    @endif
                </div>
            </x-card>
        @empty
            <div class="lg:col-span-3">
                <x-card>
                    <x-empty title="No holidays for {{ $year }}"
                        message="Add the public holidays your offices observe.">
                        <x-slot:action>
                            @if ($canManage)
                                <x-button type="button" data-dialog-open="add-holiday">Add holiday</x-button>
                            @endif
                        </x-slot:action>
                    </x-empty>
                </x-card>
            </div>
        @endforelse
    </div>

    @if ($canManage)
        <x-dialog name="add-holiday" title="Add holiday" size="sm">
            <form id="add-holiday-form" method="POST" action="{{ route('holidays.store') }}">
                @csrf
                <div class="space-y-4 px-5 py-5">
                    <x-input label="Holiday name" name="name" required placeholder="Republic Day" />
                    <x-input label="Date" name="date" type="date" required />
                    <x-select label="Type" name="type" required :options="$types" selected="public" />
                    <x-select label="Branch" name="branch_id" placeholder="All branches"
                        :options="$branches->pluck('name', 'id')->all()"
                        help="Leave blank to apply the holiday everywhere." />
                    <x-textarea label="Description" name="description" rows="2" />
                    <x-checkbox label="Repeats every year on this date" name="is_recurring" />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" data-dialog-close="add-holiday">Cancel</x-button>
                    <x-button form="add-holiday-form">Add holiday</x-button>
                </div>
            </x-slot:footer>
        </x-dialog>

        <x-dialog name="edit-holiday" title="Edit holiday" size="sm">
            <form id="edit-holiday-form" method="POST" action="">
                @csrf @method('PUT')
                <div class="space-y-4 px-5 py-5">
                    <x-input label="Holiday name" name="name" required />
                    <x-input label="Date" name="date" type="date" required />
                    <x-select label="Type" name="type" required :options="$types" />
                    <x-select label="Branch" name="branch_id" placeholder="All branches"
                        :options="$branches->pluck('name', 'id')->all()" />
                    <x-textarea label="Description" name="description" rows="2" />
                    <x-checkbox label="Repeats every year on this date" name="is_recurring" />
                </div>
            </form>

            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" data-dialog-close="edit-holiday">Cancel</x-button>
                    <x-button form="edit-holiday-form">Save changes</x-button>
                </div>
            </x-slot:footer>
        </x-dialog>
    @endif
</x-app-layout>
