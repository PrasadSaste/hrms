<x-app-layout :title="'Edit ' . $designation->name">
    <x-page-header :title="'Edit ' . $designation->name" :back="route('designations.index')" />
    <form method="POST" action="{{ route('designations.update', $designation) }}">
        @csrf @method('PUT')
        @include('designations._form')
    </form>
</x-app-layout>
