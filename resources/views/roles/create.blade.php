<x-app-layout title="New role">
    <x-page-header title="New role" subtitle="Pick the permissions this role grants" :back="route('roles.index')" />
    <form method="POST" action="{{ route('roles.store') }}">
        @csrf
        @include('roles._form')
    </form>
</x-app-layout>
