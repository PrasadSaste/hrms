<x-app-layout title="New branch">
    <x-page-header title="New branch" subtitle="Add an office location" :back="route('branches.index')" />

    <form method="POST" action="{{ route('branches.store') }}">
        @csrf
        @include('branches._form')
    </form>
</x-app-layout>
