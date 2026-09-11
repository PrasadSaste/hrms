{{--
    Shown wherever somebody might send a message and believe it reached the
    person it names. A redirect that nobody can see is worse than no redirect:
    the whole point of testing is knowing what actually happened.
--}}
@if (filled(config('mail.redirect.to')))
    <x-alert type="warning" class="mb-4" :dismissible="false">
        This is <span class="font-medium">{{ app()->environment() }}</span>, not production, so
        <span class="font-medium">every message goes to {{ config('mail.redirect.to') }}</span>
        instead of the person it names. Who it would have reached travels with it as an
        <span class="font-mono text-xs">X-Original-To</span> header.
    </x-alert>
@endif
