<x-app-layout :title="'Edit ' . $type->name">
    <x-page-header :title="'Edit ' . $type->name" :back="route('leave-types.index')" />
    <form method="POST" action="{{ route('leave-types.update', $type) }}">
        @csrf @method('PUT')
        @include('leave.types._form')
    </form>
</x-app-layout>
