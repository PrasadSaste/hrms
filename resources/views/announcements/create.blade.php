<x-app-layout title="New announcement">
    <x-page-header title="New announcement" :back="route('announcements.index')" />
    <form method="POST" action="{{ route('announcements.store') }}">
        @csrf
        @include('announcements._form')
    </form>
</x-app-layout>
