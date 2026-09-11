<x-app-layout title="Issue a letter">
    <x-page-header title="Issue a letter"
        subtitle="Pick the person and the letter, fill in what it asks for, read it, issue it"
        :back="route('letters.index')" />

    {{-- Choosing reloads the page so the right fields and a live preview can be
         shown; issuing is a separate, deliberate post. --}}
    <form method="GET" action="{{ route('letters.create') }}" class="mb-6">
        <x-card title="1. Who and what">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select label="Employee" name="employee_id" :selected="$employee?->id" required
                    placeholder="Choose an employee" data-auto-submit
                    :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->full_name.' ('.$e->employee_code.')'])->all()" />

                <div>
                    <label class="form-label" for="type">Letter <span class="text-rose-500">*</span></label>
                    <select name="type" id="type" class="form-select" required data-auto-submit>
                        <option value="">Choose a letter</option>
                        {{-- A loop variable named $definition would overwrite the
                             one this page is built around, exactly as $component
                             would inside a component. --}}
                        @foreach ($grouped as $group => $groupTypes)
                            <optgroup label="{{ $group }}">
                                @foreach ($groupTypes as $key => $option)
                                    <option value="{{ $key }}" @selected($type === $key)>{{ $option['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @if ($definition)
                        <p class="form-help">{{ $definition['description'] }}</p>
                    @endif
                </div>
            </div>

            <noscript>
                <div class="mt-4"><x-button variant="secondary">Continue</x-button></div>
            </noscript>
        </x-card>
    </form>

    @if ($employee && $definition)
        <form method="POST" action="{{ route('letters.store') }}">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
            <input type="hidden" name="type" value="{{ $type }}">

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    @if ($outstandingAssets->isNotEmpty())
                        {{-- Not a block: whoever signs may have a good reason.
                             But nobody should sign it without being told. --}}
                        <x-alert type="warning">
                            <p class="font-semibold">
                                {{ $employee->first_name }} still has
                                {{ $outstandingAssets->count() }}
                                {{ \Illuminate\Support\Str::plural('item', $outstandingAssets->count()) }}
                                of company property.
                            </p>
                            <ul class="mt-2 list-disc space-y-0.5 pl-5 text-sm">
                                @foreach ($outstandingAssets as $assignment)
                                    <li>
                                        {{ $assignment->asset->name }}
                                        <span class="text-slate-500">
                                            ({{ $assignment->asset->typeLabel() }}, tag {{ $assignment->asset->asset_tag }})
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-2 text-sm">
                                A relieving letter says somebody left cleanly. Take these back first, or
                                issue it knowing what is still out.
                                <a href="{{ route('assets.index', ['status' => 'issued']) }}"
                                    class="font-medium underline">Open the register</a>.
                            </p>
                        </x-alert>
                    @endif

                    @if ($preview)
                        <x-card :title="$preview['subject'] ?: $definition['label']"
                            subtitle="Exactly as it will read. Anything shown as a dash is a value nobody has filled in.">
                            <div class="prose-letter max-w-none text-sm leading-relaxed text-slate-700">
                                {!! App\Support\Markdown::html($preview['body'], hardBreaks: true) !!}
                            </div>
                        </x-card>
                    @endif
                </div>

                <div class="space-y-6">
                    @if ($definition['fields'])
                        <x-card title="2. What this letter needs"
                            subtitle="Save to see these in the preview.">
                            <div class="space-y-4">
                                @foreach ($definition['fields'] as $name => $field)
                                    @php
                                        $value = old('fields.'.$name, $fields[$name] ?? ($field['default'] ?? null));
                                        $inputType = match ($field['type']) {
                                            'date' => 'date',
                                            'money', 'number' => 'number',
                                            default => 'text',
                                        };
                                    @endphp

                                    @if ($field['type'] === 'boolean')
                                        <x-checkbox :label="$field['label']" name="fields[{{ $name }}]"
                                            :checked="(bool) $value" :help="$field['help'] ?? null" />
                                    @elseif ($field['type'] === 'textarea')
                                        <x-textarea :label="$field['label']" name="fields[{{ $name }}]" rows="4"
                                            :value="$value" :required="$field['required'] ?? false"
                                            :help="$field['help'] ?? null" />
                                    @else
                                        <x-input :label="$field['label']" name="fields[{{ $name }}]"
                                            :type="$inputType" :value="$value"
                                            :required="$field['required'] ?? false"
                                            :step="$field['type'] === 'money' ? '0.01' : null"
                                            :help="$field['help'] ?? null" />
                                    @endif
                                @endforeach
                            </div>

                            <div class="mt-4 border-t border-slate-100 pt-4">
                                <x-button type="submit" formmethod="GET" formaction="{{ route('letters.create') }}"
                                    variant="secondary" size="sm" class="w-full">
                                    Update the preview
                                </x-button>
                            </div>
                        </x-card>
                    @endif

                    <x-card title="3. Issue it">
                        <div class="space-y-4">
                            @if ($signatories->isNotEmpty())
                                <div>
                                    <x-select label="Signed by" name="signatory_id" required
                                        :selected="$signatory?->id"
                                        :options="$signatories->mapWithKeys(fn ($person) => [$person->id => $person->label()])->all()"
                                        help="Their name is frozen onto the letter. The company's default is picked for you." />

                                    <x-button type="submit" formmethod="GET"
                                        formaction="{{ route('letters.create') }}"
                                        variant="ghost" size="sm" class="mt-2">
                                        Show this name in the preview
                                    </x-button>
                                </div>
                            @else
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                    Nobody is set up to sign for
                                    {{ $employee->company?->displayName() ?? 'this company' }}, so the letter
                                    will print over “Authorised Signatory” with no name.
                                    @can('companies.manage')
                                        <a class="font-medium underline" href="{{ route('companies.index') }}">Add a signatory</a>
                                        first if that matters.
                                    @endcan
                                </div>
                            @endif

                            <x-checkbox label="Email it to {{ $employee->email }}" name="send_email" :checked="true"
                                help="The letter goes out as a PDF attachment. Either way it appears on their own Letters screen." />

                            <x-button class="w-full"
                                data-confirm="Issue this {{ strtolower($definition['label']) }} to {{ $employee->full_name }}?">
                                Issue the letter
                            </x-button>

                            <p class="text-xs text-slate-500">
                                It is numbered on issue and the wording is fixed at that moment.
                                Editing the template afterwards changes the next letter, not this one.
                            </p>
                        </div>
                    </x-card>

                    <x-card title="Where the values come from">
                        <p class="text-sm text-slate-600">
                            Everything not asked for above is read from
                            {{ $employee->first_name }}'s record, the salary structure in force and
                            the company that employs them. If a figure looks wrong, correct it at
                            the source rather than here.
                        </p>
                        <div class="mt-3">
                            <x-button href="{{ route('employees.show', $employee) }}" variant="secondary" size="sm">
                                Open their profile
                            </x-button>
                        </div>
                    </x-card>
                </div>
            </div>
        </form>
    @else
        <x-card>
            <x-empty title="Choose an employee and a letter"
                message="The fields it needs and a live preview appear once both are picked." />
        </x-card>
    @endif
</x-app-layout>
