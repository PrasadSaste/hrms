<x-app-layout title="New designation">
    <x-page-header title="New designation" :back="route('designations.index')" />
    <form method="POST" action="{{ route('designations.store') }}">
        @csrf
        @include('designations._form')
    </form>
</x-app-layout>
