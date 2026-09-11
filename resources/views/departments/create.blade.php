<x-app-layout title="New department">
    <x-page-header title="New department" :back="route('departments.index')" />
    <form method="POST" action="{{ route('departments.store') }}">
        @csrf
        @include('departments._form')
    </form>
</x-app-layout>
