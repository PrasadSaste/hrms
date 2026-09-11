<x-guest-layout title="Update your password">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Update your password</h1>
    <p class="mt-1.5 text-sm text-slate-500">
        Your account is still using the temporary password issued by HR. Choose your own before continuing.
    </p>

    @if ($errors->any())
        <x-alert type="error" class="mt-6" :dismissible="false">{{ $errors->first() }}</x-alert>
    @endif

    <form method="POST" action="{{ route('password.change.update') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <label for="current_password" class="form-label">Temporary password <span class="text-rose-500">*</span></label>
            <input type="password" name="current_password" id="current_password" autocomplete="current-password" required class="form-input">
            @error('current_password')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password" class="form-label">New password <span class="text-rose-500">*</span></label>
            <input type="password" name="password" id="password" autocomplete="new-password" required class="form-input">
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="form-label">Confirm new password <span class="text-rose-500">*</span></label>
            <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" required class="form-input">
        </div>

        <x-button size="lg" class="w-full">Save and continue</x-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm text-slate-500 hover:text-slate-800">Sign out instead</button>
    </form>
</x-guest-layout>
