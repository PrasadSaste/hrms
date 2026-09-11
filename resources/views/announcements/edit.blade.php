<x-app-layout title="Edit announcement">
    <x-page-header :title="'Edit: ' . $announcement->title" :back="route('announcements.show', $announcement)" />
    <form method="POST" action="{{ route('announcements.update', $announcement) }}">
        @csrf @method('PUT')
        @include('announcements._form')
    </form>
</x-app-layout>
