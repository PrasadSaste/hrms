<x-app-layout :title="'Edit ' . $role->name">
    <x-page-header :title="App\Support\Roles::label($role->name)"
        :subtitle="'Permissions granted by the ' . $role->name . ' role'" :back="route('roles.index')" />
    <form method="POST" action="{{ route('roles.update', $role) }}">
        @csrf @method('PUT')
        @include('roles._form')
    </form>
</x-app-layout>
