<x-app-layout title="Two-step verification">
    <x-page-header title="Two-step verification"
        subtitle="A code from your phone, on top of your password"
        :back="route('profile.edit')" backLabel="Profile">
        <x-slot:actions>
            @if ($enabled)
                <x-badge color="emerald" dot class="text-sm">On</x-badge>
            @elseif ($required)
                <x-badge color="rose" dot class="text-sm">Required for your role</x-badge>
            @else
                <x-badge color="slate" dot class="text-sm">Off</x-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Shown once, and only once: these are passwords, not settings. --}}
    @if ($codes)
        <x-alert type="warning" class="mb-5" :dismissible="false">
            <p class="font-medium">Save these recovery codes somewhere safe now.</p>
            <p class="mt-1 text-sm">
                Each one signs you in once if you lose your phone. This is the only time they are
                shown — they are stored hashed, so nobody, including an administrator, can read
                them back to you.
            </p>
            <div class="mt-3 grid max-w-md grid-cols-2 gap-1.5 font-mono text-sm">
                @foreach ($codes as $code)
                    <div class="rounded border border-amber-300 bg-white px-2 py-1 text-center tracking-wider">{{ $code }}</div>
                @endforeach
            </div>
        </x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if (! $enabled)
                <x-card title="Set it up">
                    <div class="grid gap-6 sm:grid-cols-[auto_1fr] sm:items-start">
                        <div class="rounded-md border border-slate-200 bg-white p-3">
                            <img src="{{ $qr }}" alt="Scan this with your authenticator app"
                                width="220" height="220" class="block">
                        </div>

                        <div class="space-y-4 text-sm text-slate-600">
                            <ol class="space-y-2">
                                <li><span class="font-medium text-slate-900">1.</span> Install an
                                    authenticator app — Google Authenticator, Microsoft Authenticator
                                    and 1Password all work.</li>
                                <li><span class="font-medium text-slate-900">2.</span> Scan the code,
                                    or type the key below in by hand.</li>
                                <li><span class="font-medium text-slate-900">3.</span> Enter the
                                    six digits it shows to prove it worked.</li>
                            </ol>

                            <div>
                                <p class="eyebrow">Or type this key</p>
                                <p class="mt-1 font-mono text-sm break-all text-slate-900">{{ $secret }}</p>
                            </div>

                            <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-3">
                                @csrf
                                <div class="sm:max-w-48">
                                    <label for="code" class="form-label">Six-digit code</label>
                                    <input type="text" name="code" id="code" required
                                        inputmode="numeric" pattern="[0-9]*" maxlength="6"
                                        autocomplete="one-time-code"
                                        class="form-input text-center tracking-[0.3em] tabular-nums"
                                        placeholder="000000">
                                    @error('code')<p class="form-error">{{ $message }}</p>@enderror
                                </div>
                                <x-button><x-icon.check class="size-4" /> Turn it on</x-button>
                            </form>
                        </div>
                    </div>
                </x-card>
            @else
                <x-card title="It is on"
                    subtitle="You will be asked for a code from your app each time you sign in.">
                    <p class="text-sm text-slate-600">
                        Turned on {{ $user->two_factor_confirmed_at?->format('d M Y') }}.
                        You have <span class="font-medium text-slate-900">{{ $remaining }}</span>
                        unused recovery {{ Str::plural('code', $remaining) }}.
                    </p>

                    @if ($remaining <= 2)
                        <x-alert type="warning" class="mt-4" :dismissible="false">
                            You are running low on recovery codes. Make a new set before you need one.
                        </x-alert>
                    @endif
                </x-card>

                <x-card title="New recovery codes"
                    subtitle="Making a new set immediately stops the old ones working.">
                    <form method="POST" action="{{ route('two-factor.recovery') }}"
                        class="flex flex-wrap items-end gap-3">
                        @csrf
                        <x-input label="Confirm your password" name="password" type="password"
                            autocomplete="current-password" class="sm:w-64" required />
                        <x-button variant="secondary">Make new codes</x-button>
                    </form>
                </x-card>

                <x-card title="Turn it off">
                    @if ($required)
                        <p class="text-sm text-slate-600">
                            Your role requires a second factor, so this cannot be turned off. Ask an
                            administrator if you believe that is wrong.
                        </p>
                    @else
                        <form method="POST" action="{{ route('two-factor.disable') }}"
                            class="flex flex-wrap items-end gap-3">
                            @csrf @method('DELETE')
                            <x-input label="Confirm your password" name="password" type="password"
                                autocomplete="current-password" class="sm:w-64" required />
                            <x-button variant="danger"
                                data-confirm="Turn off two-step verification? Your password becomes the only thing protecting this account.">
                                Turn it off
                            </x-button>
                        </form>
                    @endif
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Why this exists">
                <div class="space-y-3 text-sm text-slate-600">
                    <p>
                        This system holds salaries, bank details and identity documents. A password
                        on its own is one leaked spreadsheet away from being somebody else's.
                    </p>
                    <p>
                        The code changes every thirty seconds and never leaves your phone, so
                        knowing your password is no longer enough.
                    </p>
                </div>
            </x-card>

            <x-card title="If you lose your phone">
                <ul class="space-y-2.5 text-sm text-slate-600">
                    <li><span class="font-medium text-slate-900">1.</span> Use a recovery code. Each works once.</li>
                    <li><span class="font-medium text-slate-900">2.</span> Out of codes? An administrator can reset your second factor from your user account.</li>
                    <li><span class="font-medium text-slate-900">3.</span> Set it up again on the new phone.</li>
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
