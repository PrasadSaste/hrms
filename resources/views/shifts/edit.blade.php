<x-app-layout :title="'Edit ' . $shift->name">
    <x-page-header :title="'Edit ' . $shift->name" :back="route('shifts.index')" />
    <form method="POST" action="{{ route('shifts.update', $shift) }}">
        @csrf @method('PUT')
        @include('shifts._form')
    </form>
</x-app-layout>
