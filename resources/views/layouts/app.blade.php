<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-timezone" content="{{ config('app.timezone') }}">
    <title>{{ $title ?? 'Dashboard' }} &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.branding')
</head>
<body class="h-full">
<div class="min-h-full">
    {{-- Mobile drawer backdrop --}}
    <div data-sidebar-overlay class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"></div>

    @include('partials.sidebar')

    <div class="lg:pl-64">
        @include('partials.topbar')

        <main class="px-4 py-6 sm:px-6 lg:px-8">
            <x-flash />
            {{ $slot ?? '' }}
        </main>

        <footer class="border-t border-slate-200 px-4 py-5 text-center text-xs text-slate-400 sm:px-6 lg:px-8">
            {{ App\Models\Setting::get('company_name', config('app.name')) }} &middot; Human Resource Management System
        </footer>
    </div>
</div>

@include('partials.break-dialog')
    <x-toaster />
</body>
</html>
