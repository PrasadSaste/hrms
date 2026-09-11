@php
    // Blade compiles this whole file as text, component attribute values
    // included, so a literal pair of braces anywhere in it is compiled --
    // twice over inside an attribute, which printed the compiled PHP on
    // screen. Built from parts, the pair never appears in the source.
    $tokenExample = '{'.'{ token }'.'}';
@endphp

<x-app-layout :title="'Wording · '.$definition['label']">
    <x-page-header :title="$definition['label']" :subtitle="$definition['description']"
        :back="route('letter-templates.index')" />

    <form method="POST" action="{{ route('letter-templates.update', $type) }}">
        @csrf @method('PUT')

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="The letter">
                    <div class="space-y-4">
                        <x-input label="Heading" name="subject" :value="$template['subject']"
                            help="Printed in capitals across the top of the letter." />

                        <x-rich-text label="Body" name="body" :rows="20" :value="$template['body']" hard-breaks
                            :help="'A line break is kept as a line break, so a list of details stays a list. Use '.$tokenExample.' for the values below.'" />
                    </div>

                </x-card>

                <x-form-actions>
                    <x-button>Save the wording</x-button>
                    <x-button type="button" variant="secondary" data-letter-preview
                        data-preview-url="{{ route('letter-templates.preview', $type) }}">
                        Preview with sample values
                    </x-button>
                    @if ($customised)
                        <x-button variant="ghost" form="reset-letter"
                            data-confirm="Put this letter back to the wording it shipped with?">
                            Reset to the original
                        </x-button>
                    @endif
                </x-form-actions>

                <x-card title="Preview" data-letter-preview-card hidden>
                    <h2 data-letter-preview-subject
                        class="text-center text-sm font-semibold tracking-wide text-slate-900 uppercase"></h2>
                    <div data-letter-preview-body
                        class="prose-letter mx-auto mt-6 max-w-2xl text-sm leading-relaxed text-slate-700"></div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Values you can use">
                    <p class="mb-3 text-sm text-slate-600">
                        Write these as <span class="font-mono text-xs">&#123;&#123; token &#125;&#125;</span>.
                        A value nobody has filled in prints as a dash rather than a gap.
                    </p>
                    <dl class="space-y-2 text-sm">
                        @foreach ($placeholders as $token => $description)
                            <div>
                                <dt class="font-mono text-xs text-brand-700">&#123;&#123; {{ $token }} &#125;&#125;</dt>
                                <dd class="text-xs text-slate-500">{{ $description }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>

                @if ($definition['fields'])
                    <x-card title="Asked for at issue">
                        <ul class="space-y-2 text-sm text-slate-600">
                            @foreach ($definition['fields'] as $name => $field)
                                <li>
                                    <span class="font-medium text-slate-900">{{ $field['label'] }}</span>
                                    @if ($field['required'] ?? false)
                                        <span class="text-xs text-rose-500">required</span>
                                    @endif
                                    @if ($field['help'] ?? null)
                                        <span class="block text-xs text-slate-500">{{ $field['help'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif

                <x-card title="Letters already issued">
                    <p class="text-sm text-slate-600">
                        {{ number_format($issued) }} of these have gone out. They keep the words
                        they were issued with — editing here only changes the next one.
                    </p>
                </x-card>
            </div>
        </div>
    </form>

    @if ($customised)
        <form method="POST" action="{{ route('letter-templates.reset', $type) }}" id="reset-letter" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endif
</x-app-layout>
