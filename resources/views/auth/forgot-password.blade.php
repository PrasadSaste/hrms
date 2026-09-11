<x-guest-layout title="Forgot password">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Reset your password</h1>
    <p class="mt-1.5 text-sm text-slate-500">
        Enter your work email and we will send you a link to choose a new password.
    </p>

    @if ($errors->any())
        <x-alert type="error" class="mt-6" :dismissible="false">{{ $errors->first() }}</x-alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
        @csrf
        <x-input label="Email address" name="email" type="email" autocomplete="username" required autofocus />
        <x-button size="lg" class="w-full">Send reset link</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500">
        <a href="{{ route('login') }}" class="font-medium link-plain">Back to sign in</a>
    </p>
</x-guest-layout>
