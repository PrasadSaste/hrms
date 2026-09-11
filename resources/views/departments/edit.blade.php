<x-app-layout :title="'Edit ' . $department->name">
    <x-page-header :title="'Edit ' . $department->name" :back="route('departments.index')" />
    <form method="POST" action="{{ route('departments.update', $department) }}">
        @csrf @method('PUT')
        @include('departments._form')
    </form>
</x-app-layout>
