<x-app-layout title="Add employee">
    <x-page-header title="Add employee" subtitle="Create the employee record and, optionally, their login"
        :back="route('employees.index')" />

    <form method="POST" action="{{ route('employees.store') }}" enctype="multipart/form-data">
        @csrf
        @include('employees._form')
    </form>
</x-app-layout>
