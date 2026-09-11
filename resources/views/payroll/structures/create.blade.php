<x-app-layout title="New salary structure">
    <x-page-header title="New salary structure"
        :subtitle="$employee ? 'For ' . $employee->full_name : 'Define what an employee is paid'"
        :back="route('salary-structures.index')" />

    <form method="POST" action="{{ route('salary-structures.store') }}">
        @csrf
        @include('payroll.structures._form')
    </form>
</x-app-layout>
