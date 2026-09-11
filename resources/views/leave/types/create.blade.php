<x-app-layout title="New leave type">
    <x-page-header title="New leave type" :back="route('leave-types.index')" />
    <form method="POST" action="{{ route('leave-types.store') }}">
        @csrf
        @include('leave.types._form')
    </form>
</x-app-layout>
