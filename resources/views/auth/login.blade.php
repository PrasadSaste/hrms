<x-guest-layout title="Sign in">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Sign in to your account</h1>
    <p class="mt-1.5 text-sm text-slate-500">Use the work email address your HR team registered.</p>

    @if ($errors->any())
        <x-alert type="error" class="mt-6" :dismissible="false">{{ $errors->first() }}</x-alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
        @csrf

        <x-input label="Email address" name="email" type="email" autocomplete="username"
            placeholder="you@company.com" required autofocus />

        <div>
            <div class="flex items-baseline justify-between">
                <label for="password" class="form-label">Password <span class="text-rose-500">*</span></label>
                {{-- Underlined rather than colour alone. A dark brand clears
                     contrast easily and then sits a fifth of a stop from the
                     label beside it — readable, and not recognisable as a
                     link, which is the whole job of this one. --}}
                <a href="{{ route('password.request') }}" class="link text-[12.5px] font-medium">
                    Forgot password?
                </a>
            </div>
            <input type="password" name="password" id="password" autocomplete="current-password" required
                class="form-input @error('password') border-rose-400 @enderror">
            @error('password')<p class="form-error">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-center gap-2.5">
            <input type="checkbox" name="remember" value="1" class="form-checkbox" @checked(old('remember'))>
            <span class="text-sm text-slate-600">Keep me signed in on this device</span>
        </label>

        <x-button size="lg" class="w-full">Sign in</x-button>
    </form>

    @if (app()->environment('local', 'development'))
        <div class="mt-8 rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Demo accounts</p>
            <dl class="mt-2.5 space-y-1.5 text-xs text-slate-600">
                <div class="flex justify-between gap-3">
                    <dt>Super Admin</dt><dd class="font-mono">admin@beyondsure.example</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt>HR Manager</dt><dd class="font-mono">priya.raghavan@beyondsure.example</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt>Accountant</dt><dd class="font-mono">vikram.desai@beyondsure.example</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt>Branch Manager</dt><dd class="font-mono">ananya.iyer@beyondsure.example</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt>Employee</dt><dd class="font-mono">arjun.sharma@beyondsure.example</dd>
                </div>
            </dl>
            <p class="mt-2.5 text-xs text-slate-500">Password for all demo accounts: <span class="font-mono">Password123!</span></p>
        </div>
    @endif
</x-guest-layout>
