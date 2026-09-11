{{--
    A list of rows an administrator can add to and remove from. The template at
    the end is cloned by the browser, so the markup for a row lives in one place
    whether it came from the database or was just added.
--}}
@props(['name', 'rows' => [], 'columns' => [], 'addLabel' => 'Add a row'])

<div data-repeater="{{ $name }}" class="space-y-3">
    <div data-repeater-rows class="space-y-3">
        @foreach ($rows as $index => $row)
            <div data-repeater-row class="rounded-xl border border-slate-200 p-3">
                <div class="space-y-3">
                    @foreach ($columns as $field => $column)
                        @if (($column['type'] ?? 'text') === 'textarea')
                            <x-textarea :name="$name.'['.$index.']['.$field.']'" :label="$column['label']"
                                :rows="$column['rows'] ?? 3" :value="$row[$field] ?? null" />
                        @else
                            <x-input :name="$name.'['.$index.']['.$field.']'" :label="$column['label']"
                                :value="$row[$field] ?? null" />
                        @endif
                    @endforeach
                </div>

                <div class="mt-2 text-right">
                    <button type="button" data-repeater-remove
                        class="inline-flex items-center gap-1 text-xs font-medium text-rose-600 hover:text-rose-700"><x-icon.trash class="size-3.5" /> Remove</button>
                </div>
            </div>
        @endforeach
    </div>

    <x-button type="button" variant="secondary" size="sm" data-repeater-add>
        <x-icon.plus class="size-3.5" /> {{ $addLabel }}
    </x-button>

    <template data-repeater-template>
        <div data-repeater-row class="rounded-xl border border-slate-200 p-3">
            <div class="space-y-3">
                @foreach ($columns as $field => $column)
                    <div class="w-full">
                        <label class="form-label">{{ $column['label'] }}</label>
                        @if (($column['type'] ?? 'text') === 'textarea')
                            <textarea name="{{ $name }}[__INDEX__][{{ $field }}]" rows="{{ $column['rows'] ?? 3 }}"
                                class="form-textarea"></textarea>
                        @else
                            <input type="text" name="{{ $name }}[__INDEX__][{{ $field }}]" class="form-input">
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-2 text-right">
                <button type="button" data-repeater-remove
                    class="inline-flex items-center gap-1 text-xs font-medium text-rose-600 hover:text-rose-700"><x-icon.trash class="size-3.5" /> Remove</button>
            </div>
        </div>
    </template>
</div>
