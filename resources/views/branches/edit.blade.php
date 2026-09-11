<x-app-layout :title="'Edit ' . $branch->name">
    <x-page-header :title="'Edit ' . $branch->name" :subtitle="$branch->code" :back="route('branches.show', $branch)" />

    <form method="POST" action="{{ route('branches.update', $branch) }}">
        @csrf @method('PUT')
        @include('branches._form')
    </form>
</x-app-layout>
