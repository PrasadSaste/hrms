<x-app-layout :title="'Edit ' . $employee->full_name">
    <x-page-header :title="'Edit ' . $employee->full_name" :subtitle="$employee->employee_code"
        :back="route('employees.show', $employee)" />

    <form method="POST" action="{{ route('employees.update', $employee) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        @include('employees._form')
    </form>
</x-app-layout>
