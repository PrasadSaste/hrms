<x-app-layout title="New salary component">
    <x-page-header title="New salary component" :back="route('salary-components.index')" />
    <form method="POST" action="{{ route('salary-components.store') }}">
        @csrf
        @include('payroll.components._form')
    </form>
</x-app-layout>
