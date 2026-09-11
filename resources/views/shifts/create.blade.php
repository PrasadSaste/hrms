<x-app-layout title="New shift">
    <x-page-header title="New shift" :back="route('shifts.index')" />
    <form method="POST" action="{{ route('shifts.store') }}">
        @csrf
        @include('shifts._form')
    </form>
</x-app-layout>
