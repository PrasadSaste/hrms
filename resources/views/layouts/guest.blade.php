<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sign in' }} &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.branding')
</head>
<body class="h-full">
<div class="flex min-h-full flex-col lg:flex-row">

    {{-- Brand panel --}}
    <div class="hidden bg-brand-700 lg:flex lg:w-1/2 lg:flex-col lg:justify-between lg:p-12 xl:p-16">
        @php
            $brandLogo = App\Models\Setting::get('company_logo');
            $brandName = App\Models\Setting::get('company_name', config('app.name'));
        @endphp

        <div class="flex items-center gap-3 text-white">
            @if ($brandLogo)
                {{-- The logo carries the name, so it is not printed again beside it. --}}
                <img src="{{ route('branding.logo') }}?v={{ substr(md5($brandLogo), 0, 8) }}" alt="{{ $brandName }}"
                    class="h-14 w-auto max-w-64 rounded-xl bg-white/95 object-contain p-2">
            @else
                <span class="flex size-11 items-center justify-center rounded-xl bg-white/15 text-lg font-bold">
                    {{ collect(preg_split('/[\s\-]+/', trim($brandName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: 'HR' }}
                </span>
                <span class="text-lg font-semibold">{{ $brandName }}</span>
            @endif
        </div>

        <div class="max-w-lg text-white">
            <h2 class="text-3xl font-semibold tracking-tight xl:text-4xl">
                Everything your people team runs on, in one place.
            </h2>
            <p class="mt-4 text-brand-100">
                Attendance, leave, payroll and employee records, connected end to end
                so a single approval flows through to the salary slip.
            </p>

            <dl class="mt-10 grid grid-cols-2 gap-6 text-sm">
                <div>
                    <dt class="font-semibold">Attendance</dt>
                    <dd class="mt-1 text-brand-200">Punch in, regularise and report by branch.</dd>
                </div>
                <div>
                    <dt class="font-semibold">Leave</dt>
                    <dd class="mt-1 text-brand-200">Balances, approvals and a shared calendar.</dd>
                </div>
                <div>
                    <dt class="font-semibold">Payroll</dt>
                    <dd class="mt-1 text-brand-200">Attendance-driven runs and PDF salary slips.</dd>
                </div>
                <div>
                    <dt class="font-semibold">Access</dt>
                    <dd class="mt-1 text-brand-200">Role-wise permissions down to each screen.</dd>
                </div>
            </dl>
        </div>

        <p class="text-xs text-brand-200">&copy; {{ date('Y') }} {{ $brandName }}</p>
    </div>

    {{-- Form panel --}}
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-8 lg:w-1/2 lg:px-12 xl:px-24">
        <div class="mx-auto w-full max-w-md">
            <div class="mb-8 lg:hidden">
                <x-brand-mark size="lg" />
            </div>

            {{ $slot ?? '' }}
        </div>
    </div>
</div>
<x-toaster />
</body>
</html>
