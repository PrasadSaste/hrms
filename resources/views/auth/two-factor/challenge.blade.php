<x-guest-layout title="Two-step verification">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">One more step</h1>
    <p class="mt-1.5 text-sm text-slate-500">
        Open your authenticator app and enter the six-digit code it is showing.
    </p>

    @if ($errors->any())
        <x-alert type="error" class="mt-6" :dismissible="false">{{ $errors->first() }}</x-alert>
    @endif

    <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-6 space-y-5" data-2fa-code>
        @csrf

        <div>
            <label for="code" class="form-label">Six-digit code</label>
            {{-- inputmode numeric brings up the number pad; autocomplete
                 one-time-code lets a phone offer the code it just received. --}}
            <input type="text" name="code" id="code" required autofocus
                inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                class="form-input text-center text-lg tracking-[0.4em] tabular-nums"
                placeholder="000000">
        </div>

        <x-button size="lg" class="w-full">Verify</x-button>
    </form>

    <details class="mt-6 rounded-md border border-slate-200 bg-white p-4">
        <summary class="cursor-pointer text-sm font-medium text-slate-700">
            I do not have my phone
        </summary>

        <p class="mt-2 text-sm text-slate-600">
            Use one of the recovery codes you saved when you set this up. Each works once.
            @if ($recoveryCodesLeft > 0)
                You have {{ $recoveryCodesLeft }} left.
            @else
                <span class="font-medium text-rose-600">You have none left</span> — ask your
                administrator to reset your second factor.
            @endif
        </p>

        <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-3 space-y-3">
            @csrf
            <x-input label="Recovery code" name="recovery_code" placeholder="abcde-fghij" />
            <x-button variant="secondary" class="w-full">Use a recovery code</x-button>
        </form>
    </details>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="link text-xs font-medium">Sign out instead</button>
    </form>
</x-guest-layout>
