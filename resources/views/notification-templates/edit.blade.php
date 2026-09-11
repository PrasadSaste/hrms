<x-app-layout :title="$template['label']">
    <x-page-header
        :title="$template['label']"
        :subtitle="$template['description']"
        :back="route('notification-templates.index')" />

    <form method="POST" action="{{ route('notification-templates.update', $template['key']) }}"
        data-template-form data-preview-url="{{ route('notification-templates.preview', $template['key']) }}">
        @csrf @method('PUT')

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">

                <x-card title="Where this goes"
                    subtitle="Switch a channel off and this notification is simply not sent that way">
                    <div class="space-y-3">
                        @if (in_array('mail', $template['channels'], true))
                            <x-checkbox name="mail_enabled" label="Send by email"
                                :checked="$template['mail_enabled']"
                                help="Uses the subject and message below." />
                        @endif

                        @if (in_array('database', $template['channels'], true))
                            <x-checkbox name="database_enabled" label="Show in the notification bell"
                                :checked="$template['database_enabled']"
                                help="Uses the short title and one-line message below." />
                        @endif
                    </div>
                </x-card>

                @if (in_array('mail', $template['channels'], true))
                    <x-card title="Email">
                        <div class="space-y-4">
                            <x-input name="subject" label="Subject"
                                :value="old('subject', $template['subject'])"
                                data-template-field
                                help="Placeholders work here too." />

                            <x-rich-text name="body" label="Message" :rows="14" hard-breaks
                                :value="old('body', $template['body'])"
                                data-template-field
                                help="Headings, bold, lists and links come through in the email. Click a placeholder to drop it in where the cursor is." />

                            <x-input name="action_label" label="Button text"
                                :value="old('action_label', $template['action_label'])"
                                data-template-field
                                help="The button links back into the portal; the address is set by the system." />
                        </div>
                    </x-card>
                @endif

                @if (in_array('database', $template['channels'], true))
                    <x-card title="In the notification bell">
                        <div class="space-y-4">
                            <x-input name="title" label="Title"
                                :value="old('title', $template['title'])"
                                data-template-field />

                            <x-textarea name="message" label="One-line message" :rows="2"
                                :value="old('message', $template['message'])"
                                data-template-field />
                        </div>
                    </x-card>
                @endif

                <x-form-actions>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-button type="submit">Save changes</x-button>

                        @if (in_array('mail', $template['channels'], true))
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="sr-only" for="test_email">Address to send the test to</label>
                                <input type="email" name="test_email" id="test_email"
                                    value="{{ old('test_email', auth()->user()->email) }}"
                                    placeholder="Address to send a test to"
                                    class="form-input w-64 {{ $errors->has('test_email') ? 'border-rose-400' : '' }}">
                                <x-button variant="secondary" type="submit"
                                    formaction="{{ route('notification-templates.test', $template['key']) }}">
                                    <x-icon.mail class="size-4" /> Send a test
                                </x-button>
                            </div>
                        @endif
                    </div>

                    @error('test_email')
                        <p class="form-error w-full">{{ $message }}</p>
                    @enderror

                    @if ($template['customised'])
                        <x-button variant="ghost" size="sm" type="submit"
                            formaction="{{ route('notification-templates.reset', $template['key']) }}"
                            data-confirm="Put this notification back to the wording it came with?">
                            Restore the original wording
                        </x-button>
                    @endif
                </x-form-actions>
            </div>

            {{-- The preview stays in view while the form below it is scrolled. --}}
            <div class="space-y-6 self-start lg:sticky lg:top-6">
                <x-card title="Placeholders" subtitle="Click one to drop it into the field you were editing">
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($template['placeholders'] as $token => $meaning)
                            @php $literal = '{{ '.$token.' }}'; @endphp
                            <button type="button" data-placeholder="{{ $literal }}"
                                class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700 transition hover:bg-brand-50 hover-ink"
                                title="{{ $meaning }}">{{ $token }}</button>
                        @endforeach
                    </div>
                    <p class="form-help mt-3">
                        Anything the system has no value for shows as a dash rather than a gap.
                    </p>
                </x-card>

                <x-card title="Preview" subtitle="With example details, updating as you type">
                    <div data-preview class="space-y-4">
                        @if (in_array('mail', $template['channels'], true))
                            <div>
                                <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">Subject</p>
                                <p class="mt-1 text-sm font-medium text-slate-900" data-preview-subject></p>
                            </div>
                            <div class="max-h-96 overflow-y-auto rounded-lg border border-slate-200 bg-white p-4">
                                <div class="prose-preview text-sm text-slate-700" data-preview-body></div>
                                <p class="mt-4">
                                    <span class="inline-flex rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-medium text-white"
                                        data-preview-action></span>
                                </p>
                            </div>
                        @endif

                        @if (in_array('database', $template['channels'], true))
                            <div class="rounded-lg border border-slate-200 p-3">
                                <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">In the bell</p>
                                <p class="mt-1.5 text-sm font-medium text-slate-900" data-preview-title></p>
                                <p class="text-sm text-slate-600" data-preview-message></p>
                            </div>
                        @endif
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-app-layout>
