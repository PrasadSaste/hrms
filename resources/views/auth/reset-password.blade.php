<x-guest-layout title="Choose a new password">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Choose a new password</h1>
    <p class="mt-1.5 text-sm text-slate-500">Pick something you have not used on this account before.</p>

    @if ($errors->any())
        <x-alert type="error" class="mt-6" :dismissible="false">{{ $errors->first() }}</x-alert>
    @endif

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-input label="Email address" name="email" type="email" :value="$email" autocomplete="username" required />

        <div>
            <label for="password" class="form-label">New password <span class="text-rose-500">*</span></label>
            <input type="password" name="password" id="password" autocomplete="new-password" required class="form-input">
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="form-label">Confirm new password <span class="text-rose-500">*</span></label>
            <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" required class="form-input">
        </div>

        <x-button size="lg" class="w-full">Reset password</x-button>
    </form>
</x-guest-layout>
